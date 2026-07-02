/**
 * Amiga Activity charts — single module for the /amiga/activity/ wings.
 * Panels register in PANELS (slices 1+); drain() mounts them sequentially.
 * Time travel: every API fetch carries the page's `as=` cutoff — never
 * with-player params. See docs/amiga-activity-charts-policy.md.
 */
(function (global) {
    'use strict';

    var T = global.K2ChartTheme;
    var GAP_MS = 100;
    var FRAME_OPTS = { maintainAspectRatio: false };
    var ERRORS = [];

    function noteError(where, err) {
        ERRORS.push(where + ': ' + String((err && err.message) || err));
        if (typeof console !== 'undefined' && console.error) {
            console.error('amiga-activity-charts ' + where, err);
        }
    }

    function currentAsParam() {
        try {
            return new URLSearchParams(global.location.search).get('as') || '';
        } catch (e) {
            return '';
        }
    }

    /** Fetch an Activity API as JSON, carrying the active `as=` cutoff. */
    function fetchJson(apiPath, query) {
        var q = query || '';
        if (q && q.charAt(0) !== '?') {
            q = '?' + q;
        }
        var as = currentAsParam();
        if (as && q.indexOf('as=') === -1) {
            q += (q ? '&' : '?') + 'as=' + encodeURIComponent(as);
        }
        return fetch(apiPath + q, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                throw new Error('bad_status');
            }
            return r.json();
        });
    }

    function yearToDate(year) {
        var y = parseInt(year, 10);
        if (!y || y < 1000) {
            return null;
        }
        var d = new Date(y + '-01-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
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

    function formatCount(value) {
        if (value == null) {
            return '';
        }
        return Number(value).toLocaleString();
    }

    function parseEventDate(dateStr) {
        if (!dateStr || dateStr.length < 10) {
            return null;
        }
        var d = new Date(dateStr.substring(0, 10) + 'T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function formatEventDate(d) {
        if (!d || isNaN(d.getTime())) {
            return '';
        }
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function panelStatus(root) {
        return root.querySelector('.k2-chart-panel__status');
    }

    function tone(name) {
        return T[name] ? T[name]() : T.pitch();
    }

    /** Tooltip footer marking the honestly-partial cutoff year under time travel. */
    function partialYearFooter(cutoff, labels) {
        return function (items) {
            if (!cutoff || !cutoff.partial_year || !items.length) {
                return '';
            }
            if (labels[items[0].dataIndex] !== String(cutoff.partial_year)) {
                return '';
            }
            var d = parseEventDate(cutoff.event_date);
            return 'Partial year — through ' + (d ? formatEventDate(d) : cutoff.label);
        };
    }

    function scaleXCategory() {
        return {
            ticks: {
                color: T.tickColor(),
                maxRotation: 45,
                autoSkip: true
            },
            grid: { color: T.grid() }
        };
    }

    function scaleYCountFormatted() {
        return {
            beginAtZero: true,
            ticks: {
                color: T.tickColor(),
                precision: 0,
                callback: function (value) {
                    return formatCount(value);
                }
            },
            grid: { color: T.grid() }
        };
    }

    function renderYearBar(canvas, labels, values, spec, cutoff) {
        createChart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [Object.assign({
                    label: spec.label,
                    data: values
                }, T.barStroke(tone(spec.tone)))]
            },
            options: chartOptions({
                plugins: {
                    legend: { labels: { color: T.textMuted() } },
                    tooltip: T.mergeTooltip({
                        callbacks: {
                            label: function (item) {
                                if (item.parsed.y == null) {
                                    return 'No data';
                                }
                                if (spec.decimals != null) {
                                    return item.parsed.y.toFixed(spec.decimals) + ' ' + spec.noun;
                                }
                                return formatCount(item.parsed.y) + ' ' + spec.noun;
                            },
                            footer: partialYearFooter(cutoff, labels)
                        }
                    })
                },
                scales: {
                    x: scaleXCategory(),
                    y: spec.decimals != null ? {
                        beginAtZero: true,
                        ticks: { color: T.tickColor() },
                        grid: { color: T.grid() }
                    } : scaleYCountFormatted()
                }
            }, 'bar')
        }, 'bar');
    }

    /** L1 year bars from the year_facts API (realm slice). */
    function mountYearFacts(root, spec) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson('/api/amiga_community_year_facts.php', 'metric=' + encodeURIComponent(spec.metric))
            .then(function (data) {
                var years = data.years || [];
                var series = (data.series && data.series[0] && data.series[0].values) || [];
                if (!years.length || !series.length) {
                    if (status) {
                        status.textContent = 'No data to chart.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                renderYearBar(canvas, years.map(String), series, spec, data.cutoff);
            })
            .catch(function (err) {
                noteError(spec.metric || spec.rate || 'panel', err);
                if (status) {
                    status.textContent = 'Could not load this chart.';
                }
            });
    }

    /** L3 derived rate bars from the year_rates API. */
    function mountYearRate(root, spec) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson('/api/amiga_community_year_rates.php', 'rate=' + encodeURIComponent(spec.rate))
            .then(function (data) {
                var years = data.years || [];
                var values = data.values || [];
                if (!years.length || !values.length) {
                    if (status) {
                        status.textContent = 'No data to chart.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                renderYearBar(canvas, years.map(String), values, spec, data.cutoff);
            })
            .catch(function (err) {
                noteError(spec.metric || spec.rate || 'panel', err);
                if (status) {
                    status.textContent = 'Could not load this chart.';
                }
            });
    }

    /** L2 cumulative event-timeline lines: every point is a real tournament. */
    function mountCumulative(root, spec) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson('/api/amiga_community_snapshot_series.php', 'metric=' + encodeURIComponent(spec.metric))
            .then(function (data) {
                var points = data.points || [];
                var chartData = [];
                var meta = [];
                var i;
                for (i = 0; i < points.length; i++) {
                    var x = parseEventDate(points[i].date);
                    if (x === null || points[i].value == null) {
                        continue;
                    }
                    chartData.push({ x: x, y: points[i].value });
                    meta.push(points[i]);
                }
                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No data to chart.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                var coarse = T.isCoarsePointer();
                var chartInstance = createChart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [Object.assign({
                            label: spec.label,
                            data: chartData,
                            fill: true,
                            stepped: true,
                            pointRadius: 0,
                            pointHitRadius: 8
                        }, T.lineStroke(tone(spec.tone)))]
                    },
                    options: chartOptions({
                        interaction: { mode: 'nearest', intersect: false, axis: 'x' },
                        onClick: coarse ? undefined : function (event, elements) {
                            if (!elements.length) {
                                return;
                            }
                            var point = meta[elements[0].index];
                            if (!point || !point.t) {
                                return;
                            }
                            var url = '/amiga/tournament/event-stats.php?id=' + point.t;
                            var TT = global.K2AmigaTimeTravelUrl;
                            if (TT && TT.navigationQuerySuffix) {
                                url += TT.navigationQuerySuffix();
                            }
                            global.location.href = url;
                        },
                        onHover: coarse ? undefined : function (event, elements) {
                            var target = event && event.native ? event.native.target : canvas;
                            if (target) {
                                target.style.cursor = elements.length ? 'pointer' : 'default';
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
                                        var point = meta[items[0].dataIndex];
                                        return point ? point.name : '';
                                    },
                                    label: function (item) {
                                        var point = meta[item.dataIndex];
                                        var lines = [];
                                        if (point) {
                                            lines.push(formatEventDate(parseEventDate(point.date)));
                                        }
                                        lines.push('Total: ' + formatCount(item.parsed.y) + ' ' + spec.noun);
                                        return lines;
                                    }
                                }
                            })
                        },
                        scales: {
                            x: {
                                type: 'time',
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
                                    maxTicksLimit: 14
                                },
                                grid: { color: T.grid() }
                            },
                            y: scaleYCountFormatted()
                        }
                    }, 'line')
                }, 'line');
                if (!coarse && chartInstance) {
                    canvas.addEventListener('mouseleave', function () {
                        canvas.style.cursor = 'default';
                    });
                }
            })
            .catch(function (err) {
                noteError(spec.metric || spec.rate || 'panel', err);
                if (status) {
                    status.textContent = 'Could not load this chart.';
                }
            });
    }

    /* --- Panel registry + sequential drain (activity-charts-v2 pattern) --- */

    var PANELS = [];
    var panelsLoadComplete = false;
    var resizeListenerBound = false;
    var BAR_ENTRANCE_BUFFER_MS = 600;

    /** spec: { id, selector, run(root) -> Promise } */
    function registerPanel(spec) {
        PANELS.push(spec);
    }

    function runPanel(spec) {
        var root = document.querySelector(spec.selector);
        if (!root) {
            return Promise.resolve();
        }
        return Promise.resolve(spec.run(root));
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

    /* --- Growth wing (slice 1) --- */

    registerPanel({
        id: 'games-year',
        selector: '.amiga-act-games-year-chart',
        run: function (root) {
            return mountYearFacts(root, { metric: 'games', tone: 'pitch', label: 'Games', noun: 'rated games' });
        }
    });
    registerPanel({
        id: 'games-cumulative',
        selector: '.amiga-act-games-cumulative-chart',
        run: function (root) {
            return mountCumulative(root, { metric: 'GamesPlayed', tone: 'pitch', label: 'Cumulative games', noun: 'games' });
        }
    });
    registerPanel({
        id: 'tournaments-year',
        selector: '.amiga-act-tournaments-year-chart',
        run: function (root) {
            return mountYearFacts(root, { metric: 'tournaments', tone: 'chrome', label: 'Tournaments', noun: 'tournaments' });
        }
    });
    registerPanel({
        id: 'tournaments-cumulative',
        selector: '.amiga-act-tournaments-cumulative-chart',
        run: function (root) {
            return mountCumulative(root, { metric: 'TournamentsFinalized', tone: 'chrome', label: 'Cumulative tournaments', noun: 'tournaments' });
        }
    });
    registerPanel({
        id: 'goals-year',
        selector: '.amiga-act-goals-year-chart',
        run: function (root) {
            return mountYearFacts(root, { metric: 'goals', tone: 'amber', label: 'Goals', noun: 'goals' });
        }
    });
    registerPanel({
        id: 'goals-cumulative',
        selector: '.amiga-act-goals-cumulative-chart',
        run: function (root) {
            return mountCumulative(root, { metric: 'GoalsScored', tone: 'amber', label: 'Cumulative goals', noun: 'goals' });
        }
    });
    registerPanel({
        id: 'games-per-tournament-year',
        selector: '.amiga-act-games-per-tournament-year-chart',
        run: function (root) {
            return mountYearRate(root, {
                rate: 'games_per_tournament',
                tone: 'teal',
                label: 'Avg games per tournament',
                noun: 'games per tournament',
                decimals: 1
            });
        }
    });

    /* --- People wing (slice 2) --- */

    registerPanel({
        id: 'active-players-year',
        selector: '.amiga-act-active-players-year-chart',
        run: function (root) {
            return mountYearFacts(root, { metric: 'active_players', tone: 'chrome', label: 'Active players', noun: 'players' });
        }
    });
    registerPanel({
        id: 'debuts-year',
        selector: '.amiga-act-debuts-year-chart',
        run: function (root) {
            return mountYearFacts(root, { metric: 'player_debuts', tone: 'holo', label: 'New players', noun: 'debuts' });
        }
    });
    registerPanel({
        id: 'players-cumulative',
        selector: '.amiga-act-players-cumulative-chart',
        run: function (root) {
            return mountCumulative(root, { metric: 'NumberOfPlayers', tone: 'holo', label: 'Cumulative players', noun: 'players' });
        }
    });
    registerPanel({
        id: 'pairs-year',
        selector: '.amiga-act-pairs-year-chart',
        run: function (root) {
            return mountYearFacts(root, { metric: 'distinct_pairs', tone: 'teal', label: 'Distinct pairs', noun: 'pairings' });
        }
    });
    registerPanel({
        id: 'pairs-cumulative',
        selector: '.amiga-act-pairs-cumulative-chart',
        run: function (root) {
            return mountCumulative(root, { metric: 'DistinctOpponentPairs', tone: 'teal', label: 'Cumulative distinct pairs', noun: 'pairings' });
        }
    });

    function isAmigaActivityChartsPage() {
        return document.body && document.body.classList.contains('k2-amiga-activity-charts');
    }

    /* k2OnPageReady can invoke the callback twice on one load (shim quirk) — guard. */
    var booted = false;

    function boot() {
        if (booted || !isAmigaActivityChartsPage()) {
            return;
        }
        booted = true;
        drain(0);
    }

    (window.k2OnPageReady || function (fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    })(boot);

    global.K2AmigaActivityCharts = {
        panels: PANELS,
        errors: ERRORS,
        registerPanel: registerPanel,
        fetchJson: fetchJson,
        yearToDate: yearToDate,
        chartOptions: chartOptions,
        createChart: createChart,
        requireCanvas: requireCanvas,
        scaleYCount: scaleYCount,
        boot: boot
    };
})(typeof window !== 'undefined' ? window : this);