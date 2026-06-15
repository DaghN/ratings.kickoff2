<?php
/**
 * Shared Opponents wing page shell. Set $k2PlayerOpponentsView before require
 * (h2h | wdl | goals | dds).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_opponents_lib.php';

$k2PlayerOpponentsView = player_opponents_parse_view($k2PlayerOpponentsView ?? null);
$view = $k2PlayerOpponentsView;

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">

<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<title>Kick Off 2 ratings</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>

<?php if ($view === 'h2h') { ?>
<link rel="stylesheet" href="/stylesheets/player-opponents-h2h-poster.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-opponents-h2h-poster.css'); ?>" />
<link rel="stylesheet" href="/stylesheets/player-opponents-h2h-moments.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-opponents-h2h-moments.css'); ?>" />
<link rel="stylesheet" href="/stylesheets/player-opponents-h2h-scoreline-heatmap.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-opponents-h2h-scoreline-heatmap.css'); ?>" />
<script src="/js/chart.umd.min.js"></script>
<script src="/js/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="/js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>
<script src="/js/chart-date-range.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-date-range.js'); ?>"></script>
<script type="text/javascript" src="/js/k2-archive-listbox.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-archive-listbox.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-opponents-h2h.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-opponents-h2h.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-head-to-head-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-head-to-head-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-head-to-head-goals-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-head-to-head-goals-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-h2h-total-goals-histogram.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-h2h-total-goals-histogram.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-h2h-scoreline-heatmap.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-h2h-scoreline-heatmap.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-compare-rating-chart.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-compare-rating-chart.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/player-goals-scored-histogram.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-goals-scored-histogram.js'); ?>" defer="defer"></script>
<?php } ?>

<script type="text/javascript" src="/js/k2-table.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/k2-table.js'); ?>" defer="defer"></script>

</head>

<body class="k2-site k2-player-wing">

<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_opponents_tables.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_opponents_h2h.php';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$id = k2_positive_int_param('id', 'Invalid player id.');
$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_hero_vars.php';

$k2PageNavClass = 'k2-page-nav--opponents';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php';

?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_hero.php'; ?>

<?php
$k2PlayerTabActive = 'opponents';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_nav.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_opponents_nav.php';

if ($view === 'h2h') {
    $h2hOpponentId = player_opponents_h2h_parse_opponent_id($_GET['opponent'] ?? null, $id);
    $h2hDefaultOpponent = !array_key_exists('opponent', $_GET);
    $h2hPlayerName = isset($Name) ? (string) $Name : ('#' . $id);
    player_opponents_render_h2h_panel($con, $id, $h2hPlayerName, $h2hOpponentId, $h2hDefaultOpponent);
} elseif ($view === 'goals') {
    player_opponents_render_goals_table($con, $id);
} elseif ($view === 'dds') {
    player_opponents_render_dds_table($con, $id);
} else {
    player_opponents_render_wdl_table($con, $id);
}

mysqli_close($con);

?>

</div><!-- .k2-chrome-tabs.k2-player-opponents -->

</div><!-- .k2-page-nav -->

</body>

</html>
