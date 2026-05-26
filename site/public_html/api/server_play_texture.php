<?php
/**
 * JSON monthly play texture: goals/game, draw rate, DD rate, clean-sheet rate.
 * Powers the normalized play-texture small-multiples chart.
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
SELECT DATE_FORMAT(`Date`, '%Y-%m') AS ym,
       COUNT(*)                                              AS games,
       SUM(GoalsA + GoalsB)                                  AS total_goals,
       SUM(CASE WHEN GoalsA = GoalsB THEN 1 ELSE 0 END)     AS draws,
       SUM(CASE WHEN GoalsA >= 10 OR GoalsB >= 10 THEN 1 ELSE 0 END) AS dd_games,
       SUM(CASE WHEN GoalsA = 0 OR GoalsB = 0 THEN 1 ELSE 0 END)    AS cs_games
FROM ratedresults
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
    $games = (int) $row['games'];
    if ($games === 0) {
        continue;
    }
    $months[] = [
        'month'           => (string) $row['ym'],
        'games'           => $games,
        'goals_per_game'  => round((int) $row['total_goals'] / $games, 2),
        'draw_pct'        => round(100 * (int) $row['draws'] / $games, 1),
        'dd_per_100'      => round(100 * (int) $row['dd_games'] / $games, 1),
        'cs_per_100'      => round(100 * (int) $row['cs_games'] / $games, 1),
    ];
}

mysqli_free_result($res);
mysqli_close($con);

echo json_encode([
    'realm'  => $realm,
    'months' => $months,
]);
