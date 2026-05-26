/**
 * Participation depth by month (Chart.js stacked bar).
 * Shows monthly active players split into activity bands:
 *   1 game | 2-4 | 5-9 | 10+.
 * Expects api/server_participation_depth.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var API_PATH = 'api/server_participation_depth.php';

    function monthToDate(monthStr) {
        if (!monthStr || monthStr.length < 7) return null;
        var d = new Date(monthStr + '-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-participation-depth-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) status.textContent = 'Chart library failed to load.';
            return;
        }

        fetch(API_PATH + '?realm=online', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('bad_status');
                return r.json();
            })
            .then(function (data) {
                var months = data.months || [];
                if (!months.length) {
                    if (status) status.textContent = 'No participation data to chart.';
                    return;
                }

                var labels = [];
                var d1 = [], d2 = [], d3 = [], d4 = [];
                for (var i = 0; i < months.length; i++) {
                    var x = monthToDate(months[i].month);
                    if (!x) continue;
                    labels.push(x);
                    d1.push(months[i].band_1);
                    d2.push(months[i].band_2_4);
                    d3.push(months[i].band_5_9);
                    d4.push(months[i].band_10plus);
                }

                if (status) status.textContent = '';

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            Object.assign({ label: '1 game',  data: d1, stack: 'depth' }, T.barSolid(T.chrome(), 0.35)),
                            Object.assign({ label: '2 – 4',   data: d2, stack: 'depth' }, T.barSolid(T.chrome(), 0.55)),
                            Object.assign({ label: '5 – 9',   data: d3, stack: 'depth' }, T.barSolid(T.chrome(), 0.75)),
                            Object.assign({ label: '10 +',    data: d4, stack: 'depth' }, T.barSolid(T.chrome(), 1.0))
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
                                mode: 'index',
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) return '';
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) return '';
                                        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long' });
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: { unit: 'month', round: 'month', displayFormats: { month: 'MMM yyyy' } },
                                stacked: true,
                                ticks: { color: T.tickColor(), maxRotation: 45, autoSkip: true, maxTicksLimit: 24 },
                                grid: { color: T.softGrid() }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: { color: T.tickColor(), precision: 0 },
                                grid: { color: T.softGrid() }
                            }
                        }
                    }
                });
            })
            .catch(function () {
                if (status) status.textContent = 'Could not load participation depth.';
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-participation-depth-chart');
        for (var i = 0; i < roots.length; i++) initRoot(roots[i]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
