<?php
/**
 * Case C — delete finalized non-tip M with later finalized events (L5 slice 5).
 *
 * Verbs: truncate-derived-after · delete-finalized-mid-tournament · refinalize-forward-from
 * (amiga-live-ops-platform.md §7.3 / §7.4).
 *
 * Pipeline: truncate derived chrono > N → delete M ground → reset remaining forward →
 * project-present-at N (caller, phased HTTP) → finalize forward oldest→newest → seal (caller, AD6).
 * Reuses Case B §5.3 clear + ground/orphan patterns. Does not rewrite project_present_at.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/amiga_tournament_lib.php';
require_once dirname(__DIR__, 3) . '/includes/k2_amiga_player_naming.php';
require_once __DIR__ . '/../includes/amiga_chrono_integer_lib.php';
require_once __DIR__ . '/delete_last_finalized_tournament.php';
require_once __DIR__ . '/finalize_tournament.php';

class AmigaCaseCDeleteException extends RuntimeException
{
}

class AmigaCaseCNotFoundException extends AmigaCaseCDeleteException
{
}

class AmigaCaseCNotFinalizedException extends AmigaCaseCDeleteException
{
}

class AmigaCaseCIsTipException extends AmigaCaseCDeleteException
{
}

class AmigaCaseCNoPriorException extends AmigaCaseCDeleteException
{
}

/**
 * Chrono SQL lives in amiga_chrono_integer_lib.php (amiga_case_c_chrono_after_sql).
 */

/**
 * @param array{id:int, event_date:?string, chrono:?float} $cutoff
 * @return list<array{id:int, name:string, event_date:?string, chrono:?float, rating_finalized:int}>
 */
function amiga_case_c_list_tournaments_after(mysqli $con, array $cutoff, bool $finalizedOnly = false): array
{
    $cutoffId = (int) $cutoff['id'];
    $eventDate = (string) ($cutoff['event_date'] ?? '');
    $chrono = (float) ($cutoff['chrono'] ?? 0);
    $sql = 'SELECT id, name, event_date, chrono, COALESCE(rating_finalized, 0) AS rating_finalized '
        . 'FROM tournaments t WHERE ' . amiga_case_c_chrono_after_sql('t');
    if ($finalizedOnly) {
        $sql .= ' AND COALESCE(t.rating_finalized, 0) = 1';
    }
    $sql .= ' ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare Case C list after: ' . $con->error);
    }
    $stmt->bind_param('ssdsdi', $eventDate, $eventDate, $chrono, $eventDate, $chrono, $cutoffId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute Case C list after: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $out = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'event_date' => $row['event_date'] !== null ? (string) $row['event_date'] : null,
                'chrono' => $row['chrono'] !== null ? (float) $row['chrono'] : null,
                'rating_finalized' => (int) $row['rating_finalized'],
            ];
        }
    }
    $stmt->close();

    return $out;
}

/**
 * Prior finalized tip immediately before M (chrono order). Same key as Case B.
 *
 * @return array{id:int, name:string, event_date:?string, chrono:?float}|null
 */
function amiga_case_c_find_prior_finalized(mysqli $con, array $m): ?array
{
    return amiga_case_b_find_prior_tip($con, $m);
}

/**
 * Remaining forward events after Case C delete+reset: chrono > N and not yet re-finalized.
 *
 * @param array{id:int, event_date:?string, chrono:?float} $cutoffN
 * @return list<array{id:int, name:string, event_date:?string, chrono:?float, rating_finalized:int}>
 */
function amiga_case_c_list_pending_forward(mysqli $con, array $cutoffN): array
{
    $all = amiga_case_c_list_tournaments_after($con, $cutoffN, false);
    $pending = [];
    foreach ($all as $row) {
        if ((int) $row['rating_finalized'] === 0) {
            $pending[] = $row;
        }
    }

    return $pending;
}

