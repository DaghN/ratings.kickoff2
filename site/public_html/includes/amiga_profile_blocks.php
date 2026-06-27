<?php
/**
 * Amiga profile v0 blocks — career facts from playertable + rating chart shell.
 */
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_player_game_row.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_player_matchup_lib.php';
require_once __DIR__ . '/amiga_performance_rating.php';
require_once __DIR__ . '/amiga_player_moments_lib.php';
require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/amiga_player_tournament_lib.php';
require_once __DIR__ . '/amiga_participation_placement.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';

const AMIGA_TOURNAMENT_EVENT_STATS_ANCHOR_COL = 1;
const AMIGA_TOURNAMENT_EVENT_STATS_DEFAULT_SORT_COL = 11;
const AMIGA_PLAYER_TOURNAMENT_HISTORY_ANCHOR_COL = 1;
const AMIGA_PLAYER_TOURNAMENT_HISTORY_DEFAULT_SORT_COL = 0;
const AMIGA_TOURNAMENT_INDEX_ANCHOR_COL = 1;
const AMIGA_TOURNAMENT_INDEX_DEFAULT_SORT_COL = 0;
/** Date is default order on catalog index; sortable but never active-sort emphasis. */
const AMIGA_TOURNAMENT_INDEX_QUIET_SORT_COL = 0;

function amiga_tournament_index_sort_col_for_emphasis(int $colIndex, int $activeSortCol): int
{
    if ($colIndex === AMIGA_TOURNAMENT_INDEX_QUIET_SORT_COL && $activeSortCol === AMIGA_TOURNAMENT_INDEX_QUIET_SORT_COL) {
        return -1;
    }

    return $activeSortCol;
}

/**
 * @param array<string, mixed> $pm from amiga_player_load()
 */
function amiga_profile_render_career(array $pm): void
{
    ?>
<section class="k2-amiga-profile-career" style="padding:0 1.25rem 1.5rem">
	<h3 class="k2-panel-heading">Career</h3>
	<dl class="k2-amiga-profile-dl" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(9rem,1fr));gap:0.75rem 1.25rem;margin:0">
		<div><dt style="opacity:0.75;font-size:0.85rem">W – D – L</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php
            echo (int) $pm['wins'] . ' – ' . (int) $pm['draws'] . ' – ' . (int) $pm['losses'];
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Win %</dt><dd style="margin:0"><?php
            echo $pm['win_pct'] !== null ? htmlspecialchars((string) $pm['win_pct'], ENT_QUOTES, 'UTF-8') . '%' : '—';
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Goals</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php
            echo (int) $pm['goals_for'] . ' – ' . (int) $pm['goals_against'];
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Peak rating</dt><dd style="margin:0"><?php
            echo $pm['peak_rating'] !== null ? k2_fmt_int($pm['peak_rating']) : '—';
        ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Opp. avg.</dt><dd style="margin:0"><?php
            echo $pm['opp_avg'] !== null ? k2_fmt_int(round($pm['opp_avg'])) : '—';
        ?></dd></div>
	</dl>
</section>
    <?php
}

/**
 * @param array<string, mixed>|null $totals from amiga_player_tournament_totals_row()
 */
function amiga_profile_tournament_totals_show_honours(?array $totals): bool
{
    if ($totals === null || (int) ($totals['tournaments_played'] ?? 0) < 1) {
        return false;
    }

    $wcTotal = (int) ($totals['wc_gold'] ?? 0)
        + (int) ($totals['wc_silver'] ?? 0)
        + (int) ($totals['wc_bronze'] ?? 0);

    return $wcTotal > 0
        || (int) ($totals['tournaments_won'] ?? 0) > 0
        || (int) ($totals['event_podiums'] ?? 0) > 0;
}

/**
 * @param array<string, mixed> $totals
 */
function amiga_profile_career_wc_honours_label(array $totals): string
{
    $parts = [];
    foreach (['gold', 'silver', 'bronze'] as $medal) {
        $n = (int) ($totals['wc_' . $medal] ?? 0);
        if ($n > 0) {
            $parts[] = $n . ' ' . $medal;
        }
    }

    return implode(' · ', $parts);
}

/**
 * Career tournament honours from amiga_player_current honours columns (no extra query).
 *
 * @param array<string, mixed> $totals from amiga_player_tournament_totals_row() (reads current)
 */
