<?php
/**
 * Reusable Profile pass 2 blocks. Requires $pm, $id, profile_mock_helpers.
 */

function pm2_render_core(array $pm): void
{
    ?>
<header class="pm2-core" aria-label="Player identity">
	<h2 class="pm2-core__name"><?php echo pm_h($pm['name']); ?></h2>
	<dl class="pm2-core__stats">
		<div class="pm2-core__stat">
			<dt>Rank</dt>
			<dd><?php echo $pm['display'] ? '#' . (int) $pm['rank'] : '—'; ?></dd>
		</div>
		<div class="pm2-core__stat pm2-core__stat--focus">
			<dt>Rating</dt>
			<dd><?php echo $pm['rating'] !== null ? (int) $pm['rating'] : '—'; ?></dd>
		</div>
		<div class="pm2-core__stat">
			<dt>Peak</dt>
			<dd><?php echo $pm['peak'] !== null ? (int) $pm['peak'] : '—'; ?></dd>
		</div>
		<div class="pm2-core__stat">
			<dt>Games</dt>
			<dd><?php echo number_format((int) $pm['games']); ?></dd>
		</div>
	</dl>
</header>
    <?php
}

function pm2_render_activity(array $pm): void
{
    ?>
<div class="pm2-activity" aria-label="Activity signals">
	<div class="pm2-activity__item">
		<span class="pm2-activity__label">Last seen online</span>
		<span class="pm2-activity__value"><?php echo pm_h($pm['last_login']); ?></span>
	</div>
	<div class="pm2-activity__item">
		<span class="pm2-activity__label">Last rated match</span>
		<span class="pm2-activity__value"><?php echo pm_h($pm['last_game']); ?></span>
	</div>
	<div class="pm2-activity__item">
		<span class="pm2-activity__label">Games this month</span>
		<span class="pm2-activity__value"><?php echo (int) $pm['games_this_month']; ?></span>
	</div>
</div>
    <?php
}

function pm2_render_participation(array $pm): void
{
    ?>
<section class="pm2-participation">
	<p class="pm2-participation__tenure">
		<span class="pm2-participation__badge"><?php echo pm_h($pm['tenure_label']); ?></span>
		on the online ladder ·
		<strong><?php echo number_format((int) $pm['games']); ?></strong> rated games ·
		<strong><?php echo number_format((int) $pm['different_opponents']); ?></strong> opponents ·
		first rated <?php echo pm_h($pm['first_game_date']); ?>
	</p>
</section>
    <?php
}

