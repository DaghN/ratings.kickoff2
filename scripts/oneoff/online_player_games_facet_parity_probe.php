<?php
declare(strict_types=1);
/**
 * Parity oracle — online player games facet dedupe (Track O2).
 * Compares career-wide bundle vs per-dimension facet queries + filtered row counts.
 *
 * Usage: php scripts/oneoff/online_player_games_facet_parity_probe.php [playerId]
 */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../site/public_html') ?: '';
include __DIR__ . '/../../site/config/ko2unitydb_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_player_games_filter_facets.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_ratedresults_games_filters.php';

$playerId = isset($argv[1]) ? (int) $argv[1] : 537;
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

function assert_eq(string $label, mixed $a, mixed $b): void
{
    if ($a === $b) {
        echo "OK {$label}\n";
        return;
    }
    $ja = json_encode($a, JSON_THROW_ON_ERROR);
    $jb = json_encode($b, JSON_THROW_ON_ERROR);
    if ($ja === $jb) {
        echo "OK {$label}\n";
        return;
    }
    fwrite(STDERR, "FAIL {$label}\n  got: {$ja}\n  exp: {$jb}\n");
    exit(1);
}

function filtered_count(mysqli $con, int $playerId, array $ctx): int
{
    $types = '';
    $params = [];
    $where = k2_player_games_facet_where($playerId, $ctx, 'result', $types, $params);
    $rows = k2_player_games_facet_query_rows($con, 'SELECT COUNT(*) AS c FROM ratedresults r WHERE ' . $where, $types, $params);
    return (int) ($rows[0]['c'] ?? 0);
}

$careerCtx = k2_player_games_career_wide_filter_context();
$bundle = k2_player_games_career_wide_facets($con, $playerId);

$playerIdSql = (int) $playerId;
$heroGoalsFor = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA ELSE r.GoalsB END";
$heroGoalsAgainst = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsB ELSE r.GoalsA END";
$heroGd = "CASE WHEN r.idA = $playerIdSql THEN r.GoalsA - r.GoalsB ELSE r.GoalsB - r.GoalsA END";

assert_eq('result counts', $bundle['result'], k2_player_games_facet_result_counts($con, $playerId, $careerCtx));

$oppIdsBundle = array_map(static fn(array $r): int => (int) $r['opponent_id'], $bundle['opponent']);
$oppRows = k2_player_games_facet_opponent_rows($con, $playerId, $careerCtx);
$oppIdsDirect = array_map(static fn(array $r): int => (int) $r['opponent_id'], $oppRows);
sort($oppIdsBundle);
sort($oppIdsDirect);
assert_eq('opponent id set', $oppIdsBundle, $oppIdsDirect);

assert_eq('gf counts', $bundle['gf'], k2_player_games_facet_numeric_counts($con, $playerId, $careerCtx, 'gf', $heroGoalsFor, 'v ASC'));
assert_eq('ga counts', $bundle['ga'], k2_player_games_facet_numeric_counts($con, $playerId, $careerCtx, 'ga', $heroGoalsAgainst, 'v ASC'));
assert_eq('gs counts', $bundle['gs'], k2_player_games_facet_numeric_counts($con, $playerId, $careerCtx, 'gs', 'r.SumOfGoals', 'v ASC'));
assert_eq('gd counts', $bundle['gd'], k2_player_games_facet_numeric_counts($con, $playerId, $careerCtx, 'gd', $heroGd, 'v DESC'));

assert_eq('single-pass score line', k2_player_games_facet_score_line_counts_single_pass($con, $playerId, $careerCtx), [
    'gf' => $bundle['gf'],
    'ga' => $bundle['ga'],
    'gs' => $bundle['gs'],
    'gd' => $bundle['gd'],
]);

$filterFacets = k2_player_games_load_filter_facets($con, $playerId, $careerCtx);
$filterChoices = k2_player_games_facet_listbox_choices($con, $filterFacets, $careerCtx);
assert_eq('load uses career bundle', $filterFacets, $bundle);
echo 'listbox facets: result=' . count($filterChoices['result']) . ' opponent=' . count($filterChoices['opponent'])
    . ' gf=' . count($filterChoices['gf']) . ' ga=' . count($filterChoices['ga'])
    . ' gs=' . count($filterChoices['gs']) . ' gd=' . count($filterChoices['gd']) . "\n";

$cases = [
    ['all', 0, -1, -1, -1, null],
    ['win', 0, -1, -1, -1, null],
    ['loss', 433, -1, -1, -1, null],
    ['all', 0, 3, -1, -1, null],
    ['all', 0, -1, -1, 5, null],
    ['draw', 0, -1, -1, -1, 0],
];
foreach ($cases as [$result, $opp, $gf, $ga, $gs, $gd]) {
    $ctx = k2_player_games_filter_context($result, $opp, $gf, $ga, $gs, $gd, '', '', '', 0, 0);
    $facets = k2_player_games_load_filter_facets($con, $playerId, $ctx);
    $choices = k2_player_games_facet_listbox_choices($con, $facets, $ctx);
    $count = filtered_count($con, $playerId, $ctx);
    $label = "{$result}/opp={$opp}/gf={$gf}/ga={$ga}/gs={$gs}/gd=" . ($gd === null ? 'null' : (string) $gd);
    echo "case {$label}: count={$count} choices=" . count($choices['result']) . '/' . count($choices['opponent']) . "\n";
}

assert_eq('career total', filtered_count($con, $playerId, $careerCtx), (int) ($bundle['result']['win'] + $bundle['result']['draw'] + $bundle['result']['loss']));

$con->close();
echo "PARITY OK player {$playerId}\n";