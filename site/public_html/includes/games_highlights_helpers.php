<?php
/**
 * All-time spectacle boards among rated games (server3 Highlights).
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';

const K2_GAMES_HIGHLIGHTS_LIMIT = 100;

/** Hash target: board filter + table (below Games hub chrome). */
const K2_GAMES_HIGHLIGHTS_ANCHOR = 'k2-games-highlights';

/** @var array<string, array{label: string, heading: string, default_sort_col: int}> */
const K2_GAMES_HIGHLIGHT_BOARDS = [
	'most_goals' => [
		'label' => 'Most goals',
		'heading' => 'Most total goals',
		'default_sort_col' => 8,
	],
	'biggest_draws' => [
		'label' => 'Biggest draws',
		'heading' => 'Biggest draws',
		'default_sort_col' => 7,
	],
	'most_goals_one_side' => [
		'label' => 'One-side peak',
		'heading' => 'Most goals by one side',
		'default_sort_col' => 7,
	],
	'biggest_wins' => [
		'label' => 'Biggest wins',
		'heading' => 'Biggest wins',
		'default_sort_col' => 7,
	],
];

function k2_games_hub_valid_view(string $view): string
{
	return $view === 'highlights' ? 'highlights' : 'recent';
}

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
	$url = '/games.php?' . http_build_query([
		'view' => 'highlights',
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
		case 'most_goals_one_side':
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

/**
 * @param list<array<string, mixed>> $rows
 */
function k2_games_highlights_show_gd_column(string $board): bool
{
	$board = k2_games_highlights_valid_board($board);

	return $board !== 'biggest_draws' && $board !== 'most_goals_one_side';
}

function k2_games_highlights_show_sum_column(string $board): bool
{
	$board = k2_games_highlights_valid_board($board);

	return $board !== 'most_goals_one_side' && $board !== 'biggest_wins';
}

function k2_games_render_highlights_table(array $rows, string $board, bool $showPeakColumn): void
{
	$board = k2_games_highlights_valid_board($board);
	$meta = K2_GAMES_HIGHLIGHT_BOARDS[$board];
	$defaultSort = (int) $meta['default_sort_col'];
	$showGdColumn = k2_games_highlights_show_gd_column($board);
	$showSumColumn = k2_games_highlights_show_sum_column($board);
	$colspan = 8 + ($showGdColumn ? 1 : 0) + ($showSumColumn ? 1 : 0) + ($showPeakColumn ? 1 : 0);
	?>
<section class="k2-games-highlights" aria-labelledby="k2-games-highlights-heading">
	<h2 class="k2-panel-heading" id="k2-games-highlights-heading"><?php echo k2_rated_game_h($meta['heading']); ?></h2>
	<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-games-highlights-table" data-k2-table="sortable" data-k2-autorank="true"
	data-k2-default-sort="<?php echo $defaultSort; ?>" data-k2-default-direction="desc">
<thead>
	<tr>
		<th data-k2-sort="number" data-k2-help="Rank in this board. Equal scores tie-break to lower game ID.">#</th>
		<th data-k2-sort="number" data-k2-help="Rated game ID. Opens the single-game detail page.">ID</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-xs" data-k2-sort="number">Date</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Player listed as Team A in the result row.">Team A</th>
		<th data-k2-sort="number" data-k2-tooltip-label="Goals A" data-k2-help="Goals scored by Team A.">A</th>
		<th class="k2-table-cell--left" data-k2-sort="number" data-k2-tooltip-label="Goals B" data-k2-help="Goals scored by Team B.">B</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Player listed as Team B in the result row.">Team B</th>
<?php if ($showGdColumn) { ?>
		<th class="k2-table-cell--pad-left-md" data-k2-sort="number" data-k2-tooltip-label="Goal difference" data-k2-help="Absolute goal margin in the game.">GD</th>
<?php } ?>
<?php if ($showSumColumn) { ?>
		<th data-k2-sort="number" data-k2-tooltip-label="Goal sum" data-k2-help="Total goals scored by both players.">Sum</th>
<?php } ?>
<?php if ($showPeakColumn) { ?>
		<th data-k2-sort="number" data-k2-tooltip-label="Peak side" data-k2-help="Higher of Team A or Team B goals in this game.">Peak</th>
<?php } ?>
		<th class="k2-table-cell--left k2-table-cell--pad-left-lg" data-k2-sort="text" data-k2-help="Game winner. Drawn games show Draw.">Winner</th>
	</tr>
</thead>
<tbody class="black">
<?php if ($rows === []) { ?>
	<tr>
		<td colspan="<?php echo $colspan; ?>" class="k2-games-day__empty k2-table-cell--left">No rated games match this board yet.</td>
	</tr>
<?php } else { ?>
<?php foreach ($rows as $row) { ?>
	<?php echo k2_rated_game_row_html($row, [
		'id_mode' => 'link',
		'variant' => 'compact',
		'show_peak_column' => $showPeakColumn,
		'show_gd_column' => $showGdColumn,
		'show_sum_column' => $showSumColumn,
	]); ?>
<?php } ?>
<?php } ?>
</tbody>
</table>
	</div>
</section>
	<?php
}
