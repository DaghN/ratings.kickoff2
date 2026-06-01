/**
 * Chart.js colours aligned with stylesheets/theme.css tokens.
 * Load before chart init scripts on themed pages.
 *
 * Primitives: --k2-pure-* (hex). Chart roles: --k2-chart-* (amber → --k2-amber-soft).
 * Links: linkStar() follows active --k2-accent (tint). Profile compare uses pitch/chrome helpers.
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

    var blockInitQueue = [];
    var blockInitDraining = false;
    var BLOCK_INIT_GAP_MS = 50;

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
            return !!(global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches);
        } catch (e) {
            return false;
        }
    }

    function drainBlockInitQueue() {
        if (!blockInitQueue.length) {
            blockInitDraining = false;
            return;
        }
        blockInitDraining = true;
        blockInitQueue.sort(function (a, b) {
            return a.priority - b.priority;
        });
        var item = blockInitQueue.shift();
        try {
            item.run();
        } catch (e) {
            /* ignore */
        }
        if (blockInitQueue.length) {
            setTimeout(drainBlockInitQueue, BLOCK_INIT_GAP_MS);
        } else {
            blockInitDraining = false;
        }
    }

    function enqueueBlockInit(run, priority) {
        blockInitQueue.push({
            run: run,
            priority: priority == null ? 50 : priority
        });
        if (!blockInitDraining) {
            drainBlockInitQueue();
        }
    }

    /**
     * Queue Activity chart fetch/build in priority order on page load.
     * (Viewport deferral removed — fast scroll skipped IntersectionObserver and stalled charts.)
     */
    function whenBlockVisible(block, callback, priority) {
        if (!block || typeof callback !== 'function') {
            return;
        }
        if (block.getAttribute('data-k2-chart-init') === '1') {
            return;
        }
        block.setAttribute('data-k2-chart-init', '1');
        enqueueBlockInit(callback, priority);
    }

    function activateChartAtEvent(chart, evt, elements) {
        if (!chart || !evt) {
            return;
        }
        var pos = { x: evt.x, y: evt.y };
        if (elements && elements.length) {
            chart.setActiveElements(elements);
            if (chart.tooltip) {
                chart.tooltip.setActiveElements(elements, pos);
            }
        } else {
            chart.setActiveElements([]);
            if (chart.tooltip) {
                chart.tooltip.setActiveElements([], pos);
            }
        }
        chart.update();
    }

    function pickChartElements(chart, evt) {
        var interaction = chart.options.interaction || {};
        var mode = interaction.mode || 'nearest';
        try {
            return chart.getElementsAtEventForMode(evt, mode, {
                intersect: interaction.intersect === true,
                axis: interaction.axis
            }, false);
        } catch (err) {
            return [];
        }
    }

    /** Touch: tap shows tooltip; mouse: hover as usual. */
    function chartPointerOptions() {
        if (isCoarsePointer()) {
            return {
                events: ['touchstart', 'click', 'mousemove', 'mouseout'],
                interaction: {
                    mode: 'nearest',
                    intersect: false,
                    axis: 'x'
                },
                plugins: {
                    tooltip: {
                        enabled: true,
                        events: ['touchstart', 'click']
                    }
                }
            };
        }
        return {
            events: ['mousemove', 'mouseout', 'click', 'touchstart', 'touchmove'],
            interaction: {
                mode: 'index',
                intersect: false
            }
        };
    }

    function cloneChartDataPoint(pt) {
        if (pt === null || pt === undefined) {
            return pt;
        }
        if (typeof pt !== 'object') {
            return pt;
        }
        var copy = {};
        for (var key in pt) {
            if (Object.prototype.hasOwnProperty.call(pt, key)) {
                copy[key] = pt[key];
            }
        }
        return copy;
    }

    function zeroDatasetValues(data) {
        var zeroed = [];
        for (var i = 0; i < data.length; i++) {
            var pt = data[i];
            if (pt !== null && typeof pt === 'object' && typeof pt.y === 'number') {
                var z = cloneChartDataPoint(pt);
                z.y = 0;
                zeroed.push(z);
            } else if (typeof pt === 'number') {
                zeroed.push(0);
            } else {
                zeroed.push(pt);
            }
        }
        return zeroed;
    }

    /**
     * Bar grow: mount at y=0 then chart.update() to real values (works on desktop + mobile).
     */
    function createBarChart(canvas, config) {
        if (typeof Chart === 'undefined') {
            return null;
        }
        var animate = !prefersReducedMotion();
        var datasets = config.data && config.data.datasets;
        var saved = null;
        config.options = config.options || {};
        var wasResponsive = config.options.responsive !== false;
        var priorAnimation = config.options.animation;

        if (animate && datasets && datasets.length) {
            saved = [];
            for (var d = 0; d < datasets.length; d++) {
                saved.push(datasets[d].data);
                datasets[d].data = zeroDatasetValues(datasets[d].data || []);
            }
            config.options.responsive = false;
            config.options.animation = false;
        }

        var chart = new Chart(canvas, config);

        if (animate && saved) {
            var animation = priorAnimation || {
                duration: 700,
                easing: 'easeOutCubic',
                onComplete: function () {
                    if (!this || !this.options) {
                        return;
                    }
                    this.options.responsive = wasResponsive;
                    if (this.options.transitions && this.options.transitions.resize
                        && this.options.transitions.resize.animation) {
                        this.options.transitions.resize.animation.duration = 0;
                    }
                    if (wasResponsive) {
                        this.resize();
                    }
                }
            };
            config.options.animation = animation;
            chart.options.animation = animation;
            for (var d2 = 0; d2 < datasets.length; d2++) {
                datasets[d2].data = saved[d2];
                if (chart.data && chart.data.datasets && chart.data.datasets[d2]) {
                    chart.data.datasets[d2].data = saved[d2];
                }
            }
            chart.update();
        }
        return chart;
    }

    function createChart(canvas, config) {
        if (!config) {
            return null;
        }
        if (config.type === 'bar') {
            return createBarChart(canvas, config);
        }
        return new Chart(canvas, config);
    }

    function chartMotionOptions(chartKind) {
        if (prefersReducedMotion()) {
            return { animation: false };
        }
        var duration = chartKind === 'bar' ? 700 : 500;
        var opts = {
            animation: {
                duration: duration,
                easing: 'easeOutQuart'
            }
        };
        return opts;
    }

    function mergeChartOptions(userOptions, chartKind) {
        var base = userOptions || {};
        var motion = chartMotionOptions(chartKind);
        var pointer = chartPointerOptions();
        var merged = Object.assign({}, base, motion);
        merged.events = pointer.events;
        merged.interaction = Object.assign({}, pointer.interaction, base.interaction || {});
        merged.plugins = Object.assign({}, base.plugins || {});
        var pointerTip = pointer.plugins && pointer.plugins.tooltip;
        merged.plugins.tooltip = Object.assign(
            { enabled: true },
            pointerTip || {},
            merged.plugins.tooltip || {}
        );
        return merged;
    }

    function registerK2ChartPlugins() {
        if (typeof Chart === 'undefined' || registerK2ChartPlugins.done) {
            return;
        }
        registerK2ChartPlugins.done = true;

        Chart.register({
            id: 'k2TouchPointer',
            afterEvent: function (chart, args) {
                if (!isCoarsePointer() || !chart || !args || !args.event) {
                    return;
                }
                var e = args.event;
                if (e.type !== 'touchstart') {
                    return;
                }
                var items = pickChartElements(chart, e);
                activateChartAtEvent(chart, e, items);
            }
        });
    }

    function applyChartMotionDefaults() {
        if (typeof Chart === 'undefined' || !Chart.defaults) {
            return;
        }
        var pointer = chartPointerOptions();
        Chart.defaults.events = pointer.events;
        Chart.defaults.interaction = Object.assign({}, Chart.defaults.interaction, pointer.interaction);
        if (!prefersReducedMotion()) {
            Chart.defaults.animation = {
                duration: 600,
                easing: 'easeOutQuart'
            };
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
        amber: function () { return chartColor('--k2-chart-amber', '#ffb74d'); },
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
        /** Link ink (85% active accent + 15% primary) — follows tint picker. */
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
        whenBlockVisible: whenBlockVisible,
        mergeChartOptions: mergeChartOptions,
        createChart: createChart,
        createBarChart: createBarChart,
        applyMotionDefaults: applyChartMotionDefaults
    };

    applyChartTooltipDefaults();
    applyChartMotionDefaults();
    registerK2ChartPlugins();
})(typeof window !== 'undefined' ? window : this);
