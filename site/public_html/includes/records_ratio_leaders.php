<?php
/**
 * Records page — ratio/average leaders from playertable (not generalstatstable).
 * Eligible: NumberGames >= 30. Ties: lowest player ID wins.
 */

const RECORDS_RATIO_MIN_GAMES = 30;

/**
 * @return array{value: float|int|null, id: int, name: string}
 */
function records_fetch_ratio_leader(mysqli $con, string $column, string $direction, string $extraWhere = ''): array
{
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
    $where = 'NumberGames >= ' . (int) RECORDS_RATIO_MIN_GAMES . ' AND `' . $column . '` IS NOT NULL';
    if ($extraWhere !== '') {
        $where .= ' AND (' . $extraWhere . ')';
    }

    $sql = 'SELECT ID, Name, `' . $column . '` AS metric_value FROM playertable WHERE '
        . $where . ' ORDER BY `' . $column . '` ' . $dir . ', ID ASC LIMIT 1';

    $result = mysqli_query($con, $sql);
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return ['value' => null, 'id' => 0, 'name' => ''];
    }

    return [
        'value' => $row['metric_value'],
        'id' => (int) $row['ID'],
        'name' => (string) $row['Name'],
    ];
}

/**
 * Load all ratio record variables used by server2.php.
 */
function records_load_ratio_leaders(mysqli $con): void
{
    global $BiggestWinRatio, $BiggestWinRatioID, $BiggestWinRatioName;
    global $BiggestGoalsForAverage, $BiggestGoalsForAverageID, $BiggestGoalsForAverageName;
    global $SmallestGoalsAgainstAverage, $SmallestGoalsAgainstAverageID, $SmallestGoalsAgainstAverageName;
    global $BiggestGoalRatio, $BiggestGoalRatioID, $BiggestGoalRatioName;
    global $BiggestDoubleDigitsRatio, $BiggestDoubleDigitsRatioID, $BiggestDoubleDigitsRatioName;
    global $BiggestCleanSheetsRatio, $BiggestCleanSheetsRatioID, $BiggestCleanSheetsRatioName;

    $win = records_fetch_ratio_leader($con, 'WinRatio', 'DESC');
    $BiggestWinRatio = $win['value'];
    $BiggestWinRatioID = $win['id'];
    $BiggestWinRatioName = $win['name'];

    $atk = records_fetch_ratio_leader($con, 'AverageGoalsFor', 'DESC');
    $BiggestGoalsForAverage = $atk['value'];
    $BiggestGoalsForAverageID = $atk['id'];
    $BiggestGoalsForAverageName = $atk['name'];

    $def = records_fetch_ratio_leader($con, 'AverageGoalsAgainst', 'ASC');
    $SmallestGoalsAgainstAverage = $def['value'];
    $SmallestGoalsAgainstAverageID = $def['id'];
    $SmallestGoalsAgainstAverageName = $def['name'];

    $goal = records_fetch_ratio_leader($con, 'GoalRatio', 'DESC', 'GoalRatio > -1');
    $BiggestGoalRatio = $goal['value'];
    $BiggestGoalRatioID = $goal['id'];
    $BiggestGoalRatioName = $goal['name'];

    $dd = records_fetch_ratio_leader($con, 'DoubleDigitsRatio', 'DESC');
    $BiggestDoubleDigitsRatio = $dd['value'];
    $BiggestDoubleDigitsRatioID = $dd['id'];
    $BiggestDoubleDigitsRatioName = $dd['name'];

    $cs = records_fetch_ratio_leader($con, 'CleanSheetsRatio', 'DESC');
    $BiggestCleanSheetsRatio = $cs['value'];
    $BiggestCleanSheetsRatioID = $cs['id'];
    $BiggestCleanSheetsRatioName = $cs['name'];
}
