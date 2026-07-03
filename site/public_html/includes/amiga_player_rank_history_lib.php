<?php
/**
 * Amiga career Elo rank over time — read path for profile rank chart API.
 *
 * @see docs/amiga-player-rank-chart-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_current_lib.php';
require_once __DIR__ . '/amiga_player_h2h_pair_lib.php';

function amiga_player_rank_percentile(int $eloRank, int $ladderSize): float
{
    if ($ladderSize < 1 || $eloRank < 1) {
        return 0.0;
    }

    return round(100.0 * ($ladderSize - $eloRank + 1) / $ladderSize, 1);
}

/**
 * Career peak rank summary from stored finalize truth (SCH-041).
 *
 * @return array{eloRank: int, eventDate: string, percentile: float, tournamentName: string}|null
 */
function amiga_player_rank_peak_summary(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
    string $playerName = '',
): ?array {
    if ($playerId < 1) {
        return null;
    }

    $ctx = $ctx ?? AmigaSnapshotContext::present();
    $peakRank = null;
    $peakTournamentId = null;
    $eventDate = null;

    if ($ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff === null) {
            return null;
        }
        $sql = 'SELECT x.peak_elo_rank, x.peak_elo_rank_tournament_id, t.event_date, t.name AS tournament_name, t.country AS host_country '
            . 'FROM ('
            . '  SELECT er.peak_elo_rank, er.peak_elo_rank_tournament_id, '
            . '    ROW_NUMBER() OVER ('
            . '      ORDER BY er.event_date DESC, er.event_chrono DESC, er.tournament_id DESC'
            . '    ) AS rn '
            . '  FROM amiga_player_elo_rank_at_event er '
            . '  WHERE er.player_id = ? '
            . '    AND (er.event_date, er.event_chrono, er.tournament_id) <= (?, ?, ?) '
            . ') x '
            . 'LEFT JOIN tournaments t ON t.id = x.peak_elo_rank_tournament_id '
            . 'WHERE x.rn = 1';
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $eventDateParam = $cutoff['event_date'];
        $chrono = $cutoff['chrono'];
        $tournamentId = $cutoff['tournament_id'];
        $stmt->bind_param('isdi', $playerId, $eventDateParam, $chrono, $tournamentId);
    } else {
        $careerTable = amiga_player_career_table($con);
        $sql = 'SELECT c.peak_elo_rank, c.peak_elo_rank_tournament_id, t.event_date, t.name AS tournament_name, t.country AS host_country '
            . 'FROM `' . $careerTable . '` c '
            . 'LEFT JOIN tournaments t ON t.id = c.peak_elo_rank_tournament_id '
            . 'WHERE c.player_id = ? AND c.NumberGames > 0 LIMIT 1';
        $stmt = $con->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $playerId);
    }

    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    if ($row === false || $row === null) {
        return null;
    }

    $peakRank = (int) ($row['peak_elo_rank'] ?? 0);
    $peakTournamentId = $row['peak_elo_rank_tournament_id'] !== null
        ? (int) $row['peak_elo_rank_tournament_id'] : null;
    $eventDate = (string) ($row['event_date'] ?? '');

    if ($peakRank < 1 || $peakTournamentId === null || $peakTournamentId < 1 || $eventDate === '') {
        return null;
    }

    $tournamentName = trim((string) ($row['tournament_name'] ?? ''));

    $ladderStmt = $con->prepare(
        'SELECT COUNT(*) AS ladder_size FROM amiga_player_elo_rank_at_event WHERE tournament_id = ?'
    );
    if (!$ladderStmt) {
        return null;
    }
    $ladderStmt->bind_param('i', $peakTournamentId);
    if (!$ladderStmt->execute()) {
        $ladderStmt->close();

        return null;
    }
    $ladderRes = $ladderStmt->get_result();
    $ladderRow = $ladderRes ? $ladderRes->fetch_assoc() : false;
    if ($ladderRes) {
        $ladderRes->free();
    }
    $ladderStmt->close();

    $ladderSize = $ladderRow !== false && $ladderRow !== null
        ? (int) ($ladderRow['ladder_size'] ?? 0) : 0;
    if ($ladderSize < 1) {
        return null;
    }

    require_once __DIR__ . '/k2_amiga_country_flag.php';
    require_once __DIR__ . '/amiga_lb_peak_rating_lib.php';
    $hostCountry = trim((string) ($row['host_country'] ?? ''));
    $flagMeta = k2_amiga_country_flag_meta($hostCountry);
    $playedInEvent = amiga_player_peak_rank_played_in_event($con, $playerId, $peakTournamentId);
    $absentClause = $playedInEvent ? '' : amiga_player_peak_rank_absent_clause($playerName);

    return [
        'eloRank' => $peakRank,
        'eventDate' => $eventDate,
        'percentile' => amiga_player_rank_percentile($peakRank, $ladderSize),
        'tournamentId' => $peakTournamentId,
        'tournamentName' => $tournamentName,
        'hostCountry' => $hostCountry,
        'flagCode' => $flagMeta !== null ? $flagMeta['code'] : '',
        'playedInEvent' => $playedInEvent,
        'absentClause' => $absentClause,
        'ratingSnapshotHref' => amiga_lb_peak_rating_peak_rank_href($peakTournamentId),
    ];
}

