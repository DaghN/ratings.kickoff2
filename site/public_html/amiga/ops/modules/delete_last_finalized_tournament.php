<?php
/**
 * Case B — delete chrono-last finalized tip + project-present-at prior tip (L5 slice 4).
 *
 * Verb: delete-last-finalized-tournament (amiga-live-ops-platform.md §7.2 / §7.4).
 * Clears derived for tip T (§5.3), deletes ground, re-projects present at prior N (§5.4).
 * Caller writes backup seal after success (AD6 / BA2) — this module does not seal.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/amiga_tournament_lib.php';
require_once dirname(__DIR__, 3) . '/includes/k2_amiga_player_naming.php';
require_once __DIR__ . '/project_present_at.php';

class AmigaCaseBDeleteException extends RuntimeException
{
}

class AmigaCaseBNotFoundException extends AmigaCaseBDeleteException
{
}

class AmigaCaseBNotFinalizedException extends AmigaCaseBDeleteException
{
}

class AmigaCaseBNotTipException extends AmigaCaseBDeleteException
{
}

class AmigaCaseBNoPriorException extends AmigaCaseBDeleteException
{
}

/**
 * @return array{
 *   id:int,
 *   name:string,
 *   source_id:?int,
 *   lifecycle_status:string,
 *   rating_finalized:int,
 *   event_date:?string,
 *   chrono:?float
 * }
 */
function amiga_case_b_load_tournament(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT id, name, source_id, lifecycle_status, rating_finalized, event_date, chrono '
        . 'FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare Case B tournament load: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute Case B tournament load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        throw new AmigaCaseBNotFoundException("tournament_id={$tournamentId} not found");
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'source_id' => $row['source_id'] !== null ? (int) $row['source_id'] : null,
        'lifecycle_status' => (string) ($row['lifecycle_status'] ?? ''),
        'rating_finalized' => (int) ($row['rating_finalized'] ?? 0),
        'event_date' => $row['event_date'] !== null ? (string) $row['event_date'] : null,
        'chrono' => $row['chrono'] !== null ? (float) $row['chrono'] : null,
    ];
}

/**
 * Chrono-last rating_finalized tip.
 *
 * @return array{id:int, name:string, event_date:?string, chrono:?float}|null
 */
function amiga_case_b_find_tip(mysqli $con): ?array
{
    $res = $con->query(
        'SELECT id, name, event_date, chrono FROM tournaments '
        . 'WHERE COALESCE(rating_finalized, 0) = 1 '
        . 'ORDER BY event_date DESC, chrono DESC, id DESC LIMIT 1'
    );
    if ($res === false) {
        throw new RuntimeException('Case B tip query failed: ' . $con->error);
    }
    $row = $res->fetch_assoc();
    $res->free();
    if ($row === null) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'event_date' => $row['event_date'] !== null ? (string) $row['event_date'] : null,
        'chrono' => $row['chrono'] !== null ? (float) $row['chrono'] : null,
    ];
}

/**
 * Prior finalized tip immediately before tip T (chrono order).
 *
 * @return array{id:int, name:string, event_date:?string, chrono:?float}|null
 */
function amiga_case_b_find_prior_tip(mysqli $con, array $tip): ?array
{
    $tipId = (int) $tip['id'];
    $eventDate = (string) ($tip['event_date'] ?? '');
    $chrono = (float) ($tip['chrono'] ?? 0);
    $stmt = $con->prepare(
        'SELECT id, name, event_date, chrono FROM tournaments '
        . 'WHERE COALESCE(rating_finalized, 0) = 1 '
        . 'AND ('
        . '  event_date < ? '
        . '  OR (event_date = ? AND chrono < ?) '
        . '  OR (event_date = ? AND chrono = ? AND id < ?)'
        . ') '
        . 'ORDER BY event_date DESC, chrono DESC, id DESC LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare Case B prior tip: ' . $con->error);
    }
    $stmt->bind_param('ssdsdi', $eventDate, $eventDate, $chrono, $eventDate, $chrono, $tipId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute Case B prior tip: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'event_date' => $row['event_date'] !== null ? (string) $row['event_date'] : null,
        'chrono' => $row['chrono'] !== null ? (float) $row['chrono'] : null,
    ];
}

