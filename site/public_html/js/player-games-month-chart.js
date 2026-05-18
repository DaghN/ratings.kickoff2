/**
 * Games per calendar month (Chart.js bar + time scale).
 * Expects api/player_games_by_month.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var API_PATH = 'api/player_games_by_month.php';

    function monthToDate(monthStr) {
        if (!monthStr || monthStr.length < 7) {
            return null;
        }
        var d = new Date(monthStr + '-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-games-month-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading games per month…';
        }

        var url = API_PATH + '?id=' + encodeURIComponent(playerId) + '&realm=online';

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
                        status.textContent = 'No rated games to chart.';
                    }
                    return;
                }

                var chartData = [];
                for (var i = 0; i < months.length; i++) {
                    var x = monthToDate(months[i].month);
                    if (x === null) {
                        continue;
                    }
                    chartData.push({ x: x, y: months[i].games });
                }

                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No chartable months in game history.';
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
                            label: 'Games',
                            data: chartData,
                            backgroundColor: 'rgba(156, 204, 101, 0.65)',
                            borderColor: '#9ccc65',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                labels: { color: '#e3e3e3' }
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
                                    color: '#b0b0b0',
                                    maxRotation: 45,
                                    autoSkip: true,
                                    maxTicksLimit: 24
                                },
                                grid: { color: 'rgba(255, 255, 255, 0.08)' }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: '#b0b0b0',
                                    precision: 0
                                },
                                grid: { color: 'rgba(255, 255, 255, 0.08)' }
                            }
                        }
                    }
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load games per month.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.player-games-month-chart');
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
