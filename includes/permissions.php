<?php
// includes/permissions.php

if (function_exists('load_user_permissions')) {
    return;
}

// Permission keys:
// project_create, project_edit, project_delete
// milestone_view, milestone_create, milestone_delete, milestone_update
// task_create, task_delete, task_status_update, task_edit, task_assign_members, task_duplicate, task_view_all
// timer_log_view_others, timer_log_edit_own, timer_log_delete_own, timer_log_edit, timer_log_delete, timer_log_show_manual
// staff_create, staff_profile_update, staff_profile_delete, staff_view
// client_create, client_delete, client_profile_update, client_view

function load_user_permissions($role_id, $connect) {
    $permissions = [];
    $res = $connect->query("SELECT permission_key, value FROM role_permissions WHERE role_id = " . intval($role_id));
    while ($row = $res->fetch_assoc()) {
        $permissions[$row['permission_key']] = (int)$row['value'];
    }
    $_SESSION['permissions'] = $permissions;
    $_SESSION['permissions_role_id'] = (int) $role_id;
    return $permissions;
}

function primary_admin_marketing_module_on() {
    static $marketingModuleOn = null;
    if ($marketingModuleOn !== null) {
        return $marketingModuleOn === 1;
    }
    $marketingModuleOn = 0;
    if (!isset($_SESSION['accountStatus']) || (int) $_SESSION['accountStatus'] !== 1) {
        return false;
    }
    global $database;
    if (isset($database)) {
        try {
            $res = $database->query('SELECT module_marketing FROM settings WHERE id = 1 LIMIT 1');
            if ($res && ($row = $res->fetch_assoc()) && !empty($row['module_marketing'])) {
                $marketingModuleOn = 1;
            }
        } catch (Throwable $e) {
            $marketingModuleOn = 0;
        }
    }
    // Free edition / no paid marketing license path.
    if ($marketingModuleOn !== 1
        && function_exists('comon_addon_is_enabled')
        && comon_addon_is_enabled('marketing')) {
        $marketingModuleOn = 1;
    } elseif ($marketingModuleOn !== 1
        && is_readable(__DIR__ . '/addon_module_gate.php')) {
        require_once __DIR__ . '/addon_module_gate.php';
        if (comon_addon_is_enabled('marketing')) {
            $marketingModuleOn = 1;
        }
    }

    return $marketingModuleOn === 1;
}

function primary_admin_ecommerce_module_on() {
    static $ecomModuleOn = null;
    if ($ecomModuleOn !== null) {
        return $ecomModuleOn === 1;
    }
    $ecomModuleOn = 0;
    if (!isset($_SESSION['accountStatus']) || (int) $_SESSION['accountStatus'] !== 1) {
        return false;
    }
    if (!function_exists('comon_ecommerce_module_enabled')) {
        require_once __DIR__ . '/addon_registry.php';
    }
    $ecomModuleOn = comon_ecommerce_module_enabled() ? 1 : 0;

    return $ecomModuleOn === 1;
}

function primary_admin_ai_module_on() {
    static $aiModuleOn = null;
    if ($aiModuleOn !== null) {
        return $aiModuleOn === 1;
    }
    $aiModuleOn = 0;
    if (!isset($_SESSION['accountStatus']) || (int) $_SESSION['accountStatus'] !== 1) {
        return false;
    }
    if (!function_exists('comon_ai_module_enabled')) {
        $gate = __DIR__ . '/ai_module_gate.php';
        if (!is_readable($gate)) {
            return false;
        }
        require_once $gate;
    }
    if (!function_exists('comon_ai_module_enabled')) {
        return false;
    }
    $aiModuleOn = comon_ai_module_enabled() ? 1 : 0;

    return $aiModuleOn === 1;
}

function permissions_session_stale_for_role($connect, $role_id) {
    if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
        return false;
    }
    $role_id = (int) $role_id;
    if ($role_id <= 0) {
        return false;
    }

    // Addon refresh (unchanged): session still has marketing/ecommerce off while role now grants access.
    foreach (['marketing_access', 'ecommerce_access'] as $permKey) {
        if (!array_key_exists($permKey, $_SESSION['permissions']) || (int) $_SESSION['permissions'][$permKey] !== 0) {
            continue;
        }
        $esc = $connect->real_escape_string($permKey);
        $res = $connect->query(
            "SELECT value FROM role_permissions WHERE role_id = {$role_id} AND permission_key = '{$esc}' LIMIT 1"
        );
        if ($res && ($row = $res->fetch_assoc()) && (int) ($row['value'] ?? 0) === 1) {
            return true;
        }
    }

    // General role edit refresh (attendance, etc.): reload when any granted permission differs from DB.
    $dbPerms = array();
    $res = $connect->query('SELECT permission_key, value FROM role_permissions WHERE role_id = ' . $role_id);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            if ((int) ($row['value'] ?? 0) === 1) {
                $dbPerms[(string) $row['permission_key']] = 1;
            }
        }
        $res->free();
    }

    $sessionPerms = $_SESSION['permissions'];
    foreach ($sessionPerms as $key => $val) {
        $sessionOn = (int) $val === 1;
        $dbOn = !empty($dbPerms[$key]);
        if ($sessionOn !== $dbOn) {
            return true;
        }
    }
    foreach ($dbPerms as $key => $val) {
        if (!isset($sessionPerms[$key]) || (int) $sessionPerms[$key] !== 1) {
            return true;
        }
    }

    return false;
}

