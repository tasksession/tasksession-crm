<?php
/**
 * Chunked Upload Handler for Large Files
 * Provides real-time progress updates for drag-and-drop uploads
 */

// Errors go to log only (no public display)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set longer execution time
set_time_limit(3600); // 1 hour
ini_set('max_execution_time', 3600);

// Include required files
require_once("../includes/lib-initialize.php");
$__gdriveFm = __DIR__ . '/../vendor/google/gdrive/includes/file_manager.php';
if (!is_file($__gdriveFm) || (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition())) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(array('success' => false, 'error' => 'Media management is not available'));
    exit;
}
require_once($__gdriveFm);

// Set JSON header
header('Content-Type: application/json');

// Clear any existing output
if (ob_get_level()) {
    ob_end_clean();
}

try {
    // Get parameters
    $folder_id = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
    $project_id = isset($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 1;
    
    // Check if files were uploaded
    if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
        echo json_encode(['success' => false, 'error' => 'No files selected']);
        exit;
    }
    
    $file_count = count($_FILES['files']['name']);
    $uploaded_files = array();
    $errors = array();
    
    // Initialize FileManager
    $file_manager = new FileManager();
    
    // Process each uploaded file
    for ($i = 0; $i < $file_count; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['files']['name'][$i];
            $file_tmp = $_FILES['files']['tmp_name'][$i];
            $file_size = $_FILES['files']['size'][$i];
            $file_type = $_FILES['files']['type'][$i];
            
            // Log upload start
            error_log("Chunked upload handler - Starting upload: $file_name, size: " . number_format($file_size / 1024 / 1024, 2) . "MB");
            
            // Structure the file data as expected by FileManager::uploadFile
            $file_data = array(
                'name' => $file_name,
                'type' => $file_type,
                'tmp_name' => $file_tmp,
                'error' => $_FILES['files']['error'][$i],
                'size' => $file_size
            );
            
            // Upload file using FileManager (will use chunked upload for large files)
            $upload_start_time = microtime(true);
            $result = $file_manager->uploadFile($file_data, $project_id, $folder_id, $user_id);
            $upload_end_time = microtime(true);
            $upload_duration = round($upload_end_time - $upload_start_time, 2);
            
            // Log upload result
            error_log("Chunked upload handler - Upload completed: $file_name in {$upload_duration} seconds");
            error_log("Chunked upload handler - Result: " . print_r($result, true));
            
            if ($result['success']) {
                $uploaded_files[] = [
                    'filename' => $result['filename'],
                    'file_id' => $result['file_id'],
                    'upload_time' => $upload_duration,
                    'upload_method' => $result['upload_method'] ?? 'standard'
                ];
            } else {
                $error_msg = $file_name . ': ' . ($result['error'] ?? 'Unknown error');
                $errors[] = $error_msg;
                error_log("Chunked upload handler - Upload error: $error_msg");
            }
        } else {
            $error_msg = $_FILES['files']['name'][$i] . ': Upload error (code: ' . $_FILES['files']['error'][$i] . ')';
            $errors[] = $error_msg;
            error_log("Chunked upload handler - File upload error: $error_msg");
        }
    }
    
    // Prepare response
    if (empty($uploaded_files)) {
        echo json_encode([
            'success' => false, 
            'error' => 'No files uploaded successfully', 
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => true, 
            'uploaded_files' => $uploaded_files, 
            'errors' => $errors,
            'message' => 'Files uploaded successfully using chunked upload'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Chunked upload handler - Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Upload failed: ' . $e->getMessage()
    ]);
}
?>

