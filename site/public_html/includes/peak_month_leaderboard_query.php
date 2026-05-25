<?php
/**
 * Top players by personal peak calendar day / month / year (most rated games in one period).
 * One row per player (their best period). Ties on game count: earlier period wins.
 *
 * Prefer the player_period_games aggregate when present; fall back to ratedresults while
 * staging/prod are still catching up with the aggregate-table rollout.
 */

/**
 * @return 'day'|'month'|'year'|null
 */
function k2_peak_period_key_sql(string $period): ?string
{
    switch ($period) {
        case 'day':
            return 'DATE(`Date`)';
        case 'month':
            return "DATE_FORMAT(`Date`, '%Y-%m')";
        case 'year':
            return 'YEAR(`Date`)';
        default:
            return null;
    }
}

/**
 * @return string|null SQL expression for player_period_games.period_start.
 */
function k2_peak_period_aggregate_key_sql(string $period): ?string
{
    switch ($period) {
        case 'day':
            return "DATE_FORMAT(g.period_start, '%Y-%m-%d')";
        case 'month':
            return "DATE_FORMAT(g.period_start, '%Y-%m')";
        case 'year':
            return 'YEAR(g.period_start)';
        default:
            return null;
    }
}

/**
 * @return array<int, array{rank: int, player_id: int, player_name: string, period_key: string, games: int}>|null
 */
function k2_peak_period_leaderboard_entries_from_aggregate(mysqli $con, string $period, int $limit = 0, ?string &$error = null): ?array
{
    $error = null;
    $keySql = k2_peak_period_aggregate_key_sql($period);
    if ($keySql === null) {
        $error = 'invalid_period';

        return [];
    }

    $limitSql = $limit > 0 ? ' LIMIT ' . (int) $limit : '';
    $periodSql = "'" . mysqli_real_escape_string($con, $period) . "'";

    $sql = 'SELECT player_id, player_name, period_key, games FROM ('
        . 'SELECT g.player_id, p.Name AS player_name, ' . $keySql . ' AS period_key, g.games, '
        . 'ROW_NUMBER() OVER (PARTITION BY g.player_id ORDER BY g.games DESC, g.period_start ASC) AS rn '
        . 'FROM player_period_games g INNER JOIN playertable p ON p.ID = g.player_id '
        . 'WHERE g.period_type = ' . $periodSql
        . ') AS best_period WHERE rn = 1 '
        . 'ORDER BY games DESC, period_key ASC' . $limitSql;

    $res = mysqli_query($con, $sql);
    if ($res === false) {
        $error = mysqli_error($con);

        if (mysqli_errno($con) === 1146) {
            return null;
        }

        return [];
    }

    $entries = [];
    $rank = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $rank++;
        $entries[] = [
            'rank' => $rank,
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) $row['player_name'],
            'period_key' => (string) $row['period_key'],
            'games' => (int) $row['games'],
        ];
    }

    return $entries ?: null;
}

/**
 * @return array<int, array{rank: int, player_id: int, player_name: string, period_key: string, games: int}>|null
 */
function k2_peak_all_time_leaderboard_entries_from_aggregate(mysqli $con, int $limit = 0, ?string &$error = null): ?array
{
    $error = null;
    $limitSql = $limit > 0 ? ' LIMIT ' . (int) $limit : '';

    $sql = 'SELECT p.ID AS player_id, p.Name AS player_name, '
        . "DATE_FORMAT(MIN(g.period_start), '%Y-%m-%d') AS period_key, "
        . 'p.NumberGames AS games '
        . 'FROM playertable p INNER JOIN player_period_games g ON g.player_id = p.ID '
        . "AND g.period_type = 'day' "
        . 'WHERE p.NumberGames > 0 '
        . 'GROUP BY p.ID, p.Name, p.NumberGames '
        . 'ORDER BY games DESC, period_key ASC' . $limitSql;

    $res = mysqli_query($con, $sql);
    if ($res === false) {
        $error = mysqli_error($con);

        if (mysqli_errno($con) === 1146) {
            return null;
        }

        return [];
    }

    $entries = [];
    $rank = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $rank++;
        $entries[] = [
            'rank' => $rank,
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) $row['player_name'],
            'period_key' => (string) $row['period_key'],
            'games' => (int) $row['games'],
        ];
    }

    return $entries ?: null;
}

/**
 * @return array<int, array{rank: int, player_id: int, player_name: string, period_key: string, games: int}>
 */
