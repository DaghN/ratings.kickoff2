<?php
/**
 * Player profile feast blocks (player/profile.php Profile tab).
 */

function player_feast_section_open(string $title, ?string $hint = null, ?string $sectionClass = null): void
{
    $class = 'pm3d-section';
    if ($sectionClass !== null && $sectionClass !== '') {
        $class .= ' ' . $sectionClass;
    }
    ?>
<section class="<?php echo pm_h($class); ?>">
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

function player_feast_render_played_days(int $playerId, string $firstGameDateYmd, string $playerName = ''): void
{
    $fromAttr = preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstGameDateYmd) ? $firstGameDateYmd : date('Y-m-d');
    ?>
<section class="pm3d-section pm3d-section--played-days" id="played-days">
	<h2 class="k2-panel-heading pm3d-section__title visually-hidden">Played days</h2>
	<p class="pm3d-section__hint"><span class="pm3-cal__status pm3-muted">Loading played days…</span></p>
	<div class="pm3d-section__content">
<div class="pm3-cal pm3-cal--hero pm3-cal--days pm3-cal--year-pick" data-player-id="<?php echo $playerId; ?>" data-player-name="<?php echo pm_h($playerName); ?>" data-first-game-date="<?php echo pm_h($fromAttr); ?>" aria-label="Calendar activity">
	<div class="pm3-cal__year-picker pm3d-rating-toggle" role="tablist" aria-label="Calendar year" hidden></div>
	<div class="pm3-cal__year-view"></div>
</div>
	</div>
</section>
    <?php
}

function player_feast_render_played_weeks(int $playerId, string $firstGameDateYmd, string $playerName = ''): void
{
    $fromAttr = preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstGameDateYmd) ? $firstGameDateYmd : date('Y-m-d');
    ?>
<section class="pm3d-section pm3d-section--played-weeks" id="played-weeks">
	<h2 class="k2-panel-heading pm3d-section__title visually-hidden">Played weeks</h2>
	<p class="pm3d-section__hint"><span class="pm3-cal__status pm3-muted">Loading played weeks…</span></p>
	<div class="pm3d-section__content">
<div class="pm3-cal pm3-cal--hero pm3-cal--weeks" data-player-id="<?php echo $playerId; ?>" data-player-name="<?php echo pm_h($playerName); ?>" data-first-game-date="<?php echo pm_h($fromAttr); ?>" aria-label="Weekly activity since first rated game">
	<div class="pm3-cal__years"></div>
</div>
	</div>
</section>
    <?php
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

/** Bursts of activity — busiest day, month, and year (P01). */
function player_feast_render_busiest_card(string $glyph, string $kind, ?array $peak, string $whenFormatted): void
{
    $count = $peak !== null ? (int) $peak['count'] : null;
    $hasData = $count !== null && $count > 0 && $whenFormatted !== '';
    ?>
		<li>
			<span class="pm3-busiest__glyph" aria-hidden="true"><?php echo pm_h($glyph); ?></span>
			<span class="pm3-busiest__kind"><?php echo pm_h($kind); ?></span>
			<strong><?php echo $hasData ? number_format($count) : '—'; ?></strong>
			<?php if ($hasData) { ?>
			<span class="pm3-busiest__unit">games</span>
			<span class="pm3-busiest__when"><?php echo pm_h($whenFormatted); ?></span>
			<?php } ?>
		</li>
    <?php
}

function player_feast_render_peak_activity(array $pm): void
{
    $b = player_feast_peak_busiest($pm);
    $bd = $b['day'];
    $bm = $b['month'];
    $by = $b['year'];
    $name = pm_h((string) ($pm['name'] ?? 'This player'));
    ?>
<section class="pm3d-section pm3d-section--bursts" id="bursts-of-activity">
	<h2 class="k2-panel-heading pm3d-section__title visually-hidden">Bursts of activity</h2>
	<p class="pm3d-section__hint">After all that steady play... here are the days, months, and years where <span class="k2-link-star pm3-cal__status-name"><?php echo $name; ?></span> went into overdrive and the games really piled up...</p>
	<div class="pm3d-section__content">
<div class="pm3-busiest pm3-busiest--inline pm3hij-peak pm3hij-peak--cards pm3-busiest--bursts">
	<ol class="pm3-busiest__list">
		<?php
        player_feast_render_busiest_card(
            '🔥',
            'Busiest day',
            $bd,
            $bd ? pm2_format_busiest_day((string) $bd['key']) : ''
        );
    player_feast_render_busiest_card(
        '🔥',
        'Busiest month',
        $bm,
        $bm ? pm2_format_busiest_month((string) $bm['key']) : ''
    );
    player_feast_render_busiest_card(
        '🔥',
        'Busiest year',
        $by,
        $by ? (string) (int) $by['key'] : ''
    );
    ?>
	</ol>
</div>
	</div>
</section>
    <?php
}

