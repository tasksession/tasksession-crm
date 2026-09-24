<?php
require_once('database.php');

class Notifications extends DatabaseObject {
    
    protected static $tblName = "notifications";
    protected static $tblFields = array('id', 'user_id', 'from_user_id', 'type', 'title', 'message', 'related_id', 'related_type', 'related_project_id', 'is_read', 'created_at');
    
    public $id;
    public $user_id;
    public $from_user_id;
    public $type;
    public $title;
    public $message;
    public $related_id;
    public $related_type;
    public $related_project_id;
    public $is_read;
    public $created_at;
    
    // Notification types
    const TYPE_PROJECT_CREATED = 'project_created';
    const TYPE_PROJECT_UPDATED = 'project_updated';
    const TYPE_TASK_CREATED = 'task_created';
    const TYPE_TASK_UPDATED = 'task_updated';
    const TYPE_TASK_STATUS_CHANGED = 'task_status_changed';
    const TYPE_SUBTASK_CREATED = 'subtask_created';
    const TYPE_SUBTASK_COMPLETED = 'subtask_completed';
    const TYPE_TASK_REMINDER_1 = 'task_reminder_1';
    const TYPE_TASK_REMINDER_2 = 'task_reminder_2';
    const TYPE_TASK_REMINDER_3 = 'task_reminder_3';
    const TYPE_INVOICE_CREATED = 'invoice_created';
    const TYPE_INVOICE_UPDATED = 'invoice_updated';
    const TYPE_INVOICE_PAID = 'invoice_paid';
    const TYPE_INVOICE_NOT_CLEAR = 'invoice_not_clear';
    const TYPE_EMAIL_RECEIVED = 'email_received';
    const TYPE_EMAIL_THREAD_UPDATED = 'email_thread_updated';
    /** Envelope-only (New messages); excluded from bell */
    const TYPE_CHAT_REACTION = 'chat_reaction';
    const TYPE_LEAD_CREATED = 'lead_created';
    const TYPE_PROJECT_MEDIA_FILE_UPLOADED = 'project_media_file_uploaded';
    const TYPE_MEDIA_FOLDER_SHARED = 'media_folder_shared';
    const TYPE_MEDIA_FILE_SHARED = 'media_file_shared';
    const TYPE_MEDIA_VAULT_EXTENDED_SHARE = 'media_vault_extended_share';
    const TYPE_MEDIA_SHARED_FOLDER_NEW_UPLOAD = 'media_shared_folder_new_upload';
    const TYPE_MARKETING_CAMPAIGN_SCHEDULED = 'marketing_campaign_scheduled';
    const TYPE_MARKETING_CAMPAIGN_COMPLETED = 'marketing_campaign_completed';
    const TYPE_ATTENDANCE_LEAVE_REQUESTED = 'attendance_leave_requested';
    const TYPE_ATTENDANCE_REGULARIZATION_REQUESTED = 'attendance_regularization_requested';
    const TYPE_ATTENDANCE_LEAVE_APPROVED = 'attendance_leave_approved';
    const TYPE_ATTENDANCE_LEAVE_REJECTED = 'attendance_leave_rejected';
    const TYPE_ATTENDANCE_REGULARIZATION_APPROVED = 'attendance_regularization_approved';
    const TYPE_ATTENDANCE_REGULARIZATION_REJECTED = 'attendance_regularization_rejected';
    const TYPE_ECOMMERCE_ORDER_RECEIVED = 'ecommerce_order_received';
    const TYPE_AI_AGENT_ALERT = 'ai_agent_alert';
    const TYPE_AI_AGENT_DIGEST = 'ai_agent_digest';
    const TYPE_AI_AGENT_AUTO_REPLY = 'ai_agent_auto_reply';

    public function __construct() {
        $this->created_at = date('Y-m-d H:i:s');
        $this->is_read = 0;
    }
    
    /**
     * Create a new notification
     */
    public static function createNotification($user_id, $from_user_id, $type, $title, $message, $related_id = null, $related_type = null) {
        global $db1;
        
        // Validate inputs
        if (empty($user_id) || empty($type) || empty($title) || empty($message)) {
            return false;
        }
        
        // Check database connection - ConnectMe class doesn't always have $connection property
        if (!$db1 || !is_object($db1)) {
            return false;
        }
        
        $notification = new self();
        $notification->user_id = $user_id;
        $notification->from_user_id = $from_user_id;
        $notification->type = $type;
        $notification->title = $title;
        $notification->message = $message;
        $notification->related_id = $related_id;
        $notification->related_type = $related_type;
        
        // Build the SQL query manually since we're not using the parent class's create method
        $sql = "INSERT INTO " . static::$tblName . " (user_id, from_user_id, type, title, message, related_id, related_type, is_read, created_at) VALUES (";
        $sql .= (int)$user_id . ", ";
        // from_user_id cannot be NULL, so use user_id as fallback (self-notification)
        $sql .= ($from_user_id && (int)$from_user_id > 0 ? (int)$from_user_id : (int)$user_id) . ", ";
        $sql .= "'" . $db1->escape($type) . "', ";
        $sql .= "'" . $db1->escape($title) . "', ";
        $sql .= "'" . $db1->escape($message) . "', ";
        $sql .= ($related_id ? (int)$related_id : "NULL") . ", ";
        $sql .= ($related_type ? "'" . $db1->escape($related_type) . "'" : "NULL") . ", ";
        $sql .= "0, ";
        $sql .= "'" . date('Y-m-d H:i:s') . "')";
        
        $result = $db1->query($sql);
        
        if ($result) {
            // Get insert ID - try multiple methods
            $insertId = null;
            if (method_exists($db1, 'insert_id')) {
                $insertId = $db1->insert_id();
            } elseif (method_exists($db1, 'insertId')) {
                $insertId = $db1->insertId();
            } elseif (isset($db1->connection)) {
                $insertId = mysqli_insert_id($db1->connection);
            }
            
            return $insertId ? $insertId : false;
        }
        
        return false;
    }
    
