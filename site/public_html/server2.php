<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'records';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

<?php 
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";

//mysql_connect(localhost,$username,$password);
//@mysql_select_db($database) or die( "Unable to select database");
	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}

$query = "SELECT * FROM generalstatstable WHERE id = 1 LIMIT 1";
$result = mysqli_query($con, $query) or die("SELECT Error: ".mysqli_error($con));

$row = mysqli_fetch_assoc($result);
if (!$row) {
	die("generalstatstable row id=1 missing");
}

$NumberOfPlayers = $row['NumberOfPlayers'];
$DifferentOpponentsAverage = $row['DifferentOpponentsAverage'];
$GamesPlayed = $row['GamesPlayed'];
$GamesPlayedAverage = $row['GamesPlayedAverage'];
$NumberOfDecidedGames = $row['NumberOfDecidedGames'];
$NumberOfDraws = $row['NumberOfDraws'];
$DecidedGamesRatio = $row['DecidedGamesRatio'];
$DrawsRatio = $row['DrawsRatio'];
$GoalsScored = $row['GoalsScored'];
$GoalsPerGameAverage = $row['GoalsPerGameAverage'];
$DoubleDigits = $row['DoubleDigits'];
$CleanSheets = $row['CleanSheets'];
$DoubleDigitsRatio = $row['DoubleDigitsRatio'];
$CleanSheetsRatio = $row['CleanSheetsRatio'];

$MostGamesPlayed = $row['MostGamesPlayed'];
$MostWins = $row['MostWins'];
$MostGoalsScored = $row['MostGoalsScored'];
$MostGoalsScoredInOneGame = $row['MostGoalsScoredInOneGame'];
$BiggestWinDifference = $row['BiggestWinDifference'];
$BiggestDrawSum = $row['BiggestDrawSum'];
$BiggestSumOfGoals = $row['BiggestSumOfGoals'];
$MostDoubleDigits = $row['MostDoubleDigits'];
$MostCleanSheets = $row['MostCleanSheets'];
$MostDifferentOpponents = $row['MostDifferentOpponents'];
$MostDifferentVictims = $row['MostDifferentVictims'];
$MostDoubleDigitsVictims = $row['MostDoubleDigitsVictims'];
$MostCleanSheetsVictims = $row['MostCleanSheetsVictims'];
$BiggestRatingAscent = $row['BiggestRatingAscent'];
$BiggestPeakRating = $row['BiggestPeakRating'];
$LongestWinningStreak = $row['LongestWinningStreak'];
$LongestDrawingStreak = $row['LongestDrawingStreak'];
$LongestNonLossStreak = $row['LongestNonLossStreak'];

$MostGamesPlayedID = $row['MostGamesPlayedID'];
$MostWinsID = $row['MostWinsID'];
$MostGoalsScoredID = $row['MostGoalsScoredID'];
$MostGoalsScoredInOneGameID = $row['MostGoalsScoredInOneGameID'];
$BiggestWinDifferenceID = $row['BiggestWinDifferenceID'];
$BiggestDrawSumIDA = $row['BiggestDrawSumIDA'];
$BiggestDrawSumIDB = $row['BiggestDrawSumIDB'];
$BiggestSumOfGoalsIDA = $row['BiggestSumOfGoalsIDA'];
$BiggestSumOfGoalsIDB = $row['BiggestSumOfGoalsIDB'];
$MostDoubleDigitsID = $row['MostDoubleDigitsID'];
$MostCleanSheetsID = $row['MostCleanSheetsID'];
$MostDifferentOpponentsID = $row['MostDifferentOpponentsID'];
$MostDifferentVictimsID = $row['MostDifferentVictimsID'];
$MostDoubleDigitsVictimsID = $row['MostDoubleDigitsVictimsID'];
$MostCleanSheetsVictimsID = $row['MostCleanSheetsVictimsID'];
$BiggestRatingAscentID = $row['BiggestRatingAscentID'];
$BiggestPeakRatingID = $row['BiggestPeakRatingID'];
$LongestWinningStreakID = $row['LongestWinningStreakID'];
$LongestDrawingStreakID = $row['LongestDrawingStreakID'];
$LongestNonLossStreakID = $row['LongestNonLossStreakID'];

