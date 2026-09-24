<?php
// includes/task.php

// if your lib‑initializer already loads database.php then you may not need this
require_once __DIR__ . '/lib-initialize.php';  

require_once('database-object.php');
require_once __DIR__ . '/task_activity_helper.php';
require_once __DIR__ . '/task_delete_helper.php';

class Task extends DatabaseObject {
    protected static $tblName = "tasks";
    protected static $tblFields = ['id', 'project_id', 'status', 'title', 'description', 'position', 'created_at', 'assigned_to', 'start_date', 'due_date', 'user_id', 'creator_id', 'last_default_status', 'completed_at', 'estimated_time_seconds', 'is_archived', 'recurrence_id', 'recurrence_parent_task_id'];
    
    public $id;
    public $project_id;
    public $status;
    public $title;
    public $description;
    public $position;
    public $created_at;
    public $assigned_to;
    public $start_date;
    public $due_date;
    public $user_id;
    public $creator_id;
    public $last_default_status;
    public $completed_at;
    /** @var int|null Estimate in seconds */
    public $estimated_time_seconds;
    /** @var int|null 1 when task is archived */
    public $is_archived;
    /** @var int|null Recurring series id */
    public $recurrence_id;
    /** @var int|null Previous occurrence task id */
    public $recurrence_parent_task_id;

    public static function activeTasksWhere($alias = '') {
        $col = ($alias !== '') ? $alias . '.is_archived' : 'is_archived';
        return "($col = 0 OR $col IS NULL)";
    }
    
    public static function findByProject($project_id) {
        return static::find_by_sql("SELECT * FROM " . static::$tblName . 
                               " WHERE project_id = " . (int)$project_id .
                               " AND " . static::activeTasksWhere() .
                               " ORDER BY position ASC");
    }
    
    public static function findAll() {
        $sql = "SELECT t.*, p.project_title 
                FROM " . static::$tblName . " t
                LEFT JOIN projects p ON t.project_id = p.p_id
                WHERE " . static::activeTasksWhere('t') . "
                ORDER BY t.created_at DESC";
                
        return static::find_by_sql($sql);
    }

    public static function findArchived() {
        $sql = "SELECT t.*, p.project_title 
                FROM " . static::$tblName . " t
                LEFT JOIN projects p ON t.project_id = p.p_id
                WHERE t.is_archived = 1
                ORDER BY t.created_at DESC";
        return static::find_by_sql($sql);
    }

    /**
     * Kanban list columns (omit description unless searching).
     */
    public static function kanbanSelectFields(bool $includeDescription = false): string
    {
        $base = 't.id, t.project_id, t.status, t.title, t.position, t.created_at, t.assigned_to, '
            . 't.start_date, t.due_date, t.user_id, t.creator_id, t.last_default_status, t.completed_at, '
            . 't.is_archived, t.recurrence_id, t.recurrence_parent_task_id, p.project_title';
        if ($includeDescription) {
            return str_replace('t.title,', 't.title, t.description,', $base);
        }
        return $base;
    }

    public static function kanbanMyTasksWhere(int $userId, string $alias = 't'): string
    {
        $uid = (int) $userId;
        return static::sqlAssignedToUserId((string) $uid, $alias);
    }

    /**
     * SQL fragment: task row is assigned to the given user id expression.
     * Normalizes comma lists (spaces) for FIND_IN_SET.
     *
     * @param string $userIdSql e.g. "123" or "u.id"
     */
    public static function sqlAssignedToUserId(string $userIdSql, string $alias = 't'): string
    {
        $col = $alias . '.assigned_to';
        $normalized = "REPLACE(REPLACE(TRIM(COALESCE({$col}, '')), ' ', ''), ',,', ',')";

        return '(FIND_IN_SET(' . $userIdSql . ', ' . $normalized . ') > 0'
            . ' OR TRIM(COALESCE(' . $col . ", '')) = CAST(" . $userIdSql . ' AS CHAR)'
            . " OR CONCAT(',', " . $normalized . ", ',') LIKE CONCAT('%,', " . $userIdSql . ", ',%'))";
    }

