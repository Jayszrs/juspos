<?php
// api/admin_members.php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php'; // pastikan $pdo tersedia

// ensure PDO throws exceptions
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// small sender helper
function send($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// require login
if (empty($_SESSION['user_id'])) {
    send(['success' => false, 'error' => 'Unauthorized'], 401);
}

try {
    // Discover available columns from table (safer than brittle manual assumption)
    $colsStmt = $pdo->query("DESCRIBE members");
    $columns = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0); // array of field names

    // define canonical field order to present
    $preferred = ['id','code','name','phone','point','created_at','updated_at'];
    $available = [];
    foreach ($preferred as $c) {
        if (in_array($c, $columns, true)) $available[] = $c;
    }
    // fallback: if none of preferred found, use all discovered columns
    if (empty($available)) $available = $columns;

    $colsSql = implode(', ', array_map(function($c){ return "`$c`"; }, $available));

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // single record
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $id = (int) $_GET['id'];
            $stmt = $pdo->prepare("SELECT $colsSql FROM members WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) send(['success'=>false,'error'=>'Not found'],404);
            send(['success'=>true,'data'=>$row]);
        }

        // list + search + pagination
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(1, intval($_GET['per_page'] ?? 25));
        $q = trim($_GET['q'] ?? '');
        $offset = ($page - 1) * $per_page;

        $where = "1=1";
        $params = [];

        if ($q !== '') {
            // search against code/name/phone if these columns exist
            $searchCols = array_values(array_intersect(['code','name','phone'], $columns));
            if (!empty($searchCols)) {
                $parts = [];
                foreach ($searchCols as $i => $c) {
                    $parts[] = "`$c` LIKE :q";
                }
                $where = '(' . implode(' OR ', $parts) . ')';
                $params[':q'] = "%$q%";
            }
        }

        // count
        $countSql = "SELECT COUNT(*) as cnt FROM members WHERE $where";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total_rows = (int) $countStmt->fetchColumn();

        // select rows (use LIMIT ... OFFSET form)
        $sql = "SELECT $colsSql FROM members WHERE $where ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        // bind params (string search first)
        foreach ($params as $k=>$v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_pages = $per_page ? (int) ceil($total_rows / $per_page) : 1;

        send([
            'success'=>true,
            'data'=>[
                'rows'=>$rows,
                'pagination'=>[
                    'page'=>$page,
                    'per_page'=>$per_page,
                    'total_rows'=>$total_rows,
                    'total_pages'=>$total_pages
                ]
            ]
        ]);
    }

    // read JSON body
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($method === 'POST') {
        // create new member
        $code = isset($input['code']) ? trim($input['code']) : null;
        $name = isset($input['name']) ? trim($input['name']) : '';
        $phone = isset($input['phone']) ? trim($input['phone']) : null;
        // optional point column
        $point = in_array('point', $columns, true) ? (isset($input['point']) ? intval($input['point']) : 0) : null;

        if ($name === '') send(['success'=>false,'error'=>'Field name required'], 400);

        // build insert dynamically depending on available columns
        $insCols = [];
        $insVals = [];
        $params = [];

        if (in_array('code', $columns, true)) { $insCols[] = 'code'; $insVals[] = ':code'; $params[':code'] = $code ?: null; }
        if (in_array('name', $columns, true)) { $insCols[] = 'name'; $insVals[] = ':name'; $params[':name'] = $name; }
        if (in_array('phone', $columns, true)) { $insCols[] = 'phone'; $insVals[] = ':phone'; $params[':phone'] = $phone ?: null; }
        if (in_array('point', $columns, true)) { $insCols[] = 'point'; $insVals[] = ':point'; $params[':point'] = $point; }
        if (in_array('created_at', $columns, true)) {
            // we'll set created_at via NOW(); no param
            $insCols[] = 'created_at';
            $insVals[] = 'NOW()';
        }

        if (empty($insCols)) send(['success'=>false,'error'=>'No writable columns found'], 500);

        // prepare sql
        $sql = "INSERT INTO members (" . implode(', ', $insCols) . ") VALUES (" . implode(', ', $insVals) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $newId = $pdo->lastInsertId();

        // return created row
        $stmt2 = $pdo->prepare("SELECT $colsSql FROM members WHERE id = :id LIMIT 1");
        $stmt2->execute([':id' => $newId]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        send(['success'=>true,'data'=>$row], 201);
    }

    if ($method === 'PUT') {
        if (!isset($input['id']) || !is_numeric($input['id'])) send(['success'=>false,'error'=>'id wajib untuk update'], 400);
        $id = (int)$input['id'];

        // build dynamic update fields
        $fields = [];
        $params = [':id' => $id];

        foreach (['code','name','phone','point'] as $f) {
            if (array_key_exists($f, $input) && in_array($f, $columns, true)) {
                $fields[] = "`$f` = :$f";
                $params[":$f"] = ($f === 'point' ? intval($input[$f]) : trim($input[$f]));
            }
        }

        if (empty($fields)) send(['success'=>false,'error'=>'Tidak ada field untuk diupdate'], 400);

        // updated_at if exists
        if (in_array('updated_at', $columns, true)) {
            $fields[] = "`updated_at` = NOW()";
        }

        $sql = "UPDATE members SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $stmt2 = $pdo->prepare("SELECT $colsSql FROM members WHERE id = :id LIMIT 1");
        $stmt2->execute([':id' => $id]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);
        send(['success'=>true,'data'=>$row]);
    }

    if ($method === 'DELETE') {
        if (!isset($input['id']) || !is_numeric($input['id'])) send(['success'=>false,'error'=>'id wajib untuk hapus'], 400);
        $id = (int)$input['id'];
        $stmt = $pdo->prepare("DELETE FROM members WHERE id = :id");
        $stmt->execute([':id' => $id]);
        send(['success'=>true]);
    }

    send(['success'=>false,'error'=>'Method not allowed'], 405);

} catch (PDOException $ex) {
    // show detail for dev; in production you may hide detail
    send(['success'=>false,'error'=>'Internal error','detail'=>$ex->getMessage()], 500);
} catch (Exception $ex) {
    send(['success'=>false,'error'=>'Internal error','detail'=>$ex->getMessage()], 500);
}
