<?php
/**
 * Refresh work via PowerShell; migrate via ops/sql/migrations (PHP).
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_migrate.php';

require_once __DIR__ . '/ops_work_target.php';
require_once __DIR__ . '/ops_bootstrap.php';

function k2_ops_find_mysql_exe(): string
{
    $candidates = [
        'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe',
        'C:\\laragon\\bin\\mysql\\mysql-8.0.30-winx64\\bin\\mysql.exe',
        'C:\\laragon\\bin\\mariadb\\mariadb-11.4.2-winx64\\bin\\mysql.exe',
        'C:\\laragon\\bin\\mariadb\\mariadb-10.11.8-winx64\\bin\\mysql.exe',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    fwrite(STDERR, "mysql.exe not found under C:\\laragon (docs/LOCAL_DEV.md).\n");
    exit(1);
}

function k2_ops_find_mysqldump_exe(): string
{
    $mysql = k2_ops_find_mysql_exe();
    $dump = dirname($mysql) . '\\mysqldump.exe';
    if (!is_file($dump)) {
        fwrite(STDERR, "mysqldump.exe not found beside {$mysql}\n");
        exit(1);
    }
    return $dump;
}

/** @param list<string> $args */
function k2_ops_run_command(array $args, ?string $cwd = null): void
{
    $cmd = implode(' ', array_map('escapeshellarg', $args));
    k2_ops_log('Running: ' . $cmd);
    $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($cmd, $descriptor, $pipes, $cwd ?? k2_ops_repo_root());
    if (!is_resource($proc)) {
        fwrite(STDERR, "Failed to start: {$cmd}\n");
        exit(1);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($stdout !== false && $stdout !== '') {
        echo $stdout;
    }
    if ($stderr !== false && $stderr !== '') {
        fwrite(STDERR, $stderr);
    }
    if ($code !== 0) {
        fwrite(STDERR, "Command failed (exit {$code}): {$cmd}\n");
        exit(1);
    }
}

function k2_ops_refresh_work(K2OpsWorkTarget $target, bool $dryRun): void
{
    k2_ops_assert_refresh_target($target);
    if (!k2_ops_database_exists($target, $target->baselineDatabase)) {
        fwrite(STDERR, "Baseline database {$target->baselineDatabase} missing. Run setup_local_prod_sandbox.ps1 first.\n");
        exit(1);
    }

    k2_ops_log(
        "refresh_work profile={$target->profile} clone {$target->baselineDatabase} -> {$target->workDatabase} dry_run="
        . ($dryRun ? 'true' : 'false')
    );
    if ($dryRun) {
        return;
    }

    $mysql = k2_ops_find_mysql_exe();
    $mysqldump = k2_ops_find_mysqldump_exe();
    $work = $target->workDatabase;
    $baseline = $target->baselineDatabase;

    $dropSql = "DROP DATABASE IF EXISTS `{$work}`; CREATE DATABASE `{$work}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;";
    $mysqlBase = [$mysql, '-u', $target->user];
    if ($target->password !== '') {
        $mysqlBase[] = '-p' . $target->password;
    }
    k2_ops_run_command(array_merge($mysqlBase, ['-e', $dropSql]));

    $dumpCmd = array_merge([$mysqldump, '-u', $target->user], $target->password !== '' ? ['-p' . $target->password] : [], [
        '--single-transaction', '--no-create-db', '--routines', '--events', $baseline,
    ]);
    $loadCmd = array_merge($mysqlBase, [$work]);

    // Temp file avoids fragile pipe wiring on Windows (Python Popen pipe works; PHP proc_open does not).
    $tmpFile = tempnam(sys_get_temp_dir(), 'k2_refresh_');
    if ($tmpFile === false) {
        fwrite(STDERR, "tempnam failed\n");
        exit(1);
    }
    try {
        $dumpLine = implode(' ', array_map('escapeshellarg', $dumpCmd));
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['file', $tmpFile, 'w'],
            2 => ['pipe', 'w'],
        ];
        $dumpProc = proc_open($dumpLine, $descriptor, $dumpPipes);
        if (!is_resource($dumpProc)) {
            fwrite(STDERR, "mysqldump failed to start\n");
            exit(1);
        }
        if (isset($dumpPipes[0]) && is_resource($dumpPipes[0])) {
            fclose($dumpPipes[0]);
        }
        $dumpErr = stream_get_contents($dumpPipes[2]);
        fclose($dumpPipes[2]);
        $dumpCode = proc_close($dumpProc);
        if ($dumpErr !== false && $dumpErr !== '') {
            fwrite(STDERR, $dumpErr);
        }
        if ($dumpCode !== 0) {
            fwrite(STDERR, "mysqldump failed (exit {$dumpCode})\n");
            exit(1);
        }

        $loadLine = implode(' ', array_map('escapeshellarg', $loadCmd));
        $descriptor = [
            0 => ['file', $tmpFile, 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $loadProc = proc_open($loadLine, $descriptor, $loadPipes);
        if (!is_resource($loadProc)) {
            fwrite(STDERR, "mysql load failed to start\n");
            exit(1);
        }
        if (isset($loadPipes[0]) && is_resource($loadPipes[0])) {
            fclose($loadPipes[0]);
        }
        $loadOut = stream_get_contents($loadPipes[1]);
        $loadErr = stream_get_contents($loadPipes[2]);
        fclose($loadPipes[1]);
        fclose($loadPipes[2]);
        $loadCode = proc_close($loadProc);
        if ($loadOut !== false && $loadOut !== '') {
            echo $loadOut;
        }
        if ($loadErr !== false && $loadErr !== '') {
            fwrite(STDERR, $loadErr);
        }
        if ($loadCode !== 0) {
            fwrite(STDERR, "mysql load failed (exit {$loadCode})\n");
            exit(1);
        }
    } finally {
        if (is_file($tmpFile)) {
            @unlink($tmpFile);
        }
    }

    $verifyCmd = array_merge($mysqlBase, ['-N', '-e', "SELECT COUNT(*) FROM `{$work}`.ratedresults;"]);
    $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open(implode(' ', array_map('escapeshellarg', $verifyCmd)), $descriptor, $pipes);
    $count = '';
    if (is_resource($proc)) {
        fclose($pipes[0]);
        $count = trim((string) stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
    }
    k2_ops_log("[OK] {$work} ready — ratedresults rows: {$count}");
}

function k2_ops_migrate_work(K2OpsWorkTarget $target, bool $dryRun): void
{
    k2_ops_log("migrate_work profile={$target->profile} database={$target->workDatabase} dry_run=" . ($dryRun ? 'true' : 'false'));
    if ($dryRun) {
        return;
    }

    k2_ops_apply_migrations($target);
}
