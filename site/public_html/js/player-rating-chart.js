/**
 * ELO rating over time (Chart.js time scale). Expects api/player_rating_history.php
 * and chartjs-adapter-date-fns loaded after Chart.js.
 */
(function () {
    'use strict';

    var API_PATH = 'api/player_rating_history.php';

    function parseGameDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var normalized = String(dateStr).trim().replace(' ', 'T');
        var d = new Date(normalized);
        return isNaN(d.getTime()) ? null : d;
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-rating-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading rating history…';
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
                var points = data.points || [];
                if (!points.length) {
                    if (status) {
                        status.textContent = 'No rated games to chart.';
                    }
                    return;
                }

                var chartData = [];
                for (var i = 0; i < points.length; i++) {
                    var x = parseGameDate(points[i].date);
                    if (x === null) {
                        continue;
                    }
                    chartData.push({ x: x, y: points[i].rating });
                }

                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No chartable dates in game history.';
                    }
                    return;
                }

                if (status) {
                    status.textContent = '';
                }

                new Chart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [{
                            label: 'ELO rating (after game)',
                            data: chartData,
                            borderColor: '#9ccc65',
                            backgroundColor: 'rgba(156, 204, 101, 0.15)',
                            borderWidth: 2,
                            pointRadius: chartData.length > 80 ? 0 : 2,
                            pointHoverRadius: 4,
                            fill: true,
                            tension: 0.1
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
                                        var raw = items[0].parsed.x;
                                        var d = new Date(raw);
                                        if (isNaN(d.getTime())) {
                                            return '';
                                        }
                                        return d.toLocaleDateString(undefined, {
                                            year: 'numeric',
                                            month: 'short',
                                            day: 'numeric'
                                        });
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    displayFormats: {
                                        year: 'yyyy',
                                        month: 'MMM yyyy',
                                        day: 'MMM d, yyyy'
                                    }
                                },
                                ticks: {
                                    color: '#b0b0b0',
                                    maxRotation: 45,
                                    minRotation: 0,
                                    autoSkip: true,
                                    maxTicksLimit: 12
                                },
                                grid: { color: 'rgba(255, 255, 255, 0.08)' }
                            },
                            y: {
                                ticks: { color: '#b0b0b0' },
                                grid: { color: 'rgba(255, 255, 255, 0.08)' }
                            }
                        }
                    }
                });
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load rating history.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.player-rating-chart');
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
