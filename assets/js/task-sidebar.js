function tasksessionLocalSanitizeHtml(html) {
  if (typeof window.tasksessionSanitizeHtml === 'function') {
    return window.tasksessionSanitizeHtml(html);
  }
  if (html == null) return '';
  html = String(html);
  var wrap = document.createElement('div');
  wrap.innerHTML = html;
  var blocked = { SCRIPT: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, LINK: 1, META: 1, BASE: 1, FORM: 1 };
  var walk = wrap.querySelectorAll('*');
  for (var i = walk.length - 1; i >= 0; i--) {
    var el = walk[i];
    if (blocked[el.tagName]) {
      if (el.parentNode) el.parentNode.removeChild(el);
      continue;
    }
    if (el.attributes) {
      for (var a = el.attributes.length - 1; a >= 0; a--) {
        var name = el.attributes[a].name;
        var val = el.attributes[a].value || '';
        if (/^on/i.test(name) || /javascript:/i.test(val)) {
          el.removeAttribute(name);
        }
      }
    }
  }
  return wrap.innerHTML;
}

function resolveAppBaseUrl() {
  if (typeof window.baseUrl === 'string' && window.baseUrl.length > 1) {
    var u = window.baseUrl.trim();
    return u.charAt(u.length - 1) === '/' ? u : u + '/';
  }
  if (typeof window.siteRootUrl === 'string' && window.siteRootUrl.length) {
    var s = window.siteRootUrl.trim();
    return s.charAt(s.length - 1) === '/' ? s : s + '/';
  }
  return '../';
}

function forEachTaskSidebarPanel(cb) {
  document.querySelectorAll('#task-sidebar').forEach(function (sb) {
    try {
      cb(sb);
    } catch (_) {}
  });
}

function updateActivityTabBadgeCount(n) {
  var badge = document.getElementById('task-activity-tab-count');
  if (!badge) return;
  var num = Math.max(0, parseInt(String(n || 0), 10) || 0);
  badge.textContent = String(num);
  if (num > 0) {
    badge.classList.remove('d-none');
    badge.setAttribute('aria-hidden', 'false');
  } else {
    badge.classList.add('d-none');
    badge.setAttribute('aria-hidden', 'true');
  }
}

/**
 * Show or hide the Scheduled work (#timer) tab when time tracking is on and the task has / lacks an estimate.
 */
function applyTaskSidebarScheduleTabFromEstimate(data) {
  var cfg = window.__tasksessionTimeTracking;
  if (!cfg || !cfg.enabled) {
    return;
  }
  var raw = data && data.task ? data.task.estimated_time_seconds : null;
  var sec = raw == null || raw === '' ? 0 : parseInt(String(raw), 10);
  var hasEstimate = sec > 0;
  document.querySelectorAll('#timer-tab').forEach(function (timerLink) {
    var li = timerLink.closest('li.nav-item');
    if (!li) {
      return;
    }
    if (hasEstimate) {
      li.classList.remove('d-none');
      li.removeAttribute('hidden');
    } else {
      li.classList.add('d-none');
      if (timerLink.classList.contains('active')) {
        var desc = document.getElementById('description-tab');
        if (desc) {
          desc.click();
        }
      }
    }
  });
  var timerPane = document.getElementById('timer');
  if (timerPane) {
    if (hasEstimate) {
      timerPane.classList.remove('d-none');
    } else {
      timerPane.classList.remove('show', 'active');
      timerPane.classList.add('d-none');
    }
  }
}

