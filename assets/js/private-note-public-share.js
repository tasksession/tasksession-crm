(function () {
    'use strict';

    var cfg = window.PRIVATE_NOTE_PUBLIC || {};
    if (!cfg || !cfg.noteId || !cfg.token) return;

    var textareaEl = document.getElementById('publicDocTextarea');
    var paneEl = document.getElementById('publicDocEditorPane');
    var statusEl = document.getElementById('publicDocSaveStatus');
    if (!textareaEl || !paneEl) return;

    var canEdit = !!cfg.canEdit;
    var timer = null;
    var lastSaved = String(textareaEl.value || '');
    var updatedAt = String(cfg.updatedAt || '');

    function setStatus(msg) {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
    }

    function getCurrentHtml() {
        return String(textareaEl.value || '').trim();
    }

    /** Same layout behavior as admin/private-notes.php: title between toolbar and body, loading mask off. */
    function mountTitleBetweenToolbarAndBody() {
        var titleField = paneEl.querySelector('.note-title-under-editor');
        var editorContainer = paneEl.querySelector('.rich-editor-container');
        var toolbar = editorContainer ? editorContainer.querySelector('.rich-editor-toolbar') : null;
        var content = editorContainer ? editorContainer.querySelector('.rich-editor-content') : null;
        if (!titleField || !editorContainer || !toolbar || !content) return false;

        if (titleField.parentNode !== editorContainer || titleField.previousElementSibling !== toolbar) {
            toolbar.insertAdjacentElement('afterend', titleField);
        }

        var resizeTitle = function () {
            titleField.style.height = 'auto';
            var minH = 60;
            var nextH = Math.max(minH, titleField.scrollHeight);
            titleField.style.height = nextH + 'px';
            titleField.style.overflowY = 'hidden';
        };
        if (!titleField.dataset.autosizeBound) {
            titleField.addEventListener('input', resizeTitle);
            window.addEventListener('resize', resizeTitle);
            titleField.dataset.autosizeBound = '1';
        }
        setTimeout(resizeTitle, 0);
        resizeTitle();
        return true;
    }

    function syncEditorLayoutWithPrivateNotes() {
        var tries = 0;
        var maxTries = 60;
        var poll = setInterval(function () {
            var editorReady = paneEl.querySelector('.rich-editor-container .rich-editor-content');
            if ((editorReady && mountTitleBetweenToolbarAndBody()) || tries >= maxTries) {
                paneEl.classList.remove('notes-editor-loading');
                setTimeout(mountTitleBetweenToolbarAndBody, 120);
                clearInterval(poll);
            }
            tries++;
        }, 50);
    }

    syncEditorLayoutWithPrivateNotes();

    function bindEditorContentListeners() {
        var tries = 0;
        var t = setInterval(function () {
            var editorContent = paneEl.querySelector('.rich-editor-content');
            if (editorContent) {
                editorContent.addEventListener('input', function () {
                    clearTimeout(timer);
                    timer = setTimeout(function () { sendSave(false); }, 1200);
                });
                editorContent.addEventListener('blur', function () { sendSave(true); });
                clearInterval(t);
            }
            tries++;
            if (tries > 80) clearInterval(t);
        }, 100);
    }

    function sendSave(force) {
        if (!canEdit) return;
        var html = getCurrentHtml();
        if (!html) return;
        if (!force && html === lastSaved) return;

        setStatus('Saving...');
        fetch(String(cfg.ajaxUrl || ''), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                public_token: cfg.token,
                note_id: cfg.noteId,
                title: '',
                note_content: html,
                client_updated_at: updatedAt
            })
        })
            .then(function (r) {
                return r.json().then(function (body) {
                    return { code: r.status, body: body };
                });
            })
            .then(function (result) {
                var resp = result.body || {};
                if (result.code >= 400 || !resp || resp.status !== 'ok') {
                    setStatus(resp && resp.error ? String(resp.error) : 'Not saved');
                    return;
                }
                if (resp.updated_at) {
                    updatedAt = String(resp.updated_at);
                }
                lastSaved = html;
                setStatus('Saved');
                setTimeout(function () {
                    if (statusEl && statusEl.textContent === 'Saved') setStatus('');
                }, 1300);
            })
            .catch(function () {
                setStatus('Retry');
            });
    }

    if (!canEdit) {
        setStatus('');
        return;
    }

    textareaEl.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () { sendSave(false); }, 1200);
    });
    textareaEl.addEventListener('blur', function () { sendSave(true); });
    bindEditorContentListeners();
    window.addEventListener('beforeunload', function () { sendSave(true); });
})();
