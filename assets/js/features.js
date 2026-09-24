/**
 * Global Features JavaScript
 * Contains reusable dropdown and form functionality
 */

// Global variables for dropdown functionality
let selectedStaff = [];
let selectedAdditionalClients = [];
let projectSelectedCompanyId = null;

function projectCompanyInitial(name) {
    const s = String(name || '').trim();
    return s ? s.charAt(0).toUpperCase() : 'C';
}

function projectCompanyColorIndex(text) {
    const s = String(text || '');
    let hash = 0;
    for (let i = 0; i < s.length; i++) {
        hash = (hash + s.charCodeAt(i) * (i + 1)) % 8;
    }
    return hash + 1;
}

function projectCompanyAvatarElement(company, baseUrl) {
    const logoPath = ((company && company.logo_path) || '').trim();
    if (logoPath) {
        const img = document.createElement('img');
        img.className = 'rounded-circle';
        img.width = 28;
        img.height = 28;
        img.alt = '';
        img.src = String(baseUrl || '').replace(/\/$/, '') + '/' + logoPath.replace(/^\//, '');
        return img;
    }
    const avatar = document.createElement('div');
    avatar.className =
        'avatar-initials color-' +
        projectCompanyColorIndex((company && company.name) || (company && company.id)) +
        ' avatar-initials-small rounded-circle';
    avatar.textContent = projectCompanyInitial(company && company.name);
    return avatar;
}

function projectCompanyAvatarHtml(company, baseUrl) {
    const logoPath = ((company && company.logo_path) || '').trim();
    if (logoPath) {
        return (
            '<img class="rounded-circle" width="28" height="28" alt="" src="' +
            String(baseUrl || '').replace(/\/$/, '') +
            '/' +
            logoPath.replace(/^\//, '') +
            '">'
        );
    }
    const initial = projectCompanyInitial(company && company.name);
    const colorIdx = projectCompanyColorIndex((company && company.name) || (company && company.id));
    return (
        '<div class="avatar-initials color-' +
        colorIdx +
        ' avatar-initials-small rounded-circle">' +
        initial +
        '</div>'
    );
}



/**
 * Initialize all dropdown functionality
 */
function initializeDropdowns() {
    // Get variables from global scope (set by PHP)
    const clientList = window.clientList || [];
    const staffList = window.staffList || [];
    
    // Additional init defines window.updateAdditionalClientsList used by main picker
    initializeAdditionalClientsDropdown(clientList);
    initializeMainClientDropdown(clientList);
    initializeStaffDropdown(staffList);
    
    // Initialize Rich Editor
    initializeRichEditor();
    
    // Initialize date pickers
    initializeDatePickers();

    // Initialize add-project form validation + spinner (admin/add-new-project.php)
    initializeAddProjectForm();
}

/**
 * Add Project Form: required highlighting + spinner
 */
function initializeAddProjectForm() {
    const form = document.getElementById('addProjectForm');
    if (!form) return;

    const submitBtn = document.getElementById('create-project-btn');
    const projectTitle = document.getElementById('projectTite');
    const budget = document.getElementById('budget');
    const startDate = document.getElementById('startTime');
    const endDate = document.getElementById('endTime');
    const mainClientBtn = document.getElementById('mainClientDropdownBtn');
    const mainClientInput = document.getElementById('selectedMainClientInput');
    const mainClientError = document.getElementById('main-client-error');
    const staffBtn = document.getElementById('staffDropdownBtn');
    const staffInput = document.getElementById('selectedStaffInput');
    const staffError = document.getElementById('project-staff-error');

    const today = new Date().toISOString().split('T')[0];
    if (startDate && !startDate.getAttribute('min')) startDate.setAttribute('min', today);
    if (endDate && !endDate.getAttribute('min')) endDate.setAttribute('min', today);

    function setInvalid(el, isInvalid) {
        if (!el) return;
        el.classList.toggle('is-invalid', !!isInvalid);
    }

    function setDropdownInvalid(btnEl, isInvalid) {
        if (!btnEl) return;
        btnEl.classList.toggle('is-invalid', !!isInvalid);
    }

    function parseDate(val) {
        if (!val) return null;
        const d = new Date(val);
        if (isNaN(d.getTime())) return null;
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function validateProjectForm(showErrors) {
        const titleVal = projectTitle ? projectTitle.value.trim() : '';
        const budgetVal = budget ? budget.value.trim() : '';
        const mainClientVal = mainClientInput ? mainClientInput.value.trim() : '';
        const staffVal = staffInput ? staffInput.value.trim() : '';
        const startVal = startDate ? startDate.value : '';
        const endVal = endDate ? endDate.value : '';

        const validTitle = !!titleVal;
        const validBudget = (budgetVal === '' || !isNaN(budgetVal));
        const validMainClient = mainClientVal !== '' && mainClientVal !== '0';
        const validStaff = staffVal !== '';
        const validStart = !!startVal;
        const validEnd = !!endVal;

        const startDt = parseDate(startVal);
        const endDt = parseDate(endVal);
        const todayDt = new Date();
        todayDt.setHours(0, 0, 0, 0);

        const startNotPast = startDt ? startDt >= todayDt : false;
        const endNotPast = endDt ? endDt >= todayDt : false;
        const endAfterStart = (startDt && endDt) ? (endDt >= startDt) : false;

        const datesOk = validStart && validEnd && startNotPast && endNotPast && endAfterStart;

        if (showErrors) {
            setInvalid(projectTitle, !validTitle);
            setInvalid(budget, !validBudget);
            setInvalid(startDate, !validStart || !startNotPast);
            setInvalid(endDate, !validEnd || !endNotPast || (validStart && validEnd && !endAfterStart));

            setDropdownInvalid(mainClientBtn, !validMainClient);
            if (mainClientError) mainClientError.classList.toggle('d-none', validMainClient);

            setDropdownInvalid(staffBtn, !validStaff);
            if (staffError) staffError.classList.toggle('d-none', validStaff);
        }

        return validTitle && validBudget && validMainClient && validStaff && datesOk;
    }

    // Keep end date min in sync with start date
    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            const startVal = startDate.value;
            if (startVal) {
                endDate.setAttribute('min', startVal > today ? startVal : today);
            } else {
                endDate.setAttribute('min', today);
            }
        });
    }

    // Clear errors as user edits
    [projectTitle, budget, startDate, endDate].forEach(el => {
        if (!el) return;
        el.addEventListener('input', function() { setInvalid(el, false); });
        el.addEventListener('change', function() { setInvalid(el, false); });
    });

    // When dropdown inputs change (set by JS), clear errors
    if (mainClientInput) {
        mainClientInput.addEventListener('change', function() {
            setDropdownInvalid(mainClientBtn, false);
            if (mainClientError) mainClientError.classList.add('d-none');
        });
    }
    if (staffInput) {
        staffInput.addEventListener('change', function() {
            setDropdownInvalid(staffBtn, false);
            if (staffError) staffError.classList.add('d-none');
        });
    }

    // Spinner
    function showProjectSpinner() {
        if (!submitBtn) return;
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';

        if (!submitBtn.dataset.originalContent) {
            submitBtn.dataset.originalContent = submitBtn.innerHTML;
        }

        submitBtn.innerHTML = `
            <svg class="spinner-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle class="spinner-circle-animated" cx="12" cy="12" r="10" stroke-dasharray="24" stroke-dashoffset="24"></circle>
            </svg>
            ${submitBtn.dataset.originalContent}
        `;

        submitBtn.disabled = true;
        submitBtn.style.pointerEvents = 'none';
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
    }

    form.addEventListener('submit', function(e) {
        if (e.defaultPrevented) return false;

        if (typeof window.validateRequiredCustomFieldsForForm === 'function') {
            const cfOk = window.validateRequiredCustomFieldsForForm(form, true);
            if (!cfOk) {
                e.preventDefault();
                return false;
            }
        }

        const ok = validateProjectForm(true);
        if (!ok) {
            e.preventDefault();
            return false;
        }

        // Show spinner only when valid
        showProjectSpinner();
    });
}

