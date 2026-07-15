<?php
/**
 * Activity leaderboard wing — read stored truth for peaks, participation, play streaks.
 */
declare(strict_types=1);

require_once __DIR__ . '/lb_player_filters.php';
require_once __DIR__ . '/peak_month_leaderboard_query.php';
require_once __DIR__ . '/k2_routes.php';
require_once __DIR__ . '/player_games_from.php';

function k2_lb_activity_participation_ready(mysqli $con): bool
{
    $res = $con->query("SHOW TABLES LIKE 'player_activity_participation'");

    return $res !== false && $res->num_rows > 0;
}

function k2_lb_activity_format_peak_cell(int $games, string $period, ?string $periodKey): string
{
    if ($games <= 0 || $periodKey === null || $periodKey === '') {
        return k2_fmt_dash();
    }

    return (int) $games . ' · ' . k2_format_peak_period($period, $periodKey);
}

function k2_lb_activity_link_star_markup(string $text): string
{
    return '<span class="k2-link-star">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
}

function k2_lb_activity_peak_period_noun(string $period): string
{
    return match ($period) {
        'day' => 'one UTC calendar day',
        'week' => 'one UTC week (Monday–Sunday)',
        'month' => 'one UTC calendar month',
        'year' => 'one UTC calendar year',
        default => 'one period',
    };
}

function k2_lb_activity_peak_period_html(string $period, string $periodKey): string
{
    if ($period === 'day') {
        return k2_lb_activity_link_star_markup(k2_format_peak_period('day', $periodKey));
    }
    if ($period === 'week') {
        $endSun = (new DateTimeImmutable($periodKey, new DateTimeZone('UTC')))->modify('+6 days')->format('Y-m-d');

        return k2_lb_activity_link_star_markup(k2_format_peak_period('day', $periodKey))
            . ' – ' . k2_lb_activity_link_star_markup(k2_format_peak_period('day', $endSun));
    }
    if ($period === 'month') {
        return k2_lb_activity_link_star_markup(k2_format_peak_period('month', $periodKey));
    }
    if ($period === 'year') {
        return k2_lb_activity_link_star_markup(k2_format_peak_period('year', $periodKey));
    }

    return k2_lb_activity_link_star_markup($periodKey);
}

function k2_lb_activity_peak_tooltip_html(int $games, string $period, string $periodKey): string
{
    $sentence = k2_lb_activity_link_star_markup((string) $games)
        . ' rated games in ' . k2_lb_activity_peak_period_noun($period) . '.';
    $dates = k2_lb_activity_peak_period_html($period, $periodKey);

    return $sentence . '<br><br>' . $dates;
}

/**
 * @return array{display: string, help: ?string, help_html: bool, href: ?string}
 */
function k2_lb_activity_peak_cell_meta(int $playerId, int $games, mixed $careerGames, string $period, ?string $periodKey): array
{
    $display = k2_fmt_count($games, $careerGames);
    $meta = ['display' => $display, 'help' => null, 'help_html' => false, 'href' => null];

    if ($games <= 0 || $periodKey === null || $periodKey === '') {
        return $meta;
    }

    $meta['help'] = k2_lb_activity_peak_tooltip_html($games, $period, $periodKey);
    $meta['help_html'] = true;
    $meta['href'] = k2_lb_activity_peak_games_url($playerId, $period, $periodKey);

    return $meta;
}

function k2_lb_activity_peak_games_url(int $playerId, string $period, string $periodKey): string
{
    $params = k2_player_games_with_from_param(['id' => $playerId], 'activity-peaks');
    if ($period === 'day') {
        $params['day'] = $periodKey;

        return k2_player_games_url_with_list_anchor(k2_route('player-games', $params));
    }

    $params['period'] = $period;
    $params['anchor'] = $periodKey;

    return k2_player_games_url_with_list_anchor(k2_route('player-games', $params));
}

function k2_lb_activity_peak_period_filter_label(string $period, string $anchorYmd): string
{
    if ($period === 'week') {
        $endSun = (new DateTimeImmutable($anchorYmd, new DateTimeZone('UTC')))->modify('+6 days')->format('Y-m-d');

        return k2_format_peak_period('day', $anchorYmd) . ' – ' . k2_format_peak_period('day', $endSun);
    }

    return k2_format_peak_period($period, $anchorYmd);
}

