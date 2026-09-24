<?php 
// staff/task.php
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/permissions.php");
ensure_user_permissions($connect);

// ----------------------------------------------------------------------------
// ①— HANDLE "Rename Column" form submission (INSERT / UPDATE into project_columns)
// ----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['column_key'], $_POST['column_name'], $_POST['project_id'])
) {
    if (!$session->isLoggedIn()) {
        redirectTo($url . "index.php");
    }
    if (!isset($_SESSION['accountStatus']) || (int) $_SESSION['accountStatus'] !== 3) {
        redirectTo($url . "staff/index.php");
    }
    if (!has_permission('task_edit')) {
        redirectTo($url . "staff/index.php?message=permission_denied");
    }
    $csrfToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
    if (!function_exists('validate_csrf_token') || !validate_csrf_token($csrfToken)) {
        redirectTo($url . "staff/index.php?message=invalid_request");
    }

    // Sanitize inputs
    $colKey  = $database->escapeValue($_POST['column_key']);
    $colName = $database->escapeValue($_POST['column_name']);
    $projId  = (int)$_POST['project_id'];
    if ($projId <= 0) {
        redirectTo($url . "staff/kanban");
    }
    $renameProject = projects::findByProjectId($projId);
    if (!$renameProject) {
        redirectTo($url . "staff/kanban");
    }
    $currentUserId = (int) $_SESSION['userId'];
    if (!has_permission('project_view_all') && strpos((string) $renameProject->s_ids, (string) $currentUserId) === false) {
        redirectTo($url . "staff/kanban?message=unauthorized");
    }

    // Check if a custom name already exists for this project + key
    $checkSql  = "SELECT id 
                  FROM project_columns 
                  WHERE project_id = {$projId} 
                    AND column_key = '{$colKey}'";
    $checkRes  = $database->query($checkSql);

    if ($database->numRows($checkRes) > 0) {
        // UPDATE existing row
        $updateSql = "UPDATE project_columns 
                      SET custom_name = '{$colName}' 
                      WHERE project_id = {$projId} 
                        AND column_key = '{$colKey}'";
        $database->query($updateSql);
    } else {
        // INSERT new row
        $insertSql = "INSERT INTO project_columns 
                      (project_id, column_key, custom_name) 
                      VALUES ({$projId}, '{$colKey}', '{$colName}')";
        $database->query($insertSql);
    }

    // Redirect back to this page (so GET[projectId] remains) with a "columnRenamed" flag
    redirectTo($url . "staff/task?projectId={$projId}&message=columnRenamed");
}

// ----------------------------------------------------------------------------
// ②— SETUP PAGE TITLE & HEADER
// ----------------------------------------------------------------------------
$system_title = $system_title ?? 'My Application';
$title        = "Project Tasks | " . $system_title;

include("../templates/header.php");

// ----------------------------------------------------------------------------
// ③— AUTHENTICATION & REDIRECT IF NO projectId
// ----------------------------------------------------------------------------
if(!($session->isLoggedIn())){
    redirectTo($url."index.php");
}
if($_SESSION['accountStatus'] == 1){
    redirectTo($url."admin/index.php");
}
if($_SESSION['accountStatus'] == 2){
    redirectTo($url."client/index.php");
} 

// Get current user info
$id = $session->userId; 
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;

// Redirect to kanban.php if no projectId in GET
if (!isset($_GET['projectId']) || empty($_GET['projectId'])) {
    redirectTo($url . "staff/kanban");
}

// Sanitize & grab GET
$projectId = isset($_GET['projectId']) ? (int)$_GET['projectId'] : 0;

// If projectId is 0, redirect to kanban.php
if ($projectId === 0) {
    redirectTo($url . "staff/kanban");
}

// ----------------------------------------------------------------------------
// ④— LOAD PROJECT & RELATED DATA
// ----------------------------------------------------------------------------
include(__DIR__ . '/../includes/project-sidebar-data.php');

$project   = projects::findByProjectId($projectId);
$projTitle = $project ? $project->project_title : "Project Tasks";

if (!$project) {
    redirectTo($url . "staff/kanban");
}

// Only restrict by s_ids if staff does NOT have project_view_all
if (!has_permission('project_view_all') && strpos($project->s_ids, $id) === false){
    // Staff is not assigned to this project, redirect to kanban page
    redirectTo($url."staff/kanban?message=unauthorized");
}

// ----------------------------------------------------------------------------
// ⑤— LOAD TASKS & GROUP BY STATUS
// ----------------------------------------------------------------------------
require_once("../includes/task.php");

$allTasks = Task::findByProject($projectId);

