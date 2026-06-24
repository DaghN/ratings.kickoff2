/**
 * Amiga time travel temporal stamp - tier-1 arrival + ambient cursor blink (CSS).
 *
 * @see docs/amiga-time-travel-policy.md section 5.0
 */
(function (global) {
    'use strict';

    var ENTRY_PARAM = 'k2_tt_entry';
    var ARRIVE_ANIMATION = 'k2-tt-stamp-arrive';
    var CURSOR_BLINK_KEY = 'k2-tt-stamp-cursor-blink';
    var CURSOR_TIP_ID = 'k2-tt-stamp-cursor-tooltip';
    var CURSOR_HELP_ON = 'Click to pause cursor blink';
    var CURSOR_HELP_OFF = 'Click to resume cursor blink';
    var TIP_OFFSET_X = 7;
    var TIP_OFFSET_Y = 7;
    var viewportMargin = 8;
    var lastPointer = { x: 0, y: 0 };

    function prefersReducedMotion() {
        return global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function stampRoot() {
        return document.querySelector('.k2-amiga-tt-stamp');
    }

    function clearArrivalPending(stamp) {
        global.document.documentElement.classList.remove('k2-tt-arrival-pending');
        if (stamp) {
            stamp.classList.remove('k2-amiga-tt-stamp--arrival-pending');
        }
    }

    function consumeArrivalFromUrl() {
        try {
            var params = new URLSearchParams(global.location.search);
            if (params.get(ENTRY_PARAM) !== '1') {
                return false;
            }
            params.delete(ENTRY_PARAM);
            var qs = params.toString();
            var next = global.location.pathname + (qs ? '?' + qs : '') + global.location.hash;
            global.history.replaceState(null, '', next);
            return true;
        } catch (e) {
            return false;
        }
    }

    function restoreKickerText(kickerEl) {
        if (!kickerEl) {
            return;
        }
        var full = (kickerEl.getAttribute('data-k2-tt-kicker-text') || '').trim();
        if (full !== '') {
            kickerEl.textContent = full;
        }
    }

    function typewriter(el, text, maxMs) {
        if (!el || text === '') {
            return;
        }
        if ((el.textContent || '').trim() !== '') {
            return;
        }
        var delay = Math.min(36, Math.max(14, Math.floor(maxMs / text.length)));
        var i = 0;
        function tick() {
            if (i >= text.length) {
                return;
            }
            el.textContent += text.charAt(i);
            i += 1;
            global.setTimeout(tick, delay);
        }
        tick();
    }

    function finishArrivalClasses(stamp) {
        stamp.classList.remove('k2-amiga-tt-stamp--arrival-pending', 'k2-amiga-tt-stamp--arrival');
        clearArrivalPending(stamp);
    }

    function isCursorBlinkEnabled() {
        if (prefersReducedMotion()) {
            return false;
        }
        try {
            var stored = localStorage.getItem(CURSOR_BLINK_KEY);
            if (stored === '0') {
                return false;
            }
            if (stored === '1') {
                return true;
            }
        } catch (e) {
            /* ignore */
        }
        return true;
    }

    function cursorButton(stamp) {
        return stamp ? stamp.querySelector('.k2-amiga-tt-stamp__cursor') : null;
    }

    function cursorHelpText(on) {
        return on ? CURSOR_HELP_ON : CURSOR_HELP_OFF;
    }

    function rememberPointer(clientX, clientY) {
        if (typeof clientX === 'number' && typeof clientY === 'number') {
            lastPointer.x = clientX;
            lastPointer.y = clientY;
        }
    }

    function pointerFromButton(btn) {
        var rect = btn.getBoundingClientRect();
        return {
            x: rect.left + (rect.width / 2),
            y: rect.top + (rect.height / 2),
        };
    }

    function getCursorTooltip() {
        var tip = document.getElementById(CURSOR_TIP_ID);
        if (tip) {
            return tip;
        }
        tip = document.createElement('div');
        tip.id = CURSOR_TIP_ID;
        tip.className = 'k2-table-tooltip k2-amiga-tt-stamp__cursor-tip';
        tip.setAttribute('role', 'tooltip');
        tip.hidden = true;
        tip.innerHTML = '<div class="k2-table-tooltip__body"></div>';
        document.body.appendChild(tip);
        return tip;
    }

    function positionCursorTooltip(tip, clientX, clientY) {
        var tipRect;
        var left;
        var top;

        tip.style.left = '0px';
        tip.style.top = '0px';
        tip.hidden = false;
        tipRect = tip.getBoundingClientRect();

        left = clientX + TIP_OFFSET_X;
        top = clientY - tipRect.height - TIP_OFFSET_Y;

        if (left + tipRect.width > global.innerWidth - viewportMargin) {
            left = clientX - tipRect.width - TIP_OFFSET_X;
        }
        if (top < viewportMargin) {
            top = clientY + TIP_OFFSET_Y;
        }

        left = Math.max(viewportMargin, Math.min(left, global.innerWidth - tipRect.width - viewportMargin));
        top = Math.max(viewportMargin, Math.min(top, global.innerHeight - tipRect.height - viewportMargin));

        tip.style.left = Math.round(left) + 'px';
        tip.style.top = Math.round(top) + 'px';
    }

    function activeCursorButton() {
        var tip = document.getElementById(CURSOR_TIP_ID);
        if (!tip || tip.hidden) {
            return null;
        }
        var id = tip.getAttribute('data-k2-tt-cursor-for');
        if (!id) {
            return null;
        }
        return document.getElementById(id);
    }

    function hideCursorTooltip(btn) {
        var tip = document.getElementById(CURSOR_TIP_ID);
        if (!tip) {
            return;
        }
        tip.hidden = true;
        tip.setAttribute('aria-hidden', 'true');
        tip.removeAttribute('data-k2-tt-cursor-for');
        if (btn) {
            btn.removeAttribute('aria-describedby');
        }
    }

    function showCursorTooltip(btn, text, clientX, clientY) {
        var tip = getCursorTooltip();
        var body = tip.querySelector('.k2-table-tooltip__body');
        var point;

        if (body) {
            body.textContent = text;
        }
        if (!btn.id) {
            btn.id = 'k2-tt-stamp-cursor-btn';
        }
        if (typeof clientX === 'number' && typeof clientY === 'number') {
            rememberPointer(clientX, clientY);
        } else if (!btn.matches(':hover')) {
            point = pointerFromButton(btn);
            rememberPointer(point.x, point.y);
        }

        tip.setAttribute('data-k2-tt-cursor-for', btn.id);
        tip.setAttribute('aria-hidden', 'false');
        btn.setAttribute('aria-describedby', CURSOR_TIP_ID);
        positionCursorTooltip(tip, lastPointer.x, lastPointer.y);
    }

    function moveCursorTooltip(clientX, clientY) {
        var btn = activeCursorButton();
        var tip = document.getElementById(CURSOR_TIP_ID);
        if (!btn || !tip || tip.hidden) {
            return;
        }
        rememberPointer(clientX, clientY);
        positionCursorTooltip(tip, lastPointer.x, lastPointer.y);
    }

    function applyCursorBlinkState(stamp) {
        if (!stamp) {
            return;
        }
        var on = isCursorBlinkEnabled();
        stamp.classList.toggle('k2-amiga-tt-stamp--cursor-static', !on);
        var btn = cursorButton(stamp);
        if (!btn) {
            return;
        }
        var help = cursorHelpText(on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.setAttribute('aria-label', help);
        if (activeCursorButton() === btn) {
            showCursorTooltip(btn, help, lastPointer.x, lastPointer.y);
        }
    }

    function toggleCursorBlink(stamp) {
        if (prefersReducedMotion()) {
            return;
        }
        var nextOn = !isCursorBlinkEnabled();
        try {
            localStorage.setItem(CURSOR_BLINK_KEY, nextOn ? '1' : '0');
        } catch (e) {
            /* ignore */
        }
        applyCursorBlinkState(stamp);
    }

    function bindCursorToggle(stamp) {
        var btn = cursorButton(stamp);
        if (!btn || btn.dataset.k2TtCursorBound === '1') {
            return;
        }
        btn.dataset.k2TtCursorBound = '1';
        applyCursorBlinkState(stamp);

        btn.addEventListener('mouseenter', function (event) {
            showCursorTooltip(btn, btn.getAttribute('aria-label') || '', event.clientX, event.clientY);
        });
        btn.addEventListener('mousemove', function (event) {
            moveCursorTooltip(event.clientX, event.clientY);
        });
        btn.addEventListener('mouseleave', function () {
            hideCursorTooltip(btn);
        });
        btn.addEventListener('focus', function () {
            if (btn.matches(':hover')) {
                return;
            }
            showCursorTooltip(btn, btn.getAttribute('aria-label') || '');
        });
        btn.addEventListener('blur', function () {
            hideCursorTooltip(btn);
        });
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            toggleCursorBlink(stamp);
        });
    }

    function runToggleArrival(stamp) {
        var kickerEl = stamp.querySelector('.k2-amiga-tt-stamp__kicker-text');
        var full = kickerEl ? (kickerEl.getAttribute('data-k2-tt-kicker-text') || '').trim() : '';

        if (prefersReducedMotion()) {
            clearArrivalPending(stamp);
            restoreKickerText(kickerEl);
            return;
        }

        stamp.classList.add('k2-amiga-tt-stamp--arrival');

        stamp.addEventListener('animationend', function (event) {
            if (event.animationName !== ARRIVE_ANIMATION) {
                return;
            }
            finishArrivalClasses(stamp);
        }, { once: true });

        typewriter(kickerEl, full, 650);
    }

    function initStamp() {
        var arrival = consumeArrivalFromUrl();
        var stamp = stampRoot();
        if (!stamp || stamp.dataset.k2TtStampInit === '1') {
            if (arrival) {
                clearArrivalPending(stamp);
            }
            return;
        }
        stamp.dataset.k2TtStampInit = '1';
        bindCursorToggle(stamp);
        if (arrival) {
            runToggleArrival(stamp);
        }
    }

    global.addEventListener('scroll', function () {
        hideCursorTooltip(activeCursorButton());
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStamp);
    } else {
        initStamp();
    }
}(window));