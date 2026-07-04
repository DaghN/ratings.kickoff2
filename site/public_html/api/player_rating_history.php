<?php
/**
 * JSON ELO rating after each game (chronological) for one player.
 *
 * GET: id (required), realm (default online), optional as= (Amiga time travel)
 * Online: rating after each processed game = NewRatingA / NewRatingB on the row.
 * Unprocessed rows (NewRatingA IS NULL) are omitted — same marker as game lists (AUD-006).
 * Amiga: one point per rating event (tournament finalize); rating_after from amiga_player_event_snapshots.
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
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_h2h_pair_lib.php';
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
    $ctx = amiga_snapshot_context_from_request($con);
    $payload = amiga_player_rating_history_payload($con, $playerId, $ctx);
    $timelineStart = amiga_player_rating_timeline_start($con);
    mysqli_close($con);

    if ($payload === null) {
        echo json_encode([
            'realm' => $realm,
            'playerId' => $playerId,
            'playerName' => null,
            'points' => [],
            'meta' => ['note' => 'player_not_found'],
        ]);
        exit;
    }

    $response = [
        'realm' => $realm,
        'playerId' => $payload['playerId'],
        'playerName' => $payload['playerName'],
        'currentRating' => $payload['currentRating'],
        'points' => $payload['points'],
        'meta' => $payload['meta'],
    ];
    if ($payload['peak'] !== null) {
        $response['peak'] = $payload['peak'];
    }
    if ($timelineStart !== null) {
        $response['timelineStart'] = $timelineStart;
    }

    echo json_encode($response);
    exit;
}

$nameSql = 'SELECT Name, Rating FROM playertable WHERE ID = ? LIMIT 1';
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

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_display_names.php';

$sql = 'SELECT id, Date, idA, idB, NameA, NameB, GoalsA, GoalsB, '
    . 'RatingA, RatingB, NewRatingA, NewRatingB, AdjustmentA, AdjustmentB '
    . 'FROM ratedresults WHERE NewRatingA IS NOT NULL AND (idA = ? OR idB = ?) '
    . 'ORDER BY Date ASC, id ASC';

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

$rawRows = [];
while ($row = $res->fetch_assoc()) {
    $rawRows[] = $row;
}
$stmt->close();
$nameMap = k2_player_display_names_for_rated_rows($con, $rawRows);

$points = [];
$eventNumber = 0;
foreach (k2_rated_games_apply_display_names($rawRows, $nameMap) as $row) {
    $eventNumber++;
    $isA = ((int) $row['idA'] === $playerId);
    $ratingAfter = $isA ? (float) $row['NewRatingA'] : (float) $row['NewRatingB'];
    $points[] = [
        'gameId' => (int) $row['id'],
        'gameNumber' => $eventNumber,
        'date' => $row['Date'],
        'rating' => (int) round($ratingAfter),
        'name_a' => (string) $row['NameA'],
        'name_b' => (string) $row['NameB'],
        'rating_a' => (int) round((float) $row['RatingA']),
        'rating_b' => (int) round((float) $row['RatingB']),
        'goals_a' => (int) $row['GoalsA'],
        'goals_b' => (int) $row['GoalsB'],
    ];
}

mysqli_close($con);

$payload = [
    'realm' => $realm,
    'playerId' => $playerId,
    'playerName' => $playerName,
    'currentRating' => $currentRating,
    'points' => $points,
];

echo json_encode($payload);