function k2_lb_activity_echo_tooltip_td(array $meta, ?int $sortValue = null, string $class = '', ?int $sortTieValue = null, string $valueClass = ''): void
{
    $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
    $sortAttr = $sortValue !== null ? ' data-k2-sort-value="' . (int) $sortValue . '"' : '';
    $tieAttr = $sortTieValue !== null ? ' data-k2-sort-tie-value="' . (int) $sortTieValue . '"' : '';
    $display = htmlspecialchars($meta['display'], ENT_QUOTES, 'UTF-8');
    if ($valueClass !== '') {
        $display = '<span class="' . htmlspecialchars($valueClass, ENT_QUOTES, 'UTF-8') . '">' . $display . '</span>';
    }

    echo '<td' . $classAttr . $sortAttr . $tieAttr;
    if ($meta['help'] !== null) {
        echo ' tabindex="0" data-k2-tooltip-hide-title="1" data-k2-help="' . htmlspecialchars($meta['help'], ENT_QUOTES, 'UTF-8') . '"';
        if ($meta['help_html']) {
            echo ' data-k2-help-html="1"';
        }
    }
    echo '>';
    if (!empty($meta['href'])) {
        echo '<a class="k2-table-cell-link" href="' . htmlspecialchars((string) $meta['href'], ENT_QUOTES, 'UTF-8') . '">';
        echo $display;
        echo '</a>';
    } else {
        echo $display;
    }
    echo '</td>';
}

/** Unix time for k2-table tie-break (earlier = ranks higher when streak length ties — matches HoF). */
function k2_lb_activity_streak_achieved_tie_value(?string $achievedAt): ?int
{
    if ($achievedAt === null || $achievedAt === '') {
        return null;
    }

    $ts = strtotime($achievedAt);
    if ($ts === false) {
        return null;
    }

    return $ts;
}

/**
 * When a player first reached their current participation count (Nth distinct period).
 * Tie-break uses the establishing game datetime in that period (same rule as play-streak HoF),
 * not calendar period_start alone — otherwise year/month ties collapse to Jan 1 / 1st-of-month.
 */
function k2_lb_activity_participation_count_period_type(string $column): ?string
{
    return match ($column) {
        'active_days' => 'day',
        'active_weeks' => 'week',
        'active_months' => 'month',
        'active_years' => 'year',
        default => null,
    };
}

function k2_lb_activity_participation_active_column(string $periodType): ?string
{
    return match ($periodType) {
        'day' => 'active_days',
        'week' => 'active_weeks',
        'month' => 'active_months',
        'year' => 'active_years',
        default => null,
    };
}

function k2_lb_activity_participation_reached_at_column(string $periodType): ?string
{
    return match ($periodType) {
        'day' => 'active_days_reached_at',
        'week' => 'active_weeks_reached_at',
        'month' => 'active_months_reached_at',
        'year' => 'active_years_reached_at',
        default => null,
    };
}

function k2_lb_activity_participation_reached_game_id_column(string $periodType): ?string
{
    return match ($periodType) {
        'day' => 'active_days_reached_game_id',
        'week' => 'active_weeks_reached_game_id',
        'month' => 'active_months_reached_game_id',
        'year' => 'active_years_reached_game_id',
        default => null,
    };
}

function k2_lb_activity_participation_reached_at_column_for_active(string $activeColumn): ?string
{
    return match ($activeColumn) {
        'active_days' => 'active_days_reached_at',
        'active_weeks' => 'active_weeks_reached_at',
        'active_months' => 'active_months_reached_at',
        'active_years' => 'active_years_reached_at',
        default => null,
    };
}

function k2_lb_activity_participation_reached_columns_ready(mysqli $con): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    if (!k2_lb_activity_participation_ready($con)) {
        $ready = false;

        return false;
    }
    $res = $con->query("SHOW COLUMNS FROM `player_activity_participation` LIKE 'active_years_reached_at'");
    $ready = $res !== false && $res->num_rows > 0;
    if ($res) {
        $res->free();
    }

    return $ready;
}

/**
 * Oracle: establishing game when player first reached their current active count.
 *
 * @return array{id: int, Date: string}|null
 */
