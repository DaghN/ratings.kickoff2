<?php
/**
 * JSON total rated game counts per calendar month (whole server).
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
        'months' => [],
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

$sql = 'SELECT DATE_FORMAT(`period_start`, \'%Y-%m\') AS ym, `rated_games` AS games '
    . 'FROM `server_period_game_totals` '
    . 'WHERE `period_type` = \'month\' ORDER BY `period_start` ASC';

$res = mysqli_query($con, $sql);
if ($res === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    mysqli_close($con);
    exit;
}

$months = [];
while ($row = mysqli_fetch_assoc($res)) {
    $months[] = [
        'month' => $row['ym'],
        'games' => (int) $row['games'],
    ];
}

mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'months' => $months,
]);
