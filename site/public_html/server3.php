<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/elolist.js" ></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<ul id="aboutmenu">
        <li><a href="#" title="" class="current">Server Stats</a></li>
        <li><a href="ranked1.php" title="" class="noncurrent">Player Ranks</a></li>
        <?php $playerSearchAsNavItem = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_search_bar.php"; ?>
</ul>

<br />
<br />

<ul id="aboutmenu">
        <li><a href="server1.php" title="" class="noncurrent">Overall</a></li>
        <li><a href="server2.php" title="" class="noncurrent">Records</a></li>
        <li><a href="server3.php" title="" class="current">Activity</a></li>
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

$query = "SELECT * FROM ratedresults ORDER BY id DESC LIMIT 50";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<table class="example table-autosort table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:30 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

<thead>
	<tr style="text-align:right;">
    	<th class="table-sortable:numeric" style="text-align:left;">ID</th>
        <th class="table-sortable:date" style="text-align:left;">&nbsp;Date</th>
        <th class="table-sortable:ignorecase">Team A</th>
        <th class="table-sortable:numeric"></th>
        <th class="table-sortable:numeric"></th>
        <th class="table-sortable:ignorecase" style="text-align:left;">Team B</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;Diff</th>
        <th class="table-sortable:numeric">Sum</th>
        <th class="table-sortable:ignorecase" style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;Winner</th>
        <th class="table-sortable:numeric">Rating A</th>
        <th class="table-sortable:numeric">Rating B</th>
        <th class="table-sortable:numeric">Rating Diff</th>
       	<th class="table-sortable:numeric">ES Winner</th> 
        <th class="table-sortable:numeric">Adjustment</th>
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
    $i = "1";
    while ($row = mysqli_fetch_row($result))
    {  
    
	$id = $row[0];
	$Date = $row[1];
	$idA = $row[2];
	$NameA = $row[3];
	$idB = $row[4];
	$NameB = $row[5];
	$RatingA = $row[6];
	$RatingB = $row[7];
	$RatingDifference = $row[8];
	$GoalsA = $row[9];
	$GoalsB = $row[10];
	$ExpectedScoreA = $row[18];
	$ExpectedScoreB = $row[19];
	$ActualScore = $row[20];
	$AdjustmentA = $row[21];
	$SumOfGoals = $row[25];
	$GoalDifference = $row[26];
	?>
    
    <tr style="text-align:right;">
        <td><a href="game.php?id=<?php echo $id ?>"><?php echo $id ?></a></td>
        <td>&nbsp;<?php echo date('M d Y, H:i', strtotime($Date)) ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td><a href="individual1.php?id=<?php echo $idA ?>"><?php echo $NameA ?></a></td>
        <td><?php echo $GoalsA ?></td>
        <td style="text-align:left;"><?php echo $GoalsB ?></td>
        <td style="text-align:left;"><a href="individual1.php?id=<?php echo $idB ?>"><?php echo $NameB ?></a></td>
        <td><?php echo $GoalDifference ?></td>
        <td><?php echo $SumOfGoals ?></td>
        <td style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;
			<?php	if 		($ActualScore == 1) {echo ("<a href=\"individual1.php?id=" .$idA. "\">" .$NameA. "</a>");}
			      	elseif 	($ActualScore == 0) {echo ("<a href=\"individual1.php?id=" .$idB. "\">" .$NameB. "</a>");}
					else 	{echo "-";}
			?></td>
        <td><?php echo round($RatingA) ?></td>
        <td><?php echo round($RatingB) ?></td>
        <td><?php echo number_format(abs($RatingDifference), 1) ?></td>
        <td><?php	if 		($ActualScore == 1) {echo number_format(100*$ExpectedScoreA, 1); echo "%";}
			      	elseif 	($ActualScore == 0) {echo number_format(100*$ExpectedScoreB, 1); echo "%";}
					else 	{echo number_format(min(100*$ExpectedScoreA, 100*$ExpectedScoreB), 1); echo "%";}
			?></td>       
        <td><?php 
			if (abs($AdjustmentA) > 16 ) {echo "<span class='red'>";}
		
			echo "&#177; "; echo number_format(abs($AdjustmentA), 1);
		?></td>
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




