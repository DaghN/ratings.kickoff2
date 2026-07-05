<?php
/**
 * Status room live — lobby sim engine (work DB only).
 *
 * @see docs/status-room-live-sim-spec.md
 */
declare(strict_types=1);

const K2_STATUS_ROOM_SIM_GAME_ID_BASE = 990000;
const K2_STATUS_ROOM_SIM_DEFAULT_GAMES = 20;

function k2_status_room_sim_state_path(): string
{
    global $database;
    $db = isset($database) ? (string) $database : 'unknown';

    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'k2_status_room_live_sim_' . preg_replace('/[^a-zA-Z0-9_]/', '', $db) . '.json';
}

function k2_status_room_sim_is_allowed(): bool
{
    global $database;
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if (preg_match('/^work\.ratingskickoff\.test$/', $host)) {
        return true;
    }

    return isset($database) && $database === 'ko2unity_work';
}

/** @return array<string, mixed>|null */
function k2_status_room_sim_load_state(): ?array
{
    $path = k2_status_room_sim_state_path();
    if (!is_file($path)) {
        return null;
    }
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return null;
    }
    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : null;
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_save_state(array $state): void
{
    $path = k2_status_room_sim_state_path();
    file_put_contents($path, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function k2_status_room_sim_clear_state(): void
{
    $path = k2_status_room_sim_state_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

function k2_status_room_sim_cleanup_db(mysqli $con, ?array $playerIds = null): void
{
    mysqli_query($con, 'DELETE FROM resulttable WHERE GameID > ' . (int) K2_STATUS_ROOM_SIM_GAME_ID_BASE);
    if ($playerIds !== null && $playerIds !== []) {
        $ids = array_map('intval', $playerIds);
        $in = implode(',', $ids);
        mysqli_query($con, 'UPDATE playertable SET IsOnline = 0 WHERE ID IN (' . $in . ')');
    }
}

/** @return list<array{host: int, slave: int, name_a: string, name_b: string, goal_target: int}> */
function k2_status_room_sim_build_queue(mysqli $con, int $gameCount): array
{
    $gameCount = max(1, min(40, $gameCount));
    $r = mysqli_query(
        $con,
        'SELECT ID, Name FROM playertable WHERE NumberGames >= 1 AND ID > 0 ORDER BY RAND() LIMIT ' . ($gameCount * 2)
    );
    if ($r === false) {
        return [];
    }
    $players = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $players[] = ['id' => (int) $row['ID'], 'name' => (string) $row['Name']];
    }
    mysqli_free_result($r);
    if (count($players) < 2) {
        return [];
    }
    shuffle($players);
    $queue = [];
    for ($i = 0; $i < $gameCount; $i++) {
        $a = $players[($i * 2) % count($players)];
        $b = $players[($i * 2 + 1) % count($players)];
        if ($a['id'] === $b['id']) {
            $b = $players[($i * 2 + 2) % count($players)];
        }
        if ($a['id'] === $b['id']) {
            continue;
        }
        $queue[] = [
            'host' => $a['id'],
            'slave' => $b['id'],
            'name_a' => $a['name'],
            'name_b' => $b['name'],
            'goal_target' => random_int(5, 15),
        ];
    }

    return $queue;
}

/** @return array<string, mixed> */
function k2_status_room_sim_start(mysqli $con, int $gameCount = K2_STATUS_ROOM_SIM_DEFAULT_GAMES): array
{
    if (!k2_status_room_sim_is_allowed()) {
        return ['ok' => false, 'error' => 'sim_not_allowed'];
    }
    $prev = k2_status_room_sim_load_state();
    if (is_array($prev['players_touched'] ?? null)) {
        k2_status_room_sim_cleanup_db($con, $prev['players_touched']);
    } else {
        k2_status_room_sim_cleanup_db($con);
    }

    $queue = k2_status_room_sim_build_queue($con, $gameCount);
    if ($queue === []) {
        return ['ok' => false, 'error' => 'no_players'];
    }

    $state = [
        'active' => true,
        'started_at' => time(),
        'last_tick_at' => 0,
        'game_count' => count($queue),
        'completed_count' => 0,
        'max_concurrent' => random_int(1, 3),
        'next_game_id' => K2_STATUS_ROOM_SIM_GAME_ID_BASE + 1,
        'queue' => $queue,
        'live' => [],
        'players_touched' => [],
        'last_event' => 'started',
    ];
    k2_status_room_sim_save_state($state);

    return [
        'ok' => true,
        'game_count' => count($queue),
        'max_concurrent' => $state['max_concurrent'],
        'message' => 'Sim started — open Status to run ticks.',
    ];
}

/** @return array<string, mixed> */
function k2_status_room_sim_stop(mysqli $con): array
{
    $state = k2_status_room_sim_load_state();
    $players = is_array($state['players_touched'] ?? null) ? $state['players_touched'] : [];
    k2_status_room_sim_cleanup_db($con, $players);
    k2_status_room_sim_clear_state();

    return ['ok' => true, 'message' => 'Sim stopped and cleaned up.'];
}

/** @return array<string, mixed> */
function k2_status_room_sim_public_status(?array $state): array
{
    if ($state === null || empty($state['active'])) {
        return [
            'active' => false,
            'completed_count' => 0,
            'game_count' => 0,
            'live_count' => 0,
            'queued_count' => 0,
            'last_event' => '',
        ];
    }

    return [
        'active' => (bool) $state['active'],
        'completed_count' => (int) ($state['completed_count'] ?? 0),
        'game_count' => (int) ($state['game_count'] ?? 0),
        'live_count' => is_array($state['live'] ?? null) ? count($state['live']) : 0,
        'queued_count' => is_array($state['queue'] ?? null) ? count($state['queue']) : 0,
        'max_concurrent' => (int) ($state['max_concurrent'] ?? 1),
        'last_event' => (string) ($state['last_event'] ?? ''),
    ];
}

function k2_status_room_sim_mark_player_online(mysqli $con, int $playerId, array &$state): void
{
    if ($playerId <= 0) {
        return;
    }
    mysqli_query($con, 'UPDATE playertable SET IsOnline = 1, LastLogin = NOW() WHERE ID = ' . $playerId);
    if (!in_array($playerId, $state['players_touched'], true)) {
        $state['players_touched'][] = $playerId;
    }
}

function k2_status_room_sim_insert_live_row(mysqli $con, array $live): bool
{
    $gameId = (int) $live['game_id'];
    $host = (int) $live['host'];
    $slave = (int) $live['slave'];
    $nameA = mysqli_real_escape_string($con, (string) $live['name_a']);
    $nameB = mysqli_real_escape_string($con, (string) $live['name_b']);
    $scoreA = (int) ($live['score_a'] ?? 0);
    $scoreB = (int) ($live['score_b'] ?? 0);
    $half = (int) ($live['half_countdown'] ?? 15000);
    $period = (int) ($live['period'] ?? 1);

    $sql = 'INSERT INTO resulttable (
        GameID, HostID, SlaveID, NameA, NameB, GameVersion, GameMode,
        StartTime, ScoreA, ScoreB, RatedGameID, RNDSetup, VersionCV, Duration,
        HalfCountdown, GamePeriod, HasStarted, HasFinished, Shelved,
        HostGUID, SlaveGUID, ConnectionMethod, Referee
    )
    SELECT '
        . $gameId . ', ' . $host . ', ' . $slave . ", '" . $nameA . "', '" . $nameB . "', GameVersion, GameMode, "
        . 'NOW(), ' . $scoreA . ', ' . $scoreB . ", -1, RNDSetup, VersionCV, 0, "
        . $half . ', ' . $period . ', 1, 0, 0, HostGUID, SlaveGUID, ConnectionMethod, Referee
    FROM resulttable ORDER BY GameID DESC LIMIT 1';

    return mysqli_query($con, $sql) !== false;
}

function k2_status_room_sim_update_live_row(mysqli $con, array $live): void
{
    $gameId = (int) $live['game_id'];
    $sql = 'UPDATE resulttable SET ScoreA = ' . (int) $live['score_a']
        . ', ScoreB = ' . (int) $live['score_b']
        . ', HalfCountdown = ' . max(0, (int) $live['half_countdown'])
        . ', GamePeriod = ' . (int) $live['period']
        . ' WHERE GameID = ' . $gameId;
    mysqli_query($con, $sql);
}

function k2_status_room_sim_finish_live_row(mysqli $con, array $live): ?int
{
    $gameId = (int) $live['game_id'];
    mysqli_query($con, 'UPDATE resulttable SET HasFinished = 1, HasStarted = 1, Shelved = 0 WHERE GameID = ' . $gameId);

    $r = mysqli_query($con, 'SELECT MAX(id) AS v FROM ratedresults');
    $maxId = 0;
    if ($r !== false) {
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        $maxId = (int) ($row['v'] ?? 0);
    }
    $ratedId = $maxId + 1;
    $host = (int) $live['host'];
    $slave = (int) $live['slave'];
    $nameA = mysqli_real_escape_string($con, (string) $live['name_a']);
    $nameB = mysqli_real_escape_string($con, (string) $live['name_b']);
    $goalsA = (int) $live['score_a'];
    $goalsB = (int) $live['score_b'];

    $sql = "INSERT INTO ratedresults (id, `Date`, idA, idB, NameA, NameB, GoalsA, GoalsB)
        VALUES ({$ratedId}, NOW(), {$host}, {$slave}, '{$nameA}', '{$nameB}', {$goalsA}, {$goalsB})";
    if (mysqli_query($con, $sql) === false) {
        return null;
    }

    $opsModule = $_SERVER['DOCUMENT_ROOT'] . '/ops/modules/process_completed_game.php';
    if (is_file($opsModule)) {
        require_once $opsModule;
        try {
            k2_ops_process_completed_game($con, $ratedId, false);
        } catch (Throwable $e) {
            // Sim continues; rated row exists for pulse cascade.
        }
    }

    mysqli_query($con, 'DELETE FROM resulttable WHERE GameID = ' . $gameId);

    return $ratedId;
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_start_next_live(mysqli $con, array &$state): void
{
    if ($state['queue'] === []) {
        return;
    }
    $spec = array_shift($state['queue']);
    if (!is_array($spec)) {
        return;
    }
    $gameId = (int) $state['next_game_id'];
    $state['next_game_id'] = $gameId + 1;
    $live = [
        'game_id' => $gameId,
        'host' => (int) $spec['host'],
        'slave' => (int) $spec['slave'],
        'name_a' => (string) $spec['name_a'],
        'name_b' => (string) $spec['name_b'],
        'goal_target' => (int) $spec['goal_target'],
        'score_a' => 0,
        'score_b' => 0,
        'half_countdown' => 15000,
        'period' => 1,
        'host_scores_next' => (bool) random_int(0, 1),
    ];
    k2_status_room_sim_mark_player_online($con, $live['host'], $state);
    k2_status_room_sim_mark_player_online($con, $live['slave'], $state);
    if (k2_status_room_sim_insert_live_row($con, $live)) {
        $state['live'][] = $live;
        $state['last_event'] = 'game_started:' . $gameId;
    }
}

/** @param array<string, mixed> $live */
function k2_status_room_sim_maybe_score(array &$live): void
{
    $total = (int) $live['score_a'] + (int) $live['score_b'];
    if ($total >= (int) $live['goal_target']) {
        return;
    }
    if (random_int(1, 100) > 75) {
        return;
    }
    if (!empty($live['host_scores_next'])) {
        $live['score_a'] = (int) $live['score_a'] + 1;
    } else {
        $live['score_b'] = (int) $live['score_b'] + 1;
    }
    $live['host_scores_next'] = !$live['host_scores_next'];
}

function k2_status_room_sim_tick(mysqli $con): void
{
    if (!k2_status_room_sim_is_allowed()) {
        return;
    }
    $state = k2_status_room_sim_load_state();
    if ($state === null || empty($state['active'])) {
        return;
    }

    $now = time();
    if ($now === (int) ($state['last_tick_at'] ?? 0)) {
        return;
    }
    $state['last_tick_at'] = $now;

    $liveList = is_array($state['live'] ?? null) ? $state['live'] : [];
    $maxConcurrent = (int) ($state['max_concurrent'] ?? 2);

    while (count($liveList) < $maxConcurrent && ($state['queue'] ?? []) !== []) {
        k2_status_room_sim_start_next_live($con, $state);
        $liveList = is_array($state['live'] ?? null) ? $state['live'] : [];
    }

    $stillLive = [];
    foreach ($liveList as $live) {
        if (!is_array($live)) {
            continue;
        }
        $live['half_countdown'] = max(0, (int) $live['half_countdown'] - 50);
        if ($live['half_countdown'] <= 7500 && (int) $live['period'] === 1) {
            $live['period'] = 2;
            $live['half_countdown'] = max($live['half_countdown'], 7500);
        }
        k2_status_room_sim_maybe_score($live);
        k2_status_room_sim_update_live_row($con, $live);

        $total = (int) $live['score_a'] + (int) $live['score_b'];
        if ($total >= (int) $live['goal_target']) {
            $ratedId = k2_status_room_sim_finish_live_row($con, $live);
            $state['completed_count'] = (int) ($state['completed_count'] ?? 0) + 1;
            $state['last_event'] = $ratedId !== null
                ? 'game_finished:' . $live['game_id'] . ':rated:' . $ratedId
                : 'game_finished:' . $live['game_id'];
            continue;
        }
        $stillLive[] = $live;
    }
    $state['live'] = $stillLive;

    while (count($state['live']) < $maxConcurrent && ($state['queue'] ?? []) !== []) {
        k2_status_room_sim_start_next_live($con, $state);
    }

    $done = (int) ($state['completed_count'] ?? 0) >= (int) ($state['game_count'] ?? 0);
    if ($done && $state['live'] === []) {
        $state['active'] = false;
        $state['last_event'] = 'complete';
    }

    k2_status_room_sim_save_state($state);
}

function k2_status_room_sim_tick_if_due(mysqli $con): void
{
    k2_status_room_sim_tick($con);
}
