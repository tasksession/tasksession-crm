<?php
/**
 * Secure Image Handler
 * Serves images only to authenticated users with proper validation
 * Prevents direct access to uploads folder
 */

// Include configuration first
require_once __DIR__ . '/bootstrap_config.php';
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/tasksession-upload-path.php';
require_once __DIR__ . '/tasksession-file-acl.php';

tasksession_session_start();

// Security headers
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: no-referrer');

// Function to validate user authentication
function is_authenticated() {
    if (session_status() === PHP_SESSION_NONE) {
        tasksession_session_start();
    }
    
    // Check for userId in session (primary key used by the system)
    if (isset($_SESSION['userId']) && !empty($_SESSION['userId'])) {
        return true;
    }
    
    // Also check for logged_user_id (alternative key used by the system)
    if (isset($_SESSION['logged_user_id']) && !empty($_SESSION['logged_user_id'])) {
        return true;
    }
    
    // Also check for user_id (alternative session key)
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        return true;
    }
    
    // Check for any user session data
    if (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
        return true;
    }
    
    // Check for accountStatus (indicates user is logged in)
    if (isset($_SESSION['accountStatus']) && !empty($_SESSION['accountStatus'])) {
        return true;
    }
    
    return false;
}

// Function to validate file access permissions
function can_access_file($file_path, $folder = '') {
    $filename = basename($file_path);
    if ($folder === '') {
        $normalized = str_replace('\\', '/', (string) $file_path);
        foreach (tasksession_allowed_upload_folders() as $name) {
            if (strpos($normalized, '/' . $name . '/') !== false) {
                $folder = $name;
                break;
            }
        }
    }

    if ($folder === 'system-uploads') {
        return true;
    }

    if (!is_authenticated()) {
        return false;
    }

    return tasksession_can_access_upload($folder, $filename);
}

// Function to serve image securely
function serve_image($file_path) {
    $uploads_dir = tasksession_uploads_root();
    if (!$uploads_dir) {
        http_response_code(500);
        die('Server error');
    }

    if (!file_exists($file_path)) {
        http_response_code(404);
        die('File not found');
    }

    $requested_file = realpath($file_path);
    
    if (!$requested_file || !tasksession_path_is_inside($requested_file, $uploads_dir)) {
        http_response_code(403);
        die('Access denied');
    }
    
    // Get file info
    $file_info = pathinfo($requested_file);
    $extension = strtolower($file_info['extension']);
    
    // Allowed image extensions
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    
    if (!in_array($extension, $allowed_extensions)) {
        http_response_code(403);
        die('Invalid file type');
    }
    
    // Set appropriate content type
    $content_types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml'
    ];
    
    $content_type = $content_types[$extension] ?? 'application/octet-stream';
    
    // Get file size
    $file_size = filesize($requested_file);
    
    // Set headers
    header('Content-Type: ' . $content_type);
    header('Content-Length: ' . $file_size);
    $is_system_upload = (strpos($requested_file, 'system-uploads') !== false);
    if ($is_system_upload) {
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));
    } else {
        header('Cache-Control: private, max-age=3600');
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
    }
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', filemtime($requested_file)));
    
    // Output file
    readfile($requested_file);
    exit;
}

// Main execution
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['file']) || isset($_GET['src']))) {
    $resolved = tasksession_resolve_upload_request([
        'src' => isset($_GET['src']) ? $_GET['src'] : '',
        'file' => isset($_GET['file']) ? $_GET['file'] : '',
    ]);
    if ($resolved === false) {
        http_response_code(400);
        die('Invalid file path');
    }

    $file_path = $resolved['path'];
    $folder = $resolved['folder'];

    if ($folder !== 'system-uploads') {
        if (!is_authenticated()) {
            http_response_code(401);
            die('Authentication required');
        }
    }

    if (!can_access_file($file_path, $folder)) {
        http_response_code(403);
        die('Access denied');
    }

    serve_image($file_path);
} else {
    http_response_code(400);
    die('Invalid request');
}
?> 