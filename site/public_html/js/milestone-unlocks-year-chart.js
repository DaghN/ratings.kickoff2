/**
 * New unlocks per calendar year for one milestone (milestone.php Graphs).
 * Expects api/milestone_unlocks_by_year.php, chartjs-adapter-date-fns, chart-date-range.js.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;
    var API_PATH = 'api/milestone_unlocks_by_year.php';

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

    function yearToDate(year) {
        var y = parseInt(year, 10);
        if (!y || y < 1000) {
            return null;
        }
        var d = new Date(y + '-01-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
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
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.milestone-unlocks-year-chart-status');
        var key = root.getAttribute('data-milestone-key');
        if (!canvas || !key || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading new unlocks per year\u2026';
        }

        fetch(API_PATH + '?realm=online&key=' + encodeURIComponent(key), { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var years = data.years || [];
                var token = data.chart_token || root.getAttribute('data-chart-token') || 'pitch';
                var color = tierChartColor(token);
                var xMin = parseGameDate(data.first_rated_date);
                var chartData = [];
                var i;

                maybeShowEmptyNote(data.total_unlocks || 0, data.display_name);

                for (i = 0; i < years.length; i++) {
                    var x = yearToDate(years[i].year);
                    if (x === null) {
                        continue;
                    }
                    chartData.push({ x: x, y: years[i].unlocks });
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
                    type: 'bar',
                    data: {
                        datasets: [Object.assign({
                            label: 'New unlocks',
                            data: chartData
                        }, T.barStroke(color))]
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
                                        return String(d.getFullYear());
                                    },
                                    label: function (item) {
                                        var n = item.parsed.y || 0;
                                        return n + (n === 1 ? ' unlock' : ' unlocks');
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                min: xMin || undefined,
                                max: DR ? DR.endOfToday() : undefined,
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
                    status.textContent = 'Could not load unlocks per year.';
                }
            });
    }

    function boot() {
        var panel = document.getElementById('k2-ms-detail-panel-graphs');
        if (panel && panel.hidden) {
            return;
        }
        var roots = document.querySelectorAll('.milestone-unlocks-year-chart');
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
