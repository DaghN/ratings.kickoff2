/**
 * New Double Digit Merchants per calendar year (Chart.js bar + time scale).
 * Merchant = player's first 10+ goal rated game occurred in that year.
 * Expects api/server_double_digit_merchants_by_year.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = 'api/server_double_digit_merchants_by_year.php';

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
        var status = root.querySelector('.server-double-digit-merchants-year-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading new Double Digit Merchants per year...';
        }

        fetch(API_PATH + '?realm=online', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var years = data.years || [];
                var goalsRequired = data.goals_required || 10;
                var chartData = [];
                var i;

                for (i = 0; i < years.length; i++) {
                    var x = yearToDate(years[i].year);
                    if (x === null) {
                        continue;
                    }
                    chartData.push({ x: x, y: years[i].merchants });
                }

                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No Double Digit Merchant data to chart.';
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
                            label: 'New Double Digit Merchants',
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
                                    label: function (item) {
                                        var merchants = item.parsed.y || 0;
                                        return merchants + (merchants === 1 ? ' new merchant' : ' new merchants');
                                    },
                                    afterLabel: function () {
                                        return 'First rated game scoring ' + goalsRequired + '+ goals in this year';
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
                    status.textContent = 'Could not load new Double Digit Merchants per year.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-double-digit-merchants-year-chart');
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
