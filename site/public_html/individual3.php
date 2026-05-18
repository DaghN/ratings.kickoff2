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

<?php $id=$_GET['id']; ?>

<br />

<ul id="aboutmenu">
        <li><a href="server1.php" title="" class="noncurrent">Server Stats</a></li>
        <li><a href="ranked1.php" title="" class="noncurrent">Player Ranks</a></li>
        <li><a href="#" title="" class="current">Individual Pages</a></li>
</ul>

<br />
<br />

<ul id="aboutmenu">
        <li><a href="individual1.php?id=<?php echo $id ?>" title="" class="noncurrent">Profile</a></li>
        <li><a href="individual2a.php?id=<?php echo $id ?>" title="" class="noncurrent">Opponents</a></li>
        <li><a href="individual3.php?id=<?php echo $id ?>" title="" class="current">Games</a></li>
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

$query = "SELECT id, name FROM playertable ORDER BY name";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con));
?>

<form name="example1" action="xfer.php" method="POST">
<select name="xfer" size="1" onChange="location = '' + this.options[this.selectedIndex ].value;">
<?php
while ($line = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
?>
<option <?php if ($id == $line['id']) { ?> selected="selected" <?php } ?> value="individual3.php?id=<?php echo $line['id'];?>"> <?php echo $line['name'];?> </option>
<?php
}
?>
</select>
<noscript><input type="submit" value="Go!" />
</noscript>
</form>

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

$query = "SELECT * FROM ratedresults WHERE idA='$id' OR idB='$id' ORDER BY id DESC";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

mysqli_close($con);
?>

<table class="example table-autosort table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:30 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

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
        <td>&nbsp;<?php echo date('M d, H:i', strtotime($Date)) ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td><a href="individual3.php?id=<?php echo $idA ?>"><?php echo $NameA ?></a></td>
        <td><?php echo $GoalsA ?></td>
        <td style="text-align:left;"><?php echo $GoalsB ?></td>
        <td style="text-align:left;"><a href="individual3.php?id=<?php echo $idB ?>"><?php echo $NameB ?></a></td>
        
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
				{echo ("<a href=\"individual3.php?id=" .$idB. "\">" .$NameB. "</a>");} 
			else
				{echo ("<a href=\"individual3.php?id=" .$idA. "\">" .$NameA. "</a>");} 
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


</body>
</html>




