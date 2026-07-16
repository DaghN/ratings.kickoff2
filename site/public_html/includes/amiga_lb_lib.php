<?php
/**
 * Amiga leaderboard wings — shared read-path SQL (amiga_player_current).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/k2_table_helpers.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/lb_player_filters.php';

/** WHERE clause for career-stat wings (players with at least one rated game). */
function amiga_lb_player_where_sql(): string
{
    return 's.NumberGames > 0';
}

/** Amiga rating LB column indices (0-based; Δ column always present). */
const AMIGA_LB_RATING_COL_DELTA = 3;
const AMIGA_LB_RATING_COL_GAMES = 4;
const AMIGA_LB_RATING_COL_WINS = 5;
const AMIGA_LB_RATING_COL_WIN_RATE = 8;
const AMIGA_LB_RATING_COL_OPP_AVG = 9;

/** Load time-travel context for leaderboard pages (after DB connect). */
function amiga_lb_context(mysqli $con): AmigaSnapshotContext
{
    return amiga_snapshot_context_from_request($con);
}

/**
 * Amiga leaderboard wing URL that scrolls to the table top (profile hero stat links).
 *
 * @param array<string, scalar> $query
 */
function amiga_lb_table_href(string $wingPath, array $query = []): string
{
    return amiga_url_with_context($wingPath, $query) . k2_lb_table_anchor_hash();
}

/**
 * Amiga rating LB URL that scrolls to a player's row (profile hero rank / rating links).
 *
 * @param array<string, scalar> $query
 */
function amiga_lb_rating_player_href(int $playerId, array $query = []): string
{
    $path = amiga_url_with_context('/amiga/leaderboards/rating.php', $query);
    if ($playerId < 1) {
        return $path . k2_lb_table_anchor_hash();
    }

    return $path . k2_lb_player_row_anchor_hash($playerId);
}

/**
 * Amiga goals LB URL scrolled to a player's row (profile mosaic / comparison links).
 *
 * @param array<string, scalar> $extraQuery
 */
function amiga_lb_goals_player_href(int $playerId, int $sortCol, string $dir = 'desc', array $extraQuery = []): string
{
    $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
    $query = array_merge(['k2_sort' => (string) $sortCol, 'k2_dir' => $dir], $extraQuery);
    $path = amiga_url_with_context('/amiga/leaderboards/goals.php', $query);
    if ($playerId < 1) {
        return $path . k2_lb_table_anchor_hash();
    }

    return $path . k2_lb_player_row_anchor_hash($playerId);
}

/**
 * Amiga calendar-geo LB URL scrolled to a player's row (profile mosaic peak links).
 *
 * @param array<string, scalar> $extraQuery
 */
function amiga_lb_calendar_geo_player_href(int $playerId, int $sortCol, string $dir = 'desc', array $extraQuery = []): string
{
    $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
    $query = array_merge(['k2_sort' => (string) $sortCol, 'k2_dir' => $dir], $extraQuery);
    $path = amiga_url_with_context('/amiga/leaderboards/calendar-geo.php', $query);
    if ($playerId < 1) {
        return $path . k2_lb_table_anchor_hash();
    }

    return $path . k2_lb_player_row_anchor_hash($playerId);
}

/**
 * Amiga peak-rating LB URL scrolled to a player's row (profile mosaic comparison links).
 *
 * @param array<string, scalar> $extraQuery
 */
function amiga_lb_peak_rating_player_href(int $playerId, int $sortCol, string $dir = 'desc', array $extraQuery = []): string
{
    $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
    $query = array_merge(['k2_sort' => (string) $sortCol, 'k2_dir' => $dir], $extraQuery);
    $path = amiga_url_with_context('/amiga/leaderboards/peak-rating.php', $query);
    if ($playerId < 1) {
        return $path . k2_lb_table_anchor_hash();
    }

    return $path . k2_lb_player_row_anchor_hash($playerId);
}

/** Default ORDER BY tail for Amiga goals LB (no leading ORDER BY). */
function amiga_lb_goals_default_order_sql(): string
{
    return 's.GoalsFor DESC, s.Rating DESC, p.id ASC';
}

/**
 * Sortable column index → SQL expression for Amiga goals LB SSR order.
 *
 * @return array<int, string>
 */
