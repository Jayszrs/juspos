<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$out = [
    'php_version' => PHP_VERSION,
    'pdo_loaded' => class_exists('PDO'),
];

register_shutdown_function(function () use (&$out) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        http_response_code(500);
        $out['fatal'] = [
            'message' => $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line'],
        ];
        echo json_encode($out);
    }
});

try {
    require_once __DIR__ . '/../includes/config.php';

    $out += [
        'db_driver_config' => DB_DRIVER,
        'db_host_configured' => DB_HOST !== '',
        'db_name_configured' => DB_NAME !== '',
        'db_user_configured' => DB_USER !== '',
        'db_pass_configured' => DB_PASS !== '',
        'database_url_present' => getenv('DATABASE_URL') !== false && getenv('DATABASE_URL') !== '',
        'pdo_drivers' => class_exists('PDO') ? PDO::getAvailableDrivers() : [],
    ];

    if (!class_exists('PDO')) {
        throw new RuntimeException('PDO class is not available');
    }

    if (DB_DRIVER === 'pgsql') {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
        if (DB_SSLMODE !== '') {
            $dsn .= ";sslmode=" . DB_SSLMODE;
        }
    } else {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    }

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => DB_DRIVER === 'pgsql',
    ]);

    $out['connection'] = 'ok';
    $tables = ['users', 'categories', 'menus', 'members', 'orders', 'payments'];
    $checks = [];

    foreach ($tables as $table) {
        if (DB_DRIVER === 'pgsql') {
            $stmt = $pdo->prepare("SELECT to_regclass(:table) IS NOT NULL");
            $stmt->execute([':table' => $table]);
            $checks[$table] = (bool)$stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("
                SELECT TABLE_NAME
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
            ");
            $stmt->execute([':table' => $table]);
            $checks[$table] = (bool)$stmt->fetchColumn();
        }
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
