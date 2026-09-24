<?php
/**
 * Task Files Upload Handler
 * Handles file uploads for task attachments
 */

// Log errors; do not emit to response body (JSON must stay valid).
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Include required files
require_once("../includes/lib-initialize.php");
require_once("../includes/task.php");
require_once("../includes/projects.php");
require_once("../includes/permissions.php");
require_once("../includes/csrf-middleware.php");

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if(!($session->isLoggedIn())){
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}
ensure_user_permissions($connect);
csrf_require_for_request('json');

// Get user ID
$user_id = $session->userId;

// Check if task ID is provided
if (!isset($_POST['task_id']) || empty($_POST['task_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Task ID is required']);
    exit;
}

$task_id = (int)$_POST['task_id'];

// Verify task exists and user has access
$task = Task::findById((int)$task_id);
if (!$task) {
    echo json_encode(['status' => 'error', 'message' => 'Task not found']);
    exit;
}

$user_type = isset($_SESSION['accountStatus']) ? (int)$_SESSION['accountStatus'] : 0;
$canUpload = false;
if ($user_type === 1) {
    $canUpload = true;
} elseif ($user_type === 3) {
    $canUpload = staff_can_view_task($task, $user_id);
} elseif ($user_type === 2) {
    $canUpload = $task->isClientAssociated($user_id);
}
if (!$canUpload) {
    echo json_encode(['status' => 'error', 'message' => 'Access denied for this task']);
    exit;
}

// Check if files were uploaded
if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
    echo json_encode(['status' => 'error', 'message' => 'No files selected']);
    exit;
}

// Allowed file types and max size
$allowed_extensions = ['gif', 'png', 'jpg', 'jpeg', 'zip', 'pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'pptx', 'eps', 'psd', 'ai', 'fw'];
$max_size = 5 * 1024 * 1024; // 5MB

$uploaded_files = [];
$errors = [];

// Create upload directory if it doesn't exist
$upload_dir = dirname(__DIR__) . '/uploads/task-files/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Process each uploaded file
$file_count = count($_FILES['files']['name']);
for ($i = 0; $i < $file_count; $i++) {
    $file_name = $_FILES['files']['name'][$i];
    $file_tmp = $_FILES['files']['tmp_name'][$i];
    $file_size = $_FILES['files']['size'][$i];
    $file_type = $_FILES['files']['type'][$i];
    $file_error = $_FILES['files']['error'][$i];
    
    // Check for upload errors
    if ($file_error !== UPLOAD_ERR_OK) {
        $errors[] = "$file_name: Upload error ($file_error)";
        continue;
    }
    
    // Check file size
    if ($file_size > $max_size) {
        $errors[] = "$file_name: File too large (max 5MB)";
        continue;
    }
    
    // Check file extension
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_extensions)) {
        $errors[] = "$file_name: File type not allowed";
        continue;
    }
    
    // Generate unique filename
    $timestamp = time();
    $random_string = substr(md5(uniqid(rand(), true)), 0, 8);
    $unique_filename = $timestamp . '_' . $random_string . '.' . $file_extension;
    
    // Move file to upload directory
    $target_path = $upload_dir . $unique_filename;
    if (move_uploaded_file($file_tmp, $target_path)) {
        // Save file info to database
        $insert_query = "INSERT INTO task_files (task_id, user_id, filename, original_name, file_size, file_type, file_path) VALUES (
            " . (int)$task_id . ",
            " . (int)$user_id . ",
            '" . $database->escapeValue($unique_filename) . "',
            '" . $database->escapeValue($file_name) . "',
            " . (int)$file_size . ",
            '" . $database->escapeValue($file_type) . "',
            '" . $database->escapeValue($target_path) . "'
        )";
        
        if ($database->query($insert_query)) {
            $file_id = $database->insertId();
            $uploaded_files[] = [
                'id' => $file_id,
                'filename' => $unique_filename,
                'original_name' => $file_name,
                'file_size' => $file_size,
                'file_type' => $file_type,
                'upload_date' => date('Y-m-d H:i:s')
            ];
        } else {
            $errors[] = "$file_name: Database error";
            // Remove uploaded file if database insert failed
            if (file_exists($target_path)) {
                unlink($target_path);
            }
        }
    } else {
        $errors[] = "$file_name: Failed to save file";
    }
}

// Return response
if (!empty($uploaded_files)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Files uploaded successfully',
        'files' => $uploaded_files,
        'errors' => $errors
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No files were uploaded',
        'errors' => $errors
    ]);
}
?>
