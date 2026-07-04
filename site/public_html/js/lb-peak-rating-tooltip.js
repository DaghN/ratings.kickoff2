/**
 * Online peak-rating LB — hover tooltips on Peak + Peak date cells (games around career peak).
 */
(function () {
	'use strict';

	var API = '/api/lb_peak_rating_context.php';
	var TIP_ID = 'k2-lb-peak-context-tooltip';
	var CELL_SELECTOR = '.k2-lb-peak-context-cell[data-k2-lb-peak-player]';
	var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	var WEEKDAYS_SHORT = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
	var cache = new Map();
	var pending = new Map();
	var hideTipTimer = null;
	var activePlayerId = null;
	var tipDismissInstalled = false;
	var lastPointer = { x: null, y: null };
	var TIP_MARGIN = 8;
	var TIP_OFFSET_X = 12;
	var TIP_OFFSET_Y = 10;

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function formatGameWhen(at) {
		var normalized = String(at).replace(' ', 'T');
		if (!/Z$/i.test(normalized)) {
			normalized += 'Z';
		}
		var d = new Date(normalized);
		if (isNaN(d.getTime())) {
			return '—';
		}
		return WEEKDAYS_SHORT[d.getUTCDay()] + ' ' + MONTHS[d.getUTCMonth()] + ' ' + d.getUTCDate()
			+ ', ' + d.getUTCFullYear() + ' '
			+ String(d.getUTCHours()).padStart(2, '0') + ':' + String(d.getUTCMinutes()).padStart(2, '0');
	}

	function formatRating(value) {
		return value === null || value === undefined ? '—' : String(value);
	}

	function formatRatingDelta(delta) {
		if (delta === null || delta === undefined) {
			return '<span class="k2-lb-peak-context__delta k2-lb-peak-context__delta--muted">—</span>';
		}
		var num = Number(delta);
		if (!isFinite(num)) {
			return '<span class="k2-lb-peak-context__delta k2-lb-peak-context__delta--muted">—</span>';
		}
		var sign = num >= 0 ? '+' : '-';
		var cls = num >= 0 ? 'blue' : 'red';
		return '<span class="k2-lb-peak-context__delta ' + cls + '">' + sign + Math.abs(num).toFixed(1) + '</span>';
	}

	function renderPlayerSide(name, rating) {
		return '<span class="k2-link-star">' + escapeHtml(name) + '</span>'
			+ ' <span class="pm3-cal__tip-rating k2-link-star">(' + escapeHtml(formatRating(rating)) + ')</span>';
	}

	function formatWinningGoalCell(goals, opponentGoals) {
		goals = parseInt(goals, 10);
		opponentGoals = parseInt(opponentGoals, 10);
		return goals > opponentGoals
			? '<strong class="blue">' + goals + '</strong>'
			: String(goals);
	}

	function renderGamesList(games) {
		if (!games.length) {
			return '<p class="pm3-cal__tip-empty">No rated games</p>';
		}
		var html = '<ul class="k2-lb-peak-context-games-list">';
		var i;
		for (i = 0; i < games.length; i++) {
			var g = games[i];
			html += '<li class="k2-lb-peak-context-games-list__row">';
			html += '<span class="k2-lb-peak-context-games-list__cell k2-lb-peak-context-games-list__when">' + escapeHtml(formatGameWhen(g.at)) + '</span>';
			html += '<span class="k2-lb-peak-context-games-list__cell k2-lb-peak-context-games-list__side k2-lb-peak-context-games-list__side--a">';
			html += renderPlayerSide(g.name_a, g.rating_a);
			html += '</span>';
			html += '<span class="k2-lb-peak-context-games-list__cell k2-lb-peak-context-games-list__goals k2-lb-peak-context-games-list__goals--a">'
				+ formatWinningGoalCell(g.goals_a, g.goals_b) + '</span>';
			html += '<span class="k2-lb-peak-context-games-list__cell k2-lb-peak-context-games-list__score-sep" aria-hidden="true">–</span>';
			html += '<span class="k2-lb-peak-context-games-list__cell k2-lb-peak-context-games-list__goals k2-lb-peak-context-games-list__goals--b">'
				+ formatWinningGoalCell(g.goals_b, g.goals_a) + '</span>';
			html += '<span class="k2-lb-peak-context-games-list__cell k2-lb-peak-context-games-list__side k2-lb-peak-context-games-list__side--b">';
			html += renderPlayerSide(g.name_b, g.rating_b);
			html += '</span>';
			html += '<span class="k2-lb-peak-context-games-list__cell k2-lb-peak-context-games-list__delta">' + formatRatingDelta(g.rating_delta) + '</span>';
			html += '</li>';
		}
		html += '</ul>';
		return html;
	}

	function formatPeakTitle(payload) {
		return 'Peak · ' + String(payload.peak_rating);
	}

	function tooltipShell() {
		var tip = document.getElementById(TIP_ID);
		if (tip) {
			return tip;
		}
		tip = document.createElement('div');
		tip.id = TIP_ID;
		tip.className = 'k2-table-tooltip k2-table-tooltip--lb-peak-context';
		tip.setAttribute('role', 'tooltip');
		tip.setAttribute('aria-hidden', 'true');
		tip.innerHTML = '<div class="k2-table-tooltip__title"></div><div class="k2-table-tooltip__body"></div>';
		tip.hidden = true;
		document.body.appendChild(tip);
		return tip;
	}

	function rememberPointer(event) {
		if (event && typeof event.clientX === 'number' && typeof event.clientY === 'number') {
			lastPointer.x = event.clientX;
			lastPointer.y = event.clientY;
		}
	}

	function pointerForAnchor(anchor, pointer) {
		if (pointer && typeof pointer.x === 'number' && typeof pointer.y === 'number') {
			return { x: pointer.x, y: pointer.y };
		}
		var rect = anchor.getBoundingClientRect();
		return {
			x: rect.left + rect.width / 2,
			y: rect.top + rect.height / 2,
		};
	}

	function clamp(value, min, max) {
		return Math.max(min, Math.min(value, max));
	}

	function positionTooltip(anchor, tip, pointer) {
		var pt = pointerForAnchor(anchor, pointer);
		var tipRect;
		var left;
		var top;
		var viewW = window.innerWidth;
		var viewH = window.innerHeight;
		var spaceRight;
		var spaceLeft;
		var spaceBelow;
		var spaceAbove;

		tip.style.left = '0px';
		tip.style.top = '0px';
		tip.hidden = false;
		tipRect = tip.getBoundingClientRect();

		spaceRight = viewW - TIP_MARGIN - (pt.x + TIP_OFFSET_X);
		spaceLeft = pt.x - TIP_OFFSET_X - TIP_MARGIN;
		spaceBelow = viewH - TIP_MARGIN - (pt.y + TIP_OFFSET_Y);
		spaceAbove = pt.y - TIP_OFFSET_Y - TIP_MARGIN;

		if (tipRect.width <= spaceRight) {
			left = pt.x + TIP_OFFSET_X;
		} else if (tipRect.width <= spaceLeft) {
			left = pt.x - tipRect.width - TIP_OFFSET_X;
		} else {
			left = pt.x + TIP_OFFSET_X;
		}

		if (tipRect.height <= spaceBelow) {
			top = pt.y + TIP_OFFSET_Y;
		} else if (tipRect.height <= spaceAbove) {
			top = pt.y - tipRect.height - TIP_OFFSET_Y;
		} else if (spaceBelow >= spaceAbove) {
			top = pt.y + TIP_OFFSET_Y;
		} else {
			top = pt.y - tipRect.height - TIP_OFFSET_Y;
		}

		left = clamp(left, TIP_MARGIN, viewW - tipRect.width - TIP_MARGIN);
		top = clamp(top, TIP_MARGIN, viewH - tipRect.height - TIP_MARGIN);

		tip.classList.remove('k2-table-tooltip--below-anchor');
		tip.style.left = Math.round(left) + 'px';
		tip.style.top = Math.round(top) + 'px';
	}

	function showTooltipShell(cell, playerId, title, bodyHtml) {
		var tip = tooltipShell();
		var titleEl = tip.querySelector('.k2-table-tooltip__title');
		var bodyEl = tip.querySelector('.k2-table-tooltip__body');
		activePlayerId = String(playerId);
		if (titleEl) {
			titleEl.textContent = title || '';
			titleEl.style.display = title ? '' : 'none';
		}
		if (bodyEl) {
			bodyEl.innerHTML = bodyHtml;
		}
		tip.setAttribute('aria-hidden', 'false');
		positionTooltip(cell, tip, lastPointer);
	}

	function hideTooltip() {
		if (hideTipTimer) {
			clearTimeout(hideTipTimer);
			hideTipTimer = null;
		}
		activePlayerId = null;
		var tip = document.getElementById(TIP_ID);
		if (tip) {
			tip.hidden = true;
			tip.setAttribute('aria-hidden', 'true');
		}
	}

	function scheduleHideTooltip() {
		if (hideTipTimer) {
			clearTimeout(hideTipTimer);
		}
		hideTipTimer = setTimeout(hideTooltip, 80);
	}

	function fetchContext(playerId) {
		var key = String(playerId);
		if (cache.has(key)) {
			return Promise.resolve(cache.get(key));
		}
		if (pending.has(key)) {
			return pending.get(key);
		}
		var promise = fetch(API + '?id=' + encodeURIComponent(key))
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

	function showCellTooltip(cell, playerId) {
		showTooltipShell(cell, playerId, '', '<p class="pm3-cal__tip-loading">Loading games…</p>');
		fetchContext(playerId)
			.then(function (payload) {
				if (activePlayerId !== String(playerId)) {
					return;
				}
				var title = payload ? formatPeakTitle(payload) : '';
				var body = renderGamesList((payload && payload.games) || []);
				showTooltipShell(cell, playerId, title, body);
			})
			.catch(function () {
				if (activePlayerId !== String(playerId)) {
					return;
				}
				showTooltipShell(cell, playerId, '', '<p class="pm3-cal__tip-empty">Could not load peak games.</p>');
			});
	}

	function installTipDismiss() {
		if (tipDismissInstalled) {
			return;
		}
		tipDismissInstalled = true;
		window.addEventListener('scroll', hideTooltip, { passive: true, capture: true });
		document.addEventListener('touchmove', hideTooltip, { passive: true, capture: true });
	}

	function bindCell(cell) {
		if (cell.getAttribute('data-k2-lb-peak-bound') === '1') {
			return;
		}
		cell.setAttribute('data-k2-lb-peak-bound', '1');
		var playerId = cell.getAttribute('data-k2-lb-peak-player');
		if (!playerId) {
			return;
		}
		cell.addEventListener('mouseenter', function (event) {
			if (hideTipTimer) {
				clearTimeout(hideTipTimer);
				hideTipTimer = null;
			}
			rememberPointer(event);
			showCellTooltip(cell, playerId);
		});
		cell.addEventListener('mousemove', rememberPointer);
		cell.addEventListener('mouseleave', scheduleHideTooltip);
		cell.addEventListener('focus', function () {
			showCellTooltip(cell, playerId);
		});
		cell.addEventListener('blur', hideTooltip);
		cell.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				hideTooltip();
			}
		});
	}

	function init(root) {
		var scope = root && root.querySelectorAll ? root : document;
		var cells = scope.querySelectorAll ? scope.querySelectorAll(CELL_SELECTOR) : [];
		var i;
		installTipDismiss();
		for (i = 0; i < cells.length; i++) {
			bindCell(cells[i]);
		}
	}

	if (typeof window.k2PageReady === 'function') {
		window.k2PageReady(function () { init(document); });
	} else if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { init(document); });
	} else {
		init(document);
	}

	window.k2LbPeakRatingTooltipInit = init;
})();
