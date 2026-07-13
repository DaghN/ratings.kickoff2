<?php
/**
 * Incremental tournament standings (parity with scripts/amiga/tournament_standings.py).
 *
 * Per processed game: rebuild standings for that tournament from rated games only.
 */
declare(strict_types=1);

require_once __DIR__ . '/amiga_tournament_phases.php';
require_once dirname(__DIR__, 3) . '/includes/amiga_tournament_lib.php';
require_once dirname(__DIR__, 3) . '/includes/amiga_match_extensions.php';
require_once dirname(__DIR__, 3) . '/includes/amiga_scoring_contract.php';

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
        . 'g.goals_a, g.goals_b, g.phase, g.extra, '
        . 'g.goals_et_a, g.goals_et_b, g.pens_a, g.pens_b, '
        . 'g.source_scores_id, g.fixture_id, '
        . 'f.phase_label AS fixture_phase_label, '
        . 's.id AS stage_id, s.stage_key, s.name AS stage_name, s.stage_type, s.track_key '
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
        . 'g.goals_a, g.goals_b, g.phase, g.extra, '
        . 'g.goals_et_a, g.goals_et_b, g.pens_a, g.pens_b, '
        . 'g.source_scores_id, g.fixture_id, '
        . 'f.phase_label AS fixture_phase_label, '
        . 's.id AS stage_id, s.stage_key, s.name AS stage_name, s.stage_type, s.track_key '
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

function amiga_ops_game_stage_id(array $game): ?int
{
    if (!isset($game['stage_id']) || $game['stage_id'] === null) {
        return null;
    }
    $stageId = (int) $game['stage_id'];

    return $stageId > 0 ? $stageId : null;
}

/**
 * @param array<string, int|null> $scopeStageIds
 */
function amiga_ops_record_scope_stage_id(array &$scopeStageIds, string $scopeKey, ?int $stageId): void
{
    if ($stageId === null) {
        return;
    }
    if (!array_key_exists($scopeKey, $scopeStageIds) || $scopeStageIds[$scopeKey] === null) {
        $scopeStageIds[$scopeKey] = $stageId;

        return;
    }
    if ($scopeStageIds[$scopeKey] !== $stageId) {
        error_log(
            'amiga_ops_compute_tournament_standings: mixed stage_id for scope '
            . $scopeKey
            . ' (had '
            . (string) $scopeStageIds[$scopeKey]
            . ', got '
            . (string) $stageId
            . ')'
        );
    }
}

/**
 * @param array<int, array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, win_points?: int, draw_points?: int, loss_points?: int}> $table
 * @param array<string, mixed> $contract
 */
