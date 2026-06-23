<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php $k2RankedCloak = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>

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
$query = 'SELECT id, Name, Rating, NumberGames, DifferentOpponents, DifferentVictims, DoubleDigitsVictims, CleanSheetsVictims, MostGoalsConcededVictims, BiggestLossVictims, DifferentCulprits, DoubleDigitsCulprits, CleanSheetsCulprits, MostGoalsScoredCulprits, BiggestWinCulprits FROM playertable WHERE ' . k2_lb_player_where_sql() . ' ORDER BY DifferentOpponents DESC, rating DESC';
$result = k2_query_or_public_error($con, $query, 'ranked5 leaderboard'); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'victims';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table ranked-table-pending" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="4" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">#</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">ELO rating</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_opponents(), ENT_QUOTES, 'UTF-8'); ?>">Opponents</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_victims(), ENT_QUOTES, 'UTF-8'); ?>">Victims</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Double Digit victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_dd_victims(), ENT_QUOTES, 'UTF-8'); ?>">DD Victims</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Clean Sheet victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_cs_victims(), ENT_QUOTES, 'UTF-8'); ?>">CS Victims</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Most Goals Conceded victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_mgc_victims(), ENT_QUOTES, 'UTF-8'); ?>">MGC Victims</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Biggest Loss victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_bl_victims(), ENT_QUOTES, 'UTF-8'); ?>">BL Victims</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_culprits(), ENT_QUOTES, 'UTF-8'); ?>">Culprits</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Double Digit culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_dd_culprits(), ENT_QUOTES, 'UTF-8'); ?>">DD Culprits</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Clean Sheet culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_cs_culprits(), ENT_QUOTES, 'UTF-8'); ?>">CS Culprits</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Most Goals Scored culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_mgs_culprits(), ENT_QUOTES, 'UTF-8'); ?>">MGS Culprits</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Biggest Win culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_bw_culprits(), ENT_QUOTES, 'UTF-8'); ?>">BW Culprits</th>
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
        <td><?php echo k2_fmt_int($row[2]); ?></td>
        <td><?php echo k2_fmt_games_played($row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[4], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[5], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[6], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[7], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[8], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[9], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[10], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[11], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[12], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[13], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[14], $row[3]); ?></td>
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
