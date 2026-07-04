<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_all.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_all_filters_ui.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_filter_facets.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_games_hub_table.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$k2AmigaGamesHubView = 'all';
$k2AmigaGamesPageTitle = 'Games — All games';
$k2AmigaGamesEnqueueTableJs = true;

$state = amiga_realm_games_all_request_state();
$limit = AMIGA_REALM_GAMES_ALL_PAGE_SIZE;

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

amiga_realm_games_all_sanitize_filters($con, $state, $ctx);

$realmPlayers = amiga_realm_games_all_fetch_players($con);
$opponentRows = $state['player'] > 0
    ? amiga_realm_games_all_opponent_rows($con, $state['player'], $state, $ctx)
    : [];
$hostCountryRows = amiga_realm_games_facet_host_country_rows($con, $state, $ctx);
$hostCountryRows = amiga_realm_games_facet_inject_selected_host_country($hostCountryRows, (string) ($state['host'] ?? ''));
$hostCountryChoices = amiga_realm_games_host_country_choices($hostCountryRows);
$scoreLineFacets = amiga_realm_games_load_score_line_filter_facets($con, $state, $ctx);
$scoreLineChoices = amiga_realm_games_score_line_facet_choices($scoreLineFacets, $state);
$yearRows = amiga_realm_games_all_fetch_years($con, $ctx);

$totalMatches = amiga_realm_games_all_count($con, $state, $ctx);
$offset = $state['offset'];
if ($offset >= $totalMatches && $totalMatches > 0) {
    $offset = 0;
    $state['offset'] = 0;
}

$games = amiga_realm_games_all_fetch_page($con, $state, $ctx, $limit);

$hasActiveFilters = amiga_realm_games_all_has_active_filters($state);
$hubCounts = amiga_games_hub_status_counts($con, $ctx, $hasActiveFilters ? null : $totalMatches);
$k2AmigaGamesHubTotal = $hubCounts['total'];
$k2AmigaGamesRecentCount = $hubCounts['recent'];

mysqli_close($con);

$shownCount = count($games);
$firstShown = $totalMatches > 0 ? $offset + 1 : 0;
$lastShown = $offset + $shownCount;
$pagerBase = amiga_realm_games_all_query_params($state, false);
$emptyMessage = $hasActiveFilters ? 'No games match these filters.' : 'No rated games yet.';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_shell_start.inc.php';
?>
	<div class="k2-realm-games-all">
		<div id="matching-games" class="k2-player-games-day-anchor" tabindex="-1"></div>
		<?php amiga_realm_games_all_render_filters(
            $state,
            $realmPlayers,
            $opponentRows,
            $scoreLineChoices,
            $yearRows,
            $hostCountryChoices,
        ); ?>
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
			<?php if ($hasActiveFilters) { ?>
			<a class="k2-player-games-reset" href="<?php echo amiga_realm_games_all_h(amiga_realm_games_all_build_url([])); ?>">Reset filters</a>
			<?php } ?>
		</div>

		<?php amiga_realm_games_hub_render_table($games, [
            'server_state' => $state,
            'empty_message' => $emptyMessage,
        ]); ?>
	</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_games_hub_shell_end.inc.php'; ?>
