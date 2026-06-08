<?php
/**
 * CLI bootstrap for Amiga post-game ops (ko2amiga_db only).
 */
declare(strict_types=1);

const AMIGA_OPS_EXPECTED_DATABASE = 'ko2amiga_db';

/** Contract chronology — mirrors scripts/amiga/replay.py GAME_SELECT. */
const AMIGA_GAME_CHRONOLOGY_ORDER_ASC = <<<'SQL'
g.game_date ASC,
g.id ASC
SQL;

const AMIGA_GAME_CHRONOLOGY_ORDER_DESC = <<<'SQL'
g.game_date DESC,
g.id DESC
SQL;

function amiga_ops_require_cli(): void
{
    if (PHP_SAPI !== 'cli') {
        fwrite(STDERR, "CLI only.\n");
        exit(1);
    }
}

function amiga_ops_log(string $message): void
{
    $line = '[amiga-ops] ' . $message . PHP_EOL;
    if (PHP_SAPI === 'cli' && defined('STDOUT')) {
        fwrite(STDOUT, $line);
        return;
    }
    error_log(rtrim($line));
}

function amiga_ops_connect(): mysqli
{
    $configFile = dirname(__DIR__, 4) . '/config/ko2amiga_config.php';
    if (!is_file($configFile)) {
        fwrite(STDERR, "Missing DB config: {$configFile}\n");
        exit(1);
    }
    require $configFile;

    $database = $database ?? '';
    if ($database !== AMIGA_OPS_EXPECTED_DATABASE) {
        fwrite(STDERR, "Refusing connect: expected " . AMIGA_OPS_EXPECTED_DATABASE . ", got {$database}\n");
        exit(1);
    }

    $port = (int) ($dbportnum ?? 3306);
    $con = new mysqli($dbhost, $username, $password, $database, $port);
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
    if (($row['db'] ?? '') !== AMIGA_OPS_EXPECTED_DATABASE) {
        fwrite(STDERR, 'DATABASE()=' . ($row['db'] ?? '') . ' != ' . AMIGA_OPS_EXPECTED_DATABASE . PHP_EOL);
        exit(1);
    }

    return $con;
}