/**
 * Edit Project Form: required highlighting + spinner
 * Note: allows existing past dates if unchanged; prevents setting past dates newly.
 */
function initializeEditProjectForm() {
    const form = document.getElementById('editProjectForm');
    if (!form) return;

    const submitBtn = document.getElementById('edit-project-btn');
    const projectTitle = document.getElementById('projectTite');
    const budget = document.getElementById('budget');
    const startDate = document.getElementById('startTime');
    const endDate = document.getElementById('endTime');
    const mainClientBtn = document.getElementById('mainClientDropdownBtn');
    const mainClientInput = document.getElementById('selectedMainClientInput');
    const staffBtn = document.getElementById('staffDropdownBtn');
    const staffInput = document.getElementById('selectedStaffInput');

    const today = new Date().toISOString().split('T')[0];
    if (startDate && !startDate.getAttribute('min')) startDate.setAttribute('min', today);
    if (endDate && !endDate.getAttribute('min')) endDate.setAttribute('min', today);

    const originalStartVal = startDate ? startDate.value : '';
    const originalEndVal = endDate ? endDate.value : '';

    function setInvalid(el, isInvalid) {
        if (!el) return;
        el.classList.toggle('is-invalid', !!isInvalid);
    }

    function setDropdownInvalid(btnEl, isInvalid) {
        if (!btnEl) return;
        btnEl.classList.toggle('is-invalid', !!isInvalid);
    }

    function parseDate(val) {
        if (!val) return null;
        const d = new Date(val);
        if (isNaN(d.getTime())) return null;
        d.setHours(0, 0, 0, 0);
        return d;
    }

    function validateEditProjectForm(showErrors) {
        const titleVal = projectTitle ? projectTitle.value.trim() : '';
        const budgetVal = budget ? budget.value.trim() : '';
        const mainClientVal = mainClientInput ? mainClientInput.value.trim() : '';
        const staffVal = staffInput ? staffInput.value.trim() : '';
        const startVal = startDate ? startDate.value : '';
        const endVal = endDate ? endDate.value : '';

        const validTitle = !!titleVal;
        const validBudget = budgetVal !== '' && !isNaN(budgetVal);
        const validMainClient = mainClientVal !== '' && mainClientVal !== '0';
        const validStaff = staffVal !== '';
        const validStart = !!startVal;
        const validEnd = !!endVal;

        const startDt = parseDate(startVal);
        const endDt = parseDate(endVal);
        const todayDt = new Date();
        todayDt.setHours(0, 0, 0, 0);

        const startChanged = startVal !== originalStartVal;
        const endChanged = endVal !== originalEndVal;

        const startNotPast = startDt ? (startDt >= todayDt || !startChanged) : false;
        const endNotPast = endDt ? (endDt >= todayDt || !endChanged) : false;
        const endAfterStart = (startDt && endDt) ? (endDt >= startDt) : false;

        const datesOk = validStart && validEnd && startNotPast && endNotPast && endAfterStart;

        if (showErrors) {
            setInvalid(projectTitle, !validTitle);
            setInvalid(budget, !validBudget);
            setInvalid(startDate, !validStart || !startNotPast);
            setInvalid(endDate, !validEnd || !endNotPast || (validStart && validEnd && !endAfterStart));

            setDropdownInvalid(mainClientBtn, !validMainClient);
            setDropdownInvalid(staffBtn, !validStaff);
        }

        return validTitle && validBudget && validMainClient && validStaff && datesOk;
    }

    // Keep end date min in sync with start date (when user changes start date)
    if (startDate && endDate) {
        startDate.addEventListener('change', function() {
            const startVal = startDate.value;
            if (startVal) {
                endDate.setAttribute('min', startVal > today ? startVal : today);
            } else {
                endDate.setAttribute('min', today);
            }
        });
    }

    // Clear errors as user edits
    [projectTitle, budget, startDate, endDate].forEach(el => {
        if (!el) return;
        el.addEventListener('input', function() { setInvalid(el, false); });
        el.addEventListener('change', function() { setInvalid(el, false); });
    });

    if (mainClientInput) {
        mainClientInput.addEventListener('change', function() {
            setDropdownInvalid(mainClientBtn, false);
        });
    }
    if (staffInput) {
        staffInput.addEventListener('change', function() {
            setDropdownInvalid(staffBtn, false);
        });
    }

    function showSpinner() {
        if (!submitBtn) return;
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';

        if (!submitBtn.dataset.originalContent) {
            submitBtn.dataset.originalContent = submitBtn.innerHTML;
        }

        submitBtn.innerHTML = `
            <svg class="spinner-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <circle class="spinner-circle-animated" cx="12" cy="12" r="10" stroke-dasharray="24" stroke-dashoffset="24"></circle>
            </svg>
            ${submitBtn.dataset.originalContent}
        `;

        submitBtn.disabled = true;
        submitBtn.style.pointerEvents = 'none';
        submitBtn.style.opacity = '0.6';
        submitBtn.style.cursor = 'not-allowed';
    }

    form.addEventListener('submit', function(e) {
        if (e.defaultPrevented) return false;

        if (typeof window.validateRequiredCustomFieldsForForm === 'function') {
            const cfOk = window.validateRequiredCustomFieldsForForm(form, true);
            if (!cfOk) {
                e.preventDefault();
                return false;
            }
        }

        const ok = validateEditProjectForm(true);
        if (!ok) {
            e.preventDefault();
            return false;
        }
        showSpinner();
    });
}

