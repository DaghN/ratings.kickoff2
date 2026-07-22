<?php
/**
 * project-present-at — rebuild present tables from timeline at cutoff N (L5 Case B/C).
 *
 * Verb: project-present-at (amiga-live-ops-platform.md §7.4).
 * Mirrors verify oracles: present = latest timeline row ≤ cutoff (event_date, chrono, id).
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/amiga_event_snapshot_persist.php';
require_once __DIR__ . '/../includes/amiga_matchup_persist.php';
require_once __DIR__ . '/../includes/amiga_realm_snapshot_lib.php';
require_once __DIR__ . '/../includes/amiga_community_stats_lib.php';
require_once __DIR__ . '/../includes/amiga_slice_persist_lib.php';
require_once __DIR__ . '/../includes/amiga_country_slice_persist_lib.php';
require_once __DIR__ . '/../includes/amiga_wc_hof_lib.php';

class AmigaProjectPresentException extends RuntimeException
{
}

/**
 * @return array{event_date: string, chrono: float|int, tournament_id: int, tournament_name: string}
 */
function amiga_ops_project_present_load_cutoff(mysqli $con, int $tournamentId): array
{
    return amiga_realm_load_cutoff($con, $tournamentId);
}

/**
 * Inclusive chrono cutoff on (event_date, event_chrono, tournament_id).
 */
function amiga_ops_project_present_tuple_cutoff_sql(string $dateCol, string $chronoCol, string $idCol): string
{
    return "({$dateCol}, {$chronoCol}, {$idCol}) <= (?, ?, ?)";
}

/**
 * Rebuild all present projections at cutoff tournament N.
 *
 * Prefer {@see amiga_ops_project_present_at_phase()} on HTTP (gateway ~30s).
 *
 * @return array{
 *   cutoff_tournament_id:int,
 *   cutoff_name:string,
 *   player_current:int,
 *   matchup_summary:int,
 *   player_slice_totals:int,
 *   country_slice_totals:int,
 *   generalstats:bool,
 *   community_stats:bool,
 *   wc_hof_present:bool,
 *   phase:string,
 *   next_phase:?string
 * }
 */
function amiga_ops_project_present_at(mysqli $con, int $cutoffTournamentId): array
{
    return amiga_ops_project_present_at_phase($con, $cutoffTournamentId, 'all');
}

/**
 * Phased present rebuild (HTTP-safe under ~30s gateway).
 *
 * Phases: player_current → matchups → rest. Pass phase=all for CLI / local.
 *
 * @param 'all'|'player_current'|'matchups'|'rest' $phase
 * @return array{
 *   cutoff_tournament_id:int,
 *   cutoff_name:string,
 *   player_current:int,
 *   matchup_summary:int,
 *   player_slice_totals:int,
 *   country_slice_totals:int,
 *   generalstats:bool,
 *   community_stats:bool,
 *   wc_hof_present:bool,
 *   phase:string,
 *   next_phase:?string
 * }
 */
