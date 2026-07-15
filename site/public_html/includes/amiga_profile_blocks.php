<?php
/**
 * Amiga profile v0 blocks — moments, tournament tables, charts.
 */
require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/k2_player_game_row.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_tournament_videos_lib.php';
require_once __DIR__ . '/amiga_player_load.php';
require_once __DIR__ . '/amiga_player_matchup_lib.php';
require_once __DIR__ . '/amiga_performance_rating.php';
require_once __DIR__ . '/amiga_player_moments_lib.php';
require_once __DIR__ . '/amiga_participation_placement.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/k2_league_table_render.php';
require_once __DIR__ . '/k2_amiga_country_flag.php';
require_once __DIR__ . '/amiga_wc_podium_th.php';

const AMIGA_TOURNAMENT_EVENT_STATS_ANCHOR_COL = 1;
const AMIGA_TOURNAMENT_EVENT_STATS_DEFAULT_SORT_COL = 12;
const AMIGA_PLAYER_TOURNAMENT_HISTORY_ANCHOR_COL = 1;
const AMIGA_PLAYER_TOURNAMENT_HISTORY_DEFAULT_SORT_COL = 0;
/** Date col — quiet body on default load only (no game ID). */
const AMIGA_PLAYER_TOURNAMENT_HISTORY_QUIET_DATE_COL = 0;
const AMIGA_TOURNAMENT_INDEX_ANCHOR_COL = 1;
const AMIGA_TOURNAMENT_INDEX_DEFAULT_SORT_COL = 0;
/** Date col — quiet body on default load only (no game ID). */
const AMIGA_TOURNAMENT_INDEX_QUIET_DATE_COL = 0;

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
 * WC podium metal label markup for Finish cell (1–3) — gradient Gold/Silver/Bronze.
 *
 * @param array<string, mixed> $row
 */
function amiga_profile_wc_podium_medal_cell(array $row): string
{
    $finish = amiga_profile_row_event_finish($row);
    if ($finish === null || $finish < 1 || $finish > 3) {
        return '';
    }

    return amiga_wc_podium_metal_label_markup($finish);
}

/**
 * WC event-stats Finish cell — podium metal labels (1–3); ordinals from 4th up (no Perfect suffix).
 *
 * @param array<string, mixed> $row
 */
function amiga_profile_wc_event_stats_finish_cell(array $row): string
{
    $finish = amiga_profile_row_event_finish($row);
    if ($finish === null) {
        return '—';
    }
    if ($finish >= 1 && $finish <= 3) {
        return amiga_profile_wc_podium_medal_cell($row);
    }

    return htmlspecialchars(amiga_profile_event_finish_ordinal_label($row), ENT_QUOTES, 'UTF-8');
}

/** Unsortable Medal column header — Status league SVG pattern (non-WC tables). */
function amiga_profile_tournament_podium_medal_th_markup(): string
{
    return '<th class="k2-status-table__medal" scope="col"><span class="visually-hidden">Award</span></th>';
}

/**
 * Status league SVG medal for podium finish (1–3); empty for 4+ or unknown.
 *
 * @param array<string, mixed> $row
 */
function amiga_profile_tournament_podium_medal_cell(array $row): string
{
    $finish = amiga_profile_row_event_finish($row);
    if ($finish === null || $finish < 1 || $finish > 3) {
        return '';
    }

    return k2_status_league_podium_medal($finish);
}

/**
 * @param array{max_rated_victim: ?array<string, mixed>, moments: list<array<string, mixed>>} $bundle from amiga_player_moments_load()
 */
