<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<script type="text/javascript" src="js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'hall-of-fame';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

<?php
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/records_hof_links.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/records_hof_table.php';

function records_has_value($value): bool
{
	return (float) $value != 0.0;
}

function records_value_or_dash($value): string
{
	return records_has_value($value) ? (string) $value : '-';
}

function records_fixed_or_dash($value, int $decimals): string
{
	return records_has_value($value) ? number_format((float) $value, $decimals) : '-';
}

function records_percent_or_dash($value): string
{
	return records_has_value($value) ? number_format(100 * (float) $value, 1) . '%' : '-';
}

function records_add_age_marker(string $text, $dateValue, int $newRecordCutoff, int $legendaryRecordCutoff): string
{
	$timestamp = strtotime((string) $dateValue);
	if ($timestamp === false) {
		return $text;
	}

	if ($timestamp >= $newRecordCutoff) {
		return $text . "<span class='blue'> (New!)</span>";
	}

	if ($timestamp < $legendaryRecordCutoff) {
		return $text . "<span class='holo'> (Legendary)</span>";
	}

	return $text;
}

function records_date_or_dash($dateValue, bool $showDate, int $newRecordCutoff, int $legendaryRecordCutoff): string
{
	if (!$showDate || $dateValue === null || $dateValue === '') {
		return '-';
	}

	$timestamp = strtotime((string) $dateValue);
	if ($timestamp === false) {
		return '-';
	}

	$text = date('M j, Y', $timestamp);

	return records_add_age_marker($text, $dateValue, $newRecordCutoff, $legendaryRecordCutoff);
}

function records_render_row(string $label, string $valueHtml, string $holderHtml, string $dateHtml, ?string $labelHelp = null, ?string $valueLbHref = null): void
{
	$valueCell = records_hof_lb_value_html($valueHtml, $valueLbHref);

	echo "    <tr>\n";
	if ($labelHelp !== null && $labelHelp !== '') {
		echo '        <td class="k2-table-helped" data-k2-help="' . htmlspecialchars($labelHelp, ENT_QUOTES, 'UTF-8') . '" data-k2-tooltip-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" tabindex="0">' . $label . "</td>\n";
	} else {
		echo '        <td>' . $label . "</td>\n";
	}
	echo '        <td class="k2-table-cell--right">' . $valueCell . "</td>\n";
	echo "        <td>" . $holderHtml . "</td>\n";
	echo "        <td class=\"k2-table-cell--right\">" . $dateHtml . "</td>\n";
	echo "    </tr>\n";

	records_hof_sync_track($valueCell, $holderHtml, $dateHtml);
}

function records_render_spacer_row(): void
{
	echo "    <tr class=\"k2-table-row--spacer\">\n";
	echo "        <td></td>\n";
	echo "        <td></td>\n";
	echo "        <td></td>\n";
	echo "        <td></td>\n";
	echo "    </tr>\n";
}

function records_holder_html(string $html): string
{
	return '<span class="k2-table-cell--pad-x-md">' . $html . '</span>';
}

function records_peak_period_age_date(string $period, string $periodKey): string
{
	switch ($period) {
		case 'year':
			return $periodKey . '-12-31';
		case 'week':
			$weekStart = strtotime($periodKey);
			return $weekStart ? date('Y-m-d', strtotime('+6 days', $weekStart)) : $periodKey;
		case 'month':
			$monthStart = strtotime($periodKey . '-01');
			return $monthStart ? date('Y-m-t', $monthStart) : $periodKey;
		default:
			return $periodKey;
	}
}

