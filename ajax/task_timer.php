<?php
/**
 * Task Time Tracking API (timer sessions + time entry logs + estimate updates).
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/../includes/lib-initialize.php';
require_once __DIR__ . '/../includes/task.php';
require_once __DIR__ . '/../includes/projects.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf-middleware.php';
require_once __DIR__ . '/../includes/calendar_event.php';
require_once __DIR__ . '/../includes/time_tracking_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!$session->isLoggedIn()) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

global $lang;
if (!is_array($lang)) {
    $lang = [];
}

function tt_msg(string $key, string $fallback = ''): string
{
    global $lang;
    if (isset($lang[$key]) && $lang[$key] !== '') {
        return (string)$lang[$key];
    }
    return $fallback !== '' ? $fallback : $key;
}

function tt_json_exit(array $payload, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

ensure_user_permissions($connect);

if (!function_exists('tasksession_time_tracking_enabled') || !tasksession_time_tracking_enabled()) {
    tt_json_exit(['status' => 'error', 'message' => tt_msg('time_tracking_disabled', 'Time tracking is disabled.')], 403);
}

$userId = (int)$session->userId;
$accountStatus = isset($_SESSION['accountStatus']) ? (int)$_SESSION['accountStatus'] : 0;

function canAccessParentTaskTimer(int $taskId): bool
{
    global $session;
    $task = Task::findById($taskId);
    if (!$task) {
        return false;
    }
    $userId = (int)$session->userId;
    $accountStatus = isset($_SESSION['accountStatus']) ? (int)$_SESSION['accountStatus'] : 0;

    if ($accountStatus === 1) {
        return true;
    }
    if ($accountStatus === 2) {
        return $task->isClientAssociated($userId);
    }
    if ($accountStatus === 3) {
        return staff_can_view_task($task, $userId);
    }
    return false;
}

function timer_elapsed_seconds(array $row): int
{
    $acc = (int)($row['accumulated_seconds'] ?? 0);
    if (($row['status'] ?? '') === 'running' && !empty($row['segment_started_at'])) {
        $start = strtotime((string)$row['segment_started_at']);
        if ($start) {
            $acc += max(0, time() - $start);
        }
    }
    return $acc;
}

function staff_can_view_time_entry(Task $task, int $entryUserId): bool
{
    global $session;
    $me = (int) $session->userId;
    if ($entryUserId === $me) {
        return true;
    }
    $canSeeOtherRow = has_permission('task_view_all') || $task->isAssignedTo($me);
    if (!$canSeeOtherRow) {
        return false;
    }

    return has_permission('timer_log_view_others');
}

function can_edit_task_estimate(): bool
{
    global $accountStatus;
    if ($accountStatus === 1) {
        return true;
    }
    if ($accountStatus === 3) {
        return has_permission('task_edit');
    }
    return false;
}

function can_mutate_timer(): bool
{
    global $accountStatus;
    return $accountStatus === 1 || $accountStatus === 3;
}

/**
 * Who may set "Task is completed" when saving timer/manual log — aligned with includes/board_update.php
 * drag-to-done rules (task_status_update + project scope), plus task_edit and admin.
 */
function tt_can_mark_task_done_with_timer(Task $task): bool
{
    global $session;
    if (!function_exists('has_permission')) {
        return false;
    }
    $userId = (int)$session->userId;
    $accountStatus = isset($_SESSION['accountStatus']) ? (int)$_SESSION['accountStatus'] : 0;

    if ($accountStatus === 1) {
        return true;
    }
    if (has_permission('task_edit')) {
        return true;
    }
    if ($accountStatus === 3 && has_permission('task_status_update')) {
        return staff_can_view_task($task, $userId);
    }
    if ($accountStatus === 2) {
        return $task->isClientAssociated($userId);
    }

    return false;
}

function tt_rows_find_task(array $rows, int $taskId): ?array
{
    foreach ($rows as $r) {
        if ((int)($r['task_id'] ?? 0) === $taskId) {
            return $r;
        }
    }
    return null;
}

/** First running row whose task_id is not $excludeTaskId, or null. */
function tt_rows_running_other(array $rows, int $excludeTaskId): ?array
{
    foreach ($rows as $r) {
        if (strtolower(trim((string)($r['status'] ?? ''))) === 'running' && (int)($r['task_id'] ?? 0) !== $excludeTaskId) {
            return $r;
        }
    }
    return null;
}

/** Time entry row for task_id + entry id (timer log mutations). */
function tt_fetch_time_entry(mysqli $connect, int $entryId, int $expectedTaskId): ?array
{
    $stmt = mysqli_prepare(
        $connect,
        'SELECT id, task_id, project_id, user_id, started_at, ended_at, duration_seconds, billable, notes, source FROM task_time_entries WHERE id = ? AND task_id = ? LIMIT 1'
    );
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $entryId, $expectedTaskId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = ($result && ($r = mysqli_fetch_assoc($result))) ? $r : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function tt_assert_can_mutate_time_entry(Task $task, array $entryRow, int $accountStatus, string $mutateMode = 'update'): void
{
    global $session;
    $mutateMode = strtolower($mutateMode) === 'delete' ? 'delete' : 'update';
    $entryTaskId = (int)($entryRow['task_id'] ?? 0);
    if ($entryTaskId !== (int) $task->id) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('invalid_task', 'Invalid task.')], 400);
    }
    if (!canAccessParentTaskTimer($entryTaskId)) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
    }
    $entryUid = (int)($entryRow['user_id'] ?? 0);
    if ($accountStatus === 1) {
        return;
    }
    if ($accountStatus === 2) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
    }
    if ($accountStatus === 3) {
        if (!staff_can_view_time_entry($task, $entryUid)) {
            tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
        }
        $me = (int) $session->userId;
        if ($entryUid !== $me) {
            if ($mutateMode === 'delete' && !has_permission('timer_log_delete')) {
                tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
            }
            if ($mutateMode === 'update' && !has_permission('timer_log_edit')) {
                tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
            }
        } else {
            if ($mutateMode === 'delete' && !has_permission('timer_log_delete_own')) {
                tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
            }
            if ($mutateMode === 'update' && !has_permission('timer_log_edit_own')) {
                tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
            }
        }

        return;
    }
    tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
}

