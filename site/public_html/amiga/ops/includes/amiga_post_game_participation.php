<?php
/**
 * Incremental player tournament participation + totals (parity with
 * scripts/amiga/player_tournament_participation.py).
 *
 * Live finalize calls this after standings refresh; standings/catalog are
 * already updated by amiga_ops_standings_apply_game().
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
 * @return list<int>
 */
function amiga_ops_participation_player_ids_for_tournament(mysqli $con, int $tournamentId): array
{
    $sql = 'SELECT DISTINCT player_id
            FROM (
                SELECT player_a_id AS player_id FROM amiga_games WHERE tournament_id = ?
                UNION ALL
                SELECT player_b_id AS player_id FROM amiga_games WHERE tournament_id = ?
            ) g
            ORDER BY player_id';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare participation player ids: ' . $con->error);
    }
    $stmt->bind_param('ii', $tournamentId, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute participation player ids: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $ids = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[] = (int) $row['player_id'];
        }
        $res->free();
    }
    $stmt->close();

    return $ids;
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
        'SELECT id, name, event_date, chrono, is_cup, country, has_league, has_cup
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
    $eventFinishes = amiga_participation_derive_event_finish_position(
        $standingRows,
        $tournamentName,
        $hasLeague,
        $hasCup,
        $playerIds,
        $finishOverrides
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

function amiga_ops_participation_replace_tournament(mysqli $con, int $tournamentId): int
{
    $delete = $con->prepare('DELETE FROM amiga_player_tournament_participation WHERE tournament_id = ?');
    if ($delete === false) {
        throw new RuntimeException('prepare participation delete: ' . $con->error);
    }
    $delete->bind_param('i', $tournamentId);
    if (!$delete->execute()) {
        throw new RuntimeException('execute participation delete: ' . $delete->error);
    }
    $delete->close();

    $tournamentStmt = $con->prepare(
        'SELECT id, name, event_date, chrono, is_cup, country, has_league, has_cup
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
        return 0;
    }

    $rollups = amiga_ops_participation_games_rollups_for_tournament($con, $tournamentId);
    if ($rollups === []) {
        return 0;
    }

    $standingRows = amiga_ops_participation_standing_rows_for_tournament($con, $tournamentId);
    $finishOverrides = amiga_ops_participation_finish_overrides_for_tournament($con, $tournamentId);
    $ratingEvents = amiga_ops_participation_rating_events_for_tournament($con, $tournamentId);
    $playerIds = array_keys($rollups);
    sort($playerIds, SORT_NUMERIC);
    $tournamentName = (string) $tournament['name'];
    $hasLeague = (bool) ((int) ($tournament['has_league'] ?? 0));
    $hasCup = (bool) ((int) ($tournament['has_cup'] ?? 0));
    $eventFinishes = amiga_participation_derive_event_finish_position(
        $standingRows,
        $tournamentName,
        $hasLeague,
        $hasCup,
        $playerIds,
        $finishOverrides
    );

    $insert = $con->prepare(
        'INSERT INTO amiga_player_tournament_participation (
            player_id, tournament_id, event_date, event_chrono, tournament_name,
            is_cup, country, has_league, has_cup,
            event_finish_position, best_knockout_phase, event_points,
            games, wins, draws, losses, goals_for, goals_against,
            avg_goals_for, avg_goals_against,
            rating_before, rating_delta, rating_after, performance_rating,
            games_in_event, finalized_at, is_winner
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )'
    );
    if ($insert === false) {
        throw new RuntimeException('prepare participation insert: ' . $con->error);
    }

    $written = 0;
    foreach ($playerIds as $playerId) {
        $rollup = $rollups[$playerId];
        $eventFinishPosition = $eventFinishes[$playerId] ?? null;
        if ($eventFinishPosition !== null) {
            $eventFinishPosition = (int) $eventFinishPosition;
        }
        $bestKnockoutPhase = amiga_participation_derive_best_knockout_phase($standingRows, $playerId);
        $rating = $ratingEvents[$playerId] ?? null;
        $wins = (int) ($rollup['wins'] ?? 0);
        $draws = (int) ($rollup['draws'] ?? 0);
        $isWinner = amiga_participation_is_winner($tournamentName, $eventFinishPosition) ? 1 : 0;
        $ratingBefore = $rating['rating_before'] ?? null;
        $ratingDelta = $rating['rating_delta'] ?? null;
        $ratingAfter = $rating['rating_after'] ?? null;
        $performanceRating = $rating['performance_rating'] ?? null;
        $gamesInEvent = (int) ($rating['games_in_event'] ?? 0);
        $finalizedAt = $rating['finalized_at'] ?? null;
        $eventDate = $tournament['event_date'];
        $eventChrono = (float) ($tournament['chrono'] ?? 0);
        $isCup = (int) ($tournament['is_cup'] ?? 0);
        $country = (string) ($tournament['country'] ?? '');
        $hasLeague = (int) ($tournament['has_league'] ?? 0);
        $hasCup = (int) ($tournament['has_cup'] ?? 0);
        $eventPoints = $wins * 3 + $draws;
        $games = (int) ($rollup['games'] ?? 0);
        $losses = (int) ($rollup['losses'] ?? 0);
        $goalsFor = (int) ($rollup['goals_for'] ?? 0);
        $goalsAgainst = (int) ($rollup['goals_against'] ?? 0);
        $avgGoalsFor = amiga_ops_participation_avg_goals_per_game($goalsFor, $games);
        $avgGoalsAgainst = amiga_ops_participation_avg_goals_per_game($goalsAgainst, $games);
        $ratingBeforeVal = $ratingBefore !== null ? (float) $ratingBefore : null;
        $ratingDeltaVal = $ratingDelta !== null ? (float) $ratingDelta : null;
        $ratingAfterVal = $ratingAfter !== null ? (float) $ratingAfter : null;
        $performanceRatingVal = $performanceRating !== null ? (float) $performanceRating : null;
        $finalizedAtVal = $finalizedAt !== null ? (string) $finalizedAt : null;

        $insert->bind_param(
            'iisdsisiiisiiiiiiiddddddisi',
            $playerId,
            $tournamentId,
            $eventDate,
            $eventChrono,
            $tournamentName,
            $isCup,
            $country,
            $hasLeague,
            $hasCup,
            $eventFinishPosition,
            $bestKnockoutPhase,
            $eventPoints,
            $games,
            $wins,
            $draws,
            $losses,
            $goalsFor,
            $goalsAgainst,
            $avgGoalsFor,
            $avgGoalsAgainst,
            $ratingBeforeVal,
            $ratingDeltaVal,
            $ratingAfterVal,
            $performanceRatingVal,
            $gamesInEvent,
            $finalizedAtVal,
            $isWinner
        );
        if (!$insert->execute()) {
            throw new RuntimeException('execute participation insert: ' . $insert->error);
        }
        $written++;
    }
    $insert->close();

    $countStmt = $con->prepare(
        'SELECT COUNT(*) AS n FROM amiga_player_tournament_participation WHERE tournament_id = ?'
    );
    if ($countStmt === false) {
        throw new RuntimeException('prepare participation count: ' . $con->error);
    }
    $countStmt->bind_param('i', $tournamentId);
    if (!$countStmt->execute()) {
        throw new RuntimeException('execute participation count: ' . $countStmt->error);
    }
    $res = $countStmt->get_result();
    $written = 0;
    if ($res) {
        $row = $res->fetch_assoc();
        $written = (int) ($row['n'] ?? 0);
        $res->free();
    }
    $countStmt->close();

    return $written;
}

