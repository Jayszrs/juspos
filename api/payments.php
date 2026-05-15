<?php
// api/payments.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$order_id = (int)($input['order_id'] ?? 0);
$amount = (float)($input['amount'] ?? 0);
$method = !empty($input['method']) ? strtoupper(trim($input['method'])) : null;
$meta = !empty($input['meta']) ? json_encode($input['meta']) : null;
$user_id = (int)$_SESSION['user_id'];

if ($order_id <= 0 || $amount <= 0 || !$method) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payment data']);
    exit;
}

try {
    $pdo->beginTransaction();

    // fetch order and check total
    $stmt = $pdo->prepare('SELECT total, status FROM orders WHERE id = ? FOR UPDATE');
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found');
    $orderTotal = (float)$order['total'];
    $status = $order['status'];

    // ensure match (you can allow partials if you want - here we require exact)
    if (abs($orderTotal - $amount) > 0.001) {
        throw new Exception('Payment amount does not match order total');
    }

    // insert payment
    $stmtIns = $pdo->prepare('INSERT INTO payments (order_id, method, amount, meta, created_at) VALUES (?, ?, ?, ?, NOW())');
    $stmtIns->execute([$order_id, $method, $amount, $meta]);

    // mark order finished
    $stmtUpd = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $stmtUpd->execute(['FINISHED', $order_id]);

    $pdo->commit();
    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
