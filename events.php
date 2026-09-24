<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : events.php
   Purpose : Server-Sent Events handler for real-time chat functionality
 ================================================================================
*/

// Prevent output buffering for immediate sending of events
ob_end_clean(); // Clean any existing output buffers
ini_set('output_buffering', 'off');
ini_set('implicit_flush', true);
ob_implicit_flush(true);

// Set time limit to indefinite (be careful with this in production)
set_time_limit(0);
ignore_user_abort(true); // Continue even if client disconnects

// Set headers required for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('X-Accel-Buffering: no'); // For Nginx
header('Connection: keep-alive');

// Allow cross-origin if needed (uncomment and modify if required)
// header('Access-Control-Allow-Origin: *');

// Include database connection
require_once('includes/autoload.php');
require_once("./includes/initialize.php");

// Make sure user is authenticated
if(!($session->isLoggedIn())){
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Authentication required']) . "\n\n";
    flush();
    exit;
}

// Get project ID from query parameter
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

// Verify project exists and user has access
if ($project_id > 0) {
    $project = Projects::findByProjectId($project_id);
    if (!$project) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Invalid project']) . "\n\n";
        flush();
        exit;
    }
    
    // Get user information
    $user_id = $session->userId;
    $user = User::findById($user_id);
    $accountStatus = $user->accountStatus;

    // Admin always has access
    $hasAccess = ($accountStatus == 1);

    if (!$hasAccess) {
        if ($accountStatus == 2) { // Client
            // Check if client is the main client or additional client
            if($project->main_client_id == $user_id || $project->c_id == $user_id) {
                $hasAccess = true;
            } else if($project->isClient($user_id)) {
                $hasAccess = true;
            }
        } else if ($accountStatus == 3) { // Staff
            // Check if staff is in s_ids
            $hasAccess = strpos($project->s_ids, $user_id.',') !== false || 
                        strpos($project->s_ids, ','.$user_id) !== false ||
                        $project->s_ids == $user_id;
        }
    }

    if (!$hasAccess) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Access denied']) . "\n\n";
        flush();
        exit;
    }
}

// Get the last event ID (last message ID the client has received)
$lastId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int)$_SERVER['HTTP_LAST_EVENT_ID'] : 0;
if ($lastId == 0 && isset($_GET['lastId'])) {
    $lastId = (int)$_GET['lastId'];
}

// Try PDO connection first
try {
    $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Fall back to existing database connection if PDO fails
    $pdo = null;
}

// Function to fetch new messages
function getNewMessages($lastId, $project_id) {
    global $pdo, $database;
    
    // Try using PDO first if available
    if ($pdo) {
        try {
            $query = "SELECT m.id, m.message, m.time, m.user_id, m.receiver, m.storage_a, m.storage_b, m.status, m.Project_id, 
                      u.firstName, u.lastName 
                      FROM messages m 
                      JOIN users u ON m.user_id = u.id 
                      WHERE m.id > :last_id 
                      AND m.Project_id = :project_id 
                      AND m.receiver = 0
                      ORDER BY m.id ASC";
                      
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':last_id', $lastId, PDO::PARAM_INT);
            $stmt->bindParam(':project_id', $project_id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fall back to direct database query
        }
    }
    
    // Fall back to non-PDO database connection
    $sql = "SELECT m.id, m.message, m.time, m.user_id, m.receiver, m.storage_a, m.storage_b, m.status, m.Project_id, 
            u.firstName, u.lastName 
            FROM messages m 
            JOIN users u ON m.user_id = u.id 
            WHERE m.id > $lastId 
            AND m.Project_id = $project_id 
            AND m.receiver = 0
            ORDER BY m.id ASC";
            
    $result = $database->query($sql);
    $messages = array();
    
    if ($result) {
        while ($row = $database->fetchArray($result)) {
            $messages[] = $row;
        }
    }
    
    return $messages;
}

// Load attachment class for handling file/image uploads in messages
require_once("./includes/attachments.class.php");
$attach = new Attachment();
$attach->urlPath = rtrim($base_url, '/').'/uploads/user-uploads/';
$attach->filePath = $base_root.'/uploads/user-uploads/';

