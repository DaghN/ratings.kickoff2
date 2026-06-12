<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">

<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<title>Kick Off 2 ratings</title>



<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/k2_head.php"; ?>

<script src="js/chart.umd.min.js"></script>

<script src="js/chartjs-adapter-date-fns.bundle.min.js"></script>

<script src="js/chart-theme.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-theme.js'); ?>"></script>

<script src="js/chart-date-range.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/chart-date-range.js'); ?>"></script>

<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

<script type="text/javascript" src="js/activity-charts-v2.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/activity-charts-v2.js'); ?>" defer="defer"></script>



</head>



<body class="k2-site k2-activity-charts">



<?php include $_SERVER["DOCUMENT_ROOT"] . "/includes/site_header.php"; ?>



<?php

$k2HubTabActive = 'activity';

include $_SERVER["DOCUMENT_ROOT"] . "/includes/hub_nav.php";

$k2HubChapterTitle = 'Online activity';
$k2HubChapterLede = 'How much online Kick Off 2 do we play? How many of us are active, and who become regulars? Do the busiest players fade or return? The charts on this page follow these rhythms from the past few weeks back to the first rated online game.';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';

?>



<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/server_activity_summary.php'; ?>



<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/server_activity_chart_panels.php'; ?>



</div><!-- .k2-page-nav -->



</body>

</html>


