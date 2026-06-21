<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Performance rating</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_performance_rating.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_profile_blocks.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);
$perfRows = amiga_lb_performance_rating_rows($con, $ctx);
mysqli_close($con);

$k2AmigaLbWingActive = 'performance-rating';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';

$perfHelp = amiga_perf_rating_column_help();
?>

<header class="k2-hub-page-intro-head" style="padding:0 1.25rem">
	<p class="k2-hub-page-intro" style="margin:0 0 1rem">Best single-event performance rating per player. Loosely speaking, the best tournament performance. However, perfect win or loss records cannot define a performance rating, so such perfect tournaments are not included here. Only tournaments where you had at least one draw or loss can qualify.</p>
</header>

<div class="k2-table-wrap">

<?php
$k2LbAnchorCol = 2;
$k2LbDefaultSortCol = 3;
?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="3" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">#</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">ELO rating</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars($perfHelp, ENT_QUOTES, 'UTF-8'); ?>">Perf. rating</th>
        <th data-k2-sort="number" data-k2-help="Games in the listed event.">Event games</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Event</th>
        <th class="k2-table-cell--right" data-k2-sort="number">Date</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
foreach ($perfRows as $row) {
    $playerId = (int) $row['player_id'];
    $eventGames = (int) ($row['event_games'] ?? 0);
    ?>
    <tr>
        <td<?php echo k2_table_body_td_attr(0, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_table_body_td_attr(1, $k2LbAnchorCol, $k2LbDefaultSortCol, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link($playerId, (string) $row['player_name']); ?></td>
        <td<?php echo k2_table_body_td_attr(2, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_int($row['Rating']); ?></td>
        <td<?php echo k2_table_body_td_attr(3, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo amiga_profile_tournament_rating_cell($row['performance_rating'] ?? null); ?></td>
        <td<?php echo k2_table_body_td_attr(4, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_games_played($eventGames); ?></td>
        <td<?php echo k2_table_body_td_attr(5, $k2LbAnchorCol, $k2LbDefaultSortCol, 'k2-table-cell--left'); ?>><?php
            echo amiga_tournament_link(
                (int) ($row['tournament_id'] ?? 0),
                (string) ($row['tournament_name'] ?? '')
            );
        ?></td>
        <td<?php echo k2_table_body_td_attr(6, $k2LbAnchorCol, $k2LbDefaultSortCol, 'k2-table-cell--right'); ?> data-k2-sort-value="<?php echo amiga_profile_event_date_sort_value($row); ?>"><?php echo amiga_profile_format_event_date($row['event_date'] ?? null); ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>

<p style="padding:0 1.25rem 2rem;color:var(--k2-text-secondary)"><?php echo number_format(count($perfRows)); ?> players with a qualifying event performance rating.</p>

</div><!-- .k2-page-nav -->

</body>
</html>
