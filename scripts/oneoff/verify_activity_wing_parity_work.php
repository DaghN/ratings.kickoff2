<?php
/**
 * Orthogonal parity on ko2unity_work after incremental ops simul slice.
 *
 *   php scripts/oneoff/verify_activity_wing_parity_work.php --target local-work
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$targetName = 'local-work';
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--target' && isset($argv[$i + 1])) {
        $targetName = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--target=')) {
        $targetName = substr($argv[$i], 9);
    }
}

$repoRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $repoRoot . '/site/public_html';

require_once $_SERVER['DOCUMENT_ROOT'] . '/ops/includes/ops_bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ops/includes/ops_verify_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/ops/modules/verify_activity_wing_parity.php';

$target = k2_ops_load_work_target($targetName);
$con = k2_ops_connect_work($target);

try {
    $checks = k2_ops_verify_activity_wing_parity($con);
    $failed = 0;
    foreach ($checks as $c) {
        $tag = $c['ok'] ? 'OK' : 'FAIL';
        echo "[{$tag}] {$c['label']}: {$c['detail']}\n";
        if ($c['severity'] === 'fail' && !$c['ok']) {
            ++$failed;
        }
    }
    echo $failed === 0 ? "PASS\n" : "FAIL count={$failed}\n";
    exit($failed === 0 ? 0 : 1);
} finally {
    $con->close();
}
