<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php $k2RankedCloak = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_lb_sortable_table_head.inc.php'; ?>
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>

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

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';

$colElo = 2;
$lbSort = k2_lb_table_sort_state($colElo);
$lbDefaultOrder = k2_lb_rating_default_order_sql();
$lbOrderMap = k2_lb_rating_order_column_map();
$lbSqlOrder = k2_lb_sql_order_from_sort($lbSort, $lbOrderMap, $lbDefaultOrder);

$query = 'SELECT id, Name, Rating, NumberGames, NumberWins, NumberDraws, NumberLosses, WinRatio, DrawRatio, LossRatio, AverageOpponentRating FROM playertable WHERE ' . k2_lb_player_where_sql() . ' ORDER BY ' . $lbSqlOrder['order_clause'];
$result = k2_query_or_public_error($con, $query, 'ranked7 leaderboard'); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'rating';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>

<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_lb_table_skip_initial_sort_attr_for_ssr($lbSort, $colElo, 'desc', $lbSqlOrder['ssr_applied_url_sort']); ?>>

<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">Rank</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number">Wins</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number">Draws</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number">Losses</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_win_ratio(), ENT_QUOTES, 'UTF-8'); ?>">Win Ratio</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_draw_ratio(), ENT_QUOTES, 'UTF-8'); ?>">Draw Ratio</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_loss_ratio(), ENT_QUOTES, 'UTF-8'); ?>">Loss Ratio</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_opponent_avg(), ENT_QUOTES, 'UTF-8'); ?>">Opponent Avg.</th>
    </tr>
</thead>


<tbody class="black">
	<?php
    $rank = "1";
    while ($row = mysqli_fetch_row($result))
    {
        $playerId = (int) $row[0];
        $playerName = (string) $row[1];
	?>
    
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?>><?php echo k2_lb_player_row_anchor_markup($playerId); ?><?php echo k2_player_link($playerId, $playerName); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_lb_rating_cell_link($playerId, $row[2], $playerName); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($row[3]); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo k2_fmt_wdl_count($row[4], $row[3], 'win'); ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo k2_fmt_count($row[5], $row[3]); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo k2_fmt_wdl_count($row[6], $row[3], 'loss'); ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row[7], $row[3]); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row[8], $row[3]); ?></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo k2_fmt_pct_from_ratio($row[9], $row[3]); ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?>><?php echo k2_fmt_lb_stat($row[10], $row[3]); ?></td>
    </tr> 
    
    <?php
	$rank++; 
    }  
    ?> 
</tbody>

</table> 

<?php k2_table_wrap_close(); ?><!-- .k2-table-wrap -->


</div><!-- .k2-page-nav -->

<script type="text/javascript" src="/js/lb-rating-page.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/lb-rating-page.js'); ?>" defer="defer"></script>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/site_footer.php';
k2_site_footer_render();
?>

</body>
</html>
