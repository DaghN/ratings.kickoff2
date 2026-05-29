<?php
/**
 * Load and label a historical period league (points or activity) for league.php.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/status_queries.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/league_standings.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_table_render.php';

/**
 * @return array{cup: string, period: string, start: string}|null
 */
function k2_league_period_parse_request(): ?array
{
    $cup = isset($_GET['cup']) ? strtolower(trim((string) $_GET['cup'])) : '';
    $period = isset($_GET['period']) ? strtolower(trim((string) $_GET['period'])) : '';
    $start = isset($_GET['start']) ? trim((string) $_GET['start']) : '';

    if (!in_array($cup, ['points', 'activity'], true)) {
        return null;
    }
    if (!in_array($period, ['day', 'week', 'month', 'year'], true)) {
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
        return null;
    }

    return ['cup' => $cup, 'period' => $period, 'start' => $start];
}

function k2_league_period_href(string $cup, string $period, string $periodStart): string
{
    return 'league.php?' . http_build_query([
        'cup' => $cup,
        'period' => $period,
        'start' => $periodStart,
    ]);
}

function k2_league_period_cup_label(string $cup): string
{
    return $cup === 'activity' ? 'Activity league' : 'Points league';
}

/**
 * Short link label for milestone garden cards.
 */
function k2_league_period_short_label(string $cup, string $period, string $periodStart): string
{
    $key = k2_league_key_from_period_start($period, $periodStart);
    if ($key === null) {
        return k2_league_period_cup_label($cup);
    }
    $bounds = k2_status_bounds_from_period_key($period, $key);
    $grain = k2_status_period_segment_label($period);

    return trim($grain . ' ' . ($cup === 'activity' ? 'activity' : 'points') . ' league, ' . ($bounds['label'] ?? $periodStart));
}

/**
 * @return array{
 *   cup: string,
 *   period: string,
 *   start: string,
 *   title: string,
 *   subtitle: string,
 *   total_games: int,
 *   points_league: ?array,
 *   activity_entries: ?array
 * }|null
 */
function k2_league_period_load(mysqli $con, string $cup, string $period, string $periodStart): ?array
{
    $key = k2_league_key_from_period_start($period, $periodStart);
    if ($key === null) {
        return null;
    }
    $bounds = k2_status_bounds_from_period_key($period, $key);
    if ($bounds === null) {
        return null;
    }

    $title = k2_league_period_cup_label($cup) . ' · ' . $bounds['label'];
    $subtitle = k2_status_period_segment_label($period) . ' · UTC · ' . $bounds['start'] . ' → ' . $bounds['end'];

    if ($cup === 'points') {
        $error = null;
        $league = k2_status_league_for_key($con, $period, $key, null, $error);

        return [
            'cup' => $cup,
            'period' => $period,
            'start' => $periodStart,
            'title' => $title,
            'subtitle' => $subtitle,
            'total_games' => $league !== null ? (int) ($league['total_games'] ?? 0) : 0,
            'points_league' => $league,
            'activity_entries' => null,
        ];
    }

    $error = null;
    $entries = k2_period_activity_leaderboard_entries($con, $period, $key, 0, $error);
    $totalGames = k2_period_activity_total_games($con, $period, $key, $error);

    return [
        'cup' => $cup,
        'period' => $period,
        'start' => $periodStart,
        'title' => $title,
        'subtitle' => $subtitle,
        'total_games' => $totalGames,
        'points_league' => null,
        'activity_entries' => $entries,
    ];
}

/**
 * @param array<string, mixed> $loaded
 */
function k2_league_period_render_table(array $loaded): void
{
    if ($loaded['cup'] === 'points') {
        $league = $loaded['points_league'] ?? null;
        if ($league === null || ($league['rows'] ?? []) === []) {
            echo '<p class="k2-ms-meta-hint">No standings stored for this period.</p>';

            return;
        }
        k2_status_render_league_table($league, true);

        return;
    }

    $entries = $loaded['activity_entries'] ?? [];
    if ($entries === []) {
        echo '<p class="k2-ms-meta-hint">No activity standings for this period.</p>';

        return;
    }
    k2_status_render_activity_competition_table($entries, true);
}
