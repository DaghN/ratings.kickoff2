/**
 * Amiga time travel — carry `as=` + `as_with=` on client-built navigation URLs.
 *
 * Chart/API fetches need only `as=` (snapshot cutoff); use navigation helpers for hrefs.
 *
 * @see docs/with-player-stepper-policy.md
 */
(function (global) {
    'use strict';

    function paramsFromLocation() {
        try {
            return new URLSearchParams(global.location.search);
        } catch (e) {
            return new URLSearchParams();
        }
    }

    /** Append active TT params to URLSearchParams (profile picks, etc.). */
    function appendParams(params) {
        var current = paramsFromLocation();
        var asParam = current.get('as');
        if (asParam !== null && asParam !== '') {
            params.set('as', String(asParam));
        }
        var asWith = current.get('as_with');
        if (asWith !== null && asWith !== '') {
            params.set('as_with', String(asWith));
        }
    }

    /** Query suffix for manual URL strings, e.g. `?id=1` + suffix. */
    function navigationQuerySuffix() {
        var suffix = '';
        var current = paramsFromLocation();
        var asVal = current.get('as');
        if (asVal !== null && asVal !== '') {
            suffix += '&as=' + encodeURIComponent(String(asVal));
        }
        var asWith = current.get('as_with');
        if (asWith !== null && asWith !== '') {
            suffix += '&as_with=' + encodeURIComponent(String(asWith));
        }
        return suffix;
    }

    global.K2AmigaTimeTravelUrl = {
        appendParams: appendParams,
        navigationQuerySuffix: navigationQuerySuffix
    };
}(typeof window !== 'undefined' ? window : this));