<?php
if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}
?>
<div class="modal fade team-group-modal edit-group-chat-modal" id="editGroupModal" tabindex="-1" aria-labelledby="editGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-mg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGroupModalLabel"><?php echo htmlspecialchars($lang['Edit Group'] ?? 'Edit Group', ENT_QUOTES, 'UTF-8'); ?></h5>
                <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body modal-height">
                <input type="hidden" id="edit-group-id" value="">

                <div class="form-group field-label mb-3">
                    <label for="edit-group-name" class="form-label"><?php echo htmlspecialchars($lang['Group name'] ?? 'Group Name', ENT_QUOTES, 'UTF-8'); ?>*</label>
                    <input type="text" class="field-btn w-100" id="edit-group-name" placeholder="<?php echo htmlspecialchars($lang['Enter group name'] ?? 'Enter group name', ENT_QUOTES, 'UTF-8'); ?>" maxlength="255" autocomplete="off">
                </div>

                <div class="form-group field-label mb-0">
                    <label class="form-label" for="editGroupMemberDropdownBtn"><?php echo htmlspecialchars($lang['Select members'] ?? 'Select members', ENT_QUOTES, 'UTF-8'); ?>*</label>
                    <div class="mb-2 w-100">
                        <div class="dropdown w-100" id="editGroupMemberDropdown">
                            <button type="button" class="field-btn dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between mv-share-dd-toggle" id="editGroupMemberDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded="false">
                                <span class="flex-grow-1 text-truncate min-width-0 text-start text-muted small" id="editGroupMemberDropdownBtnText"><?php echo htmlspecialchars($lang['Select members'] ?? 'Select members', ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="mv-share-dd-close flex-shrink-0" role="button" tabindex="-1" aria-label="Close">&times;</span>
                            </button>
                            <ul class="dropdown-menu w-100 dropdown-scroll p-0 no-shadow" id="editGroupMemberDropdownMenu" aria-labelledby="editGroupMemberDropdownBtn">
                                <li class="modal-picker-dd-search px-3 py-2">
                                    <input type="text" id="editGroupMemberSearchInput" class="form-control" placeholder="<?php echo htmlspecialchars($lang['Search by name or email...'] ?? 'Search by name or email...', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                                </li>
                                <li class="px-3 py-1 d-none" id="editGroupNoMembersFound" style="color:#999;font-style:italic;"><?php echo htmlspecialchars($lang['No members found'] ?? 'No members found', ENT_QUOTES, 'UTF-8'); ?></li>
                                <li class="p-0 border-0">
                                    <ul class="list-unstyled mb-0 w-100" id="editGroupMemberOptionsList"></ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-end align-items-center col-gap-5 flex-wrap">
                <button type="button" class="primary-btn" id="save-group-edit-btn"><?php echo htmlspecialchars($lang['Save Changes'] ?? 'Save changes', ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>
</div>
