/**
 * Head-to-head cumulative wins vs one opponent (line chart).
 * Listens for kool-opponent-selected from the top-opponents chart.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = 'api/player_head_to_head.php';
    var EVENT_NAME = 'kool-opponent-selected';

    function chartOptions(extra) {
        if (T && T.activityChartOptions) {
            return T.activityChartOptions(Object.assign({ maintainAspectRatio: false }, extra || {}), {
                chartKind: 'line'
            });
        }
        return Object.assign({ responsive: true, maintainAspectRatio: false }, extra || {});
    }

    function createChart(canvas, config) {
        if (T && T.createActivityChart) {
            return T.createActivityChart(canvas, config, 'line');
        }
        return new Chart(canvas, config);
    }

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

                    var lastPoint = points[points.length - 1];
                    setMeta(
                        lastPoint.player_wins + '\u2013' + data.draws + '\u2013' + lastPoint.opponent_wins
                        + ' (W\u2013D\u2013L) \u00b7 ' + data.total_games + ' rated games between them'
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

                    chartInstance = createChart(canvas, {
                        type: 'line',
                        data: {
                            datasets: [
                                {
                                    label: data.playerName + ' wins',
                                    data: playerData,
                                    borderColor: T.profileCompareBorder(),
                                    backgroundColor: T.profileCompareFill(0.12),
                                    borderWidth: 2,
                                    pointRadius: 0,
                                    fill: false,
                                    tension: 0.05
                                },
                                {
                                    label: data.opponentName + ' wins',
                                    data: opponentData,
                                    borderColor: T.opponentFocusBorder(),
                                    backgroundColor: T.opponentFocusFill(0.12),
                                    borderWidth: 2,
                                    pointRadius: 0,
                                    fill: false,
                                    tension: 0.05
                                }
                            ]
                        },
                        options: chartOptions({
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: {
                                    labels: { color: T.textPrimary() }
                                },
                                tooltip: T.mergeTooltip({
                                    callbacks: {
                                        title: function (items) {
                                            if (!items.length) {
                                                return '';
                                            }
                                            return 'After game ' + items[0].parsed.x;
                                        }
                                    }
                                })
                            },
                            scales: {
                                x: {
                                    type: 'linear',
                                    min: 1,
                                    title: {
                                        display: true,
                                        text: 'Head-to-head game #',
                                        color: T.tickColor()
                                    },
                                    ticks: {
                                        color: T.tickColor(),
                                        precision: 0,
                                        maxTicksLimit: 16
                                    },
                                    grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                                },
                                y: {
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Cumulative wins',
                                        color: T.tickColor()
                                    },
                                    ticks: {
                                        color: T.tickColor(),
                                        precision: 0
                                    },
                                    grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                                }
                            }
                        })
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