/** First running row, or null. */
function tt_rows_any_running(array $rows): ?array
{
    foreach ($rows as $r) {
        if (strtolower(trim((string)($r['status'] ?? ''))) === 'running') {
            return $r;
        }
    }
    return null;
}

/**
 * Begin transaction and lock all timer session rows for this user (ordered).
 *
 * @return list<array<string,mixed>>|false
 */
function tt_begin_and_lock_user_sessions(mysqli $connect, int $userId)
{
    if (!mysqli_begin_transaction($connect)) {
        return false;
    }
    $sql = 'SELECT user_id, task_id, project_id, status, segment_started_at, accumulated_seconds FROM task_timer_sessions WHERE user_id = ? ORDER BY CASE `status` WHEN \'running\' THEN 0 WHEN \'paused\' THEN 1 ELSE 2 END, updated_at DESC FOR UPDATE';
    $stmt = mysqli_prepare($connect, $sql);
    if (!$stmt) {
        mysqli_rollback($connect);
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        mysqli_rollback($connect);
        return false;
    }
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/** One entry for GET ?action=state (sessions[] + legacy fields). */
function tt_state_entry_from_row(mysqli $connect, array $row): array
{
    global $lang;
    $taskId = (int)$row['task_id'];
    $elapsed = timer_elapsed_seconds($row);
    $taskTitle = '';
    $projectTitle = '';
    $taskEstimatedSeconds = null;
    $t = Task::findById($taskId);
    if ($t && canAccessParentTaskTimer($taskId)) {
        $taskTitle = (string)$t->title;
        if (!empty($t->project_id)) {
            $proj = projects::findByProjectId((int)$t->project_id);
            $projectTitle = $proj ? (string)$proj->project_title : '';
        } else {
            $projectTitle = isset($lang['Internal Task']) ? (string)$lang['Internal Task'] : 'Internal Task';
        }
        if (isset($t->estimated_time_seconds) && $t->estimated_time_seconds !== null && $t->estimated_time_seconds !== '') {
            $taskEstimatedSeconds = (int)$t->estimated_time_seconds;
            if ($taskEstimatedSeconds <= 0) {
                $taskEstimatedSeconds = null;
            }
        }
    }

    return [
        'user_id' => (int)$row['user_id'],
        'task_id' => $taskId,
        'project_id' => $row['project_id'] !== null ? (int)$row['project_id'] : null,
        'status' => (string)$row['status'],
        'segment_started_at' => $row['segment_started_at'],
        'accumulated_seconds' => (int)$row['accumulated_seconds'],
        'elapsed_display_seconds' => $elapsed,
        'task_title' => $taskTitle,
        'project_title' => $projectTitle,
        'task_estimated_seconds' => $taskEstimatedSeconds,
    ];
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// ----- GET: state + logs -----
if ($method === 'GET') {
    $action = isset($_GET['action']) ? (string)$_GET['action'] : '';

    if ($action === 'state') {
        $sql = 'SELECT user_id, task_id, project_id, status, segment_started_at, accumulated_seconds, created_at, updated_at FROM task_timer_sessions WHERE user_id = ? ORDER BY CASE `status` WHEN \'running\' THEN 0 WHEN \'paused\' THEN 1 ELSE 2 END, updated_at DESC';
        $stmt = mysqli_prepare($connect, $sql);
        if (!$stmt) {
            tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
        }
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $sessionRows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $sessionRows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);

        $taskCache = [];
        $projectCache = [];
        $sessionsOut = [];
        foreach ($sessionRows as $row) {
            $taskId = (int)$row['task_id'];
            if (!isset($taskCache[$taskId])) {
                $taskCache[$taskId] = Task::findById($taskId);
            }
            $task = $taskCache[$taskId];
            $taskTitle = '';
            $projectTitle = '';
            $taskEstimatedSeconds = null;
            if ($task && canAccessParentTaskTimer($taskId)) {
                $taskTitle = (string)$task->title;
                if (!empty($task->project_id)) {
                    $pid = (int)$task->project_id;
                    if (!isset($projectCache[$pid])) {
                        $proj = projects::findByProjectId($pid);
                        $projectCache[$pid] = $proj ? (string)$proj->project_title : '';
                    }
                    $projectTitle = $projectCache[$pid];
                } else {
                    $projectTitle = isset($lang['Internal Task']) ? (string)$lang['Internal Task'] : 'Internal Task';
                }
                if (isset($task->estimated_time_seconds) && $task->estimated_time_seconds !== null && $task->estimated_time_seconds !== '') {
                    $taskEstimatedSeconds = (int)$task->estimated_time_seconds;
                    if ($taskEstimatedSeconds <= 0) {
                        $taskEstimatedSeconds = null;
                    }
                }
            }
            $sessionsOut[] = [
                'user_id' => (int)$row['user_id'],
                'task_id' => $taskId,
                'project_id' => $row['project_id'] !== null ? (int)$row['project_id'] : null,
                'status' => (string)$row['status'],
                'segment_started_at' => $row['segment_started_at'],
                'accumulated_seconds' => (int)$row['accumulated_seconds'],
                'elapsed_display_seconds' => timer_elapsed_seconds($row),
                'task_title' => $taskTitle,
                'project_title' => $projectTitle,
                'task_estimated_seconds' => $taskEstimatedSeconds,
            ];
        }

        $primary = null;
        foreach ($sessionsOut as $entry) {
            if (($entry['status'] ?? '') === 'running') {
                $primary = $entry;
                break;
            }
        }
        if ($primary === null && $sessionsOut !== []) {
            $primary = $sessionsOut[0];
        }

        $sessionOut = null;
        $elapsed = 0;
        $taskTitle = '';
        $projectTitle = '';
        $taskEstimatedSeconds = null;
        if ($primary !== null) {
            $elapsed = (int)$primary['elapsed_display_seconds'];
            $taskTitle = (string)$primary['task_title'];
            $projectTitle = (string)$primary['project_title'];
            $taskEstimatedSeconds = $primary['task_estimated_seconds'];
            $sessionOut = [
                'user_id' => (int)$primary['user_id'],
                'task_id' => (int)$primary['task_id'],
                'project_id' => $primary['project_id'],
                'status' => (string)$primary['status'],
                'segment_started_at' => $primary['segment_started_at'],
                'accumulated_seconds' => (int)$primary['accumulated_seconds'],
            ];
        }

        $loggedTodaySeconds = tasksession_user_logged_seconds_today($userId, $connect);
        $taskEntriesCount = tasksession_user_task_entries_count_today($userId, $connect);
        $shiftCard = [
            'available' => false,
            'elapsed_sec' => 0,
            'shift_total_sec' => 0,
            'remaining_sec' => 0,
            'checked_in' => false,
            'checked_out' => false,
            'check_in_ts' => 0,
            'shift_label' => '',
            'meta_note' => $lang['No shift assigned'] ?? 'No shift assigned',
            'meta_note_color' => '#888',
        ];
        if (!empty($dash_settings->module_attendance)) {
            $__att = __DIR__ . '/../includes/attendance/helpers.php';
            if (is_file($__att)) {
                require_once $__att;
            }
            if (function_exists('attendance_dashboard_shift_card_data')) {
                $shiftCard = attendance_dashboard_shift_card_data($userId, is_array($lang) ? $lang : [], $connect);
            }
        }
        $taskGraphTargetSeconds = (int) ($shiftCard['shift_total_sec'] ?? 0) > 0
            ? (int) $shiftCard['shift_total_sec']
            : tasksession_user_daily_target_seconds($userId);

        tt_json_exit([
            'status' => 'ok',
            'sessions' => $sessionsOut,
            'session' => $sessionOut,
            'elapsed_display_seconds' => $elapsed,
            'task_title' => $taskTitle,
            'project_title' => $projectTitle,
            'task_estimated_seconds' => $taskEstimatedSeconds,
            'can_mutate_timer' => can_mutate_timer(),
            'logged_today_seconds' => $loggedTodaySeconds,
            'task_entries_count' => $taskEntriesCount,
            'task_graph_target_seconds' => $taskGraphTargetSeconds,
            'shift_card' => $shiftCard,
        ]);
    }

    if ($action === 'schedules') {
        $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
        if ($taskId <= 0) {
            tt_json_exit(['status' => 'error', 'message' => tt_msg('invalid_task', 'Invalid task.')], 400);
        }
        if (!canAccessParentTaskTimer($taskId)) {
            tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
        }
        $task = Task::findById($taskId);
        if (!$task) {
            tt_json_exit(['status' => 'error', 'message' => tt_msg('task_not_found', 'Task not found.')], 404);
        }

        if (CalendarEvent::scheduleStorageReady()) {
            CalendarEvent::ensureScheduleSchema();
        }
        $rows = CalendarEvent::listPlacementsForTask($taskId);
        $schedules = [];
        $me = (int)$userId;
        foreach ($rows as $r) {
            $uid = (int)($r['user_id'] ?? 0);
            if ($accountStatus === 3 && $uid !== $me && !has_permission('timer_log_view_others')) {
                continue;
            }
            $fn = trim((string)($r['firstName'] ?? ''));
            $disp = $fn !== '' ? $fn : ('User #' . $uid);
            $avatarHtml = '';
            if (function_exists('getUserAvatarHtml')) {
                $alt = $fn !== '' ? $fn : $disp;
                $avatarHtml = getUserAvatarHtml($uid, $fn, '', 36, 36, 'tasksession-timer-avatar-media', $alt);
            }
            $schedules[] = [
                'id' => (int)($r['id'] ?? 0),
                'user_id' => $uid,
                'user_display' => $disp,
                'user_avatar_html' => $avatarHtml,
                'schedule_date' => (string)($r['schedule_date'] ?? ''),
                'planned_seconds' => isset($r['planned_seconds']) && $r['planned_seconds'] !== null && $r['planned_seconds'] !== ''
                    ? (int)$r['planned_seconds']
                    : null,
            ];
        }

        tt_json_exit([
            'status' => 'ok',
            'schedules' => $schedules,
        ]);
    }

    if ($action === 'logs') {
        $taskId = isset($_GET['task_id']) ? (int)$_GET['task_id'] : 0;
        if ($taskId <= 0) {
            tt_json_exit(['status' => 'error', 'message' => tt_msg('invalid_task', 'Invalid task.')], 400);
        }
        if (!canAccessParentTaskTimer($taskId)) {
            tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
        }

        $task = Task::findById($taskId);
        if (!$task) {
            tt_json_exit(['status' => 'error', 'message' => tt_msg('task_not_found', 'Task not found.')], 404);
        }

        $est = isset($task->estimated_time_seconds) && $task->estimated_time_seconds !== null && $task->estimated_time_seconds !== ''
            ? (int)$task->estimated_time_seconds
            : null;

        $sql = 'SELECT e.id, e.task_id, e.project_id, e.user_id, e.started_at, e.ended_at, e.duration_seconds, e.billable, e.notes, e.source, e.created_at,
                u.firstName
                FROM task_time_entries e
                LEFT JOIN users u ON u.id = e.user_id
                WHERE e.task_id = ?
                ORDER BY e.id DESC';
        $stmt = mysqli_prepare($connect, $sql);
        if (!$stmt) {
            tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
        }
        mysqli_stmt_bind_param($stmt, 'i', $taskId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $logs = [];
        $loggedSum = 0;
        while ($result && ($r = mysqli_fetch_assoc($result))) {
            $entryUid = (int)$r['user_id'];
            $visible = false;
            if ($accountStatus === 1) {
                $visible = true;
            } elseif ($accountStatus === 2) {
                // Client already passed canAccessParentTaskTimer — show all staff time on this task.
                $visible = true;
            } elseif ($accountStatus === 3) {
                $visible = staff_can_view_time_entry($task, $entryUid);
            }

            if (!$visible) {
                continue;
            }

            $dur = isset($r['duration_seconds']) ? (int)$r['duration_seconds'] : 0;
            $loggedSum += max(0, $dur);

            $fn = trim((string)($r['firstName'] ?? ''));
            $ln = '';
            $disp = $fn !== '' ? $fn : ('User #' . $entryUid);
            $avatarHtml = '';
            if (function_exists('getUserAvatarHtml')) {
                $alt = $fn !== '' ? $fn : $disp;
                $avatarHtml = getUserAvatarHtml($entryUid, $fn, $ln, 36, 36, 'tasksession-timer-avatar-media', $alt);
            }

            $logs[] = [
                'id' => (int)$r['id'],
                'user_id' => $entryUid,
                'user_display' => $disp,
                'user_avatar_html' => $avatarHtml,
                'started_at' => $r['started_at'],
                'ended_at' => $r['ended_at'],
                'duration_seconds' => $dur,
                'billable' => (int)$r['billable'],
                'notes' => $r['notes'],
                'source' => $r['source'],
            ];
        }
        mysqli_stmt_close($stmt);

        $remaining = null;
        $over = null;
        if ($est !== null && $est > 0) {
            if ($loggedSum <= $est) {
                $remaining = $est - $loggedSum;
            } else {
                $over = $loggedSum - $est;
            }
        }

        tt_json_exit([
            'status' => 'ok',
            'logs' => $logs,
            'summary' => [
                'estimated_seconds' => $est,
                'logged_seconds' => $loggedSum,
                'remaining_seconds' => $remaining,
                'over_seconds' => $over,
            ],
            'task_status' => (string)($task->status ?? 'todo'),
            'permissions' => [
                'can_edit_estimate' => can_edit_task_estimate(),
                'can_mutate_timer' => can_mutate_timer(),
                'can_mark_task_incomplete' => function_exists('has_permission') && has_permission('task_edit') && (string)($task->status ?? '') === 'done',
                'timer_log_view_others' => $accountStatus === 1 || ($accountStatus === 3 && function_exists('has_permission') && has_permission('timer_log_view_others')),
                'timer_log_edit_own' => $accountStatus === 1 || ($accountStatus === 3 && function_exists('has_permission') && has_permission('timer_log_edit_own')),
                'timer_log_delete_own' => $accountStatus === 1 || ($accountStatus === 3 && function_exists('has_permission') && has_permission('timer_log_delete_own')),
                'timer_log_show_manual' => $accountStatus === 1 || ($accountStatus === 3 && function_exists('has_permission') && has_permission('timer_log_show_manual')),
                'timer_log_edit' => $accountStatus === 1 || ($accountStatus === 3 && function_exists('has_permission') && has_permission('timer_log_edit')),
                'timer_log_delete' => $accountStatus === 1 || ($accountStatus === 3 && function_exists('has_permission') && has_permission('timer_log_delete')),
            ],
        ]);
    }

    tt_json_exit(['status' => 'error', 'message' => tt_msg('invalid_action', 'Invalid action.')], 400);
}

// ----- Mutations -----
csrf_require_for_request('json');

$raw = csrf_request_body_raw();
$input = [];
if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$action = isset($input['action']) ? (string)$input['action'] : '';

if ($action === 'estimated') {
    if (!can_edit_task_estimate()) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
    }
    $taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    if ($taskId <= 0 || !canAccessParentTaskTimer($taskId)) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
    }
    $task = Task::findById($taskId);
    if (!$task) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('task_not_found', 'Task not found.')], 404);
    }

    $secRaw = $input['estimated_time_seconds'] ?? null;
    if ($secRaw === null || $secRaw === '') {
        $task->estimated_time_seconds = null;
    } else {
        $n = (int)$secRaw;
        $task->estimated_time_seconds = $n > 0 ? $n : null;
    }
    $save = $task->save();
    if ($save !== true && $save !== 0) {
        tt_json_exit(['status' => 'error', 'message' => (string)$save], 500);
    }
    tt_json_exit(['status' => 'ok', 'estimated_time_seconds' => $task->estimated_time_seconds]);
}

