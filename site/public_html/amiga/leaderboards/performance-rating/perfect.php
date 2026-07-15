<?php
declare(strict_types=1);

$k2AmigaLbPerfRatingView = 'perfect';
$k2AmigaLbPerfRatingPageTitle = 'Amiga ladder — Performance rating — Perfect';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_table.php';
include __DIR__ . '/../../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

$colDate = AMIGA_LB_PERF_RATING_COL_DATE;
$lbSort = k2_lb_table_sort_state($colDate);
$playerAlias = $ctx->isActive() ? 'p' : 'pl';
$lbDefaultOrder = amiga_lb_performance_rating_perfect_default_order_sql();
$lbOrderMap = amiga_lb_performance_rating_perfect_order_column_map($playerAlias);
$lbSqlOrder = k2_lb_sql_order_from_sort($lbSort, $lbOrderMap, $lbDefaultOrder);

$rows = amiga_lb_performance_rating_perfect_rows($con, $ctx, $lbSqlOrder['order_clause']);
mysqli_close($con);

$perfectRunCount = count($rows);
$k2AmigaLbPerfRatingLedeHtml = 'Every perfect tournament run, '
	. '<span class="blue">' . number_format($perfectRunCount) . '</span>'
	. ' in total.';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_shell_start.inc.php';

amiga_lb_performance_rating_render_table('perfect', $rows, $lbSqlOrder);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_shell_end.inc.php';