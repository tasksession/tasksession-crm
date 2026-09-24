<?php
/**
 * Set repeats dropdown (add/edit task). Optional $taskRepeatInitial (array|null).
 */
if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}
$tr = function ($key, $fallback) use ($lang) {
    return isset($lang[$key]) && $lang[$key] !== '' ? $lang[$key] : $fallback;
};
$repeatInitial = isset($taskRepeatInitial) && is_array($taskRepeatInitial) ? $taskRepeatInitial : null;
$repeatEnabled = !empty($repeatInitial['enabled']);
$repeatMode = $repeatEnabled ? (string) ($repeatInitial['mode'] ?? '') : '';
$repeatUnit = (string) ($repeatInitial['interval_unit'] ?? 'day');
$repeatCount = max(1, (int) ($repeatInitial['interval_count'] ?? 1));

$label = $tr('Does not repeat', 'Does not repeat');
if ($repeatEnabled) {
    if ($repeatMode === 'after_completion') {
        $label = $tr('After completion', 'After completion');
    } elseif ($repeatCount === 1) {
        $map = [
            'day' => $tr('Every day', 'Every day'),
            'workday' => $tr('Every workday', 'Every workday'),
            'week' => $tr('Every week', 'Every week'),
            'month' => $tr('Every month', 'Every month'),
            'year' => $tr('Every year', 'Every year'),
        ];
        $label = $map[$repeatUnit] ?? $tr('Customize repeat', 'Customize repeat');
    } else {
        $label = $tr('Customize repeat', 'Customize repeat');
    }
}

$enc = function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
$dueOffsetVal = ($repeatInitial && array_key_exists('due_offset_days', $repeatInitial) && $repeatInitial['due_offset_days'] !== null)
    ? (string) (int) $repeatInitial['due_offset_days']
    : '';
