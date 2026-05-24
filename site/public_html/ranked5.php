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
$query = 'SELECT id, Name, Rating, NumberGames, DifferentOpponents, DifferentVictims, DoubleDigitsVictims, CleanSheetsVictims, MostGoalsConcededVictims, BiggestLossVictims, DifferentCulprits, DoubleDigitsCulprits, CleanSheetsCulprits, MostGoalsScoredCulprits, BiggestWinCulprits FROM playertable WHERE ' . k2_lb_player_where_sql() . ' ORDER BY rating DESC';
$result = mysqli_query($con, $query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'victims';
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
        <th class="table-sortable:numeric">Opponents</th>
        <th class="table-sortable:numeric">&nbsp;Victims</th>
        <th class="table-sortable:numeric">DD Victims</th>
        <th class="table-sortable:numeric">CS Victims</th>
        <th class="table-sortable:numeric">MGC Victims</th>
        <th class="table-sortable:numeric">BL Victims</th>
        <th class="table-sortable:numeric">Culprits</th>
        <th class="table-sortable:numeric">DD Culprits</th>
        <th class="table-sortable:numeric">CS Culprits</th>
        <th class="table-sortable:numeric">MGS Culprits</th>
        <th class="table-sortable:numeric">BW Culprits</th>
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
        <td><?php echo $row[4] ?></td>
        <td><?php if ($row[5]!=0) {echo "<span class='blue'>"; echo $row[5]; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php if ($row[6] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[6];?></span><?php } ?></td>
        <td><?php if ($row[7] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[7];?></span><?php } ?></td>
        <td><?php if ($row[8] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[8];?></span><?php } ?></td>
        <td><?php if ($row[9] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[9];?></span><?php } ?></td>
        <td><?php if ($row[10]!=0) {echo "<span class='red'>"; echo $row[10]; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php if ($row[11] == 0) {echo "0";} else {?><span class="red"><?php echo $row[11];?></span><?php } ?></td>
        <td><?php if ($row[12] == 0) {echo "0";} else {?><span class="red"><?php echo $row[12];?></span><?php } ?></td>
        <td><?php if ($row[13] == 0) {echo "0";} else {?><span class="red"><?php echo $row[13];?></span><?php } ?></td>
        <td><?php if ($row[14] == 0) {echo "0";} else {?><span class="red"><?php echo $row[14];?></span><?php } ?></td>
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

Victims = same statistic as the Victims row on each player profile.<br />
DD Victims = Double Digit Victims<br />
CS Victims = Clean Sheet Victims<br />
MGC Victims = Most Goals Conceded Victims<br />
BL Victims = Biggest Loss Victims<br />
DD Culprits = Double Digit Culprits<br />
CS Culprits = Clean Sheet Culprits<br />
MGS Culprits = Most Goals Scored Culprits<br />
BW Culprits = Biggest Win Culprits<br />
<br />
Example: Joe has 3 BW Culprits. This means that 3 players set their Biggest Win record against Joe. <br />
Example: Joe has 5 MGC Victims. This means that 5 players set their Most Goals Conceded record against Joe. <br />
<br />
A player can only have one Biggest Loss (etc.) record. Let's say that Joe's biggest loss record is by 7 goals against Bob, <br />
but now Joe loses by 7 to George too. Then it is the LATEST game that takes precedence and is registered as Joe's biggest loss. <br />
So, in this case, Bob would have one LESS BL Victim, while George would get one MORE. You could say that previously Joe was <br /> 
"owned" by Bob, but now George owns him instead. The BL Victims number is then a count of how many players George owns in <br />
this way. Conversely, the BW Culprits number is a count of how many players you "own" (in a bad way) by providing them their <br />
unique biggest win.






</div><!-- .k2-page-nav -->
</body>
</html>
