<?php
/**
 * Amiga post-game: one canonical game → amiga_game_ratings + amiga_player_stats.
 *
 * v1 append-only: game must be chronologically last in contract order.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/amiga_ops_bootstrap.php';
require_once dirname(__DIR__, 3) . '/ops/includes/post_game_elo.php';
require_once dirname(__DIR__, 3) . '/ops/includes/post_game_outcome.php';
require_once __DIR__ . '/../includes/amiga_post_game_player_db.php';
require_once __DIR__ . '/../includes/amiga_post_game_player_apply.php';

/**
 * @return array<string, mixed>
 */
function amiga_ops_load_game_row(mysqli $con, int $gameId): array
{
    $stmt = $con->prepare(
        'SELECT g.id, g.game_date AS `Date`, g.player_a_id AS idA, g.player_b_id AS idB, '
        . 'g.goals_a AS GoalsA, g.goals_b AS GoalsB '
        . 'FROM amiga_games g WHERE g.id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare load game: ' . $con->error);
    }
    $stmt->bind_param('i', $gameId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute load game: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($row === false || $row === null) {
        throw new RuntimeException("amiga_games id={$gameId} not found");
    }

    return $row;
}

function amiga_ops_game_rating_exists(mysqli $con, int $gameId): bool
{
    $stmt = $con->prepare('SELECT 1 FROM amiga_game_ratings WHERE game_id = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare rating exists: ' . $con->error);
    }
    $stmt->bind_param('i', $gameId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute rating exists: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $exists = $res && $res->fetch_assoc() !== null;
    $stmt->close();

    return $exists;
}

function amiga_ops_last_game_id(mysqli $con): int
{
    $sql = 'SELECT g.id FROM amiga_games g '
        . 'LEFT JOIN tournaments t ON t.id = g.tournament_id '
        . 'ORDER BY ' . AMIGA_GAME_CHRONO_ORDER_DESC . ' LIMIT 1';
    $res = $con->query($sql);
    if ($res === false) {
        throw new RuntimeException('last game id: ' . $con->error);
    }
    $row = $res->fetch_assoc();
    $res->free();
    if ($row === null) {
        throw new RuntimeException('amiga_games is empty');
    }

    return (int) $row['id'];
}

/**
 * @return string|null null if OK
 */
function amiga_ops_append_only_skip_reason(mysqli $con, int $gameId): ?string
{
    if (amiga_ops_last_game_id($con) !== $gameId) {
        return 'not_append_only';
    }

    $res = $con->query(
        'SELECT '
        . '(SELECT COUNT(*) FROM amiga_games) AS games, '
        . '(SELECT COUNT(*) FROM amiga_game_ratings) AS ratings'
    );
    if ($res === false) {
        throw new RuntimeException('append-only counts: ' . $con->error);
    }
    $row = $res->fetch_assoc();
    $res->free();
    $games = (int) ($row['games'] ?? 0);
    $ratings = (int) ($row['ratings'] ?? 0);
    if ($games - $ratings !== 1) {
        return 'derived_gap';
    }

    return null;
}

/**
 * @return string|null null if OK
 */
function amiga_ops_game_skip_reason(mysqli $con, array $game): ?string
{
    $gameId = (int) $game['id'];
    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];

    if ($idA <= 0 || $idB <= 0) {
        return 'invalid_player_ids';
    }
    if ($idA === $idB) {
        return 'same_player';
    }
    if ($game['GoalsA'] === null || $game['GoalsB'] === null) {
        return 'goals_missing';
    }
    if (amiga_ops_game_rating_exists($con, $gameId)) {
        return 'already_processed';
    }

    return amiga_ops_append_only_skip_reason($con, $gameId);
}

function amiga_ops_log_skip_game(int $gameId, string $reason): void
{
    amiga_ops_log("[SKIP] game_id={$gameId} reason={$reason}");
}

function amiga_ops_round6(float $value): float
{
    return round($value, 6, PHP_ROUND_HALF_EVEN);
}

/**
 * @param array{a: float, b: float} $ratings
 * @return array<string, mixed>
 */
