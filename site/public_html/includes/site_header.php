<?php
/**
 * Shared site chrome — wordmark, realm switcher (Amiga coming soon), portal link.
 * Requires <html data-realm="online"> and body.k2-site on themed pages.
 */
?>
<header class="k2-site-header">
	<h1 class="k2-wordmark">
		<a href="ranked1.php" style="color: inherit; text-decoration: none; font-weight: inherit;">
			<span class="k2-wordmark__main">Kick Off 2</span><span class="k2-wordmark__sub"> ratings</span>
		</a>
	</h1>
	<div class="k2-site-header__links">
		<nav class="k2-realm-switch" aria-label="Realm">
			<button type="button" class="k2-realm-switch__btn is-active" aria-pressed="true">Online</button>
			<button type="button" class="k2-realm-switch__btn" aria-pressed="false">Amiga</button>
		</nav>
		<a class="k2-portal-link" href="https://kickoff2.com/" target="_blank" rel="noopener">kickoff2.com ↗</a>
	</div>
</header>
<div class="k2-page-nav">
