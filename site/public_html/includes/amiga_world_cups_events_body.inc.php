<?php
/**
 * Load World Cup events and render wing 1 catalog table.
 *
 * Requires hub shell already opened ($k2AmigaWorldCupsHubView = events).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cup_stats_read_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_world_cups_events_table.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';

include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$ctx = amiga_world_cup_stats_context($con);
$rows = amiga_world_cup_stats_rows($con, $ctx);
$playerIds = amiga_world_cup_stats_collect_player_ids($rows);
$nameMap = amiga_tournament_player_names($con, $playerIds);
$countryMap = amiga_tournament_player_countries($con, $playerIds);
mysqli_close($con);

amiga_world_cups_events_render_table($rows, $nameMap, $countryMap);
