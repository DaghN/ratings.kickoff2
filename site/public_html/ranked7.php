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
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";

	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';
$query = 'SELECT id, Name, Rating, NumberGames, NumberWins, NumberDraws, NumberLosses, WinRatio, DrawRatio, LossRatio, AverageOpponentRating FROM playertable WHERE ' . k2_lb_player_where_sql() . ' ORDER BY rating DESC';
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'results';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table ranked-pages-table ranked-table-pending" data-k2-table="sortable" data-k2-autorank="true" data-k2-default-sort="2" data-k2-default-direction="desc">

<thead>
    <tr style="text-align:right;">
        <th data-k2-sort="number">Rank</th>
        <th style="text-align:left;" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">ELO rating</th>
        <th data-k2-sort="number">&nbsp;&nbsp;Games</th>
        <th data-k2-sort="number">&nbsp;&nbsp;&nbsp;&nbsp;Wins</th>
        <th data-k2-sort="number">Draws</th>
        <th data-k2-sort="number">Losses</th>
        <th data-k2-sort="number">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Win Ratio</th>
        <th data-k2-sort="number">Draw Ratio</th>
        <th data-k2-sort="number">Loss Ratio</th>
        <th data-k2-sort="number">Opponent Avg.</th>
    </tr>
</thead>


<tbody class="black">
	<?php
    $rank = "1";
    while ($row = mysqli_fetch_row($result))
    { 
	?>
    
    <tr style="text-align:right;">
        <td><?php echo $rank ?></td>
        <td style="text-align:left;"><a href="individual1.php?id=<?php echo $row[0] ?>"><?php echo $row[1] ?></a></td>
        <td><?php echo round($row[2]) ?></td>
        <td><?php echo $row[3] ?></td>
        <td><?php if ($row[4]!=0) {echo "<span class='blue'>"; echo $row[4]; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php echo $row[5] ?></td>
        <td><?php if ($row[6]!=0) {echo "<span class='red'>"; echo $row[6]; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php if ($row[7]!=0) {echo "<span class='blue'>";} echo number_format(100*$row[7], 1); echo "%"; ?></td>
        <td><?php echo number_format(100*$row[8], 1); echo "%"; ?></td>
        <td><?php if ($row[9]!=0) {echo "<span class='red'>";} echo number_format(100*$row[9], 1); echo "%"; ?></td>
        <td><?php echo round($row[10]) ?></td>
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
