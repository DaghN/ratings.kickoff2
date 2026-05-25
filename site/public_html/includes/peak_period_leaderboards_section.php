<?php
/**
 * Busiest day / month / year / all-time hall of fame (ranked8.php Hall of Fame).
 *
 * Optional before include:
 *   $k2PeakPeriodLimit (default 0 = all players)
 *   $k2PeakDayEntries, $k2PeakMonthEntries, $k2PeakYearEntries, $k2PeakAllTimeEntries
 *   $k2PeakLongevityEntries
 *   $k2PeakDayQueryError, $k2PeakMonthQueryError, $k2PeakYearQueryError, $k2PeakAllTimeQueryError
 *   $k2PeakLongevityQueryError
 */
$k2PeakPeriodLimit = isset($k2PeakPeriodLimit) ? (int) $k2PeakPeriodLimit : 0;
$k2PeakPeriodPanels = ['day', 'month', 'year'];
$k2PeakAllPanels = ['all-time', 'longevity'];

if (!function_exists('k2_peak_period_leaderboard_entries')) {
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/peak_month_leaderboard_query.php';
}

$k2PeakPeriodOwnConnection = false;
if (!isset($con) || !($con instanceof mysqli) || !@$con->ping()) {
    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    if (mysqli_connect_errno()) {
        echo '<p class="server-peak-period-leaderboards-status">Could not load busiest period leaderboards.</p>';
        return;
    }
    $k2PeakPeriodOwnConnection = true;
}

$k2PeakPanels = [
    'day' => [
        'entries' => $k2PeakDayEntries ?? null,
        'error' => $k2PeakDayQueryError ?? null,
    ],
    'month' => [
        'entries' => $k2PeakMonthEntries ?? null,
        'error' => $k2PeakMonthQueryError ?? null,
    ],
    'year' => [
        'entries' => $k2PeakYearEntries ?? null,
        'error' => $k2PeakYearQueryError ?? null,
    ],
    'all-time' => [
        'entries' => $k2PeakAllTimeEntries ?? null,
        'error' => $k2PeakAllTimeQueryError ?? null,
    ],
    'longevity' => [
        'entries' => $k2PeakLongevityEntries ?? null,
        'error' => $k2PeakLongevityQueryError ?? null,
    ],
];

foreach ($k2PeakPanels as $period => &$panel) {
    if ($panel['entries'] === null) {
        $err = null;
        if ($period === 'longevity') {
            $panel['entries'] = k2_peak_longevity_leaderboard_entries($con, $k2PeakPeriodLimit, $err);
        } else {
            $panel['entries'] = k2_peak_period_leaderboard_entries($con, $period, $k2PeakPeriodLimit, $err);
        }
        $panel['error'] = $err;
    }
}
unset($panel);

if ($k2PeakPeriodOwnConnection) {
    mysqli_close($con);
    unset($con);
}