if ($action === 'mark_task_incomplete') {
    if (!function_exists('has_permission') || !has_permission('task_edit')) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
    }
    $taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    if ($taskId <= 0 || !canAccessParentTaskTimer($taskId)) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
    }
    $t = Task::findById($taskId);
    if (!$t) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('task_not_found', 'Task not found.')], 404);
    }
    if ((string)($t->status ?? '') !== 'done') {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_task_not_done', 'Task is not marked as done.')], 400);
    }
    $t->status = 'todo';
    $t->completed_at = null;
    $save = $t->save();
    if ($save !== true && $save !== 0) {
        tt_json_exit(['status' => 'error', 'message' => (string)$save], 500);
    }
    tt_json_exit(['status' => 'ok', 'task_status' => (string)$t->status]);
}

if (!can_mutate_timer()) {
    tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
}

if ($action === 'start') {
    $taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    if ($taskId <= 0 || !canAccessParentTaskTimer($taskId)) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
    }
    $task = Task::findById($taskId);
    if (!$task) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('task_not_found', 'Task not found.')], 404);
    }

    $rows = tt_begin_and_lock_user_sessions($connect, $userId);
    if ($rows === false) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }

    $runningOther = tt_rows_running_other($rows, $taskId);
    if ($runningOther) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('pause_or_complete_current_timer', 'Pause your current timer before starting another.')], 409);
    }

    $rowThis = tt_rows_find_task($rows, $taskId);
    if ($rowThis) {
        $st = strtolower(trim((string)($rowThis['status'] ?? '')));
        if ($st === 'running') {
            mysqli_rollback($connect);
            tt_json_exit(['status' => 'error', 'message' => tt_msg('already_running_timer', 'Timer is already running.')], 409);
        }
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('use_resume_timer', 'Resume the paused timer instead.')], 409);
    }

    $projId = !empty($task->project_id) ? (int)$task->project_id : null;
    $segStart = date('Y-m-d H:i:s');
    if ($projId === null) {
        $sql = 'INSERT INTO task_timer_sessions (user_id, task_id, project_id, status, segment_started_at, accumulated_seconds) VALUES (?, ?, NULL, \'running\', ?, 0)';
        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, 'iis', $userId, $taskId, $segStart);
    } else {
        $sql = 'INSERT INTO task_timer_sessions (user_id, task_id, project_id, status, segment_started_at, accumulated_seconds) VALUES (?, ?, ?, \'running\', ?, 0)';
        $stmt = mysqli_prepare($connect, $sql);
        mysqli_stmt_bind_param($stmt, 'iiis', $userId, $taskId, $projId, $segStart);
    }
    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    mysqli_stmt_close($stmt);
    mysqli_commit($connect);
    tt_json_exit(['status' => 'ok']);
}

