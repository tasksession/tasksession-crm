<?php 
// client/task.php
ob_start();
require_once("../includes/lib-initialize.php");

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
if($_SESSION['accountStatus'] == 3){
    redirectTo($url."staff/index.php");
} 

// Get current user info
$id = $session->userId; 
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;

// Redirect to projects.php if no projectId in GET
if (!isset($_GET['projectId']) || empty($_GET['projectId'])) {
    redirectTo($url . "client/projects");
}

// Sanitize & grab GET
$projectId = isset($_GET['projectId']) ? (int)$_GET['projectId'] : 0;

// If projectId is 0, redirect to projects.php
if ($projectId === 0) {
    redirectTo($url . "client/projects");
}

// ----------------------------------------------------------------------------
// ④— LOAD PROJECT & RELATED DATA
// ----------------------------------------------------------------------------
include(__DIR__ . '/../includes/project-sidebar-data.php');

$project   = projects::findByProjectId($projectId);
$projTitle = $project ? $project->project_title : "Project Tasks";

if (!$project) {
    redirectTo($url . "client/projects");
}

// Check if current user is the client for this project (main client or additional client)
$isClient = false;
if($project->c_id == $id || $project->main_client_id == $id) {
    $isClient = true;
} elseif(!empty($project->c_ids)) {
    $allClientIds = array_filter(explode(',', $project->c_ids));
    if(in_array($id, $allClientIds)) {
        $isClient = true;
    }
}

if(!$isClient){
    redirectTo($url . "client/projects?message=unauthorized");
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
// ⑧— HANDLE OPTIONAL "message" ALERTS
// ----------------------------------------------------------------------------
if (isset($_GET['message'])) {
    $msgType = $_GET['message'];
    if ($msgType == 'created') {
        $message = "<div class='alert alert-success'>Task created successfully!</div>";
    } else if ($msgType == 'updated') {
        $message = "<div class='alert alert-success'>Task updated successfully!</div>";
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

// Load task permissions for client
require_once("../includes/task_permission.php");
$taskPermissions = TaskPermission::getOrCreate($id);
$can_change_status = $taskPermissions->can_change_status;
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
                                          <div class="action-toggle border-btn-a" data-bs-toggle="collapse" data-bs-target="#project-menu<?php echo $recentProject->p_id;?>" role="button" tabindex="0">
											<span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
											<?php echo ts_icon('filter', 'w-2'); ?>
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
							
							
							
							<?php if ($taskPermissions->can_create_task): ?>
                            <div class="d-none d-md-block">
                                <?php if($projectId > 0): ?>
                                    <a href="add_task?projectId=<?= $projectId ?>" class="primary-btn">
                                        <?php echo $lang['Add New Task']; ?>
										<?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="add_task" class="primary-btn">
                                      <?php echo $lang['Add New Task']; ?>
									  <?php echo ts_icon('plus', 'dropdown-toggle-icon h-6'); ?>
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
                                <?php if ($startDateFilter || $endDateFilter): ?>
                                    <div class="alert alert-success mb-3">
                                        <strong>Date Filter Active:</strong> 
                                        <?php 
                                        if ($startDateFilter && $endDateFilter) {
                                            echo 'From ' . date('M j, Y', strtotime($startDateFilter)) . ' to ' . date('M j, Y', strtotime($endDateFilter));
                                        } elseif ($startDateFilter) {
                                            echo 'From ' . date('M j, Y', strtotime($startDateFilter)) . ' onwards';
                                        } elseif ($endDateFilter) {
                                            echo 'Until ' . date('M j, Y', strtotime($endDateFilter));
                                        }
                                        ?>
                                        <a href="#" onclick="clearDateRange(); return false;" class="float-end btn btn-sm btn-outline-success">
                                            Clear Date Filter
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($message)) echo $message; ?>
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
                                                    <span class="ms-2 badge"><?= count($columnTasks) ?></span>
                                                </h2>

                                                <?php if (empty($columnTasks)): ?>
                                                    <div class="alert alert-light text-center">
                                                        <?php echo $lang['No tasks in this column']; ?>
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
                                                                                <a class="dropdown-item" href="#" 
                                                                                   onclick="openTaskSidebar(<?= $taskId ?>); return false;">
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
                                                                            <?php if ($taskPermissions->can_update_task): ?>
                                                                            <li>
                                                                                <a class="dropdown-item" href="edit_task?id=<?= $taskId ?>">
                                                                                    <?php echo ts_icon('edit', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Edit Task']; ?>
                                                                                </a>
                                                                            </li>
                                                                            <?php endif; ?>
                                                                            <?php if ($taskPermissions->can_delete_task): ?>
                                                                            <li>
                                                                                <a class="dropdown-item text-danger" href="#" onclick="deleteTask(<?= $taskId ?>); return false;">
                                                                                    <?php echo ts_icon('close', 'tasksession-timer-log-menu-ico me-2'); ?><?php echo $lang['Delete Task']; ?>
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
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
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
<script src="../assets/js/task-date-range.js"></script>
<?php $__aiCtx = __DIR__ . '/../includes/ai_contextual_snippet.php';
if (is_file($__aiCtx)) { require_once $__aiCtx; if (function_exists('ai_contextual_emit')) { ai_contextual_emit(); } } ?>
<?php include("../templates/main-footer.php"); ?>

