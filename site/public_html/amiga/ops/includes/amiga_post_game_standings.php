<?php
/**
 * Incremental tournament standings (parity with scripts/amiga/tournament_standings.py).
 *
 * Per processed game: rebuild standings for that tournament from rated games only.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_phases.php';
require_once dirname(__DIR__, 3) . '/includes/amiga_tournament_lib.php';

const AMIGA_STANDINGS_WIN_POINTS = 3;
const AMIGA_STANDINGS_DRAW_POINTS = 1;
const AMIGA_STANDINGS_LOSS_POINTS = 0;

/**
 * @return array{wins: int, draws: int, losses: int}
 */
function amiga_ops_standings_regulation_delta(int $goalsA, int $goalsB, bool $forPlayerA): array
{
    if ($goalsA > $goalsB) {
        return $forPlayerA
            ? ['wins' => 1, 'draws' => 0, 'losses' => 0]
            : ['wins' => 0, 'draws' => 0, 'losses' => 1];
    }
    if ($goalsA < $goalsB) {
        return $forPlayerA
            ? ['wins' => 0, 'draws' => 0, 'losses' => 1]
            : ['wins' => 1, 'draws' => 0, 'losses' => 0];
    }

    return ['wins' => 0, 'draws' => 1, 'losses' => 0];
}

/**
 * Ground-truth tournament games (open or finalized) for standings rebuild.
 *
 * @return list<array<string, mixed>>
 */
function amiga_ops_standings_load_tournament_games(mysqli $con, int $tournamentId): array
{
    $sql = 'SELECT g.id, g.tournament_id, g.player_a_id, g.player_b_id, '
        . 'g.goals_a, g.goals_b, g.phase, g.extra, g.source_scores_id, g.fixture_id, '
        . 'f.phase_label AS fixture_phase_label, '
        . 's.stage_key, s.name AS stage_name, s.stage_type, s.track_key '
        . 'FROM amiga_games g '
        . 'LEFT JOIN tournament_fixtures f ON f.id = g.fixture_id '
        . 'LEFT JOIN tournament_stages s ON s.id = f.stage_id '
        . 'WHERE g.tournament_id = ? '
        . 'ORDER BY g.source_scores_id ASC, g.id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament games: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament games: ' . $stmt->error);
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
 * @return list<array<string, mixed>>
 */
