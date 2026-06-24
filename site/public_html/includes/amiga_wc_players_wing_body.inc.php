<?php
/**
 * Load WC player slice rows and render the active sub-wing table.
 *
 * Requires $k2AmigaWcPlayersView (or $k2AmigaWorldCupsPlayersView / $k2AmigaWcLbView).
 * Used by Leaderboards → World Cups and World Cups hub → Player stats.
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_players_table.php';

$k2AmigaWcPlayersView = $k2AmigaWcPlayersView
    ?? $k2AmigaWorldCupsPlayersView
    ?? $k2AmigaWcLbView
    ?? 'honours';

include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$ctx = amiga_lb_context($con);

$rows = amiga_wc_lb_rows_for_view($con, $ctx, $k2AmigaWcPlayersView);
$playerCount = amiga_wc_honours_player_count($con, $ctx);

mysqli_close($con);

amiga_wc_players_render_view($k2AmigaWcPlayersView, $rows, $playerCount);
