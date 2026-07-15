<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php $k2RankedCloak = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_lb_sortable_table_head.inc.php'; ?>
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>
<script src="/js/lb-peak-rating-tooltip.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/lb-peak-rating-tooltip.js'); ?>" defer="defer"></script>

</head>

<body class="k2-site">
<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>

<?php
$k2HubTabActive = 'leaderboards';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";
?>

<?php 
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER["DOCUMENT_ROOT"] . "/../config/ko2unitydb_config.php";

	$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_peak_rating_lib.php';

$colPeak = 4;
$lbSort = k2_lb_table_sort_state($colPeak);
$lbDefaultOrder = k2_lb_peak_rating_default_order_sql();
$lbOrderMap = k2_lb_peak_rating_order_column_map();
$lbSqlOrder = k2_lb_sql_order_from_sort($lbSort, $lbOrderMap, $lbDefaultOrder);

$result = k2_lb_peak_rating_query($con, $lbSqlOrder['order_clause']);
$queryError = $result === false;
mysqli_close($con);
?>

<?php
$k2LbWingActive = 'peak-rating';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>

<?php if ($queryError) { ?>
<p class="server-peak-period-leaderboard-status">Could not load peak ratings.</p>
<?php } else { ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_lb_table_skip_initial_sort_attr_for_ssr($lbSort, $colPeak, 'desc', $lbSqlOrder['ssr_applied_url_sort']); ?>>

<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">#</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_peak(), ENT_QUOTES, 'UTF-8'); ?>">Peak</th>
        <th<?php echo k2_lb_th(5, $lbSort, 'k2-table-cell--right'); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_online_peak_rating_date(), ENT_QUOTES, 'UTF-8'); ?>">Peak date</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_nadir(), ENT_QUOTES, 'UTF-8'); ?>">Nadir</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_opponent_avg(), ENT_QUOTES, 'UTF-8'); ?>">Opponent Avg.</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_highest_victim(), ENT_QUOTES, 'UTF-8'); ?>">Highest Victim</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_lowest_culprit(), ENT_QUOTES, 'UTF-8'); ?>">Lowest Culprit</th>
    </tr>
</thead>


<tbody class="black">
	<?php
    $rank = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        $games = (int) ($row['NumberGames'] ?? 0);
        $playerId = (int) $row['id'];
        $peakDate = $row['peak_rating_date'] ?? null;
        $peakCtxClass = k2_lb_peak_rating_context_cell_class($row['PeakRating'] ?? null, $row['peak_rating_game_id'] ?? null);
        $peakCtxAttrs = k2_lb_peak_rating_context_cell_attrs($playerId, $row['PeakRating'] ?? null, $row['peak_rating_game_id'] ?? null);
	?>
    
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank; ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?>><?php echo k2_lb_player_row_anchor_markup($playerId); ?><?php echo k2_player_link($playerId, (string) $row['Name']); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_lb_rating_cell_link($playerId, $row['Rating'], (string) $row['Name']); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($games); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort, $peakCtxClass); ?><?php echo $peakCtxAttrs; ?>><span class="blue"><?php echo k2_fmt_peak_rating($row['PeakRating']); ?></span></td>
        <td<?php echo k2_lb_td(5, $lbSort, trim('k2-table-cell--right ' . $peakCtxClass)); ?><?php echo $peakCtxAttrs; ?> data-k2-sort-value="<?php echo k2_h(k2_lb_peak_rating_date_sort_value($peakDate)); ?>"><?php echo k2_h(k2_lb_peak_rating_format_event_date($peakDate)); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo k2_fmt_nadir_rating($row['LowestRating']); ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo k2_fmt_lb_stat($row['AverageOpponentRating'], $games); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo k2_fmt_lb_stat($row['HighestRatedVictim'], $games); ?></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo k2_fmt_lb_stat($row['LowestRatedCulprit'], $games, 5000.0); ?></td>
    </tr> 
    
    <?php
	$rank++;
    }
    ?> 
</tbody>

</table> 

<?php k2_table_wrap_close(); ?><!-- .k2-table-wrap -->
<?php } ?>


<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>
