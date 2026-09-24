<?php
/**
 * Task Reminders Cron Job
 * 
 * This script should be run daily via cron job to automatically send
 * task reminders for tasks with due dates.
 * 
 * Cron job example (Linux):
 * 0 9 * * * /usr/bin/php /path/to/project/cron/task_reminders.php
 * 
 * Windows Task Scheduler:
 * Run daily at 9 AM: php.exe "D:\XAMPP\htdocs\comon\cron\task_reminders.php"
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Get the project root directory
$current_dir = dirname(__FILE__);
$project_root = dirname($current_dir);

// Logging control (suppress when run via central cron)
$disableCronLogs = (defined('_CRON_CENTRAL_MODE') && _CRON_CENTRAL_MODE) || getenv('CENTRAL_CRON') === '1';

// Set error log path (only if logging enabled)
$log_dir = $project_root . '/logs';
if (!$disableCronLogs) {
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    ini_set('error_log', $log_dir . '/task_reminders.log');
}

// Include necessary files
require_once($project_root . '/includes/lib-initialize.php');
require_once($project_root . '/includes/task_reminder_helper.php');

// Log function (noop when logging disabled)
if (!function_exists('logMessage')) {
function logMessage($message) {
    global $log_dir, $disableCronLogs;
    if ($disableCronLogs) {
        return;
    }
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents($log_dir . '/task_reminders.log', $logMessage, FILE_APPEND | LOCK_EX);
    if (php_sapi_name() === 'cli') {
        echo $logMessage;
    }
}
}

logMessage("Starting task reminders cron job...");

try {
    // Load settings
    $settings = settings::findById(1);
    
    if (!$settings) {
        logMessage("ERROR: Could not load settings");
        exit(1);
    }
    
    // Check if task reminders are enabled
    if (empty($settings->task_reminders_enabled)) {
        logMessage("Task reminders are disabled. Exiting.");
        exit(0);
    }
    
    $sentCount = 0;
    $errorCount = 0;
    
    // Process each reminder level (1, 2, 3)
    for ($reminderNumber = 1; $reminderNumber <= 3; $reminderNumber++) {
        // Check if this reminder is enabled
        $enabledField = "task_reminder_{$reminderNumber}_enabled";
        if (empty($settings->$enabledField)) {
            logMessage("Reminder #{$reminderNumber} is disabled. Skipping.");
            continue;
        }
        
        // Get reminder settings
        $daysField = "task_reminder_{$reminderNumber}_days";
        $typeField = "task_reminder_{$reminderNumber}_type";
        
        $days = isset($settings->$daysField) ? (int)$settings->$daysField : 0;
        $type = isset($settings->$typeField) ? $settings->$typeField : 'after';
        
        // Allow 0 days for same-day reminders, only reject negative values
        if ($days < 0) {
            logMessage("Reminder #{$reminderNumber}: Invalid days setting ({$days}). Skipping.");
            continue;
        }
        
        // Calculate target date for logging
        $today = date('Y-m-d');
        if ($type == 'before') {
            $targetDate = date('Y-m-d', strtotime($today . " +{$days} days"));
        } else {
            $targetDate = date('Y-m-d', strtotime($today . " -{$days} days"));
        }
        
        logMessage("Processing Reminder #{$reminderNumber}: {$days} days {$type} due date (Looking for tasks with due_date = {$targetDate})");
        
        // Get tasks needing this reminder
        $tasks = TaskReminderHelper::getTasksNeedingReminder($reminderNumber, $days, $type, $settings);
        
        if (!$tasks || empty($tasks)) {
            // Add debug info: check how many tasks exist with due dates
            global $database;
            $dueValid = reports_sql_valid_date('due_date');
            $debugSql = "SELECT COUNT(*) as cnt FROM tasks WHERE status != 'done' AND due_date IS NOT NULL AND {$dueValid}";
            $debugResult = $database->query($debugSql);
            $debugRow = $database->fetchArray($debugResult);
            $totalTasksWithDueDates = $debugRow['cnt'] ?? 0;
            
            $matchingSql = "SELECT COUNT(*) as cnt FROM tasks WHERE status != 'done' AND due_date IS NOT NULL AND {$dueValid} AND DATE(due_date) = '{$targetDate}'";
            $matchingResult = $database->query($matchingSql);
            $matchingRow = $database->fetchArray($matchingResult);
            $matchingTasksCount = $matchingRow['cnt'] ?? 0;
            
            logMessage("Reminder #{$reminderNumber}: No tasks found matching criteria. (Total tasks with due dates: {$totalTasksWithDueDates}, Tasks matching target date {$targetDate}: {$matchingTasksCount})");
            continue;
        }
        
        logMessage("Reminder #{$reminderNumber}: Found " . count($tasks) . " task(s) needing reminder");
        
        // Process each task
        foreach ($tasks as $task) {
            try {
                logMessage("Reminder #{$reminderNumber}: Processing task #{$task->id} - {$task->title}");
                logMessage("Reminder #{$reminderNumber}: Task details - Project ID: " . ($task->project_id ?? 'N/A') . ", Assigned to: " . ($task->assigned_to ?? 'N/A'));
                
                $sent = TaskReminderHelper::sendTaskReminder($task, $reminderNumber, $settings);
                
                if ($sent) {
                    $sentCount++;
                    logMessage("Reminder #{$reminderNumber}: Successfully sent for task #{$task->id}");
                } else {
                    $errorCount++;
                    logMessage("Reminder #{$reminderNumber}: Failed to send for task #{$task->id} - sendTaskReminder() returned false");
                    logMessage("Reminder #{$reminderNumber}: Check error logs for details about email sending failures");
                }
                
                // Small delay between emails to prevent SMTP issues
                usleep(500000); // 0.5 seconds
                
            } catch (Exception $e) {
                $errorCount++;
                logMessage("Reminder #{$reminderNumber}: Exception processing task #{$task->id}: " . $e->getMessage());
                logMessage("Stack trace: " . $e->getTraceAsString());
            }
        }
    }
    
    logMessage("Task reminders cron job completed. Sent: {$sentCount} reminders. Errors: {$errorCount}");
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    if (!defined('_CRON_CENTRAL_MODE')) {
        exit(1);
    }
    // If running via central cron, just return instead of exiting
    return;
}

// If running via central cron or AJAX, don't call exit() as it terminates the entire script
if (!defined('_CRON_CENTRAL_MODE') && !defined('_CRON_AJAX_MODE')) {
    exit(0);
}
// Otherwise, just return - the central cron or AJAX handler will continue processing

?>

