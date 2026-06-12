<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';

if (isset($_GET['key'])) {
	$legacyKey = k2_milestone_key_param('key');
	if ($legacyKey !== null) {
		header('Location: ' . k2_route('milestone', ['key' => $legacyKey]), true, 302);
		exit;
	}
}

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$hubView = isset($_GET['view']) ? strtolower(trim((string) $_GET['view'])) : 'recent';
if ($hubView !== 'recent' && $hubView !== 'catalog') {
	$hubView = 'recent';
}

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$tablesReady = k2_milestone_tables_ready($con);
$catalogTotal = k2_milestone_catalog_total($con);
$recentTier = null;
$recentUnlocks = [];

$k2MsHubView = $hubView;
$k2HubTabActive = 'milestones';

if ($tablesReady) {
	if ($hubView === 'catalog') {
		$catalogCards = k2_milestone_catalog_by_holders($con);
	} else {
		$recentTier = k2_milestone_recent_tier_param();
		$recentUnlocks = k2_milestone_recent_unlocks($con, K2_MILESTONE_RECENT_FEED_LIMIT, $recentTier);
	}
}
mysqli_close($con);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings — Milestones</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/hub_nav.php'; ?>
<?php
$k2HubChapterTitle = 'Milestones';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_hub_chapter.inc.php';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/milestones_hub_nav.php';
?>

<main class="k2-ms-hub" id="main">
<?php if (!$tablesReady) { ?>
	<section class="k2-ms-hub-placeholder">
		<p class="k2-ms-meta-hint">Milestone catalog data is not available on this database yet.</p>
	</section>
<?php } elseif ($hubView === 'catalog') { ?>
	<header class="k2-hub-page-intro-head">
		<p class="k2-hub-page-intro">All <span class="blue"><?php echo (int) $catalogTotal; ?></span> milestones sorted by tier and how rare they are (most common first).<br />
			Open any card for the achiever list and charts.</p>
	</header>
	<?php k2_milestone_render_catalog_grid($catalogCards); ?>
<?php } else { ?>
	<div class="k2-ms-recent-feed">
		<div class="k2-ms-recent-cluster">
		<?php
		k2_milestone_render_recent_tier_filter($recentTier);
		k2_milestone_render_recent_feed($recentUnlocks, $recentTier);
		?>
		</div>
	</div>
<?php } ?>
</main>

</div><!-- .k2-page-nav -->

</body>
</html>
