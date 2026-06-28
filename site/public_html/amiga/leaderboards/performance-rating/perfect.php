<?php
declare(strict_types=1);

$k2AmigaLbPerfRatingView = 'perfect';
$k2AmigaLbPerfRatingPageTitle = 'Amiga ladder — Performance rating — Perfect';
$k2AmigaLbPerfRatingLede = 'Every perfect tournament run: at least two games, all wins. These events have no finite performance rating (∞).';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_table.php';
include __DIR__ . '/../../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);
$rows = amiga_lb_performance_rating_perfect_rows($con, $ctx);
mysqli_close($con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_shell_start.inc.php';

amiga_lb_performance_rating_render_table('perfect', $rows);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_shell_end.inc.php';