function pm2_render_moments(array $pm): void
{
    ?>
<section class="pm2-section">
	<h3 class="pm2-section__title">Moments</h3>
	<p class="pm2-section__hint">Career highlights — the games people remember.</p>
	<div class="pm2-moments">
		<article class="pm2-moment pm2-moment--streak">
			<span class="pm2-moment__glyph" aria-hidden="true">🏆</span>
			<span class="pm2-moment__tag">Streak</span>
			<h4 class="pm2-moment__label">Longest win run</h4>
			<p class="pm2-moment__score"><?php echo (int) $pm['longest_win_streak']; ?> wins</p>
			<p class="pm2-moment__meta">Across thousands of rated matches</p>
		</article>
		<?php foreach ($pm['trophies'] as $t) { ?>
		<article class="pm2-moment">
			<span class="pm2-moment__glyph" aria-hidden="true"><?php echo $t['icon']; ?></span>
			<span class="pm2-moment__tag"><?php echo pm_h($t['tag']); ?></span>
			<h4 class="pm2-moment__label"><?php echo pm_h($t['label']); ?></h4>
			<p class="pm2-moment__score">
				<a href="game.php?id=<?php echo (int) $t['game_id']; ?>"><?php echo pm_h($t['score']); ?></a>
			</p>
			<p class="pm2-moment__meta">
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

function pm2_render_busiest(array $pm): void
{
    $bm = $pm['busiest']['month'] ?? null;
    $bd = $pm['busiest']['day'] ?? null;
    $by = $pm['busiest']['year'] ?? null;
    ?>
<section class="pm2-section pm2-busiest">
	<h3 class="pm2-section__title">Peak activity</h3>
	<div class="pm2-busiest__grid">
		<div class="pm2-busiest__tile">
			<strong><?php echo $bm ? (int) $bm['count'] : '—'; ?></strong>
			<span>games in busiest month</span>
			<em><?php echo $bm ? pm_h(pm2_format_busiest_month((string) $bm['key'])) : ''; ?></em>
		</div>
		<div class="pm2-busiest__tile">
			<strong><?php echo $bd ? (int) $bd['count'] : '—'; ?></strong>
			<span>games on busiest day</span>
			<em><?php echo $bd ? pm_h(pm2_format_busiest_day((string) $bd['key'])) : ''; ?></em>
		</div>
		<div class="pm2-busiest__tile">
			<strong><?php echo $by ? (int) $by['count'] : '—'; ?></strong>
			<span>games in busiest year</span>
			<em><?php echo $by ? pm_h((string) $by['key']) : ''; ?></em>
		</div>
	</div>
</section>
    <?php
}

function pm2_render_rivalry(array $pm): void
{
    $r = $pm['featured_rival'] ?? null;
    if ($r === null) {
        return;
    }
    $h = $pm['rival_h2h'];
    ?>
<section class="pm2-section pm2-rivalry-intro">
	<h3 class="pm2-section__title">Featured rivalry</h3>
	<p class="pm2-rivalry-intro__lead">
		<a class="pm2-rivalry-intro__name" href="individual1.php?id=<?php echo (int) $r['id']; ?>"><?php echo pm_h($r['name']); ?></a>
		— <strong><?php echo number_format((int) $r['games']); ?></strong> rated games together (all-time #1).
	</p>
	<p class="pm2-rivalry-intro__record">
		<span class="pm-outcome--win"><?php echo (int) $h['wins']; ?>W</span>
		<span class="pm-outcome--draw"><?php echo (int) $h['draws']; ?>D</span>
		<span class="pm-outcome--loss"><?php echo (int) $h['losses']; ?>L</span>
		<span class="pm2-rivalry-intro__hint">Head-to-head record · charts below default to this opponent</span>
	</p>
</section>
    <?php
}

function pm2_render_charts_rivalry(int $playerId): void
{
    ?>
<section class="pm2-section pm2-charts pm2-charts--rivals">
	<h3 class="pm2-section__title">Rivals &amp; rematches</h3>
	<div class="player-top-opponents-chart" data-player-id="<?php echo $playerId; ?>">
		<p class="pm2-chart__label">Most played opponents</p>
		<p class="player-top-opponents-chart-status pm2-chart__status">Loading…</p>
		<canvas height="280" aria-label="Most played opponents"></canvas>
	</div>
	<div class="player-head-to-head-chart" data-player-id="<?php echo $playerId; ?>">
		<p class="pm2-chart__label">Head-to-head vs <span class="player-head-to-head-opponent-name">…</span></p>
		<p class="player-head-to-head-meta pm2-chart__meta"></p>
		<p class="player-head-to-head-chart-status pm2-chart__status">Loading…</p>
		<canvas height="220" aria-label="Head-to-head wins"></canvas>
	</div>
	<div class="player-compare-rating-chart" data-player-id="<?php echo $playerId; ?>">
		<p class="pm2-chart__label">Rating paths vs <span class="player-compare-rating-opponent-name">…</span></p>
		<p class="player-compare-rating-meta pm2-chart__meta"></p>
		<p class="player-compare-rating-chart-status pm2-chart__status">Loading…</p>
		<canvas height="220" aria-label="Compare rating"></canvas>
	</div>
	<div class="player-h2h-opponent-search player-search pm2-h2h-search" data-player-id="<?php echo $playerId; ?>" data-realm="online" role="search">
		<label class="player-search-label" for="player-h2h-search-q-<?php echo $playerId; ?>">Compare with another player</label>
		<input id="player-h2h-search-q-<?php echo $playerId; ?>" class="player-search-input player-h2h-search-input" type="search" maxlength="32" autocomplete="off" spellcheck="false" placeholder="Search player name…" />
		<ul class="player-search-results player-h2h-search-results" role="listbox" hidden></ul>
	</div>
</section>
    <?php
}

function pm2_render_charts_primary(int $playerId): void
{
    ?>
<section class="pm2-section pm2-charts">
	<h3 class="pm2-section__title">Career charts</h3>
	<div class="pm2-charts__grid pm2-charts__grid--2">
		<div class="pm2-chart-panel player-rating-chart" data-player-id="<?php echo $playerId; ?>">
			<p class="pm2-chart__label">Rating over time</p>
			<p class="player-rating-chart-status pm2-chart__status">Loading…</p>
			<p class="player-rating-peak-current-summary pm2-chart__summary" style="display:none;"></p>
			<canvas height="240" aria-label="Rating history"></canvas>
		</div>
		<div class="pm2-chart-panel player-games-month-chart" data-player-id="<?php echo $playerId; ?>">
			<p class="pm2-chart__label">Games per month</p>
			<p class="player-games-month-chart-status pm2-chart__status">Loading…</p>
			<canvas height="240" aria-label="Games per month"></canvas>
		</div>
	</div>
</section>
    <?php
}

function pm2_render_charts_secondary(int $playerId): void
{
    ?>
<section class="pm2-section pm2-charts">
	<h3 class="pm2-section__title">Deeper analytics</h3>
	<div class="pm2-charts__grid pm2-charts__grid--2">
		<div class="pm2-chart-panel player-rating-game-chart" data-player-id="<?php echo $playerId; ?>">
			<p class="pm2-chart__label">Rating by game number</p>
			<p class="player-rating-game-chart-status pm2-chart__status">Loading…</p>
			<canvas height="200" aria-label="Rating by game number"></canvas>
		</div>
		<div class="pm2-chart-panel player-winrate-opponent-chart" data-player-id="<?php echo $playerId; ?>">
			<p class="pm2-chart__label">Win rate vs opponent rating</p>
			<p class="player-winrate-opponent-chart-status pm2-chart__status">Loading…</p>
			<canvas height="200" aria-label="Win rate buckets"></canvas>
		</div>
	</div>
</section>
    <?php
}

function pm2_render_heatmap(int $playerId): void
{
    ?>
<section class="pm2-section pm2-heat" data-player-id="<?php echo $playerId; ?>" aria-label="Activity heatmap">
	<h3 class="pm2-section__title">Activity map</h3>
	<p class="pm2-section__hint">Rated games per week — last ~2 years. Darker = busier.</p>
	<p class="pm2-heat__status pm2-chart__status">Loading weekly activity…</p>
	<div class="pm2-heat__grid" role="img" aria-label="Weekly activity heatmap"></div>
	<div class="pm2-heat__legend">
		<span>Less</span>
		<span class="pm2-heat__cell pm2-heat__cell--0"></span>
		<span class="pm2-heat__cell pm2-heat__cell--1"></span>
		<span class="pm2-heat__cell pm2-heat__cell--2"></span>
		<span class="pm2-heat__cell pm2-heat__cell--3"></span>
		<span class="pm2-heat__cell pm2-heat__cell--4"></span>
		<span>More</span>
	</div>
</section>
    <?php
}

function pm2_render_stats_compact(array $pm): void
{
    ?>
<section class="pm2-section pm2-stats">
	<h3 class="pm2-section__title">Career numbers</h3>
	<div class="pm2-stats__grid">
		<div class="pm2-stats__cell">
			<span class="pm2-stats__label">Record</span>
			<span class="pm2-stats__value"><?php echo number_format((int) $pm['wins']); ?>W · <?php echo number_format((int) $pm['draws']); ?>D · <?php echo number_format((int) $pm['losses']); ?>L</span>
			<span class="pm2-stats__sub"><?php echo $pm['win_pct']; ?>% wins</span>
		</div>
		<div class="pm2-stats__cell">
			<span class="pm2-stats__label">Goals</span>
			<span class="pm2-stats__value"><?php echo number_format((int) $pm['goals_for']); ?> for · <?php echo number_format((int) $pm['goals_against']); ?> against</span>
			<span class="pm2-stats__sub">Ratio <?php echo $pm['goal_ratio'] !== null ? $pm['goal_ratio'] : '—'; ?></span>
		</div>
		<div class="pm2-stats__cell">
			<span class="pm2-stats__label">Opponents faced</span>
			<span class="pm2-stats__value"><?php echo number_format((int) $pm['different_opponents']); ?></span>
		</div>
		<div class="pm2-stats__cell">
			<span class="pm2-stats__label">Avg opponent rating</span>
			<span class="pm2-stats__value"><?php echo $pm['average_opponent_rating'] !== null ? (int) $pm['average_opponent_rating'] : '—'; ?></span>
		</div>
		<div class="pm2-stats__cell">
			<span class="pm2-stats__label">Current win streak</span>
			<span class="pm2-stats__value"><?php echo (int) $pm['winning_streak']; ?></span>
		</div>
		<div class="pm2-stats__cell">
			<span class="pm2-stats__label">Double digits · clean sheets</span>
			<span class="pm2-stats__value"><?php echo (int) $pm['double_digits']; ?> · <?php echo (int) $pm['clean_sheets']; ?></span>
		</div>
	</div>
	<p class="pm2-stats__foot">
		<a href="individual3.php?id=<?php echo (int) $pm['id']; ?>">Full game list</a>
		· <a href="individual2a.php?id=<?php echo (int) $pm['id']; ?>">Wins by opponent</a>
		· <a href="individual2b.php?id=<?php echo (int) $pm['id']; ?>">Goals</a>
		· <a href="individual2c.php?id=<?php echo (int) $pm['id']; ?>">DDs</a>
	</p>
</section>
    <?php
}

function pm2_render_nav_portal(string $variant, string $title, int $playerId): void
{
    $v = pm_h($variant);
    ?>
<nav class="pm2-portal-nav" aria-label="Pass 2 mock switcher">
	<a href="profile_mocks.php?id=<?php echo $playerId; ?>">Lab portal</a>
	<a href="individual1.php?id=<?php echo $playerId; ?>">Production</a>
	<a href="profile_mock_a.php?id=<?php echo $playerId; ?>"<?php echo $variant === 'a' ? ' class="is-current"' : ''; ?>>Chronicle</a>
	<a href="profile_mock_b.php?id=<?php echo $playerId; ?>"<?php echo $variant === 'b' ? ' class="is-current"' : ''; ?>>Arena</a>
	<a href="profile_mock_c.php?id=<?php echo $playerId; ?>"<?php echo $variant === 'c' ? ' class="is-current"' : ''; ?>>Atlas</a>
	<span class="pm2-portal-nav__tag">Pass 2 · <?php echo pm_h($title); ?></span>
</nav>
    <?php
}
