<?php
/**
 * JSON scoreline heatmap for one head-to-head pairing (subject GF × GA grid).
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

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'opponentId' => $opponentId,
        'playerName' => null,
        'opponentName' => null,
        'maxGoalsFor' => 0,
        'maxGoalsAgainst' => 0,
        'gridAxisMax' => 0,
        'totalGames' => 0,
        'cells' => [],
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

$payload = player_h2h_scoreline_heatmap_payload($con, $playerId, $opponentId);
$totalGames = 0;
foreach ($payload['cells'] as $cell) {
    $totalGames += (int) $cell['games'];
}

mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'playerId' => $playerId,
    'opponentId' => $opponentId,
    'playerName' => $names[$playerId],
    'opponentName' => $names[$opponentId],
    'maxGoalsFor' => (int) $payload['max_goals_for'],
    'maxGoalsAgainst' => (int) $payload['max_goals_against'],
    'gridAxisMax' => (int) ($payload['grid_axis_max'] ?? 0),
    'totalGames' => $totalGames,
    'cells' => $payload['cells'],
]);
