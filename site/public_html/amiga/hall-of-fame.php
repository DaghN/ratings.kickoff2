<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga 500 — Hall of Fame</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'hall-of-fame';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_records_common.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_records_hof_links.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_records_ratio_leaders.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$recordColumns = [
    'MostGamesPlayed',
    'MostWins',
    'MostGoalsScored',
    'MostDoubleDigits',
    'MostCleanSheets',
    'MostDifferentOpponents',
    'MostDifferentVictims',
    'MostDoubleDigitsVictims',
    'MostCleanSheetsVictims',
    'MostGoalsScoredInOneGame',
    'BiggestWinDifference',
    'BiggestDrawSum',
    'BiggestSumOfGoals',
    'BiggestPeakRating',
    'MostGamesPlayedID',
    'MostWinsID',
    'MostGoalsScoredID',
    'MostDoubleDigitsID',
    'MostCleanSheetsID',
    'MostDifferentOpponentsID',
    'MostDifferentVictimsID',
    'MostDoubleDigitsVictimsID',
    'MostCleanSheetsVictimsID',
    'MostGoalsScoredInOneGameID',
    'BiggestWinDifferenceID',
    'BiggestDrawSumIDA',
    'BiggestDrawSumIDB',
    'BiggestSumOfGoalsIDA',
    'BiggestSumOfGoalsIDB',
    'BiggestPeakRatingID',
    'MostGamesPlayedName',
    'MostWinsName',
    'MostGoalsScoredName',
    'MostDoubleDigitsName',
    'MostCleanSheetsName',
    'MostDifferentOpponentsName',
    'MostDifferentVictimsName',
    'MostDoubleDigitsVictimsName',
    'MostCleanSheetsVictimsName',
    'MostGoalsScoredInOneGameName',
    'BiggestWinDifferenceName',
    'BiggestDrawSumNameA',
    'BiggestDrawSumNameB',
    'BiggestSumOfGoalsNameA',
    'BiggestSumOfGoalsNameB',
    'BiggestPeakRatingName',
    'MostGamesPlayedDate',
    'MostWinsDate',
    'MostGoalsScoredDate',
    'MostDoubleDigitsDate',
    'MostCleanSheetsDate',
    'MostDifferentOpponentsDate',
    'MostDifferentVictimsDate',
    'MostDoubleDigitsVictimsDate',
    'MostCleanSheetsVictimsDate',
    'MostGoalsScoredInOneGameDate',
    'BiggestWinDifferenceDate',
    'BiggestDrawSumDate',
    'BiggestSumOfGoalsDate',
    'BiggestPeakRatingDate',
    'GamesPlayed',
];

$selectColumns = '`' . implode('`, `', $recordColumns) . '`';
$query = 'SELECT ' . $selectColumns . ' FROM amiga_generalstats WHERE id = 1 LIMIT 1';
$result = k2_query_or_public_error($con, $query, 'amiga generalstats');
$records = mysqli_fetch_assoc($result);
mysqli_free_result($result);
if (!$records) {
    mysqli_close($con);
    http_response_code(503);
    exit('Hall of Fame data is not available yet. Run python -m scripts.amiga replay.');
}

$newRecordCutoff = strtotime('-1 month');
$legendaryRecordCutoff = strtotime('-5 years');

amiga_records_load_ratio_leaders($con);
$wcLeaders = amiga_records_wc_medal_leaders($con);
mysqli_close($con);
?>

<header class="k2-hub-page-intro-head" style="padding:0 1.25rem">
	<p class="k2-hub-page-intro">Single-holder career and single-game records for the offline Amiga ladder (<?php echo number_format((int) ($records['GamesPlayed'] ?? 0)); ?> rated games). Records under one month old show as &quot;<span class="blue">(New!)</span>&quot;; records over five years old as &quot;<span class="holo">(Legendary)</span>&quot;. Ratio leaders require <?php echo (int) k2_established_min_games(); ?>+ games. Match and calendar streaks are omitted.</p>
</header>

<div class="server-records-panels">
<section class="server-records-panel server-records-panel--activity">
<div class="k2-table-wrap">
<table class="k2-table server-records-table k2-table--calm-stats" data-k2-anchor-col="1">
<thead>
    <tr>
		<th colspan="4" class="nohovercell k2-table-cell--left">Career records</th>
    </tr>
