<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings — Activity participation</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
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
<div class="k2-table-wrap">
<table class="k2-table k2-table--numeric-default k2-table--calm-stats ranked-pages-table ranked-table-pending" data-k2-table="sortable" data-k2-autorank="true" data-k2-anchor-col="2" data-k2-default-sort="4" data-k2-default-direction="desc">
<thead>
	<tr>
		<th data-k2-sort="number">#</th>
		<th class="k2-table-cell--left" data-k2-sort="text">Player</th>
		<th data-k2-sort="number">ELO rating</th>
		<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_games(), ENT_QUOTES, 'UTF-8'); ?>">Games</th>
		<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_active_days(), ENT_QUOTES, 'UTF-8'); ?>">Active days</th>
		<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_active_weeks(), ENT_QUOTES, 'UTF-8'); ?>">Active weeks</th>
		<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_active_months(), ENT_QUOTES, 'UTF-8'); ?>">Active months</th>
		<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_active_years(), ENT_QUOTES, 'UTF-8'); ?>">Active years</th>
		<th data-k2-sort="number" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_participation_longevity(), ENT_QUOTES, 'UTF-8'); ?>">Longevity</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-sort-first="asc" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_first_rated_game(), ENT_QUOTES, 'UTF-8'); ?>">First game</th>
		<th class="k2-table-cell--left" data-k2-sort="text" data-k2-help="<?php echo htmlspecialchars(k2_lb_help_last_rated_game(), ENT_QUOTES, 'UTF-8'); ?>">Last game</th>
	</tr>
</thead>
<tbody class="black">
<?php
while ($row = mysqli_fetch_assoc($result)) {
    $firstDay = $row['first_rated_day'] ?? null;
    $lastDay = $row['last_rated_day'] ?? null;
    $longevityDays = k2_db_is_null($row['longevity_days']) ? null : (int) $row['longevity_days'];
    $games = (int) ($row['NumberGames'] ?? 0);
    ?>
	<tr>
		<td></td>
		<td class="k2-table-cell--left"><?php echo k2_player_link((int) $row['id'], (string) $row['Name']); ?></td>
		<td><?php echo k2_fmt_int($row['Rating']); ?></td>
		<td><?php echo k2_fmt_games_played($games); ?></td>
		<td><?php echo k2_fmt_int($row['active_days']); ?></td>
		<td><?php echo k2_fmt_int($row['active_weeks']); ?></td>
		<td><?php echo k2_fmt_int($row['active_months']); ?></td>
		<td><?php echo k2_fmt_int($row['active_years']); ?></td>
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
</div>
<?php } ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav_end.php'; ?>

</div><!-- .k2-page-nav -->
</body>
</html>
