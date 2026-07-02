<?php
/**
 * JSON per-country cumulative series for Amiga Activity geography race lines.
 *
 * GET: slice (host_country | player_nationality), metric (games | goals | tournaments | active_players),
 * optional keys (CSV, max 9; default top 5 by all-time games at cutoff), as.
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

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_community_stats_lib.php';

const K2_ACT_SLICE_SERIES_SLICES = [
    'host_country' => ['games', 'goals', 'tournaments'],
    'player_nationality' => ['games', 'goals', 'active_players'],
];

$slice = isset($_GET['slice']) ? strtolower(trim((string) $_GET['slice'])) : '';
$metric = isset($_GET['metric']) ? strtolower(trim((string) $_GET['metric'])) : '';
$keysCsv = isset($_GET['keys']) ? trim((string) $_GET['keys']) : '';

if (!isset(K2_ACT_SLICE_SERIES_SLICES[$slice])) {
    http_response_code(400);
    echo json_encode(['error' => 'slice_not_supported']);
    exit;
}
if (!in_array($metric, K2_ACT_SLICE_SERIES_SLICES[$slice], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_metric']);
    exit;
}

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
    $cutoffChrono = null;
    $cutoffInfo = null;
    if ($ctx->isActive()) {
        $cutoffEntry = $ctx->cutoff();
        if ($cutoffEntry !== null) {
            $cutoffChrono = (float) $cutoffEntry['chrono'];
            $cutoffInfo = ['label' => (string) $cutoffEntry['label']];
        }
    }

    if ($cutoffTid === null) {
        echo json_encode([
            'slice' => $slice,
            'metric' => $metric,
            'series' => [],
            'available_keys' => [],
            'cutoff' => null,
        ]);
        exit;
    }

    $availableRanked = amiga_community_slice_keys_at_cutoff($con, $cutoffTid, $slice, 'games');
    $availableKeys = array_keys($availableRanked);

    if ($keysCsv !== '') {
        $resolvedKeys = amiga_community_geo_validate_keys(
            amiga_community_geo_parse_keys_csv($keysCsv, AMIGA_COMMUNITY_GEO_RACE_KEYS_MAX),
            $availableRanked,
            AMIGA_COMMUNITY_GEO_RACE_KEYS_MAX
        );
    } else {
        $resolvedKeys = [];
    }
    if ($resolvedKeys === []) {
        $resolvedKeys = amiga_community_geo_default_race_keys($availableKeys);
    }

    $series = amiga_community_slice_series($con, $slice, $metric, $resolvedKeys, $cutoffChrono);

    echo json_encode([
        'slice' => $slice,
        'metric' => $metric,
        'series' => $series,
        'available_keys' => $availableKeys,
        'cutoff' => $cutoffInfo,
    ], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
} finally {
    $con->close();
}