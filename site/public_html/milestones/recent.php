<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';

include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$k2MsHubView = 'recent';
$k2MilestonesHubTitle = 'Milestones — Recent';

$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);
$tablesReady = k2_milestone_tables_ready($con);
$recentTier = null;
$recentUnlocks = [];

if ($tablesReady) {
	$recentTier = k2_milestone_recent_tier_param();
	$recentUnlocks = k2_milestone_recent_unlocks($con, K2_MILESTONE_RECENT_FEED_LIMIT, $recentTier);
}
mysqli_close($con);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/milestones_hub_shell_start.inc.php';
?>
<?php if (!$tablesReady) { ?>
	<section class="k2-ms-hub-placeholder">
		<p class="k2-ms-meta-hint">Milestone catalog data is not available on this database yet.</p>
	</section>
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
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/milestones_hub_shell_end.inc.php'; ?>
