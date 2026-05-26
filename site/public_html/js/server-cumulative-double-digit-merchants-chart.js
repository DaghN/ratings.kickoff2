/**
 * Cumulative Double Digit Merchants — steps up by 1 at each player's first 10+ goal game.
 * Expects api/server_cumulative_double_digit_merchants.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;

    var API_PATH = 'api/server_cumulative_double_digit_merchants.php';

    function parseGameDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var s = String(dateStr).trim();
        var d = new Date(s.indexOf('T') === -1 ? s.replace(' ', 'T') : s);
        if (isNaN(d.getTime()) && s.length >= 10) {
            d = new Date(s.substring(0, 10) + 'T00:00:00');
        }
        return isNaN(d.getTime()) ? null : d;
    }

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-cumulative-double-digit-merchants-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading cumulative Double Digit Merchants...';
        }

        fetch(API_PATH + '?realm=online', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var events = data.events || [];
                var goalsRequired = data.goals_required || 10;
                var chartData = [];
                var i;

                for (i = 0; i < events.length; i++) {
                    var x = parseGameDate(events[i].date);
                    if (x === null) {
                        continue;
                    }
                    chartData.push({ x: x, y: events[i].cumulative_merchants });
                }

                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No Double Digit Merchant milestones to chart.';
                    }
                    return;
                }

                if (DR && DR.appendRatingThroughToday) {
                    chartData = DR.appendRatingThroughToday(chartData);
                }

                if (status) {
                    status.textContent = '';
                }

                new Chart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [Object.assign({
                            label: 'Cumulative Double Digit Merchants',
                            data: chartData,
                            fill: true,
                            stepped: true,
                            pointRadius: 0,
                            pointHitRadius: 6
                        }, T.lineStroke(T.holo()))]
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
                                        return 'Total merchants: ' + item.parsed.y;
                                    },
                                    afterLabel: function () {
                                        return 'A player scored ' + goalsRequired + '+ goals for the first time';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                max: DR ? DR.endOfToday() : undefined,
                                time: {
                                    displayFormats: {
                                        year: 'yyyy',
                                        month: 'MMM yyyy',
                                        day: 'd MMM yyyy'
                                    }
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    maxRotation: 45,
                                    autoSkip: true,
                                    maxTicksLimit: 18
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
                    status.textContent = 'Could not load cumulative Double Digit Merchants.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-cumulative-double-digit-merchants-chart');
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