function loadTaskActivity(taskId) {
  var id = parseInt(String(taskId || ''), 10);
  if (!id) return;
  var list = document.getElementById('task-activity-list');
  var loading = document.getElementById('task-activity-loading');
  if (!list) return;
  if (loading) loading.style.display = 'block';
  var url = resolveAppBaseUrl() + 'includes/task_activity_list.php?task_id=' + encodeURIComponent(String(id));
  var runFetch = window.fetchWithCsrf ? function () { return window.fetchWithCsrf(url); } : function () { return fetch(url); };
  runFetch()
    .then(function (r) { return r.text(); })
    .then(function (text) {
      var data = null;
      try {
        data = JSON.parse(text);
      } catch (err) {
        data = null;
      }
      if (loading) loading.style.display = 'none';
      if (!data) {
        list.innerHTML = '<div class="alert alert-light text-center">Invalid response</div>';
        updateActivityTabBadgeCount(0);
        return;
      }
      if (data.status !== 'ok') {
        var err = data.error || 'Failed to load activity';
        list.innerHTML = '<div class="alert alert-light text-center">' + err.replace(/</g, '&lt;') + '</div>';
        updateActivityTabBadgeCount(0);
        return;
      }
      var html = data.data && data.data.html ? String(data.data.html) : '';
      list.innerHTML = html || '<div class="alert alert-light text-center">No activity found</div>';
      var cnt = 0;
      if (data.data && data.data.count != null) {
        cnt = parseInt(String(data.data.count), 10) || 0;
      } else {
        cnt = list.querySelectorAll('.lead-activity-item').length;
      }
      updateActivityTabBadgeCount(cnt);
      if (typeof window.initRelativeTimes === 'function') {
        try {
          window.initRelativeTimes(list);
        } catch (e) {}
      }
    })
    .catch(function () {
      if (loading) loading.style.display = 'none';
      list.innerHTML = '<div class="alert alert-light text-center">Network error occurred</div>';
      updateActivityTabBadgeCount(0);
    });
}

function ensureMessagesCss() {
  var href = (typeof window.comonMessagesCssHref === 'string' && window.comonMessagesCssHref)
    ? window.comonMessagesCssHref
    : (resolveAppBaseUrl() + 'assets/css/messages.css');
  var existing = document.getElementById('comon-messages-css');
  if (existing) {
    if (existing.getAttribute('href') !== href) {
      existing.href = href;
    }
    return;
  }
  var link = document.createElement('link');
  link.id = 'comon-messages-css';
  link.rel = 'stylesheet';
  link.type = 'text/css';
  link.href = href;
  document.head.appendChild(link);
}

