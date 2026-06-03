<?php
/**
 * Dev runner: league period finalize / awards rebuild on work DB (no dispatch.php).
 *
 *   php site/public_html/ops/run_finalize_league.php finalize-due --target local-work
 *   php site/public_html/ops/run_finalize_league.php finalize-due --target local-work --as-of 2026-05-27T00:00:01Z
 *   php site/public_html/ops/run_finalize_league.php rebuild-all --target local-work
 *   php site/public_html/ops/run_finalize_league.php rebuild-aggregates --target local-work
 *
 * PER-003: finalize-due. REP-012/013: rebuild-all, rebuild-aggregates.
 * See docs/leagues-rules-spec.md and docs/coordination/periodic-register.md.
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/includes/ops_bootstrap.php';
require_once __DIR__ . '/modules/finalize_league_period.php';

k2_ops_require_cli();

$verb = $argv[1] ?? '';
if ($verb === '' || str_starts_with($verb, '-')) {
    fwrite(STDERR, "Usage: php run_finalize_league.php <verb> [--target local-work] [--as-of ISO-8601-UTC]\n");
    fwrite(STDERR, "Verbs: finalize-due (PER-003), rebuild-all (REP-012), rebuild-aggregates (REP-013)\n");
    exit(1);
}

$targetName = 'local-work';
$asOfRaw = null;

for ($i = 2, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--target' && isset($argv[$i + 1])) {
        $targetName = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--target=')) {
        $targetName = substr($argv[$i], 9);
    } elseif ($argv[$i] === '--as-of' && isset($argv[$i + 1])) {
        $asOfRaw = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--as-of=')) {
        $asOfRaw = substr($argv[$i], 8);
    }
}

$allowed = ['finalize-due', 'rebuild-all', 'rebuild-aggregates'];
if (!in_array($verb, $allowed, true)) {
    fwrite(STDERR, "Unknown verb {$verb}\n");
    exit(1);
}

$target = k2_ops_load_work_target($targetName);
k2_ops_assert_mutate_work_target($target);

$asOf = k2_ops_parse_as_of($asOfRaw);
$t0 = microtime(true);

$con = k2_ops_connect_work($target);
try {
    k2_ops_log(
        'PER-003 league ops profile=' . $target->profile
        . ' database=' . $target->workDatabase
        . ' verb=' . $verb
        . ($asOf !== null ? ' as_of=' . $asOf->format('Y-m-d\TH:i:s\Z') : '')
    );

    switch ($verb) {
        case 'finalize-due':
            $result = k2_ops_finalize_league_due_periods($con, $asOf);
            k2_ops_log('New instances finalized: ' . $result['finalized']);
            k2_ops_log('As-of: ' . $result['as_of']);
            if ($result['finalized'] > 0) {
                k2_ops_log_league_aggregate_counts($con);
            }
            break;
        case 'rebuild-all':
            $result = k2_ops_rebuild_all_league_awards($con, $asOf);
            k2_ops_log('Instances finalized: ' . $result['instances']);
            k2_ops_log('Award rows: ' . $result['awards']);
            k2_ops_log('As-of: ' . $result['as_of']);
            k2_ops_log_league_aggregate_counts($con);
            break;
        case 'rebuild-aggregates':
            k2_ops_rebuild_league_player_aggregates($con);
            k2_ops_log('REP-013: rebuilt player_league_totals + player_league_slice_totals');
            k2_ops_log_league_aggregate_counts($con);
            break;
    }
} finally {
    $con->close();
}

$ms = round((microtime(true) - $t0) * 1000);
k2_ops_log("Done in {$ms} ms");

exit(0);