if ($action === 'pause') {
    $bodyTaskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    $rows = tt_begin_and_lock_user_sessions($connect, $userId);
    if ($rows === false) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }

    $target = null;
    if ($bodyTaskId > 0) {
        $r = tt_rows_find_task($rows, $bodyTaskId);
        if ($r && strtolower(trim((string)($r['status'] ?? ''))) === 'running') {
            $target = $r;
        }
    } else {
        $target = tt_rows_any_running($rows);
    }

    if (!$target) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_not_running', 'Timer is not running.')], 400);
    }

    $sessTaskId = (int)$target['task_id'];
    $acc = (int)$target['accumulated_seconds'];
    if (!empty($target['segment_started_at'])) {
        $start = strtotime((string)$target['segment_started_at']);
        if ($start) {
            $acc += max(0, time() - $start);
        }
    }
    $stmt = mysqli_prepare($connect, 'UPDATE task_timer_sessions SET status = \'paused\', segment_started_at = NULL, accumulated_seconds = ?, updated_at = NOW() WHERE user_id = ? AND task_id = ?');
    mysqli_stmt_bind_param($stmt, 'iii', $acc, $userId, $sessTaskId);
    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    mysqli_stmt_close($stmt);
    mysqli_commit($connect);
    tt_json_exit(['status' => 'ok']);
}

