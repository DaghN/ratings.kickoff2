<?php
/**
 * Case A — delete unfinalized / never-official generated tournament (L5 slice 3).
 *
 * Verb: delete-unfinalized-tournament (amiga-live-ops-platform.md §7.1 / §7.4).
 * Deletes L3+L4 ground (+ running package / finish override via FK). No present re-project.
 * No auto-seal — Case A is not tip-changing (BA2/AD6 = Finish + Case B/C tip deletes).
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/amiga_tournament_lib.php';
require_once dirname(__DIR__, 3) . '/includes/k2_amiga_player_naming.php';

class AmigaCaseADeleteException extends RuntimeException
{
}

class AmigaCaseANotFoundException extends AmigaCaseADeleteException
{
}

class AmigaCaseAFinalizedException extends AmigaCaseADeleteException
{
}

class AmigaCaseANotEligibleException extends AmigaCaseADeleteException
{
}

class AmigaCaseAHasTimelineException extends AmigaCaseADeleteException
{
}

/**
 * @return array{
 *   id:int,
 *   name:string,
 *   source_id:?int,
 *   format_overrides:?string,
 *   lifecycle_status:string,
 *   rating_finalized:int,
 *   event_date:?string,
 *   chrono:?float
 * }
 */
function amiga_case_a_load_tournament(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT id, name, source_id, format_overrides, lifecycle_status, '
        . 'rating_finalized, event_date, chrono '
        . 'FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare Case A tournament load: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute Case A tournament load: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        throw new AmigaCaseANotFoundException("tournament_id={$tournamentId} not found");
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'source_id' => $row['source_id'] !== null ? (int) $row['source_id'] : null,
        'format_overrides' => $row['format_overrides'] !== null ? (string) $row['format_overrides'] : null,
        'lifecycle_status' => (string) ($row['lifecycle_status'] ?? ''),
        'rating_finalized' => (int) ($row['rating_finalized'] ?? 0),
        'event_date' => $row['event_date'] !== null ? (string) $row['event_date'] : null,
        'chrono' => $row['chrono'] !== null ? (float) $row['chrono'] : null,
    ];
}

function amiga_case_a_is_eligible_generated(array $row): bool
{
    if ($row['source_id'] !== null) {
        return false;
    }
    $overrides = json_decode((string) ($row['format_overrides'] ?? '{}'), true);
    if (!is_array($overrides)) {
        return false;
    }
    $generatedBy = (string) ($overrides['generated_by'] ?? '');
    foreach (AMIGA_FIXTURE_GENERATED_BY_PREFIXES as $prefix) {
        if (str_starts_with($generatedBy, $prefix)) {
            return true;
        }
    }

    return false;
}

/**
 * L5 timeline evidence for this tournament id (should be empty for Case A).
 *
 * @return array{event_snapshots:int, realm_snapshots:int, game_ratings:int}
 */
function amiga_case_a_timeline_counts(mysqli $con, int $tournamentId): array
{
    $eventSnapshots = 0;
    $realmSnapshots = 0;
    $gameRatings = 0;

    $stmt = $con->prepare(
        'SELECT COUNT(*) AS n FROM amiga_player_event_snapshots WHERE tournament_id = ?'
    );
    if ($stmt !== false) {
        $stmt->bind_param('i', $tournamentId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $eventSnapshots = (int) ($row['n'] ?? 0);
        }
        $stmt->close();
    }

    $stmt = $con->prepare(
        'SELECT COUNT(*) AS n FROM amiga_realm_snapshots WHERE tournament_id = ?'
    );
    if ($stmt !== false) {
        $stmt->bind_param('i', $tournamentId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $realmSnapshots = (int) ($row['n'] ?? 0);
        }
        $stmt->close();
    }

    $stmt = $con->prepare(
        'SELECT COUNT(*) AS n FROM amiga_game_ratings r '
        . 'INNER JOIN amiga_games g ON g.id = r.game_id '
        . 'WHERE g.tournament_id = ?'
    );
    if ($stmt !== false) {
        $stmt->bind_param('i', $tournamentId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $gameRatings = (int) ($row['n'] ?? 0);
        }
        $stmt->close();
    }

    return [
        'event_snapshots' => $eventSnapshots,
        'realm_snapshots' => $realmSnapshots,
        'game_ratings' => $gameRatings,
    ];
}

