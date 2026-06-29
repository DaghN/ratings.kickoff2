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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_hof_read_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
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

$peakHolder = amiga_hof_peak_rating_holder($con, $ctx);

// World Cup HoF block (sparse store; present row or time-travel snapshot).
$wcRecords = amiga_wc_hof_records_load($con, $ctx) ?? [];

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

$hofHolderIds = amiga_hof_holder_ids_from_records($records);
if (($peakHolder['player_id'] ?? 0) > 0) {
    $hofHolderIds[] = (int) $peakHolder['player_id'];
}
foreach (amiga_wc_hof_holder_ids_from_records($wcRecords) as $wcHolderId) {
    $hofHolderIds[] = $wcHolderId;
}
$hofHolderIds = array_values(array_unique($hofHolderIds));
$hofCountryByPlayer = amiga_tournament_player_countries($con, $hofHolderIds);

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
    'Most perfect events',
    'Most countries played in',
    'Most opponent countries faced',
    'Most opponent countries beaten',
    // World Cup HoF block (rendered after the career rows; same width-sync group).
    'Most World Cups',
    'Most WC golds',
    'Most WC games',
    'Most WC wins',
    'Most WC points',
    'Best WC points/game',
    'Best WC win rate',
    'Most WC goals',
    'Best WC goals/game',
    'Best WC defense/game',
    'Best WC goal diff/game',
    'Best WC goal ratio',
    'Most WC double digits',
    'Best WC double digit rate',
    'Most WC clean sheets',
    'Best WC clean sheet rate',
    'Most WC opponents',
    'Most WC victims',
    'Most WC double digit victims',
    'Most WC clean sheet victims',
    'Most WC goals in one game',
    'Biggest WC winning margin',
    'Biggest WC draw',
    'Biggest WC sum of goals',
    'Most best attack awards',
    'Most best defense awards',
    'Best single-WC attack',
    'Best single-WC defense',
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
    amiga_records_holder_player((int) ($records['MostGamesPlayedID'] ?? 0), (string) ($records['MostGamesPlayedName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostGamesPlayedDate'] ?? null, true, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_games')
);
amiga_records_render_row(
    'Most wins',
    amiga_records_value_or_dash($records['MostWins'] ?? null),
    amiga_records_holder_player((int) ($records['MostWinsID'] ?? 0), (string) ($records['MostWinsName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostWinsDate'] ?? null, amiga_records_has_value($records['MostWins'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_wins')
);
amiga_records_render_row(
    'Most goals',
    amiga_records_value_or_dash($records['MostGoalsScored'] ?? null),
    amiga_records_holder_player((int) ($records['MostGoalsScoredID'] ?? 0), (string) ($records['MostGoalsScoredName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostGoalsScoredDate'] ?? null, amiga_records_has_value($records['MostGoalsScored'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_goals')
);
amiga_records_render_row(
    'Most double digits',
    amiga_records_value_or_dash($records['MostDoubleDigits'] ?? null),
    amiga_records_holder_player((int) ($records['MostDoubleDigitsID'] ?? 0), (string) ($records['MostDoubleDigitsName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostDoubleDigitsDate'] ?? null, amiga_records_has_value($records['MostDoubleDigits'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_dd')
);
amiga_records_render_row(
    'Most clean sheets',
    amiga_records_value_or_dash($records['MostCleanSheets'] ?? null),
    amiga_records_holder_player((int) ($records['MostCleanSheetsID'] ?? 0), (string) ($records['MostCleanSheetsName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostCleanSheetsDate'] ?? null, amiga_records_has_value($records['MostCleanSheets'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_cs')
);
amiga_records_render_spacer_row();
amiga_records_render_row(
    'Most opponents',
    (string) ($records['MostDifferentOpponents'] ?? '-'),
    amiga_records_holder_player((int) ($records['MostDifferentOpponentsID'] ?? 0), (string) ($records['MostDifferentOpponentsName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostDifferentOpponentsDate'] ?? null, true, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_opponents')
);
amiga_records_render_row(
    'Most victims',
    amiga_records_value_or_dash($records['MostDifferentVictims'] ?? null),
    amiga_records_holder_player((int) ($records['MostDifferentVictimsID'] ?? 0), (string) ($records['MostDifferentVictimsName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostDifferentVictimsDate'] ?? null, amiga_records_has_value($records['MostDifferentVictims'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_victims')
);
amiga_records_render_row(
    'Most double digit victims',
    amiga_records_value_or_dash($records['MostDoubleDigitsVictims'] ?? null),
    amiga_records_holder_player((int) ($records['MostDoubleDigitsVictimsID'] ?? 0), (string) ($records['MostDoubleDigitsVictimsName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostDoubleDigitsVictimsDate'] ?? null, amiga_records_has_value($records['MostDoubleDigitsVictims'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_dd_victims')
);
amiga_records_render_row(
    'Most clean sheet victims',
    amiga_records_value_or_dash($records['MostCleanSheetsVictims'] ?? null),
    amiga_records_holder_player((int) ($records['MostCleanSheetsVictimsID'] ?? 0), (string) ($records['MostCleanSheetsVictimsName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostCleanSheetsVictimsDate'] ?? null, amiga_records_has_value($records['MostCleanSheetsVictims'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_cs_victims')
);
amiga_records_render_spacer_row();
$hasMostGoalsOneGame = amiga_records_has_value($records['MostGoalsScoredInOneGame'] ?? 0);
amiga_records_render_row(
    'Most goals in one game',
    amiga_records_value_or_dash($records['MostGoalsScoredInOneGame'] ?? null),
    amiga_records_holder_player((int) ($records['MostGoalsScoredInOneGameID'] ?? 0), (string) ($records['MostGoalsScoredInOneGameName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostGoalsScoredInOneGameDate'] ?? null, $hasMostGoalsOneGame, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_goals_one_game')
);
$hasBiggestWinMargin = amiga_records_has_value($records['BiggestWinDifference'] ?? 0);
amiga_records_render_row(
    'Biggest winning margin',
    amiga_records_value_or_dash($records['BiggestWinDifference'] ?? null),
    amiga_records_holder_player((int) ($records['BiggestWinDifferenceID'] ?? 0), (string) ($records['BiggestWinDifferenceName'] ?? ''), $hofCountryByPlayer),
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
    amiga_records_holder_players_pair(
        (int) ($records['BiggestDrawSumIDA'] ?? 0),
        (string) ($records['BiggestDrawSumNameA'] ?? ''),
        (int) ($records['BiggestDrawSumIDB'] ?? 0),
        (string) ($records['BiggestDrawSumNameB'] ?? ''),
        $hofCountryByPlayer
    ),
    amiga_records_date_or_dash($records['BiggestDrawSumDate'] ?? null, $hasBiggestDraw, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('biggest_draw')
);
$hasBiggestSumGoals = amiga_records_has_value($records['BiggestSumOfGoals'] ?? 0);
amiga_records_render_row(
    'Biggest sum of goals',
    amiga_records_value_or_dash($records['BiggestSumOfGoals'] ?? null),
    amiga_records_holder_players_pair(
        (int) ($records['BiggestSumOfGoalsIDA'] ?? 0),
        (string) ($records['BiggestSumOfGoalsNameA'] ?? ''),
        (int) ($records['BiggestSumOfGoalsIDB'] ?? 0),
        (string) ($records['BiggestSumOfGoalsNameB'] ?? ''),
        $hofCountryByPlayer
    ),
    amiga_records_date_or_dash($records['BiggestSumOfGoalsDate'] ?? null, $hasBiggestSumGoals, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('biggest_sum_goals')
);
amiga_records_render_spacer_row();
$hasPeakRating = ($peakHolder['value'] ?? null) !== null && (float) $peakHolder['value'] > 0;
amiga_records_render_row(
    'Highest peak rating',
    $hasPeakRating ? number_format((float) $peakHolder['value'], 0, '.', '') : '-',
    amiga_records_holder_player((int) ($peakHolder['player_id'] ?? 0), (string) ($peakHolder['name'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($peakHolder['date'] ?? null, $hasPeakRating, $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('peak_rating')
);
amiga_records_render_spacer_row();
amiga_records_render_row(
    'Best attack average',
    amiga_records_fixed_or_dash($records['BiggestGoalsForAverage'] ?? null, 2),
    amiga_records_holder_player((int) ($records['BiggestGoalsForAverageID'] ?? 0), (string) ($records['BiggestGoalsForAverageName'] ?? ''), $hofCountryByPlayer),
    '-',
    amiga_records_hof_lb_href('attack_avg')
);
amiga_records_render_row(
    'Best defense average',
    amiga_records_fixed_or_dash($records['SmallestGoalsAgainstAverage'] ?? null, 2),
    amiga_records_holder_player((int) ($records['SmallestGoalsAgainstAverageID'] ?? 0), (string) ($records['SmallestGoalsAgainstAverageName'] ?? ''), $hofCountryByPlayer),
    '-',
    amiga_records_hof_lb_href('defense_avg')
);
amiga_records_render_row(
    'Best goal ratio',
    amiga_records_fixed_or_dash($records['BiggestGoalRatio'] ?? null, 2),
    amiga_records_holder_player((int) ($records['BiggestGoalRatioID'] ?? 0), (string) ($records['BiggestGoalRatioName'] ?? ''), $hofCountryByPlayer),
    '-',
    amiga_records_hof_lb_href('goal_ratio')
);
amiga_records_render_spacer_row();
amiga_records_render_row(
    'Highest winning frequency',
    amiga_records_percent_or_dash($records['BiggestWinRatio'] ?? null),
    amiga_records_holder_player((int) ($records['BiggestWinRatioID'] ?? 0), (string) ($records['BiggestWinRatioName'] ?? ''), $hofCountryByPlayer),
    '-',
    amiga_records_hof_lb_href('win_ratio')
);
amiga_records_render_row(
    'Highest double digit frequency',
    amiga_records_percent_or_dash($records['BiggestDoubleDigitsRatio'] ?? null),
    amiga_records_holder_player((int) ($records['BiggestDoubleDigitsRatioID'] ?? 0), (string) ($records['BiggestDoubleDigitsRatioName'] ?? ''), $hofCountryByPlayer),
    '-',
    amiga_records_hof_lb_href('dd_ratio')
);
amiga_records_render_row(
    'Highest clean sheet frequency',
    amiga_records_percent_or_dash($records['BiggestCleanSheetsRatio'] ?? null),
    amiga_records_holder_player((int) ($records['BiggestCleanSheetsRatioID'] ?? 0), (string) ($records['BiggestCleanSheetsRatioName'] ?? ''), $hofCountryByPlayer),
    '-',
    amiga_records_hof_lb_href('cs_ratio')
);
amiga_records_render_spacer_row();
amiga_records_render_row(
    'Most games in one year',
    amiga_records_value_or_dash($records['MostGamesInOneYear'] ?? null),
    amiga_records_holder_player((int) ($records['MostGamesInOneYearID'] ?? 0), (string) ($records['MostGamesInOneYearName'] ?? ''), $hofCountryByPlayer),
    amiga_records_peak_year_or_dash($records['MostGamesInOneYearDate'] ?? null, amiga_records_has_value($records['MostGamesInOneYear'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_games_in_year')
);
amiga_records_render_row(
    'Most tournaments in one year',
    amiga_records_value_or_dash($records['MostTournamentsInOneYear'] ?? null),
    amiga_records_holder_player((int) ($records['MostTournamentsInOneYearID'] ?? 0), (string) ($records['MostTournamentsInOneYearName'] ?? ''), $hofCountryByPlayer),
    amiga_records_peak_year_or_dash($records['MostTournamentsInOneYearDate'] ?? null, amiga_records_has_value($records['MostTournamentsInOneYear'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_tournaments_in_year')
);
amiga_records_render_row(
    'Most tournaments (career)',
    amiga_records_value_or_dash($records['MostTournamentsPlayed'] ?? null),
    amiga_records_holder_player((int) ($records['MostTournamentsPlayedID'] ?? 0), (string) ($records['MostTournamentsPlayedName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostTournamentsPlayedDate'] ?? null, amiga_records_has_value($records['MostTournamentsPlayed'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_tournaments_played')
);
amiga_records_render_row(
    'Most tournament wins',
    amiga_records_value_or_dash($records['MostTournamentWins'] ?? null),
    amiga_records_holder_player((int) ($records['MostTournamentWinsID'] ?? 0), (string) ($records['MostTournamentWinsName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostTournamentWinsDate'] ?? null, amiga_records_has_value($records['MostTournamentWins'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_tournament_wins')
);
amiga_records_render_row(
    'Most perfect events',
    amiga_records_value_or_dash($records['MostPerfectEvents'] ?? null),
    amiga_records_holder_player((int) ($records['MostPerfectEventsID'] ?? 0), (string) ($records['MostPerfectEventsName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostPerfectEventsDate'] ?? null, amiga_records_has_value($records['MostPerfectEvents'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_perfect_events')
);
amiga_records_render_spacer_row();
amiga_records_render_row(
    'Most countries played in',
    amiga_records_value_or_dash($records['MostCountriesPlayedIn'] ?? null),
    amiga_records_holder_player((int) ($records['MostCountriesPlayedInID'] ?? 0), (string) ($records['MostCountriesPlayedInName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostCountriesPlayedInDate'] ?? null, amiga_records_has_value($records['MostCountriesPlayedIn'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_countries_played_in')
);
amiga_records_render_row(
    'Most opponent countries faced',
    amiga_records_value_or_dash($records['MostOpponentCountriesFaced'] ?? null),
    amiga_records_holder_player((int) ($records['MostOpponentCountriesFacedID'] ?? 0), (string) ($records['MostOpponentCountriesFacedName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostOpponentCountriesFacedDate'] ?? null, amiga_records_has_value($records['MostOpponentCountriesFaced'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_opponent_countries_faced')
);
amiga_records_render_row(
    'Most opponent countries beaten',
    amiga_records_value_or_dash($records['MostOpponentCountriesBeaten'] ?? null),
    amiga_records_holder_player((int) ($records['MostOpponentCountriesBeatenID'] ?? 0), (string) ($records['MostOpponentCountriesBeatenName'] ?? ''), $hofCountryByPlayer),
    amiga_records_date_or_dash($records['MostOpponentCountriesBeatenDate'] ?? null, amiga_records_has_value($records['MostOpponentCountriesBeaten'] ?? 0), $newRecordCutoff, $legendaryRecordCutoff),
    amiga_records_hof_lb_href('most_opponent_countries_beaten')
);

// ---- World Cups -------------------------------------------------------------
amiga_records_render_spacer_row();
echo "    <tr class=\"server-records-section-header\"><td colspan=\"4\">World Cups</td></tr>\n";

$wcRow = static function (
    string $label,
    string $valueHtml,
    string $holderHtml,
    $dateValue,
    bool $hasValue,
    ?string $lbHref
) use ($newRecordCutoff, $legendaryRecordCutoff): void {
    amiga_records_render_row(
        $label,
        $valueHtml,
        $holderHtml,
        amiga_records_date_or_dash($dateValue, $hasValue, $newRecordCutoff, $legendaryRecordCutoff),
        $lbHref
    );
};
$wcHolder = static fn(string $prefix): string => amiga_records_holder_player(
    (int) ($wcRecords[$prefix . 'ID'] ?? 0),
    (string) ($wcRecords[$prefix . 'Name'] ?? ''),
    $hofCountryByPlayer
);
// Ratio / average records are "best as of now (or snapshot)" and carry no
// meaningful achievement date (leadership can change when someone else's ratio
// drops). Mirror the career ratio rows above and surface a dash, never a date.
$wcRatioRow = static function (
    string $label,
    string $valueHtml,
    string $holderHtml,
    ?string $lbHref
): void {
    amiga_records_render_row($label, $valueHtml, $holderHtml, '-', $lbHref);
};

// 4.1 — career-shape totals
$wcRow('Most World Cups', amiga_records_value_or_dash($wcRecords['MostWcPlayed'] ?? null), $wcHolder('MostWcPlayed'), $wcRecords['MostWcPlayedDate'] ?? null, amiga_records_has_value($wcRecords['MostWcPlayed'] ?? 0), amiga_records_hof_lb_href('most_wc_played'));
$wcRow('Most WC golds', amiga_records_value_or_dash($wcRecords['MostWcGold'] ?? null), $wcHolder('MostWcGold'), $wcRecords['MostWcGoldDate'] ?? null, amiga_records_has_value($wcRecords['MostWcGold'] ?? 0), amiga_records_hof_lb_href('wc_gold'));
$wcRow('Most WC games', amiga_records_value_or_dash($wcRecords['MostWcGames'] ?? null), $wcHolder('MostWcGames'), $wcRecords['MostWcGamesDate'] ?? null, amiga_records_has_value($wcRecords['MostWcGames'] ?? 0), amiga_records_hof_lb_href('wc_games'));
$wcRow('Most WC wins', amiga_records_value_or_dash($wcRecords['MostWcWins'] ?? null), $wcHolder('MostWcWins'), $wcRecords['MostWcWinsDate'] ?? null, amiga_records_has_value($wcRecords['MostWcWins'] ?? 0), amiga_records_hof_lb_href('wc_wins'));
$wcRow('Most WC points', amiga_records_value_or_dash($wcRecords['MostWcPoints'] ?? null), $wcHolder('MostWcPoints'), $wcRecords['MostWcPointsDate'] ?? null, amiga_records_has_value($wcRecords['MostWcPoints'] ?? 0), amiga_records_hof_lb_href('wc_points'));
amiga_records_render_spacer_row();

// 4.2 — efficiency ratios (>= 20 WC games)
$wcRatioRow('Best WC points/game', amiga_records_fixed_or_dash($wcRecords['BestWcPtsPerGame'] ?? null, 2), $wcHolder('BestWcPtsPerGame'), amiga_records_hof_lb_href('wc_pts_per_game'));
$wcRatioRow('Best WC win rate', amiga_records_percent_or_dash($wcRecords['BestWcWinRate'] ?? null), $wcHolder('BestWcWinRate'), amiga_records_hof_lb_href('wc_win_rate'));
amiga_records_render_spacer_row();

// 4.3 — goals
$wcRow('Most WC goals', amiga_records_value_or_dash($wcRecords['MostWcGoalsFor'] ?? null), $wcHolder('MostWcGoalsFor'), $wcRecords['MostWcGoalsForDate'] ?? null, amiga_records_has_value($wcRecords['MostWcGoalsFor'] ?? 0), amiga_records_hof_lb_href('wc_goals_for'));
$wcRatioRow('Best WC goals/game', amiga_records_fixed_or_dash($wcRecords['BestWcGoalsForPerGame'] ?? null, 2), $wcHolder('BestWcGoalsForPerGame'), amiga_records_hof_lb_href('wc_gf_per_game'));
$wcRatioRow('Best WC defense/game', amiga_records_fixed_or_dash($wcRecords['BestWcGoalsAgainstPerGame'] ?? null, 2), $wcHolder('BestWcGoalsAgainstPerGame'), amiga_records_hof_lb_href('wc_ga_per_game'));
$wcRatioRow('Best WC goal diff/game', amiga_records_fixed_or_dash($wcRecords['BestWcGoalDiffPerGame'] ?? null, 2), $wcHolder('BestWcGoalDiffPerGame'), amiga_records_hof_lb_href('wc_gd_per_game'));
$wcRatioRow('Best WC goal ratio', amiga_records_fixed_or_dash($wcRecords['BestWcGoalRatio'] ?? null, 2), $wcHolder('BestWcGoalRatio'), amiga_records_hof_lb_href('wc_goal_ratio'));
amiga_records_render_spacer_row();

// 4.4 — double digits / clean sheets
$wcRow('Most WC double digits', amiga_records_value_or_dash($wcRecords['MostWcDoubleDigits'] ?? null), $wcHolder('MostWcDoubleDigits'), $wcRecords['MostWcDoubleDigitsDate'] ?? null, amiga_records_has_value($wcRecords['MostWcDoubleDigits'] ?? 0), amiga_records_hof_lb_href('wc_double_digits'));
$wcRatioRow('Best WC double digit rate', amiga_records_percent_or_dash($wcRecords['BestWcDoubleDigitsRatio'] ?? null), $wcHolder('BestWcDoubleDigitsRatio'), amiga_records_hof_lb_href('wc_dd_ratio'));
$wcRow('Most WC clean sheets', amiga_records_value_or_dash($wcRecords['MostWcCleanSheets'] ?? null), $wcHolder('MostWcCleanSheets'), $wcRecords['MostWcCleanSheetsDate'] ?? null, amiga_records_has_value($wcRecords['MostWcCleanSheets'] ?? 0), amiga_records_hof_lb_href('wc_clean_sheets'));
$wcRatioRow('Best WC clean sheet rate', amiga_records_percent_or_dash($wcRecords['BestWcCleanSheetsRatio'] ?? null), $wcHolder('BestWcCleanSheetsRatio'), amiga_records_hof_lb_href('wc_cs_ratio'));
amiga_records_render_spacer_row();

// 4.5 — opponents / victims
$wcRow('Most WC opponents', amiga_records_value_or_dash($wcRecords['MostWcOpponents'] ?? null), $wcHolder('MostWcOpponents'), $wcRecords['MostWcOpponentsDate'] ?? null, amiga_records_has_value($wcRecords['MostWcOpponents'] ?? 0), amiga_records_hof_lb_href('wc_opponents'));
$wcRow('Most WC victims', amiga_records_value_or_dash($wcRecords['MostWcVictims'] ?? null), $wcHolder('MostWcVictims'), $wcRecords['MostWcVictimsDate'] ?? null, amiga_records_has_value($wcRecords['MostWcVictims'] ?? 0), amiga_records_hof_lb_href('wc_victims'));
$wcRow('Most WC double digit victims', amiga_records_value_or_dash($wcRecords['MostWcDoubleDigitsVictims'] ?? null), $wcHolder('MostWcDoubleDigitsVictims'), $wcRecords['MostWcDoubleDigitsVictimsDate'] ?? null, amiga_records_has_value($wcRecords['MostWcDoubleDigitsVictims'] ?? 0), amiga_records_hof_lb_href('wc_dd_victims'));
$wcRow('Most WC clean sheet victims', amiga_records_value_or_dash($wcRecords['MostWcCleanSheetsVictims'] ?? null), $wcHolder('MostWcCleanSheetsVictims'), $wcRecords['MostWcCleanSheetsVictimsDate'] ?? null, amiga_records_has_value($wcRecords['MostWcCleanSheetsVictims'] ?? 0), amiga_records_hof_lb_href('wc_cs_victims'));
amiga_records_render_spacer_row();

// 4.6 — single-game (value links to Games highlights board; WC scope when applicable)
$wcRow('Most WC goals in one game', amiga_records_value_or_dash($wcRecords['MostWcGoalsInOneGame'] ?? null), $wcHolder('MostWcGoalsInOneGame'), $wcRecords['MostWcGoalsInOneGameDate'] ?? null, amiga_records_has_value($wcRecords['MostWcGoalsInOneGame'] ?? 0), amiga_records_hof_lb_href('wc_most_goals_one_game'));
$wcRow('Biggest WC winning margin', amiga_records_value_or_dash($wcRecords['BiggestWcWinDifference'] ?? null), $wcHolder('BiggestWcWinDifference'), $wcRecords['BiggestWcWinDifferenceDate'] ?? null, amiga_records_has_value($wcRecords['BiggestWcWinDifference'] ?? 0), amiga_records_hof_lb_href('wc_biggest_win_margin'));
$hasWcDraw = amiga_records_has_value($wcRecords['BiggestWcDrawSum'] ?? 0);
$wcDrawScore = $hasWcDraw
    ? ((string) ((int) $wcRecords['BiggestWcDrawSum'] / 2) . '-' . (string) ((int) $wcRecords['BiggestWcDrawSum'] / 2))
    : '-';
$wcRow(
    'Biggest WC draw',
    $wcDrawScore,
    amiga_records_holder_players_pair(
        (int) ($wcRecords['BiggestWcDrawSumIDA'] ?? 0),
        (string) ($wcRecords['BiggestWcDrawSumNameA'] ?? ''),
        (int) ($wcRecords['BiggestWcDrawSumIDB'] ?? 0),
        (string) ($wcRecords['BiggestWcDrawSumNameB'] ?? ''),
        $hofCountryByPlayer
    ),
    $wcRecords['BiggestWcDrawSumDate'] ?? null,
    $hasWcDraw,
    amiga_records_hof_lb_href('wc_biggest_draw')
);
$wcRow(
    'Biggest WC sum of goals',
    amiga_records_value_or_dash($wcRecords['BiggestWcSumOfGoals'] ?? null),
    amiga_records_holder_players_pair(
        (int) ($wcRecords['BiggestWcSumOfGoalsIDA'] ?? 0),
        (string) ($wcRecords['BiggestWcSumOfGoalsNameA'] ?? ''),
        (int) ($wcRecords['BiggestWcSumOfGoalsIDB'] ?? 0),
        (string) ($wcRecords['BiggestWcSumOfGoalsNameB'] ?? ''),
        $hofCountryByPlayer
    ),
    $wcRecords['BiggestWcSumOfGoalsDate'] ?? null,
    amiga_records_has_value($wcRecords['BiggestWcSumOfGoals'] ?? 0),
    amiga_records_hof_lb_href('wc_biggest_sum_goals')
);
amiga_records_render_spacer_row();

// 4.7 — per-event awards (HoF-only; no leaderboard link)
$wcRow('Most best attack awards', amiga_records_value_or_dash($wcRecords['MostWcBestAttackAwards'] ?? null), $wcHolder('MostWcBestAttackAwards'), $wcRecords['MostWcBestAttackAwardsDate'] ?? null, amiga_records_has_value($wcRecords['MostWcBestAttackAwards'] ?? 0), null);
$wcRow('Most best defense awards', amiga_records_value_or_dash($wcRecords['MostWcBestDefenseAwards'] ?? null), $wcHolder('MostWcBestDefenseAwards'), $wcRecords['MostWcBestDefenseAwardsDate'] ?? null, amiga_records_has_value($wcRecords['MostWcBestDefenseAwards'] ?? 0), null);
amiga_records_render_spacer_row();

// 4.8 — single-WC peaks (HoF-only; no leaderboard link)
$wcRow('Best single-WC attack', amiga_records_fixed_or_dash($wcRecords['BestSingleWcGoalsForPerGame'] ?? null, 2), $wcHolder('BestSingleWcGoalsForPerGame'), $wcRecords['BestSingleWcGoalsForPerGameDate'] ?? null, amiga_records_has_value($wcRecords['BestSingleWcGoalsForPerGame'] ?? 0), null);
$wcRow('Best single-WC defense', amiga_records_fixed_or_dash($wcRecords['BestSingleWcGoalsAgainstPerGame'] ?? null, 2), $wcHolder('BestSingleWcGoalsAgainstPerGame'), $wcRecords['BestSingleWcGoalsAgainstPerGameDate'] ?? null, amiga_records_has_value($wcRecords['BestSingleWcGoalsAgainstPerGame'] ?? 0), null);
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
