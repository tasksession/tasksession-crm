<?php
/*
 ================================================================================
   Task Session â€“ Project Management System
   File    : edit_task.php
   Purpose : Handles task editing and updates
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/email_helper.php");
require_once("../includes/calendar_event.php");
$__gcalSvc = __DIR__ . '/../includes/google_calendar_service.php';
if (is_file($__gcalSvc)) { require_once $__gcalSvc; }
require_once __DIR__ . '/../includes/task_recurrence_helper.php';
$title = "Edit Task | ". $syatem_title;

function admin_edit_task_safe_redirect($target) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (function_exists('redirectTo')) {
        redirectTo($target);
    } elseif (!headers_sent()) {
        if (function_exists('tasksession_pretty_redirect_location')) {
            $target = tasksession_pretty_redirect_location($target);
        }
        header('Location: ' . $target);
    }
    exit;
}

// auth (before any HTML output so POST redirects work on production)
if (! $session->isLoggedIn()) {
    header('Location: ' . $url . 'index');
    exit;
}
if ($_SESSION['accountStatus'] == 2) {
    header('Location: ' . $url . 'client/index');
    exit;
}
if ($_SESSION['accountStatus'] == 3) {
    header('Location: ' . $url . 'staff/index');
    exit;
}


// Get current user info
$id = $session->userId; 
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;

// Initialize EmailHelper
$settings = settings::findById(1);
$emailHelper = new EmailHelper($settings);

// Load task model
require_once("../includes/task.php");

// Initialize variables
$message = "";
$task = null;
$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$source = isset($_GET['source']) ? trim((string)$_GET['source']) : '';
$returnUrl = isset($_GET['return']) ? trim((string)$_GET['return']) : '';

// Load the task
if ($taskId > 0) {
    $task = Task::findById($taskId);
    if (!$task) {
        $message = "<div class='alert alert-danger'>" . $lang['Task not found!'] . "</div>";
    }
} else {
    redirectTo($url."admin/index");
}

$taskCfInitialValues = [];
if ($task && $taskId > 0) {
    $cfUid = (int) $taskId;
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
                    $fid = (int) $cfRow['id'];
                    $ft = (string) $cfRow['field_type'];
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

// Helper to get display name for a status
function getStatusDisplayName($status, $userId = null) {
    global $database;
    $query = "SELECT custom_name FROM project_columns WHERE column_key = '" . $database->escapeValue($status) . "'";
    if ($userId !== null) {
        $query .= " AND (user_id IS NULL OR user_id = " . intval($userId) . ")";
    }
    $query .= " ORDER BY user_id DESC LIMIT 1";
    $result = $database->query($query);
    if ($row = $database->fetchArray($result)) {
        return $row['custom_name'];
    }
    $defaultLabels = [
        'todo' => 'To Do',
        'inprogress' => 'In Progress',
        'review' => 'In Review',
        'done' => 'Completed'
    ];
    return isset($defaultLabels[$status]) ? $defaultLabels[$status] : ucfirst($status);
}

// Helper to limit words and strip HTML
function limitWords($text, $limit = 20) {
    $plain = strip_tags($text);
    $words = preg_split('/\s+/', $plain);
    if (count($words) > $limit) {
        return implode(' ', array_slice($words, 0, $limit)) . '...';
    }
    return $plain;
}

// Get project information (moved above POST handler to ensure $project is available for emails)
require_once("../includes/projects.php");
$project = $task->project_id > 0 ? projects::findByProjectId($task->project_id) : null;
$projTitle = $project ? $project->project_title : "";
$hasProject = $task->project_id > 0 && $project !== null && $project !== false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Server-side validation (required fields)
    $validationErrors = [];

    $title_raw = trim($_POST['task_title'] ?? '');
    if ($title_raw === '') {
        $validationErrors[] = 'Task Title is required.';
    }

    // Parse assigned_to[] which may contain a single comma-separated string from the hidden input
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

    // Start Date is LOCKED on edit: use existing task start date as source of truth
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

    if (!empty($validationErrors)) {
        $message = "<div class='alert alert-danger'><strong>Validation Error:</strong><ul>";
        foreach ($validationErrors as $err) {
            $message .= "<li>" . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "</li>";
        }
        $message .= "</ul></div>";
    } else {
        // Store original status for comparison (before applying POST value)
        $original_status = $task->status;

        // Update task properties
        $task->title = $title_raw;
        $task->description = sanitize_tinymce_content($_POST['description'] ?? '');
        $task->status = $_POST['task_status'] ?? $task->status;
        
        // Make sure creator_id is set
        $adminId = isset($_SESSION['adminid']) ? $_SESSION['adminid'] : (isset($session->userId) ? $session->userId : 0);
        if (empty($task->creator_id) || $task->creator_id <= 0) {
            $task->creator_id = $adminId;
        }

        // If both user_id and creator_id are empty, set them
        if (empty($task->user_id) || $task->user_id <= 0) {
            $task->user_id = $task->creator_id ?: $adminId;
        }

        // Handle multiple staff assignment (normalized)
        $task->assigned_to = implode(',', $assignedIds);
        
        // Start date is locked: do NOT change it on edit
        $task->due_date = $due_date_raw;

        if (tasksession_time_tracking_enabled() && isset($_POST['estimated_time_seconds'])) {
            $rawEst = trim((string)$_POST['estimated_time_seconds']);
            if ($rawEst === '' || $rawEst === '0') {
                $task->estimated_time_seconds = null;
            } elseif (ctype_digit($rawEst)) {
                $ne = (int)$rawEst;
                $task->estimated_time_seconds = $ne > 0 ? $ne : null;
            }
        }
        
        // Save changes
        $result = $task->save();
        if ($result === true || $result === 0) {
            try {
            require_once __DIR__ . '/../includes/custom-fields/project_task_values.php';
            $postedCf = isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : null;
            save_task_custom_field_values($connect, (int) $task->id, $postedCf);

            if (function_exists('tasksession_seed_task_schedules')) {
                tasksession_seed_task_schedules((int) $task->id, ['skip_if_user_has_row' => true]);
            }
            tasksession_recurrence_save_from_post($task);
            tasksession_recurrence_maybe_spawn_on_done($task);

            if (class_exists('GoogleCalendarService') && GoogleCalendarService::moduleReady()) {
                $gcSettings = GoogleCalendarService::getSettings();
                if (!empty($gcSettings['task_sync_enabled'])) {
                    $syncEventId = CalendarEvent::upsertTaskSyncEvent((int)$task->id, (int)$session->userId);
                    if ($syncEventId > 0) {
                        $syncEvent = CalendarEvent::findEventById($syncEventId, (int)$session->userId);
                        if ($syncEvent) {
                            $push = GoogleCalendarService::pushEvent((int)$session->userId, $syncEvent);
                            if ($push['ok']) {
                                $googleId = (string)($push['data']['id'] ?? '');
                                $etag = (string)($push['data']['etag'] ?? '');
                                $calendarId = (string)($push['calendar_id'] ?? '');
                                $syncEventIdInt = (int)$syncEventId;
                                $userIdInt = (int)$session->userId;
                                $stmt = $connect->prepare("UPDATE calendar_events SET google_event_id = ?, google_calendar_id = ?, google_etag = ?, sync_state = 'synced', last_synced_at = NOW() WHERE id = ? AND user_id = ?");
                                if ($stmt) {
                                    $stmt->bind_param('sssii', $googleId, $calendarId, $etag, $syncEventIdInt, $userIdInt);
                                    $stmt->execute();
                                    $stmt->close();
                                }
                                GoogleCalendarService::logSync((int)$session->userId, 'push', 'task', (int)$task->id, $googleId !== '' ? $googleId : null, 'success', 'Task updated synced');
                            } else {
                                GoogleCalendarService::logSync((int)$session->userId, 'push', 'task', (int)$task->id, null, 'error', $push['error'] ?? 'push_failed');
                            }
                        }
                    }
                }
            }
            // Create notification for task update
            require_once('../includes/notification_helper.php');
            NotificationHelper::taskUpdated($task->id, $task->title, $session->userId, $task->assigned_to, $task->project_id);
            
            // Check if status changed
            if (isset($_POST['task_status']) && $_POST['task_status'] !== $original_status) {
                NotificationHelper::taskStatusChanged($task->id, $task->title, $session->userId, $_POST['task_status'], $task->assigned_to, $task->project_id);
            }
            if (isset($_POST['notifyTaskUpdate'])) {
                $settings = settings::findById(1);
                $task_title = $task->title;
                $task_description = $task->description;
                $due_date = $task->due_date;
                $project_name = $projTitle;
                $task_status_display = getStatusDisplayName($task->status, $id);
                // Notify assigned staff members
                if (!empty($task->assigned_to)) {
                    $assigned_staff_ids = explode(',', $task->assigned_to);
                    foreach ($assigned_staff_ids as $staff_id) {
                        if ($staff_id > 0) {
                            $staffUser = User::findById($staff_id);
                            if ($staffUser && filter_var($staffUser->email, FILTER_VALIDATE_EMAIL)) {
                                $variablesArr = array(
                                    '{USER_NAME}'        => htmlspecialchars($staffUser->firstName, ENT_QUOTES, 'UTF-8'),
                                    '{TASK_TITLE}'       => htmlspecialchars($task_title, ENT_QUOTES, 'UTF-8'),
                                    '{PROJECT_NAME}'     => htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8'),
                                    '{TASK_DESCRIPTION}' => limitWords($task_description, 20),
                                    '{TASK_STATUS}'      => $task_status_display,
                                    '{DUE_DATE}'         => htmlspecialchars($due_date, ENT_QUOTES, 'UTF-8'),
                                    '{DASHBOARD_URL}'    => $url,
                                    '{SIGNATURE}'        => $company_name
                                );
                                $templateHTML = $settings->task_update_email;
                                
                                // Check if template is empty
                                if (empty($templateHTML)) {
                                    error_log("Email template is empty for task update staff notification: " . $staffUser->email);
                                    continue;
                                }
                                
                                $messageBody = strtr($templateHTML, $variablesArr);
                                $subject = 'Task status has been updated in your project!';
                                // Use centralized email helper
                                $emailSent = $emailHelper->sendTemplateEmail($staffUser->email, $subject, $templateHTML, $variablesArr);
                                if (!$emailSent) {
                                    error_log("Failed to send task update email to staff: " . $staffUser->email);
                                }
                            }
                        }
                    }
                }
                // Send email to all clients if task is linked to a project
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
                    
                    // Send email to all clients
                    foreach ($all_client_ids as $client_id) {
                        $clientUser = User::findById($client_id);
                        if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL)) {
                            $variablesArr = array(
                                '{USER_NAME}'        => htmlspecialchars($clientUser->firstName, ENT_QUOTES, 'UTF-8'),
                                '{TASK_TITLE}'       => htmlspecialchars($task_title, ENT_QUOTES, 'UTF-8'),
                                '{PROJECT_NAME}'     => htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8'),
                                '{TASK_DESCRIPTION}' => limitWords($task_description, 20),
                                '{TASK_STATUS}'      => $task_status_display,
                                '{DUE_DATE}'         => htmlspecialchars($due_date, ENT_QUOTES, 'UTF-8'),
                                '{DASHBOARD_URL}'    => $url,
                                '{SIGNATURE}'        => $company_name
                            );
                            $templateHTML = $settings->task_update_email;
                            
                            // Check if template is empty
                            if (empty($templateHTML)) {
                                error_log("Email template is empty for task update client notification: " . $clientUser->email);
                                continue;
                            }
                            
                            $messageBody = strtr($templateHTML, $variablesArr);
                            $subject = 'Task status has been updated in your project!';
                            // Use centralized email helper
                            $emailSent = $emailHelper->sendTemplateEmail($clientUser->email, $subject, $templateHTML, $variablesArr);
                            if (!$emailSent) {
                                error_log("Failed to send task update email to client: " . $clientUser->email);
                            }
                        }
                    }
                }
            }
            } catch (Throwable $postSaveErr) {
                error_log('[admin/edit_task] post-save side effect: ' . $postSaveErr->getMessage());
            }
            // Redirect back (HTTP redirect before any HTML â€” shows toast on destination page)
            $redirectTarget = 'task?message=updated';
            if ($source === 'calendar') {
                $safeReturn = '';
                if ($returnUrl !== '') {
                    $parsed = @parse_url($returnUrl);
                    $path = (string)($parsed['path'] ?? '');
                    $host = (string)($parsed['host'] ?? '');
                    $currentHost = (string)($_SERVER['HTTP_HOST'] ?? '');
                    $hostOk = ($host === '' || strcasecmp($host, $currentHost) === 0);
                    if ($path !== '' && $hostOk && preg_match('#/admin/[a-zA-Z0-9_\-]+\.php#', $path)) {
                        $safeReturn = $returnUrl;
                    }
                }

                if ($safeReturn !== '') {
                    $sep = (strpos($safeReturn, '?') !== false) ? '&' : '?';
                    $redirectTarget = $safeReturn . $sep . 'message=updated';
                } else {
                    $redirectTarget = 'calendar?message=updated';
                }
            } elseif ($task->project_id > 0) {
                $redirectTarget = 'task?projectId=' . (int)$task->project_id . '&message=updated';
            }
            admin_edit_task_safe_redirect($redirectTarget);
        } else {
            $message = "<div class='alert alert-danger'>" . $lang['Failed to update task'] . ": " . $result . "</div>";
        }
    }
}

include("../templates/header.php");

// auth (GET fallbacks)
if (! $session->isLoggedIn()) redirectTo($url."index");
if ($_SESSION['accountStatus'] == 2) redirectTo($url."client/index");
if ($_SESSION['accountStatus'] == 3) redirectTo($url."staff/index");

// Get staff members assigned to this project
$assignedStaff = [];

if ($hasProject && isset($project->s_ids)) {
    $assignedStaffIds = explode(',', $project->s_ids);
    
    foreach ($assignedStaffIds as $staffId) {
        // Skip empty values and client IDs
        if ($staffId > 0 && $staffId != $project->c_id) {
            $staffUser = User::findById($staffId);
            // Include both staff (accountStatus == 3) and admin (accountStatus == 1) accounts
            if ($staffUser && ($staffUser->accountStatus == 3 || $staffUser->accountStatus == 1)) {
                $assignedStaff[$staffId] = $staffUser;
            }
        }
    }
} else {
    // If no project is linked or it's an internal task, load all staff and admin
    $allStaff = User::findBySql("SELECT * FROM users WHERE (accountStatus = 3 OR accountStatus = 1) AND status = 0");
    foreach ($allStaff as $staffUser) {
        $assignedStaff[$staffUser->id] = $staffUser;
    }
}

// Build staff list for dropdown (like add_task.php)
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
                                        <a href="task.php<?= $hasProject ? '?projectId=' . $task->project_id : '' ?>" class="btn">
                                           <?php echo ts_icon('restore', 'w-6'); ?>
                                        </a>
                                    </div>
                                    <h2><?php echo $lang['Edit Task']; ?></h2>
                                    <?php if ($hasProject): ?>
                                        <p class="text-muted"><?php echo $lang['Editing task in project']; ?>: <strong><?= htmlspecialchars($projTitle) ?></strong></p>
                                    <?php else: ?>
                                        <p class="text-muted"><?php echo $lang['Edit an internal task']; ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($message)) { echo $message; } ?>
                        <div class="add-projects">
                            <form id="editTaskForm" method="post" action="" novalidate data-custom-fields-guard="1">
                                <div class="row">
                                    <div class="col-md-12">
                                        <!-- Project selection (hidden for edit) -->
                                        <?php if ($hasProject): ?>
                                            <input type="hidden" name="project_id" value="<?= $task->project_id ?>">
                                        <?php endif; ?>
                                        <div class="form-group">
                                            <label><?php echo $lang['Task Title*']; ?></label>
                                            <input type="text" name="task_title" class="form-control" value="<?= htmlspecialchars($task->title) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label><?php echo $lang['Description']; ?></label>
                                            <textarea id="description" name="description" class="form-control" placeholder="<?php echo $lang['Describe the task']; ?>"><?= htmlspecialchars($task->description) ?></textarea>
                                            <?php
                                            $aiFieldComposeTarget = 'textarea[name="description"]';
                                            $aiFieldComposeField = 'task_description';
                                            $aiFieldComposeTitle = 'input[name="task_title"]';
                                            include dirname(__DIR__) . '/templates/partials/ai-field-compose.php';
                                            ?>
                                        </div>
                                        <div class="form-group field-label mb-0">
                                            <div class="row g-3 align-items-start comon-task-estimated-row">
                                                <div class="col-12">
                                                    <label for="staffDropdownBtn"><?php echo $lang['Assign teammates & admins']; ?> <span class="text-danger">*</span></label>
                                                    <div class="mb-3">
                                                        <div class="dropdown">
                                                            <button
                                                                class="field-btn dropdown-toggle w-100 text-start"
                                                                type="button"
                                                                id="staffDropdownBtn"
                                                                data-bs-toggle="dropdown"
                                                                aria-expanded="false"
                                                            >
                                                                <span id="staffDropdownBtnText"><?php echo $lang['Select Staff members']; ?></span>
                                                            </button>
                                                            <ul
                                                                class="dropdown-menu w-100"
                                                                aria-labelledby="staffDropdownBtn"
                                                                id="staffDropdownMenu"
                                                                style="max-height: 250px; overflow-y: auto;"
                                                            ></ul>
                                                        </div>
                                                        <input
                                                            type="hidden"
                                                            name="assigned_to[]"
                                                            id="selectedStaffInput"
                                                        />
                                                        <small id="staff-error" class="text-danger d-none"><?php echo isset($lang['Please select at least one team member or admin']) ? $lang['Please select at least one team member or admin'] : 'Please select at least one team member or admin.'; ?></small>
                                                    </div>
                                                </div>
                                                <?php require dirname(__DIR__) . '/includes/partials/task_estimated_time_fields.php'; ?>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label><?php echo $lang['Status']; ?></label>
                                            <select class="form-control" name="task_status">
                                                <option value="todo" <?= $task->status === 'todo' ? 'selected' : '' ?>><?php echo $lang['To Do']; ?></option>
                                                <option value="inprogress" <?= $task->status === 'inprogress' ? 'selected' : '' ?>><?php echo $lang['In Progress']; ?></option>
                                                <option value="review" <?= $task->status === 'review' ? 'selected' : '' ?>><?php echo $lang['Review']; ?></option>
                                                <option value="done" <?= $task->status === 'done' ? 'selected' : '' ?>><?php echo $lang['Done']; ?></option>
                                            </select>
                                        </div>
                                        <?php if ($hasProject): ?>
                                        <div class="form-group">
                                            <label><?php echo $lang['Client']; ?></label>
                                            <?php if (isset($project->c_id) && $project->c_id > 0): ?>
                                                <?php $clientUser = User::findById($project->c_id); ?>
                                                <input type="text" class="form-control" value="<?php echo $clientUser ? htmlspecialchars($clientUser->firstName . ' (' . $clientUser->email . ')') : $lang['N/A']; ?>" disabled />
                                            <?php else: ?>
                                                <input type="text" class="form-control" value="<?php echo $lang['No client assigned']; ?>" disabled />
                                            <?php endif; ?>
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
                                        <?php if ($task): ?>
                                        <div id="userProfileCustomFieldsSection" class="form-group full-grid js-user-profile-cf-section d-none" aria-hidden="true">
                                            <div class="staff-heading mt-3"><h4>Custom fields</h4></div>
                                            <div id="userProfileCustomFieldsContainer"></div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="input-notify">
                                            <div class="form-group d-block d-md-flex justify-content-between">
                                                <div class="d-flex col-gap align-items-center">
                                                    <div class="checkbox-wrapper-6">
                                                        <input class="tgl tgl-light" id="notifyTaskUpdate" name="notifyTaskUpdate" type="checkbox" checked />
                                                        <label class="tgl-btn" for="notifyTaskUpdate"></label>
                                                    </div>
                                                    <div>
                                                        <?php
                                                        $notifyTaskTip = !empty($hasProject)
                                                            ? ($lang['Notify assigned staff and client about the task update'] ?? 'Notify assigned staff and client about the task update')
                                                            : ($lang['Notify assigned staff about the task update'] ?? 'Notify assigned staff about the task update');
                                                        ?>
                                                        <label for="notifyTaskUpdate" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                                            <?php echo $lang['Email Notification']; ?>
                                                            <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($notifyTaskTip, ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <button type="submit" id="edit-task-btn" class="btn primary-btn"><?php echo $lang['Update Task']; ?></button>
                                            </div>
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
<?php include("../templates/task-repeat-modal-bundle.php"); ?>
<?php $__aiCtx = __DIR__ . '/../includes/ai_contextual_snippet.php';
if (is_file($__aiCtx)) { require_once $__aiCtx; if (function_exists('ai_contextual_emit')) { ai_contextual_emit(); } } ?>
<?php include("../templates/main-footer.php"); ?>
<?php if ($task): ?>
<script>
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
<?php endif; ?>
<script src="../assets/js/rich-editor.js"></script>
<script src="../assets/js/task.js"></script>
<script src="../assets/js/task-repeat.js?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/js/task-repeat.js') ?: '1'; ?>"></script>
<?php if (tasksession_time_tracking_enabled()) : ?>
<script src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>assets/js/task-estimated-form.js?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/js/task-estimated-form.js') ?: '1'; ?>"></script>
<?php endif; ?>
<script>
    // Pass PHP data to JavaScript - make it globally available for task.js
    window.staffList = <?php echo json_encode($staffList); ?>;
    window.selectedStaffIds = <?php echo json_encode($selectedStaffIds); ?>;
    window.isEditMode = true;
    window.canAssignMembers = true; // Admin has full permissions
</script> 