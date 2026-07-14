<?php
/**
 * Amiga profile moments — trophy games from career *GameID pointers (present or cutoff snapshot).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
require_once __DIR__ . '/amiga_snapshot_context.php';
require_once __DIR__ . '/amiga_player_current_lib.php';
require_once __DIR__ . '/amiga_player_snapshot_lib.php';/**
 * @param array<string, mixed> $row ratedresults-shaped row from amiga_rated_games_from_sql()
 * @return array<string, mixed>
 */
function amiga_player_moment_parse_game_row(array $row, int $playerId): array
{
    $isA = (int) ($row['idA'] ?? 0) === $playerId;
    $goalsFor = $isA ? (int) ($row['GoalsA'] ?? 0) : (int) ($row['GoalsB'] ?? 0);
    $goalsAgainst = $isA ? (int) ($row['GoalsB'] ?? 0) : (int) ($row['GoalsA'] ?? 0);
    $opponentId = $isA ? (int) ($row['idB'] ?? 0) : (int) ($row['idA'] ?? 0);
    $opponentName = $isA ? (string) ($row['NameB'] ?? '') : (string) ($row['NameA'] ?? '');
    $actual = (float) ($row['ActualScore'] ?? 0);

    if (abs($actual - 0.5) < 0.001) {
        $outcome = 'Draw';
        $outcomeClass = 'pm-outcome--draw';
    } elseif (($isA && $actual >= 0.99) || (!$isA && $actual <= 0.01)) {
        $outcome = 'Win';
        $outcomeClass = 'pm-outcome--win';
    } else {
        $outcome = 'Loss';
        $outcomeClass = 'pm-outcome--loss';
    }

    $dateRaw = (string) ($row['Date'] ?? '');
    $dateTs = strtotime($dateRaw);

    return [
        'outcome' => $outcome,
        'outcome_class' => $outcomeClass,
        'score' => $goalsFor . '–' . $goalsAgainst,
        'goals_for' => $goalsFor,
        'goals_against' => $goalsAgainst,
        'opponent_id' => $opponentId,
        'opponent_name' => $opponentName,
        'game_id' => (int) ($row['id'] ?? 0),
        'date' => $dateTs !== false ? date('M j, Y', $dateTs) : $dateRaw,
    ];
}

/**
 * @param list<int> $gameIds
 * @return array<int, array<string, mixed>>
 */
function amiga_player_moment_fetch_games(
    mysqli $con,
    array $gameIds,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null
): array {
    $gameIds = array_values(array_unique(array_filter(
        array_map('intval', $gameIds),
        static fn (int $id): bool => $id > 0
    )));
    if ($gameIds === [] || $playerId < 1) {
        return [];
    }

    $cutoffTypes = '';
    $cutoffParams = [];
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $cutoffTypes, $cutoffParams);

    $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
    $types = str_repeat('i', count($gameIds)) . 'ii' . $cutoffTypes;
    $params = array_merge($gameIds, [$playerId, $playerId], $cutoffParams);
    $sql = 'SELECT r.id, r.Date, r.idA, r.idB, r.NameA, r.NameB, r.GoalsA, r.GoalsB, r.ActualScore, r.SumOfGoals '
        . amiga_rated_games_from_sql()
        . " WHERE r.id IN ({$placeholders}) AND (r.idA = ? OR r.idB = ?)" . $cutoffSql;    $rows = amiga_games_query_all($con, $sql, $types, $params);
    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['id']] = amiga_player_moment_parse_game_row($row, $playerId);
    }

    return $byId;
}

function amiga_player_moment_games_href(int $playerId, int $opponentId, int $gameId = 0): string
{
    if ($gameId > 0) {
        require_once __DIR__ . '/k2_amiga_routes.php';

        return k2_amiga_game_page_url($gameId);
    }

    $params = ['id' => $playerId];
    if ($opponentId > 0) {
        $params['opponent'] = $opponentId;
    }

    return amiga_games_build_url($params);
}