</thead>
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
?>
</tbody>
</table>
</div>
</section>

<section class="server-records-panel server-records-panel--performance">
<div class="k2-table-wrap">
<table class="k2-table server-records-table k2-table--calm-stats" data-k2-anchor-col="1">
<thead>
    <tr>
		<th colspan="4" class="nohovercell k2-table-cell--left">Peak performance &amp; ratios</th>
    </tr>
</thead>
<tbody class="black">
<?php
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
    amiga_records_fixed_or_dash($BiggestGoalsForAverage ?? null, 2),
    amiga_records_holder_html(amiga_records_profile_link((int) ($BiggestGoalsForAverageID ?? 0), (string) ($BiggestGoalsForAverageName ?? ''))),
    '-',
    amiga_records_hof_lb_href('attack_avg')
);
amiga_records_render_row(
    'Best defense average',
    amiga_records_fixed_or_dash($SmallestGoalsAgainstAverage ?? null, 2),
    amiga_records_holder_html(amiga_records_profile_link((int) ($SmallestGoalsAgainstAverageID ?? 0), (string) ($SmallestGoalsAgainstAverageName ?? ''))),
    '-',
    amiga_records_hof_lb_href('defense_avg')
);
amiga_records_render_row(
    'Best goal ratio',
    amiga_records_fixed_or_dash($BiggestGoalRatio ?? null, 2),
    amiga_records_holder_html(amiga_records_profile_link((int) ($BiggestGoalRatioID ?? 0), (string) ($BiggestGoalRatioName ?? ''))),
    '-',
    amiga_records_hof_lb_href('goal_ratio')
);
amiga_records_render_spacer_row();
amiga_records_render_row(
    'Highest winning frequency',
    amiga_records_percent_or_dash($BiggestWinRatio ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($BiggestWinRatioID ?? 0), (string) ($BiggestWinRatioName ?? ''))),
    '-',
    amiga_records_hof_lb_href('win_ratio')
);
amiga_records_render_row(
    'Highest double digit frequency',
    amiga_records_percent_or_dash($BiggestDoubleDigitsRatio ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($BiggestDoubleDigitsRatioID ?? 0), (string) ($BiggestDoubleDigitsRatioName ?? ''))),
    '-',
    amiga_records_hof_lb_href('dd_ratio')
);
amiga_records_render_row(
    'Highest clean sheet frequency',
    amiga_records_percent_or_dash($BiggestCleanSheetsRatio ?? null),
    amiga_records_holder_html(amiga_records_profile_link((int) ($BiggestCleanSheetsRatioID ?? 0), (string) ($BiggestCleanSheetsRatioName ?? ''))),
    '-',
    amiga_records_hof_lb_href('cs_ratio')
);
?>
</tbody>
</table>
</div>
</section>

<section class="server-records-panel server-records-panel--honours">
<div class="k2-table-wrap">
<table class="k2-table server-records-table k2-table--calm-stats" data-k2-anchor-col="1">
<thead>
    <tr>
		<th colspan="3" class="nohovercell k2-table-cell--left">World Cup medals <span style="font-weight:normal;font-size:0.9em">(<a class="k2-link-star" href="/amiga/leaderboards/tournament-honours.php">full leaderboard</a>)</span></th>
    </tr>
    <tr>
        <th class="k2-table-cell--left">Medal</th>
        <th class="k2-table-cell--right">Count</th>
        <th class="k2-table-cell--left">Player</th>
    </tr>
</thead>
<tbody class="black">
<?php
$medalLabels = ['gold' => 'Gold', 'silver' => 'Silver', 'bronze' => 'Bronze'];
foreach ($medalLabels as $key => $label) {
    $leader = $wcLeaders[$key] ?? null;
    if ($leader === null) {
        echo '    <tr><td>' . $label . '</td><td class="k2-table-cell--right">-</td><td>-</td></tr>' . "\n";
        continue;
    }
    echo '    <tr><td>' . $label . '</td><td class="k2-table-cell--right">'
        . (int) $leader['medal_count'] . '</td><td>'
        . amiga_records_holder_html(amiga_records_profile_link((int) $leader['player_id'], (string) $leader['name']))
        . "</td></tr>\n";
}
?>
</tbody>
</table>
</div>
</section>
</div>

</div><!-- .k2-page-nav -->

</body>
</html>
