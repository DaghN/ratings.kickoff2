<?php
/**
 * Top players by personal peak calendar day / month / year (most rated games in one period).
 * One row per player (their best period). Ties on game count: earlier period wins.
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
 * @return array<int, array{rank: int, player_id: int, player_name: string, period_key: string, games: int}>
 */
function k2_peak_period_leaderboard_entries(mysqli $con, string $period, int $limit = 50, ?string &$error = null): array
{
    $error = null;
    $keySql = k2_peak_period_key_sql($period);
    if ($keySql === null) {
        $error = 'invalid_period';

        return [];
    }

    $limit = max(1, min(100, $limit));
    $limitSql = (int) $limit;

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
        . 'ORDER BY games DESC, period_key ASC LIMIT ' . $limitSql;

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
function k2_peak_month_leaderboard_entries(mysqli $con, int $limit = 50, ?string &$error = null): array
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
        default:
            return [
                'title' => 'Busiest month',
                'period_label' => 'Peak month',
                'hint' => 'Top players by most rated games in a single calendar month (each player’s personal best only). Ties: earlier month wins.',
            ];
    }
}
