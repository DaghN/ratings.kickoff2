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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
$query = 'SELECT id, Name, Rating, NumberGames, PeakRating, LowestRating, AverageOpponentRating, HighestRatedVictim, LowestRatedCulprit FROM playertable WHERE ' . k2_lb_player_where_sql() . ' ORDER BY PeakRating DESC, rating DESC';
$result = k2_query_or_public_error($con, $query, 'ranked1 leaderboard'); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'rating';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table ranked-table-pending" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="4" data-k2-default-sort="4" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">#</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">ELO rating</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_peak(), ENT_QUOTES, 'UTF-8'); ?>">Peak</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_nadir(), ENT_QUOTES, 'UTF-8'); ?>">Nadir</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_opponent_avg(), ENT_QUOTES, 'UTF-8'); ?>">Opponent Avg.</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_highest_victim(), ENT_QUOTES, 'UTF-8'); ?>">Highest Victim</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_lowest_culprit(), ENT_QUOTES, 'UTF-8'); ?>">Lowest Culprit</th>
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
        <td><?php echo $row[4] == 0 ? '-' : (int) round($row[4]); ?></td>
        <td><?php echo $row[5] == 5000 ? '-' : (int) round($row[5]); ?></td>
        <td><?php echo (int) round($row[6]); ?></td>
        <td><?php echo $row[7] == 0 ? '-' : (int) round($row[7]); ?></td>
        <td><?php echo $row[8] == 5000 ? '-' : (int) round($row[8]); ?></td>
    </tr> 
    
    <?php
	$rank++; 
    }  
    ?> 
</tbody>

</table> 

</div><!-- .k2-table-wrap -->

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav_end.php"; ?>

</div><!-- .k2-page-nav -->

</body>
</html>
