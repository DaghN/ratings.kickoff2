<?php
/**
 * Milestone unlock librarian — single live write path for player_milestones.
 *
 * @see docs/milestones-unlock-librarian.md
 */
declare(strict_types=1);

/**
 * @param array{
 *   player_id: int,
 *   milestone_key: string,
 *   achieved_at: string,
 *   value: int,
 *   source_kind?: string|null,
 *   source_game_id?: int|null,
 *   source_league_kind?: string|null,
 *   source_period_type?: string|null,
 *   source_period_start?: string|null,
 * } $payload
 */
function k2_milestone_unlock_insert(mysqli $con, array $payload): bool
{
    $playerId = (int) ($payload['player_id'] ?? 0);
    $milestoneKey = (string) ($payload['milestone_key'] ?? '');
    $achievedAt = (string) ($payload['achieved_at'] ?? '');
    $value = (int) ($payload['value'] ?? 0);

    if ($playerId < 1 || $milestoneKey === '' || $achievedAt === '') {
        return false;
    }

    if (!k2_milestone_unlock_tables_ready($con)) {
        return false;
    }

    $sourceKind = array_key_exists('source_kind', $payload) ? $payload['source_kind'] : null;
    $sourceGameId = array_key_exists('source_game_id', $payload) ? $payload['source_game_id'] : null;
    $sourceLeagueKind = array_key_exists('source_league_kind', $payload) ? $payload['source_league_kind'] : null;
    $sourcePeriodType = array_key_exists('source_period_type', $payload) ? $payload['source_period_type'] : null;
    $sourcePeriodStart = array_key_exists('source_period_start', $payload) ? $payload['source_period_start'] : null;

    $sourceKindParam = $sourceKind === null ? null : (string) $sourceKind;
    $sourceGameIdParam = $sourceGameId === null ? null : (int) $sourceGameId;
    $sourceLeagueKindParam = $sourceLeagueKind === null ? null : (string) $sourceLeagueKind;
    $sourcePeriodTypeParam = $sourcePeriodType === null ? null : (string) $sourcePeriodType;
    $sourcePeriodStartParam = $sourcePeriodStart === null ? null : (string) $sourcePeriodStart;

    $stmt = $con->prepare(
        'INSERT INTO `player_milestones` '
        . '(`player_id`, `milestone_key`, `achieved_at`, `value`, '
        . '`source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`) '
        . 'SELECT ?, ?, ?, ?, ?, ?, ?, ?, ? FROM DUAL '
        . 'WHERE NOT EXISTS ('
        . 'SELECT 1 FROM `player_milestones` '
        . 'WHERE `player_id` = ? AND `milestone_key` = ? LIMIT 1'
        . ')'
    );
    if ($stmt === false) {
        throw new RuntimeException('milestone unlock insert prepare: ' . $con->error);
    }

    $stmt->bind_param(
        'issisisssis',
        $playerId,
        $milestoneKey,
        $achievedAt,
        $value,
        $sourceKindParam,
        $sourceGameIdParam,
        $sourceLeagueKindParam,
        $sourcePeriodTypeParam,
        $sourcePeriodStartParam,
        $playerId,
        $milestoneKey
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('milestone unlock insert execute: ' . $err);
    }

    $inserted = $stmt->affected_rows > 0;
    $stmt->close();

    if ($inserted) {
        $tierBand = k2_milestone_unlock_tier_band($con, $milestoneKey);
        if ($tierBand !== null) {
            k2_milestone_totals_bump($con, $playerId, $tierBand);
        }
        k2_milestone_holder_count_bump($con, $milestoneKey);
    }

    return $inserted;
}

function k2_milestone_unlock_tables_ready(mysqli $con): bool
{
    if (!function_exists('k2_status_table_exists')) {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot !== '' && is_file($docRoot . '/includes/status_queries.php')) {
            require_once $docRoot . '/includes/status_queries.php';
        }
    }

    return function_exists('k2_status_table_exists')
        && k2_status_table_exists($con, 'milestone_definitions')
        && k2_status_table_exists($con, 'player_milestones');
}

