<?php
/**
 * Leaderboard wing — career league honours.
 * Overall: playertable + player_league_totals.
 * Slices: aggregate player_league_award by league_kind + period_type (read-time; stored breakdown later).
 */
declare(strict_types=1);

require_once __DIR__ . '/lb_player_filters.php';
require_once __DIR__ . '/status_queries.php';

/** @var list<string> */
const K2_LB_LEAGUE_HONOURS_CUPS = ['overall', 'activity', 'points'];

/** @var list<string> */
const K2_LB_LEAGUE_HONOURS_GRAINS = ['day', 'week', 'month', 'year'];

/**
 * @param array<string, mixed>|null $get
 * @return array{cup: string, grain: string|null}
 */
function k2_lb_league_honours_parse_view(?array $get = null): array
{
    $get = $get ?? $_GET;
    $cup = isset($get['cup']) ? strtolower(trim((string) $get['cup'])) : 'overall';
    if (!in_array($cup, K2_LB_LEAGUE_HONOURS_CUPS, true)) {
        $cup = 'overall';
    }

    $grain = isset($get['grain']) ? strtolower(trim((string) $get['grain'])) : 'day';
    if (!in_array($grain, K2_LB_LEAGUE_HONOURS_GRAINS, true)) {
        $grain = 'day';
    }

    if ($cup === 'overall') {
        return ['cup' => 'overall', 'grain' => null];
    }

    return ['cup' => $cup, 'grain' => $grain];
}

/**
 * @param array{include_inactive?: bool, include_provisional?: bool}|null $filterOpts
 */
function k2_lb_league_honours_href(string $cup, ?string $grain = null, ?array $filterOpts = null): string
{
    $params = k2_lb_filter_query_params($filterOpts);
    if ($cup === 'overall') {
        $params['cup'] = 'overall';
    } else {
        $params['cup'] = $cup;
        $params['grain'] = $grain ?? 'day';
    }

    return k2_route('lb-league-honours', $params);
}

/**
 * Merge league-honours view params into leaderboard filter toggle URLs on ranked9.
 *
 * @param array<string, string> $params
 * @return array<string, string>
 */
function k2_lb_league_honours_merge_filter_params(array $params): array
{
    if (!k2_route_is_current('lb-league-honours')) {
        return $params;
    }

    $view = k2_lb_league_honours_parse_view();

    if ($view['cup'] === 'overall') {
        $params['cup'] = 'overall';
    } else {
        $params['cup'] = $view['cup'];
        $params['grain'] = $view['grain'] ?? 'day';
    }

    return $params;
}

function k2_lb_league_honours_gold_help(array $view): string
{
    if ($view['cup'] === 'overall') {
        return 'First-place league wins you earned (daily, weekly, monthly, yearly · points or activity).';
    }

    $grainWord = match ($view['grain']) {
        'week' => 'weekly',
        'month' => 'monthly',
        'year' => 'yearly',
        default => 'daily',
    };
    $kindWord = $view['cup'] === 'activity' ? 'activity' : 'points';

    return 'First-place finishes you earned in a ' . $grainWord . ' ' . $kindWord . ' league.';
}

/**
 * @return array<int, array{
 *   id: int,
 *   name: string,
 *   rating: float,
 *   games: int,
 *   gold: int,
 *   silver: int,
 *   bronze: int,
 *   podiums: int
 * }>
 */
function k2_lb_league_honours_rows(mysqli $con, array $view, ?string &$error = null): array
{
    if (($view['cup'] ?? 'overall') === 'overall') {
        return k2_lb_league_honours_rows_overall($con, $error);
    }

    return k2_lb_league_honours_rows_slice(
        $con,
        (string) $view['cup'],
        (string) ($view['grain'] ?? 'day'),
        $error
    );
}

/**
 * @return array<int, array{id: int, name: string, rating: float, games: int, gold: int, silver: int, bronze: int, podiums: int}>
 */
