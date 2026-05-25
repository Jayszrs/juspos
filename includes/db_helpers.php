<?php

function db_driver(): string
{
    return defined('DB_DRIVER') ? DB_DRIVER : 'mysql';
}

function db_is_pgsql(): bool
{
    return db_driver() === 'pgsql';
}

function db_ident(string $name): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
        throw new InvalidArgumentException('Invalid identifier: ' . $name);
    }

    return db_is_pgsql() ? '"' . $name . '"' : '`' . $name . '`';
}

function db_table_columns(PDO $pdo, string $table): array
{
    static $cache = [];
    $key = db_driver() . ':' . $table;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    if (db_is_pgsql()) {
        $stmt = $pdo->prepare("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = :table
            ORDER BY ordinal_position
        ");
        $stmt->execute([':table' => $table]);
        return $cache[$key] = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    $stmt = $pdo->query('SHOW COLUMNS FROM ' . db_ident($table));
    return $cache[$key] = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    return in_array($column, db_table_columns($pdo, $table), true);
}

function db_table_exists(PDO $pdo, string $table): bool
{
    if (db_is_pgsql()) {
        $stmt = $pdo->prepare("SELECT to_regclass(:table) IS NOT NULL");
        $stmt->execute([':table' => $table]);
        return (bool)$stmt->fetchColumn();
    }

    $stmt = $pdo->prepare("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
    ");
    $stmt->execute([':table' => $table]);
    return (bool)$stmt->fetchColumn();
}

function db_last_insert_id(PDO $pdo, string $table = ''): string
{
    if (!db_is_pgsql()) {
        return (string)$pdo->lastInsertId();
    }

    if ($table === '') {
        return (string)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("SELECT currval(pg_get_serial_sequence(:table, 'id'))");
    $stmt->execute([':table' => $table]);
    return (string)$stmt->fetchColumn();
}
