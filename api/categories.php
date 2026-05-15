<?php
// api/categories.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

try {
    $stmt = $pdo->prepare("SELECT id, code, name FROM categories ORDER BY id ASC");
    $stmt->execute();
    $rows = $stmt->fetchAll();
    echo json_encode($rows);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error', 'detail' => $e->getMessage()]);
}
