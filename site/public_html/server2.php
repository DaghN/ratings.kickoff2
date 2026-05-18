<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>KOOL Rating</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/elolist.js" ></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body>

<br />

<ul id="aboutmenu">
        <li><a href="#" title="" class="current">Server Stats</a></li>
        <li><a href="ranked1.php" title="" class="noncurrent">Player Ranks</a></li>
        <?php $playerSearchAsNavItem = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_search_bar.php"; ?>
</ul>

<br />
<br />

<ul id="aboutmenu">
        <li><a href="server1.php" title="" class="noncurrent">Overall</a></li>
        <li><a href="server2.php" title="" class="current">Records</a></li>
        <li><a href="server3.php" title="" class="noncurrent">Activity</a></li>
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

$MostGamesPlayed = $row[15];
$MostWins = $row[16];
$BiggestWinRatio = $row[17];
$MostGoalsScored = $row[18];
$BiggestGoalsForAverage = $row[19];
$SmallestGoalsAgainstAverage = $row[20];
$BiggestGoalRatio = $row[21];
$MostGoalsScoredInOneGame = $row[22];
$BiggestWinDifference = $row[23];
$BiggestDrawSum = $row[24];
$BiggestSumOfGoals = $row[25];
$MostDoubleDigits = $row[26];
$MostCleanSheets = $row[27];
$BiggestDoubleDigitsRatio = $row[28];
$BiggestCleanSheetsRatio = $row[29];
$MostDifferentOpponents = $row[30];
$MostDifferentVictims = $row[31];
$MostDoubleDigitsVictims = $row[32];
$MostCleanSheetsVictims = $row[33];
$BiggestAverageOpponentRating = $row[34];
$BiggestRatingAscent = $row[35];
$BiggestPeakRating = $row[36];
$LongestWinningStreak = $row[37];
$LongestDrawingStreak = $row[38];
$LongestNonLossStreak = $row[39];

$MostGamesPlayedID = $row[40];
$MostWinsID = $row[41];
$BiggestWinRatioID = $row[42];
$MostGoalsScoredID = $row[43];
$BiggestGoalsForAverageID = $row[44];
$SmallestGoalsAgainstAverageID = $row[45];
$BiggestGoalRatioID = $row[46];
$MostGoalsScoredInOneGameID = $row[47];
$BiggestWinDifferenceID = $row[48];
$BiggestDrawSumIDA = $row[49];
$BiggestDrawSumIDB = $row[50];
$BiggestSumOfGoalsIDA = $row[51];
$BiggestSumOfGoalsIDB = $row[52];

$MostDoubleDigitsID = $row[53];
$MostCleanSheetsID = $row[54];
$BiggestDoubleDigitsRatioID = $row[55];
$BiggestCleanSheetsRatioID = $row[56];

$MostDifferentOpponentsIDB = $row[57];
$MostDifferentVictimsID = $row[58];
$MostDoubleDigitsVictimsID = $row[59];
$MostCleanSheetsVictimsID = $row[60];
$BiggestAverageOpponentRatingID = $row[61];
$BiggestRatingAscentID = $row[62];
$BiggestPeakRatingID = $row[63];
$LongestWinningStreakID = $row[64];
$LongestDrawingStreakID = $row[65];
$LongestNonLossStreakID = $row[66];

$MostGamesPlayedName = $row[67];
$MostWinsName = $row[68];
$BiggestWinRatioName = $row[69];
$MostGoalsScoredName = $row[70];
$BiggestGoalsForAverageName = $row[71];
$SmallestGoalsAgainstAverageName = $row[72];
$BiggestGoalRatioName = $row[73];
$MostGoalsScoredInOneGameName = $row[74];
$BiggestWinDifferenceName = $row[75];
$BiggestDrawSumNameA = $row[76];
$BiggestDrawSumNameB = $row[77];
$BiggestSumOfGoalsNameA = $row[78];
$BiggestSumOfGoalsNameB = $row[79];
$MostDoubleDigitsName = $row[80];
$MostCleanSheetsName = $row[81];
$BiggestDoubleDigitsRatioName = $row[82];
$BiggestCleanSheetsRatioName = $row[83];
$MostDifferentOpponentsName = $row[84];
$MostDifferentVictimsName = $row[85];
$MostDoubleDigitsVictimsName = $row[86];
$MostCleanSheetsVictimsName = $row[87];
$BiggestAverageOpponentRatingName = $row[88];
$BiggestRatingAscentName = $row[89];
$BiggestPeakRatingName = $row[90];
$LongestWinningStreakName = $row[91];
$LongestDrawingStreakName = $row[92];
$LongestNonLossStreakName = $row[93];

