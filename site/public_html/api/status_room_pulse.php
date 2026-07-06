<?php
/**
 * Status room live heartbeat — 1 s signal bundle + conditional section payloads.
 *
 * GET: revision, last_rated_id, live_fp, online_fp, … (previous signals),
 *      period, key (active league tab)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_room_pulse.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_room_live_sim.php';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

k2_site_ensure_utc();
$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}
$con->set_charset('utf8mb4');
if (!$con->query("SET time_zone = '+00:00'")) {
    http_response_code(500);
    echo json_encode(['error' => 'db_timezone_failed']);
    mysqli_close($con);
    exit;
}

if (k2_status_room_sim_is_allowed()) {
    k2_status_room_sim_tick_if_due($con);
}

$leaguePeriod = isset($_GET['period']) ? (string) $_GET['period'] : 'week';
if (!in_array($leaguePeriod, ['day', 'week', 'month', 'year'], true)) {
    $leaguePeriod = 'week';
}
$leagueKey = isset($_GET['key']) ? trim((string) $_GET['key']) : '';

$prevRevision = isset($_GET['revision']) ? trim((string) $_GET['revision']) : '';
$prevSignals = [
    'last_rated_id' => (int) ($_GET['last_rated_id'] ?? 0),
    'games_played' => (int) ($_GET['games_played'] ?? 0),
    'live_fp' => isset($_GET['live_fp']) ? (string) $_GET['live_fp'] : '',
    'online_fp' => isset($_GET['online_fp']) ? (string) $_GET['online_fp'] : '',
    'last_login_epoch' => (int) ($_GET['last_login_epoch'] ?? 0),
    'last_login_id' => (int) ($_GET['last_login_id'] ?? 0),
    'last_join_epoch' => (int) ($_GET['last_join_epoch'] ?? 0),
    'last_join_id' => (int) ($_GET['last_join_id'] ?? 0),
    'league_fp' => isset($_GET['league_fp']) ? (string) $_GET['league_fp'] : '',
];
$periodKeysRaw = isset($_GET['period_keys']) ? (string) $_GET['period_keys'] : '';
if ($periodKeysRaw !== '') {
    $decodedKeys = json_decode($periodKeysRaw, true);
    if (is_array($decodedKeys)) {
        $prevSignals['period_keys'] = $decodedKeys;
    }
}

$bundle = k2_status_pulse_collect_signals($con, $leaguePeriod, $leagueKey);
$signals = is_array($bundle['signals'] ?? null) ? $bundle['signals'] : [];
$revision = (string) ($bundle['revision'] ?? '');
$serverNowEpoch = (int) ($bundle['server_now_epoch'] ?? time());
$liveClocks = k2_status_pulse_live_clock_payload(
    is_array($bundle['_live_games'] ?? null) ? $bundle['_live_games'] : null
);

if ($leagueKey === '' && is_array($signals['period_keys'] ?? null)) {
    $leagueKey = (string) ($signals['period_keys'][$leaguePeriod] ?? '');
}

$firstPoll = $prevRevision === '';
$unchanged = !$firstPoll && !k2_status_pulse_client_signals_stale($prevSignals, $signals);
if ($unchanged) {
    mysqli_close($con);
    echo json_encode([
        'changed' => false,
        'revision' => $revision,
        'server_now_epoch' => $serverNowEpoch,
        'live_clocks' => $liveClocks,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$want = k2_status_pulse_changed_sections($prevSignals, $signals, $firstPoll);
$cascade = in_array('cascade', $want, true);
if ($cascade) {
    $want = ['cascade'];
}

$sections = [];
if ($want !== []) {
    $sections = k2_status_pulse_build_sections(
        $con,
        $want,
        $bundle,
        $leaguePeriod,
        $leagueKey,
        $serverNowEpoch
    );
}

mysqli_close($con);

$payload = [
    'changed' => true,
    'revision' => $revision,
    'server_now_epoch' => $serverNowEpoch,
    'signals' => $signals,
    'cascade' => $cascade,
    'live_clocks' => $liveClocks,
];
if ($sections !== []) {
    $payload['sections'] = $sections;
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
