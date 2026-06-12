<?php
/**
 * Amiga profile moments — trophy games from amiga_player_stats *GameID pointers.
 *
 * Single-game fetches only (no amiga_games table scans).
 */
declare(strict_types=1);

require_once __DIR__ . '/k2_safety.php';
require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
/**
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
function amiga_player_moment_fetch_games(mysqli $con, array $gameIds, int $playerId): array
{
    $gameIds = array_values(array_unique(array_filter(
        array_map('intval', $gameIds),
        static fn (int $id): bool => $id > 0
    )));
    if ($gameIds === [] || $playerId < 1) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($gameIds), '?'));
    $types = str_repeat('i', count($gameIds)) . 'ii';
    $params = array_merge($gameIds, [$playerId, $playerId]);
    $sql = 'SELECT r.id, r.Date, r.idA, r.idB, r.NameA, r.NameB, r.GoalsA, r.GoalsB, r.ActualScore '
        . amiga_rated_games_from_sql()
        . " WHERE r.id IN ({$placeholders}) AND (r.idA = ? OR r.idB = ?)";
    $rows = amiga_games_query_all($con, $sql, $types, $params);
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

        return k2_amiga_route('amiga-game', ['id' => $gameId]);
    }

    $params = ['id' => $playerId];
    if ($opponentId > 0) {
        $params['opponent'] = $opponentId;
    }

    return amiga_games_build_url($params);
}

/**
 * Trophy moments for profile (biggest win, most goals, peak rating game).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_moments_load(mysqli $con, int $playerId): array
{
    if ($playerId < 1) {
        return [];
    }

    $stmt = $con->prepare(
        'SELECT BiggestWinGameID, MostGoalsScoredGameID, PeakRatingGameID, PeakRating '
        . 'FROM amiga_player_stats WHERE player_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $playerId);
    $stmt->execute();
    $res = $stmt->get_result();
    $stats = $res ? $res->fetch_assoc() : false;
    $stmt->close();
    if ($stats === false) {
        return [];
    }

    $peakRating = !k2_db_is_null($stats['PeakRating'] ?? null) && (float) $stats['PeakRating'] > 0
        ? (int) round((float) $stats['PeakRating'])
        : null;

    $defs = [
        [
            'key' => 'biggest_win',
            'label' => 'Biggest win',
            'game_id' => (int) ($stats['BiggestWinGameID'] ?? 0),
            'icon' => '⚡',
            'tag' => 'Margin',
            'peak_rating' => null,
        ],
        [
            'key' => 'goal_festival',
            'label' => 'Goal festival',
            'game_id' => (int) ($stats['MostGoalsScoredGameID'] ?? 0),
            'icon' => '🎯',
            'tag' => 'Attack',
            'peak_rating' => null,
        ],
        [
            'key' => 'peak_rating',
            'label' => 'Peak rating game',
            'game_id' => (int) ($stats['PeakRatingGameID'] ?? 0),
            'icon' => '★',
            'tag' => 'Peak',
            'peak_rating' => $peakRating,
        ],
    ];

    $gameIds = [];
    foreach ($defs as $def) {
        if ((int) $def['game_id'] > 0) {
            $gameIds[] = (int) $def['game_id'];
        }
    }
    $gamesById = amiga_player_moment_fetch_games($con, $gameIds, $playerId);

    $moments = [];
    foreach ($defs as $def) {
        $gameId = (int) $def['game_id'];
        if ($gameId < 1 || !isset($gamesById[$gameId])) {
            continue;
        }
        $moments[] = array_merge($def, $gamesById[$gameId]);
    }

    return $moments;
}
