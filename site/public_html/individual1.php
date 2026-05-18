<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>KOOL Rating</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/thrColFixHdr.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/elolist.js" ></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body>

<div id="container">

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
        <li><a href="individual1.php?id=<?php echo $id ?>" title="" class="current">Profile</a></li>
        <li><a href="individual2a.php?id=<?php echo $id ?>" title="" class="noncurrent">Opponents</a></li>
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

?>

<?php

$query = "SELECT * FROM playertable WHERE id = '$id'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 

$row = mysqli_fetch_assoc($result);
if ($row == null) exit();

$ID = $row['ID'];
$Name = $row['Name'];
//$Email = $row['Email'];
//$CryptPassword = $row[];
$JoinDate = $row['JoinDate'];
$LastLogin = $row['LastLogin'];
$LastGame = $row['LastGame'];
$Display = $row['Display'];
$NumberGames = $row['NumberGames'];
$NumberWins = $row['NumberWins'];
$NumberDraws = $row['NumberDraws'];
$NumberLosses = $row['NumberLosses'];
$WinRatio = $row['WinRatio'];
$DrawRatio = $row['DrawRatio'];
$LossRatio = $row['LossRatio'];
$GoalsFor = $row['GoalsFor'];
$GoalsAgainst = $row['GoalsAgainst'];
$AverageGoalsFor = $row['AverageGoalsFor'];
$AverageGoalsAgainst = $row['AverageGoalsAgainst'];
$GoalRatio = $row['GoalRatio'];
$MostGoalsScored = $row['MostGoalsScored'];
$LeastGoalsScored = $row['LeastGoalsScored'];
$MostGoalsConceded = $row['MostGoalsConceded'];
$LeastGoalsConceded = $row['LeastGoalsConceded'];
$BiggestWinDifference = $row['BiggestWinDifference'];
$BiggestDrawSum = $row['BiggestDrawSum'];
$BiggestLossDifference = $row['BiggestLossDifference'];
$SmallestSumOfGoals = $row['SmallestSumOfGoals'];
$BiggestSumOfGoals = $row['BiggestSumOfGoals'];
$DoubleDigits = $row['DoubleDigits'];
$CleanSheets = $row['CleanSheets'];
$DoubleDigitsConceded = $row['DoubleDigitsConceded'];
$CleanSheetsConceded = $row['CleanSheetsConceded'];
$DoubleDigitsRatio = $row['DoubleDigitsRatio'];
$CleanSheetsRatio = $row['CleanSheetsRatio'];
$DoubleDigitsConcededRatio = $row['DoubleDigitsConcededRatio'];
$CleanSheetsConcededRatio = $row['CleanSheetsConcededRatio'];
$DifferentOpponents = $row['DifferentOpponents'];
$DifferentVictims = $row['DifferentVictims'];
$DoubleDigitsVictims = $row['DoubleDigitsVictims'];
$CleanSheetsVictims = $row['CleanSheetsVictims'];
$MostGoalsConcededVictims = $row['MostGoalsConcededVictims'];
$LeastGoalsScoredVictims = $row['LeastGoalsScoredVictims'];
$BiggestLossVictims = $row['BiggestLossVictims'];
$DifferentCulprits = $row['DifferentCulprits'];
$DoubleDigitsCulprits = $row['DoubleDigitsCulprits'];
$CleanSheetsCulprits = $row['CleanSheetsCulprits'];
$MostGoalsScoredCulprits = $row['MostGoalsScoredCulprits'];
$LeastGoalsConcededCulprits = $row['LeastGoalsConcededCulprits'];
$BiggestWinCulprits = $row['BiggestWinCulprits'];
$SumOfOpponentsRating = $row['SumOfOpponentsRating'];
$AverageOpponentRating = $row['AverageOpponentRating'];
$HighestRatedVictim = $row['HighestRatedVictim'];
$LowestRatedCulprit = $row['LowestRatedCulprit'];
$CurrentRatingAscent = $row['CurrentRatingAscent'];
$BiggestRatingAscent = $row['BiggestRatingAscent'];
$CurrentRatingDescent = $row['CurrentRatingDescent'];
$BiggestRatingDescent = $row['BiggestRatingDescent'];
$LowestRating = $row['LowestRating'];
$PeakRating = $row['PeakRating'];
$RecentAverageRating = $row['RecentAverageRating'];
$Rating = $row['Rating'];
$WinningStreak = $row['WinningStreak'];
$DrawingStreak = $row['DrawingStreak'];
$LosingStreak = $row['LosingStreak'];
$NonWinStreak = $row['NonWinStreak'];
$NonDrawStreak = $row['NonDrawStreak'];
$NonLossStreak = $row['NonLossStreak'];
$LongestWinningStreak = $row['LongestWinningStreak'];
$LongestDrawingStreak = $row['LongestDrawingStreak'];
$LongestLosingStreak = $row['LongestLosingStreak'];
$LongestNonWinStreak = $row['LongestNonWinStreak'];
$LongestNonDrawStreak = $row['LongestNonDrawStreak'];
$LongestNonLossStreak = $row['LongestNonLossStreak'];
$LastGameGameID = $row['LastGameGameID'];
$LastWinGameID = $row['LastWinGameID'];
$LastDrawGameID = $row['LastDrawGameID'];
$LastLossGameID = $row['LastLossGameID'];
$LowestRatingGameID = $row['LowestRatingGameID'];
$PeakRatingGameID = $row['PeakRatingGameID'];
$MostGoalsScoredGameID = $row['MostGoalsScoredGameID'];
$LeastGoalsScoredGameID = $row['LeastGoalsScoredGameID'];
$MostGoalsConcededGameID = $row['MostGoalsConcededGameID'];
$LeastGoalsConcededGameID = $row['LeastGoalsConcededGameID'];
$BiggestWinGameID = $row['BiggestWinGameID'];
$BiggestDrawGameID = $row['BiggestDrawGameID'];
$BiggestLossGameID = $row['BiggestLossGameID'];
$SmallestSumOfGoalsGameID = $row['SmallestSumOfGoalsGameID'];
$BiggestSumOfGoalsGameID = $row['BiggestSumOfGoalsGameID'];
$MostGoalsScoredVictimID = $row['MostGoalsScoredVictimID'];
$LeastGoalsConcededVictimID = $row['LeastGoalsConcededVictimID'];
$BiggestWinVictimID = $row['BiggestWinVictimID'];
$MostGoalsConcededCulpritID = $row['MostGoalsConcededCulpritID'];
$LeastGoalsScoredCulpritID = $row['LeastGoalsScoredCulpritID'];
$BiggestLossCulpritID = $row['BiggestLossCulpritID'];
$HighestRatedVictimGameID = $row['HighestRatedVictimGameID'];
$LowestRatedCulpritGameID = $row['LowestRatedCulpritGameID'];


