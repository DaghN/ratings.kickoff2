/**
 * Chart.js colours aligned with stylesheets/theme.css tokens.
 * Load before chart init scripts on themed pages.
 *
 * Primitives: --k2-pure-* (hex). Chart roles: --k2-chart-* (amber â†’ --k2-amber-soft).
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

    /** Bar grow-up on all pointers; off when OS requests reduced motion. */
    function activityBarMotionEnabled() {
        return !prefersReducedMotion();
    }

    function activityBarGrowDurationMs() {
        return isCoarsePointer() ? 420 : 520;
    }

    function activityBarGrowAnimation() {
        return {
            duration: activityBarGrowDurationMs(),
            easing: 'easeOutQuart'
        };
    }

    /** Pixel Y where the bar top starts (baseline or stack foot) — not 0 (top of canvas). */
    function activityBarGrowYFrom(ctx) {
        if (!ctx || ctx.type !== 'data') {
            return undefined;
        }
        var chart = ctx.chart;
        var meta = chart.getDatasetMeta(ctx.datasetIndex);
        var yAxisID = (meta && meta.yAxisID) ? meta.yAxisID : 'y';
        var yScale = chart.scales[yAxisID];
        if (!yScale || typeof yScale.getPixelForValue !== 'function') {
            return undefined;
        }
        var datasets = chart.data.datasets;
        var dataset = datasets[ctx.datasetIndex];
        var baseValue = 0;
        if (dataset && dataset.stack) {
            var stackId = dataset.stack;
            var i;
            for (i = 0; i < ctx.datasetIndex; i++) {
                if (datasets[i].stack === stackId) {
                    var raw = datasets[i].data[ctx.dataIndex];
                    var n = typeof raw === 'object' && raw !== null ? raw.y : raw;
                    baseValue += Number(n) || 0;
                }
            }
        }
        return yScale.getPixelForValue(baseValue);
    }

    function activityBarGrowAnimations() {
        var duration = activityBarGrowDurationMs();
        return {
            y: {
                type: 'number',
                from: activityBarGrowYFrom,
                duration: duration,
                easing: 'easeOutQuart'
            }
        };
    }

    function activityChartTransitions() {
        return {
            resize: {
                animation: {
                    duration: 0
                }
            }
        };
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
            maintainAspectRatio: true,
            animation: animateBars ? activityBarGrowAnimation() : false,
            animations: animateBars ? activityBarGrowAnimations() : false,
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
        if (animateBars && u.animation === undefined) {
            merged.animation = activityBarGrowAnimation();
            merged.animations = activityBarGrowAnimations();
        }
        merged.events = u.events || base.events;
        return merged;
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
        activityBarMotionEnabled: activityBarMotionEnabled,
        isCoarsePointer: isCoarsePointer,
        prefersReducedMotion: prefersReducedMotion
    };

    applyChartTooltipDefaults();
})(typeof window !== 'undefined' ? window : this);
