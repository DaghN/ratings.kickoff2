<?php
/**
 * JSON top players by personal peak calendar month (most games in one month).
 *
 * GET: realm (default online), limit (default 20, max 100)
 * One row per player (their best month). Ties on game count: earlier month wins.
 * Leaderboard ties: higher games first, then earlier peak month.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

if ($limit < 1) {
    $limit = 20;
}
if ($limit > 100) {
    $limit = 100;
}

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'limit' => $limit,
        'entries' => [],
        'meta' => ['note' => 'realm_not_implemented'],
    ]);
    exit;
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

$con->set_charset('utf8mb4');

$limitSql = (int) $limit;

$sql = 'SELECT player_id, player_name, ym, games FROM ('
    . 'SELECT pm.player_id, p.Name AS player_name, pm.ym, pm.games, '
    . 'ROW_NUMBER() OVER (PARTITION BY pm.player_id ORDER BY pm.games DESC, pm.ym ASC) AS rn '
    . 'FROM ('
    . 'SELECT player_id, ym, COUNT(*) AS games FROM ('
    . 'SELECT idA AS player_id, DATE_FORMAT(`Date`, \'%Y-%m\') AS ym FROM ratedresults '
    . 'UNION ALL '
    . 'SELECT idB AS player_id, DATE_FORMAT(`Date`, \'%Y-%m\') AS ym FROM ratedresults'
    . ') AS appearances GROUP BY player_id, ym'
    . ') AS pm INNER JOIN playertable p ON p.ID = pm.player_id'
    . ') AS best_month WHERE rn = 1 '
    . 'ORDER BY games DESC, ym ASC LIMIT ' . $limitSql;

$res = mysqli_query($con, $sql);
if ($res === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    mysqli_close($con);
    exit;
}

$entries = [];
$rank = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $rank++;
    $entries[] = [
        'rank' => $rank,
        'player_id' => (int) $row['player_id'],
        'player_name' => $row['player_name'],
        'month' => $row['ym'],
        'games' => (int) $row['games'],
    ];
}

mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'limit' => $limit,
    'entries' => $entries,
]);
