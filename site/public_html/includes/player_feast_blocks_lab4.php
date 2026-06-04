<?php
/**
 * Profile feast blocks — Agent 4 lab fork (individual1-profile-lab4.php).
 *
 * B1/B2 rethink: the production pm3efg-duo stat tables are NOT used here.
 * Presence reads as an open-background "pulse strip"; Career reads as a
 * highlight number + character prose + chip rail (ranks inline, no rank
 * column). Honours composes milestone + league snippets into one band.
 * Story before analyst charts throughout.
 */

/* ── Section chrome (own copy; production blocks file is not required) ── */

function player_feast_lab4_section_open(string $title, ?string $hint = null, string $extraClass = ''): void
{
    $cls = 'pm3d-section lab4-section';
    if ($extraClass !== '') {
        $cls .= ' ' . $extraClass;
    }
    ?>
<section class="<?php echo pm_h($cls); ?>">
	<?php if ($title !== '') { ?>
	<h2 class="k2-panel-heading pm3d-section__title"><?php echo pm_h($title); ?></h2>
	<?php } ?>
	<?php if ($hint !== null && $hint !== '') { ?>
	<p class="pm3d-section__hint"><?php echo pm_h($hint); ?></p>
	<?php } ?>
	<div class="pm3d-section__content">
    <?php
}

function player_feast_lab4_section_close(): void
{
    ?>
	</div>
</section>
    <?php
}

/* ── Lab banner ── */

function player_feast_lab4_render_banner(): void
{
    ?>
<p class="k2-lab-banner lab4-banner" role="note">Profile lab preview — Agent 4 — not production</p>
    <?php
}

/* ════════════════════════════════════════════════════════════════════
 * B1 — Presence (pulse strip, open background)
 * ════════════════════════════════════════════════════════════════════ */

function player_feast_lab4_render_presence(array $pm): void
{
    $playerId = (int) $pm['id'];
    $display = !empty($pm['display']);
    $games = (int) $pm['games'];
    $winStreak = (int) ($pm['winning_streak'] ?? 0);
    $streakStory = player_feast_lab4_streak_story($pm, $playerId);

    player_feast_lab4_section_open('Presence', 'Still around? — the recent pulse of this player on the ladder.');
    ?>
<div class="lab4-pulse">
	<p class="lab4-pulse__lead">
    <?php if ($games < 1) { ?>
		Newcomer — <strong>no rated games yet</strong>. The ladder is waiting.
    <?php } else { ?>
		Last rated game <strong><?php echo pm_h((string) $pm['last_game']); ?></strong>
		· last seen online <?php echo pm_h((string) $pm['last_login']); ?>
		· on the ladder since <?php echo pm_h((string) $pm['first_game_date']); ?>
    <?php } ?>
	</p>
    <?php if ($games >= 1) { ?>
	<ul class="lab4-pulse__chips">
		<li class="lab4-chip">
			<span class="lab4-chip__num"><?php echo number_format((int) $pm['games_this_month']); ?></span>
			<span class="lab4-chip__label">games this month</span>
		</li>
		<li class="lab4-chip">
			<span class="lab4-chip__num"><?php echo number_format((int) $pm['games_this_year']); ?></span>
			<span class="lab4-chip__label">games this year</span>
		</li>
        <?php if ($winStreak >= 3) { ?>
		<li class="lab4-chip lab4-chip--hot">
			<span class="lab4-chip__num">🔥 <?php echo $winStreak; ?></span>
			<span class="lab4-chip__label">win streak right now</span>
		</li>
        <?php } ?>
        <?php if ($streakStory !== null) { ?>
		<li class="lab4-chip lab4-chip--streak">
			<span class="lab4-chip__num"><?php echo pm_h($streakStory['value']); ?></span>
			<span class="lab4-chip__label"><a href="ranked4.php"><?php echo pm_h($streakStory['label']); ?></a></span>
		</li>
        <?php } ?>
	</ul>
    <?php } ?>
</div>
    <?php
    player_feast_lab4_section_close();
}

