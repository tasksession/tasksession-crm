(function () {
  'use strict';
  const cfg = window.userImportConfig || {};
  const importUrl = cfg.importUrl || 'import.php';
  const jobStatusUrl = cfg.jobStatusUrl || '';
  const targetUserType = cfg.targetUserType || 'client';
  const userFields = cfg.userFields || {};
  const skipTargetUserType = cfg.skipTargetUserType === true;
  const previewColumns = Array.isArray(cfg.previewColumns) && cfg.previewColumns.length
    ? cfg.previewColumns
    : [
        { key: 'email', label: 'Email' },
        { key: 'firstName', label: 'First Name' },
        { key: 'phone', label: 'Phone' },
        { key: 'note', label: 'Note' }
      ];
  const mapDuplicateWarnTpl = cfg.mapDuplicateWarn || 'Field already mapped with {column}';
  const mapDuplicateBlockMsg = cfg.mapDuplicateBlock || 'Resolve duplicate field mappings before continuing.';
  const mapFieldSearchPlaceholder = cfg.mapFieldSearchPlaceholder || 'Search fields…';
  const previewLoadingLabel = cfg.previewLoadingLabel || 'Loading preview…';
  const importProgressStarting = cfg.importProgressStarting || 'Starting import…';
  const importProgressProcessing = cfg.importProgressProcessing || 'Processing rows…';
  const importProgressQueued = cfg.importProgressQueued || 'Import queued…';

  let currentStep = 1;
  let importProgressPanel = null;
  let importPollTimer = null;
  let csvFile = null;
  let csvHeaders = [];
  let csvSampleRow = [];
  let fieldMapping = {};
  let openMappingCombobox = null;

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  function formatImportError(err, fallback) {
    var msg = (err && err.message) ? String(err.message) : (fallback || 'Request failed');
    if (err && err.debug) {
      try {
        msg += '\n\n' + JSON.stringify(err.debug, null, 2);
      } catch (e) {
        msg += '\n\n' + String(err.debug);
      }
    }
    if (err && err.raw) {
      var raw = String(err.raw).trim();
      if (raw && raw.indexOf('{') !== 0) {
        msg += '\n\n--- Server response ---\n' + raw.slice(0, 2000);
      }
    }
    if (cfg.importDebugUrl) {
      msg += '\n\nDiagnostics: ' + cfg.importDebugUrl;
    }
    return msg;
  }

  function parseImportResponse(r) {
    return r.text().then(function (text) {
      var data = null;
      try {
        data = text ? JSON.parse(text) : null;
      } catch (parseErr) {
        var bad = new Error('Server returned invalid JSON (HTTP ' + r.status + ')');
        bad.raw = text;
        throw bad;
      }
      if (!r.ok || !data || data.status !== 'ok') {
        var fail = new Error((data && data.error) || ('HTTP ' + r.status));
        if (data && data.debug) {
          fail.debug = data.debug;
        }
        fail.raw = text;
        throw fail;
      }
      return data;
    });
  }

  function showAlert(type, msg) {
    const el = qs('#previewAlert');
    if (!el) return;
    el.className = 'alert alert-' + (type || 'info');
    el.textContent = msg || '';
    el.style.whiteSpace = (msg && String(msg).indexOf('\n') >= 0) ? 'pre-wrap' : '';
    el.style.display = 'block';
  }
  function hideAlert() {
    const el = qs('#previewAlert');
    if (el) el.style.display = 'none';
  }

  function showMappingAlert(type, msg) {
    const el = qs('#fieldMappingAlert');
    if (!el) return;
    el.className = 'alert alert-' + (type || 'info');
    el.textContent = msg || '';
    el.style.display = 'block';
  }
  function hideMappingAlert() {
    const el = qs('#fieldMappingAlert');
    if (el) el.style.display = 'none';
  }

  var previewSkelBarSizes = ['sm', 'xl', 'md', 'lg', 'xl'];

  function createSkelBar(size) {
    var bar = document.createElement('span');
    bar.className = 'import-skel-bar import-skel-bar--' + (size || 'md');
    bar.setAttribute('aria-hidden', 'true');
    return bar;
  }

  function buildPreviewSkeletonTable() {
    var colCount = previewColumns.length || 5;
    var rowCount = 10;
    var table = document.createElement('table');
    table.className = 'import-preview-skeleton-table w-100';
    var thead = document.createElement('thead');
    var hr = document.createElement('tr');
    for (var c = 0; c < colCount; c++) {
      var th = document.createElement('th');
      th.appendChild(createSkelBar(previewSkelBarSizes[c % previewSkelBarSizes.length]));
      hr.appendChild(th);
    }
    thead.appendChild(hr);
    table.appendChild(thead);
    var tbody = document.createElement('tbody');
    for (var r = 0; r < rowCount; r++) {
      var tr = document.createElement('tr');
      for (var c2 = 0; c2 < colCount; c2++) {
        var td = document.createElement('td');
        td.appendChild(createSkelBar(previewSkelBarSizes[(r + c2) % previewSkelBarSizes.length]));
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
    }
    table.appendChild(tbody);
    return table;
  }

  function ensurePreviewStage() {
    var container = qs('#previewContainer');
    if (!container) return null;
    var stage = qs('#previewStage');
    if (!stage) {
      stage = document.createElement('div');
      stage.id = 'previewStage';
      stage.className = 'import-preview-stage';
      container.parentNode.insertBefore(stage, container);
      stage.appendChild(container);
    }
    var block = qs('#previewSkeletonBlock', stage);
    if (!block) {
      block = document.createElement('div');
      block.id = 'previewSkeletonBlock';
      block.className = 'import-preview-skeleton-block';
      stage.insertBefore(block, container);
    }
    if (!block.querySelector('.import-preview-skeleton-table')) {
      block.innerHTML = '';
      block.appendChild(buildPreviewSkeletonTable());
    }
    return { stage: stage, block: block, container: container };
  }

  function showPreviewLoading() {
    var parts = ensurePreviewStage();
    if (!parts) return;
    parts.stage.classList.add('is-loading');
    parts.block.hidden = false;
    parts.block.setAttribute('aria-hidden', 'false');
    parts.block.setAttribute('aria-busy', 'true');
    parts.container.hidden = true;
    parts.container.setAttribute('aria-hidden', 'true');
    var trh = qs('#previewTableHead');
    var trb = qs('#previewTableBody');
    var tot = qs('#previewTotalRows');
    if (trh) trh.innerHTML = '';
    if (trb) trb.innerHTML = '';
    if (tot) {
      tot.textContent = previewLoadingLabel;
      tot.classList.add('import-preview-total--loading');
    }
    hideAlert();
  }

  function hidePreviewLoading() {
    var parts = ensurePreviewStage();
    if (!parts) return;
    parts.stage.classList.remove('is-loading');
    parts.block.hidden = true;
    parts.block.setAttribute('aria-hidden', 'true');
    parts.block.removeAttribute('aria-busy');
    parts.container.hidden = false;
    parts.container.removeAttribute('aria-hidden');
    var tot = qs('#previewTotalRows');
    if (tot) tot.classList.remove('import-preview-total--loading');
  }

  function ensureImportProgressPanel() {
    if (importProgressPanel) return importProgressPanel;
    var host = qs('.step-content[data-step="4"] .card-body');
    if (!host) return null;
    var panel = document.createElement('div');
    panel.id = 'importProgressPanel';
    panel.className = 'import-progress-panel';
    panel.hidden = true;
    panel.innerHTML =
      '<div class="import-progress-header">' +
        '<span class="import-progress-title" id="importProgressTitle"></span>' +
        '<span class="import-progress-pct" id="importProgressPct">0%</span>' +
      '</div>' +
      '<div class="import-progress-track" id="importProgressTrack">' +
        '<div class="import-progress-fill" id="importProgressFill" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"></div>' +
      '</div>' +
      '<p class="import-progress-detail text-muted small mb-0" id="importProgressDetail"></p>';
    var results = qs('#importResults', host);
    if (results) host.insertBefore(panel, results);
    else host.appendChild(panel);
    importProgressPanel = panel;
    return panel;
  }

  function setWizardBusy(busy) {
    ['#wizardImportBtn', '#wizardNextBtn', '#wizardBackBtn'].forEach(function (sel) {
      var btn = qs(sel);
      if (btn) btn.disabled = !!busy;
    });
  }

  function showImportProgress(opts) {
    opts = opts || {};
    var panel = ensureImportProgressPanel();
    if (!panel) return;
    panel.hidden = false;
    var title = qs('#importProgressTitle', panel);
    var pct = qs('#importProgressPct', panel);
    var fill = qs('#importProgressFill', panel);
    var track = qs('#importProgressTrack', panel);
    var detail = qs('#importProgressDetail', panel);
    var indeterminate = !!opts.indeterminate;
    var percent = typeof opts.percent === 'number' ? Math.max(0, Math.min(100, opts.percent)) : 0;
    if (title) title.textContent = opts.title || importProgressStarting;
    if (detail) detail.textContent = opts.detail || '';
    if (fill) {
      fill.classList.toggle('is-indeterminate', indeterminate);
      if (!indeterminate) {
        fill.style.width = percent + '%';
        fill.setAttribute('aria-valuenow', String(Math.round(percent)));
      } else {
        fill.style.width = '';
        fill.setAttribute('aria-valuenow', '0');
      }
    }
    if (track) track.classList.toggle('is-active', indeterminate || percent > 0);
    if (pct) pct.textContent = indeterminate ? '…' : (Math.round(percent) + '%');
    var resultsDiv = qs('#importResults');
    if (resultsDiv) resultsDiv.style.display = 'none';
  }

  function hideImportProgress() {
    if (importProgressPanel) importProgressPanel.hidden = true;
    if (importPollTimer) {
      clearInterval(importPollTimer);
      importPollTimer = null;
    }
  }

  function finishImportProgress(msg, isError) {
    var panel = ensureImportProgressPanel();
    var fill = panel ? qs('#importProgressFill', panel) : null;
    var pct = panel ? qs('#importProgressPct', panel) : null;
    var title = panel ? qs('#importProgressTitle', panel) : null;
    var detail = panel ? qs('#importProgressDetail', panel) : null;
    if (fill) {
      fill.classList.remove('is-indeterminate');
      fill.style.width = isError ? '0%' : '100%';
      fill.setAttribute('aria-valuenow', isError ? '0' : '100');
    }
    if (pct) pct.textContent = isError ? '—' : '100%';
    if (title) title.textContent = isError ? (msg || 'Import failed') : 'Complete';
    if (detail && !isError) detail.textContent = msg || '';
    setWizardBusy(false);
  }

  /** Normalize header/label for auto-map (case, spaces, trailing punctuation, [id] suffix). */
  function normalizeMapKey(s) {
    if (s == null || s === '') return '';
    var out = String(s).toLowerCase().trim().replace(/\s+/g, ' ');
    while (/[:;.,!?]+$/.test(out)) {
      out = out.replace(/[:;.,!?]+$/, '').trim();
    }
    return out;
  }

  function stripBracketId(s) {
    return String(s).replace(/\s+\[\d+\]\s*$/, '').trim();
  }

  /** org: / organization: aliases and bare label forms for export ↔ import matching. */
  function mapKeyVariants(s) {
    var n = normalizeMapKey(stripBracketId(s));
    if (!n) return [];
    var set = Object.create(null);
    set[n] = true;
    function addAlias(fromPrefix, toPrefix) {
      if (n.indexOf(fromPrefix) === 0) {
        set[toPrefix + n.slice(fromPrefix.length)] = true;
      }
    }
    addAlias('org:', 'organization:');
    addAlias('organization:', 'org:');
    var bare = n.replace(/^(org|organization):\s*/, '');
    if (bare && bare !== n) set[bare] = true;
    if (n.indexOf('org ') === 0) {
      set['organization: ' + n.slice(4)] = true;
    }
    return Object.keys(set);
  }

  function variantsOverlap(a, b) {
    var va = mapKeyVariants(a);
    var vb = mapKeyVariants(b);
    for (var i = 0; i < va.length; i++) {
      for (var j = 0; j < vb.length; j++) {
        if (va[i] === vb[j]) return true;
      }
    }
    return false;
  }

  function resolveFieldForHeader(header, fields) {
    var keys = Object.keys(fields || {});
    for (var k = 0; k < keys.length; k++) {
      var fieldName = keys[k];
      var label = fields[fieldName] || fieldName;
      if (variantsOverlap(header, fieldName) || variantsOverlap(header, label)) {
        return fieldName;
      }
    }
    return null;
  }

  function closeMappingCombobox(wrap) {
    if (!wrap) return;
    wrap.classList.remove('is-open');
    var panel = wrap.querySelector('.field-mapping-combobox-panel');
    var trigger = wrap.querySelector('.field-mapping-combobox-trigger');
    if (panel) {
      panel.hidden = true;
      panel.style.position = '';
      panel.style.top = '';
      panel.style.left = '';
      panel.style.width = '';
      panel.style.maxHeight = '';
      panel.style.zIndex = '';
    }
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
    if (openMappingCombobox === wrap) openMappingCombobox = null;
  }

  function positionMappingPanel(trigger, panel) {
    var r = trigger.getBoundingClientRect();
    var spaceBelow = window.innerHeight - r.bottom - 12;
    var maxH = Math.min(320, Math.max(160, spaceBelow));
    panel.style.position = 'fixed';
    panel.style.top = (r.bottom + 4) + 'px';
    panel.style.left = r.left + 'px';
    panel.style.width = Math.max(r.width, 240) + 'px';
    panel.style.maxHeight = maxH + 'px';
    panel.style.zIndex = '10050';
  }

  function attachFieldMappingCombobox(wrap, select) {
    select.classList.add('field-mapping-target-select--hidden');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'field-mapping-combobox-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    var panel = document.createElement('div');
    panel.className = 'field-mapping-combobox-panel';
    panel.hidden = true;
    panel.setAttribute('role', 'listbox');

    var searchWrap = document.createElement('div');
    searchWrap.className = 'field-mapping-combobox-search-wrap';

    var search = document.createElement('input');
    search.type = 'search';
    search.className = 'field-mapping-combobox-search';
    search.placeholder = mapFieldSearchPlaceholder;
    search.autocomplete = 'off';
    search.setAttribute('aria-label', mapFieldSearchPlaceholder);

    var list = document.createElement('ul');
    list.className = 'field-mapping-combobox-list';

    Array.from(select.options).forEach(function (opt) {
      var li = document.createElement('li');
      li.className = 'field-mapping-combobox-option';
      li.dataset.value = opt.value;
      li.textContent = opt.textContent;
      li.setAttribute('role', 'option');
      if (opt.selected) li.classList.add('is-selected');
      list.appendChild(li);
    });

    function updateTriggerLabel() {
      var opt = select.options[select.selectedIndex];
      trigger.textContent = opt ? opt.textContent : '-- Skip --';
    }

    function filterList(query) {
      var q = (query || '').toLowerCase().trim();
      qsa('.field-mapping-combobox-option', list).forEach(function (li) {
        if (li.dataset.value === '') {
          li.classList.remove('is-hidden');
          return;
        }
        if (!q) {
          li.classList.remove('is-hidden');
          return;
        }
        if (li.textContent.toLowerCase().indexOf(q) !== -1) {
          li.classList.remove('is-hidden');
        } else {
          li.classList.add('is-hidden');
        }
      });
    }

    function chooseValue(value) {
      select.value = value;
      qsa('.field-mapping-combobox-option', list).forEach(function (li) {
        li.classList.toggle('is-selected', li.dataset.value === value);
      });
      updateTriggerLabel();
      closeMappingCombobox(wrap);
      select.dispatchEvent(new Event('change', { bubbles: true }));
    }

    searchWrap.appendChild(search);
    panel.appendChild(searchWrap);
    panel.appendChild(list);
    panel.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      if (wrap.classList.contains('is-open')) {
        closeMappingCombobox(wrap);
        return;
      }
      if (openMappingCombobox) closeMappingCombobox(openMappingCombobox);
      openMappingCombobox = wrap;
      wrap.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      panel.hidden = false;
      positionMappingPanel(trigger, panel);
      search.value = '';
      filterList('');
      search.focus();
    });

    search.addEventListener('input', function () {
      filterList(search.value);
    });
    search.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        closeMappingCombobox(wrap);
        trigger.focus();
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        var first = list.querySelector('.field-mapping-combobox-option:not(.is-hidden)');
        if (first) chooseValue(first.dataset.value);
      }
    });

    list.addEventListener('click', function (e) {
      var li = e.target.closest('.field-mapping-combobox-option');
      if (!li || li.classList.contains('is-hidden')) return;
      chooseValue(li.dataset.value);
    });

    wrap.classList.add('field-mapping-combobox');
    wrap.appendChild(trigger);
    wrap.appendChild(select);
    wrap.appendChild(panel);
    updateTriggerLabel();
  }

  function repositionOpenMappingCombobox() {
    if (!openMappingCombobox) return;
    var trigger = openMappingCombobox.querySelector('.field-mapping-combobox-trigger');
    var panel = openMappingCombobox.querySelector('.field-mapping-combobox-panel');
    if (trigger && panel && !panel.hidden) positionMappingPanel(trigger, panel);
  }

  function parseCSVLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;
    for (let i = 0; i < line.length; i++) {
      const char = line[i];
      if (char === '"') inQuotes = !inQuotes;
      else if (char === ',' && !inQuotes) { result.push(current.trim()); current = ''; }
      else current += char;
    }
    result.push(current.trim());
    return result;
  }

  function parseCSV(file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      const text = e.target.result;
      const lines = text.split(/\r?\n/).filter(function (l) { return l.trim() !== ''; });
      if (lines.length < 2) {
        showAlert('danger', 'CSV needs a header and at least one row');
        return;
      }
      if (lines[0].charCodeAt(0) === 0xFEFF) {
        lines[0] = lines[0].slice(1);
      }
      csvHeaders = parseCSVLine(lines[0]);
      csvSampleRow = lines.length > 1 ? parseCSVLine(lines[1]) : [];
      fieldMapping = {};
      csvHeaders.forEach(function (header, index) {
        var matched = resolveFieldForHeader(header, userFields);
        if (matched) {
          fieldMapping[matched] = index;
        }
      });
      hideMappingAlert();
      updateFieldMappingUI();
    };
    reader.readAsText(file);
  }

  function syncFieldMappingFromDom() {
    fieldMapping = {};
    qsa('#fieldMappingBody .field-mapping-target-select').forEach(function (sel) {
      if (!sel.value) return;
      var colIdx = parseInt(sel.dataset.columnIndex, 10);
      if (Number.isNaN(colIdx) || colIdx < 0) return;
      if (!Object.prototype.hasOwnProperty.call(fieldMapping, sel.value)) {
        fieldMapping[sel.value] = colIdx;
      }
    });
  }

  /** Same CRM field selected on more than one CSV column: warn under later rows; first row wins in field_mapping. */
  function refreshDuplicateMappingWarnings() {
    var firstHeaderByDest = Object.create(null);
    qsa('#fieldMappingBody tr').forEach(function (tr) {
      var sel = tr.querySelector('.field-mapping-target-select');
      var warn = tr.querySelector('.field-mapping-duplicate-msg');
      if (!sel || !warn) return;
      var dest = sel.value;
      if (!dest) {
        warn.style.display = 'none';
        warn.textContent = '';
        return;
      }
      var srcHeader = tr.cells[1] ? tr.cells[1].textContent.trim() : '';
      if (!firstHeaderByDest[dest]) {
        firstHeaderByDest[dest] = srcHeader;
        warn.style.display = 'none';
        warn.textContent = '';
      } else {
        warn.textContent = mapDuplicateWarnTpl.split('{column}').join(firstHeaderByDest[dest]);
        warn.style.display = 'block';
      }
    });
  }

  function hasDuplicateFieldMapping() {
    var seen = Object.create(null);
    var dup = false;
    qsa('#fieldMappingBody tr').forEach(function (tr) {
      var sel = tr.querySelector('.field-mapping-target-select');
      if (!sel || !sel.value) return;
      if (seen[sel.value]) dup = true;
      else seen[sel.value] = true;
    });
    return dup;
  }

  function updateFieldMappingUI() {
    const tbody = qs('#fieldMappingBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    const keys = Object.keys(userFields);
    csvHeaders.forEach(function (header, index) {
      const tr = document.createElement('tr');
      const tdNo = document.createElement('td');
      tdNo.className = 'field-mapping-col--no text-muted';
      tdNo.textContent = String(index + 1);
      tr.appendChild(tdNo);
      tr.appendChild(document.createElement('td')).textContent = header;
      const tdS = document.createElement('td');
      tdS.textContent = csvSampleRow[index] || '';
      tdS.className = 'text-muted';
      tr.appendChild(tdS);
      const tdSel = document.createElement('td');
      tdSel.className = 'field-mapping-col--select';
      const wrap = document.createElement('div');
      wrap.className = 'field-mapping-select-wrap';
      const select = document.createElement('select');
      select.className = 'form-control field-mapping-target-select';
      select.dataset.columnIndex = String(index);
      const skip = document.createElement('option');
      skip.value = '';
      skip.textContent = '-- Skip --';
      select.appendChild(skip);
      keys.forEach(function (fn) {
        const o = document.createElement('option');
        o.value = fn;
        o.textContent = userFields[fn] || fn;
        if (fieldMapping[fn] === index) o.selected = true;
        select.appendChild(o);
      });
      select.addEventListener('change', function () {
        syncFieldMappingFromDom();
        refreshDuplicateMappingWarnings();
        if (hasDuplicateFieldMapping()) {
          showMappingAlert('warning', mapDuplicateBlockMsg);
        } else {
          hideMappingAlert();
        }
      });
      attachFieldMappingCombobox(wrap, select);
      tdSel.appendChild(wrap);
      var warn = document.createElement('div');
      warn.className = 'field-mapping-duplicate-msg text-danger small mt-1';
      warn.style.display = 'none';
      warn.setAttribute('role', 'alert');
      tdSel.appendChild(warn);
      tr.appendChild(tdSel);
      tbody.appendChild(tr);
    });
    refreshDuplicateMappingWarnings();
    if (hasDuplicateFieldMapping()) {
      showMappingAlert('warning', mapDuplicateBlockMsg);
    } else {
      hideMappingAlert();
    }
  }

  function goToStep(step) {
    if (step < 1 || step > 4) return;
    qsa('.step-content').forEach(function (el) { el.classList.remove('active'); });
    qsa('.step-list__step').forEach(function (el) {
      el.classList.remove('step-list__step--done');
      el.classList.remove('step-list__step--highlight');
    });
    const sc = qs('.step-content[data-step="' + step + '"]');
    const ind = qs('.step-list__step[data-step="' + step + '"]');
    if (sc) sc.classList.add('active');
    if (ind) ind.classList.add('step-list__step--highlight');
    for (let i = 1; i < step; i++) {
      const p = qs('.step-list__step[data-step="' + i + '"]');
      if (p) { p.classList.remove('step-list__step--highlight'); p.classList.add('step-list__step--done'); }
    }
    currentStep = step;
    const back = qs('#wizardBackBtn');
    const next = qs('#wizardNextBtn');
    const imp = qs('#wizardImportBtn');
    if (back) back.style.display = step > 1 ? 'inline-block' : 'none';
    if (next) next.style.display = step < 4 ? 'inline-block' : 'none';
    if (imp) imp.style.display = step === 4 ? 'inline-block' : 'none';
    syncClientOverwriteHint();
  }

  /** Show org re-import hint only when "Update existing same type" is selected (client import). */
  function syncClientOverwriteHint() {
    var sel = qs('#duplicate_strategy_select');
    var box = qs('#clientImportOverwriteHint');
    if (!sel || !box) return;
    box.style.display = sel.value === 'overwrite' ? 'block' : 'none';
  }

  function buildFormData(extra) {
    syncFieldMappingFromDom();
    refreshDuplicateMappingWarnings();
    const fd = new FormData();
    if (csvFile) fd.append('csv_file', csvFile);
    fd.append('csrf_token', window.csrfToken || (qs('meta[name="csrf-token"]') && qs('meta[name="csrf-token"]').getAttribute('content')) || '');
    if (!skipTargetUserType) {
      fd.append('target_user_type', targetUserType);
    }
    fd.append('field_mapping', JSON.stringify(fieldMapping));
    const form = qs('#importOptionsForm');
    if (form) {
      const o = new FormData(form);
      o.forEach(function (v, k) { fd.append(k, v); });
    }
    if (extra) Object.keys(extra).forEach(function (k) { fd.append(k, extra[k]); });
    return fd;
  }

  function fetchPreview() {
    showPreviewLoading();
    const fd = buildFormData({ preview: '1' });
    fetch(importUrl, { method: 'POST', body: fd })
      .then(parseImportResponse)
      .then(function (data) {
        hidePreviewLoading();
        const d = data.data || {};
        const trh = qs('#previewTableHead');
        const trb = qs('#previewTableBody');
        const tot = qs('#previewTotalRows');
        if (tot) tot.textContent = (d.total_rows || 0) + ' rows';
        if (trh) {
          trh.innerHTML = '';
          const hr = document.createElement('tr');
          previewColumns.forEach(function (col) {
            const th = document.createElement('th');
            th.textContent = col.label || col.key || '';
            if (col.key === '__rowNo__') {
              th.className = 'preview-col--no text-muted';
              th.scope = 'col';
            }
            hr.appendChild(th);
          });
          trh.appendChild(hr);
        }
        if (trb) {
          trb.innerHTML = '';
          (d.preview || []).forEach(function (row, ri) {
            const tr = document.createElement('tr');
            previewColumns.forEach(function (col) {
              const td = document.createElement('td');
              var k = col.key;
              if (k === '__rowNo__') {
                td.textContent = (row && row.row != null && row.row !== '') ? String(row.row) : String(ri + 1);
                td.className = 'preview-col--no text-muted text-center';
              } else {
                td.textContent = (row && row[k] !== undefined && row[k] !== null) ? String(row[k]) : '';
              }
              tr.appendChild(td);
            });
            trb.appendChild(tr);
          });
        }
      })
      .catch(function (err) {
        hidePreviewLoading();
        showAlert('danger', formatImportError(err, 'Network error'));
      });
  }

  function pollJob(jobId) {
    const resultsText = qs('#importResultsText');
    const resultsDiv = qs('#importResults');
    let n = 0;
    const tick = function () {
      n++;
      if (!jobStatusUrl) return;
      fetch(jobStatusUrl + '?job_id=' + encodeURIComponent(String(jobId)))
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.ok || !j.job) return;
          const st = j.job;
          var total = Math.max(0, Number(st.total_rows) || 0);
          var processed = Math.max(0, Number(st.processed_rows) || 0);
          var pct = total > 0 ? Math.min(99, (processed / total) * 100) : 5;
          if (st.status === 'completed') pct = 100;
          var detail = total > 0
            ? (processed + ' / ' + total + ' rows')
            : (st.status || 'processing');
          if (st.status === 'processing' || st.status === 'queued' || st.status === 'running') {
            detail += ' · inserted ' + (st.success_rows || 0) + ', skipped ' + (st.skipped_rows || 0);
          }
          showImportProgress({
            title: importProgressProcessing,
            detail: detail,
            percent: pct,
            indeterminate: total <= 0 && st.status !== 'completed'
          });
          if (resultsDiv) resultsDiv.style.display = 'block';
          if (st.status === 'completed' || st.status === 'failed') {
            if (importPollTimer) clearInterval(importPollTimer);
            importPollTimer = null;
            const dry = Number(st.dry_run) === 1;
            const msg = st.status === 'completed'
              ? (dry
                ? ('Dry run finished (no database writes). Would import ' + st.success_rows + ', skipped ' + st.skipped_rows + ', failed ' + st.failed_rows + '.')
                : ('Done. Inserted ' + st.success_rows + ', updated ' + st.updated_rows + ', skipped ' + st.skipped_rows + ', failed ' + st.failed_rows + '.'))
              : ('Failed: ' + (st.last_error || 'error'));
            finishImportProgress(msg, st.status === 'failed');
            if (resultsText) resultsText.textContent = msg;
          } else if (n > 400) {
            if (importPollTimer) clearInterval(importPollTimer);
            importPollTimer = null;
            finishImportProgress('Still processing. Check Import history later.', false);
            if (resultsText) resultsText.textContent = 'Still processing. Check Import history later.';
            setWizardBusy(false);
          }
        })
        .catch(function () {});
    };
    if (importPollTimer) clearInterval(importPollTimer);
    importPollTimer = setInterval(tick, 800);
    tick();
  }

  function runImport() {
    const fd = buildFormData({ preview: '0' });
    if (hasDuplicateFieldMapping()) {
      showMappingAlert('danger', mapDuplicateBlockMsg);
      goToStep(2);
      return;
    }
    const dryCb = qs('#importOptionsForm input[name="dry_run"]');
    if (dryCb && dryCb.checked) {
      fd.append('simulate', '1');
    }
    const resultsDiv = qs('#importResults');
    const resultsText = qs('#importResultsText');
    setWizardBusy(true);
    showImportProgress({
      title: importProgressStarting,
      detail: importProgressProcessing,
      indeterminate: true
    });
    if (resultsText) resultsText.textContent = '';
    fetch(importUrl, { method: 'POST', body: fd })
      .then(parseImportResponse)
      .then(function (data) {
        if (data.data && data.data.async && data.data.job_id) {
          if (resultsText) resultsText.textContent = data.data.message || importProgressQueued;
          if (resultsDiv) resultsDiv.style.display = 'block';
          showImportProgress({
            title: importProgressQueued,
            detail: data.data.message || '',
            percent: 2,
            indeterminate: false
          });
          pollJob(data.data.job_id);
          return;
        }
        const d = data.data || {};
        const sim = Number(d.simulate) === 1;
        let msg = sim
          ? ('Dry run (no saves): would import ' + (d.would_insert != null ? d.would_insert : 0) +
            ', skipped ' + (d.skipped || 0) + '.')
          : ('Inserted ' + (d.inserted || 0) + ', updated ' + (d.updated || 0) +
            ', skipped ' + (d.skipped || 0) + '.');
        const errs = d.errors;
        if (Array.isArray(errs) && errs.length) {
          msg += ' ' + errs.join(' ');
        }
        finishImportProgress(msg, false);
        if (resultsText) resultsText.textContent = msg;
        if (resultsDiv) resultsDiv.style.display = 'block';
      })
      .catch(function (err) {
        var errMsg = formatImportError(err, 'Network error');
        finishImportProgress(errMsg, true);
        if (resultsText) resultsText.textContent = errMsg;
        if (resultsDiv) resultsDiv.style.display = 'block';
        showAlert('danger', errMsg);
      });
  }

  function init() {
    document.addEventListener('click', function (e) {
      if (!openMappingCombobox) return;
      if (openMappingCombobox.contains(e.target)) return;
      closeMappingCombobox(openMappingCombobox);
    });
    window.addEventListener('resize', repositionOpenMappingCombobox);
    window.addEventListener('scroll', repositionOpenMappingCombobox, true);
    if (qs('#previewStage') || qs('#previewContainer')) ensurePreviewStage();

    const dz = qs('#uploadDropzone');
    const inp = qs('#csvFileInput');
    if (dz && inp) {
      dz.addEventListener('click', function () { inp.click(); });
      dz.addEventListener('dragover', function (e) { e.preventDefault(); dz.classList.add('dragover'); });
      dz.addEventListener('dragleave', function () { dz.classList.remove('dragover'); });
      dz.addEventListener('drop', function (e) {
        e.preventDefault();
        dz.classList.remove('dragover');
        if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
      });
      inp.addEventListener('change', function () {
        if (inp.files.length) handleFile(inp.files[0]);
      });
    }
    qs('#removeFileBtn') && qs('#removeFileBtn').addEventListener('click', function () {
      csvFile = null;
      csvHeaders = [];
      csvSampleRow = [];
      fieldMapping = {};
      if (inp) inp.value = '';
      const fi = qs('#fileInfo');
      if (fi) fi.style.display = 'none';
      goToStep(1);
    });
    qs('#wizardNextBtn') && qs('#wizardNextBtn').addEventListener('click', function () {
      if (currentStep === 1 && !csvFile) { showAlert('danger', 'Choose a file'); return; }
      if (currentStep === 2) {
        syncFieldMappingFromDom();
        refreshDuplicateMappingWarnings();
        if (hasDuplicateFieldMapping()) {
          showMappingAlert('danger', mapDuplicateBlockMsg);
          var warns = qsa('#fieldMappingBody .field-mapping-duplicate-msg');
          for (var wi = 0; wi < warns.length; wi++) {
            if (warns[wi].style.display === 'block') {
              warns[wi].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
              break;
            }
          }
          return;
        }
        hideMappingAlert();
      }
      if (currentStep === 3) {
        syncFieldMappingFromDom();
        refreshDuplicateMappingWarnings();
        if (hasDuplicateFieldMapping()) {
          showMappingAlert('danger', mapDuplicateBlockMsg);
          goToStep(2);
          return;
        }
        showPreviewLoading();
        fetchPreview();
      }
      if (currentStep < 4) goToStep(currentStep + 1);
    });
    qs('#wizardBackBtn') && qs('#wizardBackBtn').addEventListener('click', function () {
      if (currentStep > 1) goToStep(currentStep - 1);
    });
    qs('#wizardImportBtn') && qs('#wizardImportBtn').addEventListener('click', runImport);
    var dupStrat = qs('#duplicate_strategy_select');
    if (dupStrat) dupStrat.addEventListener('change', syncClientOverwriteHint);
    syncClientOverwriteHint();
    qs('#wizardCancelBtn') && qs('#wizardCancelBtn').addEventListener('click', function () {
      if (confirm('Cancel?')) window.history.back();
    });
  }

  function handleFile(file) {
    if (!file.name.match(/\.(csv|xlsx)$/i)) {
      showAlert('danger', 'CSV or XLSX only');
      return;
    }
    csvFile = file;
    const fi = qs('#fileInfo');
    const fn = qs('.file-name', fi);
    if (fi && fn) {
      fn.textContent = file.name;
      fi.style.display = 'block';
    }
    if (file.name.toLowerCase().endsWith('.csv')) parseCSV(file);
    else showAlert('danger', 'Please use a CSV file for this wizard.');
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
