<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : includes/board_update.php
   Purpose : Centralized handler for task board and column updates
 ================================================================================
 */
// Suppress all output except JSON
if (!defined('CRM_LIGHTWEIGHT_INIT')) {
    define('CRM_LIGHTWEIGHT_INIT', true);
}
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/lib-initialize.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/projects.php';
require_once __DIR__ . '/csrf-middleware.php';
require_once __DIR__ . '/crm_perf_helper.php';
require_once __DIR__ . '/crm_mail_queue_helper.php';

// auth check
if (!$session->isLoggedIn()) {
    ob_clean();
    die(json_encode(['status' => 'error', 'error' => 'Not logged in']));
}
ensure_user_permissions($connect);
csrf_require_for_request('json');

try {
    $userType = $_SESSION['accountStatus'];
    $userId = $_SESSION['userId'];

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id']) || !isset($input['status'])) {
        ob_clean();
        die(json_encode(['status' => 'error', 'error' => 'Missing required fields']));
    }

    $id = (int)$input['id'];
    $status = strip_tags($input['status']);
    $position = isset($input['position']) ? (int)$input['position'] : 0;
    $insertBeforeId = !empty($input['insert_before_id']) ? (int)$input['insert_before_id'] : 0;
    $insertAfterId = !empty($input['insert_after_id']) ? (int)$input['insert_after_id'] : 0;
    $orderedIdsRaw = isset($input['ordered_ids']) && is_array($input['ordered_ids']) ? $input['ordered_ids'] : [];
    $clientSortOrder = (isset($input['sort_order']) && trim((string)$input['sort_order']) === 'asc') ? 'asc' : 'desc';

    require_once __DIR__ . '/task.php';
    $task = Task::findById($id);
    if (!$task) {
        ob_clean();
        die(json_encode(['status' => 'error', 'error' => 'Task not found']));
    }

    $canUpdate = false;

    switch ($userType) {
        case 1:
            $canUpdate = true;
            break;
        case 3:
            if (has_permission('task_status_update')) {
                $canUpdate = staff_can_view_task($task, $userId);
            }
            break;
        case 2:
            $canUpdate = $task->isClientAssociated($userId);
            break;
        default:
            $canUpdate = false;
    }

    if (!$canUpdate) {
        ob_clean();
        die(json_encode(['status' => 'error', 'error' => 'Insufficient permissions']));
    }

    $defaultStatuses = ['todo', 'inprogress', 'review', 'done'];
    $isPersonalColumn = !in_array($status, $defaultStatuses, true);

    if ($isPersonalColumn) {
        $deleteSQL = "DELETE FROM extra_tasks_columns WHERE user_id = $userId AND task_id = $id";
        $database->query($deleteSQL);

        $insertSQL = "INSERT INTO extra_tasks_columns (user_id, task_id, column_key) VALUES ($userId, $id, '" . $database->escapeValue($status) . "')";
        $database->query($insertSQL);

        ob_clean();
        echo json_encode(['status' => 'ok', 'message' => 'Task moved to personal column']);
        exit;
    } else {
        $oldStatus = $task->status;
        $movingId = (int)$task->id;

        $escNew = $database->escapeValue($status);
        $peerIds = [];
        $rNew = $database->query("SELECT id FROM tasks WHERE status = '$escNew' AND id != $movingId ORDER BY position ASC, created_at ASC, id ASC");
        if ($rNew) {
            while ($row = $database->fetchArray($rNew)) {
                $peerIds[] = (int)$row['id'];
            }
        }

        $orderedClean = [];
        foreach ($orderedIdsRaw as $v) {
            $vid = (int)$v;
            if ($vid <= 0) {
                continue;
            }
            if (!in_array($vid, $orderedClean, true)) {
                $orderedClean[] = $vid;
            }
        }

        $willTotal = count($peerIds) + 1;
        $expectedUnion = $peerIds;
        $expectedUnion[] = $movingId;
        $expSorted = $expectedUnion;
        sort($expSorted, SORT_NUMERIC);
        $ordSorted = $orderedClean;
        sort($ordSorted, SORT_NUMERIC);
        $validFullOrdered = ($willTotal > 0
            && count($orderedClean) === $willTotal
            && count($orderedClean) === count(array_unique($orderedClean))
            && $ordSorted === $expSorted);

        $fallbackPos = max(0, min($position, count($peerIds)));

        $subsetOrdered = false;
        $missingForSubset = [];
        $spliceFallbackUsed = false;
        if (!$validFullOrdered && count($orderedClean) > 0 && count($orderedClean) < $willTotal) {
            $missingForSubset = array_values(array_diff($expectedUnion, $orderedClean));
            if (count($orderedClean) + count($missingForSubset) === $willTotal) {
                $subsetOk = true;
                foreach ($orderedClean as $oid) {
                    if (!in_array($oid, $expectedUnion, true)) {
                        $subsetOk = false;
                        break;
                    }
                }
                if ($subsetOk) {
                    $subsetOrdered = true;
                }
            }
        }

        if ($validFullOrdered) {
            $newIds = $clientSortOrder === 'desc' ? array_reverse($orderedClean) : $orderedClean;
        } elseif ($subsetOrdered) {
            $canonicalDom = $clientSortOrder === 'desc' ? array_reverse($orderedClean) : $orderedClean;
            $missingOrdered = [];
            foreach ($peerIds as $pid) {
                if (in_array($pid, $missingForSubset, true)) {
                    $missingOrdered[] = $pid;
                }
            }
            $newIds = array_merge($canonicalDom, $missingOrdered);
        } else {
            $spliceFallbackUsed = true;
            $newIds = $peerIds;
            $fallbackPos = max(0, min($position, count($newIds)));
            $spliced = false;

            if ($insertBeforeId > 0) {
                $idx = array_search($insertBeforeId, $newIds, true);
                if ($idx !== false) {
                    array_splice($newIds, $idx, 0, [$movingId]);
                    $spliced = true;
                }
            }
            if (!$spliced && $insertAfterId > 0) {
                $idx = array_search($insertAfterId, $newIds, true);
                if ($idx !== false) {
                    array_splice($newIds, $idx + 1, 0, [$movingId]);
                    $spliced = true;
                }
            }
            if (!$spliced) {
                array_splice($newIds, $fallbackPos, 0, [$movingId]);
            }
        }

        $newIds = array_map('intval', $newIds);

        $finalPos = array_search($movingId, $newIds, true);
        if ($finalPos === false) {
            $finalPos = $fallbackPos;
        }

        $task->status = $status;
        $task->position = (int)$finalPos;
        $task->last_default_status = $status;

        if ($status == 'done') {
            $task->completed_at = date('Y-m-d H:i:s');
        } else {
            // Re-open / move away from Done: clear completion timestamp (avoids stale completed_at + strict SQL)
            $task->completed_at = null;
        }

        $deletePersonalSQL = "DELETE FROM extra_tasks_columns WHERE task_id = $id";
        $database->query($deletePersonalSQL);

        $boardUpdateOk = false;
        if ($task->update()) {
            if (!crm_batch_update_task_positions($database, $newIds)) {
                ob_clean();
                echo json_encode(['status' => 'error', 'error' => 'Board update failed (database busy). Please refresh and try again.']);
                exit;
            }
            if ($oldStatus !== $status && in_array($oldStatus, $defaultStatuses, true)) {
                $escOld = $database->escapeValue($oldStatus);
                $oldIds = [];
                $rOld = $database->query("SELECT id FROM tasks WHERE status = '$escOld' ORDER BY position ASC, created_at ASC, id ASC");
                if ($rOld) {
                    while ($row = $database->fetchArray($rOld)) {
                        $oldIds[] = (int)$row['id'];
                    }
                }
                if (!empty($oldIds) && !crm_batch_update_task_positions($database, $oldIds)) {
                    ob_clean();
                    echo json_encode(['status' => 'error', 'error' => 'Board update failed (database busy). Please refresh and try again.']);
                    exit;
                }
            }
            $boardUpdateOk = true;
        }

        if ($boardUpdateOk) {
            ob_clean();
            echo json_encode(['status' => 'ok', 'message' => 'Task status updated']);
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            } else {
                @flush();
            }

            try {
                require_once __DIR__ . '/notification_helper.php';
                NotificationHelper::taskStatusChanged($task->id, $task->title, $userId, $status, $task->assigned_to, $task->project_id);
            } catch (Throwable $e) {
                error_log('[board_update] notification: ' . $e->getMessage());
            }

            try {
                require_once __DIR__ . '/task_recurrence_helper.php';
                tasksession_recurrence_maybe_spawn_on_done($task);
            } catch (Throwable $e) {
                error_log('[board_update] recurrence: ' . $e->getMessage());
            }

            $boardUpdateDeferredMails = [];

            try {
                require_once __DIR__ . '/projects.php';
                $project = $task->project_id > 0 ? projects::findByProjectId($task->project_id) : null;
                $projTitle = $project ? $project->project_title : '';
                $settings = settings::findById(1);
                $task_title = $task->title;
                $task_description = $task->description;
                $due_date = $task->due_date;
                $project_name = $projTitle;
                $task_status_display = $status;

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
                                    '{TASK_DESCRIPTION}' => $task_description,
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
                                $boardUpdateDeferredMails[] = [
                                    'to' => $staffUser->email,
                                    'subject' => $subject,
                                    'body' => $messageBody,
                                    'headers' => $headers,
                                ];
                            }
                        }
                    }
                }
                if ($task->project_id > 0 && $project) {
                    $main_client_id = $project->main_client_id ?: $project->c_id;
                    $all_client_ids = [];
                    if (!empty($project->c_ids)) {
                        $all_client_ids = array_filter(explode(',', $project->c_ids));
                    }
                    if (empty($all_client_ids)) {
                        $all_client_ids = [$main_client_id];
                    }
                    foreach ($all_client_ids as $client_id) {
                        $clientUser = User::findById($client_id);
                        if ($clientUser && filter_var($clientUser->email, FILTER_VALIDATE_EMAIL)) {
                            $variablesArr = array(
                                '{USER_NAME}'        => htmlspecialchars($clientUser->firstName, ENT_QUOTES, 'UTF-8'),
                                '{TASK_TITLE}'       => htmlspecialchars($task_title, ENT_QUOTES, 'UTF-8'),
                                '{PROJECT_NAME}'     => htmlspecialchars($project_name, ENT_QUOTES, 'UTF-8'),
                                '{TASK_DESCRIPTION}' => $task_description,
                                '{TASK_STATUS}'      => $task_status_display,
                                '{DUE_DATE}'         => htmlspecialchars($due_date, ENT_QUOTES, 'UTF-8'),
                                '{DASHBOARD_URL}'    => $url,
                                '{SIGNATURE}'        => $company_name
                            );
                            $templateHTML = $settings->task_update_email;
                            if (empty($templateHTML)) {
                                error_log('Email template is empty for task status change client notification: ' . $clientUser->email);
                                continue;
                            }
                            $messageBody = strtr($templateHTML, $variablesArr);
                            $subject = 'Task status has been updated in your project!';
                            $headers  = 'MIME-Version: 1.0' . "\r\n";
                            $headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
                            $headers .= 'From: ' . $company_name . ' <' . $system_email . '>' . "\r\n";
                            $boardUpdateDeferredMails[] = [
                                'to' => $clientUser->email,
                                'subject' => $subject,
                                'body' => $messageBody,
                                'headers' => $headers,
                            ];
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log('[board_update] email block: ' . $e->getMessage());
            }

            if (crm_task_update_email_on_drag_enabled($settings ?? null) && !empty($boardUpdateDeferredMails)) {
                foreach ($boardUpdateDeferredMails as $m) {
                    crm_mail_queue_enqueue($database, $m['to'], $m['subject'], $m['body'], $m['headers'] ?? '');
                }
            }
        } else {
            ob_clean();
            echo json_encode(['status' => 'error', 'error' => 'Failed to update task']);
        }
    }
} catch (Throwable $e) {
    error_log('[board_update] ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode(array(
        'status' => 'error',
        'error' => 'Board update failed',
        'detail' => $e->getMessage(),
        'file' => basename((string) $e->getFile()),
        'line' => (int) $e->getLine(),
    ));
    exit;
}
