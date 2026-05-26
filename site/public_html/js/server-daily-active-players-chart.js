/**
 * Daily active players · 30-day average (Chart.js line + time scale).
 * All-time smoothed line: how many distinct players played on a typical day.
 * Expects api/server_daily_active_players.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var API_PATH = 'api/server_daily_active_players.php';
    var WINDOW = 30;

    function rollingAverage(data, window) {
        var result = [];
        var sum = 0;
        var queue = [];
        for (var i = 0; i < data.length; i++) {
            queue.push(data[i].y);
            sum += data[i].y;
            if (queue.length > window) {
                sum -= queue.shift();
            }
            if (queue.length === window) {
                result.push({ x: data[i].x, y: Math.round((sum / window) * 100) / 100 });
            }
        }
        return result;
    }

    function dayToDate(dayStr) {
        if (!dayStr || dayStr.length < 10) {
            return null;
        }
        var d = new Date(dayStr + 'T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-daily-active-players-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading daily active players…';
        }

        var url = API_PATH + '?realm=online&source=stored';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var days = data.days || [];
                if (!days.length) {
                    if (status) {
                        status.textContent = 'No daily activity data to chart.';
                    }
                    return;
                }

                var rawData = [];
                for (var i = 0; i < days.length; i++) {
                    var x = dayToDate(days[i].day);
                    if (x === null) {
                        continue;
                    }
                    rawData.push({ x: x, y: days[i].active_players });
                }

                if (rawData.length < WINDOW) {
                    if (status) {
                        status.textContent = 'Not enough data for a 30-day average.';
                    }
                    return;
                }

                var smoothed = rollingAverage(rawData, WINDOW);

                if (!smoothed.length) {
                    if (status) {
                        status.textContent = 'Could not compute rolling average.';
                    }
                    return;
                }

                if (status) {
                    status.textContent = '';
                }

                new Chart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [Object.assign({
                            label: '30-day avg active players',
                            data: smoothed,
                            pointRadius: 0,
                            pointHitRadius: 6,
                            tension: 0.3,
                            fill: true
                        }, T.lineStroke(T.chrome()))]
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
                                        var v = item.parsed.y;
                                        return v.toFixed(1) + ' players (30-day avg)';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'month',
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
                    }
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load daily active players.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-daily-active-players-chart');
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
