/**
 * Accounts page helpers (admin)
 * - Add Client: staff assignment dropdown + required field gating + Next button gating
 *
 * This file is intentionally written to be reusable across account pages.
 */

(function () {
  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function safeParseJson(text) {
    try {
      return JSON.parse(text);
    } catch (e) {
      return null;
    }
  }

  function setInvalid(el, isInvalid) {
    if (!el) return;
    el.classList.toggle('is-invalid', !!isInvalid);
  }

  function setButtonInvalid(btn, isInvalid) {
    if (!btn) return;
    btn.classList.toggle('is-invalid', !!isInvalid);
    btn.style.borderColor = isInvalid ? '#dc3545' : '';
  }

  function setMultiselectWrapInvalid(wrap, isInvalid) {
    if (!wrap) return;
    wrap.classList.toggle('border-danger', !!isInvalid);
  }

  /** Required profile custom fields inside #userProfileCustomFieldsContainer (async-rendered). */
  function validateUserProfileCustomFields(form, showErrors) {
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

  // ===== Add Client page =====
  function initAddClientStaffDropdown() {
    const staffMenu = document.getElementById('staffDropdownMenu');
    const staffBtn = document.getElementById('staffDropdownBtn');
    const staffBtnText = document.getElementById('staffDropdownBtnText');
    const selectedStaffInput = document.getElementById('selectedStaffInput');
    const jsonEl = document.getElementById('staffListForAssignmentJson');

    if (!staffMenu || !staffBtn || !staffBtnText || !selectedStaffInput || !jsonEl) return;

    const staffList = safeParseJson(jsonEl.value || jsonEl.textContent || '') || [];
    let selectedStaff = [];

    function renderStaffDropdown() {
      staffMenu.innerHTML = '';
      staffList.forEach((staff) => {
        const li = document.createElement('li');
        const avatarHtml = staff.image || '';
        const adminBadge =
          Number(staff.accountStatus) === 1
            ? '<span class="badge color-inprogress inprogress-bg-op ms-2 align-self-start">ADMIN</span>'
            : '';

        const isSelected = selectedStaff.some((s) => String(s.id) === String(staff.id));
        li.innerHTML = `
          <a href="#" class="dropdown-item d-flex align-items-center ${isSelected ? 'active' : ''}" data-id="${staff.id}">
            <div class="me-2">${avatarHtml}</div>
            <span>${staff.name || ''}</span>
            ${adminBadge}
          </a>
        `;
        staffMenu.appendChild(li);
      });
    }

    function dispatchSelectedChange() {
      try {
        selectedStaffInput.dispatchEvent(new Event('change', { bubbles: true }));
      } catch (e) {
        // ignore
      }
    }

    function updateSelectedStaff() {
      selectedStaffInput.value = selectedStaff.map((s) => s.id).join(',');

      if (selectedStaff.length) {
        const staffHtml = selectedStaff
          .map((s) => {
            let avatarHtml = s.image || '';
            if (avatarHtml.includes('<img')) {
              avatarHtml = avatarHtml
                .replace(/width=["']?\d+px["']?/g, 'width="24px"')
                .replace(/height=["']?\d+px["']?/g, 'height="24px"')
                .replace(/me-2/g, 'me-1');
            } else {
              avatarHtml = avatarHtml
                .replace(/width:28px;height:28px/g, 'width:24px;height:24px')
                .replace(/me-2/g, 'me-1');
            }

            const adminBadge =
              Number(s.accountStatus) === 1
                ? '<span class="badge color-inprogress inprogress-bg-op">ADMIN</span>'
                : '';

            return `
              <div class="d-inline-flex align-items-center active-user">
                <div>${avatarHtml}</div>
                <span class="ms-1">${s.name || ''}</span>
                ${adminBadge}
                <button type="button" class="btn-close btn-close-sm ms-2" aria-label="Remove"
                        data-remove-staff="${s.id}" style="font-size: 8px; padding: 1px;">×</button>
              </div>
            `;
          })
          .join('');

        staffBtnText.innerHTML = staffHtml;
      } else {
        staffBtnText.textContent = staffBtnText.dataset.placeholder || 'Select Staff members';
      }

      renderStaffDropdown();
      dispatchSelectedChange();
    }

    // Remove staff handler (no inline onclick)
    staffBtnText.addEventListener('click', function (e) {
      const btn = e.target.closest('[data-remove-staff]');
      if (!btn) return;
      e.preventDefault();
      const staffId = btn.getAttribute('data-remove-staff');
      selectedStaff = selectedStaff.filter((s) => String(s.id) !== String(staffId));
      updateSelectedStaff();
    });

    staffMenu.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();

      const a = e.target.closest('a[data-id]');
      if (!a) return;

      const id = a.getAttribute('data-id');
      const nameEl = a.querySelector('span');
      const name = nameEl ? nameEl.textContent : '';
      const imgDiv = a.querySelector('div');
      const img = imgDiv ? imgDiv.innerHTML : '';

      const staffObj = staffList.find((s) => String(s.id) === String(id));

      const idx = selectedStaff.findIndex((s) => String(s.id) === String(id));
      if (idx === -1) {
        selectedStaff.push({
          id,
          name,
          image: img,
          accountStatus: staffObj ? staffObj.accountStatus : 3
        });
      } else {
        selectedStaff.splice(idx, 1);
      }

      updateSelectedStaff();
      e.stopImmediatePropagation();
    });

    renderStaffDropdown();
    updateSelectedStaff();
  }

  function validateAddClientForm(showErrors) {
    const form = document.getElementById('add-client-form');
    if (!form) return true;

    const firstName = qs('input[name="firstName"]', form);
    const email = qs('input[name="email"]', form);
    const password = qs('input[name="password"]', form);
    const currency = qs('select[name="currency"]', form);

    const staffBtn = document.getElementById('staffDropdownBtn');
    const staffInput = document.getElementById('selectedStaffInput');

    const staffErr = document.getElementById('staff-error');
    const currencyErr = document.getElementById('currency-error');

    // Dropdown validation first (per requirement)
    const staffValid = !!(staffInput && String(staffInput.value || '').trim());
    const currencyRequired = !!currency;
    const currencyValid = !currencyRequired || !!String(currency.value || '').trim();

    if (showErrors) {
      setButtonInvalid(staffBtn, !staffValid);
      if (staffErr) staffErr.classList.toggle('d-none', staffValid);

      if (currency) setInvalid(currency, !currencyValid);
      if (currencyErr) currencyErr.classList.toggle('d-none', currencyValid);
    }

    const firstNameValid = !!(firstName && String(firstName.value || '').trim());
    const emailValid = !!(email && typeof email.checkValidity === 'function' && email.checkValidity());
    const passwordValid = !!(password && String(password.value || '').trim());

    if (showErrors) {
      setInvalid(firstName, !firstNameValid);
      setInvalid(email, !emailValid);
      setInvalid(password, !passwordValid);
    }

    const customFieldsValid = validateUserProfileCustomFields(form, showErrors);

    const allValid =
      staffValid &&
      currencyValid &&
      firstNameValid &&
      emailValid &&
      passwordValid &&
      customFieldsValid;
    return allValid;
  }

  function initAddClientValidationAndNextGate() {
    const form = document.getElementById('add-client-form');
    if (!form) return;

    const nextBtn = document.getElementById('next-to-permissions');
    const permissionsTab = document.getElementById('permissions-tab');
    const accountTab = document.getElementById('account-tab');
    const accountSubmitBox = document.getElementById('account-submit-box');
    const permissionsSubmitBox = document.getElementById('permissions-submit-box');

    function showAccountTab() {
      if (accountTab) accountTab.click();
    }

    function setSubmitBoxes(mode) {
      if (!accountSubmitBox || !permissionsSubmitBox) return;
      if (mode === 'permissions') {
        accountSubmitBox.style.display = 'none';
        permissionsSubmitBox.style.display = 'flex';
      } else {
        accountSubmitBox.style.display = 'flex';
        permissionsSubmitBox.style.display = 'none';
      }
    }

    const emailN1 = document.getElementById('emailNotification');
    const emailN2 = document.getElementById('emailNotification2');
    function syncAddClientEmailNotificationToggles(fromFirst) {
      if (!emailN1 || !emailN2) return;
      if (fromFirst) {
        emailN2.checked = emailN1.checked;
      } else {
        emailN1.checked = emailN2.checked;
      }
    }
    if (emailN1) {
      emailN1.addEventListener('change', function () {
        syncAddClientEmailNotificationToggles(true);
      });
    }
    if (emailN2) {
      emailN2.addEventListener('change', function () {
        syncAddClientEmailNotificationToggles(false);
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function (e) {
        const ok = validateAddClientForm(true);
        if (!ok) {
          e.preventDefault();
          e.stopPropagation();
          showAccountTab();
          setSubmitBoxes('account');
          return;
        }
        syncAddClientEmailNotificationToggles(true);
        if (permissionsTab) permissionsTab.click();
        setSubmitBoxes('permissions');
      });
    }

    const createClientBtn = document.getElementById('create-client-btn');
    if (createClientBtn) {
      createClientBtn.addEventListener(
        'click',
        function () {
          if (validateAddClientForm(false)) {
            form.dataset.allowSpinner = 'true';
          }
        },
        true
      );
    }

    form.addEventListener('submit', function (e) {
      const ok = validateAddClientForm(true);
      if (!ok) {
        form.dataset.allowSpinner = 'false';
        e.preventDefault();
        e.stopPropagation();
        showAccountTab();
        setSubmitBoxes('account');
        return;
      }
      // Allow general.js spinner to run (it attaches later via main-footer)
      form.dataset.allowSpinner = 'true';
    });

    form.addEventListener('input', function () {
      validateAddClientForm(true);
    });
    form.addEventListener('change', function () {
      validateAddClientForm(true);
    });

    // Keep submit boxes in sync when user clicks tabs directly
    if (accountTab) {
      accountTab.addEventListener('click', function () {
        setSubmitBoxes('account');
      });
    }
    if (permissionsTab) {
      permissionsTab.addEventListener('click', function () {
        setSubmitBoxes('permissions');
      });
    }

    // Initial state
    setSubmitBoxes('account');
  }

  function validateAddStaffAccountStep(showErrors) {
    const form = document.getElementById('add-staff-form');
    if (!form) return true;

    const requiredFields = [
      qs('input[name="firstName"]', form),
      qs('input[name="email"]', form),
      qs('input[name="title"]', form),
      qs('input[name="password"]', form),
      qs('select[name="role_id"]', form)
    ];

    let valid = true;
    requiredFields.forEach(function (field) {
      const fieldValid = !!(field && typeof field.checkValidity === 'function' && field.checkValidity());
      if (!fieldValid) valid = false;
      if (showErrors) setInvalid(field, !fieldValid);
    });

    const customFieldsValid = validateUserProfileCustomFields(form, showErrors);
    return valid && customFieldsValid;
  }

  function initAddStaffTabs() {
    const form = document.getElementById('add-staff-form');
    if (!form) return;

    const accountTab = document.getElementById('staff-account-tab');
    const attendanceTab = document.getElementById('attendance-settings-tab');
    const nextBtn = document.getElementById('next-to-attendance-settings');
    const accountSubmitBox = document.getElementById('staff-account-submit-box');
    const attendanceSubmitBox = document.getElementById('staff-attendance-submit-box');

    if (!attendanceTab) {
      if (accountSubmitBox) accountSubmitBox.style.display = 'flex';
      if (attendanceSubmitBox) attendanceSubmitBox.style.display = 'none';
      form.addEventListener('submit', function (e) {
        const ok = validateAddStaffAccountStep(true);
        if (!ok) {
          form.dataset.allowSpinner = 'false';
          e.preventDefault();
          e.stopPropagation();
          return;
        }
        form.dataset.allowSpinner = 'true';
      });
      form.addEventListener('input', function () {
        validateAddStaffAccountStep(true);
      });
      form.addEventListener('change', function () {
        validateAddStaffAccountStep(true);
      });
      return;
    }

    const attendanceDisabledAddStaff = document.getElementById('attendance_disabled_add_staff');
    const addStaffAttendanceFieldset = document.getElementById('add-staff-attendance-fields');
    function syncAddStaffAttendanceFieldsetDisabled() {
      if (!addStaffAttendanceFieldset) return;
      addStaffAttendanceFieldset.disabled = !!(attendanceDisabledAddStaff && attendanceDisabledAddStaff.checked);
    }
    if (attendanceDisabledAddStaff) {
      attendanceDisabledAddStaff.addEventListener('change', syncAddStaffAttendanceFieldsetDisabled);
      syncAddStaffAttendanceFieldsetDisabled();
    }

    function setSubmitBoxes(mode) {
      if (!accountSubmitBox || !attendanceSubmitBox) return;
      if (mode === 'attendance') {
        accountSubmitBox.style.display = 'none';
        attendanceSubmitBox.style.display = 'flex';
      } else {
        accountSubmitBox.style.display = 'flex';
        attendanceSubmitBox.style.display = 'none';
      }
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function (e) {
        const ok = validateAddStaffAccountStep(true);
        if (!ok) {
          e.preventDefault();
          e.stopPropagation();
          if (accountTab) accountTab.click();
          setSubmitBoxes('account');
          return;
        }
        if (attendanceTab) attendanceTab.click();
        setSubmitBoxes('attendance');
      });
    }

    form.addEventListener('submit', function (e) {
      const ok = validateAddStaffAccountStep(true);
      if (!ok) {
        form.dataset.allowSpinner = 'false';
        e.preventDefault();
        e.stopPropagation();
        if (accountTab) accountTab.click();
        setSubmitBoxes('account');
        return;
      }
      form.dataset.allowSpinner = 'true';
    });

    form.addEventListener('input', function () {
      validateAddStaffAccountStep(true);
    });
    form.addEventListener('change', function () {
      validateAddStaffAccountStep(true);
    });

    if (accountTab) {
      accountTab.addEventListener('click', function () {
        setSubmitBoxes('account');
      });
    }
    if (attendanceTab) {
      attendanceTab.addEventListener('click', function () {
        setSubmitBoxes('attendance');
      });
    }

    setSubmitBoxes('account');
  }

  function validateAddAdminAccountStep(showErrors) {
    const form = document.getElementById('add-admin-form');
    if (!form) return true;

    const requiredFields = [
      qs('input[name="firstName"]', form),
      qs('input[name="email"]', form),
      qs('input[name="title"]', form),
      qs('input[name="password"]', form)
    ];

    let valid = true;
    requiredFields.forEach(function (field) {
      const fieldValid = !!(field && typeof field.checkValidity === 'function' && field.checkValidity());
      if (!fieldValid) valid = false;
      if (showErrors) setInvalid(field, !fieldValid);
    });

    return valid;
  }

  function initAddAdminTabs() {
    const form = document.getElementById('add-admin-form');
    if (!form) return;

    const accountTab = document.getElementById('admin-account-tab');
    const attendanceTab = document.getElementById('admin-attendance-settings-tab');
    const nextBtn = document.getElementById('next-to-attendance-settings-admin');
    const accountSubmitBox = document.getElementById('admin-account-submit-box');
    const attendanceSubmitBox = document.getElementById('admin-attendance-submit-box');

    if (!attendanceTab) {
      if (accountSubmitBox) accountSubmitBox.style.display = 'flex';
      if (attendanceSubmitBox) attendanceSubmitBox.style.display = 'none';
      form.addEventListener('submit', function (e) {
        const ok = validateAddAdminAccountStep(true);
        if (!ok) {
          form.dataset.allowSpinner = 'false';
          e.preventDefault();
          e.stopPropagation();
          return;
        }
        form.dataset.allowSpinner = 'true';
      });
      form.addEventListener('input', function () {
        validateAddAdminAccountStep(true);
      });
      form.addEventListener('change', function () {
        validateAddAdminAccountStep(true);
      });
      return;
    }

    const attendanceDisabledAddAdmin = document.getElementById('attendance_disabled_add_admin');
    const addAdminAttendanceFieldset = document.getElementById('add-admin-attendance-fields');
    function syncAddAdminAttendanceFieldsetDisabled() {
      if (!addAdminAttendanceFieldset) return;
      addAdminAttendanceFieldset.disabled = !!(attendanceDisabledAddAdmin && attendanceDisabledAddAdmin.checked);
    }
    if (attendanceDisabledAddAdmin) {
      attendanceDisabledAddAdmin.addEventListener('change', syncAddAdminAttendanceFieldsetDisabled);
      syncAddAdminAttendanceFieldsetDisabled();
    }

    function setSubmitBoxes(mode) {
      if (!accountSubmitBox || !attendanceSubmitBox) return;
      if (mode === 'attendance') {
        accountSubmitBox.style.display = 'none';
        attendanceSubmitBox.style.display = 'flex';
      } else {
        accountSubmitBox.style.display = 'flex';
        attendanceSubmitBox.style.display = 'none';
      }
    }

    if (nextBtn) {
      nextBtn.addEventListener('click', function (e) {
        const ok = validateAddAdminAccountStep(true);
        if (!ok) {
          e.preventDefault();
          e.stopPropagation();
          if (accountTab) accountTab.click();
          setSubmitBoxes('account');
          return;
        }
        if (attendanceTab) attendanceTab.click();
        setSubmitBoxes('attendance');
      });
    }

    form.addEventListener('submit', function (e) {
      const ok = validateAddAdminAccountStep(true);
      if (!ok) {
        form.dataset.allowSpinner = 'false';
        e.preventDefault();
        e.stopPropagation();
        if (accountTab) accountTab.click();
        setSubmitBoxes('account');
        return;
      }
      form.dataset.allowSpinner = 'true';
    });

    form.addEventListener('input', function () {
      validateAddAdminAccountStep(true);
    });
    form.addEventListener('change', function () {
      validateAddAdminAccountStep(true);
    });

    if (accountTab) {
      accountTab.addEventListener('click', function () {
        setSubmitBoxes('account');
      });
    }
    if (attendanceTab) {
      attendanceTab.addEventListener('click', function () {
        setSubmitBoxes('attendance');
      });
    }

    setSubmitBoxes('account');
  }

  function init() {
    initAddClientStaffDropdown();
    initAddClientValidationAndNextGate();
    initAddStaffTabs();
    initAddAdminTabs();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();


