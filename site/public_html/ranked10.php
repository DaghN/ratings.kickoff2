<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

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
$leaderRows = k2_milestone_meta_leaderboard_rows($con);
mysqli_close($con);

$k2LbWingActive = 'milestones';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav.php';
?>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table ranked-table-pending" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="8" data-k2-default-sort="8" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">#</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">ELO rating</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number"><span class="k2-lb-ms-tier--pitch">Aspirational</span></th>
        <th data-k2-sort="number"><span class="k2-lb-ms-tier--chrome">Dedicated</span></th>
        <th data-k2-sort="number"><span class="k2-lb-ms-tier--amber">Accomplished</span></th>
        <th data-k2-sort="number"><span class="k2-lb-ms-tier--holo">Legendary</span></th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_milestones_total(), ENT_QUOTES, 'UTF-8'); ?>">Milestones</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
foreach ($leaderRows as $row) {
    ?>
    <tr>
        <td><?php echo $rank; ?></td>
        <td class="k2-table-cell--left"><?php echo k2_player_link($row['player_id'], $row['player_name']); ?></td>
        <td><?php echo k2_fmt_int($row['rating']); ?></td>
        <td><?php echo (int) $row['games']; ?></td>
        <td><span class="k2-lb-ms-tier--pitch"><?php echo (int) $row['aspirational']; ?></span></td>
        <td><span class="k2-lb-ms-tier--chrome"><?php echo (int) $row['dedicated']; ?></span></td>
        <td><span class="k2-lb-ms-tier--amber"><?php echo (int) $row['accomplished']; ?></span></td>
        <td><span class="k2-lb-ms-tier--holo"><?php echo (int) $row['legendary']; ?></span></td>
        <td><?php echo (int) $row['total']; ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div><!-- .k2-table-wrap -->

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav_end.php'; ?>

</div><!-- .k2-page-nav -->

</body>
</html>