function amiga_lb_goals_order_column_map(): array
{
    return [
        1 => 'p.name',
        2 => 's.Rating',
        3 => 's.NumberGames',
        4 => 's.GoalsFor',
        5 => 's.GoalsAgainst',
        6 => '(s.GoalsFor - s.GoalsAgainst)',
        7 => 's.AverageGoalsFor',
        8 => 's.AverageGoalsAgainst',
        9 => '(s.GoalsFor - s.GoalsAgainst) / NULLIF(s.NumberGames, 0)',
        10 => '(CASE WHEN s.GoalRatio IS NULL OR s.GoalRatio < 0 THEN NULL ELSE s.GoalRatio END)',
        11 => 's.MostGoalsScored',
        12 => 's.MostGoalsConceded',
        13 => 's.BiggestWinDifference',
        14 => 's.BiggestLossDifference',
        15 => 's.BiggestSumOfGoals',
        16 => 's.BiggestDrawSum',
    ];
}

/**
 * Amiga double-digits LB URL scrolled to a player's row (profile mosaic ratio links).
 *
 * @param array<string, scalar> $extraQuery
 */
function amiga_lb_double_digits_player_href(int $playerId, int $sortCol, string $dir = 'desc', array $extraQuery = []): string
{
    $dir = strtolower($dir) === 'asc' ? 'asc' : 'desc';
    $query = array_merge(['k2_sort' => (string) $sortCol, 'k2_dir' => $dir], $extraQuery);
    $path = amiga_url_with_context('/amiga/leaderboards/double-digits.php', $query);
    if ($playerId < 1) {
        return $path . k2_lb_table_anchor_hash();
    }

    return $path . k2_lb_player_row_anchor_hash($playerId);
}

/** Default ORDER BY tail for Amiga double-digits LB (no leading ORDER BY). */
function amiga_lb_double_digits_default_order_sql(): string
{
    return 's.DoubleDigits DESC, s.Rating DESC, p.id ASC';
}

/**
 * Sortable column index → SQL expression for Amiga double-digits LB SSR order.
 *
 * @return array<int, string>
 */
function amiga_lb_double_digits_order_column_map(): array
{
    $ratio = static fn (string $col): string => "(CASE WHEN s.$col IS NULL OR s.$col < 0 THEN NULL ELSE s.$col END)";

    return [
        1 => 'p.name',
        2 => 's.Rating',
        3 => 's.NumberGames',
        4 => 's.DoubleDigits',
        5 => 's.CleanSheets',
        6 => $ratio('DoubleDigitsRatio'),
        7 => $ratio('CleanSheetsRatio'),
        8 => 's.DoubleDigitsConceded',
        9 => 's.CleanSheetsConceded',
        10 => $ratio('DoubleDigitsConcededRatio'),
        11 => $ratio('CleanSheetsConcededRatio'),
    ];
}

/** Default ORDER BY tail for Amiga victims LB (no leading ORDER BY). */
function amiga_lb_victims_default_order_sql(): string
{
    return 's.DifferentOpponents DESC, s.Rating DESC';
}

/**
 * Sortable column index → SQL expression for Amiga victims LB SSR order.
 *
 * @return array<int, string>
 */
function amiga_lb_victims_order_column_map(bool $timeTravelActive = false): array
{
    if ($timeTravelActive) {
        return [
            1 => 'p.name',
            2 => 's.Rating',
            3 => 's.NumberGames',
            4 => 's.DifferentOpponents',
            5 => 's.DifferentVictims',
            6 => 's.DifferentCulprits',
            7 => 's.DoubleDigitsVictims',
            8 => 's.DoubleDigitsCulprits',
            9 => 's.CleanSheetsVictims',
            10 => 's.CleanSheetsCulprits',
            11 => 'COALESCE(inv.MostGoalsConcededVictims, 0)',
            12 => 'COALESCE(inv.BiggestLossVictims, 0)',
            13 => 'COALESCE(inv.MostGoalsScoredCulprits, 0)',
            14 => 'COALESCE(inv.BiggestWinCulprits, 0)',
        ];
    }

    return [
        1 => 'p.name',
        2 => 's.Rating',
        3 => 's.NumberGames',
        4 => 's.DifferentOpponents',
        5 => 's.DifferentVictims',
        6 => 's.DifferentCulprits',
        7 => 's.DoubleDigitsVictims',
        8 => 's.DoubleDigitsCulprits',
        9 => 's.CleanSheetsVictims',
        10 => 's.CleanSheetsCulprits',
        11 => 's.MostGoalsConcededVictims',
        12 => 's.BiggestLossVictims',
        13 => 's.MostGoalsScoredCulprits',
        14 => 's.BiggestWinCulprits',
    ];
}

