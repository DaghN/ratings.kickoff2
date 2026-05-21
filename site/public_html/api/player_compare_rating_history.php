<?php
/**
 * JSON full career rating history for two players (for comparison chart).
 *
 * GET: id (profile player), opponent, realm (default online)
 * Rating after each game = pre-game Rating + Adjustment (same as player_rating_history.php).
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
        'player' => null,
        'opponent' => null,
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
    http_response_code(400);
    echo json_encode(['error' => 'same_player']);
    exit;
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

/**
 * @return array{playerId: int, playerName: string|null, points: array}|null
 */
function rating_history_for_player(mysqli $con, int $targetId): ?array
{
    $nameStmt = $con->prepare('SELECT Name, Rating FROM playertable WHERE ID = ? LIMIT 1');
    if (!$nameStmt) {
        return null;
    }
    $nameStmt->bind_param('i', $targetId);
    $nameStmt->execute();
    $nameRes = $nameStmt->get_result();
    $nameRow = $nameRes->fetch_assoc();
    $nameStmt->close();

    if ($nameRow === null) {
        return null;
    }

    $sql = 'SELECT id, Date, idA, idB, RatingA, RatingB, AdjustmentA, AdjustmentB '
        . 'FROM ratedresults WHERE idA = ? OR idB = ? ORDER BY Date ASC, id ASC';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('ii', $targetId, $targetId);
    $stmt->execute();
    $res = $stmt->get_result();

    $points = [];
    while ($row = $res->fetch_assoc()) {
        $isA = ((int) $row['idA'] === $targetId);
        if ($isA) {
            $ratingAfter = (float) $row['RatingA'] + (float) $row['AdjustmentA'];
        } else {
            $ratingAfter = (float) $row['RatingB'] + (float) $row['AdjustmentB'];
        }
        $points[] = [
            'gameId' => (int) $row['id'],
            'date' => $row['Date'],
            'rating' => (int) round($ratingAfter),
        ];
    }

    $stmt->close();

    return [
        'playerId' => $targetId,
        'playerName' => $nameRow['Name'],
        'currentRating' => (int) round((float) $nameRow['Rating']),
        'points' => $points,
    ];
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

$con->set_charset('utf8mb4');

$player = rating_history_for_player($con, $playerId);
$opponent = rating_history_for_player($con, $opponentId);

mysqli_close($con);

if ($player === null || $opponent === null) {
    echo json_encode(['error' => 'player_not_found']);
    exit;
}

echo json_encode([
    'realm' => $realm,
    'playerId' => $playerId,
    'opponentId' => $opponentId,
    'player' => $player,
    'opponent' => $opponent,
]);
