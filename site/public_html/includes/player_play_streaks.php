<?php
/**
 * Rated play streaks — consecutive UTC day / week / month / year with ≥1 rated game.
 * Stored in player_play_streaks; HoF on generalstatstable (Longest*PlayStreak*).
 *
 * Establishing game = first rated game on the last period of the run (MIN ratedresults.id).
 * Post-game: personal row first, then HoF compare when personal best strictly increases.
 */
declare(strict_types=1);

/** @var list<string> */
const K2_PLAY_STREAK_TYPES = ['day', 'week', 'month', 'year'];

/** @var array<string, array{id: int, Date: string}>|null */
$k2_play_streak_establishing_cache = null;

function k2_play_streak_is_valid_type(string $streakType): bool
{
    return in_array($streakType, K2_PLAY_STREAK_TYPES, true);
}

/** Leaderboard / HoF tooltip copy (UTC rated-play streaks, not result streaks). */
function k2_play_streak_help_day(): string
{
    return 'Your best run of consecutive UTC calendar days with at least one rated game (not win/loss streaks).';
}

function k2_play_streak_help_week(): string
{
    return 'Your best run of consecutive UTC weeks (Monday–Sunday) with at least one rated game.';
}

function k2_play_streak_help_month(): string
{
    return 'Your best run of consecutive UTC calendar months with at least one rated game.';
}

function k2_play_streak_help_year(): string
{
    return 'Your best run of consecutive UTC calendar years with at least one rated game.';
}

function k2_play_streak_period_type_label(string $streakType, int $count): string
{
    $singular = match ($streakType) {
        'day' => 'day',
        'week' => 'week',
        'month' => 'month',
        'year' => 'year',
        default => 'period',
    };

    return $count === 1 ? $singular : $singular . 's';
}

function k2_play_streak_last_period_anchor(string $startAnchor, string $streakType, int $length): string
{
    if ($length <= 1) {
        return $startAnchor;
    }

    $cur = $startAnchor;
    for ($i = 1; $i < $length; $i++) {
        $cur = k2_play_streak_next_period($cur, $streakType);
    }

    return $cur;
}

function k2_play_streak_format_period_span(string $streakType, string $startAnchor, string $endAnchor): string
{
    [$startLabel, $endLabel] = k2_play_streak_period_span_labels($streakType, $startAnchor, $endAnchor);
    if ($endLabel === null) {
        return $startLabel;
    }

    return $startLabel . ' – ' . $endLabel;
}

/**
 * @return array{0: string, 1: ?string}
 */
function k2_play_streak_period_span_labels(string $streakType, string $startAnchor, string $endAnchor): array
{
    require_once __DIR__ . '/peak_month_leaderboard_query.php';

    if ($startAnchor === $endAnchor) {
        return [k2_format_peak_period($streakType, $startAnchor), null];
    }

    if ($streakType === 'day') {
        return [
            k2_format_peak_period('day', $startAnchor),
            k2_format_peak_period('day', $endAnchor),
        ];
    }
    if ($streakType === 'week') {
        $endSun = (new DateTimeImmutable($endAnchor, new DateTimeZone('UTC')))->modify('+6 days')->format('Y-m-d');

        return [
            k2_format_peak_period('day', $startAnchor),
            k2_format_peak_period('day', $endSun),
        ];
    }
    if ($streakType === 'month') {
        return [
            k2_format_peak_period('month', $startAnchor),
            k2_format_peak_period('month', $endAnchor),
        ];
    }
    if ($streakType === 'year') {
        return [
            k2_format_peak_period('year', $startAnchor),
            k2_format_peak_period('year', $endAnchor),
        ];
    }

    return [$startAnchor, $endAnchor];
}

function k2_play_streak_link_star_markup(string $text): string
{
    return '<span class="k2-link-star">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
}

function k2_play_streak_format_period_span_html(string $streakType, string $startAnchor, string $endAnchor): string
{
    [$startLabel, $endLabel] = k2_play_streak_period_span_labels($streakType, $startAnchor, $endAnchor);
    if ($endLabel === null) {
        return k2_play_streak_link_star_markup($startLabel);
    }

    return k2_play_streak_link_star_markup($startLabel) . ' – ' . k2_play_streak_link_star_markup($endLabel);
}