/**
 * @param array<string, mixed> $row from amiga_case_b_load_tournament
 * @return array{
 *   m: array,
 *   n: array{id:int, name:string, event_date:?string, chrono:?float},
 *   tip: array{id:int, name:string, event_date:?string, chrono:?float},
 *   forward: list<array{id:int, name:string, event_date:?string, chrono:?float, rating_finalized:int}>,
 *   remaining_forward: list<array{id:int, name:string, event_date:?string, chrono:?float, rating_finalized:int}>
 * }
 */
function amiga_case_c_assert_eligible(mysqli $con, array $row): array
{
    $id = (int) $row['id'];
    if ((int) ($row['rating_finalized'] ?? 0) !== 1) {
        throw new AmigaCaseCNotFinalizedException(
            "tournament_id={$id} is not rating_finalized — Case C refuses. "
            . 'Use Case A (delete-unfinalized-tournament) for never-official kitchens.'
        );
    }
    $tip = amiga_case_b_find_tip($con);
    if ($tip === null) {
        throw new AmigaCaseCDeleteException('no finalized tip in catalog');
    }
    if ((int) $tip['id'] === $id) {
        throw new AmigaCaseCIsTipException(
            "tournament_id={$id} is the chrono-last finalized tip — Case C refuses. "
            . 'Use Case B (delete-last-finalized-tournament).'
        );
    }
    $prior = amiga_case_c_find_prior_finalized($con, $row);
    if ($prior === null) {
        throw new AmigaCaseCNoPriorException(
            "tournament_id={$id} has no prior finalized N — Case C refuses "
            . '(cannot project-present-at M−1). Restore from seal instead.'
        );
    }
    $forward = amiga_case_c_list_tournaments_after($con, $prior, true);
    if ($forward === []) {
        throw new AmigaCaseCDeleteException(
            "tournament_id={$id}: no finalized events after N=#{$prior['id']} — unexpected."
        );
    }
    $mInForward = false;
    $remaining = [];
    foreach ($forward as $f) {
        if ((int) $f['id'] === $id) {
            $mInForward = true;
            continue;
        }
        $remaining[] = $f;
    }
    if (!$mInForward) {
        throw new AmigaCaseCDeleteException(
            "tournament_id={$id} is not in the finalized forward set after N=#{$prior['id']}."
        );
    }
    if ($remaining === []) {
        throw new AmigaCaseCIsTipException(
            "tournament_id={$id} has no later finalized events — use Case B if it is the tip."
        );
    }

    return [
        'm' => [
            'id' => $id,
            'name' => (string) $row['name'],
            'event_date' => $row['event_date'] ?? null,
            'chrono' => $row['chrono'] ?? null,
        ],
        'n' => $prior,
        'tip' => $tip,
        'forward' => $forward,
        'remaining_forward' => $remaining,
    ];
}

/**
 * Truncate §5.3 derived for every tournament with chrono > N (includes M and later tips).
 *
 * @param array{id:int, event_date:?string, chrono:?float} $cutoffN
 * @return list<int> tournament ids cleared
 */
function amiga_ops_truncate_derived_after(mysqli $con, array $cutoffN): array
{
    $rows = amiga_case_c_list_tournaments_after($con, $cutoffN, false);
    $ids = [];
    foreach ($rows as $row) {
        $tid = (int) $row['id'];
        amiga_case_b_clear_derived_for_tournament($con, $tid);
        $ids[] = $tid;
    }

    return $ids;
}

/**
 * Reset a remaining forward event so amiga_finalize_tournament can run again.
 * Derived for this id must already be truncated (or clear ratings here as safety).
 */
function amiga_case_c_reset_for_refinalize(mysqli $con, int $tournamentId): void
{
    $stmt = $con->prepare(
        'UPDATE tournaments SET rating_finalized = 0, rating_finalized_at = NULL, '
        . 'scoring_frozen_at = NULL, frozen_scoring_schema_version = NULL WHERE id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare Case C reset flags: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute Case C reset flags: ' . $stmt->error);
    }
    $stmt->close();

    // Safety: ratings may already be gone via truncate; finalize also clears before write.
    $stmt = $con->prepare(
        'DELETE r FROM amiga_game_ratings r '
        . 'INNER JOIN amiga_games g ON g.id = r.game_id WHERE g.tournament_id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare Case C reset ratings: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute Case C reset ratings: ' . $stmt->error);
    }
    $stmt->close();
}

