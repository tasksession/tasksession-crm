<?php
/* 
* Task Sidebar Template
* Location: templates/task-sidebar.php
*/
if (defined('COMON_TASK_SIDEBAR_RENDERED_V1')) {
    return;
}
define('COMON_TASK_SIDEBAR_RENDERED_V1', true);

// Task sidebar present → allow footers to load task-chat.js / album stack
if (!defined('LOAD_TASK_CHAT_JS')) {
    define('LOAD_TASK_CHAT_JS', true);
}

$comonEmailNotifShow = false;
$uidSidebar = isset($session) && is_object($session) && isset($session->userId) ? (int) $session->userId : (int) ($_SESSION['userId'] ?? 0);
$acctSidebar = (int) ($accountStatus ?? $_SESSION['accountStatus'] ?? 0);
if ($uidSidebar > 0 && $acctSidebar > 0) {
    if (!function_exists('chat_email_notifications_entry_allowed')) {
        require_once dirname(__DIR__) . '/includes/email-notification/bootstrap.php';
        require_once dirname(__DIR__) . '/includes/email-notification/chat_email_permissions.php';
    }
    $comonEmailNotifShow = chat_email_notifications_entry_allowed($uidSidebar, $acctSidebar);
    $phpSelfSidebar = isset($_SERVER['PHP_SELF']) ? basename((string) $_SERVER['PHP_SELF']) : '';
    if ($comonEmailNotifShow && $phpSelfSidebar === 'chatting.php' && in_array($acctSidebar, array(2, 3), true)) {
        $comonEmailNotifShow = false;
    }
}
// Free edition: chat/task email notification settings UI removed
if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
    $comonEmailNotifShow = false;
}

$comonTaskSidebarCanDelete = false;
if ($uidSidebar > 0 && $acctSidebar > 0) {
    if ($acctSidebar === 1) {
        $comonTaskSidebarCanDelete = true;
    } elseif ($acctSidebar === 3) {
        if (!function_exists('has_permission')) {
            require_once dirname(__DIR__) . '/includes/permissions.php';
        }
        $comonTaskSidebarCanDelete = has_permission('task_delete');
    } elseif ($acctSidebar === 2) {
        if (!class_exists('TaskPermission')) {
            require_once dirname(__DIR__) . '/includes/task_permission.php';
        }
        $__tpSidebarDel = TaskPermission::getOrCreate($uidSidebar);
        $comonTaskSidebarCanDelete = $__tpSidebarDel && $__tpSidebarDel->can_delete_task;
    }
}
?>
<div id="task-sidebar" class="task-sidebar d-flex<?php echo (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) ? ' task-sidebar--free-no-chat' : ''; ?>">
<div class="task-detail-area">

  <div class="task-sidebar-header d-flex align-items-center col-gap-5" style="z-index: 99;">
      <button id="close-sidebar" class="close-btn" type="button" aria-label="Close"><?php echo ts_icon('close', 'w-6'); ?></button>
      <h2 id="task-title" class="mb-0 flex-grow"><?php echo $lang['Task Details']; ?></h2>
      <div class="border-btn">
        <a id="view-project-btn" href="#" target="_blank" style="display:none;">
          <?php echo $lang['View Project']; ?>
        </a>
      </div>
