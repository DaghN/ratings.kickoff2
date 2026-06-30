/**
 * Amiga time travel ribbon — optional pin (C02).
 *
 * CSS position:sticky cannot span the page because the ribbon section is only
 * one bar tall at the top of .k2-page-nav; pinned mode uses position:fixed
 * with width synced to the page column.
 *
 * @see docs/creative-ideas-july-2026.md section 6.1
 */
(function (global) {
    'use strict';

    var STORAGE_KEY = 'k2-amiga-tt-ribbon-pinned';
    var PINNED_CLASS = 'k2-amiga-time-travel--pinned';
    var INIT_ATTR = 'data-k2-tt-pin-init';
    var layoutBound = false;
    var resizeObserver = null;
    var activeSection = null;

    function sectionRoot() {
        return document.querySelector('.k2-amiga-time-travel--active');
    }

    function readPinnedStorage() {
        try {
            return global.localStorage.getItem(STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function writePinnedStorage(pinned) {
        try {
            if (pinned) {
                global.localStorage.setItem(STORAGE_KEY, '1');
            } else {
                global.localStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) {
            /* ignore */
        }
    }

    function pinButton(section) {
        if (!section) {
            return null;
        }
        return section.querySelector('.k2-amiga-time-travel__pin');
    }

    function layoutAnchor(section) {
        if (!section) {
            return null;
        }
        return section.closest('.k2-page-nav') || section;
    }

    function barElement(section) {
        if (!section) {
            return null;
        }
        return section.querySelector('.k2-amiga-time-travel__bar');
    }

    function reserveSectionSpace(section, barHeight) {
        if (!section) {
            return;
        }
        section.style.minHeight = Math.max(0, Math.round(barHeight)) + 'px';
    }

    function syncPinnedGeometry(section) {
        if (!section || !section.classList.contains(PINNED_CLASS)) {
            return;
        }
        var bar = barElement(section);
        var anchor = layoutAnchor(section);
        if (!bar || !anchor) {
            return;
        }
        var rect = anchor.getBoundingClientRect();
        bar.style.left = Math.round(rect.left) + 'px';
        bar.style.width = Math.round(rect.width) + 'px';
        reserveSectionSpace(section, bar.offsetHeight);
    }

    function clearPinnedGeometry(section) {
        if (!section) {
            return;
        }
        var bar = barElement(section);
        section.style.minHeight = '';
        if (bar) {
            bar.style.left = '';
            bar.style.width = '';
        }
    }

    function observePinnedBar(section) {
        if (!global.ResizeObserver || !section) {
            return;
        }
        var bar = barElement(section);
        if (!bar) {
            return;
        }
        if (!resizeObserver) {
            resizeObserver = new global.ResizeObserver(function () {
                if (activeSection && activeSection.classList.contains(PINNED_CLASS)) {
                    syncPinnedGeometry(activeSection);
                }
            });
        }
        resizeObserver.disconnect();
        resizeObserver.observe(bar);
    }

    function bindLayoutListeners() {
        if (layoutBound) {
            return;
        }
        layoutBound = true;
        global.addEventListener('resize', function () {
            if (activeSection && activeSection.classList.contains(PINNED_CLASS)) {
                syncPinnedGeometry(activeSection);
            }
        });
        global.addEventListener('scroll', function () {
            if (activeSection && activeSection.classList.contains(PINNED_CLASS)) {
                syncPinnedGeometry(activeSection);
            }
        }, true);
    }

    function applyPinned(section, pinned) {
        if (!section) {
            return;
        }
        var bar = barElement(section);
        var reservedHeight = bar ? bar.offsetHeight : 0;

        if (pinned) {
            reserveSectionSpace(section, reservedHeight);
            section.classList.add(PINNED_CLASS);
            syncPinnedGeometry(section);
            observePinnedBar(section);
        } else {
            section.classList.remove(PINNED_CLASS);
            clearPinnedGeometry(section);
            if (resizeObserver) {
                resizeObserver.disconnect();
            }
        }

        activeSection = pinned ? section : null;

        var btn = pinButton(section);
        if (!btn) {
            return;
        }
        btn.classList.toggle('is-pinned', pinned);
        btn.setAttribute('aria-pressed', pinned ? 'true' : 'false');
        btn.setAttribute(
            'aria-label',
            pinned ? 'Unpin time travel controls' : 'Pin time travel controls'
        );
    }

    function bindPin(section) {
        var btn = pinButton(section);
        if (!btn || btn.getAttribute(INIT_ATTR) === '1') {
            return;
        }
        btn.setAttribute(INIT_ATTR, '1');
        btn.addEventListener('click', function () {
            var next = !section.classList.contains(PINNED_CLASS);
            applyPinned(section, next);
            writePinnedStorage(next);
        });
        if (typeof global.k2TableInitHelpTooltips === 'function') {
            global.k2TableInitHelpTooltips(btn);
        }
    }

    function init() {
        var section = sectionRoot();
        if (!section) {
            return;
        }
        bindLayoutListeners();
        bindPin(section);
        applyPinned(section, readPinnedStorage());
    }

    if (typeof global.k2OnPageReady === 'function') {
        global.k2OnPageReady(init);
    } else if (global.document.readyState === 'loading') {
        global.document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}(window));