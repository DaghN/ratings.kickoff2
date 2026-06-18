/**
 * Career calendar activity map — binary cells for UTC days with rated games.
 * One calendar year at a time; year segment picker; 12 months in one row.
 */
(function () {
	'use strict';

	var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	var WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
	var WEEKDAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
	var SERVER_START = '2017-06-09';
	var TIP_ID = 'k2-player-calendar-tooltip';
	var DAY_GAMES_API = '/api/player_feast/player_calendar_day_games.php';
	var MAX_TOOLTIP_GAMES = 8;
	var tipDismissInstalled = false;
	var hideTipTimer = null;
	var activeTipKey = null;

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
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function formatDayTitle(ymd) {
		var d = new Date(ymd + 'T00:00:00Z');
		return WEEKDAYS[d.getUTCDay()] + ', ' + MONTHS[d.getUTCMonth()] + ' ' + d.getUTCDate() + ', ' + d.getUTCFullYear();
	}

	function formatGameTime(at) {
		var normalized = String(at).replace(' ', 'T');
		if (!/Z$/i.test(normalized)) {
			normalized += 'Z';
		}
		var d = new Date(normalized);
		if (isNaN(d.getTime())) {
			return '—';
		}
		var h = d.getUTCHours();
		var m = d.getUTCMinutes();
		return WEEKDAYS_SHORT[d.getUTCDay()] + ' ' + String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
	}

	function formatRating(value) {
		return value === null || value === undefined ? '—' : String(value);
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

	function renderPlayerSide(name, rating) {
		return '<span class="k2-link-star">' + escapeHtml(name) + '</span>'
			+ ' <span class="pm3-cal__tip-rating k2-link-star">(' + escapeHtml(formatRating(rating)) + ')</span>';
	}

	function renderDayGamesList(games) {
		if (!games.length) {
			return '<p class="pm3-cal__tip-empty">No rated games</p>';
		}
		var html = '<ul class="pm3-cal-day-games-list">';
		var i;
		for (i = 0; i < games.length; i++) {
			var g = games[i];
			html += '<li>';
			html += '<span class="pm3-cal-day-games-list__cell pm3-cal-day-games-list__when">' + escapeHtml(formatGameTime(g.at)) + '</span>';
			html += '<span class="pm3-cal-day-games-list__cell pm3-cal-day-games-list__side pm3-cal-day-games-list__side--a">';
			html += renderPlayerSide(g.name_a, g.rating_a);
			html += '</span>';
			html += '<span class="pm3-cal-day-games-list__cell pm3-cal-day-games-list__goals pm3-cal-day-games-list__goals--a">'
				+ parseInt(g.goals_a, 10) + '</span>';
			html += '<span class="pm3-cal-day-games-list__cell pm3-cal-day-games-list__score-sep" aria-hidden="true">–</span>';
			html += '<span class="pm3-cal-day-games-list__cell pm3-cal-day-games-list__goals pm3-cal-day-games-list__goals--b">'
				+ parseInt(g.goals_b, 10) + '</span>';
			html += '<span class="pm3-cal-day-games-list__cell pm3-cal-day-games-list__side pm3-cal-day-games-list__side--b">';
			html += renderPlayerSide(g.name_b, g.rating_b);
			html += '</span>';
			html += '</li>';
		}
		html += '</ul>';
		return html;
	}

	function renderDayGamesBody(totalGames, games) {
		var html = '<p class="pm3-cal__tip-summary">' + dayTooltipSummary(totalGames) + '</p>';
		html += renderDayGamesList(games);
		if (totalGames > MAX_TOOLTIP_GAMES) {
			html += '<p class="pm3-cal__tip-truncated">Showing ' + MAX_TOOLTIP_GAMES + ' of ' + totalGames + '</p>';
		}
		return html;
	}

	function showTooltipShell(cell, ymd, bodyHtml) {
		var tip = calendarTooltip();
		var titleEl = tip.querySelector('.k2-table-tooltip__title');
		var bodyEl = tip.querySelector('.k2-table-tooltip__body');
		activeTipKey = ymd;
		if (titleEl) {
			titleEl.textContent = formatDayTitle(ymd);
		}
		if (bodyEl) {
			bodyEl.innerHTML = bodyHtml;
			bodyEl.style.display = bodyHtml ? '' : 'none';
		}
		tip.setAttribute('aria-hidden', 'false');
		positionCalendarTooltip(cell, tip);
	}

	function dayTooltipSummary(games) {
		if (games === 0) {
			return '<span class="pm3-cal__tip-count">0</span> rated games';
		}
		var word = games === 1 ? 'rated game' : 'rated games';
		return '<span class="pm3-cal__tip-count">' + games + '</span> ' + word;
	}

	function fetchDayGames(ymd, ctx) {
		var cacheKey = ctx.playerId + ':' + ymd;
		if (ctx.dayGamesCache.has(cacheKey)) {
			return Promise.resolve(ctx.dayGamesCache.get(cacheKey));
		}
		if (ctx.dayGamesPending.has(cacheKey)) {
			return ctx.dayGamesPending.get(cacheKey);
		}
		var promise = fetch(DAY_GAMES_API + '?id=' + ctx.playerId + '&day=' + encodeURIComponent(ymd))
			.then(function (r) {
				if (!r.ok) {
					throw new Error('fetch_failed');
				}
				return r.json();
			})
			.then(function (data) {
				var payload = {
					games: data.games || [],
					total: (data.games || []).length
				};
				ctx.dayGamesCache.set(cacheKey, payload);
				ctx.dayGamesPending.delete(cacheKey);
				return payload;
			})
			.catch(function (err) {
				ctx.dayGamesPending.delete(cacheKey);
				throw err;
			});
		ctx.dayGamesPending.set(cacheKey, promise);
		return promise;
	}

	function showDayTooltip(cell, ymd, games, ctx) {
		if (ymd > ctx.endYmd) {
			showTooltipShell(cell, ymd, 'Future day');
			return;
		}
		if (ymd < ctx.startYmd) {
			showTooltipShell(cell, ymd, 'Before first rated game');
			return;
		}
		if (games < 1) {
			showTooltipShell(cell, ymd, dayTooltipSummary(0));
			return;
		}

		showTooltipShell(cell, ymd, '<p class="pm3-cal__tip-summary">' + dayTooltipSummary(games)
			+ '</p><p class="pm3-cal__tip-loading">Loading games…</p>');

		fetchDayGames(ymd, ctx)
			.then(function (payload) {
				if (activeTipKey !== ymd) {
					return;
				}
				var list = payload.games.slice(0, MAX_TOOLTIP_GAMES);
				showTooltipShell(cell, ymd, renderDayGamesBody(payload.total, list));
			})
			.catch(function () {
				if (activeTipKey !== ymd) {
					return;
				}
				showTooltipShell(cell, ymd, 'Could not load games for this day.');
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
	}

	function installTipDismiss() {
		if (tipDismissInstalled) {
			return;
		}
		tipDismissInstalled = true;
		window.addEventListener('scroll', hideDayTooltip, { passive: true, capture: true });
		document.addEventListener('touchmove', hideDayTooltip, { passive: true, capture: true });
	}

	function bindDayCellTooltip(cell, ymd, games, ctx) {
		cell.setAttribute('tabindex', '0');
		if (isPlayableCalendarDay(ymd, games, ctx)) {
			cell.classList.add('pm3-cal__cell--clickable');
			cell.setAttribute('role', 'button');
			cell.setAttribute('aria-label', formatDayTitle(ymd) + ', ' + games + ' rated games. Click to view games.');
		}
		cell.addEventListener('mouseenter', function () {
			if (hideTipTimer) {
				clearTimeout(hideTipTimer);
				hideTipTimer = null;
			}
			showDayTooltip(cell, ymd, games, ctx);
		});
		cell.addEventListener('mouseleave', scheduleHideDayTooltip);
		cell.addEventListener('focus', function () {
			showDayTooltip(cell, ymd, games, ctx);
		});
		cell.addEventListener('blur', hideDayTooltip);
		cell.addEventListener('click', function () {
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

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.pm3-cal--days[data-player-id]').forEach(initSection);
	});
})();
