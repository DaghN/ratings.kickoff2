<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php $k2RankedCloak = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="js/elolist.js" ></script>
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

//mysql_connect(localhost,$username,$password);
//@mysql_select_db($database) or die( "Unable to select database");
	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';
$query = 'SELECT id, Name, Rating, NumberGames, GoalsFor, GoalsAgainst, AverageGoalsFor, AverageGoalsAgainst, GoalRatio, MostGoalsScored, MostGoalsConceded, BiggestWinDifference, BiggestLossDifference, BiggestDrawSum, BiggestSumOfGoals, NumberDraws FROM playertable WHERE ' . k2_lb_player_where_sql() . ' ORDER BY rating DESC';
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'goals';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table ranked-pages-table ranked-table-pending table-autosort table-autofilter table-autorank table-stripeclass:alternate table-autostripe table-rowshade-alternate table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

<thead>
    <tr style="text-align:right;">
        <th class="table-sortable:numeric">Rank</th>
        <th style="text-align:left;" class="table-sortable:ignorecase">Player</th>
        <th class="table-sortable:numeric">ELO rating</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;Games</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;GF</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;GA</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Avg.</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;Avg.</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Ratio</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;Most S</th>
        <th class="table-sortable:numeric">Most C</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;BW Diff</th>
        <th class="table-sortable:numeric">&nbsp;BL Diff</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;BD</th>
        <th class="table-sortable:numeric">BG Sum</th>
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
        <td><?php if ($row[5]!=0) {echo "<span class='red'>"; echo $row[5]; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php if ($row[6]!=0) {echo "<span class='blue'>";} echo number_format($row[6], 2) ?></td>
        <td><?php if ($row[7]!=0) {echo "<span class='red'>";} echo number_format($row[7], 2) ?></td>
        <td><?php
        	if ($row[8] === null || $row[8] == -1) 
				{echo "-";}
			else 
				{if ($row[8]>=1) {echo "<span class='blue'>";} else {echo "<span class='red'>";} echo number_format($row[8], 2); echo "</span>";}
		?></td>
        <td><?php if ($row[9]!=0) {echo "<span class='blue'>"; echo $row[9]; echo "</span>"; } else {echo "-";} ?></td>
       	<td><?php if ($row[10]!=0) {echo "<span class='red'>"; echo $row[10]; echo "</span>"; } else {echo "-";} ?></td>
        <td><?php if ($row[11]!=0) {echo "<span class='blue'>"; echo $row[11]; echo "</span>"; } else {echo "-";} ?></td>
       	<td><?php if ($row[12]!=0) {echo "<span class='red'>"; echo $row[12]; echo "</span>"; } else {echo "-";} ?></td>
        <td><?php if ($row[15]!=0) {echo $row[13]/2; echo "-"; echo $row[13]/2;} else {echo "-";} ?></td>
        <td><?php echo $row[14] ?></td>
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
Most S = Most Scored, ie. most goals scored by the player in one game<br />
Most C = Most Conceded<br />
<br />
BW Diff = Biggest Win Difference, ie. the player's highest winning margin ever<br />
BL Diff = Biggest Loss Difference<br />
<br />
BD = Biggest Draw<br />
BG Sum = Biggest Goal Sum, ie. the most goal rich game the player has participated in<br />




</div><!-- .k2-page-nav -->
</body>
</html>
