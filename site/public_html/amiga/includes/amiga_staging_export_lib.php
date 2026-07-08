<?php
/**
 * Staging pull export — SQL dump of ko2amiga_db product tables (PULL-1b).
 */
declare(strict_types=1);

function k2_amiga_export_table_list(): array
{
    return [
        'tournament_format_templates',
        'tournaments',
        'amiga_players',
        'tournament_entrants',
        'tournament_stages',
        'tournament_stage_players',
        'tournament_fixtures',
        'amiga_games',
        'amiga_game_ratings',
        'amiga_player_event_snapshots',
        'amiga_player_current',
        'amiga_player_elo_rank_at_event',
        'amiga_player_matchup_at_event',
        'amiga_player_matchup_summary',
        'amiga_tournament_standings',
        'amiga_tournament_catalog_stats',
        'amiga_generalstats',
        'amiga_realm_snapshots',
        'amiga_community_stats',
        'amiga_community_stats_snapshots',
        'amiga_community_stat_facts',
        'amiga_world_cup_stats',
        'amiga_tournament_finish_override',
        'amiga_player_slice_totals',
        'amiga_player_slice_at_event',
        'amiga_country_slice_totals',
        'amiga_country_slice_at_event',
        'amiga_wc_hof_snapshots',
        'amiga_wc_hof_present',
    ];
}

function k2_amiga_export_table_exists(mysqli $con, string $table): bool
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

function k2_amiga_export_mysqldump_candidates(): array
{
    $list = ['mysqldump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump'];
    if (DIRECTORY_SEPARATOR === '\\') {
        $list[] = 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe';
        $list[] = 'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysqldump.exe';
    }
    return $list;
}