$query = "SELECT COUNT(*)+1 AS plrank FROM playertable WHERE display = 1 AND rating > (SELECT rating FROM playertable WHERE id='$id')";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);

$rank = $row[0];
?>



  
 
  <div id="sidebar1">
  
    <table class="example table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:100 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount" > 


<thead>
    <tr >
    	<th class="nohovercell" style="text-align:left;" WIDTH="70">&nbsp;</th>
        <th class="nohovercell" style="text-align:left;" WIDTH="170">&nbsp;</th>
    </tr>
</thead>

<tbody class="black">
	
    <tr style="text-align:left;">
    	<td>Name</td>
        <td style="text-align:right;"><?php echo $Name ?></td>
    </tr> 

    <tr style="text-align:left;">
    	<td>Rank</td>
        <td style="text-align:right;"><?php if ($Display == 1) {echo $rank;} else {echo "-";} ?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>Rating</td>
        <td style="text-align:right;"><?php if ($Display == 1) {echo round($Rating);} else {echo "-";} ?></td>
    </tr> 

	<tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
    </tr>
    
	<tr style="text-align:left;">
    	<td>Last Login</td>
        <td style="text-align:right;"><?php echo date('M j, Y', strtotime($LastLogin)) ?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>Last Game</td>
        <td style="text-align:right;">
		<?php 
		if ($Display==1) 
			{echo date('M j, Y', strtotime($LastGame));}
		else 
			{echo "-";}
		?></td>
    </tr> 
    
	<tr style="text-align:left;">
    	<td>Join Date</td>
        <td style="text-align:right;"><?php echo date('M j, Y', strtotime($JoinDate)) ?></td>
    </tr> 
 
