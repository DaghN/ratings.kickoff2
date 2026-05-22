<?php
/**
 * Busiest day / month / year hall of fame — three tables (server1.php).
 *
 * Optional before include:
 *   $k2PeakPeriodLimit (default 50)
 *   $k2PeakDayEntries, $k2PeakMonthEntries, $k2PeakYearEntries
 *   $k2PeakDayQueryError, $k2PeakMonthQueryError, $k2PeakYearQueryError
 */
$k2PeakPeriodLimit = isset($k2PeakPeriodLimit) ? (int) $k2PeakPeriodLimit : 50;

if (!function_exists('k2_peak_period_leaderboard_entries')) {
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/peak_month_leaderboard_query.php';
}

$k2PeakPeriodOwnConnection = false;
if (!isset($con) || !($con instanceof mysqli) || !@$con->ping()) {
    include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
    if (mysqli_connect_errno()) {
        echo '<div class="server-peak-period-leaderboards">';
        echo '<p class="server-peak-period-leaderboards-status">Could not load busiest period leaderboards.</p>';
        echo '</div>';
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
];

foreach ($k2PeakPanels as $period => &$panel) {
    if ($panel['entries'] === null) {
        $err = null;
        $panel['entries'] = k2_peak_period_leaderboard_entries($con, $period, $k2PeakPeriodLimit, $err);
        $panel['error'] = $err;
    }
}
unset($panel);

if ($k2PeakPeriodOwnConnection) {
    mysqli_close($con);
    unset($con);
}
?>
<div class="server-peak-period-leaderboards">
	<p style="margin: 0 0 4px 0; color: var(--k2-text-primary, #e6edf3);">Busiest day, month &amp; year — hall of fame</p>
	<p style="margin: 0 0 12px 0; color: var(--k2-text-muted, #8b949e); font-size: 0.9em;">Top <?php echo (int) $k2PeakPeriodLimit; ?> per list — each player’s personal best calendar day, month, and year (rated games). Ties: earlier period wins.</p>
	<div class="server-peak-period-leaderboards__grid">
<?php foreach (['day', 'month', 'year'] as $period) {
    $meta = k2_peak_period_leaderboard_meta($period);
    $entries = $k2PeakPanels[$period]['entries'];
    $queryError = $k2PeakPanels[$period]['error'];
    ?>
		<div class="server-peak-period-leaderboard server-peak-period-leaderboard--<?php echo htmlspecialchars($period, ENT_QUOTES, 'UTF-8'); ?>">
			<p class="server-peak-period-leaderboard__title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></p>
			<p class="server-peak-period-leaderboard__hint"><?php echo htmlspecialchars($meta['hint'], ENT_QUOTES, 'UTF-8'); ?></p>
<?php if (!empty($queryError)) { ?>
			<p class="server-peak-period-leaderboard-status">Could not load this leaderboard.</p>
<?php } elseif (!$entries) { ?>
			<p class="server-peak-period-leaderboard-status">No rated games to rank yet.</p>
<?php } else { ?>
			<div class="k2-table-wrap">
				<table class="k2-table table-autosort table-stripeclass:alternate table-autostripe table-rowshade-alternate">
					<thead>
						<tr style="text-align:right;">
							<th class="table-sortable:numeric">Rank</th>
							<th class="table-sortable:ignorecase" style="text-align:left;">Player</th>
							<th class="table-sortable:ignorecase" style="text-align:left;"><?php echo htmlspecialchars($meta['period_label'], ENT_QUOTES, 'UTF-8'); ?></th>
							<th class="table-sortable:numeric">Games</th>
						</tr>
					</thead>
					<tbody class="black">
<?php foreach ($entries as $entry) { ?>
						<tr style="text-align:right;">
							<td><?php echo (int) $entry['rank']; ?></td>
							<td style="text-align:left;"><a href="individual1.php?id=<?php echo (int) $entry['player_id']; ?>"><?php echo htmlspecialchars($entry['player_name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
							<td style="text-align:left;"><?php echo htmlspecialchars(k2_format_peak_period($period, $entry['period_key']), ENT_QUOTES, 'UTF-8'); ?></td>
							<td><?php echo (int) $entry['games']; ?></td>
						</tr>
<?php } ?>
					</tbody>
				</table>
			</div>
<?php } ?>
		</div>
<?php } ?>
	</div>
</div>
