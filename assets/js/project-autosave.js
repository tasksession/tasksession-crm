/**
 * Project Notes autosave.
 * Expects window.PROJECT_AUTOSAVE = { enabled, projectId, noteId, csrfToken, ajaxBase }.
 */
(function () {
	'use strict';

	function init() {
		var cfg = window.PROJECT_AUTOSAVE || {};
		if (!cfg.enabled) return;
		var noteForm = document.getElementById('noteForm');
		if (!noteForm) return;

		var projectId = parseInt(String(cfg.projectId || '0'), 10) || 0;
		var csrfToken = String(cfg.csrfToken || '');
		var ajaxBase = String(cfg.ajaxBase || '../ajax/project_notes/').replace(/\/?$/, '/');
		var contentField = noteForm.querySelector('textarea[name="note_content"]');
		var noteIdInput = noteForm.querySelector('input[name="note_id"]');
		var noteUpdatedAtField = document.getElementById('noteUpdatedAt');
		var autosaveStatusEl = document.getElementById('noteAutosaveStatus');
		if (!contentField || projectId <= 0) return;

		var autosaveTimer = null;
		var autosaveAbortController = null;
		var autosaveReqSeq = 0;
		var autosaveLastSentFingerprint = '';
		var autosaveLastSavedFingerprint = '';

		function getNoteId() {
			var n = noteIdInput ? parseInt(String(noteIdInput.value || '0'), 10) : 0;
			return n > 0 ? n : 0;
		}
		function autosaveGetPayload() {
			return {
				note_id: getNoteId(),
				project_id: projectId,
				note_content: String(contentField.value || '').trim(),
				client_updated_at: noteUpdatedAtField ? String(noteUpdatedAtField.value || '') : '',
			};
		}
		function autosaveFingerprint(payload) {
			return String(payload.note_content || '');
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

		function autosaveSend(force) {
			var payload = autosaveGetPayload();
			if (!payload.note_content) return;
			var fp = autosaveFingerprint(payload);
			if (!force && (fp === autosaveLastSavedFingerprint || fp === autosaveLastSentFingerprint)) return;
			if (autosaveAbortController) autosaveAbortController.abort();
			autosaveAbortController = new AbortController();
			autosaveLastSentFingerprint = fp;
			var reqSeq = ++autosaveReqSeq;
			autosaveSetStatus('saving', 'Saving...');

			fetch(ajaxBase + 'autosave.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
				body: JSON.stringify(payload),
				signal: autosaveAbortController.signal,
			})
				.then(function (r) { return r.json(); })
				.then(function (resp) {
					if (reqSeq !== autosaveReqSeq) return;
					if (!resp || resp.status !== 'ok') throw new Error((resp && resp.error) ? resp.error : 'Autosave failed');
					if (noteIdInput && resp.note_id) noteIdInput.value = String(resp.note_id);
					if (noteUpdatedAtField && resp.updated_at) noteUpdatedAtField.value = String(resp.updated_at);
					autosaveLastSavedFingerprint = fp;
					autosaveSetStatus('saved', '');
				})
				.catch(function () {
					autosaveSetStatus('error', '');
				});
		}

		function scheduleAutosave() {
			if (autosaveTimer) clearTimeout(autosaveTimer);
			autosaveTimer = setTimeout(function () {
				autosaveSend(false);
			}, 900);
		}

		contentField.addEventListener('input', scheduleAutosave);
		window.addEventListener('beforeunload', function () {
			autosaveSend(true);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
