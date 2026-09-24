<?php
/**
 * JSON API: action=get_settings|save_settings
 * Include from ajax/chat_email_context_api.php after session is ready.
 */
header('Content-Type: application/json; charset=utf-8');

$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : 'get_settings';

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/chat_email_permissions.php';
if (file_exists(__DIR__ . '/../csrf-middleware.php')) {
    require_once __DIR__ . '/../csrf-middleware.php';
}

if (empty($session) || !is_object($session) || !method_exists($session, 'isLoggedIn') || !($session->isLoggedIn())) {
    echo json_encode(array('ok' => false, 'message' => 'Not logged in'));
    exit;
}

$uid = (int) $session->userId;

if ($action === 'get_settings') {
    if (!isset($_GET['context_type'], $_GET['context_id'])) {
        echo json_encode(array('ok' => false, 'message' => 'Missing parameters'));
        exit;
    }
    $ct = preg_replace('/[^a-z]/', '', (string) $_GET['context_type']);
    if (!in_array($ct, array('project', 'group', 'task'), true)) {
        echo json_encode(array('ok' => false, 'message' => 'Invalid context'));
        exit;
    }
    $cid = (int) $_GET['context_id'];
    if (!chat_email_user_in_context_read($uid, $ct, $cid)) {
        echo json_encode(array('ok' => false, 'message' => 'Access denied'));
        exit;
    }
    if (!class_exists('User', false) && file_exists(__DIR__ . '/../user.php')) {
        require_once __DIR__ . '/../user.php';
    }
    $authUser = User::findById($uid);
    $acct = $authUser ? (int) $authUser->accountStatus : 0;
    if (!chat_email_notifications_entry_allowed($uid, $acct)) {
        echo json_encode(array('ok' => false, 'message' => 'Notifications settings are not available for your role.'));
        exit;
    }
    if (in_array($acct, array(2, 3), true) && $ct === 'group') {
        echo json_encode(array('ok' => false, 'message' => 'Email notifications for groups are managed from mute settings.'));
        exit;
    }
    $saveMode = chat_email_notifications_save_mode($uid, $ct, $cid);
    $uiMode = ($saveMode === 'full') ? 'full' : (($saveMode === 'self_staff' || $saveMode === 'self_client') ? 'self' : 'readonly');
    $canResetGlobal = ($saveMode === 'full' && $acct === 1) ? 1 : 0;
    $selfKey = null;
    $hideClientRow = 0;
    if ($saveMode === 'self_staff') {
        $selfKey = 'staff';
    } elseif ($saveMode === 'self_client') {
        $selfKey = 'client';
    }
    if ($ct === 'task') {
        if (!class_exists('Task', false) && file_exists(__DIR__ . '/../task.php')) {
            require_once __DIR__ . '/../task.php';
        }
        if (class_exists('Task', false)) {
            $task = Task::findById((int) $cid);
            if ($task && (int) ($task->project_id ?? 0) <= 0) {
                // Internal task has no clients.
                $hideClientRow = 1;
            }
        }
    }
    $s = ChatEmailPolicy::getSettings();
    $g = $s ? ChatEmailPolicy::getGlobalTogglesFromSettings($s) : ChatEmailPolicy::defaultToggles();
    $row = ChatEmailContextRepository::getRow($ct, $cid);
    $o = null;
    if ($row) {
        $o = array(
            'admin'   => (array_key_exists('o_admin', $row) && $row['o_admin'] !== null) ? (int) $row['o_admin'] === 1   : null,
            'staff'   => (array_key_exists('o_staff', $row) && $row['o_staff'] !== null) ? (int) $row['o_staff'] === 1   : null,
            'client'  => (array_key_exists('o_client', $row) && $row['o_client'] !== null) ? (int) $row['o_client'] === 1  : null,
            'mention' => (array_key_exists('o_mention', $row) && $row['o_mention'] !== null) ? (int) $row['o_mention'] === 1 : null,
        );
    }
    $eff = ChatEmailPolicy::getEffectiveToggles($ct, $cid);
    $canSave = ($saveMode !== 'none') ? 1 : 0;
    $userEmailEnabled = 1;
    if ($saveMode === 'self_staff' || $saveMode === 'self_client') {
        $ur = ChatEmailUserPreferenceRepository::getRow($ct, $cid, $uid);
        if ($ur && array_key_exists('email_enabled', $ur) && $ur['email_enabled'] !== null) {
            $userEmailEnabled = (int) $ur['email_enabled'] === 1 ? 1 : 0;
        }
    }
    echo json_encode(array(
        'ok' => true,
        'global' => $g,
        'override' => $o,
        'effective' => $eff,
        'can_save' => $canSave,
        'ui_mode' => $uiMode,
        'self_key' => $selfKey,
        'hide_client_row' => $hideClientRow,
        'save_mode' => $saveMode,
        'can_reset_global' => $canResetGlobal,
        'user_email_enabled' => $userEmailEnabled,
    ));
    exit;
}

