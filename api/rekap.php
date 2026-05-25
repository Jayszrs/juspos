<?php
// api/rekap.php
// Endpoint rekap untuk dashboard / rekap penjualan
// - dipanggil oleh public/rekap.php
// - action: list_cashiers | list_categories | summary | daily | group | methods | orders

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php'; // pastikan $pdo tersedia

// ---------- helper fungsi respon ----------
function respond_ok($data = null)
{
    $out = ['success' => true];
    if ($data !== null) $out['data'] = $data;
    echo json_encode($out);
    exit;
}

function respond_err($msg = 'error', $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ---------- baca parameter ----------
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// tanggal start/end aman (format YYYY-MM-DD)
$start = $_GET['start'] ?? $_POST['start'] ?? null;
$end   = $_GET['end']   ?? $_POST['end']   ?? null;

// jika tidak di-provide, default range: 7 hari terakhir (hari ini termasuk)
if (!$start || !$end) {
    $end = date('Y-m-d');
    $d = new DateTime($end);
    $d->modify('-6 days');
    $start = $d->format('Y-m-d');
}

// Pastikan PDO melempar exception agar kita bisa tangani di catch
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {

    switch ($action) {

        /* -------------------- list_cashiers --------------------
           Mengembalikan daftar users (id, name).
           Frontend akan menerima either j.data (array) atau array langsung.
        ------------------------------------------------------ */
        case 'list_cashiers': {
                $stmt = $pdo->prepare("SELECT id, name FROM users ORDER BY name");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                respond_ok($rows);
            }
            break;

        /* -------------------- list_categories --------------------
           Kembalikan kategori (id, name)
        --------------------------------------------------------- */
        case 'list_categories': {
                $stmt = $pdo->prepare("SELECT id, name FROM categories ORDER BY name");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                respond_ok($rows);
            }
            break;

        /* -------------------- summary --------------------
           KPI ringkasan: total_revenue, total_orders, total_items, avg_order
        --------------------------------------------------------- */
        case 'summary': {
                $sql = "SELECT 
                      COALESCE(SUM(o.total),0) AS total_revenue,
                      COUNT(o.id) AS total_orders,
                      COALESCE(
                        (SELECT SUM(qty) FROM order_items oi JOIN orders o2 ON o2.id = oi.order_id WHERE o2.status = 'FINISHED' AND DATE(o2.created_at) BETWEEN :start AND :end),
                      0) AS total_items,
                      COALESCE(AVG(o.total),0) AS avg_order
                    FROM orders o
                    WHERE o.status = 'FINISHED'
                      AND DATE(o.created_at) BETWEEN :start AND :end";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':start' => $start, ':end' => $end]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
                // normalisasi numeric ke angka (bukan string) jika perlu
                respond_ok($data ?: (object)[]);
            }
            break;

        /* -------------------- daily --------------------
           Keluaran: daftar per-hari (day, revenue, orders_count)
        ----------------------------------------------------- */
        case 'daily': {
                $sql = "SELECT DATE(o.created_at) AS day, COALESCE(SUM(o.total),0) AS revenue, COUNT(o.id) AS orders_count
                    FROM orders o
                    WHERE o.status = 'FINISHED'
                      AND DATE(o.created_at) BETWEEN :start AND :end
                    GROUP BY DATE(o.created_at)
                    ORDER BY DATE(o.created_at) ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':start' => $start, ':end' => $end]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                respond_ok($rows);
            }
            break;

        /* -------------------- group --------------------
           Saat ini hanya 'category' didukung:
           Mengembalikan pendapatan dan jumlah item per kategori.
        ----------------------------------------------------- */
        case 'group': {
                $type = $_GET['type'] ?? $_POST['type'] ?? 'category';
                if ($type === 'category') {
                    $sql = "SELECT c.id AS category_id, c.name AS label,
                           COALESCE(SUM(oi.qty * oi.price),0) AS revenue,
                           COALESCE(SUM(oi.qty),0) AS items
                        FROM order_items oi
                        JOIN orders o ON o.id = oi.order_id
                        JOIN menus m ON m.id = oi.menu_id
                        JOIN categories c ON c.id = m.category_id
                        WHERE o.status = 'FINISHED'
                          AND DATE(o.created_at) BETWEEN :start AND :end
                        GROUP BY c.id, c.name
                        ORDER BY revenue DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':start' => $start, ':end' => $end]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    respond_ok($rows);
                } else {
                    respond_err('Unsupported group type', 400);
                }
            }
            break;

        /* -------------------- methods --------------------
           Breakdown pembayaran: method, count, total_amount
        ----------------------------------------------------- */
        case 'methods': {
                $sql = "SELECT p.method, COUNT(DISTINCT p.order_id) AS cnt, COALESCE(SUM(p.amount),0) AS total_amount
                    FROM payments p
                    JOIN orders o ON o.id = p.order_id
                    WHERE DATE(o.created_at) BETWEEN :start AND :end
                    GROUP BY p.method";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':start' => $start, ':end' => $end]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                respond_ok($rows);
            }
            break;

        /* -------------------- orders --------------------
           Daftar transaksi (paged) dengan filter:
           - q (search: order_no, order id, member name, cashier name)
           - cashier_id
           - payment_method
           - category_id (ada item dengan category tersebut)
        ----------------------------------------------------- */
        case 'orders': {
                // ambil filter
                $q = trim($_GET['q'] ?? $_POST['q'] ?? '');
                $cashier_id = trim($_GET['cashier_id'] ?? $_POST['cashier_id'] ?? '');
                $payment_method = trim($_GET['payment_method'] ?? $_POST['payment_method'] ?? '');
                $category_id = trim($_GET['category_id'] ?? $_POST['category_id'] ?? '');

                // pagination
                $page = max(1, intval($_GET['page'] ?? $_POST['page'] ?? 1));
                $per_page = max(1, intval($_GET['per_page'] ?? $_POST['per_page'] ?? 10));
                $offset = ($page - 1) * $per_page;

                // build where clauses dan params
                $where = "o.status = 'FINISHED' AND DATE(o.created_at) BETWEEN :start AND :end";
                $params = [':start' => $start, ':end' => $end];

                if ($cashier_id !== '') {
                    $where .= " AND o.user_id = :cashier_id";
                    $params[':cashier_id'] = $cashier_id;
                }
                if ($payment_method !== '') {
                    $where .= " AND EXISTS (SELECT 1 FROM payments p2 WHERE p2.order_id = o.id AND p2.method = :pm)";
                    $params[':pm'] = $payment_method;
                }
                if ($category_id !== '') {
                    $where .= " AND EXISTS (SELECT 1 FROM order_items oi2 JOIN menus m2 ON m2.id = oi2.menu_id WHERE oi2.order_id = o.id AND m2.category_id = :catid)";
                    $params[':catid'] = $category_id;
                }
                if ($q !== '') {
                    // cari di order_no, id, member.name, users.name
                    $idCast = db_is_pgsql() ? 'CAST(o.id AS TEXT)' : 'CAST(o.id AS CHAR)';
                    $where .= " AND (
                            (o.order_no IS NOT NULL AND o.order_no LIKE :q)
                            OR {$idCast} LIKE :q
                            OR EXISTS (SELECT 1 FROM members mm WHERE mm.id = o.member_id AND mm.name LIKE :q)
                            OR EXISTS (SELECT 1 FROM users uu WHERE uu.id = o.user_id AND uu.name LIKE :q)
                          )";
                    $params[':q'] = "%{$q}%";
                }

                // total rows
                $countSql = "SELECT COUNT(DISTINCT o.id) as total_rows FROM orders o WHERE {$where}";
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $total_rows = (int)$countStmt->fetchColumn();

                // pagination calc
                $total_pages = max(1, (int)ceil($total_rows / $per_page));

                // query utama: ambil info order + last payment method + item count
                $sql = "SELECT 
                        o.id AS order_id,
                        COALESCE(o.order_no, CONCAT('#', o.id)) AS order_no,
                        o.created_at,
                        u.name AS cashier,
                        m.name AS member_name,
                        o.visit_type,
                        o.total,
                        (SELECT COALESCE(SUM(qty),0) FROM order_items it WHERE it.order_id = o.id) AS items_count,
                        pm.method AS payment_method,
                        (CASE WHEN EXISTS(SELECT 1 FROM payments px WHERE px.order_id = o.id) THEN 1 ELSE 0 END) AS has_payment
                    FROM orders o
                    LEFT JOIN users u ON u.id = o.user_id
                    LEFT JOIN members m ON m.id = o.member_id
                    LEFT JOIN (
                        -- ambil payment method terakhir per order (subquery derived)
                        SELECT p1.order_id, p1.method
                        FROM payments p1
                        JOIN (
                            SELECT order_id, MAX(id) AS maxid FROM payments GROUP BY order_id
                        ) lm ON lm.order_id = p1.order_id AND lm.maxid = p1.id
                    ) pm ON pm.order_id = o.id
                    WHERE {$where}
                    ORDER BY o.created_at DESC
                    LIMIT :per_page OFFSET :offset";

                $stmt = $pdo->prepare($sql);
                // bind parameter dynamic
                foreach ($params as $k => $v) {
                    $stmt->bindValue($k, $v, PDO::PARAM_STR);
                }
                // offset & per_page sebagai integer
                $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
                $stmt->bindValue(':per_page', (int)$per_page, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // susun payload
                $payload = [
                    'rows' => $rows,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $per_page,
                        'total_rows' => $total_rows,
                        'total_pages' => $total_pages
                    ]
                ];
                respond_ok($payload);
            }
            break;

        default:
            respond_err('Unknown action', 400);
    }
} catch (Exception $e) {
    // untuk development kita kembalikan pesan; di production sebaiknya log dan kembalikan pesan umum
    http_response_code(500);
    respond_err($e->getMessage(), 500);
}
