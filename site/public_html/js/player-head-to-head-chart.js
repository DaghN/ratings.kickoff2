/**
 * Head-to-head cumulative wins vs one opponent (line chart).
 * Listens for kool-opponent-selected (H2H tab) or loads initial opponent from page data attrs.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = '/api/player_head_to_head.php';
    var EVENT_NAME = 'kool-opponent-selected';
    var CTX = window.K2PlayerOpponentsH2hContext;

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

    function withH2hChartOrigin(chartData) {
        if (!chartData.length) {
            return chartData.slice();
        }
        var out = [{ x: 0, y: 0, isOrigin: true }];
        for (var i = 0; i < chartData.length; i++) {
            out.push(chartData[i]);
        }
        return out;
    }

    function h2hGameTooltipTitle(items) {
        if (!items.length) {
            return '';
        }
        var raw = items[0].raw;
        if (raw && raw.isOrigin) {
            return 'Before first game';
        }
        return 'After game ' + items[0].parsed.x;
    }

    function initRoot(root) {
        if (root.getAttribute('data-k2-chart-bound') === '1') {
            return;
        }
        root.setAttribute('data-k2-chart-bound', '1');

        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-head-to-head-chart-status');
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

        function setHeading(opponentLabel) {
            var matchups = root.closest('.pm3d-matchups');
            var heading = matchups ? matchups.querySelector('.player-head-to-head-chart-heading') : null;
            if (!heading) {
                return;
            }
            heading.textContent = opponentLabel
                ? 'Wins vs ' + opponentLabel
                : 'Wins';
        }

        function formatH2hMeta(data) {
            return data.total_games + ' rated games';
        }

        function loadOpponent(opponentId, opponentName) {
            if (!opponentId) {
                return;
            }

            if (status) {
                status.textContent = 'Loading head-to-head…';
            }
            setMeta('');

            var url = API_PATH + '?id=' + encodeURIComponent(playerId)
                + '&opponent=' + encodeURIComponent(opponentId)
                + (CTX ? CTX.apiSuffix(root) : '&realm=online');

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
                    playerData = withH2hChartOrigin(playerData);
                    opponentData = withH2hChartOrigin(opponentData);

                    setMeta(formatH2hMeta(data));
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
                                    label: data.playerName + ' wins',
                                    data: playerData,
                                    borderColor: T.h2hSubjectBorder(),
                                    backgroundColor: T.h2hSubjectFill(0.12),
                                    borderWidth: 2,
                                    pointRadius: 0,
                                    fill: false,
                                    tension: 0.05
                                },
                                {
                                    label: data.opponentName + ' wins',
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
                                        title: h2hGameTooltipTitle
                                    }
                                })
                            },
                            scales: {
                                x: {
                                    type: 'linear',
                                    min: 0,
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

        var h2hRoot = root.closest('.k2-player-opponents-h2h');
        if (h2hRoot) {
            var initialId = h2hRoot.getAttribute('data-chart-opponent-id');
            if (initialId) {
                loadOpponent(initialId, h2hRoot.getAttribute('data-chart-opponent-name') || '');
            }
        }
    }

    function boot() {
        var roots = document.querySelectorAll('.player-head-to-head-chart');
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
