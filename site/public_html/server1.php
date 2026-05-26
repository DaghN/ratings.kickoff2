<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script src="js/chart.umd.min.js"></script>
<script src="js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="js/chart-theme.js"></script>
<script src="js/chart-date-range.js"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>
<script type="text/javascript" src="js/server-games-day-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-games-month-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-games-year-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-goals-month-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-active-players-month-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-daily-active-players-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-established-players-year-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-cumulative-established-month-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-established-rating-distribution-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-double-digit-merchants-year-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-cumulative-double-digit-merchants-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-double-digit-merchant-rating-distribution-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-activity-heatmap.js" defer="defer"></script>
<script type="text/javascript" src="js/server-participation-depth-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-play-texture-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-matchup-breadth-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-top-activity-eras-chart.js" defer="defer"></script>
<script type="text/javascript" src="js/server-milestone-digest.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'activity';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

<?php 
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";


	$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
	if (mysqli_connect_errno())
  	{
  		die("Failed to connect to MySQL: " . mysqli_connect_error());
  	}
	$con->query("SET time_zone = '+00:00'");

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

$ActivityPlayers = $NumberOfPlayers;
$r = mysqli_query($con, 'SELECT COUNT(*) AS c FROM playertable WHERE Display = 1 AND NumberGames >= 1');
if ($r !== false) {
    $activityRow = mysqli_fetch_assoc($r);
    mysqli_free_result($r);
    $ActivityPlayers = (int) ($activityRow['c'] ?? $ActivityPlayers);
}

$ActivitySinceLabel = 'the first rated game';
$r = mysqli_query($con, 'SELECT MIN(`Date`) AS first_game FROM ratedresults');
if ($r !== false) {
    $activityRow = mysqli_fetch_assoc($r);
    mysqli_free_result($r);
    $firstGame = (string) ($activityRow['first_game'] ?? '');
    $firstGameTs = strtotime($firstGame);
    if ($firstGameTs !== false) {
        $ActivitySinceLabel = date('F j, Y', $firstGameTs);
    } elseif ($firstGame !== '') {
        $ActivitySinceLabel = htmlspecialchars($firstGame, ENT_QUOTES, 'UTF-8');
    }
}

mysqli_close($con);
unset($con);
?>

<section class="server-activity-summary" aria-label="Activity summary">
    <p class="server-activity-summary__lede">
        <span class="blue"><?php echo number_format((int) $ActivityPlayers); ?></span> players played
        <span class="blue"><?php echo number_format((int) $GamesPlayed); ?></span> rated games since <?php echo $ActivitySinceLabel; ?>.
    </p>
    <div class="server-activity-summary__stats" aria-label="Activity highlights">
        <div class="server-activity-summary__stat">
            <span class="server-activity-summary__label">Goals</span>
            <span class="server-activity-summary__value"><?php echo number_format((int) $GoalsScored); ?></span>
            <span class="server-activity-summary__note"><?php echo number_format((float) $GoalsPerGameAverage, 2); ?> per game</span>
        </div>
        <div class="server-activity-summary__stat">
            <span class="server-activity-summary__label">Draws</span>
            <span class="server-activity-summary__value"><?php echo number_format((int) $NumberOfDraws); ?></span>
            <span class="server-activity-summary__note"><?php echo number_format(100 * (float) $DrawsRatio, 1); ?>% of games</span>
        </div>
        <div class="server-activity-summary__stat">
            <span class="server-activity-summary__label">Double digits</span>
            <span class="server-activity-summary__value"><?php echo number_format((int) $DoubleDigits); ?></span>
            <span class="server-activity-summary__note"><?php echo number_format(100 * (float) $DoubleDigitsRatio, 1); ?> per 100 games</span>
        </div>
        <div class="server-activity-summary__stat">
            <span class="server-activity-summary__label">Clean sheets</span>
            <span class="server-activity-summary__value"><?php echo number_format((int) $CleanSheets); ?></span>
            <span class="server-activity-summary__note"><?php echo number_format(100 * (float) $CleanSheetsRatio, 1); ?> per 100 games</span>
        </div>
    </div>
    <p class="server-activity-summary__texture">
        Players average <?php echo number_format((float) $GamesPlayedAverage, 1); ?> rated games and <?php echo number_format((float) $DifferentOpponentsAverage, 1); ?> different opponents.
    </p>
</section>

<div class="server-milestone-digest">
    <h2 class="k2-panel-heading">Recent milestones</h2>
    <p class="server-milestone-digest-status" style="margin: 0 0 8px 0;">Loading milestones…</p>
    <div class="milestone-digest-wrap"></div>
</div>

<div class="server-games-day-chart">
    <h2 class="k2-panel-heading">Games per day · past month</h2>
    <p class="server-games-day-chart-status" style="margin: 0 0 8px 0;">Loading games per day...</p>
    <canvas width="960" height="271" aria-label="Rated games per day for the past month"></canvas>
</div>

<div class="server-activity-heatmap">
    <h2 class="k2-panel-heading">Daily activity · past 12 months</h2>
    <p class="server-activity-heatmap-status" style="margin: 0 0 8px 0;">Loading activity heatmap…</p>
    <div class="activity-heatmap-wrap"></div>
</div>

<div class="server-games-month-chart">
    <h2 class="k2-panel-heading">Games per month</h2>
    <p class="server-games-month-chart-status" style="margin: 0 0 8px 0;">Loading games per month…</p>
    <canvas width="960" height="271" aria-label="Rated games per calendar month"></canvas>
</div>