function has_permission($key) {
    $perms = (isset($_SESSION['permissions']) && is_array($_SESSION['permissions']))
        ? $_SESSION['permissions']
        : [];
    // Primary admin module bypass wins over stale session zeros (e.g. after marketing addon activation).
    if (strpos((string) $key, 'marketing_') === 0 && primary_admin_marketing_module_on()) {
        return true;
    }
    if (strpos((string) $key, 'ecommerce_') === 0 && primary_admin_ecommerce_module_on()) {
        return true;
    }
    if (strpos((string) $key, 'ai_') === 0 && primary_admin_ai_module_on()) {
        return true;
    }
    if (!empty($perms[$key])) {
        return true;
    }
    if (array_key_exists($key, $perms) && (int) $perms[$key] === 0) {
        return false;
    }
    // Own time logs: no DB row yet → allow (matches pre–split behavior until roles are saved).
    if (
        ($key === 'timer_log_edit_own' || $key === 'timer_log_delete_own' || $key === 'timer_log_show_manual')
        && !array_key_exists($key, $perms)
    ) {
        return true;
    }
    return false;
}

/**
 * Whether staff may view/access a task (sidebar, activity, files, timer, subtasks).
 * Admins/clients use separate checks at call sites.
 */
function staff_can_view_task($task, $userId) {
    if (!$task || (int) $userId <= 0) {
        return false;
    }
    $userId = (int) $userId;

    if (has_permission('task_view_all')) {
        return true;
    }

    $creatorId = (int) ($task->creator_id ?? 0);
    $ownerId = (int) ($task->user_id ?? 0);
    if ($creatorId === $userId || $ownerId === $userId) {
        return true;
    }

    if (method_exists($task, 'isAssignedTo') && $task->isAssignedTo($userId)) {
        return true;
    }

    if (!empty($task->project_id)) {
        if (!class_exists('projects')) {
            require_once __DIR__ . '/projects.php';
        }
        $project = projects::findByProjectId((int) $task->project_id);
        if ($project) {
            $projectStaffIds = array_filter(array_map('trim', explode(',', (string) $project->s_ids)));
            if (has_permission('project_view_all') || in_array((string) $userId, $projectStaffIds, true)) {
                return true;
            }
        }
    }

    return false;
}

/** Staff kanban / all-tasks: may open "All Tasks" and see every task. */
function staff_kanban_can_view_all_tasks(): bool
{
    return has_permission('task_view_all');
}

/** Staff kanban / all-tasks: "All Tasks" tab shows tasks this user created. */
function staff_kanban_can_view_created_tasks_tab(): bool
{
    return has_permission('task_create') || has_permission('task_assign_members');
}

/** Whether the All Tasks tab should appear for staff. */
function staff_kanban_show_all_tasks_tab(): bool
{
    return staff_kanban_can_view_all_tasks() || staff_kanban_can_view_created_tasks_tab();
}

/**
 * Staff calendar task scope: my | all | created (mirrors kanban tabs).
 *
 * @return 'my'|'all'|'created'
 */
function staff_calendar_resolve_task_scope(bool $showMyTasks): string
{
    if ($showMyTasks) {
        return 'my';
    }
    if (staff_kanban_can_view_all_tasks()) {
        return 'all';
    }
    if (staff_kanban_can_view_created_tasks_tab()) {
        return 'created';
    }
    return 'my';
}

function staff_calendar_task_row_in_scope(array $row, int $userId, string $scope): bool
{
    if ($scope === 'all') {
        return true;
    }
    if ($scope === 'created') {
        return (int) ($row['creator_id'] ?? 0) === $userId || (int) ($row['user_id'] ?? 0) === $userId;
    }
    $assignedRaw = isset($row['assigned_to']) ? (string) $row['assigned_to'] : '';
    $assignedList = array_filter(array_map('trim', explode(',', $assignedRaw)));
    return in_array((string) $userId, $assignedList, true);
}

function staff_calendar_task_in_scope($task, int $userId, string $scope): bool
{
    if ($scope === 'all') {
        return true;
    }
    if ($scope === 'created') {
        return (int) ($task->creator_id ?? 0) === $userId || (int) ($task->user_id ?? 0) === $userId;
    }
    return method_exists($task, 'isAssignedTo') && $task->isAssignedTo($userId);
}

function ensure_user_permissions($connect) {
    if (!isset($_SESSION['userId'])) {
        return;
    }
    $user_id = (int) $_SESSION['userId'];
    $res = $connect->query("SELECT role_id FROM users WHERE id = " . $user_id . " LIMIT 1");
    if (!$res || !($row = $res->fetch_assoc())) {
        return;
    }
    $role_id = (int) ($row['role_id'] ?? 0);
    if ($role_id <= 0 && isset($_SESSION['accountStatus']) && (int) $_SESSION['accountStatus'] === 1) {
        $role_id = 1;
    }
    if (!function_exists('comon_maybe_seed_ecommerce_permissions')) {
        require_once __DIR__ . '/addon_registry.php';
    }
    if (comon_maybe_seed_ecommerce_permissions($connect, $role_id)) {
        unset($_SESSION['permissions'], $_SESSION['permissions_role_id']);
    }

    if (
        !empty($_SESSION['permissions'])
        && is_array($_SESSION['permissions'])
        && isset($_SESSION['permissions_role_id'])
        && (int) $_SESSION['permissions_role_id'] === $role_id
        && !permissions_session_stale_for_role($connect, $role_id)
    ) {
        return;
    }
    load_user_permissions($role_id, $connect);
    $_SESSION['permissions_role_id'] = $role_id;
} 