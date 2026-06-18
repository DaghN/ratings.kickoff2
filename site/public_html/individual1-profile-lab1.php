<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="robots" content="noindex, nofollow" />
<title>Kick Off 2 ratings — Profile lab 1</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/player-feast-sections.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/player-feast-glance.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-feast-glance.css'); ?>" rel="stylesheet" type="text/css" />
<link href="stylesheets/player-feast-personal-bests.css" rel="stylesheet" type="text/css" />
<link href="stylesheets/player-feast-lab1.css" rel="stylesheet" type="text/css" />
<script src="js/chart.umd.min.js"></script>
<script src="js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script src="js/chart-date-range.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-date-range.js'); ?>"></script>
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>
<script type="text/javascript" src="js/player-rating-history.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rating-history.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-rating-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rating-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-games-month-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-games-month-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-top-opponents-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-top-opponents-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-head-to-head-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-head-to-head-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-compare-rating-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-compare-rating-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="js/player-h2h-opponent-search.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-h2h-opponent-search.js'); ?>" defer="defer"></script>
<script src="js/player-feast/player-calendar.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-feast/player-calendar.js'); ?>" defer></script>
<script src="js/player-feast/player-calendar-weeks.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-feast/player-calendar-weeks.js'); ?>" defer></script>
</head>

<body class="k2-site player-feast-body player-feast-body--lab1">

<?php
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    exit();
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
$con = new mysqli($dbhost, $username, $password, $database, $dbportnum);
if (mysqli_connect_errno()) {
    die('Failed to connect to MySQL: ' . mysqli_connect_error());
}
$con->set_charset('utf8mb4');
$con->query("SET time_zone = '+00:00'");

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_load_lab1.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_blocks_lab1.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';

try {
    $pm = player_feast_load_pm_lab1($con, $id);
} catch (RuntimeException $e) {
    exit();
}

player_feast_expose_hero_vars_lab1($pm);

$playerId = (int) $pm['id'];
$heroMilestoneCounts = null;
$heroMsCatalogTotal = 0;
if ((int) $pm['games'] >= 1) {
    $heroMsCatalogTotal = k2_milestone_catalog_total($con);
    $heroMilestoneCounts = k2_milestone_player_counts($con, $playerId);
}
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_hero.php'; ?>

<?php
$k2PlayerTabActive = 'profile';
$id = $playerId;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_nav.php';

player_feast_render_lab_banner(1);
player_feast_render_presence_career_duo($pm);
player_feast_render_honours($pm);
player_feast_render_played_days($playerId, (string) $pm['first_game_date_ymd']);
player_feast_render_played_weeks($playerId, (string) $pm['first_game_date_ymd']);
player_feast_render_peak_activity($pm);
player_feast_render_moments($pm);
player_feast_render_charts($pm);
?>

</div>

<?php mysqli_close($con); ?>
</body>
</html>
