<?php
/**
 * Amiga Activity tab summary block (PHP + markup).
 *
 * Optional before include:
 *   $k2AmigaActivitySummaryPanelLede — raw HTML lede above stat cards (Activity hub shell).
 *   $k2AmigaActivitySummaryHideLede = true — skip the default “since first event” lede.
 */
declare(strict_types=1);

$k2AmigaActivitySummaryHideLede = $k2AmigaActivitySummaryHideLede ?? false;
$k2AmigaActivitySummaryPanelLede = $k2AmigaActivitySummaryPanelLede ?? null;

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_community_stats_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_snapshot_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';

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
    if ($k2AmigaActivitySummaryPanelLede !== null && $k2AmigaActivitySummaryPanelLede !== '') {
        echo '<p class="server-activity-summary__lede">' . $k2AmigaActivitySummaryPanelLede . '</p>';
    } else {
        echo '<p class="server-activity-summary__lede">Community statistics are not available yet.</p>';
    }
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
$countryCount = amiga_lb_rated_country_count($con, $ctx);
$tournamentCount = amiga_tournament_index_count($con, $ctx);
$countryLabel = $countryCount === 1 ? 'country' : 'countries';
$tournamentLabel = $tournamentCount === 1 ? 'tournament' : 'tournaments';

$readCutoffTournamentId = amiga_community_cutoff_tournament_id_for_read($con, $ctx);
$busiestYear = $readCutoffTournamentId !== null
    ? amiga_community_busiest_year_at_cutoff($con, $readCutoffTournamentId)
    : null;
$BusiestYearGames = $busiestYear !== null ? (int) $busiestYear['games'] : null;
$BusiestYear = $busiestYear !== null ? (int) $busiestYear['year'] : null;

$k2AmigaActivitySummaryTexture = 'We average <span class="blue">' . number_format((float) $GamesPlayedAverage, 1) . '</span> rated games and <span class="blue">' . number_format((float) $DifferentOpponentsAverage, 1) . '</span> different opponents.';

mysqli_close($con);
unset($con);
?>
<section class="server-activity-summary" aria-label="Activity summary">
<?php if ($k2AmigaActivitySummaryPanelLede !== null && $k2AmigaActivitySummaryPanelLede !== '') { ?>
    <p class="server-activity-summary__lede"><?php echo $k2AmigaActivitySummaryPanelLede; ?> <?php echo $k2AmigaActivitySummaryTexture; ?></p>
<?php } elseif (!$k2AmigaActivitySummaryHideLede) { ?>
    <p class="server-activity-summary__lede">
        <span class="blue"><?php echo number_format($NumberOfPlayers); ?></span> players from
        <span class="blue"><?php echo number_format($countryCount); ?></span> <?php echo $countryLabel; ?> have played
        <span class="blue"><?php echo number_format($GamesPlayed); ?></span> rated games in
        <span class="blue"><?php echo number_format($tournamentCount); ?></span> <?php echo $tournamentLabel; ?> since <?php echo $ActivitySinceLabel; ?>.
        <?php echo $k2AmigaActivitySummaryTexture; ?>
    </p>
<?php } else { ?>
    <p class="server-activity-summary__lede"><?php echo $k2AmigaActivitySummaryTexture; ?></p>
<?php } ?>
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
        <?php if ($BusiestYearGames !== null && $BusiestYear !== null) { ?>
        <div class="server-activity-summary__stat">
            <span class="server-activity-summary__label">Busiest year</span>
            <span class="server-activity-summary__value"><?php echo number_format($BusiestYearGames); ?></span>
            <span class="server-activity-summary__note">games · <?php echo (int) $BusiestYear; ?></span>
        </div>
        <?php } ?>
    </div>
</section>
