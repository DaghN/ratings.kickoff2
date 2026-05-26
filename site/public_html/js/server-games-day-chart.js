/**
 * Games per day for the recent activity window (Chart.js bar).
 * Expects api/server_games_by_day_recent.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var API_PATH = 'api/server_games_by_day_recent.php';

    function dayToDate(dayStr) {
        if (!dayStr || dayStr.length < 10) {
            return null;
        }

        var d = new Date(dayStr + 'T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-games-day-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading games per day...';
        }

        fetch(API_PATH + '?realm=online', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
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

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        datasets: [Object.assign({
                            label: 'Games',
                            data: chartData
                        }, T.barStroke(T.pitch()))]
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
                                            month: 'short',
                                            day: 'numeric'
                                        });
                                    },
                                    label: function (item) {
                                        var games = item.parsed.y || 0;
                                        return games + (games === 1 ? ' rated game' : ' rated games');
                                    }
                                }
                            }
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
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 0
                                },
                                grid: { color: T.softGrid() }
                            }
                        }
                    }
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load games per day.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-games-day-chart');
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
