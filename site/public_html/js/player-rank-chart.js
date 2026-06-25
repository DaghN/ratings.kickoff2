/**
 * Amiga profile career rank chart — date X, linear / percentile Y.
 * Expects K2PlayerRankChartCore, K2PlayerRankHistory, K2ChartTheme, Chart.js + date adapter.
 */
(function () {
    'use strict';

    var Core = window.K2PlayerRankChartCore;
    var T = window.K2ChartTheme;
    var History = window.K2PlayerRankHistory;

    if (!Core) {
        return;
    }

    function buildChart(canvas, points, meta, timelineStart, state) {
        var domain = Core.computeDomain(state.scale, state.linearWindow, state.percentileWindow, meta);
        var series = Core.buildSeries(points, state.scale, domain);
        var chartData = Core.hasPlottedPoints(series) ? series : [];
        var timeRange = Core.rankChartTimeRange(points, timelineStart, meta && meta.cutoffActive);
        var dataset = Object.assign({
            label: 'Elo rank',
            data: chartData,
            spanGaps: false,
            stepped: true,
            tension: 0,
            pointRadius: 0,
            pointHoverRadius: 4
        }, T.lineStroke(T.amber(), 0.15));

        return Core.createChart(canvas, {
            type: 'line',
            data: { datasets: [dataset] },
            options: Core.withCareerPlotGutter({
                interaction: { mode: 'nearest', axis: 'x', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: T.mergeTooltip(Core.buildRankTooltipCallbacks())
                },
                scales: {
                    x: Core.buildTimeXScale(timeRange),
                    y: Object.assign({ title: { display: false } }, Core.yAxisConfig(state.scale, domain))
                }
            }, 'line')
        });
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

        Core.renderPeakSummary(summary, points, state.scale, {
            peak: state.data.peak
        });

        state.chart = buildChart(canvas, points, meta, state.data.timelineStart, state);
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
