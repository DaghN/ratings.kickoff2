<?php
/**
 * Zero-derived core ladder reset — mirrors archived scripts/ladder/engine.py reset_universe(); authority = this PHP file.
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_prepare_constants.php';
require_once __DIR__ . '/ops_paths.php';
require_once __DIR__ . '/ops_bootstrap.php';
require_once __DIR__ . '/ops_seed_lobby.php';

function k2_ops_split_sql_statements(string $sql): array
{
    $parts = [];
    foreach (explode(';', $sql) as $chunk) {
        $lines = [];
        foreach (preg_split('/\R/', $chunk) ?: [] as $line) {
            $trim = ltrim($line);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            $lines[] = $line;
        }
        $statement = trim(implode("\n", $lines));
        if ($statement !== '') {
            $parts[] = $statement;
        }
    }
    return $parts;
}

function k2_ops_ensure_generalstatstable(mysqli $con): void
{
    $path = k2_ops_generalstatstable_sql_path();
    if (!is_file($path)) {
        fwrite(stderr(), "Missing SQL: {$path}\n");
        exit(1);
    }
    $sql = file_get_contents($path);
    if ($sql === false || $sql === '') {
        fwrite(stderr(), "Empty SQL: {$path}\n");
        exit(1);
    }
    foreach (k2_ops_split_sql_statements($sql) as $statement) {
        if (!$con->query($statement)) {
            fwrite(stderr(), 'generalstatstable SQL failed: ' . $con->error . PHP_EOL);
            exit(1);
        }
        if ($result = $con->store_result()) {
            $result->free();
        }
    }
}

function k2_ops_reset_generalstatstable_row(mysqli $con): void
{
    if (!k2_ops_table_exists($con, 'generalstatstable')) {
        return;
    }
    $res = $con->query(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS "
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'generalstatstable' "
        . "AND COLUMN_NAME != 'id' ORDER BY ORDINAL_POSITION"
    );
    if ($res === false) {
        fwrite(stderr(), 'generalstatstable columns: ' . $con->error . PHP_EOL);
        exit(1);
    }
    $cols = [];
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row['COLUMN_NAME'];
    }
    $res->free();
    if ($cols === []) {
        return;
    }
    $sets = implode(', ', array_map(static fn (string $c): string => "`{$c}` = NULL", $cols));
    if (!$con->query("UPDATE generalstatstable SET {$sets} WHERE id = 1")) {
        fwrite(stderr(), 'generalstatstable clear: ' . $con->error . PHP_EOL);
        exit(1);
    }
    k2_ops_log('generalstatstable id=1 cleared (' . count($cols) . ' columns)');
}

function k2_ops_reset_universe(mysqli $con, bool $dryRun): void
{
    $ratedClear = implode(', ', array_map(
        static fn (string $c): string => "`{$c}` = NULL",
        K2_OPS_RATEDRESULTS_CLEAR
    ));
    $sqlRated = "UPDATE ratedresults SET {$ratedClear}";

    $playerParts = ['`Rating` = ' . (int) K2_OPS_START_RATING];
    foreach (K2_OPS_PLAYERTABLE_NULL_ON_RESET as $col) {
        $playerParts[] = "`{$col}` = NULL";
    }
    foreach (K2_OPS_PLAYERTABLE_ZERO_ON_RESET as $col) {
        $playerParts[] = "`{$col}` = 0";
    }
    foreach (K2_OPS_PLAYERTABLE_SENTINELS_ON_RESET as $col => $val) {
        $playerParts[] = is_int($val) ? "`{$col}` = {$val}" : "`{$col}` = {$val}";
    }
    $playerParts[] = "`LastGame` = '" . K2_OPS_PLAYERTABLE_LASTGAME_RESET . "'";
    $sqlPlayer = 'UPDATE playertable SET ' . implode(', ', $playerParts);

    $res = $con->query('SELECT COUNT(*) AS n FROM ratedresults');
    $games = $res ? (int) $res->fetch_assoc()['n'] : 0;
    if ($res) {
        $res->free();
    }
    $res = $con->query('SELECT COUNT(*) AS n FROM playertable');
    $players = $res ? (int) $res->fetch_assoc()['n'] : 0;
    if ($res) {
        $res->free();
    }
    k2_ops_log("reset_universe: ratedresults rows={$games}, playertable rows={$players}");

    if ($dryRun) {
        return;
    }

    k2_ops_ensure_generalstatstable($con);
    if (!$con->query($sqlRated)) {
        fwrite(stderr(), 'ratedresults clear: ' . $con->error . PHP_EOL);
        exit(1);
    }
    k2_ops_log('ratedresults cleared: ' . $con->affected_rows . ' rows affected');
    if (!$con->query($sqlPlayer)) {
        fwrite(stderr(), 'playertable reset: ' . $con->error . PHP_EOL);
        exit(1);
    }
    k2_ops_log('playertable reset: ' . $con->affected_rows . ' rows affected');
    k2_ops_reset_generalstatstable_row($con);
    $con->commit();
}

function k2_ops_truncate_aggregate_tables(mysqli $con, bool $dryRun): void
{
    foreach (K2_OPS_AGGREGATE_TABLES_TRUNCATE as $table) {
        if (!k2_ops_table_exists($con, $table)) {
            k2_ops_log("truncate skip (missing): {$table}");
            continue;
        }
        k2_ops_log("truncate {$table}");
        if (!$dryRun && !$con->query("TRUNCATE TABLE `{$table}`")) {
            fwrite(stderr(), "truncate {$table}: " . $con->error . PHP_EOL);
            exit(1);
        }
    }
    if (!$dryRun) {
        $con->commit();
    }
}

function k2_ops_zero_derived(K2OpsWorkTarget $target, bool $dryRun): void
{
    k2_ops_assert_mutate_work_target($target);
    k2_ops_log("zero_derived profile={$target->profile} database={$target->workDatabase} dry_run=" . ($dryRun ? 'true' : 'false'));

    $con = k2_ops_connect_work($target);
    $con->autocommit(false);
    try {
        k2_ops_reset_universe($con, $dryRun);
        k2_ops_truncate_aggregate_tables($con, $dryRun);
    } finally {
        $con->close();
    }
    k2_ops_seed_lobby_milestones($target, $dryRun);
    k2_ops_log('[OK] zero_derived complete on ' . $target->workDatabase);
}
