<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<link href="stylesheets/main2.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/elolist.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/theme.css" rel="stylesheet" type="text/css" />
<script src="js/chart.umd.min.js"></script>
<script src="js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="js/chart-theme.js"></script>
<script type="text/javascript" src="js/elolist.js" ></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>
<script type="text/javascript" src="js/server-games-month-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-games-year-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-goals-month-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-active-players-month-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-established-players-year-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-cumulative-established-month-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-established-rating-distribution-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-peak-month-leaderboard.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'trends';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

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

<div class="server-games-month-chart">
    <p style="margin: 0 0 4px 0; color: var(--color-text-primary, #e3e3e3);">Server games per month</p>
    <p class="server-games-month-chart-status" style="margin: 0 0 8px 0;">Loading server games per month…</p>
    <canvas width="960" height="271" aria-label="Server rated games per calendar month"></canvas>
</div>

<div class="server-games-year-chart">
    <p style="margin: 0 0 4px 0; color: var(--color-text-primary, #e3e3e3);">Server games per year</p>
    <p style="margin: 0 0 4px 0; color: var(--color-text-muted, #b0b0b0); font-size: 0.9em;">Current year: games so far (green) plus projected rest of year if pace continues (blue).</p>
    <p class="server-games-year-chart-status" style="margin: 0 0 8px 0;">Loading server games per year…</p>
    <canvas width="960" height="271" aria-label="Server rated games per calendar year with projection"></canvas>
</div>

<div class="server-goals-month-chart">
    <p style="margin: 0 0 4px 0; color: var(--color-text-primary, #e3e3e3);">Server goals per month</p>
    <p class="server-goals-month-chart-status" style="margin: 0 0 8px 0;">Loading server goals per month…</p>
    <canvas width="960" height="271" aria-label="Server goals per calendar month"></canvas>
</div>

<div class="server-active-players-month-chart">
    <p style="margin: 0 0 4px 0; color: var(--color-text-primary, #e3e3e3);">Active players per month</p>
    <p class="server-active-players-month-chart-status" style="margin: 0 0 8px 0;">Loading active players per month…</p>
    <canvas width="960" height="271" aria-label="Server active players per calendar month"></canvas>
</div>

<div class="server-established-players-year-chart">
    <p style="margin: 0 0 4px 0; color: var(--color-text-primary, #e3e3e3);">New established players per year</p>
    <p style="margin: 0 0 4px 0; color: var(--color-text-muted, #b0b0b0); font-size: 0.9em;">Players whose 20th rated game fell in that calendar year.</p>
    <p class="server-established-players-year-chart-status" style="margin: 0 0 8px 0;">Loading newly established players per year…</p>
    <canvas width="960" height="271" aria-label="Server newly established players per calendar year"></canvas>
</div>

<div class="server-cumulative-established-month-chart">
    <p style="margin: 0 0 4px 0; color: var(--color-text-primary, #e3e3e3);">Cumulative established players</p>
    <p style="margin: 0 0 4px 0; color: var(--color-text-muted, #b0b0b0); font-size: 0.9em;">Steps up by one whenever a player plays their 20th rated game.</p>
    <p class="server-cumulative-established-month-chart-status" style="margin: 0 0 8px 0;">Loading cumulative established players…</p>
    <canvas width="960" height="271" aria-label="Cumulative established players over time by month"></canvas>
</div>

<div class="server-established-rating-distribution-chart">
    <p style="margin: 0 0 4px 0; color: var(--color-text-primary, #e3e3e3);">Established player rating distribution</p>
    <p style="margin: 0 0 4px 0; color: var(--color-text-muted, #b0b0b0); font-size: 0.9em;">Players with 20+ games, by current ELO (100-point buckets).</p>
    <p class="server-established-rating-distribution-chart-status" style="margin: 0 0 8px 0;">Loading rating distribution…</p>
    <canvas width="960" height="271" aria-label="Distribution of established player ratings"></canvas>
</div>

<div class="server-peak-month-leaderboard">
    <p style="margin: 0 0 4px 0; color: var(--color-text-primary, #e3e3e3);">Busiest month hall of fame</p>
    <p style="margin: 0 0 4px 0; color: var(--color-text-muted, #b0b0b0); font-size: 0.9em;">Top 50 players by most rated games in a single calendar month (each player’s personal best only). Ties: earlier month wins.</p>
    <p class="server-peak-month-leaderboard-status" style="margin: 0 0 8px 0;">Loading peak month leaderboard…</p>
    <div class="server-peak-month-leaderboard-table-wrap"></div>
</div>

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

</div><!-- .k2-page-nav -->

</body>
</html>




