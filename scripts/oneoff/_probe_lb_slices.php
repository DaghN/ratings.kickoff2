<?php
$_SERVER['DOCUMENT_ROOT'] = 'C:/Users/daghn/Desktop/Online and Amiga 500 ELO/site/public_html';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include 'C:/Users/daghn/Desktop/Online and Amiga 500 ELO/site/config/ko2amiga_config.php';
$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_profile_lb_slices.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_context.php';
$_GET['as'] = 'event:100';
amiga_snapshot_context_reset();
$ctx = amiga_snapshot_context_from_request($con);
$row = amiga_profile_lb_slices_load($con, 225, $ctx);
echo $row === null ? 'null' : 'row games=' . ($row['NumberGames'] ?? '');
mysqli_close($con);