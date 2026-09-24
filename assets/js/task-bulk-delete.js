/**
 * Task Bulk Select – Kanban board (media-vault pattern)
 */

let bulkDeleteMode = false;
let selectedTasks = new Set();

function kanbanCanDelete() {
    return typeof window.kanbanCanDelete === 'undefined' || !!window.kanbanCanDelete;
}

function kanbanCanArchive() {
    return typeof window.kanbanCanArchive === 'undefined' || !!window.kanbanCanArchive;
}

function isKanbanArchiveView() {
    return !!(typeof window !== 'undefined' && window.kanbanIsArchiveView);
}

function isTasksTableView() {
    return !!document.getElementById('tasks-table');
}

function getTaskCheckboxes() {
    const tableCheckboxes = document.querySelectorAll('#tasks-table .task-checkbox');
    if (tableCheckboxes.length > 0) {
        return tableCheckboxes;
    }
    return document.querySelectorAll('.bulk-delete-checkbox .task-checkbox');
}

function ensureTableCheckboxesVisible() {
    document.querySelectorAll('#tasks-table .task-table-checkbox').forEach(function (wrapper) {
        wrapper.style.display = 'flex';
    });
}

function syncBulkModeFromSelection() {
    if (!isTasksTableView()) {
        updateSelectedCount();
        return;
    }

    const count = document.querySelectorAll('#tasks-table .task-checkbox:checked').length;
    if (count > 0) {
        if (!bulkDeleteMode) {
            enterBulkMode();
        } else {
            updateSelectedCount();
        }
    } else if (bulkDeleteMode) {
        exitBulkMode();
    } else {
        updateSelectedCount();
    }
}

function removeTaskFromView(taskId) {
    const card = document.querySelector('.task-card[data-id="' + taskId + '"]');
    if (card) {
        card.remove();
        return;
    }
    const checkbox = document.getElementById('task-' + taskId);
    if (checkbox) {
        const row = checkbox.closest('tr');
        if (row) row.remove();
    }
}

function syncSelectAllHeaderCheckbox() {
    const selectAllCheckbox = document.getElementById('select-all-tasks');
    if (!selectAllCheckbox) return;

    const taskCheckboxes = getTaskCheckboxes();
    if (taskCheckboxes.length === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
        return;
    }

    const allChecked = Array.from(taskCheckboxes).every(cb => cb.checked);
    const anyChecked = Array.from(taskCheckboxes).some(cb => cb.checked);
    selectAllCheckbox.checked = allChecked;
    selectAllCheckbox.indeterminate = anyChecked && !allChecked;
}

function enterBulkMode() {
    if (bulkDeleteMode) return;

    bulkDeleteMode = true;
    document.body.classList.add('kanban-bulk-mode-active');

    const bulkSelectTab = document.getElementById('bulkSelectTab');
    const backTab = document.getElementById('bulkBackTab');
    const selectAllTab = document.getElementById('bulkSelectAllTab');
    const deleteTab = document.getElementById('bulkDeleteTab');
    const archiveTab = document.getElementById('bulkArchiveTab');

    if (bulkSelectTab) bulkSelectTab.style.display = 'none';
    if (backTab) backTab.style.display = 'inline-flex';
    if (selectAllTab) selectAllTab.style.display = 'inline-flex';
    if (deleteTab) {
        deleteTab.style.display = kanbanCanDelete() ? 'inline-flex' : 'none';
    }
    if (archiveTab) {
        archiveTab.style.display = kanbanCanArchive() ? 'inline-flex' : 'none';
        const archiveLabel = document.getElementById('bulkArchiveLabel');
        if (archiveLabel) {
            archiveLabel.textContent = isKanbanArchiveView()
                ? (window.langUnarchive || 'Unarchive')
                : (window.langArchive || 'Archive');
        }
    }

    applyBulkModeUiToScope(document, { checkNew: false });
    updateSelectedCount();
}

/**
 * After Load More (or any dynamic card insert): show bulk checkboxes while bulk mode is on.
 * If every visible task was already selected ("Deselect all" state), newly loaded cards are checked too.
 *
 * @param {ParentNode|Element|Element[]|null} scope
 */
