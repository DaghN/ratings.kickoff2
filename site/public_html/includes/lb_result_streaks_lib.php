<?php
/**
 * Result streaks leaderboard — read player_result_streaks for tooltips + games drill-down.
 */
declare(strict_types=1);

require_once __DIR__ . '/lb_player_filters.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/lb_activity_lib.php';
require_once __DIR__ . '/k2_routes.php';
require_once __DIR__ . '/player_games_from.php';
require_once __DIR__ . '/player_result_streaks.php';

/** @var list<array{type: string, field: string}> */
const K2_LB_RESULT_STREAK_COLUMNS = [
    ['type' => 'win', 'field' => 'LongestWinningStreak'],
    ['type' => 'non_loss', 'field' => 'LongestNonLossStreak'],
    ['type' => 'draw', 'field' => 'LongestDrawingStreak'],
    ['type' => 'non_draw', 'field' => 'LongestNonDrawStreak'],
    ['type' => 'loss', 'field' => 'LongestLosingStreak'],
    ['type' => 'non_win', 'field' => 'LongestNonWinStreak'],
];

function k2_lb_result_streaks_ready(mysqli $con): bool
{
    return k2_result_streak_table_ready($con);
}

function k2_lb_result_streaks_sql_alias(string $streakType): string
{
    return 'rs_' . str_replace('_', '', $streakType);
}

/** Default ORDER BY tail for result streaks LB (no leading ORDER BY). */
function k2_lb_result_streaks_default_order_sql(): string
{
    return 'p.`LongestWinningStreak` DESC, p.`Rating` DESC';
}

/**
 * Sortable column index → SQL expression for result streaks LB SSR order.
 *
 * @return array<int, string>
 */
function k2_lb_result_streaks_order_column_map(): array
{
    return [
        1 => 'p.`Name`',
        2 => 'p.`Rating`',
        3 => 'p.`NumberGames`',
        4 => 'p.`LongestWinningStreak`',
        5 => 'p.`LongestNonLossStreak`',
        6 => 'p.`LongestDrawingStreak`',
        7 => 'p.`LongestNonDrawStreak`',
        8 => 'p.`LongestLosingStreak`',
        9 => 'p.`LongestNonWinStreak`',
    ];
}

/**
 * @return mysqli_result|false
 */
function k2_lb_result_streaks_query(mysqli $con, ?string $orderClause = null)
{
    $orderClause ??= k2_lb_result_streaks_default_order_sql();
    $where = k2_lb_player_where_sql_for_alias('p');
    $select = 'p.`id`, p.`Name`, p.`Rating`, p.`NumberGames`, '
        . 'p.`LongestWinningStreak`, p.`LongestNonLossStreak`, p.`LongestDrawingStreak`, '
        . 'p.`LongestNonDrawStreak`, p.`LongestLosingStreak`, p.`LongestNonWinStreak`';

    if (!k2_lb_result_streaks_ready($con)) {
        $sql = 'SELECT ' . $select . ' FROM `playertable` p WHERE ' . $where . ' '
            . 'ORDER BY ' . $orderClause;

        return $con->query($sql);
    }

    $joins = '';
    foreach (K2_LB_RESULT_STREAK_COLUMNS as $col) {
        $alias = k2_lb_result_streaks_sql_alias($col['type']);
        $joins .= ' LEFT JOIN `player_result_streaks` ' . $alias
            . ' ON ' . $alias . '.`player_id` = p.`id` AND ' . $alias . '.`streak_type` = \''
            . $col['type'] . '\'';
        $select .= ', ' . $alias . '.`best_start_at` AS `' . $alias . '_start_at`'
            . ', ' . $alias . '.`best_end_at` AS `' . $alias . '_end_at`'
            . ', ' . $alias . '.`best_start_game_id` AS `' . $alias . '_from_game`'
            . ', ' . $alias . '.`best_end_game_id` AS `' . $alias . '_to_game`';
    }

    $sql = 'SELECT ' . $select . ' FROM `playertable` p' . $joins . ' WHERE ' . $where . ' '
        . 'ORDER BY ' . $orderClause;

    return $con->query($sql);
}

