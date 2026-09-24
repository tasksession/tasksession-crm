/**
 * Global online/offline presence toasts (bottom-right).
 * Config: window.PRESENCE_TOAST = { ajaxUrl, pollMs?, enabled? }
 * Staff/admin: auto-dismiss 5s. Client: 10 min + overlap stack.
 * Active toasts survive refresh via sessionStorage.
 */
(function (global) {
  'use strict';

  var cfg = global.PRESENCE_TOAST || {};
  if (cfg.enabled === false || !cfg.ajaxUrl) {
    return;
  }

  var POLL_MS = typeof cfg.pollMs === 'number' && cfg.pollMs >= 10000 ? cfg.pollMs : 25000;
  var DISMISS_STAFF_MS = 5000;
  var DISMISS_CLIENT_MS = 10 * 60 * 1000;
  var STORAGE_KEY = 'comon_presence_toast_online_v1';
  var ACTIVE_KEY = 'comon_presence_toast_active_v1';
  var toastEnabled = cfg.toastEnabled !== false;
  var beepEnabled = cfg.beepEnabled !== false;
  var staffToastEnabled = cfg.staffToastEnabled !== false;
  var knownOnline = null;
  var pollTimer = null;
  var fetching = false;
  var activeToasts = [];
  var presenceAudio = null;
  var audioUnlocked = false;
  var CATCHUP_KEY = 'comon_presence_catchup_v1';
  var AUDIO_UNLOCK_KEY = 'comon_presence_audio_unlocked_v1';
  var catchupHandled = false;

  function isClientUser(user) {
    return parseInt(user && user.account_status, 10) === 2;
  }

  function shouldShowToast(user) {
    if (!toastEnabled) {
      return false;
    }
    if (!staffToastEnabled && !isClientUser(user)) {
      return false;
    }
    return true;
  }

  function readAudioUnlocked() {
    try {
      return sessionStorage.getItem(AUDIO_UNLOCK_KEY) === '1';
    } catch (e) {
      return false;
    }
  }

  function markAudioUnlocked() {
    audioUnlocked = true;
    try {
      sessionStorage.setItem(AUDIO_UNLOCK_KEY, '1');
    } catch (e) {
      // ignore
    }
  }

  function ensurePresenceAudio() {
    if (!beepEnabled) {
      return null;
    }
    if (presenceAudio) {
      return presenceAudio;
    }
    var beepUrl = cfg.beepUrl || ((cfg.baseUrl || global.baseUrl || '/').replace(/\/?$/, '/') + 'assets/beep/online.mp4');
    try {
      presenceAudio = new Audio(beepUrl);
      // Do not preload — avoids an eager online.mp4 network hit on every page.
      presenceAudio.preload = 'none';
    } catch (e) {
      presenceAudio = null;
    }
    return presenceAudio;
  }

  function initBeep() {
    if (!beepEnabled) {
      return;
    }
    // Remember unlock across in-app navigations so mobile link taps don't re-play.
    if (readAudioUnlocked()) {
      audioUnlocked = true;
      return;
    }
    var unlock = function () {
      if (audioUnlocked) {
        return;
      }
      var audio = ensurePresenceAudio();
      if (!audio) {
        return;
      }
      // Mute during unlock — otherwise mobile hears a beep when tapping a nav link.
      var prevMuted = !!audio.muted;
      audio.muted = true;
      audio.play().then(function () {
        audio.pause();
        audio.currentTime = 0;
        audio.muted = prevMuted;
        markAudioUnlocked();
      }).catch(function () {
        if (audio) {
          audio.muted = prevMuted;
        }
        // still locked
      });
      document.removeEventListener('click', unlock);
      document.removeEventListener('keydown', unlock);
      document.removeEventListener('touchstart', unlock);
      document.removeEventListener('pointerdown', unlock);
    };
    document.addEventListener('click', unlock);
    document.addEventListener('keydown', unlock);
    document.addEventListener('touchstart', unlock, { passive: true });
    document.addEventListener('pointerdown', unlock, { passive: true });
  }

  function playClientOnlineBeep() {
    if (!beepEnabled) {
      return;
    }
    var audio = ensurePresenceAudio();
    if (!audio) {
      return;
    }
    try {
      audio.muted = false;
      audio.currentTime = 0;
      audio.play().catch(function () {
        // silent — needs prior user gesture unlock
      });
    } catch (e) {
      // ignore
    }
  }

  function toastKey(userId, kind) {
    return String(userId) + ':' + (kind === 'offline' ? 'offline' : (kind === 'catchup' ? 'catchup' : 'online'));
  }

  function catchupAlreadyShown(key) {
    if (!key) {
      return false;
    }
    try {
      return sessionStorage.getItem(CATCHUP_KEY) === String(key);
    } catch (e) {
      return false;
    }
  }

  function markCatchupShown(key) {
    try {
      sessionStorage.setItem(CATCHUP_KEY, String(key));
    } catch (e) {
      // ignore
    }
  }

  function showCatchupClients(list, key) {
    if (catchupHandled || !toastEnabled) {
      return;
    }
    if (!Array.isArray(list) || list.length === 0 || catchupAlreadyShown(key)) {
      catchupHandled = true;
      return;
    }
    catchupHandled = true;
    markCatchupShown(key);
    var anyClient = false;
    list.forEach(function (u) {
      if (!isClientUser(u)) {
        return;
      }
      anyClient = true;
      var still = !!u.still_online;
      var dur = u.duration_label || '';
      var statusText = still
        ? ('online while you were away' + (dur ? ' · ' + dur : ''))
        : ('was online while you were away' + (dur ? ' · ' + dur : ''));
      renderToast(u, still ? 'online' : 'offline', {
        statusText: statusText,
        kindKey: 'catchup'
      });
    });
    if (anyClient) {
      playClientOnlineBeep();
    }
  }

  function ensureContainer() {
    var el = document.getElementById('presenceToastContainer');
    if (el) {
      return el;
    }
    el = document.createElement('div');
    el.id = 'presenceToastContainer';
    el.className = 'presence-toast-container';
    el.setAttribute('aria-live', 'polite');

    var clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.id = 'presenceToastClearAll';
    clearBtn.className = 'presence-toast-clear';
    clearBtn.textContent = 'Clear all';
    clearBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      clearAllToasts();
    });

    var stack = document.createElement('div');
    stack.id = 'presenceToastStack';
    stack.className = 'presence-toast-stack';

    el.appendChild(clearBtn);
    el.appendChild(stack);
    document.body.appendChild(el);
    return el;
  }

  function getStack() {
    ensureContainer();
    return document.getElementById('presenceToastStack');
  }

  function syncClearButton() {
    var el = document.getElementById('presenceToastContainer');
    var stack = document.getElementById('presenceToastStack');
    if (!el || !stack) {
      return;
    }
    // Ignore toasts mid-dismiss so Clear all hides with the last card.
    var count = stack.querySelectorAll('.presence-toast:not(.toast-hide)').length;
    if (count > 0) {
      el.classList.add('has-toasts');
    } else {
      el.classList.remove('has-toasts');
    }
  }

  function clearAllToasts() {
    var el = document.getElementById('presenceToastContainer');
    var stack = document.getElementById('presenceToastStack');
    if (stack) {
      var toasts = stack.querySelectorAll('.presence-toast');
      Array.prototype.forEach.call(toasts, function (toast) {
        var key = toast.getAttribute('data-presence-key');
        if (key) {
          removeActive(key);
        }
        if (toast.parentNode) {
          toast.remove();
        }
      });
    }
    activeToasts = [];
    saveActive();
    if (el) {
      el.classList.remove('has-toasts');
    }
    syncClearButton();
  }

  function loadKnown() {
    try {
      var raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) {
        return null;
      }
      var parsed = JSON.parse(raw);
      if (!parsed || typeof parsed !== 'object' || !Array.isArray(parsed.ids)) {
        return null;
      }
      return parsed;
    } catch (e) {
      return null;
    }
  }

  function saveKnown(map) {
    try {
      var ids = Object.keys(map || {}).map(function (id) {
        return parseInt(id, 10);
      }).filter(function (id) {
        return id > 0;
      });
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
        ids: ids,
        users: map || {},
        ts: Date.now()
      }));
    } catch (e) {
      // ignore
    }
  }

  function loadActive() {
    try {
      var raw = sessionStorage.getItem(ACTIVE_KEY);
      if (!raw) {
        return [];
      }
      var parsed = JSON.parse(raw);
      if (!parsed || !Array.isArray(parsed.items)) {
        return [];
      }
      var now = Date.now();
      return parsed.items.filter(function (item) {
        return item && item.user && item.expiresAt > now;
      });
    } catch (e) {
      return [];
    }
  }

  function saveActive() {
    try {
      var now = Date.now();
      activeToasts = (activeToasts || []).filter(function (item) {
        return item && item.expiresAt > now;
      });
      sessionStorage.setItem(ACTIVE_KEY, JSON.stringify({ items: activeToasts }));
    } catch (e) {
      // ignore
    }
  }

  function removeActive(key) {
    activeToasts = (activeToasts || []).filter(function (item) {
      return item.key !== key;
    });
    saveActive();
  }

  function upsertActive(item) {
    activeToasts = (activeToasts || []).filter(function (existing) {
      return existing.key !== item.key;
    });
    activeToasts.push(item);
    saveActive();
  }

  function mapFromUsers(users) {
    var map = {};
    (users || []).forEach(function (u) {
      var id = parseInt(u && u.id, 10);
      if (id > 0) {
        map[id] = u;
      }
    });
    return map;
  }

  function dismissToast(toast, skipSync) {
    if (!toast) {
      return;
    }
    var key = toast.getAttribute('data-presence-key');
    if (key) {
      removeActive(key);
    }
    if (!toast.parentNode) {
      if (!skipSync) {
        syncClearButton();
      }
      return;
    }
    toast.classList.remove('toast-show');
    toast.classList.add('toast-hide');
    setTimeout(function () {
      if (toast.parentNode) {
        toast.remove();
      }
      if (!skipSync) {
        syncClearButton();
      }
    }, 300);
  }

  function chatUrlForUser(user) {
    var id = parseInt(user && user.id, 10);
    if (!(id > 0)) {
      return '';
    }
    var base = (cfg.baseUrl || global.baseUrl || '/').replace(/\/?$/, '/');
    return base + 'chatting.php?user=' + id;
  }

  /**
   * @param {object} user
   * @param {string} kind online|offline
   * @param {{remainingMs?: number, skipPersist?: boolean, statusText?: string, kindKey?: string}} opts
   */
  function renderToast(user, kind, opts) {
    opts = opts || {};
    if (!shouldShowToast(user)) {
      return;
    }
    ensureContainer();
    var stack = getStack();
    var offline = kind === 'offline';
    var client = isClientUser(user);
    var uid = parseInt(user && user.id, 10) || 0;
    var keyKind = opts.kindKey || kind;
    var key = toastKey(uid, keyKind);
    var ttl = client ? DISMISS_CLIENT_MS : DISMISS_STAFF_MS;
    var remaining = typeof opts.remainingMs === 'number' ? opts.remainingMs : ttl;
    if (remaining <= 0) {
      return;
    }

    // Replace existing toast for same user+kind
    var existing = stack.querySelector('[data-presence-key="' + key + '"]');
    if (existing) {
      existing.remove();
    }

    var toast = document.createElement('div');
    toast.className = [
      'presence-toast',
      'widget-card',
      'd-flex',
      'align-items-center',
      'col-gap-10',
      'position-relative',
      offline ? 'presence-toast--offline' : 'presence-toast--online',
      client ? 'presence-toast--client' : 'presence-toast--staff'
    ].join(' ');
    toast.setAttribute('role', 'link');
    toast.setAttribute('tabindex', '0');
    toast.setAttribute('data-presence-key', key);
    toast.title = 'Open chat';

    var avatarWrap = document.createElement('div');
    avatarWrap.className = 'presence-toast__avatar rounded-circle';
    if (user.avatar_url) {
      var img = document.createElement('img');
      img.src = user.avatar_url;
      img.alt = '';
      img.width = 36;
      img.height = 36;
      img.className = 'rounded-circle';
      avatarWrap.appendChild(img);
    } else {
      var colorIdx = parseInt(user.color_index, 10);
      if (!(colorIdx >= 1 && colorIdx <= 8)) {
        colorIdx = (uid % 8) + 1;
      }
      var initials = document.createElement('span');
      initials.className = 'avatar-initials avatar-initials-medium color-' + colorIdx;
      initials.textContent = user.initials || 'U';
      avatarWrap.appendChild(initials);
    }

    var body = document.createElement('div');
    body.className = 'presence-toast__body';
    var nameEl = document.createElement('div');
    nameEl.className = 'presence-toast__name font-size-14 font-weight-bold text-truncate';
    nameEl.textContent = user.full_name || 'User';
    var statusEl = document.createElement('div');
    statusEl.className = 'presence-toast__status grey font-size-12';
    if (opts.statusText) {
      statusEl.textContent = opts.statusText;
    } else {
      statusEl.textContent = offline ? 'went offline' : 'is online';
    }
    body.appendChild(nameEl);
    body.appendChild(statusEl);

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'presence-toast__close';
    closeBtn.setAttribute('aria-label', 'Dismiss');
    closeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
    closeBtn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      dismissToast(toast);
    });

    toast.appendChild(avatarWrap);
    toast.appendChild(body);
    toast.appendChild(closeBtn);

    toast.addEventListener('click', function () {
      var href = chatUrlForUser(user);
      if (href) {
        global.location.href = href;
      }
    });
    toast.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toast.click();
      }
    });

    if (client) {
      stack.appendChild(toast);
    } else {
      var firstClient = stack.querySelector('.presence-toast--client');
      if (firstClient) {
        stack.insertBefore(toast, firstClient);
      } else {
        stack.appendChild(toast);
      }
    }

    if (!opts.skipPersist) {
      upsertActive({
        key: key,
        kind: offline ? 'offline' : 'online',
        user: user,
        expiresAt: Date.now() + remaining
      });
    }

    syncClearButton();

    requestAnimationFrame(function () {
      toast.classList.add('toast-show');
    });

    setTimeout(function () {
      dismissToast(toast);
    }, remaining);
  }

  function restoreActiveToasts() {
    activeToasts = loadActive();
    saveActive();
    var now = Date.now();
    activeToasts.forEach(function (item) {
      var remaining = item.expiresAt - now;
      if (remaining > 0 && item.user) {
        renderToast(item.user, item.kind, {
          remainingMs: remaining,
          skipPersist: true
        });
      }
    });
  }

  function processUsers(users) {
    var nextMap = mapFromUsers(users);
    if (knownOnline === null) {
      knownOnline = nextMap;
      saveKnown(knownOnline);
      return;
    }

    Object.keys(nextMap).forEach(function (id) {
      if (!knownOnline[id]) {
        var user = nextMap[id];
        if (isClientUser(user)) {
          playClientOnlineBeep();
        }
        renderToast(user, 'online');
      }
    });

    Object.keys(knownOnline).forEach(function (id) {
      if (!nextMap[id]) {
        renderToast(knownOnline[id], 'offline');
      }
    });

    knownOnline = nextMap;
    saveKnown(knownOnline);
  }

  function poll() {
    if (fetching) {
      return;
    }
    fetching = true;
    fetch(cfg.ajaxUrl, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
      cache: 'no-store'
    })
      .then(function (res) {
        if (!res.ok) {
          throw new Error('presence poll failed');
        }
        return res.json();
      })
      .then(function (data) {
        if (!data || data.success === false || !Array.isArray(data.users)) {
          return;
        }
        processUsers(data.users);
        if (Array.isArray(data.catchup)) {
          showCatchupClients(data.catchup, data.catchup_key || '');
        }
      })
      .catch(function () {
        // silent
      })
      .then(function () {
        fetching = false;
      });
  }

  function start() {
    initBeep();
    // Don't reuse prior online snapshot across pages — first poll seeds; catch-up still runs once per login.
    try {
      sessionStorage.removeItem(STORAGE_KEY);
    } catch (e) {
      // ignore
    }
    knownOnline = null;
    catchupHandled = false;
    if (toastEnabled) {
      restoreActiveToasts();
    }
    poll();
    pollTimer = setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', function () {
      // Keep polling in background tabs so client-online beep still fires.
      poll();
    });
  }

  function scheduleStart() {
    if (typeof window.comonAfterPageQuiet === 'function') {
      window.comonAfterPageQuiet(start, 3000);
      return;
    }
    if (document.readyState === 'complete') {
      setTimeout(start, 3000);
    } else {
      window.addEventListener('load', function () {
        setTimeout(start, 3000);
      });
    }
  }

  scheduleStart();
})(window);
