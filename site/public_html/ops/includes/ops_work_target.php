<?php
/**
 * Work DB prepare target profiles (ops/config/work-targets.ini.example; legacy site/config/).
 *
 * Built-in names live in K2_OPS_DEFAULT_PROFILES. Extra server profiles (e.g. live-game) may
 * exist only in gitignored work-targets.ini — loader accepts either source.
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_prepare_constants.php';
require_once __DIR__ . '/ops_paths.php';

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

/**
 * @return array<string, array<string, mixed>>|null
 */
function k2_ops_parse_work_targets_ini(): ?array
{
    $iniPath = k2_ops_work_targets_ini_path();
    if (!is_file($iniPath)) {
        return null;
    }
    $ini = parse_ini_file($iniPath, true, INI_SCANNER_TYPED);
    if (!is_array($ini)) {
        return null;
    }

    return $ini;
}

function k2_ops_load_work_target(string $profile): K2OpsWorkTarget
{
    $ini = k2_ops_parse_work_targets_ini();
    $iniSection = null;
    if ($ini !== null && isset($ini[$profile]) && is_array($ini[$profile])) {
        $iniSection = $ini[$profile];
    }

    $hasDefault = isset(K2_OPS_DEFAULT_PROFILES[$profile]);
    if (!$hasDefault && $iniSection === null) {
        $known = implode(', ', array_keys(K2_OPS_DEFAULT_PROFILES));
        fwrite(stderr(), "Unknown target profile {$profile}. Expected a built-in ({$known}) or a [{$profile}] section in work-targets.ini.\n");
        exit(1);
    }

    $data = $hasDefault
        ? K2_OPS_DEFAULT_PROFILES[$profile]
        : [
            'work_database' => '',
            'baseline_database' => '',
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => '',
            'password' => '',
        ];

    if ($iniSection !== null) {
        foreach (['work_database', 'baseline_database', 'host', 'user'] as $key) {
            if (isset($iniSection[$key]) && (string) $iniSection[$key] !== '') {
                $data[$key] = (string) $iniSection[$key];
            }
        }
        if (array_key_exists('password', $iniSection)) {
            $data['password'] = (string) $iniSection['password'];
        }
        if (isset($iniSection['port']) && $iniSection['port'] !== '' && $iniSection['port'] !== null) {
            $data['port'] = (int) $iniSection['port'];
        }
    }

    if (!$hasDefault) {
        foreach (['work_database', 'baseline_database', 'host', 'user'] as $req) {
            if (($data[$req] ?? '') === '') {
                fwrite(stderr(), "Ini-only profile {$profile} requires {$req} in work-targets.ini.\n");
                exit(1);
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
        fwrite(stderr(), 'Refusing refresh: work database must not be ' . K2_OPS_PROTECTED_DEV_DATABASE . ".\n");
        exit(1);
    }
    if ($target->baselineDatabase === K2_OPS_PROTECTED_DEV_DATABASE) {
        fwrite(stderr(), 'Refusing refresh: baseline must not be ' . K2_OPS_PROTECTED_DEV_DATABASE . ".\n");
        exit(1);
    }
    if (in_array($target->workDatabase, K2_OPS_PROTECTED_BASELINE_DATABASES, true)) {
        fwrite(stderr(), "Refusing refresh: cannot replace protected baseline DB {$target->workDatabase}.\n");
        exit(1);
    }
    if ($target->workDatabase === $target->baselineDatabase) {
        fwrite(stderr(), "Refusing refresh: work and baseline database names must differ.\n");
        exit(1);
    }
}

function k2_ops_is_signoff_work_profile(string $profile): bool
{
    return in_array($profile, ['local-work', 'staging-work'], true);
}

/**
 * Sign-off work DBs (ko2unity_work / kooldb1) are filled only via prepare + simul — not batch repair verbs.
 */
function k2_ops_reject_signoff_work_batch_repair(string $verb, K2OpsWorkTarget $target): void
{
    if (!k2_ops_is_signoff_work_profile($target->profile)) {
        return;
    }
    if (!in_array($verb, ['rebuild-all', 'rebuild-aggregates'], true)) {
        return;
    }
    fwrite(stderr(), "Refusing {$verb} on sign-off work profile {$target->profile} ({$target->workDatabase}).\n");
    fwrite(stderr(), "Work sign-off: zero-derived → run_ops_sim.php → run_verify_ops_sim.php.\n");
    fwrite(stderr(), "See docs/work-db-prepare.md §1.5. Batch repair: --target local-dev only.\n");
    exit(1);
}

function k2_ops_assert_mutate_work_target(K2OpsWorkTarget $target): void
{
    if ($target->workDatabase === K2_OPS_PROTECTED_DEV_DATABASE) {
        fwrite(stderr(), 'Refusing: work database must not be ' . K2_OPS_PROTECTED_DEV_DATABASE . ".\n");
        exit(1);
    }
    if (in_array($target->workDatabase, K2_OPS_PROTECTED_BASELINE_DATABASES, true)) {
        fwrite(stderr(), "Refusing: cannot mutate protected baseline {$target->workDatabase}.\n");
        exit(1);
    }
}

function k2_ops_database_exists(K2OpsWorkTarget $target, string $database): bool
{
    $con = new mysqli($target->host, $target->user, $target->password, '', $target->port);
    if ($con->connect_errno) {
        fwrite(stderr(), 'DB connect failed: ' . $con->connect_error . PHP_EOL);
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