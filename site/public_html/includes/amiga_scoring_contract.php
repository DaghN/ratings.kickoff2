<?php
declare(strict_types=1);

/**
 * L4b scoring contract — platform_default_v1 copy-on-create (PHP parity with scoring_contract.py).
 */

const AMIGA_SCORING_SCHEMA_VERSION = 1;
const AMIGA_SCORING_WIN_POINTS = 3;
const AMIGA_SCORING_DRAW_POINTS = 1;
const AMIGA_SCORING_LOSS_POINTS = 0;

/** @var list<string> */
const AMIGA_SCORING_LEAGUE_TABLE_DEFAULT_STEPS = ['points', 'goal_difference', 'goals_for', 'games_played'];

/** @var list<string> */
const AMIGA_SCORING_KNOCKOUT_TIE_DEFAULT_STEPS = ['aggregate_goal_difference', 'extra_time', 'penalty_shootout'];

/** Executor-only KO chain for NULL DB contracts (catalog parity; mirrors Python LEGACY_KNOCKOUT_BRIDGE_STEPS). */
/** @var list<string> */
const AMIGA_SCORING_LEGACY_KNOCKOUT_BRIDGE_STEPS = ['aggregate_goal_difference', 'goals_for', 'penalty_shootout'];

/**
 * @return list<string>
 */
function amiga_scoring_default_steps_for_primitive(string $primitive): array
{
    if ($primitive === 'league_table') {
        return ['points', 'goal_difference', 'goals_for', 'games_played'];
    }
    if ($primitive === 'knockout_tie') {
        return ['aggregate_goal_difference', 'extra_time', 'penalty_shootout'];
    }
    throw new InvalidArgumentException('unsupported scoring primitive: ' . $primitive);
}

/**
 * @return list<string>
 */
function amiga_scoring_legacy_knockout_bridge_steps(): array
{
    return AMIGA_SCORING_LEGACY_KNOCKOUT_BRIDGE_STEPS;
}

/**
 * @param list<string>|null $steps
 * @return array{
 *   stage_id: int,
 *   tournament_id: int,
 *   stage_key: string,
 *   stage_type: string,
 *   primitive: string,
 *   schema_version: int,
 *   win_points: int,
 *   draw_points: int,
 *   loss_points: int,
 *   steps: list<string>
 * }
 */
function amiga_scoring_synthetic_league_contract(
    int $tournamentId,
    int $stageId = 0,
    string $stageKey = '',
    string $stageType = 'round_robin',
    int $winPoints = AMIGA_SCORING_WIN_POINTS,
    int $drawPoints = AMIGA_SCORING_DRAW_POINTS,
    int $lossPoints = AMIGA_SCORING_LOSS_POINTS
): array {
    return [
        'stage_id' => $stageId,
        'tournament_id' => $tournamentId,
        'stage_key' => $stageKey,
        'stage_type' => $stageType,
        'primitive' => 'league_table',
        'schema_version' => AMIGA_SCORING_SCHEMA_VERSION,
        'win_points' => $winPoints,
        'draw_points' => $drawPoints,
        'loss_points' => $lossPoints,
        'steps' => AMIGA_SCORING_LEAGUE_TABLE_DEFAULT_STEPS,
    ];
}

/**
 * @param list<string>|null $steps
 * @return array{
 *   stage_id: int,
 *   tournament_id: int,
 *   stage_key: string,
 *   stage_type: string,
 *   primitive: string,
 *   schema_version: int,
 *   win_points: int,
 *   draw_points: int,
 *   loss_points: int,
 *   steps: list<string>
 * }
 */
function amiga_scoring_synthetic_knockout_contract(
    int $tournamentId,
    int $stageId = 0,
    string $stageKey = '',
    int $winPoints = AMIGA_SCORING_WIN_POINTS,
    int $drawPoints = AMIGA_SCORING_DRAW_POINTS,
    int $lossPoints = AMIGA_SCORING_LOSS_POINTS,
    ?array $steps = null
): array {
    return [
        'stage_id' => $stageId,
        'tournament_id' => $tournamentId,
        'stage_key' => $stageKey,
        'stage_type' => 'knockout',
        'primitive' => 'knockout_tie',
        'schema_version' => AMIGA_SCORING_SCHEMA_VERSION,
        'win_points' => $winPoints,
        'draw_points' => $drawPoints,
        'loss_points' => $lossPoints,
        'steps' => $steps ?? AMIGA_SCORING_LEGACY_KNOCKOUT_BRIDGE_STEPS,
    ];
}

