<?php
/**
 * JSON top players by personal peak calendar month (most games in one month).
 *
 * GET: realm (default online), limit (default 20, max 100)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$realm = isset($_GET['realm']) ? strtolower(trim((string) $_GET['realm'])) : 'online';
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;

if ($limit < 1) {
    $limit = 20;
}
if ($limit > 100) {
    $limit = 100;
}

if ($realm !== 'online') {
    echo json_encode([
        'realm' => $realm,
        'limit' => $limit,
        'entries' => [],
        'meta' => ['note' => 'realm_not_implemented'],
    ]);
    exit;
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/peak_month_leaderboard_query.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}

$con->set_charset('utf8mb4');
$entries = k2_peak_month_leaderboard_entries($con, $limit);
mysqli_close($con);

echo json_encode([
    'realm' => $realm,
    'limit' => $limit,
    'entries' => $entries,
]);
