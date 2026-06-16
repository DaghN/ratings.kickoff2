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

function k2_lb_activity_echo_tooltip_td(array $meta, ?int $sortValue = null, string $class = ''): void
{
    $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
    $sortAttr = $sortValue !== null ? ' data-k2-sort-value="' . (int) $sortValue . '"' : '';

    echo '<td' . $classAttr . $sortAttr;
    if ($meta['help'] !== null) {
        echo ' tabindex="0" data-k2-tooltip-hide-title="1" data-k2-help="' . htmlspecialchars($meta['help'], ENT_QUOTES, 'UTF-8') . '"';
        if ($meta['help_html']) {
            echo ' data-k2-help-html="1"';
        }
    }
    echo '>';
    if (!empty($meta['href'])) {
        echo '<a class="k2-table-cell-link" href="' . htmlspecialchars((string) $meta['href'], ENT_QUOTES, 'UTF-8') . '">';
        echo htmlspecialchars($meta['display'], ENT_QUOTES, 'UTF-8');
        echo '</a>';
    } else {
        echo htmlspecialchars($meta['display'], ENT_QUOTES, 'UTF-8');
    }
    echo '</td>';
}

/**
 * @return mysqli_result|false
 */
function k2_lb_activity_query_peaks(mysqli $con)
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
        . 'ORDER BY COALESCE(pd.`games`, 0) DESC, p.`Rating` DESC';

    return $con->query($sql);
}

/**
 * @return mysqli_result|false
 */
function k2_lb_activity_query_participation(mysqli $con)
{
    $where = k2_lb_player_where_sql_for_alias('p');
    $sql = 'SELECT p.`id`, p.`Name`, p.`Rating`, p.`NumberGames`, '
        . 'COALESCE(a.`active_days`, 0) AS `active_days`, '
        . 'COALESCE(a.`active_weeks`, 0) AS `active_weeks`, '
        . 'COALESCE(a.`active_months`, 0) AS `active_months`, '
        . 'COALESCE(a.`active_years`, 0) AS `active_years`, '
        . 'a.`first_rated_day`, a.`last_rated_day`, '
        . 'CASE WHEN a.`first_rated_day` IS NULL OR a.`last_rated_day` IS NULL THEN NULL '
        . 'ELSE DATEDIFF(a.`last_rated_day`, a.`first_rated_day`) + 1 END AS `longevity_days` '
        . 'FROM `playertable` p '
        . 'LEFT JOIN `player_activity_participation` a ON a.`player_id` = p.`id` '
        . 'WHERE ' . $where . ' '
        . 'ORDER BY p.`id` ASC';

    return $con->query($sql);
}

/**
 * @return mysqli_result|false
 */
function k2_lb_activity_query_in_a_row(mysqli $con)
{
    $where = k2_lb_player_where_sql_for_alias('p');
    $sql = 'SELECT p.`id`, p.`Name`, p.`Rating`, p.`NumberGames`, '
        . 'COALESCE(psd.`best_streak`, 0) AS `streak_days`, '
        . 'DATE_FORMAT(psd.`best_anchor_start`, \'%Y-%m-%d\') AS `streak_days_start`, '
        . 'COALESCE(psw.`best_streak`, 0) AS `streak_weeks`, '
        . 'DATE_FORMAT(psw.`best_anchor_start`, \'%Y-%m-%d\') AS `streak_weeks_start`, '
        . 'COALESCE(psm.`best_streak`, 0) AS `streak_months`, '
        . 'DATE_FORMAT(psm.`best_anchor_start`, \'%Y-%m-%d\') AS `streak_months_start`, '
        . 'COALESCE(psy.`best_streak`, 0) AS `streak_years`, '
        . 'DATE_FORMAT(psy.`best_anchor_start`, \'%Y-%m-%d\') AS `streak_years_start` '
        . 'FROM `playertable` p '
        . 'LEFT JOIN `player_play_streaks` psd ON psd.`player_id` = p.`id` AND psd.`streak_type` = \'day\' '
        . 'LEFT JOIN `player_play_streaks` psw ON psw.`player_id` = p.`id` AND psw.`streak_type` = \'week\' '
        . 'LEFT JOIN `player_play_streaks` psm ON psm.`player_id` = p.`id` AND psm.`streak_type` = \'month\' '
        . 'LEFT JOIN `player_play_streaks` psy ON psy.`player_id` = p.`id` AND psy.`streak_type` = \'year\' '
        . 'WHERE ' . $where . ' '
        . 'ORDER BY COALESCE(psd.`best_streak`, 0) DESC, p.`Rating` DESC';

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