/**
 * Staff Dropdown: team groups + staff/admins + search
 */
function initializeStaffDropdown(staffList) {
    const staffMenu = document.getElementById('staffDropdownMenu');
    const staffBtn = document.getElementById('staffDropdownBtn');
    const staffBtnText = document.getElementById('staffDropdownBtnText');
    const selectedStaffInput = document.getElementById('selectedStaffInput');
    const L = window.addProjectLang || {};
    const teamGroupList = window.projectTeamGroupList || [];

    if (!staffMenu || !staffBtn || !staffBtnText || !selectedStaffInput) {
        return;
    }
    if (staffMenu.dataset.projectStaffDdInit === '1') {
        return;
    }
    staffMenu.dataset.projectStaffDdInit = '1';

    if (typeof window.selectedStaffIds !== 'undefined' && Array.isArray(window.selectedStaffIds)) {
        selectedStaff = window.selectedStaffIds.map(function (id) {
            const staff = staffList.find(function (s) {
                return String(s.id) === String(id);
            });
            if (staff) {
                return {
                    id: staff.id,
                    name: staff.name,
                    img: staff.image,
                    accountStatus: staff.accountStatus,
                };
            }
            return null;
        }).filter(Boolean);
    }

    function staffPlaceholder() {
        return L.selectStaffPlaceholder || 'Select staff members';
    }

    function appendSearchRow() {
        staffMenu.innerHTML = '';
        const li = document.createElement('li');
        li.className = 'px-3 py-2 border-bottom';
        const inp = document.createElement('input');
        inp.type = 'search';
        inp.className = 'form-control w-100';
        inp.setAttribute('autocomplete', 'off');
        inp.setAttribute('aria-label', L.search || 'Search');
        inp.placeholder = L.search || 'Search';
        li.appendChild(inp);
        staffMenu.appendChild(li);
        return inp;
    }

    function isGroupFullySelected(userIds) {
        if (!userIds || !userIds.length) {
            return false;
        }
        for (let i = 0; i < userIds.length; i++) {
            if (!selectedStaff.some(function (s) { return String(s.id) === String(userIds[i]); })) {
                return false;
            }
        }
        return true;
    }

    function getInitial(text) {
        const s = String(text || '').trim();
        return s ? s.charAt(0).toUpperCase() : 'G';
    }

    function getColorIndex(text) {
        const s = String(text || '');
        let hash = 0;
        for (let i = 0; i < s.length; i++) {
            hash = (hash + s.charCodeAt(i) * (i + 1)) % 8;
        }
        return hash + 1;
    }

    function renderStaffBody(filter) {
        const q = ((filter || '') + '').trim().toLowerCase();
        while (staffMenu.childElementCount > 1) {
            staffMenu.removeChild(staffMenu.lastElementChild);
        }
        let any = false;
        if (teamGroupList.length) {
            const lab = document.createElement('li');
            lab.className = 'dropdown-header text-uppercase fw-bold text-muted small';
            lab.textContent = L.teamGroups || 'Team groups';
            staffMenu.appendChild(lab);
            teamGroupList.forEach(function (g) {
                const gname = ((g.name || '') + '').toLowerCase();
                if (q && gname.indexOf(q) === -1) {
                    return;
                }
                any = true;
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'dropdown-item d-flex align-items-center';
                const uids = Array.isArray(g.user_ids) ? g.user_ids : [];
                if (isGroupFullySelected(uids)) {
                    a.classList.add('active');
                }
                a.setAttribute('data-pick-type', 'group');
                a.setAttribute('data-group-id', String(g.id));
                a.setAttribute('data-user-ids', uids.join(','));
                const initial = getInitial(g.name);
                const colorIdx = getColorIndex(g.name || g.id);
                a.innerHTML =
                    '<div class="me-2"><div class="avatar-initials color-' +
                    String(colorIdx) +
                    ' avatar-initials-small rounded-circle">' +
                    initial +
                    '</div></div><span>' +
                    (g.name || '') +
                    '</span>';
                li.appendChild(a);
                staffMenu.appendChild(li);
            });
        }
        const lab2 = document.createElement('li');
        lab2.className = 'dropdown-header text-uppercase fw-bold text-muted small';
        lab2.textContent = L.staffAdmins || 'Staff and admins';
        staffMenu.appendChild(lab2);
        staffList.forEach(function (staff) {
            const sname = ((staff.name || '') + '').toLowerCase();
            if (q && sname.indexOf(q) === -1) {
                return;
            }
            any = true;
            const li = document.createElement('li');
            const adminBadge =
                staff.accountStatus === 1
                    ? '<span class="badge color-inprogress inprogress-bg-op ms-2 align-self-start">ADMIN</span>'
                    : '';
            li.innerHTML =
                '<a href="#" class="dropdown-item d-flex align-items-center" data-pick-type="staff" data-id="' +
                String(staff.id) +
                '"><div class="me-2">' +
                staff.image +
                '</div><span>' +
                staff.name +
                '</span>' +
                adminBadge +
                '</a>';
            staffMenu.appendChild(li);
        });
        if (!any && q) {
            const empty = document.createElement('li');
            empty.className = 'px-3 py-1 text-muted fst-italic';
            empty.textContent = L.noMatches || 'No matches';
            staffMenu.appendChild(empty);
        }
    }

    const searchInput = appendSearchRow();
    renderStaffBody('');
    searchInput.addEventListener('input', function () {
        renderStaffBody(searchInput.value);
    });

    staffMenu.addEventListener('click', function (e) {
        const a = e.target.closest('a[data-pick-type]');
        if (!a) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        const type = a.getAttribute('data-pick-type');
        if (type === 'group') {
            const raw = a.getAttribute('data-user-ids') || '';
            const ids = raw
                .split(',')
                .map(function (x) {
                    return parseInt(x, 10);
                })
                .filter(function (n) {
                    return n > 0;
                });
            const allSel = isGroupFullySelected(ids);
            if (allSel) {
                const idSet = {};
                ids.forEach(function (id) {
                    idSet[String(id)] = true;
                });
                selectedStaff = selectedStaff.filter(function (s) {
                    return !idSet[String(s.id)];
                });
            } else {
                ids.forEach(function (uid) {
                    if (!selectedStaff.some(function (s) { return String(s.id) === String(uid); })) {
                        const staffObj = staffList.find(function (s) {
                            return String(s.id) === String(uid);
                        });
                        if (staffObj) {
                            selectedStaff.push({
                                id: staffObj.id,
                                name: staffObj.name,
                                img: staffObj.image,
                                accountStatus: staffObj.accountStatus,
                            });
                        }
                    }
                });
            }
            updateSelectedStaff();
            renderStaffBody(searchInput.value || '');
            return;
        }
        if (type === 'staff') {
            const id = a.getAttribute('data-id');
            const staffObj = staffList.find(function (s) {
                return String(s.id) === String(id);
            });
            const nameSpan = a.querySelector('span');
            const name = nameSpan ? nameSpan.textContent : '';
            const imgDiv = a.querySelector('div');
            const idx = selectedStaff.findIndex(function (s) {
                return String(s.id) === String(id);
            });
            if (idx === -1) {
                selectedStaff.push({
                    id: id,
                    name: name,
                    img: imgDiv ? imgDiv.outerHTML : '',
                    accountStatus: staffObj ? staffObj.accountStatus : 3,
                });
            } else {
                selectedStaff.splice(idx, 1);
            }
            updateSelectedStaff();
            renderStaffBody(searchInput.value || '');
        }
    });

    function updateSelectedStaff() {
        selectedStaffInput.value = selectedStaff
            .map(function (s) {
                return s.id;
            })
            .join(',');
        const changeEv = new Event('change', { bubbles: true });
        selectedStaffInput.dispatchEvent(changeEv);
        if (selectedStaff.length) {
            staffBtnText.innerHTML = selectedStaff
                .map(function (s) {
                    const adminBadge =
                        s.accountStatus === 1
                            ? '<span class="badge color-inprogress inprogress-bg-op">ADMIN</span>'
                            : '';
                    return (
                        '<div class="d-inline-flex align-items-center active-user">' +
                        '<div>' +
                        s.img +
                        '</div><span class="ms-1">' +
                        s.name +
                        '</span>' +
                        adminBadge +
                        '<button type="button" class="btn-close btn-close-sm ms-2" aria-label="Remove" onclick="removeStaff(\'' +
                        String(s.id) +
                        "')\">×</button></div>"
                    );
                })
                .join('');
        } else {
            staffBtnText.textContent = staffPlaceholder();
        }
    }

    window.removeStaff = function (staffId) {
        const idx = selectedStaff.findIndex(function (s) {
            return String(s.id) === String(staffId);
        });
        if (idx !== -1) {
            selectedStaff.splice(idx, 1);
            updateSelectedStaff();
            renderStaffBody(searchInput.value || '');
        }
    };

    updateSelectedStaff();
}

