<?php
// api/admin_menus.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php'; // pastikan path benar

// Ensure PDO throws exceptions
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$method = $_SERVER['REQUEST_METHOD'];

// helper
function send($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

try {
    if ($method === 'GET') {
        // return categories list if requested: ?categories=1
        if (isset($_GET['categories']) && ($_GET['categories'] === '1' || $_GET['categories'] === 'true')) {
            $stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name ASC");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            send(['success' => true, 'data' => $rows]);
        }

        // if id provided => single menu (include category_name)
        if (isset($_GET['id']) && is_numeric($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT m.id, m.category_id, c.name AS category_name, m.code, m.name, m.price, m.stock, m.available, m.description, m.created_at
                                   FROM menus m
                                   LEFT JOIN categories c ON m.category_id = c.id
                                   WHERE m.id = :id LIMIT 1");
            $stmt->execute([':id' => intval($_GET['id'])]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) send(['success' => false, 'error' => 'Not found'], 404);
            send(['success' => true, 'data' => $row]);
        }

        // list with pagination + search (include category_name)
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(5, intval($_GET['per_page'] ?? 25));
        $q = trim($_GET['q'] ?? '');

        $where = "1=1";
        $params = [];

        if ($q !== '') {
            $where = "(m.name LIKE :q OR m.code LIKE :q)";
            $params[':q'] = "%$q%";
        }

        // count total
        $countStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM menus m WHERE $where");
        $countStmt->execute($params);
        $total_rows = (int)$countStmt->fetchColumn();

        $offset = ($page - 1) * $per_page;
        $sql = "SELECT m.id, m.category_id, c.name AS category_name, m.code, m.name, m.price, m.stock, m.available, m.description, m.created_at
                FROM menus m
                LEFT JOIN categories c ON m.category_id = c.id
                WHERE $where
                ORDER BY m.id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
        $stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_pages = (int)ceil($total_rows / $per_page);

        send([
            'success' => true,
            'data' => [
                'rows' => $rows,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total_rows' => $total_rows,
                    'total_pages' => $total_pages
                ]
            ]
        ]);
    }

    // read raw body for POST/PUT/DELETE
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($method === 'POST') {
        // create
        $name = trim($input['name'] ?? '');
        $code = trim($input['code'] ?? '');
        $price = $input['price'] ?? 0;
        $stock = intval($input['stock'] ?? 0);
        $available = isset($input['available']) ? (int)($input['available'] ? 1 : 0) : 1;
        $description = trim($input['description'] ?? '');
        // category_id may be null
        $category_id = array_key_exists('category_id', $input) && ($input['category_id'] !== null && $input['category_id'] !== '') ? intval($input['category_id']) : null;

        if ($name === '') send(['success' => false, 'error' => 'Nama diperlukan'], 400);

        // generate code if empty (unique)
        if ($code === '') {
            do {
                $code = 'M' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
                $chk = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE code = :code");
                $chk->execute([':code' => $code]);
                $exists = (int)$chk->fetchColumn();
            } while ($exists);
        } else {
            // ensure unique
            $chk = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE code = :code");
            $chk->execute([':code' => $code]);
            if ((int)$chk->fetchColumn() > 0) send(['success' => false, 'error' => 'Code sudah ada'], 409);
        }

        $stmt = $pdo->prepare("INSERT INTO menus (category_id, code, name, price, stock, available, description, created_at) VALUES (:category_id, :code, :name, :price, :stock, :available, :description, NOW())");
        $stmt->execute([
            ':category_id' => $category_id,
            ':code' => $code,
            ':name' => $name,
            ':price' => $price,
            ':stock' => $stock,
            ':available' => $available,
            ':description' => $description
        ]);
        $newId = $pdo->lastInsertId();
        send(['success' => true, 'id' => $newId], 201);
    }

    if ($method === 'PUT') {
        // update
        $id = intval($input['id'] ?? 0);
        if ($id <= 0) send(['success' => false, 'error' => 'ID diperlukan'], 400);
        // fetch existing
        $chk = $pdo->prepare("SELECT * FROM menus WHERE id = :id");
        $chk->execute([':id' => $id]);
        $existing = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$existing) send(['success' => false, 'error' => 'Menu tidak ditemukan'], 404);

        $name = trim($input['name'] ?? $existing['name']);
        $code = trim($input['code'] ?? $existing['code']);
        $price = isset($input['price']) ? $input['price'] : $existing['price'];
        $stock = isset($input['stock']) ? intval($input['stock']) : intval($existing['stock']);
        $available = isset($input['available']) ? (int)($input['available'] ? 1 : 0) : (int)$existing['available'];
        $description = trim($input['description'] ?? $existing['description']);
        $category_id = array_key_exists('category_id', $input) ? ($input['category_id'] === null || $input['category_id'] === '' ? null : intval($input['category_id'])) : $existing['category_id'];

        // if code changed, ensure uniqueness
        if ($code !== $existing['code']) {
            $chk2 = $pdo->prepare("SELECT COUNT(*) FROM menus WHERE code = :code AND id != :id");
            $chk2->execute([':code' => $code, ':id' => $id]);
            if ((int)$chk2->fetchColumn() > 0) send(['success' => false, 'error' => 'Code sudah dipakai oleh menu lain'], 409);
        }

        $stmt = $pdo->prepare("UPDATE menus SET category_id = :category_id, code = :code, name = :name, price = :price, stock = :stock, available = :available, description = :description WHERE id = :id");
        $stmt->execute([
            ':category_id' => $category_id,
            ':code' => $code,
            ':name' => $name,
            ':price' => $price,
            ':stock' => $stock,
            ':available' => $available,
            ':description' => $description,
            ':id' => $id
        ]);
        send(['success' => true]);
    }

    if ($method === 'DELETE') {
        $id = intval($input['id'] ?? ($_GET['id'] ?? 0));
        if ($id <= 0) send(['success' => false, 'error' => 'ID diperlukan'], 400);

        // optionally check related order_items (integrity), but for now do hard delete
        $stmt = $pdo->prepare("DELETE FROM menus WHERE id = :id");
        $stmt->execute([':id' => $id]);

        send(['success' => true]);
    }

    send(['success' => false, 'error' => 'Method not allowed'], 405);
} catch (Exception $e) {
    // avoid leaking sensitive info in production - but for dev, show message
    send(['success' => false, 'error' => 'Internal error', 'detail' => $e->getMessage()], 500);
}
