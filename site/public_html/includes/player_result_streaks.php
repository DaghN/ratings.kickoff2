<?php
/**
 * Match-result streaks — consecutive W/D/L (and non-*) runs on rated games.
 * Counts on playertable; personal-best run boundaries in player_result_streaks.
 *
 * Tie policy: first achievement wins (earlier best_end_at kept when length ties).
 * Post-game: k2_result_streak_after_rated_game() from process_completed_game.
 */
declare(strict_types=1);

/** @var list<string> */
const K2_RESULT_STREAK_TYPES = ['win', 'draw', 'loss', 'non_win', 'non_draw', 'non_loss'];

function k2_result_streak_is_valid_type(string $streakType): bool
{
    return in_array($streakType, K2_RESULT_STREAK_TYPES, true);
}

/** @return array<string, string> streak_type => playertable Longest* column */
function k2_result_streak_playertable_longest_columns(): array
{
    return [
        'win' => 'LongestWinningStreak',
        'draw' => 'LongestDrawingStreak',
        'loss' => 'LongestLosingStreak',
        'non_win' => 'LongestNonWinStreak',
        'non_draw' => 'LongestNonDrawStreak',
        'non_loss' => 'LongestNonLossStreak',
    ];
}

function k2_result_streak_playertable_longest_column(string $streakType): string
{
    $map = k2_result_streak_playertable_longest_columns();

    return $map[$streakType] ?? '';
}

/**
 * @return array<string, mixed>
 */
function k2_result_streak_new_type_state(): array
{
    return [
        'current' => 0,
        'run_start_game_id' => null,
        'run_start_at' => null,
        'best_streak' => 0,
        'best_start_game_id' => null,
        'best_end_game_id' => null,
        'best_start_at' => null,
        'best_end_at' => null,
    ];
}

/**
 * @return array<string, array<string, mixed>>
 */
function k2_result_streak_new_state(): array
{
    $state = [];
    foreach (K2_RESULT_STREAK_TYPES as $type) {
        $state[$type] = k2_result_streak_new_type_state();
    }

    return $state;
}

/**
 * @param array{idA: int, idB: int, ActualScore: float|string|null} $gameRow
 * @return array{won: bool, drew: bool, lost: bool}
 */
function k2_result_streak_outcome_for_player(int $playerId, array $gameRow): array
{
    $idA = (int) $gameRow['idA'];
    $idB = (int) $gameRow['idB'];
    $scoreA = (float) ($gameRow['ActualScore'] ?? 0);

    if ($idA === $playerId) {
        $playerScore = $scoreA;
    } elseif ($idB === $playerId) {
        $playerScore = $scoreA === 0.5 ? 0.5 : 1.0 - $scoreA;
    } else {
        throw new InvalidArgumentException("player {$playerId} not in game row");
    }

    return [
        'won' => $playerScore === 1.0,
        'drew' => $playerScore === 0.5,
        'lost' => $playerScore === 0.0,
    ];
}

/**
 * @param array<string, array<string, mixed>> $state
 */
function k2_result_streak_apply_outcome(
    array &$state,
    bool $won,
    bool $drew,
    bool $lost,
    int $gameId,
    string $gameDate
): void {
    $before = [];
    foreach (K2_RESULT_STREAK_TYPES as $type) {
        $before[$type] = (int) $state[$type]['current'];
    }

    if ($won) {
        $state['win']['current']++;
        $state['draw']['current'] = 0;
        $state['loss']['current'] = 0;
        $state['non_win']['current'] = 0;
        $state['non_draw']['current']++;
        $state['non_loss']['current']++;
    } elseif ($drew) {
        $state['win']['current'] = 0;
        $state['draw']['current']++;
        $state['loss']['current'] = 0;
        $state['non_win']['current']++;
        $state['non_draw']['current'] = 0;
        $state['non_loss']['current']++;
    } else {
        $state['win']['current'] = 0;
        $state['draw']['current'] = 0;
        $state['loss']['current']++;
        $state['non_win']['current']++;
        $state['non_draw']['current']++;
        $state['non_loss']['current'] = 0;
    }

    foreach (K2_RESULT_STREAK_TYPES as $type) {
        $prev = $before[$type];
        $cur = (int) $state[$type]['current'];
        $slot = &$state[$type];

        if ($cur === 1 && $prev === 0) {
            $slot['run_start_game_id'] = $gameId;
            $slot['run_start_at'] = k2_result_streak_normalize_datetime($gameDate);
        }

        if ($cur > (int) $slot['best_streak']) {
            $slot['best_streak'] = $cur;
            $slot['best_start_game_id'] = $slot['run_start_game_id'];
            $slot['best_end_game_id'] = $gameId;
            $slot['best_start_at'] = $slot['run_start_at'];
            $slot['best_end_at'] = k2_result_streak_normalize_datetime($gameDate);
        }

        if ($cur === 0) {
            $slot['run_start_game_id'] = null;
            $slot['run_start_at'] = null;
        }
        unset($slot);
    }
}

