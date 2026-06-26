<?php
/**
 * Shared <head> assets for body.k2-site pages. Include inside <head> after <title>.
 * Optional before include: $k2RankedCloak = true on ranked1-5 and ranked7 (table FOUC cloak).
 */
$k2DocRoot = $_SERVER['DOCUMENT_ROOT'];
?>
<?php include $k2DocRoot . '/includes/k2_fonts_head.php'; ?>
<link href="/stylesheets/k2-fonts.css?v=<?php echo (int) @filemtime($k2DocRoot . '/stylesheets/k2-fonts.css'); ?>" rel="stylesheet" type="text/css" />
<link href="/stylesheets/theme.css?v=<?php echo (int) @filemtime($k2DocRoot . '/stylesheets/theme.css'); ?>" rel="stylesheet" type="text/css" />
<link href="/stylesheets/player-hero-rank.css?v=<?php echo (int) @filemtime($k2DocRoot . '/stylesheets/player-hero-rank.css'); ?>" rel="stylesheet" type="text/css" />
<?php include $k2DocRoot . '/includes/theme_boot_head.php'; ?>
<?php include $k2DocRoot . '/includes/k2_carry_scroll_restore.php'; ?>
<?php include $k2DocRoot . '/includes/favicon_head.php'; ?>
<script type="text/javascript" src="/js/k2-carry-scroll.js?v=<?php echo (int) @filemtime($k2DocRoot . '/js/k2-carry-scroll.js'); ?>"></script>
<?php
$k2TurboPath = $k2DocRoot . '/js/turbo.es2017-umd.js';
$k2TurboBootPath = $k2DocRoot . '/js/k2-turbo-boot.js';
$k2TurboCssPath = $k2DocRoot . '/stylesheets/k2-turbo.css';
if (is_file($k2TurboCssPath)) {
	echo '<link href="/stylesheets/k2-turbo.css?v=' . (int) filemtime($k2TurboCssPath) . '" rel="stylesheet" type="text/css" />' . "\n";
}
if (is_file($k2TurboPath)) {
	echo '<script type="text/javascript" src="/js/turbo.es2017-umd.js?v=' . (int) filemtime($k2TurboPath) . '" defer="defer"></script>' . "\n";
}
if (is_file($k2TurboBootPath)) {
	echo '<script type="text/javascript" src="/js/k2-turbo-boot.js?v=' . (int) filemtime($k2TurboBootPath) . '" defer="defer"></script>' . "\n";
}
$k2JukeboxCssPath = $k2DocRoot . '/stylesheets/k2-jukebox.css';
$k2JukeboxJsPath = $k2DocRoot . '/js/k2-jukebox.js';
if (is_file($k2JukeboxCssPath)) {
	echo '<link href="/stylesheets/k2-jukebox.css?v=' . (int) filemtime($k2JukeboxCssPath) . '" rel="stylesheet" type="text/css" />' . "\n";
}
if (is_file($k2JukeboxJsPath)) {
	echo '<script type="text/javascript" src="/js/k2-jukebox.js?v=' . (int) filemtime($k2JukeboxJsPath) . '" defer="defer"></script>' . "\n";
}
$k2ReqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (is_string($k2ReqPath)
	&& str_contains($k2ReqPath, '/amiga/')
	&& !str_contains($k2ReqPath, '/amiga/ops/')
	&& !str_contains($k2ReqPath, 'run_import_ko2amiga.php')) {
	require_once $k2DocRoot . '/includes/amiga_time_travel_stamp.php';
	if (amiga_time_travel_stamp_arrival_pending_from_request()) {
		echo "<style>html.k2-tt-arrival-pending .k2-amiga-tt-stamp{opacity:0;transform:translateY(5px)}html.k2-tt-arrival-pending .k2-amiga-tt-stamp__kicker-text{visibility:hidden}</style>\n";
		echo "<script>document.documentElement.classList.add('k2-tt-arrival-pending');</script>\n";
	}
	$dseg7Path = $k2DocRoot . '/fonts/dseg7-classic-regular.woff2';
	if (is_file($dseg7Path)) {
		$dseg7Ver = (int) filemtime($dseg7Path);
		echo '<link rel="preload" href="/fonts/dseg7-classic-regular.woff2?v=' . $dseg7Ver . '" as="font" type="font/woff2" crossorigin="anonymous" />' . "\n";
	}
}
?>
<?php if (!empty($k2RankedCloak)) {
	include $k2DocRoot . '/includes/ranked_table_cloak_head.php';
} ?>
