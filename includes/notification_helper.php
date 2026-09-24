<?php
require_once('notifications.php');
require_once('user.php');
require_once('permissions.php');

class NotificationHelper {
    
    /**
     * Check if user has permission to view a project
     */
    private static function canViewProject($user_id, $project_id) {
        global $db1;
        
        // Get user account status
        $sql = "SELECT accountStatus FROM users WHERE id = " . $db1->escape($user_id);
        $result = $db1->query($sql);
        $user = $db1->fetch_row($result);
        
        if (!$user) return false;
        
        // Admin can view all projects
        if ($user['accountStatus'] == 1) {
            return true;
        }
        
        // Check if user has project_view_all permission
        if (has_permission('project_view_all')) {
            return true;
        }
        
        // For staff, check if they're assigned to the project
        if ($user['accountStatus'] == 3) {
            $sql = "SELECT s_ids FROM projects WHERE p_id = " . $db1->escape($project_id);
            $result = $db1->query($sql);
            $project = $db1->fetch_row($result);
            
            if ($project && $project['s_ids']) {
                $staff_ids = explode(',', $project['s_ids']);
                return in_array($user_id, $staff_ids);
            }
        }
        
        // For clients, check if they own the project
        if ($user['accountStatus'] == 2) {
            $sql = "SELECT c_id FROM projects WHERE p_id = " . $db1->escape($project_id);
            $result = $db1->query($sql);
            $project = $db1->fetch_row($result);
            
            return $project && $project['c_id'] == $user_id;
        }
        
        return false;
    }
    
    /**
     * Check if user has permission to view a task
     */
    private static function canViewTask($user_id, $task_id, $project_id = null) {
        global $db1;
        
        // Get user account status
        $sql = "SELECT accountStatus FROM users WHERE id = " . $db1->escape($user_id);
        $result = $db1->query($sql);
        $user = $db1->fetch_row($result);
        
        if (!$user) return false;
        
        // Admin can view all tasks
        if ($user['accountStatus'] == 1) {
            return true;
        }
        
        // Check if user has task_view_all permission
        if (has_permission('task_view_all')) {
            return true;
        }
        
        // Get task details if project_id not provided
        if (!$project_id) {
            $sql = "SELECT project_id, assigned_to FROM tasks WHERE id = " . $db1->escape($task_id);
            $result = $db1->query($sql);
            $task = $db1->fetch_row($result);
            
            if (!$task) return false;
            
            $project_id = $task['project_id'];
            $assigned_to = $task['assigned_to'];
        } else {
            $sql = "SELECT assigned_to FROM tasks WHERE id = " . $db1->escape($task_id);
            $result = $db1->query($sql);
            $task = $db1->fetch_row($result);
            
            if (!$task) return false;
            
            $assigned_to = $task['assigned_to'];
        }
        
        // Check if user is assigned to the task
        if ($assigned_to) {
            $assigned_ids = array_map('trim', explode(',', $assigned_to));
            if (in_array((string)$user_id, $assigned_ids, true)) {
                return true;
            }
        }
        
        // For staff, check if they're assigned to the project
        if ($user['accountStatus'] == 3) {
            $sql = "SELECT s_ids FROM projects WHERE p_id = " . $db1->escape($project_id);
            $result = $db1->query($sql);
            $project = $db1->fetch_row($result);
            
            if ($project && $project['s_ids']) {
                $staff_ids = explode(',', $project['s_ids']);
                return in_array($user_id, $staff_ids);
            }
        }
        
        // For clients, check if they own the project
        if ($user['accountStatus'] == 2) {
            $sql = "SELECT c_id FROM projects WHERE p_id = " . $db1->escape($project_id);
            $result = $db1->query($sql);
            $project = $db1->fetch_row($result);
            
            return $project && $project['c_id'] == $user_id;
        }
        
        return false;
    }
    
    /**
     * Filter users based on project permissions
     */
    private static function filterUsersByProjectPermission($users, $project_id) {
        $filtered_users = [];
        
        foreach ($users as $user) {
            if (self::canViewProject($user->id, $project_id)) {
                $filtered_users[] = $user;
            }
        }
        
        return $filtered_users;
    }
    
    /**
     * Filter users based on task permissions (for internal tasks)
     */
    private static function filterUsersByTaskPermission($users, $task_id, $project_id = null) {
        global $db1;
        $filtered_users = [];
        
        try {
            // Get task details including creator
            $sql = "SELECT assigned_to, creator_id FROM tasks WHERE id = " . $db1->escape($task_id);
            $result = $db1->query($sql);
            $task = $db1->fetch_row($result);
            
            if (!$task) {
                return $filtered_users; // Task not found
            }
            
            $assigned_to = $task['assigned_to'];
            $created_by = $task['creator_id'];
            $assigned_ids = $assigned_to ? explode(',', $assigned_to) : [];
            
            foreach ($users as $user) {
                // For internal tasks (project_id = 0), only notify:
                // 1. Users assigned to the task
                // 2. The creator of the task (if different from assigned users)
                if (in_array($user->id, $assigned_ids)) {
                    $filtered_users[] = $user;
                } else if ($created_by && $user->id == $created_by) {
                    $filtered_users[] = $user;
                }
            }
        } catch (Exception $e) {
            // Log error but don't break the flow
            error_log("Error in filterUsersByTaskPermission: " . $e->getMessage());
        }
        
        return $filtered_users;
    }
    