</tbody>
</table>    

<br />

<table class="example table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:100 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 


<thead>
    <tr >
    	<th class="nohovercell" style="text-align:left;" WIDTH="160">&nbsp;</th>
        <th class="nohovercell" style="text-align:left;" WIDTH="80"></th>
    </tr>
</thead>

<tbody class="black">
    
    <tr style="text-align:left;">
    	<td>Games</td>
        <td style="text-align:left;">
        <?php
		if ($Display==1) 
			{echo $NumberGames;}
		else
			{echo "0";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Wins</td>
        <td style="text-align:left;">
        <?php
		if ($Display==1) 
			{echo $NumberWins; echo " ("; echo number_format(100*$WinRatio, 1); echo "%)";}
		else
			{echo "-";}
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>Draws</td>
        <td style="text-align:left;">
        <?php
		if ($Display==1) 
			{echo $NumberDraws; echo " ("; echo number_format(100*$DrawRatio, 1); echo "%)";}
		else
			{echo "-";}
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>Losses</td>
        <td style="text-align:left;">
        <?php
		if ($Display==1) 
			{echo $NumberLosses; echo " ("; echo number_format(100*$LossRatio, 1); echo "%)";}
		else
			{echo "-";}
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td style="text-align:left;"></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Goals For</td>
        <td style="text-align:left;">
        <?php
		if ($Display==1) 
			{echo $GoalsFor; echo " ("; echo number_format($AverageGoalsFor, 2); echo ")";}
		else
			{echo "-";}
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>Goals Against</td>
        <td style="text-align:left;">
        <?php
		if ($Display==1) 
			{echo $GoalsAgainst; echo " ("; echo number_format($AverageGoalsAgainst, 2); echo ")";}
		else
			{echo "-";}
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>Goal Ratio</td>
        <td style="text-align:left;">
        <?php
		if ($Display==1) 
			{echo number_format($GoalRatio, 2);}
		else
			{echo "-";}
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td style="text-align:left;"></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Double Digits</td>
        <td style="text-align:left;">
        <?php
		if ($Display==1) 
			{echo $DoubleDigits; echo " ("; echo number_format(100*$DoubleDigitsRatio, 1); echo "%)";}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Clean Sheets</td>
        <td style="text-align:left;">
        <?php
		if ($Display==1) 
			{echo $CleanSheets; echo " ("; echo number_format(100*$CleanSheetsRatio, 1); echo "%)";}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Double Digits Conceded</td>
        <td style="text-align:left;">
        <?php
		if ($Display==1) 
			{echo $DoubleDigitsConceded; echo " ("; echo number_format(100*$DoubleDigitsConcededRatio, 1); echo "%)";}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Clean Sheets Conceded</td>
        <td style="text-align:left;">
        <?php
		if ($Display==1) 
			{echo $CleanSheetsConceded; echo " ("; echo number_format(100*$CleanSheetsConceded, 1); echo "%)";}
		else
			{echo "-";}
		?></td>
    </tr>

</tbody>
</table> 

<br />

<table class="example table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:100 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount" > 
<thead>
    <tr >
    	<th class="nohovercell" style="text-align:left;" WIDTH="172">&nbsp;</th>
        <th class="nohovercell" style="text-align:left;" WIDTH="68">&nbsp;</th>
    </tr>
</thead>

 	<tr style="text-align:left;">
    	<td>Average Opponent Rating</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo round($AverageOpponentRating);}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>
        <?php
		if ($CurrentRatingDescent!=0) 
			{echo "Current Rating Descent";}
		else 
			{echo "Current Rating Ascent";}
		?></td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $CurrentRatingDescent!=0) 
			{echo "-"; echo number_format($CurrentRatingDescent, 1, '.', '');}
		elseif ($Display==1)
			{echo "+"; echo number_format($CurrentRatingAscent, 1, '.', '');}
		else
			{echo "-";}
		?></td>
    </tr>
    
    	<?php
		if ($CurrentRatingDescent!=0) 
    {
		?><tr style="text-align:left;">
			<td>Biggest Rating Descent</td>
			<td style="text-align:right;">
			<?php
			if ($Display==1 && $BiggestRatingDescent!=0) 
				{echo "-"; echo number_format($BiggestRatingDescent, 1, '.', '');}
			else
				{echo "-";}
			?></td>
		</tr>
		
		<tr style="text-align:left;">
			<td>Biggest Rating Ascent</td>
			<td style="text-align:right;">
			<?php
			if ($Display==1 && $BiggestRatingAscent!=0) 
				{echo "+"; echo number_format($BiggestRatingAscent, 1, '.', '');}
			else
				{echo "-";}
			?></td>
		</tr><?php ;
	}	
		else
	{
		?><tr style="text-align:left;">
			<td>Biggest Rating Ascent</td>
			<td style="text-align:right;">
			<?php
			if ($Display==1 && $BiggestRatingAscent!=0) 
				{echo "+"; echo number_format($BiggestRatingAscent, 1, '.', '');}
			else
				{echo "-";}
			?></td>
		</tr>
    
    	<tr style="text-align:left;">
			<td>Biggest Rating Descent</td>
			<td style="text-align:right;">
			<?php
			if ($Display==1 && $BiggestRatingDescent!=0) 
				{echo "-"; echo number_format($BiggestRatingDescent, 1, '.', '');}
			else
				{echo "-";}
			?></td>
		</tr><?php ;
	}?>
    
    <tr style="text-align:left;">
    	<td>Recent Avg. Rating (30 games)</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo round($RecentAverageRating);}
		else
			{echo "-";}
		?></td>
    </tr>
 
