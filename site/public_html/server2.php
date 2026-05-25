<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
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

function records_date_or_dash($dateValue, bool $showDate, int $newRecordCutoff): string
{
	if (!$showDate || $dateValue === null || $dateValue === '') {
		return '-';
	}

	$timestamp = strtotime((string) $dateValue);
	if ($timestamp === false) {
		return '-';
	}

	$text = date('M j, Y', $timestamp);
	if ($timestamp >= $newRecordCutoff) {
		$text .= "<span class='blue'> (New!)</span>";
	}

	return $text;
}

function records_render_row(string $label, string $valueHtml, string $holderHtml, string $dateHtml): void
{
	echo "    <tr style=\"text-align:left;\">\n";
	echo "        <td>" . $label . "</td>\n";
	echo "        <td style=\"text-align:right;\">" . $valueHtml . "</td>\n";
	echo "        <td>" . $holderHtml . "</td>\n";
	echo "        <td style=\"text-align:right;\">" . $dateHtml . "</td>\n";
	echo "    </tr>\n";
}

function records_render_spacer_row(): void
{
	echo "    <tr style=\"text-align:left;\">\n";
	echo "        <td>&nbsp;</td>\n";
	echo "        <td></td>\n";
	echo "        <td></td>\n";
	echo "        <td></td>\n";
	echo "    </tr>\n";
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
	'MostDifferentOpponentsDate',
	'MostDifferentVictimsDate',
	'MostDoubleDigitsVictimsDate',
	'MostCleanSheetsVictimsDate',
	'MostGoalsScoredInOneGameGameID',
	'BiggestWinDifferenceGameID',
	'BiggestDrawSumGameID',
	'BiggestSumOfGoalsGameID',
];

$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (mysqli_connect_errno()) {
	die("Failed to connect to MySQL: " . mysqli_connect_error());
}

$selectColumns = '`' . implode('`, `', $recordColumns) . '`';
$query = "SELECT " . $selectColumns . " FROM generalstatstable WHERE id = 1 LIMIT 1";
$result = mysqli_query($con, $query) or die("SELECT Error: ".mysqli_error($con));

$records = mysqli_fetch_assoc($result);
if (!$records) {
	die("generalstatstable row id=1 missing");
}

$newRecordCutoff = strtotime('-1 month');

include $_SERVER["DOCUMENT_ROOT"] . "/includes/records_ratio_leaders.php";
records_load_ratio_leaders($con);

mysqli_close($con);
?>


<div class="k2-table-wrap">

<table class="k2-table"> 

<thead>
    <tr >
    	<th colspan="4"  class="nohovercell" style="text-align:left;">Server Records</th>
    </tr>
</thead>

<tbody class="black">
<?php
$pad = '&nbsp;&nbsp;&nbsp;';

records_render_row(
	'Most games',
	(string) $records['MostGamesPlayed'],
	$pad . '<a href="individual1.php?id=' . $records['MostGamesPlayedID'] . '">' . $records['MostGamesPlayedName'] . '</a>' . $pad,
	records_date_or_dash($records['MostGamesPlayedDate'], true, $newRecordCutoff)
);
records_render_row(
	'Most wins',
	records_value_or_dash($records['MostWins']),
	$pad . '<a href="individual1.php?id=' . $records['MostWinsID'] . '">' . $records['MostWinsName'] . '</a>' . $pad,
	records_date_or_dash($records['MostWinsDate'], records_has_value($records['MostWins']), $newRecordCutoff)
);
records_render_row(
	'Most goals',
	records_value_or_dash($records['MostGoalsScored']),
	$pad . '<a href="individual1.php?id=' . $records['MostGoalsScoredID'] . '">' . $records['MostGoalsScoredName'] . '</a>' . $pad,
	records_date_or_dash($records['MostGoalsScoredDate'], records_has_value($records['MostGoalsScored']), $newRecordCutoff)
);
records_render_row(
	'Most double digits',
	records_value_or_dash($records['MostDoubleDigits']),
	$pad . '<a href="individual1.php?id=' . $records['MostDoubleDigitsID'] . '">' . $records['MostDoubleDigitsName'] . '</a>' . $pad,
	records_date_or_dash($records['MostDoubleDigitsDate'], records_has_value($records['MostDoubleDigits']), $newRecordCutoff)
);
records_render_row(
	'Most clean sheets',
	records_value_or_dash($records['MostCleanSheets']),
	$pad . '<a href="individual1.php?id=' . $records['MostCleanSheetsID'] . '">' . $records['MostCleanSheetsName'] . '</a>' . $pad,
	records_date_or_dash($records['MostCleanSheetsDate'], records_has_value($records['MostCleanSheets']), $newRecordCutoff)
);
records_render_spacer_row();