    /**
     * Check if admin is assigned to a project
     */
    private static function isAdminAssignedToProject($user_id, $project_id) {
        global $db1;
        
        // Get user account status
        $sql = "SELECT accountStatus FROM users WHERE id = " . $db1->escape($user_id);
        $result = $db1->query($sql);
        $user = $db1->fetch_row($result);
        
        if (!$user || $user['accountStatus'] != 1) {
            return false; // Not an admin
        }
        
        // Check if admin is assigned to the project
        $sql = "SELECT s_ids FROM projects WHERE p_id = " . $db1->escape($project_id);
        $result = $db1->query($sql);
        $project = $db1->fetch_row($result);
        
        if ($project && $project['s_ids']) {
            $staff_ids = explode(',', $project['s_ids']);
            return in_array($user_id, $staff_ids);
        }
        
        return false;
    }

    /**
     * Filter users for task/message notifications (admins only if assigned to project or task creator)
     */
    private static function filterUsersForTaskNotifications($users, $project_id, $task_id = null) {
        global $db1;
        $filtered_users = [];
        
        // Get project details to check assignments
        $sql = "SELECT s_ids, c_id, c_ids, main_client_id FROM projects WHERE p_id = " . $db1->escape($project_id);
        $result = $db1->query($sql);
        $project = $db1->fetch_row($result);
        
        if (!$project) {
            return $filtered_users; // Project not found
        }
        
        $assigned_staff_ids = $project['s_ids'] ? explode(',', $project['s_ids']) : [];
        $main_client_id = !empty($project['main_client_id']) ? $project['main_client_id'] : $project['c_id'];
        $additional_client_ids = $project['c_ids'] ? array_filter(explode(',', $project['c_ids'])) : [];
        
        // Combine main client and additional clients
        $all_client_ids = array_merge([$main_client_id], $additional_client_ids);
        
        // Get task creator if task_id is provided
        $task_creator_id = null;
        if ($task_id) {
            $sql = "SELECT creator_id FROM tasks WHERE id = " . $db1->escape($task_id);
            $result = $db1->query($sql);
            if ($row = $db1->fetch_row($result)) {
                $task_creator_id = $row['creator_id'];
            }
        }
        
        foreach ($users as $user) {
            // Get user account status
            $sql = "SELECT accountStatus FROM users WHERE id = " . $db1->escape($user->id);
            $result = $db1->query($sql);
            $user_data = $db1->fetch_row($result);
            
            if (!$user_data) continue;
            
            // If it's an admin, check if they're assigned to the project OR if they're the task creator
            if ($user_data['accountStatus'] == 1) {
                if (in_array($user->id, $assigned_staff_ids) || ($task_creator_id && $user->id == $task_creator_id)) {
                    $filtered_users[] = $user;
                }
                // Admins not assigned to project or not task creator will be silent for task/message notifications
            } 
            // If it's staff, check if they're assigned to the project
            else if ($user_data['accountStatus'] == 3) {
                if (in_array($user->id, $assigned_staff_ids)) {
                    $filtered_users[] = $user;
                }
                // Staff not assigned to project will be silent
            }
            // If it's a client, check if they are associated with the project (main or additional)
            else if ($user_data['accountStatus'] == 2) {
                if (in_array($user->id, $all_client_ids)) {
                    $filtered_users[] = $user;
                }
                // Both main client and additional clients get notifications
            }
        }
        
        return $filtered_users;
    }

    /**
     * Include the actor so their own actions appear on activity.php (not bell).
     */
    private static function ensureActorNotified(array $users, int $actor_id): array
    {
        if ($actor_id <= 0) {
            return $users;
        }
        foreach ($users as $user) {
            if ((int) ($user->id ?? 0) === $actor_id) {
                return $users;
            }
        }
        $users[] = (object) ['id' => $actor_id];

        return $users;
    }

    /**
     * Filter users for payment notifications (all admins, regardless of project assignment)
     */
    private static function filterUsersForPaymentNotifications($users, $project_id) {
        global $db1;
        $filtered_users = [];
        
        // Get project details to check assignments
        $sql = "SELECT s_ids, c_id FROM projects WHERE p_id = " . $db1->escape($project_id);
        $result = $db1->query($sql);
        $project = $db1->fetch_row($result);
        
        if (!$project) {
            return $filtered_users; // Project not found
        }
        
        $assigned_staff_ids = $project['s_ids'] ? explode(',', $project['s_ids']) : [];
        $client_id = $project['c_id'];
        
        foreach ($users as $user) {
            // Get user account status
            $sql = "SELECT accountStatus FROM users WHERE id = " . $db1->escape($user->id);
            $result = $db1->query($sql);
            $user_data = $db1->fetch_row($result);
            
            if (!$user_data) continue;
            
            // All admins get payment notifications, regardless of project assignment
            if ($user_data['accountStatus'] == 1) {
                $filtered_users[] = $user;
            } 
            // If it's staff, check if they're assigned to the project
            else if ($user_data['accountStatus'] == 3) {
                if (in_array($user->id, $assigned_staff_ids)) {
                    $filtered_users[] = $user;
                }
                // Staff not assigned to project will be silent
            }
            // If it's a client, check if they own the project
            else if ($user_data['accountStatus'] == 2) {
                if ($user->id == $client_id) {
                    $filtered_users[] = $user;
                }
                // Only the project owner client gets notifications
            }
        }
        
        return $filtered_users;
    }
    
