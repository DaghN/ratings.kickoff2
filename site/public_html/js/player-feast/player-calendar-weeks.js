/**
 * Career played-weeks map — 52 UTC week tiles per year (stored player_period_games).
 * All career years visible; rich tooltips; click → Games tab week filter.
 */
(function () {
	'use strict';

	var WEEKS_PER_YEAR = 52;
	var MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	var WEEKDAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
	var TIP_ID = 'k2-player-calendar-tooltip';
	var WEEK_GAMES_API = '/api/player_feast/player_calendar_week_games.php';
	var MAX_TOOLTIP_GAMES = 8;
	var tipDismissInstalled = false;
	var hideTipTimer = null;
	var activeTipKey = null;

	function pad2(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function formatYmdUtc(d) {
		return d.getUTCFullYear() + '-' + pad2(d.getUTCMonth() + 1) + '-' + pad2(d.getUTCDate());
	}

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	/** Monday UTC of the week containing the given UTC calendar date (matches MySQL WEEKDAY). */
	function mondayUtcContaining(year, monthIndex, day) {
		var d = new Date(Date.UTC(year, monthIndex, day));
		var mondayOffset = (d.getUTCDay() + 6) % 7;
		d.setUTCDate(d.getUTCDate() - mondayOffset);
		return d;
	}

	function yearWeekMondayUtc(year, weekIndex) {
		var week1 = mondayUtcContaining(year, 0, 1);
		var d = new Date(week1.getTime());
		d.setUTCDate(d.getUTCDate() + weekIndex * 7);
		return d;
	}

	function formatSinceDate(ymd) {
		var d = new Date(ymd + 'T00:00:00Z');
		if (isNaN(d.getTime())) {
			return ymd;
		}
		return d.getUTCDate() + ' ' + MONTHS_SHORT[d.getUTCMonth()] + ' ' + d.getUTCFullYear();
	}

	function formatWeekRangeTitle(mondayUtc) {
		var end = new Date(mondayUtc.getTime());
		end.setUTCDate(end.getUTCDate() + 6);
		if (mondayUtc.getUTCMonth() === end.getUTCMonth() && mondayUtc.getUTCFullYear() === end.getUTCFullYear()) {
			return mondayUtc.getUTCDate() + '–' + end.getUTCDate() + ' '
				+ MONTHS_SHORT[mondayUtc.getUTCMonth()] + ' ' + mondayUtc.getUTCFullYear();
		}
		return mondayUtc.getUTCDate() + ' ' + MONTHS_SHORT[mondayUtc.getUTCMonth()] + ' ' + mondayUtc.getUTCFullYear()
			+ ' – ' + end.getUTCDate() + ' ' + MONTHS_SHORT[end.getUTCMonth()] + ' ' + end.getUTCFullYear();
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

	function gamesTabWeekUrl(playerId, mondayYmd) {
		return '/player/games.php?id=' + encodeURIComponent(String(playerId))
			+ '&from=played-weeks&period=week&anchor=' + encodeURIComponent(mondayYmd) + '#day-games';
	}

	function navigateToWeekGames(playerId, mondayYmd) {
		window.location.assign(gamesTabWeekUrl(playerId, mondayYmd));
	}

	function isPlayableWeek(mondayYmd, games, ctx) {
		return games > 0
			&& mondayYmd >= ctx.firstGameMondayYmd
			&& mondayYmd <= ctx.currentMondayYmd;
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

	function renderGamesList(games) {
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

	function gamesTooltipSummary(games) {
		if (games === 0) {
			return '<span class="pm3-cal__tip-count">0</span> rated games';
		}
		var word = games === 1 ? 'rated game' : 'rated games';
		return '<span class="pm3-cal__tip-count">' + games + '</span> ' + word;
	}

	function renderGamesBody(totalGames, games) {
		var html = '<p class="pm3-cal__tip-summary">' + gamesTooltipSummary(totalGames) + '</p>';
		html += renderGamesList(games);
		if (totalGames > MAX_TOOLTIP_GAMES) {
			html += '<p class="pm3-cal__tip-truncated">Showing ' + MAX_TOOLTIP_GAMES + ' of ' + totalGames + '</p>';
		}
		return html;
	}

	function showTooltipShell(cell, tipKey, titleText, bodyHtml) {
		var tip = calendarTooltip();
		var titleEl = tip.querySelector('.k2-table-tooltip__title');
		var bodyEl = tip.querySelector('.k2-table-tooltip__body');
		activeTipKey = tipKey;
		if (titleEl) {
			titleEl.textContent = titleText;
		}
		if (bodyEl) {
			bodyEl.innerHTML = bodyHtml;
			bodyEl.style.display = bodyHtml ? '' : 'none';
		}
		tip.setAttribute('aria-hidden', 'false');
		positionCalendarTooltip(cell, tip);
	}

	function fetchWeekGames(mondayYmd, ctx) {
		var cacheKey = ctx.playerId + ':' + mondayYmd;
		if (ctx.weekGamesCache.has(cacheKey)) {
			return Promise.resolve(ctx.weekGamesCache.get(cacheKey));
		}
		if (ctx.weekGamesPending.has(cacheKey)) {
			return ctx.weekGamesPending.get(cacheKey);
		}
		var promise = fetch(WEEK_GAMES_API + '?id=' + ctx.playerId + '&week=' + encodeURIComponent(mondayYmd))
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
				ctx.weekGamesCache.set(cacheKey, payload);
				ctx.weekGamesPending.delete(cacheKey);
				return payload;
			})
			.catch(function (err) {
				ctx.weekGamesPending.delete(cacheKey);
				throw err;
			});
		ctx.weekGamesPending.set(cacheKey, promise);
		return promise;
	}

	function hideWeekTooltip() {
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

	function scheduleHideWeekTooltip() {
		if (hideTipTimer) {
			clearTimeout(hideTipTimer);
		}
		hideTipTimer = setTimeout(hideWeekTooltip, 80);
	}

	function installTipDismiss() {
		if (tipDismissInstalled) {
			return;
		}
		tipDismissInstalled = true;
		window.addEventListener('scroll', hideWeekTooltip, { passive: true, capture: true });
		document.addEventListener('touchmove', hideWeekTooltip, { passive: true, capture: true });
	}

	function showWeekTooltip(cell, mondayUtc, mondayYmd, games, ctx) {
		var title = formatWeekRangeTitle(mondayUtc);
		if (mondayYmd > ctx.currentMondayYmd) {
			showTooltipShell(cell, mondayYmd, title, 'Future week');
			return;
		}
		if (mondayYmd < ctx.firstGameMondayYmd) {
			showTooltipShell(cell, mondayYmd, title, 'Before first rated game');
			return;
		}
		if (games < 1) {
			showTooltipShell(cell, mondayYmd, title, gamesTooltipSummary(0));
			return;
		}

		showTooltipShell(cell, mondayYmd, title, '<p class="pm3-cal__tip-summary">' + gamesTooltipSummary(games)
			+ '</p><p class="pm3-cal__tip-loading">Loading games…</p>');

		fetchWeekGames(mondayYmd, ctx)
			.then(function (payload) {
				if (activeTipKey !== mondayYmd) {
					return;
				}
				var list = payload.games.slice(0, MAX_TOOLTIP_GAMES);
				showTooltipShell(cell, mondayYmd, title, renderGamesBody(payload.total, list));
			})
			.catch(function () {
				if (activeTipKey !== mondayYmd) {
					return;
				}
				showTooltipShell(cell, mondayYmd, title, 'Could not load games for this week.');
			});
	}

	function bindWeekCellTooltip(cell, mondayUtc, mondayYmd, games, ctx) {
		cell.setAttribute('tabindex', '0');
		if (isPlayableWeek(mondayYmd, games, ctx)) {
			cell.classList.add('pm3-cal__cell--clickable');
			cell.setAttribute('role', 'button');
			cell.setAttribute('aria-label', formatWeekRangeTitle(mondayUtc) + ', ' + games + ' rated games. Click to view games.');
		}
		cell.addEventListener('mouseenter', function () {
			if (hideTipTimer) {
				clearTimeout(hideTipTimer);
				hideTipTimer = null;
			}
			showWeekTooltip(cell, mondayUtc, mondayYmd, games, ctx);
		});
		cell.addEventListener('mouseleave', scheduleHideWeekTooltip);
		cell.addEventListener('focus', function () {
			showWeekTooltip(cell, mondayUtc, mondayYmd, games, ctx);
		});
		cell.addEventListener('blur', hideWeekTooltip);
		cell.addEventListener('click', function () {
			if (!isPlayableWeek(mondayYmd, games, ctx)) {
				return;
			}
			navigateToWeekGames(ctx.playerId, mondayYmd);
		});
		cell.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' && e.key !== ' ') {
				return;
			}
			if (!isPlayableWeek(mondayYmd, games, ctx)) {
				return;
			}
			e.preventDefault();
			navigateToWeekGames(ctx.playerId, mondayYmd);
		});
	}

	function buildYearRow(year, playedMap, ctx) {
		var row = document.createElement('div');
		row.className = 'pm3-cal__year-row';
		row.setAttribute('aria-label', 'Played weeks in ' + year);

		var label = document.createElement('span');
		label.className = 'pm3-cal__year-label';
		label.textContent = String(year);
		row.appendChild(label);

		var grid = document.createElement('div');
		grid.className = 'pm3-cal__week-grid';
		grid.setAttribute('role', 'img');

		var w;
		for (w = 0; w < WEEKS_PER_YEAR; w++) {
			var monday = yearWeekMondayUtc(year, w);
			var key = formatYmdUtc(monday);
			var weekGames = playedMap.has(key) ? playedMap.get(key) : 0;
			var cell = document.createElement('span');
			cell.className = 'pm3-cal__cell';

			if (key < ctx.firstGameMondayYmd) {
				cell.className += ' pm3-cal__cell--before-join';
			} else if (year === ctx.currentYear && key > ctx.currentMondayYmd) {
				cell.className += ' pm3-cal__cell--future';
			} else if (weekGames > 0) {
				cell.className += ' pm3-cal__cell--play';
			}
			bindWeekCellTooltip(cell, monday, key, weekGames, ctx);
			grid.appendChild(cell);
		}
		row.appendChild(grid);
		return row;
	}

	function findCareerStatus(section) {
		var block = section.closest('.pm3d-section');
		return block ? block.querySelector('.pm3-cal__status') : null;
	}

	function setCareerStatus(status, count, playerName, firstGameDateYmd) {
		if (!status) {
			return;
		}
		status.classList.remove('pm3-muted');
		var weekWord = count === 1 ? 'week' : 'weeks';
		var safeName = escapeHtml(playerName || 'This player');
		status.innerHTML = '... and since ' + escapeHtml(formatSinceDate(firstGameDateYmd)) + ', '
			+ '<span class="k2-link-star pm3-cal__status-name">' + safeName + '</span> '
			+ 'has played in no less than <span class="pm3-cal__status-count">' + count + '</span> different ' + weekWord + '.';
	}

	function initSection(section) {
		var playerId = parseInt(section.getAttribute('data-player-id'), 10);
		var firstGameDate = section.getAttribute('data-first-game-date') || '';
		if (!playerId || !/^\d{4}-\d{2}-\d{2}$/.test(firstGameDate)) {
			return;
		}

		var status = findCareerStatus(section);
		var host = section.querySelector('.pm3-cal__years');
		if (!host) {
			return;
		}

		var firstGameMonday = formatYmdUtc(mondayUtcContaining(
			parseInt(firstGameDate.slice(0, 4), 10),
			parseInt(firstGameDate.slice(5, 7), 10) - 1,
			parseInt(firstGameDate.slice(8, 10), 10)
		));
		var now = new Date();
		var currentMonday = formatYmdUtc(mondayUtcContaining(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate()));
		var startYear = parseInt(firstGameDate.slice(0, 4), 10);
		var currentYear = now.getUTCFullYear();
		var ctx = {
			playerId: playerId,
			playerName: section.getAttribute('data-player-name') || '',
			firstGameMondayYmd: firstGameMonday,
			currentMondayYmd: currentMonday,
			currentYear: currentYear,
			weekGamesCache: new Map(),
			weekGamesPending: new Map()
		};

		installTipDismiss();

		fetch('/api/player_feast/player_calendar_weeks.php?id=' + playerId)
			.then(function (r) {
				if (!r.ok) {
					throw new Error('fetch_failed');
				}
				return r.json();
			})
			.then(function (data) {
				var playedMap = new Map();
				(data.weeks || []).forEach(function (w) {
					if (w && w.start) {
						playedMap.set(w.start, w.games || 0);
					}
				});

				host.innerHTML = '';
				var year;
				for (year = startYear; year <= currentYear; year++) {
					host.appendChild(buildYearRow(year, playedMap, ctx));
				}

				setCareerStatus(status, playedMap.size, ctx.playerName, firstGameDate);
			})
			.catch(function () {
				if (status) {
					status.textContent = 'Could not load played weeks.';
				}
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.pm3-cal--weeks[data-player-id]').forEach(initSection);
	});
})();
