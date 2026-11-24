<?php
/**
 * Player tournament participation rows for Finish (parity with
 * scripts/amiga/player_tournament_participation.py).
 *
 * Built in memory after the standings refresh and stored on the event snapshots;
 * the old tables amiga_player_tournament_participation and _totals are retired.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/amiga_tournament_lib.php';
require_once dirname(__DIR__, 3) . '/includes/amiga_participation_placement.php';
require_once dirname(__DIR__, 3) . '/includes/amiga_perfect_event.php';

/**
 * Average goals per game in event (4 d.p.; NULL when games = 0).
 */
function amiga_ops_participation_avg_goals_per_game(int $goals, int $games): ?float
{
    if ($games <= 0) {
        return null;
    }

    return round($goals / $games, 4);
}

/**
 * Per-player event game totals from amiga_games (participation volume stats).
 */
function amiga_ops_participation_player_games_rollup_sql(): string
{
    return <<<'SQL'
(
  SELECT
    tournament_id,
    player_id,
    SUM(games) AS games,
    SUM(wins) AS wins,
    SUM(draws) AS draws,
    SUM(losses) AS losses,
    SUM(goals_for) AS goals_for,
    SUM(goals_against) AS goals_against
  FROM (
    SELECT
      g.tournament_id,
      g.player_a_id AS player_id,
      COUNT(*) AS games,
      SUM(CASE WHEN g.goals_a > g.goals_b THEN 1 ELSE 0 END) AS wins,
      SUM(CASE WHEN g.goals_a = g.goals_b THEN 1 ELSE 0 END) AS draws,
      SUM(CASE WHEN g.goals_a < g.goals_b THEN 1 ELSE 0 END) AS losses,
      SUM(g.goals_a) AS goals_for,
      SUM(g.goals_b) AS goals_against
    FROM amiga_games g
    GROUP BY g.tournament_id, g.player_a_id
    UNION ALL
    SELECT
      g.tournament_id,
      g.player_b_id AS player_id,
      COUNT(*) AS games,
      SUM(CASE WHEN g.goals_b > g.goals_a THEN 1 ELSE 0 END) AS wins,
      SUM(CASE WHEN g.goals_b = g.goals_a THEN 1 ELSE 0 END) AS draws,
      SUM(CASE WHEN g.goals_b < g.goals_a THEN 1 ELSE 0 END) AS losses,
      SUM(g.goals_b) AS goals_for,
      SUM(g.goals_a) AS goals_against
    FROM amiga_games g
    GROUP BY g.tournament_id, g.player_b_id
  ) side
  GROUP BY tournament_id, player_id
) pg
SQL;
}

/**
 * @return array<int, array<string, mixed>>
 */
function amiga_ops_participation_games_rollups_for_tournament(mysqli $con, int $tournamentId): array
{
    $gamesRollup = amiga_ops_participation_player_games_rollup_sql();
    $sql = "SELECT pg.player_id, pg.games, pg.wins, pg.draws, pg.losses,
                   pg.goals_for, pg.goals_against
            FROM {$gamesRollup}
            WHERE pg.tournament_id = ?";
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare participation games rollup: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute participation games rollup: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $rollups = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rollups[(int) $row['player_id']] = $row;
        }
        $res->free();
    }
    $stmt->close();

    return $rollups;
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_ops_participation_standing_rows_for_tournament(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT scope_type, scope_key, player_id, position
         FROM amiga_tournament_standings
         WHERE tournament_id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare participation standings: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute participation standings: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $rows = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
    $stmt->close();

    return $rows;
}

/**
 * Tier E curated finish positions for one tournament.
 *
 * @return array<int, int> player_id => event_finish_position
 */
function amiga_ops_participation_finish_overrides_for_tournament(mysqli $con, int $tournamentId): array
{
    if ($tournamentId < 1) {
        return [];
    }

    $stmt = $con->prepare(
        'SELECT player_id, event_finish_position
         FROM amiga_tournament_finish_override
         WHERE tournament_id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare finish overrides: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute finish overrides: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $overrides = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $overrides[(int) $row['player_id']] = (int) $row['event_finish_position'];
        }
        $res->free();
    }
    $stmt->close();

    return $overrides;
}

/**
 * @return array<int, array<string, mixed>>
 */
function amiga_ops_participation_rating_events_for_tournament(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT player_id, rating_before, rating_delta, rating_after,
                performance_rating, games_in_event, finalized_at
         FROM amiga_player_event_snapshots
         WHERE tournament_id = ?'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare participation rating events: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute participation rating events: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $events = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $events[(int) $row['player_id']] = $row;
        }
        $res->free();
    }
    $stmt->close();

    return $events;
}

/**
 * Build participation-shaped rows in memory (slice 8 — no legacy participation table).
 *
 * @param array<int, array<string, mixed>>|null $ratingEventsByPlayer
 * @return array<int, array<string, mixed>>
 */
