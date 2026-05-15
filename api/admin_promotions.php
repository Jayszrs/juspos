<?php
// api/admin_promotions.php
// Robust CRUD untuk promotions — tolerant terhadap absennya kolom updated_at
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php'; // pastikan benar
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection missing']);
    exit;
}

// helper respond
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// cek apakah promotions table punya kolom updated_at
try {
    $hasUpdated = false;
    $colStmt = $pdo->query("SHOW COLUMNS FROM promotions LIKE 'updated_at'");
    if ($colStmt && $colStmt->fetch()) $hasUpdated = true;
} catch (Exception $e) {
    // jika query SHOW COLUMNS gagal, anggap tidak ada
    $hasUpdated = false;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // jika minta single ?id=...
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $id = (int) $_GET['id'];
            $select = 'id, code, `type`, `value`, active, created_at';
            if ($hasUpdated) $select .= ', updated_at';
            $stmt = $pdo->prepare("SELECT {$select} FROM promotions WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) respond(['success' => false, 'error' => 'Promo tidak ditemukan'], 404);
            respond(['success' => true, 'data' => $row]);
        }

        // list paginated
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 25;
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $offset = ($page - 1) * $per_page;

        $where = '';
        $params = [];
        if ($q !== '') {
            $where = "WHERE code LIKE :q OR `type` LIKE :q";
            $params[':q'] = '%' . $q . '%';
        }

        // hitung total
        $countSql = "SELECT COUNT(*) as cnt FROM promotions $where";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total_rows = (int) $countStmt->fetchColumn();

        // select rows
        $select = 'id, code, `type`, `value`, active, created_at';
        if ($hasUpdated) $select .= ', updated_at';
        $sql = "SELECT {$select} FROM promotions $where ORDER BY id DESC LIMIT :off, :lim";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
        $stmt->bindValue(':lim', (int)$per_page, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pagination = [
            'page' => $page,
            'per_page' => $per_page,
            'total_rows' => $total_rows,
            'total_pages' => $per_page ? (int) ceil($total_rows / $per_page) : 1
        ];
        respond(['success' => true, 'data' => ['rows' => $rows, 'pagination' => $pagination]]);
    }

    // baca JSON body (POST/PUT/DELETE)
    $body = file_get_contents('php://input');
    $payload = json_decode($body, true);

    if ($method === 'POST') {
        if (!is_array($payload)) respond(['success' => false, 'error' => 'Invalid JSON'], 400);
        $code = trim($payload['code'] ?? '');
        $type = trim($payload['type'] ?? '');
        $value = isset($payload['value']) ? floatval($payload['value']) : 0;
        $active = isset($payload['active']) ? (int)$payload['active'] : 1;

        if ($code === '' || $type === '') respond(['success' => false, 'error' => 'Fields code and type are required'], 400);

        $stmt = $pdo->prepare("INSERT INTO promotions (code, `type`, `value`, active, created_at) VALUES (:code, :type, :value, :active, NOW())");
        $stmt->execute([':code'=>$code, ':type'=>$type, ':value'=>$value, ':active'=>$active ? 1 : 0]);
        $id = $pdo->lastInsertId();

        $select = 'id, code, `type`, `value`, active, created_at';
        if ($hasUpdated) $select .= ', updated_at';
        $r = $pdo->prepare("SELECT {$select} FROM promotions WHERE id = :id LIMIT 1");
        $r->execute([':id' => $id]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        respond(['success' => true, 'data' => $row], 201);
    }

    if ($method === 'PUT') {
        if (!is_array($payload) || empty($payload['id'])) respond(['success' => false, 'error' => 'Payload invalid or id missing'], 400);
        $id = (int)$payload['id'];

        $fields = [];
        $params = [':id' => $id];
        if (array_key_exists('code', $payload)) { $fields[] = "code = :code"; $params[':code'] = trim($payload['code']); }
        if (array_key_exists('type', $payload)) { $fields[] = "`type` = :type"; $params[':type'] = trim($payload['type']); }
        if (array_key_exists('value', $payload)) { $fields[] = "`value` = :value"; $params[':value'] = floatval($payload['value']); }
        if (array_key_exists('active', $payload)) { $fields[] = "active = :active"; $params[':active'] = (int)$payload['active'] ? 1 : 0; }

        if (count($fields) === 0) respond(['success' => false, 'error' => 'Tidak ada field untuk diupdate'], 400);

        // tambahkan updated_at jika ada kolom tersebut
        if ($hasUpdated) {
            $fields[] = "updated_at = NOW()";
        }

        $sql = "UPDATE promotions SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $select = 'id, code, `type`, `value`, active, created_at';
        if ($hasUpdated) $select .= ', updated_at';
        $r = $pdo->prepare("SELECT {$select} FROM promotions WHERE id = :id LIMIT 1");
        $r->execute([':id' => $id]);
        $row = $r->fetch(PDO::FETCH_ASSOC);
        respond(['success' => true, 'data' => $row]);
    }

    if ($method === 'DELETE') {
        if (!is_array($payload) || empty($payload['id'])) respond(['success' => false, 'error' => 'Payload invalid or id missing'], 400);
        $id = (int)$payload['id'];
        $stmt = $pdo->prepare("DELETE FROM promotions WHERE id = :id");
        $stmt->execute([':id'=>$id]);
        respond(['success' => true]);
    }

    respond(['success' => false, 'error' => 'Method not allowed'], 405);
} catch (PDOException $ex) {
    respond(['success' => false, 'error' => 'Internal error', 'detail' => $ex->getMessage()], 500);
} catch (Exception $e) {
    respond(['success' => false, 'error' => 'Internal error', 'detail' => $e->getMessage()], 500);
}
