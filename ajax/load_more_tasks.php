<?php
// Prevent any output before JSON
ob_start();

try {
    session_start();
    require_once '../includes/lib-initialize.php';
    require_once '../includes/task.php';

    $showArchive = isset($_POST['archive']) && $_POST['archive'] !== '' && $_POST['archive'] !== '0';
    $taskArchiveFilterT = $showArchive ? ' AND t.is_archived = 1' : ' AND ' . Task::activeTasksWhere('t');
    $taskArchiveFilter = $showArchive ? ' AND is_archived = 1' : ' AND ' . Task::activeTasksWhere();

    // Check if user is logged in
    if (!isset($session) || !$session->userId) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        exit;
    }

    $id = $session->userId;
    $userType = $_SESSION['accountStatus'] ?? null;

    // Client drag/drop permission (matches client kanban behavior)
    $clientCanChangeStatus = true;
    if ((int)$userType === 2) {
        require_once '../includes/task_permission.php';
        $taskPermissions = TaskPermission::getOrCreate($id);
        $clientCanChangeStatus = (bool)$taskPermissions->can_change_status;
    }

    // Get parameters
    $status = $_POST['status'] ?? '';
    $page = (int)($_POST['page'] ?? 1);
    $limit = 10; // Load 10 tasks at a time
    // Optional filters used by some contexts (kept for backwards compatibility)
    $additionalFilters = $taskArchiveFilter;
    $showMyTasks = !isset($_POST['all_tasks']) || $_POST['all_tasks'] !== '1';
    $sortOrder = isset($_POST['sort_order']) ? trim((string)$_POST['sort_order']) : 'desc';
    $sortDirection = ($sortOrder === 'asc') ? 'ASC' : 'DESC';
    $projectId = (int)($_POST['project_id'] ?? 0); // For project-specific tasks
    $profileUserId = (int)($_POST['profile_user_id'] ?? 0); // For profile tasks
    $isProfileContext = $profileUserId > 0;
    $isProjectContext = $projectId > 0; // For project-specific task pages
    $isClientContext = ((int)$userType === 2) && !$isProfileContext;

    if ($isProfileContext) {
        $sessionUid = (int)$id;
        $actorStatus = (int)$userType;
        if ($profileUserId !== $sessionUid) {
            if ($actorStatus === 2) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
                exit;
            }
            require_once '../includes/permissions.php';
            if (function_exists('ensure_user_permissions')) {
                ensure_user_permissions($connect);
            }
            $profileTarget = User::findById($profileUserId);
            if (!$profileTarget) {
                ob_clean();
                echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
                exit;
            }
            $targetStatus = (int)($profileTarget->accountStatus ?? 0);
            if ($actorStatus === 3 && function_exists('has_permission')) {
                if ($targetStatus === 2 && !has_permission('client_view')) {
                    ob_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
                    exit;
                }
                if (($targetStatus === 1 || $targetStatus === 3) && !has_permission('staff_view')) {
                    ob_clean();
                    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
                    exit;
                }
            }
        }
    }

    // For the client kanban, "My Tasks" vs "All Tasks" does not apply.
    // We always filter to the tasks that belong to the client.
    $clientProjectIds = [];
    $clientProjectsSql = '';
    $clientTaskFilterNoAlias = '';
    $clientTaskFilterAliasT = '';
    if ($isClientContext) {
        $clientId = (int)$id;
        $projectQuery = $database->query("SELECT p_id FROM projects WHERE (c_id = '$clientId' OR main_client_id = '$clientId' OR FIND_IN_SET('$clientId', c_ids))");
        while ($row = $database->fetchArray($projectQuery)) {
            $clientProjectIds[] = (int)$row['p_id'];
        }
        if (!empty($clientProjectIds)) {
            $clientProjectsSql = implode(',', $clientProjectIds);
            $clientTaskFilterNoAlias = "(project_id IN ($clientProjectsSql) OR creator_id = $clientId OR user_id = $clientId)";
            $clientTaskFilterAliasT = "(t.project_id IN ($clientProjectsSql) OR t.creator_id = $clientId OR t.user_id = $clientId)";
        } else {
            $clientTaskFilterNoAlias = "(creator_id = $clientId OR user_id = $clientId)";
            $clientTaskFilterAliasT = "(t.creator_id = $clientId OR t.user_id = $clientId)";
        }
    }
    
    
    // Calculate offset - page 2 should start from task 10 (since first 10 are already shown)
    if ($page == 2) {
        $offset = 10; // Skip the first 10 tasks that are already displayed
    } else {
        $offset = 10 + (($page - 2) * $limit); // For page 3+, calculate from the initial 10
    }

    // Validate status
    if (empty($status)) {
        ob_clean();
        echo json_encode(['status' => 'error', 'message' => 'Status parameter required']);
        exit;
    }

    // Use the global database connection
    global $database;
    
    // Escape the status parameter
    $escapedStatus = $database->escapeValue($status);
    
    // Check if this is a personal column (custom_* or not in default statuses)
    $defaultStatuses = ['todo', 'inprogress', 'review', 'done'];
    $isPersonalColumn = !in_array($status, $defaultStatuses);
    
    // Add project filter if projectId is provided
    $projectFilter = $projectId > 0 ? " AND project_id = $projectId" : "";
    $projectFilterT = $projectId > 0 ? " AND t.project_id = $projectId" : "";

    // Match staff/admin kanban board scope (My tasks / created-only All Tasks / true All Tasks).
    $kanbanScopeSqlT = '';
    $excludePersonalSqlT = '';
    $isStaffKanbanContext = ((int) $userType === 3) && !$isProfileContext && !$isClientContext && $projectId == 0;
    $isAdminKanbanContext = ((int) $userType === 1) && !$isProfileContext && !$isClientContext && $projectId == 0;
    if ($isStaffKanbanContext || $isAdminKanbanContext) {
        if (!$isPersonalColumn) {
            // Default columns hide tasks parked in this user's personal columns (same as loadKanbanColumnsLimited).
            $excludePersonalSqlT = ' AND t.id NOT IN (SELECT task_id FROM extra_tasks_columns WHERE user_id = ' . (int) $id . ')';
        }
        if ($isStaffKanbanContext) {
            require_once '../includes/permissions.php';
            if (function_exists('ensure_user_permissions')) {
                ensure_user_permissions($connect);
            }
            if ($showMyTasks && !$showArchive) {
                $kanbanScopeSqlT = ' AND ' . Task::kanbanMyTasksWhere((int) $id, 't');
            } elseif (!$showMyTasks && !$showArchive && function_exists('staff_kanban_can_view_all_tasks') && !staff_kanban_can_view_all_tasks()) {
                // "All Tasks" tab without task_view_all = creator scope only
                if (function_exists('staff_kanban_can_view_created_tasks_tab') && staff_kanban_can_view_created_tasks_tab()) {
                    $kanbanScopeSqlT = ' AND ' . Task::kanbanCreatedTasksWhere((int) $id, 't');
                } else {
                    $kanbanScopeSqlT = ' AND ' . Task::kanbanMyTasksWhere((int) $id, 't');
                }
            }
        }
    }
    
    if ($isPersonalColumn) {
        // Personal column - get tasks from extra_tasks_columns mapping
        if ($isClientContext) {
            // Client context - only tasks belonging to this client
            $query = "SELECT t.*, p.project_title FROM tasks t 
                     LEFT JOIN projects p ON t.project_id = p.p_id
                     INNER JOIN extra_tasks_columns etc ON t.id = etc.task_id 
                     WHERE etc.user_id = $id AND etc.column_key = '$escapedStatus' 
                     AND $clientTaskFilterAliasT
                     $projectFilter
                     $taskArchiveFilterT
                     ORDER BY t.position $sortDirection, t.created_at $sortDirection, t.id $sortDirection LIMIT $limit OFFSET $offset";
        } elseif ($showMyTasks && !$showArchive) {
            // My Tasks mode - only show tasks assigned to current user that are in this personal column
            $query = "SELECT t.* FROM tasks t 
                     INNER JOIN extra_tasks_columns etc ON t.id = etc.task_id 
                     WHERE etc.user_id = $id AND etc.column_key = '$escapedStatus' 
                     AND (FIND_IN_SET($id, t.assigned_to) > 0 OR t.assigned_to = $id) 
                     $projectFilter
                     $taskArchiveFilterT
                     ORDER BY t.position $sortDirection, t.created_at $sortDirection, t.id $sortDirection LIMIT $limit OFFSET $offset";
        } else {
            // All Tasks mode - show all tasks in this personal column (still scoped for staff without task_view_all)
            $query = "SELECT t.* FROM tasks t 
                     INNER JOIN extra_tasks_columns etc ON t.id = etc.task_id 
                     WHERE etc.user_id = $id AND etc.column_key = '$escapedStatus' 
                     $projectFilter
                     $taskArchiveFilterT
                     $kanbanScopeSqlT
                     ORDER BY t.position $sortDirection, t.created_at $sortDirection, t.id $sortDirection LIMIT $limit OFFSET $offset";
        }
    } else {
        // Default column - get tasks by status
        if ($isProfileContext) {
            // Profile context - get tasks for specific user
            $profileUser = User::findById($profileUserId);
            if ($profileUser && ($profileUser->accountStatus == 3 || $profileUser->accountStatus == 1)) {
                // Staff/Admin - tasks assigned to them
                $query = "SELECT t.*, 
                    CASE 
                        WHEN t.project_id > 0 THEN p.project_title 
                        ELSE 'Internal Task' 
                    END as project_title,
                    CASE 
                        WHEN t.project_id > 0 THEN p.p_id 
                        ELSE 0 
                    END as p_id,
                    CASE 
                        WHEN t.project_id = 0 THEN 1 
                        ELSE 0 
                    END as is_internal,
                    p.c_id as project_client_id,
                    u.firstName as client_first_name,
                    cp.filename as client_image
                    FROM tasks t 
                    LEFT JOIN projects p ON t.project_id = p.p_id
                    LEFT JOIN users u ON p.c_id = u.id
                    LEFT JOIN profile_pics cp ON cp.fkUserId = u.id
                    WHERE FIND_IN_SET($profileUserId, t.assigned_to) > 0 AND t.status = '$escapedStatus' $additionalFilters ORDER BY t.position $sortDirection, t.created_at $sortDirection, t.id $sortDirection LIMIT $limit OFFSET $offset";
            } else {
                // Client - tasks from projects where they are main client or additional client
                $query = "SELECT t.*, 
                    CASE 
                        WHEN t.project_id > 0 THEN p.project_title 
                        ELSE 'Internal Task' 
                    END as project_title,
                    CASE 
                        WHEN t.project_id > 0 THEN p.p_id 
                        ELSE 0 
                    END as p_id,
                    CASE 
                        WHEN t.project_id = 0 THEN 1 
                        ELSE 0 
                    END as is_internal,
                    p.c_id as project_client_id,
                    u.firstName as client_first_name,
                    cp.filename as client_image
                    FROM tasks t 
                    LEFT JOIN projects p ON t.project_id = p.p_id
                    LEFT JOIN users u ON p.c_id = u.id
                    LEFT JOIN profile_pics cp ON cp.fkUserId = u.id
                    WHERE t.project_id > 0 AND (
                        p.c_id = $profileUserId OR 
                        p.main_client_id = $profileUserId OR 
                        FIND_IN_SET($profileUserId, p.c_ids) > 0
                    ) AND t.status = '$escapedStatus' $additionalFilters ORDER BY t.position $sortDirection, t.created_at $sortDirection, t.id $sortDirection LIMIT $limit OFFSET $offset";
            }
        } elseif ($isClientContext) {
            // Client context - tasks belonging to this client (not assignment-based)
            $query = "SELECT t.*, p.project_title FROM tasks t
                     LEFT JOIN projects p ON t.project_id = p.p_id
                     WHERE t.status = '$escapedStatus' AND $clientTaskFilterAliasT
                     $projectFilter
                     $taskArchiveFilterT
                     ORDER BY t.position $sortDirection, t.created_at $sortDirection, t.id $sortDirection LIMIT $limit OFFSET $offset";
        } elseif ($isStaffKanbanContext || $isAdminKanbanContext) {
            // Staff/Admin kanban: same scope + personal-column exclusion as staff/admin kanban.php
            $query = "SELECT t.*, p.project_title FROM tasks t
                     LEFT JOIN projects p ON t.project_id = p.p_id
                     WHERE t.status = '$escapedStatus'
                     $projectFilterT
                     $taskArchiveFilterT
                     $kanbanScopeSqlT
                     $excludePersonalSqlT
                     ORDER BY t.position $sortDirection, t.created_at $sortDirection, t.id $sortDirection
                     LIMIT $limit OFFSET $offset";
        } elseif ($showMyTasks && !$showArchive && $projectId == 0) {
            // My Tasks mode - only show tasks assigned to current user (for kanban view)
            $query = "SELECT * FROM tasks WHERE status = '$escapedStatus' AND (FIND_IN_SET($id, assigned_to) > 0 OR assigned_to = $id) $projectFilter $additionalFilters ORDER BY position $sortDirection, created_at $sortDirection, id $sortDirection LIMIT $limit OFFSET $offset";
        } else {
            // All Tasks mode OR Project Tasks mode - show all tasks
            $query = "SELECT * FROM tasks WHERE status = '$escapedStatus' $projectFilter $additionalFilters ORDER BY position $sortDirection, created_at $sortDirection, id $sortDirection LIMIT $limit OFFSET $offset";
        }

    }
    
    
    $result = $database->query($query);
    $tasks = [];
    if ($result) {
        while ($row = $database->fetchArray($result)) {
            $tasks[] = (object)$row;
        }
    }
    
    // Get total count for this status based on view mode
    if ($isPersonalColumn) {
        // Personal column - count tasks from extra_tasks_columns mapping
        if ($isClientContext) {
            $countQuery = "SELECT COUNT(*) as total FROM tasks t 
                          INNER JOIN extra_tasks_columns etc ON t.id = etc.task_id 
                          WHERE etc.user_id = $id AND etc.column_key = '$escapedStatus' 
                          AND $clientTaskFilterAliasT
                          $projectFilter
                          $taskArchiveFilterT";
        } elseif ($showMyTasks && !$showArchive) {
            // My Tasks mode - count only tasks assigned to current user that are in this personal column
            $countQuery = "SELECT COUNT(*) as total FROM tasks t 
                          INNER JOIN extra_tasks_columns etc ON t.id = etc.task_id 
                          WHERE etc.user_id = $id AND etc.column_key = '$escapedStatus' 
                          AND (FIND_IN_SET($id, t.assigned_to) > 0 OR t.assigned_to = $id)
                          $projectFilter
                          $taskArchiveFilterT";
        } else {
            // All Tasks mode - count all tasks in this personal column
            $countQuery = "SELECT COUNT(*) as total FROM tasks t 
                          INNER JOIN extra_tasks_columns etc ON t.id = etc.task_id 
                          WHERE etc.user_id = $id AND etc.column_key = '$escapedStatus'
                          $projectFilter
                          $taskArchiveFilterT
                          $kanbanScopeSqlT";
        }
    } else {
        // Default column - count tasks by status
        if ($isProfileContext) {
            // Profile context - count tasks for specific user
            $profileUser = User::findById($profileUserId);
            if ($profileUser && ($profileUser->accountStatus == 3 || $profileUser->accountStatus == 1)) {
                // Staff/Admin - count tasks assigned to them
                $countQuery = "SELECT COUNT(*) as total FROM tasks t 
                    LEFT JOIN projects p ON t.project_id = p.p_id
                    WHERE FIND_IN_SET($profileUserId, t.assigned_to) > 0 AND t.status = '$escapedStatus' $additionalFilters";
            } else {
                // Client - count tasks from projects where they are main client or additional client
                $countQuery = "SELECT COUNT(*) as total FROM tasks t 
                    LEFT JOIN projects p ON t.project_id = p.p_id
                    WHERE t.project_id > 0 AND (
                        p.c_id = $profileUserId OR 
                        p.main_client_id = $profileUserId OR 
                        FIND_IN_SET($profileUserId, p.c_ids) > 0
                    ) AND t.status = '$escapedStatus' $additionalFilters";
            }
        } elseif ($isClientContext) {
            $countQuery = "SELECT COUNT(*) as total FROM tasks t 
                          WHERE t.status = '$escapedStatus' AND $clientTaskFilterAliasT
                          $projectFilter
                          $taskArchiveFilterT";
        } elseif ($isStaffKanbanContext || $isAdminKanbanContext) {
            $countQuery = "SELECT COUNT(*) as total FROM tasks t
                          WHERE t.status = '$escapedStatus'
                          $projectFilterT
                          $taskArchiveFilterT
                          $kanbanScopeSqlT
                          $excludePersonalSqlT";
        } elseif ($showMyTasks && !$showArchive && $projectId == 0) {
            // My Tasks mode - count only tasks assigned to current user (for kanban view)
            $countQuery = "SELECT COUNT(*) as total FROM tasks WHERE status = '$escapedStatus' AND (FIND_IN_SET($id, assigned_to) > 0 OR assigned_to = $id) $projectFilter $additionalFilters";
        } else {
            // All Tasks mode OR Project Tasks mode - count all tasks
            $countQuery = "SELECT COUNT(*) as total FROM tasks WHERE status = '$escapedStatus' $projectFilter $additionalFilters";
        }
    }
    
    $countResult = $database->query($countQuery);
    $totalCount = 0;
    if ($countResult) {
        $countRow = $database->fetchArray($countResult);
        $totalCount = $countRow['total'];
    }
    
    // Calculate remaining tasks - FIXED LOGIC
    // Page 2: 10 (initial) + 10 (current) = 20 total
    // Page 3: 10 (initial) + 10 (page 2) + 10 (current) = 30 total
    $totalLoadedSoFar = 10 + (($page - 2) * 10) + count($tasks);
    $remainingCount = $totalCount - $totalLoadedSoFar;
    
    
    // Ensure remaining count is not negative
    if ($remainingCount < 0) {
        $remainingCount = 0;
    }
    
    // Get all staff and admin users for avatar generation
    require_once '../includes/user.php';
    $projectStaff = [];
    $staffUsers = User::findBySql("SELECT * FROM users WHERE (accountStatus = 3 OR accountStatus = 1) AND status = 0");
    foreach ($staffUsers as $staffUser) {
        $projectStaff[$staffUser->id] = $staffUser;
    }
    
    // Include the functions file for getUserAvatarHtml
    require_once '../includes/functions.php';
    
    // Include permissions for checking task permissions
    require_once '../includes/permissions.php';
    require_once '../includes/task_recurrence_helper.php';

    $loadMoreRecurringMap = [];
    $showRecurringBadge = ((int) $userType === 1 || (int) $userType === 3)
        && function_exists('tasksession_recurrence_map_for_task_ids');
    if ($showRecurringBadge && !empty($tasks)) {
        $scanIds = [];
        foreach ($tasks as $__t) {
            if (!empty($__t->id)) {
                $scanIds[] = (int) $__t->id;
            }
        }
        $loadMoreRecurringMap = tasksession_recurrence_map_for_task_ids($scanIds);
    }
    
    // Generate HTML for tasks using the same structure as main app
    $html = '';
    foreach ($tasks as $task) {
        $taskId = $task->id;
        $projectId = $task->project_id;
        $taskTitle = htmlspecialchars($task->title);
        $taskDescription = htmlspecialchars($task->description ?? '');
        // Handle date formatting - check if it's a timestamp or date string
        $startDate = '';
        if (!empty($task->start_date)) {
            if (is_numeric($task->start_date)) {
                // It's a Unix timestamp
                $startDate = date('M j, Y', $task->start_date);
            } else {
                // It's a date string
                $startDate = date('M j, Y', strtotime($task->start_date));
            }
        }
        
        $dueDate = '';
        if (!empty($task->due_date)) {
            if (is_numeric($task->due_date)) {
                // It's a Unix timestamp
                $dueDate = date('M j, Y', $task->due_date);
            } else {
                // It's a date string
                $dueDate = date('M j, Y', strtotime($task->due_date));
            }
        }
        
        // Calculate overdue / due-today using date-only (midnight), same as staff/admin kanban.php.
        // Comparing to time() wrongly marks today's due date as Overdue after midnight.
        $isOverdue = false;
        $isDueToday = false;
        if (!empty($task->due_date)) {
            $dueTimestamp = is_numeric($task->due_date) ? (int) $task->due_date : strtotime($task->due_date);
            $dueDateOnly = strtotime(date('Y-m-d', $dueTimestamp));
            $today = strtotime('today');
            $isOverdue = $dueDateOnly < $today;
            $isDueToday = $dueDateOnly === $today;
        }
        
        // Generate avatar HTML
        $avatarHtml = '';
        if (!empty($task->assigned_to)) {
            $assignedStaffIds = explode(',', $task->assigned_to);
            foreach($assignedStaffIds as $staffId) {
                if(!empty($staffId) && isset($projectStaff[$staffId])) {
                    $staffUser = $projectStaff[$staffId];
                    $avatarHtml .= '<div class="avatar-overlap" data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($staffUser->firstName . ' ' . ($staffUser->lastName ?? '')) . '">';
                    $avatarHtml .= getUserAvatarHtml($staffId, $staffUser->firstName, $staffUser->lastName ?? '', 30, 30, 'rounded-circle', $staffUser->firstName);
                    $avatarHtml .= '</div>';
                }
            }
        }
        
        // Generate badge HTML
        $badgeHtml = '';
        if (!empty($task->due_date)) {
            // Handle both timestamp and date string for due_date
            $dueTimestamp = is_numeric($task->due_date) ? (int) $task->due_date : strtotime($task->due_date);
            $dueDateOnly = strtotime(date('Y-m-d', $dueTimestamp));
            $today = strtotime('today');
            
            if ($task->status == 'done' && !empty($task->completed_at)) {
                // For completed tasks, use completion date to determine badge (locked badge)
                $completedTimestamp = is_numeric($task->completed_at) ? $task->completed_at : strtotime($task->completed_at);
                $completedDateOnly = strtotime(date('Y-m-d', $completedTimestamp));
                
                if ($completedDateOnly < $dueDateOnly) {
                    // Completed before due date = Task Pro
                    $badgeHtml = '<span class="badge color-done review done-bg-op">Task Pro</span>';
                } elseif ($completedDateOnly == $dueDateOnly) {
                    // Completed on due date = On Time (frozen badge)
                    $badgeHtml = '<span class="badge color-review review-bg-op">On Time</span>';
                } else {
                    // Completed after due date = Overdue
                    $badgeHtml = '<span class="badge">Overdue</span>';
                }
            } else {
                // For non-completed tasks, use current date logic
                if ($isOverdue) {
                    $badgeHtml = '<span class="badge">Overdue</span>';
                } elseif ($isDueToday) {
                    $badgeHtml = '<span class="badge color-review review-bg-op">Due Today</span>';
                }
            }
        }
        
        $draggableAttr = 'true';
        $dragHandlers = 'ondragstart="drag(event)" ondragend="dragEnd(event)"';
        if ((int)$userType === 2 && !$clientCanChangeStatus) {
            $draggableAttr = 'false';
            $dragHandlers = '';
        }

        $html .= '<div class="card mb-2 task-card" 
                    draggable="' . $draggableAttr . '"
                    data-id="' . $taskId . '"
                    data-project-id="' . $projectId . '"
                    ' . $dragHandlers . '>
                    <div class="card-body p-2">
                        <!-- Bulk Delete Checkbox -->
                        <div class="bulk-delete-checkbox" style="display: none;">
                            <div class="form-check">
                                <input class="form-check-input task-checkbox" 
                                       type="checkbox" 
                                       value="' . $taskId . '" 
                                       id="task_' . $taskId . '">
                                <label class="form-check-label" for="task_' . $taskId . '">
                                    Select for deletion
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="project-badge d-flex align-items-center flex-wrap col-gap-5 mb-2">
                                    ' . ($projectId == 0 ? '<span class="internal-badge">Internal Task</span>' : '') . '
                                    ' . (
                                        $showRecurringBadge && (
                                            !empty($loadMoreRecurringMap[(int) $taskId])
                                            || (!empty($task->recurrence_id) && (int) $task->recurrence_id > 0)
                                        )
                                            ? '<span class="internal-badge task-card-recurring-badge" title="' . htmlspecialchars($lang['Recurring task'] ?? ($lang['Set repeats'] ?? 'Recurring task'), ENT_QUOTES, 'UTF-8') . '" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="' . htmlspecialchars($lang['Recurring task'] ?? ($lang['Set repeats'] ?? 'Recurring task'), ENT_QUOTES, 'UTF-8') . '">' . ts_icon('refresh', 'w-2 task-card-recurring-ico') . '</span>'
                                            : ''
                                    ) . '
                                </div>
                                <h5 class="card-title mb-1">' . $taskTitle . '</h5>
                            </div>
                            <div class="dropdown">
                                <button class="btn-dots" type="button" data-bs-toggle="dropdown">
                                    ' . ts_icon('dots-vertical', 'w-6') . '
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="openTaskSidebar(' . $taskId . '); return false;">
                                            ' . ts_icon('eye', 'me-2 tasksession-timer-log-menu-ico') . '
                                            ' . htmlspecialchars($lang['View Task'] ?? 'View task', ENT_QUOTES, 'UTF-8') . '
                                        </a>
                                    </li>
                                    ' . (has_permission('task_edit') ? '
                                    <li>
                                        <a class="dropdown-item" href="edit_task?id=' . $taskId . '">
                                            ' . ts_icon('edit', 'me-2 tasksession-timer-log-menu-ico') . '
                                            ' . htmlspecialchars($lang['Edit Task'] ?? 'Edit task', ENT_QUOTES, 'UTF-8') . '
                                        </a>
                                    </li>
                                    ' : '') . '
                                    ' . (has_permission('task_duplicate') ? '
                                    <li>
                                        <a class="dropdown-item" href="../includes/clone-task.php?id=' . $taskId . '&redirect=' . urlencode($_SERVER['HTTP_REFERER']) . '">
                                            ' . ts_icon('duplicate', 'me-2 tasksession-timer-log-menu-ico') . '
                                            Clone Task
                                        </a>
                                    </li>
                                    ' : '') . '
                                    ' . (isset($task->project_id) && $task->project_id > 0 ? '
                                    <li>
                                        <a class="dropdown-item" href="overview?projectId=' . $task->project_id . '">
                                            ' . ts_icon('info', 'me-2 tasksession-timer-log-menu-ico') . '
                                            ' . htmlspecialchars($lang['View Project'] ?? 'View project', ENT_QUOTES, 'UTF-8') . '
                                        </a>
                                    </li>
                                    ' : '') . '
                                    ' . ((int)$userType === 1 || has_permission('task_edit') ? (
                                        $showArchive
                                            ? '<li><a class="dropdown-item" href="#" onclick="unarchiveTask(' . $taskId . '); return false;">' . ts_icon('restore', 'me-2 tasksession-timer-log-menu-ico') . 'Unarchive Task</a></li>'
                                            : '<li><a class="dropdown-item" href="#" onclick="archiveTask(' . $taskId . '); return false;">' . ts_icon('archive', 'me-2 tasksession-timer-log-menu-ico') . 'Archive Task</a></li>'
                                    ) : '') . '
                                    ' . (has_permission('task_delete') ? '
                                    <li>
                                        <a class="dropdown-item text-danger" href="#" onclick="deleteTask(' . $taskId . ')">
                                            ' . ts_icon('delete', 'me-2 tasksession-timer-log-menu-ico') . '
                                            ' . htmlspecialchars($lang['Delete Task'] ?? 'Delete task', ENT_QUOTES, 'UTF-8') . '
                                        </a>
                                    </li>
                                    ' : '') . '
                                </ul>
                            </div>
                        </div>
                        
                        ' . (!empty($taskDescription) ? '<div class="task-description mb-2">' . $taskDescription . '</div>' : '') . '
                        
                        ' . (!empty($startDate) || !empty($dueDate) ? '
                        <div class="task-date d-flex">
                            ' . (!empty($startDate) ? '<div class="start-date"><b>Start:</b> ' . $startDate . '</div>' : '') . '
                            ' . (!empty($dueDate) ? '<div class="' . ($isOverdue ? 'text-danger' : '') . '"><b>Due:</b> ' . $dueDate . '</div>' : '') . '
                        </div>
                        ' : '') . '
                        
                        ' . (!empty($task->assigned_to) ? '
                        <div class="team-col d-flex align-items-baseline">
                            <div class="d-flex avatar-head">
                                ' . $avatarHtml . '
                            </div>
                            <div class="due-badge">
                                ' . $badgeHtml . '
                            </div>
                        </div>
                        ' : '') . '
                    </div>
                </div>';
    }
    
    // Clean any output and return response
    ob_clean();
    echo json_encode([
        'status' => 'success',
        'html' => $html,
        'remaining_count' => $remainingCount,
        'loaded_count' => $totalLoadedSoFar,
        'total_count' => $totalCount,
        'has_more' => $remainingCount > 0,
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    ob_clean();
    echo json_encode([
        'status' => 'error', 
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
}
?>

