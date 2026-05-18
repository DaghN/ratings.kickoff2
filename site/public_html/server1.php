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
        <li><a href="#" title="" class="current">Server Stats</a></li>
        <li><a href="ranked1.php" title="" class="noncurrent">Player Ranks</a></li>
        <li><a href="individualA.php" title="" class="noncurrent">Individual Pages</a></li>
</ul>

<br />
<br />

<ul id="aboutmenu">
        <li><a href="server1.php" title="" class="current">Overall</a></li>
        <li><a href="server2.php" title="" class="noncurrent">Records</a></li>
        <li><a href="server3.php" title="" class="noncurrent">Activity</a></li>
</ul>

<br />
<br />
<br />

<?php 
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";


	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}

$query = "SELECT * FROM generalstatstable";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

$row = mysqli_fetch_row($result);

$NumberOfPlayers = $row[1];
$DifferentOpponentsAverage = $row[2];
$GamesPlayed = $row[3];
$GamesPlayedAverage = $row[4];
$NumberOfDecidedGames = $row[5];
$NumberOfDraws = $row[6];
$DecidedGamesRatio = $row[7];
$DrawsRatio = $row[8];
$GoalsScored = $row[9];
$GoalsPerGameAverage = $row[10];
$DoubleDigits = $row[11];
$CleanSheets = $row[12];
$DoubleDigitsRatio = $row[13];
$CleanSheetsRatio = $row[14];

mysqli_close($con);
?>

<table class="example table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:100 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 


<thead>
    <tr >
    	<th colspan="3"  class="nohovercell" style="text-align:left;">Overall Server Stats</th>
    </tr>
</thead>

<tbody class="black">
	
    <tr style="text-align:left;">
    	<td>Players</td>
        <td style="text-align:right;"><?php echo $NumberOfPlayers ?></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>Games</td>
        <td style="text-align:right;"><?php echo $GamesPlayed ?></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>Goals</td>
        <td style="text-align:right;"><?php echo $GoalsScored; ?></td>
        <td><?php echo " ("; echo number_format($GoalsPerGameAverage, 2); echo" per game)"; ?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
    </tr> 

    <tr style="text-align:left;">
    	<td>Decided Games</td>
        <td style="text-align:right;"><?php echo $NumberOfDecidedGames; ?></td>
        <td><?php echo " ("; echo number_format(100*$DecidedGamesRatio, 1); echo "%)" ?></td>
    </tr> 

    <tr style="text-align:left;">
    	<td>Draws</td>
        <td style="text-align:right;"><?php echo $NumberOfDraws; ?></td>
        <td><?php echo " ("; echo number_format(100*$DrawsRatio, 1); echo "%)" ?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
    </tr> 
    
	<tr style="text-align:left;">
    	<td>Double Digits</td>
        <td style="text-align:right;"><?php echo $DoubleDigits; ?></td>
        <td><?php echo " ("; echo number_format($DoubleDigitsRatio*100, 1); echo" per 100 games)"; ?></td>
    </tr> 
    
	<tr style="text-align:left;">
    	<td>Clean Sheets</td>
        <td style="text-align:right;"><?php echo $CleanSheets; ?></td>
        <td><?php echo " ("; echo number_format($CleanSheetsRatio*100, 1); echo" per 100 games)"; ?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Average Number of Opponents</td>
        <td style="text-align:right;"><?php echo number_format($DifferentOpponentsAverage, 1) ?></td>
        <td></td>
    </tr>    
    
    <tr style="text-align:left;">
    	<td>Average Number of Games</td>
        <td style="text-align:right;"><?php echo number_format($GamesPlayedAverage, 1) ?></td>
        <td></td>
    </tr> 
    
</tbody>

</table> 



</body>
</html>