function amiga_ops_project_present_at_phase(mysqli $con, int $cutoffTournamentId, string $phase): array
{
    if ($cutoffTournamentId <= 0) {
        throw new AmigaProjectPresentException('cutoff tournament_id must be positive');
    }
    if (!in_array($phase, ['all', 'player_current', 'matchups', 'rest'], true)) {
        throw new AmigaProjectPresentException('unknown project phase: ' . $phase);
    }
    $cutoff = amiga_ops_project_present_load_cutoff($con, $cutoffTournamentId);

    $playerCurrent = 0;
    $matchupRows = 0;
    $playerSlice = 0;
    $countrySlice = 0;
    $generalOk = false;
    $communityOk = false;
    $wcHofOk = false;
    $nextPhase = null;

    if ($phase === 'all' || $phase === 'player_current') {
        $playerCurrent = amiga_ops_project_player_current_at($con, $cutoff);
        if ($phase === 'player_current') {
            $nextPhase = 'matchups';
        }
    }
    if ($phase === 'all' || $phase === 'matchups') {
        $matchupRows = amiga_ops_project_matchup_summary_at($con, $cutoff);
        if ($phase === 'matchups') {
            $nextPhase = 'rest';
        }
    }
    if ($phase === 'all' || $phase === 'rest') {
        $generalOk = amiga_ops_project_generalstats_at($con, $cutoff);
        $communityOk = amiga_ops_project_community_headline_at($con, $cutoff);
        $playerSlice = amiga_ops_project_player_slice_totals_at($con, $cutoff);
        $countrySlice = amiga_ops_project_country_slice_totals_at($con, $cutoff);
        $wcHofOk = amiga_ops_project_wc_hof_present_at($con, $cutoff);
    }

    return [
        'cutoff_tournament_id' => (int) $cutoff['tournament_id'],
        'cutoff_name' => (string) $cutoff['tournament_name'],
        'player_current' => $playerCurrent,
        'matchup_summary' => $matchupRows,
        'player_slice_totals' => $playerSlice,
        'country_slice_totals' => $countrySlice,
        'generalstats' => $generalOk,
        'community_stats' => $communityOk,
        'wc_hof_present' => $wcHofOk,
        'phase' => $phase,
        'next_phase' => $nextPhase,
    ];
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 */
function amiga_ops_project_player_current_at(mysqli $con, array $cutoff): int
{
    if (!$con->query('DELETE FROM amiga_player_current')) {
        throw new AmigaProjectPresentException('DELETE amiga_player_current: ' . $con->error);
    }

    $tuple = amiga_ops_project_present_tuple_cutoff_sql(
        's_inner.event_date',
        's_inner.event_chrono',
        's_inner.tournament_id'
    );
    $sql = "
        SELECT s.*
        FROM (
            SELECT s_inner.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY s_inner.player_id
                       ORDER BY s_inner.event_date DESC, s_inner.event_chrono DESC,
                                s_inner.tournament_id DESC
                   ) AS rn
            FROM amiga_player_event_snapshots s_inner
            WHERE {$tuple}
        ) s
        WHERE s.rn = 1
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new AmigaProjectPresentException('prepare latest snapshots: ' . $con->error);
    }
    $eventDate = (string) $cutoff['event_date'];
    $chrono = (float) $cutoff['chrono'];
    $tid = (int) $cutoff['tournament_id'];
    $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new AmigaProjectPresentException('execute latest snapshots: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res === false) {
        $stmt->close();
        throw new AmigaProjectPresentException('get_result latest snapshots: ' . $stmt->error);
    }

    $written = 0;
    $batch = [];
    $batchSize = 50;
    while ($snap = $res->fetch_assoc()) {
        unset($snap['rn']);
        $current = amiga_ops_current_row_from_snapshot($snap);
        // Peak elo lives on at-event / current; ensure keys exist for overlay step.
        if (!array_key_exists('peak_elo_rank', $current)) {
            $current['peak_elo_rank'] = $snap['peak_elo_rank'] ?? null;
        }
        if (!array_key_exists('peak_elo_rank_tournament_id', $current)) {
            $current['peak_elo_rank_tournament_id'] = $snap['peak_elo_rank_tournament_id'] ?? null;
        }
        $batch[] = $current;
        if (count($batch) >= $batchSize) {
            amiga_ops_insert_rows_batch($con, 'amiga_player_current', $batch);
            $written += count($batch);
            $batch = [];
        }
    }
    if ($batch !== []) {
        amiga_ops_insert_rows_batch($con, 'amiga_player_current', $batch);
        $written += count($batch);
    }
    $stmt->close();

    // elo_rank + peak_elo_rank: overlay from amiga_player_elo_rank_at_event (verify-event-snapshots).
    amiga_ops_project_overlay_elo_ranks_at($con, $cutoff);
    // Inverse counts: NOT snapshot authority (Jul 15 — ghosts). Rebuild from pointer oracle
    // on the just-projected current rows (same as verify-inverse-count-changelog present check).
    amiga_ops_project_overlay_inverse_counts_at($con, $cutoff);

    return $written;
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 */
function amiga_ops_project_overlay_elo_ranks_at(mysqli $con, array $cutoff): void
{
    $tuple = amiga_ops_project_present_tuple_cutoff_sql(
        'er.event_date',
        'er.event_chrono',
        'er.tournament_id'
    );
    $sql = "
        SELECT er.player_id, er.elo_rank, er.peak_elo_rank, er.peak_elo_rank_tournament_id
        FROM (
            SELECT er.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY er.player_id
                       ORDER BY er.event_date DESC, er.event_chrono DESC, er.tournament_id DESC
                   ) AS rn
            FROM amiga_player_elo_rank_at_event er
            WHERE {$tuple}
        ) er
        WHERE er.rn = 1
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new AmigaProjectPresentException('prepare elo overlay: ' . $con->error);
    }
    $eventDate = (string) $cutoff['event_date'];
    $chrono = (float) $cutoff['chrono'];
    $tid = (int) $cutoff['tournament_id'];
    $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new AmigaProjectPresentException('execute elo overlay: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res === false) {
        $stmt->close();
        throw new AmigaProjectPresentException('get_result elo overlay');
    }

    $upd = $con->prepare(
        'UPDATE amiga_player_current SET elo_rank = ?, peak_elo_rank = ?, '
        . 'peak_elo_rank_tournament_id = ? WHERE player_id = ?'
    );
    if ($upd === false) {
        $stmt->close();
        throw new AmigaProjectPresentException('prepare elo update: ' . $con->error);
    }
    while ($row = $res->fetch_assoc()) {
        $pid = (int) $row['player_id'];
        $elo = $row['elo_rank'] !== null ? (int) $row['elo_rank'] : null;
        $peak = $row['peak_elo_rank'] !== null ? (int) $row['peak_elo_rank'] : null;
        $peakTid = $row['peak_elo_rank_tournament_id'] !== null
            ? (int) $row['peak_elo_rank_tournament_id'] : null;
        $upd->bind_param('iiii', $elo, $peak, $peakTid, $pid);
        // mysqli cannot bind NULL ints cleanly via 'i' — use nullable string path when needed.
        if ($elo === null || $peak === null || $peakTid === null) {
            $sqlOne = 'UPDATE amiga_player_current SET elo_rank = '
                . ($elo === null ? 'NULL' : (string) $elo)
                . ', peak_elo_rank = ' . ($peak === null ? 'NULL' : (string) $peak)
                . ', peak_elo_rank_tournament_id = ' . ($peakTid === null ? 'NULL' : (string) $peakTid)
                . ' WHERE player_id = ' . $pid;
            if (!$con->query($sqlOne)) {
                throw new AmigaProjectPresentException('elo null update: ' . $con->error);
            }
            continue;
        }
        if (!$upd->execute()) {
            throw new AmigaProjectPresentException('execute elo update: ' . $upd->error);
        }
    }
    $upd->close();
    $stmt->close();
}

