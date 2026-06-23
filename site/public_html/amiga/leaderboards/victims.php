<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Victims &amp; Culprits</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php'; k2_table_js_enqueue(); ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_snapshot_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$ctx = amiga_lb_context($con);

$result = amiga_lb_query_career(
    $con,
    $ctx,
    'SELECT p.id AS ID, p.name AS Name, s.Rating, s.NumberGames, s.DifferentOpponents, s.DifferentVictims, '
    . 's.DoubleDigitsVictims, s.CleanSheetsVictims, s.MostGoalsConcededVictims, s.BiggestLossVictims, '
    . 's.DifferentCulprits, s.DoubleDigitsCulprits, s.CleanSheetsCulprits, s.MostGoalsScoredCulprits, s.BiggestWinCulprits ',
    'ORDER BY s.DifferentOpponents DESC, s.Rating DESC'
);

mysqli_close($con);

$k2AmigaLbWingActive = 'victims';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<div class="k2-table-wrap">

<?php
$k2LbAnchorCol = 2;
$k2LbDefaultSortCol = 4;
?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="4" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">#</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">ELO rating</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_opponents(), ENT_QUOTES, 'UTF-8'); ?>">Opponents</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_victims(), ENT_QUOTES, 'UTF-8'); ?>">Victims</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Double Digit victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_dd_victims(), ENT_QUOTES, 'UTF-8'); ?>">DD Victims</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Clean Sheet victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_cs_victims(), ENT_QUOTES, 'UTF-8'); ?>">CS Victims</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Most Goals Conceded victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_mgc_victims(), ENT_QUOTES, 'UTF-8'); ?>">MGC Victims</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Biggest Loss victims" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_bl_victims(), ENT_QUOTES, 'UTF-8'); ?>">BL Victims</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_culprits(), ENT_QUOTES, 'UTF-8'); ?>">Culprits</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Double Digit culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_dd_culprits(), ENT_QUOTES, 'UTF-8'); ?>">DD Culprits</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Clean Sheet culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_cs_culprits(), ENT_QUOTES, 'UTF-8'); ?>">CS Culprits</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Most Goals Scored culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_mgs_culprits(), ENT_QUOTES, 'UTF-8'); ?>">MGS Culprits</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Biggest Win culprits" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_bw_culprits(), ENT_QUOTES, 'UTF-8'); ?>">BW Culprits</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $games = (int) $row['NumberGames'];
    ?>
    <tr>
        <td<?php echo k2_table_body_td_attr(0, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_table_body_td_attr(1, $k2LbAnchorCol, $k2LbDefaultSortCol, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link((int) $row['ID'], (string) $row['Name']); ?></td>
        <td<?php echo k2_table_body_td_attr(2, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_int($row['Rating']); ?></td>
        <td<?php echo k2_table_body_td_attr(3, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_table_body_td_attr(4, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['DifferentOpponents'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(5, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['DifferentVictims'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(6, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['DoubleDigitsVictims'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(7, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['CleanSheetsVictims'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(8, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['MostGoalsConcededVictims'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(9, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['BiggestLossVictims'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(10, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['DifferentCulprits'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(11, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['DoubleDigitsCulprits'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(12, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['CleanSheetsCulprits'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(13, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['MostGoalsScoredCulprits'], $games); ?></td>
        <td<?php echo k2_table_body_td_attr(14, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_count($row['BiggestWinCulprits'], $games); ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>

</div><!-- .k2-page-nav -->

</body>
</html>
