<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$k2MsHubView = 'catalog';
$k2MilestonesHubTitle = 'Milestones — Catalog';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$tablesReady = k2_milestone_tables_ready($con);
$catalogTotal = k2_milestone_catalog_total($con);
$catalogCards = [];

if ($tablesReady) {
	$catalogCards = k2_milestone_catalog_by_holders($con);
}
mysqli_close($con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/milestones_hub_shell_start.inc.php';
?>
<?php if (!$tablesReady) { ?>
	<section class="k2-ms-hub-placeholder">
		<p class="k2-ms-meta-hint">Milestone catalog data is not available on this database yet.</p>
	</section>
<?php } else { ?>
	<header class="k2-hub-page-intro-head">
		<p class="k2-hub-page-intro">All <span class="blue"><?php echo (int) $catalogTotal; ?></span> milestones sorted by tier and how rare they are (most common first).<br />
			Open any card for the achiever list and charts.</p>
	</header>
	<?php k2_milestone_render_catalog_grid($catalogCards); ?>
<?php } ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/milestones_hub_shell_end.inc.php'; ?>
