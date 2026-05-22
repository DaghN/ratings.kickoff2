<?php
/**
 * Profile pass 3 blocks — three visually distinct variants (A Ember / B Ledger / C Sigil).
 * Requires $pm, profile_mock_helpers.
 */

function pm3_render_nav(array $pm, string $variant): void
{
    $id = (int) $pm['id'];
    $tabs = [
        'profile' => ['href' => 'individual1.php?id=' . $id, 'label' => 'Profile'],
        'games' => ['href' => 'individual3.php?id=' . $id, 'label' => 'Games'],
        'wins' => ['href' => 'individual2a.php?id=' . $id, 'label' => 'Wins'],
        'goals' => ['href' => 'individual2b.php?id=' . $id, 'label' => 'Goals'],
        'dds' => ['href' => 'individual2c.php?id=' . $id, 'label' => 'DDs'],
    ];
    $cls = 'pm3-nav pm3-nav--' . pm_h($variant);
    ?>
<nav class="<?php echo $cls; ?>" aria-label="Player sections">
<?php foreach ($tabs as $tabId => $tab) { ?>
	<a href="<?php echo $tab['href']; ?>" class="pm3-nav__link<?php echo $tabId === 'profile' ? ' is-active' : ''; ?>"><?php echo pm_h($tab['label']); ?></a>
<?php } ?>
</nav>
    <?php
}

function pm3_render_core_a(array $pm): void
{
    $rating = $pm['rating'] !== null ? (int) $pm['rating'] : '—';
    $peak = $pm['peak'] !== null ? (int) $pm['peak'] : '—';
    $rank = $pm['display'] ? '#' . (int) $pm['rank'] : '—';
    ?>
<header class="pm3-core pm3-core--monument" aria-label="Player identity">
	<p class="pm3-monument__name"><?php echo pm_h($pm['name']); ?></p>
	<div class="pm3-monument__plate" aria-label="Current rating">
		<span class="pm3-monument__plate-label">Rating now</span>
		<span class="pm3-monument__plate-value"><?php echo $rating; ?></span>
		<span class="pm3-monument__plate-glow" aria-hidden="true"></span>
	</div>
	<div class="pm3-monument__trio">
		<div class="pm3-monument__tile">
			<span class="pm3-monument__tile-value"><?php echo pm_h($rank); ?></span>
			<span class="pm3-monument__tile-label">Ladder rank</span>
		</div>
		<div class="pm3-monument__tile pm3-monument__tile--peak">
			<span class="pm3-monument__tile-value"><?php echo $peak; ?></span>
			<span class="pm3-monument__tile-label">Peak rating</span>
		</div>
		<div class="pm3-monument__tile">
			<span class="pm3-monument__tile-value"><?php echo number_format((int) $pm['games']); ?></span>
			<span class="pm3-monument__tile-label">Rated games</span>
		</div>
	</div>
</header>
    <?php
}

function pm3_render_core_b(array $pm): void
{
    $rating = $pm['rating'] !== null ? (int) $pm['rating'] : '—';
    $rank = $pm['display'] ? '#' . (int) $pm['rank'] : '';
    ?>
<header class="pm3-core pm3-core--ledger" aria-label="Player identity">
	<div class="pm3-ledger__identity">
		<h1 class="pm3-ledger__name"><?php echo pm_h($pm['name']); ?><?php if ($rank !== '') { ?> <span class="pm3-ledger__rank"><?php echo pm_h($rank); ?></span><?php } ?></h1>
		<p class="pm3-ledger__since">On ladder since <?php echo pm_h($pm['join_date']); ?></p>
	</div>
	<div class="pm3-ledger__focus">
		<p class="pm3-ledger__rating-label">Rating</p>
		<p class="pm3-ledger__rating-value"><?php echo $rating; ?></p>
		<dl class="pm3-ledger__side">
			<div><dt>Peak</dt><dd><?php echo $pm['peak'] !== null ? (int) $pm['peak'] : '—'; ?></dd></div>
			<div><dt>Games</dt><dd><?php echo number_format((int) $pm['games']); ?></dd></div>
		</dl>
	</div>
</header>
    <?php
}

