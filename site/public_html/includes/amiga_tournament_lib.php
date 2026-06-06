<?php
/**
 * Amiga tournament standings read path (derived amiga_tournament_standings).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';

/**
 * @return array<string, mixed>|null
 */
function amiga_tournament_load(mysqli $con, int $tournamentId): ?array
{
    $stmt = mysqli_prepare(
        $con,
        'SELECT id, name, chrono, event_date, is_cup, country, equal_teams, player_count
         FROM tournaments WHERE id = ?'
    );
    if ($stmt === false) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/**
 * @return list<string>
 */
function amiga_tournament_list_scopes(mysqli $con, int $tournamentId, string $scopeType = 'overall'): array
{
    $stmt = mysqli_prepare(
        $con,
        'SELECT DISTINCT scope_key FROM amiga_tournament_standings
         WHERE tournament_id = ? AND scope_type = ?
         ORDER BY scope_key'
    );
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'is', $tournamentId, $scopeType);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $keys = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $keys[] = (string) $row['scope_key'];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    return $keys;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_tournament_standings_rows(
    mysqli $con,
    int $tournamentId,
    string $scopeType = 'overall',
    string $scopeKey = ''
): array {
    $sql = 'SELECT s.position, s.games, s.wins, s.draws, s.losses,
                   s.goals_for, s.goals_against, s.points,
                   p.id AS player_id, p.name AS player_name, p.country
            FROM amiga_tournament_standings s
            INNER JOIN amiga_players p ON p.id = s.player_id
            WHERE s.tournament_id = ? AND s.scope_type = ? AND s.scope_key = ?
            ORDER BY s.position ASC, s.points DESC, (s.goals_for - s.goals_against) DESC';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'iss', $tournamentId, $scopeType, $scopeKey);
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
 * Recent tournaments for a player (overall scope, by event chrono desc).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_recent_tournaments(mysqli $con, int $playerId, int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    $sql = 'SELECT t.id, t.name, t.event_date, s.position, s.points, s.games
            FROM amiga_tournament_standings s
            INNER JOIN tournaments t ON t.id = s.tournament_id
            WHERE s.player_id = ? AND s.scope_type = \'overall\' AND s.scope_key = \'\'
            ORDER BY COALESCE(t.chrono, 999999) DESC, COALESCE(t.event_date, \'1970-01-01\') DESC
            LIMIT ' . (int) $limit;
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

function amiga_tournament_link(int $id, string $name): string
{
    $href = '/amiga/tournament.php?id=' . $id;
    return '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">'
        . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
}
