<?php
/**
 * JSON helpers for task-create success modal (AJAX add_task flow).
 */

if (!function_exists('tasksession_is_ajax_task_create_request')) {
    function tasksession_is_ajax_task_create_request(): bool
    {
        $xhr = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $accept = isset($_SERVER['HTTP_ACCEPT'])
            && stripos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        return $xhr || $accept;
    }
}

if (!function_exists('tasksession_task_has_other_assignees')) {
    /**
     * True when at least one assignee is not the current user.
     *
     * @param int[] $assignedIds
     */
    function tasksession_task_has_other_assignees(array $assignedIds, int $currentUserId): bool
    {
        foreach ($assignedIds as $staffId) {
            $staffId = (int) $staffId;
            if ($staffId > 0 && $staffId !== $currentUserId) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('tasksession_build_task_created_redirect_urls')) {
    /**
     * @return array{0:string,1:?string} [kanban_url, task_url]
     */
    function tasksession_build_task_created_redirect_urls(int $projectId, int $taskId, array $assignedIds, int $currentUserId): array
    {
        if ($projectId > 0) {
            $projectTasksUrl = 'task.php?projectId=' . $projectId;
            $taskUrl = $projectTasksUrl;
            if ($taskId > 0) {
                $taskUrl .= '&openTask=' . $taskId;
            }
            return [$projectTasksUrl, $taskUrl];
        }

        if (tasksession_task_has_other_assignees($assignedIds, $currentUserId)) {
            return ['kanban.php?all_tasks=1&sort_order=desc', null];
        }

        return ['kanban.php', null];
    }
}

if (!function_exists('tasksession_build_task_created_payload')) {
    /**
     * @param object $task Task model with id, title, project_id
     * @param int[] $assignedIds
     * @return array<string, mixed>
     */
    function tasksession_build_task_created_payload($task, array $assignedIds): array
    {
        $assignees = [];
        foreach ($assignedIds as $staffId) {
            $staffId = (int) $staffId;
            if ($staffId <= 0) {
                continue;
            }
            $staffUser = User::findById($staffId);
            if (!$staffUser) {
                continue;
            }
            $fullName = trim((string) ($staffUser->firstName ?? '') . ' ' . (string) ($staffUser->lastName ?? ''));
            $assignees[] = [
                'id' => $staffId,
                'name' => $fullName !== '' ? $fullName : (string) ($staffUser->firstName ?? ''),
                'avatar_html' => getUserAvatarHtml(
                    $staffId,
                    $staffUser->firstName ?? '',
                    $staffUser->lastName ?? '',
                    48,
                    48,
                    'rounded-circle',
                    $staffUser->firstName ?? 'User'
                ),
            ];
        }

        $projectId = isset($task->project_id) ? (int) $task->project_id : 0;
        $taskId = (int) ($task->id ?? 0);
        global $session;
        $currentUserId = isset($session) && isset($session->userId) ? (int) $session->userId : 0;
        list($kanbanUrl, $taskUrl) = tasksession_build_task_created_redirect_urls(
            $projectId,
            $taskId,
            $assignedIds,
            $currentUserId
        );

        return [
            'success' => true,
            'task' => [
                'id' => $taskId,
                'title' => (string) ($task->title ?? ''),
                'project_id' => $projectId,
            ],
            'assignees' => $assignees,
            'kanban_url' => $kanbanUrl,
            'task_url' => $taskUrl,
        ];
    }
}

if (!function_exists('tasksession_flush_output_buffers')) {
    function tasksession_flush_output_buffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
}

if (!function_exists('tasksession_send_task_created_json')) {
    function tasksession_send_task_created_json($task, array $assignedIds): void
    {
        tasksession_flush_output_buffers();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(tasksession_build_task_created_payload($task, $assignedIds));
        exit;
    }
}

if (!function_exists('tasksession_send_task_create_error_json')) {
    /**
     * @param string[] $errors
     * @param int $statusCode
     */
    function tasksession_send_task_create_error_json(array $errors, int $statusCode = 422): void
    {
        tasksession_flush_output_buffers();
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'errors' => array_values($errors),
        ]);
        exit;
    }
}