<?php
$query = "SELECT Date FROM ratedresults WHERE id = '$LowestRatingGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>
   
    <tr style="text-align:left;">
    	<td>Rating Nadir <?php if ($Display==1 && $LowestRating!=5000) {echo " ("; echo date('M j, Y', strtotime($row[0])); echo ")";}?> </td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $LowestRating!=5000) 
			{echo round($LowestRating);}
		else
			{echo "-";}
		?></td>
    </tr>

<?php
$query = "SELECT Date FROM ratedresults WHERE id = '$PeakRatingGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>
    
    <tr style="text-align:left;">
    	<td>Peak Rating <?php if ($Display==1 && $PeakRating!=0) {echo " ("; echo date('M j, Y', strtotime($row[0])); echo ")";}?></td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $PeakRating!=0) 
			{echo round($PeakRating);}
		else
			{echo "-";}
		?></td>
    </tr>  
    </tbody>
    </table> 

 
    
  <!-- end #sidebar1 --></div>
  <div id="sidebar2">




<table class="example table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:100 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 
<thead>
    <tr >
    	<th class="nohovercell" style="text-align:left;" WIDTH="150">&nbsp;</th>
        <th class="nohovercell" WIDTH="66"></th>
    </tr>
</thead>
<tbody class="black">
    
    <tr style="text-align:left;">
    	<td>Winning Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $WinningStreak!=0) 
			{echo $WinningStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Longest Winning Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $LongestWinningStreak!=0) 
			{echo $LongestWinningStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Drawing Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $DrawingStreak!=0) 
			{echo $DrawingStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Longest Drawing Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $LongestDrawingStreak!=0) 
			{echo $LongestDrawingStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Losing Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $LosingStreak!=0) 
			{echo $LosingStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Longest Losing Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $LongestLosingStreak!=0) 
			{echo $LongestLosingStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr>
    	<td>&nbsp;</td>
    	<td></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>No Loss Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $NonLossStreak!=0) 
			{echo $NonLossStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Longest No Loss Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $LongestNonLossStreak!=0) 
			{echo $LongestNonLossStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>No Draw Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $NonDrawStreak!=0) 
			{echo $NonDrawStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Longest No Draw Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $LongestNonDrawStreak!=0) 
			{echo $LongestNonDrawStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>No Win Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $NonWinStreak!=0) 
			{echo $NonWinStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Longest No Win Streak</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1 && $LongestNonWinStreak!=0) 
			{echo $LongestNonWinStreak;}
		else
			{echo "-";}
		?></td>
    </tr>
    
</tbody>
</table>


<br />


  
<table class="example table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:100 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount" > 
<thead>
    <tr >
    	<th class="nohovercell" style="text-align:left;" WIDTH="176">&nbsp;</th>
        <th class="nohovercell" style="text-align:left;" WIDTH="40" >&nbsp;</th>
    </tr>