function k2_lb_result_streaks_run_noun(string $streakType): string
{
    return match ($streakType) {
        'win' => 'consecutive wins',
        'draw' => 'consecutive draws',
        'loss' => 'consecutive losses',
        'non_win' => 'consecutive games without a win',
        'non_draw' => 'consecutive games without a draw',
        'non_loss' => 'consecutive games without a loss',
        default => 'consecutive games',
    };
}

function k2_lb_result_streaks_run_label(string $streakType): string
{
    return match ($streakType) {
        'win' => 'win streak',
        'draw' => 'draw streak',
        'loss' => 'loss streak',
        'non_win' => 'win drought',
        'non_draw' => 'decided run',
        'non_loss' => 'unbeaten run',
        default => 'streak',
    };
}

function k2_lb_result_streaks_format_span_html(?string $startAt, ?string $endAt): string
{
    $plain = k2_result_streak_format_span($startAt, $endAt);
    if ($plain === '') {
        return '';
    }
    if (!str_contains($plain, ' – ')) {
        return k2_lb_activity_link_star_markup($plain);
    }

    [$start, $end] = explode(' – ', $plain, 2);

    return k2_lb_activity_link_star_markup($start) . ' – ' . k2_lb_activity_link_star_markup($end);
}

function k2_lb_result_streaks_tooltip_html(int $count, string $streakType, ?string $startAt, ?string $endAt): string
{
    if ($count <= 0 || $startAt === null || $startAt === '') {
        return '';
    }

    $sentence = k2_lb_activity_link_star_markup((string) $count)
        . ' ' . k2_lb_result_streaks_run_noun($streakType) . '.';
    $dates = k2_lb_result_streaks_format_span_html($startAt, $endAt);
    if ($dates === '') {
        return $sentence;
    }

    return $sentence . '<br><br>' . $dates;
}

function k2_lb_result_streaks_games_url(int $playerId, string $streakType, int $fromGameId, int $toGameId): string
{
    $params = k2_player_games_with_from_param(
        [
            'id' => $playerId,
            'from_game' => $fromGameId,
            'to_game' => $toGameId,
            'streak' => $streakType,
        ],
        'result-streaks'
    );

    return k2_player_games_url_with_list_anchor(k2_route('player-games', $params));
}

/**
 * @param array<string, mixed> $row
 * @return array{display: string, help: ?string, help_html: bool, href: ?string}
 */
function k2_lb_result_streaks_cell_meta(
    int $playerId,
    int $count,
    mixed $careerGames,
    string $streakType,
    array $row
): array {
    $display = k2_fmt_count($count, $careerGames);
    $meta = ['display' => $display, 'help' => null, 'help_html' => false, 'href' => null];

    if ($count <= 0) {
        return $meta;
    }

    $alias = k2_lb_result_streaks_sql_alias($streakType);
    $startAt = isset($row[$alias . '_start_at']) ? (string) $row[$alias . '_start_at'] : '';
    $endAt = isset($row[$alias . '_end_at']) ? (string) $row[$alias . '_end_at'] : '';
    $fromGame = isset($row[$alias . '_from_game']) ? (int) $row[$alias . '_from_game'] : 0;
    $toGame = isset($row[$alias . '_to_game']) ? (int) $row[$alias . '_to_game'] : 0;

    if ($startAt === '' || $fromGame < 1 || $toGame < 1) {
        return $meta;
    }

    $help = k2_lb_result_streaks_tooltip_html($count, $streakType, $startAt, $endAt);
    if ($help === '') {
        return $meta;
    }

    $meta['help'] = $help;
    $meta['help_html'] = true;
    $meta['href'] = k2_lb_result_streaks_games_url($playerId, $streakType, $fromGame, $toGame);

    return $meta;
}
