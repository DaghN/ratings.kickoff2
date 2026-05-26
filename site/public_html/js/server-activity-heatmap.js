/**
 * 12-month daily activity heatmap (GitHub-style calendar grid).
 * Renders a CSS-grid of day cells, color-coded by game count.
 * Expects api/server_games_by_day_year.php.
 */
(function () {
    'use strict';

    var API_PATH = 'api/server_games_by_day_year.php';
    var CELL = 11;
    var GAP  = 2;
    var MONTH_ABBR = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var DAY_ABBR   = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

    function isoDay(d) {
        return (d.getDay() + 6) % 7;
    }

    function fmtDate(d) {
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    }

    function friendlyDate(d) {
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function buildLevels(values) {
        var nonZero = values.filter(function (v) { return v > 0; }).sort(function (a, b) { return a - b; });
        if (!nonZero.length) {
            return [0, 1, 2, 3];
        }
        var q1 = nonZero[Math.floor(nonZero.length * 0.25)] || 1;
        var q2 = nonZero[Math.floor(nonZero.length * 0.50)] || q1;
        var q3 = nonZero[Math.floor(nonZero.length * 0.75)] || q2;
        return [q1, q2, q3];
    }

    function toLevel(count, thresholds) {
        if (count === 0) return 0;
        if (count <= thresholds[0]) return 1;
        if (count <= thresholds[1]) return 2;
        if (count <= thresholds[2]) return 3;
        return 4;
    }

    function initRoot(root) {
        var wrap   = root.querySelector('.activity-heatmap-wrap');
        var status = root.querySelector('.server-activity-heatmap-status');
        if (!wrap) return;

        fetch(API_PATH + '?realm=online', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('bad_status');
                return r.json();
            })
            .then(function (data) {
                var days = data.days || [];
                var lookup = {};
                var i;
                for (i = 0; i < days.length; i++) {
                    lookup[days[i].day] = days[i].games;
                }

                var today = new Date();
                today.setHours(0, 0, 0, 0);
                var todayIso = isoDay(today);

                var start = new Date(today);
                start.setDate(start.getDate() - 364);
                var startIso = isoDay(start);
                if (startIso !== 0) {
                    start.setDate(start.getDate() - startIso);
                }

                var allDays = [];
                var d = new Date(start);
                while (d <= today) {
                    var key = fmtDate(d);
                    allDays.push({ date: new Date(d), key: key, games: lookup[key] || 0 });
                    d.setDate(d.getDate() + 1);
                }

                var values = allDays.map(function (x) { return x.games; });
                var thresholds = buildLevels(values);
                var totalWeeks = Math.ceil(allDays.length / 7);

                var container = document.createElement('div');
                container.className = 'activity-heatmap';

                /* Month labels */
                var monthRow = document.createElement('div');
                monthRow.className = 'activity-heatmap__months';
                monthRow.style.cssText =
                    'display:grid;grid-template-columns:28px repeat(' + totalWeeks + ',' + CELL + 'px);gap:' + GAP + 'px;margin-bottom:2px;';

                var labelSpan = document.createElement('span');
                monthRow.appendChild(labelSpan);

                var prevMonth = -1;
                for (i = 0; i < totalWeeks; i++) {
                    var weekStart = allDays[i * 7];
                    if (!weekStart) break;
                    var m = weekStart.date.getMonth();
                    var lbl = document.createElement('span');
                    lbl.className = 'activity-heatmap__month-label';
                    if (m !== prevMonth) {
                        lbl.textContent = MONTH_ABBR[m];
                        prevMonth = m;
                    }
                    monthRow.appendChild(lbl);
                }
                container.appendChild(monthRow);

                /* Grid */
                var grid = document.createElement('div');
                grid.className = 'activity-heatmap__grid';
                grid.style.cssText =
                    'display:grid;grid-template-columns:28px repeat(' + totalWeeks + ',' + CELL + 'px);grid-template-rows:repeat(7,' + CELL + 'px);gap:' + GAP + 'px;';

                for (var row = 0; row < 7; row++) {
                    var dayLabel = document.createElement('span');
                    dayLabel.className = 'activity-heatmap__day-label';
                    if (row === 0 || row === 2 || row === 4) {
                        dayLabel.textContent = DAY_ABBR[row];
                    }
                    dayLabel.style.cssText = 'grid-column:1;grid-row:' + (row + 1) + ';';
                    grid.appendChild(dayLabel);
                }

                for (i = 0; i < allDays.length; i++) {
                    var col = Math.floor(i / 7) + 2;
                    var rowIdx = i % 7;
                    var cell = document.createElement('span');
                    cell.className = 'activity-heatmap__cell';
                    cell.setAttribute('data-level', toLevel(allDays[i].games, thresholds));
                    cell.style.cssText = 'grid-column:' + col + ';grid-row:' + (rowIdx + 1) + ';';
                    cell.title = friendlyDate(allDays[i].date) + ': ' +
                        allDays[i].games + (allDays[i].games === 1 ? ' game' : ' games');
                    grid.appendChild(cell);
                }

                container.appendChild(grid);

                /* Legend */
                var legend = document.createElement('div');
                legend.className = 'activity-heatmap__legend';
                var less = document.createElement('span');
                less.textContent = 'Less';
                legend.appendChild(less);
                for (var lv = 0; lv <= 4; lv++) {
                    var swatch = document.createElement('span');
                    swatch.className = 'activity-heatmap__cell';
                    swatch.setAttribute('data-level', lv);
                    legend.appendChild(swatch);
                }
                var more = document.createElement('span');
                more.textContent = 'More';
                legend.appendChild(more);
                container.appendChild(legend);

                wrap.appendChild(container);

                if (status) status.textContent = '';
            })
            .catch(function () {
                if (status) status.textContent = 'Could not load activity heatmap.';
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-activity-heatmap');
        for (var i = 0; i < roots.length; i++) {
            initRoot(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
