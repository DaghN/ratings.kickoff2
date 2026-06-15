/**
 * Goals scored per game — full histogram (0..max). Click bar → games tab gf/ga filter.
 * Profile: single amber series. H2H: two single-series charts + grouped comparison (shared x-axis max).
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;

    var API_PATH = '/api/player_goals_scored_distribution.php';

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

    function gamesListUrl(playerId, goals, opponentId, goalParam) {
        var url = '/player/games.php?id=' + encodeURIComponent(String(playerId))
            + '&' + goalParam + '=' + encodeURIComponent(String(goals));
        if (opponentId) {
            url += '&opponent=' + encodeURIComponent(String(opponentId));
        }
        return url;
    }

    function setSectionHeading(root, opponentLabel) {
        if (!opponentLabel) {
            return;
        }
        var matchups = root.closest('.pm3d-matchups');
        var heading = matchups ? matchups.querySelector('.player-goals-scored-histogram-heading') : null;
        if (heading) {
            heading.textContent = 'Goals per game vs ' + opponentLabel;
        }
    }

    function barStyleForSide(isH2h, isRival) {
        if (T && T.barSolid) {
            var barColor;
            if (!isH2h) {
                barColor = T.amber();
            } else if (isRival && T.tableNegative) {
                barColor = T.tableNegative();
            } else if (T.chrome) {
                barColor = T.chrome();
            } else {
                barColor = T.amber();
            }
            return T.barSolid(barColor, 0.78);
        }
        if (!isH2h) {
            return {
                backgroundColor: 'rgba(255, 183, 77, 0.78)',
                borderColor: 'rgba(255, 183, 77, 1)',
                borderWidth: 0
            };
        }
        if (isRival) {
            return {
                backgroundColor: 'rgba(212, 138, 154, 0.78)',
                borderColor: 'rgba(212, 138, 154, 1)',
                borderWidth: 0
            };
        }
        return {
            backgroundColor: 'rgba(100, 181, 246, 0.78)',
            borderColor: 'rgba(100, 181, 246, 1)',
            borderWidth: 0
        };
    }

    function distributionMaxGoals(data) {
        if (!data) {
            return 0;
        }
        if (data.maxGoals != null && data.maxGoals >= 0) {
            return data.maxGoals;
        }
        var buckets = data.buckets || [];
        if (!buckets.length) {
            return 0;
        }
        return buckets[buckets.length - 1].goals;
    }

    function padBuckets(buckets, maxGoals) {
        var counts = Object.create(null);
        var i;
        for (i = 0; i < buckets.length; i++) {
            counts[buckets[i].goals] = buckets[i].games;
        }
        var padded = [];
        for (var g = 0; g <= maxGoals; g++) {
            padded.push({
                goals: g,
                games: counts[g] != null ? counts[g] : 0
            });
        }
        return padded;
    }

    function fetchDistribution(apiPlayerId, apiOpponentId) {
        var url = API_PATH + '?id=' + encodeURIComponent(String(apiPlayerId)) + '&realm=online';
        if (apiOpponentId) {
            url += '&opponent=' + encodeURIComponent(String(apiOpponentId));
        }
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                throw new Error('bad_status');
            }
            return r.json();
        });
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

    function renderStandaloneChart(root, buckets, data) {
        var playerId = root.getAttribute('data-player-id');
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-goals-scored-histogram-status');
        var chartInstance = root._k2GoalsHistogramChart || null;

        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (!buckets.length) {
            if (chartInstance) {
                chartInstance.destroy();
                root._k2GoalsHistogramChart = null;
            }
            if (status) {
                status.textContent = 'No rated games to chart.';
            }
            return;
        }

        if (status) {
            status.textContent = '';
        }

        var series = bucketsToSeries(buckets);
        var barStyle = barStyleForSide(false, false);

        if (chartInstance) {
            chartInstance.destroy();
            root._k2GoalsHistogramChart = null;
        }

        chartInstance = createChart(canvas, {
            type: 'bar',
            data: {
                labels: series.labels,
                datasets: [{
                    label: 'Games',
                    data: series.games,
                    backgroundColor: barStyle.backgroundColor,
                    borderColor: barStyle.borderColor,
                    borderWidth: barStyle.borderWidth
                }]
            },
            options: chartOptions({
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
                                var goals = series.goalValues[items[0].dataIndex];
                                return goals + ' goal' + (goals === 1 ? '' : 's') + ' scored';
                            },
                            label: function (item) {
                                return item.parsed.y + ' game' + (item.parsed.y === 1 ? '' : 's');
                            },
                            afterLabel: function () {
                                return 'Click to filter games list';
                            }
                        }
                    })
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Goals scored',
                            color: T.tickColor()
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
                    if (!elements.length) {
                        return;
                    }
                    var idx = elements[0].index;
                    if (!series.games[idx]) {
                        return;
                    }
                    window.location.href = gamesListUrl(
                        playerId,
                        series.goalValues[idx],
                        null,
                        'gf'
                    );
                },
                onHover: function (evt, elements) {
                    if (!elements.length || !series.games[elements[0].index]) {
                        canvas.style.cursor = 'default';
                        return;
                    }
                    canvas.style.cursor = 'pointer';
                }
            })
        });
        root._k2GoalsHistogramChart = chartInstance;
    }

    function renderH2hSingleChart(root, buckets, data) {
        var playerId = root.getAttribute('data-player-id');
        var opponentId = root.getAttribute('data-opponent-id') || '';
        var isRival = root.getAttribute('data-h2h-side') === 'rival';
        var goalParam = isRival ? 'ga' : 'gf';
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-goals-scored-histogram-status');
        var chartInstance = root._k2GoalsHistogramChart || null;

        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (!buckets.length) {
            if (chartInstance) {
                chartInstance.destroy();
                root._k2GoalsHistogramChart = null;
            }
            if (status) {
                status.textContent = 'No rated games against this opponent.';
            }
            return;
        }

        if (!isRival && data && data.opponentName) {
            setSectionHeading(root, data.opponentName);
        }

        if (status) {
            status.textContent = '';
        }

        var series = bucketsToSeries(buckets);
        var barStyle = barStyleForSide(true, isRival);

        if (chartInstance) {
            chartInstance.destroy();
            root._k2GoalsHistogramChart = null;
        }

        chartInstance = createChart(canvas, {
            type: 'bar',
            data: {
                labels: series.labels,
                datasets: [{
                    label: 'Games',
                    data: series.games,
                    backgroundColor: barStyle.backgroundColor,
                    borderColor: barStyle.borderColor,
                    borderWidth: barStyle.borderWidth
                }]
            },
            options: chartOptions({
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
                                var goals = series.goalValues[items[0].dataIndex];
                                return goals + ' goal' + (goals === 1 ? '' : 's') + ' scored';
                            },
                            label: function (item) {
                                return item.parsed.y + ' game' + (item.parsed.y === 1 ? '' : 's');
                            },
                            afterLabel: function () {
                                return isRival
                                    ? 'Click to filter games list by goals conceded'
                                    : 'Click to filter games list';
                            }
                        }
                    })
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Goals scored',
                            color: T.tickColor()
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
                    if (!elements.length) {
                        return;
                    }
                    var idx = elements[0].index;
                    if (!series.games[idx]) {
                        return;
                    }
                    window.location.href = gamesListUrl(
                        playerId,
                        series.goalValues[idx],
                        opponentId || null,
                        goalParam
                    );
                },
                onHover: function (evt, elements) {
                    if (!elements.length || !series.games[elements[0].index]) {
                        canvas.style.cursor = 'default';
                        return;
                    }
                    canvas.style.cursor = 'pointer';
                }
            })
        });
        root._k2GoalsHistogramChart = chartInstance;
    }

    function renderGroupedH2hChart(root, subjectBuckets, rivalBuckets, subjectData, rivalData) {
        var playerId = root.getAttribute('data-player-id');
        var opponentId = root.getAttribute('data-opponent-id') || '';
        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-goals-scored-histogram-status');
        var chartInstance = root._k2GoalsHistogramChart || null;

        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (!subjectBuckets.length && !rivalBuckets.length) {
            if (chartInstance) {
                chartInstance.destroy();
                root._k2GoalsHistogramChart = null;
            }
            if (status) {
                status.textContent = 'No rated games against this opponent.';
            }
            return;
        }

        if (status) {
            status.textContent = '';
        }

        var subjectSeries = bucketsToSeries(subjectBuckets);
        var rivalSeries = bucketsToSeries(rivalBuckets);
        var subjectStyle = barStyleForSide(true, false);
        var rivalStyle = barStyleForSide(true, true);
        var subjectLabel = (subjectData && subjectData.playerName) ? subjectData.playerName : 'You';
        var rivalLabel = (rivalData && rivalData.playerName) ? rivalData.playerName : 'Opponent';
        var goalValues = subjectSeries.goalValues;

        if (chartInstance) {
            chartInstance.destroy();
            root._k2GoalsHistogramChart = null;
        }

        chartInstance = createChart(canvas, {
            type: 'bar',
            data: {
                labels: subjectSeries.labels,
                datasets: [
                    {
                        label: subjectLabel,
                        data: subjectSeries.games,
                        backgroundColor: subjectStyle.backgroundColor,
                        borderColor: subjectStyle.borderColor,
                        borderWidth: subjectStyle.borderWidth,
                        maxBarThickness: 28
                    },
                    {
                        label: rivalLabel,
                        data: rivalSeries.games,
                        backgroundColor: rivalStyle.backgroundColor,
                        borderColor: rivalStyle.borderColor,
                        borderWidth: rivalStyle.borderWidth,
                        maxBarThickness: 28
                    }
                ]
            },
            options: chartOptions({
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'start',
                        labels: {
                            color: T.legendColor ? T.legendColor() : undefined,
                            boxWidth: 12,
                            boxHeight: 12,
                            usePointStyle: true,
                            pointStyle: 'rect'
                        }
                    },
                    tooltip: T.mergeTooltip({
                        mode: 'index',
                        intersect: true,
                        filter: function (item) {
                            return item.parsed.y > 0;
                        },
                        callbacks: {
                            title: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                var goals = goalValues[items[0].dataIndex];
                                return goals + ' goal' + (goals === 1 ? '' : 's') + ' scored';
                            },
                            label: function (item) {
                                return item.dataset.label + ': '
                                    + item.parsed.y + ' game' + (item.parsed.y === 1 ? '' : 's');
                            },
                            afterBody: function () {
                                return 'Click a bar to filter the games list';
                            }
                        }
                    })
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Goals scored',
                            color: T.tickColor()
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
                    if (!elements.length) {
                        return;
                    }
                    var el = elements[0];
                    var idx = el.index;
                    var datasetIndex = el.datasetIndex;
                    var games = datasetIndex === 1 ? rivalSeries.games : subjectSeries.games;
                    if (!games[idx]) {
                        return;
                    }
                    window.location.href = gamesListUrl(
                        playerId,
                        goalValues[idx],
                        opponentId || null,
                        datasetIndex === 1 ? 'ga' : 'gf'
                    );
                },
                onHover: function (evt, elements) {
                    if (!elements.length) {
                        canvas.style.cursor = 'default';
                        return;
                    }
                    var el = elements[0];
                    var games = el.datasetIndex === 1 ? rivalSeries.games : subjectSeries.games;
                    if (!games[el.index]) {
                        canvas.style.cursor = 'default';
                        return;
                    }
                    canvas.style.cursor = 'pointer';
                }
            })
        });
        root._k2GoalsHistogramChart = chartInstance;
    }

    function setLoading(root) {
        var status = root.querySelector('.player-goals-scored-histogram-status');
        if (status) {
            status.textContent = 'Loading goals per game…';
        }
    }

    function setError(root) {
        var status = root.querySelector('.player-goals-scored-histogram-status');
        if (status) {
            status.textContent = 'Could not load goals per game.';
        }
    }

    function initStandalone(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        setLoading(root);

        fetchDistribution(playerId, null)
            .then(function (data) {
                renderStandaloneChart(root, data.buckets || [], data);
            })
            .catch(function () {
                setError(root);
            });
    }

    function initH2hMatchups(matchups) {
        var subjectRoot = matchups.querySelector('.player-goals-scored-histogram[data-h2h-side="subject"]');
        var rivalRoot = matchups.querySelector('.player-goals-scored-histogram[data-h2h-side="rival"]');
        var groupedRoot = matchups.querySelector('.player-goals-scored-histogram[data-h2h-grouped="1"]');
        if (!subjectRoot || !rivalRoot) {
            return false;
        }

        var playerId = subjectRoot.getAttribute('data-player-id');
        var opponentId = subjectRoot.getAttribute('data-opponent-id');
        if (!playerId || !opponentId) {
            return false;
        }

        setLoading(subjectRoot);
        setLoading(rivalRoot);
        if (groupedRoot) {
            setLoading(groupedRoot);
        }

        Promise.all([
            fetchDistribution(playerId, opponentId),
            fetchDistribution(opponentId, playerId)
        ])
            .then(function (results) {
                var subjectData = results[0];
                var rivalData = results[1];
                var sharedMax = Math.max(
                    distributionMaxGoals(subjectData),
                    distributionMaxGoals(rivalData)
                );

                if (sharedMax < 0) {
                    sharedMax = 0;
                }

                var subjectBuckets = padBuckets(subjectData.buckets || [], sharedMax);
                var rivalBuckets = padBuckets(rivalData.buckets || [], sharedMax);

                renderH2hSingleChart(subjectRoot, subjectBuckets, subjectData);
                renderH2hSingleChart(rivalRoot, rivalBuckets, rivalData);
                if (groupedRoot) {
                    renderGroupedH2hChart(
                        groupedRoot,
                        subjectBuckets,
                        rivalBuckets,
                        subjectData,
                        rivalData
                    );
                }
            })
            .catch(function () {
                setError(subjectRoot);
                setError(rivalRoot);
                if (groupedRoot) {
                    setError(groupedRoot);
                }
            });

        return true;
    }

    function boot() {
        var matchupsBlocks = document.querySelectorAll('.pm3d-matchups');
        var handled = typeof Set !== 'undefined' ? new Set() : null;
        var i;

        for (i = 0; i < matchupsBlocks.length; i++) {
            if (initH2hMatchups(matchupsBlocks[i])) {
                var charts = matchupsBlocks[i].querySelectorAll('.player-goals-scored-histogram');
                var j;
                for (j = 0; j < charts.length; j++) {
                    if (handled) {
                        handled.add(charts[j]);
                    }
                }
            }
        }

        var roots = document.querySelectorAll('.player-goals-scored-histogram');
        for (i = 0; i < roots.length; i++) {
            if (handled && handled.has(roots[i])) {
                continue;
            }
            initStandalone(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
