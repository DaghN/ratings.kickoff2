<?php
/**
 * Profile mock 3D — panel feast (composite of pass 3 picks).
 */

function pm3d_section_open(string $title, ?string $hint = null): void
{
    ?>
<section class="pm3d-section">
	<?php if ($title !== '') { ?>
	<h2 class="pm3d-section__title"><?php echo pm_h($title); ?></h2>
	<?php } ?>
	<?php if ($hint !== null && $hint !== '') { ?>
	<p class="pm3d-section__hint"><?php echo pm_h($hint); ?></p>
	<?php } ?>
	<div class="pm3d-section__content">
    <?php
}

function pm3d_section_close(): void
{
    ?>
	</div>
</section>
    <?php
}

function pm3d_render_core(array $pm): void
{
    $display = (int) $pm['display'] === 1;
    $heroRating = $display && $pm['rating'] !== null ? (int) $pm['rating'] : '—';
    $heroPeak = $display && $pm['peak'] !== null ? (int) $pm['peak'] : '—';
    $heroGames = (int) $pm['games'];
    $heroRank = $display ? '#' . (int) $pm['rank'] : '—';
    ?>
<header class="pm3d-core" aria-label="Player identity">
	<div class="pm3d-core__inner">
		<h2 class="k2-player-hero__name"><?php echo pm_h($pm['name']); ?></h2>
		<div class="k2-player-hero__stats">
			<div class="k2-player-hero__stat">
				<span class="k2-player-hero__stat-label">Rank</span>
				<span class="k2-player-hero__stat-value pm3d-core__stat-value--rank"><?php echo pm_h((string) $heroRank); ?></span>
			</div>
			<div class="k2-player-hero__stat">
				<span class="k2-player-hero__stat-label">Rating</span>
				<span class="k2-player-hero__stat-value k2-player-hero__stat-value--accent"><?php echo $heroRating; ?></span>
			</div>
			<div class="k2-player-hero__stat">
				<span class="k2-player-hero__stat-label">Peak</span>
				<span class="k2-player-hero__stat-value"><?php echo $heroPeak; ?></span>
			</div>
			<div class="k2-player-hero__stat">
				<span class="k2-player-hero__stat-label">Games</span>
				<span class="k2-player-hero__stat-value"><?php echo number_format($heroGames); ?></span>
			</div>
		</div>
	</div>
</header>
    <?php
}

/**
 * @return array{rival: array, h2h: array, you: string}|null
 */
function pm3d_featured_rival_bundle(array $pm): ?array
{
    $r = $pm['featured_rival'] ?? null;
    if ($r === null) {
        return null;
    }

    return [
        'rival' => $r,
        'h2h' => $pm['rival_h2h'],
        'you' => (string) $pm['name'],
    ];
}

/**
 * Featured rivalry / #1 opponent. Variants: duel (3G default), h cards, i spotlight, j chart bridge.
 *
 * @param 'duel'|'h'|'i'|'j' $variant
 */
