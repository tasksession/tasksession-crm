<?php
/**
 * Free edition: user /settings page removed — redirect to role dashboard.
 */
require_once __DIR__ . '/includes/lib-initialize.php';

if (!isset($session) || !$session->isLoggedIn()) {
    $login = isset($url) ? rtrim((string) $url, '/') . '/' : '/';
    if (function_exists('redirectTo')) {
        redirectTo($login);
    }
    header('Location: ' . $login);
    exit;
}

$dest = function_exists('tasksession_free_role_dashboard_url')
    ? tasksession_free_role_dashboard_url()
    : (isset($url) ? rtrim((string) $url, '/') . '/' : '/');

if (function_exists('redirectTo')) {
    redirectTo($dest);
}
header('Location: ' . $dest);
exit;