function amiga_player_moment_bonanza_ratio_ok(int $goalsFor, int $goalsAgainst): bool
{
    return $goalsAgainst < (3 * $goalsFor);
}

/**
 * @return ?array<string, mixed>
 */
function amiga_player_moment_load_game(
    mysqli $con,
    int $gameId,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null
): ?array {
    if ($gameId <= 0 || $playerId < 1) {
        return null;
    }

    $games = amiga_player_moment_fetch_games($con, [$gameId], $playerId, $ctx);
    return $games[$gameId] ?? null;
}

/**
 * @return ?array<string, mixed>
 */
function amiga_player_moment_load_bonanza_ratio_fallback(
    mysqli $con,
    int $playerId,
    ?AmigaSnapshotContext $ctx = null
): ?array {
    if ($playerId < 1) {
        return null;
    }

    $cutoffTypes = '';
    $cutoffParams = [];
    $cutoffSql = amiga_snapshot_rated_game_cutoff_and_sql($ctx, $cutoffTypes, $cutoffParams);

    $sql = 'SELECT r.id, r.Date, r.idA, r.idB, r.NameA, r.NameB, r.GoalsA, r.GoalsB, r.ActualScore, r.SumOfGoals '
        . amiga_rated_games_from_sql($playerId)
        . ' WHERE ((r.idA = ? AND r.GoalsB < 3 * r.GoalsA)'
        . '    OR (r.idB = ? AND r.GoalsA < 3 * r.GoalsB))'
        . $cutoffSql
        . ' ORDER BY r.SumOfGoals DESC, r.Date DESC, r.id DESC'
        . ' LIMIT 1';
    $rows = amiga_games_query_all($con, $sql, 'ii' . $cutoffTypes, array_merge([$playerId, $playerId], $cutoffParams));    $row = $rows[0] ?? null;

    return is_array($row) ? amiga_player_moment_parse_game_row($row, $playerId) : null;
}

/**
 * @return ?array<string, mixed>
 */
function amiga_player_moment_load_bonanza_trophy(
    mysqli $con,
    int $playerId,
    int $biggestSumGameId,
    ?AmigaSnapshotContext $ctx = null
): ?array {    $def = [
        'key' => 'shootout',
        'label' => 'Total goals bonanza',
        'icon' => '🔥',
        'tag' => 'Chaos',
        'peak_rating' => null,
        'tournament_id' => 0,
        'is_event' => false,
    ];

    $primary = amiga_player_moment_load_game($con, $biggestSumGameId, $playerId, $ctx);
    if ($primary !== null
        && amiga_player_moment_bonanza_ratio_ok((int) $primary['goals_for'], (int) $primary['goals_against'])) {
        return array_merge($def, $primary);
    }

    $fallback = amiga_player_moment_load_bonanza_ratio_fallback($con, $playerId, $ctx);    if ($fallback === null) {
        return null;
    }

    return array_merge($def, $fallback);
}

/**
 * @return ?array<string, mixed>
 */
function amiga_player_moment_load_max_rated_victim(
    mysqli $con,
    int $playerId,
    mixed $highestRatedVictim,
    int $gameId,
    ?AmigaSnapshotContext $ctx = null
): ?array {
    if ($gameId < 1) {
        return null;
    }

    $parsed = amiga_player_moment_load_game($con, $gameId, $playerId, $ctx);    if ($parsed === null) {
        return null;
    }

    $parsed['victim_rating'] = ($highestRatedVictim === null || k2_db_is_null($highestRatedVictim))
        ? null
        : (int) round((float) $highestRatedVictim);

    return $parsed;
}

/**
 * Trophy + peak moments for profile (online card order + Amiga peak last).
 *
 * @return array{max_rated_victim: ?array<string, mixed>, moments: list<array<string, mixed>>}
 */