/**
 * Guard Case A eligibility. Throws on refuse.
 *
 * @param array<string, mixed> $row from amiga_case_a_load_tournament
 */
function amiga_case_a_assert_eligible(mysqli $con, array $row): void
{
    $id = (int) $row['id'];
    if ((int) ($row['rating_finalized'] ?? 0) === 1) {
        throw new AmigaCaseAFinalizedException(
            "tournament_id={$id} is rating_finalized — Case A refuses. "
            . 'Use Case B (delete latest finalized tip) when implemented; do not use organizer Abandon for official tips.'
        );
    }
    if ($row['source_id'] !== null) {
        throw new AmigaCaseANotEligibleException(
            "tournament_id={$id} is an imported Access tournament (source_id set) — Case A refuses."
        );
    }
    if (!amiga_case_a_is_eligible_generated($row)) {
        throw new AmigaCaseANotEligibleException(
            "tournament_id={$id} is not a fixture-generated kitchen (generated_by prefix) — Case A refuses."
        );
    }
    $timeline = amiga_case_a_timeline_counts($con, $id);
    $l5 = (int) $timeline['event_snapshots'] + (int) $timeline['realm_snapshots'] + (int) $timeline['game_ratings'];
    if ($l5 > 0) {
        throw new AmigaCaseAHasTimelineException(
            "tournament_id={$id} has L5/derived timeline rows "
            . "(event_snapshots={$timeline['event_snapshots']}, "
            . "realm_snapshots={$timeline['realm_snapshots']}, "
            . "game_ratings={$timeline['game_ratings']}) — Case A refuses. "
            . 'Incomplete finalize limbo needs repair / Case B path, not Case A trash delete.'
        );
    }
}

/**
 * @return list<int>
 */
function amiga_case_a_entrant_player_ids(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT DISTINCT player_id FROM tournament_entrants WHERE tournament_id = ? ORDER BY player_id'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare Case A entrants: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute Case A entrants: ' . $stmt->error);
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
 * @return list<array{
 *   id:int,
 *   name:string,
 *   lifecycle_status:string,
 *   event_date:?string,
 *   games:int,
 *   entrants:int
 * }>
 */
function amiga_case_a_list_candidates(mysqli $con, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $sql = 'SELECT t.id, t.name, t.lifecycle_status, t.event_date, t.source_id, t.format_overrides, '
        . '(SELECT COUNT(*) FROM amiga_games g WHERE g.tournament_id = t.id) AS games, '
        . '(SELECT COUNT(*) FROM tournament_entrants e WHERE e.tournament_id = t.id) AS entrants '
        . 'FROM tournaments t '
        . 'WHERE COALESCE(t.rating_finalized, 0) = 0 '
        . 'AND t.source_id IS NULL '
        . 'AND (' . amiga_live_tournament_fixture_generated_where('t') . ') '
        . 'ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC '
        . 'LIMIT ' . $limit;
    $res = $con->query($sql);
    if ($res === false) {
        throw new RuntimeException('Case A candidate list failed: ' . $con->error);
    }
    $out = [];
    while ($row = $res->fetch_assoc()) {
        if (!amiga_case_a_is_eligible_generated($row)) {
            continue;
        }
        $out[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'lifecycle_status' => (string) ($row['lifecycle_status'] ?? ''),
            'event_date' => $row['event_date'] !== null ? (string) $row['event_date'] : null,
            'games' => (int) ($row['games'] ?? 0),
            'entrants' => (int) ($row['entrants'] ?? 0),
        ];
    }
    $res->free();

    return $out;
}