    /**
     * Create notification for project creation
     */
    public static function projectCreated($project_id, $project_title, $creator_id, $client_id = null) {
        global $db1;
        // Get all users who should be notified (admin, staff, and client if provided)
        $sql = "SELECT id FROM users WHERE accountStatus IN (1, 3) AND id != " . $db1->escape($creator_id);
        $result = $db1->query($sql);
        $users = [];
        while ($row = $db1->fetch_row($result)) {
            $users[] = (object)$row;
        }
        // Also notify the client if provided and not the creator
        if ($client_id && $client_id != $creator_id) {
            $users[] = (object)['id' => $client_id];
        }
        // Remove duplicates
        $users = array_unique($users, SORT_REGULAR);
        
        // Filter users based on project permissions and admin project assignment
        $users = self::filterUsersForTaskNotifications($users, $project_id, null);
        
        foreach ($users as $user) {
            $data = [
                'user_name' => self::getUserName($creator_id),
                'project_title' => $project_title
            ];
            $message = Notifications::getNotificationMessage(Notifications::TYPE_PROJECT_CREATED, $data);
            $title = Notifications::getNotificationTitle(Notifications::TYPE_PROJECT_CREATED);
            Notifications::createNotificationWithProject(
                $user->id,
                $creator_id,
                Notifications::TYPE_PROJECT_CREATED,
                $title,
                $message,
                $project_id, // related_id is project id
                'project',
                $project_id // related_project_id is also project id
            );
        }
    }
    
    /**
     * Create notification for project update
     */
    public static function projectUpdated($project_id, $project_title, $updater_id) {
        global $db1;
        $users_to_notify = [];
        
        // Get all users who should be notified (staff and admin)
        $sql = "SELECT id FROM users WHERE accountStatus IN (1, 3) AND id != " . $db1->escape($updater_id);
        $result = $db1->query($sql);
        while ($row = $db1->fetch_row($result)) {
            $users_to_notify[] = (object)$row;
        }
        
        // If project_id is provided, notify the client of the project
        if ($project_id) {
            $sql = "SELECT c_id FROM projects WHERE p_id = " . $db1->escape($project_id);
            $result = $db1->query($sql);
            if ($row = $db1->fetch_row($result)) {
                $client_id = $row['c_id'];
                if ($client_id && $client_id != $updater_id) {
                    $users_to_notify[] = (object)['id' => $client_id];
                }
            }
        }
        
        // Remove duplicates
        $users_to_notify = array_unique($users_to_notify, SORT_REGULAR);
        
        // Filter users based on project permissions and admin project assignment
        $users_to_notify = self::filterUsersForTaskNotifications($users_to_notify, $project_id, null);
        
        foreach ($users_to_notify as $user) {
            $data = [
                'user_name' => self::getUserName($updater_id),
                'project_title' => $project_title
            ];
            
            $message = Notifications::getNotificationMessage(Notifications::TYPE_PROJECT_UPDATED, $data);
            $title = Notifications::getNotificationTitle(Notifications::TYPE_PROJECT_UPDATED);
            
            Notifications::createNotificationWithProject(
                $user->id,
                $updater_id,
                Notifications::TYPE_PROJECT_UPDATED,
                $title,
                $message,
                $project_id, // related_id is project id
                'project',
                $project_id // related_project_id is also project id
            );
        }
    }
    
    /**
     * In-app bell notification for sub-task assignee only (no email).
     * @param int         $parentTaskId   Parent task id (stored as related_id for sidebar deep-link).
     * @param string      $subtaskTitle   Sub-task title for message body.
     * @param int         $assigneeUserId Sub-task assigned_to user id.
     * @param int         $actorId        Acting user (creator or status changer).
     * @param string      $event          'created' or 'completed'.
     */
    public static function subtaskNotifyAssignee($parentTaskId, $subtaskTitle, $assigneeUserId, $actorId, $event) {
        global $db1;

        $parentTaskId = (int)$parentTaskId;
        $assigneeUserId = (int)$assigneeUserId;
        $actorId = (int)$actorId;

        if ($assigneeUserId <= 0 || $assigneeUserId === $actorId) {
            return;
        }
        if (!in_array($event, ['created', 'completed'], true)) {
            return;
        }

        $sql = "SELECT title, project_id FROM tasks WHERE id = " . $db1->escape($parentTaskId);
        $result = $db1->query($sql);
        $row = $result ? $db1->fetch_row($result) : null;
        if (!$row) {
            return;
        }

        $parentTitle = isset($row['title']) ? (string)$row['title'] : '';
        $project_id = !empty($row['project_id']) ? (int)$row['project_id'] : null;

        $users_to_notify = [(object)['id' => $assigneeUserId]];
        if ($project_id) {
            $users_to_notify = self::filterUsersForTaskNotifications($users_to_notify, $project_id, $parentTaskId);
        } else {
            $users_to_notify = self::filterUsersByTaskPermission($users_to_notify, $parentTaskId, $project_id);
        }

        if (empty($users_to_notify) && self::canViewTask($assigneeUserId, $parentTaskId, $project_id)) {
            $users_to_notify = [(object)['id' => $assigneeUserId]];
        }

        $type = $event === 'created'
            ? Notifications::TYPE_SUBTASK_CREATED
            : Notifications::TYPE_SUBTASK_COMPLETED;

        $subtaskTitle = trim((string)$subtaskTitle);
        if ($subtaskTitle === '') {
            $subtaskTitle = 'Sub-task';
        }

        $data = [
            'user_name' => self::getUserName($actorId),
            'subtask_title' => $subtaskTitle,
            'parent_task_title' => $parentTitle !== '' ? $parentTitle : 'Task',
        ];
        $message = Notifications::getNotificationMessage($type, $data);
        $title = Notifications::getNotificationTitle($type);

        foreach ($users_to_notify as $user) {
            Notifications::createNotificationWithProject(
                $user->id,
                $actorId,
                $type,
                $title,
                $message,
                $parentTaskId,
                'task',
                $project_id
            );
        }
    }

