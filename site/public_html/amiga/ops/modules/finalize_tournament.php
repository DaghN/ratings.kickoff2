<?php
/**
 * Finalize one Amiga tournament: frozen Elo batch + rating events commit.
 *
 * Mirrors scripts/amiga/finalize_tournament.py and contract § 5.
 */
declare(strict_types=1);

require_once __DIR__ . '/process_completed_game.php';
require_once __DIR__ . '/../includes/amiga_post_game_participation.php';

const AMIGA_FINALIZE_LOCK_NAME = 'amiga_finalize_tournament';

class AmigaTournamentAlreadyFinalizedException extends RuntimeException
{
}

class AmigaTournamentNotFoundException extends RuntimeException
{
}

class AmigaFinalizeLockException extends RuntimeException
{
}

/**
 * @return array<string, mixed>
 */
function amiga_ops_load_tournament_row(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare('SELECT id, name, rating_finalized FROM tournaments WHERE id = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare load tournament: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute load tournament: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($row === false || $row === null) {
        throw new AmigaTournamentNotFoundException("tournament_id={$tournamentId} not found");
    }

    return $row;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_ops_load_tournament_games_for_finalize(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT g.id, g.game_date AS `Date`, g.player_a_id AS idA, g.player_b_id AS idB, '
        . 'g.goals_a AS GoalsA, g.goals_b AS GoalsB, g.tournament_id '
        . 'FROM amiga_games g WHERE g.tournament_id = ? '
        . 'ORDER BY g.game_date ASC, g.id ASC'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament games: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament games: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    $stmt->close();

    return $rows;
}

/**
 * @param list<array<string, mixed>> $games
 * @return list<int>
 */
function amiga_ops_tournament_participant_ids(array $games): array
{
    $ids = [];
    foreach ($games as $game) {
        $ids[(int) $game['idA']] = true;
        $ids[(int) $game['idB']] = true;
    }

    return array_keys($ids);
}

/**
 * @param list<int> $participantIds
 * @param array<int, array<string, mixed>> $players
 * @return array<int, float>
 */
function amiga_ops_frozen_ratings(array $participantIds, array $players): array
{
    $frozen = [];
    foreach ($participantIds as $pid) {
        $st = $players[$pid] ?? null;
        $frozen[$pid] = $st !== null
            ? (float) ($st['rating'] ?? K2_POST_GAME_START_RATING)
            : K2_POST_GAME_START_RATING;
    }

    return $frozen;
}

/**
 * @param array<string, mixed> $game
 * @param array<int, float> $frozen
 * @return array<string, mixed>
 */
function amiga_ops_compute_game_ratings_frozen(array $game, array $frozen): array
{
    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];
    $ratings = [
        'a' => $frozen[$idA] ?? K2_POST_GAME_START_RATING,
        'b' => $frozen[$idB] ?? K2_POST_GAME_START_RATING,
    ];
    $derived = amiga_ops_compute_game_ratings_derived($game, $ratings);
    $derived['new_rating_a'] = null;
    $derived['new_rating_b'] = null;

    return $derived;
}

