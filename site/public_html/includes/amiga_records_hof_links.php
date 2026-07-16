<?php
/**
 * Amiga Hall of Fame — deep links on record values.
 *
 * Single-game spectacle rows link to Games highlights boards (with #k2-amiga-games-highlights);
 * career/ratio rows link to leaderboard wings with #k2-lb-table via amiga_lb_table_href(). WC single-game rows use the same boards with
 * scope=world-cup. Column indices match Amiga wing tables (0-based k2_sort).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_url.php';
require_once __DIR__ . '/amiga_games_highlights_helpers.php';
require_once __DIR__ . '/amiga_lb_lib.php';

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
        'most_dd_victims' => ['wing' => 'victims', 'sort' => 7, 'dir' => 'desc'],
        'most_cs_victims' => ['wing' => 'victims', 'sort' => 9, 'dir' => 'desc'],
        'peak_rating' => ['wing' => 'peak-rating', 'sort' => 4, 'dir' => 'desc'],
        'attack_avg' => ['wing' => 'goals', 'sort' => 7, 'dir' => 'desc'],
        'defense_avg' => ['wing' => 'goals', 'sort' => 8, 'dir' => 'asc'],
        'goal_ratio' => ['wing' => 'goals', 'sort' => 10, 'dir' => 'desc'],
        'dd_ratio' => ['wing' => 'double-digits', 'sort' => 6, 'dir' => 'desc'],
        'cs_ratio' => ['wing' => 'double-digits', 'sort' => 7, 'dir' => 'desc'],
        'most_games_in_year' => ['wing' => 'calendar-geo', 'sort' => 3, 'dir' => 'desc'],
        'most_tournaments_in_year' => ['wing' => 'calendar-geo', 'sort' => 5, 'dir' => 'desc'],
        'most_tournaments_played' => ['wing' => 'tournament-honours', 'sort' => 3, 'dir' => 'desc'],
        'most_tournament_wins' => ['wing' => 'tournament-honours', 'sort' => 4, 'dir' => 'desc'],
        'most_perfect_events' => ['wing' => 'tournament-honours', 'sort' => 8, 'dir' => 'desc'],
        'most_wc_played' => ['wing' => 'world-cups', 'sort' => 3, 'dir' => 'desc'],
        'most_countries_played_in' => ['wing' => 'calendar-geo', 'sort' => 7, 'dir' => 'desc'],
        'most_opponent_countries_faced' => ['wing' => 'calendar-geo', 'sort' => 8, 'dir' => 'desc'],
        'most_opponent_countries_beaten' => ['wing' => 'calendar-geo', 'sort' => 9, 'dir' => 'desc'],
        // World Cup HoF rows -> World Cup player leaderboard sub-wings (WCH-6).
        'wc_gold' => ['wing' => 'world-cups', 'sort' => 4, 'dir' => 'desc'],
        'wc_games' => ['wing' => 'world-cups-results', 'sort' => 4, 'dir' => 'desc'],
        'wc_wins' => ['wing' => 'world-cups-results', 'sort' => 5, 'dir' => 'desc'],
        'wc_points' => ['wing' => 'world-cups-results', 'sort' => 8, 'dir' => 'desc'],
        'wc_pts_per_game' => ['wing' => 'world-cups-results', 'sort' => 9, 'dir' => 'desc'],
        'wc_win_rate' => ['wing' => 'world-cups-results', 'sort' => 10, 'dir' => 'desc'],
        'wc_goals_for' => ['wing' => 'world-cups-goals', 'sort' => 4, 'dir' => 'desc'],
        'wc_gf_per_game' => ['wing' => 'world-cups-goals', 'sort' => 7, 'dir' => 'desc'],
        'wc_ga_per_game' => ['wing' => 'world-cups-goals', 'sort' => 8, 'dir' => 'asc'],
        'wc_gd_per_game' => ['wing' => 'world-cups-goals', 'sort' => 9, 'dir' => 'desc'],
        'wc_goal_ratio' => ['wing' => 'world-cups-goals', 'sort' => 10, 'dir' => 'desc'],
        'wc_double_digits' => ['wing' => 'world-cups-dds', 'sort' => 4, 'dir' => 'desc'],
        'wc_clean_sheets' => ['wing' => 'world-cups-dds', 'sort' => 5, 'dir' => 'desc'],
        'wc_dd_ratio' => ['wing' => 'world-cups-dds', 'sort' => 6, 'dir' => 'desc'],
        'wc_cs_ratio' => ['wing' => 'world-cups-dds', 'sort' => 7, 'dir' => 'desc'],
        'wc_opponents' => ['wing' => 'world-cups-opponents', 'sort' => 4, 'dir' => 'desc'],
        'wc_victims' => ['wing' => 'world-cups-opponents', 'sort' => 5, 'dir' => 'desc'],
        'wc_dd_victims' => ['wing' => 'world-cups-opponents', 'sort' => 7, 'dir' => 'desc'],
        'wc_cs_victims' => ['wing' => 'world-cups-opponents', 'sort' => 9, 'dir' => 'desc'],
    ];

    return $map[$metric] ?? null;
}

/** Single-game spectacle metrics -> Games highlights board id (mirror online records_hof_links.php). */
function amiga_records_hof_highlights_board(string $metric): ?string
{
    static $map = [
        'most_goals_one_game' => 'top_score',
        'biggest_win_margin' => 'biggest_wins',
        'biggest_draw' => 'biggest_draws',
        'biggest_sum_goals' => 'most_goals',
        'wc_most_goals_one_game' => 'top_score',
        'wc_biggest_win_margin' => 'biggest_wins',
        'wc_biggest_draw' => 'biggest_draws',
        'wc_biggest_sum_goals' => 'most_goals',
    ];

    return $map[$metric] ?? null;
}

/** @return 'all'|'world-cup' */
function amiga_records_hof_highlights_scope(string $metric): string
{
    return str_starts_with($metric, 'wc_') ? 'world-cup' : 'all';
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
        'world-cups' => '/amiga/world-cups/players/honours.php',
        'world-cups-results' => '/amiga/world-cups/players/results.php',
        'world-cups-goals' => '/amiga/world-cups/players/goals.php',
        'world-cups-dds' => '/amiga/world-cups/players/dds.php',
        'world-cups-opponents' => '/amiga/world-cups/players/opponents.php',
    ];

    return $paths[$wing] ?? '/amiga/leaderboards/rating.php';
}

function amiga_records_hof_lb_href(string $metric): ?string
{
    $highlightsBoard = amiga_records_hof_highlights_board($metric);
    if ($highlightsBoard !== null) {
        return amiga_games_highlights_context_href(
            $highlightsBoard,
            true,
            amiga_records_hof_highlights_scope($metric)
        );
    }

    $target = amiga_records_hof_lb_target($metric);
    if ($target === null) {
        return null;
    }

    return amiga_lb_table_href(
        amiga_records_hof_lb_wing_path($target['wing']),
        [
            'k2_sort' => (string) $target['sort'],
            'k2_dir' => $target['dir'],
        ]
    );
}
