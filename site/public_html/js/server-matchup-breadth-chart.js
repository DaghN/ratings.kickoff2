/**
 * Unique matchups per month (Chart.js bar).
 * Shows distinct opponent pairings to visualise social breadth.
 * Expects api/server_matchup_breadth.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var API_PATH = 'api/server_matchup_breadth.php';

    function monthToDate(s) {
        if (!s || s.length < 7) return null;
        var d = new Date(s + '-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-matchup-breadth-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) status.textContent = 'Chart library failed to load.';
            return;
        }

        fetch(API_PATH + '?realm=online', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('bad_status');
                return r.json();
            })
            .then(function (data) {
                var months = data.months || [];
                if (!months.length) {
                    if (status) status.textContent = 'No matchup data to chart.';
                    return;
                }

                var chartData = [];
                for (var i = 0; i < months.length; i++) {
                    var x = monthToDate(months[i].month);
                    if (!x) continue;
                    chartData.push({ x: x, y: months[i].unique_pairs });
                }

                if (status) status.textContent = '';

                T.createChart(canvas, {
                    type: 'bar',
                    data: {
                        datasets: [Object.assign({
                            label: 'Unique matchups',
                            data: chartData
                        }, T.barSolid(T.holo()))]
                    },
                    options: T.mergeChartOptions({
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) return '';
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) return '';
                                        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long' });
                                    },
                                    label: function (item) {
                                        var v = item.parsed.y || 0;
                                        return v + ' distinct ' + (v === 1 ? 'pairing' : 'pairings');
                                    }
                                }
                            }),
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: { unit: 'month', round: 'month', displayFormats: { month: 'MMM yyyy' } },
                                ticks: { color: T.tickColor(), maxRotation: 45, autoSkip: true, maxTicksLimit: 24 },
                                grid: { color: T.softGrid() }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { color: T.tickColor(), precision: 0 },
                                grid: { color: T.softGrid() }
                            }
                        }
                    }, 'bar'),
                });
            })
            .catch(function () {
                if (status) status.textContent = 'Could not load matchup breadth.';
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-matchup-breadth-chart');
        for (var i = 0; i < roots.length; i++) {
            (function (root) {
                T.whenBlockVisible(root, function () {
                    initRoot(root);
                }, 6);
            })(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