function k2_lb_activity_participation_oracle_reached(mysqli $con, int $playerId, string $periodType): ?array
{
    $activeColumn = k2_lb_activity_participation_active_column($periodType);
    if ($activeColumn === null) {
        return null;
    }

    $sql = 'SELECT a.`' . $activeColumn . '` AS active_count, ('
        . 'SELECT ranked.`period_start` FROM ('
        . 'SELECT pg.`period_start`, ROW_NUMBER() OVER (ORDER BY pg.`period_start` ASC) AS rn '
        . 'FROM `player_period_games` pg '
        . 'WHERE pg.`player_id` = ? AND pg.`period_type` = ? '
        . ') ranked WHERE ranked.rn = a.`' . $activeColumn . '` LIMIT 1'
        . ') AS nth_period '
        . 'FROM `player_activity_participation` a WHERE a.`player_id` = ? LIMIT 1';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return null;
    }
    $stmt->bind_param('isi', $playerId, $periodType, $playerId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === false || $row === null) {
        return null;
    }

    $activeCount = (int) ($row['active_count'] ?? 0);
    $periodStart = $row['nth_period'] ?? null;
    if ($activeCount <= 0 || $periodStart === null || $periodStart === '') {
        return null;
    }

    if (!function_exists('k2_play_streak_establishing_game')) {
        require_once __DIR__ . '/player_play_streaks.php';
    }

    return k2_play_streak_establishing_game($con, $playerId, (string) $periodStart, $periodType);
}

/**
 * Backfill SCH-025 columns from period-list + establishing-game oracle.
 */
function k2_lb_activity_participation_rebuild_reached_columns(mysqli $con): int
{
    if (!k2_lb_activity_participation_reached_columns_ready($con)) {
        return 0;
    }

    $playersRes = $con->query('SELECT `player_id` FROM `player_activity_participation`');
    if ($playersRes === false) {
        return 0;
    }

    $updated = 0;
    while ($pRow = $playersRes->fetch_assoc()) {
        $playerId = (int) $pRow['player_id'];
        $sets = [];
        $types = '';
        $values = [];

        foreach (['day', 'week', 'month', 'year'] as $periodType) {
            $atCol = k2_lb_activity_participation_reached_at_column($periodType);
            $gidCol = k2_lb_activity_participation_reached_game_id_column($periodType);
            if ($atCol === null || $gidCol === null) {
                continue;
            }

            $oracle = k2_lb_activity_participation_oracle_reached($con, $playerId, $periodType);
            if ($oracle === null) {
                $sets[] = '`' . $atCol . '` = NULL, `' . $gidCol . '` = NULL';
            } else {
                $sets[] = '`' . $atCol . '` = ?, `' . $gidCol . '` = ?';
                $types .= 'si';
                $values[] = (string) $oracle['Date'];
                $values[] = (int) $oracle['id'];
            }
        }

        if ($sets === []) {
            continue;
        }

        $sql = 'UPDATE `player_activity_participation` SET ' . implode(', ', $sets)
            . ' WHERE `player_id` = ?';
        $types .= 'i';
        $values[] = $playerId;

        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            continue;
        }
        $stmt->bind_param($types, ...$values);
        if ($stmt->execute()) {
            $updated++;
        }
        $stmt->close();
    }
    $playersRes->free();

    return $updated;
}

/**
 * @return list<string>
 */
function k2_lb_activity_participation_reached_oracle_mismatches(mysqli $con, ?int $playerId = null): array
{
    if (!k2_lb_activity_participation_reached_columns_ready($con)) {
        return ['SCH-025 columns missing'];
    }

    $mismatches = [];
    $sql = 'SELECT `player_id`, `active_days`, `active_weeks`, `active_months`, `active_years`, '
        . '`active_days_reached_game_id`, `active_weeks_reached_game_id`, '
        . '`active_months_reached_game_id`, `active_years_reached_game_id` '
        . 'FROM `player_activity_participation`';
    if ($playerId !== null) {
        $sql .= ' WHERE `player_id` = ' . (int) $playerId;
    }

    $res = $con->query($sql);
    if ($res === false) {
        return ['participation read failed'];
    }

    while ($row = $res->fetch_assoc()) {
        $pid = (int) $row['player_id'];
        foreach (['day', 'week', 'month', 'year'] as $periodType) {
            $activeCol = k2_lb_activity_participation_active_column($periodType);
            $gidCol = k2_lb_activity_participation_reached_game_id_column($periodType);
            if ($activeCol === null || $gidCol === null) {
                continue;
            }

            $count = (int) ($row[$activeCol] ?? 0);
            $storedGid = $row[$gidCol] !== null ? (int) $row[$gidCol] : 0;
            if ($count <= 0) {
                if ($storedGid !== 0) {
                    $mismatches[] = "player {$pid} {$periodType}: count=0 stored_game={$storedGid}";
                }
                continue;
            }

            $oracle = k2_lb_activity_participation_oracle_reached($con, $pid, $periodType);
            $oracleGid = $oracle !== null ? (int) $oracle['id'] : 0;
            if ($oracleGid !== $storedGid) {
                $mismatches[] = "player {$pid} {$periodType}: stored_game={$storedGid} oracle_game={$oracleGid}";
            }
        }
    }
    $res->free();

    return $mismatches;
}

