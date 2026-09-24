<?php
if (!isset($teamGroupPickableUsers) || !is_array($teamGroupPickableUsers)) {
    $teamGroupPickableUsers = [];
}
?>
<div class="modal fade team-group-modal" id="teamGroupCreateModal" tabindex="-1" aria-labelledby="teamGroupCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="teamGroupCreateModalLabel"><?php echo htmlspecialchars($lang['Create Team Group'] ?? 'Create team group'); ?></h5>
                <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close"><?php echo ts_icon('close'); ?></button>
            </div>
            <div class="modal-body modal-height">
                <div id="teamGroupCreateError" class="alert alert-danger" style="display:none;" role="alert"></div>
                <div class="form-group mb-3">
                    <label for="teamGroupCreateName"><?php echo htmlspecialchars($lang['Group name'] ?? 'Group name'); ?>*</label>
                    <input type="text" class="form-control" id="teamGroupCreateName" maxlength="191" autocomplete="off">
                </div>
                <div class="form-group mb-0">
                    <label><?php echo htmlspecialchars($lang['Add members optional'] ?? 'Add members (optional)'); ?></label>
                    <div class="mb-2 w-100">
                        <div class="dropdown w-100">
                            <button type="button" class="field-btn dropdown-toggle w-100 text-start" id="teamGroupCreateMemberDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded="false">
                                <span id="teamGroupCreateMemberDropdownLabel" class="text-muted small"><?php echo htmlspecialchars($lang['Select members'] ?? 'Select members'); ?></span>
                            </button>
                            <ul class="dropdown-menu w-100 dropdown-scroll p-0" id="teamGroupCreateMemberDropdownMenu" aria-labelledby="teamGroupCreateMemberDropdownBtn">
                                <li class="modal-picker-dd-search px-2 py-2">
                                    <input type="text" id="teamGroupCreateMemberSearchInput" class="form-control form-control-sm" placeholder="<?php echo htmlspecialchars($lang['Search members...'] ?? 'Search members...'); ?>" autocomplete="off">
                                </li>
                                <li id="teamGroupCreateNoMembersFound" class="dropdown-item-text px-3 py-2 text-muted small fst-italic d-none"><?php echo htmlspecialchars($lang['No members found'] ?? 'No members found'); ?></li>
                                <li class="p-0 border-0">
                                    <ul class="list-unstyled mb-0 w-100" id="teamGroupCreateMemberOptionsList"></ul>
                                </li>
                            </ul>
                        </div>
                        <input type="hidden" id="teamGroupCreateSelectedMemberIds" value="">
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-end align-items-center col-gap-10 flex-wrap">
                <button type="button" class="btn border-btn-a" data-bs-dismiss="modal"><?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel'); ?></button>
                <button type="button" class="btn primary-btn" id="teamGroupCreateSave"><?php echo htmlspecialchars($lang['Save'] ?? 'Save'); ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade team-group-modal" id="teamGroupEditModal" tabindex="-1" aria-labelledby="teamGroupEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="teamGroupEditModalLabel"><?php echo htmlspecialchars($lang['Edit Team Group'] ?? 'Edit team group'); ?></h5>
                <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close"><?php echo ts_icon('close'); ?></button>
            </div>
            <div class="modal-body modal-height">
                <input type="hidden" id="teamGroupEditId" value="">
                <div id="teamGroupEditError" class="alert alert-danger" style="display:none;" role="alert"></div>
                <div class="form-group mb-3">
                    <label for="teamGroupEditName"><?php echo htmlspecialchars($lang['Group name'] ?? 'Group name'); ?>*</label>
                    <input type="text" class="form-control" id="teamGroupEditName" maxlength="191" autocomplete="off">
                </div>
                <div class="form-group mb-0">
                    <label><?php echo htmlspecialchars($lang['Add members optional'] ?? 'Add members (optional)'); ?></label>
                    <div class="mb-2 w-100">
                        <div class="dropdown w-100">
                            <button type="button" class="field-btn dropdown-toggle w-100 text-start" id="teamGroupEditMemberDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded="false">
                                <span id="teamGroupEditMemberDropdownLabel" class="text-muted small"><?php echo htmlspecialchars($lang['Select members'] ?? 'Select members'); ?></span>
                            </button>
                            <ul class="dropdown-menu w-100 dropdown-scroll p-0" id="teamGroupEditMemberDropdownMenu" aria-labelledby="teamGroupEditMemberDropdownBtn">
                                <li class="modal-picker-dd-search px-2 py-2">
                                    <input type="text" id="teamGroupEditMemberSearchInput" class="form-control form-control-sm" placeholder="<?php echo htmlspecialchars($lang['Search members...'] ?? 'Search members...'); ?>" autocomplete="off">
                                </li>
                                <li id="teamGroupEditNoMembersFound" class="dropdown-item-text px-3 py-2 text-muted small fst-italic d-none"><?php echo htmlspecialchars($lang['No members found'] ?? 'No members found'); ?></li>
                                <li class="p-0 border-0">
                                    <ul class="list-unstyled mb-0 w-100" id="teamGroupEditMemberOptionsList"></ul>
                                </li>
                            </ul>
                        </div>
                        <input type="hidden" id="teamGroupEditSelectedMemberIds" value="">
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-end align-items-center col-gap-10 flex-wrap">
                <button type="button" class="btn border-btn-a" data-bs-dismiss="modal"><?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel'); ?></button>
                <button type="button" class="btn primary-btn" id="teamGroupEditSave"><?php echo htmlspecialchars($lang['Save'] ?? 'Save'); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
window.teamGroupPickableUsers = <?php echo json_encode($teamGroupPickableUsers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
window.teamGroupsApiUrl = <?php echo json_encode(rtrim($url, '/') . '/includes/api/team-groups-api.php'); ?>;
window.__teamGroupsCsrfToken = <?php echo json_encode(generate_csrf_token()); ?>;
window.__teamGroupsLang = {
    selectMembers: <?php echo json_encode($lang['Select members'] ?? 'Select members'); ?>,
    membersSelectedN: <?php echo json_encode($lang['{n} members selected'] ?? '{n} selected'); ?>,
    nameRequired: <?php echo json_encode($lang['Group name is required'] ?? 'Name is required'); ?>,
    confirmDelete: <?php echo json_encode($lang['Delete this team group?'] ?? 'Delete this group?'); ?>,
    confirmDeleteNamed: <?php echo json_encode($lang['Delete team group named'] ?? 'Delete team group "{name}"?'); ?>
};
</script>
