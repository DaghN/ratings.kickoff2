<?php
/**
 * Amiga profile moments — trophy games from career *GameID pointers (amiga_player_current).
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

        return k2_amiga_game_page_url($gameId);
    }

    $params = ['id' => $playerId];
    if ($opponentId > 0) {
        $params['opponent'] = $opponentId;
    }

    return amiga_games_build_url($params);
}

/**
 * Trophy moments for profile (biggest win, most goals, peak rating event).
 *
 * @return list<array<string, mixed>>
 */
function amiga_player_moments_load(mysqli $con, int $playerId): array
{
    if ($playerId < 1) {
        return [];
    }

    $career = amiga_player_current_row($con, $playerId);
    if ($career === null) {
        return [];
    }
    $stats = $career;

    $peakRating = !k2_db_is_null($stats['PeakRating'] ?? null) && (float) $stats['PeakRating'] > 0
        ? (int) round((float) $stats['PeakRating'])
        : null;
    $peakTournamentId = (int) ($stats['peak_rating_tournament_id'] ?? 0);

    $defs = [
        [
            'key' => 'biggest_win',
            'label' => 'Biggest win',
            'game_id' => (int) ($stats['BiggestWinGameID'] ?? 0),
            'icon' => '⚡',
            'tag' => 'Margin',
            'peak_rating' => null,
            'tournament_id' => 0,
        ],
        [
            'key' => 'goal_festival',
            'label' => 'Goal festival',
            'game_id' => (int) ($stats['MostGoalsScoredGameID'] ?? 0),
            'icon' => '🎯',
            'tag' => 'Attack',
            'peak_rating' => null,
            'tournament_id' => 0,
        ],
        [
            'key' => 'peak_rating',
            'label' => 'Peak rating',
            'game_id' => 0,
            'icon' => '★',
            'tag' => 'Peak',
            'peak_rating' => $peakRating,
            'tournament_id' => $peakTournamentId,
        ],
    ];

    $gameIds = [];
    foreach ($defs as $def) {
        if ((int) $def['game_id'] > 0) {
            $gameIds[] = (int) $def['game_id'];
        }
    }
    $gamesById = amiga_player_moment_fetch_games($con, $gameIds, $playerId);

    $peakTournament = null;
    if ($peakTournamentId > 0) {
        require_once __DIR__ . '/amiga_tournament_lib.php';
        $peakTournament = amiga_tournament_load($con, $peakTournamentId, false);
    }

    $moments = [];
    foreach ($defs as $def) {
        if (($def['key'] ?? '') === 'peak_rating') {
            if ($peakRating === null || $peakTournament === null) {
                continue;
            }
            $eventDate = (string) ($peakTournament['event_date'] ?? '');
            $dateTs = strtotime($eventDate);
            $moments[] = array_merge($def, [
                'tournament_name' => (string) ($peakTournament['name'] ?? ''),
                'date' => $dateTs !== false ? date('M j, Y', $dateTs) : $eventDate,
                'is_event' => true,
            ]);
            continue;
        }

        $gameId = (int) $def['game_id'];
        if ($gameId < 1 || !isset($gamesById[$gameId])) {
            continue;
        }
        $moments[] = array_merge($def, $gamesById[$gameId], ['is_event' => false]);
    }

    return $moments;
}
