<?php
/**
 * Peak rating leaderboard — playertable career peak + establishing game date (PeakRatingGameID).
 */
declare(strict_types=1);

require_once __DIR__ . '/lb_player_filters.php';
require_once __DIR__ . '/peak_month_leaderboard_query.php';
require_once __DIR__ . '/k2_safety.php';

/**
 * @return mysqli_result|false
 */
function k2_lb_peak_rating_query(mysqli $con)
{
    $where = k2_lb_player_where_sql_for_alias('p');
    $sql = 'SELECT p.`id`, p.`Name`, p.`Rating`, p.`NumberGames`, p.`PeakRating`, p.`LowestRating`, '
        . 'p.`AverageOpponentRating`, p.`HighestRatedVictim`, p.`LowestRatedCulprit`, '
        . 'rr.`Date` AS `peak_rating_date` '
        . 'FROM `playertable` p '
        . 'LEFT JOIN `ratedresults` rr ON rr.`id` = p.`PeakRatingGameID` '
        . 'WHERE ' . $where . ' ORDER BY p.`PeakRating` DESC, p.`Rating` DESC';

    return $con->query($sql);
}

function k2_lb_peak_rating_format_event_date(?string $date): string
{
    if ($date === null || $date === '') {
        return k2_fmt_dash();
    }

    $day = substr($date, 0, 10);
    if ($day === '') {
        return k2_fmt_dash();
    }

    return k2_format_peak_period('day', $day);
}

function k2_lb_peak_rating_date_sort_value(?string $date): string
{
    if ($date === null || $date === '') {
        return '0';
    }

    $ts = strtotime($date);

    return (string) ($ts !== false ? $ts : 0);
}
