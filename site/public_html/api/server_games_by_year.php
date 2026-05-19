<?php
/**
 * JSON rated game counts per calendar year (whole server).
 * Current calendar year includes linear pace projection for the remainder of the year.
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
        'current_year' => (int) date('Y'),
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

$sql = 'SELECT YEAR(`Date`) AS yr, COUNT(*) AS games '
    . 'FROM ratedresults GROUP BY yr ORDER BY yr ASC';

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
    $countsByYear[(int) $row['yr']] = (int) $row['games'];
}

mysqli_close($con);

$currentYear = (int) date('Y');
$daysInYear = (int) date('L') ? 366 : 365;
$dayOfYear = (int) date('z') + 1;

$years = [];
$yearKeys = array_keys($countsByYear);
if (!in_array($currentYear, $yearKeys, true)) {
    $yearKeys[] = $currentYear;
    sort($yearKeys, SORT_NUMERIC);
}

foreach ($yearKeys as $yr) {
    $games = $countsByYear[$yr] ?? 0;
    $isCurrent = ($yr === $currentYear);

    $entry = [
        'year' => $yr,
        'games' => $games,
        'is_current' => $isCurrent,
    ];

    if ($isCurrent && $dayOfYear > 0) {
        $projectedTotal = (int) round($games * $daysInYear / $dayOfYear);
        $remainder = $projectedTotal - $games;
        if ($remainder < 0) {
            $remainder = 0;
        }
        $entry['projected_total'] = $projectedTotal;
        $entry['projected_remainder'] = $remainder;
    }

    $years[] = $entry;
}

echo json_encode([
    'realm' => $realm,
    'current_year' => $currentYear,
    'projection' => [
        'days_elapsed' => $dayOfYear,
        'days_in_year' => $daysInYear,
        'method' => 'linear_pace',
    ],
    'years' => $years,
]);
