<?php
/**
 * Shared filter SQL helpers for admin/staff all-tasks.php (Kanban parity).
 */

if (!function_exists('reports_sql_valid_date')) {
    require_once __DIR__ . '/reports_common_helper.php';
}

if (!function_exists('allTasksListTodayDate')) {
    /** Match Kanban PHP filters (app timezone from lib-initialize, not MySQL CURDATE()). */
    function allTasksListTodayDate() {
        return date('Y-m-d');
    }
}

if (!function_exists('allTasksListStatusFilterSql')) {
    function allTasksListStatusFilterSql($statusFilter, $tableAlias = '') {
        global $database;

        if (!$statusFilter) {
            return null;
        }

        $p = ($tableAlias !== '') ? $tableAlias . '.' : '';
        $escaped = $database->escapeValue($statusFilter);

        if ($statusFilter === 'recurring') {
            require_once __DIR__ . '/task_recurrence_helper.php';
            if (!function_exists('tasksession_recurrence_tables_ready') || !tasksession_recurrence_tables_ready()) {
                return '0 = 1';
            }
            $idCol = $p !== '' ? $p . 'id' : 'id';
            $recCol = $p !== '' ? $p . 'recurrence_id' : 'recurrence_id';
            // Match kanban: spawned/linked via recurrence_id, or active rule root task.
            return "(
                ($recCol IS NOT NULL AND $recCol > 0)
                OR EXISTS (
                    SELECT 1 FROM task_recurrences tr
                    WHERE tr.is_active = 1 AND tr.root_task_id = $idCol
                )
            )";
        }

        if ($statusFilter === 'due_soon') {
            $today = allTasksListTodayDate();
            $maxDate = date('Y-m-d', strtotime('+3 days', strtotime($today)));
            $todayEsc = $database->escapeValue($today);
            $maxEsc = $database->escapeValue($maxDate);

            return "({$p}status != 'done' AND " . reports_sql_valid_date("{$p}due_date") . " AND DATE({$p}due_date) >= '$todayEsc' AND DATE({$p}due_date) <= '$maxEsc')";
        }

        if (in_array($statusFilter, ['overdue', 'due_today', 'task_pro', 'on_time'], true)) {
            $completedExpr = "DATE(CASE WHEN UNIX_TIMESTAMP({$p}completed_at) > 0 THEN {$p}completed_at ELSE {$p}created_at END)";
            $dueExpr = "DATE({$p}due_date)";

            switch ($statusFilter) {
                case 'overdue':
                    return "({$p}status = 'done' AND " . reports_sql_valid_date("{$p}due_date") . " AND $completedExpr > $dueExpr)";
                case 'due_today':
                    $todayEsc = $database->escapeValue(allTasksListTodayDate());
                    return "({$p}status = 'done' AND DATE({$p}due_date) = '$todayEsc')";
                case 'task_pro':
                    return "({$p}status = 'done' AND " . reports_sql_valid_date("{$p}due_date") . " AND $completedExpr < $dueExpr)";
                case 'on_time':
                    return "({$p}status = 'done' AND " . reports_sql_valid_date("{$p}due_date") . " AND $completedExpr = $dueExpr)";
            }
        }

        return "{$p}status = '$escaped'";
    }
}

if (!function_exists('allTasksListDateRangeSql')) {
    function allTasksListDateRangeSql($startDateFilter, $endDateFilter, $tableAlias = 't') {
        global $database;

        if ($startDateFilter === '' && $endDateFilter === '') {
            return null;
        }

        $p = ($tableAlias !== '') ? $tableAlias . '.' : '';

        if ($startDateFilter !== '' && $endDateFilter !== '') {
            $startEsc = $database->escapeValue($startDateFilter);
            $endEsc = $database->escapeValue($endDateFilter);

            return "(
                (" . reports_sql_valid_date("{$p}start_date") . " AND " . reports_sql_valid_date("{$p}due_date") . " AND DATE({$p}start_date) <= '$endEsc' AND DATE({$p}due_date) >= '$startEsc')
                OR (" . reports_sql_valid_date("{$p}start_date") . " AND NOT " . reports_sql_valid_date("{$p}due_date") . " AND DATE({$p}start_date) <= '$endEsc')
                OR (NOT " . reports_sql_valid_date("{$p}start_date") . " AND " . reports_sql_valid_date("{$p}due_date") . " AND DATE({$p}due_date) >= '$startEsc')
            )";
        }

        if ($startDateFilter !== '') {
            $startEsc = $database->escapeValue($startDateFilter);

            return "(
                (" . reports_sql_valid_date("{$p}start_date") . " AND DATE({$p}start_date) >= '$startEsc')
                OR (" . reports_sql_valid_date("{$p}due_date") . " AND DATE({$p}due_date) >= '$startEsc')
            )";
        }

        $endEsc = $database->escapeValue($endDateFilter);

        return "(
            (" . reports_sql_valid_date("{$p}start_date") . " AND DATE({$p}start_date) <= '$endEsc')
            OR (" . reports_sql_valid_date("{$p}due_date") . " AND DATE({$p}due_date) <= '$endEsc')
        )";
    }
}

