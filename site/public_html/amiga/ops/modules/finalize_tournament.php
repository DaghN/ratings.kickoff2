<?php
/**
 * Finalize one Amiga tournament: frozen Elo batch + rating events commit.
 *
 * Mirrors scripts/amiga/finalize_tournament.py and contract § 5.
 */
declare(strict_types=1);

require_once __DIR__ . '/process_completed_game.php';
require_once __DIR__ . '/../includes/amiga_post_game_participation.php';
require_once __DIR__ . '/../includes/amiga_event_snapshot_persist.php';
require_once __DIR__ . '/../includes/amiga_matchup_cumulative.php';
require_once __DIR__ . '/../includes/amiga_matchup_persist.php';
require_once __DIR__ . '/../includes/amiga_realm_snapshot_lib.php';
require_once dirname(__DIR__, 3) . '/includes/amiga_performance_rating.php';

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
    $stmt = $con->prepare('SELECT id, name, rating_finalized, event_date, chrono FROM tournaments WHERE id = ? LIMIT 1');
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
 * Entry Elo from last committed rating event before this tournament (not career table).
 *
 * @param list<int> $participantIds
 * @return array<int, float>
 */
function amiga_ops_entry_ratings_before_tournament(
    mysqli $con,
    int $tournamentId,
    array $participantIds
): array {
    if ($participantIds === []) {
        return [];
    }

    $stmt = $con->prepare(
        'SELECT event_date, chrono FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament chrono: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament chrono: ' . $con->error);
    }
    $res = $stmt->get_result();
    $tour = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($tour === false) {
        $frozen = [];
        foreach ($participantIds as $pid) {
            $frozen[(int) $pid] = K2_POST_GAME_START_RATING;
        }

        return $frozen;
    }

    $placeholders = implode(', ', array_fill(0, count($participantIds), '?'));
    $types = str_repeat('i', count($participantIds)) . 'sdi';
    $sql = 'SELECT player_id, rating_after FROM ('
        . 'SELECT e.player_id, e.rating_after, '
        . 'ROW_NUMBER() OVER ('
        . 'PARTITION BY e.player_id '
        . 'ORDER BY t.event_date DESC, t.chrono DESC, t.id DESC'
        . ') AS rn '
        . 'FROM amiga_player_event_snapshots e '
        . 'INNER JOIN tournaments t ON t.id = e.tournament_id '
        . "WHERE e.player_id IN ({$placeholders}) "
        . 'AND (t.event_date, t.chrono, t.id) < (?, ?, ?)'
        . ') ranked WHERE rn = 1';

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare entry ratings before tournament: ' . $con->error);
    }
    $params = array_merge($participantIds, [
        $tour['event_date'],
        (float) $tour['chrono'],
        $tournamentId,
    ]);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute entry ratings before tournament: ' . $con->error);
    }
    $res = $stmt->get_result();

    $frozen = [];
    foreach ($participantIds as $pid) {
        $frozen[(int) $pid] = K2_POST_GAME_START_RATING;
    }
    while ($res && ($row = $res->fetch_assoc())) {
        $frozen[(int) $row['player_id']] = (float) $row['rating_after'];
    }
    $stmt->close();

    return $frozen;
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
 * @param list<array<string, mixed>> $games
 */
function amiga_ops_apply_tournament_matchups_batch(
    mysqli $con,
    int $tournamentId,
    AmigaMatchupCumulative $matchups
): void {
    $games = amiga_ops_load_tournament_games_for_finalize($con, $tournamentId);
    foreach ($games as $game) {
        $matchups->applyGame($game);
    }
}

/**
 * Warm in-memory career + matchup state through already-finalized tournaments (refinalize).
 *
 * @param list<int> $tournamentIds
 * @param array<int, array<string, mixed>> $players
 */
function amiga_ops_warm_state_through_finalized(
    mysqli $con,
    array $tournamentIds,
    AmigaMatchupCumulative $matchups,
    array &$players
): void {
    foreach ($tournamentIds as $tournamentId) {
        amiga_ops_apply_tournament_matchups_batch($con, (int) $tournamentId, $matchups);
        amiga_ops_apply_tournament_stats_batch($con, (int) $tournamentId, $players);
    }
}

/**
 * @return list<string>
 */
