<?php
/**
 * Player profile feast blocks (player/profile.php Profile tab).
 */

require_once __DIR__ . '/k2_routes.php';
require_once __DIR__ . '/k2_safety.php';

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
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstGameDateYmd)) {
        return;
    }
    $fromAttr = $firstGameDateYmd;
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
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstGameDateYmd)) {
        return;
    }
    $fromAttr = $firstGameDateYmd;
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
 * @return array{day: ?array, week: ?array, month: ?array, year: ?array}
 */
function player_feast_peak_busiest(array $pm): array
{
    return [
        'day' => $pm['busiest']['day'] ?? null,
        'week' => $pm['busiest']['week'] ?? null,
        'month' => $pm['busiest']['month'] ?? null,
        'year' => $pm['busiest']['year'] ?? null,
    ];
}

/** Bursts of activity — busiest day, week, month, and year (P01 + P04). */
function player_feast_render_busiest_card(
    int $playerId,
    string $periodType,
    string $glyph,
    string $kind,
    ?array $peak,
    string $whenFormatted
): void {
    $count = $peak !== null ? (int) $peak['count'] : null;
    $hasData = $count !== null && $count > 0 && $whenFormatted !== '';
    $href = $hasData && $peak !== null
        ? player_feast_busiest_games_href($playerId, $periodType, (string) $peak['key'])
        : null;
    $ariaLabel = $hasData
        ? 'View ' . number_format((int) $count) . ' games — ' . $whenFormatted
        : '';
    ?>
		<li>
			<?php if ($href !== null && $href !== '') { ?>
			<a class="pm3-busiest__card" href="<?php echo pm_h($href); ?>"<?php echo $ariaLabel !== '' ? ' aria-label="' . pm_h($ariaLabel) . '"' : ''; ?>>
			<?php } else { ?>
			<div class="pm3-busiest__card pm3-busiest__card--empty">
			<?php } ?>
				<span class="pm3-busiest__glyph" aria-hidden="true"><?php echo pm_h($glyph); ?></span>
				<span class="pm3-busiest__kind"><?php echo pm_h($kind); ?></span>
				<strong><?php echo $hasData ? number_format($count) : '—'; ?></strong>
				<?php if ($hasData) { ?>
				<span class="pm3-busiest__unit">games</span>
				<span class="pm3-busiest__when"><?php echo pm_h($whenFormatted); ?></span>
				<?php } ?>
			<?php if ($href !== null && $href !== '') { ?>
			</a>
			<?php } else { ?>
			</div>
			<?php } ?>
		</li>
    <?php
}