/**
 * Finalize one pending forward tournament (must already be reset / not rating_finalized).
 *
 * @return array{ok:bool, tournament_id:int, name:string, games:int, skipped:bool, next_id:int, error:string}
 */
function amiga_ops_refinalize_forward_one(mysqli $con, int $tournamentId): array
{
    $fail = static function (string $msg) use ($tournamentId): array {
        return [
            'ok' => false,
            'tournament_id' => $tournamentId,
            'name' => '',
            'games' => 0,
            'skipped' => false,
            'next_id' => 0,
            'error' => $msg,
        ];
    };
    if ($tournamentId <= 0) {
        return $fail('tournament_id must be positive.');
    }
    try {
        $row = amiga_case_b_load_tournament($con, $tournamentId);
    } catch (AmigaCaseBNotFoundException $e) {
        return $fail($e->getMessage());
    }
    if ((int) ($row['rating_finalized'] ?? 0) === 1) {
        return $fail(
            "tournament_id={$tournamentId} is already rating_finalized — "
            . 'refinalize-forward-from expects a reset pending forward event.'
        );
    }
    try {
        $result = amiga_finalize_tournament($con, $tournamentId, false);
    } catch (Throwable $e) {
        return $fail($e->getMessage());
    }

    // Next pending: earliest unfinalized after this event's chrono (same chain).
    $nextId = 0;
    $afterThis = amiga_case_c_list_tournaments_after($con, [
        'id' => (int) $row['id'],
        'event_date' => $row['event_date'],
        'chrono' => $row['chrono'],
    ], false);
    foreach ($afterThis as $cand) {
        if ((int) $cand['rating_finalized'] === 0) {
            $nextId = (int) $cand['id'];
            break;
        }
    }

    return [
        'ok' => true,
        'tournament_id' => $tournamentId,
        'name' => (string) $row['name'],
        'games' => (int) ($result['games'] ?? 0),
        'skipped' => !empty($result['skipped']),
        'next_id' => $nextId,
        'error' => '',
    ];
}

/**
 * Admin UI: recent finalized non-tip candidates with at least one later finalized tip.
 *
 * @return list<array{
 *   id:int,
 *   name:string,
 *   event_date:?string,
 *   games:int,
 *   entrants:int,
 *   forward_count:int,
 *   prior_id:int,
 *   prior_name:string,
 *   tip_id:int,
 *   tip_name:string
 * }>
 */
function amiga_case_c_list_candidates(mysqli $con, int $limit = 20): array
{
    $tip = amiga_case_b_find_tip($con);
    if ($tip === null) {
        return [];
    }
    $tipId = (int) $tip['id'];
    $limit = max(1, min(100, $limit));
    $sql = 'SELECT t.id, t.name, t.event_date, t.chrono, '
        . '(SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id = t.id) AS games, '
        . '(SELECT COUNT(*) FROM tournament_entrants e WHERE e.tournament_id = t.id) AS entrants '
        . 'FROM tournaments t '
        . 'WHERE COALESCE(t.rating_finalized, 0) = 1 AND t.id <> ' . $tipId . ' '
        . 'ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC '
        . 'LIMIT ' . ($limit * 4);
    $res = $con->query($sql);
    if ($res === false) {
        throw new RuntimeException('Case C candidate list failed: ' . $con->error);
    }
    $out = [];
    while ($row = $res->fetch_assoc()) {
        $m = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'event_date' => $row['event_date'] !== null ? (string) $row['event_date'] : null,
            'chrono' => $row['chrono'] !== null ? (float) $row['chrono'] : null,
            'rating_finalized' => 1,
        ];
        try {
            $ctx = amiga_case_c_assert_eligible($con, array_merge($m, [
                'source_id' => null,
                'lifecycle_status' => '',
            ]));
        } catch (AmigaCaseCDeleteException $e) {
            continue;
        }
        $forwardCount = count($ctx['remaining_forward']);
        // Narrow UI: prefer short forward chains (1–3 after M).
        if ($forwardCount < 1 || $forwardCount > 3) {
            continue;
        }
        $out[] = [
            'id' => (int) $m['id'],
            'name' => (string) $m['name'],
            'event_date' => $m['event_date'],
            'games' => (int) $row['games'],
            'entrants' => (int) $row['entrants'],
            'forward_count' => $forwardCount,
            'prior_id' => (int) $ctx['n']['id'],
            'prior_name' => (string) $ctx['n']['name'],
            'tip_id' => (int) $ctx['tip']['id'],
            'tip_name' => (string) $ctx['tip']['name'],
        ];
        if (count($out) >= $limit) {
            break;
        }
    }
    $res->free();

    return $out;
}

