/**
 * Games per calendar month (Chart.js bar + time scale).
 * Expects api/player_games_by_month.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;

    var API_PATH = 'api/player_games_by_month.php';

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

                var padded;
                if (DR && DR.padGamesPerMonth) {
                    padded = DR.padGamesPerMonth(months);
                } else {
                    var fallbackData = [];
                    for (var fi = 0; fi < months.length; fi++) {
                        var fx = DR && DR.monthToDate
                            ? DR.monthToDate(months[fi].month)
                            : new Date(months[fi].month + '-01T00:00:00');
                        if (!isNaN(fx.getTime())) {
                            fallbackData.push({ x: fx, y: months[fi].games });
                        }
                    }
                    padded = { chartData: fallbackData, xMin: null, xMax: null };
                }
                var chartData = padded.chartData;

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
                            backgroundColor: function (ctx) {
                                return ctx.parsed.y === 0
                                    ? 'transparent'
                                    : T.fill(T.green(), 0.65);
                            },
                            borderColor: function (ctx) {
                                return ctx.parsed.y === 0 ? 'transparent' : T.green();
                            },
                            borderWidth: function (ctx) {
                                return ctx.parsed.y === 0 ? 0 : 1;
                            }
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
                                filter: function (item) {
                                    return item.parsed.y > 0;
                                },
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
                                min: padded.xMin || undefined,
                                max: padded.xMax || (DR ? DR.endOfCurrentMonth() : undefined),
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
