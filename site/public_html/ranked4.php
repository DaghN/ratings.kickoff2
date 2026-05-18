<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>KOOL Rating</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/elolist.js" ></script>

</head>

<body>

<br />

<ul id="aboutmenu">
        <li><a href="server1.php" title="" class="noncurrent">Server Stats</a></li>
        <li><a href="#" title="" class="current">Player Ranks</a></li>
        <li><a href="individualA.php" title="" class="noncurrent">Individual Pages</a></li>
</ul>

<br />
<br />

<ul id="aboutmenu">
        <li><a href="ranked1.php" title="" class="noncurrent">Results</a></li>
        <li><a href="ranked2.php" title="" class="noncurrent">Goals</a></li>
        <li><a href="ranked3.php" title="" class="noncurrent">DDs and CSs</a></li>
        <li><a href="ranked4.php" title="" class="current">Streaks</a></li>
        <li><a href="ranked5.php" title="" class="noncurrent">Victims and Culprits</a></li>
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

$query = "SELECT id, Name, Rating, NumberGames, WinningStreak, LongestWinningStreak, DrawingStreak, LongestDrawingStreak, LosingStreak, LongestLosingStreak, NonLossStreak, LongestNonLossStreak, NonDrawStreak, LongestNonDrawStreak, NonWinStreak, LongestNonWinStreak FROM playertable WHERE display=1 AND PlayerRank<>9999 ORDER BY rating DESC";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<table class="example table-autosort table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:30 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

<thead>
    <tr style="text-align:right;">
        <th class="table-sortable:numeric">Rank</th>
        <th style="text-align:left;" class="table-sortable:ignorecase">Player</th>
        <th class="table-sortable:numeric">Rating</th>
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

<tfoot>
	<tr> 
        <td colspan="3" class="table-page:previous" style="cursor:pointer;">&lt;&lt; Previous</td> 
		<td colspan="10" style="text-align:center;">Page <span id="tablepage"></span>&nbsp;of <span id="tablepages"></span></td> 
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
        <td><?php echo $row[3] ?></td>
        <td><?php if ($row[4] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[4]; echo "</span>";} ?></td>
        <td><?php if ($row[5] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[5]; echo "</span>";} ?></td>
        <td><?php if ($row[6] == 0) {echo "-";} else {echo $row[6];} ?></td>
        <td><?php if ($row[7] == 0) {echo "-";} else {echo $row[7];} ?></td>
        <td><?php if ($row[8] == 0) {echo "-";} else {echo "<span class='red'>"; echo $row[8]; echo "</span>";} ?></td>
        <td><?php if ($row[9] == 0) {echo "-";} else {echo "<span class='red'>"; echo $row[9]; echo "</span>";} ?></td>
        <td><?php if ($row[10] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[10]; echo "</span>";} ?></td>
        <td><?php if ($row[11] == 0) {echo "-";} else {echo "<span class='blue'>"; echo $row[11]; echo "</span>";} ?></td>
        <td><?php if ($row[12] == 0) {echo "-";} else {echo $row[12];} ?></td>
        <td><?php if ($row[13] == 0) {echo "-";} else {echo $row[13];} ?></td>
        <td><?php if ($row[14] == 0) {echo "-";} else {echo "<span class='red'>"; echo $row[14]; echo "</span>";} ?></td>
        <td><?php if ($row[15] == 0) {echo "-";} else {echo "<span class='red'>"; echo $row[15]; echo "</span>";} ?></td>
    </tr> 
    
    <?php
	$rank++; 
    }  
    ?> 
</tbody>

</table> 

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



</body>
</html>