/**
 * Main client / company dropdown + search
 */
function initializeMainClientDropdown(clientList) {
    const mainClientMenu = document.getElementById('mainClientDropdownMenu');
    const mainClientBtn = document.getElementById('mainClientDropdownBtn');
    const mainClientBtnText = document.getElementById('mainClientDropdownBtnText');
    const selectedMainClientInput = document.getElementById('selectedMainClientInput');
    const L = window.addProjectLang || {};
    const companyList = window.projectCompanyList || [];
    const baseUrl = window.projectAssetsBaseUrl || '';

    if (!mainClientMenu || !mainClientBtn || !mainClientBtnText || !selectedMainClientInput) {
        return;
    }
    if (mainClientMenu.dataset.projectMainDdInit === '1') {
        return;
    }
    mainClientMenu.dataset.projectMainDdInit = '1';

    function mainPlaceholder() {
        return L.selectMainPlaceholder || 'Select client or company';
    }

    function appendMainSearchRow() {
        mainClientMenu.innerHTML = '';
        const li = document.createElement('li');
        li.className = 'px-3 py-2 border-bottom';
        const inp = document.createElement('input');
        inp.type = 'search';
        inp.className = 'form-control w-100';
        inp.setAttribute('autocomplete', 'off');
        inp.setAttribute('aria-label', L.search || 'Search');
        inp.placeholder = L.search || 'Search';
        li.appendChild(inp);
        mainClientMenu.appendChild(li);
        return inp;
    }

    function renderMainBody(filter) {
        const q = ((filter || '') + '').trim().toLowerCase();
        while (mainClientMenu.childElementCount > 1) {
            mainClientMenu.removeChild(mainClientMenu.lastElementChild);
        }
        let any = false;
        if (companyList.length) {
            const lab = document.createElement('li');
            lab.className = 'dropdown-header text-uppercase fw-bold text-muted small';
            lab.textContent = L.companies || 'Companies';
            mainClientMenu.appendChild(lab);
            companyList.forEach(function (c) {
                const cname = ((c.name || '') + '').toLowerCase();
                if (q && cname.indexOf(q) === -1) {
                    return;
                }
                any = true;
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'dropdown-item d-flex align-items-center project-main-pick';
                a.setAttribute('data-pick-type', 'company');
                a.setAttribute('data-company-id', String(c.id));
                a.setAttribute('data-primary-id', String(c.primary_user_id || ''));
                const members = Array.isArray(c.member_user_ids) ? c.member_user_ids : [];
                a.setAttribute('data-member-ids', members.join(','));
                const left = document.createElement('div');
                left.className = 'me-2';
                left.appendChild(projectCompanyAvatarElement(c, baseUrl));
                const sp = document.createElement('span');
                sp.textContent = c.name || '';
                a.appendChild(left);
                a.appendChild(sp);
                li.appendChild(a);
                mainClientMenu.appendChild(li);
            });
        }
        const lab2 = document.createElement('li');
        lab2.className = 'dropdown-header text-uppercase fw-bold text-muted small';
        lab2.textContent = L.clients || 'Clients';
        mainClientMenu.appendChild(lab2);
        if (Array.isArray(clientList)) {
            clientList.forEach(function (client) {
                const cn = ((client.name || '') + '').toLowerCase();
                if (q && cn.indexOf(q) === -1) {
                    return;
                }
                any = true;
                const li = document.createElement('li');
                li.innerHTML =
                    '<a href="#" class="dropdown-item d-flex align-items-center project-main-pick" data-pick-type="client" data-id="' +
                    String(client.id) +
                    '"><div class="me-2">' +
                    client.image +
                    '</div><span>' +
                    client.name +
                    '</span></a>';
                mainClientMenu.appendChild(li);
            });
        }
        if (!any && q) {
            const empty = document.createElement('li');
            empty.className = 'px-3 py-1 text-muted fst-italic';
            empty.textContent = L.noMatches || 'No matches';
            mainClientMenu.appendChild(empty);
        }
    }

    const mainSearchInput = appendMainSearchRow();
    renderMainBody('');
    mainSearchInput.addEventListener('input', function () {
        renderMainBody(mainSearchInput.value);
    });

    function setMainButtonHtml(imgHtml, labelText) {
        mainClientBtnText.innerHTML =
            '<div class="d-flex align-items-center">' +
            '<div>' +
            imgHtml +
            '</div><span class="ms-1">' +
            labelText +
            '</span>' +
            '<button type="button" class="btn-close btn-close-sm ms-2" aria-label="Remove" onclick="removeMainClient()">×</button></div>';
    }

    function applyCompanySelection(c) {
        projectSelectedCompanyId = c.id;
        const primary = parseInt(c.primary_user_id, 10) || 0;
        if (primary <= 0) {
            return;
        }
        selectedMainClientInput.value = String(primary);
        const changeEv = new Event('change', { bubbles: true });
        selectedMainClientInput.dispatchEvent(changeEv);
        const members = Array.isArray(c.member_user_ids) ? c.member_user_ids : [];
        selectedAdditionalClients = [];
        members.forEach(function (uid) {
            if (String(uid) === String(primary)) {
                return;
            }
            const cl = clientList.find(function (x) {
                return String(x.id) === String(uid);
            });
            if (cl) {
                selectedAdditionalClients.push({ id: cl.id, name: cl.name, img: cl.image });
            }
        });
        const baseUrlLocal = baseUrl.replace(/\/$/, '');
        const imgHtml = projectCompanyAvatarHtml(c, baseUrlLocal);
        setMainButtonHtml(imgHtml, c.name || '');
        if (typeof window.updateSelectedAdditionalClients === 'function') {
            window.updateSelectedAdditionalClients();
        }
        if (typeof updateAdditionalClientsList === 'function') {
            updateAdditionalClientsList(String(primary));
        }
    }

    mainClientMenu.addEventListener('click', function (e) {
        const a = e.target.closest('a.project-main-pick');
        if (!a) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        const type = a.getAttribute('data-pick-type');
        if (type === 'company') {
            const cid = parseInt(a.getAttribute('data-company-id'), 10);
            const c = companyList.find(function (x) {
                return parseInt(x.id, 10) === cid;
            });
            if (c) {
                applyCompanySelection(c);
            }
            renderMainBody(mainSearchInput.value || '');
            return;
        }
        if (type === 'client') {
            projectSelectedCompanyId = null;
            const id = a.getAttribute('data-id');
            const imgDiv = a.querySelector('div');
            const nameSpan = a.querySelector('span');
            const name = nameSpan ? nameSpan.textContent : '';
            selectedMainClientInput.value = id;
            const changeEv = new Event('change', { bubbles: true });
            selectedMainClientInput.dispatchEvent(changeEv);
            selectedAdditionalClients = [];
            setMainButtonHtml(imgDiv ? imgDiv.outerHTML : '', name);
            if (typeof window.updateSelectedAdditionalClients === 'function') {
                window.updateSelectedAdditionalClients();
            }
            if (typeof updateAdditionalClientsList === 'function') {
                updateAdditionalClientsList(id);
            }
            renderMainBody(mainSearchInput.value || '');
        }
    });

    if (typeof window.selectedClientId !== 'undefined' && window.selectedClientId) {
        const selectedClient = clientList.find(function (client) {
            return String(client.id) === String(window.selectedClientId);
        });
        if (selectedClient) {
            projectSelectedCompanyId = null;
            selectedMainClientInput.value = selectedClient.id;
            setMainButtonHtml(selectedClient.image, selectedClient.name);
            if (typeof window.updateSelectedAdditionalClients === 'function') {
                window.updateSelectedAdditionalClients();
            }
            if (typeof updateAdditionalClientsList === 'function') {
                updateAdditionalClientsList(String(selectedClient.id));
            }
        }
    }

    window.removeMainClient = function () {
        projectSelectedCompanyId = null;
        selectedMainClientInput.value = '';
        const changeEv = new Event('change', { bubbles: true });
        selectedMainClientInput.dispatchEvent(changeEv);
        mainClientBtnText.textContent = mainPlaceholder();
        selectedAdditionalClients = [];
        if (typeof window.updateSelectedAdditionalClients === 'function') {
            window.updateSelectedAdditionalClients();
        }
        if (typeof updateAdditionalClientsList === 'function') {
            updateAdditionalClientsList('');
        }
        renderMainBody(mainSearchInput.value || '');
    };
}

