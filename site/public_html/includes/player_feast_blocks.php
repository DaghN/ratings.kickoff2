<?php
/**
 * Player profile feast blocks (individual1.php Profile tab).
 */

function player_feast_section_open(string $title, ?string $hint = null): void
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

function player_feast_section_close(): void
{
    ?>
	</div>
</section>
    <?php
}

function player_feast_render_played_days(int $playerId, int $year): void
{
    player_feast_section_open('Played days');
    ?>
<div class="pm3-cal pm3-cal--hero" data-player-id="<?php echo $playerId; ?>" data-year="<?php echo $year; ?>" aria-label="Calendar activity">
	<p class="pm3-cal__status pm3-muted">Loading calendar…</p>
	<div class="pm3-cal__year"></div>
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
				<a href="game.php?id=<?php echo (int) $t['game_id']; ?>"><?php echo pm_h($t['score']); ?></a>
			</p>
			<p class="pm3-moment__meta">
				<span class="<?php echo pm_h($t['outcome_class']); ?>"><?php echo pm_h($t['outcome']); ?></span>
				· vs <a href="individual1.php?id=<?php echo (int) $t['opponent_id']; ?>"><?php echo pm_h($t['opponent_name']); ?></a>
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
    $uid = 'pm3d-' . $playerId;

    player_feast_section_open('Rating over time');
    ?>
<div class="player-rating-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="player-rating-chart-status pm3d-chart__status">Loading rating history…</p>
	<p class="player-rating-peak-current-summary pm3d-chart__summary" style="display:none;"></p>
	<canvas width="960" height="345" aria-label="ELO rating over time"></canvas>
</div>
    <?php
    player_feast_section_close();

    player_feast_section_open('Games per month');
    ?>
<div class="player-games-month-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="player-games-month-chart-status pm3d-chart__status">Loading games per month…</p>
	<canvas width="960" height="271" aria-label="Games per calendar month"></canvas>
</div>
    <?php
    player_feast_section_close();

    player_feast_section_open('Rating by game number', 'ELO after each rated game — equal spacing, not calendar time.');
    ?>
<div class="player-rating-game-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="player-rating-game-chart-status pm3d-chart__status">Loading…</p>
	<canvas width="960" height="345" aria-label="Rating by game number"></canvas>
</div>
    <?php
    player_feast_section_close();

    player_feast_section_open('Most played opponents', 'Top 20 by rated games — click a bar for head-to-head below.');
    ?>
<div class="player-top-opponents-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="player-top-opponents-chart-status pm3d-chart__status">Loading…</p>
	<canvas width="960" height="591" aria-label="Most played opponents"></canvas>
</div>
    <?php
    player_feast_section_close();

    player_feast_section_open('Head-to-head', 'Cumulative wins vs selected opponent.');
    ?>
<div class="player-head-to-head-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="pm3d-chart__opponent">vs <span class="player-head-to-head-opponent-name">…</span></p>
	<p class="player-head-to-head-meta pm3d-chart__meta"></p>
	<p class="player-head-to-head-chart-status pm3d-chart__status">Waiting for opponent…</p>
	<canvas width="960" height="345" aria-label="Head-to-head cumulative wins"></canvas>
</div>
    <?php
    player_feast_section_close();

    player_feast_section_open('Rating comparison', 'Rating paths vs selected opponent.');
    ?>
<div class="player-compare-rating-chart" data-player-id="<?php echo $playerId; ?>">
	<p class="pm3d-chart__opponent">vs <span class="player-compare-rating-opponent-name">…</span></p>
	<p class="player-compare-rating-meta pm3d-chart__meta"></p>
	<p class="player-compare-rating-chart-status pm3d-chart__status">Waiting for opponent…</p>
	<canvas width="960" height="345" aria-label="Rating comparison"></canvas>
</div>
    <?php
    player_feast_section_close();

    player_feast_section_open('Compare with another player');
    ?>
<div class="player-h2h-opponent-search player-search pm3d-h2h-search" data-player-id="<?php echo $playerId; ?>" data-realm="online" role="search">
	<label class="player-search-label" for="<?php echo pm_h($uid); ?>-h2h">Search player name</label>
	<input id="<?php echo pm_h($uid); ?>-h2h" class="player-search-input player-h2h-search-input" type="search" maxlength="32" autocomplete="off" spellcheck="false" placeholder="Search player name…" />
	<ul class="player-search-results player-h2h-search-results" role="listbox" hidden></ul>
</div>
    <?php
    player_feast_section_close();
}