function amiga_ops_standings_apply_game_to_table(
    array &$table,
    int $playerAId,
    int $playerBId,
    int $goalsA,
    int $goalsB,
    array $contract
): void {
    if (!isset($table[$playerAId])) {
        $table[$playerAId] = [
            'games' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'win_points' => (int) $contract['win_points'],
            'draw_points' => (int) $contract['draw_points'],
            'loss_points' => (int) $contract['loss_points'],
        ];
    }
    if (!isset($table[$playerBId])) {
        $table[$playerBId] = [
            'games' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'win_points' => (int) $contract['win_points'],
            'draw_points' => (int) $contract['draw_points'],
            'loss_points' => (int) $contract['loss_points'],
        ];
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
 * @param array<string, mixed> $scopeContracts
 * @param array<string, mixed> $contract
 */
function amiga_ops_standings_record_scope_contract(array &$scopeContracts, string $scopeKey, array $contract): void
{
    if (!isset($scopeContracts[$scopeKey])) {
        $scopeContracts[$scopeKey] = $contract;

        return;
    }
    $existing = $scopeContracts[$scopeKey];
    if (
        ($existing['primitive'] ?? '') !== ($contract['primitive'] ?? '')
        || ($existing['steps'] ?? []) !== ($contract['steps'] ?? [])
        || (int) ($existing['win_points'] ?? 0) !== (int) ($contract['win_points'] ?? 0)
    ) {
        error_log(
            'amiga_ops_compute_tournament_standings: mixed scoring contracts for scope '
            . $scopeKey
        );
    }
}

/**
 * @param list<int> $playerIds
 * @param list<array<string, mixed>> $games
 * @param array<string, mixed> $contract
 * @return array<int, array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, win_points?: int, draw_points?: int, loss_points?: int}>
 */
function amiga_ops_standings_mutual_mini_table(array $playerIds, array $games, array $contract): array
{
    $playerSet = array_fill_keys($playerIds, true);
    $mini = [];
    foreach ($playerIds as $pid) {
        $mini[$pid] = [
            'games' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'win_points' => (int) $contract['win_points'],
            'draw_points' => (int) $contract['draw_points'],
            'loss_points' => (int) $contract['loss_points'],
        ];
    }
    foreach ($games as $g) {
        $playerAId = (int) $g['player_a_id'];
        $playerBId = (int) $g['player_b_id'];
        if (!isset($playerSet[$playerAId]) || !isset($playerSet[$playerBId])) {
            continue;
        }
        amiga_ops_standings_apply_game_to_table(
            $mini,
            $playerAId,
            $playerBId,
            (int) $g['goals_a'],
            (int) $g['goals_b'],
            $contract
        );
    }

    return $mini;
}

/**
 * @param array<int, array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, win_points?: int, draw_points?: int}> $table
 * @param array<string, mixed> $contract
 * @param list<array<string, mixed>> $games
 * @return list<array{player_id: int, position: int, games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, points: int}>
 */
function amiga_ops_standings_assign_positions(array $table, array $contract, array $games = []): array
{
    $useH2h = in_array('head_to_head', $contract['steps'] ?? [], true) && $games !== [];

    if ($useH2h) {
        $items = [];
        foreach ($table as $pid => $st) {
            $items[] = [
                'player_id' => (int) $pid,
                'standing' => $st,
                'points' => amiga_scoring_standing_points($st, $contract),
            ];
        }
        usort(
            $items,
            static function (array $a, array $b): int {
                if ($a['points'] !== $b['points']) {
                    return $b['points'] <=> $a['points'];
                }

                return $a['player_id'] <=> $b['player_id'];
            }
        );

        $ordered = [];
        $idx = 0;
        $count = count($items);
        while ($idx < $count) {
            $pts = $items[$idx]['points'];
            $chunk = [];
            while ($idx < $count && $items[$idx]['points'] === $pts) {
                $chunk[] = $items[$idx];
                $idx++;
            }
            if (count($chunk) === 1) {
                $ordered[] = $chunk[0];
                continue;
            }
            $tiedIds = array_map(static fn (array $row): int => $row['player_id'], $chunk);
            $mini = amiga_ops_standings_mutual_mini_table($tiedIds, $games, $contract);
            usort(
                $chunk,
                static function (array $a, array $b) use ($contract, $mini): int {
                    $ka = amiga_scoring_league_sort_key($a['standing'], $contract, $mini, $a['player_id']);
                    $kb = amiga_scoring_league_sort_key($b['standing'], $contract, $mini, $b['player_id']);

                    return $ka <=> $kb;
                }
            );
            foreach ($chunk as $row) {
                $ordered[] = $row;
            }
        }
        $rankedItems = $ordered;
    } else {
        $items = [];
        foreach ($table as $pid => $st) {
            $items[] = [
                'player_id' => (int) $pid,
                'standing' => $st,
                'sort_key' => amiga_scoring_league_sort_key($st, $contract),
                'points' => amiga_scoring_standing_points($st, $contract),
            ];
        }

        usort(
            $items,
            static function (array $a, array $b): int {
                return $a['sort_key'] <=> $b['sort_key'];
            }
        );
        $rankedItems = $items;
    }

    $out = [];
    $pos = 0;
    $prevKey = null;
    foreach ($rankedItems as $rankIdx => $row) {
        $st = $row['standing'];
        $pid = $row['player_id'];
        $chunkPts = amiga_scoring_standing_points($st, $contract);
        $chunkIds = [];
        foreach ($rankedItems as $row2) {
            if (amiga_scoring_standing_points($row2['standing'], $contract) === $chunkPts) {
                $chunkIds[] = $row2['player_id'];
            }
        }
        $mini = null;
        if ($useH2h && count($chunkIds) > 1) {
            $mini = amiga_ops_standings_mutual_mini_table($chunkIds, $games, $contract);
        }
        $key = amiga_scoring_league_position_tie_key($st, $contract, $mini, $pid);
        if ($key !== $prevKey) {
            $pos = $rankIdx + 1;
            $prevKey = $key;
        }
        $out[] = [
            'player_id' => $pid,
            'position' => $pos,
            'games' => $st['games'],
            'wins' => $st['wins'],
            'draws' => $st['draws'],
            'losses' => $st['losses'],
            'goals_for' => $st['goals_for'],
            'goals_against' => $st['goals_against'],
            'points' => $row['points'],
        ];
    }

    return $out;
}

/**
 * @param array<int, array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, win_points?: int, draw_points?: int}> $table
 * @param list<array<string, mixed>> $games
 * @param array<string, mixed> $contract
 * @return list<array{player_id: int, position: int, games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, points: int}>
 */
function amiga_ops_standings_knockout_positions(array $table, array $games, array $contract): array
{
    if (count($table) !== 2) {
        return amiga_ops_standings_assign_positions($table, $contract);
    }

    $ids = array_keys($table);
    sort($ids, SORT_NUMERIC);
    $id1 = (int) $ids[0];
    $id2 = (int) $ids[1];
    $s1 = $table[$id1];
    $s2 = $table[$id2];
    $gd1 = $s1['goals_for'] - $s1['goals_against'];
    $gd2 = $s2['goals_for'] - $s2['goals_against'];

    foreach ($contract['steps'] as $step) {
        if ($step === 'aggregate_goal_difference') {
            if ($gd1 > $gd2) {
                return [
                    amiga_ops_standings_row_with_position($id1, $s1, 1, $contract),
                    amiga_ops_standings_row_with_position($id2, $s2, 2, $contract),
                ];
            }
            if ($gd2 > $gd1) {
                return [
                    amiga_ops_standings_row_with_position($id2, $s2, 1, $contract),
                    amiga_ops_standings_row_with_position($id1, $s1, 2, $contract),
                ];
            }
        } elseif ($step === 'goals_for') {
            if ($s1['goals_for'] > $s2['goals_for']) {
                return [
                    amiga_ops_standings_row_with_position($id1, $s1, 1, $contract),
                    amiga_ops_standings_row_with_position($id2, $s2, 2, $contract),
                ];
            }
            if ($s2['goals_for'] > $s1['goals_for']) {
                return [
                    amiga_ops_standings_row_with_position($id2, $s2, 1, $contract),
                    amiga_ops_standings_row_with_position($id1, $s1, 2, $contract),
                ];
            }
        } elseif (in_array($step, ['extra_time', 'penalty_shootout', 'golden_goal'], true)) {
            foreach ($games as $g) {
                $wid = amiga_resolve_game_extension_winner(
                    $g,
                    $step,
                    (int) $g['player_a_id'],
                    (int) $g['player_b_id']
                );
                if ($wid !== null) {
                    $loserId = $wid === $id1 ? $id2 : $id1;

                    return [
                        amiga_ops_standings_row_with_position($wid, $table[$wid], 1, $contract),
                        amiga_ops_standings_row_with_position($loserId, $table[$loserId], 2, $contract),
                    ];
                }
            }
        } elseif ($step === 'points') {
            $p1 = amiga_scoring_standing_points($s1, $contract);
            $p2 = amiga_scoring_standing_points($s2, $contract);
            if ($p1 > $p2) {
                return [
                    amiga_ops_standings_row_with_position($id1, $s1, 1, $contract),
                    amiga_ops_standings_row_with_position($id2, $s2, 2, $contract),
                ];
            }
            if ($p2 > $p1) {
                return [
                    amiga_ops_standings_row_with_position($id2, $s2, 1, $contract),
                    amiga_ops_standings_row_with_position($id1, $s1, 2, $contract),
                ];
            }
        }
    }

    return amiga_ops_standings_assign_positions($table, $contract);
}

/**
 * @param array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, win_points?: int, draw_points?: int} $st
 * @param array<string, mixed> $contract
 * @return array{player_id: int, position: int, games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, points: int}
 */
function amiga_ops_standings_row_with_position(int $playerId, array $st, int $position, array $contract): array
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
        'points' => amiga_scoring_standing_points($st, $contract),
    ];
}

