<?php
/**
 * JSON unlock counts over time for one milestone (monthly buckets).
 *
 * GET: key (required), realm (default online)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$key = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
if ($key === '' || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_key']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'milestone_key' => $key,
        'points' => [],
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

$keyEsc = mysqli_real_escape_string($con, $key);
$displayName = $key;
$chartToken = 'pitch';

$defRes = mysqli_query(
    $con,
    "SELECT `display_name`, `chart_token` FROM `milestone_definitions` WHERE `milestone_key` = '$keyEsc' LIMIT 1"
);
if ($defRes !== false) {
    $defRow = mysqli_fetch_assoc($defRes);
    mysqli_free_result($defRes);
    if ($defRow) {
        $displayName = str_replace('**', '', (string) $defRow['display_name']);
        $chartToken = (string) $defRow['chart_token'];
    }
}

$sql = "
    SELECT DATE_FORMAT(`achieved_at`, '%Y-%m-01') AS bucket, COUNT(*) AS unlocks
    FROM `player_milestones`
    WHERE `milestone_key` = '$keyEsc'
    GROUP BY bucket
    ORDER BY bucket ASC
";

$points = [];
$res = mysqli_query($con, $sql);
if ($res !== false) {
    while ($row = mysqli_fetch_assoc($res)) {
        $points[] = [
            'month' => (string) $row['bucket'],
            'unlocks' => (int) $row['unlocks'],
        ];
    }
    mysqli_free_result($res);
}

mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'milestone_key' => $key,
    'display_name' => trim($displayName),
    'chart_token' => $chartToken,
    'points' => $points,
]);
