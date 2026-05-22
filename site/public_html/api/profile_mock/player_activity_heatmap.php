<?php
/**
 * Weekly rated-game counts for activity heatmap (profile mock).
 * GET: id (required), weeks (default 104, max 156)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$playerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$weeks = isset($_GET['weeks']) ? (int) $_GET['weeks'] : 104;
if ($weeks < 12) {
    $weeks = 12;
}
if ($weeks > 156) {
    $weeks = 156;
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

$sql = 'SELECT YEARWEEK(Date, 3) AS yw, MIN(DATE(Date)) AS week_start, COUNT(*) AS games '
    . 'FROM ratedresults WHERE (idA = ? OR idB = ?) '
    . 'AND Date >= DATE_SUB(CURDATE(), INTERVAL ? WEEK) '
    . 'GROUP BY yw ORDER BY yw ASC';

$stmt = $con->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'prepare_failed']);
    mysqli_close($con);
    exit;
}
$stmt->bind_param('iii', $playerId, $playerId, $weeks);
$stmt->execute();
$res = $stmt->get_result();

$points = [];
$maxGames = 0;
while ($row = $res->fetch_assoc()) {
    $g = (int) $row['games'];
    if ($g > $maxGames) {
        $maxGames = $g;
    }
    $points[] = [
        'yearWeek' => (int) $row['yw'],
        'weekStart' => $row['week_start'],
        'games' => $g,
    ];
}
$stmt->close();
mysqli_close($con);

echo json_encode([
    'playerId' => $playerId,
    'weeks' => $points,
    'maxGames' => $maxGames,
    'meta' => ['windowWeeks' => $weeks],
]);
