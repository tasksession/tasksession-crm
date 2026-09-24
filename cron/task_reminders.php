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

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$current_dir = dirname(__FILE__);
$project_root = dirname($current_dir);
// Logging off — avoids root-path permission errors (/task_reminders.log) and error_log spam
$disableCronLogs = true;
$log_dir = $project_root . '/logs';

require_once($project_root . '/includes/lib-initialize.php');
require_once($project_root . '/includes/task_reminder_helper.php');

if (!function_exists('logMessage')) {
function logMessage($message) {
    return;
}
}

logMessage("Starting task reminders cron job...");

try {
    $settings = settings::findById(1);
    
    if (!$settings) {
        logMessage("ERROR: Could not load settings");
        if (defined('_CRON_CENTRAL_MODE') || defined('_CRON_AJAX_MODE')) return;
        exit(1);
    }
    
    if (empty($settings->task_reminders_enabled)) {
        if (defined('_CRON_CENTRAL_MODE') || defined('_CRON_AJAX_MODE')) return;
        exit(0);
    }
    
    $sentCount = 0;
    $errorCount = 0;
    
    for ($reminderNumber = 1; $reminderNumber <= 3; $reminderNumber++) {
        $enabledField = "task_reminder_{$reminderNumber}_enabled";
        if (empty($settings->$enabledField)) {
            continue;
        }
        
        $daysField = "task_reminder_{$reminderNumber}_days";
        $typeField = "task_reminder_{$reminderNumber}_type";
        
        $days = isset($settings->$daysField) ? (int)$settings->$daysField : 0;
        $type = isset($settings->$typeField) ? $settings->$typeField : 'after';
        
        if ($days < 0) {
            continue;
        }
        
        $today = date('Y-m-d');
        if ($type == 'before') {
            $targetDate = date('Y-m-d', strtotime($today . " +{$days} days"));
        } else {
            $targetDate = date('Y-m-d', strtotime($today . " -{$days} days"));
        }
        
        $tasks = TaskReminderHelper::getTasksNeedingReminder($reminderNumber, $days, $type, $settings);
        
        if (!$tasks || empty($tasks)) {
            continue;
        }
        
        $totalTasks = count($tasks);
        $batchSize = 10;
        $delayBetweenTasks = 500000;
        $delayBetweenBatches = 2000000;
        $maxExecutionTime = 300;
        $startTime = time();
        
        if ($totalTasks > 20) {
            $delayBetweenTasks = 300000;
        }
        
        $taskIndex = 0;
        $batchNumber = 0;
        
        foreach ($tasks as $task) {
            if ((time() - $startTime) > $maxExecutionTime) {
                logMessage("Reminder #{$reminderNumber}: Execution time limit reached. Processed {$taskIndex}/{$totalTasks} tasks.");
                break;
            }
            
            $taskIndex++;
            $batchNumber = (int)ceil($taskIndex / $batchSize);
            
            if ($totalTasks > 10 && ($taskIndex % 10 == 0 || $taskIndex == 1)) {
                $progress = round(($taskIndex / $totalTasks) * 100, 1);
                logMessage("Reminder #{$reminderNumber}: Progress: {$taskIndex}/{$totalTasks} tasks ({$progress}%)");
            }
            
            try {
                $sent = TaskReminderHelper::sendTaskReminder($task, $reminderNumber, $settings);
                
                if ($sent) {
                    $sentCount++;
                } else {
                    $errorCount++;
                }
                
                if ($taskIndex < $totalTasks) {
                    usleep($delayBetweenTasks);
                }
                
                if ($totalTasks > 20 && $taskIndex % $batchSize == 0 && $taskIndex < $totalTasks) {
                    usleep($delayBetweenBatches);
                }
                
            } catch (Exception $e) {
                $errorCount++;
                logMessage("Reminder #{$reminderNumber}: Exception processing task #{$task->id}: " . $e->getMessage());
            }
        }
    }
    
    // Cleanup old reminder records (only delete if task is completed)
    logMessage("Starting cleanup of old task reminder records...");
    $cleanup_days = 60; // Keep records for 60 days
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$cleanup_days} days"));
    
    try {
        global $database;
        // Only delete records where the task status is 'done' (completed)
        // This prevents duplicate reminders if task is still incomplete
        $cleanup_query = "DELETE trs FROM task_reminders_sent trs
                          INNER JOIN tasks t ON t.id = trs.task_id
                          WHERE trs.sent_date < '" . $database->escapeValue($cutoff_date) . "'
                          AND t.status = 'done'";
        $cleanup_result = $database->query($cleanup_query);
        
        if ($cleanup_result) {
            $deleted_count = $database->affectedRows();
            logMessage("Cleanup completed: Deleted $deleted_count old record(s) (older than $cleanup_days days AND task is completed)");
        } else {
            logMessage("WARNING: Cleanup query failed");
        }
    } catch (Exception $e) {
        logMessage("ERROR during cleanup: " . $e->getMessage());
    }
    
    logMessage("Task reminders cron job completed. Sent: {$sentCount} reminders. Errors: {$errorCount}");
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    if (defined('_CRON_CENTRAL_MODE') || defined('_CRON_AJAX_MODE')) {
        return;
    }
    exit(1);
}

if (defined('_CRON_CENTRAL_MODE') || defined('_CRON_AJAX_MODE')) {
    return;
}
exit(0);

?>

