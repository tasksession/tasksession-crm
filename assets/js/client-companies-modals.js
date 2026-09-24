(function () {
    'use strict';

    var CHECK_SVG =
        '<svg class="team-group-dd-check-svg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">' +
        '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>' +
        '</svg>';

    function apiUrl() {
        return window.clientCompaniesApiUrl || '';
    }

    function apiDebugEnabled() {
        return !!window.__clientCompaniesApiDebug;
    }

    function apiUrlWithDebug(suffix) {
        var base = apiUrl();
        var u = base + (suffix || '');
        if (apiDebugEnabled()) {
            u += (u.indexOf('?') >= 0 ? '&' : '?') + 'debug=1';
        }
        return u;
    }

    function formatApiDebug(debug) {
        if (!debug) return '';
        try {
            return JSON.stringify(debug, null, 2);
        } catch (e) {
            return String(debug);
        }
    }

    function applyApiDebugBoxStyle(el) {
        if (!el) return;
        el.style.whiteSpace = 'pre-wrap';
        el.style.maxHeight = '220px';
        el.style.overflow = 'auto';
        el.style.fontFamily = 'monospace';
        el.style.fontSize = '11px';
    }

    function hideAllApiDebugBoxes() {
        document.querySelectorAll('.cc-api-debug-box, #clientCompanyApiDebugBox').forEach(function (el) {
            el.classList.add('d-none');
            el.textContent = '';
        });
    }

    function showCompanyApiError(errEl, message, debug, rawText, debugBoxEl) {
        var lines = [message || 'Error'];
        if (debug) {
            lines.push('\n--- Debug ---\n' + formatApiDebug(debug));
        }
        if (rawText && apiDebugEnabled()) {
            lines.push('\n--- Raw response ---\n' + String(rawText).substring(0, 4000));
        }
        if (apiDebugEnabled() && window.clientCompaniesApiDebugUrl) {
            lines.push('\nFull diagnostics: ' + window.clientCompaniesApiDebugUrl);
        }
        var full = lines.join('\n');
        if (errEl) {
            errEl.textContent = message || 'Error';
            errEl.style.display = 'block';
        }
        var dbg = debugBoxEl;
        if (!dbg) {
            dbg = document.getElementById('clientCompanyApiDebugBoxView') || document.getElementById('clientCompanyApiDebugBox');
        }
        if (dbg && apiDebugEnabled()) {
            applyApiDebugBoxStyle(dbg);
            dbg.textContent = full;
            dbg.classList.remove('d-none');
        } else if (errEl && apiDebugEnabled()) {
            errEl.textContent = full;
            errEl.style.whiteSpace = 'pre-wrap';
            errEl.style.fontSize = '12px';
            errEl.style.maxHeight = '240px';
            errEl.style.overflow = 'auto';
        }
    }

    function fetchCompanyApi(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
            return r.text().then(function (text) {
                var j = null;
                try {
                    j = text ? JSON.parse(text) : null;
                } catch (parseErr) {
                    var bad = new Error('Server returned invalid JSON.');
                    bad.debug = apiDebugEnabled()
                        ? { parse_error: parseErr.message || String(parseErr), http_status: r.status }
                        : null;
                    bad.rawText = text;
                    throw bad;
                }
                if (!r.ok || !j || !j.ok) {
                    var fail = new Error((j && j.error) || r.statusText || 'Request failed');
                    fail.debug = j && j.debug;
                    fail.rawText = text;
                    throw fail;
                }
                return j;
            });
        });
    }

    function baseUrl() {
        return window.__clientCompaniesBaseUrl || '';
    }

    function getPickableClients() {
        return Array.isArray(window.clientCompanyPickableClients) ? window.clientCompanyPickableClients : [];
    }

    function getPickableCompanies(excludeId) {
        var list = Array.isArray(window.clientCompanyPickableCompanies) ? window.clientCompanyPickableCompanies : [];
        var ex = parseInt(excludeId, 10) || 0;
        if (ex <= 0) return list;
        return list.filter(function (c) {
            return parseInt(c.id, 10) !== ex;
        });
    }

    function companyPickerInitials(name) {
        var raw = String(name || '')
            .replace(/&nbsp;/g, ' ')
            .replace(/<[^>]*>/g, '')
            .trim();
        return (raw.charAt(0) || 'C').toUpperCase();
    }

    function renderCompanyLinkOptions(prefix, state) {
        var selectedIds = state.linkedIds || [];
        var listWrap = document.getElementById(prefix + 'LinkedOptionsList');
        var searchInput = document.getElementById(prefix + 'LinkedSearchInput');
        var noRow = document.getElementById(prefix + 'NoLinkedFound');
        var hidden = document.getElementById(prefix + 'SelectedLinkedIds');
        var btnLabel = document.getElementById(prefix + 'LinkedDropdownLabel');
        if (!listWrap || !searchInput || !hidden) return;

        var q = (searchInput.value || '').trim().toLowerCase();
        listWrap.innerHTML = '';
        var excludeId = state.excludeCompanyId || 0;
        var filtered = getPickableCompanies(excludeId).filter(function (c) {
            return !q || (c.search && c.search.indexOf(q) !== -1);
        });
        if (!filtered.length) {
            if (noRow) noRow.classList.remove('d-none');
            hidden.value = selectedIds.join(',');
            return;
        }
        if (noRow) noRow.classList.add('d-none');

        var selected = [];
        var unselected = [];
        filtered.forEach(function (c) {
            if (selectedIds.indexOf(String(c.id)) !== -1) selected.push(c);
            else unselected.push(c);
        });
        var users = selected.concat(unselected);
        var tmpl = window.__clientCompaniesLang || {};

        users.forEach(function (c) {
            var idStr = String(c.id);
            var sel = selectedIds.indexOf(idStr) !== -1;
            var li = document.createElement('li');
            li.className = 'project-option-item cc-company-picker-row';
            var a = document.createElement('a');
            a.href = '#';
            a.className = 'dropdown-item team-group-dd-option d-flex align-items-center' + (sel ? ' selected' : '');
            a.setAttribute('data-company-id', idStr);
            a.innerHTML =
                '<span class="team-group-dd-check" aria-hidden="true">' +
                (sel ? CHECK_SVG : '') +
                '</span><div class="tg-avatar-wrap me-2"><div class="avatar-initials color-8 avatar-initials-small rounded-circle">' +
                escapeHtml(companyPickerInitials(c.name)) +
                '</div></div><div class="tg-opt-lines"><div></div><small></small></div>';
            var lines = a.querySelector('.tg-opt-lines');
            if (lines) {
                lines.querySelector('div').textContent = c.name || '';
                lines.querySelector('small').textContent = c.vat || c.email || '';
            }
            li.appendChild(a);
            listWrap.appendChild(li);
        });

        hidden.value = selectedIds.join(',');
        if (btnLabel) {
            var n = selectedIds.length;
            btnLabel.textContent =
                n > 0
                    ? (tmpl.companiesSelectedN || '{n} selected').replace('{n}', String(n))
                    : tmpl.selectCompanies || 'Select companies';
        }
    }

    function bindCompanyLinkPickerOnce(prefix, state) {
        var menu = document.getElementById(prefix + 'LinkedDropdownMenu');
        var listWrap = document.getElementById(prefix + 'LinkedOptionsList');
        var searchInput = document.getElementById(prefix + 'LinkedSearchInput');
        if (!menu || !listWrap || !searchInput || menu.dataset.ccCompanyPickerBound === '1') {
            return;
        }
        menu.dataset.ccCompanyPickerBound = '1';

        searchInput.addEventListener('input', function () {
            renderCompanyLinkOptions(prefix, state);
        });

        listWrap.addEventListener('click', function (e) {
            var opt = e.target.closest('[data-company-id]');
            if (!opt) return;
            e.preventDefault();
            e.stopPropagation();
            var idStr = opt.getAttribute('data-company-id');
            if (!idStr) return;
            var idx = state.linkedIds.indexOf(idStr);
            if (idx === -1) state.linkedIds.push(idStr);
            else state.linkedIds.splice(idx, 1);
            renderCompanyLinkOptions(prefix, state);
        });
    }

    function openLinkedDropdown(prefix) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) return;
        var btn = document.getElementById(prefix + 'LinkedDropdownBtn');
        if (!btn) return;
        window.setTimeout(function () {
            try {
                bootstrap.Dropdown.getOrCreateInstance(btn).show();
            } catch (e) {
                /* ignore */
            }
        }, 0);
    }

    function navigateToCompanyProfile(companyId) {
        var cid = parseInt(companyId, 10);
        if (!cid) return;
        var portal = 'admin';
        var path = window.location.pathname || '';
        if (path.indexOf('/staff/') !== -1) portal = 'staff';
        var root = baseUrl();
        if (root) {
            window.location.href =
                root.replace(/\/$/, '') + '/' + portal + '/company-profile.php?company_id=' + encodeURIComponent(String(cid));
            return;
        }
        window.location.href = 'company-profile.php?company_id=' + encodeURIComponent(String(cid));
    }

    function shouldOpenCompanyPage() {
        return String(window.clientCompanyViewTarget || 'page') === 'page';
    }

    function escapeHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function pickerInitialsFromUser(u) {
        var raw = String(u.name || '')
            .replace(/&nbsp;/g, ' ')
            .replace(/<[^>]*>/g, '')
            .trim();
        var parts = raw.split(/\s+/).filter(Boolean);
        if (parts.length >= 2) {
            return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase().slice(0, 2);
        }
        return (raw.charAt(0) || 'U').toUpperCase();
    }

    function setPickerAvatar(av, u) {
        if (!av) return;
        var html = String(u.image || '').trim();
        if (html.indexOf('<img') !== -1) {
            av.innerHTML = html;
            return;
        }
        av.innerHTML =
            '<div class="avatar-initials color-8 avatar-initials-small rounded-circle">' +
            escapeHtml(pickerInitialsFromUser(u)) +
            '</div>';
    }

    function openMemberDropdown(prefix) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) return;
        var btn = document.getElementById(prefix + 'MemberDropdownBtn');
        if (!btn) return;
        window.setTimeout(function () {
            try {
                bootstrap.Dropdown.getOrCreateInstance(btn).show();
            } catch (e) {
                /* ignore */
            }
        }, 0);
    }

    /**
     * Keeps primary in sync with selection: one client → they are primary;
     * two or more → if none / invalid primary, default to first selected (invoice main contact).
     */
    function ccSyncPrimaryWithSelection(state) {
        var ids = state.selectedIds;
        if (ids.length === 0) {
            state.primaryId = null;
            return;
        }
        if (ids.length === 1) {
            state.primaryId = ids[0];
            return;
        }
        if (state.primaryId && ids.indexOf(state.primaryId) !== -1) {
            return;
        }
        state.primaryId = ids[0];
    }

    function renderClientOptions(prefix, state) {
        ccSyncPrimaryWithSelection(state);
        var selectedIds = state.selectedIds;
        var listWrap = document.getElementById(prefix + 'MemberOptionsList');
        var searchInput = document.getElementById(prefix + 'MemberSearchInput');
        var noRow = document.getElementById(prefix + 'NoMembersFound');
        var hidden = document.getElementById(prefix + 'SelectedMemberIds');
        var btnLabel = document.getElementById(prefix + 'MemberDropdownLabel');
        if (!listWrap || !searchInput || !hidden) return;

        var q = (searchInput.value || '').trim().toLowerCase();
        listWrap.innerHTML = '';
        var filtered = getPickableClients().filter(function (u) {
            return !q || (u.search && u.search.indexOf(q) !== -1);
        });
        if (!filtered.length) {
            if (noRow) noRow.classList.remove('d-none');
            return;
        }
        if (noRow) noRow.classList.add('d-none');

        var selectedUsers = [];
        var unselectedUsers = [];
        filtered.forEach(function (u) {
            var idStr = String(u.id);
            if (selectedIds.indexOf(idStr) !== -1) {
                selectedUsers.push(u);
            } else {
                unselectedUsers.push(u);
            }
        });
        selectedUsers.sort(function (a, b) {
            return selectedIds.indexOf(String(a.id)) - selectedIds.indexOf(String(b.id));
        });
        var users = selectedUsers.concat(unselectedUsers);
        var tmpl = window.__clientCompaniesLang || {};
        var primLabel = tmpl.primaryMainLabel || 'Main';

        users.forEach(function (u) {
            var idStr = String(u.id);
            var sel = selectedIds.indexOf(idStr) !== -1;
            var isPrimaryRow = state.primaryId && state.primaryId === idStr;
            var li = document.createElement('li');
            li.className = 'project-option-item cc-client-picker-row d-flex align-items-stretch position-relative';
            var a = document.createElement('a');
            a.href = '#';
            a.className =
                'dropdown-item team-group-dd-option d-flex align-items-center flex-grow-1 min-w-0' +
                (sel ? ' selected' : '');
            a.setAttribute('data-user-id', idStr);
            a.innerHTML =
                '<span class="team-group-dd-check" aria-hidden="true">' +
                (sel ? CHECK_SVG : '') +
                '</span><div class="tg-avatar-wrap me-2"></div><div class="tg-opt-lines"><div></div><small></small></div>';
            var av = a.querySelector('.tg-avatar-wrap');
            var lines = a.querySelector('.tg-opt-lines');
            setPickerAvatar(av, u);
            if (lines) {
                lines.querySelector('div').textContent = u.name || '';
                lines.querySelector('small').textContent = u.email || '';
            }
            var primBtn = document.createElement('button');
            primBtn.type = 'button';
            primBtn.className =
                'btn border-btn-a cc-member-primary-btn align-self-center my-1 me-2 flex-shrink-0' +
                (isPrimaryRow ? ' is-active' : '');
            primBtn.setAttribute('data-user-id', idStr);
            primBtn.setAttribute('title', primLabel);
            primBtn.setAttribute('aria-pressed', isPrimaryRow ? 'true' : 'false');
            primBtn.textContent = primLabel;
            li.appendChild(a);
            li.appendChild(primBtn);
            listWrap.appendChild(li);
        });

        hidden.value = selectedIds.join(',');
        if (btnLabel) {
            var n = selectedIds.length;
            btnLabel.textContent =
                n > 0
                    ? (tmpl.clientsSelectedN || '{n} selected').replace('{n}', String(n))
                    : tmpl.selectClients || 'Select clients';
        }
    }

    function bindClientPickerOnce(prefix, state) {
        var menu = document.getElementById(prefix + 'MemberDropdownMenu');
        var listWrap = document.getElementById(prefix + 'MemberOptionsList');
        var searchInput = document.getElementById(prefix + 'MemberSearchInput');
        var noRow = document.getElementById(prefix + 'NoMembersFound');
        if (!menu || !listWrap || !searchInput || menu.dataset.ccPickerBound === '1') {
            return;
        }
        menu.dataset.ccPickerBound = '1';

        searchInput.addEventListener('input', function () {
            renderClientOptions(prefix, state);
        });

        listWrap.addEventListener('click', function (e) {
            var primBtn = e.target.closest('.cc-member-primary-btn');
            if (primBtn) {
                e.preventDefault();
                e.stopPropagation();
                var pid = primBtn.getAttribute('data-user-id');
                if (!pid) return;
                if (state.selectedIds.indexOf(pid) === -1) {
                    state.selectedIds.push(pid);
                }
                state.primaryId = pid;
                ccSyncPrimaryWithSelection(state);
                renderClientOptions(prefix, state);
                e.stopImmediatePropagation();
                return;
            }
            var a = e.target.closest('a.team-group-dd-option');
            if (!a) return;
            e.preventDefault();
            e.stopPropagation();
            var idStr = a.getAttribute('data-user-id');
            if (!idStr) return;
            var i = state.selectedIds.indexOf(idStr);
            if (i === -1) state.selectedIds.push(idStr);
            else state.selectedIds.splice(i, 1);
            if (state.primaryId === idStr && i !== -1) {
                state.primaryId = null;
            }
            ccSyncPrimaryWithSelection(state);
            renderClientOptions(prefix, state);
            e.stopImmediatePropagation();
        });

        if (noRow) noRow.classList.add('d-none');
        renderClientOptions(prefix, state);
    }

    function postJson(body) {
        var csrf = window.__clientCompaniesCsrfToken || '';
        var payload = body && typeof body === 'object' ? Object.assign({}, body) : {};
        if (apiDebugEnabled()) {
            payload.debug = 1;
        }
        return fetch(apiUrlWithDebug(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'X-CC-API-Debug': apiDebugEnabled() ? '1' : '' },
            body: JSON.stringify(payload),
            credentials: 'same-origin',
        }).then(function (r) {
            return r.text().then(function (text) {
                var j = null;
                try {
                    j = text ? JSON.parse(text) : null;
                } catch (parseErr) {
                    var bad = new Error('Server returned invalid JSON.');
                    bad.debug = { parse_error: parseErr.message || String(parseErr), http_status: r.status };
                    bad.rawText = text;
                    throw bad;
                }
                if (!r.ok || !j || !j.ok) {
                    var fail = new Error((j && j.error) || r.statusText || 'Request failed');
                    fail.debug = j && j.debug;
                    fail.rawText = text;
                    throw fail;
                }
                return j;
            });
        });
    }

    function activateFirstTab(modalEl, firstTabBtnSelector) {
        if (!modalEl || typeof bootstrap === 'undefined') return;
        var btn = modalEl.querySelector(firstTabBtnSelector);
        if (btn && bootstrap.Tab) {
            try {
                var t = bootstrap.Tab.getOrCreateInstance(btn);
                t.show();
            } catch (e) {
                /* ignore */
            }
        }
    }

    function appendCompanyCustomFieldsToFormData(fd, prefix) {
        var wrap = document.getElementById(prefix + 'CustomFieldsContainer');
        if (!wrap) return;
        wrap.querySelectorAll('select[name^="custom_fields"]').forEach(function (el) {
            fd.append(el.name, el.value);
        });
        wrap.querySelectorAll('input.form-control[name^="custom_fields"]').forEach(function (el) {
            if (el.name.indexOf('[]') >= 0) return;
            fd.append(el.name, el.value);
        });
        wrap.querySelectorAll('textarea[name^="custom_fields"]').forEach(function (el) {
            fd.append(el.name, el.value);
        });
        wrap.querySelectorAll('input.upcf-checkbox-input[name^="custom_fields"]').forEach(function (el) {
            if (el.name.indexOf('[]') >= 0) return;
            fd.append(el.name, el.checked ? '1' : '0');
        });
        wrap.querySelectorAll('input.upcf-multiselect-input[type="checkbox"]').forEach(function (el) {
            if (!el.name || el.name.indexOf('[]') < 0) return;
            if (el.checked) fd.append(el.name, el.value);
        });
    }

    function setCfInvalid(el, isInvalid) {
        if (!el) return;
        el.classList.toggle('is-invalid', !!isInvalid);
    }

    function setCfMultiselectWrapInvalid(wrap, isInvalid) {
        if (!wrap) return;
        wrap.classList.toggle('border-danger', !!isInvalid);
    }

    function validateCompanyCustomFieldsInWrap(wrap, showErrors) {
        var t = window.__companyModalCfI18n || {};
        var msgText = t.fieldRequired || 'This field is required.';
        if (!wrap) return true;
        var groups = wrap.querySelectorAll('.form-group[data-cf-required="1"]');
        if (!groups.length) return true;
        var allValid = true;
        groups.forEach(function (group) {
            var ftEl = group.querySelector('[data-field-type]');
            var fieldType = ftEl ? String(ftEl.getAttribute('data-field-type') || '') : '';
            var errMsg = group.querySelector('.js-upcf-required-msg');
            var valid = true;
            if (fieldType === 'multiple_select') {
                var anyChecked = !!group.querySelector('input.form-check-input[type="checkbox"]:checked');
                valid = anyChecked;
                var mwrap = group.querySelector('.js-upcf-multiselect-wrap');
                if (showErrors) setCfMultiselectWrapInvalid(mwrap, !valid);
                else if (mwrap) mwrap.classList.remove('border-danger');
            } else if (fieldType === 'checkbox') {
                var cb = group.querySelector('input.form-check-input[type="checkbox"][data-custom-field-id]');
                valid = !!(cb && cb.checked);
                if (showErrors) setCfInvalid(cb, !valid);
                else setCfInvalid(cb, false);
            } else {
                var sel = group.querySelector('select[name^="custom_fields"]');
                var inp = group.querySelector('input.form-control[name^="custom_fields"]');
                var control = sel || inp;
                if (control) {
                    var v = String(control.value != null ? control.value : '').trim();
                    valid = v.length > 0;
                    if (showErrors) setCfInvalid(control, !valid);
                    else setCfInvalid(control, false);
                } else {
                    valid = false;
                }
            }
            if (!valid) allValid = false;
            if (errMsg) {
                if (showErrors) errMsg.classList.toggle('d-none', valid);
                else errMsg.classList.add('d-none');
            }
        });
        return allValid;
    }

    function loadCompanyModalCustomFields(prefix, initialValues) {
        var el = document.getElementById(prefix + 'CustomFieldsContainer');
        if (!el || typeof window.loadProfileStyleCustomFields !== 'function') {
            return Promise.resolve();
        }
        el.innerHTML = '';
        var listUrl = window.__clientCompaniesCustomFieldsListUrl || '../includes/custom-fields/list.php';
        return window.loadProfileStyleCustomFields(el, {
            entityType: 'company',
            listUrl: listUrl,
            i18n: window.__companyModalCfI18n || {},
            initialValues: initialValues || null,
            manageSectionVisibility: false,
            fieldLayout: { bootstrapTwoColumn: true }
        });
    }

    function appendCompanyFormData(fd, prefix, pickerState) {
        var selectedIds = pickerState.selectedIds;
        var nameEl = document.getElementById(prefix + 'Name');
        var vat = document.getElementById(prefix + 'Vat');
        var phone = document.getElementById(prefix + 'Phone');
        var email = document.getElementById(prefix + 'Email');
        var website = document.getElementById(prefix + 'Website');
        var currency = document.getElementById(prefix + 'Currency');
        var address = document.getElementById(prefix + 'Address');
        var city = document.getElementById(prefix + 'City');
        var addrState = document.getElementById(prefix + 'State');
        var zip = document.getElementById(prefix + 'Zip');
        var country = document.getElementById(prefix + 'Country');
        var bAddr = document.getElementById(prefix + 'BillingAddress');
        var bStreet = document.getElementById(prefix + 'BillingStreet');
        var bCity = document.getElementById(prefix + 'BillingCity');
        var bState = document.getElementById(prefix + 'BillingState');
        var bZip = document.getElementById(prefix + 'BillingZip');
        var bCountry = document.getElementById(prefix + 'BillingCountry');

        fd.append('name', nameEl ? nameEl.value.trim() : '');
        fd.append('vat_number', vat ? vat.value.trim() : '');
        fd.append('phone', phone ? phone.value.trim() : '');
        fd.append('email', email ? email.value.trim() : '');
        fd.append('website', website ? website.value.trim() : '');
        fd.append('currency', currency ? currency.value.trim() : '');
        fd.append('address', address ? address.value.trim() : '');
        fd.append('city', city ? city.value.trim() : '');
        fd.append('state', addrState ? addrState.value.trim() : '');
        fd.append('zip', zip ? zip.value.trim() : '');
        fd.append('country', country ? country.value.trim() : '');
        fd.append('billing_address', bAddr ? bAddr.value.trim() : '');
        fd.append('billing_street', bStreet ? bStreet.value.trim() : '');
        fd.append('billing_city', bCity ? bCity.value.trim() : '');
        fd.append('billing_state', bState ? bState.value.trim() : '');
        fd.append('billing_zip', bZip ? bZip.value.trim() : '');
        fd.append('billing_country', bCountry ? bCountry.value.trim() : '');
        fd.append('client_ids', selectedIds.join(','));
        fd.append('primary_client_id', pickerState.primaryId ? String(pickerState.primaryId) : '');
        var linkedIds = pickerState.linkedIds || [];
        fd.append('linked_company_ids', linkedIds.join(','));
        appendCompanyCustomFieldsToFormData(fd, prefix);
    }

    function postMultipart(fd) {
        var csrf = window.__clientCompaniesCsrfToken || '';
        if (apiDebugEnabled()) {
            fd.append('debug', '1');
        }
        return fetch(apiUrlWithDebug(), {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrf, 'X-CC-API-Debug': apiDebugEnabled() ? '1' : '' },
            body: fd,
            credentials: 'same-origin',
        }).then(function (r) {
            return r.text().then(function (text) {
                var j = null;
                try {
                    j = text ? JSON.parse(text) : null;
                } catch (parseErr) {
                    var bad = new Error('Server returned invalid JSON.');
                    bad.debug = { parse_error: parseErr.message || String(parseErr), http_status: r.status };
                    bad.rawText = text;
                    throw bad;
                }
                if (!r.ok || !j || !j.ok) {
                    var fail = new Error((j && j.error) || r.statusText || 'Request failed');
                    fail.debug = j && j.debug;
                    fail.rawText = text;
                    throw fail;
                }
                return j;
            });
        });
    }

    function logoPlaceholderSrc() {
        var img = document.getElementById('clientCompanyCreateLogoPreview');
        if (!img) return '';
        return img.getAttribute('data-cc-logo-placeholder') || '';
    }

    function revokeLogoObjectUrl(url) {
        if (url && String(url).indexOf('blob:') === 0) {
            try {
                URL.revokeObjectURL(url);
            } catch (e) {
                /* ignore */
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var createState = { selectedIds: [], primaryId: null, linkedIds: [], excludeCompanyId: 0 };
        var editState = { selectedIds: [], primaryId: null, linkedIds: [], excludeCompanyId: 0 };
        var createLogoObjUrl = null;
        var editLogoObjUrl = null;

        function setCreateLogoPreview(src, isBlob) {
            var preview = document.getElementById('clientCompanyCreateLogoPreview');
            if (!preview) return;
            if (createLogoObjUrl) {
                revokeLogoObjectUrl(createLogoObjUrl);
                createLogoObjUrl = null;
            }
            if (isBlob && src) {
                createLogoObjUrl = src;
            }
            preview.src = src || logoPlaceholderSrc();
        }

        function setEditLogoPreview(src, isBlob) {
            var preview = document.getElementById('clientCompanyEditLogoPreview');
            if (!preview) return;
            if (editLogoObjUrl) {
                revokeLogoObjectUrl(editLogoObjUrl);
                editLogoObjUrl = null;
            }
            if (isBlob && src) {
                editLogoObjUrl = src;
            }
            preview.src = src || preview.getAttribute('data-cc-logo-placeholder') || '';
        }

        function bindLogoFileInput(inputId, setPreview) {
            var inp = document.getElementById(inputId);
            if (!inp || inp.dataset.ccLogoBound === '1') return;
            inp.dataset.ccLogoBound = '1';
            inp.addEventListener('change', function () {
                var f = inp.files && inp.files[0];
                if (!f) return;
                var blobUrl = URL.createObjectURL(f);
                setPreview(blobUrl, true);
            });
        }

        bindLogoFileInput('clientCompanyCreateLogo', setCreateLogoPreview);
        bindLogoFileInput('clientCompanyEditLogo', setEditLogoPreview);

        bindClientPickerOnce('clientCompanyCreate', createState);
        bindClientPickerOnce('clientCompanyEdit', editState);
        bindCompanyLinkPickerOnce('clientCompanyCreate', createState);
        bindCompanyLinkPickerOnce('clientCompanyEdit', editState);

        var createModal = document.getElementById('clientCompanyCreateModal');
        var editModal = document.getElementById('clientCompanyEditModal');
        var errCreate = document.getElementById('clientCompanyCreateError');
        var errEdit = document.getElementById('clientCompanyEditError');
        var createSave = document.getElementById('clientCompanyCreateSave');
        var editSave = document.getElementById('clientCompanyEditSave');
        var editId = document.getElementById('clientCompanyEditId');
        var createInFlight = false;
        var editInFlight = false;

        function clearErr(el) {
            if (el) {
                el.textContent = '';
                el.style.display = 'none';
            }
        }

        function setBtnLoading(btn, loading) {
            if (!btn) return;
            btn.disabled = !!loading;
            btn.setAttribute('aria-busy', loading ? 'true' : 'false');
        }

        if (createModal) {
            createModal.addEventListener('show.bs.modal', function () {
                clearErr(errCreate);
                hideAllApiDebugBoxes();
                activateFirstTab(createModal, '#clientCompanyCreateTabClientsBtn');
                var logoIn = document.getElementById('clientCompanyCreateLogo');
                if (logoIn) logoIn.value = '';
                setCreateLogoPreview(logoPlaceholderSrc(), false);
                var ids = [
                    'clientCompanyCreateName',
                    'clientCompanyCreateVat',
                    'clientCompanyCreatePhone',
                    'clientCompanyCreateEmail',
                    'clientCompanyCreateWebsite',
                    'clientCompanyCreateAddress',
                    'clientCompanyCreateCity',
                    'clientCompanyCreateState',
                    'clientCompanyCreateZip',
                    'clientCompanyCreateBillingAddress',
                    'clientCompanyCreateBillingStreet',
                    'clientCompanyCreateBillingCity',
                    'clientCompanyCreateBillingState',
                    'clientCompanyCreateBillingZip',
                ];
                ids.forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.value = '';
                });
                var si = document.getElementById('clientCompanyCreateMemberSearchInput');
                if (si) si.value = '';
                var lsi = document.getElementById('clientCompanyCreateLinkedSearchInput');
                if (lsi) lsi.value = '';
                createState.selectedIds = [];
                createState.primaryId = null;
                createState.linkedIds = [];
                createState.excludeCompanyId = 0;
                var prefillId = parseInt(createModal.dataset.prefillClientId || '0', 10);
                if (prefillId > 0) {
                    createState.selectedIds = [prefillId];
                    createState.primaryId = prefillId;
                }
                createModal.dataset.prefillClientId = '';
                renderClientOptions('clientCompanyCreate', createState);
                renderCompanyLinkOptions('clientCompanyCreate', createState);
                createInFlight = false;
                setBtnLoading(createSave, false);
                loadCompanyModalCustomFields('clientCompanyCreate', null);
            });
            createModal.addEventListener('hidden.bs.modal', function () {
                var cf = document.getElementById('clientCompanyCreateCustomFieldsContainer');
                if (cf) cf.innerHTML = '';
            });
            createModal.addEventListener('shown.bs.modal', function () {
                openMemberDropdown('clientCompanyCreate');
            });
            createModal.addEventListener('shown.bs.tab', function (e) {
                if (e.target && e.target.id === 'clientCompanyCreateTabClientsBtn') {
                    openMemberDropdown('clientCompanyCreate');
                }
                if (e.target && e.target.id === 'clientCompanyCreateTabLinkCompaniesBtn') {
                    openLinkedDropdown('clientCompanyCreate');
                }
            });
        }

        if (editModal) {
            editModal.addEventListener('hidden.bs.modal', function () {
                var cf = document.getElementById('clientCompanyEditCustomFieldsContainer');
                if (cf) cf.innerHTML = '';
            });
            editModal.addEventListener('shown.bs.tab', function (e) {
                if (e.target && e.target.id === 'clientCompanyEditTabClientsBtn') {
                    openMemberDropdown('clientCompanyEdit');
                }
                if (e.target && e.target.id === 'clientCompanyEditTabLinkCompaniesBtn') {
                    openLinkedDropdown('clientCompanyEdit');
                }
            });
        }

        if (createSave) {
            createSave.addEventListener('click', function () {
                if (createInFlight) return;
                clearErr(errCreate);
                validateCompanyCustomFieldsInWrap(document.getElementById('clientCompanyCreateCustomFieldsContainer'), false);
                var L = window.__clientCompaniesLang || {};
                var name = document.getElementById('clientCompanyCreateName');
                var nm = name ? name.value.trim() : '';
                if (!nm) {
                    errCreate.textContent = L.nameRequired || 'Company name is required';
                    errCreate.style.display = 'block';
                    return;
                }
                var cur = document.getElementById('clientCompanyCreateCurrency');
                if (!cur || !cur.value) {
                    errCreate.textContent = L.currencyRequired || 'Select a currency';
                    errCreate.style.display = 'block';
                    return;
                }
                var cfWrap = document.getElementById('clientCompanyCreateCustomFieldsContainer');
                if (!validateCompanyCustomFieldsInWrap(cfWrap, true)) {
                    errCreate.textContent = L.cfRequiredSummary || 'Please complete required custom fields.';
                    errCreate.style.display = 'block';
                    return;
                }
                ccSyncPrimaryWithSelection(createState);
                createInFlight = true;
                setBtnLoading(createSave, true);
                var fd = new FormData();
                fd.append('action', 'create');
                appendCompanyFormData(fd, 'clientCompanyCreate', createState);
                var logoIn = document.getElementById('clientCompanyCreateLogo');
                if (logoIn && logoIn.files && logoIn.files[0]) {
                    fd.append('logo', logoIn.files[0]);
                }
                postMultipart(fd)
                    .then(function () {
                        window.location.reload();
                    })
                    .catch(function (e) {
                        showCompanyApiError(
                            errCreate,
                            e.message || 'Error',
                            e.debug,
                            e.rawText,
                            document.getElementById('clientCompanyApiDebugBox')
                        );
                    })
                    .finally(function () {
                        createInFlight = false;
                        setBtnLoading(createSave, false);
                    });
            });
        }

        document.querySelectorAll('.js-open-client-company-create').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (!createModal) return;
                var prefillId = parseInt(btn.getAttribute('data-prefill-client-id'), 10);
                createModal.dataset.prefillClientId = prefillId > 0 ? String(prefillId) : '';
                var m = bootstrap.Modal.getOrCreateInstance(createModal);
                m.show();
            });
        });

        document.querySelectorAll('.js-edit-client-company').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var cid = parseInt(btn.getAttribute('data-company-id'), 10);
                if (!cid || !editModal) return;
                var viewModal = document.getElementById('clientCompanyViewModal');
                if (viewModal && viewModal.contains(btn) && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    var vmi = bootstrap.Modal.getInstance(viewModal);
                    if (vmi) {
                        vmi.hide();
                    }
                }
                clearErr(errEdit);
                hideAllApiDebugBoxes();
                fetchCompanyApi(apiUrlWithDebug('?action=get&id=' + encodeURIComponent(String(cid))))
                    .then(function (j) {
                        if (!j.company) throw new Error('Load failed');
                        var c = j.company;
                        editId.value = String(c.id);
                        function v(id, val) {
                            var el = document.getElementById(id);
                            if (el) el.value = val != null ? String(val) : '';
                        }
                        v('clientCompanyEditName', c.name);
                        v('clientCompanyEditVat', c.vat_number);
                        v('clientCompanyEditPhone', c.phone);
                        v('clientCompanyEditEmail', c.email);
                        v('clientCompanyEditWebsite', c.website);
                        v('clientCompanyEditCurrency', c.currency);
                        v('clientCompanyEditAddress', c.address);
                        v('clientCompanyEditCity', c.city);
                        v('clientCompanyEditState', c.state);
                        v('clientCompanyEditZip', c.zip);
                        v('clientCompanyEditCountry', c.country);
                        v('clientCompanyEditBillingAddress', c.billing_address);
                        v('clientCompanyEditBillingStreet', c.billing_street);
                        v('clientCompanyEditBillingCity', c.billing_city);
                        v('clientCompanyEditBillingState', c.billing_state);
                        v('clientCompanyEditBillingZip', c.billing_zip);
                        v('clientCompanyEditBillingCountry', c.billing_country);
                        var logoIn = document.getElementById('clientCompanyEditLogo');
                        if (logoIn) logoIn.value = '';
                        if (c.logo_path) {
                            setEditLogoPreview(baseUrl() + '/' + String(c.logo_path).replace(/^\//, ''), false);
                        } else {
                            setEditLogoPreview('', false);
                        }
                        editState.selectedIds = (c.client_ids || []).map(String);
                        editState.linkedIds = (c.linked_company_ids || []).map(String);
                        editState.excludeCompanyId = parseInt(c.id, 10) || 0;
                        var pc = parseInt(c.primary_client_id, 10);
                        editState.primaryId =
                            pc > 0 && editState.selectedIds.indexOf(String(pc)) !== -1 ? String(pc) : null;
                        ccSyncPrimaryWithSelection(editState);
                        var si = document.getElementById('clientCompanyEditMemberSearchInput');
                        if (si) si.value = '';
                        renderClientOptions('clientCompanyEdit', editState);
                        renderCompanyLinkOptions('clientCompanyEdit', editState);
                        loadCompanyModalCustomFields('clientCompanyEdit', j.custom_field_values || []).then(function () {
                            activateFirstTab(editModal, '#clientCompanyEditTabDetailsBtn');
                            var m = bootstrap.Modal.getOrCreateInstance(editModal);
                            m.show();
                        });
                    })
                    .catch(function (err) {
                        showCompanyApiError(
                            errEdit,
                            err.message || 'Error',
                            err.debug,
                            err.rawText,
                            document.getElementById('clientCompanyApiDebugBoxEdit')
                        );
                    });
            });
        });

        if (editSave) {
            editSave.addEventListener('click', function () {
                if (editInFlight) return;
                clearErr(errEdit);
                validateCompanyCustomFieldsInWrap(document.getElementById('clientCompanyEditCustomFieldsContainer'), false);
                var L = window.__clientCompaniesLang || {};
                var id = parseInt(editId.value, 10);
                var name = document.getElementById('clientCompanyEditName');
                var nm = name ? name.value.trim() : '';
                if (!id || !nm) {
                    errEdit.textContent = L.nameRequired || 'Company name is required';
                    errEdit.style.display = 'block';
                    return;
                }
                var cur = document.getElementById('clientCompanyEditCurrency');
                if (!cur || !cur.value) {
                    errEdit.textContent = L.currencyRequired || 'Select a currency';
                    errEdit.style.display = 'block';
                    return;
                }
                var cfWrapE = document.getElementById('clientCompanyEditCustomFieldsContainer');
                if (!validateCompanyCustomFieldsInWrap(cfWrapE, true)) {
                    errEdit.textContent = L.cfRequiredSummary || 'Please complete required custom fields.';
                    errEdit.style.display = 'block';
                    return;
                }
                ccSyncPrimaryWithSelection(editState);
                editInFlight = true;
                setBtnLoading(editSave, true);
                var fd = new FormData();
                fd.append('action', 'update');
                fd.append('id', String(id));
                appendCompanyFormData(fd, 'clientCompanyEdit', editState);
                var logoIn = document.getElementById('clientCompanyEditLogo');
                if (logoIn && logoIn.files && logoIn.files[0]) {
                    fd.append('logo', logoIn.files[0]);
                }
                postMultipart(fd)
                    .then(function () {
                        window.location.reload();
                    })
                    .catch(function (e) {
                        showCompanyApiError(
                            errEdit,
                            e.message || 'Error',
                            e.debug,
                            e.rawText,
                            document.getElementById('clientCompanyApiDebugBoxEdit')
                        );
                    })
                    .finally(function () {
                        editInFlight = false;
                        setBtnLoading(editSave, false);
                    });
            });
        }

        function confirmDelete(id, name) {
            var L = window.__clientCompaniesLang || {};
            var msg = L.confirmDelete || 'Delete?';
            var named = L.confirmDeleteNamed || '';
            if (name && named) {
                msg = named.replace(/\{name\}/g, name);
            } else if (name) {
                msg = msg + '\n\n' + name;
            }
            if (!window.confirm(msg)) return;
            postJson({ action: 'delete', id: id })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) {
                    alert(err.message || 'Error');
                });
        }

        document.querySelectorAll('.js-delete-client-company').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var id = parseInt(btn.getAttribute('data-company-id'), 10);
                if (!id) return;
                var nm = btn.getAttribute('data-company-name') || '';
                confirmDelete(id, nm);
            });
        });

        document.querySelectorAll('.js-clone-client-company').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var id = parseInt(btn.getAttribute('data-company-id'), 10);
                if (!id) return;
                postJson({ action: 'clone', id: id })
                    .then(function () {
                        window.location.reload();
                    })
                    .catch(function (err) {
                        alert(err.message || 'Error');
                    });
            });
        });

        function notifyCompanyAction(msg, type) {
            if (typeof window.showToast === 'function') {
                window.showToast(msg, type === 'success' ? 'success' : 'error');
                return;
            }
            alert(msg);
        }

        function pinCompany(id, pin) {
            postJson({ action: pin ? 'pin' : 'unpin', id: id })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) {
                    var L = window.__clientCompaniesLang || {};
                    notifyCompanyAction(err.message || L.pinMaxError || 'Error', 'error');
                });
        }

        document.querySelectorAll('.js-pin-client-company').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = parseInt(btn.getAttribute('data-company-id'), 10);
                if (!id) return;
                pinCompany(id, true);
            });
        });

        document.querySelectorAll('.js-unpin-client-company').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = parseInt(btn.getAttribute('data-company-id'), 10);
                if (!id) return;
                pinCompany(id, false);
            });
        });

        function dashDisplay(val) {
            var s = val != null ? String(val).trim() : '';
            return s === '' ? '—' : s;
        }

        function formatCompanyViewCurrency(code) {
            code = code != null ? String(code).trim() : '';
            if (!code) return '—';
            // DB / select value is often the map key (e.g. "USD,$"), not "USD" alone — avoid "USD,$,$".
            var displayCode = code.split(',')[0].trim() || code;
            var map = window.__clientCompanyCurrencyLabels || {};
            var label = map[code];
            if (label == null && displayCode !== code) {
                label = map[displayCode];
            }
            if (label == null && displayCode) {
                var keys = Object.keys(map);
                for (var ki = 0; ki < keys.length; ki++) {
                    if (keys[ki] === displayCode || keys[ki].indexOf(displayCode + ',') === 0) {
                        label = map[keys[ki]];
                        break;
                    }
                }
            }
            if (label != null && String(label).trim() !== '') {
                var s = String(label).trim();
                var m = s.match(/\(([^)]+)\)\s*$/);
                if (m) {
                    var inner = String(m[1] || '').trim();
                    var sym = inner.split(/[;,|]/)[0].trim().replace(/\s+/g, '');
                    sym = sym.replace(/\$\$+/g, '$');
                    if (sym && sym.toUpperCase() === displayCode.toUpperCase()) {
                        sym = '$';
                    }
                    return displayCode + ',' + (sym || '$');
                }
            }
            return displayCode + ',$';
        }

        function formatViewDate(iso) {
            if (!iso) return '—';
            var d = new Date(String(iso).replace(' ', 'T'));
            if (window.isNaN(d.getTime())) return '—';
            return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
        }

        /** Address settings tab fields — multi-line block (same layout as billing). */
        function joinCompanyAddressBlock(c) {
            var line2 = [c.city, c.state, c.zip]
                .map(function (x) {
                    return String(x || '').trim();
                })
                .filter(Boolean)
                .join(' ');
            var lines = [c.address, line2, c.country]
                .map(function (x) {
                    return String(x || '').trim();
                })
                .filter(Boolean);
            return lines.length ? lines.join('\n') : '—';
        }

        function joinBill(c) {
            var line2 = [c.billing_city, c.billing_state, c.billing_zip]
                .map(function (x) {
                    return String(x || '').trim();
                })
                .filter(Boolean)
                .join(' ');
            var lines = [c.billing_address, c.billing_street, line2, c.billing_country]
                .map(function (x) {
                    return String(x || '').trim();
                })
                .filter(Boolean);
            return lines.length ? lines.join('\n') : '—';
        }

        function setPlain(id, text) {
            var el = document.getElementById(id);
            if (el) el.textContent = text;
        }

        function populateClientCompanyView(c, customFieldViewRows) {
            var Lv = window.__clientCompanyViewLang || {};
            var editHdr = document.getElementById('clientCompanyViewEditBtn');
            if (editHdr) {
                editHdr.setAttribute('data-company-id', c.id != null ? String(c.id) : '');
            }
            var logo = document.getElementById('clientCompanyViewLogo');
            if (logo) {
                if (c.logo_path && String(c.logo_path).trim()) {
                    logo.src = baseUrl() + '/' + String(c.logo_path).replace(/^\//, '');
                } else {
                    logo.src = logo.getAttribute('data-cc-placeholder') || '';
                }
            }
            setPlain('clientCompanyViewName', c.name || '');
            var meta = document.getElementById('clientCompanyViewMeta');
            if (meta) {
                var bits = [];
                bits.push((Lv.createdLabel || 'Created') + ': ' + formatViewDate(c.created_at));
                var cr = (c.creator_name || '').trim();
                if (cr) bits.push((Lv.byLabel || 'Created by') + ': ' + cr);
                meta.textContent = bits.join(' · ');
            }
            setPlain('clientCompanyViewVat', dashDisplay(c.vat_number));
            setPlain('clientCompanyViewPhone', dashDisplay(c.phone));
            var emEl = document.getElementById('clientCompanyViewEmail');
            if (emEl) {
                emEl.innerHTML = '';
                var ev = (c.email || '').trim();
                if (!ev) {
                    emEl.textContent = '—';
                } else {
                    var ea = document.createElement('a');
                    ea.href = 'mailto:' + ev;
                    ea.className = 'client-company-view-modal__link text-break';
                    ea.textContent = ev;
                    emEl.appendChild(ea);
                }
            }
            var web = document.getElementById('clientCompanyViewWebsite');
            if (web) {
                web.innerHTML = '';
                var w = (c.website || '').trim();
                if (!w) {
                    web.textContent = '—';
                } else {
                    var href = w.match(/^https?:\/\//i) ? w : 'https://' + w;
                    var link = document.createElement('a');
                    link.href = href;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = w;
                    link.className = 'text-break client-company-view-modal__link';
                    web.appendChild(link);
                }
            }
            setPlain('clientCompanyViewCurrency', formatCompanyViewCurrency(c.currency));
            var companyAddr = document.getElementById('clientCompanyViewCompanyAddress');
            if (companyAddr) {
                companyAddr.style.whiteSpace = 'pre-line';
                companyAddr.textContent = joinCompanyAddressBlock(c);
            }
            var rows = Array.isArray(customFieldViewRows) ? customFieldViewRows : [];
            var cfHost = document.getElementById('clientCompanyViewCustomFieldsSummary');
            if (cfHost) {
                cfHost.innerHTML = '';
                rows.forEach(function (row) {
                    var vt = row.value_text != null ? String(row.value_text).trim() : '';
                    if (!vt || vt === '—' || vt === '-') {
                        return;
                    }
                    var rowEl = document.createElement('div');
                    rowEl.className = 'summary-item d-flex flex-column align-items-start mb-3';
                    var lb = document.createElement('span');
                    lb.className = 'summary-label';
                    lb.textContent = (row.label || '') + ':';
                    var sp = document.createElement('span');
                    sp.className = 'summary-value text-break';
                    var ft = row.field_type || '';
                    if (ft === 'link' && vt) {
                        var wv = vt;
                        var hrefL = wv.match(/^https?:\/\//i) ? wv : 'https://' + wv;
                        var linkL = document.createElement('a');
                        linkL.href = hrefL;
                        linkL.target = '_blank';
                        linkL.rel = 'noopener noreferrer';
                        linkL.textContent = wv;
                        linkL.className = 'text-break client-company-view-modal__link';
                        sp.appendChild(linkL);
                    } else {
                        sp.textContent = vt ? vt : '—';
                    }
                    rowEl.appendChild(lb);
                    rowEl.appendChild(sp);
                    cfHost.appendChild(rowEl);
                });
            }
            var bill = document.getElementById('clientCompanyViewBilling');
            if (bill) {
                bill.style.whiteSpace = 'pre-line';
                bill.textContent = joinBill(c);
            }
            var list = document.getElementById('clientCompanyViewMembers');
            if (list) {
                list.innerHTML = '';
                var mem = Array.isArray(c.members) ? c.members : [];
                if (!mem.length) {
                    var empty = document.createElement('li');
                    empty.className = 'client-company-view-modal__members-empty';
                    empty.textContent = Lv.noMembers || 'No clients linked';
                    list.appendChild(empty);
                } else {
                    mem.forEach(function (m) {
                        var li = document.createElement('li');
                        li.className = 'card client-company-view-modal__member-row d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3';
                        var left = document.createElement('div');
                        left.className = 'client-company-view-modal__member-left d-flex align-items-center';
                        var avWrap = document.createElement('div');
                        avWrap.className = 'client-company-view-modal__member-avatar';
                        var avHtml = m.avatar_html ? String(m.avatar_html) : '';
                        if (!avHtml) {
                            var fallbackInitial = ((m.name || 'U').trim().charAt(0) || 'U').toUpperCase();
                            avHtml =
                                '<div class="avatar-initials color-8 avatar-initials-medium rounded-circle client-company-view-modal__avatar-fallback">' +
                                escapeHtml(fallbackInitial) +
                                '</div>';
                        }
                        avWrap.innerHTML = avHtml;
                        var textWrap = document.createElement('div');
                        textWrap.className = 'client-company-view-modal__member-text';
                        left.style.minWidth = '0';
                        var n = document.createElement('div');
                        n.className = 'fw-medium text-break d-flex align-items-center flex-wrap col-gap-5';
                        var nameSpan = document.createElement('span');
                        nameSpan.textContent = m.name || '';
                        n.appendChild(nameSpan);
                        if (m.is_primary) {
                            var badge = document.createElement('span');
                            badge.className = 'client-company-view-modal__primary-badge';
                            badge.textContent = Lv.primaryBadge || 'Main';
                            n.appendChild(badge);
                        }
                        if (m.from_linked_company && m.member_company_name) {
                            var coSpan = document.createElement('span');
                            coSpan.className = 'text-muted small';
                            coSpan.textContent = m.member_company_name;
                            n.appendChild(coSpan);
                        }
                        var em = document.createElement('div');
                        em.className = 'text-muted small text-break';
                        em.textContent = m.email || '';
                        textWrap.appendChild(n);
                        textWrap.appendChild(em);
                        left.appendChild(avWrap);
                        left.appendChild(textWrap);
                        var a = document.createElement('a');
                        a.href = 'profile.php?user_id=' + encodeURIComponent(String(m.id));
                        a.target = '_blank';
                        a.rel = 'noopener noreferrer';
                        a.className = 'btn-outline-grey flex-shrink-0';
                        a.textContent = Lv.viewProfile || 'View Profile';
                        li.appendChild(left);
                        li.appendChild(a);
                        list.appendChild(li);
                    });
                }
            }
        }

        function openClientCompanyViewModal(companyId) {
            var cid = parseInt(companyId, 10);
            if (!cid) return;
            var viewModal = document.getElementById('clientCompanyViewModal');
            if (!viewModal || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
            var errV = document.getElementById('clientCompanyViewError');
            if (errV) {
                errV.textContent = '';
                errV.style.display = 'none';
            }
            hideAllApiDebugBoxes();
            fetchCompanyApi(apiUrlWithDebug('?action=get&id=' + encodeURIComponent(String(cid))))
                .then(function (j) {
                    if (!j.company) throw new Error('Load failed');
                    populateClientCompanyView(j.company, j.custom_field_view_rows);
                    bootstrap.Modal.getOrCreateInstance(viewModal).show();
                })
                .catch(function (err) {
                    showCompanyApiError(
                        errV,
                        err.message || 'Error',
                        err.debug,
                        err.rawText,
                        document.getElementById('clientCompanyApiDebugBoxView')
                    );
                    bootstrap.Modal.getOrCreateInstance(viewModal).show();
                });
        }

        document.querySelectorAll('.js-view-client-company').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var cid = btn.getAttribute('data-company-id');
                if (shouldOpenCompanyPage()) {
                    navigateToCompanyProfile(cid);
                } else {
                    openClientCompanyViewModal(cid);
                }
            });
        });

        document.addEventListener('click', function (e) {
            var card = e.target.closest('.client-company-card[data-company-id]');
            if (!card) return;
            if (e.target.closest('.file-actions-dropdown')) return;
            e.preventDefault();
            var cid = card.getAttribute('data-company-id');
            if (shouldOpenCompanyPage()) {
                navigateToCompanyProfile(cid);
            } else {
                openClientCompanyViewModal(cid);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var t = e.target;
            if (!t || !t.classList || !t.classList.contains('client-company-card')) return;
            if (!t.getAttribute('data-company-id')) return;
            e.preventDefault();
            var cid = t.getAttribute('data-company-id');
            if (shouldOpenCompanyPage()) {
                navigateToCompanyProfile(cid);
            } else {
                openClientCompanyViewModal(cid);
            }
        });

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('.client-company-card [data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
            document.querySelectorAll('.client-companies-table [data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
        }
    });
})();
