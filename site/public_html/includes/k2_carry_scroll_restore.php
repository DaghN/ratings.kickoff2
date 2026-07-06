<?php
/**
 * One-shot restore of scrollY after carry-scroll navigation (see js/k2-carry-scroll.js).
 *
 * Full-page navigation only (Turbo removed Jun 2026). The flash problem: the browser
 * paints the top of the page (wordmark) before our JS can scroll down. Fix: when — and
 * ONLY when — a carry payload or URL-hash target is pending, cloak the page body before
 * first paint, apply the scroll the moment the document is tall enough (inside rAF, before
 * paint), then reveal. A hard timeout guarantees the page can never stay cloaked.
 *
 * Normal navigations (no pending restore) are never cloaked and behave exactly as default.
 *
 * Scroll top (y=0): stored scroll is already at top — restore is a no-op. On non-TT
 * destinations the payload is cleared and the page loads normally. On TIME TRAVEL
 * destinations (?as= in URL) a full-body chrome gate cloak engages instead (F6 iter 3d-c):
 * the page emits no contentful paint until the sub-ribbon chrome (.k2-hub-chapter) is
 * parsed, so the browser's paint holding keeps the OLD page on screen — navigation reads
 * as an in-place update instead of old -> ribbon+void -> new. No scroll ops in this mode.
 * (Iter 2's narrow cloak failed because the ribbon painted immediately, ending paint
 * holding at TTFB and exposing the void below for the whole DB block.)
 * Sources still store y (realm pills, TT ribbon, pickers); mid-scroll (y>0) keeps full
 * cloak + restore (F6 iter 3).
 *
 * URL hash landing: do not add page-local hash scroll scripts — extend this file instead.
 *
 * Browser Back: pagehide stores scrollY per pathname+search; back_forward reload restores
 * that Y instead of re-running hash landing (#player etc.). After inbound hash scroll,
 * replaceState strips the hash so the history entry tracks free scroll.
 */
