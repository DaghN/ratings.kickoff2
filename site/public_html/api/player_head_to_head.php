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
$oppCountry = isset($_GET['opp_country']) ? trim((string) $_GET['opp_country']) : '';

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

$pairHero = isset($_GET['country']) ? trim((string) $_GET['country']) : '';
$pairRival = isset($_GET['rival']) ? trim((string) $_GET['rival']) : '';

if ($realm === 'amiga' && $pairHero !== '' && $pairRival !== '') {
    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_rivals_h2h_games_lib.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_country_rivals_h2h.php';

    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    if ($con->connect_errno) {
        http_response_code(500);
        echo json_encode(['error' => 'db_connect_failed']);
        exit;
    }
    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    $ctx = amiga_snapshot_context_from_request($con);
    $heroToken = amiga_country_rivals_normalize_token($pairHero);
    $rivalToken = amiga_country_rivals_normalize_token($pairRival);
    $cum = amiga_country_rivals_h2h_cumulative_payload($con, $heroToken, $rivalToken, $ctx);
    mysqli_close($con);

    echo json_encode([
        'realm' => $realm,
        'playerId' => null,
        'playerName' => amiga_country_rivals_nation_label($heroToken),
        'opponentId' => null,
        'opponentName' => amiga_country_rivals_nation_label($rivalToken),
        'heroCountry' => $heroToken,
        'rivalCountry' => $rivalToken,
        'total_games' => (int) $cum['total_games'],
        'draws' => (int) $cum['draws'],
        'player_goals_total' => (int) $cum['player_goals_total'],
        'opponent_goals_total' => (int) $cum['opponent_goals_total'],
        'points' => $cum['points'],
    ]);
    exit;
}

if ($playerId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}

if ($realm === 'amiga' && $oppCountry !== '') {
    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_matchup_lib.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_h2h_country_lib.php';

    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    if ($con->connect_errno) {
        http_response_code(500);
        echo json_encode(['error' => 'db_connect_failed']);
        exit;
    }
    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    $ctx = amiga_snapshot_context_from_request($con);
    $playerIdentity = amiga_player_identity_row($con, $playerId);
    if ($playerIdentity === null) {
        mysqli_close($con);
        echo json_encode(['error' => 'player_not_found']);
        exit;
    }

    $countryToken = amiga_player_opponents_country_token_from_field($oppCountry);
    $cum = amiga_player_h2h_cumulative_by_country_payload($con, $playerId, $countryToken, $ctx);
    mysqli_close($con);

    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'playerName' => (string) $playerIdentity['name'],
        'opponentId' => null,
        'opponentName' => amiga_player_h2h_country_opponent_label($countryToken),
        'oppCountry' => $countryToken,
        'total_games' => (int) $cum['total_games'],
        'draws' => (int) $cum['draws'],
        'player_goals_total' => (int) $cum['player_goals_total'],
        'opponent_goals_total' => (int) $cum['opponent_goals_total'],
        'points' => $cum['points'],
    ]);
    exit;
}

if ($opponentId < 1) {
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