function amiga_ops_write_game_ratings_finalize(mysqli $con, array $row): void
{
    $sql = 'INSERT INTO amiga_game_ratings ('
        . 'game_id, rating_a, rating_b, rating_difference, '
        . 'expected_score_a, expected_score_b, actual_score, '
        . 'adjustment_a, adjustment_b, '
        . 'sum_of_goals, goal_difference, winner_id, '
        . 'home_win, draw, away_win, '
        . 'dd_player_a, dd_player_b, cs_player_a, cs_player_b'
        . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare amiga_game_ratings finalize insert: ' . $con->error);
    }

    $gameId = (int) $row['game_id'];
    $stmt->bind_param(
        'iddddddddiiiiiiiii',
        $gameId,
        $row['rating_a'],
        $row['rating_b'],
        $row['rating_difference'],
        $row['expected_score_a'],
        $row['expected_score_b'],
        $row['actual_score'],
        $row['adjustment_a'],
        $row['adjustment_b'],
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
        throw new RuntimeException('execute amiga_game_ratings finalize game_id=' . $gameId . ': ' . $stmt->error);
    }
    $stmt->close();
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_ops_load_rated_game_rows_for_finalize(mysqli $con): array
{
    $sql = 'SELECT g.id AS id, g.player_a_id AS idA, g.player_b_id AS idB, '
        . 'r.actual_score AS ActualScore, r.dd_player_a AS DDPlayerA, r.dd_player_b AS DDPlayerB, '
        . 'r.cs_player_a AS CSPlayerA, r.cs_player_b AS CSPlayerB '
        . 'FROM amiga_games g '
        . 'INNER JOIN amiga_game_ratings r ON r.game_id = g.id '
        . 'ORDER BY g.game_date ASC, g.id ASC';
    $res = $con->query($sql);
    if ($res === false) {
        throw new RuntimeException('load rated game rows: ' . $con->error);
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();

    return $rows;
}

/**
 * @param array<int, array<string, mixed>> $players
 * @param list<array<string, mixed>> $gameRows
 */
function amiga_ops_finalize_network_counts_from_rows(array &$players, array $gameRows): void
{
    /** @var array<int, array<string, array<int, true>>> $buckets */
    $buckets = [];

    foreach ($gameRows as $g) {
        $idA = (int) $g['idA'];
        $idB = (int) $g['idB'];
        $score = (float) $g['ActualScore'];
        $ddA = (int) ($g['DDPlayerA'] ?? 0);
        $ddB = (int) ($g['DDPlayerB'] ?? 0);
        $csA = (int) ($g['CSPlayerA'] ?? 0);
        $csB = (int) ($g['CSPlayerB'] ?? 0);

        foreach ([$idA, $idB] as $pid) {
            if (!isset($buckets[$pid])) {
                $buckets[$pid] = [
                    'opponents' => [],
                    'victims' => [],
                    'culprits' => [],
                    'dd_victims' => [],
                    'dd_culprits' => [],
                    'cs_victims' => [],
                    'cs_culprits' => [],
                ];
            }
        }

        $buckets[$idA]['opponents'][$idB] = true;
        $buckets[$idB]['opponents'][$idA] = true;

        if ($score === 1.0) {
            $buckets[$idA]['victims'][$idB] = true;
            $buckets[$idB]['culprits'][$idA] = true;
        } elseif ($score === 0.0) {
            $buckets[$idB]['victims'][$idA] = true;
            $buckets[$idA]['culprits'][$idB] = true;
        }

        if ($ddA === 1) {
            $buckets[$idA]['dd_victims'][$idB] = true;
            $buckets[$idB]['dd_culprits'][$idA] = true;
        }
        if ($ddB === 1) {
            $buckets[$idB]['dd_victims'][$idA] = true;
            $buckets[$idA]['dd_culprits'][$idB] = true;
        }
        if ($csA === 1) {
            $buckets[$idA]['cs_victims'][$idB] = true;
            $buckets[$idB]['cs_culprits'][$idA] = true;
        }
        if ($csB === 1) {
            $buckets[$idB]['cs_victims'][$idA] = true;
            $buckets[$idA]['cs_culprits'][$idB] = true;
        }
    }

    foreach ($players as $pid => $st) {
        if ((int) ($st['games'] ?? 0) <= 0) {
            continue;
        }
        $b = $buckets[$pid] ?? null;
        if ($b === null) {
            continue;
        }
        $st['different_opponents'] = count($b['opponents']);
        $st['different_victims'] = count($b['victims']);
        $st['different_culprits'] = count($b['culprits']);
        $st['double_digits_victims'] = count($b['dd_victims']);
        $st['double_digits_culprits'] = count($b['dd_culprits']);
        $st['clean_sheets_victims'] = count($b['cs_victims']);
        $st['clean_sheets_culprits'] = count($b['cs_culprits']);
        $players[$pid] = $st;
    }
}

/**
 * @param list<int> $playerIds
 * @param array<int, array<string, mixed>> $players
 */
function amiga_ops_recompute_rating_peaks_from_events(
    mysqli $con,
    array &$players,
    array $playerIds
): void {
    $sql = 'SELECT e.rating_before, e.rating_after '
        . 'FROM amiga_rating_events e '
        . 'INNER JOIN tournaments t ON t.id = e.tournament_id '
        . 'WHERE e.player_id = ? '
        . 'ORDER BY t.event_date ASC, t.chrono ASC, e.finalized_at ASC, e.id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare rating peaks: ' . $con->error);
    }

    foreach ($playerIds as $pid) {
        $st = $players[$pid] ?? null;
        if ($st === null || (int) ($st['games'] ?? 0) < K2_POST_GAME_ESTABLISHED_MIN_GAMES) {
            continue;
        }
        $stmt->bind_param('i', $pid);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute rating peaks player_id=' . $pid . ': ' . $stmt->error);
        }
        $res = $stmt->get_result();
        $events = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $events[] = $row;
            }
            $res->free();
        }
        if ($events === []) {
            continue;
        }
        $points = [(float) $events[0]['rating_before']];
        foreach ($events as $row) {
            $points[] = (float) $row['rating_after'];
        }
        $st['peak_rating'] = max($points);
        $st['lowest_rating'] = min($points);
        $players[$pid] = $st;
    }
    $stmt->close();
}