function amiga_ops_standings_load_rated_tournament_games(mysqli $con, int $tournamentId): array
{
    $sql = 'SELECT g.id, g.tournament_id, g.player_a_id, g.player_b_id, '
        . 'g.goals_a, g.goals_b, g.phase, g.extra, g.source_scores_id, g.fixture_id, '
        . 'f.phase_label AS fixture_phase_label, '
        . 's.stage_key, s.name AS stage_name, s.stage_type, s.track_key '
        . 'FROM amiga_games g '
        . 'INNER JOIN amiga_game_ratings r ON r.game_id = g.id '
        . 'LEFT JOIN tournament_fixtures f ON f.id = g.fixture_id '
        . 'LEFT JOIN tournament_stages s ON s.id = f.stage_id '
        . 'WHERE g.tournament_id = ? '
        . 'ORDER BY g.source_scores_id ASC, g.id ASC';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare rated tournament games: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute rated tournament games: ' . $stmt->error);
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
 * @return array{scope_type: string, scope_key: string, elimination: bool}|null
 */
function amiga_ops_fixture_standings_scope(array $game, int $playerAId, int $playerBId): ?array
{
    if (!isset($game['fixture_id']) || $game['fixture_id'] === null || (int) $game['fixture_id'] <= 0) {
        return null;
    }

    $stageType = strtolower(trim((string) ($game['stage_type'] ?? '')));
    $stageKey = trim((string) ($game['stage_key'] ?? ''));
    $label = trim((string) (
        ($game['fixture_phase_label'] ?? '')
        ?: ($game['stage_name'] ?? '')
        ?: ($game['stage_key'] ?? '')
        ?: 'Fixture'
    ));
    if ($label === '') {
        $label = 'Fixture';
    }

    if ($stageType === 'round_robin') {
        if ($stageKey === '' || strtolower($stageKey) === 'overall') {
            return ['scope_type' => AMIGA_SCOPE_TYPE_LEAGUE, 'scope_key' => '', 'elimination' => false];
        }

        return ['scope_type' => AMIGA_SCOPE_TYPE_LEAGUE, 'scope_key' => $label, 'elimination' => false];
    }
    if ($stageType === 'knockout') {
        return [
            'scope_type' => AMIGA_SCOPE_TYPE_KNOCKOUT,
            'scope_key' => amiga_ops_knockout_pair_scope_key($label, $playerAId, $playerBId),
            'elimination' => true,
        ];
    }
    // Legacy stage types (pre-migration 023) — keep until all DBs migrated.
    if ($stageType === 'league') {
        if ($stageKey === '' || strtolower($stageKey) === 'overall') {
            return ['scope_type' => AMIGA_SCOPE_TYPE_LEAGUE, 'scope_key' => '', 'elimination' => false];
        }

        return ['scope_type' => AMIGA_SCOPE_TYPE_LEAGUE, 'scope_key' => $label, 'elimination' => false];
    }
    if ($stageType === 'group') {
        return ['scope_type' => AMIGA_SCOPE_TYPE_LEAGUE, 'scope_key' => $label, 'elimination' => false];
    }
    if ($stageType === 'placement') {
        return [
            'scope_type' => AMIGA_SCOPE_TYPE_KNOCKOUT,
            'scope_key' => amiga_ops_knockout_pair_scope_key($label, $playerAId, $playerBId),
            'elimination' => true,
        ];
    }
    if ($stageType === 'other') {
        return ['scope_type' => AMIGA_SCOPE_TYPE_LEAGUE, 'scope_key' => $label, 'elimination' => false];
    }

    return null;
}

/**
 * @param array<int, array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}> $table
 */
function amiga_ops_standings_apply_game_to_table(
    array &$table,
    int $playerAId,
    int $playerBId,
    int $goalsA,
    int $goalsB
): void {
    if (!isset($table[$playerAId])) {
        $table[$playerAId] = ['games' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'goals_for' => 0, 'goals_against' => 0];
    }
    if (!isset($table[$playerBId])) {
        $table[$playerBId] = ['games' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'goals_for' => 0, 'goals_against' => 0];
    }

    $da = amiga_ops_standings_regulation_delta($goalsA, $goalsB, true);
    $db = amiga_ops_standings_regulation_delta($goalsA, $goalsB, false);

    $table[$playerAId]['games']++;
    $table[$playerBId]['games']++;
    $table[$playerAId]['goals_for'] += $goalsA;
    $table[$playerAId]['goals_against'] += $goalsB;
    $table[$playerBId]['goals_for'] += $goalsB;
    $table[$playerBId]['goals_against'] += $goalsA;
    $table[$playerAId]['wins'] += $da['wins'];
    $table[$playerAId]['draws'] += $da['draws'];
    $table[$playerAId]['losses'] += $da['losses'];
    $table[$playerBId]['wins'] += $db['wins'];
    $table[$playerBId]['draws'] += $db['draws'];
    $table[$playerBId]['losses'] += $db['losses'];
}

/**
 * @param array<int, array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}> $table
 * @return list<array{player_id: int, position: int, games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, points: int}>
 */
function amiga_ops_standings_assign_positions(array $table): array
{
    $items = [];
    foreach ($table as $pid => $st) {
        $points = $st['wins'] * AMIGA_STANDINGS_WIN_POINTS + $st['draws'] * AMIGA_STANDINGS_DRAW_POINTS;
        $items[] = [
            'player_id' => (int) $pid,
            'games' => $st['games'],
            'wins' => $st['wins'],
            'draws' => $st['draws'],
            'losses' => $st['losses'],
            'goals_for' => $st['goals_for'],
            'goals_against' => $st['goals_against'],
            'points' => $points,
            'gd' => $st['goals_for'] - $st['goals_against'],
        ];
    }

    usort(
        $items,
        static function (array $a, array $b): int {
            return [$b['points'], $b['gd'], $b['goals_for'], $b['games']]
                <=> [$a['points'], $a['gd'], $a['goals_for'], $a['games']];
        }
    );

    $out = [];
    $pos = 0;
    $prevKey = null;
    foreach ($items as $rankIdx => $row) {
        $key = [$row['points'], $row['gd'], $row['goals_for']];
        if ($key !== $prevKey) {
            $pos = $rankIdx + 1;
            $prevKey = $key;
        }
        $out[] = [
            'player_id' => $row['player_id'],
            'position' => $pos,
            'games' => $row['games'],
            'wins' => $row['wins'],
            'draws' => $row['draws'],
            'losses' => $row['losses'],
            'goals_for' => $row['goals_for'],
            'goals_against' => $row['goals_against'],
            'points' => $row['points'],
        ];
    }

    return $out;
}

/**
 * @param array<int, array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}> $table
 * @param list<array<string, mixed>> $games
 * @return list<array{player_id: int, position: int, games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, points: int}>
 */
function amiga_ops_standings_knockout_positions(array $table, array $games): array
{
    if (count($table) !== 2) {
        return amiga_ops_standings_assign_positions($table);
    }

    $ids = array_keys($table);
    sort($ids, SORT_NUMERIC);
    $id1 = (int) $ids[0];
    $id2 = (int) $ids[1];
    $s1 = $table[$id1];
    $s2 = $table[$id2];
    $gd1 = $s1['goals_for'] - $s1['goals_against'];
    $gd2 = $s2['goals_for'] - $s2['goals_against'];

    $winnerId = null;
    if ($gd1 > $gd2) {
        $winnerId = $id1;
    } elseif ($gd2 > $gd1) {
        $winnerId = $id2;
    } elseif ($s1['goals_for'] > $s2['goals_for']) {
        $winnerId = $id1;
    } elseif ($s2['goals_for'] > $s1['goals_for']) {
        $winnerId = $id2;
    } else {
        foreach ($games as $g) {
            $extra = isset($g['extra']) ? (string) $g['extra'] : '';
            if (trim($extra) === '') {
                continue;
            }
            $wid = amiga_parse_standings_winner(
                (int) $g['goals_a'],
                (int) $g['goals_b'],
                $extra,
                (int) $g['player_a_id'],
                (int) $g['player_b_id']
            );
            if ($wid !== null) {
                $winnerId = $wid;
                break;
            }
        }
    }

    if ($winnerId === null) {
        return amiga_ops_standings_assign_positions($table);
    }

    $loserId = $winnerId === $id1 ? $id2 : $id1;

    return [
        amiga_ops_standings_row_with_position($winnerId, $table[$winnerId], 1),
        amiga_ops_standings_row_with_position($loserId, $table[$loserId], 2),
    ];
}

/**
 * @param array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int} $st
 * @return array{player_id: int, position: int, games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, points: int}
 */
function amiga_ops_standings_row_with_position(int $playerId, array $st, int $position): array
{
    return [
        'player_id' => $playerId,
        'position' => $position,
        'games' => $st['games'],
        'wins' => $st['wins'],
        'draws' => $st['draws'],
        'losses' => $st['losses'],
        'goals_for' => $st['goals_for'],
        'goals_against' => $st['goals_against'],
        'points' => $st['wins'] * AMIGA_STANDINGS_WIN_POINTS + $st['draws'] * AMIGA_STANDINGS_DRAW_POINTS,
    ];
}

/**
 * @param list<array<string, mixed>> $games
 * @return list<array<string, mixed>>
 */
function amiga_ops_compute_tournament_standings(array $games): array
{
    if ($games === []) {
        return [];
    }

    /** @var array<string, array<int, array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}>> $scopes */
    $scopes = [];
    /** @var array<string, array<int, array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int}>> $knockoutScopes */
    $knockoutScopes = [];
    /** @var array<string, list<array<string, mixed>>> $knockoutGames */
    $knockoutGames = [];
    $hasNullPhase = false;
    $hasStructured = false;

    foreach ($games as $g) {
        $phase = $g['phase'] ?? null;
        if ($phase === null || trim((string) $phase) === '') {
            $hasNullPhase = true;
        } else {
            $hasStructured = true;
        }

        $playerAId = (int) $g['player_a_id'];
        $playerBId = (int) $g['player_b_id'];
        $goalsA = (int) $g['goals_a'];
        $goalsB = (int) $g['goals_b'];

        $fixtureScope = amiga_ops_fixture_standings_scope($g, $playerAId, $playerBId);
        if ($fixtureScope !== null) {
            $hasStructured = true;
            $scopeKey = $fixtureScope['scope_type'] . "\0" . $fixtureScope['scope_key'];
            if ($fixtureScope['elimination']) {
                if (!isset($knockoutScopes[$scopeKey])) {
                    $knockoutScopes[$scopeKey] = [];
                }
                if (!isset($knockoutGames[$scopeKey])) {
                    $knockoutGames[$scopeKey] = [];
                }
                $knockoutGames[$scopeKey][] = $g;
                amiga_ops_standings_apply_game_to_table($knockoutScopes[$scopeKey], $playerAId, $playerBId, $goalsA, $goalsB);
            } else {
                if (!isset($scopes[$scopeKey])) {
                    $scopes[$scopeKey] = [];
                }
                amiga_ops_standings_apply_game_to_table($scopes[$scopeKey], $playerAId, $playerBId, $goalsA, $goalsB);
            }
            continue;
        }

        if (amiga_ops_is_knockout_phase($phase !== null ? (string) $phase : null)) {
            $phaseStr = amiga_ops_normalize_whitespace((string) $phase);
            $pairKey = amiga_ops_knockout_pair_scope_key($phaseStr, $playerAId, $playerBId);
            $scopeKey = AMIGA_SCOPE_TYPE_KNOCKOUT . "\0" . $pairKey;
            if (!isset($knockoutScopes[$scopeKey])) {
                $knockoutScopes[$scopeKey] = [];
            }
            if (!isset($knockoutGames[$scopeKey])) {
                $knockoutGames[$scopeKey] = [];
            }
            $knockoutGames[$scopeKey][] = $g;
            amiga_ops_standings_apply_game_to_table($knockoutScopes[$scopeKey], $playerAId, $playerBId, $goalsA, $goalsB);
            continue;
        }

        $scope = amiga_ops_parse_phase($phase !== null ? (string) $phase : null);
        if (!amiga_ops_is_league_scope($scope)) {
            continue;
        }
        $scopeKey = $scope['scope_type'] . "\0" . $scope['scope_key'];
        if (!isset($scopes[$scopeKey])) {
            $scopes[$scopeKey] = [];
        }
        amiga_ops_standings_apply_game_to_table($scopes[$scopeKey], $playerAId, $playerBId, $goalsA, $goalsB);
    }

    if ($hasNullPhase && !$hasStructured) {
        $filtered = [];
        $leagueKey = AMIGA_SCOPE_TYPE_LEAGUE . "\0";
        if (isset($scopes[$leagueKey])) {
            $filtered[$leagueKey] = $scopes[$leagueKey];
        }
        $scopes = $filtered;
    } elseif ($hasNullPhase && $hasStructured) {
        $leagueAggregate = [];
        foreach ($scopes as $key => $table) {
            [$stype, $skey] = explode("\0", $key, 2);
            if ($stype === AMIGA_SCOPE_TYPE_LEAGUE && $skey === '') {
                continue;
            }
            if ($stype !== AMIGA_SCOPE_TYPE_LEAGUE) {
                continue;
            }
            foreach ($table as $pid => $st) {
                if (!isset($leagueAggregate[$pid])) {
                    $leagueAggregate[$pid] = ['games' => 0, 'wins' => 0, 'draws' => 0, 'losses' => 0, 'goals_for' => 0, 'goals_against' => 0];
                }
                $leagueAggregate[$pid]['games'] += $st['games'];
                $leagueAggregate[$pid]['wins'] += $st['wins'];
                $leagueAggregate[$pid]['draws'] += $st['draws'];
                $leagueAggregate[$pid]['losses'] += $st['losses'];
                $leagueAggregate[$pid]['goals_for'] += $st['goals_for'];
                $leagueAggregate[$pid]['goals_against'] += $st['goals_against'];
            }
        }
        if ($leagueAggregate !== []) {
            $scopes[AMIGA_SCOPE_TYPE_LEAGUE . "\0"] = $leagueAggregate;
        }
    }

    foreach ($knockoutScopes as $key => $table) {
        $scopes[$key] = $table;
    }

    $tournamentId = (int) $games[0]['tournament_id'];
    $rows = [];
    ksort($scopes);
    foreach ($scopes as $key => $table) {
        if ($table === []) {
            continue;
        }
        [$stype, $skey] = explode("\0", $key, 2);
        if ($stype === AMIGA_SCOPE_TYPE_KNOCKOUT) {
            $ranked = amiga_ops_standings_knockout_positions($table, $knockoutGames[$key] ?? []);
        } else {
            $ranked = amiga_ops_standings_assign_positions($table);
        }
        foreach ($ranked as $r) {
            $rows[] = [
                'tournament_id' => $tournamentId,
                'player_id' => $r['player_id'],
                'scope_type' => $stype,
                'scope_key' => $skey,
                'position' => $r['position'],
                'games' => $r['games'],
                'wins' => $r['wins'],
                'draws' => $r['draws'],
                'losses' => $r['losses'],
                'goals_for' => $r['goals_for'],
                'goals_against' => $r['goals_against'],
                'points' => $r['points'],
            ];
        }
    }

    return $rows;
}

/**
 * Replace all standings rows for one tournament (rated games only).
 *
 * @param list<array<string, mixed>> $rows
 */
/**
 * Upsert one tournament row in amiga_tournament_catalog_stats (index page aggregates).
 */
function amiga_ops_catalog_stats_refresh_tournament(mysqli $con, int $tournamentId): void
{
    if ($tournamentId < 1) {
        return;
    }

    $sql = 'INSERT INTO amiga_tournament_catalog_stats (
                tournament_id, game_count, standing_players, standing_rows, league_scopes, knockout_ties
            )
            SELECT
                ?,
                (SELECT COUNT(*) FROM amiga_games WHERE tournament_id = ?),
                COALESCE((
                    SELECT COUNT(DISTINCT player_id) FROM amiga_tournament_standings WHERE tournament_id = ?
                ), 0),
                COALESCE((SELECT COUNT(*) FROM amiga_tournament_standings WHERE tournament_id = ?), 0),
                COALESCE((
                    SELECT COUNT(DISTINCT scope_key) FROM amiga_tournament_standings
                    WHERE tournament_id = ? AND scope_type = \'league\' AND scope_key <> \'\'
                ), 0),
                COALESCE((
                    SELECT COUNT(DISTINCT scope_key) FROM amiga_tournament_standings
                    WHERE tournament_id = ? AND scope_type = \'knockout\'
                ), 0)
            ON DUPLICATE KEY UPDATE
                game_count = VALUES(game_count),
                standing_players = VALUES(standing_players),
                standing_rows = VALUES(standing_rows),
                league_scopes = VALUES(league_scopes),
                knockout_ties = VALUES(knockout_ties)';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare catalog stats refresh: ' . $con->error);
    }
    $stmt->bind_param('iiiiii', $tournamentId, $tournamentId, $tournamentId, $tournamentId, $tournamentId, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute catalog stats refresh: ' . $stmt->error);
    }
    $stmt->close();
}

