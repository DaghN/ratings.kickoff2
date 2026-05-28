<?php
/**
 * Players ranked by rated games in one calendar day, week, month, or year (whole server).
 */

/**
 * @return 'day'|'week'|'month'|'year'|null
 */
function k2_period_activity_normalize_period(string $period): ?string
{
    $period = strtolower(trim($period));
    if (in_array($period, ['day', 'week', 'month', 'year'], true)) {
        return $period;
    }

    return null;
}

/**
 * @return string|null Normalized period key, or null if invalid.
 */
function k2_period_activity_normalize_key(string $period, string $key): ?string
{
    $key = trim($key);
    switch ($period) {
        case 'day':
            $d = DateTime::createFromFormat('Y-m-d', $key);
            if (!$d instanceof DateTime || $d->format('Y-m-d') !== $key) {
                return null;
            }

            return $key;
        case 'week':
            $d = DateTime::createFromFormat('Y-m-d', $key);
            if (!$d instanceof DateTime || $d->format('Y-m-d') !== $key || $d->format('N') !== '1') {
                return null;
            }

            return $key;
        case 'month':
            $d = DateTime::createFromFormat('Y-m-d', $key . '-01');
            if (!$d instanceof DateTime || $d->format('Y-m') !== $key) {
                return null;
            }

            return $key;
        case 'year':
            if (!preg_match('/^\d{4}$/', $key)) {
                return null;
            }
            $y = (int) $key;
            if ($y < 1990 || $y > 2100) {
                return null;
            }

            return (string) $y;
        default:
            return null;
    }
}

/**
 * @return string|null player_period_games.period_start, or null if invalid.
 */
function k2_period_activity_period_start(string $period, string $key): ?string
{
    switch ($period) {
        case 'day':
            return k2_period_activity_normalize_key('day', $key);
        case 'week':
            return k2_period_activity_normalize_key('week', $key);
        case 'month':
            $normalized = k2_period_activity_normalize_key('month', $key);
            return $normalized === null ? null : $normalized . '-01';
        case 'year':
            $normalized = k2_period_activity_normalize_key('year', $key);
            return $normalized === null ? null : $normalized . '-01-01';
        default:
            return null;
    }
}

function k2_period_activity_total_games(mysqli $con, string $period, string $key, ?string &$error = null): int
{
    $error = null;
    $periodStart = k2_period_activity_period_start($period, $key);
    if ($periodStart === null) {
        $error = 'invalid_period';

        return 0;
    }

    $sql = 'SELECT COALESCE(SUM(games), 0) AS appearances '
        . 'FROM player_period_games WHERE period_type = ? AND period_start = ?';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $error = mysqli_error($con);

        return 0;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $period, $periodStart);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return 0;
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    $appearances = $row ? (int) $row['appearances'] : 0;

    return (int) floor($appearances / 2);
}

/**
 * @param int $limit Max rows after sort; 0 = all players with ≥1 game in the period (Status leagues).
 * @return array<int, array{rank: int, player_id: int, player_name: string, games: int}>
 */
function k2_period_activity_leaderboard_entries(
    mysqli $con,
    string $period,
    string $key,
    int $limit = 0,
    ?string &$error = null
): array {
    $error = null;
    $periodStart = k2_period_activity_period_start($period, $key);
    if ($periodStart === null) {
        $error = 'invalid_period';

        return [];
    }

    $sql = 'SELECT g.player_id, p.Name AS player_name, g.games '
        . 'FROM player_period_games g INNER JOIN playertable p ON p.ID = g.player_id '
        . 'WHERE g.period_type = ? AND g.period_start = ?';

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $error = mysqli_error($con);

        return [];
    }

    mysqli_stmt_bind_param($stmt, 'ss', $period, $periodStart);

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return [];
    }

    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'player_id' => (int) $row['player_id'],
                'player_name' => (string) $row['player_name'],
                'games' => (int) $row['games'],
            ];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    if ($rows === []) {
        return [];
    }

    if (!function_exists('k2_league_sort_rows')) {
        require_once __DIR__ . '/league_standings.php';
    }

    $bounds = k2_league_bounds_for_start($period, $periodStart);
    if ($bounds !== null) {
        $firstGames = k2_league_load_first_games($con, $bounds['start'], $bounds['end']);
        $rows = k2_league_attach_first_games($rows, $firstGames);
    }
    $rows = k2_league_apply_ranks(k2_league_sort_rows('activity', $rows));
    if ($limit > 0) {
        $rows = array_slice($rows, 0, max(1, min(500, $limit)));
    }

    $entries = [];
    foreach ($rows as $row) {
        $entries[] = [
            'rank' => (int) $row['rank'],
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) $row['player_name'],
            'games' => (int) $row['games'],
        ];
    }

    return $entries;
}

