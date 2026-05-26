<?php
/**
 * JSON top-N most active players per calendar month, across all months.
 * Powers the "Top activity eras" line chart on Activity.
 *
 * GET: realm (default online), limit (default 10, max 20)
 *
 * Reads from the player_period_games aggregate. Returns an error note
 * if the table is missing or empty rather than falling back to a heavy
 * full-history query.
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

// Check table exists
$check = mysqli_query($con, "SHOW TABLES LIKE 'player_period_games'");
if ($check === false || mysqli_num_rows($check) === 0) {
    echo json_encode([
        'realm'   => $realm,
        'period'  => 'month',
        'limit'   => $limit,
        'months'  => [],
        'players' => [],
        'meta'    => ['note' => 'player_period_games table not available'],
    ]);
    mysqli_close($con);
    exit;
}

$sql = <<<'SQL'
SELECT DATE_FORMAT(g.period_start, '%Y-%m') AS ym,
       g.player_id,
       p.Name AS player_name,
       g.games
  FROM player_period_games g
  INNER JOIN playertable p ON p.ID = g.player_id
 WHERE g.period_type = 'month'
 ORDER BY g.period_start ASC, g.games DESC, p.Name ASC
SQL;

$res = mysqli_query($con, $sql);
if ($res === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    mysqli_close($con);
    exit;
}

// Rank per month in PHP: walk rows grouped by ym, keep top N
$allMonths = [];
$playerPoints = []; // player_id => [ {month, games, rank}, ... ]
$playerNames = [];  // player_id => name

$currentYm = null;
$rankInMonth = 0;

while ($row = mysqli_fetch_assoc($res)) {
    $ym = (string) $row['ym'];
    $pid = (int) $row['player_id'];
    $games = (int) $row['games'];
    $name = (string) $row['player_name'];

    if ($ym !== $currentYm) {
        $currentYm = $ym;
        $rankInMonth = 0;
        if (!in_array($ym, $allMonths, true)) {
            $allMonths[] = $ym;
        }
    }

    $rankInMonth++;
    if ($rankInMonth > $limit) {
        continue;
    }

    $playerNames[$pid] = $name;
    if (!isset($playerPoints[$pid])) {
        $playerPoints[$pid] = [];
    }
    $playerPoints[$pid][] = [
        'month' => $ym,
        'games' => $games,
        'rank'  => $rankInMonth,
    ];
}

mysqli_free_result($res);
mysqli_close($con);

// Build players array sorted by total top-months descending, then name
$players = [];
foreach ($playerPoints as $pid => $points) {
    $players[] = [
        'id'         => $pid,
        'name'       => $playerNames[$pid],
        'top_months' => count($points),
        'points'     => $points,
    ];
}

usort($players, function ($a, $b) {
    if ($a['top_months'] !== $b['top_months']) {
        return $b['top_months'] - $a['top_months'];
    }
    return strcmp($a['name'], $b['name']);
});

echo json_encode([
    'realm'   => $realm,
    'period'  => 'month',
    'metric'  => 'games',
    'limit'   => $limit,
    'months'  => $allMonths,
    'players' => $players,
]);
