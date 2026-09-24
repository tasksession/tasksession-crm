<?php
// Start output buffering immediately to catch any errors
ob_start();

// Suppress errors and warnings to ensure clean JSON output
error_reporting(0); // Disable all error reporting for this AJAX endpoint
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Skip heavy license / offline sweeps in lib-initialize
if (!defined('CRM_LIGHTWEIGHT_INIT')) {
    define('CRM_LIGHTWEIGHT_INIT', true);
}

// Set JSON header early
header('Content-Type: application/json');

/**
 * Always emit valid JSON for the envelope poll (never blank 500 body).
 */
function unread_counter_json_exit($payload, $httpCode = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code((int) $httpCode);
        header('Content-Type: application/json');
    }
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    if ($json === false) {
        $json = (is_array($payload) && isset($payload['success'])) ? '{"success":false}' : '[]';
    }
    echo $json;
    exit;
}

register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json');
    }
    echo '[]';
});

session_start();

// Suppress any output from includes
@require_once(dirname(__DIR__) . '/includes/lib-initialize.php');
@require_once(dirname(__DIR__) . '/includes/loader.php');

// Clear any output that might have been generated
ob_clean();

global $db1, $url;

if (!is_file(dirname(__DIR__) . '/includes/message_notification_read_helper.php')) {
    unread_counter_json_exit(array());
}
$__mnr = dirname(__DIR__) . '/includes/message_notification_read_helper.php';
if (is_file($__mnr)) { require_once $__mnr; }

// Mark all as read if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read']) && $_POST['mark_all_read'] == '1') {
    if (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {
        unread_counter_json_exit(array('success' => false, 'error' => 'Not logged in'));
    }
    $user_id = (int) $_SESSION['userId'];
    try {
        msg_notif_mark_all_as_read($db1, $user_id);
    } catch (Throwable $e) {
        error_log('[unread-counter] mark_all_read: ' . $e->getMessage());
    }
    unread_counter_json_exit(array('success' => true));
}

// Mark single notification as read if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_notification_read']) && $_POST['mark_notification_read'] == '1' && isset($_POST['notification_id'])) {
    if (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {
        unread_counter_json_exit(array('success' => false, 'error' => 'Not logged in'));
    }
    $user_id = $_SESSION['userId'];
    $notification_id = (int)$_POST['notification_id'];
    
    // Mark the specific notification as read
    if (file_exists(dirname(__DIR__) . '/includes/notifications.php')) {
        require_once(dirname(__DIR__) . '/includes/notifications.php');
        if (class_exists('Notifications') && isset($db1) && is_object($db1)) {
            $db1->query("UPDATE notifications SET is_read = 1 WHERE id = " . $notification_id . " AND user_id = " . (int)$user_id);
        }
    }
    unread_counter_json_exit(array('success' => true));
}

