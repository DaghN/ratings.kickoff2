<?php
declare(strict_types=1);

$k2AmigaLbPerfRatingView = 'top';
$k2AmigaLbPerfRatingPageTitle = 'Amiga ladder — Performance rating — Top 100';
$k2AmigaLbPerfRatingLede = 'The hundred highest single-event performance ratings. A player may appear more than once.';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_table.php';
include __DIR__ . '/../../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

$colPerf = AMIGA_LB_PERF_RATING_COL_PERF;
$lbSort = k2_lb_table_sort_state($colPerf, AMIGA_LB_PERF_RATING_COL_PERF);
$playerAlias = $ctx->isActive() ? 'p' : 'pl';
$lbDefaultOrder = amiga_lb_performance_rating_top_default_order_sql();
$lbOrderMap = amiga_lb_performance_rating_event_order_column_map($playerAlias);
$lbSqlOrder = k2_lb_sql_order_from_sort($lbSort, $lbOrderMap, $lbDefaultOrder);

$rows = amiga_lb_performance_rating_top_rows($con, $ctx, $lbSqlOrder['order_clause']);
mysqli_close($con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_shell_start.inc.php';

amiga_lb_performance_rating_render_table('top', $rows, $lbSqlOrder);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_performance_rating_shell_end.inc.php';