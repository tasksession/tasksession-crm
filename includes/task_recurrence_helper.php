<?php
/**
 * Task recurring / Set repeats helpers.
 */

if (!function_exists('tasksession_recurrence_tables_ready')) {
    function tasksession_recurrence_tables_ready(): bool
    {
        global $database;
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        if (!isset($database) || !is_object($database)) {
            $ready = false;
            return false;
        }
        $q = $database->query("SHOW TABLES LIKE 'task_recurrences'");
        $ready = $q && $database->numRows($q) > 0;
        return $ready;
    }
}

if (!function_exists('tasksession_recurrence_valid_statuses')) {
    function tasksession_recurrence_valid_statuses(): array
    {
        return ['todo', 'inprogress', 'review', 'done'];
    }
}

if (!function_exists('tasksession_recurrence_sanitize_status')) {
    function tasksession_recurrence_sanitize_status($status, string $fallback = 'todo'): string
    {
        $status = strtolower(trim((string) $status));
        $allowed = tasksession_recurrence_valid_statuses();
        return in_array($status, $allowed, true) ? $status : $fallback;
    }
}

if (!function_exists('tasksession_recurrence_map_for_task_ids')) {
    /**
     * Which of the given task IDs are part of an active recurrence
     * (root task or a spawned occurrence with recurrence_id).
     *
     * @param list<int|string> $taskIds
     * @return array<int,true> map of task_id => true
     */
    function tasksession_recurrence_map_for_task_ids(array $taskIds): array
    {
        $map = [];
        $ids = [];
        foreach ($taskIds as $tid) {
            $tid = (int) $tid;
            if ($tid > 0) {
                $ids[$tid] = $tid;
            }
        }
        if ($ids === [] || !tasksession_recurrence_tables_ready()) {
            return $map;
        }
        global $database;
        $idList = implode(',', $ids);
        $q = $database->query(
            'SELECT id, recurrence_id FROM tasks WHERE id IN (' . $idList . ')'
            . ' AND recurrence_id IS NOT NULL AND recurrence_id > 0'
        );
        if ($q) {
            while ($row = $database->fetchArray($q)) {
                $map[(int) $row['id']] = true;
            }
        }
        $q2 = $database->query(
            'SELECT root_task_id FROM task_recurrences WHERE is_active = 1'
            . ' AND root_task_id IN (' . $idList . ')'
        );
        if ($q2) {
            while ($row = $database->fetchArray($q2)) {
                $rid = (int) ($row['root_task_id'] ?? 0);
                if ($rid > 0) {
                    $map[$rid] = true;
                }
            }
        }
        return $map;
    }
}

if (!function_exists('tasksession_recurrence_task_is_recurring')) {
    /**
     * @param object|array $task
     * @param array<int,true>|null $preloadedMap from tasksession_recurrence_map_for_task_ids
     */
    function tasksession_recurrence_task_is_recurring($task, ?array $preloadedMap = null): bool
    {
        $tid = 0;
        $recurrenceId = 0;
        if (is_object($task)) {
            $tid = isset($task->id) ? (int) $task->id : 0;
            $recurrenceId = isset($task->recurrence_id) ? (int) $task->recurrence_id : 0;
        } elseif (is_array($task)) {
            $tid = isset($task['id']) ? (int) $task['id'] : 0;
            $recurrenceId = isset($task['recurrence_id']) ? (int) $task['recurrence_id'] : 0;
        }
        if ($recurrenceId > 0) {
            return true;
        }
        if ($tid <= 0) {
            return false;
        }
        if (is_array($preloadedMap)) {
            return !empty($preloadedMap[$tid]);
        }
        $map = tasksession_recurrence_map_for_task_ids([$tid]);
        return !empty($map[$tid]);
    }
}

if (!function_exists('tasksession_recurrence_find_by_id')) {
    function tasksession_recurrence_find_by_id(int $id): ?array
    {
        global $database;
        if ($id <= 0 || !tasksession_recurrence_tables_ready()) {
            return null;
        }
        $row = $database->fetchArray($database->query(
            'SELECT * FROM task_recurrences WHERE id = ' . $id . ' LIMIT 1'
        ));
        return is_array($row) && !empty($row['id']) ? $row : null;
    }
}