$MostGamesPlayedName = $row['MostGamesPlayedName'];
$MostWinsName = $row['MostWinsName'];
$MostGoalsScoredName = $row['MostGoalsScoredName'];
$MostGoalsScoredInOneGameName = $row['MostGoalsScoredInOneGameName'];
$BiggestWinDifferenceName = $row['BiggestWinDifferenceName'];
$BiggestDrawSumNameA = $row['BiggestDrawSumNameA'];
$BiggestDrawSumNameB = $row['BiggestDrawSumNameB'];
$BiggestSumOfGoalsNameA = $row['BiggestSumOfGoalsNameA'];
$BiggestSumOfGoalsNameB = $row['BiggestSumOfGoalsNameB'];
$MostDoubleDigitsName = $row['MostDoubleDigitsName'];
$MostCleanSheetsName = $row['MostCleanSheetsName'];
$MostDifferentOpponentsName = $row['MostDifferentOpponentsName'];
$MostDifferentVictimsName = $row['MostDifferentVictimsName'];
$MostDoubleDigitsVictimsName = $row['MostDoubleDigitsVictimsName'];
$MostCleanSheetsVictimsName = $row['MostCleanSheetsVictimsName'];
$BiggestRatingAscentName = $row['BiggestRatingAscentName'];
$BiggestPeakRatingName = $row['BiggestPeakRatingName'];
$LongestWinningStreakName = $row['LongestWinningStreakName'];
$LongestDrawingStreakName = $row['LongestDrawingStreakName'];
$LongestNonLossStreakName = $row['LongestNonLossStreakName'];

$MostGamesPlayedDate = $row['MostGamesPlayedDate'];
$MostWinsDate = $row['MostWinsDate'];
$MostGoalsScoredDate = $row['MostGoalsScoredDate'];
$MostGoalsScoredInOneGameDate = $row['MostGoalsScoredInOneGameDate'];
$BiggestWinDifferenceDate = $row['BiggestWinDifferenceDate'];
$BiggestDrawSumDate = $row['BiggestDrawSumDate'];
$BiggestSumOfGoalsDate = $row['BiggestSumOfGoalsDate'];
$MostDoubleDigitsDate = $row['MostDoubleDigitsDate'];
$MostCleanSheetsDate = $row['MostCleanSheetsDate'];
$MostDifferentOpponentsDate = $row['MostDifferentOpponentsDate'];
$MostDifferentVictimsDate = $row['MostDifferentVictimsDate'];
$MostDoubleDigitsVictimsDate = $row['MostDoubleDigitsVictimsDate'];
$MostCleanSheetsVictimsDate = $row['MostCleanSheetsVictimsDate'];
$BiggestRatingAscentDate = $row['BiggestRatingAscentDate'];
$BiggestPeakRatingDate = $row['BiggestPeakRatingDate'];
$LongestWinningStreakDate = $row['LongestWinningStreakDate'];
$LongestDrawingStreakDate = $row['LongestDrawingStreakDate'];
$LongestNonLossStreakDate = $row['LongestNonLossStreakDate'];

$MostGoalsScoredInOneGameGameID = $row['MostGoalsScoredInOneGameGameID'];
$BiggestWinDifferenceGameID = $row['BiggestWinDifferenceGameID'];
$BiggestDrawSumGameID = $row['BiggestDrawSumGameID'];
$BiggestSumOfGoalsGameID = $row['BiggestSumOfGoalsGameID'];


$phpMostGamesPlayedDate = strtotime( $MostGamesPlayedDate );
$phpMostWinsDate = strtotime( $MostWinsDate ); 
$phpMostGoalsScoredDate = strtotime( $MostGoalsScoredDate );
$phpMostGoalsScoredInOneGameDate = strtotime( $MostGoalsScoredInOneGameDate );
$phpBiggestWinDifferenceDate = strtotime( $BiggestWinDifferenceDate ); 
$phpBiggestDrawSumDate = strtotime( $BiggestDrawSumDate );
$phpBiggestSumOfGoalsDate = strtotime( $BiggestSumOfGoalsDate );
$phpMostDoubleDigitsDate = strtotime( $MostDoubleDigitsDate );
$phpMostCleanSheetsDate = strtotime( $MostCleanSheetsDate );
$phpMostDifferentOpponentsDate = strtotime( $MostDifferentOpponentsDate ); 
$phpMostDifferentVictimsDate = strtotime( $MostDifferentVictimsDate );
$phpMostDoubleDigitsVictimsDate = strtotime( $MostDoubleDigitsVictimsDate ); 
$phpMostCleanSheetsVictimsDate = strtotime( $MostCleanSheetsVictimsDate ); 
$phpBiggestRatingAscentDate = strtotime( $BiggestRatingAscentDate ); 
$phpBiggestPeakRatingDate = strtotime( $BiggestPeakRatingDate );
$phpLongestWinningStreakDate = strtotime( $LongestWinningStreakDate ); 
$phpLongestDrawingStreakDate = strtotime( $LongestDrawingStreakDate ); 
$phpLongestNonLossStreakDate = strtotime( $LongestNonLossStreakDate );