/**
 * Delete unfinalized generated tournament ground (Case A). No present re-project.
 *
 * @return array{
 *   ok:bool,
 *   dry_run:bool,
 *   tournament_id:int,
 *   name:string,
 *   lifecycle_status:string,
 *   games_deleted:int,
 *   orphan_players_deleted:list<int>,
 *   error:string
 * }
 */
function amiga_delete_unfinalized_tournament(mysqli $con, int $tournamentId, bool $dryRun = false): array
{
    $fail = static function (string $msg) use ($tournamentId, $dryRun): array {
        return [
            'ok' => false,
            'dry_run' => $dryRun,
            'tournament_id' => $tournamentId,
            'name' => '',
            'lifecycle_status' => '',
            'games_deleted' => 0,
            'orphan_players_deleted' => [],
            'error' => $msg,
        ];
    };

    if ($tournamentId <= 0) {
        return $fail('tournament_id must be positive.');
    }

    try {
        $row = amiga_case_a_load_tournament($con, $tournamentId);
        amiga_case_a_assert_eligible($con, $row);
    } catch (AmigaCaseADeleteException $e) {
        return $fail($e->getMessage());
    }

    $entrantIds = amiga_case_a_entrant_player_ids($con, $tournamentId);

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
        $orphanWould = [];
        foreach ($entrantIds as $playerId) {
            $stmtS = $con->prepare('SELECT player_source FROM amiga_players WHERE id = ? LIMIT 1');
            $source = '';
            if ($stmtS !== false) {
                $stmtS->bind_param('i', $playerId);
                if ($stmtS->execute()) {
                    $rs = $stmtS->get_result();
                    $pr = $rs ? $rs->fetch_assoc() : null;
                    $source = (string) ($pr['player_source'] ?? '');
                }
                $stmtS->close();
            }
            if ($source !== K2_AMIGA_PLAYER_SOURCE_LIVE_OPS) {
                continue;
            }
            if (k2_amiga_player_count_entrant_links_excluding($con, $playerId, $tournamentId) > 0) {
                continue;
            }
            $stmtG = $con->prepare(
                'SELECT COUNT(*) AS n FROM amiga_games '
                . 'WHERE (player_a_id = ? OR player_b_id = ?) AND (tournament_id IS NULL OR tournament_id <> ?)'
            );
            $otherGames = 0;
            if ($stmtG !== false) {
                $stmtG->bind_param('iii', $playerId, $playerId, $tournamentId);
                if ($stmtG->execute()) {
                    $rg = $stmtG->get_result();
                    $rr = $rg ? $rg->fetch_assoc() : null;
                    $otherGames = (int) ($rr['n'] ?? 0);
                }
                $stmtG->close();
            }
            if ($otherGames === 0) {
                $orphanWould[] = $playerId;
            }
        }

        return [
            'ok' => true,
            'dry_run' => true,
            'tournament_id' => $tournamentId,
            'name' => (string) $row['name'],
            'lifecycle_status' => (string) $row['lifecycle_status'],
            'games_deleted' => $gamesDeleted,
            'orphan_players_deleted' => $orphanWould,
            'error' => '',
        ];
    }

    $startedTx = false;
    if (!$con->begin_transaction()) {
        return $fail('begin_transaction failed: ' . $con->error);
    }
    $startedTx = true;

    try {
        // amiga_games has no ON DELETE CASCADE from tournaments — wipe first (ratings cascade from games).
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

        // Cascades: entrants, stages→fixtures/stage_players/scoring, finish_override, standings, catalog_stats, …
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
    } catch (Throwable $e) {
        if ($startedTx) {
            $con->rollback();
        }

        return $fail($e->getMessage());
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
        'lifecycle_status' => (string) $row['lifecycle_status'],
        'games_deleted' => $gamesDeleted,
        'orphan_players_deleted' => $orphans,
        'error' => '',
    ];
}