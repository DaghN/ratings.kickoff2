<?php
/**
 * Player profile feast blocks (player/profile.php Profile tab).
 */

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

/** Personal bests — busiest day, month, and year (N11). */
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
