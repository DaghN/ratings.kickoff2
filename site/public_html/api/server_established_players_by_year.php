<?php
/**
 * JSON count of players newly "established" per calendar year (whole server).
 * Established = career rated game #20 falls in that year (20 games minimum).
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
$gamesRequired = 20;

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'games_required' => $gamesRequired,
        'years' => [],
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

$sql = "SELECT YEAR(`achieved_at`) AS yr, COUNT(*) AS established_players "
    . "FROM `player_milestones` "
    . "WHERE `milestone_key` = 'established_20' "
    . "GROUP BY yr ORDER BY yr ASC";

$res = mysqli_query($con, $sql);
if ($res === false) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
    mysqli_close($con);
    exit;
}

$countsByYear = [];
while ($row = mysqli_fetch_assoc($res)) {
    if ($row['yr'] === null) {
        continue;
    }
    $countsByYear[(int) $row['yr']] = (int) $row['established_players'];
}

mysqli_close($con);

$currentYear = (int) date('Y');
$yearKeys = array_keys($countsByYear);
if (!in_array($currentYear, $yearKeys, true)) {
    $yearKeys[] = $currentYear;
    sort($yearKeys, SORT_NUMERIC);
}

$years = [];
foreach ($yearKeys as $yr) {
    $years[] = [
        'year' => $yr,
        'established_players' => $countsByYear[$yr] ?? 0,
    ];
}

echo json_encode([
    'realm' => $realm,
    'games_required' => $gamesRequired,
    'current_year' => $currentYear,
    'years' => $years,
]);
