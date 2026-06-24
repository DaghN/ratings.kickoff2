<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga 500 — Hall of Fame</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php'; k2_table_js_enqueue(); ?>
</head>
<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'hall-of-fame';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_records_common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_records_hof_links.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_realm_snapshot_read_lib.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$ctx = amiga_snapshot_context_from_request($con);
$GLOBALS['_amiga_snapshot_context'] = $ctx;

$recordColumns = amiga_hof_record_column_names();
$records = amiga_hof_records_load($con, $ctx);
if (!$records) {
    mysqli_close($con);
    http_response_code(503);
    exit('Hall of Fame data is not available yet. Run python -m scripts.amiga replay.');
}

$hofAsOf = time();
if ($ctx->isActive()) {
    $hofCutoff = $ctx->cutoff();
    if ($hofCutoff !== null && ($hofCutoff['event_date'] ?? '') !== '') {
        $hofAsOfTs = strtotime((string) $hofCutoff['event_date'] . ' 23:59:59');
        if ($hofAsOfTs !== false) {
            $hofAsOf = $hofAsOfTs;
        }
    }
}
[$newRecordCutoff, $legendaryRecordCutoff] = amiga_records_age_cutoffs_from($hofAsOf);

mysqli_close($con);
?>

<?php
if (!$ctx->isActive()) {
    $k2HubChapterTitle = 'Hall of Fame';
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
}

// Static row labels — keep in sync with amiga_records_render_row calls below (shared col 1 width).
$k2HofRecordLabels = [
    'Most games',
    'Most wins',
    'Most goals',
    'Most double digits',
    'Most clean sheets',
    'Most opponents',
    'Most victims',
    'Most double digit victims',
    'Most clean sheet victims',
    'Most goals in one game',
    'Biggest winning margin',
    'Biggest draw',
    'Biggest sum of goals',
    'Highest peak rating',
    'Best attack average',
    'Best defense average',
    'Best goal ratio',
    'Highest winning frequency',
    'Highest double digit frequency',
    'Highest clean sheet frequency',
    'Most games in one year',
    'Most tournaments in one year',
    'Most tournaments (career)',
    'Most tournament wins',
    'Most World Cups played',
    'Most countries played in',
    'Most opponent countries faced',
    'Most opponent countries beaten',
];

