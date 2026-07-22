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
 * @return array{
 *   cutoff_tournament_id:int,
 *   cutoff_name:string,
 *   player_current:int,
 *   matchup_summary:int,
 *   player_slice_totals:int,
 *   country_slice_totals:int,
 *   generalstats:bool,
 *   community_stats:bool,
 *   wc_hof_present:bool
 * }
 */
function amiga_ops_project_present_at(mysqli $con, int $cutoffTournamentId): array
{
    if ($cutoffTournamentId <= 0) {
        throw new AmigaProjectPresentException('cutoff tournament_id must be positive');
    }
    $cutoff = amiga_ops_project_present_load_cutoff($con, $cutoffTournamentId);

    $playerCurrent = amiga_ops_project_player_current_at($con, $cutoff);
    $generalOk = amiga_ops_project_generalstats_at($con, $cutoff);
    $matchupRows = amiga_ops_project_matchup_summary_at($con, $cutoff);
    $communityOk = amiga_ops_project_community_headline_at($con, $cutoff);
    $playerSlice = amiga_ops_project_player_slice_totals_at($con, $cutoff);
    $countrySlice = amiga_ops_project_country_slice_totals_at($con, $cutoff);
    $wcHofOk = amiga_ops_project_wc_hof_present_at($con, $cutoff);

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
        amiga_ops_upsert_row($con, 'amiga_player_current', $current, ['player_id']);
        $written++;
    }
    $stmt->close();

    amiga_ops_project_overlay_elo_ranks_at($con, $cutoff);
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
 * Overlay inverse-count columns from latest changelog ≤ cutoff (sparse).
 *
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 */
function amiga_ops_project_overlay_inverse_counts_at(mysqli $con, array $cutoff): void
{
    $metrics = [
        'mgs_culprits' => 'MostGoalsScoredCulprits',
        'bw_culprits' => 'BiggestWinCulprits',
        'mgc_victims' => 'MostGoalsConcededVictims',
        'bl_victims' => 'BiggestLossVictims',
    ];

    // Default all four to 0, then apply changelog tips.
    if (!$con->query(
        'UPDATE amiga_player_current SET '
        . 'MostGoalsScoredCulprits = 0, BiggestWinCulprits = 0, '
        . 'MostGoalsConcededVictims = 0, BiggestLossVictims = 0'
    )) {
        throw new AmigaProjectPresentException('reset inverse counts: ' . $con->error);
    }

    $tuple = amiga_ops_project_present_tuple_cutoff_sql(
        'inv.event_date',
        'inv.event_chrono',
        'inv.tournament_id'
    );
    $sql = "
        SELECT inv.player_id, inv.metric, inv.value_after
        FROM (
            SELECT inv.*,
                   ROW_NUMBER() OVER (
                       PARTITION BY inv.player_id, inv.metric
                       ORDER BY inv.event_date DESC, inv.event_chrono DESC, inv.tournament_id DESC
                   ) AS rn
            FROM amiga_player_inverse_count_at_event inv
            WHERE {$tuple}
        ) inv
        WHERE inv.rn = 1
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new AmigaProjectPresentException('prepare inverse overlay: ' . $con->error);
    }
    $eventDate = (string) $cutoff['event_date'];
    $chrono = (float) $cutoff['chrono'];
    $tid = (int) $cutoff['tournament_id'];
    $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new AmigaProjectPresentException('execute inverse overlay: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if ($res === false) {
        $stmt->close();
        throw new AmigaProjectPresentException('get_result inverse overlay');
    }

    while ($row = $res->fetch_assoc()) {
        $metric = (string) $row['metric'];
        if (!isset($metrics[$metric])) {
            continue;
        }
        $col = $metrics[$metric];
        $pid = (int) $row['player_id'];
        $val = (int) ($row['value_after'] ?? 0);
        $sqlUp = "UPDATE amiga_player_current SET `{$col}` = {$val} WHERE player_id = {$pid}";
        if (!$con->query($sqlUp)) {
            throw new AmigaProjectPresentException("inverse update {$col}: " . $con->error);
        }
    }
    $stmt->close();
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
 * @param array{event_date: string, chrono: float|int, tournament_id: int} $cutoff
 */
function amiga_ops_project_matchup_summary_at(mysqli $con, array $cutoff): int
{
    if (!$con->query('DELETE FROM amiga_player_matchup_summary')) {
        throw new AmigaProjectPresentException('DELETE matchup_summary: ' . $con->error);
    }

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
        WHERE e.rn = 1
          AND EXISTS (
              SELECT 1 FROM amiga_games g
              WHERE (g.player_a_id = e.player_id AND g.player_b_id = e.opponent_id)
                 OR (g.player_b_id = e.player_id AND g.player_a_id = e.opponent_id)
          )
    ";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new AmigaProjectPresentException('prepare matchup project: ' . $con->error);
    }
    $eventDate = (string) $cutoff['event_date'];
    $chrono = (float) $cutoff['chrono'];
    $tid = (int) $cutoff['tournament_id'];
    $stmt->bind_param('sdi', $eventDate, $chrono, $tid);
    if (!$stmt->execute()) {
        throw new AmigaProjectPresentException('execute matchup project: ' . $stmt->error);
    }
    $written = (int) $stmt->affected_rows;
    $stmt->close();

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