/**
 * B07/B08 — one play-streak story per load. Day vs week alternates on
 * player_id parity (cache-friendly, no session); the live run is preferred,
 * otherwise the personal best with its date.
 *
 * @return array{value: string, label: string}|null
 */
function player_feast_lab4_streak_story(array $pm, int $playerId): ?array
{
    $useWeek = ($playerId % 2) === 1;
    $primary = $useWeek ? ($pm['lab4']['streak_week'] ?? null) : ($pm['lab4']['streak_day'] ?? null);
    $fallback = $useWeek ? ($pm['lab4']['streak_day'] ?? null) : ($pm['lab4']['streak_week'] ?? null);
    $unit = $useWeek ? 'week' : 'day';
    $fallbackUnit = $useWeek ? 'day' : 'week';

    $story = player_feast_lab4_streak_story_from($primary, $unit);
    if ($story !== null) {
        return $story;
    }

    return player_feast_lab4_streak_story_from($fallback, $fallbackUnit);
}

/**
 * @param array{current: int, best: int, best_date: string}|null $streak
 * @return array{value: string, label: string}|null
 */
function player_feast_lab4_streak_story_from(?array $streak, string $unit): ?array
{
    if ($streak === null) {
        return null;
    }
    $current = (int) ($streak['current'] ?? 0);
    $best = (int) ($streak['best'] ?? 0);
    if ($current >= 2) {
        return [
            'value' => $current . '-' . $unit,
            'label' => 'play run, going now',
        ];
    }
    if ($best >= 2) {
        $when = (string) ($streak['best_date'] ?? '');
        return [
            'value' => $best . '-' . $unit,
            'label' => $when !== '' ? 'best play run · ' . $when : 'best play run',
        ];
    }

    return null;
}

/* ════════════════════════════════════════════════════════════════════
 * B2 — Career character (highlight + prose + chip rail, no stat table)
 * ════════════════════════════════════════════════════════════════════ */

function player_feast_lab4_render_career(array $pm): void
{
    $games = (int) $pm['games'];
    if ($games < 1) {
        player_feast_lab4_section_open('Career', 'What kind of player? — the character behind the numbers.');
        ?>
<p class="lab4-empty">No rated games yet — this story writes itself the moment they take the pitch.</p>
        <?php
        player_feast_lab4_section_close();

        return;
    }

    $display = !empty($pm['display']);
    $distinctDays = (int) ($pm['lab4']['distinct_days'] ?? 0);
    $opponents = (int) $pm['different_opponents'];
    $victims = (int) ($pm['different_victims'] ?? 0);
    $fav = $pm['lab4']['favourite_victim'] ?? null;
    $bestYear = $pm['lab4']['best_year'] ?? null;

    player_feast_lab4_section_open('Career', 'What kind of player? — the character behind the numbers.');
    ?>
<div class="lab4-character">
    <?php if ($distinctDays > 0) { ?>
	<div class="lab4-character__highlight">
		<span class="lab4-character__num"><?php echo number_format($distinctDays); ?></span>
		<span class="lab4-character__highlight-label">distinct days played on the ladder</span>
	</div>
    <?php } ?>
	<p class="lab4-character__line">
		<strong><?php echo number_format($games); ?></strong> rated games
		· <strong><?php echo number_format($opponents); ?></strong> opponents faced
        <?php if ($victims > 0) { ?>
		· beaten <strong><?php echo number_format($victims); ?></strong> different rivals
        <?php } ?>
        <?php if ($fav !== null) { ?>
		· favourite victim
		<a href="individual1.php?id=<?php echo (int) $fav['id']; ?>"><?php echo pm_h((string) $fav['name']); ?></a>,
		beaten <strong><?php echo number_format((int) $fav['wins']); ?></strong> times
        <?php } ?>
	</p>
    <?php if ($bestYear !== null) { ?>
	<p class="lab4-character__ticker">
		Best year on record — won <strong><?php echo number_format((int) $bestYear['wins']); ?></strong>
		games in <?php echo pm_h((string) $bestYear['year']); ?>.
	</p>
    <?php } ?>
	<ul class="lab4-statchips">
        <?php
        foreach (player_feast_lab4_career_chips($pm, $display) as $chip) {
            ?>
		<li class="lab4-statchip">
			<span class="lab4-statchip__num"><?php echo pm_h($chip['value']); ?></span>
			<span class="lab4-statchip__label"><?php echo pm_h($chip['label']); ?></span>
            <?php if ($chip['rank'] !== null) { ?>
			<span class="lab4-statchip__rank">#<?php echo (int) $chip['rank']; ?></span>
            <?php } ?>
		</li>
            <?php
        }
        ?>
	</ul>
</div>
    <?php
    player_feast_lab4_section_close();
}