?>
<div class="form-group" id="taskRepeatFieldWrap">
    <label for="setRepeatsDropdownBtn"><?php echo $enc($tr('Set repeats', 'Set repeats')); ?></label>
    <div class="dropdown">
        <button
            class="field-btn dropdown-toggle w-100 text-start"
            type="button"
            id="setRepeatsDropdownBtn"
            data-bs-toggle="dropdown"
            aria-expanded="false"
        >
            <span id="setRepeatsDropdownLabel"><?php echo $enc($label); ?></span>
        </button>
        <ul class="dropdown-menu w-100 dropdown-scroll p-1" aria-labelledby="setRepeatsDropdownBtn" id="setRepeatsDropdownMenu">
            <li>
                <button type="button" class="dropdown-item js-task-repeat-preset d-flex align-items-center" data-repeat="off">
                    <?php echo ts_icon('x-circle', 'me-2 tasksession-timer-log-menu-ico flex-shrink-0'); ?>
                    <span><?php echo $enc($tr('Does not repeat', 'Does not repeat')); ?></span>
                </button>
            </li>
            <li>
                <button type="button" class="dropdown-item js-task-repeat-preset d-flex align-items-center" data-repeat="after_completion">
                    <?php echo ts_icon('refresh', 'me-2 tasksession-timer-log-menu-ico flex-shrink-0'); ?>
                    <span><?php echo $enc($tr('After completion', 'After completion')); ?></span>
                    <span
                        class="ms-auto flex-shrink-0"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="<?php echo $enc($tr('Creates a new task once the previous one is completed.', 'Creates a new task once the previous one is completed.')); ?>"
                        onclick="event.preventDefault(); event.stopPropagation();"
                    ><?php echo ts_icon('info'); ?></span>
                </button>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <button type="button" class="dropdown-item text-muted" disabled tabindex="-1">
                    <?php echo $enc($tr('Time-based', 'Time-based')); ?>
                </button>
            </li>
            <li><button type="button" class="dropdown-item js-task-repeat-preset d-flex align-items-center" data-repeat="day"><?php echo $enc($tr('Every day', 'Every day')); ?></button></li>
            <li><button type="button" class="dropdown-item js-task-repeat-preset d-flex align-items-center" data-repeat="workday"><?php echo $enc($tr('Every workday', 'Every workday')); ?></button></li>
            <li><button type="button" class="dropdown-item js-task-repeat-preset d-flex align-items-center" data-repeat="week"><?php echo $enc($tr('Every week', 'Every week')); ?></button></li>
            <li><button type="button" class="dropdown-item js-task-repeat-preset d-flex align-items-center" data-repeat="month"><?php echo $enc($tr('Every month', 'Every month')); ?></button></li>
            <li><button type="button" class="dropdown-item js-task-repeat-preset d-flex align-items-center" data-repeat="year"><?php echo $enc($tr('Every year', 'Every year')); ?></button></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <button type="button" class="dropdown-item js-task-repeat-customize d-flex align-items-center" data-repeat="custom">
                    <?php echo ts_icon('settings', 'me-2 tasksession-timer-log-menu-ico flex-shrink-0'); ?>
                    <span><?php echo $enc($tr('Customize repeat', 'Customize repeat')); ?></span>
                </button>
            </li>
        </ul>
    </div>
    <input type="hidden" name="repeat_enabled" id="repeat_enabled" value="<?php echo $repeatEnabled ? '1' : '0'; ?>">
    <input type="hidden" name="repeat_mode" id="repeat_mode" value="<?php echo $enc($repeatMode); ?>">
    <input type="hidden" name="repeat_interval_unit" id="repeat_interval_unit" value="<?php echo $enc($repeatUnit); ?>">
    <input type="hidden" name="repeat_interval_count" id="repeat_interval_count" value="<?php echo (int) $repeatCount; ?>">
    <input type="hidden" name="repeat_skip_weekends" id="repeat_skip_weekends" value="<?php echo !empty($repeatInitial['skip_weekends']) ? '1' : '0'; ?>">
    <input type="hidden" name="repeat_start_from" id="repeat_start_from" value="<?php echo $enc($repeatInitial['start_from'] ?? ''); ?>">
    <input type="hidden" name="repeat_due_offset_days" id="repeat_due_offset_days" value="<?php echo $enc($dueOffsetVal); ?>">
    <input type="hidden" name="repeat_default_status" id="repeat_default_status" value="<?php echo $enc($repeatInitial['default_status'] ?? 'todo'); ?>">
    <input type="hidden" name="repeat_estimated_time_seconds" id="repeat_estimated_time_seconds" value="<?php echo $enc(isset($repeatInitial['estimated_time_seconds']) && $repeatInitial['estimated_time_seconds'] !== null ? (int) $repeatInitial['estimated_time_seconds'] : ''); ?>">
    <input type="hidden" name="repeat_after_status" id="repeat_after_status" value="<?php echo $enc($repeatInitial['after_status'] ?? 'todo'); ?>">
    <input type="hidden" name="repeat_ends_at" id="repeat_ends_at" value="<?php echo $enc($repeatInitial['ends_at'] ?? ''); ?>">
</div>
<script>
window.taskRepeatInitial = <?php echo json_encode($repeatInitial, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
window.taskRepeatI18n = <?php echo json_encode([
    'off' => $tr('Does not repeat', 'Does not repeat'),
    'after_completion' => $tr('After completion', 'After completion'),
    'day' => $tr('Every day', 'Every day'),
    'workday' => $tr('Every workday', 'Every workday'),
    'week' => $tr('Every week', 'Every week'),
    'month' => $tr('Every month', 'Every month'),
    'year' => $tr('Every year', 'Every year'),
    'custom' => $tr('Customize repeat', 'Customize repeat'),
    'sameDay' => $tr('Same day', 'Same day'),
    'nextDay' => $tr('Next day', 'Next day'),
    'notSet' => $tr('Not set', 'Not set'),
    'inDays' => $tr('in %d days', 'in %d days'),
    'dayUnit' => $tr('Day', 'Day'),
    'workdayUnit' => $tr('Workday', 'Workday'),
    'weekUnit' => $tr('Week', 'Week'),
    'monthUnit' => $tr('Month', 'Month'),
    'yearUnit' => $tr('Year', 'Year'),
    'timeHelp' => $tr('Creates a new task on a specific date, regardless of previous task completion.', 'Creates a new task on a specific date, regardless of previous task completion.'),
    'afterHelp' => $tr('Creates a new task once the previous one is completed.', 'Creates a new task once the previous one is completed.'),
    'never' => $tr('Never', 'Never'),
    'endRepeats' => $tr('End repeats', 'End repeats'),
    'todo' => $tr('To Do', 'To do'),
    'inprogress' => $tr('In Progress', 'In progress'),
    'review' => $tr('Review', 'Review'),
    'done' => $tr('Done', 'Done'),
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
</script>