function k2_milestone_totals_table_ready(mysqli $con): bool
{
    if (!function_exists('k2_status_table_exists')) {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot !== '' && is_file($docRoot . '/includes/status_queries.php')) {
            require_once $docRoot . '/includes/status_queries.php';
        }
    }

    return function_exists('k2_status_table_exists')
        && k2_status_table_exists($con, 'player_milestone_totals');
}

function k2_milestone_unlock_tier_band(mysqli $con, string $milestoneKey): ?string
{
    static $cache = [];

    if (array_key_exists($milestoneKey, $cache)) {
        return $cache[$milestoneKey];
    }

    $stmt = $con->prepare(
        'SELECT `tier_band` FROM `milestone_definitions` WHERE `milestone_key` = ? LIMIT 1'
    );
    if ($stmt === false) {
        return null;
    }
    $stmt->bind_param('s', $milestoneKey);
    if (!$stmt->execute()) {
        $stmt->close();

        return null;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();

    $tier = $row ? (string) ($row['tier_band'] ?? '') : '';
    if ($tier === '') {
        $cache[$milestoneKey] = null;

        return null;
    }

    $cache[$milestoneKey] = $tier;

    return $tier;
}

/**
 * Phase 2 — increment player_milestone_totals on new unlock.
 */
function k2_milestone_totals_bump(mysqli $con, int $playerId, string $tierBand): void
{
    if ($playerId < 1 || !k2_milestone_totals_table_ready($con)) {
        return;
    }

    $aspirational = $tierBand === 'aspirational' ? 1 : 0;
    $dedicated = $tierBand === 'veteran' ? 1 : 0;
    $accomplished = $tierBand === 'key' ? 1 : 0;
    $legendary = $tierBand === 'legendary' ? 1 : 0;
    if ($aspirational + $dedicated + $accomplished + $legendary === 0) {
        return;
    }

    $stmt = $con->prepare(
        'INSERT INTO `player_milestone_totals` '
        . '(`player_id`, `total`, `aspirational`, `dedicated`, `accomplished`, `legendary`) '
        . 'VALUES (?, 1, ?, ?, ?, ?) '
        . 'ON DUPLICATE KEY UPDATE '
        . '`total` = `total` + 1, '
        . '`aspirational` = `aspirational` + VALUES(`aspirational`), '
        . '`dedicated` = `dedicated` + VALUES(`dedicated`), '
        . '`accomplished` = `accomplished` + VALUES(`accomplished`), '
        . '`legendary` = `legendary` + VALUES(`legendary`)'
    );
    if ($stmt === false) {
        throw new RuntimeException('milestone totals bump prepare: ' . $con->error);
    }
    $stmt->bind_param('iiiii', $playerId, $aspirational, $dedicated, $accomplished, $legendary);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('milestone totals bump execute: ' . $err);
    }
    $stmt->close();
}

/**
 * Repair: rebuild player_milestone_totals from player_milestones + milestone_definitions.
 *
 * @return int rows in totals table after rebuild
 */
