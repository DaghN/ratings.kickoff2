<?php
declare(strict_types=1);

$repo = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $repo . '/site/public_html';

$database = 'ko2amiga_parity_php';
$dbhost = '127.0.0.1';
$username = 'root';
$password = '';
$dbportnum = 3306;

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, 'connect failed: ' . $con->connect_error . PHP_EOL);
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

require_once $repo . '/site/public_html/amiga/ops/includes/amiga_promote_running_tournament.php';
require_once $repo . '/site/public_html/amiga/ops/modules/finalize_tournament.php';

$tid = 608;
try {
    $promote = amiga_promote_running_tournament($con, $tid, false);
    fwrite(STDOUT, 'promote: ' . json_encode($promote, JSON_UNESCAPED_SLASHES) . PHP_EOL);
    $result = amiga_finalize_tournament($con, $tid, false);
    fwrite(STDOUT, 'finalize: ' . json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL);
    $res = $con->query('SELECT rating_finalized, chrono, lifecycle_status FROM tournaments WHERE id=608');
    $row = $res->fetch_assoc();
    fwrite(STDOUT, 'tour_after: ' . json_encode($row) . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    fwrite(STDERR, $e->getFile() . ':' . $e->getLine() . PHP_EOL);
    fwrite(STDERR, $e->getTraceAsString() . PHP_EOL);
    exit(1);
}