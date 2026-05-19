/**
 * Compare two players' full career rating over time (shared date axis).
 * Listens for kool-opponent-selected.
 */
(function () {
    'use strict';

    var API_PATH = 'api/player_compare_rating_history.php';
    var EVENT_NAME = 'kool-opponent-selected';

    function parseGameDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var normalized = String(dateStr).trim().replace(' ', 'T');
        var d = new Date(normalized);
        return isNaN(d.getTime()) ? null : d;
    }

    function pointsToChartData(points) {
        var chartData = [];
        for (var i = 0; i < points.length; i++) {
            var x = parseGameDate(points[i].date);
            if (x === null) {
                continue;
            }
            chartData.push({ x: x, y: points[i].rating });
        }
        return chartData;
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-compare-rating-chart-status');
        var titleOpponent = root.querySelector('.player-compare-rating-opponent-name');
        var meta = root.querySelector('.player-compare-rating-meta');
        var chartInstance = null;

        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        function setMeta(text) {
            if (meta) {
                meta.textContent = text || '';
            }
        }

        function loadOpponent(opponentId, opponentName) {
            if (!opponentId) {
                return;
            }

            if (titleOpponent) {
                titleOpponent.textContent = opponentName || '…';
            }
            if (status) {
                status.textContent = 'Loading rating comparison…';
            }
            setMeta('');

            var url = API_PATH + '?id=' + encodeURIComponent(playerId)
                + '&opponent=' + encodeURIComponent(opponentId) + '&realm=online';

            fetch(url, { credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('bad_status');
                    }
                    return r.json();
                })
                .then(function (data) {
                    var player = data.player || {};
                    var opponent = data.opponent || {};
                    var playerData = pointsToChartData(player.points || []);
                    var opponentData = pointsToChartData(opponent.points || []);

                    if (!playerData.length && !opponentData.length) {
                        if (chartInstance) {
                            chartInstance.destroy();
                            chartInstance = null;
                        }
                        if (status) {
                            status.textContent = 'No rated games to chart.';
                        }
                        return;
                    }

                    if (titleOpponent && opponent.playerName) {
                        titleOpponent.textContent = opponent.playerName;
                    }

                    setMeta(
                        (player.points ? player.points.length : 0) + ' games vs '
                        + (opponent.points ? opponent.points.length : 0)
                        + ' games (full careers)'
                    );

                    if (status) {
                        status.textContent = '';
                    }

                    if (chartInstance) {
                        chartInstance.destroy();
                    }

                    chartInstance = new Chart(canvas, {
                        type: 'line',
                        data: {
                            datasets: [
                                {
                                    label: (player.playerName || 'Player') + ' rating',
                                    data: playerData,
                                    borderColor: '#9ccc65',
                                    backgroundColor: 'rgba(156, 204, 101, 0.1)',
                                    borderWidth: 2,
                                    pointRadius: 0,
                                    pointHoverRadius: 4,
                                    fill: false,
                                    tension: 0.1
                                },
                                {
                                    label: (opponent.playerName || opponentName || 'Opponent') + ' rating',
                                    data: opponentData,
                                    borderColor: '#64b5f6',
                                    backgroundColor: 'rgba(100, 181, 246, 0.1)',
                                    borderWidth: 2,
                                    pointRadius: 0,
                                    pointHoverRadius: 4,
                                    fill: false,
                                    tension: 0.1
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            interaction: { mode: 'index', intersect: false },
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
                                        autoSkip: true,
                                        maxTicksLimit: 14
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
                        status.textContent = 'Could not load rating comparison.';
                    }
                });
        }

        document.addEventListener(EVENT_NAME, function (e) {
            if (!e.detail || String(e.detail.playerId) !== String(playerId)) {
                return;
            }
            loadOpponent(e.detail.opponentId, e.detail.opponentName);
        });
    }

    function boot() {
        var roots = document.querySelectorAll('.player-compare-rating-chart');
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
