<?php
/**
 * Apply saved realm / tint / hub-nav tune before first paint.
 * Tint default is amber in theme.css when data-k2-accent is absent.
 * Loaded from includes/k2_head.php in <head> (after theme.css).
 */
?>
<script type="text/javascript">
(function () {
	var root = document.documentElement;
	var validAccents = ['amber', 'pitch', 'chrome', 'holo'];
	var validHubNav = ['solid', 'segment', 'soft'];
	var hubNav = 'segment';
	try {
		var realm = localStorage.getItem('k2-realm');
		if (realm === 'online' || realm === 'amiga') {
			root.setAttribute('data-realm', realm);
		}
		var accent = sessionStorage.getItem('k2-accent-tune');
		if (accent && validAccents.indexOf(accent) !== -1) {
			root.setAttribute('data-k2-accent', accent);
		}
		if (typeof URLSearchParams !== 'undefined' && window.location && window.location.search) {
			var params = new URLSearchParams(window.location.search);
			var fromUrl = params.get('k2_hub_nav');
			if (fromUrl && validHubNav.indexOf(fromUrl) !== -1) {
				hubNav = fromUrl;
				sessionStorage.setItem('k2-hub-nav-tune', hubNav);
			} else {
				var savedHubNav = sessionStorage.getItem('k2-hub-nav-tune');
				if (savedHubNav && validHubNav.indexOf(savedHubNav) !== -1) {
					hubNav = savedHubNav;
				}
			}
		} else {
			var savedHubNavLegacy = sessionStorage.getItem('k2-hub-nav-tune');
			if (savedHubNavLegacy && validHubNav.indexOf(savedHubNavLegacy) !== -1) {
				hubNav = savedHubNavLegacy;
			}
		}
		/* Default hidden; sessionStorage "0" = user chose Show tint */
		if (sessionStorage.getItem('k2-accent-pills-hidden') !== '0') {
			root.setAttribute('data-k2-accent-pills-hidden', '1');
		}
	} catch (e) {
		/* ignore storage errors */
	}
	root.setAttribute('data-k2-hub-nav', hubNav);
})();
</script>
