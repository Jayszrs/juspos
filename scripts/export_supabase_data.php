<?php

// Export current local MySQL juspos data into PostgreSQL-compatible INSERTs.
// Usage: C:\xampp\php\php.exe scripts\export_supabase_data.php

$outputFile = $argv[1] ?? (__DIR__ . '/../migrations/supabase_data_from_local.sql');
$tables = [
    'users',
    'categories',
    'menus',
    'members',
    'promotions',
    'orders',
    'order_items',
    'payments',
    'promo_applied',
    'receipts',
];

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3306;dbname=juspos;charset=utf8mb4',
    'root',
    '',
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

function pg_literal($value): string
{
    if ($value === null) {
        return 'NULL';
    }

    return "'" . str_replace("'", "''", (string)$value) . "'";
}

$sql = [];
$sql[] = "-- Supabase/PostgreSQL data dump generated from local MySQL juspos.";
$sql[] = "-- Run after migrations/supabase_postgres_schema.sql.";
$sql[] = "BEGIN;";

foreach ($tables as $table) {
    $exists = $pdo->prepare("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
    ");
    $exists->execute([':table' => $table]);
    if (!$exists->fetchColumn()) {
        continue;
    }

    $rows = $pdo->query("SELECT * FROM `$table` ORDER BY id ASC")->fetchAll();
    if (!$rows) {
        continue;
    }

    $columns = array_keys($rows[0]);
    $columnSql = implode(', ', array_map(fn($column) => '"' . $column . '"', $columns));

    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $column) {
            $values[] = pg_literal($row[$column]);
        }
        $sql[] = 'INSERT INTO "' . $table . '" (' . $columnSql . ') VALUES (' . implode(', ', $values) . ');';
    }
}

foreach ($tables as $table) {
    $sql[] = "SELECT setval(pg_get_serial_sequence('$table', 'id'), COALESCE((SELECT MAX(id) FROM \"$table\"), 1), true);";
}

$sql[] = "COMMIT;";
$sql[] = "";

file_put_contents($outputFile, implode(PHP_EOL, $sql));
echo "Wrote $outputFile" . PHP_EOL;
