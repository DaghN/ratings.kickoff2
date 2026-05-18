<?php
/**
 * JSON game counts per calendar month for one player.
 *
 * GET: id (required), realm (default online)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'playerId' => $playerId,
        'playerName' => null,
        'months' => [],
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
        'months' => [],
        'meta' => ['note' => 'player_not_found'],
    ]);
    exit;
}

$playerName = $nameRow['Name'];

$sql = 'SELECT DATE_FORMAT(`Date`, \'%Y-%m\') AS ym, COUNT(*) AS games '
    . 'FROM ratedresults WHERE idA = ? OR idB = ? '
    . 'GROUP BY ym ORDER BY ym ASC';

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

$months = [];
while ($row = $res->fetch_assoc()) {
    $months[] = [
        'month' => $row['ym'],
        'games' => (int) $row['games'],
    ];
}

$stmt->close();
mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'playerId' => $playerId,
    'playerName' => $playerName,
    'months' => $months,
]);
