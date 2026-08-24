(function () {
	'use strict';

	var config = window.MediaRefInspectorAdmin || {};
	var startButton = document.getElementById('mediarefinspector-start-bulk');

	if (!startButton || !config.ajaxUrl || !config.nonce) {
		return;
	}

	var stopButton = document.getElementById('mediarefinspector-stop-bulk');
	var exportButton = document.getElementById('mediarefinspector-export-csv');
	var exportHtmlButton = document.getElementById('mediarefinspector-export-html');
	var exportJsonButton = document.getElementById('mediarefinspector-export-json');
	var progressWrap = document.getElementById('mediarefinspector-bulk-progress');
	var progressStatus = document.getElementById('mediarefinspector-progress-status');
	var progressCount = document.getElementById('mediarefinspector-progress-count');
	var progressBar = document.getElementById('mediarefinspector-progress-bar');
	var summary = document.getElementById('mediarefinspector-bulk-summary');
	var filterRow = document.getElementById('mediarefinspector-bulk-filter-row');
	var resultFilter = document.getElementById('mediarefinspector-result-filter');
	var resultSort = document.getElementById('mediarefinspector-result-sort');
	var sourceFilter = document.getElementById('mediarefinspector-source-filter');
	var healthFilter = document.getElementById('mediarefinspector-health-filter');
	var emptyState = document.getElementById('mediarefinspector-bulk-empty');
	var tableWrap = document.getElementById('mediarefinspector-bulk-table-wrap');
	var resultsBody = document.getElementById('mediarefinspector-bulk-results');
	var searchInput = document.getElementById('mediarefinspector-bulk-search');
	var typeInput = document.getElementById('mediarefinspector-bulk-type');
	var limitInput = document.getElementById('mediarefinspector-bulk-limit');
	var ageInput = document.getElementById('mediarefinspector-bulk-age');
	var selectedIdsInput = document.getElementById('mediarefinspector-selected-ids');
	var strings = config.strings || {};
	var stopped = false;
	var results = [];

	function request(payload) {
		var body = new URLSearchParams();
		Object.keys(payload).forEach(function (key) {
			body.append(key, payload[key]);
		});
		body.append('nonce', config.nonce);

		return fetch(config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		}).then(function (response) {
			return response.json();
		});
	}

	function setRunning(isRunning) {
		startButton.disabled = isRunning;
		stopButton.hidden = !isRunning;
		searchInput.disabled = isRunning;
		typeInput.disabled = isRunning;
		limitInput.disabled = isRunning;
		if (ageInput) { ageInput.disabled = isRunning; }
		if (selectedIdsInput) { selectedIdsInput.disabled = isRunning; }
	}

	function resetView() {
		stopped = false;
		results = [];
		resultsBody.replaceChildren();
		progressWrap.hidden = false;
		progressStatus.textContent = strings.starting || 'Preparing media scan…';
		progressCount.textContent = '0 / 0';
		progressBar.value = 0;
		progressBar.max = 100;
		summary.hidden = true;
		filterRow.hidden = true;
		tableWrap.hidden = true;
		emptyState.hidden = true;
		exportButton.disabled = true;
		if (exportHtmlButton) { exportHtmlButton.disabled = true; }
		if (exportJsonButton) { exportJsonButton.disabled = true; }
		updateSummary();
	}

	function updateProgress(current, total) {
		progressCount.textContent = current + ' / ' + total;
		progressBar.max = Math.max(total, 1);
		progressBar.value = current;
	}

	function updateSummary() {
		var counts = {
			scanned: results.length,
			referenced: 0,
			unreferenced: 0,
			errors: 0
		};

		results.forEach(function (item) {
			if (item.status === 'referenced') {
				counts.referenced += 1;
			} else if (item.status === 'unreferenced') {
				counts.unreferenced += 1;
			} else {
				counts.errors += 1;
			}
		});

		Object.keys(counts).forEach(function (key) {
			var el = summary.querySelector('[data-summary="' + key + '"]');
			if (el) {
				el.textContent = counts[key];
			}
		});
	}

	function appendCell(row, text, className) {
		var cell = document.createElement('td');
		if (className) {
			cell.className = className;
		}
		cell.textContent = text;
		row.appendChild(cell);
		return cell;
	}

	function makeStatusLabel(status) {
		if (status === 'referenced') {
			return strings.referenced || 'Referenced';
		}
		if (status === 'unreferenced') {
			return strings.noReferences || 'No supported references found';
		}
		return strings.error || 'Needs review';
	}

	function renderResult(item) {
		var row = document.createElement('tr');
		row.dataset.status = item.status;
		row.dataset.references = String(item.referenceCount || 0);
		row.dataset.title = String(item.title || '').toLowerCase();
		row.dataset.sources = Array.isArray(item.sourceCategories) ? item.sourceCategories.join(' ') : '';
		row.dataset.health = item.healthStatus || 'review';

		var mediaCell = document.createElement('td');
		var title = document.createElement('strong');
		var filename = document.createElement('code');
		title.textContent = item.title || ('Media #' + item.id);
		filename.textContent = item.filename || '';
		mediaCell.appendChild(title);
		mediaCell.appendChild(document.createElement('br'));
		mediaCell.appendChild(filename);
		row.appendChild(mediaCell);

		appendCell(row, item.mimeType || '');

		var statusCell = appendCell(row, makeStatusLabel(item.status));
		statusCell.classList.add('mediarefinspector-bulk-status', 'is-' + item.status);

		var types = Array.isArray(item.referenceTypes) ? item.referenceTypes.join(', ') : '';
		appendCell(row, item.referenceCount ? item.referenceCount + (types ? ' · ' + types : '') : '0');

		var actionsCell = document.createElement('td');
		if (item.inspectUrl) {
			var inspect = document.createElement('a');
			inspect.className = 'button button-small';
			inspect.href = item.inspectUrl;
			inspect.textContent = strings.inspect || 'Inspect';
			actionsCell.appendChild(inspect);
		}
		if (item.editAttachment) {
			var edit = document.createElement('a');
			edit.className = 'button button-small';
			edit.href = item.editAttachment;
			edit.textContent = strings.editMedia || 'Edit media';
			actionsCell.appendChild(edit);
		}
		row.appendChild(actionsCell);
		resultsBody.appendChild(row);
	}

	function renderError(id, message) {
		var item = {
			id: id,
			title: 'Media #' + id,
			filename: '',
			mimeType: '',
			referenceCount: 0,
			referenceTypes: [],
			status: 'error',
			errorMessage: message || strings.failed || 'Scan failed.'
		};
		results.push(item);
		renderResult(item);
		updateSummary();
	}

	function scanOne(id) {
		return request({
			action: 'mediarefinspector_bulk_scan_item',
			attachment_id: id
		}).then(function (response) {
			if (!response || !response.success || !response.data) {
				throw new Error(response && response.data && response.data.message ? response.data.message : (strings.failed || 'Scan failed.'));
			}
			results.push(response.data);
			renderResult(response.data);
			updateSummary();
		});
	}

	function runQueue(ids, index) {
		if (stopped || index >= ids.length) {
			finish(ids.length, stopped);
			return Promise.resolve();
		}

		progressStatus.textContent = strings.scanning || 'Scanning media…';
		updateProgress(index, ids.length);

		return scanOne(ids[index]).catch(function (error) {
			renderError(ids[index], error.message);
		}).then(function () {
			updateProgress(index + 1, ids.length);
			return runQueue(ids, index + 1);
		});
	}

	function finish(total, wasStopped) {
		setRunning(false);
		stopButton.disabled = false;
		progressStatus.textContent = wasStopped ? (strings.cancelled || 'Bulk scan stopped.') : (strings.complete || 'Bulk scan complete.');
		updateProgress(results.length, total || results.length);
		summary.hidden = false;
		filterRow.hidden = false;
		tableWrap.hidden = results.length === 0;
		emptyState.hidden = results.length > 0;
		exportButton.disabled = results.length === 0;
		if (exportHtmlButton) { exportHtmlButton.disabled = results.length === 0; }
		if (exportJsonButton) { exportJsonButton.disabled = results.length === 0; }
		applyResultFilter();
	}

	function applyResultFilter() {
		var filter = resultFilter.value || 'all';
		var source = sourceFilter ? (sourceFilter.value || 'all') : 'all';
		var health = healthFilter ? (healthFilter.value || 'all') : 'all';
		var rows = Array.prototype.slice.call(resultsBody.querySelectorAll('tr'));
		var sort = resultSort ? (resultSort.value || 'scan') : 'scan';
		if (sort !== 'scan') {
			rows.sort(function (a, b) {
				if (sort === 'title') { return (a.dataset.title || '').localeCompare(b.dataset.title || ''); }
				var av = parseInt(a.dataset.references || '0', 10);
				var bv = parseInt(b.dataset.references || '0', 10);
				return sort === 'references-asc' ? av - bv : bv - av;
			});
			rows.forEach(function (row) { resultsBody.appendChild(row); });
		}
		rows.forEach(function (row) {
			var statusMatch = filter === 'all' || row.dataset.status === filter;
			var sourceMatch = source === 'all' || String(row.dataset.sources || '').split(' ').indexOf(source) !== -1;
			var healthMatch = health === 'all' || row.dataset.health === health;
			row.hidden = !(statusMatch && sourceMatch && healthMatch);
		});
	}

	function csvEscape(value) {
		var text = String(value == null ? '' : value);
		return '"' + text.replace(/"/g, '""') + '"';
	}

	function htmlEscape(value) {
		return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[char]; });
	}

	function exportHtml() {
		if (!results.length) { return; }
		var body = results.map(function (item) {
			return '<tr><td>' + htmlEscape(item.id) + '</td><td>' + htmlEscape(item.title || '') + '</td><td>' + htmlEscape(makeStatusLabel(item.status)) + '</td><td>' + htmlEscape(item.referenceCount || 0) + '</td><td>' + htmlEscape((item.referenceTypes || []).join(', ')) + '</td><td>' + htmlEscape(item.healthStatus || 'review') + '</td></tr>';
		}).join('');
		var html = '<!doctype html><html><head><meta charset="utf-8"><title>Media Reference Inspector report</title><style>body{font-family:system-ui,sans-serif;margin:32px;color:#1d2327}table{border-collapse:collapse;width:100%}th,td{border:1px solid #dcdcde;padding:8px;text-align:left}th{background:#f6f7f7}small{color:#646970}</style></head><body><h1>Media Reference Inspector</h1><p>Read-only audit report. No supported references found does not prove a file is unused.</p><table><thead><tr><th>ID</th><th>Media</th><th>Status</th><th>References</th><th>Reference types</th><th>File health</th></tr></thead><tbody>' + body + '</tbody></table></body></html>';
		var blob = new Blob([html], { type: 'text/html;charset=utf-8' });
		var link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'media-reference-inspector-report.html'; document.body.appendChild(link); link.click(); URL.revokeObjectURL(link.href); link.remove();
	}


	function exportJson() {
		if (!results.length) { return; }
		var report = {
			tool: 'Media Reference Inspector',
			version: config.version || '',
			generatedAt: new Date().toISOString(),
			advisory: 'No supported references found does not prove that a file is unused.',
			results: results
		};
		var blob = new Blob([JSON.stringify(report, null, 2)], { type: 'application/json;charset=utf-8' });
		var link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.download = 'media-reference-inspector-report.json';
		document.body.appendChild(link);
		link.click();
		URL.revokeObjectURL(link.href);
		link.remove();
	}

	function exportCsv() {
		if (!results.length) {
			return;
		}

		var rows = [
			['Media ID', 'Title', 'Filename', 'Media URL', 'MIME type', 'File size (bytes)', 'Uploaded date', 'Status', 'Reference count', 'Reference types']
		];
		results.forEach(function (item) {
			rows.push([
				item.id,
				item.title || '',
				item.filename || '',
				item.url || '',
				item.mimeType || '',
				item.fileSize || 0,
				item.uploadedDate || '',
				makeStatusLabel(item.status),
				item.referenceCount || 0,
				Array.isArray(item.referenceTypes) ? item.referenceTypes.join('; ') : ''
			]);
		});

		var csv = rows.map(function (row) {
			return row.map(csvEscape).join(',');
		}).join('\r\n');
		var blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
		var link = document.createElement('a');
		link.href = URL.createObjectURL(blob);
		link.download = strings.csvFilename || 'media-reference-inspector-report.csv';
		document.body.appendChild(link);
		link.click();
		URL.revokeObjectURL(link.href);
		link.remove();
	}

	startButton.addEventListener('click', function () {
		if (strings.confirmStart && !window.confirm(strings.confirmStart)) {
			return;
		}

		resetView();
		setRunning(true);

		request({
			action: 'mediarefinspector_get_bulk_ids',
			search: searchInput.value || '',
			media_type: typeInput.value || '',
			age: ageInput ? (ageInput.value || '0') : '0',
			limit: limitInput.value || '100',
			selected_ids: selectedIdsInput ? (selectedIdsInput.value || '') : ''
		}).then(function (response) {
			if (!response || !response.success || !response.data || !Array.isArray(response.data.ids)) {
				throw new Error(strings.failed || 'Bulk scan failed.');
			}

			var ids = response.data.ids;
			if (!ids.length) {
				setRunning(false);
				progressStatus.textContent = strings.noItems || 'No media items matched these filters.';
				progressCount.textContent = '0 / 0';
				emptyState.hidden = false;
				return;
			}

			updateProgress(0, ids.length);
			return runQueue(ids, 0);
		}).catch(function () {
			setRunning(false);
			progressStatus.textContent = strings.failed || 'The bulk scan could not be completed. Please try again.';
			emptyState.hidden = false;
		});
	});

	stopButton.addEventListener('click', function () {
		stopped = true;
		stopButton.disabled = true;
	});

	resultFilter.addEventListener('change', applyResultFilter);
	if (resultSort) { resultSort.addEventListener('change', applyResultFilter); }
	if (sourceFilter) { sourceFilter.addEventListener('change', applyResultFilter); }
	if (healthFilter) { healthFilter.addEventListener('change', applyResultFilter); }
	exportButton.addEventListener('click', exportCsv);
	if (exportHtmlButton) { exportHtmlButton.addEventListener('click', exportHtml); }
	if (exportJsonButton) { exportJsonButton.addEventListener('click', exportJson); }
}());
