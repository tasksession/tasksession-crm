/**
 * Task Management JavaScript
 * Handles project selection, staff assignment, and form interactions for task creation and editing
 */

// Global variables - will be set from PHP (var so bulk-task-groups.js can reset selection)
var selectedStaff = [];
let projectStaffMap = window.projectStaffMap || {};
let allProjects = window.allProjects || [];
let selectedProjectId = window.selectedProjectId || 0;
let staffList = window.staffList || [];
let selectedStaffIds = window.selectedStaffIds || [];
let isEditMode = window.isEditMode || false;
let projectTeamGroupList = window.projectTeamGroupList || [];

/**
 * Initialize task management functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize data from PHP if available
    if (window.projectStaffMap) {
        projectStaffMap = window.projectStaffMap;
    }
    if (window.allProjects) {
        allProjects = window.allProjects;
    }
    if (window.selectedProjectId) {
        selectedProjectId = window.selectedProjectId;
    }
    if (window.staffList) {
        staffList = window.staffList;
    }
    if (window.selectedStaffIds) {
        selectedStaffIds = window.selectedStaffIds;
    }
    if (window.projectTeamGroupList) {
        projectTeamGroupList = window.projectTeamGroupList;
    }
    if (window.isEditMode) {
        isEditMode = window.isEditMode;
    }
    
    // Wait a bit to ensure all scripts are loaded
    setTimeout(function() {
        initializeTaskManagement();
        initializeAllTasksFunctionality();
    }, 100);
});

// Client add_task.php uses a native <select> for project selection; redirect via URL instead of submitting the form.
document.addEventListener('DOMContentLoaded', function() {
    const projectSelect = document.getElementById('clientProjectSelect');
    if (projectSelect) {
        projectSelect.addEventListener('change', function() {
            const val = this.value;
            if (!val) return;
            const url = new URL(window.location.href);
            url.searchParams.set('projectId', val);
            // ensure hash doesn't interfere
            url.hash = '';
            window.location.href = url.toString();
        });
    }
});

/**
 * Initialize all task management features
 */
function initializeTaskManagement() {
    // Initialize Bootstrap dropdowns for all modes
    initializeBootstrapDropdowns();
    
    // Initialize project dropdown (only for add task)
    if (!isEditMode) {
        initializeProjectDropdown();
    }
    
    // Initialize staff dropdown
    initializeStaffDropdown();
    
    // Initialize Rich Editor
    initializeRichEditor();
    
    // Initialize form submission
    initializeFormSubmission();
}

/**
 * ===== Required fields gating (Add Task) =====
 * - Assign team members & admins: at least 1 selected
 * - Start Date: required
 * - Due Date: required (>= start, not past)
 */
function getCreateTaskButton() {
    // Keep name for backward compatibility, but support both add + edit submit buttons
    return (
        document.getElementById('create-task-btn') ||
        document.getElementById('edit-task-btn') ||
        document.querySelector('button[type="submit"][name="add-task"]') ||
        document.querySelector('button[type="submit"]')
    );
}

function getSelectedStaffIdsFromUI() {
    // Prefer selectedStaff array, fallback to hidden input value
    if (Array.isArray(selectedStaff) && selectedStaff.length > 0) {
        return selectedStaff
            .map(s => String(s.id).trim())
            .filter(id => id !== '' && id !== '0' && !isNaN(id) && parseInt(id, 10) > 0)
            .map(id => parseInt(id, 10));
    }

    const input = document.getElementById('selectedStaffInput');
    const raw = input ? (input.value || '') : '';
    if (!raw) return [];
    return raw
        .split(',')
        .map(v => v.trim())
        .filter(v => v !== '' && v !== '0' && !isNaN(v) && parseInt(v, 10) > 0)
        .map(v => parseInt(v, 10));
}

function parseYmdDate(value) {
    if (!value) return null;
    const d = new Date(value);
    if (isNaN(d.getTime())) return null;
    d.setHours(0, 0, 0, 0);
    return d;
}

function getTodayDate() {
    const t = new Date();
    t.setHours(0, 0, 0, 0);
    return t;
}

function setStaffErrorState(show) {
    const staffBtn = document.getElementById('staffDropdownBtn');
    const staffError = document.getElementById('staff-error');

    if (staffBtn) {
        if (show) {
            // Use a dedicated invalid class so styling is reliable with custom button styles
            staffBtn.classList.add('is-invalid');
        } else {
            staffBtn.classList.remove('is-invalid');
        }
    }
    if (staffError) {
        staffError.classList.toggle('d-none', !show);
    }
}

function setDateErrorState(inputEl, show) {
    if (!inputEl) return;
    inputEl.classList.toggle('is-invalid', !!show);
}

function isBulkTaskForm() {
    return !!document.querySelector('form[data-task-page="bulk"]');
}

function setBulkGroupErrorState(show) {
    const btn = document.getElementById('bulkTeamGroupDropdownBtn');
    if (!btn) return;
    btn.classList.toggle('is-invalid', !!show);
}

