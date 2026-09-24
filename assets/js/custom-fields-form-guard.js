/**
 * Blocks form submit when required custom fields (in #userProfileCustomFieldsContainer) are empty.
 * Attach data-custom-fields-guard="1" on the form. Loads after user-profile-custom-fields-form.js.
 */
(function () {
  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function setInvalid(el, isInvalid) {
    if (!el) return;
    el.classList.toggle('is-invalid', !!isInvalid);
  }

  function setMultiselectWrapInvalid(wrap, isInvalid) {
    if (!wrap) return;
    wrap.classList.toggle('border-danger', !!isInvalid);
  }

  function validateRequiredCustomFields(form, showErrors) {
    const container = form && form.querySelector('#userProfileCustomFieldsContainer');
    if (!container) return true;

    const groups = container.querySelectorAll('.form-group[data-cf-required="1"]');
    if (!groups.length) return true;

    let allValid = true;

    groups.forEach(function (group) {
      const ftEl = group.querySelector('[data-field-type]');
      const fieldType = ftEl ? String(ftEl.getAttribute('data-field-type') || '') : '';
      const errMsg = group.querySelector('.js-upcf-required-msg');

      let valid = true;

      if (fieldType === 'multiple_select') {
        const anyChecked = !!group.querySelector('input.form-check-input[type="checkbox"]:checked');
        valid = anyChecked;
        const wrap = group.querySelector('.js-upcf-multiselect-wrap');
        if (showErrors) {
          setMultiselectWrapInvalid(wrap, !valid);
        } else if (wrap) {
          wrap.classList.remove('border-danger');
        }
      } else if (fieldType === 'checkbox') {
        const cb = group.querySelector('input.form-check-input[type="checkbox"][data-custom-field-id]');
        valid = !!(cb && cb.checked);
        if (showErrors) {
          setInvalid(cb, !valid);
        } else if (cb) {
          setInvalid(cb, false);
        }
      } else {
        const sel = group.querySelector('select[name^="custom_fields"]');
        const inp = group.querySelector('input.form-control[name^="custom_fields"]');
        const control = sel || inp;
        if (control) {
          const v = String(control.value != null ? control.value : '').trim();
          valid = v.length > 0;
          if (showErrors) {
            setInvalid(control, !valid);
          } else {
            setInvalid(control, false);
          }
        } else {
          valid = false;
        }
      }

      if (!valid) allValid = false;

      if (errMsg) {
        if (showErrors) {
          errMsg.classList.toggle('d-none', valid);
        } else {
          errMsg.classList.add('d-none');
        }
      }
    });

    return allValid;
  }

  // Optional shared API so other submit handlers can gate submits consistently.
  window.validateRequiredCustomFieldsForForm = function (form, showErrors) {
    return validateRequiredCustomFields(form, !!showErrors);
  };

  function bindForm(form) {
    form.addEventListener('submit', function (e) {
      if (!validateRequiredCustomFields(form, true)) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        const bad = form.querySelector('#userProfileCustomFieldsContainer .is-invalid, #userProfileCustomFieldsContainer .border-danger');
        if (bad && typeof bad.scrollIntoView === 'function') {
          bad.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
    });
    form.addEventListener('input', function () {
      validateRequiredCustomFields(form, true);
    });
    form.addEventListener('change', function () {
      validateRequiredCustomFields(form, true);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-custom-fields-guard="1"]').forEach(bindForm);
  });
})();
