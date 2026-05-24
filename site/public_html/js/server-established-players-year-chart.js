/**
 * Server-wide newly established players per calendar year (Chart.js bar + time scale).
 * Established = player's 20th rated game occurred in that year.
 * Expects api/server_established_players_by_year.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = 'api/server_established_players_by_year.php';

    function yearToDate(year) {
        var y = parseInt(year, 10);
        if (!y || y < 1000) {
            return null;
        }
        var d = new Date(y + '-01-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-established-players-year-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading newly established players per year…';
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
                var gamesRequired = data.games_required || 20;
                if (!years.length) {
                    if (status) {
                        status.textContent = 'No established-player data to chart.';
                    }
                    return;
                }

                var chartData = [];
                for (var i = 0; i < years.length; i++) {
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

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        datasets: [Object.assign({
                            label: 'New established players (' + gamesRequired + '+ games)',
                            data: chartData
                        }, T.barStroke(T.magenta()))]
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
                                        return String(d.getFullYear());
                                    },
                                    afterLabel: function () {
                                        return gamesRequired + 'th rated game in this year';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'year',
                                    round: 'year',
                                    displayFormats: {
                                        year: 'yyyy'
                                    }
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
                    }
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load established players per year.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-established-players-year-chart');
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
