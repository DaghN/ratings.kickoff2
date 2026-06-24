<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav_lib.php';
amiga_snapshot_redirect_present_only_page();
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="amiga">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Amiga — Live tournaments</title>
<?php $k2RankedCloak = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/amiga-tournament.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/amiga-tournament.css'); ?>" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_sortable_table_assets_head.inc.php'; ?>
</head>
<body class="k2-site">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php
$k2AmigaHubTabActive = 'live-tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_hub_nav.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_tournament_lib.php';
include __DIR__ . '/../../config/ko2amiga_config.php';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$con->query("SET time_zone = '+00:00'");

$liveRows = amiga_live_tournament_index_rows($con);
$allowlistConfigured = amiga_live_tournament_allowlist_ids() !== [];
mysqli_close($con);
?>

<?php
$k2HubChapterTitle = 'Live tournaments';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
?>

<?php amiga_live_tournament_index_render_table($liveRows, $allowlistConfigured); ?>

<p class="k2-amiga-live-view__ops-note k2-amiga-tournament-footnote" style="padding-bottom:1rem">
  Operators: fixture management and result entry use the internal
  <a href="/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot">fixture manager</a> (password required).
</p>

</div><!-- .k2-page-nav -->

</body>
</html>
