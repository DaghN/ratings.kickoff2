/**
 * Coarse pointer UX — first tap previews (pinned tooltip), second tap same target acts.
 * Used by profile heatmaps and Chart.js bar drill-downs (tooltips stay off on phone for scroll).
 */
(function (global) {
    'use strict';

    var CHART_TIP_ID = 'k2-coarse-chart-tooltip';
    var dismissInstalled = false;
    var pins = Object.create(null);

    function isCoarsePointer() {
        if (global.K2ChartTheme && global.K2ChartTheme.isCoarsePointer) {
            return global.K2ChartTheme.isCoarsePointer();
        }
        return global.matchMedia('(pointer: coarse)').matches
            || global.matchMedia('(hover: none)').matches;
    }

    function getPin(scopeId) {
        return pins[scopeId] || null;
    }

    function clearPin(scopeId) {
        var pin = pins[scopeId];
        if (pin && pin.clearVisual) {
            pin.clearVisual();
        }
        delete pins[scopeId];
    }

    function clearAllPins() {
        Object.keys(pins).forEach(clearPin);
    }

    function setPin(scopeId, key, clearVisual) {
        clearPin(scopeId);
        pins[scopeId] = { key: key, clearVisual: clearVisual || null };
    }

    function isPinned(scopeId, key) {
        var pin = getPin(scopeId);
        return !!(pin && pin.key === key);
    }

    function chartTooltipEl() {
        var tip = document.getElementById(CHART_TIP_ID);
        if (tip) {
            return tip;
        }
        tip = document.createElement('div');
        tip.id = CHART_TIP_ID;
        tip.className = 'k2-table-tooltip k2-table-tooltip--coarse-pin';
        tip.setAttribute('role', 'tooltip');
        tip.setAttribute('aria-hidden', 'true');
        tip.innerHTML = '<div class="k2-table-tooltip__title"></div><div class="k2-table-tooltip__body"></div>';
        tip.hidden = true;
        document.body.appendChild(tip);
        return tip;
    }

    function hideChartTooltip() {
        var tip = document.getElementById(CHART_TIP_ID);
        if (tip) {
            tip.hidden = true;
            tip.setAttribute('aria-hidden', 'true');
        }
    }

    function hideAllTableTooltips() {
        hideChartTooltip();
        var tips = document.querySelectorAll('.k2-table-tooltip');
        var i;
        for (i = 0; i < tips.length; i++) {
            tips[i].hidden = true;
            tips[i].setAttribute('aria-hidden', 'true');
        }
    }

    function positionTooltipAtRect(rect, tip) {
        var margin = 8;
        var left;
        var top;
        var tipRect;
        tip.style.left = '0px';
        tip.style.top = '0px';
        tip.hidden = false;
        tipRect = tip.getBoundingClientRect();
        left = rect.left + rect.width / 2 - tipRect.width / 2;
        left = Math.max(margin, Math.min(left, global.innerWidth - tipRect.width - margin));
        top = rect.top - tipRect.height - margin;
        if (top < margin) {
            top = rect.bottom + margin;
        }
        tip.style.left = Math.round(left) + 'px';
        tip.style.top = Math.round(top) + 'px';
    }

    function showChartTooltip(title, body, rect) {
        var tip = chartTooltipEl();
        var titleEl = tip.querySelector('.k2-table-tooltip__title');
        var bodyEl = tip.querySelector('.k2-table-tooltip__body');
        if (titleEl) {
            titleEl.textContent = title || '';
        }
        if (bodyEl) {
            bodyEl.textContent = body || '';
            bodyEl.style.display = body ? '' : 'none';
        }
        tip.setAttribute('aria-hidden', 'false');
        positionTooltipAtRect(rect, tip);
    }

    function pickBarElement(chart, evt, elements) {
        if (elements && elements.length) {
            return elements[0];
        }
        if (chart && evt && typeof chart.getElementsAtEventForMode === 'function') {
            var alongX = chart.getElementsAtEventForMode(
                evt,
                'nearest',
                { intersect: false, axis: 'x' },
                false
            );
            if (alongX.length) {
                return alongX[0];
            }
            var precise = chart.getElementsAtEventForMode(
                evt,
                'nearest',
                { intersect: true },
                false
            );
            if (precise.length) {
                return precise[0];
            }
        }
        return null;
    }

    function verticalBarRect(canvas, chart, barEl) {
        var box = canvas.getBoundingClientRect();
        var meta = chart.getDatasetMeta(barEl.datasetIndex);
        var element = meta.data[barEl.index];
        if (!element) {
            return null;
        }
        var top = box.top + Math.min(element.y, element.base);
        var height = Math.abs(element.base - element.y);
        return {
            left: box.left + element.x - element.width / 2,
            top: top,
            width: element.width,
            height: height,
            right: box.left + element.x + element.width / 2,
            bottom: top + height
        };
    }

    function horizontalBarRect(canvas, chart, barEl) {
        var box = canvas.getBoundingClientRect();
        var meta = chart.getDatasetMeta(barEl.datasetIndex);
        var element = meta.data[barEl.index];
        if (!element) {
            return null;
        }
        var left = box.left + Math.min(element.x, element.base);
        var width = Math.abs(element.base - element.x);
        return {
            left: left,
            top: box.top + element.y - element.height / 2,
            width: width,
            height: element.height,
            right: left + width,
            bottom: box.top + element.y + element.height / 2
        };
    }

    function pinChartBar(chart, barEl) {
        chart.setActiveElements([{ datasetIndex: barEl.datasetIndex, index: barEl.index }]);
        chart.update('none');
    }

    function unpinChartBar(chart) {
        chart.setActiveElements([]);
        chart.update('none');
    }

    function installDismiss() {
        if (dismissInstalled) {
            return;
        }
        dismissInstalled = true;
        global.addEventListener('scroll', function () {
            hideAllTableTooltips();
            clearAllPins();
            if (typeof global.CustomEvent === 'function') {
                global.dispatchEvent(new CustomEvent('k2-coarse-tap-dismiss'));
            }
        }, { passive: true, capture: true });
        document.addEventListener('pointerdown', function (evt) {
            if (!Object.keys(pins).length) {
                return;
            }
            var target = evt.target;
            var tips = document.querySelectorAll('.k2-table-tooltip');
            var ti;
            for (ti = 0; ti < tips.length; ti++) {
                if (!tips[ti].hidden && tips[ti].contains(target)) {
                    return;
                }
            }
            if (target.closest) {
                if (target.closest('.pm3-cal__cell--pinned')) {
                    return;
                }
                if (target.closest('.k2-table-helped--pinned')) {
                    return;
                }
                if (target.closest('canvas')) {
                    return;
                }
            }
            hideAllTableTooltips();
            clearAllPins();
            if (typeof global.CustomEvent === 'function') {
                global.dispatchEvent(new CustomEvent('k2-coarse-tap-dismiss'));
            }
        }, true);
    }

    /**
     * Chart.js onClick — desktop: immediate onNavigate; coarse: preview then second tap.
     */
    function createChartClickHandler(options) {
        installDismiss();
        var scopeId = options.scopeId;
        var chart = options.chart;
        var canvas = options.canvas;
        var pickElement = options.pickElement || pickBarElement;
        var getAnchorRect = options.getAnchorRect || verticalBarRect;
        var pinKey = options.pinKey;
        var isActive = options.isActive;
        var getTitle = options.getTitle;
        var getBody = options.getBody;
        var hintNavigate = options.hintNavigate || 'view games';
        var onNavigate = options.onNavigate;

        return function (evt, elements) {
            var el = pickElement(chart, evt, elements);
            if (!el || !isActive(el)) {
                return;
            }

            if (!isCoarsePointer()) {
                onNavigate(el);
                return;
            }

            var key = pinKey(el);
            if (isPinned(scopeId, key)) {
                hideChartTooltip();
                clearPin(scopeId);
                onNavigate(el);
                return;
            }

            clearAllPins();
            pinChartBar(chart, el);
            setPin(scopeId, key, function () {
                unpinChartBar(chart);
            });

            var rect = getAnchorRect(canvas, chart, el);
            if (!rect) {
                return;
            }
            showChartTooltip(
                getTitle(el),
                getBody(el) + ' · Tap again to ' + hintNavigate,
                rect
            );
        };
    }

    /**
     * DOM cells (profile heatmaps) — call from click handler when coarse.
     */
    function handleDomTap(scopeId, key, cell, config) {
        installDismiss();
        var pinnedClass = config.pinnedClass || 'pm3-cal__cell--pinned';

        if (isPinned(scopeId, key)) {
            clearPin(scopeId);
            if (config.onDismiss) {
                config.onDismiss();
            }
            if (config.isActionable && config.isActionable()) {
                config.onConfirm();
            }
            return;
        }

        clearAllPins();
        if (cell) {
            cell.classList.add(pinnedClass);
            setPin(scopeId, key, function () {
                cell.classList.remove(pinnedClass);
            });
        } else {
            setPin(scopeId, key, null);
        }
        if (config.onPreview) {
            config.onPreview(true);
        }
    }

    function shouldUseHoverTooltips() {
        return !isCoarsePointer();
    }

    global.K2CoarseTap = {
        isCoarsePointer: isCoarsePointer,
        shouldUseHoverTooltips: shouldUseHoverTooltips,
        createChartClickHandler: createChartClickHandler,
        handleDomTap: handleDomTap,
        clearPin: clearPin,
        clearAllPins: clearAllPins,
        isPinned: isPinned,
        pickBarElement: pickBarElement,
        verticalBarRect: verticalBarRect,
        horizontalBarRect: horizontalBarRect,
        hideChartTooltip: hideChartTooltip,
        installDismiss: installDismiss
    };
})(typeof window !== 'undefined' ? window : this);