$MostGamesPlayedDate = $row[94];
$MostWinsDate = $row[95];
$BiggestWinRatioDate = $row[96];
$MostGoalsScoredDate = $row[97];
$BiggestGoalsForAverageDate = $row[98];
$SmallestGoalsAgainstAverageDate = $row[99];
$BiggestGoalRatioDate = $row[100];
$MostGoalsScoredInOneGameDate = $row[101];
$BiggestWinDifferenceDate = $row[102];
$BiggestDrawSumDate = $row[103];
$BiggestSumOfGoalsDate = $row[104];
$MostDoubleDigitsDate = $row[105];
$MostCleanSheetsDate = $row[106];
$BiggestDoubleDigitsRatioDate = $row[107];
$BiggestCleanSheetsRatioDate = $row[108];
$MostDifferentOpponentsDate = $row[109];
$MostDifferentVictimsDate = $row[110];
$MostDoubleDigitsVictimsDate = $row[111];
$MostCleanSheetsVictimsDate = $row[112];
$BiggestAverageOpponentRatingDate = $row[113];
$BiggestRatingAscentDate = $row[114];
$BiggestPeakRatingDate = $row[115];
$LongestWinningStreakDate = $row[116];
$LongestDrawingStreakDate = $row[117];
$LongestNonLossStreakDate = $row[118];

$MostGoalsScoredInOneGameGameID = $row[119];
$BiggestWinDifferenceGameID = $row[120];
$BiggestDrawSumGameID = $row[121];
$BiggestSumOfGoalsGameID = $row[122];


$phpMostGamesPlayedDate = strtotime( $MostGamesPlayedDate );
$phpMostWinsDate = strtotime( $MostWinsDate ); 
$phpBiggestWinRatioDate = strtotime( $BiggestWinRatioDate );
$phpMostGoalsScoredDate = strtotime( $MostGoalsScoredDate );
$phpBiggestGoalsForAverageDate = strtotime( $BiggestGoalsForAverageDate ); 
$phpSmallestGoalsAgainstAverageDate = strtotime( $SmallestGoalsAgainstAverageDate );
$phpBiggestGoalRatioDate = strtotime( $BiggestGoalRatioDate );
$phpMostGoalsScoredInOneGameDate = strtotime( $MostGoalsScoredInOneGameDate );
$phpBiggestWinDifferenceDate = strtotime( $BiggestWinDifferenceDate ); 
$phpBiggestDrawSumDate = strtotime( $BiggestDrawSumDate );
$phpBiggestSumOfGoalsDate = strtotime( $BiggestSumOfGoalsDate );
$phpMostDoubleDigitsDate = strtotime( $MostDoubleDigitsDate );
$phpMostCleanSheetsDate = strtotime( $MostCleanSheetsDate );
$phpBiggestDoubleDigitsRatioDate = strtotime( $BiggestDoubleDigitsRatioDate );
$phpBiggestCleanSheetsRatioDate = strtotime( $BiggestCleanSheetsRatioDate );
$phpMostDifferentOpponentsDate = strtotime( $MostDifferentOpponentsDate ); 
$phpMostDifferentVictimsDate = strtotime( $MostDifferentVictimsDate );
$phpMostDoubleDigitsVictimsDate = strtotime( $MostDoubleDigitsVictimsDate ); 
$phpMostCleanSheetsVictimsDate = strtotime( $MostCleanSheetsVictimsDate ); 
$phpBiggestAverageOpponentRatingDate = strtotime( $BiggestAverageOpponentRatingDate ); 
$phpBiggestRatingAscentDate = strtotime( $BiggestRatingAscentDate ); 
$phpBiggestPeakRatingDate = strtotime( $BiggestPeakRatingDate );
$phpLongestWinningStreakDate = strtotime( $LongestWinningStreakDate ); 
$phpLongestDrawingStreakDate = strtotime( $LongestDrawingStreakDate ); 
$phpLongestNonLossStreakDate = strtotime( $LongestNonLossStreakDate );

$timeread = time();

mysqli_close($con);
?>


<table class="example table-autofilter table-stripeclass:alternate table-autostripe table-rowshade-alternate table-page-number:tablepage table-page-count:tablepages table-filtered-rowcount:tablefiltercount table-rowcount:tableallcount"> 

<thead>
    <tr >
    	<th colspan="4"  class="nohovercell" style="text-align:left;">Server Records</th>
    </tr>
</thead>

