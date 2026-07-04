<?php
/**
 * JSON monthly games for the 10 players with the most rated games right now.
 * Powers the Activity "Top activity eras" line chart on activity.php.
 *
 * GET: realm (default online), limit (default 10, max 20)
 *
 * Selection: playertable.NumberGames DESC, ID ASC (provisionals included).
 * Timeline: every calendar month from first server month through latest month row.
 * Reads player_period_games (month rows); 0 games when a player had no row that month.
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
        'realm'   => $realm,
        'months'  => [],
        'players' => [],
        'meta'    => ['note' => 'realm_not_implemented'],
    ]);
    exit;
}

$limit = isset($_GET['limit']) ? max(1, min(20, (int) $_GET['limit'])) : 10;

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$check = mysqli_query($con, "SHOW TABLES LIKE 'player_period_games'");
if ($check === false || mysqli_num_rows($check) === 0) {
    echo json_encode([
        'realm'     => $realm,
        'period'    => 'month',
        'selection' => 'all_time_top_games',
        'limit'     => $limit,
        'months'    => [],
        'players'   => [],
        'meta'      => ['note' => 'player_period_games table not available'],
    ]);
    mysqli_close($con);
    exit;
}

$topSql = 'SELECT p.ID AS player_id, p.Name AS player_name, p.NumberGames AS total_games '
    . 'FROM playertable p '
    . 'WHERE p.NumberGames >= 1 '
    . 'ORDER BY p.NumberGames DESC, p.ID ASC '
    . 'LIMIT ' . $limit;

$topRes = mysqli_query($con, $topSql);
if ($topRes === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    mysqli_close($con);
    exit;
}

$topPlayers = [];
$topIds = [];
while ($row = mysqli_fetch_assoc($topRes)) {
    $pid = (int) $row['player_id'];
    $topIds[] = $pid;
    $topPlayers[$pid] = [
        'id'          => $pid,
        'name'        => (string) $row['player_name'],
        'total_games' => (int) $row['total_games'],
        'points'      => [],
    ];
}
mysqli_free_result($topRes);

if ($topIds === []) {
    echo json_encode([
        'realm'     => $realm,
        'period'    => 'month',
        'selection' => 'all_time_top_games',
        'limit'     => $limit,
        'months'    => [],
        'players'   => [],
        'meta'      => ['note' => 'no_players'],
    ]);
    mysqli_close($con);
    exit;
}

$rangeSql = "SELECT DATE_FORMAT(MIN(period_start), '%Y-%m') AS first_ym, "
    . "DATE_FORMAT(MAX(period_start), '%Y-%m') AS last_ym "
    . "FROM player_period_games WHERE period_type = 'month'";

$rangeRes = mysqli_query($con, $rangeSql);
if ($rangeRes === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    mysqli_close($con);
    exit;
}

$rangeRow = mysqli_fetch_assoc($rangeRes);
mysqli_free_result($rangeRes);

$firstYm = (string) ($rangeRow['first_ym'] ?? '');
$lastYm = (string) ($rangeRow['last_ym'] ?? '');
if ($firstYm === '' || $lastYm === '') {
    echo json_encode([
        'realm'     => $realm,
        'period'    => 'month',
        'selection' => 'all_time_top_games',
        'limit'     => $limit,
        'months'    => [],
        'players'   => array_values($topPlayers),
        'meta'      => ['note' => 'no_month_rows'],
    ]);
    mysqli_close($con);
    exit;
}

/**
 * @return array<int, string>
 */
function k2_month_range_ym(string $firstYm, string $lastYm): array
{
    $start = DateTimeImmutable::createFromFormat('Y-m-d', $firstYm . '-01', new DateTimeZone('UTC'));
    $end = DateTimeImmutable::createFromFormat('Y-m-d', $lastYm . '-01', new DateTimeZone('UTC'));
    if ($start === false || $end === false || $start > $end) {
        return [];
    }

    $months = [];
    $cursor = $start;
    while ($cursor <= $end) {
        $months[] = $cursor->format('Y-m');
        $cursor = $cursor->modify('+1 month');
    }

    return $months;
}

$allMonths = k2_month_range_ym($firstYm, $lastYm);

$idList = implode(',', array_map('intval', $topIds));
$gamesSql = 'SELECT DATE_FORMAT(g.period_start, \'%Y-%m\') AS ym, g.player_id, g.games '
    . 'FROM player_period_games g '
    . 'WHERE g.period_type = \'month\' AND g.player_id IN (' . $idList . ') '
    . 'ORDER BY g.period_start ASC';

$gamesRes = mysqli_query($con, $gamesSql);
if ($gamesRes === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    mysqli_close($con);
    exit;
}

$gamesByPlayerMonth = [];
while ($row = mysqli_fetch_assoc($gamesRes)) {
    $pid = (int) $row['player_id'];
    $ym = (string) $row['ym'];
    if (!isset($gamesByPlayerMonth[$pid])) {
        $gamesByPlayerMonth[$pid] = [];
    }
    $gamesByPlayerMonth[$pid][$ym] = (int) $row['games'];
}
mysqli_free_result($gamesRes);
mysqli_close($con);

foreach ($topIds as $pid) {
    $monthGames = $gamesByPlayerMonth[$pid] ?? [];
    $points = [];
    foreach ($allMonths as $ym) {
        $points[] = [
            'month' => $ym,
            'games' => $monthGames[$ym] ?? 0,
        ];
    }
    $topPlayers[$pid]['points'] = $points;
}

$players = array_values($topPlayers);
usort($players, function ($a, $b) {
    if ($a['total_games'] !== $b['total_games']) {
        return $b['total_games'] - $a['total_games'];
    }

    return $a['id'] - $b['id'];
});

echo json_encode([
    'realm'     => $realm,
    'period'    => 'month',
    'metric'    => 'games',
    'selection' => 'all_time_top_games',
    'limit'     => $limit,
    'months'    => $allMonths,
    'players'   => $players,
]);
