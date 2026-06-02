<?php
/**
 * Work DB prepare target profiles (see site/config/work-targets.ini.example).
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_prepare_constants.php';

final class K2OpsWorkTarget
{
    public function __construct(
        public readonly string $profile,
        public readonly string $workDatabase,
        public readonly string $baselineDatabase,
        public readonly string $host,
        public readonly int $port,
        public readonly string $user,
        public readonly string $password,
    ) {
    }
}

function k2_ops_repo_root(): string
{
    static $root = null;
    if ($root === null) {
        $root = dirname(__DIR__, 4);
    }
    return $root;
}

function k2_ops_load_work_target(string $profile): K2OpsWorkTarget
{
    if (!isset(K2_OPS_DEFAULT_PROFILES[$profile])) {
        $known = implode(', ', array_keys(K2_OPS_DEFAULT_PROFILES));
        fwrite(STDERR, "Unknown target profile {$profile}. Expected one of: {$known}\n");
        exit(1);
    }

    $data = K2_OPS_DEFAULT_PROFILES[$profile];
    $iniPath = k2_ops_repo_root() . '/site/config/work-targets.ini';
    if (is_file($iniPath)) {
        $ini = parse_ini_file($iniPath, true, INI_SCANNER_TYPED);
        if (is_array($ini) && isset($ini[$profile]) && is_array($ini[$profile])) {
            $section = $ini[$profile];
            foreach (['work_database', 'baseline_database', 'host', 'user', 'password'] as $key) {
                if (!empty($section[$key])) {
                    $data[$key] = (string) $section[$key];
                }
            }
            if (!empty($section['port'])) {
                $data['port'] = (int) $section['port'];
            }
        }
    }

    return new K2OpsWorkTarget(
        $profile,
        (string) $data['work_database'],
        (string) $data['baseline_database'],
        (string) $data['host'],
        (int) $data['port'],
        (string) $data['user'],
        (string) ($data['password'] ?? ''),
    );
}

function k2_ops_assert_refresh_target(K2OpsWorkTarget $target): void
{
    if ($target->workDatabase === K2_OPS_PROTECTED_DEV_DATABASE) {
        fwrite(STDERR, 'Refusing refresh: work database must not be ' . K2_OPS_PROTECTED_DEV_DATABASE . ".\n");
        exit(1);
    }
    if ($target->baselineDatabase === K2_OPS_PROTECTED_DEV_DATABASE) {
        fwrite(STDERR, 'Refusing refresh: baseline must not be ' . K2_OPS_PROTECTED_DEV_DATABASE . ".\n");
        exit(1);
    }
    if (in_array($target->workDatabase, K2_OPS_PROTECTED_BASELINE_DATABASES, true)) {
        fwrite(STDERR, "Refusing refresh: cannot replace protected baseline DB {$target->workDatabase}.\n");
        exit(1);
    }
    if ($target->workDatabase === $target->baselineDatabase) {
        fwrite(STDERR, "Refusing refresh: work and baseline database names must differ.\n");
        exit(1);
    }
}

function k2_ops_assert_mutate_work_target(K2OpsWorkTarget $target): void
{
    if ($target->workDatabase === K2_OPS_PROTECTED_DEV_DATABASE) {
        fwrite(STDERR, 'Refusing: work database must not be ' . K2_OPS_PROTECTED_DEV_DATABASE . ".\n");
        exit(1);
    }
    if (in_array($target->workDatabase, K2_OPS_PROTECTED_BASELINE_DATABASES, true)) {
        fwrite(STDERR, "Refusing: cannot mutate protected baseline {$target->workDatabase}.\n");
        exit(1);
    }
}

function k2_ops_database_exists(K2OpsWorkTarget $target, string $database): bool
{
    $con = new mysqli($target->host, $target->user, $target->password, '', $target->port);
    if ($con->connect_errno) {
        fwrite(STDERR, 'DB connect failed: ' . $con->connect_error . PHP_EOL);
        exit(1);
    }
    $db = $con->real_escape_string($database);
    $res = $con->query(
        "SELECT COUNT(*) AS n FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{$db}'"
    );
    $ok = false;
    if ($res !== false) {
        $row = $res->fetch_assoc();
        $ok = (int) ($row['n'] ?? 0) === 1;
        $res->free();
    }
    $con->close();
    return $ok;
}
