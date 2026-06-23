<?php
/**
 * generalstatstable id=1 — incremental per-game update (P3).
 *
 * Aggregates increment from stored GST row + this game (no ratedresults full scan).
 * Holders: strict `>` via post_game_server_records.php (PG-004).
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_bootstrap.php';
require_once __DIR__ . '/ops_reset_universe.php';
require_once __DIR__ . '/post_game_server_records.php';

function k2_post_game_gst_int(array $row, string $col): int
{
    if (!isset($row[$col]) || $row[$col] === null || $row[$col] === '') {
        return 0;
    }

    return (int) $row[$col];
}

/**
 * Increment server-wide aggregates (mirrors archived scripts/ladder/generalstats.py numerators).
 *
 * @param array<string, mixed> $gstRow current generalstatstable id=1
 * @param array<string, mixed> $derived ratedresults derived for this game
 * @param array<int, array<string, mixed>> $players post-apply player state (A/B + transfer touches)
 */
function k2_post_game_increment_server_aggregates(
    array $gstRow,
    array $derived,
    array $players,
    int $idA,
    int $idB
): array {
    $games = k2_post_game_gst_int($gstRow, 'GamesPlayed') + 1;
    $draws = k2_post_game_gst_int($gstRow, 'NumberOfDraws');
    if ((float) $derived['ActualScore'] === 0.5) {
        $draws++;
    }
    $decided = $games - $draws;
    $goals = k2_post_game_gst_int($gstRow, 'GoalsScored') + (int) $derived['SumOfGoals'];
    $dd = k2_post_game_gst_int($gstRow, 'DoubleDigits')
        + (int) $derived['DDPlayerA'] + (int) $derived['DDPlayerB'];
    $cs = k2_post_game_gst_int($gstRow, 'CleanSheets')
        + (int) $derived['CSPlayerA'] + (int) $derived['CSPlayerB'];

    $numPlayers = k2_post_game_gst_int($gstRow, 'NumberOfPlayers');
    if (isset($players[$idA]) && (int) $players[$idA]['games'] === 1) {
        $numPlayers++;
    }
    if (isset($players[$idB]) && (int) $players[$idB]['games'] === 1 && $idB !== $idA) {
        $numPlayers++;
    }

    return [
        'NumberOfPlayers' => $numPlayers,
        'GamesPlayed' => $games,
        'GamesPlayedAverage' => $numPlayers > 0 ? (2 * $games / $numPlayers) : null,
        'NumberOfDecidedGames' => $decided,
        'NumberOfDraws' => $draws,
        'DecidedGamesRatio' => $games > 0 ? ($decided / $games) : null,
        'DrawsRatio' => $games > 0 ? ($draws / $games) : null,
        'GoalsScored' => $goals,
        'GoalsPerGameAverage' => $games > 0 ? ($goals / $games) : null,
        'DoubleDigits' => $dd,
        'CleanSheets' => $cs,
        'DoubleDigitsRatio' => $games > 0 ? ($dd / $games) : null,
        'CleanSheetsRatio' => $games > 0 ? ($cs / $games) : null,
    ];
}

/**
 * Recompute DifferentOpponentsAverage from playertable (~500 rows; not ratedresults scan).
 */
function k2_post_game_diff_opponents_average(mysqli $con): ?float
{
    $res = $con->query(
        'SELECT AVG(DifferentOpponents) AS a FROM playertable WHERE DifferentOpponents >= 1'
    );
    if ($res === false) {
        throw new RuntimeException('DifferentOpponentsAverage: ' . $con->error);
    }
    $row = $res->fetch_assoc();
    $res->free();
    if ($row === false || $row['a'] === null) {
        return null;
    }

    return (float) $row['a'];
}

/**
 * @param array<int, string> $playerIds
 * @return array<int, string>
 */
function k2_post_game_load_player_names(mysqli $con, array $playerIds = []): array
{
    if ($playerIds === []) {
        $res = $con->query('SELECT ID, Name FROM playertable');
        if ($res === false) {
            throw new RuntimeException('load player names: ' . $con->error);
        }
    } else {
        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        $types = str_repeat('i', count($playerIds));
        $stmt = $con->prepare("SELECT ID, Name FROM playertable WHERE ID IN ({$placeholders})");
        if ($stmt === false) {
            throw new RuntimeException('prepare player names: ' . $con->error);
        }
        $stmt->bind_param($types, ...$playerIds);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute player names: ' . $stmt->error);
        }
        $res = $stmt->get_result();
        $stmt->close();
    }

    $names = [];
    while ($row = $res->fetch_assoc()) {
        $names[(int) $row['ID']] = (string) $row['Name'];
    }
    $res->free();

    return $names;
}

/**
 * Full-table aggregates — batch rebuild / parity reference only (not per-game path).
 *
 * @return array<string, mixed>
 */
