<?php
/**
 * Profile feast — "The story so far" extras (play streaks, best year, distinct days).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_play_streaks.php';

/**
 * @return array{
 *     play_streaks: array{
 *         day: array{current: int, best: int, best_date: string},
 *         week: array{current: int, best: int, best_date: string}
 *     },
 *     best_year: ?array{year: int, games: int, wins: int},
 *     distinct_days: int
 * }
 */
function player_feast_load_story_extras(mysqli $con, int $id): array
{
    return [
        'play_streaks' => player_feast_load_story_play_streaks($con, $id),
        'best_year' => player_feast_load_story_best_year($con, $id),
        'distinct_days' => player_feast_load_story_distinct_days($con, $id),
    ];
}

function player_feast_story_table_ok(mysqli $con, string $table): bool
{
    if (function_exists('k2_status_table_exists')) {
        return k2_status_table_exists($con, $table);
    }
    $safe = mysqli_real_escape_string($con, $table);
    $res = @mysqli_query($con, "SHOW TABLES LIKE '$safe'");

    return $res !== false && mysqli_num_rows($res) > 0;
}

/**
 * @return array{
 *     day: array{current: int, best: int, best_date: string},
 *     week: array{current: int, best: int, best_date: string}
 * }
 */
function player_feast_load_story_play_streaks(mysqli $con, int $id): array
{
    $out = [
        'day' => ['current' => 0, 'best' => 0, 'best_date' => ''],
        'week' => ['current' => 0, 'best' => 0, 'best_date' => ''],
    ];
    if (!player_feast_story_table_ok($con, 'player_play_streaks')) {
        return $out;
    }
    try {
        $utcToday = k2_play_streak_utc_today($con);
    } catch (Throwable $e) {
        $utcToday = gmdate('Y-m-d');
    }
    foreach (['day', 'week'] as $type) {
        try {
            $row = k2_play_streak_load_row($con, $id, $type);
        } catch (Throwable $e) {
            $row = null;
        }
        if ($row === null) {
            continue;
        }
        $bestDate = (string) ($row['best_achieved_at'] ?? '');
        $out[$type] = [
            'current' => k2_play_streak_effective_current($row, $type, $utcToday),
            'best' => (int) ($row['best_streak'] ?? 0),
            'best_date' => $bestDate !== '' ? date('M Y', strtotime($bestDate) ?: time()) : '',
        ];
    }

    return $out;
}

/** @return array{year: int, games: int, wins: int}|null */
function player_feast_load_story_best_year(mysqli $con, int $id): ?array
{
    if (!player_feast_story_table_ok($con, 'player_period_league')) {
        return null;
    }
    $esc = (string) (int) $id;
    $res = k2_player_feast_query(
        $con,
        'story_best_year',
        "SELECT period_start, played, wins FROM player_period_league "
        . "WHERE player_id = '$esc' AND period_type = 'year' AND wins > 0 "
        . "ORDER BY wins DESC, period_start ASC LIMIT 1"
    );
    if ($res === false || !($row = mysqli_fetch_assoc($res))) {
        return null;
    }

    return [
        'year' => (int) substr((string) $row['period_start'], 0, 4),
        'games' => (int) $row['played'],
        'wins' => (int) $row['wins'],
    ];
}

function player_feast_load_story_distinct_days(mysqli $con, int $id): int
{
    if (!player_feast_story_table_ok($con, 'player_period_games')) {
        return 0;
    }
    $esc = (string) (int) $id;
    $res = k2_player_feast_query(
        $con,
        'story_distinct_days',
        "SELECT COUNT(*) AS c FROM player_period_games "
        . "WHERE player_id = '$esc' AND period_type = 'day'"
    );
    if ($res === false || !($row = mysqli_fetch_assoc($res))) {
        return 0;
    }

    return (int) $row['c'];
}

/** Day vs week play-streak line — 50/50 per page load (X04 rotate). */
function player_feast_story_play_streak_axis(): string
{
    return random_int(0, 1) === 0 ? 'day' : 'week';
}