// Define status types and labels
$statusTypes = ['todo', 'inprogress', 'review', 'done'];
$statusLabels = [
    'todo' => 'To Do',
    'inprogress' => 'In Progress',
    'review' => 'In Review',
    'done' => 'Completed'
];

// Get filter parameters from URL (with proper sanitization)
$statusFilter = isset($_GET['status']) && (in_array($_GET['status'], $statusTypes) || in_array($_GET['status'], ['overdue', 'due_today', 'task_pro', 'on_time'])) ? htmlspecialchars($_GET['status']) : null;
$showMyTasks = !isset($_GET['all_tasks']) || $_GET['all_tasks'] !== '1';
$startDateFilter = isset($_GET['start_date']) ? htmlspecialchars(trim($_GET['start_date'])) : '';
$endDateFilter = isset($_GET['end_date']) ? htmlspecialchars(trim($_GET['end_date'])) : '';

// Apply filters to tasks
if ($statusFilter || $startDateFilter || $endDateFilter) {
    $allTasks = array_filter($allTasks, function($task) use ($statusFilter, $startDateFilter, $endDateFilter) {
        // Handle computed status filters
        $statusMatch = true;
        if ($statusFilter) {
            if (in_array($statusFilter, ['overdue', 'due_today', 'task_pro', 'on_time'])) {
                // Check if task has a due date
                if (empty($task->due_date)) {
                    $statusMatch = false; // Tasks without due dates don't match date-based filters
                } else {
                    $dueDate = strtotime($task->due_date);
                    $today = strtotime('today');
                    
                    switch ($statusFilter) {
                        case 'overdue':
                            // For overdue, check if task is overdue (due date passed and not completed)
                            $statusMatch = $dueDate < $today && $task->status !== 'done';
                            break;
                        case 'due_today':
                            // For due today, check if due date is today
                            $statusMatch = $dueDate == $today;
                            break;
                        case 'task_pro':
                            // For task pro, only completed tasks that were completed before due date
                            if ($task->status !== 'done') {
                                $statusMatch = false;
                            } else {
                                if (!empty($task->completed_at)) {
                                    $completedDate = strtotime($task->completed_at);
                                    $completedDateOnly = strtotime(date('Y-m-d', $completedDate));
                                    $dueDateOnly = strtotime(date('Y-m-d', $dueDate));
                                    $statusMatch = $completedDateOnly < $dueDateOnly;
                                } else {
                                    $statusMatch = false;
                                }
                            }
                            break;
                        case 'on_time':
                            // For on time, only completed tasks that were completed on due date
                            if ($task->status !== 'done') {
                                $statusMatch = false;
                            } else {
                                if (!empty($task->completed_at)) {
                                    $completedDate = strtotime($task->completed_at);
                                    $completedDateOnly = strtotime(date('Y-m-d', $completedDate));
                                    $dueDateOnly = strtotime(date('Y-m-d', $dueDate));
                                    $statusMatch = $completedDateOnly == $dueDateOnly;
                                } else {
                                    $statusMatch = false;
                                }
                            }
                            break;
                    }
                }
            } else {
                // Handle regular status filters
                $statusMatch = $task->status === $statusFilter;
            }
        }
        
        // Handle date range filters
        $dateMatch = true;
        if ($startDateFilter || $endDateFilter) {
            $taskStartDate = !empty($task->start_date) ? strtotime($task->start_date) : null;
            $taskDueDate = !empty($task->due_date) ? strtotime($task->due_date) : null;
            
            if ($startDateFilter && $endDateFilter) {
                // Both dates selected - task must fall within range
                $startFilter = strtotime($startDateFilter);
                $endFilter = strtotime($endDateFilter);
                
                if ($taskStartDate && $taskDueDate) {
                    // Task has both start and due dates
                    $dateMatch = ($taskStartDate <= $endFilter && $taskDueDate >= $startFilter);
                } elseif ($taskStartDate) {
                    // Task has only start date
                    $dateMatch = ($taskStartDate <= $endFilter);
                } elseif ($taskDueDate) {
                    // Task has only due date
                    $dateMatch = ($taskDueDate >= $startFilter);
                } else {
                    $dateMatch = false; // No dates on task
                }
            } elseif ($startDateFilter) {
                // Only start date selected - task must start on or after this date
                $startFilter = strtotime($startDateFilter);
                $dateMatch = ($taskStartDate && $taskStartDate >= $startFilter) || 
                            ($taskDueDate && $taskDueDate >= $startFilter);
            } elseif ($endDateFilter) {
                // Only end date selected - task must be due on or before this date
                $endFilter = strtotime($endDateFilter);
                $dateMatch = ($taskStartDate && $taskStartDate <= $endFilter) || 
                            ($taskDueDate && $taskDueDate <= $endFilter);
            }
        }
        
        return $statusMatch && $dateMatch;
    });
}

