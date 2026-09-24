<?php
/**
 * Secure File Upload Handler
 * CRITICAL: This file had severe security vulnerabilities - now fixed
 */

// Security: Require proper initialization
require_once(dirname(__DIR__) . '/includes/bootstrap_config.php');
require_once(dirname(__DIR__) . '/includes/initialize.php');

// Security: Check if user is logged in
if (!isset($session) || !$session->isLoggedIn()) {
    http_response_code(403);
    die('Access denied: Authentication required');
}

// Security: Validate file upload
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('File upload error');
}

// Security: Validate timestamp parameter
if (!isset($_POST['tm']) || empty($_POST['tm'])) {
    http_response_code(400);
    die('Missing timestamp parameter');
}

$timestamp = preg_replace('/[^0-9]/', '', $_POST['tm']);
if (empty($timestamp) || strlen($timestamp) > 20) {
    http_response_code(400);
    die('Invalid timestamp');
}

$uploads_dir = dirname(__DIR__) . '/uploads/user-uploads/';

// Security: Ensure upload directory exists
if (!is_dir($uploads_dir)) {
    if (!mkdir($uploads_dir, 0755, true)) {
        http_response_code(500);
        die('Failed to create upload directory');
    }
}

// Security: Sanitize filename to prevent path traversal
$originalFilename = $_FILES['file']['name'];
// Remove any path components
$originalFilename = basename($originalFilename);
// Remove any null bytes
$originalFilename = str_replace("\0", '', $originalFilename);
// Remove directory traversal attempts
$originalFilename = str_replace(['../', '..\\', '/', '\\'], '', $originalFilename);
// Limit filename length
if (strlen($originalFilename) > 255) {
    $ext = pathinfo($originalFilename, PATHINFO_EXTENSION);
    $name = substr(pathinfo($originalFilename, PATHINFO_FILENAME), 0, 250 - strlen($ext));
    $originalFilename = $name . '.' . $ext;
}

// Security: Validate file extension
$fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'psd', 'eps', 'webp'];
if (!in_array($fileExtension, $allowedExtensions)) {
    http_response_code(400);
    die('Invalid file type');
}

// Security: Validate file size (max 50MB)
$maxSize = 50 * 1024 * 1024; // 50MB
if ($_FILES['file']['size'] > $maxSize) {
    http_response_code(400);
    die('File too large (max 50MB)');
}

// Security: Additional MIME type validation
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $_FILES['file']['tmp_name']);
finfo_close($finfo);

// Construct safe filename
$file_name = $timestamp . '_' . $originalFilename;
$file_tmp = $_FILES['file']['tmp_name'];
$target_path = $uploads_dir . $file_name;

// Security: Verify resolved path is within upload directory (prevent path traversal)
$resolvedTarget = realpath($target_path);
$resolvedDir = realpath($uploads_dir);
if ($resolvedTarget === false || $resolvedDir === false || strpos($resolvedTarget, $resolvedDir) !== 0) {
    http_response_code(403);
    die('Invalid file path');
}

// Move uploaded file
if (move_uploaded_file($file_tmp, $target_path)) {
    echo $file_name;
} else {
    http_response_code(500);
    die('Error in uploading file');
}