<?php
/**
 * JSON recent milestone digest: latest DD merchant, busiest day,
 * busiest month, most recent game.
 *
 * Avoids ROW_NUMBER() window functions for speed.
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
        'realm'      => $realm,
        'milestones' => [],
        'meta'       => ['note' => 'realm_not_implemented'],
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

$milestones = [];

/* --- Latest Double Digit Merchant (first 10+ goal game, most recent first) --- */
$sql = <<<'SQL'
SELECT firsts.player_id, firsts.first_dd_date, p.Name AS player_name
FROM (
    SELECT player_id, MIN(game_date) AS first_dd_date
    FROM (
        SELECT idA AS player_id, DATE(`Date`) AS game_date FROM ratedresults WHERE GoalsA >= 10
        UNION ALL
        SELECT idB AS player_id, DATE(`Date`) AS game_date FROM ratedresults WHERE GoalsB >= 10
    ) dd
    GROUP BY player_id
) firsts
JOIN playertable p ON p.id = firsts.player_id AND p.Display = 1
ORDER BY firsts.first_dd_date DESC
LIMIT 1
SQL;

$res = mysqli_query($con, $sql);
if ($res !== false) {
    $row = mysqli_fetch_assoc($res);
    if ($row) {
        $milestones[] = [
            'type'   => 'dd_merchant',
            'label'  => 'Latest Double Digit Merchant',
            'player' => (string) $row['player_name'],
            'date'   => (string) $row['first_dd_date'],
        ];
    }
    mysqli_free_result($res);
}

/* --- Busiest single day ever --- */
$sql = <<<'SQL'
SELECT DATE(`Date`) AS day, COUNT(*) AS games
FROM ratedresults
GROUP BY day
ORDER BY games DESC, day DESC
LIMIT 1
SQL;

$res = mysqli_query($con, $sql);
if ($res !== false) {
    $row = mysqli_fetch_assoc($res);
    if ($row) {
        $milestones[] = [
            'type'  => 'busiest_day',
            'label' => 'Busiest day',
            'value' => (int) $row['games'] . ' games',
            'date'  => (string) $row['day'],
        ];
    }
    mysqli_free_result($res);
}

/* --- Busiest month ever --- */
$sql = <<<'SQL'
SELECT DATE_FORMAT(`Date`, '%Y-%m') AS ym, COUNT(*) AS games
FROM ratedresults
GROUP BY ym
ORDER BY games DESC, ym DESC
LIMIT 1
SQL;

$res = mysqli_query($con, $sql);
if ($res !== false) {
    $row = mysqli_fetch_assoc($res);
    if ($row) {
        $ym = (string) $row['ym'];
        $ts = strtotime($ym . '-01');
        $friendlyMonth = $ts !== false ? date('F Y', $ts) : $ym;
        $milestones[] = [
            'type'  => 'busiest_month',
            'label' => 'Busiest month',
            'value' => (int) $row['games'] . ' games',
            'date'  => $friendlyMonth,
        ];
    }
    mysqli_free_result($res);
}

/* --- Most recent rated game --- */
$sql = <<<'SQL'
SELECT DATE(`Date`) AS day
FROM ratedresults
ORDER BY `Date` DESC
LIMIT 1
SQL;

$res = mysqli_query($con, $sql);
if ($res !== false) {
    $row = mysqli_fetch_assoc($res);
    if ($row) {
        $milestones[] = [
            'type'  => 'latest_game',
            'label' => 'Most recent rated game',
            'date'  => (string) $row['day'],
        ];
    }
    mysqli_free_result($res);
}

mysqli_close($con);

echo json_encode([
    'realm'      => $realm,
    'milestones' => $milestones,
]);
