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
		pendingSignalsCommit: null,
		onlineLoginEpochById: {},
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

	function patchStatCount(el, nextValue) {
		if (!el || nextValue == null) {
			return;
		}
		el.textContent = formatNumber(nextValue);
	}

	function leagueContext(root) {
		var comp = document.querySelector('[data-k2-status-period-competitions]');
		if (!comp) {
			return { period: 'week', key: '' };
		}
		var period = comp.getAttribute('data-active-period') || 'week';
		var keys = {};
		if (comp._periodKeys) {
			keys = comp._periodKeys;
		} else {
			keys = parseJsonAttr(comp, 'data-current-keys', {});
		}
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

	/** Ids to ink-glow after a list patch — Recent games (new row). Online uses LastLogin epoch (applyOnlineLoginGlow). */
	function listPatchGlowKeys(slotName, currentKeys, desiredKeys) {
		if (slotName !== 'recent_games') {
			return [];
		}
		var prevSet = {};
		for (var i = 0; i < currentKeys.length; i++) {
			prevSet[currentKeys[i]] = true;
		}
		var glow = [];
		for (var j = 0; j < desiredKeys.length; j++) {
			var key = desiredKeys[j];
			if (!prevSet[key]) {
				glow.push(key);
			}
		}
		return glow;
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
		var allApplied = true;
		for (var i = 0; i < names.length; i++) {
			var p = pending[names[i]];
			if (p) {
				if (names[i] === 'online') {
					if (patchOnline(root, p.section, { forceApply: true }) === false) {
						allApplied = false;
					}
				} else if (patchListSlot(root, names[i], p.section, p.emptyMessage, p.glowSelector, { forceApply: true }) === false) {
					allApplied = false;
				}
			}
		}
		if (allApplied && state.pendingSignalsCommit) {
			commitPulseSignals(root, state.pendingSignalsCommit);
		}
	}

	function patchListSlot(root, slotName, section, emptyMessage, glowSelector, options) {
		options = options || {};
		var forceApply = !!options.forceApply;
		var slot = root.querySelector('[data-k2-status-live-slot="' + slotName + '"]');
		if (!slot || !section) {
			return true;
		}
		if (section.empty) {
			slot.innerHTML = '<p class="k2-status-panel__empty">' + emptyMessage + '</p>';
			return true;
		}
		if (!section.html) {
			return true;
		}

		var incoming = parseListSectionHtml(section.html);
		var existingUl = slot.querySelector('ul');
		if (!existingUl) {
			slot.innerHTML = section.html;
			if (!window.k2LiveGlow || !glowSelector) {
				return true;
			}
			var freshNodes = slot.querySelectorAll(glowSelector);
			for (var f = 0; f < freshNodes.length; f++) {
				window.k2LiveGlow.trigger(freshNodes[f]);
			}
			return true;
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

		// Removals always apply (live + online: drop logouts even during row glow).
		removeStaleListRows(existingUl, nextKeys, slotName === 'live' || slotName === 'online');

		var slotHasGlow = !!existingUl.querySelector('.k2-live-glow');
		var currentKeys = currentListKeys(existingUl);
		var allowDuringGlow = forceApply || slotName === 'live';

		if (listKeysEqual(currentKeys, desiredKeys)) {
			if (!slotHasGlow) {
				for (var sk = 0; sk < desiredKeys.length; sk++) {
					var syncKey = desiredKeys[sk];
					if (existingByKey[syncKey] && templateByKey[syncKey]) {
						existingByKey[syncKey].innerHTML = templateByKey[syncKey].innerHTML;
					}
				}
			}
			return true;
		}

		if (slotHasGlow && !isAppendOnlyListPatch(currentKeys, desiredKeys) && !allowDuringGlow) {
			queuePendingListSection(slotName, section, emptyMessage, glowSelector);
			return false;
		}

		var glowKeys = listPatchGlowKeys(slotName, currentKeys, desiredKeys);
		for (var d = 0; d < desiredKeys.length; d++) {
			var dKey = desiredKeys[d];
			var tpl = templateByKey[dKey];
			if (existingByKey[dKey]) {
				if (!slotHasGlow && !nodeHasActiveGlow(existingByKey[dKey])) {
					existingByKey[dKey].innerHTML = tpl.innerHTML;
				}
			} else {
				existingByKey[dKey] = tpl.cloneNode(true);
			}
		}

		var deferred = false;
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
				if (!allowDuringGlow && nodeHasActiveGlow(node)) {
					queuePendingListSection(slotName, section, emptyMessage, glowSelector);
					deferred = true;
					continue;
				}
				existingUl.insertBefore(node, existingUl.children[r] || null);
			}
		}

		if (window.k2LiveGlow && glowSelector) {
			for (var g = 0; g < glowKeys.length; g++) {
				var glowNode = existingByKey[glowKeys[g]];
				if (glowNode) {
					window.k2LiveGlow.trigger(glowNode);
				}
			}
		}
		return !deferred;
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
			if (prevKey && prevKey !== nextKey) {
				scoreEl.innerHTML = formatLiveScoreHtml(nextA, nextB);
				if (nextA > prevA) {
					pulseScoreSide(scoreEl, 'a');
				}
				if (nextB > prevB) {
					pulseScoreSide(scoreEl, 'b');
				}
			}
			syncScoreState(scoreEl, nextA, nextB);
		}
	}

	function newLiveGameIds(prevIds, nextIds) {
		var ids = [];
		for (var id in nextIds) {
			if (Object.prototype.hasOwnProperty.call(nextIds, id) && !prevIds[id]) {
				ids.push(id);
			}
		}
		return ids;
	}

	function glowLiveKickoffScores(root, gameIds) {
		if (!window.k2LiveGlow || !gameIds || !gameIds.length) {
			return;
		}
		for (var i = 0; i < gameIds.length; i++) {
			var li = root.querySelector('[data-k2-status-live-slot="live"] li[data-game-id="' + gameIds[i] + '"]');
			if (!li) {
				continue;
			}
			var goals = li.querySelectorAll('.k2-status-score .k2-status-score__goal');
			for (var g = 0; g < goals.length; g++) {
				window.k2LiveGlow.scorePulse(goals[g]);
			}
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
		glowLiveKickoffScores(root, newLiveGameIds(prevIds, nextIds));
		syncLiveGameClocks(root, games, syncEpoch);
		state.liveGameIds = nextIds;
	}

	function parseOnlineLoginEpoch(li) {
		if (!li) {
			return 0;
		}
		var n = parseInt(li.getAttribute('data-last-login-epoch'), 10);
		return Number.isFinite(n) ? n : 0;
	}

	/**
	 * Glow when LastLogin advanced (just logged in). Keeps epoch memory for the session so
	 * DOM reappear without a new login does not glow; two same-second logins both glow once.
	 */
	function applyOnlineLoginGlow(root, templateItems) {
		if (!window.k2LiveGlow) {
			return;
		}
		var slot = root.querySelector('[data-k2-status-live-slot="online"]');
		if (!slot) {
			return;
		}
		if (!templateItems || !templateItems.length) {
			return;
		}
		var glowIds = [];
		for (var i = 0; i < templateItems.length; i++) {
			var tpl = templateItems[i];
			var id = tpl.getAttribute('data-player-id');
			if (!id) {
				continue;
			}
			var epoch = parseOnlineLoginEpoch(tpl);
			var prev = state.onlineLoginEpochById[id];
			if (prev === undefined) {
				glowIds.push(id);
			} else if (epoch > prev) {
				glowIds.push(id);
			}
			state.onlineLoginEpochById[id] = epoch;
		}
		for (var g = 0; g < glowIds.length; g++) {
			var li = slot.querySelector('li[data-player-id="' + glowIds[g] + '"]');
			if (li) {
				window.k2LiveGlow.trigger(li);
			}
		}
	}

	function patchOnline(root, section, options) {
		if (!section) {
			return true;
		}
		var countEl = root.querySelector('[data-k2-status-online-count]');
		if (countEl && section.count != null) {
			patchStatCount(countEl, section.count);
		}
		var templateItems = null;
		if (section.html) {
			templateItems = parseListSectionHtml(section.html).items;
		}
		var applied = patchListSlot(
			root,
			'online',
			section,
			'Nobody flagged online \u2014 see recent logins below.',
			null,
			options
		);
		if (applied && templateItems && templateItems.length) {
			applyOnlineLoginGlow(root, templateItems);
		}
		return applied;
	}

	function glowRatingGainers(tbody, gainerIds) {
		if (!tbody || !window.k2LiveGlow || !gainerIds || !gainerIds.length) {
			return;
		}
		for (var i = 0; i < gainerIds.length; i++) {
			var pid = String(gainerIds[i]);
			var row = tbody.querySelector('tr[data-player-id="' + pid + '"]');
			if (!row) {
				continue;
			}
			var ratingLink = row.querySelector('td.k2-status-table__num a.k2-link-star, td.k2-status-table__num a[data-k2-player-glance-rating]');
			if (ratingLink) {
				window.k2LiveGlow.triggerWhite(ratingLink);
			}
		}
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
			glowRatingGainers(tbody, section.rating_gainers);
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
			patchStatCount(gamesEl, section.games);
		}
		var playersEl = root.querySelector('[data-k2-status-arc-players]');
		if (playersEl && section.players != null) {
			patchStatCount(playersEl, section.players);
		}
	}

	function commitPulseSignals(root, body) {
		if (body.signals) {
			state.signals = body.signals;
		}
		if (body.revision) {
			state.revision = body.revision;
			root.setAttribute('data-pulse-revision', body.revision);
		}
		state.pendingSignalsCommit = null;
	}

	function applySections(root, body, syncEpoch) {
		var sections = body.sections || {};
		var cascadeOpts = { forceApply: true };
		var allApplied = true;
		function track(ok) {
			if (ok === false) {
				allApplied = false;
			}
		}
		if (body.cascade) {
			if (sections.live) {
				patchLive(root, sections.live, syncEpoch);
			}
			if (sections.online) {
				track(patchOnline(root, sections.online, cascadeOpts));
			}
			if (sections.logins) {
				track(patchListSlot(root, 'logins', sections.logins, '\u2014', null, cascadeOpts));
			}
			if (sections.registrations) {
				track(patchListSlot(root, 'registrations', sections.registrations, '\u2014', null, cascadeOpts));
			}
			if (sections.recent_games) {
				track(patchListSlot(root, 'recent_games', sections.recent_games, '\u2014', 'li[data-game-id]', cascadeOpts));
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
			return allApplied;
		}
		if (sections.live) {
			patchLive(root, sections.live, syncEpoch);
		}
		if (sections.online) {
			track(patchOnline(root, sections.online));
		}
		if (sections.logins) {
			track(patchListSlot(root, 'logins', sections.logins, '\u2014', null));
		}
		if (sections.registrations) {
			track(patchListSlot(root, 'registrations', sections.registrations, '\u2014', null));
		}
		if (sections.recent_games) {
			track(patchListSlot(root, 'recent_games', sections.recent_games, '\u2014', 'li[data-game-id]'));
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
		return allApplied;
	}

	function resyncLiveClocksFromPulse(root, body) {
		if (!body || !Object.prototype.hasOwnProperty.call(body, 'live_clocks')) {
			return;
		}
		var syncEpoch = body.server_now_epoch || Math.floor(Date.now() / 1000);
		syncLiveGameClocks(root, body.live_clocks || [], syncEpoch);
	}

	function onPulseBody(root, body) {
		if (!body) {
			return;
		}
		if (body.server_now_epoch) {
			syncCompetitionsClock(body.server_now_epoch);
			root.setAttribute('data-pulse-sync-epoch', String(body.server_now_epoch));
		}
		resyncLiveClocksFromPulse(root, body);
		if (body.changed === false) {
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
		}
		syncCompetitionsClock(syncEpoch);
		var applied = applySections(root, body, syncEpoch);
		if (applied) {
			commitPulseSignals(root, body);
		} else {
			state.pendingSignalsCommit = {
				signals: body.signals || null,
				revision: body.revision || '',
			};
		}
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

	function seedOnlineLoginEpochs(root) {
		state.onlineLoginEpochById = {};
		var nodes = root.querySelectorAll('[data-k2-status-live-slot="online"] li[data-player-id]');
		for (var i = 0; i < nodes.length; i++) {
			var id = nodes[i].getAttribute('data-player-id');
			if (!id) {
				continue;
			}
			state.onlineLoginEpochById[id] = parseOnlineLoginEpoch(nodes[i]);
		}
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
		seedOnlineLoginEpochs(root);
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