/**
 * Nth distinct period_start per player (N = their current active count).
 * Repair / legacy read fallback only — hot path uses SCH-025 stored columns.
 *
 * @return array<int, string>
 */
function k2_lb_activity_participation_nth_period_map(mysqli $con, string $periodType): array
{
    $activeColumn = k2_lb_activity_participation_active_column($periodType);
    if ($activeColumn === null) {
        return [];
    }

    $sql = 'SELECT t.`player_id`, t.`period_start` FROM ('
        . 'SELECT pg.`player_id`, pg.`period_start`, '
        . 'ROW_NUMBER() OVER (PARTITION BY pg.`player_id` ORDER BY pg.`period_start` ASC) AS rn, '
        . 'a.`' . $activeColumn . '` AS active_count '
        . 'FROM `player_period_games` pg '
        . 'INNER JOIN `player_activity_participation` a ON a.`player_id` = pg.`player_id` '
        . 'WHERE pg.`period_type` = ? '
        . ') t WHERE t.rn = t.active_count AND t.active_count > 0';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param('s', $periodType);
    $stmt->execute();
    $res = $stmt->get_result();
    $map = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $map[(int) $row['player_id']] = (string) $row['period_start'];
        }
        $res->free();
    }
    $stmt->close();

    return $map;
}

/**
 * @param array<int, string> $nthPeriodMap
 */
function k2_lb_activity_participation_reached_at(
    mysqli $con,
    int $playerId,
    string $periodType,
    int $activeCount,
    array $nthPeriodMap
): ?string {
    if ($activeCount <= 0) {
        return null;
    }

    $periodStart = $nthPeriodMap[$playerId] ?? null;
    if ($periodStart === null || $periodStart === '') {
        return null;
    }

    if (!function_exists('k2_play_streak_establishing_game')) {
        require_once __DIR__ . '/player_play_streaks.php';
    }

    $establishing = k2_play_streak_establishing_game($con, $playerId, $periodStart, $periodType);
    if ($establishing === null || ($establishing['Date'] ?? '') === '') {
        return null;
    }

    return (string) $establishing['Date'];
}

/**
 * @param array<int, string> $nthPeriodMap
 */
function k2_lb_activity_participation_count_tie_value(
    mysqli $con,
    int $playerId,
    string $periodType,
    int $activeCount,
    array $nthPeriodMap
): ?int {
    $reachedAt = k2_lb_activity_participation_reached_at($con, $playerId, $periodType, $activeCount, $nthPeriodMap);

    return k2_lb_activity_participation_period_tie_value($reachedAt);
}

/**
 * @return array{
 *   day: array<int, string>,
 *   week: array<int, string>,
 *   month: array<int, string>,
 *   year: array<int, string>
 * }
 */
function k2_lb_activity_participation_nth_period_maps(mysqli $con): array
{
    return [
        'day' => k2_lb_activity_participation_nth_period_map($con, 'day'),
        'week' => k2_lb_activity_participation_nth_period_map($con, 'week'),
        'month' => k2_lb_activity_participation_nth_period_map($con, 'month'),
        'year' => k2_lb_activity_participation_nth_period_map($con, 'year'),
    ];
}

function k2_lb_activity_participation_period_tie_value(mixed $reachedAt): ?int
{
    if ($reachedAt === null || $reachedAt === '') {
        return null;
    }

    $ts = strtotime((string) $reachedAt);
    if ($ts === false) {
        return null;
    }

    return $ts;
}

function k2_lb_activity_echo_count_td(int $count, ?int $sortTieValue = null): void
{
    $tieAttr = $sortTieValue !== null ? ' data-k2-sort-tie-value="' . (int) $sortTieValue . '"' : '';
    echo '<td data-k2-sort-value="' . (int) $count . '"' . $tieAttr . '>';
    echo k2_fmt_int($count);
    echo '</td>';
}

function k2_lb_activity_participation_longevity_expr(): string
{
    return 'CASE WHEN a.`first_rated_day` IS NULL OR a.`last_rated_day` IS NULL THEN NULL '
        . 'ELSE DATEDIFF(a.`last_rated_day`, a.`first_rated_day`) + 1 END';
}

