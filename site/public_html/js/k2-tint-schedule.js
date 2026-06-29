/**
 * Six-hour tint rotation (holo → pitch → chrome → amber) + per-period manual override.
 * Boundaries at 04:00, 10:00, 16:00, 22:00 (visitor local time by default).
 * Manual pill choice applies only for the current six-hour window; next period uses schedule again.
 */
(function (global) {
    'use strict';

    var VALID_ACCENTS = ['holo', 'pitch', 'chrome', 'amber'];
    var DEFAULT_ACCENT = 'amber';
    /** Period start hours (local/UTC per clock setting). */
    var PERIOD_BOUNDARY_HOURS = [4, 10, 16, 22];
    var ACCENT_KEY = 'k2-accent-tune';
    var PERIOD_KEY = 'k2-accent-manual-period';
    var CLOCK_KEY = 'k2-accent-clock';
    /** @deprecated — cleared when stale; period id is authoritative */
    var LEGACY_MANUAL_KEY = 'k2-accent-manual';

    function isValidAccent(accent) {
        return accent && VALID_ACCENTS.indexOf(accent) !== -1;
    }

    function readLocal(key) {
        try {
            return localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function writeLocal(key, value) {
        try {
            localStorage.setItem(key, value);
        } catch (e) {
            /* ignore */
        }
    }

    function removeLocal(key) {
        try {
            localStorage.removeItem(key);
        } catch (e) {
            /* ignore */
        }
    }

    function readSession(key) {
        try {
            return sessionStorage.getItem(key);
        } catch (e) {
            return null;
        }
    }

    function removeSession(key) {
        try {
            sessionStorage.removeItem(key);
        } catch (e) {
            /* ignore */
        }
    }

    function useUtcClock() {
        return readLocal(CLOCK_KEY) === 'utc';
    }

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    /** Stable id for the active six-hour window (calendar day + slot 0–3). */
    function periodKey(date) {
        var useUtc = useUtcClock();
        var when = date || new Date();
        var slot = periodIndex(when, useUtc);
        var hour = useUtc ? when.getUTCHours() : when.getHours();
        var anchor = when;

        /* Amber (slot 3) runs 22:00–03:59; before 04:00 belongs to yesterday's evening start. */
        if (slot === 3 && hour < PERIOD_BOUNDARY_HOURS[0]) {
            anchor = new Date(when.getTime());
            if (useUtc) {
                anchor.setUTCDate(anchor.getUTCDate() - 1);
            } else {
                anchor.setDate(anchor.getDate() - 1);
            }
        }

        var y = useUtc ? anchor.getUTCFullYear() : anchor.getFullYear();
        var mo = useUtc ? anchor.getUTCMonth() + 1 : anchor.getMonth() + 1;
        var d = useUtc ? anchor.getUTCDate() : anchor.getDate();
        return y + '-' + pad2(mo) + '-' + pad2(d) + '-' + slot;
    }

    /** Hour bucket 0–3: holo 04–09, pitch 10–15, chrome 16–21, amber 22–03. */
    function periodIndex(date, useUtc) {
        var hour = useUtc ? date.getUTCHours() : date.getHours();
        if (hour >= 4 && hour < 10) {
            return 0;
        }
        if (hour >= 10 && hour < 16) {
            return 1;
        }
        if (hour >= 16 && hour < 22) {
            return 2;
        }
        return 3;
    }

    function nextBoundaryDate(date, useUtc) {
        var when = date || new Date();
        var y = useUtc ? when.getUTCFullYear() : when.getFullYear();
        var mo = useUtc ? when.getUTCMonth() : when.getMonth();
        var d = useUtc ? when.getUTCDate() : when.getDate();
        var hour = useUtc ? when.getUTCHours() : when.getHours();
        var i;

        for (i = 0; i < PERIOD_BOUNDARY_HOURS.length; i++) {
            if (hour < PERIOD_BOUNDARY_HOURS[i]) {
                if (useUtc) {
                    return new Date(Date.UTC(y, mo, d, PERIOD_BOUNDARY_HOURS[i], 0, 0, 0));
                }
                return new Date(y, mo, d, PERIOD_BOUNDARY_HOURS[i], 0, 0, 0);
            }
        }

        if (useUtc) {
            return new Date(Date.UTC(y, mo, d + 1, PERIOD_BOUNDARY_HOURS[0], 0, 0, 0));
        }
        return new Date(y, mo, d + 1, PERIOD_BOUNDARY_HOURS[0], 0, 0, 0);
    }

    function scheduledAccent(date) {
        var useUtc = useUtcClock();
        var when = date || new Date();
        return VALID_ACCENTS[periodIndex(when, useUtc)] || DEFAULT_ACCENT;
    }

    function msUntilNextPeriod(date) {
        var useUtc = useUtcClock();
        var when = date || new Date();
        var next = nextBoundaryDate(when, useUtc);
        return Math.max(0, next.getTime() - when.getTime());
    }

    function clearManualStorage() {
        removeLocal(ACCENT_KEY);
        removeLocal(PERIOD_KEY);
        removeLocal(LEGACY_MANUAL_KEY);
    }

    /** Drop manual keys when they belong to a previous six-hour window. */
    function clearManualIfStale(date) {
        var savedPeriod = readLocal(PERIOD_KEY);
        if (!savedPeriod) {
            removeLocal(LEGACY_MANUAL_KEY);
            return;
        }
        if (savedPeriod !== periodKey(date)) {
            clearManualStorage();
        }
    }

    function migrateSessionAccent() {
        var accent = readSession(ACCENT_KEY);
        if (!isValidAccent(accent)) {
            return null;
        }
        writeLocal(ACCENT_KEY, accent);
        writeLocal(PERIOD_KEY, periodKey());
        removeSession(ACCENT_KEY);
        removeLocal(LEGACY_MANUAL_KEY);
        return accent;
    }

    /** Manual pick for the current period only; null if none or expired. */
    function manualAccentForCurrentPeriod(date) {
        clearManualIfStale(date);
        if (readLocal(PERIOD_KEY) !== periodKey(date)) {
            return null;
        }
        var accent = readLocal(ACCENT_KEY);
        if (isValidAccent(accent)) {
            return accent;
        }
        accent = migrateSessionAccent();
        if (isValidAccent(accent) && readLocal(PERIOD_KEY) === periodKey(date)) {
            return accent;
        }
        return null;
    }

    function isManualOverride(date) {
        return manualAccentForCurrentPeriod(date) !== null;
    }

    /**
     * Accent to apply on load: scheduled slot, unless visitor picked a pill this period.
     */
    function resolveAccent(date) {
        var manual = manualAccentForCurrentPeriod(date);
        if (manual) {
            return manual;
        }
        return scheduledAccent(date);
    }

    function applyAccentToRoot(root, accent) {
        if (!root) {
            return;
        }
        root.setAttribute('data-k2-accent', isValidAccent(accent) ? accent : DEFAULT_ACCENT);
    }

    function setManualAccent(accent, date) {
        if (!isValidAccent(accent)) {
            return;
        }
        writeLocal(ACCENT_KEY, accent);
        writeLocal(PERIOD_KEY, periodKey(date));
        removeLocal(LEGACY_MANUAL_KEY);
        removeSession(ACCENT_KEY);
    }

    /** Called when a six-hour boundary passes — revert to schedule unless user picks again. */
    function onPeriodBoundary(date) {
        clearManualIfStale(date);
        return resolveAccent(date);
    }

    global.K2TintSchedule = {
        VALID_ACCENTS: VALID_ACCENTS,
        DEFAULT_ACCENT: DEFAULT_ACCENT,
        ACCENT_KEY: ACCENT_KEY,
        PERIOD_KEY: PERIOD_KEY,
        CLOCK_KEY: CLOCK_KEY,
        isValidAccent: isValidAccent,
        useUtcClock: useUtcClock,
        periodKey: periodKey,
        scheduledAccent: scheduledAccent,
        msUntilNextPeriod: msUntilNextPeriod,
        isManualOverride: isManualOverride,
        manualAccentForCurrentPeriod: manualAccentForCurrentPeriod,
        resolveAccent: resolveAccent,
        applyAccentToRoot: applyAccentToRoot,
        setManualAccent: setManualAccent,
        onPeriodBoundary: onPeriodBoundary,
        clearManualIfStale: clearManualIfStale
    };
})(typeof window !== 'undefined' ? window : this);