function updateCreateButtonState(options = {}) {
    const showErrors = !!options.showErrors;
    const createBtn = getCreateTaskButton();
    if (!createBtn) return true;

    const titleEl = document.querySelector('input[name="title"]') || document.querySelector('input[name="task_title"]');
    const titleVal = titleEl ? (titleEl.value || '') : '';
    const titleValid = !!String(titleVal).trim();

    const bulkForm = isBulkTaskForm();
    const staffInputExists = !!document.getElementById('selectedStaffInput') && !!document.getElementById('staffDropdownBtn');
    const staffIds = staffInputExists ? getSelectedStaffIdsFromUI() : [];
    let staffValid = staffInputExists ? (staffIds.length > 0) : true;
    if (bulkForm && staffInputExists) {
        const rGroup = document.getElementById('assignModeGroup');
        if (rGroup && rGroup.checked) {
            const gid = parseInt((document.getElementById('bulkStaffTeamGroupId') || {}).value || '0', 10);
            staffValid = gid > 0;
        }
    }

    const startEl = document.getElementById('start_date') || document.getElementById('bulk_start_date');
    const dueEl = document.getElementById('due_date') || document.getElementById('bulk_due_date');
    const startVal = startEl ? (startEl.value || '') : '';
    const dueVal = dueEl ? (dueEl.value || '') : '';

    const startDt = parseYmdDate(startVal);
    const dueDt = parseYmdDate(dueVal);
    const today = getTodayDate();

    let datesValid;
    if (bulkForm) {
        if (!startVal && !dueVal) {
            datesValid = true;
        } else if (startVal && dueVal) {
            datesValid = dueDt >= startDt;
        } else {
            datesValid = true;
        }
    } else {
        const startRequiredValid = !!startVal;
        const dueRequiredValid = !!dueVal;
        const startIsLocked = !!(startEl && startEl.disabled);
        const startNotPast = startIsLocked ? true : (startDt ? startDt >= today : false);
        const dueNotPast = isEditMode ? true : (dueDt ? dueDt >= today : false);
        const dueAfterStart = (startDt && dueDt) ? (dueDt >= startDt) : false;
        datesValid = startRequiredValid && dueRequiredValid && startNotPast && dueNotPast && dueAfterStart;
    }

    const staffTouched = document.body.dataset.staffTouched === 'true';
    const titleTouched = titleEl ? (titleEl.dataset.touched === 'true') : false;
    const startTouched = startEl ? (startEl.dataset.touched === 'true') : false;
    const dueTouched = dueEl ? (dueEl.dataset.touched === 'true') : false;

    setDateErrorState(titleEl, (showErrors || titleTouched) && !titleValid);
    if (bulkForm && staffInputExists) {
        const rGroup = document.getElementById('assignModeGroup');
        const groupMode = rGroup && rGroup.checked;
        setStaffErrorState(!groupMode && staffInputExists && (showErrors || staffTouched) && !staffValid);
        setBulkGroupErrorState(groupMode && (showErrors || staffTouched) && !staffValid);
    } else {
        setStaffErrorState(staffInputExists && (showErrors || staffTouched) && !staffValid);
        setBulkGroupErrorState(false);
    }

    if (bulkForm) {
        if (startVal && dueVal) {
            setDateErrorState(startEl, (showErrors || startTouched) && startDt && dueDt && dueDt < startDt);
            setDateErrorState(dueEl, (showErrors || dueTouched) && startDt && dueDt && dueDt < startDt);
        } else {
            setDateErrorState(startEl, false);
            setDateErrorState(dueEl, false);
        }
    } else {
        const startRequiredValid = !!startVal;
        const dueRequiredValid = !!dueVal;
        const startIsLocked = !!(startEl && startEl.disabled);
        const startNotPast = startIsLocked ? true : (startDt ? startDt >= today : false);
        const dueNotPast = isEditMode ? true : (dueDt ? dueDt >= today : false);
        const dueAfterStart = (startDt && dueDt) ? (dueDt >= startDt) : false;
        setDateErrorState(startEl, (showErrors || startTouched) && (!startRequiredValid || !startNotPast));
        setDateErrorState(dueEl, (showErrors || dueTouched) && (!dueRequiredValid || !dueNotPast || (startRequiredValid && dueRequiredValid && !dueAfterStart)));
    }

    return titleValid && staffValid && datesValid;
}

/**
 * Spinner helpers (Create Task)
 */
function showSubmitButtonSpinner(form) {
    if (!form) return false;
    if (form.dataset.submitting === 'true') return false;

    const btn = getCreateTaskButton();
    if (!btn) return false;

    form.dataset.submitting = 'true';

    if (!btn.dataset.originalContent) {
        btn.dataset.originalContent = btn.innerHTML;
    }

    const originalContent = btn.dataset.originalContent || btn.innerHTML;

    btn.innerHTML = `
        <svg class="spinner-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <circle class="spinner-circle-animated" cx="12" cy="12" r="10" stroke-dasharray="24" stroke-dashoffset="24"></circle>
        </svg>
        ${originalContent}
    `;

    btn.disabled = true;
    btn.style.pointerEvents = 'none';
    btn.style.opacity = '0.6';
    btn.style.cursor = 'not-allowed';

    // Safety restore if request hangs
    setTimeout(function() {
        if (form.dataset.submitting === 'true') {
            restoreSubmitButton(form);
            updateCreateButtonState({ showErrors: false });
        }
    }, 30000);

    return true;
}

function restoreSubmitButton(form) {
    if (!form) return;
    const btn = getCreateTaskButton();
    if (!btn) return;

    if (btn.dataset.originalContent) {
        btn.innerHTML = btn.dataset.originalContent;
    }

    btn.style.pointerEvents = '';
    btn.style.opacity = '';
    btn.style.cursor = '';

    form.dataset.submitting = 'false';
    btn.disabled = false;
}

function usesTaskCreatedModal(form) {
    return !!(form && form.getAttribute('data-task-created-modal') === '1');
}

function clearTaskCreateFormAlerts(form) {
    const root = form ? form.closest('.add-project') || form.closest('.center-col') : null;
    if (!root) return;
    root.querySelectorAll('.task-create-alert').forEach(function (el) {
        el.remove();
    });
}