/**
 * Overlay inverse-count columns from pointer oracle on projected current.
 *
 * Jul 15 policy (amiga-player-inverse-count-timeline-policy.md): these four
 * counts change when *someone else* plays (credit transfer). Sparse snapshots
 * only write participants, so hero snapshot columns go stale for ghosts.
 * Authority for present rebuild is COUNT of players whose pointer names the
 * hero — same oracle as verify-inverse-count-changelog present check.
 *
 * Do NOT:
 * - leave snapshot-projected inverse columns as present authority
 * - zero-fill then refill from sparse changelog when changelog may be empty
 *   (export packs / Case B clears can leave 0 rows → wipe present)
 *
 * Changelog remains the TT/LB hot-path store when populated; present reproject
 * uses the pointer recount so Case B works even if changelog data is missing.
 *
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 */
function amiga_ops_project_overlay_inverse_counts_at(mysqli $con, array $cutoff): void
{
    unset($cutoff); // present overlay uses projected current pointers at cutoff

    $metrics = [
        ['count' => 'MostGoalsScoredCulprits', 'ptr' => 'MostGoalsScoredVictimID'],
        ['count' => 'BiggestWinCulprits', 'ptr' => 'BiggestWinVictimID'],
        ['count' => 'MostGoalsConcededVictims', 'ptr' => 'MostGoalsConcededCulpritID'],
        ['count' => 'BiggestLossVictims', 'ptr' => 'BiggestLossCulpritID'],
    ];

    // Reset, then apply pointer recount (heroes with no pointers stay 0).
    if (!$con->query(
        'UPDATE amiga_player_current SET '
        . 'MostGoalsScoredCulprits = 0, BiggestWinCulprits = 0, '
        . 'MostGoalsConcededVictims = 0, BiggestLossVictims = 0'
    )) {
        throw new AmigaProjectPresentException('reset inverse counts: ' . $con->error);
    }

    foreach ($metrics as $m) {
        $col = $m['count'];
        $ptr = $m['ptr'];
        $sql = "
            UPDATE amiga_player_current c
            INNER JOIN (
                SELECT `{$ptr}` AS hero_id, COUNT(*) AS n
                FROM amiga_player_current
                WHERE `{$ptr}` IS NOT NULL AND `{$ptr}` > 0
                GROUP BY `{$ptr}`
            ) inv ON inv.hero_id = c.player_id
            SET c.`{$col}` = inv.n
        ";
        if (!$con->query($sql)) {
            throw new AmigaProjectPresentException("pointer inverse {$col}: " . $con->error);
        }
    }
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int, tournament_name?: string} $cutoff
 */