if ($action === 'resume') {
    $bodyTaskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    if ($bodyTaskId <= 0) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('invalid_task', 'Invalid task.')], 400);
    }
    $rows = tt_begin_and_lock_user_sessions($connect, $userId);
    if ($rows === false) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }

    $rowThis = tt_rows_find_task($rows, $bodyTaskId);
    if (!$rowThis || strtolower(trim((string)($rowThis['status'] ?? ''))) !== 'paused') {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_not_paused', 'Timer is not paused.')], 400);
    }

    $runningOther = tt_rows_running_other($rows, $bodyTaskId);
    if ($runningOther) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('pause_or_complete_current_timer', 'Pause your current timer before starting another.')], 409);
    }

    $segStart = date('Y-m-d H:i:s');
    $stmt = mysqli_prepare($connect, 'UPDATE task_timer_sessions SET status = \'running\', segment_started_at = ?, updated_at = NOW() WHERE user_id = ? AND task_id = ?');
    mysqli_stmt_bind_param($stmt, 'sii', $segStart, $userId, $bodyTaskId);
    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    mysqli_stmt_close($stmt);
    mysqli_commit($connect);
    tt_json_exit(['status' => 'ok']);
}

if ($action === 'complete') {
    $bodyTaskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    if ($bodyTaskId <= 0) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('invalid_task', 'Invalid task.')], 400);
    }
    $rows = tt_begin_and_lock_user_sessions($connect, $userId);
    if ($rows === false) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }

    $row = tt_rows_find_task($rows, $bodyTaskId);
    if (!$row) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('no_active_timer', 'No active timer.')], 400);
    }

    $sessTaskId = (int)$row['task_id'];
    $task = Task::findById($sessTaskId);
    if (!$task) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('task_not_found', 'Task not found.')], 404);
    }

    $duration = timer_elapsed_seconds($row);
    if (array_key_exists('duration_seconds', $input) && $input['duration_seconds'] !== null && $input['duration_seconds'] !== '') {
        $override = (int)$input['duration_seconds'];
        if ($override > 0 && $override <= 604800) {
            $duration = $override;
        }
    }

    $logDate = isset($input['log_date']) ? trim((string)$input['log_date']) : '';
    if ($logDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
        $logDate = date('Y-m-d');
    }
    $endTs = strtotime($logDate . ' 18:00:00');
    if ($endTs === false) {
        $endTs = time();
    }
    $ended = date('Y-m-d H:i:s', $endTs);
    $started = date('Y-m-d H:i:s', max(0, $endTs - max(1, $duration)));

    $billable = isset($input['billable']) ? ((int)$input['billable'] ? 1 : 0) : 1;
    $notes = isset($input['notes']) ? trim((string)$input['notes']) : '';

    $projOut = !empty($task->project_id) ? (int)$task->project_id : null;

    if ($projOut === null) {
        $stmtIns = mysqli_prepare(
            $connect,
            'INSERT INTO task_time_entries (task_id, project_id, user_id, started_at, ended_at, duration_seconds, billable, notes, source) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, \'timer\')'
        );
        mysqli_stmt_bind_param($stmtIns, 'iissiis', $sessTaskId, $userId, $started, $ended, $duration, $billable, $notes);
    } else {
        $stmtIns = mysqli_prepare(
            $connect,
            'INSERT INTO task_time_entries (task_id, project_id, user_id, started_at, ended_at, duration_seconds, billable, notes, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'timer\')'
        );
        mysqli_stmt_bind_param($stmtIns, 'iiissiis', $sessTaskId, $projOut, $userId, $started, $ended, $duration, $billable, $notes);
    }
    if (!$stmtIns || !mysqli_stmt_execute($stmtIns)) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    mysqli_stmt_close($stmtIns);

    $del = mysqli_prepare($connect, 'DELETE FROM task_timer_sessions WHERE user_id = ? AND task_id = ?');
    mysqli_stmt_bind_param($del, 'ii', $userId, $sessTaskId);
    if (!$del || !mysqli_stmt_execute($del)) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    mysqli_stmt_close($del);

    $scheduleId = isset($input['schedule_id']) ? (int) $input['schedule_id'] : 0;
    $scheduleRemoved = false;
    if ($scheduleId > 0) {
        $scheduleRemoved = CalendarEvent::deleteSchedulePlacementById($scheduleId, $sessTaskId, $userId);
    }

    mysqli_commit($connect);

    $taskMarkedDone = false;
    $markDone = !empty($input['mark_task_done']);
    if ($markDone && tt_can_mark_task_done_with_timer($task)) {
        $tDone = Task::findById($sessTaskId);
        if ($tDone) {
            $tDone->status = 'done';
            $tDone->completed_at = date('Y-m-d H:i:s');
            if ($tDone->save()) {
                $taskMarkedDone = true;
            }
        }
    }
    $tOut = Task::findById($sessTaskId);

    tt_json_exit([
        'status' => 'ok',
        'duration_seconds' => $duration,
        'task_marked_done' => $taskMarkedDone,
        'task_status' => $tOut ? (string)($tOut->status ?? 'todo') : 'todo',
        'schedule_removed' => $scheduleRemoved,
    ]);
}