function player_feast_render_moments(array $pm): void
{
    player_feast_section_open('Moments');
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
        ['label' => 'First rated game', 'value' => (string) $pm['first_game_date']],
        ['label' => 'Last rated game', 'value' => (string) $pm['last_game']],
        ['label' => 'Days this year', 'value' => number_format((int) ($pm['days_this_year'] ?? 0))],
        ['label' => 'Games this year', 'value' => (string) (int) $pm['games_this_year']],
    ];
}

/**
 * @return array<int, array{label: string, value: string}>
 */
function player_feast_career_stat_rows(array $pm): array
{
    return [
        ['label' => 'Opponents', 'value' => number_format((int) $pm['different_opponents'])],
        ['label' => 'Games', 'value' => number_format((int) $pm['games'])],
        ['label' => 'Wins', 'value' => number_format((int) $pm['wins'])],
        ['label' => 'Goals', 'value' => number_format((int) $pm['goals_for'])],
    ];
}

/**
 * @param array{aspirational: int, dedicated: int, accomplished: int, legendary: int} $counts
 * @return array<int, array{band: string, count: int, token: string}>
 */
function player_feast_glance_milestone_tiers(array $counts): array
{
    $rows = [];
    foreach (K2_MILESTONE_TIER_ORDER as $band) {
        $countKey = match ($band) {
            'veteran' => 'dedicated',
            'key' => 'accomplished',
            default => $band,
        };
        $rows[] = [
            'band' => $band,
            'count' => (int) ($counts[$countKey] ?? 0),
            'token' => K2_MILESTONE_TIER_CHART_TOKEN[$band] ?? 'pitch',
        ];
    }

    return $rows;
}

/** @param array<string, mixed> $pm */
function player_feast_render_glance_milestones_cell(array $pm): void
{
    $counts = $pm['milestone_counts'] ?? null;
    if (!is_array($counts)) {
        echo '—';

        return;
    }

    $tiers = player_feast_glance_milestone_tiers($counts);
    $parts = [];
    foreach ($tiers as $i => $tier) {
        $sep = $i > 0 ? '<span class="pm3efg-tier-sep" aria-hidden="true"> · </span>' : '';
        $parts[] = $sep . '<span class="k2-lb-ms-tier--' . pm_h($tier['token']) . '">'
            . (int) $tier['count'] . '</span>';
    }
    echo implode('', $parts);
}

/** Row label with optional league medal icon — plain text like Presence/Career categories. */
function player_feast_render_glance_category_label(string $label, ?int $medalRank = null): void
{
    if ($medalRank === null) {
        echo pm_h($label);

        return;
    }
    ?>
<span class="pm3efg-stat-table__label"><?php echo k2_status_league_podium_medal($medalRank); ?><span><?php echo pm_h($label); ?></span></span>
    <?php
}

/** @param array<string, mixed> $pm */
function player_feast_render_achievements_table(array $pm): void
{
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_table_render.php';
    ?>
<table class="pm3efg-stat-table">
	<tbody>
		<tr class="pm3efg-stat-table__row">
			<th scope="row"><?php player_feast_render_glance_category_label('Milestones'); ?></th>
			<td><?php player_feast_render_glance_milestones_cell($pm); ?></td>
		</tr>
		<tr class="pm3efg-stat-table__row">
			<th scope="row"><?php player_feast_render_glance_category_label('Gold', 1); ?></th>
			<td><?php echo (int) ($pm['league_gold'] ?? 0); ?></td>
		</tr>
		<tr class="pm3efg-stat-table__row">
			<th scope="row"><?php player_feast_render_glance_category_label('Silver', 2); ?></th>
			<td><?php echo (int) ($pm['league_silver'] ?? 0); ?></td>
		</tr>
		<tr class="pm3efg-stat-table__row">
			<th scope="row"><?php player_feast_render_glance_category_label('Bronze', 3); ?></th>
			<td><?php echo (int) ($pm['league_bronze'] ?? 0); ?></td>
		</tr>
	</tbody>
</table>
    <?php
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
 * Career stats: label | number | (#rank) in a real column so ranks stack vertically.
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
	            echo '(#' . (int) $rank . ')';
	        }
	    ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
    <?php
}