// Function to process emoticons in message text
function processEmoticons($message) {
    global $url;
    
    // List of emoticons to replace
    $emoticons = [
        "angry", "angry-devil", "anguished", "astonished", "blushed", "cold-sweat", "confounded", "confused", "crying",
        "disappointed", "disappointed-relieved", "dizzy", "emoji", "expressionless", "eyes", "face-with-cold", "fearful",
        "fire", "flushed", "frowning", "ghost", "grinmacing", "grinning", "halo", "head-bandage", "heart-eyes", "hugging",
        "hungry", "hushed", "kiss-emoji", "kissing", "kissing-face", "loudly-crying", "money-face", "nerd", "neutral",
        "relieved", "rolling-eyes", "shyly", "sick", "sign", "sleeping", "sleeping-snoring", "slightly", "smiling-devil",
        "smiling-eyes", "smiling-face", "smiling-smiling", "smirk", "sunglasses", "surprised", "sweat", "tears",
        "thermometer", "thinking", "thumbs-up", "tightly", "tired", "tongue-out-tightly", "tongue-out", "tongue-winking",
        "unamused", "up-pointing", "upside", "very-angry", "very-mad", "very-sad", "victory", "weary", "wink", "worried", "zipper"
    ];
    
    foreach($emoticons as $emoticon) {
        $pattern = '/\[' . preg_quote($emoticon) . '\]/';
        $replacement = '<img src="' . $url . 'assets/images/files/emoticons/' . ucfirst(str_replace('-', '', ucwords($emoticon, '-'))) . '.png" class="emoticon-small" alt="' . $emoticon . '">';
        $message = preg_replace($pattern, $replacement, $message);
    }
    
    return $message;
}

// Function to get profile picture URL or avatar initials data
function getProfilePicture($userId) {
    global $database, $url;
    
    $profilePic = '';
    $profilePicSql = "SELECT filename FROM profile_pics WHERE fkUserId = " . $userId . " LIMIT 1";
    $profilePicResult = $database->query($profilePicSql);
    
    if($profilePicResult && $database->numRows($profilePicResult) > 0) {
        $profilePicRow = $database->fetchArray($profilePicResult);
        $profilePic = $profilePicRow['filename'];
        return [
            'type' => 'image',
            'url' => $url . 'includes/thumbnail.php?src=' . $url . 'uploads/profile-pics/' . $profilePic . '&h=35&w=35'
        ];
    }
    
    // Return avatar initials data instead of placeholder image
    return [
        'type' => 'initials',
        'colorIndex' => ($userId % 8) + 1
    ];
}

// Process attachments and prepare messages for client
function processMessage($message) {
    global $attach, $session, $database;
    
    try {
        // Process file/image attachments
        $message['message'] = $attach->attachments($message['message']);
        
        // Process emoticons
        $message['message'] = processEmoticons($message['message']);
        
        // Get profile picture data
        $profileData = getProfilePicture($message['user_id']);
        $message['profile_pic'] = $profileData;
        
        // Generate user initials for avatar fallback
        $firstName = $message['firstName'] ?? '';
        $lastName = $message['lastName'] ?? '';
        $displayName = trim($firstName . ' ' . $lastName);
        
        $initials = '';
        if (!empty($firstName)) {
            $initials = strtoupper(substr($firstName, 0, 1));
            if (!empty($lastName)) {
                $initials .= strtoupper(substr($lastName, 0, 1));
            }
        } else {
            $initials = strtoupper(substr($displayName, 0, 1));
        }
        
        $message['user_initials'] = $initials;
        
        // Check if the message is from the current user
        $message['is_self'] = ($message['user_id'] == $session->userId);
        
        // Format timestamp for display
        $message['formatted_time'] = date('d/m/Y, h:i a', $message['time']);
        
        return $message;
    } catch (Exception $e) {
        return $message;
    }
}

// Initialize connection time tracking
$connectionStartTime = time();
$maxConnectionTime = 600; // 10 minutes max connection time (increased from 5 minutes)
$retry = 5; // Retry interval in seconds (increased from 3 seconds)
$pollInterval = 2; // Seconds between polls (added to prevent excessive CPU usage)

// Send initial retry value to client
echo "retry: " . ($retry * 1000) . "\n\n";
flush();

// Main SSE loop - continuously check for new messages
while (true) {
    // Check for connection timeout - reconnect every 10 minutes to prevent resource issues
    if (time() - $connectionStartTime > $maxConnectionTime) {
        echo "event: reconnect\n";
        echo "data: " . json_encode(['message' => 'Connection timeout, please reconnect']) . "\n\n";
        flush();
        break;
    }

    // Check if client is still connected
    if (connection_aborted()) {
        break;
    }
    
    // Fetch new messages
    $newMessages = getNewMessages($lastId, $project_id);
    $count = count($newMessages);
    
    // If messages found, send them
    if ($count > 0) {
        foreach ($newMessages as $msg) {
            // Update last message ID
            $lastId = max($lastId, $msg['id']);
            
            // Process message
            $processedMsg = processMessage($msg);
            
            // Format and send event
            echo "id: " . $msg['id'] . "\n";
            echo "event: message\n";
            echo "data: " . json_encode($processedMsg) . "\n\n";
            flush();
        }
    } else {
        // Send a keep-alive comment to prevent connection timeout
        // Note: The format is important - must start with a colon
        echo ": keepalive " . time() . "\n\n";
        flush();
    }
    
    // Sleep between polls to reduce server load
    // This is essential to prevent excessive CPU usage and connection instability
    usleep(500000); // 0.5 seconds (more responsive than a full 2 seconds)
}

// If we've exited the loop, close the connection
echo "event: close\n";
echo "data: " . json_encode(['message' => 'Connection closed by server']) . "\n\n";
flush();
exit();


?> 