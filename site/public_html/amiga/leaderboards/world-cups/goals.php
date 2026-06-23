<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — World Cups goals</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php'; k2_table_js_enqueue(); ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_table_render.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
include __DIR__ . '/../../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$ctx = amiga_lb_context($con);

$rows = amiga_wc_lb_base_rows($con, $ctx);
$playerCount = amiga_wc_honours_player_count($con, $ctx);

mysqli_close($con);

$k2AmigaLbWingActive = 'world-cups';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';

$k2AmigaWcLbView = 'goals';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_wc_lb_nav.php';
?>

<div class="k2-lb-world-cups-goals">
<div class="k2-table-wrap">

<?php
$k2LbAnchorCol = 2;
$k2LbDefaultSortCol = 4;
?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="4" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">Rank</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_elo_rating(), ENT_QUOTES, 'UTF-8'); ?>">Elo</th>
        <th data-k2-sort="text">Country</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals for" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_scored(), ENT_QUOTES, 'UTF-8'); ?>">GF</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals against" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_conceded(), ENT_QUOTES, 'UTF-8'); ?>">GA</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goal difference" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_goal_difference(), ENT_QUOTES, 'UTF-8'); ?>">GD</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals for per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_scored_avg(), ENT_QUOTES, 'UTF-8'); ?>">GF/g</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals against per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_conceded_avg(), ENT_QUOTES, 'UTF-8'); ?>">GA/g</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goal difference per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_goal_difference_per_game(), ENT_QUOTES, 'UTF-8'); ?>">GD/g</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
foreach ($rows as $row) {
    $playerId = (int) $row['player_id'];
    $games = (int) $row['games'];
    $gf = (int) $row['goals_for'];
    $ga = (int) $row['goals_against'];
    $gd = $gf - $ga;
    $gfPer = amiga_wc_lb_goals_per_game($gf, $games);
    $gaPer = amiga_wc_lb_goals_per_game($ga, $games);
    $gdPer = amiga_wc_lb_goals_per_game($gd, $games);
    ?>
    <tr>
        <td<?php echo k2_table_body_td_attr(0, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_table_body_td_attr(1, $k2LbAnchorCol, $k2LbDefaultSortCol, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link($playerId, (string) $row['player_name']); ?></td>
        <td<?php echo k2_table_body_td_attr(2, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_int($row['rating']); ?></td>
        <td<?php echo k2_table_body_td_attr(3, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_h((string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_table_body_td_attr(4, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $gf; ?></td>
        <td<?php echo k2_table_body_td_attr(5, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $ga; ?></td>
        <td<?php echo k2_table_body_td_attr(6, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $gd; ?></td>
        <td<?php echo k2_table_body_td_attr(7, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $gfPer !== null ? k2_fmt_decimal($gfPer, $games) : k2_fmt_dash(); ?></td>
        <td<?php echo k2_table_body_td_attr(8, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $gaPer !== null ? k2_fmt_decimal($gaPer, $games) : k2_fmt_dash(); ?></td>
        <td<?php echo k2_table_body_td_attr(9, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $gdPer !== null ? k2_fmt_decimal($gdPer, $games) : k2_fmt_dash(); ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>
</div>

<p style="padding:0 1.25rem 2rem;color:var(--k2-text-secondary)"><?php echo number_format($playerCount); ?> players with at least one World Cup.</p>

</div><!-- .k2-page-nav -->

</body>
</html>
