<?php
/**
 * Prod-shaped simul — prepare first, then this (Mode C).
 *
 *   php site/public_html/ops/run_ops_sim.php run --target staging-work
 *   php site/public_html/ops/run_ops_sim.php run --target staging-work --until-game-id 74800
 *   php site/public_html/ops/run_ops_sim.php run --target staging-work --stop-at 2017-07-10T00:10:00Z
 *
 * @see docs/coordination/ops-simul-runbook.md
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/includes/ops_bootstrap.php';
require_once __DIR__ . '/includes/ops_sim.php';
require_once __DIR__ . '/modules/timeline_sim.php';
require_once __DIR__ . '/modules/finalize_league_period.php';

k2_ops_require_cli();

$verb = $argv[1] ?? '';
if ($verb !== 'run') {
    fwrite(
        STDERR,
        "Usage: php run_ops_sim.php run --target <profile> [--until-game-id G | --stop-at ISO-UTC] [--start-at ISO] [--dry-run]\n"
        . "  Full history: omit both --until-game-id and --stop-at (uses latest game + buffer).\n"
        . "  See docs/coordination/ops-simul-runbook.md\n"
    );
    exit(1);
}

$targetName = 'local-work';
$untilGameId = null;
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
    } elseif ($argv[$i] === '--until-game-id' && isset($argv[$i + 1])) {
        $untilGameId = (int) $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--until-game-id=')) {
        $untilGameId = (int) substr($argv[$i], 16);
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

$target = k2_ops_load_work_target($targetName);
k2_ops_assert_mutate_work_target($target);

$con = k2_ops_connect_work($target);
try {
    if ($stopAtRaw !== null && $stopAtRaw !== '') {
        try {
            $stopAt = new DateTimeImmutable($stopAtRaw, new DateTimeZone('UTC'));
        } catch (Exception $e) {
            fwrite(stderr(), "Invalid --stop-at: {$stopAtRaw}\n");
            exit(1);
        }
    } elseif ($untilGameId !== null && $untilGameId > 0) {
        $stopAt = k2_ops_stop_at_after_game_id($con, $untilGameId);
        k2_ops_log('ops_sim until_game_id=' . $untilGameId . ' => stop_at=' . $stopAt->format('Y-m-d\TH:i:s\Z'));
    } else {
        $stopAt = k2_ops_default_sim_stop_at($con);
        k2_ops_log('ops_sim full_history stop_at=' . $stopAt->format('Y-m-d\TH:i:s\Z'));
    }
} finally {
    $con->close();
}

$startAt = null;
if ($startAtRaw !== null && $startAtRaw !== '') {
    try {
        $startAt = new DateTimeImmutable($startAtRaw, new DateTimeZone('UTC'));
    } catch (Exception $e) {
        fwrite(stderr(), "Invalid --start-at: {$startAtRaw}\n");
        exit(1);
    }
}

k2_ops_log(
    '[ops_sim] prod-shaped simul (post-game + midnight steps). '
    . 'Prepare must have run first (incl. lobby milestones).'
);

$t0 = microtime(true);
$con = k2_ops_connect_work($target);
try {
    $result = k2_ops_timeline_sim_run(
        $con,
        $stopAt,
        $startAt,
        $dryRun,
        ($untilGameId !== null && $untilGameId > 0) ? $untilGameId : null
    );
    k2_ops_log('games_processed=' . $result['processed']);
    k2_ops_log(
        'last_game id=' . ($result['last_game_id'] ?? 'none')
        . ' Date=' . ($result['last_game_date'] ?? 'none')
    );
    k2_ops_log(
        'utc_day_ticks=' . $result['finalize_runs']
        . ' league_periods_finalized=' . $result['instances_finalized']
    );
    if (!$dryRun) {
        k2_ops_log_league_aggregate_counts($con);
    }
} finally {
    $con->close();
}

$ms = round((microtime(true) - $t0) * 1000);
k2_ops_log("ops_sim done in {$ms} ms");
exit(0);
