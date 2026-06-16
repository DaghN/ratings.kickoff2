<?php
/**
 * player_period_games + player_peak_period_games (P4) + player_activity_participation (P4b).
 *
 * Period bucketing (UTC) — same rules as archived batch SQL in sql/archive/batch-2026-05/.
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_bootstrap.php';

/** @var array<string, string> */
const K2_POST_GAME_PARTICIPATION_ACTIVE_COLUMN = [
    'day' => 'active_days',
    'week' => 'active_weeks',
    'month' => 'active_months',
    'year' => 'active_years',
];

/**
 * UTC instant for a rated game row (mysqli may return local-shifted Date strings on Windows).
 */
function k2_post_game_row_utc_datetime(array $game): DateTimeImmutable
{
    if (isset($game['date_utc_ts']) && $game['date_utc_ts'] !== null && $game['date_utc_ts'] !== '') {
        $dt = DateTimeImmutable::createFromFormat(
            'U',
            (string) (int) $game['date_utc_ts'],
            new DateTimeZone('UTC')
        );
        if ($dt !== false) {
            return $dt;
        }
    }

    return new DateTimeImmutable((string) ($game['Date'] ?? 'now'), new DateTimeZone('UTC'));
}

/**
 * Normalize Date to UTC wall clock for downstream post-game (matches DATE(Date) at time_zone +00:00).
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function k2_post_game_normalize_rated_game_row(array $row): array
{
    if (isset($row['date_utc_ts']) && $row['date_utc_ts'] !== null && $row['date_utc_ts'] !== '') {
        $row['Date'] = gmdate('Y-m-d H:i:s', (int) $row['date_utc_ts']);
    }

    return $row;
}

/**
 * Period starts from UNIX_TIMESTAMP(Date) at UTC (avoids Windows local TZ on DateTime format).
 *
 * @return array<string, string>
 */
function k2_post_game_period_starts_from_utc_ts(int $ts): array
{
    $day = gmdate('Y-m-d', $ts);
    $isoDow = (int) gmdate('N', $ts);
    $weekStartTs = $ts - ($isoDow - 1) * 86400;
    $weekStart = gmdate('Y-m-d', $weekStartTs);
    $month = gmdate('Y-m-01', $ts);
    $year = gmdate('Y', $ts) . '-01-01';

    return [
        'day' => $day,
        'week' => $weekStart,
        'month' => $month,
        'year' => $year,
    ];
}

/**
 * UTC period starts for a rated game (aligned with rebuild SQL / contract DATE(Date) at UTC).
 *
 * @return array<string, string> period_type => period_start (Y-m-d)
 */
function k2_post_game_period_starts_from_utc(DateTimeImmutable $dt): array
{
    $day = $dt->format('Y-m-d');
    $isoDow = (int) $dt->format('N');
    $weekStart = $dt->modify('-' . ($isoDow - 1) . ' days')->format('Y-m-d');
    $month = $dt->format('Y-m-01');
    $year = $dt->format('Y') . '-01-01';

    return [
        'day' => $day,
        'week' => $weekStart,
        'month' => $month,
        'year' => $year,
    ];
}

/**
 * @return array<string, string> period_type => period_start (Y-m-d)
 */
function k2_post_game_period_starts_from_game_date(string $gameDate): array
{
    return k2_post_game_period_starts_from_utc(
        new DateTimeImmutable($gameDate, new DateTimeZone('UTC'))
    );
}

/**
 * @param array<string, mixed> $game rated row with optional date_utc_ts from UNIX_TIMESTAMP(Date)
 * @return array<string, string>
 */
function k2_post_game_period_starts_for_game(array $game): array
{
    if (isset($game['date_utc_ts']) && $game['date_utc_ts'] !== null && $game['date_utc_ts'] !== '') {
        return k2_post_game_period_starts_from_utc_ts((int) $game['date_utc_ts']);
    }

    return k2_post_game_period_starts_from_utc(k2_post_game_row_utc_datetime($game));
}

function k2_post_game_period_tables_available(mysqli $con): bool
{
    return k2_ops_table_exists($con, 'player_period_games')
        && k2_ops_table_exists($con, 'player_peak_period_games');
}

