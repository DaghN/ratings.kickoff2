<?php
/**
 * Status room live pulse — signal bundle + section payloads.
 *
 * @see docs/status-room-live-policy.md
 */
declare(strict_types=1);

require_once __DIR__ . '/status_queries.php';
require_once __DIR__ . '/k2_league_table_render.php';
require_once __DIR__ . '/lb_column_help.php';
require_once __DIR__ . '/lb_player_filters.php';
require_once __DIR__ . '/period_activity_leaderboard_query.php';

function k2_status_pulse_fingerprint(array $parts): string
{
    return substr(hash('sha256', json_encode($parts, JSON_UNESCAPED_UNICODE)), 0, 16);
}

function k2_status_pulse_live_fingerprint(?array $games): string
{
    if ($games === null || $games === []) {
        return 'empty';
    }
    $rows = [];
    foreach ($games as $g) {
        $rows[] = [
            (int) ($g['game_id'] ?? 0),
            (int) ($g['score_a'] ?? 0),
            (int) ($g['score_b'] ?? 0),
            (int) ($g['period'] ?? 0),
        ];
    }

    return k2_status_pulse_fingerprint($rows);
}

/**
 * Minimal live rows for client half-clock resync every heartbeat (SRL-9).
 *
 * @param list<array<string, mixed>>|null $games
 * @return list<array{game_id: int, half_countdown: int, period: int}>
 */
function k2_status_pulse_live_clock_payload(?array $games): array
{
    if ($games === null || $games === []) {
        return [];
    }
    $out = [];
    foreach ($games as $g) {
        $gameId = (int) ($g['game_id'] ?? 0);
        if ($gameId < 1) {
            continue;
        }
        $out[] = [
            'game_id' => $gameId,
            'half_countdown' => (int) ($g['half_countdown'] ?? 0),
            'period' => (int) ($g['period'] ?? 0),
        ];
    }

    return $out;
}

/** @param list<array{id: int, name: string}> $online */
function k2_status_pulse_online_fingerprint(array $online): string
{
    if ($online === []) {
        return 'empty';
    }
    $ids = array_map(static fn(array $r): int => (int) $r['id'], $online);

    return implode(',', $ids);
}

function k2_status_pulse_league_total_games(mysqli $con, string $period, string $key): int
{
    if ($key === '') {
        return 0;
    }
    if (!function_exists('k2_period_activity_total_games')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
    }

    return k2_period_activity_total_games($con, $period, $key);
}

/**
 * @return array<string, mixed>
 */
function k2_status_pulse_period_keys(DateTimeImmutable $serverNow): array
{
    $keys = [];
    foreach (['day', 'week', 'month', 'year'] as $period) {
        $bounds = k2_status_league_period_bounds($period, 0, $serverNow);
        $keys[$period] = $bounds !== null
            ? (k2_status_period_activity_key_from_bounds($period, $bounds) ?? '')
            : '';
    }

    return $keys;
}

/**
 * Read lobby signals from the database (fresh each call — no cross-request cache).
 *
 * @return array{signals: array<string, mixed>, revision: string, server_now_epoch: int, _live_games?: array, _online?: array}
 */
