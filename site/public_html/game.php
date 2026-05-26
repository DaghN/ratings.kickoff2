<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (mysqli_connect_errno()) {
    die('Failed to connect to MySQL: ' . mysqli_connect_error());
}
$con->query("SET time_zone = '+00:00'");

$stmt = mysqli_prepare($con, 'SELECT * FROM ratedresults WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row = $result ? mysqli_fetch_assoc($result) : null;
mysqli_free_result($result);
mysqli_stmt_close($stmt);
mysqli_close($con);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';
?>

<div class="k2-table-wrap">

<?php if ($row === null) { ?>
<p>Game not found.</p>
<?php } else { ?>
<table class="k2-table k2-table--numeric-default">

<thead>
	<tr>
        <th class="k2-table-cell--left">ID</th>
        <th class="k2-table-cell--left k2-table-cell--pad-left-xs">Date</th>
        <th class="k2-table-cell--left">Team A</th>
        <th data-k2-tooltip-label="Goals A" data-k2-help="Goals scored by Team A."></th>
        <th data-k2-tooltip-label="Goals B" data-k2-help="Goals scored by Team B."></th>
        <th class="k2-table-cell--left">Team B</th>
        <th class="k2-table-cell--pad-left-md" data-k2-tooltip-label="Goal difference" data-k2-help="Absolute goal margin in the game. A 7-4 result has Diff 3.">Diff</th>
        <th data-k2-tooltip-label="Goal sum" data-k2-help="Total goals scored by both players. A 7-4 result has Sum 11.">Sum</th>
        <th class="k2-table-cell--left k2-table-cell--pad-left-lg" data-k2-help="Game winner. Drawn games show Draw.">Winner</th>
        <th data-k2-help="Team A's Elo rating before this game.">Rating A</th>
        <th data-k2-help="Team B's Elo rating before this game.">Rating B</th>
        <th data-k2-tooltip-label="Elo difference" data-k2-help="Absolute pre-game Elo rating difference between the two players. Larger gaps mean a stronger favorite.">Diff</th>
        <th class="k2-table-cell--pad-right-xs" data-k2-tooltip-label="Favorite expected score" data-k2-help="Elo maps the rating difference to an expected score for the favorite:&#10;&#10;ES = 1 / (1 + 10^(-diff/400))&#10;&#10;Examples:&#10;&#10;0 -> 0.50&#10;100 -> 0.64&#10;200 -> 0.76&#10;300 -> 0.85&#10;400 -> 0.91&#10;&#10;The actual score will be one of win = 1, draw = 0.5, loss = 0.">Fav ES</th>
        <th class="k2-table-cell--left" data-k2-tooltip-label="Adjustment" data-k2-help="The expected score and actual score are now used to calculate the rating change:&#10;&#10;Rating change = 32 * (actual score - expected score)&#10;&#10;Example:&#10;&#10;200 Elo difference -> expected score 0.76 ->&#10;&#10;A win would gain 7.7 rating points.&#10;A draw would lose 8.3 rating points.&#10;A loss would lose 24.3 rating points.&#10;&#10;A favorite's expected win gives a small rating gain; an underdog win beats expectation a lot and gains more. The two players win or lose the opposite amount.">Adjustment</th>
        <th data-k2-tooltip-label="Adjustment lost" data-k2-help="Rating points lost by the other player. The two players win or lose the opposite amount."></th>
	</tr>
</thead>

<tbody class="black">
	<?php echo k2_rated_game_row_html($row, ['id_mode' => 'plain']); ?>
</tbody>

</table>
<?php } ?>

</div><!-- .k2-table-wrap -->

</div><!-- .k2-page-nav -->
</body>
</html>
