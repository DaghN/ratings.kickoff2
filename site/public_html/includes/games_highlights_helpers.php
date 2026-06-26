<?php
/**
 * All-time spectacle boards among rated games (server3 Highlights).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';

const K2_GAMES_HIGHLIGHTS_LIMIT = 100;

/** Hash target: board filter + table (below Games hub chrome). */
const K2_GAMES_HIGHLIGHTS_ANCHOR = 'k2-games-highlights';

/** @var array<string, array{label: string, heading: string, default_sort_col: int}> */
const K2_GAMES_HIGHLIGHT_BOARDS = [
	// default_sort_col is the 0-based column index in the fixed full layout:
	// 0 # · 1 ID · 2 Date · 3 Team A · 4 A · 5 B · 6 Team B · 7 GD · 8 Sum · 9 TS ·
	// 10 Rating A · 11 Rating B · 12 Elo Diff · 13 Fav ES · 14 Adjustment · 15 Adjustment lost.
	'most_goals' => [
		'label' => 'Most goals',
		'heading' => 'Most total goals',
		'default_sort_col' => 8,
	],
	'biggest_draws' => [
		'label' => 'Biggest draws',
		'heading' => 'Biggest draws',
		'default_sort_col' => 8,
	],
	'biggest_wins' => [
		'label' => 'Biggest wins',
		'heading' => 'Biggest wins',
		'default_sort_col' => 7,
	],
	'top_score' => [
		'label' => 'Top score',
		'heading' => 'Top score',
		'default_sort_col' => 9,
	],
];

function k2_games_highlights_valid_board(string $board): string
{
	return isset(K2_GAMES_HIGHLIGHT_BOARDS[$board]) ? $board : 'most_goals';
}

/**
 * @param bool $scrollToAnchor true = append #k2-games-highlights (HoF / off-page entry).
 *                           false = carry-scroll pills on the highlights board nav (no hash fight).
 */
function k2_games_highlights_href(string $board, bool $scrollToAnchor = true): string
{
	$url = k2_route('games-highlights', [
		'board' => k2_games_highlights_valid_board($board),
	]);

	return $scrollToAnchor ? $url . '#' . K2_GAMES_HIGHLIGHTS_ANCHOR : $url;
}

/**
 * @return list<array<string, mixed>>
 */
function k2_games_highlights_fetch(mysqli $con, string $board, int $limit = K2_GAMES_HIGHLIGHTS_LIMIT): array
{
	$board = k2_games_highlights_valid_board($board);
	$limit = max(1, min(200, $limit));

	$select = 'SELECT `id`, `Date`, `idA`, `NameA`, `idB`, `NameB`, `GoalsA`, `GoalsB`, '
		. '`GoalDifference`, `SumOfGoals`, `ActualScore`, `RatingA`, `RatingB`, `RatingDifference`, '
		. '`ExpectedScoreA`, `ExpectedScoreB`, `AdjustmentA`, `AdjustmentB` '
		. 'FROM `ratedresults`';

	switch ($board) {
		case 'biggest_draws':
			$sql = $select . ' WHERE ABS(`ActualScore` - 0.5) < 0.001'
				. ' ORDER BY `SumOfGoals` DESC, `id` ASC LIMIT ' . (int) $limit;
			break;
		case 'top_score':
			$sql = $select . ' ORDER BY GREATEST(`GoalsA`, `GoalsB`) DESC, `SumOfGoals` DESC, `id` ASC LIMIT ' . (int) $limit;
			break;
		case 'biggest_wins':
			$sql = $select . ' WHERE ABS(`ActualScore` - 0.5) >= 0.001'
				. ' ORDER BY `GoalDifference` DESC, `id` ASC LIMIT ' . (int) $limit;
			break;
		case 'most_goals':
		default:
			$sql = $select . ' ORDER BY `SumOfGoals` DESC, `id` ASC LIMIT ' . (int) $limit;
			break;
	}

	$result = mysqli_query($con, $sql);
	if ($result === false) {
		return [];
	}

	$rows = [];
	while ($row = mysqli_fetch_assoc($result)) {
		$rows[] = $row;
	}
	mysqli_free_result($result);

	if ($rows !== []) {
		$nameMap = k2_player_display_names_for_rated_rows($con, $rows);
		$rows = k2_rated_games_apply_display_names($rows, $nameMap);
	}

	return $rows;
}

function k2_games_render_highlights_board_filter(string $activeBoard): void
{
	?>
<nav class="k2-games-highlights-board-filter" data-k2-carry-scroll aria-label="Highlight board">
	<div class="k2-chrome-tabs__bar k2-games-highlights-board-filter__bar">
<?php foreach (K2_GAMES_HIGHLIGHT_BOARDS as $boardId => $meta) {
	$isActive = $boardId === $activeBoard;
	?>
		<a href="<?php echo k2_rated_game_h(k2_games_highlights_href($boardId, false)); ?>"
			class="k2-chrome-tabs__tab<?php echo $isActive ? ' is-active' : ''; ?>"
			<?php echo $isActive ? ' aria-current="page"' : ''; ?>><?php echo k2_rated_game_h($meta['label']); ?></a>
<?php } ?>
	</div>
</nav>
	<?php
}