function amiga_ops_participation_wc_supplement_tournament(mysqli $con, int $tournamentId): int
{
    $nameStmt = $con->prepare('SELECT name FROM tournaments WHERE id = ? LIMIT 1');
    if ($nameStmt === false) {
        throw new RuntimeException('prepare tournament name: ' . $con->error);
    }
    $nameStmt->bind_param('i', $tournamentId);
    if (!$nameStmt->execute()) {
        throw new RuntimeException('execute tournament name: ' . $nameStmt->error);
    }
    $res = $nameStmt->get_result();
    $name = '';
    if ($res) {
        $row = $res->fetch_assoc();
        $name = (string) ($row['name'] ?? '');
        $res->free();
    }
    $nameStmt->close();

    if (!amiga_tournament_is_world_cup(['name' => $name])) {
        return 0;
    }

    $gamesRollup = amiga_ops_participation_player_games_rollup_sql();
    $sql = <<<SQL
INSERT INTO amiga_player_tournament_participation (
    player_id,
    tournament_id,
    event_date,
    event_chrono,
    tournament_name,
    is_cup,
    country,
    has_league,
    has_cup,
    event_finish_position,
    best_knockout_phase,
    event_points,
    games,
    wins,
    draws,
    losses,
    goals_for,
    goals_against,
    rating_before,
    rating_delta,
    rating_after,
    performance_rating,
    games_in_event,
    finalized_at,
    is_winner
)
SELECT
    ep.player_id,
    ep.tournament_id,
    t.event_date,
    t.chrono AS event_chrono,
    t.name AS tournament_name,
    t.is_cup,
    t.country,
    t.has_league,
    t.has_cup,
    NULL AS event_finish_position,
    NULL AS best_knockout_phase,
    (pg.wins * 3 + pg.draws) AS event_points,
    pg.games,
    pg.wins,
    pg.draws,
    pg.losses,
    pg.goals_for,
    pg.goals_against,
    e.rating_before,
    e.rating_delta,
    e.rating_after,
    e.performance_rating,
    COALESCE(e.games_in_event, 0) AS games_in_event,
    e.finalized_at,
    0 AS is_winner
FROM (
    SELECT DISTINCT g.tournament_id, g.player_id
    FROM (
        SELECT tournament_id, player_a_id AS player_id FROM amiga_games
        UNION ALL
        SELECT tournament_id, player_b_id AS player_id FROM amiga_games
    ) g
) ep
INNER JOIN tournaments t ON t.id = ep.tournament_id
INNER JOIN {$gamesRollup}
    ON pg.tournament_id = ep.tournament_id AND pg.player_id = ep.player_id
LEFT JOIN amiga_rating_events e
    ON e.tournament_id = ep.tournament_id AND e.player_id = ep.player_id
LEFT JOIN amiga_tournament_standings gs
    ON gs.tournament_id = ep.tournament_id
   AND gs.player_id = ep.player_id
   AND gs.scope_type = 'league'
   AND gs.scope_key = (
       SELECT MIN(s2.scope_key)
       FROM amiga_tournament_standings s2
       WHERE s2.tournament_id = ep.tournament_id
         AND s2.player_id = ep.player_id
         AND s2.scope_type = 'league'
         AND s2.scope_key <> ''
   )
WHERE t.name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
  AND NOT EXISTS (
      SELECT 1
      FROM amiga_player_tournament_participation p
      WHERE p.tournament_id = ep.tournament_id
        AND p.player_id = ep.player_id
  )
  AND ep.tournament_id = ?
SQL;
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare wc supplement: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute wc supplement: ' . $stmt->error);
    }
    $inserted = $stmt->affected_rows;
    $stmt->close();

    return max(0, $inserted);
}

