<?php
/**
 * Distinct calendar dates with at least one rated game (profile feast played-days calendar).
 * GET: id (required), from/to optional YYYY-MM-DD bounds
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$from = isset($_GET['from']) ? trim((string) $_GET['from']) : '2017-06-09';
$to = isset($_GET['to']) ? trim((string) $_GET['to']) : gmdate('Y-m-d', strtotime('+1 day'));

if ($playerId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = '2017-06-09';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = gmdate('Y-m-d', strtotime('+1 day'));
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

$stmt = $con->prepare(
    'SELECT `period_start` AS d FROM `player_period_games` '
    . 'WHERE `period_type` = \'day\' AND `player_id` = ? '
    . 'AND `period_start` >= ? AND `period_start` < ? '
    . 'ORDER BY `period_start` ASC'
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed']);
    mysqli_close($con);
    exit;
}
$stmt->bind_param('iss', $playerId, $from, $to);
$stmt->execute();
$result = $stmt->get_result();
$days = [];
while ($row = $result->fetch_assoc()) {
    $days[] = (string) $row['d'];
}
$stmt->close();
mysqli_close($con);

echo json_encode([
    'player_id' => $playerId,
    'from' => $from,
    'to' => $to,
    'days' => $days,
]);
