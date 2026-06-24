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
$query = 'SELECT id, Name, Rating, NumberGames, GoalsFor, GoalsAgainst, AverageGoalsFor, AverageGoalsAgainst, GoalRatio, MostGoalsScored, MostGoalsConceded, BiggestWinDifference, BiggestLossDifference, BiggestDrawSum, BiggestSumOfGoals, NumberDraws FROM playertable WHERE ' . k2_lb_player_where_sql() . ' ORDER BY GoalsFor DESC, rating DESC';
$result = k2_query_or_public_error($con, $query, 'ranked2 leaderboard'); 

mysqli_close($con);
?>

<?php
$k2LbWingActive = 'goals';
include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav.php";
?>

<?php k2_table_wrap_open(true); ?>
<?php $lbSort = k2_lb_table_sort_state(4); ?>

<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>

<thead>
    <tr>
        <th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">#</th>
        <th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
        <th<?php echo k2_lb_th(2, $lbSort, ''); ?> data-k2-sort="number">ELO rating</th>
        <th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals for" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_scored(), ENT_QUOTES, 'UTF-8'); ?>">GF</th>
        <th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals against" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_conceded(), ENT_QUOTES, 'UTF-8'); ?>">GA</th>
        <th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals scored per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_scored_avg(), ENT_QUOTES, 'UTF-8'); ?>">GF/g</th>
        <th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-tooltip-label="Goals conceded per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_conceded_avg(), ENT_QUOTES, 'UTF-8'); ?>">GA/g</th>
        <th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goal_ratio(), ENT_QUOTES, 'UTF-8'); ?>">Ratio</th>
        <th<?php echo k2_lb_th(9, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_most_scored(), ENT_QUOTES, 'UTF-8'); ?>">Max GF</th>
        <th<?php echo k2_lb_th(10, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_most_conceded(), ENT_QUOTES, 'UTF-8'); ?>">Max GA</th>
        <th<?php echo k2_lb_th(11, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_win_margin(), ENT_QUOTES, 'UTF-8'); ?>">Max win</th>
        <th<?php echo k2_lb_th(12, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_loss_margin(), ENT_QUOTES, 'UTF-8'); ?>">Max loss</th>
        <th<?php echo k2_lb_th(13, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goal_sum(), ENT_QUOTES, 'UTF-8'); ?>">Max sum</th>
        <th<?php echo k2_lb_th(14, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_biggest_draw(), ENT_QUOTES, 'UTF-8'); ?>">Max draw</th>
    </tr>
</thead>


<tbody class="black">
	<?php
    $rank = "1";
    while ($row = mysqli_fetch_row($result))
    {  
    ?>
    
    <tr>
        <td<?php echo k2_lb_td(0, $lbSort); ?>><?php echo $rank ?></td>
        <td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?>><?php echo k2_player_link($row[0], $row[1]); ?></td>
        <td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row[2]); ?></td>
        <td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($row[3]); ?></td>
        <td<?php echo k2_lb_td(4, $lbSort); ?>><?php echo k2_fmt_count($row[4], $row[3]); ?></td>
        <td<?php echo k2_lb_td(5, $lbSort); ?>><?php echo k2_fmt_count($row[5], $row[3]); ?></td>
        <td<?php echo k2_lb_td(6, $lbSort); ?>><?php echo k2_fmt_decimal($row[6], $row[3]); ?></td>
        <td<?php echo k2_lb_td(7, $lbSort); ?>><?php echo k2_fmt_decimal($row[7], $row[3]); ?></td>
        <td<?php echo k2_lb_td(8, $lbSort); ?>><?php
        	if (!k2_derived_games_started($row[3])) {
                echo k2_fmt_dash();
            } elseif (k2_db_is_null($row[8]) || (float) $row[8] == -1.0) {
                echo k2_fmt_dash();
            } else {
                echo k2_fmt_decimal($row[8], $row[3]);
            }
        ?></td>
        <td<?php echo k2_lb_td(9, $lbSort); ?>><?php echo k2_fmt_count($row[9], $row[3]); ?></td>
        <td<?php echo k2_lb_td(10, $lbSort); ?>><?php echo k2_fmt_count($row[10], $row[3]); ?></td>
        <td<?php echo k2_lb_td(11, $lbSort); ?>><?php echo k2_fmt_count($row[11], $row[3]); ?></td>
        <td<?php echo k2_lb_td(12, $lbSort); ?>><?php echo k2_fmt_count($row[12], $row[3]); ?></td>
        <td<?php echo k2_lb_td(13, $lbSort); ?>><?php echo k2_fmt_count($row[14], $row[3]); ?></td>
        <td<?php echo k2_lb_td(14, $lbSort); ?>><?php
        	if (!k2_derived_games_started($row[3]) || (int) $row[15] === 0) {
                echo k2_fmt_dash();
            } else {
                $drawSum = k2_db_is_null($row[13]) ? 0 : (int) $row[13];
                $half = (int) ($drawSum / 2);
                echo $half . '-' . $half;
            }
        ?></td>
    </tr> 
    
    <?php
	$rank++; 
    }  
    ?> 
</tbody>

</table>

<?php k2_table_wrap_close(); ?><!-- .k2-table-wrap -->

<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/lb_nav_end.php"; ?>

<br />
Max draw = biggest draw scoreline (equal goals each side)<br />
Max sum = most total goals in one game (both sides combined)<br />




</div><!-- .k2-page-nav -->
</body>
</html>