function pm3d_render_best_friend(array $pm, string $variant = 'duel'): void
{
    $bundle = pm3d_featured_rival_bundle($pm);
    if ($bundle === null) {
        return;
    }

    $variant = strtolower($variant);
    $r = $bundle['rival'];
    $h = $bundle['h2h'];
    $rid = (int) $r['id'];
    $rname = pm_h($r['name']);
    $games = number_format((int) $r['games']);

    if ($variant === 'j') {
        pm3d_section_open('', 'Head-to-head charts below default to this opponent.');
        ?>
<div class="pm3hij-rival pm3hij-rival--bridge" aria-label="Featured rivalry">
	<span class="pm3hij-rival__bridge-label">Primary opponent</span>
	<a class="pm3hij-rival__bridge-name" href="individual1.php?id=<?php echo $rid; ?>"><?php echo $rname; ?></a>
	<span class="pm3hij-rival__bridge-meta"><?php echo $games; ?> rated games ·</span>
	<span class="pm3hij-rival__bridge-wdl">
		<span class="pm-outcome--win"><?php echo (int) $h['wins']; ?>W</span>
		<span class="pm-outcome--draw"><?php echo (int) $h['draws']; ?>D</span>
		<span class="pm-outcome--loss"><?php echo (int) $h['losses']; ?>L</span>
	</span>
</div>
        <?php
        pm3d_section_close();

        return;
    }

    if ($variant === 'h') {
        pm3d_section_open('Most played opponent', 'All-time #1 by rated games — your head-to-head record.');
        ?>
<div class="pm3hij-rival pm3hij-rival--cards" aria-label="Featured rivalry">
	<div class="pm3hij-rival__card">
		<span class="pm3hij-rival__card-label">Rated games</span>
		<strong class="pm3hij-rival__card-value"><?php echo $games; ?></strong>
	</div>
	<div class="pm3hij-rival__card">
		<span class="pm3hij-rival__card-label">Your record</span>
		<p class="pm3hij-rival__card-wdl">
			<span class="pm-outcome--win"><?php echo (int) $h['wins']; ?>W</span>
			<span class="pm-outcome--draw"><?php echo (int) $h['draws']; ?>D</span>
			<span class="pm-outcome--loss"><?php echo (int) $h['losses']; ?>L</span>
		</p>
	</div>
	<div class="pm3hij-rival__card">
		<span class="pm3hij-rival__card-label">Opponent</span>
		<a class="pm3hij-rival__card-name" href="individual1.php?id=<?php echo $rid; ?>"><?php echo $rname; ?></a>
	</div>
</div>
        <?php
        pm3d_section_close();

        return;
    }

    if ($variant === 'i') {
        pm3d_section_open('Most played opponent', 'The opponent you have faced the most in rated matches.');
        ?>
<div class="pm3hij-rival pm3hij-rival--spotlight" aria-label="Featured rivalry">
	<article class="pm3hij-rival__hero">
		<p class="pm3hij-rival__hero-label">All-time #1 opponent</p>
		<p class="pm3hij-rival__hero-name"><a href="individual1.php?id=<?php echo $rid; ?>"><?php echo $rname; ?></a></p>
		<p class="pm3hij-rival__hero-count"><?php echo $games; ?></p>
		<p class="pm3hij-rival__hero-unit">rated games together</p>
	</article>
	<div class="pm3hij-rival__aside">
		<div class="pm3hij-rival__mini">
			<span class="pm3hij-rival__mini-label">Your record</span>
			<p class="pm3hij-rival__mini-wdl">
				<span class="pm-outcome--win"><?php echo (int) $h['wins']; ?>W</span>
				<span class="pm-outcome--draw"><?php echo (int) $h['draws']; ?>D</span>
				<span class="pm-outcome--loss"><?php echo (int) $h['losses']; ?>L</span>
			</p>
		</div>
		<div class="pm3hij-rival__mini">
			<span class="pm3hij-rival__mini-label">Charts below</span>
			<p class="pm3hij-rival__mini-note">H2H and compare default to this player.</p>
		</div>
	</div>
</div>
        <?php
        pm3d_section_close();

        return;
    }

    pm3d_section_open('Best friend');
    ?>
<div class="pm3-duel" aria-label="Featured rivalry">
	<div class="pm3-duel__corner pm3-duel__corner--you">
		<span class="pm3-duel__role">You</span>
		<span class="pm3-duel__name"><?php echo pm_h($bundle['you']); ?></span>
	</div>
	<div class="pm3-duel__center">
		<p class="pm3-duel__vs">vs</p>
		<p class="pm3-duel__count"><strong><?php echo $games; ?></strong> rated games</p>
		<p class="pm3-duel__record">
			<span class="pm-outcome--win"><?php echo (int) $h['wins']; ?>W</span>
			<span class="pm-outcome--draw"><?php echo (int) $h['draws']; ?>D</span>
			<span class="pm-outcome--loss"><?php echo (int) $h['losses']; ?>L</span>
		</p>
		<p class="pm3-duel__tag">All-time #1 opponent</p>
	</div>
	<div class="pm3-duel__corner pm3-duel__corner--rival">
		<span class="pm3-duel__role">Rival</span>
		<a class="pm3-duel__name" href="individual1.php?id=<?php echo $rid; ?>"><?php echo $rname; ?></a>
	</div>
</div>
    <?php
    pm3d_section_close();
}