function amiga_ops_wc_knockout_scope_label(string $scopeKey): string
{
    $parts = explode('|', $scopeKey, 2);

    return trim($parts[0] ?? '');
}

/**
 * @param list<int> $playerIds
 */
function amiga_ops_participation_rebuild_totals_for_players(mysqli $con, array $playerIds): int
{
    $uniqueIds = array_values(array_unique(array_map('intval', $playerIds)));
    sort($uniqueIds, SORT_NUMERIC);
    if ($uniqueIds === []) {
        return 0;
    }

    $placeholders = implode(', ', array_fill(0, count($uniqueIds), '?'));
    $types = str_repeat('i', count($uniqueIds));

    $delete = $con->prepare(
        "DELETE FROM amiga_player_tournament_totals WHERE player_id IN ({$placeholders})"
    );
    if ($delete === false) {
        throw new RuntimeException('prepare totals delete: ' . $con->error);
    }
    $delete->bind_param($types, ...$uniqueIds);
    if (!$delete->execute()) {
        throw new RuntimeException('execute totals delete: ' . $delete->error);
    }
    $delete->close();

    $insertSql = <<<SQL
INSERT INTO amiga_player_tournament_totals (
    player_id,
    tournaments_played,
    tournaments_won,
    event_gold,
    event_silver,
    event_bronze,
    event_podiums,
    wc_played,
    wc_gold,
    wc_silver,
    wc_bronze,
    wc_podiums,
    last_event_date,
    last_tournament_id
)
SELECT
    p.player_id,
    COUNT(*) AS tournaments_played,
    SUM(CASE WHEN p.event_finish_position = 1 THEN 1 ELSE 0 END) AS tournaments_won,
    SUM(CASE WHEN p.event_finish_position = 1 THEN 1 ELSE 0 END) AS event_gold,
    SUM(CASE WHEN p.event_finish_position = 2 THEN 1 ELSE 0 END) AS event_silver,
    SUM(CASE WHEN p.event_finish_position = 3 THEN 1 ELSE 0 END) AS event_bronze,
    SUM(
        CASE
            WHEN p.event_finish_position IS NOT NULL
             AND p.event_finish_position <= 3
            THEN 1 ELSE 0
        END
    ) AS event_podiums,
    SUM(CASE WHEN p.tournament_name REGEXP '^World Cup[[:space:]]+[^[:space:]]' THEN 1 ELSE 0 END) AS wc_played,
    SUM(
        CASE
            WHEN p.tournament_name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
             AND p.event_finish_position = 1
            THEN 1 ELSE 0
        END
    ) AS wc_gold,
    SUM(
        CASE
            WHEN p.tournament_name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
             AND p.event_finish_position = 2
            THEN 1 ELSE 0
        END
    ) AS wc_silver,
    SUM(
        CASE
            WHEN p.tournament_name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
             AND p.event_finish_position = 3
            THEN 1 ELSE 0
        END
    ) AS wc_bronze,
    SUM(
        CASE
            WHEN p.tournament_name REGEXP '^World Cup[[:space:]]+[^[:space:]]'
             AND p.event_finish_position IS NOT NULL
             AND p.event_finish_position <= 3
            THEN 1 ELSE 0
        END
    ) AS wc_podiums,
    MAX(p.event_date) AS last_event_date,
    CAST(
        SUBSTRING_INDEX(
            GROUP_CONCAT(
                p.tournament_id
                ORDER BY p.event_chrono DESC, p.event_date DESC, p.tournament_id DESC
            ),
            ',',
            1
        ) AS UNSIGNED
    ) AS last_tournament_id
FROM amiga_player_tournament_participation p
WHERE p.player_id IN ({$placeholders})
GROUP BY p.player_id
SQL;
    $insert = $con->prepare($insertSql);
    if ($insert === false) {
        throw new RuntimeException('prepare totals insert: ' . $con->error);
    }
    $insert->bind_param($types, ...$uniqueIds);
    if (!$insert->execute()) {
        throw new RuntimeException('execute totals insert: ' . $insert->error);
    }
    $insert->close();

    $cleanup = $con->prepare(
        "DELETE t
         FROM amiga_player_tournament_totals t
         WHERE t.player_id IN ({$placeholders})
           AND NOT EXISTS (
               SELECT 1
               FROM amiga_player_tournament_participation p
               WHERE p.player_id = t.player_id
           )"
    );
    if ($cleanup === false) {
        throw new RuntimeException('prepare totals cleanup: ' . $con->error);
    }
    $cleanup->bind_param($types, ...$uniqueIds);
    if (!$cleanup->execute()) {
        throw new RuntimeException('execute totals cleanup: ' . $cleanup->error);
    }
    $cleanup->close();

    return count($uniqueIds);
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
