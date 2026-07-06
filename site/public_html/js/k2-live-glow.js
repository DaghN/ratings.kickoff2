(function () {
	'use strict';

	var GLOW_MS = 2600;
	var GLOW_CLASS = 'k2-live-glow';
	var GLOW_WHITE_CLASS = 'k2-live-glow--white';
	var GLOW_ANIMS = ['k2-live-glow-bloom', 'k2-live-glow-bloom-blue', 'k2-live-glow-bloom-white'];

	function clearGlow(el) {
		if (!el) {
			return;
		}
		el.classList.remove(GLOW_CLASS);
		el.classList.remove(GLOW_WHITE_CLASS);
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

	function resolveScoreGoalGlowTarget(goalEl) {
		if (!goalEl) {
			return null;
		}
		var scoreInk = goalEl.querySelector('.blue');
		return scoreInk || goalEl;
	}

	function collectGlowTargets(el) {
		if (!el || el.nodeType !== 1) {
			return [];
		}
		if (el.matches('.k2-status-score__goal')) {
			return [resolveScoreGoalGlowTarget(el)];
		}
		if (el.matches('.blue, .k2-link-star, [data-k2-status-rating-count], [data-k2-status-online-count], [data-k2-status-arc-games], [data-k2-status-arc-players]')) {
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
			var targets = [];
			if (el.querySelector('.k2-status-live-list__meta')) {
				// Live panel — new game row: kickoff scoreline (0–0), not player names.
				var liveGoals = el.querySelectorAll('.k2-status-score .k2-status-score__goal');
				for (var lg = 0; lg < liveGoals.length; lg++) {
					var liveGoalInk = resolveScoreGoalGlowTarget(liveGoals[lg]);
					if (liveGoalInk) {
						targets.push(liveGoalInk);
					}
				}
				return targets;
			}
			var matchLinks = el.querySelectorAll('.k2-status-match a.k2-link-star, .k2-status-match a[href*="/player/"]');
			if (matchLinks.length) {
				for (var m = 0; m < matchLinks.length; m++) {
					targets.push(matchLinks[m]);
				}
			} else {
				var gameLink = el.querySelector('a.k2-status-day-games-list__game');
				if (gameLink) {
					targets.push(gameLink);
				}
			}
			var goalCells = el.querySelectorAll('.k2-status-score .k2-status-score__goal');
			for (var g = 0; g < goalCells.length; g++) {
				var goalInk = resolveScoreGoalGlowTarget(goalCells[g]);
				if (goalInk) {
					targets.push(goalInk);
				}
			}
			return targets;
		}
		if (el.matches('section, .k2-status-panel, ul')) {
			var ink = el.querySelector('.k2-link-star, .blue, .k2-status-score__goal, a[href*="/player/"], a[href*="/game/"]');
			return ink ? [ink] : [];
		}
		return [el];
	}

	function applyGlowToTarget(el, options) {
		options = options || {};
		if (!el) {
			return;
		}
		clearGlow(el);
		void el.offsetWidth;
		el.classList.add(GLOW_CLASS);
		if (options.white) {
			el.classList.add(GLOW_WHITE_CLASS);
		}
		el.__k2LiveGlowEnd = function (ev) {
			if (GLOW_ANIMS.indexOf(ev.animationName) !== -1) {
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

	window.k2LiveGlow = {
		trigger: triggerLiveGlow,
		scorePulse: triggerLiveGlow,
		triggerStar: function (el) {
			applyGlowToTarget(el);
		},
		triggerWhite: function (el) {
			applyGlowToTarget(el, { white: true });
		},
	};
})();