if (!function_exists('tasksession_recurrence_find_for_task')) {
    function tasksession_recurrence_find_for_task($task): ?array
    {
        if (!$task) {
            return null;
        }
        $rid = isset($task->recurrence_id) ? (int) $task->recurrence_id : 0;
        if ($rid > 0) {
            return tasksession_recurrence_find_by_id($rid);
        }
        $tid = isset($task->id) ? (int) $task->id : 0;
        if ($tid <= 0 || !tasksession_recurrence_tables_ready()) {
            return null;
        }
        global $database;
        $row = $database->fetchArray($database->query(
            'SELECT * FROM task_recurrences WHERE root_task_id = ' . $tid . ' LIMIT 1'
        ));
        return is_array($row) && !empty($row['id']) ? $row : null;
    }
}

if (!function_exists('tasksession_recurrence_to_frontend')) {
    function tasksession_recurrence_to_frontend(?array $row): ?array
    {
        if (!$row || empty($row['is_active'])) {
            return null;
        }
        $due = array_key_exists('due_offset_days', $row) && $row['due_offset_days'] !== null && $row['due_offset_days'] !== ''
            ? (int) $row['due_offset_days']
            : null;
        return [
            'enabled' => 1,
            'mode' => (string) ($row['mode'] ?? 'time_based'),
            'interval_unit' => (string) ($row['interval_unit'] ?? 'day'),
            'interval_count' => max(1, (int) ($row['interval_count'] ?? 1)),
            'skip_weekends' => !empty($row['skip_weekends']) ? 1 : 0,
            'start_from' => (string) ($row['start_from_date'] ?? ''),
            'due_offset_days' => $due,
            'default_status' => tasksession_recurrence_sanitize_status($row['default_status'] ?? 'todo'),
            'estimated_time_seconds' => isset($row['estimated_time_seconds']) && $row['estimated_time_seconds'] !== null
                ? (int) $row['estimated_time_seconds']
                : null,
            'after_status' => tasksession_recurrence_sanitize_status($row['after_status'] ?? 'todo'),
            'ends_at' => (string) ($row['ends_at'] ?? ''),
        ];
    }
}

if (!function_exists('tasksession_recurrence_parse_post')) {
    function tasksession_recurrence_parse_post(): array
    {
        $enabled = isset($_POST['repeat_enabled']) && (string) $_POST['repeat_enabled'] === '1';
        $mode = isset($_POST['repeat_mode']) && $_POST['repeat_mode'] === 'after_completion'
            ? 'after_completion'
            : 'time_based';
        $unit = strtolower(trim((string) ($_POST['repeat_interval_unit'] ?? 'day')));
        $units = ['day', 'workday', 'week', 'month', 'year'];
        if (!in_array($unit, $units, true)) {
            $unit = 'day';
        }
        $count = max(1, min(365, (int) ($_POST['repeat_interval_count'] ?? 1)));
        $skip = !empty($_POST['repeat_skip_weekends']) ? 1 : 0;
        $startFrom = trim((string) ($_POST['repeat_start_from'] ?? ''));
        if ($startFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startFrom)) {
            $startFrom = '';
        }
        $dueRaw = isset($_POST['repeat_due_offset_days']) ? trim((string) $_POST['repeat_due_offset_days']) : '';
        $dueOffset = ($dueRaw === '' || strtolower($dueRaw) === 'null') ? null : max(0, min(365, (int) $dueRaw));
        $endsAt = trim((string) ($_POST['repeat_ends_at'] ?? ''));
        if ($endsAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsAt)) {
            $endsAt = '';
        }
        $estRaw = isset($_POST['repeat_estimated_time_seconds']) ? trim((string) $_POST['repeat_estimated_time_seconds']) : '';
        $est = ($estRaw === '') ? null : max(0, (int) $estRaw);

        return [
            'enabled' => $enabled,
            'mode' => $mode,
            'interval_unit' => $mode === 'time_based' ? $unit : null,
            'interval_count' => $count,
            'skip_weekends' => $skip,
            'start_from' => $startFrom,
            'due_offset_days' => $dueOffset,
            'default_status' => tasksession_recurrence_sanitize_status($_POST['repeat_default_status'] ?? 'todo'),
            'estimated_time_seconds' => $est,
            'after_status' => tasksession_recurrence_sanitize_status($_POST['repeat_after_status'] ?? 'todo'),
            'ends_at' => $endsAt,
        ];
    }
}

