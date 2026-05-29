<?php
/**
 * Shared DB bootstrap for play-streaks staging CLI scripts.
 */
declare(strict_types=1);

function k2_staging_play_streaks_bootstrap(): mysqli
{
    if (PHP_SAPI !== 'cli') {
        fwrite(STDERR, "CLI only.\n");
        exit(1);
    }

    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
    $configPath = $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
    if (!is_file($configPath)) {
        fwrite(STDERR, "Missing config: {$configPath}\n");
        exit(1);
    }

    include $configPath;

    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum ?? 3306);
    if ($con->connect_errno) {
        fwrite(STDERR, 'DB connect failed: ' . $con->connect_error . PHP_EOL);
        exit(1);
    }

    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    return $con;
}

function k2_staging_table_exists(mysqli $con, string $table): bool
{
    // MariaDB staging: prepared SHOW TABLES LIKE ? fails — use escaped query (same as milestones bootstrap).
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table === '') {
        return false;
    }
    $res = $con->query("SHOW TABLES LIKE '" . $con->real_escape_string($table) . "'");
    if ($res === false) {
        return false;
    }
    $ok = $res->num_rows > 0;
    $res->free();

    return $ok;
}
