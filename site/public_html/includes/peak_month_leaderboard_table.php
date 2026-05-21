<?php
/**
 * Busiest month hall of fame — server trends table (server1.php).
 * Set $k2PeakMonthEntries before include, or leave unset to query here.
 */
$k2PeakMonthLimit = isset($k2PeakMonthLimit) ? (int) $k2PeakMonthLimit : 50;

if (!function_exists('k2_peak_month_leaderboard_entries')) {
    include $_SERVER['DOCUMENT_ROOT'] . '/includes/peak_month_leaderboard_query.php';
}

if (!isset($k2PeakMonthEntries)) {
    $k2PeakMonthQueryError = null;
    $k2PeakMonthOwnConnection = false;
    if (!isset($con) || !($con instanceof mysqli) || !@$con->ping()) {
        include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
        $con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
        if (mysqli_connect_errno()) {
            echo '<div class="server-peak-month-leaderboard">';
            echo '<p class="server-peak-month-leaderboard-status">Could not load peak month leaderboard.</p>';
            echo '</div>';
            return;
        }
        $k2PeakMonthOwnConnection = true;
    }

    $k2PeakMonthEntries = k2_peak_month_leaderboard_entries($con, $k2PeakMonthLimit, $k2PeakMonthQueryError);

    if ($k2PeakMonthOwnConnection) {
        mysqli_close($con);
        unset($con);
    }
}
?>
<div class="server-peak-month-leaderboard">
    <p style="margin: 0 0 4px 0; color: var(--k2-text-primary, #e6edf3);">Busiest month hall of fame</p>
    <p style="margin: 0 0 4px 0; color: var(--k2-text-muted, #8b949e); font-size: 0.9em;">Top <?php echo (int) $k2PeakMonthLimit; ?> players by most rated games in a single calendar month (each player’s personal best only). Ties: earlier month wins.</p>
<?php if (!empty($k2PeakMonthQueryError)) { ?>
    <p class="server-peak-month-leaderboard-status" style="margin: 0 0 8px 0;">Could not load peak month leaderboard.</p>
<?php } elseif (!$k2PeakMonthEntries) { ?>
    <p class="server-peak-month-leaderboard-status" style="margin: 0 0 8px 0;">No rated games to rank yet.</p>
<?php } else { ?>
    <div class="k2-table-wrap">
        <table class="k2-table table-autosort table-stripeclass:alternate table-autostripe table-rowshade-alternate">
            <thead>
                <tr style="text-align:right;">
                    <th class="table-sortable:numeric">Rank</th>
                    <th class="table-sortable:ignorecase" style="text-align:left;">Player</th>
                    <th class="table-sortable:ignorecase" style="text-align:left;">Peak month</th>
                    <th class="table-sortable:numeric">Games</th>
                </tr>
            </thead>
            <tbody class="black">
<?php foreach ($k2PeakMonthEntries as $entry) { ?>
                <tr style="text-align:right;">
                    <td><?php echo (int) $entry['rank']; ?></td>
                    <td style="text-align:left;"><a href="individual1.php?id=<?php echo (int) $entry['player_id']; ?>"><?php echo htmlspecialchars($entry['player_name'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                    <td style="text-align:left;"><?php echo htmlspecialchars(k2_format_peak_month($entry['month']), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $entry['games']; ?></td>
                </tr>
<?php } ?>
            </tbody>
        </table>
    </div><!-- .k2-table-wrap -->
<?php } ?>
</div>
