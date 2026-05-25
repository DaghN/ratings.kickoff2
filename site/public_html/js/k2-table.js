(function () {
	'use strict';

	var TABLE_SELECTOR = 'table[data-k2-table~="sortable"]';
	var SORTABLE_CLASS = 'k2-table-sortable';
	var SORTED_ASC_CLASS = 'k2-table-sorted-asc';
	var SORTED_DESC_CLASS = 'k2-table-sorted-desc';
	var PENDING_CLASS = 'ranked-table-pending';

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
			return a.index - b.index;
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
		var direction = table._k2SortIndex === columnIndex && table._k2SortDirection === 'desc' ? 'asc' : 'desc';
		var tbody = table.tBodies && table.tBodies[0];
		var rows;
		var mapped;
		var i;

		if (!tbody) {
			return;
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
			headers[i].setAttribute('title', headers[i].getAttribute('title') || 'Click to sort');
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
		var i;

		for (i = 0; i < tables.length; i++) {
			initTable(tables[i]);
		}
	}

	onReady(init);
})();
