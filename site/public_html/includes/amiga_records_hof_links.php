<?php
/**
 * Amiga Hall of Fame — leaderboard deep links on record values.
 *
 * Column indices match Amiga wing tables (0-based k2_sort). Rating wing includes Country
 * after Elo — indices differ from online ranked7. No streak / activity-peaks wings on Amiga.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_url.php';

/**
 * @return array{wing: string, sort: int, dir: 'asc'|'desc'}|null
 */
function amiga_records_hof_lb_target(string $metric): ?array
{
    static $map = [
        'most_games' => ['wing' => 'rating', 'sort' => 4, 'dir' => 'desc'],
        'most_wins' => ['wing' => 'rating', 'sort' => 5, 'dir' => 'desc'],
        'win_ratio' => ['wing' => 'rating', 'sort' => 8, 'dir' => 'desc'],
        'most_goals' => ['wing' => 'goals', 'sort' => 4, 'dir' => 'desc'],
        'most_dd' => ['wing' => 'double-digits', 'sort' => 4, 'dir' => 'desc'],
        'most_cs' => ['wing' => 'double-digits', 'sort' => 5, 'dir' => 'desc'],
        'most_opponents' => ['wing' => 'victims', 'sort' => 4, 'dir' => 'desc'],
        'most_victims' => ['wing' => 'victims', 'sort' => 5, 'dir' => 'desc'],
        'most_dd_victims' => ['wing' => 'victims', 'sort' => 6, 'dir' => 'desc'],
        'most_cs_victims' => ['wing' => 'victims', 'sort' => 7, 'dir' => 'desc'],
        'most_goals_one_game' => ['wing' => 'goals', 'sort' => 9, 'dir' => 'desc'],
        'biggest_win_margin' => ['wing' => 'goals', 'sort' => 11, 'dir' => 'desc'],
        'biggest_draw' => ['wing' => 'goals', 'sort' => 14, 'dir' => 'desc'],
        'biggest_sum_goals' => ['wing' => 'goals', 'sort' => 13, 'dir' => 'desc'],
        'peak_rating' => ['wing' => 'peak-rating', 'sort' => 4, 'dir' => 'desc'],
        'attack_avg' => ['wing' => 'goals', 'sort' => 6, 'dir' => 'desc'],
        'defense_avg' => ['wing' => 'goals', 'sort' => 7, 'dir' => 'asc'],
        'goal_ratio' => ['wing' => 'goals', 'sort' => 8, 'dir' => 'desc'],
        'dd_ratio' => ['wing' => 'double-digits', 'sort' => 6, 'dir' => 'desc'],
        'cs_ratio' => ['wing' => 'double-digits', 'sort' => 7, 'dir' => 'desc'],
        'most_games_in_year' => ['wing' => 'calendar-geo', 'sort' => 4, 'dir' => 'desc'],
        'most_tournaments_in_year' => ['wing' => 'calendar-geo', 'sort' => 6, 'dir' => 'desc'],
        'most_tournaments_played' => ['wing' => 'tournament-honours', 'sort' => 4, 'dir' => 'desc'],
        'most_tournament_wins' => ['wing' => 'tournament-honours', 'sort' => 5, 'dir' => 'desc'],
        'most_wc_played' => ['wing' => 'world-cups', 'sort' => 4, 'dir' => 'desc'],
        'most_countries_played_in' => ['wing' => 'calendar-geo', 'sort' => 8, 'dir' => 'desc'],
        'most_opponent_countries_faced' => ['wing' => 'calendar-geo', 'sort' => 9, 'dir' => 'desc'],
        'most_opponent_countries_beaten' => ['wing' => 'calendar-geo', 'sort' => 10, 'dir' => 'desc'],
    ];

    return $map[$metric] ?? null;
}

function amiga_records_hof_lb_wing_path(string $wing): string
{
    static $paths = [
        'rating' => '/amiga/leaderboards/rating.php',
        'goals' => '/amiga/leaderboards/goals.php',
        'double-digits' => '/amiga/leaderboards/double-digits.php',
        'victims' => '/amiga/leaderboards/victims.php',
        'peak-rating' => '/amiga/leaderboards/peak-rating.php',
        'calendar-geo' => '/amiga/leaderboards/calendar-geo.php',
        'tournament-honours' => '/amiga/leaderboards/tournament-honours.php',
        'world-cups' => '/amiga/leaderboards/world-cups/honours.php',
    ];

    return $paths[$wing] ?? '/amiga/leaderboards/rating.php';
}

function amiga_records_hof_lb_href(string $metric): ?string
{
    $target = amiga_records_hof_lb_target($metric);
    if ($target === null) {
        return null;
    }

    return amiga_url_with_context(
        amiga_records_hof_lb_wing_path($target['wing']),
        [
            'k2_sort' => (string) $target['sort'],
            'k2_dir' => $target['dir'],
        ]
    );
}
