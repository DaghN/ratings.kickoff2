<?php
/**
 * Amiga H2H pair reads — rated games, charts, rating history (optional time-travel cutoff).
 *
 * @see docs/amiga-opponents-wing-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_current_lib.php';
require_once __DIR__ . '/k2_amiga_routes.php';
require_once __DIR__ . '/player_goals_distribution.php';
require_once __DIR__ . '/player_opponents_h2h_moments.php';

function amiga_player_h2h_ctx(?AmigaSnapshotContext $ctx = null): AmigaSnapshotContext
{
    return $ctx ?? amiga_snapshot_context_peek() ?? AmigaSnapshotContext::present();
}

function amiga_player_h2h_pair_where_sql(
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx,
    string &$types,
    array &$params
): string {
    return amiga_games_where_clause(
        $playerId,
        '',
        $opponentId,
        0,
        '',
        '',
        '',
        '',
        0,
        0,
        0,
        -1,
        -1,
        -1,
        null,
        $types,
        $params,
        $ctx
    );
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_player_h2h_pair_game_rows_raw(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null,
    string $select = 'r.id, r.`Date`, r.idA, r.idB, r.NameA, r.NameB, r.GoalsA, r.GoalsB, r.ActualScore, r.WinnerID, r.SumOfGoals'
): array {
    $playerId = max(0, $playerId);
    $opponentId = max(0, $opponentId);
    if ($playerId < 1 || $opponentId < 1 || $playerId === $opponentId) {
        return [];
    }

    $ctx = amiga_player_h2h_ctx($ctx);
    $types = '';
    $params = [];
    $whereSql = amiga_player_h2h_pair_where_sql($playerId, $opponentId, $ctx, $types, $params);
    $sql = 'SELECT ' . $select . ' ' . amiga_rated_games_from_sql()
        . ' WHERE ' . $whereSql
        . ' ORDER BY r.`Date` ASC, r.id ASC';

    return amiga_games_query_all($con, $sql, $types, $params);
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_player_h2h_pair_games_rows(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rawRows = amiga_player_h2h_pair_game_rows_raw($con, $playerId, $opponentId, $ctx);
    $rows = [];
    foreach ($rawRows as $row) {
        $norm = player_opponents_h2h_normalize_game_row($row, $playerId);
        $norm['href'] = k2_amiga_game_page_url((int) $norm['game_id']);
        $rows[] = $norm;
    }

    return $rows;
}

/**
 * @return array<string, mixed>
 */
function amiga_player_h2h_cumulative_payload(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rows = amiga_player_h2h_pair_game_rows_raw($con, $playerId, $opponentId, $ctx);
    $points = [];
    $playerWins = 0;
    $opponentWins = 0;
    $playerGoals = 0;
    $opponentGoals = 0;
    $draws = 0;
    $gameNumber = 0;

    foreach ($rows as $row) {
        $gameNumber++;
        $actualScore = (float) ($row['ActualScore'] ?? 0);
        $winnerId = (int) ($row['WinnerID'] ?? 0);
        $idA = (int) ($row['idA'] ?? 0);
        $goalsA = (int) ($row['GoalsA'] ?? 0);
        $goalsB = (int) ($row['GoalsB'] ?? 0);

        if ($idA === $playerId) {
            $playerGoals += $goalsA;
            $opponentGoals += $goalsB;
        } else {
            $playerGoals += $goalsB;
            $opponentGoals += $goalsA;
        }

        if (abs($actualScore - 0.5) < 0.001) {
            $draws++;
        } elseif ($winnerId === $playerId) {
            $playerWins++;
        } else {
            $opponentWins++;
        }

        $points[] = [
            'game_number' => $gameNumber,
            'player_wins' => $playerWins,
            'opponent_wins' => $opponentWins,
            'player_goals' => $playerGoals,
            'opponent_goals' => $opponentGoals,
        ];
    }

    return [
        'total_games' => $gameNumber,
        'draws' => $draws,
        'player_goals_total' => $playerGoals,
        'opponent_goals_total' => $opponentGoals,
        'points' => $points,
    ];
}

