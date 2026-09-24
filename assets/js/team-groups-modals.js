(function () {
    'use strict';

    /** Selected-row checkmark (inline SVG). */
    var TEAM_GROUP_CHECK_SVG =
        '<svg class="team-group-dd-check-svg" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">' +
        '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>' +
        '</svg>';

    function apiUrl() {
        return window.teamGroupsApiUrl || '';
    }

    function escapeHtml(s) {
        if (s == null) return '';
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function pickerInitialsFromUser(u) {
        const raw = String(u.name || '')
            .replace(/&nbsp;/g, ' ')
            .replace(/<[^>]*>/g, '')
            .trim();
        const parts = raw.split(/\s+/).filter(Boolean);
        if (parts.length >= 2) {
            return (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase().slice(0, 2);
        }
        return (raw.charAt(0) || 'U').toUpperCase();
    }

    function setPickerAvatar(av, u) {
        if (!av) return;
        const html = String(u.image || '').trim();
        if (html.indexOf('<img') !== -1) {
            av.innerHTML = html;
            return;
        }
        av.innerHTML =
            '<div class="avatar-initials color-8 avatar-initials-small rounded-circle">' +
            escapeHtml(pickerInitialsFromUser(u)) +
            '</div>';
    }

    function openMemberDropdown(prefix) {
        if (typeof bootstrap === 'undefined' || !bootstrap.Dropdown) return;
        const btn = document.getElementById(prefix + 'MemberDropdownBtn');
        if (!btn) return;
        window.setTimeout(function () {
            try {
                bootstrap.Dropdown.getOrCreateInstance(btn).show();
            } catch (e) {
                /* ignore */
            }
        }, 0);
    }

    function getPickableUsers() {
        return Array.isArray(window.teamGroupPickableUsers) ? window.teamGroupPickableUsers : [];
    }

    function renderMemberOptions(prefix, selectedIds) {
        const listWrap = document.getElementById(prefix + 'MemberOptionsList');
        const searchInput = document.getElementById(prefix + 'MemberSearchInput');
        const noRow = document.getElementById(prefix + 'NoMembersFound');
        const hidden = document.getElementById(prefix + 'SelectedMemberIds');
        const btnLabel = document.getElementById(prefix + 'MemberDropdownLabel');
        if (!listWrap || !searchInput || !hidden) return;

        const q = (searchInput.value || '').trim().toLowerCase();
        listWrap.innerHTML = '';
        const filtered = getPickableUsers().filter(function (u) {
            return !q || (u.search && u.search.indexOf(q) !== -1);
        });
        if (!filtered.length) {
            if (noRow) noRow.classList.remove('d-none');
            return;
        }
        if (noRow) noRow.classList.add('d-none');

        const selectedUsers = [];
        const unselectedUsers = [];
        filtered.forEach(function (u) {
            const idStr = String(u.id);
            if (selectedIds.indexOf(idStr) !== -1) {
                selectedUsers.push(u);
            } else {
                unselectedUsers.push(u);
            }
        });
        selectedUsers.sort(function (a, b) {
            return selectedIds.indexOf(String(a.id)) - selectedIds.indexOf(String(b.id));
        });
        const users = selectedUsers.concat(unselectedUsers);

        users.forEach(function (u) {
            const idStr = String(u.id);
            const sel = selectedIds.indexOf(idStr) !== -1;
            const li = document.createElement('li');
            li.className = 'project-option-item';
            const a = document.createElement('a');
            a.href = '#';
            a.className = 'dropdown-item team-group-dd-option d-flex align-items-center' + (sel ? ' selected' : '');
            a.setAttribute('data-user-id', idStr);
            a.innerHTML =
                '<span class="team-group-dd-check" aria-hidden="true">' +
                (sel ? TEAM_GROUP_CHECK_SVG : '') +
                '</span><div class="tg-avatar-wrap me-2"></div><div class="tg-opt-lines"><div></div><small></small></div>';
            const av = a.querySelector('.tg-avatar-wrap');
            const lines = a.querySelector('.tg-opt-lines');
            setPickerAvatar(av, u);
            if (lines) {
                lines.querySelector('div').textContent = u.name || '';
                lines.querySelector('small').textContent = u.email || '';
            }
            li.appendChild(a);
            listWrap.appendChild(li);
        });

        hidden.value = selectedIds.join(',');
        if (btnLabel) {
            const tmpl = window.__teamGroupsLang || {};
            const n = selectedIds.length;
            btnLabel.textContent =
                n > 0
                    ? (tmpl.membersSelectedN || '{n} selected').replace('{n}', String(n))
                    : tmpl.selectMembers || 'Select members';
        }
    }

    function bindMemberDropdownOnce(prefix, state) {
        const menu = document.getElementById(prefix + 'MemberDropdownMenu');
        const listWrap = document.getElementById(prefix + 'MemberOptionsList');
        const searchInput = document.getElementById(prefix + 'MemberSearchInput');
        const noRow = document.getElementById(prefix + 'NoMembersFound');
        if (!menu || !listWrap || !searchInput || menu.dataset.teamDdBound === '1') {
            return;
        }
        menu.dataset.teamDdBound = '1';

        searchInput.addEventListener('input', function () {
            renderMemberOptions(prefix, state.selectedIds);
        });

        listWrap.addEventListener('click', function (e) {
            const a = e.target.closest('a.team-group-dd-option');
            if (!a) return;
            e.preventDefault();
            e.stopPropagation();
            const idStr = a.getAttribute('data-user-id');
            if (!idStr) return;
            const i = state.selectedIds.indexOf(idStr);
            if (i === -1) state.selectedIds.push(idStr);
            else state.selectedIds.splice(i, 1);
            renderMemberOptions(prefix, state.selectedIds);
            e.stopImmediatePropagation();
        });

        if (noRow) noRow.classList.add('d-none');
        renderMemberOptions(prefix, state.selectedIds);
    }

    function postJson(body) {
        var csrf = window.__teamGroupsCsrfToken || '';
        return fetch(apiUrl(), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify(body),
            credentials: 'same-origin',
        }).then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok || !j.ok) {
                    throw new Error((j && j.error) || r.statusText || 'Request failed');
                }
                return j;
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const createState = { selectedIds: [] };
        const editState = { selectedIds: [] };

        bindMemberDropdownOnce('teamGroupCreate', createState);
        bindMemberDropdownOnce('teamGroupEdit', editState);

        const createModal = document.getElementById('teamGroupCreateModal');
        const editModal = document.getElementById('teamGroupEditModal');
        const nameCreate = document.getElementById('teamGroupCreateName');
        const nameEdit = document.getElementById('teamGroupEditName');
        const editId = document.getElementById('teamGroupEditId');
        const errCreate = document.getElementById('teamGroupCreateError');
        const errEdit = document.getElementById('teamGroupEditError');

        function clearErr(el) {
            if (el) {
                el.textContent = '';
                el.style.display = 'none';
            }
        }

        if (createModal) {
            createModal.addEventListener('show.bs.modal', function () {
                clearErr(errCreate);
                if (nameCreate) nameCreate.value = '';
                const si = document.getElementById('teamGroupCreateMemberSearchInput');
                if (si) si.value = '';
                createState.selectedIds = [];
                renderMemberOptions('teamGroupCreate', createState.selectedIds);
            });
            createModal.addEventListener('shown.bs.modal', function () {
                openMemberDropdown('teamGroupCreate');
            });
        }

        if (editModal) {
            editModal.addEventListener('shown.bs.modal', function () {
                openMemberDropdown('teamGroupEdit');
            });
        }

        const btnCreateSave = document.getElementById('teamGroupCreateSave');
        if (btnCreateSave) {
            btnCreateSave.addEventListener('click', function () {
                clearErr(errCreate);
                const name = nameCreate ? nameCreate.value.trim() : '';
                const ids = createState.selectedIds.map(function (x) {
                    return parseInt(x, 10);
                });
                if (!name) {
                    errCreate.textContent = (window.__teamGroupsLang || {}).nameRequired || 'Name is required';
                    errCreate.style.display = 'block';
                    return;
                }
                postJson({ action: 'create', name: name, user_ids: ids })
                    .then(function () {
                        window.location.reload();
                    })
                    .catch(function (e) {
                        errCreate.textContent = e.message || 'Error';
                        errCreate.style.display = 'block';
                    });
            });
        }

        document.querySelectorAll('.js-edit-team-group').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const gid = parseInt(btn.getAttribute('data-group-id'), 10);
                if (!gid || !editModal) return;
                clearErr(errEdit);
                fetch(apiUrl() + '?action=get&id=' + gid, { credentials: 'same-origin' })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (j) {
                        if (!j.ok || !j.group) throw new Error(j.error || 'Load failed');
                        editId.value = String(j.group.id);
                        nameEdit.value = j.group.name || '';
                        editState.selectedIds = (j.group.user_ids || []).map(String);
                        const si = document.getElementById('teamGroupEditMemberSearchInput');
                        if (si) si.value = '';
                        renderMemberOptions('teamGroupEdit', editState.selectedIds);
                        const m = bootstrap.Modal.getOrCreateInstance(editModal);
                        m.show();
                    })
                    .catch(function (e) {
                        alert(e.message || 'Error');
                    });
            });
        });

        const btnEditSave = document.getElementById('teamGroupEditSave');
        if (btnEditSave) {
            btnEditSave.addEventListener('click', function () {
                clearErr(errEdit);
                const id = parseInt(editId.value, 10);
                const name = nameEdit ? nameEdit.value.trim() : '';
                const ids = editState.selectedIds.map(function (x) {
                    return parseInt(x, 10);
                });
                if (!id || !name) {
                    errEdit.textContent = (window.__teamGroupsLang || {}).nameRequired || 'Name is required';
                    errEdit.style.display = 'block';
                    return;
                }
                postJson({ action: 'update', id: id, name: name, user_ids: ids })
                    .then(function () {
                        window.location.reload();
                    })
                    .catch(function (e) {
                        errEdit.textContent = e.message || 'Error';
                        errEdit.style.display = 'block';
                    });
            });
        }

        function confirmAndDeleteGroup(id, name) {
            const L = window.__teamGroupsLang || {};
            let msg = L.confirmDelete || 'Delete this group?';
            const named = L.confirmDeleteNamed || '';
            if (name && named) {
                msg = named.replace(/\{name\}/g, name);
            } else if (name) {
                msg = msg + '\n\n' + name;
            }
            if (!window.confirm(msg)) return;
            postJson({ action: 'delete', id: id })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (e) {
                    alert(e.message || 'Error');
                });
        }

        document.querySelectorAll('.js-delete-team-group').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const id = parseInt(btn.getAttribute('data-group-id'), 10);
                if (!id) return;
                const nm = btn.getAttribute('data-group-name') || '';
                confirmAndDeleteGroup(id, nm);
            });
        });

        document.querySelectorAll('.js-clone-team-group').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const id = parseInt(btn.getAttribute('data-group-id'), 10);
                if (!id) return;
                postJson({ action: 'clone', id: id })
                    .then(function () {
                        window.location.reload();
                    })
                    .catch(function (err) {
                        alert(err.message || 'Error');
                    });
            });
        });

        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('.team-group-card [data-bs-toggle="tooltip"]').forEach(function (el) {
                new bootstrap.Tooltip(el);
            });
        }
    });
})();
