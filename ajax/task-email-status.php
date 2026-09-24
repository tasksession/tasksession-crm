<?php
// ajax/task-email-status.php
header('Content-Type: application/json');
require_once('../includes/lib-initialize.php');
require_once('../includes/task.php');
require_once('../includes/projects.php');
require_once('../includes/notification_helper.php');

// Ensure session is started and get user ID
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Authentication check
if (!isset($session) || !$session->isLoggedIn()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : 0;

$response = ['success' => false, 'message' => ''];

if (!isset($_POST['task_id']) || !isset($_POST['new_status'])) {
    $response['message'] = 'Missing parameters.';
    echo json_encode($response);
    exit;
}

$taskId = (int)$_POST['task_id'];
$newStatus = trim($_POST['new_status']);

$task = Task::findById($taskId);
if (!$task) {
    $response['message'] = 'Task not found.';
    echo json_encode($response);
    exit;
}

$oldStatus = $task->status;
$task->status = $newStatus;
$result = $task->save();

if ($result === true || $result === 0) {
    // Create notification for task status change
    NotificationHelper::taskStatusChanged($task->id, $task->title, $userId, $newStatus, $task->assigned_to, $task->project_id);
    require_once __DIR__ . '/../includes/task_recurrence_helper.php';
    tasksession_recurrence_maybe_spawn_on_done($task);
    
    // Send email notifications (same as edit_task.php)
    $settings = settings::findById(1);
    $task_title = $task->title;
    $task_description = $task->description;
    $due_date = $task->due_date;
    $project = $task->project_id > 0 ? projects::findByProjectId($task->project_id) : null;
    $project_name = $project ? $project->project_title : '';
    $task_status_display = $newStatus;
    // Try to get display name for status
    $query = "SELECT custom_name FROM project_columns WHERE column_key = '" . $database->escapeValue($newStatus) . "'";
    if ($task->project_id > 0) {
        $query .= " AND (project_id = " . intval($task->project_id) . " OR project_id = 0)";
    }
    $query .= " ORDER BY project_id DESC LIMIT 1";
    $resultStatus = $database->query($query);
    if ($row = $database->fetchArray($resultStatus)) {
        $task_status_display = $row['custom_name'];
    }
    // Helper to limit words and strip HTML
    function limitWords($text, $limit = 20) {
        $plain = strip_tags($text);
        $words = preg_split('/\s+/', $plain);
        if (count($words) > $limit) {
            return implode(' ', array_slice($words, 0, $limit)) . '...';
        }
        return $plain;
    }
    // Notify assigned staff
    if (!empty($task->assigned_to)) {
        $assigned_staff_ids = explode(',', $task->assigned_to);
        foreach ($assigned_staff_ids as $staff_id) {
            if ($staff_id > 0) {
                $staffUser = User::findById($staff_id);
                if ($staffUser && filter_var($staffUser->email, FILTER_VALIDATE_EMAIL)) {
                    $variablesArr = array(
                        '{USER_NAME}'        => htmlspecialchars($staffUser->firstName, ENT_QUOTES, 'UTF-8'),
                        '{TASK_TITLE}'       => htmlspecialchars($task_title, ENT_QUOTES, 'UTF-8'),
                        '{PROJECT_NAME}'     => htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8'),
                        '{TASK_DESCRIPTION}' => limitWords($task_description, 20),
                        '{TASK_STATUS}'      => $task_status_display,
                        '{DUE_DATE}'         => htmlspecialchars($due_date, ENT_QUOTES, 'UTF-8'),
                        '{DASHBOARD_URL}'    => $url,
                        '{SIGNATURE}'        => $company_name
                    );
                    $templateHTML = $settings->task_update_email;
                    $messageBody = strtr($templateHTML, $variablesArr);
                    $subject = 'Task status has been updated in your project!';
                    $headers  = 'MIME-Version: 1.0' . "\r\n";
                    $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
                    $headers .= 'From: ' . $company_name . ' <' . $system_email . '>' . "\r\n";
                    // Suppress mail warnings in development environment
                    @mail($staffUser->email, $subject, $messageBody, $headers);
                }
            }
        }
    }
    // Notify all clients if task is linked to a project
    if ($task->project_id > 0 && $project) {
        // Get main client ID
        $main_client_id = $project->main_client_id ?: $project->c_id;
        
        // Get all client IDs (main + additional)
        $all_client_ids = [];
        if (!empty($project->c_ids)) {
            $all_client_ids = array_filter(explode(',', $project->c_ids));
        }
        
        // If no additional clients, just use main client
        if (empty($all_client_ids)) {
            $all_client_ids = [$main_client_id];
        }
        
        // Send email to all clients
        foreach ($all_client_ids as $client_id) {
            $clientUser = User::findById($client_id);
            if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL)) {
                $variablesArr = array(
                    '{USER_NAME}'        => htmlspecialchars($clientUser->firstName, ENT_QUOTES, 'UTF-8'),
                    '{TASK_TITLE}'       => htmlspecialchars($task_title, ENT_QUOTES, 'UTF-8'),
                    '{PROJECT_NAME}'     => htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8'),
                    '{TASK_DESCRIPTION}' => limitWords($task_description, 20),
                    '{TASK_STATUS}'      => $task_status_display,
                    '{DUE_DATE}'         => htmlspecialchars($due_date, ENT_QUOTES, 'UTF-8'),
                    '{DASHBOARD_URL}'    => $url,
                    '{SIGNATURE}'        => $company_name
                );
                $templateHTML = $settings->task_update_email;
                
                // Check if template is empty
                if (empty($templateHTML)) {
                    error_log("Email template is empty for task status change client notification: " . $clientUser->email);
                    continue;
                }
                
                $messageBody = strtr($templateHTML, $variablesArr);
                $subject = 'Task status has been updated in your project!';
                $headers  = 'MIME-Version: 1.0' . "\r\n";
                $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
                $headers .= 'From: ' . $company_name . ' <' . $system_email . '>' . "\r\n";
                // Suppress mail warnings in development environment
                $emailSent = @mail($clientUser->email, $subject, $messageBody, $headers);
                if (!$emailSent) {
                    error_log("Failed to send task status change email to client: " . $clientUser->email);
                }
            }
        }
    }
    $response['success'] = true;
    $response['message'] = 'Task status updated, notifications sent, and emails sent.';
} else {
    $response['message'] = 'Failed to update task status.';
}
echo json_encode($response); 