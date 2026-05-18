<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>KOOL Rating</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/thrColFixHdr.css" rel="stylesheet" type="text/css" />
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
        <li><a href="#" title="" class="current">Opponents</a></li>
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

$query = "SELECT id, name FROM playertable ORDER BY name";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con));
?>

<form name="example1" action="xfer.php" method="POST">
<select name="xfer" size="1" onChange="location = '' + this.options[this.selectedIndex ].value;">
<?php
while ($line = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
?>
<option <?php if ($id == $line[id]) { ?> selected="selected" <?php } ?> value="individual2c.php?id=<?php echo $line[id];?>"> <?php echo $line[name];?> </option>
<?php
}
?>
</select>
<noscript><input type="submit" value="Go!" />
</noscript>
</form>

<br />

<ul id="aboutmenu">
        <li><a href="individual2a.php?id=<?php echo $id ?>" title="" class="noncurrent">Results</a></li>
        <li><a href="individual2b.php?id=<?php echo $id ?>" title="" class="noncurrent">Goals</a></li>
        <li><a href="individual2c.php?id=<?php echo $id ?>" title="" class="current">DDs and CSs</a></li>
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

$query = "SELECT opponentID, opponentname, COUNT(*), SUM(DD), SUM(DDC), SUM(CS), SUM(CSC), AVG(DD), AVG(DDC), AVG(CS), AVG(CSC)
FROM(
    (
    SELECT idB AS opponentID, nameB AS opponentname, DDPlayerA AS DD, DDPlayerB AS DDC, CSPlayerA AS CS, CSPlayerB AS CSC FROM ratedresults 
    WHERE idA = '$id'
	)
    UNION ALL
    (
	SELECT idA AS opponentID, nameA AS opponentname, DDPlayerB AS DD, DDPlayerA AS DDC, CSPlayerB AS CS, CSPlayerA AS CSC FROM ratedresults 
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
        <th style="text-align:left;" class="table-sortable:ignorecase">Opponent</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;Games</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;DD</th>
        <th class="table-sortable:numeric">&nbsp;DD C</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;CS</th>
        <th class="table-sortable:numeric">&nbsp;CS C</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;DD Ratio</th>
        <th class="table-sortable:numeric">DD C Ratio</th>
        <th class="table-sortable:numeric">&nbsp;&nbsp;&nbsp;&nbsp;CS Ratio</th>
        <th class="table-sortable:numeric">CS C Ratio</th>
    </tr>
</thead>

<tfoot>
	<tr> 
        <td colspan="3" class="table-page:previous" style="cursor:pointer;">&lt;&lt; Previous</td> 
		<td colspan="5" style="text-align:center;">Page <span id="tablepage"></span>&nbsp;of <span id="tablepages"></span></td> 
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
        
        <td style="text-align:left;"><a href="individual1.php?id=<?php echo $row[0] ?>"><?php echo $row[1] ?></a></td>
        <td><?php echo $row[2] ?></td>
       	<td><?php if ($row[3] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[3];?></span><?php } ?></td>
        <td><?php if ($row[4] == 0) {echo "0";} else {?><span class="red"><?php echo $row[4];?></span><?php } ?></td>
        <td><?php if ($row[5] == 0) {echo "0";} else {?><span class="blue"><?php echo $row[5];?></span><?php } ?></td>
        <td><?php if ($row[6] == 0) {echo "0";} else {?><span class="red"><?php echo $row[6];?></span><?php } ?></td>
        <td><?php if ($row[7] == 0) {echo "0%";} else {echo "<span class='blue'>"; echo number_format(100*$row[7], 1); echo "%";} ?></td>
        <td><?php if ($row[8] == 0) {echo "0%";} else {echo "<span class='red'>"; echo number_format(100*$row[8], 1); echo "%";} ?></td>
        <td><?php if ($row[9] == 0) {echo "0%";} else {echo "<span class='blue'>"; echo number_format(100*$row[9], 1); echo "%";} ?></td>
        <td><?php if ($row[10] == 0) {echo "0%";} else {echo "<span class='red'>"; echo number_format(100*$row[10], 1); echo "%";} ?></td>
    </tr> 
    
    <?php
	$rank++; 
    }  
    ?> 
</tbody>

</table> 

</body>
</html>
