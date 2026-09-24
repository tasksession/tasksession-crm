<?php
/**
 * Whether the current user may read/save per-context email overrides.
 */

if (!class_exists('ChatEmailPolicy', false) && file_exists(__DIR__ . '/ChatEmailPolicy.php')) {
    require_once __DIR__ . '/ChatEmailPolicy.php';
}

/**
 * Whether Notifications Settings entry points should appear (global toggle + role).
 *
 * @param int $userId
 * @param int $accountStatus
 * @return bool
 */
function chat_email_notifications_entry_allowed($userId, $accountStatus) {
    $st = (int) $accountStatus;
    $s = ChatEmailPolicy::getSettings();
    $g = $s ? ChatEmailPolicy::getGlobalTogglesFromSettings($s) : ChatEmailPolicy::defaultToggles();
    if ($st === 1) {
        return true;
    }
    if ($st === 3) {
        return !empty($g['staff']);
    }
    if ($st === 2) {
        return !empty($g['client']);
    }
    return false;
}

/**
 * none | full | self_staff | self_client
 *
 * @param int $userId
 * @param string $contextType
 * @param int $contextId
 * @return string
 */
function chat_email_notifications_save_mode($userId, $contextType, $contextId) {
    if (empty($userId) || !in_array($contextType, array('project', 'group', 'task'), true) || (int) $contextId <= 0) {
        return 'none';
    }
    if (!class_exists('User', false) && file_exists(__DIR__ . '/../user.php')) {
        require_once __DIR__ . '/../user.php';
    }
    $u = User::findById((int) $userId);
    if (!$u) {
        return 'none';
    }
    $st = (int) $u->accountStatus;
    // Staff/client: per-user prefs only in project and task; group uses mute, not this modal.
    if (in_array($st, array(2, 3), true) && $contextType === 'group') {
        return 'none';
    }
    $s = ChatEmailPolicy::getSettings();
    $g = $s ? ChatEmailPolicy::getGlobalTogglesFromSettings($s) : ChatEmailPolicy::defaultToggles();
    if ($st === 1) {
        if (chat_email_user_can_save_context($userId, $contextType, (int) $contextId)) {
            return 'full';
        }
        return 'none';
    }
    if ($st === 3) {
        if (empty($g['staff'])) {
            return 'none';
        }
        if (chat_email_user_can_save_context($userId, $contextType, (int) $contextId)) {
            return 'self_staff';
        }
        return 'none';
    }
    if ($st === 2) {
        if (empty($g['client'])) {
            return 'none';
        }
        if (chat_email_user_in_context_read($userId, $contextType, (int) $contextId)) {
            return 'self_client';
        }
        return 'none';
    }
    return 'none';
}

function chat_email_user_can_view_settings($userId) {
    if (empty($userId)) {
        return false;
    }
    if (!class_exists('User', false) && file_exists(__DIR__ . '/../user.php')) {
        require_once __DIR__ . '/../user.php';
    }
    $u = User::findById((int) $userId);
    if (!$u) {
        return false;
    }
    $s = (int) $u->accountStatus;
    return ($s === 1 || $s === 3);
}

function chat_email_user_can_save_context($userId, $contextType, $contextId) {
    if (empty($userId) || !in_array($contextType, array('project', 'group', 'task'), true) || (int) $contextId <= 0) {
        return false;
    }
    if (!class_exists('User', false) && file_exists(__DIR__ . '/../user.php')) {
        require_once __DIR__ . '/../user.php';
    }
    $u = User::findById((int) $userId);
    if (!$u) {
        return false;
    }
    $st = (int) $u->accountStatus;
    if ($st === 2) {
        return false;
    }
    if ($st === 1) {
        return true;
    }
    if ($st !== 3) {
        return false;
    }
    if ($contextType === 'group') {
        return chat_email_user_in_group($userId, (int) $contextId);
    }
    if ($contextType === 'project') {
        return chat_email_user_is_project_staff_member($userId, (int) $contextId);
    }
    if ($contextType === 'task') {
        if (!class_exists('Task', false) && file_exists(__DIR__ . '/../task.php')) {
            require_once __DIR__ . '/../task.php';
        }
        $t = Task::findById((int) $contextId);
        if (!$t) {
            return false;
        }
        if (!empty($t->creator_id) && (int) $t->creator_id === (int) $userId) {
            return true;
        }
        if (chat_email_user_in_assigned($userId, $t)) {
            return true;
        }
        if (!empty($t->project_id) && (int) $t->project_id > 0) {
            return chat_email_user_is_project_staff_member($userId, (int) $t->project_id);
        }
    }
    return false;
}