/** Presence + Career + Achievements (at-a-glance band). */
function player_feast_render_presence_career_duo(array $pm): void
{
    player_feast_section_open('');
    ?>
<div class="pm3efg-at-a-glance">
	<div class="pm3efg-at-a-glance__col pm3efg-at-a-glance__col--presence">
		<h3 class="pm3efg-duo__panel-title">Presence</h3>
		<?php player_feast_render_stat_table_rows(player_feast_presence_stat_rows($pm)); ?>
	</div>
	<div class="pm3efg-at-a-glance__col pm3efg-at-a-glance__col--career">
		<h3 class="pm3efg-duo__panel-title">Career</h3>
		<?php player_feast_render_stat_table_rows(player_feast_career_stat_rows($pm)); ?>
	</div>
	<div class="pm3efg-at-a-glance__col pm3efg-at-a-glance__col--achievements">
		<h3 class="pm3efg-duo__panel-title">Achievements</h3>
		<?php player_feast_render_achievements_table($pm); ?>
	</div>
</div>
    <?php
    player_feast_section_close();
}

/** One prose line: glyph + sentence with highlighted numbers. */
function player_feast_story_line(string $glyph, string $html): void
{
    ?>
		<li class="k2-story-line">
			<span class="k2-story-line__glyph" aria-hidden="true"><?php echo pm_h($glyph); ?></span>
			<span class="k2-story-line__text"><?php echo $html; ?></span>
		</li>
    <?php
}

/**
 * Zone B — celebratory career prose (lab 2 "The story so far" on production).
 * B06 win streak · B07/B08 play streak · C12 victims · P02 best year · P05 distinct days.
 */
function player_feast_render_story_lines(array $pm): void
{
    $story = $pm['story'] ?? [];
    $lines = [];

    $winStreak = (int) ($pm['winning_streak'] ?? 0);
    if ($winStreak >= 3) {
        $lines[] = ['🔥', 'On a <strong>' . (int) $winStreak . '-game</strong> winning streak right now.'];
    }

    $streaks = $story['play_streaks'] ?? null;
    if (is_array($streaks)) {
        $type = player_feast_story_play_streak_axis();
        $unit = $type === 'week' ? 'week' : 'day';
        $s = $streaks[$type] ?? ['current' => 0, 'best' => 0, 'best_date' => ''];
        if ((int) $s['current'] >= 2) {
            $lines[] = ['📆', 'Playing <strong>' . (int) $s['current'] . ' ' . $unit . 's</strong> in a row — and counting.'];
        } elseif ((int) $s['best'] >= 2) {
            $when = $s['best_date'] !== '' ? ' in ' . pm_h((string) $s['best_date']) : '';
            $lines[] = ['📆', 'The longest run: <strong>' . (int) $s['best'] . ' ' . $unit . 's</strong> in a row' . $when . '.'];
        }
    }

    $opps = (int) ($pm['different_opponents'] ?? 0);
    $victims = (int) ($pm['different_victims'] ?? 0);
    if ($opps > 0) {
        $line = 'Faced <strong>' . number_format($opps) . '</strong> different opponents';
        if ($victims > 0) {
            $line .= ' — and beat <strong>' . number_format($victims) . '</strong> of them.';
        } else {
            $line .= '.';
        }
        $lines[] = ['🌐', $line];
    }

    $bestYear = $story['best_year'] ?? null;
    if (is_array($bestYear) && (int) $bestYear['wins'] > 0) {
        $games = (int) ($bestYear['games'] ?? 0);
        $wins = (int) $bestYear['wins'];
        $gameWord = $games === 1 ? 'game' : 'games';
        $lines[] = ['🏅', 'The standout year was <strong>' . (int) $bestYear['year'] . '</strong> — <strong>'
            . number_format($games) . '</strong> ' . $gameWord . ' and <strong>'
            . number_format($wins) . '</strong> wins.'];
    }

    $distinctDays = (int) ($story['distinct_days'] ?? 0);
    if ($distinctDays > 0) {
        $lines[] = ['🗓', 'Showed up to play on <strong>' . number_format($distinctDays) . '</strong> different days.'];
    }

    if ($lines === []) {
        return;
    }

    $name = pm_h((string) ($pm['name'] ?? 'This player'));
    ?>
<section class="pm3d-section pm3d-section--story">
	<h2 class="k2-panel-heading pm3d-section__title visually-hidden">Story so far</h2>
	<div class="pm3d-section__content">
	<p class="k2-story-intro">Let's take a look at <span class="k2-link-star pm3-cal__status-name"><?php echo $name; ?></span>'s story so far...</p>
<ul class="k2-story-lines">
    <?php foreach ($lines as [$glyph, $html]) {
        player_feast_story_line($glyph, $html);
    } ?>
</ul>
	</div>
</section>
    <?php
}

