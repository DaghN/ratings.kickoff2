<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2) . '/site/public_html';
require $root . '/includes/k2_safety.php';
require $root . '/includes/amiga_lb_lib.php';
require $root . '/includes/amiga_wc_lb_lib.php';
include dirname(__DIR__, 2) . '/site/config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);
$rows = amiga_wc_lb_base_rows($con, $ctx);
echo 'rows=' . count($rows) . PHP_EOL;
if ($rows !== []) {
    $r = $rows[0];
    echo 'v2 dd=' . ($r['double_digits'] ?? 'missing')
        . ' victims=' . ($r['different_victims'] ?? 'missing')
        . ' max_gf=' . ($r['most_goals_scored'] ?? 'missing')
        . PHP_EOL;
}
mysqli_close($con);
