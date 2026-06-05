<?php
/**
 * JSON ELO rating after each game (chronological) for one player.
 *
 * GET: id (required), realm (default online)
 * Rating after each game = NewRatingA / NewRatingB on the row.
 * gameNumber = 1-based index in chronological order (Date ASC, id ASC).
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($playerId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

if ($realm === 'amiga') {
    include __DIR__ . '/../../config/ko2amiga_config.php';
} elseif ($realm === 'online') {
    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
} else {
    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'playerName' => null,
        'points' => [],
        'meta' => ['note' => 'realm_not_implemented'],
    ]);
    exit;
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$nameStmt = $con->prepare('SELECT Name, Rating FROM playertable WHERE ID = ? LIMIT 1');
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
        'points' => [],
        'meta' => ['note' => 'player_not_found'],
    ]);
    exit;
}

$playerName = $nameRow['Name'];
$currentRating = (int) round((float) $nameRow['Rating']);

$sql = 'SELECT id, Date, idA, idB, NewRatingA, NewRatingB '
    . 'FROM ratedresults WHERE idA = ? OR idB = ? ORDER BY Date ASC, id ASC';

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

$points = [];
$gameNumber = 0;
while ($row = $res->fetch_assoc()) {
    $gameNumber++;
    $isA = ((int) $row['idA'] === $playerId);
    $ratingAfter = $isA ? (float) $row['NewRatingA'] : (float) $row['NewRatingB'];
    $points[] = [
        'gameId' => (int) $row['id'],
        'gameNumber' => $gameNumber,
        'date' => $row['Date'],
        'rating' => (int) round($ratingAfter),
    ];
}

$stmt->close();

$timelineStart = null;
if ($realm === 'amiga') {
    $minRes = $con->query('SELECT MIN(Date) AS d FROM ratedresults');
    if ($minRes) {
        $minRow = $minRes->fetch_assoc();
        if ($minRow && $minRow['d'] !== null) {
            $timelineStart = $minRow['d'];
        }
        $minRes->free();
    }
}

mysqli_close($con);

$payload = [
    'realm' => $realm,
    'playerId' => $playerId,
    'playerName' => $playerName,
    'currentRating' => $currentRating,
    'points' => $points,
];
if ($timelineStart !== null) {
    $payload['timelineStart'] = $timelineStart;
}

echo json_encode($payload);