function k2_play_streak_best_run_tooltip_html(string $streakType, int $count, string $startAnchor): string
{
    if ($count <= 0 || $startAnchor === '') {
        return '';
    }

    $endAnchor = k2_play_streak_last_period_anchor($startAnchor, $streakType, $count);
    $typeLabel = k2_play_streak_period_type_label($streakType, $count);
    $sentence = k2_play_streak_link_star_markup((string) $count)
        . ' consecutive UTC calendar ' . $typeLabel . ' with at least one rated game.';
    $dates = k2_play_streak_format_period_span_html($streakType, $startAnchor, $endAnchor);

    return $sentence . '<br><br>' . $dates;
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
    if ($streakType === 'month') {
        return $dt->modify('first day of next month')->format('Y-m-d');
    }
    if ($streakType === 'year') {
        return $dt->modify('+1 year')->format('Y-01-01');
    }

    return $dt->modify('+1 day')->format('Y-m-d');
}

function k2_play_streak_prev_period(string $anchorYmd, string $streakType): string
{
    $dt = new DateTimeImmutable($anchorYmd, new DateTimeZone('UTC'));
    if ($streakType === 'week') {
        return $dt->modify('-7 days')->format('Y-m-d');
    }
    if ($streakType === 'month') {
        return $dt->modify('first day of previous month')->format('Y-m-d');
    }
    if ($streakType === 'year') {
        return $dt->modify('-1 year')->format('Y-m-d');
    }

    return $dt->modify('-1 day')->format('Y-m-d');
}

function k2_play_streak_period_steps_back(string $anchorYmd, string $streakType, int $steps): string
{
    $cur = $anchorYmd;
    for ($i = 0; $i < $steps; $i++) {
        $cur = k2_play_streak_prev_period($cur, $streakType);
    }

    return $cur;
}

function k2_play_streak_prev_day(string $dayYmd): string
{
    return (new DateTimeImmutable($dayYmd, new DateTimeZone('UTC')))->modify('-1 day')->format('Y-m-d');
}

function k2_play_streak_prev_week_monday(string $weekMonday): string
{
    return (new DateTimeImmutable($weekMonday, new DateTimeZone('UTC')))->modify('-7 days')->format('Y-m-d');
}

function k2_play_streak_month_start(string $dayYmd): string
{
    $dt = new DateTimeImmutable($dayYmd, new DateTimeZone('UTC'));

    return $dt->format('Y-m-01');
}

function k2_play_streak_year_start(string $dayYmd): string
{
    $dt = new DateTimeImmutable($dayYmd, new DateTimeZone('UTC'));

    return $dt->format('Y') . '-01-01';
}

