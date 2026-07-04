/**
 * Shared played-day game list tooltips (profile calendar heatmap + rating chart by date).
 */
(function (global) {
	'use strict';

	var DAY_GAMES_API = '/api/player_feast/player_calendar_day_games.php';
	var MAX_TOOLTIP_GAMES = 8;
	var STAR_CLASS = 'k2-link-star--chart-amber';
	var WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
	var WEEKDAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
	var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function pad2(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function utcCalendarDayKey(d) {
		if (!d || isNaN(d.getTime())) {
			return '';
		}
		return d.getUTCFullYear() + '-' + pad2(d.getUTCMonth() + 1) + '-' + pad2(d.getUTCDate());
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
			return '\u2014';
		}
		var h = d.getUTCHours();
		var m = d.getUTCMinutes();
		return WEEKDAYS_SHORT[d.getUTCDay()] + ' ' + String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
	}

	function formatRating(value) {
		return value === null || value === undefined ? '\u2014' : String(value);
	}

	function renderPlayerSide(name, rating) {
		return '<span class="' + STAR_CLASS + '">' + escapeHtml(name) + '</span>'
			+ ' <span class="pm3-cal__tip-rating ' + STAR_CLASS + '">(' + escapeHtml(formatRating(rating)) + ')</span>';
	}

	function formatWinningGoalCell(goals, opponentGoals) {
		goals = parseInt(goals, 10);
		opponentGoals = parseInt(opponentGoals, 10);
		return goals > opponentGoals
			? '<strong class="blue">' + goals + '</strong>'
			: String(goals);
	}

	function dayTooltipSummary(games, dayEndRating) {
		if (games === 0) {
			return '<span class="pm3-cal__tip-count ' + STAR_CLASS + '">0</span> rated games';
		}
		var word = games === 1 ? 'rated game' : 'rated games';
		var html = '<span class="pm3-cal__tip-count ' + STAR_CLASS + '">' + games + '</span> ' + word;
		if (dayEndRating != null && dayEndRating !== '' && isFinite(Number(dayEndRating))) {
			html += '. Rating at day end: <span class="' + STAR_CLASS + '">' + escapeHtml(String(dayEndRating)) + '</span>';
		}
		return html;
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
				+ formatWinningGoalCell(g.goals_a, g.goals_b) + '</span>';
			html += '<span class="pm3-cal-day-games-list__cell pm3-cal-day-games-list__score-sep" aria-hidden="true">\u2013</span>';
			html += '<span class="pm3-cal-day-games-list__cell pm3-cal-day-games-list__goals pm3-cal-day-games-list__goals--b">'
				+ formatWinningGoalCell(g.goals_b, g.goals_a) + '</span>';
			html += '<span class="pm3-cal-day-games-list__cell pm3-cal-day-games-list__side pm3-cal-day-games-list__side--b">';
			html += renderPlayerSide(g.name_b, g.rating_b);
			html += '</span>';
			html += '</li>';
		}
		html += '</ul>';
		return html;
	}

	function renderDayGamesBody(totalGames, games, dayEndRating) {
		var html = '<p class="pm3-cal__tip-summary">' + dayTooltipSummary(totalGames, dayEndRating) + '</p>';
		html += renderDayGamesList(games);
		if (totalGames > MAX_TOOLTIP_GAMES) {
			html += '<p class="pm3-cal__tip-truncated">Showing ' + MAX_TOOLTIP_GAMES + ' of ' + totalGames + '</p>';
		}
		return html;
	}

	function createFetchCache() {
		return {
			dayGamesCache: new Map(),
			dayGamesPending: new Map()
		};
	}

	function fetchDayGames(ymd, playerId, cache) {
		var cacheKey = playerId + ':' + ymd;
		if (cache.dayGamesCache.has(cacheKey)) {
			return Promise.resolve(cache.dayGamesCache.get(cacheKey));
		}
		if (cache.dayGamesPending.has(cacheKey)) {
			return cache.dayGamesPending.get(cacheKey);
		}
		var promise = fetch(DAY_GAMES_API + '?id=' + encodeURIComponent(String(playerId)) + '&day=' + encodeURIComponent(ymd))
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
				cache.dayGamesCache.set(cacheKey, payload);
				cache.dayGamesPending.delete(cacheKey);
				return payload;
			})
			.catch(function (err) {
				cache.dayGamesPending.delete(cacheKey);
				throw err;
			});
		cache.dayGamesPending.set(cacheKey, promise);
		return promise;
	}

	function positionTooltipNearPoint(tip, anchorX, anchorY) {
		var tipRect;
		var margin = 12;
		var left;
		var top;
		var viewW = window.innerWidth;
		var viewH = window.innerHeight;
		tip.style.left = '0px';
		tip.style.top = '0px';
		tip.hidden = false;
		tipRect = tip.getBoundingClientRect();
		/* Up-left of cursor by default; flip below when no headroom above. */
		left = anchorX - tipRect.width - margin;
		if (left < margin) {
			left = margin;
		}
		top = anchorY - tipRect.height - margin;
		if (top < margin) {
			top = anchorY + margin;
			tip.classList.add('k2-table-tooltip--below-anchor');
		} else {
			tip.classList.remove('k2-table-tooltip--below-anchor');
		}
		if (top + tipRect.height > viewH - margin) {
			top = Math.max(margin, viewH - margin - tipRect.height);
		}
		if (left + tipRect.width > viewW - margin) {
			left = viewW - margin - tipRect.width;
		}
		tip.style.left = Math.round(left) + 'px';
		tip.style.top = Math.round(top) + 'px';
	}

	function chartStarClass(realm) {
		return realm === 'amiga' ? 'k2-link-star--chart-pitch' : STAR_CLASS;
	}

	function gameListRowFromPoint(pt) {
		if (!pt || !pt.date) {
			return null;
		}
		if (pt.name_a == null || pt.name_b == null) {
			return null;
		}
		return {
			at: pt.date,
			name_a: pt.name_a,
			rating_a: pt.rating_a,
			name_b: pt.name_b,
			rating_b: pt.rating_b,
			goals_a: pt.goals_a,
			goals_b: pt.goals_b
		};
	}

	function renderRatingGameOriginBody(startRating, eventMode, starClass) {
		var label = eventMode ? 'before the first tournament' : 'before the first rated game';
		return '<p class="pm3-cal__tip-summary"><span class="' + starClass + '">' + escapeHtml(String(startRating))
			+ '</span> Elo ' + escapeHtml(label) + '</p>';
	}

	function renderRatingGameTooltipTitle(pt, eventMode) {
		if (pt.isOrigin) {
			return eventMode ? 'Tournament #0 — starting rating' : 'Game #0 — starting rating';
		}
		if (!pt.date) {
			return '';
		}
		return formatDayTitle(String(pt.date).trim().substring(0, 10));
	}

	function renderRatingGameIndexLine(pt, eventMode, starClass) {
		var n = pt.gameNumber != null ? pt.gameNumber : pt.x;
		var label = eventMode ? 'Tournament:' : 'Game:';
		return '<p class="pm3-cal__tip-game-num">' + escapeHtml(label) + ' <span class="' + starClass + '">#'
			+ escapeHtml(String(n)) + '</span></p>';
	}

	function renderRatingGameTooltipBody(pt, options) {
		var opts = options || {};
		var starClass = chartStarClass(opts.realm);
		if (pt.isOrigin) {
			return renderRatingGameOriginBody(opts.startRating || 1600, !!opts.eventMode, starClass);
		}
		var html = renderRatingGameIndexLine(pt, !!opts.eventMode, starClass);
		if (opts.eventMode) {
			if (pt.tournamentName) {
				html += '<p class="pm3-cal__tip-summary">' + escapeHtml(pt.tournamentName) + '</p>';
			}
			return html;
		}
		var row = gameListRowFromPoint(pt);
		if (row) {
			html += renderDayGamesList([row]);
		}
		return html;
	}

	global.K2PlayerCalendarDayTooltip = {
		STAR_CLASS: STAR_CLASS,
		MAX_TOOLTIP_GAMES: MAX_TOOLTIP_GAMES,
		escapeHtml: escapeHtml,
		utcCalendarDayKey: utcCalendarDayKey,
		formatDayTitle: formatDayTitle,
		formatGameTime: formatGameTime,
		dayTooltipSummary: dayTooltipSummary,
		renderPlayerSide: renderPlayerSide,
		renderDayGamesList: renderDayGamesList,
		renderDayGamesBody: renderDayGamesBody,
		createFetchCache: createFetchCache,
		fetchDayGames: fetchDayGames,
		positionTooltipNearPoint: positionTooltipNearPoint,
		renderRatingGameTooltipTitle: renderRatingGameTooltipTitle,
		renderRatingGameTooltipBody: renderRatingGameTooltipBody,
		chartStarClass: chartStarClass
	};
}(typeof window !== 'undefined' ? window : this));