if (!function_exists('tasksession_recurrence_shift_off_weekend')) {
    function tasksession_recurrence_shift_off_weekend(DateTime $dt): DateTime
    {
        $n = (int) $dt->format('N');
        if ($n === 6) {
            $dt->modify('+2 days');
        } elseif ($n === 7) {
            $dt->modify('+1 day');
        }
        return $dt;
    }
}

if (!function_exists('tasksession_recurrence_add_interval')) {
    function tasksession_recurrence_add_interval(string $fromDate, string $unit, int $count, bool $skipWeekends): ?string
    {
        try {
            $dt = new DateTime($fromDate);
        } catch (Exception $e) {
            return null;
        }
        $count = max(1, $count);
        switch ($unit) {
            case 'workday':
                $added = 0;
                while ($added < $count) {
                    $dt->modify('+1 day');
                    $n = (int) $dt->format('N');
                    if ($n < 6) {
                        $added++;
                    }
                }
                break;
            case 'week':
                $dt->modify('+' . $count . ' week');
                break;
            case 'month':
                $dt->modify('+' . $count . ' month');
                break;
            case 'year':
                $dt->modify('+' . $count . ' year');
                break;
            case 'day':
            default:
                $dt->modify('+' . $count . ' day');
                if ($skipWeekends) {
                    tasksession_recurrence_shift_off_weekend($dt);
                }
                break;
        }
        if ($skipWeekends && $unit !== 'workday' && $unit !== 'day') {
            tasksession_recurrence_shift_off_weekend($dt);
        }
        return $dt->format('Y-m-d');
    }
}

if (!function_exists('tasksession_recurrence_apply_due')) {
    function tasksession_recurrence_apply_due(string $startDate, $offsetDays): ?string
    {
        if ($offsetDays === null || $offsetDays === '') {
            return null;
        }
        try {
            $dt = new DateTime($startDate);
            $dt->modify('+' . max(0, (int) $offsetDays) . ' day');
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }
}

if (!function_exists('tasksession_recurrence_deactivate')) {
    function tasksession_recurrence_deactivate(int $recurrenceId): void
    {
        global $database;
        if ($recurrenceId <= 0 || !tasksession_recurrence_tables_ready()) {
            return;
        }
        $database->query('UPDATE task_recurrences SET is_active = 0 WHERE id = ' . $recurrenceId . ' LIMIT 1');
    }
}

if (!function_exists('tasksession_recurrence_record_occurrence')) {
    function tasksession_recurrence_record_occurrence(int $recurrenceId, int $taskId, string $occurrenceDate): bool
    {
        global $database;
        if ($recurrenceId <= 0 || $taskId < 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $occurrenceDate)) {
            return false;
        }
        $escDate = $database->escapeValue($occurrenceDate);
        $exists = $database->fetchArray($database->query(
            'SELECT id FROM task_recurrence_occurrences WHERE recurrence_id = ' . $recurrenceId
            . " AND occurrence_date = '" . $escDate . "' LIMIT 1"
        ));
        if (is_array($exists) && !empty($exists['id'])) {
            return false;
        }
        $ok = $database->querySoft(
            'INSERT IGNORE INTO task_recurrence_occurrences (recurrence_id, task_id, occurrence_date) VALUES ('
            . $recurrenceId . ', ' . $taskId . ", '" . $escDate . "')"
        );
        return (bool) $ok && (int) $database->affectedRows() > 0;
    }
}

if (!function_exists('tasksession_recurrence_source_has_child')) {
    function tasksession_recurrence_source_has_child(int $sourceTaskId, int $recurrenceId): bool
    {
        global $database;
        if ($sourceTaskId <= 0 || $recurrenceId <= 0) {
            return false;
        }
        $row = $database->fetchArray($database->query(
            'SELECT id FROM tasks WHERE recurrence_parent_task_id = ' . $sourceTaskId
            . ' AND recurrence_id = ' . $recurrenceId . ' LIMIT 1'
        ));
        return is_array($row) && !empty($row['id']);
    }
}