<div class="dropdown">
		<button class="btn-dots" type="button" data-bs-toggle="dropdown">
			<?php echo ts_icon('dots-vertical', 'w-6'); ?>
		</button>
		<ul class="dropdown-menu dropdown-menu-end">
			<li>
				<a class="dropdown-item" id="sidebar-edit-task-link" href="#" onclick="(function(){var s=document.getElementById('task-sidebar'); if(s && s.dataset && s.dataset.taskId){ window.location.href='edit_task?id='+s.dataset.taskId; }})(); return false;">
					<?php echo ts_icon('edit', 'me-2 tasksession-timer-log-menu-ico'); ?>
					<?php echo $lang['Edit Task']; ?>
				</a>
			</li>
			<li>
				<a class="dropdown-item" id="sidebar-view-project-link" href="#" style="display:none;">
					<?php echo ts_icon('info', 'me-2 tasksession-timer-log-menu-ico'); ?>
					<?php echo $lang['View Project']; ?>
				</a>
			</li>
			<?php if (!empty($comonTaskSidebarCanDelete)): ?>
			<li>
				<a class="dropdown-item text-danger" id="sidebar-delete-task-link" href="#" onclick="(function(){var s=document.getElementById('task-sidebar'); var id=s&&s.dataset&&s.dataset.taskId?s.dataset.taskId:0; if(id&&typeof deleteTask==='function'){ deleteTask(parseInt(id,10)); }})(); return false;">
					<?php echo ts_icon('delete', 'me-2 tasksession-timer-log-menu-ico'); ?>
					<?php echo $lang['Delete Task']; ?>
				</a>
			</li>
			<?php endif; ?>
			<?php if (!empty($comonEmailNotifShow)): ?>
			<li>
				<a class="dropdown-item js-task-email-notif" href="#">
					<?php echo ts_icon('emails', 'me-2 tasksession-timer-log-menu-ico'); ?>
					<?php echo isset($lang['Notifications Settings']) ? $lang['Notifications Settings'] : 'Notifications Settings'; ?>
				</a>
			</li>
			<?php endif; ?>

		</ul>
	</div>
  </div>
  
  <div class="task-sidebar-content scroll-bar">
  <!-- Loading State: thumbnail-style shimmer (solid bar + sliding highlight, no fade) -->
  <div id="task-loading" class="p-4">
    <style id="task-sidebar-skeleton-style">
      .tsk-skel {
        --radius: 8px;
        --tsk-shine: rgba(255, 255, 255, 0.75);
      }
      .tsk-skel .skel {
        position: relative;
        overflow: hidden;
        border-radius: var(--radius);
        background-color: var(--body-bg-color) !important;
      }
      @supports (background-color: color-mix(in srgb, white, black 10%)) {
        .tsk-skel .skel {
          background-color: color-mix(in srgb, var(--body-bg-color), black 10%) !important;
        }
      }
      /* Moving highlight like image/video thumb loaders */
      .tsk-skel .skel::before {
        content: "";
        position: absolute;
        inset: 0;
        width: 100%;
        transform: translateX(-100%);
        background: linear-gradient(
          100deg,
          transparent 0%,
          transparent 40%,
          var(--tsk-shine) 50%,
          transparent 60%,
          transparent 100%
        );
        animation: tskSkelMediaShine 1.25s ease-in-out infinite;
      }
      .tsk-skel .skel::after {
        content: "";
        position: absolute;
        inset: 0;
        border-radius: inherit;
        pointer-events: none;
        box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.06), inset 0 1px 0 rgba(255, 255, 255, 0.35);
      }
      @keyframes tskSkelMediaShine {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
      }
      @media (prefers-color-scheme: dark) {
        .tsk-skel {
          --tsk-shine: rgba(255, 255, 255, 0.35);
        }
        .tsk-skel .skel::after {
          box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }
      }
      @media (prefers-reduced-motion: reduce) {
        .tsk-skel .skel::before { animation: none; opacity: 0; }
      }
      .tsk-skel .line { height: 14px; margin: 10px 0; }
      .tsk-skel .line.sm { height: 10px; }
      .tsk-skel .line.lg { height: 18px; }
      .tsk-skel .row { display: flex; gap: 16px; align-items: center; }
      .tsk-skel .avatar { width: 30px; min-width: 30px; height: 30px; border-radius: 50%; margin: 0; border: none; box-shadow: none; }
      .tsk-skel .chip { height: 28px; width: 90px; border-radius: 999px; }
      .tsk-skel .block { height: 12px; border-radius: 6px; }
    </style>
    <div class="tsk-skel">
      <!-- Title -->
      <div class="skel line lg" style="width: 70%;"></div>
      <div class="skel line" style="width: 35%; margin-top:4px;"></div>
      
      <!-- Meta: Created By | Assign To | Status (match real layout) -->
      <div class="d-flex" style="margin: 30px 0px; gap:24px; align-items:flex-start;">
        <!-- Created By -->
        <div style="flex:1; min-width:0;">
          <div class="skel block" style="width:110px; height:10px; margin-bottom: 10px;"></div>
          <div class="d-flex" style="margin-top:10px; gap:12px; align-items:center;">
            <div class="skel avatar"></div>
            <div class="skel block" style="width:160px;"></div>
          </div>
        </div>

        <!-- Assign To -->
        <div style="flex:1; min-width:0;">
          <div class="skel block" style="width:100px; height:10px; margin-bottom: 10px;"></div>
          <div class="d-flex" style="gap:12px; align-items:center;">
            <div class="skel avatar"></div>
            <div class="skel chip" style="width:70px;"></div>
          </div>
        </div>

        <!-- Status -->
        <div style="flex:1; min-width:0;">
          <div class="skel block" style="width:80px; height:10px; margin-bottom: 10px;"></div>
          <div class="d-flex" style="margin-top:10px; align-items:center; justify-content:flex-start;">
            <div class="skel chip" style="width:120px;"></div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="d-flex" style="20px 0px 40px 0px;gap: 20px;height: 30px;margin-bottom: 30px;">
        <div class="skel chip" style="width:110px;"></div>
        <div class="skel chip" style="width:130px;"></div>
        <div class="skel chip" style="width:100px;"></div>
      </div>

      <!-- Description -->
      <div class="skel line" style="width: 90%;"></div>
      <div class="skel line" style="width: 95%;"></div>
      <div class="skel line" style="width: 80%;"></div>

      <!-- Subtasks header -->
      <div class="skel line" style="width: 140px;margin: 30px 0px 30px 0px;"></div>
      <!-- Subtask rows -->
      <div class="skel line" style="width: 96%; height: 44px; border-radius:12px; margin-top:10px;"></div>
      <div class="skel line" style="width: 96%; height: 44px; border-radius:12px; margin-top:10px;"></div>
    </div>
  </div>
    
    <!-- Error State -->
    <div id="task-error" class="alert alert-danger" style="display: none;">
      <i class="fa fa-exclamation-circle"></i> 
      <span id="error-message"><?php echo isset($lang['Error loading task details.']) ? $lang['Error loading task details.'] : 'Error loading task details.'; ?></span>
    </div>
    
    <!-- Content State -->
    <div id="task-content" style="display: none;">
      <div class="sidebar-content">
        <!-- Task Title -->
        <div class="task-name-section">
          <h3 id="formatted-task-title"><?php echo isset($lang['Loading task...']) ? $lang['Loading task...'] : 'Loading task...'; ?></h3>
        </div>
        
        <!-- Task Dates -->
        <div class="mb-2">
          <div class="meta-row">
            <span class="meta-label"><?php echo $lang['Starts']; ?></span>
            <span id="task-start-date" class="meta-value">-</span>
            <span class="meta-arrow">→</span>
            <span class="meta-label"><?php echo $lang['Due']; ?></span>
            <span id="task-due-date" class="meta-value date-with-edit">-</span>
          </div>
        </div>

        <!-- Task Meta Information -->
        <div class="d-flex col-gap-40">
          <!-- Task Creator -->
          <div class="task-meta">
            <div class="">
              <span class="title-head"><?php echo $lang['Created By']; ?></span>
              <div id="task-creator" class="task-team">
                <!-- Task creator will be inserted here dynamically -->
              </div>
            </div>
          </div>
          
          <!-- Task Assignee -->
          <div class="task-meta">
            <div class="">
              <span class="title-head"><?php echo $lang['Assign To']; ?></span>
              <div id="task-assigned-by" class="task-team" style="margin-top: 10px;">
                <!-- Task assignees will be inserted here dynamically -->
              </div>
            </div>
          </div>
		  
		  
		     <!-- Task Status Badge -->
          <div class="task-meta" id="task-status-section" style="display: none;">
            <div class="">
              <span class="title-head mb-3 d-block"><?php echo $lang['Status']; ?></span>
              <div class="due-badge" id="task-status-badge">
                <!-- Task status badge will be inserted here dynamically -->
              </div>
            </div>
          </div>
        </div>
        
        <!-- Task Tabs -->
        <div class="task-tabs-section">
          <div class="modal-tabs-scroll mb-3">
          <ul class="nav nav-tabs" id="taskTabs" role="tablist">
            <li class="nav-item">
              <a class="nav-link active" id="description-tab" data-toggle="tab" href="#description" role="tab" aria-controls="description" aria-selected="true">
                <?php echo $lang['Description']; ?>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" id="files-tab" data-toggle="tab" href="#files" role="tab" aria-controls="files" aria-selected="false">
                <?php echo $lang['Files & Media']; ?>
                <span class="files-count" id="files-count">0</span>
              </a>
            </li>
            <?php if (function_exists('tasksession_time_tracking_enabled') && tasksession_time_tracking_enabled()) : ?>
            <li class="nav-item">
              <a class="nav-link" id="timer-tab" data-toggle="tab" href="#timer" role="tab" aria-controls="timer" aria-selected="false">
                <?php echo htmlspecialchars(isset($lang['timer_scheduled_work']) ? $lang['timer_scheduled_work'] : 'Scheduled work'); ?>
                <span class="files-count" id="tasksession-timer-tab-count">0</span>
              </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
              <a class="nav-link" id="activities-tab" data-toggle="tab" href="#activities" role="tab" aria-controls="activities" aria-selected="false">
                <?php echo $lang['Activity'] ?? $lang['Activities'] ?? 'Activities'; ?>
                <span class="files-count d-none" id="task-activity-tab-count" aria-hidden="true">0</span>
              </a>
            </li>
    
          </ul>
          </div>
          
          <div class="tab-content" id="taskTabsContent">
            <!-- Description Tab -->
            <div class="tab-pane fade show active" id="description" role="tabpanel" aria-labelledby="description-tab">
              <div class="description-section">
                <div id="task-description" class="description-content">
                  <?php echo $lang['Loading description...']; ?>
                </div>
              </div>
        <?php
        $subtasksLangAdd = isset($lang['Add Subtask']) ? $lang['Add Subtask'] : '+ Add subtask';
        $addSubtaskBtnText = preg_replace('/^\+\s*/u', '', $subtasksLangAdd);
        if ($addSubtaskBtnText === '') {
            $addSubtaskBtnText = 'Add subtask';
        }
        $subtasksLangDescPh = isset($lang['Description optional']) ? $lang['Description optional'] : 'Description (optional)';
        $subtasksLangDueToday = isset($lang['Due Today']) ? $lang['Due Today'] : 'Due Today';
        $subtasksLangDueTomorrow = isset($lang['Due Tomorrow']) ? $lang['Due Tomorrow'] : 'Due Tomorrow';
        $subtasksLangUnassigned = isset($lang['Unassigned']) ? $lang['Unassigned'] : 'Unassigned';
        $subtasksLangCancel = isset($lang['Cancel']) ? $lang['Cancel'] : 'Cancel';
        ?>
        <div class="sub-tasks-section" id="sub-tasks-section-root">
          <div class="subtasks-header d-flex flex-wrap align-items-center justify-content-between mb-3 pb-3 border-bottom gap-15">
            <div class="subtasks-title-area d-flex flex-wrap align-items-center flex-grow col-gap-5">
              <div class="subtasks-title d-flex align-items-center col-gap-5 font-weight-bold">
                <?php echo htmlspecialchars($lang['Sub-Tasks']); ?>
                <span class="tab-badge d-inline-flex align-items-center justify-content-center px-2 border badge-pill small font-weight-bold" id="subtasksTotalCount">0</span>
              </div>
              <div class="progress-area d-flex align-items-center gap-15 flex-grow">
                <div class="progress mb-0 flex-grow" role="presentation">
                  <div class="progress-bar" id="subtasksProgressFill" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <span class="progress-text mb-0" id="subtasksProgressText">0/0</span>
              </div>
            </div>
            <div class="subtasks-header-actions d-flex align-items-center col-gap-5">
              <button type="button" class="primary-btn add-subtask-btn d-inline-flex align-items-center text-nowrap col-gap-5" id="subtask-add-toggle">
                <span>+</span> <?php echo htmlspecialchars($addSubtaskBtnText); ?>
              </button>
            </div>
          </div>
          
          <div class="sub-tasks-content" id="sub-tasks-content">
            <div class="subtask-add-panel mb-3 p-3" id="subtask-add-panel" style="display: none;">
              <div class="new-subtask-input subtask-add-form d-flex flex-column gap-15">
                <div class="subtask-form-row-top d-flex flex-wrap align-items-stretch gap-15">
                  <input type="text" class="subtask-form-name" id="new-subtask-name" placeholder="<?php echo htmlspecialchars($lang['Subtask name']); ?>" autocomplete="off">
                  <div class="subtask-assignee-select subtask-form-assignee" id="subtask-assignee-container">
                    <div class="custom-dropdown" id="new-subtask-assignee-dropdown">
                      <div class="dropdown-selected" id="assignee-selected">
                        <span class="selected-text"><?php echo htmlspecialchars($lang['Select Assignee']); ?></span>
                        <i class="fa fa-chevron-down dropdown-arrow"></i>
                      </div>
                      <div class="dropdown-options" id="assignee-options" style="display: none;">
                      </div>
                    </div>
                    <input type="hidden" id="new-subtask-assignee" value="">
                  </div>
                </div>
                <input type="date" class="subtask-date-input" id="new-subtask-date" style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0;pointer-events:none;" tabindex="-1" aria-hidden="true" title="">
                <textarea class="subtask-description-input w-100" id="new-subtask-description" rows="3" placeholder="<?php echo htmlspecialchars($subtasksLangDescPh); ?>"></textarea>
                <div class="subtask-add-panel-actions d-flex justify-content-end flex-wrap col-gap mt-1">
                  <button type="button" class="border-btn-a subtask-cancel-btn" id="new-subtask-cancel"><?php echo htmlspecialchars($subtasksLangCancel); ?></button>
                  <button type="button" class="primary-btn" id="new-subtask-save"><?php echo htmlspecialchars($lang['Save']); ?></button>
                </div>
              </div>
            </div>
            
            <div class="sub-tasks-list" id="sub-tasks-list">
            </div>
          </div>
        </div>
            </div>
            
            <!-- Files & Media Tab -->
            <div class="tab-pane fade" id="files" role="tabpanel" aria-labelledby="files-tab">
              <div class="files-section">
                <!-- File Upload Area -->
                <div class="file-upload-area">
                  <div class="upload-dropzone" id="task-file-dropzone">
                    <div class="upload-text">
                      <p><strong><?php echo $lang['Drop files here or click to select']; ?></strong></p>
                      <p class="text-muted"><?php echo $lang['JPEG, PNG, PSD, Word (.doc / .docx), Excel (.xls / .xlsx), PDF and more.']; ?></p>
                      <small class="text-muted"><?php echo $lang['Max file size: 5 MB']; ?></small>
                    </div>
                    <input type="file" id="task-file-input" multiple accept=".gif,.png,.jpg,.jpeg,.zip,.pdf,.doc,.docx,.txt,.xls,.xlsx,.pptx,.eps,.psd" style="display: none;">
                  </div>
                  
                  <!-- Upload Progress -->
                  <div class="upload-progress" id="upload-progress" style="display: none;">
                    <div class="progress">
                      <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div class="progress-text">Uploading...</div>
                  </div>
                </div>
                
                <!-- Files List -->
                <div class="files-list" id="task-files-list">
                  <div class="loading-files">
                    <i class="fa fa-spinner fa-spin"></i> <?php echo $lang['Loading files...']; ?>
                  </div>
                </div>
              </div>
            </div>

            <?php if (function_exists('tasksession_time_tracking_enabled') && tasksession_time_tracking_enabled()) :
                $tasksessionTwScheduleMoreBtn = isset($lang['timer_schedule_more_work']) ? $lang['timer_schedule_more_work'] : '+ Schedule more work';
                $tasksessionTwScheduleMoreModalTitle = isset($lang['timer_schedule_more_modal_title']) ? $lang['timer_schedule_more_modal_title'] : 'Schedule more work';
                $tasksessionTwAssigneeLbl = isset($lang['timer_assignee']) ? $lang['timer_assignee'] : 'Assignee';
                $tasksessionTwSelectAssignee = isset($lang['Select Assignee']) ? $lang['Select Assignee'] : 'Select assignee';
                $tasksessionTwMe = isset($lang['timer_me']) ? $lang['timer_me'] : 'Me';
                $tasksessionTwScheduleSaved = isset($lang['timer_schedule_saved']) ? $lang['timer_schedule_saved'] : 'Scheduled successfully.';
                $tasksessionTwToday = isset($lang['Today']) ? $lang['Today'] : 'Today';
                $tasksessionTwTotalPlanned = isset($lang['timer_total_planned']) ? $lang['timer_total_planned'] : 'Total planned';
                $tasksessionTwTracked = isset($lang['timer_time_tracked']) ? $lang['timer_time_tracked'] : 'Time tracked';
                $tasksessionTwActive = isset($lang['timer_active_timers']) ? $lang['timer_active_timers'] : 'Active timers';
                $tasksessionTwPlannedMeta = isset($lang['planned_time_col']) ? $lang['planned_time_col'] : 'Planned';
                $tasksessionTwDone = isset($lang['percent_done']) ? $lang['percent_done'] : '% done';
                $tasksessionTwColDate = isset($lang['Date']) ? $lang['Date'] : 'Date';
                $tasksessionTwColUser = isset($lang['User']) ? $lang['User'] : 'User';
                $tasksessionTwColPlanned = isset($lang['planned_time_col']) ? $lang['planned_time_col'] : 'Planned time';
                $tasksessionTwColTimer = isset($lang['timer']) ? $lang['timer'] : 'Timer';
                $tasksessionTwDeleteWork = isset($lang['timer_delete_work']) ? $lang['timer_delete_work'] : 'Delete work';
                $tasksessionTwCompleteWork = isset($lang['timer_complete_work']) ? $lang['timer_complete_work'] : 'Complete work';
                $tasksessionTwModalTitle = isset($lang['timer_complete_modal_title']) ? $lang['timer_complete_modal_title'] : 'How much time did it take?';
                $tasksessionTwEditLogTitle = isset($lang['timer_edit_time_log']) ? $lang['timer_edit_time_log'] : 'Edit time log';
                $tasksessionTwFromTimer = isset($lang['timer_from_timer']) ? $lang['timer_from_timer'] : 'From timer';
                $tasksessionTwManualTab = isset($lang['timer_manual_entry_tab']) ? $lang['timer_manual_entry_tab'] : 'Manual entry';
                $tasksessionTwLoggedTimeLbl = isset($lang['timer_logged_time_label']) ? $lang['timer_logged_time_label'] : 'Logged time';
                $tasksessionTwTaskDoneLbl = isset($lang['timer_task_completed_label']) ? $lang['timer_task_completed_label'] : 'Task is completed';
                $tasksessionTwHoursPh = isset($lang['timer_hours_placeholder']) ? $lang['timer_hours_placeholder'] : 'Hours';
                $tasksessionTwLoggedShort = isset($lang['timer_complete_logged_field']) ? $lang['timer_complete_logged_field'] : 'Logged';
                $tasksessionTwSelectDuration = isset($lang['timer_select_duration']) ? $lang['timer_select_duration'] : 'Select duration';
                $tasksessionManualDurPresets = [
                    [900, '15 min'],
                    [1800, '30 min'],
                    [2700, '45 min'],
                    [3600, '1 hour'],
                    [5400, '1h 30m'],
                    [7200, '2 hours'],
                    [9000, '2h 30m'],
                    [10800, '3 hours'],
                    [14400, '4 hours'],
                    [18000, '5 hours'],
                    [21600, '6 hours'],
                    [28800, '8 hours'],
                ];
                $tasksessionTwBillYes = isset($lang['billable_yes']) ? $lang['billable_yes'] : 'Yes';
                $tasksessionTwBillNo = isset($lang['billable_no']) ? $lang['billable_no'] : 'No';
                $tasksessionTwBillableLbl = isset($lang['billable']) ? $lang['billable'] : 'Billable';
                $tasksessionTwCommentLbl = isset($lang['Comment']) ? $lang['Comment'] : 'Comment';
                $tasksessionTwCommentPh = isset($lang['comment_placeholder']) ? $lang['comment_placeholder'] : 'Your comment...';
                $tasksessionTwSave = isset($lang['Save']) ? $lang['Save'] : 'Save';
                $tasksessionTwCancel = isset($lang['Cancel']) ? $lang['Cancel'] : 'Cancel';
                $tasksessionTwLogMoreTime = isset($lang['timer_log_more_time']) ? $lang['timer_log_more_time'] : '+ Log more time';
                $tasksessionTwNoTimeLogged = isset($lang['no_time_logged']) ? $lang['no_time_logged'] : 'No time logged yet.';
                $tasksessionTwNoScheduledWork = isset($lang['timer_no_scheduled_work']) ? $lang['timer_no_scheduled_work'] : 'No scheduled work yet.';
                $tasksessionTwTotalLoggedLbl = isset($lang['total_logged_time']) ? $lang['total_logged_time'] : 'Total logged';
                $tasksessionTwTypeCol = isset($lang['Type']) ? $lang['Type'] : 'Type';
                $tasksessionCanMarkDone = function_exists('has_permission') && has_permission('task_edit');
                $tasksessionTimerClientReadonly = isset($_SESSION['accountStatus']) && (int) $_SESSION['accountStatus'] === 2;
                $tasksessionTimerAvatarHtml = '';
                if ($uidSidebar > 0 && function_exists('getUserAvatarHtml')) {
                    $tasksessionTaUser = class_exists('User') ? User::findById($uidSidebar) : null;
                    $tasksessionTaFn = $tasksessionTaUser ? trim((string) ($tasksessionTaUser->firstName ?? '')) : '';
                    $tasksessionTimerAvatarHtml = getUserAvatarHtml(
                        $uidSidebar,
                        $tasksessionTaFn,
                        '',
                        36,
                        36,
                        'tasksession-timer-avatar-media',
                        $tasksessionTaFn !== '' ? $tasksessionTaFn : 'User'
                    );
                }
            ?>
            <!-- Timer Tab -->
            <div class="tab-pane fade" id="timer" role="tabpanel" aria-labelledby="timer-tab">
              <div class="files-section tasksession-timer-dashboard<?php echo $tasksessionTimerClientReadonly ? ' tasksession-timer-dashboard--client-readonly' : ''; ?>">
                <div class="tasksession-timer-stat-cards">
                  <div class="card pd-15 timer-card">
                    <div class="tasksession-timer-stat-value" id="tasksession-timer-stat-planned">—</div>
                    <div class="tasksession-timer-stat-label"><?php echo htmlspecialchars($tasksessionTwTotalPlanned); ?></div>
                  </div>
                  <div class="card pd-15 timer-card">
                    <div class="tasksession-timer-stat-value" id="tasksession-timer-stat-tracked">—</div>
                    <div class="tasksession-timer-stat-label"><?php echo htmlspecialchars($tasksessionTwTracked); ?></div>
                  </div>
                  <div class="card pd-15 timer-card">
                    <div class="tasksession-timer-stat-value" id="tasksession-timer-stat-active">0</div>
                    <div class="tasksession-timer-stat-label"><?php echo htmlspecialchars($tasksessionTwActive); ?></div>
                  </div>
                </div>

                <div class="tasksession-timer-work-card card mb-0"
                  data-work-complete="<?php echo htmlspecialchars($tasksessionTwCompleteWork); ?>"
                  data-work-discard="<?php echo htmlspecialchars($tasksessionTwDeleteWork); ?>"
                  data-work-actions-aria="<?php echo htmlspecialchars(isset($lang['Actions']) ? $lang['Actions'] : 'Actions'); ?>">
                  <div class="tasksession-timer-work-head">
                    <span><?php echo htmlspecialchars($tasksessionTwColDate); ?></span>
                    <span><?php echo htmlspecialchars($tasksessionTwColUser); ?></span>
                    <span><?php echo htmlspecialchars($tasksessionTwColPlanned); ?></span>
                    <span><?php echo htmlspecialchars($tasksessionTwColTimer); ?></span>
                  </div>
                  <div id="tasksession-timer-work-empty" class="tasksession-timer-work-empty small text-muted text-center py-2"><?php echo htmlspecialchars($tasksessionTwNoScheduledWork); ?></div>
                  <div id="tasksession-timer-work-list"></div>
                  <div id="tasksession-timer-estimate-wrap" class="tasksession-timer-estimate-wrap toolbar-dropdown-wrapper d-none" aria-hidden="true">
                    <button type="button" id="tasksession-timer-planned-trigger" class="tasksession-timer-planned-trigger" aria-haspopup="listbox" aria-expanded="false" aria-label="<?php echo htmlspecialchars($tasksessionTwColPlanned); ?>">—</button>
                    <div id="tasksessionTimerPlannedDropdown" class="dropdown-menu dropdown-menu-right tasksession-timer-planned-dropdown" role="listbox" aria-labelledby="tasksession-timer-planned-trigger"></div>
                  </div>
                  <ul id="tasksessionTimerWorkActionsDropdown" class="dropdown-menu dropdown-menu-end list-unstyled mb-0 tasksession-timer-actions-dropdown" role="menu" aria-hidden="true">
                    <li role="none">
                      <button type="button" class="dropdown-item tasksession-timer-work-action-item d-flex align-items-center" role="menuitem" data-work-action="complete">
                        <?php echo ts_icon('check-circle', 'tasksession-timer-log-menu-ico me-2'); ?>
                        <span><?php echo htmlspecialchars($tasksessionTwCompleteWork); ?></span>
                      </button>
                    </li>
                    <li role="none">
                      <button type="button" class="dropdown-item tasksession-timer-work-action-item d-flex align-items-center" role="menuitem" data-work-action="discard">
                        <?php echo ts_icon('delete', 'tasksession-timer-delete-work-ico tasksession-timer-work-action-ico flex-shrink-0 tasksession-timer-log-menu-ico me-2'); ?>
                        <span><?php echo htmlspecialchars($tasksessionTwDeleteWork); ?></span>
                      </button>
                    </li>
                  </ul>
                  <div class="tasksession-timer-work-footer">
                    <div class="tasksession-timer-meta-row tasksession-timer-meta-with-progress">
                      <span class="tasksession-timer-meta-planned-wrap">
                        <?php echo ts_icon('clock', 'tasksession-timer-meta-ico'); ?>
                        <span id="tasksession-timer-meta-planned"><?php echo htmlspecialchars($tasksessionTwPlannedMeta); ?>: —</span>
                      </span>
                      <span class="tasksession-timer-meta-pct-wrap">
                        <?php echo ts_icon('chart-bar', 'tasksession-timer-meta-ico'); ?>
                        <span id="tasksession-timer-meta-pct">0<?php echo htmlspecialchars($tasksessionTwDone); ?></span>
                      </span>
                      <div class="tasksession-timer-progress-track" id="tasksession-timer-progress-track">
                        <div class="tasksession-timer-progress-fill"></div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="d-flex justify-content-start w-100">
                  <button type="button" class="tasksession-timer-schedule-add text-btn" id="tasksession-timer-schedule-add">
                    <span><?php echo htmlspecialchars($tasksessionTwScheduleMoreBtn); ?></span>
                  </button>
                </div>

                <div class="tasksession-timer-logs-card shadow-none">
                  <button class="tasksession-timer-logs-head w-100 text-start border-0 bg-transparent d-flex align-items-center justify-content-between" type="button" data-bs-toggle="collapse" data-bs-target="#tasksession-timer-logs-collapse" aria-expanded="true" aria-controls="tasksession-timer-logs-collapse" id="tasksession-timer-logs-toggle">
                    <span class="d-inline-flex align-items-center col-gap min-width-0">
                      <span class="tasksession-timer-logs-head-label text-truncate"><?php echo htmlspecialchars($tasksessionTwLoggedTimeLbl); ?></span>
                      <span class="files-count" id="tasksession-timer-logs-count">0</span>
                    </span>
                    <?php echo ts_icon('chevron-down'); ?>
                  </button>
                  <div id="tasksession-timer-logs-collapse" class="collapse show">
                    <div id="tasksession-timer-logs-empty" class="tasksession-timer-logs-empty small text-muted pd-10 text-center is-visible">
					<?php echo ts_icon('clock', 'tasksession-header-timer-pill-icon tasksession-timer-complete-timer-ico flex-shrink-0'); ?>
					<?php echo htmlspecialchars($tasksessionTwNoTimeLogged); ?></div>
                    <div id="tasksession-timer-logs-table-wrap" class="tasksession-timer-logs-table-wrap d-none">
                      <table class="table table-sm tasksession-timer-logs-table mb-0">
                        <thead>
                          <tr>
                            <th scope="col"><?php echo htmlspecialchars($tasksessionTwColDate); ?></th>
                            <th scope="col"><?php echo htmlspecialchars($tasksessionTwColUser); ?></th>
                            <th scope="col"><?php echo htmlspecialchars($tasksessionTwCommentLbl); ?></th>
                            <th scope="col" class="text-center<?php echo $tasksessionTimerClientReadonly ? ' d-none' : ''; ?>"><?php echo htmlspecialchars($tasksessionTwBillableLbl); ?></th>
                            <th scope="col" class="text-center"><?php echo htmlspecialchars($tasksessionTwTypeCol); ?></th>
                            <th scope="col" class="text-end"><?php echo htmlspecialchars($tasksessionTwColTimer); ?></th>
                            <th scope="col" class="text-end tasksession-timer-log-th-actions p-0<?php echo $tasksessionTimerClientReadonly ? ' d-none' : ''; ?>"></th>
                          </tr>
                        </thead>
                        <tbody id="tasksession-timer-logs-body"></tbody>
                      </table>
                    </div>
                    <div class="tasksession-timer-logs-footer d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-top">
                      <button type="button" class="tasksession-timer-log-more-link text-btn" id="tasksession-timer-log-more-btn"><?php echo htmlspecialchars($tasksessionTwLogMoreTime); ?></button>
                      <span class="fw-semibold tasksession-timer-logs-total-wrap">
                        <span class="text-muted"><?php echo htmlspecialchars($tasksessionTwTotalLoggedLbl); ?>:</span>
                        <span id="tasksession-timer-logs-total">—</span>
                      </span>
                    </div>
                  </div>
                </div>

                <div class="modal fade tasksession-schedule-more-modal tasksession-timer-complete-modal team-group-modal" id="tasksessionScheduleMoreModal" tabindex="-1" aria-labelledby="tasksessionScheduleMoreModalLabel" aria-hidden="true"
                  data-current-user-id="<?php echo (int) $uidSidebar; ?>"
                  data-schedule-msg-saved="<?php echo htmlspecialchars($tasksessionTwScheduleSaved, ENT_QUOTES, 'UTF-8'); ?>"
                  data-schedule-msg-select="<?php echo htmlspecialchars($tasksessionTwSelectAssignee, ENT_QUOTES, 'UTF-8'); ?>"
                  data-schedule-label-today="<?php echo htmlspecialchars($tasksessionTwToday, ENT_QUOTES, 'UTF-8'); ?>"
                  data-schedule-label-me="<?php echo htmlspecialchars($tasksessionTwMe, ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="tasksessionScheduleMoreModalLabel"><?php echo htmlspecialchars($tasksessionTwScheduleMoreModalTitle); ?></h5>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars($tasksessionTwCancel); ?>">
                          <?php echo ts_icon('close'); ?>
                        </button>
                      </div>
                      <div class="modal-body pd-30">
                        <div id="tasksession-schedule-more-error" class="alert alert-danger py-2 px-3 small d-none mb-3" role="alert"></div>
                        <div class="row mb-2 align-items-center tasksession-timer-complete-field">
                          <label class="col-5 col-form-label small mb-0" for="tasksession-schedule-date"><?php echo htmlspecialchars($tasksessionTwColDate); ?></label>
                          <div class="col-7">
                            <input type="date" class="form-control field-btn" id="tasksession-schedule-date" autocomplete="off">
                          </div>
                        </div>
                        <div class="row mb-2 align-items-center tasksession-timer-complete-field">
                          <label class="col-5 col-form-label small mb-0" for="tasksession-schedule-more-planned-trigger"><?php echo htmlspecialchars($tasksessionTwColPlanned); ?></label>
                          <div class="col-7">
                            <div class="toolbar-dropdown-wrapper tasksession-timer-estimate-wrap w-100 position-relative">
                              <button type="button" id="tasksession-schedule-more-planned-trigger" class="form-control field-btn tasksession-schedule-more-planned-btn w-100 text-start d-flex align-items-center justify-content-between" aria-haspopup="listbox" aria-expanded="false">
                                <span class="tasksession-schedule-more-planned-value">0:15h</span>
                                <i class="fa fa-chevron-down tasksession-schedule-more-planned-chevron" aria-hidden="true"></i>
                              </button>
                              <div id="tasksessionScheduleMorePlannedDropdown" class="dropdown-menu tasksession-timer-planned-dropdown tasksession-schedule-more-planned-dd" role="listbox" aria-labelledby="tasksession-schedule-more-planned-trigger"></div>
                            </div>
                            <input type="hidden" id="tasksession-schedule-planned-seconds" value="900">
                          </div>
                        </div>
                        <div class="row mb-0 align-items-start tasksession-timer-complete-field">
                          <label class="col-5 col-form-label small mb-0 pt-1"><?php echo htmlspecialchars($tasksessionTwAssigneeLbl); ?></label>
                          <div class="col-7">
                            <div class="subtask-form-assignee tasksession-schedule-assignee-wrap w-100">
                              <div class="custom-dropdown" id="tasksession-schedule-assignee-dropdown">
                                <div class="dropdown-selected" id="tasksession-schedule-assignee-selected" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false">
                                  <span class="selected-text"><?php echo htmlspecialchars($tasksessionTwSelectAssignee); ?></span>
                                  <i class="fa fa-chevron-down dropdown-arrow" aria-hidden="true"></i>
                                </div>
                                <div class="dropdown-options" id="tasksession-schedule-assignee-options" style="display: none;" role="listbox"></div>
                              </div>
                              <input type="hidden" id="tasksession-schedule-assignee" value="">
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer border-top d-flex justify-content-end align-items-center col-gap-5 flex-wrap">
                        <button type="button" class="btn border-btn-a" data-bs-dismiss="modal"><?php echo htmlspecialchars($tasksessionTwCancel); ?></button>
                        <button type="button" class="primary-btn" id="tasksession-schedule-more-save"><?php echo htmlspecialchars($tasksessionTwSave); ?></button>
                      </div>
                    </div>
                  </div>
                </div>

                <div id="tasksession-timer-alert" class="alert alert-warning py-2 px-3 small d-none" role="alert" aria-live="polite"></div>

                <div id="tasksession-timer-controls" class="d-none" aria-hidden="true">
                  <div id="tasksession-timer-mutations">
                    <button type="button" id="tasksession-timer-btn-start" tabindex="-1" aria-hidden="true"></button>
                    <button type="button" id="tasksession-timer-btn-pause" tabindex="-1" aria-hidden="true"></button>
                    <button type="button" id="tasksession-timer-btn-resume" tabindex="-1" aria-hidden="true"></button>
                  </div>
                </div>

                <div class="modal fade team-group-modal tasksession-timer-complete-modal" id="tasksessionTimerCompleteModal" tabindex="-1" aria-labelledby="tasksessionTimerCompleteModalLabel" aria-hidden="true" data-default-mark-done="<?php echo $tasksessionCanMarkDone ? '1' : '0'; ?>"
                  data-current-user-id="<?php echo (int) $uidSidebar; ?>"
                  data-log-complete-msg-select="<?php echo htmlspecialchars($tasksessionTwSelectAssignee, ENT_QUOTES, 'UTF-8'); ?>"
                  data-log-complete-label-me="<?php echo htmlspecialchars($tasksessionTwMe, ENT_QUOTES, 'UTF-8'); ?>">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="tasksessionTimerCompleteModalLabel"><?php echo htmlspecialchars($tasksessionTwModalTitle); ?></h5>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars($tasksessionTwCancel); ?>">
                          <?php echo ts_icon('close'); ?>
                        </button>
                      </div>
                      <div class="modal-body pd-30">
                        <ul class="nav nav-tabs mb-3" id="tasksessionTimerCompleteTabList" role="tablist">
                          <li class="nav-item">
                            <button type="button" class="nav-link active tasksession-timer-complete-mode" id="tasksession-complete-tab-timer" data-bs-toggle="tab" data-bs-target="#tasksession-complete-pane-timer" role="tab" aria-controls="tasksession-complete-pane-timer" aria-selected="true" data-complete-mode="timer"><?php echo htmlspecialchars($tasksessionTwFromTimer); ?></button>
                          </li>
                          <li class="nav-item">
                            <button type="button" class="nav-link tasksession-timer-complete-mode" id="tasksession-complete-tab-manual" data-bs-toggle="tab" data-bs-target="#tasksession-complete-pane-manual" role="tab" aria-controls="tasksession-complete-pane-manual" aria-selected="false" data-complete-mode="manual"><?php echo htmlspecialchars($tasksessionTwManualTab); ?></button>
                          </li>
                        </ul>

                        <div id="tasksession-timer-complete-timer-row" class="row mb-2 align-items-center tasksession-timer-complete-field tasksession-timer-complete-logged-row">
                          <span class="col-5 col-form-label small mb-0"><?php echo htmlspecialchars($tasksessionTwLoggedTimeLbl); ?></span>
                          <div class="col-7 text-end">
                            <span class="d-inline-flex align-items-center justify-content-end min-width-0 tasksession-timer-complete-timer-value">
                              <svg fill="none" viewBox="0 0 20 20" width="1em" height="1em" class="tasksession-timer-complete-timer-ico flex-shrink-0" aria-hidden="true"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="1.2" d="M10 6.667v4.166M7.5 1.667h5m4.792 9.375A7.294 7.294 0 0 1 10 18.333a7.294 7.294 0 0 1-7.292-7.291A7.294 7.294 0 0 1 10 3.75a7.294 7.294 0 0 1 7.292 7.292"></path></svg>
                              <span id="tasksession-timer-complete-timer-display" class="fw-semibold text-truncate">00:00:00</span>
                            </span>
                          </div>
                        </div>

                        <div class="tab-content tasksession-timer-complete-tab-content mv-share-modal-tab-content">
                          <div class="tab-pane fade show active" id="tasksession-complete-pane-timer" role="tabpanel" aria-labelledby="tasksession-complete-tab-timer" tabindex="0">
                          </div>
                          <div class="tab-pane fade" id="tasksession-complete-pane-manual" role="tabpanel" aria-labelledby="tasksession-complete-tab-manual" tabindex="0">
                            <div class="row mb-2 align-items-center tasksession-timer-complete-field">
                              <label class="col-5 col-form-label small mb-0" for="tasksession-timer-complete-manual-dropdown-btn"><?php echo htmlspecialchars($tasksessionTwLoggedShort); ?></label>
                              <div class="col-7">
                                <div class="dropdown w-100">
                                  <button
                                    type="button"
                                    class="field-btn dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between tasksession-timer-manual-dd"
                                    id="tasksession-timer-complete-manual-dropdown-btn"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="true"
                                    aria-expanded="false"
                                  >
                                    <span id="tasksession-timer-complete-manual-dropdown-label" class="flex-grow-1 text-truncate min-width-0 text-start" data-placeholder="<?php echo htmlspecialchars($tasksessionTwSelectDuration); ?>"><?php echo htmlspecialchars($tasksessionTwSelectDuration); ?></span>
                                    <?php echo ts_icon('hourglass', 'tasksession-timer-manual-dd-ico flex-shrink-0 ms-2'); ?>
                                  </button>
                                  <ul class="dropdown-menu w-100 dropdown-scroll p-0" aria-labelledby="tasksession-timer-complete-manual-dropdown-btn">
                                    <li class="p-0 border-0">
                                      <ul class="list-unstyled mb-0 w-100">
                                        <?php foreach ($tasksessionManualDurPresets as $tasksessionDurRow) :
                                            $tasksessionDurSec = (int) ($tasksessionDurRow[0] ?? 0);
                                            $tasksessionDurLbl = isset($tasksessionDurRow[1]) ? (string) $tasksessionDurRow[1] : '';
                                            ?>
                                        <li><button type="button" class="dropdown-item tasksession-timer-manual-dur-opt" data-seconds="<?php echo $tasksessionDurSec; ?>"><?php echo htmlspecialchars($tasksessionDurLbl); ?></button></li>
                                        <?php endforeach; ?>
                                      </ul>
                                    </li>
                                  </ul>
                                </div>
                                <input type="hidden" id="tasksession-timer-complete-manual-seconds" value="">
                              </div>
                            </div>
                          </div>
                        </div>

                        <div class="row mb-2 align-items-center tasksession-timer-complete-field">
                          <label class="col-5 col-form-label small mb-0" for="tasksession-timer-complete-date"><?php echo htmlspecialchars($tasksessionTwColDate); ?></label>
                          <div class="col-7">
                            <input type="date" id="tasksession-timer-complete-date" class="field-btn w-100" autocomplete="off">
                          </div>
                        </div>

                        <div id="tasksession-complete-log-assignee-row" class="row mb-2 align-items-start tasksession-timer-complete-field d-none">
                          <label class="col-5 col-form-label small mb-0 pt-1"><?php echo htmlspecialchars($tasksessionTwAssigneeLbl); ?></label>
                          <div class="col-7">
                            <div class="subtask-form-assignee tasksession-complete-log-assignee-wrap w-100">
                              <div class="custom-dropdown" id="tasksession-complete-log-assignee-dropdown">
                                <div class="dropdown-selected" id="tasksession-complete-log-assignee-selected" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false">
                                  <span class="selected-text"><?php echo htmlspecialchars($tasksessionTwSelectAssignee); ?></span>
                                  <i class="fa fa-chevron-down dropdown-arrow" aria-hidden="true"></i>
                                </div>
                                <div class="dropdown-options" id="tasksession-complete-log-assignee-options" style="display: none;" role="listbox"></div>
                              </div>
                              <input type="hidden" id="tasksession-complete-log-assignee" value="">
                            </div>
                          </div>
                        </div>

                        <div class="row mb-2 align-items-center tasksession-timer-complete-field">
                          <span class="col-5 col-form-label small mb-0" id="tasksession-timer-complete-billable-lbl"><?php echo htmlspecialchars($tasksessionTwBillableLbl); ?></span>
                          <div class="col-7">
                            <div class="media-replace-scope mb-3" id="tasksessionTimerBillablePills" role="radiogroup" aria-labelledby="tasksession-timer-complete-billable-lbl">
                              <input type="radio" class="btn-check" name="tasksessionTimerCompleteBillable" id="tasksession-timer-complete-billable-yes" value="1">
                              <label class="media-replace-scope-option" for="tasksession-timer-complete-billable-yes"><?php echo htmlspecialchars($tasksessionTwBillYes); ?></label>
                              <input type="radio" class="btn-check" name="tasksessionTimerCompleteBillable" id="tasksession-timer-complete-billable-no" value="0" checked>
                              <label class="media-replace-scope-option" for="tasksession-timer-complete-billable-no"><?php echo htmlspecialchars($tasksessionTwBillNo); ?></label>
                            </div>
                          </div>
                        </div>

                        <div class="form-group field-label mb-2 tasksession-timer-complete-notes-block">
                          <label class="form-label small mb-1 d-block" for="tasksession-timer-complete-notes"><?php echo htmlspecialchars($tasksessionTwCommentLbl); ?></label>
                          <textarea id="tasksession-timer-complete-notes" class="field-btn w-100" rows="3" placeholder="<?php echo htmlspecialchars($tasksessionTwCommentPh); ?>" style="min-height: 5.5rem; resize: vertical;"></textarea>
                        </div>

                        <div class="tasksession-timer-complete-mark-done-bar">
                          <div class="form-check d-flex align-items-center gap-2 m-0">
                            <input class="form-check-input tasksession-timer-complete-mark-input" type="checkbox" id="tasksession-timer-complete-mark-done" autocomplete="off">
                            <label class="form-check-label mb-0 flex-grow-1 tasksession-timer-complete-mark-label" for="tasksession-timer-complete-mark-done"><?php echo htmlspecialchars($tasksessionTwTaskDoneLbl); ?></label>
                          </div>
                        </div>
                      </div>
                      <div class="modal-footer border-top d-flex justify-content-end align-items-center col-gap-5 flex-wrap">
                        <button type="button" class="btn border-btn-a" data-bs-dismiss="modal"><?php echo htmlspecialchars($tasksessionTwCancel); ?></button>
                        <button type="button" class="primary-btn" id="tasksession-timer-complete-save"><?php echo htmlspecialchars($tasksessionTwSave); ?></button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="modal fade team-group-modal tasksession-timer-complete-modal" id="tasksessionTimerEditLogModal" tabindex="-1" aria-labelledby="tasksessionTimerEditLogModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="tasksessionTimerEditLogModalLabel"><?php echo htmlspecialchars($tasksessionTwEditLogTitle); ?></h5>
                        <button type="button" class="btn-close me-2" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars($tasksessionTwCancel); ?>">
                          <?php echo ts_icon('close'); ?>
                        </button>
                      </div>
                      <div class="modal-body pd-30">
                        <input type="hidden" id="tasksession-edit-log-entry-id" value="">
                        <div class="row mb-2 align-items-center tasksession-timer-complete-field">
                          <label class="col-5 col-form-label small mb-0" for="tasksession-edit-log-manual-dropdown-btn"><?php echo htmlspecialchars($tasksessionTwLoggedShort); ?></label>
                          <div class="col-7">
                            <div class="dropdown w-100">
                              <button
                                type="button"
                                class="field-btn dropdown-toggle w-100 text-start d-flex align-items-center justify-content-between tasksession-timer-manual-dd"
                                id="tasksession-edit-log-manual-dropdown-btn"
                                data-bs-toggle="dropdown"
                                data-bs-auto-close="true"
                                aria-expanded="false"
                              >
                                <span id="tasksession-edit-log-manual-dropdown-label" class="flex-grow-1 text-truncate min-width-0 text-start" data-placeholder="<?php echo htmlspecialchars($tasksessionTwSelectDuration); ?>"><?php echo htmlspecialchars($tasksessionTwSelectDuration); ?></span>
                                <?php echo ts_icon('hourglass', 'tasksession-timer-manual-dd-ico flex-shrink-0 ms-2'); ?>
                              </button>
                              <ul class="dropdown-menu w-100 dropdown-scroll p-0" aria-labelledby="tasksession-edit-log-manual-dropdown-btn">
                                <li class="p-0 border-0">
                                  <ul class="list-unstyled mb-0 w-100">
                                    <?php foreach ($tasksessionManualDurPresets as $tasksessionDurRow) :
                                        $tasksessionDurSec = (int) ($tasksessionDurRow[0] ?? 0);
                                        $tasksessionDurLbl = isset($tasksessionDurRow[1]) ? (string) $tasksessionDurRow[1] : '';
                                        ?>
                                    <li><button type="button" class="dropdown-item tasksession-edit-log-dur-opt" data-seconds="<?php echo $tasksessionDurSec; ?>"><?php echo htmlspecialchars($tasksessionDurLbl); ?></button></li>
                                    <?php endforeach; ?>
                                  </ul>
                                </li>
                              </ul>
                            </div>
                            <input type="hidden" id="tasksession-edit-log-manual-seconds" value="">
                          </div>
                        </div>
                        <div class="row mb-2 align-items-center tasksession-timer-complete-field">
                          <label class="col-5 col-form-label small mb-0" for="tasksession-edit-log-date"><?php echo htmlspecialchars($tasksessionTwColDate); ?></label>
                          <div class="col-7">
                            <input type="date" id="tasksession-edit-log-date" class="field-btn w-100" autocomplete="off">
                          </div>
                        </div>
                        <div class="row mb-2 align-items-center tasksession-timer-complete-field">
                          <span class="col-5 col-form-label small mb-0" id="tasksession-edit-log-billable-lbl"><?php echo htmlspecialchars($tasksessionTwBillableLbl); ?></span>
                          <div class="col-7">
                            <div class="media-replace-scope mb-3" id="tasksessionTimerEditLogBillablePills" role="radiogroup" aria-labelledby="tasksession-edit-log-billable-lbl">
                              <input type="radio" class="btn-check" name="tasksessionTimerEditLogBillable" id="tasksession-edit-log-billable-yes" value="1">
                              <label class="media-replace-scope-option" for="tasksession-edit-log-billable-yes"><?php echo htmlspecialchars($tasksessionTwBillYes); ?></label>
                              <input type="radio" class="btn-check" name="tasksessionTimerEditLogBillable" id="tasksession-edit-log-billable-no" value="0" checked>
                              <label class="media-replace-scope-option" for="tasksession-edit-log-billable-no"><?php echo htmlspecialchars($tasksessionTwBillNo); ?></label>
                            </div>
                          </div>
                        </div>
                        <div class="form-group field-label mb-2 tasksession-timer-complete-notes-block">
                          <label class="form-label small mb-1 d-block" for="tasksession-edit-log-notes"><?php echo htmlspecialchars($tasksessionTwCommentLbl); ?></label>
                          <textarea id="tasksession-edit-log-notes" class="field-btn w-100" rows="3" placeholder="<?php echo htmlspecialchars($tasksessionTwCommentPh); ?>" style="min-height: 5.5rem; resize: vertical;"></textarea>
                        </div>
                      </div>
                      <div class="modal-footer d-flex justify-content-end align-items-center col-gap-5 flex-wrap">
                        <button type="button" class="btn border-btn-a" data-bs-dismiss="modal"><?php echo htmlspecialchars($tasksessionTwCancel); ?></button>
                        <button type="button" class="primary-btn" id="tasksession-edit-log-save"><?php echo htmlspecialchars($tasksessionTwSave); ?></button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <!-- Activities Tab -->
            <div class="tab-pane fade" id="activities" role="tabpanel" aria-labelledby="activities-tab">
              <div class="files-section">
                <div id="task-activity-loading" class="alert alert-light text-center" style="display:none;">
                  <?php echo $lang['Loading...'] ?? 'Loading...'; ?>
                </div>
                <div id="task-activity-list"></div>
              </div>
            </div>
          </div>
        </div>
        
        <?php
        $__subtaskAcl = [
            'userId' => $uidSidebar,
            'accountStatus' => $acctSidebar,
            'mutateAnySubtask' => false,
            'hasTaskEdit' => false,
            'hasTaskDelete' => false,
        ];
        if ($acctSidebar === 1) {
            $__subtaskAcl['mutateAnySubtask'] = true;
            $__subtaskAcl['hasTaskEdit'] = true;
            $__subtaskAcl['hasTaskDelete'] = true;
        } elseif ($acctSidebar === 3) {
            if (!function_exists('has_permission')) {
                require_once dirname(__DIR__) . '/includes/permissions.php';
            }
            $__subtaskAcl['hasTaskEdit'] = has_permission('task_edit');
            $__subtaskAcl['hasTaskDelete'] = has_permission('task_delete');
        } elseif ($acctSidebar === 2) {
            require_once dirname(__DIR__) . '/includes/task_permission.php';
            $__tpAcl = TaskPermission::getOrCreate($uidSidebar);
            $__subtaskAcl['hasTaskEdit'] = $__tpAcl && $__tpAcl->can_update_task;
            $__subtaskAcl['hasTaskDelete'] = $__tpAcl && $__tpAcl->can_delete_task;
        }
        ?>
        <script>
        window.__subtaskAcl = <?php echo json_encode($__subtaskAcl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        </script>
        <script>
        window.__subtasksSidebarStrings = <?php echo json_encode([
          'dueToday' => $subtasksLangDueToday,
          'dueTomorrow' => $subtasksLangDueTomorrow,
          'unassigned' => $subtasksLangUnassigned,
          'cancel' => $subtasksLangCancel,
          'save' => isset($lang['Save']) ? $lang['Save'] : 'Save',
          'selectAssignee' => isset($lang['Select Assignee']) ? $lang['Select Assignee'] : 'Select Assignee',
          'pleaseEnterName' => isset($lang['Please enter a sub-task name']) ? $lang['Please enter a sub-task name'] : 'Please enter a sub-task name',
          'descriptionHeading' => isset($lang['DESCRIPTION']) ? $lang['DESCRIPTION'] : (isset($lang['Description']) ? strtoupper($lang['Description']) : 'DESCRIPTION'),
          'viewDescription' => isset($lang['View description']) ? $lang['View description'] : 'View description',
          'edit' => isset($lang['Edit']) ? $lang['Edit'] : 'Edit',
          'close' => isset($lang['Close']) ? $lang['Close'] : 'Close',
          'editSubtask' => isset($lang['Edit subtask']) ? $lang['Edit subtask'] : 'Edit subtask',
          'noDescription' => isset($lang['No description yet.']) ? $lang['No description yet.'] : 'No description yet.',
          'metaCreated' => isset($lang['Created']) ? $lang['Created'] : 'Created',
          'metaDone' => isset($lang['Done']) ? $lang['Done'] : 'Done',
          'dateDash' => '—',
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        </script>
      </div>
    </div>
  </div>
 </div>
<!-- Task Chat Section - Free edition: removed (Pro only; no in-page upgrade card) -->
<?php if (!(function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())): ?>
<div class="task-chat-wrapper" id="task-chat-wrapper">
<button class="sidebar-shrink-btn" id="taskShrinkBtn" title="Shrink sidebar" style="transform: rotate(180deg); transition: transform 0.3s ease;">
	<?php echo ts_icon('chevron-left', 'w-2'); ?>
	</button>
<div class="task-chat-search task-sidebar-header">
  <input type="text" id="task-chat-search-input" class="form-control" placeholder="Search messages..." />
  <div id="task-chat-search-results"></div>
</div>

    <div class="messages-box <?php echo (isset($accountStatus) && $accountStatus==1) ? " admin-are " : " client-are "; ?>" id="task-chat-messages-box">
        <!-- Chat will be loaded here when task opens -->
    </div>
</div>
<?php endif; ?>
</div>
</div>
<?php
if (!function_exists('comon_page_asset_enabled') && defined('LIB_ROOT')) {
    require_once LIB_ROOT . DS . 'page_assets.php';
}
// Exclude file-sharing.css on inbox and new (compose) pages
$isEmailPage = isset($_SERVER['REQUEST_URI']) && (
    strpos($_SERVER['REQUEST_URI'], '/mail/inbox.php') !== false ||
    strpos($_SERVER['REQUEST_URI'], '/mail/new.php') !== false
);
$loadFileSharing = (!$isEmailPage) && (!function_exists('comon_page_asset_enabled') || comon_page_asset_enabled('file_sharing'));
$__fileSharingCss = (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'google' . DIRECTORY_SEPARATOR . 'gdrive' . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'file-sharing.css';
if ($loadFileSharing && is_file($__fileSharingCss)) {
    echo '<link rel="stylesheet" href="' . (isset($url) ? $url : '../') . 'vendor/google/gdrive/assets/css/file-sharing.css">';
}
$loadTaskSidebarBundle = !function_exists('comon_page_asset_enabled') || comon_page_asset_enabled('task_sidebar_bundle');
$lazyTaskSidebarBundle = function_exists('comon_page_asset_lazy') && comon_page_asset_lazy('task_sidebar_bundle');
$taskSidebarAssetBase = isset($url) ? $url : '../';
$taskSidebarJsRoot = (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR;
$comonMessagesCssFile = (defined('SITE_ROOT') ? SITE_ROOT : dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'messages.css';
$comonMessagesCssHref = $taskSidebarAssetBase . 'assets/css/messages.css?v=' . (int) @filemtime($comonMessagesCssFile);
?>
<script src="<?php echo $taskSidebarAssetBase; ?>assets/js/csrf-fetch.js?v=<?php echo filemtime(SITE_ROOT . DS . 'assets' . DS . 'js' . DS . 'csrf-fetch.js'); ?>"></script>
<?php if ($loadTaskSidebarBundle) { ?>
<script src="<?php echo $taskSidebarAssetBase; ?>assets/js/subtasks.js"></script>
<script src="<?php echo $taskSidebarAssetBase; ?>assets/js/task-files.js"></script>
<script src="<?php echo $taskSidebarAssetBase; ?>assets/js/task-images.js"></script>
<script>
window.comonMessagesCssHref = <?php echo json_encode($comonMessagesCssHref, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="<?php echo $taskSidebarAssetBase; ?>assets/js/task-sidebar.js?v=<?php echo (int) @filemtime($taskSidebarJsRoot . 'task-sidebar.js'); ?>"></script>
<?php } elseif ($lazyTaskSidebarBundle && !defined('COMON_TASK_SIDEBAR_BUNDLE_LAZY')) {
  define('COMON_TASK_SIDEBAR_BUNDLE_LAZY', true);
  $lazyBundle = array(
    'subtasks' => $taskSidebarAssetBase . 'assets/js/subtasks.js',
    'taskFiles' => $taskSidebarAssetBase . 'assets/js/task-files.js',
    'taskImages' => $taskSidebarAssetBase . 'assets/js/task-images.js',
    'taskSidebar' => $taskSidebarAssetBase . 'assets/js/task-sidebar.js?v=' . (int) @filemtime($taskSidebarJsRoot . 'task-sidebar.js'),
    'messagesCss' => $comonMessagesCssHref,
  );
?>
<script>
window.comonMessagesCssHref = <?php echo json_encode($comonMessagesCssHref, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
(function () {
  if (window.__comonTaskSidebarLazyBound) return;
  window.__comonTaskSidebarLazyBound = true;
  var assets = <?php echo json_encode($lazyBundle, JSON_UNESCAPED_SLASHES); ?>;
  var loading = null;
  function loadScript(src) {
    if (!src) return Promise.resolve();
    if (document.querySelector('script[src="' + src + '"]')) return Promise.resolve();
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = src;
      s.async = false;
      s.onload = function () { resolve(); };
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }
  function ensureTaskSidebarBundle() {
    if (window.__comonTaskSidebarBundleReady) {
      return Promise.resolve();
    }
    if (loading) return loading;
    loading = Promise.resolve()
      .then(function () { return loadScript(assets.subtasks); })
      .then(function () { return loadScript(assets.taskFiles); })
      .then(function () { return loadScript(assets.taskImages); })
      .then(function () { return loadScript(assets.taskSidebar); })
      .then(function () {
        window.__comonTaskSidebarBundleReady = true;
        loading = null;
      })
      .catch(function (err) {
        loading = null;
        throw err;
      });
    return loading;
  }
  window.__comonEnsureTaskSidebarBundle = ensureTaskSidebarBundle;
  function openTaskSidebarLazy(taskId) {
    var id = taskId;
    return ensureTaskSidebarBundle().then(function () {
      if (typeof window.openTaskSidebar === 'function' && window.openTaskSidebar !== openTaskSidebarLazy) {
        window.openTaskSidebar(id);
      }
    }).catch(function () {});
  }
  window.openTaskSidebar = openTaskSidebarLazy;
})();
</script>
<?php } ?>

<script>
// Close button handler
(function () {
  var closeBtn = document.getElementById('close-sidebar');
  if (!closeBtn) return;
  closeBtn.addEventListener('click', function(e) {
  e.preventDefault();
  e.stopPropagation();
  var sidebar = document.getElementById('task-sidebar');
  if (sidebar) {
    // Stop task chat polling when sidebar is closed
    if (window.taskChatPollingInterval) {
      clearInterval(window.taskChatPollingInterval);
      window.taskChatPollingInterval = null;
    }
    
    // Reset polling flags so it can restart when sidebar opens again
    window.taskChatPollingStarted = false;
    
    // Clear loading flags
    window.taskChatLoading = false;
    window.taskChatLoadingInProgress = null;
    
    // Remove inline style completely
    sidebar.style.right = null;
    sidebar.style.removeProperty('right');
    sidebar.classList.remove('shrunk');
    sidebar.classList.remove('open');
  }
  });
})();

// View Project button handler — absolute role URL (works from /ai/* pages too)
function setViewProjectButton(projectId) {
  var pid = parseInt(projectId, 10) || 0;
  var show = pid > 0;
  var href = '#';
  if (show) {
    var base = (typeof window.baseUrl === 'string' && window.baseUrl) ? String(window.baseUrl) : '/';
    if (base.slice(-1) !== '/') {
      base += '/';
    }
    var role = '';
    var st = window.accountStatus;
    if (st === 1 || st === '1') {
      role = 'admin/';
    } else if (st === 2 || st === '2') {
      role = 'client/';
    } else if (st === 3 || st === '3') {
      role = 'staff/';
    } else {
      var parts = (window.location.pathname || '').split('/').filter(Boolean);
      for (var i = 0; i < parts.length; i++) {
        var seg = String(parts[i]).toLowerCase();
        if (seg === 'admin' || seg === 'staff' || seg === 'client') {
          role = seg + '/';
          break;
        }
      }
      if (!role) {
        role = 'admin/';
      }
    }
    href = base + role + 'overview.php?projectId=' + pid;
  }
  document.querySelectorAll('#view-project-btn, #sidebar-view-project-link').forEach(function (btn) {
    btn.href = href;
    btn.style.display = show ? '' : 'none';
    var item = btn.closest('li');
    if (item) {
      item.style.display = show ? '' : 'none';
    }
  });
}

// Initialize sub-tasks when task sidebar opens
function openTaskSidebarWithSubtasks(taskId) {
  // Call the original openTaskSidebar function if it exists
  if (typeof openTaskSidebar === 'function') {
    openTaskSidebar(taskId);
  }
  
  // Initialize sub-tasks
  if (typeof initializeSubTasks === 'function') {
    initializeSubTasks(taskId);
  }
}

// Simple approach: Watch for sidebar to open and load chat
// Wait for jQuery to be available
(function() {
  window.TS_FREE_NO_TASK_CHAT = <?php echo (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) ? 'true' : 'false'; ?>;
  function initTaskChatWatch() {
    if (window.TS_FREE_NO_TASK_CHAT) { return; }
    if (typeof jQuery === 'undefined') {
      setTimeout(initTaskChatWatch, 100);
      return;
    }
    
    jQuery(document).ready(function($) {
  
      // Watch for sidebar opening using MutationObserver
      var sidebar = document.getElementById('task-sidebar');
      if (sidebar) {
        // Only react on transition from closed -> open to avoid duplicate loads
        var wasOpen = sidebar.classList.contains('open');
        var observer = new MutationObserver(function(mutations) {
          mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
              var isOpen = sidebar.classList.contains('open');
              if (isOpen && !wasOpen) {
                // Try to get task ID
                var taskId = null;
                
                // Method 1: Check data attribute on sidebar
                if (sidebar.dataset && sidebar.dataset.taskId) {
                  taskId = sidebar.dataset.taskId;
                }
                
                // Method 2: Check any active task button
                if (!taskId) {
                  var taskBtn = $('.view-task-btn.active, [data-task-id].active').first();
                  if (taskBtn.length) {
                    taskId = taskBtn.attr('data-task-id');
                  }
                }
                
                if (taskId) {
                  loadChatForTask(taskId);
                }
              }
              wasOpen = isOpen;
            }
          });
        });
        
        observer.observe(sidebar, { attributes: true });
      }
      
      // Function to load chat
      function loadChatForTask(taskId) {
        if (window.TS_FREE_NO_TASK_CHAT) { return; }
        taskId = parseInt(taskId, 10) || 0;
        if (!taskId) return;

        // Prevent duplicate loads for the same task while AJAX is in flight
        if (window.taskChatLoadingInProgress && window.taskChatLoadingInProgress === taskId) {
          return;
        }

        var chatBox = $('#task-chat-messages-box').last();
        var isSameTask = (parseInt(window.currentChatTaskId, 10) === taskId);
        var hasMessages = chatBox.find('#text-messages .msg-div').length > 0;

        // Same task with messages already showing — ensure live poll is running
        if (isSameTask && hasMessages) {
          if (!window.taskChatPollingInterval && typeof window.startTaskChatPollingWithInterval === 'function') {
            window.startTaskChatPollingWithInterval();
          }
          window.taskChatLoadingInProgress = null;
          return;
        }

        window.taskChatLoadingInProgress = taskId;

        // Fast path: chat shell already loaded — skip heavy HTML reload, just fetch messages
        if (chatBox.find('#text-messages').length > 0) {
          if (parseInt(window.currentChatTaskId, 10) !== taskId) {
            // Clear every task-chat transcript (page may include #task-sidebar twice)
            $('#task-chat-messages-box #text-messages').attr('data-task-id', taskId).empty();
            if (window.taskChatInstances && window.currentChatTaskId) {
              delete window.taskChatInstances[window.currentChatTaskId];
            }
            if (window.taskChatInstances && window.taskChatInstances[taskId]) {
              delete window.taskChatInstances[taskId];
            }
            if (window.taskChatLoadedTasks) {
              window.taskChatLoadedTasks.length = 0;
            }
          }
          window.currentChatTaskId = taskId;
          window.TASK_CHAT_ID = taskId;
          if (typeof window.initTaskChatSession === 'function') {
            window.initTaskChatSession(taskId, true);
          }
          setTimeout(function() {
            window.taskChatLoadingInProgress = null;
          }, 300);
          return;
        }
        
        var baseUrl = '';
        if (typeof window.baseUrl !== 'undefined' && window.baseUrl) {
          baseUrl = window.baseUrl;
        } else if (typeof url !== 'undefined' && url) {
          baseUrl = url;
        } else {
          var path = window.location.pathname || '';
          if (path.includes('/admin/') || path.includes('/client/') || path.includes('/staff/') || path.includes('/mail/')) {
            baseUrl = '../';
          } else {
            baseUrl = '';
          }
        }
        if (baseUrl && baseUrl.charAt(baseUrl.length - 1) !== '/') {
          baseUrl += '/';
        }
        
        $.ajax({
          url: baseUrl + 'real-chat/task_chat_load.php',
          type: 'GET',
          data: { task_id: taskId },
          dataType: 'html',
          success: function(response) {
            var chatBox = $('#task-chat-messages-box').last();

            if (window.taskChatInstances && window.taskChatInstances[taskId]) {
              delete window.taskChatInstances[taskId];
            }

            // Always clear old chat when switching tasks
            if (parseInt(window.currentChatTaskId, 10) !== taskId) {
              chatBox.empty();
              if (window.taskChatInstances && window.currentChatTaskId) {
                delete window.taskChatInstances[window.currentChatTaskId];
              }
            }

            if (window.taskChatLoadedTasks) {
              window.taskChatLoadedTasks.length = 0;
            }

            var inputField = chatBox.find('.type-a-message-box');
            var savedInputValue = inputField.length ? inputField.val() : '';
            var savedSelectionStart = inputField.length ? inputField[0].selectionStart : 0;
            var savedSelectionEnd = inputField.length ? inputField[0].selectionEnd : 0;

            window.currentChatTaskId = taskId;
            window.TASK_CHAT_ID = taskId;
            chatBox.html(response);
            if (typeof window.ensureTaskChatDropperReady === 'function') {
              window.ensureTaskChatDropperReady();
            }
            if (typeof window.chatInitComposerTooltips === 'function') {
              window.chatInitComposerTooltips(chatBox.find('.send-box').get(0) || chatBox.get(0));
            }
            if (window.ChatComposeAi && typeof window.ChatComposeAi.init === 'function') {
              window.ChatComposeAi.init();
            }
            if (typeof window.chatVoiceSyncMicSend === 'function') {
              window.chatVoiceSyncMicSend();
            }

            function tryInit(attempt) {
              if (typeof window.initTaskChatSession === 'function') {
                window.initTaskChatSession(taskId, true);
              } else if (attempt < 30) {
                setTimeout(function() { tryInit(attempt + 1); }, 100);
              }
            }
            setTimeout(function() { tryInit(0); }, 50);
            
            // Restore input field value after HTML replacement
            if (savedInputValue) {
              // Input field was replaced, find the new one and restore value
              setTimeout(function() {
                var newInputField = chatBox.find('.type-a-message-box');
                if (newInputField.length) {
                  newInputField.val(savedInputValue);
                  // Restore cursor position
                  try {
                    newInputField[0].setSelectionRange(savedSelectionStart, savedSelectionEnd);
                  } catch(e) {
                    // Fallback: set cursor to end if selection range fails
                    var len = savedInputValue.length;
                    newInputField[0].setSelectionRange(len, len);
                  }
                  newInputField.focus();
                }
              }, 50);
            }
            
            // Clear loading flag quickly
            setTimeout(function() {
              window.taskChatLoadingInProgress = null;
            }, 300);
          },
          error: function(xhr, status, error) {
            $('#task-chat-messages-box').html(
              '<div class="alert alert-danger">Error loading chat: ' + error + '</div>'
            );
            window.taskChatLoadingInProgress = null;
          }
        });
      }
      window.loadChatForTask = loadChatForTask;
    });
  }
  
  initTaskChatWatch();
})();
</script>
<?php
$taskSidebarBase = isset($url) ? $url : '../';
$loadEmailNotifModal = !function_exists('comon_page_asset_enabled') || comon_page_asset_enabled('email_notification_modal');
if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
  $loadEmailNotifModal = false;
}
if ($loadEmailNotifModal && !defined('CHAT_EMAIL_MODAL_V1')) {
  define('CHAT_EMAIL_MODAL_V1', true);
  if (!isset($lang)) { $lang = array(); }
  include dirname(__FILE__) . '/modals/email-notification.php';
}
?>
<script>
(function () {
  var u = <?php echo json_encode($taskSidebarBase, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  if (typeof window.url === 'undefined' || window.url === '' || window.url === '/') {
    window.url = u;
  }
})();
</script>
<?php
/* One script load per page: task-sidebar is often included twice (header + page) */
if ($loadEmailNotifModal && !defined('CHAT_EMAIL_NOTIF_JS_V1')) {
  define('CHAT_EMAIL_NOTIF_JS_V1', true);
  echo '<script src="' . htmlspecialchars($taskSidebarBase) . 'assets/js/notification/email-notification-modal.js"></script>' . "\n";
}
?>
 