/**
 * @return array{win_points: int, draw_points: int, loss_points: int}
 */
function amiga_scoring_tournament_point_defaults(?array $row): array
{
    if ($row === null) {
        return [
            'win_points' => AMIGA_SCORING_WIN_POINTS,
            'draw_points' => AMIGA_SCORING_DRAW_POINTS,
            'loss_points' => AMIGA_SCORING_LOSS_POINTS,
        ];
    }

    return [
        'win_points' => $row['scoring_win_points_default'] !== null
            ? (int) $row['scoring_win_points_default'] : AMIGA_SCORING_WIN_POINTS,
        'draw_points' => $row['scoring_draw_points_default'] !== null
            ? (int) $row['scoring_draw_points_default'] : AMIGA_SCORING_DRAW_POINTS,
        'loss_points' => $row['scoring_loss_points_default'] !== null
            ? (int) $row['scoring_loss_points_default'] : AMIGA_SCORING_LOSS_POINTS,
    ];
}

/**
 * @return list<string>
 */
function amiga_scoring_load_stage_steps(mysqli $con, int $stageId): array
{
    $stmt = $con->prepare(
        'SELECT sequence_no, step FROM tournament_stage_scoring_steps '
        . 'WHERE stage_id = ? ORDER BY sequence_no ASC'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare stage scoring steps: ' . $con->error);
    }
    $stmt->bind_param('i', $stageId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stage scoring steps: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $steps = [];
    $expected = 1;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $seq = (int) $row['sequence_no'];
            if ($seq !== $expected) {
                throw new RuntimeException(
                    'stage_id=' . $stageId . ': scoring step sequence gap at ' . $expected
                );
            }
            $steps[] = (string) $row['step'];
            $expected++;
        }
        $res->free();
    }
    $stmt->close();

    return $steps;
}

/**
 * @return array{
 *   stage_id: int,
 *   tournament_id: int,
 *   stage_key: string,
 *   stage_type: string,
 *   primitive: string,
 *   schema_version: int,
 *   win_points: int,
 *   draw_points: int,
 *   loss_points: int,
 *   steps: list<string>
 * }|null
 */
function amiga_scoring_load_stage_contract(mysqli $con, int $stageId): ?array
{
    $stmt = $con->prepare(
        'SELECT id, tournament_id, stage_key, stage_type, scoring_primitive, '
        . 'scoring_schema_version, scoring_win_points, scoring_draw_points, scoring_loss_points '
        . 'FROM tournament_stages WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare stage contract: ' . $con->error);
    }
    $stmt->bind_param('i', $stageId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stage contract: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException('stage_id=' . $stageId . ' not found');
    }
    if ($row['scoring_primitive'] === null) {
        return null;
    }
    foreach (['scoring_schema_version', 'scoring_win_points', 'scoring_draw_points', 'scoring_loss_points'] as $col) {
        if ($row[$col] === null) {
            throw new RuntimeException(
                'stage_id=' . $stageId . ': scoring_primitive set but missing ' . $col
            );
        }
    }

    return [
        'stage_id' => (int) $row['id'],
        'tournament_id' => (int) $row['tournament_id'],
        'stage_key' => (string) $row['stage_key'],
        'stage_type' => (string) $row['stage_type'],
        'primitive' => (string) $row['scoring_primitive'],
        'schema_version' => (int) $row['scoring_schema_version'],
        'win_points' => (int) $row['scoring_win_points'],
        'draw_points' => (int) $row['scoring_draw_points'],
        'loss_points' => (int) $row['scoring_loss_points'],
        'steps' => amiga_scoring_load_stage_steps($con, $stageId),
    ];
}

/**
 * @return array{
 *   default_league: array<string, mixed>,
 *   default_knockout: array<string, mixed>,
 *   by_stage_id: array<int, array<string, mixed>>
 * }
 */