// Open task sidebar and populate content
function openTaskSidebar(taskId) {
  ensureMessagesCss();
  const sidebars = document.querySelectorAll('#task-sidebar');
  const sidebar = sidebars.length ? sidebars[sidebars.length - 1] : null;
  const loading = sidebar ? sidebar.querySelector('#task-loading') : null;
  const content = sidebar ? sidebar.querySelector('#task-content') : null;
  const error = sidebar ? sidebar.querySelector('#task-error') : null;

  function applySidebarLoadUi(mode) {
    sidebars.forEach(function (sb) {
      var ld = sb.querySelector('#task-loading');
      var ct = sb.querySelector('#task-content');
      var er = sb.querySelector('#task-error');
      if (mode === 'loading') {
        if (ld) ld.style.display = 'block';
        if (ct) ct.style.display = 'none';
        if (er) er.style.display = 'none';
      } else if (mode === 'content') {
        if (ld) ld.style.display = 'none';
        if (ct) ct.style.display = 'block';
        if (er) er.style.display = 'none';
      } else if (mode === 'error') {
        if (ld) ld.style.display = 'none';
        if (ct) ct.style.display = 'none';
        if (er) er.style.display = 'block';
      }
    });
  }

  if (!sidebar || !loading || !content || !error) {
    return;
  }

  if (taskId) {
    var tidStr = String(taskId);
    try {
      sidebars.forEach(function (el) {
        el.dataset.taskId = tidStr;
      });
    } catch (e) {
      sidebar.dataset.taskId = tidStr;
    }
  }

  // Prevent the global click-outside handler from immediately closing on the same click
  try { window.taskSidebarPreventCloseUntil = Date.now() + 300; } catch(_) {}
  sidebars.forEach(function (el) {
    el.classList.add('open');
  });
  applySidebarLoadUi('loading');

  // Start chat immediately — do not wait for task details (loads in parallel)
  if (typeof window.loadChatForTask === 'function') {
    window.loadChatForTask(taskId);
  }

  forEachTaskSidebarPanel(function (sb) {
    var badgeElem = sb.querySelector('#task-status-badge');
    var statusSection = sb.querySelector('#task-status-section');
    if (badgeElem) badgeElem.innerHTML = '';
    if (statusSection) statusSection.style.display = 'none';
    var activityList = sb.querySelector('#task-activity-list');
    if (activityList) activityList.innerHTML = '';
  });
  updateActivityTabBadgeCount(0);

  const taskDetailsUrl = resolveAppBaseUrl() + 'includes/task_details.php?id=' + encodeURIComponent(String(taskId));
  const tdInit = { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
  const runTd = window.fetchWithCsrf
    ? function () { return window.fetchWithCsrf(taskDetailsUrl, tdInit); }
    : function () { return fetch(taskDetailsUrl, tdInit); };
  runTd()
    .then(response => {
      return response.text().then(function (text) {
        return { ok: response.ok, status: response.status, text: text };
      });
    })
    .then(result => {
      var text = result.text;
      if (!result.ok) {
        applySidebarLoadUi('error');
        forEachTaskSidebarPanel(function (sbPanel) {
          var errorMsg = sbPanel.querySelector('#error-message');
          if (errorMsg) errorMsg.textContent = 'HTTP ' + result.status + ' loading task details';
        });
        return;
      }
      try {
        const data = JSON.parse(text);

        let projectId = 0;
        if (data.task && data.task.project_id && parseInt(data.task.project_id) > 0) {
          projectId = data.task.project_id;
        } else if (data.project && data.project.id && parseInt(data.project.id) > 0) {
          projectId = data.project.id;
        }
        if (typeof setViewProjectButton === 'function') {
          setViewProjectButton(projectId);
        }

        if (data.status === 'ok' || data.task) {
          try {
            applyTaskSidebarScheduleTabFromEstimate(data);
          } catch (_) {}
          if (window.ComonTaskTimer && typeof window.ComonTaskTimer.onTaskLoaded === 'function') {
            try {
              window.ComonTaskTimer.onTaskLoaded(taskId, data);
            } catch (_) {}
          }
          try {
            if (typeof initializeSubTasks === 'function') {
              initializeSubTasks(taskId);
            }
          } catch (_) {}
          try {
            if (typeof window.taskFilesManager !== 'undefined') {
              window.taskFilesManager.setTaskId(taskId);
            }
          } catch (_) {}
          try {
            if (typeof window.taskImagesManager !== 'undefined') {
              window.taskImagesManager.setTaskId(taskId);
            }
          } catch (_) {}

          const getInitials = (name) => {
            if (!name || name.trim() === '') return 'U';
            return name.trim().charAt(0).toUpperCase();
          };

          const crc32 = (str) => {
            let crc = 0;
            const strLen = str.length;
            for (let i = 0; i < strLen; i++) {
              const char = str.charCodeAt(i);
              crc = ((crc >>> 8) ^ (crc32Table[(crc ^ char) & 0xFF])) >>> 0;
            }
            return crc;
          };
          const crc32Table = (() => {
            const table = [];
            for (let i = 0; i < 256; i++) {
              let crc = i;
              for (let j = 0; j < 8; j++) {
                crc = (crc & 1) ? (crc >>> 1) ^ 0xEDB88320 : crc >>> 1;
              }
              table[i] = crc;
            }
            return table;
          })();
          const getColorIndex = (userId, name) => {
            if (userId) return ((parseInt(userId) % 8) + 1);
            if (name) return ((crc32(name) >>> 0) % 8) + 1;
            return 1;
          };
          const isPlaceholderImage = (imageUrl) => !imageUrl || imageUrl.includes('upload-img.jpg');

          forEachTaskSidebarPanel(function (sbPanel) {
            const titleElem = sbPanel.querySelector('#formatted-task-title');
            const descElem = sbPanel.querySelector('#task-description');
            const startDateElem = sbPanel.querySelector('#task-start-date');
            const dueDateElem = sbPanel.querySelector('#task-due-date');
            if (titleElem) titleElem.textContent = data.task?.title || 'Task Details';
            if (descElem) descElem.innerHTML = data.task?.description ? tasksessionLocalSanitizeHtml(data.task.description) : 'No description provided';
            if (data.task?.start_date && startDateElem) startDateElem.textContent = data.task.start_date;
            else if (startDateElem) startDateElem.textContent = '-';

            if (data.task?.due_date && dueDateElem) {
              dueDateElem.textContent = data.task.due_date;
              const now = new Date();
              const dueDate = new Date(data.task.due_date);
              const isOverdue = data.task.status !== 'done' && dueDate < now;
              if (isOverdue) {
                dueDateElem.classList.add('text-danger', 'fw-bold');
              } else {
                dueDateElem.classList.remove('text-danger', 'fw-bold');
              }
            } else if (dueDateElem) {
              dueDateElem.textContent = '-';
              dueDateElem.classList.remove('text-danger', 'fw-bold');
            }

            const badgeElem2 = sbPanel.querySelector('#task-status-badge');
            const statusSection2 = sbPanel.querySelector('#task-status-section');
            if (data.task && data.task.badge_html) {
              if (badgeElem2) badgeElem2.innerHTML = data.task.badge_html;
              if (statusSection2) statusSection2.style.display = 'block';
            } else if (statusSection2) {
              statusSection2.style.display = 'none';
            }
          });

          if (data.creator) {
            forEachTaskSidebarPanel(function (sbPanel) {
            const creatorElem = sbPanel.querySelector('#task-creator');
            if (creatorElem) {
              creatorElem.innerHTML = '';
              const avatarWrapper = document.createElement('div');
              avatarWrapper.className = 'avatar-wrapper';
              const creatorName = data.creator.name || 'Unknown User';
              const creatorImage = data.creator.image;
              const creatorId = data.creator.id || null;
              const showInitials = !creatorImage || isPlaceholderImage(creatorImage);
              if (showInitials) {
                const initials = document.createElement('div');
                const colorIndex = getColorIndex(creatorId, creatorName);
                const initialsText = getInitials(creatorName);
                initials.className = `avatar-initials color-${colorIndex} avatar-initials-large img-fluid rounded-circle`;
                initials.textContent = initialsText;
                initials.setAttribute('data-bs-toggle', 'tooltip');
                initials.setAttribute('data-bs-placement', 'top');
                initials.title = creatorName;
                avatarWrapper.appendChild(initials);
              } else {
                const img = document.createElement('img');
                img.src = creatorImage;
                img.alt = creatorName;
                img.className = 'avatar';
                img.setAttribute('data-bs-toggle', 'tooltip');
                img.setAttribute('data-bs-placement', 'top');
                img.title = creatorName;
                img.onerror = function() {
                  this.style.display = 'none';
                  const initials = document.createElement('div');
                  const colorIndex = getColorIndex(creatorId, creatorName);
                  initials.className = `avatar-initials color-${colorIndex} avatar-initials-large img-fluid rounded-circle`;
                  initials.textContent = getInitials(creatorName);
                  initials.setAttribute('data-bs-toggle', 'tooltip');
                  initials.setAttribute('data-bs-placement', 'top');
                  initials.title = creatorName;
                  avatarWrapper.insertBefore(initials, this);
                  if (typeof bootstrap !== 'undefined') {
                    new bootstrap.Tooltip(initials, { container: 'body', trigger: 'hover', placement: 'top' });
                  }
                };
                avatarWrapper.appendChild(img);
              }
              const name = document.createElement('span');
              name.className = 'avatar-name';
              name.textContent = creatorName;
              avatarWrapper.appendChild(name);
              creatorElem.appendChild(avatarWrapper);

              if (typeof bootstrap !== 'undefined') {
                const tooltipEl = avatarWrapper.querySelector('[data-bs-toggle="tooltip"]');
                if (tooltipEl) new bootstrap.Tooltip(tooltipEl, { container: 'body', trigger: 'hover', placement: 'top' });
              }
            }
            });
          }

          if (data.assigned_staff && data.assigned_staff.length > 0) {
            forEachTaskSidebarPanel(function (sbPanel) {
            const assignedElem = sbPanel.querySelector('#task-assigned-by');
            if (assignedElem) {
              assignedElem.innerHTML = '';
              const tooltipElements = [];
              data.assigned_staff.forEach((staff, index) => {
                if (index < 4) {
                  const staffName = staff.name || 'Unknown User';
                  const staffImage = staff.image;
                  const showInitials = !staffImage || isPlaceholderImage(staffImage);
                  if (showInitials) {
                    // Wrap initials in avatar-overlap tooltip wrapper
                    const wrap = document.createElement('div');
                    wrap.className = 'avatar-overlap';
                    wrap.setAttribute('data-bs-toggle', 'tooltip');
                    wrap.setAttribute('data-bs-placement', 'top');
                    wrap.title = staffName;

                    const initials = document.createElement('div');
                    const staffId = staff.id || null;
                    const colorIndex = getColorIndex(staffId, staffName);
                    const initialsText = staff.initials && staff.initials.trim() !== '' ? staff.initials.toUpperCase().substring(0, 2) : getInitials(staffName);
                    initials.className = `avatar-initials color-${colorIndex} avatar-initials-small rounded-circle`;
                    initials.textContent = initialsText;
                    wrap.appendChild(initials);
                    assignedElem.appendChild(wrap);
                    tooltipElements.push(wrap);
                  } else {
                    // Wrap image in avatar-overlap tooltip wrapper
                    const wrap = document.createElement('div');
                    wrap.className = 'avatar-overlap';
                    wrap.setAttribute('data-bs-toggle', 'tooltip');
                    wrap.setAttribute('data-bs-placement', 'top');
                    wrap.title = staffName;

                    const img = document.createElement('img');
                    img.src = staffImage;
                    img.alt = staffName;
                    img.width = 30;
                    img.height = 30;
                    img.className = 'img-fluid rounded-circle';
                    const staffId = staff.id || null;
                    img.onerror = function() {
                      this.style.display = 'none';
                      const initials = document.createElement('div');
                      const colorIndex = getColorIndex(staffId, staffName);
                      const initialsText = getInitials(staffName);
                      initials.className = `avatar-initials color-${colorIndex} avatar-initials-small rounded-circle`;
                      initials.textContent = initialsText;
                      wrap.innerHTML = '';
                      wrap.appendChild(initials);
                      if (typeof bootstrap !== 'undefined') {
                        new bootstrap.Tooltip(wrap, { container: 'body', trigger: 'hover', placement: 'top' });
                      }
                    };
                    wrap.appendChild(img);
                    assignedElem.appendChild(wrap);
                    tooltipElements.push(wrap);
                  }
                }
              });
              if (typeof bootstrap !== 'undefined' && tooltipElements.length > 0) {
                tooltipElements.forEach(el => new bootstrap.Tooltip(el, { container: 'body', trigger: 'hover', placement: 'top' }));
              }
            }
            });
          }

          applySidebarLoadUi('content');
          loadTaskActivity(taskId);
          // Chat already started in parallel when sidebar opened
        } else {
          applySidebarLoadUi('error');
          forEachTaskSidebarPanel(function (sbPanel) {
            const errorMsg = sbPanel.querySelector('#error-message');
            if (errorMsg) errorMsg.textContent = data.error || 'Error loading task details';
          });
        }
      } catch (e) {
        applySidebarLoadUi('error');
        forEachTaskSidebarPanel(function (sbPanel) {
          const errorMsg = sbPanel.querySelector('#error-message');
          if (errorMsg) errorMsg.textContent = 'Error parsing server response';
        });
      }
    })
    .catch(function () {
      applySidebarLoadUi('error');
      forEachTaskSidebarPanel(function (sbPanel) {
        const errorMsg = sbPanel.querySelector('#error-message');
        if (errorMsg) errorMsg.textContent = 'Network error occurred';
      });
    });
}

// Close button handler
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('#close-sidebar').forEach(function (closeButton) {
    closeButton.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      document.querySelectorAll('#task-sidebar').forEach(function (sb) {
        sb.classList.remove('open');
      });
    });
  });

  function bindActivitiesTabLoader() {
    var sidebar = document.getElementById('task-sidebar');
    var tid = sidebar && sidebar.dataset.taskId ? parseInt(sidebar.dataset.taskId, 10) : 0;
    if (tid) loadTaskActivity(tid);
  }

  // general.js initTabSystem() switches these tabs via click handlers; it does not use Bootstrap's Tab
  // API, so shown.bs.tab never fires. Reload activity when the Activity nav link is clicked (e.g. refresh
  // after other actions); initial load runs after task details in openTaskSidebar.
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('#activities-tab') : null;
    if (!a) return;
    var sidebar = document.getElementById('task-sidebar');
    if (!sidebar || !sidebar.classList.contains('open')) return;
    var tid = sidebar.dataset.taskId ? parseInt(sidebar.dataset.taskId, 10) : 0;
    if (tid) bindActivitiesTabLoader();
  });

  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target.closest('#timer-tab') : null;
    if (!t) return;
    var sidebar = document.getElementById('task-sidebar');
    if (!sidebar || !sidebar.classList.contains('open')) return;
    var tid = sidebar.dataset.taskId ? parseInt(sidebar.dataset.taskId, 10) : 0;
    if (tid && window.ComonTaskTimer && typeof window.ComonTaskTimer.loadTimerTab === 'function') {
      window.ComonTaskTimer.loadTimerTab(tid);
    }
  });

  if (typeof jQuery !== 'undefined') {
    jQuery(document).on('shown.bs.tab', '#taskTabs a[data-toggle="tab"]', function (e) {
      var a = e.target && e.target.closest ? e.target.closest('a[data-toggle="tab"]') : null;
      var hrefAttr = a ? a.getAttribute('href') : null;
      if (hrefAttr !== '#activities' && !(a && a.hash === '#activities')) return;
      bindActivitiesTabLoader();
    });
  } else {
    document.querySelectorAll('#taskTabs a[data-toggle="tab"]').forEach(function (anchor) {
      anchor.addEventListener('shown.bs.tab', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a[data-toggle="tab"]') : null;
        var hrefAttr = a ? a.getAttribute('href') : null;
        if (hrefAttr !== '#activities' && !(a && a.hash === '#activities')) return;
        bindActivitiesTabLoader();
      });
    });
  }
});

