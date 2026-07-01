<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_lb_sortable_table_head.inc.php'; ?>
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2HubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
$leaderRows = k2_milestone_meta_leaderboard_rows($con);
mysqli_close($con);

$k2LbWingActive = 'milestones';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav.php';
?>

<?php k2_table_wrap_open(true); ?>
<?php $lbSort = k2_lb_table_sort_state(8); ?>

<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class('k2-table--milestones-meta-lb')); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(8); ?>>

<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">#</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number"><span class="k2-lb-ms-tier--pitch">Aspirational</span></th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number"><span class="k2-lb-ms-tier--chrome">Dedicated</span></th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number"><span class="k2-lb-ms-tier--amber">Accomplished</span></th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number"><span class="k2-lb-ms-tier--holo">Legendary</span></th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_milestones_total(), ENT_QUOTES, 'UTF-8'); ?>">Milestones</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
foreach ($leaderRows as $row) {
    ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?>><?php echo k2_player_link($row['player_id'], $row['player_name']); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['rating']); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($row['games']); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><span class="k2-lb-ms-tier--pitch"><?php echo (int) $row['aspirational']; ?></span></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><span class="k2-lb-ms-tier--chrome"><?php echo (int) $row['dedicated']; ?></span></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><span class="k2-lb-ms-tier--amber"><?php echo (int) $row['accomplished']; ?></span></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><span class="k2-lb-ms-tier--holo"><?php echo (int) $row['legendary']; ?></span></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo (int) $row['total']; ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

<?php k2_table_wrap_close(); ?><!-- .k2-table-wrap -->


</div><!-- .k2-page-nav -->

</body>
</html>
