<?php
/**
 * Task Reminder Helper
 * 
 * Helper class for managing task reminder functionality
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/reports_common_helper.php';
require_once __DIR__ . '/task.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/email_helper.php';
if (!class_exists('User')) {
    require_once __DIR__ . '/user.php';
}
if (!class_exists('Projects')) {
    require_once __DIR__ . '/projects.php';
}
if (!class_exists('Notifications', false)) {
    $notifications_file = __DIR__ . '/notifications.php';
    if (file_exists($notifications_file)) {
        require_once($notifications_file);
    } else {
        require_once __DIR__ . '/notifications.php';
    }
}

class TaskReminderHelper {
    
    /**
     * Check if a specific reminder is enabled
     * 
     * @param int $reminderNumber Reminder number (1, 2, or 3)
     * @param object $settings Settings object
     * @return bool
     */
    public static function isReminderEnabled($reminderNumber, $settings) {
        if (empty($settings->task_reminders_enabled)) {
            return false;
        }
        
        $enabledField = "task_reminder_{$reminderNumber}_enabled";
        return !empty($settings->$enabledField);
    }
    
    /**
     * Get tasks that need reminders
     * 
     * @param int $reminderNumber Reminder number (1, 2, or 3)
     * @param int $days Number of days before/after due date
     * @param string $type 'before' or 'after'
     * @param object $settings Settings object
     * @return array|false Array of task objects or false
     */
    public static function getTasksNeedingReminder($reminderNumber, $days, $type, $settings) {
        global $database;
        
        if (!self::isReminderEnabled($reminderNumber, $settings)) {
            return false;
        }
        
        $today = date('Y-m-d');
        $sql = "SELECT * FROM tasks ";
        $sql .= "WHERE status != 'done' ";
        $sql .= "AND due_date IS NOT NULL ";
        $sql .= "AND " . reports_sql_valid_date('due_date') . " ";
        
        if ($type == 'before') {
            $targetDate = date('Y-m-d', strtotime($today . " +{$days} days"));
            $sql .= "AND DATE(due_date) = '{$targetDate}' ";
        } else {
            $targetDate = date('Y-m-d', strtotime($today . " -{$days} days"));
            $sql .= "AND DATE(due_date) = '{$targetDate}' ";
        }
        
        $sql .= "ORDER BY due_date ASC";
        
        $result_array = Task::findBySql($sql);
        
        if (empty($result_array)) {
            return false;
        }
        
        $filtered = array();
        foreach ($result_array as $task) {
            if (!self::hasReminderBeenSent($task->id, $reminderNumber)) {
                $filtered[] = $task;
            }
        }
        
        return !empty($filtered) ? $filtered : false;
    }
    
    /**
     * Check if reminder has already been sent
     * 
     * @param int $taskId Task ID
     * @param int $reminderNumber Reminder number (1, 2, or 3)
     * @return bool
     */
    public static function hasReminderBeenSent($taskId, $reminderNumber) {
        global $database;
        
        $sql = "SELECT id FROM task_reminders_sent ";
        $sql .= "WHERE task_id = " . (int)$taskId . " ";
        $sql .= "AND reminder_number = " . (int)$reminderNumber . " ";
        $sql .= "LIMIT 1";
        
        $result = $database->query($sql);
        return ($result && $database->numRows($result) > 0);
    }
    
    /**
     * Mark reminder as sent
     * 
     * @param int $taskId Task ID
     * @param int $reminderNumber Reminder number (1, 2, or 3)
     * @param string $sentTo 'staff' or 'client'
     * @return bool
     */
    public static function markReminderAsSent($taskId, $reminderNumber, $sentTo = 'staff') {
        global $database;
        
        $sql = "INSERT INTO task_reminders_sent (task_id, reminder_number, sent_date, sent_to) ";
        $sql .= "VALUES (" . (int)$taskId . ", " . (int)$reminderNumber . ", NOW(), '" . $database->escapeValue($sentTo) . "')";
        
        return $database->query($sql);
    }
    
    /**
     * Check if notification has already been created for this task, reminder, and user
     * 
     * @param int $userId User ID
     * @param int $taskId Task ID
     * @param int $reminderNumber Reminder number (1, 2, or 3)
     * @return bool
     */
    public static function hasNotificationBeenCreated($userId, $taskId, $reminderNumber) {
        global $database;
        
        $notificationType = "task_reminder_{$reminderNumber}";
        
        $sql = "SELECT id FROM notifications ";
        $sql .= "WHERE user_id = " . (int)$userId . " ";
        $sql .= "AND type = '" . $database->escapeValue($notificationType) . "' ";
        $sql .= "AND related_id = " . (int)$taskId . " ";
        $sql .= "AND DATE(created_at) = CURDATE() ";
        $sql .= "LIMIT 1";
        
        $result = $database->query($sql);
        return ($result && $database->numRows($result) > 0);
    }
    
    /**
     * Create task reminder notification for a user
     * 
     * @param int $userId User ID to notify
     * @param object $task Task object
     * @param int $reminderNumber Reminder number (1, 2, or 3)
     * @param object $settings Settings object
     * @return bool Success status
     */
    public static function createTaskReminderNotification($userId, $task, $reminderNumber, $settings) {
        global $url, $company_name, $database;
        
        if (self::hasNotificationBeenCreated($userId, $task->id, $reminderNumber)) {
            return false;
        }
        
        $subjectField = "task_reminder_{$reminderNumber}_subject";
        $subject = isset($settings->$subjectField) ? $settings->$subjectField : 'Task Reminder';
        
        $project = null;
        $projectName = '';
        $projectId = null;
        if ($task->project_id && $task->project_id > 0) {
            $project = Projects::findByProjectId($task->project_id);
            if ($project) {
                $projectName = $project->project_title;
                $projectId = $task->project_id;
            }
        }
        
        $daysInfo = self::calculateDays($task->due_date);
        $dueDate = $task->due_date ? date('F d, Y', strtotime($task->due_date)) : 'N/A';
        $taskStatusDisplay = self::getTaskStatusDisplay($task);
        
        $user = User::findById($userId);
        $userName = $user && !empty($user->firstName) ? $user->firstName : 'User';
        
        $variables = array(
            '{USER_NAME}' => htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'),
            '{TASK_TITLE}' => htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8'),
            '{PROJECT_NAME}' => htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8'),
            '{TASK_STATUS}' => htmlspecialchars($taskStatusDisplay, ENT_QUOTES, 'UTF-8'),
            '{DUE_DATE}' => htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8'),
            '{DAYS_OVERDUE}' => (string)$daysInfo['overdue'],
            '{DAYS_UNTIL_DUE}' => (string)$daysInfo['until_due'],
            '{DASHBOARD_URL}' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
            '{SIGNATURE}' => htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8')
        );
        
        $notificationTitle = $subject;
        $notificationTitle = preg_replace('/\{TASK_TITLE\}/', '', $notificationTitle);
        $notificationTitle = trim($notificationTitle);
        $notificationTitle = preg_replace('/\s+/', ' ', $notificationTitle);
        $notificationTitle = rtrim($notificationTitle, ': ');
        $notificationTitle = strtr($notificationTitle, $variables);
        
        $taskTitle = htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8');
        $notificationMessage = $taskTitle;
        $notificationType = "task_reminder_{$reminderNumber}";
        
        $fromUserId = 0;
        if (!empty($task->creator_id)) {
            $fromUserId = (int)$task->creator_id;
        } elseif (!empty($task->user_id)) {
            $fromUserId = (int)$task->user_id;
        }
        
        try {
            $notificationId = Notifications::createNotificationWithProject(
                $userId,
                $fromUserId,
                $notificationType,
                $notificationTitle,
                $notificationMessage,
                $task->id,
                'task',
                $projectId
            );
            
            return ($notificationId !== false);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get staff/admin emails for task (assigned staff and admins only, excluding creator)
     * 
     * @param object $task Task object
     * @return array Array of email addresses
     */
    public static function getStaffEmailsForTask($task) {
        $emails = array();
        $processed_ids = array();
        
        if (!empty($task->assigned_to)) {
            $assignedIds = array_filter(explode(',', $task->assigned_to));
            foreach ($assignedIds as $staffId) {
                $staffId = (int)$staffId;
                if ($staffId > 0 && !in_array($staffId, $processed_ids)) {
                    $processed_ids[] = $staffId;
                    $staffUser = User::findById($staffId);
                    if ($staffUser && 
                        filter_var($staffUser->email, FILTER_VALIDATE_EMAIL) &&
                        ($staffUser->accountStatus == 1 || $staffUser->accountStatus == 3)) {
                        if (!in_array($staffUser->email, $emails)) {
                            $emails[] = $staffUser->email;
                        }
                    }
                }
            }
        }
        
        return $emails;
    }
    
    /**
     * Get creator email for task
     * 
     * @param object $task Task object
     * @return string|false Creator email address or false if not found
     */
    public static function getCreatorEmailForTask($task) {
        $creator_id = null;
        if (!empty($task->creator_id) && $task->creator_id > 0) {
            $creator_id = (int)$task->creator_id;
        } elseif (!empty($task->user_id) && $task->user_id > 0) {
            $creator_id = (int)$task->user_id;
        }
        
        if ($creator_id && $creator_id > 0) {
            $creatorUser = User::findById($creator_id);
            if ($creatorUser && filter_var($creatorUser->email, FILTER_VALIDATE_EMAIL)) {
                return $creatorUser->email;
            }
        }
        
        return false;
    }
    
    /**
     * Get client emails for task
     * 
     * @param object $task Task object
     * @return array Array of email addresses
     */
    public static function getClientEmailsForTask($task) {
        $emails = array();
        
        if ($task->project_id && $task->project_id > 0) {
            $project = Projects::findByProjectId($task->project_id);
            if ($project) {
                $main_client_id = $project->main_client_id ?: $project->c_id;
                $all_client_ids = [];
                
                if (!empty($project->c_ids)) {
                    $all_client_ids = array_filter(explode(',', $project->c_ids));
                    $all_client_ids = array_map('intval', array_filter($all_client_ids));
                }
                
                if ($main_client_id && $main_client_id > 0) {
                    $main_client_id = (int)$main_client_id;
                    if (!in_array($main_client_id, $all_client_ids)) {
                        $all_client_ids[] = $main_client_id;
                    }
                }
                
                if (empty($all_client_ids) && !empty($project->c_id)) {
                    $all_client_ids = [(int)$project->c_id];
                }
                
                $processed_ids = [];
                foreach ($all_client_ids as $client_id) {
                    if ($client_id > 0 && !in_array($client_id, $processed_ids)) {
                        $processed_ids[] = $client_id;
                        $clientUser = User::findById($client_id);
                        if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL)) {
                            if (!in_array($clientUser->email, $emails)) {
                                $emails[] = $clientUser->email;
                            }
                        }
                    }
                }
            }
        }
        
        return $emails;
    }
    
    /**
     * Calculate days overdue or days until due
     * 
     * @param string $dueDate Due date (Y-m-d format)
     * @return array ['overdue' => days, 'until_due' => days]
     */
    public static function calculateDays($dueDate) {
        $today = new DateTime(date('Y-m-d'));
        $due = new DateTime($dueDate);
        $diff = $today->diff($due);
        
        $daysUntilDue = 0;
        $daysOverdue = 0;
        
        if ($today < $due) {
            $daysUntilDue = $diff->days;
        } else {
            $daysOverdue = $diff->days;
        }
        
        return array(
            'overdue' => $daysOverdue,
            'until_due' => $daysUntilDue
        );
    }
    
    /**
     * Get task status display name
     * 
     * @param object $task Task object
     * @return string Status display name
     */
    public static function getTaskStatusDisplay($task) {
        global $database;
        
        $status = $task->status;
        
        $query = "SELECT custom_name FROM project_columns WHERE column_key = '" . $database->escapeValue($status) . "'";
        if ($task->project_id > 0) {
            $query .= " AND (project_id = " . intval($task->project_id) . " OR project_id = 0)";
        }
        $query .= " ORDER BY project_id DESC LIMIT 1";
        $resultStatus = $database->query($query);
        if ($row = $database->fetchArray($resultStatus)) {
            return $row['custom_name'];
        }
        
        return ucfirst($status);
    }
    
    /**
     * Send task reminder email
     * 
     * @param object $task Task object
     * @param int $reminderNumber Reminder number (1, 2, or 3)
     * @param object $settings Settings object
     * @return bool Success status
     */
    public static function sendTaskReminder($task, $reminderNumber, $settings) {
        global $url, $company_name;
        
        $templateField = "task_reminder_{$reminderNumber}_template";
        $subjectField = "task_reminder_{$reminderNumber}_subject";
        
        $template = isset($settings->$templateField) ? $settings->$templateField : '';
        $subject = isset($settings->$subjectField) ? $settings->$subjectField : 'Task Reminder';
        
        if (empty($template)) {
            return false;
        }
        
        $project = null;
        $projectName = '';
        if ($task->project_id && $task->project_id > 0) {
            $project = Projects::findByProjectId($task->project_id);
            if ($project) {
                $projectName = $project->project_title;
            }
        }
        
        $daysInfo = self::calculateDays($task->due_date);
        $dueDate = $task->due_date ? date('F d, Y', strtotime($task->due_date)) : 'N/A';
        $taskStatusDisplay = self::getTaskStatusDisplay($task);
        
        if (!function_exists('limitWords')) {
            function limitWords($text, $limit = 20) {
                if (empty($text) || $text === null) {
                    return '';
                }
                $plain = strip_tags($text);
                $words = preg_split('/\s+/', $plain);
                if (count($words) > $limit) {
                    return implode(' ', array_slice($words, 0, $limit)) . '...';
                }
                return $plain;
            }
        }
        
        $emailHelper = new EmailHelper($settings);
        $sent = false;
        
        if (!empty($settings->task_reminder_send_to_staff)) {
            $staffEmails = self::getStaffEmailsForTask($task);
            
            foreach ($staffEmails as $staffEmail) {
                $staffUser = User::findByEmail($staffEmail);
                $userName = $staffUser && !empty($staffUser->firstName) ? $staffUser->firstName : 'Team Member';
                
                $variables = array(
                    '{USER_NAME}' => htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'),
                    '{TASK_TITLE}' => htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8'),
                    '{TASK_DESCRIPTION}' => limitWords($task->description, 20),
                    '{PROJECT_NAME}' => htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8'),
                    '{TASK_STATUS}' => htmlspecialchars($taskStatusDisplay, ENT_QUOTES, 'UTF-8'),
                    '{DUE_DATE}' => htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8'),
                    '{DAYS_OVERDUE}' => (string)$daysInfo['overdue'],
                    '{DAYS_UNTIL_DUE}' => (string)$daysInfo['until_due'],
                    '{DASHBOARD_URL}' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                    '{SIGNATURE}' => htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8')
                );
                
                $messageBody = strtr($template, $variables);
                $emailSubject = strtr($subject, $variables);
                
                try {
                    $staffSent = $emailHelper->sendEmail($staffEmail, $emailSubject, $messageBody);
                    if ($staffSent) {
                        self::markReminderAsSent($task->id, $reminderNumber, 'staff');
                        $sent = true;
                    }
                } catch (Exception $e) {
                    // Silent fail for production
                }
                
                if ($staffUser && !empty($staffUser->id)) {
                    try {
                        self::createTaskReminderNotification($staffUser->id, $task, $reminderNumber, $settings);
                    } catch (Exception $e) {
                        // Silent fail for production
                    }
                }
            }
        }
        
        if (!empty($settings->task_reminder_send_to_client)) {
            $clientEmails = self::getClientEmailsForTask($task);
            
            foreach ($clientEmails as $clientEmail) {
                $clientUser = User::findByEmail($clientEmail);
                $userName = $clientUser && !empty($clientUser->firstName) ? $clientUser->firstName : 'Client';
                
                $variables = array(
                    '{USER_NAME}' => htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'),
                    '{TASK_TITLE}' => htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8'),
                    '{TASK_DESCRIPTION}' => limitWords($task->description, 20),
                    '{PROJECT_NAME}' => htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8'),
                    '{TASK_STATUS}' => htmlspecialchars($taskStatusDisplay, ENT_QUOTES, 'UTF-8'),
                    '{DUE_DATE}' => htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8'),
                    '{DAYS_OVERDUE}' => (string)$daysInfo['overdue'],
                    '{DAYS_UNTIL_DUE}' => (string)$daysInfo['until_due'],
                    '{DASHBOARD_URL}' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                    '{SIGNATURE}' => htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8')
                );
                
                $messageBody = strtr($template, $variables);
                $emailSubject = strtr($subject, $variables);
                
                try {
                    $clientSent = $emailHelper->sendEmail($clientEmail, $emailSubject, $messageBody);
                    if ($clientSent) {
                        self::markReminderAsSent($task->id, $reminderNumber, 'client');
                        $sent = true;
                    }
                } catch (Exception $e) {
                    // Silent fail for production
                }
                
                if ($clientUser && !empty($clientUser->id)) {
                    try {
                        self::createTaskReminderNotification($clientUser->id, $task, $reminderNumber, $settings);
                    } catch (Exception $e) {
                        // Silent fail for production
                    }
                }
            }
        }
        
        if (!empty($settings->task_reminder_send_to_creator)) {
            $creatorEmail = self::getCreatorEmailForTask($task);
            
            if ($creatorEmail) {
                $creatorUser = User::findByEmail($creatorEmail);
                $userName = $creatorUser && !empty($creatorUser->firstName) ? $creatorUser->firstName : 'Creator';
                
                $variables = array(
                    '{USER_NAME}' => htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'),
                    '{TASK_TITLE}' => htmlspecialchars($task->title, ENT_QUOTES, 'UTF-8'),
                    '{TASK_DESCRIPTION}' => limitWords($task->description, 20),
                    '{PROJECT_NAME}' => htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8'),
                    '{TASK_STATUS}' => htmlspecialchars($taskStatusDisplay, ENT_QUOTES, 'UTF-8'),
                    '{DUE_DATE}' => htmlspecialchars($dueDate, ENT_QUOTES, 'UTF-8'),
                    '{DAYS_OVERDUE}' => (string)$daysInfo['overdue'],
                    '{DAYS_UNTIL_DUE}' => (string)$daysInfo['until_due'],
                    '{DASHBOARD_URL}' => htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                    '{SIGNATURE}' => htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8')
                );
                
                $messageBody = strtr($template, $variables);
                $emailSubject = strtr($subject, $variables);
                
                try {
                    $creatorSent = $emailHelper->sendEmail($creatorEmail, $emailSubject, $messageBody);
                    if ($creatorSent) {
                        self::markReminderAsSent($task->id, $reminderNumber, 'creator');
                        $sent = true;
                    }
                } catch (Exception $e) {
                    // Silent fail for production
                }
                
                if ($creatorUser && !empty($creatorUser->id)) {
                    try {
                        self::createTaskReminderNotification($creatorUser->id, $task, $reminderNumber, $settings);
                    } catch (Exception $e) {
                        // Silent fail for production
                    }
                }
            }
        }
        
        return $sent;
    }
}

?>