function k2_games_render_highlights_table(array $rows, string $board): void
{
	$board = k2_games_highlights_valid_board($board);
	$meta = K2_GAMES_HIGHLIGHT_BOARDS[$board];
	$defaultSort = (int) $meta['default_sort_col'];
	?>
<section class="k2-games-highlights" aria-labelledby="k2-games-highlights-heading">
	<h2 class="k2-panel-heading" id="k2-games-highlights-heading"><?php echo k2_rated_game_h($meta['heading']); ?></h2>
	<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-games-highlights-table" data-k2-table="sortable" data-k2-autorank="true"
	data-k2-default-sort="<?php echo $defaultSort; ?>" data-k2-default-direction="desc">
<thead>
	<tr>
		<th class="<?php echo k2_games_highlights_col_classes('rank'); ?>" data-k2-sort="number" data-k2-help="Rank in this board. Equal scores tie-break to lower game ID.">#</th>
		<th class="<?php echo k2_games_highlights_col_classes('id'); ?>" data-k2-sort="number" data-k2-help="Rated game ID. Opens the single-game detail page.">ID</th>
		<th class="<?php echo k2_games_highlights_col_classes('date', 'k2-table-cell--left k2-table-cell--pad-left-xs'); ?>" data-k2-sort="number">Date</th>
		<th class="<?php echo k2_games_highlights_col_classes('team-a', 'k2-table-cell--right'); ?>" data-k2-sort="text" data-k2-help="Player listed as Team A in the result row.">Team A</th>
		<th class="<?php echo k2_games_highlights_col_classes('goals-a'); ?>" data-k2-sort="number" data-k2-tooltip-label="Goals A" data-k2-help="Goals scored by Team A.">A</th>
		<th class="<?php echo k2_games_highlights_col_classes('goals-b', 'k2-table-cell--left'); ?>" data-k2-sort="number" data-k2-tooltip-label="Goals B" data-k2-help="Goals scored by Team B.">B</th>
		<th class="<?php echo k2_games_highlights_col_classes('team-b', 'k2-table-cell--left'); ?>" data-k2-sort="text" data-k2-help="Player listed as Team B in the result row.">Team B</th>
		<th class="<?php echo k2_games_highlights_col_classes('gd', 'k2-table-cell--pad-left-md'); ?>" data-k2-sort="number" data-k2-tooltip-label="Goal difference" data-k2-help="Absolute goal margin in the game. A 7-4 result has GD 3.">GD</th>
		<th class="<?php echo k2_games_highlights_col_classes('sum'); ?>" data-k2-sort="number" data-k2-tooltip-label="Goal sum" data-k2-help="Total goals scored by both players. A 7-4 result has Sum 11.">Sum</th>
		<th class="<?php echo k2_games_highlights_col_classes('ts'); ?>" data-k2-sort="number" data-k2-tooltip-label="Top score" data-k2-help="Top score — the most goals either player scored in this game (e.g. 10 in 10–2).">TS</th>
		<th class="<?php echo k2_games_highlights_col_classes('rating-a'); ?>" data-k2-sort="number" data-k2-help="Team A's Elo rating before this game.">Rating A</th>
		<th class="<?php echo k2_games_highlights_col_classes('rating-b'); ?>" data-k2-sort="number" data-k2-help="Team B's Elo rating before this game.">Rating B</th>
		<th class="<?php echo k2_games_highlights_col_classes('elo-diff'); ?>" data-k2-sort="number" data-k2-tooltip-label="Elo difference" data-k2-help="Absolute pre-game Elo rating difference between the two players. Larger gaps mean a stronger favorite.">Elo Diff</th>
		<th class="<?php echo k2_games_highlights_col_classes('fav-es', 'k2-table-cell--pad-right-xs'); ?>" data-k2-sort="number" data-k2-tooltip-label="Favorite expected score" data-k2-help="Elo maps the rating difference to an expected score for the favorite:&#10;&#10;ES = 1 / (1 + 10^(-diff/400))&#10;&#10;Examples:&#10;&#10;0 -> 0.50&#10;100 -> 0.64&#10;200 -> 0.76&#10;300 -> 0.85&#10;400 -> 0.91&#10;&#10;The actual score will be one of win = 1, draw = 0.5, loss = 0.">Fav ES</th>
		<th class="<?php echo k2_games_highlights_col_classes('adjustment', 'k2-table-cell--left'); ?>" data-k2-sort="number" data-k2-tooltip-label="Adjustment" data-k2-help="The expected score and actual score are used to calculate the rating change:&#10;&#10;Rating change = 32 * (actual score - expected score)&#10;&#10;A favorite's expected win gives a small rating gain; an underdog win beats expectation a lot and gains more. The two players win or lose the opposite amount.">Adjustment</th>
		<th class="<?php echo k2_games_highlights_col_classes('adjustment-lost', 'k2-table-cell--left'); ?>" data-k2-sort="number"><span class="visually-hidden">Adjustment lost</span></th>
	</tr>
</thead>
<tbody class="black">
<?php if ($rows === []) { ?>
	<tr>
		<td colspan="16" class="k2-games-day__empty k2-table-cell--left">No rated games match this board yet.</td>
	</tr>
<?php } else { ?>
<?php foreach ($rows as $row) { ?>
	<?php echo k2_rated_game_row_html($row, [
		'id_mode' => 'link',
		'variant' => 'compact',
		'show_ts_column' => true,
		'show_gd_column' => true,
		'show_sum_column' => true,
		'show_winner' => false,
		'highlight_winner_goal' => true,
		'team_a_align' => 'right',
	]); ?>
<?php } ?>
<?php } ?>
</tbody>
</table>
	</div>
</section>
	<?php
}
