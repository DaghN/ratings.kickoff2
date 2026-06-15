/**
 * H2H combined goals per game — SumOfGoals histogram for one pairing (holo bars).
 * Click bar → games tab ?gs= + opponent=.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = '/api/player_h2h_total_goals_distribution.php';
    var EVENT_NAME = 'kool-opponent-selected';

    function chartOptions(extra) {
        if (T && T.activityChartOptions) {
            return T.activityChartOptions(Object.assign({ maintainAspectRatio: false }, extra || {}), {
                chartKind: 'bar'
            });
        }
        return Object.assign({ responsive: true, maintainAspectRatio: false }, extra || {});
    }

    function createChart(canvas, config) {
        if (T && T.createActivityChart) {
            return T.createActivityChart(canvas, config, 'bar');
        }
        return new Chart(canvas, config);
    }

    function pickBarElement(chart, evt, elements) {
        if (elements && elements.length) {
            return elements[0];
        }
        if (chart && evt && typeof chart.getElementsAtEventForMode === 'function') {
            var precise = chart.getElementsAtEventForMode(
                evt,
                'nearest',
                { intersect: true },
                false
            );
            if (precise.length) {
                return precise[0];
            }
        }
        return null;
    }

    function gamesListUrl(playerId, totalGoals, opponentId) {
        var url = '/player/games.php?id=' + encodeURIComponent(String(playerId))
            + '&gs=' + encodeURIComponent(String(totalGoals));
        if (opponentId) {
            url += '&opponent=' + encodeURIComponent(String(opponentId));
        }
        return url;
    }

    function barStyle() {
        if (T && T.barSolid && T.holo) {
            return T.barSolid(T.holo(), 0.78);
        }
        return {
            backgroundColor: 'rgba(179, 136, 255, 0.78)',
            borderColor: 'rgba(179, 136, 255, 1)',
            borderWidth: 0
        };
    }

    function bucketsToSeries(buckets) {
        var labels = [];
        var games = [];
        var goalValues = [];
        var i;
        for (i = 0; i < buckets.length; i++) {
            labels.push(String(buckets[i].goals));
            games.push(buckets[i].games);
            goalValues.push(buckets[i].goals);
        }
        return {
            labels: labels,
            games: games,
            goalValues: goalValues
        };
    }

    function formatTotalGoalsMeta(data) {
        var games = data.totalGames || 0;
        var playerName = data.playerName || '';
        var opponentName = data.opponentName || '';
        if (!games || !playerName || !opponentName) {
            return '';
        }

        var avg = data.avgTotalGoals;
        if (avg == null && data.buckets && data.buckets.length) {
            var goalSum = 0;
            var i;
            for (i = 0; i < data.buckets.length; i++) {
                goalSum += data.buckets[i].goals * data.buckets[i].games;
            }
            avg = goalSum / games;
        }
        if (avg == null) {
            return '';
        }

        var avgText = Number(avg).toFixed(2);
        return 'Across ' + games + ' rated game' + (games === 1 ? '' : 's')
            + ', ' + playerName + ' and ' + opponentName
            + ' average ' + avgText + ' combined goals per game.';
    }

    function setHeading(root, opponentLabel) {
        var matchups = root.closest('.pm3d-matchups');
        var heading = matchups ? matchups.querySelector('.player-h2h-total-goals-chart-heading') : null;
        if (!heading) {
            return;
        }
        heading.textContent = opponentLabel
            ? 'Combined goals per game vs ' + opponentLabel
            : 'Combined goals per game';
    }

    function renderChart(root, buckets, data) {
        var playerId = root.getAttribute('data-player-id');
        var opponentId = root.getAttribute('data-opponent-id') || '';
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-h2h-total-goals-chart-status');
        var meta = root.querySelector('.player-h2h-total-goals-meta');
        var chartInstance = root._k2H2hTotalGoalsChart || null;

        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (!buckets.length) {
            if (chartInstance) {
                chartInstance.destroy();
                root._k2H2hTotalGoalsChart = null;
            }
            if (status) {
                status.textContent = 'No rated games against this opponent.';
            }
            if (meta) {
                meta.textContent = '';
            }
            return;
        }

        if (data && data.opponentName) {
            setHeading(root, data.opponentName);
        }

        if (meta) {
            meta.textContent = formatTotalGoalsMeta(data);
        }

        if (status) {
            status.textContent = '';
        }

        var series = bucketsToSeries(buckets);
        var style = barStyle();

        if (chartInstance) {
            chartInstance.destroy();
            root._k2H2hTotalGoalsChart = null;
        }

        chartInstance = createChart(canvas, {
            type: 'bar',
            data: {
                labels: series.labels,
                datasets: [{
                    label: 'Games',
                    data: series.games,
                    backgroundColor: style.backgroundColor,
                    borderColor: style.borderColor,
                    borderWidth: style.borderWidth
                }]
            },
            options: chartOptions({
                interaction: {
                    mode: 'nearest',
                    intersect: true
                },
                plugins: {
                    legend: { display: false },
                    tooltip: T.mergeTooltip({
                        filter: function (item) {
                            return item.parsed.y > 0;
                        },
                        callbacks: {
                            title: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                var total = series.goalValues[items[0].dataIndex];
                                return total + ' goal' + (total === 1 ? '' : 's');
                            },
                            label: function (item) {
                                return item.parsed.y + ' game' + (item.parsed.y === 1 ? '' : 's');
                            },
                            afterLabel: function () {
                                return 'Click to filter games list by goal sum';
                            }
                        }
                    })
                },
                scales: {
                    x: {
                        title: {
                            display: false
                        },
                        ticks: {
                            color: T.tickColor(),
                            autoSkip: false,
                            maxRotation: 0
                        },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Games',
                            color: T.tickColor()
                        },
                        ticks: {
                            color: T.tickColor(),
                            precision: 0
                        },
                        grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                    }
                },
                onClick: function (evt, elements) {
                    var el = pickBarElement(chartInstance, evt, elements);
                    if (!el) {
                        return;
                    }
                    var idx = el.index;
                    if (!series.games[idx]) {
                        return;
                    }
                    window.location.href = gamesListUrl(
                        playerId,
                        series.goalValues[idx],
                        opponentId || null
                    );
                },
                onHover: function (evt, elements) {
                    var el = pickBarElement(chartInstance, evt, elements);
                    if (!el || !series.games[el.index]) {
                        canvas.style.cursor = 'default';
                        return;
                    }
                    canvas.style.cursor = 'pointer';
                }
            })
        });
        root._k2H2hTotalGoalsChart = chartInstance;
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        function loadOpponent(opponentId, opponentName) {
            if (!opponentId) {
                return;
            }

            root.setAttribute('data-opponent-id', String(opponentId));

            var status = root.querySelector('.player-h2h-total-goals-chart-status');
            var meta = root.querySelector('.player-h2h-total-goals-meta');
            if (status) {
                status.textContent = 'Loading combined goals per game…';
            }
            if (meta) {
                meta.textContent = '';
            }

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
                    renderChart(root, data.buckets || [], data);
                })
                .catch(function () {
                    if (status) {
                        status.textContent = 'Could not load combined goals per game.';
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
        } else {
            var staticOpponentId = root.getAttribute('data-opponent-id');
            if (staticOpponentId) {
                loadOpponent(staticOpponentId, '');
            }
        }
    }

    function boot() {
        var roots = document.querySelectorAll('.player-h2h-total-goals-chart');
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
