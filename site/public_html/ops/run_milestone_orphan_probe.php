<?php
/**
 * Diagnose orphan milestone unlocks and holder_count drift (SCH-021).
 *
 * Orphan unlock = player_milestones row whose player_id has no playertable row.
 * Orphans are expected (deleted accounts); they count toward holder_count.
 *
 *   php site/public_html/ops/run_milestone_orphan_probe.php --target staging-work
 *   php site/public_html/ops/run_milestone_orphan_probe.php --target local-work
 *
 * Read-only.
 *
 * @see docs/website-data-contract.md § milestone_definitions.holder_count
 */
declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once __DIR__ . '/includes/ops_bootstrap.php';

k2_ops_require_cli();

$targetName = 'local-work';
for ($i = 1, $n = count($argv); $i < $n; $i++) {
    if ($argv[$i] === '--target' && isset($argv[$i + 1])) {
        $targetName = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--target=')) {
        $targetName = substr($argv[$i], 9);
    }
}

$target = k2_ops_load_work_target($targetName);
$con = k2_ops_connect_work($target);

k2_ops_log('milestone_orphan_probe database=' . $target->workDatabase);

if (!k2_ops_table_exists($con, 'player_milestones')) {
    fwrite(STDERR, "player_milestones missing\n");
    exit(1);
}

$orphanRows = [];
$res = $con->query(
    <<<'SQL'
SELECT
  pm.player_id,
  pm.milestone_key,
  pm.achieved_at,
  pm.value,
  pm.source_kind,
  pm.source_game_id
FROM player_milestones pm
LEFT JOIN playertable p ON p.ID = pm.player_id
WHERE p.ID IS NULL
ORDER BY pm.player_id, pm.milestone_key
SQL
);
if ($res === false) {
    fwrite(STDERR, 'orphan list: ' . $con->error . PHP_EOL);
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    $orphanRows[] = $row;
}
$res->free();

$orphanPlayers = [];
foreach ($orphanRows as $row) {
    $pid = (int) $row['player_id'];
    if (!isset($orphanPlayers[$pid])) {
        $orphanPlayers[$pid] = ['unlock_rows' => 0, 'distinct_keys' => []];
    }
    ++$orphanPlayers[$pid]['unlock_rows'];
    $orphanPlayers[$pid]['distinct_keys'][(string) $row['milestone_key']] = true;
}

echo "=== Orphan unlock rows (player_id not in playertable — expected for deleted accounts) ===\n";
echo 'total_rows=' . count($orphanRows) . ' players=' . count($orphanPlayers) . "\n\n";

if ($orphanRows === []) {
    echo "(none)\n\n";
} else {
    printf("%-8s %-32s %-20s %-6s %-8s\n", 'player', 'milestone_key', 'achieved_at', 'value', 'source');
    foreach ($orphanRows as $row) {
        printf(
            "%-8d %-32s %-20s %-6s %-8s\n",
            (int) $row['player_id'],
            (string) $row['milestone_key'],
            (string) $row['achieved_at'],
            (string) $row['value'],
            (string) ($row['source_kind'] ?? '')
        );
    }
    echo "\n";
}

echo "=== Orphan players — rated game counts (ground truth still references ID) ===\n";
foreach ($orphanPlayers as $pid => $meta) {
    $stmt = $con->prepare(
        'SELECT COUNT(*) AS n FROM ratedresults WHERE idA = ? OR idB = ?'
    );
    if ($stmt === false) {
        continue;
    }
    $stmt->bind_param('ii', $pid, $pid);
    $stmt->execute();
    $gameRes = $stmt->get_result();
    $games = $gameRes ? (int) ($gameRes->fetch_assoc()['n'] ?? 0) : 0;
    if ($gameRes) {
        $gameRes->free();
    }
    $stmt->close();

    $distinctKeys = count($meta['distinct_keys']);
    echo "player_id={$pid} unlock_rows={$meta['unlock_rows']} distinct_milestone_keys={$distinctKeys} rated_games={$games}\n";
}
echo "\n";

if (k2_ops_column_exists($con, 'milestone_definitions', 'holder_count')) {
    $drift = [];
    $res = $con->query(
        <<<'SQL'
SELECT
  d.milestone_key,
  d.holder_count AS stored_count,
  COALESCE(h.holders, 0) AS unlock_row_count,
  CAST(d.holder_count AS SIGNED) - CAST(COALESCE(h.holders, 0) AS SIGNED) AS stored_minus_unlock_rows
FROM milestone_definitions d
LEFT JOIN (
  SELECT pm.milestone_key, COUNT(*) AS holders
  FROM player_milestones pm
  GROUP BY pm.milestone_key
) h ON h.milestone_key = d.milestone_key
WHERE d.holder_count <> COALESCE(h.holders, 0)
ORDER BY ABS(CAST(d.holder_count AS SIGNED) - CAST(COALESCE(h.holders, 0) AS SIGNED)) DESC, d.milestone_key
SQL
    );
    if ($res !== false) {
        while ($row = $res->fetch_assoc()) {
            $drift[] = $row;
        }
        $res->free();
    }

    echo "=== holder_count drift (stored vs unlock row count — same rule as verify) ===\n";
    echo 'mismatch_keys=' . count($drift) . "\n\n";

    if ($drift === []) {
        echo "(no drift)\n\n";
    } else {
        printf("%-32s %8s %8s %8s\n", 'milestone_key', 'stored', 'unlock', 'delta');
        foreach ($drift as $row) {
            printf(
                "%-32s %8d %8d %8d\n",
                (string) $row['milestone_key'],
                (int) $row['stored_count'],
                (int) $row['unlock_row_count'],
                (int) $row['stored_minus_unlock_rows']
            );
        }
        echo "\n";
    }

    $orphanKeyCount = 0;
    $res = $con->query(
        <<<'SQL'
SELECT COUNT(DISTINCT pm.milestone_key) AS c
FROM player_milestones pm
LEFT JOIN playertable p ON p.ID = pm.player_id
WHERE p.ID IS NULL
SQL
    );
    if ($res !== false) {
        $orphanKeyCount = (int) ($res->fetch_assoc()['c'] ?? 0);
        $res->free();
    }

    echo "=== Note ===\n";
    echo "Orphan unlocks count toward holder_count (server history). ";
    echo "distinct_milestone_keys_with_orphan_unlock={$orphanKeyCount}\n";
    echo "Drift means incremental bumps diverged from unlock rows — investigate bump path, not orphans.\n";
}

$con->close();
exit(0);
