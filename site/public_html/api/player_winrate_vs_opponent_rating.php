<?php
/**
 * JSON win rate by opponent pre-game rating bucket for one player.
 *
 * GET: id (required), realm (default online), bucket (default 50)
 * Win = WinnerID matches player; draw = ActualScore 0.5; else loss.
 * Opponent rating = RatingA/B before game (stored pre-game values on row).
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$bucketSize = isset($_GET['bucket']) ? (int) $_GET['bucket'] : 50;

if ($bucketSize < 10 || $bucketSize > 200) {
    $bucketSize = 50;
}

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'bucket_size' => $bucketSize,
        'buckets' => [],
        'meta' => ['note' => 'realm_not_implemented'],
    ]);
    exit;
}

if ($playerId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
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

$nameStmt = $con->prepare('SELECT Name FROM playertable WHERE ID = ? LIMIT 1');
if (!$nameStmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed']);
    mysqli_close($con);
    exit;
}
$nameStmt->bind_param('i', $playerId);
$nameStmt->execute();
$nameRes = $nameStmt->get_result();
$nameRow = $nameRes->fetch_assoc();
$nameStmt->close();

if ($nameRow === null) {
    mysqli_close($con);
    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'playerName' => null,
        'bucket_size' => $bucketSize,
        'buckets' => [],
        'meta' => ['note' => 'player_not_found'],
    ]);
    exit;
}

$playerName = $nameRow['Name'];

$sql = 'SELECT idA, idB, RatingA, RatingB, ActualScore, WinnerID '
    . 'FROM ratedresults WHERE idA = ? OR idB = ?';

$stmt = $con->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed']);
    mysqli_close($con);
    exit;
}

$stmt->bind_param('ii', $playerId, $playerId);
$stmt->execute();
$res = $stmt->get_result();

$agg = [];
while ($row = $res->fetch_assoc()) {
    $isA = ((int) $row['idA'] === $playerId);
    $oppRating = (int) round($isA ? (float) $row['RatingB'] : (float) $row['RatingA']);
    $bucketStart = (int) (floor($oppRating / $bucketSize) * $bucketSize);

    if (!isset($agg[$bucketStart])) {
        $agg[$bucketStart] = ['wins' => 0, 'draws' => 0, 'losses' => 0];
    }

    $actualScore = (float) $row['ActualScore'];
    $winnerId = (int) $row['WinnerID'];

    if (abs($actualScore - 0.5) < 0.001) {
        $agg[$bucketStart]['draws']++;
    } elseif ($winnerId === $playerId) {
        $agg[$bucketStart]['wins']++;
    } else {
        $agg[$bucketStart]['losses']++;
    }
}

$stmt->close();
mysqli_close($con);

ksort($agg, SORT_NUMERIC);

$buckets = [];
foreach ($agg as $start => $counts) {
    $games = $counts['wins'] + $counts['draws'] + $counts['losses'];
    $buckets[] = [
        'bucket_start' => $start,
        'bucket_end' => $start + $bucketSize - 1,
        'games' => $games,
        'wins' => $counts['wins'],
        'draws' => $counts['draws'],
        'losses' => $counts['losses'],
        'win_rate' => $games > 0 ? round($counts['wins'] / $games, 4) : 0,
    ];
}

echo json_encode([
    'realm' => $realm,
    'playerId' => $playerId,
    'playerName' => $playerName,
    'bucket_size' => $bucketSize,
    'buckets' => $buckets,
    'meta' => [
        'win_rate' => 'wins / all games in bucket (draws count in denominator)',
    ],
]);
