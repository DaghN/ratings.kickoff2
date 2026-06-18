<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_realm_games_all.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_realm_games_all_filters_ui.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_helpers.php';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$k2GamesHubView = 'all';
$k2GamesPageTitle = 'Games — All games';

$state = k2_realm_games_all_request_state();
$limit = K2_REALM_GAMES_ALL_PAGE_SIZE;

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (mysqli_connect_errno()) {
	die('Failed to connect to MySQL: ' . mysqli_connect_error());
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

k2_realm_games_all_sanitize_filters($con, $state);

$realmPlayers = k2_realm_games_all_fetch_players($con);
$opponentRows = $state['player'] > 0 ? k2_realm_games_all_opponent_rows($con, $state['player']) : [];
$gdRows = k2_realm_games_all_fetch_score_values($con, 'GoalDifference');
$sumRows = k2_realm_games_all_fetch_score_values($con, 'SumOfGoals');
$tsRows = k2_realm_games_all_fetch_score_values($con, 'GREATEST(GoalsA, GoalsB)');
$yearRows = k2_realm_games_all_fetch_years($con);

$totalMatches = k2_realm_games_all_count($con, $state);
$offset = $state['offset'];
if ($offset >= $totalMatches && $totalMatches > 0) {
	$offset = 0;
	$state['offset'] = 0;
}

$games = k2_realm_games_all_fetch_page($con, $state, $limit);

$hubCounts = k2_games_hub_status_counts($con);
$k2GamesHubArc = $hubCounts['arc'];
$k2GamesRecent14Count = $hubCounts['recent14'];

mysqli_close($con);

$shownCount = count($games);
$firstShown = $totalMatches > 0 ? $offset + 1 : 0;
$lastShown = $offset + $shownCount;
$pagerBase = k2_realm_games_all_pager_params($state);
$sortedColIndex = k2_rated_game_sort_col_index($state['sort']);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_shell_start.inc.php';
?>
	<div class="k2-realm-games-all">
		<?php k2_realm_games_all_render_filters($state, $realmPlayers, $opponentRows, $gdRows, $sumRows, $tsRows, $yearRows); ?>
		<div class="k2-player-games-status k2-realm-games-all__status" data-k2-carry-scroll>
			<div class="k2-realm-games-all__status-range">
				<span class="k2-realm-games-all__status-text">
					Showing <?php echo (int) $firstShown; ?>–<?php echo (int) $lastShown; ?> of <span class="k2-link-star"><?php echo number_format($totalMatches); ?></span> rated games.
				</span>
				<nav class="k2-player-games-day-steps k2-realm-games-all__status-nav" aria-label="Page">
					<?php if ($offset > 0) { ?>
					<a class="k2-player-games-day-step k2-player-games-day-step--prev" href="<?php echo k2_realm_games_all_h(k2_realm_games_all_build_url($pagerBase + ['offset' => max(0, $offset - $limit)])); ?>" aria-label="Previous page">
						<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
					</a>
					<?php } else { ?>
					<span class="k2-player-games-day-step k2-player-games-day-step--prev is-disabled" aria-disabled="true" aria-label="Previous page">
						<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
					</span>
					<?php } ?>
					<?php if ($offset + $limit < $totalMatches) { ?>
					<a class="k2-player-games-day-step k2-player-games-day-step--next" href="<?php echo k2_realm_games_all_h(k2_realm_games_all_build_url($pagerBase + ['offset' => $offset + $limit])); ?>" aria-label="Next page">
						<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
					</a>
					<?php } else { ?>
					<span class="k2-player-games-day-step k2-player-games-day-step--next is-disabled" aria-disabled="true" aria-label="Next page">
						<span class="k2-player-games-day-step__chevron" aria-hidden="true"></span>
					</span>
					<?php } ?>
				</nav>
			</div>
			<a class="k2-player-games-reset" href="<?php echo k2_realm_games_all_h(k2_realm_games_all_build_url([])); ?>">Reset filters</a>
		</div>

		<div class="k2-table-wrap" data-k2-scroll-mirror>

<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--realm-games-all ranked-pages-table">

<thead>
	<tr>
		<?php echo k2_realm_games_all_sort_header('id', 'ID', 'left', $state, 'Rated game ID. Opens the single-game detail page.'); ?>
		<?php echo k2_realm_games_all_sort_header('date', 'Date', 'left', $state, 'UTC date and time the rated game was played.', 'Date', 'k2-table-cell--pad-left-xs'); ?>
		<?php echo k2_realm_games_all_sort_header('team_a', 'Team A', 'left', $state, 'Player listed as Team A in the result row.'); ?>
		<?php echo k2_realm_games_all_sort_header('goals_a', 'A', 'right', $state, 'Goals scored by Team A.', 'Goals A'); ?>
		<?php echo k2_realm_games_all_sort_header('goals_b', 'B', 'right', $state, 'Goals scored by Team B.', 'Goals B'); ?>
		<?php echo k2_realm_games_all_sort_header('team_b', 'Team B', 'left', $state, 'Player listed as Team B in the result row.'); ?>
		<?php echo k2_realm_games_all_sort_header('gd', 'GD', 'right', $state, 'Absolute goal margin in the game. A 7-4 result has GD 3.', 'Goal difference', 'k2-table-cell--pad-left-md'); ?>
		<?php echo k2_realm_games_all_sort_header('sum', 'Sum', 'right', $state, 'Total goals scored by both players. A 7-4 result has Sum 11.', 'Goal sum'); ?>
		<?php echo k2_realm_games_all_sort_header(
			'top_score',
			'TS',
			'right',
			$state,
			'Top score — the most goals either player scored in this game (e.g. 10 in 10–2).',
			'Top score'
		); ?>
		<?php echo k2_realm_games_all_sort_header('winner', 'Winner', 'left', $state, 'Game winner. Drawn games show Draw.', 'Winner', 'k2-table-cell--pad-left-lg'); ?>
		<?php echo k2_realm_games_all_sort_header('rating_a', 'Rating A', 'right', $state, 'Team A\'s Elo rating before this game.'); ?>
		<?php echo k2_realm_games_all_sort_header('rating_b', 'Rating B', 'right', $state, 'Team B\'s Elo rating before this game.'); ?>
		<?php echo k2_realm_games_all_sort_header('elo_diff', 'Elo Diff', 'right', $state, 'Absolute pre-game Elo rating difference between the two players. Larger gaps mean a stronger favorite.'); ?>
		<?php echo k2_realm_games_all_sort_header(
			'fav_es',
			'Fav ES',
			'right',
			$state,
			"Elo maps the rating difference to an expected score for the favorite:\n\nES = 1 / (1 + 10^(-diff/400))\n\nExamples:\n\n0 -> 0.50\n100 -> 0.64\n200 -> 0.76\n300 -> 0.85\n400 -> 0.91\n\nThe actual score will be one of win = 1, draw = 0.5, loss = 0.",
			'Favorite expected score',
			'k2-table-cell--pad-right-xs'
		); ?>
		<?php echo k2_realm_games_all_sort_header(
			'adjustment',
			'Adjustment',
			'left',
			$state,
			"The expected score and actual score are now used to calculate the rating change:\n\nRating change = 32 * (actual score - expected score)\n\nExample:\n\n200 Elo difference -> expected score 0.76 ->\n\nA win would gain 7.7 rating points.\nA draw would lose 8.3 rating points.\nA loss would lose 24.3 rating points.\n\nA favorite's expected win gives a small rating gain; an underdog win beats expectation a lot and gains more. The two players win or lose the opposite amount.",
			'Adjustment'
		); ?>
		<th class="k2-table-cell--left"><span class="visually-hidden">Adjustment lost</span></th>
	</tr>
</thead>

<tbody class="black">
<?php if ($games === []) { ?>
	<tr>
		<td colspan="16" class="k2-games-day__empty k2-table-cell--left">No games match these filters.</td>
	</tr>
<?php } else { ?>
<?php foreach ($games as $row) { ?>
	<?php echo k2_rated_game_row_html($row, ['id_mode' => 'link', 'sorted_col_index' => $sortedColIndex]); ?>
<?php } ?>
<?php } ?>
</tbody>

</table>

		</div>
	</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_shell_end.inc.php'; ?>
