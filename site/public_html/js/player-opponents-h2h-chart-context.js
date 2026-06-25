/**
 * Shared realm / time-travel context for Opponents H2H chart scripts.
 */
(function () {
    'use strict';

    function h2hRootFrom(el) {
        if (!el || !el.closest) {
            return null;
        }
        return el.closest('.k2-player-opponents-h2h') || el.closest('.k2-h2h2-charts');
    }

    function realmFrom(el) {
        var root = h2hRootFrom(el);
        if (root) {
            var realm = root.getAttribute('data-realm') || root.getAttribute('data-chart-realm');
            if (realm) {
                return realm;
            }
        }
        if (document.documentElement) {
            var docRealm = document.documentElement.getAttribute('data-realm');
            if (docRealm) {
                return docRealm;
            }
        }
        return 'online';
    }

    function asParam() {
        try {
            var sp = new URLSearchParams(window.location.search);
            var asVal = sp.get('as');
            return asVal ? String(asVal) : '';
        } catch (e) {
            return '';
        }
    }

    function apiSuffix(el) {
        var suffix = '&realm=' + encodeURIComponent(realmFrom(el));
        if (realmFrom(el) === 'amiga') {
            var asVal = asParam();
            if (asVal) {
                suffix += '&as=' + encodeURIComponent(asVal);
            }
        }
        return suffix;
    }

    function gamesBasePath(el) {
        return realmFrom(el) === 'amiga' ? '/amiga/player/games.php' : '/player/games.php';
    }

    function gamesHash(el) {
        return realmFrom(el) === 'amiga' ? '#matching-games' : '#k2-player-games-filters';
    }

    function gamesListUrl(el, playerId, queryParams) {
        var url = gamesBasePath(el) + '?id=' + encodeURIComponent(String(playerId));
        var key;
        for (key in queryParams) {
            if (!Object.prototype.hasOwnProperty.call(queryParams, key)) {
                continue;
            }
            if (queryParams[key] == null || queryParams[key] === '') {
                continue;
            }
            url += '&' + key + '=' + encodeURIComponent(String(queryParams[key]));
        }
        if (realmFrom(el) === 'amiga') {
            var asVal = asParam();
            if (asVal) {
                url += '&as=' + encodeURIComponent(asVal);
            }
        }
        return url + gamesHash(el);
    }

    window.K2PlayerOpponentsH2hContext = {
        h2hRootFrom: h2hRootFrom,
        realmFrom: realmFrom,
        asParam: asParam,
        apiSuffix: apiSuffix,
        gamesBasePath: gamesBasePath,
        gamesHash: gamesHash,
        gamesListUrl: gamesListUrl
    };
})();