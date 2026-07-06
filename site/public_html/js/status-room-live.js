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
		pendingListSections: {},
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

	function nodeHasActiveGlow(el) {
		if (!el) {
			return false;
		}
		if (el.classList && el.classList.contains('k2-live-glow')) {
			return true;
		}
		return !!el.querySelector('.k2-live-glow');
	}

	function parseListSectionHtml(html) {
		var wrap = document.createElement('div');
		wrap.innerHTML = (html || '').trim();
		var ul = wrap.querySelector('ul');
		if (!ul) {
			return { ulClass: '', items: [] };
		}
		return {
			ulClass: ul.className || '',
			items: Array.prototype.slice.call(ul.querySelectorAll(':scope > li')),
		};
	}

	function rowKey(li) {
		return li.getAttribute('data-player-id') || li.getAttribute('data-game-id') || '';
	}

	function currentListKeys(ul) {
		var keys = [];
		for (var c = 0; c < ul.children.length; c++) {
			var k = rowKey(ul.children[c]);
			if (k) {
				keys.push(k);
			}
		}
		return keys;
	}

	/** True when desired = current prefix + optional new rows appended at end (no reorder / mid insert). */
	function isAppendOnlyListPatch(currentKeys, desiredKeys) {
		if (desiredKeys.length < currentKeys.length) {
			return false;
		}
		for (var i = 0; i < currentKeys.length; i++) {
			if (desiredKeys[i] !== currentKeys[i]) {
				return false;
			}
		}
		return true;
	}

	function listKeysEqual(a, b) {
		if (a.length !== b.length) {
			return false;
		}
		for (var i = 0; i < a.length; i++) {
			if (a[i] !== b[i]) {
				return false;
			}
		}
		return true;
	}

	function removeStaleListRows(ul, nextKeys, forceRemove) {
		var stale = Array.prototype.slice.call(ul.children);
		for (var k = 0; k < stale.length; k++) {
			var staleKey = rowKey(stale[k]);
			if (!staleKey || !nextKeys[staleKey]) {
				if (forceRemove || !nodeHasActiveGlow(stale[k])) {
					ul.removeChild(stale[k]);
				}
			}
		}
	}

	function queuePendingListSection(slotName, section, emptyMessage, glowSelector) {
		state.pendingListSections[slotName] = {
			section: section,
			emptyMessage: emptyMessage,
			glowSelector: glowSelector,
		};
	}

	function flushPendingListSections(root) {
		if (!root) {
			return;
		}
		var pending = state.pendingListSections;
		var names = Object.keys(pending);
		if (!names.length) {
			return;
		}
		state.pendingListSections = {};
		for (var i = 0; i < names.length; i++) {
			var p = pending[names[i]];
			if (p) {
				patchListSlot(root, names[i], p.section, p.emptyMessage, p.glowSelector);
			}
		}
	}

	function patchListSlot(root, slotName, section, emptyMessage, glowSelector) {
		var slot = root.querySelector('[data-k2-status-live-slot="' + slotName + '"]');
		if (!slot || !section) {
			return;
		}
		if (section.empty) {
			slot.innerHTML = '<p class="k2-status-panel__empty">' + emptyMessage + '</p>';
			return;
		}
		if (!section.html) {
			return;
		}

		var incoming = parseListSectionHtml(section.html);
		var existingUl = slot.querySelector('ul');
		if (!existingUl) {
			slot.innerHTML = section.html;
			if (!window.k2LiveGlow || !glowSelector) {
				return;
			}
			var freshNodes = slot.querySelectorAll(glowSelector);
			for (var f = 0; f < freshNodes.length; f++) {
				window.k2LiveGlow.trigger(freshNodes[f]);
			}
			return;
		}

		var existingByKey = {};
		var currentItems = existingUl.querySelectorAll(':scope > li');
		for (var i = 0; i < currentItems.length; i++) {
			var prevKey = rowKey(currentItems[i]);
			if (prevKey) {
				existingByKey[prevKey] = currentItems[i];
			}
		}

		if (incoming.ulClass && existingUl.className !== incoming.ulClass) {
			existingUl.className = incoming.ulClass;
		}

		var desiredKeys = [];
		var templateByKey = {};
		for (var j = 0; j < incoming.items.length; j++) {
			var templateLi = incoming.items[j];
			var key = rowKey(templateLi);
			if (!key) {
				continue;
			}
			desiredKeys.push(key);
			templateByKey[key] = templateLi;
		}

		var nextKeys = {};
		for (var nk = 0; nk < desiredKeys.length; nk++) {
			nextKeys[desiredKeys[nk]] = true;
		}

		// Removals always apply (live games must drop finished rows even during score glow).
		removeStaleListRows(existingUl, nextKeys, slotName === 'live');

		var slotHasGlow = !!existingUl.querySelector('.k2-live-glow');
		var currentKeys = currentListKeys(existingUl);

		if (listKeysEqual(currentKeys, desiredKeys)) {
			if (!slotHasGlow) {
				for (var sk = 0; sk < desiredKeys.length; sk++) {
					var syncKey = desiredKeys[sk];
					if (existingByKey[syncKey] && templateByKey[syncKey]) {
						existingByKey[syncKey].innerHTML = templateByKey[syncKey].innerHTML;
					}
				}
			}
			return;
		}

		if (slotHasGlow && !isAppendOnlyListPatch(currentKeys, desiredKeys)) {
			queuePendingListSection(slotName, section, emptyMessage, glowSelector);
			return;
		}

		var newKeys = [];
		for (var d = 0; d < desiredKeys.length; d++) {
			var dKey = desiredKeys[d];
			var tpl = templateByKey[dKey];
			if (existingByKey[dKey]) {
				if (!slotHasGlow && !nodeHasActiveGlow(existingByKey[dKey])) {
					existingByKey[dKey].innerHTML = tpl.innerHTML;
				}
			} else {
				existingByKey[dKey] = tpl.cloneNode(true);
				newKeys.push(dKey);
			}
		}

		if (slotHasGlow) {
			for (var a = currentKeys.length; a < desiredKeys.length; a++) {
				var appendKey = desiredKeys[a];
				var appendNode = existingByKey[appendKey];
				if (appendNode && !appendNode.parentNode) {
					existingUl.appendChild(appendNode);
				}
			}
		} else {
			for (var r = 0; r < desiredKeys.length; r++) {
				var rKey = desiredKeys[r];
				var node = existingByKey[rKey];
				if (!node) {
					continue;
				}
				if (existingUl.children[r] === node) {
					continue;
				}
				if (nodeHasActiveGlow(node)) {
					queuePendingListSection(slotName, section, emptyMessage, glowSelector);
					continue;
				}
				existingUl.insertBefore(node, existingUl.children[r] || null);
			}
		}

		if (window.k2LiveGlow && glowSelector) {
			for (var g = 0; g < newKeys.length; g++) {
				window.k2LiveGlow.trigger(existingByKey[newKeys[g]]);
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

	function formatLiveScoreHtml(scoreA, scoreB) {
		function cell(goals, opp) {
			if (goals > opp) {
				return '<strong class="blue">' + goals + '</strong>';
			}
			return String(goals);
		}
		return '<span class="k2-status-score__goal" data-side="a">' + cell(scoreA, scoreB) + '</span>'
			+ '<span class="k2-scoreline-sep" aria-hidden="true">\u2013</span>'
			+ '<span class="k2-status-score__goal" data-side="b">' + cell(scoreB, scoreA) + '</span>';
	}

	function readScoreSide(scoreEl, side) {
		var goal = scoreEl.querySelector('.k2-status-score__goal[data-side="' + side + '"]');
		if (!goal) {
			return null;
		}
		var n = parseInt(goal.textContent.replace(/\D/g, ''), 10);
		return Number.isFinite(n) ? n : 0;
	}

	function pulseScoreSide(scoreEl, side) {
		var goal = scoreEl.querySelector('.k2-status-score__goal[data-side="' + side + '"]');
		if (goal && window.k2LiveGlow) {
			window.k2LiveGlow.scorePulse(goal);
		}
	}

	function syncScoreState(scoreEl, scoreA, scoreB) {
		scoreEl.setAttribute('data-score-a', String(scoreA));
		scoreEl.setAttribute('data-score-b', String(scoreB));
		scoreEl.setAttribute('data-score-key', scoreA + '-' + scoreB);
	}

	function patchLiveScores(root, games) {
		if (!games) {
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
			var prevKey = scoreEl.getAttribute('data-score-key') || '';
			var prevA = scoreEl.hasAttribute('data-score-a')
				? parseInt(scoreEl.getAttribute('data-score-a'), 10)
				: readScoreSide(scoreEl, 'a');
			var prevB = scoreEl.hasAttribute('data-score-b')
				? parseInt(scoreEl.getAttribute('data-score-b'), 10)
				: readScoreSide(scoreEl, 'b');
			if (prevA == null || prevB == null) {
				prevA = 0;
				prevB = 0;
			}
			var nextA = g.score_a;
			var nextB = g.score_b;
			var nextKey = nextA + '-' + nextB;
			if (prevKey !== nextKey) {
				scoreEl.innerHTML = formatLiveScoreHtml(nextA, nextB);
				if (prevKey) {
					if (nextA > prevA) {
						pulseScoreSide(scoreEl, 'a');
					}
					if (nextB > prevB) {
						pulseScoreSide(scoreEl, 'b');
					}
				}
			}
			syncScoreState(scoreEl, nextA, nextB);
		}
	}

	function liveIdSet(games) {
		var ids = {};
		if (games) {
			for (var i = 0; i < games.length; i++) {
				ids[games[i].game_id] = true;
			}
		}
		return ids;
	}

	function sameLiveIdSet(prev, next) {
		var pk = Object.keys(prev);
		var nk = Object.keys(next);
		if (pk.length !== nk.length) {
			return false;
		}
		for (var i = 0; i < pk.length; i++) {
			if (!next[pk[i]]) {
				return false;
			}
		}
		return true;
	}

	function livePeriodByGame(games) {
		var map = {};
		if (!games) {
			return map;
		}
		for (var i = 0; i < games.length; i++) {
			map[games[i].game_id] = games[i].period || 0;
		}
		return map;
	}

	function livePeriodChanged(games) {
		var next = livePeriodByGame(games);
		for (var i = 0; i < state.liveClocks.length; i++) {
			var item = state.liveClocks[i];
			if (next[item.game_id] != null && next[item.game_id] !== item.period) {
				return true;
			}
		}
		return false;
	}

	function pruneLiveRows(root, games) {
		var nextIds = liveIdSet(games);
		var slot = root.querySelector('[data-k2-status-live-slot="live"]');
		if (!slot) {
			return;
		}
		var rows = slot.querySelectorAll('li[data-game-id]');
		for (var i = 0; i < rows.length; i++) {
			var gid = rows[i].getAttribute('data-game-id');
			if (gid && !nextIds[gid]) {
				rows[i].parentNode.removeChild(rows[i]);
			}
		}
	}

	function patchLive(root, section, syncEpoch) {
		if (!section) {
			return;
		}
		var games = section.games || [];
		var prevIds = state.liveGameIds || {};
		var nextIds = liveIdSet(games);

		if (section.empty || games.length === 0) {
			pruneLiveRows(root, []);
			patchListSlot(root, 'live', section, 'No live games in progress.', 'li[data-game-id]');
			state.liveClocks = [];
			state.liveGameIds = {};
			return;
		}

		pruneLiveRows(root, games);

		if (sameLiveIdSet(prevIds, nextIds)) {
			patchLiveScores(root, games);
			syncLiveGameClocks(root, games, syncEpoch);
			state.liveGameIds = nextIds;
			return;
		}
		patchListSlot(root, 'live', section, 'No live games in progress.', 'li[data-game-id]');
		patchLiveScores(root, games);
		syncLiveGameClocks(root, games, syncEpoch);
		state.liveGameIds = nextIds;
	}

	function patchOnline(root, section) {
		if (!section) {
			return;
		}
		var countEl = root.querySelector('[data-k2-status-online-count]');
		if (countEl && section.count != null) {
			countEl.textContent = formatNumber(section.count);
		}
		patchListSlot(root, 'online', section, 'Nobody flagged online \u2014 see recent logins below.', 'li[data-player-id]');
	}

	function patchRatings(root, section) {
		if (!section) {
			return;
		}
		var countEl = root.querySelector('[data-k2-status-rating-count]');
		if (countEl && section.count != null) {
			countEl.textContent = formatNumber(section.count);
		}
		var tbody = root.querySelector('[data-k2-status-live-slot="ratings"]');
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
			var table = tbody.closest('table');
			if (table && typeof window.k2TableRefreshSortableBody === 'function') {
				window.k2TableRefreshSortableBody(table);
			}
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

	function runCascadeGlow(root, sections) {
		if (!window.k2LiveGlow) {
			return;
		}
		sections = sections || {};
		var delayMs = 150;
		var step = 0;
		function schedule(fn) {
			if (!fn) {
				return;
			}
			setTimeout(fn, step * delayMs);
			step += 1;
		}
		var recentSlot = root.querySelector('[data-k2-status-live-slot="recent_games"]');
		var recentLinks = recentSlot
			? recentSlot.querySelectorAll('li:first-child .k2-status-match a.k2-link-star, li:first-child .k2-status-match a[href*="/player/"]')
			: [];
		if (recentLinks.length) {
			schedule(function () {
				for (var r = 0; r < recentLinks.length; r++) {
					window.k2LiveGlow.trigger(recentLinks[r]);
				}
			});
		}
		var ratingIds = sections.ratings && sections.ratings.highlight_player_ids;
		if (ratingIds && ratingIds.length && window.k2LiveGlow.glowRatingsPlayer) {
			for (var i = 0; i < ratingIds.length; i++) {
				(function (playerId) {
					schedule(function () {
						window.k2LiveGlow.glowRatingsPlayer(root, playerId);
					});
				})(ratingIds[i]);
			}
		}
		var leagueMeta = document.querySelector('[data-competition-meta] .blue');
		schedule(function () {
			if (leagueMeta) {
				window.k2LiveGlow.trigger(leagueMeta);
			}
		});
		schedule(function () {
			var arcGames = root.querySelector('[data-k2-status-arc-games]');
			if (arcGames) {
				window.k2LiveGlow.trigger(arcGames);
			}
		});
	}

	function applySections(root, body, syncEpoch) {
		var sections = body.sections || {};
		if (body.cascade) {
			if (sections.live) {
				patchLive(root, sections.live, syncEpoch);
			}
			if (sections.online) {
				patchOnline(root, sections.online);
			}
			if (sections.logins) {
				patchListSlot(root, 'logins', sections.logins, '\u2014', 'li[data-player-id]');
			}
			if (sections.registrations) {
				patchListSlot(root, 'registrations', sections.registrations, '\u2014', 'li[data-player-id]');
			}
			if (sections.recent_games) {
				patchListSlot(root, 'recent_games', sections.recent_games, '\u2014', 'li[data-game-id]');
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
			runCascadeGlow(root, sections);
			return;
		}
		if (sections.live) {
			patchLive(root, sections.live, syncEpoch);
		}
		if (sections.online) {
			patchOnline(root, sections.online);
		}
		if (sections.logins) {
			patchListSlot(root, 'logins', sections.logins, '\u2014', 'li[data-player-id]');
		}
		if (sections.registrations) {
			patchListSlot(root, 'registrations', sections.registrations, '\u2014', 'li[data-player-id]');
		}
		if (sections.recent_games) {
			patchListSlot(root, 'recent_games', sections.recent_games, '\u2014', 'li[data-game-id]');
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

	/** Poll immediately when tab/window returns — background tabs throttle setInterval. */
	function catchUpOnVisible(root) {
		if (document.visibilityState && document.visibilityState !== 'visible') {
			return;
		}
		pollOnce(root);
		tickLiveClocks(root);
	}

	function bindVisibilityCatchUp(root) {
		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible') {
				catchUpOnVisible(root);
			}
		});
		window.addEventListener('pageshow', function (ev) {
			if (ev.persisted) {
				catchUpOnVisible(root);
			}
		});
		window.addEventListener('focus', function () {
			catchUpOnVisible(root);
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
				var scoreA = readScoreSide(scoreEl, 'a');
				var scoreB = readScoreSide(scoreEl, 'b');
				if (scoreA != null && scoreB != null) {
					syncScoreState(scoreEl, scoreA, scoreB);
				} else {
					scoreEl.setAttribute('data-score-key', scoreEl.textContent.replace(/\s+/g, ''));
				}
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

	function bindGlowIdleFlush(root) {
		if (bindGlowIdleFlush.bound) {
			return;
		}
		bindGlowIdleFlush.bound = true;
		document.addEventListener('k2-live-glow-idle', function () {
			flushPendingListSections(state.root || root);
		});
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
		bindVisibilityCatchUp(root);
		bindGlowIdleFlush(root);
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
