<?php
/**
 * JSON daily rated-game counts for the past 365 days.
 * Powers the 12-month activity heatmap.
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
        'days'  => [],
        'meta'  => ['note' => 'realm_not_implemented'],
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

$sql = 'SELECT DATE(`Date`) AS day, COUNT(*) AS games '
     . 'FROM ratedresults '
     . 'WHERE `Date` >= DATE_SUB(CURDATE(), INTERVAL 364 DAY) '
     . 'AND `Date` < DATE_ADD(CURDATE(), INTERVAL 1 DAY) '
     . 'GROUP BY day ORDER BY day ASC';

$res = mysqli_query($con, $sql);
if ($res === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    mysqli_close($con);
    exit;
}

$days = [];
while ($row = mysqli_fetch_assoc($res)) {
    $days[] = [
        'day'   => (string) $row['day'],
        'games' => (int) $row['games'],
    ];
}

mysqli_free_result($res);
mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'days'  => $days,
]);