function showTaskCreateFormErrors(form, errors) {
    if (!form || !Array.isArray(errors) || !errors.length) return;
    clearTaskCreateFormAlerts(form);
    const host = form.closest('.add-projects');
    if (!host) return;

    const alertEl = document.createElement('div');
    alertEl.className = 'alert alert-danger task-create-alert';
    alertEl.innerHTML = '<strong>Validation Error:</strong><ul>' +
        errors.map(function (err) {
            const li = document.createElement('li');
            li.textContent = String(err);
            return li.outerHTML;
        }).join('') +
        '</ul>';

    host.parentNode.insertBefore(alertEl, host);
    alertEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function resetRichEditorField(form) {
    if (!form) return;
    const textarea = form.querySelector('textarea[name="description"], textarea[name="task_description"]');
    if (!textarea) return;
    textarea.value = '';
    const container = textarea.parentNode ? textarea.parentNode.querySelector('.rich-editor-container') : null;
    if (container) {
        const content = container.querySelector('.rich-editor-content, [contenteditable="true"]');
        if (content) {
            content.innerHTML = '';
        }
    }
}

function resetAddTaskFormAfterSuccess(form) {
    if (!form) return;

    const titleInput = form.querySelector('input[name="title"], input[name="task_title"]');
    if (titleInput) {
        titleInput.value = '';
        titleInput.dataset.touched = 'false';
    }

    const startDateInput = form.querySelector('#start_date, #bulk_start_date');
    const dueDateInput = form.querySelector('#due_date, #bulk_due_date');
    if (startDateInput) {
        startDateInput.value = '';
        startDateInput.dataset.touched = 'false';
    }
    if (dueDateInput) {
        dueDateInput.value = '';
        dueDateInput.dataset.touched = 'false';
    }

    resetRichEditorField(form);
    clearTaskCreateFormAlerts(form);

    const staffInputExists = !!document.getElementById('selectedStaffInput') && !!document.getElementById('staffDropdownBtn');
    if (staffInputExists) {
        selectedStaff = [];
        document.body.dataset.staffTouched = 'false';
        updateSelectedStaff();
        setStaffErrorState(false);

        let projectId = 0;
        const selectedProjectInput = document.getElementById('selectedProjectInput');
        const clientProjectSelect = document.getElementById('clientProjectSelect');
        if (selectedProjectInput && selectedProjectInput.value) {
            projectId = parseInt(selectedProjectInput.value, 10) || 0;
        } else if (clientProjectSelect && clientProjectSelect.value) {
            projectId = parseInt(clientProjectSelect.value, 10) || 0;
        } else if (typeof window.selectedProjectId !== 'undefined') {
            projectId = parseInt(window.selectedProjectId, 10) || 0;
        }

        const staffArr = (window.projectStaffMap && window.projectStaffMap[projectId])
            ? window.projectStaffMap[projectId]
            : (window.staffList || []);
        renderStaffDropdown(staffArr);
    }

    if (typeof window.resetUserProfileCustomFieldsForm === 'function') {
        window.resetUserProfileCustomFieldsForm();
    }

    updateCreateButtonState({ showErrors: false });
}

function syncRichEditorToForm(form) {
    if (!form) return;
    const textarea = form.querySelector('textarea[name="description"], textarea[name="task_description"]');
    if (!textarea) return;
    const container = textarea.parentNode ? textarea.parentNode.querySelector('.rich-editor-container') : null;
    if (!container) return;
    const editable = container.querySelector('.rich-editor-content, [contenteditable="true"]');
    if (editable) {
        textarea.value = editable.innerHTML;
    }
}

function submitAddTaskViaAjax(form) {
    syncRichEditorToForm(form);
    const action = form.getAttribute('action');
    const url = (!action || action === '#') ? window.location.href.split('#')[0] : action;

    return fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        },
        body: new FormData(form),
        credentials: 'same-origin'
    }).then(function (response) {
        return response.text().then(function (text) {
            let data = null;
            try {
                data = text ? JSON.parse(text) : null;
            } catch (err) {
                data = null;
            }
            return { ok: response.ok, status: response.status, data: data, raw: text };
        });
    });
}

function getTaskCreateAjaxErrors(result) {
    if (result.data && Array.isArray(result.data.errors) && result.data.errors.length) {
        return result.data.errors;
    }
    if (result.raw && /<\/html>/i.test(result.raw)) {
        return ['Unexpected server response. Please refresh the page and try again.'];
    }
    return ['Task could not be created. Please try again.'];
}

function disposeComonSuccessModalTooltips(container) {
    if (!container || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
        return;
    }
    container.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        const instance = bootstrap.Tooltip.getInstance(el);
        if (instance) {
            instance.dispose();
        }
    });
}

function initComonSuccessModalTooltips(container) {
    if (!container || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) {
        return;
    }
    container.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        bootstrap.Tooltip.getOrCreateInstance(el, {
            placement: 'top',
            trigger: 'hover focus',
            container: container.closest('.modal') || 'body'
        });
    });
}

function showTaskCreatedSuccessModal(options) {
    const modalEl = document.getElementById('comonSuccessModal');
    if (!modalEl) {
        return;
    }

    const cfg = window.comonSuccessModalConfig || {};
    const opts = options || {};
    const titleEl = modalEl.querySelector('.comon-success-modal__title');
    const subtitleEl = modalEl.querySelector('.comon-success-modal__subtitle');
    const assigneesEl = modalEl.querySelector('.comon-success-modal__assignees');
    const viewBtn = modalEl.querySelector('.comon-success-modal__btn-view');
    const kanbanBtn = modalEl.querySelector('.comon-success-modal__btn-kanban');

    if (titleEl) {
        titleEl.textContent = opts.title || cfg.titleDefault || 'Your task is created';
    }
    if (subtitleEl) {
        subtitleEl.textContent = opts.subtitle || '';
        subtitleEl.classList.toggle('d-none', !(opts.subtitle || ''));
    }

    if (assigneesEl) {
        disposeComonSuccessModalTooltips(assigneesEl);
        assigneesEl.innerHTML = '';
        const assignees = Array.isArray(opts.assignees) ? opts.assignees : [];
        if (!assignees.length) {
            assigneesEl.classList.add('d-none');
        } else {
            assigneesEl.classList.remove('d-none');
            assignees.forEach(function (assignee) {
                const wrap = document.createElement('div');
                wrap.className = 'comon-success-modal__assignee avatar-overlap';
                const assigneeName = assignee.name || '';
                if (assigneeName) {
                    wrap.setAttribute('data-bs-toggle', 'tooltip');
                    wrap.setAttribute('data-bs-placement', 'top');
                    wrap.setAttribute('title', assigneeName);
                    wrap.setAttribute('aria-label', assigneeName);
                }
                wrap.innerHTML = assignee.avatar_html || assignee.avatarHtml || '';
                assigneesEl.appendChild(wrap);
            });
        }
    }

    if (viewBtn) {
        viewBtn.textContent = opts.viewTaskLabel || cfg.viewTaskLabel || 'View task';
        viewBtn.onclick = function () {
            if (window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }
            if (typeof opts.onViewTask === 'function') {
                opts.onViewTask();
            } else if (opts.taskId && typeof window.openTaskSidebar === 'function') {
                window.openTaskSidebar(parseInt(opts.taskId, 10));
            } else if (opts.taskUrl) {
                window.location.href = opts.taskUrl;
            }
        };
    }

    if (kanbanBtn) {
        kanbanBtn.textContent = opts.goToKanbanLabel || cfg.goToKanbanLabel || 'Go to kanban';
        kanbanBtn.href = opts.kanbanUrl || 'kanban.php';
    }

    if (!window.bootstrap || !window.bootstrap.Modal) {
        return;
    }

    const modalInstance = window.bootstrap.Modal.getOrCreateInstance(modalEl);
    const onModalShown = function () {
        if (assigneesEl) {
            initComonSuccessModalTooltips(assigneesEl);
        }
        modalEl.removeEventListener('shown.bs.modal', onModalShown);
    };
    modalEl.addEventListener('shown.bs.modal', onModalShown);
    modalInstance.show();
}

