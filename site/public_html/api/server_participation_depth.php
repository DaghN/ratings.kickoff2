<?php
/**
 * JSON monthly participation depth: how many players played 1, 2-4, 5-9, 10+ games.
 * Powers the participation depth stacked bar chart.
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
        'realm'  => $realm,
        'months' => [],
        'meta'   => ['note' => 'realm_not_implemented'],
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

$sql = <<<'SQL'
SELECT DATE_FORMAT(`period_start`, '%Y-%m') AS ym,
       SUM(CASE WHEN `games` = 1             THEN 1 ELSE 0 END) AS band_1,
       SUM(CASE WHEN `games` BETWEEN 2 AND 4 THEN 1 ELSE 0 END) AS band_2_4,
       SUM(CASE WHEN `games` BETWEEN 5 AND 9 THEN 1 ELSE 0 END) AS band_5_9,
       SUM(CASE WHEN `games` >= 10           THEN 1 ELSE 0 END) AS band_10plus
FROM `player_period_games`
WHERE `period_type` = 'month'
GROUP BY ym
ORDER BY ym ASC
SQL;

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
        'month'      => (string) $row['ym'],
        'band_1'     => (int) $row['band_1'],
        'band_2_4'   => (int) $row['band_2_4'],
        'band_5_9'   => (int) $row['band_5_9'],
        'band_10plus' => (int) $row['band_10plus'],
    ];
}

mysqli_free_result($res);
mysqli_close($con);

echo json_encode([
    'realm'  => $realm,
    'months' => $months,
]);
