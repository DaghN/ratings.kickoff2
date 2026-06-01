/**
 * Play texture small multiples (Chart.js multi-line).
 * Four normalized monthly rate lines:
 *   Goals/game · Draw % · DD per 100 · Clean-sheet per 100.
 * Expects api/server_play_texture.php and chartjs-adapter-date-fns.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var API_PATH = 'api/server_play_texture.php';

    function monthToDate(s) {
        if (!s || s.length < 7) return null;
        var d = new Date(s + '-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function initRoot(root) {
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.server-play-texture-chart-status');
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
                    if (status) status.textContent = 'No play texture data to chart.';
                    return;
                }

                var gpg = [], drw = [], dd = [], cs = [];
                for (var i = 0; i < months.length; i++) {
                    var x = monthToDate(months[i].month);
                    if (!x) continue;
                    gpg.push({ x: x, y: months[i].goals_per_game });
                    drw.push({ x: x, y: months[i].draw_pct });
                    dd.push({ x: x, y: months[i].dd_per_100 });
                    cs.push({ x: x, y: months[i].cs_per_100 });
                }

                if (status) status.textContent = '';

                T.createChart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [
                            Object.assign({
                                label: 'Goals / game',
                                data: gpg,
                                yAxisID: 'yLeft',
                                tension: 0.3,
                                pointRadius: 0,
                                pointHitRadius: 6
                            }, T.lineStroke(T.pitch())),
                            Object.assign({
                                label: 'Draw %',
                                data: drw,
                                yAxisID: 'yRight',
                                tension: 0.3,
                                pointRadius: 0,
                                pointHitRadius: 6
                            }, T.lineStroke(T.amber())),
                            Object.assign({
                                label: 'DD per 100',
                                data: dd,
                                yAxisID: 'yRight',
                                tension: 0.3,
                                pointRadius: 0,
                                pointHitRadius: 6
                            }, T.lineStroke(T.magenta())),
                            Object.assign({
                                label: 'Clean sheet per 100',
                                data: cs,
                                yAxisID: 'yRight',
                                tension: 0.3,
                                pointRadius: 0,
                                pointHitRadius: 6
                            }, T.lineStroke(T.holo()))
                        ]
                    },
                    options: T.mergeChartOptions({
                        responsive: true,
                        maintainAspectRatio: true,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) return '';
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) return '';
                                        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long' });
                                    }
                                }
                            }),
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: { unit: 'month', round: 'month', displayFormats: { month: 'MMM yyyy' } },
                                ticks: { color: T.tickColor(), maxRotation: 45, autoSkip: true, maxTicksLimit: 24 },
                                grid: { color: T.softGrid() }
                            },
                            yLeft: {
                                position: 'left',
                                beginAtZero: true,
                                title: { display: true, text: 'Goals / game', color: T.textMuted() },
                                ticks: { color: T.tickColor() },
                                grid: { color: T.softGrid() }
                            },
                            yRight: {
                                position: 'right',
                                beginAtZero: true,
                                title: { display: true, text: 'Per 100 games / %', color: T.textMuted() },
                                ticks: { color: T.tickColor() },
                                grid: { drawOnChartArea: false }
                            }
                        }
                    }, 'line'),
                });
            })
            .catch(function () {
                if (status) status.textContent = 'Could not load play texture.';
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.server-play-texture-chart');
        for (var i = 0; i < roots.length; i++) {
            (function (root) {
                T.whenBlockVisible(root, function () {
                    initRoot(root);
                }, 11);
            })(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
