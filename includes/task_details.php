<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : includes/task_details.php
   Purpose : Provides a JSON API endpoint for retrieving detailed information about a specific task.
 ================================================================================
*/

// JSON endpoint — rely on session authentication and task authorization (below).

// Ensure we only return clean JSON
header('Content-Type: application/json');

// Start output buffering immediately to catch ANY output
ob_start();

// Disable error display but keep logging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Load dependencies
require_once("lib-initialize.php");
require_once("task.php");
require_once("permissions.php");
require_once("projects.php");

// Helper function to get profile image (uses batch map when provided)
function getProfileImage($userId, array $picMap = []) {
    global $database, $url;
    $userId = (int)$userId;
    if ($userId <= 0) {
        return $url . 'assets/images/upload-img.jpg';
    }
    if (isset($picMap[$userId])) {
        return $picMap[$userId];
    }
    try {
        $query = $database->query("SELECT filename FROM profile_pics WHERE fkUserId = '" . $userId . "'");
        if($row = $database->fetchArray($query)) {
            $image = $row['filename'] ?? '';
            return $image ? getProfilePicUrl($image, 40, 40) : $url . 'assets/images/upload-img.jpg';
        }
    } catch (Exception $e) {
        // Error loading profile image
    }
    return $url . 'assets/images/upload-img.jpg';
}

// Authentication check
if(!($session->isLoggedIn())){
    $output = ob_get_clean(); // Discard any output
    echo json_encode(['status' => 'error', 'error' => 'Authentication required']);
    exit;
}

// Get task ID from request
$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($taskId <= 0) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid task ID']);
    exit;
}

// Load task
$task = Task::findById($taskId);
if (!$task) {
    echo json_encode(['status' => 'error', 'error' => 'Task not found']);
    exit;
}

// Check user type and permissions
$userType = $_SESSION['accountStatus'];
$userId = $_SESSION['userId'];
ensure_user_permissions($connect);

// Check permissions based on user type
$canViewTask = false;

switch($userType) {
    case 1: // Admin
        $canViewTask = true;
        break;
    case 3: // Staff
        $canViewTask = staff_can_view_task($task, $userId);
        break;
    case 2: // Client
        $canViewTask = $task->isClientAssociated($userId);
        break;
    default:
        $canViewTask = false;
}

if (!$canViewTask) {
    echo json_encode(['status' => 'error', 'error' => 'Insufficient permissions']);
    exit;
}

// Format dates properly
$startDate = '';
if (!empty($task->start_date) && $task->start_date != '0000-00-00') {
    $startDate = date('M j, Y', strtotime($task->start_date));
}

$dueDate = '';
if (!empty($task->due_date) && $task->due_date != '0000-00-00') {
    $dueDate = date('M j, Y', strtotime($task->due_date));
}

// Get task creator info
$creatorInfo = null;
$assignedIds = [];
if (!empty($task->assigned_to)) {
    foreach (explode(',', $task->assigned_to) as $staffId) {
        $staffId = (int)trim($staffId);
        if ($staffId > 0) {
            $assignedIds[] = $staffId;
        }
    }
}
$userIdsToLoad = $assignedIds;
if (!empty($task->creator_id)) {
    $userIdsToLoad[] = (int)$task->creator_id;
}
$userIdsToLoad = array_values(array_unique(array_filter($userIdsToLoad)));
$userMap = [];
if (!empty($userIdsToLoad)) {
    $idsSql = implode(',', $userIdsToLoad);
    $loadedUsers = User::findBySql("SELECT * FROM users WHERE id IN ($idsSql)");
    if (is_array($loadedUsers)) {
        foreach ($loadedUsers as $loadedUser) {
            $userMap[(int)$loadedUser->id] = $loadedUser;
        }
    }
}
$picMap = crm_batch_profile_pic_urls($userIdsToLoad, 40, 40);

if (!empty($task->creator_id)) {
    $creator = $userMap[(int)$task->creator_id] ?? User::findById($task->creator_id);
    if ($creator) {
        $creatorInfo = [
            'id' => $creator->id,
            'name' => $creator->firstName,
            'image' => getProfileImage($creator->id, $picMap)
        ];
    }
}

// Get project info if task is associated with a project
$projectInfo = null;
if (!empty($task->project_id)) {
    $project = projects::findByProjectId($task->project_id);
    if ($project) {
        $projectInfo = [
            'id' => $project->p_id,
            'title' => $project->project_title
        ];
    }
}

// Get assigned staff with profile images
$assignedStaff = [];
foreach ($assignedIds as $staffId) {
    $staff = $userMap[$staffId] ?? null;
    if ($staff) {
        $assignedStaff[] = [
            'id' => $staff->id,
            'name' => $staff->firstName,
            'image' => getProfileImage($staff->id, $picMap),
            'initials' => strtoupper(substr($staff->firstName, 0, 1))
        ];
    }
}

// Calculate badge for task status (same as kanban logic)
$badgeHtml = '';
if (!empty($task->due_date) && $task->due_date != '0000-00-00') {
    $dueDateTimestamp = strtotime($task->due_date);
    $today = strtotime('today');
    
    if ($task->status == 'done' && !empty($task->completed_at)) {
        // For completed tasks, use completion date to determine badge
        $completedDate = strtotime($task->completed_at);
        $completedDateOnly = strtotime(date('Y-m-d', $completedDate));
        $dueDateOnly = strtotime(date('Y-m-d', $dueDateTimestamp));
        
        if ($completedDateOnly < $dueDateOnly) {
            // Completed before due date = Task Pro
            $badgeHtml = '<span class="badge color-done review done-bg-op">Task Pro</span>';
        } elseif ($completedDateOnly == $dueDateOnly) {
            // Completed on due date = On Time
            $badgeHtml = '<span class="badge color-review review-bg-op">On Time</span>';
        } else {
            // Completed after due date = Overdue
            $badgeHtml = '<span class="badge">Overdue</span>';
        }
    } else {
        // For non-completed tasks, use current date logic
        $isOverdue = $dueDateTimestamp < $today;
        $isDueToday = $dueDateTimestamp == $today;
        
        if ($isOverdue) {
            $badgeHtml = '<span class="badge">Overdue</span>';
        } elseif ($isDueToday) {
            $badgeHtml = '<span class="badge color-review review-bg-op">Due Today</span>';
        }
    }
}

// Prepare response data
$response = [
    'status' => 'ok',
    'task' => [
        'id' => $task->id,
        'title' => $task->title,
        'description' => $task->description ?? '',
        'status' => $task->status ?? 'todo',
        'start_date' => $startDate,
        'due_date' => $dueDate,
        'created_at' => $task->created_at ?? '',
        'creator_id' => $task->creator_id ?? '',
        'project_id' => $task->project_id ?? '',
        'assigned_to' => $task->assigned_to ?? '',
        'badge_html' => $badgeHtml,
        'estimated_time_seconds' => isset($task->estimated_time_seconds) && $task->estimated_time_seconds !== null && $task->estimated_time_seconds !== ''
            ? (int)$task->estimated_time_seconds
            : null,
    ],
    'project' => $projectInfo,
    'creator' => $creatorInfo,
    'assigned_staff' => $assignedStaff
];

// Clear any output before sending JSON
ob_get_clean();

// Send clean JSON response
echo json_encode($response);
exit; 