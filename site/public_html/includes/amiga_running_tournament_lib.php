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
        . 'f.extra, f.goals_et_a, f.goals_et_b, f.pens_a, f.pens_b, '
        . 'f.phase_label AS phase, f.phase_label AS fixture_phase_label, '
        . 'f.leg_no, s.id AS stage_id, s.tournament_id, s.stage_key, s.name AS stage_name, s.stage_type, s.track_key '
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
                'goals_et_a' => $row['goals_et_a'] !== null ? (int) $row['goals_et_a'] : null,
                'goals_et_b' => $row['goals_et_b'] !== null ? (int) $row['goals_et_b'] : null,
                'pens_a' => $row['pens_a'] !== null ? (int) $row['pens_a'] : null,
                'pens_b' => $row['pens_b'] !== null ? (int) $row['pens_b'] : null,
                'phase' => $row['phase'],
                'fixture_phase_label' => $row['fixture_phase_label'],
                'leg_no' => (int) $row['leg_no'],
                'stage_id' => (int) $row['stage_id'],
                'stage_key' => $row['stage_key'],
                'stage_name' => $row['stage_name'],
                'stage_type' => $row['stage_type'],
                'track_key' => $row['track_key'],
            ];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $games;
}

/**
 * Contract-driven standings rows from played fixtures (not persisted — RTB Lane B).
 *
 * @return list<array<string, mixed>>
 */
function amiga_running_tournament_compute_standings(mysqli $con, int $tournamentId): array
{
    static $cache = [];
    if (isset($cache[$tournamentId])) {
        return $cache[$tournamentId];
    }

    require_once __DIR__ . '/../amiga/ops/includes/amiga_post_game_standings.php';

    $games = amiga_running_tournament_games($con, $tournamentId);
    if ($games === []) {
        $cache[$tournamentId] = [];

        return [];
    }
    $scoringContext = amiga_scoring_load_context_for_tournament($con, $tournamentId);
    $cache[$tournamentId] = amiga_ops_compute_tournament_standings($games, $scoringContext);

    return $cache[$tournamentId];
}

/**
 * @param list<array<string, mixed>> $computed
 * @return list<int>
 */
function amiga_running_tournament_enrich_player_ids(array $computed): array
{
    return array_values(array_unique(array_map(
        static fn (array $row): int => (int) $row['player_id'],
        $computed
    )));
}

/**
 * @param list<int> $playerIds
 * @return array<int, array{name: string, country: string}>
 */
function amiga_running_tournament_player_names(mysqli $con, array $playerIds): array
{
    $names = [];
    if ($playerIds === []) {
        return $names;
    }
    $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
    $types = str_repeat('i', count($playerIds));
    $stmt = mysqli_prepare($con, "SELECT id, name, country FROM amiga_players WHERE id IN ({$placeholders})");
    if ($stmt === false) {
        return $names;
    }
    mysqli_stmt_bind_param($stmt, $types, ...$playerIds);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($nameRow = mysqli_fetch_assoc($res)) {
            $names[(int) $nameRow['id']] = [
                'name' => (string) $nameRow['name'],
                'country' => $nameRow['country'] !== null ? (string) $nameRow['country'] : '',
            ];
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);

    return $names;
}

/**
 * Broadcast standings for one scope (not persisted).
 *
 * @return list<array{position:int,games:int,wins:int,draws:int,losses:int,goals_for:int,goals_against:int,points:int,player_id:int,player_name:string,country:string}>
 */
function amiga_running_tournament_standings_scope_rows(
    mysqli $con,
    int $tournamentId,
    string $scopeType,
    string $scopeKey
): array {
    $computed = amiga_running_tournament_compute_standings($con, $tournamentId);
    if ($computed === []) {
        return [];
    }
    $scopeRows = array_values(array_filter(
        $computed,
        static fn (array $row): bool => ($row['scope_type'] ?? '') === $scopeType
            && (string) ($row['scope_key'] ?? '') === $scopeKey
    ));
    if ($scopeRows === []) {
        return [];
    }

    $names = amiga_running_tournament_player_names(
        $con,
        amiga_running_tournament_enrich_player_ids($scopeRows)
    );

    $rows = [];
    foreach ($scopeRows as $row) {
        $pid = (int) $row['player_id'];
        $playerMeta = $names[$pid] ?? null;
        $rows[] = [
            'position' => (int) $row['position'],
            'games' => (int) $row['games'],
            'wins' => (int) $row['wins'],
            'draws' => (int) $row['draws'],
            'losses' => (int) $row['losses'],
            'goals_for' => (int) $row['goals_for'],
            'goals_against' => (int) $row['goals_against'],
            'points' => (int) $row['points'],
            'stage_id' => isset($row['stage_id']) && $row['stage_id'] !== null ? (int) $row['stage_id'] : null,
            'player_id' => $pid,
            'player_name' => is_array($playerMeta) ? $playerMeta['name'] : ('Player #' . $pid),
            'country' => is_array($playerMeta) ? $playerMeta['country'] : '',
        ];
    }

    return $rows;
}

