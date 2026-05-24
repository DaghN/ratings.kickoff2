/**
 * Chart.js colours aligned with stylesheets/theme.css tokens.
 * Load before chart init scripts on themed pages.
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
        green: function () { return cssVar('--k2-chart-green', '#9ccc65'); },
        blue: function () { return cssVar('--k2-chart-blue', '#64b5f6'); },
        amber: function () { return cssVar('--k2-chart-amber', '#ffb74d'); },
        coral: function () { return cssVar('--k2-chart-coral', '#ff8a65'); },
        teal: function () { return cssVar('--k2-chart-teal', '#4db6ac'); },
        purple: function () { return cssVar('--k2-chart-purple', '#ba68c8'); },
        accent: function () { return cssVar('--k2-accent', '#ffb74d'); },
        /** @deprecated use accent() — alias for older call sites */
        realm: function () { return this.accent(); },
        /** Profile subject (this player) on matchup charts */
        profileCompareBorder: function () { return this.green(); },
        profileCompareFill: function (alpha) { return this.fill(this.green(), alpha == null ? 0.12 : alpha); },
        /** Selected opponent on profile matchup charts */
        opponentFocusBorder: function () { return this.blue(); },
        opponentFocusFill: function (alpha) { return this.fill(this.blue(), alpha == null ? 0.12 : alpha); },
        textPrimary: function () { return cssVar('--k2-text-primary', '#d0d7de'); },
        textMuted: function () { return cssVar('--k2-text-muted', '#8b949e'); },
        grid: function () { return 'rgba(255, 255, 255, 0.08)'; },
        fill: hexToRgba,
        legendColor: function () { return this.textPrimary(); },
        tickColor: function () { return this.textMuted(); }
    };
})(typeof window !== 'undefined' ? window : this);
