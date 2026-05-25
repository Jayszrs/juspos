<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';

$out = [
    'php_version' => PHP_VERSION,
    'db_driver_config' => DB_DRIVER,
    'db_host_configured' => DB_HOST !== '',
    'db_name_configured' => DB_NAME !== '',
    'db_user_configured' => DB_USER !== '',
    'db_pass_configured' => DB_PASS !== '',
    'database_url_present' => getenv('DATABASE_URL') !== false && getenv('DATABASE_URL') !== '',
    'pdo_drivers' => PDO::getAvailableDrivers(),
];

try {
    require_once __DIR__ . '/../includes/db.php';
    $out['connection'] = 'ok';

    $checks = [];
    foreach (['users', 'categories', 'menus', 'members', 'orders', 'payments'] as $table) {
        $checks[$table] = db_table_exists($pdo, $table);
    }
    $out['tables'] = $checks;

    if (!empty($checks['users'])) {
        $stmt = $pdo->query('SELECT COUNT(*) FROM users');
        $out['users_count'] = (int)$stmt->fetchColumn();
    }
} catch (Throwable $e) {
    http_response_code(500);
    $out['connection'] = 'failed';
    $out['error_type'] = get_class($e);
    $out['error_message'] = $e->getMessage();
}

echo json_encode($out);
