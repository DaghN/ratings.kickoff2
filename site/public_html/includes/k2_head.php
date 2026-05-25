<?php
/**
 * Shared <head> assets for body.k2-site pages. Include inside <head> after <title>.
 * Optional before include: $k2RankedCloak = true on ranked1-5 and ranked7 (table FOUC cloak).
 */
$k2DocRoot = $_SERVER['DOCUMENT_ROOT'];
?>
<link href="stylesheets/theme.css?v=<?php echo (int) @filemtime($k2DocRoot . '/stylesheets/theme.css'); ?>" rel="stylesheet" type="text/css" />
<link href="stylesheets/player-hero-rank.css?v=<?php echo (int) @filemtime($k2DocRoot . '/stylesheets/player-hero-rank.css'); ?>" rel="stylesheet" type="text/css" />
<?php include $k2DocRoot . '/includes/theme_boot_head.php'; ?>
<?php include $k2DocRoot . '/includes/favicon_head.php'; ?>
<?php if (!empty($k2RankedCloak)) {
	include $k2DocRoot . '/includes/ranked_table_cloak_head.php';
} ?>
