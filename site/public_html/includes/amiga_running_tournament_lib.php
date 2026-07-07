<?php
declare(strict_types=1);

/**
 * Running-tournament broadcast helpers (RTB Lane B).
 * Scores live on tournament_fixtures until Make official promotes to amiga_games.
 */

function amiga_running_tournament_is_live_ops_generated(array $row): bool
{
    if (($row['source_id'] ?? null) !== null) {
        return false;
    }
    $overrides = (string) ($row['format_overrides'] ?? '');
    return str_contains($overrides, 'tournament_builder')
        || str_contains($overrides, 'fixtures');
}

function amiga_running_tournament_broadcast_mode(mysqli $con, int $tournamentId): bool
{
    static $cache = [];
    if ($tournamentId < 1) {
        return false;
    }
    if (array_key_exists($tournamentId, $cache)) {
        return $cache[$tournamentId];
    }
    $stmt = mysqli_prepare(
        $con,
        'SELECT source_id, format_overrides, rating_finalized FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $tournamentId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    if ($row === null) {
        $cache[$tournamentId] = false;
        return false;
    }
    $broadcast = amiga_running_tournament_is_live_ops_generated([
        'source_id' => $row['source_id'] !== null ? (int) $row['source_id'] : null,
        'format_overrides' => $row['format_overrides'],
    ]) && (int) ($row['rating_finalized'] ?? 0) === 0;
    $cache[$tournamentId] = $broadcast;

    return $broadcast;
}

function amiga_running_tournament_count_played_fixtures(mysqli $con, int $tournamentId): int
{
    $stmt = mysqli_prepare(
        $con,
        'SELECT COUNT(*) AS n FROM tournament_fixtures f '
        . 'INNER JOIN tournament_stages s ON s.id = f.stage_id '
        . 'WHERE s.tournament_id = ? AND f.status = ?'
    );
    if ($stmt === false) {
        return 0;
    }
    $played = 'played';
    mysqli_stmt_bind_param($stmt, 'is', $tournamentId, $played);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    if ($res) {
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return (int) ($row['n'] ?? 0);
}

/**
 * @return list<array<string, mixed>>
 */
function amiga_running_tournament_games(mysqli $con, int $tournamentId): array
{
    $stmt = mysqli_prepare(
        $con,
        'SELECT f.id AS fixture_id, f.player_a_id, f.player_b_id, f.goals_a, f.goals_b, '
        . 'f.extra, f.phase_label AS phase, f.leg_no, s.tournament_id '
        . 'FROM tournament_fixtures f '
        . 'INNER JOIN tournament_stages s ON s.id = f.stage_id '
        . 'WHERE s.tournament_id = ? AND f.status = ? '
        . 'AND f.player_a_id IS NOT NULL AND f.player_b_id IS NOT NULL '
        . 'AND f.goals_a IS NOT NULL AND f.goals_b IS NOT NULL '
        . 'ORDER BY s.sequence_no ASC, s.id ASC, f.leg_no ASC, f.id ASC'
    );
    if ($stmt === false) {
        return [];
    }
    $played = 'played';
    mysqli_stmt_bind_param($stmt, 'is', $tournamentId, $played);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $games = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $games[] = [
                'tournament_id' => (int) $row['tournament_id'],
                'fixture_id' => (int) $row['fixture_id'],
                'player_a_id' => (int) $row['player_a_id'],
                'player_b_id' => (int) $row['player_b_id'],
                'goals_a' => (int) $row['goals_a'],
                'goals_b' => (int) $row['goals_b'],
                'extra' => $row['extra'],
                'phase' => $row['phase'],
                'leg_no' => (int) $row['leg_no'],
            ];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $games;
}

/**
 * Broadcast league standings rows (not persisted).
 *
 * @return list<array{position:int,games:int,wins:int,draws:int,losses:int,goals_for:int,goals_against:int,points:int,player_id:int,player_name:string}>
 */
function amiga_running_tournament_standings_rows(mysqli $con, int $tournamentId): array
{
    require_once __DIR__ . '/../amiga/ops/includes/amiga_post_game_standings.php';

    $games = amiga_running_tournament_games($con, $tournamentId);
    if ($games === []) {
        return [];
    }
    $computed = amiga_ops_compute_tournament_standings($games);
    $leagueRows = [];
    foreach ($computed as $row) {
        if (($row['scope_type'] ?? '') !== 'league' || (string) ($row['scope_key'] ?? '') !== '') {
            continue;
        }
        $leagueRows[] = $row;
    }
    if ($leagueRows === []) {
        return [];
    }

    $playerIds = array_values(array_unique(array_map(static fn(array $r): int => (int) $r['player_id'], $leagueRows)));
    $names = [];
    if ($playerIds !== []) {
        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        $types = str_repeat('i', count($playerIds));
        $stmt = mysqli_prepare($con, "SELECT id, name FROM amiga_players WHERE id IN ({$placeholders})");
        if ($stmt !== false) {
            mysqli_stmt_bind_param($stmt, $types, ...$playerIds);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res) {
                while ($nameRow = mysqli_fetch_assoc($res)) {
                    $names[(int) $nameRow['id']] = (string) $nameRow['name'];
                }
                mysqli_free_result($res);
            }
            mysqli_stmt_close($stmt);
        }
    }

    $rows = [];
    foreach ($leagueRows as $row) {
        $pid = (int) $row['player_id'];
        $rows[] = [
            'position' => (int) $row['position'],
            'games' => (int) $row['games'],
            'wins' => (int) $row['wins'],
            'draws' => (int) $row['draws'],
            'losses' => (int) $row['losses'],
            'goals_for' => (int) $row['goals_for'],
            'goals_against' => (int) $row['goals_against'],
            'points' => (int) $row['points'],
            'player_id' => $pid,
            'player_name' => $names[$pid] ?? ('Player #' . $pid),
        ];
    }

    return $rows;
}

function amiga_running_tournament_fixture_has_result(array $fixture): bool
{
    if (($fixture['status'] ?? '') === 'played') {
        return true;
    }

    return ($fixture['goals_a'] ?? null) !== null && ($fixture['goals_b'] ?? null) !== null;
}