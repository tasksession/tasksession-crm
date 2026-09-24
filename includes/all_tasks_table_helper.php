<?php
/**
 * All tasks table — shared markup helpers (admin/staff all-tasks, mega search).
 */

if (!function_exists('allTasksTableUserLastNameSelectSql')) {
    /**
     * Schema-aware users last-name SELECT fragment (last_name, legacy lastName, or empty).
     */
    function allTasksTableUserLastNameSelectSql(string $alias = 'u', string $as = 'client_last_name'): string
    {
        static $cache = [];
        $key = $alias . '|' . $as;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        global $connect;
        $expr = "''";
        $aliasSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($aliasSafe === '') {
            $aliasSafe = 'u';
        }

        if (isset($connect) && $connect instanceof mysqli && function_exists('crm_db_column_exists')) {
            if (crm_db_column_exists($connect, 'users', 'last_name')) {
                $expr = $aliasSafe . '.last_name';
            } elseif (crm_db_column_exists($connect, 'users', 'lastName')) {
                $expr = $aliasSafe . '.lastName';
            }
        }

        $asSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $as);
        if ($asSafe === '') {
            $asSafe = 'client_last_name';
        }
        $cache[$key] = $expr . ' AS ' . $asSafe;
        return $cache[$key];
    }
}

if (!function_exists('allTasksTableSelectSql')) {
    function allTasksTableSelectSql(): string
    {
        return "t.*,
            CASE WHEN t.project_id > 0 THEN p.project_title ELSE 'Internal Task' END AS project_title,
            CASE WHEN t.project_id > 0 THEN p.p_id ELSE 0 END AS p_id,
            p.c_id AS project_client_id,
            p.main_client_id AS project_main_client_id,
            p.c_ids AS project_c_ids,
            u.firstName AS client_first_name,
            " . allTasksTableUserLastNameSelectSql('u', 'client_last_name') . ",
            u.company AS client_company,
            cp.filename AS client_image,
            (SELECT COUNT(*) FROM subtasks st WHERE st.parent_task_id = t.id) AS subtask_total,
            (SELECT COUNT(*) FROM subtasks st WHERE st.parent_task_id = t.id AND st.status = 'done') AS subtask_done";
    }
}

if (!function_exists('allTasksTableJoinSql')) {
    function allTasksTableJoinSql(): string
    {
        return "LEFT JOIN projects p ON t.project_id = p.p_id
            LEFT JOIN users u ON p.c_id = u.id
            LEFT JOIN profile_pics cp ON cp.fkUserId = u.id";
    }
}

if (!function_exists('allTasksTableProgressPercent')) {
    function allTasksTableProgressPercent(array $task): int
    {
        $total = isset($task['subtask_total']) ? (int) $task['subtask_total'] : 0;
        $done = isset($task['subtask_done']) ? (int) $task['subtask_done'] : 0;
        if ($total > 0) {
            return (int) round(($done / $total) * 100);
        }
        $map = [
            'todo' => 0,
            'inprogress' => 40,
            'review' => 60,
            'done' => 100,
        ];
        $status = (string) ($task['status'] ?? 'todo');
        return $map[$status] ?? 0;
    }
}

if (!function_exists('allTasksTableProgressBarColor')) {
    function allTasksTableProgressBarColor(int $percent): string
    {
        if ($percent < 30) {
            return '#f66';
        }
        if ($percent < 70) {
            return '#f9b233';
        }
        return '#4caf50';
    }
}

if (!function_exists('allTasksTablePerformanceLabel')) {
    function allTasksTablePerformanceLabel(array $task, array $lang): string
    {
        if (empty($task['due_date'])) {
            return '';
        }
        $dueTimestamp = is_numeric($task['due_date']) ? (int) $task['due_date'] : strtotime((string) $task['due_date']);
        if (!$dueTimestamp) {
            return '';
        }
        $dueDateOnly = strtotime(date('Y-m-d', $dueTimestamp));
        $today = strtotime('today');

        if (($task['status'] ?? '') === 'done' && !empty($task['completed_at'])) {
            $completedTimestamp = is_numeric($task['completed_at']) ? (int) $task['completed_at'] : strtotime((string) $task['completed_at']);
            $completedDateOnly = strtotime(date('Y-m-d', $completedTimestamp));
            if ($completedDateOnly < $dueDateOnly) {
                return (string) ($lang['Task Pro'] ?? 'Task Pro');
            }
            if ($completedDateOnly === $dueDateOnly) {
                return (string) ($lang['On Time'] ?? 'On Time');
            }
            return (string) ($lang['Overdue'] ?? 'Overdue');
        }

        if ($dueDateOnly < $today) {
            return (string) ($lang['Overdue'] ?? 'Overdue');
        }
        if ($dueDateOnly === $today) {
            return (string) ($lang['Due Today'] ?? 'Due Today');
        }
        return '-';
    }
}

