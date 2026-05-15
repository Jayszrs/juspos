<?php
// api/members.php (GET ?search=..., POST untuk create)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php'; // sesuaikan path

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = isset($_GET['search']) ? trim($_GET['search']) : '';
    if ($q === '') {
        echo json_encode([]);
        exit;
    }
    try {
        // gunakan placeholder terpisah untuk menghindari HY093 jika emulate prepares = false
        $sql = "SELECT id, code, name, phone, points 
                FROM members 
                WHERE name LIKE :q1 OR phone LIKE :q2 OR code LIKE :q3 
                ORDER BY name 
                LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $like = '%' . $q . '%';
        $stmt->bindValue(':q1', $like, PDO::PARAM_STR);
        $stmt->bindValue(':q2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':q3', $like, PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'server error', 'detail' => $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $name = trim($payload['name'] ?? '');
    $phone = trim($payload['phone'] ?? '');
    if ($name === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nama diperlukan']);
        exit;
    }
    try {
        // generate code sederhana
        $code = 'M' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("INSERT INTO members (code, name, phone, points) VALUES (:code, :name, :phone, 0)");
        $stmt->execute([':code' => $code, ':name' => $name, ':phone' => $phone]);
        echo json_encode(['success' => true, 'member_id' => $pdo->lastInsertId()]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
