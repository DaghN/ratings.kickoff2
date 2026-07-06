<?php
/**
 * Status room live — lobby sim engine (work DB only).
 *
 * @see docs/status-room-live-sim-spec.md
 */
declare(strict_types=1);

const K2_STATUS_ROOM_SIM_GAME_ID_BASE = 990000;
const K2_STATUS_ROOM_SIM_DEFAULT_GAMES = 20;
const K2_STATUS_ROOM_SIM_MAX_LIVE = 4;
const K2_STATUS_ROOM_SIM_MAX_PENDING = 3;
/** Per-game chance (1–100) that a match disconnects before the final whistle. */
const K2_STATUS_ROOM_SIM_DEFAULT_GAME_CRASH_PERCENT = 5;
/** Target online band — lobby tries to stay in range when L1 enabled. */
const K2_STATUS_ROOM_SIM_ONLINE_TARGET_MIN = 3;
const K2_STATUS_ROOM_SIM_ONLINE_TARGET_MAX = 8;
/** Default synthetic registrations per run when L2 enabled. */
const K2_STATUS_ROOM_SIM_DEFAULT_REGISTRATIONS = 3;
const K2_STATUS_ROOM_SIM_MAX_REGISTRATIONS = 10;
/** Live clock at kickoff — 5:00 in the 1st half (50 ticks/s). */
const K2_STATUS_ROOM_SIM_HALF_START_TICKS = 15000;
/** Finish after 1 wall minute — clock reads 4:00 left in the 1st half. */
const K2_STATUS_ROOM_SIM_FINISH_AT_TICKS = 12000;
/** Max simulated seconds processed per tick call (wall-clock catch-up when idle). */
const K2_STATUS_ROOM_SIM_MAX_CATCHUP_SECONDS = 600;
/** Hard wall-clock run limit from started_at — prevents zombie lobby if Stop is forgotten. */
const K2_STATUS_ROOM_SIM_MAX_WALL_SECONDS = 600;

function k2_status_room_sim_state_path(): string
{
    global $database;
    $db = isset($database) ? (string) $database : 'unknown';

    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'k2_status_room_live_sim_' . preg_replace('/[^a-zA-Z0-9_]/', '', $db) . '.json';
}