function k2_lb_league_honours_rows_overall(mysqli $con, ?string &$error = null): array
{
    $error = null;
    $where = k2_lb_player_where_sql_for_alias('p');
    $joinTotals = k2_status_table_exists($con, 'player_league_totals')
        ? 'LEFT JOIN player_league_totals t ON t.player_id = p.ID'
        : '';
    $goldExpr = k2_status_table_exists($con, 'player_league_totals')
        ? 'COALESCE(t.gold, 0)'
        : '0';
    $silverExpr = k2_status_table_exists($con, 'player_league_totals') ? 'COALESCE(t.silver, 0)' : '0';
    $bronzeExpr = k2_status_table_exists($con, 'player_league_totals') ? 'COALESCE(t.bronze, 0)' : '0';
    $podiumExpr = k2_status_table_exists($con, 'player_league_totals') ? 'COALESCE(t.podiums, 0)' : '0';

    $sql = 'SELECT p.ID AS id, p.Name AS name, p.Rating AS rating, p.NumberGames AS games, '
        . $goldExpr . ' AS gold, '
        . $silverExpr . ' AS silver, '
        . $bronzeExpr . ' AS bronze, '
        . $podiumExpr . ' AS podiums '
        . 'FROM playertable p '
        . $joinTotals . ' '
        . 'WHERE ' . $where . ' '
        . 'ORDER BY gold DESC, podiums DESC, p.Name ASC';

    return k2_lb_league_honours_fetch_players($con, $sql, $error);
}

/**
 * @return array<int, array{id: int, name: string, rating: float, games: int, gold: int, silver: int, bronze: int, podiums: int}>
 */
function k2_lb_league_honours_rows_slice(
    mysqli $con,
    string $leagueKind,
    string $periodType,
    ?string &$error = null
): array {
    $error = null;
    if (!in_array($leagueKind, ['activity', 'points'], true)) {
        $error = 'invalid_cup';

        return [];
    }
    if (!in_array($periodType, K2_LB_LEAGUE_HONOURS_GRAINS, true)) {
        $error = 'invalid_grain';

        return [];
    }

    if (!k2_status_table_exists($con, 'player_league_slice_totals')) {
        return k2_lb_league_honours_rows_overall_zeros($con, $error);
    }

    $where = k2_lb_player_where_sql_for_alias('p');
    $sql = 'SELECT p.ID AS id, p.Name AS name, p.Rating AS rating, p.NumberGames AS games, '
        . 'COALESCE(s.gold, 0) AS gold, COALESCE(s.silver, 0) AS silver, '
        . 'COALESCE(s.bronze, 0) AS bronze, COALESCE(s.podiums, 0) AS podiums '
        . 'FROM playertable p '
        . 'LEFT JOIN player_league_slice_totals s '
        . 'ON s.player_id = p.ID AND s.league_kind = ? AND s.period_type = ? '
        . 'WHERE ' . $where . ' '
        . 'ORDER BY gold DESC, podiums DESC, p.Name ASC';

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $error = mysqli_error($con);

        return [];
    }
    mysqli_stmt_bind_param($stmt, 'ss', $leagueKind, $periodType);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return [];
    }
    $res = mysqli_stmt_get_result($stmt);
    $rows = k2_lb_league_honours_rows_from_result($res);
    mysqli_free_result($res);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * @return array<int, array{id: int, name: string, rating: float, games: int, gold: int, silver: int, bronze: int, podiums: int}>
 */
function k2_lb_league_honours_rows_overall_zeros(mysqli $con, ?string &$error = null): array
{
    $error = null;
    $where = k2_lb_player_where_sql_for_alias('p');
    $sql = 'SELECT p.ID AS id, p.Name AS name, p.Rating AS rating, p.NumberGames AS games, '
        . '0 AS gold, 0 AS silver, 0 AS bronze, 0 AS podiums '
        . 'FROM playertable p WHERE ' . $where . ' ORDER BY gold DESC, podiums DESC, p.Name ASC';

    return k2_lb_league_honours_fetch_players($con, $sql, $error);
}

/**
 * @return array<int, array{id: int, name: string, rating: float, games: int, gold: int, silver: int, bronze: int, podiums: int}>
 */
function k2_lb_league_honours_fetch_players(mysqli $con, string $sql, ?string &$error = null): array
{
    $error = null;
    $res = mysqli_query($con, $sql);
    if ($res === false) {
        $error = mysqli_error($con);

        return [];
    }

    $rows = k2_lb_league_honours_rows_from_result($res);
    mysqli_free_result($res);

    return $rows;
}

/**
 * @param mysqli_result|false $res
 * @return array<int, array{id: int, name: string, rating: float, games: int, gold: int, silver: int, bronze: int, podiums: int}>
 */
function k2_lb_league_honours_rows_from_result($res): array
{
    $rows = [];
    if ($res === false) {
        return $rows;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'rating' => (float) $row['rating'],
            'games' => (int) $row['games'],
            'gold' => (int) $row['gold'],
            'silver' => (int) $row['silver'],
            'bronze' => (int) $row['bronze'],
            'podiums' => (int) $row['podiums'],
        ];
    }

    return $rows;
}
