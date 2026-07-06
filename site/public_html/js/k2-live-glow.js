(function () {
	'use strict';

	var GLOW_MS = 2600;
	var GLOW_CLASS = 'k2-live-glow';
	var GLOW_ANIM = 'k2-live-glow-bloom';

	function clearGlow(el) {
		if (!el) {
			return;
		}
		el.classList.remove(GLOW_CLASS);
		if (el.__k2LiveGlowTimer) {
			clearTimeout(el.__k2LiveGlowTimer);
			el.__k2LiveGlowTimer = null;
		}
		if (el.__k2LiveGlowEnd) {
			el.removeEventListener('animationend', el.__k2LiveGlowEnd);
			el.__k2LiveGlowEnd = null;
		}
		notifyGlowIdleIfClear();
	}

	function collectGlowTargets(el) {
		if (!el || el.nodeType !== 1) {
			return [];
		}
		if (el.matches('.k2-status-score__goal, .blue, .k2-link-star, [data-k2-status-rating-count], [data-k2-status-arc-games], [data-k2-status-arc-players]')) {
			return [el];
		}
		if (el.matches('a[href*="/player/"], a[href*="/game/"]')) {
			return [el];
		}
		if (el.matches('li[data-player-id]')) {
			var playerLink = el.querySelector('a.k2-link-star, a[href*="/player/"]');
			return playerLink ? [playerLink] : [];
		}
		if (el.matches('li[data-game-id]')) {
			var matchLinks = el.querySelectorAll('.k2-status-match a.k2-link-star, .k2-status-match a[href*="/player/"]');
			if (matchLinks.length) {
				return Array.prototype.slice.call(matchLinks);
			}
			var gameLink = el.querySelector('a.k2-status-day-games-list__game');
			return gameLink ? [gameLink] : [];
		}
		if (el.matches('section, .k2-status-panel, ul')) {
			var ink = el.querySelector('.k2-link-star, .blue, .k2-status-score__goal, a[href*="/player/"], a[href*="/game/"]');
			return ink ? [ink] : [];
		}
		return [el];
	}

	function applyGlowToTarget(el) {
		if (!el) {
			return;
		}
		clearGlow(el);
		void el.offsetWidth;
		el.classList.add(GLOW_CLASS);
		el.__k2LiveGlowEnd = function (ev) {
			if (ev.animationName === GLOW_ANIM) {
				clearGlow(el);
			}
		};
		el.addEventListener('animationend', el.__k2LiveGlowEnd);
		el.__k2LiveGlowTimer = setTimeout(function () {
			clearGlow(el);
		}, GLOW_MS + 400);
	}

	function notifyGlowIdleIfClear() {
		requestAnimationFrame(function () {
			if (!document.querySelector('.k2-live-glow')) {
				document.dispatchEvent(new CustomEvent('k2-live-glow-idle'));
			}
		});
	}

	function triggerLiveGlow(el) {
		var targets = collectGlowTargets(el);
		for (var i = 0; i < targets.length; i++) {
			applyGlowToTarget(targets[i]);
		}
	}

	function staggerGlow(elements, delayMs) {
		delayMs = delayMs || 150;
		for (var i = 0; i < elements.length; i++) {
			(function (el, step) {
				if (!el) {
					return;
				}
				setTimeout(function () {
					triggerLiveGlow(el);
				}, step * delayMs);
			})(elements[i], i);
		}
	}

	function glowRatingsPlayer(root, playerId) {
		if (!root || !playerId) {
			return;
		}
		var tbody = root.querySelector('[data-k2-status-live-slot="ratings"]');
		if (!tbody) {
			return;
		}
		var row = tbody.querySelector('tr[data-player-id="' + playerId + '"]');
		if (!row) {
			return;
		}
		var nameLink = row.querySelector('.k2-status-table__player a.k2-link-star, .k2-status-table__player a[href*="/player/"]');
		var ratingLink = row.querySelector('td:nth-child(3) a.k2-link-star, td:nth-child(3) a[href]');
		if (nameLink) {
			applyGlowToTarget(nameLink);
		}
		if (ratingLink) {
			applyGlowToTarget(ratingLink);
		}
	}

	window.k2LiveGlow = {
		trigger: triggerLiveGlow,
		scorePulse: triggerLiveGlow,
		stagger: staggerGlow,
		glowRatingsPlayer: glowRatingsPlayer,
	};
})();
