<?php
/**
 * Rated play streaks — consecutive UTC days / Mon–Sun weeks with ≥1 rated game.
 * Stored in player_play_streaks; HoF on generalstatstable (LongestDaily/WeeklyPlayStreak*).
 *
 * Establishing game = first rated game on the last day/week of the run (MIN ratedresults.id).
 * Post-game: personal row first, then HoF compare when personal best strictly increases.
 */
declare(strict_types=1);

/** @var array<string, array{id: int, Date: string}>|null */
$k2_play_streak_establishing_cache = null;

/** Leaderboard / HoF tooltip copy (UTC rated-play streaks, not result streaks). */
function k2_play_streak_help_day(): string
{
    return 'Personal best: consecutive UTC calendar days with at least one rated game (not result streaks).';
}

function k2_play_streak_help_week(): string
{
    return 'Personal best: consecutive UTC weeks (Monday–Sunday) with at least one rated game.';
}

function k2_play_streak_utc_today(mysqli $con): string
{
    $res = $con->query('SELECT DATE(UTC_TIMESTAMP()) AS d');
    if ($res === false) {
        throw new RuntimeException('UTC date query failed');
    }
    $row = $res->fetch_assoc();
    $res->free();

    return (string) ($row['d'] ?? gmdate('Y-m-d'));
}

function k2_play_streak_week_monday(string $dayYmd): string
{
    $dt = new DateTimeImmutable($dayYmd, new DateTimeZone('UTC'));
    $dow = (int) $dt->format('N');

    return $dt->modify('-' . ($dow - 1) . ' days')->format('Y-m-d');
}

function k2_play_streak_next_period(string $anchorYmd, string $streakType): string
{
    $dt = new DateTimeImmutable($anchorYmd, new DateTimeZone('UTC'));
    if ($streakType === 'week') {
        return $dt->modify('+7 days')->format('Y-m-d');
    }

    return $dt->modify('+1 day')->format('Y-m-d');
}

