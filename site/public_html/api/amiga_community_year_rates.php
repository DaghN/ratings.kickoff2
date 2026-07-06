<?php
/**
 * JSON derived year rates for the Amiga Activity charts (L3 lens).
 *
 * GET: rate (whitelist below), as (time travel cutoff).
 * Texture rates include `reference` from headline at cutoff.
 * WC rates (slice 4): wc_share, wc_goals_per_game (+ realm overlay), wc_games_per_player.
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

/** rate => kind for derivation. */
const K2_ACT_YEAR_RATES = [
    'games_per_tournament' => 'realm_ratio',
    'goals_per_game' => 'realm_ratio_headline',
    'draw_rate' => 'realm_ratio_headline',
    'dd_rate' => 'realm_ratio_headline',
    'cs_rate' => 'realm_ratio_headline',
    'high_scoring_rate' => 'realm_ratio_headline',
    'low_scoring_rate' => 'realm_ratio_headline',
    'wc_share' => 'wc_share',
    'wc_goals_per_game' => 'wc_goals_per_game',
    'wc_games_per_player' => 'wc_games_per_player',
];

/** @var array<string, array{0: string, 1: string}> */
const K2_ACT_REALM_RATE_METRICS = [
    'games_per_tournament' => ['games', 'tournaments'],
    'goals_per_game' => ['goals', 'games'],
    'draw_rate' => ['draws', 'games'],
    'dd_rate' => ['double_digits', 'games'],
    'cs_rate' => ['clean_sheets', 'games'],
    'high_scoring_rate' => ['high_scoring_games', 'games'],
    'low_scoring_rate' => ['low_scoring_games', 'games'],
];

$rate = isset($_GET['rate']) ? strtolower(trim((string) $_GET['rate'])) : '';
if (!isset(K2_ACT_YEAR_RATES[$rate])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_rate']);
    exit;
}

include __DIR__ . '/../../config/ko2amiga_config.php';