function amiga_ops_standings_replace_tournament(mysqli $con, int $tournamentId, array $rows): void
{
    $stmt = $con->prepare('DELETE FROM amiga_tournament_standings WHERE tournament_id = ?');
    if ($stmt === false) {
        throw new RuntimeException('prepare standings delete: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute standings delete: ' . $stmt->error);
    }
    $stmt->close();

    if ($rows === []) {
        return;
    }

    $sql = 'INSERT INTO amiga_tournament_standings ('
        . 'tournament_id, player_id, scope_type, scope_key, position, '
        . 'games, wins, draws, losses, goals_for, goals_against, points'
        . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare standings insert: ' . $con->error);
    }

    foreach ($rows as $row) {
        $tid = (int) $row['tournament_id'];
        $pid = (int) $row['player_id'];
        $stype = (string) $row['scope_type'];
        $skey = (string) $row['scope_key'];
        $pos = (int) $row['position'];
        $games = (int) $row['games'];
        $wins = (int) $row['wins'];
        $draws = (int) $row['draws'];
        $losses = (int) $row['losses'];
        $gf = (int) $row['goals_for'];
        $ga = (int) $row['goals_against'];
        $pts = (int) $row['points'];
        $stmt->bind_param(
            'iissiiiiiiii',
            $tid,
            $pid,
            $stype,
            $skey,
            $pos,
            $games,
            $wins,
            $draws,
            $losses,
            $gf,
            $ga,
            $pts
        );
        if (!$stmt->execute()) {
            throw new RuntimeException(
                'execute standings insert tournament=' . $tournamentId . ': ' . $stmt->error
            );
        }
    }
    $stmt->close();
}

/**
 * @param array<string, mixed> $game must include tournament_id (null skips)
 */
function amiga_ops_standings_apply_game(mysqli $con, array $game): void
{
    $tournamentId = isset($game['tournament_id']) ? (int) $game['tournament_id'] : 0;
    if ($tournamentId <= 0) {
        return;
    }

    $tournamentGames = amiga_ops_standings_load_tournament_games($con, $tournamentId);
    $rows = amiga_ops_compute_tournament_standings($tournamentGames);
    amiga_ops_standings_replace_tournament($con, $tournamentId, $rows);
    amiga_ops_catalog_stats_refresh_tournament($con, $tournamentId);
}
