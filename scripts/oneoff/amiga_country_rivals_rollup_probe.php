<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/site/config/ko2amiga_config.php';
require_once $root . '/site/public_html/includes/amiga_country_rivals_load.php';
require_once $root . '/site/public_html/includes/amiga_snapshot_context.php';
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');
$ctx = AmigaSnapshotContext::present();
$rows = amiga_country_rivals_rows($con, 'Denmark', $ctx);
echo 'Denmark rivals: ' . count($rows) . "\n";
echo 'first: ' . ($rows[0]['rival_token'] ?? 'none') . "\n";
foreach ($rows as $r) { if ($r['rival_token']==='Denmark') echo "ERROR domestic row present\n"; }
$con->close();