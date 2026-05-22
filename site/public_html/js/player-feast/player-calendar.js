/**
 * Calendar-year activity map — binary green cells for days with rated games.
 */
(function () {
	'use strict';

	var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
	var WEEKDAYS = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

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

	function initSection(section) {
		var playerId = parseInt(section.getAttribute('data-player-id'), 10);
		if (!playerId) {
			return;
		}
		var year = parseInt(section.getAttribute('data-year'), 10) || new Date().getFullYear();
		var status = section.querySelector('.pm3-cal__status');
		var host = section.querySelector('.pm3-cal__year');
		if (!host) {
			return;
		}

		fetch('/api/player_feast/player_calendar_days.php?id=' + playerId + '&year=' + year)
			.then(function (r) {
				if (!r.ok) {
					throw new Error('fetch_failed');
				}
				return r.json();
			})
			.then(function (data) {
				var played = new Set(data.days || []);
				host.innerHTML = '';
				var head = document.createElement('div');
				head.className = 'pm3-cal__weekdays';
				WEEKDAYS.forEach(function (w) {
					var s = document.createElement('span');
					s.textContent = w;
					head.appendChild(s);
				});
				host.appendChild(head);
				var months = document.createElement('div');
				months.className = 'pm3-cal__months';
				var now = new Date();
				var lastMonthIndex = 11;
				if (year > now.getFullYear()) {
					lastMonthIndex = -1;
				} else if (year === now.getFullYear()) {
					lastMonthIndex = now.getMonth();
				}
				var m;
				for (m = 0; m <= lastMonthIndex; m++) {
					months.appendChild(buildMonth(year, m, played));
				}
				host.appendChild(months);
				if (status) {
					var monthCount = lastMonthIndex + 1;
					status.textContent = (data.days ? data.days.length : 0) + ' days with rated games in '
						+ year + (monthCount < 12 ? ' (Jan–' + MONTHS[lastMonthIndex] + ')' : '');
				}
			})
			.catch(function () {
				if (status) {
					status.textContent = 'Could not load calendar activity.';
				}
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.pm3-cal[data-player-id]').forEach(initSection);
	});
})();
