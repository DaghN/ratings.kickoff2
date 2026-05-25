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

//mysql_connect(localhost,$username,$password);
//@mysql_select_db($database) or die( "Unable to select database");
	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';
$query = 'SELECT id, Name, Rating, NumberGames, DoubleDigits, CleanSheets, DoubleDigitsRatio, CleanSheetsRatio, DoubleDigitsConceded, CleanSheetsConceded, DoubleDigitsConcededRatio, CleanSheetsConcededRatio FROM playertable WHERE ' . k2_lb_player_where_sql() . ' ORDER BY DoubleDigits DESC, rating DESC';
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'dds';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table ranked-pages-table ranked-table-pending" data-k2-table="sortable" data-k2-autorank="true" data-k2-default-sort="4" data-k2-default-direction="desc">

<thead>
    <tr style="text-align:right;">
        <th data-k2-sort="number">#</th>
        <th style="text-align:left;" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">ELO rating</th>
        <th data-k2-sort="number">&nbsp;&nbsp;Games</th>
        <th data-k2-sort="number">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;DD</th>
        <th data-k2-sort="number">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;CS</th>
        <th data-k2-sort="number">&nbsp;&nbsp;&nbsp;DD Ratio</th>
        <th data-k2-sort="number">&nbsp;&nbsp;&nbsp;&nbsp;CS Ratio</th>
        <th data-k2-sort="number">&nbsp;DD C</th>
        <th data-k2-sort="number">&nbsp;CS C</th>
        <th data-k2-sort="number">DD C Ratio</th>
        <th data-k2-sort="number">CS C Ratio</th>
    </tr>
</thead>


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
        <td><?php echo $row[3] ?></td>
       	<td><?php if ($row[4] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[4];?></span><?php } ?></td>
        <td><?php if ($row[5] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[5];?></span><?php } ?></td>
        <td><?php if ($row[6] == 0) {echo "0%";} else {echo "<span class='blue'>"; echo number_format(100*$row[6], 1); echo "%";} ?></td>
        <td><?php if ($row[7] == 0) {echo "0%";} else {echo "<span class='blue'>"; echo number_format(100*$row[7], 1); echo "%";} ?></td>
        <td><?php if ($row[8] == 0) {echo "0";} else {?><span class="red"><?php echo $row[8];?></span><?php } ?></td>
        <td><?php if ($row[9] == 0) {echo "0";} else {?><span class="red"><?php echo $row[9];?></span><?php } ?></td>
        <td><?php if ($row[10] == 0) {echo "0%";} else {echo "<span class='red'>"; echo number_format(100*$row[10], 1); echo "%";} ?></td>
        <td><?php if ($row[11] == 0) {echo "0%";} else {echo "<span class='red'>"; echo number_format(100*$row[11], 1); echo "%";} ?></td>
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



</div><!-- .k2-page-nav -->
</body>
</html>