function handleTaskCreatedSuccess(form, payload) {
    resetAddTaskFormAfterSuccess(form);
    restoreSubmitButton(form);

    const task = payload && payload.task ? payload.task : {};
    showTaskCreatedSuccessModal({
        subtitle: task.title || '',
        assignees: payload.assignees || [],
        taskId: task.id || 0,
        taskUrl: payload.task_url || '',
        kanbanUrl: payload.kanban_url || 'kanban.php',
        onViewTask: function () {
            if (task.id && typeof window.openTaskSidebar === 'function') {
                window.openTaskSidebar(parseInt(task.id, 10));
            } else if (payload.task_url) {
                window.location.href = payload.task_url;
            }
        }
    });
}

window.showTaskCreatedSuccessModal = showTaskCreatedSuccessModal;

(function () {
    function openTaskFromUrlParam() {
        var params = new URLSearchParams(window.location.search);
        var openTask = parseInt(params.get('openTask') || '0', 10);
        if (openTask > 0 && typeof window.openTaskSidebar === 'function') {
            window.openTaskSidebar(openTask);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', openTaskFromUrlParam);
    } else {
        openTaskFromUrlParam();
    }
})();

/**
 * Initialize all-tasks page specific functionality
 */
function initializeAllTasksFunctionality() {
    // Only initialize if we're on the all-tasks page
    const tasksTable = document.getElementById('tasks-table');
    if (!tasksTable) {
        return;
    }
    
    // Initialize bulk delete functionality (legacy form — skip when kanban toolbar is present)
    if (!document.getElementById('kanbanBulkToolbar')) {
        initializeBulkDelete();
    }
    
    // Initialize search functionality
    initializeTaskSearch();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize checkbox functionality (legacy — skip when kanban toolbar handles bulk select)
    if (!document.getElementById('kanbanBulkToolbar')) {
        initializeTaskCheckboxes();
    }
    
    // Initialize search toggle
    initializeSearchToggle();

    initializeAllTasksStatusDropdowns();
}

function initializeAllTasksStatusDropdowns() {
    const table = document.getElementById('tasks-table');
    if (!table || table.dataset.allTasksStatusBound === '1') {
        return;
    }
    table.dataset.allTasksStatusBound = '1';

    const badgeClasses = {
        todo: 'todo todo-bg-op',
        inprogress: 'inprogress inprogress-bg-op',
        review: 'review review-bg-op',
        done: 'done review done-bg-op'
    };
    const dotClasses = {
        todo: 'color-todo',
        inprogress: 'color-inprogress',
        review: 'color-review',
        done: 'color-done'
    };

    table.addEventListener('click', function (e) {
        const option = e.target.closest('.all-tasks-status-option');
        if (!option) {
            return;
        }
        e.preventDefault();
        const taskId = option.getAttribute('data-task-id');
        const newStatus = option.getAttribute('data-status');
        if (!taskId || !newStatus || typeof kanbanPostBoardUpdate !== 'function') {
            return;
        }
        kanbanPostBoardUpdate({ id: parseInt(taskId, 10), status: newStatus })
            .then(function (data) {
                if (!data || data.status !== 'ok') {
                    throw new Error((data && data.error) ? data.error : 'Status update failed');
                }
                const row = table.querySelector('tr[data-task-id="' + taskId + '"]');
                if (!row) {
                    return;
                }
                row.setAttribute('data-status', newStatus);
                const btn = row.querySelector('.all-tasks-status-pill');
                if (btn) {
                    btn.className = 'badge status all-tasks-status-pill dropdown-toggle ' + (badgeClasses[newStatus] || badgeClasses.todo);
                    const dot = btn.querySelector('.dots');
                    if (dot) {
                        dot.className = 'dots ' + (dotClasses[newStatus] || dotClasses.todo);
                    }
                    const labelEl = btn.querySelector('.all-tasks-status-label');
                    if (labelEl) {
                        const nextLabel = option.textContent.trim();
                        labelEl.textContent = nextLabel;
                        labelEl.setAttribute('title', nextLabel);
                    }
                }
                const progressCell = row.querySelector('td.tbl-tasks');
                if (progressCell && !progressCell.querySelector('.mb-1')?.textContent.includes('/')) {
                    const percentMap = { todo: 0, inprogress: 40, review: 60, done: 100 };
                    const percent = percentMap[newStatus] ?? 0;
                    const color = percent < 30 ? '#f66' : (percent < 70 ? '#f9b233' : '#4caf50');
                    const pctEl = progressCell.querySelector('.flex-grow');
                    if (pctEl) {
                        pctEl.textContent = percent + '%';
                    }
                    const bar = progressCell.querySelector('.progress-bar');
                    if (bar) {
                        bar.style.width = percent + '%';
                        bar.style.background = color;
                        bar.setAttribute('aria-valuenow', String(percent));
                    }
                }
            })
            .catch(function (err) {
                if (typeof showNotification === 'function') {
                    showNotification(err.message || 'Error updating task status', 'error');
                }
            });
    });
}

/**
 * Initialize bulk delete functionality
 */
function initializeBulkDelete() {
    const bulkDeleteForms = document.querySelectorAll('#bulk-delete-form, #bulk-delete-form-mobile');
    
    bulkDeleteForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const taskCount = form.querySelector('.bulk-task-ids').value.split(',').length;
            
            if (!confirm(`Are you sure you want to delete ${taskCount} selected task(s)? This action cannot be undone.`)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Initialize task search functionality
 */
function initializeTaskSearch() {
    const taskSearchInput = document.getElementById('task-search');
    if (taskSearchInput) {
        taskSearchInput.addEventListener('keyup', function() {
            const input = this;
            const filter = input.value.toUpperCase();
            const table = document.getElementById('tasks-table');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 0; i < tr.length; i++) {
                if (i === 0) continue; // Skip header row
                
                // Search in title and description
                const td = tr[i].getElementsByTagName('td')[1];
                if (td) {
                    const txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = '';
                    } else {
                        // Also check project name
                        const projectTd = tr[i].getElementsByTagName('td')[2];
                        const projectTxtValue = projectTd.textContent || projectTd.innerText;
                        if (projectTxtValue.toUpperCase().indexOf(filter) > -1) {
                            tr[i].style.display = '';
                        } else {
                            tr[i].style.display = 'none';
                        }
                    }
                }
            }
        });
    }
}

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

/**
 * Initialize task checkbox functionality
 */
function initializeTaskCheckboxes() {
    const selectAllCheckbox = document.getElementById('select-all-tasks');
    const taskCheckboxes = document.querySelectorAll('.task-checkbox');
    const bulkDeleteButtons = document.querySelectorAll('#bulk-delete-form button, #bulk-delete-form-mobile button');
    const bulkTaskIdsInputs = document.querySelectorAll('.bulk-task-ids');
    const pmTrashElements = document.querySelectorAll('.pm-trash');
    
    if (!selectAllCheckbox || taskCheckboxes.length === 0) {
        return;
    }
    
    // Handle select all checkbox
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        
        taskCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
        });
        
        updateBulkDeleteButton();
    });
    
    // Handle individual checkboxes
    taskCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBulkDeleteButton();
            
            // Update select all checkbox
            const allChecked = Array.from(taskCheckboxes).every(cb => cb.checked);
            const anyChecked = Array.from(taskCheckboxes).some(cb => cb.checked);
            
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = anyChecked && !allChecked;
        });
    });
    
    // Update bulk delete button state and task ids
    function updateBulkDeleteButton() {
        const selectedIds = [];
        
        taskCheckboxes.forEach(checkbox => {
            if (checkbox.checked) {
                selectedIds.push(checkbox.value);
            }
        });
        
        const hasSelection = selectedIds.length > 0;
        
        // Update button state
        bulkDeleteButtons.forEach(button => {
            button.disabled = !hasSelection;
        });
        
        // Add/remove active class to pm-trash elements
        pmTrashElements.forEach(element => {
            if (hasSelection) {
                element.classList.add('active');
            } else {
                element.classList.remove('active');
            }
        });
        
        // Update hidden input values
        bulkTaskIdsInputs.forEach(input => {
            input.value = selectedIds.join(',');
        });
    }
}

