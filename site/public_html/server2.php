<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<link href="stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'records';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

<?php
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";

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

function records_render_row(string $label, string $valueHtml, string $holderHtml, string $dateHtml, ?string $labelHelp = null): void
{
	echo "    <tr>\n";
	if ($labelHelp !== null && $labelHelp !== '') {
		echo '        <td class="k2-table-helped" data-k2-help="' . htmlspecialchars($labelHelp, ENT_QUOTES, 'UTF-8') . '" data-k2-tooltip-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" tabindex="0">' . $label . "</td>\n";
	} else {
		echo "        <td>" . $label . "</td>\n";
	}
	echo "        <td class=\"k2-table-cell--right\">" . $valueHtml . "</td>\n";
	echo "        <td>" . $holderHtml . "</td>\n";
	echo "        <td class=\"k2-table-cell--right\">" . $dateHtml . "</td>\n";
	echo "    </tr>\n";
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

function records_render_peak_period_row(string $label, string $period, ?array $entry, int $newRecordCutoff, int $legendaryRecordCutoff): void
{
	if (!$entry) {
		records_render_row($label, '-', '-', '-');

		return;
	}

	$periodKey = (string) $entry['period_key'];
	$periodText = k2_format_peak_period($period, $periodKey);

	records_render_row(
		$label,
		records_value_or_dash($entry['games']),
		records_holder_html('<a href="individual1.php?id=' . $entry['player_id'] . '">' . $entry['player_name'] . '</a>'),
		records_add_age_marker($periodText, records_peak_period_age_date($period, $periodKey), $newRecordCutoff, $legendaryRecordCutoff)
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

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_play_streaks.php';
$k2DdMerchantAchievers = k2_milestone_dd_merchant_achievers($con);

mysqli_close($con);
?>


<div class="server-records-panels">
<section class="server-records-panel server-records-panel--activity">
<div class="k2-table-wrap">
<table class="k2-table server-records-table">
<thead>
    <tr>
		<th colspan="4" class="nohovercell k2-table-cell--left">Peak activity</th>
    </tr>
</thead>
<tbody class="black">
<?php
records_render_row(
	'Most games',
	(string) $records['MostGamesPlayed'],
	records_holder_html('<a href="individual1.php?id=' . $records['MostGamesPlayedID'] . '">' . $records['MostGamesPlayedName'] . '</a>'),
	records_date_or_dash($records['MostGamesPlayedDate'], true, $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_peak_period_row('Most games in one year', 'year', $peakPeriodRecords['year'], $newRecordCutoff, $legendaryRecordCutoff);
records_render_peak_period_row('Most games in one month', 'month', $peakPeriodRecords['month'], $newRecordCutoff, $legendaryRecordCutoff);
records_render_peak_period_row('Most games in one week', 'week', $peakPeriodRecords['week'], $newRecordCutoff, $legendaryRecordCutoff);
records_render_peak_period_row('Most games in one day', 'day', $peakPeriodRecords['day'], $newRecordCutoff, $legendaryRecordCutoff);
records_render_spacer_row();

$hasDailyPlayStreak = records_has_value($records['LongestDailyPlayStreak'] ?? 0);
records_render_row(
	'Most days in a row',
	records_value_or_dash($records['LongestDailyPlayStreak'] ?? null),
	$hasDailyPlayStreak
		? records_holder_html('<a href="individual1.php?id=' . (int) $records['LongestDailyPlayStreakID'] . '">' . $records['LongestDailyPlayStreakName'] . '</a>')
		: '-',
	records_date_or_dash($records['LongestDailyPlayStreakDate'] ?? null, $hasDailyPlayStreak, $newRecordCutoff, $legendaryRecordCutoff),
	k2_play_streak_help_day()
);
$hasWeeklyPlayStreak = records_has_value($records['LongestWeeklyPlayStreak'] ?? 0);
records_render_row(
	'Most weeks in a row',
	records_value_or_dash($records['LongestWeeklyPlayStreak'] ?? null),
	$hasWeeklyPlayStreak
		? records_holder_html('<a href="individual1.php?id=' . (int) $records['LongestWeeklyPlayStreakID'] . '">' . $records['LongestWeeklyPlayStreakName'] . '</a>')
		: '-',
	records_date_or_dash($records['LongestWeeklyPlayStreakDate'] ?? null, $hasWeeklyPlayStreak, $newRecordCutoff, $legendaryRecordCutoff),
	k2_play_streak_help_week()
);
records_render_spacer_row();

records_render_row(
	'Most wins',
	records_value_or_dash($records['MostWins']),
	records_holder_html('<a href="individual1.php?id=' . $records['MostWinsID'] . '">' . $records['MostWinsName'] . '</a>'),
	records_date_or_dash($records['MostWinsDate'], records_has_value($records['MostWins']), $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_row(
	'Most goals',
	records_value_or_dash($records['MostGoalsScored']),
	records_holder_html('<a href="individual1.php?id=' . $records['MostGoalsScoredID'] . '">' . $records['MostGoalsScoredName'] . '</a>'),
	records_date_or_dash($records['MostGoalsScoredDate'], records_has_value($records['MostGoalsScored']), $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_row(
	'Most double digits',
	records_value_or_dash($records['MostDoubleDigits']),
	records_holder_html('<a href="individual1.php?id=' . $records['MostDoubleDigitsID'] . '">' . $records['MostDoubleDigitsName'] . '</a>'),
	records_date_or_dash($records['MostDoubleDigitsDate'], records_has_value($records['MostDoubleDigits']), $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_row(
	'Most clean sheets',
	records_value_or_dash($records['MostCleanSheets']),
	records_holder_html('<a href="individual1.php?id=' . $records['MostCleanSheetsID'] . '">' . $records['MostCleanSheetsName'] . '</a>'),
	records_date_or_dash($records['MostCleanSheetsDate'], records_has_value($records['MostCleanSheets']), $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_spacer_row();

records_render_row(
	'Most opponents',
	(string) $records['MostDifferentOpponents'],
	records_holder_html('<a href="individual1.php?id=' . $records['MostDifferentOpponentsID'] . '">' . $records['MostDifferentOpponentsName'] . '</a>'),
	records_date_or_dash($records['MostDifferentOpponentsDate'], true, $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_row(
	'Most victims',
	records_value_or_dash($records['MostDifferentVictims']),
	records_holder_html('<a href="individual1.php?id=' . $records['MostDifferentVictimsID'] . '">' . $records['MostDifferentVictimsName'] . '</a>'),
	records_date_or_dash($records['MostDifferentVictimsDate'], records_has_value($records['MostDifferentVictims']), $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_row(
	'Most double digit victims',
	records_value_or_dash($records['MostDoubleDigitsVictims']),
	records_holder_html('<a href="individual1.php?id=' . $records['MostDoubleDigitsVictimsID'] . '">' . $records['MostDoubleDigitsVictimsName'] . '</a>'),
	records_date_or_dash($records['MostDoubleDigitsVictimsDate'], records_has_value($records['MostDoubleDigitsVictims']), $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_row(
	'Most clean sheet victims',
	records_value_or_dash($records['MostCleanSheetsVictims']),
	records_holder_html('<a href="individual1.php?id=' . $records['MostCleanSheetsVictimsID'] . '">' . $records['MostCleanSheetsVictimsName'] . '</a>'),
	records_date_or_dash($records['MostCleanSheetsVictimsDate'], records_has_value($records['MostCleanSheetsVictims']), $newRecordCutoff, $legendaryRecordCutoff)
);
?>
</tbody>
</table>
</div><!-- .k2-table-wrap -->
</section>

<section class="server-records-panel server-records-panel--performance">
<div class="k2-table-wrap">
<table class="k2-table server-records-table">
<thead>
    <tr>
		<th colspan="4" class="nohovercell k2-table-cell--left">Peak performance</th>
    </tr>
</thead>
<tbody class="black">
<?php

records_render_row(
	'Most goals in one game',
	records_value_or_dash($records['MostGoalsScoredInOneGame']),
	records_holder_html('<a href="individual1.php?id=' . $records['MostGoalsScoredInOneGameID'] . '">' . $records['MostGoalsScoredInOneGameName'] . '</a>'),
	records_date_or_dash($records['MostGoalsScoredInOneGameDate'], records_has_value($records['MostGoalsScoredInOneGame']), $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_row(
	'Biggest winning margin',
	records_value_or_dash($records['BiggestWinDifference']),
	records_holder_html('<a href="individual1.php?id=' . $records['BiggestWinDifferenceID'] . '">' . $records['BiggestWinDifferenceName'] . '</a>'),
	records_date_or_dash($records['BiggestWinDifferenceDate'], records_has_value($records['BiggestWinDifference']), $newRecordCutoff, $legendaryRecordCutoff)
);
$biggestDrawScore = records_has_value($records['BiggestDrawSumGameID'])
	? ((string) ($records['BiggestDrawSum'] / 2) . '-' . (string) ($records['BiggestDrawSum'] / 2))
	: '-';
records_render_row(
	'Biggest draw',
	$biggestDrawScore,
	records_holder_html('<a href="individual1.php?id=' . $records['BiggestDrawSumIDA'] . '">' . $records['BiggestDrawSumNameA'] . '</a> / <a href="individual1.php?id=' . $records['BiggestDrawSumIDB'] . '">' . $records['BiggestDrawSumNameB'] . '</a>'),
	records_date_or_dash($records['BiggestDrawSumDate'], records_has_value($records['BiggestDrawSumGameID']), $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_row(
	'Biggest sum of goals',
	(string) $records['BiggestSumOfGoals'],
	records_holder_html('<a href="individual1.php?id=' . $records['BiggestSumOfGoalsIDA'] . '">' . $records['BiggestSumOfGoalsNameA'] . '</a> / <a href="individual1.php?id=' . $records['BiggestSumOfGoalsIDB'] . '">' . $records['BiggestSumOfGoalsNameB'] . '</a>'),
	records_date_or_dash($records['BiggestSumOfGoalsDate'], true, $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_spacer_row();

records_render_row(
	'Highest peak rating',
	number_format((float) $records['BiggestPeakRating'], 0, '.', ''),
	records_holder_html('<a href="individual1.php?id=' . $records['BiggestPeakRatingID'] . '">' . $records['BiggestPeakRatingName'] . '</a>'),
	records_date_or_dash($records['BiggestPeakRatingDate'], true, $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_row(
	'Longest winning streak',
	records_value_or_dash($records['LongestWinningStreak']),
	records_holder_html('<a href="individual1.php?id=' . $records['LongestWinningStreakID'] . '">' . $records['LongestWinningStreakName'] . '</a>'),
	records_date_or_dash($records['LongestWinningStreakDate'], records_has_value($records['LongestWinningStreak']), $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_row(
	'Longest undefeated streak',
	(string) $records['LongestNonLossStreak'],
	records_holder_html('<a href="individual1.php?id=' . $records['LongestNonLossStreakID'] . '">' . $records['LongestNonLossStreakName'] . '</a>'),
	records_date_or_dash($records['LongestNonLossStreakDate'], true, $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_row(
	'Longest drawing streak',
	records_value_or_dash($records['LongestDrawingStreak']),
	records_holder_html('<a href="individual1.php?id=' . $records['LongestDrawingStreakID'] . '">' . $records['LongestDrawingStreakName'] . '</a>'),
	records_date_or_dash($records['LongestDrawingStreakDate'], records_has_value($records['LongestDrawingStreak']), $newRecordCutoff, $legendaryRecordCutoff)
);
records_render_spacer_row();

records_render_row(
	'Best attack average',
	records_fixed_or_dash($BiggestGoalsForAverage, 2),
	records_holder_html('<a href="individual1.php?id=' . $BiggestGoalsForAverageID . '">' . $BiggestGoalsForAverageName . '</a>'),
	'-'
);
records_render_row(
	'Best defense average',
	records_fixed_or_dash($SmallestGoalsAgainstAverage, 2),
	records_holder_html('<a href="individual1.php?id=' . $SmallestGoalsAgainstAverageID . '">' . $SmallestGoalsAgainstAverageName . '</a>'),
	'-'
);
records_render_row(
	'Best goal ratio',
	records_fixed_or_dash($BiggestGoalRatio, 2),
	records_holder_html('<a href="individual1.php?id=' . $BiggestGoalRatioID . '">' . $BiggestGoalRatioName . '</a>'),
	'-'
);
records_render_spacer_row();

records_render_row(
	'Highest winning frequency',
	records_percent_or_dash($BiggestWinRatio),
	records_holder_html('<a href="individual1.php?id=' . $BiggestWinRatioID . '">' . $BiggestWinRatioName . '</a>'),
	'-'
);
records_render_row(
	'Highest double digit frequency',
	records_percent_or_dash($BiggestDoubleDigitsRatio),
	records_holder_html('<a href="individual1.php?id=' . $BiggestDoubleDigitsRatioID . '">' . $BiggestDoubleDigitsRatioName . '</a>'),
	'-'
);
records_render_row(
	'Highest clean sheet frequency',
	records_percent_or_dash($BiggestCleanSheetsRatio),
	records_holder_html('<a href="individual1.php?id=' . $BiggestCleanSheetsRatioID . '">' . $BiggestCleanSheetsRatioName . '</a>'),
	'-'
);
?>
</tbody>
</table>
</div><!-- .k2-table-wrap -->
</section>
</div><!-- .server-records-panels -->

<section class="k2-ms-achievers" aria-labelledby="k2-ms-achievers-heading">
	<h2 id="k2-ms-achievers-heading" class="k2-ms-achievers__title">Milestone achievers</h2>
	<p class="k2-ms-achievers__hint">
		Players who unlocked a curated milestone — many can hold the same feat (unlike single-holder server records above).
		Trial list: <strong>Double Digit Merchant</strong> (first rated game with 10+ goals).
	</p>
<?php if ($k2DdMerchantAchievers === []) { ?>
	<p class="k2-ms-meta-hint">No unlocks recorded yet.</p>
<?php } else { ?>
	<div class="k2-table-wrap">
	<table class="k2-table k2-table--numeric-default">
	<thead>
		<tr>
			<th data-k2-help="Order unlocked, newest at the top (highest number).">#</th>
			<th class="k2-table-cell--left">Player</th>
			<th class="k2-table-cell--left">Unlocked (UTC)</th>
			<th class="k2-table-cell--left">Match</th>
			<th data-k2-help="Rated game that unlocked this milestone.">Game</th>
		</tr>
	</thead>
	<tbody class="black">
<?php
    $k2DdMemberNum = count($k2DdMerchantAchievers);
    foreach ($k2DdMerchantAchievers as $ach) {
        ?>
		<tr>
			<td><?php echo (int) $k2DdMemberNum; ?></td>
			<td class="k2-table-cell--left"><?php echo k2_player_link($ach['player_id'], $ach['player_name']); ?></td>
			<td><?php echo k2_h($ach['achieved_label']); ?></td>
			<td class="k2-table-cell--left k2-ms-achiever-match-cell"><?php echo $ach['match_html']; ?></td>
			<td><?php echo $ach['game_id_html']; ?></td>
		</tr>
<?php
        --$k2DdMemberNum;
    }
    ?>
	</tbody>
	</table>
	</div>
<?php } ?>
</section>

Records that are less than one month old are displayed as "<span class="blue">(New!)</span>".
<br />
Records that are more than 5 years old are displayed as "<span class="holo">(Legendary)</span>".
<br />
A player must play 30 games for ratios and averages to take effect.





</div><!-- .k2-page-nav -->
</body>
</html>




