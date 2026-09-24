<?php
if (!isset($url)) {
    $url = '';
}
$emailNotifAcct = (int)($accountStatus ?? ($_SESSION['accountStatus'] ?? 0));
$emailNotifCanResetToSystem = ($emailNotifAcct === 1);
?>
<div class="modal fade" id="emailNotificationModal" tabindex="-1" aria-labelledby="emailNotificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-email-notif">
        <div class="modal-content chat-email-modal-shell">
            <div class="modal-header">
                <h5 class="modal-title" id="emailNotificationModalLabel"><?php echo htmlspecialchars($lang['Notifications Settings'] ?? 'Notifications settings'); ?></h5>
                <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="Close">
                    <?php echo ts_icon('close'); ?>
                </button>
            </div>
            <div class="modal-body chat-email-modal-body">
                <div id="emailNotifError" class="alert alert-danger d-none mb-3" role="alert"></div>
                <p class="small d-none mb-3 text-muted" id="emailNotifReadonly"><?php echo htmlspecialchars($lang['View only: only administrators and team can change these'] ?? 'View only.'); ?></p>

                <div id="emailNotifContextBar" class="chat-email-context-bar">
                    <span id="emailNotifContextKind" class="chat-email-context-kind"></span>
                    <span id="emailNotifContextId" class="chat-email-context-id"></span>
                </div>

                <div class="chat-email-notif-list border-none">
                    <div class="chat-email-notif-row" data-email-notif-row="admin">
                        <div class="chat-email-notif-icon chat-email-notif-icon--admin" aria-hidden="true">
                            <?php echo ts_icon('clock'); ?>
                        </div>
                        <div class="chat-email-notif-copy">
                            <div class="chat-email-notif-title"><?php echo htmlspecialchars($lang['Admin'] ?? 'Admin'); ?></div>
                            <p class="chat-email-notif-desc"><?php echo htmlspecialchars($lang['Notify administrators about project activity.'] ?? 'Notify administrators about project activity.'); ?></p>
                        </div>
                        <div class="chat-email-notif-toggle">
                            <div class="checkbox-wrapper-6">
                                <input class="tgl tgl-light" id="emailNotifAdmin" name="emailNotifAdmin" type="checkbox" value="1" />
                                <label class="tgl-btn" for="emailNotifAdmin"></label>
                            </div>
                        </div>
                    </div>

                    <div class="chat-email-notif-row" data-email-notif-row="staff">
                        <div class="chat-email-notif-icon chat-email-notif-icon--staff" aria-hidden="true">
                            <?php echo ts_icon('user-group'); ?>
                        </div>
                        <div class="chat-email-notif-copy">
                            <div class="chat-email-notif-title"><?php echo htmlspecialchars($lang['Staff'] ?? 'Staff'); ?></div>
                            <p class="chat-email-notif-desc"><?php echo htmlspecialchars($lang['Notify staff members about relevant updates.'] ?? 'Notify staff members about relevant updates.'); ?></p>
                        </div>
                        <div class="chat-email-notif-toggle">
                            <div class="checkbox-wrapper-6">
                                <input class="tgl tgl-light" id="emailNotifStaff" name="emailNotifStaff" type="checkbox" value="1" />
                                <label class="tgl-btn" for="emailNotifStaff"></label>
                            </div>
                        </div>
                    </div>

                    <div class="chat-email-notif-row" data-email-notif-row="client">
                        <div class="chat-email-notif-icon chat-email-notif-icon--client" aria-hidden="true">
                            <?php echo ts_icon('profile'); ?>
                        </div>
                        <div class="chat-email-notif-copy">
                            <div class="chat-email-notif-title"><?php echo htmlspecialchars($lang['Client'] ?? 'Client'); ?></div>
                            <p class="chat-email-notif-desc"><?php echo htmlspecialchars($lang['Notify clients about project updates and changes.'] ?? 'Notify clients about project updates and changes.'); ?></p>
                        </div>
                        <div class="chat-email-notif-toggle">
                            <div class="checkbox-wrapper-6">
                                <input class="tgl tgl-light" id="emailNotifClient" name="emailNotifClient" type="checkbox" value="1" />
                                <label class="tgl-btn" for="emailNotifClient"></label>
                            </div>
                        </div>
                    </div>

                    <div class="chat-email-notif-row chat-email-notif-row--mention" data-email-notif-row="mention">
                        <div class="chat-email-notif-icon chat-email-notif-icon--mention chat-email-notif-at-symbol" aria-hidden="true">@</div>
                        <div class="chat-email-notif-copy">
                            <div class="chat-email-notif-title"><?php echo htmlspecialchars($lang['@Mention emails'] ?? '@Mention emails'); ?></div>
                            <p class="chat-email-notif-desc"><?php echo htmlspecialchars($lang['Applies when a user is @mentioned; not a separate audience for all messages.'] ?? 'Sends when someone @mentions a user, if normal email is off for their role.'); ?></p>
                        </div>
                        <div class="chat-email-notif-toggle">
                            <div class="checkbox-wrapper-6">
                                <input class="tgl tgl-light" id="emailNotifMention" name="emailNotifMention" type="checkbox" value="1" />
                                <label class="tgl-btn" for="emailNotifMention"></label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer chat-email-modal-footer d-flex justify-content-between align-items-center col-gap-10 flex-wrap">
                <?php if ($emailNotifCanResetToSystem): ?>
                <button type="button" class="btn border-btn-a d-inline-flex align-items-center gap-2" id="emailNotifResetGlobal" data-dismiss-type="soft">
                    <?php echo ts_icon('refresh'); ?>
                    <?php echo htmlspecialchars($lang['Use system defaults'] ?? 'Use system defaults'); ?>
                </button>
                <?php endif; ?>
                <div class="d-flex col-gap-10">
                    <button type="button" class="btn primary-btn chat-email-btn-save" id="emailNotifSave"><?php echo htmlspecialchars($lang['Save changes'] ?? 'Save changes'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (isset($lang) && is_array($lang)) { ?>
<script>
window.comonEmailNotifSelfTitle = <?php echo json_encode(isset($lang['Email Notifications']) ? $lang['Email Notifications'] : 'Email Notifications', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
window.comonEmailNotifSelfDesc = <?php echo json_encode(isset($lang['Receive email updates about your activity and important changes.']) ? $lang['Receive email updates about your activity and important changes.'] : 'Receive email updates about your activity and important changes.', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<?php } ?>
