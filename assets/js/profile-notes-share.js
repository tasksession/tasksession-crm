/**
 * Share profile-tab notes (admin/staff on client profile). Reuses #shareNoteModal from share_note_modal.php.
 * Expects window.PROFILE_NOTE_SHARE = { subjectUserId, profileNoteId, ajaxBase, csrfToken }.
 */
(function () {
	'use strict';

	var SHARE_NOTE_CHECK_SVG =
		'<svg class="team-group-dd-check-svg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">' +
		'<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>' +
		'</svg>';

	function initProfileNotesShare() {
		if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
			return;
		}

		function readResponseJson(response) {
			return response.text().then(function (text) {
				var t = (text || '').trim();
				if (t.charAt(0) === '<' || t.indexOf('<br') !== -1 || t.indexOf('<b>') !== -1) {
					throw new Error('Server returned HTML (often a PHP error). Open DevTools → Network → check the response body.');
				}
				try {
					return JSON.parse(t);
				} catch (e) {
					throw new Error('Invalid JSON from server: ' + t.slice(0, 120));
				}
			});
		}

		var cfg = window.PROFILE_NOTE_SHARE || {};
		var subjectUserId = parseInt(String(cfg.subjectUserId || '0'), 10) || 0;
		var ajaxBase = String(cfg.ajaxBase || '../ajax/profile_notes/').replace(/\/?$/, '/');
		var csrfToken = String(cfg.csrfToken || '');

		var shareModalEl = document.getElementById('shareNoteModal');
		if (!shareModalEl || subjectUserId <= 0) {
			return;
		}

		var shareModal = bootstrap.Modal.getInstance(shareModalEl) || new bootstrap.Modal(shareModalEl);
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
		var activeProfileNoteId = 0;

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
			var allRows = [];
			for (var ai = 0; ai < users.length; ai++) {
				if (users[ai] && users[ai].id) allRows.push(users[ai]);
			}
			var totalSelected = 0;
			for (var tx = 0; tx < allRows.length; tx++) {
				var tux = allRows[tx];
				if (pending.has(tux.id) || (sharedIds.has(tux.id) && !pendingRemove.has(tux.id))) {
					totalSelected++;
				}
			}

			var profileUser = null;
			var toSort = allRows;
			if (subjectUserId > 0) {
				var next = [];
				for (var pi = 0; pi < allRows.length; pi++) {
					var px = allRows[pi];
					if (Number(px.id) === subjectUserId) {
						if (!profileUser) {
							profileUser = px;
						}
						continue;
					}
					next.push(px);
				}
				if (profileUser) {
					toSort = next;
				} else {
					profileUser = null;
					toSort = allRows;
				}
			}

			var selectedUsers = [];
			var unselectedUsers = [];
			for (var i = 0; i < toSort.length; i++) {
				var u = toSort[i];
				if (!u || !u.id) continue;
				var isEffectiveSelected = pending.has(u.id) || (sharedIds.has(u.id) && !pendingRemove.has(u.id));
				if (isEffectiveSelected) {
					selectedUsers.push(u);
				} else {
					unselectedUsers.push(u);
				}
			}
			var orderedUsers = selectedUsers.concat(unselectedUsers);

			function oneRowHtml(ou) {
				var st = String((ou.name || '') + ' ' + (ou.email || '')).toLowerCase();
				var av = esc(ou.avatar_url || '');
				var name = esc(ou.name || 'User');
				var em = esc(ou.email || '');
				var roleBadge = roleBadgeHtml(ou.account_status);
				var isShared = sharedIds.has(ou.id);
				var isSelected = pending.has(ou.id) || (isShared && !pendingRemove.has(ou.id));
				return (
					'<li class="project-option-item share-note-user-option-item" data-search-text="' + esc(st) + '">' +
					'<a href="#" class="dropdown-item d-flex align-items-center share-note-user-option team-group-dd-option' +
					(isSelected ? ' selected' : '') +
					'" data-user-id="' + ou.id + '" data-is-shared="' + (isShared ? '1' : '0') + '">' +
					'<span class="team-group-dd-check" aria-hidden="true">' + (isSelected ? SHARE_NOTE_CHECK_SVG : '') + '</span>' +
					'<img src="' + av + '" class="rounded-circle me-2" width="32" height="32" alt="">' +
					'<div class="w-100">' +
					'<div class="d-flex align-items-center justify-content-between"><span>' + name + '</span>' + roleBadge + '</div>' +
					'<small style="color:#888">' + em + '</small>' +
					'</div>' +
					'</a></li>'
				);
			}

			var html = [];
			var visible = 0;
			if (profileUser) {
				html.push(oneRowHtml(profileUser));
				visible++;
				if (orderedUsers.length > 0) {
					html.push(
						'<li class="share-note-user-list-sep" role="separator" aria-hidden="true"><div class="dropdown-divider m-0"></div></li>'
					);
				}
			}
			for (var j = 0; j < orderedUsers.length; j++) {
				html.push(oneRowHtml(orderedUsers[j]));
				visible++;
			}
			optionsList.innerHTML = html.join('');
			if (noMembersLi) {
				noMembersLi.style.display = visible === 0 ? 'block' : 'none';
			}
			if (dropdownBtn) {
				var btnText = dropdownBtn.querySelector('#shareNoteUserDropdownBtnText');
				if (btnText) {
					btnText.textContent = totalSelected > 0 ? totalSelected + ' selected' : 'Select team members';
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
			if (!activeProfileNoteId || !optionsList) return Promise.resolve();
			var u =
				ajaxBase +
				'share_search.php?q=' +
				encodeURIComponent(q || '') +
				'&subject_user_id=' +
				encodeURIComponent(String(subjectUserId));
			return fetch(u)
				.then(readResponseJson)
				.then(function (resp) {
					var users =
						resp && resp.status === 'ok' && resp.data && Array.isArray(resp.data.users) ? resp.data.users : [];
					lastUserFetch = users;
					renderUserOptions(users);
				})
				.catch(function () {
					lastUserFetch = [];
					renderUserOptions([]);
				});
		}

		function loadShares() {
			if (!activeProfileNoteId) return Promise.resolve();
			var url =
				ajaxBase +
				'share_list.php?profile_note_id=' +
				encodeURIComponent(String(activeProfileNoteId)) +
				'&subject_user_id=' +
				encodeURIComponent(String(subjectUserId));
			return fetch(url)
				.then(readResponseJson)
				.then(function (resp) {
					if (!resp || resp.status !== 'ok') return;
					var shares = resp.data && resp.data.shares ? resp.data.shares : [];
					updateSharedIds(shares);
					return shares;
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

		function onPickUser(uid) {
			var u = null;
			for (var i = 0; i < lastUserFetch.length; i++) {
				if (lastUserFetch[i].id === uid) {
					u = lastUserFetch[i];
					break;
				}
			}
			if (!u) return;
			if (sharedIds.has(uid)) {
				if (pendingRemove.has(uid)) {
					pendingRemove.delete(uid);
				} else {
					pendingRemove.add(uid);
				}
				renderUserOptions(lastUserFetch);
				return;
			}
			if (pending.has(uid)) {
				pending.delete(uid);
			} else {
				pending.set(uid, { name: u.name || 'User', email: u.email || '', avatar_url: u.avatar_url || '' });
			}
			renderUserOptions(lastUserFetch);
		}

		function applyPendingShares() {
			if (!activeProfileNoteId) return;
			if (pending.size === 0 && pendingRemove.size === 0) {
				alert('Select team members from the list first.');
				return;
			}
			if (applyBtn) {
				applyBtn.disabled = true;
				applyBtn.innerHTML =
					'<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sharing...';
			}
			var permission = sharePermission && sharePermission.value ? sharePermission.value : 'view';
			var ids = Array.from(pending.keys());
			var removeIds = Array.from(pendingRemove.values());
			var chain = Promise.resolve();
			removeIds.forEach(function (uid) {
				chain = chain.then(function () {
					return fetch(ajaxBase + 'share_remove.php', {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
						body: JSON.stringify({
							profile_note_id: activeProfileNoteId,
							subject_user_id: subjectUserId,
							shared_with_user_id: uid,
							csrf_token: csrfToken,
						}),
					})
						.then(readResponseJson)
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
						headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
						body: JSON.stringify({
							profile_note_id: activeProfileNoteId,
							subject_user_id: subjectUserId,
							shared_with_user_id: uid,
							permission: permission,
							csrf_token: csrfToken,
						}),
					})
						.then(readResponseJson)
						.then(function (resp) {
							if (!resp || resp.status !== 'ok') {
								throw new Error((resp && resp.error) ? resp.error : 'Failed to share');
							}
						});
				});
			});
			chain
				.then(function () {
					pending.clear();
					pendingRemove.clear();
					return loadShares();
				})
				.then(function () {
					return fetchUsers(searchInput ? searchInput.value.trim() : '');
				})
				.then(function () {
					if (shareModal) shareModal.hide();
					window.location.reload();
				})
				.catch(function (err) {
					alert(err && err.message ? err.message : 'Failed to share note');
				})
				.finally(function () {
					if (applyBtn) {
						applyBtn.disabled = false;
						applyBtn.innerHTML = applyBtnDefaultHtml;
					}
				});
		}

		document.addEventListener('click', function (e) {
			var trig = e.target.closest('[data-profile-note-share]');
			if (!trig) return;
			e.preventDefault();
			e.stopPropagation();
			var nid = parseInt(trig.getAttribute('data-profile-note-share') || '0', 10) || 0;
			if (!nid) return;
			activeProfileNoteId = nid;
			if (window.PROFILE_NOTE_SHARE) {
				window.PROFILE_NOTE_SHARE.profileNoteId = nid;
			}
			var dd = trig.closest('.dropdown');
			if (dd && typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
				var tgl = dd.querySelector('[data-bs-toggle="dropdown"]');
				if (tgl) {
					var inst = bootstrap.Dropdown.getInstance(tgl);
					if (inst) inst.hide();
				}
			}
			shareModal.show();
		});

		shareModalEl.addEventListener('shown.bs.modal', function () {
			if (searchInput) searchInput.value = '';
			pending.clear();
			pendingRemove.clear();
			lastUserFetch = [];
			var startId = parseInt(String((window.PROFILE_NOTE_SHARE && window.PROFILE_NOTE_SHARE.profileNoteId) || '0'), 10) || 0;
			if (startId) {
				activeProfileNoteId = startId;
			}
			loadShares()
				.then(function () {
					return fetchUsers('');
				})
				.then(function () {
					openUserDropdown();
				});
		});

		if (dropdownMenu && searchInput) {
			var sticky = dropdownMenu.querySelector('.px-3.py-2');
			if (sticky) {
				sticky.addEventListener('click', function (ev) {
					ev.stopPropagation();
				});
			}
			searchInput.addEventListener('click', function (ev) {
				ev.stopPropagation();
			});
			searchInput.addEventListener('input', function () {
				clearTimeout(searchTimer);
				searchTimer = setTimeout(function () {
					fetchUsers(searchInput.value.trim());
				}, 250);
			});
		}

		if (userDropdown && searchInput) {
			userDropdown.addEventListener('hide.bs.dropdown', function (ev) {
				ev.preventDefault();
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
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initProfileNotesShare);
	} else {
		initProfileNotesShare();
	}
})();
