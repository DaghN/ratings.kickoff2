/**
 * Career calendar activity map — binary cells for UTC days with rated games.
 * One calendar year at a time; year segment picker; 12 months in one row.
 */
(function () {
	'use strict';

	var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	var WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
	var SERVER_START = '2017-06-09';
	var TIP_ID = 'k2-player-calendar-tooltip';
	var MAX_TOOLTIP_GAMES = 8;
	var DayTip = window.K2PlayerCalendarDayTooltip;
	var tipDismissInstalled = false;
	var hideTipTimer = null;
	var activeTipKey = null;

	function dayTipFn(name) {
		if (!DayTip || typeof DayTip[name] !== 'function') {
			return null;
		}
		return DayTip[name];
	}

	function mondayOffset(year, monthIndex) {
		var d = new Date(year, monthIndex, 1);
		var day = d.getDay();
		return day === 0 ? 6 : day - 1;
	}

	function daysInMonth(year, monthIndex) {
		return new Date(year, monthIndex + 1, 0).getDate();
	}

	function pad2(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function escapeHtml(text) {
		return dayTipFn('escapeHtml') ? DayTip.escapeHtml(text) : String(text);
	}

	function formatDayTitle(ymd) {
		return dayTipFn('formatDayTitle') ? DayTip.formatDayTitle(ymd) : ymd;
	}

	function renderDayGamesBody(totalGames, games) {
		return dayTipFn('renderDayGamesBody')
			? DayTip.renderDayGamesBody(totalGames, games)
			: '';
	}

	function dayTooltipSummary(games) {
		return dayTipFn('dayTooltipSummary')
			? DayTip.dayTooltipSummary(games)
			: String(games) + ' rated games';
	}

	function fetchDayGames(ymd, ctx) {
		return DayTip.fetchDayGames(ymd, ctx.playerId, ctx);
	}

	function gamesTabDayUrl(playerId, ymd) {
		return '/player/games.php?id=' + encodeURIComponent(String(playerId))
			+ '&day=' + encodeURIComponent(ymd) + '#day-games';
	}

	function navigateToDayGames(playerId, ymd) {
		window.location.assign(gamesTabDayUrl(playerId, ymd));
	}

	function isPlayableCalendarDay(ymd, games, ctx) {
		return games > 0 && ymd >= ctx.startYmd && ymd <= ctx.endYmd;
	}

	function calendarTooltip() {
		var tip = document.getElementById(TIP_ID);
		if (tip) {
			return tip;
		}
		tip = document.createElement('div');
		tip.id = TIP_ID;
		tip.className = 'k2-table-tooltip k2-table-tooltip--player-cal';
		tip.setAttribute('role', 'tooltip');
		tip.setAttribute('aria-hidden', 'true');
		tip.innerHTML = '<div class="k2-table-tooltip__title"></div>'
			+ '<div class="k2-table-tooltip__body"></div>';
		tip.hidden = true;
		document.body.appendChild(tip);
		return tip;
	}

	function positionCalendarTooltip(anchor, tip) {
		if (dayTipFn('positionTooltipNearPoint')) {
			var rect = anchor.getBoundingClientRect();
			DayTip.positionTooltipNearPoint(tip, rect.left + rect.width / 2, rect.top);
			return;
		}
		var rect = anchor.getBoundingClientRect();
		var tipRect;
		var margin = 6;
		var left;
		var top;
		var viewW = window.innerWidth;
		tip.style.left = '0px';
		tip.style.top = '0px';
		tip.hidden = false;
		tipRect = tip.getBoundingClientRect();
		left = rect.left + rect.width / 2 - tipRect.width / 2;
		if (left + tipRect.width > viewW - margin) {
			left = viewW - margin - tipRect.width;
		}
		if (left < margin) {
			left = margin;
		}
		top = rect.top - tipRect.height - margin;
		if (top < margin) {
			top = rect.bottom + margin;
			tip.classList.add('k2-table-tooltip--below-anchor');
		} else {
			tip.classList.remove('k2-table-tooltip--below-anchor');
		}
		tip.style.left = Math.round(left) + 'px';
		tip.style.top = Math.round(top) + 'px';
	}

	function coarseHintHtml(pinned) {
		if (!pinned) {
			return '';
		}
		return '<p class="pm3-cal__tip-coarse-hint">Tap again to view games</p>';
	}

	function showTooltipShell(cell, ymd, bodyHtml, pinned) {
		var tip = calendarTooltip();
		var titleEl = tip.querySelector('.k2-table-tooltip__title');
		var bodyEl = tip.querySelector('.k2-table-tooltip__body');
		activeTipKey = ymd;
		if (titleEl) {
			titleEl.textContent = formatDayTitle(ymd);
		}
		if (bodyEl) {
			bodyEl.innerHTML = bodyHtml + coarseHintHtml(pinned);
			bodyEl.style.display = bodyHtml || pinned ? '' : 'none';
		}
		tip.setAttribute('aria-hidden', 'false');
		positionCalendarTooltip(cell, tip);
	}

	function showDayTooltip(cell, ymd, games, ctx, pinned) {
		pinned = !!pinned;
		if (ymd > ctx.endYmd) {
			showTooltipShell(cell, ymd, 'Future day', pinned);
			return;
		}
		if (ymd < ctx.startYmd) {
			showTooltipShell(cell, ymd, 'Before first rated game', pinned);
			return;
		}
		if (games < 1) {
			showTooltipShell(cell, ymd, dayTooltipSummary(0), pinned);
			return;
		}

		showTooltipShell(cell, ymd, '<p class="pm3-cal__tip-summary">' + dayTooltipSummary(games)
			+ '</p><p class="pm3-cal__tip-loading">Loading games…</p>', pinned);

		fetchDayGames(ymd, ctx)
			.then(function (payload) {
				if (activeTipKey !== ymd) {
					return;
				}
				var list = payload.games.slice(0, DayTip ? DayTip.MAX_TOOLTIP_GAMES : MAX_TOOLTIP_GAMES);
				showTooltipShell(cell, ymd, renderDayGamesBody(payload.total, list), pinned);
			})
			.catch(function () {
				if (activeTipKey !== ymd) {
					return;
				}
				showTooltipShell(cell, ymd, 'Could not load games for this day.', pinned);
			});
	}

	function scheduleHideDayTooltip() {
		if (hideTipTimer) {
			clearTimeout(hideTipTimer);
		}
		hideTipTimer = setTimeout(hideDayTooltip, 80);
	}

	function hideDayTooltip() {
		if (hideTipTimer) {
			clearTimeout(hideTipTimer);
			hideTipTimer = null;
		}
		activeTipKey = null;
		var tip = document.getElementById(TIP_ID);
		if (tip) {
			tip.hidden = true;
			tip.setAttribute('aria-hidden', 'true');
		}
		if (window.K2CoarseTap) {
			window.K2CoarseTap.clearAllPins();
		}
	}

	function installTipDismiss() {
		if (tipDismissInstalled) {
			return;
		}
		tipDismissInstalled = true;
		window.addEventListener('scroll', hideDayTooltip, { passive: true, capture: true });
		window.addEventListener('k2-coarse-tap-dismiss', hideDayTooltip);
		if (window.K2CoarseTap && window.K2CoarseTap.isCoarsePointer()) {
			window.K2CoarseTap.installDismiss();
		} else {
			document.addEventListener('touchmove', hideDayTooltip, { passive: true, capture: true });
		}
	}

	function bindDayCellTooltip(cell, ymd, games, ctx) {
		cell.setAttribute('tabindex', '0');
		if (isPlayableCalendarDay(ymd, games, ctx)) {
			cell.classList.add('pm3-cal__cell--clickable');
			cell.setAttribute('role', 'button');
			cell.setAttribute('aria-label', formatDayTitle(ymd) + ', ' + games + ' rated games. Click to view games.');
		}
		if (!window.K2CoarseTap || window.K2CoarseTap.shouldUseHoverTooltips()) {
			cell.addEventListener('mouseenter', function () {
				if (hideTipTimer) {
					clearTimeout(hideTipTimer);
					hideTipTimer = null;
				}
				showDayTooltip(cell, ymd, games, ctx, false);
			});
			cell.addEventListener('mouseleave', scheduleHideDayTooltip);
			cell.addEventListener('focus', function () {
				showDayTooltip(cell, ymd, games, ctx, false);
			});
			cell.addEventListener('blur', hideDayTooltip);
		}
		cell.addEventListener('click', function () {
			var CT = window.K2CoarseTap;
			if (CT && CT.isCoarsePointer()) {
				CT.handleDomTap('player-cal-days', ymd, cell, {
					isActionable: function () {
						return isPlayableCalendarDay(ymd, games, ctx);
					},
					onPreview: function (pinned) {
						if (hideTipTimer) {
							clearTimeout(hideTipTimer);
							hideTipTimer = null;
						}
						showDayTooltip(cell, ymd, games, ctx, pinned);
					},
					onDismiss: hideDayTooltip,
					onConfirm: function () {
						navigateToDayGames(ctx.playerId, ymd);
					}
				});
				return;
			}
			if (!isPlayableCalendarDay(ymd, games, ctx)) {
				return;
			}
			navigateToDayGames(ctx.playerId, ymd);
		});
		cell.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' && e.key !== ' ') {
				return;
			}
			if (!isPlayableCalendarDay(ymd, games, ctx)) {
				return;
			}
			e.preventDefault();
			navigateToDayGames(ctx.playerId, ymd);
		});
	}

	function buildMonth(year, monthIndex, playedMap, ctx) {
		var dim = daysInMonth(year, monthIndex);
		var offset = mondayOffset(year, monthIndex);
		var wrap = document.createElement('div');
		wrap.className = 'pm3-cal__month';
		wrap.setAttribute('aria-label', MONTHS[monthIndex] + ' ' + year);

		var title = document.createElement('p');
		title.className = 'pm3-cal__month-label';
		title.textContent = MONTHS[monthIndex];
		wrap.appendChild(title);

		var grid = document.createElement('div');
		grid.className = 'pm3-cal__grid';
		grid.setAttribute('role', 'img');

		var i;
		for (i = 0; i < offset; i++) {
			var pad = document.createElement('span');
			pad.className = 'pm3-cal__cell pm3-cal__cell--pad';
			pad.setAttribute('aria-hidden', 'true');
			grid.appendChild(pad);
		}
		for (i = 1; i <= dim; i++) {
			var key = year + '-' + pad2(monthIndex + 1) + '-' + pad2(i);
			var dayGames = playedMap.get(key) || 0;
			var cell = document.createElement('span');
			var cellClass = 'pm3-cal__cell';
			if (dayGames > 0) {
				cellClass += ' pm3-cal__cell--play';
			} else if (key > ctx.endYmd) {
				cellClass += ' pm3-cal__cell--future';
			}
			cell.className = cellClass;
			bindDayCellTooltip(cell, key, dayGames, ctx);
			grid.appendChild(cell);
		}
		wrap.appendChild(grid);
		return wrap;
	}

	function maxYmd(a, b) {
		return a > b ? a : b;
	}

	function todayUtcYmd() {
		var d = new Date();
		return d.getUTCFullYear() + '-' + pad2(d.getUTCMonth() + 1) + '-' + pad2(d.getUTCDate());
	}

	function nextUtcDayYmd(ymd) {
		var d = new Date(ymd + 'T00:00:00Z');
		d.setUTCDate(d.getUTCDate() + 1);
		return d.getUTCFullYear() + '-' + pad2(d.getUTCMonth() + 1) + '-' + pad2(d.getUTCDate());
	}

	function buildYear(year, playedMap, ctx) {
		var wrap = document.createElement('div');
		var months = document.createElement('div');
		var m;
		var startYear = parseInt(ctx.startYmd.slice(0, 4), 10);
		var endYear = parseInt(ctx.endYmd.slice(0, 4), 10);
		var showFullYear = year === startYear || year === endYear;
		wrap.className = 'pm3-cal__year-block';
		wrap.setAttribute('aria-label', 'Played days in ' + year);
		months.className = 'pm3-cal__months';
		for (m = 0; m < 12; m++) {
			var monthStart = year + '-' + pad2(m + 1) + '-01';
			var monthEnd = year + '-' + pad2(m + 1) + '-' + pad2(daysInMonth(year, m));
			if (!showFullYear && (monthEnd < ctx.startYmd || monthStart > ctx.endYmd)) {
				continue;
			}
			months.appendChild(buildMonth(year, m, playedMap, ctx));
		}
		wrap.appendChild(months);
		return wrap;
	}

	function countPlayedInYear(playedMap, year, startYmd, endYmd) {
		var count = 0;
		playedMap.forEach(function (games, key) {
			if (games > 0 && key.slice(0, 4) === String(year) && key >= startYmd && key <= endYmd) {
				count += 1;
			}
		});
		return count;
	}

	function setActiveYearButton(picker, year) {
		var buttons = picker.querySelectorAll('.pm3-cal__year-btn');
		var i;
		for (i = 0; i < buttons.length; i++) {
			var btn = buttons[i];
			var active = btn.getAttribute('data-year') === String(year);
			btn.classList.toggle('is-active', active);
			btn.setAttribute('aria-selected', active ? 'true' : 'false');
		}
	}

	function findYearStatus(section) {
		var block = section.closest('.pm3d-section');
		return block ? block.querySelector('.pm3-cal__status') : null;
	}

	function setYearStatus(status, count, year, playerName) {
		if (!status) {
			return;
		}
		status.classList.remove('pm3-muted');
		var dayWord = count === 1 ? 'day' : 'days';
		var safeName = escapeHtml(playerName || 'This player');
		status.innerHTML = 'In ' + year + ', <span class="k2-link-star pm3-cal__status-name">' + safeName + '</span> '
			+ 'enjoyed <span class="pm3-cal__status-count">' + count + '</span> ' + dayWord + ' of online Kick Off 2...';
	}

	function renderYear(section, year, playedMap, ctx) {
		var host = section.querySelector('.pm3-cal__year-view');
		var picker = section.querySelector('.pm3-cal__year-picker');
		var status = findYearStatus(section);
		if (!host) {
			return;
		}
		hideDayTooltip();
		host.innerHTML = '';
		host.appendChild(buildYear(year, playedMap, ctx));
		if (picker) {
			setActiveYearButton(picker, year);
		}
		setYearStatus(status, countPlayedInYear(playedMap, year, ctx.startYmd, ctx.endYmd), year, ctx.playerName);
	}

	function buildYearPicker(section, startYear, endYear, playedMap, ctx) {
		var picker = section.querySelector('.pm3-cal__year-picker');
		var year;
		if (!picker) {
			return endYear;
		}
		picker.innerHTML = '';
		for (year = startYear; year <= endYear; year++) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'pm3d-rating-toggle__btn pm3-cal__year-btn';
			btn.setAttribute('role', 'tab');
			btn.setAttribute('data-year', String(year));
			btn.setAttribute('aria-selected', 'false');
			btn.textContent = String(year);
			btn.addEventListener('click', function () {
				var picked = parseInt(this.getAttribute('data-year'), 10);
				renderYear(section, picked, playedMap, ctx);
			});
			picker.appendChild(btn);
		}
		picker.hidden = false;
		return endYear;
	}

	function initSection(section) {
		if (section.getAttribute('data-pm3-cal-bound') === '1') {
			return;
		}
		section.setAttribute('data-pm3-cal-bound', '1');

		var playerId = parseInt(section.getAttribute('data-player-id'), 10);
		if (!playerId) {
			return;
		}
		var firstGameDate = section.getAttribute('data-first-game-date') || SERVER_START;
		if (!/^\d{4}-\d{2}-\d{2}$/.test(firstGameDate)) {
			firstGameDate = SERVER_START;
		}
		var fromYmd = maxYmd(SERVER_START, firstGameDate);
		var toYmd = nextUtcDayYmd(todayUtcYmd());
		var endYmd = todayUtcYmd();
		var startYear = parseInt(fromYmd.slice(0, 4), 10);
		var endYear = parseInt(endYmd.slice(0, 4), 10);
		var status = findYearStatus(section);
		var ctx = {
			playerId: playerId,
			playerName: section.getAttribute('data-player-name') || '',
			startYmd: fromYmd,
			endYmd: endYmd,
			dayGamesCache: new Map(),
			dayGamesPending: new Map()
		};
		if (DayTip && DayTip.createFetchCache) {
			var fetchCache = DayTip.createFetchCache();
			ctx.dayGamesCache = fetchCache.dayGamesCache;
			ctx.dayGamesPending = fetchCache.dayGamesPending;
		}

		installTipDismiss();

		fetch('/api/player_feast/player_calendar_days.php?id=' + playerId + '&from=' + fromYmd + '&to=' + toYmd)
			.then(function (r) {
				if (!r.ok) {
					throw new Error('fetch_failed');
				}
				return r.json();
			})
			.then(function (data) {
				var playedMap = new Map();
				(data.days || []).forEach(function (row) {
					if (row && row.date) {
						playedMap.set(row.date, row.games || 0);
					}
				});
				var initialYear = buildYearPicker(section, startYear, endYear, playedMap, ctx);
				renderYear(section, initialYear, playedMap, ctx);
			})
			.catch(function () {
				if (status) {
					status.textContent = 'Could not load played days.';
				}
			});
	}

	function bootCalendar() {
		document.querySelectorAll('.pm3-cal--days[data-player-id]').forEach(initSection);
	}

	(window.k2OnPageReady || function (fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	})(bootCalendar);
})();