/**
 * @return list<string>
 */
function amiga_running_tournament_list_scopes(mysqli $con, int $tournamentId, string $scopeType): array
{
    $computed = amiga_running_tournament_compute_standings($con, $tournamentId);
    $keys = [];
    foreach ($computed as $row) {
        if (($row['scope_type'] ?? '') !== $scopeType) {
            continue;
        }
        $key = (string) ($row['scope_key'] ?? '');
        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
        }
    }
    sort($keys, SORT_STRING);

    return $keys;
}

/**
 * Played fixture legs for a knockout scope while running (no amiga_games).
 *
 * @return list<array<string, mixed>>
 */
function amiga_running_tournament_knockout_fixture_games(
    mysqli $con,
    int $tournamentId,
    string $scopeKey
): array {
    require_once __DIR__ . '/../amiga/ops/includes/amiga_tournament_phases.php';

    $parsed = amiga_tournament_parse_knockout_scope_key($scopeKey);
    if ($parsed === null) {
        return [];
    }
    $phase = $parsed['phase'];
    $lo = $parsed['player_lo'];
    $hi = $parsed['player_hi'];
    $games = amiga_running_tournament_games($con, $tournamentId);
    $rows = [];
    foreach ($games as $g) {
        $playerAId = (int) $g['player_a_id'];
        $playerBId = (int) $g['player_b_id'];
        $ids = [$playerAId, $playerBId];
        sort($ids, SORT_NUMERIC);
        if ($ids !== [$lo, $hi]) {
            continue;
        }
        $label = trim((string) (
            ($g['fixture_phase_label'] ?? '')
            ?: ($g['stage_name'] ?? '')
            ?: ($g['stage_key'] ?? '')
            ?: 'Fixture'
        ));
        if (strtolower($g['stage_type'] ?? '') !== 'knockout' && !amiga_ops_is_knockout_phase($label)) {
            continue;
        }
        $pairKey = amiga_ops_knockout_pair_scope_key($label, $playerAId, $playerBId);
        if ($pairKey !== $scopeKey) {
            continue;
        }
        $rows[] = [
            'id' => (int) ($g['fixture_id'] ?? 0),
            'source_scores_id' => (int) ($g['fixture_id'] ?? 0),
            'game_date' => null,
            'player_a_id' => $playerAId,
            'player_b_id' => $playerBId,
            'goals_a' => (int) $g['goals_a'],
            'goals_b' => (int) $g['goals_b'],
            'extra' => $g['extra'] ?? null,
            'phase' => $label,
            'player_a_name' => null,
            'player_b_name' => null,
        ];
    }

    usort(
        $rows,
        static function (array $a, array $b): int {
            return [$a['source_scores_id'], $a['id']] <=> [$b['source_scores_id'], $b['id']];
        }
    );

    return $rows;
}

/**
 * Merge broadcast/official league standings with full roster (zero-game players at tied rank).
 *
 * @param list<array{position:int,games:int,wins:int,draws:int,losses:int,goals_for:int,goals_against:int,points:int,player_id:int,player_name:string,country?:string}> $standingsRows
 * @param list<array{player_id:int,player_name:string,country?:string|null}> $roster
 * @return array{
 *   rows:list<array{position:int,games:int,wins:int,draws:int,losses:int,goals_for:int,goals_against:int,points:int,player_id:int,player_name:string,country:string}>,
 *   is_preview:bool,
 *   preview_note:?string
 * }
 */