/**
 * Goals scored histogram rows for one side of the pairing.
 *
 * @return list<array{goals_for: int, games: int}>
 */
function amiga_player_h2h_goals_scored_rows(
    mysqli $con,
    int $scorerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rows = amiga_player_h2h_pair_game_rows_raw($con, $scorerId, $opponentId, $ctx);
    $counts = [];
    foreach ($rows as $row) {
        $idA = (int) ($row['idA'] ?? 0);
        $gf = $idA === $scorerId ? (int) ($row['GoalsA'] ?? 0) : (int) ($row['GoalsB'] ?? 0);
        $counts[$gf] = ($counts[$gf] ?? 0) + 1;
    }
    ksort($counts);
    $out = [];
    foreach ($counts as $goalsFor => $games) {
        $out[] = ['goals_for' => (int) $goalsFor, 'games' => (int) $games];
    }

    return $out;
}

/**
 * @return list<array{goals: int, games: int}>
 */
function amiga_player_h2h_goals_scored_buckets(
    mysqli $con,
    int $scorerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rows = amiga_player_h2h_goals_scored_rows($con, $scorerId, $opponentId, $ctx);
    if ($rows === []) {
        return [];
    }

    $maxGoals = $rows[count($rows) - 1]['goals_for'];
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['goals_for']] = $row['games'];
    }

    $buckets = [];
    for ($g = 0; $g <= $maxGoals; $g++) {
        $buckets[] = [
            'goals' => $g,
            'games' => $counts[$g] ?? 0,
        ];
    }

    return $buckets;
}

/**
 * @return list<array{total_goals: int, games: int}>
 */
function amiga_player_h2h_total_goals_rows(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rows = amiga_player_h2h_pair_game_rows_raw($con, $playerId, $opponentId, $ctx);
    $counts = [];
    foreach ($rows as $row) {
        $sum = (int) ($row['SumOfGoals'] ?? 0);
        $counts[$sum] = ($counts[$sum] ?? 0) + 1;
    }
    ksort($counts);
    $out = [];
    foreach ($counts as $totalGoals => $games) {
        $out[] = ['total_goals' => (int) $totalGoals, 'games' => (int) $games];
    }

    return $out;
}

/**
 * @return list<array{goals: int, games: int}>
 */
function amiga_player_h2h_total_goals_buckets(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rows = amiga_player_h2h_total_goals_rows($con, $playerId, $opponentId, $ctx);
    if ($rows === []) {
        return [];
    }

    $maxGoals = $rows[count($rows) - 1]['total_goals'];
    $counts = [];
    foreach ($rows as $row) {
        $counts[$row['total_goals']] = $row['games'];
    }

    $buckets = [];
    for ($g = 0; $g <= $maxGoals; $g++) {
        $buckets[] = [
            'goals' => $g,
            'games' => $counts[$g] ?? 0,
        ];
    }

    return $buckets;
}

/**
 * @return list<array{goals_for: int, goals_against: int, games: int}>
 */