function k2_amiga_export_resolve_mysqldump(): ?string
{
    foreach (k2_amiga_export_mysqldump_candidates() as $candidate) {
        if ($candidate === 'mysqldump') {
            if (!function_exists('exec')) {
                continue;
            }
            $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
            if (in_array('exec', $disabled, true)) {
                continue;
            }
            $out = [];
            $code = 1;
            @exec('mysqldump --version 2>&1', $out, $code);
            if ($code === 0) {
                return 'mysqldump';
            }
            continue;
        }
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function k2_amiga_export_via_mysqldump(string $host, int $port, string $user, string $pass, string $database, array $tables, string $outPath): array
{
    $fail = static function (string $msg): array {
        return ['ok' => false, 'error' => $msg, 'method' => 'mysqldump', 'bytes' => 0, 'tables' => 0];
    };
    $bin = k2_amiga_export_resolve_mysqldump();
    if ($bin === null) {
        return $fail('mysqldump not available (exec disabled or binary missing).');
    }
    $args = [$bin, '--host=' . $host, '--port=' . (string) $port, '--user=' . $user, '--password=' . $pass,
        '--single-transaction', '--routines=0', '--triggers=0', '--default-character-set=utf8mb4', $database];
    foreach ($tables as $table) {
        $args[] = $table;
    }
    $stderrRedirect = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';
    $cmd = implode(' ', array_map('escapeshellarg', $args)) . ' > ' . escapeshellarg($outPath) . ' ' . $stderrRedirect;
    $output = [];
    $code = 1;
    exec($cmd, $output, $code);
    if ($code !== 0 || !is_file($outPath) || filesize($outPath) < 32) {
        $tail = trim(implode("\n", array_slice($output, -8)));
        if ($tail === '' && is_file($outPath)) {
            $tail = substr((string) file_get_contents($outPath), 0, 500);
        }
        return $fail('mysqldump failed (exit ' . $code . '): ' . $tail);
    }
    return ['ok' => true, 'error' => '', 'method' => 'mysqldump', 'bytes' => (int) filesize($outPath), 'tables' => count($tables)];
}

function k2_amiga_export_sql_literal(mysqli $con, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    return "'" . mysqli_real_escape_string($con, (string) $value) . "'";
}

function k2_amiga_export_via_php(mysqli $con, array $tables, string $outPath): array
{
    $fail = static function (string $msg): array {
        return ['ok' => false, 'error' => $msg, 'method' => 'php', 'bytes' => 0, 'tables' => 0];
    };
    $fh = fopen($outPath, 'wb');
    if ($fh === false) {
        return $fail('Could not open output file for writing.');
    }
    $written = 0;
    $write = static function (string $chunk) use ($fh, &$written): bool {
        $n = fwrite($fh, $chunk);
        if ($n === false) {
            return false;
        }
        $written += $n;
        return true;
    };
    if (!$write("-- ko2amiga staging pull export (PHP)\n-- generated: " . gmdate('Y-m-d H:i:s') . " UTC\n\nSET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\n\n")) {
        fclose($fh);
        return $fail('Write failed.');
    }
    $exported = 0;
    foreach ($tables as $table) {
        if (!k2_amiga_export_table_exists($con, $table)) {
            $write("-- skip missing table {$table}\n");
            continue;
        }
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        $createRes = mysqli_query($con, 'SHOW CREATE TABLE `' . $safe . '`');
        if (!$createRes) {
            fclose($fh);
            return $fail('SHOW CREATE failed for ' . $table . ': ' . mysqli_error($con));
        }
        $createRow = mysqli_fetch_assoc($createRes);
        mysqli_free_result($createRes);
        $ddl = (string) ($createRow['Create Table'] ?? '');
        if ($ddl === '') {
            fclose($fh);
            return $fail('Empty CREATE TABLE for ' . $table);
        }
        if (!$write('DROP TABLE IF EXISTS `' . $safe . "`;\n" . $ddl . ";\n\n")) {
            fclose($fh);
            return $fail('Write failed.');
        }
        $batchSize = 150;
        $offset = 0;
        while (true) {
            $res = mysqli_query($con, 'SELECT * FROM `' . $safe . '` LIMIT ' . (int) $batchSize . ' OFFSET ' . (int) $offset);
            if (!$res) {
                fclose($fh);
                return $fail('SELECT failed for ' . $table . ': ' . mysqli_error($con));
            }
            $rows = [];
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
            mysqli_free_result($res);
            if ($rows === []) {
                break;
            }
            $columns = array_keys($rows[0]);
            $colList = '`' . implode('`,`', $columns) . '`';
            $valueSets = [];
            foreach ($rows as $row) {
                $vals = [];
                foreach ($columns as $col) {
                    $vals[] = k2_amiga_export_sql_literal($con, $row[$col] ?? null);
                }
                $valueSets[] = '(' . implode(',', $vals) . ')';
            }
            if (!$write('INSERT INTO `' . $safe . '` (' . $colList . ') VALUES ' . implode(',', $valueSets) . ";\n")) {
                fclose($fh);
                return $fail('Write failed.');
            }
            $offset += count($rows);
            if (count($rows) < $batchSize) {
                break;
            }
        }
        $write("\n");
        $exported++;
    }
    if (!$write("SET FOREIGN_KEY_CHECKS=1;\nSET UNIQUE_CHECKS=1;\n")) {
        fclose($fh);
        return $fail('Write failed.');
    }
    fclose($fh);
    return ['ok' => true, 'error' => '', 'method' => 'php', 'bytes' => $written, 'tables' => $exported];
}

function k2_amiga_export_write_pull_dump(mysqli $con, string $host, int $port, string $user, string $pass, string $database, array $tables, string $outPath): array
{
    $dir = dirname($outPath);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'error' => 'Could not create export directory.', 'method' => '', 'bytes' => 0, 'tables' => 0];
    }
    $tmp = $outPath . '.tmp';
    if (is_file($tmp)) {
        @unlink($tmp);
    }
    $result = k2_amiga_export_via_mysqldump($host, $port, $user, $pass, $database, $tables, $tmp);
    if (!$result['ok']) {
        $result = k2_amiga_export_via_php($con, $tables, $tmp);
    }
    if (!$result['ok']) {
        if (is_file($tmp)) {
            @unlink($tmp);
        }
        return $result;
    }
    if (!rename($tmp, $outPath)) {
        @unlink($tmp);
        return ['ok' => false, 'error' => 'Could not move temp export into place.', 'method' => $result['method'], 'bytes' => 0, 'tables' => 0];
    }
    $result['bytes'] = (int) filesize($outPath);
    return $result;
}