/**
 * Head-to-head cumulative goals vs one opponent (line chart).
 * Shares /api/player_head_to_head.php with the wins chart.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = '/api/player_head_to_head.php';
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
        var status = root.querySelector('.player-head-to-head-goals-chart-status');
        var meta = root.querySelector('.player-head-to-head-goals-meta');
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

        function setHeading(opponentLabel) {
            var matchups = root.closest('.pm3d-matchups');
            var heading = matchups ? matchups.querySelector('.player-head-to-head-goals-chart-heading') : null;
            if (!heading) {
                return;
            }
            heading.textContent = opponentLabel
                ? 'Cumulative goals vs ' + opponentLabel
                : 'Cumulative goals';
        }

        function formatGoalsMeta(data) {
            var playerTotal = data.player_goals_total;
            var opponentTotal = data.opponent_goals_total;
            if (playerTotal === undefined || opponentTotal === undefined) {
                return data.total_games + ' rated games';
            }
            return data.playerName + ' ' + playerTotal + ' goals · '
                + data.opponentName + ' ' + opponentTotal + ' goals';
        }

        function loadOpponent(opponentId, opponentName) {
            if (!opponentId) {
                return;
            }

            if (status) {
                status.textContent = 'Loading cumulative goals…';
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
                        playerData.push({ x: n, y: points[i].player_goals });
                        opponentData.push({ x: n, y: points[i].opponent_goals });
                    }

                    setMeta(formatGoalsMeta(data));
                    if (data.opponentName) {
                        setHeading(data.opponentName);
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
                                    label: data.playerName + ' goals',
                                    data: playerData,
                                    borderColor: T.h2hSubjectBorder(),
                                    backgroundColor: T.h2hSubjectFill(0.12),
                                    borderWidth: 2,
                                    pointRadius: 0,
                                    fill: false,
                                    tension: 0.05
                                },
                                {
                                    label: data.opponentName + ' goals',
                                    data: opponentData,
                                    borderColor: T.h2hOpponentBorder(),
                                    backgroundColor: T.h2hOpponentFill(0.12),
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
                                        text: 'Cumulative goals',
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
                        status.textContent = 'Could not load cumulative goals data.';
                    }
                });
        }

        document.addEventListener(EVENT_NAME, function (e) {
            if (!e.detail || String(e.detail.playerId) !== String(playerId)) {
                return;
            }
            loadOpponent(e.detail.opponentId, e.detail.opponentName);
        });

        var h2hRoot = root.closest('.k2-player-opponents-h2h');
        if (h2hRoot) {
            var initialId = h2hRoot.getAttribute('data-chart-opponent-id');
            if (initialId) {
                loadOpponent(initialId, h2hRoot.getAttribute('data-chart-opponent-name') || '');
            }
        }
    }

    function boot() {
        var roots = document.querySelectorAll('.player-head-to-head-goals-chart');
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
