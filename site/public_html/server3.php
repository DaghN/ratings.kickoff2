<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'games';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_rated_game_row.php';

include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";

function k2_games_day_label(int $offset, int $timestamp): string
{
	if ($offset === 0) {
		return 'Today &middot; ' . date('M j, Y', $timestamp);
	}
	if ($offset === 1) {
		return 'Yesterday &middot; ' . date('M j, Y', $timestamp);
	}

	return date('M j, Y', $timestamp);
}

$gamesByDay = [];
for ($offset = 0; $offset < 7; $offset++) {
	$timestamp = strtotime('-' . $offset . ' day');
	$key = date('Y-m-d', $timestamp);
	$gamesByDay[$key] = [
		'label' => k2_games_day_label($offset, $timestamp),
		'rows' => [],
	];
}

//mysql_connect(localhost,$username,$password);
//@mysql_select_db($database) or die( "Unable to select database");
	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}

$query = "SELECT * FROM ratedresults WHERE `Date` >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND `Date` < DATE_ADD(CURDATE(), INTERVAL 1 DAY) ORDER BY `Date` DESC, id DESC";
$result = mysqli_query($con, $query) or die("SELECT Error: " . mysqli_error($con));

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

mysqli_close($con);
?>

<div class="k2-games-list">
<?php foreach ($gamesByDay as $day) { ?>
<div class="k2-games-day">
<h2 class="k2-panel-heading k2-games-day__heading"><?php echo $day['label']; ?></h2>
<div class="k2-table-wrap">

<table class="k2-table">

<thead>
	<tr style="text-align:right;">
		<th style="text-align:left;">ID</th>
        <th style="text-align:left;">&nbsp;Date</th>
        <th>Team A</th>
        <th></th>
        <th></th>
        <th style="text-align:left;">Team B</th>
        <th>&nbsp;&nbsp;&nbsp;Diff</th>
        <th>Sum</th>
        <th style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;Winner</th>
        <th>Rating A</th>
        <th>Rating B</th>
        <th>Rating Diff</th>
        <th>ES Winner</th>
        <th style="text-align:left;">Adjustment</th>
        <th></th>
	</tr>
</thead>

<tbody class="black">
<?php if ($day['rows'] === []) { ?>
	<tr style="text-align:right;">
		<td colspan="15" class="k2-games-day__empty" style="text-align:left;">No rated games on this day.</td>
	</tr>
<?php } else { ?>
<?php foreach ($day['rows'] as $row) { ?>
	<?php echo k2_rated_game_row_html($row, ['id_mode' => 'link']); ?>
<?php } ?>
<?php } ?>
</tbody>

</table>

</div><!-- .k2-table-wrap -->
</div><!-- .k2-games-day -->
<?php } ?>
</div><!-- .k2-games-list -->


</div><!-- .k2-page-nav -->
</body>
</html>