function amiga_profile_render_honours(array $totals, int $playerId): void
{
    if (!amiga_profile_tournament_totals_show_honours($totals)) {
        return;
    }

    $wcLabel = amiga_profile_career_wc_honours_label($totals);
    $hasWcMedals = $wcLabel !== '';
    $tournamentsWon = (int) ($totals['tournaments_won'] ?? 0);
    $podiums = (int) ($totals['event_podiums'] ?? 0);
    $lastEventDate = $totals['last_event_date'] ?? null;
    ?>
<section class="k2-amiga-profile-honours" style="padding:0 1.25rem 1.5rem">
	<h3 class="k2-panel-heading">Honours</h3>
	<dl class="k2-amiga-profile-dl" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(9rem,1fr));gap:0.75rem 1.25rem;margin:0">
		<?php if ($hasWcMedals) { ?>
		<div><dt style="opacity:0.75;font-size:0.85rem">WC medals</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php
            echo htmlspecialchars($wcLabel, ENT_QUOTES, 'UTF-8');
        ?></dd></div>
		<?php } ?>
		<div><dt style="opacity:0.75;font-size:0.85rem">Tournaments won</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php echo $tournamentsWon; ?></dd></div>
		<div><dt style="opacity:0.75;font-size:0.85rem">Podiums</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php echo $podiums; ?></dd></div>
	</dl>
	<?php if ($lastEventDate !== null && $lastEventDate !== '') { ?>
	<p style="margin:0.75rem 0 0;opacity:0.75;font-size:0.85rem">Last event <?php echo amiga_profile_format_event_date($lastEventDate); ?></p>
	<?php } ?>
	<p style="margin:0.75rem 0 0">
		<a class="k2-link-star" href="/amiga/leaderboards/tournament-honours.php">Tournament honours leaderboard</a><?php
        if ($hasWcMedals) {
            echo ' · <a class="k2-link-star" href="/amiga/leaderboards/world-cups/honours.php">World Cups leaderboard</a>';
        }
        if ($hasWcMedals && $playerId > 0) {
            echo ' · <a class="k2-link-star" href="' . k2_h(amiga_player_tournaments_filter_url($playerId, 'world-cup')) . '">World Cup history</a>';
        }
    ?>
	</p>
</section>
    <?php
}

/**
 * Whether recent-tournament label may append event_points (full-event 3-1-0 tally).
 *
 * League+cup marathons: event_points include cup games — omit pts suffix on profile lines.
 * Phase-scoped league points live in amiga_tournament_standings, not participation.
 */
function amiga_profile_tournament_label_includes_event_points(array $t): bool
{
    if (amiga_tournament_is_world_cup($t)) {
        return false;
    }

    return empty($t['has_league']) || empty($t['has_cup']);
}

/**
 * Canonical holistic finish from a participation row (`position` = event_finish_position).
 *
 * @param array<string, mixed> $row
 */
function amiga_profile_row_event_finish(array $row): ?int
{
    $position = $row['position'] ?? $row['event_finish_position'] ?? null;
    if ($position === null || $position === '') {
        return null;
    }
    $finish = (int) $position;

    return $finish > 0 ? $finish : null;
}

/**
 * Ordinal finish label (1st/2nd/3rd/4th…) from event_finish_position — all tournaments including WC.
 *
 * @param array<string, mixed> $row
 */
function amiga_profile_event_finish_ordinal_label(array $row): string
{
    $finish = amiga_profile_row_event_finish($row);
    if ($finish === null) {
        return '—';
    }

    return $finish . ordinal_suffix($finish);
}

/**
 * WC podium medal word from event_finish_position (v2).
 *
 * @param array<string, mixed> $row
 */
function amiga_profile_wc_podium_word(array $row): string
{
    return amiga_participation_wc_podium_word_from_finish(amiga_profile_row_event_finish($row));
}

/**
 * Profile suffix for one recent tournament row.
 *
 * Holistic finish from event_finish_position (ordinal); event_points when allowed.
 *
 * @param array<string, mixed> $t participation row (name, position = event_finish_position, event_points, has_league, has_cup)
 */
function amiga_profile_tournament_result_label(array $t): string
{
    $finish = amiga_profile_row_event_finish($t);
    if ($finish === null) {
        return '—';
    }

    $label = $finish . ordinal_suffix($finish);
    if (amiga_profile_tournament_label_includes_event_points($t)) {
        $label .= ' · ' . (int) ($t['event_points'] ?? 0) . ' pts';
    }

    return $label;
}

/**
 * Compact suffix for recent-tournament lines (winner badge, perf rating).
 * Does not alter finish / event_points suffix from amiga_profile_tournament_result_label().
 *
 * @param array<string, mixed> $t participation row
 */
function amiga_profile_recent_tournament_extras(array $t): string
{
    $parts = [];
    if ((int) ($t['is_winner'] ?? 0) === 1) {
        $parts[] = 'Winner';
    }
    $games = (int) ($t['games'] ?? 0);
    $perf = $t['performance_rating'] ?? null;
    if ($games >= 2 && $perf !== null && $perf !== '' && !k2_db_is_null($perf)) {
        $parts[] = 'Perf ' . k2_fmt_int(round((float) $perf));
    }

    return $parts === [] ? '' : ' · ' . implode(' · ', $parts);
}

/**
 * @param list<array<string, mixed>> $moments from amiga_player_moments_load()
 */