/**
 * Initialize search toggle functionality
 */
function initializeSearchToggle() {
    // Close search when clicking outside
    document.addEventListener("click", function(event) {
        const searchForm = document.getElementById("searchForm");
        const searchIcon = document.querySelector(".search-icon");
        if (searchForm && searchIcon && !searchForm.contains(event.target) && !searchIcon.contains(event.target)) {
            searchForm.classList.remove("expanded");
        }
    });
}

/**
 * Toggle search form expansion
 */
function toggleSearch() {
    const searchForm = document.getElementById("searchForm");
    if (searchForm) {
        searchForm.classList.toggle("expanded");
        if (searchForm.classList.contains("expanded")) {
            const searchInput = searchForm.querySelector("input[type=text]");
            if (searchInput) {
                searchInput.focus();
            }
        }
    }
}

// Make toggleSearch globally available
window.toggleSearch = toggleSearch;

/**
 * Initialize Bootstrap dropdowns for all pages
 */
function initializeBootstrapDropdowns() {
    if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
        const dropdownElements = document.querySelectorAll('[data-bs-toggle="dropdown"]');
        dropdownElements.forEach(element => {
            new bootstrap.Dropdown(element);
        });
    }
}

/**
 * Initialize project dropdown functionality (add task only)
 */
function initializeProjectDropdown() {
    
    renderProjectList();

    // Preselect project if available
    const selectedProject = allProjects.find(p => p.id === selectedProjectId);
    if (selectedProject) {
        const projectBtnText = document.getElementById('projectDropdownBtnText');
        const selectedProjectInput = document.getElementById('selectedProjectInput');
        if (projectBtnText && selectedProjectInput) {
            projectBtnText.textContent = selectedProject.name;
            selectedProjectInput.value = selectedProject.id;
        }
    }

    // Filter projects
    const projectSearchInput = document.getElementById('projectSearchInput');
    if (projectSearchInput) {
        projectSearchInput.addEventListener('input', function() {
            renderProjectList(this.value);
        });
    }

    // Handle project selection
    const projectList = document.getElementById('projectList');
    if (projectList) {
        projectList.addEventListener('click', function(e) {
            e.preventDefault();
            const a = e.target.closest('a[data-id]');
            if (!a) return;
            
            const id = parseInt(a.getAttribute('data-id'));
            const name = a.textContent;
            
            const projectBtnText = document.getElementById('projectDropdownBtnText');
            const selectedProjectInput = document.getElementById('selectedProjectInput');
            
            if (projectBtnText && selectedProjectInput) {
                projectBtnText.textContent = name;
                selectedProjectInput.value = id;
                
                // Close dropdown (Bootstrap 5)
                const dropdownBtn = document.getElementById('projectDropdownBtn');
                if (dropdownBtn) {
                    const dropdownInstance = bootstrap.Dropdown.getOrCreateInstance(dropdownBtn);
                    dropdownInstance.hide();
                }
                
                // Update staff dropdown
                selectedStaff = [];
                updateSelectedStaff();
                renderStaffDropdown(projectStaffMap[id] || []);
            }
        });
    }
}

/**
 * Initialize staff dropdown functionality
 */
