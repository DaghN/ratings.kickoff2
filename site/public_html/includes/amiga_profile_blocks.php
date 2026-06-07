<?php
/**
 * Amiga profile v0 blocks — career facts from playertable + rating chart shell.
 */
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_tournament_lib.php';

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
 * @param list<array<string, mixed>> $tournaments from amiga_player_recent_tournaments()
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
            echo (int) $t['position'] . ordinal_suffix((int) $t['position']);
            echo ' · ' . (int) $t['points'] . ' pts';
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

function amiga_profile_render_rating_chart(int $playerId): void
{
    ?>
<section class="k2-amiga-profile-chart" style="padding:0 1.25rem 2rem">
	<div class="player-rating-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>" data-realm="amiga">
		<h3 class="k2-panel-heading">Elo rating</h3>
		<p class="k2-chart-block__hint">Calendar view: end-of-day rating (one point per day). Game # view: every match. Axis starts at the first Amiga ladder game.</p>
		<div class="pm3d-rating-toggle" role="tablist" aria-label="Rating chart view">
			<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-view="date">By date</button>
			<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-view="game">By game #</button>
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