function pm3d_render_played_days(int $playerId, int $year): void
{
    pm3d_section_open('Played days');
    ?>
<div class="pm3-cal pm3-cal--hero" data-player-id="<?php echo $playerId; ?>" data-year="<?php echo $year; ?>" aria-label="Calendar activity">
	<p class="pm3-cal__status pm3-muted">Loading calendar…</p>
	<div class="pm3-cal__year"></div>
	<p class="pm3-cal__legend"><span class="pm3-cal__cell pm3-cal__cell--play"></span> played · empty = no rated game</p>
</div>
    <?php
    pm3d_section_close();
}

/**
 * @return array{day: ?array, month: ?array, year: ?array}
 */
function pm3d_peak_busiest(array $pm): array
{
    return [
        'day' => $pm['busiest']['day'] ?? null,
        'month' => $pm['busiest']['month'] ?? null,
        'year' => $pm['busiest']['year'] ?? null,
    ];
}

/**
 * Peak / busiest periods (N11). Variants: stack (3G default), h cards, i spotlight, j = use rhythm band.
 *
 * @param 'stack'|'h'|'i' $variant
 */
function pm3d_render_peak_activity(array $pm, string $variant = 'stack'): void
{
    $variant = strtolower($variant);
    if ($variant === 'j') {
        return;
    }

    $b = pm3d_peak_busiest($pm);
    $bd = $b['day'];
    $bm = $b['month'];
    $by = $b['year'];

    if ($variant === 'h') {
        pm3d_section_open('Personal bests', 'Most rated games in a single day, month, and calendar year.');
        ?>
<div class="pm3-busiest pm3-busiest--inline pm3hij-peak pm3hij-peak--cards">
	<ol class="pm3-busiest__list">
		<li>
			<span class="pm3-busiest__kind">Best day</span>
			<strong><?php echo $bd ? (int) $bd['count'] : '—'; ?></strong>
			<em><?php echo $bd ? pm_h(pm2_format_busiest_day((string) $bd['key'])) : ''; ?></em>
		</li>
		<li>
			<span class="pm3-busiest__kind">Best month</span>
			<strong><?php echo $bm ? (int) $bm['count'] : '—'; ?></strong>
			<em><?php echo $bm ? pm_h(pm2_format_busiest_month((string) $bm['key'])) : ''; ?></em>
		</li>
		<li>
			<span class="pm3-busiest__kind">Best year</span>
			<strong><?php echo $by ? (int) $by['count'] : '—'; ?></strong>
			<em><?php echo $by ? pm_h((string) $by['key']) : ''; ?></em>
		</li>
	</ol>
</div>
        <?php
        pm3d_section_close();

        return;
    }

    if ($variant === 'i') {
        pm3d_section_open('Peak burst', 'The day with the most rated games — month and year for context.');
        ?>
<div class="pm3hij-peak pm3hij-peak--spotlight">
	<article class="pm3hij-peak__hero" aria-label="Busiest day">
		<p class="pm3hij-peak__hero-label">Busiest day</p>
		<p class="pm3hij-peak__hero-count"><?php echo $bd ? (int) $bd['count'] : '—'; ?></p>
		<p class="pm3hij-peak__hero-unit">rated games</p>
		<p class="pm3hij-peak__hero-when"><?php echo $bd ? pm_h(pm2_format_busiest_day((string) $bd['key'])) : '—'; ?></p>
	</article>
	<div class="pm3hij-peak__aside">
		<div class="pm3hij-peak__mini">
			<span class="pm3hij-peak__mini-label">Busiest month</span>
			<strong><?php echo $bm ? (int) $bm['count'] . ' games' : '—'; ?></strong>
			<span class="pm3hij-peak__mini-when"><?php echo $bm ? pm_h(pm2_format_busiest_month((string) $bm['key'])) : ''; ?></span>
		</div>
		<div class="pm3hij-peak__mini">
			<span class="pm3hij-peak__mini-label">Busiest year</span>
			<strong><?php echo $by ? (int) $by['count'] . ' games' : '—'; ?></strong>
			<span class="pm3hij-peak__mini-when"><?php echo $by ? pm_h((string) $by['key']) : ''; ?></span>
		</div>
	</div>
</div>
        <?php
        pm3d_section_close();

        return;
    }

    pm3d_section_open('Peak activity');
    ?>
<div class="pm3-busiest pm3-busiest--stack">
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
</div>
    <?php
    pm3d_section_close();
}

