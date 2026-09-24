<?php
/**
 * Universal Media Handler
 * Redirects users to their role-specific media page based on their account status
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get URL parameters
$projectId = isset($_GET['projectId']) ? $_GET['projectId'] : '';
$fileId = isset($_GET['fileId']) ? (int)$_GET['fileId'] : 0;
$modal = isset($_GET['modal']) ? $_GET['modal'] : '';
$page = isset($_GET['page']) ? $_GET['page'] : '';
$folder = isset($_GET['folder']) ? $_GET['folder'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$fileType = isset($_GET['file_type']) ? $_GET['file_type'] : '';

// Check if this is a shared link (has fileId and modal parameters)
$isSharedLink = ($fileId > 0) && ($modal === '1');

// Simple check if user is logged in
if (!isset($_SESSION['userId']) || empty($_SESSION['userId'])) {
    if ($isSharedLink) {
        // For shared links, redirect to login with a return URL
        $returnUrl = urlencode($_SERVER['REQUEST_URI']);
        header('Location: index.php?return=' . $returnUrl);
        exit;
    } else {
        // Regular redirect to login page
        header('Location: index.php');
        exit;
    }
}

// Check if this is a media vault URL (no projectId or projectId is null)
$isMediaVault = empty($projectId) || $projectId === 'null' || $projectId === null;

// Build redirect URL based on user role and URL type
$redirectUrl = '';

// Get user's account status with fallback
$accountStatus = isset($_SESSION['accountStatus']) ? (int)$_SESSION['accountStatus'] : 2;

if ($isMediaVault) {
    // Redirect to media vault pages
    switch ($accountStatus) {
        case 1: // Admin
            $redirectUrl = 'admin/media-vault';
            break;
        case 2: // Client
            $redirectUrl = 'client/media-vault';
            break;
        case 3: // Staff
            $redirectUrl = 'staff/media-vault';
            break;
        default:
            // Fallback to client for unknown roles
            $redirectUrl = 'client/media-vault';
            break;
    }
} else {
    // Redirect to project media pages
    switch ($accountStatus) {
        case 1: // Admin
            $redirectUrl = 'admin/media';
            break;
        case 2: // Client
            $redirectUrl = 'client/media';
            break;
        case 3: // Staff
            $redirectUrl = 'staff/media'; // Staff uses their own media page
            break;
        default:
            // Fallback to client for unknown roles
            $redirectUrl = 'client/media';
            break;
    }
}

// Build query parameters
$queryParams = [];

// Only add projectId for project media pages, not for media vault
if (!$isMediaVault && !empty($projectId) && $projectId !== 'null') {
    $queryParams[] = 'projectId=' . $projectId;
}

if ($fileId > 0) {
    $queryParams[] = 'fileId=' . $fileId;
}
if ($modal === '1') {
    $queryParams[] = 'modal=1';
}
if (!empty($page)) {
    $queryParams[] = 'page=' . urlencode($page);
}
if (!empty($folder)) {
    $queryParams[] = 'folder=' . urlencode($folder);
}
if (!empty($search)) {
    $queryParams[] = 'search=' . urlencode($search);
}
if (!empty($fileType)) {
    $queryParams[] = 'file_type=' . urlencode($fileType);
}

// Add query string if parameters exist
if (!empty($queryParams)) {
    $redirectUrl .= '?' . implode('&', $queryParams);
}

// Redirect to appropriate media page
header('Location: ' . $redirectUrl);
exit;
?>