$columns    = ['todo'=>[], 'inprogress'=>[], 'review'=>[], 'done'=>[]];

$defaultStatuses = ['todo', 'inprogress', 'review', 'done'];

// Handle special status filters (put all filtered tasks in 'done' column)
if ($statusFilter && in_array($statusFilter, ['overdue', 'due_today', 'task_pro', 'on_time'])) {
    // For special status filters, put all tasks in 'done' column
    foreach ($allTasks as $t) {
        $columns['done'][] = $t;
    }
} else {
    // Normal grouping by status
foreach ($allTasks as $t) {
    $status = $t->status;
    if (!in_array($status, $defaultStatuses)) {
        // Use last_default_status if status is not a default
        $status = $t->last_default_status ?: 'todo';
    }
    $columns[$status][] = $t;
    }
}

// Store original counts before pagination
$originalColumnCounts = [];
foreach ($columns as $columnKey => $columnTasks) {
    $originalColumnCounts[$columnKey] = count($columnTasks);
}

// Limit initial display to 10 tasks per column
foreach ($columns as $columnKey => $columnTasks) {
    if (count($columnTasks) > 10) {
        $columns[$columnKey] = array_slice($columnTasks, 0, 10);
    }
}

// ----------------------------------------------------------------------------
// ⑥— LOAD PROJECT STAFF (INCLUDING ADMINS)
// ----------------------------------------------------------------------------
$projectStaff = [];
if ($project && isset($project->s_ids)) {
    $s_ids    = $project->s_ids;
    $staffIds = explode(',', $s_ids);
    foreach ($staffIds as $staffId) {
        if ($staffId > 0 && $staffId != $project->c_id) {
            $staffUser = User::findById($staffId);
            // Include both staff (accountStatus == 3) and admin (accountStatus == 1) accounts
            if ($staffUser && ($staffUser->accountStatus == 3 || $staffUser->accountStatus == 1)) {
                $projectStaff[$staffId] = $staffUser;
            }
        }
    }
}

// ----------------------------------------------------------------------------
// ⑦— DEFAULT COLUMN NAMES & OVERRIDE VIA project_columns
// ----------------------------------------------------------------------------
$statuses = [
    'todo'       => ['icon'=>'color-todo-bg','label'=>'To Do'],
    'inprogress' => ['icon'=>'color-inprogress-bg','label'=>'In Progress'],
    'review'     => ['icon'=>'color-review-bg','label'=>'Review'],
    'done'       => ['icon'=>'color-done-bg','label'=>'Done'],
];

global $database;

// Check if project_columns table exists
$tableCheckQuery  = "SHOW TABLES LIKE 'project_columns'";
$tableCheckResult = $database->query($tableCheckQuery);

if ($tableCheckResult && $database->numRows($tableCheckResult) > 0) {
    // First, try project-specific names
    $columnsQuery       = "SELECT column_key, custom_name 
                           FROM project_columns 
                           WHERE project_id = " . $projectId;
    $columnsResult      = $database->query($columnsQuery);

    if ($columnsResult && $database->numRows($columnsResult) > 0) {
        while ($row = $database->fetchArray($columnsResult)) {
            if (isset($statuses[$row['column_key']])) {
                $statuses[$row['column_key']]['label'] = $row['custom_name'];
            }
        }
    } else {
        // Fall back to global names (project_id = 0)
        $globalColumnsQuery  = "SELECT column_key, custom_name 
                                FROM project_columns 
                                WHERE project_id = 0";
        $globalColumnsResult = $database->query($globalColumnsQuery);

        if ($globalColumnsResult && $database->numRows($globalColumnsResult) > 0) {
            while ($row = $database->fetchArray($globalColumnsResult)) {
                if (isset($statuses[$row['column_key']])) {
                    $statuses[$row['column_key']]['label'] = $row['custom_name'];
                }
            }
        }
    }
}

// ----------------------------------------------------------------------------
// ⑧— HANDLE OPTIONAL "message" TOASTS
// ----------------------------------------------------------------------------
/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;
if (isset($_GET['message'])) {
    $msgType = $_GET['message'];
    if ($msgType == 'created') {
        $toast_flash = array('type' => 'success', 'msg' => 'Task created successfully!');
    } else if ($msgType == 'updated') {
        $toast_flash = array('type' => 'success', 'msg' => 'Task updated successfully!');
    } else if ($msgType == 'columnRenamed') {
        $toast_flash = array('type' => 'success', 'msg' => 'Column renamed successfully!');
    }
}

