<?php
/**
 * Amiga present player career reads — amiga_player_current (website + ops bootstrap).
 */
declare(strict_types=1);

/** Present-truth career + honours table (slice 8). */
const AMIGA_PLAYER_CAREER_TABLE = 'amiga_player_current';

/**
 * Career table for website/ops reads.
 */
function amiga_player_career_table(mysqli $con): string
{
    return AMIGA_PLAYER_CAREER_TABLE;
}

/**
 * FROM + INNER JOIN for player + career row (alias ``s``).
 */
function amiga_player_base_from_sql(mysqli $con, string $alias = 's'): string
{
    $table = amiga_player_career_table($con);

    return "FROM amiga_players p\nINNER JOIN `{$table}` {$alias} ON {$alias}.player_id = p.id";
}

/**
 * INNER JOIN fragment for an arbitrary player-id expression.
 */
function amiga_player_career_join_sql(mysqli $con, string $playerIdExpr, string $alias = 's'): string
{
    $table = amiga_player_career_table($con);

    return "INNER JOIN `{$table}` {$alias} ON {$alias}.player_id = {$playerIdExpr}";
}

/**
 * Ladder rank by present career rating (1 = highest).
 */
function amiga_player_career_rating_rank_sql(mysqli $con): string
{
    $table = amiga_player_career_table($con);

    return 'SELECT COUNT(*) + 1 AS r FROM `' . $table . '` WHERE NumberGames > 0 AND Rating > '
        . '(SELECT Rating FROM `' . $table . '` WHERE player_id = ? LIMIT 1)';
}

/**
 * @return ?array<string, mixed>
 */
function amiga_player_current_row(mysqli $con, int $playerId): ?array
{
    if ($playerId < 1) {
        return null;
    }

    $table = amiga_player_career_table($con);
    $stmt = $con->prepare("SELECT * FROM `{$table}` WHERE player_id = ? LIMIT 1");
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_player_current_row: ' . $con->error);
    }
    $stmt->bind_param('i', $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_player_current_row: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();

    return $row !== false ? $row : null;
}
