<?php
/**
 * Free edition — license page not used.
 */
require_once __DIR__ . '/../includes/initialize.php';
$dest = (isset($url) ? rtrim((string) $url, '/') . '/' : '../') . 'admin/dashboard';
if (function_exists('redirectTo')) {
    redirectTo($dest);
}
header('Location: ' . $dest);
exit;
