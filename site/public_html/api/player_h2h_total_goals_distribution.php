<?php
/**
 * JSON total-goals-per-game histogram for one head-to-head pairing (SumOfGoals).
 *
 * GET: id (subject), opponent (required), realm (default online)
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
        'playerName' => null,
        'opponentName' => null,
        'maxGoals' => 0,
        'totalGames' => 0,
        'buckets' => [],
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
    $playerIdentity = amiga_player_identity_row($con, $playerId);
    $opponentIdentity = amiga_player_identity_row($con, $opponentId);
    if ($playerIdentity === null || $opponentIdentity === null) {
        mysqli_close($con);
        echo json_encode(['error' => 'player_not_found']);
        exit;
    }

    $buckets = amiga_player_h2h_total_goals_buckets($con, $playerId, $opponentId, $ctx);
    $maxGoals = $buckets === [] ? 0 : (int) $buckets[count($buckets) - 1]['goals'];
    $totalGames = player_goals_scored_distribution_total_games($buckets);
    $goalSum = 0;
    foreach ($buckets as $bucket) {
        $goalSum += (int) $bucket['goals'] * (int) $bucket['games'];
    }
    $avgTotalGoals = $totalGames > 0 ? round($goalSum / $totalGames, 2) : null;
    mysqli_close($con);

    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'opponentId' => $opponentId,
        'playerName' => (string) $playerIdentity['name'],
        'opponentName' => (string) $opponentIdentity['name'],
        'maxGoals' => $maxGoals,
        'totalGames' => $totalGames,
        'avgTotalGoals' => $avgTotalGoals,
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

$buckets = player_h2h_total_goals_distribution_buckets($con, $playerId, $opponentId);
$maxGoals = $buckets === [] ? 0 : (int) $buckets[count($buckets) - 1]['goals'];
$totalGames = player_goals_scored_distribution_total_games($buckets);
$goalSum = 0;
foreach ($buckets as $bucket) {
    $goalSum += (int) $bucket['goals'] * (int) $bucket['games'];
}
$avgTotalGoals = $totalGames > 0 ? round($goalSum / $totalGames, 2) : null;

mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'playerId' => $playerId,
    'opponentId' => $opponentId,
    'playerName' => $names[$playerId],
    'opponentName' => $names[$opponentId],
    'maxGoals' => $maxGoals,
    'totalGames' => $totalGames,
    'avgTotalGoals' => $avgTotalGoals,
    'buckets' => $buckets,
]);