    /**
     * Bell-only: notify sub-task creator when someone else marks it complete.
     * Skips when the creator completed it themselves, or when the assignee path already notified them
     * (creator is assignee and a different user completed — handled by subtaskNotifyAssignee).
     */
    public static function subtaskNotifySubtaskCreatorOnComplete(
        $parentTaskId,
        $subtaskTitle,
        $subtaskCreatorId,
        $assigneeUserId,
        $actorId
    ) {
        global $db1;

        $parentTaskId = (int)$parentTaskId;
        $subtaskCreatorId = (int)$subtaskCreatorId;
        $assigneeUserId = (int)$assigneeUserId;
        $actorId = (int)$actorId;

        if ($subtaskCreatorId <= 0 || $subtaskCreatorId === $actorId) {
            return;
        }
        // Assignee already gets subtaskNotifyAssignee when assignee !== actor.
        if ($assigneeUserId > 0 && $assigneeUserId === $subtaskCreatorId && $assigneeUserId !== $actorId) {
            return;
        }

        $sql = "SELECT title, project_id FROM tasks WHERE id = " . $db1->escape($parentTaskId);
        $result = $db1->query($sql);
        $row = $result ? $db1->fetch_row($result) : null;
        if (!$row) {
            return;
        }

        $parentTitle = isset($row['title']) ? (string)$row['title'] : '';
        $project_id = !empty($row['project_id']) ? (int)$row['project_id'] : null;

        $users_to_notify = [(object)['id' => $subtaskCreatorId]];
        if ($project_id) {
            $users_to_notify = self::filterUsersForTaskNotifications($users_to_notify, $project_id, $parentTaskId);
        } else {
            $users_to_notify = self::filterUsersByTaskPermission($users_to_notify, $parentTaskId, $project_id);
        }

        if (empty($users_to_notify) && self::canViewTask($subtaskCreatorId, $parentTaskId, $project_id)) {
            $users_to_notify = [(object)['id' => $subtaskCreatorId]];
        }

        $type = Notifications::TYPE_SUBTASK_COMPLETED;
        $subtaskTitle = trim((string)$subtaskTitle);
        if ($subtaskTitle === '') {
            $subtaskTitle = 'Sub-task';
        }

        $data = [
            'user_name' => self::getUserName($actorId),
            'subtask_title' => $subtaskTitle,
            'parent_task_title' => $parentTitle !== '' ? $parentTitle : 'Task',
        ];
        $message = Notifications::getNotificationMessage($type, $data);
        $title = Notifications::getNotificationTitle($type);

        foreach ($users_to_notify as $user) {
            Notifications::createNotificationWithProject(
                $user->id,
                $actorId,
                $type,
                $title,
                $message,
                $parentTaskId,
                'task',
                $project_id
            );
        }
    }

    /**
     * Bell-only: notify parent task creator when a sub-task is marked complete (e.g. assignee finished their sub-task).
     * Skips when the actor is already the parent creator. No email.
     */
    public static function subtaskNotifyParentCreatorOnComplete($parentTaskId, $subtaskTitle, $actorId) {
        global $db1;

        $parentTaskId = (int)$parentTaskId;
        $actorId = (int)$actorId;
        if ($parentTaskId <= 0) {
            return;
        }

        $sql = "SELECT title, project_id, creator_id FROM tasks WHERE id = " . $db1->escape($parentTaskId);
        $result = $db1->query($sql);
        $row = $result ? $db1->fetch_row($result) : null;
        if (!$row) {
            return;
        }

        $creatorId = isset($row['creator_id']) ? (int)$row['creator_id'] : 0;
        if ($creatorId <= 0 || $creatorId === $actorId) {
            return;
        }

        $parentTitle = isset($row['title']) ? (string)$row['title'] : '';
        $project_id = !empty($row['project_id']) ? (int)$row['project_id'] : null;

        $users_to_notify = [(object)['id' => $creatorId]];
        if ($project_id) {
            $users_to_notify = self::filterUsersForTaskNotifications($users_to_notify, $project_id, $parentTaskId);
        } else {
            $users_to_notify = self::filterUsersByTaskPermission($users_to_notify, $parentTaskId, $project_id);
        }

        if (empty($users_to_notify) && self::canViewTask($creatorId, $parentTaskId, $project_id)) {
            $users_to_notify = [(object)['id' => $creatorId]];
        }

        $subtaskTitle = trim((string)$subtaskTitle);
        if ($subtaskTitle === '') {
            $subtaskTitle = 'Sub-task';
        }

        $type = Notifications::TYPE_SUBTASK_COMPLETED;
        $data = [
            'user_name' => self::getUserName($actorId),
            'subtask_title' => $subtaskTitle,
            'parent_task_title' => $parentTitle !== '' ? $parentTitle : 'Task',
        ];
        $message = Notifications::getNotificationMessage($type, $data);
        $title = Notifications::getNotificationTitle($type);

        foreach ($users_to_notify as $user) {
            Notifications::createNotificationWithProject(
                $user->id,
                $actorId,
                $type,
                $title,
                $message,
                $parentTaskId,
                'task',
                $project_id
            );
        }
    }

