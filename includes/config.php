<?php
// config.php - jangan commit ke repo jika berisi password
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'juspos');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'http://localhost/juspos'); // adjust
define('RECEIPT_PATH', __DIR__ . '/../public/receipts'); // pastikan folder ini writable
