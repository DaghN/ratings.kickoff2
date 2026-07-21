<?php
/**
 * Apply-import helpers for ko2amiga packs (shared by run_import + restore smoke).
 */
declare(strict_types=1);

if (!defined('AMIGA_IMPORT_DIR')) {
    define('AMIGA_IMPORT_DIR', dirname(__DIR__) . '/_import');
}

function k2_amiga_import_begin_stream(): void
{
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
}

function k2_amiga_import_flush(): void
{
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

function k2_amiga_import_stmt_label(string $stmt): string
{
    if (preg_match('/^INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $stmt, $m)) {
        return 'INSERT ' . $m[1];
    }
    if (preg_match('/^CREATE\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $stmt, $m)) {
        return 'CREATE ' . $m[1];
    }
    if (preg_match('/^DROP\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $stmt, $m)) {
        return 'DROP ' . $m[1];
    }

    return substr(preg_replace('/\s+/', ' ', trim($stmt)) ?? '', 0, 48);
}

/**
 * @return list<string>
 */
function k2_amiga_import_manifest_parts(): array
{
    $manifestPath = AMIGA_IMPORT_DIR . '/ko2amiga_manifest.json';
    if (is_file($manifestPath)) {
        $raw = (string) file_get_contents($manifestPath);
        if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
            $raw = substr($raw, 3);
        }
        $json = json_decode($raw, true);
        if (is_array($json) && !empty($json['parts']) && is_array($json['parts'])) {
            return array_values(array_map('strval', $json['parts']));
        }
    }

    return ['ko2amiga_db.sql'];
}

/**
 * @return array{ok:bool,error:string,bytes:int,sql?:string}
 */
function k2_amiga_import_read_dump(string $path): array
{
    if (!is_file($path)) {
        return ['ok' => false, 'error' => 'Dump file not found: ' . basename($path), 'bytes' => 0];
    }
    if (!is_readable($path)) {
        return ['ok' => false, 'error' => 'Dump file is not readable.', 'bytes' => 0];
    }
    $sql = file_get_contents($path);
    if ($sql === false) {
        return ['ok' => false, 'error' => 'Could not read dump file.', 'bytes' => 0];
    }
    if (strncmp($sql, "\xEF\xBB\xBF", 3) === 0) {
        $sql = substr($sql, 3);
    }
    $sql = preg_replace('/^CREATE DATABASE .*?;\s*/mi', '', $sql) ?? $sql;
    $sql = preg_replace('/^USE `?ko2amiga_db`?;\s*/mi', '', $sql) ?? $sql;
    if (trim($sql) === '') {
        return ['ok' => false, 'error' => 'Dump file is empty after cleanup.', 'bytes' => 0];
    }

    return ['ok' => true, 'error' => '', 'bytes' => strlen($sql), 'sql' => $sql];
}

/**
 * @return list<string>
 */
function k2_amiga_import_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    foreach (preg_split('/\R/', $sql) as $line) {
        $trim = trim($line);
        if ($trim === '' || strncmp($trim, '--', 2) === 0) {
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), ';')) {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
        }
    }
    $tail = trim($buffer);
    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

/**
 * @return array{ok:bool,error:string,statements:int}
 */
function k2_amiga_import_run_sql(mysqli $con, string $sql, bool $echoProgress): array
{
    mysqli_query($con, 'SET FOREIGN_KEY_CHECKS = 0');
    mysqli_query($con, 'SET UNIQUE_CHECKS = 0');
    @mysqli_query($con, 'SET SESSION max_allowed_packet = 67108864');

    $statements = k2_amiga_import_split_sql($sql);
    $total = count($statements);
    $done = 0;

    foreach ($statements as $stmt) {
        if (!mysqli_query($con, $stmt)) {
            return [
                'ok' => false,
                'error' => mysqli_error($con),
                'statements' => $done,
            ];
        }
        $done++;
        if ($echoProgress) {
            echo 'progress: ' . $done . ' / ' . $total . ' — ' . k2_amiga_import_stmt_label($stmt) . "\n";
            k2_amiga_import_flush();
        }
    }

    mysqli_query($con, 'SET FOREIGN_KEY_CHECKS = 1');
    mysqli_query($con, 'SET UNIQUE_CHECKS = 1');

    return ['ok' => true, 'error' => '', 'statements' => $done];
}

function k2_amiga_import_table_exists(mysqli $con, string $table): bool
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
    if ($safe === '') {
        return false;
    }
    $res = mysqli_query($con, "SHOW TABLES LIKE '" . mysqli_real_escape_string($con, $safe) . "'");
    if (!$res) {
        return false;
    }
    $exists = mysqli_num_rows($res) > 0;
    mysqli_free_result($res);

    return $exists;
}

/**
 * @return array{players:int,games:int,ok:bool,error:string,missing:bool}
 */
function k2_amiga_import_counts(mysqli $con): array
{
    $out = ['players' => 0, 'games' => 0, 'ok' => true, 'error' => '', 'missing' => false];
    foreach (['amiga_players' => 'players', 'amiga_games' => 'games'] as $table => $key) {
        if (!k2_amiga_import_table_exists($con, $table)) {
            $out['missing'] = true;
            continue;
        }
        $res = mysqli_query($con, 'SELECT COUNT(*) AS c FROM `' . $table . '`');
        if (!$res) {
            $out['ok'] = false;
            $out['error'] = mysqli_error($con);
            return $out;
        }
        $row = mysqli_fetch_assoc($res);
        mysqli_free_result($res);
        $out[$key] = (int) ($row['c'] ?? 0);
    }

    return $out;
}

/**
 * Apply all staged _import parts (CLI / smoke). Same semantics as browser Apply.
 *
 * @return array{ok:bool,error:string,parts:int,statements:int,elapsed:float}
 */
function k2_amiga_import_apply_all_parts(mysqli $con, bool $echoProgress = false): array
{
    $started = microtime(true);
    $parts = k2_amiga_import_manifest_parts();
    $statements = 0;
    foreach ($parts as $i => $file) {
        $path = AMIGA_IMPORT_DIR . '/' . $file;
        $read = k2_amiga_import_read_dump($path);
        if (!$read['ok']) {
            return [
                'ok' => false,
                'error' => 'Part ' . ($i + 1) . ' (' . $file . '): ' . $read['error'],
                'parts' => $i,
                'statements' => $statements,
                'elapsed' => round(microtime(true) - $started, 2),
            ];
        }
        $run = k2_amiga_import_run_sql($con, $read['sql'], $echoProgress);
        if (!$run['ok']) {
            return [
                'ok' => false,
                'error' => 'Part ' . ($i + 1) . ' (' . $file . '): ' . $run['error'],
                'parts' => $i,
                'statements' => $statements + (int) $run['statements'],
                'elapsed' => round(microtime(true) - $started, 2),
            ];
        }
        $statements += (int) $run['statements'];
        if ($echoProgress) {
            echo 'part ' . ($i + 1) . '/' . count($parts) . ' OK: ' . $file . "\n";
        }
    }
    return [
        'ok' => true,
        'error' => '',
        'parts' => count($parts),
        'statements' => $statements,
        'elapsed' => round(microtime(true) - $started, 2),
    ];
}