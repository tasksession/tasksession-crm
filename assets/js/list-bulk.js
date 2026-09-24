/**
 * Reusable table list bulk-select (media-vault pattern).
 * TsListBulk.init({ instances: { products: { ... } } })
 */
(function (window, document) {
    'use strict';

    var instances = {};

    function idPrefix(instanceId) {
        return 'tsListBulk_' + String(instanceId).replace(/[^a-zA-Z0-9_-]/g, '') + '_';
    }

    function getEl(instanceId, suffix) {
        return document.getElementById(idPrefix(instanceId) + suffix);
    }

    function getInstance(instanceId) {
        return instances[instanceId] || null;
    }

    function getCheckboxes(cfg) {
        var root = document.querySelector(cfg.rootSelector);
        if (!root) {
            return [];
        }
        var name = cfg.checkboxName || 'btSelectItem';
        return Array.prototype.slice.call(root.querySelectorAll('input[name="' + name + '"]:not(:disabled)'));
    }

    function setBodyBulkState(instanceId, active) {
        if (active) {
            document.body.classList.add('ts-list-bulk-mode-active');
            document.body.setAttribute('data-ts-list-bulk-instance', instanceId);
        } else {
            document.body.classList.remove('ts-list-bulk-mode-active');
            document.body.removeAttribute('data-ts-list-bulk-instance');
        }
    }

    function updateActionLabels(instanceId) {
        var cfg = getInstance(instanceId);
        if (!cfg) {
            return;
        }
        var checked = getCheckboxes(cfg).filter(function (cb) { return cb.checked; });
        var labelEl = getEl(instanceId, 'bulkDeleteLabel');
        var base = cfg.deleteLabel || 'Delete';
        if (labelEl) {
            labelEl.textContent = checked.length > 0 ? base + ' (' + checked.length + ')' : base;
        }
        var archiveLabelEl = getEl(instanceId, 'bulkArchiveLabel');
        if (archiveLabelEl) {
            var archiveBase = cfg.archiveLabel || 'Archive';
            archiveLabelEl.textContent = checked.length > 0 ? archiveBase + ' (' + checked.length + ')' : archiveBase;
        }
        var secondaryLabelEl = getEl(instanceId, 'bulkSecondaryLabel');
        if (secondaryLabelEl) {
            var secondaryBase = cfg.secondaryLabel || 'Delete';
            secondaryLabelEl.textContent = checked.length > 0 ? secondaryBase + ' (' + checked.length + ')' : secondaryBase;
        }
        var selectAllLabel = getEl(instanceId, 'bulkSelectAllLabel');
        if (selectAllLabel) {
            var all = getCheckboxes(cfg);
            var allChecked = all.length > 0 && all.every(function (cb) { return cb.checked; });
            selectAllLabel.textContent = allChecked
                ? (cfg.deselectAllLabel || 'Deselect all')
                : (cfg.selectAllLabel || 'Select all');
        }
    }

    function showBulkTabs(instanceId, isBulk) {
        var selectTab = getEl(instanceId, 'bulkSelectTab');
        var backTab = getEl(instanceId, 'bulkBackTab');
        var selectAllTab = getEl(instanceId, 'bulkSelectAllTab');
        var deleteTab = getEl(instanceId, 'bulkDeleteTab');
        var archiveTab = getEl(instanceId, 'bulkArchiveTab');
        var secondaryTab = getEl(instanceId, 'bulkSecondaryTab');
        if (selectTab) {
            selectTab.style.display = isBulk ? 'none' : 'inline-flex';
        }
        if (backTab) {
            backTab.style.display = isBulk ? 'inline-flex' : 'none';
        }
        if (selectAllTab) {
            selectAllTab.style.display = isBulk ? 'inline-flex' : 'none';
        }
        if (deleteTab) {
            deleteTab.style.display = isBulk ? 'inline-flex' : 'none';
        }
        if (archiveTab) {
            archiveTab.style.display = isBulk ? 'inline-flex' : 'none';
        }
        if (secondaryTab) {
            secondaryTab.style.display = isBulk ? 'inline-flex' : 'none';
        }
    }

    function enter(instanceId) {
        var cfg = getInstance(instanceId);
        if (!cfg) {
            return;
        }
        cfg.active = true;
        if (cfg.bodyClass) {
            document.body.classList.add(cfg.bodyClass);
        }
        setBodyBulkState(instanceId, true);
        showBulkTabs(instanceId, true);
        updateActionLabels(instanceId);
    }

    function exit(instanceId) {
        var cfg = getInstance(instanceId);
        if (!cfg) {
            return;
        }
        cfg.active = false;
        if (cfg.bodyClass) {
            document.body.classList.remove(cfg.bodyClass);
        }
        getCheckboxes(cfg).forEach(function (cb) {
            cb.checked = false;
        });
        setBodyBulkState(instanceId, false);
        showBulkTabs(instanceId, false);
    }

    function syncFromSelection(instanceId) {
        var cfg = getInstance(instanceId);
        if (!cfg) {
            return;
        }
        var checked = getCheckboxes(cfg).filter(function (cb) { return cb.checked; });
        if (checked.length > 0) {
            if (!cfg.active) {
                enter(instanceId);
            } else {
                updateActionLabels(instanceId);
            }
        } else if (cfg.active) {
            exit(instanceId);
        } else {
            updateActionLabels(instanceId);
        }
    }

    function selectAll(instanceId) {
        var cfg = getInstance(instanceId);
        if (!cfg) {
            return;
        }
        if (!cfg.active) {
            enter(instanceId);
        }
        var boxes = getCheckboxes(cfg);
        var allChecked = boxes.length > 0 && boxes.every(function (cb) { return cb.checked; });
        boxes.forEach(function (cb) {
            cb.checked = !allChecked;
        });
        syncFromSelection(instanceId);
    }

    function submitSelected(instanceId, actionType) {
        var cfg = getInstance(instanceId);
        if (!cfg) {
            return;
        }
        var ids = getCheckboxes(cfg)
            .filter(function (cb) { return cb.checked; })
            .map(function (cb) { return cb.value; })
            .filter(Boolean);
        if (ids.length === 0) {
            return;
        }

        var isArchive = actionType === 'archive';
        var isSecondary = actionType === 'secondary';
        var msg = isArchive
            ? (cfg.archiveConfirm || 'Archive selected items?')
            : isSecondary
                ? (cfg.secondaryConfirm || 'Delete selected items?')
                : (cfg.deleteConfirm || 'Delete selected items?');
        var beforeFn = isArchive
            ? cfg.onBeforeArchive
            : isSecondary
                ? cfg.onBeforeSecondary
                : cfg.onBeforeDelete;
        var afterFn = isArchive
            ? cfg.onArchive
            : isSecondary
                ? cfg.onSecondary
                : cfg.onDelete;

        if (typeof beforeFn === 'function') {
            var custom = beforeFn(ids);
            if (custom === false) {
                return;
            }
            if (typeof custom === 'string' && custom !== '') {
                msg = custom;
            }
        }
        if (!window.confirm(msg)) {
            return;
        }
        if (typeof afterFn === 'function') {
            afterFn(ids);
            return;
        }

        var formId = cfg.formId || 'tsListBulkForm';
        var form = document.getElementById(formId);
        if (!form) {
            return;
        }

        if (isArchive) {
            var archiveIdsField = cfg.archiveIdsField || 'bulk_arc_id';
            var archiveIdsInput = form.querySelector('[name="' + archiveIdsField + '"]');
            if (archiveIdsInput) {
                archiveIdsInput.value = ids.join(',');
            }
        } else {
            var actionField = cfg.actionField || 'ts_list_bulk_action';
            var idsField = cfg.idsField || 'bulk_ids';
            var actionInput = form.querySelector('[name="' + actionField + '"]');
            var idsInput = form.querySelector('[name="' + idsField + '"]');
            if (actionInput) {
                actionInput.value = isSecondary
                    ? (cfg.secondaryAction || 'delete')
                    : (cfg.deleteAction || 'delete');
            }
            if (idsInput) {
                idsInput.value = ids.join(',');
            }
        }

        form.submit();
    }

    function deleteSelected(instanceId) {
        submitSelected(instanceId, 'delete');
    }

    function archiveSelected(instanceId) {
        submitSelected(instanceId, 'archive');
    }

    function secondarySelected(instanceId) {
        submitSelected(instanceId, 'secondary');
    }

    function bindCheckboxClicks(instanceId) {
        var cfg = getInstance(instanceId);
        if (!cfg) {
            return;
        }
        var root = document.querySelector(cfg.rootSelector);
        if (!root) {
            return;
        }
        root.addEventListener('change', function (e) {
            var t = e.target;
            if (t && t.matches && t.matches('input[name="' + (cfg.checkboxName || 'btSelectItem') + '"]')) {
                syncFromSelection(instanceId);
            }
        });
        root.addEventListener('click', function (e) {
            var cell = e.target && e.target.closest ? e.target.closest('.ts-list-bulk-checkbox-cell') : null;
            if (!cell) {
                return;
            }
            var input = cell.querySelector('input[name="' + (cfg.checkboxName || 'btSelectItem') + '"]');
            if (!input || e.target === input) {
                return;
            }
            e.preventDefault();
            input.checked = !input.checked;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }, true);
    }

    function init(config) {
        config = config || {};
        var map = config.instances || {};
        Object.keys(map).forEach(function (instanceId) {
            var cfg = map[instanceId] || {};
            cfg.active = false;
            cfg.deleteLabel = cfg.deleteLabel || 'Delete';
            cfg.archiveLabel = cfg.archiveLabel || 'Archive';
            cfg.secondaryLabel = cfg.secondaryLabel || 'Delete';
            cfg.selectAllLabel = cfg.selectAllLabel || 'Select all';
            cfg.deselectAllLabel = cfg.deselectAllLabel || 'Deselect all';
            instances[instanceId] = cfg;
            bindCheckboxClicks(instanceId);
        });
    }

    window.TsListBulk = {
        init: init,
        enter: enter,
        exit: exit,
        selectAll: selectAll,
        deleteSelected: deleteSelected,
        archiveSelected: archiveSelected,
        secondarySelected: secondarySelected,
        syncFromSelection: syncFromSelection,
    };
})(window, document);
