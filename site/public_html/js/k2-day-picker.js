/**
 * Standalone day picker — flatpickr + K2 archive listboxes (Status league panel parity).
 * Auto-inits [data-k2-day-picker] on each page visit (Turbo-aware via k2OnPageReady).
 */
(function () {
    'use strict';

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
        return new Date(Date.UTC(y, m - 1, d));
    }

    function formatDayPickerLabel(key) {
        var dayDate = parseDayKey(key);
        if (!dayDate) {
            return key;
        }
        try {
            return dayDate.toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                timeZone: 'UTC',
            });
        } catch (e) {
            return key;
        }
    }

    function syncDayPickerLabel(wrap, key) {
        var label = wrap ? wrap.querySelector('[data-day-picker-label]') : null;
        if (!label || !key) {
            return;
        }
        label.textContent = formatDayPickerLabel(key);
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

    function k2FlatpickrMonthChoices(fp) {
        var bounds = flatpickrMonthBoundsForYear(fp, fp.currentYear);
        var labels = flatpickrMonthLabels(fp);
        var choices = [];
        for (var m = 0; m < 12; m++) {
            choices.push({
                value: String(m),
                label: labels[m] || String(m + 1),
                disabled: m < bounds.minM || m > bounds.maxM,
            });
        }
        return choices;
    }

    function rebuildK2FlatpickrMonthSelect(fp) {
        if (!fp || !fp._k2MonthListbox || typeof window.K2ArchiveListbox === 'undefined') {
            return;
        }
        window.K2ArchiveListbox.rebuild(
            fp._k2MonthListbox,
            k2FlatpickrMonthChoices(fp),
            String(fp.currentMonth)
        );
    }

    function syncK2FlatpickrMonthSelect(fp) {
        if (!fp || !fp._k2MonthListbox || typeof window.K2ArchiveListbox === 'undefined') {
            return;
        }
        rebuildK2FlatpickrMonthSelect(fp);
        window.K2ArchiveListbox.setValue(fp._k2MonthListbox, String(fp.currentMonth), null, true);
        window.K2ArchiveListbox.syncTriggerWidth(fp._k2MonthListbox);
    }

    function teardownK2FlatpickrListbox(ref) {
        if (!ref || !ref.box) {
            return;
        }
        if (ref.box.parentNode) {
            ref.box.parentNode.removeChild(ref.box);
        }
        ref.box = null;
    }

    function setupK2FlatpickrMonthSelect(fp) {
        if (!fp || !fp.calendarContainer) {
            return;
        }
        if (fp._k2MonthListbox && fp._k2MonthListbox.isConnected) {
            return;
        }
        if (typeof window.K2ArchiveListbox === 'undefined') {
            return;
        }
        fp._k2MonthListbox = null;
        var native = fp.calendarContainer.querySelector('.flatpickr-monthDropdown-months');
        if (!native || !native.parentNode) {
            return;
        }
        native.classList.add('k2-flatpickr-month-native--hidden');
        native.setAttribute('aria-hidden', 'true');
        native.tabIndex = -1;
        fp._k2MonthListbox = window.K2ArchiveListbox.createInline({
            compact: true,
            ariaLabel: 'Month',
            choices: k2FlatpickrMonthChoices(fp),
            value: String(fp.currentMonth),
            parent: native.parentNode,
            insertBefore: native,
            onSelect: function (value) {
                var mo = parseInt(value, 10);
                if (!isNaN(mo)) {
                    fp.changeMonth(mo, false);
                }
            },
        });
    }

    function k2FlatpickrYearChoices(fp) {
        var bounds = flatpickrYearBounds(fp);
        var choices = [];
        if (bounds.minY > bounds.maxY) {
            return choices;
        }
        for (var y = bounds.maxY; y >= bounds.minY; y--) {
            choices.push({ value: String(y), label: String(y) });
        }
        return choices;
    }

    function syncK2FlatpickrYearSelect(fp) {
        if (!fp || !fp._k2YearListbox || typeof window.K2ArchiveListbox === 'undefined') {
            return;
        }
        window.K2ArchiveListbox.setValue(fp._k2YearListbox, String(fp.currentYear), null, true);
    }

    function setupK2FlatpickrYearSelect(fp) {
        if (!fp || !fp.calendarContainer) {
            return;
        }
        if (fp._k2YearListbox && fp._k2YearListbox.isConnected) {
            return;
        }
        if (typeof window.K2ArchiveListbox === 'undefined') {
            return;
        }
        fp._k2YearListbox = null;
        var yearInput = fp.calendarContainer.querySelector('input.cur-year');
        if (!yearInput) {
            return;
        }
        var bounds = flatpickrYearBounds(fp);
        if (bounds.minY > bounds.maxY) {
            return;
        }
        var numWrap = yearInput.closest('.numInputWrapper');
        if (numWrap) {
            numWrap.classList.add('k2-flatpickr-year-stepper--hidden');
            numWrap.setAttribute('aria-hidden', 'true');
        }
        var monthRow = fp.calendarContainer.querySelector('.flatpickr-current-month');
        fp._k2YearListbox = window.K2ArchiveListbox.createInline({
            compact: true,
            ariaLabel: 'Year',
            choices: k2FlatpickrYearChoices(fp),
            value: String(fp.currentYear),
            onSelect: function (value) {
                var yr = parseInt(value, 10);
                if (!isNaN(yr)) {
                    fp.changeYear(yr);
                }
            },
        });
        var after = fp._k2MonthListbox;
        if (!after && monthRow) {
            after = monthRow.querySelector('.flatpickr-monthDropdown-months');
        }
        if (after && after.parentNode) {
            if (after.nextSibling) {
                after.parentNode.insertBefore(fp._k2YearListbox, after.nextSibling);
            } else {
                after.parentNode.appendChild(fp._k2YearListbox);
            }
        } else if (monthRow) {
            monthRow.appendChild(fp._k2YearListbox);
        }
    }

    function wireFlatpickrListboxShields(fp) {
        if (!fp || typeof window.K2ArchiveListbox === 'undefined') {
            return;
        }
        if (fp._k2MonthListbox) {
            window.K2ArchiveListbox.shieldFlatpickrListbox(fp._k2MonthListbox);
            window.K2ArchiveListbox.syncTriggerWidth(fp._k2MonthListbox);
        }
        if (fp._k2YearListbox) {
            window.K2ArchiveListbox.shieldFlatpickrListbox(fp._k2YearListbox);
            window.K2ArchiveListbox.syncTriggerWidth(fp._k2YearListbox);
        }
    }

    function ensureK2FlatpickrListboxes(fp) {
        if (!fp || !fp.calendarContainer || typeof window.K2ArchiveListbox === 'undefined') {
            return;
        }
        if (fp._k2MonthListbox && !fp._k2MonthListbox.isConnected) {
            teardownK2FlatpickrListbox({ box: fp._k2MonthListbox });
            fp._k2MonthListbox = null;
        }
        if (fp._k2YearListbox && !fp._k2YearListbox.isConnected) {
            teardownK2FlatpickrListbox({ box: fp._k2YearListbox });
            fp._k2YearListbox = null;
        }
        setupK2FlatpickrMonthSelect(fp);
        setupK2FlatpickrYearSelect(fp);
        syncK2FlatpickrMonthSelect(fp);
        syncK2FlatpickrYearSelect(fp);
        wireFlatpickrListboxShields(fp);
    }

    function initDayPicker(wrap) {
        if (!wrap || typeof flatpickr !== 'function') {
            return null;
        }
        var valueInput = wrap.querySelector('.k2-day-picker__value, .k2-status-day-picker__value');
        if (!valueInput || valueInput._k2Flatpickr) {
            return valueInput ? valueInput._k2Flatpickr : null;
        }
        var control = wrap.querySelector('.server-period-activity-leaderboard__date-control');
        var anchor = control ? control.querySelector('.k2-status-day-picker__fp-anchor') : null;
        var btn = control ? control.querySelector('.k2-status-day-picker__trigger') : null;
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
                onReady: function () {
                    ensureK2FlatpickrListboxes(fp);
                },
                onYearChange: function () {
                    syncK2FlatpickrMonthSelect(fp);
                    syncK2FlatpickrYearSelect(fp);
                },
                onMonthChange: function () {
                    syncK2FlatpickrMonthSelect(fp);
                },
                onChange: function (selectedDates, dateStr) {
                    if (!dateStr) {
                        return;
                    }
                    valueInput.value = dateStr;
                    anchor.value = dateStr;
                    syncDayPickerLabel(wrap, dateStr);
                },
                onOpen: function () {
                    ensureK2FlatpickrListboxes(fp);
                    if (fp.calendarContainer && typeof window.K2ArchiveListbox !== 'undefined') {
                        window.K2ArchiveListbox.closeFlatpickrPanels(fp.calendarContainer);
                    }
                    if (valueInput.value) {
                        try {
                            fp.setDate(valueInput.value, false);
                        } catch (e) {
                            /* keep value */
                        }
                    }
                    if (btn) {
                        btn.classList.add('is-open');
                        btn.setAttribute('aria-expanded', 'true');
                    }
                },
                onClose: function () {
                    if (btn) {
                        btn.classList.remove('is-open');
                        btn.setAttribute('aria-expanded', 'false');
                        btn.blur();
                    }
                    if (fp.calendarContainer && typeof window.K2ArchiveListbox !== 'undefined') {
                        window.K2ArchiveListbox.closeFlatpickrPanels(fp.calendarContainer);
                    }
                },
            });
        } catch (e) {
            return null;
        }
        valueInput._k2Flatpickr = fp;
        ensureK2FlatpickrListboxes(fp);
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
                if (!valueInput._k2Flatpickr) {
                    return;
                }
                var instance = valueInput._k2Flatpickr;
                if (calendarOpenOnPointerDown || instance.isOpen) {
                    instance.close();
                } else {
                    instance.open();
                }
            });
        }
        return fp;
    }

    function initAll(root) {
        var scope = root || document;
        var wraps = scope.querySelectorAll('[data-k2-day-picker]');
        for (var i = 0; i < wraps.length; i++) {
            initDayPicker(wraps[i]);
        }
    }

    window.K2DayPicker = {
        init: initAll,
        initOne: initDayPicker,
    };

    (window.k2OnPageReady || function (fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    })(function () {
        initAll(document);
    });
}());
