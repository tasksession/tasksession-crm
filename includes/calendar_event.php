<?php
/*
 ================================================================================
   Task Session – Project Management System
   File    : includes/calendar_event.php
   Purpose : Calendar event and waiting list data helpers
 ================================================================================
*/

class CalendarEvent
{
    private static function hasTable($tableName)
    {
        global $connect;
        if (!($connect instanceof mysqli)) {
            return false;
        }
        $safeName = $connect->real_escape_string($tableName);
        $res = $connect->query("SHOW TABLES LIKE '{$safeName}'");
        return ($res && $res->num_rows > 0);
    }

    private static function hasColumn($tableName, $columnName)
    {
        global $connect;
        if (!self::hasTable($tableName)) {
            return false;
        }
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
        $safeCol = $connect->real_escape_string($columnName);
        $res = $connect->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeCol}'");
        return ($res && $res->num_rows > 0);
    }

    /**
     * Ensure calendar_task_schedule exists and has planned_seconds (upgrade from pre-3.30 installs).
     */
    public static function ensureScheduleSchema()
    {
        global $connect;
        if (!($connect instanceof mysqli) || !self::hasTable('calendar_task_schedule')) {
            return false;
        }
        if (self::hasColumn('calendar_task_schedule', 'planned_seconds')) {
            return true;
        }
        $sql = "ALTER TABLE `calendar_task_schedule`
                ADD COLUMN `planned_seconds` INT UNSIGNED NULL DEFAULT NULL
                COMMENT 'Planner-entered seconds for this assignee schedule row'
                AFTER `schedule_date`";
        return (bool) $connect->query($sql);
    }

    public static function scheduleStorageReady()
    {
        return self::hasTable('calendar_task_schedule');
    }

    public static function moduleReady()
    {
        return self::hasTable('calendar_events') && self::hasTable('calendar_task_schedule');
    }

    public static function listEventsByMonth($userId, $monthStart, $monthEnd, $onlyMine = true)
    {
        global $connect;
        $items = [];
        if (!self::hasTable('calendar_events')) {
            return $items;
        }

        if ($onlyMine) {
            $sql = "SELECT *
                    FROM calendar_events
                    WHERE user_id = ?
                      AND (task_id IS NULL OR task_id = 0)
                      AND is_waiting_list = 0
                      AND (
                        (event_date BETWEEN ? AND ?)
                        OR (DATE(start_datetime) BETWEEN ? AND ?)
                      )
                    ORDER BY COALESCE(start_datetime, CONCAT(event_date, ' 00:00:00')) ASC";
        } else {
            $sql = "SELECT *
                    FROM calendar_events
                    WHERE (task_id IS NULL OR task_id = 0)
                      AND is_waiting_list = 0
                      AND (
                        (event_date BETWEEN ? AND ?)
                        OR (DATE(start_datetime) BETWEEN ? AND ?)
                      )
                    ORDER BY COALESCE(start_datetime, CONCAT(event_date, ' 00:00:00')) ASC";
        }

        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return $items;
        }
        if ($onlyMine) {
            $stmt->bind_param("issss", $userId, $monthStart, $monthEnd, $monthStart, $monthEnd);
        } else {
            $stmt->bind_param("ssss", $monthStart, $monthEnd, $monthStart, $monthEnd);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        return $items;
    }

    public static function listWaitingItems($userId)
    {
        global $connect;
        $waiting = [
            'tasks' => [],
            'events' => []
        ];

        if (self::hasTable('calendar_task_schedule')) {
            $sql = "SELECT s.task_id, s.waiting_position, t.title, t.project_id, t.status
                    FROM calendar_task_schedule s
                    INNER JOIN tasks t ON t.id = s.task_id
                    WHERE s.user_id = ? AND s.is_waiting_list = 1
                    ORDER BY s.waiting_position ASC, s.updated_at DESC";
            $stmt = $connect->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $waiting['tasks'][] = $row;
                }
                $stmt->close();
            }
        }

        if (self::hasTable('calendar_events')) {
            $sql = "SELECT id, title, event_date, start_datetime, waiting_position
                    FROM calendar_events
                    WHERE user_id = ? AND is_waiting_list = 1
                    ORDER BY waiting_position ASC, updated_at DESC";
            $stmt = $connect->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $waiting['events'][] = $row;
                }
                $stmt->close();
            }
        }

        return $waiting;
    }

    public static function listScheduledTasksByMonth($userId, $monthStart, $monthEnd, $scope = 'my')
    {
        global $connect;
        $tasks = [];
        if (!self::hasTable('calendar_task_schedule')) {
            return $tasks;
        }

        if ($scope === true || $scope === 1 || $scope === '1') {
            $scope = 'my';
        } elseif ($scope === false || $scope === 0 || $scope === '0') {
            $scope = 'all';
        } elseif (!in_array($scope, ['my', 'all', 'created'], true)) {
            $scope = 'my';
        }

        $selectFields = 's.task_id, s.schedule_date, s.waiting_position, s.updated_at AS schedule_updated_at,
                           t.title, t.status, t.project_id, t.assigned_to, t.creator_id, t.user_id';

        if ($scope === 'my') {
            $sql = "SELECT $selectFields
                    FROM calendar_task_schedule s
                    INNER JOIN tasks t ON t.id = s.task_id
                    WHERE s.user_id = ?
                      AND s.is_waiting_list = 0
                      AND s.schedule_date BETWEEN ? AND ?
                      AND (FIND_IN_SET(?, t.assigned_to) > 0 OR t.user_id = ?)
                    ORDER BY s.schedule_date ASC, s.waiting_position ASC, s.updated_at DESC";
        } elseif ($scope === 'created') {
            $sql = "SELECT $selectFields
                    FROM calendar_task_schedule s
                    INNER JOIN (
                        SELECT task_id, MAX(id) AS latest_id
                        FROM calendar_task_schedule
                        WHERE is_waiting_list = 0
                          AND schedule_date BETWEEN ? AND ?
                        GROUP BY task_id
                    ) latest ON latest.latest_id = s.id
                    INNER JOIN tasks t ON t.id = s.task_id
                    WHERE (t.creator_id = ? OR t.user_id = ?)
                    ORDER BY s.schedule_date ASC, s.waiting_position ASC, s.updated_at DESC";
        } else {
            // Global All Schedule: use the latest placement per task across all users.
            $sql = "SELECT $selectFields
                    FROM calendar_task_schedule s
                    INNER JOIN (
                        SELECT task_id, MAX(id) AS latest_id
                        FROM calendar_task_schedule
                        WHERE is_waiting_list = 0
                          AND schedule_date BETWEEN ? AND ?
                        GROUP BY task_id
                    ) latest ON latest.latest_id = s.id
                    INNER JOIN tasks t ON t.id = s.task_id
                    ORDER BY s.schedule_date ASC, s.waiting_position ASC, s.updated_at DESC";
        }

        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return $tasks;
        }

        if ($scope === 'my') {
            $stmt->bind_param("issii", $userId, $monthStart, $monthEnd, $userId, $userId);
        } elseif ($scope === 'created') {
            $stmt->bind_param("ssii", $monthStart, $monthEnd, $userId, $userId);
        } else {
            $stmt->bind_param("ss", $monthStart, $monthEnd);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $tasks[] = $row;
        }
        $stmt->close();
        return $tasks;
    }

    public static function seedWaitingTasksFromUnscheduled($userId, $maxItems = 80)
    {
        global $connect;
        if (!self::hasTable('calendar_task_schedule')) {
            return;
        }

        $sql = "SELECT t.id
                FROM tasks t
                LEFT JOIN calendar_task_schedule s
                  ON s.task_id = t.id AND s.user_id = ?
                WHERE s.id IS NULL
                  AND (
                    FIND_IN_SET(?, t.assigned_to) > 0
                    OR t.user_id = ?
                    OR t.creator_id = ?
                  )
                  /* MySQL 8+ strict: do not compare DATE to '0000-00-00' (mysqli_sql_exception on prepare). Treat NULL or legacy zero dates as “unset”. */
                  AND (t.start_date IS NULL OR t.start_date < '1970-01-01')
                  AND (t.due_date IS NULL OR t.due_date < '1970-01-01')
                ORDER BY t.created_at DESC
                LIMIT ?";

        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return;
        }
        $stmt->bind_param("iiiii", $userId, $userId, $userId, $userId, $maxItems);
        $stmt->execute();
        $res = $stmt->get_result();
        $position = self::nextWaitingPosition($userId, 'task');
        while ($row = $res->fetch_assoc()) {
            self::saveTaskPlacement((int)$row['id'], $userId, null, true, $position);
            $position++;
        }
        $stmt->close();
    }

    public static function nextWaitingPosition($userId, $type = 'task')
    {
        global $connect;
        $max = 0;
        if ($type === 'event' && self::hasTable('calendar_events')) {
            $stmt = $connect->prepare("SELECT COALESCE(MAX(waiting_position), 0) AS max_pos FROM calendar_events WHERE user_id = ? AND is_waiting_list = 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $max = (int)$row['max_pos'];
                $stmt->close();
            }
        } elseif (self::hasTable('calendar_task_schedule')) {
            $stmt = $connect->prepare("SELECT COALESCE(MAX(waiting_position), 0) AS max_pos FROM calendar_task_schedule WHERE user_id = ? AND is_waiting_list = 1");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                $max = (int)$row['max_pos'];
                $stmt->close();
            }
        }
        return $max + 1;
    }

    /**
     * Insert a new scheduled-work row (Schedule more work — same user may have many rows).
     */
    public static function insertTaskScheduleRow($taskId, $userId, $scheduleDate = null, $plannedSeconds = null, $isWaiting = false, $waitingPosition = 0)
    {
        global $connect;
        if (!self::scheduleStorageReady()) {
            return false;
        }
        self::ensureScheduleSchema();

        $plannedVal = null;
        $includePlanned = false;
        if (self::hasColumn('calendar_task_schedule', 'planned_seconds') && $plannedSeconds !== null && $plannedSeconds !== '') {
            $ps = (int) $plannedSeconds;
            if ($ps > 0 && $ps <= 604800) {
                $plannedVal = $ps;
                $includePlanned = true;
            }
        }

        $wait = $isWaiting ? 1 : 0;
        if ($includePlanned) {
            $sql = "INSERT INTO calendar_task_schedule (task_id, user_id, schedule_date, planned_seconds, is_waiting_list, waiting_position, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        } else {
            $sql = "INSERT INTO calendar_task_schedule (task_id, user_id, schedule_date, is_waiting_list, waiting_position, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        }
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return false;
        }
        if ($includePlanned) {
            $stmt->bind_param('iisiii', $taskId, $userId, $scheduleDate, $plannedVal, $wait, $waitingPosition);
        } else {
            $stmt->bind_param('iisii', $taskId, $userId, $scheduleDate, $wait, $waitingPosition);
        }
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    /**
     * Update the first placement row for task+user (calendar drag / waiting list), or insert if none.
     */
    public static function saveTaskPlacement($taskId, $userId, $scheduleDate = null, $isWaiting = false, $waitingPosition = 0, $plannedSeconds = null)
    {
        global $connect;
        if (!self::scheduleStorageReady()) {
            return false;
        }
        self::ensureScheduleSchema();

        $tid = (int) $taskId;
        $uid = (int) $userId;
        $wait = $isWaiting ? 1 : 0;
        $existingId = 0;
        $find = $connect->prepare(
            'SELECT id FROM calendar_task_schedule WHERE task_id = ? AND user_id = ? AND is_waiting_list = ? ORDER BY id ASC LIMIT 1'
        );
        if ($find) {
            $find->bind_param('iii', $tid, $uid, $wait);
            $find->execute();
            $res = $find->get_result();
            if ($res && ($row = $res->fetch_assoc())) {
                $existingId = (int) ($row['id'] ?? 0);
            }
            $find->close();
        }

        $plannedVal = null;
        $includePlanned = false;
        if (self::hasColumn('calendar_task_schedule', 'planned_seconds') && $plannedSeconds !== null && $plannedSeconds !== '') {
            $ps = (int) $plannedSeconds;
            if ($ps > 0 && $ps <= 604800) {
                $plannedVal = $ps;
                $includePlanned = true;
            }
        }

        if ($existingId > 0) {
            if ($includePlanned) {
                $sql = 'UPDATE calendar_task_schedule SET schedule_date = ?, planned_seconds = ?, is_waiting_list = ?, waiting_position = ?, updated_at = NOW() WHERE id = ?';
            } else {
                $sql = 'UPDATE calendar_task_schedule SET schedule_date = ?, is_waiting_list = ?, waiting_position = ?, updated_at = NOW() WHERE id = ?';
            }
            $stmt = $connect->prepare($sql);
            if (!$stmt) {
                return false;
            }
            if ($includePlanned) {
                $stmt->bind_param('siiii', $scheduleDate, $plannedVal, $wait, $waitingPosition, $existingId);
            } else {
                $stmt->bind_param('siii', $scheduleDate, $wait, $waitingPosition, $existingId);
            }
            $ok = $stmt->execute();
            $stmt->close();

            return $ok;
        }

        return self::insertTaskScheduleRow($tid, $uid, $scheduleDate, $plannedSeconds, $isWaiting, $waitingPosition);
    }

    private static function userHasDatedScheduleRow($taskId, $userId)
    {
        global $connect;
        $tid = (int) $taskId;
        $uid = (int) $userId;
        if ($tid <= 0 || $uid <= 0) {
            return false;
        }
        $stmt = $connect->prepare(
            "SELECT id FROM calendar_task_schedule
             WHERE task_id = ? AND user_id = ? AND is_waiting_list = 0
               AND schedule_date IS NOT NULL AND schedule_date > '1970-01-01'
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $tid, $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $has = ($res && $res->fetch_assoc());
        $stmt->close();

        return (bool) $has;
    }

    /**
     * On task create (or new assignees): one scheduled-work row per assignee when task has an estimate.
     *
     * @param array{only_user_ids?: list<int>, skip_if_user_has_row?: bool} $options
     */
    public static function seedAssigneeSchedulesFromTask($taskId, array $options = [])
    {
        if (!self::scheduleStorageReady()) {
            return 0;
        }
        self::ensureScheduleSchema();

        $tid = (int) $taskId;
        if ($tid <= 0) {
            return 0;
        }

        if (!class_exists('Task')) {
            require_once __DIR__ . '/task.php';
        }
        $task = Task::findById($tid);
        if (!$task) {
            return 0;
        }

        $planned = isset($task->estimated_time_seconds) && $task->estimated_time_seconds !== null && $task->estimated_time_seconds !== ''
            ? (int) $task->estimated_time_seconds
            : 0;
        if ($planned <= 0) {
            return 0;
        }

        $date = date('Y-m-d');
        $startRaw = isset($task->start_date) ? trim((string) $task->start_date) : '';
        if ($startRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startRaw)) {
            $date = $startRaw;
        }

        $onlyUserIds = null;
        if (!empty($options['only_user_ids']) && is_array($options['only_user_ids'])) {
            $onlyUserIds = [];
            foreach ($options['only_user_ids'] as $ou) {
                $ou = (int) $ou;
                if ($ou > 0) {
                    $onlyUserIds[] = $ou;
                }
            }
        }

        $skipIfHas = !empty($options['skip_if_user_has_row']);

        $assignees = [];
        $raw = isset($task->assigned_to) ? (string) $task->assigned_to : '';
        foreach (array_filter(array_map('trim', explode(',', $raw))) as $piece) {
            if (ctype_digit($piece) && (int) $piece > 0) {
                $assignees[] = (int) $piece;
            }
        }
        $assignees = array_values(array_unique($assignees));

        $created = 0;
        foreach ($assignees as $uid) {
            if ($onlyUserIds !== null && !in_array($uid, $onlyUserIds, true)) {
                continue;
            }
            if ($skipIfHas && self::userHasDatedScheduleRow($tid, $uid)) {
                continue;
            }
            if (!self::insertTaskScheduleRow($tid, $uid, $date, $planned, false, 0)) {
                continue;
            }
            $created++;
            if (self::moduleReady()) {
                self::upsertTaskSyncEvent($tid, $uid, $date);
            }
        }

        return $created;
    }

    /**
     * All dated schedule rows for one task (multiple rows per assignee allowed).
     *
     * @return list<array<string,mixed>>
     */
    public static function listPlacementsForTask($taskId)
    {
        global $connect;
        $out = [];
        $tid = (int) $taskId;
        if ($tid <= 0 || !self::scheduleStorageReady()) {
            return $out;
        }
        self::ensureScheduleSchema();

        $plannedSel = self::hasColumn('calendar_task_schedule', 'planned_seconds')
            ? 's.planned_seconds'
            : 'NULL AS planned_seconds';

        $sql = "SELECT s.id, s.task_id, s.user_id, s.schedule_date, {$plannedSel}, s.is_waiting_list,
                       u.firstName
                FROM calendar_task_schedule s
                INNER JOIN users u ON u.id = s.user_id
                WHERE s.task_id = ?
                  AND s.is_waiting_list = 0
                  AND s.schedule_date IS NOT NULL
                  AND s.schedule_date > '1970-01-01'
                ORDER BY s.schedule_date ASC, s.id ASC, u.firstName ASC, s.user_id ASC";
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return $out;
        }
        $stmt->bind_param('i', $tid);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $out[] = $row;
        }
        $stmt->close();

        return $out;
    }

    /**
     * Remove one dated schedule row (e.g. after timer complete). Must match task + assignee.
     */
    public static function deleteSchedulePlacementById($scheduleId, $taskId, $userId)
    {
        global $connect;
        $sid = (int) $scheduleId;
        $tid = (int) $taskId;
        $uid = (int) $userId;
        if ($sid <= 0 || $tid <= 0 || $uid <= 0 || !self::scheduleStorageReady()) {
            return false;
        }
        $stmt = $connect->prepare(
            'DELETE FROM calendar_task_schedule
             WHERE id = ? AND task_id = ? AND user_id = ? AND is_waiting_list = 0'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iii', $sid, $tid, $uid);
        $ok = $stmt->execute();
        $affected = (int) $stmt->affected_rows;
        $stmt->close();

        return $ok && $affected > 0;
    }

    public static function upsertTaskSyncEvent($taskId, $userId, $preferredDate = null)
    {
        global $connect;
        if (!self::hasTable('calendar_events')) {
            return 0;
        }

        $tid = (int)$taskId;
        $uid = (int)$userId;
        if ($tid <= 0 || $uid <= 0) {
            return 0;
        }

        $taskStmt = $connect->prepare("SELECT id, title, description, start_date, due_date FROM tasks WHERE id = ? LIMIT 1");
        if (!$taskStmt) {
            return 0;
        }
        $taskStmt->bind_param("i", $tid);
        $taskStmt->execute();
        $taskRes = $taskStmt->get_result();
        $taskRow = $taskRes->fetch_assoc();
        $taskStmt->close();

        if (!$taskRow) {
            return 0;
        }

        $eventDate = null;
        $candidateDate = trim((string)$preferredDate);
        if ($candidateDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidateDate)) {
            $eventDate = $candidateDate;
        } elseif (!empty($taskRow['due_date']) && $taskRow['due_date'] !== '0000-00-00') {
            $eventDate = (string)$taskRow['due_date'];
        } elseif (!empty($taskRow['start_date']) && $taskRow['start_date'] !== '0000-00-00') {
            $eventDate = (string)$taskRow['start_date'];
        }

        if ($eventDate === null) {
            return 0;
        }

        $title = trim((string)($taskRow['title'] ?? ''));
        if ($title === '') {
            $title = 'Task';
        }
        $description = trim((string)($taskRow['description'] ?? ''));

        $findStmt = $connect->prepare("SELECT id FROM calendar_events WHERE task_id = ? AND user_id = ? LIMIT 1");
        if (!$findStmt) {
            return 0;
        }
        $findStmt->bind_param("ii", $tid, $uid);
        $findStmt->execute();
        $findRes = $findStmt->get_result();
        $existing = $findRes->fetch_assoc();
        $findStmt->close();

        if ($existing) {
            $eventId = (int)$existing['id'];
            $updateStmt = $connect->prepare("UPDATE calendar_events
                    SET title = ?, description = ?, event_date = ?, start_datetime = NULL, end_datetime = NULL,
                        is_waiting_list = 0, waiting_position = 0, source_type = 'local', sync_state = 'pending', updated_at = NOW()
                    WHERE id = ? AND user_id = ?");
            if (!$updateStmt) {
                return 0;
            }
            $updateStmt->bind_param("sssii", $title, $description, $eventDate, $eventId, $uid);
            $ok = $updateStmt->execute();
            $updateStmt->close();
            return $ok ? $eventId : 0;
        }

        return self::createEvent([
            'task_id' => $tid,
            'user_id' => $uid,
            'title' => $title,
            'description' => $description,
            'event_date' => $eventDate,
            'start_datetime' => null,
            'end_datetime' => null,
            'location_label' => '',
            'team_label' => '',
            'participants_json' => json_encode([]),
            'is_waiting_list' => 0,
            'waiting_position' => 0
        ]);
    }

    public static function createEvent($payload)
    {
        global $connect;
        if (!self::hasTable('calendar_events')) {
            return 0;
        }

        $sql = "INSERT INTO calendar_events
                (task_id, user_id, title, description, event_date, start_datetime, end_datetime, location_label, team_label, participants_json, source_type, is_waiting_list, waiting_position, sync_state, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'local', ?, ?, 'pending', NOW(), NOW())";
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        $taskId = isset($payload['task_id']) ? (int)$payload['task_id'] : null;
        $userId = (int)$payload['user_id'];
        $title = trim((string)$payload['title']);
        $description = trim((string)($payload['description'] ?? ''));
        $eventDate = !empty($payload['event_date']) ? $payload['event_date'] : null;
        $start = !empty($payload['start_datetime']) ? $payload['start_datetime'] : null;
        $end = !empty($payload['end_datetime']) ? $payload['end_datetime'] : null;
        $location = trim((string)($payload['location_label'] ?? ''));
        $team = trim((string)($payload['team_label'] ?? ''));
        $participants = !empty($payload['participants_json']) ? $payload['participants_json'] : null;
        $isWaiting = !empty($payload['is_waiting_list']) ? 1 : 0;
        $waitingPos = isset($payload['waiting_position']) ? (int)$payload['waiting_position'] : 0;

        $stmt->bind_param(
            "iissssssssii",
            $taskId,
            $userId,
            $title,
            $description,
            $eventDate,
            $start,
            $end,
            $location,
            $team,
            $participants,
            $isWaiting,
            $waitingPos
        );

        $ok = $stmt->execute();
        $newId = $ok ? (int)$connect->insert_id : 0;
        $stmt->close();
        return $newId;
    }

    public static function updateEventPlacement($eventId, $userId, $eventDate = null, $isWaiting = false, $waitingPosition = 0)
    {
        global $connect;
        if (!self::hasTable('calendar_events')) {
            return false;
        }
        $sql = "UPDATE calendar_events
                SET event_date = ?, is_waiting_list = ?, waiting_position = ?, sync_state = 'pending', updated_at = NOW()
                WHERE id = ? AND user_id = ?";
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $wait = $isWaiting ? 1 : 0;
        $stmt->bind_param("siiii", $eventDate, $wait, $waitingPosition, $eventId, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public static function findEventById($eventId, $userId)
    {
        global $connect;
        if (!self::hasTable('calendar_events')) {
            return null;
        }
        $stmt = $connect->prepare("SELECT * FROM calendar_events WHERE id = ? AND user_id = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    /**
     * Merge scheduled tasks and standalone calendar events for one day column (drag order uses waiting_position across both).
     *
     * @param array<int, array<string, mixed>> $taskRows
     * @param array<int, array<string, mixed>> $eventRows
     * @return array<int, array{type: string, data: array<string, mixed>}>
     */
    public static function mergeCalendarDayCardsForDisplay(array $taskRows, array $eventRows, $sortOrder = 'asc')
    {
        $merged = [];
        $fbSeq = 0;
        foreach ($taskRows as $row) {
            $wp = isset($row['waiting_position']) ? (int)$row['waiting_position'] : (900000000 + $fbSeq);
            if (!isset($row['waiting_position'])) {
                $fbSeq++;
            }
            $uat = 0;
            if (!empty($row['schedule_updated_at'])) {
                $uat = strtotime((string)$row['schedule_updated_at']) ?: 0;
            }
            $merged[] = [
                'type' => 'task',
                'wp' => $wp,
                'uat' => $uat,
                'tid' => (int)($row['task_id'] ?? 0),
                'eid' => 0,
                'data' => $row,
            ];
        }
        $fbSeq = 0;
        foreach ($eventRows as $row) {
            $wp = isset($row['waiting_position']) ? (int)$row['waiting_position'] : (900000000 + $fbSeq);
            if (!isset($row['waiting_position'])) {
                $fbSeq++;
            }
            $uat = !empty($row['updated_at']) ? (strtotime((string)$row['updated_at']) ?: 0) : 0;
            $merged[] = [
                'type' => 'event',
                'wp' => $wp,
                'uat' => $uat,
                'tid' => 0,
                'eid' => (int)($row['id'] ?? 0),
                'data' => $row,
            ];
        }

        usort($merged, function ($a, $b) {
            if ($a['wp'] !== $b['wp']) {
                return $a['wp'] <=> $b['wp'];
            }
            if ($a['uat'] !== $b['uat']) {
                return $b['uat'] <=> $a['uat'];
            }
            if ($a['type'] !== $b['type']) {
                return $a['type'] === 'task' ? -1 : 1;
            }
            $ida = $a['type'] === 'task' ? $a['tid'] : $a['eid'];
            $idb = $b['type'] === 'task' ? $b['tid'] : $b['eid'];
            return $ida <=> $idb;
        });

        if ($sortOrder === 'desc') {
            $merged = array_reverse($merged);
        }

        $out = [];
        foreach ($merged as $m) {
            $out[] = ['type' => $m['type'], 'data' => $m['data']];
        }
        return $out;
    }

    /**
     * Renumber positions for remaining cards on a day after an item moved to the waiting list (or elsewhere).
     */
    public static function compactDayColumn($userId, $date)
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
            return false;
        }
        $keys = self::orderedDayKeysFromDb((int)$userId, (string)$date);
        return self::persistOrderedKeysForDay((int)$userId, (string)$date, $keys);
    }

    /**
     * Apply drag-drop order for a task or standalone event dropped on a day column (cross-type ordering).
     */
    public static function applyDayDropOrdering(
        $userId,
        $itemType,
        $itemId,
        $targetDate,
        $insertBeforeKey,
        $insertAfterKey,
        $positionFallback,
        $sourceDate,
        $sourceIsWaiting
    ) {
        global $connect;
        $userId = (int)$userId;
        $itemId = (int)$itemId;
        $itemType = $itemType === 'event' ? 'event' : 'task';
        $targetDate = (string)$targetDate;
        $insertBeforeKey = trim((string)$insertBeforeKey);
        $insertAfterKey = trim((string)$insertAfterKey);
        $positionFallback = (int)$positionFallback;
        $sourceDate = trim((string)$sourceDate);
        $sourceIsWaiting = (bool)$sourceIsWaiting;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $targetDate) || $itemId <= 0) {
            return false;
        }
        $movingKey = $itemType . ':' . $itemId;
        if (!self::calendarItemKeyValid($movingKey)) {
            return false;
        }

        $srcPayload = null;
        if ($sourceDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sourceDate) && !$sourceIsWaiting && $sourceDate !== $targetDate) {
            $srcKeys = self::orderedDayKeysFromDb($userId, $sourceDate);
            $srcKeys = array_values(array_filter($srcKeys, function ($k) use ($movingKey) {
                return $k !== $movingKey;
            }));
            $srcPayload = [$sourceDate, $srcKeys];
        }

        $tgtKeys = self::orderedDayKeysFromDb($userId, $targetDate);
        $tgtKeys = array_values(array_filter($tgtKeys, function ($k) use ($movingKey) {
            return $k !== $movingKey;
        }));

        $idx = self::computeCalendarInsertIndex($tgtKeys, $insertBeforeKey, $insertAfterKey, $positionFallback);
        array_splice($tgtKeys, $idx, 0, [$movingKey]);

        if (!($connect instanceof mysqli)) {
            return false;
        }

        try {
            if (!$connect->begin_transaction()) {
                return false;
            }

            self::persistOrderedKeysForDay($userId, $targetDate, $tgtKeys);
            if ($srcPayload !== null) {
                self::persistOrderedKeysForDay($userId, $srcPayload[0], $srcPayload[1]);
            }

            $connect->commit();
            return true;
        } catch (Throwable $e) {
            if ($connect instanceof mysqli) {
                $connect->rollback();
            }
            return false;
        }
    }

    private static function calendarItemKeyValid($key)
    {
        return is_string($key) && preg_match('/^(task|event):\d+$/', $key) === 1;
    }

    private static function orderedDayKeysFromDb($userId, $date)
    {
        global $connect;
        $items = [];
        if (self::hasTable('calendar_task_schedule')) {
            $stmt = $connect->prepare(
                "SELECT task_id AS oid, waiting_position AS wp, UNIX_TIMESTAMP(updated_at) AS uat
                 FROM calendar_task_schedule
                 WHERE user_id = ? AND schedule_date = ? AND is_waiting_list = 0"
            );
            if ($stmt) {
                $stmt->bind_param("is", $userId, $date);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $tid = (int)($row['oid'] ?? 0);
                    if ($tid > 0) {
                        $items[] = [
                            'k' => 'task:' . $tid,
                            'wp' => (int)($row['wp'] ?? 0),
                            'uat' => (int)($row['uat'] ?? 0),
                        ];
                    }
                }
                $stmt->close();
            }
        }

        if (self::hasTable('calendar_events')) {
            $stmt = $connect->prepare(
                "SELECT id AS oid, waiting_position AS wp, UNIX_TIMESTAMP(updated_at) AS uat
                 FROM calendar_events
                 WHERE user_id = ? AND event_date = ? AND is_waiting_list = 0
                   AND (task_id IS NULL OR task_id = 0)"
            );
            if ($stmt) {
                $stmt->bind_param("is", $userId, $date);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $eid = (int)($row['oid'] ?? 0);
                    if ($eid > 0) {
                        $items[] = [
                            'k' => 'event:' . $eid,
                            'wp' => (int)($row['wp'] ?? 0),
                            'uat' => (int)($row['uat'] ?? 0),
                        ];
                    }
                }
                $stmt->close();
            }
        }

        usort($items, function ($a, $b) {
            if ($a['wp'] !== $b['wp']) {
                return $a['wp'] <=> $b['wp'];
            }
            if ($a['uat'] !== $b['uat']) {
                return $b['uat'] <=> $a['uat'];
            }
            return strcmp($a['k'], $b['k']);
        });

        return array_column($items, 'k');
    }

    private static function computeCalendarInsertIndex(array $keysWithoutMoving, $beforeKey, $afterKey, $fallback)
    {
        $n = count($keysWithoutMoving);
        if (self::calendarItemKeyValid((string)$beforeKey)) {
            $i = array_search($beforeKey, $keysWithoutMoving, true);
            if ($i !== false) {
                return (int)$i;
            }
        }
        if (self::calendarItemKeyValid((string)$afterKey)) {
            $i = array_search($afterKey, $keysWithoutMoving, true);
            if ($i !== false) {
                return min($n, (int)$i + 1);
            }
        }
        if ($fallback < 0) {
            $fallback = 0;
        }
        if ($fallback > $n) {
            $fallback = $n;
        }
        return $fallback;
    }

    private static function persistOrderedKeysForDay($userId, $date, array $orderedKeys)
    {
        global $connect;
        if (!self::hasTable('calendar_task_schedule')) {
            return false;
        }

        foreach ($orderedKeys as $pos => $key) {
            $parts = explode(':', (string)$key, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$type, $idRaw] = $parts;
            $rid = (int)$idRaw;
            if ($rid <= 0) {
                continue;
            }

            if ($type === 'task') {
                $sql = "INSERT INTO calendar_task_schedule (task_id, user_id, schedule_date, is_waiting_list, waiting_position, created_at, updated_at)
                        VALUES (?, ?, ?, 0, ?, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE
                          schedule_date = VALUES(schedule_date),
                          is_waiting_list = 0,
                          waiting_position = VALUES(waiting_position),
                          updated_at = NOW()";
                $stmt = $connect->prepare($sql);
                if (!$stmt) {
                    throw new RuntimeException('calendar_task_schedule prepare failed');
                }
                $stmt->bind_param("iisi", $rid, $userId, $date, $pos);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new RuntimeException('calendar_task_schedule update failed');
                }
                $stmt->close();
            } elseif ($type === 'event') {
                if (!self::hasTable('calendar_events')) {
                    throw new RuntimeException('calendar_events table missing');
                }
                $stmt = $connect->prepare(
                    "UPDATE calendar_events
                     SET event_date = ?, is_waiting_list = 0, waiting_position = ?, sync_state = 'pending', updated_at = NOW()
                     WHERE id = ? AND user_id = ?
                       AND (task_id IS NULL OR task_id = 0)"
                );
                if (!$stmt) {
                    throw new RuntimeException('calendar_events prepare failed');
                }
                $stmt->bind_param("siii", $date, $pos, $rid, $userId);
                if (!$stmt->execute()) {
                    $stmt->close();
                    throw new RuntimeException('calendar_events update failed');
                }
                if ($stmt->affected_rows === 0) {
                    $stmt->close();
                    throw new RuntimeException('calendar_events row missing');
                }
                $stmt->close();
            }
        }

        return true;
    }
}
?>
