<?php
/**
 * JSON total rated game counts per calendar day for the recent activity window.
 *
 * GET: realm (default online)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'days' => [],
        'meta' => ['note' => 'realm_not_implemented'],
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

$rangeSql = "SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 29 DAY), '%Y-%m-%d') AS start_day, DATE_FORMAT(CURDATE(), '%Y-%m-%d') AS end_day";
$rangeRes = mysqli_query($con, $rangeSql);
if ($rangeRes === false) {
    http_response_code(500);
    echo json_encode(['error' => 'range_query_failed']);
    mysqli_close($con);
    exit;
}

$range = mysqli_fetch_assoc($rangeRes);
mysqli_free_result($rangeRes);

$startDay = (string) ($range['start_day'] ?? '');
$endDay = (string) ($range['end_day'] ?? '');
$start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDay);
$end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDay);

if ($start === false || $end === false) {
    http_response_code(500);
    echo json_encode(['error' => 'invalid_range']);
    mysqli_close($con);
    exit;
}

$counts = [];
for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
    $counts[$day->format('Y-m-d')] = 0;
}

$sql = 'SELECT `period_start` AS day, `rated_games` AS games '
    . 'FROM `server_period_game_totals` '
    . 'WHERE `period_type` = \'day\' '
    . 'AND `period_start` >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) '
    . 'AND `period_start` <= CURDATE() '
    . 'ORDER BY `period_start` ASC';

$res = mysqli_query($con, $sql);
if ($res === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    mysqli_close($con);
    exit;
}

while ($row = mysqli_fetch_assoc($res)) {
    $day = (string) ($row['day'] ?? '');
    if (isset($counts[$day])) {
        $counts[$day] = (int) $row['games'];
    }
}

mysqli_free_result($res);
mysqli_close($con);

$days = [];
foreach ($counts as $day => $games) {
    $days[] = [
        'day' => $day,
        'games' => $games,
    ];
}

echo json_encode([
    'realm' => $realm,
    'days' => $days,
    'meta' => [
        'start_day' => $startDay,
        'end_day' => $endDay,
        'days' => count($days),
    ],
]);
