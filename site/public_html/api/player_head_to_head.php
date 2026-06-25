<?php
/**
 * JSON cumulative head-to-head between two players (by game order).
 *
 * GET: id (profile player), opponent (opponent id), realm (default online)
 * Points: cumulative wins and cumulative goals scored per side. Draws: win totals unchanged.
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

if ($realm !== 'online' && $realm !== 'amiga') {
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

if ($realm === 'amiga') {
    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_h2h_pair_lib.php';

    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    if ($con->connect_errno) {
        http_response_code(500);
        echo json_encode(['error' => 'db_connect_failed']);
        exit;
    }
    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    $ctx = amiga_snapshot_context_from_request($con);
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_matchup_lib.php';
    $playerIdentity = amiga_player_identity_row($con, $playerId);
    $opponentIdentity = amiga_player_identity_row($con, $opponentId);
    if ($playerIdentity === null || $opponentIdentity === null) {
        mysqli_close($con);
        echo json_encode(['error' => 'player_not_found']);
        exit;
    }

    $cum = amiga_player_h2h_cumulative_payload($con, $playerId, $opponentId, $ctx);
    mysqli_close($con);

    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'playerName' => (string) $playerIdentity['name'],
        'opponentId' => $opponentId,
        'opponentName' => (string) $opponentIdentity['name'],
        'total_games' => (int) $cum['total_games'],
        'draws' => (int) $cum['draws'],
        'player_goals_total' => (int) $cum['player_goals_total'],
        'opponent_goals_total' => (int) $cum['opponent_goals_total'],
        'points' => $cum['points'],
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

$sql = 'SELECT id, Date, ActualScore, WinnerID, idA, GoalsA, GoalsB FROM ratedresults '
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
$playerGoals = 0;
$opponentGoals = 0;
$draws = 0;
$gameNumber = 0;

while ($row = $res->fetch_assoc()) {
    $gameNumber++;
    $actualScore = (float) $row['ActualScore'];
    $winnerId = (int) $row['WinnerID'];
    $idA = (int) $row['idA'];
    $goalsA = (int) $row['GoalsA'];
    $goalsB = (int) $row['GoalsB'];

    if ($idA === $playerId) {
        $playerGoals += $goalsA;
        $opponentGoals += $goalsB;
    } else {
        $playerGoals += $goalsB;
        $opponentGoals += $goalsA;
    }

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
        'player_goals' => $playerGoals,
        'opponent_goals' => $opponentGoals,
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
    'player_goals_total' => $playerGoals,
    'opponent_goals_total' => $opponentGoals,
    'points' => $points,
]);
