<?php
if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}
$createGroupCompaniesTab = !empty($createGroupCompaniesEnabled);
$createGroupTeamGroupsTab = !empty($createGroupTeamGroupsEnabled);
$createGroupPickerTabs = $createGroupCompaniesTab || $createGroupTeamGroupsTab;
?>
<div class="modal fade team-group-modal create-group-chat-modal" id="createGroupModal" tabindex="-1" aria-labelledby="createGroupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-mg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createGroupModalLabel"><?php echo htmlspecialchars($lang['Create Group Chat'] ?? 'Create group chat', ENT_QUOTES, 'UTF-8'); ?></h5>
                <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body modal-height">
                <div class="form-group field-label mb-3">
                    <label for="group-name-input" class="form-label"><?php echo htmlspecialchars($lang['Group name'] ?? 'Group name', ENT_QUOTES, 'UTF-8'); ?>*</label>
                    <input type="text" class="field-btn w-100" id="group-name-input" placeholder="<?php echo htmlspecialchars($lang['Enter group name'] ?? 'Enter group name', ENT_QUOTES, 'UTF-8'); ?>" maxlength="255" autocomplete="off">
                </div>

                <?php if ($createGroupPickerTabs) : ?>
                <div class="modal-tabs-scroll mb-3">
                    <ul class="nav nav-tabs flex-nowrap" id="createGroupPickerTabs" role="tablist">
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link active" id="createGroupTabMembersBtn" data-bs-toggle="tab" data-bs-target="#createGroupPaneMembers" type="button" role="tab" aria-controls="createGroupPaneMembers" aria-selected="true"><?php echo htmlspecialchars($lang['Members'] ?? 'Members', ENT_QUOTES, 'UTF-8'); ?></button>
                        </li>
                        <?php if ($createGroupCompaniesTab) : ?>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="createGroupTabCompaniesBtn" data-bs-toggle="tab" data-bs-target="#createGroupPaneCompanies" type="button" role="tab" aria-controls="createGroupPaneCompanies" aria-selected="false"><?php echo htmlspecialchars($lang['Company'] ?? 'Companies', ENT_QUOTES, 'UTF-8'); ?></button>
                        </li>
                        <?php endif; ?>
                        <?php if ($createGroupTeamGroupsTab) : ?>
                        <li class="nav-item flex-shrink-0" role="presentation">
                            <button class="nav-link" id="createGroupTabTeamGroupsBtn" data-bs-toggle="tab" data-bs-target="#createGroupPaneTeamGroups" type="button" role="tab" aria-controls="createGroupPaneTeamGroups" aria-selected="false"><?php echo htmlspecialchars($lang['Team Groups'] ?? 'Team groups', ENT_QUOTES, 'UTF-8'); ?></button>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="createGroupPaneMembers" role="tabpanel" aria-labelledby="createGroupTabMembersBtn" tabindex="0">
                        <div class="form-group field-label mb-0">
                            <label class="form-label" for="createGroupMemberDropdownBtn"><?php echo htmlspecialchars($lang['Select members'] ?? 'Select members', ENT_QUOTES, 'UTF-8'); ?>*</label>
                            <div class="mb-2 w-100">
                                <div class="dropdown w-100" id="createGroupMemberDropdown">
                                    <button type="button" class="field-btn dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between mv-share-dd-toggle" id="createGroupMemberDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded="false">
                                        <span class="flex-grow-1 text-truncate min-width-0 text-start text-muted small" id="createGroupMemberDropdownBtnText"><?php echo htmlspecialchars($lang['Select members'] ?? 'Select members', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="mv-share-dd-close flex-shrink-0" role="button" tabindex="-1" aria-label="Close">&times;</span>
                                    </button>
                                    <ul class="dropdown-menu w-100 dropdown-scroll p-0 no-shadow" id="createGroupMemberDropdownMenu" aria-labelledby="createGroupMemberDropdownBtn">
                                        <li class="modal-picker-dd-search px-3 py-2">
                                            <input type="text" id="createGroupMemberSearchInput" class="form-control" placeholder="<?php echo htmlspecialchars($lang['Search by name or email...'] ?? 'Search by name or email...', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                                        </li>
                                        <li class="px-3 py-1 d-none" id="createGroupNoMembersFound" style="color:#999;font-style:italic;"><?php echo htmlspecialchars($lang['No members found'] ?? 'No members found', ENT_QUOTES, 'UTF-8'); ?></li>
                                        <li class="p-0 border-0">
                                            <ul class="list-unstyled mb-0 w-100" id="createGroupMemberOptionsList"></ul>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($createGroupCompaniesTab) : ?>
                    <div class="tab-pane fade" id="createGroupPaneCompanies" role="tabpanel" aria-labelledby="createGroupTabCompaniesBtn" tabindex="0">
                        <div class="form-group field-label mb-0">
                            <label class="form-label" for="createGroupCompanyDropdownBtn"><?php echo htmlspecialchars($lang['Select companies'] ?? 'Select companies', ENT_QUOTES, 'UTF-8'); ?></label>
                            <div class="mb-2 w-100">
                                <div class="dropdown w-100" id="createGroupCompanyDropdown">
                                    <button type="button" class="field-btn dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between mv-share-dd-toggle" id="createGroupCompanyDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded="false">
                                        <span class="flex-grow-1 text-truncate min-width-0 text-start text-muted small" id="createGroupCompanyDropdownBtnText"><?php echo htmlspecialchars($lang['Select companies'] ?? 'Select companies', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="mv-share-dd-close flex-shrink-0" role="button" tabindex="-1" aria-label="Close">&times;</span>
                                    </button>
                                    <ul class="dropdown-menu w-100 dropdown-scroll p-0 no-shadow" id="createGroupCompanyDropdownMenu" aria-labelledby="createGroupCompanyDropdownBtn">
                                        <li class="modal-picker-dd-search px-3 py-2">
                                            <input type="text" id="createGroupCompanySearchInput" class="form-control" placeholder="<?php echo htmlspecialchars($lang['Search companies...'] ?? 'Search companies...', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                                        </li>
                                        <li class="px-3 py-1 d-none" id="createGroupNoCompaniesFound" style="color:#999;font-style:italic;"><?php echo htmlspecialchars($lang['No records Found!'] ?? 'No companies found', ENT_QUOTES, 'UTF-8'); ?></li>
                                        <li class="p-0 border-0">
                                            <ul class="list-unstyled mb-0 w-100" id="createGroupCompanyOptionsList"></ul>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($createGroupTeamGroupsTab) : ?>
                    <div class="tab-pane fade" id="createGroupPaneTeamGroups" role="tabpanel" aria-labelledby="createGroupTabTeamGroupsBtn" tabindex="0">
                        <div class="form-group field-label mb-0">
                            <label class="form-label" for="createGroupTeamGroupDropdownBtn"><?php echo htmlspecialchars($lang['Team Groups'] ?? 'Team groups', ENT_QUOTES, 'UTF-8'); ?></label>
                            <div class="mb-2 w-100">
                                <div class="dropdown w-100" id="createGroupTeamGroupDropdown">
                                    <button type="button" class="field-btn dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between mv-share-dd-toggle" id="createGroupTeamGroupDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="false" aria-expanded="false">
                                        <span class="flex-grow-1 text-truncate min-width-0 text-start text-muted small" id="createGroupTeamGroupDropdownBtnText"><?php echo htmlspecialchars($lang['Select team groups'] ?? 'Select team groups', ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="mv-share-dd-close flex-shrink-0" role="button" tabindex="-1" aria-label="Close">&times;</span>
                                    </button>
                                    <ul class="dropdown-menu w-100 dropdown-scroll p-0 no-shadow" id="createGroupTeamGroupDropdownMenu" aria-labelledby="createGroupTeamGroupDropdownBtn">
                                        <li class="modal-picker-dd-search px-3 py-2">
                                            <input type="text" id="createGroupTeamGroupSearchInput" class="form-control" placeholder="<?php echo htmlspecialchars($lang['Search team groups...'] ?? 'Search team groups...', ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                                        </li>
                                        <li class="px-3 py-1 d-none" id="createGroupNoTeamGroupsFound" style="color:#999;font-style:italic;"><?php echo htmlspecialchars($lang['No team groups found'] ?? 'No team groups found', ENT_QUOTES, 'UTF-8'); ?></li>
                                        <li class="p-0 border-0">
                                            <ul class="list-unstyled mb-0 w-100" id="createGroupTeamGroupOptionsList"></ul>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-end align-items-center col-gap-5 flex-wrap">
                <button type="button" class="btn border-btn-a" data-bs-dismiss="modal"><?php echo htmlspecialchars($lang['Cancel'] ?? 'Cancel', ENT_QUOTES, 'UTF-8'); ?></button>
                <button type="button" class="primary-btn" id="create-group-submit-btn"><?php echo htmlspecialchars($lang['Create Group'] ?? 'Create group', ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>
</div>
