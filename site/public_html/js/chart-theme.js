/**
 * Chart.js colours aligned with stylesheets/theme.css tokens.
 * Load before chart init scripts on themed pages.
 *
 * Canonical chart palette (six): pitch, chrome, holo, amber, teal, magenta.
 * Profile compare uses profileCompare* / opponentFocus* role helpers.
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

    function hexToRgba(hex, alpha) {
        var h = String(hex).replace('#', '');
        if (h.length === 3) {
            h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        }
        if (h.length !== 6) {
            return 'rgba(156, 204, 101, ' + alpha + ')';
        }
        var r = parseInt(h.slice(0, 2), 16);
        var g = parseInt(h.slice(2, 4), 16);
        var b = parseInt(h.slice(4, 6), 16);
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    global.K2ChartTheme = {
        pitch: function () { return cssVar('--k2-chart-pitch', '#9ccc65'); },
        chrome: function () { return cssVar('--k2-chart-chrome', '#64b5f6'); },
        holo: function () { return cssVar('--k2-chart-holo', '#b388ff'); },
        amber: function () { return cssVar('--k2-chart-amber', '#ffb74d'); },
        teal: function () { return cssVar('--k2-chart-teal', '#4db6ac'); },
        magenta: function () { return cssVar('--k2-chart-magenta', '#ff4081'); },
        accent: function () { return cssVar('--k2-accent', '#ffb74d'); },
        /** @deprecated use accent() */
        realm: function () { return this.accent(); },
        barFillAlpha: function () { return cssFloat('--k2-chart-bar-fill-alpha', 0.65); },
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
        textMuted: function () { return cssVar('--k2-text-muted', '#8b949e'); },
        grid: function () { return 'rgba(255, 255, 255, 0.08)'; },
        softGrid: function () { return 'rgba(255, 255, 255, 0.045)'; },
        fill: hexToRgba,
        legendColor: function () { return this.textPrimary(); },
        tickColor: function () { return this.textMuted(); }
    };
})(typeof window !== 'undefined' ? window : this);
