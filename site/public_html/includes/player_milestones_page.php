<?php
/**
 * Shared Milestones wing page shell. Set $k2PlayerMilestonesView before require
 * (garden | chronology).
 */
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_lib.php';

$k2PlayerMilestonesView = player_milestones_parse_view($k2PlayerMilestonesView ?? null);
$view = $k2PlayerMilestonesView;

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="/stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_table_helpers.php';
k2_table_js_enqueue();
?>
<script type="text/javascript" src="/js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site k2-player-wing">

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$id = k2_positive_int_param('id', 'Invalid player id.');
$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_hero_vars.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';

if (!isset($heroMsCatalogTotal) || (int) $heroMsCatalogTotal < 1) {
    $heroMsCatalogTotal = k2_milestone_catalog_total($con);
}

include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_wing_hub_nav.inc.php';
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_hero.php'; ?>

<?php
$k2PlayerTabActive = 'milestones';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_nav.php';

include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_nav.php';

if ($view !== 'chronology') {
    $gardenByTier = k2_milestone_garden_by_tier($con, $id);
    ?>
<div class="k2-ms-garden">
    <?php k2_milestone_render_garden($gardenByTier); ?>
</div>
    <?php
} elseif (k2_milestone_tables_ready($con)) {
    $chronologyTier = k2_milestone_recent_tier_param();
    $chronologyUnlocks = k2_milestone_player_unlocks($con, $id, $chronologyTier);
    ?>
<div class="k2-ms-chronology-feed k2-ms-recent-feed">
	<div class="k2-ms-recent-cluster">
		<?php
        k2_milestone_render_player_chronology_tier_filter($id, $chronologyTier);
        k2_milestone_render_player_chronology_feed($chronologyUnlocks, $chronologyTier);
        ?>
	</div>
</div>
    <?php
} else {
    ?>
<p class="k2-ms-meta-hint">Milestone data is not available on this database yet.</p>
    <?php
}

mysqli_close($con);
?>

</div><!-- .k2-chrome-tabs.k2-player-milestones -->

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_site_end.inc.php'; ?>
</body>
</html>