/**
 * @param list<array<string, mixed>> $games chronological rated games for one player
 * @return array<string, array<string, mixed>>
 */
function k2_result_streak_compute_from_games(int $playerId, array $games): array
{
    $state = k2_result_streak_new_state();

    foreach ($games as $game) {
        $outcome = k2_result_streak_outcome_for_player($playerId, $game);
        k2_result_streak_apply_outcome(
            $state,
            $outcome['won'],
            $outcome['drew'],
            $outcome['lost'],
            (int) $game['id'],
            (string) $game['Date']
        );
    }

    return $state;
}

/**
 * @return list<array<string, mixed>>
 */
function k2_result_streak_list_player_games_chronological(mysqli $con, int $playerId): array
{
    $stmt = $con->prepare(
        'SELECT `id`, `Date`, `idA`, `idB`, `ActualScore` '
        . 'FROM `ratedresults` '
        . 'WHERE (`idA` = ? OR `idB` = ?) AND `NewRatingA` IS NOT NULL '
        . 'ORDER BY `Date` ASC, `id` ASC'
    );
    if ($stmt === false) {
        throw new RuntimeException('result streak games prepare failed: ' . $con->error);
    }
    $stmt->bind_param('ii', $playerId, $playerId);
    $stmt->execute();
    $res = $stmt->get_result();
    $games = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $games[] = $row;
        }
        $res->free();
    }
    $stmt->close();

    return $games;
}

/**
 * @return array<string, mixed>|null
 */
function k2_result_streak_load_row(mysqli $con, int $playerId, string $streakType): ?array
{
    if (!k2_result_streak_is_valid_type($streakType)) {
        throw new InvalidArgumentException('invalid streak_type: ' . $streakType);
    }

    $stmt = $con->prepare(
        'SELECT `player_id`, `streak_type`, `best_streak`, `best_start_game_id`, `best_end_game_id`, '
        . '`best_start_at`, `best_end_at`, `current_run_start_game_id` '
        . 'FROM `player_result_streaks` WHERE `player_id` = ? AND `streak_type` = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('load result streak prepare failed');
    }
    $stmt->bind_param('is', $playerId, $streakType);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    return $row ?: null;
}

/**
 * @param array<string, mixed> $computed one type slot from compute_from_games
 * @return array<string, mixed>
 */
function k2_result_streak_row_from_computed(int $playerId, string $streakType, array $computed): array
{
    return [
        'player_id' => $playerId,
        'streak_type' => $streakType,
        'best_streak' => (int) ($computed['best_streak'] ?? 0),
        'best_start_game_id' => $computed['best_start_game_id'] ?? null,
        'best_end_game_id' => $computed['best_end_game_id'] ?? null,
        'best_start_at' => $computed['best_start_at'] ?? null,
        'best_end_at' => $computed['best_end_at'] ?? null,
        'current_run_start_game_id' => $computed['run_start_game_id'] ?? null,
    ];
}

/**
 * @param array<string, mixed> $row
 */
function k2_result_streak_save_row(mysqli $con, array $row): void
{
    $stmt = $con->prepare(
        'INSERT INTO `player_result_streaks` '
        . '(`player_id`, `streak_type`, `best_streak`, `best_start_game_id`, `best_end_game_id`, '
        . '`best_start_at`, `best_end_at`, `current_run_start_game_id`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE '
        . '`best_streak` = VALUES(`best_streak`), '
        . '`best_start_game_id` = VALUES(`best_start_game_id`), '
        . '`best_end_game_id` = VALUES(`best_end_game_id`), '
        . '`best_start_at` = VALUES(`best_start_at`), '
        . '`best_end_at` = VALUES(`best_end_at`), '
        . '`current_run_start_game_id` = VALUES(`current_run_start_game_id`)'
    );
    if ($stmt === false) {
        throw new RuntimeException('save result streak prepare failed');
    }

    $playerId = (int) $row['player_id'];
    $streakType = (string) $row['streak_type'];
    $bestStreak = (int) $row['best_streak'];
    $bestStartGameId = $row['best_start_game_id'] !== null ? (int) $row['best_start_game_id'] : null;
    $bestEndGameId = $row['best_end_game_id'] !== null ? (int) $row['best_end_game_id'] : null;
    $bestStartAt = k2_result_streak_normalize_datetime(
        isset($row['best_start_at']) ? (string) $row['best_start_at'] : null
    );
    $bestEndAt = k2_result_streak_normalize_datetime(
        isset($row['best_end_at']) ? (string) $row['best_end_at'] : null
    );
    $currentRunStart = $row['current_run_start_game_id'] !== null
        ? (int) $row['current_run_start_game_id'] : null;

    $stmt->bind_param(
        'isiiissi',
        $playerId,
        $streakType,
        $bestStreak,
        $bestStartGameId,
        $bestEndGameId,
        $bestStartAt,
        $bestEndAt,
        $currentRunStart
    );
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('save result streak failed: ' . $err);
    }
    $stmt->close();
}

