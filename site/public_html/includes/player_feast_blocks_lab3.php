<?php
/**
 * Player profile feast blocks — lab3 complete Profile content v1 build.
 *
 * v1 IDs covered here: B01-B03, B06, B07/B08, C01-C05, C12, M01-M03,
 * M08, M09, M12, P01, P02, P05, MS01-MS04, MS08, L01, L02/L04/L07/L08,
 * H01-H03, G01-G04, X01, X04, X05/X06 where earned.
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

function player_feast_rank_badge(?int $rank): string
{
    return $rank !== null && $rank > 0 ? '<span class="pm3-lab3-rank">#' . (int) $rank . '</span>' : '';
}

function player_feast_lab3_games_link(int $playerId, ?int $opponentId = null): string
{
    $href = 'individual3.php?id=' . (int) $playerId;
    if ($opponentId !== null && $opponentId > 0) {
        $href .= '&amp;opponent=' . (int) $opponentId;
    }

    return $href;
}

function player_feast_lab3_render_streak_line(array $pm): void
{
    $streaks = is_array($pm['play_streaks'] ?? null) ? $pm['play_streaks'] : [];
    $day = is_array($streaks['day'] ?? null) ? $streaks['day'] : null;
    $week = is_array($streaks['week'] ?? null) ? $streaks['week'] : null;
    $chosen = null;
    $unit = 'day';

    if ($day && (int) ($day['current'] ?? 0) > 0) {
        $chosen = ['mode' => 'current', 'data' => $day];
        $unit = 'day';
    } elseif ($week && (int) ($week['current'] ?? 0) > 0) {
        $chosen = ['mode' => 'current', 'data' => $week];
        $unit = 'week';
    } else {
        // B07/B08 rule: one play-streak story per page. Lab3 rotates best day/week by player id.
        $preferWeek = ((int) $pm['id'] % 2) === 0;
        $first = $preferWeek ? $week : $day;
        $second = $preferWeek ? $day : $week;
        if ($first && (int) ($first['best'] ?? 0) > 0) {
            $chosen = ['mode' => 'best', 'data' => $first];
            $unit = (string) $first['type'];
        } elseif ($second && (int) ($second['best'] ?? 0) > 0) {
            $chosen = ['mode' => 'best', 'data' => $second];
            $unit = (string) $second['type'];
        }
    }

    if ($chosen === null) {
        return;
    }

    $data = $chosen['data'];
    $mode = $chosen['mode'];
    $isWeek = $unit === 'week';
    $noun = $isWeek ? 'week' : 'day';
    $value = $mode === 'current' ? (int) $data['current'] : (int) $data['best'];
    if ($value < 1) {
        return;
    }
    $date = $mode === 'current' ? (string) ($data['current_anchor'] ?? '') : (string) ($data['best_achieved_at'] ?? '');
    $dateLabel = $date !== '' ? player_feast_lab3_short_date($date) : '';
    ?>
	<p class="pm3-lab3-pulse-line">
		<span>Play streak</span>
		<strong><?php echo number_format($value); ?> <?php echo pm_h($noun); ?><?php echo $value === 1 ? '' : 's'; ?></strong>
		<em><?php echo $mode === 'current' ? 'current run' : 'personal best'; ?><?php echo $dateLabel !== '' ? ' · ' . pm_h($dateLabel) : ''; ?></em>
		<?php if ($value >= ($isWeek ? 4 : 7)) { ?>
		<a href="ranked4.php">Streaks LB</a>
		<?php } ?>
	</p>
    <?php
}

function player_feast_render_presence_lab3(array $pm): void
{
    player_feast_section_open('Presence', 'Still around? Recent activity and one streak beat, without the spreadsheet.');
    ?>
<div class="pm3-lab3-presence">
	<div class="pm3-lab3-presence__lead">
		<span class="pm3-lab3-kicker">Last rated game</span>
		<strong><?php echo pm_h((string) $pm['last_game']); ?></strong>
		<span>Last seen <?php echo pm_h((string) $pm['last_login']); ?></span>
	</div>
	<div class="pm3-lab3-pulse-grid">
		<div class="pm3-lab3-mini">
			<span>This month</span>
			<strong><?php echo number_format((int) $pm['games_this_month']); ?></strong>
			<em>rated games</em>
		</div>
		<div class="pm3-lab3-mini">
			<span>This year</span>
			<strong><?php echo number_format((int) $pm['games_this_year']); ?></strong>
			<em>rated games</em>
		</div>
		<div class="pm3-lab3-mini">
			<span>First game</span>
			<strong><?php echo pm_h((string) $pm['first_game_date']); ?></strong>
			<em><?php echo pm_h((string) $pm['tenure_label']); ?> on ladder</em>
		</div>
	</div>
	<div class="pm3-lab3-pulse-lines">
		<?php if ((int) $pm['winning_streak'] >= 3) { ?>
		<p class="pm3-lab3-pulse-line">
			<span>Hot hand</span>
			<strong><?php echo (int) $pm['winning_streak']; ?> straight wins</strong>
			<em>current rated win streak</em>
		</p>
		<?php } ?>
		<?php player_feast_lab3_render_streak_line($pm); ?>
	</div>
</div>
    <?php
    player_feast_section_close();
}

function player_feast_render_character_lab3(array $pm): void
{
    $bestYear = is_array($pm['best_year_wins'] ?? null) ? $pm['best_year_wins'] : null;
    $days = (int) ($pm['distinct_days_played'] ?? 0);
    $victims = (int) $pm['different_victims'];
    $opponents = (int) $pm['different_opponents'];

    player_feast_section_open('Career character', 'What kind of player? Volume, edge, goals, flair, and the network they built.');
    ?>
<div class="pm3-lab3-character">
	<div class="pm3-lab3-character__tiles">
		<?php
        $tiles = [
            ['label' => 'Rated games', 'value' => number_format((int) $pm['games']), 'rank' => $pm['career_rank_games'] ?? null],
            ['label' => 'Wins', 'value' => number_format((int) $pm['wins']), 'rank' => $pm['career_rank_wins'] ?? null],
            ['label' => 'Goals scored', 'value' => number_format((int) $pm['goals_for']), 'rank' => $pm['career_rank_goals'] ?? null],
            ['label' => 'Double digits', 'value' => number_format((int) $pm['double_digits']), 'rank' => $pm['career_rank_double_digits'] ?? null],
            ['label' => 'Opponents faced', 'value' => number_format($opponents), 'rank' => $pm['career_rank_opponents'] ?? null],
        ];
        foreach ($tiles as $tile) {
            ?>
		<article class="pm3-lab3-career-tile">
			<span><?php echo pm_h($tile['label']); ?></span>
			<strong><?php echo pm_h($tile['value']); ?></strong>
			<?php echo player_feast_rank_badge($tile['rank']); ?>
		</article>
		<?php } ?>
	</div>
	<div class="pm3-lab3-character__lines">
		<?php if ($opponents > 0) { ?>
		<p><strong><?php echo number_format($opponents); ?></strong> opponents faced<?php echo $victims > 0 ? ' · <strong>' . number_format($victims) . '</strong> different victims' : ''; ?>.</p>
		<?php } ?>
		<?php if ($bestYear) { ?>
		<p><strong><?php echo number_format((int) $bestYear['wins']); ?></strong> wins in <?php echo (int) $bestYear['year']; ?> — their best calendar-year haul.</p>
		<?php } ?>
		<?php if ($days > 0) { ?>
		<p><strong><?php echo number_format($days); ?></strong> distinct UTC days with rated games.</p>
		<?php } ?>
	</div>
</div>
    <?php
    player_feast_section_close();
}

function player_feast_render_honours_lab3(array $pm): void
{
    $honours = is_array($pm['honours'] ?? null) ? $pm['honours'] : [];
    $latestMs = is_array($honours['latest_milestone'] ?? null) ? $honours['latest_milestone'] : null;
    $leagueMs = is_array($honours['league_milestone'] ?? null) ? $honours['league_milestone'] : null;
    $latestMedal = is_array($honours['latest_medal'] ?? null) ? $honours['latest_medal'] : null;
    $career = is_array($honours['career_medals'] ?? null) ? $honours['career_medals'] : null;
    $show = !empty($honours['show_strip']);

    player_feast_section_open('Honours', 'Milestones and league recognition in one band: a profile glimpse, not the full garden or history table.');
    if (!$show) {
        ?>
<div class="pm3-lab3-empty">
	<strong>First honours are waiting.</strong>
	<span>Milestones and league medals will light up here as the rated story grows.</span>
</div>
        <?php
        player_feast_section_close();
        return;
    }
    ?>
<div class="pm3-lab3-honours">
	<div class="pm3-lab3-honours__cards">
		<?php if ($latestMs) { ?>
		<article class="pm3-moment pm3-lab3-honour-card">
			<span class="pm3-moment__glyph" aria-hidden="true">&#9733;</span>
			<span class="pm3-moment__tag">Latest unlock</span>
			<h3 class="pm3-moment__label"><?php echo pm_h((string) $latestMs['display_name']); ?></h3>
			<p class="pm3-moment__score"><a href="<?php echo pm_h((string) $latestMs['detail_href']); ?>"><?php echo pm_h((string) $latestMs['achieved_label']); ?></a></p>
			<p class="pm3-moment__meta">Milestone garden glimpse</p>
		</article>
		<?php } ?>
		<?php if ($leagueMs) { ?>
		<article class="pm3-moment pm3-lab3-honour-card">
			<span class="pm3-moment__glyph" aria-hidden="true">&#9670;</span>
			<span class="pm3-moment__tag"><?php echo pm_h((string) ($leagueMs['league_label'] ?: 'League milestone')); ?></span>
			<h3 class="pm3-moment__label"><?php echo pm_h((string) $leagueMs['display_name']); ?></h3>
			<p class="pm3-moment__score"><a href="<?php echo pm_h((string) $leagueMs['detail_href']); ?>"><?php echo pm_h((string) $leagueMs['achieved_label']); ?></a></p>
			<p class="pm3-moment__meta">League-tied unlock</p>
		</article>
		<?php } ?>
		<?php if ($latestMedal) { ?>
		<article class="pm3-moment pm3-lab3-honour-card pm3-lab3-honour-card--medal">
			<span class="pm3-moment__glyph" aria-hidden="true"><?php echo player_feast_lab3_medal_entity((string) $latestMedal['medal']); ?></span>
			<span class="pm3-moment__tag">Latest medal</span>
			<h3 class="pm3-moment__label"><?php echo pm_h((string) $latestMedal['medal_label']); ?></h3>
			<p class="pm3-moment__score"><a href="<?php echo pm_h((string) $latestMedal['href']); ?>"><?php echo pm_h((string) $latestMedal['league_label']); ?></a></p>
			<p class="pm3-moment__meta"><?php echo pm_h((string) $latestMedal['period_label']); ?></p>
		</article>
		<?php } ?>
	</div>
	<div class="pm3-lab3-honours__summary">
		<?php if (($honours['signature_label'] ?? '') !== '') { ?>
		<p><strong><?php echo pm_h((string) $honours['signature_label']); ?></strong> among milestone unlocks.</p>
		<?php } ?>
		<?php if ((int) ($honours['unlocks_12mo'] ?? 0) > 0) { ?>
		<p><strong><?php echo number_format((int) $honours['unlocks_12mo']); ?></strong> milestone unlock<?php echo (int) $honours['unlocks_12mo'] === 1 ? '' : 's'; ?> in the last 12 months.</p>
		<?php } ?>
		<?php if ($career && (int) $career['podiums'] > 0) { ?>
		<p><strong><?php echo number_format((int) $career['gold']); ?></strong> gold · <strong><?php echo number_format((int) $career['silver']); ?></strong> silver · <strong><?php echo number_format((int) $career['bronze']); ?></strong> bronze.</p>
		<p><strong><?php echo number_format((int) $career['wins']); ?></strong> league win<?php echo (int) $career['wins'] === 1 ? '' : 's'; ?> across daily, weekly, monthly, and yearly races.</p>
		<a class="pm3-lab3-chip" href="ranked9.php?cup=overall">League honours</a>
		<?php } ?>
		<a class="pm3-lab3-chip" href="individual_milestones.php?id=<?php echo (int) $pm['id']; ?>">Milestone garden</a>
	</div>
</div>
    <?php
    player_feast_section_close();
}

function player_feast_lab3_medal_entity(string $medal): string
{
    return match ($medal) {
        'gold' => '&#129351;',
        'silver' => '&#129352;',
        'bronze' => '&#129353;',
        default => '&#9679;',
    };
}

function player_feast_render_played_days(int $playerId, string $firstGameDateYmd): void
{
    $fromAttr = preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstGameDateYmd) ? $firstGameDateYmd : date('Y-m-d');
    player_feast_section_open('Played days', 'UTC calendar days with at least one rated game, from the first rated game through today.');
    ?>
<div class="pm3-cal pm3-cal--hero pm3-cal--days pm3-cal--year-pick" data-player-id="<?php echo $playerId; ?>" data-first-game-date="<?php echo pm_h($fromAttr); ?>" aria-label="Calendar activity">
	<p class="pm3-cal__status pm3-muted">Loading calendar...</p>
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
	<p class="pm3-cal__status pm3-muted">Loading weeks...</p>
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

function player_feast_render_peak_activity(array $pm): void
{
    $b = player_feast_peak_busiest($pm);
    $bd = $b['day'];
    $bm = $b['month'];
    $by = $b['year'];

    player_feast_section_open('Personal bests', 'Most rated games in a single day, month, and calendar year.');
    ?>
<div class="pm3-busiest pm3-busiest--inline pm3hij-peak pm3hij-peak--cards pm3-lab3-bests">
	<ol class="pm3-busiest__list">
		<li>
			<span class="pm3-busiest__kind">Best day</span>
			<strong><?php echo $bd ? (int) $bd['count'] : '-'; ?></strong>
			<em><?php echo $bd ? pm_h(pm2_format_busiest_day((string) $bd['key'])) : 'First burst awaits'; ?></em>
		</li>
		<li>
			<span class="pm3-busiest__kind">Best month</span>
			<strong><?php echo $bm ? (int) $bm['count'] : '-'; ?></strong>
			<em><?php echo $bm ? pm_h(pm2_format_busiest_month((string) $bm['key'])) : 'Still to come'; ?></em>
		</li>
		<li>
			<span class="pm3-busiest__kind">Best year</span>
			<strong><?php echo $by ? (int) $by['count'] : '-'; ?></strong>
			<em><?php echo $by ? pm_h((string) $by['key']) : 'Story building'; ?></em>
		</li>
	</ol>
</div>
    <?php
    player_feast_section_close();
}

function player_feast_render_moments(array $pm): void
{
    $maxVictim = is_array($pm['max_rated_victim'] ?? null) ? $pm['max_rated_victim'] : null;
    $fave = is_array($pm['favourite_victim'] ?? null) ? $pm['favourite_victim'] : null;
    $hasMoments = ((int) $pm['longest_win_streak'] > 0) || !empty($pm['trophies']) || $maxVictim !== null;

    player_feast_section_open('Moments', 'Specific games and matchups people remember.');
    if (!$hasMoments) {
        ?>
<div class="pm3-lab3-empty">
	<strong>First trophy game awaits.</strong>
	<span>As rated games arrive, streaks, upsets, and standout scores will appear here.</span>
</div>
        <?php
        player_feast_section_close();
        return;
    }
    ?>
<div class="pm3-moments pm3-moments--mosaic pm3-lab3-moments">
	<div class="pm3-moments__grid">
		<article class="pm3-moment pm3-moment--streak">
			<span class="pm3-moment__glyph" aria-hidden="true">&#127942;</span>
			<span class="pm3-moment__tag">Streak</span>
			<h3 class="pm3-moment__label">Longest win run</h3>
			<p class="pm3-moment__score"><?php echo (int) $pm['longest_win_streak']; ?> wins</p>
		</article>
		<?php if ($maxVictim !== null) { ?>
		<article class="pm3-moment">
			<span class="pm3-moment__glyph" aria-hidden="true"><?php echo $maxVictim['icon']; ?></span>
			<span class="pm3-moment__tag"><?php echo pm_h((string) $maxVictim['tag']); ?></span>
			<h3 class="pm3-moment__label"><?php echo pm_h((string) $maxVictim['label']); ?></h3>
			<p class="pm3-moment__score">
				<a href="game.php?id=<?php echo (int) $maxVictim['game_id']; ?>"><?php echo pm_h((string) $maxVictim['score']); ?></a>
			</p>
			<p class="pm3-moment__meta">
				<span class="<?php echo pm_h((string) $maxVictim['outcome_class']); ?>"><?php echo pm_h((string) $maxVictim['outcome']); ?></span>
				· vs <a href="individual1.php?id=<?php echo (int) $maxVictim['opponent_id']; ?>"><?php echo pm_h((string) $maxVictim['opponent_name']); ?></a>
				<?php if ((int) $maxVictim['opponent_rating'] > 0) { ?> · opp <?php echo (int) $maxVictim['opponent_rating']; ?><?php } ?>
				· <?php echo pm_h((string) $maxVictim['date']); ?>
			</p>
		</article>
		<?php } ?>
		<?php foreach (array_slice($pm['trophies'], 0, $maxVictim ? 4 : 5) as $t) { ?>
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
	<?php if ($fave !== null && (int) $fave['wins'] > 0) { ?>
	<p class="pm3-lab3-favourite">
		Favourite victim:
		<a href="<?php echo player_feast_lab3_games_link((int) $pm['id'], (int) $fave['opponent_id']); ?>"><?php echo pm_h((string) $fave['opponent_name']); ?></a>
		has been beaten <strong><?php echo number_format((int) $fave['wins']); ?></strong> time<?php echo (int) $fave['wins'] === 1 ? '' : 's'; ?>.
	</p>
	<?php } ?>
</div>
    <?php
    player_feast_section_close();
}

function player_feast_render_charts(int $playerId, array $pm = []): void
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
		<p class="player-rating-chart-status pm3d-chart__status k2-chart-panel__status">Loading rating history...</p>
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
		<p class="player-games-month-chart-status pm3d-chart__status k2-chart-panel__status">Loading games per month...</p>
		<div class="k2-chart-frame">
			<canvas aria-label="Games per calendar month"></canvas>
		</div>
	</div>
</div>
    <?php
    player_feast_section_close();

    player_feast_section_open('Matchups', 'Main opponent context first; charts remain opt-in depth.');
    $rival = is_array($pm['featured_rival'] ?? null) ? $pm['featured_rival'] : null;
    if ($rival !== null && (int) $rival['games'] > 0) {
        ?>
<p class="pm3-lab3-rivalry">
	Most-played opponent:
	<a href="individual1.php?id=<?php echo (int) $rival['opponent_id']; ?>"><?php echo pm_h((string) $rival['opponent_name']); ?></a>
	— <strong><?php echo number_format((int) $rival['wins']); ?></strong>W
	· <strong><?php echo number_format((int) $rival['draws']); ?></strong>D
	· <strong><?php echo number_format((int) $rival['losses']); ?></strong>L
	across <?php echo number_format((int) $rival['games']); ?> games.
	<a href="<?php echo player_feast_lab3_games_link($playerId, (int) $rival['opponent_id']); ?>">All games vs <?php echo pm_h((string) $rival['opponent_name']); ?></a>
</p>
        <?php
    }
    ?>
<div class="pm3d-matchups">
	<div class="player-top-opponents-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
		<h3 class="k2-panel-heading">Most frequent opponents</h3>
		<p class="k2-chart-block__hint">Click a bar to compare against that opponent below.</p>
		<p class="player-top-opponents-chart-status pm3d-chart__status k2-chart-panel__status">Loading top opponents...</p>
		<canvas class="player-top-opponents-canvas" aria-label="Most played opponents"></canvas>
	</div>
	<h3 class="pm3d-matchups__subtitle">Head-to-head</h3>
	<div class="player-head-to-head-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>">
		<p class="pm3d-chart__opponent">vs <span class="player-head-to-head-opponent-name">...</span></p>
		<p class="player-head-to-head-meta pm3d-chart__meta"></p>
		<p class="player-head-to-head-chart-status pm3d-chart__status k2-chart-panel__status">Waiting for opponent...</p>
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
			<p class="pm3d-chart__opponent">vs <span class="player-compare-rating-opponent-name">...</span></p>
		</div>
		<p class="player-compare-rating-meta pm3d-chart__meta"></p>
		<p class="player-compare-rating-chart-status pm3d-chart__status k2-chart-panel__status">Waiting for opponent...</p>
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
		<input id="<?php echo pm_h($uid); ?>-h2h" class="player-search-input player-h2h-search-input" type="search" maxlength="32" autocomplete="off" spellcheck="false" placeholder="Search player name..." />
		<ul class="player-search-results player-h2h-search-results" role="listbox" hidden></ul>
	</div>
</div>
    <?php
    player_feast_section_close();
}