function k2_milestone_totals_rebuild(mysqli $con): int
{
    if (!k2_milestone_unlock_tables_ready($con) || !k2_milestone_totals_table_ready($con)) {
        return 0;
    }

    if (!mysqli_query($con, 'TRUNCATE TABLE `player_milestone_totals`')) {
        throw new RuntimeException('milestone totals rebuild truncate: ' . mysqli_error($con));
    }

    $sql = '
        INSERT INTO `player_milestone_totals` (
            `player_id`,
            `total`,
            `aspirational`,
            `dedicated`,
            `accomplished`,
            `legendary`
        )
        SELECT
            pm.`player_id`,
            COUNT(*) AS `total`,
            COALESCE(SUM(md.`tier_band` = \'aspirational\'), 0) AS `aspirational`,
            COALESCE(SUM(md.`tier_band` = \'veteran\'), 0) AS `dedicated`,
            COALESCE(SUM(md.`tier_band` = \'key\'), 0) AS `accomplished`,
            COALESCE(SUM(md.`tier_band` = \'legendary\'), 0) AS `legendary`
        FROM `player_milestones` pm
        INNER JOIN `milestone_definitions` md ON md.`milestone_key` = pm.`milestone_key`
        GROUP BY pm.`player_id`
    ';
    if (!mysqli_query($con, $sql)) {
        throw new RuntimeException('milestone totals rebuild insert: ' . mysqli_error($con));
    }

    $res = mysqli_query($con, 'SELECT COUNT(*) AS c FROM `player_milestone_totals`');
    if ($res === false) {
        return 0;
    }
    $row = mysqli_fetch_assoc($res);
    mysqli_free_result($res);

    return (int) ($row['c'] ?? 0);
}

function k2_milestone_holder_count_column_ready(mysqli $con): bool
{
    if (!function_exists('k2_status_table_exists')) {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if ($docRoot !== '' && is_file($docRoot . '/includes/status_queries.php')) {
            require_once $docRoot . '/includes/status_queries.php';
        }
    }

    if (!function_exists('k2_status_table_exists') || !k2_status_table_exists($con, 'milestone_definitions')) {
        return false;
    }

    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    $stmt = $con->prepare(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS '
        . 'WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    if ($stmt === false) {
        $ready = false;

        return false;
    }
    $table = 'milestone_definitions';
    $column = 'holder_count';
    $stmt->bind_param('ss', $table, $column);
    if (!$stmt->execute()) {
        $stmt->close();
        $ready = false;

        return false;
    }
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    if ($res) {
        $res->free();
    }
    $stmt->close();
    $ready = $row && (int) ($row['c'] ?? 0) > 0;

    return $ready;
}

/**
 * Increment catalog holder_count for one milestone key (one per unlock row — includes orphan earners).
 */
function k2_milestone_holder_count_bump(mysqli $con, string $milestoneKey): void
{
    if ($milestoneKey === '' || !k2_milestone_holder_count_column_ready($con)) {
        return;
    }

    $stmt = $con->prepare(
        'UPDATE `milestone_definitions` SET `holder_count` = `holder_count` + 1 '
        . 'WHERE `milestone_key` = ? LIMIT 1'
    );
    if ($stmt === false) {
        throw new RuntimeException('milestone holder_count bump prepare: ' . $con->error);
    }
    $stmt->bind_param('s', $milestoneKey);
    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        throw new RuntimeException('milestone holder_count bump execute: ' . $err);
    }
    $stmt->close();
}

/**
 * Repair: rebuild milestone_definitions.holder_count from player_milestones.
 */
function k2_milestone_holder_counts_rebuild(mysqli $con): void
{
    if (!k2_milestone_unlock_tables_ready($con) || !k2_milestone_holder_count_column_ready($con)) {
        return;
    }

    if (!mysqli_query($con, 'UPDATE `milestone_definitions` SET `holder_count` = 0')) {
        throw new RuntimeException('milestone holder_count rebuild zero: ' . mysqli_error($con));
    }

    $sql = '
        UPDATE `milestone_definitions` d
        INNER JOIN (
            SELECT pm.`milestone_key`, COUNT(*) AS `holders`
            FROM `player_milestones` pm
            GROUP BY pm.`milestone_key`
        ) h ON h.`milestone_key` = d.`milestone_key`
        SET d.`holder_count` = h.`holders`
    ';
    if (!mysqli_query($con, $sql)) {
        throw new RuntimeException('milestone holder_count rebuild update: ' . mysqli_error($con));
    }
}

/**
 * Repair player_milestone_totals + catalog holder_count after bulk unlock writes.
 */
function k2_milestone_stored_derived_rebuild(mysqli $con): void
{
    k2_milestone_totals_rebuild($con);
    k2_milestone_holder_counts_rebuild($con);
}
