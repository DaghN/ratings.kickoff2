<?php
/**
 * Busiest day / month / year hall of fame — three tables (ranked8.php Hall of Fame).
 *
 * Optional before include:
 *   $k2PeakPeriodLimit (default 0 = all players)
 *   $k2PeakDayEntries, $k2PeakMonthEntries, $k2PeakYearEntries
 *   $k2PeakDayQueryError, $k2PeakMonthQueryError, $k2PeakYearQueryError
 */
$k2PeakPeriodLimit = isset($k2PeakPeriodLimit) ? (int) $k2PeakPeriodLimit : 0;

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
<div class="server-peak-period-leaderboards__grid">
<?php foreach (['day', 'month', 'year'] as $period) {
    $meta = k2_peak_period_leaderboard_meta($period);
    $entries = $k2PeakPanels[$period]['entries'];
    $queryError = $k2PeakPanels[$period]['error'];
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
						<th data-k2-sort="number">#</th>
						<th data-k2-sort="text" style="text-align:left;">Player</th>
						<th data-k2-sort="text" style="text-align:left;"><?php echo htmlspecialchars($meta['period_label'], ENT_QUOTES, 'UTF-8'); ?></th>
						<th data-k2-sort="number">Games</th>
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
<?php } ?>
</div>
