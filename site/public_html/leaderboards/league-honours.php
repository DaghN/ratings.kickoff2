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

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->set_charset('utf8mb4');

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/league_honours_leaderboard.php';

$honoursView = k2_lb_league_honours_parse_view();
$filterOpts = k2_lb_filter_opts();
if ($honoursView['cup'] === 'overall' && isset($_GET['grain'])) {
    header('Location: ' . k2_lb_league_honours_href('overall', null, $filterOpts), true, 302);
    exit;
}
if ($honoursView['cup'] !== 'overall' && !isset($_GET['grain'])) {
    header(
        'Location: ' . k2_lb_league_honours_href($honoursView['cup'], $honoursView['grain'] ?? 'day', $filterOpts),
        true,
        302
    );
    exit;
}

$queryError = null;
$honoursRows = k2_lb_league_honours_rows($con, $honoursView, $queryError);
$dataReady = $honoursView['cup'] === 'overall'
    ? k2_status_table_exists($con, 'player_league_totals')
    : k2_status_table_exists($con, 'player_league_slice_totals');

mysqli_close($con);

$k2LbWingActive = 'league-honours';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/league_honours_panel.php';
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/lb_nav_end.php'; ?>

</div>

</body>
</html>
