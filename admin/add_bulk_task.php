<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : add_task.php
   Purpose : Allows users to create and assign new tasks to projects or staff.
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/email_helper.php");

$title = "Add New Task | ". $syatem_title;

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

    // Allowed assignees for this project (or all staff/admin for internal)
    $postProjectId = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
    $allowedIdList = [];
    if ($postProjectId > 0) {
        require_once(__DIR__ . '/../includes/projects.php');
        $projPost = projects::findByProjectId($postProjectId);
        if ($projPost) {
            foreach (explode(',', (string) $projPost->s_ids) as $sid) {
                $sid = (int) trim($sid);
                if ($sid > 0 && $sid != (int) $projPost->c_id) {
                    $su = User::findById($sid);
                    if ($su && ((int) $su->accountStatus === 1 || (int) $su->accountStatus === 3)) {
                        $allowedIdList[] = $sid;
                    }
                }
            }
        }
    } else {
        $allStaffPost = User::findBySql('SELECT id FROM users WHERE (accountStatus = 3 OR accountStatus = 1) AND status = 0');
        foreach ($allStaffPost as $su) {
            $allowedIdList[] = (int) $su->id;
        }
    }
    $allowedIdList = array_values(array_unique($allowedIdList));

    $assignedMembers = [];
    $rawAssigned = $_POST['assigned_to'] ?? null;
    if ($rawAssigned !== null) {
        $chunks = is_array($rawAssigned) ? $rawAssigned : [$rawAssigned];
        foreach ($chunks as $c) {
            foreach (explode(',', (string) $c) as $p) {
                $p = (int) trim($p);
                if ($p > 0) {
                    $assignedMembers[] = $p;
                }
            }
        }
    }
    $assignedMembers = array_values(array_unique(array_filter($assignedMembers)));
    $assignedMembers = array_values(array_intersect($assignedMembers, $allowedIdList));

    if (empty($assignedMembers)) {
        $assignedMembers = [''];
    }

    $task->start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $task->due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $estimatedTimeSeconds = tasksession_parse_estimated_time_seconds_from_post();

    // Create separate task for each assigned member
    $createdTasks = [];
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($assignedMembers as $memberId) {
        // Create a new task object for each member
        $individualTask = new Task();
        $individualTask->title = $task->title;
        $individualTask->description = $task->description;
        $individualTask->project_id = $task->project_id;
        $individualTask->status = 'todo';
        $individualTask->created_at = $task->created_at;
        $individualTask->user_id = $session->userId;
        $individualTask->creator_id = $session->userId;
        $individualTask->assigned_to = $memberId; // Single member assignment
        $individualTask->start_date = $task->start_date;
        $individualTask->due_date = $task->due_date;
        $individualTask->estimated_time_seconds = $estimatedTimeSeconds;
        
        // Calculate position for this individual task
        if ($individualTask->project_id > 0) {
            $sql = "SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM tasks WHERE project_id = {$individualTask->project_id} AND status = 'todo'";
        } else {
            $sql = "SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM tasks WHERE project_id = 0 AND status = 'todo'";
        }
        $result = $database->query($sql);
        $position = (int)$database->fetchArray($result)['pos'];
        $individualTask->position = $position;
        
        // Save the individual task
        $result = $individualTask->save();
        
        if ($result === true || $result === 0) {
            if (function_exists('tasksession_seed_task_schedules') && !empty($individualTask->id)) {
                tasksession_seed_task_schedules((int) $individualTask->id, ['skip_if_user_has_row' => false]);
            }
            $createdTasks[] = $individualTask;
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    
    if ($successCount > 0) {
        
        // Create notifications for each created task
        require_once('../includes/notification_helper.php');
        $project_id = $task->project_id > 0 ? $task->project_id : null;
        
        foreach ($createdTasks as $createdTask) {
            NotificationHelper::taskCreated($createdTask->id, $createdTask->title, $session->userId, $createdTask->assigned_to, $project_id);
        }
        
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

            // Send email for each individual task
            foreach ($createdTasks as $createdTask) {
                if (!empty($createdTask->assigned_to)) {
                    $staffUser = user::findById($createdTask->assigned_to);
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

        // Stay on bulk add page (PRG avoids duplicate POST on refresh)
        $redirQ = [
            'bulk_success' => '1',
            'count' => (int) $successCount,
        ];
        if ((int) $task->project_id > 0) {
            $redirQ['projectId'] = (int) $task->project_id;
        }
        header('Location: add_bulk_task?' . http_build_query($redirQ));
        exit;
    } else {
        global $database;
        $message = "<p class='alert alert-danger'>Tasks could not be created at this time.<br>Error: " . $database->error() . "</p>";
    }
}

include("../templates/header.php");
$__bgtg_css = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'team-groups.css';
if (is_file($__bgtg_css)) {
	echo '<link href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . 'assets/css/team-groups.css?v=' . (int) filemtime($__bgtg_css) . '" rel="stylesheet" type="text/css"/>';
}

if(!($session->isLoggedIn())){
    redirectTo($url."index");
}
if($_SESSION['accountStatus'] == 2){
    redirectTo($url."client/index");
}
if($_SESSION['accountStatus'] == 3){
    redirectTo($url."staff/index");
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

// Initialize message variable
$message = "";
$bulkSuccessCount = 0;
if (isset($_GET['bulk_success']) && (string) $_GET['bulk_success'] === '1') {
    $bulkSuccessCount = isset($_GET['count']) ? max(1, (int) $_GET['count']) : 1;
}

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

<script>
    // Pass PHP data to JavaScript - make it globally available for features.js
    window.staffList = <?php echo json_encode($staffList); ?>;
    window.projectStaffMap = <?php echo json_encode($projectStaffMap); ?>;
    window.allProjects = [
        { id: 0, name: '-- Internal Task (No Project) --' },
        <?php foreach ($allProjects as $proj): ?>
            { id: <?= (int)$proj->p_id ?>, name: <?= json_encode($proj->project_title) ?> },
        <?php endforeach; ?>
    ];
    window.selectedProjectId = <?= (int)$projectId ?>;
    window.projectTeamGroupList = <?php echo json_encode($projectTeamGroupList ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
</script>

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
							<h2>Add Bulk Task</h2>

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
                            <form method="post" action="#" data-task-page="bulk" novalidate>
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
                                        </div>
                                        <div class="form-group field-label">
                                            <label for="staffDropdownBtn"><?php echo htmlspecialchars($lang['Assign team members & Groups'] ?? 'Assign team members & Groups'); ?> <span class="text-danger">*</span></label>
                                            <div class="mb-3">
                                                <div class="dropdown">
                                                    <button
                                                        class="field-btn dropdown-toggle w-100 text-start"
                                                        type="button"
                                                        id="staffDropdownBtn"
                                                        data-bs-toggle="dropdown"
                                                        data-bs-auto-close="outside"
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
                                                <input type="hidden" name="assigned_to[]" id="selectedStaffInput" value="" />
                                                <small id="staff-error" class="text-danger d-none"><?php echo isset($lang['Please select at least one team member or admin']) ? $lang['Please select at least one team member or admin'] : 'Please select at least one team member or admin.'; ?></small>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label><?php echo $lang['Start Date']; ?></label>
                                                    <input type="date" name="start_date" id="bulk_start_date" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label><?php echo $lang['Due Date']; ?></label>
                                                    <input type="date" name="due_date" id="bulk_due_date" class="form-control">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="comon-task-estimated-row">
                                        <?php
                                        $estimatedSecondsSelected = null;
                                        $estimatedTimeColumnClass = 'col-12 pd-0';
                                        require dirname(__DIR__) . '/includes/partials/task_estimated_time_fields.php';
                                        unset($estimatedTimeColumnClass);
                                        ?>
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
                                            <?php if (!empty($bulkSuccessCount)): ?>
                                            <div id="bulk-task-success-followup" class="mt-3 pt-3 border-top border-secondary border-opacity-25 w-100">
                                                <p class="mb-2 text-success fw-medium">
                                                    <?php
                                                    if ($bulkSuccessCount > 1) {
                                                        echo htmlspecialchars(
                                                            sprintf(
                                                                $lang['Bulk tasks created success visit n'] ?? '%d tasks were created successfully. Visit Kanban to view them.',
                                                                $bulkSuccessCount
                                                            ),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                    } else {
                                                        echo htmlspecialchars(
                                                            $lang['Bulk task created success visit'] ?? 'Task created successfully. Visit Kanban to view it.',
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        );
                                                    }
                                                    ?>
                                                </p>
                                                <a
                                                    href="kanban?all_tasks=1&amp;sort_order=desc"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="btn primary-btn"
                                                    style="display: inline-block; width: initial; max-width: 100%;"
                                                ><?php echo htmlspecialchars($lang['Open Kanban board'] ?? 'Open Kanban', ENT_QUOTES, 'UTF-8'); ?></a>
                                            </div>
                                            <?php endif; ?>
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
<?php include("../templates/main-footer.php"); ?>
<script src="../assets/js/rich-editor.js"></script>
<script src="../assets/js/task.js"></script>
<script src="../assets/js/bulk-add-task-page.js"></script>
<?php if (tasksession_time_tracking_enabled()) : ?>
<script src="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>assets/js/task-estimated-form.js?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/js/task-estimated-form.js') ?: '1'; ?>"></script>
<?php endif; ?>