function pm3_render_core_c(array $pm): void
{
    $rating = $pm['rating'] !== null ? (int) $pm['rating'] : '—';
    $rank = $pm['display'] ? '#' . (int) $pm['rank'] : '';
    ?>
<header class="pm3-core pm3-core--sigil" aria-label="Player identity">
	<div class="pm3-sigil__ring" aria-hidden="true"><span></span></div>
	<div class="pm3-sigil__body">
		<h1 class="pm3-sigil__name"><?php echo pm_h($pm['name']); ?><?php if ($rank !== '') { ?><span class="pm3-sigil__rank"><?php echo pm_h($rank); ?></span><?php } ?></h1>
		<p class="pm3-sigil__meta">
			Peak <strong><?php echo $pm['peak'] !== null ? (int) $pm['peak'] : '—'; ?></strong>
			· <?php echo number_format((int) $pm['games']); ?> rated games
		</p>
	</div>
	<div class="pm3-sigil__rating" aria-label="Current rating">
		<span class="pm3-sigil__rating-num"><?php echo $rating; ?></span>
		<span class="pm3-sigil__rating-lbl">now</span>
	</div>
</header>
    <?php
}

function pm3_render_facts(array $pm): void
{
    ?>
<section class="pm3-facts" aria-label="Participation facts">
	<ul class="pm3-facts__chips">
		<li class="pm3-facts__chip pm3-facts__chip--tenure"><span><?php echo pm_h($pm['tenure_label']); ?></span> on ladder</li>
		<li class="pm3-facts__chip"><span><?php echo number_format((int) $pm['games']); ?></span> rated games</li>
		<li class="pm3-facts__chip"><span><?php echo number_format((int) $pm['different_opponents']); ?></span> opponents</li>
		<li class="pm3-facts__chip pm3-facts__chip--muted">First rated <?php echo pm_h($pm['first_game_date']); ?></li>
	</ul>
</section>
    <?php
}

function pm3_render_activity(array $pm): void
{
    ?>
<section class="pm3-activity" aria-label="Recent activity">
	<div class="pm3-activity__pulse">
		<span class="pm3-activity__label">Presence</span>
		<p class="pm3-activity__line">
			<strong>Last seen</strong> <?php echo pm_h($pm['last_login']); ?>
			<span class="pm3-activity__dot">·</span>
			<strong>Last match</strong> <?php echo pm_h($pm['last_game']); ?>
		</p>
	</div>
	<div class="pm3-activity__pace">
		<div class="pm3-activity__pace-item">
			<span class="pm3-activity__pace-num"><?php echo (int) $pm['games_this_month']; ?></span>
			<span class="pm3-activity__pace-lbl">this month</span>
		</div>
		<div class="pm3-activity__pace-item">
			<span class="pm3-activity__pace-num"><?php echo (int) $pm['games_this_year']; ?></span>
			<span class="pm3-activity__pace-lbl">this year</span>
		</div>
	</div>
</section>
    <?php
}

function pm3_render_moments(array $pm, string $layout): void
{
    $cls = 'pm3-moments pm3-moments--' . pm_h($layout);
    ?>
<section class="pm3-section <?php echo $cls; ?>">
	<h2 class="pm3-section__title">Moments</h2>
	<p class="pm3-section__hint">Career highlights — the games people remember.</p>
	<div class="pm3-moments__grid">
		<article class="pm3-moment pm3-moment--streak">
			<span class="pm3-moment__glyph" aria-hidden="true">🏆</span>
			<span class="pm3-moment__tag">Streak</span>
			<h3 class="pm3-moment__label">Longest win run</h3>
			<p class="pm3-moment__score"><?php echo (int) $pm['longest_win_streak']; ?> wins</p>
		</article>
		<?php foreach ($pm['trophies'] as $t) { ?>
		<article class="pm3-moment">
			<span class="pm3-moment__glyph" aria-hidden="true"><?php echo $t['icon']; ?></span>
			<span class="pm3-moment__tag"><?php echo pm_h($t['tag']); ?></span>
			<h3 class="pm3-moment__label"><?php echo pm_h($t['label']); ?></h3>
			<p class="pm3-moment__score">
				<a href="game.php?id=<?php echo (int) $t['game_id']; ?>"><?php echo pm_h($t['score']); ?></a>
			</p>
			<p class="pm3-moment__meta">
				<span class="<?php echo pm_h($t['outcome_class']); ?>"><?php echo pm_h($t['outcome']); ?></span>
				· vs <a href="individual1.php?id=<?php echo (int) $t['opponent_id']; ?>"><?php echo pm_h($t['opponent_name']); ?></a>
				· <?php echo pm_h($t['year']); ?>
			</p>
		</article>
		<?php } ?>
	</div>
</section>
    <?php
}

