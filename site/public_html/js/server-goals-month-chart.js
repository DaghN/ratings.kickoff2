/**
 * Server-wide goals per calendar month (Chart.js bar + time scale).
 * Expects api/server_goals_by_month.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = 'api/server_goals_by_month.php';

    function monthToDate(monthStr) {
        if (!monthStr || monthStr.length < 7) {
            return null;
        }
        var d = new Date(monthStr + '-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-goals-month-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading server goals per month…';
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
                        status.textContent = 'No goal data to chart.';
                    }
                    return;
                }

                var chartData = [];
                for (var i = 0; i < months.length; i++) {
                    var x = monthToDate(months[i].month);
                    if (x === null) {
                        continue;
                    }
                    chartData.push({ x: x, y: months[i].goals });
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

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        datasets: [{
                            label: 'Goals (server)',
                            data: chartData,
                            backgroundColor: T.fill(T.coral(), 0.65),
                            borderColor: T.coral(),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                labels: { color: T.textPrimary() }
                            },
                            tooltip: {
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
                            }
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
                    }
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load server goals per month.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-goals-month-chart');
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
