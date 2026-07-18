<?php
/**
 * Shared site chrome — wordmark, realm switcher, header search.
 * Requires <html data-realm="online|amiga"> and body.k2-site on themed pages.
 * Tint boot from includes/theme_boot_head.php via k2_head.php; accent pills via realm-switch.js.
 *
 * Neon image wordmark trial: set $k2NeonWordmarkTrial = false to restore Exo 2 text only.
 * Amber asset only for now — other tints keep the text wordmark (CSS).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/realm_switcher.php';
$k2WordmarkHomeHref = htmlspecialchars($k2RealmHomeHref, ENT_QUOTES, 'UTF-8');
$k2PageNavClass = isset($k2PageNavClass) ? trim((string) $k2PageNavClass) : '';
$k2NeonWordmarkTrial = true;
$k2WordmarkClass = 'k2-wordmark' . ($k2NeonWordmarkTrial ? ' k2-wordmark--neon-trial' : '');
$k2NeonWordmarkSrc = '/images/wordmark/kick-off-2-amber.png';
$k2NeonWordmarkVer = (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . $k2NeonWordmarkSrc);
?>
<header class="k2-site-header">
	<div class="k2-site-header__brand">
		<h1 class="<?php echo htmlspecialchars($k2WordmarkClass, ENT_QUOTES, 'UTF-8'); ?>">
			<a href="<?php echo $k2WordmarkHomeHref; ?>" class="k2-wordmark__link">
				<span class="k2-wordmark__main">Kick Off 2</span>
				<?php if ($k2NeonWordmarkTrial) { ?>
				<img
					class="k2-wordmark__img"
					src="<?php echo htmlspecialchars($k2NeonWordmarkSrc . '?v=' . $k2NeonWordmarkVer, ENT_QUOTES, 'UTF-8'); ?>"
					alt="Kick Off 2"
					width="158"
					height="49"
					decoding="async"
				/>
				<?php } ?>
			</a>
		</h1>
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/realm_switcher_nav.php'; ?>
		<?php
		require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_time_mode_nav.php';
		amiga_time_mode_nav_render();
		?>
	</div>
	<div class="k2-site-header__links k2-site-header__search">
		<?php $playerSearchInHeader = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_search_bar.php'; ?>
	</div>
</header>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/k2_jukebox.php'; ?>
<script type="text/javascript" src="/js/realm-switch.js" defer="defer"></script>
<div class="k2-page-nav<?php echo $k2PageNavClass !== '' ? ' ' . htmlspecialchars($k2PageNavClass, ENT_QUOTES, 'UTF-8') : ''; ?>">
<?php
if (($k2CurrentRealm ?? '') === 'amiga') {
	include $_SERVER['DOCUMENT_ROOT'] . '/includes/amiga_snapshot_chrome.inc.php';
}
?>
