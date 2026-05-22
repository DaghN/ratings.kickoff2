<?php
/**
 * Players ranked by rated games in one calendar day, month, or year (whole server).
 */

/**
 * @return 'day'|'month'|'year'|null
 */
function k2_period_activity_normalize_period(string $period): ?string
{
    $period = strtolower(trim($period));
    if (in_array($period, ['day', 'month', 'year'], true)) {
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
 * @return array{where: string, types: string, bind: array<int|string>}|null
 */
function k2_period_activity_filter(string $period, string $key): ?array
{
    switch ($period) {
        case 'day':
            return [
                'where' => 'DATE(`Date`) = ?',
                'types' => 's',
                'bind' => [$key],
            ];
        case 'month':
            return [
                'where' => "DATE_FORMAT(`Date`, '%Y-%m') = ?",
                'types' => 's',
                'bind' => [$key],
            ];
        case 'year':
            return [
                'where' => 'YEAR(`Date`) = ?',
                'types' => 'i',
                'bind' => [(int) $key],
            ];
        default:
            return null;
    }
}

function k2_period_activity_total_games(mysqli $con, string $period, string $key, ?string &$error = null): int
{
    $error = null;
    $filter = k2_period_activity_filter($period, $key);
    if ($filter === null) {
        $error = 'invalid_period';

        return 0;
    }

    $sql = 'SELECT COUNT(*) AS c FROM ratedresults WHERE ' . $filter['where'];
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $error = mysqli_error($con);

        return 0;
    }

    mysqli_stmt_bind_param($stmt, $filter['types'], ...$filter['bind']);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return 0;
    }

    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    return $row ? (int) $row['c'] : 0;
}

/**
 * @return array<int, array{rank: int, player_id: int, player_name: string, games: int}>
 */
function k2_period_activity_leaderboard_entries(
    mysqli $con,
    string $period,
    string $key,
    int $limit = 50,
    ?string &$error = null
): array {
    $error = null;
    $filter = k2_period_activity_filter($period, $key);
    if ($filter === null) {
        $error = 'invalid_period';

        return [];
    }

    $limit = max(1, min(100, $limit));
    $limitSql = (int) $limit;
    $where = $filter['where'];

    $sql = 'SELECT pm.player_id, p.Name AS player_name, pm.games '
        . 'FROM ('
        . 'SELECT player_id, COUNT(*) AS games FROM ('
        . 'SELECT idA AS player_id FROM ratedresults WHERE ' . $where . ' '
        . 'UNION ALL '
        . 'SELECT idB AS player_id FROM ratedresults WHERE ' . $where
        . ') AS appearances GROUP BY player_id'
        . ') AS pm INNER JOIN playertable p ON p.ID = pm.player_id '
        . 'ORDER BY pm.games DESC, p.Name ASC LIMIT ' . $limitSql;

    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        $error = mysqli_error($con);

        return [];
    }

    $bindTypes = $filter['types'] . $filter['types'];
    $bindValues = array_merge($filter['bind'], $filter['bind']);
    mysqli_stmt_bind_param($stmt, $bindTypes, ...$bindValues);

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        return [];
    }

    $res = mysqli_stmt_get_result($stmt);
    $entries = [];
    $rank = 0;
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rank++;
            $entries[] = [
                'rank' => $rank,
                'player_id' => (int) $row['player_id'],
                'player_name' => (string) $row['player_name'],
                'games' => (int) $row['games'],
            ];
        }
    }
    mysqli_stmt_close($stmt);

    return $entries;
}

/**
 * @return array{min: string|null, max: string|null}
 */
function k2_period_activity_day_bounds(mysqli $con, ?string &$error = null): array
{
    $error = null;
    $sql = 'SELECT DATE(MIN(`Date`)) AS dmin, DATE(MAX(`Date`)) AS dmax FROM ratedresults';
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
            $sql = 'SELECT DISTINCT DATE(`Date`) AS k FROM ratedresults ORDER BY k DESC';
            break;
        case 'month':
            $sql = "SELECT DISTINCT DATE_FORMAT(`Date`, '%Y-%m') AS k FROM ratedresults ORDER BY k DESC";
            break;
        case 'year':
            $sql = 'SELECT DISTINCT YEAR(`Date`) AS k FROM ratedresults ORDER BY k DESC';
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

function k2_format_period_activity_label(string $period, string $key): string
{
    switch ($period) {
        case 'day':
            $ts = strtotime($key);
            return $ts ? date('M j, Y', $ts) : $key;
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
