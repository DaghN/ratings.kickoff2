<?php
/**
 * Shared DB bootstrap for milestones staging CLI scripts.
 */
declare(strict_types=1);

function k2_staging_milestones_bootstrap(): mysqli
{
    if (PHP_SAPI !== 'cli') {
        fwrite(STDERR, "CLI only.\n");
        exit(1);
    }

    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
    $configPath = $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
    if (!is_file($configPath)) {
        fwrite(STDERR, "Missing config: {$configPath}\n");
        exit(1);
    }

    include $configPath;

    $con = new mysqli($dbhost, $username, $password, $database, $dbportnum ?? 3306);
    if ($con->connect_errno) {
        fwrite(STDERR, 'DB connect failed: ' . $con->connect_error . PHP_EOL);
        exit(1);
    }

    $con->set_charset('utf8mb4');
    $con->query("SET time_zone = '+00:00'");

    return $con;
}

function k2_staging_split_sql_statements(string $sql): array
{
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
    $parts = preg_split('/;\s*\R/', trim($sql));
    if ($parts === false) {
        return [];
    }
    $out = [];
    foreach ($parts as $part) {
        $lines = preg_split('/\R/', trim($part)) ?: [];
        $body = [];
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            $body[] = $line;
        }
        $statement = trim(implode("\n", $body));
        if ($statement !== '') {
            $out[] = $statement;
        }
    }
    return $out;
}

function k2_staging_exec_sql(mysqli $con, string $sql, string $label): void
{
    $statements = k2_staging_split_sql_statements($sql);
    if ($statements === []) {
        fwrite(STDERR, "{$label}: no statements found.\n");
        exit(1);
    }

    $n = count($statements);
    foreach ($statements as $i => $statement) {
        $step = $n > 1 ? "{$label} (" . ($i + 1) . "/{$n})" : $label;
        if (!$con->query($statement)) {
            fwrite(STDERR, "{$step} failed: " . $con->error . PHP_EOL);
            fwrite(STDERR, "Statement starts: " . substr($statement, 0, 120) . "...\n");
            exit(1);
        }
        if ($result = $con->store_result()) {
            $result->free();
        }
    }
}

function k2_staging_exec_sql_file(mysqli $con, string $path, string $label): void
{
    if (!is_file($path)) {
        fwrite(STDERR, "Missing SQL file: {$path}\n");
        exit(1);
    }
    $sql = file_get_contents($path);
    if ($sql === false || $sql === '') {
        fwrite(STDERR, "Empty or unreadable SQL: {$path}\n");
        exit(1);
    }
    echo "-> {$label}\n";
    k2_staging_exec_sql($con, $sql, $label);
}

function k2_staging_table_exists(mysqli $con, string $table): bool
{
    $table = $con->real_escape_string($table);
    $res = $con->query("SHOW TABLES LIKE '{$table}'");
    if ($res === false) {
        return false;
    }
    $ok = $res->num_rows > 0;
    $res->free();
    return $ok;
}
