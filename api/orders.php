<?php
// api/orders.php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php'; // pastikan $pdo tersedia

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

$items = $input['items'] ?? [];
$member_id = isset($input['member_id']) ? $input['member_id'] : null;
$promo_code = isset($input['promo']) ? $input['promo'] : null;
$visit_type = in_array($input['visit_type'] ?? '', ['DINE', 'TAKEAWAY'], true) ? $input['visit_type'] : 'DINE';
$payment_method = $input['payment_method'] ?? null;
$paid_amount = isset($input['paid_amount']) ? floatval($input['paid_amount']) : null;
$user_id = $_SESSION['user_id'] ?? null;

if (empty($user_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cart kosong']);
    exit;
}

try {
    // Check columns present in orders table to insert only existing fields
    $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $colsStmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = 'orders'");
    $colsStmt->execute([':schema' => $dbName]);
    $orderCols = $colsStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $hasCol = function($name) use ($orderCols) { return in_array($name, $orderCols, true); };

    // Start transaction
    $pdo->beginTransaction();

    // Recalculate subtotal and verify stock (lock rows)
    $subtotal = 0.0;
    $menuPrices = []; // menu_id => price
    foreach ($items as $it) {
        $menu_id = intval($it['menu_id'] ?? 0);
        $qty = intval($it['qty'] ?? 0);
        if ($menu_id <= 0 || $qty <= 0) throw new Exception("Invalid item data");
        // Lock menu row
        $stmt = $pdo->prepare("SELECT id, price, stock, available FROM menus WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $menu_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception("Produk tidak ditemukan (id {$menu_id})");
        if (intval($row['available']) === 0) throw new Exception("Produk tidak tersedia (id {$menu_id})");
        if (intval($row['stock']) < $qty) throw new Exception("Stok tidak cukup untuk produk id {$menu_id}");
        $price = floatval($row['price']);
        $menuPrices[$menu_id] = $price;
        $subtotal += $price * $qty;
    }

    // Promo handling (safe)
    $discount = 0.0;
    $promo_id = null;
    if ($promo_code) {
        $stmt = $pdo->prepare("SELECT id, code, `type`, `value`, active FROM promotions WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $promo_code]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($p && intval($p['active']) === 1) {
            if (($p['type'] ?? '') === 'PERCENT') $discount = $subtotal * (floatval($p['value']) / 100);
            elseif (($p['type'] ?? '') === 'AMOUNT') $discount = floatval($p['value']);
            $promo_id = $p['id'];
        } else {
            $discount = 0.0;
            $promo_id = null;
        }
    }

    $after = max(0, $subtotal - $discount);
    $tax = $after * 0.11;
    // rounding to nearest 100 (same as UI)
    $rounded_total = round(($after + $tax) / 100) * 100;
    $rounding = ($after + $tax) - $rounded_total;
    $total = (float) round($rounded_total);

    // generate order_no (if column exists, we will set it after insert)
    $order_no = 'SRPM' . date('Ymd') . str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

    // Build insert for orders: only include columns that exist in DB
    $insertCols = [];
    $insertVals = [];
    $params = [];

    // required minimal fields: status, subtotal, discount, total, created_at (if available)
    // We'll prefer commonly existing columns; check names then set accordingly.
    // Map friendly to actual column names if present
    if ($hasCol('visit_type')) { $insertCols[] = 'visit_type'; $insertVals[] = ':visit_type'; $params[':visit_type'] = $visit_type; }
    if ($hasCol('status')) { $insertCols[] = 'status'; $insertVals[] = ':status'; $params[':status'] = 'FINISHED'; }
    if ($hasCol('member_id')) { $insertCols[] = 'member_id'; $insertVals[] = ':member_id'; $params[':member_id'] = $member_id; }
    if ($hasCol('user_id')) { $insertCols[] = 'user_id'; $insertVals[] = ':user_id'; $params[':user_id'] = $user_id; }
    if ($hasCol('subtotal')) { $insertCols[] = 'subtotal'; $insertVals[] = ':subtotal'; $params[':subtotal'] = $subtotal; }
    if ($hasCol('discount')) { $insertCols[] = 'discount'; $insertVals[] = ':discount'; $params[':discount'] = $discount; }
    if ($hasCol('tax')) { $insertCols[] = 'tax'; $insertVals[] = ':tax'; $params[':tax'] = $tax; }
    if ($hasCol('rounding')) { $insertCols[] = 'rounding'; $insertVals[] = ':rounding'; $params[':rounding'] = $rounding; }
    if ($hasCol('total')) { $insertCols[] = 'total'; $insertVals[] = ':total'; $params[':total'] = $total; }
    if ($hasCol('promo_id') && $promo_id !== null) { $insertCols[] = 'promo_id'; $insertVals[] = ':promo_id'; $params[':promo_id'] = $promo_id; }

    // created_at: if exists, either use NOW() in SQL or set via param
    $useCreatedAtNow = false;
    if ($hasCol('created_at')) {
        // we'll use NOW() in SQL (no param)
        $insertCols[] = 'created_at';
        $insertVals[] = 'NOW()';
    }

    if (count($insertCols) === 0) {
        // fallback: simple insert with minimal columns (some DBs may at least have id)
        $stmtIns = $pdo->prepare("INSERT INTO orders (status, subtotal, discount, total, created_at) VALUES (:status, :subtotal, :discount, :total, NOW())");
        $stmtIns->execute([':status' => 'FINISHED', ':subtotal' => $subtotal, ':discount' => $discount, ':total' => $total]);
    } else {
        // prepare dynamic insert
        $colsSql = implode(', ', $insertCols);
        $valsSql = implode(', ', $insertVals);
        $sql = "INSERT INTO orders ($colsSql) VALUES ($valsSql)";
        $stmtIns = $pdo->prepare($sql);
        $stmtIns->execute($params);
    }

    $order_id = $pdo->lastInsertId();

    // If orders table has order_no column, update it now
    if ($hasCol('order_no')) {
        $stmtUpd = $pdo->prepare("UPDATE orders SET order_no = :order_no WHERE id = :id");
        $stmtUpd->execute([':order_no' => $order_no, ':id' => $order_id]);
    }

    // Insert order_items and decrement stock
    // ensure order_items table exists
    $tblStmt = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = 'order_items'");
    $tblStmt->execute([':schema' => $dbName]);
    $hasOrderItems = (bool) $tblStmt->fetchColumn();

    if ($hasOrderItems) {
        $insItem = $pdo->prepare("INSERT INTO order_items (order_id, menu_id, qty, price) VALUES (:order_id, :menu_id, :qty, :price)");
        $updStock = $pdo->prepare("UPDATE menus SET stock = stock - :qty WHERE id = :id");
        foreach ($items as $it) {
            $menu_id = intval($it['menu_id']);
            $qty = intval($it['qty']);
            $price = $menuPrices[$menu_id];
            $insItem->execute([':order_id' => $order_id, ':menu_id' => $menu_id, ':qty' => $qty, ':price' => $price]);
            $updStock->execute([':qty' => $qty, ':id' => $menu_id]);
        }
    } else {
        // No order_items table — we've already validated stock earlier but we cannot persist items
        // rollback and error out
        throw new Exception("order_items table is missing in database");
    }

    // Insert payment record if payment_method provided and payments table exists
    $tblStmt2 = $pdo->prepare("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = 'payments'");
    $tblStmt2->execute([':schema' => $dbName]);
    $hasPayments = (bool) $tblStmt2->fetchColumn();

    if (!empty($payment_method) && $hasPayments) {
        $stmtPay = $pdo->prepare("INSERT INTO payments (order_id, method, amount, meta, created_at) VALUES (:order_id, :method, :amount, :meta, NOW())");
        $meta = json_encode(['paid_amount' => $paid_amount]);
        $stmtPay->execute([':order_id' => $order_id, ':method' => $payment_method, ':amount' => $total, ':meta' => $meta]);
    }

    $pdo->commit();

    // Build receipt URL relative to /api
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']); // typically '/api'
    $receipt_url = $scriptDir . '/receipt.php?order_id=' . $order_id;

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order_no' => $order_no,
        'total' => $total,
        'receipt_url' => $receipt_url
    ]);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (Exception $_) {}
    }
    http_response_code(500);
    // return error message (you can remove $e->getMessage() in production)
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
