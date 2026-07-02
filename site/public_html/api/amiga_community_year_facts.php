<?php
/**
 * JSON calendar-year facts for the Amiga Activity charts (year bars).
 *
 * GET: slice (realm | host_country | player_nationality | world_cup), metric,
 * optional keys (CSV slice keys, max 9, validated), as (time travel cutoff).
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

const K2_ACT_WORLD_CUP_YEAR_METRICS = [
    'games',
    'goals',
    'active_players',
    'distinct_nationalities',
];

const K2_ACT_HOST_COUNTRY_YEAR_METRICS = [
    'games',
    'goals',
    'tournaments',
];

const K2_ACT_PLAYER_NATIONALITY_YEAR_METRICS = [
    'games',
    'goals',
    'active_players',
    'player_debuts',
];

$slice = isset($_GET['slice']) ? strtolower(trim((string) $_GET['slice'])) : 'realm';
$metric = isset($_GET['metric']) ? strtolower(trim((string) $_GET['metric'])) : '';
$keysCsv = isset($_GET['keys']) ? trim((string) $_GET['keys']) : '';

$sliceConfig = match ($slice) {
    'realm' => ['key' => AMIGA_COMMUNITY_REALM_SLICE_KEY, 'metrics' => K2_ACT_REALM_YEAR_METRICS, 'dimensional' => false],
    'world_cup' => ['key' => AMIGA_COMMUNITY_WORLD_CUP_SLICE_KEY, 'metrics' => K2_ACT_WORLD_CUP_YEAR_METRICS, 'dimensional' => false],
    'host_country' => ['key' => null, 'metrics' => K2_ACT_HOST_COUNTRY_YEAR_METRICS, 'dimensional' => true],
    'player_nationality' => ['key' => null, 'metrics' => K2_ACT_PLAYER_NATIONALITY_YEAR_METRICS, 'dimensional' => true],
    default => null,
};

if ($sliceConfig === null) {
    http_response_code(400);
    echo json_encode(['error' => 'slice_not_supported']);
    exit;
}
if (!in_array($metric, $sliceConfig['metrics'], true)) {
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
    if ($cutoffTid === null) {
        $empty = [
            'slice' => $slice,
            'metric' => $metric,
            'years' => [],
            'series' => [],
            'cutoff' => null,
        ];
        if ($sliceConfig['dimensional']) {
            $empty['available_keys'] = [];
        }
        echo json_encode($empty);
        exit;
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

    if ($sliceConfig['dimensional']) {
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
            $selection = amiga_community_geo_page_selection($keysCsv !== '' ? $keysCsv : null, $availableRanked);
            $resolvedKeys = $selection['race_keys'];
        }

        $filled = amiga_community_year_series_filled_for_keys_at_cutoff(
            $con,
            $cutoffTid,
            $slice,
            $metric,
            $resolvedKeys,
            true
        );

        echo json_encode([
            'slice' => $slice,
            'metric' => $metric,
            'years' => $filled['years'],
            'series' => $filled['series'],
            'available_keys' => $availableKeys,
            'cutoff' => $cutoffInfo,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $filled = amiga_community_year_series_filled_at_cutoff($con, $cutoffTid, $slice, $metric, true);

    $payload = [
        'slice' => $slice,
        'metric' => $metric,
        'years' => $filled['years'],
        'series' => [['key' => $sliceConfig['key'], 'values' => $filled['values']]],
        'cutoff' => $cutoffInfo,
    ];
    if ($slice === 'realm' && $metric === 'distinct_nationalities') {
        $payload['nationality_active_by_year'] = amiga_community_nationality_active_by_year_at_cutoff($con, $cutoffTid);
    }
    if ($slice === 'realm' && in_array($metric, ['distinct_host_countries', 'tournaments'], true)) {
        $payload['host_tournaments_by_year'] = amiga_community_host_tournaments_by_year_at_cutoff($con, $cutoffTid);
    }
    if ($slice === 'world_cup' && in_array($metric, ['distinct_nationalities', 'active_players'], true)) {
        $payload['wc_nationality_active_by_year'] = amiga_community_wc_nationality_active_by_year_at_cutoff($con, $cutoffTid);
    }
    echo json_encode($payload);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
} finally {
    $con->close();
}