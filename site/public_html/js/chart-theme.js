/**
 * Chart.js colours aligned with stylesheets/theme.css tokens.
 * Load before chart init scripts on themed pages.
 *
 * Primitives: --k2-pure-* (hex). Chart roles: --k2-chart-* (amber â†’ --k2-amber-soft).
 * Links: linkStar() follows active --k2-accent (tint). Profile compare uses pitch/chrome helpers.
 * H2H rivalry charts use h2hSubject* / h2hOpponent* (chrome + table-negative — same as poster).
 */
(function (global) {
    'use strict';

    function cssVar(name, fallback) {
        try {
            if (typeof document === 'undefined') {
                return fallback;
            }
            var value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return value || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function cssFloat(name, fallback) {
        var v = parseFloat(cssVar(name, String(fallback)));
        return isNaN(v) ? fallback : v;
    }

    function parseColorToRgb(color) {
        var s = String(color).trim();
        if (!s) {
            return null;
        }
        var rgb = s.match(/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/);
        if (rgb) {
            return {
                r: Math.round(Number(rgb[1])),
                g: Math.round(Number(rgb[2])),
                b: Math.round(Number(rgb[3]))
            };
        }
        var srgb = s.match(/^color\s*\(\s*srgb\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\)/i);
        if (srgb) {
            return {
                r: Math.round(Number(srgb[1]) * 255),
                g: Math.round(Number(srgb[2]) * 255),
                b: Math.round(Number(srgb[3]) * 255)
            };
        }
        var h = s.replace(/^#/, '');
        if (h.length === 3) {
            h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        }
        if (h.length === 6 && /^[0-9a-fA-F]+$/.test(h)) {
            return {
                r: parseInt(h.slice(0, 2), 16),
                g: parseInt(h.slice(2, 4), 16),
                b: parseInt(h.slice(4, 6), 16)
            };
        }
        return null;
    }

    function rgbString(rgb) {
        return 'rgb(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ')';
    }

    /** Mirrors theme.css: color-mix(in srgb, accent 85%, text-primary 15%). Tint-following. */
    function linkStarMixRgb() {
        var accent = parseColorToRgb(cssResolvedColorVar('--k2-accent', '#ffb74d'))
            || parseColorToRgb(cssVar('--k2-accent', '#ffb74d'));
        var primary = parseColorToRgb(cssResolvedColorVar('--k2-text-primary', '#d0d7de'))
            || parseColorToRgb(cssVar('--k2-text-primary', '#d0d7de'));
        if (!accent || !primary) {
            return null;
        }
        return {
            r: Math.round(accent.r * 0.85 + primary.r * 0.15),
            g: Math.round(accent.g * 0.85 + primary.g * 0.15),
            b: Math.round(accent.b * 0.85 + primary.b * 0.15)
        };
    }

    /** Mirrors theme.css: color-mix(in srgb, pure-amber 85%, text-primary 15%). Fixed hue. */
    function amberSoftMixRgb() {
        var amber = parseColorToRgb(cssResolvedColorVar('--k2-pure-amber', '#ffb74d'))
            || parseColorToRgb(cssVar('--k2-pure-amber', '#ffb74d'));
        var primary = parseColorToRgb(cssResolvedColorVar('--k2-text-primary', '#d0d7de'))
            || parseColorToRgb(cssVar('--k2-text-primary', '#d0d7de'));
        if (!amber || !primary) {
            return null;
        }
        return {
            r: Math.round(amber.r * 0.85 + primary.r * 0.15),
            g: Math.round(amber.g * 0.85 + primary.g * 0.15),
            b: Math.round(amber.b * 0.85 + primary.b * 0.15)
        };
    }

    function chartColor(varName, fallbackHex) {
        var resolved = cssResolvedColorVar(varName, '');
        if (resolved && parseColorToRgb(resolved)) {
            return resolved;
        }
        return cssVar(varName, fallbackHex);
    }

    /** Resolve a CSS color variable to computed rgb() (needed for color-mix tokens). */
    function cssResolvedColorVar(varName, fallbackHex) {
        try {
            if (typeof document === 'undefined') {
                return fallbackHex;
            }
            var root = document.documentElement;
            var probe = document.createElement('span');
            probe.style.setProperty('color', 'var(' + varName + ')');
            probe.setAttribute('aria-hidden', 'true');
            probe.style.position = 'absolute';
            probe.style.pointerEvents = 'none';
            probe.style.opacity = '0';
            root.appendChild(probe);
            var resolved = getComputedStyle(probe).color;
            root.removeChild(probe);
            if (resolved && resolved !== 'rgba(0, 0, 0, 0)') {
                return resolved;
            }
        } catch (e) {
            /* ignore */
        }
        return fallbackHex;
    }

    function colorToRgba(color, alpha, fallbackColor) {
        var parsed = parseColorToRgb(color) || parseColorToRgb(fallbackColor) || amberSoftMixRgb();
        if (!parsed) {
            parsed = { r: 232, g: 201, b: 168 };
        }
        return 'rgba(' + parsed.r + ', ' + parsed.g + ', ' + parsed.b + ', ' + alpha + ')';
    }

    function chartFontFamily() {
        try {
            if (typeof document !== 'undefined' && document.body) {
                return getComputedStyle(document.body).fontFamily;
            }
        } catch (e) {
            /* ignore */
        }
        return '"IBM Plex Sans", Verdana, Arial, sans-serif';
    }

    /** Resolve color-mix / var() tokens (color: probe misses background mixes). */
    function cssResolvedStyleVar(varName, styleProp, fallback) {
        try {
            if (typeof document === 'undefined') {
                return fallback;
            }
            var probe = document.createElement('span');
            probe.setAttribute('aria-hidden', 'true');
            probe.style.position = 'absolute';
            probe.style.opacity = '0';
            probe.style.pointerEvents = 'none';
            probe.style.setProperty(styleProp, 'var(' + varName + ')');
            document.documentElement.appendChild(probe);
            var resolved = getComputedStyle(probe).backgroundColor;
            document.documentElement.removeChild(probe);
            if (resolved && resolved !== 'rgba(0, 0, 0, 0)' && resolved !== 'transparent') {
                return resolved;
            }
        } catch (e) {
            /* ignore */
        }
        return fallback;
    }

    /** Matches theme.css .k2-table-tooltip (--k2-tooltip-* tokens). */
    function tooltipSurfaceColor() {
        return cssResolvedStyleVar('--k2-tooltip-surface', 'background-color', 'rgb(26, 34, 48)');
    }

    function tooltipBorderColor() {
        return cssResolvedStyleVar('--k2-tooltip-border', 'border-color', 'rgb(61, 68, 77)')
            || chartColor('--k2-accent', '#ffb74d');
    }

    function resolveTooltipDatasetColor(value, context) {
        if (value == null) {
            return null;
        }
        var resolved = value;
        if (typeof resolved === 'function') {
            resolved = resolved(context);
        }
        if (Array.isArray(resolved)) {
            var idx = context.dataIndex;
            resolved = resolved[idx != null ? idx : 0] || resolved[0];
        }
        if (!resolved || resolved === 'transparent') {
            return null;
        }
        return resolved;
    }

    /** Solid stroke for tooltip swatches (Chart.js default uses #fff multiKeyBackground + translucent fill). */
    function tooltipLabelColor(context) {
        var ds = context.dataset;
        var stroke = resolveTooltipDatasetColor(ds.borderColor, context)
            || resolveTooltipDatasetColor(ds.pointBorderColor, context)
            || resolveTooltipDatasetColor(ds.pointBackgroundColor, context)
            || resolveTooltipDatasetColor(ds.backgroundColor, context);
        if (!stroke) {
            stroke = cssVar('--k2-text-muted', '#8b949e');
        }
        return {
            borderColor: stroke,
            backgroundColor: stroke,
            borderWidth: 2,
            borderRadius: 2
        };
    }

    function tooltipThemeOptions() {
        var font = chartFontFamily();
        return {
            backgroundColor: tooltipSurfaceColor(),
            borderColor: tooltipBorderColor(),
            borderWidth: 1,
            titleColor: cssResolvedColorVar('--k2-tooltip-title', '#a8b3bf')
                || cssVar('--k2-text-secondary', '#a8b3bf'),
            bodyColor: cssResolvedColorVar('--k2-tooltip-body', '#d0d7de')
                || cssVar('--k2-text-primary', '#d0d7de'),
            footerColor: cssResolvedColorVar('--k2-tooltip-footer', '#8b949e')
                || cssVar('--k2-text-muted', '#8b949e'),
            padding: { top: 8, right: 10, bottom: 8, left: 10 },
            cornerRadius: cssFloat('--k2-radius-sm', 4),
            titleFont: { family: font, size: 12, weight: '600' },
            bodyFont: { family: font, size: 12, weight: 'normal' },
            footerFont: { family: font, size: 11, weight: 'normal' },
            boxPadding: 4,
            boxWidth: 10,
            boxHeight: 10,
            multiKeyBackground: tooltipSurfaceColor(),
            labelColor: tooltipLabelColor,
            caretSize: 6,
            caretPadding: 4
        };
    }

    function mergeTooltip(overrides) {
        var base = tooltipThemeOptions();
        if (!overrides) {
            return base;
        }
        var merged = Object.assign({}, base, overrides);
        if (overrides.callbacks) {
            merged.callbacks = Object.assign({}, overrides.callbacks);
        }
        return merged;
    }

    function applyChartTooltipDefaults() {
        if (typeof Chart === 'undefined' || !Chart.defaults || !Chart.defaults.plugins) {
            return;
        }
        var tip = Chart.defaults.plugins.tooltip;
        var themed = tooltipThemeOptions();
        Object.keys(themed).forEach(function (key) {
            tip[key] = themed[key];
        });
    }
    function isCoarsePointer() {
        try {
            return global.matchMedia('(pointer: coarse)').matches
                || global.matchMedia('(hover: none)').matches;
        } catch (e) {
            return false;
        }
    }

    function prefersReducedMotion() {
        try {
            return global.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (e) {
            return false;
        }
    }

    /** Set true to re-enable bar grow-up on Activity (server1). Off Jun 2026 — stutter WIP. */
    var ACTIVITY_BAR_ENTRANCE_ENABLED = false;

    function activityBarMotionEnabled() {
        return ACTIVITY_BAR_ENTRANCE_ENABLED;
    }

    function activityBarGrowDurationMs() {
        return isCoarsePointer() ? 440 : 560;
    }

    function activityBarGrowEasing() {
        return 'easeOutCubic';
    }

    function activityBarGrowAnimation(onComplete) {
        var anim = {
            duration: activityBarGrowDurationMs(),
            easing: activityBarGrowEasing()
        };
        if (onComplete) {
            anim.onComplete = onComplete;
        }
        return anim;
    }

    function activityBarClearHoverState(chart) {
        if (!chart) {
            return;
        }
        if (typeof chart.setActiveElements === 'function') {
            chart.setActiveElements([]);
        }
        if (chart.tooltip && typeof chart.tooltip.setActiveElements === 'function') {
            chart.tooltip.setActiveElements([], { x: 0, y: 0 });
        }
    }

    function activityBarPointY(pt) {
        if (pt === null || pt === undefined) {
            return 0;
        }
        if (typeof pt === 'object') {
            return Number(pt.y) || 0;
        }
        return Number(pt) || 0;
    }

    function activityBarDataMaxY(datasets) {
        var max = 0;
        var d;
        var i;
        if (!datasets || !datasets.length) {
            return max;
        }
        var stackId = datasets[0].stack;
        var len = (datasets[0].data && datasets[0].data.length) || 0;
        if (stackId) {
            for (i = 0; i < len; i++) {
                var sum = 0;
                for (d = 0; d < datasets.length; d++) {
                    if (datasets[d].stack !== stackId) {
                        continue;
                    }
                    sum += activityBarPointY(datasets[d].data[i]);
                }
                if (sum > max) {
                    max = sum;
                }
            }
        } else {
            for (d = 0; d < datasets.length; d++) {
                var data = datasets[d].data || [];
                for (i = 0; i < data.length; i++) {
                    var v = activityBarPointY(data[i]);
                    if (v > max) {
                        max = v;
                    }
                }
            }
        }
        return max;
    }

    function activityBarYAxisMax(dataMax) {
        if (dataMax <= 0) {
            return 1;
        }
        return Math.ceil(dataMax * 1.05);
    }

    /** Lock y-axis max during entrance so tick labels (and plot width) do not shift x. */
    function activityBarPinYScales(options, dataMax) {
        if (!options || !options.scales || dataMax <= 0) {
            return options;
        }
        var cap = activityBarYAxisMax(dataMax);
        var scales = options.scales;
        var key;
        var next = Object.assign({}, options, { scales: Object.assign({}, scales) });
        for (key in scales) {
            if (key === 'y' || key.charAt(0) === 'y') {
                next.scales[key] = Object.assign({}, scales[key], {
                    min: 0,
                    max: cap
                });
            }
        }
        return next;
    }

    function activityBarEntranceYFromPixel(chart, datasetIndex, dataIndex, el) {
        if (!el || !chart) {
            return undefined;
        }
        var dataset = chart.data.datasets[datasetIndex];
        if (dataset && dataset.stack) {
            var meta = chart.getDatasetMeta(datasetIndex);
            var yAxisID = (meta && meta.yAxisID) ? meta.yAxisID : 'y';
            var yScale = chart.scales[yAxisID];
            if (!yScale || typeof yScale.getPixelForValue !== 'function') {
                return el.base;
            }
            var stackId = dataset.stack;
            var baseValue = 0;
            var i;
            for (i = 0; i < datasetIndex; i++) {
                if (chart.data.datasets[i].stack === stackId) {
                    baseValue += activityBarPointY(chart.data.datasets[i].data[dataIndex]);
                }
            }
            return yScale.getPixelForValue(baseValue);
        }
        return el.base;
    }

    /** One layout pass while hidden; cache bar-foot pixels so `from` does not drift per frame. */
    function activityBarCacheEntranceYFrom(chart) {
        var cache = Object.create(null);
        var d;
        var meta;
        var i;
        var el;
        var key;
        if (!chart) {
            return cache;
        }
        for (d = 0; d < chart.data.datasets.length; d++) {
            meta = chart.getDatasetMeta(d);
            if (!meta || !meta.data) {
                continue;
            }
            for (i = 0; i < meta.data.length; i++) {
                el = meta.data[i];
                if (!el) {
                    continue;
                }
                key = d + ':' + i;
                cache[key] = activityBarEntranceYFromPixel(chart, d, i, el);
            }
        }
        chart.$k2BarEntranceYFrom = cache;
        return cache;
    }

    function activityBarGrowYFrom(ctx) {
        if (!ctx || !ctx.chart) {
            return undefined;
        }
        var cache = ctx.chart.$k2BarEntranceYFrom;
        var key = ctx.datasetIndex + ':' + ctx.dataIndex;
        if (cache && cache[key] !== undefined) {
            return cache[key];
        }
        return activityBarEntranceYFromPixel(ctx.chart, ctx.datasetIndex, ctx.dataIndex, ctx.element);
    }

    /** Bar top (y) only; layout props snap — easeOutCubic. */
    function activityBarEntranceAnimations() {
        var lock = { duration: 0 };
        var ms = activityBarGrowDurationMs();
        var ease = activityBarGrowEasing();
        return {
            x: lock,
            width: lock,
            base: lock,
            height: lock,
            y: {
                type: 'number',
                from: activityBarGrowYFrom,
                duration: ms,
                easing: ease
            },
            borderWidth: lock,
            backgroundColor: lock,
            borderColor: lock,
            colors: false
        };
    }

    function activityChartTransitions() {
        return {
            active: {
                animation: {
                    duration: 200
                }
            },
            resize: {
                animation: {
                    duration: 0
                }
            }
        };
    }

    /** Profile career stack — shared plot gutters (rating, games/month, goals histogram). */
    var CAREER_CHART_Y_AXIS_WIDTH = 48;
    var CAREER_CHART_LAYOUT_PADDING = { top: 0, right: 12, bottom: 0, left: 0 };

    function fixedYAxisFit(width) {
        var w = typeof width === 'number' && width > 0 ? width : CAREER_CHART_Y_AXIS_WIDTH;
        return {
            afterFit: function (axis) {
                axis.width = w;
            }
        };
    }

    function careerChartGutterOptions() {
        return {
            layout: {
                padding: Object.assign({}, CAREER_CHART_LAYOUT_PADDING)
            }
        };
    }

    function careerChartYAxisOptions(scaleOptions) {
        return Object.assign({}, scaleOptions || {}, fixedYAxisFit(CAREER_CHART_Y_AXIS_WIDTH));
    }

    /**
     * Per-chart options for Activity — does not mutate Chart.defaults.
     * @param {object} userOptions Chart.js options
     * @param {{ chartKind?: 'bar'|'line'|'none' }} meta chartKind 'bar' = grow-up (all devices; shorter on coarse)
     */
    function activityChartOptions(userOptions, meta) {
        var coarse = isCoarsePointer();
        var chartKind = (meta && meta.chartKind) || 'none';
        var animateBars = chartKind === 'bar' && activityBarMotionEnabled();
        var interaction = {
            mode: coarse ? 'nearest' : 'index',
            intersect: false
        };
        if (coarse) {
            interaction.axis = 'x';
        }
        var base = {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: animateBars ? 600 : 0,
            animation: animateBars ? activityBarGrowAnimation() : false,
            transitions: activityChartTransitions(),
            /* Phone: no touchstart — keeps vertical scroll; tooltips off (tap tooltips stuck scroll). */
            events: coarse
                ? ['mousemove', 'mouseout', 'click']
                : ['mousemove', 'mouseout', 'click', 'touchstart'],
            interaction: interaction,
            plugins: {
                legend: { labels: { color: cssVar('--k2-text-muted', '#8b949e') } },
                tooltip: coarse ? { enabled: false } : {}
            }
        };
        var u = userOptions || {};
        var merged = Object.assign({}, base, u);
        merged.interaction = Object.assign({}, interaction, u.interaction || {});
        merged.plugins = Object.assign({}, base.plugins, u.plugins || {});
        if (u.plugins && u.plugins.tooltip) {
            merged.plugins.tooltip = Object.assign({}, merged.plugins.tooltip);
        }
        if (coarse) {
            merged.plugins.tooltip = Object.assign({}, merged.plugins.tooltip, { enabled: false });
        }
        merged.transitions = Object.assign({}, activityChartTransitions(), u.transitions || {});
        if (animateBars) {
            if (u.animation === undefined) {
                merged.animation = activityBarGrowAnimation();
            }
            delete merged.animations;
        } else if (u.animations === undefined) {
            delete merged.animations;
        }
        merged.events = u.events || base.events;
        return merged;
    }

    function cloneChartDataPoints(data) {
        var out = [];
        var i;
        if (!data || !data.length) {
            return out;
        }
        for (i = 0; i < data.length; i++) {
            var pt = data[i];
            if (pt !== null && typeof pt === 'object') {
                out.push(Object.assign({}, pt));
            } else {
                out.push(pt);
            }
        }
        return out;
    }

    function zeroBarDataPoints(data) {
        var out = [];
        var i;
        if (!data || !data.length) {
            return out;
        }
        for (i = 0; i < data.length; i++) {
            var pt = data[i];
            if (pt !== null && typeof pt === 'object') {
                var z = Object.assign({}, pt);
                z.y = 0;
                out.push(z);
            } else {
                out.push(0);
            }
        }
        return out;
    }

    /**
     * Bar grow-up: silent y=0 mount (hidden), then one animated update() — user sees only the grow.
     * responsive: true so Chart.js owns canvas + devicePixelRatio (no manual bitmap sizing — avoids blur).
     */
    function createActivityChart(canvas, config, chartKind) {
        if (typeof Chart === 'undefined' || !canvas) {
            return null;
        }
        config = config || {};
        var kind = chartKind || 'none';
        var userOpts = config.options || {};
        var animatedOpts = activityChartOptions(userOpts, { chartKind: kind });

        if (kind !== 'bar' || !activityBarMotionEnabled()) {
            config.options = animatedOpts;
            return new Chart(canvas, config);
        }

        var frame = canvas.parentElement;
        var datasets = config.data && config.data.datasets;
        var savedSeries = [];
        var dataMax = 0;
        var i;

        if (datasets) {
            dataMax = activityBarDataMaxY(datasets);
            for (i = 0; i < datasets.length; i++) {
                savedSeries[i] = cloneChartDataPoints(datasets[i].data);
                datasets[i].data = zeroBarDataPoints(datasets[i].data);
            }
        }

        animatedOpts = activityBarPinYScales(animatedOpts, dataMax);

        if (frame) {
            frame.style.visibility = 'hidden';
        }

        config.options = Object.assign({}, animatedOpts, { animation: false });
        var chart = new Chart(canvas, config);

        if (datasets) {
            for (i = 0; i < datasets.length; i++) {
                datasets[i].data = savedSeries[i];
                if (chart.data && chart.data.datasets && chart.data.datasets[i]) {
                    chart.data.datasets[i].data = savedSeries[i];
                }
            }
        }

        chart.update('none');
        activityBarCacheEntranceYFrom(chart);

        var entranceRevealed = false;
        var entranceAnim = activityBarGrowAnimation(function () {
            var c = this;
            if (c) {
                delete c.$k2BarEntranceYFrom;
            }
            activityBarClearHoverState(c);
            if (frame) {
                frame.style.visibility = '';
            }
        });
        entranceAnim.onProgress = function () {
            if (!entranceRevealed && frame) {
                entranceRevealed = true;
                frame.style.visibility = '';
            }
        };
        chart.options.animation = entranceAnim;
        chart.options.animations = activityBarEntranceAnimations();
        chart.options.transitions = animatedOpts.transitions;

        requestAnimationFrame(function () {
            if (!chart || chart.destroyed) {
                if (frame) {
                    frame.style.visibility = '';
                }
                return;
            }
            if (typeof chart.stop === 'function') {
                chart.stop();
            }
            chart.update();
        });

        return chart;
    }

    function resizeActivityChart(canvas) {
        if (!canvas || typeof Chart === 'undefined' || typeof Chart.getChart !== 'function') {
            return;
        }
        var chart = Chart.getChart(canvas);
        if (chart && typeof chart.resize === 'function') {
            chart.resize(0);
        }
    }

    global.K2ChartTheme = {
        pureAmber: function () { return chartColor('--k2-pure-amber', '#ffb74d'); },
        purePitch: function () { return chartColor('--k2-pure-pitch', '#9ccc65'); },
        pureChrome: function () { return chartColor('--k2-pure-chrome', '#64b5f6'); },
        pureHolo: function () { return chartColor('--k2-pure-holo', '#b388ff'); },
        pitch: function () { return chartColor('--k2-chart-pitch', '#9ccc65'); },
        chrome: function () { return chartColor('--k2-chart-chrome', '#64b5f6'); },
        holo: function () { return chartColor('--k2-chart-holo', '#b388ff'); },
        /** Resolved rgb — Chart.js cannot parse var(--k2-chart-amber) / color-mix tokens. */
        amber: function () { return this.amberSoft(); },
        amberSoft: function () {
            var fromDom = parseColorToRgb(cssResolvedColorVar('--k2-amber-soft', ''));
            if (fromDom) {
                return rgbString(fromDom);
            }
            var mixed = amberSoftMixRgb();
            return mixed ? rgbString(mixed) : 'rgb(232, 201, 168)';
        },
        teal: function () { return chartColor('--k2-chart-teal', '#4db6ac'); },
        magenta: function () { return chartColor('--k2-chart-magenta', '#ff4081'); },
        accent: function () { return chartColor('--k2-accent', '#ffb74d'); },
        /** Link ink (85% active accent + 15% primary) â€” follows tint picker. */
        linkStar: function () {
            var fromDom = parseColorToRgb(cssResolvedColorVar('--k2-link-star', ''));
            if (fromDom) {
                return rgbString(fromDom);
            }
            var mixed = linkStarMixRgb();
            return mixed ? rgbString(mixed) : 'rgb(232, 201, 168)';
        },
        /** @deprecated use accent() */
        realm: function () { return this.accent(); },
        barFillAlpha: function () { return cssFloat('--k2-chart-bar-fill-alpha', 0.75); },
        barBorderWidth: function () { return cssFloat('--k2-chart-bar-border-width', 1); },
        lineAreaAlpha: function () { return cssFloat('--k2-chart-line-area-alpha', 0.12); },
        lineBorderWidth: function () { return cssFloat('--k2-chart-line-border-width', 2); },
        /** Bar charts (Activity staging style): thin edge + glass fill */
        barStroke: function (color, fillAlpha) {
            var a = fillAlpha == null ? this.barFillAlpha() : fillAlpha;
            return {
                backgroundColor: this.fill(color, a),
                borderColor: color,
                borderWidth: this.barBorderWidth()
            };
        },
        /** Dense bar charts where 1px borders create noise on narrow screens */
        barSolid: function (color, fillAlpha) {
            var a = fillAlpha == null ? this.barFillAlpha() : fillAlpha;
            return {
                backgroundColor: this.fill(color, a),
                borderColor: color,
                borderWidth: 0
            };
        },
        /** Line / area charts */
        lineStroke: function (color, fillAlpha) {
            var a = fillAlpha == null ? this.lineAreaAlpha() : fillAlpha;
            return {
                borderColor: color,
                backgroundColor: this.fill(color, a),
                borderWidth: this.lineBorderWidth()
            };
        },
        profileCompareBorder: function () { return this.pitch(); },
        profileCompareFill: function (alpha) { return this.fill(this.pitch(), alpha == null ? 0.12 : alpha); },
        opponentFocusBorder: function () { return this.chrome(); },
        opponentFocusFill: function (alpha) { return this.fill(this.chrome(), alpha == null ? 0.12 : alpha); },
        /** Table loss/red ink (color-mix) — H2H poster `.red`, not chart magenta (milestones). */
        tableNegative: function () {
            return cssResolvedColorVar('--k2-table-negative', '#d48a9a')
                || chartColor('--k2-stat-negative-base', '#f06292');
        },
        /** H2H pair charts: subject = chart chrome (aliases pure chrome). */
        h2hSubjectBorder: function () { return this.chrome(); },
        h2hSubjectFill: function (alpha) { return this.fill(this.chrome(), alpha == null ? 0.12 : alpha); },
        /** H2H pair charts: opponent = table-negative red (not T.magenta()). */
        h2hOpponentBorder: function () { return this.tableNegative(); },
        h2hOpponentFill: function (alpha) { return this.fill(this.tableNegative(), alpha == null ? 0.12 : alpha); },
        textPrimary: function () { return cssVar('--k2-text-primary', '#d0d7de'); },
        textSecondary: function () { return cssVar('--k2-text-secondary', '#a8b3bf'); },
        textMuted: function () { return cssVar('--k2-text-muted', '#8b949e'); },
        grid: function () { return 'rgba(255, 255, 255, 0.08)'; },
        softGrid: function () { return 'rgba(255, 255, 255, 0.045)'; },
        fill: function (color, alpha) {
            return colorToRgba(color, alpha, color);
        },
        legendColor: function () { return this.textMuted(); },
        tickColor: function () { return this.textMuted(); },
        /** Chart.js plugin options aligned with .k2-table-tooltip */
        tooltipDefaults: tooltipThemeOptions,
        mergeTooltip: mergeTooltip,
        applyTooltipDefaults: applyChartTooltipDefaults,
        activityChartOptions: activityChartOptions,
        createActivityChart: createActivityChart,
        careerChartGutterOptions: careerChartGutterOptions,
        careerChartYAxisOptions: careerChartYAxisOptions,
        resizeActivityChart: resizeActivityChart,
        activityBarMotionEnabled: activityBarMotionEnabled,
        isCoarsePointer: isCoarsePointer,
        prefersReducedMotion: prefersReducedMotion
    };

    applyChartTooltipDefaults();
})(typeof window !== 'undefined' ? window : this);
