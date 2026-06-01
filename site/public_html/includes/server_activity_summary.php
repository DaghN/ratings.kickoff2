<?php
/**
 * Activity tab summary block (PHP + markup).
 * Used by server1.php and server1-charts-lab.php.
 */
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (mysqli_connect_errno()) {
    die('Failed to connect to MySQL: ' . mysqli_connect_error());
}
$con->query("SET time_zone = '+00:00'");

$query = 'SELECT * FROM generalstatstable';
$result = mysqli_query($con, $query) or die('SELECT Error: ' . mysqli_error($con));

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

$BusiestDayGames = null;
$BusiestDayDateLabel = '';
$busiestDayRes = mysqli_query(
    $con,
    'SELECT `period_start` AS day, `rated_games` AS games '
    . 'FROM server_period_game_totals '
    . "WHERE period_type = 'day' "
    . 'ORDER BY rated_games DESC, period_start DESC '
    . 'LIMIT 1'
);
if ($busiestDayRes !== false) {
    $busiestDayRow = mysqli_fetch_assoc($busiestDayRes);
    mysqli_free_result($busiestDayRes);
    if ($busiestDayRow) {
        $BusiestDayGames = (int) $busiestDayRow['games'];
        $busiestDayTs = strtotime((string) $busiestDayRow['day']);
        $BusiestDayDateLabel = $busiestDayTs !== false
            ? date('F j, Y', $busiestDayTs)
            : (string) $busiestDayRow['day'];
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
        <?php if ($BusiestDayGames !== null) { ?>
        <div class="server-activity-summary__stat">
            <span class="server-activity-summary__label"><?php echo (int) $BusiestDayGames === 1 ? 'Rated game' : 'Rated games'; ?></span>
            <span class="server-activity-summary__value"><?php echo number_format($BusiestDayGames); ?></span>
            <span class="server-activity-summary__note">Busiest day · <?php echo htmlspecialchars($BusiestDayDateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php } ?>
    </div>
    <p class="server-activity-summary__texture">
        Players average <span class="blue"><?php echo number_format((float) $GamesPlayedAverage, 1); ?></span> rated games and <span class="blue"><?php echo number_format((float) $DifferentOpponentsAverage, 1); ?></span> different opponents.
    </p>
</section>
