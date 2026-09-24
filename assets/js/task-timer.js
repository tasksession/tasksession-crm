/* global fetch, window, document */
(function () {
  'use strict';

  function cfg() {
    return window.__tasksessionTimeTracking && window.__tasksessionTimeTracking.enabled
      ? window.__tasksessionTimeTracking
      : {
        enabled: false,
        isClient: false,
        clientTimerReadOnly: false,
        accountStatus: 0,
        canMutateTimer: false,
        canEditEstimateInSidebar: false,
        userId: 0,
        timerLogShowManual: true
      };
  }

  function apiUrl() {
    var c = cfg();
    return c.apiUrl || (typeof window.baseUrl === 'string' ? window.baseUrl : '') + 'ajax/task_timer.php';
  }

  function normSessionStatus(sess) {
    return sess ? String(sess.status == null ? '' : sess.status).trim().toLowerCase() : '';
  }

  function sessionRunning(sess) {
    return normSessionStatus(sess) === 'running';
  }

  function sessionPaused(sess) {
    return normSessionStatus(sess) === 'paused';
  }

  function getSessionsList(data) {
    var d = data || state.lastServerState;
    if (!d || d.status !== 'ok') return [];
    return Array.isArray(d.sessions) ? d.sessions : [];
  }

  function getSessionForTask(tid, data) {
    var t = parseInt(String(tid || ''), 10) || 0;
    if (!t) return null;
    var list = getSessionsList(data);
    for (var i = 0; i < list.length; i++) {
      if (parseInt(String(list[i].task_id), 10) === t) return list[i];
    }
    return null;
  }

  function kanbanTimerBadgeLabels() {
    var s = cfg().strings || {};
    return {
      active: s.kanbanTimerActive || 'Timer Active',
      paused: s.kanbanTimerPaused || 'Timer Paused'
    };
  }

  function syncKanbanTimerBadges(data) {
    var cards = document.querySelectorAll('.board-wrap .task-card[data-id]');
    if (!cards.length) return;
    var labels = kanbanTimerBadgeLabels();
    var d = data && data.status === 'ok' ? normalizeTimerState(data) : null;
    var active = {};
    if (d && d.status === 'ok') {
      getSessionsList(d).forEach(function (e) {
        var t = parseInt(String(e.task_id || ''), 10) || 0;
        if (t) active[t] = sessionRunning(e) ? 'run' : 'pause';
      });
    }
    var i;
    for (i = 0; i < cards.length; i++) {
      var card = cards[i];
      var tid = parseInt(String(card.getAttribute('data-id') || ''), 10) || 0;
      var badge = card.querySelector('.task-card-timer-badge');
      if (!badge || !tid) continue;
      var st = active[tid];
      if (st) {
        var label = st === 'run' ? labels.active : labels.paused;
        var textEl = badge.querySelector('.task-card-timer-badge-text');
        if (textEl) textEl.textContent = label;
        else badge.textContent = label;
        badge.setAttribute('title', label);
        badge.setAttribute('aria-label', label);
        badge.classList.remove('d-none');
        if (st === 'run') {
          badge.classList.add('task-card-timer-badge--running');
          badge.classList.remove('task-card-timer-badge--paused');
        } else {
          badge.classList.remove('task-card-timer-badge--running');
          badge.classList.add('task-card-timer-badge--paused');
        }
      } else {
        badge.classList.add('d-none');
        badge.classList.remove('task-card-timer-badge--running');
        badge.classList.remove('task-card-timer-badge--paused');
      }
    }
  }

  function normalizeTimerState(data) {
    if (!data || data.status !== 'ok') return data;
    if (Array.isArray(data.sessions)) return data;
    data.sessions = [];
    if (data.session && data.session.task_id) {
      data.sessions.push({
        user_id: data.session.user_id,
        task_id: data.session.task_id,
        project_id: data.session.project_id,
        status: data.session.status,
        segment_started_at: data.session.segment_started_at,
        accumulated_seconds: data.session.accumulated_seconds,
        elapsed_display_seconds: data.elapsed_display_seconds != null ? Number(data.elapsed_display_seconds) : 0,
        task_title: data.task_title != null ? String(data.task_title) : '',
        project_title: data.project_title != null ? String(data.project_title) : '',
        task_estimated_seconds: data.task_estimated_seconds
      });
    }
    return data;
  }

  /** Live elapsed for one session row (uses header baseline only for the globally running row). */
  function getElapsedForTaskDisplay(entry, data) {
    if (!entry) return 0;
    var base = Number(entry.elapsed_display_seconds);
    if (!isFinite(base)) base = 0;
    base = Math.max(0, Math.floor(base));
    var prim = data && data.session;
    if (!sessionRunning(entry) || !state.headerElapsedBaselineAt || !prim) return base;
    if (parseInt(String(prim.task_id), 10) !== parseInt(String(entry.task_id), 10)) return base;
    var deltaSec = Math.floor((Date.now() - state.headerElapsedBaselineAt) / 1000);
    return base + Math.max(0, deltaSec);
  }

  function timerDebugAppend() {
    /* Debug UI removed for production; calls kept as no-ops. */
  }

  function formatHoursDecimal(sec) {
    var s = Math.max(0, parseInt(String(sec || 0), 10) || 0);
    var h = s / 3600;
    var t = Math.round(h * 10) / 10;
    if (!isFinite(t)) return '—';
    if (t <= 0) return '0h';
    if (t === Math.floor(t)) return String(Math.floor(t)) + 'h';
    return t.toFixed(1) + 'h';
  }

  function formatPlannedMeta(sec) {
    var s = parseInt(String(sec || 0), 10) || 0;
    if (s <= 0) return '—';
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    if (h > 0) return h + ':' + (m < 10 ? '0' : '') + m + 'h';
    return Math.max(1, m) + 'm';
  }

  function formatScheduleDateStack(ymd) {
    var raw = String(ymd || '').trim();
    if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
      var p = raw.split('-');
      var d = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
      if (!isNaN(d.getTime())) {
        var pad = function (n) { return n < 10 ? '0' + n : String(n); };
        return formatLogDateStack(d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()));
      }
    }
    return formatLogDateStack(ymd);
  }

  function updateScheduleElapsedOnly(data) {
    var rows = document.querySelectorAll('#tasksession-timer-work-list .tasksession-timer-work-row[data-is-mine="1"]');
    if (!rows.length) return;
    ensureActiveScheduleRowForSession();
    var elapsed = formatDuration(0);
    var tid = state.sidebarTaskId;
    var d = data || state.lastServerState;
    var sess = cfg().canMutateTimer && d && d.status === 'ok' ? getSessionForTask(tid, d) : null;
    var live = !!(sess && (sessionRunning(sess) || sessionPaused(sess)));
    if (live) {
      elapsed = formatDuration(getElapsedForTaskDisplay(sess, d));
    }
    rows.forEach(function (row) {
      var out = row.querySelector('.tasksession-timer-sched-elapsed');
      if (!out) return;
      if (isScheduleRowActive(row) && live) {
        out.textContent = elapsed;
      } else {
        out.textContent = '00:00:00';
      }
    });
  }

  function updateProgressFromSummary(sum) {
    var track = el('tasksession-timer-progress-track');
    var pctEl = el('tasksession-timer-meta-pct');
    if (!track) return;
    var est = sum && sum.estimated_seconds != null ? parseInt(String(sum.estimated_seconds), 10) : 0;
    var logged = sum && sum.logged_seconds != null ? parseInt(String(sum.logged_seconds), 10) : 0;
    var pct = 0;
    if (est > 0) {
      pct = Math.min(100, Math.round((logged / est) * 100));
    }
    track.style.setProperty('--tasksession-progress', pct + '%');
    var suf = (cfg().strings && cfg().strings.percentDone) ? String(cfg().strings.percentDone) : '% done';
    if (pctEl) pctEl.textContent = String(pct) + suf;
  }

  function updateStatActive(data) {
    var a = el('tasksession-timer-stat-active');
    if (!a) return;
    var list = getSessionsList(data);
    if (!data || data.status !== 'ok' || !list.length) {
      a.textContent = '0';
      return;
    }
    a.textContent = String(list.length);
  }

  function formatDashDurationHm(sec) {
    sec = Math.max(0, parseInt(String(sec || 0), 10) || 0);
    var h = Math.floor(sec / 3600);
    var m = Math.floor((sec % 3600) / 60);
    if (h > 0) return h + 'h ' + m + 'm';
    return m + 'm';
  }

  function formatDashEntriesTodayLabel(count) {
    var str = cfg().strings || {};
    var today = str.today || 'today';
    count = Math.max(0, parseInt(String(count || 0), 10) || 0);
    if (count === 1) {
      return (str.taskTimeEntryToday || '1 entry') + ' \u00b7 ' + today;
    }
    var tpl = str.taskTimeEntriesToday || '%d entries';
    return tpl.replace('%d', String(count)) + ' \u00b7 ' + today;
  }

  function formatDashShiftRemainingNote(elapsedSec, targetSec, shiftCard) {
    var str = cfg().strings || {};
    shiftCard = shiftCard || {};
    if (!shiftCard.checked_in) {
      return { text: str.notCheckedIn || 'Not checked in', color: '#ef4444' };
    }
    if (shiftCard.checked_out) {
      return { text: str.shiftEnded || 'Shift ended', color: '#888' };
    }
    var remaining = Math.max(0, targetSec - elapsedSec);
    if (remaining <= 0) {
      return { text: str.shiftComplete || 'Shift complete', color: 'green' };
    }
    var tpl = str.shiftTimeLeftNote || '%s left';
    return { text: tpl.replace('%s', formatDashDurationHm(remaining)), color: '#888' };
  }

  function staffDashGraphScaleTarget(loggedSec, targetSec) {
    targetSec = parseInt(String(targetSec || 0), 10) || 0;
    loggedSec = Math.max(0, parseInt(String(loggedSec || 0), 10) || 0);
    if (targetSec <= 0) return 3600;
    var floor = 3600;
    var zoom = Math.max(floor, loggedSec + 600);
    return Math.min(targetSec, zoom);
  }

  function hasAnyRunningSession(data) {
    data = data || state.lastServerState;
    if (!data || data.status !== 'ok') return false;
    if (data.session && sessionRunning(data.session)) return true;
    var list = getSessionsList(data);
    for (var i = 0; i < list.length; i++) {
      if (sessionRunning(list[i])) return true;
    }
    return false;
  }

  function markDashLoggedBaselineFromApi(data) {
    if (!data || data.status !== 'ok') {
      state.dashLoggedBaselineAt = 0;
      state.dashLoggedBaselineSec = 0;
      return;
    }
    state.dashLoggedBaselineSec = parseInt(String(data.logged_today_seconds || 0), 10) || 0;
    state.dashLoggedBaselineAt = hasAnyRunningSession(data) ? Date.now() : 0;
    state.dashLoggedDisplaySec = state.dashLoggedBaselineSec;
  }

  function getDashLoggedLiveSeconds() {
    var base = state.dashLoggedBaselineSec != null
      ? state.dashLoggedBaselineSec
      : (parseInt(String(state.dashLoggedDisplaySec || 0), 10) || 0);
    if (!state.dashLoggedBaselineAt || !hasAnyRunningSession()) return base;
    var deltaSec = Math.floor((Date.now() - state.dashLoggedBaselineAt) / 1000);
    return base + Math.max(0, deltaSec);
  }

  function updateStaffDashProgressGraph(prefix, loggedSec, targetSec, cardId) {
    loggedSec = Math.max(0, parseInt(String(loggedSec || 0), 10) || 0);
    targetSec = parseInt(String(targetSec || 0), 10) || 0;
    var graphTargetSec = staffDashGraphScaleTarget(loggedSec, targetSec);
    var cardEl = el(cardId || ('staff-dash-' + prefix + '-card'));

    var sparkWrap = cardEl ? cardEl.querySelector('.stat-sparkline') : null;
    if (sparkWrap) {
      sparkWrap.classList.toggle('stat-sparkline--live', loggedSec > 0);
      sparkWrap.classList.toggle('stat-sparkline--empty', loggedSec <= 0);
    }

    var linePath = statSparklinePathFromNormsJs(staffDashTimeGraphNorms(loggedSec, graphTargetSec));
    var areaPath = linePath + ' L320 70 L0 70 Z';
    var lineEl = el('staff-dash-' + prefix + '-graph-line');
    var areaEl = el('staff-dash-' + prefix + '-graph-area');
    if (lineEl) lineEl.setAttribute('d', linePath);
    if (areaEl) areaEl.setAttribute('d', areaPath);
  }

  function updateStaffDashShiftCard(shiftCard) {
    var card = el('staff-dash-shift-today-card');
    if (!card || !shiftCard) return;

    var elapsedSec = parseInt(String(shiftCard.elapsed_sec || 0), 10) || 0;
    var targetSec = parseInt(String(shiftCard.shift_total_sec || 0), 10) || 0;
    state.dashShiftDisplaySec = elapsedSec;

    card.setAttribute('data-checked-in', shiftCard.checked_in ? '1' : '0');
    card.setAttribute('data-checked-out', shiftCard.checked_out ? '1' : '0');
    card.setAttribute('data-check-in-ts', String(parseInt(String(shiftCard.check_in_ts || 0), 10) || 0));
    card.setAttribute('data-shift-total-sec', String(targetSec));

    var valEl = el('staff-dash-shift-today-value');
    if (valEl) valEl.textContent = formatDashDurationHm(elapsedSec);

    var targetEl = el('staff-dash-shift-today-target');
    if (targetEl) targetEl.textContent = shiftCard.shift_label ? String(shiftCard.shift_label) : '';

    var note = formatDashShiftRemainingNote(elapsedSec, targetSec, shiftCard);
    var noteEl = el('staff-dash-shift-today-note');
    if (noteEl) {
      noteEl.textContent = shiftCard.meta_note ? String(shiftCard.meta_note) : note.text;
      noteEl.style.color = shiftCard.meta_note_color ? String(shiftCard.meta_note_color) : note.color;
    }

    updateStaffDashProgressGraph('shift-today', elapsedSec, targetSec, 'staff-dash-shift-today-card');
  }

  function updateStaffDashTimerCards(data) {
    var loggedEl = el('staff-dash-time-logged-value');
    if (!loggedEl) return;

    var targetSec = data && data.task_graph_target_seconds != null
      ? parseInt(String(data.task_graph_target_seconds), 10) || 0
      : 18000;
    markDashLoggedBaselineFromApi(data);
    var loggedSec = getDashLoggedLiveSeconds();
    var entryCount = data && data.task_entries_count != null
      ? parseInt(String(data.task_entries_count), 10) || 0
      : 0;

    state.dashLoggedDisplaySec = loggedSec;
    loggedEl.textContent = formatDashDurationHm(loggedSec);

    var metaEl = el('staff-dash-time-logged-meta');
    if (metaEl) metaEl.textContent = formatDashEntriesTodayLabel(entryCount);

    updateStaffDashProgressGraph('time-logged', loggedSec, targetSec, 'staff-dash-time-logged-card');

    if (data && data.shift_card) {
      updateStaffDashShiftCard(data.shift_card);
    }
  }

  function tickStaffDashLoggedTime() {
    if (!el('staff-dash-time-logged-value')) return;
    if (!hasAnyRunningSession()) return;
    var loggedSec = getDashLoggedLiveSeconds();
    state.dashLoggedDisplaySec = loggedSec;
    var loggedEl = el('staff-dash-time-logged-value');
    if (loggedEl) loggedEl.textContent = formatDashDurationHm(loggedSec);
    var targetSec = state.lastServerState && state.lastServerState.task_graph_target_seconds != null
      ? parseInt(String(state.lastServerState.task_graph_target_seconds), 10) || 0
      : 18000;
    updateStaffDashProgressGraph('time-logged', loggedSec, targetSec, 'staff-dash-time-logged-card');
  }

  function tickStaffDashShiftTime() {
    var card = el('staff-dash-shift-today-card');
    if (!card) return;
    if (card.getAttribute('data-checked-in') !== '1') return;
    if (card.getAttribute('data-checked-out') === '1') return;
    var checkInTs = parseInt(card.getAttribute('data-check-in-ts') || '0', 10) || 0;
    var targetSec = parseInt(card.getAttribute('data-shift-total-sec') || '0', 10) || 0;
    if (checkInTs <= 0) return;

    var elapsedSec = Math.max(0, Math.floor(Date.now() / 1000) - checkInTs);
    state.dashShiftDisplaySec = elapsedSec;
    var valEl = el('staff-dash-shift-today-value');
    if (valEl) valEl.textContent = formatDashDurationHm(elapsedSec);

    var note = formatDashShiftRemainingNote(elapsedSec, targetSec, {
      checked_in: true,
      checked_out: false
    });
    var noteEl = el('staff-dash-shift-today-note');
    if (noteEl) {
      noteEl.textContent = note.text;
      noteEl.style.color = note.color;
    }

    updateStaffDashProgressGraph('shift-today', elapsedSec, targetSec, 'staff-dash-shift-today-card');
  }

  function startStaffDashCardTicker() {
    if (!el('staff-dash-time-logged-value') && !el('staff-dash-shift-today-value')) return;
    if (state.dashCardTick) clearInterval(state.dashCardTick);
    state.dashCardTick = setInterval(function () {
      tickStaffDashLoggedTime();
      tickStaffDashShiftTime();
    }, 1000);
  }

  function staffDashTimeGraphColor(pct) {
    var t = Math.max(0, Math.min(1, pct / 100));
    var r = Math.round(239 + (34 - 239) * t);
    var g = Math.round(68 + (197 - 68) * t);
    var b = Math.round(68 + (94 - 68) * t);
    return 'rgb(' + r + ',' + g + ',' + b + ')';
  }

  var STAFF_DASH_TIME_BOOT_NORMS = [0.36, 0.50, 0.22, 0.54, 0.28, 0.16, 0.46, 0.32, 0.58, 0.20, 0.42, 0.26];

  function staffDashTimeGraphNorms(loggedSec, targetSec) {
    var pct = targetSec > 0 ? Math.min(1, loggedSec / targetSec) : 0;
    if (pct <= 0) {
      return STAFF_DASH_TIME_BOOT_NORMS.map(function () { return 0.16; });
    }
    var pctNorm = Math.max(0.22, pct);
    return STAFF_DASH_TIME_BOOT_NORMS.map(function (v) {
      return 0.16 + (v - 0.16) * pctNorm;
    });
  }

  function statSparklineCubicPathJs(coords) {
    var n = coords.length;
    if (n < 2) return 'M0 52 L320 52';
    var d = 'M' + coords[0][0].toFixed(2) + ' ' + coords[0][1].toFixed(2);
    var topFloor = 3.5;
    var bottomCeil = 56;
    for (var i = 0; i < n - 1; i++) {
      var p0 = coords[i === 0 ? 0 : i - 1];
      var p1 = coords[i];
      var p2 = coords[i + 1];
      var p3 = coords[(i + 2 >= n) ? n - 1 : i + 2];
      var c1x = p1[0] + (p2[0] - p0[0]) / 6;
      var c1y = p1[1] + (p2[1] - p0[1]) / 6;
      var c2x = p2[0] - (p3[0] - p1[0]) / 6;
      var c2y = p2[1] - (p3[1] - p1[1]) / 6;
      c1y = Math.max(topFloor, Math.min(bottomCeil, c1y));
      c2y = Math.max(topFloor, Math.min(bottomCeil, c2y));
      d += ' C' + c1x.toFixed(2) + ' ' + c1y.toFixed(2)
        + ' ' + c2x.toFixed(2) + ' ' + c2y.toFixed(2)
        + ' ' + p2[0].toFixed(2) + ' ' + p2[1].toFixed(2);
    }
    return d;
  }

  function statSparklinePathFromNormsJs(norms, width, height, padY) {
    width = width || 320;
    height = height || 70;
    padY = padY || 10;
    var n = norms.length;
    if (n < 2) {
      norms = [0.2, 0.2];
      n = 2;
    }
    var padTop = padY;
    var padBottom = padY + 4;
    var usable = Math.max(1, height - padTop - padBottom);
    var step = width / (n - 1);
    var topFloor = Math.max(2, padTop * 0.35);
    var bottomCeil = height - padBottom;
    var coords = [];
    for (var i = 0; i < n; i++) {
      var norm = Math.max(0, Math.min(1, norms[i]));
      var y = (height - padBottom) - (norm * usable);
      coords.push([i * step, Math.max(topFloor, Math.min(bottomCeil, y))]);
    }
    return statSparklineCubicPathJs(coords);
  }

  function syncScheduleChrome() {
    var rows = document.querySelectorAll('#tasksession-timer-work-list .tasksession-timer-work-row');
    if (!rows.length) return;

    var start = el('tasksession-timer-btn-start');
    var pause = el('tasksession-timer-btn-pause');
    var resume = el('tasksession-timer-btn-resume');
    var tid = state.sidebarTaskId;
    var sess = getSessionForTask(tid, state.lastServerState);
    var sameTask = !!(sess && tid && parseInt(String(sess.task_id), 10) === parseInt(String(tid), 10));
    var running = !!(sess && sessionRunning(sess) && sameTask);
    var paused = !!(sess && sessionPaused(sess) && sameTask);
    var live = running || paused;
    if (live) ensureActiveScheduleRowForSession();
    var controlsLocked = !!(start && start.disabled && pause && pause.disabled && resume && resume.disabled);
    var str = cfg().strings || {};

    rows.forEach(function (row) {
      var isMine = row.getAttribute('data-is-mine') === '1';
      var isActive = isMine && isScheduleRowActive(row);
      row.classList.toggle('tasksession-timer-work-row--active-schedule', isActive);
      var rd = row.querySelector('.tasksession-timer-sched-play');
      var more = row.querySelector('.tasksession-timer-work-more');
      var icoPlay = rd ? rd.querySelector('.tasksession-timer-ico-play') : null;
      var icoPause = rd ? rd.querySelector('.tasksession-timer-ico-pause') : null;

      if (!rd) return;
      if (!cfg().canMutateTimer || !isMine) {
        rd.disabled = true;
        if (more) more.disabled = true;
        if (icoPlay && icoPause) {
          icoPlay.classList.remove('d-none');
          icoPause.classList.add('d-none');
        }
        return;
      }

      rd.disabled = controlsLocked;
      if (more) {
        more.disabled = !(live && isActive);
      }

      if (icoPlay && icoPause) {
        if (live && isActive && running) {
          icoPlay.classList.add('d-none');
          icoPause.classList.remove('d-none');
        } else {
          icoPlay.classList.remove('d-none');
          icoPause.classList.add('d-none');
        }
      }
      if (live && isActive && running) {
        rd.setAttribute('aria-label', str.pauseTimer || 'Pause');
      } else if (live && isActive && paused) {
        rd.setAttribute('aria-label', str.resumeTimer || 'Resume');
      } else {
        rd.setAttribute('aria-label', str.startTimer || 'Start');
      }
    });
  }

  function formatDuration(totalSec) {
    var s = Math.max(0, parseInt(String(totalSec || 0), 10) || 0);
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    var r = s % 60;
    var pad = function (n) { return n < 10 ? '0' + n : String(n); };
    return h + ':' + pad(m) + ':' + pad(r);
  }

  function formatEstimateHuman(sec) {
    var s = parseInt(String(sec || 0), 10) || 0;
    if (s <= 0) return '';
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    if (h > 0 && m > 0) return h + 'h ' + m + 'm';
    if (h > 0) return h + 'h';
    return Math.max(1, m) + 'm';
  }

  /** Compact label on the planned trigger (e.g. 0:15h, 1:30h). */
  function formatEstimateTrigger(sec) {
    var s = parseInt(String(sec || 0), 10) || 0;
    if (s <= 0) return '—';
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    var pad = function (n) { return n < 10 ? '0' + n : String(n); };
    return h + ':' + pad(m) + 'h';
  }

  /** Preset row labels in the dropdown (15 min, 1 hour, 1:30 hours, …). */
  function formatEstimatePresetLabel(sec) {
    var s = parseInt(String(sec || 0), 10) || 0;
    if (s <= 0) return '';
    var h = Math.floor(s / 3600);
    var m = Math.floor((s % 3600) / 60);
    if (h === 0) return Math.max(1, m) + ' min';
    if (m === 0) return h === 1 ? '1 hour' : (h + ' hours');
    return h + ':' + (m < 10 ? '0' : '') + m + ' hours';
  }

  function formatEstimateCap(sec) {
    var human = formatEstimateHuman(sec);
    if (!human) return '';
    var tpl = (cfg().strings && cfg().strings.timerOutOf) ? String(cfg().strings.timerOutOf) : 'Out of %s';
    return tpl.replace('%s', human);
  }

  var PRESETS = [
    900, 1800, 2700, 3600, 5400, 7200, 10800, 14400, 18000, 21600, 25200, 28800, 32400, 36000
  ];

  var state = {
    sidebarTaskId: 0,
    lastTaskDetail: null,
    lastServerState: null,
    headerPoll: null,
    tick: null,
    /** Wall clock ms when we last synced elapsed_display_seconds from API (for live ticking while running). */
    headerElapsedBaselineAt: 0,
    sidebarCanEditEstimate: false,
    /** Task id for complete modal when opened from header row (else 0 = use sidebar). */
    timerModalTargetTaskId: 0,
    /** When true, next complete modal open shows Manual tab first ("+ Log more time"). */
    completeModalPreferManual: false,
    /** Permissions from last GET logs response (can_mark_task_incomplete, etc.). */
    logPermissions: {},
    /** When unchanged, header rows are not re-built (keeps ⋯ dropdown open during live clock ticks). */
    headerRowsSignature: '',
    dashLoggedBaselineAt: 0,
    dashLoggedBaselineSec: 0,
    dashLoggedDisplaySec: 0,
    dashShiftDisplaySec: 0,
    dashCardTick: null,
    /** Rows from GET schedules for current sidebar task. */
    schedules: [],
    /** Active ⋯ trigger for shared work actions dropdown. */
    workActionsTrigger: null,
    /** calendar_task_schedule.id row driving play/pause UI (one session per user+task). */
    activeScheduleRowId: 0,
    /** Row that opened the ⋯ menu (complete / discard). */
    workActionsScheduleRowId: 0,
    /** Schedule row context for complete modal. */
    completeModalScheduleId: 0
  };

  function scheduleById(scheduleId) {
    var sid = parseInt(String(scheduleId || ''), 10) || 0;
    if (!sid || !state.schedules.length) return null;
    for (var i = 0; i < state.schedules.length; i++) {
      if (parseInt(String(state.schedules[i].id || ''), 10) === sid) return state.schedules[i];
    }
    return null;
  }

  function setActiveScheduleRowFromEl(rowEl) {
    if (!rowEl) return;
    var sid = parseInt(rowEl.getAttribute('data-schedule-id') || '0', 10) || 0;
    if (sid > 0) state.activeScheduleRowId = sid;
  }

  function clearScheduleRowFocus() {
    state.activeScheduleRowId = 0;
    state.workActionsScheduleRowId = 0;
    state.completeModalScheduleId = 0;
  }

  function getCompleteModalScheduleId() {
    return (
      state.completeModalScheduleId ||
      state.workActionsScheduleRowId ||
      state.activeScheduleRowId ||
      0
    );
  }

  function getCompleteModalMode() {
    var manualPane = el('tasksession-complete-pane-manual');
    if (manualPane && manualPane.classList.contains('active')) {
      return 'manual';
    }
    return 'timer';
  }

  /** Stop live schedule UI immediately after complete (before state poll). */
  function purgeLocalTimerSessionForTask(taskId) {
    var tid = parseInt(String(taskId || ''), 10) || 0;
    if (!tid || !state.lastServerState) return;
    var d = state.lastServerState;
    if (d.session && parseInt(String(d.session.task_id), 10) === tid) {
      d.session = null;
    }
    if (Array.isArray(d.sessions)) {
      d.sessions = d.sessions.filter(function (s) {
        return parseInt(String(s.task_id), 10) !== tid;
      });
    }
    markHeaderElapsedBaselineFromApi(d);
    markDashLoggedBaselineFromApi(d);
    restartHeaderLiveClock();
    applyTimerButtons();
    syncScheduleChrome();
    updateScheduleElapsedOnly(d);
    updateStatActive(d);
    updateHeaderWidget(d);
    updateStaffDashTimerCards(d);
    syncKanbanTimerBadges(d);
  }

  function removeLocalScheduleRow(scheduleRowId) {
    var sid = parseInt(String(scheduleRowId || ''), 10) || 0;
    if (!sid) return;
    state.schedules = (state.schedules || []).filter(function (s) {
      return parseInt(String(s.id), 10) !== sid;
    });
    renderScheduledWorkList(state.schedules);
  }

  function applyLocalTimerCompleteSuccess(taskId, scheduleRowId, response) {
    purgeLocalTimerSessionForTask(taskId);
    var sid = parseInt(String(scheduleRowId || ''), 10) || 0;
    if (sid > 0 || (response && response.schedule_removed)) {
      removeLocalScheduleRow(sid);
    }
  }

  /** Drop row ids that no longer exist after schedules reload (keep valid selection). */
  function pruneStaleScheduleRowFocus() {
    if (state.activeScheduleRowId && !scheduleById(state.activeScheduleRowId)) {
      state.activeScheduleRowId = 0;
    }
    if (state.workActionsScheduleRowId && !scheduleById(state.workActionsScheduleRowId)) {
      state.workActionsScheduleRowId = 0;
    }
    if (state.completeModalScheduleId && !scheduleById(state.completeModalScheduleId)) {
      state.completeModalScheduleId = 0;
    }
  }

  function isScheduleRowActive(rowEl) {
    if (!rowEl) return false;
    var sid = parseInt(rowEl.getAttribute('data-schedule-id') || '0', 10) || 0;
    var active = state.activeScheduleRowId || 0;
    if (!active) return false;
    return sid === active;
  }

  function ensureActiveScheduleRowForSession() {
    var tid = state.sidebarTaskId;
    var sess = getSessionForTask(tid, state.lastServerState);
    if (!sess || !(sessionRunning(sess) || sessionPaused(sess))) return;
    if (state.activeScheduleRowId) return;
    var first = document.querySelector('#tasksession-timer-work-list .tasksession-timer-work-row[data-is-mine="1"]');
    if (first) setActiveScheduleRowFromEl(first);
  }

  function markHeaderElapsedBaselineFromApi(data) {
    if (data && data.status === 'ok' && sessionRunning(data.session)) {
      state.headerElapsedBaselineAt = Date.now();
    } else {
      state.headerElapsedBaselineAt = 0;
    }
  }

  /** Elapsed seconds for header pill + row: server value + client drift while running. */
  function getHeaderDisplayElapsed(data) {
    if (!data || data.status !== 'ok') return 0;
    var base = Number(data.elapsed_display_seconds);
    if (!isFinite(base)) base = 0;
    base = Math.max(0, Math.floor(base));
    var sess = data.session;
    if (!sess || !sessionRunning(sess) || !state.headerElapsedBaselineAt) return base;
    var deltaSec = Math.floor((Date.now() - state.headerElapsedBaselineAt) / 1000);
    return base + Math.max(0, deltaSec);
  }

  /** Active running timer task id (only one may run at a time). */
  function getActiveTimerTaskId() {
    var d = state.lastServerState;
    if (!d || d.status !== 'ok') return 0;
    var list = getSessionsList(d);
    for (var i = 0; i < list.length; i++) {
      if (sessionRunning(list[i])) return parseInt(String(list[i].task_id || ''), 10) || 0;
    }
    if (d.session) return parseInt(String(d.session.task_id || ''), 10) || 0;
    return 0;
  }

  function el(id) {
    return document.getElementById(id);
  }

  function updateTimerTabBadgeCount(n) {
    var tabBadge = el('tasksession-timer-tab-count');
    if (!tabBadge) return;
    var num = Math.max(0, parseInt(String(n || 0), 10) || 0);
    tabBadge.textContent = String(num);
  }

  function headerHasTimerSessions(data) {
    return getSessionsList(data).length > 0;
  }

  function setHeaderTimerSlotEmpty(empty) {
    var slot = el('tasksession-header-timer-slot');
    if (!slot) return;
    if (empty) {
      slot.classList.add('tasksession-header-timer-slot--ready', 'tasksession-header-timer-slot--empty');
      slot.classList.remove('tasksession-header-timer-slot--show-skeleton');
    } else {
      slot.classList.remove('tasksession-header-timer-slot--empty');
      slot.classList.add('tasksession-header-timer-slot--show-skeleton');
    }
  }

  function showHeaderTimerSkeleton() {
    var slot = el('tasksession-header-timer-slot');
    if (!slot) return;
    slot.classList.remove('tasksession-header-timer-slot--ready', 'tasksession-header-timer-slot--empty');
    slot.classList.add('tasksession-header-timer-slot--show-skeleton');
  }

  function dismissHeaderTimerSkeleton() {
    var slot = el('tasksession-header-timer-slot');
    if (!slot) return;
    slot.classList.add('tasksession-header-timer-slot--ready');
    slot.classList.remove('tasksession-header-timer-slot--show-skeleton');
  }

  function syncHeaderTimerSlotFromState(data) {
    if (!el('tasksession-header-timer-slot')) return;
    if (!headerHasTimerSessions(data)) {
      setHeaderTimerSlotEmpty(true);
      return;
    }
    setHeaderTimerSlotEmpty(false);
  }

  /** Sidebar is z-index 9999; raise Bootstrap backdrop + modals above it while any timer sidebar modal is open. */
  function bumpTimerSidebarModalOpen(delta) {
    var cur = parseInt(String(window.__tasksessionSidebarTimerModalOpenCount || 0), 10) || 0;
    var n = cur + delta;
    if (n < 0) n = 0;
    window.__tasksessionSidebarTimerModalOpenCount = n;
    document.body.classList.toggle('tasksession-task-sidebar-timer-modal-open', n > 0);
  }

  function setScheduleMorePlannedLabel(sec) {
    var trig = el('tasksession-schedule-more-planned-trigger');
    if (!trig) return;
    var v = formatEstimateTrigger(sec);
    var sp = trig.querySelector('.tasksession-schedule-more-planned-value');
    if (sp) sp.textContent = v;
    else trig.textContent = v;
  }

  function showAlert(msg) {
    var a = el('tasksession-timer-alert');
    if (msg) {
      timerDebugAppend('timer notice: ' + String(msg));
    }
    if (!a) return;
    a.classList.add('d-none');
    a.textContent = '';
  }

  function getBillable() {
    var g = el('tasksession-timer-controls');
    if (!g) return 1;
    var on = g.querySelector('.tasksession-timer-billable.active');
    if (!on) return 1;
    return parseInt(on.getAttribute('data-billable') || '1', 10) ? 1 : 0;
  }

  function wireBillable() {
    var g = el('tasksession-timer-controls');
    if (!g) return;
    var bills = g.querySelectorAll('.tasksession-timer-billable');
    if (!bills.length) return;
    bills.forEach(function (b) {
      b.addEventListener('click', function () {
        g.querySelectorAll('.tasksession-timer-billable').forEach(function (x) { x.classList.remove('active'); });
        b.classList.add('active');
      });
    });
  }

  function clearSidebarDropdownFixed(menu) {
    if (!menu) return;
    menu.style.position = '';
    menu.style.left = '';
    menu.style.top = '';
    menu.style.right = '';
    menu.style.bottom = '';
    menu.style.minWidth = '';
    menu.style.zIndex = '';
    menu.style.maxHeight = '';
  }

  /** Avoid clipping inside .task-sidebar-content (overflow-x: hidden). */
  function positionSidebarDropdown(anchor, menu, opts) {
    if (!anchor || !menu) return;
    opts = opts || {};
    var r = anchor.getBoundingClientRect();
    var gap = 4;
    var pad = 8;
    var vw = window.innerWidth || document.documentElement.clientWidth || 0;
    var vh = window.innerHeight || document.documentElement.clientHeight || 0;
    var preferAbove = !!opts.preferAbove || !!(anchor.closest && anchor.closest('.dropup'));
    var alignEnd = opts.alignEnd !== false;

    menu.style.position = 'fixed';
    menu.style.zIndex = '10060';
    menu.style.right = 'auto';
    menu.style.bottom = 'auto';
    menu.style.marginTop = '0';
    menu.style.transform = 'none';
    menu.style.display = 'block';
    menu.style.visibility = 'hidden';
    menu.style.pointerEvents = 'none';

    var minW = Math.max(alignEnd ? 168 : 200, Math.ceil(r.width) || 28);
    menu.style.minWidth = minW + 'px';
    menu.style.maxHeight = 'min(320px, calc(100vh - ' + pad * 2 + 'px))';
    menu.style.left = '-9999px';
    menu.style.top = '0';

    var menuW = menu.offsetWidth || minW;
    var menuH = menu.offsetHeight || 0;
    var spaceBelow = vh - pad - (r.bottom + gap);
    var spaceAbove = r.top - gap - pad;
    var openBelow = !preferAbove && (spaceBelow >= menuH || spaceBelow >= spaceAbove);
    if (preferAbove && spaceAbove >= menuH) openBelow = false;

    var top = openBelow ? r.bottom + gap : Math.max(pad, r.top - menuH - gap);
    var left = alignEnd
      ? Math.min(Math.max(pad, r.right - menuW), Math.max(pad, vw - menuW - pad))
      : Math.min(Math.max(pad, r.left), Math.max(pad, vw - menuW - pad));

    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
    menu.style.visibility = '';
    menu.style.pointerEvents = '';
  }

  function repositionOpenTimerSidebarDropdowns() {
    var pdd = el('tasksessionTimerPlannedDropdown');
    var pt = el('tasksession-timer-planned-trigger');
    if (pdd && pdd.classList.contains('active') && pt && !pt.disabled) {
      positionSidebarDropdown(pt, pdd);
    }
    var list = el('tasksession-timer-work-list');
    if (list) {
      list.querySelectorAll('.tasksession-timer-work-more[data-bs-toggle="dropdown"]').forEach(function (btn) {
        if (!btn.getAttribute('aria-expanded') || btn.getAttribute('aria-expanded') === 'false') return;
        var menu = btn.nextElementSibling;
        if (menu && menu.classList && menu.classList.contains('dropdown-menu')) {
          positionSidebarDropdown(btn, menu, { preferAbove: true, alignEnd: true });
        }
      });
    }
  }

  function closeWorkActionsDropdown() {
    var list = el('tasksession-timer-work-list');
    if (list && window.bootstrap && window.bootstrap.Dropdown) {
      list.querySelectorAll('.tasksession-timer-work-more[data-bs-toggle="dropdown"]').forEach(function (btn) {
        try {
          var inst = window.bootstrap.Dropdown.getInstance(btn);
          if (inst) inst.hide();
        } catch (eHide) {}
      });
    }
    state.workActionsTrigger = null;
  }

  function closePlannedDropdown() {
    var dd = el('tasksessionTimerPlannedDropdown');
    var trig = el('tasksession-timer-planned-trigger');
    if (dd) {
      dd.classList.remove('active');
      clearSidebarDropdownFixed(dd);
    }
    if (trig) trig.setAttribute('aria-expanded', 'false');
  }

  function closeScheduleMorePlannedDropdown() {
    var dd = el('tasksessionScheduleMorePlannedDropdown');
    var trig = el('tasksession-schedule-more-planned-trigger');
    if (dd) {
      dd.classList.remove('active');
      clearSidebarDropdownFixed(dd);
    }
    if (trig) trig.setAttribute('aria-expanded', 'false');
  }

  function closeScheduleMoreAssigneeDropdown() {
    var smOpts = el('tasksession-schedule-assignee-options');
    var smSel = el('tasksession-schedule-assignee-selected');
    if (smOpts) smOpts.style.display = 'none';
    if (smSel) {
      smSel.classList.remove('open');
      smSel.setAttribute('aria-expanded', 'false');
    }
  }

  function togglePlannedDropdown() {
    var dd = el('tasksessionTimerPlannedDropdown');
    var trig = el('tasksession-timer-planned-trigger');
    if (!dd || !trig || trig.disabled) return;
    var becomingOpen = !dd.classList.contains('active');
    if (becomingOpen) {
      closeWorkActionsDropdown();
      closeScheduleMorePlannedDropdown();
      closeScheduleMoreAssigneeDropdown();
    }
    dd.classList.toggle('active');
    var isOpen = dd.classList.contains('active');
    trig.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen) positionSidebarDropdown(trig, dd);
    else clearSidebarDropdownFixed(dd);
  }

  function toggleScheduleMorePlannedDropdown() {
    var dd = el('tasksessionScheduleMorePlannedDropdown');
    var trig = el('tasksession-schedule-more-planned-trigger');
    if (!dd || !trig) return;
    var becomingOpen = !dd.classList.contains('active');
    if (becomingOpen) {
      closePlannedDropdown();
      closeWorkActionsDropdown();
      closeScheduleMoreAssigneeDropdown();
      clearSidebarDropdownFixed(dd);
    }
    dd.classList.toggle('active');
    var isOpen = dd.classList.contains('active');
    trig.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (!isOpen) clearSidebarDropdownFixed(dd);
  }

  function getSidebarTimerElapsedSec() {
    var d = state.lastServerState;
    var tid = parseInt(String(state.sidebarTaskId || ''), 10) || 0;
    if (!d || d.status !== 'ok' || !tid) return 0;
    var ent = getSessionForTask(tid, d);
    return getElapsedForTaskDisplay(ent, d);
  }

  function formatLoggedHoursDisplay(sec) {
    var s = Math.max(0, parseInt(String(sec || 0), 10) || 0);
    var h = s / 3600;
    var rounded = Math.round(h * 100) / 100;
    if (rounded <= 0) return '0h';
    if (rounded === Math.floor(rounded)) return String(Math.floor(rounded)) + 'h';
    return String(rounded) + 'h';
  }

  function canShowManualTimeEntry() {
    var p = state.logPermissions || {};
    if (Object.prototype.hasOwnProperty.call(p, 'timer_log_show_manual')) {
      return !!p.timer_log_show_manual;
    }
    var c = cfg();
    if (Object.prototype.hasOwnProperty.call(c, 'timerLogShowManual')) {
      return !!c.timerLogShowManual;
    }
    return true;
  }

  function applyManualEntryTabVisibility() {
    var show = !!canShowManualTimeEntry();
    var manualTab = el('tasksession-complete-tab-manual');
    var manualLi = manualTab && manualTab.closest ? manualTab.closest('li.nav-item') : null;
    var manualPane = el('tasksession-complete-pane-manual');
    var tabList = el('tasksessionTimerCompleteTabList');
    if (manualLi) manualLi.classList.toggle('d-none', !show);
    if (manualPane) manualPane.classList.toggle('d-none', !show);
    if (tabList) tabList.classList.toggle('d-none', !show);
    if (!show) {
      setCompleteModalMode('timer');
    }
  }

  function syncCompleteModalLoggedDisplay() {
    var disp = el('tasksession-timer-complete-timer-display');
    if (!disp) return;
    var target = state.timerModalTargetTaskId || state.sidebarTaskId;
    var d = state.lastServerState;
    var ent = getSessionForTask(target, d);
    disp.textContent = formatDuration(getElapsedForTaskDisplay(ent, d));
  }

  function setCompleteModalMode(mode) {
    if (mode === 'manual' && !canShowManualTimeEntry()) {
      mode = 'timer';
    }
    var modal = el('tasksessionTimerCompleteModal');
    if (!modal) return;
    var trig = modal.querySelector('.tasksession-timer-complete-mode[data-complete-mode="' + mode + '"]');
    if (trig && window.bootstrap && window.bootstrap.Tab) {
      try {
        window.bootstrap.Tab.getOrCreateInstance(trig).show();
      } catch (eTab) {}
    }
    modal.querySelectorAll('.tasksession-timer-complete-mode').forEach(function (b) {
      var on = b.getAttribute('data-complete-mode') === mode;
      b.classList.toggle('active', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    modal.querySelectorAll('.tasksession-timer-complete-tab-content .tab-pane').forEach(function (p) {
      var show =
        (mode === 'timer' && p.id === 'tasksession-complete-pane-timer') ||
        (mode === 'manual' && p.id === 'tasksession-complete-pane-manual');
      p.classList.toggle('show', show);
      p.classList.toggle('active', show);
    });
    var timerRow = el('tasksession-timer-complete-timer-row');
    if (timerRow) timerRow.classList.toggle('d-none', mode !== 'timer');
    var assigneeRow = el('tasksession-complete-log-assignee-row');
    if (assigneeRow) assigneeRow.classList.toggle('d-none', mode !== 'manual');
    closeCompleteLogAssigneeDropdown();
    if (mode === 'timer') syncCompleteModalLoggedDisplay();
  }

  function syncManualDurationFromSeconds(sec) {
    var hidden = el('tasksession-timer-complete-manual-seconds');
    var label = el('tasksession-timer-complete-manual-dropdown-label');
    var modal = el('tasksessionTimerCompleteModal');
    if (!hidden || !label || !modal) return;
    var placeholder = label.getAttribute('data-placeholder') || '';
    sec = Math.max(0, parseInt(String(sec || 0), 10) || 0);
    var opts = modal.querySelectorAll('.tasksession-timer-manual-dur-opt');
    var best = null;
    var bestDiff = Infinity;
    opts.forEach(function (o) {
      var s = parseInt(o.getAttribute('data-seconds'), 10);
      if (!isFinite(s)) return;
      var d = Math.abs(s - sec);
      if (d < bestDiff) {
        bestDiff = d;
        best = o;
      }
    });
    if (sec <= 0) {
      hidden.value = '';
      label.textContent = placeholder;
      return;
    }
    if (best) {
      hidden.value = String(parseInt(best.getAttribute('data-seconds'), 10));
      label.textContent = String(best.textContent || '').trim();
      return;
    }
    hidden.value = '';
    label.textContent = placeholder;
  }

  function syncEditLogDurationFromSeconds(sec) {
    var hidden = el('tasksession-edit-log-manual-seconds');
    var label = el('tasksession-edit-log-manual-dropdown-label');
    var modal = el('tasksessionTimerEditLogModal');
    if (!hidden || !label || !modal) return;
    var placeholder = label.getAttribute('data-placeholder') || '';
    sec = Math.max(0, parseInt(String(sec || 0), 10) || 0);
    var opts = modal.querySelectorAll('.tasksession-edit-log-dur-opt');
    var best = null;
    var bestDiff = Infinity;
    opts.forEach(function (o) {
      var s = parseInt(o.getAttribute('data-seconds'), 10);
      if (!isFinite(s)) return;
      var d = Math.abs(s - sec);
      if (d < bestDiff) {
        bestDiff = d;
        best = o;
      }
    });
    if (sec <= 0) {
      hidden.value = '';
      label.textContent = placeholder;
      return;
    }
    if (best) {
      hidden.value = String(parseInt(best.getAttribute('data-seconds'), 10));
      label.textContent = String(best.textContent || '').trim();
      return;
    }
    hidden.value = '';
    label.textContent = placeholder;
  }

  function getEditLogBillable() {
    var modal = el('tasksessionTimerEditLogModal');
    if (!modal) return 0;
    var rad = modal.querySelector('input[name="tasksessionTimerEditLogBillable"]:checked');
    if (!rad) return 0;
    return parseInt(String(rad.value || '1'), 10) ? 1 : 0;
  }

  function setEditLogBillable(val) {
    var modal = el('tasksessionTimerEditLogModal');
    if (!modal) return;
    var v = val ? '1' : '0';
    var rad = modal.querySelector('input[name="tasksessionTimerEditLogBillable"][value="' + v + '"]');
    if (rad) rad.checked = true;
  }

  function syncCompleteModalBillable(val) {
    var modal = el('tasksessionTimerCompleteModal');
    if (!modal) return;
    var v = val ? '1' : '0';
    var rad = modal.querySelector('input[name="tasksessionTimerCompleteBillable"][value="' + v + '"]');
    if (rad) rad.checked = true;
  }

  function getCompleteModalBillable() {
    var modal = el('tasksessionTimerCompleteModal');
    if (!modal) return 0;
    var rad = modal.querySelector('input[name="tasksessionTimerCompleteBillable"]:checked');
    if (!rad) return 0;
    return parseInt(String(rad.value || '1'), 10) ? 1 : 0;
  }

  function openTimerCompleteModal(forTaskId, opts) {
    if (!cfg().canMutateTimer) return;
    var modalEl = el('tasksessionTimerCompleteModal');
    if (!modalEl) return;
    closeWorkActionsDropdown();

    var tid = forTaskId != null && parseInt(String(forTaskId), 10) > 0 ? parseInt(String(forTaskId), 10) : (parseInt(String(state.sidebarTaskId || ''), 10) || 0);
    state.timerModalTargetTaskId = tid;

    var schedId = opts && opts.scheduleRowId != null ? parseInt(String(opts.scheduleRowId), 10) || 0 : 0;
    if (!schedId) schedId = state.workActionsScheduleRowId || state.activeScheduleRowId || 0;
    state.completeModalScheduleId = schedId;
    var sched = scheduleById(schedId);

    var preferManual = !!(opts && opts.preferManual) && canShowManualTimeEntry();
    state.completeModalPreferManual = preferManual;

    var d = state.lastServerState;
    var ent = getSessionForTask(tid, d);
    var sec = getElapsedForTaskDisplay(ent, d);
    syncCompleteModalLoggedDisplay();
    if (preferManual) {
      syncManualDurationFromSeconds(0);
    } else {
      syncManualDurationFromSeconds(sec);
    }

    var dateIn = el('tasksession-timer-complete-date');
    if (dateIn) {
      var dateVal = '';
      if (sched && sched.schedule_date && /^\d{4}-\d{2}-\d{2}$/.test(String(sched.schedule_date))) {
        dateVal = String(sched.schedule_date);
      } else {
        var y = new Date();
        var pad = function (n) { return n < 10 ? '0' + n : String(n); };
        dateVal = y.getFullYear() + '-' + pad(y.getMonth() + 1) + '-' + pad(y.getDate());
      }
      dateIn.value = dateVal;
    }

    var modalTitle = el('tasksessionTimerCompleteModalLabel');
    if (modalTitle) {
      var baseTitle = modalTitle.getAttribute('data-base-title') || modalTitle.textContent;
      if (!modalTitle.getAttribute('data-base-title')) modalTitle.setAttribute('data-base-title', baseTitle);
      if (sched && sched.planned_seconds != null && parseInt(String(sched.planned_seconds), 10) > 0) {
        modalTitle.textContent = baseTitle + ' (' + formatEstimateTrigger(sched.planned_seconds) + ')';
      } else {
        modalTitle.textContent = baseTitle;
      }
    }

    var notes = el('tasksession-timer-complete-notes');
    if (notes) notes.value = '';

    var mark = el('tasksession-timer-complete-mark-done');
    if (mark && modalEl) {
      mark.checked = modalEl.getAttribute('data-default-mark-done') === '1';
    }

    loadCompleteModalAssignees();

    syncCompleteModalBillable(false);

    if (window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    } else {
      modalEl.classList.add('show');
      modalEl.style.display = 'block';
      document.body.classList.add('modal-open');
    }
  }

  function openEditLogFromRow(tr) {
    if (!cfg().canMutateTimer || !tr || !tr.dataset) return;
    var eid = parseInt(String(tr.dataset.logEntryId || ''), 10) || 0;
    if (!eid) return;
    var hid = el('tasksession-edit-log-entry-id');
    if (hid) hid.value = String(eid);
    var ds = parseInt(String(tr.dataset.logEntryDurationSec || '0'), 10) || 0;
    syncEditLogDurationFromSeconds(ds);
    var dIn = el('tasksession-edit-log-date');
    if (dIn) dIn.value = String(tr.dataset.logDateYmd || '').trim();
    setEditLogBillable((tr.dataset.logBillable || '0') === '1');
    var ta = el('tasksession-edit-log-notes');
    if (ta) ta.value = String(tr.dataset.logNoteRaw || '');
    var modalEl = el('tasksessionTimerEditLogModal');
    if (modalEl && window.bootstrap && window.bootstrap.Modal) {
      window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
  }

  function submitEditLogFromModal() {
    if (!cfg().canMutateTimer) return;
    var tid = parseInt(String(state.sidebarTaskId || ''), 10) || 0;
    var eidEl = el('tasksession-edit-log-entry-id');
    var eid = eidEl ? parseInt(String(eidEl.value || ''), 10) || 0 : 0;
    if (!tid || !eid) return;
    var hidSec = el('tasksession-edit-log-manual-seconds');
    var ds = hidSec ? parseInt(String(hidSec.value || '0'), 10) : 0;
    if (!isFinite(ds) || ds <= 0 || ds > 604800) {
      showAlert('Enter a valid logged time.');
      return;
    }
    var d0 = el('tasksession-edit-log-date');
    var logDate = d0 ? String(d0.value || '').trim() : '';
    var notesEl = el('tasksession-edit-log-notes');
    var notes = notesEl ? String(notesEl.value || '').trim() : '';
    postTimerAction(
      {
        action: 'update_time_log',
        task_id: tid,
        entry_id: eid,
        duration_seconds: ds,
        log_date: logDate,
        billable: getEditLogBillable(),
        notes: notes
      },
      'update time log'
    )
      .then(function (j) {
        if (!isOkResponse(j)) {
          showAlert(j.message || 'Error');
          return;
        }
        var modalEl = el('tasksessionTimerEditLogModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
          var inst = window.bootstrap.Modal.getInstance(modalEl);
          if (inst) inst.hide();
        }
        loadLogs(tid);
      })
      .catch(function (err) {
        logFetchError('update time log', err);
      });
  }

  function submitTimerCompleteFromModal() {
    if (!cfg().canMutateTimer) return;
    var tid = state.timerModalTargetTaskId || state.sidebarTaskId;
    tid = parseInt(String(tid || ''), 10) || 0;
    if (!tid) return;
    var schedId = getCompleteModalScheduleId();
    var mode = getCompleteModalMode();
    var sess = getSessionForTask(tid, state.lastServerState);
    var liveSess = !!(sess && (sessionRunning(sess) || sessionPaused(sess)));
    var me = parseInt(String(cfg().userId || ''), 10) || 0;

    if (mode === 'manual' && liveSess) {
      var logUserElEarly = el('tasksession-complete-log-assignee');
      var logUidEarly = logUserElEarly ? parseInt(String(logUserElEarly.value || '0'), 10) : 0;
      if (!logUidEarly || logUidEarly === me) {
        mode = 'timer';
      }
    }

    var payload = {
      task_id: tid,
      billable: getCompleteModalBillable(),
      notes: (function () {
        var n = el('tasksession-timer-complete-notes');
        return n ? String(n.value || '').trim() : '';
      })(),
      log_date: (function () {
        var d0 = el('tasksession-timer-complete-date');
        return d0 ? String(d0.value || '').trim() : '';
      })()
    };

    if (mode === 'manual' && !canShowManualTimeEntry()) {
      var denyMsg =
        (cfg().strings && cfg().strings.manualEntryDenied) ||
        'Manual time entry is not allowed for your role.';
      showAlert(denyMsg);
      return;
    }

    if (mode === 'manual') {
      var hidSec = el('tasksession-timer-complete-manual-seconds');
      var ds = hidSec ? parseInt(String(hidSec.value || '0'), 10) : 0;
      if (!isFinite(ds) || ds <= 0) {
        showAlert('Enter a valid logged time.');
        return;
      }
      if (ds <= 0 || ds > 604800) {
        showAlert('Enter a valid logged time.');
        return;
      }
      payload.action = 'manual_log';
      payload.duration_seconds = ds;
      var logUserEl = el('tasksession-complete-log-assignee');
      var logUid = logUserEl ? parseInt(String(logUserEl.value || '0'), 10) : 0;
      if (!logUid) {
        var compModal = el('tasksessionTimerCompleteModal');
        var selMsg = compModal ? String(compModal.getAttribute('data-log-complete-msg-select') || '').trim() : '';
        showAlert(selMsg || 'Select assignee.');
        return;
      }
      payload.log_user_id = logUid;
      if (schedId > 0 && logUid === me) {
        payload.schedule_id = schedId;
      }
    } else {
      payload.action = 'complete';
      if (schedId > 0) payload.schedule_id = schedId;
    }

    var markEl = el('tasksession-timer-complete-mark-done');
    if (markEl && markEl.checked) {
      payload.mark_task_done = true;
    }

    var completedSchedId = schedId;
    var postLabel = mode === 'manual' ? 'manual log' : 'timer complete';

    postTimerAction(payload, postLabel)
      .then(function (j) {
        if (!isOkResponse(j)) {
          showAlert(j.message || 'Error');
          timerDebugAppend('timer complete: ' + (j.message || 'error'));
          return;
        }
        if (j.task_status && state.lastTaskDetail && (parseInt(String(state.sidebarTaskId || ''), 10) || 0) === tid) {
          state.lastTaskDetail.status = j.task_status;
        }
        if (j.task_marked_done && typeof window.comonKanbanSyncTaskToColumn === 'function') {
          window.comonKanbanSyncTaskToColumn(tid, 'done').catch(function () {});
        }
        applyLocalTimerCompleteSuccess(tid, completedSchedId, j);
        var modalEl = el('tasksessionTimerCompleteModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
          var inst = window.bootstrap.Modal.getInstance(modalEl);
          if (inst) inst.hide();
        }
        clearScheduleRowFocus();
        syncStateAndUi().then(function () {
          if (tid) {
            return loadLogs(tid).then(function () {
              return loadSchedules(tid);
            });
          }
          return null;
        }).then(function () {
          focusTimerTabAndLogsExpand(tid);
        });
      })
      .catch(function (err) {
        logFetchError('timer complete', err);
      });
  }

  function submitTimerCompleteWork() {
    var sid = state.workActionsScheduleRowId || state.activeScheduleRowId || 0;
    if (sid) state.activeScheduleRowId = sid;
    closeWorkActionsDropdown();
    openTimerCompleteModal(state.sidebarTaskId, { scheduleRowId: sid });
  }

  function submitTimerDiscardWork(optTid) {
    if (!cfg().canMutateTimer || cfg().isClient) return;
    var tid = optTid != null && parseInt(String(optTid), 10) > 0 ? parseInt(String(optTid), 10) : 0;
    if (!tid) tid = parseInt(String(state.sidebarTaskId || ''), 10) || 0;
    if (!tid) tid = getActiveTimerTaskId();
    if (!tid) return;
    postTimerAction({ action: 'discard', task_id: tid }, 'timer discard')
      .then(function (j) {
        if (!isOkResponse(j)) {
          showAlert(j.message || 'Error');
          timerDebugAppend('timer discard: ' + (j.message || 'error'));
          return;
        }
        closeWorkActionsDropdown();
        clearScheduleRowFocus();
        syncStateAndUi().then(function () {
          loadLogs(tid);
          loadSchedules(tid);
        });
      })
      .catch(function (err) {
        logFetchError('timer discard', err);
      });
  }

  function submitEstimateSeconds(secOrNull) {
    var can = !!(state.sidebarCanEditEstimate && cfg().canEditEstimateInSidebar);
    if (!can) return;
    var tid = state.sidebarTaskId;
    if (!tid) return;
    postTimerAction({ action: 'estimated', task_id: tid, estimated_time_seconds: secOrNull }, 'timer estimated')
      .then(function (j) {
        if (!isOkResponse(j)) {
          timerDebugAppend('timer estimated: ' + (j.message || 'error'));
          return;
        }
        if (state.lastTaskDetail) state.lastTaskDetail.estimated_time_seconds = j.estimated_time_seconds;
        closePlannedDropdown();
        loadLogs(tid);
      })
      .catch(function (err) {
        logFetchError('timer estimated', err);
      });
  }

  function buildPlannedDropdown(currentSec, canEdit) {
    var menu = el('tasksessionTimerPlannedDropdown');
    var trig = el('tasksession-timer-planned-trigger');
    var wrap = el('tasksession-timer-estimate-wrap');
    if (!menu || !trig || !wrap) return;
    state.sidebarCanEditEstimate = !!canEdit;
    trig.textContent = formatEstimateTrigger(currentSec);
    trig.disabled = !canEdit;

    menu.innerHTML = '';
    var buttons = [];

    var clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.setAttribute('data-estimate-clear', '1');
    clearBtn.textContent = '—';
    clearBtn.setAttribute('aria-label', 'Clear planned time');
    buttons.push(clearBtn);

    var curInt = currentSec != null ? parseInt(String(currentSec), 10) : 0;
    var isCustom = curInt > 0 && PRESETS.indexOf(curInt) === -1;
    if (isCustom) {
      var curBtn = document.createElement('button');
      curBtn.type = 'button';
      curBtn.setAttribute('data-seconds', String(curInt));
      curBtn.textContent = formatEstimatePresetLabel(curInt);
      buttons.push(curBtn);
    }

    PRESETS.forEach(function (sec) {
      var b = document.createElement('button');
      b.type = 'button';
      b.setAttribute('data-seconds', String(sec));
      b.textContent = formatEstimatePresetLabel(sec);
      buttons.push(b);
    });

    buttons.forEach(function (b, idx) {
      if (idx === 0) b.classList.add('first');
      if (idx === buttons.length - 1) b.classList.add('last');
      menu.appendChild(b);
    });
  }

  function escHtml(str) {
    var d = document.createElement('div');
    d.textContent = str == null ? '' : String(str);
    return d.innerHTML;
  }

  function scheduleAssigneePicBase() {
    var b = typeof window.baseUrl === 'string' ? window.baseUrl : '/';
    if (b.slice(-1) !== '/') b += '/';
    return b;
  }

  function scheduleFallbackPic() {
    return scheduleAssigneePicBase() + 'assets/images/upload-img.jpg';
  }

  /** Refresh sidebar status badge (and due-date styling) after task status changes without reopening the sidebar. */
  function refreshSidebarTaskStatusBadge(taskId) {
    var tid = parseInt(String(taskId || ''), 10) || 0;
    if (!tid) return Promise.resolve();
    var url = scheduleAssigneePicBase() + 'includes/task_details.php?id=' + encodeURIComponent(String(tid));
    var init = { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
    var run = window.fetchWithCsrf
      ? function () { return window.fetchWithCsrf(url, init); }
      : function () { return fetch(url, init); };
    return run()
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || data.status !== 'ok' || !data.task) return;
        if (state.lastTaskDetail && (parseInt(String(state.sidebarTaskId || ''), 10) || 0) === tid) {
          state.lastTaskDetail.status = data.task.status || 'todo';
        }
        var badgeElem = el('task-status-badge');
        var statusSection = el('task-status-section');
        if (data.task.badge_html) {
          if (badgeElem) badgeElem.innerHTML = data.task.badge_html;
          if (statusSection) statusSection.style.display = 'block';
        } else {
          if (badgeElem) badgeElem.innerHTML = '';
          if (statusSection) statusSection.style.display = 'none';
        }
        var dueDateElem = el('task-due-date');
        if (data.task.due_date && dueDateElem) {
          dueDateElem.textContent = data.task.due_date;
          var dueDate = new Date(data.task.due_date);
          var isOverdue = data.task.status !== 'done' && dueDate < new Date();
          if (isOverdue) {
            dueDateElem.classList.add('text-danger', 'fw-bold');
          } else {
            dueDateElem.classList.remove('text-danger', 'fw-bold');
          }
        }
      })
      .catch(function () {});
  }

  function scheduleSanitizeImg(u, fb) {
    var s = String(u || '').trim();
    if (!s) return fb;
    if (/^https?:\/\//i.test(s) || s.charAt(0) === '/') return s;
    return fb;
  }

  function buildScheduleMorePlannedMenu() {
    var menu = el('tasksessionScheduleMorePlannedDropdown');
    if (!menu) return;
    menu.innerHTML = '';
    PRESETS.forEach(function (sec, idx) {
      var b = document.createElement('button');
      b.type = 'button';
      b.setAttribute('data-schedule-seconds', String(sec));
      b.textContent = formatEstimatePresetLabel(sec);
      if (idx === 0) b.classList.add('first');
      if (idx === PRESETS.length - 1) b.classList.add('last');
      menu.appendChild(b);
    });
  }

  function showTimerTabNotice(msg, isError) {
    var a = el('tasksession-timer-alert');
    if (!a || !msg) return;
    a.className = 'alert py-2 px-3 small ' + (isError ? 'alert-danger' : 'alert-success');
    a.textContent = msg;
    a.classList.remove('d-none');
    if (a._hideT) clearTimeout(a._hideT);
    a._hideT = setTimeout(function () {
      a.classList.add('d-none');
      a.textContent = '';
    }, 4500);
  }

  function selectScheduleAssignee(userId, displayName, imageUrl) {
    var hidden = el('tasksession-schedule-assignee');
    var selText = document.querySelector('#tasksession-schedule-assignee-selected .selected-text');
    var fb = scheduleFallbackPic();
    if (hidden) hidden.value = userId || '';
    if (selText) {
      if (userId) {
        selText.innerHTML = '';
        var img = document.createElement('img');
        img.src = scheduleSanitizeImg(imageUrl, fb);
        img.alt = displayName || '';
        img.className = 'selected-image';
        img.addEventListener('error', function () { img.src = fb; });
        var span = document.createElement('span');
        span.textContent = displayName || '';
        selText.appendChild(img);
        selText.appendChild(span);
      } else {
        selText.textContent = displayName || '';
      }
    }
    closeScheduleMoreAssigneeDropdown();
  }

  function closeCompleteLogAssigneeDropdown() {
    var cOpts = el('tasksession-complete-log-assignee-options');
    var cSel = el('tasksession-complete-log-assignee-selected');
    if (cOpts) cOpts.style.display = 'none';
    if (cSel) {
      cSel.classList.remove('open');
      cSel.setAttribute('aria-expanded', 'false');
    }
  }

  function selectCompleteLogAssignee(userId, displayName, imageUrl) {
    var hidden = el('tasksession-complete-log-assignee');
    var selText = document.querySelector('#tasksession-complete-log-assignee-selected .selected-text');
    var fb = scheduleFallbackPic();
    if (hidden) hidden.value = userId || '';
    if (selText) {
      if (userId) {
        selText.innerHTML = '';
        var img = document.createElement('img');
        img.src = scheduleSanitizeImg(imageUrl, fb);
        img.alt = displayName || '';
        img.className = 'selected-image';
        img.addEventListener('error', function () { img.src = fb; });
        var span = document.createElement('span');
        span.textContent = displayName || '';
        selText.appendChild(img);
        selText.appendChild(span);
      } else {
        selText.textContent = displayName || '';
      }
    }
    closeCompleteLogAssigneeDropdown();
  }

  function loadCompleteModalAssignees() {
    var tid = state.timerModalTargetTaskId || state.sidebarTaskId;
    var opts = el('tasksession-complete-log-assignee-options');
    var modal = el('tasksessionTimerCompleteModal');
    if (!tid || !opts || !modal) return;

    var msgSelect = modal.getAttribute('data-log-complete-msg-select') || 'Select assignee';
    var msgMe = modal.getAttribute('data-log-complete-label-me') || 'Me';
    var curUid = parseInt(modal.getAttribute('data-current-user-id') || '0', 10) || 0;

    opts.innerHTML = '';
    selectCompleteLogAssignee('', msgSelect, '');

    var defaultOpt = document.createElement('div');
    defaultOpt.className = 'dropdown-option';
    defaultOpt.innerHTML = '<span class="option-text">' + escHtml(msgSelect) + '</span>';
    defaultOpt.addEventListener('click', function () {
      selectCompleteLogAssignee('', msgSelect, '');
    });
    opts.appendChild(defaultOpt);

    var url = scheduleAssigneePicBase() + 'includes/task_details.php?id=' + encodeURIComponent(String(tid));
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || data.status !== 'ok' || !Array.isArray(data.assigned_staff)) return;
        var fb = scheduleFallbackPic();
        var list = data.assigned_staff.filter(function (u) { return u && u.id; });
        if (list.length === 0 && curUid > 0) {
          list = [{ id: curUid, name: '', image: '' }];
        }
        var defaultPick = null;
        if (list.length) {
          var cur = null;
          for (var i = 0; i < list.length; i++) {
            if (parseInt(String(list[i].id), 10) === curUid) {
              cur = list[i];
              break;
            }
          }
          defaultPick = cur || list[0];
        }
        for (var i2 = 0; i2 < list.length; i2++) {
          var user = list[i2];
          var uid = parseInt(String(user.id), 10);
          var realName = String(user.name || '');
          var dispName = (curUid > 0 && uid === curUid) ? msgMe : (realName || msgMe);
          var opt = document.createElement('div');
          opt.className = 'dropdown-option';
          var safeSrc = scheduleSanitizeImg(user.image, fb);
          opt.innerHTML =
            '<img src="' + escHtml(safeSrc) + '" alt="" class="option-image" loading="lazy">' +
            '<span class="option-text">' + escHtml(dispName) + '</span>';
          var imgEl = opt.querySelector('img');
          if (imgEl) {
            imgEl.alt = realName || dispName;
            imgEl.addEventListener('error', function () { imgEl.src = fb; });
          }
          opt.addEventListener('click', (function (uId, dName, src) {
            return function () {
              selectCompleteLogAssignee(String(uId), dName, src);
            };
          })(uid, dispName, safeSrc));
          opts.appendChild(opt);
        }
        if (defaultPick) {
          var uid0 = parseInt(String(defaultPick.id), 10);
          var dn0 = (curUid > 0 && uid0 === curUid) ? msgMe : String(defaultPick.name || msgMe);
          var src0 = scheduleSanitizeImg(defaultPick.image, fb);
          selectCompleteLogAssignee(String(uid0), dn0, src0);
        }
      })
      .catch(function () {});
  }

  function loadScheduleMoreAssignees() {
    var tid = state.sidebarTaskId;
    var opts = el('tasksession-schedule-assignee-options');
    var modal = el('tasksessionScheduleMoreModal');
    if (!tid || !opts || !modal) return;

    var msgSelect = modal.getAttribute('data-schedule-msg-select') || 'Select assignee';
    var msgMe = modal.getAttribute('data-schedule-label-me') || 'Me';
    var curUid = parseInt(modal.getAttribute('data-current-user-id') || '0', 10) || 0;

    opts.innerHTML = '';
    selectScheduleAssignee('', msgSelect, '');

    var defaultOpt = document.createElement('div');
    defaultOpt.className = 'dropdown-option';
    defaultOpt.innerHTML = '<span class="option-text">' + escHtml(msgSelect) + '</span>';
    defaultOpt.addEventListener('click', function () {
      selectScheduleAssignee('', msgSelect, '');
    });
    opts.appendChild(defaultOpt);

    var url = scheduleAssigneePicBase() + 'includes/task_details.php?id=' + encodeURIComponent(String(tid));
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || data.status !== 'ok' || !Array.isArray(data.assigned_staff)) return;
        var fb = scheduleFallbackPic();
        var list = data.assigned_staff.filter(function (u) { return u && u.id; });
        var defaultPick = null;
        if (list.length) {
          var cur = null;
          for (var i = 0; i < list.length; i++) {
            if (parseInt(String(list[i].id), 10) === curUid) {
              cur = list[i];
              break;
            }
          }
          defaultPick = cur || list[0];
        }
        for (var i2 = 0; i2 < list.length; i2++) {
          var user = list[i2];
          var uid = parseInt(String(user.id), 10);
          var realName = String(user.name || '');
          var dispName = (curUid > 0 && uid === curUid) ? msgMe : realName;
          var opt = document.createElement('div');
          opt.className = 'dropdown-option';
          var safeSrc = scheduleSanitizeImg(user.image, fb);
          opt.innerHTML =
            '<img src="' + escHtml(safeSrc) + '" alt="" class="option-image" loading="lazy">' +
            '<span class="option-text">' + escHtml(dispName) + '</span>';
          var imgEl = opt.querySelector('img');
          if (imgEl) {
            imgEl.alt = realName || dispName;
            imgEl.addEventListener('error', function () { imgEl.src = fb; });
          }
          opt.addEventListener('click', (function (uId, dName, src) {
            return function () {
              selectScheduleAssignee(String(uId), dName, src);
            };
          })(uid, dispName, safeSrc));
          opts.appendChild(opt);
        }
        if (defaultPick) {
          var uid0 = parseInt(String(defaultPick.id), 10);
          var dn0 = (curUid > 0 && uid0 === curUid) ? msgMe : String(defaultPick.name || '');
          var src0 = scheduleSanitizeImg(defaultPick.image, fb);
          selectScheduleAssignee(String(uid0), dn0, src0);
        }
      })
      .catch(function () {});
  }

  function openScheduleMoreModalPrep() {
    var err = el('tasksession-schedule-more-error');
    if (err) {
      err.classList.add('d-none');
      err.textContent = '';
    }
    var dateIn = el('tasksession-schedule-date');
    if (dateIn) {
      var t = new Date();
      var mo = t.getMonth() + 1;
      var da = t.getDate();
      var month = mo < 10 ? '0' + mo : String(mo);
      var day = da < 10 ? '0' + da : String(da);
      dateIn.value = t.getFullYear() + '-' + month + '-' + day;
    }
    var hid = el('tasksession-schedule-planned-seconds');
    var defSec = 900;
    if (hid) hid.value = String(defSec);
    setScheduleMorePlannedLabel(defSec);
    closeScheduleMorePlannedDropdown();
    closeScheduleMoreAssigneeDropdown();
    loadScheduleMoreAssignees();
  }

  function submitScheduleMoreWork() {
    var modal = el('tasksessionScheduleMoreModal');
    var err = el('tasksession-schedule-more-error');
    if (!modal || !err) return;
    err.classList.add('d-none');
    err.textContent = '';

    var tid = state.sidebarTaskId;
    var uidEl = el('tasksession-schedule-assignee');
    var dateIn = el('tasksession-schedule-date');
    var assigneeId = uidEl ? parseInt(String(uidEl.value || '0'), 10) : 0;
    var d = dateIn ? String(dateIn.value || '').trim() : '';
    var bad = !tid || assigneeId <= 0 || !/^\d{4}-\d{2}-\d{2}$/.test(d);
    if (bad) {
      err.textContent = modal.getAttribute('data-schedule-msg-select') || 'Please select assignee and date.';
      err.classList.remove('d-none');
      return;
    }

    var hidPlanned = el('tasksession-schedule-planned-seconds');
    var plannedSec = hidPlanned ? parseInt(String(hidPlanned.value || '0'), 10) : 0;
    var payload = {
      action: 'schedule_placement',
      task_id: tid,
      schedule_user_id: assigneeId,
      schedule_date: d
    };
    if (isFinite(plannedSec) && plannedSec > 0) {
      payload.planned_seconds = plannedSec;
    }

    postTimerAction(payload, 'schedule placement')
      .then(function (j) {
        if (!isOkResponse(j)) {
          var failMsg = j.message || 'Error';
          err.textContent = failMsg;
          err.classList.remove('d-none');
          showTimerTabNotice(failMsg, true);
          timerDebugAppend('schedule placement: ' + failMsg);
          return;
        }
        var inst = window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getInstance(modal) : null;
        if (inst) inst.hide();
        timerDebugAppend('schedule placement: ok');
        var okMsg = modal.getAttribute('data-schedule-msg-saved') || 'Saved';
        showTimerTabNotice(okMsg, false);
        loadSchedules(tid);
      })
      .catch(function (e2) {
        err.textContent = 'Error';
        err.classList.remove('d-none');
        logFetchError('schedule placement', e2);
      });
  }

  function postJson(body) {
    var u = apiUrl();
    var init = {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    };
    if (window.fetchWithCsrf) return window.fetchWithCsrf(u, init);
    return fetch(u, init);
  }

  function jsonFromResponse(r, label) {
    return r.text().then(function (text) {
      try {
        return JSON.parse(text);
      } catch (err) {
        timerDebugAppend((label || 'timer API') + ': invalid JSON — ' + String(text).slice(0, 500));
        return { status: 'error', message: 'Invalid server response' };
      }
    });
  }

  function logFetchError(label, err) {
    timerDebugAppend(label + ' fetch: ' + (err && err.message ? err.message : String(err)));
  }

  function isOkResponse(j) {
    return j && j.status === 'ok';
  }

  function postTimerAction(payload, label) {
    return postJson(payload)
      .then(function (r) { return jsonFromResponse(r, label); });
  }

  function handleSidebarTimerMutationJson(label, j, onOk) {
    if (!isOkResponse(j)) {
      if (label === 'timer start') {
        var m = j.message || 'Error';
        showAlert(m);
        timerDebugAppend('timer start: ' + m);
        return;
      }
      showAlert(j.message || 'Error');
      timerDebugAppend(label + ': ' + (j.message || 'error'));
      return;
    }
    onOk();
  }

  function runSidebarTimerMutation(payload, label, onOk) {
    if (cfg().isClient) {
      return;
    }
    postTimerAction(payload, label)
      .then(function (j) {
        handleSidebarTimerMutationJson(label, j, onOk);
      })
      .catch(function (err) {
        logFetchError(label, err);
      });
  }

  function refreshServerState() {
    var u = apiUrl() + '?action=state';
    return fetch(u, { credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (err) {
            timerDebugAppend('timer state: invalid JSON (' + (err && err.message ? err.message : '') + '): ' + String(text).slice(0, 500));
            return null;
          }
        });
      });
  }

  function applyTimerButtons() {
    var c = cfg();
    var controls = el('tasksession-timer-controls');
    if (!controls) return;

    var tid = state.sidebarTaskId;
    var sess = getSessionForTask(tid, state.lastServerState);
    var start = el('tasksession-timer-btn-start');
    var pause = el('tasksession-timer-btn-pause');
    var resume = el('tasksession-timer-btn-resume');

    var list = getSessionsList(state.lastServerState);
    var otherRunning = false;
    var i;
    for (i = 0; i < list.length; i++) {
      if (sessionRunning(list[i]) && parseInt(String(list[i].task_id), 10) !== parseInt(String(tid || ''), 10)) {
        otherRunning = true;
        break;
      }
    }

    var hasSess = !!(sess && sess.task_id);
    var running = hasSess && sessionRunning(sess);
    var paused = hasSess && sessionPaused(sess);

    showAlert('');
    if (c.canMutateTimer && otherRunning && tid) {
      showAlert((cfg().strings && cfg().strings.pauseOrComplete) || 'Pause your current timer before starting another.');
    }

    if (!c.canMutateTimer) {
      if (start) start.disabled = true;
      if (pause) pause.disabled = true;
      if (resume) resume.disabled = true;
      syncScheduleChrome();
      updateScheduleElapsedOnly(state.lastServerState);
      return;
    }

    if (start) {
      start.disabled = !tid || (hasSess && (running || paused)) || otherRunning;
    }
    if (pause) pause.disabled = !hasSess || !running;
    if (resume) resume.disabled = !hasSess || !paused;
    syncScheduleChrome();
    updateScheduleElapsedOnly(state.lastServerState);
  }

  function renderSummary(sum) {
    var sp = el('tasksession-timer-stat-planned');
    var st = el('tasksession-timer-stat-tracked');
    var est = sum && sum.estimated_seconds != null ? parseInt(String(sum.estimated_seconds), 10) : null;
    var logged = sum && sum.logged_seconds != null ? parseInt(String(sum.logged_seconds), 10) : 0;
    if (sp) sp.textContent = est && est > 0 ? formatHoursDecimal(est) : '—';
    if (st) st.textContent = formatHoursDecimal(logged);
    var plannedMeta = el('tasksession-timer-meta-planned');
    if (plannedMeta) {
      var plab = (cfg().strings && cfg().strings.plannedLabel) ? cfg().strings.plannedLabel : 'Planned';
      plannedMeta.textContent = plab + ': ' + formatPlannedMeta(est && est > 0 ? est : 0);
    }
    updateProgressFromSummary(sum || {});
  }

  function parseSqlDateTime(s) {
    if (s == null || String(s).trim() === '') return null;
    var raw = String(s).trim();
    var d = new Date(raw.replace(' ', 'T'));
    if (!isNaN(d.getTime())) return d;
    d = new Date(raw);
    return isNaN(d.getTime()) ? null : d;
  }

  function formatLogDateStack(isoOrMysql) {
    var d = parseSqlDateTime(isoOrMysql);
    if (!d) return { month: '—', day: '—' };
    var month = '—';
    var day = '—';
    try {
      month = d.toLocaleDateString('en-US', { month: 'short' }).toUpperCase();
      day = String(d.getDate());
    } catch (e0) {}
    return { month: month, day: day };
  }

  function logEntryDateYmd(entry) {
    var raw = entry && (entry.ended_at || entry.started_at);
    var d = parseSqlDateTime(raw);
    if (!d) return '';
    var pad = function (n) {
      return n < 10 ? '0' + n : String(n);
    };
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }

  function initialsForLogEntry(entry) {
    var c = cfg();
    var uid = parseInt(String(entry && entry.user_id != null ? entry.user_id : ''), 10) || 0;
    var cur = parseInt(String(c.userId || ''), 10) || 0;
    if (uid && cur && uid === cur && c.userInitials) {
      return String(c.userInitials).slice(0, 3);
    }
    var disp = entry && entry.user_display != null ? String(entry.user_display).trim() : '';
    if (!disp) return '?';
    var parts = disp.split(/\s+/).filter(Boolean);
    var ini = '';
    for (var i = 0; i < parts.length && ini.length < 3; i++) {
      var ch = parts[i].charAt(0);
      if (ch) ini += ch.toUpperCase();
    }
    return ini || '?';
  }

  var SVG_BILL = typeof tsIcon === 'function' ? tsIcon('currency-dollar') : '';
  var SVG_TIMER = '<svg fill="none" viewBox="0 0 20 20" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="1.2" d="M10 6.667v4.166M7.5 1.667h5m4.792 9.375A7.294 7.294 0 0 1 10 18.333a7.294 7.294 0 0 1-7.292-7.291A7.294 7.294 0 0 1 10 3.75a7.294 7.294 0 0 1 7.292 7.292"/></svg>';
  var SVG_MANUAL = typeof tsIcon === 'function' ? tsIcon('edit') : '';
  var SVG_LOG_MENU_EDIT = typeof tsIcon === 'function' ? tsIcon('edit', 'tasksession-timer-log-menu-ico') : '';
  var SVG_LOG_MENU_DELETE = typeof tsIcon === 'function' ? tsIcon('delete', 'tasksession-timer-log-menu-ico') : '';
  var SVG_LOG_MENU_MARK_INCOMPLETE = typeof tsIcon === 'function' ? tsIcon('x-circle', 'tasksession-timer-log-menu-ico') : '';

  function logRowMenuStrings() {
    var str = (cfg().strings) || {};
    return {
      tipTimer: str.timerLogVerifiedByTimer || 'Time verified by timer',
      tipManual: str.timerLogManualEntry || 'Manual entry',
      tipBillYes: str.timerLogTooltipBillable || str.billableYes || 'Billable',
      tipBillNo: str.timerLogTooltipNonBillable || str.billableNo || 'Non-billable',
      markIncomplete: str.timerLogMarkIncomplete || 'Mark as incomplete',
      editLog: str.timerLogEditEntry || 'Edit time log',
      deleteLog: str.timerLogDeleteEntry || 'Delete time log',
      confirmDelete: str.timerLogConfirmDelete || 'Delete this time log?'
    };
  }

  function initLogRowDropdowns(tbody) {
    if (!tbody || !window.bootstrap || !window.bootstrap.Dropdown) return;
    tbody.querySelectorAll('.tasksession-timer-log-dd [data-bs-toggle="dropdown"]').forEach(function (btn) {
      try {
        window.bootstrap.Dropdown.getOrCreateInstance(btn);
      } catch (eInit) {}
    });
  }

  function workRowMenuStrings() {
    var card = document.querySelector('.tasksession-timer-work-card');
    return {
      complete: card ? String(card.getAttribute('data-work-complete') || 'Complete work').trim() : 'Complete work',
      discard: card ? String(card.getAttribute('data-work-discard') || 'Delete work').trim() : 'Delete work',
      actionsAria: card ? String(card.getAttribute('data-work-actions-aria') || 'Actions').trim() : 'Actions'
    };
  }

  function buildWorkRowActionsDropdownHtml(actionsAria) {
    var m = workRowMenuStrings();
    var aria = actionsAria || m.actionsAria;
    return (
      '<div class="dropup dropdown tasksession-timer-log-dd">' +
      '<button type="button" class="btn-dots text-muted p-0 tasksession-timer-work-more" data-bs-toggle="dropdown" data-bs-popper="static" data-bs-auto-close="outside" aria-expanded="false" aria-haspopup="true" aria-label="' +
      escHtml(aria) +
      '" disabled>' +
      (typeof tsIcon === 'function' ? tsIcon('dots-vertical', 'w-6') : '') +
      '</button>' +
      '<ul class="dropdown-menu dropdown-menu-end tasksession-timer-log-dd-menu py-1">' +
      '<li><button type="button" class="dropdown-item tasksession-timer-work-action-item d-flex align-items-center" data-work-action="complete">' +
      (typeof tsIcon === 'function' ? tsIcon('check-circle', 'tasksession-timer-work-action-ico tasksession-timer-work-action-ico--complete flex-shrink-0') : '') +
      '<span>' +
      escHtml(m.complete) +
      '</span></button></li>' +
      '<li><button type="button" class="dropdown-item tasksession-timer-work-action-item d-flex align-items-center" data-work-action="discard">' +
      (typeof tsIcon === 'function' ? tsIcon('delete', 'tasksession-timer-delete-work-ico tasksession-timer-work-action-ico flex-shrink-0') : '') +
      '<span>' +
      escHtml(m.discard) +
      '</span></button></li>' +
      '</ul></div>'
    );
  }


  /** Avatar / DP name tooltips in scheduled work + logged time tables. */
  function initTimerAvatarTooltips(root) {
    if (!root || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
    root.querySelectorAll('.tasksession-timer-avatar-wrap[data-bs-toggle="tooltip"]').forEach(function (el) {
      try {
        var existing = bootstrap.Tooltip.getInstance(el);
        if (existing) existing.dispose();
        new bootstrap.Tooltip(el, {
          trigger: 'hover',
          placement: 'top',
          html: false,
          customClass: 'custom-tooltip',
          container: 'body'
        });
      } catch (eAv) {}
    });
  }

  function timerAvatarWrapHtml(avatarInner, userName) {
    var name = userName ? String(userName).trim() : '';
    var tip =
      name !== ''
        ? ' data-bs-toggle="tooltip" data-bs-placement="top" title="' + escHtml(name) + '"'
        : '';
    return (
      '<div class="tasksession-timer-work-user-cell">' +
      '<div class="tasksession-timer-avatar-wrap global-user-avatar"' +
      tip +
      '>' +
      avatarInner +
      '</div></div>'
    );
  }

  /** Match assets/js/general.js initializeTooltips (custom-tooltip, body container). */
  function initTimerLogRowTooltips(tbody) {
    if (!tbody || typeof bootstrap === 'undefined' || !bootstrap.Tooltip) return;
    tbody.querySelectorAll('.tasksession-timer-log-bill[data-bs-toggle="tooltip"], .tasksession-timer-log-type[data-bs-toggle="tooltip"]').forEach(function (el) {
      try {
        var existing = bootstrap.Tooltip.getInstance(el);
        if (existing) existing.dispose();
        new bootstrap.Tooltip(el, {
          trigger: 'hover',
          placement: 'top',
          html: false,
          customClass: 'custom-tooltip',
          container: 'body'
        });
      } catch (eTt) {}
    });
  }

  function wireLogRowActionsMenuOnce() {
    if (window.__tasksessionLogMenuWired) return;
    var logWrap = el('tasksession-timer-logs-table-wrap');
    if (!logWrap) return;
    window.__tasksessionLogMenuWired = true;
    logWrap.addEventListener('click', function (e) {
      var item = e.target.closest('.tasksession-timer-log-menu-item');
      if (!item) return;
      e.preventDefault();
      e.stopPropagation();
      var act = item.getAttribute('data-log-action') || '';
      var tr = item.closest('tr.tasksession-timer-log-row');
      var ddRoot = item.closest('.tasksession-timer-log-dd');
      var ddBtn = ddRoot ? ddRoot.querySelector('[data-bs-toggle="dropdown"]') : null;
      if (ddBtn && window.bootstrap && window.bootstrap.Dropdown) {
        var inst = window.bootstrap.Dropdown.getInstance(ddBtn);
        if (inst) inst.hide();
      }
      var tid = parseInt(String(state.sidebarTaskId || ''), 10) || 0;
      var lms = logRowMenuStrings();
      if (act === 'mark-incomplete') {
        if (!tid) return;
        postTimerAction({ action: 'mark_task_incomplete', task_id: tid }, 'mark task incomplete')
          .then(function (j) {
            if (!isOkResponse(j)) {
              showAlert(j.message || 'Error');
              return;
            }
            var newStatus = j.task_status || 'todo';
            if (state.lastTaskDetail) state.lastTaskDetail.status = newStatus;
            state.logPermissions = state.logPermissions || {};
            state.logPermissions.can_mark_task_incomplete = false;
            var kanbanSync = Promise.resolve();
            if (typeof window.comonKanbanSyncTaskToColumn === 'function') {
              kanbanSync = window.comonKanbanSyncTaskToColumn(tid, newStatus).catch(function () {});
            }
            return Promise.all([
              kanbanSync,
              refreshSidebarTaskStatusBadge(tid),
              loadLogs(tid)
            ]);
          })
          .catch(function (err) {
            logFetchError('mark task incomplete', err);
          });
        return;
      }
      if (act === 'edit-log' && tr) {
        openEditLogFromRow(tr);
        return;
      }
      if (act === 'delete-log' && tr && tr.dataset) {
        if (!cfg().canMutateTimer) return;
        var eid = parseInt(String(tr.dataset.logEntryId || ''), 10) || 0;
        if (!tid || !eid) return;
        if (!window.confirm(lms.confirmDelete)) return;
        postTimerAction({ action: 'delete_time_log', task_id: tid, entry_id: eid }, 'delete time log')
          .then(function (j) {
            if (!isOkResponse(j)) {
              showAlert(j.message || 'Error');
              return;
            }
            loadLogs(tid);
          })
          .catch(function (err) {
            logFetchError('delete time log', err);
          });
      }
    });
  }

  function renderLoggedTimeTable(logs, summary) {
    var tbody = el('tasksession-timer-logs-body');
    var wrap = el('tasksession-timer-logs-table-wrap');
    var empty = el('tasksession-timer-logs-empty');
    var countEl = el('tasksession-timer-logs-count');
    var totalEl = el('tasksession-timer-logs-total');
    if (!tbody || !wrap || !empty) return;

    var list = Array.isArray(logs) ? logs.slice() : [];
    list.sort(function (a, b) {
      var ta = parseSqlDateTime(a && a.ended_at) || parseSqlDateTime(a && a.started_at);
      var tb = parseSqlDateTime(b && b.ended_at) || parseSqlDateTime(b && b.started_at);
      var va = ta ? ta.getTime() : 0;
      var vb = tb ? tb.getTime() : 0;
      return vb - va;
    });

    tbody.innerHTML = '';
    var lms = logRowMenuStrings();
    list.forEach(function (entry) {
      var isClientRo = !!cfg().isClient;
      var tr = document.createElement('tr');
      tr.className = 'tasksession-timer-log-row';

      var tdDate = document.createElement('td');
      tdDate.className = 'tasksession-timer-log-td-date p-0 align-middle';
      var stack = formatLogDateStack(entry && (entry.ended_at || entry.started_at));
      tdDate.innerHTML =
        '<div class="tasksession-timer-work-date-cell tasksession-timer-work-date-cell--log">' +
        '<div class="tasksession-timer-schedule-date tasksession-timer-schedule-date--stack">' +
        '<span class="tasksession-timer-sd-month">' + escHtml(stack.month) + '</span>' +
        '<span class="tasksession-timer-sd-day">' + escHtml(stack.day) + '</span></div></div>';

      var tdUser = document.createElement('td');
      tdUser.className = 'tasksession-timer-log-td-user p-0 align-middle';
      var avHtml = entry && entry.user_avatar_html ? String(entry.user_avatar_html).trim() : '';
      if (!avHtml) {
        avHtml =
          '<div class="avatar-initials color-1 avatar-initials-medium" aria-hidden="true">' +
          escHtml(initialsForLogEntry(entry)) +
          '</div>';
      }
      var logUserName = entry && entry.user_display ? String(entry.user_display) : '';
      tdUser.innerHTML = timerAvatarWrapHtml(avHtml, logUserName);
      var tdComment = document.createElement('td');
      var note = entry && entry.notes != null ? String(entry.notes).trim() : '';
      tdComment.textContent = note || '—';
      tdComment.className = 'text-truncate tasksession-timer-log-td-comment';
      var bill = parseInt(String(entry && entry.billable != null ? entry.billable : '0'), 10) === 1;
      var tdBill = null;
      if (!isClientRo) {
        tdBill = document.createElement('td');
        tdBill.className = 'text-center';
        var billWrap = document.createElement('div');
        billWrap.className = 'tasksession-timer-log-bill ' + (bill ? 'tasksession-timer-log-bill--yes' : 'tasksession-timer-log-bill--no');
        var billTip = bill ? lms.tipBillYes : lms.tipBillNo;
        billWrap.setAttribute('data-bs-toggle', 'tooltip');
        billWrap.setAttribute('data-bs-placement', 'top');
        billWrap.setAttribute('title', billTip);
        billWrap.setAttribute('aria-label', billTip);
        billWrap.innerHTML = SVG_BILL;
        tdBill.appendChild(billWrap);
      }
      var tdType = document.createElement('td');
      tdType.className = 'text-center';
      var typeWrap = document.createElement('div');
      var src = entry && entry.source != null ? String(entry.source).toLowerCase() : 'timer';
      if (src === 'manual') {
        typeWrap.className = 'tasksession-timer-log-type tasksession-timer-log-type--manual';
        typeWrap.innerHTML = SVG_MANUAL;
        typeWrap.setAttribute('data-bs-toggle', 'tooltip');
        typeWrap.setAttribute('data-bs-placement', 'top');
        typeWrap.setAttribute('title', lms.tipManual);
        typeWrap.setAttribute('aria-label', lms.tipManual);
      } else {
        typeWrap.className = 'tasksession-timer-log-type';
        typeWrap.innerHTML = SVG_TIMER;
        typeWrap.setAttribute('data-bs-toggle', 'tooltip');
        typeWrap.setAttribute('data-bs-placement', 'top');
        typeWrap.setAttribute('title', lms.tipTimer);
        typeWrap.setAttribute('aria-label', lms.tipTimer);
      }
      tdType.appendChild(typeWrap);
      var tdTime = document.createElement('td');
      tdTime.className = 'text-end tasksession-timer-log-time';
      var dur = entry && entry.duration_seconds != null ? parseInt(String(entry.duration_seconds), 10) : 0;
      var durLabel = formatHoursDecimal(Math.max(0, dur));
      tdTime.textContent = durLabel;

      tr.dataset.logMonthDay = (stack.month + ' ' + stack.day).trim();
      tr.dataset.logUser = entry && entry.user_display != null ? String(entry.user_display) : '';
      tr.dataset.logNote = note;
      tr.dataset.logNoteRaw = note;
      tr.dataset.logDurationLabel = durLabel;
      tr.dataset.logBillable = bill ? '1' : '0';
      tr.dataset.logEntryId = String(entry && entry.id != null ? entry.id : '');
      tr.dataset.logEntryDurationSec = String(Math.max(0, dur));
      tr.dataset.logDateYmd = logEntryDateYmd(entry);

      var tdActions = null;
      if (!isClientRo) {
        tdActions = document.createElement('td');
        tdActions.className = 'text-end tasksession-timer-log-td-actions align-middle p-0';
        var perm = state.logPermissions || {};
        var showReopen = !!perm.can_mark_task_incomplete;
        var canTm = !!cfg().canMutateTimer;
        var myUid = parseInt(String(cfg().userId || ''), 10) || 0;
        var entryUid = entry && entry.user_id != null ? parseInt(String(entry.user_id), 10) || 0 : 0;
        var isOwnLog = myUid > 0 && entryUid === myUid;
        var canEditThis =
          canTm &&
          ((isOwnLog && !!perm.timer_log_edit_own) || (!isOwnLog && !!perm.timer_log_edit));
        var canDeleteThis =
          canTm &&
          ((isOwnLog && !!perm.timer_log_delete_own) || (!isOwnLog && !!perm.timer_log_delete));
        var menuItems = [];
        if (showReopen) {
          menuItems.push(
            '<li><button type="button" class="dropdown-item tasksession-timer-log-menu-item" data-log-action="mark-incomplete">' +
              '<span class="tasksession-timer-log-menu-row">' +
              SVG_LOG_MENU_MARK_INCOMPLETE +
              '<span class="tasksession-timer-log-menu-label">' +
              escHtml(lms.markIncomplete) +
              '</span></span></button></li>'
          );
        }
        if (canEditThis) {
          menuItems.push(
            '<li><button type="button" class="dropdown-item tasksession-timer-log-menu-item" data-log-action="edit-log">' +
              '<span class="tasksession-timer-log-menu-row">' +
              SVG_LOG_MENU_EDIT +
              '<span class="tasksession-timer-log-menu-label">' +
              escHtml(lms.editLog) +
              '</span></span></button></li>'
          );
        }
        if (canDeleteThis) {
          menuItems.push(
            '<li><button type="button" class="dropdown-item tasksession-timer-log-menu-item tasksession-timer-log-menu-item--delete" data-log-action="delete-log">' +
              '<span class="tasksession-timer-log-menu-row">' +
              SVG_LOG_MENU_DELETE +
              '<span class="tasksession-timer-log-menu-label">' +
              escHtml(lms.deleteLog) +
              '</span></span></button></li>'
          );
        }
        if (menuItems.length === 0) {
          tdActions.innerHTML = '';
        } else {
          var svgDots = typeof tsIcon === 'function' ? tsIcon('dots-vertical', 'w-6') : '';
          tdActions.innerHTML =
            '<div class="dropup dropdown tasksession-timer-log-dd">' +
            '<button type="button" class="btn-dots text-muted p-0" data-bs-toggle="dropdown" data-bs-popper="static" data-bs-auto-close="outside" aria-expanded="false" aria-haspopup="true" aria-label="' +
            escHtml((((cfg().strings) || {}).timerLogActionsAria) || 'Actions') +
            '">' +
            svgDots +
            '</button>' +
            '<ul class="dropdown-menu dropdown-menu-end tasksession-timer-log-dd-menu py-1">' +
            menuItems.join('') +
            '</ul></div>';
        }
      }

      tr.appendChild(tdDate);
      tr.appendChild(tdUser);
      tr.appendChild(tdComment);
      if (tdBill) tr.appendChild(tdBill);
      tr.appendChild(tdType);
      tr.appendChild(tdTime);
      if (tdActions) tr.appendChild(tdActions);
      tbody.appendChild(tr);
    });

    initLogRowDropdowns(tbody);
    initTimerLogRowTooltips(tbody);
    initTimerAvatarTooltips(wrap);

    var n = list.length;
    if (countEl) countEl.textContent = String(n);
    var loggedSec = summary && summary.logged_seconds != null ? parseInt(String(summary.logged_seconds), 10) : 0;
    if (totalEl) totalEl.textContent = formatHoursDecimal(Math.max(0, loggedSec));

    if (n === 0) {
      wrap.classList.add('d-none');
      empty.classList.add('is-visible');
    } else {
      empty.classList.remove('is-visible');
      wrap.classList.remove('d-none');
    }
  }

  function focusTimerTabAndLogsExpand(forTaskId) {
    var tid = parseInt(String(forTaskId || ''), 10) || 0;
    var sidebar = document.getElementById('task-sidebar');
    if (!sidebar || !sidebar.classList.contains('open')) return;
    var cur = sidebar.dataset && sidebar.dataset.taskId ? parseInt(String(sidebar.dataset.taskId), 10) || 0 : 0;
    if (tid && cur && tid !== cur) return;

    var tab = document.getElementById('timer-tab');
    if (tab && typeof jQuery !== 'undefined' && jQuery.fn && jQuery.fn.tab) {
      try {
        jQuery(tab).tab('show');
      } catch (eTab) {}
    }

    var col = el('tasksession-timer-logs-collapse');
    if (col) {
      if (window.bootstrap && window.bootstrap.Collapse) {
        try {
          window.bootstrap.Collapse.getOrCreateInstance(col, { toggle: false }).show();
        } catch (e1) {}
      } else {
        col.classList.add('show');
      }
    }
    var btn = el('tasksession-timer-logs-toggle');
    if (btn) btn.setAttribute('aria-expanded', 'true');
  }

  function renderScheduledWorkList(schedules) {
    var list = el('tasksession-timer-work-list');
    var empty = el('tasksession-timer-work-empty');
    if (!list) return;

    state.schedules = schedules || [];
    var n = state.schedules.length;
    updateTimerTabBadgeCount(n);
    if (empty) empty.classList.toggle('d-none', n > 0);

    var me = parseInt(String(cfg().userId || ''), 10) || 0;
    var str = cfg().strings || {};
    var startLbl = str.startTimer || 'Start';
    var card = document.querySelector('.tasksession-timer-work-card');
    var actionsAria = card ? String(card.getAttribute('data-work-actions-aria') || 'Actions') : 'Actions';

    list.innerHTML = '';
    state.schedules.forEach(function (sched) {
      var uid = parseInt(String(sched.user_id || ''), 10) || 0;
      var isMine = !!(uid && me && uid === me);
      var dateStack = formatScheduleDateStack(sched.schedule_date);
      var planned =
        sched.planned_seconds != null && parseInt(String(sched.planned_seconds), 10) > 0
          ? formatEstimateTrigger(sched.planned_seconds)
          : '—';
      var avatar = sched.user_avatar_html ? String(sched.user_avatar_html) : '';
      if (!avatar) {
        avatar = '<div class="avatar-initials color-1 avatar-initials-medium" aria-hidden="true">?</div>';
      }

      var row = document.createElement('div');
      row.className = 'tasksession-timer-work-row';
      row.setAttribute('data-schedule-user-id', String(uid));
      row.setAttribute('data-schedule-id', String(sched.id || ''));
      if (isMine) row.setAttribute('data-is-mine', '1');

      var isClientRo = !!cfg().isClient;
      var timerControlsHtml = '';
      if (!isClientRo) {
        timerControlsHtml =
          '<button type="button" class="btn tasksession-timer-sched-play tasksession-timer-play-circle" aria-label="' +
          escHtml(startLbl) +
          '">' +
          (typeof tsIcon === 'function' ? tsIcon('play', 'tasksession-timer-ico-play') : '') +
          (typeof tsIcon === 'function' ? tsIcon('pause', 'tasksession-timer-ico-pause d-none') : '') +
          '</button>' +
          '<span class="tasksession-timer-sched-elapsed">00:00:00</span>' +
          buildWorkRowActionsDropdownHtml(actionsAria);
      }

      row.innerHTML =
        '<div class="tasksession-timer-work-date-cell">' +
        '<div class="tasksession-timer-schedule-date tasksession-timer-schedule-date--stack">' +
        '<span class="tasksession-timer-sd-month">' + escHtml(dateStack.month) + '</span>' +
        '<span class="tasksession-timer-sd-day">' + escHtml(dateStack.day) + '</span>' +
        '</div></div>' +
        timerAvatarWrapHtml(avatar, sched.user_display ? String(sched.user_display) : '') +
        '<div class="tasksession-timer-work-planned-cell">' +
        '<span class="tasksession-timer-sched-planned-label">' + escHtml(planned) + '</span></div>' +
        '<div class="tasksession-timer-work-timer-cell' + (isClientRo ? ' tasksession-timer-work-timer-cell--client' : '') + '">' +
        (isClientRo ? '<span class="tasksession-timer-sched-elapsed">00:00:00</span>' : timerControlsHtml) +
        '</div>';

      list.appendChild(row);
    });

    pruneStaleScheduleRowFocus();
    ensureActiveScheduleRowForSession();
    syncScheduleChrome();
    updateScheduleElapsedOnly(state.lastServerState);
    initTimerAvatarTooltips(list);
    initLogRowDropdowns(list);
  }

  function loadSchedules(taskId) {
    if (!taskId || !cfg().enabled) return Promise.resolve();
    var u = apiUrl() + '?action=schedules&task_id=' + encodeURIComponent(String(taskId));
    var errFallback = (cfg().strings && cfg().strings.timerLoadError) || 'Could not load scheduled work.';
    return fetch(u, { credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (text) {
          var data = null;
          try {
            data = JSON.parse(text);
          } catch (err) {
            timerDebugAppend('schedules: JSON parse error for task ' + taskId + ': ' + String(text).slice(0, 400));
            data = null;
          }
          return { data: data };
        });
      })
      .then(function (res) {
        if (!res.data || res.data.status !== 'ok') {
          var msg = (res.data && res.data.message) ? String(res.data.message) : errFallback;
          timerDebugAppend('schedules API: ' + msg + ' (task_id=' + taskId + ')');
          renderScheduledWorkList([]);
          return;
        }
        renderScheduledWorkList(res.data.schedules || []);
      })
      .catch(function (err) {
        logFetchError('schedules', err);
        renderScheduledWorkList([]);
      });
  }

  function loadLogs(taskId) {
    if (!taskId || !cfg().enabled) return Promise.resolve();
    var u = apiUrl() + '?action=logs&task_id=' + encodeURIComponent(String(taskId));
    var errFallback = (cfg().strings && cfg().strings.timerLoadError) || 'Could not load time data.';
    return fetch(u, { credentials: 'same-origin' })
      .then(function (r) {
        return r.text().then(function (text) {
          var data = null;
          try {
            data = JSON.parse(text);
          } catch (err) {
            timerDebugAppend('logs: JSON parse error for task ' + taskId + ': ' + (err && err.message ? err.message : '') + ' | body: ' + String(text).slice(0, 600));
            data = null;
          }
          return { data: data };
        });
      })
      .then(function (res) {
        if (!res.data || res.data.status !== 'ok') {
          var msg = (res.data && res.data.message) ? String(res.data.message) : errFallback;
          timerDebugAppend('logs API: ' + msg + ' (task_id=' + taskId + ')');
          state.logPermissions = {};
          applyManualEntryTabVisibility();
          renderSummary({});
          renderLoggedTimeTable([], {});
          buildPlannedDropdown(null, false);
          applyTimerButtons();
          updateStatActive(state.lastServerState);
          return;
        }
        renderSummary(res.data.summary || {});
        var sum = res.data.summary || {};
        if (res.data.task_status && state.lastTaskDetail && (parseInt(String(state.sidebarTaskId || ''), 10) || 0) === taskId) {
          state.lastTaskDetail.status = res.data.task_status;
        }
        state.logPermissions = res.data.permissions || {};
        applyManualEntryTabVisibility();
        var estVal = sum.estimated_seconds != null ? sum.estimated_seconds : null;
        var canEst = res.data.permissions && res.data.permissions.can_edit_estimate;
        buildPlannedDropdown(estVal, !!(canEst && cfg().canEditEstimateInSidebar));
        applyTimerButtons();
        updateStatActive(state.lastServerState);
        renderLoggedTimeTable(res.data.logs || [], sum);
      })
      .catch(function (err) {
        logFetchError('logs', err);
        state.logPermissions = {};
        applyManualEntryTabVisibility();
        renderSummary({});
        renderLoggedTimeTable([], {});
        buildPlannedDropdown(null, false);
        applyTimerButtons();
        updateStatActive(state.lastServerState);
      });
  }

  function syncStateAndUi() {
    return refreshServerState().then(function (data) {
      if (data && data.status === 'ok') {
        state.lastServerState = normalizeTimerState(data);
        markHeaderElapsedBaselineFromApi(state.lastServerState);
        markDashLoggedBaselineFromApi(state.lastServerState);
      } else if (data == null) {
        timerDebugAppend('timer state: response not JSON or parse failed (check ajax/task_timer.php and DB tables task_timer_sessions, task_time_entries).');
      }
      applyTimerButtons();
      updateHeaderWidget(state.lastServerState);
      restartHeaderLiveClock();
      updateStatActive(state.lastServerState);
      updateStaffDashTimerCards(state.lastServerState);
      updateScheduleElapsedOnly(state.lastServerState);
      syncKanbanTimerBadges(state.lastServerState);
      return state.lastServerState;
    }).catch(function (err) {
      timerDebugAppend('timer state network: ' + (err && err.message ? err.message : String(err)));
      return null;
    });
  }

  function getHeaderSessionsListSignature(d) {
    var list = getSessionsList(normalizeTimerState(d)).slice();
    list.sort(function (a, b) {
      return (parseInt(String(a.task_id), 10) || 0) - (parseInt(String(b.task_id), 10) || 0);
    });
    return list.map(function (e) {
      var tid = parseInt(String(e.task_id || ''), 10) || 0;
      return String(tid) + ':' + normSessionStatus(e) + ':' + String(e.task_title != null ? e.task_title : '') + ':' + String(e.project_title != null ? e.project_title : '');
    }).join('|');
  }

  function updateHeaderTimerRowsElapsedOnly(d) {
    var root = el('tasksession-header-timer-root');
    if (!root || !d || d.status !== 'ok') return;
    var dNorm = normalizeTimerState(d);
    var pillTime = root.querySelector('.tasksession-header-timer-pill-time');
    if (pillTime) pillTime.textContent = formatDuration(getHeaderDisplayElapsed(dNorm));
    var ul = el('tasksession-header-timer-list');
    if (!ul) return;
    ul.querySelectorAll('.tasksession-header-timer-row[data-task-id]').forEach(function (rowEl) {
      var tid = parseInt(rowEl.getAttribute('data-task-id') || '0', 10) || 0;
      if (!tid) return;
      var entry = getSessionForTask(tid, dNorm);
      if (!entry) return;
      var rowElapsed = rowEl.querySelector('.tasksession-header-timer-row-elapsed');
      if (rowElapsed) {
        rowElapsed.textContent = formatDuration(getElapsedForTaskDisplay(entry, dNorm));
        rowElapsed.classList.toggle('tasksession-header-timer-row-elapsed--running', sessionRunning(entry));
      }
      rowEl.classList.toggle('tasksession-header-timer-row--active', sessionRunning(entry));
    });
  }

  function updateHeaderWidget(data) {
    var root = el('tasksession-header-timer-root');
    if (!root || !cfg().canMutateTimer) return;
    var pillBtn = root.querySelector('.tasksession-header-timer-pill');
    if (!data || data.status !== 'ok') {
      root.classList.add('d-none');
      state.headerRowsSignature = '';
      if (pillBtn) pillBtn.classList.remove('tasksession-header-timer-pill--clock-active');
      setHeaderTimerSlotEmpty(true);
      return;
    }
    var d = normalizeTimerState(data);
    var list = getSessionsList(d);
    var pillTime = root.querySelector('.tasksession-header-timer-pill-time');

    if (!list.length) {
      root.classList.add('d-none');
      state.headerRowsSignature = '';
      if (pillBtn) pillBtn.classList.remove('tasksession-header-timer-pill--clock-active');
      setHeaderTimerSlotEmpty(true);
      return;
    }

    setHeaderTimerSlotEmpty(false);
    root.classList.remove('d-none');
    var hasRunning = false;
    var ri;
    for (ri = 0; ri < list.length; ri++) {
      if (sessionRunning(list[ri])) {
        hasRunning = true;
        break;
      }
    }
    if (pillBtn) pillBtn.classList.toggle('tasksession-header-timer-pill--clock-active', hasRunning);

    var elapsedPill = getHeaderDisplayElapsed(d);
    if (pillTime) pillTime.textContent = formatDuration(elapsedPill);

    var ul = el('tasksession-header-timer-list');
    var tpl = el('tasksession-header-timer-row-tpl');
    if (!ul || !tpl || !tpl.content) return;

    var sig = getHeaderSessionsListSignature(d);
    var rowCount = ul.querySelectorAll('.tasksession-header-timer-row').length;
    var firstDataRow = ul.querySelector('.tasksession-header-timer-row[data-task-id]');
    if (sig === state.headerRowsSignature && rowCount === list.length && list.length > 0 && firstDataRow) {
      updateHeaderTimerRowsElapsedOnly(d);
      return;
    }
    state.headerRowsSignature = sig;
    ul.innerHTML = '';

    dismissHeaderTimerSkeleton();

    list.forEach(function (entry) {
      var node = tpl.content.cloneNode(true);
      var row = node.querySelector('.tasksession-header-timer-row');
      var rowTitle = node.querySelector('.tasksession-header-timer-row-title');
      var rowProject = node.querySelector('.tasksession-header-timer-row-project');
      var rowElapsed = node.querySelector('.tasksession-header-timer-row-elapsed');
      var btnPause = node.querySelector('.tasksession-header-timer-pause');
      var btnResume = node.querySelector('.tasksession-header-timer-resume');
      var btnComplete = node.querySelector('.tasksession-header-timer-header-complete');
      var btnDiscard = node.querySelector('[data-header-timer-action="discard"]');
      var tid = parseInt(String(entry.task_id || ''), 10) || 0;
      var title = entry.task_title ? String(entry.task_title) : ('#' + String(tid));
      var project = entry.project_title ? String(entry.project_title) : '';
      if (row && tid) row.setAttribute('data-task-id', String(tid));
      if (rowTitle) rowTitle.textContent = title;
      if (rowProject) rowProject.textContent = project;
      var rowSec = getElapsedForTaskDisplay(entry, d);
      if (rowElapsed) {
        rowElapsed.textContent = formatDuration(rowSec);
        rowElapsed.classList.toggle('tasksession-header-timer-row-elapsed--running', sessionRunning(entry));
      }
      if (row) row.classList.toggle('tasksession-header-timer-row--active', sessionRunning(entry));
      if (btnPause && btnResume) {
        if (sessionRunning(entry)) {
          btnPause.classList.remove('d-none');
          btnResume.classList.add('d-none');
        } else {
          btnPause.classList.add('d-none');
          btnResume.classList.remove('d-none');
        }
      }
      [btnPause, btnResume, btnComplete, btnDiscard].forEach(function (b) {
        if (b && tid) b.setAttribute('data-task-id', String(tid));
      });
      ul.appendChild(node);
    });
  }

  function wireActions() {
    if (window.__tasksessionTaskTimerActionsWired) return;
    window.__tasksessionTaskTimerActionsWired = true;
    var start = el('tasksession-timer-btn-start');
    var pause = el('tasksession-timer-btn-pause');
    var resume = el('tasksession-timer-btn-resume');
    if (start) {
      start.addEventListener('click', function () {
        var tid = state.sidebarTaskId;
        if (!tid) return;
        runSidebarTimerMutation({ action: 'start', task_id: tid }, 'timer start', function () {
          syncStateAndUi();
        });
      });
    }
    if (pause) {
      pause.addEventListener('click', function () {
        var tid = state.sidebarTaskId;
        if (!tid) return;
        runSidebarTimerMutation({ action: 'pause', task_id: tid }, 'timer pause', function () {
          syncStateAndUi();
        });
      });
    }
    if (resume) {
      resume.addEventListener('click', function () {
        var tid = state.sidebarTaskId;
        if (!tid) return;
        runSidebarTimerMutation({ action: 'resume', task_id: tid }, 'timer resume', function () {
          syncStateAndUi();
        });
      });
    }
    if (!window.__tasksessionSidebarTimerDropdownsWired) {
      window.__tasksessionSidebarTimerDropdownsWired = true;

      var plannedTrig = el('tasksession-timer-planned-trigger');
      var plannedMenu = el('tasksessionTimerPlannedDropdown');
      if (plannedTrig && plannedMenu) {
        plannedTrig.addEventListener('click', function (e) {
          e.stopPropagation();
          togglePlannedDropdown();
        });
        plannedMenu.addEventListener('click', function (e) {
          e.stopPropagation();
          var clearBtn = e.target.closest('button[data-estimate-clear]');
          if (clearBtn) {
            e.preventDefault();
            submitEstimateSeconds(null);
            return;
          }
          var opt = e.target.closest('button[data-seconds]');
          if (!opt) return;
          e.preventDefault();
          var raw = opt.getAttribute('data-seconds');
          var sec = raw != null ? parseInt(String(raw), 10) : 0;
          if (!isFinite(sec) || sec <= 0) return;
          submitEstimateSeconds(sec);
        });
      }

      var workList = el('tasksession-timer-work-list');
      if (workList && !window.__tasksessionWorkListWired) {
        window.__tasksessionWorkListWired = true;
        workList.addEventListener('show.bs.dropdown', function (e) {
          var btn = e.target;
          if (!btn || !btn.classList || !btn.classList.contains('tasksession-timer-work-more')) return;
          var row = btn.closest('.tasksession-timer-work-row');
          state.workActionsTrigger = btn;
          state.workActionsScheduleRowId = row
            ? parseInt(row.getAttribute('data-schedule-id') || '0', 10) || 0
            : 0;
          closePlannedDropdown();
          closeScheduleMorePlannedDropdown();
          closeScheduleMoreAssigneeDropdown();
          var menu = btn.nextElementSibling;
          if (menu && menu.classList && menu.classList.contains('dropdown-menu')) {
            positionSidebarDropdown(btn, menu, { preferAbove: true, alignEnd: true });
          }
        });
        workList.addEventListener('hidden.bs.dropdown', function (e) {
          var btn = e.target;
          if (!btn || !btn.classList || !btn.classList.contains('tasksession-timer-work-more')) return;
          var menu = btn.nextElementSibling;
          if (menu) clearSidebarDropdownFixed(menu);
        });
        workList.addEventListener('click', function (e) {
          var actBtn = e.target.closest('button[data-work-action]');
          if (actBtn) {
            e.preventDefault();
            e.stopPropagation();
            var act = actBtn.getAttribute('data-work-action');
            if (act === 'complete') submitTimerCompleteWork();
            else if (act === 'discard') submitTimerDiscardWork();
            return;
          }
          var playBtn = e.target.closest('.tasksession-timer-sched-play');
          if (playBtn && !playBtn.disabled) {
            if (cfg().isClient) return;
            var row = playBtn.closest('.tasksession-timer-work-row');
            var prevActive = state.activeScheduleRowId || 0;
            if (row) setActiveScheduleRowFromEl(row);
            var rowSid = state.activeScheduleRowId || 0;
            var tid = state.sidebarTaskId;
            var sess = getSessionForTask(tid, state.lastServerState);
            var running = !!(sess && sessionRunning(sess));
            var paused = !!(sess && sessionPaused(sess));
            if (prevActive && rowSid && prevActive !== rowSid && (running || paused)) {
              syncScheduleChrome();
              updateScheduleElapsedOnly(state.lastServerState);
              return;
            }
            var pauseB = el('tasksession-timer-btn-pause');
            var resumeB = el('tasksession-timer-btn-resume');
            var startB = el('tasksession-timer-btn-start');
            if (running && isScheduleRowActive(row)) {
              if (pauseB && !pauseB.disabled) pauseB.click();
              return;
            }
            if (paused && isScheduleRowActive(row)) {
              if (resumeB && !resumeB.disabled) resumeB.click();
              return;
            }
            if (startB && !startB.disabled) startB.click();
            return;
          }
        }, true);
      }

      wireLogRowActionsMenuOnce();

      document.addEventListener('click', function (e) {
        closePlannedDropdown();
        if (!e.target.closest('.tasksession-timer-log-dd')) {
          closeWorkActionsDropdown();
        }
        closeScheduleMorePlannedDropdown();
        closeScheduleMoreAssigneeDropdown();
        closeCompleteLogAssigneeDropdown();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          closePlannedDropdown();
          closeWorkActionsDropdown();
          closeScheduleMorePlannedDropdown();
          closeScheduleMoreAssigneeDropdown();
          closeCompleteLogAssigneeDropdown();
        }
      });
      window.addEventListener('resize', repositionOpenTimerSidebarDropdowns);
      var sbScroll = document.querySelector('.task-sidebar-content');
      if (sbScroll) sbScroll.addEventListener('scroll', repositionOpenTimerSidebarDropdowns, true);
    }

    if (!window.__tasksessionScheduleMoreWired) {
      window.__tasksessionScheduleMoreWired = true;
      buildScheduleMorePlannedMenu();
      var smPlannedTrig = el('tasksession-schedule-more-planned-trigger');
      var smPlannedMenu = el('tasksessionScheduleMorePlannedDropdown');
      if (smPlannedTrig && smPlannedMenu) {
        smPlannedTrig.addEventListener('click', function (e) {
          e.stopPropagation();
          toggleScheduleMorePlannedDropdown();
        });
        smPlannedMenu.addEventListener('click', function (e) {
          e.stopPropagation();
          var opt = e.target.closest('button[data-schedule-seconds]');
          if (!opt) return;
          e.preventDefault();
          var sec = parseInt(opt.getAttribute('data-schedule-seconds'), 10);
          if (!isFinite(sec) || sec <= 0) return;
          var hid = el('tasksession-schedule-planned-seconds');
          if (hid) hid.value = String(sec);
          setScheduleMorePlannedLabel(sec);
          closeScheduleMorePlannedDropdown();
        });
      }
      var smSel = el('tasksession-schedule-assignee-selected');
      var smDd = el('tasksession-schedule-assignee-dropdown');
      if (smSel && smDd) {
        smSel.addEventListener('click', function (e) {
          e.stopPropagation();
          closePlannedDropdown();
          closeWorkActionsDropdown();
          closeScheduleMorePlannedDropdown();
          closeCompleteLogAssigneeDropdown();
          var smOpts = el('tasksession-schedule-assignee-options');
          if (!smOpts) return;
          var open = smOpts.style.display === 'block';
          smOpts.style.display = open ? 'none' : 'block';
          smSel.classList.toggle('open', !open);
          smSel.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
      }
      var smSave = el('tasksession-schedule-more-save');
      if (smSave) smSave.addEventListener('click', submitScheduleMoreWork);
      var smModal = el('tasksessionScheduleMoreModal');
      if (smModal && window.bootstrap && window.bootstrap.Modal) {
        smModal.addEventListener('show.bs.modal', function () {
          bumpTimerSidebarModalOpen(1);
          if (smModal.parentElement !== document.body) {
            document.body.appendChild(smModal);
          }
          openScheduleMoreModalPrep();
        });
        smModal.addEventListener('hidden.bs.modal', function () {
          bumpTimerSidebarModalOpen(-1);
        });
      }
    }

    var completeModal = el('tasksessionTimerCompleteModal');
    if (completeModal && !window.__tasksessionTimerCompleteModalWired) {
      window.__tasksessionTimerCompleteModalWired = true;
      completeModal.addEventListener('show.bs.modal', function () {
        bumpTimerSidebarModalOpen(1);
        if (completeModal.parentElement !== document.body) {
          document.body.appendChild(completeModal);
        }
        applyManualEntryTabVisibility();
      });
      completeModal.addEventListener('hidden.bs.modal', function () {
        bumpTimerSidebarModalOpen(-1);
      });
      completeModal.addEventListener('shown.bs.modal', function () {
        var pref = state.completeModalPreferManual && canShowManualTimeEntry();
        state.completeModalPreferManual = false;
        if (pref) {
          setCompleteModalMode('manual');
          syncManualDurationFromSeconds(0);
        } else {
          setCompleteModalMode('timer');
          syncCompleteModalLoggedDisplay();
        }
      });
      var completeTabList = el('tasksessionTimerCompleteTabList');
      if (completeTabList) {
        completeTabList.addEventListener('shown.bs.tab', function (e) {
          var trig = e.target;
          if (!trig || !trig.classList || !trig.classList.contains('tasksession-timer-complete-mode')) return;
          var m = trig.getAttribute('data-complete-mode');
          if (m) setCompleteModalMode(m);
        });
      }
      completeModal.querySelectorAll('.tasksession-timer-manual-dur-opt').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var s = parseInt(btn.getAttribute('data-seconds'), 10);
          var hidSec = el('tasksession-timer-complete-manual-seconds');
          var lab = el('tasksession-timer-complete-manual-dropdown-label');
          if (hidSec && isFinite(s)) hidSec.value = String(s);
          if (lab) lab.textContent = String(btn.textContent || '').trim();
        });
      });
      var saveComplete = el('tasksession-timer-complete-save');
      if (saveComplete) {
        saveComplete.addEventListener('click', function () {
          submitTimerCompleteFromModal();
        });
      }
      var cLogSel = el('tasksession-complete-log-assignee-selected');
      var cLogDd = el('tasksession-complete-log-assignee-dropdown');
      if (cLogSel && cLogDd) {
        cLogSel.addEventListener('click', function (e) {
          e.stopPropagation();
          closePlannedDropdown();
          closeWorkActionsDropdown();
          closeScheduleMorePlannedDropdown();
          closeScheduleMoreAssigneeDropdown();
          var cOpts = el('tasksession-complete-log-assignee-options');
          if (!cOpts) return;
          var open = cOpts.style.display === 'block';
          cOpts.style.display = open ? 'none' : 'block';
          cLogSel.classList.toggle('open', !open);
          cLogSel.setAttribute('aria-expanded', open ? 'false' : 'true');
        });
      }
    }

    var editLogModal = el('tasksessionTimerEditLogModal');
    if (editLogModal && !window.__tasksessionEditLogModalWired) {
      window.__tasksessionEditLogModalWired = true;
      editLogModal.addEventListener('show.bs.modal', function () {
        bumpTimerSidebarModalOpen(1);
        if (editLogModal.parentElement !== document.body) {
          document.body.appendChild(editLogModal);
        }
      });
      editLogModal.addEventListener('hidden.bs.modal', function () {
        bumpTimerSidebarModalOpen(-1);
      });
      editLogModal.querySelectorAll('.tasksession-edit-log-dur-opt').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var s = parseInt(btn.getAttribute('data-seconds'), 10);
          var hidSec = el('tasksession-edit-log-manual-seconds');
          var lab = el('tasksession-edit-log-manual-dropdown-label');
          if (hidSec && isFinite(s)) hidSec.value = String(s);
          if (lab) lab.textContent = String(btn.textContent || '').trim();
        });
      });
      var saveEditLog = el('tasksession-edit-log-save');
      if (saveEditLog) {
        saveEditLog.addEventListener('click', function () {
          submitEditLogFromModal();
        });
      }
    }

    var logMoreBtn = el('tasksession-timer-log-more-btn');
    if (logMoreBtn && !window.__tasksessionTimerLogMoreWired) {
      window.__tasksessionTimerLogMoreWired = true;
      logMoreBtn.addEventListener('click', function () {
        if (!cfg().canMutateTimer || cfg().isClient) return;
        var tid = parseInt(String(state.sidebarTaskId || ''), 10) || 0;
        if (!tid) return;
        openTimerCompleteModal(tid, { preferManual: true });
      });
    }

    var hp = el('tasksession-header-timer-root');
    if (hp && !window.__tasksessionHeaderTimerDelegated) {
      window.__tasksessionHeaderTimerDelegated = true;
      hp.addEventListener('click', function (e) {
        var colTxt = e.target.closest('.tasksession-header-timer-col-text');
        if (colTxt) {
          var rowOpen = colTxt.closest('.tasksession-header-timer-row');
          var tOpen = rowOpen ? parseInt(rowOpen.getAttribute('data-task-id') || '0', 10) : 0;
          if (tOpen) {
            var openSb = typeof window.openTaskSidebar === 'function' ? window.openTaskSidebar : (typeof openTaskSidebar === 'function' ? openTaskSidebar : null);
            if (openSb) {
              e.preventDefault();
              e.stopPropagation();
              openSb(tOpen);
            }
          }
          return;
        }
        var disc = e.target.closest('[data-header-timer-action="discard"]');
        if (disc) {
          e.preventDefault();
          e.stopPropagation();
          var d1 = parseInt(disc.getAttribute('data-task-id') || '0', 10);
          submitTimerDiscardWork(d1);
          return;
        }
        var pauseB = e.target.closest('.tasksession-header-timer-pause');
        if (pauseB && !pauseB.classList.contains('d-none')) {
          e.stopPropagation();
          var t2 = parseInt(pauseB.getAttribute('data-task-id') || '0', 10);
          if (t2) {
            postTimerAction({ action: 'pause', task_id: t2 }, 'header pause').then(function () { syncStateAndUi(); });
          }
          return;
        }
        var resumeB = e.target.closest('.tasksession-header-timer-resume');
        if (resumeB && !resumeB.classList.contains('d-none')) {
          e.stopPropagation();
          var t3 = parseInt(resumeB.getAttribute('data-task-id') || '0', 10);
          if (t3) {
            postTimerAction({ action: 'resume', task_id: t3 }, 'header resume').then(function () { syncStateAndUi(); });
          }
          return;
        }
        var comp = e.target.closest('.tasksession-header-timer-header-complete');
        if (comp) {
          e.stopPropagation();
          var t4 = parseInt(comp.getAttribute('data-task-id') || '0', 10);
          var hdrSched = 0;
          if (t4 === (parseInt(String(state.sidebarTaskId || ''), 10) || 0)) {
            hdrSched =
              state.completeModalScheduleId ||
              state.workActionsScheduleRowId ||
              state.activeScheduleRowId ||
              0;
          }
          openTimerCompleteModal(t4, hdrSched > 0 ? { scheduleRowId: hdrSched } : undefined);
          return;
        }
      });
    }

    var schedAdd = el('tasksession-timer-schedule-add');
    if (schedAdd) {
      schedAdd.addEventListener('click', function (e) {
        e.preventDefault();
        if (!cfg().canMutateTimer || cfg().isClient) return;
        var modal = el('tasksessionScheduleMoreModal');
        if (!modal || !window.bootstrap || !window.bootstrap.Modal) return;
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
      });
    }
  }

  function startHeaderPolling() {
    if (!cfg().canMutateTimer) return;
    if (state.headerPoll) clearInterval(state.headerPoll);
    state.headerPoll = setInterval(function () {
      refreshServerState().then(function (d) {
        if (d && d.status === 'ok') {
          state.lastServerState = normalizeTimerState(d);
          markHeaderElapsedBaselineFromApi(state.lastServerState);
          markDashLoggedBaselineFromApi(state.lastServerState);
          updateHeaderWidget(state.lastServerState);
          restartHeaderLiveClock();
          updateStatActive(state.lastServerState);
          updateStaffDashTimerCards(state.lastServerState);
          updateScheduleElapsedOnly(state.lastServerState);
        }
      });
    }, 30000);
  }

  function restartHeaderLiveClock() {
    if (state.tick) {
      clearInterval(state.tick);
      state.tick = null;
    }
    if (!cfg().canMutateTimer) return;
    var d = state.lastServerState;
    if (!d || !d.session || !sessionRunning(d.session)) return;
    state.tick = setInterval(function () {
      var cur = state.lastServerState;
      if (!cur || !cur.session || !sessionRunning(cur.session)) return;
      updateHeaderWidget(cur);
      updateScheduleElapsedOnly(cur);
    }, 1000);
  }

  document.addEventListener('visibilitychange', function () {
    if (!cfg().enabled || document.visibilityState !== 'visible') return;
    syncStateAndUi();
  });

  function onTaskLoaded(taskId, detailJson) {
    if (!cfg().enabled) return;
    var nextId = parseInt(String(taskId || ''), 10) || 0;
    if (nextId !== state.sidebarTaskId) clearScheduleRowFocus();
    state.sidebarTaskId = nextId;
    state.lastTaskDetail = detailJson && detailJson.task ? detailJson.task : null;
    wireBillable();
    showAlert('');
    syncStateAndUi().then(function () {
      var tid = state.sidebarTaskId;
      loadLogs(tid);
      loadSchedules(tid);
    });
  }

  function loadTimerTab(taskId) {
    if (!cfg().enabled) return;
    var nextId = parseInt(String(taskId || ''), 10) || 0;
    if (nextId !== state.sidebarTaskId) clearScheduleRowFocus();
    state.sidebarTaskId = nextId;
    syncStateAndUi().then(function () {
      var tid = state.sidebarTaskId;
      loadLogs(tid);
      loadSchedules(tid);
    });
  }

  function init() {
    if (!cfg().enabled) {
      setHeaderTimerSlotEmpty(true);
      return;
    }
    wireActions();
    applyManualEntryTabVisibility();
    var slot = el('tasksession-header-timer-slot');
    if (slot && slot.classList.contains('tasksession-header-timer-slot--show-skeleton')) {
      showHeaderTimerSkeleton();
    }
    function startTimerChrome() {
      startHeaderPolling();
      startStaffDashCardTicker();
      syncStateAndUi().then(
        function (data) {
          syncHeaderTimerSlotFromState(data || state.lastServerState);
          if (headerHasTimerSessions(data || state.lastServerState)) {
            dismissHeaderTimerSkeleton();
          }
        },
        function () {
          var slotErr = el('tasksession-header-timer-slot');
          if (slotErr && !slotErr.classList.contains('tasksession-header-timer-slot--empty')) {
            dismissHeaderTimerSkeleton();
          }
        }
      );
    }
    if (typeof window.comonAfterPageQuiet === 'function') {
      window.comonAfterPageQuiet(startTimerChrome, 1800);
    } else if (document.readyState === 'complete') {
      setTimeout(startTimerChrome, 1800);
    } else {
      window.addEventListener('load', function () {
        setTimeout(startTimerChrome, 1800);
      });
    }
  }

  window.ComonTaskTimer = {
    init: init,
    onTaskLoaded: onTaskLoaded,
    loadTimerTab: loadTimerTab,
    sync: syncStateAndUi
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
