<?php
/**
 * Register-event derived updates (not rated games).
 *
 * Planned dispatcher: CMD=ProcessPlayerRegistered player_id=N
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/player_milestone_entered_arena.php';

/**
 * After a player registers in the app: lobby milestones from playertable.JoinDate.
 *
 * @return array{player_id: int, entered_arena_inserted: bool, committed: bool}
 */
function k2_ops_process_player_registered(mysqli $con, int $playerId, bool $dryRun = false): array
{
    if ($playerId < 1) {
        throw new InvalidArgumentException('player_id must be positive');
    }

    if ($dryRun) {
        return [
            'player_id' => $playerId,
            'entered_arena_inserted' => false,
            'committed' => false,
        ];
    }

    $con->begin_transaction();
    try {
        $inserted = k2_milestone_maybe_unlock_entered_arena($con, $playerId);
        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }

    return [
        'player_id' => $playerId,
        'entered_arena_inserted' => $inserted,
        'committed' => true,
    ];
}