function k2_peak_all_time_leaderboard_entries(mysqli $con, int $limit = 0, ?string &$error = null): array
{
    $error = null;
    $aggregateError = null;
    $aggregateEntries = k2_peak_all_time_leaderboard_entries_from_aggregate($con, $limit, $aggregateError);
    if ($aggregateEntries !== null) {
        $error = $aggregateError;

        return $aggregateEntries;
    }

    $limitSql = $limit > 0 ? ' LIMIT ' . (int) $limit : '';

    $sql = 'SELECT p.ID AS player_id, p.Name AS player_name, '
        . "DATE_FORMAT(first_games.first_date, '%Y-%m-%d') AS period_key, "
        . 'p.NumberGames AS games '
        . 'FROM playertable p INNER JOIN ('
        . 'SELECT player_id, MIN(game_date) AS first_date FROM ('
        . 'SELECT idA AS player_id, `Date` AS game_date FROM ratedresults '
        . 'UNION ALL '
        . 'SELECT idB AS player_id, `Date` AS game_date FROM ratedresults'
        . ') AS appearances GROUP BY player_id'
        . ') AS first_games ON first_games.player_id = p.ID '
        . 'WHERE p.NumberGames > 0 '
        . 'ORDER BY games DESC, period_key ASC' . $limitSql;

    $res = mysqli_query($con, $sql);
    if ($res === false) {
        $error = mysqli_error($con);
        return [];
    }

    $entries = [];
    $rank = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $rank++;
        $entries[] = [
            'rank' => $rank,
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) $row['player_name'],
            'period_key' => (string) $row['period_key'],
            'games' => (int) $row['games'],
        ];
    }

    return $entries;
}

/**
 * @return array<int, array{rank: int, player_id: int, player_name: string, first_game: string, last_game: string, days: int}>|null
 */
function k2_peak_longevity_leaderboard_entries_from_aggregate(mysqli $con, int $limit = 0, ?string &$error = null): ?array
{
    $error = null;
    $limitSql = $limit > 0 ? ' LIMIT ' . (int) $limit : '';

    $sql = 'SELECT p.ID AS player_id, p.Name AS player_name, '
        . "DATE_FORMAT(MIN(g.period_start), '%Y-%m-%d') AS first_game, "
        . "DATE_FORMAT(MAX(g.period_start), '%Y-%m-%d') AS last_game, "
        . 'DATEDIFF(MAX(g.period_start), MIN(g.period_start)) + 1 AS days '
        . 'FROM player_period_games g INNER JOIN playertable p ON p.ID = g.player_id '
        . "WHERE g.period_type = 'day' "
        . 'GROUP BY p.ID, p.Name '
        . 'ORDER BY days DESC, first_game ASC, player_name ASC' . $limitSql;

    $res = mysqli_query($con, $sql);
    if ($res === false) {
        $error = mysqli_error($con);

        if (mysqli_errno($con) === 1146) {
            return null;
        }

        return [];
    }

    $entries = [];
    $rank = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $rank++;
        $entries[] = [
            'rank' => $rank,
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) $row['player_name'],
            'first_game' => (string) $row['first_game'],
            'last_game' => (string) $row['last_game'],
            'days' => (int) $row['days'],
        ];
    }

    return $entries ?: null;
}

/**
 * @return array<int, array{rank: int, player_id: int, player_name: string, first_game: string, last_game: string, days: int}>
 */
function k2_peak_longevity_leaderboard_entries(mysqli $con, int $limit = 0, ?string &$error = null): array
{
    $error = null;
    $aggregateError = null;
    $aggregateEntries = k2_peak_longevity_leaderboard_entries_from_aggregate($con, $limit, $aggregateError);
    if ($aggregateEntries !== null) {
        $error = $aggregateError;

        return $aggregateEntries;
    }

    $limitSql = $limit > 0 ? ' LIMIT ' . (int) $limit : '';

    $sql = 'SELECT p.ID AS player_id, p.Name AS player_name, '
        . "DATE_FORMAT(MIN(a.game_date), '%Y-%m-%d') AS first_game, "
        . "DATE_FORMAT(MAX(a.game_date), '%Y-%m-%d') AS last_game, "
        . 'DATEDIFF(DATE(MAX(a.game_date)), DATE(MIN(a.game_date))) + 1 AS days '
        . 'FROM playertable p INNER JOIN ('
        . 'SELECT idA AS player_id, `Date` AS game_date FROM ratedresults '
        . 'UNION ALL '
        . 'SELECT idB AS player_id, `Date` AS game_date FROM ratedresults'
        . ') AS a ON a.player_id = p.ID '
        . 'GROUP BY p.ID, p.Name '
        . 'ORDER BY days DESC, first_game ASC, player_name ASC' . $limitSql;

    $res = mysqli_query($con, $sql);
    if ($res === false) {
        $error = mysqli_error($con);
        return [];
    }

    $entries = [];
    $rank = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $rank++;
        $entries[] = [
            'rank' => $rank,
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) $row['player_name'],
            'first_game' => (string) $row['first_game'],
            'last_game' => (string) $row['last_game'],
            'days' => (int) $row['days'],
        ];
    }

    return $entries;
}

