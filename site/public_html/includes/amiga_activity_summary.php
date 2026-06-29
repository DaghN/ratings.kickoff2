<?php
/**
 * Amiga Activity tab summary block (PHP + markup).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_community_stats_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

$ctx = amiga_snapshot_context_from_request($con);
$cutoffTournamentId = null;
if ($ctx->isActive()) {
    $cutoff = $ctx->cutoff();
    if ($cutoff !== null) {
        $cutoffTournamentId = (int) $cutoff['tournament_id'];
    }
}

$row = amiga_community_headline_load($con, $cutoffTournamentId);
if ($row === null) {
    mysqli_close($con);
    echo '<section class="server-activity-summary" aria-label="Activity summary">';
    echo '<p class="server-activity-summary__lede">Community statistics are not available yet.</p>';
    echo '</section>';

    return;
}

$NumberOfPlayers = (int) ($row['NumberOfPlayers'] ?? 0);
$DifferentOpponentsAverage = $row['DifferentOpponentsAverage'];
$GamesPlayed = (int) ($row['GamesPlayed'] ?? 0);
$GamesPlayedAverage = $row['GamesPlayedAverage'];
$NumberOfDraws = (int) ($row['NumberOfDraws'] ?? 0);
$DrawsRatio = $row['DrawsRatio'];
$GoalsScored = (int) ($row['GoalsScored'] ?? 0);
$GoalsPerGameAverage = $row['GoalsPerGameAverage'];
$DoubleDigits = (int) ($row['DoubleDigits'] ?? 0);
$CleanSheets = (int) ($row['CleanSheets'] ?? 0);
$DoubleDigitsRatio = $row['DoubleDigitsRatio'];
$CleanSheetsRatio = $row['CleanSheetsRatio'];

$ActivitySinceLabel = amiga_community_first_event_label($con);

mysqli_close($con);
unset($con);
?>
<section class="server-activity-summary" aria-label="Activity summary">
    <p class="server-activity-summary__lede">
        <span class="blue"><?php echo number_format($NumberOfPlayers); ?></span> players played
        <span class="blue"><?php echo number_format($GamesPlayed); ?></span> rated games since <?php echo $ActivitySinceLabel; ?>.
    </p>
    <div class="server-activity-summary__stats" aria-label="Activity highlights">
        <div class="server-activity-summary__stat">
            <span class="server-activity-summary__label">Goals</span>
            <span class="server-activity-summary__value"><?php echo number_format($GoalsScored); ?></span>
            <span class="server-activity-summary__note"><?php echo number_format((float) $GoalsPerGameAverage, 2); ?> per game</span>
        </div>
        <div class="server-activity-summary__stat">
            <span class="server-activity-summary__label">Draws</span>
            <span class="server-activity-summary__value"><?php echo number_format($NumberOfDraws); ?></span>
            <span class="server-activity-summary__note"><?php echo number_format(100 * (float) $DrawsRatio, 1); ?>% of games</span>
        </div>
        <div class="server-activity-summary__stat">
            <span class="server-activity-summary__label">Double digits</span>
            <span class="server-activity-summary__value"><?php echo number_format($DoubleDigits); ?></span>
            <span class="server-activity-summary__note"><?php echo number_format(100 * (float) $DoubleDigitsRatio, 1); ?> per 100 games</span>
        </div>
        <div class="server-activity-summary__stat">
            <span class="server-activity-summary__label">Clean sheets</span>
            <span class="server-activity-summary__value"><?php echo number_format($CleanSheets); ?></span>
            <span class="server-activity-summary__note"><?php echo number_format(100 * (float) $CleanSheetsRatio, 1); ?> per 100 games</span>
        </div>
        <div class="server-activity-summary__stat">
            <span class="server-activity-summary__label">Games per player</span>
            <span class="server-activity-summary__value"><?php echo number_format((float) $GamesPlayedAverage, 1); ?></span>
            <span class="server-activity-summary__note"><?php echo number_format((float) $DifferentOpponentsAverage, 1); ?> different opponents avg.</span>
        </div>
    </div>
</section>