function amiga_scoring_load_context_for_tournament(mysqli $con, int $tournamentId): array
{
    $stmt = $con->prepare(
        'SELECT scoring_win_points_default, scoring_draw_points_default, scoring_loss_points_default '
        . 'FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament scoring context: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament scoring context: ' . $con->error);
    }
    $res = $stmt->get_result();
    $tourRow = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    $pts = amiga_scoring_tournament_point_defaults($tourRow);
    $defaultLeague = amiga_scoring_synthetic_league_contract(
        $tournamentId,
        0,
        '',
        'round_robin',
        $pts['win_points'],
        $pts['draw_points'],
        $pts['loss_points']
    );
    $defaultKnockout = amiga_scoring_synthetic_knockout_contract(
        $tournamentId,
        0,
        '',
        $pts['win_points'],
        $pts['draw_points'],
        $pts['loss_points'],
        AMIGA_SCORING_LEGACY_KNOCKOUT_BRIDGE_STEPS
    );

    $stmt = $con->prepare(
        'SELECT id, stage_key, stage_type FROM tournament_stages WHERE tournament_id = ? ORDER BY id'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare stage list: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stage list: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $byStageId = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $stageId = (int) $row['id'];
            $stageType = (string) $row['stage_type'];
            $loaded = amiga_scoring_load_stage_contract($con, $stageId);
            if ($loaded !== null) {
                $byStageId[$stageId] = $loaded;
                continue;
            }
            if ($stageType === 'knockout') {
                $byStageId[$stageId] = amiga_scoring_synthetic_knockout_contract(
                    $tournamentId,
                    $stageId,
                    (string) $row['stage_key'],
                    $pts['win_points'],
                    $pts['draw_points'],
                    $pts['loss_points'],
                    AMIGA_SCORING_LEGACY_KNOCKOUT_BRIDGE_STEPS
                );
            } else {
                $byStageId[$stageId] = amiga_scoring_synthetic_league_contract(
                    $tournamentId,
                    $stageId,
                    (string) $row['stage_key'],
                    $stageType,
                    $pts['win_points'],
                    $pts['draw_points'],
                    $pts['loss_points']
                );
            }
        }
        $res->free();
    }
    $stmt->close();

    return [
        'default_league' => $defaultLeague,
        'default_knockout' => $defaultKnockout,
        'by_stage_id' => $byStageId,
    ];
}

/**
 * @return array{
 *   default_league: array<string, mixed>,
 *   default_knockout: array<string, mixed>,
 *   by_stage_id: array<int, array<string, mixed>>
 * }
 */
function amiga_scoring_default_context(int $tournamentId = 0): array
{
    return [
        'default_league' => amiga_scoring_synthetic_league_contract($tournamentId),
        'default_knockout' => amiga_scoring_synthetic_knockout_contract($tournamentId),
        'by_stage_id' => [],
    ];
}

/**
 * @param array{
 *   default_league: array<string, mixed>,
 *   default_knockout: array<string, mixed>,
 *   by_stage_id: array<int, array<string, mixed>>
 * } $context
 * @param array<string, mixed> $game
 * @return array<string, mixed>
 */
function amiga_scoring_context_contract_for_game(array $context, array $game, bool $isElimination): array
{
    if (isset($game['stage_id']) && $game['stage_id'] !== null && (int) $game['stage_id'] > 0) {
        $stageId = (int) $game['stage_id'];
        if (isset($context['by_stage_id'][$stageId])) {
            return $context['by_stage_id'][$stageId];
        }
    }

    return $isElimination ? $context['default_knockout'] : $context['default_league'];
}

/**
 * @param array{wins: int, draws: int, losses: int, goals_for: int, goals_against: int, win_points?: int, draw_points?: int} $standing
 */
function amiga_scoring_standing_points(array $standing, array $contract): int
{
    $winPts = isset($standing['win_points']) ? (int) $standing['win_points'] : (int) $contract['win_points'];
    $drawPts = isset($standing['draw_points']) ? (int) $standing['draw_points'] : (int) $contract['draw_points'];

    return (int) $standing['wins'] * $winPts + (int) $standing['draws'] * $drawPts;
}

/**
 * @param array{wins: int, draws: int, losses: int, goals_for: int, goals_against: int, games: int} $standing
 * @return list<int|float>
 */
function amiga_scoring_league_metric_negated(array $standing, string $step, array $contract): ?array
{
    $points = amiga_scoring_standing_points($standing, $contract);
    $gd = (int) $standing['goals_for'] - (int) $standing['goals_against'];
    if ($step === 'points') {
        return [-$points];
    }
    if ($step === 'goal_difference' || $step === 'aggregate_goal_difference') {
        return [-$gd];
    }
    if ($step === 'goals_for') {
        return [-(int) $standing['goals_for']];
    }
    if ($step === 'games_played') {
        return [-(int) $standing['games']];
    }

    return null;
}

/**
 * @param array<int, array{wins: int, draws: int, losses: int, goals_for: int, goals_against: int, games: int, win_points?: int, draw_points?: int, loss_points?: int}>|null $mini
 * @return list<int|float>
 */
