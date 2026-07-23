<?php
/**
 * Case C insert — mid-history organizer Finish (keep M, re-finalize forward).
 *
 * Policy: docs/amiga-case-c-insert-finish-implementation-plan.md
 * Reuses Case C delete helpers in delete_finalized_mid_tournament.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/delete_finalized_mid_tournament.php';
require_once __DIR__ . '/finalize_tournament.php';
require_once __DIR__ . '/project_present_at.php';
require_once __DIR__ . '/../includes/amiga_promote_running_tournament.php';

class AmigaCaseCInsertException extends RuntimeException
{
}

const AMIGA_CASE_C_INSERT_LOCK_NAME = 'amiga_case_c_insert_finish';

/**
 * @return array{id:int, name:string, event_date:?string, chrono:?float, rating_finalized:int}
 */
function amiga_case_c_insert_finish_load_m_row(mysqli $con, int $tournamentId): array
{
    if ($tournamentId <= 0) {
        throw new AmigaCaseCInsertException('tournament_id must be positive.');
    }
    $stmt = $con->prepare(
        'SELECT id, name, event_date, chrono, COALESCE(rating_finalized, 0) AS rating_finalized, '
        . 'lifecycle_status FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare insert M load: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute insert M load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        throw new AmigaCaseCInsertException("tournament_id={$tournamentId} not found.");
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'event_date' => $row['event_date'] !== null ? (string) $row['event_date'] : null,
        'chrono' => $row['chrono'] !== null ? (float) $row['chrono'] : null,
        'rating_finalized' => (int) $row['rating_finalized'],
        'lifecycle_status' => (string) ($row['lifecycle_status'] ?? ''),
    ];
}

/**
 * Catalog tuple for M; previews chrono when null (no write).
 *
 * @return array{id:int, name:string, event_date:?string, chrono:?float}
 */
function amiga_case_c_insert_finish_m_catalog_tuple(mysqli $con, array $row): array
{
    $chrono = $row['chrono'];
    if ($chrono === null && $row['event_date'] !== null && $row['event_date'] !== '') {
        $chrono = amiga_promote_next_tournament_chrono($con, (string) $row['event_date'], (int) $row['id']);
    }
    if ($chrono === null) {
        $chrono = 0.0;
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'event_date' => $row['event_date'],
        'chrono' => (float) $chrono,
    ];
}

/**
 * @return array{
 *   is_mid_history:bool,
 *   m:array{id:int, name:string, event_date:?string, chrono:?float},
 *   n:?array{id:int, name:string, event_date:?string, chrono:?float},
 *   forward:list<array{id:int, name:string, event_date:?string, chrono:?float, rating_finalized:int}>,
 *   forward_count:int,
 *   error:string
 * }
 */
function amiga_case_c_insert_finish_probe(mysqli $con, int $tournamentId): array
{
    $emptyForward = static function (array $m, ?array $n = null, string $err = ''): array {
        return [
            'is_mid_history' => false,
            'm' => $m,
            'n' => $n,
            'forward' => [],
            'forward_count' => 0,
            'error' => $err,
        ];
    };

    try {
        $row = amiga_case_c_insert_finish_load_m_row($con, $tournamentId);
    } catch (AmigaCaseCInsertException $e) {
        return [
            'is_mid_history' => false,
            'm' => ['id' => $tournamentId, 'name' => '', 'event_date' => null, 'chrono' => null],
            'n' => null,
            'forward' => [],
            'forward_count' => 0,
            'error' => $e->getMessage(),
        ];
    }

    $m = amiga_case_c_insert_finish_m_catalog_tuple($con, $row);
    if ((int) $row['rating_finalized'] === 1) {
        return $emptyForward($m);
    }

    $forward = amiga_case_c_list_tournaments_after($con, $m, true);
    $n = null;
    if ($forward !== []) {
        $n = amiga_case_c_find_prior_finalized($con, $m);
    }

    return [
        'is_mid_history' => $forward !== [],
        'm' => $m,
        'n' => $n,
        'forward' => $forward,
        'forward_count' => count($forward),
        'error' => '',
    ];
}

/**
 * @param list<int> $forwardIds
 */
function amiga_case_c_insert_finish_finalize_chain_csv(int $mId, array $forwardIds): string
{
    $ids = array_merge([$mId], $forwardIds);

    return implode(',', array_map('intval', $ids));
}

/**
 * Case C insert phase 1: truncate > N, reset forward, promote M (no finalize/project).
 *
 * @return array{
 *   ok:bool,
 *   tournament_id:int,
 *   name:string,
 *   cutoff_id:int,
 *   cutoff_name:string,
 *   truncated_ids:list<int>,
 *   forward_ids:list<int>,
 *   finalize_chain_csv:string,
 *   promoted_games:int,
 *   error:string
 * }
 */
