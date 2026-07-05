<?php
declare(strict_types=1);

$k2ScrollTargetId = (isset($_GET['v']) && (string) $_GET['v'] !== '') ? 'k2-tournament-video-player' : '';
?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga — orphan videos</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<link href="/stylesheets/amiga-tournament-videos.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament-videos.css'); ?>" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
<script type="text/javascript" src="/js/amiga-tournament-videos.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/amiga-tournament-videos.js'); ?>" defer="defer"></script>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_video_orphans_lib.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_video_orphans_render.inc.php';

$grouped = amiga_video_orphans_grouped_unassigned();
$excluded = amiga_video_orphans_excluded_rows();
$spotlight = amiga_video_orphans_spotlight_state();
$orphanCount = count(amiga_video_orphans_unassigned_rows());
?>

<?php
$k2HubChapterTitle = 'Orphan videos';
$k2HubChapterLede = 'Harvest leftovers and excluded rows — dev reference. Tournament assignment review complete (Jun 2026): nothing here should map to a tournament Videos tab. Remaining unassigned = tutorials, general KO2, duplicate candidates.';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
?>

<?php amiga_video_orphans_render_body($grouped, $excluded, $spotlight); ?>

<p class="k2-amiga-tournament-footnote" style="padding-bottom:1rem">
  <?php echo (int) $orphanCount; ?> unassigned · <?php echo count($excluded); ?> excluded · dev review page (not linked from hub).
</p>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>