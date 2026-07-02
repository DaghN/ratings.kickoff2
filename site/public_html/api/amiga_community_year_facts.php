<?php
/**
 * JSON calendar-year facts for the Amiga Activity charts (year bars).
 *
 * GET: slice (realm only in slice 0), metric (whitelist below), as (time travel cutoff).
 * Years are zero-filled across the realm games span at the cutoff so every
 * year chart on the hub shares the same x-axis.
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

const K2_ACT_REALM_YEAR_METRICS = [
    'games',
    'goals',
    'active_players',
    'tournaments',
    'draws',
    'double_digits',
    'clean_sheets',
    'high_scoring_games',
    'distinct_pairs',
    'player_debuts',
    'distinct_host_countries',
    'distinct_nationalities',
];

$slice = isset($_GET['slice']) ? strtolower(trim((string) $_GET['slice'])) : 'realm';
$metric = isset($_GET['metric']) ? strtolower(trim((string) $_GET['metric'])) : '';

if ($slice !== 'realm') {
    http_response_code(400);
    echo json_encode(['error' => 'slice_not_supported_yet']);
    exit;
}
if (!in_array($metric, K2_ACT_REALM_YEAR_METRICS, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_metric']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_community_stats_lib.php';

include __DIR__ . '/../../config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

try {
    $ctx = amiga_snapshot_context_from_request($con);
    $cutoffTid = amiga_community_cutoff_tournament_id_for_read($con, $ctx);
    if ($cutoffTid === null) {
        echo json_encode(['slice' => $slice, 'metric' => $metric, 'years' => [], 'series' => [], 'cutoff' => null]);
        exit;
    }

    $span = amiga_community_year_span_at_cutoff($con, $cutoffTid);
    $facts = amiga_community_year_facts_at_cutoff($con, $cutoffTid, 'realm', $metric);
    $byYear = $facts[AMIGA_COMMUNITY_REALM_SLICE_KEY] ?? [];

    $years = [];
    $values = [];
    if ($span !== null) {
        for ($year = $span[0]; $year <= $span[1]; $year++) {
            $years[] = $year;
            $values[] = (int) round($byYear[$year] ?? 0.0);
        }
    }

    $cutoffInfo = null;
    if ($ctx->isActive()) {
        $cutoffEntry = $ctx->cutoff();
        if ($cutoffEntry !== null) {
            $cutoffInfo = [
                'label' => (string) $cutoffEntry['label'],
                'event_date' => (string) $cutoffEntry['event_date'],
                'partial_year' => (int) substr((string) $cutoffEntry['event_date'], 0, 4),
            ];
        }
    }

    echo json_encode([
        'slice' => $slice,
        'metric' => $metric,
        'years' => $years,
        'series' => [['key' => AMIGA_COMMUNITY_REALM_SLICE_KEY, 'values' => $values]],
        'cutoff' => $cutoffInfo,
    ]);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
} finally {
    $con->close();
}