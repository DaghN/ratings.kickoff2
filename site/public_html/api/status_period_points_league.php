<?php
/**
 * JSON points league for one calendar day / week / month / year (Status archive).
 *
 * GET: period=day|week|month|year, key=(Y-m-d | Monday Y-m-d | Y-m | YYYY), limit (optional, omit = all rows)
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';

$period = isset($_GET['period']) ? (string) $_GET['period'] : '';
if (!in_array($period, ['day', 'week', 'month', 'year'], true)) {
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

$limit = null;
if (isset($_GET['limit']) && $_GET['limit'] !== '') {
    $limit = max(1, min(500, (int) $_GET['limit']));
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

k2_site_ensure_utc();
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}
$con->set_charset('utf8mb4');
if (!$con->query("SET time_zone = '+00:00'")) {
    http_response_code(500);
    echo json_encode(['error' => 'db_timezone_failed']);
    exit;
}

$queryError = null;
$league = k2_status_league_for_key($con, $period, $key, $limit, $queryError);
if ($queryError !== null || $league === null) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed', 'detail' => $queryError]);
    mysqli_close($con);
    exit;
}

$timing = k2_status_league_timing_for_api($con, $league);

mysqli_close($con);

echo json_encode([
    'period' => $period,
    'key' => $key,
    'label' => (string) ($league['label'] ?? ''),
    'total_games' => (int) ($league['total_games'] ?? 0),
    'end' => (string) ($league['end'] ?? ''),
    'end_epoch' => $timing['end_epoch'],
    'end_label' => k2_status_league_end_label($league),
    'server_now_epoch' => $timing['server_now_epoch'],
    'show_medals' => $timing['show_medals'],
    'rows' => $league['rows'] ?? [],
]);
