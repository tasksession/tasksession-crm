<?php
/*
 ================================================================================
   Task Session â€“ Project Management System
   File    : add_task.php
   Purpose : Allows users to create and assign new tasks to projects or staff.
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/email_helper.php");
require_once("../includes/calendar_event.php");
$__gcalSvc = __DIR__ . '/../includes/google_calendar_service.php';
if (is_file($__gcalSvc)) { require_once $__gcalSvc; }
require_once __DIR__ . '/../includes/task_recurrence_helper.php';

$title = "Add New Task | ". $syatem_title;

// Initialize message variable early so POST handlers can set it and it persists
$message = "";

// Add this helper function near the top (after includes)
function limitWords(
    $text,
    $limit = 20
) {
    $plain = strip_tags($text);
    $words = preg_split('/\s+/', $plain);
    if (count($words) > $limit) {
        return implode(' ', array_slice($words, 0, $limit)) . '...';
    }
    return $plain;
}

$taskCreatedSuccess = null;
require_once __DIR__ . '/../includes/task_created_success_helper.php';
if (isset($_POST['add-task'])) {
    
    // Create new task object
    $task = new Task();
    
    // Set task properties
    $task->title = $_POST['title'];
    $task->description = sanitize_tinymce_content($_POST['description'] ?? '');
    $task->project_id = $_POST['project_id'] ?? 0;
    $task->status = 'todo';
    $task->created_at = date('Y-m-d H:i:s');
    $task->user_id = $session->userId;
    $task->creator_id = $session->userId;

    // Server-side validation (required fields)
    $validationErrors = [];

    // Task title is required (novalidate disables browser required enforcement)
    $title_raw = trim($_POST['title'] ?? '');
    if ($title_raw === '') {
        $validationErrors[] = 'Task Title is required.';
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

    // Validate date rules only if both present
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
        // Normalize fields after validation
        $task->title = $title_raw;
        // Handle multiple staff assignment (normalized)
        $task->assigned_to = implode(',', $assignedIds);

        $task->start_date = $start_date_raw;
        $task->due_date = $due_date_raw;
        $task->estimated_time_seconds = tasksession_parse_estimated_time_seconds_from_post();

        // Calculate next position for the new task
        if ($task->project_id > 0) {
            $sql = "SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM tasks WHERE project_id = {$task->project_id} AND status = 'todo'";
        } else {
            $sql = "SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM tasks WHERE project_id = 0 AND status = 'todo'";
        }
        $result = $database->query($sql);
        $position = (int)$database->fetchArray($result)['pos'];
        $task->position = $position;
        
        // Save the task
        $result = $task->save();
    
        if ($result === true || $result === 0) {
            require_once __DIR__ . '/../includes/custom-fields/project_task_values.php';
            $postedCf = isset($_POST['custom_fields']) && is_array($_POST['custom_fields']) ? $_POST['custom_fields'] : null;
            save_task_custom_field_values($connect, (int) $task->id, $postedCf);

            if (function_exists('tasksession_seed_task_schedules')) {
                tasksession_seed_task_schedules((int) $task->id, ['skip_if_user_has_row' => false]);
            }
            tasksession_recurrence_save_from_post($task);

        if (class_exists('GoogleCalendarService') && GoogleCalendarService::moduleReady()) {
            $gcSettings = GoogleCalendarService::getSettings();
            if (!empty($gcSettings['task_sync_enabled'])) {
                $syncEventId = CalendarEvent::upsertTaskSyncEvent((int)$task->id, (int)$session->userId);
                if ($syncEventId > 0) {
                    $syncEvent = CalendarEvent::findEventById($syncEventId, (int)$session->userId);
                    if ($syncEvent) {
                        $push = GoogleCalendarService::pushEvent((int)$session->userId, $syncEvent);
                        if ($push['ok']) {
                            $googleId = $push['data']['id'];
                            $etag = isset($push['data']['etag']) ? $push['data']['etag'] : null;
                            $calendarId = $push['calendar_id'];
                            $stmt = $connect->prepare("UPDATE calendar_events SET google_event_id = ?, google_calendar_id = ?, google_etag = ?, sync_state = 'synced', last_synced_at = NOW() WHERE id = ? AND user_id = ?");
                            if ($stmt) {
                                $stmt->bind_param("sssii", $googleId, $calendarId, $etag, $syncEventId, $session->userId);
                                $stmt->execute();
                                $stmt->close();
                            }
                            GoogleCalendarService::logSync((int)$session->userId, 'push', 'task', (int)$task->id, $googleId, 'success', 'Task created synced');
                        } else {
                            GoogleCalendarService::logSync((int)$session->userId, 'push', 'task', (int)$task->id, null, 'error', $push['error'] ?? 'push_failed');
                        }
                    }
                }
            }
        }
        
        // Create notification for task creation
        require_once('../includes/notification_helper.php');
        $project_id = $task->project_id > 0 ? $task->project_id : null;
        NotificationHelper::taskCreated($task->id, $_POST['title'], $session->userId, $task->assigned_to, $project_id);
        
        // Email notification logic
        if (isset($_POST['notifyTask'])) {
            $settings = settings::findById(1);
            
            // Initialize EmailHelper
            $emailHelper = new EmailHelper($settings);
            $task_title = $_POST['title'];
            $task_description = sanitize_tinymce_content($_POST['description'] ?? '');
            $due_date = $_POST['due_date'] ?? '';
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
                        $staffUser = user::findById($staff_id);
                        if ($staffUser && filter_var($staffUser->email, FILTER_VALIDATE_EMAIL)) {
                            $subject = 'New Task Assignment';
                            $variablesArr = array(
                                '{USER_NAME}'        => htmlspecialchars($staffUser->firstName, ENT_QUOTES, 'UTF-8'),
                                '{TASK_TITLE}'       => htmlspecialchars($task_title, ENT_QUOTES, 'UTF-8'),
                                '{PROJECT_NAME}'     => htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8'),
                                '{TASK_DESCRIPTION}' => limitWords($task_description, 20),
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

                            // Use centralized email helper
                            $emailSent = $emailHelper->sendTemplateEmail($staffUser->email, $subject, $templateHTML, $variablesArr);
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
                
                // Send email to all clients
                foreach ($all_client_ids as $client_id) {
                    $clientUser = user::findById($client_id);
                    if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL)) {
                        $subject = 'New Task Created in Your Project';
                        $variablesArr = array(
                            '{USER_NAME}'        => htmlspecialchars($clientUser->firstName, ENT_QUOTES, 'UTF-8'),
                            '{TASK_TITLE}'       => htmlspecialchars($task_title, ENT_QUOTES, 'UTF-8'),
                            '{PROJECT_NAME}'     => htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8'),
                            '{TASK_DESCRIPTION}' => limitWords($task_description, 20),
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

                        // Use centralized email helper
                        $emailSent = $emailHelper->sendTemplateEmail($clientUser->email, $subject, $templateHTML, $variablesArr);
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

if(!($session->isLoggedIn())){
    redirectTo($url."");
}
if($_SESSION['accountStatus'] == 2){
    redirectTo($url."client/dashboard");
}
if($_SESSION['accountStatus'] == 3){
    redirectTo($url."staff/dashboard");
}

// Get current user info
$id = $session->userId; 
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
// Get project ID from URL if available
$projectId = isset($_GET['projectId']) ? (int)$_GET['projectId'] : 0;
$project = null;
$assignedStaff = [];

// If a project ID is provided, get project information
if ($projectId > 0) {
    require_once("../includes/projects.php");
    $project = projects::findByProjectId($projectId);
    
    // If project doesn't exist, redirect to the main add task page
    if (!$project) {
        $message = "<p class='alert alert-danger'>The specified project does not exist.</p>";
        // Store message in session and redirect
        $_SESSION['temp_message'] = $message;
        redirectTo($url."admin/add_task");
    }
    
    // Get staff members assigned to this project
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
}

// If no project ID or invalid project, get all staff and admin for internal tasks
if (!$project) {
    // Get all staff and admin members for internal tasks
    $allStaff = User::findBySql("SELECT * FROM users WHERE (accountStatus = 3 OR accountStatus = 1) AND status = 0");
    foreach ($allStaff as $staffUser) {
        $assignedStaff[$staffUser->id] = $staffUser;
    }
}

// Get temporary message from session if available
if (isset($_SESSION['temp_message'])) {
    $message = $_SESSION['temp_message'];
    unset($_SESSION['temp_message']);
}

// Build staff list for dropdown (like edit-project.php)
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

// Get all projects for dropdown
$allProjects = projects::findBySql("SELECT * FROM projects WHERE trash = 0 ORDER BY project_title");

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
// For internal tasks (no project), show all staff and admin
$projectStaffMap[0] = $staffList;
require_once __DIR__ . '/../includes/add-project-dropdown-data.php';

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
									<?php if ($projectId > 0): ?>
										<a href="task?projectId=<?= $projectId ?>" class="btn">
											<?php echo ts_icon('restore', 'w-6'); ?>
										</a>
									<?php else: ?>
                                    <a href="task" class="btn">
                                        <?php echo ts_icon('restore', 'w-6'); ?>
                                    </a>
                                <?php endif; ?>
								</div>
							<h2><?php echo $lang['Add New Task']; ?></h2>

                                <?php if ($projectId > 0 && $project): ?>
                                    <p class="text-muted"><?php echo $lang['Adding task to project']; ?>: <strong><?= htmlspecialchars($project->project_title) ?></strong> <a href="add_task" class="small">(<?php echo $lang['change']; ?>)</a></p>
                                <?php else: ?>
                                    <p class="text-muted"><?php echo $lang['Select a project or create an internal task']; ?></p>
                                <?php endif; ?>
							</div>
							
                               
                            </div>
                        </div>
                        
                        <?php if(isset($message) && (!empty($message))){echo $message;} ?>
                        <div class="add-projects">
                            <form id="addTaskForm" method="post" action="#" novalidate data-custom-fields-guard="1" data-task-created-modal="1">
                                <!-- Ensure POST handler triggers even if submit button is disabled by spinner JS -->
                                <input type="hidden" name="add-task" value="1">
                                <div class="row">
                                    <div class="col-md-12">
                                        <!-- Project selection -->
                                        <?php if ($projectId == 0): ?>
                                        <div class="form-group">
                                            <label><?php echo $lang['Project (optional)']; ?></label>
                                            <div class="dropdown">
                                                <button
                                                    class="field-btn dropdown-toggle w-100 text-start"
                                                    type="button"
                                                    id="projectDropdownBtn"
                                                    data-bs-toggle="dropdown"
                                                    aria-expanded="false"
                                                >
                                                    <span id="projectDropdownBtnText">
                                                        <?php
                                                        if ($projectId > 0 && $project) {
                                                            echo htmlspecialchars($project->project_title);
                                                        } else {
                                                            echo $lang['-- Internal Task (No Project) --'];
                                                        }
                                                        ?>
                                                    </span>
                                                </button>
                                                <div class="dropdown-menu w-100 p-2" aria-labelledby="projectDropdownBtn" id="projectDropdownMenu" style="max-height: 300px;">
                                                    <div style="position: sticky; top: 0; background: #fff; z-index: 2;">
                                                        <input type="text" class="form-control mb-2" id="projectSearchInput" placeholder="<?php echo $lang['Search project...']; ?>">
                                                    </div>
                                                    <ul class="list-unstyled mb-0" id="projectList" style="max-height: 240px; overflow-y: auto;"></ul>
                                                </div>
                                                <input type="hidden" name="project_id" id="selectedProjectInput" value="<?= $projectId ?>">
                                            </div>
                                            <small class="form-text text-muted">
                                                <?php echo $lang['Select a project or leave as "Internal Task" for tasks not tied to a project']; ?>
                                            </small>
                                        </div>
                                        <?php elseif ($projectId > 0 && $project): ?>
                                            <input type="hidden" name="project_id" value="<?= $projectId ?>">
                                        <?php endif; ?>
                                        
                                        <div class="form-group">
                                            <label><?php echo $lang['Task Title*']; ?></label>
                                            <input type="text" name="title" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label><?php echo $lang['Description']; ?></label>
                                            <textarea id="description" name="description" class="form-control" placeholder="<?php echo $lang['Describe the task']; ?>"></textarea>
                                            <?php
                                            $aiFieldComposeTarget = 'textarea[name="description"]';
                                            $aiFieldComposeField = 'task_description';
                                            $aiFieldComposeTitle = 'input[name="title"]';
                                            include dirname(__DIR__) . '/templates/partials/ai-field-compose.php';
                                            ?>
                                        </div>
                                        <div class="form-group field-label mb-0">
                                            <div class="row g-3 align-items-start comon-task-estimated-row">
                                                <div class="col-12">
                                                    <label for="staffDropdownBtn">Assign team members & Groups <span class="text-danger">*</span></label>
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
                                                                class="dropdown-menu w-100 dropdown-scroll"
                                                                aria-labelledby="staffDropdownBtn"
                                                                id="staffDropdownMenu"
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
                                        
                                        <!-- Email Notification Toggle & Submit -->
                                        <div class="input-notify">
                                            <div class="form-group d-block d-md-flex justify-content-between">
                                                <div class="d-flex col-gap align-items-center">
                                                    <div class="checkbox-wrapper-6">
                                                        <input class="tgl tgl-light" id="notifyTask" name="notifyTask" type="checkbox" />
                                                        <label class="tgl-btn" for="notifyTask"></label>
                                                    </div>
                                                    <div>
                                                        <label for="notifyTask" class="permission-label d-flex align-items-center col-gap-5 mb-0">
                                                            <?php echo $lang['Email Notification']; ?>
                                                            <span class="flex-shrink-0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($lang['Notify assigned staff and client about the new task'], ENT_QUOTES, 'UTF-8'); ?>" onclick="event.preventDefault(); event.stopPropagation();"><?php echo ts_icon('info', 'w-2 text-muted'); ?></span>
                                                        </label>
                                                    </div>
                                                </div>
                                                <button type="submit" name="add-task" id="create-task-btn" class="btn primary-btn"><?php echo $lang['Create Task']; ?></button>
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
<?php include("../templates/comon-success-modal-bundle.php"); ?>
<?php include("../templates/task-repeat-modal-bundle.php"); ?>
<?php include("../templates/main-footer.php"); ?>
<script>
window.USER_PROFILE_CF_CONFIG = {
  entityType: 'task',
  listUrl: '../includes/custom-fields/list.php',
  containerSelector: '#userProfileCustomFieldsContainer',
  initialValues: [],
  i18n: {
    fieldRequired: <?php echo json_encode(isset($lang['This field is required.']) ? $lang['This field is required.'] : 'This field is required.', JSON_HEX_TAG | JSON_HEX_APOS); ?>
  }
};
window.staffList = <?php echo json_encode($staffList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
window.projectStaffMap = <?php echo json_encode($projectStaffMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}'; ?>;
window.projectTeamGroupList = <?php echo json_encode($projectTeamGroupList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?: '[]'; ?>;
window.allProjects = [
    { id: 0, name: '-- Internal Task (No Project) --' },
    <?php foreach ($allProjects as $proj): ?>
        { id: <?= (int)$proj->p_id ?>, name: <?= json_encode($proj->project_title) ?> },
    <?php endforeach; ?>
];
window.selectedProjectId = <?= (int)$projectId ?>;
window.isEditMode = false;
window.comonSuccessModalConfig = {
    titleDefault: <?php echo json_encode($lang['Your task is created'] ?? 'Your task is created', JSON_HEX_TAG | JSON_HEX_APOS); ?>,
    viewTaskLabel: <?php echo json_encode(trim($lang['View Task'] ?? 'View task'), JSON_HEX_TAG | JSON_HEX_APOS); ?>,
    goToKanbanLabel: <?php echo json_encode($lang['Go to kanban'] ?? 'Go to kanban', JSON_HEX_TAG | JSON_HEX_APOS); ?>
};
</script>
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

