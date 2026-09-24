<?php
if (!isset($lang) || !is_array($lang)) {
    $lang = [];
}
$tr = function ($key, $fallback) use ($lang) {
    return isset($lang[$key]) && $lang[$key] !== '' ? $lang[$key] : $fallback;
};
$enc = function ($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
$statusOptions = [
    'todo' => $tr('To Do', 'To do'),
    'inprogress' => $tr('In Progress', 'In progress'),
    'review' => $tr('Review', 'Review'),
    'done' => $tr('Done', 'Done'),
];
$statusColors = [
    'todo' => 'color-todo-bg',
    'inprogress' => 'color-inprogress-bg',
    'review' => 'color-review-bg',
    'done' => 'color-done-bg',
];
$fieldTip = function ($text) use ($enc) {
    return '<span class="ms-1 flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $enc($text) . '" onclick="event.preventDefault(); event.stopPropagation();">'
        . ts_icon('info', 'w-2 text-muted')
        . '</span>';
};
$showEstimatedTime = !function_exists('tasksession_time_tracking_enabled') || tasksession_time_tracking_enabled();
$estPresets = [
    900 => ['time_estimate_15m', '15 min'],
    1800 => ['time_estimate_30m', '30 min'],
    2700 => ['time_estimate_45m', '45 min'],
    3600 => ['time_estimate_1h', '1 hour'],
    5400 => ['time_estimate_1h30', '1:30 hours'],
    7200 => ['time_estimate_2h', '2:00 hours'],
    10800 => ['time_estimate_3h', '3:00 hours'],
    14400 => ['time_estimate_4h', '4:00 hours'],
    18000 => ['time_estimate_5h', '5:00 hours'],
    21600 => ['time_estimate_6h', '6:00 hours'],
    25200 => ['time_estimate_7h', '7:00 hours'],
    28800 => ['time_estimate_8h', '8:00 hours'],
    32400 => ['time_estimate_9h', '9:00 hours'],
    36000 => ['time_estimate_10h', '10:00 hours'],
];
?>
<div class="modal fade team-group-modal tasksession-timer-complete-modal" id="taskRepeatModal" tabindex="-1" aria-labelledby="taskRepeatModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg" style="height: auto;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="taskRepeatModalLabel"><?php echo $enc($tr('Set repeats', 'Set repeats')); ?></h5>
        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="<?php echo $enc($tr('Close', 'Close')); ?>">
          <?php echo ts_icon('close'); ?>
        </button>
      </div>
      <div class="modal-body pd-30">
        <div class="row g-4 align-items-stretch">
          <div class="col-lg-7" id="taskRepeatFieldsCol">
            <div class="media-replace-scope mb-3" role="radiogroup" aria-label="<?php echo $enc($tr('Repeat mode', 'Repeat mode')); ?>">
              <input type="radio" class="btn-check" name="taskRepeatModalMode" id="taskRepeatModeTime" value="time_based" checked>
              <label class="media-replace-scope-option" for="taskRepeatModeTime"><?php echo $enc($tr('Time-based', 'Time-based')); ?></label>
              <input type="radio" class="btn-check" name="taskRepeatModalMode" id="taskRepeatModeAfter" value="after_completion">
              <label class="media-replace-scope-option" for="taskRepeatModeAfter"><?php echo $enc($tr('After completion', 'After completion')); ?></label>
            </div>
            <p class="small text-muted mb-3" id="taskRepeatModeHelp"><?php echo $enc($tr('Creates a new task on a specific date, regardless of previous task completion.', 'Creates a new task on a specific date, regardless of previous task completion.')); ?></p>

            <div id="taskRepeatPaneTime">
              <div class="row mb-2 align-items-center tasksession-timer-complete-field">
                <label class="col-5 col-form-label small mb-0" for="taskRepeatCount"><?php echo $enc($tr('Repeat every', 'Repeat every')); ?></label>
                <div class="col-7 d-flex align-items-center col-gap-5 flex-nowrap">
                  <div class="field-btn d-inline-flex align-items-stretch p-0 flex-shrink-0">
                    <button type="button" class="d-flex align-items-center justify-content-center border-0 bg-transparent px-2" id="taskRepeatCountMinus" aria-label="-"><?php echo ts_icon('minus', 'w-2 text-muted'); ?></button>
                    <input type="number" min="1" max="365" class="border-0 bg-transparent text-center p-0" id="taskRepeatCount" value="1" style="width: 28px;">
                    <button type="button" class="d-flex align-items-center justify-content-center border-0 bg-transparent px-2" id="taskRepeatCountPlus" aria-label="+"><?php echo ts_icon('plus', 'w-2 text-muted'); ?></button>
                  </div>
                  <div class="dropdown flex-shrink-0">
                    <button class="field-btn dropdown-toggle text-start d-flex align-items-center justify-content-between col-gap-5" type="button" id="taskRepeatUnitBtn" data-bs-toggle="dropdown">
                      <span id="taskRepeatUnitLabel"><?php echo $enc($tr('Day', 'Day')); ?></span>
                      <?php echo ts_icon('chevron-down', 'flex-shrink-0 w-2 text-muted'); ?>
                    </button>
                    <ul class="dropdown-menu" id="taskRepeatUnitMenu">
                      <li><button type="button" class="dropdown-item js-task-repeat-unit" data-unit="day"><?php echo $enc($tr('Day', 'Day')); ?></button></li>
                      <li><button type="button" class="dropdown-item js-task-repeat-unit" data-unit="workday"><?php echo $enc($tr('Workday', 'Workday')); ?></button></li>
                      <li><button type="button" class="dropdown-item js-task-repeat-unit" data-unit="week"><?php echo $enc($tr('Week', 'Week')); ?></button></li>
                      <li><button type="button" class="dropdown-item js-task-repeat-unit" data-unit="month"><?php echo $enc($tr('Month', 'Month')); ?></button></li>
                      <li><button type="button" class="dropdown-item js-task-repeat-unit" data-unit="year"><?php echo $enc($tr('Year', 'Year')); ?></button></li>
                    </ul>
                  </div>
                </div>
              </div>
              <div class="form-group mb-3">
                <label class="d-flex align-items-center col-gap-5 mb-0">
                  <input type="checkbox" id="taskRepeatSkipWeekends">
                  <span><?php echo $enc($tr('Skip weekends', 'Skip weekends')); ?></span>
                </label>
              </div>
            </div>

            <input type="hidden" id="taskRepeatStartFrom" value="">

            <hr class="dropdown-divider">

            <div class="row mb-2 align-items-center tasksession-timer-complete-field">
              <label class="col-5 col-form-label small mb-0 d-flex align-items-center col-gap-5"><?php echo $enc($tr('Due date', 'Due date')); ?><?php echo $fieldTip($tr('Sets the due date of each new repeated task, relative to the date that task is created.', 'Sets the due date of each new repeated task, relative to the date that task is created.')); ?></label>
              <div class="col-7">
                <div class="dropdown dropup w-100">
                  <button class="field-btn dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between" type="button" id="taskRepeatDueBtn" data-bs-toggle="dropdown">
                    <span id="taskRepeatDueLabel"><?php echo $enc($tr('Same day', 'Same day')); ?></span>
                    <?php echo ts_icon('chevron-down', 'flex-shrink-0 ms-2 w-2 text-muted'); ?>
                  </button>
                  <ul class="dropdown-menu w-100 dropdown-scroll">
                    <li><button type="button" class="dropdown-item js-task-repeat-due" data-due=""><?php echo $enc($tr('Not set', 'Not set')); ?></button></li>
                    <li><button type="button" class="dropdown-item js-task-repeat-due" data-due="0"><?php echo $enc($tr('Same day', 'Same day')); ?></button></li>
                    <li><button type="button" class="dropdown-item js-task-repeat-due" data-due="1"><?php echo $enc($tr('Next day', 'Next day')); ?></button></li>
                    <li><button type="button" class="dropdown-item js-task-repeat-due" data-due="2"><?php echo $enc($tr('in 2 days', 'in 2 days')); ?></button></li>
                    <li><button type="button" class="dropdown-item js-task-repeat-due" data-due="3"><?php echo $enc($tr('in 3 days', 'in 3 days')); ?></button></li>
                    <li><button type="button" class="dropdown-item js-task-repeat-due" data-due="4"><?php echo $enc($tr('in 4 days', 'in 4 days')); ?></button></li>
                    <li><button type="button" class="dropdown-item js-task-repeat-due" data-due="5"><?php echo $enc($tr('in 5 days', 'in 5 days')); ?></button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                      <button type="button" class="dropdown-item d-flex align-items-center" id="taskRepeatDueCustomBtn">
                        <?php echo ts_icon('settings', 'me-2 tasksession-timer-log-menu-ico'); ?>
                        <?php echo $enc($tr('Custom', 'Custom')); ?>
                      </button>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            <div class="row mb-2 align-items-center tasksession-timer-complete-field">
              <label class="col-5 col-form-label small mb-0 d-flex align-items-center col-gap-5"><?php echo $enc($tr('Default status', 'Default status')); ?><?php echo $fieldTip($tr('Status assigned to each new repeated task when it is created.', 'Status assigned to each new repeated task when it is created.')); ?></label>
              <div class="col-7">
                <div class="dropdown dropup w-100">
                  <button class="field-btn dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between" type="button" id="taskRepeatStatusBtn" data-bs-toggle="dropdown">
                    <span class="d-flex align-items-center col-gap-5 min-w-0">
                      <span id="taskRepeatStatusSwatch" class="d-inline-block flex-shrink-0 rounded <?php echo $enc($statusColors['todo']); ?>" style="width:16px;height:16px;" aria-hidden="true"></span>
                      <span id="taskRepeatStatusLabel"><?php echo $enc($statusOptions['todo']); ?></span>
                    </span>
                    <?php echo ts_icon('chevron-down', 'flex-shrink-0 ms-2 w-2 text-muted'); ?>
                  </button>
                  <ul class="dropdown-menu w-100">
                    <?php foreach ($statusOptions as $sk => $sl): ?>
                    <li>
                      <button type="button" class="dropdown-item d-flex align-items-center js-task-repeat-status" data-status="<?php echo $enc($sk); ?>">
                        <span class="d-inline-block flex-shrink-0 rounded me-2 <?php echo $enc($statusColors[$sk] ?? 'color-todo-bg'); ?>" style="width:16px;height:16px;" aria-hidden="true"></span>
                        <span><?php echo $enc($sl); ?></span>
                      </button>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
            </div>
            <?php if ($showEstimatedTime): ?>
            <div class="row mb-2 align-items-center tasksession-timer-complete-field">
              <label class="col-5 col-form-label small mb-0 d-flex align-items-center col-gap-5" for="taskRepeatEstBtn"><?php echo $enc($tr('estimated_time', 'Estimated time')); ?><?php echo $fieldTip($tr('Estimated time assigned to each new repeated task.', 'Estimated time assigned to each new repeated task.')); ?></label>
              <div class="col-7">
                <div class="dropdown dropup w-100">
                  <button class="field-btn dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between" type="button" id="taskRepeatEstBtn" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="d-flex align-items-center col-gap-5 min-w-0">
                      <?php echo ts_icon('clock', 'flex-shrink-0 w-2 text-muted'); ?>
                      <span id="taskRepeatEstLabel" class="text-truncate">0h</span>
                    </span>
                    <?php echo ts_icon('chevron-down', 'flex-shrink-0 ms-2 w-2 text-muted'); ?>
                  </button>
                  <ul class="dropdown-menu w-100 dropdown-scroll" id="taskRepeatEstMenu">
                    <li>
                      <button type="button" class="dropdown-item js-task-repeat-est" data-est-value="">0h</button>
                    </li>
                    <?php foreach ($estPresets as $sec => $meta): ?>
                    <li>
                      <button type="button" class="dropdown-item js-task-repeat-est" data-est-value="<?php echo (int) $sec; ?>"><?php echo $enc($tr($meta[0], $meta[1])); ?></button>
                    </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <input type="hidden" id="taskRepeatEstSeconds" value="">
              </div>
            </div>
            <?php endif; ?>
            <div class="row mb-0 align-items-center tasksession-timer-complete-field" id="taskRepeatEndsRow">
              <label class="col-5 col-form-label small mb-0 d-flex align-items-center col-gap-5"><?php echo $enc($tr('Ends', 'Ends')); ?><?php echo $fieldTip($tr('When repeating should stop. Never keeps creating tasks; pick a date to stop after that day.', 'When repeating should stop. Never keeps creating tasks; pick a date to stop after that day.')); ?></label>
              <div class="col-7">
                <div class="dropdown dropup w-100">
                  <button class="field-btn dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between" type="button" id="taskRepeatEndsBtn" data-bs-toggle="dropdown">
                    <span class="d-flex align-items-center col-gap-5 min-w-0">
                      <?php echo ts_icon('calendar', 'flex-shrink-0 w-2 text-muted'); ?>
                      <span id="taskRepeatEndsLabel" class="text-truncate"><?php echo $enc($tr('Never', 'Never')); ?></span>
                    </span>
                    <?php echo ts_icon('chevron-down', 'flex-shrink-0 ms-2 w-2 text-muted'); ?>
                  </button>
                  <ul class="dropdown-menu w-100 p-1">
                    <li>
                      <button type="button" class="dropdown-item js-task-repeat-ends-never"><?php echo $enc($tr('Never', 'Never')); ?></button>
                    </li>
                    <li class="px-2 py-2">
                      <input type="date" class="field-btn w-100" id="taskRepeatEndsAt">
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-5 d-flex flex-column" id="taskRepeatPreviewCol">
            <h6 class="mb-3 font-weight-bold"><?php echo $enc($tr('Scheduled repeats', 'Scheduled repeats')); ?></h6>
            <div id="taskRepeatCalendarPreview" class="scroll-bar"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-top d-flex justify-content-between align-items-center flex-wrap col-gap-5">
        <button type="button" class="btn text-danger d-flex align-items-center col-gap-5" id="taskRepeatEndRepeats">
          <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico flex-shrink-0'); ?>
          <span><?php echo $enc($tr('End repeats', 'End repeats')); ?></span>
        </button>
        <div class="d-flex align-items-center col-gap-5">
          <button type="button" class="btn border-btn-a" data-bs-dismiss="modal"><?php echo $enc($tr('Cancel', 'Cancel')); ?></button>
          <button type="button" class="primary-btn" id="taskRepeatModalSave"><?php echo $enc($tr('Save', 'Save')); ?></button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade team-group-modal tasksession-timer-complete-modal" id="taskRepeatDueCustomModal" tabindex="-1" aria-labelledby="taskRepeatDueCustomModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="taskRepeatDueCustomModalLabel"><?php echo $enc($tr('Set due date', 'Set due date')); ?></h5>
        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="<?php echo $enc($tr('Close', 'Close')); ?>">
          <?php echo ts_icon('close'); ?>
        </button>
      </div>
      <div class="modal-body pd-30">
        <div class="d-flex align-items-center col-gap-20 flex-nowrap">
          <label class="col-form-label small mb-0 font-weight-bold" for="taskRepeatCustomDueDays"><?php echo $enc($tr('In days', 'In days')); ?></label>
          <div class="field-btn d-inline-flex align-items-stretch p-0 flex-shrink-0">
            <button type="button" class="d-flex align-items-center justify-content-center border-0 bg-transparent px-2" id="taskRepeatCustomDueMinus" aria-label="-"><?php echo ts_icon('minus', 'w-2 text-muted'); ?></button>
            <input type="number" min="0" max="365" class="border-0 bg-transparent text-center p-0" id="taskRepeatCustomDueDays" value="1" style="width: 28px;">
            <button type="button" class="d-flex align-items-center justify-content-center border-0 bg-transparent px-2" id="taskRepeatCustomDuePlus" aria-label="+"><?php echo ts_icon('plus', 'w-2 text-muted'); ?></button>
          </div>
        </div>
      </div>
      <div class="modal-footer border-top d-flex justify-content-end align-items-center col-gap-5 flex-wrap">
        <button type="button" class="btn border-btn-a" data-bs-dismiss="modal"><?php echo $enc($tr('Cancel', 'Cancel')); ?></button>
        <button type="button" class="primary-btn" id="taskRepeatCustomDueSave"><?php echo $enc($tr('Save', 'Save')); ?></button>
      </div>
    </div>
  </div>
</div>
