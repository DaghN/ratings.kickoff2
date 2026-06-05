<?php
/**
 * Player profile feast blocks — LAB 2 fork (individual1-profile-lab2.php only).
 *
 * Agent 2 brief: Chronicle tone — celebration and memory before analyst charts.
 * This file is a self-contained fork: it re-declares the shipped render helpers
 * (so the lab page only requires this one blocks file) and adds the v1 modules.
 *
 * Scroll spine (Chronicle-first — B4 Memory deliberately ABOVE B5 Texture):
 *   Hero → pills
 *   B1/B2  Presence + Career duo  →  story lines (win/play streak, victims, best year, distinct days)
 *   B3     Honours (milestones + league)
 *   B4     Personal bests → Moments (+ M03 max-rated victim, M08 favourite victim)
 *   B5     Played days → Played weeks
 *   C1/C2  Rivalry line (M09) → charts → opponent search
 *
 * Production player/profile.php / player_feast_blocks.php are untouched.
 */

/* ------------------------------------------------------------------ *
 * Shipped helpers (forked verbatim so this file stands alone)
 * ------------------------------------------------------------------ */

function player_feast_section_open(string $title, ?string $hint = null): void
{
    ?>
<section class="pm3d-section">
	<?php if ($title !== '') { ?>
	<h2 class="k2-panel-heading pm3d-section__title"><?php echo pm_h($title); ?></h2>
	<?php } ?>
	<?php if ($hint !== null && $hint !== '') { ?>
	<p class="pm3d-section__hint"><?php echo pm_h($hint); ?></p>
	<?php } ?>
	<div class="pm3d-section__content">
    <?php
}

function player_feast_section_close(): void
{
    ?>
	</div>
</section>
    <?php
}

function player_feast_render_played_days(int $playerId, string $firstGameDateYmd): void
{
    $fromAttr = preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstGameDateYmd) ? $firstGameDateYmd : date('Y-m-d');
    player_feast_section_open('Played days', 'UTC calendar days with at least one rated game, from the first rated game through today.');
    ?>
<div class="pm3-cal pm3-cal--hero pm3-cal--days pm3-cal--year-pick" data-player-id="<?php echo $playerId; ?>" data-first-game-date="<?php echo pm_h($fromAttr); ?>" aria-label="Calendar activity">
	<p class="pm3-cal__status pm3-muted">Loading calendar…</p>
	<div class="pm3-cal__toolbar" hidden>
		<div class="pm3-cal__year-picker pm3d-rating-toggle" role="tablist" aria-label="Calendar year"></div>
	</div>
	<div class="pm3-cal__year-view"></div>
	<p class="pm3-cal__legend"><span class="pm3-cal__cell" aria-hidden="true"></span> no rated game · <span class="pm3-cal__cell pm3-cal__cell--play" aria-hidden="true"></span> played</p>
</div>
    <?php
    player_feast_section_close();
}

function player_feast_render_played_weeks(int $playerId, string $firstGameDateYmd): void
{
    $fromAttr = preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstGameDateYmd) ? $firstGameDateYmd : date('Y-m-d');
    player_feast_section_open('Played weeks', 'UTC weeks with at least one rated game since the first rated game.');
    ?>
<div class="pm3-cal pm3-cal--hero pm3-cal--weeks" data-player-id="<?php echo $playerId; ?>" data-first-game-date="<?php echo pm_h($fromAttr); ?>" aria-label="Weekly activity since first rated game">
	<p class="pm3-cal__status pm3-muted">Loading weeks…</p>
	<div class="pm3-cal__years"></div>
	<p class="pm3-cal__legend"><span class="pm3-cal__cell" aria-hidden="true"></span> no rated game · <span class="pm3-cal__cell pm3-cal__cell--play" aria-hidden="true"></span> played</p>
</div>
    <?php
    player_feast_section_close();
}

/**
 * @return array{day: ?array, month: ?array, year: ?array}
 */
function player_feast_peak_busiest(array $pm): array
{
    return [
        'day' => $pm['busiest']['day'] ?? null,
        'month' => $pm['busiest']['month'] ?? null,
        'year' => $pm['busiest']['year'] ?? null,
    ];
}