function initializeStaffDropdown() {
    const staffMenu = document.getElementById('staffDropdownMenu');
    const staffBtn = document.getElementById('staffDropdownBtn');
    const staffBtnText = document.getElementById('staffDropdownBtnText');
    const selectedStaffInput = document.getElementById('selectedStaffInput');
    
    if (!staffMenu || !staffBtn || !staffBtnText || !selectedStaffInput) {
        return;
    }
    
    // Initialize with preselected staff (edit mode)
    if (isEditMode && selectedStaffIds && Array.isArray(selectedStaffIds)) {
        selectedStaff = selectedStaffIds.map(id => {
            const staff = staffList.find(staff => String(staff.id) === String(id));
            if (staff) {
                return {
                    id: staff.id,
                    name: staff.name,
                    image: staff.image,
                    accountStatus: staff.accountStatus
                };
            }
            return null;
        }).filter(Boolean);
    }
    
    // Initialize staff dropdown with current project's staff (add mode) or all staff (edit mode)
    if (isEditMode) {
        renderStaffDropdown(staffList);
    } else {
        renderStaffDropdown(projectStaffMap[selectedProjectId] || []);
    }

    function getCurrentStaffList() {
        return isEditMode ? staffList : (projectStaffMap[selectedProjectId] || []);
    }

    function ensureSelectedStaffEntryById(userId) {
        const currentStaffList = getCurrentStaffList();
        const staffObj = currentStaffList.find(s => String(s.id) === String(userId));
        if (!staffObj) {
            return;
        }
        const idx = selectedStaff.findIndex(s => String(s.id) === String(userId));
        if (idx === -1) {
            selectedStaff.push({
                id: String(staffObj.id),
                name: staffObj.name,
                image: staffObj.image,
                accountStatus: staffObj.accountStatus
            });
        }
    }

    // Handle staff / group selection-deselection
    staffMenu.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        document.body.dataset.staffTouched = 'true';
        
        const groupEl = e.target.closest('a[data-group-id]');
        if (groupEl) {
            const groupUsersRaw = (groupEl.getAttribute('data-user-ids') || '').trim();
            const groupUserIds = groupUsersRaw
                .split(',')
                .map(v => v.trim())
                .filter(v => v !== '' && v !== '0');

            if (groupUserIds.length > 0) {
                const allSelected = groupUserIds.every(uid =>
                    selectedStaff.some(s => String(s.id) === String(uid))
                );

                if (allSelected) {
                    selectedStaff = selectedStaff.filter(s => !groupUserIds.includes(String(s.id)));
                } else {
                    groupUserIds.forEach(uid => ensureSelectedStaffEntryById(uid));
                }
                updateSelectedStaff();
                renderStaffDropdown(getCurrentStaffList());
            }

            e.stopImmediatePropagation();
            return;
        }

        const a = e.target.closest('a[data-id]');
        if (!a) {
            return;
        }
        
        const id = a.getAttribute('data-id');
        const nameElement = a.querySelector('span');
        const name = nameElement ? nameElement.textContent : '';
        const imgDiv = a.querySelector('div');
        const img = imgDiv ? imgDiv.innerHTML : '';

        // Find the staff object from the current staff list
        const currentStaffList = getCurrentStaffList();
        const staffObj = currentStaffList.find(s => String(s.id) === String(id));

        const idx = selectedStaff.findIndex(s => String(s.id) === String(id));
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

        if (document.querySelector('form[data-task-page="bulk"]')) {
            const gidEl = document.getElementById('bulkStaffTeamGroupId');
            if (gidEl) gidEl.value = '';
            const rm = document.getElementById('assignModeMembers');
            if (rm) {
                rm.checked = true;
                rm.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        
        // Prevent dropdown from closing
        e.stopImmediatePropagation();
    });

    updateSelectedStaff(); // Ensure preselected staff are shown in the button
    
    // Permission-based functionality (edit mode only)
    if (isEditMode) {
        const canAssignMembers = window.canAssignMembers || false;
        if (!canAssignMembers) {
            staffBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
            staffMenu.style.pointerEvents = 'none';
        }
    }
}

/**
 * Initialize TinyMCE editor
 */
function initializeRichEditor() {
    // Try multiple times to initialize Rich Editor
    let attempts = 0;
    const maxAttempts = 5;
    
    function tryInitRichEditor() {
        attempts++;
        
        if (typeof RichEditor !== 'undefined') {
            try {
                // Handle different textarea names for different pages
                let selector;
                if (isEditMode) {
                    // Check if it's admin edit (name="description") or staff edit (name="task_description")
                    const adminTextarea = document.querySelector('textarea[name="description"]');
                    const staffTextarea = document.querySelector('textarea[name="task_description"]');
                    selector = staffTextarea ? 'textarea[name="task_description"]' : 'textarea[name="description"]';
                } else {
                    // Check if it's admin add (name="description") or client add (name="task_description")
                    const adminTextarea = document.querySelector('textarea[name="description"]');
                    const clientTextarea = document.querySelector('textarea[name="task_description"]');
                    selector = clientTextarea ? 'textarea[name="task_description"]' : 'textarea[name="description"]';
                }
                
                // Initialize custom rich editor
                new RichEditor(selector, {
                    height: 300,
                    placeholder: 'Start typing...'
                });
            } catch (error) {
                console.error('Rich editor initialization error', error);
            }
        } else if (attempts < maxAttempts) {
            setTimeout(tryInitRichEditor, 200);
        }
    }
    
    // Start the initialization process
    setTimeout(tryInitRichEditor, 100);
}

/**
 * Initialize form submission handling
 */
function initializeFormSubmission() {
    // Bind to the task form specifically (the one that contains the create button / date inputs).
    const form =
        (document.getElementById('create-task-btn') && document.getElementById('create-task-btn').closest('form')) ||
        (document.getElementById('edit-task-btn') && document.getElementById('edit-task-btn').closest('form')) ||
        (document.getElementById('start_date') && document.getElementById('start_date').closest('form')) ||
        (document.getElementById('due_date') && document.getElementById('due_date').closest('form')) ||
        document.querySelector('form');
    if (form) {
        // Initialize date restrictions
        initializeDateRestrictions();

        // Initial state (no disabling; just keep errors hidden)
        updateCreateButtonState({ showErrors: false });

        const startDateInput = document.getElementById('start_date') || document.getElementById('bulk_start_date');
        const dueDateInput = document.getElementById('due_date') || document.getElementById('bulk_due_date');
        const titleInput = form.querySelector('input[name="title"]') || form.querySelector('input[name="task_title"]');

        if (titleInput) {
            titleInput.addEventListener('input', function() {
                titleInput.dataset.touched = 'true';
                updateCreateButtonState({ showErrors: false });
            });
            titleInput.addEventListener('change', function() {
                titleInput.dataset.touched = 'true';
                updateCreateButtonState({ showErrors: false });
            });
        }

        if (startDateInput) {
            startDateInput.addEventListener('input', function() {
                startDateInput.dataset.touched = 'true';
                updateCreateButtonState({ showErrors: false });
            });
            startDateInput.addEventListener('change', function() {
                startDateInput.dataset.touched = 'true';
                updateCreateButtonState({ showErrors: false });
            });
        }

        if (dueDateInput) {
            dueDateInput.addEventListener('input', function() {
                dueDateInput.dataset.touched = 'true';
                updateCreateButtonState({ showErrors: false });
            });
            dueDateInput.addEventListener('change', function() {
                dueDateInput.dataset.touched = 'true';
                updateCreateButtonState({ showErrors: false });
            });
        }
        
        form.addEventListener('submit', function(e) {
            // Mark everything as touched and show errors if invalid
            document.body.dataset.staffTouched = 'true';
            if (titleInput) titleInput.dataset.touched = 'true';
            if (startDateInput) startDateInput.dataset.touched = 'true';
            if (dueDateInput) dueDateInput.dataset.touched = 'true';
            const isValidRequired = updateCreateButtonState({ showErrors: true });

            if (!isValidRequired) {
                e.preventDefault();
                return false;
            }

            // Validate dates before submission
            if (!validateDates()) {
                e.preventDefault();
                updateCreateButtonState({ showErrors: true });
                return false;
            }

            // Show spinner only on valid submission + prevent double submit
            if (!showSubmitButtonSpinner(form)) {
                // If spinner couldn't be shown for some reason, still prevent accidental double submit
                if (form.dataset.submitting === 'true') {
                    e.preventDefault();
                    return false;
                }
            }

            if (usesTaskCreatedModal(form)) {
                e.preventDefault();
                submitAddTaskViaAjax(form)
                    .then(function (result) {
                        if (result.data && result.data.success) {
                            handleTaskCreatedSuccess(form, result.data);
                            return;
                        }
                        showTaskCreateFormErrors(form, getTaskCreateAjaxErrors(result));
                        restoreSubmitButton(form);
                    })
                    .catch(function () {
                        showTaskCreateFormErrors(form, ['Network error. Please try again.']);
                        restoreSubmitButton(form);
                    });
                return false;
            }
            // Rich editor automatically saves content to textarea
            // No additional action needed
        });
    }
}

