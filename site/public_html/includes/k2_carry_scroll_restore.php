<?php
/**
 * One-shot restore of scrollY after carry-scroll navigation (see js/k2-carry-scroll.js).
 * Pill navigation may store a nav anchor (viewport offset) so table/filter height changes
 * do not reclamp scroll upward. After first apply, only scrolls further down if the page grows.
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

		var storedY = 0;
		var anchor = null;
		if (raw.charAt(0) === '{') {
			try {
				var payload = JSON.parse(raw);
				storedY = parseInt(payload.y, 10);
				if (payload.anchor && payload.anchor.label) {
					anchor = payload.anchor;
				}
			} catch (eParse) {
				storedY = parseInt(raw, 10);
			}
		} else {
			storedY = parseInt(raw, 10);
		}
		if (isNaN(storedY) || storedY < 0) {
			return;
		}

		var finished = false;
		var userTookOver = false;
		var appliedY = null;
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

		function findAnchorNav() {
			if (!anchor || !anchor.label) {
				return null;
			}
			var nodes = document.querySelectorAll('nav[data-k2-carry-scroll]');
			for (var i = 0; i < nodes.length; i++) {
				if (nodes[i].getAttribute('aria-label') === anchor.label) {
					return nodes[i];
				}
			}
			return null;
		}

		function resolveTargetY() {
			var nav = findAnchorNav();
			if (nav && typeof anchor.viewportOffset === 'number') {
				var docTop = nav.getBoundingClientRect().top + window.scrollY;
				return docTop - anchor.viewportOffset;
			}
			return storedY;
		}

		function clampedTop() {
			var y = resolveTargetY();
			ensureMinScrollHeight(y);
			return Math.min(y, maxScrollTop());
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

			var top = clampedTop();

			if (appliedY !== null) {
				/* Never scroll up after first apply — only down if the page grew. */
				if (top > appliedY + APPLY_EPS) {
					window.scrollTo(0, top);
					appliedY = window.scrollY;
				}
				if (Math.abs(window.scrollY - top) <= APPLY_EPS) {
					cleanup();
				}
				return;
			}

			window.scrollTo(0, top);
			appliedY = window.scrollY;
			if (Math.abs(appliedY - top) <= APPLY_EPS) {
				cleanup();
			}
		}

		function scheduleRestorePasses() {
			tryRestore();
			if (typeof requestAnimationFrame === 'function') {
				requestAnimationFrame(function () {
					tryRestore();
				});
			}
		}

		window.addEventListener('wheel', userCancel, { passive: true, once: true });
		window.addEventListener('touchmove', userCancel, { passive: true, once: true });
		window.addEventListener('mousedown', userCancel, { once: true });
		window.addEventListener('keydown', onScrollKeydown);

		if (document.readyState === 'loading') {
			document.addEventListener(
				'DOMContentLoaded',
				scheduleRestorePasses,
				{ once: true }
			);
		} else {
			scheduleRestorePasses();
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
