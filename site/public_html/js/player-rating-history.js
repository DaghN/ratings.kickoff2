/**
 * Shared fetch/cache for api/player_rating_history.php (dedupes page load).
 */
(function (global) {
    'use strict';

    // Root-relative — profile lives under /player/ and /amiga/, not site root.
    var API_PATH = '/api/player_rating_history.php';
    var cache = {};

    function cacheKey(playerId, realm, asParam) {
        return String(playerId) + ':' + (realm || 'online') + ':' + (asParam || '');
    }

    function load(playerId, realm, options) {
        var opts = options || {};
        var realmVal = realm || 'online';
        var asParam = opts.as != null ? String(opts.as) : '';
        var key = cacheKey(playerId, realmVal, asParam);

        if (cache[key] && cache[key].data) {
            return Promise.resolve(cache[key].data);
        }
        if (cache[key] && cache[key].promise) {
            return cache[key].promise;
        }

        var url = API_PATH + '?id=' + encodeURIComponent(playerId)
            + '&realm=' + encodeURIComponent(realmVal);
        if (asParam !== '') {
            url += '&as=' + encodeURIComponent(asParam);
        }

        var promise = fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('bad_status');
                }
                return r.json();
            })
            .then(function (data) {
                cache[key] = { data: data, promise: promise };
                return data;
            })
            .catch(function (err) {
                delete cache[key];
                throw err;
            });

        cache[key] = { promise: promise };
        return promise;
    }

    global.K2PlayerRatingHistory = {
        load: load
    };
}(typeof window !== 'undefined' ? window : this));