function syncKanbanBulkModeAfterLoadMore(scope) {
    if (!bulkDeleteMode || isTasksTableView()) {
        return;
    }
    const selectAllLabel = document.getElementById('bulkSelectAllLabel');
    const deselectAllText = (window.langDeselectAll || 'Deselect all').trim();
    const allWereSelected = !!(selectAllLabel && selectAllLabel.textContent.trim() === deselectAllText);

    if (Array.isArray(scope)) {
        scope.forEach(function (node) {
            applyBulkModeUiToScope(node, { checkNew: allWereSelected });
        });
    } else {
        applyBulkModeUiToScope(scope || document, { checkNew: allWereSelected });
    }
    updateSelectedCount();
}

function applyBulkModeUiToScope(scope, options) {
    const root = scope || document;
    const checkNew = !!(options && options.checkNew);

    root.querySelectorAll('.bulk-delete-checkbox').forEach(function (checkbox) {
        if (!checkbox.closest('#tasks-table')) {
            checkbox.style.display = 'block';
        }
        if (checkNew) {
            const input = checkbox.querySelector('input.task-checkbox');
            if (input) {
                input.checked = true;
            }
        }
    });

    root.querySelectorAll('.task-card').forEach(function (card) {
        card.draggable = false;
        card.style.cursor = 'default';
    });
}

function exitBulkMode() {
    bulkDeleteMode = false;
    selectedTasks.clear();
    document.body.classList.remove('kanban-bulk-mode-active');

    const bulkSelectTab = document.getElementById('bulkSelectTab');
    const backTab = document.getElementById('bulkBackTab');
    const selectAllTab = document.getElementById('bulkSelectAllTab');
    const deleteTab = document.getElementById('bulkDeleteTab');
    const archiveTab = document.getElementById('bulkArchiveTab');

    if (bulkSelectTab) bulkSelectTab.style.display = 'inline-flex';
    if (backTab) backTab.style.display = 'none';
    if (selectAllTab) selectAllTab.style.display = 'none';
    if (deleteTab) deleteTab.style.display = 'none';
    if (archiveTab) archiveTab.style.display = 'none';

    document.querySelectorAll('.bulk-delete-checkbox').forEach(checkbox => {
        if (!checkbox.closest('#tasks-table')) {
            checkbox.style.display = 'none';
        }
        const input = checkbox.querySelector('input');
        if (input) input.checked = false;
    });

    document.querySelectorAll('#tasks-table .task-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });

    const selectAllCheckbox = document.getElementById('select-all-tasks');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
    }

    ensureTableCheckboxesVisible();

    document.querySelectorAll('.task-card').forEach(card => {
        const draggable = card.getAttribute('data-draggable');
        if (draggable === 'false') {
            card.draggable = false;
            card.style.cursor = 'default';
        } else {
            card.draggable = true;
            card.style.cursor = 'grab';
        }
    });

    updateSelectedCount();
}

function toggleBulkDeleteMode() {
    if (!bulkDeleteMode) {
        enterBulkMode();
    } else {
        exitBulkMode();
    }
}

function selectAllTasks() {
    getTaskCheckboxes().forEach(checkbox => {
        checkbox.checked = true;
    });
    syncSelectAllHeaderCheckbox();
    syncBulkModeFromSelection();
}

function deselectAllTasks() {
    getTaskCheckboxes().forEach(checkbox => {
        checkbox.checked = false;
    });
    syncSelectAllHeaderCheckbox();
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.task-checkbox:checked');
    const count = checkboxes.length;

    selectedTasks.clear();
    checkboxes.forEach(checkbox => {
        selectedTasks.add(checkbox.value);
    });

    const selectAllTab = document.getElementById('bulkSelectAllTab');
    const selectAllLabel = document.getElementById('bulkSelectAllLabel');
    if (selectAllTab && selectAllLabel) {
        const totalTasks = getTaskCheckboxes().length;
        const selectAllText = window.langSelectAll || 'Select all';
        const deselectAllText = window.langDeselectAll || 'Deselect all';

        if (count === 0) {
            selectAllLabel.textContent = selectAllText;
            selectAllTab.setAttribute('onclick', 'selectAllTasks(); return false;');
        } else if (count === totalTasks && totalTasks > 0) {
            selectAllLabel.textContent = deselectAllText;
            selectAllTab.setAttribute('onclick', 'deselectAllTasks(); return false;');
        } else {
            selectAllLabel.textContent = selectAllText + ' (' + count + '/' + totalTasks + ')';
            selectAllTab.setAttribute('onclick', 'selectAllTasks(); return false;');
        }
    }
}