function k2_result_streak_rebuild_player(mysqli $con, int $playerId): int
{
    $games = k2_result_streak_list_player_games_chronological($con, $playerId);
    if ($games === []) {
        return 0;
    }

    $computed = k2_result_streak_compute_from_games($playerId, $games);
    $written = 0;
    foreach (K2_RESULT_STREAK_TYPES as $type) {
        $row = k2_result_streak_row_from_computed($playerId, $type, $computed[$type]);
        if ((int) $row['best_streak'] < 1) {
            continue;
        }
        k2_result_streak_save_row($con, $row);
        $written++;
    }

    return $written;
}

function k2_result_streak_rebuild_all(mysqli $con): int
{
    $con->query('TRUNCATE TABLE `player_result_streaks`');

    $playersRes = $con->query(
        'SELECT DISTINCT `player_id` FROM ('
        .         'SELECT `idA` AS `player_id` FROM `ratedresults` WHERE `idA` > 0 '
        . 'UNION '
        . 'SELECT `idB` FROM `ratedresults` WHERE `idB` > 0'
        . ') AS `players` ORDER BY `player_id` ASC'
    );
    if ($playersRes === false) {
        throw new RuntimeException('player list query failed');
    }

    $written = 0;
    while ($pRow = $playersRes->fetch_assoc()) {
        $written += k2_result_streak_rebuild_player($con, (int) $pRow['player_id']);
    }
    $playersRes->free();

    return $written;
}

/**
 * @param array<string, mixed> $stored
 * @param array<string, mixed> $expected computed type slot
 */
function k2_result_streak_rows_match(array $stored, array $expected): bool
{
    $expBest = (int) ($expected['best_streak'] ?? 0);
    $gotBest = (int) ($stored['best_streak'] ?? 0);
    if ($expBest !== $gotBest) {
        return false;
    }
    if ($expBest < 1) {
        return true;
    }

    return (int) ($stored['best_start_game_id'] ?? 0) === (int) ($expected['best_start_game_id'] ?? 0)
        && (int) ($stored['best_end_game_id'] ?? 0) === (int) ($expected['best_end_game_id'] ?? 0);
}

/**
 * Verify stored best run is a valid consecutive streak in ratedresults order.
 *
 * @return list<string> empty = pass
 */
function k2_result_streak_validate_run_boundary(
    mysqli $con,
    int $playerId,
    string $streakType,
    int $startGameId,
    int $endGameId,
    int $expectedLen
): array {
    if ($expectedLen < 1 || $startGameId < 1 || $endGameId < 1) {
        return ['invalid boundary inputs'];
    }

    $games = k2_result_streak_list_player_games_chronological($con, $playerId);
    $inRun = false;
    $runLen = 0;
    $errors = [];

    foreach ($games as $game) {
        $gid = (int) $game['id'];
        if ($gid === $startGameId) {
            $inRun = true;
        }
        if (!$inRun) {
            continue;
        }

        $outcome = k2_result_streak_outcome_for_player($playerId, $game);
        $qualifies = match ($streakType) {
            'win' => $outcome['won'],
            'draw' => $outcome['drew'],
            'loss' => $outcome['lost'],
            'non_win' => !$outcome['won'],
            'non_draw' => !$outcome['drew'],
            'non_loss' => !$outcome['lost'],
            default => false,
        };

        if (!$qualifies) {
            $errors[] = "game {$gid} breaks {$streakType} run";
            break;
        }
        $runLen++;

        if ($gid === $endGameId) {
            break;
        }
    }

    if ($runLen !== $expectedLen) {
        $errors[] = "run length expected {$expectedLen} counted {$runLen}";
    }

    return $errors;
}

