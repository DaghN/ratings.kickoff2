<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>KOOL Rating</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/ranked_table_cloak_head.php"; ?>
<script type="text/javascript" src="js/elolist.js" ></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body>

<br />

<ul id="aboutmenu">
        <li><a href="server1.php" title="" class="noncurrent">Server Stats</a></li>
        <li><a href="#" title="" class="current">Player Ranks</a></li>
        <?php $playerSearchAsNavItem = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_search_bar.php"; ?>
</ul>

<br />
<br />

<ul id="aboutmenu">
        <li><a href="ranked1.php" title="" class="noncurrent">Results</a></li>
        <li><a href="ranked2.php" title="" class="noncurrent">Goals</a></li>
        <li><a href="ranked3.php" title="" class="noncurrent">DDs and CSs</a></li>
        <li><a href="ranked4.php" title="" class="noncurrent">Streaks</a></li>
        <li><a href="ranked5.php" title="" class="current">Victims and Culprits</a></li>
        <li><a href="ranked6.php" title="" class="noncurrent">Rating Records</a></li>
</ul>

<br />
<br />
<br />

<?php 
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";

//mysql_connect(localhost,$username,$password);
//@mysql_select_db($database) or die( "Unable to select database");
	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}

$query = "SELECT id, Name, Rating, PeakRating, NumberGames, DifferentOpponents, DifferentVictims, MostGoalsConcededVictims, LeastGoalsScoredVictims, BiggestLossVictims, MostGoalsScoredCulprits, LeastGoalsConcededCulprits, BiggestWinCulprits FROM playertable WHERE display=1 ORDER BY rating DESC";
$result = mysqli_query($con, $query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<table class="example ranked-pages-table ranked-table-pending table-autosort table-autofilter table-autorank table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:30 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

<thead>
    <tr style="text-align:right;">
        <th class="table-sortable:numeric">Rank</th>
        <th style="text-align:left;" class="table-sortable:ignorecase">Player</th>
        <th class="table-sortable:numeric">ELO rating</th>
        <th class="table-sortable:numeric">Peak</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;Games</th>
        <th class="table-sortable:numeric">Opponents</th>
        <th class="table-sortable:numeric">&nbsp;Victims</th>
        <th class="table-sortable:numeric">MGC Victims</th>
        <th class="table-sortable:numeric">LGS Victims</th>
        <th class="table-sortable:numeric">BL Victims</th>
        <th class="table-sortable:numeric">MGS Culprits</th>
        <th class="table-sortable:numeric">LGC Culprits</th>
        <th class="table-sortable:numeric">BW Culprits</th>
    </tr>
</thead>

<tfoot>
	<tr> 
        <td colspan="3" class="table-page:previous" style="cursor:pointer;">&lt;&lt; Previous</td> 
		<td colspan="7" style="text-align:center;">Page <span id="tablepage"></span>&nbsp;of <span id="tablepages"></span></td> 
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
        <td><?php echo $row[5] ?></td>
        <td><?php if ($row[6]!=0) {echo "<span class='blue'>"; echo $row[6]; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php if ($row[7] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[7];?></span><?php } ?></td>
        <td><?php if ($row[8] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[8];?></span><?php } ?></td>
        <td><?php if ($row[9] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[9];?></span><?php } ?></td>
        <td><?php if ($row[10] == 0) {echo "0";} else {?><span class="red"><?php echo $row[10];?></span><?php } ?></td>
        <td><?php if ($row[11] == 0) {echo "0";} else {?><span class="red"><?php echo $row[11];?></span><?php } ?></td>
        <td><?php if ($row[12] == 0) {echo "0";} else {?><span class="red"><?php echo $row[12];?></span><?php } ?></td>
    </tr> 
    
    <?php
	$rank++; 
    }  
    ?> 
</tbody>

</table> 

<br />

Victims = same statistic as the Victims row on each player profile.<br />
MGC Victims = Most Goals Conceded Victims<br />
LGC Victims = Least Goals Conceded Victims<br />
BL Victims = Biggest Loss Victims<br />
MGS Culprits = Most Goals Scored Culprits<br />
LGC Culprits = Least Goals Conceded Culprits<br />
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





</body>
</html>