/**
 * @param object $task
 */
function chat_email_user_in_assigned($userId, $task) {
    if (empty($task->assigned_to) || (string) $task->assigned_to === '') {
        return false;
    }
    $u = (int) $userId;
    $a = (string) $task->assigned_to;
    if (function_exists('preg_match')) {
        if (preg_match('/(^|,) *' . $u . ' *(,|$)/', $a) || $a == (string) $u) {
            return true;
        }
    }
    foreach (preg_split('/[,\s]+/', str_replace(' ', ',', $a)) as $x) {
        if (trim($x) !== '' && (int) trim($x) === $u) {
            return true;
        }
    }
    return false;
}

function chat_email_user_is_project_staff_member($userId, $projectId) {
    if (!class_exists('Projects', false) && file_exists(__DIR__ . '/../projects.php')) {
        require_once __DIR__ . '/../projects.php';
    }
    if ($projectId <= 0) {
        return false;
    }
    $p = Projects::findByProjectId((int) $projectId);
    if (!$p) {
        return false;
    }
    $u = (int) $userId;
    if (empty($p->s_ids) || (string) $p->s_ids === '') {
        return false;
    }
    if ((string) $p->s_ids === (string) $u) {
        return true;
    }
    if (function_exists('preg_match') && preg_match('/(^|,) *' . $u . ' *(,|$)/', (string) $p->s_ids)) {
        return true;
    }
    foreach (array_map('trim', explode(',', (string) $p->s_ids)) as $sid) {
        if ($sid !== '' && (int) $sid === $u) {
            return true;
        }
    }
    return false;
}

function chat_email_user_in_group($userId, $groupId) {
    global $database;
    if ($groupId <= 0) {
        return false;
    }
    $g = (int) $groupId;
    $u = (int) $userId;
    $q = $database->query("SELECT id FROM group_chat_members WHERE group_id = {$g} AND user_id = {$u} AND left_at IS NULL LIMIT 1");
    return $q && $database->numRows($q) > 0;
}

function chat_email_user_in_project($userId, $projectId) {
    if (!class_exists('Projects', false) && file_exists(__DIR__ . '/../projects.php')) {
        require_once __DIR__ . '/../projects.php';
    }
    if ($projectId <= 0) {
        return false;
    }
    $p = Projects::findByProjectId((int) $projectId);
    if (!$p) {
        return false;
    }
    $u = (int) $userId;
    if (!empty($p->s_ids) && (strpos($p->s_ids, (string) $u . ',') !== false
        || strpos($p->s_ids, ',' . (string) $u) !== false
        || (string) $p->s_ids === (string) $u
        || preg_match('/(^|,) *' . $u . ' *(,|$)/', (string) $p->s_ids)
    )) {
        return true;
    }
    if (method_exists($p, 'isClient') && $p->isClient($u)) {
        return true;
    }
    if (!empty($p->main_client_id) && (int) $p->main_client_id === $u) {
        return true;
    }
    return false;
}

/**
 * Can this user read notification settings for this context (admin/staff shortcut, or client in context).
 *
 * @param string $ct project|group|task
 * @param int $cid
 * @return bool
 */
function chat_email_user_in_context_read($userId, $ct, $cid) {
    if (chat_email_user_can_view_settings($userId)) {
        return true;
    }
    if (!class_exists('User', false) && file_exists(__DIR__ . '/../user.php')) {
        require_once __DIR__ . '/../user.php';
    }
    $u = User::findById((int) $userId);
    if ($u && (int) $u->accountStatus === 2) {
        if ($ct === 'group') {
            return chat_email_user_in_group($userId, $cid);
        }
        if ($ct === 'project') {
            return chat_email_user_in_project($userId, $cid);
        }
        if ($ct === 'task') {
            if (!class_exists('Task', false) && file_exists(__DIR__ . '/../task.php')) {
                require_once __DIR__ . '/../task.php';
            }
            if (class_exists('Task', false)) {
                $t = Task::findById((int) $cid);
                if ($t && (int) $t->project_id) {
                    return chat_email_user_in_project($userId, (int) $t->project_id);
                }
            }
        }
    }
    return false;
}