/**
 * Compare stored rows to chronological oracle; optional playertable count check.
 *
 * @return list<string> mismatch descriptions; empty = pass
 */
function k2_result_streak_oracle_mismatches(
    mysqli $con,
    ?int $playerId = null,
    bool $checkPlayertable = false
): array {
    $mismatches = [];
    $colMap = k2_result_streak_playertable_longest_columns();

    $sql = 'SELECT DISTINCT `player_id` FROM ('
        .         'SELECT `idA` AS `player_id` FROM `ratedresults` WHERE `idA` > 0 '
        . 'UNION '
        . 'SELECT `idB` FROM `ratedresults` WHERE `idB` > 0'
        . ') AS `players`';
    if ($playerId !== null) {
        $sql .= ' WHERE `player_id` = ' . (int) $playerId;
    }
    $sql .= ' ORDER BY `player_id` ASC';

    $playersRes = $con->query($sql);
    if ($playersRes === false) {
        return ['player list query failed'];
    }

    while ($pRow = $playersRes->fetch_assoc()) {
        $pid = (int) $pRow['player_id'];
        $games = k2_result_streak_list_player_games_chronological($con, $pid);
        $expected = k2_result_streak_compute_from_games($pid, $games);

        foreach (K2_RESULT_STREAK_TYPES as $type) {
            $exp = $expected[$type];
            $expBest = (int) ($exp['best_streak'] ?? 0);
            $stored = k2_result_streak_load_row($con, $pid, $type);
            $gotBest = $stored ? (int) ($stored['best_streak'] ?? 0) : 0;

            if ($expBest !== $gotBest) {
                $mismatches[] = "player {$pid} {$type} best_streak expected {$expBest} got {$gotBest}";
                continue;
            }

            if ($expBest < 1) {
                if ($stored !== null) {
                    $mismatches[] = "player {$pid} {$type} expected no row got best_streak {$gotBest}";
                }
                continue;
            }

            if ($stored === null || !k2_result_streak_rows_match($stored, $exp)) {
                $mismatches[] = "player {$pid} {$type} boundary mismatch stored vs oracle";
                continue;
            }

            if ($checkPlayertable) {
                $col = $colMap[$type];
                $ptRes = $con->query(
                    'SELECT `' . $col . '` AS v FROM `playertable` WHERE `ID` = ' . $pid . ' LIMIT 1'
                );
                $ptVal = 0;
                if ($ptRes) {
                    $ptRow = $ptRes->fetch_assoc();
                    $ptVal = (int) ($ptRow['v'] ?? 0);
                    $ptRes->free();
                }
                if ($ptVal !== $expBest) {
                    $mismatches[] = "player {$pid} {$type} playertable.{$col}={$ptVal} oracle={$expBest}";
                }
            }

            $boundaryErrors = k2_result_streak_validate_run_boundary(
                $con,
                $pid,
                $type,
                (int) $stored['best_start_game_id'],
                (int) $stored['best_end_game_id'],
                $expBest
            );
            foreach ($boundaryErrors as $err) {
                $mismatches[] = "player {$pid} {$type} {$err}";
            }
        }
    }
    $playersRes->free();

    return $mismatches;
}

function k2_result_streak_format_day_label(?string $at): string
{
    if ($at === null || trim($at) === '') {
        return '';
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $at, new DateTimeZone('UTC'));
    if ($dt === false) {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', substr($at, 0, 10), new DateTimeZone('UTC'));
    }
    if ($dt === false) {
        return substr($at, 0, 10);
    }

    return $dt->format('M j, Y');
}

function k2_result_streak_format_span(?string $startAt, ?string $endAt): string
{
    $start = k2_result_streak_format_day_label($startAt);
    if ($start === '') {
        return '';
    }
    $end = k2_result_streak_format_day_label($endAt);
    if ($end === '' || $end === $start) {
        return $start;
    }

    return $start . ' – ' . $end;
}

function k2_result_streak_normalize_datetime(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }

    return gmdate('Y-m-d H:i:s', $ts);
}

function k2_result_streak_table_ready(mysqli $con): bool
{
    $res = $con->query("SHOW TABLES LIKE 'player_result_streaks'");

    return $res !== false && $res->num_rows > 0;
}