/**
 * @return array<int, array{rank: int, player_id: int, player_name: string, period_key: string, games: int}>
 */
function k2_peak_period_leaderboard_entries(mysqli $con, string $period, int $limit = 0, ?string &$error = null): array
{
    $error = null;
    if ($period === 'all-time') {
        return k2_peak_all_time_leaderboard_entries($con, $limit, $error);
    }

    $aggregateError = null;
    $aggregateEntries = k2_peak_period_leaderboard_entries_from_aggregate($con, $period, $limit, $aggregateError);
    if ($aggregateEntries !== null) {
        $error = $aggregateError;

        return $aggregateEntries;
    }

    $keySql = k2_peak_period_key_sql($period);
    if ($keySql === null) {
        $error = 'invalid_period';

        return [];
    }

    $limitSql = $limit > 0 ? ' LIMIT ' . (int) $limit : '';

    $sql = 'SELECT player_id, player_name, period_key, games FROM ('
        . 'SELECT pm.player_id, p.Name AS player_name, pm.period_key, pm.games, '
        . 'ROW_NUMBER() OVER (PARTITION BY pm.player_id ORDER BY pm.games DESC, pm.period_key ASC) AS rn '
        . 'FROM ('
        . 'SELECT player_id, period_key, COUNT(*) AS games FROM ('
        . 'SELECT idA AS player_id, ' . $keySql . ' AS period_key FROM ratedresults '
        . 'UNION ALL '
        . 'SELECT idB AS player_id, ' . $keySql . ' AS period_key FROM ratedresults'
        . ') AS appearances GROUP BY player_id, period_key'
        . ') AS pm INNER JOIN playertable p ON p.ID = pm.player_id'
        . ') AS best_period WHERE rn = 1 '
        . 'ORDER BY games DESC, period_key ASC' . $limitSql;

    $res = mysqli_query($con, $sql);
    if ($res === false) {
        $error = mysqli_error($con);
        return [];
    }

    $entries = [];
    $rank = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $rank++;
        $entries[] = [
            'rank' => $rank,
            'player_id' => (int) $row['player_id'],
            'player_name' => (string) $row['player_name'],
            'period_key' => (string) $row['period_key'],
            'games' => (int) $row['games'],
        ];
    }

    return $entries;
}

/**
 * @return array<int, array{rank: int, player_id: int, player_name: string, month: string, games: int}>
 */
function k2_peak_month_leaderboard_entries(mysqli $con, int $limit = 0, ?string &$error = null): array
{
    $entries = [];
    foreach (k2_peak_period_leaderboard_entries($con, 'month', $limit, $error) as $row) {
        $entries[] = [
            'rank' => $row['rank'],
            'player_id' => $row['player_id'],
            'player_name' => $row['player_name'],
            'month' => $row['period_key'],
            'games' => $row['games'],
        ];
    }

    return $entries;
}

function k2_format_peak_month(string $ym): string
{
    $d = DateTime::createFromFormat('Y-m-d', $ym . '-01');
    if ($d instanceof DateTime) {
        return $d->format('M Y');
    }

    return $ym;
}

function k2_format_peak_period(string $period, string $periodKey): string
{
    switch ($period) {
        case 'day':
            $ts = strtotime($periodKey);
            return $ts ? date('M j, Y', $ts) : $periodKey;
        case 'month':
            return k2_format_peak_month($periodKey);
        case 'year':
            return $periodKey;
        case 'all-time':
            $ts = strtotime($periodKey);
            return $ts ? date('M j, Y', $ts) : $periodKey;
        default:
            return $periodKey;
    }
}

/**
 * @param array<int, array{rank: int, player_id: int, player_name: string, period_key: string, games: int}> $entries
 */
function k2_peak_period_leaderboard_meta(string $period): array
{
    switch ($period) {
        case 'day':
            return [
                'title' => 'Busiest day',
                'period_label' => 'Peak day',
                'hint' => 'Top players by most rated games in a single calendar day (each player’s personal best only). Ties: earlier day wins.',
            ];
        case 'year':
            return [
                'title' => 'Busiest year',
                'period_label' => 'Peak year',
                'hint' => 'Top players by most rated games in a single calendar year (each player’s personal best only). Ties: earlier year wins.',
            ];
        case 'all-time':
            return [
                'title' => 'Busiest of all time',
                'period_label' => 'Since',
                'hint' => 'Top players by total rated games. Since shows the player’s first rated game date.',
            ];
        default:
            return [
                'title' => 'Busiest month',
                'period_label' => 'Peak month',
                'hint' => 'Top players by most rated games in a single calendar month (each player’s personal best only). Ties: earlier month wins.',
            ];
    }
}