/**
 * @return list<string>
 */
function amiga_ops_verify_tournament_finalize(mysqli $con, int $tournamentId): array
{
    $errors = [];

    $stmt = $con->prepare(
        'SELECT e.player_id, e.rating_before, e.rating_delta, e.rating_after '
        . 'FROM amiga_rating_events e WHERE e.tournament_id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare verify events: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute verify events: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $events = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $events[(int) $row['player_id']] = $row;
        }
        $res->free();
    }
    $stmt->close();

    $stmt = $con->prepare(
        'SELECT g.player_a_id AS idA, g.player_b_id AS idB, r.adjustment_a, r.adjustment_b '
        . 'FROM amiga_games g INNER JOIN amiga_game_ratings r ON r.game_id = g.id '
        . 'WHERE g.tournament_id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare verify games: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute verify games: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $gameRows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $gameRows[] = $row;
        }
        $res->free();
    }
    $stmt->close();

    $countRes = $con->query(
        'SELECT '
        . '(SELECT COUNT(*) FROM amiga_games WHERE tournament_id = ' . (int) $tournamentId . ') AS games, '
        . '(SELECT COUNT(*) FROM amiga_games g INNER JOIN amiga_game_ratings r ON r.game_id = g.id '
        . 'WHERE g.tournament_id = ' . (int) $tournamentId . ') AS rated'
    );
    if ($countRes === false) {
        throw new RuntimeException('verify game counts: ' . $con->error);
    }
    $countRow = $countRes->fetch_assoc();
    $countRes->free();
    $gameCount = (int) ($countRow['games'] ?? 0);
    $ratedCount = (int) ($countRow['rated'] ?? 0);
    if ($gameCount !== $ratedCount) {
        $errors[] = "games={$gameCount} rated_rows={$ratedCount} for tournament_id={$tournamentId}";
    }

    /** @var array<int, float> $deltaByPlayer */
    $deltaByPlayer = [];
    foreach ($gameRows as $row) {
        $idA = (int) $row['idA'];
        $idB = (int) $row['idB'];
        $deltaByPlayer[$idA] = ($deltaByPlayer[$idA] ?? 0.0) + (float) $row['adjustment_a'];
        $deltaByPlayer[$idB] = ($deltaByPlayer[$idB] ?? 0.0) + (float) $row['adjustment_b'];
    }

    foreach ($events as $pid => $event) {
        $rb = (float) $event['rating_before'];
        $rd = (float) $event['rating_delta'];
        $ra = (float) $event['rating_after'];
        if (abs($ra - ($rb + $rd)) > 1e-5) {
            $errors[] = "player_id={$pid} rating_after != rating_before + rating_delta";
        }
        $summed = round($deltaByPlayer[$pid] ?? 0.0, 6);
        if (abs($summed - $rd) > 1e-5) {
            $errors[] = "player_id={$pid} sum(adjustments)={$summed} != rating_delta={$rd}";
        }
    }

    return $errors;
}

function amiga_ops_acquire_finalize_lock(mysqli $con): void
{
    $name = AMIGA_FINALIZE_LOCK_NAME;
    $res = $con->query("SELECT GET_LOCK('{$name}', 0) AS got");
    if ($res === false) {
        throw new RuntimeException('GET_LOCK failed: ' . $con->error);
    }
    $row = $res->fetch_assoc();
    $res->free();
    if ((int) ($row['got'] ?? 0) !== 1) {
        throw new AmigaFinalizeLockException('Another finalize is in progress');
    }
}

function amiga_ops_release_finalize_lock(mysqli $con): void
{
    $name = AMIGA_FINALIZE_LOCK_NAME;
    $con->query("SELECT RELEASE_LOCK('{$name}')");
}

/**
 * @param array<int, array<string, mixed>> $players
 */