    /**
     * Create a new notification with project
     */
    public static function createNotificationWithProject($user_id, $from_user_id, $type, $title, $message, $related_id = null, $related_type = null, $related_project_id = null, $activity_only = false) {
        global $db1;
        $notification = new self();
        $notification->user_id = $user_id;
        $notification->from_user_id = $from_user_id;
        $notification->type = $type;
        $notification->title = $title;
        $notification->message = $message;
        $notification->related_id = $related_id;
        $notification->related_type = $related_type;
        $notification->related_project_id = $related_project_id;
        $isRead = $activity_only ? 1 : 0;
        // Build the SQL query manually since we're not using the parent class's create method
        $sql = "INSERT INTO " . static::$tblName . " (user_id, from_user_id, type, title, message, related_id, related_type, related_project_id, is_read, created_at) VALUES (";
        $sql .= $db1->escape($user_id) . ", ";
        // from_user_id cannot be NULL, so use user_id as fallback (self-notification)
        $sql .= ($from_user_id && (int)$from_user_id > 0 ? $db1->escape($from_user_id) : $db1->escape($user_id)) . ", ";
        $sql .= "'" . $db1->escape($type) . "', ";
        $sql .= "'" . $db1->escape($title) . "', ";
        $sql .= "'" . $db1->escape($message) . "', ";
        $sql .= ($related_id ? $db1->escape($related_id) : "NULL") . ", ";
        $sql .= ($related_type ? "'" . $db1->escape($related_type) . "'" : "NULL") . ", ";
        $sql .= ($related_project_id ? $db1->escape($related_project_id) : "NULL") . ", ";
        $sql .= $isRead . ", ";
        $sql .= "'" . date('Y-m-d H:i:s') . "')";
        if ($db1->query($sql)) {
            return $db1->insert_id();
        } else {
            return false;
        }
    }
    
    /**
     * Get unread notifications count for a user
     * Excludes email notifications (they show in message icon, not bell icon)
     */
    public static function getUnreadCount($user_id, $excludeTypes = []) {
        global $db1;
        
        // Default exclude envelope-only types (email + chat reactions → New messages icon)
        $defaultExcludeTypes = [self::TYPE_EMAIL_RECEIVED, self::TYPE_EMAIL_THREAD_UPDATED, self::TYPE_CHAT_REACTION];
        $excludeTypes = array_merge($excludeTypes, $defaultExcludeTypes);
        
        // Build SQL query excluding email notifications
        $sql = "SELECT * FROM " . static::$tblName . " WHERE user_id = " . $db1->escape($user_id) . " AND is_read = 0";
        
        // Exclude specified types
        if (!empty($excludeTypes)) {
            $excludedTypesStr = implode("','", array_map([$db1, 'escape'], $excludeTypes));
            $sql .= " AND type NOT IN ('" . $excludedTypesStr . "')";
        }
        
        $result = $db1->query($sql);
        $notifications = [];
        
        if ($result) {
            while ($row = $db1->fetch_row($result)) {
                $notification = new self();
                foreach ($row as $key => $value) {
                    if (property_exists($notification, $key)) {
                        $notification->$key = $value;
                    }
                }
                $notifications[] = $notification;
            }
        }
        
        // Filter notifications based on permissions
        $filtered_notifications = self::filterNotificationsByPermission($notifications, $user_id);
        
        return count($filtered_notifications);
    }
    
    /**
     * Get notifications for a user
     */
    /**
     * Self-actions logged for activity.php only (hidden from bell dropdown).
     */
    public static function notificationIsActivityOnlySelf(object $notification): bool
    {
        if ((int) ($notification->user_id ?? 0) !== (int) ($notification->from_user_id ?? 0)) {
            return false;
        }

        $type = (string) ($notification->type ?? '');

        return in_array($type, [self::TYPE_TASK_UPDATED, self::TYPE_TASK_STATUS_CHANGED], true);
    }

    private static function filterActivityOnlySelfFromBell(array $notifications): array
    {
        return array_values(array_filter($notifications, static function ($notification) {
            return !self::notificationIsActivityOnlySelf($notification);
        }));
    }