function amiga_ops_compute_game_ratings_derived(array $game, array $ratings): array
{
    $gameId = (int) $game['id'];
    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];
    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];

    $outcome = k2_post_game_outcome_from_goals($goalsA, $goalsB, $idA, $idB);
    $elo = k2_post_game_compute_elo($ratings['a'], $ratings['b'], (float) $outcome['actual_score']);

    return [
        'game_id' => $gameId,
        'rating_a' => amiga_ops_round6($elo['rating_a']),
        'rating_b' => amiga_ops_round6($elo['rating_b']),
        'expected_score_a' => amiga_ops_round6($elo['expected_a']),
        'expected_score_b' => amiga_ops_round6($elo['expected_b']),
        'adjustment_a' => amiga_ops_round6($elo['adjustment_a']),
        'adjustment_b' => amiga_ops_round6($elo['adjustment_b']),
        'new_rating_a' => amiga_ops_round6($elo['new_rating_a']),
        'new_rating_b' => amiga_ops_round6($elo['new_rating_b']),
        'rating_difference' => amiga_ops_round6($elo['rating_difference']),
        'actual_score' => amiga_ops_round6((float) $outcome['actual_score']),
        'winner_id' => $outcome['winner_id'],
        'sum_of_goals' => $outcome['sum_of_goals'],
        'goal_difference' => $outcome['goal_difference'],
        'home_win' => $outcome['home_win'],
        'draw' => $outcome['draw'],
        'away_win' => $outcome['away_win'],
        'dd_player_a' => $outcome['dd_player_a'],
        'dd_player_b' => $outcome['dd_player_b'],
        'cs_player_a' => $outcome['cs_player_a'],
        'cs_player_b' => $outcome['cs_player_b'],
    ];
}

/**
 * CamelCase row for player apply helpers (mirrors ratedresults derived shape).
 *
 * @param array<string, mixed> $derived snake_case amiga_game_ratings row
 * @return array<string, mixed>
 */
function amiga_ops_derived_for_player_apply(array $derived): array
{
    return [
        'ActualScore' => $derived['actual_score'],
        'RatingA' => $derived['rating_a'],
        'RatingB' => $derived['rating_b'],
        'NewRatingA' => $derived['new_rating_a'],
        'NewRatingB' => $derived['new_rating_b'],
        'AdjustmentA' => $derived['adjustment_a'],
        'AdjustmentB' => $derived['adjustment_b'],
        'GoalDifference' => $derived['goal_difference'],
        'SumOfGoals' => $derived['sum_of_goals'],
        'DDPlayerA' => $derived['dd_player_a'],
        'DDPlayerB' => $derived['dd_player_b'],
        'CSPlayerA' => $derived['cs_player_a'],
        'CSPlayerB' => $derived['cs_player_b'],
    ];
}

/**
 * @param array<string, mixed> $derived
 * @param array<int, array<string, mixed>> $players
 */
function amiga_ops_apply_player_stats_for_game(
    mysqli $con,
    array $game,
    array $derived,
    array &$players
): void {
    $gameId = (int) $game['id'];
    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];
    $goalsA = (int) $game['GoalsA'];
    $goalsB = (int) $game['GoalsB'];
    $gameDate = (string) $game['Date'];
    $d = amiga_ops_derived_for_player_apply($derived);

    $players[$idA] = amiga_post_game_player_load($con, $idA, $gameId);
    $players[$idB] = amiga_post_game_player_load($con, $idB, $gameId);

    $oldRatingA = (float) $players[$idA]['rating'];
    $oldRatingB = (float) $players[$idB]['rating'];
    $actualScore = (float) $d['ActualScore'];
    $scoreB = $actualScore === 0.5 ? 0.5 : 1.0 - $actualScore;

    amiga_post_game_player_apply_match(
        $con,
        $players,
        $idA,
        $idB,
        (float) $d['RatingB'],
        $goalsA,
        $goalsB,
        $actualScore,
        (int) $d['GoalDifference'],
        (int) $d['SumOfGoals'],
        (int) $d['DDPlayerA'] === 1,
        $goalsB === 0,
        $oldRatingA,
        (float) $d['NewRatingA'],
        (float) $d['AdjustmentA'],
        $gameId,
        $gameDate,
        $gameId
    );
    amiga_post_game_player_apply_match(
        $con,
        $players,
        $idB,
        $idA,
        (float) $d['RatingA'],
        $goalsB,
        $goalsA,
        $scoreB,
        (int) $d['GoalDifference'],
        (int) $d['SumOfGoals'],
        (int) $d['DDPlayerB'] === 1,
        $goalsA === 0,
        $oldRatingB,
        (float) $d['NewRatingB'],
        (float) $d['AdjustmentB'],
        $gameId,
        $gameDate,
        $gameId
    );

    if ((int) $d['DDPlayerA'] === 1) {
        amiga_post_game_player_apply_conceded_network($con, $players, $idB, $idA, $gameId, true, false);
    }
    if ((int) $d['DDPlayerB'] === 1) {
        amiga_post_game_player_apply_conceded_network($con, $players, $idA, $idB, $gameId, true, false);
    }
    if ((int) $d['CSPlayerA'] === 1) {
        amiga_post_game_player_apply_conceded_network($con, $players, $idB, $idA, $gameId, false, true);
    }
    if ((int) $d['CSPlayerB'] === 1) {
        amiga_post_game_player_apply_conceded_network($con, $players, $idA, $idB, $gameId, false, true);
    }
}