/** Personal bests — busiest day, month, and year. */
function player_feast_render_peak_activity(array $pm): void
{
    $b = player_feast_peak_busiest($pm);
    $bd = $b['day'];
    $bm = $b['month'];
    $by = $b['year'];

    player_feast_section_open('Personal bests', 'Most rated games in a single day, month, and calendar year.');
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
    player_feast_section_close();
}

function player_feast_render_stat_value(string $value): void
{
    echo pm_h($value);
}

/**
 * @return array<int, array{label: string, value: string, rank: ?int}>
 */
function player_feast_presence_stat_rows(array $pm): array
{
    return [
        ['label' => 'Last seen online', 'value' => (string) $pm['last_login'], 'rank' => null],
        ['label' => 'Last rated game', 'value' => (string) $pm['last_game'], 'rank' => null],
        ['label' => 'Games this month', 'value' => (string) (int) $pm['games_this_month'], 'rank' => null],
        ['label' => 'Games this year', 'value' => (string) (int) $pm['games_this_year'], 'rank' => null],
        ['label' => 'First rated game', 'value' => (string) $pm['first_game_date'], 'rank' => null],
    ];
}

/**
 * @return array<int, array{label: string, value: string, rank: ?int}>
 */
function player_feast_career_stat_rows(array $pm): array
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
function player_feast_render_stat_table_rows(array $rows): void
{
    ?>
<table class="pm3efg-stat-table">
	<tbody>
	<?php foreach ($rows as $row) { ?>
		<tr class="pm3efg-stat-table__row">
			<th scope="row"><?php echo pm_h($row['label']); ?></th>
			<td><?php player_feast_render_stat_value($row['value']); ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
    <?php
}

/**
 * Career stats: label | number | (#rank) — LAB 2 keeps the rank column but mutes
 * it (C01–C05 "rethink"); honours/character lines carry the loud beats instead.
 *
 * @param array<int, array{label: string, value: string, rank?: ?int}> $rows
 */
function player_feast_render_career_stats_table(array $rows): void
{
    ?>
<table class="pm3efg-stat-table pm3efg-stat-table--career">
	<tbody>
	<?php foreach ($rows as $row) {
	    $rank = $row['rank'] ?? null;
	    ?>
		<tr class="pm3efg-stat-table__row">
			<th scope="row"><?php echo pm_h($row['label']); ?></th>
			<td class="pm3efg-career-stats__num"><?php player_feast_render_stat_value($row['value']); ?></td>
			<td class="pm3efg-career-stats__rank"><?php
	        if ($rank !== null && $rank > 0) {
	            echo '<span class="k2-lab2-rank">#' . (int) $rank . '</span>';
	        }
	    ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
    <?php
}

/** Presence + Career side by side (at-a-glance band). */
function player_feast_render_presence_career_duo(array $pm): void
{
    player_feast_section_open('');
    ?>
<div class="pm3efg-duo pm3efg-duo--g">
	<div class="pm3efg-duo__panel pm3efg-duo__panel--presence">
		<h3 class="pm3efg-duo__panel-title">Presence</h3>
		<?php player_feast_render_stat_table_rows(player_feast_presence_stat_rows($pm)); ?>
	</div>
	<div class="pm3efg-duo__panel pm3efg-duo__panel--career">
		<h3 class="pm3efg-duo__panel-title">Career</h3>
		<?php player_feast_render_career_stats_table(player_feast_career_stat_rows($pm)); ?>
	</div>
</div>
    <?php
    player_feast_section_close();
}

function player_feast_render_charts(int $playerId): void
{
    $uid = 'pm3d-' . $playerId;

    player_feast_section_open('Career rating', 'Rating arc and monthly activity — toggle the left chart by calendar date or game number.');
    ?>
<div class="pm3d-career-charts">
	<div class="player-rating-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
		<h3 class="k2-panel-heading">ELO rating</h3>
		<p class="k2-chart-block__hint">Calendar view uses the shared server timeline from June 9, 2017; game-number view shows career progress without calendar gaps.</p>
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
	<div class="player-games-month-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
		<h3 class="k2-panel-heading">Games per month</h3>
		<p class="k2-chart-block__hint">Monthly activity on the same server timeline, including quiet months.</p>
		<p class="player-games-month-chart-status pm3d-chart__status k2-chart-panel__status">Loading games per month…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Games per calendar month"></canvas>
		</div>
	</div>
</div>
    <?php
    player_feast_section_close();

    player_feast_section_open('Matchups', 'Pick a frequent opponent to update the head-to-head and rating comparison graphs.');
    ?>
<div class="pm3d-matchups">
	<div class="player-top-opponents-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
		<h3 class="k2-panel-heading">Most frequent opponents</h3>
		<p class="k2-chart-block__hint">Click a bar to compare against that opponent below.</p>
		<p class="player-top-opponents-chart-status pm3d-chart__status k2-chart-panel__status">Loading top opponents…</p>
		<canvas class="player-top-opponents-canvas" aria-label="Most played opponents"></canvas>
	</div>
	<h3 class="pm3d-matchups__subtitle">Head-to-head</h3>
	<div class="player-head-to-head-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
		<p class="pm3d-chart__opponent">vs <span class="player-head-to-head-opponent-name">…</span></p>
		<p class="player-head-to-head-meta pm3d-chart__meta"></p>
		<p class="player-head-to-head-chart-status pm3d-chart__status k2-chart-panel__status">Waiting for opponent…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Head-to-head cumulative wins"></canvas>
		</div>
	</div>
	<h3 class="pm3d-matchups__subtitle">Rating comparison</h3>
	<div class="player-compare-rating-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
		<div class="pm3d-chart-toolbar">
			<div class="pm3d-rating-toggle" role="tablist" aria-label="Rating comparison chart view">
				<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-view="date">By date</button>
				<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-view="game">By games played</button>
			</div>
			<p class="pm3d-chart__opponent">vs <span class="player-compare-rating-opponent-name">…</span></p>
		</div>
		<p class="player-compare-rating-meta pm3d-chart__meta"></p>
		<p class="player-compare-rating-chart-status pm3d-chart__status k2-chart-panel__status">Waiting for opponent…</p>
		<div class="player-compare-rating-view player-compare-rating-view--date">
			<div class="k2-chart-frame">
				<canvas class="player-compare-rating-canvas--date" aria-label="Rating comparison by calendar date"></canvas>
			</div>
		</div>
		<div class="player-compare-rating-view player-compare-rating-view--game" hidden>
			<div class="k2-chart-frame">
				<canvas class="player-compare-rating-canvas--game" aria-label="Rating comparison by games played"></canvas>
			</div>
		</div>
	</div>
	<div class="player-h2h-opponent-search player-search pm3d-h2h-search" data-player-id="<?php echo $playerId; ?>" data-realm="online" role="search">
		<label class="player-search-label" for="<?php echo pm_h($uid); ?>-h2h">Compare someone else</label>
		<p class="k2-chart-block__hint">Search is here for rare matchups outside the top-opponent graph.</p>
		<input id="<?php echo pm_h($uid); ?>-h2h" class="player-search-input player-h2h-search-input" type="search" maxlength="32" autocomplete="off" spellcheck="false" placeholder="Search player name…" />
		<ul class="player-search-results player-h2h-search-results" role="listbox" hidden></ul>
	</div>
</div>
    <?php
    player_feast_section_close();
}

/* ------------------------------------------------------------------ *
 * LAB 2 — new Profile content v1 modules
 * ------------------------------------------------------------------ */

/** One prose "ticker" line: glyph + sentence with a single primary number. */
function pl2_story_line(string $glyph, string $html): void
{
    ?>
		<li class="k2-lab2-line">
			<span class="k2-lab2-line__glyph" aria-hidden="true"><?php echo pm_h($glyph); ?></span>
			<span class="k2-lab2-line__text"><?php echo $html; ?></span>
		</li>
    <?php
}

/**
 * B1 pulse + B2 character, told as celebratory one-liners under the duo tiles.
 * B06 win streak · B07/B08 play streak (one narrative, rotated) · C12 victims
 * · P02 best year · P05 distinct days.
 */
function player_feast_render_story_lines(array $pm): void
{
    $lab = $pm['lab2'] ?? [];
    $lines = [];

    // B06 — current win streak, only when it actually means something (>= 3).
    $winStreak = (int) ($pm['winning_streak'] ?? 0);
    if ($winStreak >= 3) {
        $lines[] = ['🔥', 'On a <strong>' . (int) $winStreak . '-game</strong> winning streak right now.'];
    }

    // B07/B08 — one play-streak story per load. Day vs week chosen deterministically
    // by player id parity (cache-friendly, no session); prefer a live run, else the
    // personal best with its date.
    $streaks = $lab['play_streaks'] ?? null;
    if (is_array($streaks)) {
        $useWeek = ((int) $pm['id'] % 2) === 1;
        $type = $useWeek ? 'week' : 'day';
        $unit = $useWeek ? 'week' : 'day';
        $s = $streaks[$type] ?? ['current' => 0, 'best' => 0, 'best_date' => ''];
        if ((int) $s['current'] >= 2) {
            $lines[] = ['📆', 'Playing <strong>' . (int) $s['current'] . ' ' . $unit . 's</strong> in a row — and counting.'];
        } elseif ((int) $s['best'] >= 2) {
            $when = $s['best_date'] !== '' ? ' in ' . pm_h((string) $s['best_date']) : '';
            $lines[] = ['📆', 'Longest run: <strong>' . (int) $s['best'] . ' ' . $unit . 's</strong> in a row' . $when . '.'];
        }
    }

    // C12 — reach across the ladder.
    $opps = (int) ($pm['different_opponents'] ?? 0);
    $victims = (int) ($pm['different_victims'] ?? 0);
    if ($opps > 0) {
        $line = 'Faced <strong>' . number_format($opps) . '</strong> different opponents';
        if ($victims > 0) {
            $line .= ' — and beaten <strong>' . number_format($victims) . '</strong> of them.';
        } else {
            $line .= '.';
        }
        $lines[] = ['🌐', $line];
    }

    // P02 — best calendar year by wins.
    $bestYear = $lab['best_year'] ?? null;
    if (is_array($bestYear) && (int) $bestYear['wins'] > 0) {
        $lines[] = ['🏅', 'Best year was <strong>' . (int) $bestYear['year'] . '</strong> — <strong>' . number_format((int) $bestYear['wins']) . '</strong> wins.'];
    }

    // P05 — lifetime presence.
    $distinctDays = (int) ($lab['distinct_days'] ?? 0);
    if ($distinctDays > 0) {
        $lines[] = ['🗓', 'Showed up to play on <strong>' . number_format($distinctDays) . '</strong> different days.'];
    }

    if ($lines === []) {
        return;
    }

    player_feast_section_open('The story so far');
    ?>
<ul class="k2-lab2-lines">
    <?php foreach ($lines as [$glyph, $html]) {
        pl2_story_line($glyph, $html);
    } ?>
</ul>
    <?php
    player_feast_section_close();
}

/**
 * B3 Honours — one combined band for milestones + league recognition.
 * X04: the whole band is skipped when the player has neither.
 */
function player_feast_render_honours(array $pm): void
{
    $lab = $pm['lab2'] ?? [];
    $ms = $lab['milestones'] ?? ['total' => 0, 'holo' => 0, 'amber' => 0, 'last12' => 0, 'latest' => null];
    $lg = $lab['league'] ?? ['has_any' => false];
    $playerId = (int) $pm['id'];

    $hasMilestones = (int) $ms['total'] > 0;
    $hasLeague = !empty($lg['has_any']);
    if (!$hasMilestones && !$hasLeague) {
        return;
    }

    player_feast_section_open('Honours', 'Milestones unlocked and league medals — the marks the community keeps.');
    ?>
<div class="k2-lab2-honours">
    <?php if ($hasMilestones) { ?>
	<div class="k2-lab2-honours__col k2-lab2-honours__col--ms">
		<h3 class="k2-lab2-honours__heading">Milestones</h3>
        <?php
        $latest = $ms['latest'] ?? null;
        if (is_array($latest)) {
            $token = pm_h((string) $latest['token']);
            ?>
		<a class="k2-lab2-ms-card k2-ms-card--<?php echo $token; ?>" href="<?php echo pm_h((string) $latest['href']); ?>">
			<span class="k2-lab2-ms-card__tag">Latest unlock</span>
			<span class="k2-lab2-ms-card__name"><?php echo pm_h((string) $latest['name']); ?></span>
            <?php if ($latest['date'] !== '') { ?>
			<span class="k2-lab2-ms-card__date"><?php echo pm_h((string) $latest['date']); ?> UTC</span>
            <?php } ?>
		</a>
        <?php } ?>
		<ul class="k2-lab2-honours__facts">
			<li><strong><?php echo (int) $ms['total']; ?></strong> milestones unlocked</li>
            <?php if ((int) $ms['holo'] > 0) { ?>
			<li><strong><?php echo (int) $ms['holo']; ?></strong> legendary <span class="k2-lab2-muted">(holo tier)</span></li>
            <?php } elseif ((int) $ms['amber'] > 0) { ?>
			<li><strong><?php echo (int) $ms['amber']; ?></strong> accomplished <span class="k2-lab2-muted">(amber tier)</span></li>
            <?php } ?>
            <?php if ((int) $ms['last12'] > 0) { ?>
			<li><strong><?php echo (int) $ms['last12']; ?></strong> unlocked in the last 12 months</li>
            <?php } ?>
		</ul>
		<p class="k2-lab2-honours__link"><a href="/player/milestones.php?id=<?php echo $playerId; ?>">Milestone garden →</a></p>
	</div>
    <?php } ?>
    <?php if ($hasLeague) {
        $latest = $lg['latest'] ?? null;
        ?>
	<div class="k2-lab2-honours__col k2-lab2-honours__col--league">
		<h3 class="k2-lab2-honours__heading">League</h3>
        <?php if (is_array($latest)) {
            $medal = (string) $latest['medal'];
            ?>
		<a class="k2-lab2-medal k2-lab2-medal--<?php echo pm_h($medal); ?>" href="<?php echo pm_h((string) $latest['href']); ?>">
			<span class="k2-lab2-medal__icon" aria-hidden="true"><?php echo pl2_medal_glyph($medal); ?></span>
			<span class="k2-lab2-medal__body">
				<span class="k2-lab2-medal__tag">Latest medal · <?php echo pm_h((string) $latest['date']); ?></span>
				<span class="k2-lab2-medal__label"><?php echo pm_h(ucfirst($medal)); ?> · <?php echo pm_h((string) $latest['label']); ?></span>
			</span>
		</a>
        <?php } ?>
		<ul class="k2-lab2-honours__facts">
            <?php if ((int) $lg['wins'] > 0) { ?>
			<li><strong><?php echo (int) $lg['wins']; ?></strong> league <?php echo (int) $lg['wins'] === 1 ? 'win' : 'wins'; ?></li>
            <?php } ?>
			<li class="k2-lab2-podium">
				<span class="k2-lab2-podium__medal">🥇 <strong><?php echo (int) $lg['gold']; ?></strong></span>
				<span class="k2-lab2-podium__medal">🥈 <strong><?php echo (int) $lg['silver']; ?></strong></span>
				<span class="k2-lab2-podium__medal">🥉 <strong><?php echo (int) $lg['bronze']; ?></strong></span>
			</li>
            <?php if (!empty($lg['top_slice'])) { ?>
			<li>Strongest in <strong><?php echo pm_h((string) $lg['top_slice']['label']); ?></strong> <span class="k2-lab2-muted">(<?php echo (int) $lg['top_slice']['gold']; ?> gold)</span></li>
            <?php } ?>
		</ul>
		<p class="k2-lab2-honours__link"><a href="/status.php">League standings →</a></p>
	</div>
    <?php } ?>
</div>
    <?php
    player_feast_section_close();
}

function pl2_medal_glyph(string $medal): string
{
    switch ($medal) {
        case 'gold':
            return '🥇';
        case 'silver':
            return '🥈';
        case 'bronze':
            return '🥉';
        default:
            return '🏅';
    }
}

/**
 * B4 Memory — Moments mosaic. Forks the shipped grid to add the M03 max-rated
 * victim card (rank-gated upset) and an M08 favourite-victim line below.
 * X01: optimistic empty state when there is nothing to celebrate yet.
 */
function player_feast_render_moments(array $pm): void
{
    $lab = $pm['lab2'] ?? [];
    $maxVictim = $lab['max_rated_victim'] ?? null;
    $fave = $lab['favourite_victim'] ?? null;
    $trophies = $pm['trophies'] ?? [];
    $winStreak = (int) ($pm['longest_win_streak'] ?? 0);

    $hasAnything = $winStreak > 0 || $trophies !== [] || is_array($maxVictim);

    player_feast_section_open('Moments', 'The games worth remembering.');

    if (!$hasAnything) {
        ?>
<div class="pm3-moments pm3-moments--mosaic k2-lab2-moments--empty">
	<p class="k2-lab2-empty">The first trophy game is still out there. Play a few rated games and the highlights will land here.</p>
</div>
        <?php
        player_feast_section_close();

        return;
    }
    ?>
<div class="pm3-moments pm3-moments--mosaic">
	<div class="pm3-moments__grid">
        <?php if ($winStreak > 0) { ?>
		<article class="pm3-moment pm3-moment--streak">
			<span class="pm3-moment__glyph" aria-hidden="true">🏆</span>
			<span class="pm3-moment__tag">Streak</span>
			<h3 class="pm3-moment__label">Longest win run</h3>
			<p class="pm3-moment__score"><?php echo (int) $winStreak; ?> wins</p>
		</article>
        <?php } ?>
        <?php if (is_array($maxVictim)) { ?>
		<article class="pm3-moment pm3-moment--giant">
			<span class="pm3-moment__glyph" aria-hidden="true">🗡️</span>
			<span class="pm3-moment__tag">Giant-killing</span>
			<h3 class="pm3-moment__label">Best scalp<?php echo (isset($maxVictim['victim_rating']) && (int) $maxVictim['victim_rating'] > 0) ? ' · ' . (int) $maxVictim['victim_rating'] . ' Elo' : ''; ?></h3>
			<p class="pm3-moment__score">
				<a href="/game.php?id=<?php echo (int) $maxVictim['game_id']; ?>"><?php echo pm_h((string) $maxVictim['score']); ?></a>
			</p>
			<p class="pm3-moment__meta">
				<span class="<?php echo pm_h((string) $maxVictim['outcome_class']); ?>"><?php echo pm_h((string) $maxVictim['outcome']); ?></span>
				· vs <a href="/player/profile.php?id=<?php echo (int) $maxVictim['opponent_id']; ?>"><?php echo pm_h((string) $maxVictim['opponent_name']); ?></a>
				· <?php echo pm_h((string) $maxVictim['date']); ?>
			</p>
		</article>
        <?php } ?>
        <?php foreach ($trophies as $t) { ?>
		<article class="pm3-moment">
			<span class="pm3-moment__glyph" aria-hidden="true"><?php echo $t['icon']; ?></span>
			<span class="pm3-moment__tag"><?php echo pm_h($t['tag']); ?></span>
			<h3 class="pm3-moment__label"><?php echo pm_h($t['label']); ?></h3>
			<p class="pm3-moment__score">
				<a href="/game.php?id=<?php echo (int) $t['game_id']; ?>"><?php echo pm_h($t['score']); ?></a>
			</p>
			<p class="pm3-moment__meta">
				<span class="<?php echo pm_h($t['outcome_class']); ?>"><?php echo pm_h($t['outcome']); ?></span>
				· vs <a href="/player/profile.php?id=<?php echo (int) $t['opponent_id']; ?>"><?php echo pm_h($t['opponent_name']); ?></a>
				· <?php echo pm_h($t['date']); ?>
			</p>
		</article>
        <?php } ?>
	</div>
    <?php if (is_array($fave)) { ?>
	<p class="k2-lab2-fave-victim">
		Favourite victim:
		<a href="/player/games.php?id=<?php echo (int) $pm['id']; ?>&amp;opponent=<?php echo (int) $fave['opponent_id']; ?>"><?php echo pm_h((string) $fave['opponent_name']); ?></a>
		— beaten <strong><?php echo (int) $fave['wins']; ?></strong> times.
	</p>
    <?php } ?>
</div>
    <?php
    player_feast_section_close();
}

/**
 * C1 — M09 featured-rivalry W–D–L line, shown just before the matchup charts so
 * the auto-selected #1 opponent has prose context.
 */
function player_feast_render_rivalry_line(array $pm): void
{
    $rival = $pm['lab2']['featured_rival'] ?? null;
    if (!is_array($rival) || (int) $rival['games'] < 1) {
        return;
    }
    $playerId = (int) $pm['id'];

    player_feast_section_open('Main rival');
    ?>
<p class="k2-lab2-rivalry">
	Most-played opponent:
	<a href="/player/profile.php?id=<?php echo (int) $rival['opponent_id']; ?>"><?php echo pm_h((string) $rival['opponent_name']); ?></a>
	over <strong><?php echo (int) $rival['games']; ?></strong> games —
	<span class="k2-lab2-wdl">
		<span class="pm-outcome--win"><?php echo (int) $rival['wins']; ?>W</span> ·
		<span class="pm-outcome--draw"><?php echo (int) $rival['draws']; ?>D</span> ·
		<span class="pm-outcome--loss"><?php echo (int) $rival['losses']; ?>L</span>
	</span>.
	<a class="k2-lab2-rivalry__link" href="/player/games.php?id=<?php echo $playerId; ?>&amp;opponent=<?php echo (int) $rival['opponent_id']; ?>">All games →</a>
</p>
    <?php
    player_feast_section_close();
}

/** Lab banner — required so a preview is never mistaken for production. */
function player_feast_render_lab2_banner(array $pm): void
{
    ?>
<p class="k2-lab-banner k2-lab2-banner">Profile lab preview — Agent 2 — not production</p>
    <?php
}

/** Optimistic welcome for players with no rated games yet (X01/X04). */
function player_feast_render_lab2_newcomer(array $pm): void
{
    player_feast_section_open('Welcome to the ladder');
    ?>
<p class="k2-lab2-empty">No rated games on record yet. Play your first match and this profile fills with streaks, milestones and memorable games.</p>
    <?php
    player_feast_section_close();
}

/**
 * Master orchestrator — Chronicle-first scroll spine for the lab page.
 */
function player_feast_render_profile_lab2(array $pm): void
{
    $playerId = (int) $pm['id'];

    player_feast_render_lab2_banner($pm);

    if ((int) $pm['games'] < 1) {
        // Sparse / no-games: keep it optimistic, skip the heavy analyst bands.
        player_feast_render_lab2_newcomer($pm);
        player_feast_render_presence_career_duo($pm);

        return;
    }

    // Zone B — Celebrate (tiles → prose → honours → memory → texture)
    player_feast_render_presence_career_duo($pm);   // B1 + B2 at a glance
    player_feast_render_story_lines($pm);           // B1 pulse + B2 character prose
    player_feast_render_honours($pm);               // B3 recognition
    player_feast_render_peak_activity($pm);         // B4 personal bests
    player_feast_render_moments($pm);               // B4 moments (+ M03, M08)
    player_feast_render_played_days($playerId, (string) $pm['first_game_date_ymd']);   // B5 texture
    player_feast_render_played_weeks($playerId, (string) $pm['first_game_date_ymd']);

    // Zone C — Understand (rivalry context → analyst charts last)
    player_feast_render_rivalry_line($pm);          // C1
    player_feast_render_charts($playerId);          // C2 / C3
}
