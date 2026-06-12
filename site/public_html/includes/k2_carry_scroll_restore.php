<?php
/**
 * One-shot restore of scrollY after carry-scroll navigation (see js/k2-carry-scroll.js).
 * Inline in <head> so restore runs as early as practical on the next document.
 */
?>
<script>
(function () {
	var KEY = 'k2:carryScrollY';
	try {
		if ('scrollRestoration' in history) {
			history.scrollRestoration = 'manual';
		}
	} catch (e1) {
		/* ignore */
	}
	try {
		var raw = sessionStorage.getItem(KEY);
		if (raw === null) {
			return;
		}
		sessionStorage.removeItem(KEY);
		var y = parseInt(raw, 10);
		if (isNaN(y) || y < 0) {
			return;
		}

		function scrollHeightNow() {
			var el = document.documentElement;
			var body = document.body;
			return Math.max(
				el ? el.scrollHeight : 0,
				body ? body.scrollHeight : 0
			);
		}

		function maxScrollTop() {
			return Math.max(0, scrollHeightNow() - (window.innerHeight || 0));
		}

		function ensureMinScrollHeight(targetY) {
			var needed = targetY + (window.innerHeight || 0);
			var sh = scrollHeightNow();
			if (sh >= needed) {
				return;
			}
			var el = document.documentElement;
			el.style.minHeight = needed + 'px';
		}

		function apply() {
			ensureMinScrollHeight(y);
			var top = Math.min(y, maxScrollTop());
			window.scrollTo(0, top);
		}

		/* First pass in head: extend document and scroll before first paint when possible. */
		ensureMinScrollHeight(y);
		window.scrollTo(0, y);

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', apply, { once: true });
		} else {
			apply();
		}
		window.addEventListener(
			'load',
			function () {
				apply();
			},
			{ once: true }
		);
	} catch (e) {
		/* ignore */
	}
})();
</script>
