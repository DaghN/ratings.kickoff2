<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="js/elolist.js" ></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php $id=$_GET['id']; ?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

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

$query = "SELECT * FROM ratedresults WHERE idA='$id' OR idB='$id' ORDER BY id DESC";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_hero.php"; ?>
<?php
$k2PlayerTabActive = 'games';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_nav.php";
?>

<div class="k2-table-wrap">

<table class="k2-table table-autosort table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:100 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount">

<thead>
	<tr style="text-align:right;">
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
      <th class="filtercell"></th>
      <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="table-filterable filtercell"></th>
        <th class="table-filterable filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
        <th class="filtercell"></th>
    </tr>
    
<tr style="text-align:right;">
    	<th class="table-sortable:numeric" style="text-align:left;">ID</th>
        <th class="table-sortable:date" style="text-align:left;">&nbsp;Date</th>
        <th class="table-sortable:ignorecase">Team A</th>
        <th class="table-sortable:numeric"></th>
        <th class="table-sortable:numeric"></th>
<th class="table-sortable:ignorecase" style="text-align:left;">Team B</th>
        <th class="table-sortable:ignorecase" style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Result</th>
        <th class="table-sortable:ignorecase" style="text-align:left;">Opponent</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;F</th>
        <th class="table-sortable:numeric">A</th>
      <th class="table-sortable:numeric">Diff</th>
        <th class="table-sortable:numeric">Sum</th>
<th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;<?php echo $name ?></th>
        <th class="table-sortable:numeric">Opponent</th>
       	<th class="table-sortable:numeric">ES <?php echo $name ?></th> 
        <th class="table-sortable:numeric">Adjustment</th>   
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
    
	$gameid = $row[0];
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
	$AdjustmentB = $row[22];
	$SumOfGoals = $row[25];
	$GoalDifference = $row[26];
	$WinnerID = $row[27]
	?>
    
    <tr style="text-align:right;">
        
        <td><a href="game.php?id=<?php echo $gameid ?>"><?php echo $gameid ?></a></td>
        <td>&nbsp;<?php echo date('M d Y, H:i', strtotime($Date)) ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td><a href="individual1.php?id=<?php echo $idA ?>"><?php echo $NameA ?></a></td>
        <td><?php echo $GoalsA ?></td>
        <td style="text-align:left;"><?php echo $GoalsB ?></td>
        <td style="text-align:left;"><a href="individual1.php?id=<?php echo $idB ?>"><?php echo $NameB ?></a></td>
        
        <td style="text-align:left;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php 	
			if ($WinnerID == $id) 
				{echo "<span class='blue'>Win</span>";} 
			elseif ($ActualScore == 0.5) 
				{echo "-";}
			else 
				{echo "<span class='red'>Loss</span>";}
		?></td> 
        
        <td style="text-align:left;"><?php 	
			if ($idA == $id) 
				{echo ("<a href=\"individual1.php?id=" .$idB. "\">" .$NameB. "</a>");} 
			else 
				{echo ("<a href=\"individual1.php?id=" .$idA. "\">" .$NameA. "</a>");}
		?></td>
        
        <td><?php if ($idA == $id) {echo $GoalsA;} else {echo $GoalsB;} ?></td>
        <td><?php if ($idA == $id) {echo $GoalsB;} else {echo $GoalsA;} ?></td>
        <td><?php 
			if ($ActualScore == 0.5)
				{echo $GoalDifference;}
			elseif ($WinnerID != $id) 
				{echo "<span class='red'>"; echo -$GoalDifference; echo "</span>";} 
			else 
				{echo "<span class='blue'>"; echo $GoalDifference; echo "</span>";}
        ?></td>
        <td><?php echo $SumOfGoals ?></td>
      <td><?php if ($idA == $id) {echo round($RatingA);} else {echo round($RatingB);} ?></td>
        <td><?php if ($idA == $id) {echo round($RatingB);} else {echo round($RatingA);} ?></td>
    
        
        <td><?php if ($idA == $id) {echo number_format(100*$ExpectedScoreA, 1); echo "%";} else {echo number_format(100*$ExpectedScoreB, 1); echo "%";} ?></td>
        <td><?php 
			if ($idA == $id) {
				if ($AdjustmentA >= 0) 
					{echo "<span class='blue'>"; echo number_format($AdjustmentA, 1); echo "</span>";} 
				else
					{echo "<span class='red'>"; echo number_format($AdjustmentA, 1); echo "</span>";}
			}
			else {
				if ($AdjustmentB >= 0) 
					{echo "<span class='blue'>"; echo number_format($AdjustmentB, 1); echo "</span>";} 
				else
					{echo "<span class='red'>"; echo number_format($AdjustmentB, 1); echo "</span>";}
			}
		?></td>
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




