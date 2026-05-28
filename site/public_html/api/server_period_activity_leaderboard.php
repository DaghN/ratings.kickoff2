<?php
/**
 * JSON leaderboard: players by rated games in one calendar day, week, month, or year.
 *
 * GET: period=day|week|month|year, key=(Y-m-d | Monday Y-m-d | Y-m | YYYY), limit (optional; 0 or omit = all players, max 500)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';

$period = isset($_GET['period']) ? (string) $_GET['period'] : '';
$period = k2_period_activity_normalize_period($period);
if ($period === null) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_period']);
    exit;
}

$keyRaw = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
$key = k2_period_activity_normalize_key($period, $keyRaw);
if ($key === null) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_key']);
    exit;
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 0;
if ($limit > 0) {
    $limit = max(1, min(500, $limit));
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

$queryError = null;
$entries = k2_period_activity_leaderboard_entries($con, $period, $key, $limit, $queryError);
if ($queryError !== null) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed', 'detail' => $queryError]);
    mysqli_close($con);
    exit;
}

$totalGames = k2_period_activity_total_games($con, $period, $key);

mysqli_close($con);

echo json_encode([
    'period' => $period,
    'key' => $key,
    'label' => k2_format_period_activity_label($period, $key),
    'total_games' => $totalGames,
    'entries' => $entries,
]);