/**
 * Victims LB stat cell → player chronology Made-it entry (C1 calm cell link).
 * Same destinations as profile Victims & Culprits mosaic; link only when count > 0.
 *
 * @param bool $anchorBlue Wing anchor column (Opponents) — editorial `.blue` ink at rest per hub LB pattern.
 */
function amiga_lb_victims_chronology_cell_html(
    int $playerId,
    mixed $count,
    int $games,
    string $href,
    bool $anchorBlue = false
): string {
    $display = k2_fmt_count($count, $games);
    if ($display === '-' || $display === '—') {
        return k2_h($display);
    }

    $numericCount = (int) $display;
    $linkClass = $anchorBlue ? 'k2-table-cell-link blue' : 'k2-table-cell-link';
    if ($playerId > 0 && $numericCount > 0 && $href !== '') {
        return '<a class="' . $linkClass . '" href="' . k2_h($href) . '">'
            . k2_h($display) . '</a>';
    }

    if ($anchorBlue) {
        return '<span class="blue">' . k2_h($display) . '</span>';
    }

    return k2_h($display);
}

/**
 * Hub LB cell → player chronology Made-it entry (C1 calm cell link).
 * Same destinations as profile Calendar & geography country mosaic rows; link only when count > 0.
 */
function amiga_lb_chronology_inventory_cell_html(
    int $playerId,
    int $count,
    string $href,
): string {
    $display = (string) $count;
    if ($playerId > 0 && $count > 0 && $href !== '') {
        return '<a class="k2-table-cell-link" href="' . k2_h($href) . '">'
            . k2_h($display) . '</a>';
    }

    return k2_h($display);
}

/**
 * Player games tab inventory href (profile mosaic parity).
 *
 * @param 'all'|'win'|'draw'|'loss' $resultFilter
 */
function amiga_lb_player_games_inventory_href(
    int $playerId,
    string $resultFilter = 'all',
    ?string $sortKey = null,
    ?string $sortDir = null,
    int $heroGfMin = -1,
    int $heroGfMax = -1,
    int $heroGaMin = -1,
    int $heroGaMax = -1,
): string {
    if ($playerId < 1) {
        return '';
    }

    require_once __DIR__ . '/amiga_player_games_lib.php';
    require_once __DIR__ . '/k2_safety.php';

    $params = ['id' => $playerId];
    $resultFilter = amiga_games_valid_result($resultFilter);
    if ($resultFilter !== 'all') {
        $params['result'] = $resultFilter;
    }
    if ($sortKey !== null && $sortKey !== '') {
        $params['sort'] = $sortKey === 'for' ? 'goals_for' : $sortKey;
    }
    if ($sortDir !== null && $sortDir !== '') {
        $params['dir'] = amiga_games_valid_direction($sortDir);
    }
    if ($heroGfMin >= 0) {
        $params['gf_min'] = amiga_games_valid_hero_goals_bound($heroGfMin);
    }
    if ($heroGfMax >= 0) {
        $params['gf_max'] = amiga_games_valid_hero_goals_bound($heroGfMax);
    }
    if ($heroGaMin >= 0) {
        $params['ga_min'] = amiga_games_valid_hero_goals_bound($heroGaMin);
    }
    if ($heroGaMax >= 0) {
        $params['ga_max'] = amiga_games_valid_hero_goals_bound($heroGaMax);
    }

    return amiga_games_build_url($params) . k2_player_matching_games_anchor_fragment();
}

/**
 * Hub LB cell → player games tab inventory (C1 calm cell link).
 *
 * @param 'all'|'win'|'draw'|'loss' $resultFilter
 */
