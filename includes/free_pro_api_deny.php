<?php
/**
 * Free edition: deny Pro-only AJAX/API endpoints with JSON (never upgrade HTML).
 * Include at the top of exclusive Pro endpoint scripts.
 *
 * Usage: require_once __DIR__ . '/../includes/free_pro_api_deny.php';
 * Or with feature: $TS_FREE_PRO_FEATURE = 'chatting'; require ...
 */
if (!defined('LIB_ROOT')) {
    require_once dirname(__DIR__) . '/includes/lib-initialize.php';
} elseif (!function_exists('tasksession_deny_pro_api')) {
    require_once LIB_ROOT . DIRECTORY_SEPARATOR . 'free_edition.php';
}

$feature = isset($TS_FREE_PRO_FEATURE) ? (string) $TS_FREE_PRO_FEATURE : 'pro';
if (function_exists('tasksession_deny_pro_api')) {
    tasksession_deny_pro_api($feature);
}
exit;