function amiga_ops_verify_tournament_finalize(mysqli $con, int $tournamentId): array
{
    $errors = [];

    $stmt = $con->prepare(
        'SELECT e.player_id, e.rating_before, e.rating_delta, e.rating_after '
        . 'FROM amiga_player_event_snapshots e WHERE e.tournament_id = ?'
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
        'SELECT player_id, rating_after FROM amiga_player_event_snapshots WHERE tournament_id = ?'
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
function amiga_finalize_tournament(
    mysqli $con,
    int $tournamentId,
    bool $dryRun = false,
    ?AmigaMatchupCumulative $matchups = null,
    ?array &$players = null,
): array {
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
    if ($players === null) {
        $players = amiga_ops_load_player_states_for_finalize($con);
    }
    foreach ($participantIds as $pid) {
        if (!isset($players[$pid])) {
            $players[$pid] = k2_post_game_player_state_new();
        }
    }
    if ($matchups === null) {
        $matchups = new AmigaMatchupCumulative();
        $matchups->loadFromSummary($con, $participantIds);
    }
    $frozen = amiga_ops_entry_ratings_before_tournament($con, $tournamentId, $participantIds);

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
    /** @var array<int, array<string, mixed>> $eventCommitsByPlayer */
    $eventCommitsByPlayer = [];
    $ratingEvents = 0;
    try {
        $con->begin_transaction();

        /** @var array<int, float> $pendingDelta */
        $pendingDelta = array_fill_keys($participantIds, 0.0);
        /** @var array<int, int> $gamesInEvent */
        $gamesInEvent = array_fill_keys($participantIds, 0);
        /** @var array<int, list<array{opponent: float, score: float}>> $perfPairs */
        $perfPairs = array_fill_keys($participantIds, []);

        foreach ($games as $game) {
            $matchups->applyGame($game);
            $gameId = (int) $game['id'];
            $derived = amiga_ops_compute_game_ratings_frozen($game, $frozen);
            amiga_ops_apply_player_stats_for_game($con, $game, $derived, $players, false, false);

            $idA = (int) $game['idA'];
            $idB = (int) $game['idB'];
            $scoreA = (float) $derived['actual_score'];
            $ratingA = (float) $derived['rating_a'];
            $ratingB = (float) $derived['rating_b'];
            $pendingDelta[$idA] = ($pendingDelta[$idA] ?? 0.0) + (float) $derived['adjustment_a'];
            $pendingDelta[$idB] = ($pendingDelta[$idB] ?? 0.0) + (float) $derived['adjustment_b'];
            $gamesInEvent[$idA] = ($gamesInEvent[$idA] ?? 0) + 1;
            $gamesInEvent[$idB] = ($gamesInEvent[$idB] ?? 0) + 1;
            $perfPairs[$idA][] = ['opponent' => $ratingB, 'score' => $scoreA];
            $perfPairs[$idB][] = ['opponent' => $ratingA, 'score' => 1.0 - $scoreA];

            amiga_ops_write_game_ratings_finalize($con, $derived);
        }

        $finalizedAt = gmdate('Y-m-d H:i:s');
        sort($participantIds);
        foreach ($participantIds as $pid) {
            if (($gamesInEvent[$pid] ?? 0) === 0) {
                continue;
            }
            $ratingBefore = $frozen[$pid];
            $ratingDelta = amiga_ops_round6($pendingDelta[$pid] ?? 0.0);
            $ratingAfter = amiga_ops_round6($ratingBefore + $ratingDelta);
            $performanceRating = amiga_performance_rating_from_pairs($perfPairs[$pid] ?? []);
            $players[$pid]['rating'] = $ratingAfter;
            $gamesPlayed = $gamesInEvent[$pid];
            $eventCommitsByPlayer[$pid] = [
                'rating_before' => $ratingBefore,
                'rating_delta' => $ratingDelta,
                'rating_after' => $ratingAfter,
                'performance_rating' => $performanceRating,
                'games_in_event' => $gamesPlayed,
                'finalized_at' => $finalizedAt,
            ];
            $ratingEvents++;
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

    foreach ($participantIds as $pid) {
        if (!isset($eventCommitsByPlayer[$pid])) {
            continue;
        }
        $matchups->applyNetworkToPlayerState($pid, $players[$pid]);
        amiga_matchup_apply_peak_from_event_rating(
            $players[$pid],
            (float) $eventCommitsByPlayer[$pid]['rating_after']
        );
    }

    $participation = amiga_ops_participation_refresh_tournament(
        $con,
        $tournamentId,
        $eventCommitsByPlayer
    );
    amiga_ops_log(
        'participation build: id=' . $tournamentId
        . ' rows=' . (int) ($participation['participation_rows'] ?? 0)
    );

    $snapshotRows = amiga_ops_persist_tournament_event_snapshots(
        $con,
        $tournamentId,
        $players,
        $participantIds,
        $participation['participation_by_player'] ?? null
    );
    amiga_ops_log(
        'event snapshots: id=' . $tournamentId . ' rows=' . $snapshotRows
    );

    $eventDate = (string) $tour['event_date'];
    $eventChrono = (int) $tour['chrono'];
    $atEventRows = amiga_ops_persist_matchup_at_event(
        $con,
        $tournamentId,
        $eventDate,
        $eventChrono,
        $matchups,
        $participantIds
    );
    $summaryRows = amiga_ops_upsert_matchup_summary($con, $matchups, $participantIds);
    amiga_ops_log(
        'matchup at_event: id=' . $tournamentId
        . ' rows=' . $atEventRows
        . ' summary_upserts=' . $summaryRows
    );

    amiga_realm_persist_snapshot_for_tournament($con, $tournamentId, $finalizedAt);
    amiga_ops_log('realm snapshot: id=' . $tournamentId);

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