$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
if ($con->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect_failed']);
    exit;
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

/**
 * @return list<float|null>
 */
function k2_act_year_rate_values(mysqli $con, int $cutoffTid, string $numSlice, string $numMetric, string $denSlice, string $denMetric): array
{
    $span = amiga_community_year_span_at_cutoff($con, $cutoffTid);
    if ($span === null) {
        return [];
    }

    $numFacts = amiga_community_year_facts_at_cutoff($con, $cutoffTid, $numSlice, $numMetric);
    $denFacts = amiga_community_year_facts_at_cutoff($con, $cutoffTid, $denSlice, $denMetric);
    $numKey = $numSlice === 'world_cup' ? AMIGA_COMMUNITY_WORLD_CUP_SLICE_KEY : AMIGA_COMMUNITY_REALM_SLICE_KEY;
    $denKey = $denSlice === 'world_cup' ? AMIGA_COMMUNITY_WORLD_CUP_SLICE_KEY : AMIGA_COMMUNITY_REALM_SLICE_KEY;
    $numByYear = $numFacts[$numKey] ?? [];
    $denByYear = $denFacts[$denKey] ?? [];

    $values = [];
    for ($year = $span[0]; $year <= $span[1]; $year++) {
        $den = $denByYear[$year] ?? 0.0;
        $values[] = $den > 0 ? round(($numByYear[$year] ?? 0.0) / $den, 4) : null;
    }

    return $values;
}

/**
 * Mean WC games per distinct active player in a calendar year.
 *
 * Each rated game counts once in `games` but adds one game to each of two players,
 * so participations = 2 × games (matches per-WC `avg_games_per_player` on the hub table).
 *
 * @return list<float|null>
 */
function k2_act_wc_games_per_player_year_values(mysqli $con, int $cutoffTid): array
{
    $span = amiga_community_year_span_at_cutoff($con, $cutoffTid);
    if ($span === null) {
        return [];
    }

    $gamesFacts = amiga_community_year_facts_at_cutoff($con, $cutoffTid, 'world_cup', 'games');
    $playersFacts = amiga_community_year_facts_at_cutoff($con, $cutoffTid, 'world_cup', 'active_players');
    $gamesByYear = $gamesFacts[AMIGA_COMMUNITY_WORLD_CUP_SLICE_KEY] ?? [];
    $playersByYear = $playersFacts[AMIGA_COMMUNITY_WORLD_CUP_SLICE_KEY] ?? [];

    $values = [];
    for ($year = $span[0]; $year <= $span[1]; $year++) {
        $players = $playersByYear[$year] ?? 0.0;
        $values[] = $players > 0
            ? round(2.0 * ($gamesByYear[$year] ?? 0.0) / $players, 4)
            : null;
    }

    return $values;
}

try {
    $ctx = amiga_snapshot_context_from_request($con);
    $cutoffTid = amiga_community_cutoff_tournament_id_for_read($con, $ctx);
    if ($cutoffTid === null) {
        echo json_encode(['rate' => $rate, 'years' => [], 'values' => [], 'reference' => null, 'overlay' => null, 'cutoff' => null]);
        exit;
    }

    $span = amiga_community_year_span_at_cutoff($con, $cutoffTid);
    $years = [];
    if ($span !== null) {
        for ($year = $span[0]; $year <= $span[1]; $year++) {
            $years[] = $year;
        }
    }

    $kind = K2_ACT_YEAR_RATES[$rate];
    $values = [];
    $denominatorByYear = null;
    $reference = null;
    $overlay = null;

    if ($kind === 'realm_ratio' || $kind === 'realm_ratio_headline') {
        [$numMetric, $denMetric] = K2_ACT_REALM_RATE_METRICS[$rate];
        $numFacts = amiga_community_year_facts_at_cutoff($con, $cutoffTid, 'realm', $numMetric);
        $denFacts = amiga_community_year_facts_at_cutoff($con, $cutoffTid, 'realm', $denMetric);
        $numByYear = $numFacts[AMIGA_COMMUNITY_REALM_SLICE_KEY] ?? [];
        $denByYear = $denFacts[AMIGA_COMMUNITY_REALM_SLICE_KEY] ?? [];
        if ($span !== null) {
            $denominatorByYear = [];
            for ($year = $span[0]; $year <= $span[1]; $year++) {
                $den = $denByYear[$year] ?? 0.0;
                $denominatorByYear[] = $den > 0 ? (int) round($den) : null;
                $values[] = $den > 0 ? round(($numByYear[$year] ?? 0.0) / $den, 4) : null;
            }
        }
        if ($kind === 'realm_ratio_headline') {
            $reference = amiga_community_year_rate_reference_at_cutoff($con, $cutoffTid, $rate);
        }
    } elseif ($kind === 'wc_share') {
        $realmGamesFacts = amiga_community_year_facts_at_cutoff($con, $cutoffTid, 'realm', 'games');
        $realmGamesByYear = $realmGamesFacts[AMIGA_COMMUNITY_REALM_SLICE_KEY] ?? [];
        $values = k2_act_year_rate_values($con, $cutoffTid, 'world_cup', 'games', 'realm', 'games');
        if ($span !== null) {
            $denominatorByYear = [];
            for ($year = $span[0]; $year <= $span[1]; $year++) {
                $den = $realmGamesByYear[$year] ?? 0.0;
                $denominatorByYear[] = $den > 0 ? (int) round($den) : null;
            }
        }
    } elseif ($kind === 'wc_goals_per_game') {
        $values = k2_act_year_rate_values($con, $cutoffTid, 'world_cup', 'goals', 'world_cup', 'games');
        $overlayValues = k2_act_year_rate_values($con, $cutoffTid, 'realm', 'goals', 'realm', 'games');
        $overlay = [
            'label' => 'Realm goals per game',
            'values' => $overlayValues,
        ];
    } elseif ($kind === 'wc_games_per_player') {
        $values = k2_act_wc_games_per_player_year_values($con, $cutoffTid);
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

    $payload = [
        'rate' => $rate,
        'years' => $years,
        'values' => $values,
        'denominator_by_year' => $denominatorByYear,
        'reference' => $reference,
        'overlay' => $overlay,
        'cutoff' => $cutoffInfo,
    ];
    if (str_starts_with($rate, 'wc_')) {
        $payload['wc_events_by_year'] = amiga_community_wc_events_by_year_at_cutoff($con, $cutoffTid);
    }
    echo json_encode($payload);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
} finally {
    $con->close();
}