<?php
/*
 * Calendar Event Modal Template
 * Location: templates/calendar-event-modal.php
 */
$calendarParticipantUsers = [];
$allUsersForEvent = User::findBySql("SELECT id, firstName, accountStatus FROM users ORDER BY firstName ASC");
if ($allUsersForEvent) {
    foreach ($allUsersForEvent as $u) {
        $status = (int)$u->accountStatus;
        if (!in_array($status, [1, 2, 3], true)) {
            continue;
        }
        $fullName = trim((string)($u->firstName ?? ''));
        if ($fullName === '') {
            $fullName = 'User ' . (int)$u->id;
        }
        $calendarParticipantUsers[] = [
            'id' => (int)$u->id,
            'name' => $fullName,
            'role' => $status === 1 ? 'ADMIN' : ($status === 2 ? 'CLIENT' : 'STAFF'),
            'image' => getUserAvatarHtml((int)$u->id, $u->firstName ?? '', '', 24, 24, 'rounded-circle', $fullName)
        ];
    }
}
?>
<div class="modal fade" id="calendarEventModal" tabindex="-1" aria-labelledby="calendarEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="calendarEventForm">
                <div class="modal-header">
                    <h4 class="card-title" id="calendarEventModalLabel"><?php echo $lang['Create event'] ?? 'Create event'; ?></h4>
                    <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>"><?php echo ts_icon('close'); ?></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?php echo $lang['Date']; ?></label>
                            <input type="date" name="event_date" id="calendar_event_date" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?php echo $lang['Start']; ?></label>
                            <input type="time" name="start_time" id="calendar_start_time" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?php echo $lang['End']; ?></label>
                            <input type="time" name="end_time" id="calendar_end_time" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang['Event name'] ?? 'Event name'; ?></label>
                        <input type="text" name="title" id="calendar_event_title" class="form-control" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo $lang['Location']; ?></label>
                            <input type="text" name="location_label" id="calendar_location" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo $lang['Create in'] ?? 'Create in'; ?></label>
                            <input type="text" name="team_label" id="calendar_team_label" class="form-control" placeholder="<?php echo $lang['Team'] ?? 'Team'; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?php echo $lang['Participants'] ?? 'Participants'; ?></label>
                        <div class="dropdown">
                            <button
                                class="field-btn dropdown-toggle w-100 text-start"
                                type="button"
                                id="eventParticipantsDropdownBtn"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                            >
                                <span id="eventParticipantsDropdownBtnText"><?php echo $lang['Add participants'] ?? 'Add participants'; ?></span>
                            </button>
                            <ul
                                class="dropdown-menu w-100"
                                aria-labelledby="eventParticipantsDropdownBtn"
                                id="eventParticipantsDropdownMenu"
                                style="max-height: 250px; overflow-y: auto;"
                            >
                                <li class="px-3 py-2" style="position: sticky; top: 0; z-index: 10; border-bottom: 1px solid var(--border-color); background: var(--card-body-color);">
                                    <input
                                        type="text"
                                        id="eventParticipantsSearchInput"
                                        class="form-control"
                                        placeholder="<?php echo $lang['Search'] ?? 'Search'; ?>..."
                                        autocomplete="off"
                                        style="width: 100%;"
                                    >
                                </li>
                                <li class="px-3 py-1" id="noParticipantsFound" style="display: none; color: #999; font-style: italic;">
                                    <?php echo $lang['No users found'] ?? 'No users found'; ?>
                                </li>
                                <div id="eventParticipantsOptionsList"></div>
                            </ul>
                        </div>
                        <input type="hidden" name="participants" id="calendar_participants">
                    </div>

                    <div class="mb-0">
                        <label class="form-label"><?php echo $lang['Event agenda'] ?? 'Event agenda'; ?></label>
                        <textarea name="description" id="calendar_description" rows="5" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn border-btn-a" data-bs-dismiss="modal"><?php echo $lang['Cancel']; ?></button>
                    <button
                        type="submit"
                        class="btn primary-btn"
                        id="calendarEventSubmitBtn"
                        data-create-label="<?php echo htmlspecialchars($lang['Create event'] ?? 'Create event', ENT_QUOTES, 'UTF-8'); ?>"
                        data-update-label="<?php echo htmlspecialchars($lang['Update event'] ?? 'Update event', ENT_QUOTES, 'UTF-8'); ?>"
                    ><?php echo $lang['Create event'] ?? 'Create event'; ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$__calPartFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $__calPartFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$__calPartJson = json_encode($calendarParticipantUsers, $__calPartFlags);
if ($__calPartJson === false) {
    $__calPartJson = '[]';
}
?>
<script>
window.calendarParticipantUsers = <?php echo $__calPartJson; ?>;
</script>
