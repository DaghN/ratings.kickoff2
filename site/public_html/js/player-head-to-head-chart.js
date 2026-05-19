/**
 * Head-to-head cumulative wins vs one opponent (line chart).
 * Listens for kool-opponent-selected from the top-opponents chart.
 */
(function () {
    'use strict';

    var API_PATH = 'api/player_head_to_head.php';
    var EVENT_NAME = 'kool-opponent-selected';

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-head-to-head-chart-status');
        var titleOpponent = root.querySelector('.player-head-to-head-opponent-name');
        var meta = root.querySelector('.player-head-to-head-meta');
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
                status.textContent = 'Loading head-to-head…';
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
                    var points = data.points || [];
                    if (!points.length) {
                        if (chartInstance) {
                            chartInstance.destroy();
                            chartInstance = null;
                        }
                        if (status) {
                            status.textContent = 'No rated games between these players.';
                        }
                        return;
                    }

                    var playerData = [];
                    var opponentData = [];
                    for (var i = 0; i < points.length; i++) {
                        var n = points[i].game_number;
                        playerData.push({ x: n, y: points[i].player_wins });
                        opponentData.push({ x: n, y: points[i].opponent_wins });
                    }

                    var drawNote = data.draws > 0
                        ? data.draws + ' draw' + (data.draws === 1 ? '' : 's') + ' · '
                        : '';
                    setMeta(
                        drawNote + data.total_games + ' rated games between them'
                    );

                    if (titleOpponent && data.opponentName) {
                        titleOpponent.textContent = data.opponentName;
                    }

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
                                    label: data.playerName + ' wins',
                                    data: playerData,
                                    borderColor: '#9ccc65',
                                    backgroundColor: 'rgba(156, 204, 101, 0.12)',
                                    borderWidth: 2,
                                    pointRadius: 0,
                                    fill: false,
                                    tension: 0.05
                                },
                                {
                                    label: data.opponentName + ' wins',
                                    data: opponentData,
                                    borderColor: '#64b5f6',
                                    backgroundColor: 'rgba(100, 181, 246, 0.12)',
                                    borderWidth: 2,
                                    pointRadius: 0,
                                    fill: false,
                                    tension: 0.05
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
                                            return 'After game ' + items[0].parsed.x;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    type: 'linear',
                                    min: 1,
                                    title: {
                                        display: true,
                                        text: 'Head-to-head game #',
                                        color: '#b0b0b0'
                                    },
                                    ticks: {
                                        color: '#b0b0b0',
                                        precision: 0,
                                        maxTicksLimit: 16
                                    },
                                    grid: { color: 'rgba(255, 255, 255, 0.08)' }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Cumulative wins',
                                        color: '#b0b0b0'
                                    },
                                    ticks: {
                                        color: '#b0b0b0',
                                        precision: 0
                                    },
                                    grid: { color: 'rgba(255, 255, 255, 0.08)' }
                                }
                            }
                        }
                    });
                })
                .catch(function () {
                    if (status) {
                        status.textContent = 'Could not load head-to-head data.';
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
        var roots = document.querySelectorAll('.player-head-to-head-chart');
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
