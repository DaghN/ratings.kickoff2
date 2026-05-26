<?php
/**
 * JSON most-played opponents for one player (by rated game count).
 *
 * GET: id (required), realm (default online), limit (default 20, max 20)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

if ($limit < 1) {
    $limit = 20;
}
if ($limit > 20) {
    $limit = 20;
}

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'opponents' => [],
        'meta' => ['note' => 'realm_not_implemented'],
    ]);
    exit;
}

if ($playerId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
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
        'playerName' => null,
        'opponents' => [],
        'meta' => ['note' => 'player_not_found'],
    ]);
    exit;
}

$playerName = $nameRow['Name'];

$sql = 'SELECT m.opponent_id, COALESCE(p.Name, CONCAT(\'#\', m.opponent_id)) AS opponent_name, m.games '
    . 'FROM player_matchup_summary m '
    . 'LEFT JOIN playertable p ON p.ID = m.opponent_id '
    . 'WHERE m.player_id = ? '
    . 'ORDER BY m.games DESC, opponent_name ASC LIMIT ?';

$stmt = $con->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed']);
    mysqli_close($con);
    exit;
}

$stmt->bind_param('ii', $playerId, $limit);
$stmt->execute();
$res = $stmt->get_result();

$opponents = [];
while ($row = $res->fetch_assoc()) {
    $opponents[] = [
        'opponent_id' => (int) $row['opponent_id'],
        'opponent_name' => $row['opponent_name'],
        'games' => (int) $row['games'],
    ];
}

$stmt->close();
mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'playerId' => $playerId,
    'playerName' => $playerName,
    'limit' => $limit,
    'opponents' => $opponents,
]);
