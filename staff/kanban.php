<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : kanban.php
   Purpose : Displays and manages the Kanban board for all tasks across projects
 ================================================================================
 */
ob_start();
require_once("../includes/lib-initialize.php");
require_once("../includes/permissions.php");
ensure_user_permissions($connect);
$title = "Kanban Board | ". $syatem_title;
include("../templates/header.php");

// Authentication check
if(!($session->isLoggedIn())){
		redirectTo($url."index.php");
	}
if($_SESSION['accountStatus'] == 2){
	redirectTo($url."client/index.php");
}
if($_SESSION['accountStatus'] == 1){
	redirectTo($url."admin/index.php");
} 
//condition check for login

$id=$session->userId; //id of the current logged in user 
$user = User::findById((int)$id); //take the record of current user in an object array 	
$username=$user->firstName;;
$email=$user->email;;
$account_stat=$user->status;;
$user->regDate;


// Handle column deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_column'], $_POST['column_key'])) {
    $columnKey = $_POST['column_key'];
    if (strpos($columnKey, 'custom_') === 0) {
        $projectId = 0;
        $userId = (int)$session->userId;
        $stmt = $connect->prepare("DELETE FROM project_columns WHERE project_id = ? AND column_key = ? AND user_id = ?");
        $stmt->bind_param("isi", $projectId, $columnKey, $userId);
        $stmt->execute();
        $stmt->close();
        exit;
    }
}