function records_render_peak_period_row(string $label, string $period, ?array $entry, int $newRecordCutoff, int $legendaryRecordCutoff, ?string $lbHref = null): void
{
	if (!$entry) {
		records_render_row($label, '-', '-', '-', null, $lbHref);

		return;
	}

	$periodKey = (string) $entry['period_key'];
	$periodText = k2_format_peak_period($period, $periodKey);

	records_render_row(
		$label,
		htmlspecialchars(records_value_or_dash($entry['games']), ENT_QUOTES, 'UTF-8'),
		records_holder_html('<a href="/player/profile.php?id=' . $entry['player_id'] . '">' . $entry['player_name'] . '</a>'),
		records_add_age_marker($periodText, records_peak_period_age_date($period, $periodKey), $newRecordCutoff, $legendaryRecordCutoff),
		null,
		$lbHref
	);
}

$recordColumns = [
	'MostGamesPlayed',
	'MostWins',
	'MostGoalsScored',
	'MostDoubleDigits',
	'MostCleanSheets',
	'MostGoalsScoredInOneGame',
	'BiggestWinDifference',
	'BiggestDrawSum',
	'BiggestSumOfGoals',
	'BiggestPeakRating',
	'LongestWinningStreak',
	'LongestNonLossStreak',
	'LongestDrawingStreak',
	'LongestDailyPlayStreak',
	'LongestWeeklyPlayStreak',
	'MostDifferentOpponents',
	'MostDifferentVictims',
	'MostDoubleDigitsVictims',
	'MostCleanSheetsVictims',
	'MostGamesPlayedID',
	'MostWinsID',
	'MostGoalsScoredID',
	'MostDoubleDigitsID',
	'MostCleanSheetsID',
	'MostGoalsScoredInOneGameID',
	'BiggestWinDifferenceID',
	'BiggestDrawSumIDA',
	'BiggestDrawSumIDB',
	'BiggestSumOfGoalsIDA',
	'BiggestSumOfGoalsIDB',
	'BiggestPeakRatingID',
	'LongestWinningStreakID',
	'LongestNonLossStreakID',
	'LongestDrawingStreakID',
	'LongestDailyPlayStreakID',
	'LongestWeeklyPlayStreakID',
	'MostDifferentOpponentsID',
	'MostDifferentVictimsID',
	'MostDoubleDigitsVictimsID',
	'MostCleanSheetsVictimsID',
	'MostGamesPlayedName',
	'MostWinsName',
	'MostGoalsScoredName',
	'MostDoubleDigitsName',
	'MostCleanSheetsName',
	'MostGoalsScoredInOneGameName',
	'BiggestWinDifferenceName',
	'BiggestDrawSumNameA',
	'BiggestDrawSumNameB',
	'BiggestSumOfGoalsNameA',
	'BiggestSumOfGoalsNameB',
	'BiggestPeakRatingName',
	'LongestWinningStreakName',
	'LongestNonLossStreakName',
	'LongestDrawingStreakName',
	'LongestDailyPlayStreakName',
	'LongestWeeklyPlayStreakName',
	'MostDifferentOpponentsName',
	'MostDifferentVictimsName',
	'MostDoubleDigitsVictimsName',
	'MostCleanSheetsVictimsName',
	'MostGamesPlayedDate',
	'MostWinsDate',
	'MostGoalsScoredDate',
	'MostDoubleDigitsDate',
	'MostCleanSheetsDate',
	'MostGoalsScoredInOneGameDate',
	'BiggestWinDifferenceDate',
	'BiggestDrawSumDate',
	'BiggestSumOfGoalsDate',
	'BiggestPeakRatingDate',
	'LongestWinningStreakDate',
	'LongestNonLossStreakDate',
	'LongestDrawingStreakDate',
	'LongestDailyPlayStreakDate',
	'LongestWeeklyPlayStreakDate',
	'MostDifferentOpponentsDate',
	'MostDifferentVictimsDate',
	'MostDoubleDigitsVictimsDate',
	'MostCleanSheetsVictimsDate',
	'MostGoalsScoredInOneGameGameID',
	'LongestDailyPlayStreakGameID',
	'LongestWeeklyPlayStreakGameID',
	'BiggestWinDifferenceGameID',
	'BiggestDrawSumGameID',
	'BiggestSumOfGoalsGameID',
];

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (mysqli_connect_errno()) {
	die("Failed to connect to MySQL: " . mysqli_connect_error());
}
$con->query("SET time_zone = '+00:00'");