function amiga_ops_participation_rows_for_tournament(
    mysqli $con,
    int $tournamentId,
    ?array $ratingEventsByPlayer = null,
): array {
    if ($tournamentId < 1) {
        return [];
    }

    $tournamentStmt = $con->prepare(
        'SELECT id, name, event_date, chrono, is_cup, country, has_league, has_cup, is_world_cup
         FROM tournaments
         WHERE id = ?
         LIMIT 1'
    );
    if ($tournamentStmt === false) {
        throw new RuntimeException('prepare participation tournament: ' . $con->error);
    }
    $tournamentStmt->bind_param('i', $tournamentId);
    if (!$tournamentStmt->execute()) {
        throw new RuntimeException('execute participation tournament: ' . $tournamentStmt->error);
    }
    $tres = $tournamentStmt->get_result();
    $tournament = $tres ? $tres->fetch_assoc() : false;
    if ($tres) {
        $tres->free();
    }
    $tournamentStmt->close();
    if ($tournament === false) {
        return [];
    }

    $rollups = amiga_ops_participation_games_rollups_for_tournament($con, $tournamentId);
    if ($rollups === []) {
        return [];
    }

    $standingRows = amiga_ops_participation_standing_rows_for_tournament($con, $tournamentId);
    $finishOverrides = amiga_ops_participation_finish_overrides_for_tournament($con, $tournamentId);
    if ($ratingEventsByPlayer === null) {
        $ratingEventsByPlayer = amiga_ops_participation_rating_events_for_tournament($con, $tournamentId);
    }
    $playerIds = array_keys($rollups);
    sort($playerIds, SORT_NUMERIC);
    $tournamentName = (string) $tournament['name'];
    $hasLeague = (bool) ((int) ($tournament['has_league'] ?? 0));
    $hasCup = (bool) ((int) ($tournament['has_cup'] ?? 0));
    $isWorldCup = (bool) ((int) ($tournament['is_world_cup'] ?? 0));
    $eventFinishes = amiga_participation_derive_event_finish_position(
        $standingRows,
        $tournamentName,
        $hasLeague,
        $hasCup,
        $playerIds,
        $finishOverrides,
        $isWorldCup
    );

    $rowsByPlayer = [];
    foreach ($playerIds as $playerId) {
        $rollup = $rollups[$playerId];
        $eventFinishPosition = $eventFinishes[$playerId] ?? null;
        if ($eventFinishPosition !== null) {
            $eventFinishPosition = (int) $eventFinishPosition;
        }
        $bestKnockoutPhase = amiga_participation_derive_best_knockout_phase($standingRows, $playerId);
        $rating = $ratingEventsByPlayer[$playerId] ?? null;
        $wins = (int) ($rollup['wins'] ?? 0);
        $draws = (int) ($rollup['draws'] ?? 0);
        $games = (int) ($rollup['games'] ?? 0);
        $goalsFor = (int) ($rollup['goals_for'] ?? 0);
        $goalsAgainst = (int) ($rollup['goals_against'] ?? 0);
        $rowsByPlayer[$playerId] = [
            'player_id' => $playerId,
            'tournament_id' => $tournamentId,
            'event_date' => $tournament['event_date'],
            'event_chrono' => (float) ($tournament['chrono'] ?? 0),
            'tournament_name' => $tournamentName,
            'is_cup' => (int) ($tournament['is_cup'] ?? 0),
            'country' => (string) ($tournament['country'] ?? ''),
            'has_league' => (int) ($tournament['has_league'] ?? 0),
            'has_cup' => (int) ($tournament['has_cup'] ?? 0),
            'is_world_cup' => (int) ($tournament['is_world_cup'] ?? 0),
            'event_finish_position' => $eventFinishPosition,
            'best_knockout_phase' => $bestKnockoutPhase,
            'event_points' => $wins * 3 + $draws,
            'games' => $games,
            'wins' => $wins,
            'draws' => $draws,
            'losses' => (int) ($rollup['losses'] ?? 0),
            'goals_for' => $goalsFor,
            'goals_against' => $goalsAgainst,
            'avg_goals_for' => amiga_ops_participation_avg_goals_per_game($goalsFor, $games),
            'avg_goals_against' => amiga_ops_participation_avg_goals_per_game($goalsAgainst, $games),
            'rating_before' => $rating['rating_before'] ?? null,
            'rating_delta' => $rating['rating_delta'] ?? null,
            'rating_after' => $rating['rating_after'] ?? null,
            'performance_rating' => $rating['performance_rating'] ?? null,
            'games_in_event' => (int) ($rating['games_in_event'] ?? 0),
            'finalized_at' => $rating['finalized_at'] ?? null,
            'is_winner' => amiga_participation_is_winner($tournamentName, $eventFinishPosition) ? 1 : 0,
            'is_perfect_event' => amiga_is_perfect_event_from_rollup($games, $wins, $draws, (int) ($rollup['losses'] ?? 0)) ? 1 : 0,
        ];
    }

    return $rowsByPlayer;
}

/**
 * @return array{participation_rows: int, totals_players: int}
 */
function amiga_ops_participation_refresh_tournament(
    mysqli $con,
    int $tournamentId,
    ?array $ratingEventsByPlayer = null,
): array {
    if ($tournamentId < 1) {
        return ['participation_rows' => 0, 'totals_players' => 0];
    }

    $rows = amiga_ops_participation_rows_for_tournament($con, $tournamentId, $ratingEventsByPlayer);

    return [
        'participation_rows' => count($rows),
        'totals_players' => count($rows),
        'participation_by_player' => $rows,
    ];
}