function k2_status_room_sim_is_allowed(): bool
{
    global $database;

    // Belt + suspenders: local work hostname AND work DB name (synced code must not run on staging/prod).
    if (!isset($database) || (string) $database !== 'ko2unity_work') {
        return false;
    }

    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');

    return (bool) preg_match('/^work\.ratingskickoff\.test$/', $host);
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

/** Cancel in-progress sim live rows (resulttable only — never ratedresults). */
function k2_status_room_sim_cancel_live_games(mysqli $con): int
{
    mysqli_query(
        $con,
        'DELETE FROM resulttable WHERE GameID >= ' . (int) K2_STATUS_ROOM_SIM_GAME_ID_BASE
            . ' AND HasStarted = 1 AND HasFinished = 0 AND Shelved = 0'
    );

    return (int) mysqli_affected_rows($con);
}

function k2_status_room_sim_logout_all_online(mysqli $con): int
{
    mysqli_query($con, 'UPDATE playertable SET IsOnline = 0 WHERE COALESCE(IsOnline, 0) <> 0');

    return (int) mysqli_affected_rows($con);
}

/** @return list<int> */
function k2_status_room_sim_build_lobby_pool(mysqli $con, int $count = 25): array
{
    $count = max(5, min(50, $count));
    $r = mysqli_query(
        $con,
        'SELECT ID FROM playertable WHERE NumberGames >= 1 AND ID > 0 ORDER BY RAND() LIMIT ' . $count
    );
    if ($r === false) {
        return [];
    }
    $ids = [];
    while ($row = mysqli_fetch_assoc($r)) {
        $ids[] = (int) $row['ID'];
    }
    mysqli_free_result($r);

    return $ids;
}

/**
 * @param array<string, mixed> $request
 * @return array{games: int, enable_l1: bool, enable_l2: bool, enable_l3: bool, crash_chance: int, registration_limit: int}
 */
function k2_status_room_sim_parse_start_options(array $request): array
{
    $games = isset($request['games']) ? (int) $request['games'] : K2_STATUS_ROOM_SIM_DEFAULT_GAMES;
    $games = max(0, min(40, $games));
    $enableL1 = !isset($request['l1']) || (string) $request['l1'] !== '0';
    $enableL2 = !isset($request['l2']) || (string) $request['l2'] !== '0';
    $enableL3 = !isset($request['l3']) || (string) $request['l3'] !== '0';
    $crash = isset($request['crash']) ? (int) $request['crash'] : K2_STATUS_ROOM_SIM_DEFAULT_GAME_CRASH_PERCENT;
    $crash = max(0, min(20, $crash));
    $regLimit = isset($request['registrations']) ? (int) $request['registrations'] : K2_STATUS_ROOM_SIM_DEFAULT_REGISTRATIONS;
    $regLimit = max(0, min(K2_STATUS_ROOM_SIM_MAX_REGISTRATIONS, $regLimit));
    if (!$enableL3) {
        $games = 0;
    }
    if (!$enableL2) {
        $regLimit = 0;
    }
    if (!$enableL1 && !$enableL2 && !$enableL3) {
        $enableL3 = true;
        $games = K2_STATUS_ROOM_SIM_DEFAULT_GAMES;
    }

    return [
        'games' => $games,
        'enable_l1' => $enableL1,
        'enable_l2' => $enableL2,
        'enable_l3' => $enableL3,
        'crash_chance' => $crash,
        'registration_limit' => $regLimit,
    ];
}

function k2_status_room_sim_next_synthetic_name(mysqli $con): ?string
{
    for ($attempt = 0; $attempt < 16; $attempt++) {
        $name = 'Sim_' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $esc = mysqli_real_escape_string($con, $name);
        $r = mysqli_query($con, "SELECT 1 FROM playertable WHERE Name = '" . $esc . "' LIMIT 1");
        if ($r === false) {
            return null;
        }
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        if ($row === null) {
            return $name;
        }
    }

    return null;
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_register_player(mysqli $con, array &$state): bool
{
    $name = k2_status_room_sim_next_synthetic_name($con);
    if ($name === null) {
        return false;
    }
    $nameEsc = mysqli_real_escape_string($con, $name);
    $guid = (string) random_int(100000000000000, 999999999999999);
    $sql = "INSERT INTO playertable (
        Name, JoinDate, LastLogin, LastGame, LastActive,
        Pref_Formation, Pref_AutoSlides, Pref_PBD, Pref_TrapFix, Country,
        LeastGoalsScored, LeastGoalsConceded, SmallestSumOfGoals,
        LowestRatedCulprit, LowestRating, Rating,
        ScoreStreak, MerchantStreak, ExactTenGoalStreak, WinMarginOneStreak, LossMarginOneStreak,
        GUID, LobbyTime, PlayerRank, Profile_AvatarURL, Profile_LinkURL, IsOnline, Display, LegalAccepted
    ) VALUES (
        '" . $nameEsc . "', NOW(), '1970-01-01 00:00:00', '1970-01-01 00:00:00', NOW(),
        0, 0, 0, 0, 'GB',
        50, 50, 50,
        5000.00, 5000.00, 1600.000000,
        0, 0, 0, 0, 0,
        '" . mysqli_real_escape_string($con, $guid) . "', 0, 9999, '', '', 0, 1, 0
    )";
    if (mysqli_query($con, $sql) === false) {
        return false;
    }
    $playerId = (int) mysqli_insert_id($con);
    if ($playerId < 1) {
        return false;
    }

    $opsTag = 'no_ops';
    $opsModule = $_SERVER['DOCUMENT_ROOT'] . '/ops/modules/process_player_registered.php';
    if (is_file($opsModule)) {
        require_once $opsModule;
        try {
            $opsResult = k2_ops_process_player_registered($con, $playerId, false);
            $opsTag = !empty($opsResult['entered_arena_inserted']) ? 'ops_entered_arena' : 'ops_no_milestone';
        } catch (Throwable $e) {
            $opsTag = 'ops_error';
        }
    }

    $state['last_event'] = 'registered:' . $playerId . ':' . $name . ':' . $opsTag;

    $pool = is_array($state['lobby_pool'] ?? null) ? $state['lobby_pool'] : [];
    $pool[] = $playerId;
    $state['lobby_pool'] = array_values(array_unique(array_map('intval', $pool)));
    $state['registration_count'] = (int) ($state['registration_count'] ?? 0) + 1;

    if (!empty($state['enable_l3'])) {
        k2_status_room_sim_append_registration_match($con, $state, $playerId);
    }

    return true;
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_tick_idle_lobby(mysqli $con, array &$state, ?int $now = null): void
{
    if (!empty($state['lobby_event_this_tick'])) {
        return;
    }
    $now = $now ?? time();
    $enableL1 = ($state['enable_l1'] ?? true) !== false;
    $enableL2 = !empty($state['enable_l2']);
    if (!$enableL1 && !$enableL2) {
        return;
    }

    $regCount = (int) ($state['registration_count'] ?? 0);
    $regLimit = (int) ($state['registration_limit'] ?? 0);
    $canRegister = $enableL2 && $regCount < $regLimit;
    if ($canRegister && random_int(1, 100) <= 12) {
        if (k2_status_room_sim_register_player($con, $state)) {
            $state['lobby_event_this_tick'] = true;

            return;
        }
    }
    if ($enableL1) {
        if (k2_status_room_sim_tick_lobby_presence($con, $state, $now)) {
            $state['lobby_event_this_tick'] = true;
        }
    }
}

function k2_status_room_sim_next_game_id(mysqli $con): int
{
    $r = mysqli_query(
        $con,
        'SELECT COALESCE(MAX(GameID), ' . (int) K2_STATUS_ROOM_SIM_GAME_ID_BASE . ') + 1 AS n
         FROM resulttable WHERE GameID >= ' . (int) K2_STATUS_ROOM_SIM_GAME_ID_BASE
    );
    if ($r === false) {
        return K2_STATUS_ROOM_SIM_GAME_ID_BASE + 1;
    }
    $row = mysqli_fetch_assoc($r);
    mysqli_free_result($r);

    return max(K2_STATUS_ROOM_SIM_GAME_ID_BASE + 1, (int) ($row['n'] ?? K2_STATUS_ROOM_SIM_GAME_ID_BASE + 1));
}

function k2_status_room_sim_player_name(mysqli $con, int $playerId): string
{
    if ($playerId <= 0) {
        return '';
    }
    $r = mysqli_query($con, 'SELECT Name FROM playertable WHERE ID = ' . $playerId . ' LIMIT 1');
    if ($r === false) {
        return '';
    }
    $row = mysqli_fetch_assoc($r);
    mysqli_free_result($r);

    return $row !== null ? (string) $row['Name'] : '';
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_pick_queue_partner(mysqli $con, array $state, int $excludeId): ?int
{
    $now = time();
    $busy = array_fill_keys(k2_status_room_sim_match_player_ids($state), true);
    $pool = is_array($state['lobby_pool'] ?? null) ? $state['lobby_pool'] : [];
    $candidates = [];
    foreach ($pool as $rawId) {
        $pid = (int) $rawId;
        if ($pid <= 0 || $pid === $excludeId) {
            continue;
        }
        if (isset($busy[$pid])) {
            continue;
        }
        if (k2_status_room_sim_player_in_grace($state, $pid, $now)) {
            continue;
        }
        $candidates[] = $pid;
    }
    if ($candidates === []) {
        $r = mysqli_query(
            $con,
            'SELECT ID FROM playertable WHERE ID <> ' . $excludeId
                . ' AND NumberGames >= 1 AND ID > 0 ORDER BY RAND() LIMIT 12'
        );
        if ($r !== false) {
            while ($row = mysqli_fetch_assoc($r)) {
                $pid = (int) $row['ID'];
                if (!isset($busy[$pid]) && !k2_status_room_sim_player_in_grace($state, $pid, $now)) {
                    $candidates[] = $pid;
                }
            }
            mysqli_free_result($r);
        }
    }
    if ($candidates === []) {
        return null;
    }

    return $candidates[random_int(0, count($candidates) - 1)];
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_append_registration_match(mysqli $con, array &$state, int $playerId): bool
{
    if ($playerId <= 0 || empty($state['active']) || empty($state['enable_l3'])) {
        return false;
    }
    $partnerId = k2_status_room_sim_pick_queue_partner($con, $state, $playerId);
    if ($partnerId === null) {
        return false;
    }
    $hostId = $playerId;
    $slaveId = $partnerId;
    if (random_int(0, 1) === 1) {
        $hostId = $partnerId;
        $slaveId = $playerId;
    }
    $nameA = k2_status_room_sim_player_name($con, $hostId);
    $nameB = k2_status_room_sim_player_name($con, $slaveId);
    if ($nameA === '' || $nameB === '') {
        return false;
    }
    if (!is_array($state['queue'] ?? null)) {
        $state['queue'] = [];
    }
    $state['queue'][] = [
        'host' => $hostId,
        'slave' => $slaveId,
        'name_a' => $nameA,
        'name_b' => $nameB,
    ];
    $state['game_count'] = (int) ($state['game_count'] ?? 0) + 1;
    $state['last_event'] = 'queued_reg_match:' . $playerId . ':' . $partnerId;

    return true;
}

/** @return list<array{host: int, slave: int, name_a: string, name_b: string}> */
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
        ];
    }

    return $queue;
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_normalize_state(array &$state): void
{
    if (!isset($state['pending_matches']) && is_array($state['pending_match'] ?? null)) {
        $state['pending_matches'] = [$state['pending_match']];
        unset($state['pending_match']);
    }
    if (!is_array($state['pending_matches'] ?? null)) {
        $state['pending_matches'] = [];
    }
    if (!is_array($state['live'] ?? null)) {
        $state['live'] = [];
    }
    if (!is_array($state['player_grace'] ?? null)) {
        $state['player_grace'] = [];
    }
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_count_online_in_pool(mysqli $con, array $state): int
{
    $pool = is_array($state['lobby_pool'] ?? null) ? $state['lobby_pool'] : [];
    if ($pool === []) {
        return 0;
    }
    $inList = implode(',', array_map('intval', $pool));
    if ($inList === '') {
        return 0;
    }
    $r = mysqli_query(
        $con,
        'SELECT COUNT(*) AS c FROM playertable WHERE ID IN (' . $inList . ') AND COALESCE(IsOnline, 0) <> 0'
    );
    if ($r === false) {
        return 0;
    }
    $row = mysqli_fetch_assoc($r);
    mysqli_free_result($r);

    return (int) ($row['c'] ?? 0);
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_set_player_grace(array &$state, int $playerId, int $until): void
{
    if ($playerId <= 0) {
        return;
    }
    if (!is_array($state['player_grace'] ?? null)) {
        $state['player_grace'] = [];
    }
    $state['player_grace'][(string) $playerId] = $until;
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_player_in_grace(array $state, int $playerId, int $now): bool
{
    if ($playerId <= 0) {
        return false;
    }
    $grace = is_array($state['player_grace'] ?? null) ? $state['player_grace'] : [];
    $until = (int) ($grace[(string) $playerId] ?? 0);

    return $until > $now;
}

/** @return array<string, mixed> */
function k2_status_room_sim_start(mysqli $con, array $request = []): array
{
    if (!k2_status_room_sim_is_allowed()) {
        return ['ok' => false, 'error' => 'sim_not_allowed'];
    }

    $parsed = k2_status_room_sim_parse_start_options($request);
    $gameCount = $parsed['games'];
    $queue = $gameCount > 0 ? k2_status_room_sim_build_queue($con, $gameCount) : [];
    if ($parsed['enable_l3'] && $queue === []) {
        return ['ok' => false, 'error' => 'no_players'];
    }

    $now = time();
    $state = [
        'active' => true,
        'started_at' => $now,
        'last_tick_at' => 0,
        'game_count' => count($queue),
        'completed_count' => 0,
        'max_concurrent' => K2_STATUS_ROOM_SIM_MAX_LIVE,
        'next_game_id' => k2_status_room_sim_next_game_id($con),
        'queue' => $queue,
        'live' => [],
        'pending_matches' => [],
        'next_match_at' => $now,
        'last_kickoff_at' => 0,
        'lobby_pool' => k2_status_room_sim_build_lobby_pool($con),
        'player_grace' => [],
        'enable_l1' => $parsed['enable_l1'],
        'enable_l2' => $parsed['enable_l2'],
        'enable_l3' => $parsed['enable_l3'],
        'crash_chance_percent' => $parsed['crash_chance'],
        'registration_limit' => $parsed['registration_limit'],
        'registration_count' => 0,
        'last_event' => 'started',
    ];
    k2_status_room_sim_save_state($state);

    return [
        'ok' => true,
        'game_count' => count($queue),
        'max_concurrent' => $state['max_concurrent'],
        'registration_limit' => $parsed['registration_limit'],
        'enable_l1' => $parsed['enable_l1'],
        'enable_l2' => $parsed['enable_l2'],
        'enable_l3' => $parsed['enable_l3'],
        'crash_chance' => $parsed['crash_chance'],
        'message' => 'Sim started — auto-stops after 10 min; ticks on wall clock (Status load, pulse, or sim control page).',
    ];
}

/** Seconds left before wall-clock cap (0 when inactive or expired). */
function k2_status_room_sim_wall_seconds_remaining(array $state, ?int $now = null): int
{
    if (empty($state['active'])) {
        return 0;
    }
    $started = (int) ($state['started_at'] ?? 0);
    if ($started <= 0) {
        return K2_STATUS_ROOM_SIM_MAX_WALL_SECONDS;
    }
    $now = $now ?? time();
    $elapsed = max(0, $now - $started);

    return max(0, K2_STATUS_ROOM_SIM_MAX_WALL_SECONDS - $elapsed);
}

function k2_status_room_sim_is_wall_limit_reached(array $state, ?int $now = null): bool
{
    return k2_status_room_sim_wall_seconds_remaining($state, $now) <= 0;
}

/**
 * Stop cleanup shared by manual Stop, wall cap, and stalled idle halt.
 *
 * @param array<string, mixed> $state
 * @return array{cancelled_live: int, logged_out: int}
 */
function k2_status_room_sim_halt_active(mysqli $con, array &$state, string $lastEvent): array
{
    $cancelledLive = k2_status_room_sim_cancel_live_games($con);
    $loggedOut = k2_status_room_sim_logout_all_online($con);
    $state['active'] = false;
    $state['queue'] = [];
    $state['live'] = [];
    $state['pending_matches'] = [];
    $state['player_grace'] = [];
    $state['last_event'] = $lastEvent;
    k2_status_room_sim_save_state($state);

    require_once __DIR__ . '/status_room_pulse_cache.php';
    k2_status_pulse_cache_invalidate('signals');

    return [
        'cancelled_live' => $cancelledLive,
        'logged_out' => $loggedOut,
    ];
}

/** @return array<string, mixed> */
function k2_status_room_sim_stop(mysqli $con): array
{
    $state = k2_status_room_sim_load_state();
    if ($state === null) {
        $cancelledLive = k2_status_room_sim_cancel_live_games($con);
        $loggedOut = k2_status_room_sim_logout_all_online($con);

        require_once __DIR__ . '/status_room_pulse_cache.php';
        k2_status_pulse_cache_invalidate('signals');

        return [
            'ok' => true,
            'message' => 'Sim stopped — ' . $loggedOut . ' player(s) logged out, ' . $cancelledLive . ' live game(s) cancelled.',
        ];
    }

    $halt = k2_status_room_sim_halt_active($con, $state, 'stopped');

    return [
        'ok' => true,
        'message' => 'Sim stopped — ' . $halt['logged_out'] . ' player(s) logged out, ' . $halt['cancelled_live'] . ' live game(s) cancelled, queue cleared.',
    ];
}

/** @return array<string, mixed> */
function k2_status_room_sim_public_status(?array $state, ?mysqli $con = null): array
{
    if ($state === null) {
        return [
            'active' => false,
            'completed_count' => 0,
            'game_count' => 0,
            'live_count' => 0,
            'queued_count' => 0,
            'registration_count' => 0,
            'registration_limit' => 0,
            'last_event' => '',
            'wall_seconds_remaining' => 0,
            'max_wall_seconds' => K2_STATUS_ROOM_SIM_MAX_WALL_SECONDS,
        ];
    }
    k2_status_room_sim_normalize_state($state);
    $onlineCount = ($con instanceof mysqli) ? k2_status_room_sim_count_online_in_pool($con, $state) : 0;

    return [
        'active' => !empty($state['active']),
        'completed_count' => (int) ($state['completed_count'] ?? 0),
        'game_count' => (int) ($state['game_count'] ?? 0),
        'live_count' => is_array($state['live'] ?? null) ? count($state['live']) : 0,
        'queued_count' => is_array($state['queue'] ?? null) ? count($state['queue']) : 0,
        'registration_count' => (int) ($state['registration_count'] ?? 0),
        'registration_limit' => (int) ($state['registration_limit'] ?? 0),
        'enable_l1' => ($state['enable_l1'] ?? true) !== false,
        'enable_l2' => !empty($state['enable_l2']),
        'enable_l3' => ($state['enable_l3'] ?? true) !== false,
        'crash_chance' => (int) ($state['crash_chance_percent'] ?? K2_STATUS_ROOM_SIM_DEFAULT_GAME_CRASH_PERCENT),
        'max_concurrent' => K2_STATUS_ROOM_SIM_MAX_LIVE,
        'pending_count' => is_array($state['pending_matches'] ?? null) ? count($state['pending_matches']) : 0,
        'online_count' => $onlineCount,
        'pending_phase' => k2_status_room_sim_pending_phase_label($state),
        'last_event' => (string) ($state['last_event'] ?? ''),
        'wall_seconds_remaining' => k2_status_room_sim_wall_seconds_remaining($state),
        'max_wall_seconds' => K2_STATUS_ROOM_SIM_MAX_WALL_SECONDS,
    ];
}

function k2_status_room_sim_pending_phase_label(array $state): string
{
    k2_status_room_sim_normalize_state($state);
    $pending = $state['pending_matches'] ?? [];
    if (!is_array($pending) || $pending === []) {
        return '';
    }
    $labels = [];
    foreach ($pending as $item) {
        if (!is_array($item)) {
            continue;
        }
        $phase = (string) ($item['phase'] ?? '');
        if ($phase !== '') {
            $labels[] = $phase;
        }
    }

    return implode(',', $labels);
}

function k2_status_room_sim_mark_player_online(mysqli $con, int $playerId): void
{
    if ($playerId <= 0) {
        return;
    }
    // Login event only: online flag + LastLogin (never set on registration alone).
    mysqli_query($con, 'UPDATE playertable SET IsOnline = 1, LastLogin = NOW() WHERE ID = ' . $playerId);
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

function k2_status_room_sim_finish_live_row(mysqli $con, array $live): array
{
    $gameId = (int) $live['game_id'];

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
        return ['rated_id' => null, 'ops' => 'insert_failed'];
    }

    $opsTag = 'no_ops';
    $opsModule = $_SERVER['DOCUMENT_ROOT'] . '/ops/modules/process_completed_game.php';
    if (is_file($opsModule)) {
        require_once $opsModule;
        try {
            $opsResult = k2_ops_process_completed_game($con, $ratedId, false);
            if (!empty($opsResult['skipped'])) {
                $opsTag = 'ops_skipped:' . (string) ($opsResult['skip_reason'] ?? '?');
            } elseif (!empty($opsResult['committed'])) {
                $opsTag = 'ops_committed';
            } else {
                $opsTag = 'ops_no_commit';
            }
        } catch (Throwable $e) {
            $opsTag = 'ops_error';
        }
    }

    mysqli_query($con, 'DELETE FROM resulttable WHERE GameID = ' . $gameId);

    require_once __DIR__ . '/status_room_pulse_cache.php';
    k2_status_pulse_cache_invalidate('signals');

    return ['rated_id' => $ratedId, 'ops' => $opsTag];
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_begin_pending_match(array &$state, ?int $now = null): void
{
    $now = $now ?? time();
    if (($state['queue'] ?? []) === []) {
        return;
    }
    k2_status_room_sim_normalize_state($state);
    $pending = $state['pending_matches'];
    if (count($pending) >= K2_STATUS_ROOM_SIM_MAX_PENDING) {
        return;
    }
    $spec = array_shift($state['queue']);
    if (!is_array($spec)) {
        return;
    }
    $pending[] = [
        'spec' => $spec,
        'phase' => 'login_host',
        'wait_until' => $now + random_int(2, 6),
    ];
    $state['pending_matches'] = $pending;
    $state['last_kickoff_at'] = $now;
}

/**
 * Advance one pending kickoff item by one phase.
 *
 * @param array<string, mixed> $item
 * @return bool true when item should be removed from pending_matches
 */
function k2_status_room_sim_advance_one_pending(mysqli $con, array &$state, array &$item, ?int $now = null): bool
{
    $now = $now ?? time();
    if ($now < (int) ($item['wait_until'] ?? 0)) {
        return false;
    }
    $spec = is_array($item['spec'] ?? null) ? $item['spec'] : null;
    if ($spec === null) {
        return true;
    }

    $phase = (string) ($item['phase'] ?? '');
    if ($phase === 'login_host') {
        k2_status_room_sim_mark_player_online($con, (int) $spec['host']);
        $item['phase'] = 'login_slave';
        $item['wait_until'] = $now + random_int(2, 6);
        $state['last_event'] = 'login:' . (int) $spec['host'];
        $state['lobby_event_this_tick'] = true;

        return false;
    }
    if ($phase === 'login_slave') {
        k2_status_room_sim_mark_player_online($con, (int) $spec['slave']);
        $item['phase'] = 'kickoff';
        $item['wait_until'] = $now + random_int(3, 8);
        $state['last_event'] = 'login:' . (int) $spec['slave'];
        $state['lobby_event_this_tick'] = true;

        return false;
    }
    if ($phase === 'kickoff') {
        $hostId = (int) $spec['host'];
        $slaveId = (int) $spec['slave'];
        if (!k2_status_room_sim_both_online($con, $hostId, $slaveId)) {
            array_unshift($state['queue'], $spec);
            $state['next_match_at'] = $now + random_int(3, 8);
            $state['last_event'] = 'kickoff_aborted:not_online';

            return true;
        }
        k2_status_room_sim_kickoff_live_match($con, $state, $spec, $now);

        return true;
    }

    return true;
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_advance_pending_matches(mysqli $con, array &$state, ?int $now = null): void
{
    if (!empty($state['lobby_event_this_tick'])) {
        return;
    }
    k2_status_room_sim_normalize_state($state);
    $now = $now ?? time();
    $pending = $state['pending_matches'];
    $remaining = [];
    $advanced = false;
    for ($i = 0, $n = count($pending); $i < $n; $i++) {
        $item = $pending[$i];
        if (!is_array($item)) {
            continue;
        }
        if (!$advanced && $now >= (int) ($item['wait_until'] ?? 0)) {
            if (k2_status_room_sim_advance_one_pending($con, $state, $item, $now)) {
                $advanced = true;
                continue;
            }
            $remaining[] = $item;
            $advanced = true;
        } else {
            $remaining[] = $item;
        }
    }
    $state['pending_matches'] = $remaining;
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_maybe_start_pending_matches(mysqli $con, array &$state, ?int $now = null): void
{
    if (($state['enable_l3'] ?? true) === false) {
        return;
    }
    k2_status_room_sim_normalize_state($state);
    $now = $now ?? time();
    $liveCount = count($state['live']);
    $pendingCount = count($state['pending_matches']);
    if ($now < (int) ($state['next_match_at'] ?? 0)) {
        return;
    }
    if (
        ($state['queue'] ?? []) !== []
        && count($state['live']) < K2_STATUS_ROOM_SIM_MAX_LIVE
        && count($state['pending_matches']) < K2_STATUS_ROOM_SIM_MAX_PENDING
        && ($now - (int) ($state['last_kickoff_at'] ?? 0)) >= 3
    ) {
        k2_status_room_sim_begin_pending_match($state, $now);
    }
}

/** @param array<string, mixed> $spec */
function k2_status_room_sim_kickoff_live_match(mysqli $con, array &$state, array $spec, ?int $now = null): void
{
    k2_status_room_sim_normalize_state($state);
    $now = $now ?? time();
    if (count($state['live']) >= K2_STATUS_ROOM_SIM_MAX_LIVE) {
        array_unshift($state['queue'], $spec);
        $state['next_match_at'] = $now + random_int(2, 5);

        return;
    }
    $gameId = (int) $state['next_game_id'];
    $state['next_game_id'] = $gameId + 1;
    $crashPct = (int) ($state['crash_chance_percent'] ?? K2_STATUS_ROOM_SIM_DEFAULT_GAME_CRASH_PERCENT);
    $live = [
        'game_id' => $gameId,
        'host' => (int) $spec['host'],
        'slave' => (int) $spec['slave'],
        'name_a' => (string) $spec['name_a'],
        'name_b' => (string) $spec['name_b'],
        'score_a' => 0,
        'score_b' => 0,
        'half_countdown' => K2_STATUS_ROOM_SIM_HALF_START_TICKS,
        'period' => 1,
        'next_goal_at' => $now + random_int(5, 12),
        'host_scores_next' => (bool) random_int(0, 1),
        'crash_scheduled' => $crashPct > 0 && random_int(1, 100) <= $crashPct,
        'crash_at' => $now + random_int(15, 50),
    ];
    if (k2_status_room_sim_insert_live_row($con, $live)) {
        if (!is_array($state['live'] ?? null)) {
            $state['live'] = [];
        }
        $state['live'][] = $live;
        $state['last_event'] = 'game_started:' . $gameId;
    } else {
        $state['next_match_at'] = $now + random_int(3, 8);
        $state['last_event'] = 'kickoff_failed:' . $gameId;
    }
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_start_next_live(mysqli $con, array &$state): void
{
    // Legacy entry — use pending_match flow instead.
    k2_status_room_sim_begin_pending_match($state);
}

/** @return list<int> */
function k2_status_room_sim_match_player_ids(array $state): array
{
    k2_status_room_sim_normalize_state($state);
    $ids = [];
    foreach ($state['live'] ?? [] as $live) {
        if (!is_array($live)) {
            continue;
        }
        $ids[(int) $live['host']] = true;
        $ids[(int) $live['slave']] = true;
    }
    foreach ($state['pending_matches'] ?? [] as $pending) {
        if (!is_array($pending) || !is_array($pending['spec'] ?? null)) {
            continue;
        }
        $ids[(int) $pending['spec']['host']] = true;
        $ids[(int) $pending['spec']['slave']] = true;
    }

    return array_keys($ids);
}

function k2_status_room_sim_player_is_online(mysqli $con, int $playerId): bool
{
    if ($playerId <= 0) {
        return false;
    }
    $r = mysqli_query(
        $con,
        'SELECT COALESCE(IsOnline, 0) AS v FROM playertable WHERE ID = ' . $playerId . ' LIMIT 1'
    );
    if ($r === false) {
        return false;
    }
    $row = mysqli_fetch_assoc($r);
    mysqli_free_result($r);

    return (int) ($row['v'] ?? 0) !== 0;
}

function k2_status_room_sim_both_online(mysqli $con, int $hostId, int $slaveId): bool
{
    return k2_status_room_sim_player_is_online($con, $hostId)
        && k2_status_room_sim_player_is_online($con, $slaveId);
}

/** @param array<string, mixed> $live */
function k2_status_room_sim_abort_live_game(mysqli $con, array $live, array &$state, string $reason, ?int $now = null): void
{
    $now = $now ?? time();
    $gameId = (int) $live['game_id'];
    mysqli_query($con, 'DELETE FROM resulttable WHERE GameID = ' . $gameId);
    $state['next_match_at'] = min($now + random_int(3, 8), (int) ($state['next_match_at'] ?? $now));
    $state['last_event'] = 'game_cancelled:' . $gameId . ':' . $reason;
}

/**
 * Rare mid-match disconnect — one player logs out, live row cancelled (no rated result).
 *
 * @param array<string, mixed> $live
 * @param array<string, mixed> $state
 */
function k2_status_room_sim_maybe_crash(mysqli $con, array $live, array &$state, int $now): bool
{
    if (empty($live['crash_scheduled']) || $now < (int) ($live['crash_at'] ?? 0)) {
        return false;
    }
    $hostId = (int) $live['host'];
    $slaveId = (int) $live['slave'];
    $crashId = random_int(0, 1) === 0 ? $hostId : $slaveId;
    mysqli_query($con, 'UPDATE playertable SET IsOnline = 0 WHERE ID = ' . $crashId);
    k2_status_room_sim_abort_live_game($con, $live, $state, 'player_crash:' . $crashId, $now);
    $state['last_event'] = 'crash:' . $crashId . ':game_cancelled:' . (int) $live['game_id'];

    return true;
}

/** @return bool true when a login or logout ran */
function k2_status_room_sim_tick_lobby_presence(mysqli $con, array &$state, ?int $now = null): bool
{
    $now = $now ?? time();
    $pool = is_array($state['lobby_pool'] ?? null) ? $state['lobby_pool'] : [];
    if ($pool === []) {
        return false;
    }
    $online = k2_status_room_sim_count_online_in_pool($con, $state);
    $target = random_int(K2_STATUS_ROOM_SIM_ONLINE_TARGET_MIN, K2_STATUS_ROOM_SIM_ONLINE_TARGET_MAX);
    $roll = random_int(1, 100);
    if ($online < $target) {
        if ($roll > 80) {
            return false;
        }
        $preferLogin = true;
    } elseif ($online > $target) {
        if ($roll > 45) {
            return false;
        }
        $preferLogin = false;
    } else {
        if ($roll > 40) {
            return false;
        }
        $preferLogin = random_int(0, 1) === 0;
    }

    $pool = array_values(array_unique(array_map('intval', $pool)));
    $inList = implode(',', $pool);
    if ($inList === '') {
        return false;
    }
    $inMatch = k2_status_room_sim_match_player_ids($state);
    $notInMatchSql = $inMatch !== [] ? ' AND ID NOT IN (' . implode(',', array_map('intval', $inMatch)) . ')' : '';

    if ($preferLogin) {
        $r = mysqli_query(
            $con,
            'SELECT ID FROM playertable WHERE ID IN (' . $inList . ') AND COALESCE(IsOnline, 0) = 0 ORDER BY RAND() LIMIT 1'
        );
        if ($r === false) {
            return false;
        }
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        if ($row === null) {
            return false;
        }
        $pid = (int) $row['ID'];
        k2_status_room_sim_mark_player_online($con, $pid);
        $state['last_event'] = 'login:' . $pid;

        return true;
    }

    $graceSql = '';
    foreach ($pool as $pid) {
        if (k2_status_room_sim_player_in_grace($state, (int) $pid, $now)) {
            $graceSql .= ($graceSql === '' ? '' : ',') . (int) $pid;
        }
    }
    $notGraceSql = $graceSql !== '' ? ' AND ID NOT IN (' . $graceSql . ')' : '';

    $r = mysqli_query(
        $con,
        'SELECT ID FROM playertable WHERE ID IN (' . $inList . ') AND COALESCE(IsOnline, 0) <> 0'
            . $notInMatchSql . $notGraceSql . ' ORDER BY RAND() LIMIT 1'
    );
    if ($r === false) {
        return false;
    }
    $row = mysqli_fetch_assoc($r);
    mysqli_free_result($r);
    if ($row === null) {
        return false;
    }
    $pid = (int) $row['ID'];
    mysqli_query($con, 'UPDATE playertable SET IsOnline = 0 WHERE ID = ' . $pid);
    $state['last_event'] = 'logout:' . $pid;

    return true;
}

/** @param array<string, mixed> $live */
function k2_status_room_sim_ensure_live_goal_schedule(array &$live, int $now): void
{
    if (!isset($live['next_goal_at']) || (int) $live['next_goal_at'] <= 0) {
        $live['next_goal_at'] = $now + random_int(5, 12);
    }
    if (!isset($live['host_scores_next'])) {
        $live['host_scores_next'] = (bool) random_int(0, 1);
    }
}

/**
 * At most one goal per tick when wall clock reaches next_goal_at.
 *
 * @param array<string, mixed> $state
 * @param array<string, mixed> $live
 */
function k2_status_room_sim_maybe_score_goal(mysqli $con, array &$state, array &$live, int $now): void
{
    k2_status_room_sim_ensure_live_goal_schedule($live, $now);
    if ($now < (int) ($live['next_goal_at'] ?? 0)) {
        return;
    }
    if ((int) ($live['half_countdown'] ?? 0) <= K2_STATUS_ROOM_SIM_FINISH_AT_TICKS) {
        return;
    }

    if (!empty($live['host_scores_next'])) {
        $live['score_a'] = (int) ($live['score_a'] ?? 0) + 1;
        $live['host_scores_next'] = false;
    } else {
        $live['score_b'] = (int) ($live['score_b'] ?? 0) + 1;
        $live['host_scores_next'] = true;
    }
    $live['next_goal_at'] = $now + random_int(5, 15);
    $state['last_event'] = 'goal:' . (int) $live['game_id'] . ':' . (int) $live['score_a'] . '-' . (int) $live['score_b'];
    k2_status_room_sim_update_live_row($con, $live);
}

/** @param array<string, mixed> $live */
function k2_status_room_sim_tick_live_match(mysqli $con, array &$state, array &$live, int $now): ?array
{
    k2_status_room_sim_ensure_live_goal_schedule($live, $now);
    $live['half_countdown'] = max(0, (int) $live['half_countdown'] - 50);
    k2_status_room_sim_maybe_score_goal($con, $state, $live, $now);
    k2_status_room_sim_update_live_row($con, $live);

    if ((int) $live['period'] === 1 && (int) $live['half_countdown'] <= K2_STATUS_ROOM_SIM_FINISH_AT_TICKS) {
        $state['last_event'] = 'whistle:' . $live['game_id'] . ':' . (int) $live['score_a'] . '-' . (int) $live['score_b'];

        return $live;
    }

    return null;
}

/** @param array<string, mixed> $state */
function k2_status_room_sim_tick_all_live(mysqli $con, array &$state, int $now): void
{
    k2_status_room_sim_normalize_state($state);
    $liveList = is_array($state['live'] ?? null) ? $state['live'] : [];
    $state['live'] = [];
    foreach ($liveList as $live) {
        if (!is_array($live)) {
            continue;
        }
        $hostId = (int) $live['host'];
        $slaveId = (int) $live['slave'];
        if (!k2_status_room_sim_both_online($con, $hostId, $slaveId)) {
            k2_status_room_sim_abort_live_game($con, $live, $state, 'player_offline', $now);
            continue;
        }
        if (k2_status_room_sim_maybe_crash($con, $live, $state, $now)) {
            continue;
        }
        $finished = k2_status_room_sim_tick_live_match($con, $state, $live, $now);
        if ($finished !== null) {
            $finish = k2_status_room_sim_finish_live_row($con, $finished);
            $state['completed_count'] = (int) ($state['completed_count'] ?? 0) + 1;
            k2_status_room_sim_set_player_grace($state, $hostId, $now + random_int(8, 25));
            k2_status_room_sim_set_player_grace($state, $slaveId, $now + random_int(8, 25));
            $state['next_match_at'] = min($now + random_int(2, 6), (int) ($state['next_match_at'] ?? $now));
            $ratedId = $finish['rated_id'] ?? null;
            $ops = (string) ($finish['ops'] ?? '');
            $state['last_event'] = $ratedId !== null
                ? 'game_finished:' . $finished['game_id'] . ':rated:' . $ratedId . ':' . $ops
                : 'game_finished:' . $finished['game_id'] . ':' . $ops;
            continue;
        }
        $state['live'][] = $live;
    }
}

function k2_status_room_sim_run_tick_at(mysqli $con, array &$state, int $tickAt): void
{
    k2_status_room_sim_normalize_state($state);
    $state['lobby_event_this_tick'] = false;

    if (($state['enable_l3'] ?? true) !== false) {
        k2_status_room_sim_advance_pending_matches($con, $state, $tickAt);
        k2_status_room_sim_tick_all_live($con, $state, $tickAt);
        k2_status_room_sim_maybe_start_pending_matches($con, $state, $tickAt);
    }

    k2_status_room_sim_tick_idle_lobby($con, $state, $tickAt);

    $l3Enabled = ($state['enable_l3'] ?? true) !== false;
    $done = $l3Enabled && (int) ($state['completed_count'] ?? 0) >= (int) ($state['game_count'] ?? 0);
    $idle = ($state['live'] ?? []) === []
        && ($state['pending_matches'] ?? []) === []
        && ($state['queue'] ?? []) === [];
    if ($idle && $done) {
        $state['active'] = false;
        $state['last_event'] = 'complete';
    } elseif ($idle && $l3Enabled && (int) ($state['game_count'] ?? 0) > 0 && !$done) {
        k2_status_room_sim_halt_active($con, $state, 'stalled');
    }
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

    if (k2_status_room_sim_is_wall_limit_reached($state, $now)) {
        k2_status_room_sim_halt_active($con, $state, 'time_limit');

        return;
    }

    $lastTick = (int) ($state['last_tick_at'] ?? 0);
    if ($lastTick >= $now) {
        return;
    }

    if ($lastTick === 0) {
        k2_status_room_sim_run_tick_at($con, $state, $now);
        $state['last_tick_at'] = $now;
        k2_status_room_sim_save_state($state);
        require_once __DIR__ . '/status_room_pulse_cache.php';
        k2_status_pulse_cache_invalidate('signals');

        return;
    }

    $elapsed = $now - $lastTick;
    $steps = min($elapsed, K2_STATUS_ROOM_SIM_MAX_CATCHUP_SECONDS);
    $endAt = $lastTick + $steps;

    for ($tickAt = $lastTick + 1; $tickAt <= $endAt; $tickAt++) {
        k2_status_room_sim_run_tick_at($con, $state, $tickAt);
        if (empty($state['active'])) {
            break;
        }
    }

    $state['last_tick_at'] = $endAt;
    k2_status_room_sim_save_state($state);

    require_once __DIR__ . '/status_room_pulse_cache.php';
    k2_status_pulse_cache_invalidate('signals');
}

function k2_status_room_sim_tick_if_due(mysqli $con): void
{
    k2_status_room_sim_tick($con);
}
