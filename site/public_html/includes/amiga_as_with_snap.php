<?php
/**
 * TT Event ribbon — filter auto-snap (`as_with=`).
 *
 * Separate from tournament `id_with` / league `start_with` (WP6). Tournament and league
 * keep their own entry redirects; this module is TT-only.
 *
 * @see docs/with-player-stepper-policy.md §3.2
 */
declare(strict_types=1);

/** 302 to nearest eligible event when `as_with=` active and current event is off-filter. */
function amiga_as_with_apply_snap_redirect(): void
{
    if (headers_sent()) {
        return;
    }

    require_once __DIR__ . '/amiga_snapshot_context.php';
    require_once __DIR__ . '/amiga_rating_history_lib.php';

    $asRaw = amiga_snapshot_as_param_from_request();
    if ($asRaw === null) {
        return;
    }

    $parsed = amiga_snapshot_parse_as_param($asRaw);
    if ($parsed === null || ($parsed['wing'] ?? '') !== 'event') {
        return;
    }

    $configPath = __DIR__ . '/../../config/ko2amiga_config.php';
    if (!is_file($configPath)) {
        return;
    }

    include $configPath;
    if (!isset($dbhost, $username, $password, $database)) {
        return;
    }

    $port = isset($dbportnum) ? (int) $dbportnum : (int) ini_get('mysqli.default_port');
    $con = @new mysqli($dbhost, $username, $password, $database, $port);
    if ($con->connect_errno) {
        return;
    }
    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    require_once __DIR__ . '/amiga_participation_step_lib.php';

    $playerId = amiga_as_with_active_player_id($con);
    if ($playerId < 1) {
        $con->close();

        return;
    }

    $view = amiga_snapshot_resolve_as($con, $asRaw);
    if ($view === null || ($view['wing'] ?? '') !== 'event') {
        $con->close();

        return;
    }

    $currentKey = (string) $view['key'];
    $participatedSet = amiga_player_participated_event_key_set($con, $playerId);
    if (isset($participatedSet[$currentKey])) {
        $con->close();

        return;
    }

    $steps = k2_participation_step_keys($view['catalog'], $currentKey, $participatedSet);
    $targetKey = $steps['prev_key'] ?? $steps['next_key'];
    if ($targetKey === null || $targetKey === '' || $targetKey === $currentKey) {
        $con->close();

        return;
    }

    require_once __DIR__ . '/amiga_snapshot_url.php';

    $href = amiga_url_with_as_param(
        amiga_snapshot_request_path(),
        amiga_snapshot_format_as_param('event', $targetKey),
    );
    $con->close();

    header('Location: ' . $href, true, 302);
    exit;
}

/** Idempotent entry — safe from auto_prepend and page preamble. */
function amiga_as_with_snap_try_from_request(): void
{
    static $attempted = false;
    if ($attempted || PHP_SAPI === 'cli') {
        return;
    }
    $attempted = true;

    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === '' || !str_contains($script, '/amiga/')) {
        return;
    }
    if (str_contains($script, '/amiga/ops/') || str_contains($script, 'run_import_ko2amiga.php')) {
        return;
    }
    if (!isset($_GET['as_with'])) {
        return;
    }

    $as = trim((string) ($_GET['as'] ?? ''));
    if ($as === '') {
        return;
    }
    if (isset($_GET['wing'], $_GET['at']) && trim((string) $_GET['at']) !== '') {
        $wing = strtolower(trim((string) $_GET['wing']));
        if ($wing !== 'event') {
            return;
        }
    } elseif (!str_starts_with(strtolower($as), 'event:')) {
        return;
    }

    amiga_as_with_apply_snap_redirect();
}