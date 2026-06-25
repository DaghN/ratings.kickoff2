/**
 * Shared rank chart domain, series, peak, and Chart.js helpers (solo + H2H compare).
 */
(function (global) {
    'use strict';

    var T = global.K2ChartTheme;
    var DR = global.K2ChartDateRange;

    var BAND_LABELS = {
        top20: 20,
        top50: 50,
        top100: 100
    };

    var PERCENTILE_RANGES = {
        p95: [95, 100],
        p90: [90, 100],
        p80: [80, 100],
        p50: [50, 100],
        community: [0, 100]
    };

    function parseEventDate(dateStr) {
        if (!dateStr) {
            return null;
        }
        var normalized = String(dateStr).trim().replace(' ', 'T');
        var d = new Date(normalized);
        return isNaN(d.getTime()) ? null : d;
    }

    function chartOptions(extra, chartKind) {
        if (T && T.activityChartOptions) {
            return T.activityChartOptions(Object.assign({ maintainAspectRatio: false }, extra || {}), {
                chartKind: chartKind || 'line'
            });
        }
        return Object.assign({ responsive: true, maintainAspectRatio: false }, extra || {});
    }

    function withCareerPlotGutter(extra, chartKind) {
        var gutter = T && T.careerChartGutterOptions ? T.careerChartGutterOptions() : {};
        return chartOptions(Object.assign({}, gutter, extra || {}), chartKind || 'line');
    }

    function rankChartGridColor() {
        if (T && T.rankChartGrid) {
            return T.rankChartGrid();
        }
        return T && T.grid ? T.grid() : 'rgba(255, 255, 255, 0.08)';
    }

    function careerYScale(extra) {
        var base = {
            ticks: { color: T.tickColor() },
            grid: { color: rankChartGridColor() }
        };
        var extraScale = extra || {};
        var scale = Object.assign({}, base, extraScale);
        if (extraScale.ticks) {
            scale.ticks = Object.assign({}, base.ticks, extraScale.ticks);
        }
        return T && T.careerChartYAxisOptions ? T.careerChartYAxisOptions(scale) : scale;
    }

    function createChart(canvas, config) {
        if (T && T.createActivityChart) {
            return T.createActivityChart(canvas, config, 'line');
        }
        return new Chart(canvas, config);
    }

    function careerPadding(best, worst) {
        var span = Math.max(0, worst - best);
        var pad = Math.round(0.05 * span);
        if (pad < 5) {
            pad = 5;
        }
        if (pad > 20) {
            pad = 20;
        }
        return pad;
    }

    function percentileCareerPadding(best, worst) {
        var span = Math.max(0, best - worst);
        var pad = Math.round(0.05 * span);
        if (pad < 2) {
            pad = 2;
        }
        if (pad > 10) {
            pad = 10;
        }
        return pad;
    }

    function rankChartTimeRange(pointsList, timelineStart, cutoffActive) {
        var points = pointsList;
        if (Array.isArray(pointsList)) {
            points = pointsList[0] || [];
            if (pointsList.length > 1) {
                for (var p = 1; p < pointsList.length; p++) {
                    if ((pointsList[p] || []).length > points.length) {
                        points = pointsList[p];
                    }
                }
            }
        }
        var range;
        if (DR && DR.careerTimeRangeFromStart) {
            range = DR.careerTimeRangeFromStart(timelineStart);
        } else {
            range = {
                xMin: undefined,
                xMax: DR && DR.endOfToday ? DR.endOfToday() : undefined
            };
        }
        if (cutoffActive && points.length) {
            var last = parseEventDate(points[points.length - 1].eventDate);
            if (last) {
                range.xMax = last;
            }
        }
        return range;
    }

    function computeDomain(scale, linearWindow, percentileWindow, meta) {
        var ceiling = meta && meta.ceiling ? meta.ceiling : 1;
        var best = meta && meta.careerBestRank != null ? meta.careerBestRank : 1;
        var worst = meta && meta.careerWorstRank != null ? meta.careerWorstRank : ceiling;

        if (scale === 'percentile') {
            if (percentileWindow === 'career') {
                var bestPct = meta && meta.careerBestPercentile != null ? meta.careerBestPercentile : 100;
                var worstPct = meta && meta.careerWorstPercentile != null ? meta.careerWorstPercentile : 0;
                var pctPad = percentileCareerPadding(bestPct, worstPct);
                return {
                    kind: 'percentile',
                    min: Math.max(0, worstPct - pctPad),
                    max: Math.min(100, bestPct + pctPad)
                };
            }
            var pr = PERCENTILE_RANGES[percentileWindow] || PERCENTILE_RANGES.community;
            return { kind: 'percentile', min: pr[0], max: pr[1] };
        }

        if (linearWindow === 'community') {
            return { kind: 'linear', min: 1, max: ceiling, band: null };
        }
        if (linearWindow === 'career') {
            var pad = careerPadding(best, worst);
            return {
                kind: 'linear',
                min: Math.max(1, best - pad),
                max: Math.min(ceiling, worst + pad),
                band: null
            };
        }
        if (BAND_LABELS[linearWindow]) {
            return {
                kind: 'linear',
                min: 1,
                max: BAND_LABELS[linearWindow],
                band: BAND_LABELS[linearWindow]
            };
        }
        return { kind: 'linear', min: 1, max: ceiling, band: null };
    }

    function mergeCompareMeta(playerMeta, opponentMeta) {
        var pMeta = playerMeta || {};
        var oMeta = opponentMeta || {};
        var ceiling = Math.max(pMeta.ceiling || 1, oMeta.ceiling || 1);
        var pBest = pMeta.careerBestRank != null ? pMeta.careerBestRank : ceiling;
        var pWorst = pMeta.careerWorstRank != null ? pMeta.careerWorstRank : 1;
        var oBest = oMeta.careerBestRank != null ? oMeta.careerBestRank : ceiling;
        var oWorst = oMeta.careerWorstRank != null ? oMeta.careerWorstRank : 1;
        var pBestPct = pMeta.careerBestPercentile != null ? pMeta.careerBestPercentile : 0;
        var pWorstPct = pMeta.careerWorstPercentile != null ? pMeta.careerWorstPercentile : 100;
        var oBestPct = oMeta.careerBestPercentile != null ? oMeta.careerBestPercentile : 0;
        var oWorstPct = oMeta.careerWorstPercentile != null ? oMeta.careerWorstPercentile : 100;

        return {
            careerBestRank: Math.min(pBest, oBest),
            careerWorstRank: Math.max(pWorst, oWorst),
            careerBestPercentile: Math.max(pBestPct, oBestPct),
            careerWorstPercentile: Math.min(pWorstPct, oWorstPct),
            ceiling: ceiling,
            cutoffActive: !!(pMeta.cutoffActive || oMeta.cutoffActive)
        };
    }

    function plotPointStatus(scale, domain, point) {
        var rank = point.eloRank;
        var pct = point.percentile;

        if (scale === 'linear') {
            if (domain.band && rank > domain.band) {
                return { inRange: false, clipY: domain.max };
            }
            if (rank < domain.min) {
                return { inRange: false, clipY: domain.min };
            }
            if (rank > domain.max) {
                return { inRange: false, clipY: domain.max };
            }
            return { inRange: true, y: rank };
        }
        if (pct < domain.min) {
            return { inRange: false, clipY: domain.min };
        }
        if (pct > domain.max) {
            return { inRange: false, clipY: domain.max };
        }
        return { inRange: true, y: pct };
    }

    function pushSeriesPoint(out, x, y, clipped, raw) {
        out.push({
            x: x,
            y: y,
            clipped: clipped,
            raw: raw
        });
    }

    function buildSeries(points, scale, domain) {
        var out = [];
        var outOfRangeStreak = false;
        var lastClipY = null;

        for (var i = 0; i < points.length; i++) {
            var pt = points[i];
            var x = parseEventDate(pt.eventDate);
            if (x === null) {
                continue;
            }

            var status = plotPointStatus(scale, domain, pt);

            if (status.inRange) {
                if (outOfRangeStreak && lastClipY != null) {
                    pushSeriesPoint(out, x, lastClipY, true, pt);
                }
                outOfRangeStreak = false;
                lastClipY = null;
                pushSeriesPoint(out, x, status.y, false, pt);
                continue;
            }

            if (status.clipY == null) {
                continue;
            }

            if (!outOfRangeStreak) {
                pushSeriesPoint(out, x, status.clipY, true, pt);
                outOfRangeStreak = true;
                lastClipY = status.clipY;
                continue;
            }

            pushSeriesPoint(out, x, null, true, pt);
        }

        return out;
    }

    function hasPlottedPoints(series) {
        for (var i = 0; i < series.length; i++) {
            if (!series[i].clipped && series[i].y != null && !isNaN(series[i].y)) {
                return true;
            }
        }
        return false;
    }

    function anyPlottedSeries(seriesList) {
        for (var i = 0; i < seriesList.length; i++) {
            if (hasPlottedPoints(seriesList[i])) {
                return true;
            }
        }
        return false;
    }

    function playerEverInLinearBand(points, bandK) {
        if (!bandK) {
            return true;
        }
        for (var i = 0; i < points.length; i++) {
            if (points[i].eloRank <= bandK) {
                return true;
            }
        }
        return false;
    }

    function playerEverInPercentilePreset(points, minPct, maxPct) {
        for (var i = 0; i < points.length; i++) {
            var pct = points[i].percentile;
            if (pct >= minPct && pct <= maxPct) {
                return true;
            }
        }
        return false;
    }

    function compareBandHasAnyPlayer(playerPoints, opponentPoints, scale, linearWindow, percentileWindow) {
        if (scale === 'linear') {
            var bandK = BAND_LABELS[linearWindow];
            if (!bandK) {
                return true;
            }
            return playerEverInLinearBand(playerPoints, bandK)
                || playerEverInLinearBand(opponentPoints, bandK);
        }
        if (percentileWindow === 'career' || percentileWindow === 'community') {
            return true;
        }
        var pr = PERCENTILE_RANGES[percentileWindow] || PERCENTILE_RANGES.community;
        return playerEverInPercentilePreset(playerPoints, pr[0], pr[1])
            || playerEverInPercentilePreset(opponentPoints, pr[0], pr[1]);
    }

    function yAxisConfig(scale, domain) {
        if (scale === 'percentile') {
            return careerYScale({
                reverse: false,
                min: domain.min,
                max: domain.max,
                ticks: {
                    callback: function (v) {
                        return v + '%';
                    }
                }
            });
        }
        return careerYScale({
            reverse: true,
            min: domain.min,
            max: domain.max,
            ticks: {
                stepSize: undefined,
                callback: function (v) {
                    return '#' + Math.round(v);
                }
            }
        });
    }

    function formatTooltipDate(d) {
        return d.toLocaleDateString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function escapeHtml(text) {
        if (text == null || text === '') {
            return '';
        }

        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function peakAfterClause(tournamentName) {
        if (!tournamentName) {
            return '';
        }

        return ', after ' + escapeHtml(tournamentName);
    }

    function peakPointFromHistory(points, scale) {
        if (!points.length) {
            return null;
        }
        var bestIdx = 0;
        var bestVal = scale === 'percentile' ? points[0].percentile : points[0].eloRank;
        for (var i = 1; i < points.length; i++) {
            var v = scale === 'percentile' ? points[i].percentile : points[i].eloRank;
            if (scale === 'percentile') {
                if (v > bestVal) {
                    bestVal = v;
                    bestIdx = i;
                }
            } else if (v < bestVal) {
                bestVal = v;
                bestIdx = i;
            }
        }
        return {
            point: points[bestIdx],
            display: scale === 'percentile' ? bestVal + '%' : '#' + bestVal
        };
    }

    function renderPeakSummary(summary, points, scale, options) {
        if (!summary) {
            return;
        }
        var opts = options || {};
        if (!points.length) {
            summary.hidden = true;
            return;
        }

        var peak = null;
        var stored = opts.peak;
        if (stored && stored.eloRank > 0 && stored.eventDate) {
            var storedDate = parseEventDate(stored.eventDate);
            if (storedDate) {
                peak = {
                    point: {
                        eventDate: stored.eventDate,
                        tournamentName: stored.tournamentName || ''
                    },
                    display: scale === 'percentile'
                        ? (stored.percentile != null ? stored.percentile + '%' : null)
                        : '#' + stored.eloRank
                };
            }
        }
        if (!peak || peak.display == null) {
            peak = peakPointFromHistory(points, scale);
        }
        if (!peak) {
            summary.hidden = true;
            return;
        }
        var whenDate = parseEventDate(peak.point.eventDate);
        if (!whenDate) {
            summary.hidden = true;
            return;
        }
        var tournamentName = peak.point.tournamentName || '';
        var valueClass = opts.peakValueClass || 'pm3-chart-peak-value';
        var rankHtml = '<span class="' + valueClass + '">' + peak.display + '</span>';
        var whenHtml = ' <span class="pm3d-chart__summary-note">on ' + formatTooltipDate(whenDate)
            + peakAfterClause(tournamentName) + '.</span>';
        if (opts.namePrefix) {
            summary.innerHTML = opts.namePrefix + ' peak: ' + rankHtml + whenHtml;
        } else {
            summary.innerHTML = 'Peak: ' + rankHtml + whenHtml;
        }
        summary.hidden = false;
    }

    function rankTooltipLabel(raw) {
        return '#' + raw.eloRank + ' of ' + raw.ladderSize + ' (' + raw.percentile + '%)';
    }

    function buildRankTooltipCallbacks(labelPrefix) {
        return {
            filter: function (item) {
                return item.raw && !item.raw.clipped && item.raw.y != null;
            },
            callbacks: {
                title: function (items) {
                    if (!items.length || !items[0].raw || !items[0].raw.raw) {
                        return '';
                    }
                    var raw = items[0].raw.raw;
                    var d = parseEventDate(raw.eventDate);
                    var title = d ? formatTooltipDate(d) : String(raw.eventDate || '');
                    if (raw.tournamentName) {
                        title += ' — ' + raw.tournamentName;
                    }
                    return title;
                },
                label: function (item) {
                    var raw = item.raw && item.raw.raw;
                    if (!raw) {
                        return '';
                    }
                    var text = rankTooltipLabel(raw);
                    if (labelPrefix) {
                        return labelPrefix + ': ' + text;
                    }
                    if (item.dataset && item.dataset.label) {
                        return item.dataset.label + ' — ' + text;
                    }
                    return text;
                }
            }
        };
    }

    var COMPARE_RANK_HTML_TOOLTIP_ID = 'k2-compare-rank-html-tooltip';

    function playerRankSeriesShortName(datasetLabel) {
        if (!datasetLabel) {
            return 'Player';
        }
        return datasetLabel.replace(/\s+rank$/i, '');
    }

    function rankTooltipClassForDatasetIndex(datasetIndex) {
        return datasetIndex === 0
            ? 'pm3-chart-tooltip-rating--subject'
            : 'pm3-chart-tooltip-rating--opponent';
    }

    function formatRankTooltipValueHtml(raw, datasetIndex) {
        var rankPart = '#' + raw.eloRank;
        var suffix = ' of ' + raw.ladderSize + ' (' + raw.percentile + '%)';
        return '<span class="' + rankTooltipClassForDatasetIndex(datasetIndex) + '">'
            + escapeHtml(rankPart) + '</span>'
            + escapeHtml(suffix);
    }

    function getOrCreateCompareRankHtmlTooltip() {
        var el = document.getElementById(COMPARE_RANK_HTML_TOOLTIP_ID);
        if (el) {
            return el;
        }
        el = document.createElement('div');
        el.id = COMPARE_RANK_HTML_TOOLTIP_ID;
        el.className = 'k2-chart-html-tooltip';
        el.setAttribute('role', 'tooltip');
        el.hidden = true;
        document.body.appendChild(el);
        return el;
    }

    function buildCompareRankTooltipTitle(hoverMs) {
        if (DR && DR.formatCompareDateTooltipTitle) {
            return DR.formatCompareDateTooltipTitle(hoverMs);
        }
        return '';
    }

    function formatCompareRankTooltipRowHtml(item) {
        var wrap = item.raw;
        var raw = wrap && wrap.raw;
        if (!raw) {
            return '';
        }
        var name = escapeHtml(playerRankSeriesShortName(item.dataset && item.dataset.label));
        var rankHtml = formatRankTooltipValueHtml(raw, item.datasetIndex);
        return name + ': ' + rankHtml;
    }

    function swatchColorForRankItem(item, theme) {
        var ds = item.dataset || {};
        var color = ds.borderColor;
        if (typeof color === 'function') {
            color = color(item);
        }
        if (!color || color === 'transparent') {
            color = item.datasetIndex === 0
                ? theme.h2hSubjectBorder()
                : theme.h2hOpponentBorder();
        }
        return color;
    }

    function bindCompareRankExternalTooltip(theme) {
        return function (context) {
            var tooltipEl = getOrCreateCompareRankHtmlTooltip();
            var tooltip = context.tooltip;
            var chart = context.chart;
            var resolved;
            var points;
            if (!tooltip) {
                tooltipEl.hidden = true;
                return;
            }
            if (DR && DR.resolveCompareRankDateTooltipItems) {
                resolved = DR.resolveCompareRankDateTooltipItems(chart, tooltip.caretX);
                points = resolved.items;
                if (!points.length) {
                    if (typeof chart.setActiveElements === 'function') {
                        chart.setActiveElements([]);
                    }
                    tooltipEl.hidden = true;
                    return;
                }
            } else {
                if (tooltip.opacity === 0) {
                    tooltipEl.hidden = true;
                    return;
                }
                points = (tooltip.dataPoints || []).filter(function (item) {
                    return item.raw && !item.raw.clipped && item.raw.y != null;
                });
                if (!points.length) {
                    if (typeof chart.setActiveElements === 'function') {
                        chart.setActiveElements([]);
                    }
                    tooltipEl.hidden = true;
                    return;
                }
                resolved = { hoverMs: points[0].parsed.x };
            }
            var title = buildCompareRankTooltipTitle(resolved.hoverMs);
            var bodyHtml = points.map(function (item) {
                var line = formatCompareRankTooltipRowHtml(item);
                var swatch = swatchColorForRankItem(item, theme);
                return '<div class="k2-chart-html-tooltip__row">'
                    + '<span class="k2-chart-html-tooltip__swatch" style="background:'
                    + escapeHtml(swatch) + '"></span>'
                    + '<span class="k2-chart-html-tooltip__text">' + line + '</span>'
                    + '</div>';
            }).join('');
            tooltipEl.innerHTML = '<div class="k2-chart-html-tooltip__title">'
                + escapeHtml(title) + '</div>'
                + '<div class="k2-chart-html-tooltip__body">' + bodyHtml + '</div>';
            tooltipEl.hidden = false;

            if (DR && points.length && typeof chart.setActiveElements === 'function') {
                chart.setActiveElements(
                    DR.compareDateTooltipActiveElements(points),
                    { x: tooltip.caretX, y: tooltip.caretY }
                );
            }

            var canvas = context.chart.canvas;
            var rect = canvas.getBoundingClientRect();
            tooltipEl.style.left = (rect.left + tooltip.caretX) + 'px';
            tooltipEl.style.top = (rect.top + tooltip.caretY) + 'px';
            tooltipEl.style.opacity = '1';
        };
    }

    function buildTimeXScale(timeRange) {
        return {
            type: 'time',
            min: timeRange.xMin,
            max: timeRange.xMax,
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
            grid: { color: rankChartGridColor() }
        };
    }

    global.K2PlayerRankChartCore = {
        BAND_LABELS: BAND_LABELS,
        PERCENTILE_RANGES: PERCENTILE_RANGES,
        parseEventDate: parseEventDate,
        computeDomain: computeDomain,
        mergeCompareMeta: mergeCompareMeta,
        buildSeries: buildSeries,
        hasPlottedPoints: hasPlottedPoints,
        anyPlottedSeries: anyPlottedSeries,
        compareBandHasAnyPlayer: compareBandHasAnyPlayer,
        rankChartTimeRange: rankChartTimeRange,
        yAxisConfig: yAxisConfig,
        formatTooltipDate: formatTooltipDate,
        peakPointFromHistory: peakPointFromHistory,
        renderPeakSummary: renderPeakSummary,
        escapeHtml: escapeHtml,
        peakAfterClause: peakAfterClause,
        withCareerPlotGutter: withCareerPlotGutter,
        createChart: createChart,
        buildTimeXScale: buildTimeXScale,
        buildRankTooltipCallbacks: buildRankTooltipCallbacks,
        bindCompareRankExternalTooltip: bindCompareRankExternalTooltip,
        rankTooltipLabel: rankTooltipLabel
    };
}(typeof window !== 'undefined' ? window : this));