/**
 * C01–C05 as chips. (#rank) is rethought: an inline accent badge shown only
 * when the player is on the ladder (Display = 1), never a table column.
 *
 * @return array<int, array{label: string, value: string, rank: ?int}>
 */
function player_feast_lab4_career_chips(array $pm, bool $display): array
{
    return [
        ['label' => 'Rated games', 'value' => number_format((int) $pm['games']), 'rank' => $display ? ($pm['career_rank_games'] ?? null) : null],
        ['label' => 'Wins', 'value' => number_format((int) $pm['wins']), 'rank' => $display ? ($pm['career_rank_wins'] ?? null) : null],
        ['label' => 'Goals scored', 'value' => number_format((int) $pm['goals_for']), 'rank' => $display ? ($pm['career_rank_goals'] ?? null) : null],
        ['label' => 'Double digits', 'value' => number_format((int) $pm['double_digits']), 'rank' => $display ? ($pm['career_rank_double_digits'] ?? null) : null],
        ['label' => 'Opponents', 'value' => number_format((int) $pm['different_opponents']), 'rank' => $display ? ($pm['career_rank_opponents'] ?? null) : null],
    ];
}

/* ════════════════════════════════════════════════════════════════════
 * B3 — Honours (milestone + league snippets in one band)
 * ════════════════════════════════════════════════════════════════════ */