function amiga_lb_games_inventory_cell_html(
    int $playerId,
    int $games,
    string $display,
    string $resultFilter = 'all',
    ?string $sortKey = null,
    ?string $sortDir = null,
    int $heroGfMin = -1,
    int $heroGfMax = -1,
    int $heroGaMin = -1,
    int $heroGaMax = -1,
    bool $anchorBlue = false,
    ?string $editorialTone = null,
): string {
    if ($display === '-' || $display === '—') {
        return k2_h($display);
    }

    $numericCount = (int) $display;
    $linkClass = 'k2-table-cell-link';
    if ($anchorBlue) {
        $linkClass = 'k2-table-cell-link blue';
    } elseif ($editorialTone === 'blue' && $numericCount > 0) {
        $linkClass = 'k2-table-cell-link blue';
    } elseif ($editorialTone === 'red' && $numericCount > 0) {
        $linkClass = 'k2-table-cell-link red';
    }

    if ($playerId > 0 && k2_derived_games_started($games)) {
        $href = amiga_lb_player_games_inventory_href(
            $playerId,
            $resultFilter,
            $sortKey,
            $sortDir,
            $heroGfMin,
            $heroGfMax,
            $heroGaMin,
            $heroGaMax
        );
        if ($href !== '') {
            return '<a class="' . $linkClass . '" href="' . k2_h($href) . '">'
                . k2_h($display) . '</a>';
        }
    }

    if ($anchorBlue) {
        return '<span class="blue">' . k2_h($display) . '</span>';
    }
    if ($editorialTone === 'blue' && $numericCount > 0) {
        return '<span class="blue">' . k2_h($display) . '</span>';
    }
    if ($editorialTone === 'red' && $numericCount > 0) {
        return '<span class="red">' . k2_h($display) . '</span>';
    }

    return k2_h($display);
}

/**
 * Rating LB Games / W / D / L → player games tab (C1 calm cell link).
 * Same destinations as profile Results mosaic; links when rated games started.
 *
 * @param 'all'|'win'|'draw'|'loss' $resultFilter
 * @param 'win'|'loss'|null $wdlTone Editorial ink on link when count > 0
 */
function amiga_lb_rating_games_inventory_cell_html(
    int $playerId,
    int $games,
    string $resultFilter = 'all',
    ?string $wdlTone = null,
    mixed $wdlCount = null,
): string {
    if ($resultFilter === 'all') {
        $plain = k2_fmt_games_played($games);
        $restHtml = k2_h($plain);
        $linkClass = 'k2-table-cell-link';
    } elseif ($wdlTone === 'win' || $wdlTone === 'loss') {
        $plain = k2_fmt_count($wdlCount, $games);
        $restHtml = k2_fmt_wdl_count($wdlCount, $games, $wdlTone);
        $numericCount = ($plain === '-' || $plain === '—') ? 0 : (int) $plain;
        $linkClass = 'k2-table-cell-link';
        if ($wdlTone === 'win' && $numericCount > 0) {
            $linkClass = 'k2-table-cell-link blue';
        } elseif ($wdlTone === 'loss' && $numericCount > 0) {
            $linkClass = 'k2-table-cell-link red';
        }
    } else {
        $plain = k2_fmt_count($wdlCount, $games);
        $restHtml = k2_h($plain);
        $linkClass = 'k2-table-cell-link';
    }

    if ($plain === '-' || $plain === '—') {
        return k2_h($plain);
    }

    if ($playerId > 0 && k2_derived_games_started($games)) {
        $href = amiga_lb_player_games_inventory_href($playerId, $resultFilter);
        if ($href !== '') {
            return '<a class="' . $linkClass . '" href="' . k2_h($href) . '">'
                . k2_h($plain) . '</a>';
        }
    }

    return $restHtml;
}

function amiga_lb_player_tournaments_inventory_href(
    int $playerId,
    string $eventFilter = 'all',
    string $perfectFilter = '',
    string $winnerFilter = '',
    string $podiumFilter = '',
    int $finishFilter = 0,
): string {
    require_once __DIR__ . '/amiga_player_tournament_lib.php';

    if ($playerId < 1) {
        return '';
    }

    return amiga_player_tournaments_filter_url(
        $playerId,
        $eventFilter,
        '',
        0,
        $perfectFilter,
        $winnerFilter,
        $podiumFilter,
        $finishFilter,
    ) . amiga_player_tournaments_table_anchor_fragment();
}

/**
 * Hub LB cell → player Tournaments tab inventory (C1 calm cell link).
 * Same destinations as profile Tournament honours mosaic.
 *
 * @param 'all'|'world-cup' $eventFilter
 */
