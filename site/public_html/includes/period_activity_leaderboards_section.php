<?php
/**
 * Games played in a selected day / week / month / year.
 *
 * Optional before include:
 *   $k2PeriodActivityLimit (default 50)
 *   $k2PeriodActivityKeys — ['day' => 'Y-m-d', 'week' => Monday 'Y-m-d', 'month' => 'Y-m', 'year' => 'YYYY']
 *   $k2PeriodActivityPanels — preloaded ['day'|'week'|'month'|'year' => ['entries', 'total_games', 'error']]
 *   $k2PeriodActivityWeekChoices, $k2PeriodActivityMonthChoices, $k2PeriodActivityYearChoices
 *   $k2PeriodActivityDayMin, $k2PeriodActivityDayMax
 */
$k2PeriodActivityLimit = isset($k2PeriodActivityLimit) ? (int) $k2PeriodActivityLimit : 50;

if (!function_exists('k2_period_activity_leaderboard_entries')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
}

$k2PeriodActivityOwnConnection = false;
if (!isset($con) || !($con instanceof mysqli) || !@$con->ping()) {
    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    if (mysqli_connect_errno()) {
        echo '<div class="server-period-activity-leaderboards">';
        echo '<p class="server-period-activity-leaderboards-status">Could not load games-by-period tables.</p>';
        echo '</div>';
        return;
    }
    $con->query("SET time_zone = '+00:00'");
    $k2PeriodActivityOwnConnection = true;
}

$today = date('Y-m-d');
$todayDate = new DateTimeImmutable($today);
$thisWeek = $todayDate->modify('-' . ((int) $todayDate->format('N') - 1) . ' days')->format('Y-m-d');
$thisMonth = date('Y-m');
$thisYear = date('Y');

$k2PeriodActivityKeys = isset($k2PeriodActivityKeys) && is_array($k2PeriodActivityKeys)
    ? $k2PeriodActivityKeys
    : [];
$k2PeriodActivityKeys['day'] = $k2PeriodActivityKeys['day'] ?? $today;
$k2PeriodActivityKeys['week'] = $k2PeriodActivityKeys['week'] ?? $thisWeek;
$k2PeriodActivityKeys['month'] = $k2PeriodActivityKeys['month'] ?? $thisMonth;
$k2PeriodActivityKeys['year'] = $k2PeriodActivityKeys['year'] ?? $thisYear;

foreach (['day', 'week', 'month', 'year'] as $p) {
    $normalized = k2_period_activity_normalize_key($p, (string) $k2PeriodActivityKeys[$p]);
    $k2PeriodActivityKeys[$p] = $normalized ?? ($p === 'day' ? $today : ($p === 'week' ? $thisWeek : ($p === 'month' ? $thisMonth : $thisYear)));
}

$k2PeriodActivityPanels = isset($k2PeriodActivityPanels) && is_array($k2PeriodActivityPanels)
    ? $k2PeriodActivityPanels
    : [];

foreach (['day', 'week', 'month', 'year'] as $period) {
    if (!isset($k2PeriodActivityPanels[$period]) || !is_array($k2PeriodActivityPanels[$period])) {
        $err = null;
        $k2PeriodActivityPanels[$period] = [
            'entries' => k2_period_activity_leaderboard_entries(
                $con,
                $period,
                $k2PeriodActivityKeys[$period],
                $k2PeriodActivityLimit,
                $err
            ),
            'total_games' => k2_period_activity_total_games($con, $period, $k2PeriodActivityKeys[$period]),
            'error' => $err,
        ];
    }
}

$choicesErr = null;
if (!isset($k2PeriodActivityMonthChoices)) {
    $k2PeriodActivityMonthChoices = k2_period_activity_available_keys($con, 'month', $choicesErr);
}
if (!isset($k2PeriodActivityWeekChoices)) {
    $k2PeriodActivityWeekChoices = k2_period_activity_available_keys($con, 'week', $choicesErr);
}
if (!isset($k2PeriodActivityYearChoices)) {
    $k2PeriodActivityYearChoices = k2_period_activity_available_keys($con, 'year', $choicesErr);
}
if (!in_array($k2PeriodActivityKeys['week'], $k2PeriodActivityWeekChoices, true)) {
    array_unshift($k2PeriodActivityWeekChoices, $k2PeriodActivityKeys['week']);
}
if (!in_array($k2PeriodActivityKeys['month'], $k2PeriodActivityMonthChoices, true)) {
    array_unshift($k2PeriodActivityMonthChoices, $k2PeriodActivityKeys['month']);
}
if (!in_array($k2PeriodActivityKeys['year'], $k2PeriodActivityYearChoices, true)) {
    array_unshift($k2PeriodActivityYearChoices, $k2PeriodActivityKeys['year']);
}
if (!isset($k2PeriodActivityDayMin) || !isset($k2PeriodActivityDayMax)) {
    $bounds = k2_period_activity_day_bounds($con, $choicesErr);
    $k2PeriodActivityDayMin = $bounds['min'] ?? $today;
    $k2PeriodActivityDayMax = $bounds['max'] ?? $today;
}

if ($k2PeriodActivityOwnConnection) {
    mysqli_close($con);
    unset($con);
}

/**
 * @param array<int, array{rank: int, player_id: int, player_name: string, games: int}> $entries
 */
