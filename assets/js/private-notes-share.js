/**
 * Private note sharing modal (expects window.PRIVATE_NOTE_SHARE from the page).
 * Runs on DOMContentLoaded so Bootstrap (loaded in footer) is available.
 */
(function () {
    'use strict';

    var SHARE_NOTE_CHECK_SVG =
        '<svg class="team-group-dd-check-svg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">' +
        '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>' +
        '</svg>';

    function initPrivateNotesShare() {
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }

        var cfg = window.PRIVATE_NOTE_SHARE || {};
        var noteId = parseInt(String(cfg.noteId || '0'), 10) || 0;
        var ajaxBase = String(cfg.ajaxBase || '../ajax/private_notes/').replace(/\/?$/, '/');
        var csrfToken = String(cfg.csrfToken || '');
        var canEdit = !!cfg.canEdit;

        var openShareBtn = document.getElementById('openShareNoteBtn');
        var shareModalEl = document.getElementById('shareNoteModal');
        if (!shareModalEl) return;

        var shareModal = bootstrap.Modal.getInstance(shareModalEl);
        if (!shareModal) {
            shareModal = new bootstrap.Modal(shareModalEl);
        }

        var sharePermission = document.getElementById('sharePermissionSelect');
        var optionsList = document.getElementById('shareNoteUserOptionsList');
        var searchInput = document.getElementById('shareNoteMemberSearchInput');
        var noMembersLi = document.getElementById('shareNoteNoMembersFound');
        var dropdownMenu = document.getElementById('shareNoteUserDropdownMenu');
        var dropdownBtn = document.getElementById('shareNoteUserDropdownBtn');
        var applyBtn = document.getElementById('shareNoteApplyBtn');
        var userDropdown = document.getElementById('shareNoteUserDropdown');

        var pending = new Map();
        var pendingRemove = new Set();
        var sharedIds = new Set();
        var lastUserFetch = [];
        var searchTimer = null;
        var applyBtnDefaultHtml = applyBtn ? applyBtn.innerHTML : '';
        var noteForm = document.getElementById('noteForm');
        var titleField = noteForm ? noteForm.querySelector('textarea[name="title"]') : null;
        var contentField = noteForm ? noteForm.querySelector('textarea[name="note_content"]') : null;
        var noteUpdatedAtField = document.getElementById('noteUpdatedAt');
        var autosaveStatusEl = document.getElementById('noteAutosaveStatus');
        var autosaveTimer = null;
        var autosaveAbortController = null;
        var autosaveReqSeq = 0;
        var autosaveLastSentFingerprint = '';
        var autosaveLastSavedFingerprint = '';
        var autosaveBound = false;
        var currentPublicUrl = '';
        var linkShareNoteId = 0;

        var shareLinkModalEl = document.getElementById('shareNoteLinkModal');
        var shareLinkModal = null;
        if (shareLinkModalEl) {
            shareLinkModal = bootstrap.Modal.getInstance(shareLinkModalEl) || new bootstrap.Modal(shareLinkModalEl);
        }
        var shareLinkGeneralAccessSelect = document.getElementById('shareLinkGeneralAccessSelect');
        var shareLinkRoleSelect = document.getElementById('shareLinkRoleSelect');
        var shareLinkGeneralAccessHint = document.getElementById('shareLinkGeneralAccessHint');
        var shareLinkCopyBtn = document.getElementById('shareLinkCopyBtn');
        var shareLinkDoneBtn = document.getElementById('shareLinkDoneBtn');
        var shareLinkMetaAccess = document.getElementById('shareLinkMetaAccess');
        var shareLinkMetaSharedDate = document.getElementById('shareLinkMetaSharedDate');
        var shareLinkMetaLastUpdate = document.getElementById('shareLinkMetaLastUpdate');

        function formatNoteDateYmd(raw) {
            if (raw == null || raw === '') return '—';
            var s = String(raw);
            if (s.length >= 10) return s.slice(0, 10);
            return s;
        }

        function syncShareLinkMetaAccessFromRole() {
            if (!shareLinkMetaAccess) return;
            var r = shareLinkRoleSelect ? String(shareLinkRoleSelect.value || 'viewer') : 'viewer';
            shareLinkMetaAccess.textContent = r === 'editor' ? 'Edit & view access' : 'View access only';
        }

        function populateLinkMetaFromData(data) {
            data = data || {};
            syncShareLinkMetaAccessFromRole();
            if (shareLinkMetaSharedDate) {
                shareLinkMetaSharedDate.textContent = formatNoteDateYmd(data.public_share_updated_at);
            }
            if (shareLinkMetaLastUpdate) {
                shareLinkMetaLastUpdate.textContent = formatNoteDateYmd(data.updated_at);
            }
        }

        function openPublicLinkModal(forNoteId) {
            var actionNoteId = parseInt(String(forNoteId || '0'), 10) || 0;
            if (!actionNoteId || !shareLinkModal) return;
            linkShareNoteId = actionNoteId;
            loadPublicShareSettings(actionNoteId)
                .then(function () {
                    shareLinkModal.show();
                })
                .catch(function (err) {
                    alert(err && err.message ? err.message : 'Failed to open link settings');
                });
        }

        function esc(s) {
            return String(s == null ? '' : s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/"/g, '&quot;');
        }

        function roleBadgeHtml(accountStatusRaw) {
            var st = String(accountStatusRaw == null ? '' : accountStatusRaw);
            if (st === '1') {
                return '<span class="badge color-done review done-bg-op ms-2 align-self-start" style="font-size:9px;padding:2px 6px;line-height:1.1;">ADMIN</span>';
            }
            if (st === '2') {
                return '<span class="badge color-inprogress inprogress-bg-op ms-2 align-self-start" style="font-size:9px;padding:2px 6px;line-height:1.1;">CLIENT</span>';
            }
            if (st === '3') {
                return '<span class="badge color-review review-bg-op ms-2 align-self-start" style="font-size:9px;padding:2px 6px;line-height:1.1;">STAFF</span>';
            }
            return '';
        }

        function renderUserOptions(users) {
            if (!optionsList) return;
            var html = [];
            var visible = 0;
            var selectedUsers = [];
            var unselectedUsers = [];
            for (var i = 0; i < users.length; i++) {
                var u = users[i];
                if (!u || !u.id) continue;
                var isEffectiveSelected = pending.has(u.id) || (sharedIds.has(u.id) && !pendingRemove.has(u.id));
                if (isEffectiveSelected) {
                    selectedUsers.push(u);
                } else {
                    unselectedUsers.push(u);
                }
            }
            var orderedUsers = selectedUsers.concat(unselectedUsers);
            var selectedCount = selectedUsers.length;
            for (var j = 0; j < orderedUsers.length; j++) {
                var ou = orderedUsers[j];
                if (!ou || !ou.id) continue;
                var st = String((ou.name || '') + ' ' + (ou.email || '')).toLowerCase();
                var av = esc(ou.avatar_url || '');
                var name = esc(ou.name || 'User');
                var em = esc(ou.email || '');
                var roleBadge = roleBadgeHtml(ou.account_status);
                var isShared = sharedIds.has(ou.id);
                var isSelected = pending.has(ou.id) || (isShared && !pendingRemove.has(ou.id));
                html.push(
                    '<li class="project-option-item share-note-user-option-item" data-search-text="' + esc(st) + '">'
                    + '<a href="#" class="dropdown-item d-flex align-items-center share-note-user-option team-group-dd-option'
                    + (isSelected ? ' selected' : '')
                    + '" data-user-id="' + ou.id + '" data-is-shared="' + (isShared ? '1' : '0') + '">'
                    + '<span class="team-group-dd-check" aria-hidden="true">' + (isSelected ? SHARE_NOTE_CHECK_SVG : '') + '</span>'
                    + '<img src="' + av + '" class="rounded-circle me-2" width="32" height="32" alt="">'
                    + '<div class="w-100">'
                    + '<div class="d-flex align-items-center justify-content-between"><span>' + name + '</span>' + roleBadge + '</div>'
                    + '<small style="color:#888">' + em + '</small>'
                    + '</div>'
                    + '</a></li>'
                );
                visible++;
            }
            optionsList.innerHTML = html.join('');
            if (noMembersLi) {
                noMembersLi.style.display = visible === 0 ? 'block' : 'none';
            }
            if (dropdownBtn) {
                var btnText = dropdownBtn.querySelector('#shareNoteUserDropdownBtnText');
                if (btnText) {
                    btnText.textContent = selectedCount > 0
                        ? selectedCount + ' selected'
                        : 'Select team members';
                }
            }
        }

        function updateSharedIds(items) {
            sharedIds = new Set();
            if (!items || items.length === 0) {
                renderUserOptions(lastUserFetch);
                return;
            }
            items.forEach(function (item) {
                sharedIds.add(item.shared_with_user_id);
            });
            renderUserOptions(lastUserFetch);
        }

        function fetchUsers(q) {
            if (!noteId || !optionsList) return Promise.resolve();
            return fetch(ajaxBase + 'share_search.php?q=' + encodeURIComponent(q || ''))
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    var users = (resp && resp.status === 'ok' && resp.data && Array.isArray(resp.data.users))
                        ? resp.data.users
                        : [];
                    lastUserFetch = users;
                    renderUserOptions(users);
                })
                .catch(function () {
                    lastUserFetch = [];
                    renderUserOptions([]);
                });
        }

        function loadShares() {
            if (!noteId) return Promise.resolve();
            return fetch(ajaxBase + 'share_list.php?note_id=' + encodeURIComponent(String(noteId)))
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (!resp || resp.status !== 'ok') return;
                    var shares = (resp.data && resp.data.shares) ? resp.data.shares : [];
                    updateSharedIds(shares);
                    return shares;
                });
        }

        function syncLinkShareUi() {
            var isAnyone = shareLinkGeneralAccessSelect && shareLinkGeneralAccessSelect.value === 'anyone_link';
            if (shareLinkGeneralAccessHint) {
                shareLinkGeneralAccessHint.textContent = isAnyone
                    ? 'Anyone on the internet with the link can open this note.'
                    : 'Only invited users can open this note.';
            }
        }

        function mapLinkRoleToServer(roleVal) {
            var r = String(roleVal || 'viewer');
            if (r === 'editor') { return 'editor'; }
            return 'viewer';
        }

        function loadPublicShareSettings(forNoteId) {
            var nid = parseInt(String(forNoteId || '0'), 10) || 0;
            if (!nid) return Promise.resolve();
            return fetch(ajaxBase + 'public_share_get.php?note_id=' + encodeURIComponent(String(nid)))
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (!resp || resp.status !== 'ok') {
                        throw new Error((resp && resp.error) ? resp.error : 'Unable to load link settings');
                    }
                    var data = resp.data || {};
                    if (shareLinkGeneralAccessSelect) {
                        shareLinkGeneralAccessSelect.value = data.general_access || 'restricted';
                    }
                    if (shareLinkRoleSelect) {
                        var role = data.role || 'viewer';
                        shareLinkRoleSelect.value = (role === 'editor') ? 'editor' : 'viewer';
                    }
                    currentPublicUrl = String(data.public_url || '');
                    syncLinkShareUi();
                    populateLinkMetaFromData(data);
                });
        }

        function updatePublicShareSettings(forNoteId) {
            var nid = parseInt(String(forNoteId || '0'), 10) || 0;
            if (!nid || !shareLinkGeneralAccessSelect || !shareLinkRoleSelect) return Promise.resolve();
            return fetch(ajaxBase + 'public_share_update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    note_id: nid,
                    general_access: shareLinkGeneralAccessSelect.value || 'restricted',
                    role: mapLinkRoleToServer(shareLinkRoleSelect.value || 'viewer'),
                    csrf_token: csrfToken
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (!resp || resp.status !== 'ok') {
                        throw new Error((resp && resp.error) ? resp.error : 'Failed to update public share');
                    }
                    var data = resp.data || {};
                    currentPublicUrl = String(data.public_url || '');
                    syncLinkShareUi();
                });
        }

        function copyText(text) {
            var val = String(text || '');
            if (!val) return Promise.reject(new Error('No link available'));
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(val);
            }
            var temp = document.createElement('input');
            temp.value = val;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            return Promise.resolve();
        }

        function autosaveSetStatus(state, message) {
            if (!autosaveStatusEl) return;
            autosaveStatusEl.setAttribute('data-state', state);
            if (state === 'saving') {
                autosaveStatusEl.textContent = message || 'Saving...';
                autosaveStatusEl.classList.remove('d-none');
            } else {
                autosaveStatusEl.classList.add('d-none');
            }
        }

        function autosaveGetPayload() {
            var titleVal = titleField ? String(titleField.value || '').trim() : '';
            var contentVal = contentField ? String(contentField.value || '').trim() : '';
            return {
                note_id: noteId,
                title: titleVal,
                note_content: contentVal,
                client_updated_at: noteUpdatedAtField ? String(noteUpdatedAtField.value || '') : ''
            };
        }

        function autosaveFingerprint(payload) {
            return String(payload.title || '') + '\n::\n' + String(payload.note_content || '');
        }

        function autosaveSend(force) {
            if (!canEdit || !titleField || !contentField) return;
            var payload = autosaveGetPayload();
            if (!payload.note_content) return;

            var fp = autosaveFingerprint(payload);
            if (!force && (fp === autosaveLastSavedFingerprint || fp === autosaveLastSentFingerprint)) {
                return;
            }

            if (autosaveAbortController) {
                autosaveAbortController.abort();
            }
            autosaveAbortController = new AbortController();
            autosaveReqSeq += 1;
            var reqSeq = autosaveReqSeq;
            autosaveLastSentFingerprint = fp;
            payload.csrf_token = csrfToken;

            autosaveSetStatus('saving', 'Saving...');
            fetch(ajaxBase + 'autosave.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify(payload),
                signal: autosaveAbortController.signal
            })
                .then(function (r) {
                    return r.json().then(function (body) {
                        return { statusCode: r.status, body: body };
                    });
                })
                .then(function (result) {
                    if (reqSeq !== autosaveReqSeq) return;
                    var body = result.body || {};
                    if (result.statusCode === 409 || body.status === 'conflict') {
                        if (noteUpdatedAtField && body.updated_at) {
                            noteUpdatedAtField.value = String(body.updated_at);
                        }
                        autosaveSetStatus('error', 'Not saved');
                        return;
                    }
                    if (!body || body.status !== 'ok') {
                        throw new Error(body && body.error ? body.error : 'Autosave failed');
                    }
                    if (body.note_id) {
                        var newNoteId = parseInt(String(body.note_id), 10) || 0;
                        if (newNoteId > 0) {
                            noteId = newNoteId;
                            cfg.noteId = newNoteId;
                            var noteIdField = noteForm ? noteForm.querySelector('input[name="note_id"]') : null;
                            if (noteIdField) {
                                noteIdField.value = String(newNoteId);
                            }
                        }
                    }
                    if (noteUpdatedAtField && body.updated_at) {
                        noteUpdatedAtField.value = String(body.updated_at);
                    }
                    autosaveLastSavedFingerprint = fp;
                    autosaveSetStatus('saved', 'Saved');
                    if (body.created && noteId > 0) {
                        var currentPath = String((window.location && window.location.pathname) || '');
                        var targetPage = (currentPath.indexOf('/admin/') !== -1 || currentPath.indexOf('/staff/') !== -1 || currentPath.indexOf('/client/documents.php') !== -1) ? 'documents.php' : 'private-notes.php';
                        window.location.replace(targetPage + '?note_id=' + encodeURIComponent(String(noteId)));
                    }
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    autosaveSetStatus('error', 'Retry');
                });
        }

        function autosaveQueue(delayMs) {
            if (!canEdit || autosaveBound !== true) return;
            clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(function () {
                autosaveSend(false);
            }, typeof delayMs === 'number' ? delayMs : 1800);
        }

        function bindAutosaveListeners() {
            if (!autosaveStatusEl) return;
            if (!canEdit || !titleField || !contentField) {
                autosaveStatusEl.classList.add('d-none');
                return;
            }
            if (autosaveBound) return;
            autosaveBound = true;
            autosaveSetStatus('idle', 'Saved');

            var initialPayload = autosaveGetPayload();
            autosaveLastSavedFingerprint = autosaveFingerprint(initialPayload);
            autosaveLastSentFingerprint = autosaveLastSavedFingerprint;

            titleField.addEventListener('input', function () { autosaveQueue(1800); });
            titleField.addEventListener('blur', function () { autosaveSend(true); });
            contentField.addEventListener('input', function () { autosaveQueue(1800); });
            contentField.addEventListener('blur', function () { autosaveSend(true); });

            var editorBindTries = 0;
            var editorBindTimer = setInterval(function () {
                var editorContent = document.querySelector('#privateNoteEditorPane .rich-editor-content');
                if (editorContent) {
                    editorContent.addEventListener('input', function () { autosaveQueue(1800); });
                    editorContent.addEventListener('blur', function () { autosaveSend(true); });
                    clearInterval(editorBindTimer);
                }
                editorBindTries++;
                if (editorBindTries > 80) {
                    clearInterval(editorBindTimer);
                }
            }, 100);

            window.addEventListener('beforeunload', function () {
                var payload = autosaveGetPayload();
                var fp = autosaveFingerprint(payload);
                if (fp === autosaveLastSavedFingerprint || !payload.note_content) return;
                payload.csrf_token = csrfToken;
                if (navigator.sendBeacon) {
                    var blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
                    navigator.sendBeacon(ajaxBase + 'autosave.php', blob);
                } else {
                    fetch(ajaxBase + 'autosave.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify(payload),
                        keepalive: true
                    });
                }
            });
        }

        function refreshSidebarNotesList() {
            var currentList = document.querySelector('.col-lg-3.vh-100.br-right.bg-white.pd-0 .list-group');
            if (!currentList) return Promise.resolve();
            return fetch(window.location.href, { credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var freshList = doc.querySelector('.col-lg-3.vh-100.br-right.bg-white.pd-0 .list-group');
                    if (!freshList || !currentList.parentNode) return;
                    currentList.parentNode.replaceChild(freshList, currentList);
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        var tipTargets = freshList.querySelectorAll('[data-bs-toggle="tooltip"]');
                        tipTargets.forEach(function (el) {
                            bootstrap.Tooltip.getOrCreateInstance(el);
                        });
                    }
                })
                .catch(function () {
                    /* ignore sidebar refresh failures */
                });
        }

        function openUserDropdown() {
            if (!dropdownBtn || typeof bootstrap === 'undefined' || !bootstrap.Dropdown) return;
            window.setTimeout(function () {
                try {
                    bootstrap.Dropdown.getOrCreateInstance(dropdownBtn).show();
                } catch (e) {
                    /* ignore */
                }
            }, 0);
        }

        function onPickUser(userId) {
            var u = null;
            for (var i = 0; i < lastUserFetch.length; i++) {
                if (lastUserFetch[i].id === userId) { u = lastUserFetch[i]; break; }
            }
            if (!u) return;
            if (sharedIds.has(userId)) {
                if (pendingRemove.has(userId)) {
                    pendingRemove.delete(userId);
                } else {
                    pendingRemove.add(userId);
                }
                renderUserOptions(lastUserFetch);
                return;
            }
            if (pending.has(userId)) {
                pending.delete(userId);
            } else {
                pending.set(userId, { name: u.name || 'User', email: u.email || '', avatar_url: u.avatar_url || '' });
            }
            renderUserOptions(lastUserFetch);
        }

        function applyPendingShares() {
            if (!noteId) return;
            if (pending.size === 0 && pendingRemove.size === 0) {
                alert('Select team members from the list first.');
                return;
            }
            if (applyBtn) {
                applyBtn.disabled = true;
                applyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sharing...';
            }
            var permission = sharePermission && sharePermission.value ? sharePermission.value : 'view';
            var ids = Array.from(pending.keys());
            var removeIds = Array.from(pendingRemove.values());
            var chain = Promise.resolve();
            removeIds.forEach(function (uid) {
                chain = chain.then(function () {
                    return fetch(ajaxBase + 'share_remove.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            note_id: noteId,
                            shared_with_user_id: uid,
                            csrf_token: csrfToken
                        })
                    }).then(function (r) { return r.json(); })
                        .then(function (resp) {
                            if (!resp || resp.status !== 'ok') {
                                throw new Error((resp && resp.error) ? resp.error : 'Failed to remove share');
                            }
                        });
                });
            });
            ids.forEach(function (uid) {
                chain = chain.then(function () {
                    return fetch(ajaxBase + 'share_add.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        body: JSON.stringify({
                            note_id: noteId,
                            shared_with_user_id: uid,
                            permission: permission,
                            csrf_token: csrfToken
                        })
                    }).then(function (r) { return r.json(); })
                        .then(function (resp) {
                            if (!resp || resp.status !== 'ok') {
                                throw new Error((resp && resp.error) ? resp.error : 'Failed to share');
                            }
                        });
                });
            });
            chain.then(function () {
                pending.clear();
                pendingRemove.clear();
                return loadShares();
            }).then(function () {
                return fetchUsers(searchInput ? searchInput.value.trim() : '');
            }).then(function () {
                return refreshSidebarNotesList();
            }).then(function () {
                if (shareModal) {
                    shareModal.hide();
                }
            }).catch(function (err) {
                alert(err && err.message ? err.message : 'Failed to share note');
            }).finally(function () {
                if (applyBtn) {
                    applyBtn.disabled = false;
                    applyBtn.innerHTML = applyBtnDefaultHtml;
                }
            });
        }

        if (openShareBtn && shareModal) {
            openShareBtn.addEventListener('click', function () {
                shareModal.show();
            });
        }

        shareModalEl.addEventListener('shown.bs.modal', function () {
            if (searchInput) searchInput.value = '';
            pending.clear();
            pendingRemove.clear();
            lastUserFetch = [];
            loadShares().then(function () { return fetchUsers(''); }).then(function () {
                openUserDropdown();
            });
        });

        if (dropdownMenu && searchInput) {
            var sticky = dropdownMenu.querySelector('.px-3.py-2');
            if (sticky) {
                sticky.addEventListener('click', function (e) { e.stopPropagation(); });
            }
            searchInput.addEventListener('click', function (e) { e.stopPropagation(); });
            searchInput.addEventListener('input', function () {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () {
                    fetchUsers(searchInput.value.trim());
                }, 250);
            });
        }

        if (userDropdown && searchInput) {
            userDropdown.addEventListener('hide.bs.dropdown', function (e) {
                e.preventDefault();
            });
            userDropdown.addEventListener('hidden.bs.dropdown', function () {
                searchInput.value = '';
                fetchUsers('');
            });
        }

        if (optionsList) {
            optionsList.addEventListener('click', function (e) {
                var a = e.target.closest('.share-note-user-option');
                if (!a) return;
                e.preventDefault();
                var uid = parseInt(a.getAttribute('data-user-id') || '0', 10);
                if (uid) onPickUser(uid);
            });
        }

        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                applyPendingShares();
            });
        }

        if (shareLinkGeneralAccessSelect) {
            shareLinkGeneralAccessSelect.addEventListener('change', syncLinkShareUi);
        }

        if (shareLinkRoleSelect) {
            shareLinkRoleSelect.addEventListener('change', syncShareLinkMetaAccessFromRole);
        }

        if (shareLinkCopyBtn) {
            shareLinkCopyBtn.addEventListener('click', function () {
                var selectedAccess = shareLinkGeneralAccessSelect ? String(shareLinkGeneralAccessSelect.value || 'restricted') : 'restricted';
                var afterCopy = function () {
                    return copyText(currentPublicUrl)
                        .then(function () { shareLinkCopyBtn.textContent = 'Link copied'; });
                };

                var chain = Promise.resolve();
                if (selectedAccess === 'anyone_link' && !currentPublicUrl && linkShareNoteId) {
                    chain = updatePublicShareSettings(linkShareNoteId);
                }

                chain
                    .then(function () {
                        if (!currentPublicUrl) {
                            throw new Error('No link available. Set General access to Anyone with the link and click Done once.');
                        }
                        return afterCopy();
                    })
                    .catch(function (err) {
                        alert(err && err.message ? err.message : 'No link available. Set General access to Anyone with the link and save.');
                    })
                    .finally(function () {
                        setTimeout(function () { shareLinkCopyBtn.textContent = 'Copy link'; }, 1200);
                    });
            });
        }

        if (shareLinkDoneBtn) {
            shareLinkDoneBtn.addEventListener('click', function () {
                if (!linkShareNoteId) return;
                shareLinkDoneBtn.disabled = true;
                shareLinkDoneBtn.textContent = 'Saving...';
                updatePublicShareSettings(linkShareNoteId)
                    .then(function () {
                        return loadPublicShareSettings(linkShareNoteId);
                    })
                    .then(function () {
                        shareLinkDoneBtn.textContent = 'Done';
                        if (shareLinkModal) shareLinkModal.hide();
                    })
                    .catch(function (err) {
                        alert(err && err.message ? err.message : 'Failed to update link settings');
                    })
                    .finally(function () {
                        shareLinkDoneBtn.disabled = false;
                        shareLinkDoneBtn.textContent = 'Done';
                    });
            });
        }

        document.addEventListener('click', function (e) {
            var linkTrig = e.target.closest('.note-card-public-link-trigger');
            if (linkTrig) {
                e.preventDefault();
                e.stopPropagation();
                var tid = parseInt(linkTrig.getAttribute('data-note-id') || '0', 10);
                if (tid) openPublicLinkModal(tid);
                return;
            }
            var copyAction = e.target.closest('.copy-note-link-action');
            if (!copyAction) return;
            e.preventDefault();
            var actionNoteId = parseInt(copyAction.getAttribute('data-note-id') || '0', 10);
            if (!actionNoteId) return;
            openPublicLinkModal(actionNoteId);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var linkTrig = e.target.closest('.note-card-public-link-trigger');
            if (!linkTrig) return;
            e.preventDefault();
            e.stopPropagation();
            var tid = parseInt(linkTrig.getAttribute('data-note-id') || '0', 10);
            if (tid) openPublicLinkModal(tid);
        });

        bindAutosaveListeners();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPrivateNotesShare);
    } else {
        initPrivateNotesShare();
    }
})();
