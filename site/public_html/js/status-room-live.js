(function () {
	'use strict';

	var PULSE_API = 'api/status_room_pulse.php';
	var POLL_MS = 1000;
	var TICKS_PER_SEC = 50;

	var state = {
		root: null,
		revision: '',
		signals: {},
		liveGameIds: {},
		pollTimer: null,
		clockTimer: null,
		liveClocks: [],
		inflight: false,
	};

	function parseJsonAttr(el, name, fallback) {
		try {
			return JSON.parse(el.getAttribute(name) || '');
		} catch (e) {
			return fallback;
		}
	}

	function formatHalfCountdown(ticks) {
		if (ticks <= 0) {
			return '\u2014';
		}
		var seconds = Math.round(ticks / TICKS_PER_SEC);
		var minutes = Math.floor(seconds / 60);
		var secs = seconds % 60;
		return minutes + ':' + String(secs).padStart(2, '0');
	}

	function formatPeriod(period) {
		if (period === 1) {
			return '1st half';
		}
		if (period === 2) {
			return '2nd half';
		}
		return '\u2014';
	}

	function formatNumber(n) {
		return Number(n).toLocaleString('en-US');
	}

	function leagueContext(root) {
		var comp = document.querySelector('[data-k2-status-period-competitions]');
		if (!comp) {
			return { period: 'week', key: '' };
		}
		var period = comp.getAttribute('data-active-period') || 'week';
		var keys = parseJsonAttr(comp, 'data-current-keys', {});
		return { period: period, key: keys[period] || '' };
	}

	function pulseQuery(root) {
		var ctx = leagueContext(root);
		var params = new URLSearchParams();
		if (state.revision) {
			params.set('revision', state.revision);
		}
		params.set('period', ctx.period);
		if (ctx.key) {
			params.set('key', ctx.key);
		}
		var sig = state.signals || {};
		params.set('last_rated_id', String(sig.last_rated_id || 0));
		params.set('games_played', String(sig.games_played || 0));
		params.set('live_fp', sig.live_fp || '');
		params.set('online_fp', sig.online_fp || '');
		params.set('last_login_epoch', String(sig.last_login_epoch || 0));
		params.set('last_login_id', String(sig.last_login_id || 0));
		params.set('last_join_epoch', String(sig.last_join_epoch || 0));
		params.set('last_join_id', String(sig.last_join_id || 0));
		params.set('league_fp', sig.league_fp || '');
		if (sig.period_keys) {
			params.set('period_keys', JSON.stringify(sig.period_keys));
		}
		return PULSE_API + '?' + params.toString();
	}

	function syncCompetitionsClock(epoch) {
		if (window.k2StatusCompetitions && typeof window.k2StatusCompetitions.syncServerClock === 'function') {
			window.k2StatusCompetitions.syncServerClock(epoch);
		}
	}

	function replaceListSlot(root, slotName, section, emptyMessage, glowSelector) {
		var slot = root.querySelector('[data-k2-status-live-slot="' + slotName + '"]');
		if (!slot || !section) {
			return;
		}
		var prevIds = {};
		var prevNodes = slot.querySelectorAll('[data-player-id], [data-game-id]');
		for (var i = 0; i < prevNodes.length; i++) {
			var pid = prevNodes[i].getAttribute('data-player-id') || prevNodes[i].getAttribute('data-game-id');
			if (pid) {
				prevIds[pid] = true;
			}
		}
		if (section.empty) {
			slot.innerHTML = '<p class="k2-status-panel__empty">' + emptyMessage + '</p>';
			return;
		}
		if (section.html) {
			slot.innerHTML = section.html;
		}
		if (window.k2LiveGlow && glowSelector) {
			var nodes = slot.querySelectorAll(glowSelector);
			for (var j = 0; j < nodes.length; j++) {
				var id = nodes[j].getAttribute('data-player-id') || nodes[j].getAttribute('data-game-id');
				if (id && !prevIds[id]) {
					window.k2LiveGlow.trigger(nodes[j]);
				}
			}
		}
	}

	function syncLiveGameClocks(root, games, syncEpoch) {
		state.liveClocks = [];
		if (!games || !games.length) {
			return;
		}
		for (var i = 0; i < games.length; i++) {
			var g = games[i];
			state.liveClocks.push({
				game_id: g.game_id,
				half_countdown: g.half_countdown || 0,
				period: g.period || 0,
				sync_epoch: syncEpoch,
			});
		}
		tickLiveClocks(root);
	}

	function tickLiveClocks(root) {
		if (!root || !state.liveClocks.length) {
			return;
		}
		var now = Math.floor(Date.now() / 1000);
		for (var i = 0; i < state.liveClocks.length; i++) {
			var item = state.liveClocks[i];
			var li = root.querySelector('[data-k2-status-live-slot="live"] li[data-game-id="' + item.game_id + '"]');
			if (!li) {
				continue;
			}
			var elapsed = Math.max(0, now - item.sync_epoch);
			var remaining = item.half_countdown - elapsed * TICKS_PER_SEC;
			var clockEl = li.querySelector('.k2-status-live-list__clock');
			var periodEl = li.querySelector('.k2-status-live-list__period');
			if (clockEl) {
				clockEl.textContent = formatHalfCountdown(remaining);
			}
			if (periodEl) {
				periodEl.textContent = formatPeriod(item.period);
			}
		}
	}

	function patchLiveScores(root, games) {
		if (!games || !window.k2LiveGlow) {
			return;
		}
		for (var i = 0; i < games.length; i++) {
			var g = games[i];
			var li = root.querySelector('[data-k2-status-live-slot="live"] li[data-game-id="' + g.game_id + '"]');
			if (!li) {
				continue;
			}
			var scoreEl = li.querySelector('.k2-status-score');
			if (!scoreEl) {
				continue;
			}
			var prev = scoreEl.getAttribute('data-score-key') || '';
			var next = g.score_a + '-' + g.score_b;
			if (prev && prev !== next) {
				window.k2LiveGlow.scorePulse(scoreEl);
			}
			scoreEl.setAttribute('data-score-key', next);
		}
	}

	function patchLive(root, section, syncEpoch) {
		var prevIds = state.liveGameIds || {};
		var nextIds = {};
		if (section && section.games) {
			for (var i = 0; i < section.games.length; i++) {
				nextIds[section.games[i].game_id] = true;
			}
		}
		replaceListSlot(root, 'live', section, 'No live games in progress.', 'li[data-game-id]');
		if (section && section.games) {
			patchLiveScores(root, section.games);
			syncLiveGameClocks(root, section.games, syncEpoch);
		}
		state.liveGameIds = nextIds;
	}

	function patchRatings(root, section) {
		if (!section) {
			return;
		}
		var countEl = root.querySelector('[data-k2-status-rating-count]');
		if (countEl && section.count != null) {
			var prev = countEl.textContent.replace(/,/g, '');
			var next = String(section.count);
			countEl.textContent = formatNumber(section.count);
			if (prev && prev !== next && window.k2LiveGlow) {
				window.k2LiveGlow.trigger(countEl);
			}
		}
		var tbody = root.querySelector('[data-k2-status-live-slot="ratings"]');
		var panel = root.querySelector('[data-k2-status-panel="ratings"]');
		if (!tbody) {
			return;
		}
		if (section.empty) {
			var wrap = tbody.closest('.k2-table-wrap');
			if (wrap && wrap.parentNode) {
				wrap.parentNode.innerHTML = '<p class="k2-status-panel__empty">No active rated players in this window yet.</p>';
			}
			return;
		}
		if (section.tbody_html) {
			tbody.innerHTML = section.tbody_html;
		}
		if (window.k2LiveGlow && panel) {
			window.k2LiveGlow.trigger(panel);
		}
		if (typeof window.k2TableInitHelpTooltips === 'function') {
			window.k2TableInitHelpTooltips(tbody.closest('table') || root);
		}
	}

	function patchArc(root, section) {
		if (!section) {
			return;
		}
		var gamesEl = root.querySelector('[data-k2-status-arc-games]');
		if (gamesEl && section.games != null) {
			var prevG = gamesEl.textContent.replace(/,/g, '');
			var nextG = String(section.games);
			gamesEl.textContent = formatNumber(section.games);
			if (prevG && prevG !== nextG && window.k2LiveGlow) {
				window.k2LiveGlow.trigger(gamesEl);
			}
		}
		var playersEl = root.querySelector('[data-k2-status-arc-players]');
		if (playersEl && section.players != null) {
			var prevP = playersEl.textContent.replace(/,/g, '');
			var nextP = String(section.players);
			playersEl.textContent = formatNumber(section.players);
			if (prevP && prevP !== nextP && window.k2LiveGlow) {
				window.k2LiveGlow.trigger(playersEl);
			}
		}
	}

	function runCascadeGlow(root) {
		if (!window.k2LiveGlow) {
			return;
		}
		window.k2LiveGlow.stagger([
			root.querySelector('[data-k2-status-panel="recent_games"]'),
			root.querySelector('[data-k2-status-panel="ratings"]'),
			document.querySelector('[data-k2-status-period-competitions]'),
			root.querySelector('[data-k2-status-panel="arc"]'),
		], 150);
	}

	function applySections(root, body, syncEpoch) {
		var sections = body.sections || {};
		if (body.cascade) {
			if (sections.live) {
				patchLive(root, sections.live, syncEpoch);
			}
			if (sections.online) {
				replaceListSlot(root, 'online', sections.online, 'Nobody flagged online \u2014 see recent logins below.', 'li[data-player-id]');
			}
			if (sections.logins) {
				replaceListSlot(root, 'logins', sections.logins, '\u2014', 'li[data-player-id]');
			}
			if (sections.registrations) {
				replaceListSlot(root, 'registrations', sections.registrations, '\u2014', 'li[data-player-id]');
			}
			if (sections.recent_games) {
				replaceListSlot(root, 'recent_games', sections.recent_games, '\u2014', 'li[data-game-id]');
			}
			if (sections.ratings) {
				patchRatings(root, sections.ratings);
			}
			if (sections.arc) {
				patchArc(root, sections.arc);
			}
			if (sections.league && window.k2StatusCompetitions && window.k2StatusCompetitions.applyLeaguePulse) {
				window.k2StatusCompetitions.applyLeaguePulse(sections.league);
			}
			runCascadeGlow(root);
			return;
		}
		if (sections.live) {
			patchLive(root, sections.live, syncEpoch);
		}
		if (sections.online) {
			replaceListSlot(root, 'online', sections.online, 'Nobody flagged online \u2014 see recent logins below.', 'li[data-player-id]');
		}
		if (sections.logins) {
			replaceListSlot(root, 'logins', sections.logins, '\u2014', 'li[data-player-id]');
		}
		if (sections.registrations) {
			replaceListSlot(root, 'registrations', sections.registrations, '\u2014', 'li[data-player-id]');
		}
		if (sections.recent_games) {
			replaceListSlot(root, 'recent_games', sections.recent_games, '\u2014', 'li[data-game-id]');
		}
		if (sections.ratings) {
			patchRatings(root, sections.ratings);
		}
		if (sections.arc) {
			patchArc(root, sections.arc);
		}
		if (sections.league && window.k2StatusCompetitions && window.k2StatusCompetitions.applyLeaguePulse) {
			window.k2StatusCompetitions.applyLeaguePulse(sections.league);
		}
	}

	function onPulseBody(root, body) {
		if (!body || body.changed === false) {
			if (body && body.server_now_epoch) {
				syncCompetitionsClock(body.server_now_epoch);
			}
			return;
		}
		var prevPeriodKeys = JSON.stringify((state.signals && state.signals.period_keys) || {});
		var syncEpoch = body.server_now_epoch || Math.floor(Date.now() / 1000);
		root.setAttribute('data-pulse-sync-epoch', String(syncEpoch));
		if (body.signals) {
			var newPeriodKeys = JSON.stringify(body.signals.period_keys || {});
			if (prevPeriodKeys && newPeriodKeys !== prevPeriodKeys
				&& window.k2StatusCompetitions
				&& typeof window.k2StatusCompetitions.onPeriodKeysChange === 'function') {
				window.k2StatusCompetitions.onPeriodKeysChange(body.signals.period_keys);
			}
			state.signals = body.signals;
		}
		if (body.revision) {
			state.revision = body.revision;
			root.setAttribute('data-pulse-revision', body.revision);
		}
		syncCompetitionsClock(syncEpoch);
		applySections(root, body, syncEpoch);
	}

	function pollOnce(root) {
		if (state.inflight) {
			return;
		}
		state.inflight = true;
		fetch(pulseQuery(root), { credentials: 'same-origin', cache: 'no-store' })
			.then(function (res) {
				return res.json();
			})
			.then(function (body) {
				onPulseBody(root, body);
			})
			.catch(function () {
				/* ignore transient errors */
			})
			.then(function () {
				state.inflight = false;
			});
	}

	function seedLiveIds(root) {
		state.liveGameIds = {};
		var nodes = root.querySelectorAll('[data-k2-status-live-slot="live"] li[data-game-id]');
		for (var i = 0; i < nodes.length; i++) {
			var id = parseInt(nodes[i].getAttribute('data-game-id'), 10);
			if (id) {
				state.liveGameIds[id] = true;
			}
			var scoreEl = nodes[i].querySelector('.k2-status-score');
			if (scoreEl) {
				scoreEl.setAttribute('data-score-key', scoreEl.textContent.replace(/\s+/g, ''));
			}
		}
		var syncEpoch = parseInt(root.getAttribute('data-pulse-sync-epoch'), 10) || Math.floor(Date.now() / 1000);
		var clocks = [];
		for (var j = 0; j < nodes.length; j++) {
			var clockEl = nodes[j].querySelector('.k2-status-live-list__clock');
			var periodEl = nodes[j].querySelector('.k2-status-live-list__period');
			var ticks = clockEl ? parseInt(clockEl.getAttribute('data-half-countdown'), 10) : 0;
			var period = periodEl && periodEl.textContent.indexOf('2nd') !== -1 ? 2 : 1;
			clocks.push({
				game_id: parseInt(nodes[j].getAttribute('data-game-id'), 10),
				half_countdown: ticks,
				period: period,
				sync_epoch: syncEpoch,
			});
		}
		state.liveClocks = clocks;
	}

	function initRoot(root) {
		state.root = root;
		state.revision = root.getAttribute('data-pulse-revision') || '';
		state.signals = parseJsonAttr(root, 'data-pulse-signals', {});
		seedLiveIds(root);
		tickLiveClocks(root);
		pollOnce(root);
		if (state.pollTimer) {
			clearInterval(state.pollTimer);
		}
		if (state.clockTimer) {
			clearInterval(state.clockTimer);
		}
		state.pollTimer = setInterval(function () {
			pollOnce(root);
		}, POLL_MS);
		state.clockTimer = setInterval(function () {
			tickLiveClocks(root);
		}, POLL_MS);
	}

	function boot() {
		var root = document.querySelector('[data-k2-status-room-live]');
		if (!root) {
			return;
		}
		initRoot(root);
	}

	(window.k2OnPageReady || function (fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	})(boot);
})();
