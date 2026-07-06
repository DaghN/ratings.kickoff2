<?php
/**
 * JSON cumulative headline series across finalized events (Activity curves).
 *
 * GET: metric (headline column whitelist below), as (time travel cutoff).
 * Points are chrono-ordered; under time travel only events on or before the
 * cutoff are returned. `name` feeds tooltips + tournament click-through.
 *
 * @see docs/amiga-activity-charts-implementation-plan.md §3
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

const K2_ACT_SNAPSHOT_SERIES_METRICS = [
    'GamesPlayed',
    'GoalsScored',
    'NumberOfPlayers',
    'TournamentsFinalized',
    'DistinctHostCountries',
    'WcGamesPlayed',
    'DistinctOpponentPairs',
];

$metric = isset($_GET['metric']) ? trim((string) $_GET['metric']) : '';
if (!in_array($metric, K2_ACT_SNAPSHOT_SERIES_METRICS, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_metric']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_community_stats_lib.php';

include __DIR__ . '/../../config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

try {
    $ctx = amiga_snapshot_context_from_request($con);
    $cutoffChrono = null;
    $cutoffInfo = null;
    if ($ctx->isActive()) {
        $cutoffEntry = $ctx->cutoff();
        if ($cutoffEntry !== null) {
            $cutoffChrono = (float) $cutoffEntry['chrono'];
            $cutoffInfo = ['label' => (string) $cutoffEntry['label']];
        }
    }

    $rows = amiga_community_snapshot_series($con, $metric, $cutoffChrono);
    $points = [];
    foreach ($rows as $row) {
        $points[] = [
            't' => $row['t'],
            'date' => $row['date'],
            'name' => $row['name'],
            'host' => $row['host'],
            'value' => $row['value'] === null ? null : (int) round($row['value']),
        ];
    }

    echo json_encode([
        'metric' => $metric,
        'points' => $points,
        'cutoff' => $cutoffInfo,
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
} finally {
    $con->close();
}