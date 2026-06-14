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

    require_once __DIR__ . '/milestone_unlock.php';

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

    return k2_milestone_unlock_insert($con, [
        'player_id' => $playerId,
        'milestone_key' => 'entered_arena',
        'achieved_at' => (string) $joinDate,
        'value' => 1,
        'source_kind' => 'lobby',
        'source_game_id' => null,
        'source_league_kind' => null,
        'source_period_type' => null,
        'source_period_start' => null,
    ]);
}