function amiga_ops_project_generalstats_at(mysqli $con, array $cutoff): bool
{
    $tid = (int) $cutoff['tournament_id'];
    $stmt = $con->prepare('SELECT * FROM amiga_realm_snapshots WHERE tournament_id = ? LIMIT 1');
    if ($stmt === false) {
        throw new AmigaProjectPresentException('prepare realm snapshot: ' . $con->error);
    }
    $stmt->bind_param('i', $tid);
    if (!$stmt->execute()) {
        throw new AmigaProjectPresentException('execute realm snapshot: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        // Fallback: latest realm snapshot ≤ cutoff.
        $tuple = amiga_ops_project_present_tuple_cutoff_sql(
            's.event_date',
            's.event_chrono',
            's.tournament_id'
        );
        $sql = "
            SELECT s.* FROM amiga_realm_snapshots s
            WHERE {$tuple}
            ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC
            LIMIT 1
        ";
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new AmigaProjectPresentException('prepare latest realm: ' . $con->error);
        }
        $eventDate = (string) $cutoff['event_date'];
        $chrono = (float) $cutoff['chrono'];
        $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
        if (!$stmt->execute()) {
            throw new AmigaProjectPresentException('execute latest realm: ' . $stmt->error);
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
    if ($row === null) {
        return false;
    }

    $timeline = ['tournament_id', 'event_date', 'event_chrono', 'tournament_name', 'finalized_at'];
    $payload = array_diff_key($row, array_flip($timeline));
    if ($payload === []) {
        return false;
    }
    if (!$con->query('INSERT IGNORE INTO amiga_generalstats (id) VALUES (1)')) {
        throw new AmigaProjectPresentException('seed generalstats: ' . $con->error);
    }
    $sets = [];
    $values = [];
    foreach ($payload as $col => $value) {
        $sets[] = '`' . $col . '` = ?';
        $values[] = $value;
    }
    $gstSql = 'UPDATE amiga_generalstats SET ' . implode(', ', $sets) . ' WHERE id = 1';
    amiga_realm_execute_bound($con, $gstSql, $values);

    return true;
}

/**
 * Rebuild matchup present from latest at_event ≤ cutoff.
 *
 * Orphan filter = pairs that still exist in amiga_games (same rule as verify).
 * Use INNER JOIN to a once-materialized directed pair set — not correlated EXISTS
 * (MariaDB nested-loop EXISTS blew the ~30s gateway on staging).
 *
 * DELETE + INSERT run in one transaction so a killed request rolls back.
 *
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 */
function amiga_ops_project_matchup_summary_at(mysqli $con, array $cutoff): int
{
    $cols = amiga_matchup_pair_columns();
    $colList = 'player_id, opponent_id, `' . implode('`, `', $cols) . '`';
    $selectCols = 'e.player_id, e.opponent_id, e.`' . implode('`, e.`', $cols) . '`';
    $tuple = amiga_ops_project_present_tuple_cutoff_sql(
        'event_date',
        'event_chrono',
        'as_of_tournament_id'
    );

    $sql = "
        INSERT INTO amiga_player_matchup_summary ({$colList})
        SELECT {$selectCols}
        FROM (
            SELECT mae.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY mae.player_id, mae.opponent_id
                       ORDER BY mae.event_date DESC, mae.event_chrono DESC,
                                mae.as_of_tournament_id DESC
                   ) AS rn
            FROM amiga_player_matchup_at_event mae
            WHERE {$tuple}
        ) e
        INNER JOIN (
            SELECT player_a_id AS player_id, player_b_id AS opponent_id
            FROM amiga_games
            UNION
            SELECT player_b_id AS player_id, player_a_id AS opponent_id
            FROM amiga_games
        ) pairs
            ON pairs.player_id = e.player_id
           AND pairs.opponent_id = e.opponent_id
        WHERE e.rn = 1
    ";

    if (!$con->begin_transaction()) {
        throw new AmigaProjectPresentException('begin matchup project txn: ' . $con->error);
    }
    try {
        if (!$con->query('DELETE FROM amiga_player_matchup_summary')) {
            throw new AmigaProjectPresentException('DELETE matchup_summary: ' . $con->error);
        }
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new AmigaProjectPresentException('prepare matchup project: ' . $con->error);
        }
        $eventDate = (string) $cutoff['event_date'];
        $chrono = (float) $cutoff['chrono'];
        $tid = (int) $cutoff['tournament_id'];
        $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new AmigaProjectPresentException('execute matchup project: ' . $err);
        }
        $written = (int) $stmt->affected_rows;
        $stmt->close();
        if (!$con->commit()) {
            throw new AmigaProjectPresentException('commit matchup project: ' . $con->error);
        }
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }

    return $written;
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 */
function amiga_ops_project_community_headline_at(mysqli $con, array $cutoff): bool
{
    $tid = (int) $cutoff['tournament_id'];
    $stmt = $con->prepare(
        'SELECT * FROM amiga_community_stats_snapshots WHERE tournament_id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new AmigaProjectPresentException('prepare community snapshot: ' . $con->error);
    }
    $stmt->bind_param('i', $tid);
    if (!$stmt->execute()) {
        throw new AmigaProjectPresentException('execute community snapshot: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        $tuple = amiga_ops_project_present_tuple_cutoff_sql(
            's.event_date',
            's.event_chrono',
            's.tournament_id'
        );
        $sql = "
            SELECT s.* FROM amiga_community_stats_snapshots s
            WHERE {$tuple}
            ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC
            LIMIT 1
        ";
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new AmigaProjectPresentException('prepare latest community: ' . $con->error);
        }
        $eventDate = (string) $cutoff['event_date'];
        $chrono = (float) $cutoff['chrono'];
        $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
        if (!$stmt->execute()) {
            throw new AmigaProjectPresentException('execute latest community: ' . $stmt->error);
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
    if ($row === null) {
        return false;
    }

    $headlineCols = amiga_community_headline_column_names();
    if (!$con->query('INSERT IGNORE INTO amiga_community_stats (id) VALUES (1)')) {
        throw new AmigaProjectPresentException('seed community_stats: ' . $con->error);
    }
    $setParts = [];
    $hbind = [];
    $htypes = '';
    $intCols = [
        'NumberOfPlayers',
        'GamesPlayed',
        'NumberOfDecidedGames',
        'NumberOfDraws',
        'GoalsScored',
        'DoubleDigits',
        'CleanSheets',
        'TournamentsFinalized',
        'DistinctHostCountries',
        'WcGamesPlayed',
        'DistinctOpponentPairs',
        'PlayersDebuted',
    ];
    foreach ($headlineCols as $col) {
        $setParts[] = "`{$col}` = ?";
        $val = $row[$col] ?? null;
        if (in_array($col, $intCols, true)) {
            $htypes .= 'i';
            $hbind[] = $val === null ? null : (int) $val;
        } else {
            $htypes .= 'd';
            $hbind[] = $val === null ? null : (float) $val;
        }
    }
    $updateSql = 'UPDATE amiga_community_stats SET ' . implode(', ', $setParts) . ' WHERE id = 1';
    $ustmt = $con->prepare($updateSql);
    if ($ustmt === false) {
        throw new AmigaProjectPresentException('prepare community present: ' . $con->error);
    }
    $ustmt->bind_param($htypes, ...$hbind);
    if (!$ustmt->execute()) {
        throw new AmigaProjectPresentException('execute community present: ' . $ustmt->error);
    }
    $ustmt->close();

    return true;
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 */
function amiga_ops_project_player_slice_totals_at(mysqli $con, array $cutoff): int
{
    $sliceKey = amiga_slice_key_world_cup();
    $del = $con->prepare('DELETE FROM amiga_player_slice_totals WHERE slice_key = ?');
    if ($del === false) {
        throw new AmigaProjectPresentException('prepare delete player slice totals: ' . $con->error);
    }
    $del->bind_param('s', $sliceKey);
    if (!$del->execute()) {
        throw new AmigaProjectPresentException('execute delete player slice totals: ' . $del->error);
    }
    $del->close();

    $tuple = amiga_ops_project_present_tuple_cutoff_sql(
        's.event_date',
        's.event_chrono',
        's.as_of_tournament_id'
    );
    $sql = "
        SELECT s.*
        FROM (
            SELECT s.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY s.player_id
                       ORDER BY s.event_date DESC, s.event_chrono DESC, s.as_of_tournament_id DESC
                   ) AS rn
            FROM amiga_player_slice_at_event s
            WHERE s.slice_key = ?
              AND {$tuple}
        ) s
        WHERE s.rn = 1
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new AmigaProjectPresentException('prepare player slice latest: ' . $con->error);
    }
    $eventDate = (string) $cutoff['event_date'];
    $chrono = (float) $cutoff['chrono'];
    $tid = (int) $cutoff['tournament_id'];
    $stmt->bind_param('ssdi', $sliceKey, $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new AmigaProjectPresentException('execute player slice latest: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res === false) {
        $stmt->close();
        throw new AmigaProjectPresentException('get_result player slice');
    }
    $written = 0;
    while ($row = $res->fetch_assoc()) {
        unset($row['rn'], $row['as_of_tournament_id'], $row['event_date'], $row['event_chrono']);
        amiga_slice_upsert_row($con, 'amiga_player_slice_totals', $row, ['player_id', 'slice_key']);
        $written++;
    }
    $stmt->close();

    return $written;
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 */
function amiga_ops_project_country_slice_totals_at(mysqli $con, array $cutoff): int
{
    $sliceKey = amiga_slice_key_world_cup();
    $del = $con->prepare('DELETE FROM amiga_country_slice_totals WHERE slice_key = ?');
    if ($del === false) {
        throw new AmigaProjectPresentException('prepare delete country slice totals: ' . $con->error);
    }
    $del->bind_param('s', $sliceKey);
    if (!$del->execute()) {
        throw new AmigaProjectPresentException('execute delete country slice totals: ' . $del->error);
    }
    $del->close();

    $tuple = amiga_ops_project_present_tuple_cutoff_sql(
        's.event_date',
        's.event_chrono',
        's.as_of_tournament_id'
    );
    $sql = "
        SELECT s.*
        FROM (
            SELECT s.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY s.country_token
                       ORDER BY s.event_date DESC, s.event_chrono DESC, s.as_of_tournament_id DESC
                   ) AS rn
            FROM amiga_country_slice_at_event s
            WHERE s.slice_key = ?
              AND {$tuple}
        ) s
        WHERE s.rn = 1
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new AmigaProjectPresentException('prepare country slice latest: ' . $con->error);
    }
    $eventDate = (string) $cutoff['event_date'];
    $chrono = (float) $cutoff['chrono'];
    $tid = (int) $cutoff['tournament_id'];
    $stmt->bind_param('ssdi', $sliceKey, $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new AmigaProjectPresentException('execute country slice latest: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res === false) {
        $stmt->close();
        throw new AmigaProjectPresentException('get_result country slice');
    }
    $written = 0;
    while ($row = $res->fetch_assoc()) {
        unset($row['rn'], $row['as_of_tournament_id'], $row['event_date'], $row['event_chrono']);
        amiga_slice_upsert_row(
            $con,
            'amiga_country_slice_totals',
            $row,
            ['country_token', 'slice_key']
        );
        $written++;
    }
    $stmt->close();

    return $written;
}

/**
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 */
function amiga_ops_project_wc_hof_present_at(mysqli $con, array $cutoff): bool
{
    $tid = (int) $cutoff['tournament_id'];
    $stmt = $con->prepare('SELECT * FROM amiga_wc_hof_snapshots WHERE tournament_id = ? LIMIT 1');
    if ($stmt === false) {
        throw new AmigaProjectPresentException('prepare wc hof snapshot: ' . $con->error);
    }
    $stmt->bind_param('i', $tid);
    if (!$stmt->execute()) {
        throw new AmigaProjectPresentException('execute wc hof snapshot: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if ($row === null) {
        $tuple = amiga_ops_project_present_tuple_cutoff_sql(
            's.event_date',
            's.event_chrono',
            's.tournament_id'
        );
        $sql = "
            SELECT s.* FROM amiga_wc_hof_snapshots s
            WHERE {$tuple}
            ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC
            LIMIT 1
        ";
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new AmigaProjectPresentException('prepare latest wc hof: ' . $con->error);
        }
        $eventDate = (string) $cutoff['event_date'];
        $chrono = (float) $cutoff['chrono'];
        $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
        if (!$stmt->execute()) {
            throw new AmigaProjectPresentException('execute latest wc hof: ' . $stmt->error);
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
    if ($row === null) {
        return false;
    }

    $payloadCols = amiga_wc_hof_payload_columns();
    $presentRow = ['id' => 1];
    foreach ($payloadCols as $col) {
        $presentRow[$col] = $row[$col] ?? null;
    }
    amiga_wc_hof_upsert(
        $con,
        'amiga_wc_hof_present',
        array_merge(['id'], $payloadCols),
        $presentRow,
        ['id']
    );

    return true;
}

/**
 * Read-only diagnosis for present re-project (no DELETE/INSERT).
 *
 * Phases (HTTP-safe):
 * - counts — tip + row counts + inverse pointer sample (fast)
 * - time_no_exists — SELECT COUNT of latest matchup pairs (window only)
 * - time_exists — same + EXISTS on amiga_games (phase matchups shape)
 *
 * Timing phases set MySQL MAX_EXECUTION_TIME (ms) and MariaDB max_statement_time (sec)
 * so a slow probe returns an error flash instead of a bare gateway 500.
 *
 * @param 'counts'|'time_no_exists'|'time_exists' $phase
 * @return array{
 *   tip_id:int,
 *   tip_name:string,
 *   phase:string,
 *   next_phase:?string,
 *   counts:array<string,int>,
 *   timings_ms:array<string,int|null>,
 *   notes:list<string>,
 *   ok:bool
 * }
 */
function amiga_ops_diagnose_project_present(
    mysqli $con,
    string $phase = 'counts',
    int $maxExecutionTimeMs = 8000,
): array {
    if (!in_array($phase, ['counts', 'time_no_exists', 'time_exists'], true)) {
        throw new AmigaProjectPresentException('unknown diagnose phase: ' . $phase);
    }

    $resTip = $con->query(
        'SELECT id, name FROM tournaments '
        . 'WHERE COALESCE(rating_finalized, 0) = 1 '
        . 'ORDER BY event_date DESC, chrono DESC, id DESC LIMIT 1'
    );
    if ($resTip === false) {
        throw new AmigaProjectPresentException('tip query: ' . $con->error);
    }
    $tipRow = $resTip->fetch_assoc();
    $resTip->free();
    if ($tipRow === null) {
        throw new AmigaProjectPresentException('No finalized tip');
    }
    $cutoff = amiga_ops_project_present_load_cutoff($con, (int) $tipRow['id']);
    $eventDate = (string) $cutoff['event_date'];
    $chrono = (float) $cutoff['chrono'];
    $tid = (int) $cutoff['tournament_id'];

    $counts = [];
    $timings = [
        'matchup_latest_pairs_no_exists_ms' => null,
        'matchup_latest_pairs_with_exists_ms' => null,
    ];
    $notes = [];
    $nextPhase = null;

    if ($phase === 'counts') {
        $countSql = [
            'player_current' => 'SELECT COUNT(*) AS n FROM amiga_player_current',
            'matchup_summary' => 'SELECT COUNT(*) AS n FROM amiga_player_matchup_summary',
            'matchup_at_event' => 'SELECT COUNT(*) AS n FROM amiga_player_matchup_at_event',
            'games' => 'SELECT COUNT(*) AS n FROM amiga_games',
            'inverse_changelog' => 'SELECT COUNT(*) AS n FROM amiga_player_inverse_count_at_event',
        ];
        foreach ($countSql as $key => $sql) {
            $res = $con->query($sql);
            if ($res === false) {
                throw new AmigaProjectPresentException("count {$key}: " . $con->error);
            }
            $counts[$key] = (int) ($res->fetch_assoc()['n'] ?? 0);
            $res->free();
        }

        $ptrDiffs = 0;
        $ptrSql = '
            SELECT COUNT(*) AS n FROM amiga_player_current c
            LEFT JOIN (
                SELECT MostGoalsScoredVictimID AS hero_id, COUNT(*) AS n
                FROM amiga_player_current
                WHERE MostGoalsScoredVictimID IS NOT NULL AND MostGoalsScoredVictimID > 0
                GROUP BY MostGoalsScoredVictimID
            ) inv ON inv.hero_id = c.player_id
            WHERE COALESCE(c.MostGoalsScoredCulprits, 0) <> COALESCE(inv.n, 0)
        ';
        $res = $con->query($ptrSql);
        if ($res !== false) {
            $ptrDiffs = (int) ($res->fetch_assoc()['n'] ?? 0);
            $res->free();
        }
        $counts['inverse_mgs_ptr_diffs'] = $ptrDiffs;

        if ($counts['matchup_summary'] === 0 && $counts['games'] > 0) {
            $notes[] = 'matchup_summary is EMPTY while games exist — phase matchups likely DELETE-committed then aborted before INSERT finished.';
        } elseif ($counts['matchup_summary'] > 0) {
            $notes[] = 'matchup_summary has rows — DELETE may not have run, or INSERT finished after browser 500, or prior good state remains.';
        }
        if ($ptrDiffs === 0) {
            $notes[] = 'MGS inverse present matches pointer oracle (phase player_current likely OK).';
        } else {
            $notes[] = "MGS inverse present vs pointer diffs={$ptrDiffs}.";
        }
        $notes[] = 'Counts-only phase done. Next optional: time_no_exists (window query).';
        $nextPhase = 'time_no_exists';
    }

    if ($phase === 'time_no_exists' || $phase === 'time_exists') {
        amiga_ops_diagnose_apply_statement_timeout($con, $maxExecutionTimeMs, $notes);

        $tuple = amiga_ops_project_present_tuple_cutoff_sql(
            'event_date',
            'event_chrono',
            'as_of_tournament_id'
        );
        $inner = "
            SELECT mae.player_id, mae.opponent_id,
                   ROW_NUMBER() OVER (
                       PARTITION BY mae.player_id, mae.opponent_id
                       ORDER BY mae.event_date DESC, mae.event_chrono DESC,
                                mae.as_of_tournament_id DESC
                   ) AS rn
            FROM amiga_player_matchup_at_event mae
            WHERE {$tuple}
        ";

        if ($phase === 'time_no_exists') {
            $sql = "SELECT COUNT(*) AS n FROM ({$inner}) e WHERE e.rn = 1";
            $timings['matchup_latest_pairs_no_exists_ms'] = amiga_ops_diagnose_timed_count(
                $con,
                $sql,
                $eventDate,
                $chrono,
                $tid,
                $notes,
                'no-EXISTS'
            );
            $nextPhase = 'time_exists';
        } else {
            $sql = "SELECT COUNT(*) AS n FROM ({$inner}) e WHERE e.rn = 1
                AND EXISTS (
                    SELECT 1 FROM amiga_games g
                    WHERE (g.player_a_id = e.player_id AND g.player_b_id = e.opponent_id)
                       OR (g.player_b_id = e.player_id AND g.player_a_id = e.opponent_id)
                )";
            $timings['matchup_latest_pairs_with_exists_ms'] = amiga_ops_diagnose_timed_count(
                $con,
                $sql,
                $eventDate,
                $chrono,
                $tid,
                $notes,
                'with-EXISTS'
            );
            $nextPhase = null;
            $notes[] = 'Timing phases done.';
        }

        $con->query('SET SESSION MAX_EXECUTION_TIME = 0');
        $con->query('SET SESSION max_statement_time = 0');
    }

    return [
        'tip_id' => $tid,
        'tip_name' => (string) $cutoff['tournament_name'],
        'phase' => $phase,
        'next_phase' => $nextPhase,
        'counts' => $counts,
        'timings_ms' => $timings,
        'notes' => $notes,
        'ok' => true,
    ];
}

/**
 * Best-effort statement timeout for MySQL (ms) and MariaDB (seconds).
 *
 * @param list<string> $notes
 */
function amiga_ops_diagnose_apply_statement_timeout(mysqli $con, int $maxExecutionTimeMs, array &$notes): void
{
    $limitMs = max(1000, $maxExecutionTimeMs);
    $limitSec = max(1, (int) ceil($limitMs / 1000));
    $okMysql = @$con->query('SET SESSION MAX_EXECUTION_TIME = ' . (int) $limitMs);
    $okMaria = @$con->query('SET SESSION max_statement_time = ' . (int) $limitSec);
    $notes[] = 'statement timeout request: MAX_EXECUTION_TIME=' . $limitMs . 'ms ('
        . ($okMysql ? 'ok' : 'fail') . '), max_statement_time=' . $limitSec . 's ('
        . ($okMaria ? 'ok' : 'fail') . ')';
    $ver = $con->query('SELECT VERSION() AS v');
    if ($ver) {
        $v = (string) ($ver->fetch_assoc()['v'] ?? '');
        $ver->free();
        $notes[] = 'server VERSION=' . $v;
    }
}

/**
 * @param list<string> $notes
 */
function amiga_ops_diagnose_timed_count(
    mysqli $con,
    string $sql,
    string $eventDate,
    float $chrono,
    int $tid,
    array &$notes,
    string $label,
): ?int {
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        $notes[] = "{$label}: prepare failed: " . $con->error;

        return null;
    }
    $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
    $t0 = microtime(true);
    $ok = $stmt->execute();
    $ms = (int) round((microtime(true) - $t0) * 1000);
    if (!$ok) {
        $err = $stmt->error !== '' ? $stmt->error : $con->error;
        $stmt->close();
        $notes[] = "{$label}: FAILED after {$ms}ms — {$err}";
        if (stripos($err, 'maximum statement execution time') !== false
            || stripos($err, 'max_execution_time') !== false
            || stripos($err, 'max_statement_time') !== false
            || stripos($err, 'Query execution was interrupted') !== false
        ) {
            $notes[] = "{$label}: confirmed slow — DB killed the statement at statement timeout "
                . '(same shape as phase matchups; gateway 500 expected if INSERT runs longer).';
        }

        return null;
    }
    $res = $stmt->get_result();
    $n = $res ? (int) ($res->fetch_assoc()['n'] ?? 0) : -1;
    $stmt->close();
    $notes[] = "{$label}: OK count={$n} in {$ms}ms";

    return $ms;
}