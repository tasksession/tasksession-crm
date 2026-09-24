<?php
/**
 * Secure File Handler
 * Serves all file types to authenticated users with proper validation
 * Generates thumbnails for different file types using custom icons
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

/**
 * Private, non-cacheable policy for authenticated file/thumbnail responses (vault thumbnails must not be cached on shared proxies).
 */
function secure_file_private_no_store_headers() {
    header('Cache-Control: private, no-store, no-cache, must-revalidate, no-transform');
    header('Pragma: no-cache');
    /* X-Content-Type-Options: nosniff is already sent above for every handler request */
}

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

/**
 * Folder ACL after path jail — see tasksession-file-acl.php.
 */
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

require_once __DIR__ . '/file_type_icons.php';

// Function to generate thumbnail for non-image files
function generate_file_thumbnail($file_path, $extension, $width = 200, $height = 200) {
    // Get the icon for this file type
    $icon_filename = get_file_type_icon($extension);
    $icon_path = dirname(__FILE__) . '/../assets/images/files/file-types/' . $icon_filename;
    
    // Debug: Check if icon exists
    if (!file_exists($icon_path)) {
        // Try alternative paths
        $alt_paths = [
            dirname(__FILE__) . '/../assets/images/files/file-types/' . $icon_filename,
            dirname(__FILE__) . '/../assets/images/files/file-types/' . strtolower($icon_filename),
            dirname(__FILE__) . '/../assets/images/files/file-types/files.png'
        ];
        
        foreach ($alt_paths as $path) {
            if (file_exists($path)) {
                $icon_path = $path;
                break;
            }
        }
        
        // If still not found, use generic file icon
        if (!file_exists($icon_path)) {
            $icon_path = dirname(__FILE__) . '/../assets/images/files/file-types/files.png';
        }
    }
    

    
    // Create a thumbnail image with the file type icon
    $thumbnail = imagecreatetruecolor($width, $height);
    
    // Set background to white
    $white = imagecolorallocate($thumbnail, 255, 255, 255);
    imagefill($thumbnail, 0, 0, $white);
    
    // Load the file type icon
    $icon_info = getimagesize($icon_path);
    if ($icon_info) {
        $icon_width = $icon_info[0];
        $icon_height = $icon_info[1];
        $icon_type = $icon_info[2];
        
        // Load icon based on type
        $icon_image = null;
        switch ($icon_type) {
            case IMAGETYPE_PNG:
                $icon_image = imagecreatefrompng($icon_path);
                break;
            case IMAGETYPE_JPEG:
                $icon_image = imagecreatefromjpeg($icon_path);
                break;
            case IMAGETYPE_GIF:
                $icon_image = imagecreatefromgif($icon_path);
                break;
        }
        
        if ($icon_image) {
            // Calculate position to center the icon
            $icon_size = min($width, $height) * 0.6; // Icon takes 60% of thumbnail
            $scale = min($icon_size / $icon_width, $icon_size / $icon_height);
            $new_icon_width = $icon_width * $scale;
            $new_icon_height = $icon_height * $scale;
            $x = ($width - $new_icon_width) / 2;
            $y = ($height - $new_icon_height) / 2;
            
            // Resize and copy icon to thumbnail
            imagecopyresampled($thumbnail, $icon_image, $x, $y, 0, 0, $new_icon_width, $new_icon_height, $icon_width, $icon_height);
            
            // Clean up
            imagedestroy($icon_image);
        }
    }
    
    return $thumbnail;
}

// Function to generate thumbnail for image files
function generate_image_thumbnail($file_path, $width = 200, $height = 200, $crop = false) {
    // Get image info
    $image_info = getimagesize($file_path);
    if (!$image_info) {
        // Return a default error thumbnail
        $thumbnail = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($thumbnail, 255, 255, 255);
        $red = imagecolorallocate($thumbnail, 255, 0, 0);
        imagefill($thumbnail, 0, 0, $white);
        imagestring($thumbnail, 3, 10, $height/2 - 10, 'Error', $red);
        return $thumbnail;
    }
    
    $original_width = $image_info[0];
    $original_height = $image_info[1];
    $image_type = $image_info[2];
    
    // Load original image based on type
    $original_image = null;
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $original_image = imagecreatefromjpeg($file_path);
            break;
        case IMAGETYPE_PNG:
            $original_image = imagecreatefrompng($file_path);
            break;
        case IMAGETYPE_GIF:
            $original_image = imagecreatefromgif($file_path);
            break;
        case IMAGETYPE_WEBP:
            $original_image = imagecreatefromwebp($file_path);
            break;
        default:
            // Return a default error thumbnail
            $thumbnail = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($thumbnail, 255, 255, 255);
            $red = imagecolorallocate($thumbnail, 255, 0, 0);
            imagefill($thumbnail, 0, 0, $white);
            imagestring($thumbnail, 3, 10, $height/2 - 10, 'Unsupported', $red);
            return $thumbnail;
    }
    
    if (!$original_image) {
        // Return a default error thumbnail
        $thumbnail = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($thumbnail, 255, 255, 255);
        $red = imagecolorallocate($thumbnail, 255, 0, 0);
        imagefill($thumbnail, 0, 0, $white);
        imagestring($thumbnail, 3, 10, $height/2 - 10, 'Error', $red);
        return $thumbnail;
    }
    
    // Create thumbnail
    $thumbnail = imagecreatetruecolor($width, $height);
    
    // Preserve transparency for PNG and GIF
    if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefill($thumbnail, 0, 0, $transparent);
    }
    
    if ($crop) {
        // Cover crop to fill the destination and crop excess
        $scale = max($width / $original_width, $height / $original_height);
        $new_width = (int) floor($original_width * $scale);
        $new_height = (int) floor($original_height * $scale);
        $src_x = (int) floor(($new_width - $width) / 2 / $scale * 1);
        $src_y = (int) floor(($new_height - $height) / 2 / $scale * 1);
        $src_w = (int) floor($width / $scale);
        $src_h = (int) floor($height / $scale);
        imagecopyresampled($thumbnail, $original_image, 0, 0, $src_x, $src_y, $width, $height, $src_w, $src_h);
    } else {
        // Contain fit with letterboxing
        $ratio = min($width / $original_width, $height / $original_height);
        $new_width = (int) floor($original_width * $ratio);
        $new_height = (int) floor($original_height * $ratio);
        $x = (int) floor(($width - $new_width) / 2);
        $y = (int) floor(($height - $new_height) / 2);
        imagecopyresampled($thumbnail, $original_image, $x, $y, 0, 0, $new_width, $new_height, $original_width, $original_height);
    }
    
    // Clean up
    imagedestroy($original_image);
    
    return $thumbnail;
}

