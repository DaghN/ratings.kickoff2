<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Calendar &amp; geography</title>
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_table_render.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$ctx = amiga_lb_context($con);
$rows = amiga_calendar_geo_leaderboard_rows($con, $ctx);
mysqli_close($con);

$k2AmigaLbWingActive = 'calendar-geo';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<?php k2_table_wrap_open(true); ?>
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
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_peak_year_games(), ENT_QUOTES, 'UTF-8'); ?>">Peak yr games</th>
        <th data-k2-sort="number">Year</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_peak_year_tournaments(), ENT_QUOTES, 'UTF-8'); ?>">Peak yr events</th>
        <th data-k2-sort="number">Year</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_countries_played_in(), ENT_QUOTES, 'UTF-8'); ?>">Hosts</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_opponent_countries_faced(), ENT_QUOTES, 'UTF-8'); ?>">Opp. countries</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_opponent_countries_beaten(), ENT_QUOTES, 'UTF-8'); ?>">Opp. beaten</th>
    </tr>
</thead>
<tbody class="black">
<?php
$rank = 1;
foreach ($rows as $row) {
    $playerId = (int) $row['player_id'];
    ?>
    <tr>
        <td<?php echo k2_table_body_td_attr(0, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_table_body_td_attr(1, $k2LbAnchorCol, $k2LbDefaultSortCol, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link($playerId, (string) $row['player_name']); ?></td>
        <td<?php echo k2_table_body_td_attr(2, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_int($row['rating']); ?></td>
        <td<?php echo k2_table_body_td_attr(3, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_h((string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_table_body_td_attr(4, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['peak_year_games']; ?></td>
        <td<?php echo k2_table_body_td_attr(5, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $row['peak_year_games_year'] !== null ? (int) $row['peak_year_games_year'] : '—'; ?></td>
        <td<?php echo k2_table_body_td_attr(6, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['peak_year_tournaments']; ?></td>
        <td<?php echo k2_table_body_td_attr(7, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $row['peak_year_tournaments_year'] !== null ? (int) $row['peak_year_tournaments_year'] : '—'; ?></td>
        <td<?php echo k2_table_body_td_attr(8, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['countries_played_in']; ?></td>
        <td<?php echo k2_table_body_td_attr(9, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['opponent_countries_faced']; ?></td>
        <td<?php echo k2_table_body_td_attr(10, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['opponent_countries_beaten']; ?></td>
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