    public static function kanbanCreatedTasksWhere(int $userId, string $alias = 't'): string
    {
        $uid = (int) $userId;
        return '(' . $alias . '.creator_id = ' . $uid . ' OR ' . $alias . '.user_id = ' . $uid . ')';
    }

    public static function kanbanStaffArchiveWhere(int $userId, string $alias = 't'): string
    {
        $uid = (int) $userId;
        return '(' . static::kanbanMyTasksWhere($uid, $alias) . ' OR ' . static::kanbanCreatedTasksWhere($uid, $alias) . ')';
    }

    /**
     * True when kanban still needs PHP-side filtering (badge/computed status filters).
     */
    public static function kanbanRequiresPhpFilters(array $opts): bool
    {
        $statusFilter = isset($opts['statusFilter']) ? (string) $opts['statusFilter'] : '';
        $badgeFilters = ['overdue', 'due_today', 'due_soon', 'task_pro', 'on_time', 'recurring'];
        return $statusFilter !== '' && in_array($statusFilter, $badgeFilters, true);
    }

    /**
     * @return array{0:string,1:bool} SQL WHERE fragment (without WHERE keyword), includeDescription
     */
    public static function kanbanBuildWhere(array $opts): array
    {
        $parts = [];
        $includeDescription = !empty($opts['searchQuery']);
        $userId = (int) ($opts['userId'] ?? 0);

        if (!empty($opts['showArchive'])) {
            $parts[] = 't.is_archived = 1';
        } else {
            $parts[] = static::activeTasksWhere('t');
        }

        if (!empty($opts['showMyTasks']) && empty($opts['showArchive']) && $userId > 0) {
            $parts[] = static::kanbanMyTasksWhere($userId, 't');
        } elseif (empty($opts['showMyTasks']) && empty($opts['showArchive']) && $userId > 0 && !empty($opts['kanbanScopeCreatedOnly'])) {
            $parts[] = static::kanbanCreatedTasksWhere($userId, 't');
        }

        if (!empty($opts['internalFilter'])) {
            $parts[] = 't.project_id = 0';
        }

        $search = isset($opts['searchQuery']) ? trim((string) $opts['searchQuery']) : '';
        if ($search !== '') {
            global $database;
            $esc = $database->escapeValue($search);
            $like = "'%" . $esc . "%'";
            $parts[] = '(t.title LIKE ' . $like . ' OR t.description LIKE ' . $like . ')';
        }

        $startDate = isset($opts['startDateFilter']) ? trim((string) $opts['startDateFilter']) : '';
        $endDate = isset($opts['endDateFilter']) ? trim((string) $opts['endDateFilter']) : '';
        if ($startDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            $parts[] = '(t.start_date >= \'' . $startDate . '\' OR t.due_date >= \'' . $startDate . '\')';
        }
        if ($endDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $parts[] = '(t.start_date <= \'' . $endDate . '\' OR t.due_date <= \'' . $endDate . '\')';
        }

        $statusFilter = isset($opts['statusFilter']) ? (string) $opts['statusFilter'] : '';
        $simpleStatuses = ['todo', 'inprogress', 'review', 'done'];
        if ($statusFilter !== '' && in_array($statusFilter, $simpleStatuses, true)) {
            global $database;
            $parts[] = 't.status = \'' . $database->escapeValue($statusFilter) . '\'';
        }

        return [implode(' AND ', $parts), $includeDescription];
    }

    /**
     * Load tasks for kanban with SQL-side filters (slim columns).
     *
     * @return Task[]
     */
    public static function findForKanban(array $opts): array
    {
        list($where, $includeDescription) = static::kanbanBuildWhere($opts);
        $fields = static::kanbanSelectFields($includeDescription);
        $sql = 'SELECT ' . $fields . ' FROM ' . static::$tblName . ' t '
            . 'LEFT JOIN projects p ON t.project_id = p.p_id WHERE ' . $where
            . ' ORDER BY t.status ASC, t.position ASC, t.created_at ASC, t.id ASC';
        return static::find_by_sql($sql);
    }

