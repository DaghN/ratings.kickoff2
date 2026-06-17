<?php
/**
 * Hall of Fame — milestones + career league honours (read-time stored truth).
 */
declare(strict_types=1);

require_once __DIR__ . '/player_milestones_helpers.php';
require_once __DIR__ . '/league_honours_leaderboard.php';

/**
 * @return array{value: int|null, id: int, name: string, record_date: ?string}
 */
function records_empty_career_leader(): array
{
    return ['value' => null, 'id' => 0, 'name' => '', 'record_date' => null];
}

/**
 * Latest unlock instant for the record holder (when they last extended their milestone count).
 *
 * @return array{value: int|null, id: int, name: string, record_date: ?string}
 */
function records_fetch_milestone_hof_leader(mysqli $con): array
{
    $empty = records_empty_career_leader();
    if (!k2_milestone_tables_ready($con)) {
        return $empty;
    }

    if (k2_milestone_totals_read_ready($con)) {
        $sql = 'SELECT p.`ID`, p.`Name`, t.`total` AS metric_value, '
            . '(SELECT MAX(pm.`achieved_at`) FROM `player_milestones` pm WHERE pm.`player_id` = p.`ID`) AS record_date '
            . 'FROM `playertable` p '
            . 'INNER JOIN `player_milestone_totals` t ON t.`player_id` = p.`ID` '
            . 'WHERE t.`total` > 0 '
            . 'ORDER BY t.`total` DESC, t.`aspirational` DESC, t.`dedicated` DESC, '
            . 't.`accomplished` DESC, t.`legendary` DESC, p.`ID` ASC '
            . 'LIMIT 1';
    } else {
        $sql = 'SELECT p.`ID`, p.`Name`, COUNT(pm.`milestone_key`) AS metric_value, '
            . 'MAX(pm.`achieved_at`) AS record_date '
            . 'FROM `playertable` p '
            . 'INNER JOIN `player_milestones` pm ON pm.`player_id` = p.`ID` '
            . 'INNER JOIN `milestone_definitions` md ON md.`milestone_key` = pm.`milestone_key` '
            . 'GROUP BY p.`ID`, p.`Name` '
            . 'HAVING metric_value > 0 '
            . 'ORDER BY metric_value DESC, p.`Name` ASC, p.`ID` ASC '
            . 'LIMIT 1';
    }

    $result = mysqli_query($con, $sql);
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return $empty;
    }

    return [
        'value' => (int) $row['metric_value'],
        'id' => (int) $row['ID'],
        'name' => (string) $row['Name'],
        'record_date' => $row['record_date'] !== null ? (string) $row['record_date'] : null,
    ];
}

/**
 * Career overall league medal leader; record_date = latest award of that medal.
 *
 * @return array{value: int|null, id: int, name: string, record_date: ?string}
 */
function records_fetch_league_medal_hof_leader(mysqli $con, string $medal): array
{
    $empty = records_empty_career_leader();
    $allowed = ['gold', 'silver', 'bronze'];
    if (!in_array($medal, $allowed, true)) {
        return $empty;
    }
    if (!k2_status_table_exists($con, 'player_league_totals')
        || !k2_status_table_exists($con, 'player_league_award')) {
        return $empty;
    }

    $column = $medal;
    $medalSql = "'" . mysqli_real_escape_string($con, $medal) . "'";

    $sql = 'SELECT p.`ID`, p.`Name`, t.`' . $column . '` AS metric_value, '
        . '(SELECT MAX(a.`period_end`) FROM `player_league_award` a '
        . 'WHERE a.`player_id` = p.`ID` AND a.`medal` = ' . $medalSql . ') AS record_date '
        . 'FROM `playertable` p '
        . 'INNER JOIN `player_league_totals` t ON t.`player_id` = p.`ID` '
        . 'WHERE t.`' . $column . '` > 0 '
        . 'ORDER BY t.`' . $column . '` DESC, t.`podiums` DESC, p.`Name` ASC, p.`ID` ASC '
        . 'LIMIT 1';

    $result = mysqli_query($con, $sql);
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return $empty;
    }

    return [
        'value' => (int) $row['metric_value'],
        'id' => (int) $row['ID'],
        'name' => (string) $row['Name'],
        'record_date' => $row['record_date'] !== null ? (string) $row['record_date'] : null,
    ];
}

/**
 * @return array{
 *   milestones: array{value: int|null, id: int, name: string, record_date: ?string},
 *   league_gold: array{value: int|null, id: int, name: string, record_date: ?string},
 *   league_silver: array{value: int|null, id: int, name: string, record_date: ?string},
 *   league_bronze: array{value: int|null, id: int, name: string, record_date: ?string}
 * }
 */
function records_load_career_celebration_leaders(mysqli $con): array
{
    return [
        'milestones' => records_fetch_milestone_hof_leader($con),
        'league_gold' => records_fetch_league_medal_hof_leader($con, 'gold'),
        'league_silver' => records_fetch_league_medal_hof_leader($con, 'silver'),
        'league_bronze' => records_fetch_league_medal_hof_leader($con, 'bronze'),
    ];
}