if (!function_exists('k2_render_period_activity_tbody')) {
function k2_render_period_activity_tbody(string $period, array $entries): void
{
    if (!$entries) {
        echo '<tbody class="black"><tr><td colspan="3" class="k2-table-cell--left">No rated games in this period.</td></tr></tbody>';
        return;
    }
    echo '<tbody class="black">';
    foreach ($entries as $entry) {
        echo '<tr>';
        echo '<td>' . (int) $entry['rank'] . '</td>';
        echo '<td class="k2-table-cell--left"><a href="individual1.php?id='
            . (int) $entry['player_id'] . '">'
            . htmlspecialchars($entry['player_name'], ENT_QUOTES, 'UTF-8') . '</a></td>';
        echo '<td>' . (int) $entry['games'] . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
}
}
?>
<div class="server-period-activity-leaderboards" data-limit="<?php echo (int) $k2PeriodActivityLimit; ?>">
	<h2 class="k2-panel-heading server-period-activity-leaderboards__heading">Games played — day, week, month &amp; year</h2>
	<p class="server-period-activity-leaderboards__intro">Who played how many rated games in the selected calendar day, week, month, and year. Defaults to today, this week, this month, and this year.</p>
	<div class="server-period-activity-leaderboards__grid">
<?php foreach (['day', 'week', 'month', 'year'] as $period) {
    $meta = k2_period_activity_leaderboard_meta($period);
    $panel = $k2PeriodActivityPanels[$period];
    $entries = $panel['entries'] ?? [];
    $queryError = $panel['error'] ?? null;
    $totalGames = (int) ($panel['total_games'] ?? 0);
    $selectedKey = $k2PeriodActivityKeys[$period];
    $periodLabel = k2_format_period_activity_label($period, $selectedKey);
    $pickerId = 'period-activity-' . $period . '-picker';
    ?>
		<div class="server-period-activity-leaderboard server-period-activity-leaderboard--<?php echo htmlspecialchars($period, ENT_QUOTES, 'UTF-8'); ?>" data-period="<?php echo htmlspecialchars($period, ENT_QUOTES, 'UTF-8'); ?>">
			<div class="server-period-activity-leaderboard__head">
				<h3 class="k2-panel-heading server-period-activity-leaderboard__title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
				<div class="server-period-activity-leaderboard__picker">
					<label class="server-period-activity-leaderboard__picker-label" for="<?php echo htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($meta['picker_label'], ENT_QUOTES, 'UTF-8'); ?></label>
<?php if ($period === 'day') { ?>
					<span class="server-period-activity-leaderboard__date-control">
						<input type="date" id="<?php echo htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8'); ?>" class="server-period-activity-leaderboard__input server-period-activity-leaderboard__input--day" value="<?php echo htmlspecialchars($selectedKey, ENT_QUOTES, 'UTF-8'); ?>" min="<?php echo htmlspecialchars((string) $k2PeriodActivityDayMin, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars((string) $k2PeriodActivityDayMax, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Select calendar day" />
						<button type="button" class="server-period-activity-leaderboard__calendar-button" aria-label="Open calendar picker">
							<span class="server-period-activity-leaderboard__calendar-icon" aria-hidden="true"></span>
						</button>
					</span>
<?php } elseif ($period === 'week') { ?>
					<select id="<?php echo htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8'); ?>" class="server-period-activity-leaderboard__input server-period-activity-leaderboard__input--week" aria-label="Select calendar week">
<?php foreach ($k2PeriodActivityWeekChoices as $weekStart) {
    $sel = $weekStart === $selectedKey ? ' selected="selected"' : '';
    ?>
						<option value="<?php echo htmlspecialchars($weekStart, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars(k2_format_period_activity_label('week', $weekStart), ENT_QUOTES, 'UTF-8'); ?></option>
<?php } ?>
					</select>
<?php } elseif ($period === 'month') { ?>
					<select id="<?php echo htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8'); ?>" class="server-period-activity-leaderboard__input server-period-activity-leaderboard__input--month" aria-label="Select calendar month">
<?php foreach ($k2PeriodActivityMonthChoices as $ym) {
    $sel = $ym === $selectedKey ? ' selected="selected"' : '';
    ?>
						<option value="<?php echo htmlspecialchars($ym, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars(k2_format_period_activity_label('month', $ym), ENT_QUOTES, 'UTF-8'); ?></option>
<?php } ?>
					</select>
<?php } else { ?>
					<select id="<?php echo htmlspecialchars($pickerId, ENT_QUOTES, 'UTF-8'); ?>" class="server-period-activity-leaderboard__input server-period-activity-leaderboard__input--year" aria-label="Select calendar year">
<?php foreach ($k2PeriodActivityYearChoices as $y) {
    $sel = $y === $selectedKey ? ' selected="selected"' : '';
    ?>
						<option value="<?php echo htmlspecialchars($y, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $sel; ?>><?php echo htmlspecialchars($y, ENT_QUOTES, 'UTF-8'); ?></option>
<?php } ?>
					</select>
<?php } ?>
				</div>
			</div>
			<p class="server-period-activity-leaderboard__summary" data-summary>
				<strong><?php echo (int) $totalGames; ?></strong> rated game<?php echo $totalGames === 1 ? '' : 's'; ?>
				<span class="server-period-activity-leaderboard__summary-period">· <?php echo htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?></span>
			</p>
<?php if (!empty($queryError)) { ?>
			<p class="server-period-activity-leaderboard-status">Could not load this table.</p>
<?php } else { ?>
			<div class="k2-table-wrap">
				<table class="k2-table k2-table--numeric-default server-period-activity-leaderboard__table">
					<thead>
						<tr>
							<th style="width:3.5em;">Rank</th>
							<th class="k2-table-cell--left">Player</th>
							<th style="width:4.5em;">Games</th>
						</tr>
					</thead>
<?php k2_render_period_activity_tbody($period, $entries); ?>
				</table>
			</div>
<?php } ?>
		</div>
<?php } ?>
	</div>
	<p class="server-period-activity-leaderboards-status server-period-activity-leaderboards-status--global" hidden="hidden" aria-live="polite"></p>
</div>
