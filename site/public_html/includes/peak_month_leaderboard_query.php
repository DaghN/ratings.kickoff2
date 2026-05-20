<?php
/**
 * Top players by personal peak calendar month (most rated games in one month).
 * One row per player (their best month). Ties on game count: earlier month wins.
 *
 * @return array<int, array{rank: int, player_id: int, player_name: string, month: string, games: int}>
 */
function k2_peak_month_leaderboard_entries(mysqli $con, int $limit = 50, ?string &$error = null): array
{
    $error = null;
    $limit = max(1, min(100, $limit));
    $limitSql = (int) $limit;

    $sql = 'SELECT player_id, player_name, ym, games FROM ('
        . 'SELECT pm.player_id, p.Name AS player_name, pm.ym, pm.games, '
        . 'ROW_NUMBER() OVER (PARTITION BY pm.player_id ORDER BY pm.games DESC, pm.ym ASC) AS rn '
        . 'FROM ('
        . 'SELECT player_id, ym, COUNT(*) AS games FROM ('
        . 'SELECT idA AS player_id, DATE_FORMAT(`Date`, \'%Y-%m\') AS ym FROM ratedresults '
        . 'UNION ALL '
        . 'SELECT idB AS player_id, DATE_FORMAT(`Date`, \'%Y-%m\') AS ym FROM ratedresults'
        . ') AS appearances GROUP BY player_id, ym'
        . ') AS pm INNER JOIN playertable p ON p.ID = pm.player_id'
        . ') AS best_month WHERE rn = 1 '
        . 'ORDER BY games DESC, ym ASC LIMIT ' . $limitSql;

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
            'player_name' => $row['player_name'],
            'month' => $row['ym'],
            'games' => (int) $row['games'],
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
