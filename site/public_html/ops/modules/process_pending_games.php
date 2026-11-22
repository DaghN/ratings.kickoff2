<?php

declare(strict_types=1);

/**
 * In-order post-game processing: whatever is waiting before a game is processed before that game.
 *
 * Same sequence as the full recalculation (timeline_sim.php): games in Date, id order, and a UTC day is
 * closed before the first game of a later day. Callers must hold k2_ops_game_queue_lock().
 *
 * @see includes/ops_game_queue.php
 */

require_once __DIR__ . '/../includes/ops_bootstrap.php';
require_once __DIR__ . '/../includes/ops_game_queue.php';
require_once __DIR__ . '/process_completed_game.php';
require_once __DIR__ . '/finalize_utc_day.php';
require_once __DIR__ . '/timeline_sim.php';

/**
 * Process pending games in order. Stops at the first failing game (exception), so nothing overtakes it.
 *
 * @param int|null $throughGameId stop after this game id (null = everything pending)
 * @param DateTimeImmutable|null $before only games dated before this moment
 * @param float|null $budgetSeconds start no new game after this many seconds (null = no limit)
 * @return array{processed: list<int>, remaining: int, last: array<string, mixed>|null}
 */
function k2_ops_process_pending_games(
    mysqli $con,
    ?int $throughGameId = null,
    ?DateTimeImmutable $before = null,
    ?float $budgetSeconds = null,
    int $maxBacklog = K2_OPS_GAME_QUEUE_MAX_BACKLOG
): array {
    $frontier = k2_ops_game_queue_frontier($con);
    $pending = k2_ops_game_queue_pending($con, $throughGameId, $before);
    if (count($pending) > $maxBacklog) {
        throw new RuntimeException(
            'backlog of ' . count($pending) . ' games is over the limit of ' . $maxBacklog
            . '; full recalculation in progress or needed (zero-derived + run_ops_sim.php)'
        );
    }

    $t0 = microtime(true);
    $previousDay = $frontier !== null ? gmdate('Y-m-d', $frontier['ts']) : null;
    $processed = [];
    $last = null;
    foreach ($pending as $i => $game) {
        if ($budgetSeconds !== null && microtime(true) - $t0 >= $budgetSeconds) {
            return ['processed' => $processed, 'remaining' => count($pending) - $i, 'last' => $last];
        }
        $day = gmdate('Y-m-d', $game['ts']);
        if ($previousDay !== null && $day > $previousDay) {
            // Normally the midnight run has closed it already; closing again changes nothing.
            k2_ops_timeline_finalize_utc_day($con, $previousDay, false);
        }
        $result = k2_ops_process_completed_game($con, $game['id'], false);
        if (!empty($result['skipped'])) {
            throw new RuntimeException(
                'pending game ' . $game['id'] . ' was skipped: ' . ($result['skip_reason'] ?? 'unknown')
            );
        }
        $processed[] = $game['id'];
        $last = $result;
        if ($previousDay === null || $day > $previousDay) {
            $previousDay = $day;
        }
    }

    return ['processed' => $processed, 'remaining' => 0, 'last' => $last];
}

/**
 * ProcessCompletedGame entry: clear the backlog up to and including this game.
 *
 * @return array{
 *   status: 'processed'|'skipped'|'refused'|'backlog',
 *   reason: string|null,
 *   caught_up: list<int>,
 *   remaining: int,
 *   result: array<string, mixed>|null
 * }
 */
function k2_ops_process_game_in_order(mysqli $con, int $gameId, ?float $budgetSeconds = null): array
{
    $out = ['status' => 'processed', 'reason' => null, 'caught_up' => [], 'remaining' => 0, 'result' => null];

    $skipReason = k2_ops_rated_game_skip_reason(k2_ops_load_rated_game_row($con, $gameId));
    if ($skipReason !== null) {
        k2_ops_log_skip_rated_game($gameId, $skipReason);

        return ['status' => 'skipped', 'reason' => $skipReason] + $out;
    }

    $frontier = k2_ops_game_queue_frontier($con);
    if ($frontier !== null && $gameId < $frontier['id']) {
        return [
            'status' => 'refused',
            'reason' => 'later games are already processed (up to id ' . $frontier['id'] . ')',
        ] + $out;
    }

    $run = k2_ops_process_pending_games($con, $gameId, null, $budgetSeconds);
    $out['caught_up'] = array_values(array_diff($run['processed'], [$gameId]));
    $out['remaining'] = $run['remaining'];
    if ($run['remaining'] > 0) {
        return ['status' => 'backlog', 'reason' => 'time limit reached'] + $out;
    }
    $out['result'] = $run['last'];

    return $out;
}

/**
 * Midnight entry: process every game of the closed day first, then finalize the day.
 *
 * @return array<string, mixed> k2_ops_finalize_utc_day() result plus caught_up
 */
function k2_ops_finalize_utc_day_after_pending(
    mysqli $con,
    ?DateTimeImmutable $asOf,
    ?string $closedUtcDay
): array {
    $asOf = $asOf ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
    $closedUtcDay = $closedUtcDay ?? k2_ops_closed_utc_day_for_as_of($asOf);
    $dayEnd = (new DateTimeImmutable($closedUtcDay . ' 00:00:00', new DateTimeZone('UTC')))->modify('+1 day');

    $run = k2_ops_process_pending_games($con, null, $dayEnd);

    return k2_ops_finalize_utc_day($con, $asOf, false, $closedUtcDay) + ['caught_up' => $run['processed']];
}