function amiga_lb_tournaments_inventory_cell_html(
    int $playerId,
    int $count,
    string $display,
    string $eventFilter = 'all',
    string $perfectFilter = '',
    string $winnerFilter = '',
    string $podiumFilter = '',
    int $finishFilter = 0,
): string {
    if ($playerId > 0 && $count > 0) {
        $href = amiga_lb_player_tournaments_inventory_href(
            $playerId,
            $eventFilter,
            $perfectFilter,
            $winnerFilter,
            $podiumFilter,
            $finishFilter,
        );
        if ($href !== '') {
            return '<a class="k2-table-cell-link" href="' . k2_h($href) . '">'
                . k2_h($display) . '</a>';
        }
    }

    return k2_h($display);
}

/**
 * Hub LB medal count → player Tournaments tab inventory (C1; gradient medal ink preserved).
 *
 * @param 'all'|'world-cup' $eventFilter
 */
function amiga_lb_tournaments_medal_inventory_cell_html(
    int $playerId,
    int $count,
    int $place,
    string $winnerFilter = '',
    string $podiumFilter = '',
    int $finishFilter = 0,
    string $eventFilter = 'all',
): string {
    require_once __DIR__ . '/amiga_wc_podium_th.php';

    $displayHtml = amiga_wc_podium_medal_value_markup($count, $place);
    if ($playerId > 0 && $count > 0) {
        $href = amiga_lb_player_tournaments_inventory_href(
            $playerId,
            $eventFilter,
            '',
            $winnerFilter,
            $podiumFilter,
            $finishFilter,
        );
        if ($href !== '') {
            return '<a class="k2-table-cell-link" href="' . k2_h($href) . '">'
                . $displayHtml . '</a>';
        }
    }

    return $displayHtml;
}

/** Default ORDER BY tail for Amiga peak-rating LB (no leading ORDER BY). */
function amiga_lb_peak_rating_default_order_sql(): string
{
    return 's.PeakRating DESC, s.Rating DESC';
}

/**
 * Sortable column index → SQL expression for Amiga peak-rating LB SSR order.
 *
 * @return array<int, string>
 */
function amiga_lb_peak_rating_order_column_map(bool $timeTravelActive = false): array
{
    return [
        1 => 'p.name',
        2 => 's.Rating',
        3 => 's.NumberGames',
        4 => 's.PeakRating',
        5 => 'tpr.event_date',
        6 => $timeTravelActive ? 'er.peak_elo_rank' : 's.peak_elo_rank',
        7 => 'tpke.event_date',
        8 => 's.LowestRating',
        9 => 'tlow.event_date',
        10 => 's.HighestRatedVictim',
        11 => 's.LowestRatedCulprit',
    ];
}

/**
 * Sortable column index → SQL expression for Amiga tournament honours LB SSR order.
 *
 * @return array<int, string>
 */
function amiga_lb_tournament_honours_order_column_map(string $alias = 't'): array
{
    $a = $alias;

    return [
        1 => 'p.name',
        2 => "{$a}.Rating",
        3 => "{$a}.tournaments_played",
        4 => "{$a}.event_gold",
        5 => "{$a}.event_silver",
        6 => "{$a}.event_bronze",
        7 => "{$a}.event_podiums",
        8 => "{$a}.perfect_events",
    ];
}

/** Default ORDER BY tail for Amiga calendar-geo LB (no leading ORDER BY). */
function amiga_lb_calendar_geo_default_order_sql(string $alias = 't'): string
{
    $a = $alias;

    return "{$a}.peak_year_games DESC, {$a}.peak_year_games_year ASC, {$a}.player_id ASC";
}

/**
 * Sortable column index → SQL expression for Amiga calendar-geo LB SSR order.
 *
 * @return array<int, string>
 */
function amiga_lb_calendar_geo_order_column_map(string $alias = 't'): array
{
    $a = $alias;

    return [
        1 => 'p.name',
        2 => "{$a}.Rating",
        3 => "{$a}.peak_year_games",
        4 => "{$a}.peak_year_games_year",
        5 => "{$a}.peak_year_tournaments",
        6 => "{$a}.peak_year_tournaments_year",
        7 => "{$a}.countries_played_in",
        8 => "{$a}.opponent_countries_faced",
        9 => "{$a}.opponent_countries_beaten",
        10 => "{$a}.opponent_countries_beaten_by",
    ];
}

