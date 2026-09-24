<?php
/**
 * AJAX: setup guide actions (super admin only).
 */
define('CRM_LIGHTWEIGHT_INIT', true);

if (ob_get_level() === 0) {
    ob_start();
}
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/includes/lib-initialize.php';
require_once dirname(__DIR__) . '/includes/setup_guide.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=utf-8');

/**
 * @param array<string, mixed> $payload
 */
function setup_guide_ajax_exit(array $payload, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload);
    exit;
}

if (!isset($session) || !$session->isLoggedIn()) {
    setup_guide_ajax_exit(array('status' => 'error', 'message' => 'Authentication required'), 401);
}

if (!setup_guide_is_super_admin()) {
    setup_guide_ajax_exit(array('status' => 'error', 'message' => 'Forbidden'), 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    setup_guide_ajax_exit(array('status' => 'error', 'message' => 'Invalid request method'), 405);
}

$action = trim((string) ($_POST['action'] ?? ''));

switch ($action) {
    case 'minimize':
        setup_guide_minimize();
        setup_guide_ajax_exit(array('status' => 'ok', 'state' => setup_guide_state()));

    case 'expand':
        setup_guide_expand();
        setup_guide_ajax_exit(array('status' => 'ok', 'state' => setup_guide_state()));

    case 'dismiss_forever':
        setup_guide_dismiss_forever();
        setup_guide_ajax_exit(array('status' => 'ok', 'show' => false));

    case 'remind_next_login':
        setup_guide_remind_next_login();
        setup_guide_ajax_exit(array('status' => 'ok', 'show' => false));

    case 'ack_celebration':
        setup_guide_ack_celebration();
        setup_guide_ajax_exit(array('status' => 'ok', 'show' => false));

    case 'mark_step':
        $step = trim((string) ($_POST['step'] ?? ''));
        if ($step === '' || !isset(setup_guide_steps()[$step])) {
            setup_guide_ajax_exit(array('status' => 'error', 'message' => 'Invalid step'), 400);
        }
        setup_guide_mark_visit($step);
        setup_guide_ajax_exit(array('status' => 'ok', 'state' => setup_guide_state()));

    default:
        setup_guide_ajax_exit(array('status' => 'error', 'message' => 'Unknown action'), 400);
}
