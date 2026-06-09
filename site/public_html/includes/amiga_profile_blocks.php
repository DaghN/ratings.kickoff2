<?php
/**
 * Amiga profile v0 blocks — career facts from playertable + rating chart shell.
 */
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_player_load.php';

/**
 * @param array<string, mixed> $pm from amiga_player_load()
 */
function amiga_profile_render_career(array $pm): void
{
    ?>
<section class="k2-amiga-profile-career" style="padding:0 1.25rem 1.5rem">
	<h3 class="k2-panel-heading">Career</h3>
	<dl class="k2-amiga-profile-dl" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(9rem,1fr));gap:0.75rem 1.25rem;margin:0">
		<div><dt style="opacity:0.75;font-size:0.85rem">W – D – L</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php
            echo (int) $pm['wins'] . ' – ' . (int) $pm['draws'] . ' – ' . (int) $pm['losses'];
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Win %</dt><dd style="margin:0"><?php
            echo $pm['win_pct'] !== null ? htmlspecialchars((string) $pm['win_pct'], ENT_QUOTES, 'UTF-8') . '%' : '—';
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Goals</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php
            echo (int) $pm['goals_for'] . ' – ' . (int) $pm['goals_against'];
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Peak rating</dt><dd style="margin:0"><?php
            echo $pm['peak_rating'] !== null ? k2_fmt_int($pm['peak_rating']) : '—';
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Opp. avg.</dt><dd style="margin:0"><?php
            echo $pm['opp_avg'] !== null ? k2_fmt_int(round($pm['opp_avg'])) : '—';
        ?></dd></div>
	</dl>
</section>
    <?php
}

/**
 * Profile suffix for one recent tournament row.
 *
 * World Cups have no overall standing in participation — use wc_medal podium rank.
 * Kitchen / marathon events use overall position and league points.
 *
 * @param array<string, mixed> $t participation row (name, position, points, wc_medal)
 */
function amiga_profile_tournament_result_label(array $t): string
{
    if (amiga_tournament_is_world_cup($t)) {
        $rank = match ((string) ($t['wc_medal'] ?? 'none')) {
            'gold' => 1,
            'silver' => 2,
            'bronze' => 3,
            default => 0,
        };
        if ($rank > 0) {
            return $rank . ordinal_suffix($rank);
        }

        return '—';
    }

    $position = (int) ($t['position'] ?? 0);
    if ($position <= 0) {
        return '—';
    }

    return $position . ordinal_suffix($position) . ' · ' . (int) ($t['points'] ?? 0) . ' pts';
}

/**
 * @param list<array<string, mixed>> $tournaments from amiga_player_tournament_participation_recent()
 */
function amiga_profile_render_recent_tournaments(array $tournaments): void
{
    if ($tournaments === []) {
        return;
    }
    ?>
<section class="k2-amiga-profile-tournaments" style="padding:0 1.25rem 1.5rem">
	<h3 class="k2-panel-heading">Recent tournaments</h3>
	<ul style="margin:0;padding-left:1.25rem">
	<?php foreach ($tournaments as $t) {
            $fragment = (int) ($t['knockout_ties'] ?? 0) > 0 ? 'bracket' : '';
            ?>
		<li><?php
            echo amiga_tournament_link((int) $t['id'], (string) $t['name'], $fragment);
            echo ' — ';
            echo htmlspecialchars(amiga_profile_tournament_result_label($t), ENT_QUOTES, 'UTF-8');
        ?></li>
	<?php } ?>
	</ul>
</section>
    <?php
}

function ordinal_suffix(int $n): string
{
    if ($n % 100 >= 11 && $n % 100 <= 13) {
        return 'th';
    }
    return match ($n % 10) {
        1 => 'st',
        2 => 'nd',
        3 => 'rd',
        default => 'th',
    };
}

/**
 * @param list<array<string, mixed>> $opponents from amiga_player_top_opponents()
 */
function amiga_profile_render_top_opponents(array $opponents): void
{
    if ($opponents === []) {
        return;
    }
    ?>
<section class="k2-amiga-profile-opponents" style="padding:0 1.25rem 1.5rem">
	<h3 class="k2-panel-heading">Top opponents</h3>
	<table class="k2-table k2-table--numeric-default k2-table--calm-stats" style="width:100%;max-width:36rem">
		<thead>
			<tr>
				<th class="k2-table-cell--left">Opponent</th>
				<th>W – D – L</th>
				<th>Games</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($opponents as $row) {
            $opponentId = (int) ($row['opponent_id'] ?? 0);
            $opponentName = (string) ($row['opponent_name'] ?? '');
            $wins = (int) ($row['wins'] ?? 0);
            $draws = (int) ($row['draws'] ?? 0);
            $losses = (int) ($row['losses'] ?? 0);
            $games = (int) ($row['games'] ?? 0);
            ?>
			<tr>
				<td class="k2-table-cell--left"><?php echo k2_amiga_player_link($opponentId, $opponentName); ?></td>
				<td style="font-variant-numeric:tabular-nums"><?php
                    echo $wins . ' – ' . $draws . ' – ' . $losses;
                ?></td>
				<td style="font-variant-numeric:tabular-nums"><?php echo $games; ?></td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
</section>
    <?php
}

function amiga_profile_render_rating_chart(int $playerId): void
{
    ?>
<section class="k2-amiga-profile-chart" style="padding:0 1.25rem 2rem">
	<div class="player-rating-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>" data-realm="amiga">
		<h3 class="k2-panel-heading">Elo rating</h3>
		<p class="k2-chart-block__hint">Calendar view: end-of-day rating after each tournament day. Tournament # view: one point per finalized event (no within-event zigzags). Axis starts at the first Amiga ladder game.</p>
		<div class="pm3d-rating-toggle" role="tablist" aria-label="Rating chart view">
			<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-view="date">By date</button>
			<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-view="game">By tournament #</button>
		</div>
		<p class="player-rating-chart-status pm3d-chart__status k2-chart-panel__status">Loading rating history…</p>
		<div class="player-rating-view player-rating-view--date">
			<p class="player-rating-peak-current-summary pm3d-chart__summary" hidden></p>
			<div class="k2-chart-frame">
				<canvas class="player-rating-canvas--date" aria-label="ELO rating over time"></canvas>
			</div>
		</div>
		<div class="player-rating-view player-rating-view--game" hidden>
			<p class="player-rating-game-peak-current-summary pm3d-chart__summary" hidden></p>
			<div class="k2-chart-frame">
				<canvas class="player-rating-canvas--game" aria-label="Rating by game number"></canvas>
			</div>
		</div>
	</div>
</section>
    <?php
}