function k2_post_game_compute_server_aggregates(mysqli $con): array
{
    $res = $con->query(
        'SELECT COUNT(*) AS games, '
        . 'SUM(CASE WHEN ActualScore = 0.5 THEN 1 ELSE 0 END) AS draws, '
        . 'SUM(SumOfGoals) AS goals, '
        . 'SUM(DDPlayerA + DDPlayerB) AS dd, '
        . 'SUM(CSPlayerA + CSPlayerB) AS cs '
        . 'FROM ratedresults WHERE NewRatingA IS NOT NULL'
    );
    if ($res === false) {
        throw new RuntimeException('server aggregates ratedresults: ' . $con->error);
    }
    $agg = $res->fetch_assoc();
    $res->free();

    $games = (int) ($agg['games'] ?? 0);
    $draws = (int) ($agg['draws'] ?? 0);
    $decided = $games - $draws;
    $goals = (int) ($agg['goals'] ?? 0);
    $dd = (int) ($agg['dd'] ?? 0);
    $cs = (int) ($agg['cs'] ?? 0);

    $res = $con->query('SELECT COUNT(*) AS n FROM playertable WHERE NumberGames >= 1');
    if ($res === false) {
        throw new RuntimeException('server aggregates players: ' . $con->error);
    }
    $row = $res->fetch_assoc();
    $res->free();
    $numPlayers = (int) ($row['n'] ?? 0);

    return array_merge(
        [
            'NumberOfPlayers' => $numPlayers,
            'DifferentOpponentsAverage' => k2_post_game_diff_opponents_average($con),
            'GamesPlayed' => $games,
            'GamesPlayedAverage' => $numPlayers > 0 ? (2 * $games / $numPlayers) : null,
            'NumberOfDecidedGames' => $decided,
            'NumberOfDraws' => $draws,
            'DecidedGamesRatio' => $games > 0 ? ($decided / $games) : null,
            'DrawsRatio' => $games > 0 ? ($draws / $games) : null,
            'GoalsScored' => $goals,
            'GoalsPerGameAverage' => $games > 0 ? ($goals / $games) : null,
            'DoubleDigits' => $dd,
            'CleanSheets' => $cs,
            'DoubleDigitsRatio' => $games > 0 ? ($dd / $games) : null,
            'CleanSheetsRatio' => $games > 0 ? ($cs / $games) : null,
        ]
    );
}

function k2_post_game_write_generalstats_row(mysqli $con, array $patch): void
{
    if ($patch === []) {
        return;
    }

    $cols = array_keys($patch);
    $sets = implode(', ', array_map(static fn (string $c): string => "`{$c}` = ?", $cols));
    $sql = "UPDATE generalstatstable SET {$sets} WHERE id = 1";

    $types = '';
    $values = [];
    foreach ($patch as $val) {
        if ($val === null) {
            $types .= 's';
            $values[] = null;
        } elseif (is_int($val)) {
            $types .= 'i';
            $values[] = $val;
        } elseif (is_float($val)) {
            $types .= 'd';
            $values[] = $val;
        } else {
            $types .= 's';
            $values[] = (string) $val;
        }
    }

    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare generalstatstable: ' . $con->error);
    }
    $bind = [$types];
    foreach ($values as $i => $v) {
        $bind[] = &$values[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
    if (!$stmt->execute()) {
        throw new RuntimeException('generalstatstable update: ' . $stmt->error);
    }
    $stmt->close();
}

/**
 * Per-game GST update after ratedresults + playertable for this game.
 *
 * @param array<string, mixed> $game
 * @param array<string, mixed> $derived
 * @param array<int, array<string, mixed>> $players
 */
function k2_post_game_update_generalstats_after_game(
    mysqli $con,
    array $game,
    array $derived,
    array &$players,
    array $names
): int {
    k2_ops_ensure_generalstatstable($con);
    if (!k2_ops_table_exists($con, 'generalstatstable')) {
        return 0;
    }

    $res = $con->query('SELECT * FROM generalstatstable WHERE id = 1 LIMIT 1');
    if ($res === false) {
        throw new RuntimeException('generalstatstable load: ' . $con->error);
    }
    $gstRow = $res->fetch_assoc();
    $res->free();
    if ($gstRow === false || $gstRow === null) {
        return 0;
    }

    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];

    $serverState = k2_post_game_server_records_from_gst_row($gstRow);
    k2_post_game_update_server_records_after_game(
        $serverState,
        $game,
        $derived,
        $players,
        $names
    );

    $patch = array_merge(
        k2_post_game_increment_server_aggregates($gstRow, $derived, $players, $idA, $idB),
        ['DifferentOpponentsAverage' => k2_post_game_diff_opponents_average($con)],
        k2_post_game_server_holder_patch($serverState)
    );
    k2_post_game_write_generalstats_row($con, $patch);

    return count($patch);
}
