<?php
/**
 * Load WC stats rows and render the active stats sub-wing table.
 *
 * Requires $k2AmigaWorldCupsStatsView and hub shell already opened.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cup_stats_read_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cup_stats_table.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';

$k2AmigaWorldCupsStatsView = $k2AmigaWorldCupsStatsView ?? 'participation';

include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$ctx = amiga_world_cup_stats_context($con);
$rows = amiga_world_cup_stats_rows($con, $ctx);
$nameMap = amiga_tournament_player_names($con, amiga_world_cup_stats_collect_player_ids($rows));
mysqli_close($con);

amiga_world_cup_stats_render_view($k2AmigaWorldCupsStatsView, $rows, $nameMap);
