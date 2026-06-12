<?php
/**
 * One-shot restore of scrollY after carry-scroll navigation (see js/k2-carry-scroll.js).
 * Inline in <head> so restore runs as early as practical on the next document.
 * Retries only while the target is unreachable; stops on success, user scroll, or timeout.
 */
?>
<script>
(function () {
	var KEY = 'k2:carryScrollY';
	var APPLY_EPS = 3;
	var STOP_MS = 2000;

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
		var targetY = parseInt(raw, 10);
		if (isNaN(targetY) || targetY < 0) {
			return;
		}

		var finished = false;
		var userTookOver = false;
		var resizeObserver = null;
		var stopTimer = null;

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

		function ensureMinScrollHeight(y) {
			var needed = y + (window.innerHeight || 0);
			var sh = scrollHeightNow();
			if (sh >= needed) {
				return;
			}
			var el = document.documentElement;
			el.style.minHeight = needed + 'px';
		}

		function intendedTop() {
			ensureMinScrollHeight(targetY);
			return Math.min(targetY, maxScrollTop());
		}

		function atTarget() {
			return Math.abs(window.scrollY - intendedTop()) <= APPLY_EPS;
		}

		function cleanup() {
			finished = true;
			if (resizeObserver) {
				resizeObserver.disconnect();
				resizeObserver = null;
			}
			if (stopTimer) {
				clearTimeout(stopTimer);
				stopTimer = null;
			}
			window.removeEventListener('keydown', onScrollKeydown);
		}

		function userCancel() {
			if (finished || userTookOver) {
				return;
			}
			userTookOver = true;
			cleanup();
		}

		function onScrollKeydown(ev) {
			var key = ev.key;
			if (
				key === 'ArrowUp' ||
				key === 'ArrowDown' ||
				key === 'PageUp' ||
				key === 'PageDown' ||
				key === 'Home' ||
				key === 'End' ||
				key === ' '
			) {
				userCancel();
			}
		}

		function tryRestore() {
			if (finished || userTookOver) {
				return;
			}
			var top = intendedTop();
			if (atTarget()) {
				cleanup();
				return;
			}
			window.scrollTo(0, top);
			if (atTarget()) {
				cleanup();
			}
		}

		window.addEventListener('wheel', userCancel, { passive: true, once: true });
		window.addEventListener('touchmove', userCancel, { passive: true, once: true });
		window.addEventListener('mousedown', userCancel, { once: true });
		window.addEventListener('keydown', onScrollKeydown);

		tryRestore();

		if (document.readyState === 'loading') {
			document.addEventListener(
				'DOMContentLoaded',
				function () {
					tryRestore();
					if (typeof requestAnimationFrame === 'function') {
						requestAnimationFrame(function () {
							requestAnimationFrame(tryRestore);
						});
					}
				},
				{ once: true }
			);
		} else {
			tryRestore();
		}

		if (typeof ResizeObserver !== 'undefined') {
			resizeObserver = new ResizeObserver(function () {
				tryRestore();
			});
			resizeObserver.observe(document.documentElement);
		}

		stopTimer = setTimeout(cleanup, STOP_MS);
	} catch (e) {
		/* ignore */
	}
})();
</script>