if (!function_exists('tasksession_recurrence_next_position')) {
    function tasksession_recurrence_next_position(int $projectId, string $status): int
    {
        global $database;
        $esc = $database->escapeValue($status);
        $row = $database->fetchArray($database->query(
            'SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM tasks WHERE project_id = '
            . $projectId . " AND status = '" . $esc . "'"
        ));
        return (int) ($row['pos'] ?? 0);
    }
}

if (!function_exists('tasksession_recurrence_save_from_post')) {
    function tasksession_recurrence_save_from_post($task): void
    {
        tasksession_recurrence_save($task, tasksession_recurrence_parse_post());
    }
}

if (!function_exists('tasksession_recurrence_save')) {
    /**
     * Persist recurrence for a task (shared by add_task UI and AI tasks.create).
     *
     * @param object $task Task with id (+ start_date preferred)
     * @param array $parsed From tasksession_recurrence_parse_post() or AI-built schedule
     */
    function tasksession_recurrence_save($task, array $parsed): void
    {
        global $database;
        if (!$task || empty($task->id) || !tasksession_recurrence_tables_ready()) {
            return;
        }
        if (!isset($database) || !is_object($database)) {
            return;
        }
        $taskId = (int) $task->id;
        $existing = tasksession_recurrence_find_for_task($task);

        if (empty($parsed['enabled'])) {
            if ($existing) {
                tasksession_recurrence_deactivate((int) $existing['id']);
            }
            return;
        }

        $mode = (($parsed['mode'] ?? '') === 'after_completion') ? 'after_completion' : 'time_based';
        $unit = strtolower(trim((string) ($parsed['interval_unit'] ?? 'day')));
        $units = ['day', 'workday', 'week', 'month', 'year'];
        if (!in_array($unit, $units, true)) {
            $unit = 'day';
        }
        $count = max(1, min(365, (int) ($parsed['interval_count'] ?? 1)));
        $skip = !empty($parsed['skip_weekends']) ? 1 : 0;
        $startFrom = trim((string) ($parsed['start_from'] ?? ''));
        if ($startFrom === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startFrom)) {
            $startFrom = (string) ($task->start_date ?? date('Y-m-d'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startFrom)) {
            $startFrom = date('Y-m-d');
        }
        $dueOffset = array_key_exists('due_offset_days', $parsed) ? $parsed['due_offset_days'] : null;
        if ($dueOffset !== null && $dueOffset !== '') {
            $dueOffset = max(0, min(365, (int) $dueOffset));
        } else {
            $dueOffset = null;
        }
        $endsAt = trim((string) ($parsed['ends_at'] ?? ''));
        if ($endsAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endsAt)) {
            $endsAt = '';
        }
        $est = array_key_exists('estimated_time_seconds', $parsed) ? $parsed['estimated_time_seconds'] : null;
        if ($est !== null && $est !== '') {
            $est = max(0, (int) $est);
        } else {
            $est = null;
        }

        $parsedNorm = [
            'enabled' => true,
            'mode' => $mode,
            'interval_unit' => $mode === 'time_based' ? $unit : null,
            'interval_count' => $count,
            'skip_weekends' => $skip,
            'start_from' => $startFrom,
            'due_offset_days' => $dueOffset,
            'default_status' => tasksession_recurrence_sanitize_status($parsed['default_status'] ?? 'todo'),
            'estimated_time_seconds' => $est,
            'after_status' => tasksession_recurrence_sanitize_status($parsed['after_status'] ?? 'todo'),
            'ends_at' => $endsAt,
        ];

        $endsSql = $parsedNorm['ends_at'] !== '' ? ("'" . $database->escapeValue($parsedNorm['ends_at']) . "'") : 'NULL';
        $dueSql = $parsedNorm['due_offset_days'] === null ? 'NULL' : (string) (int) $parsedNorm['due_offset_days'];
        $estSql = $parsedNorm['estimated_time_seconds'] === null ? 'NULL' : (string) (int) $parsedNorm['estimated_time_seconds'];
        $unitSql = $parsedNorm['interval_unit'] ? ("'" . $database->escapeValue($parsedNorm['interval_unit']) . "'") : 'NULL';
        $modeEsc = $database->escapeValue($parsedNorm['mode']);
        $defStatus = $database->escapeValue($parsedNorm['default_status']);
        $afterStatus = $database->escapeValue($parsedNorm['after_status']);
        $startSql = "'" . $database->escapeValue($startFrom) . "'";

        $nextRun = 'NULL';
        if ($parsedNorm['mode'] === 'time_based') {
            $keepNext = false;
            if ($existing && ($existing['mode'] ?? '') === 'time_based') {
                $sameSchedule =
                    (string) ($existing['interval_unit'] ?? '') === (string) $parsedNorm['interval_unit']
                    && (int) ($existing['interval_count'] ?? 1) === (int) $parsedNorm['interval_count']
                    && (int) ($existing['skip_weekends'] ?? 0) === (int) $parsedNorm['skip_weekends']
                    && (string) ($existing['start_from_date'] ?? '') === $startFrom;
                $existingNext = (string) ($existing['next_run_date'] ?? '');
                if ($sameSchedule && preg_match('/^\d{4}-\d{2}-\d{2}$/', $existingNext)) {
                    $keepNext = true;
                    $nextRun = "'" . $database->escapeValue($existingNext) . "'";
                }
            }
            if (!$keepNext) {
                $next = tasksession_recurrence_add_interval(
                    $startFrom,
                    (string) $parsedNorm['interval_unit'],
                    (int) $parsedNorm['interval_count'],
                    (bool) $parsedNorm['skip_weekends']
                );
                if ($existing && $next) {
                    $today = date('Y-m-d');
                    $guard = 0;
                    while ($next <= $today && $guard++ < 400) {
                        $advanced = tasksession_recurrence_add_interval(
                            $next,
                            (string) $parsedNorm['interval_unit'],
                            (int) $parsedNorm['interval_count'],
                            (bool) $parsedNorm['skip_weekends']
                        );
                        if (!$advanced || $advanced === $next) {
                            break;
                        }
                        $next = $advanced;
                    }
                }
                if ($next) {
                    $nextRun = "'" . $database->escapeValue($next) . "'";
                }
            }
        }

        if ($existing) {
            $rid = (int) $existing['id'];
            $database->query(
                'UPDATE task_recurrences SET mode = \'' . $modeEsc . '\', interval_unit = ' . $unitSql
                . ', interval_count = ' . (int) $parsedNorm['interval_count']
                . ', skip_weekends = ' . (int) $parsedNorm['skip_weekends']
                . ', start_from_date = ' . $startSql
                . ', due_offset_days = ' . $dueSql
                . ', default_status = \'' . $defStatus . '\''
                . ', estimated_time_seconds = ' . $estSql
                . ', after_status = \'' . $afterStatus . '\''
                . ', ends_at = ' . $endsSql
                . ', next_run_date = ' . $nextRun
                . ', is_active = 1 WHERE id = ' . $rid . ' LIMIT 1'
            );
        } else {
            $database->query(
                'INSERT INTO task_recurrences (root_task_id, mode, interval_unit, interval_count, skip_weekends, start_from_date, due_offset_days, default_status, estimated_time_seconds, after_status, ends_at, next_run_date, is_active) VALUES ('
                . $taskId . ', \'' . $modeEsc . '\', ' . $unitSql . ', ' . (int) $parsedNorm['interval_count'] . ', '
                . (int) $parsedNorm['skip_weekends'] . ', ' . $startSql . ', ' . $dueSql . ', \'' . $defStatus . '\', '
                . $estSql . ', \'' . $afterStatus . '\', ' . $endsSql . ', ' . $nextRun . ', 1)'
            );
            $rid = (int) $database->insertId();
            if ($rid > 0) {
                $database->query('UPDATE tasks SET recurrence_id = ' . $rid . ' WHERE id = ' . $taskId . ' LIMIT 1');
                if (isset($task->recurrence_id)) {
                    $task->recurrence_id = $rid;
                }
                if ($parsedNorm['mode'] === 'time_based' && function_exists('tasksession_recurrence_record_occurrence')) {
                    tasksession_recurrence_record_occurrence($rid, $taskId, $startFrom);
                }
            }
        }
    }
}

