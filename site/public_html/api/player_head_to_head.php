<?php
/**
 * JSON cumulative head-to-head wins between two players (by game order).
 *
 * GET: id (profile player), opponent (opponent id), realm (default online)
 * Draws: neither win total increases.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$opponentId = isset($_GET['opponent']) ? (int) $_GET['opponent'] : 0;

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'opponentId' => $opponentId,
        'points' => [],
        'meta' => ['note' => 'realm_not_implemented'],
    ]);
    exit;
}

if ($playerId < 1 || $opponentId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

if ($playerId === $opponentId) {
    echo json_encode(['error' => 'same_player']);
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

$nameStmt = $con->prepare('SELECT ID, Name FROM playertable WHERE ID IN (?, ?)');
if (!$nameStmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed']);
    mysqli_close($con);
    exit;
}
$nameStmt->bind_param('ii', $playerId, $opponentId);
$nameStmt->execute();
$nameRes = $nameStmt->get_result();
$names = [];
while ($row = $nameRes->fetch_assoc()) {
    $names[(int) $row['ID']] = $row['Name'];
}
$nameStmt->close();

if (!isset($names[$playerId], $names[$opponentId])) {
    mysqli_close($con);
    echo json_encode(['error' => 'player_not_found']);
    exit;
}

$sql = 'SELECT id, Date, ActualScore, WinnerID FROM ratedresults '
    . 'WHERE (idA = ? AND idB = ?) OR (idA = ? AND idB = ?) '
    . 'ORDER BY Date ASC, id ASC';

$stmt = $con->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed']);
    mysqli_close($con);
    exit;
}

$stmt->bind_param('iiii', $playerId, $opponentId, $opponentId, $playerId);
$stmt->execute();
$res = $stmt->get_result();

$points = [];
$playerWins = 0;
$opponentWins = 0;
$draws = 0;
$gameNumber = 0;

while ($row = $res->fetch_assoc()) {
    $gameNumber++;
    $actualScore = (float) $row['ActualScore'];
    $winnerId = (int) $row['WinnerID'];

    if (abs($actualScore - 0.5) < 0.001) {
        $draws++;
    } elseif ($winnerId === $playerId) {
        $playerWins++;
    } else {
        $opponentWins++;
    }

    $points[] = [
        'game_number' => $gameNumber,
        'player_wins' => $playerWins,
        'opponent_wins' => $opponentWins,
    ];
}

$stmt->close();
mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'playerId' => $playerId,
    'playerName' => $names[$playerId],
    'opponentId' => $opponentId,
    'opponentName' => $names[$opponentId],
    'total_games' => $gameNumber,
    'draws' => $draws,
    'points' => $points,
]);
