<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, code, type, value, starts_at, ends_at, active FROM promotions WHERE active = 1");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows); exit;
}
if ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    if (!isset($in['action']) || $in['action'] !== 'validate') { http_response_code(400); echo json_encode(['error'=>'invalid_action']); exit; }
    $code = trim($in['code'] ?? '');
    $subtotal = floatval($in['subtotal'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM promotions WHERE code = ? AND active = 1 LIMIT 1");
    $stmt->execute([$code]); $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$promo) { echo json_encode(['valid'=>false]); exit; }

    // basic checks: date range if provided
    $now = new DateTime();
    if ($promo['starts_at'] && $promo['starts_at'] > $now->format('Y-m-d H:i:s')) { echo json_encode(['valid'=>false]); exit; }
    if ($promo['ends_at'] && $promo['ends_at'] < $now->format('Y-m-d H:i:s')) { echo json_encode(['valid'=>false]); exit; }

    // compute sample discount
    $discount = 0;
    if ($promo['type'] === 'PERCENT') $discount = $subtotal * ($promo['value'] / 100);
    elseif ($promo['type'] === 'AMOUNT') $discount = floatval($promo['value']);

    echo json_encode(['valid'=>true, 'promo'=>['id'=>$promo['id'],'code'=>$promo['code'],'type'=>$promo['type'],'value'=>$promo['value']], 'discount'=>$discount]);
    exit;
}
http_response_code(405); echo json_encode(['error'=>'Method not allowed']);