/**
 * Additional Clients Dropdown + search
 */
function initializeAdditionalClientsDropdown(clientList) {
    const additionalClientsMenu = document.getElementById('additionalClientsDropdownMenu');
    const additionalClientsBtn = document.getElementById('additionalClientsDropdownBtn');
    const additionalClientsBtnText = document.getElementById('additionalClientsDropdownBtnText');
    const selectedClientsInput = document.getElementById('selectedClientsInput');
    const L = window.addProjectLang || {};

    if (!additionalClientsMenu || !additionalClientsBtn || !additionalClientsBtnText || !selectedClientsInput) {
        return;
    }
    if (additionalClientsMenu.dataset.projectAddClientsDdInit === '1') {
        return;
    }
    additionalClientsMenu.dataset.projectAddClientsDdInit = '1';

    function moreClientsPlaceholder() {
        return L.selectMoreClientsPlaceholder || 'Select more clients';
    }

    function appendAdditionalSearchRow() {
        additionalClientsMenu.innerHTML = '';
        const li = document.createElement('li');
        li.className = 'px-3 py-2 border-bottom';
        const inp = document.createElement('input');
        inp.type = 'search';
        inp.id = 'projectAdditionalClientsSearch';
        inp.className = 'form-control w-100';
        inp.setAttribute('autocomplete', 'off');
        inp.setAttribute('aria-label', L.search || 'Search');
        inp.placeholder = L.search || 'Search';
        li.appendChild(inp);
        additionalClientsMenu.appendChild(li);
        return inp;
    }

    const addSearchInput = appendAdditionalSearchRow();

    function updateAdditionalClientsList(mainClientId) {
        while (additionalClientsMenu.childElementCount > 1) {
            additionalClientsMenu.removeChild(additionalClientsMenu.lastElementChild);
        }
        const q = (addSearchInput.value || '').trim().toLowerCase();
        let any = false;
        if (Array.isArray(clientList)) {
            clientList.forEach(function (client) {
                if (String(client.id) === String(mainClientId)) {
                    return;
                }
                const cn = ((client.name || '') + '').toLowerCase();
                if (q && cn.indexOf(q) === -1) {
                    return;
                }
                any = true;
                const li = document.createElement('li');
                li.innerHTML =
                    '<a href="#" class="dropdown-item d-flex align-items-center" data-id="' +
                    String(client.id) +
                    '"><div class="me-2">' +
                    client.image +
                    '</div><span>' +
                    client.name +
                    '</span></a>';
                additionalClientsMenu.appendChild(li);
            });
        }
        if (!any && q) {
            const empty = document.createElement('li');
            empty.className = 'px-3 py-1 text-muted fst-italic';
            empty.textContent = L.noMatches || 'No matches';
            additionalClientsMenu.appendChild(empty);
        }
    }

    addSearchInput.addEventListener('input', function () {
        const mainClientId = document.getElementById('selectedMainClientInput')
            ? document.getElementById('selectedMainClientInput').value
            : '';
        updateAdditionalClientsList(mainClientId);
    });

    additionalClientsMenu.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const a = e.target.closest('a[data-id]');
        if (!a) {
            return;
        }
        const id = a.getAttribute('data-id');
        const name = a.querySelector('span') ? a.querySelector('span').textContent : '';
        const imgDiv = a.querySelector('div');
        const idx = selectedAdditionalClients.findIndex(function (c) {
            return String(c.id) === String(id);
        });
        if (idx === -1) {
            selectedAdditionalClients.push({ id: id, name: name, img: imgDiv ? imgDiv.outerHTML : '' });
        } else {
            selectedAdditionalClients.splice(idx, 1);
        }
        updateSelectedAdditionalClients();
    });

    function updateSelectedAdditionalClients() {
        const mainClientId = document.getElementById('selectedMainClientInput')
            ? document.getElementById('selectedMainClientInput').value
            : '';
        let allClientIds = [mainClientId];
        if (selectedAdditionalClients.length) {
            allClientIds = allClientIds.concat(
                selectedAdditionalClients.map(function (c) {
                    return c.id;
                })
            );
            additionalClientsBtnText.innerHTML = selectedAdditionalClients
                .map(function (c) {
                    return (
                        '<div class="d-inline-flex align-items-center active-user">' +
                        '<div>' +
                        c.img +
                        '</div><span class="ms-1">' +
                        c.name +
                        '</span>' +
                        '<button type="button" class="btn-close btn-close-sm ms-2" aria-label="Remove" onclick="removeAdditionalClient(\'' +
                        String(c.id) +
                        "')\">×</button></div>"
                    );
                })
                .join('');
        } else {
            additionalClientsBtnText.textContent = moreClientsPlaceholder();
        }
        if (selectedClientsInput) {
            selectedClientsInput.value = allClientIds.filter(Boolean).join(',');
        }
    }

    window.updateSelectedAdditionalClients = updateSelectedAdditionalClients;

    window.removeAdditionalClient = function (clientId) {
        const idx = selectedAdditionalClients.findIndex(function (c) {
            return String(c.id) === String(clientId);
        });
        if (idx !== -1) {
            selectedAdditionalClients.splice(idx, 1);
            updateSelectedAdditionalClients();
        }
    };

    window.updateAdditionalClientsList = updateAdditionalClientsList;

    const currentMainClientId = document.getElementById('selectedMainClientInput')
        ? document.getElementById('selectedMainClientInput').value
        : '';
    updateAdditionalClientsList(currentMainClientId);

    if (typeof window.selectedAdditionalClientIds !== 'undefined') {
        let additionalClientIds = window.selectedAdditionalClientIds;
        if (Array.isArray(additionalClientIds)) {
            /* ok */
        } else if (typeof additionalClientIds === 'object' && additionalClientIds !== null) {
            additionalClientIds = Object.values(additionalClientIds);
        } else {
            additionalClientIds = [];
        }
        if (additionalClientIds.length > 0) {
            const mid =
                document.getElementById('selectedMainClientInput') &&
                document.getElementById('selectedMainClientInput').value
                    ? document.getElementById('selectedMainClientInput').value
                    : '';
            selectedAdditionalClients = additionalClientIds
                .filter(function (id) {
                    return String(id) !== String(mid);
                })
                .map(function (id) {
                    const client = clientList.find(function (cl) {
                        return String(cl.id) === String(id);
                    });
                    if (client) {
                        return { id: client.id, name: client.name, img: client.image };
                    }
                    return null;
                })
                .filter(Boolean);
            updateSelectedAdditionalClients();
        }
    }
}

