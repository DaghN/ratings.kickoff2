<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga ladder — Tournament honours</title>
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_snapshot_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_tournament_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_country_flag.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");
$ctx = amiga_lb_context($con);

$honoursRows = amiga_tournament_honours_leaderboard_rows($con, $ctx);
$playerCount = amiga_lb_honours_player_count($con, $ctx);

mysqli_close($con);

$k2AmigaLbWingActive = 'tournament-honours';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_lb_nav.php';
?>

<div class="k2-lb-tournament-honours">
<?php k2_table_wrap_open(true); ?>

<?php $lbSort = k2_lb_table_sort_state(4); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>

<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_tournament_events(), ENT_QUOTES, 'UTF-8'); ?>">Events</th>
        <th<?php echo k2_lb_th(4, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="Event gold" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_event_gold(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(1); ?><span class="visually-hidden">Event gold</span></th>
        <th<?php echo k2_lb_th(5, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="Event silver" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_event_silver(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(2); ?><span class="visually-hidden">Event silver</span></th>
        <th<?php echo k2_lb_th(6, $lbSort, 'k2-lb-honours-medal-th'); ?> data-k2-sort="number" data-k2-tooltip-label="Event bronze" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_event_bronze(), ENT_QUOTES, 'UTF-8'); ?>"><?php echo k2_status_league_podium_medal(3); ?><span class="visually-hidden">Event bronze</span></th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_event_podiums(), ENT_QUOTES, 'UTF-8'); ?>">Podiums</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_amiga_perfect_events(), ENT_QUOTES, 'UTF-8'); ?>">Perfect</th>
    </tr>
</thead>

<tbody class="black">
<?php
$rank = 1;
foreach ($honoursRows as $row) {
    $playerId = (int) $row['player_id'];
    $playerName = (string) $row['player_name'];
    ?>
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort-value="<?php echo k2_h($playerName); ?>"><?php echo k2_amiga_lb_player_cell($playerId, $playerName, (string) ($row['country'] ?? '')); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['rating']); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo (int) $row['tournaments_played']; ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo (int) $row['event_gold']; ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo (int) $row['event_silver']; ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo (int) $row['event_bronze']; ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo (int) $row['event_podiums']; ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo (int) ($row['perfect_events'] ?? 0); ?></td>
    </tr>
    <?php
    $rank++;
}
?>
</tbody>

</table>

</div>
</div>

<p style="padding:0 1.25rem 2rem;color:var(--k2-text-secondary)"><?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_amiga_routes.php';
echo number_format($playerCount);
?> players with at least one tournament. World Cup honours: <a class="k2-link-star" href="<?php echo htmlspecialchars(k2_amiga_route('amiga-world-cups-players-honours'), ENT_QUOTES, 'UTF-8'); ?>">World Cups → Player stats</a>.</p>

</div><!-- .k2-page-nav -->

</body>
</html>
