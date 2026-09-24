<?php
/**
 * Share private note modal (admin / staff / client).
 * Styles: ../vendor/google/gdrive/assets/css/file-sharing.css (share-folder-modal, field-btn).
 */
?>
<div class="modal fade team-group-modal show" id="shareNoteModal" tabindex="-1" aria-labelledby="shareNoteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-mg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shareNoteModalLabel">Share note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body modal-height">
                <form id="shareNoteForm" onsubmit="return false;">
                    <div class="form-group field-label">
                        <label for="sharePermissionSelect" class="form-label">Permissions</label>
                        <select class="field-btn w-100" id="sharePermissionSelect" name="permission">
                            <option value="view">View only — can read the note</option>
                            <option value="edit">Read &amp; write — can edit the note</option>
                        </select>
                    </div>

                    <div class="form-group field-label">
                        <label class="form-label">Select team members</label>
                        <div class="mb-3">
                            <div class="dropdown w-100" id="shareNoteUserDropdown">
                                <button
                                    class="field-btn dropdown-toggle w-100 text-start"
                                    type="button"
                                    id="shareNoteUserDropdownBtn"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="false"
                                    aria-expanded="false"
                                >
                                    <span id="shareNoteUserDropdownBtnText">Select team members</span>
                                </button>
                                <ul
                                    class="dropdown-menu w-100 dropdown-scroll p-0"
                                    aria-labelledby="shareNoteUserDropdownBtn"
                                    id="shareNoteUserDropdownMenu"
                                >
                                    <li class="modal-picker-dd-search px-3 py-2" style="position: sticky; top: 0; z-index: 10; border-bottom: 1px solid var(--border-color);">
                                        <input type="text" id="shareNoteMemberSearchInput" class="form-control" placeholder="Search by name or email..." autocomplete="off" style="width: 100%;">
                                    </li>
                                    <li class="px-3 py-1" id="shareNoteNoMembersFound" style="display: none; color: #999; font-style: italic;">No users found</li>
                                    <li class="p-0 border-0">
                                        <ul class="list-unstyled mb-0 w-100" id="shareNoteUserOptionsList"></ul>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn primary-btn" id="shareNoteApplyBtn">Share</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade team-group-modal" id="shareNoteLinkModal" tabindex="-1" aria-labelledby="shareNoteLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shareNoteLinkModalLabel">Public link</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group field-label mb-3">
                    <label for="shareLinkGeneralAccessSelect" class="form-label">General access</label>
                    <select class="field-btn form-select w-100" id="shareLinkGeneralAccessSelect">
                        <option value="restricted">Restricted</option>
                        <option value="anyone_link">Anyone with the link</option>
                    </select>
                    <small class="text-muted d-block mt-1" id="shareLinkGeneralAccessHint">Only invited users can open this note.</small>
                </div>

                <div class="form-group field-label mb-3">
                    <label for="shareLinkRoleSelect" class="form-label">Role</label>
                    <select class="field-btn form-select w-100" id="shareLinkRoleSelect">
                        <option value="viewer">Viewer</option>
                        <option value="editor">Editor</option>
                    </select>
                </div>

                <div class="form-group field-label">
                    <button type="button" class="btn border-btn-a" id="shareLinkCopyBtn">Copy link</button>
                </div>
                <div id="shareLinkMetaSection" class="br-top pt-3 mt-3">
                    <div class="font-size-13 mb-2 fw-semibold" id="shareLinkMetaAccess"></div>
                    <div class="grey font-size-11 mb-1"><span class="text-muted">Shared date:</span> <span id="shareLinkMetaSharedDate">—</span></div>
                    <div class="grey font-size-11"><span class="text-muted">Last update:</span> <span id="shareLinkMetaLastUpdate">—</span></div>
                </div>
            </div>
            <div class="modal-footer justify-content-end br-top">
                <button type="button" class="btn primary-btn" id="shareLinkDoneBtn">Done</button>
            </div>
        </div>
    </div>
</div>