function k2_status_pulse_collect_signals(mysqli $con, string $leaguePeriod, string $leagueKey): array
{
    $serverClock = k2_status_server_clock($con);
    $serverNow = $serverClock['now'];
    $serverNowEpoch = $serverNow->getTimestamp();

    $lastRatedId = 0;
    $r = mysqli_query($con, 'SELECT MAX(id) AS v FROM ratedresults');
    if ($r !== false) {
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        $lastRatedId = (int) ($row['v'] ?? 0);
    }

    $gamesPlayed = 0;
    $r = mysqli_query($con, 'SELECT GamesPlayed AS v FROM generalstatstable WHERE id = 1 LIMIT 1');
    if ($r !== false) {
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        if ($row && $row['v'] !== null) {
            $gamesPlayed = (int) $row['v'];
        }
    }

    $liveErr = null;
    $liveGames = k2_status_live_games($con, 10, $liveErr) ?? [];

    $onlineErr = null;
    $online = k2_status_online_players($con, $onlineErr) ?? [];

    $lastLoginEpoch = 0;
    $lastLoginId = 0;
    $r = mysqli_query($con, 'SELECT ID, LastLogin FROM playertable ORDER BY LastLogin DESC LIMIT 1');
    if ($r !== false) {
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        if ($row) {
            $lastLoginId = (int) ($row['ID'] ?? 0);
            $ts = strtotime((string) ($row['LastLogin'] ?? ''));
            $lastLoginEpoch = $ts === false ? 0 : (int) $ts;
        }
    }

    $lastJoinEpoch = 0;
    $lastJoinId = 0;
    $r = mysqli_query($con, 'SELECT ID, JoinDate FROM playertable ORDER BY JoinDate DESC LIMIT 1');
    if ($r !== false) {
        $row = mysqli_fetch_assoc($r);
        mysqli_free_result($r);
        if ($row) {
            $lastJoinId = (int) ($row['ID'] ?? 0);
            $ts = strtotime((string) ($row['JoinDate'] ?? ''));
            $lastJoinEpoch = $ts === false ? 0 : (int) $ts;
        }
    }

    $periodKeys = k2_status_pulse_period_keys($serverNow);

    $leaguePeriod = in_array($leaguePeriod, ['day', 'week', 'month', 'year'], true) ? $leaguePeriod : 'week';
    $leagueKey = $leagueKey !== '' ? $leagueKey : (string) ($periodKeys[$leaguePeriod] ?? '');
    $leagueTotalGames = k2_status_pulse_league_total_games($con, $leaguePeriod, $leagueKey);

    $signals = [
        'last_rated_id' => $lastRatedId,
        'games_played' => $gamesPlayed,
        'live_fp' => k2_status_pulse_live_fingerprint($liveGames),
        'online_fp' => k2_status_pulse_online_fingerprint($online),
        'last_login_epoch' => $lastLoginEpoch,
        'last_login_id' => $lastLoginId,
        'last_join_epoch' => $lastJoinEpoch,
        'last_join_id' => $lastJoinId,
        'league_fp' => k2_status_pulse_fingerprint([
            $leaguePeriod,
            $leagueKey,
            $leagueTotalGames,
        ]),
        'period_keys' => $periodKeys,
    ];
    $revision = k2_status_pulse_fingerprint($signals);

    return [
        'signals' => $signals,
        'revision' => $revision,
        'server_now_epoch' => $serverNowEpoch,
        '_live_games' => $liveGames,
        '_online' => $online,
    ];
}

/** Client GET params vs fresh server signals — sole gate for `changed: false`. */
function k2_status_pulse_client_signals_stale(array $prevSignals, array $serverSignals): bool
{
    $keys = [
        'last_rated_id',
        'games_played',
        'live_fp',
        'online_fp',
        'last_login_epoch',
        'last_login_id',
        'last_join_epoch',
        'last_join_id',
        'league_fp',
    ];
    foreach ($keys as $key) {
        $isStr = in_array($key, ['live_fp', 'online_fp', 'league_fp'], true);
        $prev = $isStr ? (string) ($prevSignals[$key] ?? '') : (int) ($prevSignals[$key] ?? 0);
        $cur = $isStr ? (string) ($serverSignals[$key] ?? '') : (int) ($serverSignals[$key] ?? 0);
        if ($prev !== $cur) {
            return true;
        }
    }

    $prevKeys = is_array($prevSignals['period_keys'] ?? null) ? $prevSignals['period_keys'] : [];
    $curKeys = is_array($serverSignals['period_keys'] ?? null) ? $serverSignals['period_keys'] : [];
    if ($prevKeys !== $curKeys) {
        return true;
    }

    return false;
}

/**
 * @param array<string, mixed> $prevSignals
 * @return list<string>
 */
function k2_status_pulse_changed_sections(array $prevSignals, array $newSignals, bool $firstPoll): array
{
    if ($firstPoll) {
        return [];
    }

    $sections = [];
    $keys = ['live_fp', 'online_fp', 'last_login_epoch', 'last_login_id', 'last_join_epoch', 'last_join_id', 'league_fp'];
    foreach ($keys as $key) {
        if (($prevSignals[$key] ?? null) !== ($newSignals[$key] ?? null)) {
            $sections[] = match ($key) {
                'live_fp' => 'live',
                'online_fp' => 'online',
                'last_login_epoch', 'last_login_id' => 'logins',
                'last_join_epoch', 'last_join_id' => 'registrations',
                'league_fp' => 'league',
                default => $key,
            };
        }
    }

    if (($prevSignals['last_rated_id'] ?? 0) !== ($newSignals['last_rated_id'] ?? 0)
        && (int) ($newSignals['last_rated_id'] ?? 0) > 0) {
        return ['cascade'];
    }

    if (($prevSignals['games_played'] ?? 0) !== ($newSignals['games_played'] ?? 0)) {
        $sections[] = 'arc';
    }

    $prevKeys = is_array($prevSignals['period_keys'] ?? null) ? $prevSignals['period_keys'] : [];
    $newKeys = is_array($newSignals['period_keys'] ?? null) ? $newSignals['period_keys'] : [];
    if ($prevKeys !== [] && $prevKeys !== $newKeys) {
        $sections[] = 'league';
    }

    return array_values(array_unique($sections));
}