// ----------------------------------------------------------------------------
// ⑨— HELPER FUNCTION: first_n_words()
// ----------------------------------------------------------------------------
function first_n_words($text, $limit = 15) {
    $plain = strip_tags($text);
    $words = preg_split('/\s+/', $plain);
    if (count($words) > $limit) {
        $short = implode(' ', array_slice($words, 0, $limit)) . '...';
        return $short;
    }
    return $plain;
}

$can_change_status = has_permission('task_status_update');

$hasActiveProjectTaskFilters = (bool) ($statusFilter || $startDateFilter || $endDateFilter);
$projectTaskClearFiltersUrl = 'task?projectId=' . (int) $projectId;
?>
<div class="page-container vh-100">
    <div class="container-fluid vh-100">
        <div class="row row-eq-height vh-100">
            <?php include("../templates/sidebar.php"); ?>

            <div class="page-content">
                <?php include('../templates/top-header.php'); ?>
                <link rel="stylesheet" href="../assets/css/kanban-bulk.css?v=9">

                <div class="row bg-grey">
                    <div class="col-md-12 margin-top-10 clients project-tabs">
                        <div class="row">
                            <?php 
                            // Set project_id variable for project-tabs.php
                            $project_id = $projectId;
                            include('../templates/project-tabs.php');
                            ?>
                            <div class="edit-overview-btn kanban-header-filters">
                                <td class="extra-height">
                                          <div class="action-toggle border-btn-a<?php echo $hasActiveProjectTaskFilters ? ' has-active-filters' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#project-menu<?php echo $recentProject->p_id;?>" role="button" tabindex="0">
											<span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
											<?php echo ts_icon('filter', 'w-2'); ?>
											<?php if ($hasActiveProjectTaskFilters): ?>
											<a href="<?php echo htmlspecialchars($projectTaskClearFiltersUrl, ENT_QUOTES, 'UTF-8'); ?>" class="kanban-filter-clear" title="<?php echo htmlspecialchars($lang['Clear'] ?? 'Clear', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Clear filters'] ?? 'Clear filters', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.stopPropagation();">
												<?php echo ts_icon('close'); ?>
											</a>
											<?php endif; ?>
                                    </div>
										  <div id="project-menu<?php echo $recentProject->p_id;?>" class="toggle-action collapse shadow-dept">
											<ul>
												<li class="<?php echo !$statusFilter ? 'active' : ''; ?>" data-status="all">
													<a href="?projectId=<?= $projectId ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
														<span><b><?php echo $lang['All Task']; ?></b></span>
													</a>
												</li>
												<?php foreach ($statusTypes as $status): ?>
												<li class="<?php echo $statusFilter === $status ? 'active' : ''; ?>" data-status="<?php echo $status; ?>">
													<a href="?projectId=<?= $projectId ?>&status=<?php echo $status; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
														<span>
															<?php echo $statusLabels[$status]; ?>   <i class="dots <?php echo $statuses[$status]['icon']; ?> object-align-right"></i>

														</span>
													</a>
												</li>
												<?php endforeach; ?>
												<hr>
												<li><b><?php echo $lang['By Status']; ?> </b></li>
												<li class="<?php echo $statusFilter === 'overdue' ? 'active' : ''; ?>" data-status="overdue">
													<a href="?projectId=<?= $projectId ?>&status=overdue<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
														<span>
															<?php echo $lang['Overdue']; ?> 
														</span>
													</a>
												</li>
												<li class="<?php echo $statusFilter === 'due_today' ? 'active' : ''; ?>" data-status="due_today">
													<a href="?projectId=<?= $projectId ?>&status=due_today<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
														<span>
															<?php echo $lang['Due Today']; ?> 
														</span>
													</a>
												</li>
												<li class="<?php echo $statusFilter === 'task_pro' ? 'active' : ''; ?>" data-status="task_pro">
													<a href="?projectId=<?= $projectId ?>&status=task_pro<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
														<span>
															<?php echo $lang['Task Pro']; ?> 
														</span>
													</a>
												</li>
												<li class="<?php echo $statusFilter === 'on_time' ? 'active' : ''; ?>" data-status="on_time">
													<a href="?projectId=<?= $projectId ?>&status=on_time<?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>">
														<span>
															<?php echo $lang['On Time']; ?> 
 														</span>
													</a>
												</li>
												<hr>
												
												<li>
													<a class="secondary-btn-a" href="#" onclick="toggleDateRangeCard(); return false;">
														<span>
															<?php echo $lang['Select Date Range']; ?> 
 														</span>
													</a>
												</li>
												
												<!-- Date Range Card -->
												<div class="date-range card" style="display: none;">
													<div class="card-body">
														<b class="mb-2 d-block"><?php echo $lang['Quick Range']; ?></b>
														<div>
															<li>
																<button class="dropdown-item" onclick="selectQuickRange('last7')"><?php echo $lang['Last 7 days']; ?></button>
															</li>
															<li>
																<button class="dropdown-item" onclick="selectQuickRange('last30')"><?php echo $lang['Last 30 days']; ?></button>
															</li>
															<li>
																<button class="dropdown-item" onclick="selectQuickRange('last90')"><?php echo $lang['Last 90 days']; ?></button>
															</li>
															<li>
																<button class="dropdown-item" onclick="selectQuickRange('last6months')"><?php echo $lang['Last 6 months']; ?></button>
															</li>
															<li>
																<button class="dropdown-item" onclick="selectQuickRange('thisYear')"><?php echo $lang['This Year (Jan - Today)']; ?></button>
															</li>
														</div>
														
														<hr>
														
														<div class="mb-3">
															<div class="date-f">
																<label class="form-label font-size-10"><?php echo $lang['Start Date']; ?></label>
																<input type="date" id="customStart" class="form-control">
															</div>
															<div class="date-f">
																<label class="form-label font-size-10"><?php echo $lang['End Date']; ?></label>
																<input type="date" id="customEnd" class="form-control">
															</div>
														</div>
														
														<div class="d-flex col-gap-10">
															<button class="primary-btn" id="primary-btn" onclick="selectCustomRange()"><?php echo $lang['Apply']; ?></button>
															<button class="btn border-btn-a" onclick="clearDateRange()"><?php echo $lang['Clear']; ?></button>
														</div>
													</div>
												</div>
                                        </ul>
                                    </div>
                                </td>
                            </div>
							<?php if (has_permission('task_create')): ?>
                            <div class="d-none d-md-block">
                                <?php if($projectId > 0): ?>
                                    <a href="add_task?projectId=<?= $projectId ?>&source=project" class="primary-btn">
                                        <?php echo $lang['Add New Task']; ?>
										<?php echo ts_icon('plus', 'w-2'); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="add_task?source=project" class="primary-btn">
                                      <?php echo $lang['Add New Task']; ?>
									  <?php echo ts_icon('plus', 'w-2'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
							 <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="clearfix"></div>
                <div class="row vh-100" id="project-layout-row">
                    <div class="container-fluid vh-100">
                        <div class="row vh-100">
                            <?php 
                            // Build sidebar with project stats, milestones, etc.
                            if ($projectId > 0 && $project) {
                                $taskCount           = 0;
                                $completedTaskCount  = 0;

                                foreach ($columns as $status => $tasks) {
                                    if ($status === 'done') {
                                        $completedTaskCount = count($tasks);
                                    }
                                    $taskCount += count($tasks);
                                }

                                $today = date('Y-m-d');
                                $daysLeftQuery = "SELECT due_date 
                                                  FROM tasks 
                                                  WHERE project_id = $projectId 
                                                    AND status != 'done' 
                                                    AND due_date IS NOT NULL
                                                    AND due_date >= '$today'
                                                  ORDER BY due_date ASC";
                                $daysLeftResult = $database->query($daysLeftQuery);

                                $uniqueDates  = [];
                                $totalDaysLeft = 0;

                                if ($daysLeftResult && $database->numRows($daysLeftResult) > 0) {
                                    while ($dueRow = $database->fetchArray($daysLeftResult)) {
                                        if (!empty($dueRow['due_date'])) {
                                            $dueDate = $dueRow['due_date'];
                                            $uniqueDates[$dueDate] = true;
                                        }
                                    }
                                    foreach ($uniqueDates as $date => $val) {
                                        $daysLeft      = (strtotime($date) - strtotime($today)) / (60 * 60 * 24);
                                        $totalDaysLeft += ceil($daysLeft);
                                    }
                                }

                                $client = User::findById($project->c_id);

                                $totalMilestonesQuery = "SELECT COUNT(*) as total_count, SUM(budget) as total_budget 
                                                         FROM milestones 
                                                         WHERE p_id = $projectId";
                                $totalMilestonesResult = $database->query($totalMilestonesQuery);

                                $totalMilestones = 0;
                                $totalBudget     = 0;
                                if ($milestonesRow = $database->fetchArray($totalMilestonesResult)) {
                                    $totalMilestones = $milestonesRow['total_count'] ?: 0;
                                    $totalBudget     = $milestonesRow['total_budget'] ?: 0;
                                }

                                $paidMilestonesQuery  = "SELECT COUNT(*) as paid_count, SUM(budget) as paid_amount 
                                                        FROM milestones 
                                                        WHERE p_id = $projectId 
                                                          AND status = 1";
                                $paidMilestonesResult = $database->query($paidMilestonesQuery);

                                $paidMilestones = 0;
                                $paidAmount     = 0;
                                if ($paidRow = $database->fetchArray($paidMilestonesResult)) {
                                    $paidMilestones = $paidRow['paid_count'] ?: 0;
                                    $paidAmount     = $paidRow['paid_amount'] ?: 0;
                                }

                                $unpaidMilestones = $totalMilestones - $paidMilestones;
                                $unpaidAmount     = $totalBudget - $paidAmount;

                                // Fetch staff members for sidebar (including admins)
                                $staffMembers = [];
                                if ($project->s_ids) {
                                    $staffIds = explode(',', $project->s_ids);
                                    foreach ($staffIds as $staffId) {
                                        if ($staffId != $project->c_id && $staffId != 0) {
                                            $staffMember = User::findById($staffId);
                                            // Include both staff (accountStatus == 3) and admin (accountStatus == 1) accounts
                                            if ($staffMember && ($staffMember->accountStatus == 3 || $staffMember->accountStatus == 1)) {
                                                $staffMembers[] = $staffMember;
                                            }
                                        }
                                    }
                                }
                            }
                            include("../templates/project-sidebar.php"); 
                            ?>
                            <div class="col-xl-9 col-lg-8 col-md-12 flex-grow-1 fill-rest faded-right pd-0">
                                <div class="board-wrap h-100">
                                    <div class="board d-flex  h-100">
                                        <?php 
                                        // Always show all columns, but update title for special filters
                                        $activeFilterName = '';
                                        if ($statusFilter && in_array($statusFilter, ['overdue', 'due_today', 'task_pro', 'on_time'])) {
                                            switch ($statusFilter) {
                                                case 'overdue':
                                                    $activeFilterName = 'Overdue Tasks';
                                                    break;
                                                case 'due_today':
                                                    $activeFilterName = 'Due Today';
                                                    break;
                                                case 'task_pro':
                                                    $activeFilterName = 'Task Pro';
                                                    break;
                                                case 'on_time':
                                                    $activeFilterName = 'On Time';
                                                    break;
                                            }
                                        }
                                        
                                        foreach ($statuses as $key => $cfg): 
                                            $columnTasks = $columns[$key];
                                        ?>
                                            <div class="board-column  h-100" 
                                                 id="<?= $key ?>-column" 
                                                 <?php if ($can_change_status): ?>
                                                     ondrop="drop(event)" 
                                                     ondragover="allowDrop(event)" 
                                                     ondragleave="dragLeave(event)"
                                                 <?php endif; ?>>
                                                <h2 class="h6 d-flex align-items-center">
                                                    <i class="dots <?= $cfg['icon'] ?> me-1"></i>
                                                    <span class="column-title" data-column="<?= $key ?>">
                                                        <?php 
                                                        if ($activeFilterName && $key === 'done') {
                                                            echo $activeFilterName;
                                                        } else {
                                                            echo $cfg['label'];
                                                        }
                                                        ?>
                                                    </span>

                                                    <button class="btn rename-btn" 
                                                            onclick="startColumnRename('<?= $key ?>')">
                                                       <?php echo ts_icon('edit', 'w-4'); ?>
                                                    </button>
                                                    <span class="ms-2 badge"><?= isset($originalColumnCounts[$key]) ? $originalColumnCounts[$key] : count($columnTasks) ?></span>
                                                </h2>

                                                <?php if (empty($columnTasks) && $projectId == 0): ?>
                                                    <div class="alert alert-light text-center">
                                                        <?php echo ts_icon('info'); ?><?php echo $lang['No tasks in this column']; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($columnTasks)): ?>
                                                    <?php foreach ($columnTasks as $task): ?>
                                                        <?php 
                                                        try {
                                                            $taskId    = isset($task->id) ? $task->id : 0;
                                                            $projIdFor = isset($task->project_id) ? $task->project_id : 0;
                                                            $taskTitle = isset($task->title) ? htmlspecialchars($task->title) : 'Untitled Task';
                                                        ?>
                                                        <div class="card mb-2 task-card" 
                                                             draggable="<?= $can_change_status ? 'true' : 'false' ?>"
                                                             data-id="<?= $taskId ?>"
                                                             data-project-id="<?= $projIdFor ?>"
                                                             <?php if ($can_change_status): ?>
                                                                 ondragstart="drag(event)"
                                                                 ondragend="dragEnd(event)"
                                                             <?php endif; ?>>
                                                            <div class="card-body p-2">
                                                                <div class="d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <h5 class="card-title mb-1"><?= $taskTitle ?></h5>
                                                                    </div>
                                                                    <div class="dropdown">
                                                                        <button class="btn-dots text-muted p-0" 
                                                                                type="button" 
                                                                                data-bs-toggle="dropdown">
                                                                             <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                                                                        </button>
                                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                                            <li>
                                                                                <a class="dropdown-item" href="#" onclick="openTaskSidebar(<?= $taskId ?>); return false;">
                                                                                    <?php echo ts_icon('eye', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Task']; ?>
                                                                                </a>
                                                                            </li>
                                                                            <?php if ($projIdFor > 0): ?>
                                                                            <li>
                                                                                <a class="dropdown-item" href="overview?projectId=<?= (int) $projIdFor ?>">
                                                                                    <?php echo ts_icon('info', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['View Project']; ?>
                                                                                </a>
                                                                            </li>
                                                                            <?php endif; ?>
                                                                            <?php if (has_permission('task_edit')): ?>
                                                                            <li>
                                                                                <a class="dropdown-item" href="edit_task?id=<?= $taskId ?>">
                                                                                    <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Task']; ?>
                                                                                </a>
                                                                            </li>
                                                                            <?php endif; ?>
                                                                            <?php if (has_permission('task_delete')): ?>
                                                                            <li>
                                                                                <a class="dropdown-item text-danger" href="#" onclick="deleteTask(<?= $taskId ?>)">
                                                                                    <?php echo ts_icon('delete', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete Task']; ?>
                                                                                </a>
                                                                            </li>
                                                                            <?php endif; ?>
                                                                            <?php if (has_permission('task_duplicate')): ?>
                                                                            <li>
                                                                                <a class="dropdown-item" href="../includes/clone-task.php?id=<?= $taskId ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                                                                                    <?php echo ts_icon('duplicate', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Clone Task']; ?>
                                                                                </a>
                                                                            </li>
                                                                            <?php endif; ?>
                                                                        </ul>
                                                                    </div>
                                                                </div>

                                                                <?php if (isset($task->description) && !empty($task->description)): ?>
                                                                    <div class="task-description mb-2">
                                                                        <?= first_n_words($task->description, 15) ?>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php if (!empty($task->start_date) || !empty($task->due_date)): ?>
                                                                    <div class="task-date d-flex">
                                                                        <?php if (!empty($task->start_date)): ?>
                                                                            <div class="start-date">
                                                                                <b><?php echo $lang['Start']; ?>:</b> 
                                                                                <?= date('M j, Y', strtotime($task->start_date)) ?>
                                                                            </div>
                                                                        <?php endif; ?>

                                                                        <?php if (!empty($task->due_date)): ?>
                                                                            <?php 
                                                                            $dueDate = strtotime($task->due_date);
                                                                            $today = strtotime('today');
                                                                            $isOverdue = $dueDate < $today;
                                                                            $isDueToday = $dueDate == $today;
                                                                            ?>
                                                                            <div class="<?= $isOverdue ? 'text-danger' : ($isDueToday ? 'color-review' : '') ?>">
                                                                                <b><?php echo $lang['Due']; ?>:</b> 
                                                                                <?= date('M j, Y', strtotime($task->due_date)) ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php if (!empty($task->assigned_to)): ?>
                                                                    <div class="team-col d-flex align-items-baseline">
                                                                        <div class="d-flex avatar-head">
                                                                            <?php
                                                                            $assignedStaffIds = explode(',', $task->assigned_to);
                                                                            foreach ($assignedStaffIds as $staffId):
                                                                                if (!empty($staffId) && isset($projectStaff[$staffId])):
                                                                                    $staffUser = $projectStaff[$staffId];
                                                                            ?>
                                                                                <div class="avatar-overlap" data-bs-toggle="tooltip" data-bs-placement="top" title="<?php echo htmlspecialchars($staffUser->firstName . ' ' . ($staffUser->lastName ?? '')); ?>">
                                                                                    <?php 
                                                                                    echo getUserAvatarHtml($staffId, $staffUser->firstName, $staffUser->lastName ?? '', 30, 30, 'rounded-circle', $staffUser->firstName);
                                                                                    ?>
                                                                                </div>
                                                                            <?php 
                                                                                endif;
                                                                            endforeach;
                                                                            ?>
                                                                        </div>
                                                                        <div class="due-badge">
                                                                            <?php 
                                                                            if (!empty($task->due_date)):
                                                                                $dueDate = strtotime($task->due_date);
                                                                                $today = strtotime('today');
                                                                                
                                                                                if ($task->status == 'done' && !empty($task->completed_at)) {
                                                                                    // For completed tasks, use completion date to determine badge (locked badge)
                                                                                    // completed_at is updated every time task moves to 'done' to prevent cheating
                                                                                    $completedDate = strtotime($task->completed_at);
                                                                                    $completedDateOnly = strtotime(date('Y-m-d', $completedDate));
                                                                                    
                                                                                    if ($completedDateOnly < $dueDate) {
                                                                                        // Completed before due date = Task Pro
                                                                                        echo '<span class="badge color-done review done-bg-op">' . $lang['Task Pro'] . '</span>';
                                                                                    } elseif ($completedDateOnly == $dueDate) {
                                                                                        // Completed on due date = On Time (frozen badge)
                                                                                        echo '<span class="badge color-review review-bg-op">' . $lang['On Time'] . '</span>';
                                                                                    } else {
                                                                                        // Completed after due date = Overdue
                                                                                        echo '<span class="badge">' . $lang['Overdue'] . '</span>';
                                                                                    }
                                                                                } else {
                                                                                    // For non-completed tasks, use current date logic
                                                                                $isOverdue = $dueDate < $today;
                                                                                $isDueToday = $dueDate == $today;
                                                                                
                                                                                    if ($isOverdue): ?>
                                                                                <span class="badge"><?php echo $lang['Overdue']; ?></span>
                                                                                    <?php elseif ($isDueToday): ?>
                                                                                <span class="badge color-review review-bg-op"><?php echo $lang['Due Today']; ?></span>
                                                                                    <?php endif;
                                                                                }
                                                                            endif;
                                                                            ?>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php 
                                                        } catch (Exception $e) {
                                                            echo '<div class="alert alert-danger">Error rendering task: ' . $e->getMessage() . '</div>';
                                                        }
                                                        ?>
                                                    <?php endforeach; ?>
                                                    
                                                    <!-- Load More Button -->
                                                    <?php if (isset($originalColumnCounts[$key]) && $originalColumnCounts[$key] > 10): ?>
                                                    <div class="load-more-container" id="load-more-<?= $key ?>" style="display: block;">
                                                        <button class="primary-btn w-100 load-more-btn" 
                                                                data-status="<?= $key ?>" 
                                                                data-page="2">
                                                            <span class="load-more-text"><?php echo $lang['Load more tasks']; ?></span>
                                                            <span class="load-more-count" style="display: inline;">(<?= $originalColumnCounts[$key] - 10 ?>)</span>
                                                            <span class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
                                                        </button>
                                                    </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Column Rename Modal -->
                                <div class="modal fade" id="columnRenameModal" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <form id="columnRenameForm" 
                                              action="<?= $url ?>staff/task?projectId=<?= $projectId ?>" 
                                              method="post" 
                                              class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="card-title"><?php echo $lang['Rename Column']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"><?php echo ts_icon('close'); ?></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" id="column-key" name="column_key" value="">
                                                <input type="hidden" name="project_id" value="<?= $projectId ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                <div class="mb-3">
                                                    <label class="form-label"><?php echo $lang['Column Name']; ?></label>
                                                    <input type="text" class="form-control" id="column-name" name="column_name" required>
                                                    <?php if ($projectId == 0): ?>
                                                        <small class="form-text text-info mt-2">
                                                            <?php echo ts_icon('info'); ?>
                                                            <?php echo $lang['Changes made here will apply to all project views.']; ?>
                                                        </small>
                                                    <?php else: ?>
                                                        <small class="form-text text-muted mt-2">
                                                            <?php echo $lang['This change will apply to this project and the All Tasks view.']; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" 
                                                        class="btn secondary-btn" 
                                                        data-bs-dismiss="modal">
                                                    <?php echo $lang['Cancel']; ?>
                                                </button>
                                                <button type="submit" class="primary-btn">
                                                    <?php echo $lang['Save']; ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div> 
                        </div> 
                    </div> 
                </div> 
            </div> 
        </div> 
    </div> 
</div> 
   <!-- Include JavaScript files -->
<script src="../assets/js/task-date-range.js"></script>
<script src="../assets/js/task-bulk-delete.js"></script>
<script src="../assets/js/kanban-sticky-headers.js?v=4"></script>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php $__aiCtx = __DIR__ . '/../includes/ai_contextual_snippet.php';
if (is_file($__aiCtx)) { require_once $__aiCtx; if (function_exists('ai_contextual_emit')) { ai_contextual_emit(); } } ?>
<?php include("../templates/main-footer.php"); ?>