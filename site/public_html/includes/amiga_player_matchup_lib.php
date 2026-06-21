<?php
/**
 * Amiga head-to-head read path (amiga_player_matchup_summary).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_player_load.php';

/**
 * Most-played opponents for one player (directed summary rows).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_top_opponents(mysqli $con, int $playerId, int $limit = 10): array
{
    if ($playerId < 1) {
        return [];
    }

    $limit = max(1, min(20, $limit));
    $sql = 'SELECT m.opponent_id,
                   COALESCE(p.name, CONCAT(\'#\', m.opponent_id)) AS opponent_name,
                   m.games,
                   m.wins,
                   m.draws,
                   m.losses,
                   m.goals_for,
                   m.goals_against
            FROM amiga_player_matchup_summary m
            LEFT JOIN amiga_players p ON p.id = m.opponent_id
            WHERE m.player_id = ?
            ORDER BY m.games DESC, opponent_name ASC
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

/**
 * @return array{id: int, name: string, country: string}|null
 */
function amiga_player_identity_row(mysqli $con, int $playerId): ?array
{
    if ($playerId < 1) {
        return null;
    }

    $stmt = mysqli_prepare($con, 'SELECT id, name, country FROM amiga_players WHERE id = ? LIMIT 1');
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

    if ($row === false) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'country' => (string) ($row['country'] ?? ''),
    ];
}

/**
 * Directed matchup summary for one player vs one opponent (may be absent when games = 0).
 *
 * @return array<string, mixed>|null null only on query failure
 */
function amiga_player_matchup_directed_row(mysqli $con, int $playerId, int $opponentId): ?array
{
    if ($playerId < 1 || $opponentId < 1) {
        return null;
    }

    $sql = 'SELECT m.player_id,
                   m.opponent_id,
                   COALESCE(p.name, CONCAT(\'#\', m.opponent_id)) AS opponent_name,
                   m.games,
                   m.wins,
                   m.draws,
                   m.losses,
                   m.goals_for,
                   m.goals_against
            FROM amiga_player_matchup_summary m
            LEFT JOIN amiga_players p ON p.id = m.opponent_id
            WHERE m.player_id = ? AND m.opponent_id = ?
            LIMIT 1';
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt === false) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $playerId, $opponentId);
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
 * @return array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}
 */
function amiga_player_matchup_empty_totals(): array
{
    return [
        'games' => 0,
        'wins' => 0,
        'draws' => 0,
        'losses' => 0,
        'goals_for' => 0,
        'goals_against' => 0,
    ];
}

/**
 * @param array<string, mixed>|null $row
 * @return array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}
 */
function amiga_player_matchup_totals_from_row(?array $row): array
{
    if ($row === null) {
        return amiga_player_matchup_empty_totals();
    }

    return [
        'games' => (int) ($row['games'] ?? 0),
        'wins' => (int) ($row['wins'] ?? 0),
        'draws' => (int) ($row['draws'] ?? 0),
        'losses' => (int) ($row['losses'] ?? 0),
        'goals_for' => (int) ($row['goals_for'] ?? 0),
        'goals_against' => (int) ($row['goals_against'] ?? 0),
    ];
}
