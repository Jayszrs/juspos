<?php
// api/receipt.php
// Usage: /api/receipt.php?order_id=123[&reprint=1][&auto_print=1]

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php'; // $pdo

// helper
function fmtIDR($n) {
    return 'Rp ' . number_format(round($n), 0, ',', '.');
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$reprint = isset($_GET['reprint']) && in_array($_GET['reprint'], ['1','true','yes'], true);
$auto_print = isset($_GET['auto_print']) && in_array($_GET['auto_print'], ['1','true','yes'], true);

if ($order_id <= 0) {
    http_response_code(400);
    echo "Invalid order_id";
    exit;
}

try {
    // fetch order with cashier and member if available; select columns gracefully
    $stmt = $pdo->prepare("
        SELECT o.*, u.name AS cashier_name, m.name AS member_name
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN members m ON m.id = o.member_id
        WHERE o.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        http_response_code(404);
        echo "Order not found";
        exit;
    }

    // fetch items with menu names if possible
    $stmt = $pdo->prepare("
        SELECT oi.qty, oi.price, oi.menu_id, COALESCE(m.name, '') AS item_name
        FROM order_items oi
        LEFT JOIN menus m ON m.id = oi.menu_id
        WHERE oi.order_id = :id
    ");
    $stmt->execute([':id' => $order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // latest payment (if payments table exists)
    $stmt = $pdo->prepare("SELECT method, amount, meta, created_at FROM payments WHERE order_id = :id ORDER BY id DESC LIMIT 1");
    $stmt->execute([':id' => $order_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    // store info (customize as needed)
    $storeName = "Toko Jus Segar";
    $storeAddr = "Jl. Contoh No. 123, Jakarta";
    $storePhone = "021-12345678";

    $order_no = !empty($order['order_no']) ? $order['order_no'] : ('#' . $order['id']);
    $created_at = !empty($order['created_at']) ? date('d M Y, H:i', strtotime($order['created_at'])) : '';
    $kasir = !empty($order['cashier_name']) ? htmlspecialchars($order['cashier_name']) : '-';
    $member = !empty($order['member_name']) ? htmlspecialchars($order['member_name']) : '-';
    $visit_type = !empty($order['visit_type']) ? htmlspecialchars($order['visit_type']) : '';

    // totals: prefer stored values if exist
    $subtotal = isset($order['subtotal']) ? floatval($order['subtotal']) : 0.0;
    $discount = isset($order['discount']) ? floatval($order['discount']) : 0.0;
    $total = isset($order['total']) ? floatval($order['total']) : 0.0;
    if ($subtotal == 0 && is_array($items)) {
        foreach ($items as $it) $subtotal += floatval($it['price']) * intval($it['qty']);
    }
    $tax = isset($order['tax']) ? floatval($order['tax']) : round(($subtotal - $discount) * 0.11);
    $rounding = isset($order['rounding']) ? floatval($order['rounding']) : ($total - ($subtotal - $discount + $tax));

    $paidLabel = $payment['method'] ?? '-';
    $paidAmount = isset($payment['amount']) ? floatval($payment['amount']) : $total;

    // build items HTML
    $items_html = '';
    foreach ($items as $it) {
        $name = htmlspecialchars($it['item_name'] ?: ('Item ' . ($it['menu_id'] ?? '')));
        $qty = intval($it['qty']);
        $line_total = floatval($it['price']) * $qty;
        $items_html .= "<tr><td style=\"padding:4px 0;\">{$qty}x {$name}</td><td style=\"text-align:right;padding:4px 0;\">" . fmtIDR($line_total) . "</td></tr>\n";
    }

    $reprint_badge = $reprint ? '<div class="reprint-badge">*** REPRINT ***</div>' : '';

    // build HTML receipt
    $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Struk - {$order_no}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4; max-width: 320px; margin: 0 auto; padding: 20px; background: #fff; color: #000; }
    .header { text-align: center; margin-bottom: 12px; }
    .store-name { font-size: 18px; font-weight: bold; }
    .divider { border-top: 1px dashed #000; margin: 12px 0; }
    .info-row { display: flex; justify-content: space-between; margin: 4px 0; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
    .total-row { font-weight: bold; font-size: 14px; }
    .footer { text-align: center; margin-top: 12px; font-size: 10px; color: #666; }
    .reprint-badge { background: #ef4444; color: white; padding: 4px 12px; font-weight: bold; display: inline-block; margin-bottom: 8px; }
    @media print { body { padding: 0; } .no-print { display: none; } }
  </style>
</head>
<body>
  <div class="header">
    {$reprint_badge}
    <div class="store-name">{$storeName}</div>
    <div>{$storeAddr}</div>
    <div>Tel: {$storePhone}</div>
  </div>

  <div class="divider"></div>

  <div class="info-row"><span>No. Order:</span><span>{$order_no}</span></div>
  <div class="info-row"><span>Tanggal:</span><span>{$created_at}</span></div>
  <div class="info-row"><span>Kasir:</span><span>{$kasir}</span></div>
  <div class="info-row"><span>Tipe:</span><span>{$visit_type}</span></div>

  <div class="divider"></div>

  <table><tbody>
    {$items_html}
  </tbody></table>

  <div class="divider"></div>

  <table>
    <tbody>
      <tr><td style="padding:2px 0;">Subtotal</td><td style="text-align:right;padding:2px 0;">{SUBTOTAL}</td></tr>
HTML;

    $html = str_replace('{SUBTOTAL}', fmtIDR($subtotal), $html);

    if ($discount > 0) {
        $promo_html = "<tr><td style=\"padding:2px 0; color:#16a34a;\">Promo</td><td style=\"text-align:right;padding:2px 0; color:#16a34a;\">-" . fmtIDR($discount) . "</td></tr>";
        $html .= "<tr>{$promo_html}</tr>";
    }

    $html .= "<tr><td style=\"padding:2px 0;\">PPN 11%</td><td style=\"text-align:right;padding:2px 0;\">" . fmtIDR($tax) . "</td></tr>";
    $html .= "<tr><td style=\"padding:2px 0;\">Pembulatan</td><td style=\"text-align:right;padding:2px 0;\">" . fmtIDR($rounding) . "</td></tr>";

    $html .= <<<HTML2
    </tbody>
  </table>

  <div class="divider"></div>

  <table>
    <tbody>
      <tr class="total-row"><td style="padding:4px 0;">GRAND TOTAL</td><td style="text-align:right;padding:4px 0;">{TOTAL}</td></tr>
      <tr><td style="padding:2px 0;">Bayar ({PAYMETHOD})</td><td style="text-align:right;padding:2px 0;">{PAID}</td></tr>
    </tbody>
  </table>

  <div class="divider"></div>

  <div class="footer">
    <p>Terima kasih atas kunjungan Anda!</p>
    <p style="margin-top:8px;font-size:9px;">Cooling pack dan penanganan suhu yang tepat wajib dilakukan untuk menjaga kualitas produk.</p>
  </div>

  <div class="no-print" style="margin-top:20px;text-align:center;">
    <button onclick="window.print()" style="padding:10px 24px;font-size:14px;cursor:pointer;">Cetak Struk</button>
  </div>
</body>
</html>
HTML2;

    // replace totals
    $html = str_replace('{TOTAL}', fmtIDR($total), $html);
    $html = str_replace('{PAYMETHOD}', htmlspecialchars($paidLabel), $html);
    $html = str_replace('{PAID}', fmtIDR($paidAmount), $html);

    // ensure receipts dir
    $receiptsDir = __DIR__ . '/../public/receipts';
    if (!is_dir($receiptsDir)) {
        @mkdir($receiptsDir, 0755, true);
    }

    $receiptFile = $receiptsDir . '/receipt_' . $order_id . '.html';
    @file_put_contents($receiptFile, $html);

    // append auto-print JS if requested
    if ($auto_print) {
        $printScript = <<<JS
<script>
  window.addEventListener('DOMContentLoaded', function(){
    try {
      setTimeout(function(){ window.print(); }, 250);
    } catch(e) { console.error(e); }
  });
</script>
JS;
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $printScript . '</body>', $html);
        } else {
            $html .= $printScript;
        }
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Internal error: " . $e->getMessage();
    exit;
}
