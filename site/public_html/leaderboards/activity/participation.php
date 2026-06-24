<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings — Activity participation</title>

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

$participationReady = k2_lb_activity_participation_ready($con);
$result = $participationReady ? k2_lb_activity_query_participation($con) : false;
$queryError = $participationReady && $result === false;
mysqli_close($con);
?>

<?php
$k2LbWingActive = 'activity';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav.php';

$k2LbActivityView = 'participation';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_activity_nav.php';
?>

<?php if (!$participationReady) { ?>
<p class="server-peak-period-leaderboard-status">Participation data is not available on this database yet.</p>
<?php } elseif ($queryError) { ?>
<p class="server-peak-period-leaderboard-status">Could not load activity participation.</p>
<?php } else { ?>
<?php k2_table_wrap_open(true); ?>
<?php $lbSort = k2_lb_table_sort_state(4); ?>
<table class="<?php echo k2_h(k2_table_ranked_leaderboard_class()); ?>" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="<?php echo $lbSort['anchor']; ?>" data-k2-default-sort="<?php echo $lbSort['sort_col']; ?>" data-k2-default-direction="<?php echo k2_h($lbSort['sort_dir']); ?>"<?php echo k2_table_skip_initial_sort_attr(4); ?>>
<thead>
	<tr>
		<th<?php echo k2_lb_th(0, $lbSort, ''); ?> data-k2-sort="number">#</th>
		<th<?php echo k2_lb_th(1, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text">Player</th>
		<th<?php echo k2_lb_th_elo(2, $lbSort); ?> data-k2-sort="number"<?php echo k2_lb_elo_column_help_attrs(); ?>>Elo</th>
		<th<?php echo k2_lb_th(3, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
		<th<?php echo k2_lb_th(4, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_active_days(), ENT_QUOTES, 'UTF-8'); ?>">Active days</th>
		<th<?php echo k2_lb_th(5, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_active_weeks(), ENT_QUOTES, 'UTF-8'); ?>">Active weeks</th>
		<th<?php echo k2_lb_th(6, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_active_months(), ENT_QUOTES, 'UTF-8'); ?>">Active months</th>
		<th<?php echo k2_lb_th(7, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_active_years(), ENT_QUOTES, 'UTF-8'); ?>">Active years</th>
		<th<?php echo k2_lb_th(8, $lbSort, ''); ?> data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_participation_longevity(), ENT_QUOTES, 'UTF-8'); ?>">Longevity</th>
		<th<?php echo k2_lb_th(9, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text" data-k2-sort-first="asc" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_first_rated_game(), ENT_QUOTES, 'UTF-8'); ?>">First game</th>
		<th<?php echo k2_lb_th(10, $lbSort, 'k2-table-cell--left'); ?> data-k2-sort="text" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_last_rated_game(), ENT_QUOTES, 'UTF-8'); ?>">Last game</th>
	</tr>
</thead>
<tbody class="black">
<?php
while ($row = mysqli_fetch_assoc($result)) {
    $firstDay = $row['first_rated_day'] ?? null;
    $lastDay = $row['last_rated_day'] ?? null;
    $longevityDays = k2_db_is_null($row['longevity_days']) ? null : (int) $row['longevity_days'];
    $games = (int) ($row['NumberGames'] ?? 0);
    $activeDays = (int) ($row['active_days'] ?? 0);
    $activeWeeks = (int) ($row['active_weeks'] ?? 0);
    $activeMonths = (int) ($row['active_months'] ?? 0);
    $activeYears = (int) ($row['active_years'] ?? 0);
    $playerId = (int) $row['id'];
    ?>
	<tr>
		<td<?php echo k2_lb_td(0, $lbSort); ?>></td>
		<td<?php echo k2_lb_td(1, $lbSort, 'k2-table-cell--left'); ?>><?php echo k2_player_link($playerId, (string) $row['Name']); ?></td>
		<td<?php echo k2_lb_td(2, $lbSort); ?>><?php echo k2_fmt_int($row['Rating']); ?></td>
		<td<?php echo k2_lb_td(3, $lbSort); ?>><?php echo k2_fmt_games_played($games); ?></td>
		<?php k2_lb_activity_echo_count_td($activeDays, $activeDays > 0 ? k2_lb_activity_participation_period_tie_value($row['active_days_reached_at'] ?? null) : null); ?>
		<?php k2_lb_activity_echo_count_td($activeWeeks, $activeWeeks > 0 ? k2_lb_activity_participation_period_tie_value($row['active_weeks_reached_at'] ?? null) : null); ?>
		<?php k2_lb_activity_echo_count_td($activeMonths, $activeMonths > 0 ? k2_lb_activity_participation_period_tie_value($row['active_months_reached_at'] ?? null) : null); ?>
		<?php k2_lb_activity_echo_count_td($activeYears, $activeYears > 0 ? k2_lb_activity_participation_period_tie_value($row['active_years_reached_at'] ?? null) : null); ?>
		<td data-k2-sort-value="<?php echo $longevityDays ?? ''; ?>"><?php echo htmlspecialchars(k2_lb_activity_format_longevity($longevityDays), ENT_QUOTES, 'UTF-8'); ?></td>
		<td class="k2-table-cell--left" data-k2-sort-value="<?php echo htmlspecialchars((string) ($firstDay ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php
            echo htmlspecialchars(k2_lb_activity_format_rated_day($firstDay ? (string) $firstDay : null), ENT_QUOTES, 'UTF-8');
        ?></td>
		<td class="k2-table-cell--left" data-k2-sort-value="<?php echo htmlspecialchars((string) ($lastDay ?? ''), ENT_QUOTES, 'UTF-8'); ?>"><?php
            echo htmlspecialchars(k2_lb_activity_format_rated_day($lastDay ? (string) $lastDay : null), ENT_QUOTES, 'UTF-8');
        ?></td>
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