function amiga_scoring_league_sort_key(
    array $standing,
    array $contract,
    ?array $mini = null,
    ?int $playerId = null
): array {
    $parts = [];
    foreach ($contract['steps'] as $step) {
        if ($step === 'head_to_head') {
            if ($mini !== null && $playerId !== null && isset($mini[$playerId])) {
                $m = $mini[$playerId];
                $gd = (int) $m['goals_for'] - (int) $m['goals_against'];
                array_push(
                    $parts,
                    -amiga_scoring_standing_points($m, $contract),
                    -$gd,
                    -(int) $m['goals_for']
                );
            }

            continue;
        }
        $metric = amiga_scoring_league_metric_negated($standing, $step, $contract);
        if ($metric === null) {
            continue;
        }
        array_push($parts, ...$metric);
    }
    if ($parts === []) {
        $gd = (int) $standing['goals_for'] - (int) $standing['goals_against'];

        return [
            -amiga_scoring_standing_points($standing, $contract),
            -$gd,
            -(int) $standing['goals_for'],
            -(int) $standing['games'],
        ];
    }

    return $parts;
}

/**
 * @param array<int, array{wins: int, draws: int, losses: int, goals_for: int, goals_against: int, games: int, win_points?: int, draw_points?: int, loss_points?: int}>|null $mini
 * @return list<int|float>
 */
function amiga_scoring_league_position_tie_key(
    array $standing,
    array $contract,
    ?array $mini = null,
    ?int $playerId = null
): array {
    $parts = [];
    foreach ($contract['steps'] as $step) {
        if ($step === 'games_played') {
            break;
        }
        if ($step === 'head_to_head') {
            if ($mini !== null && $playerId !== null && isset($mini[$playerId])) {
                $m = $mini[$playerId];
                $gd = (int) $m['goals_for'] - (int) $m['goals_against'];
                array_push(
                    $parts,
                    -amiga_scoring_standing_points($m, $contract),
                    -$gd,
                    -(int) $m['goals_for']
                );
            }

            continue;
        }
        $metric = amiga_scoring_league_metric_negated($standing, $step, $contract);
        if ($metric === null) {
            continue;
        }
        array_push($parts, ...$metric);
    }
    if ($parts === []) {
        $gd = (int) $standing['goals_for'] - (int) $standing['goals_against'];

        return [-amiga_scoring_standing_points($standing, $contract), -$gd, -(int) $standing['goals_for']];
    }

    return $parts;
}

function amiga_scoring_primitive_for_stage_type(string $stageType): string
{
    if ($stageType === 'round_robin') {
        return 'league_table';
    }
    if ($stageType === 'knockout') {
        return 'knockout_tie';
    }
    throw new InvalidArgumentException('unsupported stage_type for scoring contract: ' . $stageType);
}

function amiga_scoring_contract_ensure_tournament_defaults(mysqli $con, int $tournamentId): bool
{
    $stmt = $con->prepare(
        'SELECT scoring_win_points_default FROM tournaments WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament scoring defaults: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament scoring defaults select: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException('tournament_id=' . $tournamentId . ' not found');
    }
    if ($row['scoring_win_points_default'] !== null) {
        return false;
    }

    $win = AMIGA_SCORING_WIN_POINTS;
    $draw = AMIGA_SCORING_DRAW_POINTS;
    $loss = AMIGA_SCORING_LOSS_POINTS;
    $stmt = $con->prepare(
        'UPDATE tournaments SET scoring_win_points_default = ?, scoring_draw_points_default = ?, '
        . 'scoring_loss_points_default = ? WHERE id = ? AND scoring_win_points_default IS NULL'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare tournament scoring defaults update: ' . $con->error);
    }
    $stmt->bind_param('iiii', $win, $draw, $loss, $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute tournament scoring defaults update: ' . $stmt->error);
    }
    $wrote = $stmt->affected_rows > 0;
    $stmt->close();
    return $wrote;
}

