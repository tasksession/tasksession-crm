/**
 * Share project notes. Reuses #shareNoteModal.
 * Expects window.PROJECT_NOTE_SHARE = { projectId, projectNoteId, ajaxBase, csrfToken, subjectUserId }.
 */
(function () {
	'use strict';
	var SHARE_NOTE_CHECK_SVG =
		'<svg class="team-group-dd-check-svg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>';

	function init() {
		if (typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
		var cfg = window.PROJECT_NOTE_SHARE || {};
		var projectId = parseInt(String(cfg.projectId || '0'), 10) || 0;
		var subjectUserId = parseInt(String(cfg.subjectUserId || '0'), 10) || 0;
		var ajaxBase = String(cfg.ajaxBase || '../ajax/project_notes/').replace(/\/?$/, '/');
		var csrfToken = String(cfg.csrfToken || '');
		var shareModalEl = document.getElementById('shareNoteModal');
		if (!shareModalEl || projectId <= 0) return;

		function readResponseJson(response) { return response.text().then(function (text) { return JSON.parse((text || '').trim() || '{}'); }); }
		function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); }
		function roleBadgeHtml(st) {
			st = String(st == null ? '' : st);
			if (st === '1') return '<span class="badge color-done review done-bg-op ms-2 align-self-start" style="font-size:9px;padding:2px 6px;line-height:1.1;">ADMIN</span>';
			if (st === '2') return '<span class="badge color-inprogress inprogress-bg-op ms-2 align-self-start" style="font-size:9px;padding:2px 6px;line-height:1.1;">CLIENT</span>';
			if (st === '3') return '<span class="badge color-review review-bg-op ms-2 align-self-start" style="font-size:9px;padding:2px 6px;line-height:1.1;">STAFF</span>';
			return '';
		}

		var shareModal = bootstrap.Modal.getInstance(shareModalEl) || new bootstrap.Modal(shareModalEl);
		var sharePermission = document.getElementById('sharePermissionSelect');
		var optionsList = document.getElementById('shareNoteUserOptionsList');
		var searchInput = document.getElementById('shareNoteMemberSearchInput');
		var noMembersLi = document.getElementById('shareNoteNoMembersFound');
		var dropdownBtn = document.getElementById('shareNoteUserDropdownBtn');
		var applyBtn = document.getElementById('shareNoteApplyBtn');
		var pending = new Map();
		var pendingRemove = new Set();
		var sharedIds = new Set();
		var lastUserFetch = [];
		var searchTimer = null;
		var applyBtnDefaultHtml = applyBtn ? applyBtn.innerHTML : '';
		var activeProjectNoteId = 0;
		function normalizeId(v) {
			return parseInt(String(v || '0'), 10) || 0;
		}
		function isUserSelected(userId) {
			var id = normalizeId(userId);
			if (id <= 0) return false;
			return pending.has(id) || (sharedIds.has(id) && !pendingRemove.has(id));
		}
		function ensureOk(resp, fallbackMessage) {
			if (resp && resp.status === 'ok') return resp;
			throw new Error((resp && resp.error) ? String(resp.error) : fallbackMessage);
		}

		function renderUserOptions(users) {
			if (!optionsList) return;
			var allRows = [];
			for (var ai = 0; ai < users.length; ai++) {
				if (users[ai] && normalizeId(users[ai].id) > 0) allRows.push(users[ai]);
			}
			var profileUser = null;
			var toSort = allRows;
			if (subjectUserId > 0) {
				var next = [];
				for (var i = 0; i < allRows.length; i++) {
					var x = allRows[i];
					if (normalizeId(x.id) === subjectUserId) {
						if (!profileUser) profileUser = x;
						continue;
					}
					next.push(x);
				}
				if (profileUser) toSort = next;
			}
			var selected = [], unselected = [], selectedCount = 0;
			for (var j = 0; j < toSort.length; j++) {
				var u = toSort[j];
				var isSel = isUserSelected(u.id);
				if (isSel) { selected.push(u); selectedCount++; } else unselected.push(u);
			}
			if (profileUser && isUserSelected(profileUser.id)) selectedCount++;
			var ordered = selected.concat(unselected);
			function rowHtml(ou) {
				var st = String((ou.name || '') + ' ' + (ou.email || '')).toLowerCase();
				var id = normalizeId(ou.id);
				var isShared = sharedIds.has(id);
				var isSelected = isUserSelected(id);
				return '<li class="project-option-item share-note-user-option-item" data-search-text="' + esc(st) + '">' +
					'<a href="#" class="dropdown-item d-flex align-items-center share-note-user-option team-group-dd-option' + (isSelected ? ' selected' : '') + '" data-user-id="' + id + '">' +
					'<span class="team-group-dd-check" aria-hidden="true">' + (isSelected ? SHARE_NOTE_CHECK_SVG : '') + '</span>' +
					'<img src="' + esc(ou.avatar_url || '') + '" class="rounded-circle me-2" width="32" height="32" alt="">' +
					'<div class="w-100"><div class="d-flex align-items-center justify-content-between"><span>' + esc(ou.name || 'User') + '</span>' + roleBadgeHtml(ou.account_status) + '</div><small style="color:#888">' + esc(ou.email || '') + '</small></div></a></li>';
			}
			var html = [], visible = 0;
			if (profileUser) {
				html.push(rowHtml(profileUser)); visible++;
				if (ordered.length > 0) html.push('<li class="share-note-user-list-sep" role="separator" aria-hidden="true"><div class="dropdown-divider m-0"></div></li>');
			}
			for (var k = 0; k < ordered.length; k++) { html.push(rowHtml(ordered[k])); visible++; }
			optionsList.innerHTML = html.join('');
			if (noMembersLi) noMembersLi.style.display = visible === 0 ? 'block' : 'none';
			if (dropdownBtn) {
				var btnText = dropdownBtn.querySelector('#shareNoteUserDropdownBtnText');
				if (btnText) btnText.textContent = selectedCount > 0 ? selectedCount + ' selected' : 'Select team members';
			}
		}
		function updateSharedIds(items) {
			sharedIds = new Set();
			(items || []).forEach(function (it) {
				var id = normalizeId(it && it.shared_with_user_id);
				if (id > 0) sharedIds.add(id);
			});
			renderUserOptions(lastUserFetch);
		}
		function fetchUsers(q) {
			if (!activeProjectNoteId || !optionsList) return Promise.resolve();
			var u = ajaxBase + 'share_search.php?q=' + encodeURIComponent(q || '') + '&project_id=' + encodeURIComponent(String(projectId)) + '&subject_user_id=' + encodeURIComponent(String(subjectUserId));
			return fetch(u).then(readResponseJson).then(function (resp) {
				lastUserFetch = resp && resp.status === 'ok' && resp.data && Array.isArray(resp.data.users) ? resp.data.users : [];
				renderUserOptions(lastUserFetch);
			}).catch(function () { lastUserFetch = []; renderUserOptions([]); });
		}
		function loadShares() {
			if (!activeProjectNoteId) return Promise.resolve();
			var url = ajaxBase + 'share_list.php?project_note_id=' + encodeURIComponent(String(activeProjectNoteId)) + '&project_id=' + encodeURIComponent(String(projectId));
			return fetch(url).then(readResponseJson).then(function (resp) {
				var shares = resp && resp.status === 'ok' && resp.data ? (resp.data.shares || []) : [];
				updateSharedIds(shares);
			});
		}
		function onPickUser(uid) {
			uid = normalizeId(uid);
			if (uid <= 0) return;
			var u = null;
			for (var i = 0; i < lastUserFetch.length; i++) {
				if (normalizeId(lastUserFetch[i].id) === uid) {
					u = lastUserFetch[i];
					break;
				}
			}
			if (!u) return;
			if (sharedIds.has(uid)) { pendingRemove.has(uid) ? pendingRemove.delete(uid) : pendingRemove.add(uid); renderUserOptions(lastUserFetch); return; }
			pending.has(uid) ? pending.delete(uid) : pending.set(uid, { name: u.name || 'User', email: u.email || '', avatar_url: u.avatar_url || '' });
			renderUserOptions(lastUserFetch);
		}
		function applyPendingShares() {
			if (!activeProjectNoteId) return;
			if (applyBtn) { applyBtn.disabled = true; applyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sharing...'; }
			var permission = sharePermission && sharePermission.value ? sharePermission.value : 'view';
			var removeIds = Array.from(pendingRemove.values());
			var upsertSet = new Set();
			sharedIds.forEach(function (id) {
				if (!pendingRemove.has(id)) upsertSet.add(id);
			});
			pending.forEach(function (_u, id) {
				upsertSet.add(id);
			});
			var ids = Array.from(upsertSet.values());
			if (ids.length === 0 && removeIds.length === 0) {
				if (applyBtn) { applyBtn.disabled = false; applyBtn.innerHTML = applyBtnDefaultHtml; }
				alert('Select team members from the list first.');
				return;
			}
			var chain = Promise.resolve();
			removeIds.forEach(function (uid) {
				chain = chain.then(function () {
					return fetch(ajaxBase + 'share_remove.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ project_note_id: activeProjectNoteId, project_id: projectId, shared_with_user_id: uid, csrf_token: csrfToken }) })
						.then(readResponseJson)
						.then(function (resp) { return ensureOk(resp, 'Failed to remove shared user'); });
				});
			});
			ids.forEach(function (uid) {
				chain = chain.then(function () {
					return fetch(ajaxBase + 'share_add.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ project_note_id: activeProjectNoteId, project_id: projectId, shared_with_user_id: uid, permission: permission, csrf_token: csrfToken }) })
						.then(readResponseJson)
						.then(function (resp) { return ensureOk(resp, 'Failed to add shared user'); });
				});
			});
			chain.then(function () { pending.clear(); pendingRemove.clear(); return loadShares(); })
				.then(function () { return fetchUsers(searchInput ? searchInput.value.trim() : ''); })
				.then(function () { if (shareModal) shareModal.hide(); window.location.reload(); })
				.catch(function (err) { alert(err && err.message ? err.message : 'Failed to share note'); })
				.finally(function () { if (applyBtn) { applyBtn.disabled = false; applyBtn.innerHTML = applyBtnDefaultHtml; } });
		}

		document.addEventListener('click', function (e) {
			var trig = e.target.closest('[data-project-note-share]');
			if (!trig) return;
			e.preventDefault(); e.stopPropagation();
			var nid = parseInt(trig.getAttribute('data-project-note-share') || '0', 10) || 0;
			if (!nid) return;
			activeProjectNoteId = nid;
			if (window.PROJECT_NOTE_SHARE) window.PROJECT_NOTE_SHARE.projectNoteId = nid;
			shareModal.show();
		});
		shareModalEl.addEventListener('shown.bs.modal', function () {
			if (searchInput) searchInput.value = '';
			pending.clear(); pendingRemove.clear(); lastUserFetch = [];
			var startId = parseInt(String((window.PROJECT_NOTE_SHARE && window.PROJECT_NOTE_SHARE.projectNoteId) || '0'), 10) || 0;
			if (startId) activeProjectNoteId = startId;
			loadShares().then(function () { return fetchUsers(''); });
		});
		if (searchInput) {
			searchInput.addEventListener('input', function () {
				clearTimeout(searchTimer);
				searchTimer = setTimeout(function () { fetchUsers(searchInput.value.trim()); }, 250);
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
		if (applyBtn) applyBtn.addEventListener('click', applyPendingShares);
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
