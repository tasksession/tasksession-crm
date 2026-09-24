<?php
/**
 * Task session time tracking module gate (settings.module_time_tracking).
 */

if (!function_exists('tasksession_time_tracking_enabled')) {
    /**
     * @param object|null $settings Pass settings row object or null to use global $dash_settings.
     */
    function tasksession_time_tracking_enabled($settings = null): bool
    {
        if ($settings !== null && is_object($settings)) {
            return isset($settings->module_time_tracking) && (int)$settings->module_time_tracking === 1;
        }
        global $dash_settings;
        return isset($dash_settings) && is_object($dash_settings)
            && isset($dash_settings->module_time_tracking)
            && (int)$dash_settings->module_time_tracking === 1;
    }
}

if (!function_exists('tasksession_parse_estimated_time_seconds_from_post')) {
    /**
     * Reads hidden field estimated_time_seconds (integer seconds) when time tracking is enabled.
     */
    function tasksession_parse_estimated_time_seconds_from_post()
    {
        if (!tasksession_time_tracking_enabled()) {
            return null;
        }
        if (!isset($_POST['estimated_time_seconds'])) {
            return null;
        }
        $raw = trim((string)$_POST['estimated_time_seconds']);
        if ($raw === '' || $raw === '0') {
            return null;
        }
        if (!ctype_digit($raw)) {
            return null;
        }
        $n = (int)$raw;
        return $n > 0 ? $n : null;
    }
}

if (!function_exists('tasksession_format_estimated_seconds_display')) {
    /**
     * Human-readable estimate for read-only UI (uses $lang hours/minutes when present).
     *
     * @param int|string|null $seconds
     */
    function tasksession_format_estimated_seconds_display($seconds, array $lang): string
    {
        if ($seconds === null || $seconds === '' || (int)$seconds <= 0) {
            return isset($lang['no_estimate']) ? (string)$lang['no_estimate'] : '—';
        }
        $sec = (int)$seconds;
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $hLabel = isset($lang['hours']) ? (string)$lang['hours'] : 'h';
        $mLabel = isset($lang['minutes']) ? (string)$lang['minutes'] : 'min';
        $parts = [];
        if ($h > 0) {
            $parts[] = $h . ' ' . $hLabel;
        }
        if ($m > 0 || $h === 0) {
            $parts[] = $m . ' ' . $mLabel;
        }
        return implode(' ', $parts);
    }
}

if (!function_exists('tasksession_user_has_active_timer_sessions')) {
    /**
     * True when the user has at least one running or paused task_timer_sessions row.
     */
    function tasksession_user_has_active_timer_sessions(int $userId, $connect = null): bool
    {
        if ($userId <= 0 || !tasksession_time_tracking_enabled()) {
            return false;
        }
        if (!$connect instanceof mysqli) {
            global $connect;
        }
        if (!$connect instanceof mysqli) {
            return false;
        }
        $stmt = mysqli_prepare(
            $connect,
            'SELECT 1 FROM task_timer_sessions WHERE user_id = ? AND status IN (\'running\', \'paused\') LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return false;
        }
        $res = mysqli_stmt_get_result($stmt);
        $has = $res && mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);
        return (bool) $has;
    }
}

if (!function_exists('tasksession_pause_running_timers_for_user')) {
    /**
     * Pause every running task_timer_sessions row for a user (e.g. on logout).
     * Accumulates elapsed time the same way as ajax/task_timer.php pause action.
     */
    function tasksession_pause_running_timers_for_user(mysqli $connect, int $userId): void
    {
        if ($userId <= 0 || !tasksession_time_tracking_enabled()) {
            return;
        }

        if (!mysqli_begin_transaction($connect)) {
            return;
        }

        $sql = 'SELECT user_id, task_id, status, segment_started_at, accumulated_seconds
                FROM task_timer_sessions
                WHERE user_id = ? AND status = \'running\'
                FOR UPDATE';
        $stmt = mysqli_prepare($connect, $sql);
        if (!$stmt) {
            mysqli_rollback($connect);
            return;
        }
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            mysqli_rollback($connect);
            return;
        }
        $res = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        if ($rows === []) {
            mysqli_commit($connect);
            return;
        }

        $upd = mysqli_prepare(
            $connect,
            'UPDATE task_timer_sessions SET status = \'paused\', segment_started_at = NULL, accumulated_seconds = ?, updated_at = NOW() WHERE user_id = ? AND task_id = ?'
        );
        if (!$upd) {
            mysqli_rollback($connect);
            return;
        }

        foreach ($rows as $row) {
            $taskId = (int)($row['task_id'] ?? 0);
            if ($taskId <= 0) {
                continue;
            }
            $acc = (int)($row['accumulated_seconds'] ?? 0);
            if (!empty($row['segment_started_at'])) {
                $start = strtotime((string)$row['segment_started_at']);
                if ($start) {
                    $acc += max(0, time() - $start);
                }
            }
            mysqli_stmt_bind_param($upd, 'iii', $acc, $userId, $taskId);
            if (!mysqli_stmt_execute($upd)) {
                mysqli_stmt_close($upd);
                mysqli_rollback($connect);
                return;
            }
        }

        mysqli_stmt_close($upd);
        mysqli_commit($connect);
    }
}

