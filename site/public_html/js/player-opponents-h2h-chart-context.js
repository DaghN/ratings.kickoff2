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

    function h2hGrainFrom(el) {
        var root = h2hRootFrom(el);
        if (root && root.getAttribute('data-h2h-grain') === 'country') {
            return 'country';
        }
        if (el && el.closest) {
            var charts = el.closest('.k2-h2h2-charts');
            if (charts && charts.getAttribute('data-h2h-grain') === 'country') {
                return 'country';
            }
        }
        return 'player';
    }

    function opponentCountryFrom(el) {
        if (!el) {
            return '';
        }
        if (el.getAttribute && el.getAttribute('data-opponent-country')) {
            return el.getAttribute('data-opponent-country');
        }
        var root = h2hRootFrom(el);
        if (root) {
            var fromRoot = root.getAttribute('data-chart-country');
            if (fromRoot) {
                return fromRoot;
            }
        }
        return '';
    }

    function matchupApiQuery(el, opponentId, options) {
        options = options || {};
        if (h2hGrainFrom(el) === 'country') {
            var country = opponentCountryFrom(el) || options.country || '';
            if (!country) {
                return '';
            }
            var q = '&opp_country=' + encodeURIComponent(country);
            if (options.side) {
                q += '&side=' + encodeURIComponent(options.side);
            }
            return q;
        }
        if (opponentId) {
            return '&opponent=' + encodeURIComponent(String(opponentId));
        }
        if (el && el.getAttribute && el.getAttribute('data-opponent-id')) {
            return '&opponent=' + encodeURIComponent(el.getAttribute('data-opponent-id'));
        }
        return '';
    }

    function gamesMatchupParams(el, opponentId) {
        if (h2hGrainFrom(el) === 'country') {
            var country = opponentCountryFrom(el);
            if (country) {
                return { opp_country: country };
            }
            return {};
        }
        if (opponentId) {
            return { opponent: opponentId };
        }
        return {};
    }

    function initialMatchupFromPage(el) {
        var country = opponentCountryFrom(el);
        if (country && h2hGrainFrom(el) === 'country') {
            return { type: 'country', id: country, name: '' };
        }
        var h2hRoot = h2hRootFrom(el);
        if (h2hRoot) {
            var oppId = h2hRoot.getAttribute('data-chart-opponent-id');
            if (oppId) {
                return {
                    type: 'player',
                    id: oppId,
                    name: h2hRoot.getAttribute('data-chart-opponent-name') || ''
                };
            }
            var chartCountry = h2hRoot.getAttribute('data-chart-country');
            if (chartCountry) {
                return { type: 'country', id: chartCountry, name: '' };
            }
        }
        if (el && el.getAttribute) {
            var staticOpp = el.getAttribute('data-opponent-id');
            if (staticOpp) {
                return { type: 'player', id: staticOpp, name: '' };
            }
            country = el.getAttribute('data-opponent-country');
            if (country) {
                return { type: 'country', id: country, name: '' };
            }
        }
        return null;
    }

    window.K2PlayerOpponentsH2hContext = {
        h2hRootFrom: h2hRootFrom,
        realmFrom: realmFrom,
        asParam: asParam,
        apiSuffix: apiSuffix,
        gamesBasePath: gamesBasePath,
        gamesHash: gamesHash,
        gamesListUrl: gamesListUrl,
        h2hGrainFrom: h2hGrainFrom,
        opponentCountryFrom: opponentCountryFrom,
        matchupApiQuery: matchupApiQuery,
        gamesMatchupParams: gamesMatchupParams,
        initialMatchupFromPage: initialMatchupFromPage
    };
})();