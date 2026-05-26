<?php
/**
 * JSON daily active player counts (all-time) with 30-day rolling average.
 *
 * GET params:
 *   realm  — default 'online'
 *   source — 'stored' (default) reads server_daily_activity;
 *            'raw' computes from ratedresults (slower, for comparison)
 *
 * Response includes timing metadata for performance comparison.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'days' => [],
        'meta' => ['note' => 'realm_not_implemented'],
    ]);
    exit;
}

$source = isset($_GET['source']) ? strtolower(trim((string) $_GET['source'])) : 'stored';
if ($source !== 'stored' && $source !== 'raw') {
    $source = 'stored';
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$t0 = microtime(true);

if ($source === 'stored') {
    $sql = 'SELECT `activity_day` AS `day`, `active_players` '
         . 'FROM `server_daily_activity` '
         . 'ORDER BY `activity_day` ASC';
} else {
    $sql = 'SELECT `day`, COUNT(DISTINCT `player_id`) AS `active_players` '
         . 'FROM ('
         . '  SELECT DATE(`Date`) AS `day`, `idA` AS `player_id` FROM `ratedresults` WHERE `idA` IS NOT NULL'
         . '  UNION ALL'
         . '  SELECT DATE(`Date`) AS `day`, `idB` AS `player_id` FROM `ratedresults` WHERE `idB` IS NOT NULL'
         . ') appearances '
         . 'GROUP BY `day` ORDER BY `day` ASC';
}

$res = mysqli_query($con, $sql);

$queryMs = round((microtime(true) - $t0) * 1000, 2);

if ($res === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed', 'source' => $source]);
    mysqli_close($con);
    exit;
}

$days = [];
while ($row = mysqli_fetch_assoc($res)) {
    $days[] = [
        'day' => (string) $row['day'],
        'active_players' => (int) $row['active_players'],
    ];
}

mysqli_free_result($res);
mysqli_close($con);

$totalMs = round((microtime(true) - $t0) * 1000, 2);

echo json_encode([
    'realm' => $realm,
    'source' => $source,
    'days' => $days,
    'meta' => [
        'total_days' => count($days),
        'source' => $source,
        'query_ms' => $queryMs,
        'total_ms' => $totalMs,
    ],
]);