function k2_result_streak_game_date(mysqli $con, int $gameId): ?string
{
    if ($gameId < 1) {
        return null;
    }
    $stmt = $con->prepare('SELECT `Date` FROM `ratedresults` WHERE `id` = ? LIMIT 1');
    if ($stmt === false) {
        return null;
    }
    $stmt->bind_param('i', $gameId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    if (!$row) {
        return null;
    }

    return k2_result_streak_normalize_datetime((string) $row['Date']);
}

/**
 * @param array<string, mixed> $after playertable state after this game (post_game_player_apply_match)
 * @return array<string, int>
 */
function k2_result_streak_counters_before_from_after(array $after, bool $won, bool $drew, bool $lost): array
{
    return [
        'win' => $won ? max(0, (int) ($after['winning_streak'] ?? 0) - 1) : 0,
        'draw' => $drew ? max(0, (int) ($after['drawing_streak'] ?? 0) - 1) : 0,
        'loss' => $lost ? max(0, (int) ($after['losing_streak'] ?? 0) - 1) : 0,
        'non_win' => $won ? 0 : max(0, (int) ($after['non_win_streak'] ?? 0) - 1),
        'non_draw' => $drew ? 0 : max(0, (int) ($after['non_draw_streak'] ?? 0) - 1),
        'non_loss' => $lost ? 0 : max(0, (int) ($after['non_loss_streak'] ?? 0) - 1),
    ];
}

/**
 * @param array<string, int> $beforeCurrents
 * @return array<string, array<string, mixed>>
 */
function k2_result_streak_load_state_for_incremental(mysqli $con, int $playerId, array $beforeCurrents): array
{
    $state = k2_result_streak_new_state();

    foreach (K2_RESULT_STREAK_TYPES as $type) {
        $slot = &$state[$type];
        $slot['current'] = (int) ($beforeCurrents[$type] ?? 0);
        $row = k2_result_streak_load_row($con, $playerId, $type);
        if ($row === null) {
            unset($slot);
            continue;
        }

        $slot['best_streak'] = (int) ($row['best_streak'] ?? 0);
        $slot['best_start_game_id'] = $row['best_start_game_id'] !== null ? (int) $row['best_start_game_id'] : null;
        $slot['best_end_game_id'] = $row['best_end_game_id'] !== null ? (int) $row['best_end_game_id'] : null;
        $slot['best_start_at'] = k2_result_streak_normalize_datetime(
            isset($row['best_start_at']) ? (string) $row['best_start_at'] : null
        );
        $slot['best_end_at'] = k2_result_streak_normalize_datetime(
            isset($row['best_end_at']) ? (string) $row['best_end_at'] : null
        );
        $runStartId = $row['current_run_start_game_id'] !== null ? (int) $row['current_run_start_game_id'] : null;
        $slot['run_start_game_id'] = $runStartId > 0 ? $runStartId : null;
        if ($slot['run_start_game_id'] !== null) {
            $slot['run_start_at'] = k2_result_streak_game_date($con, (int) $slot['run_start_game_id']);
        }
        unset($slot);
    }

    return $state;
}

/**
 * Post-game: update personal-best run boundaries for both players.
 *
 * @param array<int, array<string, mixed>> $playersAfter
 */
function k2_result_streak_after_rated_game(
    mysqli $con,
    int $gameId,
    string $gameDate,
    int $idA,
    int $idB,
    float $actualScore,
    array $playersAfter
): void {
    if (!k2_result_streak_table_ready($con)) {
        return;
    }

    $gameRow = ['idA' => $idA, 'idB' => $idB, 'ActualScore' => $actualScore];

    foreach ([$idA, $idB] as $playerId) {
        if ($playerId < 1 || !isset($playersAfter[$playerId])) {
            continue;
        }

        $after = $playersAfter[$playerId];
        $outcome = k2_result_streak_outcome_for_player($playerId, $gameRow);
        $before = k2_result_streak_counters_before_from_after(
            $after,
            $outcome['won'],
            $outcome['drew'],
            $outcome['lost']
        );
        $state = k2_result_streak_load_state_for_incremental($con, $playerId, $before);
        k2_result_streak_apply_outcome(
            $state,
            $outcome['won'],
            $outcome['drew'],
            $outcome['lost'],
            $gameId,
            $gameDate
        );

        foreach (K2_RESULT_STREAK_TYPES as $type) {
            $row = k2_result_streak_row_from_computed($playerId, $type, $state[$type]);
            if ((int) $row['best_streak'] < 1) {
                continue;
            }
            k2_result_streak_save_row($con, $row);
        }
    }
}
