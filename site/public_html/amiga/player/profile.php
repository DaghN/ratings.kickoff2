<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga player profile</title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-feast.css" rel="stylesheet" type="text/css" />
<link href="/stylesheets/player-feast-sections.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-feast-sections.css'); ?>" rel="stylesheet" type="text/css" />
<script src="/js/chart.umd.min.js"></script>
<script src="/js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="/js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script src="/js/chart-date-range.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-date-range.js'); ?>"></script>
<script type="text/javascript" src="/js/player-rating-history.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rating-history.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-rank-chart-core.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rank-chart-core.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-rating-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rating-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-rank-history.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rank-history.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-rank-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rank-chart.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site k2-player-wing player-feast-body">

<?php
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    http_response_code(404);
    exit('Player not found.');
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include __DIR__ . '/../../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_profile_blocks.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_profile_lb_slices.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_videos_lib.php';

try {
    $pm = amiga_player_load($con, $id);
} catch (RuntimeException $e) {
    mysqli_close($con);
    http_response_code(404);
    exit('Player not found.');
}

$profileCtx = amiga_snapshot_context_peek();
$profileMoments = amiga_player_moments_load($con, $id, $profileCtx);
$profileLbSliceRow = amiga_profile_lb_slices_load($con, $id);
amiga_player_publish_hero_context($pm, $con);
$k2AmigaPlayerHasVideos = amiga_player_has_videos($id, $con, $profileCtx);
mysqli_close($con);

$k2AmigaPlayerTabActive = 'profile';
$k2AmigaPlayerTabWiredAtCutoff = true;
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_wing_hub_nav.inc.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_hero.php'; ?>

<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_player_nav.php';

amiga_profile_render_lb_slices($profileLbSliceRow);
amiga_profile_render_moments($profileMoments, $playerId);
amiga_profile_render_rating_chart($playerId);
amiga_profile_render_rank_chart($playerId);
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>