if (!function_exists('tasksession_seed_task_schedules')) {
    /**
     * Create default scheduled-work rows for each assignee when the task has an estimate.
     *
     * @param array{only_user_ids?: list<int>, skip_if_user_has_row?: bool} $options
     */
    function tasksession_seed_task_schedules($taskId, array $options = [])
    {
        if (!tasksession_time_tracking_enabled()) {
            return 0;
        }
        $path = __DIR__ . '/calendar_event.php';
        if (!is_file($path)) {
            return 0;
        }
        require_once $path;

        return CalendarEvent::seedAssigneeSchedulesFromTask((int) $taskId, $options);
    }
}

if (!function_exists('tasksession_timer_row_elapsed_seconds')) {
    function tasksession_timer_row_elapsed_seconds(array $row): int
    {
        $acc = (int) ($row['accumulated_seconds'] ?? 0);
        if (($row['status'] ?? '') === 'running' && !empty($row['segment_started_at'])) {
            $start = strtotime((string) $row['segment_started_at']);
            if ($start) {
                $acc += max(0, time() - $start);
            }
        }
        return $acc;
    }
}

if (!function_exists('tasksession_user_daily_target_seconds')) {
    /** Default daily time target for dashboard (5 hours). */
    function tasksession_user_daily_target_seconds(int $userId = 0): int
    {
        return 5 * 3600;
    }
}

if (!function_exists('tasksession_user_logged_seconds_today')) {
    function tasksession_user_logged_seconds_today(int $userId, $connect = null): int
    {
        if ($userId <= 0) {
            return 0;
        }
        if (!$connect instanceof mysqli) {
            global $connect;
        }
        if (!$connect instanceof mysqli) {
            return 0;
        }

        $total = 0;
        $stmt = mysqli_prepare(
            $connect,
            'SELECT COALESCE(SUM(duration_seconds), 0) AS sec
             FROM task_time_entries
             WHERE user_id = ? AND DATE(COALESCE(ended_at, started_at)) = CURDATE()'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                $total = (int) ($row['sec'] ?? 0);
            }
            mysqli_stmt_close($stmt);
        }

        if (tasksession_time_tracking_enabled()) {
            $sessStmt = mysqli_prepare(
                $connect,
                'SELECT status, segment_started_at, accumulated_seconds
                 FROM task_timer_sessions
                 WHERE user_id = ? AND status IN (\'running\', \'paused\')'
            );
            if ($sessStmt) {
                mysqli_stmt_bind_param($sessStmt, 'i', $userId);
                if (mysqli_stmt_execute($sessStmt)) {
                    $sessRes = mysqli_stmt_get_result($sessStmt);
                    if ($sessRes) {
                        while ($sessRow = mysqli_fetch_assoc($sessRes)) {
                            $total += tasksession_timer_row_elapsed_seconds($sessRow);
                        }
                    }
                }
                mysqli_stmt_close($sessStmt);
            }
        }

        return max(0, $total);
    }
}

if (!function_exists('tasksession_user_task_entries_count_today')) {
    function tasksession_user_task_entries_count_today(int $userId, $connect = null): int
    {
        if ($userId <= 0) {
            return 0;
        }
        if (!$connect instanceof mysqli) {
            global $connect;
        }
        if (!$connect instanceof mysqli) {
            return 0;
        }
        $count = 0;
        $stmt = mysqli_prepare(
            $connect,
            'SELECT COUNT(*) AS c
             FROM task_time_entries
             WHERE user_id = ? AND DATE(COALESCE(ended_at, started_at)) = CURDATE()'
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            if (mysqli_stmt_execute($stmt)) {
                $res = mysqli_stmt_get_result($stmt);
                $row = $res ? mysqli_fetch_assoc($res) : null;
                $count = (int) ($row['c'] ?? 0);
            }
            mysqli_stmt_close($stmt);
        }
        if (tasksession_time_tracking_enabled()) {
            $sessStmt = mysqli_prepare(
                $connect,
                'SELECT COUNT(*) AS c FROM task_timer_sessions WHERE user_id = ? AND status IN (\'running\', \'paused\')'
            );
            if ($sessStmt) {
                mysqli_stmt_bind_param($sessStmt, 'i', $userId);
                if (mysqli_stmt_execute($sessStmt)) {
                    $res = mysqli_stmt_get_result($sessStmt);
                    $row = $res ? mysqli_fetch_assoc($res) : null;
                    $count += (int) ($row['c'] ?? 0);
                }
                mysqli_stmt_close($sessStmt);
            }
        }
        return max(0, $count);
    }
}

