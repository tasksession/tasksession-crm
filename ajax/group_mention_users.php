<?php
/*
 * Group Chat Mention Users - Get list of users who can be mentioned in a group chat
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

// Get group ID
$group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
if (!$group_id) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing group ID'
    ]);
    exit;
}

// Get search query
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Verify user is a member of the group
global $database;
$memberCheck = $database->query("SELECT id FROM group_chat_members 
                                 WHERE group_id = $group_id AND user_id = " . (int)$session->userId . " AND left_at IS NULL 
                                 LIMIT 1");
if (!$memberCheck || $database->numRows($memberCheck) == 0) {
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'You are not a member of this group'
    ]);
    exit;
}

// Get all group members
$membersQuery = $database->query("SELECT gcm.user_id, u.firstName, u.email, u.accountStatus
                                  FROM group_chat_members gcm
                                  JOIN users u ON gcm.user_id = u.id
                                  WHERE gcm.group_id = $group_id AND gcm.left_at IS NULL
                                  ORDER BY u.firstName ASC");

$users = array();
while ($member = $database->fetchArray($membersQuery)) {
    $uid = (int)$member['user_id'];
    $user = User::findById($uid);
    
    if (!$user) continue;
    
    // Use only first name (eliminate last name)
    $firstName = !empty($user->firstName) ? trim($user->firstName) : 'User';
    $nameParts = preg_split('/\s+/', $firstName, 2);
    $displayName = !empty($nameParts[0]) ? trim($nameParts[0]) : 'User';
    
    // Filter by search query if provided
    if (!empty($search)) {
        $searchLower = strtolower($search);
        $nameLower = strtolower($displayName);
        $emailLower = strtolower($user->email);
        
        if (strpos($nameLower, $searchLower) === false && strpos($emailLower, $searchLower) === false) {
            continue;
        }
    }
    
    // Get profile picture
    $avatarUrl = '';
    $hasProfilePic = false;
    if (class_exists('profilePicture')) {
        $pics = profilePicture::findByfkUserId($uid);
        if ($pics && is_array($pics) && count($pics) > 0) {
            $avatarUrl = $url . 'includes/secure_file_handler.php?src=' . urlencode($url . 'uploads/profile-pics/' . $pics[0]->filename) . '&thumb=1';
            $hasProfilePic = true;
        }
    }
    
    // Generate initials from first name only
    $initials = !empty($displayName) ? strtoupper(substr($displayName, 0, 1)) : 'U';
    
    $users[] = array(
        'id' => $uid,
        'name' => htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'),
        'firstName' => htmlspecialchars($user->firstName, ENT_QUOTES, 'UTF-8'),
        'avatar' => $avatarUrl,
        'hasAvatar' => $hasProfilePic,
        'initials' => $initials,
        'colorIndex' => ($uid % 8) + 1,
        'accountStatus' => (int)$user->accountStatus
    );
}

ob_clean();
echo json_encode([
    'status' => 'success',
    'users' => $users
]);
exit;

