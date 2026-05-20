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

$query = "SELECT id, Name, Rating, PeakRating, NumberGames, DoubleDigits, DoubleDigitsConceded, CleanSheets, CleanSheetsConceded, DoubleDigitsRatio, DoubleDigitsConcededRatio, CleanSheetsRatio, CleanSheetsConcededRatio, DifferentOpponents, DoubleDigitsVictims, DoubleDigitsCulprits, CleanSheetsVictims, CleanSheetsCulprits FROM playertable WHERE display=1 ORDER BY rating DESC";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'dds';
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
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;DD</th>
        <th class="table-sortable:numeric">&nbsp;DD C</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;CS</th>
        <th class="table-sortable:numeric">&nbsp;CS C</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;DD Ratio</th>
        <th class="table-sortable:numeric">DD C Ratio</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;CS Ratio</th>
        <th class="table-sortable:numeric">CS C Ratio</th>
        <th class="table-sortable:numeric">Opponents</th>
        <th class="table-sortable:numeric">DD V</th>
        <th class="table-sortable:numeric">DD C</th>
        <th class="table-sortable:numeric">CS V</th>
        <th class="table-sortable:numeric">CS C</th>
        
    </tr>
</thead>

<tfoot>
	<tr> 
        <td colspan="3" class="table-page:previous" style="cursor:pointer;">&lt;&lt; Previous</td> 
		<td colspan="12" style="text-align:center;">Page <span id="tablepage"></span>&nbsp;of <span id="tablepages"></span></td> 
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
    
    <tr style="text-align:right">
        <td><?php echo $rank ?></td>
        <td style="text-align:left;"><a href="individual1.php?id=<?php echo $row[0] ?>"><?php echo $row[1] ?></a></td>
        <td><?php echo round($row[2]) ?></td>
        <td><?php if ($row[3] == 0) {echo "-";} else {echo "<span class='blue'>"; echo round($row[3]); echo "</span>";} ?></td>
        <td><?php echo $row[4] ?></td>
       	<td><?php if ($row[5] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[5];?></span><?php } ?></td>
        <td><?php if ($row[6] == 0) {echo "0";} else {?><span class="red"><?php echo $row[6];?></span><?php } ?></td>
        <td><?php if ($row[7] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[7];?></span><?php } ?></td>
        <td><?php if ($row[8] == 0) {echo "0";} else {?><span class="red"><?php echo $row[8];?></span><?php } ?></td>
        <td><?php if ($row[9] == 0) {echo "0%";} else {echo "<span class='blue'>"; echo number_format(100*$row[9], 1); echo "%";} ?></td>
        <td><?php if ($row[10] == 0) {echo "0%";} else {echo "<span class='red'>"; echo number_format(100*$row[10], 1); echo "%";} ?></td>
        <td><?php if ($row[11] == 0) {echo "0%";} else {echo "<span class='blue'>"; echo number_format(100*$row[11], 1); echo "%";} ?></td>
        <td><?php if ($row[12] == 0) {echo "0%";} else {echo "<span class='red'>"; echo number_format(100*$row[12], 1); echo "%";} ?></td>
        <td><?php echo $row[13] ?></td>
       	<td><?php if ($row[14] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[14];?></span><?php } ?></td>
        <td><?php if ($row[15] == 0) {echo "0";} else {?><span class="red"><?php echo $row[15];?></span><?php } ?></td>
        <td><?php if ($row[16] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[16];?></span><?php } ?></td>
        <td><?php if ($row[17] == 0) {echo "0";} else {?><span class="red"><?php echo $row[17];?></span><?php } ?></td>
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
DD = Double Digits, ie. the number of games where the player scored 10 or more goals<br />
DD C = Double Digits Conceded<br />
CS = Clean Sheets, ie. the number of games where the player's opponent scored no goals<br />
CS C = Clean Sheets Conceded, ie. the number of games where the player scored no goals<br />
<br />
DD V = Double Digit Victims, ie. the number of players the player has scored 10 or more goals against in one game<br />
DD C = Double Digit Culprits, ie. the number of players that have scored 10 or more goals against the player in one game<br />
 



</div><!-- .k2-page-nav -->
</body>
</html>