function amiga_scoring_contract_ensure_stage(mysqli $con, int $stageId, string $stageType): bool
{
    $stmt = $con->prepare(
        'SELECT tournament_id, scoring_primitive FROM tournament_stages WHERE id = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare stage scoring select: ' . $con->error);
    }
    $stmt->bind_param('i', $stageId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stage scoring select: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException('stage_id=' . $stageId . ' not found');
    }
    if ($row['scoring_primitive'] !== null) {
        return false;
    }

    $tournamentId = (int) $row['tournament_id'];
    amiga_scoring_contract_ensure_tournament_defaults($con, $tournamentId);

    $primitive = amiga_scoring_primitive_for_stage_type($stageType);
    $schemaVersion = AMIGA_SCORING_SCHEMA_VERSION;
    $win = AMIGA_SCORING_WIN_POINTS;
    $draw = AMIGA_SCORING_DRAW_POINTS;
    $loss = AMIGA_SCORING_LOSS_POINTS;
    $stmt = $con->prepare(
        'UPDATE tournament_stages SET scoring_primitive = ?, scoring_schema_version = ?, '
        . 'scoring_win_points = ?, scoring_draw_points = ?, scoring_loss_points = ? '
        . 'WHERE id = ? AND scoring_primitive IS NULL'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare stage scoring update: ' . $con->error);
    }
    $stmt->bind_param('siiiii', $primitive, $schemaVersion, $win, $draw, $loss, $stageId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute stage scoring update: ' . $stmt->error);
    }
    if ($stmt->affected_rows === 0) {
        $stmt->close();
        return false;
    }
    $stmt->close();

    $steps = amiga_scoring_default_steps_for_primitive($primitive);
    $stmt = $con->prepare(
        'INSERT INTO tournament_stage_scoring_steps (stage_id, sequence_no, step) VALUES (?, ?, ?)'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare stage scoring steps: ' . $con->error);
    }
    foreach ($steps as $idx => $step) {
        $sequenceNo = $idx + 1;
        $stmt->bind_param('iis', $stageId, $sequenceNo, $step);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute stage scoring step insert: ' . $stmt->error);
        }
    }
    $stmt->close();
    return true;
}

/**
 * Copy live L4b contract onto frozen columns for stages missing a snapshot.
 */
function amiga_scoring_sync_unfrozen_stage_contracts(mysqli $con, int $tournamentId): int
{
    $stmt = $con->prepare(
        'UPDATE tournament_stages SET '
        . 'frozen_scoring_primitive = scoring_primitive, '
        . 'frozen_scoring_schema_version = scoring_schema_version, '
        . 'frozen_scoring_win_points = scoring_win_points, '
        . 'frozen_scoring_draw_points = scoring_draw_points, '
        . 'frozen_scoring_loss_points = scoring_loss_points '
        . 'WHERE tournament_id = ? '
        . 'AND scoring_primitive IS NOT NULL '
        . 'AND frozen_scoring_primitive IS NULL'
    );
    if ($stmt === false) {
        throw new RuntimeException('prepare scoring sync unfrozen stages: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute scoring sync unfrozen stages: ' . $stmt->error);
    }
    $stagesUpdated = $stmt->affected_rows;
    $stmt->close();
    return $stagesUpdated;
}

/**
 * @return array{tournament: int, stages: int, skipped: bool}
 */
function amiga_scoring_freeze_contracts_for_tournament(
    mysqli $con,
    int $tournamentId,
    string $frozenAt
): array {
    $stmt = $con->prepare('SELECT scoring_frozen_at FROM tournaments WHERE id = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare scoring freeze select: ' . $con->error);
    }
    $stmt->bind_param('i', $tournamentId);
    if (!$stmt->execute()) {
        throw new RuntimeException('execute scoring freeze select: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    if ($row === null) {
        throw new RuntimeException('tournament_id=' . $tournamentId . ' not found');
    }
    $alreadyFrozen = $row['scoring_frozen_at'] !== null;

    $stagesUpdated = amiga_scoring_sync_unfrozen_stage_contracts($con, $tournamentId);
    $tournamentUpdated = 0;
    if (!$alreadyFrozen) {
        $schemaVersion = AMIGA_SCORING_SCHEMA_VERSION;
        $stmt = $con->prepare(
            'UPDATE tournaments SET frozen_scoring_schema_version = ?, scoring_frozen_at = ? WHERE id = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('prepare scoring freeze tournament: ' . $con->error);
        }
        $stmt->bind_param('isi', $schemaVersion, $frozenAt, $tournamentId);
        if (!$stmt->execute()) {
            throw new RuntimeException('execute scoring freeze tournament: ' . $con->error);
        }
        $tournamentUpdated = $stmt->affected_rows;
        $stmt->close();
    }

    return [
        'tournament' => $tournamentUpdated,
        'stages' => $stagesUpdated,
        'skipped' => false,
    ];
}
