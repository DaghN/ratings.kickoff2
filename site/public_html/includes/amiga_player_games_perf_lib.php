<?php
/**
 * Performance rating for the filtered games list on /amiga/player/games.php (async API).
 *
 * @see docs/amiga-performance-rating.md
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_db.php';
require_once __DIR__ . '/amiga_player_games_lib.php';
require_once __DIR__ . '/amiga_player_games_filter_facets.php';
require_once __DIR__ . '/amiga_performance_rating.php';
require_once __DIR__ . '/amiga_snapshot_context.php';

/**
 * @param array<string, mixed> $query GET params (id, filters — sort/dir ignored)
 * @return array{
 *     player_id: int,
 *     games: int,
 *     performance_rating: ?int,
 *     reason: ?string
 * }
 */
function amiga_player_games_list_performance_rating(mysqli $con, int $playerId, array $query): array
{
    $ctx = amiga_snapshot_context_from_request($con);
    $filters = amiga_player_games_filters_from_request($con, $playerId, $query, $ctx);
    amiga_player_games_validate_filters_career_wide($con, $playerId, $filters, $ctx);

    $whereTypes = '';
    $whereParams = [];
    $whereSql = amiga_games_where_clause(
        $playerId,
        $filters['result'],
        $filters['opponent'],
        $filters['tournament'],
        $filters['event'],
        $filters['country'],
        $filters['opp_country'],
        $filters['day'],
        $filters['since'],
        $filters['until'],
        $filters['year'],
        $filters['gf'],
        $filters['ga'],
        $filters['gs'],
        $filters['gd'],
        $whereTypes,
        $whereParams,
        $ctx
    );

    $rows = amiga_games_query_all(
        $con,
        'SELECT r.idA, r.idB, r.ActualScore, r.RatingA, r.RatingB '
            . amiga_rated_games_from_sql()
            . ' WHERE '
            . $whereSql,
        $whereTypes,
        $whereParams
    );

    $pairs = [];
    foreach ($rows as $row) {
        $idA = (int) ($row['idA'] ?? 0);
        $actualScore = (float) ($row['ActualScore'] ?? 0);
        if ($idA === $playerId) {
            $pairs[] = [
                'opponent' => (float) ($row['RatingB'] ?? 0),
                'score' => $actualScore,
            ];
        } else {
            $pairs[] = [
                'opponent' => (float) ($row['RatingA'] ?? 0),
                'score' => 1.0 - $actualScore,
            ];
        }
    }

    $gameCount = count($pairs);
    $perf = amiga_performance_rating_from_pairs($pairs);

    $reason = null;
    if ($gameCount < AMIGA_PERFORMANCE_RATING_MIN_GAMES) {
        $reason = 'min_games';
    } elseif ($perf === null) {
        $reason = 'perfect_record';
    }

    return [
        'player_id' => $playerId,
        'games' => $gameCount,
        'performance_rating' => $perf !== null ? (int) round($perf) : null,
        'reason' => $reason,
    ];
}