/** Default ORDER BY tail for activity participation LB (no leading ORDER BY). */
function k2_lb_activity_participation_default_order_sql(): string
{
    return 'p.`NumberGames` DESC, p.`Rating` DESC';
}

/**
 * Sortable column index → SQL expression for activity participation LB SSR order.
 *
 * @return array<int, string>
 */
function k2_lb_activity_participation_order_column_map(): array
{
    return [
        1 => 'p.`Name`',
        2 => 'p.`Rating`',
        3 => 'p.`NumberGames`',
        4 => 'COALESCE(a.`active_days`, 0)',
        5 => 'COALESCE(a.`active_weeks`, 0)',
        6 => 'COALESCE(a.`active_months`, 0)',
        7 => 'COALESCE(a.`active_years`, 0)',
        8 => k2_lb_activity_participation_longevity_expr(),
        9 => 'a.`first_rated_day`',
        10 => 'a.`last_rated_day`',
    ];
}

/** Default ORDER BY tail for activity in-a-row LB (no leading ORDER BY). */
function k2_lb_activity_in_a_row_default_order_sql(): string
{
    return 'COALESCE(psd.`best_streak`, 0) DESC, p.`Rating` DESC';
}

/**
 * Sortable column index → SQL expression for activity in-a-row LB SSR order.
 *
 * @return array<int, string>
 */
function k2_lb_activity_in_a_row_order_column_map(): array
{
    return [
        1 => 'p.`Name`',
        2 => 'p.`Rating`',
        3 => 'p.`NumberGames`',
        4 => 'COALESCE(psd.`best_streak`, 0)',
        5 => 'COALESCE(psw.`best_streak`, 0)',
        6 => 'COALESCE(psm.`best_streak`, 0)',
        7 => 'COALESCE(psy.`best_streak`, 0)',
    ];
}

/**
 * @return mysqli_result|false
 */
function k2_lb_activity_query_peaks(mysqli $con, string $orderClause = 'COALESCE(pd.`games`, 0) DESC, p.`Rating` DESC, p.`id` ASC')
{
    $where = k2_lb_player_where_sql_for_alias('p');
    $sql = 'SELECT p.`id`, p.`Name`, p.`Rating`, p.`NumberGames`, '
        . 'pd.`games` AS `peak_day_games`, DATE_FORMAT(pd.`period_start`, \'%Y-%m-%d\') AS `peak_day_key`, '
        . 'pw.`games` AS `peak_week_games`, DATE_FORMAT(pw.`period_start`, \'%Y-%m-%d\') AS `peak_week_key`, '
        . 'pm.`games` AS `peak_month_games`, DATE_FORMAT(pm.`period_start`, \'%Y-%m-%d\') AS `peak_month_key`, '
        . 'py.`games` AS `peak_year_games`, DATE_FORMAT(py.`period_start`, \'%Y-%m-%d\') AS `peak_year_key` '
        . 'FROM `playertable` p '
        . 'LEFT JOIN `player_peak_period_games` pd ON pd.`player_id` = p.`id` AND pd.`period_type` = \'day\' '
        . 'LEFT JOIN `player_peak_period_games` pw ON pw.`player_id` = p.`id` AND pw.`period_type` = \'week\' '
        . 'LEFT JOIN `player_peak_period_games` pm ON pm.`player_id` = p.`id` AND pm.`period_type` = \'month\' '
        . 'LEFT JOIN `player_peak_period_games` py ON py.`player_id` = p.`id` AND py.`period_type` = \'year\' '
        . 'WHERE ' . $where . ' '
        . 'ORDER BY ' . $orderClause;

    return $con->query($sql);
}

/**
 * @return mysqli_result|false
 */
function k2_lb_activity_query_participation(mysqli $con, ?string $orderClause = null)
{
    $orderClause ??= k2_lb_activity_participation_default_order_sql();
    $where = k2_lb_player_where_sql_for_alias('p');
    $reachedCols = k2_lb_activity_participation_reached_columns_ready($con)
        ? 'a.`active_days_reached_at`, a.`active_weeks_reached_at`, '
            . 'a.`active_months_reached_at`, a.`active_years_reached_at`, '
        : 'NULL AS `active_days_reached_at`, NULL AS `active_weeks_reached_at`, '
            . 'NULL AS `active_months_reached_at`, NULL AS `active_years_reached_at`, ';

    $sql = 'SELECT p.`id`, p.`Name`, p.`Rating`, p.`NumberGames`, '
        . 'COALESCE(a.`active_days`, 0) AS `active_days`, '
        . 'COALESCE(a.`active_weeks`, 0) AS `active_weeks`, '
        . 'COALESCE(a.`active_months`, 0) AS `active_months`, '
        . 'COALESCE(a.`active_years`, 0) AS `active_years`, '
        . $reachedCols
        . 'a.`first_rated_day`, a.`last_rated_day`, '
        . k2_lb_activity_participation_longevity_expr() . ' AS `longevity_days` '
        . 'FROM `playertable` p '
        . 'LEFT JOIN `player_activity_participation` a ON a.`player_id` = p.`id` '
        . 'WHERE ' . $where . ' '
        . 'ORDER BY ' . $orderClause;

    return $con->query($sql);
}