</thead>
<tbody class="black">

	<tr style="text-align:left;">
    	<td>Different Opponents</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $DifferentOpponents;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Victims</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $DifferentVictims;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Culprits</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $DifferentCulprits;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Double Digit Victims</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $DoubleDigitsVictims;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Double Digit Culprits</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $DoubleDigitsCulprits;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Clean Sheet Victims</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $CleanSheetsVictims;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Clean Sheet Culprits</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $CleanSheetsCulprits;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Biggest Loss Victims</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $BiggestLossVictims;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Biggest Win Culprits</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $BiggestWinCulprits;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Most Goals Conceded Victims</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $MostGoalsConcededVictims;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Most Goals Scored Culprits</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $MostGoalsScoredCulprits;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Least Goals Scored Victims</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $LeastGoalsScoredVictims;}
		else
			{echo "-";}
		?></td>
    </tr>
    
    <tr style="text-align:left;">
    	<td>Least Goals Conceded Culprits</td>
        <td style="text-align:right;">
        <?php
		if ($Display==1) 
			{echo $LeastGoalsConcededCulprits;}
		else
			{echo "-";}
		?></td>
    </tr>

</tbody>
    </table>  
 
    
  <!-- end #sidebar2 --></div>
  <div id="mainContent">
  

<table class="example table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-autopage:100 table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 
<thead>
    <tr >
    	<th class="nohovercell" style="text-align:left;" WIDTH="120">&nbsp;</th>
        <th class="nohovercell" style="text-align:left;" WIDTH="200">&nbsp;</th>
        <th class="nohovercell" style="text-align:left;" WIDTH="90"></th>
    </tr>
</thead>
<tbody class="black">

