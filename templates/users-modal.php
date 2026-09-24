<?php
/*
================================================================================
  Users Modal Template (Lead -> Client Convert)
  Location: templates/users-modal.php
================================================================================
*/
?>

<div class="modal fade" id="convertClientModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?php echo $lang['Convert to client'] ?? 'Convert to client'; ?></h5>
			<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
				</div>

      <form id="convert-client-form" novalidate>
        <div class="modal-body">
          <input type="hidden" name="lead_id" id="convert_lead_id" value="">

          <!-- Success pane -->
          <div id="convertClientSuccessPane" style="display:none;">
            <div class="text-center py-4">
              <div class="mb-3">
                <strong><?php echo $lang['Account created successfully'] ?? 'Account created successfully'; ?></strong>
              </div>
              <div class="d-flex justify-content-center">
                <a href="#" class="btn primary-btn" id="convertClientViewProfileBtn" target="_blank"><?php echo $lang['View Profile'] ?? 'View profile'; ?></a>
              </div>
            </div>
          </div>

          <!-- Form panes -->
          <div id="convertClientFormPane">
            <ul class="nav nav-tabs" id="clientTabsModal" role="tablist">
              <li class="nav-item" style="margin-right: 5px;">
                <a class="nav-link active" id="cc-account-tab" href="#" role="tab"><?php echo $lang['Account Information'] ?? 'Account information'; ?></a>
              </li>
              <li class="nav-item">
                <a class="nav-link" id="cc-permissions-tab" href="#" role="tab"><?php echo $lang['Permissions'] ?? 'Permissions'; ?></a>
              </li>
            </ul>

            <div class="tab-content">
              <!-- Account Information -->
              <div class="tab-pane fade show active" id="cc-account-pane" role="tabpanel">
                <div class="user-info">
                  <div class="display-grid">
                    <div class="form-group">
                      <label><?php echo $lang['Full name*'] ?? 'Full name*'; ?></label>
                      <input type="text" name="firstName" id="cc_firstName" class="form-control" required>
                    </div>

                    <div class="form-group">
                      <label><?php echo $lang['Email*'] ?? 'Email*'; ?></label>
                      <input type="email" name="email" id="cc_email" class="form-control" required>
                    </div>

                    <div class="form-group">
                      <label><?php echo $lang['Password'] ?? 'Password'; ?>*</label>
                      <div class="eyes-row">
                        <input type="password" name="password" id="cc_password" class="form-control passwordfield" required autocomplete="new-password">
                        <?php echo ts_password_toggle_html($lang['Show password'] ?? 'Show password'); ?>
                      </div>
                    </div>

                    <div class="form-group">
                      <label><?php echo $lang['Company Name'] ?? 'Company Name'; ?></label>
                      <input type="text" name="company" id="cc_company" class="form-control">
                    </div>

                    <div class="form-group">
                      <label><?php echo $lang['Website URL'] ?? 'Website URL'; ?></label>
                      <input type="url" name="website" id="cc_website" class="form-control">
                    </div>

                    <div class="form-group">
                      <label><?php echo $lang['Country'] ?? 'Country'; ?></label>
                      <select name="country" id="cc_country" class="form-control">
                        <option value=""><?php echo $lang['Select Country'] ?? 'Select Country'; ?></option>
                        <?php foreach(($countries ?? []) as $c){ echo '<option value="'.htmlspecialchars($c).'">'.htmlspecialchars($c).'</option>'; } ?>
                      </select>
                    </div>

                    <div class="form-group">
                      <label><?php echo $lang['City'] ?? 'City'; ?></label>
                      <input type="text" name="city" id="cc_city" class="form-control">
                    </div>

                    <div class="form-group">
                      <label><?php echo $lang['State'] ?? 'State'; ?></label>
                      <input type="text" name="state" id="cc_state" class="form-control">
                    </div>

                    <div class="form-group">
                      <label><?php echo $lang['Address'] ?? 'Address'; ?></label>
                      <input type="text" name="address" id="cc_address" class="form-control">
                    </div>

                    <div class="form-group">
                      <label><?php echo $lang['Zip'] ?? 'Zip'; ?></label>
                      <input type="text" name="zip" id="cc_zip" class="form-control">
                    </div>

                    <div class="form-group">
                      <label><?php echo $lang['Currency'] ?? 'Currency'; ?> <span class="text-danger">*</span></label>
                      <select name="currency" id="cc_currency" class="form-control" required>
                        <option value=""><?php echo $lang['Select Currency'] ?? 'Select Currency'; ?></option>
                        <?php
                          $settings = settings::findById(1);
                          $enabledCurrencies = $settings ? $settings->getMultipleCurrencies() : [];
                          if (empty($enabledCurrencies) && $settings && !empty($settings->system_currency)) {
                            $enabledCurrencies = [$settings->system_currency];
                          }
                          $symbols = $settings && isset($settings->currency_symbols) ? $settings->currency_symbols : [];
                          foreach($symbols as $currency => $symbol){
                            if(in_array($currency, $enabledCurrencies)) {
                              echo '<option value="'.htmlspecialchars($currency).'">'.htmlspecialchars($symbol).'</option>';
                            }
                          }
                        ?>
                      </select>
                    </div>

                    <div class="form-group">
                      <label><?php echo $lang['Teams ID'] ?? 'Teams ID'; ?></label>
                      <input type="text" name="teams_id" id="cc_teams_id" class="form-control">
                    </div>

                    <!-- Assign Team -->
                    <div class="form-group full-grid">
                      <label for="ccStaffDropdownBtn">Assign team members & admins <span class="text-danger">*</span></label>
                      <div class="mb-3">
                        <div class="dropdown">
                          <button class="field-btn dropdown-toggle w-100 text-start" type="button" id="ccStaffDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                            <span id="ccStaffDropdownBtnText" data-placeholder="<?php echo htmlspecialchars($lang['Select Staff members'] ?? 'Select Staff members', ENT_QUOTES, 'UTF-8'); ?>"><?php echo $lang['Select Staff members'] ?? 'Select Staff members'; ?></span>
                          </button>
                          <ul class="dropdown-menu w-100" aria-labelledby="ccStaffDropdownBtn" id="ccStaffDropdownMenu" style="max-height: 250px; overflow-y: auto;"></ul>
                        </div>
                        <input type="hidden" name="assigned_team" id="ccSelectedStaffInput" value="">
                        <small id="cc-staff-error" class="text-danger d-none"><?php echo $lang['Please select at least one team member or admin'] ?? 'Please select at least one team member or admin.'; ?></small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Permissions -->
              <div class="tab-pane fade" id="cc-permissions-pane" role="tabpanel">
                <div class="row">
                  <div class="col-md-12">
                    <div class="card permission-box" style="border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.12);">
                      <div class="card-body">
                        <div class="row">
                          <div class="col-md-12">
                            <div class="permission-item d-flex col-gap">
                              <div class="checkbox-wrapper-6">
                                <input class="tgl tgl-light" id="cc_can_create_task" name="permissions[can_create_task]" type="checkbox"/>
                                <label class="tgl-btn" for="cc_can_create_task"></label>
                              </div>
                              <div>
                                <label for="cc_can_create_task" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                  <?php echo $lang['Create Tasks'] ?? 'Create tasks'; ?>
                                  <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can create new tasks in their projects'] ?? 'Client can create new tasks in their projects', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                </label>
                              </div>
                            </div>

                            <div class="permission-item d-flex col-gap">
                              <div class="checkbox-wrapper-6">
                                <input class="tgl tgl-light" id="cc_can_delete_task" name="permissions[can_delete_task]" type="checkbox"/>
                                <label class="tgl-btn" for="cc_can_delete_task"></label>
                              </div>
                              <div>
                                <label for="cc_can_delete_task" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                  <?php echo $lang['Delete Tasks'] ?? 'Delete tasks'; ?>
                                  <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can remove tasks from their projects'] ?? 'Client can remove tasks from their projects', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                </label>
                              </div>
                            </div>

                            <div class="permission-item d-flex col-gap">
                              <div class="checkbox-wrapper-6">
                                <input class="tgl tgl-light" id="cc_can_change_status" name="permissions[can_change_status]" type="checkbox"/>
                                <label class="tgl-btn" for="cc_can_change_status"></label>
                              </div>
                              <div>
                                <label for="cc_can_change_status" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                  <?php echo $lang['Change Task Status'] ?? 'Change task status'; ?>
                                  <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can move tasks between columns (To Do, In Progress, etc.)'] ?? 'Client can move tasks between columns (To Do, In Progress, etc.)', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                </label>
                              </div>
                            </div>

                            <div class="permission-item d-flex col-gap">
                              <div class="checkbox-wrapper-6">
                                <input class="tgl tgl-light" id="cc_can_update_task" name="permissions[can_update_task]" type="checkbox"/>
                                <label class="tgl-btn" for="cc_can_update_task"></label>
                              </div>
                              <div>
                                <label for="cc_can_update_task" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                  <?php echo $lang['Update Tasks'] ?? 'Update tasks'; ?>
                                  <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can edit task details, titles, and descriptions'] ?? 'Client can edit task details, titles, and descriptions', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                </label>
                              </div>
                            </div>

                            <div class="permission-item d-flex col-gap">
                              <div class="checkbox-wrapper-6">
                                <input class="tgl tgl-light" id="cc_can_assign_members" name="permissions[can_assign_members]" type="checkbox"/>
                                <label class="tgl-btn" for="cc_can_assign_members"></label>
                              </div>
                              <div>
                                <label for="cc_can_assign_members" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                  <?php echo $lang['Assign Members'] ?? 'Assign members'; ?>
                                  <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can assign tasks to staff members'] ?? 'Client can assign tasks to staff members', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                </label>
                              </div>
                            </div>

                            <div class="permission-item d-flex col-gap">
                              <div class="checkbox-wrapper-6">
                                <input class="tgl tgl-light" id="cc_can_view_milestones" name="permissions[can_view_milestones]" type="checkbox"/>
                                <label class="tgl-btn" for="cc_can_view_milestones"></label>
                              </div>
                              <div>
                                <label for="cc_can_view_milestones" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                  <?php echo $lang['View Milestones'] ?? 'View payments & invoices'; ?>
                                  <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Client can view project milestones and payment information'] ?? 'Client can view project milestones and payment information', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                </label>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="form-group submit-box" id="cc-account-submit-box">
              <div class="d-flex col-gap align-items-center">
                <div class="checkbox-wrapper-6">
                  <input class="tgl tgl-light" id="ccEmailNotification" name="email_notification" type="checkbox" checked/>
                  <label class="tgl-btn" for="ccEmailNotification"></label>
                </div>
                <div>
                  <label for="ccEmailNotification" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                    <?php echo $lang['Email Notification'] ?? 'Email notification'; ?>
                    <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Notify the client of account creation'] ?? 'Notify the client of account creation', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                  </label>
                </div>
              </div>
              <div>
                <button type="button" id="cc-next-to-permissions" class="bigbutton"><?php echo $lang['Next'] ?? 'Next'; ?></button>
              </div>
            </div>

            <div class="form-group submit-box" id="cc-permissions-submit-box" style="display:none;">
              <div class="d-flex col-gap align-items-center">
                <div class="checkbox-wrapper-6">
                  <input class="tgl tgl-light" id="ccEmailNotification2" name="email_notification" type="checkbox" checked/>
                  <label class="tgl-btn" for="ccEmailNotification2"></label>
                </div>
                <div>
                  <label for="ccEmailNotification2" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                    <?php echo $lang['Email Notification'] ?? 'Email notification'; ?>
                    <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Notify the client of account creation'] ?? 'Notify the client of account creation', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                  </label>
                </div>
              </div>
              <div><button type="submit" id="cc-create-client-btn" class="bigbutton"><?php echo $lang['Create Account'] ?? 'Create Account'; ?></button></div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>