/**
 * @return list<array{
 *   tournamentId: int,
 *   eventDate: string,
 *   eventChrono: float,
 *   eloRank: int,
 *   ladderSize: int,
 *   percentile: float,
 *   tournamentName: string
 * }>
 */
function amiga_player_rank_history_points(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
): array {
    if ($playerId < 1) {
        return [];
    }

    $ctx = $ctx ?? AmigaSnapshotContext::present();

    $sql = 'SELECT er.tournament_id, er.event_date, er.event_chrono, er.elo_rank, '
        . 't.name AS tournament_name, ls.ladder_size '
        . 'FROM amiga_player_elo_rank_at_event er '
        . 'INNER JOIN tournaments t ON t.id = er.tournament_id '
        . 'INNER JOIN ( '
        . '  SELECT tournament_id, COUNT(*) AS ladder_size '
        . '  FROM amiga_player_elo_rank_at_event '
        . '  GROUP BY tournament_id '
        . ') ls ON ls.tournament_id = er.tournament_id '
        . 'WHERE er.player_id = ?';

    $types = 'i';
    $params = [$playerId];

    if ($ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff === null) {
            return [];
        }
        $sql .= ' AND (er.event_date, er.event_chrono, er.tournament_id) <= (?, ?, ?)';
        $types .= 'sdi';
        $params[] = $cutoff['event_date'];
        $params[] = $cutoff['chrono'];
        $params[] = $cutoff['tournament_id'];
    }

    $sql .= ' ORDER BY er.event_date ASC, er.event_chrono ASC, er.tournament_id ASC';

    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return [];
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $stmt->close();

        return [];
    }

    $res = $stmt->get_result();
    $points = [];
    while ($row = $res->fetch_assoc()) {
        $eloRank = (int) ($row['elo_rank'] ?? 0);
        $ladderSize = (int) ($row['ladder_size'] ?? 0);
        if ($eloRank < 1 || $ladderSize < 1) {
            continue;
        }

        $points[] = [
            'tournamentId' => (int) $row['tournament_id'],
            'eventDate' => (string) ($row['event_date'] ?? ''),
            'eventChrono' => (float) ($row['event_chrono'] ?? 0),
            'eloRank' => $eloRank,
            'ladderSize' => $ladderSize,
            'percentile' => amiga_player_rank_percentile($eloRank, $ladderSize),
            'tournamentName' => (string) ($row['tournament_name'] ?? ''),
        ];
    }

    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $points;
}

/**
 * @param list<array{eloRank: int, ladderSize: int, eventDate: string}> $points
 * @return array{
 *   careerBestRank: int|null,
 *   careerWorstRank: int|null,
 *   careerBestPercentile: float|null,
 *   careerWorstPercentile: float|null,
 *   ceiling: int|null,
 *   cutoffActive: bool
 * }
 */
