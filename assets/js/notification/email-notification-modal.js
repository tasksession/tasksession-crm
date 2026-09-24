/**
 * Per-context chat email policy modal (project / group / task).
 * Requires: Bootstrap 5 modal, fetch, window.url (base), csrf token meta or window.csrfToken
 *
 * State lives on window.__comonEmailNotifState because task-sidebar.php may be included twice
 * (header + page); a second script tag would otherwise leave stale closure vars so Save never runs.
 */
(function () {
  try {
    if (typeof window.comonEmailNotifDebug === 'undefined' &&
        typeof window.location.pathname === 'string' &&
        window.location.pathname.indexOf('discussion.php') !== -1) {
      window.comonEmailNotifDebug = true;
    }
  } catch (eDbg) {}

  function comonLogEmailNotif() {
    if (!window.comonEmailNotifDebug) {
      return;
    }
    var args = Array.prototype.slice.call(arguments);
    args.unshift('[EmailNotif]');
    console.log.apply(console, args);
  }

  function getState() {
    if (!window.__comonEmailNotifState) {
      window.__comonEmailNotifState = {
        currentType: '',
        currentId: 0,
        hasOverrideRow: false,
        canSave: false,
        canResetGlobal: false,
        hideClientRow: false,
        uiMode: 'full',
        selfKey: null,
        saveMode: 'none',
      };
    }
    return window.__comonEmailNotifState;
  }

  function restoreSelfRowCopy() {
    document.querySelectorAll('.chat-email-notif-row[data-email-notif-row]').forEach(function (row) {
      if (row._comonOrigTitle != null) {
        var tit = row.querySelector('.chat-email-notif-title');
        var ds = row.querySelector('.chat-email-notif-desc');
        if (tit) tit.textContent = row._comonOrigTitle;
        if (ds) ds.textContent = row._comonOrigDesc;
        delete row._comonOrigTitle;
        delete row._comonOrigDesc;
      }
    });
  }

  function applySelfRowCopy() {
    var st = getState();
    if (st.uiMode !== 'self' || !st.selfKey) {
      return;
    }
    var row = document.querySelector('.chat-email-notif-row[data-email-notif-row="' + st.selfKey + '"]');
    if (!row) {
      return;
    }
    var titEl = row.querySelector('.chat-email-notif-title');
    var dsEl = row.querySelector('.chat-email-notif-desc');
    if (!titEl || !dsEl) {
      return;
    }
    if (row._comonOrigTitle == null) {
      row._comonOrigTitle = titEl.textContent;
      row._comonOrigDesc = dsEl.textContent;
    }
    titEl.textContent = (typeof window.comonEmailNotifSelfTitle === 'string' && window.comonEmailNotifSelfTitle) ? window.comonEmailNotifSelfTitle : 'Email Notifications';
    dsEl.textContent = (typeof window.comonEmailNotifSelfDesc === 'string' && window.comonEmailNotifSelfDesc) ? window.comonEmailNotifSelfDesc : 'Receive email updates about your activity and important changes.';
  }

  function applyNotifLayout() {
    var st = getState();
    restoreSelfRowCopy();
    var list = document.querySelector('.chat-email-notif-list');
    if (!list) {
      return;
    }
    var rows = list.querySelectorAll('.chat-email-notif-row[data-email-notif-row]');
    var i;
    for (i = 0; i < rows.length; i++) {
      rows[i].classList.remove('d-none');
    }
    if (st.uiMode === 'self' && st.selfKey) {
      for (i = 0; i < rows.length; i++) {
        var r = rows[i].getAttribute('data-email-notif-row');
        if (r === st.selfKey) {
          continue;
        }
        rows[i].classList.add('d-none');
      }
      applySelfRowCopy();
    }
    if (st.hideClientRow) {
      for (i = 0; i < rows.length; i++) {
        if (rows[i].getAttribute('data-email-notif-row') === 'client') {
          rows[i].classList.add('d-none');
        }
      }
    }
    var resetBtn = el('emailNotifResetGlobal');
    if (resetBtn) {
      resetBtn.classList.toggle('d-none', !st.canResetGlobal);
    }
    var foot = document.querySelector('.chat-email-modal-footer');
    if (foot) {
      foot.classList.toggle('justify-content-end', st.uiMode === 'self');
      foot.classList.toggle('justify-content-between', st.uiMode !== 'self');
    }
  }

  /**
   * Infer app root when window.url is missing or wrongly set to '/' (common with subdirectory installs).
   * Wrong base caused fetch('/ajax/...') → 404 at domain root instead of /comon/ajax/...
   */
  function inferBaseFromLocation() {
    var origin = window.location.origin || '';
    var path = window.location.pathname || '/';
    var markers = ['/admin/', '/staff/', '/client/', '/mail/', '/real-chat/'];
    var i;
    for (i = 0; i < markers.length; i++) {
      var idx = path.indexOf(markers[i]);
      if (idx === 0) {
        return origin + '/';
      }
      if (idx > 0) {
        return origin + path.substring(0, idx + 1);
      }
    }
    var parts = path.split('/').filter(function (s) { return s.length > 0; });
    if (parts.length >= 2) {
      return origin + '/' + parts[0] + '/';
    }
    return origin + '/';
  }

  function normBase() {
    var b = typeof window.url === 'string' ? window.url.trim() : '';
    if (b && b !== '/') {
      return b.charAt(b.length - 1) === '/' ? b : (b + '/');
    }
    return inferBaseFromLocation();
  }

  function getBase() {
    return normBase();
  }

  function el(id) { return document.getElementById(id); }

  function ensureMailStyleToastContainer() {
    var c = document.getElementById('toastContainer');
    if (!c) {
      c = document.createElement('div');
      c.id = 'toastContainer';
      c.className = 'toast-container';
      document.body.appendChild(c);
    }
    c.style.zIndex = '11060';
    return c;
  }

  function toast(message, type) {
    var t = type || 'info';
    if (t !== 'success' && t !== 'error' && t !== 'info') {
      t = 'info';
    }
    var container = ensureMailStyleToastContainer();
    var toastEl = document.createElement('div');
    toastEl.className = 'toast toast-' + t;
    toastEl.setAttribute('role', 'status');
    var messageSpan = document.createElement('span');
    messageSpan.className = 'toast-message';
    messageSpan.textContent = message;
    toastEl.appendChild(messageSpan);
    container.appendChild(toastEl);
    requestAnimationFrame(function () {
      toastEl.classList.add('toast-show');
    });
    var duration = t === 'success' ? 2000 : 3000;
    setTimeout(function () {
      toastEl.classList.remove('toast-show');
      toastEl.classList.add('toast-hide');
      setTimeout(function () {
        if (toastEl.parentNode) {
          toastEl.remove();
        }
      }, 300);
    }, duration);
  }

  function showError(msg) {
    var e = el('emailNotifError');
    if (!e) return;
    e.textContent = msg || '';
    e.classList.toggle('d-none', !msg);
  }

  function setTogglesFromEffective(eff) {
    if (!eff) return;
    if (el('emailNotifAdmin')) el('emailNotifAdmin').checked = !!eff.admin;
    if (el('emailNotifStaff')) el('emailNotifStaff').checked = !!eff.staff;
    if (el('emailNotifClient')) el('emailNotifClient').checked = !!eff.client;
    if (el('emailNotifMention')) el('emailNotifMention').checked = !!eff.mention;
  }

  function getCsrf() {
    if (window.csrfToken) return window.csrfToken;
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function parseJsonResponse(r) {
    return r.text().then(function (text) {
      try {
        return text ? JSON.parse(text) : {};
      } catch (e) {
        return { ok: false, message: text || r.statusText || 'Invalid response' };
      }
    });
  }

  function openEmailNotificationModal(contextType, contextId) {
    var st = getState();
    st.currentType = contextType;
    st.currentId = parseInt(contextId, 10) || 0;
    comonLogEmailNotif('openEmailNotificationModal called', { contextType: contextType, contextId: contextId, resolvedId: st.currentId });
    if (!st.currentType || !st.currentId) {
      comonLogEmailNotif('abort: missing contextType or contextId (invalid after parse)');
      return;
    }
    showError('');
    var base = getBase();
    var u = base + 'ajax/chat_email_context_api.php?action=get_settings&context_type=' + encodeURIComponent(st.currentType) + '&context_id=' + encodeURIComponent(String(st.currentId));
    comonLogEmailNotif('fetch get_settings', { base: base, url: u });
    fetch(u, { credentials: 'same-origin' })
      .then(function (r) {
        comonLogEmailNotif('fetch response http', r.status, r.ok);
        return parseJsonResponse(r);
      })
      .then(function (data) {
        comonLogEmailNotif('get_settings json', data);
        if (!data.ok) {
          comonLogEmailNotif('blocked by API (permission or validation)', data.message || '');
          showError(data.message || 'Failed to load');
          return;
        }
        st.uiMode = data.ui_mode || 'full';
        st.selfKey = data.self_key || null;
        st.saveMode = data.save_mode || 'none';
        st.canSave = !!data.can_save;
        st.canResetGlobal = !!data.can_reset_global;
        st.hideClientRow = !!data.hide_client_row;
        st.hasOverrideRow = !!(data.override);
        applyNotifLayout();
        if (el('emailNotifReadonly')) {
          el('emailNotifReadonly').classList.toggle('d-none', st.canSave);
        }
        if (el('emailNotifSave')) {
          el('emailNotifSave').disabled = !st.canSave;
        }
        if (el('emailNotifResetGlobal')) {
          el('emailNotifResetGlobal').disabled = (!st.canSave || !st.canResetGlobal);
        }
        setTogglesFromEffective(data.effective);
        if (st.uiMode === 'self' && st.selfKey === 'staff' && typeof data.user_email_enabled !== 'undefined') {
          if (el('emailNotifStaff')) el('emailNotifStaff').checked = !!data.user_email_enabled;
        }
        if (st.uiMode === 'self' && st.selfKey === 'client' && typeof data.user_email_enabled !== 'undefined') {
          if (el('emailNotifClient')) el('emailNotifClient').checked = !!data.user_email_enabled;
        }
        var kind = st.currentType ? (st.currentType.charAt(0).toUpperCase() + st.currentType.slice(1)) : '';
        if (el('emailNotifContextKind')) {
          el('emailNotifContextKind').textContent = kind;
        }
        if (el('emailNotifContextId')) {
          el('emailNotifContextId').textContent = '#' + String(st.currentId);
        }
        var modalEl = el('emailNotificationModal');
        if (!modalEl) {
          comonLogEmailNotif('abort: #emailNotificationModal not in DOM');
          return;
        }
        // Always reuse one instance — "new bootstrap.Modal()" each time stacks multiple .modal-backdrop
        var M = (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) ? window.bootstrap.Modal : null;
        if (!M) {
          comonLogEmailNotif('abort: window.bootstrap.Modal missing (Bootstrap JS not loaded?)');
          return;
        }
        var m = (typeof M.getOrCreateInstance === 'function') ? M.getOrCreateInstance(modalEl) : new M(modalEl);
        comonLogEmailNotif('show modal');
        m.show();
      })
      .catch(function (err) {
        comonLogEmailNotif('fetch catch', err && err.message ? err.message : err);
        showError('Network error');
      });
  }

  function postSave(body) {
    var st = getState();
    var token = getCsrf();
    var b = body || {};
    b.csrf_token = token;
    b.context_type = st.currentType;
    b.context_id = st.currentId;
    return fetch(getBase() + 'ajax/chat_email_context_api.php?action=save_settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: JSON.stringify(b),
      credentials: 'same-origin',
    }).then(function (r) {
      return parseJsonResponse(r).then(function (data) {
        if (!r.ok && (!data || data.ok !== false)) {
          data = data || {};
          data.ok = false;
          data.message = data.message || ('HTTP ' + r.status);
        }
        return data;
      });
    });
  }

  function hideEmailModal() {
    var modalEl = el('emailNotificationModal');
    if (!modalEl || typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
      return;
    }
    var inst = window.bootstrap.Modal.getInstance(modalEl) || (typeof window.bootstrap.Modal.getOrCreateInstance === 'function' ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : null);
    if (inst) {
      inst.hide();
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var modalZ = el('emailNotificationModal');
    if (modalZ && !window.__comonEmailNotifZStackBound) {
      window.__comonEmailNotifZStackBound = true;
      modalZ.addEventListener('show.bs.modal', function () {
        document.body.classList.add('comon-email-notif-backdrop');
      });
      modalZ.addEventListener('hidden.bs.modal', function () {
        document.body.classList.remove('comon-email-notif-backdrop');
      });
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    if (window.__comonEmailNotifModalBound) {
      return;
    }
    window.__comonEmailNotifModalBound = true;
    /**
     * Bind on each link — do not rely on bubbling to document: Bootstrap's dropdown
     * often stops propagation on .dropdown-menu clicks, which broke delegation.
     */
    function resolveTaskIdFromSidebarClick(anchor) {
      var tid = '';
      var side = anchor && anchor.closest ? anchor.closest('.task-sidebar') : null;
      if (side) {
        tid = side.getAttribute('data-task-id') || (side.dataset && side.dataset.taskId) || '';
      }
      if (!tid) {
        try {
          document.querySelectorAll('#task-sidebar').forEach(function (el) {
            var x = el.getAttribute('data-task-id') || (el.dataset && el.dataset.taskId);
            if (x && !tid) tid = x;
          });
        } catch (e2) {}
      }
      if (!tid && typeof window.TASK_CHAT_ID !== 'undefined' && window.TASK_CHAT_ID) {
        tid = String(window.TASK_CHAT_ID);
      }
      if (!tid && typeof window.getCurrentTaskId === 'function') {
        try {
          var gt = window.getCurrentTaskId();
          if (gt) tid = String(gt);
        } catch (e3) {}
      }
      return parseInt(tid, 10) || 0;
    }

    function bindTaskEmailNotifLinks() {
      document.querySelectorAll('a.js-task-email-notif').forEach(function (anchor) {
        if (anchor.getAttribute('data-comon-email-notif-bound') === '1') {
          return;
        }
        anchor.setAttribute('data-comon-email-notif-bound', '1');
        anchor.addEventListener('click', function (e) {
          e.preventDefault();
          var tid = resolveTaskIdFromSidebarClick(anchor);
          comonLogEmailNotif('task sidebar Notifications click', { tid: tid });
          if (!tid) {
            comonLogEmailNotif('task id is 0 — duplicate #task-sidebar without data-task-id? Set window.comonEmailNotifDebug=true');
          }
          if (tid && typeof openEmailNotificationModal === 'function') {
            openEmailNotificationModal('task', tid);
          }
        });
      });
    }
    bindTaskEmailNotifLinks();
    var saveBtn = el('emailNotifSave');
    if (saveBtn) {
      saveBtn.addEventListener('click', function () {
        var st = getState();
        if (!st.canSave) {
          toast((typeof window.__comonEmailNotifReadOnlyMsg === 'string' && window.__comonEmailNotifReadOnlyMsg) ? window.__comonEmailNotifReadOnlyMsg : 'You do not have permission to change these settings.', 'error');
          return;
        }
        if (!st.canResetGlobal) {
          return;
        }
        showError('');
        var payload;
        if (st.uiMode === 'self' && st.selfKey === 'staff') {
          payload = {
            email_enabled: el('emailNotifStaff') && el('emailNotifStaff').checked ? 1 : 0
          };
        } else if (st.uiMode === 'self' && st.selfKey === 'client') {
          payload = {
            email_enabled: el('emailNotifClient') && el('emailNotifClient').checked ? 1 : 0
          };
        } else {
          payload = {
            o_admin: el('emailNotifAdmin') && el('emailNotifAdmin').checked ? 1 : 0,
            o_staff: el('emailNotifStaff') && el('emailNotifStaff').checked ? 1 : 0,
            o_client: el('emailNotifClient') && el('emailNotifClient').checked ? 1 : 0,
            o_mention: el('emailNotifMention') && el('emailNotifMention').checked ? 1 : 0,
            use_system_defaults: 0
          };
        }
        postSave(payload).then(function (data) {
          if (data && data.ok) {
            toast(typeof window.__comonEmailNotifSavedMsg === 'string' ? window.__comonEmailNotifSavedMsg : (typeof window.comonNotificationsSettingsSaved === 'string' ? window.comonNotificationsSettingsSaved : 'Notifications settings saved.'), 'success');
            hideEmailModal();
            st.hasOverrideRow = true;
          } else {
            var msg = (data && data.message) || 'Save failed';
            showError(msg);
            toast(msg, 'error');
          }
        }).catch(function () {
          showError('Network error');
          toast('Network error', 'error');
        });
      });
    }
    var resetBtn = el('emailNotifResetGlobal');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        var st = getState();
        if (!st.canSave) {
          toast((typeof window.__comonEmailNotifReadOnlyMsg === 'string' && window.__comonEmailNotifReadOnlyMsg) ? window.__comonEmailNotifReadOnlyMsg : 'You do not have permission to change these settings.', 'error');
          return;
        }
        showError('');
        postSave({ use_system_defaults: 1 }).then(function (data) {
          if (data && data.ok) {
            setTogglesFromEffective(data.effective);
            toast(typeof window.__comonEmailNotifResetMsg === 'string' ? window.__comonEmailNotifResetMsg : 'Restored system defaults for this context.', 'success');
            hideEmailModal();
            st.hasOverrideRow = false;
          } else {
            var msg = (data && data.message) || 'Reset failed';
            showError(msg);
            toast(msg, 'error');
          }
        }).catch(function () {
          showError('Network error');
          toast('Network error', 'error');
        });
      });
    }
  });

  window.openEmailNotificationModal = openEmailNotificationModal;
  window.comonLogEmailNotif = comonLogEmailNotif;
})();