    public static function getUserNotifications($user_id, $limit = 10, $offset = 0, $excludeEmailNotifications = true, $excludeActivityOnlySelf = false) {
        global $db1;
        
        // First, get all notifications for the user
        // Exclude email notifications by default (they show in message icon, not bell icon)
        $sql = "SELECT n.*, u.firstName, pp.filename as profile_pic 
                FROM " . static::$tblName . " n 
                LEFT JOIN users u ON n.from_user_id = u.id 
                LEFT JOIN profile_pics pp ON u.id = pp.fkUserId 
                WHERE n.user_id = " . $db1->escape($user_id);
        
        // Exclude envelope-only types (email + chat reactions) if requested
        if ($excludeEmailNotifications) {
            $sql .= " AND n.type NOT IN ('" . self::TYPE_EMAIL_RECEIVED . "', '" . self::TYPE_EMAIL_THREAD_UPDATED . "', '" . self::TYPE_CHAT_REACTION . "')";
        }
        
        // Tie-breaker: same-second inserts (e.g. lead + task) must stay newest-first by id
        $sql .= " ORDER BY n.created_at DESC, n.id DESC";
        $sqlCap = min(500, max(50, (int)$limit + (int)$offset + 50));
        $sql .= ' LIMIT ' . $sqlCap;

        $result = $db1->query($sql);
        $all_notifications = [];
        
        while ($row = $db1->fetch_row($result)) {
            $notification = new self();
            foreach ($row as $key => $value) {
                if (property_exists($notification, $key)) {
                    $notification->$key = $value;
                }
            }
            $all_notifications[] = $notification;
        }
        
        // Filter notifications based on permissions
        $filtered_notifications = self::filterNotificationsByPermission($all_notifications, $user_id);
        if ($excludeActivityOnlySelf) {
            $filtered_notifications = self::filterActivityOnlySelfFromBell($filtered_notifications);
        }
        
        // Apply limit and offset
        $notifications = array_slice($filtered_notifications, $offset, $limit);
        
        return $notifications;
    }

    /**
     * Parse order count from an ecommerce batch notification title.
     */
    public static function parseEcommerceBatchNotificationCount($title)
    {
        if (function_exists('tasksession_ecommerce_parse_batch_notification_count')) {
            return tasksession_ecommerce_parse_batch_notification_count($title);
        }
        $title = trim((string) $title);
        if ($title === 'New order received') {
            return 1;
        }
        if (preg_match('/^(\d+)\s+new orders received$/', $title, $matches)) {
            return max(1, (int) $matches[1]);
        }

        return 1;
    }

    private static function groupProjectMediaUploadNotifications($notifications) {
        if (empty($notifications) || !is_array($notifications)) {
            return $notifications;
        }

        $grouped = [];
        $groupIndexByKey = [];

        foreach ($notifications as $n) {
            $isUpload = isset($n->type) && $n->type === self::TYPE_PROJECT_MEDIA_FILE_UPLOADED;
            $fromUser = isset($n->from_user_id) ? (int)$n->from_user_id : 0;
            $projectId = isset($n->related_project_id) ? (int)$n->related_project_id : 0;
            $folderId = isset($n->related_id) ? (int)$n->related_id : 0;

            if ($isUpload && $fromUser > 0 && $projectId > 0) {
                // Keep read/unread groups separate so read state is predictable.
                $readKey = (int)$n->is_read;
                $key = 'pmu:' . $fromUser . ':' . $projectId . ':' . $folderId . ':' . $readKey;

                if (!isset($groupIndexByKey[$key])) {
                    $copy = clone $n;
                    $copy->upload_count = 1;
                    $copy->group_ids = [(int)$n->id];
                    $grouped[] = $copy;
                    $groupIndexByKey[$key] = count($grouped) - 1;
                } else {
                    $idx = $groupIndexByKey[$key];
                    $grouped[$idx]->upload_count = (int)$grouped[$idx]->upload_count + 1;
                    $grouped[$idx]->group_ids[] = (int)$n->id;
                    // Keep latest created_at/title/message/id from newest row
                    if (strtotime((string)$n->created_at) > strtotime((string)$grouped[$idx]->created_at)) {
                        $grouped[$idx]->created_at = $n->created_at;
                        $grouped[$idx]->id = $n->id;
                        $grouped[$idx]->title = $n->title;
                        $grouped[$idx]->message = $n->message;
                    }
                }
            } else {
                $n->upload_count = 1;
                $n->order_count = isset($n->order_count) ? (int) $n->order_count : 1;
                $n->group_ids = [(int)$n->id];
                if (isset($n->type) && $n->type === self::TYPE_ECOMMERCE_ORDER_RECEIVED && $n->related_type === 'ecommerce_orders') {
                    $parsedCount = self::parseEcommerceBatchNotificationCount($n->title ?? '');
                    $n->order_count = $parsedCount;
                    $n->upload_count = $parsedCount;
                }
                $grouped[] = $n;
            }
        }

        usort($grouped, function($a, $b) {
            $ta = isset($a->created_at) ? strtotime((string)$a->created_at) : 0;
            $tb = isset($b->created_at) ? strtotime((string)$b->created_at) : 0;
            if ($tb !== $ta) {
                return $tb <=> $ta;
            }
            $ida = isset($a->id) ? (int)$a->id : 0;
            $idb = isset($b->id) ? (int)$b->id : 0;
            return $idb <=> $ida;
        });

        return $grouped;
    }