function amiga_case_c_insert_finish_prepare(mysqli $con, int $tournamentId): array
{
    $fail = static function (string $msg) use ($tournamentId): array {
        return [
            'ok' => false,
            'tournament_id' => $tournamentId,
            'name' => '',
            'cutoff_id' => 0,
            'cutoff_name' => '',
            'truncated_ids' => [],
            'forward_ids' => [],
            'finalize_chain_csv' => '',
            'promoted_games' => 0,
            'error' => $msg,
        ];
    };

    $probe = amiga_case_c_insert_finish_probe($con, $tournamentId);
    if ($probe['error'] !== '') {
        return $fail($probe['error']);
    }
    if (!$probe['is_mid_history']) {
        return $fail('not a mid-history Finish — use normal tip Finish.');
    }
    if ($probe['n'] === null) {
        return $fail(
            'no prior finalized N before this event — cannot project-present-at. '
            . 'Restore from seal or contact admin.'
        );
    }

    try {
        $row = amiga_case_c_insert_finish_load_m_row($con, $tournamentId);
    } catch (AmigaCaseCInsertException $e) {
        return $fail($e->getMessage());
    }
    if ((int) $row['rating_finalized'] === 1) {
        return $fail('tournament is already rating_finalized.');
    }
    if (($row['lifecycle_status'] ?? '') !== 'running') {
        return $fail('mid-history Finish requires lifecycle_status=running.');
    }

    $n = $probe['n'];
    $cutoffId = (int) $n['id'];
    $cutoffName = (string) $n['name'];
    $forwardIds = [];
    foreach ($probe['forward'] as $f) {
        $forwardIds[] = (int) $f['id'];
    }

    $lockName = AMIGA_CASE_C_INSERT_LOCK_NAME;
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
        return $fail('could not acquire advisory lock (another Finish / Case C op in progress?)');
    }

    $truncatedIds = [];
    $promotedGames = 0;
    $released = false;
    try {
        if (!$con->begin_transaction()) {
            throw new RuntimeException('begin_transaction failed: ' . $con->error);
        }

        $truncatedIds = amiga_ops_truncate_derived_after($con, $n);

        foreach ($forwardIds as $fwdId) {
            amiga_case_c_reset_for_refinalize($con, $fwdId);
        }

        $officialCount = 0;
        $stmt = $con->prepare('SELECT COUNT(*) AS n FROM amiga_games WHERE tournament_id = ?');
        if ($stmt === false) {
            throw new RuntimeException('prepare game count: ' . $con->error);
        }
        $stmt->bind_param('i', $tournamentId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute game count: ' . $stmt->error);
        }
        $res = $stmt->get_result();
        $countRow = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $officialCount = (int) ($countRow['n'] ?? 0);

        if ($officialCount === 0) {
            $promote = amiga_promote_running_tournament($con, $tournamentId, false);
            if (!empty($promote['skipped']) && ($promote['skip_reason'] ?? '') === 'games_already_exist') {
                throw new RuntimeException('promote refused: games_already_exist');
            }
            $promotedGames = (int) ($promote['promoted'] ?? 0);
        }

        if (!$con->commit()) {
            throw new RuntimeException('commit failed: ' . $con->error);
        }
    } catch (Throwable $e) {
        $con->rollback();
        $rel = $con->query('SELECT RELEASE_LOCK(\'' . $con->real_escape_string($lockName) . '\')');
        if ($rel === false) {
            // ignore
        }
        $released = true;

        return $fail($e->getMessage());
    }

    if (!$released) {
        $con->query('SELECT RELEASE_LOCK(\'' . $con->real_escape_string($lockName) . '\')');
    }

    return [
        'ok' => true,
        'tournament_id' => $tournamentId,
        'name' => (string) $row['name'],
        'cutoff_id' => $cutoffId,
        'cutoff_name' => $cutoffName,
        'truncated_ids' => $truncatedIds,
        'forward_ids' => $forwardIds,
        'finalize_chain_csv' => amiga_case_c_insert_finish_finalize_chain_csv($tournamentId, $forwardIds),
        'promoted_games' => $promotedGames,
        'error' => '',
    ];
}

/**
 * Finalize one tournament in the insert chain (M or forward).
 *
 * @return array{ok:bool, tournament_id:int, name:string, games:int, next_id:int, error:string}
 */
function amiga_case_c_insert_finish_finalize_one(mysqli $con, int $tournamentId): array
{
    $fail = static function (string $msg) use ($tournamentId): array {
        return [
            'ok' => false,
            'tournament_id' => $tournamentId,
            'name' => '',
            'games' => 0,
            'next_id' => 0,
            'error' => $msg,
        ];
    };

    if ($tournamentId <= 0) {
        return $fail('tournament_id must be positive.');
    }

    try {
        $row = amiga_case_c_insert_finish_load_m_row($con, $tournamentId);
    } catch (AmigaCaseCInsertException $e) {
        return $fail($e->getMessage());
    }

    if ((int) $row['rating_finalized'] === 1) {
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
            'games' => 0,
            'next_id' => $nextId,
            'error' => '',
        ];
    }

    try {
        $result = amiga_finalize_tournament($con, $tournamentId, false);
    } catch (Throwable $e) {
        return $fail($e->getMessage());
    }

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
        'next_id' => $nextId,
        'error' => '',
    ];
}

/**
 * @param list<string> $names
 */
function amiga_case_c_insert_finish_forward_names_summary(array $names, int $maxShow = 3): string
{
    $names = array_values(array_filter(array_map('strval', $names)));
    if ($names === []) {
        return '';
    }
    if (count($names) <= $maxShow) {
        return implode(', ', $names);
    }
    $head = array_slice($names, 0, $maxShow);
    $more = count($names) - $maxShow;

    return implode(', ', $head) . ' and ' . $more . ' more';
}