function k2_play_streak_prev_day(string $dayYmd): string
{
    return (new DateTimeImmutable($dayYmd, new DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d');
}

function k2_play_streak_prev_week_monday(string $weekMonday): string
{
    return (new DateTimeImmutable($weekMonday, new DateTimeZone('UTC')))->modify('-7 days')->format('Y-m-d');
}

function k2_play_streak_is_alive(string $streakType, string $anchorYmd, string $utcToday): bool
{
    if ($anchorYmd === '') {
        return false;
    }
    if ($streakType === 'day') {
        return $anchorYmd === $utcToday || $anchorYmd === k2_play_streak_prev_day($utcToday);
    }
    $thisWeek = k2_play_streak_week_monday($utcToday);

    return $anchorYmd === $thisWeek || $anchorYmd === k2_play_streak_prev_week_monday($thisWeek);
}

/** @return int 0 when the stored run is no longer alive */
function k2_play_streak_effective_current(array $row, string $streakType, string $utcToday): int
{
    $len = (int) ($row['current_streak'] ?? 0);
    if ($len < 1) {
        return 0;
    }
    $anchor = (string) ($row['current_anchor'] ?? '');
    if (!k2_play_streak_is_alive($streakType, $anchor, $utcToday)) {
        return 0;
    }

    return $len;
}

/**
 * Bulk load establishing games for rebuild (key = "{playerId}|{periodStart}").
 *
 * @return array<string, array{id: int, Date: string}>
 */
function k2_play_streak_load_establishing_cache(mysqli $con): array
{
    global $k2_play_streak_establishing_cache;
    if ($k2_play_streak_establishing_cache !== null) {
        return $k2_play_streak_establishing_cache;
    }

    $cache = [];
    $byGame = [];

    foreach (['day' => 'DATE(`Date`)', 'week' => 'DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY)'] as $streakType => $periodExpr) {
        $sql = 'SELECT `player_id`, `period_start`, MIN(`game_id`) AS `game_id` FROM ('
            . 'SELECT `idA` AS `player_id`, ' . $periodExpr . ' AS `period_start`, `id` AS `game_id` '
            . 'FROM `ratedresults` WHERE `idA` IS NOT NULL '
            . 'UNION ALL '
            . 'SELECT `idB`, ' . $periodExpr . ', `id` '
            . 'FROM `ratedresults` WHERE `idB` IS NOT NULL'
            . ') AS `appearances` GROUP BY `player_id`, `period_start`';

        $res = $con->query($sql);
        if ($res === false) {
            throw new RuntimeException('establishing cache query failed: ' . $con->error);
        }

        while ($row = $res->fetch_assoc()) {
            $pid = (int) $row['player_id'];
            $period = (string) $row['period_start'];
            $gid = (int) $row['game_id'];
            $key = $streakType . '|' . k2_play_streak_cache_key($pid, $period);
            $cache[$key] = ['id' => $gid, 'Date' => ''];
            $byGame[$gid] = true;
        }
        $res->free();
    }

    if ($byGame !== []) {
        $ids = implode(',', array_map('intval', array_keys($byGame)));
        $dateRes = $con->query('SELECT `id`, `Date` FROM `ratedresults` WHERE `id` IN (' . $ids . ')');
        if ($dateRes) {
            $dates = [];
            while ($d = $dateRes->fetch_assoc()) {
                $dates[(int) $d['id']] = (string) $d['Date'];
            }
            $dateRes->free();
            foreach ($cache as $key => $entry) {
                $cache[$key]['Date'] = $dates[$entry['id']] ?? '';
            }
        }
    }

    $k2_play_streak_establishing_cache = $cache;

    return $cache;
}

function k2_play_streak_clear_establishing_cache(): void
{
    global $k2_play_streak_establishing_cache;
    $k2_play_streak_establishing_cache = null;
}

function k2_play_streak_cache_key(int $playerId, string $periodStart): string
{
    return $playerId . '|' . $periodStart;
}

/**
 * @return array{id: int, Date: string}|null
 */
function k2_play_streak_establishing_game(mysqli $con, int $playerId, string $periodStart, string $streakType): ?array
{
    global $k2_play_streak_establishing_cache;
    if ($k2_play_streak_establishing_cache !== null) {
        $key = $streakType . '|' . k2_play_streak_cache_key($playerId, $periodStart);

        return $k2_play_streak_establishing_cache[$key] ?? null;
    }

    if ($streakType === 'day') {
        $sql = 'SELECT `id`, `Date` FROM `ratedresults` '
            . 'WHERE (`idA` = ? OR `idB` = ?) AND DATE(`Date`) = ? ORDER BY `id` ASC LIMIT 1';
    } else {
        $sql = 'SELECT `id`, `Date` FROM `ratedresults` '
            . 'WHERE (`idA` = ? OR `idB` = ?) '
            . 'AND DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY) = ? '
            . 'ORDER BY `id` ASC LIMIT 1';
    }
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('establishing game prepare failed');
    }
    $stmt->bind_param('iis', $playerId, $playerId, $periodStart);
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

    return ['id' => (int) $row['id'], 'Date' => (string) $row['Date']];
}

/**
 * @return array<string, mixed>|null
 */
function k2_play_streak_load_row(mysqli $con, int $playerId, string $streakType): ?array
{
    $stmt = $con->prepare(
        'SELECT `player_id`, `streak_type`, `current_streak`, `current_anchor`, `current_last_game_id`, '
        . '`best_streak`, `best_achieved_at`, `best_last_game_id` '
        . 'FROM `player_play_streaks` WHERE `player_id` = ? AND `streak_type` = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('load streak prepare failed');
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

function k2_play_streak_default_row(int $playerId, string $streakType): array
{
    return [
        'player_id' => $playerId,
        'streak_type' => $streakType,
        'current_streak' => 0,
        'current_anchor' => null,
        'current_last_game_id' => null,
        'best_streak' => 0,
        'best_achieved_at' => null,
        'best_last_game_id' => null,
    ];
}

/**
 * @param array<string, mixed> $row
 * @param array{id: int, Date: string} $establishing
 */
function k2_play_streak_set_best_from_establishing(array &$row, int $length, array $establishing): bool
{
    $best = (int) ($row['best_streak'] ?? 0);
    if ($length <= $best) {
        return false;
    }
    $row['best_streak'] = $length;
    $row['best_last_game_id'] = $establishing['id'];
    $row['best_achieved_at'] = $establishing['Date'];

    return true;
}

/**
 * @param array<string, mixed> $row
 */
function k2_play_streak_save_row(mysqli $con, array $row): void
{
    $anchor = $row['current_anchor'];
    $currentGameId = $row['current_last_game_id'];
    $bestAt = $row['best_achieved_at'];
    $bestGameId = $row['best_last_game_id'];

    $stmt = $con->prepare(
        'INSERT INTO `player_play_streaks` '
        . '(`player_id`, `streak_type`, `current_streak`, `current_anchor`, `current_last_game_id`, '
        . '`best_streak`, `best_achieved_at`, `best_last_game_id`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE '
        . '`current_streak` = VALUES(`current_streak`), '
        . '`current_anchor` = VALUES(`current_anchor`), '
        . '`current_last_game_id` = VALUES(`current_last_game_id`), '
        . '`best_streak` = VALUES(`best_streak`), '
        . '`best_achieved_at` = VALUES(`best_achieved_at`), '
        . '`best_last_game_id` = VALUES(`best_last_game_id`)'
    );
    if ($stmt === false) {
        throw new RuntimeException('save streak prepare failed');
    }

    $playerId = (int) $row['player_id'];
    $streakType = (string) $row['streak_type'];
    $currentStreak = (int) $row['current_streak'];
    $bestStreak = (int) $row['best_streak'];
    $stmt->bind_param(
        'isisissi',
        $playerId,
        $streakType,
        $currentStreak,
        $anchor,
        $currentGameId,
        $bestStreak,
        $bestAt,
        $bestGameId
    );
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('save streak failed: ' . $err);
    }
    $stmt->close();
}

function k2_play_streak_check_year_in_heaven_after_new_week(
    mysqli $con,
    int $playerId,
    string $streakType,
    string $periodAnchor,
    string $prevAnchor
): void {
    if ($streakType !== 'week' || $periodAnchor === $prevAnchor) {
        return;
    }
    if (!function_exists('k2_milestone_maybe_unlock_year_in_heaven')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestone_year_in_heaven.php';
    }
    k2_milestone_maybe_unlock_year_in_heaven($con, $playerId, $periodAnchor);
}

/**
 * Apply one rated game to a player's streak row.
 *
 * @return bool true when personal best_streak strictly increased (HoF may need update)
 */
function k2_play_streak_apply_game(
    mysqli $con,
    int $playerId,
    string $streakType,
    string $periodAnchor,
    int $gameId,
    string $gameDate
): bool {
    if ($streakType !== 'day' && $streakType !== 'week') {
        throw new InvalidArgumentException('streak_type must be day or week');
    }

    $row = k2_play_streak_load_row($con, $playerId, $streakType) ?? k2_play_streak_default_row($playerId, $streakType);
    $bestChanged = false;

    $prevAnchor = $row['current_anchor'] !== null ? (string) $row['current_anchor'] : '';
    $current = (int) $row['current_streak'];

    $establishingThisPeriod = ['id' => $gameId, 'Date' => $gameDate];

    if ($prevAnchor === '' || $current < 1) {
        $row['current_streak'] = 1;
        $row['current_anchor'] = $periodAnchor;
        $row['current_last_game_id'] = $gameId;
        if (k2_play_streak_set_best_from_establishing($row, 1, $establishingThisPeriod)) {
            $bestChanged = true;
        }
        k2_play_streak_save_row($con, $row);
        k2_play_streak_check_year_in_heaven_after_new_week($con, $playerId, $streakType, $periodAnchor, $prevAnchor);

        return $bestChanged;
    }

    if ($periodAnchor === $prevAnchor) {
        k2_play_streak_save_row($con, $row);

        return false;
    }

    $expectedNext = k2_play_streak_next_period($prevAnchor, $streakType);

    if ($periodAnchor === $expectedNext) {
        $newLen = $current + 1;
        $row['current_streak'] = $newLen;
        $row['current_anchor'] = $periodAnchor;
        $row['current_last_game_id'] = $gameId;
        if (k2_play_streak_set_best_from_establishing($row, $newLen, $establishingThisPeriod)) {
            $bestChanged = true;
        }
        k2_play_streak_save_row($con, $row);
        if ($streakType === 'day' && $newLen === 100) {
            $est100 = k2_play_streak_establishing_game($con, $playerId, $periodAnchor, 'day');
            if ($est100 !== null) {
                k2_play_streak_maybe_unlock_milestone_100($con, $playerId, $est100['id'], $est100['Date']);
            }
        }
        k2_play_streak_check_year_in_heaven_after_new_week($con, $playerId, $streakType, $periodAnchor, $prevAnchor);

        return $bestChanged;
    }

    if ($current > (int) $row['best_streak']) {
        $est = k2_play_streak_establishing_game($con, $playerId, $prevAnchor, $streakType);
        if ($est !== null && k2_play_streak_set_best_from_establishing($row, $current, $est)) {
            $bestChanged = true;
        }
    }

    $row['current_streak'] = 1;
    $row['current_anchor'] = $periodAnchor;
    $row['current_last_game_id'] = $gameId;
    if (k2_play_streak_set_best_from_establishing($row, 1, $establishingThisPeriod)) {
        $bestChanged = true;
    }
    k2_play_streak_save_row($con, $row);
    k2_play_streak_check_year_in_heaven_after_new_week($con, $playerId, $streakType, $periodAnchor, $prevAnchor);

    return $bestChanged;
}

/**
 * @param array{id: int, Date: string} $candidate
 * @param array{id: int, Date: string}|null $incumbent
 */
function k2_play_streak_beats_hof_holder(
    mysqli $con,
    int $candidateLength,
    array $candidate,
    int $candidatePlayerId,
    ?int $incumbentLength,
    ?int $incumbentGameId,
    ?string $incumbentAchievedAt,
    ?int $incumbentPlayerId
): bool {
    if ($incumbentLength === null || $incumbentLength < 1) {
        return $candidateLength > 0;
    }
    if ($candidateLength > $incumbentLength) {
        return true;
    }
    if ($candidateLength < $incumbentLength) {
        return false;
    }

    $candAt = strtotime($candidate['Date']);
    $incAt = $incumbentAchievedAt !== null && $incumbentAchievedAt !== ''
        ? strtotime($incumbentAchievedAt) : false;
    if ($candAt !== false && $incAt !== false && $candAt !== $incAt) {
        return $candAt < $incAt;
    }

    if ($candidate['id'] === $incumbentGameId) {
        $idB = k2_play_streak_game_id_b($con, $candidate['id']);

        return $idB !== null && $candidatePlayerId === $idB && $incumbentPlayerId !== $idB;
    }

    return false;
}

function k2_play_streak_game_id_b(mysqli $con, int $gameId): ?int
{
    $stmt = $con->prepare('SELECT `idB` FROM `ratedresults` WHERE `id` = ? LIMIT 1');
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
    if (!$row || $row['idB'] === null) {
        return null;
    }

    return (int) $row['idB'];
}

function k2_play_streak_hof_column_map(string $streakType): array
{
    if ($streakType === 'day') {
        return [
            'value' => 'LongestDailyPlayStreak',
            'id' => 'LongestDailyPlayStreakID',
            'name' => 'LongestDailyPlayStreakName',
            'date' => 'LongestDailyPlayStreakDate',
            'game_id' => 'LongestDailyPlayStreakGameID',
        ];
    }

    return [
        'value' => 'LongestWeeklyPlayStreak',
        'id' => 'LongestWeeklyPlayStreakID',
        'name' => 'LongestWeeklyPlayStreakName',
        'date' => 'LongestWeeklyPlayStreakDate',
        'game_id' => 'LongestWeeklyPlayStreakGameID',
    ];
}

function k2_play_streak_maybe_update_hof(
    mysqli $con,
    string $streakType,
    int $playerId,
    string $playerName
): void {
    $row = k2_play_streak_load_row($con, $playerId, $streakType);
    if ($row === null) {
        return;
    }
    $bestLen = (int) ($row['best_streak'] ?? 0);
    $bestGameId = (int) ($row['best_last_game_id'] ?? 0);
    if ($bestLen < 1 || $bestGameId < 1) {
        return;
    }

    $stmt = $con->prepare('SELECT `Date` FROM `ratedresults` WHERE `id` = ? LIMIT 1');
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('i', $bestGameId);
    $stmt->execute();
    $res = $stmt->get_result();
    $gameRow = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    if (!$gameRow) {
        return;
    }
    $candidate = ['id' => $bestGameId, 'Date' => (string) $gameRow['Date']];

    $cols = k2_play_streak_hof_column_map($streakType);
    $hofRes = $con->query('SELECT `'
        . $cols['value'] . '`, `'
        . $cols['id'] . '`, `'
        . $cols['date'] . '`, `'
        . $cols['game_id'] . '` '
        . 'FROM `generalstatstable` WHERE `id` = 1 LIMIT 1');
    if ($hofRes === false) {
        return;
    }
    $hof = $hofRes->fetch_assoc();
    $hofRes->free();
    if (!$hof) {
        return;
    }

    $incLen = isset($hof[$cols['value']]) ? (int) $hof[$cols['value']] : 0;
    $incId = isset($hof[$cols['id']]) ? (int) $hof[$cols['id']] : 0;
    $incGameId = isset($hof[$cols['game_id']]) ? (int) $hof[$cols['game_id']] : 0;
    $incDate = isset($hof[$cols['date']]) ? (string) $hof[$cols['date']] : '';

    if (!k2_play_streak_beats_hof_holder(
        $con,
        $bestLen,
        $candidate,
        $playerId,
        $incLen > 0 ? $incLen : null,
        $incGameId > 0 ? $incGameId : null,
        $incDate !== '' ? $incDate : null,
        $incId > 0 ? $incId : null
    )) {
        return;
    }

    $dateStr = (string) $candidate['Date'];
    $stmt = $con->prepare(
        'UPDATE `generalstatstable` SET '
        . '`' . $cols['value'] . '` = ?, '
        . '`' . $cols['id'] . '` = ?, '
        . '`' . $cols['name'] . '` = ?, '
        . '`' . $cols['date'] . '` = ?, '
        . '`' . $cols['game_id'] . '` = ? '
        . 'WHERE `id` = 1'
    );
    if ($stmt === false) {
        return;
    }
    $stmt->bind_param('iissi', $bestLen, $playerId, $playerName, $dateStr, $bestGameId);
    $stmt->execute();
    $stmt->close();
}

/**
 * Post-game entry: update both players' day/week streaks, then HoF when personal best rises.
 */
function k2_play_streak_maybe_unlock_milestone_100(
    mysqli $con,
    int $playerId,
    int $gameId,
    string $gameDate
): void {
    if (!function_exists('k2_milestone_insert_game_unlock')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';
    }
    if (!k2_milestone_tables_ready($con)) {
        return;
    }
    k2_milestone_insert_game_unlock($con, $playerId, 'play_streak_100', $gameId, $gameDate, 100);
}

function k2_play_streak_after_rated_game(
    mysqli $con,
    int $gameId,
    string $gameDate,
    int $idA,
    int $idB,
    string $nameA,
    string $nameB
): void {
    $dayStart = substr($gameDate, 0, 10);
    if (strlen($dayStart) < 10) {
        $res = $con->query('SELECT DATE(' . "'" . $con->real_escape_string($gameDate) . "'" . ') AS d');
        if ($res) {
            $r = $res->fetch_assoc();
            $dayStart = (string) ($r['d'] ?? $dayStart);
            $res->free();
        }
    }
    $weekStart = k2_play_streak_week_monday($dayStart);

    foreach (
        [
            [$idA, $nameA],
            [$idB, $nameB],
        ] as [$pid, $pname]
    ) {
        if ($pid < 1) {
            continue;
        }
        $dayBest = k2_play_streak_apply_game($con, $pid, 'day', $dayStart, $gameId, $gameDate);
        $weekBest = k2_play_streak_apply_game($con, $pid, 'week', $weekStart, $gameId, $gameDate);
        if ($dayBest) {
            k2_play_streak_maybe_update_hof($con, 'day', $pid, $pname);
        }
        if ($weekBest) {
            k2_play_streak_maybe_update_hof($con, 'week', $pid, $pname);
        }
    }
}

/**
 * Full rebuild for one player from player_period_games period list.
 *
 * @param list<string> $periodStarts sorted ascending Y-m-d
 * @return array<string, mixed>
 */
function k2_play_streak_compute_from_periods(
    mysqli $con,
    int $playerId,
    string $streakType,
    array $periodStarts,
    string $utcToday
): array {
    $row = k2_play_streak_default_row($playerId, $streakType);
    if ($periodStarts === []) {
        return $row;
    }

    $runs = [];
    $run = [$periodStarts[0]];
    $count = count($periodStarts);
    for ($i = 1; $i < $count; $i++) {
        $prev = $periodStarts[$i - 1];
        $cur = $periodStarts[$i];
        $expected = k2_play_streak_next_period($prev, $streakType);
        if ($cur === $expected) {
            $run[] = $cur;
        } else {
            $runs[] = $run;
            $run = [$cur];
        }
    }
    $runs[] = $run;

    $bestLen = 0;
    $bestEst = null;
    foreach ($runs as $oneRun) {
        $len = count($oneRun);
        $lastPeriod = $oneRun[$len - 1];
        $est = k2_play_streak_establishing_game($con, $playerId, $lastPeriod, $streakType);
        if ($est === null) {
            continue;
        }
        if ($len > $bestLen) {
            $bestLen = $len;
            $bestEst = $est;
        } elseif ($len === $bestLen && $bestEst !== null) {
            $estTs = strtotime($est['Date']);
            $bestTs = strtotime($bestEst['Date']);
            if ($estTs !== false && $bestTs !== false && $estTs < $bestTs) {
                $bestEst = $est;
            }
        }
    }

    $lastRun = $runs[count($runs) - 1];
    $lastLen = count($lastRun);
    $lastAnchor = $lastRun[$lastLen - 1];
    $lastEst = k2_play_streak_establishing_game($con, $playerId, $lastAnchor, $streakType);

    $row['current_streak'] = $lastLen;
    $row['current_anchor'] = $lastAnchor;
    $row['current_last_game_id'] = $lastEst['id'] ?? null;
    if ($bestLen > 0 && $bestEst !== null) {
        $row['best_streak'] = $bestLen;
        $row['best_last_game_id'] = $bestEst['id'];
        $row['best_achieved_at'] = $bestEst['Date'];
    }

    return $row;
}

function k2_play_streak_rebuild_all(mysqli $con): int
{
    $utcToday = k2_play_streak_utc_today($con);
    k2_play_streak_clear_establishing_cache();
    k2_play_streak_load_establishing_cache($con);

    $con->query('TRUNCATE TABLE `player_play_streaks`');

    $playersRes = $con->query(
        'SELECT DISTINCT `player_id` FROM `player_period_games` ORDER BY `player_id` ASC'
    );
    if ($playersRes === false) {
        throw new RuntimeException('player list query failed');
    }

    $written = 0;
    while ($pRow = $playersRes->fetch_assoc()) {
        $playerId = (int) $pRow['player_id'];
        foreach (['day', 'week'] as $streakType) {
            $periodType = $streakType === 'day' ? 'day' : 'week';
            $stmt = $con->prepare(
                'SELECT `period_start` FROM `player_period_games` '
                . 'WHERE `player_id` = ? AND `period_type` = ? ORDER BY `period_start` ASC'
            );
            if ($stmt === false) {
                continue;
            }
            $stmt->bind_param('is', $playerId, $periodType);
            $stmt->execute();
            $res = $stmt->get_result();
            $periods = [];
            while ($r = $res->fetch_assoc()) {
                $periods[] = (string) $r['period_start'];
            }
            if ($res) {
                $res->free();
            }
            $stmt->close();

            $row = k2_play_streak_compute_from_periods($con, $playerId, $streakType, $periods, $utcToday);
            if ((int) $row['current_streak'] < 1 && (int) $row['best_streak'] < 1) {
                continue;
            }
            k2_play_streak_save_row($con, $row);
            $written++;
        }
    }
    $playersRes->free();

    k2_play_streak_clear_establishing_cache();
    k2_play_streak_rebuild_hof_from_table($con);

    return $written;
}

function k2_play_streak_rebuild_hof_from_table(mysqli $con): void
{
    foreach (['day', 'week'] as $streakType) {
        $sql = 'SELECT s.`player_id`, s.`best_streak`, s.`best_last_game_id`, s.`best_achieved_at`, '
            . 'p.`Name` AS player_name, r.`idB` '
            . 'FROM `player_play_streaks` s '
            . 'INNER JOIN `playertable` p ON p.`ID` = s.`player_id` '
            . 'LEFT JOIN `ratedresults` r ON r.`id` = s.`best_last_game_id` '
            . 'WHERE s.`streak_type` = ? AND s.`best_streak` > 0 '
            . 'ORDER BY s.`best_streak` DESC, s.`best_achieved_at` ASC, '
            . '(s.`player_id` = r.`idB`) DESC, s.`player_id` ASC '
            . 'LIMIT 1';
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            continue;
        }
        $stmt->bind_param('s', $streakType);
        $stmt->execute();
        $res = $stmt->get_result();
        $top = $res ? $res->fetch_assoc() : null;
        if ($res) {
            $res->free();
        }
        $stmt->close();
        if (!$top) {
            continue;
        }

        $cols = k2_play_streak_hof_column_map($streakType);
        $bestLen = (int) $top['best_streak'];
        $playerId = (int) $top['player_id'];
        $playerName = (string) ($top['player_name'] ?? '');
        $bestGameId = (int) ($top['best_last_game_id'] ?? 0);
        $dateStr = (string) ($top['best_achieved_at'] ?? '');

        $upd = $con->prepare(
            'UPDATE `generalstatstable` SET '
            . '`' . $cols['value'] . '` = ?, '
            . '`' . $cols['id'] . '` = ?, '
            . '`' . $cols['name'] . '` = ?, '
            . '`' . $cols['date'] . '` = ?, '
            . '`' . $cols['game_id'] . '` = ? '
            . 'WHERE `id` = 1'
        );
        if ($upd === false) {
            continue;
        }
        $upd->bind_param('iissi', $bestLen, $playerId, $playerName, $dateStr, $bestGameId);
        $upd->execute();
        $upd->close();
    }
}