function player_feast_render_charts(int $playerId): void
{
    player_feast_section_open('Career rating', 'Rating arc, monthly activity, and goals-per-game spread — toggle the left chart by calendar date or game number.');
    ?>
<div class="pm3d-career-charts">
	<div class="player-rating-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
		<h3 class="k2-panel-heading">ELO rating</h3>
		<p class="k2-chart-block__hint">Calendar view: end-of-day rating from June 9, 2017; game-number view shows every match without calendar gaps.</p>
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
	<div class="player-goals-scored-histogram k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
		<h3 class="k2-panel-heading">Goals per game</h3>
		<p class="k2-chart-block__hint">How many rated games you scored exactly 0, 1, 2… goals. Click a bar to filter the games list.</p>
		<p class="player-goals-scored-histogram-status pm3d-chart__status k2-chart-panel__status">Loading goals per game…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Goals scored per game histogram"></canvas>
		</div>
	</div>
</div>
    <?php
    player_feast_section_close();

    require_once __DIR__ . '/player_opponents_h2h_charts.php';

    player_feast_section_open(
        'Most played opponents',
        'Who you meet most on the ladder — click a bar to open head-to-head.'
    );
    player_feast_render_top_opponents_chart($playerId);
    player_feast_section_close();
}

/**
 * Placeholder rivalry card — top opponent + link to Opponents H2H (full band TBD).
 */
function player_feast_render_rivalry_teaser(mysqli $con, int $playerId): void
{
    $playerId = max(0, $playerId);
    if ($playerId <= 0) {
        return;
    }

    require_once __DIR__ . '/player_opponents_h2h.php';
    require_once __DIR__ . '/player_opponents_lib.php';

    $opponents = player_opponents_h2h_played_opponents($con, $playerId);
    if ($opponents === []) {
        return;
    }

    $top = $opponents[0];
    $opponentId = (int) $top['opponent_id'];
    $opponentName = (string) $top['opponent_name'];
    $games = (int) $top['games'];
    $h2hHref = player_opponents_href($playerId, 'h2h', $opponentId);

    player_feast_section_open(
        'Rivalry',
        'Placeholder card — fuller rivalry summary (record, form, all games) will land here.'
    );
    ?>
<article class="pm3-rivalry-teaser">
	<p class="pm3-rivalry-teaser__tag">Placeholder</p>
	<p class="pm3-rivalry-teaser__lead">
		Most played opponent:
		<strong><?php echo pm_h($opponentName); ?></strong>
		<span class="pm3-rivalry-teaser__meta"><?php echo pm_h(k2_fmt_int($games, '0')); ?> rated games</span>
	</p>
	<p class="pm3-rivalry-teaser__note">Future slice: headline W/D/L, recent form, streak, and quick links to head-to-head and all games vs this rival.</p>
	<p class="pm3-rivalry-teaser__actions">
		<a class="pm3-rivalry-teaser__link" href="<?php echo pm_h($h2hHref); ?>">Head-to-head vs <?php echo pm_h($opponentName); ?></a>
	</p>
</article>
    <?php
    player_feast_section_close();
}