/**
 * @param array<string, mixed> $row from amiga_case_b_load_tournament
 * @return array{tip: array, prior: array}
 */
function amiga_case_b_assert_eligible(mysqli $con, array $row): array
{
    $id = (int) $row['id'];
    if ((int) ($row['rating_finalized'] ?? 0) !== 1) {
        throw new AmigaCaseBNotFinalizedException(
            "tournament_id={$id} is not rating_finalized — Case B refuses. "
            . 'Use Case A (delete-unfinalized-tournament) for never-official kitchens.'
        );
    }
    $tip = amiga_case_b_find_tip($con);
    if ($tip === null || (int) $tip['id'] !== $id) {
        $tipId = $tip !== null ? (int) $tip['id'] : 0;
        throw new AmigaCaseBNotTipException(
            "tournament_id={$id} is not the chrono-last finalized tip "
            . "(tip is #{$tipId}). Case C (forward re-finalize) is not this verb."
        );
    }
    $prior = amiga_case_b_find_prior_tip($con, $tip);
    if ($prior === null) {
        throw new AmigaCaseBNoPriorException(
            "tournament_id={$id} is the only finalized tip — Case B refuses "
            . '(no prior N to project-present-at). Restore from seal instead of emptying the realm.'
        );
    }

    return ['tip' => $tip, 'prior' => $prior];
}

/**
 * Explicit §5.3 derived clear for tip T (even where CASCADE would fire).
 */
function amiga_case_b_clear_derived_for_tournament(mysqli $con, int $tournamentId): void
{
    $sqls = [
        'DELETE r FROM amiga_game_ratings r '
            . 'INNER JOIN amiga_games g ON g.id = r.game_id WHERE g.tournament_id = ?',
        'DELETE FROM amiga_player_event_snapshots WHERE tournament_id = ?',
        'DELETE FROM amiga_player_elo_rank_at_event WHERE tournament_id = ?',
        'DELETE FROM amiga_player_inverse_count_at_event WHERE tournament_id = ?',
        'DELETE FROM amiga_player_matchup_at_event WHERE as_of_tournament_id = ?',
        'DELETE FROM amiga_tournament_standings WHERE tournament_id = ?',
        'DELETE FROM amiga_tournament_catalog_stats WHERE tournament_id = ?',
        'DELETE FROM amiga_realm_snapshots WHERE tournament_id = ?',
        'DELETE FROM amiga_community_stats_snapshots WHERE tournament_id = ?',
        'DELETE FROM amiga_community_stat_facts WHERE tournament_id = ?',
        'DELETE FROM amiga_world_cup_stats WHERE tournament_id = ?',
        'DELETE FROM amiga_player_slice_at_event WHERE as_of_tournament_id = ?',
        'DELETE FROM amiga_country_slice_at_event WHERE as_of_tournament_id = ?',
        'DELETE FROM amiga_wc_hof_snapshots WHERE tournament_id = ?',
    ];
    foreach ($sqls as $sql) {
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('prepare Case B derived clear: ' . $con->error . ' SQL=' . $sql);
        }
        $stmt->bind_param('i', $tournamentId);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('execute Case B derived clear: ' . $err . ' SQL=' . $sql);
        }
        $stmt->close();
    }
}

/**
 * @return list<int>
 */
function amiga_case_b_entrant_player_ids(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT DISTINCT player_id FROM tournament_entrants WHERE tournament_id = ? ORDER BY player_id'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare Case B entrants: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute Case B entrants: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $ids = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['player_id'];
        }
    }
    $stmt->close();

    return $ids;
}

/**
 * Tip row for admin UI (current chrono-last finalized + prior).
 *
 * @return array{
 *   tip:?array{id:int,name:string,event_date:?string,chrono:?float,games:int,entrants:int},
 *   prior:?array{id:int,name:string,event_date:?string,chrono:?float}
 * }
 */