/** 3J — calendar + busiest chips in one “rhythm” section. */
function pm3d_render_played_days_rhythm(int $playerId, int $year, array $pm): void
{
    $b = pm3d_peak_busiest($pm);
    $bd = $b['day'];
    $bm = $b['month'];
    $by = $b['year'];

    pm3d_section_open('Played days', 'Calendar shows when you played; chips are your busiest day, month, and year.');
    ?>
<div class="pm3j-rhythm">
	<div class="pm3-cal pm3-cal--hero pm3j-rhythm__cal" data-player-id="<?php echo $playerId; ?>" data-year="<?php echo $year; ?>" aria-label="Calendar activity">
		<p class="pm3-cal__status pm3-muted">Loading calendar…</p>
		<div class="pm3-cal__year"></div>
		<p class="pm3-cal__legend"><span class="pm3-cal__cell pm3-cal__cell--play"></span> played · empty = no rated game</p>
	</div>
	<div class="pm3j-rhythm__peaks" aria-label="Busiest periods">
		<div class="pm3j-rhythm__chip">
			<span class="pm3j-rhythm__chip-label">Best day</span>
			<strong class="pm3j-rhythm__chip-value"><?php echo $bd ? (int) $bd['count'] : '—'; ?></strong>
			<span class="pm3j-rhythm__chip-meta"><?php echo $bd ? pm_h(pm2_format_busiest_day((string) $bd['key'])) : '—'; ?></span>
		</div>
		<div class="pm3j-rhythm__chip">
			<span class="pm3j-rhythm__chip-label">Best month</span>
			<strong class="pm3j-rhythm__chip-value"><?php echo $bm ? (int) $bm['count'] : '—'; ?></strong>
			<span class="pm3j-rhythm__chip-meta"><?php echo $bm ? pm_h(pm2_format_busiest_month((string) $bm['key'])) : '—'; ?></span>
		</div>
		<div class="pm3j-rhythm__chip">
			<span class="pm3j-rhythm__chip-label">Best year</span>
			<strong class="pm3j-rhythm__chip-value"><?php echo $by ? (int) $by['count'] : '—'; ?></strong>
			<span class="pm3j-rhythm__chip-meta"><?php echo $by ? pm_h((string) $by['key']) : '—'; ?></span>
		</div>
	</div>
</div>
    <?php
    pm3d_section_close();
}

function pm3d_render_moments(array $pm): void
{
    pm3d_section_open('Moments');
    ?>
<div class="pm3-moments pm3-moments--mosaic">
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
</div>
    <?php
    pm3d_section_close();
}

function pm3d_render_stat_value(string $value): void
{
    echo pm_h($value);
}

/**
 * @return array<int, array{label: string, value: string, rank: ?int}>
 */