<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$LastWinGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>   
    
    <tr style="text-align:left;">
    	<td>Last Win</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $LastWinGameID!=0) 
			{?><a href="game.php?id=<?php echo $LastWinGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php } 
		elseif ($Display==1 && $LastWinGameID!=0)
			{?><a href="game.php?id=<?php echo $LastWinGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($LastWinGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$LastDrawGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Last Draw</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $LastDrawGameID!=0) 
			{?><a href="game.php?id=<?php echo $LastDrawGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php }
		elseif ($Display==1 && $LastDrawGameID!=0)
			{?><a href="game.php?id=<?php echo $LastDrawGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($LastDrawGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$LastLossGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>
    
    <tr style="text-align:left;">
    	<td>Last Loss</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $LastLossGameID!=0) 
			{?><a href="game.php?id=<?php echo $LastLossGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php }
		elseif ($Display==1 && $LastLossGameID!=0)
			{?><a href="game.php?id=<?php echo $LastLossGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($LastLossGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
    <tr>
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$BiggestWinGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Biggest Win</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $BiggestWinGameID!=0) 
			{?><a href="game.php?id=<?php echo $BiggestWinGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php }
		elseif ($Display==1 && $BiggestWinGameID!=0)
			{?><a href="game.php?id=<?php echo $BiggestWinGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($BiggestWinGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$MostGoalsScoredGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Most For</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $MostGoalsScoredGameID!=0) 
			{?><a href="game.php?id=<?php echo $MostGoalsScoredGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php }
		elseif ($Display==1 && $MostGoalsScoredGameID!=0)
			{?><a href="game.php?id=<?php echo $MostGoalsScoredGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($MostGoalsScoredGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$LeastGoalsConcededGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Least Against</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $LeastGoalsConcededGameID!=0) 
			{?><a href="game.php?id=<?php echo $LeastGoalsConcededGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php }
		elseif ($Display==1 && $LeastGoalsConcededGameID!=0)
			{?><a href="game.php?id=<?php echo $LeastGoalsConcededGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($LeastGoalsConcededGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
    <tr>
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$BiggestLossGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Biggest Loss</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $BiggestLossGameID!=0) 
			{?><a href="game.php?id=<?php echo $BiggestLossGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php }
		elseif ($Display==1 && $BiggestLossGameID!=0)
			{?><a href="game.php?id=<?php echo $BiggestLossGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($BiggestLossGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$MostGoalsConcededGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Most Against</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $MostGoalsConcededGameID!=0) 
			{?><a href="game.php?id=<?php echo $MostGoalsConcededGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php }
		elseif ($Display==1 && $MostGoalsConcededGameID!=0)
			{?><a href="game.php?id=<?php echo $MostGoalsConcededGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($MostGoalsConcededGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$LeastGoalsScoredGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Least For</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $LeastGoalsScoredGameID!=0) 
			{?><a href="game.php?id=<?php echo $LeastGoalsScoredGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php }
		elseif ($Display==1 && $LeastGoalsScoredGameID!=0)
			{?><a href="game.php?id=<?php echo $LeastGoalsScoredGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($LeastGoalsScoredGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
    <tr>
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$BiggestDrawGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Biggest Draw</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $BiggestDrawGameID!=0) 
			{?><a href="game.php?id=<?php echo $BiggestDrawGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php }
		elseif ($Display==1 && $BiggestDrawGameID!=0)
			{?><a href="game.php?id=<?php echo $BiggestDrawGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($BiggestDrawGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$BiggestSumOfGoalsGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Biggest Sum</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $BiggestSumOfGoalsGameID!=0) 
			{?><a href="game.php?id=<?php echo $BiggestSumOfGoalsGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php }
		elseif ($Display==1 && $BiggestSumOfGoalsGameID!=0)
			{?><a href="game.php?id=<?php echo $BiggestSumOfGoalsGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($BiggestSumOfGoalsGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB FROM ratedresults WHERE id = '$SmallestSumOfGoalsGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Smallest Sum</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $SmallestSumOfGoalsGameID!=0) 
			{?><a href="game.php?id=<?php echo $SmallestSumOfGoalsGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a><?php }
		elseif ($Display==1 && $SmallestSumOfGoalsGameID!=0)
			{?><a href="game.php?id=<?php echo $SmallestSumOfGoalsGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a><?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($SmallestSumOfGoalsGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
    <tr>
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB, RatingA, RatingB FROM ratedresults WHERE id = '$HighestRatedVictimGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Max Rated Victim</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $HighestRatedVictimGameID!=0) 
			{?><a href="game.php?id=<?php echo $HighestRatedVictimGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a> (<?php echo round($row[8]);?>)<?php }
		elseif ($Display==1 && $HighestRatedVictimGameID!=0)
			{?><a href="game.php?id=<?php echo $HighestRatedVictimGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a> (<?php echo round($row[7]);?>)<?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($HighestRatedVictimGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr> 
    
<?php
$query = "SELECT Date, idA, idB, NameA, NameB, GoalsA, GoalsB, RatingA, RatingB FROM ratedresults WHERE id = '$LowestRatedCulpritGameID'";
$result = mysqli_query($con,$query) or die("SELECT Error: ".mysqli_error($con)); 
$row = mysqli_fetch_row($result);
?>

    <tr style="text-align:left;">
    	<td>Min Rated Culprit</td>
        <td style="text-align:left;">
        <?php
		if ($id==$row[1] && $Display==1 && $LowestRatedCulpritGameID!=0) 
			{?><a href="game.php?id=<?php echo $LowestRatedCulpritGameID?>"><?php echo $row[5]; echo "-"; echo $row[6];?></a> vs. <a href="individual1.php?id=<?php echo $row[2]?>"><?php echo $row[4];?></a> (<?php echo round($row[8]);?>)<?php }
		elseif ($Display==1 && $LowestRatedCulpritGameID!=0)
			{?><a href="game.php?id=<?php echo $LowestRatedCulpritGameID?>"><?php echo $row[6]; echo "-"; echo $row[5];?></a> vs. <a href="individual1.php?id=<?php echo $row[1]?>"><?php echo $row[3];?></a> (<?php echo round($row[7]);?>)<?php }
        else 
			{echo "-";}
		?>
        </td>
        <td style="text-align:right;"><?php if ($LowestRatedCulpritGameID!=0) {echo date('M j, Y', strtotime($row[0]));}?></td>
    </tr>


</tbody>

</table>


    
    &nbsp;
    
  <!-- end #mainContent --></div>
	<!-- This clearing element should immediately follow the #mainContent div in order to force the #container div to contain all child floats --><br class="clearfloat" />
  
<!-- end #container --></div>
  

</body>
</html>
