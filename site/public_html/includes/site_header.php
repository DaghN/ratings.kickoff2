<?php
/**
 * Shared site chrome — wordmark, header search, realm switcher.
 * Requires <html data-realm="online"> and body.k2-site on themed pages.
 * Realm/accent boot runs from includes/k2_head.php in <head>.
 */
?>
<header class="k2-site-header">
	<h1 class="k2-wordmark">
		<a href="status.php" class="k2-wordmark__link">
			<span class="k2-wordmark__main">Kick Off 2</span>
		</a>
	</h1>
	<div class="k2-site-header__links">
		<?php $playerSearchInHeader = true; include $_SERVER["DOCUMENT_ROOT"] . "/includes/player_search_bar.php"; ?>
		<nav class="k2-realm-switch" aria-label="Realm">
			<button type="button" class="k2-realm-switch__btn is-active" data-realm="online" aria-pressed="true">Online</button>
			<button type="button" class="k2-realm-switch__btn" data-realm="amiga" aria-pressed="false">Amiga</button>
		</nav>
	</div>
</header>
<script type="text/javascript" src="js/realm-switch.js" defer="defer"></script>
<div class="k2-page-nav">
