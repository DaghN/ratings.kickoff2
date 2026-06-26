/**
 * KOOL listbox — themed dropdown (archive week/month/year + inline e.g. Flatpickr).
 * Archive mode: hidden input + change event. Inline mode: onSelect callback.
 */
(function (global) {
    'use strict';

    var OPEN = null;
    var measureProbe = null;

    /** Horizontal padding + chevron gutter (matches trigger padding-right). */
    var TRIGGER_WIDTH_EXTRA_PX = 34;

    function qs(el, sel) {
        return el ? el.querySelector(sel) : null;
    }

    function panel(box) {
        return qs(box, '.k2-archive-listbox__panel');
    }

    function trigger(box) {
        return qs(box, '.k2-archive-listbox__trigger');
    }

    function valueInput(box) {
        return qs(box, '.k2-archive-listbox__value');
    }

    function labelEl(box) {
        return qs(box, '.k2-archive-listbox__label');
    }

    function triggerMetaEl(box) {
        return qs(box, '.k2-archive-listbox__trigger-meta');
    }

    function optionMetaText(opt) {
        if (!opt) {
            return '';
        }
        var meta = opt.getAttribute('data-option-meta');
        if (meta) {
            return meta;
        }
        var metaNode = opt.querySelector('.k2-archive-listbox__option-meta');
        return metaNode && metaNode.textContent ? metaNode.textContent : '';
    }

    function options(box) {
        var list = panel(box);
        return list ? list.querySelectorAll('[role="option"]') : [];
    }

    function isInline(box) {
        return box && box.getAttribute('data-k2-listbox-inline') === '1';
    }

    function getValue(box) {
        var input = valueInput(box);
        if (input) {
            return input.value;
        }
        return box._k2ListboxValue || '';
    }

    function optionLabel(period, value) {
        if (global.K2ArchiveListbox && typeof global.K2ArchiveListbox.formatLabel === 'function') {
            return global.K2ArchiveListbox.formatLabel(period, value);
        }
        return value;
    }

    function setTriggerExpanded(box, open) {
        var btn = trigger(box);
        if (!btn) {
            return;
        }
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.classList.toggle('is-open', open);
    }

    function measureProbeEl(btn) {
        if (!measureProbe) {
            measureProbe = document.createElement('span');
            measureProbe.className = 'k2-archive-listbox__measure-probe';
            measureProbe.setAttribute('aria-hidden', 'true');
            document.body.appendChild(measureProbe);
        }
        if (btn) {
            var cs = getComputedStyle(btn);
            measureProbe.style.font = cs.font;
            measureProbe.style.fontSize = cs.fontSize;
            measureProbe.style.fontWeight = cs.fontWeight;
            measureProbe.style.fontFamily = cs.fontFamily;
            measureProbe.style.letterSpacing = cs.letterSpacing;
        }
        return measureProbe;
    }

    function measureTextWidth(btn, text) {
        if (!btn || text == null) {
            return 0;
        }
        var probe = measureProbeEl(btn);
        probe.textContent = String(text);
        return probe.offsetWidth;
    }

    /**
     * Lock trigger (and box) width to the longest label in the panel so the toolbar does not jump.
     * @param {HTMLElement} btn
     * @param {string[]} texts
     */
    function syncTriggerWidthForButton(btn, texts) {
        if (!btn || !texts || !texts.length) {
            return;
        }
        var max = 0;
        for (var i = 0; i < texts.length; i++) {
            max = Math.max(max, measureTextWidth(btn, texts[i]));
        }
        if (max <= 0) {
            return;
        }
        var px = Math.ceil(max + TRIGGER_WIDTH_EXTRA_PX);
        var comp = btn.closest('[data-k2-status-period-competitions]');
        if (comp && comp._k2UnifiedPickerWidthPx > 0) {
            px = comp._k2UnifiedPickerWidthPx;
        }
        btn.style.minWidth = px + 'px';
        btn.style.width = px + 'px';
        btn.style.maxWidth = px + 'px';
        var box = btn.closest('[data-k2-archive-listbox], .k2-archive-listbox');
        if (box) {
            box.style.width = px + 'px';
            box.style.maxWidth = px + 'px';
        }
    }

    /**
     * Apply an explicit trigger (and wrapper) width in px — e.g. unified Leagues slot width.
     * @param {HTMLElement} btn
     * @param {number} px
     */
    function setTriggerWidthPx(btn, px) {
        if (!btn || !px || px <= 0) {
            return;
        }
        var w = Math.ceil(px) + 'px';
        btn.style.minWidth = w;
        btn.style.width = w;
        btn.style.maxWidth = w;
        var box = btn.closest('[data-k2-archive-listbox], .k2-archive-listbox');
        if (box) {
            box.style.minWidth = w;
            box.style.width = w;
            box.style.maxWidth = w;
        }
    }

    /** Row width for split options (label + meta); ignores fixed data-trigger-label. */
    function optionRowContentWidth(btn, opt) {
        if (!btn || !opt || !opt.classList.contains('k2-archive-listbox__option--split')) {
            return 0;
        }
        var labelNode = opt.querySelector('.k2-archive-listbox__option-label');
        var metaNode = opt.querySelector('.k2-archive-listbox__option-meta');
        var left = labelNode && labelNode.textContent ? labelNode.textContent : '';
        var right = metaNode && metaNode.textContent ? metaNode.textContent : '';
        var pad = 20;
        var gap = 12;
        if (!right) {
            return measureTextWidth(btn, left) + pad;
        }
        return measureTextWidth(btn, left) + gap + measureTextWidth(btn, right) + pad;
    }

    function syncTriggerWidth(box) {
        if (!box) {
            return;
        }
        if (box.classList.contains('k2-player-opponents-h2h__listbox')) {
            return;
        }
        if (box.closest('.k2-realm-games-filters')) {
            return;
        }
        if (box.closest('.k2-amiga-history__picker')) {
            return;
        }
        if (box.classList.contains('k2-archive-listbox--ghost-sized')) {
            return;
        }
        var btn = trigger(box);
        if (!btn) {
            return;
        }
        if (box.classList.contains('k2-archive-listbox--meta-options')) {
            var metaMax = 0;
            var metaOpts = options(box);
            for (var m = 0; m < metaOpts.length; m++) {
                metaMax = Math.max(metaMax, optionRowContentWidth(btn, metaOpts[m]));
            }
            var metaLbl = labelEl(box);
            if (metaLbl && metaLbl.textContent) {
                metaMax = Math.max(metaMax, measureTextWidth(btn, metaLbl.textContent) + 16);
            }
            if (metaMax > 0) {
                setTriggerWidthPx(btn, metaMax + TRIGGER_WIDTH_EXTRA_PX);
            }
            return;
        }
        var texts = [];
        var opts = options(box);
        for (var i = 0; i < opts.length; i++) {
            texts.push(optionTriggerLabel(opts[i]) || opts[i].textContent || '');
        }
        var lbl = labelEl(box);
        if (lbl && lbl.textContent) {
            texts.push(lbl.textContent);
        }
        if (!texts.length) {
            return;
        }
        syncTriggerWidthForButton(btn, texts);
    }

    function scrollOptionIntoView(opt) {
        if (!opt || !opt.scrollIntoView) {
            return;
        }
        try {
            opt.scrollIntoView({ block: 'nearest' });
        } catch (e) {
            opt.scrollIntoView(false);
        }
    }

    function isOptionDisabled(opt) {
        return opt && (opt.classList.contains('is-disabled') || opt.getAttribute('aria-disabled') === 'true');
    }

    /** Trigger text for rich options (name + meta in panel, short label on button). */
    function optionTriggerLabel(opt) {
        if (!opt) {
            return '';
        }
        var trigger = opt.getAttribute('data-trigger-label');
        if (trigger) {
            return trigger;
        }
        var nameEl = opt.querySelector('.k2-h2h-listbox__name, .player-search-name, .k2-archive-listbox__option-label');
        if (nameEl && nameEl.textContent) {
            return nameEl.textContent;
        }
        return opt.textContent || '';
    }

    function optionIsAccented(opt) {
        if (!opt) {
            return false;
        }
        if (opt.getAttribute('data-option-accent') === '1') {
            return true;
        }
        var labelNode = splitOptionLabelNode(opt);
        return !!(labelNode && labelNode.classList.contains('k2-link-star'));
    }

    function syncTriggerAccent(box) {
        var btn = trigger(box);
        if (!btn) {
            return;
        }
        var accented;
        if (box.hasAttribute('data-k2-listbox-idle-value')) {
            accented = String(getValue(box)) !== String(box.getAttribute('data-k2-listbox-idle-value'));
        } else if (box.getAttribute('data-k2-listbox-accent-active') === '1') {
            accented = true;
        } else {
            accented = optionIsAccented(findOption(box, getValue(box)));
        }
        btn.classList.toggle('k2-link-star', accented);
        var metaEl = triggerMetaEl(box);
        if (metaEl) {
            metaEl.classList.toggle('k2-link-star', accented);
        }
    }

    function markSelected(box, value) {
        var opts = options(box);
        for (var i = 0; i < opts.length; i++) {
            var on = opts[i].getAttribute('data-value') === value;
            opts[i].setAttribute('aria-selected', on ? 'true' : 'false');
            opts[i].classList.toggle('is-selected', on);
        }
    }

    function findOption(box, value) {
        var opts = options(box);
        for (var i = 0; i < opts.length; i++) {
            if (opts[i].getAttribute('data-value') === value) {
                return opts[i];
            }
        }
        return null;
    }

    function labelForValue(box, value) {
        var opt = findOption(box, value);
        if (opt) {
            return optionTriggerLabel(opt) || opt.textContent || value;
        }
        return value;
    }

    function renderChoices(list, choices) {
        if (!list) {
            return;
        }
        list.innerHTML = '';
        if (!choices || !choices.length) {
            return;
        }
        for (var i = 0; i < choices.length; i++) {
            var c = choices[i];
            var value = String(c.value);
            var li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.setAttribute('data-value', value);
            li.className = 'k2-archive-listbox__option';
            li.textContent = c.label != null ? String(c.label) : value;
            if (c.disabled) {
                li.classList.add('is-disabled');
                li.setAttribute('aria-disabled', 'true');
            } else {
                li.setAttribute('aria-selected', 'false');
            }
            list.appendChild(li);
        }
    }

    function afterChoicesRendered(box) {
        if (box) {
            syncTriggerWidth(box);
        }
    }

    function splitOptionLabelNode(opt) {
        if (!opt) {
            return null;
        }
        return opt.querySelector('.k2-archive-listbox__option-label')
            || opt.querySelector('.k2-h2h-listbox__name')
            || opt.querySelector('.player-search-name');
    }

    function ensureOption(box, value, label) {
        var existing = findOption(box, value);
        if (existing) {
            if (label) {
                var labelNode = splitOptionLabelNode(existing);
                if (labelNode) {
                    if (labelNode.textContent !== label) {
                        labelNode.textContent = label;
                    }
                } else if (existing.textContent !== label) {
                    existing.textContent = label;
                }
            }
            return existing;
        }
        var list = panel(box);
        if (!list) {
            return null;
        }
        var li = document.createElement('li');
        li.setAttribute('role', 'option');
        li.setAttribute('data-value', value);
        li.setAttribute('aria-selected', 'false');
        li.className = 'k2-archive-listbox__option';
        li.textContent = label || value;
        list.insertBefore(li, list.firstChild);
        return li;
    }

    function flatpickrCalendar(box) {
        return box && box.closest ? box.closest('.flatpickr-calendar') : null;
    }

    function clearPanelInlineStyles(list) {
        if (!list) {
            return;
        }
        list.classList.remove('k2-archive-listbox__panel--floated');
        list.style.position = '';
        list.style.left = '';
        list.style.top = '';
        list.style.right = '';
        list.style.width = '';
        list.style.minWidth = '';
        list.style.maxWidth = '';
        list.style.zIndex = '';
    }

    /** Panels detached onto .flatpickr-calendar by an older build — put them back and hide. */
    function recoverFlatpickrListboxes(cal) {
        if (!cal || !cal.querySelectorAll) {
            return;
        }
        var panels = cal.querySelectorAll('.k2-archive-listbox__panel');
        for (var i = 0; i < panels.length; i++) {
            var list = panels[i];
            var box = list.closest('[data-k2-archive-listbox]');
            clearPanelInlineStyles(list);
            list.hidden = true;
            if (box && list.parentNode !== box) {
                box.appendChild(list);
            } else if (!box) {
                list.remove();
            }
            if (box) {
                setTriggerExpanded(box, false);
            }
        }
        if (OPEN && cal.contains(OPEN)) {
            OPEN = null;
        }
    }

    function closePeersInCalendar(box) {
        var cal = flatpickrCalendar(box);
        if (!cal) {
            return;
        }
        var boxes = cal.querySelectorAll('[data-k2-archive-listbox]');
        for (var i = 0; i < boxes.length; i++) {
            if (boxes[i] !== box) {
                close(boxes[i]);
            }
        }
    }

    function interactionInsideBox(box, target) {
        if (!box || !target) {
            return false;
        }
        if (box.contains(target)) {
            return true;
        }
        var list = panel(box);
        return !!(list && list.contains(target));
    }

    function close(box, opts) {
        if (!box) {
            return;
        }
        opts = opts || {};
        var list = panel(box);
        if (list) {
            list.hidden = true;
        }
        setTriggerExpanded(box, false);
        if (OPEN === box) {
            OPEN = null;
        }
        if (!opts.keepTriggerFocus) {
            restoreTriggerFocus(box, false);
        }
    }

    /** Mouse pick: blur so :focus-visible ring does not flash; keyboard: return focus to trigger. */
    function restoreTriggerFocus(box, useFocus) {
        var btn = trigger(box);
        if (!btn) {
            return;
        }
        if (useFocus) {
            try {
                btn.focus({ preventScroll: true });
            } catch (e) {
                btn.focus();
            }
            return;
        }
        btn.blur();
    }

    function closeAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        if (scope.classList && scope.classList.contains('flatpickr-calendar')) {
            recoverFlatpickrListboxes(scope);
        }
        var boxes = scope.querySelectorAll('[data-k2-archive-listbox]');
        for (var i = 0; i < boxes.length; i++) {
            close(boxes[i]);
        }
        if (OPEN) {
            close(OPEN);
        }
    }

    function isListboxUiTarget(target) {
        return !!(target && target.closest && target.closest('[data-k2-archive-listbox]'));
    }

    function shieldFlatpickrListbox(box) {
        if (!box || !flatpickrCalendar(box) || box._k2FlatpickrShield) {
            return;
        }
        box._k2FlatpickrShield = true;
        var stop = function (e) {
            e.stopPropagation();
        };
        var btn = trigger(box);
        var list = panel(box);
        /* Mousedown/pointerdown capture only — stopPropagation on click capture would block bindBox’s click handler. */
        if (btn) {
            btn.addEventListener('mousedown', stop, true);
            btn.addEventListener('pointerdown', stop, true);
        }
        if (list) {
            list.addEventListener('mousedown', stop, true);
            list.addEventListener('pointerdown', stop, true);
        }
    }

    function open(box, opts) {
        if (!box) {
            return;
        }
        opts = opts || {};
        if (!box._k2ArchiveListboxBound) {
            bindBox(box);
        }
        closePeersInCalendar(box);
        if (OPEN && OPEN !== box) {
            close(OPEN);
        }
        var list = panel(box);
        var btn = trigger(box);
        if (!list || !btn || btn.disabled) {
            return;
        }
        list.hidden = false;
        setTriggerExpanded(box, true);
        OPEN = box;
        var val = getValue(box);
        markSelected(box, val);
        scrollOptionIntoView(findOption(box, val));
        if (opts.focusPanel) {
            try {
                list.focus({ preventScroll: true });
            } catch (e) {
                list.focus();
            }
        }
    }

    function commit(box, value, label, silent) {
        if (!box || value == null) {
            return;
        }
        var text = label || labelForValue(box, value);
        if (!isInline(box)) {
            ensureOption(box, value, text);
        }
        var input = valueInput(box);
        if (input) {
            input.value = value;
        } else {
            box._k2ListboxValue = value;
        }
        var lbl = labelEl(box);
        if (lbl) {
            lbl.textContent = text;
        }
        var metaEl = triggerMetaEl(box);
        if (metaEl) {
            metaEl.textContent = optionMetaText(findOption(box, value));
        }
        markSelected(box, value);
        syncTriggerAccent(box);
        syncTriggerWidth(box);
        if (!silent) {
            if (input) {
                try {
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                } catch (e) {
                    var ev = document.createEvent('HTMLEvents');
                    ev.initEvent('change', true, false);
                    input.dispatchEvent(ev);
                }
            } else if (typeof box._k2OnSelect === 'function') {
                box._k2OnSelect(value, text);
            }
        }
    }

    function activeOptionIndex(box) {
        var opts = options(box);
        for (var i = 0; i < opts.length; i++) {
            if (opts[i].getAttribute('aria-selected') === 'true') {
                return i;
            }
        }
        return opts.length ? 0 : -1;
    }

    function nextEnabledIndex(box, fromIdx, delta) {
        var opts = options(box);
        if (!opts.length) {
            return -1;
        }
        var idx = fromIdx;
        for (var n = 0; n < opts.length; n++) {
            idx += delta;
            if (idx < 0) {
                idx = opts.length - 1;
            }
            if (idx >= opts.length) {
                idx = 0;
            }
            if (!isOptionDisabled(opts[idx])) {
                return idx;
            }
        }
        return -1;
    }

    function moveHighlight(box, delta) {
        var opts = options(box);
        if (!opts.length) {
            return;
        }
        var idx = activeOptionIndex(box);
        var next = nextEnabledIndex(box, idx, delta);
        if (next < 0) {
            return;
        }
        var val = opts[next].getAttribute('data-value');
        markSelected(box, val);
        scrollOptionIntoView(opts[next]);
    }

    function selectHighlighted(box) {
        var idx = activeOptionIndex(box);
        var opts = options(box);
        if (idx < 0 || !opts[idx] || isOptionDisabled(opts[idx])) {
            return;
        }
        var value = opts[idx].getAttribute('data-value');
        var label = optionTriggerLabel(opts[idx]) || opts[idx].textContent || value;
        commit(box, value, label, false);
        close(box, { keepTriggerFocus: true });
        restoreTriggerFocus(box, true);
    }

    function onPanelClick(box, e) {
        var li = e.target.closest ? e.target.closest('[role="option"]') : null;
        if (!li || !box.contains(li) || isOptionDisabled(li)) {
            return;
        }
        e.preventDefault();
        if (!li.hasAttribute('data-value')) {
            return;
        }
        var value = li.getAttribute('data-value');
        commit(box, value, optionTriggerLabel(li) || li.textContent || value, false);
        close(box);
        restoreTriggerFocus(box, false);
    }

    function bindBox(box) {
        if (!box || box._k2ArchiveListboxBound) {
            return;
        }
        box._k2ArchiveListboxBound = true;
        var btn = trigger(box);
        var list = panel(box);
        if (list) {
            list.addEventListener('mousedown', function (e) {
                e.stopPropagation();
                if (e.target.closest && e.target.closest('[role="option"]')) {
                    e.preventDefault();
                }
            });
            list.addEventListener('click', function (e) {
                e.stopPropagation();
                onPanelClick(box, e);
            });
            list.addEventListener('keydown', function (e) {
                var key = e.key || e.keyCode;
                if (key === 'Escape' || key === 'Esc' || key === 27) {
                    e.preventDefault();
                    close(box, { keepTriggerFocus: true });
                    restoreTriggerFocus(box, true);
                } else if (key === 'ArrowDown' || key === 'Down' || key === 40) {
                    e.preventDefault();
                    moveHighlight(box, 1);
                } else if (key === 'ArrowUp' || key === 'Up' || key === 38) {
                    e.preventDefault();
                    moveHighlight(box, -1);
                } else if (key === 'Home' || key === 36) {
                    e.preventDefault();
                    var optsHome = options(box);
                    for (var h = 0; h < optsHome.length; h++) {
                        if (!isOptionDisabled(optsHome[h])) {
                            markSelected(box, optsHome[h].getAttribute('data-value'));
                            scrollOptionIntoView(optsHome[h]);
                            break;
                        }
                    }
                } else if (key === 'End' || key === 35) {
                    e.preventDefault();
                    var optsEnd = options(box);
                    for (var eidx = optsEnd.length - 1; eidx >= 0; eidx--) {
                        if (!isOptionDisabled(optsEnd[eidx])) {
                            markSelected(box, optsEnd[eidx].getAttribute('data-value'));
                            scrollOptionIntoView(optsEnd[eidx]);
                            break;
                        }
                    }
                } else if (key === 'Enter' || key === 13) {
                    e.preventDefault();
                    selectHighlighted(box);
                }
            });
        }
        if (btn) {
            btn.addEventListener('mousedown', function (e) {
                e.stopPropagation();
            });
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var listEl = panel(box);
                if (listEl && !listEl.hidden) {
                    close(box);
                } else {
                    open(box, { focusPanel: false });
                    restoreTriggerFocus(box, false);
                }
            });
            btn.addEventListener('keydown', function (e) {
                var key = e.key || e.keyCode;
                if (key === 'ArrowDown' || key === 'Down' || key === 40) {
                    e.preventDefault();
                    open(box, { focusPanel: true });
                } else if (key === 'Enter' || key === ' ' || key === 'Spacebar' || key === 32) {
                    e.preventDefault();
                    open(box, { focusPanel: true });
                }
            });
        }
    }

    function init(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var boxes = scope.querySelectorAll('[data-k2-archive-listbox]');
        for (var i = 0; i < boxes.length; i++) {
            bindBox(boxes[i]);
            var inp = valueInput(boxes[i]);
            if (inp && inp.value !== '') {
                commit(boxes[i], inp.value, labelForValue(boxes[i], inp.value), true);
            }
            syncTriggerWidth(boxes[i]);
        }
    }

    /**
     * @param {{ compact?: boolean, ariaLabel: string, choices: Array<{value: string, label?: string, disabled?: boolean}>, value?: string, onSelect?: function, parent?: Element, insertBefore?: Element }} config
     */
    function createInline(config) {
        config = config || {};
        var box = document.createElement('div');
        box.className = 'k2-archive-listbox k2-archive-listbox--inline';
        if (config.compact) {
            box.className += ' k2-archive-listbox--compact';
        }
        box.setAttribute('data-k2-archive-listbox', '');
        box.setAttribute('data-k2-listbox-inline', '1');

        var listId = 'k2-listbox-' + String(Math.random()).slice(2, 10);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'k2-archive-listbox__trigger';
        btn.setAttribute('aria-label', config.ariaLabel || 'Choose option');
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', listId);
        var span = document.createElement('span');
        span.className = 'k2-archive-listbox__label';
        btn.appendChild(span);
        var chevron = document.createElement('span');
        chevron.className = 'k2-archive-listbox__chevron';
        chevron.setAttribute('aria-hidden', 'true');
        btn.appendChild(chevron);

        var list = document.createElement('ul');
        list.id = listId;
        list.className = 'k2-archive-listbox__panel';
        list.setAttribute('role', 'listbox');
        list.tabIndex = -1;
        list.hidden = true;

        renderChoices(list, config.choices || []);
        box._k2OnSelect = config.onSelect || null;
        box._k2ListboxValue = config.value != null ? String(config.value) : '';

        box.appendChild(btn);
        box.appendChild(list);

        if (config.parent) {
            if (config.insertBefore && config.insertBefore.parentNode) {
                config.parent.insertBefore(box, config.insertBefore);
            } else {
                config.parent.appendChild(box);
            }
        }

        bindBox(box);
        shieldFlatpickrListbox(box);
        afterChoicesRendered(box);
        setValue(box, box._k2ListboxValue, null, true);
        return box;
    }

    function rebuild(box, choices, value) {
        if (!box) {
            return;
        }
        renderChoices(panel(box), choices || []);
        afterChoicesRendered(box);
        if (value != null) {
            setValue(box, String(value), null, true);
        }
    }

    function setValue(box, value, label, silent) {
        if (!box) {
            return;
        }
        commit(box, value, label, !!silent);
    }

    function closeFlatpickrPanels(cal) {
        if (!cal) {
            return;
        }
        recoverFlatpickrListboxes(cal);
        var boxes = cal.querySelectorAll('[data-k2-archive-listbox]');
        for (var i = 0; i < boxes.length; i++) {
            close(boxes[i]);
        }
    }

    if (!global._k2ArchiveListboxDocOutside) {
        global._k2ArchiveListboxDocOutside = true;
        /* Capture mousedown so we close + blur before focus moves (avoids trigger ring flash). */
        document.addEventListener(
            'mousedown',
            function (e) {
                if (!OPEN) {
                    return;
                }
                if (isListboxUiTarget(e.target) || interactionInsideBox(OPEN, e.target)) {
                    return;
                }
                close(OPEN);
            },
            true
        );
    }

    global.K2ArchiveListbox = {
        init: init,
        open: open,
        close: close,
        closeAll: closeAll,
        closeFlatpickrPanels: closeFlatpickrPanels,
        recoverFlatpickrListboxes: recoverFlatpickrListboxes,
        shieldFlatpickrListbox: shieldFlatpickrListbox,
        setValue: setValue,
        rebuild: rebuild,
        createInline: createInline,
        syncTriggerWidth: syncTriggerWidth,
        syncTriggerWidthForButton: syncTriggerWidthForButton,
        setTriggerWidthPx: setTriggerWidthPx,
        formatLabel: null,
    };

    function dismissStaleOpenListbox() {
        if (OPEN && !OPEN.isConnected) {
            OPEN = null;
        }
        if (OPEN) {
            close(OPEN);
        }
    }

    function onPageReadyListbox() {
        dismissStaleOpenListbox();
        init(document);
    }

    if (typeof window !== 'undefined') {
        if (typeof window.k2OnPageReady === 'function') {
            window.k2OnPageReady(onPageReadyListbox);
        } else if (typeof window.k2PageReady === 'function') {
            window.k2PageReady(onPageReadyListbox);
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', onPageReadyListbox);
            } else {
                onPageReadyListbox();
            }
        } else if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', onPageReadyListbox);
        } else {
            onPageReadyListbox();
        }
    }
}(typeof window !== 'undefined' ? window : this));