/**
 * @return mysqli_result|false
 */
function k2_lb_activity_query_in_a_row(mysqli $con, ?string $orderClause = null)
{
    $orderClause ??= k2_lb_activity_in_a_row_default_order_sql();
    $where = k2_lb_player_where_sql_for_alias('p');
    $sql = 'SELECT p.`id`, p.`Name`, p.`Rating`, p.`NumberGames`, '
        . 'COALESCE(psd.`best_streak`, 0) AS `streak_days`, '
        . 'DATE_FORMAT(psd.`best_anchor_start`, \'%Y-%m-%d\') AS `streak_days_start`, '
        . 'psd.`best_achieved_at` AS `streak_days_achieved_at`, '
        . 'COALESCE(psw.`best_streak`, 0) AS `streak_weeks`, '
        . 'DATE_FORMAT(psw.`best_anchor_start`, \'%Y-%m-%d\') AS `streak_weeks_start`, '
        . 'psw.`best_achieved_at` AS `streak_weeks_achieved_at`, '
        . 'COALESCE(psm.`best_streak`, 0) AS `streak_months`, '
        . 'DATE_FORMAT(psm.`best_anchor_start`, \'%Y-%m-%d\') AS `streak_months_start`, '
        . 'psm.`best_achieved_at` AS `streak_months_achieved_at`, '
        . 'COALESCE(psy.`best_streak`, 0) AS `streak_years`, '
        . 'DATE_FORMAT(psy.`best_anchor_start`, \'%Y-%m-%d\') AS `streak_years_start`, '
        . 'psy.`best_achieved_at` AS `streak_years_achieved_at` '
        . 'FROM `playertable` p '
        . 'LEFT JOIN `player_play_streaks` psd ON psd.`player_id` = p.`id` AND psd.`streak_type` = \'day\' '
        . 'LEFT JOIN `player_play_streaks` psw ON psw.`player_id` = p.`id` AND psw.`streak_type` = \'week\' '
        . 'LEFT JOIN `player_play_streaks` psm ON psm.`player_id` = p.`id` AND psm.`streak_type` = \'month\' '
        . 'LEFT JOIN `player_play_streaks` psy ON psy.`player_id` = p.`id` AND psy.`streak_type` = \'year\' '
        . 'WHERE ' . $where . ' '
        . 'ORDER BY ' . $orderClause;

    return $con->query($sql);
}

function k2_lb_activity_peak_help(string $period): string
{
    $meta = k2_peak_period_leaderboard_meta($period);

    return $meta['hint'];
}

function k2_lb_activity_format_longevity(?int $days): string
{
    if ($days === null || $days <= 0) {
        return k2_fmt_dash();
    }

    return (int) $days . ($days === 1 ? ' day' : ' days');
}

function k2_lb_activity_format_rated_day(?string $dayYmd): string
{
    if ($dayYmd === null || $dayYmd === '') {
        return k2_fmt_dash();
    }

    return k2_format_peak_period('all-time', $dayYmd);
}

/**
 * @return array{display: string, help: ?string, help_html: bool}
 */
function k2_lb_activity_streak_cell_meta(int $count, mixed $games, ?string $startAnchor, string $streakType): array
{
    $display = k2_fmt_count($count, $games);
    $meta = ['display' => $display, 'help' => null, 'help_html' => false];

    if ($count <= 0 || $startAnchor === null || $startAnchor === '') {
        return $meta;
    }

    require_once __DIR__ . '/player_play_streaks.php';
    $help = k2_play_streak_best_run_tooltip_html($streakType, $count, $startAnchor);
    if ($help === '') {
        return $meta;
    }

    $meta['help'] = $help;
    $meta['help_html'] = true;

    return $meta;
}