/**
 * Rich Editor Initialization (replaces TinyMCE)
 */
function initializeRichEditor() {
    // Check if RichEditor is available
    if (typeof RichEditor !== 'undefined') {
        // Initialize for description textareas
        const descriptionTextareas = document.querySelectorAll('textarea[name="description"], textarea[name="note_content"], textarea[name="task_description"], textarea.editor');
        
        descriptionTextareas.forEach(textarea => {
            // Check if already initialized
            if (textarea.dataset.richEditorInitialized !== 'true') {
                // Pass the element directly — never '#description' (task-sidebar also uses that id)
                new RichEditor(textarea, {
                    height: 300,
                    placeholder: textarea.placeholder || 'Start typing...'
                });
            }
        });
    }
}

/**
 * Date Picker Initialization
 */
function initializeDatePickers() {
    document.querySelectorAll('input[type="date"]').forEach(function(input) {
        input.addEventListener('focus', function(e) {
            this.showPicker && this.showPicker();
        });
        input.addEventListener('click', function(e) {
            this.showPicker && this.showPicker();
        });
    });
}

/**
 * Initialize Semantic-UI checkbox
 */
function initializeCheckboxes() {
    if (typeof $ !== 'undefined') {
        $('.custom-btnc').checkbox();
    }
}

/**
 * Project Search Functionality
 */
