<?php
/*
 * Task Mention Users - Get list of users who can be mentioned in a task chat
 */

// Start output buffering FIRST before any includes
ob_start();

// Suppress any warnings/notices that might output text
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    require_once('../includes/loader.php');
    require_once('../includes/initialize.php');
    require_once('../includes/task.php');
    require_once('../includes/projects.php');
    require_once('../includes/user.php');
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load system files: ' . $e->getMessage()
    ]);
    exit;
} catch (Error $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load system files: ' . $e->getMessage()
    ]);
    exit;
}

// Set headers after includes are loaded
header('Content-Type: application/json');

// Security check - must be logged in
if (!isset($session) || !($session->isLoggedIn())) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'User not logged in'
    ]);
    exit;
}

// Get task ID
$task_id = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
if (!$task_id) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing task ID'
    ]);
    exit;
}

// Get task details - wrap in try-catch
try {
    $task = Task::findById($task_id);
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Error loading task: ' . $e->getMessage()
    ]);
    exit;
}

if (!$task) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Task not found'
    ]);
    exit;
}

// Get mentionable users: task creator, assignees, main client, project clients
$users = array();
$user_ids = array();

// Add task creator
if (!empty($task->creator_id)) {
    $user_ids[] = (int)$task->creator_id;
}

// Add assigned staff
if (!empty($task->assigned_to)) {
    $assigned_ids = array_map('trim', explode(',', $task->assigned_to));
    foreach ($assigned_ids as $assigned_id) {
        if (!empty($assigned_id) && is_numeric($assigned_id)) {
            $user_ids[] = (int)$assigned_id;
        }
    }
}

// Get project details to add clients
if (!empty($task->project_id)) {
    $project = Projects::findByProjectId($task->project_id);
    
    if ($project) {
        // Add project clients
        if (!empty($project->c_ids)) {
            $client_ids = array_map('trim', explode(',', $project->c_ids));
            foreach ($client_ids as $client_id) {
                if (!empty($client_id) && is_numeric($client_id)) {
                    $user_ids[] = (int)$client_id;
                }
            }
        }
        
        // Add main client
        if (!empty($project->main_client_id)) {
            $user_ids[] = (int)$project->main_client_id;
        }
    }
}

// Remove duplicates
$user_ids = array_unique($user_ids);

// Include profile picture helper once (not in loop)
require_once('../includes/profilePicture.php');

// Get user details
foreach ($user_ids as $uid) {
    try {
        $user = User::findById($uid);
    } catch (Exception $e) {
        // Skip this user if there's an error
        continue;
    }
    
    if (!$user) continue;
    
    // Use only first name (eliminate last name to avoid space and repetition issues)
    // Extract ONLY the first word - split by space and take first part
    $firstName = !empty($user->firstName) ? trim($user->firstName) : 'User';
    // Split by any whitespace and take only the first word (before first space)
    $nameParts = preg_split('/\s+/', $firstName, 2); // Limit to 2 parts to get first word only
    $displayName = !empty($nameParts[0]) ? trim($nameParts[0]) : 'User';
    
    // Get avatar
    $profilePic = '';
    $hasProfilePic = false;
    $profilePicSql = "SELECT filename FROM profile_pics WHERE fkUserId = " . (int)$uid . " LIMIT 1";
    $profilePicResult = $database->query($profilePicSql);
    
    if ($profilePicResult && $database->numRows($profilePicResult) > 0) {
        $profilePicRow = $database->fetchArray($profilePicResult);
        $profilePic = $profilePicRow['filename'];
        $hasProfilePic = true;
    }
    
    // Build avatar URL
    $avatarUrl = '';
    if ($hasProfilePic && $profilePic) {
        $avatarUrl = $url . 'includes/secure_file_handler.php?src=' . urlencode($url . 'uploads/profile-pics/' . $profilePic) . '&thumb=1&width=50&height=50&crop=1';
    }
    
    // Generate initials from first name only (first letter of first word)
    $initials = !empty($displayName) ? strtoupper(substr($displayName, 0, 1)) : 'U';
    
    $users[] = array(
        'id' => (int)$uid,
        'name' => htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'), // Only first name
        'firstName' => htmlspecialchars($user->firstName, ENT_QUOTES, 'UTF-8'),
        'avatar' => $avatarUrl,
        'hasAvatar' => $hasProfilePic,
        'initials' => $initials,
        'colorIndex' => ($uid % 8) + 1,
        'accountStatus' => (int)$user->accountStatus
    );
}

// Sort by name
usort($users, function($a, $b) {
    return strcmp(strtolower($a['name']), strtolower($b['name']));
});

// Clean any output that might have been generated
// Get current buffer contents to check for errors
$buffer_content = ob_get_contents();
ob_clean();

// Check if there's any non-JSON output
if (!empty($buffer_content) && !preg_match('/^\s*\{.*\}\s*$/s', $buffer_content)) {
    // There's unwanted output - log it but don't include in response
    error_log('[task_mention_users] Buffer contained non-JSON output: ' . substr($buffer_content, 0, 200));
}

// Ensure we output only JSON
$response = [
    'status' => 'success',
    'users' => $users
];

// Final cleanup - remove all output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Set content type header again to be sure
header('Content-Type: application/json; charset=utf-8');

echo json_encode($response);
exit;

