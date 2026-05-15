<?php
// api/products.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';
session_start(); // safe: check session once

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $category = isset($_GET['category']) ? trim($_GET['category']) : null;
        $search = isset($_GET['search']) ? trim($_GET['search']) : null;

        $sql = "SELECT m.id, m.code, m.name, m.price, m.stock, m.available, m.description, c.code AS category_code, c.name AS category_name
                FROM menus m
                LEFT JOIN categories c ON m.category_id = c.id";
        $conds = [];
        $params = [];

        if ($category && strtoupper($category) !== 'ALL') {
            // user may pass category code (BEVERAGE, FOOD, ...) or id; we prefer code
            $conds[] = 'c.code = :cat';
            $params[':cat'] = strtoupper($category);
        }
        if ($search) {
            $conds[] = '(m.name LIKE :q OR m.code LIKE :q OR m.description LIKE :q)';
            $params[':q'] = "%$search%";
        }
        if ($conds) $sql .= ' WHERE ' . implode(' AND ', $conds);
        $sql .= ' ORDER BY m.name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // cast numeric fields to proper types for JS
        foreach ($rows as &$r) {
            $r['price'] = (float)$r['price'];
            $r['stock'] = (int)$r['stock'];
            $r['available'] = (int)$r['available'];
        }

        echo json_encode($rows);
        exit;
    }

    if ($method === 'POST') {
        // Admin-only: create product (menu)
        if (empty($_SESSION['user_id']) || empty($_SESSION['user_role']) || strtoupper($_SESSION['user_role']) !== 'ADMIN') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Forbidden: admin only']);
            exit;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
            exit;
        }

        $code = isset($payload['code']) ? trim($payload['code']) : null;
        $name = isset($payload['name']) ? trim($payload['name']) : null;
        $price = isset($payload['price']) ? (float)$payload['price'] : 0;
        $stock = isset($payload['stock']) ? (int)$payload['stock'] : 0;
        $description = isset($payload['description']) ? trim($payload['description']) : null;
        $category_code = isset($payload['category_code']) ? strtoupper(trim($payload['category_code'])) : null;

        if (!$code || !$name) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'code and name are required']);
            exit;
        }

        // find category_id from code
        $category_id = null;
        if ($category_code) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE code = :code LIMIT 1");
            $stmt->execute([':code' => $category_code]);
            $row = $stmt->fetch();
            if ($row) $category_id = (int)$row['id'];
        }
        // fallback: set to OTHERS
        if (!$category_id) {
            $stmt = $pdo->prepare("SELECT id FROM categories WHERE code = 'OTHERS' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            $category_id = $row ? (int)$row['id'] : null;
        }

        // insert (use prepared stmt)
        $stmt = $pdo->prepare("INSERT INTO menus (category_id, code, name, price, stock, available, description, created_at)
                               VALUES (:category_id, :code, :name, :price, :stock, :available, :description, NOW())");
        $stmt->execute([
            ':category_id' => $category_id,
            ':code' => $code,
            ':name' => $name,
            ':price' => $price,
            ':stock' => $stock,
            ':available' => $stock > 0 ? 1 : 0,
            ':description' => $description
        ]);
        $newId = (int)$pdo->lastInsertId();
        echo json_encode(['success' => true, 'menu_id' => $newId]);
        exit;
    }

    // other methods not implemented
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
    exit;
}
