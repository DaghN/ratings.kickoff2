<?php

declare(strict_types=1);

/**
 * Queue of rated games that still need post-game processing, and the lock that keeps it in order.
 *
 * Post-game processing is only right in game order: ratings, streaks and peaks build on the games before.
 * Frontier = highest processed ratedresults.id (NewRatingA set).
 *   Pending = valid unprocessed games after the frontier. Safe to process now, in Date, id order.
 *   Hole    = valid unprocessed game before the frontier. Too late to process: later games already
 *             built on a ladder without it. Only a full recalculation (zero-derived + ops sim) fixes it.
 *
 * @see modules/process_pending_games.php
 * @see docs/coordination/online-unprocessed-games-2026-11.md
 */

require_once __DIR__ . '/ops_bootstrap.php';

/** More pending games than this means the ladder is being rebuilt (zero-derived), not that calls were missed. */
const K2_OPS_GAME_QUEUE_MAX_BACKLOG = 1000;

/** Same rows as k2_ops_rated_game_skip_reason() accepts, minus the already-processed check. */
const K2_OPS_GAME_QUEUE_VALID_SQL =
    'idA > 0 AND idB > 0 AND idA <> idB AND GoalsA IS NOT NULL AND GoalsB IS NOT NULL';

function k2_ops_game_queue_lock_name(mysqli $con): string
{
    $res = $con->query('SELECT DATABASE() AS db');
    if ($res === false) {
        throw new RuntimeException('game queue lock name: ' . $con->error);
    }
    $db = (string) ($res->fetch_assoc()['db'] ?? '');
    $res->free();

    // GET_LOCK names are server-wide and at most 64 characters.
    return 'k2_ops_games_' . substr(sha1($db), 0, 24);
}

/**
 * One writer at a time per database. Held by the connection: freed on close, also if PHP is killed.
 */
function k2_ops_game_queue_lock(mysqli $con, int $timeoutSeconds): bool
{
    $name = $con->real_escape_string(k2_ops_game_queue_lock_name($con));
    $res = $con->query("SELECT GET_LOCK('{$name}', " . max(0, $timeoutSeconds) . ') AS got');
    if ($res === false) {
        throw new RuntimeException('game queue lock: ' . $con->error);
    }
    $got = (string) ($res->fetch_assoc()['got'] ?? '') === '1';
    $res->free();

    return $got;
}

function k2_ops_game_queue_unlock(mysqli $con): void
{
    $name = $con->real_escape_string(k2_ops_game_queue_lock_name($con));
    $res = $con->query("SELECT RELEASE_LOCK('{$name}') AS released");
    if ($res !== false) {
        $res->free();
    }
}

/**
 * @return array{id: int, ts: int}|null null when no game is processed yet
 */
function k2_ops_game_queue_frontier(mysqli $con): ?array
{
    $res = $con->query(
        'SELECT id, UNIX_TIMESTAMP(`Date`) AS ts FROM ratedresults'
        . ' WHERE NewRatingA IS NOT NULL ORDER BY id DESC LIMIT 1'
    );
    if ($res === false) {
        throw new RuntimeException('game queue frontier: ' . $con->error);
    }
    $row = $res->fetch_assoc();
    $res->free();

    return $row ? ['id' => (int) $row['id'], 'ts' => (int) $row['ts']] : null;
}

/**
 * Pending games in processing order.
 *
 * @param int|null $throughGameId only games with id up to and including this one
 * @param DateTimeImmutable|null $before only games dated before this moment
 * @return list<array{id: int, ts: int}>
 */
function k2_ops_game_queue_pending(
    mysqli $con,
    ?int $throughGameId = null,
    ?DateTimeImmutable $before = null
): array {
    $frontier = k2_ops_game_queue_frontier($con);
    $sql = 'SELECT id, UNIX_TIMESTAMP(`Date`) AS ts FROM ratedresults WHERE id > '
        . (int) ($frontier['id'] ?? 0)
        . ' AND NewRatingA IS NULL AND ' . K2_OPS_GAME_QUEUE_VALID_SQL;
    if ($throughGameId !== null) {
        $sql .= ' AND id <= ' . $throughGameId;
    }
    if ($before !== null) {
        // Connection time_zone is +00:00 (k2_ops_connect_work).
        $utc = $before->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $sql .= " AND `Date` < '{$utc}'";
    }
    $res = $con->query($sql . ' ORDER BY `Date` ASC, id ASC');
    if ($res === false) {
        throw new RuntimeException('game queue pending: ' . $con->error);
    }
    $games = [];
    while ($row = $res->fetch_assoc()) {
        $games[] = ['id' => (int) $row['id'], 'ts' => (int) $row['ts']];
    }
    $res->free();

    return $games;
}

/**
 * Valid games that were never processed although later games were. Scans the whole table.
 *
 * @return list<int>
 */
function k2_ops_game_queue_holes(mysqli $con): array
{
    $frontier = k2_ops_game_queue_frontier($con);
    if ($frontier === null) {
        return [];
    }
    $res = $con->query(
        'SELECT id FROM ratedresults WHERE id < ' . $frontier['id']
        . ' AND NewRatingA IS NULL AND ' . K2_OPS_GAME_QUEUE_VALID_SQL . ' ORDER BY id ASC'
    );
    if ($res === false) {
        throw new RuntimeException('game queue holes: ' . $con->error);
    }
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['id'];
    }
    $res->free();

    return $ids;
}
