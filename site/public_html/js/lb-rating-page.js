/**
 * Online rating leaderboard only — same-page Elo link scroll to #k2-lb-player-{id}.
 * Inbound hash landing stays in k2_carry_scroll_restore.php.
 */
(function () {
	'use strict';

	var ROW_HASH_PREFIX = 'k2-lb-player-';
	var LINK_SEL = 'a.k2-link-star[href*="#' + ROW_HASH_PREFIX + '"]';

	function scrollToPlayerRow(id) {
		var el = document.getElementById(id);
		if (!el) {
			return false;
		}
		var rect = el.getBoundingClientRect();
		var marginTop = parseFloat(window.getComputedStyle(el).scrollMarginTop) || 0;
		var top = Math.max(0, rect.top + window.scrollY - marginTop);
		window.scrollTo(0, top);
		return true;
	}

	function hashIdFromUrl(url) {
		if (!url || !url.hash) {
			return '';
		}
		var id = url.hash.charAt(0) === '#' ? url.hash.slice(1) : url.hash;
		try {
			id = decodeURIComponent(id);
		} catch (eDec) {
			/* keep raw */
		}
		if (!id || id.indexOf(ROW_HASH_PREFIX) !== 0) {
			return '';
		}
		return id;
	}

	function scrollHashFromLocation() {
		var id = hashIdFromUrl({ hash: window.location.hash });
		if (!id) {
			return;
		}
		function retry() {
			scrollToPlayerRow(id);
		}
		retry();
		if (typeof requestAnimationFrame === 'function') {
			requestAnimationFrame(retry);
			requestAnimationFrame(function () {
				requestAnimationFrame(retry);
			});
		}
	}

	function initSamePageEloRowScroll() {
		document.addEventListener('click', function (ev) {
			var link;
			var url;
			var id;

			if (ev.button !== 0 || ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey) {
				return;
			}
			link = ev.target && ev.target.closest ? ev.target.closest(LINK_SEL) : null;
			if (!link || !link.href) {
				return;
			}
			try {
				url = new URL(link.href, window.location.href);
			} catch (eUrl) {
				return;
			}
			if (url.pathname !== window.location.pathname || url.search !== window.location.search) {
				return;
			}
			id = hashIdFromUrl(url);
			if (!id) {
				return;
			}
			ev.preventDefault();
			if (window.location.hash !== url.hash) {
				if (window.history && window.history.pushState) {
					window.history.pushState(null, '', url.pathname + url.search + url.hash);
				} else {
					window.location.hash = url.hash;
				}
			}
			function retry() {
				scrollToPlayerRow(id);
			}
			retry();
			if (typeof requestAnimationFrame === 'function') {
				requestAnimationFrame(retry);
				requestAnimationFrame(function () {
					requestAnimationFrame(retry);
				});
			}
		}, true);

		scrollHashFromLocation();
		document.addEventListener('k2:page-ready', scrollHashFromLocation, { once: true });
	}

	if (typeof window.k2OnPageReady === 'function') {
		window.k2OnPageReady(initSamePageEloRowScroll);
	} else if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initSamePageEloRowScroll);
	} else {
		initSamePageEloRowScroll();
	}
}());