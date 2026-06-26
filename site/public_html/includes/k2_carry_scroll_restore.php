<?php

/**
 * One-shot restore of scrollY after carry-scroll navigation (see js/k2-carry-scroll.js).
 *
 * URL hash landing under Turbo: see docs/k2-turbo-page-init-checklist.md § Hash anchor landing.
 * Do not add page-local hash scroll scripts — extend this file instead.
 *
 * Turbo in-page visits: restore happens on turbo:render (before paint) and we suppress
 * Turbo's own scroll-to-top via currentVisit.scrolled, so there is no wordmark flash and
 * no body-hiding cloak. Full-page loads (first load, non-Turbo search picks) restore after
 * layout. Pill navigation may carry a nav anchor (viewport offset) so header/filter height
 * changes do not reclamp scroll. URL hash targets win: carry restore is skipped.
 */

?>

<script>
(function () {
	var KEY = 'k2:carryScrollY';
	var PENDING_HASH_KEY = 'k2:pendingHashScroll';
	var APPLY_EPS = 3;

	function hashTargetId() {
		var hash = window.location.hash;
		if (!hash || hash.charAt(0) !== '#') {
			try {
				var pending = sessionStorage.getItem(PENDING_HASH_KEY);
				if (pending) {
					return pending;
				}
			} catch (ePending) {
				/* ignore */
			}
			return '';
		}
		try {
			return decodeURIComponent(hash.slice(1));
		} catch (eDec) {
			return hash.slice(1);
		}
	}

	function clearPendingHash() {
		try {
			sessionStorage.removeItem(PENDING_HASH_KEY);
		} catch (eClr) {
			/* ignore */
		}
	}

	function scrollHeightNow() {
		var docEl = document.documentElement;
		var body = document.body;
		return Math.max(
			docEl ? docEl.scrollHeight : 0,
			body ? body.scrollHeight : 0
		);
	}

	function maxScrollTop() {
		return Math.max(0, scrollHeightNow() - (window.innerHeight || 0));
	}

	function ensureMinScrollHeight(y) {
		var needed = y + (window.innerHeight || 0);
		if (scrollHeightNow() >= needed) {
			return;
		}
		document.documentElement.style.minHeight = needed + 'px';
	}

	/* ---- URL hash target scroll (robust against late-growing content) ---- */

	function scrollToHashTarget() {
		var hashId = hashTargetId();
		var el;
		var rect;
		var cs;
		var marginTop;
		var top;

		if (!hashId) {
			return false;
		}
		el = document.getElementById(hashId);
		if (!el) {
			return false;
		}
		rect = el.getBoundingClientRect();
		cs = window.getComputedStyle(el);
		marginTop = parseFloat(cs.scrollMarginTop) || 0;
		top = Math.max(0, rect.top + window.scrollY - marginTop);
		ensureMinScrollHeight(top);
		window.scrollTo(0, Math.min(top, maxScrollTop()));
		return true;
	}

	function beginHashScrollWatch() {
		if (hashTargetId() === '') {
			return;
		}
		clearKey();
		suppressTurboScrollToTop();

		function attempt() {
			if (scrollToHashTarget()) {
				clearPendingHash();
			}
		}

		function schedule() {
			if (typeof requestAnimationFrame === 'function') {
				requestAnimationFrame(function () {
					requestAnimationFrame(attempt);
				});
			} else {
				attempt();
			}
		}

		schedule();
		window.addEventListener('load', attempt, { once: true });
		document.addEventListener('k2:page-ready', attempt, { once: true });

		if (typeof ResizeObserver !== 'undefined') {
			var ro = new ResizeObserver(attempt);
			ro.observe(document.documentElement);
			setTimeout(function () {
				ro.disconnect();
			}, 3500);
		}
	}

	/* ---- Carry-scroll payload ---- */

	function readPayload() {
		var raw;
		try {
			raw = sessionStorage.getItem(KEY);
		} catch (eRead) {
			return null;
		}
		if (raw === null) {
			return null;
		}
		var storedY = 0;
		var anchor = null;
		if (raw.charAt(0) === '{') {
			try {
				var p = JSON.parse(raw);
				storedY = parseInt(p.y, 10);
				if (p.anchor && p.anchor.label) {
					anchor = p.anchor;
				}
			} catch (eParse) {
				storedY = parseInt(raw, 10);
			}
		} else {
			storedY = parseInt(raw, 10);
		}
		if (isNaN(storedY) || storedY < 0) {
			return null;
		}
		return { y: storedY, anchor: anchor };
	}

	function clearKey() {
		try {
			sessionStorage.removeItem(KEY);
		} catch (eClr) {
			/* ignore */
		}
	}

	function findAnchorNav(anchor) {
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

	/* Keep the carried nav at the same viewport offset; fall back to raw stored Y. */
	function resolveTargetY(payload) {
		if (payload.anchor && typeof payload.anchor.viewportOffset === 'number') {
			var nav = findAnchorNav(payload.anchor);
			if (nav) {
				var docTop = nav.getBoundingClientRect().top + window.scrollY;
				var t = docTop - payload.anchor.viewportOffset;
				if (t >= 0) {
					return t;
				}
			}
		}
		return payload.y;
	}

	function applyScroll(payload) {
		var target = resolveTargetY(payload);
		ensureMinScrollHeight(target);
		var clamped = Math.min(target, maxScrollTop());
		window.scrollTo(0, clamped);
		return clamped;
	}

	function setManualScrollRestoration() {
		try {
			if ('scrollRestoration' in history) {
				history.scrollRestoration = 'manual';
			}
		} catch (eHist) {
			/* ignore */
		}
	}

	/* Late layout (fonts, ranked-table reveal) can grow the page after first apply.
	   Re-assert downward only, and stop if the user starts scrolling. */
	function scheduleReassert(payload, appliedTop) {
		var cancelled = false;
		var floorTop = appliedTop;

		function onUserScroll() {
			cancelled = true;
			window.removeEventListener('wheel', onUserScroll);
			window.removeEventListener('touchmove', onUserScroll);
			window.removeEventListener('keydown', onUserScroll);
		}

		window.addEventListener('wheel', onUserScroll, { passive: true });
		window.addEventListener('touchmove', onUserScroll, { passive: true });
		window.addEventListener('keydown', onUserScroll);

		function reassert() {
			if (cancelled) {
				return;
			}
			var target = resolveTargetY(payload);
			ensureMinScrollHeight(target);
			var clamped = Math.min(target, maxScrollTop());
			if (clamped - window.scrollY > APPLY_EPS && clamped >= floorTop - APPLY_EPS) {
				window.scrollTo(0, clamped);
				floorTop = clamped;
			}
		}

		var raf = (typeof requestAnimationFrame === 'function')
			? requestAnimationFrame
			: function (f) { setTimeout(f, 16); };
		raf(function () {
			reassert();
			raf(reassert);
		});
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(reassert).catch(function () {});
		}
		document.addEventListener('k2:page-ready', function onPR() {
			document.removeEventListener('k2:page-ready', onPR);
			reassert();
		});
		setTimeout(function () {
			cancelled = true;
		}, 2000);
	}

	function suppressTurboScrollToTop() {
		try {
			if (window.Turbo && window.Turbo.navigator && window.Turbo.navigator.currentVisit) {
				window.Turbo.navigator.currentVisit.scrolled = true;
			}
		} catch (eTurbo) {
			/* ignore */
		}
	}

	/* Turbo in-page render: synchronous, pre-paint. No cloak needed. */
	function restoreOnRender() {
		if (hashTargetId() !== '') {
			beginHashScrollWatch();
			return;
		}
		var payload = readPayload();
		if (!payload) {
			return;
		}
		clearKey();
		suppressTurboScrollToTop();
		setManualScrollRestoration();
		var applied = applyScroll(payload);
		scheduleReassert(payload, applied);
	}

	/* Full-page load (first load, non-Turbo search picks): restore after layout. */
	function restoreOnFullLoad() {
		if (hashTargetId() !== '') {
			beginHashScrollWatch();
			return;
		}
		var payload = readPayload();
		if (!payload) {
			return;
		}
		clearKey();
		setManualScrollRestoration();
		function go() {
			var applied = applyScroll(payload);
			scheduleReassert(payload, applied);
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', go, { once: true });
		} else {
			go();
		}
	}

	window.K2CarryScrollRestore = restoreOnFullLoad;

	document.addEventListener('turbo:render', restoreOnRender);

	/* Turbo may scroll to top before location.hash is applied; re-run after visit completes. */
	document.addEventListener('turbo:load', beginHashScrollWatch);

	/* Remember hash targets before Turbo rewrites history (turbo:render can run first). */
	document.addEventListener('click', function (ev) {
		var link;
		var url;

		if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
			return;
		}
		link = ev.target && ev.target.closest ? ev.target.closest('a[href*="#"]') : null;
		if (!link || !link.href) {
			return;
		}
		try {
			url = new URL(link.href, window.location.href);
		} catch (eUrl) {
			return;
		}
		if (!url.hash || url.origin !== window.location.origin) {
			return;
		}
		if (url.pathname === window.location.pathname && url.search === window.location.search) {
			return;
		}
		try {
			sessionStorage.removeItem(KEY);
			sessionStorage.setItem(PENDING_HASH_KEY, url.hash.charAt(0) === '#' ? url.hash.slice(1) : url.hash);
		} catch (eStore) {
			/* ignore */
		}
	}, true);

	restoreOnFullLoad();
})();
</script>