<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_all.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_hub_table.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$k2AmigaGamesHubView = 'all';
$k2AmigaGamesPageTitle = 'Games — All games';
$k2AmigaGamesEnqueueTableJs = false;

$state = amiga_realm_games_all_request_state();
$limit = AMIGA_REALM_GAMES_ALL_PAGE_SIZE;

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

$totalMatches = amiga_realm_games_all_count($con, $ctx);
$offset = $state['offset'];
if ($offset >= $totalMatches && $totalMatches > 0) {
    $offset = 0;
    $state['offset'] = 0;
}

$games = amiga_realm_games_all_fetch_page($con, $state, $ctx, $limit);

$hubCounts = amiga_games_hub_status_counts($con, $ctx);
$k2AmigaGamesHubTotal = $hubCounts['total'];
$k2AmigaGamesRecentCount = $hubCounts['recent'];

mysqli_close($con);

$shownCount = count($games);
$firstShown = $totalMatches > 0 ? $offset + 1 : 0;
$lastShown = $offset + $shownCount;
$pagerBase = amiga_realm_games_all_query_params($state, false);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_shell_start.inc.php';
?>
	<div class="k2-realm-games-all">
		<div class="k2-player-games-status k2-realm-games-all__status" data-k2-carry-scroll>
			<div class="k2-realm-games-all__status-range">
				<span class="k2-realm-games-all__status-text">
					Showing <?php echo (int) $firstShown; ?>–<?php echo (int) $lastShown; ?> of <span class="k2-link-star"><?php echo number_format($totalMatches); ?></span> rated games.
				</span>
				<nav class="k2-player-games-day-steps k2-realm-games-all__status-nav" aria-label="Page">
					<?php if ($offset > 0) { ?>
					<a class="k2-player-games-day-step k2-player-games-day-step--prev" href="<?php echo amiga_realm_games_all_h(amiga_realm_games_all_build_url($pagerBase + ['offset' => max(0, $offset - $limit)])); ?>" aria-label="Previous page">
						<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
					</a>
					<?php } else { ?>
					<span class="k2-player-games-day-step k2-player-games-day-step--prev is-disabled" aria-disabled="true" aria-label="Previous page">
						<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
					</span>
					<?php } ?>
					<?php if ($offset + $limit < $totalMatches) { ?>
					<a class="k2-player-games-day-step k2-player-games-day-step--next" href="<?php echo amiga_realm_games_all_h(amiga_realm_games_all_build_url($pagerBase + ['offset' => $offset + $limit])); ?>" aria-label="Next page">
						<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
					</a>
					<?php } else { ?>
					<span class="k2-player-games-day-step k2-player-games-day-step--next is-disabled" aria-disabled="true" aria-label="Next page">
						<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
					</span>
					<?php } ?>
				</nav>
			</div>
		</div>

		<?php amiga_realm_games_hub_render_table($games, [
            'server_state' => $state,
            'empty_message' => 'No rated games yet.',
        ]); ?>
	</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_shell_end.inc.php'; ?>
