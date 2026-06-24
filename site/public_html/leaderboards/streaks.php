<?php
/**
 * Streaks wing — match-result streaks only (rated play streaks live under Activity → In a row).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_result_streaks_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';

$result = k2_lb_result_streaks_query($con);
$queryError = $result === false;
mysqli_close($con);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_lb_sortable_table_head.inc.php'; ?>
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

<?php if ($queryError) { ?>
<p class="server-peak-period-leaderboard-status">Could not load streaks.</p>
<?php } else { ?>
<?php k2_table_wrap_open(true); ?>

<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="4" data-k2-default-direction="desc">

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
    while ($row = mysqli_fetch_assoc($result)) {
        $playerId = (int) $row['id'];
        $games = (int) ($row['NumberGames'] ?? 0);
        $winMeta = k2_lb_result_streaks_cell_meta($playerId, (int) ($row['LongestWinningStreak'] ?? 0), $games, 'win', $row);
        $undefMeta = k2_lb_result_streaks_cell_meta($playerId, (int) ($row['LongestNonLossStreak'] ?? 0), $games, 'non_loss', $row);
        $drawMeta = k2_lb_result_streaks_cell_meta($playerId, (int) ($row['LongestDrawingStreak'] ?? 0), $games, 'draw', $row);
        $decMeta = k2_lb_result_streaks_cell_meta($playerId, (int) ($row['LongestNonDrawStreak'] ?? 0), $games, 'non_draw', $row);
        $lossMeta = k2_lb_result_streaks_cell_meta($playerId, (int) ($row['LongestLosingStreak'] ?? 0), $games, 'loss', $row);
        $droughtMeta = k2_lb_result_streaks_cell_meta($playerId, (int) ($row['LongestNonWinStreak'] ?? 0), $games, 'non_win', $row);
        $aliasWin = k2_lb_result_streaks_sql_alias('win');
        $aliasUndef = k2_lb_result_streaks_sql_alias('non_loss');
        $aliasDraw = k2_lb_result_streaks_sql_alias('draw');
        $aliasDec = k2_lb_result_streaks_sql_alias('non_draw');
        $aliasLoss = k2_lb_result_streaks_sql_alias('loss');
        $aliasDrought = k2_lb_result_streaks_sql_alias('non_win');
    ?>
    <tr>
        <td></td>
        <td class="k2-table-cell--left"><?php echo k2_player_link($playerId, (string) $row['Name']); ?></td>
        <td><?php echo k2_fmt_int($row['Rating']); ?></td>
        <td><?php echo k2_fmt_games_played($games); ?></td>
        <?php k2_lb_activity_echo_tooltip_td($winMeta, (int) ($row['LongestWinningStreak'] ?? 0), '', k2_lb_activity_streak_achieved_tie_value($row[$aliasWin . '_end_at'] ?? null)); ?>
        <?php k2_lb_activity_echo_tooltip_td($undefMeta, (int) ($row['LongestNonLossStreak'] ?? 0), '', k2_lb_activity_streak_achieved_tie_value($row[$aliasUndef . '_end_at'] ?? null)); ?>
        <?php k2_lb_activity_echo_tooltip_td($drawMeta, (int) ($row['LongestDrawingStreak'] ?? 0), '', k2_lb_activity_streak_achieved_tie_value($row[$aliasDraw . '_end_at'] ?? null)); ?>
        <?php k2_lb_activity_echo_tooltip_td($decMeta, (int) ($row['LongestNonDrawStreak'] ?? 0), '', k2_lb_activity_streak_achieved_tie_value($row[$aliasDec . '_end_at'] ?? null)); ?>
        <?php k2_lb_activity_echo_tooltip_td($lossMeta, (int) ($row['LongestLosingStreak'] ?? 0), '', k2_lb_activity_streak_achieved_tie_value($row[$aliasLoss . '_end_at'] ?? null)); ?>
        <?php k2_lb_activity_echo_tooltip_td($droughtMeta, (int) ($row['LongestNonWinStreak'] ?? 0), '', k2_lb_activity_streak_achieved_tie_value($row[$aliasDrought . '_end_at'] ?? null)); ?>
    </tr>
    <?php } ?>
</tbody>

</table>

<?php k2_table_wrap_close(); ?><!-- .k2-table-wrap -->
<?php } ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav_end.php'; ?>

</div><!-- .k2-page-nav -->
</body>
</html>
