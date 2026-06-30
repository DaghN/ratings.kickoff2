<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Video glyph picker (dev)</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_video_glyph_picker_lib.php';

$glyphCount = count(amiga_video_glyph_picker_catalog());
?>

<h1 class="k2-page-title">Video glyph picker</h1>
<p class="k2-video-glyph-picker-lede">Dev-only comparison for <strong>C06</strong> chronology video glyphs. Each row uses the same dummy tournament table shape as <code>/amiga/tournaments.php</code>; hover the glyph to preview production styling. Highlighted row is the <strong>current shipped</strong> glyph (<code>ph:play-circle-fill</code>). Pick an icon id and tell an agent which row to ship.</p>
<p class="k2-video-glyph-picker-lede" style="margin-top:-0.5rem"><strong><?php echo (int) $glyphCount; ?></strong> candidates · SVG bodies from <a class="k2-link-star" href="https://icon-sets.iconify.design/?query=film+video" rel="noopener noreferrer" target="_blank">Iconify</a> (dev page only).</p>

<?php amiga_video_glyph_picker_render_table(); ?>

</body>
</html>