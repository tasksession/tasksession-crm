<?php
// Security: Require proper initialization
require_once('../bootstrap_config.php');
require_once('../initialize.php');

// Security: Check if user is logged in
if (!isset($session) || !$session->isLoggedIn()) {
    http_response_code(403);
    die('Access denied: Authentication required');
}

require_once('../csrf-middleware.php');
csrf_require_for_request('html');

// Security: Validate POST data exists
if (empty($_FILES) || !isset($_POST['timestamp']) || !isset($_POST['token']) || !isset($_POST['upType'])) {
    http_response_code(400);
    die('Invalid request: Missing required parameters');
}

include('secureImageUploader.class.php');

$uploader = new SecureImageUploader();

// Define a destination
$targetFolder = $base_root.'/uploads/user-uploads/'; // Relative to the root
$uploader->uploadPath = $_SERVER['DOCUMENT_ROOT'].$targetFolder;

// Security: Validate and sanitize timestamp
$timestamp = isset($_POST['timestamp']) ? preg_replace('/[^0-9]/', '', $_POST['timestamp']) : '';
if (empty($timestamp) || strlen($timestamp) > 20) {
    http_response_code(400);
    die('Invalid timestamp');
}

// Security: Validate token (weak but better than nothing - consider using session-based tokens)
$verifyToken = md5('S4lt' . $timestamp);
if ($_POST['token'] !== $verifyToken) {
    http_response_code(403);
    die('Invalid security token');
}

// Security: Validate upload type
$upType = isset($_POST['upType']) ? $_POST['upType'] : '';
if (!in_array($upType, ['images', 'files'])) {
    http_response_code(400);
    die('Invalid upload type');
}

// Security: Validate file was uploaded
if (!isset($_FILES['Filedata']) || $_FILES['Filedata']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die('File upload error');
}

$tempFile = $_FILES['Filedata']['tmp_name'];
$targetPath = $_SERVER['DOCUMENT_ROOT'] . $targetFolder;

// Security: Sanitize filename to prevent path traversal
$originalFilename = $_FILES['Filedata']['name'];
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

$fileParts = pathinfo($originalFilename);
$fileExtension = isset($fileParts['extension']) ? strtolower($fileParts['extension']) : '';

// Security: Validate file extension based on type
if ($upType == 'images') {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        http_response_code(400);
        die('Invalid image file type');
    }
} elseif ($upType == 'files') {
    $allowedExtensions = ['zip', 'pdf', 'doc', 'ppt', 'xls', 'txt', 'docx', 'xlsx', 'pptx'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        http_response_code(400);
        die('Invalid file type');
    }
}

// Security: Construct safe file path
$targetFile = rtrim($targetPath, '/') . '/' . $timestamp . '_' . $originalFilename;

// Security: Ensure target directory exists and is writable
if (!is_dir($targetPath)) {
    if (!mkdir($targetPath, 0755, true)) {
        http_response_code(500);
        die('Failed to create upload directory');
    }
}

// Security: Verify resolved path is within target directory (prevent path traversal)
$resolvedTarget = realpath($targetFile);
$resolvedPath = realpath($targetPath);
if ($resolvedTarget === false || $resolvedPath === false || strpos($resolvedTarget, $resolvedPath) !== 0) {
    http_response_code(403);
    die('Invalid file path');
}

// Process upload based on type
if ($upType == 'images') {
    $response = $uploader->upload_image($_FILES['Filedata'], $timestamp);
    if ($response == 'No Errors') {
        echo '1';
    } else {
        http_response_code(400);
        echo $response;
    }
} elseif ($upType == 'files') {
    // Security: Additional MIME type validation for files
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tempFile);
    finfo_close($finfo);
    
    // Basic MIME type check (not exhaustive but helps)
    $allowedMimeTypes = [
        'application/zip',
        'application/pdf',
        'application/msword',
        'application/vnd.ms-powerpoint',
        'application/vnd.ms-excel',
        'text/plain',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];
    
    if (!in_array($mimeType, $allowedMimeTypes) && strpos($mimeType, 'application/octet-stream') === false) {
        http_response_code(400);
        die('Invalid file MIME type');
    }
    
    if (move_uploaded_file($tempFile, $targetFile)) {
        echo '1';
    } else {
        http_response_code(500);
        die('Failed to move uploaded file');
    }
}
?>