function amiga_profile_render_moments(array $moments, int $playerId = 0): void
{
    if ($moments === []) {
        return;
    }
    ?>
<section class="k2-amiga-profile-moments" style="padding:0 1.25rem 1.5rem">
	<h3 class="k2-panel-heading">Moments</h3>
	<div class="pm3-moments pm3-moments--mosaic">
		<div class="pm3-moments__grid">
		<?php foreach ($moments as $moment) {
            $isEvent = !empty($moment['is_event']);
            $opponentId = (int) ($moment['opponent_id'] ?? 0);
            $gamesHref = amiga_player_moment_games_href($playerId, $opponentId, (int) ($moment['game_id'] ?? 0));
            $peakRating = $moment['peak_rating'] ?? null;
            ?>
			<article class="pm3-moment<?php echo ($moment['key'] ?? '') === 'peak_rating' ? ' pm3-moment--peak' : ''; ?>">
				<span class="pm3-moment__glyph" aria-hidden="true"><?php echo k2_h((string) ($moment['icon'] ?? '')); ?></span>
				<span class="pm3-moment__tag"><?php echo k2_h((string) ($moment['tag'] ?? '')); ?></span>
				<h3 class="pm3-moment__label"><?php echo k2_h((string) ($moment['label'] ?? '')); ?></h3>
				<?php if ($isEvent) { ?>
				<p class="pm3-moment__score">
					<?php echo $peakRating !== null ? 'Peak ' . (int) $peakRating : k2_h((string) ($moment['score'] ?? '')); ?>
				</p>
				<p class="pm3-moment__meta"><?php
                    echo amiga_tournament_link(
                        (int) ($moment['tournament_id'] ?? 0),
                        (string) ($moment['tournament_name'] ?? '')
                    );
                    echo ' · ' . k2_h((string) ($moment['date'] ?? ''));
                ?></p>
				<?php } else { ?>
				<p class="pm3-moment__score">
					<a class="k2-link-star" href="<?php echo k2_h($gamesHref); ?>"><?php echo k2_h((string) ($moment['score'] ?? '')); ?></a>
				</p>
				<p class="pm3-moment__meta"><?php
                    if ($peakRating !== null) {
                        echo 'Peak ' . (int) $peakRating . ' · ';
                    }
                    ?><span class="<?php echo k2_h((string) ($moment['outcome_class'] ?? '')); ?>"><?php
                    echo k2_h((string) ($moment['outcome'] ?? ''));
                ?></span>
					· vs <?php echo k2_amiga_player_link($opponentId, (string) ($moment['opponent_name'] ?? '')); ?>
					· <?php echo k2_h((string) ($moment['date'] ?? '')); ?></p>
				<?php } ?>
			</article>
		<?php } ?>
		</div>
	</div>
</section>
    <?php
}

/**
 * @param array{best: ?array<string, mixed>, recent: ?array<string, mixed>} $highlight from amiga_player_perf_rating_highlight()
 */
function amiga_profile_render_perf_rating_highlight(array $highlight, int $playerId = 0): void
{
    $best = is_array($highlight['best'] ?? null) ? $highlight['best'] : null;
    $recent = is_array($highlight['recent'] ?? null) ? $highlight['recent'] : null;
    if ($best === null && $recent === null) {
        return;
    }

    $help = amiga_perf_rating_column_help();
    $helpAttr = htmlspecialchars($help, ENT_QUOTES, 'UTF-8');
    $showRecent = $recent !== null && (
        $best === null
        || (int) ($recent['tournament_id'] ?? 0) !== (int) ($best['tournament_id'] ?? 0)
    );
    k2_table_js_enqueue();
    ?>
<section class="k2-amiga-profile-perf-rating" style="padding:0 1.25rem 1.5rem">
	<h3 class="k2-panel-heading">Performance rating</h3>
	<dl class="k2-amiga-profile-dl" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(11rem,1fr));gap:0.75rem 1.25rem;margin:0">
		<?php if ($best !== null) { ?>
		<div><dt style="opacity:0.75;font-size:0.85rem" class="k2-table-helped" data-k2-help="<?php echo $helpAttr; ?>" data-k2-tooltip-label="Best event" tabindex="0">Best event</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php
            echo amiga_profile_tournament_rating_cell($best['performance_rating'] ?? null);
            echo ' · ';
            echo amiga_tournament_link((int) ($best['tournament_id'] ?? 0), (string) ($best['name'] ?? ''));
        ?></dd></div>
		<?php } ?>
		<?php if ($showRecent) { ?>
		<div><dt style="opacity:0.75;font-size:0.85rem" class="k2-table-helped" data-k2-help="<?php echo $helpAttr; ?>" data-k2-tooltip-label="Latest event" tabindex="0">Latest event</dt><dd style="margin:0;font-variant-numeric:tabular-nums"><?php
            echo amiga_profile_tournament_rating_cell($recent['performance_rating'] ?? null);
            echo ' · ';
            echo amiga_tournament_link((int) ($recent['tournament_id'] ?? 0), (string) ($recent['name'] ?? ''));
        ?></dd></div>
		<?php } ?>
	</dl>
	<p style="margin:0.75rem 0 0">
		<a class="k2-link-star" href="/amiga/leaderboards/performance-rating.php">Best event performance leaderboard</a>
		<?php if ($playerId > 0) { ?>
		 · <a class="k2-link-star" href="<?php echo k2_h(k2_amiga_route('amiga-player-tournaments', ['id' => $playerId])); ?>">Full tournament history</a>
		<?php } ?>
	</p>
