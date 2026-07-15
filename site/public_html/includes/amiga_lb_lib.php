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
        6 => 's.AverageGoalsFor',
        7 => 's.AverageGoalsAgainst',
        8 => '(s.GoalsFor - s.GoalsAgainst) / NULLIF(s.NumberGames, 0)',
        9 => '(CASE WHEN s.GoalRatio IS NULL OR s.GoalRatio < 0 THEN NULL ELSE s.GoalRatio END)',
        10 => 's.MostGoalsScored',
        11 => 's.MostGoalsConceded',
        12 => 's.BiggestWinDifference',
        13 => 's.BiggestLossDifference',
        14 => 's.BiggestSumOfGoals',
        15 => 's.BiggestDrawSum',
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
function amiga_lb_victims_order_column_map(): array
{
    return [
        1 => 'p.name',
        2 => 's.Rating',
        3 => 's.NumberGames',
        4 => 's.DifferentOpponents',
        5 => 's.DifferentVictims',
        6 => 's.DifferentCulprits',
        7 => 's.DoubleDigitsVictims',
        8 => 's.DoubleDigitsCulprits',
        9 => 's.MostGoalsConcededVictims',
        10 => 's.BiggestLossVictims',
        11 => 's.CleanSheetsVictims',
        12 => 's.CleanSheetsCulprits',
        13 => 's.MostGoalsScoredCulprits',
        14 => 's.BiggestWinCulprits',
    ];
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
        9 => 's.AverageOpponentRating',
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

/**
 * Sortable column index → SQL expression for Amiga perf-rating Best LB SSR order.
 *
 * @return array<int, string>
 */
function amiga_lb_performance_rating_best_order_column_map(): array
{
    return [
        1 => 'p.name',
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
 * Rating LB delta column state (present-day WC-start Δ or time-travel event Δ).
 *
 * @return array{
 *     show: bool,
 *     show_rating_delta: bool,
 *     show_wc_start_delta: bool,
 *     delta_by_player: array<int, float>,
 *     last_wc_for_delta_help: ?array
 * }
 */
function amiga_lb_rating_delta_column_bundle(mysqli $con, AmigaSnapshotContext $ctx): array
{
    require_once __DIR__ . '/amiga_lb_snapshot_lib.php';

    $showRatingDelta = $ctx->isActive();
    $deltaByPlayer = [];
    $showWcStartDelta = false;
    $lastWcForDeltaHelp = null;

    if ($showRatingDelta) {
        $deltaByPlayer = amiga_lb_rating_delta_map($con, $ctx);
    } else {
        $deltaByPlayer = amiga_lb_wc_start_rating_delta_map($con);
        if ($deltaByPlayer !== []) {
            $showWcStartDelta = true;
            require_once __DIR__ . '/amiga_rating_history_lib.php';
            $lastWcForDeltaHelp = amiga_rating_history_last_world_cup_tournament($con);
        }
    }

    return [
        'show' => $showRatingDelta || $showWcStartDelta,
        'show_rating_delta' => $showRatingDelta,
        'show_wc_start_delta' => $showWcStartDelta,
        'delta_by_player' => $deltaByPlayer,
        'last_wc_for_delta_help' => $lastWcForDeltaHelp,
    ];
}

/** Games column index on the Amiga rating LB (shifts when the Δ column is visible). */
function amiga_lb_rating_games_sort_col(bool $showDeltaColumn): int
{
    return 3 + ($showDeltaColumn ? 1 : 0);
}

/** Win rate column index on the Amiga rating LB (shifts when the Δ column is visible). */
function amiga_lb_rating_win_rate_sort_col(bool $showDeltaColumn): int
{
    return 7 + ($showDeltaColumn ? 1 : 0);
}

/** Opponent Average column index on the Amiga rating LB (shifts when the Δ column is visible). */
function amiga_lb_rating_opponent_avg_sort_col(bool $showDeltaColumn): int
{
    return 8 + ($showDeltaColumn ? 1 : 0);
}

/**
 * Amiga rating LB URL sorted by Games, scrolled to a player's row (profile hero games link).
 *
 * @param array<string, scalar> $extraQuery
 */
function amiga_lb_rating_games_player_href(int $playerId, ?mysqli $con = null, array $extraQuery = []): string
{
    $sortCol = 3;
    if ($con !== null) {
        $ctx = amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
        $delta = amiga_lb_rating_delta_column_bundle($con, $ctx);
        $sortCol = amiga_lb_rating_games_sort_col($delta['show']);
    } else {
        $ctx = amiga_snapshot_context_peek();
        if ($ctx !== null && $ctx->isActive()) {
            $sortCol = 4;
        }
    }

    $query = array_merge(['k2_sort' => (string) $sortCol, 'k2_dir' => 'desc'], $extraQuery);

    return amiga_lb_rating_player_href($playerId, $query);
}

/**
 * Amiga rating LB URL sorted by Win rate, scrolled to a player's row (profile mosaic comparison link).
 *
 * @param array<string, scalar> $extraQuery
 */
function amiga_lb_rating_win_rate_player_href(int $playerId, ?mysqli $con = null, array $extraQuery = []): string
{
    $sortCol = 7;
    if ($con !== null) {
        $ctx = amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
        $delta = amiga_lb_rating_delta_column_bundle($con, $ctx);
        $sortCol = amiga_lb_rating_win_rate_sort_col($delta['show']);
    } else {
        $ctx = amiga_snapshot_context_peek();
        if ($ctx !== null && $ctx->isActive()) {
            $sortCol = 8;
        }
    }

    $query = array_merge(['k2_sort' => (string) $sortCol, 'k2_dir' => 'desc'], $extraQuery);

    return amiga_lb_rating_player_href($playerId, $query);
}

/**
 * Amiga rating LB URL sorted by Opponent Average, scrolled to a player's row (profile mosaic comparison link).
 *
 * @param array<string, scalar> $extraQuery
 */
function amiga_lb_rating_opponent_avg_player_href(int $playerId, ?mysqli $con = null, array $extraQuery = []): string
{
    $sortCol = 8;
    if ($con !== null) {
        $ctx = amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
        $delta = amiga_lb_rating_delta_column_bundle($con, $ctx);
        $sortCol = amiga_lb_rating_opponent_avg_sort_col($delta['show']);
    } else {
        $ctx = amiga_snapshot_context_peek();
        if ($ctx !== null && $ctx->isActive()) {
            $sortCol = 9;
        }
    }

    $query = array_merge(['k2_sort' => (string) $sortCol, 'k2_dir' => 'desc'], $extraQuery);

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
