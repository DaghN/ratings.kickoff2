(function () {
	'use strict';

	var TABLE_SELECTOR = 'table[data-k2-table~="sortable"]';
	var HELP_HEADER_SELECTOR = 'th[data-k2-help], th[data-k2-tooltip-label], td[data-k2-help]';
	var SORTABLE_CLASS = 'k2-table-sortable';
	var HELPED_CLASS = 'k2-table-helped';
	var SORTED_ASC_CLASS = 'k2-table-sorted-asc';
	var SORTED_DESC_CLASS = 'k2-table-sorted-desc';
	var ANCHOR_CELL_CLASS = 'k2-table-anchor-cell';
	var SORTED_COL_CLASS = 'k2-table-col-sorted';
	var PENDING_CLASS = 'ranked-table-pending';
	var TOOLTIP_BOUND_ATTR = 'data-k2-tooltip-bound';
	var TOOLTIP_ID = 'k2-table-tooltip';
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

		tooltip.style.left = '0px';
		tooltip.style.top = '0px';
		tooltip.hidden = false;
		tooltipRect = tooltip.getBoundingClientRect();

		left = headerRect.left + (headerRect.width / 2) - (tooltipRect.width / 2);
		left = Math.max(margin, Math.min(left, window.innerWidth - tooltipRect.width - margin));
		top = headerRect.top - tooltipRect.height - margin;

		if (top < margin) {
			top = headerRect.bottom + margin;
		}

		tooltip.style.left = Math.round(left) + 'px';
		tooltip.style.top = Math.round(top) + 'px';
	}

	function showTooltip(header) {
		var help = trimHelpText(header.getAttribute('data-k2-help'));
		var title = trimText(header.getAttribute('data-k2-tooltip-label') || header.textContent || header.innerText);
		var isSortable = !!header.getAttribute('data-k2-sort') || hasClass(header, SORTABLE_CLASS);
		var tooltip;
		var body;
		var action;

		if (!header.getAttribute('data-k2-sort') && !header.getAttribute('data-k2-help') && !header.getAttribute('data-k2-tooltip-label')) {
			return;
		}

		tooltip = getTooltip();
		body = tooltip.querySelector ? tooltip.querySelector('.k2-table-tooltip__body') : null;
		action = tooltip.querySelector ? tooltip.querySelector('.k2-table-tooltip__action') : null;

		setText(tooltip.querySelector ? tooltip.querySelector('.k2-table-tooltip__title') : null, title);
		setText(body, help);
		if (body) {
			body.style.display = help ? '' : 'none';
		}
		if (action) {
			action.style.display = isSortable ? '' : 'none';
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
			return;
		}

		setSortState(table, headers[index], direction);
	}

	function syncSortToUrl(columnIndex, direction, table) {
		var url;

		if (!table || !hasClass(table, 'ranked-pages-table')) {
			return;
		}

		if (!window.history || !window.history.replaceState || !window.URLSearchParams) {
			refreshLbFilterToggleHrefs();
			return;
		}

		url = new URL(window.location.href);
		url.searchParams.set('k2_sort', String(columnIndex));
		url.searchParams.set('k2_dir', direction === 'asc' ? 'asc' : 'desc');
		window.history.replaceState(null, '', url.pathname + url.search + url.hash);
		refreshLbFilterToggleHrefs();
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

	function getUrlSortParams() {
		var params;
		var sortRaw;
		var index;
		var dirRaw;

		if (!window.URLSearchParams) {
			return null;
		}

		params = new URLSearchParams(window.location.search);
		if (!params.has('k2_sort')) {
			return null;
		}

		sortRaw = params.get('k2_sort');
		index = parseInt(sortRaw, 10);
		if (isNaN(index)) {
			return null;
		}

		dirRaw = params.get('k2_dir');
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
		var urlSort = getUrlSortParams();

		if (!urlSort || !hasClass(table, 'ranked-pages-table')) {
			return false;
		}

		return sortTableByIndex(table, urlSort.index, urlSort.direction);
	}

	function sortTable(table, header) {
		var columnIndex = header.cellIndex;
		var isSameColumn = table._k2SortIndex === columnIndex;
		var direction;

		if (isSameColumn) {
			direction = table._k2SortDirection === 'desc' ? 'asc' : 'desc';
		} else {
			direction = 'desc';
		}

		sortTableByIndex(table, columnIndex, direction);
	}

	function initHeaderTooltip(header) {
		if (header.getAttribute(TOOLTIP_BOUND_ATTR) === 'true') {
			return;
		}

		header.setAttribute(TOOLTIP_BOUND_ATTR, 'true');
		addClass(header, HELPED_CLASS);
		header.removeAttribute('title');
		header.addEventListener('mouseenter', function () {
			showTooltip(this);
		});
		header.addEventListener('mouseleave', function () {
			hideTooltip(this);
		});
		header.addEventListener('focusin', function () {
			showTooltip(this);
		});
		header.addEventListener('focusout', function () {
			hideTooltip(this);
		});
		header.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				hideTooltip(this);
			}
		});
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
			initHeaderTooltip(headers[i]);
			headers[i].addEventListener('click', function () {
				sortTable(table, this);
			});
			headers[i].addEventListener('keydown', function (event) {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					sortTable(table, this);
				}
			});
		}

		applyAnchorColumn(table);
		if (!applyUrlSortState(table)) {
			applyDefaultSortState(table);
		}
		refreshSortedColumnEmphasis(table);
		refreshRankColumn(table);
		if (hasClass(table, 'ranked-pages-table')) {
			refreshLbFilterToggleHrefs();
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
