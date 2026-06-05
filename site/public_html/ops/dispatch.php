<?php
/**
 * Thin dispatcher — routes CMD= to ops modules. No ladder business logic in this file.
 *
 *   php site/public_html/ops/dispatch.php CMD=ProcessPlayerRegistered player_id=42 target=staging-work
 *   php site/public_html/ops/dispatch.php CMD=ProcessCompletedGame game_id=57216 target=staging-work
 *   php site/public_html/ops/dispatch.php CMD=FinalizeUtcDay target=staging-work
 *   php site/public_html/ops/dispatch.php CMD=Help
 *
 * Steve: ops/docs/steve-live-ops.md · Design: ops/docs/ops-dispatch.md
 * Registry: includes/ops_dispatch.php
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/includes/ops_dispatch.php';

k2_ops_require_cli();

$parsed = k2_ops_parse_dispatch_argv($argv);
$exitCode = k2_ops_dispatch_run($parsed['cmd'], $parsed['params'], $parsed['dry_run']);

exit($exitCode);
