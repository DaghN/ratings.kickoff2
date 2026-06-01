/**
 * Activity charts v2 — single module (lab → promote).
 * See docs/activity-charts.md.
 */
(function (global) {
    'use strict';

    var T = global.K2ChartTheme;
    var DR = global.K2ChartDateRange;
    var REALM = 'online';
    var GAP_MS = 100;
    var FRAME_OPTS = { maintainAspectRatio: false };

    function dayToDate(dayStr) {
        if (!dayStr || dayStr.length < 10) {
            return null;
        }
        var d = new Date(dayStr + 'T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function monthToDate(monthStr) {
        if (!monthStr || monthStr.length < 7) {
            return null;
        }
        var d = new Date(monthStr + '-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function monthToDateMid(monthStr) {
        if (!monthStr || monthStr.length < 7) {
            return null;
        }
        var d = new Date(monthStr + '-15T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function yearToDate(year) {
        var y = parseInt(year, 10);
        if (!y || y < 1000) {
            return null;
        }
        var d = new Date(y + '-01-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function parseGameDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var s = String(dateStr).trim();
        var d = new Date(s.indexOf('T') === -1 ? s.replace(' ', 'T') : s);
        if (isNaN(d.getTime()) && s.length >= 10) {
            d = new Date(s.substring(0, 10) + 'T00:00:00');
        }
        return isNaN(d.getTime()) ? null : d;
    }

    function formatDayKey(d) {
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    }

    /** 30 calendar-day trailing mean; days without games count as 0. */
    function rollingAverageCalendar(dayRows, windowDays) {
        if (!dayRows.length || windowDays < 1) {
            return [];
        }
        var lookup = {};
        var i;
        var first = dayToDate(dayRows[0].day);
        var last = dayToDate(dayRows[dayRows.length - 1].day);
        if (first === null || last === null) {
            return [];
        }
        for (i = 0; i < dayRows.length; i++) {
            lookup[dayRows[i].day] = dayRows[i].active_players;
        }
        var daily = [];
        var d = new Date(first.getTime());
        var endMs = last.getTime();
        while (d.getTime() <= endMs) {
            var key = formatDayKey(d);
            daily.push({
                x: new Date(d.getFullYear(), d.getMonth(), d.getDate()),
                y: lookup[key] || 0
            });
            d.setDate(d.getDate() + 1);
        }
        var result = [];
        var sum = 0;
        var queue = [];
        for (i = 0; i < daily.length; i++) {
            queue.push(daily[i].y);
            sum += daily[i].y;
            if (queue.length > windowDays) {
                sum -= queue.shift();
            }
            if (queue.length === windowDays) {
                result.push({
                    x: daily[i].x,
                    y: Math.round((sum / windowDays) * 100) / 100
                });
            }
        }
        return result;
    }

    function scaleTimeMonth(maxTicks) {
        return {
            type: 'time',
            time: {
                unit: 'month',
                round: 'month',
                displayFormats: {
                    month: 'MMM yyyy',
                    year: 'yyyy'
                }
            },
            ticks: {
                color: T.tickColor(),
                maxRotation: 45,
                autoSkip: true,
                maxTicksLimit: maxTicks || 24
            },
            grid: { color: T.softGrid() }
        };
    }

    /** Time axis for daily series (no month rounding on points). */
    function scaleTimeMonthAxis() {
        return {
            type: 'time',
            time: {
                unit: 'month',
                displayFormats: {
                    month: 'MMM yyyy',
                    year: 'yyyy'
                }
            },
            ticks: {
                color: T.tickColor(),
                maxRotation: 45,
                autoSkip: true,
                maxTicksLimit: 24
            },
            grid: { color: T.softGrid() }
        };
    }

    function scaleYCount() {
        return {
            beginAtZero: true,
            ticks: {
                color: T.tickColor(),
                precision: 0
            },
            grid: { color: T.softGrid() }
        };
    }

    function fetchJson(apiPath, query) {
        var q = query || '';
        if (q && q.charAt(0) !== '?') {
            q = '?' + q;
        }
        if (q.indexOf('realm=') === -1) {
            q += (q ? '&' : '?') + 'realm=' + encodeURIComponent(REALM);
        }
        return fetch(apiPath + q, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                throw new Error('bad_status');
            }
            return r.json();
        });
    }

    function chartCanvas(root) {
        var frame = root.querySelector('.k2-chart-frame');
        if (frame) {
            return frame.querySelector('canvas');
        }
        return root.querySelector('canvas');
    }

    function resizeChart(canvas) {
        if (T && typeof T.resizeActivityChart === 'function') {
            T.resizeActivityChart(canvas);
            return;
        }
        if (!canvas || typeof Chart === 'undefined' || typeof Chart.getChart !== 'function') {
            return;
        }
        var instance = Chart.getChart(canvas);
        if (instance && typeof instance.resize === 'function') {
            instance.resize(0);
        }
    }

    function requireCanvas(root, status) {
        var canvas = chartCanvas(root);
        if (!canvas || typeof Chart === 'undefined' || !T) {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return null;
        }
        return canvas;
    }

    function chartOptions(extra, chartKind) {
        return T.activityChartOptions(Object.assign({}, FRAME_OPTS, extra || {}), {
            chartKind: chartKind || 'none'
        });
    }

    function createChart(canvas, config, chartKind) {
        return T.createActivityChart(canvas, config, chartKind || 'none');
    }

    function mountGamesDay(root) {
        var status = root.querySelector('.server-games-day-chart-status');
        var canvas = requireCanvas(root, status);

        if (!canvas) {
            return Promise.resolve();
        }

        if (status) {
            status.textContent = 'Loading games per day...';
        }

        return fetchJson('api/server_games_by_day_recent.php')
            .then(function (data) {
                var days = data.days || [];
                var chartData = [];
                var i;

                for (i = 0; i < days.length; i++) {
                    var x = dayToDate(days[i].day);
                    if (x === null) {
                        continue;
                    }
                    chartData.push({ x: x, y: days[i].games });
                }

                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No recent rated games to chart.';
                    }
                    return;
                }

                if (status) {
                    status.textContent = '';
                }

                createChart(canvas, {
                    type: 'bar',
                    data: {
                        datasets: [Object.assign({
                            label: 'Games',
                            data: chartData
                        }, T.barStroke(T.pitch()))]
                    },
                    options: chartOptions({
                        plugins: {
                            legend: {
                                labels: { color: T.textMuted() }
                            },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) {
                                            return '';
                                        }
                                        return d.toLocaleDateString(undefined, {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric'
                                        });
                                    },
                                    label: function (item) {
                                        var games = item.parsed.y || 0;
                                        return games + (games === 1 ? ' rated game' : ' rated games');
                                    }
                                }
                            })
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'day',
                                    round: 'day',
                                    displayFormats: {
                                        day: 'MMM d'
                                    }
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    maxRotation: 45,
                                    autoSkip: true,
                                    maxTicksLimit: 10
                                },
                                grid: { color: T.softGrid() }
                            },
                            y: scaleYCount()
                        }
                    }, 'bar')
                }, 'bar');
            })
            .catch(function (err) {
                if (status) {
                    status.textContent = 'Could not load games per day.';
                }
                if (typeof console !== 'undefined' && console.error) {
                    console.error('activity-charts-v2 games-day', err);
                }
            });
    }

    function mountGamesMonth(root) {
        var status = root.querySelector('.server-games-month-chart-status');
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        if (status) {
            status.textContent = 'Loading games per month…';
        }
        return fetchJson('api/server_games_by_month.php')
            .then(function (data) {
                var months = data.months || [];
                var chartData = [];
                var i;
                if (!months.length) {
                    if (status) {
                        status.textContent = 'No rated games to chart.';
                    }
                    return;
                }
                for (i = 0; i < months.length; i++) {
                    var x = monthToDate(months[i].month);
                    if (x === null) {
                        continue;
                    }
                    chartData.push({ x: x, y: months[i].games });
                }
                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No chartable months in server history.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                createChart(canvas, {
                    type: 'bar',
                    data: {
                        datasets: [Object.assign({
                            label: 'Games',
                            data: chartData
                        }, T.barSolid(T.pitch()))]
                    },
                    options: chartOptions({
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) {
                                            return '';
                                        }
                                        return d.toLocaleDateString(undefined, {
                                            year: 'numeric',
                                            month: 'long'
                                        });
                                    }
                                }
                            })
                        },
                        scales: {
                            x: scaleTimeMonth(),
                            y: scaleYCount()
                        }
                    }, 'bar')
                }, 'bar');
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load games per month.';
                }
            });
    }

    function mountGamesYear(root) {
        var status = root.querySelector('.server-games-year-chart-status');
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        if (status) {
            status.textContent = 'Loading games per year…';
        }
        return fetchJson('api/server_games_by_year.php')
            .then(function (data) {
                var years = data.years || [];
                var projection = data.projection || {};
                var labels = [];
                var actualData = [];
                var projectedData = [];
                var i;
                if (!years.length) {
                    if (status) {
                        status.textContent = 'No rated games to chart.';
                    }
                    return;
                }
                for (i = 0; i < years.length; i++) {
                    var y = years[i];
                    labels.push(String(y.year));
                    actualData.push(y.games);
                    projectedData.push(y.is_current ? (y.projected_remainder || 0) : 0);
                }
                if (status) {
                    status.textContent = '';
                }
                createChart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            Object.assign({
                                label: 'Games',
                                data: actualData,
                                stack: 'games'
                            }, T.barStroke(T.pitch())),
                            Object.assign({
                                label: 'Projected',
                                data: projectedData,
                                stack: 'games'
                            }, T.barStroke(T.chrome(), 0.55))
                        ]
                    },
                    options: chartOptions({
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    footer: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var idx = items[0].dataIndex;
                                        var yr = years[idx];
                                        if (!yr.is_current) {
                                            return '';
                                        }
                                        var total = yr.projected_total;
                                        if (total == null) {
                                            return '';
                                        }
                                        var days = projection.days_elapsed;
                                        var dim = projection.days_in_year;
                                        var pace = '';
                                        if (days && dim) {
                                            pace = ' Pace: ' + yr.games + ' games in ' + days + ' of ' + dim + ' days.';
                                        }
                                        return 'Projected full ' + yr.year + ': ~' + total + ' games.' + pace;
                                    },
                                    label: function (ctx) {
                                        var yr = years[ctx.dataIndex];
                                        if (ctx.datasetIndex === 0) {
                                            if (yr.is_current) {
                                                return 'Games YTD: ' + ctx.parsed.y;
                                            }
                                            return 'Games: ' + ctx.parsed.y;
                                        }
                                        if (ctx.parsed.y === 0) {
                                            return null;
                                        }
                                        return 'Projected remainder: ~' + ctx.parsed.y;
                                    }
                                },
                                filter: function (item) {
                                    if (item.datasetIndex === 1 && item.parsed.y === 0) {
                                        return false;
                                    }
                                    return true;
                                }
                            })
                        },
                        scales: {
                            x: {
                                stacked: true,
                                ticks: {
                                    color: T.tickColor(),
                                    maxRotation: 45,
                                    autoSkip: true
                                },
                                grid: { color: T.grid() }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 0
                                },
                                grid: { color: T.grid() }
                            }
                        }
                    }, 'bar')
                }, 'bar');
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load games per year.';
                }
            });
    }

    /* --- Heatmap (DOM, not Chart.js) --- */
    var HEATMAP_LABEL_COL = 28;
    var HEATMAP_GAP = 2;
    var HEATMAP_CELL_MIN = 8;
    var HEATMAP_MONTH_ABBR = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    var HEATMAP_DAY_ABBR = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    var HEATMAP_TIP_ID = 'k2-heatmap-tooltip';

    function heatmapTooltip() {
        var tip = document.getElementById(HEATMAP_TIP_ID);
        if (tip) {
            return tip;
        }
        tip = document.createElement('div');
        tip.id = HEATMAP_TIP_ID;
        tip.className = 'k2-table-tooltip';
        tip.setAttribute('role', 'tooltip');
        tip.setAttribute('aria-hidden', 'true');
        tip.innerHTML = '<div class="k2-table-tooltip__title"></div><div class="k2-table-tooltip__body"></div>';
        tip.hidden = true;
        document.body.appendChild(tip);
        return tip;
    }

    function positionHeatmapTooltip(anchor, tip) {
        var rect = anchor.getBoundingClientRect();
        var tipRect;
        var margin = 8;
        var left;
        var top;
        tip.style.left = '0px';
        tip.style.top = '0px';
        tip.hidden = false;
        tipRect = tip.getBoundingClientRect();
        left = rect.left + rect.width / 2 - tipRect.width / 2;
        left = Math.max(margin, Math.min(left, window.innerWidth - tipRect.width - margin));
        top = rect.top - tipRect.height - margin;
        if (top < margin) {
            top = rect.bottom + margin;
        }
        tip.style.left = Math.round(left) + 'px';
        tip.style.top = Math.round(top) + 'px';
    }

    function showHeatmapTooltip(cell, title, body) {
        var tip = heatmapTooltip();
        var titleEl = tip.querySelector('.k2-table-tooltip__title');
        var bodyEl = tip.querySelector('.k2-table-tooltip__body');
        if (titleEl) {
            titleEl.textContent = title;
        }
        if (bodyEl) {
            bodyEl.textContent = body;
            bodyEl.style.display = body ? '' : 'none';
        }
        tip.setAttribute('aria-hidden', 'false');
        positionHeatmapTooltip(cell, tip);
    }

    function hideHeatmapTooltip() {
        var tip = document.getElementById(HEATMAP_TIP_ID);
        if (tip) {
            tip.hidden = true;
            tip.setAttribute('aria-hidden', 'true');
        }
    }

    var heatmapTouchDismissInstalled = false;

    function installHeatmapTouchDismiss() {
        if (heatmapTouchDismissInstalled) {
            return;
        }
        heatmapTouchDismissInstalled = true;
        window.addEventListener('scroll', hideHeatmapTooltip, { passive: true, capture: true });
        document.addEventListener('touchmove', hideHeatmapTooltip, { passive: true, capture: true });
    }

    function heatmapIsoDay(d) {
        return (d.getDay() + 6) % 7;
    }

    function heatmapFmtDate(d) {
        return d.getFullYear() + '-' +
            String(d.getMonth() + 1).padStart(2, '0') + '-' +
            String(d.getDate()).padStart(2, '0');
    }

    function heatmapFriendlyDate(d) {
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function heatmapBuildLevels(values) {
        var nonZero = values.filter(function (v) { return v > 0; }).sort(function (a, b) { return a - b; });
        if (!nonZero.length) {
            return [0, 1, 2, 3];
        }
        var q1 = nonZero[Math.floor(nonZero.length * 0.25)] || 1;
        var q2 = nonZero[Math.floor(nonZero.length * 0.50)] || q1;
        var q3 = nonZero[Math.floor(nonZero.length * 0.75)] || q2;
        return [q1, q2, q3];
    }

    function heatmapToLevel(count, thresholds) {
        if (count === 0) {
            return 0;
        }
        if (count <= thresholds[0]) {
            return 1;
        }
        if (count <= thresholds[1]) {
            return 2;
        }
        if (count <= thresholds[2]) {
            return 3;
        }
        return 4;
    }

    function heatmapApplyLayout(container, totalWeeks) {
        var wrap = container.parentElement;
        var w;
        var cell;
        var gaps;
        var totalWidth;
        if (!wrap || totalWeeks < 1) {
            return;
        }
        w = wrap.clientWidth;
        if (w < 1) {
            return;
        }
        gaps = HEATMAP_GAP * totalWeeks;
        cell = Math.floor((w - HEATMAP_LABEL_COL - gaps - 1) / totalWeeks);
        if (cell < HEATMAP_CELL_MIN) {
            cell = HEATMAP_CELL_MIN;
        }
        totalWidth = HEATMAP_LABEL_COL + totalWeeks * cell + gaps;
        container.style.setProperty('--heatmap-weeks', String(totalWeeks));
        container.style.setProperty('--heatmap-cell', cell + 'px');
        container.style.setProperty('--heatmap-label-col', HEATMAP_LABEL_COL + 'px');
        container.style.setProperty('--heatmap-gap', HEATMAP_GAP + 'px');
        container.classList.toggle('activity-heatmap--scrolls', totalWidth > w);
    }

    function heatmapBindLayout(container, totalWeeks) {
        var wrap = container.parentElement;
        if (!wrap) {
            return;
        }
        heatmapApplyLayout(container, totalWeeks);
        if (typeof ResizeObserver === 'undefined') {
            return;
        }
        if (container._k2HeatmapRo) {
            container._k2HeatmapRo.disconnect();
        }
        container._k2HeatmapRo = new ResizeObserver(function () {
            heatmapApplyLayout(container, totalWeeks);
        });
        container._k2HeatmapRo.observe(wrap);
    }

    function mountHeatmap(root) {
        var wrap = root.querySelector('.activity-heatmap-wrap');
        var status = root.querySelector('.server-activity-heatmap-status');
        if (!wrap) {
            return Promise.resolve();
        }
        if (status) {
            status.textContent = 'Loading activity heatmap…';
        }
        return fetchJson('api/server_games_by_day_year.php')
            .then(function (data) {
                var days = data.days || [];
                var lookup = {};
                var i;
                for (i = 0; i < days.length; i++) {
                    lookup[days[i].day] = days[i].games;
                }
                var today = new Date();
                today.setHours(0, 0, 0, 0);
                var start = new Date(today);
                start.setDate(start.getDate() - 364);
                var startIso = heatmapIsoDay(start);
                if (startIso !== 0) {
                    start.setDate(start.getDate() - startIso);
                }
                var allDays = [];
                var d = new Date(start);
                while (d <= today) {
                    var key = heatmapFmtDate(d);
                    allDays.push({ date: new Date(d), key: key, games: lookup[key] || 0 });
                    d.setDate(d.getDate() + 1);
                }
                var values = allDays.map(function (x) { return x.games; });
                var thresholds = heatmapBuildLevels(values);
                var totalWeeks = Math.ceil(allDays.length / 7);
                var container = document.createElement('div');
                container.className = 'activity-heatmap';
                var monthRow = document.createElement('div');
                monthRow.className = 'activity-heatmap__months';
                monthRow.appendChild(document.createElement('span'));
                i = 0;
                while (i < totalWeeks) {
                    var weekStart = allDays[i * 7];
                    var span;
                    var j;
                    var m;
                    var monthLbl;
                    if (!weekStart) {
                        break;
                    }
                    m = weekStart.date.getMonth();
                    span = 1;
                    for (j = i + 1; j < totalWeeks; j++) {
                        var ws = allDays[j * 7];
                        if (!ws || ws.date.getMonth() !== m) {
                            break;
                        }
                        span += 1;
                    }
                    monthLbl = document.createElement('span');
                    monthLbl.className = 'activity-heatmap__month-label';
                    monthLbl.textContent = HEATMAP_MONTH_ABBR[m];
                    monthLbl.style.gridColumn = (i + 2) + ' / span ' + span;
                    monthRow.appendChild(monthLbl);
                    i += span;
                }
                container.appendChild(monthRow);
                var grid = document.createElement('div');
                grid.className = 'activity-heatmap__grid';
                var row;
                for (row = 0; row < 7; row++) {
                    var dayLabel = document.createElement('span');
                    dayLabel.className = 'activity-heatmap__day-label';
                    if (row === 0 || row === 2 || row === 4) {
                        dayLabel.textContent = HEATMAP_DAY_ABBR[row];
                    }
                    dayLabel.style.cssText = 'grid-column:1;grid-row:' + (row + 1) + ';';
                    grid.appendChild(dayLabel);
                }
                for (i = 0; i < allDays.length; i++) {
                    var col = Math.floor(i / 7) + 2;
                    var rowIdx = i % 7;
                    var cell = document.createElement('span');
                    cell.className = 'activity-heatmap__cell';
                    cell.setAttribute('data-level', heatmapToLevel(allDays[i].games, thresholds));
                    cell.style.cssText = 'grid-column:' + col + ';grid-row:' + (rowIdx + 1) + ';';
                    if (!T.isCoarsePointer()) {
                        cell.setAttribute('tabindex', '0');
                    }
                    (function (dayInfo, dayCell) {
                        var gamesLabel = dayInfo.games + (dayInfo.games === 1 ? ' rated game' : ' rated games');
                        if (T.isCoarsePointer()) {
                            return;
                        }
                        dayCell.addEventListener('mouseenter', function () {
                            showHeatmapTooltip(dayCell, heatmapFriendlyDate(dayInfo.date), gamesLabel);
                        });
                        dayCell.addEventListener('mouseleave', hideHeatmapTooltip);
                        dayCell.addEventListener('focus', function () {
                            showHeatmapTooltip(dayCell, heatmapFriendlyDate(dayInfo.date), gamesLabel);
                        });
                        dayCell.addEventListener('blur', hideHeatmapTooltip);
                    })(allDays[i], cell);
                    grid.appendChild(cell);
                }
                container.appendChild(grid);
                var legend = document.createElement('div');
                legend.className = 'activity-heatmap__legend';
                var less = document.createElement('span');
                less.textContent = 'Less';
                legend.appendChild(less);
                var lv;
                for (lv = 0; lv <= 4; lv++) {
                    var swatch = document.createElement('span');
                    swatch.className = 'activity-heatmap__cell';
                    swatch.setAttribute('data-level', lv);
                    legend.appendChild(swatch);
                }
                var more = document.createElement('span');
                more.textContent = 'More';
                legend.appendChild(more);
                container.appendChild(legend);
                wrap.innerHTML = '';
                wrap.appendChild(container);
                wrap.addEventListener('mouseleave', hideHeatmapTooltip);
                requestAnimationFrame(function () {
                    heatmapBindLayout(container, totalWeeks);
                });
                if (status) {
                    status.textContent = '';
                }
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load activity heatmap.';
                }
            });
    }

    function mountActivePlayersMonth(root) {
        var status = root.querySelector('.server-active-players-month-chart-status');
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        if (status) {
            status.textContent = 'Loading active players per month…';
        }
        return fetchJson('api/server_active_players_by_month.php')
            .then(function (data) {
                var months = data.months || [];
                var chartData = [];
                var i;
                if (!months.length) {
                    if (status) {
                        status.textContent = 'No active player data to chart.';
                    }
                    return;
                }
                for (i = 0; i < months.length; i++) {
                    var x = monthToDate(months[i].month);
                    if (x === null) {
                        continue;
                    }
                    chartData.push({ x: x, y: months[i].active_players });
                }
                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No chartable months in server history.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                createChart(canvas, {
                    type: 'bar',
                    data: {
                        datasets: [Object.assign({
                            label: 'Active players',
                            data: chartData
                        }, T.barSolid(T.chrome()))]
                    },
                    options: chartOptions({
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) {
                                            return '';
                                        }
                                        return d.toLocaleDateString(undefined, {
                                            year: 'numeric',
                                            month: 'long'
                                        });
                                    }
                                }
                            })
                        },
                        scales: {
                            x: scaleTimeMonth(),
                            y: scaleYCount()
                        }
                    }, 'bar')
                }, 'bar');
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load active players per month.';
                }
            });
    }

    function mountDailyActivePlayers(root) {
        var status = root.querySelector('.server-daily-active-players-chart-status');
        var canvas = requireCanvas(root, status);
        var WINDOW = 30;
        if (!canvas) {
            return Promise.resolve();
        }
        if (status) {
            status.textContent = 'Loading daily active players…';
        }
        return fetchJson('api/server_daily_active_players.php', 'source=stored')
            .then(function (data) {
                var days = data.days || [];
                if (!days.length) {
                    if (status) {
                        status.textContent = 'No daily activity data to chart.';
                    }
                    return;
                }
                if (days.length < WINDOW) {
                    if (status) {
                        status.textContent = 'Not enough data for a 30-day average.';
                    }
                    return;
                }
                var smoothed = rollingAverageCalendar(days, WINDOW);
                if (!smoothed.length) {
                    if (status) {
                        status.textContent = 'Could not compute rolling average.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                createChart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [Object.assign({
                            label: '30-day avg active players',
                            data: smoothed,
                            pointRadius: 0,
                            pointHitRadius: 6,
                            stepped: false,
                            tension: 0.3,
                            fill: true
                        }, T.lineStroke(T.chrome()))]
                    },
                    options: chartOptions({
                        elements: {
                            line: {
                                stepped: false
                            }
                        },
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) {
                                            return '';
                                        }
                                        return d.toLocaleDateString(undefined, {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric'
                                        });
                                    },
                                    label: function (item) {
                                        return item.parsed.y.toFixed(1) + ' players (30-day avg)';
                                    }
                                }
                            })
                        },
                        scales: {
                            x: scaleTimeMonthAxis(),
                            y: scaleYCount()
                        }
                    }, 'line')
                }, 'line');
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load daily active players.';
                }
            });
    }

    function mountMatchupBreadth(root) {
        var status = root.querySelector('.server-matchup-breadth-chart-status');
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson('api/server_matchup_breadth.php')
            .then(function (data) {
                var months = data.months || [];
                var chartData = [];
                var i;
                if (!months.length) {
                    if (status) {
                        status.textContent = 'No matchup data to chart.';
                    }
                    return;
                }
                for (i = 0; i < months.length; i++) {
                    var x = monthToDate(months[i].month);
                    if (!x) {
                        continue;
                    }
                    chartData.push({ x: x, y: months[i].unique_pairs });
                }
                if (status) {
                    status.textContent = '';
                }
                createChart(canvas, {
                    type: 'bar',
                    data: {
                        datasets: [Object.assign({
                            label: 'Unique matchups',
                            data: chartData
                        }, T.barSolid(T.holo()))]
                    },
                    options: chartOptions({
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) {
                                            return '';
                                        }
                                        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long' });
                                    },
                                    label: function (item) {
                                        var v = item.parsed.y || 0;
                                        return v + ' distinct ' + (v === 1 ? 'pairing' : 'pairings');
                                    }
                                }
                            })
                        },
                        scales: {
                            x: scaleTimeMonth(),
                            y: scaleYCount()
                        }
                    }, 'bar')
                }, 'bar');
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load matchup breadth.';
                }
            });
    }

    function mountEstablishedYear(root) {
        var status = root.querySelector('.server-established-players-year-chart-status');
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        if (status) {
            status.textContent = 'Loading newly established players per year…';
        }
        return fetchJson('api/server_established_players_by_year.php')
            .then(function (data) {
                var years = data.years || [];
                var gamesRequired = data.games_required || 20;
                var chartData = [];
                var i;
                if (!years.length) {
                    if (status) {
                        status.textContent = 'No established-player data to chart.';
                    }
                    return;
                }
                for (i = 0; i < years.length; i++) {
                    var x = yearToDate(years[i].year);
                    if (x === null) {
                        continue;
                    }
                    chartData.push({ x: x, y: years[i].established_players });
                }
                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No chartable years in server history.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                createChart(canvas, {
                    type: 'bar',
                    data: {
                        datasets: [Object.assign({
                            label: 'New established players (' + gamesRequired + '+ games)',
                            data: chartData
                        }, T.barStroke(T.magenta()))]
                    },
                    options: chartOptions({
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) {
                                            return '';
                                        }
                                        return String(d.getFullYear());
                                    },
                                    afterLabel: function () {
                                        return gamesRequired + 'th rated game in this year';
                                    }
                                }
                            })
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'year',
                                    round: 'year',
                                    displayFormats: { year: 'yyyy' }
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    maxRotation: 45,
                                    autoSkip: true
                                },
                                grid: { color: T.grid() }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 0
                                },
                                grid: { color: T.grid() }
                            }
                        }
                    }, 'bar')
                }, 'bar');
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load established players per year.';
                }
            });
    }

    function mountCumulativeEstablished(root) {
        var status = root.querySelector('.server-cumulative-established-month-chart-status');
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        if (status) {
            status.textContent = 'Loading cumulative established players…';
        }
        return fetchJson('api/server_cumulative_established_by_month.php')
            .then(function (data) {
                var events = data.events || [];
                var gamesRequired = data.games_required || 20;
                var chartData = [];
                var i;
                if (!events.length) {
                    if (status) {
                        status.textContent = 'No established-player history to chart.';
                    }
                    return;
                }
                for (i = 0; i < events.length; i++) {
                    var x = parseGameDate(events[i].date);
                    if (x === null) {
                        continue;
                    }
                    chartData.push({ x: x, y: events[i].cumulative_established });
                }
                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No chartable establishment events.';
                    }
                    return;
                }
                if (DR && DR.appendRatingThroughToday) {
                    chartData = DR.appendRatingThroughToday(chartData);
                }
                if (status) {
                    status.textContent = '';
                }
                createChart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [Object.assign({
                            label: 'Cumulative established (' + gamesRequired + '+ games)',
                            data: chartData,
                            fill: true,
                            stepped: true,
                            pointRadius: 0,
                            pointHitRadius: 6
                        }, T.lineStroke(T.magenta()))]
                    },
                    options: chartOptions({
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) {
                                            return '';
                                        }
                                        return d.toLocaleDateString(undefined, {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric'
                                        });
                                    },
                                    label: function (item) {
                                        return 'Total established: ' + item.parsed.y;
                                    }
                                }
                            })
                        },
                        scales: {
                            x: {
                                type: 'time',
                                max: DR ? DR.endOfToday() : undefined,
                                time: {
                                    displayFormats: {
                                        year: 'yyyy',
                                        month: 'MMM yyyy',
                                        day: 'd MMM yyyy'
                                    }
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    maxRotation: 45,
                                    autoSkip: true,
                                    maxTicksLimit: 18
                                },
                                grid: { color: T.grid() }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 0
                                },
                                grid: { color: T.grid() }
                            }
                        }
                    }, 'line')
                }, 'line');
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load cumulative established players.';
                }
            });
    }

    function mountRatingDistribution(root) {
        var status = root.querySelector('.server-established-rating-distribution-chart-status');
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        if (status) {
            status.textContent = 'Loading rating distribution…';
        }
        return fetchJson('api/server_established_rating_distribution.php', 'bucket=100&min_games=20')
            .then(function (data) {
                var buckets = data.buckets || [];
                var bucketSize = data.bucket_size || 100;
                var minGames = data.min_games || 20;
                var labels = [];
                var counts = [];
                var meta = [];
                var i;
                if (!buckets.length) {
                    if (status) {
                        status.textContent = 'No established players to chart.';
                    }
                    return;
                }
                for (i = 0; i < buckets.length; i++) {
                    var b = buckets[i];
                    labels.push(String(b.bucket_start));
                    counts.push(b.players);
                    meta.push(b);
                }
                if (status) {
                    status.textContent = '';
                }
                createChart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [Object.assign({
                            label: 'Established players (' + minGames + '+ games)',
                            data: counts
                        }, T.barStroke(T.magenta()))]
                    },
                    options: chartOptions({
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var b = meta[items[0].dataIndex];
                                        return 'Rating ' + b.bucket_start + '\u2013' + b.bucket_end;
                                    },
                                    label: function (item) {
                                        return item.parsed.y + ' players';
                                    },
                                    afterLabel: function (item) {
                                        var total = data.total_players || 0;
                                        if (total < 1) {
                                            return '';
                                        }
                                        var pct = Math.round(1000 * meta[item.dataIndex].players / total) / 10;
                                        return pct + '% of established players';
                                    }
                                }
                            })
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'ELO rating (' + bucketSize + '-point buckets)',
                                    color: T.textMuted()
                                },
                                ticks: { color: T.tickColor(), maxRotation: 45 },
                                grid: { color: T.grid() }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Players',
                                    color: T.textMuted()
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 0
                                },
                                grid: { color: T.grid() }
                            }
                        }
                    }, 'bar')
                }, 'bar');
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load rating distribution.';
                }
            });
    }

    var TOP_ERAS_PALETTE = [
        function () { return T.pitch(); },
        function () { return T.chrome(); },
        function () { return T.amber(); },
        function () { return T.teal(); },
        function () { return T.magenta(); },
        function () { return T.holo(); },
        function () { return '#e57373'; },
        function () { return '#81c784'; },
        function () { return '#fff176'; },
        function () { return '#4fc3f7'; }
    ];

    function topErasColor(index) {
        return TOP_ERAS_PALETTE[index % TOP_ERAS_PALETTE.length]();
    }

    function topErasMonthIndex(months) {
        var idx = {};
        var i;
        for (i = 0; i < months.length; i++) {
            idx[months[i]] = i;
        }
        return idx;
    }

    function topErasRollingMean(values, windowSize) {
        var n = values.length;
        var out = new Array(n);
        var i;
        var k;
        for (i = 0; i < n; i++) {
            var start = Math.max(0, i - windowSize + 1);
            var sum = 0;
            for (k = start; k <= i; k++) {
                sum += values[k];
            }
            out[i] = sum / (i - start + 1);
        }
        return out;
    }

    function topErasFormatMonth(rawX) {
        if (rawX == null) {
            return '';
        }
        var dt = new Date(rawX);
        if (isNaN(dt.getTime())) {
            return '';
        }
        return dt.toLocaleDateString(undefined, { year: 'numeric', month: 'long' });
    }

    function topErasHighlight(chart, activeIdx) {
        if (chart._k2HighlightIdx === activeIdx) {
            return;
        }
        chart._k2HighlightIdx = activeIdx;
        var dsList = chart.data.datasets;
        var d;
        for (d = 0; d < dsList.length; d++) {
            var ds = dsList[d];
            var base = ds._k2BaseColor;
            if (activeIdx === -1 || d === activeIdx) {
                ds.borderColor = base;
                ds.borderWidth = activeIdx === -1 ? 2 : 3;
                ds.backgroundColor = T.fill(base, activeIdx === -1 ? 0.08 : 0.12);
            } else {
                ds.borderColor = T.fill(base, 0.2);
                ds.borderWidth = 2;
                ds.backgroundColor = 'transparent';
            }
        }
        chart.update('none');
    }

    function mountTopActivityEras(root) {
        var status = root.querySelector('.server-top-activity-eras-chart-status');
        var canvas = requireCanvas(root, status);
        var ROLLING_MONTHS = 6;
        if (!canvas) {
            return Promise.resolve();
        }
        if (status) {
            status.textContent = 'Loading…';
        }
        return fetchJson('api/server_top_activity_eras.php')
            .then(function (data) {
                var months = data.months || [];
                var players = data.players || [];
                var monthIndex;
                var monthDates = [];
                var datasets = [];
                var m;
                var p;
                if (!months.length || !players.length) {
                    if (status) {
                        var note = (data.meta && data.meta.note) || '';
                        status.textContent = note
                            ? 'No data available (' + note + ').'
                            : 'No busiest-player data to chart.';
                    }
                    return;
                }
                monthIndex = topErasMonthIndex(months);
                for (m = 0; m < months.length; m++) {
                    monthDates.push(monthToDateMid(months[m]));
                }
                for (p = 0; p < players.length; p++) {
                    var player = players[p];
                    var points = player.points || [];
                    var dataArr;
                    var j;
                    var i;
                    if (!points.length) {
                        continue;
                    }
                    dataArr = new Array(months.length);
                    for (j = 0; j < points.length; j++) {
                        var mi = monthIndex[points[j].month];
                        if (mi !== undefined) {
                            dataArr[mi] = points[j].games;
                        }
                    }
                    for (i = 0; i < months.length; i++) {
                        if (dataArr[i] === undefined) {
                            dataArr[i] = 0;
                        }
                    }
                    dataArr = topErasRollingMean(dataArr, ROLLING_MONTHS);
                    var color = topErasColor(p);
                    datasets.push({
                        label: player.name,
                        data: dataArr,
                        _k2BaseColor: color,
                        borderColor: color,
                        backgroundColor: T.fill(color, 0.08),
                        borderWidth: 2,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        pointHitRadius: 12,
                        fill: false,
                        spanGaps: false,
                        tension: 0.25
                    });
                }
                if (!datasets.length) {
                    if (status) {
                        status.textContent = 'No chartable data.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                var chartInstance = createChart(canvas, {
                    type: 'line',
                    data: {
                        labels: monthDates,
                        datasets: datasets
                    },
                    options: chartOptions({
                        interaction: T.isCoarsePointer()
                            ? { mode: 'nearest', intersect: false, axis: 'x' }
                            : { mode: 'dataset', intersect: false },
                        elements: {
                            point: {
                                radius: 0,
                                hoverRadius: 0,
                                hitRadius: 12
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: T.mergeTooltip({
                                mode: 'nearest',
                                intersect: false,
                                displayColors: false,
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var item = items[0];
                                        var name = (item.dataset && item.dataset.label)
                                            ? item.dataset.label
                                            : '';
                                        var month = topErasFormatMonth(
                                            item.parsed && item.parsed.x
                                        );
                                        if (name && month) {
                                            return [name, month];
                                        }
                                        return name || month || '';
                                    },
                                    label: function () {
                                        return '';
                                    },
                                    footer: function () {
                                        return '';
                                    }
                                }
                            })
                        },
                        onHover: T.isCoarsePointer() ? undefined : function (event, elements) {
                            var activeIdx = elements.length ? elements[0].datasetIndex : -1;
                            topErasHighlight(chartInstance, activeIdx);
                            var target = event && event.native ? event.native.target : canvas;
                            if (target) {
                                target.style.cursor = activeIdx === -1 ? 'default' : 'pointer';
                            }
                        },
                        scales: {
                            x: scaleTimeMonth(),
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Games / month (' + ROLLING_MONTHS + '-mo avg)',
                                    color: T.textMuted(),
                                    font: { size: 11 }
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 1
                                },
                                grid: { color: T.softGrid() }
                            }
                        }
                    }, 'line')
                }, 'line');
                if (!T.isCoarsePointer()) {
                    chartInstance._k2HighlightIdx = -1;
                    canvas.addEventListener('mouseleave', function () {
                        topErasHighlight(chartInstance, -1);
                        canvas.style.cursor = 'default';
                    });
                }
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load busiest players chart.';
                }
            });
    }

    function mountPlayTexture(root) {
        var status = root.querySelector('.server-play-texture-chart-status');
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson('api/server_play_texture.php')
            .then(function (data) {
                var months = data.months || [];
                var gpg = [];
                var drw = [];
                var dd = [];
                var cs = [];
                var i;
                if (!months.length) {
                    if (status) {
                        status.textContent = 'No play texture data to chart.';
                    }
                    return;
                }
                for (i = 0; i < months.length; i++) {
                    var x = monthToDate(months[i].month);
                    if (!x) {
                        continue;
                    }
                    gpg.push({ x: x, y: months[i].goals_per_game });
                    drw.push({ x: x, y: months[i].draw_pct });
                    dd.push({ x: x, y: months[i].dd_per_100 });
                    cs.push({ x: x, y: months[i].cs_per_100 });
                }
                if (status) {
                    status.textContent = '';
                }
                createChart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [
                            Object.assign({
                                label: 'Goals / game',
                                data: gpg,
                                yAxisID: 'yLeft',
                                tension: 0.3,
                                pointRadius: 0,
                                pointHitRadius: 6
                            }, T.lineStroke(T.pitch())),
                            Object.assign({
                                label: 'Draw %',
                                data: drw,
                                yAxisID: 'yRight',
                                tension: 0.3,
                                pointRadius: 0,
                                pointHitRadius: 6
                            }, T.lineStroke(T.amber())),
                            Object.assign({
                                label: 'DD per 100',
                                data: dd,
                                yAxisID: 'yRight',
                                tension: 0.3,
                                pointRadius: 0,
                                pointHitRadius: 6
                            }, T.lineStroke(T.magenta())),
                            Object.assign({
                                label: 'Clean sheet per 100',
                                data: cs,
                                yAxisID: 'yRight',
                                tension: 0.3,
                                pointRadius: 0,
                                pointHitRadius: 6
                            }, T.lineStroke(T.holo()))
                        ]
                    },
                    options: chartOptions({
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) {
                                            return '';
                                        }
                                        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long' });
                                    }
                                }
                            })
                        },
                        scales: {
                            x: scaleTimeMonth(),
                            yLeft: {
                                position: 'left',
                                beginAtZero: true,
                                title: { display: true, text: 'Goals / game', color: T.textMuted() },
                                ticks: { color: T.tickColor() },
                                grid: { color: T.softGrid() }
                            },
                            yRight: {
                                position: 'right',
                                beginAtZero: true,
                                title: { display: true, text: 'Per 100 games / %', color: T.textMuted() },
                                ticks: { color: T.tickColor() },
                                grid: { drawOnChartArea: false }
                            }
                        }
                    }, 'line')
                }, 'line');
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load play texture.';
                }
            });
    }

    /** No chart.resize() until panels mounted + bar entrance (~520ms desktop). */
    var panelsLoadComplete = false;
    var resizeListenerBound = false;
    var BAR_ENTRANCE_BUFFER_MS = 600;

    var PANELS = [
        { id: 'games-day', selector: '.server-games-day-chart', run: mountGamesDay },
        { id: 'games-month', selector: '.server-games-month-chart', run: mountGamesMonth },
        { id: 'games-year', selector: '.server-games-year-chart', run: mountGamesYear },
        { id: 'heatmap', selector: '.server-activity-heatmap', run: mountHeatmap },
        { id: 'active-month', selector: '.server-active-players-month-chart', run: mountActivePlayersMonth },
        { id: 'daily-active', selector: '.server-daily-active-players-chart', run: mountDailyActivePlayers },
        { id: 'matchup', selector: '.server-matchup-breadth-chart', run: mountMatchupBreadth },
        { id: 'established-year', selector: '.server-established-players-year-chart', run: mountEstablishedYear },
        { id: 'cumulative-established', selector: '.server-cumulative-established-month-chart', run: mountCumulativeEstablished },
        { id: 'rating-distribution', selector: '.server-established-rating-distribution-chart', run: mountRatingDistribution },
        { id: 'top-eras', selector: '.server-top-activity-eras-chart', run: mountTopActivityEras },
        { id: 'play-texture', selector: '.server-play-texture-chart', run: mountPlayTexture }
    ];

    function runPanel(spec) {
        var root = document.querySelector(spec.selector);
        if (!root) {
            return Promise.resolve();
        }
        return Promise.resolve(spec.run(root));
    }

    function bindWindowResize() {
        if (resizeListenerBound) {
            return;
        }
        resizeListenerBound = true;
        window.addEventListener('resize', resizeAll);
    }

    function finishPanelLoad() {
        setTimeout(function () {
            panelsLoadComplete = true;
            bindWindowResize();
        }, BAR_ENTRANCE_BUFFER_MS);
    }

    function drain(index) {
        if (index >= PANELS.length) {
            finishPanelLoad();
            return;
        }
        runPanel(PANELS[index]).finally(function () {
            setTimeout(function () {
                drain(index + 1);
            }, GAP_MS);
        });
    }

    var resizeAllTimer;

    function resizeAll() {
        if (!panelsLoadComplete) {
            return;
        }
        if (resizeAllTimer) {
            clearTimeout(resizeAllTimer);
        }
        resizeAllTimer = setTimeout(function () {
            var i;
            for (i = 0; i < PANELS.length; i++) {
                var root = document.querySelector(PANELS[i].selector);
                if (root) {
                    resizeChart(chartCanvas(root));
                }
            }
        }, 120);
    }

    function isActivityChartsPage() {
        return document.body && document.body.classList.contains('k2-activity-charts');
    }

    function boot() {
        if (!isActivityChartsPage()) {
            return;
        }
        panelsLoadComplete = false;
        resizeListenerBound = false;
        installHeatmapTouchDismiss();
        drain(0);
    }

    document.addEventListener('DOMContentLoaded', boot);

    global.K2ActivityChartsV2 = {
        panels: PANELS,
        boot: boot
    };
})(typeof window !== 'undefined' ? window : this);