records_render_row(
	'Most goals in one game',
	records_value_or_dash($records['MostGoalsScoredInOneGame']),
	$pad . '<a href="individual1.php?id=' . $records['MostGoalsScoredInOneGameID'] . '">' . $records['MostGoalsScoredInOneGameName'] . '</a>' . $pad,
	records_date_or_dash($records['MostGoalsScoredInOneGameDate'], records_has_value($records['MostGoalsScoredInOneGame']), $newRecordCutoff)
);
records_render_row(
	'Biggest winning margin',
	records_value_or_dash($records['BiggestWinDifference']),
	$pad . '<a href="individual1.php?id=' . $records['BiggestWinDifferenceID'] . '">' . $records['BiggestWinDifferenceName'] . '</a>' . $pad,
	records_date_or_dash($records['BiggestWinDifferenceDate'], records_has_value($records['BiggestWinDifference']), $newRecordCutoff)
);
$biggestDrawScore = records_has_value($records['BiggestDrawSumGameID'])
	? ((string) ($records['BiggestDrawSum'] / 2) . '-' . (string) ($records['BiggestDrawSum'] / 2))
	: '-';
records_render_row(
	'Biggest draw',
	$biggestDrawScore,
	$pad . '<a href="individual1.php?id=' . $records['BiggestDrawSumIDA'] . '">' . $records['BiggestDrawSumNameA'] . '</a> / <a href="individual1.php?id=' . $records['BiggestDrawSumIDB'] . '">' . $records['BiggestDrawSumNameB'] . '</a>' . $pad,
	records_date_or_dash($records['BiggestDrawSumDate'], records_has_value($records['BiggestDrawSumGameID']), $newRecordCutoff)
);
records_render_row(
	'Biggest sum of goals',
	(string) $records['BiggestSumOfGoals'],
	$pad . '<a href="individual1.php?id=' . $records['BiggestSumOfGoalsIDA'] . '">' . $records['BiggestSumOfGoalsNameA'] . '</a> / <a href="individual1.php?id=' . $records['BiggestSumOfGoalsIDB'] . '">' . $records['BiggestSumOfGoalsNameB'] . '</a>' . $pad,
	records_date_or_dash($records['BiggestSumOfGoalsDate'], true, $newRecordCutoff)
);
records_render_spacer_row();

records_render_row(
	'Highest peak rating',
	number_format((float) $records['BiggestPeakRating'], 0, '.', ''),
	$pad . '<a href="individual1.php?id=' . $records['BiggestPeakRatingID'] . '">' . $records['BiggestPeakRatingName'] . '</a>' . $pad,
	records_date_or_dash($records['BiggestPeakRatingDate'], true, $newRecordCutoff)
);
records_render_row(
	'Longest winning streak',
	records_value_or_dash($records['LongestWinningStreak']),
	$pad . '<a href="individual1.php?id=' . $records['LongestWinningStreakID'] . '">' . $records['LongestWinningStreakName'] . '</a>' . $pad,
	records_date_or_dash($records['LongestWinningStreakDate'], records_has_value($records['LongestWinningStreak']), $newRecordCutoff)
);
records_render_row(
	'Longest undefeated streak',
	(string) $records['LongestNonLossStreak'],
	$pad . '<a href="individual1.php?id=' . $records['LongestNonLossStreakID'] . '">' . $records['LongestNonLossStreakName'] . '</a>' . $pad,
	records_date_or_dash($records['LongestNonLossStreakDate'], true, $newRecordCutoff)
);
records_render_row(
	'Longest drawing streak',
	records_value_or_dash($records['LongestDrawingStreak']),
	$pad . '<a href="individual1.php?id=' . $records['LongestDrawingStreakID'] . '">' . $records['LongestDrawingStreakName'] . '</a>' . $pad,
	records_date_or_dash($records['LongestDrawingStreakDate'], records_has_value($records['LongestDrawingStreak']), $newRecordCutoff)
);
records_render_spacer_row();

