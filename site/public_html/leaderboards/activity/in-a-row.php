<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings — Activity in a row</title>

<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_lb_sortable_table_head.inc.php'; ?>
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2HubTabActive = 'leaderboards';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_column_help.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_activity_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_play_streaks.php';

$result = k2_lb_activity_query_in_a_row($con);
$queryError = $result === false;
mysqli_close($con);
?>

<?php
$k2LbWingActive = 'activity';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav.php';

$k2LbActivityView = 'in-a-row';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_activity_nav.php';
?>

<?php if ($queryError) { ?>
<p class="server-peak-period-leaderboard-status">Could not load play streaks.</p>
<?php } else { ?>
<?php k2_table_wrap_open(true); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="4" data-k2-default-direction="desc">
<thead>
	<tr>
		<th data-k2-sort="number">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Player</th>
		<th data-k2-sort="number">ELO rating</th>
		<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
		<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_play_streak_help_day(), ENT_QUOTES, 'UTF-8'); ?>">Days</th>
		<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_play_streak_help_week(), ENT_QUOTES, 'UTF-8'); ?>">Weeks</th>
		<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_play_streak_help_month(), ENT_QUOTES, 'UTF-8'); ?>">Months</th>
		<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_play_streak_help_year(), ENT_QUOTES, 'UTF-8'); ?>">Years</th>
	</tr>
</thead>
<tbody class="black">
<?php
while ($row = mysqli_fetch_assoc($result)) {
    $games = (int) ($row['NumberGames'] ?? 0);
    $streakDays = (int) ($row['streak_days'] ?? 0);
    $streakWeeks = (int) ($row['streak_weeks'] ?? 0);
    $streakMonths = (int) ($row['streak_months'] ?? 0);
    $streakYears = (int) ($row['streak_years'] ?? 0);
    $dayMeta = k2_lb_activity_streak_cell_meta($streakDays, $games, $row['streak_days_start'] ?? null, 'day');
    $weekMeta = k2_lb_activity_streak_cell_meta($streakWeeks, $games, $row['streak_weeks_start'] ?? null, 'week');
    $monthMeta = k2_lb_activity_streak_cell_meta($streakMonths, $games, $row['streak_months_start'] ?? null, 'month');
    $yearMeta = k2_lb_activity_streak_cell_meta($streakYears, $games, $row['streak_years_start'] ?? null, 'year');
    ?>
	<tr>
		<td></td>
		<td class="k2-table-cell--left"><?php echo k2_player_link((int) $row['id'], (string) $row['Name']); ?></td>
		<td><?php echo k2_fmt_int($row['Rating']); ?></td>
		<td><?php echo k2_fmt_games_played($games); ?></td>
		<?php k2_lb_activity_echo_tooltip_td($dayMeta, $streakDays, '', k2_lb_activity_streak_achieved_tie_value($row['streak_days_achieved_at'] ?? null)); ?>
		<?php k2_lb_activity_echo_tooltip_td($weekMeta, $streakWeeks, '', k2_lb_activity_streak_achieved_tie_value($row['streak_weeks_achieved_at'] ?? null)); ?>
		<?php k2_lb_activity_echo_tooltip_td($monthMeta, $streakMonths, '', k2_lb_activity_streak_achieved_tie_value($row['streak_months_achieved_at'] ?? null)); ?>
		<?php k2_lb_activity_echo_tooltip_td($yearMeta, $streakYears, '', k2_lb_activity_streak_achieved_tie_value($row['streak_years_achieved_at'] ?? null)); ?>
	</tr>
<?php } ?>
</tbody>
</table>
<?php k2_table_wrap_close(); ?>
<?php } ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav_end.php'; ?>

</div><!-- .k2-page-nav -->
</body>
</html>