</section>
    <?php
}

/**
 * @param list<array<string, mixed>> $tournaments from amiga_player_tournament_participation_recent()
 */
function amiga_profile_render_recent_tournaments(array $tournaments, int $playerId = 0, int $totalCount = 0): void
{
    if ($tournaments === []) {
        return;
    }
    ?>
<section class="k2-amiga-profile-tournaments" style="padding:0 1.25rem 1.5rem">
	<h3 class="k2-panel-heading">Recent tournaments</h3>
	<ul style="margin:0;padding-left:1.25rem">
	<?php foreach ($tournaments as $t) { ?>
		<li><?php
            echo amiga_tournament_link((int) $t['id'], (string) $t['name']);
            echo ' — ';
            echo htmlspecialchars(
                amiga_profile_tournament_result_label($t) . amiga_profile_recent_tournament_extras($t),
                ENT_QUOTES,
                'UTF-8'
            );
        ?></li>
	<?php } ?>
	</ul>
	<?php if ($playerId > 0 && $totalCount > count($tournaments)) { ?>
	<p style="margin:0.75rem 0 0">
		<a class="k2-link-star" href="<?php echo k2_h(k2_amiga_route('amiga-player-tournaments', ['id' => $playerId])); ?>">All <?php echo (int) $totalCount; ?> tournaments</a>
	</p>
	<?php } elseif ($playerId > 0 && $totalCount > 0) { ?>
	<p style="margin:0.75rem 0 0">
		<a class="k2-link-star" href="<?php echo k2_h(k2_amiga_route('amiga-player-tournaments', ['id' => $playerId])); ?>">Full tournament history</a>
	</p>
	<?php } ?>
</section>
    <?php
}

/**
 * Tournament event date for history tables — named month, year last (`Jan 9, 2026`).
 * Right-align the column so years stack visually; avoids numeric-only US/EU ambiguity.
 */
function amiga_profile_format_event_date(mixed $eventDate): string
{
    if ($eventDate === null || $eventDate === '') {
        return '—';
    }
    $ts = strtotime((string) $eventDate);
    if ($ts === false) {
        return k2_h((string) $eventDate);
    }

    return k2_h(date('M j, Y', $ts));
}

/**
 * @param array<string, mixed> $row participation row with event_date and optional event_chrono
 */
function amiga_profile_event_date_sort_value(array $row): string
{
    if (isset($row['event_chrono']) && $row['event_chrono'] !== null && $row['event_chrono'] !== '') {
        return (string) (float) $row['event_chrono'];
    }
    $eventDate = $row['event_date'] ?? null;
    if ($eventDate === null || $eventDate === '') {
        return '0';
    }
    $ts = strtotime((string) $eventDate);

    return (string) ($ts !== false ? $ts : 0);
}

function amiga_profile_tournament_finish_rank_label(array $row): string
{
    return amiga_profile_event_finish_ordinal_label($row);
}

function amiga_profile_tournament_wdl_cell(int $value, string $tone): string
{
    if ($value === 0) {
        return '0';
    }
    if ($tone === 'win') {
        return '<span class="blue">' . $value . '</span>';
    }
    if ($tone === 'loss') {
        return '<span class="red">' . $value . '</span>';
    }

    return (string) $value;
}

function amiga_profile_tournament_rating_delta_cell(mixed $delta): string
{
    if ($delta === null || $delta === '') {
        return k2_fmt_dash();
    }

    return k2_player_game_signed_number_html((float) $delta);
}

function amiga_profile_tournament_rating_cell(mixed $rating): string
{
    if ($rating === null || $rating === '') {
        return k2_fmt_dash();
    }

    return k2_fmt_int($rating);
}

function amiga_profile_tournament_avg_goals_cell(mixed $avgGoals, int $games): string
{
    return k2_fmt_decimal($avgGoals, $games > 0 ? $games : null, 2);
}

/**
 * @param list<array<string, mixed>> $tournaments from amiga_player_tournament_participation_all()
 */
