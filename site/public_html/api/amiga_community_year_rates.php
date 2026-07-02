<?php
/**
 * JSON derived year rates for the Amiga Activity charts (L3 lens).
 *
 * GET: rate (whitelist below), as (time travel cutoff).
 * Slice 1 ships games_per_tournament only; texture + WC rates land in
 * slices 3-4. Zero-denominator years return null values (skipped by Chart.js).
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

/** rate => [numerator metric, denominator metric] (realm year facts). */
const K2_ACT_YEAR_RATES = [
    'games_per_tournament' => ['games', 'tournaments'],
];

$rate = isset($_GET['rate']) ? strtolower(trim((string) $_GET['rate'])) : '';
if (!isset(K2_ACT_YEAR_RATES[$rate])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_rate']);
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
        echo json_encode(['rate' => $rate, 'years' => [], 'values' => [], 'reference' => null, 'overlay' => null, 'cutoff' => null]);
        exit;
    }

    [$numMetric, $denMetric] = K2_ACT_YEAR_RATES[$rate];
    $span = amiga_community_year_span_at_cutoff($con, $cutoffTid);
    $numFacts = amiga_community_year_facts_at_cutoff($con, $cutoffTid, 'realm', $numMetric);
    $denFacts = amiga_community_year_facts_at_cutoff($con, $cutoffTid, 'realm', $denMetric);
    $numByYear = $numFacts[AMIGA_COMMUNITY_REALM_SLICE_KEY] ?? [];
    $denByYear = $denFacts[AMIGA_COMMUNITY_REALM_SLICE_KEY] ?? [];

    $years = [];
    $values = [];
    if ($span !== null) {
        for ($year = $span[0]; $year <= $span[1]; $year++) {
            $years[] = $year;
            $den = $denByYear[$year] ?? 0.0;
            $values[] = $den > 0 ? round(($numByYear[$year] ?? 0.0) / $den, 4) : null;
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
        'rate' => $rate,
        'years' => $years,
        'values' => $values,
        'reference' => null,
        'overlay' => null,
        'cutoff' => $cutoffInfo,
    ]);
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'query_failed']);
} finally {
    $con->close();
}