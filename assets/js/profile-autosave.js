/**
 * Standalone profile Notes autosave (not shared with admin/documents or private-notes-share.js).
 * Server: ajax/profile_notes/autosave.php
 * Page must set window.PROFILE_AUTOSAVE (see admin/staff profile.php when tab=notes).
 */
(function () {
    'use strict';

    function init() {
        var cfg = window.PROFILE_AUTOSAVE || {};
        if (!cfg.enabled) {
            return;
        }

        var noteForm = document.getElementById('noteForm');
        if (!noteForm) {
            return;
        }

        var profileUserId = parseInt(String(cfg.profileUserId || '0'), 10) || 0;
        var noteId = parseInt(String(cfg.noteId || '0'), 10) || 0;
        var csrfToken = String(cfg.csrfToken || '');
        var ajaxBase = String(cfg.ajaxBase || '../ajax/profile_notes/').replace(/\/?$/, '/');

        var contentField = noteForm.querySelector('textarea[name="note_content"]');
        var noteIdInput = noteForm.querySelector('input[name="note_id"]');
        var noteUpdatedAtField = document.getElementById('noteUpdatedAt');
        var autosaveStatusEl = document.getElementById('noteAutosaveStatus');
        if (!contentField) {
            return;
        }

        var autosaveTimer = null;
        var autosaveAbortController = null;
        var autosaveReqSeq = 0;
        var autosaveLastSentFingerprint = '';
        var autosaveLastSavedFingerprint = '';
        var autosaveBound = false;

        function getNoteId() {
            var n = noteIdInput ? parseInt(String(noteIdInput.value || '0'), 10) : 0;
            return n > 0 ? n : 0;
        }

        function autosaveSetStatus(state, message) {
            if (!autosaveStatusEl) {
                return;
            }
            autosaveStatusEl.setAttribute('data-state', state);
            if (state === 'saving') {
                autosaveStatusEl.textContent = message || 'Saving...';
                autosaveStatusEl.classList.remove('d-none');
            } else {
                autosaveStatusEl.classList.add('d-none');
            }
        }

        function autosaveGetPayload() {
            return {
                note_id: getNoteId(),
                profile_user_id: profileUserId,
                note_content: String(contentField.value || '').trim(),
                client_updated_at: noteUpdatedAtField ? String(noteUpdatedAtField.value || '') : '',
            };
        }

        function autosaveFingerprint(payload) {
            return String(payload.note_content || '');
        }

        function autosaveSend(force) {
            var payload = autosaveGetPayload();
            if (!payload.note_content) {
                return;
            }
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
            if (!payload.profile_user_id) {
                return;
            }

            autosaveSetStatus('saving', 'Saving...');
            fetch(ajaxBase + 'autosave.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify(payload),
                signal: autosaveAbortController.signal,
            })
                .then(function (r) {
                    return r.json().then(function (body) {
                        return { statusCode: r.status, body: body };
                    });
                })
                .then(function (result) {
                    if (reqSeq !== autosaveReqSeq) {
                        return;
                    }
                    var body = result.body || {};
                    if (result.statusCode === 409 || body.status === 'conflict') {
                        if (noteUpdatedAtField && body.updated_at) {
                            noteUpdatedAtField.value = String(body.updated_at);
                        }
                        autosaveSetStatus('error', 'Not saved');
                        return;
                    }
                    if (!body || body.status !== 'ok') {
                        throw new Error((body && body.error) ? body.error : 'Autosave failed');
                    }
                    var newNoteId = parseInt(String(body.note_id || '0'), 10) || 0;
                    if (newNoteId > 0) {
                        noteId = newNoteId;
                        if (noteIdInput) {
                            noteIdInput.value = String(newNoteId);
                        }
                    }
                    if (noteUpdatedAtField && body.updated_at) {
                        noteUpdatedAtField.value = String(body.updated_at);
                    }
                    autosaveLastSavedFingerprint = fp;
                    autosaveSetStatus('saved', 'Saved');
                    if (body.created && newNoteId > 0) {
                        var uid = String(profileUserId);
                        var base = String(cfg.profileUrlBase || 'profile.php');
                        window.location.replace(base + '?user_id=' + encodeURIComponent(uid) + '&tab=notes&note_id=' + encodeURIComponent(String(newNoteId)));
                    }
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    autosaveSetStatus('error', 'Retry');
                });
        }

        function autosaveQueue(delayMs) {
            if (autosaveBound !== true) {
                return;
            }
            clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(function () {
                autosaveSend(false);
            }, typeof delayMs === 'number' ? delayMs : 1800);
        }

        function bindAutosaveListeners() {
            if (!autosaveStatusEl) {
                return;
            }
            if (autosaveBound) {
                return;
            }
            autosaveBound = true;
            autosaveSetStatus('idle', 'Saved');
            var initialPayload = autosaveGetPayload();
            if (!initialPayload.note_content) {
                autosaveStatusEl.classList.add('d-none');
            } else {
                autosaveLastSavedFingerprint = autosaveFingerprint(initialPayload);
                autosaveLastSentFingerprint = autosaveLastSavedFingerprint;
            }
            contentField.addEventListener('input', function () {
                autosaveQueue(1800);
            });
            contentField.addEventListener('blur', function () {
                autosaveSend(true);
            });
            var editorBindTries = 0;
            var editorBindTimer = setInterval(function () {
                var editorContent = document.querySelector('#user-notes-editor .rich-editor-content');
                if (editorContent) {
                    editorContent.addEventListener('input', function () {
                        autosaveQueue(1800);
                    });
                    editorContent.addEventListener('blur', function () {
                        autosaveSend(true);
                    });
                    clearInterval(editorBindTimer);
                }
                editorBindTries += 1;
                if (editorBindTries > 80) {
                    clearInterval(editorBindTimer);
                }
            }, 100);

            window.addEventListener('beforeunload', function () {
                var p = autosaveGetPayload();
                var fp2 = autosaveFingerprint(p);
                if (fp2 === autosaveLastSavedFingerprint || !p.note_content) {
                    return;
                }
                p.csrf_token = csrfToken;
                if (navigator.sendBeacon) {
                    var blob = new Blob([JSON.stringify(p)], { type: 'application/json' });
                    navigator.sendBeacon(ajaxBase + 'autosave.php', blob);
                } else {
                    fetch(ajaxBase + 'autosave.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken,
                        },
                        body: JSON.stringify(p),
                        keepalive: true,
                    });
                }
            });
        }

        bindAutosaveListeners();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