function pm3_render_busiest(array $pm, string $layout): void
{
    $bm = $pm['busiest']['month'] ?? null;
    $bd = $pm['busiest']['day'] ?? null;
    $by = $pm['busiest']['year'] ?? null;
    ?>
<section class="pm3-section pm3-busiest pm3-busiest--<?php echo pm_h($layout); ?>">
	<h2 class="pm3-section__title">Peak activity</h2>
	<ol class="pm3-busiest__list">
		<li>
			<span class="pm3-busiest__kind">Busiest day</span>
			<strong><?php echo $bd ? (int) $bd['count'] . ' games' : '—'; ?></strong>
			<em><?php echo $bd ? pm_h(pm2_format_busiest_day((string) $bd['key'])) : ''; ?></em>
		</li>
		<li>
			<span class="pm3-busiest__kind">Busiest month</span>
			<strong><?php echo $bm ? (int) $bm['count'] . ' games' : '—'; ?></strong>
			<em><?php echo $bm ? pm_h(pm2_format_busiest_month((string) $bm['key'])) : ''; ?></em>
		</li>
		<li>
			<span class="pm3-busiest__kind">Busiest year</span>
			<strong><?php echo $by ? (int) $by['count'] . ' games' : '—'; ?></strong>
			<em><?php echo $by ? pm_h((string) $by['key']) : ''; ?></em>
		</li>
	</ol>
</section>
    <?php
}

function pm3_render_rivalry_a(array $pm): void
{
    $r = $pm['featured_rival'] ?? null;
    if ($r === null) {
        return;
    }
    $h = $pm['rival_h2h'];
    ?>
<section class="pm3-duel" aria-label="Featured rivalry">
	<div class="pm3-duel__corner pm3-duel__corner--you">
		<span class="pm3-duel__role">You</span>
		<span class="pm3-duel__name"><?php echo pm_h($pm['name']); ?></span>
	</div>
	<div class="pm3-duel__center">
		<p class="pm3-duel__vs">vs</p>
		<p class="pm3-duel__count"><strong><?php echo number_format((int) $r['games']); ?></strong> rated games</p>
		<p class="pm3-duel__record">
			<span class="pm-outcome--win"><?php echo (int) $h['wins']; ?>W</span>
			<span class="pm-outcome--draw"><?php echo (int) $h['draws']; ?>D</span>
			<span class="pm-outcome--loss"><?php echo (int) $h['losses']; ?>L</span>
		</p>
		<p class="pm3-duel__tag">All-time #1 opponent</p>
	</div>
	<div class="pm3-duel__corner pm3-duel__corner--rival">
		<span class="pm3-duel__role">Rival</span>
		<a class="pm3-duel__name" href="individual1.php?id=<?php echo (int) $r['id']; ?>"><?php echo pm_h($r['name']); ?></a>
	</div>
</section>
    <?php
}

function pm3_render_rivalry_b(array $pm): void
{
    $r = $pm['featured_rival'] ?? null;
    if ($r === null) {
        return;
    }
    $h = $pm['rival_h2h'];
    ?>
<section class="pm3-scoreboard" aria-label="Featured rivalry">
	<div class="pm3-scoreboard__head">
		<h2 class="pm3-section__title">Featured rivalry</h2>
		<a href="individual1.php?id=<?php echo (int) $r['id']; ?>"><?php echo pm_h($r['name']); ?></a>
	</div>
	<div class="pm3-scoreboard__body">
		<div class="pm3-scoreboard__stat">
			<span class="pm3-scoreboard__num"><?php echo number_format((int) $r['games']); ?></span>
			<span class="pm3-scoreboard__lbl">games together</span>
		</div>
		<div class="pm3-scoreboard__wdl">
			<span class="pm-outcome--win"><?php echo (int) $h['wins']; ?>W</span>
			<span class="pm-outcome--draw"><?php echo (int) $h['draws']; ?>D</span>
			<span class="pm-outcome--loss"><?php echo (int) $h['losses']; ?>L</span>
		</div>
		<p class="pm3-scoreboard__note">Charts below open on this opponent first.</p>
	</div>
</section>
    <?php
}

