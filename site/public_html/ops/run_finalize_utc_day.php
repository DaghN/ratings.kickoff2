<?php
/**
 * Dev runner: one UTC day tick (same as CMD=FinalizeUtcDay).
 *
 *   php site/public_html/ops/run_finalize_utc_day.php --target local-work
 *   php site/public_html/ops/run_finalize_utc_day.php --target local-work --as-of 2026-06-04T00:00:01Z
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/includes/ops_bootstrap.php';
require_once __DIR__ . '/modules/finalize_league_period.php';
require_once __DIR__ . '/modules/finalize_utc_day.php';

k2_ops_require_cli();

$targetName = 'local-work';
$asOfRaw = null;
$closedUtcDay = null;
$dryRun = false;

for ($i = 1, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--dry-run') {
        $dryRun = true;
    } elseif ($argv[$i] === '--target' && isset($argv[$i + 1])) {
        $targetName = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--target=')) {
        $targetName = substr($argv[$i], 9);
    } elseif ($argv[$i] === '--as-of' && isset($argv[$i + 1])) {
        $asOfRaw = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--as-of=')) {
        $asOfRaw = substr($argv[$i], 8);
    } elseif ($argv[$i] === '--closed-utc-day' && isset($argv[$i + 1])) {
        $closedUtcDay = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--closed-utc-day=')) {
        $closedUtcDay = substr($argv[$i], 16);
    }
}

$asOf = k2_ops_parse_as_of($asOfRaw);
$target = k2_ops_load_work_target($targetName);
k2_ops_assert_mutate_work_target($target);

$con = k2_ops_connect_work($target);
try {
    $result = k2_ops_finalize_utc_day($con, $asOf, $dryRun, $closedUtcDay);
    k2_ops_log(
        'FinalizeUtcDay closed_utc_day=' . $result['closed_utc_day']
        . ' league_finalized=' . $result['league_finalized']
        . ' league_event_milestones=' . $result['league_event_milestones_inserted']
        . ' perfect_day=' . $result['perfect_day']
        . ' nightmare_day=' . $result['nightmare_day']
    );
} finally {
    $con->close();
}