/** Manual time entry without an active timer session (e.g. "+ Log more time"). */
if ($action === 'manual_log') {
    if ($accountStatus === 3 && (!function_exists('has_permission') || !has_permission('timer_log_show_manual'))) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
    }
    $bodyTaskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    if ($bodyTaskId <= 0) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('invalid_task', 'Invalid task.')], 400);
    }
    if (!canAccessParentTaskTimer($bodyTaskId)) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
    }
    $task = Task::findById($bodyTaskId);
    if (!$task) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('task_not_found', 'Task not found.')], 404);
    }

    $duration = isset($input['duration_seconds']) ? (int)$input['duration_seconds'] : 0;
    if ($duration <= 0 || $duration > 604800) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_invalid_duration', 'Invalid duration.')], 400);
    }

    $logDate = isset($input['log_date']) ? trim((string)$input['log_date']) : '';
    if ($logDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
        $logDate = date('Y-m-d');
    }
    $endTs = strtotime($logDate . ' 18:00:00');
    if ($endTs === false) {
        $endTs = time();
    }
    $ended = date('Y-m-d H:i:s', $endTs);
    $started = date('Y-m-d H:i:s', max(0, $endTs - max(1, $duration)));

    $logUserId = isset($input['log_user_id']) ? (int)$input['log_user_id'] : 0;
    if ($logUserId <= 0) {
        $logUserId = $userId;
    }

    $assignedRaw = isset($task->assigned_to) ? (string)$task->assigned_to : '';
    $assignedList = array_filter(array_map('trim', explode(',', $assignedRaw)));
    if ($assignedList !== []) {
        if (!in_array((string)$logUserId, $assignedList, true)) {
            tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_schedule_assignee_not_on_task', 'Selected user is not assigned to this task.')], 400);
        }
    } elseif ($logUserId !== $userId) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_log_self_only_unassigned', 'This task has no assignees; time can only be logged for yourself.')], 400);
    }

    if ($logUserId !== $userId && $accountStatus !== 1 && !has_permission('task_edit')) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
    }

    $billable = isset($input['billable']) ? ((int)$input['billable'] ? 1 : 0) : 1;
    $notes = isset($input['notes']) ? trim((string)$input['notes']) : '';

    $projOut = !empty($task->project_id) ? (int)$task->project_id : null;

    if ($projOut === null) {
        $stmtIns = mysqli_prepare(
            $connect,
            'INSERT INTO task_time_entries (task_id, project_id, user_id, started_at, ended_at, duration_seconds, billable, notes, source) VALUES (?, NULL, ?, ?, ?, ?, ?, ?, \'manual\')'
        );
        mysqli_stmt_bind_param($stmtIns, 'iissiis', $bodyTaskId, $logUserId, $started, $ended, $duration, $billable, $notes);
    } else {
        $stmtIns = mysqli_prepare(
            $connect,
            'INSERT INTO task_time_entries (task_id, project_id, user_id, started_at, ended_at, duration_seconds, billable, notes, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'manual\')'
        );
        mysqli_stmt_bind_param($stmtIns, 'iiissiis', $bodyTaskId, $projOut, $logUserId, $started, $ended, $duration, $billable, $notes);
    }
    if (!$stmtIns || !mysqli_stmt_execute($stmtIns)) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    mysqli_stmt_close($stmtIns);

    $scheduleId = isset($input['schedule_id']) ? (int) $input['schedule_id'] : 0;
    $scheduleRemoved = false;
    $sessionEnded = false;
    if ($logUserId === $userId) {
        $delSess = mysqli_prepare($connect, 'DELETE FROM task_timer_sessions WHERE user_id = ? AND task_id = ?');
        if ($delSess) {
            mysqli_stmt_bind_param($delSess, 'ii', $userId, $bodyTaskId);
            if (mysqli_stmt_execute($delSess)) {
                $sessionEnded = mysqli_stmt_affected_rows($delSess) > 0;
            }
            mysqli_stmt_close($delSess);
        }
        if ($scheduleId > 0) {
            $scheduleRemoved = CalendarEvent::deleteSchedulePlacementById($scheduleId, $bodyTaskId, $userId);
        }
    }

    $taskMarkedDone = false;
    $markDone = !empty($input['mark_task_done']);
    if ($markDone && tt_can_mark_task_done_with_timer($task)) {
        $tDone = Task::findById($bodyTaskId);
        if ($tDone) {
            $tDone->status = 'done';
            $tDone->completed_at = date('Y-m-d H:i:s');
            if ($tDone->save()) {
                $taskMarkedDone = true;
            }
        }
    }
    $tOut = Task::findById($bodyTaskId);

    tt_json_exit([
        'status' => 'ok',
        'duration_seconds' => $duration,
        'task_marked_done' => $taskMarkedDone,
        'task_status' => $tOut ? (string)($tOut->status ?? 'todo') : 'todo',
        'schedule_removed' => $scheduleRemoved,
        'session_ended' => $sessionEnded,
    ]);
}