function player_feast_lab4_render_honours(array $pm): void
{
    $ms = $pm['lab4']['milestones'] ?? ['ready' => false];
    $lg = $pm['lab4']['league'] ?? ['ready' => false];
    $counts = $pm['heroMilestoneCounts'] ?? null;
    $catalogTotal = (int) ($pm['heroMsCatalogTotal'] ?? 0);
    $games = (int) $pm['games'];

    $hasMilestones = !empty($ms['ready']) && (!empty($ms['latest']) || ($counts !== null && (int) ($counts['total'] ?? 0) > 0));
    $hasLeague = !empty($lg['ready']) && (!empty($lg['latest']) || (int) ($lg['wins'] ?? 0) > 0 || (int) ($lg['podiums'] ?? 0) > 0);

    if (!$hasMilestones && !$hasLeague) {
        if ($games < 1) {
            return;
        }
        player_feast_lab4_section_open('Honours', 'Community marks — milestones unlocked and league medals won.');
        ?>
<p class="lab4-empty">No milestones or league medals yet — the first one is always the sweetest. <a href="<?php echo pm_h(k2_milestones_recent_href()); ?>">See what is on offer →</a></p>
        <?php
        player_feast_lab4_section_close();

        return;
    }

    player_feast_lab4_section_open('Honours', 'Community marks — milestones unlocked and league medals won.');
    ?>
<div class="lab4-honours">
	<div class="lab4-honours__cards">
        <?php
        if (!empty($ms['latest'])) {
            player_feast_lab4_honour_card(
                'Latest milestone',
                $ms['latest']['name'],
                'Unlocked ' . $ms['latest']['date'],
                $ms['latest']['href'],
                'k2-lb-ms-tier--' . $ms['latest']['token']
            );
        }
        if (!empty($ms['league_card'])) {
            player_feast_lab4_honour_card(
                'League milestone',
                $ms['league_card']['name'],
                'Unlocked ' . $ms['league_card']['date'],
                $ms['league_card']['href'],
                'k2-lb-ms-tier--' . $ms['league_card']['token']
            );
        }
        if (!empty($lg['latest'])) {
            $medal = (string) $lg['latest']['medal'];
            player_feast_lab4_honour_card(
                'Latest league medal',
                player_feast_lab4_medal_label($medal) . ' — ' . $lg['latest']['label'],
                'League honours',
                $lg['latest']['href'],
                'lab4-medal--' . $medal,
                player_feast_lab4_medal_glyph($medal)
            );
        }
        ?>
	</div>
	<ul class="lab4-honours__tallies">
        <?php
        if ($counts !== null && $catalogTotal > 0) {
            ?>
		<li class="lab4-tally">
			<span class="lab4-tally__num"><?php echo (int) ($counts['total'] ?? 0); ?> / <?php echo $catalogTotal; ?></span>
			<span class="lab4-tally__label"><a href="<?php echo pm_h(k2_milestones_recent_href()); ?>">milestones unlocked</a></span>
		</li>
            <?php
        }
        if (!empty($ms['ready']) && (int) ($ms['holo'] ?? 0) > 0) {
            ?>
		<li class="lab4-tally lab4-tally--holo">
			<span class="lab4-tally__num"><?php echo (int) $ms['holo']; ?></span>
			<span class="lab4-tally__label">holo unlock<?php echo (int) $ms['holo'] === 1 ? '' : 's'; ?> (rarest tier)</span>
		</li>
            <?php
        } elseif (!empty($ms['ready']) && (int) ($ms['amber'] ?? 0) > 0) {
            ?>
		<li class="lab4-tally lab4-tally--amber">
			<span class="lab4-tally__num"><?php echo (int) $ms['amber']; ?></span>
			<span class="lab4-tally__label">amber accomplishment<?php echo (int) $ms['amber'] === 1 ? '' : 's'; ?></span>
		</li>
            <?php
        }
        if (!empty($ms['ready']) && (int) ($ms['unlocks_12mo'] ?? 0) > 0) {
            ?>
		<li class="lab4-tally">
			<span class="lab4-tally__num"><?php echo (int) $ms['unlocks_12mo']; ?></span>
			<span class="lab4-tally__label">unlocked in the last 12 months</span>
		</li>
            <?php
        }
        if (!empty($lg['ready']) && ((int) $lg['gold'] + (int) $lg['silver'] + (int) $lg['bronze']) > 0) {
            ?>
		<li class="lab4-tally lab4-tally--podium">
			<span class="lab4-tally__num"><?php echo (int) $lg['gold']; ?>&#129351; <?php echo (int) $lg['silver']; ?>&#129352; <?php echo (int) $lg['bronze']; ?>&#129353;</span>
			<span class="lab4-tally__label"><a href="ranked9.php">career league podium</a></span>
		</li>
            <?php
        }
        if (!empty($lg['ready']) && (int) $lg['wins'] > 0) {
            ?>
		<li class="lab4-tally">
			<span class="lab4-tally__num"><?php echo (int) $lg['wins']; ?></span>
			<span class="lab4-tally__label">league title<?php echo (int) $lg['wins'] === 1 ? '' : 's'; ?> won</span>
		</li>
            <?php
        }
        ?>
	</ul>
</div>
    <?php
    player_feast_lab4_section_close();
}

function player_feast_lab4_honour_card(
    string $tag,
    string $title,
    string $meta,
    string $href,
    string $accentClass,
    string $glyph = ''
): void {
    ?>
<a class="lab4-honour-card <?php echo pm_h($accentClass); ?>" href="<?php echo pm_h($href); ?>">
    <?php if ($glyph !== '') { ?>
	<span class="lab4-honour-card__glyph" aria-hidden="true"><?php echo $glyph; ?></span>
    <?php } ?>
	<span class="lab4-honour-card__tag"><?php echo pm_h($tag); ?></span>
	<span class="lab4-honour-card__title"><?php echo pm_h($title); ?></span>
	<span class="lab4-honour-card__meta"><?php echo pm_h($meta); ?></span>
</a>
    <?php
}