function amiga_profile_render_tournament_history_table(array $tournaments): void
{
    $anchorCol = AMIGA_PLAYER_TOURNAMENT_HISTORY_ANCHOR_COL;
    $defaultSortCol = k2_table_default_sort_col_from_request(AMIGA_PLAYER_TOURNAMENT_HISTORY_DEFAULT_SORT_COL);
    $defaultSortDir = k2_table_default_sort_dir_from_request('desc');
    $tableClass = k2_table_ranked_sortable_class('k2-table--player-tournaments');
    $skipInitialSort = $defaultSortCol === AMIGA_PLAYER_TOURNAMENT_HISTORY_DEFAULT_SORT_COL && $defaultSortDir === 'desc';
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>"<?php echo $skipInitialSort ? ' data-k2-skip-initial-sort="1"' : ''; ?>>
	<thead>
		<tr>
			<th<?php echo k2_table_sortable_th_attr(0, $defaultSortCol, $defaultSortDir, 'k2-table-cell--right'); ?> data-k2-sort="number">Date</th>
			<th<?php echo k2_table_sortable_th_attr(1, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Tournament</th>
			<th<?php echo k2_table_sortable_th_attr(2, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center'); ?> data-k2-sort="text">Country</th>
			<th<?php echo k2_table_sortable_th_attr(3, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Games</th>
			<th<?php echo k2_table_sortable_th_attr(4, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Wins in this event (all phases).">W</th>
			<th<?php echo k2_table_sortable_th_attr(5, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Draws in this event (all phases).">D</th>
			<th<?php echo k2_table_sortable_th_attr(6, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Losses in this event (all phases).">L</th>
			<th<?php echo k2_table_sortable_th_attr(7, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Goals scored in this event (all phases).">GF</th>
			<th<?php echo k2_table_sortable_th_attr(8, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Goals conceded in this event (all phases).">GA</th>
			<th<?php echo k2_table_sortable_th_attr(9, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">GD</th>
			<th<?php echo k2_table_sortable_th_attr(10, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Average goals scored per game in this event.">GF/g</th>
			<th<?php echo k2_table_sortable_th_attr(11, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Average goals conceded per game in this event.">GA/g</th>
			<th<?php echo k2_table_sortable_th_attr(12, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Result points across all games in this event (3 per win, 1 per draw). Phase league tables use amiga_tournament_standings.">Pts</th>
			<th<?php echo k2_table_sortable_th_attr(13, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="text">Finish</th>
			<th<?php echo k2_table_sortable_th_attr(14, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Elo rating before this event.">Rating</th>
			<th<?php echo k2_table_sortable_th_attr(15, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Rating points gained or lost in this event.">Adj.</th>
			<th<?php echo k2_table_sortable_th_attr(16, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Elo rating after this event.">New rating</th>
			<th<?php echo k2_table_sortable_th_attr(17, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-tooltip-label="<?php echo htmlspecialchars(amiga_perf_rating_column_label(), ENT_QUOTES, 'UTF-8'); ?>" data-k2-help="<?php echo htmlspecialchars(amiga_perf_rating_column_help(), ENT_QUOTES, 'UTF-8'); ?>">Perf. rating</th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ($tournaments as $t) {
        $games = (int) ($t['games'] ?? 0);
        $wins = (int) ($t['wins'] ?? 0);
        $draws = (int) ($t['draws'] ?? 0);
        $losses = (int) ($t['losses'] ?? 0);
        $goalsFor = (int) ($t['goals_for'] ?? 0);
        $goalsAgainst = (int) ($t['goals_against'] ?? 0);
        $goalDiff = $goalsFor - $goalsAgainst;
        $points = (int) ($t['event_points'] ?? 0);
        $finishRank = amiga_profile_tournament_finish_rank_label($t);
        $hostCountry = (string) ($t['country'] ?? '');
        ?>
		<tr>
			<td<?php echo k2_table_body_td_attr(0, $anchorCol, $defaultSortCol, 'k2-table-cell--right'); ?> data-k2-sort-value="<?php echo amiga_profile_event_date_sort_value($t); ?>"><?php echo amiga_profile_format_event_date($t['event_date'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr(1, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php
                echo amiga_tournament_link((int) $t['id'], (string) $t['name']);
            ?></td>
			<td<?php echo k2_table_body_td_attr(2, $anchorCol, $defaultSortCol, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h($hostCountry); ?>"><?php echo k2_amiga_country_table_cell_or_dash($hostCountry); ?></td>
			<td<?php echo k2_table_body_td_attr(3, $anchorCol, $defaultSortCol); ?>><?php echo k2_fmt_games_played($games); ?></td>
			<td<?php echo k2_table_body_td_attr(4, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($wins, 'win'); ?></td>
			<td<?php echo k2_table_body_td_attr(5, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($draws, 'draw'); ?></td>
			<td<?php echo k2_table_body_td_attr(6, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($losses, 'loss'); ?></td>
			<td<?php echo k2_table_body_td_attr(7, $anchorCol, $defaultSortCol); ?>><?php echo $goalsFor; ?></td>
			<td<?php echo k2_table_body_td_attr(8, $anchorCol, $defaultSortCol); ?>><?php echo $goalsAgainst; ?></td>
			<td<?php echo k2_table_body_td_attr(9, $anchorCol, $defaultSortCol); ?>><?php echo $goalDiff > 0 ? '+' . $goalDiff : (string) $goalDiff; ?></td>
			<td<?php echo k2_table_body_td_attr(10, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_avg_goals_cell($t['avg_goals_for'] ?? null, $games); ?></td>
			<td<?php echo k2_table_body_td_attr(11, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_avg_goals_cell($t['avg_goals_against'] ?? null, $games); ?></td>
			<td<?php echo k2_table_body_td_attr(12, $anchorCol, $defaultSortCol); ?>><?php echo $points; ?></td>
			<td<?php echo k2_table_body_td_attr(13, $anchorCol, $defaultSortCol); ?>><?php echo htmlspecialchars($finishRank, ENT_QUOTES, 'UTF-8'); ?></td>
			<td<?php echo k2_table_body_td_attr(14, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_cell($t['rating_before'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr(15, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_delta_cell($t['rating_delta'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr(16, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_cell($t['rating_after'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr(17, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_cell($t['performance_rating'] ?? null); ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

/**
 * Tournament catalog index table (/amiga/tournaments.php).
 *
 * @param list<array<string, mixed>> $rows from amiga_tournament_index_rows()
 */
function amiga_tournament_index_render_table(array $rows): void
{
    $anchorCol = AMIGA_TOURNAMENT_INDEX_ANCHOR_COL;
    $defaultSortCol = k2_table_default_sort_col_from_request(AMIGA_TOURNAMENT_INDEX_DEFAULT_SORT_COL);
    $defaultSortDir = k2_table_default_sort_dir_from_request('desc');
    $tableClass = k2_table_ranked_sortable_class('k2-table--tournament-index');
    $skipInitialSort = $defaultSortCol === AMIGA_TOURNAMENT_INDEX_DEFAULT_SORT_COL && $defaultSortDir === 'desc';
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>" data-k2-quiet-sort-cols="<?php echo AMIGA_TOURNAMENT_INDEX_QUIET_SORT_COL; ?>"<?php echo $skipInitialSort ? ' data-k2-skip-initial-sort="1"' : ''; ?>>
<thead>
    <tr>
        <th<?php echo k2_table_sortable_th_attr(0, amiga_tournament_index_sort_col_for_emphasis(0, $defaultSortCol), $defaultSortDir, 'k2-table-cell--right k2-tournament-index-date'); ?> data-k2-sort="number">Date</th>
        <th<?php echo k2_table_sortable_th_attr(1, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Tournament</th>
        <th<?php echo k2_table_sortable_th_attr(2, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center'); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_table_sortable_th_attr(3, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Games</th>
        <th<?php echo k2_table_sortable_th_attr(4, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Players</th>
        <th<?php echo k2_table_sortable_th_attr(5, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Format</th>
    </tr>
</thead>
<tbody class="black">
<?php if ($rows === []) { ?>
    <tr>
        <td colspan="6" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No tournaments match this filter.</td>
    </tr>
<?php } ?>
<?php foreach ($rows as $row) {
    $games = (int) $row['game_count'];
    $players = (int) $row['standing_players'];
    $hasStandings = (int) ($row['standing_rows'] ?? 0) > 0;
    $kind = amiga_tournament_index_format_kind($row);
    $formatLabel = amiga_tournament_index_format_label($kind);
    $hostCountry = (string) ($row['country'] ?? '');
    ?>
    <tr>
        <td<?php echo k2_table_body_td_attr(0, $anchorCol, amiga_tournament_index_sort_col_for_emphasis(0, $defaultSortCol), 'k2-table-cell--right k2-tournament-index-date'); ?> data-k2-sort-value="<?php echo amiga_profile_event_date_sort_value([
            'event_date' => $row['event_date'] ?? null,
            'event_chrono' => $row['chrono'] ?? null,
        ]); ?>"><?php echo amiga_profile_format_event_date($row['event_date'] ?? null); ?></td>
        <td<?php echo k2_table_body_td_attr(1, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php
            if ($hasStandings) {
                echo amiga_tournament_link((int) $row['id'], (string) $row['name']);
            } else {
                echo k2_h((string) $row['name']);
            }
        ?></td>
        <td<?php echo k2_table_body_td_attr(2, $anchorCol, $defaultSortCol, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h($hostCountry); ?>"><?php echo k2_amiga_country_table_cell_or_dash($hostCountry); ?></td>
        <td<?php echo k2_table_body_td_attr(3, $anchorCol, $defaultSortCol); ?>><?php echo $games; ?></td>
        <td<?php echo k2_table_body_td_attr(4, $anchorCol, $defaultSortCol); ?>><?php echo $hasStandings ? (string) $players : '—'; ?></td>
        <td<?php echo k2_table_body_td_attr(5, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>>
            <span class="k2-amiga-tournament-format"><?php echo k2_h($formatLabel); ?></span>
        </td>
    </tr>
<?php } ?>
</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

/**
 * @param list<array<string, mixed>> $rows from amiga_tournament_participation_rows()
 */
function amiga_tournament_render_event_stats_table(array $rows, bool $isWorldCup): void
{
    if ($rows === []) {
        return;
    }

    $anchorCol = AMIGA_TOURNAMENT_EVENT_STATS_ANCHOR_COL;
    $defaultSortCol = k2_table_default_sort_col_from_request(AMIGA_TOURNAMENT_EVENT_STATS_DEFAULT_SORT_COL);
    $defaultSortDir = k2_table_default_sort_dir_from_request('desc');
    $tableClass = k2_table_ranked_sortable_class('k2-table--tournament-event-stats');
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>" data-k2-skip-initial-sort="1">
	<thead>
		<tr>
			<th<?php echo k2_table_sortable_th_attr(0, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center'); ?> data-k2-sort="text">Country</th>
			<th<?php echo k2_table_sortable_th_attr(1, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
			<th<?php echo k2_table_sortable_th_attr(2, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Games</th>
			<th<?php echo k2_table_sortable_th_attr(3, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Wins</th>
			<th<?php echo k2_table_sortable_th_attr(4, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Draws</th>
			<th<?php echo k2_table_sortable_th_attr(5, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Losses</th>
			<th<?php echo k2_table_sortable_th_attr(6, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Goals scored in this event (all phases).">GF</th>
			<th<?php echo k2_table_sortable_th_attr(7, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Goals conceded in this event (all phases).">GA</th>
			<th<?php echo k2_table_sortable_th_attr(8, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">GD</th>
			<th<?php echo k2_table_sortable_th_attr(9, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Average goals scored per game in this event.">GF/g</th>
			<th<?php echo k2_table_sortable_th_attr(10, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Average goals conceded per game in this event.">GA/g</th>
			<th<?php echo k2_table_sortable_th_attr(11, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Result points across all games in this event (3 per win, 1 per draw). Phase league tables use amiga_tournament_standings.">Pts</th>
			<?php if ($isWorldCup) { ?>
			<th<?php echo k2_table_sortable_th_attr(12, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="text" data-k2-help="World Cup podium from event finish (1st–3rd); medal word is display only.">Medal</th>
			<?php } else { ?>
			<th<?php echo k2_table_sortable_th_attr(12, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="text">Finish</th>
			<?php } ?>
			<th<?php echo k2_table_sortable_th_attr(13, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Elo rating before this event.">Rating</th>
			<th<?php echo k2_table_sortable_th_attr(14, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Rating points gained or lost in this event.">Adjustment</th>
			<th<?php echo k2_table_sortable_th_attr(15, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Elo rating after this event.">New rating</th>
			<th<?php echo k2_table_sortable_th_attr(16, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-tooltip-label="<?php echo htmlspecialchars(amiga_perf_rating_column_label(), ENT_QUOTES, 'UTF-8'); ?>" data-k2-help="<?php echo htmlspecialchars(amiga_perf_rating_column_help(), ENT_QUOTES, 'UTF-8'); ?>">Perf. rating</th>
		</tr>
	</thead>
	<tbody class="black">
	<?php foreach ($rows as $row) {
        $playerId = (int) ($row['player_id'] ?? 0);
        $games = (int) ($row['games'] ?? 0);
        $wins = (int) ($row['wins'] ?? 0);
        $draws = (int) ($row['draws'] ?? 0);
        $losses = (int) ($row['losses'] ?? 0);
        $goalsFor = (int) ($row['goals_for'] ?? 0);
        $goalsAgainst = (int) ($row['goals_against'] ?? 0);
        $goalDiff = $goalsFor - $goalsAgainst;
        $points = (int) ($row['event_points'] ?? 0);
        $finishCell = $isWorldCup
            ? amiga_profile_wc_podium_word($row)
            : amiga_profile_tournament_finish_rank_label($row);
        $playerCountry = (string) ($row['player_country'] ?? '');
        ?>
		<tr>
			<td<?php echo k2_table_body_td_attr(0, $anchorCol, $defaultSortCol, 'k2-table-cell--center'); ?> data-k2-sort-value="<?php echo k2_h($playerCountry); ?>"><?php echo k2_amiga_country_table_cell_or_dash($playerCountry); ?></td>
			<td<?php echo k2_table_body_td_attr(1, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link($playerId, (string) ($row['player_name'] ?? '')); ?></td>
			<td<?php echo k2_table_body_td_attr(2, $anchorCol, $defaultSortCol); ?>><?php echo k2_fmt_games_played($games); ?></td>
			<td<?php echo k2_table_body_td_attr(3, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($wins, 'win'); ?></td>
			<td<?php echo k2_table_body_td_attr(4, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($draws, 'draw'); ?></td>
			<td<?php echo k2_table_body_td_attr(5, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($losses, 'loss'); ?></td>
			<td<?php echo k2_table_body_td_attr(6, $anchorCol, $defaultSortCol); ?>><?php echo $goalsFor; ?></td>
			<td<?php echo k2_table_body_td_attr(7, $anchorCol, $defaultSortCol); ?>><?php echo $goalsAgainst; ?></td>
			<td<?php echo k2_table_body_td_attr(8, $anchorCol, $defaultSortCol); ?>><?php echo $goalDiff > 0 ? '+' . $goalDiff : (string) $goalDiff; ?></td>
			<td<?php echo k2_table_body_td_attr(9, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_avg_goals_cell($row['avg_goals_for'] ?? null, $games); ?></td>
			<td<?php echo k2_table_body_td_attr(10, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_avg_goals_cell($row['avg_goals_against'] ?? null, $games); ?></td>
			<td<?php echo k2_table_body_td_attr(11, $anchorCol, $defaultSortCol); ?>><?php echo $points; ?></td>
			<td<?php echo k2_table_body_td_attr(12, $anchorCol, $defaultSortCol); ?>><?php echo htmlspecialchars($finishCell, ENT_QUOTES, 'UTF-8'); ?></td>
			<td<?php echo k2_table_body_td_attr(13, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_cell($row['rating_before'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr(14, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_delta_cell($row['rating_delta'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr(15, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_cell($row['rating_after'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr(16, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_cell($row['performance_rating'] ?? null); ?></td>
		</tr>
	<?php } ?>
	</tbody>
</table>
<?php k2_table_wrap_close(); ?>
    <?php
}

function ordinal_suffix(int $n): string
{
    if ($n % 100 >= 11 && $n % 100 <= 13) {
        return 'th';
    }
    return match ($n % 10) {
        1 => 'st',
        2 => 'nd',
        3 => 'rd',
        default => 'th',
    };
}

function amiga_profile_render_rating_chart(int $playerId): void
{
    require_once __DIR__ . '/amiga_snapshot_context.php';
    $asAttr = '';
    $asParam = amiga_snapshot_as_param_from_request();
    if ($asParam !== null && $asParam !== '') {
        $asAttr = ' data-as="' . htmlspecialchars($asParam, ENT_QUOTES, 'UTF-8') . '"';
    }
    ?>
<section class="k2-amiga-profile-chart" style="padding:0 1.25rem 2rem">
	<div class="player-rating-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>" data-realm="amiga"<?php echo $asAttr; ?>>
		<h3 class="k2-panel-heading">Elo rating</h3>
		<p class="k2-chart-block__hint">Calendar view: end-of-day rating after each tournament day. Tournament # view: one point per finalized event.</p>
		<div class="pm3d-chart-toolbar">
			<div class="pm3d-rating-toggle" role="tablist" aria-label="Rating chart view">
				<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-view="date">By date</button>
				<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-view="game">By tournament #</button>
			</div>
			<div class="pm3d-rating-toggle player-rating-chart__line-style" role="tablist" aria-label="Rating line style">
				<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-line-style="stepped">Stepwise</button>
				<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-line-style="smooth">Connected</button>
			</div>
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
</section>
    <?php
}

function amiga_profile_render_rank_chart(int $playerId): void
{
    require_once __DIR__ . '/amiga_snapshot_context.php';
    $asAttr = '';
    $asParam = amiga_snapshot_as_param_from_request();
    if ($asParam !== null && $asParam !== '') {
        $asAttr = ' data-as="' . htmlspecialchars($asParam, ENT_QUOTES, 'UTF-8') . '"';
    }
    ?>
<section class="k2-amiga-profile-chart" style="padding:0 1.25rem 2rem">
	<div class="player-rank-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>" data-realm="amiga"<?php echo $asAttr; ?>>
		<h3 class="k2-panel-heading">Elo rank</h3>
		<p class="k2-chart-block__hint">End-of-day rank after each tournament day.</p>
		<div class="pm3d-chart-toolbar player-rank-chart__toolbar" data-range-mode="linear">
			<div class="player-rank-chart__toolbar-row">
				<div class="pm3d-rating-toggle player-rank-chart__scale" role="tablist" aria-label="Rank chart scale">
					<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-scale="linear">Linear</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-scale="percentile">Percentile</button>
				</div>
			</div>
			<div class="player-rank-chart__toolbar-row player-rank-chart__range-row">
				<div class="pm3d-rating-toggle player-rank-chart__window" role="tablist" aria-label="Rank chart Y window">
					<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-window="career">Career</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-window="top20">Top 20</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-window="top50">Top 50</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-window="top100">Top 100</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-window="community">Full ladder</button>
				</div>
				<div class="pm3d-rating-toggle player-rank-chart__percentile-window" role="tablist" aria-label="Percentile Y window">
					<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-pwindow="career">Career</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-pwindow="p95">95–100</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-pwindow="p90">90–100</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-pwindow="p80">80–100</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-pwindow="p50">50–100</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-pwindow="community">Full ladder</button>
				</div>
			</div>
		</div>
		<p class="player-rank-peak-summary pm3d-chart__summary" hidden></p>
		<p class="player-rank-chart-status pm3d-chart__status k2-chart-panel__status">Loading rank history…</p>
		<div class="k2-chart-frame">
			<canvas class="player-rank-canvas" aria-label="Elo rank over time"></canvas>
		</div>
	</div>
</section>
    <?php
}