/**
 * Case C phase 1: truncate > N, delete M ground, reset remaining forward.
 * Does NOT project / finalize / seal — HTTP must phase those separately.
 *
 * @return array{
 *   ok:bool,
 *   dry_run:bool,
 *   tournament_id:int,
 *   name:string,
 *   cutoff_id:int,
 *   cutoff_name:string,
 *   truncated_ids:list<int>,
 *   remaining_forward_ids:list<int>,
 *   remaining_forward_names:list<string>,
 *   games_deleted:int,
 *   orphan_players_deleted:list<int>,
 *   error:string
 * }
 */
function amiga_delete_finalized_mid_tournament(mysqli $con, int $tournamentId, bool $dryRun = false): array
{
    $fail = static function (string $msg) use ($tournamentId, $dryRun): array {
        return [
            'ok' => false,
            'dry_run' => $dryRun,
            'tournament_id' => $tournamentId,
            'name' => '',
            'cutoff_id' => 0,
            'cutoff_name' => '',
            'truncated_ids' => [],
            'remaining_forward_ids' => [],
            'remaining_forward_names' => [],
            'games_deleted' => 0,
            'orphan_players_deleted' => [],
            'error' => $msg,
        ];
    };

    if ($tournamentId <= 0) {
        return $fail('tournament_id must be positive.');
    }

    try {
        $row = amiga_case_b_load_tournament($con, $tournamentId);
        $ctx = amiga_case_c_assert_eligible($con, $row);
    } catch (AmigaCaseBNotFoundException $e) {
        return $fail($e->getMessage());
    } catch (AmigaCaseCDeleteException $e) {
        return $fail($e->getMessage());
    }

    $n = $ctx['n'];
    $cutoffId = (int) $n['id'];
    $cutoffName = (string) $n['name'];
    $remaining = $ctx['remaining_forward'];
    $remainingIds = [];
    $remainingNames = [];
    foreach ($remaining as $f) {
        $remainingIds[] = (int) $f['id'];
        $remainingNames[] = (string) $f['name'];
    }
    $forwardIds = [];
    foreach ($ctx['forward'] as $f) {
        $forwardIds[] = (int) $f['id'];
    }

    $gamesDeleted = 0;
    $stmt = $con->prepare('SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = ?');
    if ($stmt === false) {
        return $fail('prepare game count: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();

        return $fail('execute game count: ' . $err);
    }
    $res = $stmt->get_result();
    $countRow = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $gamesDeleted = (int) ($countRow['n'] ?? 0);

    if ($dryRun) {
        return [
            'ok' => true,
            'dry_run' => true,
            'tournament_id' => $tournamentId,
            'name' => (string) $row['name'],
            'cutoff_id' => $cutoffId,
            'cutoff_name' => $cutoffName,
            'truncated_ids' => $forwardIds,
            'remaining_forward_ids' => $remainingIds,
            'remaining_forward_names' => $remainingNames,
            'games_deleted' => $gamesDeleted,
            'orphan_players_deleted' => [],
            'error' => '',
        ];
    }

    $lockName = 'amiga_delete_finalized_mid_tournament';
    $lockStmt = $con->prepare('SELECT GET_LOCK(?, 30)');
    if ($lockStmt === false) {
        return $fail('prepare GET_LOCK: ' . $con->error);
    }
    $lockStmt->bind_param('s', $lockName);
    if (!$lockStmt->execute()) {
        $err = $lockStmt->error;
        $lockStmt->close();

        return $fail('execute GET_LOCK: ' . $err);
    }
    $lockRes = $lockStmt->get_result();
    $lockRow = $lockRes ? $lockRes->fetch_row() : null;
    $lockStmt->close();
    if ((int) ($lockRow[0] ?? 0) !== 1) {
        return $fail('could not acquire advisory lock (another Case B/C / finalize in progress?)');
    }

    $startedTx = false;
    $entrantIds = amiga_case_b_entrant_player_ids($con, $tournamentId);
    $truncatedIds = [];
    try {
        $row = amiga_case_b_load_tournament($con, $tournamentId);
        $ctx = amiga_case_c_assert_eligible($con, $row);
        $n = $ctx['n'];
        $cutoffId = (int) $n['id'];
        $cutoffName = (string) $n['name'];
        $remaining = $ctx['remaining_forward'];
        $remainingIds = [];
        $remainingNames = [];
        foreach ($remaining as $f) {
            $remainingIds[] = (int) $f['id'];
            $remainingNames[] = (string) $f['name'];
        }

        if (!$con->begin_transaction()) {
            throw new RuntimeException('begin_transaction failed: ' . $con->error);
        }
        $startedTx = true;

        $truncatedIds = amiga_ops_truncate_derived_after($con, $n);

        $stmt = $con->prepare('DELETE FROM amiga_games WHERE tournament_id = ?');
        if ($stmt === false) {
            throw new RuntimeException('prepare delete games: ' . $con->error);
        }
        $stmt->bind_param('i', $tournamentId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute delete games: ' . $stmt->error);
        }
        $gamesDeleted = (int) $stmt->affected_rows;
        $stmt->close();

        $stmt = $con->prepare('DELETE FROM tournaments WHERE id = ?');
        if ($stmt === false) {
            throw new RuntimeException('prepare delete tournament: ' . $con->error);
        }
        $stmt->bind_param('i', $tournamentId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute delete tournament: ' . $stmt->error);
        }
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException("DELETE tournaments affected {$stmt->affected_rows} rows (expected 1)");
        }
        $stmt->close();

        amiga_chrono_integer_decrement_forward_after_cutoff($con, $n);

        foreach ($remainingIds as $fwdId) {
            amiga_case_c_reset_for_refinalize($con, $fwdId);
        }

        if (!$con->commit()) {
            throw new RuntimeException('commit failed: ' . $con->error);
        }
        $startedTx = false;
    } catch (Throwable $e) {
        if ($startedTx) {
            $con->rollback();
        }
        $rel = $con->query("SELECT RELEASE_LOCK('{$lockName}')");
        if ($rel) {
            $rel->free();
        }

        return $fail($e->getMessage());
    }

    $rel = $con->query("SELECT RELEASE_LOCK('{$lockName}')");
    if ($rel) {
        $rel->free();
    }

    $orphans = [];
    foreach ($entrantIds as $playerId) {
        if (k2_amiga_player_try_delete_orphan($con, $playerId, null)) {
            $orphans[] = $playerId;
        }
    }

    return [
        'ok' => true,
        'dry_run' => false,
        'tournament_id' => $tournamentId,
        'name' => (string) $row['name'],
        'cutoff_id' => $cutoffId,
        'cutoff_name' => $cutoffName,
        'truncated_ids' => $truncatedIds,
        'remaining_forward_ids' => $remainingIds,
        'remaining_forward_names' => $remainingNames,
        'games_deleted' => $gamesDeleted,
        'orphan_players_deleted' => $orphans,
        'error' => '',
    ];
}