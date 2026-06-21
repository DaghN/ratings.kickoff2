<?php
/**
 * Website reads for persisted career Elo rank (present + time travel).
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';

/** Latest persisted rank for one player on or before cutoff. */
function amiga_player_elo_rank_at_cutoff(mysqli $con, int $playerId, AmigaSnapshotContext $ctx): int
{
    if ($playerId < 1 || !$ctx->isActive()) {
        return 0;
    }

    $cutoff = $ctx->cutoff();
    if ($cutoff === null) {
        return 0;
    }

    $sql = 'SELECT r.elo_rank FROM (
        SELECT er.elo_rank,
            ROW_NUMBER() OVER (
                ORDER BY er.event_date DESC, er.event_chrono DESC, er.tournament_id DESC
            ) AS rn
        FROM amiga_player_elo_rank_at_event er
        WHERE er.player_id = ?
          AND (er.event_date, er.event_chrono, er.tournament_id) <= (?, ?, ?)
    ) r WHERE r.rn = 1 LIMIT 1';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return 0;
    }

    $eventDate = $cutoff['event_date'];
    $chrono = $cutoff['chrono'];
    $tournamentId = $cutoff['tournament_id'];
    $stmt->bind_param('isdi', $playerId, $eventDate, $chrono, $tournamentId);
    if (!$stmt->execute()) {
        $stmt->close();

        return 0;
    }

    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $row !== false ? (int) ($row['elo_rank'] ?? 0) : 0;
}
