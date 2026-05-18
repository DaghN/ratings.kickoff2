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
        <li><a href="ranked1.php" title="" class="noncurrent">Player Ranks</a></li>
        <li><a href="individualA.php" title="" class="current">Individual Pages</a></li>
</ul>

<br />
<br />

<ul id="aboutmenu">
        <li><a href="individualA.php?id=<?php if (isset($id)) echo $id ?>" title="" class="noncurrent">Profile</a></li>
        <li><a href="individualB.php?id=<?php if (isset($id)) echo $id ?>" title="" class="noncurrent">Opponents</a></li>
        <li><a href="individualC.php?id=<?php if (isset($id)) echo $id ?>" title="" class="current">Games</a></li>
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
$result = mysqli_query($con,$query);
?>

<script type="text/javascript">
function functionblack_dress(el,selected){ 
var to=el.value; 
el.selectedIndex=selected; 
window.location=to; 
}
</script>

<form name="example1" action="xfer.php" method="POST">
<select name="xfer" size="1" onChange="location = '' + this.options[this.selectedIndex ].value;">
<option selected="selected" value="individual.php">--Select Player--</option>
<?php
while ($line = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
?>
<option value="individual3.php?id=<?php echo $line['id'];?>"> <?php echo $line['name'];?> </option>
<?php
}
?>
</select>
<noscript><input type="submit" value="Go!" />
</noscript>
</form>

</body>
</html>
