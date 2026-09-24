<?php
/*
 ================================================================================
   Task Session â€“ Project Management System
   File    : edit_task.php
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once __DIR__ . '/../includes/custom-fields/project_task_values.php';
require_once __DIR__ . '/../includes/task_recurrence_helper.php';

// Set page title
$system_title = $system_title ?? 'My Application';
$title        = "Edit Task | " . $system_title;

// Only allow logged-in clients
if (! $session->isLoggedIn() || $_SESSION['accountStatus'] != 2) redirectTo($url."index");

// Get current client info
$client_id = $session->userId;
$user = User::findById((int)$client_id);
$username = $user->firstName;
$email = $user->email;

// Load permissions
require_once("../includes/task_permission.php");
$permissions = TaskPermission::getOrCreate($client_id);
if (!$permissions || !$permissions->can_update_task) {
    redirectTo($url."client/projects?message=permission_denied");
}

// Get task ID from URL
$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Load task model
require_once("../includes/task.php");
$task = Task::findById($taskId);
if (!$task) {
    redirectTo($url."client/projects?message=task_not_found");
}

// Verify this task's project belongs to this client (main client or additional client)
require_once("../includes/projects.php");
$project = projects::findByProjectId($task->project_id);
if (!$project) {
    redirectTo($url."client/projects?message=invalid_access");
}

$isClient = false;
if($project->c_id == $client_id || $project->main_client_id == $client_id) {
    $isClient = true;
} elseif(!empty($project->c_ids)) {
    $allClientIds = array_filter(explode(',', $project->c_ids));
    if(in_array($client_id, $allClientIds)) {
        $isClient = true;
    }
}

if(!$isClient){
    redirectTo($url."client/projects?message=invalid_access");
}
$projTitle = $project->project_title;

$taskCfInitialValues = [];
if ($task && $taskId > 0) {
    $cfUid = (int)$taskId;
    $cfEnt = 'task';
    $cfStmt = @$connect->prepare(
        'SELECT cf.id, cf.field_type, cfv.field_value FROM custom_fields cf
         INNER JOIN task_custom_field_values cfv ON cf.id = cfv.custom_field_id AND cfv.task_id = ?
         WHERE cf.entity_type = ?'
    );
    if ($cfStmt) {
        $cfStmt->bind_param('is', $cfUid, $cfEnt);
        if ($cfStmt->execute()) {
            $cfRes = $cfStmt->get_result();
            if ($cfRes) {
                while ($cfRow = $cfRes->fetch_assoc()) {
                    $fid = (int)$cfRow['id'];
                    $ft = (string)$cfRow['field_type'];
                    $fv = $cfRow['field_value'];
                    $val = $fv;
                    if ($ft === 'multiple_select' && $fv !== null && $fv !== '') {
                        $d = json_decode($fv, true);
                        $val = is_array($d) ? $d : [];
                    }
                    $taskCfInitialValues[] = ['id' => $fid, 'field_type' => $ft, 'value' => $val];
                }
            }
        }
        $cfStmt->close();
    }
}

// Process form submission
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server-side validation (required fields)
    $validationErrors = [];

    $title_raw = trim($_POST['task_title'] ?? '');
    if ($title_raw === '') {
        $validationErrors[] = 'Task Title is required.';
    }

    // Start Date is LOCKED on client edit: use existing task start date as source of truth
    $start_date_locked = $task->start_date ?? '';
    if (empty($start_date_locked)) {
        $validationErrors[] = 'Start Date is missing on this task.';
    }

    // Due Date is editable and required
    $due_date_raw = $_POST['due_date'] ?? '';
    if (empty($due_date_raw)) {
        $validationErrors[] = 'Due Date is required.';
    }

    // Validate dates (allow start date in the past; only enforce due date rules)
    $startDt = !empty($start_date_locked) ? DateTime::createFromFormat('Y-m-d', $start_date_locked) : null;
    $dueDt = !empty($due_date_raw) ? DateTime::createFromFormat('Y-m-d', $due_date_raw) : null;
    $todayDt = new DateTime('today');

    if ($startDt && $startDt->format('Y-m-d') !== $start_date_locked) {
        $validationErrors[] = 'Start Date is invalid.';
        $startDt = null;
    }

    if ($dueDt && $dueDt->format('Y-m-d') !== $due_date_raw) {
        $validationErrors[] = 'Due Date is invalid.';
        $dueDt = null;
    }

    if ($startDt && $dueDt && $dueDt < $startDt) {
        $validationErrors[] = 'Due Date cannot be before Start Date.';
    }

    // Staff assignment required only if client has permission to assign members (and field is shown)
    $assignedIds = [];
    if ($permissions->can_assign_members) {
        $assignedRaw = $_POST['assigned_to'] ?? [];
        $assignedVals = is_array($assignedRaw) ? $assignedRaw : [$assignedRaw];
        foreach ($assignedVals as $val) {
            $val = (string)$val;
            foreach (explode(',', $val) as $piece) {
                $piece = trim($piece);
                if ($piece === '' || $piece === '0') continue;
                if (ctype_digit($piece) && (int)$piece > 0) {
                    $assignedIds[] = (int)$piece;
                }
            }
        }
        $assignedIds = array_values(array_unique($assignedIds));
        if (empty($assignedIds)) {
            $validationErrors[] = 'Please select at least one team member or admin.';
        }
    }

    if (!empty($validationErrors)) {
        $message = "<div class='alert alert-danger'><i class='fa fa-exclamation-circle'></i> <strong>Validation Error:</strong><ul>";
        foreach ($validationErrors as $err) {
            $message .= "<li>" . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "</li>";
        }
        $message .= "</ul></div>";
    } else {
        // Apply updates after validation
        $task->title = $title_raw;
        $task->description = sanitize_tinymce_content($_POST['task_description'] ?? '');

        $original_status = $task->status;
        if ($permissions->can_change_status && isset($_POST['task_status'])) {
            $task->status = $_POST['task_status'];
        }

        if ($permissions->can_assign_members) {
            $task->assigned_to = implode(',', $assignedIds);
        }

        // Start date is locked: do NOT change it on client edit
        $task->due_date = $due_date_raw;

        $result = $task->save();
        if ($result === true || $result === 0) {
            $postedCf = isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : null;
            save_task_custom_field_values($connect, (int)$task->id, $postedCf);

            tasksession_recurrence_save_from_post($task);
            tasksession_recurrence_maybe_spawn_on_done($task);

            require_once('../includes/notification_helper.php');
            NotificationHelper::taskUpdated($task->id, $task->title, $session->userId, $task->assigned_to, $task->project_id);
            if ($permissions->can_change_status && isset($_POST['task_status']) && $_POST['task_status'] !== $original_status) {
                NotificationHelper::taskStatusChanged($task->id, $task->title, $session->userId, $_POST['task_status'], $task->assigned_to, $task->project_id);
            }
            $message = "<div class='alert alert-success'><i class='fa fa-check-circle'></i> " . $lang['Task updated successfully!'] . "</div>";
        } else {
            $message = "<div class='alert alert-danger'><i class='fa fa-exclamation-circle'></i> " . $lang['Failed to update task'] . ": " . $result . "</div>";
        }
    }
}

// Get staff members assigned to this project
$assignedStaff = [];
if ($project) {
    $assignedStaffIds = explode(',', $project->s_ids);
    foreach ($assignedStaffIds as $id) {
        if ($id > 0 && $id != $project->c_id) {
            $staffUser = User::findById($id);
            if ($staffUser && ($staffUser->accountStatus == 3 || $staffUser->accountStatus == 1)) {
                $assignedStaff[$id] = $staffUser;
            }
        }
    }
}

// Build staff list for dropdown (like admin version)
$staffList = [];
foreach ($assignedStaff as $staffId => $staffUser) {
    $filenamePicture = getUserAvatarHtml($staffUser->id, $staffUser->firstName, $staffUser->lastName ?? '', 28, 28, 'rounded-circle', $staffUser->firstName);
    $fullName = $staffUser->firstName;
    if (!empty($staffUser->lastName)) {
        $fullName .= ' ' . $staffUser->lastName;
    }
    $staffList[] = [
        'id'    => (int)$staffUser->id,
        'name'  => htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
        'image' => $filenamePicture,
        'accountStatus' => (int)$staffUser->accountStatus
    ];
}

$selectedStaffIds = array_filter(explode(',', $task->assigned_to));
$estimatedSecondsSelected = (isset($task->estimated_time_seconds) && $task->estimated_time_seconds !== null && $task->estimated_time_seconds !== '')
    ? (int)$task->estimated_time_seconds
    : null;

include("../templates/header.php");
?>
<div class="page-container">
  <div class="container-fluid">
    <div class="row row-eq-height">
      <?php include("../templates/sidebar.php"); ?>
      <div class="page-content">
        <?php include('../templates/top-header.php'); ?>
        <div class="row">
            <div class="col-md-12 center-col add-project">
                <div class="row">
                    <div class="col-md-12">
                        <div class="project-header page-title">
                            <div class="float-btn">
                                <a href="kanban" class="btn">
                                    <?php echo ts_icon('restore', 'w-6'); ?>
                                </a>
                            </div>
                            <h2><?php echo $lang['Edit Task']; ?></h2>
                            <p class="text-muted"><?php echo $lang['Editing task in project']; ?>: <strong><?= htmlspecialchars($projTitle) ?></strong></p>
                        </div>
                    </div>
                </div>
                <?php if (!empty($message)) { echo $message; } ?>
                <div class="add-projects">
                    <form method="post" action="" novalidate data-custom-fields-guard="1">
                        <div class="row">
                            <div class="col-md-12">
                                <input type="hidden" name="project_id" value="<?= $task->project_id ?>">
                                <div class="form-group">
                                    <label><?php echo $lang['Task Title*']; ?></label>
                                    <input type="text" name="task_title" class="form-control" value="<?= htmlspecialchars($task->title) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Description']; ?></label>
                                    <textarea id="description" name="task_description" class="form-control" placeholder="<?php echo $lang['Describe the task']; ?>"><?= htmlspecialchars($task->description) ?></textarea>
                                    <?php
                                    $aiFieldComposeTarget = 'textarea[name="task_description"]';
                                    $aiFieldComposeField = 'task_description';
                                    $aiFieldComposeTitle = 'input[name="task_title"]';
                                    include dirname(__DIR__) . '/templates/partials/ai-field-compose.php';
                                    ?>
                                </div>
                                <?php
                                $clientTimeTrackingOn = tasksession_time_tracking_enabled();
                                $clientShowAssignRow = $permissions->can_assign_members;
                                if ($clientShowAssignRow || $clientTimeTrackingOn): ?>
                                <div class="form-group field-label mb-0">
                                    <div class="row g-3 align-items-start comon-task-estimated-row">
                                        <?php if ($clientShowAssignRow): ?>
                                        <div class="col-12">
                                            <label for="staffDropdownBtn"><?php echo $lang['Assign To (Multiple Staff)']; ?> <span class="text-danger">*</span></label>
                                            <div class="mb-3">
                                                <div class="dropdown">
                                                    <button class="field-btn dropdown-toggle w-100 text-start" type="button" id="staffDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <span id="staffDropdownBtnText"><?php echo $lang['Select Staff members']; ?></span>
                                                    </button>
                                                    <ul class="dropdown-menu w-100" aria-labelledby="staffDropdownBtn" id="staffDropdownMenu" style="max-height: 250px; overflow-y: auto;"></ul>
                                                </div>
                                                <input type="hidden" name="assigned_to[]" id="selectedStaffInput" />
                                                <small id="staff-error" class="text-danger d-none"><?php echo isset($lang['Please select at least one team member or admin']) ? $lang['Please select at least one team member or admin'] : 'Please select at least one team member or admin.'; ?></small>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php
                                        if ($clientTimeTrackingOn) {
                                            $estimatedTimeReadOnly = true;
                                            $estimatedTimeColumnClass = 'col-12';
                                            require dirname(__DIR__) . '/includes/partials/task_estimated_time_fields.php';
                                            unset($estimatedTimeReadOnly, $estimatedTimeColumnClass);
                                        }
                                        ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if ($permissions->can_change_status): ?>
                                <div class="form-group">
                                    <label><?php echo $lang['Status']; ?></label>
                                    <select class="form-control" name="task_status">
                                        <option value="todo" <?= $task->status === 'todo' ? 'selected' : '' ?>><?php echo $lang['To Do']; ?></option>
                                        <option value="inprogress" <?= $task->status === 'inprogress' ? 'selected' : '' ?>><?php echo $lang['In Progress']; ?></option>
                                        <option value="review" <?= $task->status === 'review' ? 'selected' : '' ?>><?php echo $lang['Review']; ?></option>
                                        <option value="done" <?= $task->status === 'done' ? 'selected' : '' ?>><?php echo $lang['Done']; ?></option>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo $lang['Start Date']; ?> <span class="text-danger">*</span></label>
                                            <input type="date" name="start_date_display" id="start_date" class="form-control" value="<?= $task->start_date ? htmlspecialchars($task->start_date) : '' ?>" disabled>
                                            <small class="text-muted"><?php echo isset($lang['Start Date cannot be changed']) ? $lang['Start Date cannot be changed'] : 'Start Date cannot be changed.'; ?></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo $lang['Due Date']; ?> <span class="text-danger">*</span></label>
                                            <input type="date" name="due_date" id="due_date" class="form-control" min="<?php echo !empty($task->start_date) ? htmlspecialchars($task->start_date) : date('Y-m-d'); ?>" value="<?= $task->due_date ? htmlspecialchars($task->due_date) : '' ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                $taskRepeatInitial = $task ? tasksession_recurrence_to_frontend(tasksession_recurrence_find_for_task($task)) : null;
                                require dirname(__DIR__) . '/includes/partials/task_repeat_fields.php';
                                ?>
                                <div id="userProfileCustomFieldsSection" class="form-group full-grid js-user-profile-cf-section d-none" aria-hidden="true">
                                    <div class="staff-heading mt-3"><h4>Custom fields</h4></div>
                                    <div id="userProfileCustomFieldsContainer"></div>
                                </div>
                                <div class="form-group">
                                    <button type="submit" id="edit-task-btn" class="btn primary-btn"><?php echo $lang['Update Task']; ?></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
    // Pass PHP data to JavaScript - make it globally available for task.js
    window.staffList = <?php echo json_encode($staffList); ?>;
    window.selectedStaffIds = <?php echo json_encode($selectedStaffIds); ?>;
    window.isEditMode = true;
    window.canAssignMembers = <?php echo $permissions->can_assign_members ? 'true' : 'false'; ?>;
    window.USER_PROFILE_CF_CONFIG = {
      entityType: 'task',
      listUrl: '../includes/custom-fields/list.php',
      containerSelector: '#userProfileCustomFieldsContainer',
      initialValues: <?php echo json_encode($taskCfInitialValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>,
      i18n: {
        fieldRequired: <?php echo json_encode(isset($lang['This field is required.']) ? $lang['This field is required.'] : 'This field is required.', JSON_HEX_TAG | JSON_HEX_APOS); ?>
      }
    };
</script>
<script src="../assets/js/user-profile-custom-fields-form.js"></script>
<script src="../assets/js/custom-fields-form-guard.js"></script>
<script src="../assets/js/task.js"></script>
<script src="../assets/js/task-repeat.js?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/js/task-repeat.js') ?: '1'; ?>"></script>
<script src="../assets/js/rich-editor.js"></script>
<?php include("../templates/task-repeat-modal-bundle.php"); ?>
<?php $__aiCtx = __DIR__ . '/../includes/ai_contextual_snippet.php';
if (is_file($__aiCtx)) { require_once $__aiCtx; if (function_exists('ai_contextual_emit')) { ai_contextual_emit(); } } ?>
<?php include("../templates/main-footer.php"); ?> 