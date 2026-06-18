/**
 * Games per calendar month (Chart.js bar + time scale).
 * Expects api/player_games_by_month.php and chartjs-adapter-date-fns.
 * Click a month bar → Games tab filtered to that calendar month.
 */
(function () {
    'use strict';

    var T = window.K2ChartTheme;
    var DR = window.K2ChartDateRange;

    var API_PATH = '/api/player_games_by_month.php';

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

    function monthAnchorFromDate(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1).padStart(2, '0');
        return y + '-' + m + '-01';
    }

    function monthGamesUrl(playerId, date) {
        return '/player/games.php?id=' + encodeURIComponent(String(playerId))
            + '&from=profile-games-chart&period=month&anchor='
            + monthAnchorFromDate(date) + '#day-games';
    }

    function formatMonthTitle(date) {
        return date.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'long'
        });
    }

    function pickMonthBarElement(chart, evt, elements) {
        var CT = window.K2CoarseTap;
        if (CT && CT.pickBarElement) {
            var picked = CT.pickBarElement(chart, evt, elements);
            if (picked) {
                return picked;
            }
        } else if (elements && elements.length) {
            return elements[0];
        }
        if (!chart || !evt || typeof chart.getElementsAtEventForMode !== 'function') {
            return null;
        }
        var alongX = chart.getElementsAtEventForMode(
            evt,
            'nearest',
            { intersect: false, axis: 'x' },
            false
        );
        if (alongX.length) {
            return alongX[0];
        }
        return null;
    }

    function monthBarPayload(chart, el, chartData) {
        if (!el) {
            return null;
        }
        var pt = chartData[el.index];
        if (pt && pt.y > 0 && pt.x) {
            return { date: pt.x, games: pt.y };
        }
        if (chart) {
            var meta = chart.getDatasetMeta(el.datasetIndex);
            var bar = meta && meta.data ? meta.data[el.index] : null;
            var parsed = bar && bar.$context ? bar.$context.parsed : null;
            if (parsed && parsed.y > 0 && parsed.x != null) {
                return { date: new Date(parsed.x), games: parsed.y };
            }
        }
        return null;
    }

    function bindMonthChartClick(chart, canvas, playerId, chartData) {
        if (!chart) {
            return;
        }

        var CT = window.K2CoarseTap;

        function navigateMonth(el) {
            var payload = monthBarPayload(chart, el, chartData);
            if (!payload) {
                return;
            }
            window.location.href = monthGamesUrl(playerId, payload.date);
        }

        var directClick = function (evt, elements) {
            var el = pickMonthBarElement(chart, evt, elements);
            navigateMonth(el);
        };

        if (!CT) {
            chart.options.onClick = directClick;
            return;
        }

        chart.options.onClick = CT.createChartClickHandler({
            scopeId: 'profile-games-month-' + playerId,
            chart: chart,
            canvas: canvas,
            pickElement: pickMonthBarElement,
            pinKey: function (el) {
                return String(el.index);
            },
            isActive: function (el) {
                return !!monthBarPayload(chart, el, chartData);
            },
            getTitle: function (el) {
                var payload = monthBarPayload(chart, el, chartData);
                return payload ? formatMonthTitle(payload.date) : '';
            },
            getBody: function (el) {
                var payload = monthBarPayload(chart, el, chartData);
                if (!payload) {
                    return '';
                }
                var n = payload.games;
                return n + ' game' + (n === 1 ? '' : 's');
            },
            onNavigate: navigateMonth
        });
    }

    function initRoot(root) {
        var playerId = root.getAttribute('data-player-id');
        if (!playerId) {
            return;
        }

        var canvas = root.querySelector('canvas');
        var status = root.querySelector('.player-games-month-chart-status');
        if (!canvas || typeof Chart === 'undefined') {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return;
        }

        if (status) {
            status.textContent = 'Loading games per month…';
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
                var months = data.months || [];
                if (!months.length) {
                    if (status) {
                        status.textContent = 'No rated games to chart.';
                    }
                    return;
                }

                var padded;
                if (DR && DR.padGamesPerMonth) {
                    padded = DR.padGamesPerMonth(months);
                } else {
                    var fallbackData = [];
                    for (var fi = 0; fi < months.length; fi++) {
                        var fx = DR && DR.monthToDate
                            ? DR.monthToDate(months[fi].month)
                            : new Date(months[fi].month + '-01T00:00:00');
                        if (!isNaN(fx.getTime())) {
                            fallbackData.push({ x: fx, y: months[fi].games });
                        }
                    }
                    padded = { chartData: fallbackData, xMin: null, xMax: null };
                }
                var chartData = padded.chartData;

                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No chartable months in game history.';
                    }
                    return;
                }

                if (status) {
                    status.textContent = '';
                }

                var chart = createChart(canvas, {
                    type: 'bar',
                    data: {
                        datasets: [{
                            label: 'Games',
                            data: chartData,
                            backgroundColor: function (ctx) {
                                return ctx.parsed.y === 0
                                    ? 'transparent'
                                    : T.fill(T.pitch(), 0.65);
                            },
                            borderColor: function (ctx) {
                                return ctx.parsed.y === 0 ? 'transparent' : T.pitch();
                            },
                            borderWidth: function (ctx) {
                                return ctx.parsed.y === 0 ? 0 : 1;
                            }
                        }]
                    },
                    options: chartOptions(Object.assign({}, T && T.careerChartGutterOptions ? T.careerChartGutterOptions() : {}, {
                        interaction: {
                            mode: 'nearest',
                            intersect: false,
                            axis: 'x'
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
                                        var d = new Date(items[0].parsed.x);
                                        if (isNaN(d.getTime())) {
                                            return '';
                                        }
                                        return d.toLocaleDateString(undefined, {
                                            year: 'numeric',
                                            month: 'long'
                                        });
                                    },
                                    label: function (item) {
                                        return item.parsed.y + ' game' + (item.parsed.y === 1 ? '' : 's');
                                    },
                                    afterLabel: function () {
                                        return 'Click to view games in this month';
                                    }
                                }
                            })
                        },
                        scales: {
                            x: {
                                type: 'time',
                                min: DR && DR.profileCareerTimeRange
                                    ? DR.profileCareerTimeRange().xMin
                                    : (padded.xMin || undefined),
                                max: DR && DR.profileCareerTimeRange
                                    ? DR.profileCareerTimeRange().xMax
                                    : (padded.xMax || undefined),
                                offset: false,
                                time: {
                                    displayFormats: {
                                        year: 'yyyy',
                                        month: 'MMM yyyy',
                                        day: 'MMM d, yyyy'
                                    }
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    maxRotation: 45,
                                    minRotation: 0,
                                    autoSkip: true,
                                    maxTicksLimit: 12
                                },
                                grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                            },
                            y: T && T.careerChartYAxisOptions ? T.careerChartYAxisOptions({
                                beginAtZero: true,
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 0
                                },
                                grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                            }) : {
                                beginAtZero: true,
                                ticks: {
                                    color: T.tickColor(),
                                    precision: 0
                                },
                                grid: { color: T.softGrid ? T.softGrid() : T.grid() }
                            }
                        },
                        onHover: function (evt, elements) {
                            var liveChart = typeof Chart !== 'undefined' && Chart.getChart
                                ? Chart.getChart(canvas)
                                : null;
                            var el = pickMonthBarElement(liveChart, evt, elements);
                            var payload = monthBarPayload(liveChart, el, chartData);
                            canvas.style.cursor = payload ? 'pointer' : 'default';
                        }
                    }))
                });
                bindMonthChartClick(chart, canvas, playerId, chartData);
            })
            .catch(function () {
                if (status) {
                    status.textContent = 'Could not load games per month.';
                }
            });
    }

    function boot() {
        var roots = document.querySelectorAll('.player-games-month-chart');
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
