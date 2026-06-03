<?php
/**
 * @deprecated Superseded by site/public_html/ops/run_prepare.php seed-catalog (REP-014).
 *
 * Work / Laragon dev:
 *   php site/public_html/ops/run_prepare.php seed-catalog --target local-work
 *   php site/public_html/ops/run_prepare.php seed-catalog --target local-dev
 *
 * Staging (until ops + repo data/ are on server): this file reloads from
 * public_html/staging-data/milestones_definitions_seed.json (WinSCP copy of seed).
 *
 * Historical CLI after staging-sql/010:
 *   php staging-scripts/load_milestone_definitions.php
 */
declare(strict_types=1);

require_once __DIR__ . '/_staging_milestones_bootstrap.php';

$con = k2_staging_milestones_bootstrap();

if (!k2_staging_table_exists($con, 'milestone_definitions')) {
    fwrite(STDERR, "Apply staging-sql/010_milestone_definitions.sql first.\n");
    exit(1);
}

$seedPath = dirname(__DIR__) . '/staging-data/milestones_definitions_seed.json';
if (!is_file($seedPath)) {
    fwrite(STDERR, "Missing seed: {$seedPath}\n");
    exit(1);
}

$payload = json_decode(file_get_contents($seedPath), true, 512, JSON_THROW_ON_ERROR);
$rows = $payload['definitions'] ?? null;
if (!is_array($rows)) {
    fwrite(STDERR, "Invalid seed: missing definitions array.\n");
    exit(1);
}

$tierMap = [
    'aspirational' => 'aspirational',
    'dedicated' => 'veteran',
    'accomplished' => 'key',
    'legendary' => 'legendary',
];

$dbRes = $con->query('SELECT DATABASE() AS db');
$dbName = ($dbRes && ($r = $dbRes->fetch_assoc())) ? (string) $r['db'] : '?';
if ($dbRes) {
    $dbRes->free();
}
fwrite(STDERR, "Note: prefer ops/run_prepare.php seed-catalog when available.\n");
echo "REP-014: loading milestone_definitions on {$dbName}...\n";
$con->query('TRUNCATE TABLE milestone_definitions');

$stmt = $con->prepare(
    'INSERT INTO milestone_definitions (
        milestone_key, display_name, tier_band, chart_token,
        rule_short, description, sort_order, icon
    ) VALUES (?, ?, ?, ?, ?, NULL, ?, NULL)'
);
if ($stmt === false) {
    fwrite(STDERR, 'Prepare failed: ' . $con->error . PHP_EOL);
    exit(1);
}

foreach ($rows as $i => $row) {
    $seedTier = (string) ($row['tier_band'] ?? '');
    $tier = $tierMap[$seedTier] ?? null;
    if ($tier === null) {
        fwrite(STDERR, "Unknown tier_band in seed: {$seedTier}\n");
        exit(1);
    }
    $sort = $i + 1;
    $stmt->bind_param(
        'sssssi',
        $row['milestone_key'],
        $row['display_name'],
        $tier,
        $row['chart_token'],
        $row['rule_short'],
        $sort
    );
    if (!$stmt->execute()) {
        fwrite(STDERR, 'Insert failed: ' . $stmt->error . PHP_EOL);
        exit(1);
    }
}
$stmt->close();

$res = $con->query('SELECT COUNT(*) AS n FROM milestone_definitions');
$row = $res ? $res->fetch_assoc() : null;
if ($res) {
    $res->free();
}
$n = (int) ($row['n'] ?? 0);
echo "Catalog rows: {$n}\n";
if ($n !== count($rows)) {
    fwrite(STDERR, "Expected " . count($rows) . " rows, got {$n}\n");
    exit(1);
}

echo "Done.\n";
mysqli_close($con);
