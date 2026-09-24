<?php
/*
================================================================================
  Lead Modal Template (Create/Edit) – reusable
  Location: templates/lead-modal.php
================================================================================
*/
?>

<!-- Add/Edit Lead Modal -->
<div class="modal fade" id="leadCreateModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-scrollable">
        <form id="leadCreateForm" class="modal-content" onsubmit="return false;">
            <input type="hidden" name="id" id="leadEditId" value="">
            <div class="modal-header">
                <h5 class="card-title" id="leadCreateModalTitle"><?php echo $lang['Add New Lead']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body">
                <div>
                    <ul class="nav nav-tabs" id="leadCreateTabs" role="tablist">
                        <li class="nav-item" style="margin-right: 5px;">
                            <button class="nav-link active" id="lead-step1-tab" data-bs-toggle="tab" data-bs-target="#lead-step1" type="button" role="tab" aria-controls="lead-step1" aria-selected="true">
                                <?php echo $lang['Lead Information'] ?? 'Lead Information'; ?>
                            </button>
                        </li>
                        <li class="nav-item" style="margin-right: 5px;">
                            <button class="nav-link" id="lead-step2-tab" data-bs-toggle="tab" data-bs-target="#lead-step2" type="button" role="tab" aria-controls="lead-step2" aria-selected="false">
                                <?php echo $lang['Client Information'] ?? 'Client Information'; ?>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="lead-step3-tab" data-bs-toggle="tab" data-bs-target="#lead-step3" type="button" role="tab" aria-controls="lead-step3" aria-selected="false">
                                <?php echo $lang['Notes']; ?>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content pt-3">
                        <!-- Step 1: Lead information -->
                        <div class="tab-pane fade show active" id="lead-step1" role="tabpanel" aria-labelledby="lead-step1-tab">
                            <div class="display-grid">
                                <div class="form-group">
                                    <label><?php echo $lang['Status']; ?></label>
                                    <select class="form-control" name="status_id" required>
                                        <?php foreach ($statuses as $st): ?>
                                            <option value="<?php echo (int)$st['id']; ?>" <?php echo !empty($st['is_default']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($st['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Source']; ?></label>
                                    <select class="form-control" name="source_id">
                                        <option value=""><?php echo $lang['Select']; ?></option>
                                        <?php foreach ($sources as $src): ?>
                                            <option value="<?php echo (int)$src['id']; ?>" <?php echo !empty($src['is_default']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($src['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Priority'] ?? 'Priority'; ?> <span class="text-danger">*</span></label>
                                    <select class="form-control" name="priority" id="leadPrioritySelect">
                                        <option value=""><?php echo $lang['Select']; ?></option>
                                        <option value="High"><?php echo $lang['High'] ?? 'High'; ?></option>
                                        <option value="Medium"><?php echo $lang['Medium'] ?? 'Medium'; ?></option>
                                        <option value="Low"><?php echo $lang['Low'] ?? 'Low'; ?></option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Tags'] ?? 'Tags'; ?></label>
                                    <div class="position-relative">
                                        <input 
                                            type="text" 
                                            class="form-control" 
                                            id="leadTagsSearchInput" 
                                            placeholder="<?php echo htmlspecialchars($lang['Select tags'] ?? 'Select tags', ENT_QUOTES, 'UTF-8'); ?>" 
                                            autocomplete="off"
                                        >
                                        <div class="position-absolute tags-arrow-icon" id="leadTagsArrowIcon">
                                            <?php echo ts_icon('chevron-right'); ?>
                                        </div>
                                        <div 
                                            id="leadTagsDropdownMenu" 
                                            class="position-absolute w-100 bg-white border rounded shadow-lg mt-1 tags-dropdown"
                                            style="display: none;"
                                        >
                                            <div id="leadTagsListContainer" class="p-2"></div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="tag_ids" id="leadSelectedTagsInput" value="" />
                                    <div id="leadSelectedTagsBadges" class="mt-2 d-flex flex-wrap col-gap-10 tags-badges"></div>
                                </div>
                                <div class="form-group full-grid">
                                    <label for="leadStaffDropdownBtn"><?php echo $lang['Assign team members & admins'] ?? 'Assign team members & admins'; ?> <span class="text-danger">*</span></label>
                                    <div class="mb-1">
                                        <div class="dropdown">
                                            <button
                                                class="field-btn dropdown-toggle w-100 text-start"
                                                type="button"
                                                id="leadStaffDropdownBtn"
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false"
                                            >
                                                <span id="leadStaffDropdownBtnText" data-placeholder="<?php echo htmlspecialchars($lang['Select Staff members'] ?? 'Select Staff members', ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo $lang['Select Staff members'] ?? 'Select Staff members'; ?>
                                                </span>
                                            </button>
                                            <ul
                                                class="dropdown-menu w-100"
                                                aria-labelledby="leadStaffDropdownBtn"
                                                id="leadStaffDropdownMenu"
                                                style="max-height: 250px; overflow-y: auto;"
                                            ></ul>
                                        </div>
                                        <input type="hidden" name="assigned_to" id="leadSelectedStaffInput" value="" />
                                        
                                    </div>
                                </div>
                                
                                <!-- Move to Column (Edit Mode Only) -->
                                <div class="form-group full-grid" id="leadMoveStatusGroup" style="display: none;">
                                    <label><?php echo $lang['Move to Column'] ?? 'Move to Column'; ?></label>
                                    <select class="form-control" id="leadMoveStatusSelect">
                                        <option value=""><?php echo $lang['Select Column'] ?? 'Select Column (optional)'; ?></option>
                                        <?php foreach ($statuses as $st): ?>
                                            <option value="<?php echo (int)$st['id']; ?>">
                                                <?php echo htmlspecialchars($st['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted"><?php echo $lang['Select a column to move this lead to'] ?? 'Select a column to move this lead to'; ?></small>
                                </div>

                                <!-- Custom Fields Section -->
                                <div class="form-group full-grid" id="leadCustomFieldsContainer">
                                    <!-- Custom fields will be dynamically inserted here -->
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Client information -->
                        <div class="tab-pane fade" id="lead-step2" role="tabpanel" aria-labelledby="lead-step2-tab">
                            <div class="display-grid">
                                <div class="form-group">
                                    <label><?php echo $lang['Name']; ?> <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" required>
                                    <input type="hidden" name="last_name" id="leadLastNameInput" value="">
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Email']; ?></label>
                                    <input type="email" class="form-control" name="email">
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Phone']; ?></label>
                                    <input type="text" class="form-control" name="phone">
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Website']; ?></label>
                                    <input type="url" class="form-control" name="website" placeholder="https://">
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Company Name'] ?? ($lang['Company Name'] ?? 'Company Name'); ?></label>
                                    <input type="text" class="form-control" name="company">
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Expected close date'] ?? 'Expected close date'; ?></label>
                                    <input type="date" class="form-control" name="expected_close_date">
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Country']; ?></label>
                                    <select name="country" class="form-control">
                                        <option value=""><?php echo $lang["Select Country"]; ?></option>
                                        <?php foreach($countries as $countrie){ echo '<option value="'.htmlspecialchars($countrie).'">'.htmlspecialchars($countrie).'</option>'; } ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Zip']; ?></label>
                                    <input type="text" class="form-control" name="zip">
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['City']; ?></label>
                                    <input type="text" class="form-control" name="city">
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['State']; ?></label>
                                    <input type="text" class="form-control" name="state">
                                </div>
                                <div class="form-group full-grid">
                                    <label><?php echo $lang['Address']; ?></label>
                                    <input type="text" class="form-control" name="address">
                                </div>
                                <div class="form-group">
                                    <label for="lead_currency"><?php echo $lang["Currency"]; ?></label>
                                    <select name="currency" id="lead_currency" class="form-control">
                                        <option value=""><?php echo $lang["Select Currency"]; ?></option>
                                        <?php
                                            $enabledCurrencies2 = $settings ? $settings->getMultipleCurrencies() : [];
                                            if (empty($enabledCurrencies2) && !empty($settings->system_currency)) {
                                                $enabledCurrencies2 = [$settings->system_currency];
                                            }
                                            foreach (($settings->currency_symbols ?? []) as $currency => $symbol) {
                                                if (!empty($enabledCurrencies2) && !in_array($currency, $enabledCurrencies2)) continue;
                                                echo '<option value="'.htmlspecialchars($currency).'">'.htmlspecialchars($symbol).'</option>';
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Lead Value'] ?? 'Lead Value'; ?></label>
                                    <input type="number" step="0.01" class="form-control" name="lead_value" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Notes -->
                        <div class="tab-pane fade" id="lead-step3" role="tabpanel" aria-labelledby="lead-step3-tab">
                            <div class="display-grid">
                                <div class="form-group full-grid">
                                    <label><?php echo $lang['Write lead note'] ?? 'Write lead note'; ?></label>
                                    <textarea class="form-control" name="notes" rows="6"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn border-btn-a" id="leadCreateBackBtn"><?php echo $lang['Back'] ?? 'Back'; ?></button>
                <button type="button" class="btn border-btn-a" id="leadCreateNextBtn"><?php echo $lang['Next'] ?? 'Next'; ?></button>
                <button type="submit" class="btn primary-btn" id="leadCreateSubmitBtn"><?php echo $lang['Create']; ?></button>
            </div>
        </form>
    </div>
</div>

<textarea id="leadStaffListForAssignmentJson" class="d-none"><?php echo htmlspecialchars(json_encode($staffListForAssignment ?? []), ENT_QUOTES, 'UTF-8'); ?></textarea>