records_render_row(
	'Most opponents',
	(string) $records['MostDifferentOpponents'],
	$pad . '<a href="individual1.php?id=' . $records['MostDifferentOpponentsID'] . '">' . $records['MostDifferentOpponentsName'] . '</a>' . $pad,
	records_date_or_dash($records['MostDifferentOpponentsDate'], true, $newRecordCutoff)
);
records_render_row(
	'Most victims',
	records_value_or_dash($records['MostDifferentVictims']),
	$pad . '<a href="individual1.php?id=' . $records['MostDifferentVictimsID'] . '">' . $records['MostDifferentVictimsName'] . '</a>' . $pad,
	records_date_or_dash($records['MostDifferentVictimsDate'], records_has_value($records['MostDifferentVictims']), $newRecordCutoff)
);
records_render_row(
	'Most double digit victims',
	records_value_or_dash($records['MostDoubleDigitsVictims']),
	$pad . '<a href="individual1.php?id=' . $records['MostDoubleDigitsVictimsID'] . '">' . $records['MostDoubleDigitsVictimsName'] . '</a>' . $pad,
	records_date_or_dash($records['MostDoubleDigitsVictimsDate'], records_has_value($records['MostDoubleDigitsVictims']), $newRecordCutoff)
);
records_render_row(
	'Most clean sheet victims',
	records_value_or_dash($records['MostCleanSheetsVictims']),
	$pad . '<a href="individual1.php?id=' . $records['MostCleanSheetsVictimsID'] . '">' . $records['MostCleanSheetsVictimsName'] . '</a>' . $pad,
	records_date_or_dash($records['MostCleanSheetsVictimsDate'], records_has_value($records['MostCleanSheetsVictims']), $newRecordCutoff)
);
records_render_spacer_row();

records_render_row(
	'Best attack average',
	records_fixed_or_dash($BiggestGoalsForAverage, 2),
	$pad . '<a href="individual1.php?id=' . $BiggestGoalsForAverageID . '">' . $BiggestGoalsForAverageName . '</a>' . $pad,
	'-'
);
records_render_row(
	'Best defense average',
	records_fixed_or_dash($SmallestGoalsAgainstAverage, 2),
	$pad . '<a href="individual1.php?id=' . $SmallestGoalsAgainstAverageID . '">' . $SmallestGoalsAgainstAverageName . '</a>' . $pad,
	'-'
);
records_render_row(
	'Best goal ratio',
	records_fixed_or_dash($BiggestGoalRatio, 2),
	$pad . '<a href="individual1.php?id=' . $BiggestGoalRatioID . '">' . $BiggestGoalRatioName . '</a>' . $pad,
	'-'
);
records_render_row(
	'Highest winning frequency',
	records_percent_or_dash($BiggestWinRatio),
	$pad . '<a href="individual1.php?id=' . $BiggestWinRatioID . '">' . $BiggestWinRatioName . '</a>' . $pad,
	'-'
);
records_render_row(
	'Highest double digit frequency',
	records_percent_or_dash($BiggestDoubleDigitsRatio),
	$pad . '<a href="individual1.php?id=' . $BiggestDoubleDigitsRatioID . '">' . $BiggestDoubleDigitsRatioName . '</a>' . $pad,
	'-'
);
records_render_row(
	'Highest clean sheet frequency',
	records_percent_or_dash($BiggestCleanSheetsRatio),
	$pad . '<a href="individual1.php?id=' . $BiggestCleanSheetsRatioID . '">' . $BiggestCleanSheetsRatioName . '</a>' . $pad,
	'-'
);
?>
</tbody>

</table>

</div><!-- .k2-table-wrap -->

A player must play 30 games for ratios and averages to take effect.
<br />
Records that are less than one month old are displayed as "<span class="blue">(New!)</span>".





</div><!-- .k2-page-nav -->
</body>
</html>