function k2_play_streak_is_alive(string $streakType, string $anchorYmd, string $utcToday): bool
{
    if ($anchorYmd === '') {
        return false;
    }
    if ($streakType === 'day') {
        return $anchorYmd === $utcToday || $anchorYmd === k2_play_streak_prev_day($utcToday);
    }
    if ($streakType === 'week') {
        $thisWeek = k2_play_streak_week_monday($utcToday);

        return $anchorYmd === $thisWeek || $anchorYmd === k2_play_streak_prev_week_monday($thisWeek);
    }
    if ($streakType === 'month') {
        $thisMonth = k2_play_streak_month_start($utcToday);
        $prevMonth = (new DateTimeImmutable($thisMonth, new DateTimeZone('UTC')))
            ->modify('-1 month')
            ->format('Y-m-d');

        return $anchorYmd === $thisMonth || $anchorYmd === $prevMonth;
    }
    if ($streakType === 'year') {
        $thisYear = k2_play_streak_year_start($utcToday);
        $prevYear = (new DateTimeImmutable($thisYear, new DateTimeZone('UTC')))
            ->modify('-1 year')
            ->format('Y-m-d');

        return $anchorYmd === $thisYear || $anchorYmd === $prevYear;
    }

    return false;
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

    foreach ([
        'day' => 'DATE(`Date`)',
        'week' => 'DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY)',
        'month' => "DATE_FORMAT(DATE(`Date`), '%Y-%m-01')",
        'year' => "CONCAT(YEAR(DATE(`Date`)), '-01-01')",
    ] as $streakType => $periodExpr) {
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
    } elseif ($streakType === 'week') {
        $sql = 'SELECT `id`, `Date` FROM `ratedresults` '
            . 'WHERE (`idA` = ? OR `idB` = ?) '
            . 'AND DATE_SUB(DATE(`Date`), INTERVAL WEEKDAY(`Date`) DAY) = ? '
            . 'ORDER BY `id` ASC LIMIT 1';
    } elseif ($streakType === 'month') {
        $sql = 'SELECT `id`, `Date` FROM `ratedresults` '
            . 'WHERE (`idA` = ? OR `idB` = ?) '
            . "AND DATE_FORMAT(DATE(`Date`), '%Y-%m-01') = ? "
            . 'ORDER BY `id` ASC LIMIT 1';
    } elseif ($streakType === 'year') {
        $sql = 'SELECT `id`, `Date` FROM `ratedresults` '
            . 'WHERE (`idA` = ? OR `idB` = ?) '
            . "AND CONCAT(YEAR(DATE(`Date`)), '-01-01') = ? "
            . 'ORDER BY `id` ASC LIMIT 1';
    } else {
        throw new InvalidArgumentException('invalid streak_type for establishing game: ' . $streakType);
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
        . '`best_streak`, `best_anchor_start`, `best_achieved_at`, `best_last_game_id` '
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
        'best_anchor_start' => null,
        'best_achieved_at' => null,
        'best_last_game_id' => null,
    ];
}

/**
 * @param array<string, mixed> $row
 * @param array{id: int, Date: string} $establishing
 */
