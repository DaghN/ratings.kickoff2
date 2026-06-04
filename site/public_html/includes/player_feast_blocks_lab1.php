<?php
/**
 * Player profile feast blocks — lab Agent 1 (individual1-profile-lab1.php).
 */
if (!defined('PLAYER_FEAST_LAB1_M08_MIN_WINS')) {
    define('PLAYER_FEAST_LAB1_M08_MIN_WINS', 5);
}

function player_feast_render_lab_banner(int $agentNumber = 1): void
{
    ?>
<p class="k2-lab-banner pm3-muted" role="note">
	Profile lab preview — Agent <?php echo (int) $agentNumber; ?> — not production
</p>
    <?php
}

function player_feast_lab1_tier_glyph(string $tierBand): string
{
    return match ($tierBand) {
        'legendary' => '✦',
        'key' => '◆',
        'veteran' => '●',
        default => '○',
    };
}

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

/** Personal bests — busiest day, month, and year + P02 best-year ticker. */
function player_feast_render_peak_activity(array $pm): void
{
    $b = player_feast_peak_busiest($pm);
    $bd = $b['day'];
    $bm = $b['month'];
    $by = $b['year'];
    $bestYear = $pm['best_year'] ?? null;

    player_feast_section_open('Personal bests', 'Most rated games in a single day, month, and calendar year.');
    if (is_array($bestYear) && (int) $bestYear['wins'] > 0) {
        ?>
<p class="pm3-feast-line pm3-muted">
	Won <strong><?php echo (int) $bestYear['wins']; ?></strong> rated games in <strong><?php echo (int) $bestYear['year']; ?></strong> — his best calendar year by wins.
</p>
        <?php
    }
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
    $playerId = (int) ($pm['id'] ?? 0);
    $trophies = is_array($pm['trophies'] ?? null) ? $pm['trophies'] : [];
    $showM03 = !empty($pm['show_m03_card']) && is_array($pm['max_rated_victim'] ?? null);
    $m03 = $showM03 ? $pm['max_rated_victim'] : null;
    $hasStreak = (int) ($pm['longest_win_streak'] ?? 0) > 0;
    $cardCount = ($hasStreak ? 1 : 0) + count($trophies) + ($m03 !== null ? 1 : 0);
    $fav = is_array($pm['favourite_victim'] ?? null) ? $pm['favourite_victim'] : null;

    player_feast_section_open('Moments');
    if ($fav !== null && (int) $fav['wins'] >= PLAYER_FEAST_LAB1_M08_MIN_WINS) {
        ?>
<p class="pm3-feast-line pm3-muted">
	His favourite victim is <a href="individual1-profile-lab1.php?id=<?php echo (int) $fav['opponent_id']; ?>"><?php echo pm_h((string) $fav['opponent_name']); ?></a>
	— beaten <strong><?php echo (int) $fav['wins']; ?></strong> times
	<span class="pm3-feast-line__sub">(<?php echo (int) $fav['games']; ?> meetings)</span>.
	<a class="pm3-feast-line__action" href="individual3.php?id=<?php echo $playerId; ?>&amp;opponent=<?php echo (int) $fav['opponent_id']; ?>">All games vs <?php echo pm_h((string) $fav['opponent_name']); ?></a>
</p>
        <?php
    }

    if ($cardCount < 1) {
        ?>
<p class="pm3-moments-empty pm3-muted">The ladder is waiting — first trophy games and streak cards will land here.</p>
        <?php
        player_feast_section_close();

        return;
    }
    ?>
<div class="pm3-moments pm3-moments--mosaic">
	<div class="pm3-moments__grid">
		<?php if ($hasStreak) { ?>
		<article class="pm3-moment pm3-moment--streak">
			<span class="pm3-moment__glyph" aria-hidden="true">🏆</span>
			<span class="pm3-moment__tag">Streak</span>
			<h3 class="pm3-moment__label">Longest win run</h3>
			<p class="pm3-moment__score"><?php echo (int) $pm['longest_win_streak']; ?> wins</p>
		</article>
		<?php } ?>
		<?php if ($m03 !== null) { ?>
		<article class="pm3-moment pm3-moment--giant">
			<span class="pm3-moment__glyph" aria-hidden="true">🗡</span>
			<span class="pm3-moment__tag">Giant killing</span>
			<h3 class="pm3-moment__label">Beat a rated giant</h3>
			<p class="pm3-moment__score">
				<a href="game.php?id=<?php echo (int) $m03['game_id']; ?>"><?php echo pm_h($m03['score']); ?></a>
			</p>
			<p class="pm3-moment__meta">
				<span class="<?php echo pm_h($m03['outcome_class']); ?>"><?php echo pm_h($m03['outcome']); ?></span>
				· vs <a href="individual1-profile-lab1.php?id=<?php echo (int) $m03['opponent_id']; ?>"><?php echo pm_h($m03['opponent_name']); ?></a>
				· <?php echo (int) $m03['opponent_rating']; ?> Elo
				· <?php echo pm_h($m03['date']); ?>
			</p>
		</article>
		<?php } ?>
		<?php
        $shown = 0;
    foreach ($trophies as $t) {
        if ($shown >= 6) {
            break;
        }
        $shown++;
        ?>
		<article class="pm3-moment">
			<span class="pm3-moment__glyph" aria-hidden="true"><?php echo $t['icon']; ?></span>
			<span class="pm3-moment__tag"><?php echo pm_h($t['tag']); ?></span>
			<h3 class="pm3-moment__label"><?php echo pm_h($t['label']); ?></h3>
			<p class="pm3-moment__score">
				<a href="game.php?id=<?php echo (int) $t['game_id']; ?>"><?php echo pm_h($t['score']); ?></a>
			</p>
			<p class="pm3-moment__meta">
				<span class="<?php echo pm_h($t['outcome_class']); ?>"><?php echo pm_h($t['outcome']); ?></span>
				· vs <a href="individual1-profile-lab1.php?id=<?php echo (int) $t['opponent_id']; ?>"><?php echo pm_h($t['opponent_name']); ?></a>
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
<table class="pm3efg-stat-table pm3efg-stat-table--career pm3efg-stat-table--career-inline-rank">
	<tbody>
	<?php foreach ($rows as $row) {
	    $rank = $row['rank'] ?? null;
	    $value = (string) $row['value'];
	    if ($rank !== null && $rank > 0) {
	        $value .= ' (#' . (int) $rank . ')';
	    }
	    ?>
		<tr class="pm3efg-stat-table__row">
			<th scope="row"><?php echo pm_h($row['label']); ?></th>
			<td class="pm3efg-career-stats__num" colspan="2"><?php echo pm_h($value); ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
    <?php
}

/** Presence + Career side by side (B1 pulse + B2 character). */
function player_feast_render_presence_career_duo(array $pm): void
{
    $winStreak = (int) ($pm['winning_streak'] ?? 0);
    $playStreak = is_array($pm['play_streak'] ?? null) ? $pm['play_streak'] : ['show' => false];
    $distinctDays = (int) ($pm['distinct_days_played'] ?? 0);
    $victims = (int) ($pm['different_victims'] ?? 0);
    $opponents = (int) ($pm['different_opponents'] ?? 0);

    player_feast_section_open('');
    ?>
<div class="pm3efg-duo pm3efg-duo--g">
	<div class="pm3efg-duo__panel pm3efg-duo__panel--presence">
		<h3 class="pm3efg-duo__panel-title">Presence</h3>
		<?php player_feast_render_stat_table_rows(player_feast_presence_stat_rows($pm)); ?>
		<?php if ($winStreak >= 3) { ?>
		<p class="pm3-feast-line pm3-feast-line--pulse">
			<span class="pm3-feast-line__tag">Hot hand</span>
			<?php echo (int) $winStreak; ?> wins in a row right now.
		</p>
		<?php } ?>
		<?php if (!empty($playStreak['show']) && ($playStreak['line'] ?? '') !== '') { ?>
		<p class="pm3-feast-line pm3-feast-line--pulse pm3-muted">
			<span class="pm3-feast-line__tag">Play streak</span>
			<?php echo pm_h((string) $playStreak['line']); ?>
			<?php if (!empty($playStreak['streak_lb_href'])) { ?>
			<a class="pm3-feast-line__action" href="<?php echo pm_h((string) $playStreak['streak_lb_href']); ?>">Streaks leaderboard</a>
			<?php } ?>
		</p>
		<?php } ?>
	</div>
	<div class="pm3efg-duo__panel pm3efg-duo__panel--career">
		<h3 class="pm3efg-duo__panel-title">Career</h3>
		<?php player_feast_render_career_stats_table(player_feast_career_stat_rows($pm)); ?>
		<?php if ($distinctDays > 0) { ?>
		<p class="pm3-feast-line pm3-muted">
			Played on <strong><?php echo number_format($distinctDays); ?></strong> distinct UTC days — a lifetime of showing up.
		</p>
		<?php } ?>
		<?php if ($opponents > 0 && $victims > 0) { ?>
		<p class="pm3-feast-line pm3-muted">
			<?php echo number_format($opponents); ?> opponents faced · <?php echo number_format($victims); ?> different victims.
		</p>
		<?php } ?>
	</div>
</div>
    <?php
    player_feast_section_close();
}

/** B3 Recognition — milestones + league honours strip. */
function player_feast_render_honours(array $pm): void
{
    $honours = is_array($pm['honours'] ?? null) ? $pm['honours'] : [];
    $games = (int) ($pm['games'] ?? 0);
    $playerId = (int) ($pm['id'] ?? 0);

    if ($games < 1) {
        return;
    }

    if (empty($honours['show_strip'])) {
        if (!empty($pm['display'])) {
            player_feast_section_open('Honours', 'Milestones and league medals appear here once the ladder has something to celebrate.');
            ?>
<p class="pm3-muted">No milestone unlocks or league medals yet — keep playing.</p>
            <?php
            player_feast_section_close();
        }

        return;
    }

    $latestMs = $honours['latest_milestone'] ?? null;
    $leagueMs = $honours['league_milestone'] ?? null;
    $latestMedal = $honours['latest_medal'] ?? null;
    $career = $honours['career_medals'] ?? null;

    player_feast_section_open('Honours', 'Community marks — milestones and league recognition at a glance.');
    ?>
<div class="pm3-honours">
	<div class="pm3-honours__grid">
		<?php if (is_array($latestMs)) { ?>
		<article class="pm3-moment pm3-moment--honour pm3-moment--honour-ms">
			<span class="pm3-moment__glyph" aria-hidden="true"><?php echo player_feast_lab1_tier_glyph((string) $latestMs['tier_band']); ?></span>
			<span class="pm3-moment__tag">Latest unlock</span>
			<h3 class="pm3-moment__label"><a href="<?php echo pm_h((string) $latestMs['detail_href']); ?>"><?php echo pm_h((string) $latestMs['display_name']); ?></a></h3>
			<p class="pm3-moment__meta"><?php echo pm_h((string) $latestMs['achieved_label']); ?></p>
		</article>
		<?php } ?>
		<?php if (is_array($leagueMs)) { ?>
		<article class="pm3-moment pm3-moment--honour pm3-moment--honour-league">
			<span class="pm3-moment__glyph" aria-hidden="true">🏅</span>
			<span class="pm3-moment__tag"><?php echo pm_h((string) $leagueMs['league_label']); ?></span>
			<h3 class="pm3-moment__label"><a href="<?php echo pm_h((string) $leagueMs['detail_href']); ?>"><?php echo pm_h((string) $leagueMs['display_name']); ?></a></h3>
			<p class="pm3-moment__meta">League milestone · <?php echo pm_h((string) $leagueMs['achieved_label']); ?></p>
		</article>
		<?php } ?>
		<?php if (is_array($latestMedal)) {
		    $medalClass = 'pm3-honours-medal--' . preg_replace('/[^a-z]/', '', (string) $latestMedal['medal']);
		    ?>
		<article class="pm3-honours-medal <?php echo pm_h($medalClass); ?>">
			<p class="pm3-honours-medal__title">Latest league medal</p>
			<p class="pm3-honours-medal__score"><?php echo pm_h((string) $latestMedal['medal_label']); ?></p>
			<p class="pm3-honours-medal__meta"><?php echo pm_h((string) $latestMedal['league_label']); ?> · <?php echo pm_h((string) $latestMedal['period_label']); ?></p>
		</article>
		<?php } ?>
	</div>
	<ul class="pm3-honours__stats pm3-muted">
		<?php if (($honours['signature_label'] ?? '') !== '') { ?>
		<li><?php echo pm_h((string) $honours['signature_label']); ?></li>
		<?php } ?>
		<?php if ((int) ($honours['unlocks_12mo'] ?? 0) > 0) { ?>
		<li><strong><?php echo (int) $honours['unlocks_12mo']; ?></strong> milestone unlock<?php echo (int) $honours['unlocks_12mo'] === 1 ? '' : 's'; ?> in the last 12 months</li>
		<?php } ?>
		<?php if (is_array($career) && (int) $career['podiums'] > 0) { ?>
		<li>Career league podium: <strong><?php echo (int) $career['gold']; ?></strong> gold · <strong><?php echo (int) $career['silver']; ?></strong> silver · <strong><?php echo (int) $career['bronze']; ?></strong> bronze</li>
		<?php } ?>
		<?php if ((int) ($honours['league_wins'] ?? 0) > 0) { ?>
		<li><strong><?php echo (int) $honours['league_wins']; ?></strong> league win<?php echo (int) $honours['league_wins'] === 1 ? '' : 's'; ?> across all competitions</li>
		<?php } ?>
	</ul>
	<p class="pm3-honours__links">
		<a href="<?php echo pm_h((string) ($honours['garden_href'] ?? 'individual_milestones.php?id=' . $playerId)); ?>">Milestone garden</a>
		· <a href="<?php echo pm_h((string) ($honours['honours_href'] ?? 'ranked9.php?cup=overall')); ?>">League honours</a>
	</p>
</div>
    <?php
    player_feast_section_close();
}

function player_feast_render_charts(array $pm): void
{
    $playerId = (int) ($pm['id'] ?? 0);
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

    $rival = is_array($pm['featured_rival'] ?? null) ? $pm['featured_rival'] : null;
    $playerIdForCharts = $playerId;
    player_feast_section_open('Matchups', 'Pick a frequent opponent to update the head-to-head and rating comparison graphs.');
    if ($rival !== null && (int) $rival['games'] >= 3) {
        ?>
<p class="pm3-feast-line pm3-feast-line--rival pm3d-chart__meta">
	Main rival <a href="individual1-profile-lab1.php?id=<?php echo (int) $rival['opponent_id']; ?>"><?php echo pm_h((string) $rival['opponent_name']); ?></a>:
	<strong><?php echo (int) $rival['wins']; ?></strong>–<strong><?php echo (int) $rival['draws']; ?></strong>–<strong><?php echo (int) $rival['losses']; ?></strong>
	<span class="pm3-muted">(<?php echo (int) $rival['games']; ?> rated games)</span>
	<a class="pm3-feast-line__action" href="individual3.php?id=<?php echo $playerIdForCharts; ?>&amp;opponent=<?php echo (int) $rival['opponent_id']; ?>">All games vs <?php echo pm_h((string) $rival['opponent_name']); ?></a>
</p>
        <?php
    }
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