/**
 * @param array<string, mixed> $row
 */
function amiga_ops_write_game_ratings(mysqli $con, array $row): void
{
    $sql = 'INSERT INTO amiga_game_ratings ('
        . 'game_id, rating_a, rating_b, rating_difference, '
        . 'expected_score_a, expected_score_b, actual_score, '
        . 'adjustment_a, adjustment_b, new_rating_a, new_rating_b, '
        . 'sum_of_goals, goal_difference, winner_id, '
        . 'home_win, draw, away_win, '
        . 'dd_player_a, dd_player_b, cs_player_a, cs_player_b'
        . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_game_ratings insert: ' . $con->error);
    }

    $gameId = (int) $row['game_id'];
    $stmt->bind_param(
        'idddddddddddiiiiiiiii',
        $gameId,
        $row['rating_a'],
        $row['rating_b'],
        $row['rating_difference'],
        $row['expected_score_a'],
        $row['expected_score_b'],
        $row['actual_score'],
        $row['adjustment_a'],
        $row['adjustment_b'],
        $row['new_rating_a'],
        $row['new_rating_b'],
        $row['sum_of_goals'],
        $row['goal_difference'],
        $row['winner_id'],
        $row['home_win'],
        $row['draw'],
        $row['away_win'],
        $row['dd_player_a'],
        $row['dd_player_b'],
        $row['cs_player_a'],
        $row['cs_player_b']
    );
    if (!$stmt->execute()) {
        throw new RuntimeException('execute amiga_game_ratings insert game_id=' . $gameId . ': ' . $stmt->error);
    }
    $stmt->close();
}

/**
 * Process one completed Amiga game (derived tables only).
 *
 * @return array{
 *   derived: array<string, mixed>,
 *   committed: bool,
 *   skipped: bool,
 *   skip_reason: string|null
 * }
 */
function amiga_process_completed_game(mysqli $con, int $gameId, bool $dryRun = false): array
{
    $game = amiga_ops_load_game_row($con, $gameId);
    $skipReason = amiga_ops_game_skip_reason($con, $game);
    if ($skipReason !== null) {
        amiga_ops_log_skip_game($gameId, $skipReason);

        return [
            'derived' => [],
            'committed' => false,
            'skipped' => true,
            'skip_reason' => $skipReason,
        ];
    }

    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];
    $ratings = amiga_post_game_load_player_ratings($con, $idA, $idB);
    $derived = amiga_ops_compute_game_ratings_derived($game, $ratings);

    $players = [];
    amiga_ops_apply_player_stats_for_game($con, $game, $derived, $players);

    if ($dryRun) {
        return ['derived' => $derived, 'committed' => false, 'skipped' => false, 'skip_reason' => null];
    }

    $con->begin_transaction();
    try {
        amiga_ops_write_game_ratings($con, $derived);
        foreach ($players as $pid => $st) {
            amiga_post_game_player_write($con, k2_post_game_player_to_db_row($st, (int) $pid));
        }
        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    }

    return ['derived' => $derived, 'committed' => true, 'skipped' => false, 'skip_reason' => null];
}