records_hof_sync_reset();
ob_start();
?>
<section class="server-records-panel server-records-panel--hof">
<div class="k2-table-wrap">
<table class="k2-table server-records-table k2-table--calm-stats" data-k2-anchor-col="1">
<?php records_hof_render_colgroup(); ?>
<tbody class="black">
<?php
amiga_records_render_row(
    'Most games',
    (string) ($records['MostGamesPlayed'] ?? '-'),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostGamesPlayedID'] ?? 0), (string) ($records['MostGamesPlayedName'] ?? ''))),
    amiga_records_date_or_dash($records['MostGamesPlayedDate'] ?? null, true, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_games')
);
amiga_records_render_row(
    'Most wins',
    amiga_records_value_or_dash($records['MostWins'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostWinsID'] ?? 0), (string) ($records['MostWinsName'] ?? ''))),
    amiga_records_date_or_dash($records['MostWinsDate'] ?? null, amiga_records_has_value($records['MostWins'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_wins')
);
amiga_records_render_row(
    'Most goals',
    amiga_records_value_or_dash($records['MostGoalsScored'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostGoalsScoredID'] ?? 0), (string) ($records['MostGoalsScoredName'] ?? ''))),
    amiga_records_date_or_dash($records['MostGoalsScoredDate'] ?? null, amiga_records_has_value($records['MostGoalsScored'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_goals')
);
amiga_records_render_row(
    'Most double digits',
    amiga_records_value_or_dash($records['MostDoubleDigits'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostDoubleDigitsID'] ?? 0), (string) ($records['MostDoubleDigitsName'] ?? ''))),
    amiga_records_date_or_dash($records['MostDoubleDigitsDate'] ?? null, amiga_records_has_value($records['MostDoubleDigits'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_dd')
);
amiga_records_render_row(
    'Most clean sheets',
    amiga_records_value_or_dash($records['MostCleanSheets'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostCleanSheetsID'] ?? 0), (string) ($records['MostCleanSheetsName'] ?? ''))),
    amiga_records_date_or_dash($records['MostCleanSheetsDate'] ?? null, amiga_records_has_value($records['MostCleanSheets'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_cs')
);
amiga_records_render_spacer_row();
amiga_records_render_row(
    'Most opponents',
    (string) ($records['MostDifferentOpponents'] ?? '-'),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostDifferentOpponentsID'] ?? 0), (string) ($records['MostDifferentOpponentsName'] ?? ''))),
    amiga_records_date_or_dash($records['MostDifferentOpponentsDate'] ?? null, true, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_opponents')
);
amiga_records_render_row(
    'Most victims',
    amiga_records_value_or_dash($records['MostDifferentVictims'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostDifferentVictimsID'] ?? 0), (string) ($records['MostDifferentVictimsName'] ?? ''))),
    amiga_records_date_or_dash($records['MostDifferentVictimsDate'] ?? null, amiga_records_has_value($records['MostDifferentVictims'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_victims')
);
amiga_records_render_row(
    'Most double digit victims',
    amiga_records_value_or_dash($records['MostDoubleDigitsVictims'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostDoubleDigitsVictimsID'] ?? 0), (string) ($records['MostDoubleDigitsVictimsName'] ?? ''))),
    amiga_records_date_or_dash($records['MostDoubleDigitsVictimsDate'] ?? null, amiga_records_has_value($records['MostDoubleDigitsVictims'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_dd_victims')
);
amiga_records_render_row(
    'Most clean sheet victims',
    amiga_records_value_or_dash($records['MostCleanSheetsVictims'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostCleanSheetsVictimsID'] ?? 0), (string) ($records['MostCleanSheetsVictimsName'] ?? ''))),
    amiga_records_date_or_dash($records['MostCleanSheetsVictimsDate'] ?? null, amiga_records_has_value($records['MostCleanSheetsVictims'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_cs_victims')
);
amiga_records_render_spacer_row();
$hasMostGoalsOneGame = amiga_records_has_value($records['MostGoalsScoredInOneGame'] ?? 0);
amiga_records_render_row(
    'Most goals in one game',
    amiga_records_value_or_dash($records['MostGoalsScoredInOneGame'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostGoalsScoredInOneGameID'] ?? 0), (string) ($records['MostGoalsScoredInOneGameName'] ?? ''))),
    amiga_records_date_or_dash($records['MostGoalsScoredInOneGameDate'] ?? null, $hasMostGoalsOneGame, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_goals_one_game')
);
$hasBiggestWinMargin = amiga_records_has_value($records['BiggestWinDifference'] ?? 0);
amiga_records_render_row(
    'Biggest winning margin',
    amiga_records_value_or_dash($records['BiggestWinDifference'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['BiggestWinDifferenceID'] ?? 0), (string) ($records['BiggestWinDifferenceName'] ?? ''))),
    amiga_records_date_or_dash($records['BiggestWinDifferenceDate'] ?? null, $hasBiggestWinMargin, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('biggest_win_margin')
);
$hasBiggestDraw = amiga_records_has_value($records['BiggestDrawSum'] ?? 0);
$biggestDrawScore = $hasBiggestDraw
    ? ((string) ((int) $records['BiggestDrawSum'] / 2) . '-' . (string) ((int) $records['BiggestDrawSum'] / 2))
    : '-';
amiga_records_render_row(
    'Biggest draw',
    $biggestDrawScore,
    amiga_records_holder_html(
        amiga_records_profile_link((int) ($records['BiggestDrawSumIDA'] ?? 0), (string) ($records['BiggestDrawSumNameA'] ?? ''))
        . ' / '
        . amiga_records_profile_link((int) ($records['BiggestDrawSumIDB'] ?? 0), (string) ($records['BiggestDrawSumNameB'] ?? ''))
    ),
    amiga_records_date_or_dash($records['BiggestDrawSumDate'] ?? null, $hasBiggestDraw, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('biggest_draw')
);
$hasBiggestSumGoals = amiga_records_has_value($records['BiggestSumOfGoals'] ?? 0);
amiga_records_render_row(
    'Biggest sum of goals',
    amiga_records_value_or_dash($records['BiggestSumOfGoals'] ?? null),
    amiga_records_holder_html(
        amiga_records_profile_link((int) ($records['BiggestSumOfGoalsIDA'] ?? 0), (string) ($records['BiggestSumOfGoalsNameA'] ?? ''))
        . ' / '
        . amiga_records_profile_link((int) ($records['BiggestSumOfGoalsIDB'] ?? 0), (string) ($records['BiggestSumOfGoalsNameB'] ?? ''))
    ),
    amiga_records_date_or_dash($records['BiggestSumOfGoalsDate'] ?? null, $hasBiggestSumGoals, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('biggest_sum_goals')
);
amiga_records_render_spacer_row();
$hasPeakRating = amiga_records_has_value($records['BiggestPeakRating'] ?? 0);
amiga_records_render_row(
    'Highest peak rating',
    $hasPeakRating ? number_format((float) $records['BiggestPeakRating'], 0, '.', '') : '-',
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['BiggestPeakRatingID'] ?? 0), (string) ($records['BiggestPeakRatingName'] ?? ''))),
    amiga_records_date_or_dash($records['BiggestPeakRatingDate'] ?? null, $hasPeakRating, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('peak_rating')
);
amiga_records_render_spacer_row();
amiga_records_render_row(
    'Best attack average',
    amiga_records_fixed_or_dash($records['BiggestGoalsForAverage'] ?? null, 2),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['BiggestGoalsForAverageID'] ?? 0), (string) ($records['BiggestGoalsForAverageName'] ?? ''))),
    '-',
    amiga_records_hof_lb_href('attack_avg')
);
amiga_records_render_row(
    'Best defense average',
    amiga_records_fixed_or_dash($records['SmallestGoalsAgainstAverage'] ?? null, 2),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['SmallestGoalsAgainstAverageID'] ?? 0), (string) ($records['SmallestGoalsAgainstAverageName'] ?? ''))),
    '-',
    amiga_records_hof_lb_href('defense_avg')
);
amiga_records_render_row(
    'Best goal ratio',
    amiga_records_fixed_or_dash($records['BiggestGoalRatio'] ?? null, 2),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['BiggestGoalRatioID'] ?? 0), (string) ($records['BiggestGoalRatioName'] ?? ''))),
    '-',
    amiga_records_hof_lb_href('goal_ratio')
);
amiga_records_render_spacer_row();
amiga_records_render_row(
    'Highest winning frequency',
    amiga_records_percent_or_dash($records['BiggestWinRatio'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['BiggestWinRatioID'] ?? 0), (string) ($records['BiggestWinRatioName'] ?? ''))),
    '-',
    amiga_records_hof_lb_href('win_ratio')
);
amiga_records_render_row(
    'Highest double digit frequency',
    amiga_records_percent_or_dash($records['BiggestDoubleDigitsRatio'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['BiggestDoubleDigitsRatioID'] ?? 0), (string) ($records['BiggestDoubleDigitsRatioName'] ?? ''))),
    '-',
    amiga_records_hof_lb_href('dd_ratio')
);
amiga_records_render_row(
    'Highest clean sheet frequency',
    amiga_records_percent_or_dash($records['BiggestCleanSheetsRatio'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['BiggestCleanSheetsRatioID'] ?? 0), (string) ($records['BiggestCleanSheetsRatioName'] ?? ''))),
    '-',
    amiga_records_hof_lb_href('cs_ratio')
);
amiga_records_render_spacer_row();
amiga_records_render_row(
    'Most games in one year',
    amiga_records_value_or_dash($records['MostGamesInOneYear'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostGamesInOneYearID'] ?? 0), (string) ($records['MostGamesInOneYearName'] ?? ''))),
    amiga_records_peak_year_or_dash($records['MostGamesInOneYearDate'] ?? null, amiga_records_has_value($records['MostGamesInOneYear'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_games_in_year')
);
amiga_records_render_row(
    'Most tournaments in one year',
    amiga_records_value_or_dash($records['MostTournamentsInOneYear'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostTournamentsInOneYearID'] ?? 0), (string) ($records['MostTournamentsInOneYearName'] ?? ''))),
    amiga_records_peak_year_or_dash($records['MostTournamentsInOneYearDate'] ?? null, amiga_records_has_value($records['MostTournamentsInOneYear'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_tournaments_in_year')
);
amiga_records_render_row(
    'Most tournaments (career)',
    amiga_records_value_or_dash($records['MostTournamentsPlayed'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostTournamentsPlayedID'] ?? 0), (string) ($records['MostTournamentsPlayedName'] ?? ''))),
    amiga_records_date_or_dash($records['MostTournamentsPlayedDate'] ?? null, amiga_records_has_value($records['MostTournamentsPlayed'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_tournaments_played')
);
amiga_records_render_row(
    'Most tournament wins',
    amiga_records_value_or_dash($records['MostTournamentWins'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostTournamentWinsID'] ?? 0), (string) ($records['MostTournamentWinsName'] ?? ''))),
    amiga_records_date_or_dash($records['MostTournamentWinsDate'] ?? null, amiga_records_has_value($records['MostTournamentWins'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_tournament_wins')
);
amiga_records_render_row(
    'Most World Cups played',
    amiga_records_value_or_dash($records['MostWcPlayed'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostWcPlayedID'] ?? 0), (string) ($records['MostWcPlayedName'] ?? ''))),
    amiga_records_date_or_dash($records['MostWcPlayedDate'] ?? null, amiga_records_has_value($records['MostWcPlayed'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_wc_played')
);
amiga_records_render_spacer_row();
amiga_records_render_row(
    'Most countries played in',
    amiga_records_value_or_dash($records['MostCountriesPlayedIn'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostCountriesPlayedInID'] ?? 0), (string) ($records['MostCountriesPlayedInName'] ?? ''))),
    amiga_records_date_or_dash($records['MostCountriesPlayedInDate'] ?? null, amiga_records_has_value($records['MostCountriesPlayedIn'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_countries_played_in')
);
amiga_records_render_row(
    'Most opponent countries faced',
    amiga_records_value_or_dash($records['MostOpponentCountriesFaced'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostOpponentCountriesFacedID'] ?? 0), (string) ($records['MostOpponentCountriesFacedName'] ?? ''))),
    amiga_records_date_or_dash($records['MostOpponentCountriesFacedDate'] ?? null, amiga_records_has_value($records['MostOpponentCountriesFaced'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_opponent_countries_faced')
);
amiga_records_render_row(
    'Most opponent countries beaten',
    amiga_records_value_or_dash($records['MostOpponentCountriesBeaten'] ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($records['MostOpponentCountriesBeatenID'] ?? 0), (string) ($records['MostOpponentCountriesBeatenName'] ?? ''))),
    amiga_records_date_or_dash($records['MostOpponentCountriesBeatenDate'] ?? null, amiga_records_has_value($records['MostOpponentCountriesBeaten'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_opponent_countries_beaten')
);
?>
</tbody>
</table>
</div>
</section>
<?php
$k2HofTableHtml = ob_get_clean();
$k2HofSyncWidths = records_hof_sync_compute_widths($k2HofRecordLabels);
?>
<div class="server-records-hof" style="<?php echo htmlspecialchars(records_hof_sync_style_attr($k2HofSyncWidths), ENT_QUOTES, 'UTF-8'); ?>">
<?php echo $k2HofTableHtml; ?>
</div><!-- .server-records-hof -->

</div><!-- .k2-page-nav -->

</body>
</html>