/**
 * Initialize date restrictions to prevent past dates
 */
function initializeDateRestrictions() {
    const startDateInput = document.getElementById('start_date') || document.getElementById('bulk_start_date');
    const dueDateInput = document.getElementById('due_date') || document.getElementById('bulk_due_date');
    
    if (!startDateInput || !dueDateInput) {
        return;
    }
    
    const today = new Date().toISOString().split('T')[0];
    const startLocked = !!startDateInput.disabled;
    
    // Ensure min attribute is set (in case HTML didn't set it)
    // - Add mode: prevent past dates
    // - Edit mode with locked start: allow historical dates; enforce due >= start instead
    if (!startLocked && !startDateInput.getAttribute('min')) {
        startDateInput.setAttribute('min', today);
    }
    if (!dueDateInput.getAttribute('min')) {
        // If start is locked and present, allow overdue dates but not before start
        const currentStartVal = startDateInput.value;
        dueDateInput.setAttribute('min', (startLocked && currentStartVal) ? currentStartVal : today);
    }
    
    // Set due-date min based on current start date (if present)
    const currentStart = startDateInput.value;
    if (currentStart) {
        // If start is locked (edit), due must be at least start (even if start is in the past).
        // Otherwise, due must be at least today or start (whichever is later).
        dueDateInput.setAttribute('min', startLocked ? currentStart : (currentStart > today ? currentStart : today));
    }

    // Update due date minimum when start date changes (add/edit where start is editable)
    startDateInput.addEventListener('change', function() {
        const startDate = this.value;
        if (startDate) {
            // Due date should be at least the start date
            dueDateInput.setAttribute('min', startLocked ? startDate : (startDate > today ? startDate : today));
            
            // If due date is before start date, update it
            if (dueDateInput.value && dueDateInput.value < startDate) {
                dueDateInput.value = startDate;
            }
        } else {
            // If start date is cleared, reset due date min to today
            dueDateInput.setAttribute('min', today);
        }
    });
    
    // Validate due date when it changes
    dueDateInput.addEventListener('change', function() {
        const dueDate = this.value;
        const startDate = startDateInput.value;
        
        if (dueDate && startDate && dueDate < startDate) {
            this.value = startDate;
        }
    });
}

/**
 * Validate dates before form submission
 * @returns {boolean} - True if dates are valid, false otherwise
 */
function validateDates() {
    const startDateInput = document.getElementById('start_date') || document.getElementById('bulk_start_date');
    const dueDateInput = document.getElementById('due_date') || document.getElementById('bulk_due_date');
    
    if (!startDateInput || !dueDateInput) {
        return true; // No date inputs, validation not needed
    }

    if (isBulkTaskForm()) {
        const sv = startDateInput.value || '';
        const dv = dueDateInput.value || '';
        if (!sv && !dv) return true;
        if (sv && dv) {
            const sd = parseYmdDate(sv);
            const dd = parseYmdDate(dv);
            return sd && dd && dd >= sd;
        }
        return true;
    }
    
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const startLocked = !!startDateInput.disabled;
    
    // Helper function to parse and normalize date
    const parseDate = (dateString) => {
        if (!dateString) return null;
        const date = new Date(dateString);
        date.setHours(0, 0, 0, 0);
        return date;
    };
    
    const startDate = parseDate(startDateInput.value);
    const dueDate = parseDate(dueDateInput.value);
    
    // Validate start date is not in the past (skip if start date is locked/disabled on edit)
    if (!startLocked && startDate && startDate < today) {
        return false;
    }
    
    // Validate due date is not in the past (skip in edit mode: overdue tasks can be edited)
    if (!isEditMode && dueDate && dueDate < today) {
        return false;
    }
    
    // Validate due date is not before start date
    if (startDate && dueDate && dueDate < startDate) {
        return false;
    }
    
    return true;
}

/**
 * Render project list with optional filtering
 * @param {string} filter - Optional filter string
 */
function renderProjectList(filter = '') {
    const ul = document.getElementById('projectList');
    if (!ul) {
        return;
    }
    
    ul.innerHTML = '';
    const filteredProjects = allProjects.filter(p => p.name.toLowerCase().includes(filter.toLowerCase()));
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    filteredProjects.forEach(p => {
        const li = document.createElement('li');
        li.innerHTML = `<a href="#" class="dropdown-item" data-id="${p.id}">${escapeHtml(p.name)}</a>`;
        ul.appendChild(li);
    });
}

/**
 * Render staff dropdown with provided staff array
 * @param {Array} staffArr - Array of staff objects
 */
