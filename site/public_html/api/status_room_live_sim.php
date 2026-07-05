<?php
/**
 * Status room live sim — start / stop / status (work DB only).
 *
 * GET/POST action=start|stop|status
 *   start: optional games=20
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_room_live_sim.php';
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

if (!k2_status_room_sim_is_allowed()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'sim_not_allowed']);
    exit;
}

k2_site_ensure_utc();
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_connect_failed']);
    exit;
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : 'status';
$result = ['ok' => false, 'error' => 'unknown_action'];

if ($action === 'start') {
    $games = isset($_REQUEST['games']) ? (int) $_REQUEST['games'] : K2_STATUS_ROOM_SIM_DEFAULT_GAMES;
    $result = k2_status_room_sim_start($con, $games);
} elseif ($action === 'stop') {
    $result = k2_status_room_sim_stop($con);
} elseif ($action === 'status') {
    k2_status_room_sim_tick_if_due($con);
    $state = k2_status_room_sim_load_state();
    $result = [
        'ok' => true,
        'status' => k2_status_room_sim_public_status($state),
    ];
}

mysqli_close($con);
echo json_encode($result, JSON_UNESCAPED_UNICODE);