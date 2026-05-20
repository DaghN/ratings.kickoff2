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
        <li><a href="ranked5.php" title="" class="noncurrent">Victims and Culprits</a></li>
        <li><a href="ranked6.php" title="" class="current">Rating Records</a></li>
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

$query = "SELECT id, Name, Rating, PeakRating, NumberGames, AverageOpponentRating, HighestRatedVictim, LowestRatedCulprit, CurrentRatingAscent, CurrentRatingDescent, BiggestRatingAscent, BiggestRatingDescent, RecentAverageRating, LowestRating FROM playertable WHERE display=1 ORDER BY rating DESC";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
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
        <th class="table-sortable:numeric">Opponent Avg.</th>
        <th class="table-sortable:numeric">Highest Victim</th>
        <th class="table-sortable:numeric">Lowest Culprit</th>
        <th class="table-sortable:numeric">Current Ascent</th>
        <th class="table-sortable:numeric">Highest Ascent</th>
        <th class="table-sortable:numeric">Current Descent</th>
        <th class="table-sortable:numeric">Longest Descent</th>
        <th class="table-sortable:numeric">Recent Avg.</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;Nadir</th>
    </tr>
</thead>

<tfoot>
	<tr> 
        <td colspan="2" class="table-page:previous" style="cursor:pointer;">&lt;&lt; Previous</td> 
		<td colspan="10" style="text-align:center;">Page <span id="tablepage"></span>&nbsp;of <span id="tablepages"></span></td> 
		<td colspan="2" class="table-page:next" style="cursor:pointer; text-align:right;">Next &gt;&gt;</td> 
        
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
        <td><?php echo round($row[5]) ?></td>
        <td><?php if ($row[6] == 0) {echo "-";} else {echo "<span class='blue'>"; echo round($row[6]); echo "</span>";} ?></td>
        <td><?php if ($row[7] == 5000) {echo "-";} else {echo "<span class='red'>"; echo round($row[7]); echo "</span>";} ?></td>
        <td><?php if ($row[8] == 0) {echo "-";} else {echo "<span class='blue'>"; echo "+"; echo number_format($row[8], 1, '.', ''); echo "</span>";} ?></td>
        <td><?php if ($row[10] == 0) {echo "-";} else {echo "<span class='blue'>"; echo "+"; echo number_format($row[10], 1, '.', ''); echo "</span>";} ?></td>
        <td><?php if ($row[9] == 0) {echo "-";} else {echo "<span class='red'>"; echo "-"; echo number_format($row[9], 1, '.', ''); echo "</span>";} ?></td>
        <td><?php if ($row[11] == 0) {echo "-";} else {echo "<span class='red'>"; echo "-"; echo number_format($row[11], 1, '.', ''); echo "</span>";} ?></td>
        <td><?php echo number_format($row[12], 1, '.', '') ?></td>
        <td><?php if ($row[13] == 5000) {echo "-";} else {echo "<span class='red'>"; echo round($row[13]); echo "</span>";} ?></td>
    </tr> 
    
    <?php
	$rank++; 
    }  
    ?> 
</tbody>

</table> 





</div><!-- .k2-page-nav -->
</body>
</html>
