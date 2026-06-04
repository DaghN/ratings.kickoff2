<?php
/**
 * Thin dispatcher — routes CMD= to ops modules. No ladder business logic in this file.
 *
 *   php site/public_html/ops/dispatch.php CMD=ProcessCompletedGame game_id=57216 target=staging-work
 *   php site/public_html/ops/dispatch.php CMD=FinalizeLeagueDue target=staging-work
 *   php site/public_html/ops/dispatch.php CMD=FinalizeUtcDay target=staging-work as_of=2026-06-04T00:00:01Z
 *   php site/public_html/ops/dispatch.php CMD=Help
 *
 * Design: docs/coordination/ops-dispatch.md · platform: docs/ladder-ops-platform.md §6.5
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/includes/ops_dispatch.php';

k2_ops_require_cli();

$parsed = k2_ops_parse_dispatch_argv($argv);
$exitCode = k2_ops_dispatch_run($parsed['cmd'], $parsed['params'], $parsed['dry_run']);

exit($exitCode);
