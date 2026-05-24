<?php
/**
 * Status room markup (hub status.php).
 *
 * Before include:
 *   $k2StatusRoom — array from k2_status_load_room()
 *   $k2StatusRoomError — set if load failed
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_game_rating_adjustment.php';

if (!function_exists('k2_status_player_link')) {
    function k2_status_player_link(int $id, string $name): string
    {
        return '<a class="k2-link-star" href="individual1.php?id=' . (int) $id . '">' . k2_status_h($name) . '</a>';
    }
}

if (!function_exists('k2_status_player_link_or_name')) {
    function k2_status_player_link_or_name(int $id, string $name): string
    {
        if ($id > 0) {
            return k2_status_player_link($id, $name);
        }

        return k2_status_h($name);
    }
}

function k2_status_player_with_adjustment(int $id, string $name, float $adj): string
{
    return k2_status_player_link($id, $name) . ' ' . k2_game_rating_adjustment_span_html($adj);
}

function k2_status_league_podium_medal(int $rank): string
{
    static $svgByRank = null;
    if ($svgByRank === null) {
        $svgByRank = [
            1 => k2_status_league_podium_medal_svg('gold', '1st place', '1'),
            2 => k2_status_league_podium_medal_svg('silver', '2nd place', '2'),
            3 => k2_status_league_podium_medal_svg('bronze', '3rd place', '3'),
        ];
    }

    return $svgByRank[$rank] ?? '';
}

function k2_status_league_podium_medal_svg(string $variant, string $ariaLabel, string $place): string
{
    $id = 'k2-medal-' . $variant;
    $palettes = [
        'gold' => [
            'disk' => ['#fff9e6', '#ffe566', '#d4af37', '#8b6914'],
            'rim' => '#5c4508',
            'glyph' => '#4a3606',
            'glyphShadow' => '#f7e7a8',
            'ribbon' => ['#ff4d6d', '#c9184a', '#800f2f'],
            'ribbonFold' => '#590d22',
        ],
        'silver' => [
            'disk' => ['#ffffff', '#f0f4f8', '#b8c4ce', '#6b7a86'],
            'rim' => '#3d4852',
            'glyph' => '#2a3238',
            'glyphShadow' => '#eef2f6',
            'ribbon' => ['#4cc9f0', '#4895ef', '#2b4a7a'],
            'ribbonFold' => '#1b2f4d',
        ],
        'bronze' => [
            'disk' => ['#ffe8d6', '#e8a87c', '#cd7f32', '#7a4a1f'],
            'rim' => '#4a2c12',
            'glyph' => '#3a220f',
            'glyphShadow' => '#f5d4bc',
            'ribbon' => ['#95d5b2', '#52b788', '#2d6a4f'],
            'ribbonFold' => '#1b4332',
        ],
    ];
    $p = $palettes[$variant] ?? $palettes['bronze'];
    $aria = k2_status_h($ariaLabel);
    $placeEsc = k2_status_h($place);

    return '<span class="k2-status-medal k2-status-medal--' . k2_status_h($variant) . '" role="img" aria-label="' . $aria . '">'
        . '<svg class="k2-status-medal__svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" aria-hidden="true" focusable="false">'
        . '<defs>'
        . '<radialGradient id="' . $id . '-disk" cx="38%" cy="36%" r="68%">'
        . '<stop offset="0%" stop-color="' . $p['disk'][0] . '"/>'
        . '<stop offset="38%" stop-color="' . $p['disk'][1] . '"/>'
        . '<stop offset="72%" stop-color="' . $p['disk'][2] . '"/>'
        . '<stop offset="100%" stop-color="' . $p['disk'][3] . '"/>'
        . '</radialGradient>'
        . '<linearGradient id="' . $id . '-shine" x1="0%" y1="0%" x2="100%" y2="100%">'
        . '<stop offset="0%" stop-color="#ffffff" stop-opacity="0"/>'
        . '<stop offset="45%" stop-color="#ffffff" stop-opacity="0.85"/>'
        . '<stop offset="100%" stop-color="#ffffff" stop-opacity="0"/>'
        . '</linearGradient>'
        . '</defs>'
        . '<circle cx="16" cy="16" r="14" fill="' . $p['rim'] . '"/>'
        . '<circle cx="16" cy="16" r="12.5" fill="url(#' . $id . '-disk)"/>'
        . '<circle cx="16" cy="16" r="12.5" fill="none" stroke="' . $p['disk'][0] . '" stroke-opacity="0.55" stroke-width="0.65"/>'
        . '<circle cx="16" cy="16" r="9" fill="none" stroke="' . $p['rim'] . '" stroke-opacity="0.35" stroke-width="0.5"/>'
        . '<ellipse class="k2-status-medal__glint" cx="12" cy="12.5" rx="5" ry="3" fill="url(#' . $id . '-shine)" transform="rotate(-28 12 12.5)"/>'
        . '<text x="16" y="18.5" text-anchor="middle" font-family="Georgia, \'Times New Roman\', serif" font-size="10" font-weight="700" fill="' . $p['glyphShadow'] . '" opacity="0.45">' . $placeEsc . '</text>'
        . '<text x="16" y="18" text-anchor="middle" font-family="Georgia, \'Times New Roman\', serif" font-size="10" font-weight="700" fill="' . $p['glyph'] . '">' . $placeEsc . '</text>'
        . '</svg></span>';
}

function k2_status_render_monthly_table(?array $monthly, bool $showPodiumMedals = false): void
{
    if ($monthly === null || $monthly['rows'] === []) {
        return;
    }
    ?>
			<div class="k2-table-wrap k2-table-wrap--compact">
				<table class="k2-table k2-status-table k2-status-table--dense<?php echo $showPodiumMedals ? ' k2-status-table--podium' : ''; ?>">
					<thead>
						<tr>
							<th class="k2-status-table__num">#</th>
							<th class="k2-status-table__player">Player</th>
							<th class="k2-status-table__num" title="Played">Pld</th>
							<th class="k2-status-table__num">W</th>
							<th class="k2-status-table__num">D</th>
							<th class="k2-status-table__num">L</th>
							<th class="k2-status-table__num">GF</th>
							<th class="k2-status-table__num">GA</th>
							<th class="k2-status-table__num">GD</th>
							<th class="k2-status-table__num">Pts</th>
<?php if ($showPodiumMedals) { ?>
							<th class="k2-status-table__medal" scope="col"><span class="visually-hidden">Award</span></th>
<?php } ?>
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
							<td class="k2-status-table__player"><?php echo k2_status_player_link($row['id'], $row['name']); ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['played']; ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['wins']; ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['draws']; ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['losses']; ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['gf']; ?></td>
							<td class="k2-status-table__num"><?php echo (int) $row['ga']; ?></td>
							<td class="k2-status-table__num"><?php echo $gd > 0 ? '+' . $gd : (string) $gd; ?></td>
							<td class="k2-status-table__num"><span class="blue"><?php echo (int) $row['pts']; ?></span></td>
<?php if ($showPodiumMedals) { ?>
							<td class="k2-status-table__medal"><?php echo $rank <= 3 ? k2_status_league_podium_medal($rank) : ''; ?></td>
<?php } ?>
						</tr>
<?php
        ++$rank;
    }
    ?>
					</tbody>
				</table>
			</div>
<?php
}

$room = $k2StatusRoom ?? null;
$loadError = $k2StatusRoomError ?? null;

if ($loadError !== null || $room === null) {
    echo '<section class="k2-status-room"><p class="k2-status-room__error">Could not load status room.</p></section>';

    return;
}

$arc = $room['arc'];
$activeTop = $room['active_top'];
$monthlyCurrent = $room['monthly_current'];
$monthlyPrev = $room['monthly_prev'];
$online = $room['online'];
$liveGames = $room['live_games'];
$logins = $room['logins'];
$registrations = $room['registrations'];
$recentGames = $room['recent_games'];

$leagueMetaCurrent = $monthlyCurrent !== null ? k2_status_league_meta_line($monthlyCurrent) : '';
$leagueMetaPrev = $monthlyPrev !== null ? k2_status_league_meta_line($monthlyPrev) : '';
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
						<span class="k2-status-score"><?php echo (int) $g['score_a']; ?>–<?php echo (int) $g['score_b']; ?></span>
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
				<span class="blue"><?php echo number_format((int) $arc['games']); ?></span> rated games since <?php echo k2_status_h($arc['since_label']); ?>
			</p>
		</section>

		<section class="k2-status-panel k2-status-panel--tight k2-status-room__panel-heritage" aria-label="Original Amiga box art">
			<div class="k2-heritage-box k2-heritage-box--status-panel">
				<img class="k2-heritage-box__art" src="images/KO2BoxFront.jpg" width="88" alt="" loading="lazy" decoding="async" />
				<p class="k2-heritage-box__caption">KO2 · 1990</p>
			</div>
		</section>

		<section class="k2-status-panel k2-status-panel--tight k2-status-panel--compact k2-status-room__panel-leaderboard" aria-labelledby="k2-status-active-title">
			<div class="k2-status-panel__head">
				<h2 id="k2-status-active-title" class="k2-panel-heading">Leaderboard <span class="k2-panel-heading__sep" aria-hidden="true">·</span> <span class="blue"><?php echo number_format($activePlayerCount); ?></span> active players</h2>
				<p class="k2-status-panel__meta">Active in last 12 months · <a class="k2-link" href="ranked7.php">Full ladder</a></p>
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
							<th class="k2-status-table__player">Player</th>
							<th class="k2-status-table__num">Elo</th>
							<th class="k2-status-table__num">Games</th>
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
							<td class="k2-status-table__num"><span class="blue"><?php echo (int) $row['rating']; ?></span></td>
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
			<h2 id="k2-status-games-title" class="k2-panel-heading">Recent games</h2>
<?php if ($recentGames === []) { ?>
			<p class="k2-status-panel__empty">—</p>
<?php } else { ?>
			<ul class="k2-status-recency-list">
<?php foreach ($recentGames as $g) { ?>
				<li>
					<span class="k2-status-recency-list__when"><?php echo k2_status_h(k2_status_short_time($g['at'])); ?></span>
					<span class="k2-status-match">
						<span class="k2-status-match__side"><?php echo k2_status_player_with_adjustment($g['id_a'], $g['name_a'], (float) $g['adjustment_a']); ?></span>
						<span class="k2-status-score"><?php echo (int) $g['goals_a']; ?>–<?php echo (int) $g['goals_b']; ?></span>
						<span class="k2-status-match__side"><?php echo k2_status_player_with_adjustment($g['id_b'], $g['name_b'], (float) $g['adjustment_b']); ?></span>
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

		<section class="k2-status-panel k2-status-panel--tight k2-status-room__panel-league" aria-labelledby="k2-status-monthly-title" data-k2-status-league>
			<div class="k2-status-panel__head k2-status-panel__head--league">
				<div class="k2-status-league-head__title-row">
					<h2 id="k2-status-monthly-title" class="k2-panel-heading">Monthly league</h2>
					<div class="k2-status-league-toggle" role="group" aria-label="League month">
						<button type="button" class="k2-status-league-toggle__btn is-active" data-league-target="current" aria-pressed="true">This month</button>
						<button type="button" class="k2-status-league-toggle__btn" data-league-target="prev" aria-pressed="false">Previous month</button>
					</div>
				</div>
				<p class="k2-status-panel__meta" data-league-meta><?php echo k2_status_h($leagueMetaCurrent); ?></p>
			</div>
			<div class="k2-status-league-panels">
				<div class="k2-status-league-panel" data-league-panel="current" data-league-meta-text="<?php echo k2_status_h($leagueMetaCurrent); ?>">
<?php if (!empty($room['monthly_current_error']) || $monthlyCurrent === null) { ?>
					<p class="k2-status-panel__empty">Could not load this month&rsquo;s league.</p>
<?php } elseif ($monthlyCurrent['rows'] === []) { ?>
					<p class="k2-status-panel__empty">No rated games in <?php echo k2_status_h($monthlyCurrent['label']); ?> yet.</p>
<?php } else {
    k2_status_render_monthly_table($monthlyCurrent);
} ?>
				</div>
				<div class="k2-status-league-panel" data-league-panel="prev" data-league-meta-text="<?php echo k2_status_h($leagueMetaPrev); ?>" hidden>
<?php if (!empty($room['monthly_prev_error']) || $monthlyPrev === null) { ?>
					<p class="k2-status-panel__empty">Could not load previous month.</p>
<?php } elseif ($monthlyPrev['rows'] === []) { ?>
					<p class="k2-status-panel__empty">No rated games in <?php echo k2_status_h($monthlyPrev['label']); ?>.</p>
<?php } else {
    k2_status_render_monthly_table($monthlyPrev, true);
} ?>
				</div>
			</div>
		</section>
		</div>
	</div>

</section>
