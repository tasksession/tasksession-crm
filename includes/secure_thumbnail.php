<?php
/**
 * Secure Thumbnail Handler
 * Creates thumbnails from secure images with authentication
 */

require_once __DIR__ . '/session_bootstrap.php';
tasksession_session_start();

// Include configuration
require_once __DIR__ . '/bootstrap_config.php';

// Security headers
header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: no-referrer');

// Function to validate user authentication
function is_authenticated() {
    return isset($_SESSION['userId']) && !empty($_SESSION['userId']);
}

// Function to validate file access permissions
function can_access_file($file_path) {
    global $connect;
    
    if (!is_authenticated()) {
        return false;
    }
    
    // Extract filename from path
    $filename = basename($file_path);
    
    // Check if user owns this file or has permission to access it
    $user_id = $_SESSION['userId'];
    
    // Check in profile pictures
    $sql = "SELECT id FROM profile_pics WHERE filename = ? AND fkUserId = ?";
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "si", $filename, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        return true;
    }
    
    // Allow own user-uploads files only (best-effort ownership by filename convention with user id prefix)
    if (strpos($file_path, 'user-uploads') !== false) {
        $uidPrefix = (string)$user_id . '_';
        if (strpos($filename, $uidPrefix) === 0) {
            return true;
        }
    }

    // Profile pictures remain readable for authenticated users (used across CRM avatars)
    if (strpos($file_path, 'profile-pics') !== false) {
        return true;
    }

    // Task file attachments: enforce task/project scope.
    if (strpos($file_path, 'task-files') !== false) {
        $sql = "SELECT tf.task_id FROM task_files tf WHERE tf.filename = ? LIMIT 1";
        $stmt = mysqli_prepare($connect, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $filename);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = $result ? mysqli_fetch_assoc($result) : null;
            mysqli_stmt_close($stmt);
            if (!$row || empty($row['task_id'])) {
                return false;
            }
            $taskId = (int)$row['task_id'];
            $taskSql = "SELECT t.id, t.project_id, t.assigned_to, p.c_id, p.main_client_id, p.c_ids, p.s_ids
                        FROM tasks t
                        LEFT JOIN projects p ON p.p_id = t.project_id
                        WHERE t.id = ? LIMIT 1";
            $taskStmt = mysqli_prepare($connect, $taskSql);
            if (!$taskStmt) {
                return false;
            }
            mysqli_stmt_bind_param($taskStmt, "i", $taskId);
            mysqli_stmt_execute($taskStmt);
            $taskRes = mysqli_stmt_get_result($taskStmt);
            $taskRow = $taskRes ? mysqli_fetch_assoc($taskRes) : null;
            mysqli_stmt_close($taskStmt);
            if (!$taskRow) {
                return false;
            }

            $acctStmt = mysqli_prepare($connect, "SELECT accountStatus FROM users WHERE id = ? LIMIT 1");
            if (!$acctStmt) {
                return false;
            }
            mysqli_stmt_bind_param($acctStmt, "i", $user_id);
            mysqli_stmt_execute($acctStmt);
            $acctRes = mysqli_stmt_get_result($acctStmt);
            $acctRow = $acctRes ? mysqli_fetch_assoc($acctRes) : null;
            mysqli_stmt_close($acctStmt);
            $accountStatus = isset($acctRow['accountStatus']) ? (int)$acctRow['accountStatus'] : 0;

            if ($accountStatus === 1) return true; // admin
            if ($accountStatus === 3) { // staff
                $staffIds = array_filter(array_map('trim', explode(',', (string)($taskRow['s_ids'] ?? ''))));
                $assignedIds = array_filter(array_map('trim', explode(',', (string)($taskRow['assigned_to'] ?? ''))));
                return in_array((string)$user_id, $staffIds, true) || in_array((string)$user_id, $assignedIds, true);
            }
            if ($accountStatus === 2) { // client
                if ((int)($taskRow['c_id'] ?? 0) === (int)$user_id || (int)($taskRow['main_client_id'] ?? 0) === (int)$user_id) {
                    return true;
                }
                $clientIds = array_filter(array_map('trim', explode(',', (string)($taskRow['c_ids'] ?? ''))));
                return in_array((string)$user_id, $clientIds, true);
            }
            return false;
        }
    }
    
    // Check if user is admin/staff and can access all files
    $sql = "SELECT accountStatus FROM users WHERE id = ?";
    $stmt = mysqli_prepare($connect, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if ($user && in_array((int)$user['accountStatus'], [1, 3], true)) { // Admin or Staff
        return true;
    }
    
    return false;
}

// Define cache directory
$cacheDir = __DIR__ . '/../uploads/cache/';
if (!is_dir($cacheDir)) {
    if (!mkdir($cacheDir, 0755, true)) {
        header("HTTP/1.1 500 Internal Server Error");
        exit("Failed to create cache directory.");
    }
}

// Get parameters and validate
$src = isset($_GET['src']) ? $_GET['src'] : '';
$targetWidth = isset($_GET['w']) ? (int)$_GET['w'] : 50;
$targetHeight = isset($_GET['h']) ? (int)$_GET['h'] : 50;

if (empty($src)) {
    header("HTTP/1.1 400 Bad Request");
    exit("No image source specified.");
}

// Check authentication
if (!is_authenticated()) {
    header("HTTP/1.1 401 Unauthorized");
    exit("Authentication required.");
}

