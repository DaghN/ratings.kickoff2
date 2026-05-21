<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />
<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/favicon_head.php"; ?>
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
        <li><a href="individual2.php?id=<?php echo $id ?>" title="" class="current">Opponents</a></li>
        <li><a href="individual3.php?id=<?php echo $id ?>" title="" class="noncurrent">Games</a></li>
</ul>

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

$query = "SELECT opponentID, opponentname, COUNT(*), SUM(win), SUM(draw), SUM(defeat), AVG(win), AVG(draw), AVG(defeat), SUM(goalsfor), SUM(goalsagainst), AVG(goalsfor), AVG(goalsagainst), SUM(doubledigit), SUM(cleansheet), AVG(doubledigit), AVG(cleansheet)
FROM(
    (
    SELECT idB AS opponentID, nameB as opponentname, homewin as win, draw as draw, awaywin as defeat, goalsA AS goalsfor, goalsB AS goalsagainst, DDPlayerA AS doubledigit, CSPlayerA AS cleansheet FROM ratedresults 
    WHERE idA = '$id'
	)
    UNION ALL
    (
	SELECT idA AS opponentID, nameA as opponentname, awaywin as win, draw as draw, homewin as defeat, goalsB AS goalsfor, goalsA AS goalsagainst, DDPlayerB AS doubledigit, CSPlayerB AS cleansheet FROM ratedresults 
    WHERE idB = '$id' 
    )
	)AS derivedtable
GROUP BY opponentID
ORDER BY COUNT(*) DESC";

$result = mysqli_query($con, $query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<div class="k2-table-wrap">

<table class="k2-table table-autosort table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:30 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

<thead>
	
    
    
    <tr style="text-align:right;">
    	<th colspan="1" class="table-sortable:ignorecase" style="text-align:left;" >Opponent</th>
        <th class="table-sortable:numeric">Games</th>
        <th class="table-sortable:numeric">Wins</th>
        <th class="table-sortable:numeric">Draws</th>
        <th class="table-sortable:numeric">Defeats</th>
        <th class="table-sortable:numeric">Win Ratio</th>
        <th class="table-sortable:numeric">Draw Ratio</th>
        <th class="table-sortable:numeric">Defeat Ratio</th>
        <th class="table-sortable:numeric">Goals For</th>
        <th class="table-sortable:numeric">Goals Against</th>
        <th class="table-sortable:numeric">For avg.</th>
        <th class="table-sortable:numeric">Against avg.</th>
        <th class="table-sortable:numeric">Goal Ratio</th>
        <th class="table-sortable:numeric">Double Digits</th>
        <th class="table-sortable:numeric">Clean Sheets</th>
        <th class="table-sortable:numeric">DD Ratio</th>
        <th class="table-sortable:numeric">CS Ratio</th>
        
    </tr>
</thead>

<tfoot>
	<tr> 
        <td colspan="2" class="table-page:previous" style="cursor:pointer;">&lt;&lt; Previous</td> 
		<td colspan="13" style="text-align:center;">Page <span id="tablepage"></span>&nbsp;of <span id="tablepages"></span></td> 
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
    ?>
    
    <tr style="text-align:right;">
        
        
        <td style="text-align:left;"><a href="players1old.php?id=<?php echo $row[0] ?>"><?php echo $row[1] ?></a></td>
        <td><?php echo $row[2] ?></td>
        <td><?php echo $row[3] ?></td>
        <td><?php echo $row[4] ?></td>
        <td><?php echo $row[5] ?></td>
        <td><?php echo number_format(100*$row[6], 1); echo "%"; ?></td>
        <td><?php echo number_format(100*$row[7], 1); echo "%"; ?></td>
        <td><?php echo number_format(100*$row[8], 1); echo "%"; ?></td>
        <td><?php echo $row[9] ?></td>
        <td><?php echo $row[10] ?></td>
        <td><?php echo number_format($row[11], 2) ?></td>
        <td><?php echo number_format($row[12], 2) ?></td>
        <td><?php if($row[10] != 0) {echo number_format($row[9]/$row[10], 2);} else {echo "&#8734;";} ?></td>
        <td><?php echo $row[13] ?></td>
        <td><?php echo $row[14] ?></td>
        <td><?php echo number_format(100*$row[15], 1); echo "%"; ?></td>
        <td><?php echo number_format(100*$row[16], 1); echo "%"; ?></td>
        
    </tr> 
    
    <?php
	$i++; 
    }  
    ?> 
</tbody>

</table>

</div><!-- .k2-table-wrap -->


</div><!-- .k2-page-nav -->
</body>
</html>