// Handle bulk delete tasks
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete_action']) && $_POST['bulk_delete_action'] === 'delete') {
    if (!has_permission('task_delete')) {
        $_SESSION['message'] = 'Not authorized to delete tasks.';
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
    if (isset($_POST['bulk_delete_tasks']) && is_array($_POST['bulk_delete_tasks'])) {
        $taskIds = array_map('intval', $_POST['bulk_delete_tasks']);
        $taskIds = array_filter($taskIds);

        if (!empty($taskIds)) {
            $successCount = 0;
            $failedTasks = [];

            foreach ($taskIds as $taskId) {
                $task = Task::findById($taskId);

                if (!$task) {
                    $failedTasks[] = "Task ID $taskId not found";
                    continue;
                }

                if ($task->user_id != $session->userId) {
                    $failedTasks[] = "No permission to delete task ID $taskId";
                    continue;
                }

                if ($task->delete()) {
                    $successCount++;
                } else {
                    $failedTasks[] = "Failed to delete task ID $taskId";
                }
            }

            if ($successCount > 0) {
                if ($successCount === count($taskIds)) {
                    $_SESSION['message'] = "Successfully deleted $successCount task(s).";
                } else {
                    $_SESSION['message'] = "Partially successful: $successCount deleted, " . count($failedTasks) . " failed.";
                }
            } else {
                $_SESSION['message'] = "No tasks were deleted.";
            }

            header("Location: " . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['column_key'], $_POST['column_name'])) {
    $columnKey = $_POST['column_key'];
    $columnName = trim($_POST['column_name']);
    $projectId = 0; // For global columns in kanban
    $userId = (int)$session->userId;

    // Check if the column already exists for this user
    $stmt = $connect->prepare("SELECT * FROM project_columns WHERE project_id = ? AND column_key = ? AND user_id = ?");
    $stmt->bind_param("isi", $projectId, $columnKey, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        // Update existing column name for this user
        $stmt = $connect->prepare("UPDATE project_columns SET custom_name = ? WHERE project_id = ? AND column_key = ? AND user_id = ?");
        $stmt->bind_param("sisi", $columnName, $projectId, $columnKey, $userId);
        $stmt->execute();
        $stmt->close();
    } else {
        // Check for a row with user_id IS NULL (shared/global)
        $stmt = $connect->prepare("SELECT * FROM project_columns WHERE project_id = ? AND column_key = ? AND user_id IS NULL");
        $stmt->bind_param("is", $projectId, $columnKey);
        $stmt->execute();
        $resultNull = $stmt->get_result();
        $stmt->close();
        
        if ($resultNull->num_rows > 0) {
            // Convert global row to user-specific
            $stmt = $connect->prepare("UPDATE project_columns SET custom_name = ?, user_id = ? WHERE project_id = ? AND column_key = ? AND user_id IS NULL");
            $stmt->bind_param("siis", $columnName, $userId, $projectId, $columnKey);
            $stmt->execute();
            $stmt->close();
        } else {
            // Insert new column for this user
            $stmt = $connect->prepare("INSERT INTO project_columns (project_id, column_key, custom_name, user_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $projectId, $columnKey, $columnName, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
    // Redirect to avoid form resubmission
    header("Location: kanban");
    exit;
}

function first_n_words($text, $limit = 15) {
    $plain = strip_tags($text);
    $words = preg_split('/\s+/', $plain);
    if (count($words) > $limit) {
        $short = implode(' ', array_slice($words, 0, $limit)) . '...';
        return $short;
    }
    return $plain;
}



// Define status types and labels
$statusTypes = ['todo', 'inprogress', 'review', 'done'];
$statusLabels = [
    'todo' => 'To Do',
    'inprogress' => 'In Progress',
    'review' => 'In Review',
    'done' => 'Completed'
];

// Define all valid status filters (including badge filters)
$allStatusFilters = ['todo', 'inprogress', 'review', 'done', 'overdue', 'due_today', 'due_soon', 'task_pro', 'on_time', 'recurring'];

// Load custom column names from database
global $database;
$kanbanTimerActiveByTask = [];
if (!empty($id)) {
    $kanbanTimerTq = $database->query(
        "SELECT task_id, status FROM task_timer_sessions WHERE user_id = " . (int)$id . " AND status IN ('running', 'paused')"
    );
    if ($kanbanTimerTq && $database->numRows($kanbanTimerTq) > 0) {
        while ($kanbanTimerRow = $database->fetchArray($kanbanTimerTq)) {
            if (!empty($kanbanTimerRow['task_id'])) {
                $kanbanTimerActiveByTask[(int)$kanbanTimerRow['task_id']] = strtolower(trim((string)$kanbanTimerRow['status']));
            }
        }
    }
}
$columnNamesQuery = "SELECT column_key, custom_name FROM project_columns WHERE project_id = 0";
$columnNamesResult = $database->query($columnNamesQuery);

if ($columnNamesResult && $database->numRows($columnNamesResult) > 0) {
    while ($row = $database->fetchArray($columnNamesResult)) {
        if (isset($statusLabels[$row['column_key']])) {
            $statusLabels[$row['column_key']] = $row['custom_name'];
        }
    }
}

// Get status filter from URL
$statusFilter = isset($_GET['status']) && in_array($_GET['status'], $allStatusFilters) ? $_GET['status'] : null;
$internalFilter = isset($_GET['internal']) && $_GET['internal'] == '1';

// Get search query from URL
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get date range filters from URL
$startDateFilter = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDateFilter = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$sortOrder = isset($_GET['sort_order']) ? trim((string)$_GET['sort_order']) : 'desc';
if (!in_array($sortOrder, ['asc', 'desc'], true)) {
    $sortOrder = 'desc';
}

// Helper function to build URLs with all current parameters
function buildFilterUrl($baseUrl, $additionalParams = []) {
    global $statusFilter, $searchQuery, $startDateFilter, $endDateFilter, $showMyTasks, $showArchive, $sortOrder;
    
    $params = [];
    
    if ($showArchive) {
        $params[] = 'archive=1';
    }
    if (!$showMyTasks && !$showArchive) {
        $params[] = 'all_tasks=1';
    }
    if ($statusFilter) {
        $params[] = 'status=' . htmlspecialchars($statusFilter);
    }
    if ($searchQuery) {
        $params[] = 'search=' . htmlspecialchars($searchQuery);
    }
    if ($startDateFilter) {
        $params[] = 'start_date=' . htmlspecialchars($startDateFilter);
    }
    if ($endDateFilter) {
        $params[] = 'end_date=' . htmlspecialchars($endDateFilter);
    }
    if (!empty($sortOrder)) {
        $params[] = 'sort_order=' . htmlspecialchars($sortOrder);
    }
    
    // Add additional parameters
    foreach ($additionalParams as $key => $value) {
        $params[] = $key . '=' . htmlspecialchars($value);
    }
    
    return $baseUrl . (!empty($params) ? '?' . implode('&', $params) : '');
}

// Check if we're showing "My Tasks" or "All Tasks"
$showMyTasks = !isset($_GET['all_tasks']) || $_GET['all_tasks'] !== '1';
$showArchive = isset($_GET['archive']);

$kanbanCanViewAllTasks = staff_kanban_can_view_all_tasks();
$kanbanCanViewCreatedTab = staff_kanban_can_view_created_tasks_tab();
$kanbanShowAllTasksTab = staff_kanban_show_all_tasks_tab();

if (!$showMyTasks && !$showArchive && !$kanbanShowAllTasksTab) {
    $showMyTasks = true;
}

// Always show all tasks (projectId = 0)
$projectId = 0;
$project   = null;
$projTitle = "All Projects";

// load tasks model
require_once("../includes/task.php");

$kanbanOpts = [
    'userId' => (int) $id,
    'showMyTasks' => $showMyTasks,
    'showArchive' => $showArchive,
    'internalFilter' => $internalFilter,
    'searchQuery' => $searchQuery,
    'startDateFilter' => $startDateFilter,
    'endDateFilter' => $endDateFilter,
    'statusFilter' => $statusFilter,
    'sortOrder' => $sortOrder,
    'kanbanScopeCreatedOnly' => !$showMyTasks && !$showArchive && !$kanbanCanViewAllTasks && $kanbanCanViewCreatedTab,
];

$kanbanColumnCountsFromSql = null;
$kanbanUseColumnLimitedLoad = !$showArchive
    && !Task::kanbanRequiresPhpFilters($kanbanOpts)
    && $searchQuery === ''
    && $startDateFilter === ''
    && $endDateFilter === ''
    && ($statusFilter === null || $statusFilter === '');

// Load tasks based on view mode (SQL-optimized where possible)
if ($showArchive) {
    $allTasks = Task::findArchived();
    if (!$kanbanCanViewAllTasks) {
        $allTasks = array_filter($allTasks, function ($task) use ($id) {
            return $task->isAssignedTo($id)
                || (int) ($task->creator_id ?? 0) === (int) $id
                || (int) ($task->user_id ?? 0) === (int) $id;
        });
    }
} elseif ($kanbanUseColumnLimitedLoad) {
    $allTasks = [];
} else {
    $allTasks = Task::findForKanban($kanbanOpts);
}

// Apply filters (skipped when SQL column-limited load handles criteria)
if (!$kanbanUseColumnLimitedLoad) {
$kanbanRecurringFilterMap = null;
if ($statusFilter === 'recurring') {
    require_once dirname(__DIR__) . '/includes/task_recurrence_helper.php';
    $__rfIds = [];
    foreach ($allTasks as $__t) {
        if (!empty($__t->id)) {
            $__rfIds[] = (int) $__t->id;
        }
    }
    $kanbanRecurringFilterMap = function_exists('tasksession_recurrence_map_for_task_ids')
        ? tasksession_recurrence_map_for_task_ids($__rfIds)
        : [];
    unset($__rfIds, $__t);
}
if ($internalFilter) {
    $allTasks = array_filter($allTasks, function($task) {
        return isset($task->project_id) && $task->project_id == 0;
    });
} elseif ($statusFilter || $searchQuery || $startDateFilter || $endDateFilter || ($showMyTasks && !$showArchive)) {
    $allTasks = array_filter($allTasks, function($task) use ($statusFilter, $searchQuery, $startDateFilter, $endDateFilter, $showMyTasks, $showArchive, $id, $kanbanCanViewAllTasks, $kanbanCanViewCreatedTab, $kanbanRecurringFilterMap) {
        // Handle computed status filters
        $statusMatch = true;
        if ($statusFilter) {
            if ($statusFilter === 'recurring') {
                $tid = isset($task->id) ? (int) $task->id : 0;
                $statusMatch = ($tid > 0 && !empty($kanbanRecurringFilterMap[$tid]))
                    || (isset($task->recurrence_id) && (int) $task->recurrence_id > 0);
            } elseif ($statusFilter === 'due_soon') {
                if ($task->status === 'done' || empty($task->due_date)) {
                    $statusMatch = false;
                } else {
                    $dueDateOnly = strtotime(date('Y-m-d', strtotime($task->due_date)));
                    $today = strtotime('today');
                    $threeDaysOut = strtotime('+3 days', $today);
                    $statusMatch = ($dueDateOnly >= $today && $dueDateOnly <= $threeDaysOut);
                }
            } elseif (in_array($statusFilter, ['overdue', 'due_today', 'task_pro', 'on_time'])) {
                // For badge-based filters, only apply to completed tasks
                if ($task->status !== 'done') {
                    $statusMatch = false; // Non-completed tasks don't match badge filters
                } else {
                    // For completed tasks, calculate badge based on completion date
                    $dueDate = strtotime($task->due_date);
                    $today = strtotime('today');
                    
                    // Use completed_at if available, otherwise fallback to created_at
                    if (!empty($task->completed_at)) {
                        $completedDate = strtotime($task->completed_at);
                        $completedDateOnly = strtotime(date('Y-m-d', $completedDate));
                    } else {
                        // Fallback to created_at if completed_at is not set
                        $completedDate = strtotime($task->created_at);
                        $completedDateOnly = strtotime(date('Y-m-d', $completedDate));
                    }
                    
                    $dueDateOnly = strtotime(date('Y-m-d', $dueDate));
                    
                    switch ($statusFilter) {
                        case 'overdue':
                            $statusMatch = $completedDateOnly > $dueDateOnly;
                            break;
                        case 'due_today':
                            $statusMatch = $dueDate == $today;
                            break;
                        case 'task_pro':
                            $statusMatch = $completedDateOnly < $dueDateOnly;
                            break;
                        case 'on_time':
                            $statusMatch = $completedDateOnly == $dueDateOnly;
                            break;
                    }
                }
            } else {
                // Handle regular status filters
                $statusMatch = $task->status === $statusFilter;
            }
        }
        
        $searchMatch = !$searchQuery || 
                      stripos($task->title, $searchQuery) !== false || 
                      stripos($task->description, $searchQuery) !== false;
        
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
        
        // Handle assignment / created scope for staff tabs
        $scopeMatch = true;
        if ($showMyTasks && !$showArchive) {
            $scopeMatch = $task->isAssignedTo($id);
        } elseif (!$showMyTasks && !$showArchive && !$kanbanCanViewAllTasks && $kanbanCanViewCreatedTab) {
            $scopeMatch = (int) ($task->creator_id ?? 0) === (int) $id
                || (int) ($task->user_id ?? 0) === (int) $id;
        }
        
        return $statusMatch && $searchMatch && $dateMatch && $scopeMatch;
    });
}
}

// Load all columns from project_columns (not just default four)
$statuses = [];
$defaultIcons = [
  'todo' => 'color-todo-bg',
  'inprogress' => 'color-inprogress-bg',
  'review' => 'color-review-bg',
  'done' => 'color-done-bg',
];

// Add default statuses first
$statuses['todo'] = ['icon' => $defaultIcons['todo'], 'label' => $statusLabels['todo']];
$statuses['inprogress'] = ['icon' => $defaultIcons['inprogress'], 'label' => $statusLabels['inprogress']];
$statuses['review'] = ['icon' => $defaultIcons['review'], 'label' => $statusLabels['review']];
$statuses['done'] = ['icon' => $defaultIcons['done'], 'label' => $statusLabels['done']];

$columnsQuery = "SELECT column_key, custom_name FROM project_columns WHERE project_id = 0 AND (user_id IS NULL OR user_id = $id) ORDER BY id ASC";
$columnsResult = $database->query($columnsQuery);
if ($columnsResult && $database->numRows($columnsResult) > 0) {
  while ($row = $database->fetchArray($columnsResult)) {
    $key = $row['column_key'];
    $label = $row['custom_name'];
    $icon = isset($defaultIcons[$key]) ? $defaultIcons[$key] : 'text-secondary';
    $statuses[$key] = ['icon' => $icon, 'label' => $label];
  }
}

if ($kanbanUseColumnLimitedLoad) {
    $loaded = Task::loadKanbanColumnsLimited($kanbanOpts, array_keys($statuses), 11);
    $allTasks = $loaded['tasks'];
    $kanbanColumnCountsFromSql = $loaded['columnCounts'];
}

// group by status
$columns = [];
foreach ($statuses as $key => $cfg) {
  $columns[$key] = [];
}

// Get personal column mappings for current user
$personalMappings = [];
if (!empty($allTasks)) {
    $taskIds = array_map(function($task) { return $task->id; }, $allTasks);
    if (!empty($taskIds)) {
        $taskIdsStr = implode(',', $taskIds);
        $personalMappingQuery = "SELECT task_id, column_key FROM extra_tasks_columns WHERE user_id = $id AND task_id IN ($taskIdsStr)";
        $personalMappingResult = $database->query($personalMappingQuery);
        if ($personalMappingResult && $database->numRows($personalMappingResult) > 0) {
            while ($row = $database->fetchArray($personalMappingResult)) {
                $personalMappings[$row['task_id']] = $row['column_key'];
            }
        }
    }
}

// For "My Tasks" view, personal mappings are already filtered by the tasks loaded
// No need for additional database queries

// When grouping tasks, only use the default statuses and personal columns
$defaultStatuses = ['todo', 'inprogress', 'review', 'done'];
$personalColumns = array_diff(array_keys($statuses), $defaultStatuses);

foreach ($allTasks as $t) {
  $taskId = $t->id;
  
  // For "My Tasks" view, tasks are already filtered at database level
  // No need to check assignment again here
  
  // Check if this task is in a personal column for current user
  if (isset($personalMappings[$taskId])) {
    $personalColumn = $personalMappings[$taskId];
    // Only add to personal column if it exists in current user's columns
    if (isset($statuses[$personalColumn])) {
      $columns[$personalColumn][] = $t;
      continue; // Skip adding to default columns
    }
  }
  
  // Use default status logic
  $status = $t->status;
  if (!in_array($status, $defaultStatuses) && !in_array($status, $personalColumns)) {
    // Use last_default_status if status is not a default or personal column
    $status = $t->last_default_status ?: 'inprogress';
  }
  
  // Add to columns (default, personal, or custom)
  if (isset($columns[$status])) {
    $columns[$status][] = $t;
  } elseif (isset($columns['todo'])) {
    $columns['todo'][] = $t;
  }
}

// Canonical order = position ASC, then created_at / id ASC (stored order). sort_order=desc only reverses display.
foreach ($columns as $columnKey => $columnTasks) {
  usort($columnTasks, function ($a, $b) {
    $aPos = isset($a->position) ? (int)$a->position : 0;
    $bPos = isset($b->position) ? (int)$b->position : 0;
    if ($aPos !== $bPos) {
      return $aPos <=> $bPos;
    }
    $aTime = !empty($a->created_at) ? strtotime($a->created_at) : 0;
    $bTime = !empty($b->created_at) ? strtotime($b->created_at) : 0;
    if ($aTime === $bTime) {
      $aId = isset($a->id) ? (int)$a->id : 0;
      $bId = isset($b->id) ? (int)$b->id : 0;
      return $aId <=> $bId;
    }
    return $aTime <=> $bTime;
  });
  if ($sortOrder === 'desc') {
    $columnTasks = array_reverse($columnTasks);
  }
  $columns[$columnKey] = $columnTasks;
}

// Store original task counts for Load More button logic
$originalColumnCounts = [];
if (is_array($kanbanColumnCountsFromSql)) {
    foreach ($kanbanColumnCountsFromSql as $columnKey => $cnt) {
        $originalColumnCounts[$columnKey] = (int) $cnt;
    }
} else {
foreach ($columns as $columnKey => $columnTasks) {
  $originalColumnCounts[$columnKey] = count($columnTasks);
}
}

// Limit initial display to 10 tasks per column
foreach ($columns as $columnKey => $columnTasks) {
  if (count($columnTasks) > 10) {
    $columns[$columnKey] = array_slice($columnTasks, 0, 10);
  }
}

require_once dirname(__DIR__) . '/includes/task_recurrence_helper.php';
$kanbanRecurringTaskIds = [];
$__recurringScanIds = [];
foreach ($columns as $__colTasks) {
    foreach ($__colTasks as $__t) {
        if (!empty($__t->id)) {
            $__recurringScanIds[] = (int) $__t->id;
        }
    }
}
if ($__recurringScanIds !== [] && function_exists('tasksession_recurrence_map_for_task_ids')) {
    $kanbanRecurringTaskIds = tasksession_recurrence_map_for_task_ids($__recurringScanIds);
}
unset($__recurringScanIds, $__colTasks, $__t);

// Get total task count for "All Tasks" / "My Tasks" / archive tabs
$kanbanTabCounts = Task::kanbanTabCounts((int)$id, $kanbanCanViewAllTasks, $kanbanCanViewCreatedTab);
$total_all_tasks = $kanbanTabCounts['total_all'];
$total_my_tasks = $kanbanTabCounts['my_tasks'];
$total_archived_tasks = $kanbanTabCounts['archived'];

$hasKanbanListFilters = !empty($internalFilter)
    || $searchQuery !== ''
    || $startDateFilter !== ''
    || $endDateFilter !== ''
    || ($statusFilter !== null && $statusFilter !== '');

if ($hasKanbanListFilters || !$kanbanUseColumnLimitedLoad) {
    $display_task_count = count($allTasks);
} elseif ($showArchive) {
    $display_task_count = $total_archived_tasks;
} elseif ($showMyTasks) {
    $display_task_count = $total_my_tasks;
} else {
    $display_task_count = $total_all_tasks;
}

$total_visible_tasks = $display_task_count;

// Get all staff and admin users
$projectStaff = [];
$staffUsers = User::findBySql("SELECT id, firstName, email, title FROM users WHERE (accountStatus = 3 OR accountStatus = 1) AND status = 0");
foreach ($staffUsers as $staffUser) {
  $projectStaff[$staffUser->id] = $staffUser;
}

// Handle messages
/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;
if(isset($_GET['message'])) {
    $msgType = $_GET['message'];
    if($msgType == 'created') {
        $toast_flash = array('type' => 'success', 'msg' => 'Task created successfully!');
    } else if($msgType == 'updated') {
        $toast_flash = array('type' => 'success', 'msg' => 'Task updated successfully!');
    }
}

$viewUrlExtra = [];
if ($internalFilter) {
    $viewUrlExtra['internal'] = '1';
}
$kanbanGridViewUrl = buildFilterUrl('kanban', $viewUrlExtra);
$kanbanTableViewUrl = buildFilterUrl('all-tasks', $viewUrlExtra);

$hasActiveKanbanFilters = (bool) ($statusFilter || $internalFilter || $startDateFilter || $endDateFilter);
$clearKanbanFilterParams = array();
if ($showArchive) {
    $clearKanbanFilterParams['archive'] = '1';
} elseif (!$showMyTasks && $kanbanShowAllTasksTab) {
    $clearKanbanFilterParams['all_tasks'] = '1';
}
if ($searchQuery !== '') {
    $clearKanbanFilterParams['search'] = $searchQuery;
}
if (!empty($sortOrder)) {
    $clearKanbanFilterParams['sort_order'] = $sortOrder;
}
$clearKanbanFiltersUrl = 'kanban' . (!empty($clearKanbanFilterParams) ? '?' . http_build_query($clearKanbanFilterParams) : '');
$kanbanSearchClearParams = $clearKanbanFilterParams;
unset($kanbanSearchClearParams['search']);
$kanbanSearchClearHref = 'kanban' . (!empty($kanbanSearchClearParams) ? '?' . http_build_query($kanbanSearchClearParams) : '');
$kanbanCalendarTabParams = $kanbanSearchClearParams;
$kanbanCalendarTabHref = 'calendar' . (!empty($kanbanCalendarTabParams) ? '?' . http_build_query($kanbanCalendarTabParams) : '');

$kanbanCanBulkSelect = has_permission('task_edit') || has_permission('task_delete');
$kanbanCanDelete = has_permission('task_delete');
$kanbanCanArchive = has_permission('task_edit');

$can_change_status = has_permission('task_status_update'); 
?>
<div class="page-container vh-100">
    <div class="container-fluid vh-100">
        <div class="row row-eq-height vh-100">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content">
                <?php include('../templates/top-header.php'); ?>
                <link rel="stylesheet" href="../assets/css/kanban-bulk.css?v=10">
                     <div class="row bg-grey">
                      <div class="col-md-12 margin-top-10 project-tabs">
                        <div class="row">
						   <div class="project-tabs-header">
							<div class="scrollable-tabs-container d-flex col-gap-40 col-gap-40-sep">
							   <div class="main-heading">
									<h1>
										<?php echo $lang['Kanban Board']; ?>
										<span>(<?php echo $display_task_count; ?> <?php echo $lang['Task']; ?>)</span>
									</h1>
								</div>
                                <?php /* AI actions: templates/partials/ai-menu.php (beside Filters) */ ?>
					  		 <div class="icon-container sep" id="kanbanNavTabs">
								<a href="kanban<?php echo ($statusFilter || (isset($searchQuery) && !empty($searchQuery)) || !empty($sortOrder)) ? '?' : ''; ?><?php echo $statusFilter ? 'status=' . htmlspecialchars($statusFilter) : ''; ?><?php echo isset($searchQuery) && !empty($searchQuery) ? ($statusFilter ? '&' : '') . 'search=' . htmlspecialchars($searchQuery) : ''; ?><?php echo !empty($sortOrder) ? (($statusFilter || (isset($searchQuery) && !empty($searchQuery))) ? '&' : '') . 'sort_order=' . htmlspecialchars($sortOrder) : ''; ?>" class="<?php echo ($showMyTasks && !$showArchive) ? 'active' : ''; ?>">
									<?php echo ts_icon('tasks'); ?><?php echo $lang['My Tasks']; ?> (<?php echo $total_my_tasks; ?>)</a>
									<?php if ($kanbanShowAllTasksTab): ?>
									<a href="kanban?all_tasks=1<?php echo $statusFilter ? '&status=' . htmlspecialchars($statusFilter) : ''; ?><?php echo isset($searchQuery) && !empty($searchQuery) ? '&search=' . htmlspecialchars($searchQuery) : ''; ?><?php echo !empty($sortOrder) ? '&sort_order=' . htmlspecialchars($sortOrder) : ''; ?>" class="<?php echo (!$showMyTasks && !$showArchive) ? 'active' : ''; ?>">
										<?php echo ts_icon('inbox-stack'); ?><?php echo $lang['All Tasks']; ?> (<?php echo $total_all_tasks; ?>)</a>
									<?php endif; ?>
								<a href="<?php echo htmlspecialchars(buildFilterUrl('kanban', array_merge($viewUrlExtra, ['archive' => '1']))); ?>" class="<?php echo $showArchive ? 'active' : ''; ?>">
									<?php echo ts_icon('archive-box'); ?>
									<?php echo $lang['Archive'] ?? 'Archive'; ?> (<?php echo $total_archived_tasks; ?>)
								</a>
								<a href="<?php echo htmlspecialchars($kanbanCalendarTabHref, ENT_QUOTES, 'UTF-8'); ?>">
									<?php echo ts_icon('calendar'); ?>
									<?php echo $lang['Calendar View'] ?? 'Calendar View'; ?>
								</a>
							</div>
						</div>   
					</div> 
					   					 <div class="search">
                                            <div class="search-icon border-btn-a" onclick="toggleSearch()">
                                                <?php echo ts_icon('search', 'w-2'); ?>
                                            </div>
                                            <form method="GET" action="" class="search-form" id="searchForm">
                                                <?php if($statusFilter): ?>
                                                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                                <?php endif; ?>
                                                <?php if($internalFilter): ?>
                                                    <input type="hidden" name="internal" value="1">
                                                <?php endif; ?>
                                                <?php if($showArchive): ?>
                                                    <input type="hidden" name="archive" value="1">
                                                <?php elseif(!$showMyTasks && $kanbanShowAllTasksTab): ?>
                                                    <input type="hidden" name="all_tasks" value="1">
                                                <?php endif; ?>
                                                <?php if($startDateFilter): ?>
                                                    <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDateFilter); ?>">
                                                <?php endif; ?>
                                                <?php if($endDateFilter): ?>
                                                    <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDateFilter); ?>">
                                                <?php endif; ?>
                                                <input type="hidden" name="sort_order" value="<?php echo htmlspecialchars($sortOrder); ?>">
                                                <div class="input-group">
                                                    <span class="search-field-icon">
                                                        <?php echo ts_icon('search', 'w-2'); ?>
                                                    </span>
                                                    <input type="text" 
                                                           name="search" 
                                                           class="form-control" 
                                                           placeholder="<?php echo htmlspecialchars($lang['Search tasks...'] ?? 'Search tasks...', ENT_QUOTES, 'UTF-8'); ?>" 
                                                           value="<?php echo htmlspecialchars($searchQuery ?? ''); ?>">
                                                    <?php if ($searchQuery !== ''): ?>
                                                        <a href="<?php echo htmlspecialchars($kanbanSearchClearHref, ENT_QUOTES, 'UTF-8'); ?>" class="cross" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo ts_icon('close', 'w-2'); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="#" class="cross" onclick="toggleSearch(); return false;" title="<?php echo htmlspecialchars($lang['Close'] ?? 'Close', ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo ts_icon('close', 'w-2'); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </form>
                                        </div>
									<div class="edit-overview-btn d-none d-md-block" id="kanbanBulkToolbar">
										<div class="icon-container sep">
											<div class="pm-trash task-trash align-middle d-flex col-gap-5">
												<?php if ($kanbanCanBulkSelect): ?>
												<a href="#" onclick="enterBulkMode(); return false;" class="bulk-delete-tab red border-btn-a" id="bulkSelectTab" title="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Bulk Select'] ?? 'Bulk select', ENT_QUOTES, 'UTF-8'); ?>">
													<?php echo ts_icon('duplicate', 'w-2'); ?>
												</a>
												<?php endif; ?>
												<div class="media-view-toggle" id="kanbanViewToggle" role="group" aria-label="View mode">
													<a href="<?php echo htmlspecialchars($kanbanGridViewUrl, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a is-active" title="<?php echo htmlspecialchars($lang['Grid View'] ?? 'Grid view', ENT_QUOTES, 'UTF-8'); ?>">
														<?php echo ts_icon('view-grid', 'w-2'); ?>
													</a>
													<a href="<?php echo htmlspecialchars($kanbanTableViewUrl, ENT_QUOTES, 'UTF-8'); ?>" class="media-view-toggle__btn border-btn-a" title="<?php echo htmlspecialchars($lang['Table View'] ?? 'Table view', ENT_QUOTES, 'UTF-8'); ?>">
														<?php echo ts_icon('table', 'w-2'); ?>
													</a>
												</div>
												<?php if ($kanbanCanBulkSelect): ?>
												<a href="#" onclick="exitBulkMode(); return false;" class="bulk-delete-tab border-btn-a" id="bulkBackTab" style="display:none;">
													<?php echo ts_icon('arrow-left', 'w-2'); ?>
													<span><?php echo $lang['Back'] ?? 'Back'; ?></span>
												</a>
												<a href="#" onclick="selectAllTasks(); return false;" class="bulk-delete-tab border-btn-a" id="bulkSelectAllTab" style="display:none;">
													<?php echo ts_icon('check-circle', 'w-2'); ?>
													<span id="bulkSelectAllLabel"><?php echo $lang['Select all'] ?? 'Select all'; ?></span>
												</a>
												<?php if ($kanbanCanDelete): ?>
												<a href="#" onclick="confirmBulkDelete(); return false;" class="bulk-delete-tab border-btn-a" id="bulkDeleteTab" style="display:none;">
													<?php echo ts_icon('delete', 'w-2'); ?>
													<span id="bulkDeleteLabel"><?php echo $lang['Delete'] ?? 'Delete'; ?></span>
												</a>
												<?php endif; ?>
												<?php if ($kanbanCanArchive): ?>
												<a href="#" onclick="confirmBulkArchive(); return false;" class="bulk-delete-tab border-btn-a" id="bulkArchiveTab" style="display:none;">
													<?php echo ts_icon('archive-box', 'w-2'); ?>
													<span id="bulkArchiveLabel"><?php echo $showArchive ? ($lang['Unarchive Task'] ?? 'Unarchive') : ($lang['Archive'] ?? 'Archive'); ?></span>
												</a>
												<?php endif; ?>
												<?php endif; ?>
											</div>
										</div>
									</div>
									<?php
									$aiMenuId = 'aiHeaderMenuKanban' . (int) ($recentProject->p_id ?? 0);
									include dirname(__DIR__) . '/templates/partials/ai-menu.php';
									unset($aiMenuId);
									?>
									<div class="edit-overview-btn kanban-header-filters">
										<td class="extra-height">
                                          <div class="action-toggle border-btn-a<?php echo $hasActiveKanbanFilters ? ' has-active-filters' : ''; ?>" data-bs-toggle="collapse" data-bs-target="#project-menu<?php echo $recentProject->p_id;?>" role="button" tabindex="0">
											<span class="action-text"><?php echo $lang['Filters']; ?></span><span class="mobile-ellipsis"><?php echo ts_icon('ellipsis', 'w-2'); ?></span>
											<?php echo ts_icon('filter', 'w-2'); ?>
											<?php if ($hasActiveKanbanFilters): ?>
											<a href="<?php echo htmlspecialchars($clearKanbanFiltersUrl); ?>" class="kanban-filter-clear" title="<?php echo htmlspecialchars($lang['Clear'] ?? 'Clear', ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($lang['Clear filters'] ?? 'Clear filters', ENT_QUOTES, 'UTF-8'); ?>" onclick="event.stopPropagation();">
												<?php echo ts_icon('close'); ?>
											</a>
											<?php endif; ?>
										</div>
										  <div id="project-menu<?php echo $recentProject->p_id;?>" class="toggle-action collapse shadow-dept">
											<ul>
												<li class="<?php echo !$statusFilter ? 'active' : ''; ?>" data-status="all">
													<a href="?<?php echo !$showMyTasks ? 'all_tasks=1' : ''; ?><?php echo $startDateFilter ? (!empty($showMyTasks) ? '&' : '') . 'start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?><?php echo !empty($sortOrder) ? '&sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
														<span><b><?php echo $lang['All Task']; ?></b></span>
													</a>
												</li>
												<li class="<?php echo $internalFilter ? 'active' : ''; ?>" data-status="internal">
													<a href="?internal=1<?php echo !$showMyTasks ? '&all_tasks=1' : ''; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?><?php echo !empty($sortOrder) ? '&sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
														<span>
														   <?php echo $lang['Internal Task']; ?>  <i class="dots text-secondary object-align-right"></i>
														</span>
													</a>
												</li>
												<li class="<?php echo $statusFilter === 'recurring' ? 'active' : ''; ?>" data-status="recurring">
													<a href="?status=recurring<?php echo !$showMyTasks ? '&all_tasks=1' : ''; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?><?php echo !empty($sortOrder) ? '&sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
														<span>
															<?php echo htmlspecialchars($lang['Recurring task'] ?? 'Recurring task', ENT_QUOTES, 'UTF-8'); ?>
															<?php echo ts_icon('refresh', 'w-2 object-align-right'); ?>
														</span>
													</a>
												</li>
												<?php foreach ($statusTypes as $status): ?>
												<li class="<?php echo $statusFilter === $status ? 'active' : ''; ?>" data-status="<?php echo $status; ?>">
													<a href="?status=<?php echo $status; ?><?php echo !$showMyTasks ? '&all_tasks=1' : ''; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?><?php echo !empty($sortOrder) ? '&sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
														<span>
															<?php echo $statusLabels[$status]; ?>   <i class="dots <?php echo $statuses[$status]['icon']; ?> object-align-right"></i>

														</span>
													</a>
												</li>
												<?php endforeach; ?>
												<hr>
												<li><b><?php echo $lang['Sort By'] ?? 'Sort By'; ?> </b></li>
												<li class="<?php echo $sortOrder === 'asc' ? 'active' : ''; ?>" data-status="ascending">
													<a href="?<?php echo $statusFilter ? 'status=' . htmlspecialchars($statusFilter) : ''; ?><?php echo $internalFilter ? ($statusFilter ? '&' : '') . 'internal=1' : ''; ?><?php echo !$showMyTasks ? ($statusFilter || $internalFilter ? '&' : '') . 'all_tasks=1' : ''; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>&sort_order=asc">
														<span>
															<?php echo ts_icon('arrow-up'); ?>
															<?php echo $lang['Ascending'] ?? 'Ascending'; ?>
														</span>
													</a>
												</li>
												<li class="<?php echo $sortOrder === 'desc' ? 'active' : ''; ?>" data-status="descending">
													<a href="?<?php echo $statusFilter ? 'status=' . htmlspecialchars($statusFilter) : ''; ?><?php echo $internalFilter ? ($statusFilter ? '&' : '') . 'internal=1' : ''; ?><?php echo !$showMyTasks ? ($statusFilter || $internalFilter ? '&' : '') . 'all_tasks=1' : ''; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?>&sort_order=desc">
														<span>
															<?php echo ts_icon('chevron-down'); ?>
															<?php echo $lang['Descending'] ?? 'Descending'; ?>
														</span>
													</a>
												</li>
												<hr>
												<li><b><?php echo $lang['By Status']; ?> </b></li>
												<li class="<?php echo $statusFilter === 'overdue' ? 'active' : ''; ?>" data-status="overdue">
													<a href="?status=overdue<?php echo !$showMyTasks ? '&all_tasks=1' : ''; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?><?php echo !empty($sortOrder) ? '&sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
														<span>
															<?php echo $lang['Overdue']; ?> 
														</span>
													</a>
												</li>
												<li class="<?php echo $statusFilter === 'due_today' ? 'active' : ''; ?>" data-status="due_today">
													<a href="?status=due_today<?php echo !$showMyTasks ? '&all_tasks=1' : ''; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?><?php echo !empty($sortOrder) ? '&sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
														<span>
															<?php echo $lang['Due Today']; ?> 
														</span>
													</a>
												</li>
												<li class="<?php echo $statusFilter === 'due_soon' ? 'active' : ''; ?>" data-status="due_soon">
													<a href="?status=due_soon<?php echo !$showMyTasks ? '&all_tasks=1' : ''; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?><?php echo !empty($sortOrder) ? '&sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
														<span>
															<?php echo $lang['Due Soon']; ?> 
														</span>
													</a>
												</li>
												<li class="<?php echo $statusFilter === 'task_pro' ? 'active' : ''; ?>" data-status="task_pro">
													<a href="?status=task_pro<?php echo !$showMyTasks ? '&all_tasks=1' : ''; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?><?php echo !empty($sortOrder) ? '&sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
														<span>
															<?php echo $lang['Task Pro']; ?> 
														</span>
													</a>
												</li>
												<li class="<?php echo $statusFilter === 'on_time' ? 'active' : ''; ?>" data-status="on_time">
													<a href="?status=on_time<?php echo !$showMyTasks ? '&all_tasks=1' : ''; ?><?php echo $startDateFilter ? '&start_date=' . htmlspecialchars($startDateFilter) : ''; ?><?php echo $endDateFilter ? '&end_date=' . htmlspecialchars($endDateFilter) : ''; ?><?php echo !empty($sortOrder) ? '&sort_order=' . htmlspecialchars($sortOrder) : ''; ?>">
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
								<div class="d-none d-md-block kanban-header-add-btn">
									  <?php if($projectId > 0): ?>
										<a href="add_task?projectId=<?= $projectId ?>&source=kanban" class="primary-btn">
										  <?php echo $lang['Add New Task']; ?> <?php echo ts_icon('plus', 'w-2'); ?></a>
									<?php else: ?>
										<a href="add_task?source=kanban" class="primary-btn"><?php echo $lang['Add New Task']; ?> <?php echo ts_icon('plus', 'w-2'); ?></a>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							</div>
                    </div>
                </div>
                <div class="clearfix"></div>
                <div class="row">
                    <div class="container-fluid pd-0">
                        <div class="row">
                            <div class="col-12">
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
                                
                                <?php if(isset($message)) echo $message; ?>
                                <div class="board-wrap">
                                    <div class="board d-flex full-width">
                                        <?php foreach ($statuses as $key => $cfg): 
                                            $columnTasks = isset($columns[$key]) && is_array($columns[$key]) ? $columns[$key] : [];
                                        ?>
                                            <div class="board-column " 
                                                 id="<?= $key ?>-column" 
                                                 <?php if ($can_change_status): ?>
                                                     ondrop="drop(event)" 
                                                     ondragover="allowDrop(event)" 
                                                     ondragleave="dragLeave(event)"
                                                 <?php endif; ?>>
                                                <h2 class="h6 d-flex align-items-center">
                                                    <i class="dots <?= $cfg['icon'] ?> me-1"></i>
                                                    <span class="column-title" data-column="<?= $key ?>"><?= htmlspecialchars($cfg['label']) ?></span>
                                                    <button class="btn rename-btn" onclick="startColumnRename('<?= $key ?>')">
                                                        <?php echo ts_icon('edit', 'w-4'); ?>
                                                    </button>
                                                    <?php if(strpos($key, 'custom_') === 0): ?>
                                                        <button class="btn remove-btn" data-key="<?= $key ?>" title="Delete Column">
                                                          <?php echo ts_icon('delete', 'w-4'); ?>
                                                        </button>
                                                    <?php endif; ?>
                                                    <span class="ms-2 badge"><?= isset($originalColumnCounts[$key]) ? $originalColumnCounts[$key] : count($columnTasks) ?></span>
                                                </h2>

                                                <?php if(empty($columnTasks)): ?>
                                                    <div class="alert alert-light text-center">
                                                       <?php echo $lang['No tasks in this column']; ?>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if(!empty($columnTasks)): ?>
                                                    <?php foreach ($columnTasks as $task): ?>
                                                        <?php 
                                                        try {
                                                            // Safety checks
                                                            $taskId = isset($task->id) ? $task->id : 0;
                                                            $projectId = isset($task->project_id) ? $task->project_id : 0;
                                                            $taskTitle = isset($task->title) ? htmlspecialchars($task->title) : 'Untitled Task';
                                                        ?>
                                                            <div class="card mb-2 task-card" 
                                                                draggable="<?= $can_change_status ? 'true' : 'false' ?>"
                                                                data-draggable="<?= $can_change_status ? 'true' : 'false' ?>"
                                                                data-id="<?= $taskId ?>"
                                                                data-project-id="<?= $projectId ?>"
                                                                <?php if ($can_change_status): ?>
                                                                    ondragstart="drag(event)"
                                                                    ondragend="dragEnd(event)"
                                                                <?php endif; ?>>
                                                                <div class="card-body p-2">
                                                                    <div class="bulk-delete-checkbox" style="display: none;">
                                                                        <div class="form-check">
                                                                            <input class="form-check-input task-checkbox" 
                                                                                   type="checkbox" 
                                                                                   value="<?= $taskId ?>" 
                                                                                   id="task_<?= $taskId ?>">
                                                                            <label class="form-check-label" for="task_<?= $taskId ?>">
                                                                                Select task
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="d-flex justify-content-between align-items-start">
                                                                        <div>
																		<div class="project-badge d-flex align-items-center flex-wrap col-gap-5 mb-2">
                                                                                <?php
                                                                                $kanbanTimerActiveLbl = isset($lang['kanban_timer_active']) ? $lang['kanban_timer_active'] : 'Timer Active';
                                                                                $kanbanTimerPausedLbl = isset($lang['kanban_timer_paused']) ? $lang['kanban_timer_paused'] : 'Timer Paused';
                                                                                $kanbanTimerSt = isset($kanbanTimerActiveByTask[$taskId]) ? $kanbanTimerActiveByTask[$taskId] : '';
                                                                                $kanbanTimerVisible = $kanbanTimerSt !== '';
                                                                                $kanbanTimerIsRunning = $kanbanTimerSt === 'running';
                                                                                $kanbanTimerBadgeLbl = $kanbanTimerIsRunning ? $kanbanTimerActiveLbl : ($kanbanTimerVisible ? $kanbanTimerPausedLbl : $kanbanTimerActiveLbl);
                                                                                $kanbanTimerBadgeModifier = $kanbanTimerVisible ? ($kanbanTimerIsRunning ? ' task-card-timer-badge--running' : ' task-card-timer-badge--paused') : '';
                                                                                ?>
                                                                                <span class="internal-badge task-card-timer-badge primary-border<?php echo $kanbanTimerBadgeModifier; ?><?php echo $kanbanTimerVisible ? '' : ' d-none'; ?>" title="<?php echo htmlspecialchars($kanbanTimerBadgeLbl, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($kanbanTimerBadgeLbl, ENT_QUOTES, 'UTF-8'); ?>">
                                                                                    <span class="task-card-timer-badge-text"><?php echo htmlspecialchars($kanbanTimerBadgeLbl, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                                </span>
                                                                                <?php if ($projectId == 0): ?>
                                                                                    <span class="internal-badge">
                                                                                        <?php echo $lang['Internal Task']; ?>
                                                                                    </span>
                                                                                <?php endif; ?>
                                                                                <?php
                                                                                $isKanbanRecurring = !empty($kanbanRecurringTaskIds[$taskId])
                                                                                    || (isset($task->recurrence_id) && (int) $task->recurrence_id > 0);
                                                                                if ($isKanbanRecurring):
                                                                                    $recurringTip = $lang['Recurring task'] ?? ($lang['Set repeats'] ?? 'Recurring task');
                                                                                ?>
                                                                                    <span class="internal-badge task-card-recurring-badge" title="<?php echo htmlspecialchars($recurringTip, ENT_QUOTES, 'UTF-8'); ?>" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="<?php echo htmlspecialchars($recurringTip, ENT_QUOTES, 'UTF-8'); ?>"><?php echo ts_icon('refresh', 'w-2 task-card-recurring-ico'); ?></span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                            <h5 class="card-title mb-1"><?= $taskTitle ?></h5>
                                                                            <?php if ($projectId > 0 && isset($task->project_title)): ?>
                                                                            <div class="project-badge mb-2">
                                                                                <span class="badge bg-primary">
                                                                                 <?= htmlspecialchars($task->project_title ?? 'Unknown Project') ?>
                                                                                </span>
                                                                            </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <div class="dropdown">
                                                                            <button class="btn-dots" type="button" data-bs-toggle="dropdown">
                                                                                <?php echo ts_icon('dots-vertical', 'w-6'); ?>
                                                                            </button>
                                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                                <li>
                                                                                    <a class="dropdown-item" href="#" onclick="openTaskSidebar(<?= $taskId ?>); return false;">
                                                                                        <?php echo ts_icon('eye', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                        <?php echo $lang['View Task']; ?>
                                                                                    </a>
                                                                                </li>
                                                                                <?php if (has_permission('task_edit')) { ?>
                                                                                <li>
                                                                                    <a class="dropdown-item" href="edit_task?id=<?= $taskId ?>">
                                                                                        <?php echo ts_icon('edit', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                        <?php echo $lang['Edit Task']; ?>
                                                                                    </a>
                                                                                </li>
                                                                                <?php } ?>
                                                                                <?php if (has_permission('task_duplicate')) { ?>
                                                                                <li>
                                                                                    <a class="dropdown-item" href="../includes/clone-task.php?id=<?= $taskId ?>&redirect=<?= urlencode($_SERVER['REQUEST_URI']) ?>">
                                                                                        <?php echo ts_icon('duplicate', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                        <?php echo $lang['Clone Task']; ?>
                                                                                    </a>
                                                                                </li>
                                                                                <?php } ?>
                                                                                <?php if ($projectId > 0) { ?>
                                                                                <li>
                                                                                    <a class="dropdown-item" href="overview?projectId=<?= $projectId ?>">
                                                                                        <?php echo ts_icon('info', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                        <?php echo $lang['View Project']; ?>
                                                                                    </a>
                                                                                </li>
                                                                                <?php } ?>
                                                                                <?php if (has_permission('task_edit')) { ?>
                                                                                <?php if ($showArchive): ?>
                                                                                <li>
                                                                                    <a class="dropdown-item" href="#" onclick="unarchiveTask(<?= $taskId ?>); return false;">
                                                                                        <?php echo ts_icon('restore', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                        <?php echo $lang['Unarchive Task'] ?? 'Unarchive Task'; ?>
                                                                                    </a>
                                                                                </li>
                                                                                <?php else: ?>
                                                                                <li>
                                                                                    <a class="dropdown-item" href="#" onclick="archiveTask(<?= $taskId ?>); return false;">
                                                                                        <?php echo ts_icon('archive', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                        <?php echo $lang['Archive Task'] ?? 'Archive Task'; ?>
                                                                                    </a>
                                                                                </li>
                                                                                <?php endif; ?>
                                                                                <?php } ?>
                                                                                <?php if (has_permission('task_delete')) { ?>
                                                                                <li>
                                                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteTask(<?= $taskId ?>)">
                                                                                        <?php echo ts_icon('delete', 'me-2 tasksession-timer-log-menu-ico'); ?>
                                                                                        <?php echo $lang['Delete Task']; ?>
                                                                                    </a>
                                                                                </li>
                                                                                <?php } ?>
                                                                            </ul>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <?php if (isset($task->description) && !empty($task->description)): ?>
                                                                        <div class="task-description mb-2"><?= first_n_words($task->description, 15) ?></div>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if (!empty($task->start_date) || !empty($task->due_date)): ?>
                                                                        <div class="task-date d-flex">
                                                                            <?php if (!empty($task->start_date)): ?>
                                                                                <div class="start-date">
                                                                                    <b><?php echo $lang['Start']; ?>:</b> <?= date('M j, Y', strtotime($task->start_date)) ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                            
                                                                            <?php if (!empty($task->due_date)): ?>
                                                                                <?php
                                                                                    $dueDate = strtotime($task->due_date);
                                                                                    $today = strtotime('today');
                                                                                    $isOverdue = $dueDate < $today;
                                                                                    $isDueToday = $dueDate == $today;
                                                                                ?>
                                                                                <div class="<?= $isOverdue ? 'text-danger' : '' ?>">
                                                                                    <div class="overdue <?= $isOverdue ? 'text-danger' : ($isDueToday ? 'color-review' : '') ?>"></div> 
                                                                                    <b><?php echo $lang['Due']; ?>:</b> <?= date('M j, Y', strtotime($task->due_date)) ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    
                                                                    <?php if (!empty($task->assigned_to)): ?>
                                                                        <div class="team-col d-flex align-items-baseline">
                                                                            <div class="d-flex avatar-head">
                                                                                <?php
                                                                                $assignedStaffIds = explode(',', $task->assigned_to);
                                                                                foreach($assignedStaffIds as $staffId):
                                                                                    if(!empty($staffId) && isset($projectStaff[$staffId])):
                                                                                        // Get staff profile image
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
                                        <div class="board-column add-column-col">
										<h2 class="h6 d-flex align-items-center">
                                                    <span class="column-title"  id="addColumnBtn"><?php echo $lang['+ Add New Column']; ?></span>
                                                </h2>
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
</div>
<!-- Column Rename Modal -->
                                <div class="modal fade" id="columnRenameModal" tabindex="-1">
                                    <div class="modal-dialog modal-sm">
                                        <form id="columnRenameForm" 
                                              method="post" 
                                              class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="card-title"><?php echo $lang['Rename Column']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><?php echo ts_icon('close'); ?></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" id="column-key" name="column_key" value="">
                                                <input type="hidden" name="project_id" value="<?= $projectId ?>">
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
                                         
                                                <button type="submit" class="primary-btn">
                                                    <?php echo $lang['Save']; ?>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
							<div class="modal fade" id="addColumnModal" tabindex="-1">
							  <div class="modal-dialog modal-sm">
								<form id="addColumnForm" method="POST" class="modal-content">
								  <div class="modal-header">
									<h5 class="card-title"><?php echo $lang['Add New Column']; ?></h5>
									<button type="button" class="btn-close" data-bs-dismiss="modal">                                                            <?php echo ts_icon('close'); ?></button>
								  </div>
								  <div class="modal-body">
									<div class="mb-3">
									  <label class="form-label">  
									  <?php echo $lang['Column Name']; ?>
									</label>
									  <input type="text" name="column_name" class="form-control" placeholder="<?php echo $lang['Column Name'] ?? 'Column Name'; ?>" required>
									</div>
								  </div>
								  <div class="modal-footer">
									<button type="submit" class="primary-btn"> <?php echo $lang['Create']; ?>
							</button>
								  </div>
								</form>
							  </div>
							</div>
							<div id="kanban-lock-btn" title="Lock/Unlock Kanban">
							  <svg id="kanban-lock-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4">
								<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />';
							  </svg>
							</div>
<script>
window.kanbanIsArchiveView = <?php echo $showArchive ? 'true' : 'false'; ?>;
window.kanbanCanDelete = <?php echo $kanbanCanDelete ? 'true' : 'false'; ?>;
window.kanbanCanArchive = <?php echo $kanbanCanArchive ? 'true' : 'false'; ?>;
window.langSelectAll = <?php echo json_encode($lang['Select all'] ?? 'Select all'); ?>;
window.langDeselectAll = <?php echo json_encode($lang['Deselect all'] ?? 'Deselect all'); ?>;
window.langArchive = <?php echo json_encode($lang['Archive'] ?? 'Archive'); ?>;
window.langUnarchive = <?php echo json_encode($lang['Unarchive Task'] ?? 'Unarchive'); ?>;
window.langBulkDeleteConfirm = <?php echo json_encode($lang['Bulk delete confirm'] ?? 'Are you sure you want to delete %d task(s)? This action cannot be undone.'); ?>;
window.langBulkDeleteNone = <?php echo json_encode($lang['Bulk delete none'] ?? 'Please select at least one task to delete.'); ?>;
window.langBulkArchiveConfirm = <?php echo json_encode($lang['Bulk archive confirm'] ?? 'Are you sure you want to archive %d selected task(s)?'); ?>;
window.langBulkUnarchiveConfirm = <?php echo json_encode($lang['Bulk unarchive confirm'] ?? 'Restore %d selected task(s) to the active board?'); ?>;
window.langBulkArchiveNone = <?php echo json_encode($lang['Bulk archive none'] ?? 'Please select at least one task.'); ?>;
window.langBulkArchiveFail = <?php echo json_encode($lang['Bulk archive fail'] ?? 'Failed to update tasks'); ?>;
window.langBulkPartialFail = <?php echo json_encode($lang['Bulk partial fail'] ?? 'Some tasks could not be updated.'); ?>;
</script>
<script src="../assets/js/task-date-range.js"></script>
<script src="../assets/js/task-bulk-delete.js"></script>
<script src="../assets/js/kanban-sticky-headers.js?v=4"></script>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include("../templates/main-footer.php"); ?>