/**
 * @return array{min: string|null, max: string|null}
 */
function k2_period_activity_day_bounds(mysqli $con, ?string &$error = null): array
{
    $error = null;
    $sql = "SELECT MIN(period_start) AS dmin, MAX(period_start) AS dmax FROM player_period_games WHERE period_type = 'day'";
    $res = mysqli_query($con, $sql);
    if ($res === false) {
        $error = mysqli_error($con);

        return ['min' => null, 'max' => null];
    }
    $row = mysqli_fetch_assoc($res);
    if (!$row || $row['dmin'] === null) {
        return ['min' => null, 'max' => null];
    }

    return [
        'min' => (string) $row['dmin'],
        'max' => (string) $row['dmax'],
    ];
}

/**
 * @return list<string>
 */
function k2_period_activity_available_keys(mysqli $con, string $period, ?string &$error = null): array
{
    $error = null;
    switch ($period) {
        case 'day':
            $sql = "SELECT DISTINCT DATE_FORMAT(period_start, '%Y-%m-%d') AS k FROM player_period_games WHERE period_type = 'day' ORDER BY k DESC";
            break;
        case 'week':
            $sql = "SELECT DISTINCT DATE_FORMAT(period_start, '%Y-%m-%d') AS k FROM player_period_games WHERE period_type = 'week' ORDER BY k DESC";
            break;
        case 'month':
            $sql = "SELECT DISTINCT DATE_FORMAT(period_start, '%Y-%m') AS k FROM player_period_games WHERE period_type = 'month' ORDER BY k DESC";
            break;
        case 'year':
            $sql = "SELECT DISTINCT YEAR(period_start) AS k FROM player_period_games WHERE period_type = 'year' ORDER BY k DESC";
            break;
        default:
            $error = 'invalid_period';

            return [];
    }

    $res = mysqli_query($con, $sql);
    if ($res === false) {
        $error = mysqli_error($con);

        return [];
    }

    $keys = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $keys[] = (string) $row['k'];
    }

    return $keys;
}

/**
 * ISO-8601 calendar week (Monday start), e.g. "Week 22, 2026" — common in DK/EU.
 *
 * @param string $weekMondayYmd Monday of the week (Y-m-d)
 */
function k2_format_calendar_week_label(string $weekMondayYmd): string
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $weekMondayYmd);
    if (!$d) {
        $ts = strtotime($weekMondayYmd);
        if ($ts === false) {
            return $weekMondayYmd;
        }
        $d = (new DateTimeImmutable())->setTimestamp($ts);
    }

    return 'Week ' . (int) $d->format('W') . ', ' . (int) $d->format('o');
}

/** Human label for one calendar day, e.g. Monday, May 27, 2026. */
function k2_format_calendar_day_label(string $dayYmd): string
{
    $d = DateTimeImmutable::createFromFormat('Y-m-d', $dayYmd);
    if (!$d instanceof DateTimeImmutable) {
        return $dayYmd;
    }

    return $d->format('l, F j, Y');
}

function k2_format_period_activity_label(string $period, string $key): string
{
    switch ($period) {
        case 'day':
            return k2_format_calendar_day_label($key);
        case 'week':
            return k2_format_calendar_week_label($key);
        case 'month':
            $d = DateTime::createFromFormat('Y-m-d', $key . '-01');
            if ($d instanceof DateTime) {
                return $d->format('M Y');
            }

            return $key;
        case 'year':
            return $key;
        default:
            return $key;
    }
}

/**
 * @return array{title: string, hint: string, picker_label: string}
 */
function k2_period_activity_leaderboard_meta(string $period): array
{
    switch ($period) {
        case 'day':
            return [
                'title' => 'Games on this day',
                'hint' => 'Players ranked by rated games on the selected calendar day.',
                'picker_label' => 'Day',
            ];
        case 'week':
            return [
                'title' => 'Games in this week',
                'hint' => 'Players ranked by rated games in the selected Monday-starting calendar week.',
                'picker_label' => 'Week',
            ];
        case 'year':
            return [
                'title' => 'Games in this year',
                'hint' => 'Players ranked by rated games in the selected calendar year.',
                'picker_label' => 'Year',
            ];
        default:
            return [
                'title' => 'Games in this month',
                'hint' => 'Players ranked by rated games in the selected calendar month.',
                'picker_label' => 'Month',
            ];
    }
}