if (!function_exists('allTasksTableProgressCellHtml')) {
    function allTasksTableProgressCellHtml(array $task, array $lang): string
    {
        $percent = allTasksTableProgressPercent($task);
        $color = allTasksTableProgressBarColor($percent);
        $perfLabel = allTasksTablePerformanceLabel($task, $lang);
        $leftHtml = '';
        if ($perfLabel !== '') {
            $leftClass = $perfLabel === '-' ? 'text-muted' : '';
            $leftHtml = '<span class="' . $leftClass . '">' . htmlspecialchars($perfLabel, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $meta = '<div class="mb-1 d-flex col-gap-5 font-size-12">' . $leftHtml
            . '<span class="text-align-right flex-grow">' . (int) $percent . '%</span></div>';
        return '<td class="tbl-tasks extra-height">'
            . $meta
            . '<div class="progress" style="height:8px;">'
            . '<div class="progress-bar" role="progressbar" style="width:' . $percent . '%;background:' . $color . ';" aria-valuenow="' . $percent . '" aria-valuemin="0" aria-valuemax="100"></div>'
            . '</div></td>';
    }
}

if (!function_exists('allTasksTableClientDisplayName')) {
    function allTasksTableClientDisplayName($clientUser): string
    {
        if (!$clientUser) {
            return '';
        }
        $company = trim((string) ($clientUser->company ?? ''));
        if ($company !== '') {
            return $company;
        }
        $name = trim((string) ($clientUser->firstName ?? '') . ' ' . crm_user_last_name($clientUser));
        return $name !== '' ? $name : trim((string) ($clientUser->firstName ?? ''));
    }
}

if (!function_exists('allTasksTableProjectClients')) {
    /**
     * @return array<int,object>
     */
    function allTasksTableProjectClients(array $task): array
    {
        if (empty($task['project_id']) || (int) $task['project_id'] === 0) {
            return [];
        }
        $mainClientId = (int) ($task['project_main_client_id'] ?? 0);
        if ($mainClientId <= 0) {
            $mainClientId = (int) ($task['project_client_id'] ?? 0);
        }
        $allClients = [];
        if ($mainClientId > 0) {
            $mainClient = User::findById($mainClientId);
            if ($mainClient) {
                $allClients[$mainClientId] = $mainClient;
            }
        }
        if (!empty($task['project_c_ids'])) {
            $clientIds = array_filter(array_map('trim', explode(',', (string) $task['project_c_ids'])));
            foreach ($clientIds as $clientId) {
                $clientId = (int) $clientId;
                if ($clientId <= 0 || isset($allClients[$clientId])) {
                    continue;
                }
                $client = User::findById($clientId);
                if ($client) {
                    $allClients[$clientId] = $client;
                }
            }
        }
        return array_values($allClients);
    }
}

if (!function_exists('allTasksTableClientCellHtml')) {
    function allTasksTableClientCellHtml(array $task, array $lang): string
    {
        $internal = htmlspecialchars($lang['Internal Task'] ?? 'Internal Task', ENT_QUOTES, 'UTF-8');
        $allClients = allTasksTableProjectClients($task);
        if (count($allClients) === 0) {
            return '<td class="clients-rpt" style="text-align: left;"><span class="text-muted badge">' . $internal . '</span></td>';
        }
        if (count($allClients) === 1) {
            $client = $allClients[0];
            $displayName = allTasksTableClientDisplayName($client);
            $displayEsc = htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8');
            $avatar = getUserAvatarHtml(
                $client->id,
                $client->firstName,
                crm_user_last_name($client),
                36,
                36,
                'img-fluid rounded-circle',
                $displayName
            );
            return '<td class="clients-rpt" style="text-align: left;">'
                . '<div class="d-flex align-items-center col-gap">'
                . '<div class="user-box" style="margin-left:0;" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $displayEsc . '">' . $avatar . '</div>'
                . '<span class="client-name">' . $displayEsc . '</span>'
                . '</div></td>';
        }
        $maxDisplay = 3;
        $extraCount = count($allClients) - $maxDisplay;
        $html = '<td class="clients-rpt" style="text-align: left;"><div class="d-flex align-items-center">';
        foreach ($allClients as $index => $client) {
            if ($index >= $maxDisplay) {
                break;
            }
            $fullName = trim((string) ($client->firstName ?? '') . ' ' . crm_user_last_name($client));
            $tip = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $html .= '<div class="user-box" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $tip . '">';
            $html .= getUserAvatarHtml(
                $client->id,
                $client->firstName,
                crm_user_last_name($client),
                36,
                36,
                'img-fluid rounded-circle',
                $client->firstName
            );
            $html .= '</div>';
        }
        if ($extraCount > 0) {
            $html .= '<div class="plus-more">+' . (int) $extraCount . '</div>';
        }
        $html .= '</div></td>';
        return $html;
    }
}

if (!function_exists('allTasksTableFormatDate')) {
    function allTasksTableFormatDate($value): string
    {
        if ($value === null || $value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return '';
        }
        $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if (!$ts) {
            return '';
        }
        return date('M j, Y', $ts);
    }
}

if (!function_exists('allTasksTableDatesCellHtml')) {
    function allTasksTableDatesCellHtml(array $task, array $lang): string
    {
        $startRaw = !empty($task['start_date']) ? $task['start_date'] : ($task['created_at'] ?? '');
        $start = allTasksTableFormatDate($startRaw);
        $due = allTasksTableFormatDate($task['due_date'] ?? '');
        $noDeadline = htmlspecialchars($lang['No deadline'] ?? 'No deadline', ENT_QUOTES, 'UTF-8');
        if (function_exists('ts_icon_inline')) {
            $calIcon = ts_icon_inline('calendar', 'all-tasks-date-ico');
        } elseif (function_exists('ts_icon')) {
            $calIcon = ts_icon('calendar', 'all-tasks-date-ico');
        } else {
            $calIcon = '';
        }
        $startHtml = $start !== '' ? htmlspecialchars($start, ENT_QUOTES, 'UTF-8') : '-';
        if ($due !== '') {
            $dueTs = is_numeric($task['due_date']) ? (int) $task['due_date'] : strtotime((string) $task['due_date']);
            $dueDateOnly = strtotime(date('Y-m-d', $dueTs));
            $today = strtotime('today');
            $dueClass = $dueDateOnly < $today ? 'text-danger' : ($dueDateOnly === $today ? 'color-review' : '');
            $dueHtml = '<span class="all-tasks-due-date ' . $dueClass . '">' . htmlspecialchars($due, ENT_QUOTES, 'UTF-8') . '</span>';
        } else {
            $dueHtml = '<span class="text-muted">' . $noDeadline . '</span>';
        }
        return '<td class="all-tasks-dates-cell">'
            . '<div class="d-flex align-items-center col-gap">'
            . ($calIcon !== '' ? '<span class="all-tasks-date-ico-wrap">' . $calIcon . '</span>' : '')
            . '<div class="all-tasks-date-stack"><div class="all-tasks-start-date">' . $startHtml . '</div><div>' . $dueHtml . '</div></div>'
            . '</div></td>';
    }
}

if (!function_exists('allTasksTableStatusDotClass')) {
    function allTasksTableStatusDotClass(string $status): string
    {
        $map = [
            'todo' => 'color-todo',
            'inprogress' => 'color-inprogress',
            'review' => 'color-review',
            'done' => 'color-done',
        ];
        return $map[$status] ?? 'color-todo';
    }
}

if (!function_exists('allTasksTableStatusCellHtml')) {
    function allTasksTableStatusCellHtml(array $task, array $statusLabels, array $statusColors, bool $canUpdateStatus): string
    {
        $status = (string) ($task['status'] ?? 'todo');
        $labelRaw = (string) ($statusLabels[$status] ?? $status);
        $label = htmlspecialchars($labelRaw, ENT_QUOTES, 'UTF-8');
        $labelTitle = htmlspecialchars($labelRaw, ENT_QUOTES, 'UTF-8');
        $badgeClass = $statusColors[$status] ?? 'todo todo-bg-op';
        $dotClass = allTasksTableStatusDotClass($status);
        $taskId = (int) ($task['id'] ?? 0);
        if (!$canUpdateStatus) {
            return '<td class="all-tasks-status-cell"><span class="badge status all-tasks-status-pill ' . htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') . '">'
                . '<i class="dots ' . htmlspecialchars($dotClass, ENT_QUOTES, 'UTF-8') . '"></i>'
                . '<span class="all-tasks-status-label" title="' . $labelTitle . '">' . $label . '</span></span></td>';
        }
        $items = '';
        foreach ($statusLabels as $key => $name) {
            $active = $key === $status ? ' active' : '';
            $items .= '<li><button type="button" class="dropdown-item all-tasks-status-option' . $active . '" data-task-id="' . $taskId . '" data-status="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</button></li>';
        }
        $chevron = function_exists('ts_icon') ? ts_icon('chevron-down', 'all-tasks-status-chevron') : '';
        return '<td class="all-tasks-status-cell">'
            . '<div class="dropdown all-tasks-status-dd">'
            . '<button type="button" class="badge status all-tasks-status-pill dropdown-toggle ' . htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="dropdown" aria-expanded="false" data-task-id="' . $taskId . '">'
            . '<i class="dots ' . htmlspecialchars($dotClass, ENT_QUOTES, 'UTF-8') . '"></i>'
            . '<span class="all-tasks-status-label" title="' . $labelTitle . '">' . $label . '</span>'
            . $chevron
            . '</button>'
            . '<ul class="dropdown-menu">' . $items . '</ul>'
            . '</div></td>';
    }
}

if (!function_exists('allTasksTableActionsMenuItemsHtml')) {
    function allTasksTableActionsMenuItemsHtml(
        array $task,
        array $lang,
        string $allTasksLinkEsc,
        string $allTasksCloneEsc,
        string $allTasksRedirectUri,
        bool $showArchive,
        string $tsMenuIco,
        bool $canArchive,
        bool $canDelete,
        string $itemClass = 'dropdown-item d-flex align-items-center',
        bool $canEdit = true,
        bool $canDuplicate = true
    ): string {
        $taskId = (int) ($task['id'] ?? 0);
        $redirectEnc = urlencode($allTasksRedirectUri);
        $itemClassAttr = $itemClass !== '' ? ' class="' . htmlspecialchars($itemClass, ENT_QUOTES, 'UTF-8') . '"' : '';
        $dangerClass = $itemClass !== '' ? ' class="' . htmlspecialchars(trim($itemClass . ' text-danger'), ENT_QUOTES, 'UTF-8') . '"' : ' class="text-danger"';

        $menu = '<li><a' . $itemClassAttr . ' href="#" onclick="openTaskSidebar(' . $taskId . '); return false;">'
            . ts_icon('eye', $tsMenuIco) . htmlspecialchars($lang['View Task'] ?? 'View Task', ENT_QUOTES, 'UTF-8') . '</a></li>';
        if ($canEdit) {
            $menu .= '<li><a' . $itemClassAttr . ' href="' . $allTasksLinkEsc . 'edit_task?id=' . $taskId . '">'
                . ts_icon('edit', $tsMenuIco) . htmlspecialchars($lang['Edit Task'] ?? 'Edit Task', ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
        if ($canDuplicate) {
            $menu .= '<li><a' . $itemClassAttr . ' href="' . $allTasksCloneEsc . 'clone-task.php?id=' . $taskId . '&redirect=' . $redirectEnc . '">'
                . ts_icon('duplicate', $tsMenuIco) . htmlspecialchars($lang['Clone Task'] ?? 'Clone Task', ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
        if (!empty($task['project_id']) && (int) $task['project_id'] > 0) {
            $pid = (int) $task['project_id'];
            $menu .= '<li><a' . $itemClassAttr . ' href="' . $allTasksLinkEsc . 'overview?projectId=' . $pid . '">'
                . ts_icon('info', $tsMenuIco) . htmlspecialchars($lang['View Project'] ?? 'View Project', ENT_QUOTES, 'UTF-8') . '</a></li>'
                . '<li><a' . $itemClassAttr . ' href="' . $allTasksLinkEsc . 'task?projectId=' . $pid . '">'
                . ts_icon('document-text', $tsMenuIco) . htmlspecialchars($lang['Project Board'] ?? 'Project Board', ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
        if ($canArchive) {
            if ($showArchive) {
                $menu .= '<li><a' . $itemClassAttr . ' href="#" onclick="unarchiveTask(' . $taskId . '); return false;">'
                    . ts_icon('restore', $tsMenuIco) . htmlspecialchars($lang['Unarchive Task'] ?? 'Unarchive Task', ENT_QUOTES, 'UTF-8') . '</a></li>';
            } else {
                $menu .= '<li><a' . $itemClassAttr . ' href="#" onclick="archiveTask(' . $taskId . '); return false;">'
                    . ts_icon('archive', $tsMenuIco) . htmlspecialchars($lang['Archive Task'] ?? 'Archive Task', ENT_QUOTES, 'UTF-8') . '</a></li>';
            }
        }
        if ($canDelete) {
            $menu .= '<li><a' . $dangerClass . ' href="#" onclick="deleteTask(' . $taskId . '); return false;">'
                . ts_icon('delete', $tsMenuIco) . htmlspecialchars($lang['Delete Task'] ?? 'Delete Task', ENT_QUOTES, 'UTF-8') . '</a></li>';
        }
        return $menu;
    }
}

if (!function_exists('allTasksTableActionsCellHtml')) {
    function allTasksTableActionsCellHtml(
        array $task,
        array $lang,
        string $allTasksLinkEsc,
        string $allTasksCloneEsc,
        string $allTasksRedirectUri,
        bool $showArchive,
        string $tsMenuIco,
        bool $canArchive = true,
        bool $canDelete = true,
        string $uiMode = 'dropdown',
        bool $canEdit = true,
        bool $canDuplicate = true
    ): string {
        $taskId = (int) ($task['id'] ?? 0);
        $uiMode = $uiMode === 'legacy' ? 'legacy' : 'dropdown';

        if ($uiMode === 'legacy') {
            $menu = allTasksTableActionsMenuItemsHtml(
                $task,
                $lang,
                $allTasksLinkEsc,
                $allTasksCloneEsc,
                $allTasksRedirectUri,
                $showArchive,
                $tsMenuIco,
                $canArchive,
                $canDelete,
                '',
                $canEdit,
                $canDuplicate
            );
            $dropdownId = 'actionDropdown2' . $taskId;
            $actionLabel = htmlspecialchars($lang['Action'] ?? 'Action', ENT_QUOTES, 'UTF-8');
            $chevron = function_exists('ts_icon') ? ts_icon('chevron-down') : '';
            return '<td class="extra-height">'
                . '<div class="action-toggle" data-bs-toggle="collapse" data-bs-target="#' . $dropdownId . '">'
                . $actionLabel . $chevron
                . '</div>'
                . '<div id="' . $dropdownId . '" class="toggle-action collapse shadow-dept">'
                . '<ul>' . $menu . '</ul>'
                . '</div></td>';
        }

        $menu = allTasksTableActionsMenuItemsHtml(
            $task,
            $lang,
            $allTasksLinkEsc,
            $allTasksCloneEsc,
            $allTasksRedirectUri,
            $showArchive,
            $tsMenuIco,
            $canArchive,
            $canDelete,
            'dropdown-item d-flex align-items-center',
            $canEdit,
            $canDuplicate
        );
        $dots = function_exists('ts_icon') ? ts_icon('dots-vertical', 'w-6') : '...';
        return '<td class="extra-height all-tasks-actions-cell">'
            . '<div class="dropdown">'
            . '<button class="btn-dots" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="' . htmlspecialchars($lang['Actions'] ?? 'Actions', ENT_QUOTES, 'UTF-8') . '">' . $dots . '</button>'
            . '<ul class="dropdown-menu dropdown-menu-end">' . $menu . '</ul>'
            . '</div></td>';
    }
}

if (!function_exists('allTasksTableTheadHtml')) {
    function allTasksTableTheadHtml(array $lang, bool $showCheckbox = true, bool $checkboxDisabled = false): string
    {
        $html = '<thead><tr>';
        if ($showCheckbox) {
            $disabledAttr = $checkboxDisabled ? ' disabled' : '';
            $html .= '<th class="text-center bs-checkbox"><div class="task-table-checkbox">'
                . '<input type="checkbox" id="select-all-tasks"' . $disabledAttr
                . ' aria-label="' . htmlspecialchars($lang['Select all'] ?? 'Select all', ENT_QUOTES, 'UTF-8') . '">'
                . '</div></th>';
        }
        $html .= '<th class="all-tasks-th-task">' . htmlspecialchars($lang['Task'] ?? 'Task', ENT_QUOTES, 'UTF-8') . '</th>'
            . '<th>' . htmlspecialchars($lang['Assigned To'] ?? 'Assigned To', ENT_QUOTES, 'UTF-8') . '</th>'
            . '<th>' . htmlspecialchars($lang['Client'] ?? 'Client', ENT_QUOTES, 'UTF-8') . '</th>'
            . '<th>' . htmlspecialchars($lang['Dates'] ?? 'Dates', ENT_QUOTES, 'UTF-8') . '</th>'
            . '<th>' . htmlspecialchars($lang['Progress'] ?? 'Progress', ENT_QUOTES, 'UTF-8') . '</th>'
            . '<th>' . htmlspecialchars($lang['Status'] ?? 'Status', ENT_QUOTES, 'UTF-8') . '</th>'
            . '<th class="max_w_150">' . htmlspecialchars($lang['Options'] ?? 'Options', ENT_QUOTES, 'UTF-8') . '</th>'
            . '</tr></thead>';
        return $html;
    }
}

if (!function_exists('allTasksTableColspan')) {
    function allTasksTableColspan(bool $showCheckbox = true): int
    {
        return $showCheckbox ? 8 : 7;
    }
}
