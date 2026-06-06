<?php
/**
 * HTTP entry for ops dispatch — game server / remote callers without PHP CLI.
 *
 * Setup: ops/config/dispatch-http.ini.example → dispatch-http.ini (shared_key).
 *
 * Example:
 *   /dispatch_request.php?key=YOUR_KEY&CMD=ProcessCompletedGame&game_id=57216&target=staging-work
 *
 * Response: JSON { ok, exit, cmd, log } — same exit codes as php ops/dispatch.php.
 * Steve: ops/docs/steve-live-ops.md
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = __DIR__;

require_once __DIR__ . '/ops/includes/ops_dispatch_http.php';

$query = array_merge($_GET, $_POST);
k2_ops_dispatch_http_send(k2_ops_dispatch_http_handle($query));
