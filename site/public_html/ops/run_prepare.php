<?php
/**
 * Dev runner: work DB prepare (no dispatch.php).
 *
 *   php site/public_html/ops/run_prepare.php prepare --target local-work
 *   php site/public_html/ops/run_prepare.php prepare --target local-work --zero-only
 *   php site/public_html/ops/run_prepare.php parity --target local-work
 *   php site/public_html/ops/run_prepare.php refresh-work --target local-work
 *   php site/public_html/ops/run_prepare.php migrate-work --target local-work
 *   php site/public_html/ops/run_prepare.php seed-catalog --target local-work
 *   php site/public_html/ops/run_prepare.php seed-catalog --target local-dev
 *   php site/public_html/ops/run_prepare.php zero-derived --target local-work
 *   php site/public_html/ops/run_prepare.php seed-lobby --target local-work
 *
 * See docs/work-db-prepare.md and docs/ladder-ops-platform.md §6.6.
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/includes/ops_bootstrap.php';
require_once __DIR__ . '/modules/prepare_work.php';
require_once __DIR__ . '/modules/parity_check.php';

k2_ops_require_cli();

$verb = $argv[1] ?? '';
if ($verb === '' || str_starts_with($verb, '-')) {
    fwrite(STDERR, "Usage: php run_prepare.php <verb> [--target local-work|local-dev|staging-work] [--dry-run] [--zero-only]\n");
    fwrite(STDERR, "Verbs: prepare, refresh-work, migrate-work, seed-catalog, zero-derived, seed-lobby, parity\n");
    exit(1);
}

$targetName = 'local-work';
$dryRun = false;
$zeroOnly = false;
for ($i = 2, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--dry-run') {
        $dryRun = true;
    } elseif ($argv[$i] === '--zero-only') {
        $zeroOnly = true;
    } elseif ($argv[$i] === '--target' && isset($argv[$i + 1])) {
        $targetName = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--target=')) {
        $targetName = substr($argv[$i], 9);
    }
}

$target = k2_ops_load_work_target($targetName);

$allowed = [
    'prepare', 'refresh-work', 'migrate-work', 'seed-catalog', 'zero-derived', 'seed-lobby', 'parity',
];
if (!in_array($verb, $allowed, true)) {
    fwrite(STDERR, "Unknown verb {$verb}\n");
    exit(1);
}

switch ($verb) {
    case 'prepare':
        if ($zeroOnly) {
            k2_ops_prepare_fast($target, $dryRun);
        } else {
            k2_ops_prepare_full($target, $dryRun);
        }
        if (!$dryRun) {
            exit(k2_ops_print_parity_report(k2_ops_run_parity_checks($target)));
        }
        break;
    case 'refresh-work':
        k2_ops_refresh_work($target, $dryRun);
        break;
    case 'migrate-work':
        k2_ops_migrate_work($target, $dryRun);
        break;
    case 'seed-catalog':
        k2_ops_seed_milestone_definitions($target, $dryRun, $targetName === 'local-dev');
        break;
    case 'zero-derived':
        k2_ops_zero_derived($target, $dryRun);
        break;
    case 'seed-lobby':
        k2_ops_seed_lobby_milestones($target, $dryRun);
        break;
    case 'parity':
        exit(k2_ops_print_parity_report(k2_ops_run_parity_checks($target)));
}

exit(0);
