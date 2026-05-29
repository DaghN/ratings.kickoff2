<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php $k2RankedCloak = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'leaderboards';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";

	$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_play_streaks.php';
$con->query("SET time_zone = '+00:00'");
$query = 'SELECT p.`id`, p.`Name`, p.`Rating`, p.`NumberGames`, '
    . 'p.`LongestWinningStreak`, p.`LongestNonLossStreak`, p.`LongestDrawingStreak`, '
    . 'p.`LongestNonDrawStreak`, p.`LongestLosingStreak`, p.`LongestNonWinStreak`, '
    . 'COALESCE(psd.`best_streak`, 0) AS `play_streak_days`, '
    . 'COALESCE(psw.`best_streak`, 0) AS `play_streak_weeks` '
    . 'FROM `playertable` p '
    . 'LEFT JOIN `player_play_streaks` psd ON psd.`player_id` = p.`id` AND psd.`streak_type` = \'day\' '
    . 'LEFT JOIN `player_play_streaks` psw ON psw.`player_id` = p.`id` AND psw.`streak_type` = \'week\' '
    . 'WHERE ' . k2_lb_player_where_sql_for_alias('p') . ' '
    . 'ORDER BY p.`LongestWinningStreak` DESC, p.`Rating` DESC';
$result = k2_query_or_public_error($con, $query, 'ranked4 leaderboard'); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'streaks';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default ranked-pages-table ranked-table-pending" data-k2-table="sortable" data-k2-autorank="true" data-k2-default-sort="4" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">#</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number" data-k2-help="Current Elo rating.">ELO rating</th>
        <th class="k2-table-cell--pad-left-sm" data-k2-sort="number">Games</th>
        <th data-k2-sort="number" data-k2-help="Longest winning streak ever.">LWS</th>
        <th data-k2-sort="number" data-k2-help="Longest no-loss streak ever.">LNLS</th>
        <th data-k2-sort="number" data-k2-help="Longest drawing streak ever.">LDS</th>
        <th data-k2-sort="number" data-k2-help="Longest no-draw streak ever.">LNDS</th>
        <th data-k2-sort="number" data-k2-help="Longest losing streak ever.">LLS</th>
        <th data-k2-sort="number" data-k2-help="Longest no-win streak ever.">LNWS</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_play_streak_help_day(), ENT_QUOTES, 'UTF-8'); ?>">Days</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_play_streak_help_week(), ENT_QUOTES, 'UTF-8'); ?>">Weeks</th>
    </tr>
</thead>


<tbody class="black">
	<?php
    $rank = "1";
    while ($row = mysqli_fetch_row($result))
    {  
    ?>
    
    <tr>
        <td><?php echo $rank ?></td>
        <td class="k2-table-cell--left"><?php echo k2_player_link($row[0], $row[1]); ?></td>
        <td><?php echo round($row[2]) ?></td>
        <td><?php echo $row[3] ?></td>
        <td><?php if ($row[4] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[4]; echo "</span>";} ?></td>
        <td><?php if ($row[5] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[5]; echo "</span>";} ?></td>
        <td><?php if ($row[6] == 0) {echo "-";} else {echo $row[6];} ?></td>
        <td><?php if ($row[7] == 0) {echo "-";} else {echo $row[7];} ?></td>
        <td><?php if ($row[8] == 0) {echo "-";} else {echo "<span class='red'>"; echo $row[8]; echo "</span>";} ?></td>
        <td><?php if ($row[9] == 0) {echo "-";} else {echo "<span class='red'>"; echo $row[9]; echo "</span>";} ?></td>
        <td><?php if ($row[10] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[10]; echo "</span>";} ?></td>
        <td><?php if ($row[11] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[11]; echo "</span>";} ?></td>
    </tr> 
    
    <?php
	$rank++; 
    }  
    ?> 
</tbody>

</table>

</div><!-- .k2-table-wrap -->

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav_end.php"; ?>

<br />
LWS = Longest Winning Streak ever<br />
LNLS = Longest No Losses Streak ever<br />
LDS = Longest Drawing Streak ever<br />
LNDS = Longest No Draws Streak ever<br />
LLS = Longest Losing Streak ever<br />
LNWS = Longest No Wins Streak ever<br />
Days = longest run of consecutive UTC days with at least one rated game<br />
Weeks = longest run of consecutive UTC weeks (Mon–Sun) with at least one rated game




</div><!-- .k2-page-nav -->
</body>
</html>