function amiga_player_moments_load(mysqli $con, int $playerId, ?AmigaSnapshotContext $ctx = null): array
{
    $empty = ['max_rated_victim' => null, 'moments' => []];
    if ($playerId < 1) {
        return $empty;
    }

    $ctx ??= amiga_snapshot_context_peek();
    if ($ctx instanceof AmigaSnapshotContext && $ctx->isActive()) {
        $career = amiga_player_snapshot_row_at_cutoff($con, $playerId, $ctx);
        if ($career === null || (int) ($career['NumberGames'] ?? 0) <= 0) {
            return $empty;
        }
    } else {
        $career = amiga_player_current_row($con, $playerId);
        if ($career === null) {
            return $empty;
        }
    }
    $stats = $career;

    $maxRatedVictim = amiga_player_moment_load_max_rated_victim(
        $con,
        $playerId,
        $stats['HighestRatedVictim'] ?? null,
        (int) ($stats['HighestRatedVictimGameID'] ?? 0),
        $ctx
    );
    $peakRating = !k2_db_is_null($stats['PeakRating'] ?? null) && (float) $stats['PeakRating'] > 0
        ? (int) round((float) $stats['PeakRating'])
        : null;
    $peakTournamentId = (int) ($stats['peak_rating_tournament_id'] ?? 0);

    $gameTrophyDefs = [
        [
            'key' => 'biggest_win',
            'label' => 'Biggest win',
            'game_id' => (int) ($stats['BiggestWinGameID'] ?? 0),
            'icon' => '⚡',
            'tag' => 'Margin',
        ],
        [
            'key' => 'biggest_draw',
            'label' => 'Biggest draw',
            'game_id' => (int) ($stats['BiggestDrawGameID'] ?? 0),
            'icon' => '⚖',
            'tag' => 'Stalemate epic',
        ],
        [
            'key' => 'goal_festival',
            'label' => 'Goal festival',
            'game_id' => (int) ($stats['MostGoalsScoredGameID'] ?? 0),
            'icon' => '🎯',
            'tag' => 'Attack',
        ],
    ];

    $bonanza = amiga_player_moment_load_bonanza_trophy(
        $con,
        $playerId,
        (int) ($stats['BiggestSumOfGoalsGameID'] ?? 0),
        $ctx
    );
    $gameIds = [];
    foreach ($gameTrophyDefs as $def) {
        if ((int) $def['game_id'] > 0) {
            $gameIds[] = (int) $def['game_id'];
        }
    }
    $gamesById = amiga_player_moment_fetch_games($con, $gameIds, $playerId, $ctx);
    $moments = [];
    foreach ($gameTrophyDefs as $def) {
        $gameId = (int) $def['game_id'];
        if ($gameId < 1 || !isset($gamesById[$gameId])) {
            continue;
        }
        $moments[] = array_merge($def, $gamesById[$gameId], [
            'peak_rating' => null,
            'tournament_id' => 0,
            'is_event' => false,
        ]);
    }

    if ($bonanza !== null) {
        $moments[] = $bonanza;
    }

    $peakTournament = null;
    if ($peakTournamentId > 0) {
        require_once __DIR__ . '/amiga_tournament_lib.php';
        $peakTournament = amiga_tournament_load($con, $peakTournamentId, false);
    }
    if ($peakRating !== null && $peakTournament !== null) {
        $eventDate = (string) ($peakTournament['event_date'] ?? '');
        $dateTs = strtotime($eventDate);
        $moments[] = [
            'key' => 'peak_rating',
            'label' => 'Peak rating',
            'game_id' => 0,
            'icon' => '★',
            'tag' => 'Peak',
            'peak_rating' => $peakRating,
            'tournament_id' => $peakTournamentId,
            'tournament_name' => (string) ($peakTournament['name'] ?? ''),
            'date' => $dateTs !== false ? date('M j, Y', $dateTs) : $eventDate,
            'is_event' => true,
        ];
    }

    return [
        'max_rated_victim' => $maxRatedVictim,
        'moments' => $moments,
    ];
}
