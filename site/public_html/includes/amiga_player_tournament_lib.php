<?php
/**
 * Amiga player tournament participation + career totals read path.
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_tournament_lib.php';
require_once __DIR__ . '/amiga_player_load.php';

/**
 * Tournament participation rows for a player (canonical derived source).
 *
 * Rows are shaped for profile blocks: id, name, position, event_points, games, knockout_ties.
 * Ordered newest event first. Optional $limit caps rows (profile recent list); omit for full history.
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_tournament_participation_rows(mysqli $con, int $playerId, ?int $limit = null): array
{
    $sql = 'SELECT p.tournament_id AS id,
                   p.tournament_name AS name,
                   p.event_date,
                   p.event_chrono,
                   p.is_cup,
                   p.has_league,
                   p.has_cup,
                   p.country,
                   p.overall_position AS position,
                   p.event_points,
                   p.games,
                   p.wins,
                   p.draws,
                   p.losses,
                   p.goals_for,
                   p.goals_against,
                   p.rating_before,
                   p.rating_delta,
                   p.rating_after,
                   p.performance_rating,
                   p.is_winner,
                   p.wc_medal,
                   (SELECT COUNT(DISTINCT sk.scope_key)
                    FROM amiga_tournament_standings sk
                    WHERE sk.tournament_id = p.tournament_id
                      AND sk.scope_type = \'knockout\') AS knockout_ties
            FROM amiga_player_tournament_participation p
            INNER JOIN tournaments t ON t.id = p.tournament_id
            WHERE p.player_id = ?
              AND ' . amiga_tournament_public_visibility_where('t') . '
            ORDER BY COALESCE(p.event_chrono, 999999) DESC,
                     COALESCE(p.event_date, \'1970-01-01\') DESC,
                     p.tournament_id DESC';
    if ($limit !== null) {
        $limit = max(1, min(20, $limit));
        $sql .= ' LIMIT ' . (int) $limit;
    }
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $playerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Recent tournament participation (profile snippet).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_tournament_participation_recent(mysqli $con, int $playerId, int $limit = 5): array
{
    return amiga_player_tournament_participation_rows($con, $playerId, $limit);
}

/**
 * Full tournament participation history (all events, no pagination).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_tournament_participation_all(mysqli $con, int $playerId): array
{
    return amiga_player_tournament_participation_rows($con, $playerId, null);
}

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function amiga_player_tournament_participation_filter_events(array $rows, string $filter): array
{
    if ($filter !== 'world-cup') {
        return $rows;
    }

    return array_values(array_filter(
        $rows,
        static fn (array $row): bool => amiga_tournament_is_world_cup($row)
    ));
}

/**
 * Career tournament rollups for one player (hero / honours — future slices).
 *
 * @return array<string, mixed>|null
 */
function amiga_player_tournament_totals_row(mysqli $con, int $playerId): ?array
{
    $sql = 'SELECT player_id,
                   tournaments_played,
                   tournaments_won,
                   wc_gold,
                   wc_silver,
                   wc_bronze,
                   cup_gold,
                   cup_silver,
                   cup_bronze,
                   podiums,
                   last_event_date,
                   last_tournament_id
            FROM amiga_player_tournament_totals
            WHERE player_id = ?
            LIMIT 1';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $playerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : false;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $row !== false ? $row : null;
}

/**
 * Tournament honours leaderboard rows (amiga_player_tournament_totals only).
 *
 * Default SQL order: wc_gold, wc_silver, wc_bronze, tournaments_won, tournaments_played.
 *
 * @return list<array<string, mixed>>
 */
function amiga_tournament_honours_leaderboard_rows(mysqli $con): array
{
    $sql = 'SELECT t.player_id,
                   p.name AS player_name,
                   p.country,
                   t.tournaments_played,
                   t.tournaments_won,
                   t.wc_gold,
                   t.wc_silver,
                   t.wc_bronze,
                   t.podiums
            FROM amiga_player_tournament_totals t
            INNER JOIN amiga_players p ON p.id = t.player_id
            WHERE t.tournaments_played > 0
            ORDER BY t.wc_gold DESC,
                     t.wc_silver DESC,
                     t.wc_bronze DESC,
                     t.tournaments_won DESC,
                     t.tournaments_played DESC,
                     t.player_id ASC';
    $result = mysqli_query($con, $sql);
    if (!$result) {
        return [];
    }
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_free_result($result);

    return $rows;
}