    public static function getUserNotificationsGrouped($user_id, $limit = 10, $offset = 0, $excludeEmailNotifications = true, $excludeActivityOnlySelf = false) {
        $fetchLimit = min(300, max(50, (int)$limit + (int)$offset + 40));
        $all = self::getUserNotifications($user_id, $fetchLimit, 0, $excludeEmailNotifications, $excludeActivityOnlySelf);
        $grouped = self::groupProjectMediaUploadNotifications($all);
        return array_slice($grouped, $offset, $limit);
    }

    /**
     * Lighter feed for activity.php — date filter in SQL, no profile_pics join, bounded oversample.
     *
     * @return array<int, object>
     */
    public static function getActivityPageNotifications($user_id, $limit = 20, $offset = 0, $days = 60, $excludeEmailNotifications = true)
    {
        global $db1;

        $user_id = (int) $user_id;
        $limit = max(1, min(50, (int) $limit));
        $offset = max(0, (int) $offset);
        $days = max(1, min(120, (int) $days));
        if ($user_id <= 0) {
            return [];
        }

        // Oversample for permission filtering + media grouping; keep hard cap to protect DB.
        $needed = $offset + $limit;
        $oversample = min(180, max($needed + 20, (int) ceil($needed * 1.5)));

        $sql = 'SELECT n.* FROM ' . static::$tblName . ' n
                WHERE n.user_id = ' . $db1->escape($user_id) . '
                  AND n.created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';

        if ($excludeEmailNotifications) {
            $sql .= " AND n.type NOT IN ('" . self::TYPE_EMAIL_RECEIVED . "', '" . self::TYPE_EMAIL_THREAD_UPDATED . "', '" . self::TYPE_CHAT_REACTION . "')";
        }

        $sql .= ' ORDER BY n.created_at DESC, n.id DESC LIMIT ' . (int) $oversample;

        $result = $db1->query($sql);
        $all_notifications = [];
        if ($result) {
            while ($row = $db1->fetch_row($result)) {
                $notification = new self();
                foreach ($row as $key => $value) {
                    if (property_exists($notification, $key)) {
                        $notification->$key = $value;
                    }
                }
                $all_notifications[] = $notification;
            }
        }

        $filtered = self::filterNotificationsByPermission($all_notifications, $user_id);
        $grouped = self::groupProjectMediaUploadNotifications($filtered);

        return array_slice($grouped, $offset, $limit);
    }

    public static function getUnreadCountGrouped($user_id, $excludeTypes = []) {
        return self::getUnreadCountFiltered($user_id);
    }

    /**
     * Unread bell count after permission filtering (staff/client visibility rules).
     */
    public static function getUnreadCountFiltered($user_id): int
    {
        global $db1;
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return 0;
        }

        $sql = "SELECT n.*, u.firstName, pp.filename as profile_pic
                FROM " . static::$tblName . " n
                LEFT JOIN users u ON n.from_user_id = u.id
                LEFT JOIN profile_pics pp ON u.id = pp.fkUserId
                WHERE n.user_id = " . $db1->escape($user_id) . "
                  AND n.is_read = 0
                  AND n.type NOT IN ('" . self::TYPE_EMAIL_RECEIVED . "', '" . self::TYPE_EMAIL_THREAD_UPDATED . "', '" . self::TYPE_CHAT_REACTION . "')
                  AND NOT (n.type = '" . self::TYPE_ECOMMERCE_ORDER_RECEIVED . "' AND n.related_type = 'ecommerce_order' AND n.related_id IS NOT NULL AND n.title = '-')
                ORDER BY n.created_at DESC, n.id DESC
                LIMIT 500";

        $result = $db1->query($sql);
        $notifications = [];
        if ($result) {
            while ($row = $db1->fetch_row($result)) {
                $notification = new self();
                foreach ($row as $key => $value) {
                    if (property_exists($notification, $key)) {
                        $notification->$key = $value;
                    }
                }
                $notifications[] = $notification;
            }
        }

        $filtered = self::filterNotificationsByPermission($notifications, $user_id);

