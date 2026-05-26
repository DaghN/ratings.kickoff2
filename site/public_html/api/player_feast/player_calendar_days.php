<?php
/**
 * Distinct calendar dates with at least one rated game (profile feast played-days calendar).
 * GET: id (required), year (default current calendar year)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

if ($playerId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_id']);
    exit;
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
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
$yearStart = $year . '-01-01';
$yearEnd = ($year + 1) . '-01-01';
$stmt->bind_param('iss', $playerId, $yearStart, $yearEnd);
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
    'year' => $year,
    'days' => $days,
]);
