<?php
require_once __DIR__ . '/includes/lib-initialize.php';
$home = isset($url) ? rtrim((string)$url, '/') . '/' : '/';
if (function_exists('redirectTo')) {
    redirectTo($home);
} else {
    header('Location: ' . $home);
}
exit;