    /**
     * Create notification for task creation
     *
     * @param bool $alsoNotifyCreator When true (recurring spawn), also bell the original creator.
     */
    public static function taskCreated($task_id, $task_title, $creator_id, $assigned_to = null, $project_id = null, $alsoNotifyCreator = false) {
        global $db1;
        $users_to_notify = [];
        // Get all staff and admin users
        $sql = "SELECT id FROM users WHERE accountStatus IN (1, 3) AND id != " . $db1->escape($creator_id);
        $result = $db1->query($sql);
        while ($row = $db1->fetch_row($result)) {
            $users_to_notify[] = (object)$row;
        }
        // If task is assigned to specific users, notify them
        if ($assigned_to) {
            $assigned_ids = explode(',', $assigned_to);
            foreach ($assigned_ids as $assigned_id) {
                if ($assigned_id && $assigned_id != $creator_id) {
                    $users_to_notify[] = (object)['id' => $assigned_id];
                }
            }
        }
        // If project_id is provided, notify all clients of the project (main + additional)
        if ($project_id) {
            $sql = "SELECT c_id, c_ids FROM projects WHERE p_id = " . $db1->escape($project_id);
            $result = $db1->query($sql);
            if ($row = $db1->fetch_row($result)) {
                $main_client_id = $row['c_id'];
                $additional_client_ids = $row['c_ids'] ? array_filter(explode(',', $row['c_ids'])) : [];
                
                // Add main client
                if ($main_client_id && $main_client_id != $creator_id) {
                    $users_to_notify[] = (object)['id' => $main_client_id];
                }
                
                // Add additional clients
                foreach ($additional_client_ids as $client_id) {
                    if ($client_id && $client_id != $creator_id) {
                        $users_to_notify[] = (object)['id' => $client_id];
                    }
                }
            }
        }
        // Remove duplicates
        $users_to_notify = array_unique($users_to_notify, SORT_REGULAR);
        
        // Filter users based on task permissions and admin project assignment
        if ($project_id) {
            $users_to_notify = self::filterUsersForTaskNotifications($users_to_notify, $project_id, $task_id);
        } else {
            $users_to_notify = self::filterUsersByTaskPermission($users_to_notify, $task_id, $project_id);
        }

        if ($alsoNotifyCreator && (int) $creator_id > 0) {
            $hasCreator = false;
            foreach ($users_to_notify as $u) {
                if ((int) $u->id === (int) $creator_id) {
                    $hasCreator = true;
                    break;
                }
            }
            if (!$hasCreator) {
                $users_to_notify[] = (object)['id' => (int) $creator_id];
            }
        }
        
        foreach ($users_to_notify as $user) {
            $data = [
                'user_name' => self::getUserName($creator_id),
                'task_title' => $task_title
            ];
            $message = Notifications::getNotificationMessage(Notifications::TYPE_TASK_CREATED, $data);
            $title = Notifications::getNotificationTitle(Notifications::TYPE_TASK_CREATED);
            Notifications::createNotificationWithProject(
                $user->id,
                $creator_id,
                Notifications::TYPE_TASK_CREATED,
                $title,
                $message,
                $task_id, // related_id is always the task id
                'task',
                $project_id // related_project_id
            );
        }
    }

