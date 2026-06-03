<?php
/**
 * Dev runner: Mode C timeline simul (post-game + daily PER-003 step).
 *
 *   php site/public_html/ops/run_timeline_sim.php run --target local-work
 *     --stop-at 2017-07-10T00:10:00Z
 *
 * First experiment (June 9 2017 + ~1 month → Monday 00:10 UTC):
 *   --stop-at 2017-07-10T00:10:00Z
 *
 * @see docs/work-db-prepare.md §5.1 Mode C
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/includes/ops_bootstrap.php';
require_once __DIR__ . '/modules/timeline_sim.php';

k2_ops_require_cli();

$verb = $argv[1] ?? '';
if ($verb !== 'run') {
    fwrite(STDERR, "Usage: php run_timeline_sim.php run --target local-work --stop-at ISO-8601-UTC [--start-at ISO] [--dry-run]\n");
    fwrite(STDERR, "Example: --stop-at 2017-07-10T00:10:00Z  (Monday ~1 month after 2017-06-09)\n");
    exit(1);
}

$targetName = 'local-work';
$stopAtRaw = null;
$startAtRaw = null;
$dryRun = false;

for ($i = 2, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--dry-run') {
        $dryRun = true;
    } elseif ($argv[$i] === '--target' && isset($argv[$i + 1])) {
        $targetName = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--target=')) {
        $targetName = substr($argv[$i], 9);
    } elseif ($argv[$i] === '--stop-at' && isset($argv[$i + 1])) {
        $stopAtRaw = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--stop-at=')) {
        $stopAtRaw = substr($argv[$i], 10);
    } elseif ($argv[$i] === '--start-at' && isset($argv[$i + 1])) {
        $startAtRaw = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--start-at=')) {
        $startAtRaw = substr($argv[$i], 11);
    }
}

if ($stopAtRaw === null || $stopAtRaw === '') {
    fwrite(STDERR, "run requires --stop-at (e.g. 2017-07-10T00:10:00Z)\n");
    exit(1);
}

try {
    $stopAt = new DateTimeImmutable($stopAtRaw, new DateTimeZone('UTC'));
} catch (Exception $e) {
    fwrite(STDERR, "Invalid --stop-at: {$stopAtRaw}\n");
    exit(1);
}

$startAt = null;
if ($startAtRaw !== null && $startAtRaw !== '') {
    try {
        $startAt = new DateTimeImmutable($startAtRaw, new DateTimeZone('UTC'));
    } catch (Exception $e) {
        fwrite(STDERR, "Invalid --start-at: {$startAtRaw}\n");
        exit(1);
    }
}

$target = k2_ops_load_work_target($targetName);
k2_ops_assert_mutate_work_target($target);

$t0 = microtime(true);
$con = k2_ops_connect_work($target);
try {
    k2_ops_log(
        'timeline_sim profile=' . $target->profile
        . ' database=' . $target->workDatabase
        . ' stop_at=' . $stopAt->format('Y-m-d\TH:i:s\Z')
        . ($startAt !== null ? ' start_at=' . $startAt->format('Y-m-d\TH:i:s\Z') : '')
        . ($dryRun ? ' dry_run=true' : '')
    );
    $result = k2_ops_timeline_sim_run($con, $stopAt, $startAt, $dryRun);
    k2_ops_log('games_processed=' . $result['processed']);
    k2_ops_log(
        'last_game id=' . ($result['last_game_id'] ?? 'none')
        . ' Date=' . ($result['last_game_date'] ?? 'none')
    );
    k2_ops_log(
        'finalize_runs=' . $result['finalize_runs']
        . ' instances_finalized=' . $result['instances_finalized']
    );
    if (!$dryRun) {
        k2_ops_log_league_aggregate_counts($con);
    }
} finally {
    $con->close();
}

$ms = round((microtime(true) - $t0) * 1000);
k2_ops_log("Done in {$ms} ms");
exit(0);
