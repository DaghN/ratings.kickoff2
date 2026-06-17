<?php
/**
 * Report players whose ratedresults snapshot names differ from playertable.Name
 * or who appear under more than one snapshot spelling.
 *
 * Usage (repo root):
 *   php scripts/oneoff/player_name_renames_report.php
 *   php scripts/oneoff/player_name_renames_report.php --csv
 */
declare(strict_types=1);

$asCsv = in_array('--csv', $argv ?? [], true);

$configPath = dirname(__DIR__, 2) . '/site/config/ko2unitydb_config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Config not found: $configPath\n");
    exit(1);
}

include $configPath;

$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, 'DB connect failed: ' . $con->connect_error . "\n");
    exit(1);
}
$con->set_charset('utf8mb4');

$sql = <<<'SQL'
SELECT
    snap.player_id,
    COALESCE(p.Name, CONCAT('#', snap.player_id)) AS current_name,
    GROUP_CONCAT(DISTINCT snap.hist_name ORDER BY snap.hist_name SEPARATOR ' | ') AS snapshot_names,
    COUNT(DISTINCT snap.hist_name) AS distinct_snapshot_names,
    MIN(snap.first_seen) AS first_game_at,
    MAX(snap.last_seen) AS last_game_at,
    SUM(snap.game_rows) AS game_rows
FROM (
    SELECT
        idA AS player_id,
        TRIM(NameA) AS hist_name,
        MIN(`Date`) AS first_seen,
        MAX(`Date`) AS last_seen,
        COUNT(*) AS game_rows
    FROM ratedresults
    WHERE idA IS NOT NULL AND idA > 0 AND NameA IS NOT NULL AND TRIM(NameA) <> ''
    GROUP BY idA, TRIM(NameA)
    UNION ALL
    SELECT
        idB AS player_id,
        TRIM(NameB) AS hist_name,
        MIN(`Date`) AS first_seen,
        MAX(`Date`) AS last_seen,
        COUNT(*) AS game_rows
    FROM ratedresults
    WHERE idB IS NOT NULL AND idB > 0 AND NameB IS NOT NULL AND TRIM(NameB) <> ''
    GROUP BY idB, TRIM(NameB)
) AS snap
LEFT JOIN playertable p ON p.ID = snap.player_id
GROUP BY snap.player_id, current_name
HAVING distinct_snapshot_names > 1
    OR SUM(CASE WHEN snap.hist_name <> COALESCE(p.Name, '') THEN 1 ELSE 0 END) > 0
ORDER BY snap.player_id ASC
SQL;

$res = $con->query($sql);
if ($res === false) {
    fwrite(STDERR, 'Query failed: ' . $con->error . "\n");
    exit(1);
}

$rows = [];
while ($row = $res->fetch_assoc()) {
    $current = (string) $row['current_name'];
    $snapshots = array_values(array_filter(array_map('trim', explode('|', (string) $row['snapshot_names']))));
    $former = array_values(array_filter($snapshots, static fn (string $n): bool => $n !== $current));
    $rows[] = [
        'player_id' => (int) $row['player_id'],
        'current_name' => $current,
        'snapshot_names' => implode(' | ', $snapshots),
        'former_names' => implode(' | ', $former),
        'distinct_snapshot_names' => (int) $row['distinct_snapshot_names'],
        'first_game_at' => (string) $row['first_game_at'],
        'last_game_at' => (string) $row['last_game_at'],
        'game_rows' => (int) $row['game_rows'],
    ];
}
$res->free();
$con->close();

if ($asCsv) {
    $out = fopen('php://output', 'w');
    fputcsv($out, array_keys($rows[0] ?? [
        'player_id' => '',
        'current_name' => '',
        'snapshot_names' => '',
        'former_names' => '',
        'distinct_snapshot_names' => '',
        'first_game_at' => '',
        'last_game_at' => '',
        'game_rows' => '',
    ]));
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit(0);
}

echo "Player name rename report (" . count($rows) . " players)\n";
echo str_repeat('-', 72) . "\n";
if ($rows === []) {
    echo "No mismatches between playertable and ratedresults snapshots.\n";
    exit(0);
}

foreach ($rows as $row) {
    echo 'ID ' . $row['player_id'] . '  current: ' . $row['current_name'] . "\n";
    echo '  snapshots: ' . $row['snapshot_names'] . "\n";
    if ($row['former_names'] !== '') {
        echo '  former (not current): ' . $row['former_names'] . "\n";
    }
    echo '  games (name slices): ' . $row['game_rows']
        . '  first: ' . $row['first_game_at']
        . '  last: ' . $row['last_game_at'] . "\n\n";
}
