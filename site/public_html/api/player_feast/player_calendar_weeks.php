<?php
/**
 * UTC weeks with at least one rated game (profile feast played-weeks map).
 * GET: id (required)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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

$stmt = $con->prepare(
    'SELECT `period_start` AS w, `games` FROM `player_period_games` '
    . 'WHERE `period_type` = \'week\' AND `player_id` = ? '
    . 'ORDER BY `period_start` ASC'
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed']);
    mysqli_close($con);
    exit;
}
$stmt->bind_param('i', $playerId);
$stmt->execute();
$result = $stmt->get_result();
$weeks = [];
while ($row = $result->fetch_assoc()) {
    $weeks[] = [
        'start' => (string) $row['w'],
        'games' => (int) $row['games'],
    ];
}
$stmt->close();
mysqli_close($con);

echo json_encode([
    'player_id' => $playerId,
    'weeks' => $weeks,
]);
