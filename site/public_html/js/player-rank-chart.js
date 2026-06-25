/**
 * Amiga profile career rank chart — date X, linear / percentile Y.
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
        p95: [95, 100],
        p90: [90, 100],
        p80: [80, 100],
        p50: [50, 100],
        community: [0, 100]
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

    function rankChartGridColor() {
        if (T && T.rankChartGrid) {
            return T.rankChartGrid();
        }
        return T && T.grid ? T.grid() : 'rgba(255, 255, 255, 0.08)';
    }

    function careerYScale(extra) {
        var base = {
            ticks: { color: T.tickColor() },
            grid: { color: rankChartGridColor() }
        };
        var extraScale = extra || {};
        var scale = Object.assign({}, base, extraScale);
        if (extraScale.ticks) {
            scale.ticks = Object.assign({}, base.ticks, extraScale.ticks);
        }
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

    function percentileCareerPadding(best, worst) {
        var span = Math.max(0, best - worst);
        var pad = Math.round(0.05 * span);
        if (pad < 2) {
            pad = 2;
        }
        if (pad > 10) {
            pad = 10;
        }
        return pad;
    }

    function computeDomain(scale, linearWindow, percentileWindow, meta) {
        var ceiling = meta && meta.ceiling ? meta.ceiling : 1;
        var best = meta && meta.careerBestRank ? meta.careerBestRank : 1;
        var worst = meta && meta.careerWorstRank ? meta.careerWorstRank : ceiling;

        if (scale === 'percentile') {
            if (percentileWindow === 'career') {
                var bestPct = meta && meta.careerBestPercentile != null ? meta.careerBestPercentile : 100;
                var worstPct = meta && meta.careerWorstPercentile != null ? meta.careerWorstPercentile : 0;
                var pctPad = percentileCareerPadding(bestPct, worstPct);
                return {
                    kind: 'percentile',
                    min: Math.max(0, worstPct - pctPad),
                    max: Math.min(100, bestPct + pctPad)
                };
            }
            var pr = PERCENTILE_RANGES[percentileWindow] || PERCENTILE_RANGES.community;
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

    function plotPointStatus(scale, domain, point) {
        var rank = point.eloRank;
        var pct = point.percentile;

        if (scale === 'linear') {
            if (domain.band && rank > domain.band) {
                return { inRange: false, clipY: domain.max };
            }
            if (rank < domain.min) {
                return { inRange: false, clipY: domain.min };
            }
            if (rank > domain.max) {
                return { inRange: false, clipY: domain.max };
            }
            return { inRange: true, y: rank };
        }
        if (pct < domain.min) {
            return { inRange: false, clipY: domain.min };
        }
        if (pct > domain.max) {
            return { inRange: false, clipY: domain.max };
        }
        return { inRange: true, y: pct };
    }

    function pushSeriesPoint(out, x, y, clipped, raw) {
        out.push({
            x: x,
            y: y,
            clipped: clipped,
            raw: raw
        });
    }

    function buildSeries(points, scale, domain) {
        var out = [];
        var outOfRangeStreak = false;
        var lastClipY = null;

        for (var i = 0; i < points.length; i++) {
            var pt = points[i];
            var x = parseEventDate(pt.eventDate);
            if (x === null) {
                continue;
            }

            var status = plotPointStatus(scale, domain, pt);

            if (status.inRange) {
                if (outOfRangeStreak && lastClipY != null) {
                    pushSeriesPoint(out, x, lastClipY, true, pt);
                }
                outOfRangeStreak = false;
                lastClipY = null;
                pushSeriesPoint(out, x, status.y, false, pt);
                continue;
            }

            if (status.clipY == null) {
                continue;
            }

            if (!outOfRangeStreak) {
                pushSeriesPoint(out, x, status.clipY, true, pt);
                outOfRangeStreak = true;
                lastClipY = status.clipY;
                continue;
            }

            pushSeriesPoint(out, x, null, true, pt);
        }

        return out;
    }

    function hasPlottedPoints(series) {
        for (var i = 0; i < series.length; i++) {
            if (!series[i].clipped && series[i].y != null && !isNaN(series[i].y)) {
                return true;
            }
        }
        return false;
    }

    function yAxisConfig(scale, domain) {
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

    function peakPointFromHistory(points, scale) {
        if (!points.length) {
            return null;
        }
        var bestIdx = 0;
        var bestVal = scale === 'percentile' ? points[0].percentile : points[0].eloRank;
        for (var i = 1; i < points.length; i++) {
            var v = scale === 'percentile' ? points[i].percentile : points[i].eloRank;
            if (scale === 'percentile') {
                if (v > bestVal) {
                    bestVal = v;
                    bestIdx = i;
                }
            } else if (v < bestVal) {
                bestVal = v;
                bestIdx = i;
            }
        }
        return {
            point: points[bestIdx],
            display: scale === 'percentile' ? bestVal + '%' : '#' + bestVal
        };
    }

    function renderPeakSummary(summary, points, scale) {
        if (!summary) {
            return;
        }
        var peak = peakPointFromHistory(points, scale);
        if (!peak) {
            summary.hidden = true;
            return;
        }
        var whenDate = parseEventDate(peak.point.eventDate);
        if (!whenDate) {
            summary.hidden = true;
            return;
        }
        summary.innerHTML = 'Peak: <span class="pm3-chart-peak-value">' + peak.display + '</span>'
            + ' <span class="pm3d-chart__summary-note">on ' + formatTooltipDate(whenDate) + '.</span>';
        summary.hidden = false;
    }

    function buildChart(canvas, points, meta, timelineStart, state) {
        var domain = computeDomain(state.scale, state.linearWindow, state.percentileWindow, meta);
        var series = buildSeries(points, state.scale, domain);
        var stepped = true;
        var chartData = hasPlottedPoints(series) ? series : [];
        var timeRange = rankChartTimeRange(points, timelineStart, meta && meta.cutoffActive);
        var dataset = Object.assign({
            label: 'Elo rank',
            data: chartData,
            spanGaps: false,
            stepped: stepped,
            tension: stepped ? 0 : 0.1,
            pointRadius: 0,
            pointHoverRadius: 4
        }, T.lineStroke(T.amber(), 0.15));

        return {
            chart: createChart(canvas, {
                type: 'line',
                data: { datasets: [dataset] },
                options: withCareerPlotGutter({
                    interaction: { mode: 'nearest', axis: 'x', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: T.mergeTooltip({
                            filter: function (item) {
                                return item.raw && !item.raw.clipped && item.raw.y != null;
                            },
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
                            grid: { color: rankChartGridColor() }
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
        var toolbar = root.querySelector('.player-rank-chart__toolbar');
        if (toolbar) {
            toolbar.setAttribute('data-range-mode', state.scale);
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
        var summary = root.querySelector('.player-rank-peak-summary');
        var frame = root.querySelector('.k2-chart-frame');
        if (!canvas || !state.data) {
            return;
        }

        destroyChart(state);

        var points = state.data.points || [];
        var meta = state.data.meta || {};
        if (!points.length) {
            if (summary) {
                summary.hidden = true;
            }
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

        renderPeakSummary(summary, points, state.scale);

        var built = buildChart(canvas, points, meta, state.data.timelineStart, state);
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
            percentileWindow: 'career',
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