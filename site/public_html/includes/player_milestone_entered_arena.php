<?php
/**
 * Lobby milestone `entered_arena` — unlock when a player registers (enters the arena).
 *
 * Contract: source_kind = lobby, achieved_at = playertable.JoinDate, no source_game_id.
 */
declare(strict_types=1);

/**
 * Insert `entered_arena` for one player if missing. Idempotent.
 *
 * @return bool true when a new row was inserted
 */
function k2_milestone_maybe_unlock_entered_arena(mysqli $con, int $playerId): bool
{
    if ($playerId < 1) {
        return false;
    }

    require_once __DIR__ . '/player_milestones_helpers.php';
    if (!k2_milestone_tables_ready($con)) {
        return false;
    }

    $check = $con->prepare(
        'SELECT 1 FROM `player_milestones` '
        . 'WHERE `player_id` = ? AND `milestone_key` = \'entered_arena\' LIMIT 1'
    );
    if ($check === false) {
        throw new RuntimeException('entered_arena exists check: ' . $con->error);
    }
    $check->bind_param('i', $playerId);
    $check->execute();
    $exists = $check->get_result()->fetch_row() !== null;
    $check->close();
    if ($exists) {
        return false;
    }

    $load = $con->prepare('SELECT `JoinDate` FROM `playertable` WHERE `ID` = ? LIMIT 1');
    if ($load === false) {
        throw new RuntimeException('entered_arena load JoinDate: ' . $con->error);
    }
    $load->bind_param('i', $playerId);
    $load->execute();
    $res = $load->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $load->close();

    $joinDate = $row['JoinDate'] ?? null;
    if ($joinDate === null || (string) $joinDate === '' || (string) $joinDate === '0000-00-00 00:00:00') {
        return false;
    }
    $achievedAt = (string) $joinDate;

    $stmt = $con->prepare(
        'INSERT IGNORE INTO `player_milestones` '
        . '(`player_id`, `milestone_key`, `achieved_at`, `value`, '
        . '`source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`) '
        . 'VALUES (?, \'entered_arena\', ?, 1, \'lobby\', NULL, NULL, NULL, NULL)'
    );
    if ($stmt === false) {
        throw new RuntimeException('entered_arena insert: ' . $con->error);
    }
    $stmt->bind_param('is', $playerId, $achievedAt);
    $stmt->execute();
    $inserted = $stmt->affected_rows > 0;
    $stmt->close();

    return $inserted;
}
