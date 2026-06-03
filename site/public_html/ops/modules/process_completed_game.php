<?php

/**

 * Post-game: one game, DB read/write, commit (ratedresults + playertable career).

 *

 * P3: generalstatstable. P4: period + peak activity. P5: period aggregates.

 */

declare(strict_types=1);



require_once __DIR__ . '/../includes/post_game_constants.php';

require_once __DIR__ . '/../includes/post_game_outcome.php';

require_once __DIR__ . '/../includes/post_game_elo.php';

require_once __DIR__ . '/../includes/post_game_player_db.php';

require_once __DIR__ . '/../includes/post_game_player_state.php';

require_once __DIR__ . '/../includes/post_game_generalstats.php';

require_once __DIR__ . '/../includes/post_game_period_activity.php';

require_once __DIR__ . '/../includes/ops_bootstrap.php';



/**

 * @return array<string, mixed>

 */

function k2_ops_load_rated_game_row(mysqli $con, int $gameId): array

{

    $stmt = $con->prepare(

        'SELECT id, Date, idA, idB, GoalsA, GoalsB, NewRatingA FROM ratedresults WHERE id = ? LIMIT 1'

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

        throw new RuntimeException("ratedresults id={$gameId} not found");

    }



    return $row;

}



/**

 * @param array<string, mixed> $game

 */

function k2_ops_guard_rated_game_processable(array $game): void

{

    $gameId = (int) $game['id'];

    $idA = (int) $game['idA'];

    $idB = (int) $game['idB'];



    if ($idA <= 0 || $idB <= 0) {

        throw new RuntimeException("ratedresults id={$gameId}: invalid idA/idB");

    }

    if ($idA === $idB) {

        throw new RuntimeException("ratedresults id={$gameId}: idA must differ from idB");

    }

    if ($game['GoalsA'] === null || $game['GoalsB'] === null) {

        throw new RuntimeException("ratedresults id={$gameId}: goals must be set");

    }

    if ($game['NewRatingA'] !== null) {

        throw new RuntimeException(

            "ratedresults id={$gameId} already processed (NewRatingA is set); run prepare zero-derived to replay"

        );

    }

}



/**

 * @param array{a: float, b: float} $ratings

 * @return array<string, mixed>

 */

function k2_ops_compute_ratedresults_derived(array $game, array $ratings): array

{

    $gameId = (int) $game['id'];

    $idA = (int) $game['idA'];

    $idB = (int) $game['idB'];

    $goalsA = (int) $game['GoalsA'];

    $goalsB = (int) $game['GoalsB'];



    $outcome = k2_post_game_outcome_from_goals($goalsA, $goalsB, $idA, $idB);

    $elo = k2_post_game_compute_elo($ratings['a'], $ratings['b'], (float) $outcome['actual_score']);



    return [

        'id' => $gameId,

        'RatingA' => $elo['rating_a'],

        'RatingB' => $elo['rating_b'],

        'ExpectedScoreA' => $elo['expected_a'],

        'ExpectedScoreB' => $elo['expected_b'],

        'AdjustmentA' => $elo['adjustment_a'],

        'AdjustmentB' => $elo['adjustment_b'],

        'NewRatingA' => $elo['new_rating_a'],

        'NewRatingB' => $elo['new_rating_b'],

        'RatingDifference' => $elo['rating_difference'],

        'ActualScore' => $outcome['actual_score'],

        'WinnerID' => $outcome['winner_id'],

        'SumOfGoals' => $outcome['sum_of_goals'],

        'GoalDifference' => $outcome['goal_difference'],

        'HomeWin' => $outcome['home_win'],

        'Draw' => $outcome['draw'],

        'AwayWin' => $outcome['away_win'],

        'DDPlayerA' => $outcome['dd_player_a'],

        'DDPlayerB' => $outcome['dd_player_b'],

        'CSPlayerA' => $outcome['cs_player_a'],

        'CSPlayerB' => $outcome['cs_player_b'],

    ];

}



/**

 * @param array<string, mixed> $row

 */

function k2_ops_write_ratedresults_derived(mysqli $con, array $row): void

