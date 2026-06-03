<?php
/**
 * Seed ground-truth lobby milestones after zero-derived (entered_arena from JoinDate).
 */
declare(strict_types=1);

require_once __DIR__ . '/ops_prepare_constants.php';
require_once __DIR__ . '/ops_bootstrap.php';

function k2_ops_count_join_date_eligible(mysqli $con): int
{
    $res = $con->query(
        'SELECT COUNT(*) AS n FROM playertable WHERE ' . K2_OPS_JOIN_DATE_VALID_WHERE
    );
    if ($res === false) {
        throw new RuntimeException('count join-date eligible: ' . $con->error);
    }
    $n = (int) $res->fetch_assoc()['n'];
    $res->free();

    return $n;
}

function k2_ops_seed_lobby_milestones(K2OpsWorkTarget $target, bool $dryRun): int
{
    k2_ops_assert_mutate_work_target($target);
    k2_ops_log(
        'seed_lobby_milestones profile=' . $target->profile
        . ' database=' . $target->workDatabase
        . ' dry_run=' . ($dryRun ? 'true' : 'false')
    );

    $con = k2_ops_connect_work($target);
    try {
        if (!k2_ops_table_exists($con, 'player_milestones')) {
            k2_ops_log('player_milestones missing — skip seed_lobby');
            return 0;
        }

        $eligible = k2_ops_count_join_date_eligible($con);
        if ($dryRun) {
            k2_ops_log("seed_lobby dry-run: eligible players={$eligible}");
            return 0;
        }

        if (!$con->query("SET time_zone = '+00:00'")) {
            throw new RuntimeException('seed_lobby time_zone: ' . $con->error);
        }

        $sql = 'INSERT INTO `player_milestones` ('
            . '`player_id`, `milestone_key`, `achieved_at`, `value`, '
            . '`source_kind`, `source_game_id`, `source_league_kind`, `source_period_type`, `source_period_start`) '
            . 'SELECT '
            . '`ID`, \'entered_arena\', `JoinDate`, 1, '
            . '\'lobby\', NULL, NULL, NULL, NULL '
            . 'FROM `playertable` '
            . 'WHERE ' . K2_OPS_JOIN_DATE_VALID_WHERE . ' '
            . 'AND NOT EXISTS ('
            . 'SELECT 1 FROM `player_milestones` pm '
            . 'WHERE pm.`player_id` = `playertable`.`ID` AND pm.`milestone_key` = \'entered_arena\''
            . ')';

        if (!$con->query($sql)) {
            throw new RuntimeException('seed_lobby insert: ' . $con->error);
        }
        $inserted = $con->affected_rows;
        $con->commit();
        k2_ops_log("[OK] seed_lobby_milestones: inserted={$inserted} eligible={$eligible}");

        return $inserted;
    } finally {
        $con->close();
    }
}
