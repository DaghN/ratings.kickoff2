/**
 * Amiga profile career rank chart — date X, linear / log / percentile Y.
 * Expects K2PlayerRankHistory, K2ChartTheme, K2ChartDateRange, Chart.js + date adapter.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;
    var History = window.K2PlayerRankHistory;

    var BAND_LABELS = {
        top20: 20,
        top50: 50,
        top100: 100
    };

    var PERCENTILE_RANGES = {
        full: [0, 100],
        p50: [50, 100],
        p90: [90, 100],
        p95: [95, 100]
    };

    function parseEventDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var normalized = String(dateStr).trim().replace(' ', 'T');
        var d = new Date(normalized);
        return isNaN(d.getTime()) ? null : d;
    }

    function chartOptions(extra, chartKind) {
        if (T && T.activityChartOptions) {
            return T.activityChartOptions(Object.assign({ maintainAspectRatio: false }, extra || {}), {
                chartKind: chartKind || 'line'
            });
        }
        return Object.assign({ responsive: true, maintainAspectRatio: false }, extra || {});
    }

    function withCareerPlotGutter(extra, chartKind) {
        var gutter = T && T.careerChartGutterOptions ? T.careerChartGutterOptions() : {};
        return chartOptions(Object.assign({}, gutter, extra || {}), chartKind || 'line');
    }

    function careerYScale(extra) {
        var scale = Object.assign({
            ticks: { color: T.tickColor() },
            grid: { color: T.softGrid ? T.softGrid() : T.grid() }
        }, extra || {});
        return T && T.careerChartYAxisOptions ? T.careerChartYAxisOptions(scale) : scale;
    }

    function createChart(canvas, config) {
        if (T && T.createActivityChart) {
            return T.createActivityChart(canvas, config, 'line');
        }
        return new Chart(canvas, config);
    }

    function careerPadding(best, worst) {
        var span = Math.max(0, worst - best);
        var pad = Math.round(0.05 * span);
        if (pad < 5) {
            pad = 5;
        }
        if (pad > 20) {
            pad = 20;
        }
        return pad;
    }

    function rankChartTimeRange(points, timelineStart, cutoffActive) {
        var range;
        if (DR && DR.careerTimeRangeFromStart) {
            range = DR.careerTimeRangeFromStart(timelineStart);
        } else {
            range = {
                xMin: undefined,
                xMax: DR && DR.endOfToday ? DR.endOfToday() : undefined
            };
        }
        if (cutoffActive && points.length) {
            var last = parseEventDate(points[points.length - 1].eventDate);
            if (last) {
                range.xMax = last;
            }
        }
        return range;
    }

    function computeDomain(scale, linearWindow, percentileWindow, meta) {
        var ceiling = meta && meta.ceiling ? meta.ceiling : 1;
        var best = meta && meta.careerBestRank ? meta.careerBestRank : 1;
        var worst = meta && meta.careerWorstRank ? meta.careerWorstRank : ceiling;

        if (scale === 'log') {
            return { kind: 'log', min: 1, max: ceiling, ceiling: ceiling };
        }
        if (scale === 'percentile') {
            var pr = PERCENTILE_RANGES[percentileWindow] || PERCENTILE_RANGES.full;
            return { kind: 'percentile', min: pr[0], max: pr[1] };
        }

        if (linearWindow === 'community') {
            return { kind: 'linear', min: 1, max: ceiling, band: null };
        }
        if (linearWindow === 'career') {
            var pad = careerPadding(best, worst);
            return {
                kind: 'linear',
                min: Math.max(1, best - pad),
                max: Math.min(ceiling, worst + pad),
                band: null
            };
        }
        if (BAND_LABELS[linearWindow]) {
            return {
                kind: 'linear',
                min: 1,
                max: BAND_LABELS[linearWindow],
                band: BAND_LABELS[linearWindow]
            };
        }
        return { kind: 'linear', min: 1, max: ceiling, band: null };
    }

    function plotY(scale, domain, point) {
        var rank = point.eloRank;
        var pct = point.percentile;

        if (scale === 'linear') {
            if (domain.band && rank > domain.band) {
                return null;
            }
            return rank;
        }
        if (scale === 'log') {
            return rank > 0 ? Math.log(rank) : null;
        }
        if (pct < domain.min || pct > domain.max) {
            return null;
        }
        return pct;
    }

    function buildSeries(points, scale, domain) {
        var out = [];
        for (var i = 0; i < points.length; i++) {
            var pt = points[i];
            var x = parseEventDate(pt.eventDate);
            if (x === null) {
                continue;
            }
            out.push({
                x: x,
                y: plotY(scale, domain, pt),
                raw: pt
            });
        }
        return out;
    }

    function hasPlottedPoints(series) {
        for (var i = 0; i < series.length; i++) {
            if (series[i].y != null && !isNaN(series[i].y)) {
                return true;
            }
        }
        return false;
    }

    function emptyBandMessage(scale, linearWindow) {
        if (scale !== 'linear' || !BAND_LABELS[linearWindow]) {
            return 'No rank data in this view.';
        }
        return 'Not in top ' + BAND_LABELS[linearWindow] + ' at any recorded event.';
    }

    function logTickRanks(maxRank) {
        var candidates = [1, 2, 5, 10, 20, 50, 100, 200, 500, 1000];
        var ticks = [];
        for (var i = 0; i < candidates.length; i++) {
            if (candidates[i] <= maxRank) {
                ticks.push(candidates[i]);
            }
        }
        if (ticks.length === 0 || ticks[ticks.length - 1] !== maxRank) {
            ticks.push(maxRank);
        }
        return ticks;
    }

    function yAxisConfig(scale, domain) {
        if (scale === 'log') {
            var maxRank = domain.max;
            var logTicks = logTickRanks(maxRank);
            return careerYScale({
                reverse: true,
                min: 0,
                max: Math.log(maxRank),
                ticks: {
                    callback: function () {
                        return '';
                    }
                },
                afterBuildTicks: function (axis) {
                    axis.ticks = logTicks.map(function (rank) {
                        return { value: Math.log(rank), label: String(rank) };
                    });
                }
            });
        }
        if (scale === 'percentile') {
            return careerYScale({
                reverse: false,
                min: domain.min,
                max: domain.max,
                ticks: {
                    callback: function (v) {
                        return v + '%';
                    }
                }
            });
        }
        return careerYScale({
            reverse: true,
            min: domain.min,
            max: domain.max,
            ticks: {
                stepSize: undefined,
                callback: function (v) {
                    return '#' + Math.round(v);
                }
            }
        });
    }

    function yAxisTitle(scale) {
        if (scale === 'percentile') {
            return 'Percentile';
        }
        return 'Rank';
    }

    function formatTooltipDate(d) {
        return d.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function buildChart(canvas, points, meta, timelineStart, state) {
        var domain = computeDomain(state.scale, state.linearWindow, state.percentileWindow, meta);
        var series = buildSeries(points, state.scale, domain);
        var stepped = state.line === 'stepped';

        if (!hasPlottedPoints(series)) {
            return { empty: true, message: emptyBandMessage(state.scale, state.linearWindow) };
        }

        var timeRange = rankChartTimeRange(points, timelineStart, meta && meta.cutoffActive);
        var dataset = Object.assign({
            label: 'Elo rank',
            data: series,
            spanGaps: false,
            stepped: stepped,
            tension: stepped ? 0 : 0.1,
            pointRadius: 0,
            pointHoverRadius: 4
        }, T.lineStroke(T.amber(), 0.15));

        return {
            empty: false,
            chart: createChart(canvas, {
                type: 'line',
                data: { datasets: [dataset] },
                options: withCareerPlotGutter({
                    interaction: { mode: 'nearest', axis: 'x', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: T.mergeTooltip({
                            callbacks: {
                                title: function (items) {
                                    if (!items.length || !items[0].raw || !items[0].raw.raw) {
                                        return '';
                                    }
                                    var raw = items[0].raw.raw;
                                    var d = parseEventDate(raw.eventDate);
                                    var title = d ? formatTooltipDate(d) : String(raw.eventDate || '');
                                    if (raw.tournamentName) {
                                        title += ' — ' + raw.tournamentName;
                                    }
                                    return title;
                                },
                                label: function (item) {
                                    var raw = item.raw && item.raw.raw;
                                    if (!raw) {
                                        return '';
                                    }
                                    return '#' + raw.eloRank + ' of ' + raw.ladderSize
                                        + ' (' + raw.percentile + '%)';
                                }
                            }
                        })
                    },
                    scales: {
                        x: {
                            type: 'time',
                            min: timeRange.xMin,
                            max: timeRange.xMax,
                            offset: false,
                            time: {
                                displayFormats: {
                                    year: 'yyyy',
                                    month: 'MMM yyyy',
                                    day: 'MMM d, yyyy'
                                }
                            },
                            ticks: {
                                color: T.tickColor(),
                                maxRotation: 45,
                                minRotation: 0,
                                autoSkip: true,
                                maxTicksLimit: 12
                            },
                            grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                        },
                        y: Object.assign({ title: { display: false } }, yAxisConfig(state.scale, domain))
                    }
                }, 'line')
            })
        };
    }

    function setToggleGroup(root, selector, attr, value) {
        var buttons = root.querySelectorAll(selector + ' .pm3d-rating-toggle__btn');
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var active = btn.getAttribute(attr) === value;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-selected', active ? 'true' : 'false');
        }
    }

    function syncToolbarVisibility(root, state) {
        var linearWindow = root.querySelector('.player-rank-chart__window');
        var percentileWindow = root.querySelector('.player-rank-chart__percentile-window');
        if (linearWindow) {
            linearWindow.hidden = state.scale !== 'linear';
        }
        if (percentileWindow) {
            percentileWindow.hidden = state.scale !== 'percentile';
        }
    }

    function destroyChart(state) {
        if (state.chart && typeof state.chart.destroy === 'function') {
            state.chart.destroy();
        }
        state.chart = null;
    }

    function renderChart(root, state) {
        var canvas = root.querySelector('.player-rank-canvas');
        var status = root.querySelector('.player-rank-chart-status');
        var frame = root.querySelector('.k2-chart-frame');
        if (!canvas || !state.data) {
            return;
        }

        destroyChart(state);

        var points = state.data.points || [];
        var meta = state.data.meta || {};
        if (!points.length) {
            if (status) {
                status.textContent = meta.cutoffActive
                    ? 'Not on the ladder at this date.'
                    : 'No rank history to chart.';
            }
            if (frame) {
                frame.hidden = true;
            }
            return;
        }

        var built = buildChart(canvas, points, meta, state.data.timelineStart, state);
        if (built.empty) {
            if (status) {
                status.textContent = built.message;
            }
            if (frame) {
                frame.hidden = true;
            }
            return;
        }

        state.chart = built.chart;
        if (status) {
            status.textContent = '';
        }
        if (frame) {
            frame.hidden = false;
        }
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId || typeof Chart === 'undefined') {
            return;
        }
        if (!History || !History.load) {
            var statusEl = root.querySelector('.player-rank-chart-status');
            if (statusEl) {
                statusEl.textContent = 'Rank history loader failed to load.';
            }
            return;
        }

        var state = {
            scale: 'linear',
            linearWindow: 'career',
            percentileWindow: 'full',
            line: 'connected',
            data: null,
            chart: null
        };

        syncToolbarVisibility(root, state);

        function bindToggle(selector, attr, key, onChange) {
            var group = root.querySelector(selector);
            if (!group) {
                return;
            }
            group.addEventListener('click', function (evt) {
                var btn = evt.target.closest('.pm3d-rating-toggle__btn');
                if (!btn || !group.contains(btn)) {
                    return;
                }
                var val = btn.getAttribute(attr);
                if (!val || val === state[key]) {
                    return;
                }
                state[key] = val;
                setToggleGroup(root, selector, attr, val);
                if (onChange) {
                    onChange();
                }
                renderChart(root, state);
            });
        }

        bindToggle('.player-rank-chart__scale', 'data-scale', 'scale', function () {
            syncToolbarVisibility(root, state);
        });
        bindToggle('.player-rank-chart__window', 'data-window', 'linearWindow');
        bindToggle('.player-rank-chart__percentile-window', 'data-pwindow', 'percentileWindow');
        bindToggle('.player-rank-chart__line', 'data-line', 'line');

        var asParam = root.getAttribute('data-as') || '';
        if (!asParam && typeof URLSearchParams !== 'undefined') {
            asParam = new URLSearchParams(window.location.search).get('as') || '';
        }
        var loadOpts = asParam ? { as: asParam } : {};
        var realm = root.getAttribute('data-realm') || 'amiga';

        History.load(playerId, realm, loadOpts)
            .then(function (data) {
                state.data = data;
                renderChart(root, state);
            })
            .catch(function () {
                var status = root.querySelector('.player-rank-chart-status');
                if (status) {
                    status.textContent = 'Could not load rank history.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.player-rank-chart');
        for (var i = 0; i < roots.length; i++) {
            initRoot(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());