if ($action === 'delete_time_log') {
    $tid = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    $entryId = isset($input['entry_id']) ? (int)$input['entry_id'] : 0;
    if ($tid <= 0 || $entryId <= 0) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('invalid_task', 'Invalid task.')], 400);
    }
    $task = Task::findById($tid);
    if (!$task) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('task_not_found', 'Task not found.')], 404);
    }
    $row = tt_fetch_time_entry($connect, $entryId, $tid);
    if (!$row) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_log_not_found', 'Time entry not found.')], 404);
    }
    tt_assert_can_mutate_time_entry($task, $row, $accountStatus, 'delete');
    $stmt = mysqli_prepare($connect, 'DELETE FROM task_time_entries WHERE id = ? AND task_id = ? LIMIT 1');
    if (!$stmt) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    mysqli_stmt_bind_param($stmt, 'ii', $entryId, $tid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    mysqli_stmt_close($stmt);
    tt_json_exit(['status' => 'ok']);
}

if ($action === 'update_time_log') {
    $tid = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    $entryId = isset($input['entry_id']) ? (int)$input['entry_id'] : 0;
    if ($tid <= 0 || $entryId <= 0) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('invalid_task', 'Invalid task.')], 400);
    }
    $task = Task::findById($tid);
    if (!$task) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('task_not_found', 'Task not found.')], 404);
    }
    $row = tt_fetch_time_entry($connect, $entryId, $tid);
    if (!$row) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_log_not_found', 'Time entry not found.')], 404);
    }
    tt_assert_can_mutate_time_entry($task, $row, $accountStatus, 'update');

    $duration = isset($input['duration_seconds']) ? (int)$input['duration_seconds'] : 0;
    if ($duration <= 0 || $duration > 604800) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_invalid_duration', 'Invalid duration.')], 400);
    }

    $logDate = isset($input['log_date']) ? trim((string)$input['log_date']) : '';
    if ($logDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $logDate)) {
        $logDate = date('Y-m-d');
    }
    $endTs = strtotime($logDate . ' 18:00:00');
    if ($endTs === false) {
        $endTs = time();
    }
    $ended = date('Y-m-d H:i:s', $endTs);
    $started = date('Y-m-d H:i:s', max(0, $endTs - max(1, $duration)));

    $billable = isset($input['billable']) ? ((int)$input['billable'] ? 1 : 0) : 1;
    $notes = isset($input['notes']) ? trim((string)$input['notes']) : '';

    $stmt = mysqli_prepare(
        $connect,
        'UPDATE task_time_entries SET started_at = ?, ended_at = ?, duration_seconds = ?, billable = ?, notes = ? WHERE id = ? AND task_id = ? LIMIT 1'
    );
    if (!$stmt) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    mysqli_stmt_bind_param($stmt, 'ssiisii', $started, $ended, $duration, $billable, $notes, $entryId, $tid);
    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    mysqli_stmt_close($stmt);
    tt_json_exit(['status' => 'ok', 'duration_seconds' => $duration]);
}