function k2_status_pulse_render_live_list(array $games): string
{
    if ($games === []) {
        return '';
    }
    ob_start();
    echo '<ul class="k2-status-live-list">';
    foreach ($games as $g) {
        $gameId = (int) ($g['game_id'] ?? 0);
        echo '<li data-game-id="' . $gameId . '">';
        echo k2_status_day_clock_html($g['start'] ?? null, 'k2-status-live-list__time');
        echo '<span class="k2-status-match">';
        echo '<span class="k2-status-match__side">' . k2_status_player_link_or_name((int) $g['id_a'], (string) $g['name_a']) . '</span>';
        echo '<span class="k2-status-score">' . k2_status_score_html((int) $g['score_a'], (int) $g['score_b']) . '</span>';
        echo '<span class="k2-status-match__side">' . k2_status_player_link_or_name((int) $g['id_b'], (string) $g['name_b']) . '</span>';
        echo '</span>';
        echo '<span class="k2-status-live-list__meta">';
        echo '<span class="k2-status-live-list__clock" data-half-countdown="' . (int) ($g['half_countdown'] ?? 0) . '">';
        echo k2_status_h(k2_status_format_half_countdown((int) ($g['half_countdown'] ?? 0)));
        echo '</span>';
        echo '<span class="k2-status-live-list__period">' . k2_status_h(k2_status_format_game_period((int) ($g['period'] ?? 0))) . '</span>';
        echo '</span>';
        echo '</li>';
    }
    echo '</ul>';

    return (string) ob_get_clean();
}

/** @param list<array{id: int, name: string, last_login_epoch?: int}> $online */
function k2_status_pulse_render_online_list(array $online): string
{
    if ($online === []) {
        return '';
    }
    ob_start();
    echo '<ul class="k2-status-name-list">';
    foreach ($online as $row) {
        $epoch = (int) ($row['last_login_epoch'] ?? 0);
        echo '<li data-player-id="' . (int) $row['id'] . '" data-last-login-epoch="' . $epoch . '">'
            . k2_status_player_link((int) $row['id'], (string) $row['name']) . '</li>';
    }
    echo '</ul>';

    return (string) ob_get_clean();
}

/** @param list<array{id: int, name: string, at: string}> $logins */
function k2_status_pulse_render_logins_list(array $logins): string
{
    if ($logins === []) {
        return '';
    }
    ob_start();
    echo '<ul class="k2-status-recency-list">';
    foreach ($logins as $row) {
        echo '<li data-player-id="' . (int) $row['id'] . '">';
        echo k2_status_day_clock_html($row['at'] ?? null);
        echo k2_status_player_link((int) $row['id'], (string) $row['name']);
        echo '</li>';
    }
    echo '</ul>';

    return (string) ob_get_clean();
}

/** @param list<array{id: int, name: string, joined: string}> $registrations */
function k2_status_pulse_render_registrations_list(array $registrations): string
{
    if ($registrations === []) {
        return '';
    }
    ob_start();
    echo '<ul class="k2-status-recency-list">';
    foreach ($registrations as $row) {
        echo '<li data-player-id="' . (int) $row['id'] . '">';
        echo '<span class="k2-status-recency-list__when">' . k2_status_h(k2_status_registration_date($row['joined'] ?? null)) . '</span>';
        echo k2_status_player_link((int) $row['id'], (string) $row['name']);
        echo '</li>';
    }
    echo '</ul>';

    return (string) ob_get_clean();
}

/** @param list<array<string, mixed>> $recentGames */
function k2_status_pulse_render_recent_games_list(array $recentGames): string
{
    if ($recentGames === []) {
        return '';
    }
    ob_start();
    echo '<ul class="k2-status-recency-list">';
    foreach ($recentGames as $g) {
        echo '<li data-game-id="' . (int) ($g['id'] ?? 0) . '">';
        echo k2_status_day_clock_html($g['at'] ?? null);
        echo '<span class="k2-status-match">';
        echo '<span class="k2-status-match__side">' . k2_status_player_link((int) $g['id_a'], (string) $g['name_a']) . '</span>';
        echo '<span class="k2-status-score">' . k2_status_score_html((int) $g['goals_a'], (int) $g['goals_b']) . '</span>';
        echo '<span class="k2-status-match__side">' . k2_status_player_link((int) $g['id_b'], (string) $g['name_b']) . '</span>';
        echo '</span>';
        echo '</li>';
    }
    echo '</ul>';

    return (string) ob_get_clean();
}

