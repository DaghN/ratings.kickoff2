<?php
/**
 * @deprecated Use site/public_html/ops/run_finalize_league.php instead.
 *
 * Thin delegate for legacy docs / muscle memory. Maps flags to ops verbs on --target local-dev (ko2unity_db).
 *
 *   php scripts/finalize_league_periods.php              → finalize-due
 *   php scripts/finalize_league_periods.php --full-rebuild → rebuild-all
 *   php scripts/finalize_league_periods.php --rebuild-aggregates → rebuild-aggregates
 */
declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$opsScript = $repoRoot . '/site/public_html/ops/run_finalize_league.php';

if (!is_file($opsScript)) {
    fwrite(STDERR, "Missing ops runner: {$opsScript}\n");
    exit(1);
}

$verb = 'finalize-due';
if (in_array('--full-rebuild', $argv, true)) {
    $verb = 'rebuild-all';
} elseif (in_array('--rebuild-aggregates', $argv, true)) {
    $verb = 'rebuild-aggregates';
}

fwrite(STDERR, "Note: delegating to ops/run_finalize_league.php {$verb} --target local-dev\n");

$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($opsScript)
    . ' ' . escapeshellarg($verb) . ' --target local-dev';

passthru($cmd, $exitCode);
exit((int) $exitCode);