<div class="server-games-year-chart">
    <h2 class="k2-panel-heading">Games per year</h2>
    <p class="server-games-year-chart-status" style="margin: 0 0 8px 0;">Loading games per year…</p>
    <canvas width="960" height="271" aria-label="Rated games per calendar year with projection"></canvas>
</div>

<div class="server-goals-month-chart">
    <h2 class="k2-panel-heading">Goals per month</h2>
    <p class="server-goals-month-chart-status" style="margin: 0 0 8px 0;">Loading goals per month…</p>
    <canvas width="960" height="271" aria-label="Goals per calendar month"></canvas>
</div>

<div class="server-play-texture-chart">
    <h2 class="k2-panel-heading">Play texture by month</h2>
    <p class="k2-chart-block__hint">Normalized rates: goals per game, draw %, double-digit and clean-sheet rates per 100 games.</p>
    <p class="server-play-texture-chart-status" style="margin: 0 0 8px 0;">Loading play texture…</p>
    <canvas width="960" height="271" aria-label="Monthly play texture rates"></canvas>
</div>

<div class="server-active-players-month-chart">
    <h2 class="k2-panel-heading">Active players per month</h2>
    <p class="server-active-players-month-chart-status" style="margin: 0 0 8px 0;">Loading active players per month…</p>
    <canvas width="960" height="271" aria-label="Active players per calendar month"></canvas>
</div>

<div class="server-daily-active-players-chart">
    <h2 class="k2-panel-heading">Daily active players · 30-day average</h2>
    <p class="server-daily-active-players-chart-status" style="margin: 0 0 8px 0;">Loading daily active players…</p>
    <canvas width="960" height="271" aria-label="Daily active players smoothed over 30 days, all time"></canvas>
</div>

<div class="server-top-activity-eras-chart">
    <h2 class="k2-panel-heading">Top activity eras</h2>
    <p class="k2-chart-block__hint">Players appear while they are top 10 for rated games in a calendar month.</p>
    <p class="server-top-activity-eras-chart-status" style="margin: 0 0 8px 0;">Loading top activity eras&#8230;</p>
    <canvas width="960" height="360" aria-label="Top activity players over time by calendar month"></canvas>
</div>

<div class="server-participation-depth-chart">
    <h2 class="k2-panel-heading">Participation depth by month</h2>
    <p class="k2-chart-block__hint">Monthly players split by games played: 1 · 2–4 · 5–9 · 10+.</p>
    <p class="server-participation-depth-chart-status" style="margin: 0 0 8px 0;">Loading participation depth…</p>
    <canvas width="960" height="271" aria-label="Participation depth by month"></canvas>
</div>

<div class="server-matchup-breadth-chart">
    <h2 class="k2-panel-heading">Unique matchups per month</h2>
    <p class="k2-chart-block__hint">Distinct player pairings each month — social breadth of the scene.</p>
    <p class="server-matchup-breadth-chart-status" style="margin: 0 0 8px 0;">Loading matchup breadth…</p>
    <canvas width="960" height="271" aria-label="Unique matchups per month"></canvas>
</div>

<div class="server-established-players-year-chart">
    <h2 class="k2-panel-heading">New established players per year</h2>
    <p class="k2-chart-block__hint">Players whose 20th rated game fell in that calendar year.</p>
    <p class="server-established-players-year-chart-status" style="margin: 0 0 8px 0;">Loading newly established players per year…</p>
    <canvas width="960" height="271" aria-label="Newly established players per calendar year"></canvas>
</div>

<div class="server-cumulative-established-month-chart">
    <h2 class="k2-panel-heading">Cumulative established players</h2>
    <p class="k2-chart-block__hint">Steps up by one whenever a player plays their 20th rated game.</p>
    <p class="server-cumulative-established-month-chart-status" style="margin: 0 0 8px 0;">Loading cumulative established players…</p>
    <canvas width="960" height="271" aria-label="Cumulative established players over time by month"></canvas>
</div>

<div class="server-established-rating-distribution-chart">
    <h2 class="k2-panel-heading">Established player rating distribution</h2>
    <p class="server-established-rating-distribution-chart-status" style="margin: 0 0 8px 0;">Loading rating distribution…</p>
    <canvas width="960" height="271" aria-label="Distribution of established player ratings"></canvas>
</div>

<div class="server-double-digit-merchants-year-chart">
    <h2 class="k2-panel-heading">New Double Digit Merchants per year</h2>
    <p class="k2-chart-block__hint">Players whose first 10+ goal game fell in that calendar year.</p>
    <p class="server-double-digit-merchants-year-chart-status" style="margin: 0 0 8px 0;">Loading new Double Digit Merchants per year...</p>
    <canvas width="960" height="271" aria-label="New Double Digit Merchants per calendar year"></canvas>
</div>

<div class="server-cumulative-double-digit-merchants-chart">
    <h2 class="k2-panel-heading">Cumulative Double Digit Merchants</h2>
    <p class="k2-chart-block__hint">Steps up by one whenever a player scores 10+ for the first time.</p>
    <p class="server-cumulative-double-digit-merchants-chart-status" style="margin: 0 0 8px 0;">Loading cumulative Double Digit Merchants...</p>
    <canvas width="960" height="271" aria-label="Cumulative Double Digit Merchants over time"></canvas>
</div>

<div class="server-double-digit-merchant-rating-distribution-chart">
    <h2 class="k2-panel-heading">Double Digit Merchant rating distribution</h2>
    <p class="server-double-digit-merchant-rating-distribution-chart-status" style="margin: 0 0 8px 0;">Loading merchant rating distribution...</p>
    <canvas width="960" height="271" aria-label="Distribution of Double Digit Merchant ratings"></canvas>
</div>

</div><!-- .k2-page-nav -->

</body>
</html>