function pm3d_presence_stat_rows(array $pm): array
{
    return [
        ['label' => 'Last seen online', 'value' => (string) $pm['last_login'], 'rank' => null],
        ['label' => 'Last rated match', 'value' => (string) $pm['last_game'], 'rank' => null],
        ['label' => 'Games this month', 'value' => (string) (int) $pm['games_this_month'], 'rank' => null],
        ['label' => 'Games this year', 'value' => (string) (int) $pm['games_this_year'], 'rank' => null],
        ['label' => 'First rated game', 'value' => (string) $pm['first_game_date'], 'rank' => null],
    ];
}

/**
 * @return array<int, array{label: string, value: string, rank: ?int}>
 */
function pm3d_career_stat_rows(array $pm): array
{
    return [
        ['label' => 'Rated games', 'value' => number_format((int) $pm['games']), 'rank' => $pm['career_rank_games'] ?? null],
        ['label' => 'Wins', 'value' => number_format((int) $pm['wins']), 'rank' => $pm['career_rank_wins'] ?? null],
        ['label' => 'Goals scored', 'value' => number_format((int) $pm['goals_for']), 'rank' => $pm['career_rank_goals'] ?? null],
        ['label' => 'Double digits', 'value' => number_format((int) $pm['double_digits']), 'rank' => $pm['career_rank_double_digits'] ?? null],
        ['label' => 'Opponents faced', 'value' => number_format((int) $pm['different_opponents']), 'rank' => $pm['career_rank_opponents'] ?? null],
    ];
}

/**
 * @param array<int, array{label: string, value: string, rank?: ?int}> $rows
 */