function renderStaffDropdown(staffArr) {
    const staffMenu = document.getElementById('staffDropdownMenu');
    if (!staffMenu) {
        return;
    }

    function getInitial(name) {
        const s = String(name || '').trim();
        return s ? s.charAt(0).toUpperCase() : 'G';
    }

    function getColorIndex(seedText) {
        const s = String(seedText || '');
        let hash = 0;
        for (let i = 0; i < s.length; i++) {
            hash = (hash + s.charCodeAt(i) * (i + 1)) % 8;
        }
        return hash + 1;
    }

    function filteredGroupsForStaff() {
        const staffIdSet = new Set((staffArr || []).map(s => String(s.id)));
        const groups = Array.isArray(projectTeamGroupList) ? projectTeamGroupList : [];
        return groups.map(g => {
            const users = Array.isArray(g.user_ids) ? g.user_ids.map(id => String(id)) : [];
            const filteredUsers = users.filter(uid => staffIdSet.has(uid));
            return {
                id: g.id,
                name: g.name,
                user_ids: filteredUsers
            };
        }).filter(g => g.user_ids.length > 0);
    }

    function rowSearchText(name, extra) {
        return String((name || '') + ' ' + (extra || '')).toLowerCase();
    }

    staffMenu.innerHTML = '';

    const searchLi = document.createElement('li');
    searchLi.className = 'px-3 py-2 border-bottom';
    searchLi.innerHTML = '<input type="text" class="form-control w-100" id="taskStaffSearchInput" placeholder="Search" autocomplete="off">';
    staffMenu.appendChild(searchLi);

    const groups = filteredGroupsForStaff();
    if (groups.length > 0) {
        const groupsLabel = document.createElement('li');
        groupsLabel.className = 'dropdown-header text-uppercase fw-bold text-muted small';
        groupsLabel.textContent = 'Team groups';
        staffMenu.appendChild(groupsLabel);

        groups.forEach(group => {
            const li = document.createElement('li');
            li.className = 'task-staff-item';
            li.setAttribute('data-search', rowSearchText(group.name, 'team group'));

            const colorIdx = getColorIndex(group.name || group.id);
            const initial = getInitial(group.name);
            const allSelected = group.user_ids.every(uid => selectedStaff.some(s => String(s.id) === String(uid)));
            const activeClass = allSelected ? ' active' : '';

            li.innerHTML = `
                <a href="#" class="dropdown-item d-flex align-items-center${activeClass}" data-group-id="${group.id}" data-user-ids="${group.user_ids.join(',')}">
                    <div class="me-2"><div class="avatar-initials color-${colorIdx} avatar-initials-small rounded-circle">${initial}</div></div>
                    <span>${group.name}</span>
                </a>
            `;
            staffMenu.appendChild(li);
        });
    }

    const staffLabel = document.createElement('li');
    staffLabel.className = 'dropdown-header text-uppercase fw-bold text-muted small';
    staffLabel.textContent = 'Staff and admins';
    staffMenu.appendChild(staffLabel);

    staffArr.forEach(staff => {
        const li = document.createElement('li');
        li.className = 'task-staff-item';
        li.setAttribute('data-search', rowSearchText(staff.name, staff.accountStatus === 1 ? 'admin' : 'staff'));
        // Check if staff.image is an HTML element (initials) or a URL (profile picture)
        const isImageUrl = staff.image.startsWith('http') || staff.image.startsWith('/');
        const avatarHtml = isImageUrl 
            ? `<img src="${staff.image}" class="rounded-circle me-2" style="width:28px;height:28px;object-fit:cover;">`
            : staff.image;
        
        // Add admin badge for admin users (accountStatus == 1)
        const adminBadge = staff.accountStatus === 1 ? '<span class="badge color-inprogress inprogress-bg-op ms-2 align-self-start">ADMIN</span>' : '';
        
        li.innerHTML = `
            <a href="#" class="dropdown-item d-flex align-items-center" data-id="${staff.id}">
                <div class="me-2">${avatarHtml}</div>
                <span>${staff.name}</span>
                ${adminBadge}
            </a>
        `;
        staffMenu.appendChild(li);
    });

    const searchInput = document.getElementById('taskStaffSearchInput');
    if (searchInput) {
        searchInput.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        searchInput.addEventListener('input', function() {
            const q = String(searchInput.value || '').trim().toLowerCase();
            const rows = staffMenu.querySelectorAll('li.task-staff-item');
            rows.forEach(row => {
                const hay = row.getAttribute('data-search') || '';
                row.style.display = !q || hay.includes(q) ? '' : 'none';
            });
        });
    }
    
}

/**
 * Update the selected staff display
 */
function updateSelectedStaff() {
    const staffBtnText = document.getElementById('staffDropdownBtnText');
    const selectedStaffInput = document.getElementById('selectedStaffInput');
    
    if (!staffBtnText || !selectedStaffInput) {
        return;
    }
    
    selectedStaffInput.value = selectedStaff.map(s => s.id).join(',');
    
    if (selectedStaff.length) {
        const staffHtml = selectedStaff.map(s => {
            const isImageUrl = s.image.startsWith('http') || s.image.startsWith('/');
            const avatarHtml = isImageUrl 
                ? `<img src="${s.image}" class="rounded-circle me-1" style="width:24px;height:24px;object-fit:cover;">`
                : s.image.replace('width:28px;height:28px', 'width:24px;height:24px').replace('me-2', 'me-1');
            
            // Add admin badge for admin users (accountStatus == 1)
            const adminBadge = s.accountStatus === 1 ? '<span class="badge color-inprogress inprogress-bg-op">ADMIN</span>' : '';
            
            return `
                <div class="d-inline-flex align-items-center active-user">
                    <div>${avatarHtml}</div>
                    <span class="ms-1">${s.name}</span>
                    ${adminBadge}
                    <button type="button" class="btn-close btn-close-sm ms-2" aria-label="Remove" 
                            onclick="removeStaff('${s.id}')" style="font-size: 8px; padding: 1px;">×</button>
                </div>
            `;
        }).join('');
        
        staffBtnText.innerHTML = staffHtml;
    } else {
        staffBtnText.textContent = 'Select Staff members';
    }

    // Update Create button state whenever staff changes (add task page)
    updateCreateButtonState({ showErrors: false });
}

/**
 * Remove staff member from selection (global function)
 * @param {string} staffId - ID of staff member to remove
 */
window.removeStaff = function(staffId) {
    const idx = selectedStaff.findIndex(s => String(s.id) === String(staffId));
    if (idx !== -1) {
        selectedStaff.splice(idx, 1);
        updateSelectedStaff();
    }
};

/**
 * Set project staff map data
 * @param {Object} data - Project staff mapping data
 */
function setProjectStaffMap(data) {
    projectStaffMap = data;
}

/**
 * Set all projects data
 * @param {Array} data - All projects array
 */
function setAllProjects(data) {
    allProjects = data;
}

/**
 * Set selected project ID
 * @param {number} id - Selected project ID
 */
function setSelectedProjectId(id) {
    selectedProjectId = id;
}

/**
 * Set staff list data
 * @param {Array} data - Staff list array
 */
function setStaffList(data) {
    staffList = data;
}

/**
 * Set selected staff IDs (for edit mode)
 * @param {Array} data - Selected staff IDs array
 */
function setSelectedStaffIds(data) {
    selectedStaffIds = data;
}

/**
 * Set edit mode flag
 * @param {boolean} mode - Whether in edit mode
 */
function setEditMode(mode) {
    isEditMode = mode;
}