if (!function_exists('allTasksListBuildStatuses')) {
    function allTasksListBuildStatuses(array $statusLabels, $userId) {
        global $database;

        $defaultIcons = [
            'todo' => 'color-todo-bg',
            'inprogress' => 'color-inprogress-bg',
            'review' => 'color-review-bg',
            'done' => 'color-done-bg',
        ];

        $statuses = [
            'todo' => ['icon' => $defaultIcons['todo'], 'label' => $statusLabels['todo']],
            'inprogress' => ['icon' => $defaultIcons['inprogress'], 'label' => $statusLabels['inprogress']],
            'review' => ['icon' => $defaultIcons['review'], 'label' => $statusLabels['review']],
            'done' => ['icon' => $defaultIcons['done'], 'label' => $statusLabels['done']],
        ];

        $uid = (int) $userId;
        $columnsQuery = "SELECT column_key, custom_name FROM project_columns WHERE project_id = 0 AND (user_id IS NULL OR user_id = $uid) ORDER BY id ASC";
        $columnsResult = $database->query($columnsQuery);
        if ($columnsResult && $database->numRows($columnsResult) > 0) {
            while ($row = $database->fetchArray($columnsResult)) {
                $key = $row['column_key'];
                $icon = isset($defaultIcons[$key]) ? $defaultIcons[$key] : 'text-secondary';
                $statuses[$key] = [
                    'icon' => $icon,
                    'label' => isset($statusLabels[$key]) ? $statusLabels[$key] : $row['custom_name'],
                ];
            }
        }

        return $statuses;
    }
}

if (!function_exists('staffAllTasksScopeSql')) {
    /**
     * SQL scope for staff all-tasks list (mirrors kanban tab rules).
     *
     * @return string|null WHERE fragment without leading AND
     */
    function staffAllTasksScopeSql($tableAlias, $showMyTasks, $showArchive, $userId) {
        $uid = (int) $userId;
        $alias = ($tableAlias !== '') ? rtrim((string) $tableAlias, '.') : 'tasks';

        if (staff_kanban_can_view_all_tasks()) {
            if ($showMyTasks && !$showArchive) {
                $col = $tableAlias !== '' ? $tableAlias . '.assigned_to' : 'assigned_to';
                return "FIND_IN_SET($uid, $col) > 0";
            }
            return null;
        }

        if (!$showMyTasks && !$showArchive && staff_kanban_can_view_created_tasks_tab()) {
            if (!class_exists('Task')) {
                require_once __DIR__ . '/task.php';
            }
            return Task::kanbanCreatedTasksWhere($uid, $alias);
        }

        if ($showArchive) {
            if (!class_exists('Task')) {
                require_once __DIR__ . '/task.php';
            }
            return Task::kanbanStaffArchiveWhere($uid, $alias);
        }

        $col = $tableAlias !== '' ? $tableAlias . '.assigned_to' : 'assigned_to';
        return "FIND_IN_SET($uid, $col) > 0";
    }
}

if (!function_exists('clientAllTasksProjectIds')) {
    function clientAllTasksProjectIds($clientId) {
        global $connect;

        $clientId = (int) $clientId;
        $projectIds = [];
        if (!isset($connect)) {
            return $projectIds;
        }

        $stmt = $connect->prepare('SELECT p_id FROM projects WHERE (c_id = ? OR main_client_id = ? OR FIND_IN_SET(?, c_ids))');
        if (!$stmt) {
            return $projectIds;
        }
        $stmt->bind_param('iii', $clientId, $clientId, $clientId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $projectIds[] = (int) $row['p_id'];
        }
        $stmt->close();

        return $projectIds;
    }
}

if (!function_exists('clientAllTasksScopeSql')) {
    /**
     * SQL scope for client all-tasks list (mirrors client kanban visibility).
     *
     * @return string WHERE fragment without leading AND
     */
    function clientAllTasksScopeSql($tableAlias, $showMyTasks, $clientId, array $projectIds) {
        $p = ($tableAlias !== '') ? $tableAlias . '.' : '';
        $clientId = (int) $clientId;

        if ($showMyTasks) {
            return "({$p}creator_id = $clientId OR {$p}user_id = $clientId)";
        }

        if ($projectIds === []) {
            return "({$p}creator_id = $clientId OR {$p}user_id = $clientId)";
        }

        $ids = implode(',', array_map('intval', $projectIds));
        return "({$p}project_id IN ($ids) OR {$p}creator_id = $clientId OR {$p}user_id = $clientId)";
    }
}