function amiga_player_rank_history_meta(array $points, bool $cutoffActive): array
{
    if ($points === []) {
        return [
            'careerBestRank' => null,
            'careerWorstRank' => null,
            'careerBestPercentile' => null,
            'careerWorstPercentile' => null,
            'ceiling' => null,
            'cutoffActive' => $cutoffActive,
        ];
    }

    $best = $points[0]['eloRank'];
    $worst = $points[0]['eloRank'];
    $ceiling = $points[0]['ladderSize'];
    $bestPercentile = (float) $points[0]['percentile'];
    $worstPercentile = (float) $points[0]['percentile'];

    foreach ($points as $point) {
        $rank = $point['eloRank'];
        $percentile = (float) $point['percentile'];
        if ($rank < $best) {
            $best = $rank;
        }
        if ($rank > $worst) {
            $worst = $rank;
        }
        if ($percentile > $bestPercentile) {
            $bestPercentile = $percentile;
        }
        if ($percentile < $worstPercentile) {
            $worstPercentile = $percentile;
        }
        if ($point['ladderSize'] > $ceiling) {
            $ceiling = $point['ladderSize'];
        }
    }

    return [
        'careerBestRank' => $best,
        'careerWorstRank' => $worst,
        'careerBestPercentile' => $bestPercentile,
        'careerWorstPercentile' => $worstPercentile,
        'ceiling' => $ceiling,
        'cutoffActive' => $cutoffActive,
    ];
}

/**
 * @return array{
 *   playerId: int,
 *   playerName: string,
 *   currentRank: int|null,
 *   points: list<array<string, mixed>>,
 *   meta: array<string, mixed>,
 *   peak: array{eloRank: int, eventDate: string, percentile: float}|null,
 *   timelineStart: string|null
 * }|null
 */
function amiga_player_rank_history_payload(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
): ?array {
    if ($playerId < 1) {
        return null;
    }

    $ctx = $ctx ?? AmigaSnapshotContext::present();
    $careerTable = amiga_player_career_table($con);
    $nameSql = 'SELECT p.name AS Name, s.elo_rank FROM amiga_players p '
        . 'INNER JOIN `' . $careerTable . '` s ON s.player_id = p.id WHERE p.id = ? LIMIT 1';
    $nameStmt = $con->prepare($nameSql);
    if (!$nameStmt) {
        return null;
    }
    $nameStmt->bind_param('i', $playerId);
    $nameStmt->execute();
    $nameRes = $nameStmt->get_result();
    $nameRow = $nameRes ? $nameRes->fetch_assoc() : null;
    if ($nameRes) {
        $nameRes->free();
    }
    $nameStmt->close();

    if ($nameRow === null) {
        return null;
    }

    $points = amiga_player_rank_history_points($con, $playerId, $ctx);
    $meta = amiga_player_rank_history_meta($points, $ctx->isActive());
    $timelineStart = amiga_player_rating_timeline_start($con);

    $currentRank = null;
    if ($points !== []) {
        $last = $points[count($points) - 1];
        $currentRank = $last['eloRank'];
    } elseif (!$ctx->isActive()) {
        $stored = (int) ($nameRow['elo_rank'] ?? 0);
        $currentRank = $stored > 0 ? $stored : null;
    }

    return [
        'playerId' => $playerId,
        'playerName' => (string) $nameRow['Name'],
        'currentRank' => $currentRank,
        'points' => $points,
        'meta' => $meta,
        'peak' => amiga_player_rank_peak_summary($con, $playerId, $ctx, (string) $nameRow['Name']),
        'timelineStart' => $timelineStart,
    ];
}

/**
 * @return array{
 *   player: array<string, mixed>,
 *   opponent: array<string, mixed>,
 *   timelineStart: string|null
 * }|null
 */
function amiga_player_rank_history_compare_payload(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null,
): ?array {
    if ($playerId < 1 || $opponentId < 1 || $playerId === $opponentId) {
        return null;
    }

    $ctx = $ctx ?? AmigaSnapshotContext::present();
    $player = amiga_player_rank_history_payload($con, $playerId, $ctx);
    $opponent = amiga_player_rank_history_payload($con, $opponentId, $ctx);
    if ($player === null || $opponent === null) {
        return null;
    }

    return [
        'player' => $player,
        'opponent' => $opponent,
        'timelineStart' => amiga_player_rating_timeline_start($con),
    ];
}