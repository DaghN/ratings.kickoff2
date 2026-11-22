<?php
/**
 * Helpers for prod-shaped simul (Mode C).
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_bootstrap.php';

/**
 * On a live ladder the last game's day may still be running: never simulate past now, or that day is closed early.
 */
function k2_ops_sim_not_after_now(DateTimeImmutable $stopAt): DateTimeImmutable
{
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

    return $stopAt > $now ? $now : $stopAt;
}

/**
 * stop-at slightly after the last game day so FinalizeUtcDay runs for that UTC day.
 */
function k2_ops_stop_at_after_game_id(mysqli $con, int $gameId): DateTimeImmutable
{
    $stmt = $con->prepare('SELECT `Date` FROM ratedresults WHERE id = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare stop-at game: ' . $con->error);
    }
    $stmt->bind_param('i', $gameId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stop-at game: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res !== false ? $res->fetch_assoc() : null;
    if ($res !== false) {
        $res->free();
    }
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException("ratedresults id={$gameId} not found");
    }

    $gameAt = new DateTimeImmutable((string) $row['Date'], new DateTimeZone('UTC'));

    return k2_ops_sim_not_after_now($gameAt->modify('+1 day')->setTime(0, 10, 0));
}

/**
 * Default full-history stop-at: latest rated game + buffer for day tick.
 */
function k2_ops_default_sim_stop_at(mysqli $con): DateTimeImmutable
{
    $res = $con->query('SELECT MAX(`Date`) AS max_date FROM ratedresults');
    if ($res === false) {
        throw new RuntimeException('max Date: ' . $con->error);
    }
    $row = $res->fetch_assoc();
    $res->free();
    if ($row === null || $row['max_date'] === null) {
        throw new RuntimeException('ratedresults is empty — nothing to simul');
    }

    $maxAt = new DateTimeImmutable((string) $row['max_date'], new DateTimeZone('UTC'));

    return k2_ops_sim_not_after_now($maxAt->modify('+1 day')->setTime(0, 10, 0));
}
