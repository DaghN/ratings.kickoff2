<?php
/**
 * Verify work DB after prepare + run_ops_sim (local gate before Steve full simul).
 *
 *   php site/public_html/ops/run_verify_ops_sim.php --target local-work
 *
 * Exit 0 = no failures (warnings allowed). Exit 1 = at least one fail severity.
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/includes/ops_bootstrap.php';
require_once __DIR__ . '/modules/verify_ops_sim.php';

k2_ops_require_cli();

$targetName = 'local-work';
for ($i = 1, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--target' && isset($argv[$i + 1])) {
        $targetName = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--target=')) {
        $targetName = substr($argv[$i], 9);
    }
}

$target = k2_ops_load_work_target($targetName);
k2_ops_assert_mutate_work_target($target);

$con = k2_ops_connect_work($target);
try {
    k2_ops_log('verify_ops_sim database=' . $target->workDatabase);
    $checks = k2_ops_verify_sim_complete($con);
    $failures = 0;
    $warnings = 0;
    foreach ($checks as $c) {
        $tag = $c['ok'] ? 'PASS' : ($c['severity'] === 'warn' ? 'WARN' : 'FAIL');
        if ($tag === 'FAIL') {
            ++$failures;
        }
        if ($tag === 'WARN') {
            ++$warnings;
        }
        k2_ops_log("[{$tag}] {$c['label']}: {$c['detail']}");
    }
    k2_ops_log("summary failures={$failures} warnings={$warnings}");
    exit(k2_ops_verify_exit_code($checks));
} finally {
    $con->close();
}
