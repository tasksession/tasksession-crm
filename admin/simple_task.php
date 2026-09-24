<?php
// Ensure no whitespace before this opening PHP tag
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Start output buffering to capture any unexpected output
ob_start();

// Include necessary files
try {
    require_once("../includes/lib-initialize.php");
    require_once("../includes/task.php");
} catch (Exception $e) {
    // Clean buffer and return error if includes fail
    ob_end_clean();
    echo json_encode(['error' => 'Failed to include required files: ' . $e->getMessage()]);
    exit;
}

// Get task ID from request
$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Authentication check
if(!($session->isLoggedIn())){
    ob_end_clean();
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

if($_SESSION['accountStatus'] != 1){
    ob_end_clean();
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

// Validate task ID
if($taskId == 0) {
    ob_end_clean();
    echo json_encode(['error' => 'Invalid task ID']);
    exit;
}

try {
    // Load task data
    $task = Task::findById($taskId);

    if(!$task) {
        ob_end_clean();
        echo json_encode(['error' => 'Task not found']);
        exit;
    }

    // Try to find the user who created the task directly from database
    $creatorId = null;
    $createdBy = null;
    try {
        // First check if the user_id or created_by column exists in the tasks table
        $checkColumns = $database->query("SHOW COLUMNS FROM tasks LIKE 'user_id'");
        $hasUserIdColumn = $database->numRows($checkColumns) > 0;
        
        if(!$hasUserIdColumn) {
            $checkColumns = $database->query("SHOW COLUMNS FROM tasks LIKE 'created_by'");
            $hasCreatedByColumn = $database->numRows($checkColumns) > 0;
        }
        
        // If we have a user_id or created_by column, query it directly
        if($hasUserIdColumn) {
            $userQuery = $database->query("SELECT user_id FROM tasks WHERE id = " . (int)$taskId);
            if($userRow = $database->fetchArray($userQuery)) {
                $creatorId = $userRow['user_id'];
            }
        } else if($hasCreatedByColumn) {
            $userQuery = $database->query("SELECT created_by FROM tasks WHERE id = " . (int)$taskId);
            if($userRow = $database->fetchArray($userQuery)) {
                $creatorId = $userRow['created_by'];
            }
        } else {
            // No specific creator column found, use the current user as fallback
            $creatorId = $session->userId;
        }
    } catch (Exception $e) {
        // error_log("Error checking task creator: " . $e->getMessage());
        // Fallback to current user
        $creatorId = $session->userId;
    }

    // Status information
    $statusLabels = [
        'todo' => 'To Do',
        'inprogress' => 'In Progress',
        'review' => 'In Review',
        'done' => 'Completed'
    ];

    $statusColors = [
        'todo' => 'danger',
        'inprogress' => 'primary',
        'review' => 'warning',
        'done' => 'success'
    ];

    // Directly query the database for project name
    $projectName = "Internal Task";
    if (!empty($task->project_id) && $task->project_id > 0) {
        global $database;
        $projectId = (int)$task->project_id;
        try {
            $query = $database->query("SELECT project_title FROM projects WHERE p_id = $projectId");
            if ($row = $database->fetchArray($query)) {
                $projectName = $row['project_title'];
            } else {
                $projectName = "Project #" . $projectId;
            }
        } catch (Exception $e) {
            $projectName = "Project #" . $projectId;
            // error_log("Error querying project name: " . $e->getMessage());
        }
    }

    // Get assigned staff information
    $assignedStaff = [];
    if(!empty($task->assigned_to)) {
        $assignedIds = explode(',', $task->assigned_to);
        foreach($assignedIds as $staffId) {
            if(!empty($staffId)) {
                try {
                    $staff = User::findById($staffId);
                    if($staff) {
                        // Get profile image
                        $image = $url . 'assets/images/upload-img.jpg';
                        try {
                            $query = $database->query("SELECT filename FROM profile_pics WHERE fkUserId = " . (int)$staffId);
                            if ($row = $database->fetchArray($query)) {
                                if (!empty($row['filename'])) {
                                    $image = getProfilePicUrl($row['filename'], 40, 40);
                                }
                            }
                        } catch (Exception $e) {
                            // error_log("Error getting profile image: " . $e->getMessage());
                        }
                        
                        $assignedStaff[] = [
                            'id' => $staff->id,
                            'name' => $staff->firstName,
                            'title' => $staff->title ?? '',
                            'image' => $image
                        ];
                    }
                } catch (Exception $e) {
                    // error_log("Error getting staff info: " . $e->getMessage());
                }
            }
        }
    }

    // Get the task creator
    $creatorId = null;
    
    // First try to get creator from task's created_by field if it exists
    if(property_exists($task, 'created_by') && !empty($task->created_by)) {
        $creatorId = $task->created_by;
    } 
    // If no created_by field, try to use the owner field if it exists
    else if(property_exists($task, 'owner') && !empty($task->owner)) {
        $creatorId = $task->owner;
    }
    // If no created_by or owner, try to use the user_id field if it exists
    else if(property_exists($task, 'user_id') && !empty($task->user_id)) {
        $creatorId = $task->user_id;
    }
    // If still no creator available, default to the current user
    else {
        // Use the current logged in user as fallback
        $creatorId = $session->userId;
    }
    
    $creator = null;
    if($creatorId) {
        try {
            $creatorUser = User::findById($creatorId);
            if($creatorUser) {
                // Get profile image
                $creatorImage = $url . 'assets/images/upload-img.jpg';
                try {
                    $query = $database->query("SELECT filename FROM profile_pics WHERE fkUserId = " . (int)$creatorId);
                    if ($row = $database->fetchArray($query)) {
                        if (!empty($row['filename'])) {
                            $creatorImage = getProfilePicUrl($row['filename'], 40, 40);
                        }
                    }
                } catch (Exception $e) {
                    // error_log("Error getting creator profile image: " . $e->getMessage());
                }
                
                $creator = [
                    'id' => $creatorUser->id,
                    'name' => $creatorUser->firstName,
                    'title' => $creatorUser->title ?? '',
                    'image' => $creatorImage
                ];
            }
        } catch (Exception $e) {
            // error_log("Error loading creator: " . $e->getMessage());
        }
    }

    // Prepare response data
    $response = [
        'task' => [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'status' => $task->status,
            'statusLabel' => $statusLabels[$task->status] ?? 'Unknown',
            'statusColor' => $statusColors[$task->status] ?? 'secondary',
            'start_date' => $task->start_date ? date('F j, Y', strtotime($task->start_date)) : null,
            'due_date' => $task->due_date ? date('F j, Y', strtotime($task->due_date)) : null,
            'created_at' => date('F j, Y', strtotime($task->created_at)),
            'is_overdue' => $task->due_date && (strtotime($task->due_date) < time() && $task->status != 'done')
        ],
        'project' => [
            'id' => $task->project_id ?? 0,
            'title' => $projectName
        ],
        'assigned_staff' => $assignedStaff,
        'creator' => $creator
    ];

    // Discard any buffered output and send clean JSON
    ob_end_clean();
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log the error and return a clean error response
    // error_log("Error processing task details: " . $e->getMessage());
    ob_end_clean();
    echo json_encode([
        'error' => 'Failed to process task details',
        'message' => $e->getMessage()
    ]);
}
?> 