/** Default ORDER BY tail for Amiga perf-rating Best LB (no leading ORDER BY). */
function amiga_lb_performance_rating_best_default_order_sql(): string
{
    return 'part.performance_rating DESC, part.games DESC, s.Rating DESC, p.id ASC';
}

/** Default ORDER BY tail for Amiga perf-rating Top 100 LB (no leading ORDER BY). */
function amiga_lb_performance_rating_top_default_order_sql(): string
{
    return 'part.performance_rating DESC, part.games DESC, part.tournament_id DESC, part.player_id ASC';
}

/** Default ORDER BY tail for Amiga perf-rating Perfect LB (no leading ORDER BY). */
function amiga_lb_performance_rating_perfect_default_order_sql(): string
{
    return 'part.event_date DESC, part.event_chrono DESC, part.tournament_id DESC, part.player_id ASC';
}

/**
 * Sortable column index → SQL expression for Amiga perf-rating Best/Top event rows SSR order.
 *
 * @return array<int, string>
 */
function amiga_lb_performance_rating_event_order_column_map(string $playerAlias = 'p'): array
{
    $pl = $playerAlias;

    return [
        1 => "{$pl}.name",
        2 => 's.Rating',
        3 => 'part.performance_rating',
        4 => 'part.games',
        5 => 'part.wins',
        6 => 'part.draws',
        7 => 'part.losses',
        8 => 'part.tournament_name',
        9 => 'part.event_date',
    ];
}

/**
 * Sortable column index → SQL expression for Amiga perf-rating Best LB SSR order.
 *
 * @return array<int, string>
 */
function amiga_lb_performance_rating_best_order_column_map(): array
{
    return amiga_lb_performance_rating_event_order_column_map('p');
}

/**
 * Sortable column index → SQL expression for Amiga perf-rating Perfect LB SSR order.
 *
 * @return array<int, string>
 */
function amiga_lb_performance_rating_perfect_order_column_map(string $playerAlias = 'p'): array
{
    return amiga_lb_performance_rating_event_order_column_map($playerAlias);
}

/**
 * Rating LB Δ cell values (present-day WC-start Δ or time-travel event Δ). Column is always visible.
 *
 * @return array{
 *     show_rating_delta: bool,
 *     delta_by_player: array<int, float>,
 *     last_wc_for_delta_help: ?array
 * }
 */
function amiga_lb_rating_delta_column_bundle(mysqli $con, AmigaSnapshotContext $ctx): array
{
    require_once __DIR__ . '/amiga_lb_snapshot_lib.php';

    $showRatingDelta = $ctx->isActive();
    $lastWcForDeltaHelp = null;

    if ($showRatingDelta) {
        $deltaByPlayer = amiga_lb_rating_delta_map($con, $ctx);
    } else {
        $deltaByPlayer = amiga_lb_wc_start_rating_delta_map($con);
        require_once __DIR__ . '/amiga_rating_history_lib.php';
        $lastWcForDeltaHelp = amiga_rating_history_last_world_cup_tournament($con);
    }

    return [
        'show_rating_delta' => $showRatingDelta,
        'delta_by_player' => $deltaByPlayer,
        'last_wc_for_delta_help' => $lastWcForDeltaHelp,
    ];
}

/**
 * Amiga rating LB URL sorted by Games, scrolled to a player's row (profile hero games link).
 *
 * @param array<string, scalar> $extraQuery
 */
function amiga_lb_rating_games_player_href(int $playerId, ?mysqli $con = null, array $extraQuery = []): string
{
    $query = array_merge(
        ['k2_sort' => (string) AMIGA_LB_RATING_COL_GAMES, 'k2_dir' => 'desc'],
        $extraQuery
    );

    return amiga_lb_rating_player_href($playerId, $query);
}

/**
 * Amiga rating LB URL sorted by Win rate, scrolled to a player's row (profile mosaic comparison link).
 *
 * @param array<string, scalar> $extraQuery
 */