if (!function_exists('tasksession_recurrence_spawn_next')) {
    /**
     * @param array<string,mixed> $recurrence
     */
    function tasksession_recurrence_spawn_next(array $recurrence, $sourceTask, string $occurrenceDate): ?object
    {
        if (!$sourceTask || empty($sourceTask->id) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $occurrenceDate)) {
            return null;
        }
        $rid = (int) ($recurrence['id'] ?? 0);
        if ($rid <= 0) {
            return null;
        }
        $endsAt = trim((string) ($recurrence['ends_at'] ?? ''));
        if ($endsAt !== '' && $occurrenceDate > $endsAt) {
            tasksession_recurrence_deactivate($rid);
            return null;
        }
        $mode = (string) ($recurrence['mode'] ?? 'time_based');
        if ($mode === 'after_completion' && tasksession_recurrence_source_has_child((int) $sourceTask->id, $rid)) {
            return null;
        }
        $occRowDate = $occurrenceDate;
        if ($mode === 'after_completion') {
            $shiftGuard = 0;
            $recorded = false;
            while ($shiftGuard++ < 400) {
                if (tasksession_recurrence_record_occurrence($rid, 0, $occRowDate)) {
                    $recorded = true;
                    break;
                }
                $shifted = date('Y-m-d', strtotime($occRowDate . ' +1 day'));
                if (!$shifted || $shifted === $occRowDate) {
                    break;
                }
                $occRowDate = $shifted;
            }
            if (!$recorded) {
                return null;
            }
        } elseif (!tasksession_recurrence_record_occurrence($rid, 0, $occurrenceDate)) {
            return null;
        }

        $status = tasksession_recurrence_sanitize_status($recurrence['default_status'] ?? 'todo');
        $projectId = isset($sourceTask->project_id) ? (int) $sourceTask->project_id : 0;
        $due = tasksession_recurrence_apply_due($occurrenceDate, $recurrence['due_offset_days'] ?? null);
        $est = isset($recurrence['estimated_time_seconds']) && $recurrence['estimated_time_seconds'] !== null
            ? (int) $recurrence['estimated_time_seconds']
            : (isset($sourceTask->estimated_time_seconds) ? (int) $sourceTask->estimated_time_seconds : null);

        $newTask = Task::createFromClone($sourceTask, [
            'keep_title' => true,
            'user_id' => isset($sourceTask->user_id) ? (int) $sourceTask->user_id : 0,
            'creator_id' => isset($sourceTask->creator_id) ? (int) $sourceTask->creator_id : 0,
        ]);
        if (!$newTask) {
            global $database;
            $escDate = $database->escapeValue($occRowDate);
            $database->query(
                'DELETE FROM task_recurrence_occurrences WHERE recurrence_id = ' . $rid
                . " AND occurrence_date = '" . $escDate . "' AND task_id = 0 LIMIT 1"
            );
            return null;
        }

        $newTask->status = $status;
        $newTask->start_date = $occurrenceDate;
        $newTask->due_date = $due ?: $occurrenceDate;
        $newTask->estimated_time_seconds = $est;
        $newTask->recurrence_id = $rid;
        $newTask->recurrence_parent_task_id = (int) $sourceTask->id;
        $newTask->completed_at = null;
        $newTask->is_archived = 0;
        $newTask->position = tasksession_recurrence_next_position($projectId, $status);
        $newTask->save();

        global $database;
        $escDate = $database->escapeValue($occRowDate);
        $database->query(
            'UPDATE task_recurrence_occurrences SET task_id = ' . (int) $newTask->id
            . ' WHERE recurrence_id = ' . $rid . " AND occurrence_date = '" . $escDate . "' LIMIT 1"
        );

        if (function_exists('tasksession_seed_task_schedules')) {
            try {
                tasksession_seed_task_schedules((int) $newTask->id, ['skip_if_user_has_row' => false]);
            } catch (Throwable $e) {
                error_log('[task_recurrence] seed schedules: ' . $e->getMessage());
            }
        }

        try {
            require_once __DIR__ . '/notification_helper.php';
            $actorId = (int) ($newTask->creator_id ?? $sourceTask->creator_id ?? 0);
            NotificationHelper::taskCreated(
                (int) $newTask->id,
                (string) $newTask->title,
                $actorId,
                $newTask->assigned_to,
                $projectId > 0 ? $projectId : null,
                true
            );
            NotificationHelper::sendTaskCreatedEmails($newTask, true);
        } catch (Throwable $e) {
            error_log('[task_recurrence] notify: ' . $e->getMessage());
        }

        return $newTask;
    }
}

