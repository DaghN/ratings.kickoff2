<?php
/**
 * Dev runner: league period finalize (no dispatch.php).
 *
 *   php site/public_html/ops/run_finalize_league.php finalize-due --target local-work
 *   php site/public_html/ops/run_finalize_league.php finalize-due --target local-work --as-of 2026-05-27T00:00:01Z
 *
 * Batch repair (REP-012/013) — local-dev / frozen dev only (refused on local-work / staging-work):
 *   php site/public_html/ops/run_finalize_league.php rebuild-all --target local-dev
 *   php site/public_html/ops/run_finalize_league.php rebuild-aggregates --target local-dev
 *
 * Work sign-off (ko2unity_work / kooldb1): league awards come from run_ops_sim.php (FinalizeUtcDay), not rebuild-all.
 * See docs/work-db-prepare.md §1.5.
 *
 * PER-003: finalize-due. See docs/leagues-rules-spec.md and docs/coordination/periodic-register.md.
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/includes/ops_bootstrap.php';
require_once __DIR__ . '/modules/finalize_league_period.php';

k2_ops_require_cli();

$verb = $argv[1] ?? '';
if ($verb === '' || str_starts_with($verb, '-')) {
    fwrite(stderr(), "Usage: php run_finalize_league.php <verb> [--target local-work|local-dev|staging-work] [--as-of ISO-8601-UTC]\n");
    fwrite(stderr(), "Verbs: finalize-due (PER-003), rebuild-all (REP-012), rebuild-aggregates (REP-013)\n");
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
    fwrite(stderr(), "Unknown verb {$verb}\n");
    exit(1);
}

$target = k2_ops_load_work_target($targetName);
k2_ops_reject_signoff_work_batch_repair($verb, $target);
$allowDevDb = ($targetName === 'local-dev');
if (!$allowDevDb) {
    k2_ops_assert_mutate_work_target($target);
} else {
    k2_ops_log('WARNING: mutating ko2unity_db via --target local-dev');
}

$asOf = k2_ops_parse_as_of($asOfRaw);
$t0 = microtime(true);

$con = k2_ops_connect_work($target, $allowDevDb);
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