function pm3_render_rivalry_c(array $pm): void
{
    $r = $pm['featured_rival'] ?? null;
    if ($r === null) {
        return;
    }
    $h = $pm['rival_h2h'];
    ?>
<p class="pm3-rival-pin" aria-label="Featured rivalry">
	<span class="pm3-rival-pin__label">Primary rival</span>
	<a href="individual1.php?id=<?php echo (int) $r['id']; ?>"><?php echo pm_h($r['name']); ?></a>
	— <?php echo number_format((int) $r['games']); ?> games ·
	<span class="pm-outcome--win"><?php echo (int) $h['wins']; ?>W</span>
	<span class="pm-outcome--draw"><?php echo (int) $h['draws']; ?>D</span>
	<span class="pm-outcome--loss"><?php echo (int) $h['losses']; ?>L</span>
</p>
    <?php
}

function pm3_render_calendar(int $playerId, int $year, string $size = 'standard'): void
{
    ?>
<section class="pm3-section pm3-cal pm3-cal--<?php echo pm_h($size); ?>" data-player-id="<?php echo $playerId; ?>" data-year="<?php echo $year; ?>" aria-label="Calendar activity">
	<h2 class="pm3-section__title">Played days</h2>
	<p class="pm3-section__hint">Each cell is a calendar day — green means at least one rated game (not intensity).</p>
	<p class="pm3-cal__status pm3-muted">Loading calendar…</p>
	<div class="pm3-cal__year"></div>
	<p class="pm3-cal__legend"><span class="pm3-cal__cell pm3-cal__cell--play"></span> played · empty = no rated game</p>
</section>
    <?php
}

function pm3_render_charts_prod(int $playerId): void
{
    $uid = 'pm3-' . $playerId;
    ?>
<div class="pm3-charts-prod">

<div class="player-rating-chart" data-player-id="<?php echo $playerId; ?>">
	<p style="margin: 0 0 4px 0; color: var(--k2-text-primary, #e6edf3);">Rating over time</p>
	<p class="player-rating-chart-status" style="margin: 0 0 8px 0;">Loading rating history…</p>
	<p class="player-rating-peak-current-summary" style="display: none; margin: 0 0 8px 0; color: var(--k2-text-primary, #e6edf3); font-size: 1.05em;"></p>
	<canvas width="960" height="345" aria-label="ELO rating over time"></canvas>
</div>

<div class="player-games-month-chart" data-player-id="<?php echo $playerId; ?>">
	<p style="margin: 0 0 4px 0; color: var(--k2-text-primary, #e6edf3);">Games per month</p>
	<p class="player-games-month-chart-status" style="margin: 0 0 8px 0;">Loading games per month…</p>
	<canvas width="960" height="271" aria-label="Games per calendar month"></canvas>
</div>

<div class="player-rating-game-chart" data-player-id="<?php echo $playerId; ?>">
	<p style="margin: 0 0 4px 0; color: var(--k2-text-primary, #e6edf3);">Rating by game number</p>
	<p style="margin: 0 0 4px 0; color: var(--k2-text-muted, #8b949e); font-size: 0.9em;">ELO after each rated game — equal spacing, not calendar time.</p>
	<p class="player-rating-game-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
	<canvas width="960" height="345" aria-label="Rating by game number"></canvas>
</div>

<div class="player-winrate-opponent-chart" data-player-id="<?php echo $playerId; ?>">
	<p style="margin: 0 0 4px 0; color: var(--k2-text-primary, #e6edf3);">Win rate vs opponent rating</p>
	<p style="margin: 0 0 4px 0; color: var(--k2-text-muted, #8b949e); font-size: 0.9em;">Win % by opponent pre-game rating (50-point buckets).</p>
	<p class="player-winrate-opponent-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
	<canvas width="960" height="295" aria-label="Win rate versus opponent rating"></canvas>
</div>

<div class="player-top-opponents-chart" data-player-id="<?php echo $playerId; ?>">
	<p style="margin: 0 0 4px 0; color: var(--k2-text-primary, #e6edf3);">Most played opponents</p>
	<p style="margin: 0 0 4px 0; color: var(--k2-text-muted, #8b949e); font-size: 0.9em;">Top 20 by rated games — click a bar for head-to-head below.</p>
	<p class="player-top-opponents-chart-status" style="margin: 0 0 8px 0;">Loading…</p>
	<canvas width="960" height="591" aria-label="Most played opponents"></canvas>
</div>

<div class="player-head-to-head-chart" data-player-id="<?php echo $playerId; ?>">
	<p style="margin: 0 0 4px 0; color: var(--k2-text-primary, #e6edf3);">Head-to-head vs <span class="player-head-to-head-opponent-name">…</span></p>
	<p class="player-head-to-head-meta" style="margin: 0 0 4px 0; color: var(--k2-text-muted, #8b949e); font-size: 0.9em;"></p>
	<p class="player-head-to-head-chart-status" style="margin: 0 0 8px 0;">Waiting for opponent…</p>
	<canvas width="960" height="345" aria-label="Head-to-head cumulative wins"></canvas>
</div>

<div class="player-compare-rating-chart" data-player-id="<?php echo $playerId; ?>">
	<p style="margin: 0 0 4px 0; color: var(--k2-text-primary, #e6edf3);">Rating comparison vs <span class="player-compare-rating-opponent-name">…</span></p>
	<p class="player-compare-rating-meta" style="margin: 0 0 4px 0; color: var(--k2-text-muted, #8b949e); font-size: 0.9em;"></p>
	<p class="player-compare-rating-chart-status" style="margin: 0 0 8px 0;">Waiting for opponent…</p>
	<canvas width="960" height="345" aria-label="Rating comparison"></canvas>
</div>

<div class="player-h2h-opponent-search player-search" data-player-id="<?php echo $playerId; ?>" data-realm="online" role="search">
	<label class="player-search-label" for="<?php echo pm_h($uid); ?>-h2h">Compare rating history with another player</label>
	<input id="<?php echo pm_h($uid); ?>-h2h" class="player-search-input player-h2h-search-input" type="search" maxlength="32" autocomplete="off" spellcheck="false" placeholder="Search player name…" />
	<ul class="player-search-results player-h2h-search-results" role="listbox" hidden></ul>
</div>

</div>
    <?php
}