function initializeProjectSearch() {
    const searchInput = document.getElementById('task-search');
    if (!searchInput) return;

    // If AJAX search is enabled on this page, do not attach DOM-only filtering
    if (window.ajaxProjectSearchEnabled) return;

    searchInput.addEventListener('keyup', function() {
        var input = this.value.toUpperCase();
        var table = document.querySelector('#projects-tbl');
        if (!table) return;
        
        var rows = table.getElementsByTagName('tr');

        for (var i = 0; i < rows.length; i++) {
            var projectName = rows[i].getElementsByTagName('td')[1];
            if (projectName) {
                var txtValue = projectName.textContent || projectName.innerText;
                if (txtValue.toUpperCase().indexOf(input) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    });
}

/**
 * AJAX Project Search Functionality (admin/projects.php)
 * Searches the entire dataset (server-side) while typing.
 */
function initializeAjaxProjectSearch() {
    if (!window.ajaxProjectSearchEnabled) return;

    const searchInput = document.getElementById('task-search');
    if (!searchInput) return;

    const isProjectsTable = !!document.querySelector('#projects-tbl');
    const isProjectsGrid = !!document.querySelector('#project-grid');
    if (!isProjectsTable && !isProjectsGrid) return;

    const endpointBase = (window.baseUrl || '').replace(/\/?$/, '/');
    const endpointUrl = endpointBase + 'ajax/filter_projects.php';

    let debounceTimer = null;
    let controller = null;

    function getCurrentFilters() {
        const qs = new URLSearchParams(window.location.search);
        const status = qs.get('status');
        const allProjects = qs.get('all_projects');
        const view = qs.get('view');
        return {
            status: (status === '0' || status === '1') ? status : '',
            all_projects: allProjects === '1' ? '1' : '',
            view: view === 'grid' ? 'grid' : ''
        };
    }

    function getContext() {
        if (window.ajaxProjectSearchContext) return String(window.ajaxProjectSearchContext);
        const p = window.location.pathname || '';
        if (p.indexOf('/staff/') !== -1) return 'staff';
        if (p.indexOf('/client/') !== -1) return 'client';
        return 'admin';
    }

    function updateUrl(searchValue, page) {
        try {
            const qs = new URLSearchParams(window.location.search);
            if (searchValue && searchValue.trim()) {
                qs.set('search', searchValue.trim());
            } else {
                qs.delete('search');
            }
            qs.set('page', String(page || 1));
            const newUrl = window.location.pathname + '?' + qs.toString();
            window.history.replaceState({}, '', newUrl);
        } catch (e) {
            // ignore
        }
    }

    async function fetchAndRender(searchValue, page) {
        const filters = getCurrentFilters();
        const context = getContext();
        const params = new URLSearchParams();
        if (searchValue && searchValue.trim()) params.set('search', searchValue.trim());
        if (filters.status) params.set('status', filters.status);
        if (filters.all_projects) params.set('all_projects', '1');
        if (filters.view) params.set('view', 'grid');
        if (context) params.set('context', context);
        params.set('page', String(page || 1));

        if (controller) controller.abort();
        controller = new AbortController();

        const url = endpointUrl + '?' + params.toString();
        const res = await fetch(url, { signal: controller.signal, credentials: 'same-origin' });
        const data = await res.json();
        if (!data || !data.success) return;

        // Update results
        const tbody = document.querySelector('#projects-tbl');
        const grid = document.querySelector('#project-grid');
        if (tbody) tbody.innerHTML = data.results_html || '';
        if (grid) grid.innerHTML = data.results_html || '';

        // Update pagination (hidden when total records <= page size)
        const paginationBox = document.querySelector('.pagination-box');
        if (paginationBox) {
            if (data.pagination_html) {
                paginationBox.outerHTML = data.pagination_html;
            } else {
                paginationBox.remove();
            }
        } else if (data.pagination_html) {
            const anchor = grid || (tbody && tbody.closest('.table-responsive'));
            if (anchor && anchor.parentNode) {
                anchor.insertAdjacentHTML('afterend', data.pagination_html);
            }
        }

        // Re-init dropdowns (dynamic HTML)
        if (typeof initializeBootstrapDropdowns === 'function') {
            initializeBootstrapDropdowns();
        }
    }

    function schedule(searchValue, page) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            updateUrl(searchValue, page);
            fetchAndRender(searchValue, page).catch(() => {});
        }, 300);
    }

    // Typing: always go back to page 1
    searchInput.addEventListener('input', function() {
        schedule(this.value, 1);
    });

    // Pagination clicks (AJAX)
    document.addEventListener('click', function(e) {
        const link = e.target && e.target.closest ? e.target.closest('.pagination a.page-link') : null;
        if (!link) return;
        // Only intercept on the projects page when AJAX is enabled
        if (!window.ajaxProjectSearchEnabled) return;

        const href = link.getAttribute('href') || '';
        if (!href) return;

        // Extract page from href like "?page=2&..."
        let page = 1;
        try {
            const tmpUrl = new URL(href, window.location.href);
            const p = tmpUrl.searchParams.get('page');
            page = p ? Math.max(1, parseInt(p, 10) || 1) : 1;
        } catch (err) {
            // ignore
        }

        e.preventDefault();
        schedule(searchInput.value, page);
    });
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCheckboxes();
    initializeProjectSearch();
    initializeAjaxProjectSearch();
    // Wait a bit to ensure PHP variables are loaded
    setTimeout(function() {
        initializeDropdowns();
        initializeEditProjectForm();
    }, 100);
}); 