function amiga_ops_apply_tournament_stats_batch(
    mysqli $con,
    int $tournamentId,
    array &$players
): void {
    $games = amiga_ops_load_tournament_games_for_finalize($con, $tournamentId);
    if ($games === []) {
        return;
    }

    $participantIds = amiga_ops_tournament_participant_ids($games);
    foreach ($participantIds as $pid) {
        if (!isset($players[$pid])) {
            $players[$pid] = k2_post_game_player_state_new();
        }
    }
    $frozen = amiga_ops_frozen_ratings($participantIds, $players);

    foreach ($games as $game) {
        $derived = amiga_ops_compute_game_ratings_frozen($game, $frozen);
        amiga_ops_apply_player_stats_for_game($con, $game, $derived, $players, false, false);
    }

    $stmt = $con->prepare(
        'SELECT player_id, rating_after FROM amiga_rating_events WHERE tournament_id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare rating events for stats batch: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute rating events for stats batch: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
        $pid = (int) $row['player_id'];
        if (isset($players[$pid])) {
            $players[$pid]['rating'] = (float) $row['rating_after'];
        }
    }
    $stmt->close();
}

/**
 * @param list<int> $tournamentIds
 */
function amiga_ops_rebuild_stats_through_finalized(mysqli $con, array $tournamentIds): void
{
    if ($tournamentIds === []) {
        return;
    }

    $players = [];
    foreach ($tournamentIds as $tournamentId) {
        amiga_ops_apply_tournament_stats_batch($con, $tournamentId, $players);
    }

    amiga_ops_finalize_network_counts_from_rows($players, amiga_ops_load_rated_game_rows_for_finalize($con));
    amiga_ops_recompute_rating_peaks_from_events($con, $players, array_keys($players));

    foreach ($players as $pid => $st) {
        if ((int) ($st['games'] ?? 0) <= 0) {
            continue;
        }
        amiga_post_game_player_write($con, k2_post_game_player_to_db_row($st, (int) $pid));
    }
}

/**
 * Finalize one tournament per amiga-tournament-finalize-rating-contract.md § 5.
 *
 * @return array{
 *   tournament_id: int,
 *   name?: string,
 *   games: int,
 *   rating_events?: int,
 *   skipped: bool,
 *   dry_run?: bool
 * }
 */
