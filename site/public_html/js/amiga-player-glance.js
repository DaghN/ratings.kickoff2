/**
 * Player name hover glance (online + Amiga) — tiers A (compact) and B (hero stat strip);
 * Amiga Elo links use rating mode (rank + rating only) via data-k2-amiga-player-glance-rating
 * and __print--rating (shrink-to-fit shell).
 * Tier toggle: includes/amiga_player_glance_config.php → K2_PLAYER_GLANCE_TIER
 */
(function (global) {
	'use strict';

	var TIP_ID = 'k2-amiga-player-glance-tooltip';
	var API_AMIGA = '/api/amiga_player_glance.php';
	var API_ONLINE = '/api/player_glance.php';
	var SHOW_DELAY_MS = 500;
	var HIDE_DELAY_MS = 100;
	var glanceCfg = global.K2PlayerGlance || global.K2AmigaPlayerGlance;
	var tier = (glanceCfg && glanceCfg.tier === 'B') ? 'B' : 'A';

	var cache = new Map();
	var pending = new Map();
	var showTimer = null;
	var hideTimer = null;
	var hoverSession = null;
	var activeAnchor = null;
	var activePlayerId = null;
	var activeRealm = null;
	var activeMode = null;
	var bound = false;

	function onReady(fn) {
		if (typeof global.k2PageReady === 'function') {
			global.k2PageReady(fn);
			return;
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
			return;
		}
		fn();
	}

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function dash(value) {
		return value === null || value === undefined || value === '' ? '—' : String(value);
	}

	function fmtInt(value) {
		if (value === null || value === undefined || value === '') {
			return '—';
		}
		var n = Number(value);
		return isFinite(n) ? String(Math.round(n)) : '—';
	}

	function flagImgHtml(flagCode) {
		if (!flagCode) {
			return '';
		}
		var src = '/img/flags/amiga/' + encodeURIComponent(flagCode) + '.svg';
		return '<img src="' + src + '" width="20" height="15" alt="" aria-hidden="true" class="k2-amiga-country-flag-img" decoding="async" loading="lazy">';
	}

	function nameLineHtml(data) {
		var name = escapeHtml(data.name || '');
		var flag = flagImgHtml(data.flag_code);
		if (flag) {
			return '<span class="k2-amiga-wc-podium-player">' + flag + '<span>' + name + '</span></span>';
		}
		return name;
	}

	function statBlockHtml(label, value, extraClass) {
		var cls = 'k2-amiga-player-glance__stat-value' + (extraClass ? ' ' + extraClass : '');
		return '<div class="k2-amiga-player-glance__stat">'
			+ '<span class="k2-amiga-player-glance__stat-label">' + escapeHtml(label) + '</span>'
			+ '<span class="' + cls + '">' + escapeHtml(value) + '</span>'
			+ '</div>';
	}

	function heroMilestonesStatHtml(tiers) {
		if (!tiers || !tiers.length) {
			return '';
		}
		var links = tiers.map(function (tier) {
			var token = escapeHtml(tier.token || 'pitch');
			return '<a href="' + escapeHtml(tier.href || '#') + '"'
				+ ' class="k2-player-hero__ms-tier k2-lb-ms-tier--' + token + ' k2-player-hero__ms-tier--' + token + ' k2-table-helped"'
				+ ' data-k2-coarse-tap="1"'
				+ ' data-k2-tooltip-hide-title="1"'
				+ ' data-k2-help="' + escapeHtml(tier.help || '') + '"'
				+ ' data-k2-tooltip-tier="' + token + '"'
				+ ' data-k2-tooltip-action="Click to open the milestone garden"'
				+ ' data-k2-tooltip-action-coarse="Tap again to open the milestone garden"'
				+ '>' + escapeHtml(String(tier.count)) + '</a>';
		}).join('');
		return '<div class="k2-player-hero__stat k2-player-hero__stat--milestones">'
			+ '<span class="k2-player-hero__stat-label">Milestones</span>'
			+ '<span class="k2-player-hero__stat-value k2-player-hero__stat-value--milestones">'
			+ '<span class="k2-player-hero__ms-tiers">' + links + '</span>'
			+ '</span></div>';
	}

	function heroStatHtml(label, value, accent, extraStatClass) {
		var valueClass = 'k2-player-hero__stat-value' + (accent ? ' k2-player-hero__stat-value--accent' : '');
		if (label === 'Rank') {
			valueClass = 'k2-player-hero__stat-value k2-player-hero__stat-value--rank';
		}
		var statClass = 'k2-player-hero__stat' + (extraStatClass ? ' ' + extraStatClass : '');
		return '<div class="' + statClass + '">'
			+ '<span class="k2-player-hero__stat-label">' + escapeHtml(label) + '</span>'
			+ '<span class="' + valueClass + '">' + escapeHtml(value) + '</span>'
			+ '</div>';
	}

	function medalLabelHtml(variant, label) {
		return '<span class="k2-amiga-wc-podium-th k2-amiga-wc-podium-th--' + variant + ' k2-amiga-wc-podium-th--metal-only" role="img">'
			+ '<span class="k2-amiga-wc-podium-th__metal">' + escapeHtml(label) + '</span>'
			+ '</span>';
	}

	function medalStatHtml(variant, label, count) {
		return '<div class="k2-player-hero__stat k2-country-hero__stat--medal">'
			+ '<span class="k2-country-hero__medal-label">' + medalLabelHtml(variant, label) + '</span>'
			+ '<span class="k2-country-hero__medal-value k2-country-hero__medal-value--' + variant + '">' + escapeHtml(String(count)) + '</span>'
			+ '</div>';
	}

	function medalsHtml(wcMedals) {
		var medals = wcMedals || {};
		var blocks = [];
		if ((medals.gold || 0) > 0) {
			blocks.push(medalStatHtml('gold', 'Gold', medals.gold));
		}
		if ((medals.silver || 0) > 0) {
			blocks.push(medalStatHtml('silver', 'Silver', medals.silver));
		}
		if ((medals.bronze || 0) > 0) {
			blocks.push(medalStatHtml('bronze', 'Bronze', medals.bronze));
		}
		if (!blocks.length) {
			return '';
		}
		return '<div class="k2-player-hero__medals" style="--k2-player-hero-medal-count:' + blocks.length + '">'
			+ blocks.join('')
			+ '</div>';
	}

	function preDebutNoteHtml(data) {
		if (!data.pre_debut) {
			return '';
		}
		return '<p class="k2-amiga-player-glance__note">Not on the ladder at this cutoff.</p>';
	}

	function glanceFrameOpen(printTier) {
		return '<div class="k2-amiga-player-glance__frame">'
			+ '<div class="k2-amiga-player-glance__canvas" aria-hidden="true"></div>'
			+ '<div class="k2-amiga-player-glance__lift" aria-hidden="true"></div>'
			+ '<div class="k2-amiga-player-glance__print k2-amiga-player-glance__print--' + printTier + '">';
	}

	function glanceFrameClose() {
		return '</div></div>';
	}

	function renderTierA(data) {
		var initial = (data.name || '?').charAt(0).toUpperCase();
		var rank = data.pre_debut || !data.display ? '—' : (data.rank ? '#' + data.rank : '—');
		var rating = data.pre_debut || !data.display ? '—' : fmtInt(data.rating);
		return '<div class="k2-amiga-player-glance k2-amiga-player-glance--a">'
			+ glanceFrameOpen('a')
			+ '<div class="k2-amiga-player-glance__card" aria-hidden="true">'
			+ '<div class="k2-amiga-player-glance__media"><div class="k2-amiga-player-glance__avatar">' + escapeHtml(initial) + '</div></div>'
			+ '<div class="k2-amiga-player-glance__body">'
			+ '<p class="k2-amiga-player-glance__name">' + nameLineHtml(data) + '</p>'
			+ '<div class="k2-amiga-player-glance__stats">'
			+ statBlockHtml('Rank', rank, 'k2-amiga-player-glance__stat-value--rank')
			+ statBlockHtml('Rating', rating, '')
			+ '</div>'
			+ '</div>'
			+ '</div>'
			+ glanceFrameClose()
			+ '</div>';
	}

	function renderTierBOnline(data) {
		var initial = (data.name || '?').charAt(0).toUpperCase();
		var rank = !data.display ? '—' : (data.rank ? '#' + data.rank : '—');
		var rating = !data.display ? '—' : fmtInt(data.rating);
		var games = fmtInt(data.games);
		var stats = heroStatHtml('Rank', rank, false)
			+ heroStatHtml('Rating', rating, true)
			+ heroStatHtml('Games', games, true)
			+ heroMilestonesStatHtml(data.milestone_tiers);

		return '<div class="k2-amiga-player-glance k2-amiga-player-glance--b k2-amiga-player-glance--online">'
			+ glanceFrameOpen('b')
			+ '<article class="k2-player-hero k2-player-hero--feast k2-amiga-player-glance__hero" aria-hidden="true">'
			+ '<div class="k2-player-hero__inner">'
			+ '<div class="k2-player-hero__media"><div class="k2-player-hero__avatar">' + escapeHtml(initial) + '</div></div>'
			+ '<div class="k2-player-hero__body">'
			+ '<h2 class="k2-player-hero__name">' + escapeHtml(data.name || '') + '</h2>'
			+ '<div class="k2-player-hero__stats">'
			+ stats
			+ '</div>'
			+ '</div>'
			+ '</div>'
			+ '</article>'
			+ glanceFrameClose()
			+ '</div>';
	}

	function renderTierB(data) {
		var initial = (data.name || '?').charAt(0).toUpperCase();
		var rank = data.pre_debut || !data.display ? '—' : (data.rank ? '#' + data.rank : '—');
		var rating = data.pre_debut || !data.display ? '—' : fmtInt(data.rating);
		var events = data.pre_debut ? '—' : fmtInt(data.events);
		var games = data.pre_debut ? '—' : fmtInt(data.games);
		var worldCups = data.pre_debut ? '—' : fmtInt(data.world_cups);
		var medalBlock = data.pre_debut ? '' : medalsHtml(data.wc_medals);

		return '<div class="k2-amiga-player-glance k2-amiga-player-glance--b">'
			+ glanceFrameOpen('b')
			+ '<article class="k2-player-hero k2-player-hero--feast k2-amiga-player-glance__hero" aria-hidden="true">'
			+ '<div class="k2-player-hero__inner">'
			+ '<div class="k2-player-hero__media"><div class="k2-player-hero__avatar">' + escapeHtml(initial) + '</div></div>'
			+ '<div class="k2-player-hero__body">'
			+ '<h2 class="k2-player-hero__name">' + nameLineHtml(data) + '</h2>'
			+ '<div class="k2-player-hero__stats">'
			+ heroStatHtml('Rank', rank, false)
			+ heroStatHtml('Rating', rating, true)
			+ heroStatHtml('Events', events, true)
			+ heroStatHtml('Games', games, true)
			+ heroStatHtml('World Cups', worldCups, true)
			+ medalBlock
			+ '</div>'
			+ '</div>'
			+ '</div>'
			+ '</article>'
			+ preDebutNoteHtml(data)
			+ glanceFrameClose()
			+ '</div>';
	}

	function renderRatingGlance(data) {
		var rank = data.pre_debut || !data.display ? '—' : (data.rank ? '#' + data.rank : '—');
		var rating = data.pre_debut || !data.display ? '—' : fmtInt(data.rating);
		return '<div class="k2-amiga-player-glance k2-amiga-player-glance--rating">'
			+ glanceFrameOpen('rating')
			+ '<div class="k2-amiga-player-glance__card k2-amiga-player-glance__card--rating" aria-hidden="true">'
			+ '<div class="k2-amiga-player-glance__body">'
			+ '<div class="k2-amiga-player-glance__stats">'
			+ statBlockHtml('Rank', rank, 'k2-amiga-player-glance__stat-value--rank')
			+ statBlockHtml('Rating', rating, '')
			+ '</div>'
			+ '</div>'
			+ '</div>'
			+ preDebutNoteHtml(data)
			+ glanceFrameClose()
			+ '</div>';
	}

	function renderGlance(data, realm, mode) {
		if (mode === 'rating' && realm === 'amiga') {
			return renderRatingGlance(data);
		}
		if (tier === 'B') {
			return realm === 'online' ? renderTierBOnline(data) : renderTierB(data);
		}
		return renderTierA(data);
	}

	function glanceTooltip() {
		var tip = document.getElementById(TIP_ID);
		if (tip) {
			return tip;
		}
		tip = document.createElement('div');
		tip.id = TIP_ID;
		tip.className = 'k2-table-tooltip k2-table-tooltip--amiga-player-glance';
		tip.setAttribute('role', 'tooltip');
		tip.setAttribute('aria-hidden', 'true');
		tip.innerHTML = '<div class="k2-table-tooltip__title"></div><div class="k2-table-tooltip__body"></div>';
		tip.hidden = true;
		document.body.appendChild(tip);
		return tip;
	}

	function positionTooltip(anchor, tip) {
		var rect = anchor.getBoundingClientRect();
		var margin = 8;
		var viewW = global.innerWidth;
		tip.style.left = '0px';
		tip.style.top = '0px';
		tip.hidden = false;
		var tipRect = tip.getBoundingClientRect();
		var left = rect.left + rect.width / 2 - tipRect.width / 2;
		if (left + tipRect.width > viewW - margin) {
			left = viewW - margin - tipRect.width;
		}
		if (left < margin) {
			left = margin;
		}
		var top = rect.top - tipRect.height - margin;
		if (top < margin) {
			top = rect.bottom + margin;
			tip.classList.add('k2-table-tooltip--below-anchor');
		} else {
			tip.classList.remove('k2-table-tooltip--below-anchor');
		}
		tip.style.left = Math.round(left) + 'px';
		tip.style.top = Math.round(top) + 'px';
	}

	function cacheKey(playerId, realm) {
		var suffix = '';
		if (realm === 'amiga') {
			var TT = global.K2AmigaTimeTravelUrl;
			if (TT && TT.navigationQuerySuffix) {
				suffix = TT.navigationQuerySuffix();
			}
		}
		return realm + ':' + String(playerId) + suffix;
	}

	function fetchGlance(playerId, realm) {
		var key = cacheKey(playerId, realm);
		if (cache.has(key)) {
			return Promise.resolve(cache.get(key));
		}
		if (pending.has(key)) {
			return pending.get(key);
		}
		var url = (realm === 'amiga' ? API_AMIGA : API_ONLINE) + '?id=' + encodeURIComponent(String(playerId));
		if (realm === 'amiga') {
			var TT = global.K2AmigaTimeTravelUrl;
			if (TT && TT.appendParams) {
				var params = new URLSearchParams();
				TT.appendParams(params);
				var asVal = params.get('as');
				if (asVal) {
					url += '&as=' + encodeURIComponent(asVal);
				}
			}
		}
		var promise = fetch(url)
			.then(function (r) {
				if (!r.ok) {
					throw new Error('fetch_failed');
				}
				return r.json();
			})
			.then(function (data) {
				cache.set(key, data);
				pending.delete(key);
				return data;
			})
			.catch(function (err) {
				pending.delete(key);
				throw err;
			});
		pending.set(key, promise);
		return promise;
	}

	function hideTooltip() {
		var tip = document.getElementById(TIP_ID);
		if (tip) {
			tip.hidden = true;
			tip.setAttribute('aria-hidden', 'true');
		}
		activeAnchor = null;
		activePlayerId = null;
		activeRealm = null;
		activeMode = null;
	}

	function showGlance(anchor, playerId, realm, data, mode) {
		var tip = glanceTooltip();
		var bodyEl = tip.querySelector('.k2-table-tooltip__body');
		if (bodyEl) {
			bodyEl.innerHTML = renderGlance(data, realm, mode);
			if (typeof global.k2TableInitHelpTooltips === 'function') {
				global.k2TableInitHelpTooltips(bodyEl);
			}
		}
		tip.setAttribute('aria-hidden', 'false');
		activeAnchor = anchor;
		activePlayerId = playerId;
		activeRealm = realm;
		activeMode = mode;
		positionTooltip(anchor, tip);
	}

	function tryReveal(session) {
		if (hoverSession !== session || session.failed || !session.delayReady || !session.data) {
			return;
		}
		showGlance(session.anchor, session.playerId, session.realm, session.data, session.mode);
		hoverSession = null;
	}

	function clearShowTimer() {
		if (showTimer) {
			clearTimeout(showTimer);
			showTimer = null;
		}
	}

	function clearHideTimer() {
		if (hideTimer) {
			clearTimeout(hideTimer);
			hideTimer = null;
		}
	}

	function scheduleHide() {
		clearHideTimer();
		hideTimer = setTimeout(function () {
			hideTimer = null;
			hideTooltip();
		}, HIDE_DELAY_MS);
	}

	function scheduleShow(anchor, playerId, realm, mode) {
		clearShowTimer();
		clearHideTimer();
		hideTooltip();

		var session = {
			anchor: anchor,
			playerId: playerId,
			realm: realm,
			mode: mode,
			delayReady: false,
			data: null,
			failed: false
		};
		hoverSession = session;

		showTimer = setTimeout(function () {
			showTimer = null;
			if (hoverSession !== session) {
				return;
			}
			session.delayReady = true;
			tryReveal(session);
		}, SHOW_DELAY_MS);

		fetchGlance(playerId, realm)
			.then(function (data) {
				if (hoverSession !== session) {
					return;
				}
				session.data = data;
				tryReveal(session);
			})
			.catch(function () {
				if (hoverSession !== session) {
					return;
				}
				session.failed = true;
				hoverSession = null;
			});
	}

	function glanceModeFromAnchor(anchor) {
		if (anchor.getAttribute('data-k2-amiga-player-glance-rating')) {
			return 'rating';
		}
		return 'full';
	}

	function realmFromAnchor(anchor) {
		if (!anchor || anchor.nodeType !== 1) {
			return null;
		}
		if (anchor.getAttribute('data-k2-amiga-player-glance-rating')) {
			return 'amiga';
		}
		if (anchor.getAttribute('data-k2-amiga-player-glance')) {
			return 'amiga';
		}
		if (anchor.getAttribute('data-k2-player-glance')) {
			return 'online';
		}
		return null;
	}

	function playerIdFromAnchor(anchor) {
		if (!anchor || anchor.nodeType !== 1) {
			return 0;
		}
		var attrs = ['data-k2-amiga-player-glance', 'data-k2-player-glance', 'data-k2-amiga-player-glance-rating'];
		for (var i = 0; i < attrs.length; i++) {
			var attr = anchor.getAttribute(attrs[i]);
			if (attr) {
				var fromAttr = parseInt(attr, 10);
				if (fromAttr > 0) {
					return fromAttr;
				}
			}
		}
		return 0;
	}

	function isGlanceTrigger(anchor) {
		if (!anchor || anchor.nodeType !== 1 || anchor.tagName !== 'A') {
			return false;
		}
		// Opt-in only: data-k2-*-player-glance on k2_player_link / k2_amiga_player_link (not nav pills that share profile URLs).
		if (anchor.closest('.k2-player-hero, .k2-amiga-player-glance, .k2-h2h2-card')) {
			return false;
		}
		return playerIdFromAnchor(anchor) > 0 && realmFromAnchor(anchor) !== null;
	}

	function onPointerOver(event) {
		var anchor = event.target.closest('a');
		if (!isGlanceTrigger(anchor)) {
			return;
		}
		var playerId = playerIdFromAnchor(anchor);
		var realm = realmFromAnchor(anchor);
		var mode = glanceModeFromAnchor(anchor);
		if (playerId < 1 || !realm) {
			return;
		}
		if (activeAnchor === anchor && activePlayerId === playerId && activeRealm === realm && activeMode === mode) {
			clearHideTimer();
			return;
		}
		scheduleShow(anchor, playerId, realm, mode);
	}

	function onPointerOut(event) {
		if (!activeAnchor) {
			clearShowTimer();
			hoverSession = null;
			return;
		}
		var related = event.relatedTarget;
		if (related && (activeAnchor === related || activeAnchor.contains(related))) {
			return;
		}
		var tip = document.getElementById(TIP_ID);
		if (tip && related && (tip === related || tip.contains(related))) {
			return;
		}
		clearShowTimer();
		hoverSession = null;
		scheduleHide();
	}

	function bind() {
		if (bound) {
			return;
		}
		bound = true;
		document.addEventListener('mouseover', onPointerOver);
		document.addEventListener('mouseout', onPointerOut);
		global.addEventListener('scroll', hideTooltip, true);
		global.addEventListener('resize', hideTooltip);
	}

	onReady(bind);
}(typeof window !== 'undefined' ? window : this));