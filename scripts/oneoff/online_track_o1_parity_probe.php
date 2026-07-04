<?php
declare(strict_types=1);
/**
 * Online Track O1 parity — score-line facet bundle vs legacy 3-query waterfall.
 */
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../site/public_html') ?: '';
include __DIR__ . '/../../site/config/ko2unitydb_config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_realm_games_all.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_realm_games_filter_facets.php';

function online_track_o1_legacy_score_line_facets(mysqli $con, array $state): array
{
    return [
        'gd' => k2_realm_games_facet_numeric_counts($con, $state, 'gd', 'r.GoalDifference', 'v DESC'),
        'gs' => k2_realm_games_facet_numeric_counts($con, $state, 'gs', 'r.SumOfGoals', 'v ASC'),
        'ts' => k2_realm_games_facet_numeric_counts($con, $state, 'ts', 'GREATEST(r.GoalsA, r.GoalsB)', 'v ASC'),
    ];
}

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, "connect fail: {$con->connect_error}\n");
    exit(1);
}
$con->set_charset('utf8mb4');

$cases = [
    'default' => [],
    'player537' => ['player' => '537'],
    'year2021_in' => ['year' => '2021', 'year_mode' => 'in'],
    'year2021_not' => ['year' => '2021', 'year_mode' => 'not'],
    'gd+3' => ['gd' => '3'],
    'gs+5' => ['gs' => '5'],
    'ts+4' => ['ts' => '4'],
    'player537_opp433' => ['player' => '537', 'opponent' => '433'],
];

$fail = 0;
foreach ($cases as $label => $get) {
    $_GET = $get;
    $state = k2_realm_games_all_request_state();
    k2_realm_games_all_sanitize_filters($con, $state);
    $old = online_track_o1_legacy_score_line_facets($con, $state);
    $new = k2_realm_games_load_score_line_filter_facets($con, $state);
    $choicesOld = k2_realm_games_score_line_facet_choices($old, $state);
    $choicesNew = k2_realm_games_score_line_facet_choices($new, $state);
    $total = k2_realm_games_all_count($con, $state);
    $facetsOk = json_encode($old) === json_encode($new);
    $choicesOk = json_encode($choicesOld) === json_encode($choicesNew);
    $ok = $facetsOk && $choicesOk;
    if (!$ok) {
        $fail++;
    }
    echo $label . ': facets ' . ($facetsOk ? 'OK' : 'DIFF')
        . ' choices ' . ($choicesOk ? 'OK' : 'DIFF')
        . ' total=' . $total . "\n";
}

$con->close();
echo ($fail === 0 ? "ALL OK\n" : "FAIL {$fail}\n");
exit($fail === 0 ? 0 : 1);