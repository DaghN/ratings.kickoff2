<?php
declare(strict_types=1);
/**
 * L5 restore smoke — direct apply from pack dir (BA4; does not require _import).
 *
 * Prefer newest amiga/_backups/seal-*; else GitHub checkpoint pack.
 * CLI: php scripts/oneoff/amiga_backup_restore_smoke.php
 */
$root = dirname(__DIR__, 2);
require_once $root . '/site/public_html/amiga/includes/amiga_backup_seal_lib.php';
require_once $root . '/site/public_html/amiga/includes/amiga_staging_import_lib.php';
require $root . '/site/config/ko2amiga_config.php';

$dbName = (string) ($database ?? '');
if ($dbName !== 'ko2amiga_db' && $dbName !== 'ko2amiga_work') {
    fwrite(STDERR, "Expected ko2amiga_db or ko2amiga_work, got {$dbName}\n");
    exit(1);
}

$packDir = '';
$label = '';
$seals = amiga_backup_seal_list();
if ($seals !== []) {
    $sealId = (string) $seals[count($seals) - 1]['id'];
    $validated = amiga_backup_seal_validate_for_restore($sealId);
    if (!$validated['ok']) {
        fwrite(STDERR, 'VALIDATE FAIL: ' . $validated['error'] . "\n");
        exit(1);
    }
    $packDir = (string) $validated['pack_dir'];
    $label = 'seal ' . $sealId;
    echo "OK validate {$sealId} parts=" . count($validated['parts']) . "\n";
} else {
    $checkpoint = $root . '/data/amiga/checkpoints/work-2026-07-18-forum';
    if (!is_dir($checkpoint) || !is_file($checkpoint . '/ko2amiga_manifest.json')) {
        fwrite(STDERR, "No _backups seals and no GitHub checkpoint pack found.\n");
        exit(1);
    }
    $parts = k2_amiga_import_manifest_parts_from_dir($checkpoint);
    if ($parts === []) {
        fwrite(STDERR, "Checkpoint has no parts.\n");
        exit(1);
    }
    $packDir = $checkpoint;
    $label = 'checkpoint work-2026-07-18-forum';
    echo 'OK using ' . $label . ' parts=' . count($parts) . "\n";
}

mysqli_report(MYSQLI_REPORT_OFF);
$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, 'connect failed: ' . $con->connect_error . "\n");
    exit(1);
}
$con->set_charset('utf8mb4');

$probe = 'ZZRESTOREPROBE';
$res = $con->query('SELECT id, Country FROM amiga_players ORDER BY id ASC LIMIT 1');
$row = $res ? $res->fetch_assoc() : null;
if ($res) {
    $res->free();
}
if ($row === null) {
    fwrite(STDERR, "No players to probe\n");
    exit(1);
}
$pid = (int) $row['id'];
$origCountry = (string) $row['Country'];
$stmt = $con->prepare('UPDATE amiga_players SET Country = ? WHERE id = ?');
$stmt->bind_param('si', $probe, $pid);
$stmt->execute();
$stmt->close();
$check = $con->query('SELECT Country FROM amiga_players WHERE id = ' . $pid);
$cRow = $check->fetch_assoc();
$check->free();
if (($cRow['Country'] ?? '') !== $probe) {
    fwrite(STDERR, "Probe mutate failed\n");
    exit(1);
}
echo "OK mutated player {$pid} Country -> {$probe} (was {$origCountry})\n";

echo "Applying all parts from {$label} (direct pack dir; _import untouched)...\n";
$apply = k2_amiga_import_apply_all_parts_from_dir($con, $packDir, false);
if (!$apply['ok']) {
    fwrite(STDERR, 'APPLY FAIL: ' . $apply['error'] . "\n");
    exit(1);
}
echo 'OK apply parts=' . $apply['parts'] . ' statements=' . $apply['statements']
    . ' elapsed=' . $apply['elapsed'] . "s\n";

$check = $con->query('SELECT Country FROM amiga_players WHERE id = ' . $pid);
$cRow = $check ? $check->fetch_assoc() : null;
if ($check) {
    $check->free();
}
$after = (string) ($cRow['Country'] ?? '');
if ($after === $probe) {
    fwrite(STDERR, "RESTORE FAIL: probe Country still present after Apply\n");
    exit(1);
}
echo "OK probe wiped (Country now '{$after}', original '{$origCountry}')\n";

$counts = k2_amiga_import_counts($con);
echo 'OK counts players=' . $counts['players'] . ' games=' . $counts['games'] . "\n";
mysqli_close($con);
echo "SMOKE PASS (direct restore path)\n";
exit(0);
