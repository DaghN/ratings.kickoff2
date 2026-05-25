(function () {
	'use strict';

	var TABLE_SELECTOR = 'table[data-k2-table~="sortable"]';
	var HELP_HEADER_SELECTOR = 'th.k2-table-sortable[data-k2-help], th.k2-table-sortable[data-k2-tooltip-label]';
	var SORTABLE_CLASS = 'k2-table-sortable';
	var SORTED_ASC_CLASS = 'k2-table-sorted-asc';
	var SORTED_DESC_CLASS = 'k2-table-sorted-desc';
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
		var help = trimText(header.getAttribute('data-k2-help'));
		var title = trimText(header.getAttribute('data-k2-tooltip-label') || header.textContent || header.innerText);
		var tooltip;
		var body;

		if (!header.getAttribute('data-k2-sort') && !header.getAttribute('data-k2-help') && !header.getAttribute('data-k2-tooltip-label')) {
			return;
		}

		tooltip = getTooltip();
		body = tooltip.querySelector ? tooltip.querySelector('.k2-table-tooltip__body') : null;

		setText(tooltip.querySelector ? tooltip.querySelector('.k2-table-tooltip__title') : null, title);
		setText(body, help);
		if (body) {
			body.style.display = help ? '' : 'none';
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

	function setSortState(table, header, direction) {
		table._k2SortIndex = header.cellIndex;
		table._k2SortDirection = direction;

		clearSortState(table);
		addClass(header, direction === 'desc' ? SORTED_DESC_CLASS : SORTED_ASC_CLASS);
		header.setAttribute('aria-sort', direction === 'desc' ? 'descending' : 'ascending');
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

	function sortTable(table, header) {
		var columnIndex = header.cellIndex;
		var sortType = header.getAttribute('data-k2-sort') || 'text';
		var isSameColumn = table._k2SortIndex === columnIndex;
		var direction = isSameColumn && table._k2SortDirection === 'desc' ? 'asc' : 'desc';
		var tbody = table.tBodies && table.tBodies[0];
		var rows;
		var mapped;
		var i;

		if (!tbody) {
			return;
		}

		rows = Array.prototype.slice.call(tbody.rows);

		if (isSameColumn) {
			rows.reverse();
			for (i = 0; i < rows.length; i++) {
				tbody.appendChild(rows[i]);
			}

			setSortState(table, header, direction);
			refreshRankColumn(table);
			return;
		}

		mapped = rows.map(function (row, index) {
			return {
				row: row,
				index: index,
				value: getSortValue(row, columnIndex, sortType)
			};
		});

		mapped.sort(function (a, b) {
			var result = compareValues(a, b, sortType);
			if (result === 0) {
				return a.index - b.index;
			}
			return direction === 'desc' ? -result : result;
		});

		for (i = 0; i < mapped.length; i++) {
			tbody.appendChild(mapped[i].row);
		}

		table._k2SortIndex = columnIndex;
		table._k2SortDirection = direction;

		setSortState(table, header, direction);
		refreshRankColumn(table);
	}

	function initHeaderTooltip(header) {
		if (header.getAttribute(TOOLTIP_BOUND_ATTR) === 'true') {
			return;
		}

		header.setAttribute(TOOLTIP_BOUND_ATTR, 'true');
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

		applyDefaultSortState(table);
		refreshRankColumn(table);
		removeClass(table, PENDING_CLASS);
	}

	function init() {
		var tables = document.querySelectorAll ? document.querySelectorAll(TABLE_SELECTOR) : [];
		var helpHeaders = document.querySelectorAll ? document.querySelectorAll(HELP_HEADER_SELECTOR) : [];
		var i;

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

	onReady(init);
})();
