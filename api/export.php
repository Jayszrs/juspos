<?php
// api/export.php (updated - robust orders export)
// Place in project/api/export.php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/db.php'; // pastikan $pdo diset di includes/db.php

// very small helper
function http_forbidden($msg = 'Forbidden') {
    http_response_code(403);
    echo $msg;
    exit;
}

// auth guard: must be logged in
if (empty($_SESSION['user_id'])) {
    http_forbidden('Forbidden - login required');
}

// allow only ADMIN or LEADER (adjust if you want broader)
$role = strtoupper($_SESSION['user_role'] ?? '');
if (!in_array($role, ['ADMIN','LEADER'], true)) {
    http_forbidden('Forbidden - insufficient privileges');
}

$type = $_GET['type'] ?? 'menus';
$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;

// send CSV headers and BOM
function sendCsvHeaders($filename) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: public');
    header('Cache-Control: no-store, no-cache');
    // BOM for Excel/Windows
    echo "\xEF\xBB\xBF";
    flush();
}

try {
    if ($type === 'menus') {
        $sql = "SELECT id, code, name, price, stock, available, category_id, description FROM menus ORDER BY id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        sendCsvHeaders('menus_export_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','code','name','price','stock','available','category_id','description']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['id'] ?? '',
                $row['code'] ?? '',
                $row['name'] ?? '',
                $row['price'] ?? '',
                $row['stock'] ?? '',
                $row['available'] ?? '',
                $row['category_id'] ?? '',
                $row['description'] ?? ''
            ]);
            flush();
        }
        fclose($out);
        exit;
    }

    if ($type === 'promotions') {
        // conservative columns
        $sql = "SELECT id, code, `type`, `value`, active, created_at FROM promotions ORDER BY id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();

        sendCsvHeaders('promotions_export_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['id','code','type','value','active','created_at']);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['id'] ?? '',
                $row['code'] ?? '',
                $row['type'] ?? '',
                $row['value'] ?? '',
                $row['active'] ?? '',
                $row['created_at'] ?? ''
            ]);
            flush();
        }
        fclose($out);
        exit;
    }

    if ($type === 'orders') {
        // determine start/end defaults if not provided
        if (!$start || !$end) {
            $end_dt = new DateTime();
            $start_dt = (clone $end_dt)->modify('-6 days');
            $start = $start_dt->format('Y-m-d');
            $end = $end_dt->format('Y-m-d');
        }

        // Inspect schema to build safe query (avoid referencing missing columns)
        $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();

        // fetch columns in orders
        $colStmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = 'orders'
        ");
        $colStmt->execute([':schema' => $dbName]);
        $orderCols = $colStmt->fetchAll(PDO::FETCH_COLUMN, 0);

        // utility to check column exists
        $has = function($c) use ($orderCols) { return in_array($c, $orderCols, true); };

        // candidate column names - choose first present
        $memberCol = null;
        foreach (['member_name','member','member_id'] as $c) if ($has($c)) { $memberCol = $c; break; }

        $orderNoCol = null;
        foreach (['order_no','no','invoice_no','invoice'] as $c) if ($has($c)) { $orderNoCol = $c; break; }

        // total might be named differently
        $totalCol = null;
        foreach (['total','total_amount','grand_total','amount'] as $c) if ($has($c)) { $totalCol = $c; break; }

        // created_at variants
        $createdAtCol = $has('created_at') ? 'created_at' : ($has('created') ? 'created' : null);

        // user/cashier
        $userIdCol = $has('user_id') ? 'user_id' : ( $has('cashier_id') ? 'cashier_id' : null );

        // check if order_items & menus exist for items_summary
        $tableStmt = $pdo->prepare("
            SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = :schema AND TABLE_NAME IN ('order_items','menus')
        ");
        $tableStmt->execute([':schema' => $dbName]);
        $tables = $tableStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $hasOrderItems = in_array('order_items', $tables, true);
        $hasMenus = in_array('menus', $tables, true);

        // Build SELECT - always include order_id (o.id)
        $selectParts = ["o.id AS order_id"];

        if ($orderNoCol) $selectParts[] = "COALESCE(o.`$orderNoCol`,'') AS order_no";
        else $selectParts[] = "'' AS order_no";

        if ($createdAtCol) $selectParts[] = "o.`$createdAtCol` AS created_at";
        else $selectParts[] = "o.created_at AS created_at";

        if ($userIdCol && in_array('users', $tables, true)) {
            $selectParts[] = "COALESCE(u.name, '') AS cashier";
        } else {
            // maybe there is a plain column like cashier_name
            if ($has('cashier')) $selectParts[] = "COALESCE(o.cashier,'') AS cashier";
            else $selectParts[] = "'' AS cashier";
        }

        if ($memberCol) $selectParts[] = "COALESCE(o.`$memberCol`,'') AS member";
        else $selectParts[] = "'' AS member";

        if ($has('visit_type')) $selectParts[] = "COALESCE(o.visit_type,'') AS visit_type";
        else $selectParts[] = "'' AS visit_type";

        if ($hasOrderItems) {
            $selectParts[] = "(SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS items_count";
        } else {
            $selectParts[] = "0 AS items_count";
        }

        if ($hasOrderItems && $hasMenus) {
            // safe GROUP_CONCAT subquery (may produce long text)
            $selectParts[] = "(SELECT GROUP_CONCAT(CONCAT(IFNULL(mi.name,''), ' x', IFNULL(oi.qty,0)) SEPARATOR ' | ')
                                  FROM order_items oi
                                  LEFT JOIN menus mi ON mi.id = oi.menu_id
                                  WHERE oi.order_id = o.id
                               ) AS items_summary";
        } else {
            $selectParts[] = "'' AS items_summary";
        }

        if ($totalCol) $selectParts[] = "COALESCE(o.`$totalCol`,0) AS total";
        else $selectParts[] = "COALESCE(o.total, 0) AS total";

        if ($has('payment_method')) $selectParts[] = "COALESCE(o.payment_method,'') AS payment_method";
        else $selectParts[] = "'' AS payment_method";

        // start building FROM / JOIN
        $from = "FROM orders o";
        $joins = [];
        if ($userIdCol && in_array('users', $tables, true)) {
            // determine user id column in orders: user_id or cashier_id etc
            $joins[] = "LEFT JOIN users u ON u.id = o.`$userIdCol`";
        }

        // assemble final query
        $sql = "SELECT " . implode(", ", $selectParts) . " $from " . (count($joins)? ' ' . implode(' ', $joins) : '') .
               " WHERE DATE(" . ( $createdAtCol ? "o.`$createdAtCol`" : "o.created_at" ) . ") BETWEEN :start AND :end ORDER BY " .
               ( $createdAtCol ? "o.`$createdAtCol` ASC" : "o.created_at ASC" );

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':start' => $start, ':end' => $end]);

        // Build CSV headers same order as selectParts names mapping
        $headers = ['order_id','order_no','created_at','cashier','member','visit_type','items_count','items_summary','payment_method','total'];

        sendCsvHeaders('orders_export_' . $start . '_to_' . $end . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // ensure consistent ordering
            $row = [
                $r['order_id'] ?? '',
                $r['order_no'] ?? '',
                $r['created_at'] ?? '',
                $r['cashier'] ?? '',
                $r['member'] ?? '',
                $r['visit_type'] ?? '',
                $r['items_count'] ?? 0,
                $r['items_summary'] ?? '',
                $r['payment_method'] ?? '',
                $r['total'] ?? ''
            ];
            fputcsv($out, $row);
            flush();
        }

        fclose($out);
        exit;
    }

    // unknown type
    http_response_code(400);
    echo "Unknown export type";
    exit;

} catch (PDOException $ex) {
    http_response_code(500);
    // show concise DB error for debugging; remove detail in production
    echo "Database error: " . $ex->getMessage();
    exit;
} catch (Exception $ex) {
    http_response_code(500);
    echo "Internal error: " . $ex->getMessage();
    exit;
}
