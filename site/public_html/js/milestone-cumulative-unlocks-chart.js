/**
 * Cumulative unlocks for one milestone (milestone.php Graphs).
 * Expects api/milestone_cumulative_unlocks.php, chartjs-adapter-date-fns, chart-date-range.js.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;
    var API_PATH = 'api/milestone_cumulative_unlocks.php';

    function tierChartColor(token) {
        if (!T) {
            return '#9ccc65';
        }
        if (token === 'chrome') {
            return T.chrome();
        }
        if (token === 'amber') {
            return T.amber();
        }
        if (token === 'holo') {
            return T.holo();
        }
        return T.pitch();
    }

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

    function maybeShowEmptyNote(totalUnlocks, displayName) {
        var note = document.getElementById('k2-ms-charts-empty-note');
        if (!note || totalUnlocks > 0) {
            return;
        }
        var label = displayName ? String(displayName) : 'this milestone';
        note.hidden = false;
        note.textContent = 'Nobody has unlocked \u201c' + label + '\u201d yet. The charts below span the full rated-ladder era (zero unlocks so far).';
    }

    function initRoot(root) {
        if (root.getAttribute('data-k2-chart-bound') === '1') {
            return;
        }
        root.setAttribute('data-k2-chart-bound', '1');

        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.milestone-cumulative-unlocks-chart-status');
        var key = root.getAttribute('data-milestone-key');
        if (!canvas || !key || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading cumulative unlocks\u2026';
        }

        fetch(API_PATH + '?realm=online&key=' + encodeURIComponent(key), { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var events = data.events || [];
                var token = data.chart_token || root.getAttribute('data-chart-token') || 'pitch';
                var color = tierChartColor(token);
                var firstX = parseGameDate(data.first_rated_date);
                var chartData = [];
                var i;
                var x;
                var lastY = 0;

                maybeShowEmptyNote(data.total_unlocks || 0, data.display_name);

                if (firstX) {
                    chartData.push({ x: firstX, y: 0 });
                }

                for (i = 0; i < events.length; i++) {
                    x = parseGameDate(events[i].date);
                    if (x === null) {
                        continue;
                    }
                    lastY = events[i].cumulative_unlocks;
                    chartData.push({ x: x, y: lastY });
                }

                if (!chartData.length && firstX) {
                    chartData.push({ x: firstX, y: 0 });
                }

                if (DR && DR.appendRatingThroughToday) {
                    chartData = DR.appendRatingThroughToday(chartData, lastY);
                }

                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No rated-ladder history to chart.';
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
                            label: 'Cumulative unlocks',
                            data: chartData,
                            fill: true,
                            stepped: true,
                            pointRadius: 0,
                            pointHitRadius: 6
                        }, T.lineStroke(color))]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                labels: { color: T.textSecondary() }
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
                                        return 'Total unlocks: ' + item.parsed.y;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                min: firstX || undefined,
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
                    status.textContent = 'Could not load cumulative unlocks.';
                }
            });
    }

    function boot() {
        var panel = document.getElementById('k2-ms-detail-panel-graphs');
        if (panel && panel.hidden) {
            return;
        }
        var roots = document.querySelectorAll('.milestone-cumulative-unlocks-chart');
        for (var i = 0; i < roots.length; i++) {
            initRoot(roots[i]);
        }
    }

    (window.k2OnPageReady || function (fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    })(boot);
})();