?>
<style>
html.k2-carry-cloak body { visibility: hidden !important; }
</style>
<script>
(function () {
	var KEY = 'k2:carryScrollY';
	var BACK_SCROLL_PREFIX = 'k2:backScroll:';
	var PENDING_HASH_KEY = 'k2:pendingHashScroll';
	var MAX_CLOAK_MS = 700;
	var APPLY_EPS = 3;
	var startTime = Date.now();
	/* Optional server-declared pre-paint scroll target id (no URL hash needed) —
	   e.g. a video deep link ?v=… that should land on the player. Lowest priority
	   after a real URL hash / pending-hash. */
	var SERVER_TARGET = <?php echo json_encode((string) ($k2ScrollTargetId ?? '')); ?>;

	/* ---------- intent (read synchronously, before paint) ---------- */

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

	function pageScrollKey() {
		return window.location.pathname + window.location.search;
	}

	function saveBackScroll() {
		var y = window.pageYOffset;
		if (y == null) {
			y = window.scrollY;
		}
		try {
			sessionStorage.setItem(BACK_SCROLL_PREFIX + pageScrollKey(), String(y || 0));
		} catch (eSave) {
			/* ignore */
		}
	}

	function readBackScroll() {
		var raw;
		try {
			raw = sessionStorage.getItem(BACK_SCROLL_PREFIX + pageScrollKey());
		} catch (eRead) {
			return null;
		}
		if (raw === null) {
			return null;
		}
		var y = parseInt(raw, 10);
		if (isNaN(y) || y < 0) {
			return null;
		}
		return { y: y, anchor: null };
	}

	function clearBackScroll() {
		try {
			sessionStorage.removeItem(BACK_SCROLL_PREFIX + pageScrollKey());
		} catch (eClr) {
			/* ignore */
		}
	}

	function isBackForwardNav() {
		try {
			var nav = performance.getEntriesByType('navigation');
			if (nav && nav.length && nav[0].type === 'back_forward') {
				return true;
			}
		} catch (eNav) {
			/* ignore */
		}
		return false;
	}

	function stripHashFromHistory() {
		if (!window.history || !window.history.replaceState || !window.location.hash) {
			return;
		}
		try {
			var url = new URL(window.location.href);
			url.hash = '';
			window.history.replaceState(null, '', url.pathname + url.search);
		} catch (eStrip) {
			/* ignore */
		}
	}

	window.addEventListener('pagehide', saveBackScroll);

	var backPayload = isBackForwardNav() ? readBackScroll() : null;

	var hashId = backPayload ? '' : hashTargetId();
	var payload = hashId ? null : (backPayload || readPayload());
	/* Bare entry with no hash and no carry payload — server-declared target (e.g. game.php?id=). */
	if (!hashId && !payload && SERVER_TARGET && !backPayload) {
		hashId = SERVER_TARGET;
	}

	/* y=0: scroll restore is a no-op. Non-TT destination: normal full-page load at top
	   (F6 iter 3a). TT destination (?as=): full-body chrome gate until sub-ribbon chrome
	   is parsed — paint holding keeps the old page visible, so nav updates in place
	   (F6 iter 3d-c). */
	var chromeGate = false;
	if (payload && payload.y === 0 && !hashId) {
		clearKey();
		if (backPayload) {
			clearBackScroll();
		}
		payload = null;
		if (/[?&]as=/.test(window.location.search)) {
			chromeGate = true;
		}
	}

	var hasPending = !!hashId || !!payload || chromeGate;

	/* ---------- cloak ---------- */

	var cloaked = false;

	function cloak() {
		if (!hasPending || cloaked) {
			return;
		}
		document.documentElement.classList.add('k2-carry-cloak');
		cloaked = true;
	}

	function reveal() {
		if (!cloaked) {
			return;
		}
		document.documentElement.classList.remove('k2-carry-cloak');
		cloaked = false;
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

	if (hasPending) {
		if (!chromeGate) {
			setManualScrollRestoration();
		}
		cloak();
		if (payload) {
			clearKey();
			if (backPayload) {
				clearBackScroll();
			}
		}
	}

	/* ---------- geometry ---------- */

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

	/* ---------- carry target ---------- */

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

	/* Keep the carried nav at the same viewport offset; fall back to raw stored Y.
	   TT ribbon anchors inside position:sticky can store stuck-phase offsets — prefer
	   stored Y when anchor math collapses toward the sticky latch (belt-and-suspenders). */
	function resolveTargetY(p) {
		if (p.anchor && typeof p.anchor.viewportOffset === 'number') {
			var nav = findAnchorNav(p.anchor);
			if (nav) {
				var docTop = nav.getBoundingClientRect().top + window.scrollY;
				var t = docTop - p.anchor.viewportOffset;
				if (t >= 0) {
					if (nav.closest && nav.closest('.k2-amiga-time-travel--active') && p.y - t > 40) {
						return p.y;
					}
					return t;
				}
			}
		}
		return p.y;
	}

	function applyCarry() {
		var target = resolveTargetY(payload);
		ensureMinScrollHeight(target);
		var clamped = Math.min(target, maxScrollTop());
		window.scrollTo(0, clamped);
		return clamped;
	}

	function feastHeroPresent() {
		return !!document.querySelector('.k2-player-hero.k2-player-hero--feast:not(.k2-amiga-player-glance__hero)');
	}

	function carryReady() {
		if (!document.body) {
			return false;
		}
		/* Pill navigation stores the source nav's aria-label. Peer pages render the same
		   nav, and it sits below the hero/page chrome — so waiting for it guarantees
		   everything above (incl. the shrink-wrapped player hero) is fully parsed.
		   Without this, y=0 passed while only <body> existed (maxScrollTop >= -1) and
		   uncloaked onto a half-parsed hero (narrow border flash at top). Content below
		   the nav (e.g. a huge games table) may still be streaming — that is fine. */
		if (payload.anchor && payload.anchor.label && !findAnchorNav(payload.anchor)) {
			return false;
		}
		return maxScrollTop() >= resolveTargetY(payload) - 1;
	}

	/* ---------- hash target ---------- */

	function scrollToHashTarget() {
		var el = hashId ? document.getElementById(hashId) : null;
		if (!el) {
			return false;
		}
		var rect = el.getBoundingClientRect();
		var cs = window.getComputedStyle(el);
		var marginTop = parseFloat(cs.scrollMarginTop) || 0;
		var top = Math.max(0, rect.top + window.scrollY - marginTop);
		ensureMinScrollHeight(top);
		window.scrollTo(0, Math.min(top, maxScrollTop()));
		return true;
	}

	/* ---------- late-layout reassert (downward, stops on user scroll) ---------- */

	function scheduleReassert(reapply) {
		var cancelled = false;

		function onUserScroll() {
			cancelled = true;
			window.removeEventListener('wheel', onUserScroll);
			window.removeEventListener('touchmove', onUserScroll);
			window.removeEventListener('keydown', onUserScroll);
		}

		window.addEventListener('wheel', onUserScroll, { passive: true });
		window.addEventListener('touchmove', onUserScroll, { passive: true });
		window.addEventListener('keydown', onUserScroll);

		function step() {
			if (cancelled) {
				return;
			}
			reapply();
		}

		var raf = (typeof requestAnimationFrame === 'function')
			? requestAnimationFrame
			: function (f) { setTimeout(f, 16); };
		raf(function () {
			step();
			raf(step);
		});
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(step).catch(function () {});
		}
		document.addEventListener('k2:page-ready', function onPR() {
			document.removeEventListener('k2:page-ready', onPR);
			step();
		});
		setTimeout(function () {
			cancelled = true;
		}, 2000);
	}

	function reassertCarry() {
		var prev = window.scrollY;
		var target = resolveTargetY(payload);
		ensureMinScrollHeight(target);
		var clamped = Math.min(target, maxScrollTop());
		/* Only nudge downward toward target; never yank back up past the user. */
		if (clamped - prev > APPLY_EPS) {
			window.scrollTo(0, clamped);
		}
	}

	/* ---------- main pre-paint loop ---------- */

	function finishCarry() {
		applyCarry();
		var target = resolveTargetY(payload);
		var deferFeastHeroLayout = target <= 0 && feastHeroPresent();
		if (deferFeastHeroLayout) {
			var raf = (typeof requestAnimationFrame === 'function')
				? requestAnimationFrame
				: function (f) { setTimeout(f, 16); };
			raf(function () {
				raf(function () {
					reveal();
					scheduleReassert(reassertCarry);
				});
			});
			return;
		}
		reveal();
		scheduleReassert(reassertCarry);
	}

	function finishHash() {
		if (scrollToHashTarget()) {
			stripHashFromHistory();
		}
		clearPendingHash();
		reveal();
		scheduleReassert(function () { scrollToHashTarget(); });
	}

	/* Chrome gate (TT y=0): sub-ribbon chrome parsed — hub pages emit .k2-hub-chapter
	   right after the blocking queries; non-hub TT pages fall back to domReady. */
	function chromeGateReady() {
		return !!(document.body && document.querySelector('.k2-hub-chapter'));
	}

	function tick() {
		try {
			var domReady = document.readyState !== 'loading';
			if (hashId) {
				if (document.body && document.getElementById(hashId)) {
					finishHash();
					return;
				}
				/* Full DOM parsed and target still absent — nothing to land on. */
				if (domReady) {
					clearPendingHash();
					reveal();
					return;
				}
			} else if (chromeGate) {
				if (chromeGateReady() || domReady) {
					reveal();
					return;
				}
			} else if (payload) {
				/* Reveal the instant the page is ready enough (often before first paint):
				   source nav parsed (hero above it complete) + page tall enough for the
				   target Y. domReady fallback handles destinations missing the nav. */
				if (carryReady() || domReady) {
					finishCarry();
					return;
				}
			}
		} catch (eTick) {
			/* fall through to timeout safety */
		}
		if (Date.now() - startTime > MAX_CLOAK_MS) {
			try {
				if (hashId) {
					finishHash();
				} else if (payload) {
					finishCarry();
				}
			} catch (eFin) {
				reveal();
			}
			reveal();
			return;
		}
		requestAnimationFrame(tick);
	}

	if (hasPending) {
		if (typeof requestAnimationFrame === 'function') {
			requestAnimationFrame(tick);
		} else {
			document.addEventListener('DOMContentLoaded', tick);
		}
		/* Absolute safety nets so the page can never stay hidden. */
		window.addEventListener('load', reveal, { once: true });
		setTimeout(reveal, MAX_CLOAK_MS + 200);
	}

	/* ---------- remember hash targets before navigation ---------- */

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
		if (link.getAttribute('data-k2-tv-inpage') === '1') {
			return;
		}
		/* Peer pills use carry-scroll, not hash landing (see hub/player wing nav). */
		if (link.closest && link.closest('nav[data-k2-carry-scroll]')) {
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
})();
</script>