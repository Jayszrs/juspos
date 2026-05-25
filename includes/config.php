<?php
// config.php
// Local defaults tetap cocok untuk XAMPP, sedangkan Vercel membaca nilai dari Environment Variables.

function env_value(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false && isset($_ENV[$key])) {
        $value = $_ENV[$key];
    }

    return ($value === false || $value === '') ? $default : (string) $value;
}

$databaseUrl = env_value('DATABASE_URL', env_value('POSTGRES_URL', env_value('MYSQL_URL')));
$dbConfig = [];
$dbDriver = env_value('DB_DRIVER', 'mysql');

if ($databaseUrl !== '') {
    $parsed = parse_url($databaseUrl);
    if (is_array($parsed)) {
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (in_array($scheme, ['postgres', 'postgresql'], true)) {
            $dbDriver = 'pgsql';
        } elseif (in_array($scheme, ['mysql', 'mariadb'], true)) {
            $dbDriver = 'mysql';
        }

        $dbConfig = [
            'host' => $parsed['host'] ?? '127.0.0.1',
            'port' => isset($parsed['port']) ? (string) $parsed['port'] : ($dbDriver === 'pgsql' ? '5432' : '3306'),
            'name' => isset($parsed['path']) ? ltrim($parsed['path'], '/') : ($dbDriver === 'pgsql' ? 'postgres' : 'juspos'),
            'user' => isset($parsed['user']) ? rawurldecode($parsed['user']) : 'root',
            'pass' => isset($parsed['pass']) ? rawurldecode($parsed['pass']) : '',
        ];
    }
}

define('DB_DRIVER', $dbDriver === 'pgsql' ? 'pgsql' : 'mysql');
define('DB_HOST', env_value('DB_HOST', $dbConfig['host'] ?? '127.0.0.1'));
define('DB_PORT', env_value('DB_PORT', $dbConfig['port'] ?? (DB_DRIVER === 'pgsql' ? '5432' : '3306')));
define('DB_NAME', env_value('DB_NAME', $dbConfig['name'] ?? (DB_DRIVER === 'pgsql' ? 'postgres' : 'juspos')));
define('DB_USER', env_value('DB_USER', $dbConfig['user'] ?? 'root'));
define('DB_PASS', env_value('DB_PASS', $dbConfig['pass'] ?? ''));
define('DB_SSLMODE', env_value('DB_SSLMODE', DB_DRIVER === 'pgsql' ? 'require' : ''));

$baseUrl = env_value('BASE_URL');
if ($baseUrl === '' && !empty($_SERVER['HTTP_HOST'])) {
    $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $scheme = explode(',', $scheme)[0];
    $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'];
}

define('BASE_URL', rtrim($baseUrl !== '' ? $baseUrl : 'http://localhost/juspos', '/'));
define('RECEIPT_PATH', env_value('RECEIPT_PATH', __DIR__ . '/../public/receipts'));
