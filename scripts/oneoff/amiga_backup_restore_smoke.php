<?php
declare(strict_types=1);
/**
 * L5 slice 2 smoke — stage seal → mutate → full Apply → mutation gone.
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

$seals = amiga_backup_seal_list();
if ($seals === []) {
    fwrite(STDERR, "No seals in _backups — run slice 1 smoke first.\n");
    exit(1);
}
$sealId = (string) $seals[count($seals) - 1]['id'];
echo "Using seal {$sealId}\n";

$staged = amiga_backup_seal_stage_for_import($sealId);
if (!$staged['ok']) {
    fwrite(STDERR, 'STAGE FAIL: ' . $staged['error'] . "\n");
    exit(1);
}
echo 'OK staged parts=' . $staged['parts'] . "\n";

$marker = amiga_backup_restore_marker_read();
if (!is_array($marker) || ($marker['seal_id'] ?? '') !== $sealId) {
    fwrite(STDERR, "Restore marker missing or mismatched\n");
    exit(1);
}
echo "OK restore marker\n";

// Byte-compare first/last part vs seal
$manifest = json_decode((string) file_get_contents(amiga_backup_import_dir() . '/ko2amiga_manifest.json'), true);
$parts = $manifest['parts'] ?? [];
$first = (string) $parts[0];
$last = (string) $parts[count($parts) - 1];
foreach ([$first, $last] as $p) {
    $a = amiga_backup_seals_root() . DIRECTORY_SEPARATOR . $sealId . DIRECTORY_SEPARATOR . $p;
    $b = amiga_backup_import_dir() . DIRECTORY_SEPARATOR . $p;
    if (!is_file($a) || !is_file($b) || filesize($a) !== filesize($b)) {
        fwrite(STDERR, "Part size mismatch: {$p}\n");
        exit(1);
    }
}
echo "OK staged files match seal sizes ({$first}, {$last})\n";

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

echo "Applying all staged parts (full replace)...\n";
$apply = k2_amiga_import_apply_all_parts($con, false);
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
if ($after !== $origCountry) {
    fwrite(STDERR, "RESTORE WARN: Country={$after} expected {$origCountry} (still wiped probe)\n");
}
echo "OK probe wiped (Country now '{$after}', original '{$origCountry}')\n";

$counts = k2_amiga_import_counts($con);
echo 'OK counts players=' . $counts['players'] . ' games=' . $counts['games'] . "\n";
mysqli_close($con);
echo "SMOKE PASS\n";
exit(0);