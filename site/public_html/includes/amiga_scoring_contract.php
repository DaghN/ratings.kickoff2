<?php
declare(strict_types=1);

/**
 * L4b scoring contract — platform_default_v1 copy-on-create (PHP parity with scoring_contract.py).
 */

const AMIGA_SCORING_SCHEMA_VERSION = 1;
const AMIGA_SCORING_WIN_POINTS = 3;
const AMIGA_SCORING_DRAW_POINTS = 1;
const AMIGA_SCORING_LOSS_POINTS = 0;

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