{

    $sql = 'UPDATE ratedresults SET '

        . 'RatingA = ?, RatingB = ?, ExpectedScoreA = ?, ExpectedScoreB = ?, '

        . 'AdjustmentA = ?, AdjustmentB = ?, NewRatingA = ?, NewRatingB = ?, '

        . 'RatingDifference = ?, ActualScore = ?, WinnerID = ?, SumOfGoals = ?, '

        . 'GoalDifference = ?, HomeWin = ?, Draw = ?, AwayWin = ?, '

        . 'DDPlayerA = ?, DDPlayerB = ?, CSPlayerA = ?, CSPlayerB = ? '

        . 'WHERE id = ?';



    $stmt = $con->prepare($sql);

    if ($stmt === false) {

        throw new RuntimeException('prepare ratedresults update: ' . $con->error);

    }



    $id = (int) $row['id'];

    $stmt->bind_param(

        'ddddddddddiiiiiiiiiii',

        $row['RatingA'],

        $row['RatingB'],

        $row['ExpectedScoreA'],

        $row['ExpectedScoreB'],

        $row['AdjustmentA'],

        $row['AdjustmentB'],

        $row['NewRatingA'],

        $row['NewRatingB'],

        $row['RatingDifference'],

        $row['ActualScore'],

        $row['WinnerID'],

        $row['SumOfGoals'],

        $row['GoalDifference'],

        $row['HomeWin'],

        $row['Draw'],

        $row['AwayWin'],

        $row['DDPlayerA'],

        $row['DDPlayerB'],

        $row['CSPlayerA'],

        $row['CSPlayerB'],

        $id

    );

    if (!$stmt->execute()) {

        throw new RuntimeException('execute ratedresults update id=' . $id . ': ' . $stmt->error);

    }

    $stmt->close();

}



/**

 * @param array<string, mixed> $derived

 * @param array<int, array<string, mixed>> $players

 */

function k2_ops_apply_playertable_for_game(

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



    $players[$idA] = k2_post_game_player_load($con, $idA, $gameId);

    $players[$idB] = k2_post_game_player_load($con, $idB, $gameId);



    $oldRatingA = (float) $players[$idA]['rating'];

    $oldRatingB = (float) $players[$idB]['rating'];

    $actualScore = (float) $derived['ActualScore'];

    $scoreB = $actualScore === 0.5 ? 0.5 : 1.0 - $actualScore;



    k2_post_game_player_apply_match(

        $con,

        $players,

        $idA,

        $idB,

        (float) $derived['RatingB'],

        $goalsA,

        $goalsB,

        $actualScore,

        (int) $derived['GoalDifference'],

        (int) $derived['SumOfGoals'],

        (int) $derived['DDPlayerA'] === 1,

        $goalsB === 0,

        $oldRatingA,

        (float) $derived['NewRatingA'],

        (float) $derived['AdjustmentA'],

        $gameId,

        $gameDate,

        $gameId

    );

    k2_post_game_player_apply_match(

        $con,

        $players,

        $idB,

        $idA,

        (float) $derived['RatingA'],

        $goalsB,

        $goalsA,

        $scoreB,

        (int) $derived['GoalDifference'],

        (int) $derived['SumOfGoals'],

        (int) $derived['DDPlayerB'] === 1,

        $goalsA === 0,

        $oldRatingB,

        (float) $derived['NewRatingB'],

        (float) $derived['AdjustmentB'],

        $gameId,

        $gameDate,

        $gameId

    );



    if ((int) $derived['DDPlayerA'] === 1) {

        k2_post_game_player_apply_conceded_network($con, $players, $idB, $idA, $gameId, true, false);

    }

    if ((int) $derived['DDPlayerB'] === 1) {

        k2_post_game_player_apply_conceded_network($con, $players, $idA, $idB, $gameId, true, false);

    }

    if ((int) $derived['CSPlayerA'] === 1) {

        k2_post_game_player_apply_conceded_network($con, $players, $idB, $idA, $gameId, false, true);

    }

    if ((int) $derived['CSPlayerB'] === 1) {

        k2_post_game_player_apply_conceded_network($con, $players, $idA, $idB, $gameId, false, true);

    }

}