    /**
     * Emails for a newly created task (assignees + project clients).
     * When $includeCreator is true, also emails the original creator (deduped).
     */
    public static function sendTaskCreatedEmails($task, $includeCreator = false): void
    {
        if (!$task || empty($task->id)) {
            return;
        }
        require_once __DIR__ . '/email_helper.php';
        require_once __DIR__ . '/projects.php';
        require_once __DIR__ . '/settings.php';
        require_once __DIR__ . '/user.php';

        global $url, $company_name;

        $settings = settings::findById(1);
        if (!$settings || empty($settings->task_create_email)) {
            return;
        }
        $templateHTML = $settings->task_create_email;
        $emailHelper = new EmailHelper($settings);
        $taskTitle = (string) ($task->title ?? '');
        $dueDate = (string) ($task->due_date ?? '');
        $snippet = self::taskCreatedEmailSnippet($task->description ?? '');
        $dashUrl = isset($url) ? (string) $url : (string) ($settings->url ?? '');
        $signature = isset($company_name) && $company_name !== ''
            ? (string) $company_name
            : (string) ($settings->company_name ?? '');

        $projectId = isset($task->project_id) ? (int) $task->project_id : 0;
        $projectName = 'Internal Task';
        $project = null;
        if ($projectId > 0) {
            $project = projects::findByProjectId($projectId);
            if ($project && !empty($project->project_title)) {
                $projectName = $project->project_title;
            }
        }

        $sentIds = [];
        $sendOne = static function ($userId, $subject) use (
            &$sentIds,
            $emailHelper,
            $templateHTML,
            $taskTitle,
            $projectName,
            $snippet,
            $dueDate,
            $dashUrl,
            $signature
        ) {
            $uid = (int) $userId;
            if ($uid <= 0 || isset($sentIds[$uid])) {
                return;
            }
            $recipient = User::findById($uid);
            if (!$recipient || !filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $variablesArr = [
                '{USER_NAME}' => htmlspecialchars((string) $recipient->firstName, ENT_QUOTES, 'UTF-8'),
                '{TASK_TITLE}' => htmlspecialchars($taskTitle, ENT_QUOTES, 'UTF-8'),
                '{PROJECT_NAME}' => htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8'),
                '{TASK_DESCRIPTION}' => $snippet,
                '{DUE_DATE}' => htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8'),
                '{DASHBOARD_URL}' => $dashUrl,
                '{SIGNATURE}' => $signature,
            ];
            $ok = $emailHelper->sendTemplateEmail($recipient->email, $subject, $templateHTML, $variablesArr);
            if (!$ok) {
                error_log('[task_recurrence] Failed to send task creation email to: ' . $recipient->email);
            }
            $sentIds[$uid] = true;
        };

        if (!empty($task->assigned_to)) {
            foreach (explode(',', (string) $task->assigned_to) as $staffId) {
                $sendOne($staffId, 'New Task Assignment');
            }
        }

        if ($projectId > 0 && $project) {
            $mainClientId = !empty($project->main_client_id) ? $project->main_client_id : ($project->c_id ?? 0);
            $clientIds = [];
            if (!empty($project->c_ids)) {
                $clientIds = array_filter(explode(',', (string) $project->c_ids));
            }
            if (empty($clientIds) && $mainClientId) {
                $clientIds = [$mainClientId];
            }
            foreach ($clientIds as $clientId) {
                $sendOne($clientId, 'New Task Created in Your Project');
            }
        }

        if ($includeCreator) {
            $creatorId = isset($task->creator_id) ? (int) $task->creator_id : 0;
            if ($creatorId > 0 && !isset($sentIds[$creatorId])) {
                $creator = User::findById($creatorId);
                $isClient = $creator && (int) $creator->accountStatus === 2;
                $sendOne($creatorId, $isClient ? 'New Task Created in Your Project' : 'New Task Assignment');
            }
        }
    }

    private static function taskCreatedEmailSnippet($html): string
    {
        $raw = (string) $html;
        if (function_exists('sanitize_tinymce_content')) {
            $raw = sanitize_tinymce_content($raw);
        }
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($raw)));
        if ($plain === '') {
            return '';
        }
        $words = preg_split('/\s+/', $plain, -1, PREG_SPLIT_NO_EMPTY);
        if (is_array($words) && count($words) > 20) {
            return implode(' ', array_slice($words, 0, 20)) . '...';
        }
        return $plain;
    }
    
    /**
     * Create notification for task update
     */
    public static function taskUpdated($task_id, $task_title, $updater_id, $assigned_to = null, $project_id = null) {
        global $db1;
        $users_to_notify = [];
        
        // Get all staff and admin users
        $sql = "SELECT id FROM users WHERE accountStatus IN (1, 3) AND id != " . $db1->escape($updater_id);
        $result = $db1->query($sql);
        while ($row = $db1->fetch_row($result)) {
            $users_to_notify[] = (object)$row;
        }
        
        // If task is assigned to specific users, notify them
        if ($assigned_to) {
            $assigned_ids = explode(',', $assigned_to);
            foreach ($assigned_ids as $assigned_id) {
                if ($assigned_id && $assigned_id != $updater_id) {
                    $users_to_notify[] = (object)['id' => $assigned_id];
                }
            }
        }
        
        // If project_id is not provided, fetch it from the task
        if (!$project_id) {
            $sql = "SELECT project_id FROM tasks WHERE id = " . $db1->escape($task_id);
            $result = $db1->query($sql);
            if ($row = $db1->fetch_row($result)) {
                $project_id = $row['project_id'];
            }
        }
        
        // If project_id is provided, notify the client of the project
        if ($project_id) {
            $sql = "SELECT c_id FROM projects WHERE p_id = " . $db1->escape($project_id);
            $result = $db1->query($sql);
            if ($row = $db1->fetch_row($result)) {
                $client_id = $row['c_id'];
                if ($client_id && $client_id != $updater_id) {
                    $users_to_notify[] = (object)['id' => $client_id];
                }
            }
        }
        
        // Remove duplicates
        $users_to_notify = array_unique($users_to_notify, SORT_REGULAR);
        
        // Filter users based on task permissions and admin project assignment
        if ($project_id) {
            $users_to_notify = self::filterUsersForTaskNotifications($users_to_notify, $project_id, $task_id);
        } else {
            $users_to_notify = self::filterUsersByTaskPermission($users_to_notify, $task_id, $project_id);
        }
        $users_to_notify = self::ensureActorNotified($users_to_notify, (int) $updater_id);
        
        foreach ($users_to_notify as $user) {
            $data = [
                'user_name' => self::getUserName($updater_id),
                'task_title' => $task_title
            ];
            
            $message = Notifications::getNotificationMessage(Notifications::TYPE_TASK_UPDATED, $data);
            $title = Notifications::getNotificationTitle(Notifications::TYPE_TASK_UPDATED);
            
            Notifications::createNotificationWithProject(
                $user->id,
                $updater_id,
                Notifications::TYPE_TASK_UPDATED,
                $title,
                $message,
                $task_id, // related_id is always the task id
                'task',
                $project_id, // related_project_id
                (int) $user->id === (int) $updater_id
            );
        }
    }
    
    /**
     * Create notification for task status change
     */
    public static function taskStatusChanged($task_id, $task_title, $updater_id, $new_status, $assigned_to = null, $project_id = null) {
        global $db1;
        
        try {
            $users_to_notify = [];
            $candidateIds = [];

            if ($assigned_to) {
                foreach (explode(',', (string) $assigned_to) as $assigned_id) {
                    $assigned_id = (int) trim($assigned_id);
                    if ($assigned_id > 0 && $assigned_id !== (int) $updater_id) {
                        $candidateIds[$assigned_id] = true;
                    }
                }
            }

            if (!$project_id) {
                $sql = "SELECT project_id FROM tasks WHERE id = " . $db1->escape($task_id);
                $result = $db1->query($sql);
                if ($row = $db1->fetch_row($result)) {
                    $project_id = $row['project_id'];
                }
            }

            $sql = "SELECT creator_id FROM tasks WHERE id = " . $db1->escape($task_id);
            $result = $db1->query($sql);
            if ($row = $db1->fetch_row($result)) {
                $creator_id = (int) ($row['creator_id'] ?? 0);
                if ($creator_id > 0 && $creator_id !== (int) $updater_id) {
                    $candidateIds[$creator_id] = true;
                }
            }

            if ($project_id) {
                $sql = "SELECT s_ids, c_id, c_ids, main_client_id FROM projects WHERE p_id = " . $db1->escape($project_id);
                $result = $db1->query($sql);
                if ($row = $db1->fetch_row($result)) {
                    if (!empty($row['s_ids'])) {
                        foreach (explode(',', (string) $row['s_ids']) as $sid) {
                            $sid = (int) trim($sid);
                            if ($sid > 0 && $sid !== (int) $updater_id) {
                                $candidateIds[$sid] = true;
                            }
                        }
                    }
                    $main_client_id = (int) ((!empty($row['main_client_id']) ? $row['main_client_id'] : ($row['c_id'] ?? 0)));
                    if ($main_client_id > 0 && $main_client_id !== (int) $updater_id) {
                        $candidateIds[$main_client_id] = true;
                    }
                    if (!empty($row['c_ids'])) {
                        foreach (array_filter(explode(',', (string) $row['c_ids'])) as $client_id) {
                            $client_id = (int) trim($client_id);
                            if ($client_id > 0 && $client_id !== (int) $updater_id) {
                                $candidateIds[$client_id] = true;
                            }
                        }
                    }
                }
            }

            foreach (array_keys($candidateIds) as $uid) {
                $users_to_notify[] = (object) ['id' => $uid];
            }
            
            // Remove duplicates
            $users_to_notify = array_unique($users_to_notify, SORT_REGULAR);
            
            // Filter users based on task permissions and admin project assignment
            if ($project_id) {
                $users_to_notify = self::filterUsersForTaskNotifications($users_to_notify, $project_id, $task_id);
            } else {
                $users_to_notify = self::filterUsersByTaskPermission($users_to_notify, $task_id, $project_id);
            }
            $users_to_notify = self::ensureActorNotified($users_to_notify, (int) $updater_id);
            
            foreach ($users_to_notify as $user) {
                $data = [
                    'user_name' => self::getUserName($updater_id),
                    'task_title' => $task_title,
                    'new_status' => $new_status
                ];
                
                $message = Notifications::getNotificationMessage(Notifications::TYPE_TASK_STATUS_CHANGED, $data);
                $title = Notifications::getNotificationTitle(Notifications::TYPE_TASK_STATUS_CHANGED);
                
                Notifications::createNotificationWithProject(
                    $user->id,
                    $updater_id,
                    Notifications::TYPE_TASK_STATUS_CHANGED,
                    $title,
                    $message,
                    $task_id, // related_id is always the task id
                    'task',
                    $project_id, // related_project_id
                    (int) $user->id === (int) $updater_id
                );
            }
        } catch (Exception $e) {
            // Log error but don't break the flow
            error_log("Error in taskStatusChanged: " . $e->getMessage());
        }
    }
    
    /**
     * Create notification for invoice creation
     */
    public static function invoiceCreated($invoice_id, $invoice_number, $creator_id, $project_id = null) {
        global $db1;
        $users = [];
        // Get all admins only (no staff) - including creator if admin
        $sql = "SELECT id FROM users WHERE accountStatus = 1";
        $result = $db1->query($sql);
        while ($row = $db1->fetch_row($result)) {
            $users[] = (object)$row;
        }
        // If project_id is provided, notify the client of the project
        if ($project_id) {
            $sql = "SELECT c_id FROM projects WHERE p_id = " . $db1->escape($project_id);
            $result = $db1->query($sql);
            if ($row = $db1->fetch_row($result)) {
                $client_id = $row['c_id'];
                if ($client_id) {
                    // Check if client is already in the list
                    $client_exists = false;
                    foreach ($users as $user) {
                        if ($user->id == $client_id) {
                            $client_exists = true;
                            break;
                        }
                    }
                    if (!$client_exists) {
                        $users[] = (object)['id' => $client_id];
                    }
                }
            }
        } else {
            // Direct client invoice (no project) - get client from invoice record
            $sql = "SELECT c_id FROM milestones WHERE id = " . $db1->escape($invoice_id);
            $result = $db1->query($sql);
            if ($row = $db1->fetch_row($result)) {
                $client_id = $row['c_id'];
                if ($client_id) {
                    // Check if client is already in the list
                    $client_exists = false;
                    foreach ($users as $user) {
                        if ($user->id == $client_id) {
                            $client_exists = true;
                            break;
                        }
                    }
                    if (!$client_exists) {
                        $users[] = (object)['id' => $client_id];
                    }
                }
            }
        }
        
        // Ensure creator is notified (add if not already in list)
        $creator_exists = false;
        foreach ($users as $user) {
            if ($user->id == $creator_id) {
                $creator_exists = true;
                break;
            }
        }
        if (!$creator_exists) {
            $users[] = (object)['id' => $creator_id];
        }
        
        // Remove duplicates
        $users = array_unique($users, SORT_REGULAR);
        
        // Filter users based on project permissions (for invoice notifications - all admins get payment notifications)
        if ($project_id) {
            $users = self::filterUsersForPaymentNotifications($users, $project_id);
        }
        
        foreach ($users as $user) {
            $data = [
                'user_name' => self::getUserName($creator_id),
                'invoice_number' => $invoice_number
            ];
            $message = Notifications::getNotificationMessage(Notifications::TYPE_INVOICE_CREATED, $data);
            $title = Notifications::getNotificationTitle(Notifications::TYPE_INVOICE_CREATED);
            Notifications::createNotificationWithProject(
                $user->id,
                $creator_id,
                Notifications::TYPE_INVOICE_CREATED,
                $title,
                $message,
                $invoice_id,
                'invoice',
                $project_id // set related_project_id
            );
        }
    }
    
    /**
     * Create notification for invoice update
     */
    public static function invoiceUpdated($invoice_id, $invoice_number, $updater_id) {
        global $db1;
        // Get all users who should be notified (staff and admin) - including updater
        $sql = "SELECT id FROM users WHERE accountStatus IN (1, 3)";
        $result = $db1->query($sql);
        $users = [];
        while ($row = $db1->fetch_row($result)) {
            $users[] = (object)$row;
        }
        
        foreach ($users as $user) {
            $data = [
                'user_name' => self::getUserName($updater_id),
                'invoice_number' => $invoice_number
            ];
            
            $message = Notifications::getNotificationMessage(Notifications::TYPE_INVOICE_UPDATED, $data);
            $title = Notifications::getNotificationTitle(Notifications::TYPE_INVOICE_UPDATED);
            
            Notifications::createNotification(
                $user->id,
                $updater_id,
                Notifications::TYPE_INVOICE_UPDATED,
                $title,
                $message,
                $invoice_id,
                'invoice'
            );
        }
    }
    
    /**
     * Create notification for invoice payment
     */
    public static function invoicePaid($invoice_id, $invoice_number, $updater_id, $project_id = null) {
        global $db1;
        $users = [];
        
        // Get invoice creator from the invoice record
        $invoice_creator_id = null;
        $sql = "SELECT created_by, p_id FROM milestones WHERE id = " . $db1->escape($invoice_id);
        $result = $db1->query($sql);
        if ($row = $db1->fetch_row($result)) {
            $invoice_creator_id = $row['created_by'];
            // If project_id not provided, get it from invoice
            if (!$project_id && $row['p_id']) {
                $project_id = $row['p_id'];
            }
        }
        
        // Notify invoice creator (if exists)
        if ($invoice_creator_id) {
            $users[] = (object)['id' => $invoice_creator_id];
        }
        
        // Notify superadmin (user id = 1)
        $users[] = (object)['id' => 1];
        
        // If project_id is provided, notify the client of the project
        if ($project_id) {
            $sql = "SELECT c_id FROM projects WHERE p_id = " . $db1->escape($project_id);
            $result = $db1->query($sql);
            if ($row = $db1->fetch_row($result)) {
                $client_id = $row['c_id'];
                if ($client_id) {
                    // Check if client is already in the list
                    $client_exists = false;
                    foreach ($users as $user) {
                        if ($user->id == $client_id) {
                            $client_exists = true;
                            break;
                        }
                    }
                    if (!$client_exists) {
                        $users[] = (object)['id' => $client_id];
                    }
                }
            }
        }
        
        // Remove duplicates
        $users = array_unique($users, SORT_REGULAR);
        
        foreach ($users as $user) {
            $data = [
                'user_name' => self::getUserName($updater_id),
                'invoice_number' => $invoice_number
            ];
            $message = Notifications::getNotificationMessage(Notifications::TYPE_INVOICE_PAID, $data);
            $title = Notifications::getNotificationTitle(Notifications::TYPE_INVOICE_PAID);
            Notifications::createNotificationWithProject(
                $user->id,
                $updater_id,
                Notifications::TYPE_INVOICE_PAID,
                $title,
                $message,
                $invoice_id,
                'invoice',
                $project_id // set related_project_id
            );
        }
    }

    /**
     * Admin in-app alert when a client payment reminder email is sent.
     */
    public static function invoiceNotClearAfterReminder($invoice_id, $invoice_number, $reminderNumber, $project_id = null)
    {
        global $db1;
        $users = [];
        $sql = "SELECT id FROM users WHERE accountStatus = 1";
        $result = $db1->query($sql);
        while ($row = $db1->fetch_row($result)) {
            $users[] = (object) $row;
        }
        if ($project_id) {
            $users = self::filterUsersForPaymentNotifications($users, $project_id);
        }
        foreach ($users as $user) {
            $data = [
                'invoice_number' => $invoice_number,
                'reminder_number' => (int) $reminderNumber,
            ];
            $message = Notifications::getNotificationMessage(Notifications::TYPE_INVOICE_NOT_CLEAR, $data);
            $title = Notifications::getNotificationTitle(Notifications::TYPE_INVOICE_NOT_CLEAR);
            Notifications::createNotificationWithProject(
                $user->id,
                0,
                Notifications::TYPE_INVOICE_NOT_CLEAR,
                $title,
                $message,
                $invoice_id,
                'invoice',
                $project_id
            );
        }
    }
    
    /**
     * Get user name by ID
     */
    private static function getUserName($user_id) {
        global $db1;

        $sql = "SELECT firstName FROM users WHERE id = " . $db1->escape($user_id);
        $result = $db1->query($sql);
        $row = $result ? $db1->fetch_row($result) : null;

        if ($row) {
            return $row['firstName'];
        }

        return 'Unknown User';
    }
}
?>