// Click-outside close handler
document.addEventListener('click', function(event) {
  const sidebar = document.getElementById('task-sidebar');
  // Skip closing if we just opened via a dropdown click
  if (window.taskSidebarPreventCloseUntil && Date.now() < window.taskSidebarPreventCloseUntil) {
    return;
  }
  // Do not close when clicking inside dropdowns, action menus, view-task triggers, or mention dropdown
  const isDropdownClick = !!event.target.closest('.dropdown-menu, .dropdown-item, .action-toggle');
  const isViewTaskTrigger = !!event.target.closest('[onclick*="openTaskSidebar"], .view-task-btn');
  const isMentionDropdown = !!event.target.closest('#task-chat-mention-dropdown, .task-chat-mention-dropdown');
  // Do not close when interacting with task chat area (editor, dropdown inside chat, reply preview, etc.)
  const isTaskChatArea = !!event.target.closest('#task-chat-wrapper, #task-chat-messages-box, .edit-message-form, .message-dropdown-wrapper, #reply-preview');
  // Reaction emoji bar + mobile message menu + emoji picker are portaled to <body>
  const isChatReactionBar = !!event.target.closest('#chat-reaction-bar, .chat-reaction-bar');
  const isPortaledMsgMenu = !!event.target.closest('.message-dropdown-menu.is-portaled, .message-dropdown-menu.show');
  const isEmoticonPopover = !!event.target.closest('#emoticon-popover, #smilyies');
  // Clicks in Bootstrap modals (e.g. Email notifications) are outside #task-sidebar but must not close the sidebar
  const isInModal = !!event.target.closest('.modal');
  const isModalBackdrop = event.target && event.target.classList && event.target.classList.contains('modal-backdrop');
  if (isInModal || isModalBackdrop) {
    return;
  }
  if (sidebar && sidebar.classList.contains('open') && !sidebar.contains(event.target) && !isDropdownClick && !isViewTaskTrigger && !isMentionDropdown && !isTaskChatArea && !isChatReactionBar && !isPortaledMsgMenu && !isEmoticonPopover) {
    document.querySelectorAll('#task-sidebar').forEach(function (el) {
      el.classList.remove('open');
    });
  }
});


