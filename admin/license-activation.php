<?php
/**
 * Free edition — license activation is not required.
 */
require_once __DIR__ . '/../includes/lib-initialize.php';

if (!($session->isLoggedIn())) {
    redirectTo((isset($url) ? rtrim((string) $url, '/') . '/' : '../') . 'index');
}

$dest = (isset($url) ? rtrim((string) $url, '/') . '/' : '../') . 'admin/dashboard';
if (function_exists('redirectTo')) {
    redirectTo($dest);
}
header('Location: ' . $dest);
exit;
