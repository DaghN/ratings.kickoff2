<?php
/**
 * CLI bootstrap for ops prepare (work DB). No dispatch.php — dev runners only.
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_work_target.php';

function k2_ops_require_cli(): void
{
    if (PHP_SAPI !== 'cli') {
        fwrite(STDERR, "CLI only.\n");
        exit(1);
    }
}

function k2_ops_connect_work(K2OpsWorkTarget $target, bool $allowDevDb = false): mysqli
{
    if (!$allowDevDb && $target->workDatabase === K2_OPS_PROTECTED_DEV_DATABASE) {
        fwrite(STDERR, 'Refusing connect to dev DB. Pass allow_dev_db=1 only when intentional.' . PHP_EOL);
        exit(1);
    }
    if (in_array($target->workDatabase, K2_OPS_PROTECTED_BASELINE_DATABASES, true)) {
        fwrite(STDERR, "Refusing connect to protected baseline DB {$target->workDatabase}.\n");
        exit(1);
    }

    $con = new mysqli(
        $target->host,
        $target->user,
        $target->password,
        $target->workDatabase,
        $target->port
    );
    if ($con->connect_errno) {
        fwrite(STDERR, 'DB connect failed: ' . $con->connect_error . PHP_EOL);
        exit(1);
    }
    $con->set_charset('utf8mb4');
    if (!$con->query("SET time_zone = '+00:00'")) {
        fwrite(STDERR, 'SET time_zone failed: ' . $con->error . PHP_EOL);
        exit(1);
    }

    $res = $con->query('SELECT DATABASE() AS db');
    if ($res === false) {
        fwrite(STDERR, 'DATABASE() check failed: ' . $con->error . PHP_EOL);
        exit(1);
    }
    $row = $res->fetch_assoc();
    $res->free();
    if (($row['db'] ?? '') !== $target->workDatabase) {
        fwrite(STDERR, "DATABASE()={$row['db']} != configured {$target->workDatabase}\n");
        exit(1);
    }

    return $con;
}

function k2_ops_log(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function k2_ops_table_exists(mysqli $con, string $table): bool
{
    $table = $con->real_escape_string($table);
    $res = $con->query(
        "SELECT COUNT(*) AS n FROM information_schema.tables "
        . "WHERE table_schema = DATABASE() AND table_name = '{$table}'"
    );
    if ($res === false) {
        return false;
    }
    $row = $res->fetch_assoc();
    $res->free();
    return (int) ($row['n'] ?? 0) > 0;
}

function k2_ops_column_exists(mysqli $con, string $table, string $column): bool
{
    $stmt = $con->prepare(
        'SELECT COUNT(*) AS n FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param('ss', $table, $column);
    if (!$stmt->execute()) {
        $stmt->close();

        return false;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return (int) ($row['n'] ?? 0) > 0;
}