    /**
     * Initial kanban load: up to $limitPerColumn tasks per column key (plus personal-column tasks).
     * Uses $opts['sortOrder'] (asc|desc) so LIMIT fetches the same end of the board the UI shows.
     * Default desc = highest position / newest first (new assignments appear without searching).
     *
     * @param list<string> $columnKeys
     * @return array{tasks: Task[], columnCounts: array<string,int>}
     */
    public static function loadKanbanColumnsLimited(array $opts, array $columnKeys, int $limitPerColumn = 11): array
    {
        global $database;
        $userId = (int) ($opts['userId'] ?? 0);
        $limitPerColumn = max(1, min(50, $limitPerColumn));
        $sortOrder = isset($opts['sortOrder']) ? strtolower(trim((string) $opts['sortOrder'])) : 'desc';
        if ($sortOrder !== 'asc' && $sortOrder !== 'desc') {
            $sortOrder = 'desc';
        }
        $orderDir = $sortOrder === 'asc' ? 'ASC' : 'DESC';
        $orderBy = 't.position ' . $orderDir . ', t.created_at ' . $orderDir . ', t.id ' . $orderDir;
        list($baseWhere,) = static::kanbanBuildWhere($opts);
        $fields = static::kanbanSelectFields(false);
        $defaultStatuses = ['todo', 'inprogress', 'review', 'done'];
        $tasksById = [];
        $columnCounts = [];

        foreach ($columnKeys as $columnKey) {
            $columnKey = (string) $columnKey;
            $columnCounts[$columnKey] = 0;
            $isPersonal = !in_array($columnKey, $defaultStatuses, true);

            if ($isPersonal) {
                $escCol = $database->escapeValue($columnKey);
                $countSql = 'SELECT COUNT(*) AS c FROM extra_tasks_columns e INNER JOIN tasks t ON t.id = e.task_id '
                    . 'LEFT JOIN projects p ON t.project_id = p.p_id WHERE e.user_id = ' . $userId
                    . ' AND e.column_key = \'' . $escCol . '\' AND ' . $baseWhere;
                $countRow = $database->fetchArray($database->query($countSql));
                $columnCounts[$columnKey] = (int) ($countRow['c'] ?? 0);

                $sql = 'SELECT ' . $fields . ' FROM extra_tasks_columns e '
                    . 'INNER JOIN tasks t ON t.id = e.task_id '
                    . 'LEFT JOIN projects p ON t.project_id = p.p_id '
                    . 'WHERE e.user_id = ' . $userId . ' AND e.column_key = \'' . $escCol . '\' AND ' . $baseWhere
                    . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int) $limitPerColumn;
            } else {
                $escStatus = $database->escapeValue($columnKey);
                $excludePersonal = 't.id NOT IN (SELECT task_id FROM extra_tasks_columns WHERE user_id = ' . $userId . ')';
                $colWhere = $baseWhere . ' AND t.status = \'' . $escStatus . '\' AND ' . $excludePersonal;

                $countSql = 'SELECT COUNT(*) AS c FROM ' . static::$tblName . ' t WHERE ' . $colWhere;
                $countRow = $database->fetchArray($database->query($countSql));
                $columnCounts[$columnKey] = (int) ($countRow['c'] ?? 0);

                $sql = 'SELECT ' . $fields . ' FROM ' . static::$tblName . ' t '
                    . 'LEFT JOIN projects p ON t.project_id = p.p_id WHERE ' . $colWhere
                    . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int) $limitPerColumn;
            }

