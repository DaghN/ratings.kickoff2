<?php
/**
 * Streaks wing — match-result streaks only (rated play streaks live under Activity → In a row).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_player_filters.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
$query = 'SELECT `id`, `Name`, `Rating`, `NumberGames`, '
    . '`LongestWinningStreak`, `LongestNonLossStreak`, `LongestDrawingStreak`, '
    . '`LongestNonDrawStreak`, `LongestLosingStreak`, `LongestNonWinStreak` '
    . 'FROM `playertable` WHERE ' . k2_lb_player_where_sql() . ' '
    . 'ORDER BY `LongestWinningStreak` DESC, `Rating` DESC';
$result = k2_query_or_public_error($con, $query, 'ranked4 leaderboard');

mysqli_close($con);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2HubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php';
?>

<?php
$k2LbWingActive = 'streaks';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav.php';
?>

<div class="k2-table-wrap">

<table class="k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table ranked-table-pending" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="4" data-k2-default-direction="desc">

<thead>
    <tr>
        <th data-k2-sort="number">#</th>
        <th class="k2-table-cell--left" data-k2-sort="text">Player</th>
        <th data-k2-sort="number">ELO rating</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_streak_wins(), ENT_QUOTES, 'UTF-8'); ?>">Wins</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_streak_undefeated(), ENT_QUOTES, 'UTF-8'); ?>">Undefeated</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_streak_draws(), ENT_QUOTES, 'UTF-8'); ?>">Draws</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_streak_decided(), ENT_QUOTES, 'UTF-8'); ?>">Decided</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_streak_losses(), ENT_QUOTES, 'UTF-8'); ?>">Losses</th>
        <th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_streak_win_drought(), ENT_QUOTES, 'UTF-8'); ?>">Win drought</th>
    </tr>
</thead>

<tbody class="black">
	<?php
    while ($row = mysqli_fetch_row($result)) {
    ?>
    <tr>
        <td></td>
        <td class="k2-table-cell--left"><?php echo k2_player_link($row[0], $row[1]); ?></td>
        <td><?php echo k2_fmt_int($row[2]); ?></td>
        <td><?php echo k2_fmt_games_played($row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[4], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[5], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[6], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[7], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[8], $row[3]); ?></td>
        <td><?php echo k2_fmt_count($row[9], $row[3]); ?></td>
    </tr>
    <?php } ?>
</tbody>

</table>

</div><!-- .k2-table-wrap -->

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav_end.php'; ?>

</div><!-- .k2-page-nav -->
</body>
</html>
