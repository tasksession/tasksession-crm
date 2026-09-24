/**
 * Sub-Tasks Management JavaScript
 * Handles all sub-task functionality in the task sidebar
 */

// Prevent duplicate loading
if (typeof window.SubTasksManager !== 'undefined') {
} else {

class SubTasksManager {
    constructor() {
        this.currentParentTaskId = null;
        this.subTasks = [];
        this.eventsBound = false;
        this.init();
    }

    init() {
        // Don't bind events immediately - wait for parent task ID to be set
        this.loadSubTasks();
    }

    getAppRoot() {
        if (typeof window.baseUrl === 'string' && window.baseUrl.length > 0) {
            const u = window.baseUrl;
            return u.endsWith('/') ? u : (u + '/');
        }
        const currentPath = window.location.pathname || '';
        if (currentPath.includes('/admin/') || currentPath.includes('/staff/') || currentPath.includes('/client/') || currentPath.includes('/mail/')) {
            return '../';
        }
        return '';
    }

    getApiPath() {
        return this.getAppRoot() + 'ajax/subtasks.php';
    }

    getBaseUrl() {
        return this.getAppRoot();
    }

    /** Reload Activity tab HTML when sidebar is open (server stores time on task_activities.created_at). */
    refreshTaskActivityIfOpen() {
        const tid = this.currentParentTaskId;
        if (!tid || typeof window.loadTaskActivity !== 'function') {
            return;
        }
        const sidebar = document.getElementById('task-sidebar');
        if (sidebar && sidebar.classList.contains('open')) {
            window.loadTaskActivity(parseInt(String(tid), 10));
        }
    }

    /** Align with task-sidebar __subtaskAcl: admin all; staff/client only own sub-tasks + role/TaskPermission. */
    getSubtaskAcl() {
        const a = typeof window.__subtaskAcl === 'object' && window.__subtaskAcl !== null ? window.__subtaskAcl : {};
        return {
            userId: parseInt(String(a.userId || '0'), 10) || 0,
            accountStatus: parseInt(String(a.accountStatus || '0'), 10) || 0,
            mutateAnySubtask: !!a.mutateAnySubtask,
            hasTaskEdit: !!a.hasTaskEdit,
            hasTaskDelete: !!a.hasTaskDelete,
        };
    }

    /** Effective creator for ACL (legacy rows may only have user_id). */
    getSubtaskCreatorId(subtask) {
        let c = parseInt(String(subtask && subtask.creator_id != null ? subtask.creator_id : '0'), 10) || 0;
        if (c <= 0 && subtask && subtask.user_id != null) {
            c = parseInt(String(subtask.user_id), 10) || 0;
        }
        return c;
    }

    canShowSubtaskEdit(subtask) {
        const acl = this.getSubtaskAcl();
        if (acl.mutateAnySubtask) {
            return true;
        }
        const creator = this.getSubtaskCreatorId(subtask);
        if (acl.accountStatus === 2 || acl.accountStatus === 3) {
            return acl.hasTaskEdit && creator > 0 && creator === acl.userId;
        }
        return false;
    }

    canShowSubtaskDelete(subtask) {
        const acl = this.getSubtaskAcl();
        if (acl.mutateAnySubtask) {
            return true;
        }
        const creator = this.getSubtaskCreatorId(subtask);
        if (acl.accountStatus === 2 || acl.accountStatus === 3) {
            return acl.hasTaskDelete && creator > 0 && creator === acl.userId;
        }
        return false;
    }

    labels() {
        const L = typeof window.__subtasksSidebarStrings === 'object' && window.__subtasksSidebarStrings !== null
            ? window.__subtasksSidebarStrings
            : {};
        return {
            selectAssignee: L.selectAssignee || 'Select Assignee',
            cancel: L.cancel || 'Cancel',
            save: L.save || 'Save',
            unassigned: L.unassigned || 'Unassigned',
            dueToday: L.dueToday || 'Due Today',
            dueTomorrow: L.dueTomorrow || 'Due Tomorrow',
            pleaseEnterName: L.pleaseEnterName || 'Please enter a sub-task name',
            descriptionHeading: L.descriptionHeading || 'DESCRIPTION',
            viewDescription: L.viewDescription || 'View description',
            edit: L.edit || 'Edit',
            close: L.close || 'Close',
            editSubtask: L.editSubtask || 'Edit subtask',
            noDescription: L.noDescription || 'No description yet.',
            metaCreated: L.metaCreated || 'Created',
            metaDone: L.metaDone || 'Done',
            dateDash: L.dateDash || '—',
        };
    }

    resetNewSubtaskForm() {
        const nameInput = document.getElementById('new-subtask-name');
        const dateInput = document.getElementById('new-subtask-date');
        const descInput = document.getElementById('new-subtask-description');
        const L = this.labels();
        if (nameInput) nameInput.value = '';
        if (dateInput) {
            dateInput.value = '';
            dateInput.style.display = 'none';
        }
        if (descInput) descInput.value = '';
        this.selectAssignee('', L.selectAssignee, '');
    }

    hideAddPanel() {
        const panel = document.getElementById('subtask-add-panel');
        if (panel) panel.style.display = 'none';
    }

    showAddPanel() {
        const panel = document.getElementById('subtask-add-panel');
        if (panel) panel.style.display = 'block';
    }

    /** Show add form when there are no subtasks; hide when list is non-empty */
    syncAddPanelVisibility() {
        const panel = document.getElementById('subtask-add-panel');
        if (!panel) return;
        if (this.subTasks.length === 0) {
            panel.style.display = 'block';
        } else {
            panel.style.display = 'none';
        }
    }

    updateProgressBar() {
        const total = this.subTasks.length;
        const done = this.subTasks.filter(s =>
            s.status === 'done' || s.status === 'completed' || s.completed === true
        ).length;
        const pct = total === 0 ? 0 : Math.round((done / total) * 100);
        const fill = document.getElementById('subtasksProgressFill');
        const fractionEl = document.getElementById('subtasksProgressText');
        if (fill) {
            fill.style.width = pct + '%';
            fill.setAttribute('aria-valuenow', String(pct));
        }
        if (fractionEl) fractionEl.textContent = total === 0 ? '0/0' : `${done}/${total}`;
    }

    isSubtaskCompleted(subtask) {
        return subtask.status === 'done' || subtask.status === 'completed' || subtask.completed === true;
    }

    bindEvents() {
        const addToggle = document.getElementById('subtask-add-toggle');
        if (addToggle) {
            addToggle.addEventListener('click', (e) => {
                e.preventDefault();
                const panel = document.getElementById('subtask-add-panel');
                if (!panel) return;
                if (this.subTasks.length === 0) {
                    const nameInput = document.getElementById('new-subtask-name');
                    if (nameInput) setTimeout(() => nameInput.focus(), 0);
                    return;
                }
                const isHidden = panel.style.display === 'none' || panel.style.display === '';
                if (isHidden) {
                    this.showAddPanel();
                    const nameInput = document.getElementById('new-subtask-name');
                    if (nameInput) setTimeout(() => nameInput.focus(), 0);
                } else {
                    this.resetNewSubtaskForm();
                    this.hideAddPanel();
                }
            });
        }

        const cancelBtn = document.getElementById('new-subtask-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.resetNewSubtaskForm();
                if (this.subTasks.length > 0) {
                    this.hideAddPanel();
                }
            });
        }

        // Save new sub-task
        const saveBtn = document.getElementById('new-subtask-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.saveNewSubTask();
            });
        }

        // Enter key to save
        const nameInput = document.getElementById('new-subtask-name');
        if (nameInput) {
            nameInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.saveNewSubTask();
                }
            });
        }

        // Custom assignee dropdown
        const assigneeDropdown = document.getElementById('new-subtask-assignee-dropdown');
        const assigneeSelected = document.getElementById('assignee-selected');
        const assigneeOptions = document.getElementById('assignee-options');
        
        if (assigneeDropdown && assigneeSelected && assigneeOptions) {
            assigneeSelected.addEventListener('click', () => {
                const isOpen = assigneeOptions.style.display === 'block';
                assigneeOptions.style.display = isOpen ? 'none' : 'block';
                assigneeSelected.classList.toggle('open', !isOpen);
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!assigneeDropdown.contains(e.target)) {
                    assigneeOptions.style.display = 'none';
                    assigneeSelected.classList.remove('open');
                }
            });
        }

        const subtasksList = document.getElementById('sub-tasks-list');
        if (subtasksList && !this._subtasksListHintDelegated) {
            this._subtasksListHintDelegated = true;
            subtasksList.addEventListener('click', (e) => {
                const wrap = e.target.closest('.subtask-description-wrap');
                if (wrap && subtasksList.contains(wrap)) {
                    e.preventDefault();
                    const item = wrap.closest('.subtask-item');
                    const sid = item && item.dataset ? item.dataset.subtaskId : null;
                    if (sid) this.openSubtaskDescriptionModal(parseInt(sid, 10), false);
                }
            });
            subtasksList.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                const wrap = e.target.closest('.subtask-description-wrap');
                if (!wrap || !subtasksList.contains(wrap)) return;
                e.preventDefault();
                const item = wrap.closest('.subtask-item');
                const sid = item && item.dataset ? item.dataset.subtaskId : null;
                if (sid) this.openSubtaskDescriptionModal(parseInt(sid, 10), false);
            });
        }
    }

    setParentTaskId(taskId) {
        this.currentParentTaskId = taskId;
        this.resetNewSubtaskForm();
        this.hideAddPanel();

        this.loadSubTasks();
        this.loadAssignedUsers();

        if (!this.eventsBound) {
            this.bindEvents();
            this.eventsBound = true;
        }
    }

    expandSection() {
        const content = document.getElementById('sub-tasks-content');
        if (content) {
            content.style.display = 'block';
        }
    }

    async loadSubTasks() {
        if (!this.currentParentTaskId) return;

        try {
            const apiPath = this.getApiPath();
            const response = await fetch(`${apiPath}?parent_task_id=${this.currentParentTaskId}`);
            const data = await response.json();
            
            if (data.status === 'success') {
                this.subTasks = data.subtasks || [];
                this.renderSubTasks();
                this.expandSection();
            }
        } catch (error) {
        }
    }

    async loadAssignedUsers() {
        if (!this.currentParentTaskId) return;

        try {
            const baseUrl = this.getBaseUrl();
            const response = await fetch(`${baseUrl}includes/task_details.php?id=${this.currentParentTaskId}`);
            const data = await response.json();
            
            if (data.status === 'ok' && data.assigned_staff) {
                this.assignedUsers = data.assigned_staff;
                this.populateAssigneeDropdown(data.assigned_staff);
            }
        } catch (error) {
            // Silent fail - assigned users not critical
        }
    }

    populateAssigneeDropdown(assignedStaff) {
        const optionsContainer = document.getElementById('assignee-options');
        const hiddenInput = document.getElementById('new-subtask-assignee');
        if (!optionsContainer || !hiddenInput) return;

        // Clear existing options
        optionsContainer.innerHTML = '';
        
        // Add "Select Assignee" option
        const defaultOption = document.createElement('div');
        defaultOption.className = 'dropdown-option';
        defaultOption.dataset.value = '';
        defaultOption.innerHTML = `
            <span class="option-text">${this.escapeHtml(this.labels().selectAssignee)}</span>
        `;
        defaultOption.addEventListener('click', () => {
            this.selectAssignee('', this.labels().selectAssignee, '');
        });
        optionsContainer.appendChild(defaultOption);
        
        // Add user options
        const fallbackPic = this.getBaseUrl() + 'assets/images/upload-img.jpg';
        assignedStaff.forEach(user => {
            const option = document.createElement('div');
            option.className = 'dropdown-option';
            option.dataset.value = user.id;
            const safeSrc = this.sanitizeUrlForImg(user.image || fallbackPic, fallbackPic);
            const safeName = String(user.name ?? '');
            option.innerHTML = `
                <img src="${this.escapeAttr(safeSrc)}" alt="${this.escapeAttr(safeName)}" class="option-image" onerror="this.src='${this.escapeAttr(fallbackPic)}'">
                <span class="option-text">${this.escapeHtml(safeName)}</span>
            `;
            option.setAttribute('data-bs-toggle', 'tooltip');
            option.setAttribute('data-bs-placement', 'top');
            option.setAttribute('title', safeName);
            option.addEventListener('click', () => {
                this.selectAssignee(user.id, user.name, user.image);
            });
            optionsContainer.appendChild(option);
        });
        
        // Initialize tooltips for dropdown options
        this.initializeDropdownTooltips();
    }

    selectAssignee(userId, userName, userImage) {
        const selectedText = document.querySelector('#assignee-selected .selected-text');
        const hiddenInput = document.getElementById('new-subtask-assignee');
        const optionsContainer = document.getElementById('assignee-options');
        const selectedElement = document.getElementById('assignee-selected');
        
        if (selectedText && hiddenInput) {
            if (userId) {
                const fallbackPic = this.getBaseUrl() + 'assets/images/upload-img.jpg';
                const src = this.sanitizeUrlForImg(userImage || fallbackPic, fallbackPic);
                selectedText.innerHTML = '';
                const img = document.createElement('img');
                img.src = src;
                img.alt = String(userName ?? '');
                img.className = 'selected-image';
                img.onerror = () => { img.src = fallbackPic; };
                const span = document.createElement('span');
                span.textContent = String(userName ?? '');
                selectedText.appendChild(img);
                selectedText.appendChild(span);
            } else {
                selectedText.textContent = this.labels().selectAssignee;
            }
            hiddenInput.value = userId;
        }
        
        if (optionsContainer && selectedElement) {
            optionsContainer.style.display = 'none';
            selectedElement.classList.remove('open');
        }
    }

    renderSubTasks() {
        const container = document.getElementById('sub-tasks-list');
        if (!container) {
            return;
        }

        container.innerHTML = '';

        this.subTasks.forEach(subtask => {
            const subtaskElement = this.createSubTaskElement(subtask);
            container.appendChild(subtaskElement);
        });

        this.updateCount();
        this.syncAddPanelVisibility();

        // Initialize Bootstrap tooltips and dropdowns for the newly rendered sub-tasks
        this.initializeTooltips();
        this.initializeDropdowns();
    }

    initializeTooltips() {
        // Initialize Bootstrap tooltips for sub-task assignee images
        const tooltipTriggerList = document.querySelectorAll('#sub-tasks-list [data-bs-toggle="tooltip"]');
        if (tooltipTriggerList.length > 0 && typeof bootstrap !== 'undefined') {
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }

    initializeDropdownTooltips() {
        // Initialize Bootstrap tooltips for dropdown options
        const dropdownTooltips = document.querySelectorAll('#assignee-options [data-bs-toggle="tooltip"]');
        if (dropdownTooltips.length > 0 && typeof bootstrap !== 'undefined') {
            dropdownTooltips.forEach(tooltipEl => {
                new bootstrap.Tooltip(tooltipEl);
            });
        }
    }

    initializeDropdowns() {
        // Initialize Bootstrap dropdowns for sub-task actions
        const dropdownElements = document.querySelectorAll('#sub-tasks-list .dropdown-toggle, #sub-tasks-list [data-bs-toggle="dropdown"]');
        if (dropdownElements.length > 0 && typeof bootstrap !== 'undefined') {
            dropdownElements.forEach(dropdownEl => {
                new bootstrap.Dropdown(dropdownEl);
            });
        }
    }

    createSubTaskElement(subtask) {
        const div = document.createElement('div');
        div.className = 'subtask-item p-3 mb-2';
        div.dataset.subtaskId = String(subtask.id);

        const isCompleted = this.isSubtaskCompleted(subtask);
        const completedClass = isCompleted ? 'completed' : '';
        const L = this.labels();

        let assigneeAvatar = '';
        let assigneeNameHtml = '';
        if (subtask.assigned_to) {
            const userName = subtask.assigned_user_name || String(subtask.assigned_to);
            assigneeNameHtml = this.escapeHtml(userName);
            const colorIndex = (() => {
                if (!userName) return 1;
                let hash = 0;
                for (let i = 0; i < userName.length; i++) {
                    hash = ((hash << 5) - hash) + userName.charCodeAt(i);
                    hash |= 0;
                }
                return ((hash >>> 0) % 8) + 1;
            })();
            const fallbackPic = this.getBaseUrl() + 'assets/images/upload-img.jpg';
            const safeAvatarSrc = this.sanitizeUrlForImg(subtask.assigned_user_image || '', '');
            if (safeAvatarSrc) {
                assigneeAvatar = `
                    <div class="small-circle d-flex align-items-center justify-content-center" data-bs-toggle="tooltip" data-bs-placement="top" title="${this.escapeAttr(userName)}">
                        <img src="${this.escapeAttr(safeAvatarSrc)}" width="19" height="19" class="img-fluid rounded-circle" alt="${this.escapeAttr(userName)}">
                    </div>
                `;
            } else {
                const initial = this.escapeHtml(userName.trim().charAt(0).toUpperCase());
                assigneeAvatar = `
                    <div class="small-circle d-flex align-items-center justify-content-center" data-bs-toggle="tooltip" data-bs-placement="top" title="${this.escapeAttr(userName)}">
                        <div class="avatar-initials color-${colorIndex} avatar-initials-small rounded-circle">${initial}</div>
                    </div>
                `;
            }
        } else {
            assigneeNameHtml = this.escapeHtml(L.unassigned);
        }

        const duePart = this.formatDueHtml(subtask);

        const createdVal = this.formatSubtaskCalendarDateShort(subtask.created_at);
        const completedVal = this.formatSubtaskCalendarDateShort(subtask.completed_at);
        const createdShown = this.escapeHtml(createdVal || L.dateDash);
        const completedShown = this.escapeHtml(completedVal || L.dateDash);
        const createdLabel = this.escapeHtml(L.metaCreated);
        const doneLabel = this.escapeHtml(L.metaDone);
        const assigneeMetaSuffix = `<span class="subtask-assignee-meta-suffix d-inline-flex flex-wrap align-items-baseline col-gap-10"><span class="subtask-meta-sep" aria-hidden="true">•</span><span class="subtask-meta-part"><strong class="subtask-meta-label">${createdLabel}</strong> <span class="subtask-meta-date">${createdShown}</span></span><span class="subtask-meta-sep" aria-hidden="true">•</span><span class="subtask-meta-part"><strong class="subtask-meta-label">${doneLabel}</strong> <span class="subtask-meta-date">${completedShown}</span></span></span>`;

        const rawDesc = subtask.description != null ? String(subtask.description).trim() : '';
        const hasDesc = rawDesc.length > 0;
        const descForClamp = hasDesc ? rawDesc.replace(/\s+/g, ' ').trim() : '';
        const descBlock = hasDesc
            ? `<div class="subtask-description-wrap" role="button" tabindex="0" aria-label="${this.escapeAttr(L.viewDescription)}">
                <div class="subtask-description-trimmed">${this.escapeHtml(descForClamp)}</div>
            </div>`
            : '';

        const assigneeBlock = `
            <div class="subtask-assignee-group d-flex align-items-start flex-nowrap">
                ${assigneeAvatar}
                <div class="subtask-assignee-line d-flex flex-wrap align-items-baseline flex-grow col-gap-10">
                    <span class="subtask-assignee-name">${assigneeNameHtml}</span>${assigneeMetaSuffix}
                </div>
            </div>
        `;

        const showEditItem = this.canShowSubtaskEdit(subtask);
        const showDeleteItem = this.canShowSubtaskDelete(subtask);
        const editMenuLi = showEditItem
            ? `<li>
                                    <a class="dropdown-item" href="#" onclick="window.subTasksManager.openSubtaskDescriptionModal(${subtask.id}, true); return false;">
                                        ${typeof tsIcon === 'function' ? tsIcon('edit', 'me-2 tasksession-timer-log-menu-ico') : ''}
                                        ${this.escapeHtml(L.editSubtask)}
                                    </a>
                                </li>`
            : '';
        const deleteMenuLi = showDeleteItem
            ? `<li>
                                    <a class="dropdown-item text-danger" href="#" onclick="window.subTasksManager.deleteSubTask(${subtask.id}); return false;">
                                        ${typeof tsIcon === 'function' ? tsIcon('delete', 'me-2 tasksession-timer-log-menu-ico') : ''}
                                        Delete Sub-task
                                    </a>
                                </li>`
            : '';

        div.innerHTML = `
            <div class="subtask-item-body w-100">
                <div class="subtask-title-row d-flex justify-content-between align-items-start col-gap-10">
                    <div class="subtask-title-stack d-flex flex-column align-items-start flex-grow">
                        <span class="subtask-name ${completedClass}">${this.escapeHtml(subtask.title)}</span>
                        ${descBlock}
                    </div>
                    <div class="subtask-actions">
                        <div class="dropdown">
                            <button class="btn-dots" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                ${typeof tsIcon === 'function' ? tsIcon('dots-vertical', 'w-6') : ''}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="#" onclick="window.subTasksManager.toggleSubTask(${subtask.id}, ${!isCompleted}); return false;">
                                        ${typeof tsIcon === 'function' ? tsIcon('check-circle', 'me-2 tasksession-timer-log-menu-ico') : ''}
                                        ${isCompleted ? 'Mark as Incomplete' : 'Mark as Completed'}
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="#" onclick="window.subTasksManager.openSubtaskDescriptionModal(${subtask.id}, false); return false;">
                                        ${typeof tsIcon === 'function' ? tsIcon('eye', 'me-2 tasksession-timer-log-menu-ico') : ''}
                                        ${this.escapeHtml(L.viewDescription)}
                                    </a>
                                </li>
                                ${editMenuLi}
                                ${deleteMenuLi}
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="subtask-meta-row d-flex flex-wrap align-items-center mt-2 gap-15">
                    ${assigneeBlock}
                    ${duePart}
                </div>
            </div>
        `;

        return div;
    }

    async saveNewSubTask() {
        // Check if parent task ID is set
        if (!this.currentParentTaskId) {
            alert('Error: Parent task not found. Please refresh and try again.');
            return;
        }

        const nameInput = document.getElementById('new-subtask-name');
        const dateInput = document.getElementById('new-subtask-date');
        const assigneeSelect = document.getElementById('new-subtask-assignee');

        const title = this.sanitizePlainSubtaskField(nameInput.value, 500);
        if (!title) {
            alert(this.labels().pleaseEnterName);
            return;
        }

        const descInput = document.getElementById('new-subtask-description');
        const description = descInput && descInput.value
            ? this.sanitizePlainSubtaskField(descInput.value, 65535)
            : '';

        const subtaskData = {
            parent_task_id: this.currentParentTaskId,
            title: title,
            description: description,
            status: 'todo',
            due_date: dateInput.value || null,
            assigned_to: assigneeSelect.value || null
        };
        
        try {
            const apiPath = this.getApiPath();
            const response = await (window.fetchWithCsrf ? window.fetchWithCsrf(apiPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subtaskData)
            }) : fetch(apiPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(subtaskData)
            }));

            const responseText = await response.text();
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                alert('Error saving sub-task: Invalid response from server');
                return;
            }
            
            if (data.status === 'ok') {
                this.resetNewSubtaskForm();

                // Reload sub-tasks (syncAddPanelVisibility hides form when count > 0)
                this.loadSubTasks();
                this.refreshTaskActivityIfOpen();
            } else {
                alert('Error saving sub-task: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Error saving sub-task: ' + (error.message || 'Network or server error'));
        }
    }

    async toggleSubTask(subtaskId, isCompleted) {
        const status = isCompleted ? 'done' : 'todo';
        
        try {
            const apiPath = this.getApiPath();
            const response = await (window.fetchWithCsrf ? window.fetchWithCsrf(apiPath, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: subtaskId,
                    status: status
                })
            }) : fetch(apiPath, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: subtaskId,
                    status: status
                })
            }));

            const responseText = await response.text();
            const data = JSON.parse(responseText);
            
            if (data.status === 'ok') {
                await this.loadSubTasks();
                this.refreshTaskActivityIfOpen();
            }
        } catch (error) {
        }
    }

    async deleteSubTask(subtaskId) {
        const st = this.subTasks.find(s => String(s.id) === String(subtaskId));
        if (!st || !this.canShowSubtaskDelete(st)) {
            alert(typeof lang !== 'undefined' && lang.permission_denied ? lang.permission_denied : 'Permission denied');
            return;
        }
        if (!confirm('Are you sure you want to delete this sub-task?')) {
            return;
        }

        try {
            const apiPath = this.getApiPath();
            const response = await (window.fetchWithCsrf ? window.fetchWithCsrf(apiPath, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: subtaskId })
            }) : fetch(apiPath, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: subtaskId })
            }));

            const responseText = await response.text();
            const data = JSON.parse(responseText);
            
            if (data.status === 'ok') {
                this.subTasks = this.subTasks.filter(s => String(s.id) !== String(subtaskId));

                const subtaskElement = document.querySelector(`#sub-tasks-list .subtask-item[data-subtask-id="${subtaskId}"]`);
                if (subtaskElement) {
                    subtaskElement.remove();
                }

                this.updateCount();
                this.syncAddPanelVisibility();
                this.refreshTaskActivityIfOpen();
            } else {
                alert('Error deleting sub-task: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            alert('Error deleting sub-task: ' + error.message);
        }
    }

    closeSubtaskDescriptionModal() {
        if (this._descModalKeyHandler) {
            document.removeEventListener('keydown', this._descModalKeyHandler);
            this._descModalKeyHandler = null;
        }
        const root = document.getElementById('subtask-desc-modal-root');
        if (root) root.remove();
        this._descModalState = null;
    }

    openSubtaskDescriptionModal(subtaskId, startInEditMode) {
        const subtask = this.subTasks.find(s => parseInt(s.id, 10) === parseInt(subtaskId, 10));
        if (!subtask) return;
        if (startInEditMode && !this.canShowSubtaskEdit(subtask)) {
            startInEditMode = false;
        }

        this.closeSubtaskDescriptionModal();
        const L = this.labels();

        const getDesc = () => {
            const st = this.subTasks.find(s => String(s.id) === String(subtaskId));
            return st && st.description != null ? String(st.description) : '';
        };

        const root = document.createElement('div');
        root.id = 'subtask-desc-modal-root';
        root.className = 'subtask-desc-modal-backdrop d-flex align-items-center justify-content-center p-4';
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-modal', 'true');
        root.setAttribute('aria-labelledby', 'subtask-desc-modal-title');

        root.innerHTML = `
            <div class="subtask-desc-modal-card w-100">
                <div class="subtask-desc-modal-header d-flex flex-wrap align-items-center justify-content-between gap-15 px-3 pt-3 pb-2">
                    <div class="subtask-desc-modal-title-block d-flex align-items-center col-gap-10">
                        <i class="fa fa-file-text-o subtask-desc-modal-icon" aria-hidden="true"></i>
                        <h3 class="subtask-desc-modal-heading" id="subtask-desc-modal-title">${this.escapeHtml(L.descriptionHeading)}</h3>
                    </div>
                    <div class="subtask-desc-modal-header-btns d-flex flex-wrap align-items-center col-gap-10">
                        <button type="button" class="subtask-desc-modal-btn-edit border-btn-a d-inline-flex align-items-center col-gap-10">${typeof tsIcon === 'function' ? tsIcon('edit') : ''}${this.escapeHtml(L.edit)}</button>
                        <button type="button" class="subtask-desc-modal-btn-save primary-btn d-none align-items-center col-gap-10">${this.escapeHtml(L.save)}</button>
                        <button type="button" class="subtask-desc-modal-btn-close border-btn-a d-inline-flex align-items-center col-gap-10">${typeof tsIcon === 'function' ? tsIcon('close') : ''}${this.escapeHtml(L.close)}</button>
                    </div>
                </div>
                <div class="subtask-desc-modal-divider"></div>
                <div class="subtask-desc-modal-body px-3 pb-4 pt-3">
                    <div class="subtask-desc-modal-view"></div>
                    <textarea class="subtask-desc-modal-textarea w-100" rows="10"></textarea>
                </div>
            </div>
        `;

        document.body.appendChild(root);

        const viewEl = root.querySelector('.subtask-desc-modal-view');
        const ta = root.querySelector('.subtask-desc-modal-textarea');
        const btnEdit = root.querySelector('.subtask-desc-modal-btn-edit');
        const btnSave = root.querySelector('.subtask-desc-modal-btn-save');
        const btnClose = root.querySelector('.subtask-desc-modal-btn-close');
        const card = root.querySelector('.subtask-desc-modal-card');

        const refreshView = () => {
            const text = getDesc().trim();
            viewEl.classList.toggle('subtask-desc-modal-view--empty', !text);
            viewEl.textContent = text || L.noDescription;
            viewEl.style.whiteSpace = 'pre-wrap';
        };

        refreshView();
        ta.value = getDesc();

        const showViewMode = () => {
            viewEl.style.display = '';
            ta.style.display = 'none';
            btnEdit.classList.remove('d-none');
            btnEdit.classList.add('d-inline-flex');
            btnSave.classList.add('d-none');
            btnSave.classList.remove('d-inline-flex');
            btnClose.classList.remove('d-none');
            btnClose.classList.add('d-inline-flex');
        };

        const showEditMode = () => {
            ta.value = getDesc();
            viewEl.style.display = 'none';
            ta.style.display = 'block';
            btnEdit.classList.add('d-none');
            btnEdit.classList.remove('d-inline-flex');
            btnSave.classList.remove('d-none');
            btnSave.classList.add('d-inline-flex');
            btnClose.classList.remove('d-none');
            btnClose.classList.add('d-inline-flex');
            ta.focus();
        };

        showViewMode();
        if (startInEditMode) {
            showEditMode();
        }

        if (!this.canShowSubtaskEdit(subtask)) {
            btnEdit.classList.add('d-none');
            btnEdit.style.display = 'none';
        }

        btnEdit.addEventListener('click', () => {
            if (!this.canShowSubtaskEdit(subtask)) return;
            showEditMode();
        });
        btnClose.addEventListener('click', () => this.closeSubtaskDescriptionModal());
        btnSave.addEventListener('click', () => this.saveSubtaskDescriptionFromModal(subtaskId, ta.value));

        root.addEventListener('click', (e) => {
            if (e.target === root) this.closeSubtaskDescriptionModal();
        });
        if (card) {
            card.addEventListener('click', (e) => e.stopPropagation());
        }

        this._descModalKeyHandler = (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                this.closeSubtaskDescriptionModal();
            }
        };
        document.addEventListener('keydown', this._descModalKeyHandler);
    }

    async saveSubtaskDescriptionFromModal(subtaskId, description) {
        const st = this.subTasks.find(s => String(s.id) === String(subtaskId));
        if (!st || !this.canShowSubtaskEdit(st)) {
            alert(typeof lang !== 'undefined' && lang.permission_denied ? lang.permission_denied : 'Permission denied');
            return;
        }
        try {
            const apiPath = this.getApiPath();
            const payload = JSON.stringify({
                id: subtaskId,
                description: this.sanitizePlainSubtaskField(description, 65535),
            });
            const response = window.fetchWithCsrf
                ? await window.fetchWithCsrf(apiPath, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: payload,
                })
                : await fetch(apiPath, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: payload,
                });

            const responseText = await response.text();
            let data;
            try {
                data = JSON.parse(responseText);
            } catch {
                alert('Error saving sub-task: Invalid response');
                return;
            }

            if (data.status === 'ok') {
                this.closeSubtaskDescriptionModal();
                await this.loadSubTasks();
            } else {
                alert(data.message || 'Update failed');
            }
        } catch (error) {
            alert(error.message || 'Error saving sub-task');
        }
    }

    editSubTask(subtaskId) {
        this.openSubtaskDescriptionModal(subtaskId, true);
    }

    updateCount() {
        const countElement = document.getElementById('subtasksTotalCount');
        if (countElement) {
            countElement.textContent = String(this.subTasks.length);
        }
        this.updateProgressBar();
    }

    formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        if (Number.isNaN(date.getTime())) return '';
        return date.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
    }

    /** Short calendar label from MySQL datetime (e.g. "May 10"). */
    formatSubtaskCalendarDateShort(value) {
        if (value == null) return '';
        const s = String(value).trim();
        if (!s) return '';
        const datePart = s.split(/[\sT]/)[0];
        const parts = datePart.split(/[-/]/);
        if (parts.length >= 3) {
            const y = parseInt(parts[0], 10);
            const m = parseInt(parts[1], 10) - 1;
            const d = parseInt(parts[2], 10);
            if (Number.isNaN(y) || Number.isNaN(m) || Number.isNaN(d)) return '';
            const dt = new Date(y, m, d);
            if (Number.isNaN(dt.getTime())) return '';
            return dt.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        }
        const fallback = new Date(datePart);
        if (Number.isNaN(fallback.getTime())) return '';
        return fallback.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    /** Due date display: orange pill for today/tomorrow, else calendar + long date */
    formatDueHtml(subtask) {
        if (!subtask || !subtask.due_date) return '';
        const L = this.labels();
        const raw = String(subtask.due_date).trim();
        const parts = raw.split(/[-T]/)[0].split(/[-/]/);
        let y;
        let m;
        let d;
        if (parts.length >= 3) {
            y = parseInt(parts[0], 10);
            m = parseInt(parts[1], 10) - 1;
            d = parseInt(parts[2], 10);
        } else {
            const due = new Date(raw);
            if (Number.isNaN(due.getTime())) return '';
            y = due.getFullYear();
            m = due.getMonth();
            d = due.getDate();
        }
        const dueDay = new Date(y, m, d);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        dueDay.setHours(0, 0, 0, 0);
        const diffDays = Math.round((dueDay.getTime() - today.getTime()) / 86400000);
        if (diffDays === 0) {
            return `<span class="subtask-due-pill subtask-due-pill--urgent d-inline-flex align-items-center col-gap-10"><i class="fa fa-clock-o" aria-hidden="true"></i><span>${this.escapeHtml(L.dueToday)}</span></span>`;
        }
        if (diffDays === 1) {
            return `<span class="subtask-due-pill subtask-due-pill--urgent d-inline-flex align-items-center col-gap-10"><i class="fa fa-clock-o" aria-hidden="true"></i><span>${this.escapeHtml(L.dueTomorrow)}</span></span>`;
        }
        return `<span class="subtask-date-wrap subtask-date-muted d-inline-flex align-items-center col-gap-10"><i class="fa fa-calendar" aria-hidden="true"></i><span>${this.escapeHtml(this.formatDate(raw))}</span></span>`;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : text;
        return div.innerHTML;
    }

    escapeAttr(text) {
        return String(text ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    /** Allow only safe image URLs (blocks javascript:, data:, etc.). */
    sanitizeUrlForImg(url, fallback) {
        const fb = fallback || '';
        if (url == null || typeof url !== 'string') return fb;
        const u = url.trim();
        if (u === '') return fb;
        const lower = u.slice(0, 12).toLowerCase();
        if (lower.startsWith('javascript:') || lower.startsWith('vbscript:') || lower.startsWith('data:') || lower.startsWith('blob:')) return fb;
        if (/["'<>\\\s]/.test(u)) return fb;
        if (lower.startsWith('//')) return fb;
        if (/^https?:\/\//i.test(u)) return u;
        if (u.startsWith('/') && !u.startsWith('//')) return u;
        if (u.startsWith('./') || u.startsWith('../')) return u;
        return fb;
    }

    /** Strip control chars and cap length before sending to API (server sanitizes again). */
    sanitizePlainSubtaskField(value, maxLen) {
        let s = String(value ?? '');
        s = s.replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, '');
        const cap = typeof maxLen === 'number' && maxLen > 0 ? maxLen : 65535;
        if (s.length > cap) s = s.slice(0, cap);
        return s.trim();
    }
}

// Initialize the sub-tasks manager
window.subTasksManager = new SubTasksManager();

// Function to be called when task sidebar opens
function initializeSubTasks(parentTaskId) {
    window.subTasksManager.setParentTaskId(parentTaskId);
}

} // End of duplicate loading prevention
    