if (!function_exists('k2_render_peak_period_panel')) {
function k2_render_peak_period_panel(string $period, array $panel): void
{
    $meta = k2_peak_period_leaderboard_meta($period);
    $entries = $panel['entries'];
    $queryError = $panel['error'];
    $periodHelp = $period === 'all-time'
        ? "Date of the player's first rated game."
        : "The player's busiest " . $period . ' by rated games.';
    ?>
	<section class="server-peak-period-leaderboard-block server-peak-period-leaderboard-block--<?php echo htmlspecialchars($period, ENT_QUOTES, 'UTF-8'); ?>">
		<h3 class="k2-panel-heading server-peak-period-leaderboard-block__title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
<?php if (!empty($queryError)) { ?>
		<p class="server-peak-period-leaderboard-status">Could not load this leaderboard.</p>
<?php } elseif (!$entries) { ?>
		<p class="server-peak-period-leaderboard-status">No rated games to rank yet.</p>
<?php } else { ?>
		<div class="k2-table-wrap">
			<table class="k2-table" data-k2-table="sortable" data-k2-default-sort="3" data-k2-default-direction="desc">
				<thead>
					<tr style="text-align:right;">
						<th data-k2-sort="number" data-k2-help="Rank within this activity table.">#</th>
						<th data-k2-sort="text" style="text-align:left;" data-k2-help="Player name.">Player</th>
						<th data-k2-sort="text" style="text-align:left;" data-k2-help="<?php echo htmlspecialchars($periodHelp, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($meta['period_label'], ENT_QUOTES, 'UTF-8'); ?></th>
						<th data-k2-sort="number" data-k2-help="<?php echo $period === 'all-time' ? 'Total rated games played.' : 'Rated games played in that peak period.'; ?>">Games</th>
					</tr>
				</thead>
				<tbody class="black">
<?php foreach ($entries as $entry) { ?>
					<tr style="text-align:right;">
						<td><?php echo (int) $entry['rank']; ?></td>
						<td style="text-align:left;"><a href="individual1.php?id=<?php echo (int) $entry['player_id']; ?>"><?php echo htmlspecialchars($entry['player_name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
						<td style="text-align:left;"><?php echo htmlspecialchars(k2_format_peak_period($period, $entry['period_key']), ENT_QUOTES, 'UTF-8'); ?></td>
						<td><?php if ((int) $entry['games'] === 0) { echo '0'; } else { echo "<span class='blue'>", (int) $entry['games'], '</span>'; } ?></td>
					</tr>
<?php } ?>
				</tbody>
			</table>
		</div>
<?php } ?>
	</section>
<?php
}
}

if (!function_exists('k2_render_peak_longevity_panel')) {
function k2_render_peak_longevity_panel(array $panel): void
{
    $entries = $panel['entries'];
    $queryError = $panel['error'];
    ?>
	<section class="server-peak-period-leaderboard-block server-peak-period-leaderboard-block--longevity">
		<h3 class="k2-panel-heading server-peak-period-leaderboard-block__title">Longevity</h3>
<?php if (!empty($queryError)) { ?>
		<p class="server-peak-period-leaderboard-status">Could not load this leaderboard.</p>
<?php } elseif (!$entries) { ?>
		<p class="server-peak-period-leaderboard-status">No rated games to rank yet.</p>
<?php } else { ?>
		<div class="k2-table-wrap">
			<table class="k2-table" data-k2-table="sortable" data-k2-default-sort="4" data-k2-default-direction="desc">
				<thead>
					<tr style="text-align:right;">
						<th data-k2-sort="number" data-k2-help="Rank within this activity table.">#</th>
						<th data-k2-sort="text" style="text-align:left;" data-k2-help="Player name.">Player</th>
						<th data-k2-sort="text" style="text-align:left;" data-k2-help="Date of the player's first rated game.">First game</th>
						<th data-k2-sort="text" style="text-align:left;" data-k2-help="Date of the player's latest rated game.">Last game</th>
						<th data-k2-sort="number" data-k2-help="Days between the player's first and latest rated games.">Days</th>
					</tr>
				</thead>
				<tbody class="black">
<?php foreach ($entries as $entry) { ?>
					<tr style="text-align:right;">
						<td><?php echo (int) $entry['rank']; ?></td>
						<td style="text-align:left;"><a href="individual1.php?id=<?php echo (int) $entry['player_id']; ?>"><?php echo htmlspecialchars($entry['player_name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
						<td style="text-align:left;"><?php echo htmlspecialchars(k2_format_peak_period('all-time', $entry['first_game']), ENT_QUOTES, 'UTF-8'); ?></td>
						<td style="text-align:left;"><?php echo htmlspecialchars(k2_format_peak_period('all-time', $entry['last_game']), ENT_QUOTES, 'UTF-8'); ?></td>
						<td><span class="blue"><?php echo (int) $entry['days']; ?></span></td>
					</tr>
<?php } ?>
				</tbody>
			</table>
		</div>
<?php } ?>
	</section>
<?php
}
}
?>
<div class="server-peak-period-leaderboards" data-k2-activity-mode="period">
	<nav class="server-peak-period-mode" aria-label="Activity view">
		<button type="button" class="server-peak-period-mode__btn is-active" data-k2-activity-target="period" aria-pressed="true">Period</button>
		<button type="button" class="server-peak-period-mode__btn" data-k2-activity-target="all-time" aria-pressed="false">All time</button>
	</nav>
	<div class="server-peak-period-leaderboards__panel" data-k2-activity-panel="period">
		<div class="server-peak-period-leaderboards__grid">
<?php foreach ($k2PeakPeriodPanels as $period) { k2_render_peak_period_panel($period, $k2PeakPanels[$period]); } ?>
		</div>
	</div>
	<div class="server-peak-period-leaderboards__panel" data-k2-activity-panel="all-time" hidden="hidden">
		<div class="server-peak-period-leaderboards__grid server-peak-period-leaderboards__grid--all-time">
<?php foreach ($k2PeakAllPanels as $period) { ?>
<?php if ($period === 'longevity') { k2_render_peak_longevity_panel($k2PeakPanels[$period]); } else { k2_render_peak_period_panel($period, $k2PeakPanels[$period]); } ?>
<?php } ?>
		</div>
	</div>
</div>