// Extract filename and path from src URL
$parsed_url = parse_url($src);
$path_parts = explode('/', $parsed_url['path']);

// Check if this is a secure image handler URL
if (strpos($parsed_url['path'], 'secure_image_handler.php') !== false) {
    // Handle secure image handler URL
    if (isset($parsed_url['query'])) {
        parse_str($parsed_url['query'], $query_params);
        if (isset($query_params['src'])) {
            // Decode the nested URL
            $nested_url = urldecode($query_params['src']);
            $nested_parsed = parse_url($nested_url);
            $nested_path_parts = explode('/', $nested_parsed['path']);
            
            // Find the uploads folder in the nested path
            $uploads_index = array_search('uploads', $nested_path_parts);
            if ($uploads_index === false) {
                header("HTTP/1.1 400 Bad Request");
                exit("Invalid uploads path in nested URL.");
            }
            
            // Get the relative path from uploads folder
            $relative_path_parts = array_slice($nested_path_parts, $uploads_index + 1);
            $relative_path = implode('/', $relative_path_parts);
            $filename = basename($relative_path);
        } else {
            header("HTTP/1.1 400 Bad Request");
            exit("No src parameter in secure image handler URL.");
        }
    } else {
        header("HTTP/1.1 400 Bad Request");
        exit("No query parameters in secure image handler URL.");
    }
} else {
    // Handle direct uploads URL
    $uploads_index = array_search('uploads', $path_parts);
    if ($uploads_index === false) {
        header("HTTP/1.1 400 Bad Request");
        exit("Invalid uploads path.");
    }
    
    // Get the relative path from uploads folder
    $relative_path_parts = array_slice($path_parts, $uploads_index + 1);
    $relative_path = implode('/', $relative_path_parts);
    $filename = basename($relative_path);
}

// Check if user can access this file
if (!can_access_file($relative_path)) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied.");
}

// Validate file path
$uploads_dir = realpath(__DIR__ . '/../uploads/');
$file_path = $uploads_dir . '/' . ltrim($relative_path, '/\\');
$real_file_path = realpath($file_path);

// Security check: ensure file is within uploads directory
if (!$real_file_path || strpos($real_file_path, $uploads_dir) !== 0) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied.");
}

// Check if file exists
if (!file_exists($real_file_path)) {
    header("HTTP/1.1 404 Not Found");
    exit("File not found.");
}

// Get file info
$file_info = pathinfo($real_file_path);
$extension = strtolower($file_info['extension']);

// Allowed image extensions
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

if (!in_array($extension, $allowed_extensions)) {
    header("HTTP/1.1 403 Forbidden");
    exit("Invalid file type.");
}

// Generate a unique cache filename based on source and dimensions
$cacheFile = $cacheDir . md5($filename . $targetWidth . $targetHeight . 'crop') . '.jpg';

// If cached file exists, serve it securely
if (file_exists($cacheFile)) {
    // Set headers
    header('Content-Type: image/jpeg');
    header('Cache-Control: private, max-age=3600'); // Cache for 1 hour
    header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', filemtime($cacheFile)));
    
    // Output cached file
    readfile($cacheFile);
    exit;
}

// Read the image file
$imageContent = file_get_contents($real_file_path);
if ($imageContent === false) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Failed to read image file.");
}

// Create an image resource from the content
$sourceImage = imagecreatefromstring($imageContent);
if (!$sourceImage) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Failed to create image from source.");
}

// Get original dimensions
$sourceWidth = imagesx($sourceImage);
$sourceHeight = imagesy($sourceImage);

// Calculate dimensions for center crop to exact size
$scaleX = $targetWidth / $sourceWidth;
$scaleY = $targetHeight / $sourceHeight;
$scale = max($scaleX, $scaleY); // Use the larger scale to ensure the image covers the target area

// Calculate the scaled dimensions
$scaledWidth = $sourceWidth * $scale;
$scaledHeight = $sourceHeight * $scale;

// Calculate the crop position (center the image)
$cropX = ($scaledWidth - $targetWidth) / 2;
$cropY = ($scaledHeight - $targetHeight) / 2;

// Create a new true color image for the thumbnail
$newImage = imagecreatetruecolor($targetWidth, $targetHeight);

// Handle transparency for PNG and GIF images
$imageInfo = getimagesizefromstring($imageContent);
if ($imageInfo && in_array($imageInfo[2], [IMAGETYPE_PNG, IMAGETYPE_GIF])) {
    // Enable alpha blending
    imagealphablending($newImage, false);
    imagesavealpha($newImage, true);
    
    // Create transparent background
    $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
    imagefill($newImage, 0, 0, $transparent);
}

// Resample the source image into the new image with center crop
imagecopyresampled(
    $newImage, $sourceImage,
    0, 0, $cropX / $scale, $cropY / $scale,
    $targetWidth, $targetHeight,
    $sourceWidth - (2 * $cropX / $scale), $sourceHeight - (2 * $cropY / $scale)
);

// Save the thumbnail to cache as JPEG
if (!imagejpeg($newImage, $cacheFile, 90)) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Failed to save the resized image.");
}

// Output the thumbnail
header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600'); // Cache for 1 hour
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));
readfile($cacheFile);

// Clean up
imagedestroy($sourceImage);
imagedestroy($newImage);
exit;
?> 