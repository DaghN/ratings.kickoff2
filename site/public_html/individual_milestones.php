<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en" data-realm="online">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Kick Off 2 ratings</title>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/player-milestones.css?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/stylesheets/player-milestones.css'); ?>" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="js/player-search.js" defer="defer"></script>

</head>

<body class="k2-site">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php'; ?>

<div class="k2-page-nav">

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_safety.php';
include $_SERVER['DOCUMENT_ROOT'] . '/../config/ko2unitydb_config.php';

$id = k2_positive_int_param('id', 'Invalid player id.');
$con = k2_db_connect_or_public_error($dbhost, $username, $password, $database, $dbportnum);

include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_hero_vars.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/player_milestones_helpers.php';

$numberGames = isset($NumberGames) ? (int) $NumberGames : 0;
$gardenByTier = k2_milestone_garden_by_tier($con, $id);
$counts = $heroMilestoneCounts ?? null;
if (!isset($heroMsCatalogTotal) || (int) $heroMsCatalogTotal < 1) {
	$heroMsCatalogTotal = k2_milestone_catalog_total($con);
}
$milestoneCatalogTotal = (int) $heroMsCatalogTotal;
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_hero.php'; ?>

<?php mysqli_close($con); ?>

<?php
$k2PlayerTabActive = 'milestones';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_nav.php';
?>

<div class="k2-ms-garden">
<?php if ($numberGames < 1) { ?>
	<p class="k2-ms-meta-hint">This player has no rated games yet — milestones unlock once you join the ladder.</p>
<?php } elseif ($counts !== null) { ?>
	<p class="k2-ms-meta-hint">
		<span class="k2-ms-meta-hint__unlocked"><?php echo (int) $counts['total']; ?></span> of <?php echo (int) $milestoneCatalogTotal; ?> milestones unlocked.
		Light up the garden over your career — locked cards still show what each feat takes.
	</p>
<?php } ?>
<?php k2_milestone_render_garden($gardenByTier); ?>
</div>

</div><!-- .k2-page-nav -->

</body>
</html>