function confirmBulkDelete() {
    if (selectedTasks.size === 0) {
        alert(window.langBulkDeleteNone || 'Please select at least one task to delete.');
        return;
    }

    const taskCount = selectedTasks.size;
    const template = window.langBulkDeleteConfirm || 'Are you sure you want to delete %d task(s)? This action cannot be undone.';
    const confirmMessage = template.replace('%d', taskCount);

    if (confirm(confirmMessage)) {
        performBulkDelete();
    }
}

function performBulkDelete() {
    const taskIds = Array.from(selectedTasks);
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = window.location.href;

    taskIds.forEach(taskId => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'bulk_delete_tasks[]';
        input.value = taskId;
        form.appendChild(input);
    });

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'bulk_delete_action';
    actionInput.value = 'delete';
    form.appendChild(actionInput);

    document.body.appendChild(form);
    form.submit();
}

function confirmBulkArchive() {
    if (selectedTasks.size === 0) {
        alert(window.langBulkArchiveNone || 'Please select at least one task.');
        return;
    }

    const taskCount = selectedTasks.size;
    const isUnarchive = isKanbanArchiveView();
    const template = isUnarchive
        ? (window.langBulkUnarchiveConfirm || 'Restore %d selected task(s) to the active board?')
        : (window.langBulkArchiveConfirm || 'Are you sure you want to archive %d selected task(s)?');
    const confirmMessage = template.replace('%d', taskCount);

    if (confirm(confirmMessage)) {
        performBulkArchive();
    }
}

function performBulkArchive() {
    const taskIds = Array.from(selectedTasks);
    const action = isKanbanArchiveView() ? 'unarchive' : 'archive';

    fetch('../includes/bulk_archive_tasks.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': (typeof window !== 'undefined' && window.csrfToken) ? window.csrfToken : ''
        },
        body: JSON.stringify({
            ids: taskIds.map(id => parseInt(id, 10)),
            action: action,
            csrf_token: (typeof window !== 'undefined' && window.csrfToken) ? window.csrfToken : ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'ok') {
            taskIds.forEach(taskId => {
                removeTaskFromView(taskId);
            });
            exitBulkMode();
            if (data.failed && data.failed.length > 0) {
                alert((window.langBulkPartialFail || 'Some tasks could not be updated.') + '\n' + data.failed.join('\n'));
            }
        } else {
            alert(data.error || (window.langBulkArchiveFail || 'Failed to update tasks'));
        }
    })
    .catch(error => alert('Error: ' + error));
}

function initBulkDelete() {
    ensureTableCheckboxesVisible();

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('task-checkbox')) {
            syncSelectAllHeaderCheckbox();
            syncBulkModeFromSelection();
        }
        if (e.target.id === 'select-all-tasks') {
            getTaskCheckboxes().forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
            syncBulkModeFromSelection();
        }
    });

    window.enterBulkMode = enterBulkMode;
    window.exitBulkMode = exitBulkMode;
    window.toggleBulkDeleteMode = toggleBulkDeleteMode;
    window.selectAllTasks = selectAllTasks;
    window.deselectAllTasks = deselectAllTasks;
    window.updateSelectedCount = updateSelectedCount;
    window.confirmBulkDelete = confirmBulkDelete;
    window.performBulkDelete = performBulkDelete;
    window.confirmBulkArchive = confirmBulkArchive;
    window.performBulkArchive = performBulkArchive;
    window.exitBulkDeleteMode = exitBulkMode;
    window.syncKanbanBulkModeAfterLoadMore = syncKanbanBulkModeAfterLoadMore;
}

document.addEventListener('DOMContentLoaded', initBulkDelete);