/**
 * @param list<array<string, mixed>> $games
 * @param array{
 *   default_league: array<string, mixed>,
 *   default_knockout: array<string, mixed>,
 *   by_stage_id: array<int, array<string, mixed>>
 * }|null $scoringContext
 * @return list<array<string, mixed>>
 */
function amiga_ops_compute_tournament_standings(array $games, ?array $scoringContext = null): array
{
    if ($games === []) {
        return [];
    }

    $tournamentId = (int) $games[0]['tournament_id'];
    $context = $scoringContext ?? amiga_scoring_default_context($tournamentId);

    /** @var array<string, array<int, array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, win_points?: int, draw_points?: int, loss_points?: int}>> $scopes */
    $scopes = [];
    /** @var array<string, array<string, mixed>> $scopeContracts */
    $scopeContracts = [];
    /** @var array<string, array<int, array{games: int, wins: int, draws: int, losses: int, goals_for: int, goals_against: int, win_points?: int, draw_points?: int, loss_points?: int}>> $knockoutScopes */
    $knockoutScopes = [];
    /** @var array<string, list<array<string, mixed>>> $knockoutGames */
    $knockoutGames = [];
    /** @var array<string, list<array<string, mixed>>> $scopeLeagueGames */
    $scopeLeagueGames = [];
    /** @var array<string, int|null> $scopeStageIds */
    $scopeStageIds = [];
    $hasNullPhase = false;
    $hasStructured = false;

    foreach ($games as $g) {
        $playerAId = (int) $g['player_a_id'];
        $playerBId = (int) $g['player_b_id'];
        $goalsA = (int) $g['goals_a'];
        $goalsB = (int) $g['goals_b'];

        $fixtureScope = amiga_ops_fixture_standings_scope($g, $playerAId, $playerBId);
        if ($fixtureScope !== null) {
            $hasStructured = true;
            $contract = amiga_scoring_context_contract_for_game(
                $context,
                $g,
                (bool) $fixtureScope['elimination']
            );
            $scopeKey = $fixtureScope['scope_type'] . "\0" . $fixtureScope['scope_key'];
            amiga_ops_standings_record_scope_contract($scopeContracts, $scopeKey, $contract);
            amiga_ops_record_scope_stage_id($scopeStageIds, $scopeKey, amiga_ops_game_stage_id($g));
            if ($fixtureScope['elimination']) {
                if (!isset($knockoutScopes[$scopeKey])) {
                    $knockoutScopes[$scopeKey] = [];
                }
                if (!isset($knockoutGames[$scopeKey])) {
                    $knockoutGames[$scopeKey] = [];
                }
                $knockoutGames[$scopeKey][] = $g;
                amiga_ops_standings_apply_game_to_table(
                    $knockoutScopes[$scopeKey],
                    $playerAId,
                    $playerBId,
                    $goalsA,
                    $goalsB,
                    $contract
                );
            } else {
                if (!isset($scopes[$scopeKey])) {
                    $scopes[$scopeKey] = [];
                }
                if (!isset($scopeLeagueGames[$scopeKey])) {
                    $scopeLeagueGames[$scopeKey] = [];
                }
                $scopeLeagueGames[$scopeKey][] = $g;
                amiga_ops_standings_apply_game_to_table(
                    $scopes[$scopeKey],
                    $playerAId,
                    $playerBId,
                    $goalsA,
                    $goalsB,
                    $contract
                );
            }
            continue;
        }

        $phase = $g['phase'] ?? null;
        if ($phase === null || trim((string) $phase) === '') {
            $hasNullPhase = true;
        } else {
            $hasStructured = true;
        }

        if (amiga_ops_is_knockout_phase($phase !== null ? (string) $phase : null)) {
            $phaseStr = amiga_ops_normalize_whitespace((string) $phase);
            $pairKey = amiga_ops_knockout_pair_scope_key($phaseStr, $playerAId, $playerBId);
            $scopeKey = AMIGA_SCOPE_TYPE_KNOCKOUT . "\0" . $pairKey;
            $contract = $context['default_knockout'];
            amiga_ops_standings_record_scope_contract($scopeContracts, $scopeKey, $contract);
            if (!isset($knockoutScopes[$scopeKey])) {
                $knockoutScopes[$scopeKey] = [];
            }
            if (!isset($knockoutGames[$scopeKey])) {
                $knockoutGames[$scopeKey] = [];
            }
            $knockoutGames[$scopeKey][] = $g;
            amiga_ops_standings_apply_game_to_table(
                $knockoutScopes[$scopeKey],
                $playerAId,
                $playerBId,
                $goalsA,
                $goalsB,
                $contract
            );
            continue;
        }

        $scope = amiga_ops_parse_phase($phase !== null ? (string) $phase : null);
        if (!amiga_ops_is_league_scope($scope)) {
            continue;
        }
        $scopeKey = $scope['scope_type'] . "\0" . $scope['scope_key'];
        $contract = $context['default_league'];
        amiga_ops_standings_record_scope_contract($scopeContracts, $scopeKey, $contract);
        if (!isset($scopes[$scopeKey])) {
            $scopes[$scopeKey] = [];
        }
        if (!isset($scopeLeagueGames[$scopeKey])) {
            $scopeLeagueGames[$scopeKey] = [];
        }
        $scopeLeagueGames[$scopeKey][] = $g;
        amiga_ops_standings_apply_game_to_table(
            $scopes[$scopeKey],
            $playerAId,
            $playerBId,
            $goalsA,
            $goalsB,
            $contract
        );
    }

    if ($hasNullPhase && !$hasStructured) {
        $filtered = [];
        $leagueKey = AMIGA_SCOPE_TYPE_LEAGUE . "\0";
        if (isset($scopes[$leagueKey])) {
            $filtered[$leagueKey] = $scopes[$leagueKey];
        }
        $scopes = $filtered;
        $scopeContracts[$leagueKey] = $context['default_league'];
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
                    $leagueAggregate[$pid] = [
                        'games' => 0,
                        'wins' => 0,
                        'draws' => 0,
                        'losses' => 0,
                        'goals_for' => 0,
                        'goals_against' => 0,
                        'win_points' => $st['win_points'] ?? AMIGA_SCORING_WIN_POINTS,
                        'draw_points' => $st['draw_points'] ?? AMIGA_SCORING_DRAW_POINTS,
                        'loss_points' => $st['loss_points'] ?? AMIGA_SCORING_LOSS_POINTS,
                    ];
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
            $scopeContracts[AMIGA_SCOPE_TYPE_LEAGUE . "\0"] = $context['default_league'];
            $scopeStageIds[AMIGA_SCOPE_TYPE_LEAGUE . "\0"] = null;
        }
    }

    foreach ($knockoutScopes as $key => $table) {
        $scopes[$key] = $table;
    }

    $rows = [];
    ksort($scopes);
    foreach ($scopes as $key => $table) {
        if ($table === []) {
            continue;
        }
        [$stype, $skey] = explode("\0", $key, 2);
        $contract = $scopeContracts[$key] ?? (
            $stype === AMIGA_SCOPE_TYPE_KNOCKOUT
                ? $context['default_knockout']
                : $context['default_league']
        );
        if ($stype === AMIGA_SCOPE_TYPE_KNOCKOUT) {
            $ranked = amiga_ops_standings_knockout_positions($table, $knockoutGames[$key] ?? [], $contract);
        } else {
            $ranked = amiga_ops_standings_assign_positions(
                $table,
                $contract,
                $scopeLeagueGames[$key] ?? []
            );
        }
        foreach ($ranked as $r) {
            $stageId = $scopeStageIds[$key] ?? null;
            $rows[] = [
                'tournament_id' => $tournamentId,
                'player_id' => $r['player_id'],
                'scope_type' => $stype,
                'scope_key' => $skey,
                'stage_id' => $stageId,
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
                tournament_id, game_count, standing_players, standing_rows, league_scopes, knockout_ties,
                has_perfect_participant
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
                ), 0),
                CASE WHEN EXISTS (
                    SELECT 1 FROM amiga_player_event_snapshots
                    WHERE tournament_id = ? AND is_perfect_event = 1
                ) THEN 1 ELSE 0 END
            ON DUPLICATE KEY UPDATE
                game_count = VALUES(game_count),
                standing_players = VALUES(standing_players),
                standing_rows = VALUES(standing_rows),
                league_scopes = VALUES(league_scopes),
                knockout_ties = VALUES(knockout_ties),
                has_perfect_participant = VALUES(has_perfect_participant)';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare catalog stats refresh: ' . $con->error);
    }
    $stmt->bind_param('iiiiiii', $tournamentId, $tournamentId, $tournamentId, $tournamentId, $tournamentId, $tournamentId, $tournamentId);
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
        . 'tournament_id, player_id, scope_type, scope_key, stage_id, position, '
        . 'games, wins, draws, losses, goals_for, goals_against, points'
        . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $stmt = $con->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('prepare standings insert: ' . $con->error);
    }

    foreach ($rows as $row) {
        $tid = (int) $row['tournament_id'];
        $pid = (int) $row['player_id'];
        $stype = (string) $row['scope_type'];
        $skey = (string) $row['scope_key'];
        $stageId = array_key_exists('stage_id', $row) && $row['stage_id'] !== null
            ? (int) $row['stage_id']
            : null;
        $pos = (int) $row['position'];
        $games = (int) $row['games'];
        $wins = (int) $row['wins'];
        $draws = (int) $row['draws'];
        $losses = (int) $row['losses'];
        $gf = (int) $row['goals_for'];
        $ga = (int) $row['goals_against'];
        $pts = (int) $row['points'];
        $stmt->bind_param(
            'iissiiiiiiiii',
            $tid,
            $pid,
            $stype,
            $skey,
            $stageId,
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
    $scoringContext = amiga_scoring_load_context_for_tournament($con, $tournamentId);
    $rows = amiga_ops_compute_tournament_standings($tournamentGames, $scoringContext);
    amiga_ops_standings_replace_tournament($con, $tournamentId, $rows);
    amiga_ops_catalog_stats_refresh_tournament($con, $tournamentId);
}
