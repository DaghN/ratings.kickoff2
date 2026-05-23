<?php
/**
 * Status room markup (hub status.php v1).
 *
 * Before include:
 *   $k2StatusRoom — array from k2_status_load_room()
 *   $k2StatusRoomError — set if load failed
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';

if (!function_exists('k2_status_player_link')) {
    function k2_status_player_link(int $id, string $name): string
    {
        return '<a class="k2-link-star" href="individual1.php?id=' . (int) $id . '">' . k2_status_h($name) . '</a>';
    }
}

$room = $k2StatusRoom ?? null;
$loadError = $k2StatusRoomError ?? null;

if ($loadError !== null || $room === null) {
    echo '<section class="k2-status-room"><p class="k2-status-room__error">Could not load status room.</p></section>';

    return;
}

$pulse = $room['pulse'];
$activeTop = $room['active_top'];
$monthly = $room['monthly'];
$online = $room['online'];
$liveGames = $room['live_games'];
$logins = $room['logins'];
$registrations = $room['registrations'];
$recentGames = $room['recent_games'];

$pulseParts = [];
$pulseParts[] = (int) $pulse['online'] . ' online';
$pulseParts[] = (int) $pulse['live_games'] . ' live ' . ((int) $pulse['live_games'] === 1 ? 'game' : 'games');
if ($pulse['last_login_ago'] !== '') {
    $pulseParts[] = 'last login ' . k2_status_h($pulse['last_login_ago']);
}
if ($pulse['games_played'] !== null) {
    $pulseParts[] = number_format((int) $pulse['games_played']) . ' rated games';
}
?>
<section class="k2-status-room" aria-label="Online status room">

	<p class="k2-status-tagline">Who&rsquo;s on tonight &middot; live games &middot; this month&rsquo;s table</p>

	<p class="k2-status-room__pulse"><?php echo implode(' <span class="k2-status-room__pulse-sep">·</span> ', $pulseParts); ?></p>

	<div class="k2-status-room__competition">
		<section class="k2-status-panel k2-status-panel--compact" aria-labelledby="k2-status-active-title">
			<div class="k2-status-panel__head">
				<h2 id="k2-status-active-title" class="k2-status-panel__title">Top rated (active)</h2>
				<p class="k2-status-panel__meta">Rated in the last 12 months · <a class="k2-link" href="ranked7.php">Full ladder</a></p>
			</div>
<?php if (!empty($room['active_top_error'])) { ?>
			<p class="k2-status-panel__empty">Could not load active ratings.</p>
<?php } elseif ($activeTop === []) { ?>
			<p class="k2-status-panel__empty">No active rated players in this window yet.</p>
<?php } else { ?>
			<div class="k2-table-wrap k2-table-wrap--compact">
				<table class="k2-table k2-status-table k2-status-table--dense">
					<thead>
						<tr>
							<th class="k2-status-table__num">#</th>
							<th>Player</th>
							<th class="k2-status-table__num">Elo</th>
							<th class="k2-status-table__num">Gp</th>
						</tr>
					</thead>
					<tbody class="black">
<?php
    $rank = 1;
    foreach ($activeTop as $row) {
        ?>
						<tr>
							<td class="k2-status-table__num"><?php echo $rank; ?></td>
							<td><?php echo k2_status_player_link($row['id'], $row['name']); ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['rating']; ?></td>
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

		<section class="k2-status-panel" aria-labelledby="k2-status-monthly-title">
			<div class="k2-status-panel__head">
				<h2 id="k2-status-monthly-title" class="k2-status-panel__title">Monthly league</h2>
<?php if ($monthly !== null) { ?>
				<p class="k2-status-panel__meta"><?php echo k2_status_h($monthly['label']); ?> · 3 pts win, 1 draw · <?php echo (int) $monthly['total_games']; ?> rated games</p>
<?php } ?>
			</div>
<?php if (!empty($room['monthly_error']) || $monthly === null) { ?>
			<p class="k2-status-panel__empty">Could not load monthly league.</p>
<?php } elseif ($monthly['rows'] === []) { ?>
			<p class="k2-status-panel__empty">No rated games this month yet — table fills as matches finish.</p>
<?php } else { ?>
			<div class="k2-table-wrap k2-table-wrap--compact">
				<table class="k2-table k2-status-table k2-status-table--dense">
					<thead>
						<tr>
							<th class="k2-status-table__num">#</th>
							<th>Player</th>
							<th class="k2-status-table__num" title="Played">Pld</th>
							<th class="k2-status-table__num">W</th>
							<th class="k2-status-table__num">D</th>
							<th class="k2-status-table__num">L</th>
							<th class="k2-status-table__num">GF</th>
							<th class="k2-status-table__num">GA</th>
							<th class="k2-status-table__num">GD</th>
							<th class="k2-status-table__num">Pts</th>
						</tr>
					</thead>
					<tbody class="black">
<?php
    $rank = 1;
    foreach ($monthly['rows'] as $row) {
        $gd = (int) $row['gd'];
        ?>
						<tr>
							<td class="k2-status-table__num"><?php echo $rank; ?></td>
							<td><?php echo k2_status_player_link($row['id'], $row['name']); ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['played']; ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['wins']; ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['draws']; ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['losses']; ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['gf']; ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['ga']; ?></td>
							<td class="k2-status-table__num"><?php echo $gd > 0 ? '+' . $gd : (string) $gd; ?></td>
							<td class="k2-status-table__num"><strong><?php echo (int) $row['pts']; ?></strong></td>
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
	</div>

	<div class="k2-status-room__live">
		<section class="k2-status-panel" aria-labelledby="k2-status-now-title">
			<h2 id="k2-status-now-title" class="k2-status-panel__title">Right now</h2>

			<h3 class="k2-status-panel__subtitle">Online</h3>
<?php if ($online === []) { ?>
			<p class="k2-status-panel__empty">Nobody flagged online — check recent logins.</p>
<?php } else { ?>
			<ul class="k2-status-name-list">
<?php foreach ($online as $row) { ?>
				<li><?php echo k2_status_player_link($row['id'], $row['name']); ?></li>
<?php } ?>
			</ul>
<?php } ?>

			<h3 class="k2-status-panel__subtitle">Live games</h3>
<?php if ($liveGames === []) { ?>
			<p class="k2-status-panel__empty">No live games in progress.</p>
<?php } else { ?>
			<ul class="k2-status-live-list">
<?php foreach ($liveGames as $g) { ?>
				<li>
					<span class="k2-status-live-list__time"><?php echo k2_status_h(k2_status_short_time($g['start'])); ?></span>
					<span class="k2-status-live-list__match">
						<?php echo k2_status_h($g['name_a']); ?>
						<strong><?php echo (int) $g['score_a']; ?>–<?php echo (int) $g['score_b']; ?></strong>
						<?php echo k2_status_h($g['name_b']); ?>
					</span>
					<span class="k2-status-live-list__meta">P<?php echo (int) $g['period']; ?></span>
				</li>
<?php } ?>
			</ul>
<?php } ?>
		</section>

		<div class="k2-status-room__recency">
			<section class="k2-status-panel k2-status-panel--mini" aria-labelledby="k2-status-logins-title">
				<h2 id="k2-status-logins-title" class="k2-status-panel__title">Recent logins</h2>
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

			<section class="k2-status-panel k2-status-panel--mini" aria-labelledby="k2-status-reg-title">
				<h2 id="k2-status-reg-title" class="k2-status-panel__title">New players</h2>
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

			<section class="k2-status-panel k2-status-panel--mini" aria-labelledby="k2-status-games-title">
				<h2 id="k2-status-games-title" class="k2-status-panel__title">Recent games</h2>
<?php if ($recentGames === []) { ?>
				<p class="k2-status-panel__empty">—</p>
<?php } else { ?>
				<ul class="k2-status-recency-list">
<?php foreach ($recentGames as $g) { ?>
					<li>
						<span class="k2-status-recency-list__when"><?php echo k2_status_h(k2_status_short_time($g['at'])); ?></span>
						<a class="k2-link-star" href="individual1.php?id=<?php echo (int) $g['id_a']; ?>"><?php echo k2_status_h($g['name_a']); ?></a>
						<strong><?php echo (int) $g['goals_a']; ?>–<?php echo (int) $g['goals_b']; ?></strong>
						<a class="k2-link-star" href="individual1.php?id=<?php echo (int) $g['id_b']; ?>"><?php echo k2_status_h($g['name_b']); ?></a>
						<a class="k2-link k2-status-recency-list__detail" href="game.php?id=<?php echo (int) $g['id']; ?>">match</a>
					</li>
<?php } ?>
				</ul>
<?php } ?>
			</section>
		</div>
	</div>

	<p class="k2-hub-panel__hint">Leaderboards, full game archive, trends, and records are on the tabs above.</p>

</section>
