<?php
declare(strict_types=1);

$k2AmigaLbPerfRatingView = 'best';
$k2AmigaLbPerfRatingPageTitle = 'Amiga ladder — Performance rating — Best';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_table.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';
include __DIR__ . '/../../../../config/ko2amiga_config.php';

$k2AmigaLbPerfRatingPerfectHref = amiga_url_with_context(k2_amiga_route('amiga-lb-performance-rating-perfect'));
$k2AmigaLbPerfRatingLedeHtml = 'Best single-event performance rating per player. Loosely speaking, the best tournament performance. However, perfect win or loss records cannot define a performance rating, so such <a class="k2-carry-scroll-link" href="'
	. k2_h($k2AmigaLbPerfRatingPerfectHref) . '">perfect tournaments</a> are not included here. Only tournaments where you had at least one draw or loss can qualify.';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);
$rows = amiga_lb_performance_rating_rows($con, $ctx);
amiga_lb_chapter_lede_html_for_request($con, $ctx);
mysqli_close($con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_shell_start.inc.php';

amiga_lb_performance_rating_render_table('best', $rows);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_shell_end.inc.php';