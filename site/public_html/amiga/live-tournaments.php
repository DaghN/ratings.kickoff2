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
mysqli_close($con);

$amigaOrganizerUrl = '/amiga/ops/fixtures.php?once=amiga-fixtures-one-shot';
?>

<?php
$k2HubChapterTitle = 'Live tournaments';
$k2HubChapterLede = 'Watch kitchen leagues and community events while they are in progress — tables and fixtures update as results come in.';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
?>

<section class="k2-amiga-live-hub__watch" aria-labelledby="k2-amiga-live-watch-heading">
  <h2 id="k2-amiga-live-watch-heading" class="k2-panel-heading">Watch</h2>
  <?php amiga_live_tournament_index_render_table($liveRows); ?>
</section>

<section class="k2-amiga-live-hub__run k2-status-panel k2-status-panel--tight" aria-labelledby="k2-amiga-live-run-heading">
  <h2 id="k2-amiga-live-run-heading" class="k2-panel-heading">Run a tournament</h2>
  <p class="k2-amiga-live-hub__run-prose">
    Create a league, enter scores, and make it official when the night is done.
    This tooling is young — try it, break it gently, and tell us what you need.
  </p>
  <p class="k2-amiga-live-hub__run-cta">
    <a class="k2-link-star" href="<?php echo htmlspecialchars($amigaOrganizerUrl, ENT_QUOTES, 'UTF-8'); ?>">Open the tournament organizer &rarr;</a>
  </p>
</section>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>