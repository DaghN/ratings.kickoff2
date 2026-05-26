<?php
/**
 * JSON player count per current ELO bucket (Double Digit Merchants only).
 * Merchant = player has scored 10 or more goals in at least one rated game.
 *
 * GET: realm (default online), bucket (default 100)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$bucketSize = isset($_GET['bucket']) ? (int) $_GET['bucket'] : 100;
$goalsRequired = 10;

if ($bucketSize < 10 || $bucketSize > 500) {
    $bucketSize = 100;
}

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'bucket_size' => $bucketSize,
        'goals_required' => $goalsRequired,
        'buckets' => [],
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
$con->query("SET time_zone = '+00:00'");

$goalsSql = (int) $goalsRequired;
$bucketSizeSql = (int) $bucketSize;

$sql = 'SELECT bucket_start, COUNT(*) AS players FROM ('
    . 'SELECT FLOOR(p.Rating / ' . $bucketSizeSql . ') * ' . $bucketSizeSql . ' AS bucket_start '
    . 'FROM playertable p INNER JOIN ('
    . 'SELECT DISTINCT player_id FROM ('
    . 'SELECT idA AS player_id FROM ratedresults WHERE GoalsA >= ' . $goalsSql . ' '
    . 'UNION ALL '
    . 'SELECT idB AS player_id FROM ratedresults WHERE GoalsB >= ' . $goalsSql
    . ') AS merchant_appearances'
    . ') AS merchants ON merchants.player_id = p.ID '
    . 'WHERE p.Rating IS NOT NULL'
    . ') AS rated GROUP BY bucket_start ORDER BY bucket_start ASC';

$res = mysqli_query($con, $sql);
if ($res === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    mysqli_close($con);
    exit;
}

$countsByBucket = [];
$totalPlayers = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $start = (int) $row['bucket_start'];
    $count = (int) $row['players'];
    $countsByBucket[$start] = $count;
    $totalPlayers += $count;
}

mysqli_close($con);

$buckets = [];
if ($totalPlayers > 0) {
    $bucketStarts = array_keys($countsByBucket);
    $minBucket = min($bucketStarts);
    $maxBucket = max($bucketStarts);
    for ($start = $minBucket; $start <= $maxBucket; $start += $bucketSize) {
        $buckets[] = [
            'bucket_start' => $start,
            'bucket_end' => $start + $bucketSize - 1,
            'players' => $countsByBucket[$start] ?? 0,
        ];
    }
}

echo json_encode([
    'realm' => $realm,
    'bucket_size' => $bucketSize,
    'goals_required' => $goalsRequired,
    'total_players' => $totalPlayers,
    'buckets' => $buckets,
]);
