<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-feast.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-feast.css'); ?>" rel="stylesheet" type="text/css" />
<link href="/stylesheets/player-feast-sections.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-feast-sections.css'); ?>" rel="stylesheet" type="text/css" />
<link href="/stylesheets/player-feast-glance.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-feast-glance.css'); ?>" rel="stylesheet" type="text/css" />
<link href="/stylesheets/player-feast-story.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-feast-story.css'); ?>" rel="stylesheet" type="text/css" />
<link href="/stylesheets/player-feast-personal-bests.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-feast-personal-bests.css'); ?>" rel="stylesheet" type="text/css" />
<script src="/js/chart.umd.min.js"></script>
<script src="/js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="/js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script src="/js/k2-coarse-tap.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-coarse-tap.js'); ?>"></script>
<script src="/js/chart-date-range.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-date-range.js'); ?>"></script>
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>
<script type="text/javascript" src="/js/player-rating-history.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rating-history.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-rating-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-rating-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-games-month-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-games-month-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-goals-scored-histogram.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-goals-scored-histogram.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-top-opponents-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-top-opponents-chart.js'); ?>" defer="defer"></script>
<script src="/js/player-feast/player-calendar.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-feast/player-calendar.js'); ?>" defer></script>
<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>
<script src="/js/player-feast/player-calendar-weeks.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-feast/player-calendar-weeks.js'); ?>" defer></script>
</head>

<body class="k2-site k2-player-wing player-feast-body">

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';

$id = k2_positive_int_param('id', 'Invalid player id.');

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';
$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_feast_blocks.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_wing_hub_nav.inc.php';

try {
    $pm = player_feast_load_pm($con, $id);
} catch (RuntimeException $e) {
    k2_public_error('Player not found.', 404);
}

player_feast_expose_hero_vars($pm);

$playerId = (int) $pm['id'];
$heroMilestoneCounts = $pm['milestone_counts'] ?? null;
$heroMsCatalogTotal = (int) ($pm['milestone_catalog_total'] ?? 0);
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_hero.php'; ?>

<?php
$k2PlayerTabActive = 'profile';
$id = $playerId;
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_nav.php';

    player_feast_render_presence_career_duo($pm);
player_feast_render_story_lines($pm);
player_feast_render_played_days($playerId, (string) $pm['first_game_date_ymd'], (string) $pm['name']);
player_feast_render_played_weeks($playerId, (string) $pm['first_game_date_ymd'], (string) $pm['name']);
player_feast_render_peak_activity($pm);
player_feast_render_moments($pm);
player_feast_render_charts($playerId, (string) $pm['name']);
?>

</div><!-- .k2-page-nav -->

<?php mysqli_close($con); ?>
</body>
</html>
