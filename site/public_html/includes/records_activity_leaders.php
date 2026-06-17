<?php
/**
 * Hall of Fame — activity participation leaders (stored truth on player_activity_participation).
 * Ties on equal counts: earlier active_*_reached_at (SCH-025), then lowest ID.
 */

declare(strict_types=1);

require_once __DIR__ . '/lb_activity_lib.php';

/**
 * @return array{value: int|null, id: int, name: string, record_date: ?string}
 */
function records_fetch_participation_count_leader(mysqli $con, string $column): array
{
    $empty = ['value' => null, 'id' => 0, 'name' => '', 'record_date' => null];
    $reachedColumn = k2_lb_activity_participation_reached_at_column_for_active($column);
    if ($reachedColumn === null) {
        return $empty;
    }

    if (!k2_lb_activity_participation_reached_columns_ready($con)) {
        return $empty;
    }

    $sql = 'SELECT p.`ID`, p.`Name`, a.`' . $column . '` AS metric_value, a.`' . $reachedColumn . '` AS record_date '
        . 'FROM `player_activity_participation` a '
        . 'INNER JOIN `playertable` p ON p.`ID` = a.`player_id` '
        . 'WHERE a.`' . $column . '` > 0 '
        . 'ORDER BY a.`' . $column . '` DESC, a.`' . $reachedColumn . '` ASC, p.`ID` ASC '
        . 'LIMIT 1';

    $result = mysqli_query($con, $sql);
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return $empty;
    }

    $reached = $row['record_date'] ?? null;

    return [
        'value' => (int) $row['metric_value'],
        'id' => (int) $row['ID'],
        'name' => (string) $row['Name'],
        'record_date' => $reached !== null && $reached !== '' ? (string) $reached : null,
    ];
}

/**
 * @return array{value: int|null, id: int, name: string, first_rated_day: ?string, last_rated_day: ?string}
 */
function records_fetch_participation_longevity_leader(mysqli $con): array
{
    $empty = ['value' => null, 'id' => 0, 'name' => '', 'first_rated_day' => null, 'last_rated_day' => null];

    $sql = 'SELECT p.`ID`, p.`Name`, a.`first_rated_day`, a.`last_rated_day`, '
        . 'DATEDIFF(a.`last_rated_day`, a.`first_rated_day`) + 1 AS longevity_days '
        . 'FROM `player_activity_participation` a '
        . 'INNER JOIN `playertable` p ON p.`ID` = a.`player_id` '
        . 'WHERE a.`first_rated_day` IS NOT NULL AND a.`last_rated_day` IS NOT NULL '
        . 'ORDER BY longevity_days DESC, p.`ID` ASC '
        . 'LIMIT 1';

    $result = mysqli_query($con, $sql);
    if (!$result || !($row = mysqli_fetch_assoc($result))) {
        return $empty;
    }

    $days = (int) $row['longevity_days'];
    if ($days <= 0) {
        return $empty;
    }

    return [
        'value' => $days,
        'id' => (int) $row['ID'],
        'name' => (string) $row['Name'],
        'first_rated_day' => $row['first_rated_day'] !== null ? (string) $row['first_rated_day'] : null,
        'last_rated_day' => $row['last_rated_day'] !== null ? (string) $row['last_rated_day'] : null,
    ];
}

/**
 * Load participation HoF leaders when SCH-022 table exists.
 *
 * @return array{
 *   ready: bool,
 *   days: array{value: int|null, id: int, name: string, record_date: ?string},
 *   weeks: array{value: int|null, id: int, name: string, record_date: ?string},
 *   months: array{value: int|null, id: int, name: string, record_date: ?string},
 *   years: array{value: int|null, id: int, name: string, record_date: ?string},
 *   longevity: array{value: int|null, id: int, name: string, first_rated_day: ?string, last_rated_day: ?string}
 * }
 */
function records_load_participation_leaders(mysqli $con): array
{
    $emptyLeader = ['value' => null, 'id' => 0, 'name' => '', 'record_date' => null];
    $emptyLongevity = ['value' => null, 'id' => 0, 'name' => '', 'first_rated_day' => null, 'last_rated_day' => null];

    if (!k2_lb_activity_participation_ready($con)) {
        return [
            'ready' => false,
            'days' => $emptyLeader,
            'weeks' => $emptyLeader,
            'months' => $emptyLeader,
            'years' => $emptyLeader,
            'longevity' => $emptyLongevity,
        ];
    }

    return [
        'ready' => true,
        'days' => records_fetch_participation_count_leader($con, 'active_days'),
        'weeks' => records_fetch_participation_count_leader($con, 'active_weeks'),
        'months' => records_fetch_participation_count_leader($con, 'active_months'),
        'years' => records_fetch_participation_count_leader($con, 'active_years'),
        'longevity' => records_fetch_participation_longevity_leader($con),
    ];
}

function records_participation_longevity_span_html(?string $firstDay, ?string $lastDay): string
{
    if ($firstDay === null || $firstDay === '' || $lastDay === null || $lastDay === '') {
        return '-';
    }

    $firstTs = strtotime($firstDay);
    $lastTs = strtotime($lastDay);
    if ($firstTs === false || $lastTs === false) {
        return '-';
    }

    return date('M j, Y', $firstTs) . ' – ' . date('M j, Y', $lastTs);
}
