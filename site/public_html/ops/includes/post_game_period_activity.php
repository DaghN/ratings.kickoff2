<?php
/**
 * player_period_games + player_peak_period_games (P4).
 *
 * Mirrors scripts/ladder/sql/player_period_games_rebuild.sql period bucketing (UTC).
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_bootstrap.php';

/**
 * UTC period starts for a rated game (aligned with rebuild SQL).
 *
 * @return array<string, string> period_type => period_start (Y-m-d)
 */
function k2_post_game_period_starts_from_game_date(string $gameDate): array
{
    $dt = new DateTimeImmutable($gameDate, new DateTimeZone('UTC'));

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

function k2_post_game_period_tables_available(mysqli $con): bool
{
    return k2_ops_table_exists($con, 'player_period_games')
        && k2_ops_table_exists($con, 'player_peak_period_games');
}

function k2_post_game_upsert_period_game(
    mysqli $con,
    string $periodType,
    string $periodStart,
    int $playerId
): int {
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

    return (int) $row['games'];
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
): int {
    $dayGames = 0;
    foreach ($periodStarts as $periodType => $periodStart) {
        $games = k2_post_game_upsert_period_game($con, $periodType, $periodStart, $playerId);
        k2_post_game_update_peak_period($con, $periodType, $playerId, $periodStart, $games);
        if ($periodType === 'day') {
            $dayGames = $games;
        }
    }

    return $dayGames;
}

/**
 * After one rated game: period games + peak; optional P5 aggregates when $derived is set.
 *
 * @param array<string, mixed> $game must include Date, idA, idB
 * @param array<string, mixed>|null $derived ratedresults derived row (P5)
 */
function k2_post_game_update_period_activity_after_game(
    mysqli $con,
    array $game,
    ?array $derived = null
): void {
    if (!k2_post_game_period_tables_available($con)) {
        return;
    }

    $idA = (int) $game['idA'];
    $idB = (int) $game['idB'];
    $periodStarts = k2_post_game_period_starts_from_game_date((string) $game['Date']);

    $dayGamesA = k2_post_game_apply_period_activity_for_player($con, $idA, $periodStarts);
    $dayGamesB = k2_post_game_apply_period_activity_for_player($con, $idB, $periodStarts);

    if ($derived !== null) {
        require_once __DIR__ . '/post_game_period_aggregates.php';
        k2_post_game_update_period_aggregates_after_game(
            $con,
            $game,
            $derived,
            $periodStarts,
            $dayGamesA,
            $dayGamesB
        );
    }
}
