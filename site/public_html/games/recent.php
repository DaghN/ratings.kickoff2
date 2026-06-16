<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_helpers.php';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$k2GamesHubView = 'recent';
$k2GamesPageTitle = 'Games — Recent';

$gamesByDay = [];
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (mysqli_connect_errno()) {
	die('Failed to connect to MySQL: ' . mysqli_connect_error());
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

for ($offset = 0; $offset < 14; $offset++) {
	$timestamp = strtotime('-' . $offset . ' day');
	$key = date('Y-m-d', $timestamp);
	$gamesByDay[$key] = [
		'label' => k2_games_day_label($offset, $timestamp),
		'rows' => [],
	];
}

$query = 'SELECT * FROM `ratedresults` WHERE `Date` >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) '
	. 'AND `Date` < DATE_ADD(CURDATE(), INTERVAL 1 DAY) ORDER BY `Date` DESC, `id` DESC';
$result = mysqli_query($con, $query);
if ($result === false) {
	die('SELECT Error: ' . mysqli_error($con));
}

while ($row = mysqli_fetch_assoc($result)) {
	$timestamp = strtotime((string) ($row['Date'] ?? ''));
	if ($timestamp === false) {
		continue;
	}

	$key = date('Y-m-d', $timestamp);
	if (isset($gamesByDay[$key])) {
		$gamesByDay[$key]['rows'][] = $row;
	}
}
mysqli_free_result($result);

$hubCounts = k2_games_hub_status_counts($con);
$k2GamesHubArc = $hubCounts['arc'];
$k2GamesRecent14Count = $hubCounts['recent14'];

mysqli_close($con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_shell_start.inc.php';
?>
	<div class="k2-games-list">
<?php foreach ($gamesByDay as $day) { ?>
	<div class="k2-games-day">
		<h2 class="k2-panel-heading k2-games-day__heading"><?php echo $day['label']; ?></h2>
		<div class="k2-table-wrap" data-k2-scroll-mirror>

<table class="k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table" data-k2-table="sortable" data-k2-default-sort="0" data-k2-default-direction="desc">

<thead>
	<tr>
		<th class="k2-table-cell--left" data-k2-sort="number" data-k2-help="Rated game ID. Opens the single-game detail page.">ID</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-xs" data-k2-sort="number">Date</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Player listed as Team A in the result row.">Team A</th>
		<th data-k2-sort="number" data-k2-tooltip-label="Goals A" data-k2-help="Goals scored by Team A.">A</th>
		<th class="k2-table-cell--left" data-k2-sort="number" data-k2-tooltip-label="Goals B" data-k2-help="Goals scored by Team B.">B</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="Player listed as Team B in the result row.">Team B</th>
		<th class="k2-table-cell--pad-left-md" data-k2-sort="number" data-k2-tooltip-label="Goal difference" data-k2-help="Absolute goal margin in the game. A 7-4 result has GD 3.">GD</th>
		<th data-k2-sort="number" data-k2-tooltip-label="Goal sum" data-k2-help="Total goals scored by both players. A 7-4 result has Sum 11.">Sum</th>
		<th data-k2-sort="number" data-k2-tooltip-label="Top score" data-k2-help="Top score — the most goals either player scored in this game (e.g. 10 in 10–2).">TS</th>
		<th class="k2-table-cell--left k2-table-cell--pad-left-lg" data-k2-sort="text" data-k2-help="Game winner. Drawn games show Draw.">Winner</th>
		<th data-k2-sort="number" data-k2-help="Team A's Elo rating before this game.">Rating A</th>
		<th data-k2-sort="number" data-k2-help="Team B's Elo rating before this game.">Rating B</th>
		<th data-k2-sort="number" data-k2-help="Absolute pre-game Elo rating difference between the two players. Larger gaps mean a stronger favorite.">Elo Diff</th>
		<th class="k2-table-cell--pad-right-xs" data-k2-sort="number" data-k2-tooltip-label="Favorite expected score" data-k2-help="Elo maps the rating difference to an expected score for the favorite:&#10;&#10;ES = 1 / (1 + 10^(-diff/400))&#10;&#10;Examples:&#10;&#10;0 -> 0.50&#10;100 -> 0.64&#10;200 -> 0.76&#10;300 -> 0.85&#10;400 -> 0.91&#10;&#10;The actual score will be one of win = 1, draw = 0.5, loss = 0.">Fav ES</th>
		<th class="k2-table-cell--left" data-k2-sort="number" data-k2-tooltip-label="Adjustment" data-k2-help="The expected score and actual score are now used to calculate the rating change:&#10;&#10;Rating change = 32 * (actual score - expected score)&#10;&#10;Example:&#10;&#10;200 Elo difference -> expected score 0.76 ->&#10;&#10;A win would gain 7.7 rating points.&#10;A draw would lose 8.3 rating points.&#10;A loss would lose 24.3 rating points.&#10;&#10;A favorite's expected win gives a small rating gain; an underdog win beats expectation a lot and gains more. The two players win or lose the opposite amount.">Adjustment</th>
		<th class="k2-table-cell--left" data-k2-sort="number"><span class="visually-hidden">Adjustment lost</span></th>
	</tr>
</thead>

<tbody class="black">
<?php if ($day['rows'] === []) { ?>
	<tr>
		<td colspan="16" class="k2-games-day__empty k2-table-cell--left">No rated games on this day.</td>
	</tr>
<?php } else { ?>
<?php foreach ($day['rows'] as $row) { ?>
	<?php echo k2_rated_game_row_html($row, ['id_mode' => 'link', 'sorted_col_index' => 0]); ?>
<?php } ?>
<?php } ?>
</tbody>

</table>

		</div>
	</div>
<?php } ?>
	</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/games_hub_shell_end.inc.php'; ?>