function amiga_lb_rating_win_rate_player_href(int $playerId, ?mysqli $con = null, array $extraQuery = []): string
{
    $query = array_merge(
        ['k2_sort' => (string) AMIGA_LB_RATING_COL_WIN_RATE, 'k2_dir' => 'desc'],
        $extraQuery
    );

    return amiga_lb_rating_player_href($playerId, $query);
}

/**
 * Amiga rating LB URL sorted by Opponent Average, scrolled to a player's row (profile mosaic comparison link).
 *
 * @param array<string, scalar> $extraQuery
 */
function amiga_lb_rating_opponent_avg_player_href(int $playerId, ?mysqli $con = null, array $extraQuery = []): string
{
    $query = array_merge(
        ['k2_sort' => (string) AMIGA_LB_RATING_COL_OPP_AVG, 'k2_dir' => 'desc'],
        $extraQuery
    );

    return amiga_lb_rating_player_href($playerId, $query);
}

/**
 * Country roster / table Elo cell → rating LB row anchor (same URL contract as profile hero rating link).
 */
function k2_amiga_lb_rating_cell_link(int $playerId, mixed $rating, string $playerName = ''): string
{
    $display = k2_fmt_int($rating, '—');
    if ($playerId < 1 || $display === '—') {
        return k2_h($display);
    }

    $href = amiga_lb_rating_player_href($playerId);
    $name = trim($playerName);
    $ariaLabel = $name !== ''
        ? 'View ' . $name . ' on rating leaderboard'
        : 'View on rating leaderboard';

    return '<a class="k2-link-star" href="' . k2_h($href) . '" aria-label="' . k2_h($ariaLabel) . '"'
        . ' data-k2-amiga-player-glance-rating="' . $playerId . '">'
        . k2_h($display) . '</a>';
}

/**
 * ORDER BY for tournament honours LB (must match default sort col + skip-initial-sort).
 *
 * @see site/public_html/amiga/leaderboards/tournament-honours.php
 */
function amiga_lb_tournament_honours_order_sql(string $alias = 't'): string
{
    $a = $alias;

    return "{$a}.event_gold DESC, {$a}.event_silver DESC, {$a}.event_bronze DESC, {$a}.tournaments_played DESC, {$a}.player_id ASC";
}

function amiga_lb_chapter_lede_html(int $gameCount, int $tournamentCount): string
{
    $gamesHtml = '<span class="blue">' . number_format($gameCount) . '</span>';
    $tournamentsHtml = '<span class="blue">' . number_format($tournamentCount) . '</span>';

    return 'Leaderboards from ' . $gamesHtml
        . ' Amiga games played in ' . $tournamentsHtml
        . ' official KOA tournaments. Elo ratings and tournament honours, goals, double digits and clean sheets, victims and culprits, peak and performance ratings — sort any column to see who leads a different way.';
}

function amiga_lb_chapter_lede_html_for_request(?mysqli $con = null, ?AmigaSnapshotContext $ctx = null): string
{
    require_once __DIR__ . '/amiga_snapshot_context.php';

    $ctx ??= amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
    $cutoffForKey = $ctx->isActive() ? $ctx->cutoff() : null;
    $cacheKey = $cutoffForKey === null
        ? 'present'
        : $cutoffForKey['event_date'] . '|' . $cutoffForKey['chrono'] . '|' . $cutoffForKey['tournament_id'];

    static $cache = [];
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    require_once __DIR__ . '/k2_safety.php';
    require_once __DIR__ . '/amiga_lb_snapshot_lib.php';
    require_once __DIR__ . '/amiga_tournament_lib.php';

    $ownedConnection = false;
    if ($con === null) {
        $configPath = __DIR__ . '/../../config/ko2amiga_config.php';
        if (!is_file($configPath)) {
            return $cache[$cacheKey] = amiga_lb_chapter_lede_html(0, 0);
        }
        include $configPath;
        $con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
        $ownedConnection = true;
        $peeked = amiga_snapshot_context_peek();
        if ($peeked !== null) {
            $ctx = $peeked;
        } else {
            $ctx = amiga_lb_context($con);
        }
    }

    $gameCount = amiga_lb_games_count($con, $ctx);
    $tournamentCount = amiga_tournament_index_count($con, $ctx);
    if ($ownedConnection) {
        mysqli_close($con);
    }

    return $cache[$cacheKey] = amiga_lb_chapter_lede_html($gameCount, $tournamentCount);
}
