/**
 * Amiga Activity charts — single module for the /amiga/activity/ wings.
 * Panels register in PANELS (slices 1+); drain() mounts them sequentially.
 * Time travel: every API fetch carries the page's `as=` cutoff — never
 * with-player params. See docs/amiga-activity-charts-policy.md.
 */
(function (global) {
    'use strict';

    var T = global.K2ChartTheme;
    var GAP_MS = 100;
    var FRAME_OPTS = { maintainAspectRatio: false };
    var ERRORS = [];

    function noteError(where, err) {
        ERRORS.push(where + ': ' + String((err && err.message) || err));
        if (typeof console !== 'undefined' && console.error) {
            console.error('amiga-activity-charts ' + where, err);
        }
    }

    function currentAsParam() {
        try {
            return new URLSearchParams(global.location.search).get('as') || '';
        } catch (e) {
            return '';
        }
    }

    /** Fetch an Activity API as JSON, carrying the active `as=` cutoff. */
    function fetchJson(apiPath, query) {
        var q = query || '';
        if (q && q.charAt(0) !== '?') {
            q = '?' + q;
        }
        var as = currentAsParam();
        if (as && q.indexOf('as=') === -1) {
            q += (q ? '&' : '?') + 'as=' + encodeURIComponent(as);
        }
        return fetch(apiPath + q, { credentials: 'same-origin' }).then(function (r) {
            if (!r.ok) {
                throw new Error('bad_status');
            }
            return r.json();
        });
    }

    function yearToDate(year) {
        var y = parseInt(year, 10);
        if (!y || y < 1000) {
            return null;
        }
        var d = new Date(y + '-01-01T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function chartCanvas(root) {
        var frame = root.querySelector('.k2-chart-frame');
        if (frame) {
            return frame.querySelector('canvas');
        }
        return root.querySelector('canvas');
    }

    function resizeChart(canvas) {
        if (T && typeof T.resizeActivityChart === 'function') {
            T.resizeActivityChart(canvas);
            return;
        }
        if (!canvas || typeof Chart === 'undefined' || typeof Chart.getChart !== 'function') {
            return;
        }
        var instance = Chart.getChart(canvas);
        if (instance && typeof instance.resize === 'function') {
            instance.resize(0);
        }
    }

    function requireCanvas(root, status) {
        var canvas = chartCanvas(root);
        if (!canvas || typeof Chart === 'undefined' || !T) {
            if (status) {
                status.textContent = 'Chart library failed to load.';
            }
            return null;
        }
        return canvas;
    }

    function chartOptions(extra, chartKind) {
        return T.activityChartOptions(Object.assign({}, FRAME_OPTS, extra || {}), {
            chartKind: chartKind || 'none'
        });
    }

    function createChart(canvas, config, chartKind) {
        return T.createActivityChart(canvas, config, chartKind || 'none');
    }

    function scaleYCount() {
        return {
            beginAtZero: true,
            ticks: {
                color: T.tickColor(),
                precision: 0
            },
            grid: { color: T.softGrid() }
        };
    }

    function formatCount(value) {
        if (value == null) {
            return '';
        }
        return Number(value).toLocaleString();
    }

    function parseEventDate(dateStr) {
        if (!dateStr || dateStr.length < 10) {
            return null;
        }
        var d = new Date(dateStr.substring(0, 10) + 'T00:00:00');
        return isNaN(d.getTime()) ? null : d;
    }

    function formatEventDate(d) {
        if (!d || isNaN(d.getTime())) {
            return '';
        }
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function panelStatus(root) {
        return root.querySelector('.k2-chart-panel__status');
    }

    function tone(name) {
        return T[name] ? T[name]() : T.pitch();
    }

    /** Tooltip footer marking the honestly-partial cutoff year under time travel. */
    function partialYearFooter(cutoff, labels) {
        return function (items) {
            if (!cutoff || !cutoff.partial_year || !items.length) {
                return '';
            }
            if (labels[items[0].dataIndex] !== String(cutoff.partial_year)) {
                return '';
            }
            var d = parseEventDate(cutoff.event_date);
            return 'Partial year — through ' + (d ? formatEventDate(d) : cutoff.label);
        };
    }

    function scaleXCategory() {
        return {
            ticks: {
                color: T.tickColor(),
                maxRotation: 45,
                autoSkip: true
            },
            grid: { color: T.grid() }
        };
    }

    function scaleYCountFormatted() {
        return {
            beginAtZero: true,
            ticks: {
                color: T.tickColor(),
                precision: 0,
                callback: function (value) {
                    return formatCount(value);
                }
            },
            grid: { color: T.grid() }
        };
    }

    function formatRateValue(value, spec) {
        if (value == null || isNaN(value)) {
            return 'No data';
        }
        if (spec.format === 'percent') {
            return (value * 100).toFixed(1) + '% of games';
        }
        if (spec.format === 'per100') {
            return (value * 100).toFixed(1) + ' per 100 games';
        }
        var decimals = spec.decimals != null ? spec.decimals : 2;
        return value.toFixed(decimals) + ' ' + spec.noun;
    }

    function rateAxisTick(value, spec) {
        if (spec.format === 'percent' || spec.format === 'per100') {
            return (value * 100).toFixed(1);
        }
        if (spec.decimals != null) {
            return value.toFixed(spec.decimals);
        }
        return value;
    }

    function rateTooltipFooter(cutoff, labels, reference, spec) {
        return function (items) {
            var lines = [];
            var partial = partialYearFooter(cutoff, labels)(items);
            if (partial) {
                lines.push(partial);
            }
            if (reference != null && !isNaN(reference)) {
                lines.push('All-time avg: ' + formatRateValue(reference, spec));
            }
            return lines.join('\n');
        };
    }

    function scaleYRate(spec) {
        return {
            beginAtZero: true,
            ticks: {
                color: T.tickColor(),
                callback: function (value) {
                    return rateAxisTick(value, spec);
                }
            },
            grid: { color: T.grid() }
        };
    }

    function ghostBarStroke() {
        var muted = T.textMuted();
        return {
            backgroundColor: T.fill(muted, 0.12),
            borderColor: T.fill(muted, 0.35),
            borderWidth: T.barBorderWidth()
        };
    }

    function renderGhostYearBar(canvas, labels, frontValues, ghostValues, spec, cutoff) {
        createChart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    Object.assign({
                        ghost: true,
                        label: spec.ghostLabel,
                        data: ghostValues,
                        order: 2,
                        barPercentage: 1.0,
                        categoryPercentage: 0.82
                    }, ghostBarStroke()),
                    Object.assign({
                        label: spec.label,
                        data: frontValues,
                        order: 1,
                        barPercentage: 0.58,
                        categoryPercentage: 0.82
                    }, T.barStroke(tone(spec.tone)))
                ]
            },
            options: chartOptions({
                plugins: {
                    legend: { labels: { color: T.textMuted() } },
                    tooltip: T.mergeTooltip({
                        callbacks: {
                            label: function (item) {
                                if (item.parsed.y == null) {
                                    return 'No data';
                                }
                                if (item.dataset.ghost) {
                                    return formatCount(item.parsed.y) + ' ' + spec.ghostNoun;
                                }
                                return formatCount(item.parsed.y) + ' ' + spec.noun;
                            },
                            footer: partialYearFooter(cutoff, labels)
                        }
                    })
                },
                scales: {
                    x: scaleXCategory(),
                    y: scaleYCountFormatted()
                }
            }, 'bar')
        }, 'bar');
    }

    function renderYearBar(canvas, labels, values, spec, cutoff) {
        createChart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [Object.assign({
                    label: spec.label,
                    data: values
                }, T.barStroke(tone(spec.tone)))]
            },
            options: chartOptions({
                plugins: {
                    legend: { labels: { color: T.textMuted() } },
                    tooltip: T.mergeTooltip({
                        callbacks: {
                            label: function (item) {
                                if (item.parsed.y == null) {
                                    return 'No data';
                                }
                                if (spec.decimals != null) {
                                    return item.parsed.y.toFixed(spec.decimals) + ' ' + spec.noun;
                                }
                                return formatCount(item.parsed.y) + ' ' + spec.noun;
                            },
                            footer: partialYearFooter(cutoff, labels)
                        }
                    })
                },
                scales: {
                    x: scaleXCategory(),
                    y: spec.decimals != null ? scaleYRate(spec) : scaleYCountFormatted()
                }
            }, 'bar')
        }, 'bar');
    }

    function renderBreakdownYearBar(canvas, labels, values, spec, cutoff, breakdownByYear, countLabelFn) {
        var coarse = T.isCoarsePointer && T.isCoarsePointer();
        var hasBreakdown = breakdownByYear && Object.keys(breakdownByYear).length > 0;
        var useRichTooltip = hasBreakdown && !coarse;
        var tooltipConfig = useRichTooltip
            ? T.mergeTooltip({
                enabled: false,
                external: bindGeoBreakdownExternalTooltip(labels, breakdownByYear, spec, cutoff, countLabelFn)
            })
            : T.mergeTooltip({
                callbacks: {
                    label: function (item) {
                        if (item.parsed.y == null) {
                            return 'No data';
                        }
                        return formatCount(item.parsed.y) + ' ' + spec.noun;
                    },
                    footer: partialYearFooter(cutoff, labels)
                }
            });
        createChart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [Object.assign({
                    label: spec.label,
                    data: values
                }, T.barStroke(tone(spec.tone)))]
            },
            options: chartOptions({
                plugins: {
                    legend: { labels: { color: T.textMuted() } },
                    tooltip: tooltipConfig
                },
                scales: {
                    x: scaleXCategory(),
                    y: scaleYCountFormatted()
                }
            }, 'bar')
        }, 'bar');
    }

    function renderYearRateBar(canvas, labels, values, spec, cutoff, reference, overlay) {
        var datasets = [Object.assign({
            type: 'bar',
            label: spec.label,
            data: values,
            order: 3
        }, T.barStroke(tone(spec.tone)))];
        if (overlay && overlay.values && overlay.values.length) {
            datasets.push({
                type: 'line',
                label: overlay.label || 'Overlay',
                data: overlay.values,
                borderColor: T.textMuted(),
                backgroundColor: 'transparent',
                borderWidth: 1.5,
                borderDash: [6, 4],
                pointRadius: 0,
                pointHitRadius: 6,
                fill: false,
                order: 1
            });
        }
        if (reference != null && !isNaN(reference)) {
            datasets.push({
                type: 'line',
                label: 'All-time average',
                data: labels.map(function () {
                    return reference;
                }),
                borderColor: T.textMuted(),
                backgroundColor: 'transparent',
                borderWidth: 1.5,
                borderDash: [6, 4],
                pointRadius: 0,
                pointHitRadius: 0,
                fill: false,
                order: 2
            });
        }
        var hasOverlay = overlay && overlay.values && overlay.values.length;
        createChart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: chartOptions({
                plugins: {
                    legend: { labels: { color: T.textMuted() } },
                    tooltip: T.mergeTooltip({
                        filter: hasOverlay ? undefined : function (item) {
                            return item.dataset.type === 'bar';
                        },
                        callbacks: {
                            label: function (item) {
                                if (item.parsed.y == null) {
                                    return 'No data';
                                }
                                if (item.dataset.type === 'line') {
                                    return item.dataset.label + ': ' + formatRateValue(item.parsed.y, spec);
                                }
                                return formatRateValue(item.parsed.y, spec);
                            },
                            footer: rateTooltipFooter(cutoff, labels, reference, spec)
                        }
                    })
                },
                scales: {
                    x: scaleXCategory(),
                    y: spec.decimals != null || spec.format ? scaleYRate(spec) : scaleYCountFormatted()
                }
            }, 'bar')
        }, 'bar');
    }

    /** L1 year bars from the year_facts API (realm slice). */
    function mountYearFacts(root, spec) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson('/api/amiga_community_year_facts.php', (spec.slice ? 'slice=' + encodeURIComponent(spec.slice) + '&' : '') + 'metric=' + encodeURIComponent(spec.metric))
            .then(function (data) {
                var years = data.years || [];
                var series = (data.series && data.series[0] && data.series[0].values) || [];
                if (!years.length || !series.length) {
                    if (status) {
                        status.textContent = 'No data to chart.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                renderYearBar(canvas, years.map(String), series, spec, data.cutoff);
            })
            .catch(function (err) {
                noteError(spec.metric || spec.rate || 'panel', err);
                if (status) {
                    status.textContent = 'Could not load this chart.';
                }
            });
    }

    /** L1 year bars — distinct nationalities with per-country active-player tooltip. */
    function mountNationalitiesYear(root, spec) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson('/api/amiga_community_year_facts.php', 'slice=realm&metric=distinct_nationalities')
            .then(function (data) {
                var years = data.years || [];
                var series = (data.series && data.series[0] && data.series[0].values) || [];
                if (!years.length || !series.length) {
                    if (status) {
                        status.textContent = 'No data to chart.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                renderBreakdownYearBar(
                    canvas,
                    years.map(String),
                    series,
                    spec,
                    data.cutoff,
                    data.nationality_active_by_year || null,
                    nationalitiesActivePlayerLabel
                );
            })
            .catch(function (err) {
                noteError(spec.metric || 'distinct_nationalities', err);
                if (status) {
                    status.textContent = 'Could not load this chart.';
                }
            });
    }

    function mountWcYearWithNationalityBreakdown(root, spec, metric) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson(
            '/api/amiga_community_year_facts.php',
            'slice=world_cup&metric=' + encodeURIComponent(metric)
        )
            .then(function (data) {
                var years = data.years || [];
                var series = (data.series && data.series[0] && data.series[0].values) || [];
                if (!years.length || !series.length) {
                    if (status) {
                        status.textContent = 'No data to chart.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                renderBreakdownYearBar(
                    canvas,
                    years.map(String),
                    series,
                    spec,
                    data.cutoff,
                    data.wc_nationality_active_by_year || null,
                    wcNationalityPlayerLabel
                );
            })
            .catch(function (err) {
                noteError(spec.metric || metric, err);
                if (status) {
                    status.textContent = 'Could not load this chart.';
                }
            });
    }

    function mountWcNationsYear(root, spec) {
        return mountWcYearWithNationalityBreakdown(root, spec, 'distinct_nationalities');
    }

    function mountWcPlayersYear(root, spec) {
        return mountWcYearWithNationalityBreakdown(root, spec, 'active_players');
    }

    /** L1 year bars — realm metric with per-host-country event breakdown tooltip. */
    function mountRealmYearWithHostBreakdown(root, spec, metric) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson(
            '/api/amiga_community_year_facts.php',
            'slice=realm&metric=' + encodeURIComponent(metric)
        )
            .then(function (data) {
                var years = data.years || [];
                var series = (data.series && data.series[0] && data.series[0].values) || [];
                if (!years.length || !series.length) {
                    if (status) {
                        status.textContent = 'No data to chart.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                renderBreakdownYearBar(
                    canvas,
                    years.map(String),
                    series,
                    spec,
                    data.cutoff,
                    data.host_tournaments_by_year || null,
                    hostEventsHostedLabel
                );
            })
            .catch(function (err) {
                noteError(spec.metric || metric, err);
                if (status) {
                    status.textContent = 'Could not load this chart.';
                }
            });
    }

    function mountHostCountriesYear(root, spec) {
        return mountRealmYearWithHostBreakdown(root, spec, 'distinct_host_countries');
    }

    function mountTournamentsYear(root, spec) {
        return mountRealmYearWithHostBreakdown(root, spec, 'tournaments');
    }

    /** L3 derived rate bars from the year_rates API. */
    function mountYearRate(root, spec) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson('/api/amiga_community_year_rates.php', 'rate=' + encodeURIComponent(spec.rate))
            .then(function (data) {
                var years = data.years || [];
                var values = data.values || [];
                if (!years.length || !values.length) {
                    if (status) {
                        status.textContent = 'No data to chart.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                renderYearRateBar(canvas, years.map(String), values, spec, data.cutoff, data.reference, data.overlay);
            })
            .catch(function (err) {
                noteError(spec.metric || spec.rate || 'panel', err);
                if (status) {
                    status.textContent = 'Could not load this chart.';
                }
            });
    }

    function histogramPopulationUnit(count, label) {
        var n = Number(count);
        if (label === 'games') {
            return n === 1 ? 'game' : 'games';
        }
        if (label === 'tournaments') {
            return n === 1 ? 'tournament' : 'tournaments';
        }
        return n === 1 ? 'player' : 'players';
    }

    function renderHistogramBar(canvas, buckets, spec, population, populationLabel) {
        var labels = [];
        var counts = [];
        var i;
        for (i = 0; i < buckets.length; i++) {
            labels.push(String(buckets[i].label));
            counts.push(buckets[i].count);
        }
        var popTotal = Number(population) || 0;
        createChart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [Object.assign({
                    label: spec.label,
                    data: counts
                }, T.barStroke(tone(spec.tone)))]
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
                                return items[0].label;
                            },
                            label: function (item) {
                                var count = item.parsed.y;
                                return formatCount(count) + ' ' + histogramPopulationUnit(count, populationLabel);
                            },
                            afterLabel: function (item) {
                                if (!popTotal) {
                                    return '';
                                }
                                var pct = (item.parsed.y / popTotal) * 100;
                                return pct.toFixed(1) + '% of ' + popTotal.toLocaleString() + ' ' + histogramPopulationUnit(popTotal, populationLabel);
                            }
                        }
                    })
                },
                scales: {
                    x: {
                        ticks: {
                            color: T.tickColor(),
                            maxRotation: spec.dense ? 90 : 45,
                            minRotation: 0,
                            autoSkip: !spec.dense,
                            maxTicksLimit: spec.dense ? 40 : 18
                        },
                        grid: { display: false }
                    },
                    y: scaleYCountFormatted()
                }
            }, 'bar')
        }, 'bar');
    }

    /** Shape wing histogram from the histogram API. */
    function mountHistogram(root, spec) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson('/api/amiga_community_histogram.php', 'kind=' + encodeURIComponent(spec.kind))
            .then(function (data) {
                var buckets = data.buckets || [];
                var population = data.population || 0;
                if (!buckets.length || !population) {
                    if (status) {
                        status.textContent = 'No data to chart.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                renderHistogramBar(canvas, buckets, spec, population, data.population_label || 'players');
            })
            .catch(function (err) {
                noteError(spec.kind || 'histogram', err);
                if (status) {
                    status.textContent = 'Could not load this chart.';
                }
            });
    }

    /** WC games per year with realm games as muted ghost bars behind (Q-WC-001). */
    function mountWcGamesGhostYear(root, spec) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return Promise.all([
            fetchJson('/api/amiga_community_year_facts.php', 'slice=world_cup&metric=games'),
            fetchJson('/api/amiga_community_year_facts.php', 'slice=realm&metric=games')
        ]).then(function (results) {
            var wcData = results[0];
            var realmData = results[1];
            var years = wcData.years || [];
            var wcValues = (wcData.series && wcData.series[0] && wcData.series[0].values) || [];
            var ghostValues = (realmData.series && realmData.series[0] && realmData.series[0].values) || [];
            if (!years.length || !wcValues.length || !ghostValues.length) {
                if (status) {
                    status.textContent = 'No data to chart.';
                }
                return;
            }
            if (status) {
                status.textContent = '';
            }
            renderGhostYearBar(canvas, years.map(String), wcValues, ghostValues, spec, wcData.cutoff || realmData.cutoff);
        }).catch(function (err) {
            noteError('wc-games-ghost', err);
            if (status) {
                status.textContent = 'Could not load this chart.';
            }
        });
    }

    /** L2 cumulative event-timeline lines: every point is a real tournament. */
    var CUMULATIVE_HTML_TOOLTIP_ID = 'k2-amiga-act-cumulative-tooltip';
    var GEO_BREAKDOWN_HTML_TOOLTIP_ID = 'k2-amiga-act-geo-breakdown-tooltip';
    var TOURNAMENT_PAGE_FRAGMENT = 'tournament';

    function tournamentChartClickUrl(tournamentId) {
        var url = '/amiga/tournament/event-stats.php?id=' + encodeURIComponent(tournamentId);
        var TT = global.K2AmigaTimeTravelUrl;
        if (TT && TT.navigationQuerySuffix) {
            url += TT.navigationQuerySuffix();
        }
        url += '#' + TOURNAMENT_PAGE_FRAGMENT;
        return url;
    }

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function getOrCreateCumulativeHtmlTooltip() {
        var el = document.getElementById(CUMULATIVE_HTML_TOOLTIP_ID);
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = CUMULATIVE_HTML_TOOLTIP_ID;
        el.className = 'k2-chart-html-tooltip k2-amiga-act-cumulative-tooltip';
        el.setAttribute('role', 'tooltip');
        el.hidden = true;
        document.body.appendChild(el);
        if (T.registerChartHtmlTooltipScrollDismiss) {
            T.registerChartHtmlTooltipScrollDismiss(function () {
                el.hidden = true;
                el.style.opacity = '0';
            });
        }
        return el;
    }

    function cumulativeTooltipFlagHtml(host) {
        if (!host) {
            return '';
        }
        return '<span class="k2-amiga-act-cumulative-tooltip__flag" aria-hidden="true">'
            + geoFlagImgHtml(host)
            + '</span>';
    }

    function cumulativeEventNoun(count, noun) {
        if (typeof noun === 'object' && noun !== null) {
            return Number(count) === 1 ? (noun.one || noun.other || '') : (noun.other || noun.one || '');
        }
        return noun;
    }

    function buildCumulativeTooltipHtml(point, total, spec) {
        var titleHtml = '<div class="k2-amiga-act-cumulative-tooltip__title">'
            + cumulativeTooltipFlagHtml(point.host)
            + '<span class="k2-amiga-act-cumulative-tooltip__name">' + escapeHtml(point.name || '') + '</span>'
            + '</div>';
        var bodyLines = [];
        var eventDate = formatEventDate(parseEventDate(point.date));
        if (eventDate) {
            bodyLines.push(escapeHtml(eventDate));
        }
        if (spec.eventNoun && point.eventDelta != null) {
            bodyLines.push(formatCount(point.eventDelta) + ' ' + cumulativeEventNoun(point.eventDelta, spec.eventNoun));
        }
        bodyLines.push('Total: ' + formatCount(total) + ' ' + spec.noun);
        var bodyHtml = bodyLines.map(function (line) {
            return '<div class="k2-amiga-act-cumulative-tooltip__line">' + line + '</div>';
        }).join('');
        return titleHtml + '<div class="k2-amiga-act-cumulative-tooltip__body">' + bodyHtml + '</div>';
    }

    function bindCumulativeExternalTooltip(meta, spec) {
        return function (context) {
            var tooltipEl = getOrCreateCumulativeHtmlTooltip();
            var tooltip = context.tooltip;
            if (!tooltip || tooltip.opacity === 0) {
                tooltipEl.hidden = true;
                return;
            }
            var items = tooltip.dataPoints || [];
            if (!items.length) {
                tooltipEl.hidden = true;
                return;
            }
            var point = meta[items[0].dataIndex];
            if (!point) {
                tooltipEl.hidden = true;
                return;
            }
            tooltipEl.innerHTML = buildCumulativeTooltipHtml(point, items[0].parsed.y, spec);
            tooltipEl.hidden = false;
            var canvas = context.chart.canvas;
            var rect = canvas.getBoundingClientRect();
            tooltipEl.style.left = (rect.left + tooltip.caretX) + 'px';
            tooltipEl.style.top = (rect.top + tooltip.caretY) + 'px';
            tooltipEl.style.opacity = '1';
        };
    }

    function getOrCreateGeoBreakdownHtmlTooltip() {
        var el = document.getElementById(GEO_BREAKDOWN_HTML_TOOLTIP_ID);
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = GEO_BREAKDOWN_HTML_TOOLTIP_ID;
        el.className = 'k2-chart-html-tooltip k2-amiga-act-nationalities-tooltip';
        el.setAttribute('role', 'tooltip');
        el.hidden = true;
        document.body.appendChild(el);
        if (T.registerChartHtmlTooltipScrollDismiss) {
            T.registerChartHtmlTooltipScrollDismiss(function () {
                el.hidden = true;
                el.style.opacity = '0';
            });
        }
        return el;
    }

    function nationalitiesActivePlayerLabel(count) {
        return formatCount(count) + ' active player' + (Number(count) === 1 ? '' : 's');
    }

    function wcNationalityPlayerLabel(count) {
        return formatCount(count) + ' player' + (Number(count) === 1 ? '' : 's');
    }

    function hostEventsHostedLabel(count) {
        return formatCount(count) + ' event' + (Number(count) === 1 ? '' : 's') + ' hosted';
    }

    function buildGeoBreakdownTooltipHtml(yearLabel, rows, total, spec, countLabelFn) {
        var titleHtml = '<div class="k2-amiga-act-nationalities-tooltip__title">'
            + escapeHtml(yearLabel)
            + '</div>';
        var summaryHtml = '<div class="k2-amiga-act-nationalities-tooltip__summary">'
            + formatCount(total) + ' ' + escapeHtml(spec.noun || '')
            + '</div>';
        var listHtml = rows.map(function (row) {
            return '<div class="k2-amiga-act-nationalities-tooltip__row">'
                + '<span class="k2-amiga-act-nationalities-tooltip__flag" aria-hidden="true">'
                + geoFlagImgHtml(row.key)
                + '</span>'
                + '<span class="k2-amiga-act-nationalities-tooltip__name">' + escapeHtml(row.key || '') + '</span>'
                + '<span class="k2-amiga-act-nationalities-tooltip__count">' + countLabelFn(row.count) + '</span>'
                + '</div>';
        }).join('');
        return titleHtml + summaryHtml + '<div class="k2-amiga-act-nationalities-tooltip__list">' + listHtml + '</div>';
    }

    function bindGeoBreakdownExternalTooltip(labels, breakdownByYear, spec, cutoff, countLabelFn) {
        return function (context) {
            var tooltipEl = getOrCreateGeoBreakdownHtmlTooltip();
            var tooltip = context.tooltip;
            if (!tooltip || tooltip.opacity === 0) {
                tooltipEl.hidden = true;
                return;
            }
            var items = tooltip.dataPoints || [];
            if (!items.length) {
                tooltipEl.hidden = true;
                return;
            }
            var idx = items[0].dataIndex;
            var yearLabel = labels[idx] || '';
            var rows = (breakdownByYear && breakdownByYear[yearLabel]) || [];
            if (!rows.length) {
                tooltipEl.hidden = true;
                return;
            }
            var total = items[0].parsed.y;
            if (cutoff && cutoff.partial_year && String(cutoff.partial_year) === String(yearLabel)) {
                yearLabel += ' (partial)';
            }
            tooltipEl.innerHTML = buildGeoBreakdownTooltipHtml(yearLabel, rows, total, spec, countLabelFn);
            tooltipEl.hidden = false;
            var canvas = context.chart.canvas;
            var rect = canvas.getBoundingClientRect();
            tooltipEl.style.left = (rect.left + tooltip.caretX) + 'px';
            tooltipEl.style.top = (rect.top + tooltip.caretY) + 'px';
            tooltipEl.style.opacity = '1';
        };
    }

    function mountCumulative(root, spec) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson('/api/amiga_community_snapshot_series.php', 'metric=' + encodeURIComponent(spec.metric))
            .then(function (data) {
                var points = data.points || [];
                var chartData = [];
                var meta = [];
                var prevValue = null;
                var i;
                for (i = 0; i < points.length; i++) {
                    var x = parseEventDate(points[i].date);
                    if (x === null || points[i].value == null) {
                        continue;
                    }
                    var value = points[i].value;
                    chartData.push({ x: x, y: value });
                    meta.push({
                        t: points[i].t,
                        date: points[i].date,
                        name: points[i].name,
                        host: points[i].host || '',
                        value: value,
                        eventDelta: prevValue != null ? value - prevValue : value
                    });
                    prevValue = value;
                }
                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No data to chart.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                var coarse = T.isCoarsePointer();
                var useRichTooltip = !!spec.richTooltip && !coarse;
                var tooltipConfig = useRichTooltip
                    ? T.mergeTooltip({
                        enabled: false,
                        external: bindCumulativeExternalTooltip(meta, spec)
                    })
                    : T.mergeTooltip({
                        callbacks: {
                            title: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                var point = meta[items[0].dataIndex];
                                return point ? point.name : '';
                            },
                            label: function (item) {
                                var point = meta[item.dataIndex];
                                var lines = [];
                                if (point) {
                                    lines.push(formatEventDate(parseEventDate(point.date)));
                                }
                                if (spec.eventNoun && point && point.eventDelta != null) {
                                    lines.push(formatCount(point.eventDelta) + ' ' + cumulativeEventNoun(point.eventDelta, spec.eventNoun));
                                }
                                lines.push('Total: ' + formatCount(item.parsed.y) + ' ' + spec.noun);
                                return lines;
                            }
                        }
                    });
                var chartInstance = createChart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [Object.assign({
                            label: spec.label,
                            data: chartData,
                            fill: true,
                            stepped: true,
                            pointRadius: 0,
                            pointHitRadius: 8
                        }, T.lineStroke(tone(spec.tone)))]
                    },
                    options: chartOptions({
                        interaction: { mode: 'nearest', intersect: false, axis: 'x' },
                        onClick: coarse ? undefined : function (event, elements) {
                            if (!elements.length) {
                                return;
                            }
                            var point = meta[elements[0].index];
                            if (!point || !point.t) {
                                return;
                            }
                            global.location.href = tournamentChartClickUrl(point.t);
                        },
                        onHover: coarse ? undefined : function (event, elements) {
                            var target = event && event.native ? event.native.target : canvas;
                            if (target) {
                                target.style.cursor = elements.length ? 'pointer' : 'default';
                            }
                        },
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: tooltipConfig
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    displayFormats: {
                                        year: 'yyyy',
                                        month: 'MMM yyyy',
                                        day: 'd MMM yyyy'
                                    }
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    maxRotation: 45,
                                    autoSkip: true,
                                    maxTicksLimit: 14
                                },
                                grid: { color: T.grid() }
                            },
                            y: scaleYCountFormatted()
                        }
                    }, 'line')
                }, 'line');
                if (!coarse && chartInstance) {
                    canvas.addEventListener('mouseleave', function () {
                        canvas.style.cursor = 'default';
                        var tip = document.getElementById(CUMULATIVE_HTML_TOOLTIP_ID);
                        if (tip) {
                            tip.hidden = true;
                            tip.style.opacity = '0';
                        }
                    });
                }
            })
            .catch(function (err) {
                noteError(spec.metric || spec.rate || 'panel', err);
                if (status) {
                    status.textContent = 'Could not load this chart.';
                }
            });
    }

    /** GEO-009 — cumulative distinct host countries; tooltip flags unlock events. */
    function mountHostCountriesCumulative(root, spec) {
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        return fetchJson('/api/amiga_community_snapshot_series.php', 'metric=' + encodeURIComponent(spec.metric))
            .then(function (data) {
                var points = data.points || [];
                var chartData = [];
                var meta = [];
                var prevValue = null;
                var i;
                for (i = 0; i < points.length; i++) {
                    var x = parseEventDate(points[i].date);
                    if (x === null || points[i].value == null) {
                        continue;
                    }
                    chartData.push({ x: x, y: points[i].value });
                    meta.push({
                        t: points[i].t,
                        date: points[i].date,
                        name: points[i].name,
                        value: points[i].value,
                        prevValue: prevValue
                    });
                    prevValue = points[i].value;
                }
                if (!chartData.length) {
                    if (status) {
                        status.textContent = 'No data to chart.';
                    }
                    return;
                }
                if (status) {
                    status.textContent = '';
                }
                var coarse = T.isCoarsePointer();
                createChart(canvas, {
                    type: 'line',
                    data: {
                        datasets: [Object.assign({
                            label: spec.label,
                            data: chartData,
                            fill: true,
                            stepped: true,
                            pointRadius: 0,
                            pointHitRadius: 8
                        }, T.lineStroke(tone(spec.tone)))]
                    },
                    options: chartOptions({
                        interaction: { mode: 'nearest', intersect: false, axis: 'x' },
                        onClick: coarse ? undefined : function (event, elements) {
                            if (!elements.length) {
                                return;
                            }
                            var point = meta[elements[0].index];
                            if (!point || !point.t) {
                                return;
                            }
                            global.location.href = tournamentChartClickUrl(point.t);
                        },
                        onHover: coarse ? undefined : function (event, elements) {
                            var target = event && event.native ? event.native.target : canvas;
                            if (target) {
                                target.style.cursor = elements.length ? 'pointer' : 'default';
                            }
                        },
                        plugins: {
                            legend: { labels: { color: T.textMuted() } },
                            tooltip: T.mergeTooltip({
                                callbacks: {
                                    title: function (items) {
                                        if (!items.length) {
                                            return '';
                                        }
                                        var point = meta[items[0].dataIndex];
                                        return point ? point.name : '';
                                    },
                                    label: function (item) {
                                        var point = meta[item.dataIndex];
                                        var lines = [];
                                        if (point) {
                                            lines.push(formatEventDate(parseEventDate(point.date)));
                                        }
                                        lines.push('Total: ' + formatCount(item.parsed.y) + ' host countries');
                                        if (point && point.prevValue != null && point.value > point.prevValue) {
                                            lines.push('New host country unlocked at this tournament');
                                        }
                                        return lines;
                                    }
                                }
                            })
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    displayFormats: {
                                        year: 'yyyy',
                                        month: 'MMM yyyy',
                                        day: 'd MMM yyyy'
                                    }
                                },
                                ticks: {
                                    color: T.tickColor(),
                                    maxRotation: 45,
                                    autoSkip: true,
                                    maxTicksLimit: 14
                                },
                                grid: { color: T.grid() }
                            },
                            y: scaleYCountFormatted()
                        }
                    }, 'line')
                }, 'line');
                if (!coarse) {
                    canvas.addEventListener('mouseleave', function () {
                        canvas.style.cursor = 'default';
                    });
                }
            })
            .catch(function (err) {
                noteError(spec.metric || 'host-countries-cumulative', err);
                if (status) {
                    status.textContent = 'Could not load this chart.';
                }
            });
    }

    /* --- Panel registry + sequential drain (activity-charts-v2 pattern) --- */

    var PANELS = [];
    var activePanelQueue = [];
    var panelsLoadComplete = false;
    var resizeListenerBound = false;
    var BAR_ENTRANCE_BUFFER_MS = 600;

    /** spec: { id, selector, loadTier?, run(root) -> Promise } — loadTier `deferred` = after other panels on this page. */
    function registerPanel(spec) {
        PANELS.push(spec);
    }

    function buildPanelQueue() {
        var queue = [];
        var deferred = [];
        var i;
        for (i = 0; i < PANELS.length; i++) {
            if (!document.querySelector(PANELS[i].selector)) {
                continue;
            }
            if (PANELS[i].loadTier === 'deferred') {
                deferred.push(PANELS[i]);
            } else {
                queue.push(PANELS[i]);
            }
        }
        return queue.concat(deferred);
    }

    /** Geography duel/race panels — re-fetch when country selection changes. */
    var geoPanelRefreshers = [];

    function registerGeoPanel(spec) {
        registerPanel({
            id: spec.id,
            selector: spec.selector,
            loadTier: spec.pattern === 'race' ? 'deferred' : undefined,
            run: function (root) {
                var mountFn = function () {
                    if (spec.pattern === 'duel') {
                        return mountGeoDuelYear(root, spec);
                    }
                    return mountGeoRace(root, spec);
                };
                if (!root.getAttribute('data-k2-geo-panel-bound')) {
                    root.setAttribute('data-k2-geo-panel-bound', '1');
                    geoPanelRefreshers.push({ pattern: spec.pattern, run: mountFn });
                }
                return mountFn();
            }
        });
    }

    function runPanel(spec) {
        var root = document.querySelector(spec.selector);
        if (!root) {
            return Promise.resolve();
        }
        return Promise.resolve(spec.run(root));
    }

    var resizeAllTimer;

    function resizeAll() {
        if (!panelsLoadComplete) {
            return;
        }
        if (resizeAllTimer) {
            clearTimeout(resizeAllTimer);
        }
        resizeAllTimer = setTimeout(function () {
            var queue = activePanelQueue.length ? activePanelQueue : buildPanelQueue();
            var i;
            for (i = 0; i < queue.length; i++) {
                var root = document.querySelector(queue[i].selector);
                if (root) {
                    resizeChart(chartCanvas(root));
                }
            }
        }, 120);
    }

    function bindWindowResize() {
        if (resizeListenerBound) {
            return;
        }
        resizeListenerBound = true;
        window.addEventListener('resize', resizeAll);
    }

    function finishPanelLoad() {
        setTimeout(function () {
            panelsLoadComplete = true;
            bindWindowResize();
        }, BAR_ENTRANCE_BUFFER_MS);
    }

    function drain(index) {
        if (index === 0) {
            activePanelQueue = buildPanelQueue();
        }
        if (index >= activePanelQueue.length) {
            finishPanelLoad();
            return;
        }
        runPanel(activePanelQueue[index]).finally(function () {
            setTimeout(function () {
                drain(index + 1);
            }, GAP_MS);
        });
    }

    /* --- Growth wing (slice 1) --- */

    registerPanel({
        id: 'games-year',
        selector: '.amiga-act-games-year-chart',
        run: function (root) {
            return mountYearFacts(root, { metric: 'games', tone: 'pitch', label: 'Games', noun: 'rated games' });
        }
    });
    registerPanel({
        id: 'games-cumulative',
        selector: '.amiga-act-games-cumulative-chart',
        run: function (root) {
            return mountCumulative(root, {
                metric: 'GamesPlayed',
                tone: 'pitch',
                label: 'Cumulative games',
                noun: 'games',
                richTooltip: true,
                eventNoun: 'games'
            });
        }
    });
    registerPanel({
        id: 'tournaments-year',
        selector: '.amiga-act-tournaments-year-chart',
        run: function (root) {
            return mountTournamentsYear(root, {
                metric: 'tournaments',
                tone: 'chrome',
                label: 'Tournaments',
                noun: 'tournaments'
            });
        }
    });
    registerPanel({
        id: 'tournaments-cumulative',
        selector: '.amiga-act-tournaments-cumulative-chart',
        run: function (root) {
            return mountCumulative(root, {
                metric: 'TournamentsFinalized',
                tone: 'chrome',
                label: 'Cumulative tournaments',
                noun: 'tournaments',
                richTooltip: true
            });
        }
    });
    registerPanel({
        id: 'goals-year',
        selector: '.amiga-act-goals-year-chart',
        run: function (root) {
            return mountYearFacts(root, { metric: 'goals', tone: 'amber', label: 'Goals', noun: 'goals' });
        }
    });
    registerPanel({
        id: 'goals-cumulative',
        selector: '.amiga-act-goals-cumulative-chart',
        run: function (root) {
            return mountCumulative(root, {
                metric: 'GoalsScored',
                tone: 'amber',
                label: 'Cumulative goals',
                noun: 'goals',
                richTooltip: true,
                eventNoun: 'goals'
            });
        }
    });
    registerPanel({
        id: 'games-per-tournament-year',
        selector: '.amiga-act-games-per-tournament-year-chart',
        run: function (root) {
            return mountYearRate(root, {
                rate: 'games_per_tournament',
                tone: 'teal',
                label: 'Avg games per tournament',
                noun: 'games per tournament',
                decimals: 1
            });
        }
    });

    /* --- People wing (slice 2) --- */

    registerPanel({
        id: 'active-players-year',
        selector: '.amiga-act-active-players-year-chart',
        run: function (root) {
            return mountYearFacts(root, { metric: 'active_players', tone: 'chrome', label: 'Active players', noun: 'players' });
        }
    });
    registerPanel({
        id: 'debuts-year',
        selector: '.amiga-act-debuts-year-chart',
        run: function (root) {
            return mountYearFacts(root, { metric: 'player_debuts', tone: 'holo', label: 'New players', noun: 'debuts' });
        }
    });
    registerPanel({
        id: 'players-cumulative',
        selector: '.amiga-act-players-cumulative-chart',
        run: function (root) {
            return mountCumulative(root, {
                metric: 'NumberOfPlayers',
                tone: 'holo',
                label: 'Cumulative players',
                noun: 'players',
                richTooltip: true,
                eventNoun: { one: 'new player', other: 'new players' }
            });
        }
    });
    registerPanel({
        id: 'pairs-year',
        selector: '.amiga-act-pairs-year-chart',
        run: function (root) {
            return mountYearFacts(root, { metric: 'distinct_pairs', tone: 'teal', label: 'Distinct pairs', noun: 'pairings' });
        }
    });
    registerPanel({
        id: 'pairs-cumulative',
        selector: '.amiga-act-pairs-cumulative-chart',
        run: function (root) {
            return mountCumulative(root, {
                metric: 'DistinctOpponentPairs',
                tone: 'teal',
                label: 'Cumulative distinct pairs',
                noun: 'pairings',
                richTooltip: true
            });
        }
    });

    /* --- Texture wing (slice 3) --- */

    registerPanel({
        id: 'goals-per-game-year',
        selector: '.amiga-act-goals-per-game-year-chart',
        run: function (root) {
            return mountYearRate(root, {
                rate: 'goals_per_game',
                tone: 'pitch',
                label: 'Goals per game',
                noun: 'goals per game',
                decimals: 2
            });
        }
    });
    registerPanel({
        id: 'draw-rate-year',
        selector: '.amiga-act-draw-rate-year-chart',
        run: function (root) {
            return mountYearRate(root, {
                rate: 'draw_rate',
                tone: 'chrome',
                label: 'Draw rate',
                noun: 'draw rate',
                format: 'percent'
            });
        }
    });
    registerPanel({
        id: 'dd-rate-year',
        selector: '.amiga-act-dd-rate-year-chart',
        run: function (root) {
            return mountYearRate(root, {
                rate: 'dd_rate',
                tone: 'magenta',
                label: 'Double-digit rate',
                noun: 'double-digit rate',
                format: 'per100'
            });
        }
    });
    registerPanel({
        id: 'cs-rate-year',
        selector: '.amiga-act-cs-rate-year-chart',
        run: function (root) {
            return mountYearRate(root, {
                rate: 'cs_rate',
                tone: 'holo',
                label: 'Clean-sheet rate',
                noun: 'clean-sheet rate',
                format: 'per100'
            });
        }
    });
    registerPanel({
        id: 'high-scoring-rate-year',
        selector: '.amiga-act-high-scoring-rate-year-chart',
        run: function (root) {
            return mountYearRate(root, {
                rate: 'high_scoring_rate',
                tone: 'amber',
                label: 'High-scoring rate',
                noun: 'high-scoring rate',
                format: 'per100'
            });
        }
    });

    /* --- World Cups wing (slice 4) --- */

    registerPanel({
        id: 'wc-games-year',
        selector: '.amiga-act-wc-games-year-chart',
        run: function (root) {
            return mountWcGamesGhostYear(root, {
                tone: 'holo',
                label: 'WC games',
                noun: 'WC games',
                ghostLabel: 'All rated games',
                ghostNoun: 'rated games'
            });
        }
    });
    registerPanel({
        id: 'wc-share-year',
        selector: '.amiga-act-wc-share-year-chart',
        run: function (root) {
            return mountYearRate(root, {
                rate: 'wc_share',
                tone: 'chrome',
                label: 'WC share',
                noun: 'WC share',
                format: 'percent'
            });
        }
    });
    registerPanel({
        id: 'wc-games-cumulative',
        selector: '.amiga-act-wc-games-cumulative-chart',
        run: function (root) {
            return mountCumulative(root, { metric: 'WcGamesPlayed', tone: 'holo', label: 'Cumulative WC games', noun: 'WC games' });
        }
    });
    registerPanel({
        id: 'wc-goals-per-game-year',
        selector: '.amiga-act-wc-goals-per-game-year-chart',
        run: function (root) {
            return mountYearRate(root, {
                rate: 'wc_goals_per_game',
                tone: 'amber',
                label: 'WC goals per game',
                noun: 'goals per game',
                decimals: 2
            });
        }
    });
    registerPanel({
        id: 'wc-nations-year',
        selector: '.amiga-act-wc-nations-year-chart',
        run: function (root) {
            return mountWcNationsYear(root, {
                slice: 'world_cup',
                metric: 'distinct_nationalities',
                tone: 'teal',
                label: 'Nations',
                noun: 'nationalities'
            });
        }
    });
    registerPanel({
        id: 'wc-players-year',
        selector: '.amiga-act-wc-players-year-chart',
        run: function (root) {
            return mountWcPlayersYear(root, {
                slice: 'world_cup',
                metric: 'active_players',
                tone: 'pitch',
                label: 'WC players',
                noun: 'players'
            });
        }
    });

    function isAmigaActivityChartsPage() {
        return document.body && document.body.classList.contains('k2-amiga-activity-charts');
    }

    /* k2OnPageReady can invoke the callback twice on one load (shim quirk) — guard. */
    var booted = false;

    /* --- Geography selector platform (slice 5+) --- */

    var GEO_RACE_TONES = ['pitch', 'chrome', 'holo', 'amber', 'teal', 'magenta'];
    var GEO_RACE_KEYS_MAX = 9;
    var GEO_FLAG_CODES = {
        'Germany': 'de',
        'England': 'gb-eng',
        'Italy': 'it',
        'Norway': 'no',
        'Greece': 'gr',
        'Netherlands': 'nl',
        'Sweden': 'se',
        'Denmark': 'dk',
        'Spain': 'es',
        'Austria': 'at',
        'Ireland': 'ie',
        'France': 'fr',
        'Poland': 'pl',
        'Switzerland': 'ch',
        'Turkey': 'tr',
        'Scotland': 'gb-sct',
        'Belgium': 'be',
        'Wales': 'gb-wls',
        'Portugal': 'pt',
        'N. Ireland': 'gb-nir',
        'Hong Kong': 'hk',
        'UAE': 'ae'
    };
    var geoStates = new WeakMap();
    var geoListeners = [];

    function destroyChartOnCanvas(canvas) {
        if (!canvas || typeof Chart === 'undefined' || typeof Chart.getChart !== 'function') {
            return;
        }
        var inst = Chart.getChart(canvas);
        if (inst) {
            inst.destroy();
        }
    }

    function parseGeoJsonAttr(el, name, fallback) {
        try {
            var raw = el.getAttribute(name);
            if (!raw) {
                return fallback;
            }
            return JSON.parse(raw);
        } catch (e) {
            return fallback;
        }
    }

    function geoFlagSrc(code) {
        return code ? '/img/flags/amiga/' + encodeURIComponent(code) + '.svg' : '';
    }

    function geoFlagCode(country) {
        return GEO_FLAG_CODES[country] || '';
    }

    function geoFlagImgHtml(country, extraClass) {
        var code = geoFlagCode(country);
        if (!code) {
            return '';
        }
        var cls = 'k2-amiga-country-flag-img' + (extraClass ? ' ' + extraClass : '');
        return '<img src="' + geoFlagSrc(code) + '" width="20" height="15" alt="" aria-hidden="true" class="' + cls + '" decoding="async" loading="lazy">';
    }

    function geoRosterHref(country) {
        var url = '/amiga/country/roster.php?country=' + encodeURIComponent(country);
        var TT = global.K2AmigaTimeTravelUrl;
        if (TT && TT.navigationQuerySuffix) {
            url += TT.navigationQuerySuffix();
        }
        return url + '#k2-country-roster';
    }

    function geoDefaultDuelB(state) {
        var keys = state.availableKeys || [];
        if (!keys.length) {
            return '';
        }
        var a = state.duelA || keys[0];
        var b = keys.indexOf('Germany') !== -1 ? 'Germany' : (keys[1] || keys[0]);
        if (b === a && keys.length > 1) {
            b = keys[1];
        }
        if (b === a) {
            b = keys[0];
        }
        return b;
    }

    function ensureGeoDuelB(state) {
        if (!state.duelB && state.availableKeys.length) {
            state.duelB = geoDefaultDuelB(state);
        }
    }

    function getGeoDuelKeys(state) {
        var keys = [];
        if (state.duelA) {
            keys.push(state.duelA);
        }
        if (state.duelB && state.duelB !== state.duelA) {
            keys.push(state.duelB);
        }
        return keys;
    }

    function getGeoVisibleRaceKeys(state) {
        return state.raceKeys.filter(function (key) {
            return !state.hidden[key];
        });
    }

    function geoKeysCsv(keys) {
        return keys.join(',');
    }

    function syncGeoUrl(state) {
        var csv = geoKeysCsv(state.raceKeys);
        var url = new URL(global.location.href);
        url.searchParams.set(state.param, csv);
        var next = url.pathname + url.search + url.hash;
        global.history.replaceState(null, '', next);
        state.root.setAttribute('data-k2-geo-csv', csv);
    }

    function notifyGeoListeners(state) {
        var i;
        for (i = 0; i < geoListeners.length; i++) {
            try {
                geoListeners[i](state);
            } catch (e) {
                noteError('geo-listener', e);
            }
        }
    }

    function renderGroupedYearBar(canvas, labels, seriesList, spec, cutoff) {
        destroyChartOnCanvas(canvas);
        var datasets = [];
        var duelTones = ['pitch', 'chrome'];
        var i;
        for (i = 0; i < seriesList.length; i++) {
            var s = seriesList[i];
            datasets.push(Object.assign({
                label: s.key,
                data: s.values,
                barPercentage: 0.82,
                categoryPercentage: 0.72
            }, T.barStroke(tone(s.tone || duelTones[i % duelTones.length]))));
        }
        createChart(canvas, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: chartOptions({
                plugins: {
                    legend: { labels: { color: T.textMuted() } },
                    tooltip: T.mergeTooltip({
                        callbacks: {
                            label: function (item) {
                                if (item.parsed.y == null) {
                                    return 'No data';
                                }
                                return item.dataset.label + ': ' + formatCount(item.parsed.y) + ' ' + spec.noun;
                            },
                            footer: partialYearFooter(cutoff, labels)
                        }
                    })
                },
                scales: {
                    x: scaleXCategory(),
                    y: scaleYCountFormatted()
                }
            }, 'bar')
        }, 'bar');
    }

    function renderRaceLines(canvas, seriesData, spec, cutoff) {
        destroyChartOnCanvas(canvas);
        var datasets = [];
        var metaByDataset = [];
        var coarse = T.isCoarsePointer();
        var i;
        for (i = 0; i < seriesData.length; i++) {
            var s = seriesData[i];
            var chartData = [];
            var meta = [];
            var j;
            for (j = 0; j < (s.points || []).length; j++) {
                var pt = s.points[j];
                var x = parseEventDate(pt.date);
                if (x === null || pt.value == null) {
                    continue;
                }
                chartData.push({ x: x, y: pt.value });
                meta.push(pt);
            }
            if (!chartData.length) {
                continue;
            }
            metaByDataset.push(meta);
            datasets.push(Object.assign({
                label: s.key,
                data: chartData,
                fill: false,
                stepped: true,
                pointRadius: 0,
                pointHitRadius: 8,
                hidden: !!spec.hidden[s.key]
            }, T.lineStroke(tone(GEO_RACE_TONES[i % GEO_RACE_TONES.length]))));
        }
        if (!datasets.length) {
            return false;
        }
        createChart(canvas, {
            type: 'line',
            data: { datasets: datasets },
            options: chartOptions({
                interaction: { mode: 'nearest', intersect: false, axis: 'x' },
                onClick: coarse ? undefined : function (event, elements) {
                    if (!elements.length) {
                        return;
                    }
                    var dsIndex = elements[0].datasetIndex;
                    var ptIndex = elements[0].index;
                    var point = metaByDataset[dsIndex] && metaByDataset[dsIndex][ptIndex];
                    if (!point || !point.t) {
                        return;
                    }
                    global.location.href = tournamentChartClickUrl(point.t);
                },
                plugins: {
                    legend: {
                        labels: { color: T.textMuted() },
                        onClick: function (e, legendItem, legend) {
                            var ci = legend.chart;
                            var idx = legendItem.datasetIndex;
                            if (idx == null) {
                                return;
                            }
                            ci.setDatasetVisibility(idx, !ci.isDatasetVisible(idx));
                            ci.update();
                        }
                    },
                    tooltip: T.mergeTooltip({
                        callbacks: {
                            title: function (items) {
                                if (!items.length) {
                                    return '';
                                }
                                var dsIdx = items[0].datasetIndex;
                                var ptIdx = items[0].dataIndex;
                                var point = metaByDataset[dsIdx] && metaByDataset[dsIdx][ptIdx];
                                return point && point.name ? point.name : '';
                            },
                            label: function (item) {
                                if (item.parsed.y == null) {
                                    return 'No data';
                                }
                                return formatCount(item.parsed.y) + ' ' + spec.noun;
                            }
                        }
                    })
                },
                scales: {
                    x: {
                        type: 'time',
                        time: { unit: 'year', displayFormats: { year: 'yyyy' } },
                        ticks: { color: T.tickColor(), maxRotation: 0 },
                        grid: { color: T.grid() }
                    },
                    y: scaleYCountFormatted()
                }
            }, 'line')
        }, 'line');
        return true;
    }

    function updateGeoDuelFlags(state) {
        var flagA = state.root.querySelector('[data-k2-geo-flag-for="duel-a"]');
        var flagB = state.root.querySelector('[data-k2-geo-flag-for="duel-b"]');
        if (flagA) {
            flagA.innerHTML = geoFlagImgHtml(state.duelA);
        }
        if (flagB) {
            flagB.innerHTML = geoFlagImgHtml(state.duelB);
        }
    }

    function normalizeRaceKeys(keys) {
        var out = [];
        var i;
        for (i = 0; i < keys.length; i++) {
            var key = keys[i];
            if (!key || out.indexOf(key) !== -1 || out.length >= GEO_RACE_KEYS_MAX) {
                continue;
            }
            out.push(key);
        }
        return out;
    }

    function renderGeoRaceList(state) {
        var wrap = state.root.querySelector('.k2-amiga-act-geo-race-list');
        if (!wrap) {
            return;
        }
        var html = '';
        var i;
        for (i = 0; i < state.raceKeys.length; i++) {
            var key = state.raceKeys[i];
            var off = state.hidden[key] ? ' is-off' : '';
            html += '<button type="button" class="k2-amiga-act-geo-race-item' + off + '" data-country="' + key.replace(/"/g, '&quot;') + '">'
                + geoFlagImgHtml(key)
                + '<a class="k2-amiga-act-geo-race-item-name k2-link-star" href="' + geoRosterHref(key) + '">' + key + '</a>'
                + '</button>';
        }
        wrap.innerHTML = html;
    }

    function geoListboxBox(root, inputId) {
        var input = root.querySelector('#' + inputId);
        return input ? input.closest('[data-k2-archive-listbox]') : null;
    }

    function geoListboxValue(root, inputId) {
        var input = root.querySelector('#' + inputId);
        return input ? String(input.value) : '';
    }

    function geoCountryChoices(keys, withEmpty) {
        var choices = [];
        if (withEmpty) {
            choices.push({ value: '', label: '—' });
        }
        var i;
        for (i = 0; i < keys.length; i++) {
            choices.push({
                value: keys[i],
                label: keys[i],
                flagHtml: geoFlagImgHtml(keys[i])
            });
        }
        return choices;
    }

    function syncDuelSelects(state) {
        var LB = global.K2ArchiveListbox;
        if (LB) {
            LB.setValue(geoListboxBox(state.root, 'k2-amiga-act-geo-duel-a'), state.duelA, null, true);
            LB.setValue(geoListboxBox(state.root, 'k2-amiga-act-geo-duel-b'), state.duelB || '', null, true);
        }
        updateGeoDuelFlags(state);
    }

    function rebuildGeoSelectOptions(state) {
        var LB = global.K2ArchiveListbox;
        if (!LB) {
            return;
        }
        ensureGeoDuelB(state);
        LB.rebuild(
            geoListboxBox(state.root, 'k2-amiga-act-geo-duel-a'),
            geoCountryChoices(state.availableKeys, false),
            state.duelA
        );
        LB.rebuild(
            geoListboxBox(state.root, 'k2-amiga-act-geo-duel-b'),
            geoCountryChoices(state.availableKeys, false),
            state.duelB || ''
        );
        var addChoices = [];
        var j;
        for (j = 0; j < state.availableKeys.length; j++) {
            var k = state.availableKeys[j];
            if (state.raceKeys.indexOf(k) === -1) {
                addChoices.push({ value: k, label: k, flagHtml: geoFlagImgHtml(k) });
            }
        }
        LB.rebuild(geoListboxBox(state.root, 'k2-amiga-act-geo-race-add'), addChoices, '');
    }

    function mountGeoDuelYear(root, spec) {
        var state = getGeoState();
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        if (!state) {
            if (status) {
                status.textContent = 'Country controls not ready.';
            }
            return Promise.resolve();
        }
        var duelKeys = getGeoDuelKeys(state);
        if (!duelKeys.length) {
            if (status) {
                status.textContent = 'Pick a country to compare.';
            }
            destroyChartOnCanvas(canvas);
            return Promise.resolve();
        }
        var slice = spec.slice || state.slice;
        var q = 'slice=' + encodeURIComponent(slice) + '&metric=' + encodeURIComponent(spec.metric)
            + '&keys=' + encodeURIComponent(geoKeysCsv(duelKeys));
        return fetchJson('/api/amiga_community_year_facts.php', q).then(function (data) {
            if (data.available_keys && data.available_keys.length) {
                state.availableKeys = data.available_keys;
                rebuildGeoSelectOptions(state);
            }
            var years = data.years || [];
            var series = data.series || [];
            if (!years.length || !series.length) {
                if (status) {
                    status.textContent = 'No data to chart.';
                }
                destroyChartOnCanvas(canvas);
                return;
            }
            if (status) {
                status.textContent = '';
            }
            renderGroupedYearBar(canvas, years.map(String), series, {
                noun: spec.noun,
                tone: spec.tone || 'pitch'
            }, data.cutoff);
        }).catch(function (err) {
            noteError(spec.id || spec.metric || 'geo-duel', err);
            if (status) {
                status.textContent = 'Could not load this chart.';
            }
        });
    }

    function mountGeoRace(root, spec) {
        var state = getGeoState();
        var status = panelStatus(root);
        var canvas = requireCanvas(root, status);
        if (!canvas) {
            return Promise.resolve();
        }
        if (!state) {
            if (status) {
                status.textContent = 'Country controls not ready.';
            }
            return Promise.resolve();
        }
        var visible = getGeoVisibleRaceKeys(state);
        if (!visible.length) {
            if (status) {
                status.textContent = 'Turn on at least one race line.';
            }
            destroyChartOnCanvas(canvas);
            return Promise.resolve();
        }
        var slice = spec.slice || state.slice;
        var q = 'slice=' + encodeURIComponent(slice) + '&metric=' + encodeURIComponent(spec.metric)
            + '&keys=' + encodeURIComponent(geoKeysCsv(state.raceKeys));
        return fetchJson('/api/amiga_community_slice_series.php', q).then(function (data) {
            if (data.available_keys && data.available_keys.length) {
                state.availableKeys = data.available_keys;
                rebuildGeoSelectOptions(state);
            }
            var series = (data.series || []).filter(function (s) {
                return visible.indexOf(s.key) !== -1;
            });
            if (!series.length) {
                if (status) {
                    status.textContent = 'No data to chart.';
                }
                destroyChartOnCanvas(canvas);
                return;
            }
            if (status) {
                status.textContent = '';
            }
            renderRaceLines(canvas, series, {
                noun: spec.noun,
                hidden: state.hidden
            }, data.cutoff);
        }).catch(function (err) {
            noteError(spec.id || spec.metric || 'geo-race', err);
            if (status) {
                status.textContent = 'Could not load this chart.';
            }
        });
    }

    function mountGeoHarnessDuel(state) {
        var root = state.root.querySelector('.amiga-act-geo-harness-duel-chart');
        if (!root) {
            return Promise.resolve();
        }
        return mountGeoDuelYear(root, {
            id: 'geo-harness-duel',
            metric: 'games',
            noun: state.harnessNoun,
            tone: 'pitch'
        });
    }

    function mountGeoHarnessRace(state) {
        var root = state.root.querySelector('.amiga-act-geo-harness-race-chart');
        if (!root) {
            return Promise.resolve();
        }
        return mountGeoRace(root, {
            id: 'geo-harness-race',
            metric: 'games',
            noun: state.harnessNoun
        });
    }

    function refreshGeoRacePanels(state) {
        var chain = Promise.resolve();
        chain = chain.then(function () {
            return mountGeoHarnessRace(state);
        });
        var i;
        for (i = 0; i < geoPanelRefreshers.length; i++) {
            if (geoPanelRefreshers[i].pattern === 'race') {
                (function (fn) {
                    chain = chain.then(fn);
                })(geoPanelRefreshers[i].run);
            }
        }
        return chain;
    }

    function refreshGeoAllPanels(state) {
        var chain = Promise.resolve();
        chain = chain.then(function () {
            return mountGeoHarnessDuel(state);
        });
        chain = chain.then(function () {
            return mountGeoHarnessRace(state);
        });
        var i;
        for (i = 0; i < geoPanelRefreshers.length; i++) {
            (function (fn) {
                chain = chain.then(fn);
            })(geoPanelRefreshers[i].run);
        }
        return chain;
    }

    function applyGeoStateChange(state, updateUrl) {
        state.raceKeys = normalizeRaceKeys(state.raceKeys);
        if (updateUrl !== false) {
            syncGeoUrl(state);
        }
        renderGeoRaceList(state);
        syncDuelSelects(state);
        rebuildGeoSelectOptions(state);
        notifyGeoListeners(state);
        return refreshGeoAllPanels(state);
    }

    function initGeographyRoot(root) {
        var slice = root.getAttribute('data-k2-geo-slice') || 'host_country';
        var param = root.getAttribute('data-k2-geo-param') || 'hosts';
        var state = {
            root: root,
            slice: slice,
            param: param,
            duelA: root.getAttribute('data-k2-geo-duel-a') || '',
            duelB: root.getAttribute('data-k2-geo-duel-b') || '',
            raceKeys: parseGeoJsonAttr(root, 'data-k2-geo-race', []),
            availableKeys: parseGeoJsonAttr(root, 'data-k2-geo-available', []),
            hidden: {},
            harnessNoun: slice === 'player_nationality' ? 'appearances' : 'hosted games'
        };
        ensureGeoDuelB(state);
        if (!state.raceKeys.length && state.availableKeys.length) {
            state.raceKeys = normalizeRaceKeys(state.availableKeys.slice(0, 5));
        }
        geoStates.set(root, state);

        var inputA = root.querySelector('#k2-amiga-act-geo-duel-a');
        var inputB = root.querySelector('#k2-amiga-act-geo-duel-b');
        var addInput = root.querySelector('#k2-amiga-act-geo-race-add');
        var raceList = root.querySelector('.k2-amiga-act-geo-race-list');

        if (inputA) {
            inputA.addEventListener('change', function () {
                state.duelA = geoListboxValue(state.root, 'k2-amiga-act-geo-duel-a');
                applyGeoStateChange(state);
            });
        }
        if (inputB) {
            inputB.addEventListener('change', function () {
                state.duelB = geoListboxValue(state.root, 'k2-amiga-act-geo-duel-b');
                applyGeoStateChange(state);
            });
        }
        if (addInput) {
            addInput.addEventListener('change', function () {
                var key = geoListboxValue(state.root, 'k2-amiga-act-geo-race-add');
                if (!key || state.raceKeys.indexOf(key) !== -1) {
                    if (global.K2ArchiveListbox) {
                        global.K2ArchiveListbox.setValue(geoListboxBox(state.root, 'k2-amiga-act-geo-race-add'), '', null, true);
                    }
                    return;
                }
                if (state.raceKeys.length >= GEO_RACE_KEYS_MAX) {
                    if (global.K2ArchiveListbox) {
                        global.K2ArchiveListbox.setValue(geoListboxBox(state.root, 'k2-amiga-act-geo-race-add'), '', null, true);
                    }
                    return;
                }
                state.raceKeys.push(key);
                delete state.hidden[key];
                applyGeoStateChange(state);
            });
        }
        if (raceList) {
            raceList.addEventListener('click', function (event) {
                var target = event.target;
                if (target && target.closest && target.closest('a')) {
                    return;
                }
                var btn = target && target.closest ? target.closest('.k2-amiga-act-geo-race-item') : null;
                if (!btn) {
                    return;
                }
                event.preventDefault();
                var country = btn.getAttribute('data-country');
                if (!country) {
                    return;
                }
                if (event.shiftKey && state.raceKeys.length > 1) {
                    state.raceKeys = state.raceKeys.filter(function (k) {
                        return k !== country;
                    });
                    delete state.hidden[country];
                    if (state.duelA === country) {
                        state.duelA = state.raceKeys[0] || '';
                    }
                    if (state.duelB === country) {
                        state.duelB = state.raceKeys[1] || '';
                    }
                    applyGeoStateChange(state);
                    return;
                }
                state.hidden[country] = !state.hidden[country];
                renderGeoRaceList(state);
                refreshGeoRacePanels(state);
            });
        }

        renderGeoRaceList(state);
        syncDuelSelects(state);
        rebuildGeoSelectOptions(state);

        return refreshGeoAllPanels(state);
    }

    function initGeographyPlatform() {
        var roots = document.querySelectorAll('.k2-amiga-act-geo-root');
        if (!roots.length) {
            return Promise.resolve();
        }
        var chain = Promise.resolve();
        var i;
        for (i = 0; i < roots.length; i++) {
            (function (root) {
                chain = chain.then(function () {
                    return initGeographyRoot(root);
                });
            })(roots[i]);
        }
        return chain;
    }

    function getGeoState(root) {
        if (!root) {
            root = document.querySelector('.k2-amiga-act-geo-root');
        }
        return root ? geoStates.get(root) || null : null;
    }

    function subscribeGeoChange(fn) {
        if (typeof fn === 'function') {
            geoListeners.push(fn);
        }
    }

    /* --- Geography Hosts wing (slice 6) --- */

    registerGeoPanel({
        id: 'host-games-year',
        selector: '.amiga-act-host-games-year-chart',
        pattern: 'duel',
        metric: 'games',
        noun: 'hosted games',
        tone: 'pitch'
    });
    registerGeoPanel({
        id: 'host-games-race',
        selector: '.amiga-act-host-games-race-chart',
        pattern: 'race',
        metric: 'games',
        noun: 'hosted games'
    });
    registerGeoPanel({
        id: 'host-tournaments-year',
        selector: '.amiga-act-host-tournaments-year-chart',
        pattern: 'duel',
        metric: 'tournaments',
        noun: 'tournaments hosted',
        tone: 'chrome'
    });
    registerGeoPanel({
        id: 'host-tournaments-race',
        selector: '.amiga-act-host-tournaments-race-chart',
        pattern: 'race',
        metric: 'tournaments',
        noun: 'tournaments hosted'
    });
    registerGeoPanel({
        id: 'host-goals-year',
        selector: '.amiga-act-host-goals-year-chart',
        pattern: 'duel',
        metric: 'goals',
        noun: 'goals',
        tone: 'amber'
    });
    registerGeoPanel({
        id: 'host-goals-race',
        selector: '.amiga-act-host-goals-race-chart',
        pattern: 'race',
        metric: 'goals',
        noun: 'goals'
    });
    registerPanel({
        id: 'host-countries-year',
        selector: '.amiga-act-host-countries-year-chart',
        run: function (root) {
            return mountHostCountriesYear(root, {
                slice: 'realm',
                metric: 'distinct_host_countries',
                tone: 'teal',
                label: 'Host countries',
                noun: 'host countries'
            });
        }
    });
    registerPanel({
        id: 'host-countries-cumulative',
        selector: '.amiga-act-host-countries-cumulative-chart',
        run: function (root) {
            return mountHostCountriesCumulative(root, {
                metric: 'DistinctHostCountries',
                tone: 'teal',
                label: 'Distinct host countries',
                noun: 'host countries'
            });
        }
    });

    /* --- Geography Nations wing (slice 7) --- */

    registerGeoPanel({
        id: 'nat-active-players-year',
        selector: '.amiga-act-nat-active-players-year-chart',
        pattern: 'duel',
        metric: 'active_players',
        noun: 'active players',
        tone: 'teal'
    });
    registerGeoPanel({
        id: 'nat-roster-race',
        selector: '.amiga-act-nat-roster-race-chart',
        pattern: 'race',
        metric: 'active_players',
        noun: 'active players'
    });
    registerGeoPanel({
        id: 'nat-debuts-year',
        selector: '.amiga-act-nat-debuts-year-chart',
        pattern: 'duel',
        metric: 'player_debuts',
        noun: 'new players',
        tone: 'holo'
    });
    registerGeoPanel({
        id: 'nat-appearances-year',
        selector: '.amiga-act-nat-appearances-year-chart',
        pattern: 'duel',
        metric: 'games',
        noun: 'appearances',
        tone: 'pitch'
    });
    registerGeoPanel({
        id: 'nat-appearances-race',
        selector: '.amiga-act-nat-appearances-race-chart',
        pattern: 'race',
        metric: 'games',
        noun: 'appearances'
    });
    registerGeoPanel({
        id: 'nat-goals-year',
        selector: '.amiga-act-nat-goals-year-chart',
        pattern: 'duel',
        metric: 'goals',
        noun: 'goals',
        tone: 'amber'
    });
    registerGeoPanel({
        id: 'nat-goals-race',
        selector: '.amiga-act-nat-goals-race-chart',
        pattern: 'race',
        metric: 'goals',
        noun: 'goals'
    });
    registerPanel({
        id: 'nat-nationalities-year',
        selector: '.amiga-act-nationalities-year-chart',
        run: function (root) {
            return mountNationalitiesYear(root, {
                tone: 'teal',
                label: 'Nationalities',
                noun: 'nationalities'
            });
        }
    });

    /* --- Shape wing (slice 9) — loader order: snapshot kinds first, game scans last --- */

    registerPanel({
        id: 'career-games-histogram',
        selector: '.amiga-act-career-games-histogram',
        run: function (root) {
            return mountHistogram(root, { kind: 'career_games', tone: 'pitch', label: 'Career games' });
        }
    });
    registerPanel({
        id: 'tournaments-played-histogram',
        selector: '.amiga-act-tournaments-played-histogram',
        run: function (root) {
            return mountHistogram(root, { kind: 'tournaments_played', tone: 'chrome', label: 'Tournaments played' });
        }
    });
    registerPanel({
        id: 'distinct-opponents-histogram',
        selector: '.amiga-act-distinct-opponents-histogram',
        run: function (root) {
            return mountHistogram(root, { kind: 'distinct_opponents', tone: 'holo', label: 'Distinct opponents' });
        }
    });
    registerPanel({
        id: 'countries-played-histogram',
        selector: '.amiga-act-countries-played-histogram',
        run: function (root) {
            return mountHistogram(root, { kind: 'countries_played', tone: 'amber', label: 'Countries played in' });
        }
    });
    registerPanel({
        id: 'wcs-played-histogram',
        selector: '.amiga-act-wcs-played-histogram',
        run: function (root) {
            return mountHistogram(root, { kind: 'world_cups_played', tone: 'magenta', label: 'World Cups played' });
        }
    });
    registerPanel({
        id: 'rating-distribution-histogram',
        selector: '.amiga-act-rating-distribution-histogram',
        run: function (root) {
            return mountHistogram(root, { kind: 'rating', tone: 'teal', label: 'Rating', dense: true });
        }
    });
    registerPanel({
        id: 'tournament-size-histogram',
        selector: '.amiga-act-tournament-size-histogram',
        run: function (root) {
            return mountHistogram(root, { kind: 'tournament_games', tone: 'chrome', label: 'Rated games per tournament' });
        }
    });
    registerPanel({
        id: 'goal-sum-histogram',
        selector: '.amiga-act-goal-sum-histogram',
        loadTier: 'deferred',
        run: function (root) {
            return mountHistogram(root, { kind: 'goal_sum', tone: 'amber', label: 'Total goals per game' });
        }
    });
    registerPanel({
        id: 'active-years-histogram',
        selector: '.amiga-act-active-years-histogram',
        loadTier: 'deferred',
        run: function (root) {
            return mountHistogram(root, { kind: 'active_years', tone: 'pitch', label: 'Active calendar years', dense: true });
        }
    });

    function boot() {
        if (booted || !isAmigaActivityChartsPage()) {
            return;
        }
        booted = true;
        initGeographyPlatform().then(function () {
            drain(0);
        });
    }

    (window.k2OnPageReady || function (fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    })(boot);

    global.K2AmigaActivityCharts = {
        panels: PANELS,
        errors: ERRORS,
        registerPanel: registerPanel,
        fetchJson: fetchJson,
        yearToDate: yearToDate,
        chartOptions: chartOptions,
        createChart: createChart,
        requireCanvas: requireCanvas,
        scaleYCount: scaleYCount,
        boot: boot,
        getGeoState: getGeoState,
        subscribeGeoChange: subscribeGeoChange,
        renderGroupedYearBar: renderGroupedYearBar,
        renderRaceLines: renderRaceLines,
        geoKeysCsv: geoKeysCsv,
        getGeoDuelKeys: getGeoDuelKeys,
        mountGeoDuelYear: mountGeoDuelYear,
        mountGeoRace: mountGeoRace,
        registerGeoPanel: registerGeoPanel,
        mountHistogram: mountHistogram
    };
})(typeof window !== 'undefined' ? window : this);