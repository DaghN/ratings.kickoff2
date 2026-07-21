<?php
declare(strict_types=1);
/**
 * L5 slice 1 smoke — write reserve seal, refuse PHP delete, list seals.
 * CLI: php scripts/oneoff/amiga_backup_seal_smoke.php
 */
$root = dirname(__DIR__, 2);
require_once $root . '/site/public_html/amiga/includes/amiga_backup_seal_lib.php';
require $root . '/site/config/ko2amiga_config.php';

$dbName = (string) ($database ?? '');
if ($dbName !== 'ko2amiga_db' && $dbName !== 'ko2amiga_work') {
    fwrite(STDERR, "Expected ko2amiga_db or ko2amiga_work, got {$dbName}\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);
$con = new mysqli($dbhost, $username, $password, $database, (int) $dbportnum);
if ($con->connect_errno) {
    fwrite(STDERR, 'connect failed: ' . $con->connect_error . "\n");
    exit(1);
}
$con->set_charset('utf8mb4');

echo "Sealing reserve pack from {$database}...\n";
$seal = amiga_backup_seal_write($con, [
    'host' => (string) $dbhost,
    'port' => (int) $dbportnum,
    'user' => (string) $username,
    'pass' => (string) $password,
    'database' => (string) $database,
], [
    'reason' => 'smoke',
    'reserve' => true,
    'label' => 's1',
]);
mysqli_close($con);

if (!$seal['ok']) {
    fwrite(STDERR, 'SEAL FAIL: ' . $seal['error'] . "\n");
    exit(1);
}

echo 'OK seal_id=' . $seal['seal_id'] . ' parts=' . $seal['parts']
    . ' bytes=' . $seal['bytes'] . ' elapsed=' . $seal['elapsed']
    . ' method=' . $seal['method'] . ' reserve=' . ($seal['reserve'] ? '1' : '0') . "\n";

$del = amiga_backup_seal_try_delete((string) $seal['seal_id']);
if ($del['ok']) {
    fwrite(STDERR, "BA6 FAIL: reserve seal was deleted via PHP\n");
    exit(1);
}
echo 'OK reserve delete refused: ' . $del['error'] . "\n";

$manifest = amiga_backup_seals_root() . DIRECTORY_SEPARATOR . $seal['seal_id'] . DIRECTORY_SEPARATOR . 'ko2amiga_manifest.json';
if (!is_file($manifest)) {
    fwrite(STDERR, "Missing manifest\n");
    exit(1);
}
$json = json_decode((string) file_get_contents($manifest), true);
$partCount = is_array($json['parts'] ?? null) ? count($json['parts']) : 0;
echo "OK manifest parts={$partCount}\n";

$hasInverse = false;
foreach ($json['parts'] as $p) {
    if (is_string($p) && str_contains($p, 'inverse_count')) {
        $hasInverse = true;
        break;
    }
}
if (!$hasInverse) {
    fwrite(STDERR, "Missing inverse_count part\n");
    exit(1);
}
echo "OK inverse_count part present\n";
echo "SMOKE PASS\n";
exit(0);