<?php
/**
 * Shared guard for migration hub (admin or staff with any migration-related access).
 */
if (!isset($session) || !$session->isLoggedIn()) {
    redirectTo($url . 'index.php');
}
$isAdmin = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 1;
$isStaff = isset($_SESSION['accountStatus']) && (int)$_SESSION['accountStatus'] === 3;
if (!$isAdmin && !$isStaff) {
    redirectTo($url . 'unauthorized.php');
}
require_once __DIR__ . '/../../includes/permissions.php';
ensure_user_permissions($connect);
if ($isStaff && !has_permission('lead_import') && !has_permission('lead_export')) {
    redirectTo($url . 'staff/index.php?message=error&error_msg=' . urlencode($lang['You do not have permission'] ?? 'You do not have permission'));
}