function amiga_profile_render_moments(array $bundle, int $playerId = 0): void
{
    $maxVictim = is_array($bundle['max_rated_victim'] ?? null) ? $bundle['max_rated_victim'] : null;
    $moments = is_array($bundle['moments'] ?? null) ? $bundle['moments'] : [];
    if ($maxVictim === null && $moments === []) {
        return;
    }
    ?>
<section class="k2-amiga-profile-moments">
	<h3 class="k2-panel-heading">Moments</h3>
	<div class="pm3-moments pm3-moments--mosaic">
		<div class="pm3-moments__grid">
		<?php if ($maxVictim !== null) {
            $opponentId = (int) ($maxVictim['opponent_id'] ?? 0);
            $gamesHref = amiga_player_moment_games_href($playerId, $opponentId, (int) ($maxVictim['game_id'] ?? 0));
            $victimRating = $maxVictim['victim_rating'] ?? null;
            ?>
			<article class="pm3-moment pm3-moment--giant">
				<span class="pm3-moment__glyph" aria-hidden="true">🗡️</span>
				<span class="pm3-moment__tag">Giant-killing</span>
				<h3 class="pm3-moment__label">Best scalp<?php
                    echo $victimRating !== null && (int) $victimRating > 0
                        ? ' · ' . (int) $victimRating . ' Elo'
                        : '';
                ?></h3>
				<p class="pm3-moment__score">
					<a class="k2-link-star" href="<?php echo k2_h($gamesHref); ?>"><?php echo k2_h((string) ($maxVictim['score'] ?? '')); ?></a>
				</p>
				<p class="pm3-moment__meta">
					<span class="<?php echo k2_h((string) ($maxVictim['outcome_class'] ?? '')); ?>"><?php echo k2_h((string) ($maxVictim['outcome'] ?? '')); ?></span>
					· vs <?php echo k2_amiga_player_link($opponentId, (string) ($maxVictim['opponent_name'] ?? '')); ?>
					· <?php echo k2_h((string) ($maxVictim['date'] ?? '')); ?>
				</p>
			</article>
		<?php } ?>
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
					<?php if ($peakRating !== null) { ?>
					<span class="k2-link-star"><?php echo (int) $peakRating; ?></span>
					<?php } else {
                        echo k2_h((string) ($moment['score'] ?? ''));
                    } ?>
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

/**
 * Sort attrs for peak-rating LB Peak rank column — tie-break by peak rank date (first attainment wins).
 */
function amiga_lb_peak_elo_rank_cell_sort_attrs(mixed $peakRank, mixed $peakRankDate): string
{
    $rankVal = (!k2_db_is_null($peakRank) && (int) $peakRank >= 1) ? (int) $peakRank : 999999;
    if ($peakRankDate === null || $peakRankDate === '') {
        $dateTie = '9999999999';
    } else {
        $dateTie = amiga_profile_event_date_sort_value(['event_date' => $peakRankDate]);
    }

    return ' data-k2-sort-value="' . $rankVal . '" data-k2-sort-tie-value="' . htmlspecialchars($dateTie, ENT_QUOTES, 'UTF-8') . '"';
}

function amiga_profile_tournament_finish_rank_label(array $row): string
{
    $label = amiga_profile_event_finish_ordinal_label($row);
    if ((int) ($row['is_perfect_event'] ?? 0) === 1) {
        $label .= ' · Perfect';
    }

    return $label;
}

/**
 * Numeric sort key for Finish column (event_finish_position); unknown → last on asc.
 *
 * @param array<string, mixed> $row
 */
function amiga_profile_tournament_finish_sort_value(array $row): string
{
    $finish = amiga_profile_row_event_finish($row);

    return $finish !== null ? (string) $finish : '999999';
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

/**
 * Perf. rating column — finite integer, ∞ for perfect win events, dash otherwise.
 *
 * @param array<string, mixed> $row participation / event-stats row
 */
function amiga_profile_tournament_perf_rating_cell(mixed $rating, array $row): string
{
    $isPerfect = (int) ($row['is_perfect_event'] ?? 0) === 1;

    return performance_rating_display_cell($rating, $isPerfect, k2_fmt_dash());
}

/**
 * @param array<string, mixed> $row
 */
function amiga_profile_tournament_perf_rating_sort_value(mixed $rating, array $row): string
{
    if ((int) ($row['is_perfect_event'] ?? 0) === 1) {
        return PERFORMANCE_RATING_INFINITY_SORT_VALUE;
    }
    if ($rating === null || $rating === '' || k2_db_is_null($rating)) {
        return '-1';
    }

    return (string) (int) round((float) $rating);
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
    $isDefaultSortView = k2_table_is_default_client_sort_view();
    $dateEmphasisCol = k2_table_sort_col_for_emphasis(
        AMIGA_PLAYER_TOURNAMENT_HISTORY_QUIET_DATE_COL,
        $defaultSortCol,
        [AMIGA_PLAYER_TOURNAMENT_HISTORY_QUIET_DATE_COL],
        $isDefaultSortView
    );
    $dateCellClass = k2_table_quiet_date_cell_class(
        AMIGA_PLAYER_TOURNAMENT_HISTORY_QUIET_DATE_COL,
        $defaultSortCol,
        AMIGA_PLAYER_TOURNAMENT_HISTORY_DEFAULT_SORT_COL,
        $isDefaultSortView,
        'k2-table-cell--right'
    );
    $tableClass = k2_table_ranked_sortable_class('k2-table--player-tournaments') . ' k2-status-table--podium';
    $medalCol = 13;
    $ratingCol = 14;
    $adjustCol = 15;
    $newRatingCol = 16;
    $perfCol = 17;
    $skipInitialSort = $defaultSortCol === AMIGA_PLAYER_TOURNAMENT_HISTORY_DEFAULT_SORT_COL && $defaultSortDir === 'desc';
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_PLAYER_TOURNAMENT_HISTORY_QUIET_DATE_COL]); ?><?php echo $skipInitialSort ? ' data-k2-skip-initial-sort="1"' : ''; ?>>
	<thead>
		<tr>
			<th<?php echo k2_table_sortable_th_attr(0, $defaultSortCol, $defaultSortDir, 'k2-table-cell--right'); ?> data-k2-sort="number">Date</th>
			<th<?php echo k2_table_sortable_th_attr(1, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Tournament</th>
			<th<?php echo k2_table_sortable_th_attr(2, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Games</th>
			<th<?php echo k2_table_sortable_th_attr(3, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Wins in this event (all phases).">W</th>
			<th<?php echo k2_table_sortable_th_attr(4, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Draws in this event (all phases).">D</th>
			<th<?php echo k2_table_sortable_th_attr(5, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Losses in this event (all phases).">L</th>
			<th<?php echo k2_table_sortable_th_attr(6, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Goals scored in this event (all phases).">GF</th>
			<th<?php echo k2_table_sortable_th_attr(7, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Goals conceded in this event (all phases).">GA</th>
			<th<?php echo k2_table_sortable_th_attr(8, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">GD</th>
			<th<?php echo k2_table_sortable_th_attr(9, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Average goals scored per game in this event.">GF/g</th>
			<th<?php echo k2_table_sortable_th_attr(10, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Average goals conceded per game in this event.">GA/g</th>
			<th<?php echo k2_table_sortable_th_attr(11, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Result points across all games in this event (3 per win, 1 per draw). Phase league tables use amiga_tournament_standings.">Pts</th>
			<th<?php echo k2_table_sortable_th_attr(12, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">Finish</th>
			<?php echo amiga_profile_tournament_podium_medal_th_markup(); ?>
			<th<?php echo k2_table_sortable_th_attr($ratingCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Elo rating before this event.">Rating</th>
			<th<?php echo k2_table_sortable_th_attr($adjustCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Rating points gained or lost in this event.">Adj.</th>
			<th<?php echo k2_table_sortable_th_attr($newRatingCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Elo rating after this event.">New rating</th>
			<th<?php echo k2_table_sortable_th_attr($perfCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-tooltip-label="<?php echo htmlspecialchars(amiga_perf_rating_column_label(), ENT_QUOTES, 'UTF-8'); ?>" data-k2-help="<?php echo htmlspecialchars(amiga_perf_rating_column_help(), ENT_QUOTES, 'UTF-8'); ?>">Perf. rating</th>
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
			<td<?php echo k2_table_body_td_attr(0, $anchorCol, $dateEmphasisCol, $dateCellClass); ?> data-k2-sort-value="<?php echo amiga_profile_event_date_sort_value($t); ?>"><?php echo amiga_profile_format_event_date($t['event_date'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr(1, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php
                echo k2_amiga_lb_tournament_cell((int) $t['id'], (string) $t['name'], $hostCountry);
            ?></td>
			<td<?php echo k2_table_body_td_attr(2, $anchorCol, $defaultSortCol); ?>><?php echo k2_fmt_games_played($games); ?></td>
			<td<?php echo k2_table_body_td_attr(3, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($wins, 'win'); ?></td>
			<td<?php echo k2_table_body_td_attr(4, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($draws, 'draw'); ?></td>
			<td<?php echo k2_table_body_td_attr(5, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($losses, 'loss'); ?></td>
			<td<?php echo k2_table_body_td_attr(6, $anchorCol, $defaultSortCol); ?>><?php echo $goalsFor; ?></td>
			<td<?php echo k2_table_body_td_attr(7, $anchorCol, $defaultSortCol); ?>><?php echo $goalsAgainst; ?></td>
			<td<?php echo k2_table_body_td_attr(8, $anchorCol, $defaultSortCol); ?>><?php echo $goalDiff > 0 ? '+' . $goalDiff : (string) $goalDiff; ?></td>
			<td<?php echo k2_table_body_td_attr(9, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_avg_goals_cell($t['avg_goals_for'] ?? null, $games); ?></td>
			<td<?php echo k2_table_body_td_attr(10, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_avg_goals_cell($t['avg_goals_against'] ?? null, $games); ?></td>
			<td<?php echo k2_table_body_td_attr(11, $anchorCol, $defaultSortCol); ?>><?php echo $points; ?></td>
			<td<?php echo k2_table_body_td_attr(12, $anchorCol, $defaultSortCol); ?> data-k2-sort-value="<?php echo k2_h(amiga_profile_tournament_finish_sort_value($t)); ?>"><?php echo htmlspecialchars($finishRank, ENT_QUOTES, 'UTF-8'); ?></td>
			<td<?php echo k2_table_body_td_attr($medalCol, $anchorCol, $defaultSortCol, 'k2-status-table__medal'); ?>><?php echo amiga_profile_tournament_podium_medal_cell($t); ?></td>
			<td<?php echo k2_table_body_td_attr($ratingCol, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_cell($t['rating_before'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr($adjustCol, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_delta_cell($t['rating_delta'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr($newRatingCol, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_cell($t['rating_after'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr($perfCol, $anchorCol, $defaultSortCol); ?> data-k2-sort-value="<?php echo k2_h(amiga_profile_tournament_perf_rating_sort_value($t['performance_rating'] ?? null, $t)); ?>"><?php echo amiga_profile_tournament_perf_rating_cell($t['performance_rating'] ?? null, $t); ?></td>
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
    $isDefaultSortView = k2_table_is_default_client_sort_view();
    $dateEmphasisCol = k2_table_sort_col_for_emphasis(
        AMIGA_TOURNAMENT_INDEX_QUIET_DATE_COL,
        $defaultSortCol,
        [AMIGA_TOURNAMENT_INDEX_QUIET_DATE_COL],
        $isDefaultSortView
    );
    $dateCellClass = k2_table_quiet_date_cell_class(
        AMIGA_TOURNAMENT_INDEX_QUIET_DATE_COL,
        $defaultSortCol,
        AMIGA_TOURNAMENT_INDEX_DEFAULT_SORT_COL,
        $isDefaultSortView,
        'k2-table-cell--right k2-tournament-index-date'
    );
    $tableClass = k2_table_ranked_sortable_class('k2-table--tournament-index');
    $skipInitialSort = $defaultSortCol === AMIGA_TOURNAMENT_INDEX_DEFAULT_SORT_COL && $defaultSortDir === 'desc';
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>"<?php echo k2_table_quiet_default_sort_col_attr([AMIGA_TOURNAMENT_INDEX_QUIET_DATE_COL]); ?><?php echo $skipInitialSort ? ' data-k2-skip-initial-sort="1"' : ''; ?>>
<thead>
    <tr>
        <th<?php echo k2_table_sortable_th_attr(0, $defaultSortCol, $defaultSortDir, 'k2-table-cell--right k2-tournament-index-date'); ?> data-k2-sort="number">Date</th>
        <th<?php echo k2_table_sortable_th_attr(1, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Tournament</th>
        <th<?php echo k2_table_sortable_th_attr(2, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center k2-table-cell--video-glyph'); ?> data-k2-sort="number"></th>
        <th<?php echo k2_table_sortable_th_attr(3, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center'); ?> data-k2-sort="number">Players</th>
        <th<?php echo k2_table_sortable_th_attr(4, $defaultSortCol, $defaultSortDir, 'k2-table-cell--center'); ?> data-k2-sort="number">Games</th>
        <th<?php echo k2_table_sortable_th_attr(5, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Winner</th>
        <th<?php echo k2_table_sortable_th_attr(6, $defaultSortCol, $defaultSortDir, 'k2-table-cell--left'); ?> data-k2-sort="text">Format</th>
    </tr>
</thead>
<tbody class="black">
<?php if ($rows === []) { ?>
    <tr>
        <td colspan="7" class="k2-table-cell--left" style="color:var(--k2-text-secondary)">No tournaments match this filter.</td>
    </tr>
<?php } ?>
<?php foreach ($rows as $row) {
    $games = (int) $row['game_count'];
    $players = (int) $row['standing_players'];
    $hasStandings = (int) ($row['standing_rows'] ?? 0) > 0;
    $kind = amiga_tournament_index_format_kind($row);
    $formatLabel = amiga_tournament_index_format_label($kind);
    $hostCountry = (string) ($row['country'] ?? '');
    $winnerId = (int) ($row['winner_player_id'] ?? 0);
    $winnerName = trim((string) ($row['winner_name'] ?? ''));
    $winnerCountry = trim((string) ($row['winner_country'] ?? ''));
    $winnerCell = $winnerId > 0 && $winnerName !== ''
        ? k2_amiga_lb_player_cell($winnerId, $winnerName, $winnerCountry)
        : k2_fmt_dash();
    $winnerSortValue = $winnerName !== '' ? $winnerName : '';
    ?>
    <tr>
        <td<?php echo k2_table_body_td_attr(0, $anchorCol, $dateEmphasisCol, $dateCellClass); ?> data-k2-sort-value="<?php echo amiga_profile_event_date_sort_value([
            'event_date' => $row['event_date'] ?? null,
            'event_chrono' => $row['chrono'] ?? null,
        ]); ?>"><?php echo amiga_profile_format_event_date($row['event_date'] ?? null); ?></td>
        <td<?php echo k2_table_body_td_attr(1, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php
            if ($hasStandings) {
                echo k2_amiga_lb_tournament_cell((int) $row['id'], (string) $row['name'], $hostCountry);
            } else {
                $nameHtml = k2_h((string) $row['name']);
                $flag = k2_amiga_country_flag_link($hostCountry);
                echo $flag !== '' ? '<span class="k2-amiga-wc-podium-player">' . $flag . $nameHtml . '</span>' : $nameHtml;
            }
        ?></td>
        <td<?php echo k2_table_body_td_attr(2, $anchorCol, $defaultSortCol, 'k2-table-cell--center k2-table-cell--video-glyph'); ?> data-k2-sort-value="<?php echo amiga_tournament_has_videos((int) $row['id']) ? '1' : '0'; ?>"><?php echo amiga_tournament_video_column_cell((int) $row['id']); ?></td>
        <td<?php echo k2_table_body_td_attr(3, $anchorCol, $defaultSortCol, 'k2-table-cell--center'); ?>><?php echo $hasStandings ? (string) $players : '—'; ?></td>
        <td<?php echo k2_table_body_td_attr(4, $anchorCol, $defaultSortCol, 'k2-table-cell--center'); ?>><?php echo $games; ?></td>
        <td<?php echo k2_table_body_td_attr(5, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($winnerSortValue); ?>"><?php echo $winnerCell; ?></td>
        <td<?php echo k2_table_body_td_attr(6, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>>
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
    $finishCol = AMIGA_TOURNAMENT_EVENT_STATS_DEFAULT_SORT_COL;
    $hasPodiumMedalCol = !$isWorldCup;
    $medalCol = 13;
    $ratingCol = $hasPodiumMedalCol ? 14 : 13;
    $adjustCol = $hasPodiumMedalCol ? 15 : 14;
    $newRatingCol = $hasPodiumMedalCol ? 16 : 15;
    $perfCol = $hasPodiumMedalCol ? 17 : 16;
    $defaultSortCol = k2_table_default_sort_col_from_request($finishCol);
    $defaultSortDir = k2_table_default_sort_dir_from_request('asc');
    $tableClass = k2_table_ranked_sortable_class('k2-table--tournament-event-stats');
    if ($hasPodiumMedalCol) {
        $tableClass .= ' k2-status-table--podium';
    }
    $finishHelp = $isWorldCup
        ? 'Holistic event finish from event_finish_position. Podium shows Gold, Silver, or Bronze; 4th and below show ordinal rank.'
        : null;
    ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h($tableClass); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $anchorCol; ?>" data-k2-default-sort="<?php echo $defaultSortCol; ?>" data-k2-default-direction="<?php echo k2_h($defaultSortDir); ?>" data-k2-skip-initial-sort="1">
	<thead>
		<tr>
			<th<?php echo k2_table_sortable_th_attr(0, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number">#</th>
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
			<th<?php echo k2_table_sortable_th_attr($finishCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number"<?php echo $finishHelp !== null ? ' data-k2-help="' . htmlspecialchars($finishHelp, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>Finish</th>
<?php if ($hasPodiumMedalCol) { ?>
			<?php echo amiga_profile_tournament_podium_medal_th_markup(); ?>
<?php } ?>
			<th<?php echo k2_table_sortable_th_attr($ratingCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Elo rating before this event.">Rating</th>
			<th<?php echo k2_table_sortable_th_attr($adjustCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Rating points gained or lost in this event.">Adjustment</th>
			<th<?php echo k2_table_sortable_th_attr($newRatingCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-help="Elo rating after this event.">New rating</th>
			<th<?php echo k2_table_sortable_th_attr($perfCol, $defaultSortCol, $defaultSortDir); ?> data-k2-sort="number" data-k2-tooltip-label="<?php echo htmlspecialchars(amiga_perf_rating_column_label(), ENT_QUOTES, 'UTF-8'); ?>" data-k2-help="<?php echo htmlspecialchars(amiga_perf_rating_column_help(), ENT_QUOTES, 'UTF-8'); ?>">Perf. rating</th>
		</tr>
	</thead>
	<tbody class="black">
	<?php
    $rank = 1;
    foreach ($rows as $row) {
        $playerId = (int) ($row['player_id'] ?? 0);
        $games = (int) ($row['games'] ?? 0);
        $wins = (int) ($row['wins'] ?? 0);
        $draws = (int) ($row['draws'] ?? 0);
        $losses = (int) ($row['losses'] ?? 0);
        $goalsFor = (int) ($row['goals_for'] ?? 0);
        $goalsAgainst = (int) ($row['goals_against'] ?? 0);
        $goalDiff = $goalsFor - $goalsAgainst;
        $points = (int) ($row['event_points'] ?? 0);
        $finishSortValue = amiga_profile_tournament_finish_sort_value($row);
        $playerCountry = (string) ($row['player_country'] ?? '');
        ?>
		<tr>
			<td<?php echo k2_table_body_td_attr(0, $anchorCol, $defaultSortCol); ?>><?php echo $rank; ?></td>
			<td<?php echo k2_table_body_td_attr(1, $anchorCol, $defaultSortCol, 'k2-table-cell--left'); ?>><?php echo k2_amiga_lb_player_cell($playerId, (string) ($row['player_name'] ?? ''), $playerCountry); ?></td>
			<td<?php echo k2_table_body_td_attr(2, $anchorCol, $defaultSortCol); ?>><?php echo k2_fmt_games_played($games); ?></td>
			<td<?php echo k2_table_body_td_attr(3, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($wins, 'win'); ?></td>
			<td<?php echo k2_table_body_td_attr(4, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($draws, 'draw'); ?></td>
			<td<?php echo k2_table_body_td_attr(5, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($losses, 'loss'); ?></td>
			<td<?php echo k2_table_body_td_attr(6, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($goalsFor, 'win'); ?></td>
			<td<?php echo k2_table_body_td_attr(7, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_wdl_cell($goalsAgainst, 'loss'); ?></td>
			<td<?php echo k2_table_body_td_attr(8, $anchorCol, $defaultSortCol); ?>><?php echo $goalDiff > 0 ? '+' . $goalDiff : (string) $goalDiff; ?></td>
			<td<?php echo k2_table_body_td_attr(9, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_avg_goals_cell($row['avg_goals_for'] ?? null, $games); ?></td>
			<td<?php echo k2_table_body_td_attr(10, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_avg_goals_cell($row['avg_goals_against'] ?? null, $games); ?></td>
			<td<?php echo k2_table_body_td_attr(11, $anchorCol, $defaultSortCol); ?>><?php echo $points; ?></td>
			<td<?php echo k2_table_body_td_attr($finishCol, $anchorCol, $defaultSortCol); ?> data-k2-sort-value="<?php echo k2_h($finishSortValue); ?>"><?php
            if ($isWorldCup) {
                echo amiga_profile_wc_event_stats_finish_cell($row);
            } else {
                echo htmlspecialchars(amiga_profile_tournament_finish_rank_label($row), ENT_QUOTES, 'UTF-8');
            }
        ?></td>
<?php if ($hasPodiumMedalCol) { ?>
			<td<?php echo k2_table_body_td_attr($medalCol, $anchorCol, $defaultSortCol, 'k2-status-table__medal'); ?>><?php echo amiga_profile_tournament_podium_medal_cell($row); ?></td>
<?php } ?>
			<td<?php echo k2_table_body_td_attr($ratingCol, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_cell($row['rating_before'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr($adjustCol, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_delta_cell($row['rating_delta'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr($newRatingCol, $anchorCol, $defaultSortCol); ?>><?php echo amiga_profile_tournament_rating_cell($row['rating_after'] ?? null); ?></td>
			<td<?php echo k2_table_body_td_attr($perfCol, $anchorCol, $defaultSortCol); ?> data-k2-sort-value="<?php echo k2_h(amiga_profile_tournament_perf_rating_sort_value($row['performance_rating'] ?? null, $row)); ?>"><?php echo amiga_profile_tournament_perf_rating_cell($row['performance_rating'] ?? null, $row); ?></td>
		</tr>
	<?php
        $rank++;
    } ?>
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

function amiga_profile_render_charts(int $playerId): void
{
    ?>
<section class="k2-amiga-profile-charts" aria-label="Charts">
	<h3 class="k2-panel-heading">Charts</h3>
<?php
    amiga_profile_render_rating_chart($playerId);
    amiga_profile_render_rank_chart($playerId);
    ?>
</section>
    <?php
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
<div class="k2-amiga-profile-chart">
	<div class="player-rating-chart k2-chart-panel" data-player-id="<?php echo $playerId; ?>" data-realm="amiga"<?php echo $asAttr; ?>>
		<h3 class="k2-panel-heading">Elo rating</h3>
		<p class="k2-chart-block__hint">Calendar view: end-of-day rating after each tournament day. Tournament # view: one point per finalized event.</p>
		<div class="pm3d-chart-toolbar player-rating-chart__toolbar">
			<div class="player-rating-chart__toolbar-row">
				<div class="pm3d-rating-toggle" role="tablist" aria-label="Rating chart view">
					<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-view="date">By date</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-view="game">By tournament #</button>
				</div>
			</div>
			<div class="player-rating-chart__toolbar-row">
				<div class="pm3d-rating-toggle player-rating-chart__line-style" role="tablist" aria-label="Rating line style">
					<button type="button" class="pm3d-rating-toggle__btn is-active" role="tab" aria-selected="true" data-line-style="stepped">Stepwise</button>
					<button type="button" class="pm3d-rating-toggle__btn" role="tab" aria-selected="false" data-line-style="smooth">Connected</button>
				</div>
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
</div>
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
<div class="k2-amiga-profile-chart">
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
</div>
    <?php
}