<tbody class="black">
	
    <tr style="text-align:left;">
    	<td><a href="">Most Games</a></td>
        <td style="text-align:right;"><?php echo $MostGamesPlayed ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostGamesPlayedID ?>"><?php echo $MostGamesPlayedName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		echo date('M j, Y', $phpMostGamesPlayedDate); 
		if ($timeread - $phpMostGamesPlayedDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr>    
    
    <tr style="text-align:left;">
    	<td><a href="#">Most Wins</a></td>
        <td style="text-align:right;"><?php if ($MostWins != 0) {echo $MostWins;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostWinsID ?>"><?php echo $MostWinsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostWins != 0) {echo date('M j, Y', $phpMostWinsDate);} 
		if ($timeread - $phpMostWinsDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Best Win Ratio</a></td>
        <td style="text-align:right;"><?php if ($BiggestWinRatio != 0) {echo number_format(100*$BiggestWinRatio, 1); echo "%";} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestWinRatioID ?>"><?php echo $BiggestWinRatioName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Most Goals</a></td>
        <td style="text-align:right;"><?php if ($MostGoalsScored != 0) {echo $MostGoalsScored;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostGoalsScoredID ?>"><?php echo $MostGoalsScoredName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostGoalsScored != 0) {echo date('M j, Y', $phpMostGoalsScoredDate);} 
		if ($timeread - $phpMostGoalsScoredDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Best Attack Average</a></td>
        <td style="text-align:right;"><?php if ($BiggestGoalsForAverage != 0) {echo number_format($BiggestGoalsForAverage, 2);} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestGoalsForAverageID ?>"><?php echo $BiggestGoalsForAverageName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Best Defense Average</a></td>
        <td style="text-align:right;"><?php if ($SmallestGoalsAgainstAverage != 0) {echo number_format($SmallestGoalsAgainstAverage, 2);} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $SmallestGoalsAgainstAverageID ?>"><?php echo $SmallestGoalsAgainstAverageName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Best Goal Ratio</a></td>
        <td style="text-align:right;"><?php if ($BiggestGoalRatio != 0) {echo number_format($BiggestGoalRatio, 2);} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestGoalRatioID ?>"><?php echo $BiggestGoalRatioName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Most Goals in One Game</a></td>
        <td style="text-align:right;"><?php if ($MostGoalsScoredInOneGame != 0) {echo $MostGoalsScoredInOneGame;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostGoalsScoredInOneGameID ?>"><?php echo $MostGoalsScoredInOneGameName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostGoalsScoredInOneGame != 0) {echo date('M j, Y', $phpMostGoalsScoredInOneGameDate);} 
		if ($timeread - $phpMostGoalsScoredInOneGameDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Biggest Winning Margin</a></td>
        <td style="text-align:right;"><?php if ($BiggestWinDifference != 0) {echo $BiggestWinDifference;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestWinDifferenceID ?>"><?php echo $BiggestWinDifferenceName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($BiggestWinDifference != 0) {echo date('M j, Y', $phpBiggestWinDifferenceDate);} 
		if ($timeread - $phpBiggestWinDifferenceDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Biggest Draw</a></td>
        <td style="text-align:right;"><?php if ($BiggestDrawSumGameID != 0) {echo $BiggestDrawSum/2; echo "-"; echo $BiggestDrawSum/2;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestDrawSumIDA ?>"><?php echo $BiggestDrawSumNameA ?></a> / <a href="individual1.php?id=<?php echo $BiggestDrawSumIDB ?>"><?php echo $BiggestDrawSumNameB ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($BiggestDrawSumGameID != 0) {echo date('M j, Y', $phpBiggestDrawSumDate);} 
		if ($timeread - $phpBiggestDrawSumDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Biggest Sum of Goals</a></td>
        <td style="text-align:right;"><?php echo $BiggestSumOfGoals ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestSumOfGoalsIDA ?>"><?php echo $BiggestSumOfGoalsNameA ?></a> / <a href="individual1.php?id=<?php echo $BiggestSumOfGoalsIDB ?>"><?php echo $BiggestSumOfGoalsNameB ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		echo date('M j, Y', $phpBiggestSumOfGoalsDate); 
		if ($timeread - $phpBiggestSumOfGoalsDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Most Double Digits</a></td>
        <td style="text-align:right;"><?php if ($MostDoubleDigits != 0) {echo $MostDoubleDigits;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostDoubleDigitsID ?>"><?php echo $MostDoubleDigitsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostDoubleDigits != 0) {echo date('M j, Y', $phpMostDoubleDigitsDate);} 
		if ($timeread - $phpMostDoubleDigitsDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Most Clean Sheets</a></td>
        <td style="text-align:right;"><?php if ($MostCleanSheets != 0) {echo $MostCleanSheets;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostCleanSheetsID ?>"><?php echo $MostCleanSheetsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostCleanSheets != 0) {echo date('M j, Y', $phpMostCleanSheetsDate);} 
		if ($timeread - $phpMostCleanSheetsDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Best Double Digit Ratio</a></td>
        <td style="text-align:right;"><?php if ($BiggestDoubleDigitsRatio != 0) {echo number_format(100*$BiggestDoubleDigitsRatio, 1); echo "%";} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestDoubleDigitsRatioID ?>"><?php echo $BiggestDoubleDigitsRatioName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Best Clean Sheet Ratio</a></td>
        <td style="text-align:right;"><?php if ($BiggestCleanSheetsRatio != 0) {echo number_format(100*$BiggestCleanSheetsRatio, 1); echo "%";} else {echo "-";}?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestCleanSheetsRatioID ?>"><?php echo $BiggestCleanSheetsRatioName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Most Different Opponents</a></td>
        <td style="text-align:right;"><?php echo $MostDifferentOpponents ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostDifferentOpponentsIDB ?>"><?php echo $MostDifferentOpponentsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		echo date('M j, Y', $phpMostDifferentOpponentsDate); 
		if ($timeread - $phpMostDifferentOpponentsDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Most Different Victims</a></td>
        <td style="text-align:right;"><?php if ($MostDifferentVictims != 0) {echo $MostDifferentVictims;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostDifferentVictimsID ?>"><?php echo $MostDifferentVictimsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostDifferentVictims != 0) {echo date('M j, Y', $phpMostDifferentVictimsDate);} 
		if ($timeread - $phpMostDifferentVictimsDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Most Double Digit Victims</a></td>
        <td style="text-align:right;"><?php if ($MostDoubleDigitsVictims != 0) {echo $MostDoubleDigitsVictims;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostDoubleDigitsVictimsID ?>"><?php echo $MostDoubleDigitsVictimsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostDoubleDigitsVictims != 0) {echo date('M j, Y', $phpMostDoubleDigitsVictimsDate);} 
		if ($timeread - $phpMostDoubleDigitsVictimsDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Most Clean Sheet Victims</a></td>
        <td style="text-align:right;"><?php if ($MostCleanSheetsVictims != 0) {echo $MostCleanSheetsVictims;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $MostCleanSheetsVictimsID ?>"><?php echo $MostCleanSheetsVictimsName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($MostCleanSheetsVictims != 0) {echo date('M j, Y', $phpMostCleanSheetsVictimsDate);} 
		if ($timeread - $phpMostCleanSheetsVictimsDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Biggest Average Opponent Rating</a>&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php if ($BiggestAverageOpponentRating != 0) {echo round($BiggestAverageOpponentRating);} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestAverageOpponentRatingID ?>"><?php echo $BiggestAverageOpponentRatingName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;">-</td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Biggest Rating Ascent</a></td>
        <td style="text-align:right;"><?php echo "+"; echo number_format($BiggestRatingAscent, 1) ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestRatingAscentID ?>"><?php echo $BiggestRatingAscentName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		echo date('M j, Y', $phpBiggestRatingAscentDate); 
		if ($timeread - $phpBiggestRatingAscentDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Highest Peak Rating</a></td>
        <td style="text-align:right;"><?php echo number_format($BiggestPeakRating, 0, '.', '') ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $BiggestPeakRatingID ?>"><?php echo $BiggestPeakRatingName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		echo date('M j, Y', $phpBiggestPeakRatingDate); 
		if ($timeread - $phpBiggestPeakRatingDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td>&nbsp;</td>
        <td></td>
        <td></td>
        <td></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Longest Winning Streak</a></td>
        <td style="text-align:right;"><?php if ($LongestWinningStreak != 0) {echo $LongestWinningStreak;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $LongestWinningStreakID ?>"><?php echo $LongestWinningStreakName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($LongestWinningStreak != 0) {echo date('M j, Y', $phpLongestWinningStreakDate);} 
		if ($timeread - $phpLongestWinningStreakDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Longest Undefeated Streak</a></td>
        <td style="text-align:right;"><?php echo $LongestNonLossStreak ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $LongestNonLossStreakID ?>"><?php echo $LongestNonLossStreakName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		echo date('M j, Y', $phpLongestNonLossStreakDate); 
		if ($timeread - $phpLongestNonLossStreakDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr> 
    
    <tr style="text-align:left;">
    	<td><a href="#">Longest Drawing Streak</a></td>
        <td style="text-align:right;"><?php if ($LongestDrawingStreak != 0) {echo $LongestDrawingStreak;} else {echo "-";} ?></td>
        <td>&nbsp;&nbsp;&nbsp;<a href="individual1.php?id=<?php echo $LongestDrawingStreakID ?>"><?php echo $LongestDrawingStreakName ?></a>&nbsp;&nbsp;&nbsp;</td>
        <td style="text-align:right;"><?php 
		if ($LongestDrawingStreak != 0) {echo date('M j, Y', $phpLongestDrawingStreakDate);} 
		if ($timeread - $phpLongestDrawingStreakDate < 2 * 24 * 60 * 60) {echo "<span class='blue'>"; echo " (New!)";}; 
		?></td>
    </tr>     
    
</tbody>

</table> 

<br />
A player must play 30 games for ratios and averages to take effect.
<br />
Records that are less than 48 hours old are displayed as "(New!)". 




</body>
</html>




