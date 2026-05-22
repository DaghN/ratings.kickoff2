<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/profile_mock_load.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/pm2_blocks.php';
?>
<!DOCTYPE html>
<html lang="en" data-realm="online">
<head>
<meta charset="utf-8" />
<meta name="robots" content="noindex, nofollow" />
<title>Pass 2 · Atlas — <?php echo pm_h($pm['name']); ?></title>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_head.php'; ?>
<link href="stylesheets/profile-mock-v2.css" rel="stylesheet" type="text/css" />
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/pm2_scripts.php'; ?>
</head>
<body class="k2-site pm2-body">

<?php
include $_SERVER['DOCUMENT_ROOT'] . '/includes/site_header.php';
?>

<div class="k2-page-nav">

<?php pm2_render_nav_portal('c', 'The Atlas', (int) $pm['id']); ?>

<?php pm2_render_core($pm); ?>

<?php
$k2PlayerTabActive = 'profile';
include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_nav.php';
?>

<div class="pm2 pm2--c">

	<?php pm2_render_activity($pm); ?>
	<?php pm2_render_participation($pm); ?>
	<?php pm2_render_heatmap((int) $pm['id']); ?>
	<?php pm2_render_busiest($pm); ?>
	<?php pm2_render_moments($pm); ?>
	<?php pm2_render_charts_primary((int) $pm['id']); ?>
	<?php pm2_render_rivalry($pm); ?>
	<?php pm2_render_charts_rivalry((int) $pm['id']); ?>
	<?php pm2_render_charts_secondary((int) $pm['id']); ?>
	<?php pm2_render_stats_compact($pm); ?>

</div>

</div>

<?php mysqli_close($con); ?>
</body>
</html>