// Function to serve file securely
function serve_file($file_path, $thumbnail_mode = false) {
    secure_file_private_no_store_headers();

    // Validate file path
    if (!file_exists($file_path)) {
        http_response_code(404);
        die('File not found');
    }

    $uploads_dir = tasksession_uploads_root();
    $requested_file = realpath($file_path);
    
    if (!$uploads_dir || !$requested_file || !tasksession_path_is_inside($requested_file, $uploads_dir)) {
        http_response_code(403);
        die('Access denied');
    }
    
    // Get file info
    $file_info = pathinfo($requested_file);
    $extension = strtolower($file_info['extension']);
    $basename = isset($file_info['basename']) ? $file_info['basename'] : basename($requested_file);

    // Encrypted voice notes (.webm.enc) — decrypt in memory and stream audio
    $is_voice_enc = ($extension === 'enc' && (substr($basename, -9) === '.webm.enc' || isset($_GET['voice'])));
    if ($is_voice_enc || (isset($_GET['voice']) && $_GET['voice'] === '1' && $extension === 'enc')) {
        require_once __DIR__ . '/file-crypto.php';
        $payload = file_get_contents($requested_file);
        if ($payload === false || !isEncryptedFilePayload($payload)) {
            http_response_code(500);
            die('Invalid encrypted voice file');
        }
        $plain = decryptFileBytes($payload);
        if ($plain === false || $plain === '') {
            http_response_code(500);
            die('Could not decrypt voice file');
        }
        header('Content-Type: audio/webm');
        header('Content-Length: ' . strlen($plain));
        header('Content-Disposition: inline; filename="' . preg_replace('/\.enc$/', '', $basename) . '"');
        header('Accept-Ranges: none');
        echo $plain;
        exit;
    }
    
    // If thumbnail mode is requested, generate thumbnail
    if ($thumbnail_mode) {
        // Optional custom size and crop params (profile pics default to 50x50 cropped)
        $is_profile_pic = strpos($file_path, 'profile-pics') !== false;
        $default_size = $is_profile_pic ? 50 : 200;
        $w = isset($_GET['w']) ? max(1, min(2000, (int) $_GET['w'])) : $default_size;
        $h = isset($_GET['h']) ? max(1, min(2000, (int) $_GET['h'])) : $default_size;
        $crop = isset($_GET['crop']) ? ((string)$_GET['crop'] === '1') : $is_profile_pic;
        // Check if it's an image file
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
        
        if (in_array($extension, $image_extensions)) {
            $__cma = __DIR__ . '/chat_media_album_helper.php';
if (is_file($__cma)) { require_once $__cma; }
            $cache = function_exists('chat_media_ensure_thumb')
                ? chat_media_ensure_thumb($requested_file, $w, $h, $crop)
                : false;
            header('Cache-Control: private, max-age=86400, no-transform');
            header_remove('Pragma');
            if ($cache && is_file($cache)) {
                header('Content-Type: image/jpeg');
                header('Content-Length: ' . filesize($cache));
                header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', filemtime($cache)));
                readfile($cache);
                exit;
            }
            $thumbnail = generate_image_thumbnail($file_path, $w, $h, $crop);
            header('Content-Type: image/jpeg');
            imagejpeg($thumbnail, null, 85);
            imagedestroy($thumbnail);
            exit;
        } else {
            // For non-image files, generate thumbnail with file type icon
            $thumbnail = generate_file_thumbnail($file_path, $extension, $w, $h);
            
            header('Content-Type: image/png');
            imagepng($thumbnail);
            imagedestroy($thumbnail);
            exit;
        }
    }
    
    // Set appropriate content type for file download
    $content_types = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt' => 'text/plain',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'psd' => 'image/vnd.adobe.photoshop',
        'eps' => 'application/postscript',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'bmp' => 'image/bmp',
        'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4',
        'avi' => 'video/x-msvideo',
        'mov' => 'video/quicktime',
        'wmv' => 'video/x-ms-wmv',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'flac' => 'audio/flac'
    ];
    
    $content_type = $content_types[$extension] ?? 'application/octet-stream';
    
    // Get file size
    $file_size = filesize($requested_file);
    
    // Set headers for file download / inline display
    header('Content-Type: ' . $content_type);
    header('Content-Length: ' . $file_size);
    header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
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
    $thumbnail_mode = isset($_GET['thumb']) && $_GET['thumb'] === '1';

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

    serve_file($file_path, $thumbnail_mode);
} else {
    http_response_code(400);
    die('Invalid request');
}
?> 