<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/thrColFixHdr.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/elolist.js" ></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php $id=$_GET['id']; ?>

<br />

<ul id="aboutmenu">
        <li><a href="server1.php" title="" class="noncurrent">Server Stats</a></li>
        <li><a href="ranked1.php" title="" class="noncurrent">Player Ranks</a></li>
        <?php $playerSearchAsNavItem = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_search_bar.php"; ?>
</ul>

<br />
<br />

<ul id="aboutmenu">
        <li><a href="individual1.php?id=<?php echo $id ?>" title="" class="noncurrent">Profile</a></li>
        <li><a href="#" title="" class="current">Opponents</a></li>
        <li><a href="individual3.php?id=<?php echo $id ?>" title="" class="noncurrent">Games</a></li>
</ul>

<br />
<br />

<br />

<ul id="aboutmenu">
        <li><a href="individual2a.php?id=<?php echo $id ?>" title="" class="current">Results</a></li>
        <li><a href="individual2b.php?id=<?php echo $id ?>" title="" class="noncurrent">Goals</a></li>
        <li><a href="individual2c.php?id=<?php echo $id ?>" title="" class="noncurrent">DDs and CSs</a></li>
<!--
        <li><a href="individual2d.php?id=<?php echo $id ?>" title="" class="noncurrent">Streaks</a></li>
        <li><a href="individual2e.php?id=<?php echo $id ?>" title="" class="noncurrent">Victims and Culprits</a></li>
-->
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

$query = "SELECT name FROM playertable WHERE id='$id'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

$row = mysqli_fetch_row($result);
$name = $row[0];

$query = "SELECT opponentID, opponentname, COUNT(*), SUM(win), SUM(draw), SUM(defeat), AVG(win), AVG(draw), AVG(defeat)
FROM(
    (
    SELECT idB AS opponentID, nameB AS opponentname, homewin AS win, draw AS draw, awaywin AS defeat FROM ratedresults 
    WHERE idA = '$id'
	)
    UNION ALL
    (
	SELECT idA AS opponentID, nameA AS opponentname, awaywin AS win, draw AS draw, homewin AS defeat FROM ratedresults 
    WHERE idB = '$id' 
    )
	)AS derivedtable
GROUP BY opponentID,opponentname
ORDER BY COUNT(*) DESC";

$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<table class="example table-autosort table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:30 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

<thead>
	
    <tr style="text-align:right;">
    	<th colspan="1" class="table-sortable:ignorecase" style="text-align:left;" >Opponent</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;Games</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;Wins</th>
        <th class="table-sortable:numeric">Draws</th>
        <th class="table-sortable:numeric">Losses</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Win Ratio</th>
        <th class="table-sortable:numeric">Draw Ratio</th>
        <th class="table-sortable:numeric">Loss Ratio</th>
    </tr>
</thead>

<tfoot>
	<tr> 
        <td colspan="2" class="table-page:previous" style="cursor:pointer;">&lt;&lt; Previous</td> 
		<td colspan="4" style="text-align:center;">Page <span id="tablepage"></span>&nbsp;of <span id="tablepages"></span></td> 
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
    $i = "1";
    while ($row = mysqli_fetch_row($result))
    {  
        
    $opponentid = $row[0];
    $opponentname = $row[1];
    $games = $row[2];
    $wins = $row[3];
    $draws = $row[4];
    $losses = $row[5];
    $winratio = $row[6];
    $drawratio = $row[7];
    $lossratio = $row[8];
	?>
    
    <tr style="text-align:right;">
        <td style="text-align:left;"><a href="individual1.php?id=<?php echo $opponentid ?>"><?php echo $opponentname ?></a></td>
        <td><?php echo $games ?></td>
        <td><?php if ($wins!=0) {echo "<span class='blue'>"; echo $wins; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php echo $draws ?></td>
        <td><?php if ($losses!=0) {echo "<span class='red'>"; echo $losses; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php if ($wins!=0) {echo "<span class='blue'>"; echo number_format(100*$winratio, 1); echo "%";} else {echo "0%";} ?></td>
        <td><?php echo number_format(100*$drawratio, 1); echo "%"; ?></td>
        <td><?php if ($losses!=0) {echo "<span class='red'>"; echo number_format(100*$lossratio, 1); echo "%";} else {echo "0%";} ?></td>
    </tr> 
    
    <?php
	$i++; 
    }  
    ?> 
</tbody>

</table> 


</div><!-- .k2-page-nav -->
</body>
</html>
