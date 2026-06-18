/**
 * Most-played opponents (horizontal bar). Profile finale: uniform H2H red, click → Opponents H2H.
 * Lab / legacy: click selects opponent for paired matchup charts below.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = '/api/player_top_opponents.php';
    var EVENT_NAME = 'kool-opponent-selected';
    var MAX_LABEL_LEN = 22;
    var CHART_ASPECT = 960 / 591;

    function truncateLabel(name) {
        if (!name || name.length <= MAX_LABEL_LEN) {
            return name;
        }
        return name.substring(0, MAX_LABEL_LEN - 1) + '\u2026';
    }

    function isProfileFinale(root) {
        return root.getAttribute('data-profile-finale') === '1';
    }

    function uniformBarStyle() {
        if (T && T.barSolid && T.tableNegative) {
            return T.barSolid(T.tableNegative(), 0.78);
        }
        return {
            backgroundColor: 'rgba(212, 138, 154, 0.78)',
            borderColor: 'rgba(212, 138, 154, 1)',
            borderWidth: 0
        };
    }

    function uniformBarColors(count) {
        var style = uniformBarStyle();
        var fills = [];
        var borders = [];
        for (var i = 0; i < count; i++) {
            fills.push(style.backgroundColor);
            borders.push(style.borderColor);
        }
        return { fills: fills, borders: borders, borderWidth: style.borderWidth };
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

    function barBorderColors(opponentIds, selectedId) {
        var colors = [];
        var sel = selectedId == null ? null : Number(selectedId);
        for (var i = 0; i < opponentIds.length; i++) {
            if (sel !== null && Number(opponentIds[i]) === sel) {
                colors.push(T.profileCompareBorder());
            } else {
                colors.push(T.chrome());
            }
        }
        return colors;
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

    function h2hNavigateRoot(fromEl) {
        return fromEl.closest('[data-h2h-base]');
    }

    function navigateH2hOpponent(h2hRoot, opponentId) {
        if (!h2hRoot || !opponentId) {
            return false;
        }
        var base = h2hRoot.getAttribute('data-h2h-base') || '';
        if (!base) {
            return false;
        }
        var hash = h2hRoot.getAttribute('data-h2h-hash') || '';
        if (hash && hash.charAt(0) !== '#') {
            hash = '#' + hash;
        }
        if (!hash && window.K2CarryScroll && typeof window.K2CarryScroll.store === 'function') {
            window.K2CarryScroll.store();
        }
        var sep = base.indexOf('?') >= 0 ? '&' : '?';
        window.location.href = base + sep + 'opponent=' + encodeURIComponent(String(opponentId)) + hash;
        return true;
    }

    function initialOpponentFromH2h(h2hRoot) {
        if (!h2hRoot) {
            return null;
        }
        var id = h2hRoot.getAttribute('data-chart-opponent-id');
        if (!id) {
            return null;
        }
        return {
            id: id,
            name: h2hRoot.getAttribute('data-chart-opponent-name') || ''
        };
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var profileFinale = isProfileFinale(root);
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-top-opponents-chart-status');
        var h2hRoot = h2hNavigateRoot(root);
        var navRoot = profileFinale ? h2hRoot : null;
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

                var selectedId = profileFinale ? null : opponents[0].opponent_id;
                var initialOpponent = profileFinale ? null : initialOpponentFromH2h(h2hRoot);
                if (initialOpponent) {
                    selectedId = initialOpponent.id;
                }

                var barPaint = profileFinale
                    ? uniformBarColors(opponentIds.length)
                    : {
                        fills: barColors(opponentIds, selectedId),
                        borders: barBorderColors(opponentIds, selectedId),
                        borderWidth: 1
                    };

                var maxGames = 0;
                for (var gi = 0; gi < games.length; gi++) {
                    if (games[gi] > maxGames) {
                        maxGames = games[gi];
                    }
                }

                var chart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Games played',
                            data: games,
                            backgroundColor: barPaint.fills,
                            borderColor: barPaint.borders,
                            borderWidth: barPaint.borderWidth
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: true,
                        aspectRatio: CHART_ASPECT,
                        animation: false,
                        interaction: {
                            mode: 'nearest',
                            axis: 'y',
                            intersect: true
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: T && T.mergeTooltip ? T.mergeTooltip({
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
                                        return navRoot || profileFinale
                                            ? 'Click to open head-to-head'
                                            : 'Click to update matchup charts';
                                    }
                                }
                            }) : {
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
                                        return navRoot || profileFinale
                                            ? 'Click to open head-to-head'
                                            : 'Click to update matchup charts';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                bounds: 'data',
                                max: maxGames,
                                grace: 0,
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 0,
                                    max: maxGames
                                },
                                grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                            },
                            y: {
                                ticks: {
                                    color: T.tickColor(),
                                    autoSkip: false
                                },
                                grid: { display: false }
                            }
                        },
                        onHover: function (evt, elements) {
                            canvas.style.cursor = elements.length ? 'pointer' : 'default';
                        }
                    }
                });

                function selectOpponent(idx) {
                    if (navRoot || profileFinale) {
                        navigateH2hOpponent(h2hRoot, opponentIds[idx]);
                        return;
                    }
                    selectedId = opponentIds[idx];
                    chart.data.datasets[0].backgroundColor = barColors(
                        opponentIds,
                        selectedId
                    );
                    chart.data.datasets[0].borderColor = barBorderColors(
                        opponentIds,
                        selectedId
                    );
                    chart.update('none');
                    dispatchSelection(
                        playerId,
                        selectedId,
                        fullNames[idx]
                    );
                }

                var CT = window.K2CoarseTap;
                if (CT) {
                    chart.options.onClick = CT.createChartClickHandler({
                        scopeId: 'top-opponents-' + playerId,
                        chart: chart,
                        canvas: canvas,
                        getAnchorRect: CT.horizontalBarRect,
                        pinKey: function (el) {
                            return String(el.index);
                        },
                        isActive: function () {
                            return true;
                        },
                        getTitle: function (el) {
                            return fullNames[el.index];
                        },
                        getBody: function (el) {
                            return games[el.index] + ' games';
                        },
                        hintNavigate: navRoot || profileFinale
                            ? 'open head-to-head'
                            : 'update matchup charts',
                        onNavigate: function (el) {
                            selectOpponent(el.index);
                        }
                    });
                } else {
                    chart.options.onClick = function (evt, elements) {
                        if (!elements.length) {
                            return;
                        }
                        selectOpponent(elements[0].index);
                    };
                }

                if (status) {
                    status.textContent = '';
                }

                if (!profileFinale) {
                    function applyHighlight(opponentId) {
                        chart.data.datasets[0].backgroundColor = barColors(
                            opponentIds,
                            opponentId
                        );
                        chart.data.datasets[0].borderColor = barBorderColors(
                            opponentIds,
                            opponentId
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

                    var bootOpponent = initialOpponent || {
                        id: opponents[0].opponent_id,
                        name: opponents[0].opponent_name
                    };
                    if (initialOpponent || !navRoot) {
                        dispatchSelection(
                            playerId,
                            bootOpponent.id,
                            bootOpponent.name
                        );
                    }
                }
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
