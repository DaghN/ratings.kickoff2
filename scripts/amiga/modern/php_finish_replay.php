<?php
/**
 * Driver for `python -m scripts.amiga php-finish-check`.
 *
 * Runs the website's Finish calculation (amiga_finalize_tournament) over every tournament, oldest first,
 * in the throwaway check database that php_finish_check.py builds and drops. Refuses any other database.
 *
 * Saves once per tournament (autocommit off). The website saves row by row; the calculation is the same.
 *
 * Usage: php php_finish_replay.php <site public_html> <report json> [limit]
 * Connection: FINISH_CHECK_HOST / _USER / _PASSWORD / _PORT environment variables.
 */
declare(strict_types=1);

const K2_FINISH_CHECK_DATABASE = 'ko2amiga_finish_check';

if (PHP_SAPI !== 'cli' || $argc < 3) {
    fwrite(STDERR, "Usage: php php_finish_replay.php <site public_html> <report json> [limit]\n");
    exit(2);
}

$siteRoot = rtrim(str_replace('\\', '/', (string) $argv[1]), '/');
$reportPath = (string) $argv[2];
$limit = (int) ($argv[3] ?? 0);

$finalizeModule = $siteRoot . '/amiga/ops/modules/finalize_tournament.php';
if (!is_file($finalizeModule)) {
    fwrite(STDERR, "Missing {$finalizeModule}\n");
    exit(2);
}

$_SERVER['DOCUMENT_ROOT'] = $siteRoot;
set_time_limit(0);

/** @var array<string, int> $phpMessages */
$phpMessages = [];
set_error_handler(static function (int $level, string $message, string $file, int $line) use (&$phpMessages): bool {
    $key = $message . ' (' . basename($file) . ':' . $line . ')';
    $phpMessages[$key] = ($phpMessages[$key] ?? 0) + 1;

    return true;
});

mysqli_report(MYSQLI_REPORT_OFF);
$con = new mysqli(
    (string) getenv('FINISH_CHECK_HOST'),
    (string) getenv('FINISH_CHECK_USER'),
    (string) getenv('FINISH_CHECK_PASSWORD'),
    K2_FINISH_CHECK_DATABASE,
    (int) getenv('FINISH_CHECK_PORT')
);
if ($con->connect_errno) {
    fwrite(STDERR, 'DB connect failed: ' . $con->connect_error . PHP_EOL);
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");
$con->autocommit(false);

require_once $siteRoot . '/amiga/ops/includes/amiga_ops_bootstrap.php';
require_once $finalizeModule;

$res = $con->query(
    'SELECT t.id, t.name FROM tournaments t '
    . 'INNER JOIN amiga_games g ON g.tournament_id = t.id '
    . 'GROUP BY t.id, t.name, t.event_date, t.chrono '
    . 'ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC'
);
if ($res === false) {
    fwrite(STDERR, 'Tournament list failed: ' . $con->error . PHP_EOL);
    exit(1);
}
$tournaments = $res->fetch_all(MYSQLI_ASSOC);
$res->free();
if ($limit > 0) {
    $tournaments = array_slice($tournaments, 0, $limit);
}

$total = count($tournaments);
$done = [];
$error = null;
$started = microtime(true);
foreach ($tournaments as $row) {
    $tid = (int) $row['id'];
    try {
        amiga_finalize_tournament($con, $tid, false);
        if (!$con->commit()) {
            throw new RuntimeException('commit: ' . $con->error);
        }
    } catch (Throwable $e) {
        $con->rollback();
        $error = [
            'tournament_id' => $tid,
            'name' => (string) $row['name'],
            'message' => $e->getMessage(),
            'at' => basename($e->getFile()) . ':' . $e->getLine(),
        ];
        break;
    }
    $done[] = $tid;
    if (count($done) % 50 === 0) {
        fwrite(STDERR, sprintf("  PHP Finish: %d / %d tournaments, %d s\n", count($done), $total, microtime(true) - $started));
    }
}

$usedFiles = [];
foreach (get_included_files() as $file) {
    $file = str_replace(DIRECTORY_SEPARATOR, '/', $file);
    if (str_starts_with($file, $siteRoot . '/')) {
        $usedFiles[] = substr($file, strlen($siteRoot) + 1);
    }
}
sort($usedFiles);
arsort($phpMessages);
$report = [
    'tournaments_total' => $total,
    'tournaments_done' => $done,
    'seconds' => round(microtime(true) - $started, 1),
    'php_version' => PHP_VERSION,
    'php_files' => $usedFiles,
    'php_messages' => $phpMessages,
    'error' => $error,
];
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($reportPath, $json . "\n") === false) {
    fwrite(STDERR, "Could not write {$reportPath}\n" . print_r($phpMessages, true));
    exit(3);
}
exit($error === null ? 0 : 1);
