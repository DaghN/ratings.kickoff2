/**
 * Top horizontal scrollbar mirror for wide .k2-table-wrap panels.
 * Mark the wrap with data-k2-scroll-mirror; syncs scrollLeft with the table wrap below.
 * Layout: panel always shrink-wraps (fit-content); --active only toggles the top mirror bar.
 */
(function () {
	'use strict';

	var WRAP_SELECTOR = '.k2-table-wrap[data-k2-scroll-mirror]';
	var GROUP_CLASS = 'k2-table-mirror-group';
	var GROUP_ACTIVE_CLASS = 'k2-table-mirror-group--active';
	var MIRROR_CLASS = 'k2-table-scroll-mirror';
	var SIZER_CLASS = 'k2-table-scroll-mirror__sizer';

	function onReady(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
			return;
		}
		fn();
	}

	function initWrap(wrap) {
		if (!wrap || wrap.getAttribute('data-k2-scroll-mirror-bound') === '1') {
			return;
		}
		wrap.setAttribute('data-k2-scroll-mirror-bound', '1');

		var table = wrap.querySelector('table');
		if (!table) {
			return;
		}

		var group = document.createElement('div');
		group.className = GROUP_CLASS;

		var mirror = document.createElement('div');
		mirror.className = MIRROR_CLASS;
		mirror.setAttribute('aria-hidden', 'true');
		mirror.setAttribute('tabindex', '-1');

		var sizer = document.createElement('div');
		sizer.className = SIZER_CLASS;
		mirror.appendChild(sizer);

		var parent = wrap.parentNode;
		if (!parent) {
			return;
		}
		parent.insertBefore(group, wrap);
		group.appendChild(mirror);
		group.appendChild(wrap);

		var syncing = false;

		function syncScroll(from, to) {
			if (syncing) {
				return;
			}
			syncing = true;
			to.scrollLeft = from.scrollLeft;
			syncing = false;
		}

		function updateMetrics() {
			var scrollWidth = wrap.scrollWidth;
			var clientWidth = wrap.clientWidth;

			// Skip until the wrap has a real layout box (avoids false overflow during cloak / font load).
			if (clientWidth < 1) {
				return;
			}

			sizer.style.width = scrollWidth + 'px';

			if (scrollWidth > clientWidth + 1) {
				group.classList.add(GROUP_ACTIVE_CLASS);
				mirror.style.width = clientWidth + 'px';
				mirror.scrollLeft = wrap.scrollLeft;
			} else {
				group.classList.remove(GROUP_ACTIVE_CLASS);
				mirror.style.width = '';
				mirror.scrollLeft = 0;
				wrap.scrollLeft = 0;
			}
		}

		mirror.addEventListener('scroll', function () {
			syncScroll(mirror, wrap);
		});

		wrap.addEventListener('scroll', function () {
			syncScroll(wrap, mirror);
		});

		if (typeof ResizeObserver !== 'undefined') {
			var ro = new ResizeObserver(updateMetrics);
			ro.observe(wrap);
			ro.observe(table);
		}

		window.addEventListener('resize', updateMetrics);

		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(updateMetrics).catch(function () {
				/* ignore */
			});
		}

		updateMetrics();
	}

	onReady(function () {
		var wraps = document.querySelectorAll(WRAP_SELECTOR);
		for (var i = 0; i < wraps.length; i++) {
			initWrap(wraps[i]);
		}
	});
})();
