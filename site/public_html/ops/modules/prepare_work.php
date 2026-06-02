<?php
/**
 * Work DB prepare — refresh → migrate → seed catalog → zero derived.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/ops_prepare_constants.php';
require_once __DIR__ . '/../includes/ops_work_target.php';
require_once __DIR__ . '/../includes/ops_bootstrap.php';
require_once __DIR__ . '/../includes/ops_shell.php';
require_once __DIR__ . '/../includes/ops_reset_universe.php';

function k2_ops_seed_milestone_definitions(K2OpsWorkTarget $target, bool $dryRun): void
{
    k2_ops_assert_mutate_work_target($target);
    $seedPath = k2_ops_repo_root() . '/data/milestones_definitions_seed.json';
    if (!is_file($seedPath)) {
        fwrite(STDERR, "Missing seed file: {$seedPath}\n");
        exit(1);
    }
    $json = file_get_contents($seedPath);
    if ($json === false) {
        fwrite(STDERR, "Cannot read {$seedPath}\n");
        exit(1);
    }
    $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $rows = $payload['definitions'];
    $expected = (int) ($payload['milestone_count'] ?? count($rows));

    k2_ops_log(
        'seed_milestone_definitions profile=' . $target->profile
        . ' rows=' . count($rows)
        . ' dry_run=' . ($dryRun ? 'true' : 'false')
    );
    if ($dryRun) {
        return;
    }

    $con = k2_ops_connect_work($target);
    $con->autocommit(false);
    try {
        if (!k2_ops_table_exists($con, 'milestone_definitions')) {
            fwrite(STDERR, "milestone_definitions table missing — run migrate-work before seed-catalog.\n");
            exit(1);
        }
        if (!$con->query('TRUNCATE TABLE milestone_definitions')) {
            fwrite(STDERR, 'TRUNCATE milestone_definitions: ' . $con->error . PHP_EOL);
            exit(1);
        }
        $stmt = $con->prepare(
            'INSERT INTO milestone_definitions (
                milestone_key, display_name, tier_band, chart_token,
                rule_short, description, sort_order, icon
            ) VALUES (?, ?, ?, ?, ?, NULL, ?, NULL)'
        );
        if ($stmt === false) {
            fwrite(STDERR, 'prepare INSERT: ' . $con->error . PHP_EOL);
            exit(1);
        }
        $sort = 1;
        foreach ($rows as $row) {
            $seedTier = (string) $row['tier_band'];
            if (!isset(K2_OPS_TIER_BAND_PRODUCT[$seedTier])) {
                fwrite(STDERR, "Unknown tier_band in seed: {$seedTier}\n");
                exit(1);
            }
            $tier = K2_OPS_TIER_BAND_PRODUCT[$seedTier];
            $key = (string) $row['milestone_key'];
            $name = (string) $row['display_name'];
            $token = (string) $row['chart_token'];
            $rule = (string) $row['rule_short'];
            $stmt->bind_param('sssssi', $key, $name, $tier, $token, $rule, $sort);
            if (!$stmt->execute()) {
                fwrite(STDERR, 'INSERT milestone_definitions: ' . $stmt->error . PHP_EOL);
                exit(1);
            }
            $sort++;
        }
        $stmt->close();
        $res = $con->query('SELECT COUNT(*) AS n FROM milestone_definitions');
        $n = $res ? (int) $res->fetch_assoc()['n'] : 0;
        if ($res) {
            $res->free();
        }
        $con->commit();
    } catch (Throwable $e) {
        $con->rollback();
        throw $e;
    } finally {
        $con->close();
    }

    if ($n !== count($rows)) {
        fwrite(STDERR, "milestone_definitions: expected " . count($rows) . " rows, got {$n}\n");
        exit(1);
    }
    if ($n !== $expected) {
        k2_ops_log("WARNING: Seed milestone_count={$expected} but loaded {$n} rows");
    }
    k2_ops_log("[OK] milestone_definitions seeded: {$n} rows");
}

function k2_ops_prepare_full(K2OpsWorkTarget $target, bool $dryRun): void
{
    k2_ops_log(
        '=== prepare full (refresh → migrate → seed catalog → zero derived) profile='
        . $target->profile . ' ==='
    );
    k2_ops_refresh_work($target, $dryRun);
    k2_ops_migrate_work($target, $dryRun);
    k2_ops_seed_milestone_definitions($target, $dryRun);
    k2_ops_zero_derived($target, $dryRun);
}

function k2_ops_prepare_fast(K2OpsWorkTarget $target, bool $dryRun): void
{
    k2_ops_log('=== prepare fast (zero derived only) profile=' . $target->profile . ' ===');
    k2_ops_zero_derived($target, $dryRun);
}