function k2_post_game_participation_table_available(mysqli $con): bool
{
    return k2_ops_table_exists($con, 'player_activity_participation');
}

/**
 * @return array{games: int, is_new_period: bool}
 */
function k2_post_game_upsert_period_game(
    mysqli $con,
    string $periodType,
    string $periodStart,
    int $playerId
): array {
    $stmt = $con->prepare(
        'INSERT INTO player_period_games (period_type, period_start, player_id, games) '
        . 'VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE games = games + 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare period games upsert: ' . $con->error);
    }
    $stmt->bind_param('ssi', $periodType, $periodStart, $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute period games upsert: ' . $stmt->error);
    }
    $stmt->close();

    $stmt = $con->prepare(
        'SELECT games FROM player_period_games '
        . 'WHERE period_type = ? AND period_start = ? AND player_id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare period games read: ' . $con->error);
    }
    $stmt->bind_param('ssi', $periodType, $periodStart, $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute period games read: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($row === false || $row === null) {
        throw new RuntimeException("period games row missing after upsert {$periodType}/{$playerId}");
    }

    $games = (int) $row['games'];

    return [
        'games' => $games,
        'is_new_period' => $games === 1,
    ];
}

function k2_post_game_bump_participation_period(
    mysqli $con,
    int $playerId,
    string $periodType,
    string $dayStart
): void {
    if (!isset(K2_POST_GAME_PARTICIPATION_ACTIVE_COLUMN[$periodType])) {
        throw new InvalidArgumentException('invalid participation period type: ' . $periodType);
    }

    $column = K2_POST_GAME_PARTICIPATION_ACTIVE_COLUMN[$periodType];

    if ($periodType === 'day') {
        $stmt = $con->prepare(
            'INSERT INTO player_activity_participation '
            . '(player_id, active_days, first_rated_day, last_rated_day) VALUES (?, 1, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'active_days = active_days + 1, '
            . 'first_rated_day = COALESCE(first_rated_day, VALUES(first_rated_day)), '
            . 'last_rated_day = GREATEST(COALESCE(last_rated_day, VALUES(last_rated_day)), VALUES(last_rated_day))'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare participation day bump: ' . $con->error);
        }
        $stmt->bind_param('iss', $playerId, $dayStart, $dayStart);
    } else {
        $sql = 'INSERT INTO player_activity_participation (player_id, `' . $column . '`) VALUES (?, 1) '
            . 'ON DUPLICATE KEY UPDATE `' . $column . '` = `' . $column . '` + 1';
        $stmt = $con->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('prepare participation bump: ' . $con->error);
        }
        $stmt->bind_param('i', $playerId);
    }

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('execute participation bump: ' . $err);
    }
    $stmt->close();
}

function k2_post_game_update_peak_period(
    mysqli $con,
    string $periodType,
    int $playerId,
    string $periodStart,
    int $games
): void {
    $stmt = $con->prepare(
        'SELECT period_start, games FROM player_peak_period_games '
        . 'WHERE period_type = ? AND player_id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare peak read: ' . $con->error);
    }
    $stmt->bind_param('si', $periodType, $playerId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute peak read: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : false;
    $stmt->close();

    if ($row === false || $row === null) {
        $ins = $con->prepare(
            'INSERT INTO player_peak_period_games (period_type, player_id, period_start, games) '
            . 'VALUES (?, ?, ?, ?)'
        );
        if ($ins === false) {
            throw new RuntimeException('prepare peak insert: ' . $con->error);
        }
        $ins->bind_param('sisi', $periodType, $playerId, $periodStart, $games);
        if (!$ins->execute()) {
            throw new RuntimeException('execute peak insert: ' . $ins->error);
        }
        $ins->close();

        return;
    }

    $peakGames = (int) $row['games'];
    $peakStart = (string) $row['period_start'];
    if ($games > $peakGames || ($games === $peakGames && $periodStart < $peakStart)) {
        $upd = $con->prepare(
            'UPDATE player_peak_period_games SET period_start = ?, games = ? '
            . 'WHERE period_type = ? AND player_id = ?'
        );
        if ($upd === false) {
            throw new RuntimeException('prepare peak update: ' . $con->error);
        }
        $upd->bind_param('sisi', $periodStart, $games, $periodType, $playerId);
        if (!$upd->execute()) {
            throw new RuntimeException('execute peak update: ' . $upd->error);
        }
        $upd->close();
    }
}

