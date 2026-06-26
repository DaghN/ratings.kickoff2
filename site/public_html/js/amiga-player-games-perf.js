/**
 * Lazy-load Perf. rating for /amiga/player/games.php (filtered games list).
 */
(function () {
	'use strict';

	var API = '/api/amiga_player_games_perf_rating.php';

	function onReady(fn) {
		if (typeof window.k2OnPageReady === 'function') {
			window.k2OnPageReady(fn);
			return;
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
			return;
		}
		fn();
	}

	function queryString() {
		if (window.location.search && window.location.search.length > 1) {
			return window.location.search;
		}
		return '';
	}

	function setPerfValue(el, text) {
		var valueEl = el.querySelector('.k2-player-games-status__perf-value');
		if (valueEl) {
			valueEl.textContent = text;
		}
	}

	onReady(function () {
		var perfEl = document.querySelector('.k2-player-games-status__perf');
		if (!perfEl) {
			return;
		}

		var qs = queryString();
		if (qs === '' || qs.indexOf('id=') === -1) {
			perfEl.hidden = true;
			return;
		}

		fetch(API + qs, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
		})
			.then(function (res) {
				if (!res.ok) {
					throw new Error('http_' + res.status);
				}
				return res.json();
			})
			.then(function (data) {
				if (
					data &&
					data.performance_rating !== null &&
					data.performance_rating !== undefined
				) {
					setPerfValue(perfEl, String(data.performance_rating));
					return;
				}
				setPerfValue(perfEl, '—');
			})
			.catch(function () {
				setPerfValue(perfEl, '—');
			});
	});
})();
