<?php
/**
 * JSON goals-scored-per-game histogram for one player (0..max, every integer).
 *
 * GET: id (required), opponent (optional — pair scope), realm (default online)
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
        'opponentId' => $opponentId > 0 ? $opponentId : null,
        'playerName' => null,
        'opponentName' => null,
        'maxGoals' => 0,
        'totalGames' => 0,
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

if ($opponentId > 0 && $opponentId === $playerId) {
    http_response_code(400);
    echo json_encode(['error' => 'same_player']);
    exit;
}

$scopeOpponentId = $opponentId > 0 ? $opponentId : null;

if ($realm === 'amiga') {
    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_matchup_lib.php';
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
    $identity = amiga_player_identity_row($con, $playerId);
    if ($identity === null) {
        mysqli_close($con);
        echo json_encode([
            'realm' => $realm,
            'playerId' => $playerId,
            'opponentId' => $scopeOpponentId,
            'playerName' => null,
            'opponentName' => null,
            'maxGoals' => 0,
            'totalGames' => 0,
            'buckets' => [],
            'meta' => ['note' => 'player_not_found'],
        ]);
        exit;
    }

    $opponentName = null;
    if ($scopeOpponentId !== null) {
        $oppIdentity = amiga_player_identity_row($con, $scopeOpponentId);
        $opponentName = $oppIdentity !== null ? (string) $oppIdentity['name'] : null;
        $buckets = amiga_player_h2h_goals_scored_buckets($con, $playerId, $scopeOpponentId, $ctx);
    } else {
        $buckets = [];
    }

    $maxGoals = $buckets === [] ? 0 : (int) $buckets[count($buckets) - 1]['goals'];
    $totalGames = player_goals_scored_distribution_total_games($buckets);
    $avgGoalsPerGame = player_goals_scored_distribution_avg_goals_per_game($buckets);
    mysqli_close($con);

    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'opponentId' => $scopeOpponentId,
        'playerName' => (string) $identity['name'],
        'opponentName' => $opponentName,
        'maxGoals' => $maxGoals,
        'totalGames' => $totalGames,
        'avgGoalsPerGame' => $avgGoalsPerGame,
        'buckets' => $buckets,
    ]);
    exit;
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_goals_distribution.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

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
        'opponentId' => $opponentId > 0 ? $opponentId : null,
        'playerName' => null,
        'opponentName' => null,
        'maxGoals' => 0,
        'totalGames' => 0,
        'buckets' => [],
        'meta' => ['note' => 'player_not_found'],
    ]);
    exit;
}

$playerName = $nameRow['Name'];
$opponentName = null;
$scopeOpponentId = $opponentId > 0 ? $opponentId : null;

if ($scopeOpponentId !== null) {
    $oppStmt = $con->prepare('SELECT Name FROM playertable WHERE ID = ? LIMIT 1');
    if ($oppStmt) {
        $oppStmt->bind_param('i', $scopeOpponentId);
        $oppStmt->execute();
        $oppRes = $oppStmt->get_result();
        $oppRow = $oppRes->fetch_assoc();
        $oppStmt->close();
        if ($oppRow !== null) {
            $opponentName = $oppRow['Name'];
        }
    }
}

$buckets = player_goals_scored_distribution_buckets($con, $playerId, $scopeOpponentId);
$maxGoals = $buckets === [] ? 0 : (int) $buckets[count($buckets) - 1]['goals'];
$totalGames = player_goals_scored_distribution_total_games($buckets);
$avgGoalsPerGame = player_goals_scored_distribution_avg_goals_per_game($buckets);

mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'playerId' => $playerId,
    'opponentId' => $scopeOpponentId,
    'playerName' => $playerName,
    'opponentName' => $opponentName,
    'maxGoals' => $maxGoals,
    'totalGames' => $totalGames,
    'avgGoalsPerGame' => $avgGoalsPerGame,
    'buckets' => $buckets,
]);
