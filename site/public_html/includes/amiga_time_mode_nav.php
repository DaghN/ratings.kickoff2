<?php
/**
 * Amiga header segment — Present day | Time travel (beside realm switcher).
 *
 * Requires realm_switcher.php first ($k2CurrentRealm).
 *
 * @see docs/amiga-time-travel-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_hub_nav_lib.php';
require_once __DIR__ . '/amiga_rating_history_lib.php';
require_once __DIR__ . '/amiga_player_snapshot_lib.php';

function amiga_time_mode_nav_should_show(): bool
{
    global $k2CurrentRealm;
    if (($k2CurrentRealm ?? '') !== 'amiga') {
        return false;
    }

    $path = amiga_snapshot_request_path();

    return !str_contains($path, '/amiga/ops/') && !str_contains($path, 'run_import_ko2amiga.php');
}

function amiga_time_mode_nav_time_travel_href(string $path): ?string
{
    $asParam = null;
    if (isset($_GET['as'])) {
        $as = trim((string) $_GET['as']);
        if ($as !== '' && amiga_snapshot_parse_as_param($as) !== null) {
            $asParam = $as;
        }
    }

    if ($asParam === null || $asParam === '') {
        $configPath = __DIR__ . '/../../config/ko2amiga_config.php';
        if (!is_file($configPath)) {
            return null;
        }
        include $configPath;
        if (!isset($dbhost, $username, $password, $database)) {
            return null;
        }
        $port = isset($dbportnum) ? (int) $dbportnum : ini_get('mysqli.default_port');
        $con = @new mysqli($dbhost, $username, $password, $database, (int) $port);
        if ($con->connect_errno) {
            return null;
        }
        $con->set_charset('utf8mb4');
        $asParam = null;
        if (amiga_player_wing_request_path($path)) {
            $playerId = amiga_player_wing_id_from_request();
            if ($playerId > 0) {
                $asParam = amiga_player_first_snapshot_as_param($con, $playerId);
            }
        }
        if ($asParam === null || $asParam === '') {
            $asParam = amiga_snapshot_latest_as_param($con);
        }
        $con->close();
        if ($asParam === null) {
            return null;
        }
    }

    $targetPath = amiga_time_mode_nav_time_travel_target_path($path);

    return amiga_url_with_as_param($targetPath, $asParam);
}

function amiga_time_mode_nav_render(): void
{
    if (!amiga_time_mode_nav_should_show()) {
        return;
    }

    $path = amiga_snapshot_request_path();
    $timeTravelActive = amiga_snapshot_time_travel_active_from_request();
    $presentHref = amiga_url_present($path);
    $timeTravelHref = amiga_time_mode_nav_time_travel_href($path);
    if ($timeTravelHref === null) {
        return;
    }

    $presentClass = !$timeTravelActive ? ' is-active' : '';
    $travelClass = $timeTravelActive ? ' is-active' : '';
    $presentAria = !$timeTravelActive ? ' aria-current="page"' : '';
    $travelAria = $timeTravelActive ? ' aria-current="page"' : '';
    ?>
<nav class="k2-realm-switch k2-amiga-time-mode" aria-label="Amiga time mode">
	<div class="k2-realm-switch__track" role="group" aria-label="Present day or time travel">
		<a href="<?php echo k2_h($presentHref); ?>" class="k2-realm-switch__btn<?php echo $presentClass; ?>"<?php echo $presentAria; ?>>Present day</a>
		<a href="<?php echo k2_h($timeTravelHref); ?>" class="k2-realm-switch__btn<?php echo $travelClass; ?>"<?php echo $travelAria; ?>>Time travel</a>
	</div>
</nav>
    <?php
}