if ($action === 'save_settings') {
    if (function_exists('csrf_require_for_request')) {
        csrf_require_for_request('json');
    }
    $raw = function_exists('csrf_request_body_raw') ? csrf_request_body_raw() : (string) file_get_contents('php://input');
    $body = $raw ? json_decode($raw, true) : null;
    if (!is_array($body)) {
        $body = $_POST;
    }
    $ct = isset($body['context_type']) ? (string) $body['context_type'] : '';
    if (!in_array($ct, array('project', 'group', 'task'), true)) {
        echo json_encode(array('ok' => false, 'message' => 'Invalid context'));
        exit;
    }
    $cid = isset($body['context_id']) ? (int) $body['context_id'] : 0;
    if ($cid <= 0) {
        echo json_encode(array('ok' => false, 'message' => 'Invalid id'));
        exit;
    }
    if (!chat_email_user_in_context_read($uid, $ct, $cid)) {
        echo json_encode(array('ok' => false, 'message' => 'Access denied'));
        exit;
    }
    if (!class_exists('User', false) && file_exists(__DIR__ . '/../user.php')) {
        require_once __DIR__ . '/../user.php';
    }
    $authUser = User::findById($uid);
    $acct = $authUser ? (int) $authUser->accountStatus : 0;
    if (!chat_email_notifications_entry_allowed($uid, $acct)) {
        echo json_encode(array('ok' => false, 'message' => 'Notifications settings are not available for your role.'));
        exit;
    }
    if (in_array($acct, array(2, 3), true) && $ct === 'group') {
        echo json_encode(array('ok' => false, 'message' => 'Access denied'));
        exit;
    }
    $saveMode = chat_email_notifications_save_mode($uid, $ct, $cid);
    if ($saveMode === 'none') {
        echo json_encode(array('ok' => false, 'message' => 'Access denied'));
        exit;
    }
    if ($saveMode === 'full') {
        if (!empty($body['use_system_defaults'])) {
            if (!ChatEmailContextRepository::deleteRow($ct, $cid)) {
                echo json_encode(array('ok' => false, 'message' => 'Could not clear overrides.'));
                exit;
            }
        } else {
            $a = _chat_api_bool_or_null($body, 'o_admin');
            $stf = _chat_api_bool_or_null($body, 'o_staff');
            $c = _chat_api_bool_or_null($body, 'o_client');
            $m = _chat_api_bool_or_null($body, 'o_mention');
            if (!ChatEmailContextRepository::upsert($ct, $cid, $a, $stf, $c, $m)) {
                echo json_encode(array('ok' => false, 'message' => 'Could not save. Ensure the database migration (chat_email_context) is applied.'));
                exit;
            }
        }
    } elseif ($saveMode === 'self_staff' || $saveMode === 'self_client') {
        if (!array_key_exists('email_enabled', $body)) {
            echo json_encode(array('ok' => false, 'message' => 'Missing email preference.'));
            exit;
        }
        $be = $body['email_enabled'];
        $en = ($be === true || $be === 1 || $be === '1') ? 1 : 0;
        if (!ChatEmailUserPreferenceRepository::upsert($ct, $cid, $uid, $en)) {
            echo json_encode(array('ok' => false, 'message' => 'Could not save. Ensure user preference table is installed (chat_email_user_preference).'));
            exit;
        }
    }
    $eff = ChatEmailPolicy::getEffectiveToggles($ct, $cid);
    $userEmailEnabled = 1;
    if ($saveMode === 'self_staff' || $saveMode === 'self_client') {
        $ur = ChatEmailUserPreferenceRepository::getRow($ct, $cid, $uid);
        if ($ur && array_key_exists('email_enabled', $ur) && $ur['email_enabled'] !== null) {
            $userEmailEnabled = (int) $ur['email_enabled'] === 1 ? 1 : 0;
        }
    }
    echo json_encode(array('ok' => true, 'effective' => $eff, 'user_email_enabled' => $userEmailEnabled));
    exit;
}

echo json_encode(array('ok' => false, 'message' => 'Unknown action'));
exit;

function _chat_api_bool_or_null($b, $k) {
    if (!array_key_exists($k, $b)) {
        return null;
    }
    if ($b[$k] === null || $b[$k] === 'inherit' || (string) $b[$k] === '') {
        return null;
    }
    if ($b[$k] === true) {
        return 1;
    }
    if ($b[$k] === false) {
        return 0;
    }
    $n = (int) $b[$k];
    return $n ? 1 : 0;
}