function pm3_render_stats(array $pm): void
{
    ?>
<section class="pm3-section pm3-stats">
	<h2 class="pm3-section__title">Career numbers</h2>
	<div class="pm3-stats__grid">
		<div class="pm3-stats__cell">
			<span class="pm3-stats__label">Record</span>
			<span class="pm3-stats__value"><?php echo number_format((int) $pm['wins']); ?>W · <?php echo number_format((int) $pm['draws']); ?>D · <?php echo number_format((int) $pm['losses']); ?>L</span>
			<span class="pm3-stats__sub"><?php echo $pm['win_pct']; ?>% wins</span>
		</div>
		<div class="pm3-stats__cell">
			<span class="pm3-stats__label">Goals</span>
			<span class="pm3-stats__value"><?php echo number_format((int) $pm['goals_for']); ?> for · <?php echo number_format((int) $pm['goals_against']); ?> against</span>
			<span class="pm3-stats__sub">Ratio <?php echo $pm['goal_ratio'] !== null ? $pm['goal_ratio'] : '—'; ?></span>
		</div>
		<div class="pm3-stats__cell">
			<span class="pm3-stats__label">Avg opponent rating</span>
			<span class="pm3-stats__value"><?php echo $pm['average_opponent_rating'] !== null ? (int) $pm['average_opponent_rating'] : '—'; ?></span>
		</div>
		<div class="pm3-stats__cell">
			<span class="pm3-stats__label">Win streak · DDs · clean sheets</span>
			<span class="pm3-stats__value"><?php echo (int) $pm['winning_streak']; ?> · <?php echo (int) $pm['double_digits']; ?> · <?php echo (int) $pm['clean_sheets']; ?></span>
		</div>
	</div>
	<p class="pm3-stats__foot">
		<a href="individual3.php?id=<?php echo (int) $pm['id']; ?>">Full game list</a>
		· <a href="individual2a.php?id=<?php echo (int) $pm['id']; ?>">Wins by opponent</a>
	</p>
</section>
    <?php
}
