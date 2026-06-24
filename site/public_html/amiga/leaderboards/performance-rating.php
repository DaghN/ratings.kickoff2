<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Performance rating</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_country_flag.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
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

<?php k2_table_wrap_open(true); ?>

<?php $lbSort = k2_lb_table_sort_state(4); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>

<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">#</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th_country(3, $lbSort); ?> data-k2-sort="text">Country</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars($perfHelp, ENT_QUOTES, 'UTF-8'); ?>">Perf. rating</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="Games in the listed event.">Event games</th>
        <th<?php echo k2_lb_th(6, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Event</th>
        <th<?php echo k2_lb_th(7, $lbSort, 'k2-table-cell--right'); ?> data-k2-sort="number">Date</th>
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
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link($playerId, (string) $row['player_name']); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['Rating']); ?></td>
        <?php echo k2_lb_td_country_open(3, $lbSort, (string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo amiga_profile_tournament_rating_cell($row['performance_rating'] ?? null); ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo k2_fmt_games_played($eventGames); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort, 'k2-table-cell--left'); ?>><?php
            echo amiga_tournament_link(
                (int) ($row['tournament_id'] ?? 0),
                (string) ($row['tournament_name'] ?? '')
            );
        ?></td>
        <td<?php echo k2_lb_td(7, $lbSort, 'k2-table-cell--right'); ?> data-k2-sort-value="<?php echo amiga_profile_event_date_sort_value($row); ?>"><?php echo amiga_profile_format_event_date($row['event_date'] ?? null); ?></td>
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
