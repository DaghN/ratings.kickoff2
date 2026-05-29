/**
 * Career played-weeks map — 52 UTC week tiles per year (stored player_period_games).
 */
(function () {
	'use strict';

	var WEEKS_PER_YEAR = 52;
	var MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

	function pad2(n) {
		return n < 10 ? '0' + n : String(n);
	}

	function formatYmdUtc(d) {
		return d.getUTCFullYear() + '-' + pad2(d.getUTCMonth() + 1) + '-' + pad2(d.getUTCDate());
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

	function formatWeekRangeTitle(mondayUtc, games) {
		var end = new Date(mondayUtc.getTime());
		end.setUTCDate(end.getUTCDate() + 6);
		var range;
		if (mondayUtc.getUTCMonth() === end.getUTCMonth() && mondayUtc.getUTCFullYear() === end.getUTCFullYear()) {
			range = mondayUtc.getUTCDate() + '–' + end.getUTCDate() + ' '
				+ MONTHS_SHORT[mondayUtc.getUTCMonth()] + ' ' + mondayUtc.getUTCFullYear();
		} else {
			range = mondayUtc.getUTCDate() + ' ' + MONTHS_SHORT[mondayUtc.getUTCMonth()] + ' ' + mondayUtc.getUTCFullYear()
				+ ' – ' + end.getUTCDate() + ' ' + MONTHS_SHORT[end.getUTCMonth()] + ' ' + end.getUTCFullYear();
		}
		var gameWord = games === 1 ? 'rated game' : 'rated games';
		return range + ' · ' + games + ' ' + gameWord;
	}

	function buildYearRow(year, playedMap, firstGameMondayYmd, currentMondayYmd) {
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
			var cell = document.createElement('span');
			cell.className = 'pm3-cal__cell';

			if (key < firstGameMondayYmd) {
				cell.className += ' pm3-cal__cell--before-join';
				cell.title = 'Before first rated game';
			} else if (year === new Date().getUTCFullYear() && key > currentMondayYmd) {
				cell.className += ' pm3-cal__cell--future';
				cell.title = 'Future week';
			} else if (playedMap.has(key)) {
				var games = playedMap.get(key);
				cell.className += ' pm3-cal__cell--play';
				cell.title = formatWeekRangeTitle(monday, games);
			} else {
				cell.title = 'No rated games';
			}
			grid.appendChild(cell);
		}
		row.appendChild(grid);
		return row;
	}

	function initSection(section) {
		var playerId = parseInt(section.getAttribute('data-player-id'), 10);
		var firstGameDate = section.getAttribute('data-first-game-date') || '';
		if (!playerId || !/^\d{4}-\d{2}-\d{2}$/.test(firstGameDate)) {
			return;
		}

		var status = section.querySelector('.pm3-cal__status');
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
				var totalPlayed = playedMap.size;
				for (year = startYear; year <= currentYear; year++) {
					host.appendChild(buildYearRow(year, playedMap, firstGameMonday, currentMonday));
				}

				if (status) {
					status.textContent = totalPlayed + ' weeks with rated games since ' + startYear;
				}
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