function amiga_running_tournament_merged_league_table_rows(array $standingsRows, array $roster): array
{
    if ($roster === []) {
        return [
            'rows' => [],
            'is_preview' => false,
            'preview_note' => null,
        ];
    }

    if ($standingsRows === []) {
        $rows = [];
        foreach ($roster as $idx => $entrant) {
            $rows[] = [
                'position' => $idx + 1,
                'games' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'points' => 0,
                'player_id' => (int) $entrant['player_id'],
                'player_name' => (string) $entrant['player_name'],
                'country' => trim((string) ($entrant['country'] ?? '')),
            ];
        }

        return [
            'rows' => $rows,
            'is_preview' => true,
            'preview_note' => 'No results yet — showing entrants at zero.',
        ];
    }

    /** @var array<int, array<string, mixed>> $standingsByPlayer */
    $standingsByPlayer = [];
    $maxPosition = 0;
    foreach ($standingsRows as $row) {
        $playerId = (int) $row['player_id'];
        $standingsByPlayer[$playerId] = $row;
        $maxPosition = max($maxPosition, (int) $row['position']);
    }
    $zeroGamePosition = $maxPosition > 0 ? $maxPosition + 1 : count($roster);

    $rows = [];
    foreach ($roster as $entrant) {
        $playerId = (int) $entrant['player_id'];
        if (isset($standingsByPlayer[$playerId])) {
            $standing = $standingsByPlayer[$playerId];
            $rows[] = [
                'position' => (int) $standing['position'],
                'games' => (int) $standing['games'],
                'wins' => (int) $standing['wins'],
                'draws' => (int) $standing['draws'],
                'losses' => (int) $standing['losses'],
                'goals_for' => (int) $standing['goals_for'],
                'goals_against' => (int) $standing['goals_against'],
                'points' => (int) $standing['points'],
                'player_id' => $playerId,
                'player_name' => (string) $standing['player_name'],
                'country' => trim((string) ($standing['country'] ?? $entrant['country'] ?? '')),
            ];
            continue;
        }
        $rows[] = [
            'position' => $zeroGamePosition,
            'games' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'points' => 0,
            'player_id' => $playerId,
            'player_name' => (string) $entrant['player_name'],
            'country' => trim((string) ($entrant['country'] ?? '')),
        ];
    }

    usort(
        $rows,
        static function (array $a, array $b): int {
            $posCmp = (int) $a['position'] <=> (int) $b['position'];
            if ($posCmp !== 0) {
                return $posCmp;
            }
            $pointsCmp = (int) $b['points'] <=> (int) $a['points'];
            if ($pointsCmp !== 0) {
                return $pointsCmp;
            }
            $gdA = (int) $a['goals_for'] - (int) $a['goals_against'];
            $gdB = (int) $b['goals_for'] - (int) $b['goals_against'];
            if ($gdA !== $gdB) {
                return $gdB <=> $gdA;
            }
            return strnatcasecmp((string) $a['player_name'], (string) $b['player_name']);
        }
    );

    return [
        'rows' => $rows,
        'is_preview' => false,
        'preview_note' => null,
    ];
}

/**
 * Live hub league table rows for running broadcast tournaments.
 *
 * @return array{rows:list<array<string,mixed>>,is_preview:bool,preview_note:?string}|null
 */
function amiga_live_tournament_league_table_rows(mysqli $con, int $tournamentId): ?array
{
    if (!amiga_running_tournament_broadcast_mode($con, $tournamentId)) {
        return null;
    }

    $participants = amiga_live_tournament_participants($con, $tournamentId);
    if ($participants === []) {
        return null;
    }

    $roster = [];
    foreach ($participants as $participant) {
        $roster[] = [
            'player_id' => (int) $participant['player_id'],
            'player_name' => (string) $participant['player_name'],
            'country' => $participant['country'] ?? '',
        ];
    }

    $standingsRows = amiga_running_tournament_standings_rows($con, $tournamentId);

    return amiga_running_tournament_merged_league_table_rows($standingsRows, $roster);
}

/**
 * Broadcast league standings rows (not persisted).
 *
 * @return list<array{position:int,games:int,wins:int,draws:int,losses:int,goals_for:int,goals_against:int,points:int,player_id:int,player_name:string,country:string}>
 */
function amiga_running_tournament_standings_rows(mysqli $con, int $tournamentId): array
{
    return amiga_running_tournament_standings_scope_rows($con, $tournamentId, 'league', '');
}

function amiga_running_tournament_fixture_has_result(array $fixture): bool
{
    if (($fixture['status'] ?? '') === 'played') {
        return true;
    }

    return ($fixture['goals_a'] ?? null) !== null && ($fixture['goals_b'] ?? null) !== null;
}