if (!function_exists('tasksession_recurrence_latest_task')) {
    function tasksession_recurrence_latest_task(int $recurrenceId)
    {
        global $database;
        if ($recurrenceId <= 0) {
            return null;
        }
        $q = $database->query(
            'SELECT task_id FROM task_recurrence_occurrences WHERE recurrence_id = ' . $recurrenceId
            . ' ORDER BY occurrence_date DESC, id DESC'
        );
        if ($q) {
            while ($row = $database->fetchArray($q)) {
                $tid = (int) ($row['task_id'] ?? 0);
                if ($tid <= 0) {
                    continue;
                }
                $task = Task::findById($tid);
                if ($task) {
                    return $task;
                }
            }
        }
        $rule = tasksession_recurrence_find_by_id($recurrenceId);
        $tid = (int) ($rule['root_task_id'] ?? 0);
        return $tid > 0 ? Task::findById($tid) : null;
    }
}

if (!function_exists('tasksession_recurrence_maybe_spawn_on_done')) {
    function tasksession_recurrence_maybe_spawn_on_done($task): void
    {
        if (!$task || empty($task->id)) {
            return;
        }
        $status = strtolower(trim((string) ($task->status ?? '')));
        if ($status !== 'done') {
            return;
        }
        $rule = tasksession_recurrence_find_for_task($task);
        if (!$rule || empty($rule['is_active']) || ($rule['mode'] ?? '') !== 'after_completion') {
            return;
        }
        $today = date('Y-m-d');
        tasksession_recurrence_spawn_next($rule, $task, $today);
    }
}

