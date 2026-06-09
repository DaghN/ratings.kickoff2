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
