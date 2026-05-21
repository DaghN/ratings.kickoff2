<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />
<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/favicon_head.php"; ?>
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

$query = "SELECT id, Name, Rating, PeakRating, NumberGames, WinningStreak, LongestWinningStreak, DrawingStreak, LongestDrawingStreak, LosingStreak, LongestLosingStreak, NonLossStreak, LongestNonLossStreak, NonDrawStreak, LongestNonDrawStreak, NonWinStreak, LongestNonWinStreak FROM playertable WHERE display=1 ORDER BY rating DESC";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'streaks';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table ranked-pages-table ranked-table-pending table-autosort table-autofilter table-autorank table-stripeclass:alternate table-autostripe table-rowshade-alternate table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

<thead>
    <tr style="text-align:right;">
        <th class="table-sortable:numeric">Rank</th>
        <th style="text-align:left;" class="table-sortable:ignorecase">Player</th>
        <th class="table-sortable:numeric">ELO rating</th>
        <th class="table-sortable:numeric">Peak</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;Games</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;WS</th>
        <th class="table-sortable:numeric">LWS</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;DS</th>
        <th class="table-sortable:numeric">LDS</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;LS</th>
        <th class="table-sortable:numeric">LLS</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;NLS</th>
        <th class="table-sortable:numeric">LNLS</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;NDS</th>
        <th class="table-sortable:numeric">LNDS</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;NWS</th>
        <th class="table-sortable:numeric">LNWS</th>
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
        <td><?php if ($row[3] == 0) {echo "-";} else {echo "<span class='blue'>"; echo round($row[3]); echo "</span>";} ?></td>
        <td><?php echo $row[4] ?></td>
        <td><?php if ($row[5] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[5]; echo "</span>";} ?></td>
        <td><?php if ($row[6] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[6]; echo "</span>";} ?></td>
        <td><?php if ($row[7] == 0) {echo "-";} else {echo $row[7];} ?></td>
        <td><?php if ($row[8] == 0) {echo "-";} else {echo $row[8];} ?></td>
        <td><?php if ($row[9] == 0) {echo "-";} else {echo "<span class='red'>"; echo $row[9]; echo "</span>";} ?></td>
        <td><?php if ($row[10] == 0) {echo "-";} else {echo "<span class='red'>"; echo $row[10]; echo "</span>";} ?></td>
        <td><?php if ($row[11] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[11]; echo "</span>";} ?></td>
        <td><?php if ($row[12] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[12]; echo "</span>";} ?></td>
        <td><?php if ($row[13] == 0) {echo "-";} else {echo $row[13];} ?></td>
        <td><?php if ($row[14] == 0) {echo "-";} else {echo $row[14];} ?></td>
        <td><?php if ($row[15] == 0) {echo "-";} else {echo "<span class='red'>"; echo $row[15]; echo "</span>";} ?></td>
        <td><?php if ($row[16] == 0) {echo "-";} else {echo "<span class='red'>"; echo $row[16]; echo "</span>";} ?></td>
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
WS = Winning Streak<br />
LWS = Longest Winning Streak ever<br />
<br />
DS = Drawing Streak<br />
LDS = Longest Drawing Streak ever<br />
<br />
LS = Losing Streak<br />
LLS = Longest Losing Streak ever<br />
<br />
NLS = No Losses Streak<br />
LNLS = Longest No Losses Streak ever<br />
<br />
NDS = No Draws Streak<br />
LNDS = Longest No Draws Streak ever<br />
<br />
NWS = No Wins Streak<br />
LNWS = Longest No Wins Streak ever




</div><!-- .k2-page-nav -->
</body>
</html>