if (!function_exists('tasksession_format_entries_today_label')) {
    function tasksession_format_entries_today_label(int $count, array $lang): string
    {
        $today = (string) ($lang['today'] ?? 'today');
        if ($count === 1) {
            $entry = (string) ($lang['task_time_entry_today'] ?? '1 entry');
            return $entry . ' · ' . $today;
        }
        $tpl = (string) ($lang['task_time_entries_today'] ?? '%d entries');
        return sprintf($tpl, max(0, $count)) . ' · ' . $today;
    }
}

if (!function_exists('tasksession_task_due_label')) {
    function tasksession_task_due_label($dueDate, array $lang): string
    {
        $raw = trim((string) $dueDate);
        if ($raw === '' || $raw === '0000-00-00') {
            return (string) ($lang['No due date'] ?? 'No due date');
        }
        $ts = strtotime($raw);
        if (!$ts) {
            return (string) ($lang['No due date'] ?? 'No due date');
        }
        $dueDay = date('Y-m-d', $ts);
        if ($dueDay === date('Y-m-d')) {
            return (string) ($lang['Due Today'] ?? 'Due today');
        }
        if ($dueDay === date('Y-m-d', strtotime('+1 day'))) {
            return (string) ($lang['Due Tomorrow'] ?? 'Due tomorrow');
        }
        return ((string) ($lang['Due'] ?? 'Due')) . ': ' . date('M j, Y', $ts);
    }
}

if (!function_exists('tasksession_format_duration_hm')) {
    function tasksession_format_duration_hm(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0m';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h > 0) {
            return $h . 'h ' . $m . 'm';
        }
        return $m . 'm';
    }
}

if (!function_exists('tasksession_format_target_duration_label')) {
    function tasksession_format_target_duration_label(int $seconds, array $lang): string
    {
        $seconds = max(0, $seconds);
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $dur = $h > 0
            ? ($m > 0 ? ($h . 'h ' . $m . 'm') : ($h . 'h'))
            : ($m . 'm');
        return trim($dur . ' ' . ((string) ($lang['daily target'] ?? 'daily target')));
    }
}

if (!function_exists('tasksession_dashboard_active_task_for_user')) {
    /**
     * @return array{task_id:int,title:string,due_label:string,timer_status:string,elapsed_seconds:int}|null
     */
    function tasksession_dashboard_active_task_for_user(int $userId, $connect = null, $fallbackWhereSql = ''): ?array
    {
        global $database, $lang;
        if ($userId <= 0) {
            return null;
        }
        if (!$connect instanceof mysqli) {
            global $connect;
        }

        if (tasksession_time_tracking_enabled() && $connect instanceof mysqli) {
            $sql = 'SELECT task_id, status, segment_started_at, accumulated_seconds
                    FROM task_timer_sessions
                    WHERE user_id = ? AND status IN (\'running\', \'paused\')
                    ORDER BY CASE `status` WHEN \'running\' THEN 0 WHEN \'paused\' THEN 1 ELSE 2 END, updated_at DESC
                    LIMIT 1';
            $stmt = mysqli_prepare($connect, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $userId);
                if (mysqli_stmt_execute($stmt)) {
                    $res = mysqli_stmt_get_result($stmt);
                    $sess = $res ? mysqli_fetch_assoc($res) : null;
                    mysqli_stmt_close($stmt);
                    if ($sess) {
                        $taskId = (int) ($sess['task_id'] ?? 0);
                        if ($taskId > 0 && $database) {
                            $task = Task::findById($taskId);
                            if ($task) {
                                return [
                                    'task_id' => $taskId,
                                    'title' => (string) ($task->title ?? ''),
                                    'due_label' => tasksession_task_due_label($task->due_date ?? '', is_array($lang) ? $lang : []),
                                    'timer_status' => (string) ($sess['status'] ?? ''),
                                    'elapsed_seconds' => tasksession_timer_row_elapsed_seconds($sess),
                                ];
                            }
                        }
                    }
                } else {
                    mysqli_stmt_close($stmt);
                }
            }
        }

        if ($fallbackWhereSql !== '' && $database) {
            if (!function_exists('reports_sql_valid_date')) {
                require_once __DIR__ . '/reports_common_helper.php';
            }
            $fallback = $database->fetchArray($database->query(
                "SELECT id, title, due_date
                 FROM tasks
                 WHERE {$fallbackWhereSql}
                   AND status != 'done'
                 ORDER BY
                   CASE WHEN NOT " . reports_sql_valid_date('due_date') . " THEN 1 ELSE 0 END,
                   CASE WHEN DATE(due_date) = CURDATE() THEN 0 ELSE 1 END,
                   due_date ASC
                 LIMIT 1"
            ));
            if (!empty($fallback['id'])) {
                return [
                    'task_id' => (int) $fallback['id'],
                    'title' => (string) ($fallback['title'] ?? ''),
                    'due_label' => tasksession_task_due_label($fallback['due_date'] ?? '', is_array($lang) ? $lang : []),
                    'timer_status' => '',
                    'elapsed_seconds' => 0,
                ];
            }
        }

        return null;
    }
}