function pm3d_render_stat_table_rows(array $rows, bool $headlineFirst = false): void
{
    ?>
<table class="pm3efg-stat-table">
	<tbody>
	<?php foreach ($rows as $i => $row) {
	    $rowClass = $headlineFirst && $i === 1 ? ' pm3efg-stat-table__row--headline' : '';
	    ?>
		<tr class="pm3efg-stat-table__row<?php echo $rowClass; ?>">
			<th scope="row"><?php echo pm_h($row['label']); ?></th>
			<td><?php pm3d_render_stat_value($row['value']); ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
    <?php
}

/**
 * Career stats: label | number | (#rank) in a real column so ranks stack vertically.
 *
 * @param array<int, array{label: string, value: string, rank?: ?int}> $rows
 */
function pm3d_render_career_stats_table(array $rows): void
{
    ?>
<table class="pm3efg-stat-table pm3efg-stat-table--career">
	<tbody>
	<?php foreach ($rows as $row) {
	    $rank = $row['rank'] ?? null;
	    ?>
		<tr class="pm3efg-stat-table__row">
			<th scope="row"><?php echo pm_h($row['label']); ?></th>
			<td class="pm3efg-career-stats__num"><?php pm3d_render_stat_value($row['value']); ?></td>
			<td class="pm3efg-career-stats__rank"><?php
	        if ($rank !== null && $rank > 0) {
	            echo '(#' . (int) $rank . ')';
	        }
	    ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
    <?php
}

/**
 * @param array<int, array{label: string, value: string, rank?: ?int}> $rows
 */
function pm3d_render_stat_tiles(array $rows): void
{
    ?>
<div class="pm3d-career__grid pm3efg-tiles">
	<?php foreach ($rows as $row) {
	    ?>
	<div class="pm3d-career__tile">
		<span class="pm3d-career__label"><?php echo pm_h($row['label']); ?></span>
		<span class="pm3d-career__value"><?php pm3d_render_stat_value($row['value']); ?></span>
	</div>
	    <?php
	} ?>
</div>
    <?php
}

/** 3G — Presence + Career side by side (at-a-glance band). */
function pm3d_render_presence_career_duo(array $pm): void
{
    pm3d_section_open('');
    ?>
<div class="pm3efg-duo pm3efg-duo--g">
	<div class="pm3efg-duo__panel pm3efg-duo__panel--presence">
		<h3 class="pm3efg-duo__panel-title">Presence</h3>
		<?php pm3d_render_stat_table_rows(pm3d_presence_stat_rows($pm), true); ?>
	</div>
	<div class="pm3efg-duo__panel pm3efg-duo__panel--career">
		<h3 class="pm3efg-duo__panel-title">Career</h3>
		<?php pm3d_render_career_stats_table(pm3d_career_stat_rows($pm)); ?>
	</div>
</div>
    <?php
    pm3d_section_close();
}

function pm3d_render_presence(array $pm): void
{
    pm3d_section_open('Presence');
    pm3d_render_stat_tiles(pm3d_presence_stat_rows($pm));
    pm3d_section_close();
}

function pm3d_render_career(array $pm): void
{
    pm3d_section_open('Career');
    pm3d_render_career_stats_table(pm3d_career_stat_rows($pm));
    pm3d_section_close();
}

function pm3d_render_charts(int $playerId): void
{
    $uid = 'pm3d-' . $playerId;

    pm3d_section_open('Rating over time');
    ?>
<div class="player-rating-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="player-rating-chart-status pm3d-chart__status">Loading rating history…</p>
	<p class="player-rating-peak-current-summary pm3d-chart__summary" style="display:none;"></p>
	<canvas width="960" height="345" aria-label="ELO rating over time"></canvas>
</div>
    <?php
    pm3d_section_close();

    pm3d_section_open('Games per month');
    ?>
<div class="player-games-month-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="player-games-month-chart-status pm3d-chart__status">Loading games per month…</p>
	<canvas width="960" height="271" aria-label="Games per calendar month"></canvas>
</div>
    <?php
    pm3d_section_close();

    pm3d_section_open('Rating by game number', 'ELO after each rated game — equal spacing, not calendar time.');
    ?>
<div class="player-rating-game-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="player-rating-game-chart-status pm3d-chart__status">Loading…</p>
	<canvas width="960" height="345" aria-label="Rating by game number"></canvas>
</div>
    <?php
    pm3d_section_close();

    pm3d_section_open('Most played opponents', 'Top 20 by rated games — click a bar for head-to-head below.');
    ?>
<div class="player-top-opponents-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="player-top-opponents-chart-status pm3d-chart__status">Loading…</p>
	<canvas width="960" height="591" aria-label="Most played opponents"></canvas>
</div>
    <?php
    pm3d_section_close();

    pm3d_section_open('Head-to-head', 'Cumulative wins vs selected opponent.');
    ?>
<div class="player-head-to-head-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="pm3d-chart__opponent">vs <span class="player-head-to-head-opponent-name">…</span></p>
	<p class="player-head-to-head-meta pm3d-chart__meta"></p>
	<p class="player-head-to-head-chart-status pm3d-chart__status">Waiting for opponent…</p>
	<canvas width="960" height="345" aria-label="Head-to-head cumulative wins"></canvas>
</div>
    <?php
    pm3d_section_close();

    pm3d_section_open('Rating comparison', 'Rating paths vs selected opponent.');
    ?>
<div class="player-compare-rating-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="pm3d-chart__opponent">vs <span class="player-compare-rating-opponent-name">…</span></p>
	<p class="player-compare-rating-meta pm3d-chart__meta"></p>
	<p class="player-compare-rating-chart-status pm3d-chart__status">Waiting for opponent…</p>
	<canvas width="960" height="345" aria-label="Rating comparison"></canvas>
</div>
    <?php
    pm3d_section_close();

    pm3d_section_open('Compare with another player');
    ?>
<div class="player-h2h-opponent-search player-search pm3d-h2h-search" data-player-id="<?php echo $playerId; ?>" data-realm="online" role="search">
	<label class="player-search-label" for="<?php echo pm_h($uid); ?>-h2h">Search player name</label>
	<input id="<?php echo pm_h($uid); ?>-h2h" class="player-search-input player-h2h-search-input" type="search" maxlength="32" autocomplete="off" spellcheck="false" placeholder="Search player name…" />
	<ul class="player-search-results player-h2h-search-results" role="listbox" hidden></ul>
</div>
    <?php
    pm3d_section_close();
}
