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

    $participantSet = [];
    foreach ($participantIds as $pid) {
        $participantSet[(int) $pid] = true;
    }

    $sql = 'INSERT INTO amiga_player_elo_rank_at_event '
        . '(player_id, tournament_id, event_date, event_chrono, elo_rank) '
        . 'VALUES (?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE event_date = VALUES(event_date), '
        . 'event_chrono = VALUES(event_chrono), elo_rank = VALUES(elo_rank)';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare elo_rank_at_event: ' . $con->error);
    }

    foreach ($ranks as $playerId => $rank) {
        $pid = (int) $playerId;
        $r = (int) $rank;
        $stmt->bind_param('iisdi', $pid, $tournamentId, $eventDate, $eventChrono, $r);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute elo_rank_at_event: ' . $stmt->error);
        }
    }
    $stmt->close();

    $nonParticipants = [];
    foreach ($ranks as $playerId => $rank) {
        $pid = (int) $playerId;
        if (!isset($participantSet[$pid])) {
            $nonParticipants[$pid] = (int) $rank;
        }
    }

    if ($nonParticipants === []) {
        return;
    }

    $caseParts = [];
    $ids = [];
    foreach ($nonParticipants as $pid => $rank) {
        $caseParts[] = 'WHEN ' . $pid . ' THEN ' . $rank;
        $ids[] = (string) $pid;
    }
    $updateSql = 'UPDATE amiga_player_current SET elo_rank = CASE player_id '
        . implode(' ', $caseParts)
        . ' END WHERE player_id IN (' . implode(', ', $ids) . ')';
    if (!$con->query($updateSql)) {
        throw new RuntimeException('update current elo_rank for non-participants: ' . $con->error);
    }
}