        return count(self::filterActivityOnlySelfFromBell($filtered));
    }

    public static function getUnreadCountFast($user_id): int
    {
        global $db1;
        $user_id = (int)$user_id;
        if ($user_id <= 0) {
            return 0;
        }
        $sql = "SELECT COUNT(*) AS c FROM " . static::$tblName . "
                WHERE user_id = " . $db1->escape($user_id) . "
                  AND is_read = 0
                  AND type NOT IN ('" . self::TYPE_EMAIL_RECEIVED . "', '" . self::TYPE_EMAIL_THREAD_UPDATED . "', '" . self::TYPE_CHAT_REACTION . "')
                  AND NOT (type = '" . self::TYPE_ECOMMERCE_ORDER_RECEIVED . "' AND related_type = 'ecommerce_order' AND related_id IS NOT NULL AND title = '-')";
        $result = $db1->query($sql);
        $row = $result ? $db1->fetch_row($result) : null;

        return $row ? (int)$row['c'] : 0;
    }

    /**
     * @return array{notifications:array,unread_count:int}
     */
    public static function getNotificationsPollPayload($user_id, $limit = 10, $offset = 0): array
    {
        return [
            'notifications' => self::getUserNotificationsGrouped($user_id, $limit, $offset, true, true),
            'unread_count' => self::getUnreadCountFiltered($user_id),
        ];
    }

    private static function notificationUserAccountStatus(int $user_id): int
    {
        global $db1;
        $sql = "SELECT accountStatus FROM users WHERE id = " . $db1->escape($user_id) . " LIMIT 1";
        $result = $db1->query($sql);
        $user = $result ? $db1->fetch_row($result) : null;

        return $user ? (int) ($user['accountStatus'] ?? 0) : 0;
    }

    private static function notificationProjectRow(int $project_id): ?array
    {
        global $db1;
        if ($project_id <= 0) {
            return null;
        }
        $sql = "SELECT p_id, s_ids, c_id, c_ids, main_client_id FROM projects WHERE p_id = " . $db1->escape($project_id) . " LIMIT 1";
        $result = $db1->query($sql);

        return $result ? $db1->fetch_row($result) : null;
    }

    private static function notificationUserOnProjectStaff(int $user_id, ?array $projectRow): bool
    {
        if (!$projectRow) {
            return false;
        }
        $staffIds = array_filter(array_map('trim', explode(',', (string) ($projectRow['s_ids'] ?? ''))));

        return in_array((string) $user_id, $staffIds, true);
    }

    private static function notificationUserOwnsProject(int $user_id, ?array $projectRow): bool
    {
        if (!$projectRow) {
            return false;
        }

        if ((int) ($projectRow['c_id'] ?? 0) === $user_id) {
            return true;
        }
        if ((int) ($projectRow['main_client_id'] ?? 0) === $user_id) {
            return true;
        }
        $additionalClientIds = array_filter(array_map('trim', explode(',', (string) ($projectRow['c_ids'] ?? ''))));

        return in_array((string) $user_id, $additionalClientIds, true);
    }

    private static function notificationUserHasProjectAccess(int $user_id, int $account_status, int $project_id): bool
    {
        if ($project_id <= 0) {
            return false;
        }
        if ($account_status === 1) {
            return true;
        }

        $projectRow = self::notificationProjectRow($project_id);
        if (!$projectRow) {
            return false;
        }
        if ($account_status === 2) {
            return self::notificationUserOwnsProject($user_id, $projectRow);
        }
        if ($account_status === 3) {
            require_once __DIR__ . '/permissions.php';
            if (has_permission('project_view_all')) {
                return true;
            }

            return self::notificationUserOnProjectStaff($user_id, $projectRow);
        }

        return false;
    }

    private static function notificationIsDirectMediaVaultShare(object $notification): bool
    {
        $type = (string) ($notification->type ?? '');

        return in_array($type, [
            self::TYPE_MEDIA_FOLDER_SHARED,
            self::TYPE_MEDIA_FILE_SHARED,
            self::TYPE_MEDIA_VAULT_EXTENDED_SHARE,
            self::TYPE_MEDIA_SHARED_FOLDER_NEW_UPLOAD,
        ], true);
    }

    private static function notificationResolveProjectId(object $notification): int
    {
        global $db1;

        $projectId = (int) ($notification->related_project_id ?? 0);
        if ($projectId > 0) {
            return $projectId;
        }

        $relatedType = strtolower((string) ($notification->related_type ?? ''));
        $relatedId = (int) ($notification->related_id ?? 0);

        if ($relatedType === 'project' && $relatedId > 0) {
            return $relatedId;
        }

        if ($relatedType === 'task' && $relatedId > 0) {
            $sql = "SELECT project_id FROM tasks WHERE id = " . $db1->escape($relatedId) . " LIMIT 1";
            $result = $db1->query($sql);
            $row = $result ? $db1->fetch_row($result) : null;

            return $row ? (int) ($row['project_id'] ?? 0) : 0;
        }

        if ($relatedType === 'invoice' && $relatedId > 0) {
            if (!class_exists('milestone')) {
                require_once __DIR__ . '/milestone.php';
            }
            $invoice = milestone::findById($relatedId);
            if ($invoice && !empty($invoice->project_id)) {
                return (int) $invoice->project_id;
            }
        }

        return 0;
    }

    private static function notificationStaffCanViewTask(object $notification, int $user_id): bool
    {
        require_once __DIR__ . '/permissions.php';

        if (has_permission('task_view_all')) {
            return true;
        }

        $taskId = (int) ($notification->related_id ?? 0);
        if ($taskId <= 0 || strtolower((string) ($notification->related_type ?? '')) !== 'task') {
            $type = (string) ($notification->type ?? '');
            if (strpos($type, 'task_') === 0 || strpos($type, 'subtask_') === 0) {
                $taskId = (int) ($notification->related_id ?? 0);
            }
        }
        if ($taskId <= 0) {
            return false;
        }

        if (!class_exists('Task')) {
            require_once __DIR__ . '/task.php';
        }
        $task = Task::findById($taskId);
        if (!$task || !function_exists('staff_can_view_task')) {
            return false;
        }

        return staff_can_view_task($task, $user_id);
    }

    private static function notificationLeadBoardEnabled(): bool
    {
        static $enabled = null;
        if ($enabled !== null) {
            return $enabled;
        }
        $settings = class_exists('settings') ? settings::findById(1) : null;
        $enabled = $settings && !empty($settings->module_lead_board);

        return $enabled;
    }

    private static function notificationAttendanceEnabled(): bool
    {
        static $enabled = null;
        if ($enabled !== null) {
            return $enabled;
        }
        $settings = class_exists('settings') ? settings::findById(1) : null;
        $enabled = $settings && !empty($settings->module_attendance);

        return $enabled;
    }

    private static function notificationShouldInclude(object $notification, int $user_id, int $account_status): bool
    {
        require_once __DIR__ . '/permissions.php';

        $type = (string) ($notification->type ?? '');
        $relatedType = strtolower((string) ($notification->related_type ?? ''));

        if ($type === self::TYPE_CHAT_REACTION) {
            return true;
        }

        if ($type === self::TYPE_AI_AGENT_ALERT || $type === self::TYPE_AI_AGENT_DIGEST || strpos($type, 'ai_agent_') === 0) {
            return self::notificationAiAgentShouldInclude($notification, $user_id, $account_status);
        }

        if (self::notificationIsActivityOnlySelf($notification)) {
            return true;
        }

        if ($account_status === 1) {
            $allowed = true;
        } elseif ($account_status === 2) {
            if (
                strpos($type, 'lead_') === 0
                || $relatedType === 'lead'
                || strpos($type, 'marketing_campaign_') === 0
                || $relatedType === 'marketing_campaign'
                || $type === self::TYPE_ECOMMERCE_ORDER_RECEIVED
                || $relatedType === 'ecommerce_order'
                || $relatedType === 'ecommerce_orders'
                || strpos($type, 'attendance_') === 0
                || $relatedType === 'attendance_leave'
                || $relatedType === 'attendance_regularization'
            ) {
                return false;
            }

            $projectId = self::notificationResolveProjectId($notification);
            if ($projectId > 0) {
                return self::notificationUserHasProjectAccess($user_id, $account_status, $projectId);
            }

            if ($relatedType === 'task' || strpos($type, 'task_') === 0 || strpos($type, 'subtask_') === 0) {
                return false;
            }
            if ($relatedType === 'invoice' || strpos($type, 'invoice_') === 0) {
                return false;
            }
            if (self::notificationIsDirectMediaVaultShare($notification)) {
                return (int) ($notification->user_id ?? 0) === $user_id;
            }
            if (strpos($type, 'project_') === 0 || strpos($type, 'media_') === 0) {
                return false;
            }

            return false;
        } elseif ($account_status === 3) {
            if (strpos($type, 'lead_') === 0 || $relatedType === 'lead') {
                return self::notificationLeadBoardEnabled() && has_permission('lead_view_all');
            }
            if (strpos($type, 'marketing_campaign_') === 0 || $relatedType === 'marketing_campaign') {
                require_once __DIR__ . '/marketing_module_gate.php';

                return comon_marketing_module_enabled() && has_permission('marketing_campaigns_view');
            }
            if (
                $type === self::TYPE_ECOMMERCE_ORDER_RECEIVED
                || $relatedType === 'ecommerce_order'
                || $relatedType === 'ecommerce_orders'
            ) {
                if (!function_exists('comon_ecommerce_module_enabled')) {
                    require_once __DIR__ . '/addon_registry.php';
                }
                if (!comon_ecommerce_module_enabled()) {
                    return false;
                }

                return has_permission('ecommerce_orders_view');
            }
            if (
                strpos($type, 'attendance_') === 0
                || $relatedType === 'attendance_leave'
                || $relatedType === 'attendance_regularization'
            ) {
                return self::notificationAttendanceEnabled() && has_permission('attendance_view');
            }

            if ($relatedType === 'task' || strpos($type, 'task_') === 0 || strpos($type, 'subtask_') === 0) {
                return self::notificationStaffCanViewTask($notification, $user_id);
            }

            if ($relatedType === 'invoice' || strpos($type, 'invoice_') === 0) {
                if (has_permission('milestone_view')) {
                    return true;
                }
                $projectId = self::notificationResolveProjectId($notification);

                return $projectId > 0 && self::notificationUserHasProjectAccess($user_id, $account_status, $projectId);
            }

            if (
                strpos($type, 'project_') === 0
                || strpos($type, 'media_') === 0
                || $type === self::TYPE_PROJECT_MEDIA_FILE_UPLOADED
                || $relatedType === 'project'
            ) {
                if (self::notificationIsDirectMediaVaultShare($notification)) {
                    return (int) ($notification->user_id ?? 0) === $user_id;
                }
                $projectId = self::notificationResolveProjectId($notification);

                return $projectId > 0 && self::notificationUserHasProjectAccess($user_id, $account_status, $projectId);
            }

            $projectId = self::notificationResolveProjectId($notification);
            if ($projectId > 0) {
                return self::notificationUserHasProjectAccess($user_id, $account_status, $projectId);
            }

            return false;
        } else {
            return false;
        }

        if (
            $relatedType === 'ecommerce_order'
            && !empty($notification->related_id)
            && trim((string) ($notification->title ?? '')) === '-'
        ) {
            return false;
        }

        return $allowed;
    }
    
    /**
     * Filter notifications based on user permissions
     */
    private static function filterNotificationsByPermission($notifications, $user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0 || !is_array($notifications)) {
            return [];
        }

        $account_status = self::notificationUserAccountStatus($user_id);
        if ($account_status <= 0) {
            return [];
        }

        $filtered = [];
        foreach ($notifications as $notification) {
            if (!is_object($notification)) {
                continue;
            }
            if (self::notificationShouldInclude($notification, $user_id, $account_status)) {
                $filtered[] = $notification;
            }
        }

        return $filtered;
    }

    /**
     * AI agent bell rows: module + permission + (for alerts) agent still enabled.
     */
    private static function notificationAiAgentShouldInclude(object $notification, int $user_id, int $account_status): bool
    {
        if ($account_status === 2) {
            return false;
        }
        if (!function_exists('comon_ai_module_enabled')) {
            $gate = __DIR__ . '/ai_module_gate.php';
            if (!is_readable($gate)) {
                return false;
            }
            require_once $gate;
        }
        if (!function_exists('comon_ai_module_enabled') || !comon_ai_module_enabled()) {
            return false;
        }

        $notifyPath = dirname(__DIR__) . '/ai/includes/agent-notify.php';
        if (is_readable($notifyPath)) {
            require_once $notifyPath;
        }
        if (function_exists('ai_agent_notify_allowed') && !ai_agent_notify_allowed($user_id)) {
            return false;
        } elseif (!function_exists('ai_agent_notify_allowed')) {
            // Fallback: admin or staff with ai_agents via session helpers
            if ($account_status !== 1) {
                require_once __DIR__ . '/permissions.php';
                if (!has_permission('ai_agents') && !has_permission('ai_settings_manage')) {
                    return false;
                }
            }
        }

        $type = (string) ($notification->type ?? '');
        if ($type === self::TYPE_AI_AGENT_DIGEST || $type === self::TYPE_AI_AGENT_AUTO_REPLY) {
            // Digest + auto-reply are not ai_agent_alerts rows (related_id is alert id only for alerts).
            return true;
        }

        $alertId = (int) ($notification->related_id ?? 0);
        if ($alertId <= 0) {
            return false;
        }
        if (!function_exists('ai_agent_notify_alert_by_id')) {
            return false;
        }
        $alert = ai_agent_notify_alert_by_id($alertId);
        if (!$alert || (int) ($alert['user_id'] ?? 0) !== $user_id) {
            return false;
        }
        $agentType = (string) ($alert['agent_type'] ?? '');
        if ($agentType === '' || !ai_agent_notify_agent_enabled($agentType)) {
            return false;
        }

        return true;
    }

    /**
     * Check permission directly from database
     */
    private static function hasPermissionFromDB($user_id, $role_id, $permission_key) {
        global $db1;
        
        if (!$role_id) return false;
        
        $sql = "SELECT value FROM role_permissions WHERE role_id = " . $db1->escape($role_id) . " AND permission_key = '" . $db1->escape($permission_key) . "'";
        $result = $db1->query($sql);
        $row = $db1->fetch_row($result);
        
        return $row && $row['value'] == 1;
    }
    
    /**
     * Mark notification as read
     */
    public static function markAsRead($notification_id, $user_id) {
        global $db1;
        $sql = "UPDATE " . static::$tblName . " SET is_read = 1 WHERE id = " . $db1->escape($notification_id) . " AND user_id = " . $db1->escape($user_id);
        return $db1->query($sql);
    }
    
    /**
     * Mark all bell notifications as read for a user.
     * Envelope-only types (email + chat reactions) stay unread — they use their own "mark all" control.
     */
    public static function markAllAsRead($user_id) {
        global $db1;
        $sql = "UPDATE " . static::$tblName . " SET is_read = 1
                WHERE user_id = " . $db1->escape($user_id) . "
                  AND is_read = 0
                  AND type NOT IN (
                      '" . self::TYPE_EMAIL_RECEIVED . "',
                      '" . self::TYPE_EMAIL_THREAD_UPDATED . "',
                      '" . self::TYPE_CHAT_REACTION . "'
                  )";
        return $db1->query($sql);
    }
    
    /**
     * Delete old notifications (older than 30 days)
     */
    public static function cleanupOldNotifications() {
        global $db1;
        $sql = "DELETE FROM " . static::$tblName . " WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        return $db1->query($sql);
    }
    
    /**
     * Get notification message based on type and data
     */
    public static function getNotificationMessage($type, $data = []) {
        switch ($type) {
            case self::TYPE_PROJECT_CREATED:
                return sprintf('%s created new project "%s"', $data['user_name'], $data['project_title']);
                
            case self::TYPE_PROJECT_UPDATED:
                return sprintf('%s updated project "%s"', $data['user_name'], $data['project_title']);
                
            case self::TYPE_TASK_CREATED:
                return sprintf('%s created new task "%s"', $data['user_name'], $data['task_title']);
                
            case self::TYPE_TASK_UPDATED:
                return sprintf('%s updated task "%s"', $data['user_name'], $data['task_title']);
                
            case self::TYPE_TASK_STATUS_CHANGED:
                return sprintf('%s changed task "%s" status to %s', $data['user_name'], $data['task_title'], $data['new_status'] ?? $data['status'] ?? 'unknown');

            case self::TYPE_SUBTASK_CREATED:
                return sprintf(
                    '%s assigned you a sub-task "%s" on task "%s"',
                    $data['user_name'],
                    $data['subtask_title'],
                    $data['parent_task_title']
                );

            case self::TYPE_SUBTASK_COMPLETED:
                return sprintf(
                    '%s completed sub-task "%s" on task "%s"',
                    $data['user_name'],
                    $data['subtask_title'],
                    $data['parent_task_title']
                );
                
            case self::TYPE_TASK_REMINDER_1:
            case self::TYPE_TASK_REMINDER_2:
            case self::TYPE_TASK_REMINDER_3:
                // Use custom message if provided, otherwise generate default
                if (isset($data['message'])) {
                    return $data['message'];
                }
                $daysUntilDue = isset($data['days_until_due']) ? $data['days_until_due'] : 0;
                $daysOverdue = isset($data['days_overdue']) ? $data['days_overdue'] : 0;
                $taskTitle = isset($data['task_title']) ? $data['task_title'] : 'Task';
                
                if ($daysOverdue > 0) {
                    return sprintf('Task "%s" is %d day%s overdue', $taskTitle, $daysOverdue, $daysOverdue > 1 ? 's' : '');
                } elseif ($daysUntilDue == 0) {
                    return sprintf('Task "%s" is due today', $taskTitle);
                } else {
                    return sprintf('Task "%s" is due in %d day%s', $taskTitle, $daysUntilDue, $daysUntilDue > 1 ? 's' : '');
                }
                
            case self::TYPE_INVOICE_CREATED:
                return sprintf('%s created new invoice #%s', $data['user_name'], $data['invoice_number']);
                
            case self::TYPE_INVOICE_UPDATED:
                return sprintf('%s updated invoice #%s', $data['user_name'], $data['invoice_number']);
                
            case self::TYPE_INVOICE_PAID:
                return sprintf('Invoice #%s has been paid', $data['invoice_number']);

            case self::TYPE_INVOICE_NOT_CLEAR:
                return sprintf(
                    'Invoice #%s is still unpaid — payment reminder %d of 3 sent to client.',
                    $data['invoice_number'] ?? '',
                    (int) ($data['reminder_number'] ?? 1)
                );

            case self::TYPE_LEAD_CREATED:
                $source = isset($data['source_label']) ? $data['source_label'] : 'form/webhook';
                return sprintf('A new lead was created from %s.', $source);

            case self::TYPE_ECOMMERCE_ORDER_RECEIVED:
                return 'WooCommerce • Waiting for processing';

            default:
                return 'New notification';
        }
    }

    /**
     * Get notification title based on type
     */
    public static function getNotificationTitle($type) {
        switch ($type) {
            case self::TYPE_PROJECT_CREATED:
                return 'New Project Created';
                
            case self::TYPE_PROJECT_UPDATED:
                return 'Project Updated';
                
            case self::TYPE_TASK_CREATED:
                return 'New Task Created';
                
            case self::TYPE_TASK_UPDATED:
                return 'Task Updated';
                
            case self::TYPE_TASK_STATUS_CHANGED:
                return 'Task Status Changed';

            case self::TYPE_SUBTASK_CREATED:
                return 'Sub-task assigned';

            case self::TYPE_SUBTASK_COMPLETED:
                return 'Sub-task completed';
                
            case self::TYPE_TASK_REMINDER_1:
                return 'Task Reminder';
                
            case self::TYPE_TASK_REMINDER_2:
                return 'Task Due Today';
                
            case self::TYPE_TASK_REMINDER_3:
                return 'Overdue Task';
                
            case self::TYPE_INVOICE_CREATED:
                return 'New Invoice Created';
                
            case self::TYPE_INVOICE_UPDATED:
                return 'Invoice Updated';
                
            case self::TYPE_INVOICE_PAID:
                return 'Invoice Paid';

            case self::TYPE_INVOICE_NOT_CLEAR:
                return 'Invoice Not Clear';

            case self::TYPE_LEAD_CREATED:
                return 'New lead created';

            case self::TYPE_ECOMMERCE_ORDER_RECEIVED:
                return 'New order received';

            default:
                return 'Notification';
        }
    }

    /**
     * Get time ago string
     */
    public function getTimeAgo() {
        $time = strtotime($this->created_at);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }
}
?> 