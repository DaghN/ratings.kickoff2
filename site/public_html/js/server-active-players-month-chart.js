/**
 * Active players per calendar month (Chart.js bar + time scale).
 * Active = distinct players with at least one game that month.
 * Expects api/server_active_players_by_month.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = 'api/server_active_players_by_month.php';

    function monthToDate(monthStr) {
        if (!monthStr || monthStr.length < 7) {
            return null;
        }
        var d = new Date(monthStr + '-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-active-players-month-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading active players per month…';
        }

        var url = API_PATH + '?realm=online';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var months = data.months || [];
                if (!months.length) {
                    if (status) {
                        status.textContent = 'No active player data to chart.';
                    }
                    return;
                }

                var chartData = [];
                for (var i = 0; i < months.length; i++) {
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

                T.createChart(canvas, {
                    type: 'bar',
                    data: {
                        datasets: [Object.assign({
                            label: 'Active players',
                            data: chartData
                        }, T.barSolid(T.chrome()))]
                    },
                    options: T.mergeChartOptions({
                        responsive: true,
                        maintainAspectRatio: true,
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
                                            month: 'long'
                                        });
                                    }
                                }
                            }),
                        },
                        scales: {
                            x: {
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
                                    maxTicksLimit: 24
                                },
                                grid: { color: T.softGrid() }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 0
                                },
                                grid: { color: T.softGrid() }
                            }
                        }
                    }, 'bar'),
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load active players per month.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-active-players-month-chart');
        for (var i = 0; i < roots.length; i++) {
            (function (root) {
                T.whenBlockVisible(root, function () {
                    initRoot(root);
                }, 4);
            })(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