function player_feast_render_peak_activity(array $pm): void
{
    $b = player_feast_peak_busiest($pm);
    $bd = $b['day'];
    $bw = $b['week'];
    $bm = $b['month'];
    $by = $b['year'];
    $name = pm_h((string) ($pm['name'] ?? 'This player'));
    $playerId = (int) $pm['id'];
    ?>
<section class="pm3d-section pm3d-section--bursts" id="bursts-of-activity">
	<h2 class="k2-panel-heading pm3d-section__title visually-hidden">Bursts of activity</h2>
	<p class="pm3d-section__hint">After all that steady play... here are the days, weeks, months, and years where <span class="k2-link-star pm3-cal__status-name"><?php echo $name; ?></span> went into overdrive and the games really piled up...</p>
	<div class="pm3d-section__content">
<div class="pm3-busiest pm3-busiest--inline pm3-busiest--bursts">
	<ol class="pm3-busiest__list">
		<?php
        player_feast_render_busiest_card(
            $playerId,
            'day',
            '🔥',
            'Busiest day',
            $bd,
            $bd ? pm2_format_busiest_day((string) $bd['key']) : ''
        );
    player_feast_render_busiest_card(
        $playerId,
        'week',
        '⚡',
        'Busiest week',
        $bw,
        $bw ? pm2_format_busiest_week((string) $bw['key']) : ''
    );
    player_feast_render_busiest_card(
        $playerId,
        'month',
        '🔥',
        'Busiest month',
        $bm,
        $bm ? pm2_format_busiest_month((string) $bm['key']) : ''
    );
    player_feast_render_busiest_card(
        $playerId,
        'year',
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
    $maxVictim = $pm['max_rated_victim'] ?? null;
    $trophies = is_array($pm['trophies'] ?? null) ? $pm['trophies'] : [];
    $peakMoment = is_array($pm['peak_moment'] ?? null) ? $pm['peak_moment'] : null;
    if (!is_array($maxVictim) && $trophies === [] && $peakMoment === null) {
        return;
    }

    $name = pm_h((string) ($pm['name'] ?? 'This player'));
    ?>
<section class="pm3d-section pm3d-section--moments" id="moments">
	<h2 class="k2-panel-heading pm3d-section__title visually-hidden">Moments</h2>
	<p class="pm3d-section__hint">No career page could ever capture every match worth remembering... but these ones? Certainly among <span class="k2-link-star pm3-cal__status-name"><?php echo $name; ?></span>'s proudest moments!</p>
	<div class="pm3d-section__content">
<div class="pm3-moments pm3-moments--mosaic">
	<div class="pm3-moments__grid">
        <?php if (is_array($maxVictim)) { ?>
		<article class="pm3-moment pm3-moment--giant">
			<span class="pm3-moment__glyph" aria-hidden="true">🗡️</span>
			<span class="pm3-moment__tag">Giant-killing</span>
			<h3 class="pm3-moment__label">Best scalp<?php echo isset($maxVictim['victim_rating']) && (int) $maxVictim['victim_rating'] > 0 ? ' · ' . (int) $maxVictim['victim_rating'] . ' Elo' : ''; ?></h3>
			<p class="pm3-moment__score">
				<a href="<?php echo pm_h(k2_game_page_url((int) $maxVictim['game_id'])); ?>"><?php echo pm_h((string) $maxVictim['score']); ?></a>
			</p>
			<p class="pm3-moment__meta">
				<span class="<?php echo pm_h((string) $maxVictim['outcome_class']); ?>"><?php echo pm_h((string) $maxVictim['outcome']); ?></span>
				· vs <?php echo k2_player_link((int) $maxVictim['opponent_id'], (string) $maxVictim['opponent_name']); ?>
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
				<a href="<?php echo pm_h(k2_game_page_url((int) $t['game_id'])); ?>"><?php echo pm_h($t['score']); ?></a>
			</p>
			<p class="pm3-moment__meta">
				<span class="<?php echo pm_h($t['outcome_class']); ?>"><?php echo pm_h($t['outcome']); ?></span>
				· vs <?php echo k2_player_link((int) $t['opponent_id'], (string) $t['opponent_name']); ?>
				· <?php echo pm_h($t['date']); ?>
			</p>
		</article>
        <?php } ?>
        <?php if ($peakMoment !== null) { ?>
		<article class="pm3-moment pm3-moment--peak">
			<span class="pm3-moment__glyph" aria-hidden="true"><?php echo $peakMoment['icon']; ?></span>
			<span class="pm3-moment__tag"><?php echo pm_h((string) $peakMoment['tag']); ?></span>
			<h3 class="pm3-moment__label"><?php echo pm_h((string) $peakMoment['label']); ?></h3>
			<p class="pm3-moment__score">
				<a href="<?php echo pm_h(k2_game_page_url((int) $peakMoment['game_id'])); ?>"><?php echo (int) $peakMoment['peak_rating']; ?></a>
			</p>
			<p class="pm3-moment__meta">
				<span class="<?php echo pm_h((string) $peakMoment['outcome_class']); ?>"><?php echo pm_h((string) $peakMoment['outcome']); ?></span>
				· vs <?php echo k2_player_link((int) $peakMoment['opponent_id'], (string) $peakMoment['opponent_name']); ?>
				· <?php echo pm_h((string) $peakMoment['date']); ?>
			</p>
		</article>
        <?php } ?>
	</div>
</div>
	</div>
</section>
    <?php
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
        ['label' => 'Last rated game', 'value' => (string) $pm['last_game']],
        ['label' => 'First rated game', 'value' => (string) $pm['first_game_date']],
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
    return k2_milestone_player_tier_rows($counts);
}

function player_feast_glance_milestone_tier_tooltip(int $count, string $band): string
{
    return k2_milestone_tier_count_tooltip($count, $band);
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
    $playerId = (int) ($pm['id'] ?? 0);
    $parts = [];
    foreach ($tiers as $i => $tier) {
        $sep = $i > 0 ? '<span class="pm3efg-tier-sep" aria-hidden="true"> · </span>' : '';
        $count = (int) $tier['count'];
        $token = pm_h($tier['token']);
        $help = pm_h(player_feast_glance_milestone_tier_tooltip($count, $tier['band']));
        $href = pm_h(k2_milestone_garden_tier_href($playerId, $tier['band']));
        $parts[] = $sep . '<a href="' . $href . '"'
            . ' class="k2-lb-ms-tier--' . $token . ' pm3efg-tier-count pm3efg-tier-count__link k2-table-helped"'
            . ' data-k2-coarse-tap="1"'
            . ' data-k2-tooltip-hide-title="1"'
            . ' data-k2-help="' . $help . '"'
            . ' data-k2-tooltip-tier="' . $token . '"'
            . ' data-k2-tooltip-action="Click to open the milestone garden"'
            . ' data-k2-tooltip-action-coarse="Tap again to open the milestone garden"'
            . '>' . $count . '</a>';
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

    $longestRunLine = null;
    $streaks = $story['play_streaks'] ?? null;
    if (is_array($streaks)) {
        $type = player_feast_story_play_streak_axis();
        $unit = $type === 'week' ? 'week' : 'day';
        $s = $streaks[$type] ?? ['current' => 0, 'best' => 0, 'best_date' => ''];
        if ((int) $s['current'] >= 2) {
            $lines[] = ['📆', 'Playing <strong>' . (int) $s['current'] . ' ' . $unit . 's</strong> in a row — and counting.'];
        } elseif ((int) $s['best'] >= 2) {
            $when = $s['best_date'] !== '' ? ' in ' . pm_h((string) $s['best_date']) : '';
            $longestRunLine = ['📆', 'The longest run: <strong>' . (int) $s['best'] . ' ' . $unit . 's</strong> in a row' . $when . '.'];
        }
    }

    $opps = (int) ($pm['different_opponents'] ?? 0);
    $victims = (int) ($pm['different_victims'] ?? 0);
    if ($opps > 0) {
        $line = 'Faced <strong>' . number_format($opps) . '</strong> different opponents';
        if ($victims > 0) {
            $line .= ' — and beat <strong>' . number_format($victims) . '</strong> of them!';
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
        $lines[] = ['🗓', 'Showed up to play on <strong>' . number_format($distinctDays) . '</strong> different days!'];
    }

    if ($longestRunLine !== null) {
        $lines[] = $longestRunLine;
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

function player_feast_render_charts(int $playerId, string $playerName = ''): void
{
    $name = pm_h($playerName !== '' ? $playerName : 'This player');
    ?>
<section class="pm3d-section pm3d-section--charts" id="career-charts">
	<h2 class="k2-panel-heading pm3d-section__title visually-hidden">Career rating</h2>
	<p class="pm3d-section__hint">Every career has its ups and downs… the charts below capture some of that… but while spanning his whole career, the charts cannot reveal the fact that <span class="k2-link-star pm3-cal__status-name"><?php echo $name; ?></span>'s best days are surely still ahead of him!</p>
	<div class="pm3d-section__content">
<div class="pm3d-career-charts">
	<div class="player-rating-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
		<h3 class="k2-panel-heading" data-k2-help="<?php echo pm_h('Standard Elo rating with a start rating of 1600 and a fixed K-factor of 32.'); ?>" data-k2-tooltip-align="start" tabindex="0">ELO rating</h3>
		<div class="pm3d-rating-toggle" role="tablist" aria-label="Rating chart view">
			<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-view="game">By game #</button>
			<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-view="date">By date</button>
		</div>
		<p class="player-rating-chart-status pm3d-chart__status k2-chart-panel__status">Loading rating history…</p>
		<div class="player-rating-view player-rating-view--date" hidden>
			<p class="player-rating-peak-current-summary pm3d-chart__summary" hidden></p>
			<div class="k2-chart-frame">
				<canvas class="player-rating-canvas--date" aria-label="ELO rating over time"></canvas>
			</div>
		</div>
		<div class="player-rating-view player-rating-view--game">
			<p class="player-rating-game-peak-current-summary pm3d-chart__summary" hidden></p>
			<div class="k2-chart-frame">
				<canvas class="player-rating-canvas--game" aria-label="Rating by game number"></canvas>
			</div>
		</div>
	</div>
	<div class="player-games-month-chart k2-chart-panel" id="games-per-month" data-player-id="<?php echo $playerId; ?>">
		<h3 class="k2-panel-heading">Games per month</h3>
		<p class="k2-chart-block__hint"><span class="k2-link-star pm3-cal__status-name"><?php echo $name; ?></span>'s monthly activity on the server timeline, including quiet months.</p>
		<p class="player-games-month-chart-status pm3d-chart__status k2-chart-panel__status">Loading games per month…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Games per calendar month"></canvas>
		</div>
	</div>
	<div class="player-goals-scored-histogram k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
		<h3 class="k2-panel-heading">Goals per game</h3>
		<p class="k2-chart-block__hint">How many games <span class="k2-link-star pm3-cal__status-name"><?php echo $name; ?></span> scored exactly 0, 1, 2… goals in.<span class="player-goals-scored-histogram-avg-suffix" hidden> <span class="k2-link-star pm3-cal__status-name"><?php echo $name; ?></span> has averaged <span class="player-goals-scored-histogram-avg-val"></span> goals per game so far.</span></p>
		<p class="player-goals-scored-histogram-status pm3d-chart__status k2-chart-panel__status">Loading goals per game…</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Goals scored per game histogram"></canvas>
		</div>
	</div>
</div>
	</div>
</section>
    <?php
    require_once __DIR__ . '/player_opponents_h2h_charts.php';
    ?>
<section class="pm3d-section pm3d-section--top-opponents" id="top-opponents">
	<h2 class="k2-panel-heading pm3d-section__title visually-hidden">Most played opponents</h2>
	<p class="pm3d-section__hint">A lot has happened in <span class="k2-link-star pm3-cal__status-name"><?php echo $name; ?></span>'s career on the ladder — and plenty is still to come! Let's not forget that above the ratings and the scorelines, what matters most are the friends and friendly rivalries we picked up along the way...</p>
	<div class="pm3d-section__content">
    <?php
    player_feast_render_top_opponents_chart($playerId, true);
    ?>
	</div>
</section>
    <?php
}