// Check if user is logged in
if (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {
    unread_counter_json_exit(array());
}
$user_id = (int)$_SESSION['userId'];
$user_cache = [];
$project_titles = [];
$unread_msgs_js = [];

if (!isset($db1) || !is_object($db1)) {
    unread_counter_json_exit(array());
}

try {
// Check if user is admin
$user_query = $db1->query("SELECT accountStatus FROM users WHERE id = {$user_id} LIMIT 1");
$user_row = $db1->fetch_row($user_query);
$is_admin = ($user_row && (int)$user_row['accountStatus'] === 1);

// Projects this user participates in (single query instead of scanning all projects in PHP)
$project_ids = [];
if ($is_admin) {
    $project_query = $db1->query("SELECT p_id, project_title FROM projects WHERE FIND_IN_SET({$user_id}, REPLACE(s_ids, ' ', '')) > 0");
} else {
    $project_query = $db1->query(
        "SELECT p_id, project_title FROM projects
         WHERE c_id = {$user_id}
            OR main_client_id = {$user_id}
            OR FIND_IN_SET({$user_id}, REPLACE(c_ids, ' ', '')) > 0
            OR FIND_IN_SET({$user_id}, REPLACE(s_ids, ' ', '')) > 0"
    );
}
if ($project_query) {
    while ($row = $db1->fetch_row($project_query)) {
        $pid = (int)$row['p_id'];
        $project_ids[] = $pid;
        $project_titles[$pid] = (string)$row['project_title'];
    }
}

$unread_msgs_js = [];
// Fetch unread discussion messages and group by project
if (!empty($project_ids)) {
    $in = implode(',', array_map('intval', $project_ids));
    $discussion_query = $db1->query(
        "SELECT m.* FROM messages m
         LEFT JOIN discussion_reads dr ON dr.message_id = m.id AND dr.user_id = {$user_id}
         WHERE m.status = 'unread'
           AND m.Project_id IN ($in)
           AND (m.receiver = 0 OR m.receiver = {$user_id})
           AND m.user_id != {$user_id}
           AND dr.message_id IS NULL
         ORDER BY m.time DESC
         LIMIT 150"
    );
    
    // Group messages by project
    $project_messages = [];
    while ($discussion_query && ($row = $db1->fetch_row($discussion_query))) {
        $project_id = $row['Project_id'];
        
        // Initialize project group if not exists
        if (!isset($project_messages[$project_id])) {
            $project_messages[$project_id] = [
                'project_id' => $project_id,
                'project_title' => '',
                'messages' => [],
                'latest_time' => 0,
                'sender_count' => 0,
                'senders' => []
            ];
            
            $project_messages[$project_id]['project_title'] = $project_titles[(int)$project_id] ?? '';
        }
        
        // Add message to project group
        $sender_uid = (int)$row['user_id'];
        if (!isset($user_cache[$sender_uid])) {
            $user_query2 = $db1->query("SELECT firstName, session_status, last_seen FROM users WHERE id = {$sender_uid} LIMIT 1");
            $user_cache[$sender_uid] = $user_query2 ? ($db1->fetch_row($user_query2) ?: []) : [];
        }
        $sender_name = (string)($user_cache[$sender_uid]['firstName'] ?? '');
        $online_status = function_exists('user_presence_status')
            ? user_presence_status($user_cache[$sender_uid]['session_status'] ?? 'offline', $user_cache[$sender_uid]['last_seen'] ?? 0)
            : ((($user_cache[$sender_uid]['session_status'] ?? '') === 'online') ? 'online' : 'offline');
        
        // Decrypt project discussion message before adding
        $message_content = $row['message'];
        if (isset($message_content) && !empty($message_content) && function_exists('decryptString')) {
            try {
                $decrypted = decryptString($message_content);
                if ($decrypted !== false && $decrypted !== $message_content) {
                    $message_content = $decrypted;
                }
            } catch(Exception $e) {
                // If decryption fails, use original (for backward compatibility)
                error_log("Failed to decrypt project discussion message in notifications: " . $e->getMessage());
            }
        }
        $message_content = msg_notif_format_message_preview($message_content);
        
        $project_messages[$project_id]['messages'][] = [
            'id' => $row['id'],
            'message' => $message_content,
            'time' => $row['time'],
            'user_id' => $row['user_id'],
            'sender_name' => $sender_name,
            'online_status' => $online_status
        ];
        
        // Track latest time and unique senders
        if ($row['time'] > $project_messages[$project_id]['latest_time']) {
            $project_messages[$project_id]['latest_time'] = $row['time'];
        }
        
        if (!in_array($row['user_id'], $project_messages[$project_id]['senders'])) {
            $project_messages[$project_id]['senders'][] = $row['user_id'];
            $project_messages[$project_id]['sender_count']++;
        }
    }
    
    // Convert grouped messages to notification format
    foreach ($project_messages as $project_id => $project_data) {
        if (empty($project_data['messages'])) continue;
        
        $latest_message = $project_data['messages'][0]; // First message is latest due to DESC order
        $message_count = count($project_data['messages']);
        
        $unread_msgs_js[] = [
            'id' => 'group_' . $project_id,
            'message' => $latest_message['message'],
            'time' => $project_data['latest_time'],
            'user_id' => $latest_message['user_id'],
            'receiver' => 0,
            'Project_id' => $project_id,
            'status' => 'unread',
            'sender_name' => $project_data['sender_count'] > 1 ? $project_data['sender_count'] . ' people' : $latest_message['sender_name'],
            'online_status' => $latest_message['online_status'],
            'project_title' => $project_data['project_title'],
            'message_count' => $message_count,
            'is_group' => true
        ];
    }
}

// Optionally, also fetch unread chat messages (Project_id = 0) and group by sender
$chat_query = $db1->query(
    "SELECT * FROM messages
     WHERE status = 'unread' AND Project_id = 0 AND (receiver = 0 OR receiver = {$user_id}) AND user_id != {$user_id}
     ORDER BY time DESC
     LIMIT 100"
);

// Group direct chat messages by sender
$chat_messages = [];
while ($chat_query && ($row = $db1->fetch_row($chat_query))) {
    $sender_id = (int)$row['user_id'];
    
    // Get sender info
    $sender_name = '';
    $online_status = 'offline';
    $sender_profile_img = '';
    if (!isset($user_cache[$sender_id])) {
        $user_query2 = $db1->query("SELECT firstName, session_status, last_seen FROM users WHERE id = {$sender_id} LIMIT 1");
        $user_cache[$sender_id] = $user_query2 ? ($db1->fetch_row($user_query2) ?: []) : [];
    }
    $sender_name = (string)($user_cache[$sender_id]['firstName'] ?? '');
    $online_status = function_exists('user_presence_status')
        ? user_presence_status($user_cache[$sender_id]['session_status'] ?? 'offline', $user_cache[$sender_id]['last_seen'] ?? 0)
        : ((($user_cache[$sender_id]['session_status'] ?? '') === 'online') ? 'online' : 'offline');
    
    // Get sender profile picture
    if (class_exists('profilePicture')) {
        $pics = profilePicture::findByfkUserId($sender_id);
        if ($pics && is_array($pics) && count($pics) > 0) {
            $sender_profile_img = $url . 'includes/thumbnail.php?src=' . urlencode($url . 'uploads/profile-pics/' . $pics[0]->filename) . '&w=35&h=35';
        }
    }
    
    // Initialize sender group if not exists
    if (!isset($chat_messages[$sender_id])) {
        $chat_messages[$sender_id] = [
            'sender_id' => $sender_id,
            'sender_name' => $sender_name,
            'online_status' => $online_status,
            'profile_img' => $sender_profile_img,
            'messages' => [],
            'latest_time' => 0
        ];
    }
    
    // Decrypt message before adding to sender group
    $message_content = $row['message'];
    if (isset($message_content) && !empty($message_content) && function_exists('decryptString')) {
        try {
            $decrypted = decryptString($message_content);
            if ($decrypted !== false && $decrypted !== $message_content) {
                $message_content = $decrypted;
            }
        } catch(Exception $e) {
            // If decryption fails, use original (for backward compatibility)
            error_log("Failed to decrypt chat message in notifications: " . $e->getMessage());
        }
    }
    $message_content = msg_notif_format_message_preview($message_content);
    
    // Add message to sender group
    $chat_messages[$sender_id]['messages'][] = [
        'id' => $row['id'],
        'message' => $message_content,
        'time' => $row['time'],
        'user_id' => $row['user_id']
    ];
    
    // Track latest time
    if ($row['time'] > $chat_messages[$sender_id]['latest_time']) {
        $chat_messages[$sender_id]['latest_time'] = $row['time'];
    }
}

// Convert grouped chat messages to notification format
foreach ($chat_messages as $sender_id => $chat_data) {
    if (empty($chat_data['messages'])) continue;
    
    $latest_message = $chat_data['messages'][0]; // First message is latest due to DESC order
    $message_count = count($chat_data['messages']);
    
    $unread_msgs_js[] = [
        'id' => 'chat_' . $sender_id,
        'message' => $latest_message['message'],
        'time' => $chat_data['latest_time'],
        'user_id' => $chat_data['sender_id'],
        'receiver' => 0,
        'Project_id' => 0,
        'status' => 'unread',
        'sender_name' => $chat_data['sender_name'],
        'online_status' => $chat_data['online_status'],
        'profile_img' => isset($chat_data['profile_img']) ? $chat_data['profile_img'] : '',
        'project_title' => '', // No project title for chat messages
        'message_count' => $message_count,
        'is_group' => false, // Keep as individual user (not group icon)
        'is_grouped_user' => $message_count > 1 // Flag for counter badge
    ];
}

// Tasks this user may receive task-chat notifications for (SQL filter — no full tasks table scan)
$user_tasks = [];
$user_task_query = $db1->query(
    "SELECT DISTINCT t.id FROM tasks t
     LEFT JOIN projects p ON t.project_id = p.p_id
     WHERE t.creator_id = {$user_id}
        OR FIND_IN_SET({$user_id}, REPLACE(t.assigned_to, ' ', '')) > 0
        OR p.c_id = {$user_id}
        OR p.main_client_id = {$user_id}
        OR FIND_IN_SET({$user_id}, REPLACE(p.c_ids, ' ', '')) > 0"
);
if ($user_task_query) {
    while ($task_row = $db1->fetch_row($user_task_query)) {
        $user_tasks[] = (int)$task_row['id'];
    }
}

// Fetch task chat messages for tasks user has access to
if (!empty($user_tasks)) {
    $task_ids_str = implode(',', array_map('intval', $user_tasks));
    $task_chat_query = $db1->query(
        "SELECT tc.*, t.title as task_title, t.project_id, p.project_title as project_name
         FROM task_chat tc
         JOIN tasks t ON tc.task_id = t.id
         LEFT JOIN projects p ON t.project_id = p.p_id
         LEFT JOIN task_chat_reads tcr ON tcr.message_id = tc.id AND tcr.user_id = {$user_id}
         WHERE tc.task_id IN ($task_ids_str)
           AND tc.user_id != {$user_id}
           AND tcr.message_id IS NULL
         ORDER BY tc.time DESC
         LIMIT 100"
    );
    
    $task_chat_messages = [];
    while ($task_chat_query && ($row = $db1->fetch_row($task_chat_query))) {
        $task_id = $row['task_id'];
        
        if (!isset($task_chat_messages[$task_id])) {
            $task_chat_messages[$task_id] = [
                'task_id' => $task_id,
                'task_title' => $row['task_title'],
                'project_name' => $row['project_name'],
                'messages' => [],
                'latest_time' => 0,
                'sender_count' => 0,
                'senders' => []
            ];
        }
        
        $sender_uid = (int)$row['user_id'];
        if (!isset($user_cache[$sender_uid])) {
            $user_query3 = $db1->query("SELECT firstName, session_status, last_seen FROM users WHERE id = {$sender_uid} LIMIT 1");
            $user_cache[$sender_uid] = $user_query3 ? ($db1->fetch_row($user_query3) ?: []) : [];
        }
        $sender_name = (string)($user_cache[$sender_uid]['firstName'] ?? '');
        $online_status = function_exists('user_presence_status')
            ? user_presence_status($user_cache[$sender_uid]['session_status'] ?? 'offline', $user_cache[$sender_uid]['last_seen'] ?? 0)
            : ((($user_cache[$sender_uid]['session_status'] ?? '') === 'online') ? 'online' : 'offline');
        
        // Decrypt message
        $message_content = $row['message'];
        if (!empty($message_content) && function_exists('decryptString')) {
            try {
                $decrypted = decryptString($message_content);
                if ($decrypted !== false && $decrypted !== $message_content) {
                    $message_content = $decrypted;
                }
            } catch (Exception $e) {
                error_log("Failed to decrypt task chat message: " . $e->getMessage());
            }
        }
        $message_content = msg_notif_format_message_preview($message_content);
        
        $task_chat_messages[$task_id]['messages'][] = [
            'id' => $row['id'],
            'message' => $message_content,
            'time' => $row['time'],
            'user_id' => $row['user_id'],
            'sender_name' => $sender_name,
            'online_status' => $online_status
        ];
        
        if ($row['time'] > $task_chat_messages[$task_id]['latest_time']) {
            $task_chat_messages[$task_id]['latest_time'] = $row['time'];
        }
        
        if (!in_array($row['user_id'], $task_chat_messages[$task_id]['senders'])) {
            $task_chat_messages[$task_id]['senders'][] = $row['user_id'];
            $task_chat_messages[$task_id]['sender_count']++;
        }
    }
    
    // Add task chat notifications
    foreach ($task_chat_messages as $task_id => $task_data) {
        if (empty($task_data['messages'])) continue;
        
        $latest_message = $task_data['messages'][0];
        $message_count = count($task_data['messages']);
        
        // For task chat, show ONLY the task title (not the project name)
        $task_info = $task_data['task_title'] ?? 'Task';
        
        $unread_msgs_js[] = [
            'id' => 'task_chat_' . $task_id,
            'message' => $latest_message['message'],
            'time' => $task_data['latest_time'],
            'user_id' => $latest_message['user_id'],
            'receiver' => 0,
            'Project_id' => 0,
            'status' => 'unread',
            'sender_name' => $task_data['sender_count'] > 1 ? $task_data['sender_count'] . ' people' : $latest_message['sender_name'],
            'online_status' => $latest_message['online_status'],
            'project_title' => $task_info, // Just the task title for task chat
            'message_count' => $message_count,
            'is_group' => true,
            'is_task_chat' => true,
            'task_id' => $task_id
        ];
    }
}

// Fetch unread group chat messages grouped by group
// Get all groups where user is a member
$user_groups_query = $db1->query("SELECT gcm.group_id, gc.name as group_name 
                                  FROM group_chat_members gcm
                                  JOIN group_chats gc ON gcm.group_id = gc.id
                                  WHERE gcm.user_id = $user_id AND gcm.left_at IS NULL");
$user_groups = [];
if ($user_groups_query) {
    while ($group_row = $db1->fetch_row($user_groups_query)) {
        $user_groups[] = [
            'group_id' => (int)$group_row['group_id'],
            'group_name' => $group_row['group_name']
        ];
    }
}

// Fetch group chat messages for groups user is member of
if (!empty($user_groups)) {
    $group_ids = array_map(function($g) { return $g['group_id']; }, $user_groups);
    $group_ids_str = implode(',', array_map('intval', $group_ids));
    
    $group_chat_query = $db1->query(
        "SELECT gcm.*, gc.name as group_name
         FROM group_chat_messages gcm
         JOIN group_chats gc ON gcm.group_id = gc.id
         LEFT JOIN group_chat_reads gcr ON gcr.message_id = gcm.id AND gcr.user_id = {$user_id}
         WHERE gcm.group_id IN ($group_ids_str)
           AND gcm.user_id != {$user_id}
           AND gcr.message_id IS NULL
         ORDER BY gcm.time DESC
         LIMIT 100"
    );
    
    $group_chat_messages = [];
    while ($group_chat_query && ($row = $db1->fetch_row($group_chat_query))) {
        $group_id = (int)$row['group_id'];
        
        if (!isset($group_chat_messages[$group_id])) {
            $group_chat_messages[$group_id] = [
                'group_id' => $group_id,
                'group_name' => $row['group_name'],
                'messages' => [],
                'latest_time' => 0,
                'sender_count' => 0,
                'senders' => []
            ];
        }
        
        $sender_uid = (int)$row['user_id'];
        if (!isset($user_cache[$sender_uid])) {
            $user_query4 = $db1->query("SELECT firstName, session_status, last_seen FROM users WHERE id = {$sender_uid} LIMIT 1");
            $user_cache[$sender_uid] = $user_query4 ? ($db1->fetch_row($user_query4) ?: []) : [];
        }
        $sender_name = (string)($user_cache[$sender_uid]['firstName'] ?? '');
        $online_status = function_exists('user_presence_status')
            ? user_presence_status($user_cache[$sender_uid]['session_status'] ?? 'offline', $user_cache[$sender_uid]['last_seen'] ?? 0)
            : ((($user_cache[$sender_uid]['session_status'] ?? '') === 'online') ? 'online' : 'offline');
        
        // Decrypt group chat message before adding
        $message_content = $row['message'];
        if (isset($message_content) && !empty($message_content) && function_exists('decryptString')) {
            try {
                $decrypted = decryptString($message_content);
                if ($decrypted !== false && $decrypted !== $message_content) {
                    $message_content = $decrypted;
                }
            } catch(Exception $e) {
                // If decryption fails, use original (for backward compatibility)
                error_log("Failed to decrypt group chat message in notifications: " . $e->getMessage());
            }
        }
        $message_content = msg_notif_format_message_preview($message_content);
        
        $group_chat_messages[$group_id]['messages'][] = [
            'id' => $row['id'],
            'message' => $message_content,
            'time' => $row['time'],
            'user_id' => $row['user_id'],
            'sender_name' => $sender_name,
            'online_status' => $online_status
        ];
        
        if ($row['time'] > $group_chat_messages[$group_id]['latest_time']) {
            $group_chat_messages[$group_id]['latest_time'] = $row['time'];
        }
        
        if (!in_array($row['user_id'], $group_chat_messages[$group_id]['senders'])) {
            $group_chat_messages[$group_id]['senders'][] = $row['user_id'];
            $group_chat_messages[$group_id]['sender_count']++;
        }
    }
    
    // Add group chat notifications
    foreach ($group_chat_messages as $group_id => $group_data) {
        if (empty($group_data['messages'])) continue;
        
        $latest_message = $group_data['messages'][0];
        $message_count = count($group_data['messages']);
        
        $unread_msgs_js[] = [
            'id' => 'group_chat_' . $group_id,
            'message' => $latest_message['message'],
            'time' => $group_data['latest_time'],
            'user_id' => $latest_message['user_id'],
            'receiver' => 0,
            'Project_id' => 0,
            'status' => 'unread',
            'sender_name' => $group_data['sender_count'] > 1 ? $group_data['sender_count'] . ' people' : $latest_message['sender_name'],
            'online_status' => $latest_message['online_status'],
            'project_title' => $group_data['group_name'], // Use group name for display
            'message_count' => $message_count,
            'is_group' => true,
            'is_group_chat' => true, // Flag to distinguish from project discussions
            'group_id' => $group_id,
            'group_name' => $group_data['group_name']
        ];
    }
}

// Fetch unread email notifications
if (file_exists(dirname(__DIR__) . '/includes/notifications.php')) {
    require_once(dirname(__DIR__) . '/includes/notifications.php');
    
    if (class_exists('Notifications')) {
        // Ensure user_id is set and is an integer
        $email_user_id = isset($user_id) ? (int)$user_id : 0;
        
        // Debug: Log query parameters
        error_log("[EMAIL NOTIFICATIONS] Query params: user_id=$email_user_id");
        
        $email_notifications_query = $db1->query(
            "SELECT id, type, title, message, related_id, created_at 
             FROM notifications 
             WHERE user_id = " . (int)$email_user_id . " 
             AND is_read = 0 
             AND related_type = 'email_account'
             AND type IN ('email_received', 'email_thread_updated')
             ORDER BY created_at DESC"
        );
    
    if ($email_notifications_query) {
        $email_notification_count = 0;
        while ($email_row = $db1->fetch_row($email_notifications_query)) {
            $email_notification_count++;
            // Parse notification message to extract email count and account email
            $notification_message = $email_row['message'];
            $account_id = (int)$email_row['related_id'];
            
            // Extract account email from message (format: "X emails received MM/DD/YYYY HH:MM\naccount@email.com")
            $account_email = '';
            $lines = explode("\n", $notification_message);
            if (count($lines) > 1) {
                $account_email = trim($lines[1]);
            }
            
            // Extract email count from first line
            $email_count = 1;
            if (preg_match('/^(\d+)\s+email/i', $notification_message, $matches)) {
                $email_count = (int)$matches[1];
            }
            
            // Convert created_at to timestamp (ensure proper timezone handling)
            // If created_at is in UTC or server timezone, strtotime should handle it
            $created_at_str = $email_row['created_at'];
            $created_timestamp = strtotime($created_at_str);
            
            // Debug: Log timestamp conversion for troubleshooting
            if ($created_timestamp === false) {
                error_log("[EMAIL NOTIFICATIONS] Failed to parse created_at: " . $created_at_str);
                $created_timestamp = time(); // Fallback to current time
            }
            
            $unread_msgs_js[] = [
                'id' => 'email_notification_' . $email_row['id'],
                'notification_id' => $email_row['id'], // Store notification ID for marking as read
                'message' => $notification_message,
                'time' => $created_timestamp,
                'user_id' => 0, // System notification
                'receiver' => 0,
                'Project_id' => 0,
                'status' => 'unread',
                'sender_name' => 'Email',
                'online_status' => 'offline',
                'profile_img' => '', // Will use envelope icon
                'project_title' => $account_email ? $account_email : 'Email Account',
                'message_count' => $email_count,
                'is_group' => false,
                'is_email_notification' => true, // Flag to identify email notifications
                'email_account_id' => $account_id,
                'email_type' => $email_row['type']
            ];
        }
        
        // Debug: Log email notification count
        error_log("[EMAIL NOTIFICATIONS] Fetched $email_notification_count email notifications for user $user_id");
        } else {
            // Debug: Log query failure
            $error = '';
            if (isset($db1->connection)) {
                $error = mysqli_error($db1->connection);
            } elseif (method_exists($db1, 'error')) {
                $error = $db1->error();
            }
            error_log("[EMAIL NOTIFICATIONS] Query failed for user $user_id: " . ($error ?: 'Unknown error'));
        }
    }
}

// Unread chat reaction notifications (envelope New messages only)
try {
    if (!function_exists('msg_notif_append_chat_reactions')) {
        $__mnr = dirname(__DIR__) . '/includes/message_notification_read_helper.php';
if (is_file($__mnr)) { require_once $__mnr; }
    }
    if (function_exists('msg_notif_append_chat_reactions')) {
        msg_notif_append_chat_reactions($db1, (int) $user_id, $unread_msgs_js, isset($url) ? $url : '');
    }
} catch (Throwable $e) {
    error_log('[unread-counter] chat reactions: ' . $e->getMessage());
}

// Newest activity first (discussion, DM, task, group, email, reactions — unified by `time` unix ts)
if (!empty($unread_msgs_js)) {
    usort($unread_msgs_js, function ($a, $b) {
        $ta = isset($a['time']) ? (int) $a['time'] : 0;
        $tb = isset($b['time']) ? (int) $b['time'] : 0;
        if ($tb !== $ta) {
            return $tb <=> $ta;
        }
        $ia = isset($a['id']) ? (string) $a['id'] : '';
        $ib = isset($b['id']) ? (string) $b['id'] : '';
        return strcmp($ib, $ia);
    });
}

} catch (Throwable $e) {
    error_log('[unread-counter] fatal path: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!isset($unread_msgs_js) || !is_array($unread_msgs_js)) {
        $unread_msgs_js = array();
    }
}

unread_counter_json_exit($unread_msgs_js); 