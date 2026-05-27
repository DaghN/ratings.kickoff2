/**
 * Status — Leagues block: paired Activity + Points tables, period tabs, step nav, pickers.
 * Single table slot; in-memory cache by period:key; optional prewarm of five next clicks.
 * Lock-step keys clamped to data-first-rated-day after derive.
 */
(function () {
    'use strict';

    var ACTIVITY_API = 'api/server_period_activity_leaderboard.php';
    var POINTS_API = 'api/status_period_points_league.php';
    var PERIODS = ['day', 'week', 'month', 'year'];

    var PANEL_ATTRS = [
        'data-league-meta-text',
        'data-league-period-label',
        'data-league-total-games',
        'data-league-end-label',
        'data-league-end-epoch',
        'data-league-period',
    ];

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function pluralRatedGames(n) {
        return n === 1 ? 'rated game' : 'rated games';
    }

    function endLabelParts(endLabel, periodTab) {
        if (periodTab === 'year') {
            var lastComma = endLabel.lastIndexOf(', ');
            if (lastComma === -1) {
                return { date: endLabel, time: '' };
            }
            return {
                date: endLabel.slice(0, lastComma),
                time: endLabel.slice(lastComma + 2),
            };
        }
        var comma = endLabel.indexOf(', ');
        if (comma === -1) {
            return { date: endLabel, time: '' };
        }
        return {
            date: endLabel.slice(0, comma),
            time: endLabel.slice(comma + 2),
        };
    }

    function formatRemaining(seconds) {
        seconds = Math.max(0, Math.floor(seconds));
        if (seconds <= 0) {
            return 'ended';
        }
        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        if (days > 0) {
            return days + 'd ' + hours + 'h';
        }
        if (hours > 0) {
            return hours + 'h ' + minutes + 'm';
        }
        return Math.max(1, minutes) + 'm';
    }

    function serverNowEpoch(root) {
        var initialServer = parseInt(root.getAttribute('data-server-now-epoch'), 10);
        var loadedAt = parseInt(root.getAttribute('data-browser-loaded-epoch'), 10);
        if (!initialServer || !loadedAt) {
            return Math.floor(Date.now() / 1000);
        }
        return initialServer + Math.floor(Date.now() / 1000) - loadedAt;
    }

    function parseJsonAttr(root, name, fallback) {
        try {
            return JSON.parse(root.getAttribute(name) || '');
        } catch (e) {
            return fallback;
        }
    }

    function metaHtmlFromPanel(root, panel) {
        if (!panel) {
            return '';
        }
        var fallback = panel.getAttribute('data-league-meta-text') || '';
        var label = panel.getAttribute('data-league-period-label') || '';
        var total = parseInt(panel.getAttribute('data-league-total-games'), 10);
        var endLabel = panel.getAttribute('data-league-end-label') || '';
        var endEpoch = parseInt(panel.getAttribute('data-league-end-epoch'), 10);
        var periodTab = panel.getAttribute('data-league-period') || '';
        if (!label || isNaN(total) || !endLabel || !endEpoch) {
            return escapeHtml(fallback);
        }
        var now = serverNowEpoch(root);
        var isLive = endEpoch > now;
        var text = '<span class="blue">' + escapeHtml(label) + '</span> · <span class="holo">'
            + total.toLocaleString('en-US') + '</span> ' + pluralRatedGames(total);
        if (isLive) {
            text += ' · ends ' + escapeHtml(endLabel) + ' UTC';
        } else {
            var endParts = endLabelParts(endLabel, periodTab);
            text += ' · ended <span class="blue">' + escapeHtml(endParts.date) + '</span>';
            if (endParts.time) {
                text += ', ' + escapeHtml(endParts.time);
            }
            text += ' UTC';
        }
        if (isLive) {
            var remaining = formatRemaining(endEpoch - now);
            if (remaining === 'ended') {
                text += ' · ' + escapeHtml(remaining);
            } else {
                text += ' · <span class="blue">' + escapeHtml(remaining) + '</span> left';
            }
        }
        return text;
    }

    function activePeriod(root) {
        var fromAttr = root.getAttribute('data-active-period');
        if (fromAttr) {
            return fromAttr;
        }
        var tab = root.querySelector('[data-competition-period].is-active');
        return tab ? tab.getAttribute('data-competition-period') : (root.getAttribute('data-default-period') || 'week');
    }

    function podiumMedals(root) {
        return parseJsonAttr(root, 'data-podium-medals', {});
    }

    var medalInjectSeq = 0;

    function medalHtml(root, rank) {
        if (rank > 3) {
            return '';
        }
        var medals = podiumMedals(root);
        var raw = medals[String(rank)] || '';
        if (!raw) {
            return '';
        }
        medalInjectSeq += 1;
        var suffix = '-inj' + medalInjectSeq;
        return raw
            .replace(/\bid="(k2-medal-[^"]+)"/g, function (_m, id) {
                return 'id="' + id + suffix + '"';
            })
            .replace(/url\(#(k2-medal-[^)]+)\)/g, function (_m, id) {
                return 'url(#' + id + suffix + ')';
            });
    }

    function showMedalsForPeriod(root, endEpoch) {
        return endEpoch > 0 && endEpoch <= serverNowEpoch(root);
    }

    function updatePeriodTabs(root, period) {
        root.setAttribute('data-active-period', period);
        var tabs = root.querySelectorAll('[data-competition-period]');
        for (var i = 0; i < tabs.length; i++) {
            var tab = tabs[i];
            var on = tab.getAttribute('data-competition-period') === period;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        }
    }

    function competitionSlots(root) {
        return {
            activity: root.querySelector('[data-competition-activity-body]'),
            points: root.querySelector('[data-competition-points-body]'),
        };
    }

    function periodCacheId(period, key) {
        return period + ':' + key;
    }

    function ensurePeriodCache(root) {
        if (!root._periodCache) {
            root._periodCache = Object.create(null);
        }
        if (!root._periodFetchInflight) {
            root._periodFetchInflight = Object.create(null);
        }
    }

    function getPeriodCache(root, period, key) {
        ensurePeriodCache(root);
        return root._periodCache[periodCacheId(period, key)] || null;
    }

    function setPeriodCache(root, period, key, snap) {
        ensurePeriodCache(root);
        root._periodCache[periodCacheId(period, key)] = snap;
    }

    function snapshotSlots(root) {
        var slots = competitionSlots(root);
        if (!slots.activity || !slots.points) {
            return null;
        }
        return {
            activity: slots.activity.innerHTML,
            points: slots.points.innerHTML,
            pointsAttrs: snapshotPanelAttrs(slots.points),
        };
    }

    function applyPeriodCacheToSlots(root, snap) {
        var slots = competitionSlots(root);
        if (!snap || !slots.activity || !slots.points) {
            return;
        }
        slots.activity.innerHTML = snap.activity;
        slots.points.innerHTML = snap.points;
        restorePanelAttrs(slots.points, snap.pointsAttrs);
        slots.points.setAttribute('data-competition-points-panel', '');
        updateMeta(root);
    }

    function isViewingPeriodKey(root, period, key) {
        return activePeriod(root) === period && !!(root._periodKeys && root._periodKeys[period] === key);
    }

    function prewarmEnabled(root) {
        return root.getAttribute('data-competition-prewarm') === '1';
    }

    function bumpNavSeq(root) {
        root._periodNavSeq = (root._periodNavSeq || 0) + 1;
        return root._periodNavSeq;
    }

    function navSeqIsCurrent(root, seq) {
        return root._periodNavSeq === seq;
    }

    function cancelPendingWarm(root) {
        if (root._warmTimer) {
            clearTimeout(root._warmTimer);
            root._warmTimer = null;
        }
    }

    function scheduleWarmNextChoices(root) {
        if (!prewarmEnabled(root)) {
            return;
        }
        cancelPendingWarm(root);
        root._warmTimer = setTimeout(function () {
            root._warmTimer = null;
            warmNextChoices(root);
        }, 300);
    }

    function abortForegroundFetch(root) {
        if (root._periodFetchAbort) {
            root._periodFetchAbort.abort();
            root._periodFetchAbort = null;
        }
    }

    function seedInitialPeriodCache(root) {
        var defaultPeriod = root.getAttribute('data-default-period') || 'week';
        var keys = currentKeys(root);
        var key = keys[defaultPeriod];
        var snap = snapshotSlots(root);
        if (key && snap) {
            setPeriodCache(root, defaultPeriod, key, snap);
        }
    }

    function setArchivePickersVisible(root, period) {
        var pickers = root.querySelectorAll('[data-archive-picker-period]');
        for (var i = 0; i < pickers.length; i++) {
            var picker = pickers[i];
            picker.hidden = picker.getAttribute('data-archive-picker-period') !== period;
        }
        if (period !== 'day') {
            closeDayFlatpickr(root);
        }
    }

    function archiveInput(root, period) {
        var wrap = root.querySelector('[data-archive-picker-period="' + period + '"]');
        return wrap ? wrap.querySelector('.k2-status-period-competitions__archive-input') : null;
    }

    function updateMeta(root) {
        var meta = root.querySelector('[data-competition-meta]');
        var slots = competitionSlots(root);
        if (!meta || !slots.points) {
            return;
        }
        meta.innerHTML = metaHtmlFromPanel(root, slots.points);
    }

    function setStatus(root, message, show) {
        var el = root.querySelector('[data-competition-status]');
        if (!el) {
            return;
        }
        el.hidden = !show;
        el.textContent = show ? message : '';
    }

    function snapshotPanelAttrs(panel) {
        var attrs = {};
        if (!panel) {
            return attrs;
        }
        for (var i = 0; i < PANEL_ATTRS.length; i++) {
            var name = PANEL_ATTRS[i];
            attrs[name] = panel.getAttribute(name) || '';
        }
        return attrs;
    }

    function restorePanelAttrs(panel, attrs) {
        if (!panel || !attrs) {
            return;
        }
        for (var i = 0; i < PANEL_ATTRS.length; i++) {
            var name = PANEL_ATTRS[i];
            if (attrs[name]) {
                panel.setAttribute(name, attrs[name]);
            } else {
                panel.removeAttribute(name);
            }
        }
    }

    function pad2(n) {
        return n < 10 ? '0' + n : String(n);
    }

    function utcDate(y, m, d) {
        return new Date(Date.UTC(y, m - 1, d));
    }

    function parseDayKey(key) {
        var parts = String(key).split('-');
        if (parts.length !== 3) {
            return null;
        }
        var y = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10);
        var d = parseInt(parts[2], 10);
        if (!y || !m || !d) {
            return null;
        }
        return utcDate(y, m, d);
    }

    function formatDayKey(date) {
        return date.getUTCFullYear() + '-' + pad2(date.getUTCMonth() + 1) + '-' + pad2(date.getUTCDate());
    }

    function mondayOfWeekContaining(date) {
        var d = new Date(date.getTime());
        var dow = d.getUTCDay();
        var delta = dow === 0 ? -6 : 1 - dow;
        d.setUTCDate(d.getUTCDate() + delta);
        return d;
    }

    function formatWeekKey(date) {
        return formatDayKey(mondayOfWeekContaining(date));
    }

    function formatMonthKey(date) {
        return date.getUTCFullYear() + '-' + pad2(date.getUTCMonth() + 1);
    }

    function formatYearKey(date) {
        return String(date.getUTCFullYear());
    }

    function anchorFromKey(period, key) {
        if (period === 'day' || period === 'week') {
            return parseDayKey(key);
        }
        if (period === 'month') {
            var mp = String(key).split('-');
            if (mp.length !== 2) {
                return null;
            }
            return utcDate(parseInt(mp[0], 10), parseInt(mp[1], 10), 1);
        }
        if (period === 'year') {
            var y = parseInt(key, 10);
            return y >= 1990 && y <= 2100 ? utcDate(y, 1, 1) : null;
        }
        return null;
    }

    function derivePeriodKeys(changedPeriod, key) {
        var anchor = anchorFromKey(changedPeriod, key);
        if (!anchor) {
            return null;
        }
        return {
            day: formatDayKey(anchor),
            week: formatWeekKey(anchor),
            month: formatMonthKey(anchor),
            year: formatYearKey(anchor),
        };
    }

    function isoWeekYearAndNumber(date) {
        var d = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()));
        var dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        var yearStart = Date.UTC(d.getUTCFullYear(), 0, 1);
        var weekNo = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
        return { week: weekNo, year: d.getUTCFullYear() };
    }

    function formatPeriodPickerLabel(period, key) {
        if (period === 'month') {
            var parts = String(key).split('-');
            if (parts.length === 2) {
                var y = parseInt(parts[0], 10);
                var m = parseInt(parts[1], 10) - 1;
                var names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                if (!isNaN(y) && m >= 0 && m < 12) {
                    return names[m] + ' ' + y;
                }
            }
        }
        if (period === 'week') {
            var wd = parseDayKey(key);
            if (wd) {
                var iso = isoWeekYearAndNumber(wd);
                return 'Week ' + iso.week + ', ' + iso.year;
            }
        }
        return key;
    }

    function clampKeysToFirstRated(root, keys) {
        var floorKey = root.getAttribute('data-first-rated-day');
        if (!floorKey || !keys) {
            return keys;
        }
        var floor = parseDayKey(floorKey);
        if (!floor) {
            return keys;
        }
        var floorDay = formatDayKey(floor);
        var floorWeek = formatWeekKey(floor);
        var floorMonth = formatMonthKey(floor);
        var floorYear = formatYearKey(floor);

        if (keys.year && compareKeys('year', keys.year, floorYear) < 0) {
            var realigned = derivePeriodKeys('year', floorYear);
            if (realigned) {
                keys = realigned;
            }
        }
        if (keys.day && compareKeys('day', keys.day, floorDay) < 0) {
            keys.day = floorDay;
        }
        if (keys.week && compareKeys('week', keys.week, floorWeek) < 0) {
            keys.week = floorWeek;
        }
        if (keys.month && compareKeys('month', keys.month, floorMonth) < 0) {
            keys.month = floorMonth;
        }
        return keys;
    }

    function resolvePeriodKeys(root, changedPeriod, key) {
        var keys = derivePeriodKeys(changedPeriod, key);
        if (!keys) {
            return null;
        }
        return clampKeysToFirstRated(root, keys);
    }

    function dayValueInput(root) {
        return root.querySelector('.k2-status-day-picker__value');
    }

    function syncDayFlatpickrFromValue(root) {
        var valueInput = dayValueInput(root);
        var fp = valueInput ? valueInput._k2Flatpickr : null;
        if (!fp || !valueInput.value) {
            return;
        }
        root._syncingPickers = true;
        try {
            fp.setDate(valueInput.value, false);
        } catch (e) {
            /* keep valueInput.value */
        }
        root._syncingPickers = false;
    }

    function setPickerValue(root, period, key) {
        var input = archiveInput(root, period);
        if (!input || !key) {
            return;
        }
        if (input.tagName === 'SELECT') {
            var has = false;
            for (var i = 0; i < input.options.length; i++) {
                if (input.options[i].value === key) {
                    has = true;
                    break;
                }
            }
            if (!has) {
                var opt = document.createElement('option');
                opt.value = key;
                opt.textContent = formatPeriodPickerLabel(period, key);
                input.insertBefore(opt, input.firstChild);
            }
            input.value = key;
            return;
        }
        input.value = key;
        if (period === 'day') {
            var anchor = root.querySelector('.k2-status-day-picker__fp-anchor');
            if (anchor) {
                anchor.value = key;
            }
        }
    }

    function closeDayFlatpickr(root) {
        var valueInput = dayValueInput(root);
        if (valueInput && valueInput._k2Flatpickr) {
            valueInput._k2Flatpickr.close();
        }
    }

    function flatpickrYearBounds(fp) {
        var minY = 1990;
        var maxY = 2100;
        if (fp.config.minDate instanceof Date) {
            minY = fp.config.minDate.getFullYear();
        }
        if (fp.config.maxDate instanceof Date) {
            maxY = fp.config.maxDate.getFullYear();
        }
        return { minY: minY, maxY: maxY };
    }

    function flatpickrMonthBoundsForYear(fp, year) {
        var minM = 0;
        var maxM = 11;
        if (fp.config.minDate instanceof Date && year === fp.config.minDate.getFullYear()) {
            minM = fp.config.minDate.getMonth();
        }
        if (fp.config.maxDate instanceof Date && year === fp.config.maxDate.getFullYear()) {
            maxM = fp.config.maxDate.getMonth();
        }
        return { minM: minM, maxM: maxM };
    }

    function flatpickrMonthLabels(fp) {
        if (fp.l10n && fp.l10n.months && fp.l10n.months.longhand) {
            return fp.l10n.months.longhand;
        }
        return [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December',
        ];
    }

    function rebuildK2FlatpickrMonthSelect(fp) {
        if (!fp || !fp._k2MonthSelect) {
            return;
        }
        var bounds = flatpickrMonthBoundsForYear(fp, fp.currentYear);
        var opts = fp._k2MonthSelect.options;
        for (var i = 0; i < opts.length; i++) {
            opts[i].disabled = i < bounds.minM || i > bounds.maxM;
        }
    }

    function syncK2FlatpickrMonthSelect(fp) {
        if (!fp || !fp._k2MonthSelect) {
            return;
        }
        rebuildK2FlatpickrMonthSelect(fp);
        fp._k2MonthSelect.value = String(fp.currentMonth);
    }

    function setupK2FlatpickrMonthSelect(fp) {
        if (!fp || !fp.calendarContainer || fp._k2MonthSelect) {
            return;
        }
        var native = fp.calendarContainer.querySelector('.flatpickr-monthDropdown-months');
        if (!native) {
            return;
        }
        var select = document.createElement('select');
        select.className = 'k2-flatpickr-month-select';
        select.setAttribute('aria-label', 'Month');
        native.classList.add('k2-flatpickr-month-native--hidden');
        native.setAttribute('aria-hidden', 'true');
        native.tabIndex = -1;
        native.parentNode.insertBefore(select, native);
        var labels = flatpickrMonthLabels(fp);
        for (var m = 0; m < 12; m++) {
            var opt = document.createElement('option');
            opt.value = String(m);
            opt.textContent = labels[m] || String(m + 1);
            select.appendChild(opt);
        }
        select.addEventListener('change', function () {
            var mo = parseInt(select.value, 10);
            if (!isNaN(mo)) {
                fp.changeMonth(mo, false);
            }
        });
        fp._k2MonthSelect = select;
        rebuildK2FlatpickrMonthSelect(fp);
        syncK2FlatpickrMonthSelect(fp);
    }

    function syncK2FlatpickrYearSelect(fp) {
        if (!fp || !fp._k2YearSelect) {
            return;
        }
        fp._k2YearSelect.value = String(fp.currentYear);
    }

    function setupK2FlatpickrYearSelect(fp) {
        if (!fp || !fp.calendarContainer || fp._k2YearSelect) {
            return;
        }
        var yearInput = fp.calendarContainer.querySelector('input.cur-year');
        if (!yearInput) {
            return;
        }
        var bounds = flatpickrYearBounds(fp);
        if (bounds.minY > bounds.maxY) {
            return;
        }
        var select = document.createElement('select');
        select.className = 'k2-flatpickr-year-select';
        select.setAttribute('aria-label', 'Year');
        for (var y = bounds.maxY; y >= bounds.minY; y--) {
            var opt = document.createElement('option');
            opt.value = String(y);
            opt.textContent = String(y);
            select.appendChild(opt);
        }
        select.value = String(fp.currentYear);
        select.addEventListener('change', function () {
            var yr = parseInt(select.value, 10);
            if (!isNaN(yr)) {
                fp.changeYear(yr);
            }
        });
        /* Hide Flatpickr's year stepper (input + up/down arrows); keep wrapper in DOM for internals. */
        var numWrap = yearInput.closest('.numInputWrapper');
        if (numWrap) {
            numWrap.classList.add('k2-flatpickr-year-stepper--hidden');
            numWrap.setAttribute('aria-hidden', 'true');
        }
        var monthRow = fp.calendarContainer.querySelector('.flatpickr-current-month');
        var insertAfter = fp._k2MonthSelect;
        if (!insertAfter && monthRow) {
            insertAfter = monthRow.querySelector('.flatpickr-monthDropdown-months');
        }
        if (insertAfter && insertAfter.parentNode) {
            insertAfter.parentNode.insertBefore(select, insertAfter.nextSibling);
        } else if (monthRow) {
            monthRow.appendChild(select);
        }
        fp._k2YearSelect = select;
    }

    function initDayFlatpickr(root, valueInput) {
        if (typeof flatpickr !== 'function' || valueInput._k2Flatpickr) {
            return valueInput._k2Flatpickr || null;
        }
        var control = valueInput.closest('.server-period-activity-leaderboard__date-control');
        var anchor = control ? control.querySelector('.k2-status-day-picker__fp-anchor') : null;
        var btn = control ? control.querySelector('.k2-status-period-competitions__calendar-btn') : null;
        if (!anchor) {
            return null;
        }
        var minDate = valueInput.getAttribute('data-min') || undefined;
        var maxDate = valueInput.getAttribute('data-max') || undefined;
        var fp;
        try {
            fp = flatpickr(anchor, {
                allowInput: false,
                clickOpens: false,
                dateFormat: 'Y-m-d',
                defaultDate: valueInput.value || undefined,
                minDate: minDate,
                maxDate: maxDate,
                monthSelectorType: 'dropdown',
                disableMobile: true,
                positionElement: btn || anchor,
                appendTo: document.body,
                onReady: function (selectedDates, dateStr, instance) {
                    setupK2FlatpickrMonthSelect(instance);
                    setupK2FlatpickrYearSelect(instance);
                    syncK2FlatpickrMonthSelect(instance);
                    syncK2FlatpickrYearSelect(instance);
                },
                onYearChange: function (selectedDates, dateStr, instance) {
                    syncK2FlatpickrMonthSelect(instance);
                    syncK2FlatpickrYearSelect(instance);
                },
                onMonthChange: function (selectedDates, dateStr, instance) {
                    syncK2FlatpickrMonthSelect(instance);
                },
                onChange: function (selectedDates, dateStr) {
                    if (root._k2CompetitionsBootstrapping || root._syncingPickers || !dateStr) {
                        return;
                    }
                    valueInput.value = dateStr;
                    anchor.value = dateStr;
                    var keys = resolvePeriodKeys(root, 'day', dateStr);
                    if (!keys) {
                        return;
                    }
                    applyPeriodKeys(root, 'day', keys);
                },
                onOpen: function (selectedDates, dateStr, instance) {
                    syncDayFlatpickrFromValue(root);
                    syncK2FlatpickrMonthSelect(instance);
                    syncK2FlatpickrYearSelect(instance);
                    if (btn) {
                        btn.classList.add('is-open');
                    }
                },
                onClose: function () {
                    if (btn) {
                        btn.classList.remove('is-open');
                        btn.blur();
                    }
                },
            });
        } catch (e) {
            return null;
        }
        valueInput._k2Flatpickr = fp;
        if (fp) {
            setupK2FlatpickrMonthSelect(fp);
            setupK2FlatpickrYearSelect(fp);
            syncK2FlatpickrMonthSelect(fp);
            syncK2FlatpickrYearSelect(fp);
        }
        if (btn) {
            var calendarOpenOnPointerDown = false;
            btn.addEventListener('mousedown', function (e) {
                if (e.button !== 0) {
                    return;
                }
                e.preventDefault();
                e.stopPropagation();
                calendarOpenOnPointerDown = !!(valueInput._k2Flatpickr && valueInput._k2Flatpickr.isOpen);
            });
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (valueInput.disabled || !valueInput._k2Flatpickr) {
                    return;
                }
                var fp = valueInput._k2Flatpickr;
                if (calendarOpenOnPointerDown || fp.isOpen) {
                    fp.close();
                } else {
                    fp.open();
                }
            });
        }
        return fp;
    }

    function applyKeysToPickers(root, keys) {
        if (!keys) {
            return;
        }
        for (var i = 0; i < PERIODS.length; i++) {
            setPickerValue(root, PERIODS[i], keys[PERIODS[i]]);
        }
    }

    function compareKeys(period, a, b) {
        if (a === b) {
            return 0;
        }
        if (period === 'day' || period === 'week') {
            return a < b ? -1 : 1;
        }
        if (period === 'month') {
            return a < b ? -1 : 1;
        }
        var ya = parseInt(a, 10);
        var yb = parseInt(b, 10);
        if (ya === yb) {
            return 0;
        }
        return ya < yb ? -1 : 1;
    }

    function navBounds(root) {
        return parseJsonAttr(root, 'data-nav-bounds', {});
    }

    function currentKeys(root) {
        return parseJsonAttr(root, 'data-current-keys', {});
    }

    function isWithinBounds(period, key, bounds) {
        var b = bounds[period];
        if (!b || !key) {
            return true;
        }
        if (b.min && compareKeys(period, key, b.min) < 0) {
            return false;
        }
        if (b.max && compareKeys(period, key, b.max) > 0) {
            return false;
        }
        return true;
    }

    function stepKey(period, key, direction) {
        var anchor = anchorFromKey(period, key);
        if (!anchor) {
            return null;
        }
        if (period === 'day') {
            anchor.setUTCDate(anchor.getUTCDate() + direction);
            return formatDayKey(anchor);
        }
        if (period === 'week') {
            anchor.setUTCDate(anchor.getUTCDate() + (7 * direction));
            return formatWeekKey(anchor);
        }
        if (period === 'month') {
            anchor.setUTCMonth(anchor.getUTCMonth() + direction);
            return formatMonthKey(anchor);
        }
        anchor.setUTCFullYear(anchor.getUTCFullYear() + direction);
        return formatYearKey(anchor);
    }

    function canStep(root, period, key, direction) {
        if (!key) {
            return false;
        }
        var bounds = navBounds(root);
        var b = bounds[period];
        if (b && b.min && direction < 0 && compareKeys(period, key, b.min) <= 0) {
            return false;
        }
        if (b && b.max && direction > 0 && compareKeys(period, key, b.max) >= 0) {
            return false;
        }
        var next = stepKey(period, key, direction);
        if (!next) {
            return false;
        }
        return isWithinBounds(period, next, bounds);
    }

    function updateStepButtons(root, period) {
        var key = (root._periodKeys && root._periodKeys[period]) || '';
        var prevBtn = root.querySelector('[data-competition-step="prev"]');
        var nextBtn = root.querySelector('[data-competition-step="next"]');
        if (prevBtn) {
            prevBtn.disabled = !canStep(root, period, key, -1);
        }
        if (nextBtn) {
            nextBtn.disabled = !canStep(root, period, key, 1);
        }
    }

    function buildActivityTbody(entries, showMedals, root) {
        if (!entries || !entries.length) {
            return '';
        }
        var html = '<tbody class="black">';
        for (var i = 0; i < entries.length; i++) {
            var e = entries[i];
            var rank = e.rank;
            html += '<tr>';
            html += '<td class="k2-status-table__num">' + rank + '</td>';
            html += '<td class="k2-status-table__player"><a class="k2-link-star" href="individual1.php?id=' + e.player_id + '">'
                + escapeHtml(e.player_name) + '</a></td>';
            html += '<td class="k2-status-table__num">' + e.games + '</td>';
            if (showMedals) {
                html += '<td class="k2-status-table__medal">' + medalHtml(root, rank) + '</td>';
            }
            html += '</tr>';
        }
        html += '</tbody>';
        return html;
    }

    function activityTableShell(showMedals) {
        var medalHead = showMedals
            ? '<th class="k2-status-table__medal" scope="col"><span class="visually-hidden">Award</span></th>'
            : '';
        var podiumClass = showMedals ? ' k2-status-table--podium' : '';
        return '<div class="k2-table-wrap k2-table-wrap--compact"><table class="k2-table k2-status-table k2-status-table--dense k2-status-period-competitions__activity-table' + podiumClass + '">'
            + '<thead><tr><th class="k2-status-table__num">#</th><th class="k2-status-table__player">Player</th>'
            + '<th class="k2-status-table__num">Games</th>' + medalHead + '</tr></thead></table></div>';
    }

    function buildPointsTbody(rows, showMedals, root) {
        if (!rows || !rows.length) {
            return '';
        }
        var html = '<div class="k2-table-wrap k2-table-wrap--compact"><table class="k2-table k2-status-table k2-status-table--dense'
            + (showMedals ? ' k2-status-table--podium' : '') + '"><thead><tr>'
            + '<th class="k2-status-table__num">#</th><th class="k2-status-table__player">Player</th>'
            + '<th class="k2-status-table__num">Pld</th><th class="k2-status-table__num">W</th><th class="k2-status-table__num">D</th>'
            + '<th class="k2-status-table__num">L</th><th class="k2-status-table__num">GF</th><th class="k2-status-table__num">GA</th>'
            + '<th class="k2-status-table__num">GD</th><th class="k2-status-table__num">Pts</th>';
        if (showMedals) {
            html += '<th class="k2-status-table__medal" scope="col"><span class="visually-hidden">Award</span></th>';
        }
        html += '</tr></thead><tbody class="black">';
        for (var r = 0; r < rows.length; r++) {
            var row = rows[r];
            var rank = r + 1;
            var gd = row.gd;
            html += '<tr>';
            html += '<td class="k2-status-table__num">' + rank + '</td>';
            html += '<td class="k2-status-table__player"><a class="k2-link-star" href="individual1.php?id=' + row.id + '">'
                + escapeHtml(row.name) + '</a></td>';
            html += '<td class="k2-status-table__num">' + row.played + '</td>';
            html += '<td class="k2-status-table__num">' + row.wins + '</td>';
            html += '<td class="k2-status-table__num">' + row.draws + '</td>';
            html += '<td class="k2-status-table__num">' + row.losses + '</td>';
            html += '<td class="k2-status-table__num">' + row.gf + '</td>';
            html += '<td class="k2-status-table__num">' + row.ga + '</td>';
            html += '<td class="k2-status-table__num">' + (gd > 0 ? '+' + gd : String(gd)) + '</td>';
            html += '<td class="k2-status-table__num"><span class="blue">' + row.pts + '</span></td>';
            if (showMedals) {
                html += '<td class="k2-status-table__medal">' + medalHtml(root, rank) + '</td>';
            }
            html += '</tr>';
        }
        html += '</tbody></table></div>';
        return html;
    }

    function applyPointsPanelAttrs(panel, data) {
        if (!panel) {
            return;
        }
        panel.setAttribute('data-league-period-label', data.label || '');
        panel.setAttribute('data-league-total-games', String(data.total_games || 0));
        panel.setAttribute('data-league-end-label', data.end_label || '');
        panel.setAttribute('data-league-end-epoch', String(data.end_epoch || 0));
        panel.setAttribute('data-league-period', data.period || '');
        panel.setAttribute('data-league-meta-text', '');
    }

    function injectActivity(slot, entries, root, showMedals) {
        if (!slot) {
            return;
        }
        if (!entries || !entries.length) {
            slot.innerHTML = '<p class="k2-status-panel__empty">No rated games in this period.</p>';
            return;
        }
        slot.innerHTML = activityTableShell(showMedals);
        var table = slot.querySelector('table');
        if (!table) {
            return;
        }
        table.insertAdjacentHTML('beforeend', buildActivityTbody(entries, showMedals, root));
    }

    function injectPoints(slot, data, showMedals, root) {
        if (!slot) {
            return;
        }
        applyPointsPanelAttrs(slot, data);
        if (!data.rows || !data.rows.length) {
            slot.innerHTML = '<p class="k2-status-panel__empty">No rated games in this period.</p>';
            slot.setAttribute('data-competition-points-panel', '');
            return;
        }
        slot.innerHTML = buildPointsTbody(data.rows, showMedals, root);
        slot.setAttribute('data-competition-points-panel', '');
    }

    function renderPeriodSnapshot(root, entries, pointsBody, showMedals) {
        var actSlot = document.createElement('div');
        var ptsSlot = document.createElement('div');
        injectActivity(actSlot, entries, root, showMedals);
        injectPoints(ptsSlot, pointsBody, showMedals, root);
        return {
            activity: actSlot.innerHTML,
            points: ptsSlot.innerHTML,
            pointsAttrs: snapshotPanelAttrs(ptsSlot),
        };
    }

    function enqueueWarmFetch(root, period, key) {
        if (!key || getPeriodCache(root, period, key)) {
            return;
        }
        var id = periodCacheId(period, key);
        ensurePeriodCache(root);
        if (root._periodFetchInflight[id]) {
            return;
        }
        if (!root._warmQueue) {
            root._warmQueue = [];
        }
        for (var q = 0; q < root._warmQueue.length; q++) {
            if (root._warmQueue[q].period === period && root._warmQueue[q].key === key) {
                return;
            }
        }
        root._warmQueue.push({ period: period, key: key });
        drainWarmQueue(root);
    }

    function drainWarmQueue(root) {
        if (!root._warmActive) {
            root._warmActive = 0;
        }
        var maxParallel = 2;
        while (root._warmActive < maxParallel && root._warmQueue && root._warmQueue.length) {
            var item = root._warmQueue.shift();
            root._warmActive++;
            fetchPeriod(root, item.period, item.key, { background: true }).then(function () {
                root._warmActive--;
                drainWarmQueue(root);
            }).catch(function () {
                root._warmActive--;
                drainWarmQueue(root);
            });
        }
    }

    function warmNextChoices(root) {
        if (!prewarmEnabled(root) || !root._periodKeys) {
            return;
        }
        var period = activePeriod(root);
        var keys = root._periodKeys;
        var key = keys[period];
        var bounds = navBounds(root);
        var directions = [-1, 1];

        for (var d = 0; d < directions.length; d++) {
            var neighbor = stepKey(period, key, directions[d]);
            if (neighbor && isWithinBounds(period, neighbor, bounds)) {
                enqueueWarmFetch(root, period, neighbor);
            }
        }
        for (var i = 0; i < PERIODS.length; i++) {
            var p = PERIODS[i];
            if (p === period) {
                continue;
            }
            var k = keys[p];
            if (k) {
                enqueueWarmFetch(root, p, k);
            }
        }
    }

    function fetchPeriod(root, period, key, options) {
        options = options || {};
        var background = !!options.background;
        var navSeq = options.navSeq;
        if (!key) {
            return Promise.resolve();
        }
        if (getPeriodCache(root, period, key)) {
            if (!background && isViewingPeriodKey(root, period, key)) {
                setStatus(root, '', false);
                applyPeriodCacheToSlots(root, getPeriodCache(root, period, key));
                scheduleWarmNextChoices(root);
            }
            return Promise.resolve();
        }

        var id = periodCacheId(period, key);
        ensurePeriodCache(root);
        if (root._periodFetchInflight[id]) {
            return root._periodFetchInflight[id];
        }

        if (!background) {
            setStatus(root, '', false);
            abortForegroundFetch(root);
        }

        var fetchOpts = { credentials: 'same-origin' };
        if (!background && typeof AbortController !== 'undefined') {
            root._periodFetchAbort = new AbortController();
            fetchOpts.signal = root._periodFetchAbort.signal;
        }

        var limit = root.getAttribute('data-activity-limit') || '50';
        var activityUrl = ACTIVITY_API + '?period=' + encodeURIComponent(period)
            + '&key=' + encodeURIComponent(key) + '&limit=' + encodeURIComponent(limit);
        var pointsUrl = POINTS_API + '?period=' + encodeURIComponent(period)
            + '&key=' + encodeURIComponent(key);

        var promise = Promise.all([
            fetch(activityUrl, fetchOpts).then(function (res) {
                return res.json().then(function (body) {
                    return { ok: res.ok, body: body };
                });
            }),
            fetch(pointsUrl, fetchOpts).then(function (res) {
                return res.json().then(function (body) {
                    return { ok: res.ok, body: body };
                });
            }),
        ]).then(function (results) {
            delete root._periodFetchInflight[id];
            if (!root._periodKeys || root._periodKeys[period] !== key) {
                return;
            }
            if (!background && navSeq !== undefined && !navSeqIsCurrent(root, navSeq)) {
                return;
            }
            if (!results[0].ok || results[0].body.error) {
                throw new Error('activity');
            }
            if (!results[1].ok || results[1].body.error) {
                throw new Error('points');
            }
            var showMedals = showMedalsForPeriod(root, results[1].body.end_epoch || 0);
            var snap = renderPeriodSnapshot(root, results[0].body.entries, results[1].body, showMedals);
            setPeriodCache(root, period, key, snap);
            if (isViewingPeriodKey(root, period, key)) {
                applyPeriodCacheToSlots(root, snap);
                if (!background) {
                    scheduleWarmNextChoices(root);
                }
            }
        }).catch(function (err) {
            delete root._periodFetchInflight[id];
            if (err && err.name === 'AbortError') {
                return;
            }
            if (!background && navSeq !== undefined && !navSeqIsCurrent(root, navSeq)) {
                return;
            }
            if (!background && isViewingPeriodKey(root, period, key)) {
                setStatus(root, 'Could not load the selected period. Try again.', true);
            }
        });

        root._periodFetchInflight[id] = promise;
        return promise;
    }

    function applyPeriodKeys(root, period, keys) {
        if (!keys || !keys[period]) {
            return;
        }
        root._syncingPickers = true;
        root._periodKeys = keys;
        applyKeysToPickers(root, keys);
        root._syncingPickers = false;

        updatePeriodTabs(root, period);
        setArchivePickersVisible(root, period);
        updateStepButtons(root, period);

        cancelPendingWarm(root);
        root._warmQueue = [];
        var navSeq = bumpNavSeq(root);

        var key = keys[period];
        var cached = getPeriodCache(root, period, key);
        if (cached) {
            setStatus(root, '', false);
            applyPeriodCacheToSlots(root, cached);
            scheduleWarmNextChoices(root);
            return;
        }
        fetchPeriod(root, period, key, { navSeq: navSeq });
    }

    function navigatePeriod(root, period) {
        var keys = root._periodKeys;
        applyPeriodKeys(root, period, keys);
    }

    function stepPeriod(root, period, direction) {
        var key = root._periodKeys[period] || '';
        var next = stepKey(period, key, direction);
        if (!next || !isWithinBounds(period, next, navBounds(root))) {
            return;
        }
        var keys = resolvePeriodKeys(root, period, next);
        if (!keys) {
            return;
        }
        applyPeriodKeys(root, period, keys);
    }

    function initRoot(root) {
        if (!root.getAttribute('data-browser-loaded-epoch')) {
            root.setAttribute('data-browser-loaded-epoch', String(Math.floor(Date.now() / 1000)));
        }
        seedInitialPeriodCache(root);
        root._periodKeys = clampKeysToFirstRated(root, currentKeys(root));
        root._syncingPickers = true;
        applyKeysToPickers(root, root._periodKeys);
        root._syncingPickers = false;

        var periodTabs = root.querySelectorAll('[data-competition-period]');
        for (var p = 0; p < periodTabs.length; p++) {
            periodTabs[p].addEventListener('click', function () {
                navigatePeriod(root, this.getAttribute('data-competition-period'));
            });
        }

        var stepButtons = root.querySelectorAll('[data-competition-step]');
        for (var s = 0; s < stepButtons.length; s++) {
            stepButtons[s].addEventListener('click', function () {
                var dir = this.getAttribute('data-competition-step') === 'next' ? 1 : -1;
                stepPeriod(root, activePeriod(root), dir);
            });
        }

        var archiveInputs = root.querySelectorAll('.k2-status-period-competitions__archive-input');
        for (var a = 0; a < archiveInputs.length; a++) {
            archiveInputs[a].addEventListener('change', function () {
                if (root._k2CompetitionsBootstrapping || root._syncingPickers) {
                    return;
                }
                var changedPeriod = this.getAttribute('data-archive-period');
                var keys = resolvePeriodKeys(root, changedPeriod, this.value);
                if (!keys) {
                    return;
                }
                applyPeriodKeys(root, changedPeriod, keys);
                if (this.tagName === 'SELECT') {
                    this.blur();
                }
            });
        }

        root._k2CompetitionsBootstrapping = true;
        var defaultPeriod = root.getAttribute('data-default-period') || 'week';
        navigatePeriod(root, defaultPeriod);

        var dayValueInputs = root.querySelectorAll('.k2-status-day-picker__value');
        for (var d = 0; d < dayValueInputs.length; d++) {
            initDayFlatpickr(root, dayValueInputs[d]);
        }
        root._k2CompetitionsBootstrapping = false;
    }

    function refreshMeta() {
        var roots = document.querySelectorAll('[data-k2-status-period-competitions]');
        for (var i = 0; i < roots.length; i++) {
            updateMeta(roots[i]);
        }
    }

    function init() {
        var roots = document.querySelectorAll('[data-k2-status-period-competitions]');
        for (var i = 0; i < roots.length; i++) {
            initRoot(roots[i]);
        }
        if (roots.length) {
            window.setInterval(refreshMeta, 30000);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
