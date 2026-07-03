<?php
/**
 * Status room markup (hub status.php).
 *
 * Before include:
 *   $k2StatusRoom — array from k2_status_load_room()
 *   $k2StatusRoomError — set if load failed
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_table_render.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_routes.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';

$room = $k2StatusRoom ?? null;
$loadError = $k2StatusRoomError ?? null;

if ($loadError !== null || $room === null) {
    echo '<section class="k2-status-room"><p class="k2-status-room__error">Could not load status room.</p></section>';

    return;
}

$arc = $room['arc'];
$activeTop = $room['active_top'];
$leagues = is_array($room['leagues'] ?? null) ? $room['leagues'] : [];
$serverClock = is_array($room['server_clock'] ?? null) ? $room['server_clock'] : [];
$serverNowEpoch = (int) ($serverClock['now_epoch'] ?? time());
$online = $room['online'];
$liveGames = $room['live_games'];
$logins = $room['logins'];
$registrations = $room['registrations'];
$recentGames = $room['recent_games'];

$activePlayerCount = is_array($activeTop) ? count($activeTop) : 0;
?>
<section class="k2-status-room" aria-label="Online status room">

	<div class="k2-status-room__layout">
		<section class="k2-status-panel k2-status-panel--tight k2-status-room__panel-online" aria-labelledby="k2-status-online-title">
			<h2 id="k2-status-online-title" class="k2-panel-heading">Online</h2>
<?php if ($online === []) { ?>
			<p class="k2-status-panel__empty">Nobody flagged online — see recent logins below.</p>
<?php } else { ?>
			<ul class="k2-status-name-list">
<?php foreach ($online as $row) { ?>
				<li><?php echo k2_status_player_link($row['id'], $row['name']); ?></li>
<?php } ?>
			</ul>
<?php } ?>
		</section>

		<section class="k2-status-panel k2-status-panel--tight k2-status-room__panel-live" aria-labelledby="k2-status-live-title">
			<h2 id="k2-status-live-title" class="k2-panel-heading">Live games</h2>
<?php if ($liveGames === []) { ?>
			<p class="k2-status-panel__empty">No live games in progress.</p>
<?php } else { ?>
			<ul class="k2-status-live-list">
<?php foreach ($liveGames as $g) { ?>
				<li>
					<span class="k2-status-live-list__time"><?php echo k2_status_h(k2_status_short_time($g['start'])); ?></span>
					<span class="k2-status-match">
						<span class="k2-status-match__side"><?php echo k2_status_player_link_or_name($g['id_a'], $g['name_a']); ?></span>
						<span class="k2-status-score"><?php echo k2_status_score_html((int) $g['score_a'], (int) $g['score_b']); ?></span>
						<span class="k2-status-match__side"><?php echo k2_status_player_link_or_name($g['id_b'], $g['name_b']); ?></span>
					</span>
					<span class="k2-status-live-list__meta">
						<span class="k2-status-live-list__clock"><?php echo k2_status_h(k2_status_format_half_countdown((int) $g['half_countdown'])); ?></span>
						<span class="k2-status-live-list__period"><?php echo k2_status_h(k2_status_format_game_period((int) $g['period'])); ?></span>
					</span>
				</li>
<?php } ?>
			</ul>
<?php } ?>
		</section>

		<section class="k2-status-panel k2-status-panel--tight k2-status-room__panel-arc" aria-label="Rated games summary">
			<p class="k2-status-room__arc">
				<span class="blue"><?php echo number_format((int) ($arc['players'] ?? 0)); ?></span> players played
				<span class="blue"><?php echo number_format((int) $arc['games']); ?></span> online Kick Off 2 games since <?php echo k2_status_h($arc['since_label']); ?>
			</p>
			<a class="k2-link-star k2-status-room__arc-link" href="<?php echo k2_status_h(k2_status_on_this_day_last_year_href($serverClock)); ?>">On this day last year &rarr;</a>
		</section>

		<section class="k2-status-panel k2-status-panel--tight k2-status-room__panel-heritage" aria-label="Original Amiga box art">
			<a class="k2-status-heritage-inset k2-status-heritage-inset--link" href="/boxart.php">
				<img class="k2-heritage-box__art" src="images/KO2BoxFront.jpg" width="88" alt="The original 1990 Kick Off 2 Amiga box art — read its story" loading="lazy" decoding="async" />
				<p class="k2-heritage-box__caption">KO2 · 1990</p>
			</a>
		</section>

		<section class="k2-status-panel k2-status-panel--tight k2-status-panel--compact k2-status-room__panel-leaderboard" aria-labelledby="k2-status-active-title">
			<div class="k2-status-panel__head">
				<h2 id="k2-status-active-title" class="k2-panel-heading">Leaderboard <span class="k2-panel-heading__sep" aria-hidden="true">·</span> <span class="blue"><?php echo number_format($activePlayerCount); ?></span> active online players in the past year</h2>
				<p class="k2-status-panel__meta"><a class="k2-link-star k2-status-panel__more" href="/leaderboards/rating.php">Leaderboards &rarr;</a></p>
			</div>
<?php if (!empty($room['active_top_error'])) { ?>
			<p class="k2-status-panel__empty">Could not load active ratings.</p>
<?php } elseif ($activeTop === []) { ?>
			<p class="k2-status-panel__empty">No active rated players in this window yet.</p>
<?php } else { ?>
			<div class="k2-table-wrap k2-table-wrap--compact">
				<table class="k2-table k2-status-table k2-status-table--dense k2-table--calm-stats" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="2" data-k2-default-direction="desc">
					<thead>
						<tr>
							<th class="k2-status-table__num" data-k2-sort="number" data-k2-help="Rank within this visible leaderboard. Rank updates when the table is sorted.">#</th>
							<th class="k2-status-table__player" data-k2-sort="text">Player</th>
							<th class="k2-status-table__num k2-table-cell--center" data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(k2_lb_help_elo_rating_status()); ?>>Elo</th>
							<th class="k2-status-table__num" data-k2-sort="number" data-k2-help="Games played (career).">Games</th>
						</tr>
					</thead>
					<tbody class="black">
<?php
    $rank = 1;
    foreach ($activeTop as $row) {
        ?>
						<tr>
							<td class="k2-status-table__num"><?php echo $rank; ?></td>
							<td class="k2-status-table__player"><?php echo k2_status_player_link($row['id'], $row['name']); ?></td>
							<td class="k2-status-table__num"><?php echo k2_lb_rating_cell_link((int) $row['id'], $row['rating'], (string) $row['name']); ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['games']; ?></td>
						</tr>
<?php
        ++$rank;
    }
    ?>
					</tbody>
				</table>
			</div>
<?php } ?>
		</section>

		<div class="k2-status-room__west">
		<section class="k2-status-panel k2-status-panel--tight k2-status-panel--mini k2-status-room__panel-logins" aria-labelledby="k2-status-logins-title">
			<h2 id="k2-status-logins-title" class="k2-panel-heading">Recent logins</h2>
<?php if ($logins === []) { ?>
			<p class="k2-status-panel__empty">—</p>
<?php } else { ?>
			<ul class="k2-status-recency-list">
<?php foreach ($logins as $row) { ?>
				<li>
					<span class="k2-status-recency-list__when"><?php echo k2_status_h(k2_status_short_time($row['at'])); ?></span>
					<?php echo k2_status_player_link($row['id'], $row['name']); ?>
				</li>
<?php } ?>
			</ul>
<?php } ?>
		</section>

		<section class="k2-status-panel k2-status-panel--tight k2-status-panel--mini k2-status-room__panel-games" aria-labelledby="k2-status-games-title">
			<div class="k2-status-panel__heading-row">
				<h2 id="k2-status-games-title" class="k2-panel-heading">Recent games</h2>
				<a class="k2-link-star k2-status-panel__more" href="<?php echo htmlspecialchars(k2_route('games-recent'), ENT_QUOTES, 'UTF-8'); ?>">Games &rarr;</a>
			</div>
<?php if ($recentGames === []) { ?>
			<p class="k2-status-panel__empty">—</p>
<?php } else { ?>
			<ul class="k2-status-recency-list">
<?php foreach ($recentGames as $g) { ?>
				<li>
					<span class="k2-status-recency-list__when"><?php echo k2_status_h(k2_status_short_time($g['at'])); ?></span>
					<span class="k2-status-match">
						<span class="k2-status-match__side"><?php echo k2_status_player_link($g['id_a'], $g['name_a']); ?></span>
						<span class="k2-status-score"><?php echo k2_status_score_html((int) $g['goals_a'], (int) $g['goals_b']); ?></span>
						<span class="k2-status-match__side"><?php echo k2_status_player_link($g['id_b'], $g['name_b']); ?></span>
					</span>
				</li>
<?php } ?>
			</ul>
<?php } ?>
		</section>

		<section class="k2-status-panel k2-status-panel--tight k2-status-panel--mini k2-status-room__panel-new" aria-labelledby="k2-status-reg-title">
			<h2 id="k2-status-reg-title" class="k2-panel-heading">New players</h2>
<?php if ($registrations === []) { ?>
			<p class="k2-status-panel__empty">—</p>
<?php } else { ?>
			<ul class="k2-status-recency-list">
<?php foreach ($registrations as $row) { ?>
				<li>
					<span class="k2-status-recency-list__when"><?php echo k2_status_h(date('M j, Y', strtotime($row['joined']) ?: time())); ?></span>
					<?php echo k2_status_player_link($row['id'], $row['name']); ?>
				</li>
<?php } ?>
			</ul>
<?php } ?>
		</section>

		<div class="k2-status-room__leagues">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/status_period_competitions_section.php'; ?>
		</div>
		</div>
	</div>

</section>
