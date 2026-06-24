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

<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="4" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">#</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">ELO rating</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals for" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_scored(), ENT_QUOTES, 'UTF-8'); ?>">GF</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals against" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_conceded(), ENT_QUOTES, 'UTF-8'); ?>">GA</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals scored per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_scored_avg(), ENT_QUOTES, 'UTF-8'); ?>">GF/g</th>
        <th data-k2-sort="number" data-k2-tooltip-label="Goals conceded per game" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goals_conceded_avg(), ENT_QUOTES, 'UTF-8'); ?>">GA/g</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goal_ratio(), ENT_QUOTES, 'UTF-8'); ?>">Ratio</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_most_scored(), ENT_QUOTES, 'UTF-8'); ?>">Max GF</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_most_conceded(), ENT_QUOTES, 'UTF-8'); ?>">Max GA</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_win_margin(), ENT_QUOTES, 'UTF-8'); ?>">Max win</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_loss_margin(), ENT_QUOTES, 'UTF-8'); ?>">Max loss</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_goal_sum(), ENT_QUOTES, 'UTF-8'); ?>">Max sum</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_biggest_draw(), ENT_QUOTES, 'UTF-8'); ?>">Max draw</th>
    </tr>
</thead>


<tbody class="black">
	<?php
    $rank = "1";
    while ($row = mysqli_fetch_row($result))
    {  
    ?>
    
    <tr>
        <td><?php echo $rank ?></td>
        <td class="k2-table-cell--left"><?php echo k2_player_link($row[0], $row[1]); ?></td>
        <td><?php echo k2_fmt_int($row[2]); ?></td>
        <td><?php echo k2_fmt_games_played($row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[4], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[5], $row[3]); ?></td>
        <td><?php echo k2_fmt_decimal($row[6], $row[3]); ?></td>
        <td><?php echo k2_fmt_decimal($row[7], $row[3]); ?></td>
        <td><?php
        	if (!k2_derived_games_started($row[3])) {
                echo k2_fmt_dash();
            } elseif (k2_db_is_null($row[8]) || (float) $row[8] == -1.0) {
                echo k2_fmt_dash();
            } else {
                echo k2_fmt_decimal($row[8], $row[3]);
            }
        ?></td>
        <td><?php echo k2_fmt_count($row[9], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[10], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[11], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[12], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[14], $row[3]); ?></td>
        <td><?php
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