function player_feast_lab4_medal_glyph(string $medal): string
{
    switch ($medal) {
        case 'gold':
            return '&#129351;';
        case 'silver':
            return '&#129352;';
        case 'bronze':
            return '&#129353;';
        default:
            return '&#127941;';
    }
}

/* ════════════════════════════════════════════════════════════════════
 * B5 — Texture (played days / weeks heatmaps — open background, keep)
 * ════════════════════════════════════════════════════════════════════ */

function player_feast_lab4_render_played_days(int $playerId, string $firstGameDateYmd): void
{
    $fromAttr = preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstGameDateYmd) ? $firstGameDateYmd : date('Y-m-d');
    player_feast_lab4_section_open('Played days', 'UTC calendar days with at least one rated game, from the first rated game through today.');
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
    player_feast_lab4_section_close();
}

function player_feast_lab4_render_played_weeks(int $playerId, string $firstGameDateYmd): void
{
    $fromAttr = preg_match('/^\d{4}-\d{2}-\d{2}$/', $firstGameDateYmd) ? $firstGameDateYmd : date('Y-m-d');
    player_feast_lab4_section_open('Played weeks', 'UTC weeks with at least one rated game since the first rated game.');
    ?>
<div class="pm3-cal pm3-cal--hero pm3-cal--weeks" data-player-id="<?php echo $playerId; ?>" data-first-game-date="<?php echo pm_h($fromAttr); ?>" aria-label="Weekly activity since first rated game">
	<p class="pm3-cal__status pm3-muted">Loading weeks…</p>
	<div class="pm3-cal__years"></div>
	<p class="pm3-cal__legend"><span class="pm3-cal__cell" aria-hidden="true"></span> no rated game · <span class="pm3-cal__cell pm3-cal__cell--play" aria-hidden="true"></span> played</p>
</div>
    <?php
    player_feast_lab4_section_close();
}

/* ════════════════════════════════════════════════════════════════════
 * B4 — Memory (Personal bests → Moments + M03)
 * ════════════════════════════════════════════════════════════════════ */

function player_feast_lab4_render_peak_activity(array $pm): void
{
    $b = [
        'day' => $pm['busiest']['day'] ?? null,
        'month' => $pm['busiest']['month'] ?? null,
        'year' => $pm['busiest']['year'] ?? null,
    ];
    $bd = $b['day'];
    $bm = $b['month'];
    $by = $b['year'];

    player_feast_lab4_section_open('Personal bests', 'Most rated games in a single day, month, and calendar year.');
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
    player_feast_lab4_section_close();
}

