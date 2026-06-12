<?php
/**
 * Amiga Hall of Fame — ratio/average leaders from amiga_player_stats.
 *
 * Eligible: NumberGames >= k2_established_min_games() (20). Ties: lowest player_id wins.
 */
declare(strict_types=1);

require_once __DIR__ . '/lb_player_filters.php';
require_once __DIR__ . '/amiga_db.php';

/**
 * @return array{value: float|int|null, id: int, name: string}
 */
function amiga_records_fetch_ratio_leader(
    mysqli $con,
    string $column,
    string $direction,
    string $extraWhere = '',
): array {
    $allowed = [
        'WinRatio',
        'AverageGoalsFor',
        'AverageGoalsAgainst',
        'GoalRatio',
        'DoubleDigitsRatio',
        'CleanSheetsRatio',
    ];
    if (!in_array($column, $allowed, true)) {
        return ['value' => null, 'id' => 0, 'name' => ''];
    }

    $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
    $where = 's.NumberGames >= ' . k2_established_min_games() . ' AND s.`' . $column . '` IS NOT NULL';
    if ($extraWhere !== '') {
        $where .= ' AND (' . $extraWhere . ')';
    }

    $sql = 'SELECT p.id AS player_id, p.name, s.`' . $column . '` AS metric_value '
        . amiga_player_base_from_sql()
        . ' WHERE ' . $where
        . ' ORDER BY s.`' . $column . '` ' . $dir . ', p.id ASC LIMIT 1';

    $result = mysqli_query($con, $sql);
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return ['value' => null, 'id' => 0, 'name' => ''];
    }

    return [
        'value' => $row['metric_value'],
        'id' => (int) $row['player_id'],
        'name' => (string) $row['name'],
    ];
}

/**
 * Load ratio record variables used by amiga/hall-of-fame.php.
 */
function amiga_records_load_ratio_leaders(mysqli $con): void
{
    global $BiggestWinRatio, $BiggestWinRatioID, $BiggestWinRatioName;
    global $BiggestGoalsForAverage, $BiggestGoalsForAverageID, $BiggestGoalsForAverageName;
    global $SmallestGoalsAgainstAverage, $SmallestGoalsAgainstAverageID, $SmallestGoalsAgainstAverageName;
    global $BiggestGoalRatio, $BiggestGoalRatioID, $BiggestGoalRatioName;
    global $BiggestDoubleDigitsRatio, $BiggestDoubleDigitsRatioID, $BiggestDoubleDigitsRatioName;
    global $BiggestCleanSheetsRatio, $BiggestCleanSheetsRatioID, $BiggestCleanSheetsRatioName;

    $win = amiga_records_fetch_ratio_leader($con, 'WinRatio', 'DESC');
    $BiggestWinRatio = $win['value'];
    $BiggestWinRatioID = $win['id'];
    $BiggestWinRatioName = $win['name'];

    $atk = amiga_records_fetch_ratio_leader($con, 'AverageGoalsFor', 'DESC');
    $BiggestGoalsForAverage = $atk['value'];
    $BiggestGoalsForAverageID = $atk['id'];
    $BiggestGoalsForAverageName = $atk['name'];

    $def = amiga_records_fetch_ratio_leader($con, 'AverageGoalsAgainst', 'ASC');
    $SmallestGoalsAgainstAverage = $def['value'];
    $SmallestGoalsAgainstAverageID = $def['id'];
    $SmallestGoalsAgainstAverageName = $def['name'];

    $goal = amiga_records_fetch_ratio_leader($con, 'GoalRatio', 'DESC', 's.GoalRatio > -1');
    $BiggestGoalRatio = $goal['value'];
    $BiggestGoalRatioID = $goal['id'];
    $BiggestGoalRatioName = $goal['name'];

    $dd = amiga_records_fetch_ratio_leader($con, 'DoubleDigitsRatio', 'DESC');
    $BiggestDoubleDigitsRatio = $dd['value'];
    $BiggestDoubleDigitsRatioID = $dd['id'];
    $BiggestDoubleDigitsRatioName = $dd['name'];

    $cs = amiga_records_fetch_ratio_leader($con, 'CleanSheetsRatio', 'DESC');
    $BiggestCleanSheetsRatio = $cs['value'];
    $BiggestCleanSheetsRatioID = $cs['id'];
    $BiggestCleanSheetsRatioName = $cs['name'];
}

/**
 * Top World Cup medal holders from amiga_player_tournament_totals.
 *
 * @return array{gold: array<string, mixed>|null, silver: array<string, mixed>|null, bronze: array<string, mixed>|null}
 */
function amiga_records_wc_totals_leaders(mysqli $con): array
{
    $out = ['gold' => null, 'silver' => null, 'bronze' => null];
    $specs = [
        'gold' => 'wc_gold',
        'silver' => 'wc_silver',
        'bronze' => 'wc_bronze',
    ];

    foreach ($specs as $key => $column) {
        $sql = 'SELECT t.player_id, p.name, t.' . $column . ' AS medal_count '
            . 'FROM amiga_player_tournament_totals t '
            . 'INNER JOIN amiga_players p ON p.id = t.player_id '
            . 'WHERE t.' . $column . ' > 0 '
            . 'ORDER BY t.' . $column . ' DESC, p.name ASC, t.player_id ASC '
            . 'LIMIT 1';
        $result = mysqli_query($con, $sql);
        if ($result && ($row = mysqli_fetch_assoc($result))) {
            $out[$key] = $row;
            mysqli_free_result($result);
        }
    }

    return $out;
}
