<?php
/**
 * JSON ELO rating after each game (chronological) for one player.
 *
 * GET: id (required), realm (default online)
 * Online: rating after each processed game = NewRatingA / NewRatingB on the row.
 * Unprocessed rows (NewRatingA IS NULL) are omitted — same marker as game lists (AUD-006).
 * Amiga: one point per rating event (tournament finalize); rating_after from amiga_rating_events.
 * gameNumber / eventNumber = 1-based index in chronological order.
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($realm === 'amiga') {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_db.php';
}

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

if ($realm === 'amiga') {
    $nameSql = 'SELECT p.name AS Name, s.Rating FROM amiga_players p '
        . 'INNER JOIN amiga_player_stats s ON s.player_id = p.id WHERE p.id = ? LIMIT 1';
} else {
    $nameSql = 'SELECT Name, Rating FROM playertable WHERE ID = ? LIMIT 1';
}
$nameStmt = $con->prepare($nameSql);
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

if ($realm === 'amiga') {
    $sql = 'SELECT e.id, e.tournament_id, e.rating_before, e.rating_delta, e.rating_after, '
        . 'e.games_in_event, e.finalized_at, t.event_date, t.name AS tournament_name '
        . 'FROM amiga_rating_events e '
        . 'INNER JOIN tournaments t ON t.id = e.tournament_id '
        . 'WHERE e.player_id = ? '
        . 'ORDER BY t.event_date ASC, t.chrono ASC, e.finalized_at ASC, e.id ASC';
} else {
    $sql = 'SELECT id, Date, idA, idB, NewRatingA, NewRatingB '
        . 'FROM ratedresults WHERE NewRatingA IS NOT NULL AND (idA = ? OR idB = ?) '
        . 'ORDER BY Date ASC, id ASC';
}

$stmt = $con->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed']);
    mysqli_close($con);
    exit;
}

if ($realm === 'amiga') {
    $stmt->bind_param('i', $playerId);
} else {
    $stmt->bind_param('ii', $playerId, $playerId);
}
$stmt->execute();
$res = $stmt->get_result();

$points = [];
$eventNumber = 0;
while ($row = $res->fetch_assoc()) {
    $eventNumber++;
    if ($realm === 'amiga') {
        $points[] = [
            'eventId' => (int) $row['id'],
            'tournamentId' => (int) $row['tournament_id'],
            'tournamentName' => (string) $row['tournament_name'],
            'eventNumber' => $eventNumber,
            'gameNumber' => $eventNumber,
            'gameId' => (int) $row['tournament_id'],
            'date' => $row['event_date'],
            'rating' => (int) round((float) $row['rating_after']),
            'ratingDelta' => round((float) $row['rating_delta'], 1),
            'gamesInEvent' => (int) $row['games_in_event'],
        ];
        continue;
    }
    $isA = ((int) $row['idA'] === $playerId);
    $ratingAfter = $isA ? (float) $row['NewRatingA'] : (float) $row['NewRatingB'];
    $points[] = [
        'gameId' => (int) $row['id'],
        'gameNumber' => $eventNumber,
        'date' => $row['Date'],
        'rating' => (int) round($ratingAfter),
    ];
}

$stmt->close();

$timelineStart = null;
if ($realm === 'amiga') {
    $minRes = $con->query('SELECT MIN(game_date) AS d FROM amiga_games');
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
if ($realm === 'amiga') {
    $payload['meta'] = ['granularity' => 'event'];
}
if ($timelineStart !== null) {
    $payload['timelineStart'] = $timelineStart;
}

echo json_encode($payload);
