<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Tournament honours</title>
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_league_table_render.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$honoursRows = amiga_tournament_honours_leaderboard_rows($con);

$playerCount = 0;
$countRes = mysqli_query($con, 'SELECT COUNT(*) AS n FROM amiga_player_current WHERE tournaments_played > 0');
if ($countRes) {
    $countRow = mysqli_fetch_assoc($countRes);
    $playerCount = (int) ($countRow['n'] ?? 0);
    mysqli_free_result($countRes);
}

mysqli_close($con);

$k2AmigaLbWingActive = 'tournament-honours';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<div class="k2-lb-tournament-honours">
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
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_tournament_events(), ENT_QUOTES, 'UTF-8'); ?>">Events</th>
        <th class="k2-lb-honours-medal-th" data-k2-sort="number" data-k2-tooltip-label="Event gold" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_event_gold(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(1); ?><span class="visually-hidden">Event gold</span></th>
        <th class="k2-lb-honours-medal-th" data-k2-sort="number" data-k2-tooltip-label="Event silver" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_event_silver(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(2); ?><span class="visually-hidden">Event silver</span></th>
        <th class="k2-lb-honours-medal-th" data-k2-sort="number" data-k2-tooltip-label="Event bronze" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_event_bronze(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(3); ?><span class="visually-hidden">Event bronze</span></th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_event_podiums(), ENT_QUOTES, 'UTF-8'); ?>">Podiums</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_played(), ENT_QUOTES, 'UTF-8'); ?>">WCs</th>
        <th class="k2-lb-honours-medal-th" data-k2-sort="number" data-k2-tooltip-label="WC gold" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_gold(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(1); ?><span class="visually-hidden">WC gold</span></th>
        <th class="k2-lb-honours-medal-th" data-k2-sort="number" data-k2-tooltip-label="WC silver" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_silver(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(2); ?><span class="visually-hidden">WC silver</span></th>
        <th class="k2-lb-honours-medal-th" data-k2-sort="number" data-k2-tooltip-label="WC bronze" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_bronze(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(3); ?><span class="visually-hidden">WC bronze</span></th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_wc_podiums(), ENT_QUOTES, 'UTF-8'); ?>">WC pod.</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
foreach ($honoursRows as $row) {
    $playerId = (int) $row['player_id'];
    ?>
    <tr>
        <td<?php echo k2_table_body_td_attr(0, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_table_body_td_attr(1, $k2LbAnchorCol, $k2LbDefaultSortCol, 'k2-table-cell--left'); ?>><?php echo k2_amiga_player_link($playerId, (string) $row['player_name']); ?></td>
        <td<?php echo k2_table_body_td_attr(2, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_fmt_int($row['rating']); ?></td>
        <td<?php echo k2_table_body_td_attr(3, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo k2_h((string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_table_body_td_attr(4, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['tournaments_played']; ?></td>
        <td<?php echo k2_table_body_td_attr(5, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['event_gold']; ?></td>
        <td<?php echo k2_table_body_td_attr(6, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['event_silver']; ?></td>
        <td<?php echo k2_table_body_td_attr(7, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['event_bronze']; ?></td>
        <td<?php echo k2_table_body_td_attr(8, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['event_podiums']; ?></td>
        <td<?php echo k2_table_body_td_attr(9, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['wc_played']; ?></td>
        <td<?php echo k2_table_body_td_attr(10, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['wc_gold']; ?></td>
        <td<?php echo k2_table_body_td_attr(11, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['wc_silver']; ?></td>
        <td<?php echo k2_table_body_td_attr(12, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['wc_bronze']; ?></td>
        <td<?php echo k2_table_body_td_attr(13, $k2LbAnchorCol, $k2LbDefaultSortCol); ?>><?php echo (int) $row['wc_podiums']; ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>
</div>

<p style="padding:0 1.25rem 2rem;color:var(--k2-text-secondary)"><?php echo number_format($playerCount); ?> players with at least one tournament.</p>

</div><!-- .k2-page-nav -->

</body>
</html>
