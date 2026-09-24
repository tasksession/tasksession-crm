<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : logout.php
   Purpose : User logout functionality and session cleanup
 ================================================================================
*/
ob_start();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR);
defined('SITE_ROOT') ? null : define('SITE_ROOT', __DIR__);
defined('LIB_ROOT') ? null : define('LIB_ROOT', SITE_ROOT . DS . 'includes');

require_once __DIR__ . '/includes/bootstrap_config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/database-object.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/user.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/auth_helper.php';

$dash_settings = settings::findById(1);
$url = $dash_settings ? $dash_settings->url : '/';

$userId = isset($session->userId) ? (int) $session->userId : 0;
if ($userId > 0 && isset($connect) && $connect instanceof mysqli) {
    $stmt = $connect->prepare('UPDATE users SET session_status = ?, last_seen = ? WHERE id = ? LIMIT 1');
    if ($stmt) {
        $offline = 'offline';
        $lastSeen = time();
        $stmt->bind_param('sii', $offline, $lastSeen, $userId);
        $stmt->execute();
        $stmt->close();
    }
}

$authHelper = new AuthHelper($session);
$authHelper->logout();

// HTTPS sites: remember-me cookie was set with Secure; clear with same flags
if (isset($_COOKIE['secure_remember_token'])) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    setcookie('secure_remember_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Location: ' . rtrim((string) $url, '/') . '/', true, 302);
exit;
