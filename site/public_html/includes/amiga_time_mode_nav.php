<?php
/**
 * Amiga header segment — Present day | Time travel (beside realm switcher).
 *
 * Requires realm_switcher.php first ($k2CurrentRealm).
 *
 * @see docs/amiga-time-travel-policy.md §T14 / T19
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/amiga_time_travel_stamp.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_hub_nav_lib.php';

function amiga_time_mode_nav_should_show(): bool
{
    global $k2CurrentRealm;
    if (($k2CurrentRealm ?? '') !== 'amiga') {
        return false;
    }

    $path = amiga_snapshot_request_path();

    return !str_contains($path, '/amiga/ops/') && !str_contains($path, 'run_import_ko2amiga.php');
}

function amiga_time_mode_nav_time_travel_href(?string $path = null): ?string
{
    if (amiga_snapshot_time_travel_active_from_request()) {
        return amiga_url_with_context(amiga_hub_time_travel_entry_path());
    }

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
    $asParam = amiga_snapshot_latest_as_param($con);
    $con->close();
    if ($asParam === null) {
        return null;
    }

    return amiga_url_with_as_param(amiga_hub_time_travel_entry_path(), $asParam, amiga_time_travel_stamp_arrival_entry_query());
}

/** Present-day toggle — same page without lens when in time travel; News when already present. */
function amiga_time_mode_nav_present_href(): string
{
    if (amiga_snapshot_time_travel_active_from_request()) {
        return amiga_url_present(amiga_snapshot_request_path());
    }

    return amiga_hub_present_entry_path();
}

function amiga_time_mode_nav_time_travel_help_text(): string
{
    return 'WARNING! You will be taken back in time and see the world and its data as they were at that moment in time. Side effects may include outdated Elo, lost wins, lost bragging rights, missing holy shields, acute nostalgia, and an uncontrollable urge to rematch everyone from 2003.';
}

function amiga_time_mode_nav_render(): void
{
    if (!amiga_time_mode_nav_should_show()) {
        return;
    }

    $timeTravelActive = amiga_snapshot_time_travel_active_from_request();
    $presentHref = amiga_time_mode_nav_present_href();
    $timeTravelHref = amiga_time_mode_nav_time_travel_href();
    if ($timeTravelHref === null) {
        return;
    }

    $presentClass = !$timeTravelActive ? ' is-active' : '';
    $travelClass = $timeTravelActive ? ' is-active' : '';
    $presentAria = !$timeTravelActive ? ' aria-current="page"' : '';
    $travelAria = $timeTravelActive ? ' aria-current="page"' : '';
    $travelHelpAttrs = '';
    if (!$timeTravelActive) {
        $travelHelpAttrs = ' data-k2-help="' . k2_h(amiga_time_mode_nav_time_travel_help_text())
            . '" data-k2-tooltip-hide-title="1"';
    }
    ?>
<nav class="k2-realm-switch k2-amiga-time-mode" data-k2-carry-scroll aria-label="Amiga time mode">
	<div class="k2-realm-switch__track" role="group" aria-label="Present day or time travel">
		<a href="<?php echo k2_h($presentHref); ?>" class="k2-realm-switch__btn<?php echo $presentClass; ?>"<?php echo $presentAria; ?>>Present day</a>
		<a href="<?php echo k2_h($timeTravelHref); ?>" class="k2-realm-switch__btn<?php echo $travelClass; ?>"<?php echo $travelAria; ?><?php echo $travelHelpAttrs; ?>>Time travel</a>
	</div>
</nav>
    <?php
    if (!$timeTravelActive) {
        k2_table_js_enqueue();
    }
}