function amiga_finalize_tournament(mysqli $con, int $tournamentId, bool $dryRun = false): array
{
    $tour = amiga_ops_load_tournament_row($con, $tournamentId);
    if ((int) ($tour['rating_finalized'] ?? 0) === 1) {
        throw new AmigaTournamentAlreadyFinalizedException(
            "tournament_id={$tournamentId} ({$tour['name']}) already rating_finalized"
        );
    }

    $games = amiga_ops_load_tournament_games_for_finalize($con, $tournamentId);
    if ($games === []) {
        amiga_ops_log("finalize-tournament: tournament_id={$tournamentId} has no games; skipping");

        return ['tournament_id' => $tournamentId, 'games' => 0, 'skipped' => true];
    }

    $participantIds = amiga_ops_tournament_participant_ids($games);
    $players = amiga_ops_load_player_states_for_finalize($con);
    foreach ($participantIds as $pid) {
        if (!isset($players[$pid])) {
            $players[$pid] = k2_post_game_player_state_new();
        }
    }
    $frozen = amiga_ops_frozen_ratings($participantIds, $players);

    amiga_ops_log(
        'finalize-tournament: id=' . $tournamentId
        . ' name=' . $tour['name']
        . ' games=' . count($games)
        . ' participants=' . count($participantIds)
        . ($dryRun ? ' (dry-run)' : '')
    );

    if ($dryRun) {
        $sample = amiga_ops_compute_game_ratings_frozen($games[0], $frozen);
        amiga_ops_log(
            'Dry-run sample game id=' . $games[0]['id']
            . ' frozen RatingA=' . round((float) $sample['rating_a'], 3)
            . ' AdjustmentA=' . round((float) $sample['adjustment_a'], 3)
        );

        return [
            'tournament_id' => $tournamentId,
            'games' => count($games),
            'skipped' => false,
            'dry_run' => true,
        ];
    }

    amiga_ops_acquire_finalize_lock($con);
    try {
        $con->begin_transaction();

        /** @var array<int, float> $pendingDelta */
        $pendingDelta = array_fill_keys($participantIds, 0.0);
        /** @var array<int, int> $gamesInEvent */
        $gamesInEvent = array_fill_keys($participantIds, 0);

        foreach ($games as $game) {
            $gameId = (int) $game['id'];
            $derived = amiga_ops_compute_game_ratings_frozen($game, $frozen);
            amiga_ops_apply_player_stats_for_game($con, $game, $derived, $players, false, false);

            $idA = (int) $game['idA'];
            $idB = (int) $game['idB'];
            $pendingDelta[$idA] = ($pendingDelta[$idA] ?? 0.0) + (float) $derived['adjustment_a'];
            $pendingDelta[$idB] = ($pendingDelta[$idB] ?? 0.0) + (float) $derived['adjustment_b'];
            $gamesInEvent[$idA] = ($gamesInEvent[$idA] ?? 0) + 1;
            $gamesInEvent[$idB] = ($gamesInEvent[$idB] ?? 0) + 1;

            amiga_ops_write_game_ratings_finalize($con, $derived);
        }

        amiga_ops_finalize_network_counts_from_rows($players, amiga_ops_load_rated_game_rows_for_finalize($con));

        $finalizedAt = gmdate('Y-m-d H:i:s');
        $eventStmt = $con->prepare(
            'INSERT INTO amiga_rating_events ('
            . 'tournament_id, player_id, rating_before, rating_delta, '
            . 'rating_after, games_in_event, finalized_at'
            . ') VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if ($eventStmt === false) {
            throw new RuntimeException('prepare rating event insert: ' . $con->error);
        }

        sort($participantIds);
        $ratingEvents = 0;
        foreach ($participantIds as $pid) {
            if (($gamesInEvent[$pid] ?? 0) === 0) {
                continue;
            }
            $ratingBefore = $frozen[$pid];
            $ratingDelta = amiga_ops_round6($pendingDelta[$pid] ?? 0.0);
            $ratingAfter = amiga_ops_round6($ratingBefore + $ratingDelta);
            $players[$pid]['rating'] = $ratingAfter;
            $gamesPlayed = $gamesInEvent[$pid];
            $eventStmt->bind_param(
                'iidddis',
                $tournamentId,
                $pid,
                $ratingBefore,
                $ratingDelta,
                $ratingAfter,
                $gamesPlayed,
                $finalizedAt
            );
            if (!$eventStmt->execute()) {
                throw new RuntimeException('execute rating event player_id=' . $pid . ': ' . $eventStmt->error);
            }
            $ratingEvents++;
        }
        $eventStmt->close();

        amiga_ops_recompute_rating_peaks_from_events($con, $players, $participantIds);

        foreach ($participantIds as $pid) {
            if ((int) ($players[$pid]['games'] ?? 0) <= 0) {
                continue;
            }
            amiga_post_game_player_write($con, k2_post_game_player_to_db_row($players[$pid], $pid));
        }

        $flagStmt = $con->prepare(
            'UPDATE tournaments SET rating_finalized = 1, rating_finalized_at = ? WHERE id = ?'
        );
        if ($flagStmt === false) {
            throw new RuntimeException('prepare tournament finalize flag: ' . $con->error);
        }
        $flagStmt->bind_param('si', $finalizedAt, $tournamentId);
        if (!$flagStmt->execute()) {
            throw new RuntimeException('execute tournament finalize flag: ' . $flagStmt->error);
        }
        $flagStmt->close();

        amiga_ops_standings_apply_game($con, $games[array_key_last($games)]);

        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    } finally {
        amiga_ops_release_finalize_lock($con);
    }

    $participation = amiga_ops_participation_refresh_tournament($con, $tournamentId);
    amiga_ops_log(
        'participation refresh: id=' . $tournamentId
        . ' rows=' . (int) ($participation['participation_rows'] ?? 0)
        . ' totals_players=' . (int) ($participation['totals_players'] ?? 0)
    );

    $errors = amiga_ops_verify_tournament_finalize($con, $tournamentId);
    if ($errors !== []) {
        throw new RuntimeException(
            'finalize_tournament verification failed for tournament_id=' . $tournamentId . ': '
            . implode('; ', $errors)
        );
    }

    amiga_ops_log("finalize-tournament complete: id={$tournamentId} events={$ratingEvents}");

    return [
        'tournament_id' => $tournamentId,
        'name' => (string) $tour['name'],
        'games' => count($games),
        'rating_events' => $ratingEvents,
        'skipped' => false,
    ];
}
