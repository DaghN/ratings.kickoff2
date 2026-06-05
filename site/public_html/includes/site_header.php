<?php
/**
 * Shared site chrome — wordmark, realm switcher, header search.
 * Requires <html data-realm="online|amiga"> and body.k2-site on themed pages.
 * Tint boot from includes/theme_boot_head.php via k2_head.php; accent pills via realm-switch.js.
 */
?>
<header class="k2-site-header">
	<div class="k2-site-header__brand">
		<h1 class="k2-wordmark">
			<a href="/status.php" class="k2-wordmark__link">
				<span class="k2-wordmark__main">Kick Off 2</span>
			</a>
		</h1>
		<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/realm_switcher.php'; ?>
	</div>
	<div class="k2-site-header__links k2-site-header__search">
		<?php $playerSearchInHeader = true; include $_SERVER['DOCUMENT_ROOT'] . '/includes/player_search_bar.php'; ?>
	</div>
</header>
<script type="text/javascript" src="/js/player-search.js?v=<?php echo (int) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/player-search.js'); ?>" defer="defer"></script>
<script type="text/javascript" src="/js/realm-switch.js" defer="defer"></script>
<div class="k2-page-nav">
