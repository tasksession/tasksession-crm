<?php
/*
 ================================================================================
   Task Session â€“ Project Management System
   File    : add_task.php
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once __DIR__ . '/../includes/custom-fields/project_task_values.php';
require_once __DIR__ . '/../includes/task_recurrence_helper.php';

// Set page title
$system_title = $system_title ?? 'My Application';
$title        = "Add Task | " . $system_title;

// Only allow logged-in clients
if (! $session->isLoggedIn() || $_SESSION['accountStatus'] != 2) redirectTo($url."");

// Get current client info
$client_id = $session->userId;
$user = User::findById((int)$client_id);
$username = $user->firstName;
$email = $user->email;

// Load permissions (if your system uses them)
require_once("../includes/task_permission.php");
$permissions = TaskPermission::getOrCreate($client_id);
if (!$permissions || !$permissions->can_create_task) {
    redirectTo($url."client/projects?message=permission_denied");
}

// Get projectId from URL or POST
$projectId = isset($_GET['projectId']) ? (int)$_GET['projectId'] : (isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0);

require_once("../includes/projects.php");
$project = null;
if ($projectId > 0) {
    $project = projects::findByProjectId($projectId);
    if (!$project) {
        redirectTo($url."client/projects?message=invalid_access");
    }
    
    // Check if current user is the client for this project (main client or additional client)
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
}

// Only show projects that belong to this client (main client or additional client)
$allProjectsSql = "SELECT * FROM projects WHERE trash = 0 AND (c_id = ? OR main_client_id = ? OR FIND_IN_SET(?, c_ids)) ORDER BY project_title";
$allProjectsStmt = $connect->prepare($allProjectsSql);
$allProjectsStmt->bind_param("iii", $client_id, $client_id, $client_id);
$allProjectsStmt->execute();
$allProjectsResult = $allProjectsStmt->get_result();
$allProjects = [];
while ($row = $allProjectsResult->fetch_assoc()) {
    $projectObj = new projects();
    foreach ($row as $key => $value) {
        if (property_exists($projectObj, $key)) {
            $projectObj->$key = $value;
        }
    }
    $allProjects[] = $projectObj;
}
$allProjectsStmt->close();

// Get staff assigned to the selected project
$assignedStaff = [];
if ($projectId > 0 && $project) {
    $assignedStaffIds = explode(',', $project->s_ids);
    foreach ($assignedStaffIds as $id) {
        if ($id > 0 && $id != $client_id) {
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

// Build a mapping of project_id => array of staff (id, name, image)
$projectStaffMap = [];
foreach ($allProjects as $proj) {
    $staffArr = [];
    $assignedStaffIds = explode(',', $proj->s_ids);
    foreach ($assignedStaffIds as $staffId) {
        $staffUser = User::findById($staffId);
        // Include both staff (accountStatus == 3) and admin (accountStatus == 1) accounts
        if ($staffUser && ($staffUser->accountStatus == 3 || $staffUser->accountStatus == 1)) {
            $filenamePicture = getUserAvatarHtml($staffUser->id, $staffUser->firstName, $staffUser->lastName ?? '', 28, 28, 'rounded-circle', $staffUser->firstName);
            $fullName = $staffUser->firstName;
            if (!empty($staffUser->lastName)) {
                $fullName .= ' ' . $staffUser->lastName;
            }
            
            $staffArr[] = [
                'id'    => (int)$staffUser->id,
                'name'  => htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'),
                'image' => $filenamePicture,
                'accountStatus' => (int)$staffUser->accountStatus
            ];
        }
    }
    $projectStaffMap[$proj->p_id] = $staffArr;
}

// Process form submission
$message = "";
$taskCreatedSuccess = null;
require_once __DIR__ . '/../includes/task_created_success_helper.php';
if (isset($_POST['add-task'])) {
    // Server-side validation (required fields)
    $validationErrors = [];

    $title_raw = trim($_POST['task_title'] ?? '');
    if ($title_raw === '') {
        $validationErrors[] = 'Task Title is required.';
    }

    // Project is required for client task creation
    $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : $projectId;
    if ($projectId <= 0) {
        $validationErrors[] = 'Project is required.';
    }

    // Parse assigned_to[] which may contain a single comma-separated string
    $assignedIds = [];
    $assignedRaw = $_POST['assigned_to'] ?? [];
    $assignedVals = is_array($assignedRaw) ? $assignedRaw : [$assignedRaw];
    foreach ($assignedVals as $val) {
        $val = (string)$val;
        foreach (explode(',', $val) as $piece) {
            $piece = trim($piece);
            if ($piece === '' || $piece === '0') {
                continue;
            }
            if (ctype_digit($piece) && (int)$piece > 0) {
                $assignedIds[] = (int)$piece;
            }
        }
    }
    $assignedIds = array_values(array_unique($assignedIds));
    if (empty($assignedIds)) {
        $validationErrors[] = 'Please select at least one team member or admin.';
    }

    $start_date_raw = $_POST['start_date'] ?? '';
    $due_date_raw = $_POST['due_date'] ?? '';
    if (empty($start_date_raw)) {
        $validationErrors[] = 'Start Date is required.';
    }
    if (empty($due_date_raw)) {
        $validationErrors[] = 'Due Date is required.';
    }

    if (!empty($start_date_raw) && !empty($due_date_raw)) {
        $startDt = DateTime::createFromFormat('Y-m-d', $start_date_raw);
        $dueDt = DateTime::createFromFormat('Y-m-d', $due_date_raw);
        $todayDt = new DateTime('today');

        if (!$startDt || $startDt->format('Y-m-d') !== $start_date_raw) {
            $validationErrors[] = 'Start Date is invalid.';
        }
        if (!$dueDt || $dueDt->format('Y-m-d') !== $due_date_raw) {
            $validationErrors[] = 'Due Date is invalid.';
        }
        if ($startDt && $startDt < $todayDt) {
            $validationErrors[] = 'Start Date cannot be in the past.';
        }
        if ($dueDt && $dueDt < $todayDt) {
            $validationErrors[] = 'Due Date cannot be in the past.';
        }
        if ($startDt && $dueDt && $dueDt < $startDt) {
            $validationErrors[] = 'Due Date cannot be before Start Date.';
        }
    }

    if (!empty($validationErrors)) {
        if (tasksession_is_ajax_task_create_request()) {
            tasksession_send_task_create_error_json($validationErrors);
        }
        $message = "<div class='alert alert-danger'><strong>Validation Error:</strong><ul>";
        foreach ($validationErrors as $err) {
            $message .= "<li>" . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "</li>";
        }
        $message .= "</ul></div>";
    } else {
    $task = new Task();
    $task->title = $title_raw;
    $task->description = sanitize_tinymce_content($_POST['task_description'] ?? '');
    $task->project_id = $projectId;
    $task->status = 'todo';
    $task->created_at = date('Y-m-d H:i:s');
    $task->user_id = $client_id;
    $task->creator_id = $client_id;

    // Handle staff assignment (normalized)
    $task->assigned_to = implode(',', $assignedIds);

    // Handle dates (validated above)
    $task->start_date = $start_date_raw;
    $task->due_date = $due_date_raw;
    $task->estimated_time_seconds = tasksession_parse_estimated_time_seconds_from_post();

    // Calculate next position for the new task
    $stmt = $connect->prepare("SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM tasks WHERE project_id = ? AND status = 'todo'");
    $stmt->bind_param("i", $task->project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $position = (int)$result->fetch_assoc()['pos'];
    $stmt->close();
    $task->position = $position;

    if ($task->save()) {
        $postedCf = isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : null;
        save_task_custom_field_values($connect, (int)$task->id, $postedCf);

        if (function_exists('tasksession_seed_task_schedules')) {
            tasksession_seed_task_schedules((int) $task->id, ['skip_if_user_has_row' => false]);
        }
        tasksession_recurrence_save_from_post($task);

        // Send notifications for task creation
        require_once("../includes/notification_helper.php");
        NotificationHelper::taskCreated($task->id, $task->title, $client_id, $task->assigned_to, $task->project_id);
        
        // Email notification logic (always send emails for task creation)
        $settings = settings::findById(1);
        $task_title = $task->title;
        $task_description = $task->description;
        $due_date = $task->due_date ?? '';
        $project_name = 'Internal Task';
        
        // Get project name if task is assigned to a project
        if ($task->project_id > 0) {
            require_once("../includes/projects.php");
            $project = projects::findByProjectId($task->project_id);
            if ($project) {
                $project_name = $project->project_title;
            }
        }

        // Notify assigned staff members
        if (!empty($task->assigned_to)) {
            $assigned_staff_ids = explode(',', $task->assigned_to);
            foreach ($assigned_staff_ids as $staff_id) {
                if ($staff_id > 0) {
                    $staffUser = User::findById($staff_id);
                    if ($staffUser && filter_var($staffUser->email, FILTER_VALIDATE_EMAIL)) {
                        $subject = 'New Task Assignment';
                        $variablesArr = array(
                            '{USER_NAME}'        => htmlspecialchars($staffUser->firstName, ENT_QUOTES, 'UTF-8'),
                            '{TASK_TITLE}'       => htmlspecialchars($task_title, ENT_QUOTES, 'UTF-8'),
                            '{PROJECT_NAME}'     => htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8'),
                            '{TASK_DESCRIPTION}' => $task_description,
                            '{DUE_DATE}'         => htmlspecialchars($due_date, ENT_QUOTES, 'UTF-8'),
                            '{DASHBOARD_URL}'    => $url,
                            '{SIGNATURE}'        => $company_name
                        );
                        $templateHTML = $settings->task_create_email;
                        
                        // Check if template is empty
                        if (empty($templateHTML)) {
                            error_log("Email template is empty for task creation staff notification: " . $staffUser->email);
                            continue;
                        }
                        
                        $messageBody = strtr($templateHTML, $variablesArr);

                        $headers  = 'MIME-Version: 1.0' . "\r\n";
                        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
                        $headers .= 'From: ' . $company_name . ' <' . $system_email . '>' . "\r\n";

                        $emailSent = @mail($staffUser->email, $subject, $messageBody, $headers);
                        if (!$emailSent) {
                            error_log("Failed to send task creation email to staff: " . $staffUser->email);
                        }
                    }
                }
            }
        }

        // Notify all clients if task is assigned to a project
        if ($task->project_id > 0 && $project) {
            // Get main client ID
            $main_client_id = $project->main_client_id ?: $project->c_id;
            
            // Get all client IDs (main + additional)
            $all_client_ids = [];
            if (!empty($project->c_ids)) {
                $all_client_ids = array_filter(explode(',', $project->c_ids));
            }
            
            // If no additional clients, just use main client
            if (empty($all_client_ids)) {
                $all_client_ids = [$main_client_id];
            }
            
            // Send email to all clients (excluding the creator)
            $creator_id = $client_id;
            foreach ($all_client_ids as $client_user_id) {
                if ($client_user_id != $creator_id) { // Don't email the creator
                    $clientUser = User::findById($client_user_id);
                    if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL)) {
                        $subject = 'New Task Created in Your Project';
                        $variablesArr = array(
                            '{USER_NAME}'        => htmlspecialchars($clientUser->firstName, ENT_QUOTES, 'UTF-8'),
                            '{TASK_TITLE}'       => htmlspecialchars($task_title, ENT_QUOTES, 'UTF-8'),
                            '{PROJECT_NAME}'     => htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8'),
                            '{TASK_DESCRIPTION}' => $task_description,
                            '{DUE_DATE}'         => htmlspecialchars($due_date, ENT_QUOTES, 'UTF-8'),
                            '{DASHBOARD_URL}'    => $url,
                            '{SIGNATURE}'        => $company_name
                        );
                        $templateHTML = $settings->task_create_email;
                        
                        // Check if template is empty
                        if (empty($templateHTML)) {
                            error_log("Email template is empty for task creation client notification: " . $clientUser->email);
                            continue;
                        }
                        
                        $messageBody = strtr($templateHTML, $variablesArr);

                        $headers  = 'MIME-Version: 1.0' . "\r\n";
                        $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
                        $headers .= 'From: ' . $company_name . ' <' . $system_email . '>' . "\r\n";

                        $emailSent = @mail($clientUser->email, $subject, $messageBody, $headers);
                        if (!$emailSent) {
                            error_log("Failed to send task creation email to client: " . $clientUser->email);
                        }
                    }
                }
            }
        }
        
        if (tasksession_is_ajax_task_create_request()) {
            tasksession_send_task_created_json($task, $assignedIds);
        }
        $taskCreatedSuccess = tasksession_build_task_created_payload($task, $assignedIds);
    } else {
        global $database;
        $saveError = 'Task could not be created at this time.';
        if (tasksession_is_ajax_task_create_request()) {
            tasksession_send_task_create_error_json([$saveError . ' ' . $database->error()], 500);
        }
        $message = "<p class='alert alert-danger'>" . htmlspecialchars($saveError, ENT_QUOTES, 'UTF-8') . "<br>Error: " . htmlspecialchars($database->error(), ENT_QUOTES, 'UTF-8') . "</p>";
    }
    }
}
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
                            <h2><?php echo $lang['Add New Task']; ?></h2>
                            <?php if ($projectId > 0 && $project): ?>
                                <p class="text-muted"><?php echo $lang['Adding task to project']; ?> <strong><?= htmlspecialchars($project->project_title) ?></strong></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php if (!empty($message)) { echo $message; } ?>
                <div class="add-projects">
                    <form method="post" action="" novalidate data-custom-fields-guard="1" data-task-created-modal="1">
                        <!-- Ensure POST handler triggers even if submit button is disabled by spinner JS -->
                        <input type="hidden" name="add-task" value="1">
                        <div class="row">
                            <div class="col-md-12">
                                <!-- Project selection (required) -->
                                <div class="form-group">
                                   <label><?php echo $lang['Project']; ?></label>
                                    <select name="project_id" class="form-control" required id="clientProjectSelect">
                                        <option value=""><?php echo $lang['Select Project']; ?></option>
                                        <?php foreach ($allProjects as $proj): ?>
                                            <option value="<?= $proj->p_id ?>" <?= ($projectId == $proj->p_id) ? 'selected' : '' ?>><?= htmlspecialchars($proj->project_title) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Task Title*']; ?></label>
                                    <input type="text" name="task_title" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang['Description']; ?></label>
                                    <textarea id="task_description" name="task_description" class="form-control" placeholder="<?php echo $lang['Describe the task']; ?>"></textarea>
                                    <?php
                                    $aiFieldComposeTarget = 'textarea[name="task_description"]';
                                    $aiFieldComposeField = 'task_description';
                                    $aiFieldComposeTitle = 'input[name="task_title"]';
                                    include dirname(__DIR__) . '/templates/partials/ai-field-compose.php';
                                    ?>
                                </div>
                                <div class="form-group field-label mb-0">
                                    <div class="row g-3 align-items-start comon-task-estimated-row">
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
                                        <?php
                                        $estimatedSecondsSelected = null;
                                        require dirname(__DIR__) . '/includes/partials/task_estimated_time_fields.php';
                                        ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo $lang['Start Date']; ?> <span class="text-danger">*</span></label>
                                            <input type="date" name="start_date" id="start_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label><?php echo $lang['Due Date']; ?> <span class="text-danger">*</span></label>
                                            <input type="date" name="due_date" id="due_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                $taskRepeatInitial = null;
                                require dirname(__DIR__) . '/includes/partials/task_repeat_fields.php';
                                ?>
                                <div id="userProfileCustomFieldsSection" class="form-group full-grid js-user-profile-cf-section d-none" aria-hidden="true">
                                    <div class="staff-heading mt-3"><h4>Custom fields</h4></div>
                                    <div id="userProfileCustomFieldsContainer"></div>
                                </div>
                                <div class="form-group">
                                    <button type="submit" name="add-task" id="create-task-btn" class="btn primary-btn"><?php echo $lang['Create Task']; ?></button>
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
    window.staffList = <?php echo json_encode($staffList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
    window.projectStaffMap = <?php echo json_encode($projectStaffMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}'; ?>;
    window.allProjects = [
        <?php foreach ($allProjects as $proj): ?>
            { id: <?= (int)$proj->p_id ?>, name: <?= json_encode($proj->project_title) ?> },
        <?php endforeach; ?>
    ];
    window.selectedProjectId = <?= (int)$projectId ?>;
    window.isEditMode = false;

    window.USER_PROFILE_CF_CONFIG = {
      entityType: 'task',
      listUrl: '../includes/custom-fields/list.php',
      containerSelector: '#userProfileCustomFieldsContainer',
      initialValues: [],
      i18n: {
        fieldRequired: <?php echo json_encode(isset($lang['This field is required.']) ? $lang['This field is required.'] : 'This field is required.', JSON_HEX_TAG | JSON_HEX_APOS); ?>
      }
    };
    window.comonSuccessModalConfig = {
        titleDefault: <?php echo json_encode($lang['Your task is created'] ?? 'Your task is created', JSON_HEX_TAG | JSON_HEX_APOS); ?>,
        viewTaskLabel: <?php echo json_encode(trim($lang['View Task'] ?? 'View task'), JSON_HEX_TAG | JSON_HEX_APOS); ?>,
        goToKanbanLabel: <?php echo json_encode($lang['Go to kanban'] ?? 'Go to kanban', JSON_HEX_TAG | JSON_HEX_APOS); ?>
    };
</script>
<?php include("../templates/comon-success-modal-bundle.php"); ?>
<?php include("../templates/task-repeat-modal-bundle.php"); ?>
<?php include("../templates/main-footer.php"); ?>
<script src="../assets/js/user-profile-custom-fields-form.js"></script>
<script src="../assets/js/custom-fields-form-guard.js"></script>
<script src="../assets/js/rich-editor.js"></script>
<script src="../assets/js/task.js"></script>
<script src="../assets/js/task-repeat.js?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/js/task-repeat.js') ?: '1'; ?>"></script>
<?php if (!empty($taskCreatedSuccess)): ?>
<script>
(function () {
    function showCreatedModal() {
        if (typeof window.showTaskCreatedSuccessModal !== 'function') return;
        var payload = <?php echo json_encode($taskCreatedSuccess, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
        window.showTaskCreatedSuccessModal({
            subtitle: payload.task && payload.task.title ? payload.task.title : '',
            assignees: payload.assignees || [],
            taskId: payload.task && payload.task.id ? payload.task.id : 0,
            taskUrl: payload.task_url || '',
            kanbanUrl: payload.kanban_url || 'kanban'
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showCreatedModal);
    } else {
        showCreatedModal();
    }
})();
</script>
<?php endif; ?>
<?php if (tasksession_time_tracking_enabled()) : ?>
<script src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>assets/js/task-estimated-form.js?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/js/task-estimated-form.js') ?: '1'; ?>"></script>
<?php endif; ?>
