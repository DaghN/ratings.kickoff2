(function () {
	'use strict';

	var TABLE_SELECTOR = 'table[data-k2-table~="sortable"]';
	var HELP_HEADER_SELECTOR = '[data-k2-help], [data-k2-tooltip-label]';
	var SORTABLE_CLASS = 'k2-table-sortable';
	var HELPED_CLASS = 'k2-table-helped';
	var SORTED_ASC_CLASS = 'k2-table-sorted-asc';
	var SORTED_DESC_CLASS = 'k2-table-sorted-desc';
	var ANCHOR_CELL_CLASS = 'k2-table-anchor-cell';
	var SORTED_COL_CLASS = 'k2-table-col-sorted';
	var PENDING_CLASS = 'ranked-table-pending';
	var TOOLTIP_BOUND_ATTR = 'data-k2-tooltip-bound';
	var TOOLTIP_ID = 'k2-table-tooltip';
	var COARSE_TAP_SCOPE = 'k2-help-link';
	var PINNED_HELP_CLASS = 'k2-table-helped--pinned';
	var activeTooltipHeader = null;

	function addClass(el, className) {
		if (el.classList) {
			el.classList.add(className);
			return;
		}
		if ((' ' + el.className + ' ').indexOf(' ' + className + ' ') === -1) {
			el.className += ' ' + className;
		}
	}

	function removeClass(el, className) {
		if (el.classList) {
			el.classList.remove(className);
			return;
		}
		el.className = (' ' + el.className + ' ').replace(' ' + className + ' ', ' ').replace(/^\s+|\s+$/g, '');
	}

	function hasClass(el, className) {
		return (' ' + (el.className || '') + ' ').indexOf(' ' + className + ' ') !== -1;
	}

	function onReady(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
			return;
		}
		fn();
	}

	function trimText(value) {
		return String(value || '').replace(/\s+/g, ' ').replace(/^\s+|\s+$/g, '');
	}

	function trimHelpText(value) {
		return String(value || '')
			.replace(/\r\n/g, '\n')
			.replace(/\r/g, '\n')
			.replace(/[ \t]+\n/g, '\n')
			.replace(/\n[ \t]+/g, '\n')
			.replace(/[ \t]+/g, ' ')
			.replace(/\n{3,}/g, '\n\n')
			.replace(/^\s+|\s+$/g, '');
	}

	function getTooltip() {
		var tooltip = document.getElementById(TOOLTIP_ID);

		if (tooltip) {
			return tooltip;
		}

		tooltip = document.createElement('div');
		tooltip.id = TOOLTIP_ID;
		tooltip.className = 'k2-table-tooltip';
		tooltip.setAttribute('role', 'tooltip');
		tooltip.setAttribute('aria-hidden', 'true');
		tooltip.innerHTML = '<div class="k2-table-tooltip__title"></div><div class="k2-table-tooltip__body"></div><div class="k2-table-tooltip__action">Click to sort.</div>';
		tooltip.hidden = true;
		document.body.appendChild(tooltip);

		return tooltip;
	}

	function setText(el, value) {
		if (el) {
			el.textContent = value;
		}
	}

	function positionTooltip(header, tooltip) {
		var headerRect = header.getBoundingClientRect();
		var tooltipRect;
		var left;
		var top;
		var margin = 8;
		var align = header.getAttribute('data-k2-tooltip-align') || 'center';

		tooltip.style.left = '0px';
		tooltip.style.top = '0px';
		tooltip.hidden = false;
		tooltipRect = tooltip.getBoundingClientRect();

		if (align === 'start') {
			left = headerRect.left;
		} else {
			left = headerRect.left + (headerRect.width / 2) - (tooltipRect.width / 2);
		}
		left = Math.max(margin, Math.min(left, window.innerWidth - tooltipRect.width - margin));
		top = headerRect.top - tooltipRect.height - margin;

		if (top < margin) {
			top = headerRect.bottom + margin;
		}

		tooltip.style.left = Math.round(left) + 'px';
		tooltip.style.top = Math.round(top) + 'px';
	}

	function showTooltip(header) {
		var helpRaw = header.getAttribute('data-k2-help') || '';
		var helpIsHtml = header.getAttribute('data-k2-help-html') === '1';
		var help = helpIsHtml ? helpRaw : trimHelpText(helpRaw);
		var hideTitle = header.getAttribute('data-k2-tooltip-hide-title') === '1';
		var titleLabel = header.getAttribute('data-k2-tooltip-label');
		var title = hideTitle
			? ''
			: trimText(titleLabel || header.textContent || header.innerText);
		var isSortable = !!header.getAttribute('data-k2-sort') || hasClass(header, SORTABLE_CLASS);
		var tooltip;
		var body;
		var action;
		var titleEl;

		if (!header.getAttribute('data-k2-sort') && !header.getAttribute('data-k2-help') && !header.getAttribute('data-k2-tooltip-label')) {
			return;
		}

		tooltip = getTooltip();
		body = tooltip.querySelector ? tooltip.querySelector('.k2-table-tooltip__body') : null;
		action = tooltip.querySelector ? tooltip.querySelector('.k2-table-tooltip__action') : null;
		titleEl = tooltip.querySelector ? tooltip.querySelector('.k2-table-tooltip__title') : null;

		setText(titleEl, title);
		if (titleEl) {
			titleEl.style.display = title ? '' : 'none';
		}
		if (body) {
			if (helpIsHtml) {
				body.innerHTML = help;
			} else {
				setText(body, help);
			}
			body.style.display = help ? '' : 'none';
		}
		if (action) {
			var customAction = trimText(header.getAttribute('data-k2-tooltip-action') || '');
			if (customAction) {
				setText(action, customAction);
				action.style.display = '';
			} else if (isSortable) {
				setText(action, 'Click to sort.');
				action.style.display = '';
			} else {
				action.style.display = 'none';
			}
		}

		var tierAccent = header.getAttribute('data-k2-tooltip-tier');
		if (tierAccent) {
			tooltip.setAttribute('data-k2-tooltip-tier', tierAccent);
		} else {
			tooltip.removeAttribute('data-k2-tooltip-tier');
		}

		activeTooltipHeader = header;
		header.setAttribute('aria-describedby', TOOLTIP_ID);
		tooltip.setAttribute('aria-hidden', 'false');
		positionTooltip(header, tooltip);
	}

	function hideTooltip(header) {
		var tooltip = document.getElementById(TOOLTIP_ID);

		if (header && activeTooltipHeader !== header) {
			return;
		}

		activeTooltipHeader = null;
		if (header) {
			header.removeAttribute('aria-describedby');
		}
		if (tooltip) {
			tooltip.hidden = true;
			tooltip.setAttribute('aria-hidden', 'true');
			tooltip.removeAttribute('data-k2-tooltip-tier');
		}
	}

	function repositionTooltip() {
		var tooltip;

		if (!activeTooltipHeader) {
			return;
		}

		tooltip = document.getElementById(TOOLTIP_ID);
		if (tooltip && !tooltip.hidden) {
			positionTooltip(activeTooltipHeader, tooltip);
		}
	}

	function getSortValue(row, columnIndex, sortType) {
		var cell = row.cells[columnIndex];
		var value;

		if (!cell) {
			return sortType === 'number' ? 0 : '';
		}

		value = cell.getAttribute('data-k2-sort-value');
		if (value === null) {
			value = cell.textContent || cell.innerText || '';
		}

		if (sortType === 'number') {
			value = parseFloat(String(value).replace(/,/g, '').replace(/[^0-9.\-]/g, ''));
			return isNaN(value) ? 0 : value;
		}

		return String(value).replace(/\s+/g, ' ').replace(/^\s+|\s+$/g, '').toLowerCase();
	}

	/**
	 * Secondary key when primary compare ties.
	 * Default: always ascending (e.g. games highlights — lower game ID first on equal scores).
	 * data-k2-sort-tie-order="match": follow primary sort direction (milestone unlock # on same achieved_at).
	 */
	function getSortTieValue(row, columnIndex) {
		var cell = row.cells[columnIndex];
		var raw;
		var num;

		if (cell) {
			raw = cell.getAttribute('data-k2-sort-tie-value');
			if (raw !== null && raw !== '') {
				num = parseFloat(String(raw).replace(/,/g, ''));
				return isNaN(num) ? NaN : num;
			}
		}

		raw = row.getAttribute('data-k2-sort-tie-value');
		if (raw !== null && raw !== '') {
			num = parseFloat(String(raw).replace(/,/g, ''));
			return isNaN(num) ? NaN : num;
		}

		return NaN;
	}

	function compareValues(a, b, sortType) {
		if (a.value === b.value) {
			return 0;
		}

		if (sortType === 'number') {
			return a.value - b.value;
		}

		return a.value < b.value ? -1 : 1;
	}

	function refreshRankColumn(table) {
		var enabled = table.getAttribute('data-k2-autorank') === 'true';
		var bodies;
		var rank;
		var i;
		var rows;
		var j;
		var row;

		if (!enabled) {
			return;
		}

		bodies = table.tBodies || [];
		rank = 1;

		for (i = 0; i < bodies.length; i++) {
			rows = bodies[i].rows;
			for (j = 0; j < rows.length; j++) {
				row = rows[j];
				if (row.style.display === 'none' || !row.cells || !row.cells[0]) {
					continue;
				}
				row.cells[0].textContent = String(rank++);
			}
		}
	}

	function clearSortState(table) {
		var headers = table.tHead ? table.tHead.getElementsByTagName('th') : [];
		var i;

		for (i = 0; i < headers.length; i++) {
			removeClass(headers[i], SORTED_ASC_CLASS);
			removeClass(headers[i], SORTED_DESC_CLASS);
			if (headers[i].getAttribute('data-k2-sort')) {
				headers[i].setAttribute('aria-sort', 'none');
			}
		}
	}

	function getAnchorColIndex(table) {
		var index = parseInt(table.getAttribute('data-k2-anchor-col'), 10);
		return isNaN(index) ? -1 : index;
	}

	function clearColumnBodyClass(table, className) {
		var bodies = table.tBodies || [];
		var i;
		var rows;
		var j;
		var row;
		var k;

		for (i = 0; i < bodies.length; i++) {
			rows = bodies[i].rows;
			for (j = 0; j < rows.length; j++) {
				row = rows[j];
				if (!row.cells) {
					continue;
				}
				for (k = 0; k < row.cells.length; k++) {
					removeClass(row.cells[k], className);
				}
			}
		}
	}

	function applyAnchorColumn(table) {
		var index = getAnchorColIndex(table);
		var bodies;
		var i;
		var rows;
		var j;
		var row;
		var k;

		clearColumnBodyClass(table, ANCHOR_CELL_CLASS);
		if (index < 0) {
			return;
		}

		bodies = table.tBodies || [];
		for (i = 0; i < bodies.length; i++) {
			rows = bodies[i].rows;
			for (j = 0; j < rows.length; j++) {
				row = rows[j];
				if (!row.cells || !row.cells[index]) {
					continue;
				}
				addClass(row.cells[index], ANCHOR_CELL_CLASS);
			}
		}
	}

	function refreshSortedColumnEmphasis(table) {
		var anchorIndex = getAnchorColIndex(table);
		var sortIndex = table._k2SortIndex;
		var bodies;
		var i;
		var rows;
		var j;
		var row;

		clearColumnBodyClass(table, SORTED_COL_CLASS);
		if (sortIndex === undefined || sortIndex === null || sortIndex < 0) {
			return;
		}
		if (anchorIndex >= 0 && sortIndex === anchorIndex) {
			return;
		}

		bodies = table.tBodies || [];
		for (i = 0; i < bodies.length; i++) {
			rows = bodies[i].rows;
			for (j = 0; j < rows.length; j++) {
				row = rows[j];
				if (!row.cells || !row.cells[sortIndex]) {
					continue;
				}
				if (row.cells[sortIndex].colSpan > 1) {
					continue;
				}
				addClass(row.cells[sortIndex], SORTED_COL_CLASS);
			}
		}
	}

	function setSortState(table, header, direction) {
		table._k2SortIndex = header.cellIndex;
		table._k2SortDirection = direction;

		clearSortState(table);
		addClass(header, direction === 'desc' ? SORTED_DESC_CLASS : SORTED_ASC_CLASS);
		header.setAttribute('aria-sort', direction === 'desc' ? 'descending' : 'ascending');
		refreshSortedColumnEmphasis(table);
	}

	function applyDefaultSortState(table) {
		var index = parseInt(table.getAttribute('data-k2-default-sort'), 10);
		var direction = table.getAttribute('data-k2-default-direction') === 'asc' ? 'asc' : 'desc';
		var headers = table.tHead ? table.tHead.getElementsByTagName('th') : [];

		if (isNaN(index) || !headers[index] || !headers[index].getAttribute('data-k2-sort')) {
			return false;
		}

		return sortTableByIndex(table, index, direction);
	}

	/** Sort header chrome only — no tbody reorder (SSR order trusted). */
	function applyDefaultSortHeaderState(table) {
		var index = parseInt(table.getAttribute('data-k2-default-sort'), 10);
		var direction = table.getAttribute('data-k2-default-direction') === 'asc' ? 'asc' : 'desc';
		var headers = table.tHead ? table.tHead.getElementsByTagName('th') : [];
		var header;

		if (isNaN(index) || !headers[index] || !headers[index].getAttribute('data-k2-sort')) {
			return;
		}

		header = headers[index];
		table._k2SortIndex = index;
		table._k2SortDirection = direction;
		setSortState(table, header, direction);
	}

	function getSortScope(table) {
		var scope = table && table.getAttribute ? table.getAttribute('data-k2-sort-scope') : '';
		scope = scope ? String(scope).trim() : '';
		return scope;
	}

	function sortUrlParamKey(scope, base) {
		return scope ? base + '_' + scope : base;
	}

	function syncSortToUrl(columnIndex, direction, table) {
		var url;
		var scope;

		if (!table || !hasClass(table, 'ranked-pages-table')) {
			return;
		}

		if (!window.history || !window.history.replaceState || !window.URLSearchParams) {
			refreshLbFilterToggleHrefs();
			refreshTimeTravelRibbonHrefs();
			return;
		}

		scope = getSortScope(table);
		url = new URL(window.location.href);
		url.searchParams.set(sortUrlParamKey(scope, 'k2_sort'), String(columnIndex));
		url.searchParams.set(sortUrlParamKey(scope, 'k2_dir'), direction === 'asc' ? 'asc' : 'desc');
		window.history.replaceState(null, '', url.pathname + url.search + url.hash);
		refreshLbFilterToggleHrefs();
		refreshTimeTravelRibbonHrefs();
	}

	function applySortParamsToUrl(target, current) {
		if (current.searchParams.has('k2_sort')) {
			target.searchParams.set('k2_sort', current.searchParams.get('k2_sort'));
			target.searchParams.set('k2_dir', current.searchParams.get('k2_dir') || 'desc');
		} else {
			target.searchParams.delete('k2_sort');
			target.searchParams.delete('k2_dir');
		}
	}

	function syncTimeTravelPickerSortFields(form, current) {
		var sortInput = form.querySelector('input[name="k2_sort"]');
		var dirInput = form.querySelector('input[name="k2_dir"]');
		if (current.searchParams.has('k2_sort')) {
			if (!sortInput) {
				sortInput = document.createElement('input');
				sortInput.type = 'hidden';
				sortInput.name = 'k2_sort';
				form.insertBefore(sortInput, form.firstChild);
			}
			if (!dirInput) {
				dirInput = document.createElement('input');
				dirInput.type = 'hidden';
				dirInput.name = 'k2_dir';
				form.insertBefore(dirInput, form.firstChild);
			}
			sortInput.value = current.searchParams.get('k2_sort');
			dirInput.value = current.searchParams.get('k2_dir') || 'desc';
			return;
		}
		if (sortInput) {
			sortInput.parentNode.removeChild(sortInput);
		}
		if (dirInput) {
			dirInput.parentNode.removeChild(dirInput);
		}
	}

	function refreshTimeTravelRibbonHrefs() {
		var ribbon = document.querySelector('[data-k2-preserve-table-sort="1"]');
		var current;
		var links;
		var i;
		var link;
		var target;
		var form;

		if (!ribbon || !window.URLSearchParams) {
			return;
		}

		current = new URL(window.location.href);
		links = ribbon.querySelectorAll('a[href]');
		for (i = 0; i < links.length; i++) {
			link = links[i];
			if (!link.href) {
				continue;
			}
			target = new URL(link.href, window.location.href);
			if (target.pathname !== current.pathname) {
				continue;
			}
			applySortParamsToUrl(target, current);
			link.href = target.pathname + target.search + target.hash;
		}

		form = ribbon.querySelector('.k2-amiga-history__picker');
		if (form) {
			syncTimeTravelPickerSortFields(form, current);
		}
	}

	function refreshLbFilterToggleHrefs() {
		var filters = document.querySelectorAll ? document.querySelectorAll('.k2-lb-filter') : [];
		var current = new URL(window.location.href);
		var i;
		var link;
		var target;

		if (!window.URLSearchParams) {
			return;
		}

		for (i = 0; i < filters.length; i++) {
			link = filters[i];
			if (!link.href) {
				continue;
			}
			target = new URL(link.href, window.location.href);
			if (current.searchParams.has('k2_sort')) {
				target.searchParams.set('k2_sort', current.searchParams.get('k2_sort'));
				target.searchParams.set('k2_dir', current.searchParams.get('k2_dir') || 'desc');
			} else {
				target.searchParams.delete('k2_sort');
				target.searchParams.delete('k2_dir');
			}
			link.href = target.pathname + target.search;
		}
	}

	function getUrlSortParams(table) {
		var params;
		var sortRaw;
		var index;
		var dirRaw;
		var scope;
		var sortKey;
		var dirKey;

		if (!window.URLSearchParams) {
			return null;
		}

		scope = getSortScope(table);
		sortKey = sortUrlParamKey(scope, 'k2_sort');
		dirKey = sortUrlParamKey(scope, 'k2_dir');
		params = new URLSearchParams(window.location.search);
		if (!params.has(sortKey)) {
			return null;
		}

		sortRaw = params.get(sortKey);
		index = parseInt(sortRaw, 10);
		if (isNaN(index)) {
			return null;
		}

		dirRaw = params.get(dirKey);
		return {
			index: index,
			direction: dirRaw === 'asc' ? 'asc' : 'desc'
		};
	}

	function sortTableByIndex(table, columnIndex, direction) {
		var headers = table.tHead ? table.tHead.getElementsByTagName('th') : [];
		var header = headers[columnIndex];
		var sortType;
		var tbody;
		var rows;
		var mapped;
		var i;

		if (!header || !header.getAttribute('data-k2-sort')) {
			return false;
		}

		sortType = header.getAttribute('data-k2-sort') || 'text';
		tbody = table.tBodies && table.tBodies[0];
		if (!tbody) {
			return false;
		}

		rows = Array.prototype.slice.call(tbody.rows);
		mapped = rows.map(function (row, index) {
			return {
				row: row,
				index: index,
				value: getSortValue(row, columnIndex, sortType)
			};
		});

		mapped.sort(function (a, b) {
			var result = compareValues(a, b, sortType);
			var tieA;
			var tieB;
			var tieResult = 0;

			if (result !== 0) {
				return direction === 'desc' ? -result : result;
			}

			tieA = getSortTieValue(a.row, columnIndex);
			tieB = getSortTieValue(b.row, columnIndex);
			if (!isNaN(tieA) && !isNaN(tieB) && tieA !== tieB) {
				tieResult = tieA - tieB;
				if (table.getAttribute('data-k2-sort-tie-order') === 'match') {
					return direction === 'desc' ? -tieResult : tieResult;
				}

				return tieResult;
			}

			return a.index - b.index;
		});

		for (i = 0; i < mapped.length; i++) {
			tbody.appendChild(mapped[i].row);
		}

		table._k2SortIndex = columnIndex;
		table._k2SortDirection = direction;
		setSortState(table, header, direction);
		refreshRankColumn(table);
		syncSortToUrl(columnIndex, direction, table);

		return true;
	}

	function applyUrlSortState(table) {
		var urlSort = getUrlSortParams(table);

		if (!urlSort || !hasClass(table, 'ranked-pages-table')) {
			return false;
		}

		return sortTableByIndex(table, urlSort.index, urlSort.direction);
	}

	function isCoarseTapHelpTarget(header) {
		return header && header.getAttribute('data-k2-coarse-tap') === '1';
	}

	function coarseTapModule() {
		return typeof window !== 'undefined' ? window.K2CoarseTap : null;
	}

	function updateCoarseTooltipAction(header) {
		var tooltip = document.getElementById(TOOLTIP_ID);
		var action;
		var coarseText;

		if (!tooltip) {
			return;
		}
		action = tooltip.querySelector ? tooltip.querySelector('.k2-table-tooltip__action') : null;
		if (!action) {
			return;
		}
		coarseText = trimText(header.getAttribute('data-k2-tooltip-action-coarse') || '');
		if (!coarseText) {
			coarseText = trimText(header.getAttribute('data-k2-tooltip-action') || '');
			if (coarseText.indexOf('Click ') === 0) {
				coarseText = 'Tap again to ' + coarseText.slice(6);
			} else if (coarseText) {
				coarseText = 'Tap again — ' + coarseText;
			}
		}
		setText(action, coarseText);
		action.style.display = coarseText ? '' : 'none';
	}

	function initCoarseTapHelpLink(header) {
		var CT = coarseTapModule();

		if (!isCoarseTapHelpTarget(header) || !CT || !CT.isCoarsePointer || !CT.handleDomTap) {
			return;
		}

		header.addEventListener('click', function (evt) {
			var href;

			if (!CT.isCoarsePointer()) {
				return;
			}

			href = header.getAttribute('href') || '';
			evt.preventDefault();
			if (CT.installDismiss) {
				CT.installDismiss();
			}

			CT.handleDomTap(COARSE_TAP_SCOPE, href, header, {
				pinnedClass: PINNED_HELP_CLASS,
				onDismiss: function () {
					hideTooltip(header);
				},
				onPreview: function () {
					showTooltip(header);
					updateCoarseTooltipAction(header);
				},
				isActionable: function () {
					return href !== '';
				},
				onConfirm: function () {
					hideTooltip(header);
					if (href) {
						window.location.href = href;
					}
				}
			});
		});
	}

	function sortTable(table, header) {
		var columnIndex = header.cellIndex;
		var isSameColumn = table._k2SortIndex === columnIndex;
		var direction;

		if (isSameColumn) {
			direction = table._k2SortDirection === 'desc' ? 'asc' : 'desc';
		} else {
			direction = header.getAttribute('data-k2-sort-first') === 'asc' ? 'asc' : 'desc';
		}

		sortTableByIndex(table, columnIndex, direction);
	}

	function isHoverOnlyTooltipTarget(header) {
		if (!header) {
			return false;
		}
		if (isCoarseTapHelpTarget(header)) {
			var CT = coarseTapModule();
			if (CT && CT.isCoarsePointer && CT.isCoarsePointer()) {
				return false;
			}
		}
		if (header.getAttribute('data-k2-tooltip-hover-only') === '1') {
			return true;
		}
		/* Nav links (chevrons, etc.) — hover + aria-label; focus flash on click is distracting */
		return header.tagName === 'A' && !!header.getAttribute('href');
	}

	function initHeaderTooltip(header) {
		if (header.getAttribute(TOOLTIP_BOUND_ATTR) === 'true') {
			return;
		}

		var hoverOnly = isHoverOnlyTooltipTarget(header);

		header.setAttribute(TOOLTIP_BOUND_ATTR, 'true');
		addClass(header, HELPED_CLASS);
		header.removeAttribute('title');
		header.addEventListener('mouseenter', function () {
			showTooltip(this);
		});
		header.addEventListener('mouseleave', function () {
			hideTooltip(this);
		});
		if (!hoverOnly) {
			header.addEventListener('focusin', function () {
				showTooltip(this);
			});
			header.addEventListener('focusout', function () {
				hideTooltip(this);
			});
		}
		header.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				hideTooltip(this);
			}
		});
		if (hoverOnly && !isCoarseTapHelpTarget(header)) {
			header.addEventListener('mousedown', function () {
				hideTooltip(this);
			});
		}
		initCoarseTapHelpLink(header);
	}

	function initTable(table) {
		var headers = table.tHead ? table.tHead.getElementsByTagName('th') : [];
		var i;

		for (i = 0; i < headers.length; i++) {
			if (!headers[i].getAttribute('data-k2-sort')) {
				continue;
			}

			addClass(headers[i], SORTABLE_CLASS);
			headers[i].setAttribute('aria-sort', 'none');
			headers[i].setAttribute('tabindex', '0');
			if (headers[i].getAttribute('data-k2-help') || headers[i].getAttribute('data-k2-tooltip-label')) {
				initHeaderTooltip(headers[i]);
			}
			headers[i].addEventListener('click', function () {
				sortTable(table, this);
				this.blur();
			});
			headers[i].addEventListener('keydown', function (event) {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					sortTable(table, this);
					this.blur();
				}
			});
		}

		applyAnchorColumn(table);
		if (table.getAttribute('data-k2-skip-initial-sort') !== '1') {
			if (!applyUrlSortState(table)) {
				applyDefaultSortState(table);
			}
		} else {
			applyDefaultSortHeaderState(table);
		}
		refreshSortedColumnEmphasis(table);
		refreshRankColumn(table);
		if (hasClass(table, 'ranked-pages-table')) {
			refreshLbFilterToggleHrefs();
			refreshTimeTravelRibbonHrefs();
		}
		removeClass(table, PENDING_CLASS);
	}

	function initAnchorTables() {
		var tables = document.querySelectorAll ? document.querySelectorAll('table[data-k2-anchor-col]') : [];
		var i;

		for (i = 0; i < tables.length; i++) {
			applyAnchorColumn(tables[i]);
		}
	}

	function revealRemainingPendingTables() {
		var pending = document.querySelectorAll
			? document.querySelectorAll('table.ranked-pages-table.' + PENDING_CLASS)
			: [];
		var i;

		for (i = 0; i < pending.length; i++) {
			removeClass(pending[i], PENDING_CLASS);
		}
	}

	/** Server-sorted ranked tables: reveal after anchor/tooltip init (and scroll mirror when present). */
	function scheduleServerRankedTableReveal() {
		function revealWhenReady() {
			if (document.fonts && document.fonts.ready) {
				document.fonts.ready.then(revealRemainingPendingTables).catch(revealRemainingPendingTables);
				return;
			}
			revealRemainingPendingTables();
		}

		if (document.querySelector('.k2-table-wrap[data-k2-scroll-mirror]')) {
			window.k2TableRevealPendingRankedTables = revealWhenReady;
			return;
		}

		revealWhenReady();
	}

	function init() {
		var tables = document.querySelectorAll ? document.querySelectorAll(TABLE_SELECTOR) : [];
		var helpHeaders = document.querySelectorAll ? document.querySelectorAll(HELP_HEADER_SELECTOR) : [];
		var i;

		initAnchorTables();

		for (i = 0; i < tables.length; i++) {
			initTable(tables[i]);
		}

		for (i = 0; i < helpHeaders.length; i++) {
			initHeaderTooltip(helpHeaders[i]);
		}

		if (tables.length || helpHeaders.length) {
			window.addEventListener('resize', repositionTooltip);
			window.addEventListener('scroll', repositionTooltip, true);
		}

		scheduleServerRankedTableReveal();
	}

	window.k2TableApplyAnchors = function (root) {
		var tables;
		var i;

		if (!root) {
			tables = document.querySelectorAll ? document.querySelectorAll('table[data-k2-anchor-col]') : [];
		} else if (root.tagName === 'TABLE') {
			applyAnchorColumn(root);
			return;
		} else if (root.querySelectorAll) {
			tables = root.querySelectorAll('table[data-k2-anchor-col]');
		} else {
			return;
		}

		for (i = 0; i < tables.length; i++) {
			applyAnchorColumn(tables[i]);
		}
	};

	onReady(init);
})();
