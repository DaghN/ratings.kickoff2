/**
 * Games per calendar year (Chart.js stacked bar).
 * Current year: YTD (solid) + projected remainder (stacked, different color).
 * Expects api/server_games_by_year.php
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = 'api/server_games_by_year.php';

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-games-year-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading games per year…';
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
                var years = data.years || [];
                var currentYear = data.current_year;
                var projection = data.projection || {};

                if (!years.length) {
                    if (status) {
                        status.textContent = 'No rated games to chart.';
                    }
                    return;
                }

                var labels = [];
                var actualData = [];
                var projectedData = [];

                for (var i = 0; i < years.length; i++) {
                    var y = years[i];
                    labels.push(String(y.year));
                    if (y.is_current) {
                        actualData.push(y.games);
                        projectedData.push(y.projected_remainder || 0);
                    } else {
                        actualData.push(y.games);
                        projectedData.push(0);
                    }
                }

                if (status) {
                    status.textContent = '';
                }

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            Object.assign({
                                label: 'Games',
                                data: actualData,
                                stack: 'games'
                            }, T.barStroke(T.pitch(), 0.75)),
                            Object.assign({
                                label: 'Projected',
                                data: projectedData,
                                stack: 'games'
                            }, T.barStroke(T.chrome(), 0.55))
                        ]
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
                                    footer: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var idx = items[0].dataIndex;
                                        var y = years[idx];
                                        if (!y.is_current) {
                                            return '';
                                        }
                                        var total = y.projected_total;
                                        if (total == null) {
                                            return '';
                                        }
                                        var days = projection.days_elapsed;
                                        var dim = projection.days_in_year;
                                        var pace = '';
                                        if (days && dim) {
                                            pace = ' Pace: ' + y.games + ' games in ' + days + ' of ' + dim + ' days.';
                                        }
                                        return 'Projected full ' + y.year + ': ~' + total + ' games.' + pace;
                                    },
                                    label: function (ctx) {
                                        var y = years[ctx.dataIndex];
                                        if (ctx.datasetIndex === 0) {
                                            if (y.is_current) {
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
                            }
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
                    }
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load games per year.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-games-year-chart');
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