if (!function_exists('tasksession_recurrence_run_due_time_based')) {
    function tasksession_recurrence_run_due_time_based(): array
    {
        global $database;
        $created = 0;
        $skipped = 0;
        if (!tasksession_recurrence_tables_ready()) {
            return ['created' => 0, 'skipped' => 0];
        }
        $today = date('Y-m-d');
        $q = $database->query(
            "SELECT * FROM task_recurrences WHERE is_active = 1 AND mode = 'time_based'"
            . " AND next_run_date IS NOT NULL AND next_run_date <= '" . $database->escapeValue($today) . "'"
        );
        if (!$q) {
            return ['created' => 0, 'skipped' => 0];
        }
        while ($rule = $database->fetchArray($q)) {
            $guard = 0;
            while ($guard++ < 31) {
                $occDate = (string) ($rule['next_run_date'] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $occDate) || $occDate > $today) {
                    break;
                }
                $source = tasksession_recurrence_latest_task((int) $rule['id']);
                if (!$source) {
                    tasksession_recurrence_deactivate((int) $rule['id']);
                    $skipped++;
                    break;
                }
                $spawned = tasksession_recurrence_spawn_next($rule, $source, $occDate);
                $next = tasksession_recurrence_add_interval(
                    $occDate,
                    (string) ($rule['interval_unit'] ?? 'day'),
                    (int) ($rule['interval_count'] ?? 1),
                    !empty($rule['skip_weekends'])
                );
                if (!$next) {
                    $skipped++;
                    break;
                }
                $endsAt = trim((string) ($rule['ends_at'] ?? ''));
                if ($endsAt !== '' && $next > $endsAt) {
                    tasksession_recurrence_deactivate((int) $rule['id']);
                    break;
                }
                $database->query(
                    "UPDATE task_recurrences SET next_run_date = '" . $database->escapeValue($next)
                    . "' WHERE id = " . (int) $rule['id'] . ' LIMIT 1'
                );
                $rule['next_run_date'] = $next;
                if ($spawned) {
                    $created++;
                } else {
                    $skipped++;
                }
            }
        }
        return ['created' => $created, 'skipped' => $skipped];
    }
}