/**

 * Process one completed game: read DB, write DB, commit. No cross-game in-memory state.

 *

 * @return array{derived: array<string, mixed>, committed: bool}

 */

function k2_ops_process_completed_game(
    mysqli $con,
    int $gameId,
    bool $dryRun = false,
    ?array &$milestoneChrono = null
): array

{

    if ($milestoneChrono === null) {
        $milestoneChrono = ['_mode' => 'hydrate'];
    }

    $game = k2_ops_load_rated_game_row($con, $gameId);

    k2_ops_guard_rated_game_processable($game);



    $idA = (int) $game['idA'];

    $idB = (int) $game['idB'];

    $ratings = k2_post_game_load_player_ratings($con, $idA, $idB);

    $derived = k2_ops_compute_ratedresults_derived($game, $ratings);



    $players = [];

    k2_ops_apply_playertable_for_game($con, $game, $derived, $players);



    if ($dryRun) {

        return ['derived' => $derived, 'committed' => false];

    }



    $con->begin_transaction();

    try {

        k2_ops_write_ratedresults_derived($con, $derived);

        foreach ($players as $pid => $st) {

            k2_post_game_player_write($con, k2_post_game_player_to_db_row($st, (int) $pid));

        }

        $names = k2_post_game_load_player_names($con, array_keys($players));

        k2_post_game_update_generalstats_after_game($con, $game, $derived, $players, $names);

        $periodCounts = k2_post_game_update_period_activity_after_game($con, $game, $derived);
        if ($periodCounts !== null) {
            require_once __DIR__ . '/../includes/post_game_milestones.php';
            k2_post_game_update_milestones_after_game(
                $con,
                $game,
                $derived,
                $players,
                $periodCounts['dayA'],
                $periodCounts['dayB'],
                $periodCounts['weekA'],
                $periodCounts['weekB'],
                $periodCounts['monthA'],
                $periodCounts['monthB'],
                $periodCounts['weekStart'],
                $milestoneChrono
            );
            require_once dirname(__DIR__, 2) . '/includes/player_play_streaks.php';
            k2_play_streak_after_rated_game(
                $con,
                $gameId,
                (string) $game['Date'],
                $idA,
                $idB,
                $names[$idA] ?? '',
                $names[$idB] ?? ''
            );
        }

        $con->commit();

    } catch (Throwable $e) {

        $con->rollback();

        throw $e;

    }



    return ['derived' => $derived, 'committed' => true];

}



/**

 * Chronological sim — one k2_ops_process_completed_game per game (no batch finalize).

 *

 * @return array{processed: list<int>, committed: int}

 */

function k2_ops_replay_post_game(

    mysqli $con,

    ?int $limit = null,

    ?int $untilGameId = null,

    bool $dryRun = false

): array {

    require_once __DIR__ . '/../includes/post_game_milestones.php';
    k2_post_game_milestones_reset_replay_cache();
    $milestoneChrono = ['_mode' => 'batch'];

    $sql = 'SELECT id FROM ratedresults ORDER BY Date ASC, id ASC';

    $res = $con->query($sql);

    if ($res === false) {

        throw new RuntimeException('list games: ' . $con->error);

    }



    $ids = [];

    while ($row = $res->fetch_assoc()) {

        $gid = (int) $row['id'];

        if ($untilGameId !== null && $gid > $untilGameId) {

            break;

        }

        $ids[] = $gid;

        if ($limit !== null && count($ids) >= $limit) {

            break;

        }

    }

    $res->free();

    $processed = [];

    $committed = 0;

    foreach ($ids as $gid) {

        $result = k2_ops_process_completed_game($con, $gid, $dryRun, $milestoneChrono);

        $processed[] = $gid;

        if ($result['committed']) {

            $committed++;

        }

    }

    if (!$dryRun) {
        k2_post_game_milestones_finalize_replay_chrono($con, $milestoneChrono);
        k2_post_game_milestones_seed_lobby($con);
    }

    return ['processed' => $processed, 'committed' => $committed];

}


