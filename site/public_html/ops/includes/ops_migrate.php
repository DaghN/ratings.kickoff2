<?php
/**
 * Apply numbered SCH migrations from ops/sql/migrations/ (idempotent re-apply-all).
 */
declare(strict_types=1);

function k2_ops_migrations_directory(): string
{
    return dirname(__DIR__) . '/sql/migrations';
}

/**
 * @return list<string> Absolute paths to *.sql sorted by basename
 */
function k2_ops_list_migration_files(): array
{
    $dir = k2_ops_migrations_directory();
    $files = glob($dir . '/*.sql');
    if ($files === false) {
        return [];
    }
    sort($files, SORT_STRING);

    return $files;
}

function k2_ops_apply_migrations(K2OpsWorkTarget $target): void
{
    k2_ops_assert_mutate_work_target($target);

    $files = k2_ops_list_migration_files();
    if ($files === []) {
        k2_ops_log('[OK] No migrations in ops/sql/migrations/.');
        return;
    }

    k2_ops_log('Applying ' . count($files) . ' migration(s) to ' . $target->workDatabase . '...');
    foreach ($files as $path) {
        k2_ops_log('  - ' . basename($path));
    }

    $con = k2_ops_connect_work($target);
    try {
        foreach ($files as $path) {
            $name = basename($path);
            k2_ops_log('  -> ' . $name);
            $sql = file_get_contents($path);
            if ($sql === false) {
                fwrite(stderr(), "Cannot read {$path}\n");
                exit(1);
            }
            $batch = "SET time_zone = '+00:00';\n" . $sql;
            if (!$con->multi_query($batch)) {
                fwrite(stderr(), "Failed {$name}: {$con->error}\n");
                exit(1);
            }
            do {
                if ($result = $con->store_result()) {
                    $result->free();
                }
                if (!$con->more_results()) {
                    break;
                }
                if (!$con->next_result()) {
                    fwrite(stderr(), "Failed {$name}: {$con->error}\n");
                    exit(1);
                }
            } while (true);
        }
    } finally {
        $con->close();
    }

    k2_ops_log('[OK] ops schema migrations applied.');
}
