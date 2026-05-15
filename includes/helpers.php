<?php
function jsonResponse($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
function formatRupiah($n)
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function generateReceiptHtml($order, $items, $meta = [])
{
    $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:monospace;width:280px;padding:10px}table{width:100%;}td{padding:4px}</style></head><body>';
    $html .= '<div style="text-align:center;font-weight:bold">JUSPOINT</div>';
    $html .= '<div style="text-align:center">Order#: ' . $order['id'] . '<br>' . $order['created_at'] . '</div>';
    $html .= '<hr>';
    $html .= '<table>';
    foreach ($items as $it) {
        $html .= '<tr><td>' . $it['name'] . ' x' . $it['qty'] . '</td><td style="text-align:right">' . formatRupiah($it['price'] * $it['qty']) . '</td></tr>';
    }
    $html .= '</table><hr>';
    $html .= '<div style="text-align:right">Total: ' . formatRupiah($meta['total'] ?? $order['total']) . '</div>';
    $html .= '</body></html>';
    return $html;
}
