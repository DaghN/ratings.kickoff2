<?php
/**
 * HTML rated games for one Monday-start UTC week (Status Leagues — Weekly tab).
 *
 * GET: key=Y-m-d (week Monday)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_week_games_render.php';

$keyRaw = isset($_GET['key']) ? trim((string) $_GET['key']) : '';
$key = k2_period_activity_normalize_key('week', $keyRaw);
if ($key === null) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_key']);
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

$queryError = null;
$days = k2_status_rated_games_for_calendar_week($con, $key, $queryError);
if ($queryError !== null || $days === null) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed', 'detail' => $queryError]);
    mysqli_close($con);
    exit;
}

mysqli_close($con);

echo json_encode([
    'key' => $key,
    'html' => k2_status_week_games_html($days),
]);