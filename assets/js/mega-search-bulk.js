/**
 * Mega search bulk select + delete (projects header pattern).
 */
(function (window, document) {
  'use strict';

  var cfg = window.MEGA_SEARCH || {};
  var bulkCfg = window.MEGA_SEARCH_BULK || {};
  var tabConfigs = bulkCfg.tabs || {};
  var bulkMode = false;

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function getActiveTab() {
    var hidden = document.getElementById('mega-search-tab-input');
    if (hidden && hidden.value) {
      return hidden.value;
    }
    var activeBtn = qs('.mega-search-tabs__btn.active') || qs('.mega-search-tabs__btn.is-active');
    return activeBtn ? activeBtn.getAttribute('data-tab') : (cfg.activeTab || 'people');
  }

  function getTabConfig(tab) {
    return tabConfigs[tab] || null;
  }

  function toolbarVisible(tab) {
    var conf = getTabConfig(tab);
    return !!(conf && conf.enabled);
  }

  function updateToolbarVisibility() {
    var wrap = document.getElementById('megaSearchBulkToolbar');
    if (!wrap) {
      return;
    }
    wrap.style.display = toolbarVisible(getActiveTab()) ? '' : 'none';
  }

  function getRoot(tab) {
    var conf = getTabConfig(tab);
    if (!conf || !conf.root) {
      return null;
    }
    return qs(conf.root);
  }

  function getCheckboxes(tab) {
    var conf = getTabConfig(tab);
    var root = getRoot(tab);
    if (!conf || !root) {
      return [];
    }
    return qsa(conf.checkbox, root).filter(function (cb) {
      return !cb.disabled;
    });
  }

  function getCheckedIds(tab) {
    return getCheckboxes(tab)
      .filter(function (cb) { return cb.checked; })
      .map(function (cb) { return cb.value; })
      .filter(Boolean);
  }

  function el(id) {
    return document.getElementById(id);
  }

  function showBulkTabs(isBulk) {
    var selectTab = el('megaSearchBulkSelectTab');
    var backTab = el('megaSearchBulkBackTab');
    var selectAllTab = el('megaSearchBulkSelectAllTab');
    var deleteTab = el('megaSearchBulkDeleteTab');
    if (selectTab) selectTab.style.display = isBulk ? 'none' : 'inline-flex';
    if (backTab) backTab.style.display = isBulk ? 'inline-flex' : 'none';
    if (selectAllTab) selectAllTab.style.display = isBulk ? 'inline-flex' : 'none';
    if (deleteTab) deleteTab.style.display = isBulk ? 'inline-flex' : 'none';
  }

  function updateDeleteLabel() {
    var tab = getActiveTab();
    var label = el('megaSearchBulkDeleteLabel');
    var base = (bulkCfg.labels && bulkCfg.labels.delete) || 'Delete';
    var count = getCheckedIds(tab).length;
    if (label) {
      label.textContent = count > 0 ? base + ' (' + count + ')' : base;
    }
    var selectAllLabel = el('megaSearchBulkSelectAllLabel');
    if (selectAllLabel) {
      var boxes = getCheckboxes(tab);
      var allChecked = boxes.length > 0 && boxes.every(function (cb) { return cb.checked; });
      selectAllLabel.textContent = allChecked
        ? ((bulkCfg.labels && bulkCfg.labels.deselectAll) || 'Deselect all')
        : ((bulkCfg.labels && bulkCfg.labels.selectAll) || 'Select all');
    }
  }

  function setBulkMode(on) {
    bulkMode = !!on;
    document.body.classList.toggle('mega-search-bulk-mode-active', bulkMode);
    document.body.classList.toggle('ts-list-bulk-mode-active', bulkMode);
    showBulkTabs(bulkMode);
    updateDeleteLabel();
  }

  function enterBulkMode() {
    if (!toolbarVisible(getActiveTab())) {
      return;
    }
    setBulkMode(true);
  }

  function exitBulkMode() {
    var tab = getActiveTab();
    getCheckboxes(tab).forEach(function (cb) {
      cb.checked = false;
    });
    var conf = getTabConfig(tab);
    if (conf && conf.selectAll) {
      var selectAll = qs(conf.selectAll);
      if (selectAll) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
      }
    }
    setBulkMode(false);
  }

  function syncFromSelection() {
    var tab = getActiveTab();
    var checked = getCheckedIds(tab);
    if (checked.length > 0) {
      if (!bulkMode) {
        setBulkMode(true);
      } else {
        updateDeleteLabel();
      }
    } else if (bulkMode) {
      exitBulkMode();
    } else {
      updateDeleteLabel();
    }
  }

  function selectAll() {
    var tab = getActiveTab();
    if (!bulkMode) {
      enterBulkMode();
    }
    var boxes = getCheckboxes(tab);
    var allChecked = boxes.length > 0 && boxes.every(function (cb) { return cb.checked; });
    boxes.forEach(function (cb) {
      cb.checked = !allChecked;
    });
    syncSelectAllHeader(tab);
    syncFromSelection();
  }

  function syncSelectAllHeader(tab) {
    var conf = getTabConfig(tab);
    if (!conf || !conf.selectAll) {
      return;
    }
    var selectAll = qs(conf.selectAll);
    if (!selectAll) {
      return;
    }
    var boxes = getCheckboxes(tab);
    if (boxes.length === 0) {
      selectAll.checked = false;
      selectAll.indeterminate = false;
      return;
    }
    var allChecked = boxes.every(function (cb) { return cb.checked; });
    var anyChecked = boxes.some(function (cb) { return cb.checked; });
    selectAll.checked = allChecked;
    selectAll.indeterminate = anyChecked && !allChecked;
  }

  function refreshBindings() {
    updateToolbarVisibility();
    if (bulkMode) {
      exitBulkMode();
    }
  }

  function runSearchRefresh() {
    if (typeof window.megaSearchRunNow === 'function') {
      window.megaSearchRunNow(false);
      return;
    }
    window.location.reload();
  }

  function deleteSelected() {
    var tab = getActiveTab();
    var conf = getTabConfig(tab);
    if (!conf || !conf.enabled) {
      return;
    }
    var ids = getCheckedIds(tab);
    if (ids.length === 0) {
      return;
    }
    var msg = conf.confirm || ((bulkCfg.labels && bulkCfg.labels.confirm) || 'Delete selected items?');
    if (!window.confirm(msg)) {
      return;
    }

    var url = bulkCfg.ajaxUrl || (String(cfg.ajaxUrl || '').replace('mega_search.php', 'mega_search_bulk.php'));
    if (!url) {
      return;
    }

    var body = new FormData();
    body.append('tab', tab);
    ids.forEach(function (id) {
      body.append('ids[]', id);
    });

    var xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) {
        return;
      }
      try {
        var data = JSON.parse(xhr.responseText || '{}');
        if (data && data.success) {
          exitBulkMode();
          runSearchRefresh();
          if (window.__toastFlash !== undefined && typeof window.showToast === 'function') {
            var deleted = parseInt(data.deleted, 10) || 0;
            if (deleted > 0) {
              window.showToast('success', deleted + ' item(s) deleted.');
            }
          }
        } else if (data && data.error) {
          window.alert(data.error);
        }
      } catch (e) {
        window.alert('Delete failed. Please try again.');
      }
    };
    xhr.send(body);
  }

  function bindResultsEvents() {
    var box = document.getElementById('mega-search-results');
    if (!box || box.__megaBulkBound) {
      return;
    }
    box.__megaBulkBound = true;
    box.addEventListener('change', function (e) {
      var target = e.target;
      if (!target || target.type !== 'checkbox') {
        return;
      }
      var tab = getActiveTab();
      var conf = getTabConfig(tab);
      if (!conf) {
        return;
      }
      if (conf.selectAll && target.matches(conf.selectAll)) {
        var checked = target.checked;
        getCheckboxes(tab).forEach(function (cb) {
          cb.checked = checked;
        });
        syncFromSelection();
        return;
      }
      if (target.matches(conf.checkbox)) {
        syncSelectAllHeader(tab);
        syncFromSelection();
      }
    });
  }

  function initToolbarClicks() {
    var selectTab = el('megaSearchBulkSelectTab');
    var backTab = el('megaSearchBulkBackTab');
    var selectAllTab = el('megaSearchBulkSelectAllTab');
    var deleteTab = el('megaSearchBulkDeleteTab');
    if (selectTab) {
      selectTab.addEventListener('click', function (e) {
        e.preventDefault();
        enterBulkMode();
      });
    }
    if (backTab) {
      backTab.addEventListener('click', function (e) {
        e.preventDefault();
        exitBulkMode();
      });
    }
    if (selectAllTab) {
      selectAllTab.addEventListener('click', function (e) {
        e.preventDefault();
        selectAll();
      });
    }
    if (deleteTab) {
      deleteTab.addEventListener('click', function (e) {
        e.preventDefault();
        deleteSelected();
      });
    }
  }

  window.MegaSearchBulk = {
    refresh: refreshBindings,
    exit: exitBulkMode,
    onTabChange: function () {
      exitBulkMode();
      updateToolbarVisibility();
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    if (!document.getElementById('mega-search-results')) {
      return;
    }
    initToolbarClicks();
    bindResultsEvents();
    updateToolbarVisibility();
  });
})(window, document);
