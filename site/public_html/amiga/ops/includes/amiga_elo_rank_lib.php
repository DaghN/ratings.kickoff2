<?php
/**
 * Career Elo ladder rank at tournament finalize (LB: Rating DESC, player_id ASC).
 *
 * @see scripts/amiga/elo_rank.py
 */
declare(strict_types=1);

/**
 * @param array<int, float> $ratings
 * @return array<int, int>
 */
function amiga_ops_assign_elo_ranks(array $ratings): array
{
    if ($ratings === []) {
        return [];
    }

    $items = [];
    foreach ($ratings as $playerId => $rating) {
        $items[] = ['player_id' => (int) $playerId, 'rating' => (float) $rating];
    }

    usort($items, static function (array $a, array $b): int {
        $cmp = $b['rating'] <=> $a['rating'];
        if ($cmp !== 0) {
            return $cmp;
        }

        return $a['player_id'] <=> $b['player_id'];
    });

    $ranks = [];
    $rank = 0;
    foreach ($items as $item) {
        $rank++;
        $ranks[(int) $item['player_id']] = $rank;
    }

    return $ranks;
}

/**
 * @param array<int, array<string, mixed>> $players
 * @param list<int> $activeParticipantIds
 * @return array<int, float>
 */
function amiga_ops_load_career_ratings_through_tournament(
    mysqli $con,
    int $tournamentId,
    array $players,
    array $activeParticipantIds
): array {
    $sql = 'SELECT x.player_id, x.Rating FROM ('
        . 'SELECT s.player_id, s.Rating, s.NumberGames, '
        . 'ROW_NUMBER() OVER ('
        . 'PARTITION BY s.player_id '
        . 'ORDER BY s.event_date DESC, s.event_chrono DESC, s.tournament_id DESC'
        . ') AS rn '
        . 'FROM amiga_player_event_snapshots s '
        . 'INNER JOIN tournaments tc ON tc.id = ? '
        . 'WHERE ('
        . '  s.event_date < tc.event_date '
        . '  OR (s.event_date = tc.event_date AND s.event_chrono < tc.chrono) '
        . '  OR (s.event_date = tc.event_date AND s.event_chrono = tc.chrono AND s.tournament_id < tc.id)'
        . ')'
        . ') x WHERE x.rn = 1 AND x.NumberGames > 0';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare career ratings through tournament: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute career ratings through tournament: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $ratings = [];
    while ($res && ($row = $res->fetch_assoc())) {
        if ($row['Rating'] !== null) {
            $ratings[(int) $row['player_id']] = (float) $row['Rating'];
        }
    }
    $stmt->close();

    foreach ($activeParticipantIds as $pid) {
        $pid = (int) $pid;
        $games = (int) ($players[$pid]['games'] ?? 0);
        if ($games < 1) {
            continue;
        }
        $rating = $players[$pid]['rating'] ?? null;
        if ($rating !== null) {
            $ratings[$pid] = (float) $rating;
        }
    }

    return $ratings;
}

/**
 * @param list<int> $playerIds
 * @return array<int, array{peak: ?int, tournament_id: ?int}>
 */
function amiga_ops_load_prior_peak_elo_ranks(mysqli $con, array $playerIds): array
{
    if ($playerIds === []) {
        return [];
    }

    $ids = implode(', ', array_map('intval', $playerIds));
    $sql = 'SELECT player_id, peak_elo_rank, peak_elo_rank_tournament_id '
        . 'FROM amiga_player_current WHERE player_id IN (' . $ids . ')';
    $res = $con->query($sql);
    if ($res === false) {
        throw new RuntimeException('load prior peak elo ranks: ' . $con->error);
    }

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $pid = (int) $row['player_id'];
        $out[$pid] = [
            'peak' => $row['peak_elo_rank'] !== null ? (int) $row['peak_elo_rank'] : null,
            'tournament_id' => $row['peak_elo_rank_tournament_id'] !== null
                ? (int) $row['peak_elo_rank_tournament_id'] : null,
        ];
    }
    $res->free();

    return $out;
}

function amiga_ops_compute_peak_elo_rank(
    int $rank,
    int $tournamentId,
    ?int $priorPeak,
    ?int $priorPeakTournamentId
): array {
    if ($priorPeak === null || $rank < $priorPeak) {
        return ['peak' => $rank, 'tournament_id' => $tournamentId];
    }
    if ($priorPeakTournamentId === null) {
        return ['peak' => $priorPeak, 'tournament_id' => $tournamentId];
    }

    return ['peak' => $priorPeak, 'tournament_id' => $priorPeakTournamentId];
}

/**
 * @param array<int, int> $ranks
 * @param list<int> $participantIds
 */
function amiga_ops_persist_elo_ranks_at_tournament(
    mysqli $con,
    int $tournamentId,
    ?string $eventDate,
    float $eventChrono,
    array $ranks,
    array $participantIds
): void {
    if ($ranks === []) {
        return;
    }

    unset($participantIds);

    $playerIds = array_map('intval', array_keys($ranks));
    $priorPeaks = amiga_ops_load_prior_peak_elo_ranks($con, $playerIds);

    $sql = 'INSERT INTO amiga_player_elo_rank_at_event '
        . '(player_id, tournament_id, event_date, event_chrono, elo_rank, peak_elo_rank, peak_elo_rank_tournament_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE event_date = VALUES(event_date), '
        . 'event_chrono = VALUES(event_chrono), elo_rank = VALUES(elo_rank), '
        . 'peak_elo_rank = VALUES(peak_elo_rank), '
        . 'peak_elo_rank_tournament_id = VALUES(peak_elo_rank_tournament_id)';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare elo_rank_at_event: ' . $con->error);
    }

    $currentUpdates = [];
    foreach ($ranks as $playerId => $rank) {
        $pid = (int) $playerId;
        $r = (int) $rank;
        $prior = $priorPeaks[$pid] ?? ['peak' => null, 'tournament_id' => null];
        $peak = amiga_ops_compute_peak_elo_rank(
            $r,
            $tournamentId,
            $prior['peak'],
            $prior['tournament_id']
        );
        $peakRank = (int) $peak['peak'];
        $peakTournamentId = (int) $peak['tournament_id'];
        $stmt->bind_param('iisdiii', $pid, $tournamentId, $eventDate, $eventChrono, $r, $peakRank, $peakTournamentId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute elo_rank_at_event: ' . $stmt->error);
        }
        $currentUpdates[$pid] = [$r, $peakRank, $peakTournamentId];
    }
    $stmt->close();

    if ($currentUpdates === []) {
        return;
    }

    $caseRank = [];
    $casePeak = [];
    $casePeakTid = [];
    $ids = [];
    foreach ($currentUpdates as $pid => $vals) {
        $caseRank[] = 'WHEN ' . $pid . ' THEN ' . $vals[0];
        $casePeak[] = 'WHEN ' . $pid . ' THEN ' . $vals[1];
        $casePeakTid[] = 'WHEN ' . $pid . ' THEN ' . $vals[2];
        $ids[] = (string) $pid;
    }
    $updateSql = 'UPDATE amiga_player_current SET '
        . 'elo_rank = CASE player_id ' . implode(' ', $caseRank) . ' END, '
        . 'peak_elo_rank = CASE player_id ' . implode(' ', $casePeak) . ' END, '
        . 'peak_elo_rank_tournament_id = CASE player_id ' . implode(' ', $casePeakTid) . ' END '
        . 'WHERE player_id IN (' . implode(', ', $ids) . ')';
    if (!$con->query($updateSql)) {
        throw new RuntimeException('update current elo rank + peak: ' . $con->error);
    }
}