function player_feast_lab4_render_moments(array $pm): void
{
    $games = (int) $pm['games'];
    $longestStreak = (int) $pm['longest_win_streak'];
    $trophies = $pm['trophies'] ?? [];
    $maxVictim = player_feast_lab4_show_max_victim($pm) ? ($pm['lab4']['max_rated_victim'] ?? null) : null;
    $hasMoments = $longestStreak > 0 || !empty($trophies) || $maxVictim !== null;

    player_feast_lab4_section_open('Moments', 'Specific games and runs worth remembering.');

    if (!$hasMoments) {
        ?>
<p class="lab4-empty">
    <?php echo $games < 1
        ? 'First trophy game awaits — every legend starts at game one.'
        : 'No headline games yet — keep playing and the trophies will come.'; ?>
</p>
        <?php
        player_feast_lab4_section_close();

        return;
    }
    ?>
<div class="pm3-moments pm3-moments--mosaic">
	<div class="pm3-moments__grid">
        <?php if ($longestStreak > 0) { ?>
		<article class="pm3-moment pm3-moment--streak">
			<span class="pm3-moment__glyph" aria-hidden="true">🏆</span>
			<span class="pm3-moment__tag">Streak</span>
			<h3 class="pm3-moment__label">Longest win run</h3>
			<p class="pm3-moment__score"><?php echo $longestStreak; ?> wins</p>
		</article>
        <?php } ?>
        <?php if ($maxVictim !== null) { ?>
		<article class="pm3-moment lab4-moment--upset">
			<span class="pm3-moment__glyph" aria-hidden="true">🗡️</span>
			<span class="pm3-moment__tag">Giant-killing</span>
			<h3 class="pm3-moment__label">Best scalp<?php echo isset($maxVictim['victim_rating']) && $maxVictim['victim_rating'] ? ' (' . (int) $maxVictim['victim_rating'] . ' rated)' : ''; ?></h3>
			<p class="pm3-moment__score">
				<a href="game.php?id=<?php echo (int) $maxVictim['game_id']; ?>"><?php echo pm_h((string) $maxVictim['score']); ?></a>
			</p>
			<p class="pm3-moment__meta">
				beat <a href="individual1.php?id=<?php echo (int) $maxVictim['opponent_id']; ?>"><?php echo pm_h((string) $maxVictim['opponent_name']); ?></a>
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
    player_feast_lab4_section_close();
}

/* ════════════════════════════════════════════════════════════════════
 * C1/C2/C3 — Matchups (M09 rivalry line → charts → search)
 * ════════════════════════════════════════════════════════════════════ */

function player_feast_lab4_render_matchups(array $pm): void
{
    $playerId = (int) $pm['id'];
    $rival = $pm['lab4']['featured_rival'] ?? null;
    $uid = 'pm3d-' . $playerId;

    player_feast_lab4_section_open('Career rating', 'Rating arc and monthly activity — toggle the left chart by calendar date or game number.');
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
    player_feast_lab4_section_close();

    player_feast_lab4_section_open('Matchups', 'Pick a frequent opponent to update the head-to-head and rating comparison graphs.');

    if ($rival !== null && (int) $rival['games'] > 1) {
        ?>
<p class="lab4-rival-line">
	Main rival:
	<a href="individual1.php?id=<?php echo (int) $rival['id']; ?>"><?php echo pm_h((string) $rival['name']); ?></a>
	over <strong><?php echo number_format((int) $rival['games']); ?></strong> games —
	<span class="pm-outcome--win"><?php echo (int) $rival['wins']; ?>W</span> ·
	<span class="pm-outcome--draw"><?php echo (int) $rival['draws']; ?>D</span> ·
	<span class="pm-outcome--loss"><?php echo (int) $rival['losses']; ?>L</span>.
	<a class="lab4-rival-line__link" href="individual3.php?id=<?php echo $playerId; ?>&amp;vs=<?php echo (int) $rival['id']; ?>">All games vs <?php echo pm_h((string) $rival['name']); ?> &rarr;</a>
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
    player_feast_lab4_section_close();
}

/* ════════════════════════════════════════════════════════════════════
 * Orchestrator — full v1 spine
 * ════════════════════════════════════════════════════════════════════ */

function player_feast_lab4_render_all(array $pm): void
{
    $playerId = (int) $pm['id'];
    $firstGameYmd = (string) $pm['first_game_date_ymd'];

    player_feast_lab4_render_banner();
    player_feast_lab4_render_presence($pm);          // B1
    player_feast_lab4_render_career($pm);            // B2
    player_feast_lab4_render_honours($pm);           // B3
    player_feast_lab4_render_played_days($playerId, $firstGameYmd);   // B5
    player_feast_lab4_render_played_weeks($playerId, $firstGameYmd);  // B5
    player_feast_lab4_render_peak_activity($pm);     // B4 bests
    player_feast_lab4_render_moments($pm);           // B4 moments (+ M03)
    player_feast_lab4_render_matchups($pm);          // C1 → C2 → C3
}