/** Discard active session without logging time (abandon timer). */
if ($action === 'discard') {
    $taskIdOpt = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    if ($taskIdOpt <= 0) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('invalid_task', 'Invalid task.')], 400);
    }
    $rows = tt_begin_and_lock_user_sessions($connect, $userId);
    if ($rows === false) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    $row = tt_rows_find_task($rows, $taskIdOpt);
    if (!$row) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('no_active_timer', 'No active timer.')], 400);
    }

    $del = mysqli_prepare($connect, 'DELETE FROM task_timer_sessions WHERE user_id = ? AND task_id = ?');
    mysqli_stmt_bind_param($del, 'ii', $userId, $taskIdOpt);
    if (!$del || !mysqli_stmt_execute($del)) {
        mysqli_rollback($connect);
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_db_error', 'Database error.')], 500);
    }
    mysqli_stmt_close($del);
    mysqli_commit($connect);
    tt_json_exit(['status' => 'ok']);
}

/** Place task on a task assignee's calendar day (calendar_task_schedule + optional calendar_events). */
if ($action === 'schedule_placement') {
    $taskId = isset($input['task_id']) ? (int)$input['task_id'] : 0;
    $scheduleUserId = isset($input['schedule_user_id']) ? (int)$input['schedule_user_id'] : 0;
    $scheduleDate = isset($input['schedule_date']) ? trim((string)$input['schedule_date']) : '';
    if ($taskId <= 0 || $scheduleUserId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $scheduleDate)) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_schedule_invalid', 'Please choose a valid date and assignee.')], 400);
    }
    if (!canAccessParentTaskTimer($taskId)) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('access_denied', 'Access denied.')], 403);
    }
    $task = Task::findById($taskId);
    if (!$task) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('task_not_found', 'Task not found.')], 404);
    }
    $assignedRaw = isset($task->assigned_to) ? (string)$task->assigned_to : '';
    $assignedList = array_filter(array_map('trim', explode(',', $assignedRaw)));
    if (!in_array((string)$scheduleUserId, $assignedList, true)) {
        tt_json_exit(['status' => 'error', 'message' => tt_msg('timer_schedule_assignee_not_on_task', 'Selected user is not assigned to this task.')], 400);
    }
    $plannedSeconds = isset($input['planned_seconds']) ? (int)$input['planned_seconds'] : null;
    if ($plannedSeconds !== null && ($plannedSeconds <= 0 || $plannedSeconds > 604800)) {
        $plannedSeconds = null;
    }

    if (!CalendarEvent::scheduleStorageReady()) {
        tt_json_exit([
            'status' => 'error',
            'message' => tt_msg(
                'timer_calendar_unavailable',
                'Calendar scheduling is not available on this installation. Run the database update to install calendar_task_schedule.'
            ),
        ], 503);
    }
    CalendarEvent::ensureScheduleSchema();

    $ok = CalendarEvent::insertTaskScheduleRow($taskId, $scheduleUserId, $scheduleDate, $plannedSeconds, false, 0);
    if (!$ok) {
        $dbHint = ($connect instanceof mysqli && $connect->error) ? trim((string) $connect->error) : '';
        $msg = tt_msg('timer_schedule_save_failed', 'Could not save scheduled work. Please try again or run database update (migration 3.30).');
        if ($dbHint !== '' && defined('DEBUG_MODE') && DEBUG_MODE) {
            $msg .= ' (' . $dbHint . ')';
        }
        tt_json_exit(['status' => 'error', 'message' => $msg], 500);
    }
    if (CalendarEvent::moduleReady()) {
        CalendarEvent::upsertTaskSyncEvent($taskId, $scheduleUserId, $scheduleDate);
    }
    tt_json_exit(['status' => 'ok']);
}

tt_json_exit(['status' => 'error', 'message' => tt_msg('invalid_action', 'Invalid action.')], 400);
