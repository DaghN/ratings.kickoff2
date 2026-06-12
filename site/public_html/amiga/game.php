<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga rated game</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_rated_game_row.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$row = $id > 0 ? amiga_rated_game_load($con, $id) : null;
?>

<div class="k2-table-wrap">

<?php if ($row === null) { ?>
<p>Game not found.</p>
<?php } else { ?>
<table class="k2-table k2-table--numeric-default k2-table--calm-stats k2-table--single-game ranked-pages-table">

<thead>
	<tr>
        <th class="k2-table-cell--left">ID</th>
        <th class="k2-table-cell--left k2-table-cell--pad-left-xs" data-k2-help="Synthetic event date (tournament day + order within event).">Date</th>
        <th class="k2-table-cell--left">Team A</th>
        <th data-k2-tooltip-label="Goals A" data-k2-help="Goals scored by Team A."></th>
        <th data-k2-tooltip-label="Goals B" data-k2-help="Goals scored by Team B."></th>
        <th class="k2-table-cell--left">Team B</th>
        <th class="k2-table-cell--left" data-k2-help="Offline tournament or event.">Tournament</th>
        <th class="k2-table-cell--left" data-k2-help="Bracket phase when recorded (group, final, etc.).">Phase</th>
        <th class="k2-table-cell--pad-left-md" data-k2-tooltip-label="Goal difference" data-k2-help="Absolute goal margin in the game. A 7-4 result has Diff 3.">Diff</th>
        <th data-k2-tooltip-label="Goal sum" data-k2-help="Total goals scored by both players. A 7-4 result has Sum 11.">Sum</th>
        <th class="k2-table-cell--left k2-table-cell--pad-left-lg" data-k2-help="Game winner. Drawn games show Draw.">Winner</th>
        <th data-k2-help="Team A's Elo rating before this game.">Rating A</th>
        <th data-k2-help="Team B's Elo rating before this game.">Rating B</th>
        <th data-k2-tooltip-label="Elo difference" data-k2-help="Absolute pre-game Elo rating difference between the two players.">Diff</th>
        <th class="k2-table-cell--pad-right-xs" data-k2-tooltip-label="Favorite expected score" data-k2-help="Elo maps the rating difference to an expected score for the favorite.">Fav ES</th>
        <th class="k2-table-cell--left" data-k2-tooltip-label="Adjustment" data-k2-help="Rating points gained by the player who beat expectation. K = 32.">Adjustment</th>
        <th data-k2-tooltip-label="Adjustment lost" data-k2-help="Rating points lost by the other player."></th>
	</tr>
</thead>

<tbody class="black">
	<?php echo amiga_rated_game_row_html($row, ['id_mode' => 'plain'], $con); ?>
</tbody>

</table>
<?php } ?>

</div><!-- .k2-table-wrap -->

<?php mysqli_close($con); ?>

</div><!-- .k2-page-nav -->
</body>
</html>
