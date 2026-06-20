<?php
/**
 * Reopen and rebuild-forward refinalize (contract § 6.3).
 *
 * Mirrors scripts/amiga/refinalize.py.
 */
declare(strict_types=1);

require_once __DIR__ . '/finalize_tournament.php';

/**
 * @return list<int>
 */
function amiga_ops_tournament_ids_for_replay(mysqli $con): array
{
    $sql = 'SELECT t.id FROM tournaments t '
        . 'INNER JOIN amiga_games g ON g.tournament_id = t.id '
        . 'GROUP BY t.id, t.event_date, t.chrono '
        . 'ORDER BY t.event_date ASC, t.chrono ASC, t.id ASC';
    $res = $con->query($sql);
    if ($res === false) {
        throw new RuntimeException('tournament ids for replay: ' . $con->error);
    }
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['id'];
    }
    $res->free();

    return $ids;
}

/**
 * @return array{all: list<int>, before: list<int>, from: list<int>}
 */
function amiga_ops_tournaments_from_split(mysqli $con, int $tournamentId): array
{
    $allIds = amiga_ops_tournament_ids_for_replay($con);
    $pos = array_search($tournamentId, $allIds, true);
    if ($pos === false) {
        throw new AmigaTournamentNotFoundException(
            "tournament_id={$tournamentId} not found or has no games in replay order"
        );
    }

    return [
        'all' => $allIds,
        'before' => array_slice($allIds, 0, $pos),
        'from' => array_slice($allIds, $pos),
    ];
}

/**
 * @param list<int> $tournamentIds
 */
function amiga_ops_reopen_tournaments_batch(mysqli $con, array $tournamentIds): void
{
    if ($tournamentIds === []) {
        return;
    }
    $idList = implode(',', array_map('intval', $tournamentIds));
    if (!$con->query("DELETE FROM amiga_player_event_snapshots WHERE tournament_id IN ({$idList})")) {
        throw new RuntimeException('DELETE event snapshots batch: ' . $con->error);
    }
    if (!$con->query(
        "DELETE FROM amiga_player_matchup_at_event WHERE as_of_tournament_id IN ({$idList})"
    )) {
        throw new RuntimeException('DELETE matchup at-event batch: ' . $con->error);
    }
    $sql = 'DELETE r FROM amiga_game_ratings r '
        . 'INNER JOIN amiga_games g ON g.id = r.game_id '
        . "WHERE g.tournament_id IN ({$idList})";
    if (!$con->query($sql)) {
        throw new RuntimeException('DELETE game ratings batch: ' . $con->error);
    }
    $currentSql = 'DELETE c FROM amiga_player_current c '
        . 'WHERE c.player_id IN ('
        . 'SELECT player_id FROM ('
        . "SELECT player_a_id AS player_id FROM amiga_games WHERE tournament_id IN ({$idList}) "
        . 'UNION '
        . "SELECT player_b_id AS player_id FROM amiga_games WHERE tournament_id IN ({$idList})"
        . ') roster)';
    if (!$con->query($currentSql)) {
        throw new RuntimeException('DELETE player current batch: ' . $con->error);
    }
    if (!$con->query(
        "UPDATE tournaments SET rating_finalized = 0, rating_finalized_at = NULL WHERE id IN ({$idList})"
    )) {
        throw new RuntimeException('UPDATE tournaments reopen batch: ' . $con->error);
    }
}

/**
 * @return array{tournament_id: int, name?: string, reopened: bool, skipped?: bool, dry_run?: bool}
 */
function amiga_ops_reopen_tournament(mysqli $con, int $tournamentId, bool $dryRun = false): array
{
    $tour = amiga_ops_load_tournament_row($con, $tournamentId);
    if ((int) ($tour['rating_finalized'] ?? 0) !== 1) {
        amiga_ops_log("reopen-tournament: id={$tournamentId} not rating_finalized; no-op");

        return ['tournament_id' => $tournamentId, 'reopened' => false, 'skipped' => true];
    }

    amiga_ops_log('reopen-tournament: id=' . $tournamentId . ' name=' . $tour['name'] . ($dryRun ? ' (dry-run)' : ''));
    if ($dryRun) {
        return ['tournament_id' => $tournamentId, 'reopened' => true, 'dry_run' => true];
    }

    amiga_ops_reopen_tournaments_batch($con, [$tournamentId]);

    return [
        'tournament_id' => $tournamentId,
        'name' => (string) $tour['name'],
        'reopened' => true,
    ];
}

/**
 * @return array{
 *   tournament_id: int,
 *   name?: string,
 *   before_tournaments?: int,
 *   from_tournaments?: int,
 *   games_finalized?: int,
 *   rating_events?: int,
 *   dry_run?: bool
 * }
 */
function amiga_ops_refinalize_from(mysqli $con, int $tournamentId, bool $dryRun = false): array
{
    $split = amiga_ops_tournaments_from_split($con, $tournamentId);
    $tour = amiga_ops_load_tournament_row($con, $tournamentId);
    $beforeIds = $split['before'];
    $fromIds = $split['from'];

    amiga_ops_log(
        'refinalize-from: id=' . $tournamentId
        . ' name=' . $tour['name']
        . ' before=' . count($beforeIds)
        . ' from=' . count($fromIds)
        . ($dryRun ? ' (dry-run)' : '')
    );

    if ($dryRun) {
        return [
            'tournament_id' => $tournamentId,
            'before_tournaments' => count($beforeIds),
            'from_tournaments' => count($fromIds),
            'dry_run' => true,
        ];
    }

    amiga_ops_reopen_tournaments_batch($con, $fromIds);

    $players = amiga_ops_load_player_states_for_finalize($con);
    $matchups = new AmigaMatchupCumulative();
    amiga_ops_warm_state_through_finalized($con, $beforeIds, $matchups, $players);

    $gamesTotal = 0;
    $eventsTotal = 0;
    foreach ($fromIds as $tid) {
        $result = amiga_finalize_tournament($con, $tid, false, $matchups, $players);
        if (!empty($result['skipped'])) {
            continue;
        }
        $gamesTotal += (int) ($result['games'] ?? 0);
        $eventsTotal += (int) ($result['rating_events'] ?? 0);
        $games = amiga_ops_load_tournament_games_for_finalize($con, $tid);
        if ($games !== []) {
            amiga_ops_standings_apply_game($con, $games[array_key_last($games)]);
        }
    }

    amiga_ops_log(
        "refinalize-from complete: id={$tournamentId} games={$gamesTotal} events={$eventsTotal}"
    );

    return [
        'tournament_id' => $tournamentId,
        'name' => (string) $tour['name'],
        'before_tournaments' => count($beforeIds),
        'from_tournaments' => count($fromIds),
        'games_finalized' => $gamesTotal,
        'rating_events' => $eventsTotal,
    ];
}