/** Player ids with positive rating adjustment on a finished rated row. */
function k2_status_pulse_rating_gainer_ids(mysqli $con, int $ratedId): array
{
    if ($ratedId < 1) {
        return [];
    }
    $stmt = mysqli_prepare(
        $con,
        'SELECT idA, idB, AdjustmentA, AdjustmentB FROM ratedresults WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        return [];
    }
    mysqli_stmt_bind_param($stmt, 'i', $ratedId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return [];
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : false;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    if (!$row) {
        return [];
    }
    $gainers = [];
    if ((float) ($row['AdjustmentA'] ?? 0) > 0) {
        $gainers[] = (int) ($row['idA'] ?? 0);
    }
    if ((float) ($row['AdjustmentB'] ?? 0) > 0) {
        $gainers[] = (int) ($row['idB'] ?? 0);
    }

    return array_values(array_filter($gainers, static fn(int $id): bool => $id > 0));
}

/**
 * League cascade glow: activity = both finishers; pts = winner only, or both on draw.
 *
 * @return array{activity: list<int>, pts: list<int>}
 */
function k2_status_pulse_league_glow_ids(mysqli $con, int $ratedId): array
{
    $empty = ['activity' => [], 'pts' => []];
    if ($ratedId < 1) {
        return $empty;
    }
    $stmt = mysqli_prepare(
        $con,
        'SELECT idA, idB, GoalsA, GoalsB FROM ratedresults WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        return $empty;
    }
    mysqli_stmt_bind_param($stmt, 'i', $ratedId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);

        return $empty;
    }
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : false;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    if (!$row) {
        return $empty;
    }
    $idA = (int) ($row['idA'] ?? 0);
    $idB = (int) ($row['idB'] ?? 0);
    $goalsA = (int) ($row['GoalsA'] ?? 0);
    $goalsB = (int) ($row['GoalsB'] ?? 0);
    $activity = array_values(array_filter([$idA, $idB], static fn(int $id): bool => $id > 0));
    if ($goalsA > $goalsB) {
        $pts = $idA > 0 ? [$idA] : [];
    } elseif ($goalsB > $goalsA) {
        $pts = $idB > 0 ? [$idB] : [];
    } else {
        $pts = $activity;
    }

    return ['activity' => $activity, 'pts' => $pts];
}

/** @param list<array{id: int, name: string, rating: int, games: int}> $rows */
function k2_status_pulse_render_ratings_tbody(array $rows): string
{
    if ($rows === []) {
        return '';
    }
    ob_start();
    $rank = 1;
    foreach ($rows as $row) {
        echo '<tr data-player-id="' . (int) $row['id'] . '">';
        echo '<td class="k2-status-table__num">' . $rank . '</td>';
        echo '<td class="k2-status-table__player">' . k2_status_player_link((int) $row['id'], (string) $row['name']) . '</td>';
        echo '<td class="k2-status-table__num">' . k2_lb_rating_cell_link((int) $row['id'], (int) $row['rating'], (string) $row['name']) . '</td>';
        echo '<td class="k2-status-table__num">' . (int) $row['games'] . '</td>';
        echo '</tr>';
        ++$rank;
    }

    return (string) ob_get_clean();
}

/**
 * @param list<string> $want
 * @return array<string, mixed>
 */