function k2_post_game_apply_period_activity_for_player(
    mysqli $con,
    int $playerId,
    array $periodStarts
): array {
    $dayGames = 0;
    $weekGames = 0;
    $monthGames = 0;
    $isNewPeriod = [
        'day' => false,
        'week' => false,
        'month' => false,
        'year' => false,
    ];
    $participationEnabled = k2_post_game_participation_table_available($con);
    $dayStart = $periodStarts['day'] ?? '';

    foreach ($periodStarts as $periodType => $periodStart) {
        $upsert = k2_post_game_upsert_period_game($con, $periodType, $periodStart, $playerId);
        $games = $upsert['games'];
        k2_post_game_update_peak_period($con, $periodType, $playerId, $periodStart, $games);

        if ($upsert['is_new_period']) {
            $isNewPeriod[$periodType] = true;
            if ($participationEnabled) {
                k2_post_game_bump_participation_period(
                    $con,
                    $playerId,
                    $periodType,
                    $dayStart !== '' ? $dayStart : (string) $periodStart
                );
            }
        }

        if ($periodType === 'day') {
            $dayGames = $games;
        }
        if ($periodType === 'week') {
            $weekGames = $games;
        }
        if ($periodType === 'month') {
            $monthGames = $games;
        }
    }

    return [
        'day' => $dayGames,
        'week' => $weekGames,
        'month' => $monthGames,
        'is_new_period' => $isNewPeriod,
    ];
}

/**
 * After one rated game: period games + peak; optional P5 aggregates when $derived is set.
 *
 * @param array<string, mixed> $game must include Date, idA, idB
 * @param array<string, mixed>|null $derived ratedresults derived row (P5)
 */
/**
 * @return array{
 *   dayA: int,
 *   dayB: int,
 *   weekA: int,
 *   weekB: int,
 *   monthA: int,
 *   monthB: int,
 *   weekStart: string,
 *   periodStarts: array<string, string>,
 *   isNewPeriodA: array<string, bool>,
 *   isNewPeriodB: array<string, bool>
 * }|null when $derived set
 */
function k2_post_game_update_period_activity_after_game(
    mysqli $con,
    array $game,
    ?array $derived = null
): ?array {
    if (!k2_post_game_period_tables_available($con)) {
        return $derived !== null
            ? [
                'dayA' => 0,
                'dayB' => 0,
                'weekA' => 0,
                'weekB' => 0,
                'monthA' => 0,
                'monthB' => 0,
                'weekStart' => '',
                'periodStarts' => [],
                'isNewPeriodA' => [],
                'isNewPeriodB' => [],
            ]
            : null;
    }

    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];
    $periodStarts = k2_post_game_period_starts_for_game($game);

    $countsA = k2_post_game_apply_period_activity_for_player($con, $idA, $periodStarts);
    $countsB = k2_post_game_apply_period_activity_for_player($con, $idB, $periodStarts);

    if ($derived !== null) {
        require_once __DIR__ . '/post_game_period_aggregates.php';
        k2_post_game_update_period_aggregates_after_game(
            $con,
            $game,
            $derived,
            $periodStarts,
            $countsA['day'],
            $countsB['day']
        );
    }

    if ($derived === null) {
        return null;
    }

    return [
        'dayA' => $countsA['day'],
        'dayB' => $countsB['day'],
        'weekA' => $countsA['week'],
        'weekB' => $countsB['week'],
        'monthA' => $countsA['month'],
        'monthB' => $countsB['month'],
        'weekStart' => $periodStarts['week'],
        'periodStarts' => $periodStarts,
        'isNewPeriodA' => $countsA['is_new_period'],
        'isNewPeriodB' => $countsB['is_new_period'],
    ];
}