function k2_play_streak_set_best_from_establishing(
    array &$row,
    int $length,
    string $startAnchor,
    array $establishing
): bool {
    $best = (int) ($row['best_streak'] ?? 0);
    if ($length <= $best) {
        return false;
    }
    $row['best_streak'] = $length;
    $row['best_anchor_start'] = $startAnchor;
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
    $bestStart = $row['best_anchor_start'] ?? null;
    $bestAt = $row['best_achieved_at'];
    $bestGameId = $row['best_last_game_id'];

    $stmt = $con->prepare(
        'INSERT INTO `player_play_streaks` '
        . '(`player_id`, `streak_type`, `current_streak`, `current_anchor`, `current_last_game_id`, '
        . '`best_streak`, `best_anchor_start`, `best_achieved_at`, `best_last_game_id`) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE '
        . '`current_streak` = VALUES(`current_streak`), '
        . '`current_anchor` = VALUES(`current_anchor`), '
        . '`current_last_game_id` = VALUES(`current_last_game_id`), '
        . '`best_streak` = VALUES(`best_streak`), '
        . '`best_anchor_start` = VALUES(`best_anchor_start`), '
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
        'isisisssi',
        $playerId,
        $streakType,
        $currentStreak,
        $anchor,
        $currentGameId,
        $bestStreak,
        $bestStart,
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
    string $prevAnchor,
    int $gameId,
    string $gameDate
): void {
    if ($streakType !== 'week' || $periodAnchor === $prevAnchor) {
        return;
    }
    if (!function_exists('k2_milestone_maybe_unlock_year_in_heaven')) {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestone_year_in_heaven.php';
    }
    k2_milestone_maybe_unlock_year_in_heaven($con, $playerId, $periodAnchor, $gameId, $gameDate);
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
    if (!k2_play_streak_is_valid_type($streakType)) {
        throw new InvalidArgumentException('streak_type must be day, week, month, or year');
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
        if (k2_play_streak_set_best_from_establishing($row, 1, $periodAnchor, $establishingThisPeriod)) {
            $bestChanged = true;
        }
        k2_play_streak_save_row($con, $row);
        k2_play_streak_check_year_in_heaven_after_new_week(
            $con,
            $playerId,
            $streakType,
            $periodAnchor,
            $prevAnchor,
            $gameId,
            $gameDate
        );

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
        if (k2_play_streak_set_best_from_establishing(
            $row,
            $newLen,
            k2_play_streak_period_steps_back($periodAnchor, $streakType, $newLen - 1),
            $establishingThisPeriod
        )) {
            $bestChanged = true;
        }
        k2_play_streak_save_row($con, $row);
        if ($streakType === 'day' && $newLen === 100) {
            k2_play_streak_maybe_unlock_milestone_100($con, $playerId, $gameId, $gameDate);
        }
        k2_play_streak_check_year_in_heaven_after_new_week(
            $con,
            $playerId,
            $streakType,
            $periodAnchor,
            $prevAnchor,
            $gameId,
            $gameDate
        );

        return $bestChanged;
    }

    if ($current > (int) $row['best_streak']) {
        $est = k2_play_streak_establishing_game($con, $playerId, $prevAnchor, $streakType);
        if ($est !== null && k2_play_streak_set_best_from_establishing(
            $row,
            $current,
            k2_play_streak_period_steps_back($prevAnchor, $streakType, $current - 1),
            $est
        )) {
            $bestChanged = true;
        }
    }

    $row['current_streak'] = 1;
    $row['current_anchor'] = $periodAnchor;
    $row['current_last_game_id'] = $gameId;
    if (k2_play_streak_set_best_from_establishing($row, 1, $periodAnchor, $establishingThisPeriod)) {
        $bestChanged = true;
    }
    k2_play_streak_save_row($con, $row);
    k2_play_streak_check_year_in_heaven_after_new_week(
        $con,
        $playerId,
        $streakType,
        $periodAnchor,
        $prevAnchor,
        $gameId,
        $gameDate
    );

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
    switch ($streakType) {
        case 'day':
            return [
                'value' => 'LongestDailyPlayStreak',
                'id' => 'LongestDailyPlayStreakID',
                'name' => 'LongestDailyPlayStreakName',
                'date' => 'LongestDailyPlayStreakDate',
                'game_id' => 'LongestDailyPlayStreakGameID',
            ];
        case 'month':
            return [
                'value' => 'LongestMonthlyPlayStreak',
                'id' => 'LongestMonthlyPlayStreakID',
                'name' => 'LongestMonthlyPlayStreakName',
                'date' => 'LongestMonthlyPlayStreakDate',
                'game_id' => 'LongestMonthlyPlayStreakGameID',
            ];
        case 'year':
            return [
                'value' => 'LongestYearlyPlayStreak',
                'id' => 'LongestYearlyPlayStreakID',
                'name' => 'LongestYearlyPlayStreakName',
                'date' => 'LongestYearlyPlayStreakDate',
                'game_id' => 'LongestYearlyPlayStreakGameID',
            ];
        default:
            return [
                'value' => 'LongestWeeklyPlayStreak',
                'id' => 'LongestWeeklyPlayStreakID',
                'name' => 'LongestWeeklyPlayStreakName',
                'date' => 'LongestWeeklyPlayStreakDate',
                'game_id' => 'LongestWeeklyPlayStreakGameID',
            ];
    }
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

/**
 * Post-game entry: update both players' play streaks when a period boundary is new.
 *
 * @param array<string, string>|null $periodStarts from P4 when ops path
 * @param array<string, bool>|null $isNewPeriodA
 * @param array<string, bool>|null $isNewPeriodB
 */
function k2_play_streak_after_rated_game(
    mysqli $con,
    int $gameId,
    string $gameDate,
    int $idA,
    int $idB,
    string $nameA,
    string $nameB,
    ?array $periodStarts = null,
    ?array $isNewPeriodA = null,
    ?array $isNewPeriodB = null
): void {
    if ($periodStarts === null || $isNewPeriodA === null || $isNewPeriodB === null) {
        $dayStart = substr($gameDate, 0, 10);
        if (strlen($dayStart) < 10) {
            $res = $con->query('SELECT DATE(' . "'" . $con->real_escape_string($gameDate) . "'" . ') AS d');
            if ($res) {
                $r = $res->fetch_assoc();
                $dayStart = (string) ($r['d'] ?? $dayStart);
                $res->free();
            }
        }
        $periodStarts = [
            'day' => $dayStart,
            'week' => k2_play_streak_week_monday($dayStart),
            'month' => k2_play_streak_month_start($dayStart),
            'year' => k2_play_streak_year_start($dayStart),
        ];
        $isNewPeriodA = array_fill_keys(K2_PLAY_STREAK_TYPES, true);
        $isNewPeriodB = array_fill_keys(K2_PLAY_STREAK_TYPES, true);
    }

    foreach (
        [
            [$idA, $nameA, $isNewPeriodA],
            [$idB, $nameB, $isNewPeriodB],
        ] as [$pid, $pname, $isNewPeriod]
    ) {
        if ($pid < 1) {
            continue;
        }
        foreach (K2_PLAY_STREAK_TYPES as $streakType) {
            if (empty($isNewPeriod[$streakType])) {
                continue;
            }
            $anchor = $periodStarts[$streakType] ?? '';
            if ($anchor === '') {
                continue;
            }
            $bestChanged = k2_play_streak_apply_game($con, $pid, $streakType, $anchor, $gameId, $gameDate);
            if ($bestChanged) {
                k2_play_streak_maybe_update_hof($con, $streakType, $pid, $pname);
            }
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
    $bestStart = null;
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
            $bestStart = $oneRun[0];
        } elseif ($len === $bestLen && $bestEst !== null) {
            $estTs = strtotime($est['Date']);
            $bestTs = strtotime($bestEst['Date']);
            if ($estTs !== false && $bestTs !== false && $estTs < $bestTs) {
                $bestEst = $est;
                $bestStart = $oneRun[0];
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
        $row['best_anchor_start'] = $bestStart;
        $row['best_last_game_id'] = $bestEst['id'];
        $row['best_achieved_at'] = $bestEst['Date'];
    }

    return $row;
}

/**
 * Compare incremental player_play_streaks rows to period-list oracle (repair / smoke).
 *
 * @return list<string> mismatch descriptions; empty = pass
 */
function k2_play_streak_oracle_mismatches(mysqli $con, ?int $playerId = null): array
{
    $utcToday = k2_play_streak_utc_today($con);
    $mismatches = [];

    $sql = 'SELECT DISTINCT `player_id` FROM `player_period_games`';
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
        foreach (K2_PLAY_STREAK_TYPES as $streakType) {
            $stmt = $con->prepare(
                'SELECT `period_start` FROM `player_period_games` '
                . 'WHERE `player_id` = ? AND `period_type` = ? ORDER BY `period_start` ASC'
            );
            if ($stmt === false) {
                continue;
            }
            $stmt->bind_param('is', $pid, $streakType);
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

            $expected = k2_play_streak_compute_from_periods($con, $pid, $streakType, $periods, $utcToday);
            $stored = k2_play_streak_load_row($con, $pid, $streakType);

            $expBest = (int) ($expected['best_streak'] ?? 0);
            $gotBest = $stored ? (int) ($stored['best_streak'] ?? 0) : 0;
            if ($expBest !== $gotBest) {
                $mismatches[] = "player {$pid} {$streakType} best_streak expected {$expBest} got {$gotBest}";
            }
            $expStart = $expected['best_anchor_start'] ?? null;
            $gotStart = $stored['best_anchor_start'] ?? null;
            if ($expBest > 0 && (string) $expStart !== (string) $gotStart) {
                $mismatches[] = "player {$pid} {$streakType} best_anchor_start expected {$expStart} got {$gotStart}";
            }
        }
    }
    $playersRes->free();

    return $mismatches;
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
        foreach (K2_PLAY_STREAK_TYPES as $streakType) {
            $periodType = $streakType;
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
    foreach (K2_PLAY_STREAK_TYPES as $streakType) {
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