$selectColumns = '`' . implode('`, `', $recordColumns) . '`';
$query = "SELECT " . $selectColumns . " FROM generalstatstable WHERE id = 1 LIMIT 1";
$result = mysqli_query($con, $query) or die("SELECT Error: ".mysqli_error($con));

$records = mysqli_fetch_assoc($result);
if (!$records) {
	die("generalstatstable row id=1 missing");
}

$newRecordCutoff = strtotime('-1 month');
$legendaryRecordCutoff = strtotime('-5 years');

include $_SERVER["DOCUMENT_ROOT"] . "/includes/records_ratio_leaders.php";
records_load_ratio_leaders($con);

$peakPeriodRecords = [];
include $_SERVER["DOCUMENT_ROOT"] . "/includes/peak_month_leaderboard_query.php";
foreach (['year', 'month', 'week', 'day'] as $period) {
	$peakPeriodError = null;
	$peakPeriodEntries = k2_peak_period_leaderboard_entries($con, $period, 1, $peakPeriodError);
	$peakPeriodRecords[$period] = $peakPeriodEntries[0] ?? null;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_play_streaks.php';

mysqli_close($con);
?>

<?php
$k2HubChapterTitle = 'Hall of Fame';
$k2HubChapterLede = 'What is the all-time online record? Who holds it, and when was it set?';
$k2HubChapterList = '<ul class="k2-hub-chapter__list">'
	. '<li>Records less than one month old are shown as &quot;<span class="blue">(New!)</span>&quot;.</li>'
	. '<li>Records more than five years old are shown as &quot;<span class="holo">(Legendary)</span>&quot;.</li>'
	. '<li>A player must play ' . (int) k2_established_min_games() . ' games for ratios and averages to take effect.</li>'
	. '</ul>';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';

// Static row labels — keep in sync with records_render_* calls below (shared col 1 width).
$k2HofRecordLabels = [
	'Most games',
	'Most games in one year',
	'Most games in one month',
	'Most games in one week',
	'Most games in one day',
	'Most days in a row',
	'Most weeks in a row',
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
	'Longest winning streak',
	'Longest undefeated streak',
	'Longest drawing streak',
	'Best attack average',
	'Best defense average',
	'Best goal ratio',
	'Highest winning frequency',
	'Highest double digit frequency',
	'Highest clean sheet frequency',
];

records_hof_sync_reset();
ob_start();
?>
<section class="server-records-panel server-records-panel--activity">
<div class="k2-table-wrap">
<table class="k2-table server-records-table k2-table--calm-stats" data-k2-anchor-col="1">
<?php records_hof_render_colgroup(); ?>
<thead>
    <tr>
		<th colspan="4" class="nohovercell k2-table-cell--left">Activity</th>
    </tr>
</thead>
<tbody class="black">
<?php
records_render_row(
	'Most games',
	(string) $records['MostGamesPlayed'],
	records_holder_html('<a href="/player/profile.php?id=' . $records['MostGamesPlayedID'] . '">' . $records['MostGamesPlayedName'] . '</a>'),
	records_date_or_dash($records['MostGamesPlayedDate'], true, $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('most_games')
);
records_render_peak_period_row('Most games in one year', 'year', $peakPeriodRecords['year'], $newRecordCutoff, $legendaryRecordCutoff, records_hof_lb_href('peak_year'));
records_render_peak_period_row('Most games in one month', 'month', $peakPeriodRecords['month'], $newRecordCutoff, $legendaryRecordCutoff, records_hof_lb_href('peak_month'));
records_render_peak_period_row('Most games in one week', 'week', $peakPeriodRecords['week'], $newRecordCutoff, $legendaryRecordCutoff, records_hof_lb_href('peak_week'));
records_render_peak_period_row('Most games in one day', 'day', $peakPeriodRecords['day'], $newRecordCutoff, $legendaryRecordCutoff, records_hof_lb_href('peak_day'));
records_render_spacer_row();

$hasDailyPlayStreak = records_has_value($records['LongestDailyPlayStreak'] ?? 0);
records_render_row(
	'Most days in a row',
	records_value_or_dash($records['LongestDailyPlayStreak'] ?? null),
	$hasDailyPlayStreak
		? records_holder_html('<a href="/player/profile.php?id=' . (int) $records['LongestDailyPlayStreakID'] . '">' . $records['LongestDailyPlayStreakName'] . '</a>')
		: '-',
	records_date_or_dash($records['LongestDailyPlayStreakDate'] ?? null, $hasDailyPlayStreak, $newRecordCutoff, $legendaryRecordCutoff),
	k2_play_streak_help_day(),
	records_hof_lb_href('play_streak_day')
);
$hasWeeklyPlayStreak = records_has_value($records['LongestWeeklyPlayStreak'] ?? 0);
records_render_row(
	'Most weeks in a row',
	records_value_or_dash($records['LongestWeeklyPlayStreak'] ?? null),
	$hasWeeklyPlayStreak
		? records_holder_html('<a href="/player/profile.php?id=' . (int) $records['LongestWeeklyPlayStreakID'] . '">' . $records['LongestWeeklyPlayStreakName'] . '</a>')
		: '-',
	records_date_or_dash($records['LongestWeeklyPlayStreakDate'] ?? null, $hasWeeklyPlayStreak, $newRecordCutoff, $legendaryRecordCutoff),
	k2_play_streak_help_week(),
	records_hof_lb_href('play_streak_week')
);
records_render_spacer_row();

records_render_row(
	'Most wins',
	records_value_or_dash($records['MostWins']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['MostWinsID'] . '">' . $records['MostWinsName'] . '</a>'),
	records_date_or_dash($records['MostWinsDate'], records_has_value($records['MostWins']), $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('most_wins')
);
records_render_row(
	'Most goals',
	records_value_or_dash($records['MostGoalsScored']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['MostGoalsScoredID'] . '">' . $records['MostGoalsScoredName'] . '</a>'),
	records_date_or_dash($records['MostGoalsScoredDate'], records_has_value($records['MostGoalsScored']), $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('most_goals')
);
records_render_row(
	'Most double digits',
	records_value_or_dash($records['MostDoubleDigits']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['MostDoubleDigitsID'] . '">' . $records['MostDoubleDigitsName'] . '</a>'),
	records_date_or_dash($records['MostDoubleDigitsDate'], records_has_value($records['MostDoubleDigits']), $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('most_dd')
);
records_render_row(
	'Most clean sheets',
	records_value_or_dash($records['MostCleanSheets']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['MostCleanSheetsID'] . '">' . $records['MostCleanSheetsName'] . '</a>'),
	records_date_or_dash($records['MostCleanSheetsDate'], records_has_value($records['MostCleanSheets']), $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('most_cs')
);
records_render_spacer_row();

records_render_row(
	'Most opponents',
	(string) $records['MostDifferentOpponents'],
	records_holder_html('<a href="/player/profile.php?id=' . $records['MostDifferentOpponentsID'] . '">' . $records['MostDifferentOpponentsName'] . '</a>'),
	records_date_or_dash($records['MostDifferentOpponentsDate'], true, $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('most_opponents')
);
records_render_row(
	'Most victims',
	records_value_or_dash($records['MostDifferentVictims']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['MostDifferentVictimsID'] . '">' . $records['MostDifferentVictimsName'] . '</a>'),
	records_date_or_dash($records['MostDifferentVictimsDate'], records_has_value($records['MostDifferentVictims']), $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('most_victims')
);
records_render_row(
	'Most double digit victims',
	records_value_or_dash($records['MostDoubleDigitsVictims']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['MostDoubleDigitsVictimsID'] . '">' . $records['MostDoubleDigitsVictimsName'] . '</a>'),
	records_date_or_dash($records['MostDoubleDigitsVictimsDate'], records_has_value($records['MostDoubleDigitsVictims']), $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('most_dd_victims')
);
records_render_row(
	'Most clean sheet victims',
	records_value_or_dash($records['MostCleanSheetsVictims']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['MostCleanSheetsVictimsID'] . '">' . $records['MostCleanSheetsVictimsName'] . '</a>'),
	records_date_or_dash($records['MostCleanSheetsVictimsDate'], records_has_value($records['MostCleanSheetsVictims']), $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('most_cs_victims')
);
?>
</tbody>
</table>
</div><!-- .k2-table-wrap -->
</section>

<section class="server-records-panel server-records-panel--performance">
<div class="k2-table-wrap">
<table class="k2-table server-records-table k2-table--calm-stats" data-k2-anchor-col="1">
<?php records_hof_render_colgroup(); ?>
<thead>
    <tr>
		<th colspan="4" class="nohovercell k2-table-cell--left">Performance</th>
    </tr>
</thead>
<tbody class="black">
<?php

$hasMostGoalsOneGame = records_has_value($records['MostGoalsScoredInOneGame']);
records_render_row(
	'Most goals in one game',
	records_value_or_dash($records['MostGoalsScoredInOneGame']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['MostGoalsScoredInOneGameID'] . '">' . $records['MostGoalsScoredInOneGameName'] . '</a>'),
	records_date_or_dash($records['MostGoalsScoredInOneGameDate'], $hasMostGoalsOneGame, $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('most_goals_one_game')
);
$hasBiggestWinMargin = records_has_value($records['BiggestWinDifference']);
records_render_row(
	'Biggest winning margin',
	records_value_or_dash($records['BiggestWinDifference']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['BiggestWinDifferenceID'] . '">' . $records['BiggestWinDifferenceName'] . '</a>'),
	records_date_or_dash($records['BiggestWinDifferenceDate'], $hasBiggestWinMargin, $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('biggest_win_margin')
);
$hasBiggestDraw = records_has_value($records['BiggestDrawSumGameID']);
$biggestDrawScore = $hasBiggestDraw
	? ((string) ($records['BiggestDrawSum'] / 2) . '-' . (string) ($records['BiggestDrawSum'] / 2))
	: '-';
records_render_row(
	'Biggest draw',
	$biggestDrawScore,
	records_holder_html('<a href="/player/profile.php?id=' . $records['BiggestDrawSumIDA'] . '">' . $records['BiggestDrawSumNameA'] . '</a> / <a href="/player/profile.php?id=' . $records['BiggestDrawSumIDB'] . '">' . $records['BiggestDrawSumNameB'] . '</a>'),
	records_date_or_dash($records['BiggestDrawSumDate'], $hasBiggestDraw, $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('biggest_draw')
);
$hasBiggestSumGoals = records_has_value($records['BiggestSumOfGoals']);
records_render_row(
	'Biggest sum of goals',
	records_value_or_dash($records['BiggestSumOfGoals']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['BiggestSumOfGoalsIDA'] . '">' . $records['BiggestSumOfGoalsNameA'] . '</a> / <a href="/player/profile.php?id=' . $records['BiggestSumOfGoalsIDB'] . '">' . $records['BiggestSumOfGoalsNameB'] . '</a>'),
	records_date_or_dash($records['BiggestSumOfGoalsDate'], $hasBiggestSumGoals, $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('biggest_sum_goals')
);
records_render_spacer_row();

records_render_row(
	'Highest peak rating',
	number_format((float) $records['BiggestPeakRating'], 0, '.', ''),
	records_holder_html('<a href="/player/profile.php?id=' . $records['BiggestPeakRatingID'] . '">' . $records['BiggestPeakRatingName'] . '</a>'),
	records_date_or_dash($records['BiggestPeakRatingDate'], true, $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('peak_rating')
);
records_render_row(
	'Longest winning streak',
	records_value_or_dash($records['LongestWinningStreak']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['LongestWinningStreakID'] . '">' . $records['LongestWinningStreakName'] . '</a>'),
	records_date_or_dash($records['LongestWinningStreakDate'], records_has_value($records['LongestWinningStreak']), $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('win_streak')
);
records_render_row(
	'Longest undefeated streak',
	(string) $records['LongestNonLossStreak'],
	records_holder_html('<a href="/player/profile.php?id=' . $records['LongestNonLossStreakID'] . '">' . $records['LongestNonLossStreakName'] . '</a>'),
	records_date_or_dash($records['LongestNonLossStreakDate'], true, $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('non_loss_streak')
);
records_render_row(
	'Longest drawing streak',
	records_value_or_dash($records['LongestDrawingStreak']),
	records_holder_html('<a href="/player/profile.php?id=' . $records['LongestDrawingStreakID'] . '">' . $records['LongestDrawingStreakName'] . '</a>'),
	records_date_or_dash($records['LongestDrawingStreakDate'], records_has_value($records['LongestDrawingStreak']), $newRecordCutoff, $legendaryRecordCutoff),
	null,
	records_hof_lb_href('draw_streak')
);
records_render_spacer_row();

records_render_row(
	'Best attack average',
	records_fixed_or_dash($BiggestGoalsForAverage, 2),
	records_holder_html('<a href="/player/profile.php?id=' . $BiggestGoalsForAverageID . '">' . $BiggestGoalsForAverageName . '</a>'),
	'-',
	null,
	records_hof_lb_href('attack_avg')
);
records_render_row(
	'Best defense average',
	records_fixed_or_dash($SmallestGoalsAgainstAverage, 2),
	records_holder_html('<a href="/player/profile.php?id=' . $SmallestGoalsAgainstAverageID . '">' . $SmallestGoalsAgainstAverageName . '</a>'),
	'-',
	null,
	records_hof_lb_href('defense_avg')
);
records_render_row(
	'Best goal ratio',
	records_fixed_or_dash($BiggestGoalRatio, 2),
	records_holder_html('<a href="/player/profile.php?id=' . $BiggestGoalRatioID . '">' . $BiggestGoalRatioName . '</a>'),
	'-',
	null,
	records_hof_lb_href('goal_ratio')
);
records_render_spacer_row();

records_render_row(
	'Highest winning frequency',
	records_percent_or_dash($BiggestWinRatio),
	records_holder_html('<a href="/player/profile.php?id=' . $BiggestWinRatioID . '">' . $BiggestWinRatioName . '</a>'),
	'-',
	null,
	records_hof_lb_href('win_ratio')
);
records_render_row(
	'Highest double digit frequency',
	records_percent_or_dash($BiggestDoubleDigitsRatio),
	records_holder_html('<a href="/player/profile.php?id=' . $BiggestDoubleDigitsRatioID . '">' . $BiggestDoubleDigitsRatioName . '</a>'),
	'-',
	null,
	records_hof_lb_href('dd_ratio')
);
records_render_row(
	'Highest clean sheet frequency',
	records_percent_or_dash($BiggestCleanSheetsRatio),
	records_holder_html('<a href="/player/profile.php?id=' . $BiggestCleanSheetsRatioID . '">' . $BiggestCleanSheetsRatioName . '</a>'),
	'-',
	null,
	records_hof_lb_href('cs_ratio')
);
?>
</tbody>
</table>
</div><!-- .k2-table-wrap -->
</section>
<?php
$k2HofTablesHtml = ob_get_clean();
$k2HofSyncWidths = records_hof_sync_compute_widths($k2HofRecordLabels);
?>
<div class="server-records-panels server-records-panels--sync-cols" style="<?php echo htmlspecialchars(records_hof_sync_style_attr($k2HofSyncWidths), ENT_QUOTES, 'UTF-8'); ?>">
<?php echo $k2HofTablesHtml; ?>
</div><!-- .server-records-panels -->



</div><!-- .k2-page-nav -->
</body>
</html>




