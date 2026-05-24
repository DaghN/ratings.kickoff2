/**
 * Most-played opponents (horizontal bar). Click selects opponent for head-to-head chart.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = 'api/player_top_opponents.php';
    var EVENT_NAME = 'kool-opponent-selected';
    var MAX_LABEL_LEN = 22;

    function truncateLabel(name) {
        if (!name || name.length <= MAX_LABEL_LEN) {
            return name;
        }
        return name.substring(0, MAX_LABEL_LEN - 1) + '\u2026';
    }

    function barColors(opponentIds, selectedId) {
        var colors = [];
        var sel = selectedId == null ? null : Number(selectedId);
        for (var i = 0; i < opponentIds.length; i++) {
            if (sel !== null && Number(opponentIds[i]) === sel) {
                colors.push(T.profileCompareFill(0.85));
            } else {
                colors.push(T.opponentFocusFill(0.45));
            }
        }
        return colors;
    }

    function isInOpponentList(opponentIds, opponentId) {
        var id = Number(opponentId);
        for (var i = 0; i < opponentIds.length; i++) {
            if (Number(opponentIds[i]) === id) {
                return true;
            }
        }
        return false;
    }

    function dispatchSelection(playerId, opponentId, opponentName) {
        document.dispatchEvent(new CustomEvent(EVENT_NAME, {
            detail: {
                playerId: playerId,
                opponentId: opponentId,
                opponentName: opponentName
            }
        }));
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-top-opponents-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading top opponents…';
        }

        var url = API_PATH + '?id=' + encodeURIComponent(playerId) + '&realm=online&limit=20';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                var opponents = data.opponents || [];
                if (!opponents.length) {
                    if (status) {
                        status.textContent = 'No opponents to chart.';
                    }
                    return;
                }

                var labels = [];
                var games = [];
                var fullNames = [];
                var opponentIds = [];
                for (var i = opponents.length - 1; i >= 0; i--) {
                    var o = opponents[i];
                    labels.push(truncateLabel(o.opponent_name));
                    games.push(o.games);
                    fullNames.push(o.opponent_name);
                    opponentIds.push(o.opponent_id);
                }

                var selectedId = opponents[0].opponent_id;
                var chart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Games played',
                            data: games,
                            backgroundColor: barColors(opponentIds, selectedId),
                            borderColor: T.chrome(),
                            borderWidth: 1
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        return fullNames[items[0].dataIndex];
                                    },
                                    label: function (item) {
                                        return item.parsed.x + ' games';
                                    },
                                    afterLabel: function () {
                                        return 'Click to update matchup charts';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 0
                                },
                                grid: { color: T.grid() }
                            },
                            y: {
                                ticks: { color: T.tickColor() },
                                grid: { display: false }
                            }
                        },
                        onClick: function (evt, elements) {
                            if (!elements.length) {
                                return;
                            }
                            var idx = elements[0].index;
                            selectedId = opponentIds[idx];
                            chart.data.datasets[0].backgroundColor = barColors(
                                opponentIds,
                                selectedId
                            );
                            chart.update();
                            dispatchSelection(
                                playerId,
                                selectedId,
                                fullNames[idx]
                            );
                        },
                        onHover: function (evt, elements) {
                            canvas.style.cursor = elements.length ? 'pointer' : 'default';
                        }
                    }
                });

                if (status) {
                    status.textContent = '';
                }

                function applyHighlight(opponentId) {
                    var highlightId = isInOpponentList(opponentIds, opponentId)
                        ? opponentId
                        : null;
                    chart.data.datasets[0].backgroundColor = barColors(
                        opponentIds,
                        highlightId
                    );
                    chart.update('none');
                }

                document.addEventListener(EVENT_NAME, function (e) {
                    if (!e.detail || String(e.detail.playerId) !== String(playerId)) {
                        return;
                    }
                    selectedId = e.detail.opponentId;
                    applyHighlight(selectedId);
                });

                dispatchSelection(
                    playerId,
                    opponents[0].opponent_id,
                    opponents[0].opponent_name
                );
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load top opponents.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.player-top-opponents-chart');
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