function amiga_player_h2h_scoreline_rows(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rows = amiga_player_h2h_pair_game_rows_raw($con, $playerId, $opponentId, $ctx);
    $counts = [];
    foreach ($rows as $row) {
        $idA = (int) ($row['idA'] ?? 0);
        if ($idA === $playerId) {
            $gf = (int) ($row['GoalsA'] ?? 0);
            $ga = (int) ($row['GoalsB'] ?? 0);
        } else {
            $gf = (int) ($row['GoalsB'] ?? 0);
            $ga = (int) ($row['GoalsA'] ?? 0);
        }
        $key = $gf . ':' . $ga;
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    $out = [];
    foreach ($counts as $key => $games) {
        [$gf, $ga] = array_map('intval', explode(':', $key, 2));
        $out[] = [
            'goals_for' => $gf,
            'goals_against' => $ga,
            'games' => (int) $games,
        ];
    }

    usort(
        $out,
        static function (array $a, array $b): int {
            $byGf = $a['goals_for'] <=> $b['goals_for'];
            if ($byGf !== 0) {
                return $byGf;
            }

            return $a['goals_against'] <=> $b['goals_against'];
        }
    );

    return $out;
}

/**
 * @return array{max_goals_for: int, max_goals_against: int, grid_axis_max: int, cells: list<array<string, mixed>>}
 */
function amiga_player_h2h_scoreline_heatmap_payload(
    mysqli $con,
    int $playerId,
    int $opponentId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $rows = amiga_player_h2h_scoreline_rows($con, $playerId, $opponentId, $ctx);
    if ($rows === []) {
        return [
            'max_goals_for' => 0,
            'max_goals_against' => 0,
            'grid_axis_max' => 0,
            'cells' => [],
        ];
    }

    $pairMaxGoalsFor = 0;
    $pairMaxGoalsAgainst = 0;
    $cells = [];
    foreach ($rows as $row) {
        $goalsFor = (int) $row['goals_for'];
        $goalsAgainst = (int) $row['goals_against'];
        if ($goalsFor > $pairMaxGoalsFor) {
            $pairMaxGoalsFor = $goalsFor;
        }
        if ($goalsAgainst > $pairMaxGoalsAgainst) {
            $pairMaxGoalsAgainst = $goalsAgainst;
        }

        $outcome = 'draw';
        if ($goalsFor > $goalsAgainst) {
            $outcome = 'win';
        } elseif ($goalsFor < $goalsAgainst) {
            $outcome = 'loss';
        }

        $cells[] = [
            'goals_for' => $goalsFor,
            'goals_against' => $goalsAgainst,
            'games' => (int) $row['games'],
            'outcome' => $outcome,
        ];
    }

    return [
        'max_goals_for' => $pairMaxGoalsFor,
        'max_goals_against' => $pairMaxGoalsAgainst,
        'grid_axis_max' => max($pairMaxGoalsFor, $pairMaxGoalsAgainst),
        'cells' => $cells,
    ];
}

/**
 * Career peak rating summary from stored finalize truth (SCH-042).
 *
 * @return array{rating: int, eventDate: string, tournamentName: string}|null
 */
function amiga_player_rating_peak_summary(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null,
): ?array {
    if ($playerId < 1) {
        return null;
    }

    $ctx = amiga_player_h2h_ctx($ctx);
    $peakRating = null;
    $peakTournamentId = null;
    $eventDate = null;

    if ($ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff === null) {
            return null;
        }
        $sql = 'SELECT x.PeakRating, x.peak_rating_tournament_id, t.event_date, t.name AS tournament_name, t.country AS host_country '
            . 'FROM ('
            . '  SELECT s.PeakRating, s.peak_rating_tournament_id, '
            . '    ROW_NUMBER() OVER ('
            . '      ORDER BY t2.event_date DESC, t2.chrono DESC, s.tournament_id DESC'
            . '    ) AS rn '
            . '  FROM amiga_player_event_snapshots s '
            . '  INNER JOIN tournaments t2 ON t2.id = s.tournament_id '
            . '  WHERE s.player_id = ? '
            . '    AND (t2.event_date, t2.chrono, t2.id) <= (?, ?, ?) '
            . ') x '
            . 'LEFT JOIN tournaments t ON t.id = x.peak_rating_tournament_id '
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
        $sql = 'SELECT c.PeakRating, c.peak_rating_tournament_id, t.event_date, t.name AS tournament_name, t.country AS host_country '
            . 'FROM `' . $careerTable . '` c '
            . 'LEFT JOIN tournaments t ON t.id = c.peak_rating_tournament_id '
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

    $rawPeak = $row['PeakRating'] ?? null;
    $peakRating = ($rawPeak !== null && $rawPeak !== '' && (float) $rawPeak > 0) ? (float) $rawPeak : 0.0;
    $peakTournamentId = $row['peak_rating_tournament_id'] !== null
        ? (int) $row['peak_rating_tournament_id'] : null;
    $eventDate = (string) ($row['event_date'] ?? '');

    if ($peakRating <= 0 || $peakTournamentId === null || $peakTournamentId < 1 || $eventDate === '') {
        return null;
    }

    require_once __DIR__ . '/k2_amiga_country_flag.php';
    $tournamentName = trim((string) ($row['tournament_name'] ?? ''));
    $hostCountry = trim((string) ($row['host_country'] ?? ''));
    $flagMeta = k2_amiga_country_flag_meta($hostCountry);

    return [
        'rating' => (int) round($peakRating),
        'eventDate' => $eventDate,
        'tournamentId' => $peakTournamentId,
        'tournamentName' => $tournamentName,
        'hostCountry' => $hostCountry,
        'flagCode' => $flagMeta !== null ? $flagMeta['code'] : '',
    ];
}

/**
 * @return array{playerId: int, playerName: string|null, currentRating: ?int, points: list<array<string, mixed>>, peak: array{rating: int, eventDate: string, tournamentName: string}|null}|null
 */
function amiga_player_rating_history_payload(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): ?array
{
    if ($playerId < 1) {
        return null;
    }

    $ctx = amiga_player_h2h_ctx($ctx);
    $careerTable = amiga_player_career_table($con);
    $nameSql = 'SELECT p.name AS Name, s.Rating FROM amiga_players p '
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

    $sql = 'SELECT s.tournament_id, s.rating_before, s.rating_delta, s.rating_after, '
        . 's.games_in_event, s.finalized_at, t.event_date, t.chrono, t.name AS tournament_name '
        . 'FROM amiga_player_event_snapshots s '
        . 'INNER JOIN tournaments t ON t.id = s.tournament_id '
        . 'WHERE s.player_id = ?';
    $types = 'i';
    $params = [$playerId];

    if ($ctx->isActive()) {
        $cutoff = $ctx->cutoff();
        if ($cutoff === null) {
            return [
                'playerId' => $playerId,
                'playerName' => (string) $nameRow['Name'],
                'currentRating' => null,
                'points' => [],
            ];
        }
        $sql .= ' AND (t.event_date, t.chrono, t.id) <= (?, ?, ?)';
        $types .= 'ssi';
        $params[] = $cutoff['event_date'];
        $params[] = $cutoff['chrono'];
        $params[] = $cutoff['tournament_id'];
    }

    $sql .= ' ORDER BY t.event_date ASC, t.chrono ASC, s.finalized_at ASC, s.tournament_id ASC';
    $stmt = $con->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $points = [];
    $eventNumber = 0;
    while ($row = $res->fetch_assoc()) {
        $eventNumber++;
        $points[] = [
            'eventId' => (int) $row['tournament_id'],
            'tournamentId' => (int) $row['tournament_id'],
            'tournamentName' => (string) $row['tournament_name'],
            'eventNumber' => $eventNumber,
            'gameNumber' => $eventNumber,
            'gameId' => (int) $row['tournament_id'],
            'date' => $row['event_date'],
            'rating' => (int) round((float) $row['rating_after']),
            'ratingDelta' => round((float) $row['rating_delta'], 1),
            'gamesInEvent' => (int) $row['games_in_event'],
        ];
    }
    if ($res) {
        $res->free();
    }
    $stmt->close();

    $currentRating = (int) round((float) $nameRow['Rating']);
    if ($ctx->isActive()) {
        $currentRating = $points !== [] ? (int) $points[count($points) - 1]['rating'] : null;
    }

    return [
        'playerId' => $playerId,
        'playerName' => (string) $nameRow['Name'],
        'currentRating' => $currentRating,
        'points' => $points,
        'peak' => amiga_player_rating_peak_summary($con, $playerId, $ctx),
        'meta' => [
            'granularity' => 'event',
            'cutoffActive' => $ctx->isActive(),
        ],
    ];
}

function amiga_player_rating_timeline_start(mysqli $con): ?string
{
    $minRes = $con->query('SELECT MIN(game_date) AS d FROM amiga_games');
    if (!$minRes) {
        return null;
    }
    $minRow = $minRes->fetch_assoc();
    $minRes->free();
    if ($minRow === null || $minRow['d'] === null) {
        return null;
    }

    return (string) $minRow['d'];
}