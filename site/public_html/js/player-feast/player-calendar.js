/**
 * Career calendar activity map — binary cells for UTC days with rated games.
 * One calendar year at a time; year segment picker; 12 months in one row.
 */
(function () {
	'use strict';

	var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	var SERVER_START = '2017-06-09';

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

	function buildMonth(year, monthIndex, playedSet) {
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
			var cell = document.createElement('span');
			cell.className = 'pm3-cal__cell' + (playedSet.has(key) ? ' pm3-cal__cell--play' : '');
			cell.title = key + (playedSet.has(key) ? ' · rated game' : '');
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

	function buildYear(year, playedSet, startYmd, endYmd) {
		var wrap = document.createElement('div');
		var months = document.createElement('div');
		var m;
		wrap.className = 'pm3-cal__year-block';
		wrap.setAttribute('aria-label', 'Played days in ' + year);
		months.className = 'pm3-cal__months';
		for (m = 0; m < 12; m++) {
			var monthStart = year + '-' + pad2(m + 1) + '-01';
			var monthEnd = year + '-' + pad2(m + 1) + '-' + pad2(daysInMonth(year, m));
			if (monthEnd < startYmd || monthStart > endYmd) {
				continue;
			}
			months.appendChild(buildMonth(year, m, playedSet));
		}
		wrap.appendChild(months);
		return wrap;
	}

	function countPlayedInYear(playedSet, year, startYmd, endYmd) {
		var count = 0;
		playedSet.forEach(function (key) {
			if (key.slice(0, 4) === String(year) && key >= startYmd && key <= endYmd) {
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

	function renderYear(section, year, playedSet, startYmd, endYmd) {
		var host = section.querySelector('.pm3-cal__year-view');
		var picker = section.querySelector('.pm3-cal__year-picker');
		var status = section.querySelector('.pm3-cal__status');
		if (!host) {
			return;
		}
		host.innerHTML = '';
		host.appendChild(buildYear(year, playedSet, startYmd, endYmd));
		if (picker) {
			setActiveYearButton(picker, year);
		}
		if (status) {
			status.textContent = countPlayedInYear(playedSet, year, startYmd, endYmd)
				+ ' days with rated games in ' + year;
		}
	}

	function buildYearPicker(section, startYear, endYear, playedSet, startYmd, endYmd) {
		var toolbar = section.querySelector('.pm3-cal__toolbar');
		var picker = section.querySelector('.pm3-cal__year-picker');
		var year;
		if (!picker || !toolbar) {
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
				renderYear(section, picked, playedSet, startYmd, endYmd);
			});
			picker.appendChild(btn);
		}
		toolbar.hidden = false;
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
		var status = section.querySelector('.pm3-cal__status');

		fetch('/api/player_feast/player_calendar_days.php?id=' + playerId + '&from=' + fromYmd + '&to=' + toYmd)
			.then(function (r) {
				if (!r.ok) {
					throw new Error('fetch_failed');
				}
				return r.json();
			})
			.then(function (data) {
				var played = new Set(data.days || []);
				var initialYear = buildYearPicker(section, startYear, endYear, played, fromYmd, endYmd);
				renderYear(section, initialYear, played, fromYmd, endYmd);
			})
			.catch(function () {
				if (status) {
					status.textContent = 'Could not load calendar activity.';
				}
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.pm3-cal--days[data-player-id]').forEach(initSection);
	});
})();
