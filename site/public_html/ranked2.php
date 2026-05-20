<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />
<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/ranked_table_cloak_head.php"; ?>
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

$query = "SELECT id, Name, Rating, PeakRating, NumberGames, GoalsFor, GoalsAgainst, AverageGoalsFor, AverageGoalsAgainst, GoalRatio, MostGoalsScored, MostGoalsConceded, LeastGoalsScored, LeastGoalsConceded, BiggestWinDifference, BiggestLossDifference, BiggestDrawSum, BiggestSumOfGoals, SmallestSumOfGoals, NumberDraws FROM playertable WHERE display=1 ORDER BY rating DESC";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'goals';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>

<div class="k2-table-wrap">

<table class="example ranked-pages-table ranked-table-pending table-autosort table-autofilter table-autorank table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:30 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

<thead>
    <tr style="text-align:right;">
        <th class="table-sortable:numeric">Rank</th>
        <th style="text-align:left;" class="table-sortable:ignorecase">Player</th>
        <th class="table-sortable:numeric">ELO rating</th>
        <th class="table-sortable:numeric">Peak</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;Games</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;GF</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;GA</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Avg.</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;Avg.</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Ratio</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;Most S</th>
        <th class="table-sortable:numeric">Most C</th>
        <th class="table-sortable:numeric">&nbsp;Least S</th>
        <th class="table-sortable:numeric">Least C</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;BW Diff</th>
        <th class="table-sortable:numeric">&nbsp;BL Diff</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;BD</th>
        <th class="table-sortable:numeric">BG Sum</th>
        <th class="table-sortable:numeric">SG Sum</th>
    </tr>
</thead>

<tfoot>
	<tr> 
        <td colspan="3" class="table-page:previous" style="cursor:pointer;">&lt;&lt; Previous</td> 
		<td colspan="14" style="text-align:center;">Page <span id="tablepage"></span>&nbsp;of <span id="tablepages"></span></td> 
		<td colspan="3" class="table-page:next" style="cursor:pointer; text-align:right;">Next &gt;&gt;</td> 
        
	</tr>
<!--
	<tr>
        <td colspan="8" style="text-align:center;"><span id="tablefiltercount"></span>&nbsp;out of <span id="tableallcount"></span>&nbsp;goals in filter</td> 	
    </tr>
-->    
</tfoot>

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
        <td><?php if ($row[3] == 0) {echo "-";} else {echo "<span class='blue'>"; echo round($row[3]); echo "</span>";} ?></td>
        <td><?php echo $row[4] ?></td>
        <td><?php if ($row[5]!=0) {echo "<span class='blue'>"; echo $row[5]; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php if ($row[6]!=0) {echo "<span class='red'>"; echo $row[6]; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php if ($row[7]!=0) {echo "<span class='blue'>";} echo number_format($row[7], 2) ?></td>
        <td><?php if ($row[8]!=0) {echo "<span class='red'>";} echo number_format($row[8], 2) ?></td>
        <td><?php
        	if ($row[9] == -1) 
				{echo "-";}
			else 
				{if ($row[9]>=1) {echo "<span class='blue'>";} else {echo "<span class='red'>";} echo number_format($row[9], 2);}
		?></td>
        <td><?php if ($row[10]!=0) {echo "<span class='blue'>"; echo $row[10]; echo "</span>"; } else {echo "-";} ?></td>
       	<td><?php if ($row[11]!=0) {echo "<span class='red'>"; echo $row[11]; echo "</span>"; } else {echo "-";} ?></td>
        <td><?php echo "<span class='blue'>"; echo $row[12]; echo "</span>"; ?></td>
       	<td><?php echo "<span class='red'>"; echo $row[13]; echo "</span>"; ?></td>
        <td><?php if ($row[14]!=0) {echo "<span class='blue'>"; echo $row[14]; echo "</span>"; } else {echo "-";} ?></td>
       	<td><?php if ($row[15]!=0) {echo "<span class='red'>"; echo $row[15]; echo "</span>"; } else {echo "-";} ?></td>
        <td><?php if ($row[19]!=0) {echo $row[16]/2; echo "-"; echo $row[16]/2;} else {echo "-";} ?></td>
        <td><?php echo $row[17] ?></td>
        <td><?php echo $row[18] ?></td>
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
Least S = Least Scored<br />
Least S = Least Conceded<br />
<br />
BW Diff = Biggest Win Difference, ie. the player's highest winning margin ever<br />
BL Diff = Biggest Loss Difference<br />
<br />
BD = Biggest Draw<br />
BGS = Biggest Goal Sum, ie. the most goal rich game the player has participated in<br />
SGS = Smallest Goal Sum




</div><!-- .k2-page-nav -->
</body>
</html>