function k2_status_pulse_build_sections(
    mysqli $con,
    array $want,
    array $signalBundle,
    string $leaguePeriod,
    string $leagueKey,
    int $serverNowEpoch
): array {
    $sections = [];
    $needCascade = in_array('cascade', $want, true);
    if ($needCascade) {
        $want = ['live', 'online', 'logins', 'registrations', 'recent_games', 'ratings', 'arc', 'league'];
    }

    $want = array_values(array_unique($want));

    if (in_array('live', $want, true)) {
        $liveErr = null;
        $live = k2_status_live_games($con, 10, $liveErr);
        if ($live === null) {
            $live = [];
        }
        $sections['live'] = [
            'games' => $live,
            'html' => k2_status_pulse_render_live_list($live),
            'empty' => $live === [],
        ];
    }

    if (in_array('online', $want, true)) {
        $onlineErr = null;
        $online = k2_status_online_players($con, $onlineErr) ?? $signalBundle['_online'] ?? [];
        $sections['online'] = [
            'html' => k2_status_pulse_render_online_list($online),
            'empty' => $online === [],
            'count' => count($online),
        ];
    }

    if (in_array('logins', $want, true)) {
        $err = null;
        $logins = k2_status_recent_logins($con, 10, $err) ?? [];
        $sections['logins'] = [
            'html' => k2_status_pulse_render_logins_list($logins),
            'empty' => $logins === [],
        ];
    }

    if (in_array('registrations', $want, true)) {
        $err = null;
        $regs = k2_status_recent_registrations($con, 10, $err) ?? [];
        $sections['registrations'] = [
            'html' => k2_status_pulse_render_registrations_list($regs),
            'empty' => $regs === [],
        ];
    }

    if (in_array('recent_games', $want, true)) {
        $err = null;
        $recent = k2_status_recent_rated_games($con, 10, $err) ?? [];
        $sections['recent_games'] = [
            'html' => k2_status_pulse_render_recent_games_list($recent),
            'empty' => $recent === [],
        ];
    }

    if (in_array('ratings', $want, true)) {
        $err = null;
        $rows = k2_status_active_top_rated($con, $err) ?? [];
        $ratedId = (int) ($signalBundle['signals']['last_rated_id'] ?? 0);
        $sections['ratings'] = [
            'count' => count($rows),
            'tbody_html' => k2_status_pulse_render_ratings_tbody($rows),
            'empty' => $rows === [],
            'rating_gainers' => $needCascade ? k2_status_pulse_rating_gainer_ids($con, $ratedId) : [],
        ];
    }

    if (in_array('arc', $want, true)) {
        $err = null;
        $arc = k2_status_arc_ticker($con, $err);
        if ($arc !== null) {
            $sections['arc'] = [
                'players' => (int) ($arc['players'] ?? 0),
                'games' => (int) ($arc['games'] ?? 0),
                'since_label' => (string) ($arc['since_label'] ?? ''),
            ];
        }
    }

    if (in_array('league', $want, true) && $leagueKey !== '') {
        if (!function_exists('k2_period_activity_leaderboard_entries')) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/period_activity_leaderboard_query.php';
        }
        $pointsErr = null;
        $points = k2_status_league_for_key($con, $leaguePeriod, $leagueKey, null, $pointsErr);
        $activityErr = null;
        $entries = k2_period_activity_leaderboard_entries($con, $leaguePeriod, $leagueKey, 0, $activityErr);
        $totalGames = k2_period_activity_total_games($con, $leaguePeriod, $leagueKey);
        $timing = $points !== null ? k2_status_league_timing_for_api($con, $points) : [
            'end_epoch' => 0,
            'server_now_epoch' => $serverNowEpoch,
            'show_medals' => false,
        ];
        $ratedId = (int) ($signalBundle['signals']['last_rated_id'] ?? 0);
        $sections['league'] = [
            'period' => $leaguePeriod,
            'key' => $leagueKey,
            'total_games' => $totalGames,
            'glow' => $needCascade ? k2_status_pulse_league_glow_ids($con, $ratedId) : ['activity' => [], 'pts' => []],
            'activity' => [
                'entries' => $entries,
                'total_games' => $totalGames,
                'label' => k2_format_period_activity_label($leaguePeriod, $leagueKey),
            ],
            'points' => $points !== null ? [
                'period' => $leaguePeriod,
                'key' => $leagueKey,
                'label' => (string) ($points['label'] ?? ''),
                'total_games' => (int) ($points['total_games'] ?? 0),
                'end' => (string) ($points['end'] ?? ''),
                'end_epoch' => $timing['end_epoch'],
                'end_label' => k2_status_league_end_label($points),
                'server_now_epoch' => $timing['server_now_epoch'],
                'show_medals' => $timing['show_medals'],
                'rows' => $points['rows'] ?? [],
            ] : null,
        ];
    }

    return $sections;
}

/**
 * Lightweight signals for SSR data attributes (no cache — page load only).
 *
 * @return array{signals: array<string, mixed>, revision: string, server_now_epoch: int}
 */
function k2_status_pulse_signals_for_page(mysqli $con, string $leaguePeriod, string $leagueKey): array
{
    $bundle = k2_status_pulse_collect_signals($con, $leaguePeriod, $leagueKey);

    return [
        'signals' => $bundle['signals'],
        'revision' => (string) ($bundle['revision'] ?? ''),
        'server_now_epoch' => (int) ($bundle['server_now_epoch'] ?? time()),
    ];
}