$timeread = time();
$newRecordCutoff = strtotime('-1 month', $timeread);

include $_SERVER["DOCUMENT_ROOT"] . "/includes/records_ratio_leaders.php";
records_load_ratio_leaders($con);

mysqli_close($con);
?>


<div class="k2-table-wrap">

<table class="k2-table"> 

<thead>
    <tr >
    	<th colspan="4"  class="nohovercell" style="text-align:left;">Server Records</th>
    </tr>
</thead>

<tbody class="black">
	
    <tr style="text-align:left;">
        <td>Most games</td>
        <td style="text-align:right;"><?php echo $MostGamesPlayed ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostGamesPlayedID ?>"><?php echo $MostGamesPlayedName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		echo date('M j, Y', $phpMostGamesPlayedDate); 
		if ($phpMostGamesPlayedDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr>    
    
    <tr style="text-align:left;">
        <td>Most wins</td>
        <td style="text-align:right;"><?php if ($MostWins != 0) {echo $MostWins;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostWinsID ?>"><?php echo $MostWinsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostWins != 0) {echo date('M j, Y', $phpMostWinsDate);} 
		if ($phpMostWinsDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Most goals</td>
        <td style="text-align:right;"><?php if ($MostGoalsScored != 0) {echo $MostGoalsScored;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostGoalsScoredID ?>"><?php echo $MostGoalsScoredName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostGoalsScored != 0) {echo date('M j, Y', $phpMostGoalsScoredDate);} 
		if ($phpMostGoalsScoredDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Most double digits</td>
        <td style="text-align:right;"><?php if ($MostDoubleDigits != 0) {echo $MostDoubleDigits;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostDoubleDigitsID ?>"><?php echo $MostDoubleDigitsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php
		if ($MostDoubleDigits != 0) {echo date('M j, Y', $phpMostDoubleDigitsDate);}
		if ($phpMostDoubleDigitsDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Most clean sheets</td>
        <td style="text-align:right;"><?php if ($MostCleanSheets != 0) {echo $MostCleanSheets;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostCleanSheetsID ?>"><?php echo $MostCleanSheetsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php
		if ($MostCleanSheets != 0) {echo date('M j, Y', $phpMostCleanSheetsDate);}
		if ($phpMostCleanSheetsDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Most goals in one game</td>
        <td style="text-align:right;"><?php if ($MostGoalsScoredInOneGame != 0) {echo $MostGoalsScoredInOneGame;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostGoalsScoredInOneGameID ?>"><?php echo $MostGoalsScoredInOneGameName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostGoalsScoredInOneGame != 0) {echo date('M j, Y', $phpMostGoalsScoredInOneGameDate);} 
		if ($phpMostGoalsScoredInOneGameDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Biggest winning margin</td>
        <td style="text-align:right;"><?php if ($BiggestWinDifference != 0) {echo $BiggestWinDifference;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestWinDifferenceID ?>"><?php echo $BiggestWinDifferenceName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($BiggestWinDifference != 0) {echo date('M j, Y', $phpBiggestWinDifferenceDate);} 
		if ($phpBiggestWinDifferenceDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Biggest draw</td>
        <td style="text-align:right;"><?php if ($BiggestDrawSumGameID != 0) {echo $BiggestDrawSum/2; echo "-"; echo $BiggestDrawSum/2;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestDrawSumIDA ?>"><?php echo $BiggestDrawSumNameA ?></a> / <a href="individual1.php?id=<?php echo $BiggestDrawSumIDB ?>"><?php echo $BiggestDrawSumNameB ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($BiggestDrawSumGameID != 0) {echo date('M j, Y', $phpBiggestDrawSumDate);} 
		if ($phpBiggestDrawSumDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Biggest sum of goals</td>
        <td style="text-align:right;"><?php echo $BiggestSumOfGoals ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestSumOfGoalsIDA ?>"><?php echo $BiggestSumOfGoalsNameA ?></a> / <a href="individual1.php?id=<?php echo $BiggestSumOfGoalsIDB ?>"><?php echo $BiggestSumOfGoalsNameB ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		echo date('M j, Y', $phpBiggestSumOfGoalsDate); 
		if ($phpBiggestSumOfGoalsDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Highest peak rating</td>
        <td style="text-align:right;"><?php echo number_format($BiggestPeakRating, 0, '.', '') ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestPeakRatingID ?>"><?php echo $BiggestPeakRatingName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		echo date('M j, Y', $phpBiggestPeakRatingDate);
		if ($phpBiggestPeakRatingDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Longest winning streak</td>
        <td style="text-align:right;"><?php if ($LongestWinningStreak != 0) {echo $LongestWinningStreak;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $LongestWinningStreakID ?>"><?php echo $LongestWinningStreakName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($LongestWinningStreak != 0) {echo date('M j, Y', $phpLongestWinningStreakDate);}
		if ($phpLongestWinningStreakDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Longest undefeated streak</td>
        <td style="text-align:right;"><?php echo $LongestNonLossStreak ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $LongestNonLossStreakID ?>"><?php echo $LongestNonLossStreakName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php
		echo date('M j, Y', $phpLongestNonLossStreakDate);
		if ($phpLongestNonLossStreakDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Longest drawing streak</td>
        <td style="text-align:right;"><?php if ($LongestDrawingStreak != 0) {echo $LongestDrawingStreak;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $LongestDrawingStreakID ?>"><?php echo $LongestDrawingStreakName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php
		if ($LongestDrawingStreak != 0) {echo date('M j, Y', $phpLongestDrawingStreakDate);}
		if ($phpLongestDrawingStreakDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Most opponents</td>
        <td style="text-align:right;"><?php echo $MostDifferentOpponents ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostDifferentOpponentsID ?>"><?php echo $MostDifferentOpponentsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		echo date('M j, Y', $phpMostDifferentOpponentsDate); 
		if ($phpMostDifferentOpponentsDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Most victims</td>
        <td style="text-align:right;"><?php if ($MostDifferentVictims != 0) {echo $MostDifferentVictims;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostDifferentVictimsID ?>"><?php echo $MostDifferentVictimsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostDifferentVictims != 0) {echo date('M j, Y', $phpMostDifferentVictimsDate);} 
		if ($phpMostDifferentVictimsDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Most double digit victims</td>
        <td style="text-align:right;"><?php if ($MostDoubleDigitsVictims != 0) {echo $MostDoubleDigitsVictims;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostDoubleDigitsVictimsID ?>"><?php echo $MostDoubleDigitsVictimsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostDoubleDigitsVictims != 0) {echo date('M j, Y', $phpMostDoubleDigitsVictimsDate);} 
		if ($phpMostDoubleDigitsVictimsDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Most clean sheet victims</td>
        <td style="text-align:right;"><?php if ($MostCleanSheetsVictims != 0) {echo $MostCleanSheetsVictims;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostCleanSheetsVictimsID ?>"><?php echo $MostCleanSheetsVictimsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostCleanSheetsVictims != 0) {echo date('M j, Y', $phpMostCleanSheetsVictimsDate);} 
		if ($phpMostCleanSheetsVictimsDate >= $newRecordCutoff) {echo "<span class='blue'>"; echo " (New!)";};
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Best attack average</td>
        <td style="text-align:right;"><?php if ($BiggestGoalsForAverage != 0) {echo number_format($BiggestGoalsForAverage, 2);} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestGoalsForAverageID ?>"><?php echo $BiggestGoalsForAverageName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Best defense average</td>
        <td style="text-align:right;"><?php if ($SmallestGoalsAgainstAverage != 0) {echo number_format($SmallestGoalsAgainstAverage, 2);} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $SmallestGoalsAgainstAverageID ?>"><?php echo $SmallestGoalsAgainstAverageName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Best goal ratio</td>
        <td style="text-align:right;"><?php if ($BiggestGoalRatio != 0) {echo number_format($BiggestGoalRatio, 2);} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestGoalRatioID ?>"><?php echo $BiggestGoalRatioName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Highest winning frequency</td>
        <td style="text-align:right;"><?php if ($BiggestWinRatio != 0) {echo number_format(100*$BiggestWinRatio, 1); echo "%";} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestWinRatioID ?>"><?php echo $BiggestWinRatioName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Highest double digit frequency</td>
        <td style="text-align:right;"><?php if ($BiggestDoubleDigitsRatio != 0) {echo number_format(100*$BiggestDoubleDigitsRatio, 1); echo "%";} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestDoubleDigitsRatioID ?>"><?php echo $BiggestDoubleDigitsRatioName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
        <td>Highest clean sheet frequency</td>
        <td style="text-align:right;"><?php if ($BiggestCleanSheetsRatio != 0) {echo number_format(100*$BiggestCleanSheetsRatio, 1); echo "%";} else {echo "-";}?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestCleanSheetsRatioID ?>"><?php echo $BiggestCleanSheetsRatioName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr>     
    
</tbody>

</table>

</div><!-- .k2-table-wrap -->

A player must play 30 games for ratios and averages to take effect.
<br />
Records that are less than one month old are displayed as "<span class="blue">(New!)</span>".





</div><!-- .k2-page-nav -->
</body>
</html>




