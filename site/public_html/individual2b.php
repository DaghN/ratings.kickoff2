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

<?php 
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";
//mysql_connect(localhost,$username,$password);
//@mysql_select_db($database) or die( "Unable to select database");
	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}

include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero_vars.php";
$name = $Name;

$query = "SELECT opponentID, opponentname, COUNT(*), SUM(goalsfor), SUM(goalsagainst), AVG(goalsfor), AVG(goalsagainst), MAX(goalsfor), MAX(goalsagainst), MIN(goalsfor), MIN(goalsagainst), MAX(goalsfor-goalsagainst), MAX(goalsagainst-goalsfor), MAX((goalsfor)*(goalsfor=goalsagainst)), SUM(draw), MAX(goalsfor+goalsagainst), MIN(goalsfor+goalsagainst)
FROM(
    (
    SELECT idB AS opponentID, nameB AS opponentname, goalsA AS goalsfor, goalsB AS goalsagainst, draw AS draw FROM ratedresults 
    WHERE idA = '$id'
	)
    UNION ALL
    (
	SELECT idA AS opponentID, nameA AS opponentname, goalsB AS goalsfor, goalsA AS goalsagainst, draw AS draw FROM ratedresults 
    WHERE idB = '$id' 
    )
	)AS derivedtable
GROUP BY opponentID,opponentname
ORDER BY COUNT(*) DESC";

$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero.php"; ?>
<?php
$k2PlayerTabActive = 'goals';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table table-autosort table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:30 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

<thead>
	
    <tr style="text-align:right;">
    	<th colspan="1" class="table-sortable:ignorecase" style="text-align:left;" >Opponent</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;Games</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;GF</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;GA</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Avg.</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;Avg.</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Ratio</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;Most S</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;Most C</th>
        <th class="table-sortable:numeric">&nbsp;Least S</th>
        <th class="table-sortable:numeric">&nbsp;Least C</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;BW Diff</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;BL Diff</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;BD </th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;BG Sum</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;SG Sum</th>
    </tr>
</thead>

<tfoot>
	<tr> 
        <td colspan="2" class="table-page:previous" style="cursor:pointer;">&lt;&lt; Previous</td> 
		<td colspan="12" style="text-align:center;">Page <span id="tablepage"></span>&nbsp;of <span id="tablepages"></span></td> 
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
    $goalsfor = $row[3];
    $goalsagainst = $row[4];
    $averagefor = $row[5];
    $averageagainst = $row[6];
    if ($row[4] != 0){$goalratio = $row[3]/$row[4];} else {$goalratio = -1;}
	$mostscored = $row[7];
	$mostconceded = $row[8];
	$leastscored = $row[9];
	$leastconceded = $row[10];
	$biggestwin = $row[11];
	$biggestloss = $row[12];
	$biggestdraw = $row[13];
	$numberdraws = $row[14];
	$biggestgoalsum = $row[15];
	$smallestgoalsum = $row[16];
	
	
	?>
    
    <tr style="text-align:right;">
        <td style="text-align:left;"><a href="individual1.php?id=<?php echo $opponentid ?>"><?php echo $opponentname ?></a></td>
        <td><?php echo $games ?></td>
        <td><?php if ($goalsfor!=0) {echo "<span class='blue'>"; echo $goalsfor; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php if ($goalsagainst!=0) {echo "<span class='red'>"; echo $goalsagainst; echo "</span>"; } else {echo "0";} ?></td>
        <td><?php if ($averagefor!=0) {echo "<span class='blue'>";} echo number_format($averagefor, 2) ?></td>
        <td><?php if ($averageagainst!=0) {echo "<span class='red'>";} echo number_format($averageagainst, 2) ?></td>
        <td><?php
        	if ($goalratio == -1) 
				{echo "-";}
			else 
				{if ($goalratio>=1) {echo "<span class='blue'>";} else {echo "<span class='red'>";} echo number_format($goalratio, 2);}
		?></td>
        <td><?php if ($mostscored!=0) {echo "<span class='blue'>"; echo $mostscored; echo "</span>"; } else {echo "-";} ?></td>
        <td><?php if ($mostconceded!=0) {echo "<span class='red'>"; echo $mostconceded; echo "</span>"; } else {echo "-";} ?></td>
        <td><?php echo "<span class='blue'>"; echo $leastscored; echo "</span>"; ?></td>
       	<td><?php echo "<span class='red'>"; echo $leastconceded; echo "</span>"; ?></td>
        <td><?php if ($biggestwin!=0) {echo "<span class='blue'>"; echo $biggestwin; echo "</span>"; } else {echo "-";} ?></td>
       	<td><?php if ($biggestloss!=0) {echo "<span class='red'>"; echo $biggestloss; echo "</span>"; } else {echo "-";} ?></td>
        <td><?php if ($numberdraws > 0) {echo $biggestdraw; echo "-"; echo $biggestdraw;} else {echo "-";} ?></td>
        <td><?php echo $biggestgoalsum ?></td>
        <td><?php echo $smallestgoalsum ?></td>
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
