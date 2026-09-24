<?php
/**
 * Free edition — paid addon deactivation is Pro-only.
 */
define('CRM_LIGHTWEIGHT_INIT', true);
ob_start();
require_once('../../includes/lib-initialize.php');

header('Content-Type: application/json; charset=utf-8');
while (ob_get_level() > 0) {
    ob_end_clean();
}
http_response_code(403);
echo json_encode([
    'success' => false,
    'code' => 'free_edition',
    'message' => 'Add-on management requires TaskSession Pro.',
]);
exit;
