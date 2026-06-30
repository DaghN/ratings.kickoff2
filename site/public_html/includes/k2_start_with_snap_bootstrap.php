<?php
declare(strict_types=1);

/**
 * Online league — `start_with=` filter snap before page HTML.
 * Include as the first line of league.php (before DOCTYPE).
 *
 * @see docs/with-player-stepper-policy.md SS3.3
 */

if (PHP_SAPI === 'cli' || headers_sent()) {
    return;
}

if (!isset($_GET['start_with'])) {
    return;
}

$docRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
if ($docRoot === '' || !is_file($docRoot . '/../config/ko2unitydb_config.php')) {
    return;
}

require_once $docRoot . '/includes/k2_safety.php';
require_once $docRoot . '/includes/k2_league_period_page.php';
require_once $docRoot . '/includes/k2_league_period_with_player.php';

$request = k2_league_period_parse_request();
if ($request === null) {
    return;
}

include $docRoot . '/../config/ko2unitydb_config.php';

$con = @new mysqli($dbhost, $username, $password, $database, (int) ($dbportnum ?? ini_get('mysqli.default_port')));
if ($con->connect_errno) {
    return;
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

$clock = k2_status_server_clock($con);
k2_league_period_apply_start_with_snap_redirect(
    $con,
    $request['cup'],
    $request['period'],
    $request['start'],
    $clock['now'],
);
$con->close();