/**
 * Renders client/staff/company custom fields on add/edit forms (standalone, not lead-board).
 */
(function () {
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function safeDomIdPart(s) {
    return String(s).replace(/[^a-zA-Z0-9_-]/g, '_');
  }

  function fetchJson(url) {
    return fetch(url).then(async (r) => {
      const text = await r.text();
      try {
        return JSON.parse(text);
      } catch (e) {
        return { status: 'error', error: text || r.statusText || 'Request failed' };
      }
    }).catch((err) => {
      return { status: 'error', error: 'Network error: ' + (err.message || 'Unknown error') };
    });
  }

  /**
   * @param {object} [fieldLayout] If fieldLayout.bootstrapTwoColumn is true, wrap fields in Bootstrap
   *   columns (e.g. col-md-6) inside an existing .row — matches company modal / billing tab layout.
   */
  function renderFields(container, fields, t, fieldLayout) {
    if (!container) return;
    if (!fields.length) {
      container.innerHTML = '';
      return;
    }

    const lay = fieldLayout && typeof fieldLayout === 'object' ? fieldLayout : {};
    const useCols = !!lay.bootstrapTwoColumn;
    const colHalf = lay.colHalf || 'col-12 col-md-6';
    const colFull = lay.colFull || 'col-12';

    let html = '';
    fields.forEach((field) => {
      const fieldId = field.id;
      const fieldType = field.field_type;
      const label = escapeHtml(field.label);
      const description = field.description ? escapeHtml(field.description) : '';
      const isRequired = Number(field.is_required) === 1;
      const requiredAttr = isRequired ? 'required' : '';
      const requiredStar = isRequired ? ' <span class="text-danger">*</span>' : '';
      const listItems = field.list_items || [];

      const reqAttr = isRequired ? ' data-cf-required="1"' : '';
      const isWideField = fieldType === 'multiple_select';
      if (useCols) {
        html += '<div class="' + (isWideField ? colFull : colHalf) + '">';
        html += '<div class="form-group mb-3"' + reqAttr + '>';
      } else {
        html += '<div class="form-group full-grid mb-3"' + reqAttr + '>';
      }
      if (fieldType !== 'checkbox') {
        html += '<label>' + label + requiredStar + '</label>';
      }

      if (fieldType === 'text' || fieldType === 'tel' || fieldType === 'link') {
        const inputType = fieldType === 'tel' ? 'tel' : (fieldType === 'link' ? 'url' : 'text');
        html += '<input type="' + inputType + '" class="form-control" name="custom_fields[' + fieldId + ']" data-custom-field-id="' + fieldId + '" data-field-type="' + fieldType + '" ' + requiredAttr + '>';
      } else if (fieldType === 'number') {
        html += '<input type="number" step="0.01" class="form-control" name="custom_fields[' + fieldId + ']" data-custom-field-id="' + fieldId + '" data-field-type="' + fieldType + '" ' + requiredAttr + '>';
      } else if (fieldType === 'date') {
        html += '<input type="date" class="form-control" name="custom_fields[' + fieldId + ']" data-custom-field-id="' + fieldId + '" data-field-type="' + fieldType + '" ' + requiredAttr + '>';
      } else if (fieldType === 'checkbox') {
        html += '<div class="form-check upcf-checkbox-row">';
        html += '<label class="upcf-checkbox-title" for="upcf_' + fieldId + '">' + label + requiredStar + '</label>';
        html += '<input type="hidden" name="custom_fields[' + fieldId + ']" value="0" />';
        html += '<input type="checkbox" class="form-check-input upcf-checkbox-input" name="custom_fields[' + fieldId + ']" value="1" data-custom-field-id="' + fieldId + '" data-field-type="' + fieldType + '" id="upcf_' + fieldId + '"' + (isRequired ? ' required' : '') + '>';
        html += '</div>';
      } else if (fieldType === 'single_select') {
        html += '<select class="form-control" name="custom_fields[' + fieldId + ']" data-custom-field-id="' + fieldId + '" data-field-type="' + fieldType + '" ' + requiredAttr + '>';
        html += '<option value="">' + escapeHtml(t.select || 'Select') + '</option>';
        listItems.forEach((item) => {
          html += '<option value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</option>';
        });
        html += '</select>';
      } else if (fieldType === 'multiple_select') {
        html += '<div class="js-upcf-multiselect-wrap upcf-multiselect-wrap">';
        listItems.forEach((item) => {
          const sid = safeDomIdPart(item);
          html += '<div class="form-check upcf-multiselect-item">';
          html += '<input type="checkbox" class="form-check-input upcf-multiselect-input" name="custom_fields[' + fieldId + '][]" data-custom-field-id="' + fieldId + '" data-field-type="' + fieldType + '" value="' + escapeHtml(item) + '" id="upcf_' + fieldId + '_' + sid + '">';
          html += '<label class="form-check-label upcf-multiselect-label" for="upcf_' + fieldId + '_' + sid + '">' + escapeHtml(item) + '</label>';
          html += '</div>';
        });
        html += '</div>';
      }

      if (description) {
        html += '<small class="form-text text-muted mb-3">' + description + '</small>';
      }
      if (isRequired) {
        html += '<small class="form-text text-danger d-none js-upcf-required-msg">' + escapeHtml(t.fieldRequired || 'This field is required.') + '</small>';
      }

      html += useCols ? '</div></div>' : '</div>';
    });

    container.innerHTML = html;
  }

  function setCustomFieldsSectionVisible(container, cfg, visible) {
    const sel = cfg.sectionSelector || '#userProfileCustomFieldsSection';
    const section = qs(sel) || (container && container.closest ? container.closest('.js-user-profile-cf-section') : null);
    if (!section) return;
    section.classList.toggle('d-none', !visible);
    section.setAttribute('aria-hidden', visible ? 'false' : 'true');
  }

  function hydrate(container, initialValues) {
    if (!container || !initialValues || !initialValues.length) return;
    initialValues.forEach((cf) => {
      const id = cf.id;
      const ft = cf.field_type;
      const val = cf.value;

      if (ft === 'checkbox') {
        const cb = container.querySelector('#upcf_' + id);
        if (cb) cb.checked = (val == '1' || val === true || val === 1);
      } else if (ft === 'multiple_select') {
        const vals = Array.isArray(val) ? val : [];
        container.querySelectorAll('[data-custom-field-id="' + id + '"]').forEach((el) => {
          if (el.type === 'checkbox') el.checked = vals.indexOf(el.value) !== -1;
        });
      } else {
        const sel = container.querySelector('select[name="custom_fields[' + id + ']"]');
        if (sel) {
          sel.value = val != null ? String(val) : '';
          return;
        }
        const input = container.querySelector('input[name="custom_fields[' + id + ']"]');
        if (input && input.name.indexOf('[]') === -1) {
          input.value = val != null ? String(val) : '';
        }
      }
    });
  }

  /**
   * @param {Element|string} containerOrSelector Root element for field markup
   * @param {object} opts entityType, listUrl?, i18n?, initialValues?, sectionSelector?, manageSectionVisibility?, fieldLayout?
   * @returns {Promise<void>}
   */
  function loadProfileStyleCustomFields(containerOrSelector, opts) {
    const optsObj = opts || {};
    const container = typeof containerOrSelector === 'string'
      ? qs(containerOrSelector)
      : containerOrSelector;
    if (!container) {
      return Promise.resolve();
    }

    const entityType = optsObj.entityType || 'client';
    const listUrl = optsObj.listUrl || '../includes/custom-fields/list.php';
    const t = optsObj.i18n || {};
    const initialValues = optsObj.initialValues;
    const manageSection = optsObj.manageSectionVisibility !== false;
    const cfg = { sectionSelector: optsObj.sectionSelector };
    const fieldLayout = optsObj.fieldLayout;

    const url = listUrl + (listUrl.indexOf('?') >= 0 ? '&' : '?') + 'entity_type=' + encodeURIComponent(entityType);

    return fetchJson(url).then((data) => {
      const fields = (data.status === 'ok' && Array.isArray(data.data)) ? data.data : [];
      if (manageSection) {
        setCustomFieldsSectionVisible(container, cfg, fields.length > 0);
      }
      if (!fields.length) {
        container.innerHTML = '';
        return;
      }
      renderFields(container, fields, t, fieldLayout);
      if (initialValues && initialValues.length) {
        hydrate(container, initialValues);
      }
    });
  }

  window.loadProfileStyleCustomFields = loadProfileStyleCustomFields;

  function init() {
    const cfg = window.USER_PROFILE_CF_CONFIG || {};
    const container = qs(cfg.containerSelector || '#userProfileCustomFieldsContainer');
    if (!container) return;

    loadProfileStyleCustomFields(container, {
      entityType: cfg.entityType || 'client',
      listUrl: cfg.listUrl,
      i18n: cfg.i18n,
      initialValues: cfg.initialValues,
      sectionSelector: cfg.sectionSelector,
      manageSectionVisibility: true
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