function amiga_case_b_tip_context(mysqli $con): array
{
    $tip = amiga_case_b_find_tip($con);
    if ($tip === null) {
        return ['tip' => null, 'prior' => null];
    }
    $tid = (int) $tip['id'];
    $games = 0;
    $entrants = 0;
    $stmt = $con->prepare(
        'SELECT '
        . '(SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id = ?) AS games, '
        . '(SELECT COUNT(*) FROM tournament_entrants e WHERE e.tournament_id = ?) AS entrants'
    );
    if ($stmt !== false) {
        $stmt->bind_param('ii', $tid, $tid);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $games = (int) ($row['games'] ?? 0);
            $entrants = (int) ($row['entrants'] ?? 0);
        }
        $stmt->close();
    }
    $prior = amiga_case_b_find_prior_tip($con, $tip);

    return [
        'tip' => [
            'id' => $tid,
            'name' => (string) $tip['name'],
            'event_date' => $tip['event_date'],
            'chrono' => $tip['chrono'],
            'games' => $games,
            'entrants' => $entrants,
        ],
        'prior' => $prior,
    ];
}

/**
 * Delete chrono-last finalized tip (Case B). Does not seal — caller must seal (AD6).
 *
 * @return array{
 *   ok:bool,
 *   dry_run:bool,
 *   tournament_id:int,
 *   name:string,
 *   prior_tournament_id:int,
 *   prior_name:string,
 *   games_deleted:int,
 *   orphan_players_deleted:list<int>,
 *   project:?array,
 *   error:string
 * }
 */
function amiga_delete_last_finalized_tournament(mysqli $con, int $tournamentId, bool $dryRun = false): array
{
    $fail = static function (string $msg) use ($tournamentId, $dryRun): array {
        return [
            'ok' => false,
            'dry_run' => $dryRun,
            'tournament_id' => $tournamentId,
            'name' => '',
            'prior_tournament_id' => 0,
            'prior_name' => '',
            'games_deleted' => 0,
            'orphan_players_deleted' => [],
            'project' => null,
            'error' => $msg,
        ];
    };

    if ($tournamentId <= 0) {
        return $fail('tournament_id must be positive.');
    }

    try {
        $row = amiga_case_b_load_tournament($con, $tournamentId);
        $ctx = amiga_case_b_assert_eligible($con, $row);
    } catch (AmigaCaseBDeleteException $e) {
        return $fail($e->getMessage());
    }

    $prior = $ctx['prior'];
    $priorId = (int) $prior['id'];
    $priorName = (string) $prior['name'];
    $entrantIds = amiga_case_b_entrant_player_ids($con, $tournamentId);

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
            'prior_tournament_id' => $priorId,
            'prior_name' => $priorName,
            'games_deleted' => $gamesDeleted,
            'orphan_players_deleted' => [],
            'project' => null,
            'error' => '',
        ];
    }

    $lockName = 'amiga_delete_last_finalized_tournament';
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
        return $fail('could not acquire advisory lock (another Case B / finalize in progress?)');
    }

    $startedTx = false;
    $project = null;
    try {
        // Re-check tip under lock.
        $row = amiga_case_b_load_tournament($con, $tournamentId);
        $ctx = amiga_case_b_assert_eligible($con, $row);
        $prior = $ctx['prior'];
        $priorId = (int) $prior['id'];
        $priorName = (string) $prior['name'];

        if (!$con->begin_transaction()) {
            throw new RuntimeException('begin_transaction failed: ' . $con->error);
        }
        $startedTx = true;

        amiga_case_b_clear_derived_for_tournament($con, $tournamentId);

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

        if (!$con->commit()) {
            throw new RuntimeException('commit failed: ' . $con->error);
        }
        $startedTx = false;

        $project = amiga_ops_project_present_at($con, $priorId);
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
        'prior_tournament_id' => $priorId,
        'prior_name' => $priorName,
        'games_deleted' => $gamesDeleted,
        'orphan_players_deleted' => $orphans,
        'project' => $project,
        'error' => '',
    ];
}