            $rows = static::find_by_sql($sql);
            foreach ($rows as $task) {
                $tasksById[(int) $task->id] = $task;
            }
        }

        return ['tasks' => array_values($tasksById), 'columnCounts' => $columnCounts];
    }

    /**
     * Tab badge counts for kanban header.
     *
     * @return array{total_all:int,my_tasks:int,archived:int}
     */
    public static function kanbanTabCounts(int $userId, bool $canViewAll, bool $canViewCreatedTab): array
    {
        global $database;
        $active = static::activeTasksWhere('t');
        $uid = (int) $userId;

        if ($canViewAll) {
            $totalRow = $database->fetchArray($database->query(
                'SELECT COUNT(*) AS c FROM tasks t WHERE ' . $active
            ));
            $totalAll = (int) ($totalRow['c'] ?? 0);
        } elseif ($canViewCreatedTab) {
            $totalRow = $database->fetchArray($database->query(
                'SELECT COUNT(*) AS c FROM tasks t WHERE ' . $active . ' AND ' . static::kanbanCreatedTasksWhere($uid, 't')
            ));
            $totalAll = (int) ($totalRow['c'] ?? 0);
        } else {
            $totalAll = 0;
        }

        $myRow = $database->fetchArray($database->query(
            'SELECT COUNT(*) AS c FROM tasks t WHERE ' . $active . ' AND ' . static::kanbanMyTasksWhere($uid, 't')
        ));
        $archived = 0;
        if ($canViewAll) {
            $archRow = $database->fetchArray($database->query('SELECT COUNT(*) AS c FROM tasks WHERE is_archived = 1'));
            $archived = (int) ($archRow['c'] ?? 0);
        } else {
            $archRow = $database->fetchArray($database->query(
                'SELECT COUNT(*) AS c FROM tasks WHERE is_archived = 1 AND ' . static::kanbanStaffArchiveWhere($uid, 'tasks')
            ));
            $archived = (int) ($archRow['c'] ?? 0);
        }

        return [
            'total_all' => $totalAll,
            'my_tasks' => (int) ($myRow['c'] ?? 0),
            'archived' => $archived,
        ];
    }

    public function archive() {
        global $database;
        $sql = "UPDATE " . static::$tblName . " SET is_archived = 1 WHERE id = " . (int)$this->id . " LIMIT 1";
        $database->query($sql);
        return ($database->affectedRows() >= 0);
    }

    public function unarchive() {
        global $database;
        $sql = "UPDATE " . static::$tblName . " SET is_archived = 0 WHERE id = " . (int)$this->id . " LIMIT 1";
        $database->query($sql);
        return ($database->affectedRows() >= 0);
    }
    

    
    public static function findById($id=0) {
        $result_array = self::find_by_sql("SELECT * FROM " . static::$tblName . " WHERE id=" . (int)$id . " LIMIT 1");
        return !empty($result_array) ? array_shift($result_array) : false;
    }
    
    public function update() {
        global $database, $connect, $lang;

        $taskId = isset($this->id) ? (int)$this->id : 0;
        $oldStatus = null;
        if ($taskId > 0 && isset($connect) && $connect instanceof mysqli) {
            try {
                $res = mysqli_query($connect, 'SELECT status FROM tasks WHERE id=' . $taskId . ' LIMIT 1');
                $row = $res ? mysqli_fetch_assoc($res) : null;
                if ($row && array_key_exists('status', $row)) {
                    $oldStatus = (string)$row['status'];
                }
            } catch (\Exception $e) {
                error_log('[Task::update] status preselect: ' . $e->getMessage());
            }
        }

        $attributes = $this->sanitizedAttributes();

        $attributePairs = array();
        foreach ($attributes as $key => $value) {
            // sanitizedAttributes uses literal string "NULL" for SQL NULL — must not become 'NULL' in quotes (MySQL datetime strict error)
            if ($value === 'NULL') {
                $attributePairs[] = "{$key}=NULL";
            } else {
                $attributePairs[] = "{$key}='{$value}'";
            }
        }

        $sql = 'UPDATE ' . static::$tblName . ' SET ';
        $sql .= join(', ', $attributePairs);
        $sql .= ' WHERE id=' . $database->escapeValue($this->id);

        $database->query($sql);

        $ok = ($database->affectedRows() >= 0);

        $willRecord = ($ok && $taskId > 0 && $oldStatus !== null && (string)$oldStatus !== (string)$this->status);
        if ($willRecord) {
            try {
                $actorId = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : 0;
                $title = isset($lang['Status updated']) ? (string)$lang['Status updated'] : 'Status updated';
                $fromLabel = task_activity_status_label($oldStatus);
                $toLabel = task_activity_status_label((string)$this->status);
                $msg = $fromLabel . ' -> ' . $toLabel;
                recordTaskActivity($taskId, $actorId, 'task_status_changed', $title, $msg);
            } catch (\Exception $e) {
                error_log('[Task::update] recordTaskActivity: ' . $e->getMessage());
            }
        }

        return $ok;
    }
    
    public function save() {
        // If the ID exists, just update the record
        if(isset($this->id)) {
            return $this->update();
        } else {
            global $database;
            
            // Make sure creator_id is set for new tasks
            if(empty($this->creator_id) && isset($_SESSION['userId'])) {
                $this->creator_id = $_SESSION['userId'];
            }
            
            // Also set user_id if it's empty
            if(empty($this->user_id) && isset($_SESSION['userId'])) {
                $this->user_id = $_SESSION['userId'];
            }

            if (!isset($this->is_archived) || $this->is_archived === null || $this->is_archived === '') {
                $this->is_archived = 0;
            }
            
            // Prepare attributes for database
            $attributes = $this->sanitizedAttributes();
            
            // Build the INSERT SQL query
            $sql = "INSERT INTO ".static::$tblName." (";
            $sql .= join(", ", array_keys($attributes));
            $sql .= ") VALUES (";
            
            $values = [];
            foreach($attributes as $value) {
                if($value === "NULL") {
                    $values[] = "NULL";  // No quotes for NULL
                } else {
                    $values[] = "'" . $value . "'";  // Add quotes for non-NULL values
                }
            }
            
            $sql .= join(", ", $values);
            $sql .= ")";
            
            // Execute the query
            if ($database->query($sql)) {
                $this->id = $database->insertId();
                global $lang;
                $tid = (int)$this->id;
                $actorId = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : (int)($this->creator_id ?? 0);
                $title = isset($lang['Task created']) ? (string)$lang['Task created'] : 'Task created';
                $statusLabel = task_activity_status_label((string)($this->status ?? 'todo'));
                $rawTitle = isset($this->title) ? strip_tags((string)$this->title) : '';
                $snippet = function_exists('mb_substr')
                    ? mb_substr($rawTitle, 0, 80, 'UTF-8')
                    : substr($rawTitle, 0, 80);
                $snippet = trim(preg_replace('/\s+/', ' ', $snippet));
                $msg = $snippet !== '' ? ($snippet . ' · ' . $statusLabel) : $statusLabel;
                recordTaskActivity($tid, $actorId, 'task_created', $title, $msg);
                return true;
            }
            return false;
        }
    }
    
    protected function sanitizedAttributes() {
        global $database;
        $clean_attributes = array();
        
        // Sanitize the values before submitting
        foreach($this->attributes() as $key => $value) {
            // Special handling for creator_id during updates
            if($key === 'creator_id' && isset($this->id)) {
                // If we're updating an existing record (not creating new)
                // Only include creator_id if it has a valid value
                if($value !== null && $value !== '' && $value > 0) {
                    $clean_attributes[$key] = $database->escapeValue($value);
                }
                // Skip this field if empty or invalid for updates
                continue;
            }
            
            if($value === null || $value === '') {
                if($key === 'project_id') {
                    $clean_attributes[$key] = 0; // For project_id, use 0 instead of NULL for internal tasks
                } elseif ($key === 'is_archived') {
                    $clean_attributes[$key] = 0;
                } else {
                    $clean_attributes[$key] = "NULL";
                }
            } else {
                $clean_attributes[$key] = $database->escapeValue($value);
            }
        }
        return $clean_attributes;
    }
    
    protected function attributes() {
        // Return an array of attribute keys and their values
        $attributes = array();
        foreach(static::$tblFields as $field) {
            if(property_exists($this, $field)) {
                $attributes[$field] = $this->$field;
            }
        }
        
        // Check if creator_id is set, provide a default for new tasks
        if (!isset($attributes['creator_id']) || empty($attributes['creator_id'])) {
            // Only for new tasks (no ID yet)
            if (!isset($this->id) && isset($_SESSION['userId'])) {
                $attributes['creator_id'] = $_SESSION['userId'];
            }
        }
        
        return $attributes;
    }
    
    public function delete() {
        global $database;

        if (empty($this->id)) {
            return false;
        }

        task_delete_purge_related_data((int) $this->id);

        $sql = "DELETE FROM " . static::$tblName;
        $sql .= " WHERE id=" . (int)$this->id;
        $sql .= " LIMIT 1";
        $database->query($sql);
        return ($database->affectedRows() == 1) ? true : false;
    }
    
    public static function find_by_sql($sql="") {
        global $database;
        $result_set = $database->query($sql);
        $object_array = array();
        while ($row = $database->fetchArray($result_set)) {
            $object_array[] = self::instantiate($row);
        }
        return $object_array;
    }
    
    protected static function instantiate($record) {
        $object = new self;
        foreach($record as $attribute=>$value) {
            if($object->has_attribute($attribute)) {
                $object->$attribute = $value;
            }
        }
        return $object;
    }
    
    protected function has_attribute($attribute) {
        return array_key_exists($attribute, get_object_vars($this));
    }
    
    public static function createFromClone($task, $options = []) {
        // Create a new Task object
        $clone = new self();

        // Copy all fields except id, created_at
        $clone->project_id   = $task->project_id;
        $clone->status       = $task->status;
        $clone->title        = !empty($options['keep_title']) ? $task->title : ($task->title . ' (Copy)');
        $clone->description  = $task->description;
        $clone->position     = $task->position;
        $assignedTo = isset($task->assigned_to) ? (string)$task->assigned_to : '';
        if (!empty($options['force_assign_current_user']) && !empty($options['current_user_id'])) {
            $currentUserId = (string)((int)$options['current_user_id']);
            $assignedIds = array_filter(array_map('trim', explode(',', $assignedTo)));
            if (!in_array($currentUserId, $assignedIds, true)) {
                $assignedIds[] = $currentUserId;
            }
            $assignedTo = implode(',', $assignedIds);
        }
        $clone->assigned_to  = $assignedTo;
        $clone->start_date   = $task->start_date;
        $clone->due_date     = $task->due_date;
        $clone->user_id      = isset($options['user_id']) ? (int)$options['user_id'] : $task->user_id;
        $clone->creator_id   = isset($options['creator_id']) ? (int)$options['creator_id'] : $task->creator_id;

        // If clone has no date and caller provided a calendar date,
        // set it so the cloned task is visible on that day.
        if (!empty($options['default_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$options['default_date'])) {
            $defaultDate = (string)$options['default_date'];
            if (empty($clone->start_date) || $clone->start_date === '0000-00-00') {
                $clone->start_date = $defaultDate;
            }
            if (empty($clone->due_date) || $clone->due_date === '0000-00-00') {
                $clone->due_date = $defaultDate;
            }
        }

        // Save the new task (created_at will be set automatically if your DB has a default)
        if ($clone->save()) {
            return $clone;
        } else {
            return false;
        }
    }
    
    public function isClientAssociated($clientId) {
        // If this task is not linked to a project, return false
        if (empty($this->project_id)) {
            return false;
        }
        // Load the project
        require_once('projects.php');
        $project = projects::findByProjectId($this->project_id);
        if (!$project) {
            return false;
        }
        
        // Check if the client is the main client
        if (isset($project->c_id) && $project->c_id == $clientId) {
            return true;
        }
        
        // Check if the client is in the additional clients list
        if (!empty($project->c_ids)) {
            $additional_clients = array_filter(explode(',', $project->c_ids));
            if (in_array($clientId, $additional_clients)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function isAssignedTo($userId) {
        if (empty($this->assigned_to)) return false;
        $assignedIds = array_map('trim', explode(',', $this->assigned_to));
        return in_array((string)(int)$userId, $assignedIds, true);
    }
}
