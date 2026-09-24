<?php
/**
 * Task Reports – filter parsing, SQL scope, aggregates, formatting.
 */

require_once __DIR__ . '/reports_common_helper.php';

if (!function_exists('task_reports_status_types')) {
    function task_reports_status_types(): array
    {
        return ['todo', 'inprogress', 'review', 'done'];
    }
}

if (!function_exists('task_reports_parse_filters')) {
    /**
     * @param array<string,mixed>|null $input Typically $_GET
     * @return array<string,mixed>
     */
    function task_reports_parse_filters(?array $input = null): array
    {
        if (!is_array($input)) {
            $input = [];
        }
        $date = reports_parse_date_filters($input);

        $status = isset($input['status']) ? trim((string)$input['status']) : '';
        if ($status !== '' && $status !== 'all' && !in_array($status, task_reports_status_types(), true)) {
            $status = '';
        }

        return array_merge($date, [
            'user_id' => isset($input['user_id']) ? max(0, (int)$input['user_id']) : 0,
            'project_id' => isset($input['project_id']) ? max(0, (int)$input['project_id']) : 0,
            'client_id' => isset($input['client_id']) ? max(0, (int)$input['client_id']) : 0,
            'status' => $status,
        ]);
    }
}

if (!function_exists('task_reports_resolve_date_range')) {
    function task_reports_resolve_date_range(string $preset, string $from = '', string $to = ''): array
    {
        return reports_resolve_date_range($preset, $from, $to);
    }
}

if (!function_exists('task_reports_valid_ymd')) {
    function task_reports_valid_ymd(string $date): bool
    {
        return reports_valid_ymd($date);
    }
}

if (!function_exists('task_reports_task_in_scope_sql')) {
    /**
     * Task included if created in range OR has a time log in range.
     * Binds: from, to, from, to (four placeholders).
     */
    function task_reports_task_in_scope_sql(string $taskAlias = 't'): string
    {
        $t = $taskAlias;
        return "(
            (" . reports_sql_valid_date("{$t}.created_at") . " AND DATE({$t}.created_at) BETWEEN ? AND ?)
            OR " . reports_sql_date_between("{$t}.start_date") . "
            OR EXISTS (
                SELECT 1 FROM task_time_entries e_scope
                WHERE e_scope.task_id = {$t}.id
                  AND DATE(COALESCE(e_scope.ended_at, e_scope.started_at)) BETWEEN ? AND ?
            )
        )";
    }
}

if (!function_exists('task_reports_build_filter_parts')) {
    /**
     * @return array{joins:string,where:string,types:string,params:array}
     */
    function task_reports_build_filter_parts(array $filters, string $taskAlias = 't'): array
    {
        $t = $taskAlias;
        $joins = '';
        $where = [task_reports_task_in_scope_sql($t)];
        $types = 'ssssss';
        $params = [
            $filters['from_date'],
            $filters['to_date'],
            $filters['from_date'],
            $filters['to_date'],
            $filters['from_date'],
            $filters['to_date'],
        ];

        $needsProjectJoin = !empty($filters['client_id']) || !empty($filters['project_id']);
        if ($needsProjectJoin) {
            $joins .= " LEFT JOIN projects p ON p.p_id = {$t}.project_id AND {$t}.project_id > 0 ";
        }

        if (!empty($filters['user_id'])) {
            $where[] = "FIND_IN_SET(?, {$t}.assigned_to) > 0";
            $types .= 'i';
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['project_id'])) {
            $where[] = "{$t}.project_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['project_id'];
        }

        if (!empty($filters['client_id'])) {
            $cid = (int)$filters['client_id'];
            $where[] = "(
                {$t}.project_id > 0 AND (
                    p.c_id = ? OR p.main_client_id = ? OR FIND_IN_SET(?, p.c_ids) > 0
                )
            )";
            $types .= 'iii';
            $params[] = $cid;
            $params[] = $cid;
            $params[] = (string)$cid;
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = "{$t}.status = ?";
            $types .= 's';
            $params[] = $filters['status'];
        }

        return [
            'joins' => $joins,
            'where' => implode(' AND ', $where),
            'types' => $types,
            'params' => $params,
        ];
    }
}

if (!function_exists('task_reports_bind_and_execute')) {
    function task_reports_bind_and_execute(mysqli $connect, string $sql, string $types, array $params)
    {
        return reports_bind_and_execute($connect, $sql, $types, $params);
    }
}

if (!function_exists('task_reports_format_duration_short')) {
    function task_reports_format_duration_short(int $seconds): string
    {
        return reports_format_duration_short($seconds);
    }
}

if (!function_exists('task_reports_avg_time_per_task_sec')) {
    function task_reports_avg_time_per_task_sec(int $totalTimeSec, int $totalTasks): int
    {
        return reports_avg_time_sec($totalTimeSec, $totalTasks);
    }
}

if (!function_exists('task_reports_seconds_to_chart_hours')) {
    function task_reports_seconds_to_chart_hours(int $seconds): float
    {
        return round($seconds / 3600, 2);
    }
}

if (!function_exists('task_reports_time_entry_date_sql')) {
    function task_reports_time_entry_date_sql(string $entryAlias = 'e'): string
    {
        return "DATE(COALESCE({$entryAlias}.ended_at, {$entryAlias}.started_at))";
    }
}

if (!function_exists('task_reports_build_time_filter_parts')) {
    /**
     * @return array{joins:string,where:string,types:string,params:array}
     */
    function task_reports_build_time_filter_parts(array $filters, string $taskAlias = 't', string $entryAlias = 'e'): array
    {
        $parts = task_reports_build_filter_parts($filters, $taskAlias);
        $parts['joins'] = " INNER JOIN tasks {$taskAlias} ON {$taskAlias}.id = {$entryAlias}.task_id " . $parts['joins'];
        $parts['where'] .= ' AND ' . task_reports_time_entry_date_sql($entryAlias) . ' BETWEEN ? AND ?';
        $parts['types'] .= 'ss';
        $parts['params'][] = $filters['from_date'];
        $parts['params'][] = $filters['to_date'];

        if (!empty($filters['user_id'])) {
            $parts['where'] .= " AND {$entryAlias}.user_id = ?";
            $parts['types'] .= 'i';
            $parts['params'][] = (int)$filters['user_id'];
        }

        return $parts;
    }
}

if (!function_exists('task_reports_calc_percent_change')) {
    function task_reports_calc_percent_change($current, $previous): float
    {
        return reports_calc_percent_change($current, $previous);
    }
}

if (!function_exists('task_reports_comparison_filters')) {
    function task_reports_comparison_filters(array $filters): ?array
    {
        return reports_comparison_filters($filters);
    }
}

if (!function_exists('task_reports_comparison_label_key')) {
    function task_reports_comparison_label_key(string $preset): string
    {
        return reports_comparison_label_key($preset);
    }
}

if (!function_exists('task_reports_fetch_card_metrics')) {
    /**
     * @return array{total_tasks:int,completed_tasks:int,pending_tasks:int,overdue_tasks:int,total_time_sec:int,avg_time_per_task_sec:int}
     */
    function task_reports_fetch_card_metrics(mysqli $connect, array $filters): array
    {
        $parts = task_reports_build_filter_parts($filters);
        $sql = "SELECT
            COUNT(*) AS total_tasks,
            SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS completed_tasks,
            SUM(CASE WHEN t.status IN ('todo','inprogress','review') THEN 1 ELSE 0 END) AS pending_tasks,
            SUM(CASE WHEN " . reports_sql_valid_date('t.due_date') . "
                AND t.due_date < CURDATE() AND t.status != 'done' THEN 1 ELSE 0 END) AS overdue_tasks
            FROM tasks t {$parts['joins']}
            WHERE {$parts['where']}";

        $res = task_reports_bind_and_execute($connect, $sql, $parts['types'], $parts['params']);
        $row = ['total_tasks' => 0, 'completed_tasks' => 0, 'pending_tasks' => 0, 'overdue_tasks' => 0];
        if ($res && ($r = $res->fetch_assoc())) {
            $row = [
                'total_tasks' => (int)$r['total_tasks'],
                'completed_tasks' => (int)$r['completed_tasks'],
                'pending_tasks' => (int)$r['pending_tasks'],
                'overdue_tasks' => (int)$r['overdue_tasks'],
            ];
        }

        $totalTimeSec = 0;

        if (function_exists('tasksession_time_tracking_enabled') && tasksession_time_tracking_enabled()) {
            $tp = task_reports_build_time_filter_parts($filters);
            $timeSql = "SELECT COALESCE(SUM(e.duration_seconds), 0) AS total_sec
                FROM task_time_entries e {$tp['joins']}
                WHERE {$tp['where']}";
            $tRes = task_reports_bind_and_execute($connect, $timeSql, $tp['types'], $tp['params']);
            if ($tRes && ($tr = $tRes->fetch_assoc())) {
                $totalTimeSec = (int)$tr['total_sec'];
            }
        }

        $totalTasks = (int)$row['total_tasks'];
        $avgTimePerTaskSec = task_reports_avg_time_per_task_sec($totalTimeSec, $totalTasks);

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $row['completed_tasks'],
            'pending_tasks' => $row['pending_tasks'],
            'overdue_tasks' => $row['overdue_tasks'],
            'total_time_sec' => $totalTimeSec,
            'avg_time_per_task_sec' => $avgTimePerTaskSec,
        ];
    }
}

if (!function_exists('task_reports_build_card_comparison')) {
    function task_reports_build_card_comparison(array $current, array $previous): array
    {
        return reports_build_card_comparison(
            $current,
            $previous,
            ['total_tasks', 'completed_tasks', 'pending_tasks', 'overdue_tasks', 'total_time_sec', 'avg_time_per_task_sec'],
            'completed_tasks',
            'total_tasks'
        );
    }
}

if (!function_exists('task_reports_fetch_cards')) {
    function task_reports_fetch_cards(mysqli $connect, array $filters): array
    {
        $metrics = task_reports_fetch_card_metrics($connect, $filters);

        $comparison = null;
        $compareFilters = task_reports_comparison_filters($filters);
        if ($compareFilters !== null) {
            $prevMetrics = task_reports_fetch_card_metrics($connect, $compareFilters);
            $comparison = [
                'label_key' => task_reports_comparison_label_key((string)($filters['date_preset'] ?? 'this_month')),
                'metrics' => task_reports_build_card_comparison($metrics, $prevMetrics),
            ];
        }

        $totalTasks = (int)$metrics['total_tasks'];
        $completedTasks = (int)$metrics['completed_tasks'];
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0.0;

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'pending_tasks' => $metrics['pending_tasks'],
            'overdue_tasks' => $metrics['overdue_tasks'],
            'completion_rate' => $completionRate,
            'total_time' => task_reports_format_duration_short($metrics['total_time_sec']),
            'avg_time_per_task' => task_reports_format_duration_short($metrics['avg_time_per_task_sec']),
            'comparison' => $comparison,
        ];
    }
}

if (!function_exists('task_reports_fetch_donut')) {
    function task_reports_fetch_donut(mysqli $connect, array $filters, ?array $cards = null): array
    {
        if ($cards === null) {
            $cards = task_reports_fetch_cards($connect, $filters);
        }
        $completed = (int)$cards['completed_tasks'];
        $open = (int)$cards['pending_tasks'];
        $total = (int)$cards['total_tasks'];
        $incomplete = max(0, $total - $completed);

        return [
            'open' => $open,
            'completed' => $completed,
            'incomplete' => $incomplete,
            'total' => $total,
        ];
    }
}

if (!function_exists('task_reports_fetch_bar_raw')) {
    function task_reports_fetch_bar_raw(mysqli $connect, array $filters): array
    {
        $entries = [];
        $staffMap = [];

        if (!function_exists('tasksession_time_tracking_enabled') || !tasksession_time_tracking_enabled()) {
            return ['entries' => [], 'staff' => []];
        }

        $tp = task_reports_build_time_filter_parts($filters);
        $logDateExpr = task_reports_time_entry_date_sql('e');
        $sql = "SELECT {$logDateExpr} AS log_date,
                e.user_id, u.firstName, SUM(e.duration_seconds) AS total_sec
            FROM task_time_entries e {$tp['joins']}
            INNER JOIN users u ON u.id = e.user_id
            WHERE {$tp['where']}
            GROUP BY {$logDateExpr}, e.user_id, u.firstName
            ORDER BY log_date ASC, u.firstName ASC";

        $res = task_reports_bind_and_execute($connect, $sql, $tp['types'], $tp['params']);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $uid = (int)$row['user_id'];
                $name = (string)$row['firstName'];
                $entries[] = [
                    'date' => (string)$row['log_date'],
                    'user_id' => $uid,
                    'name' => $name,
                    'seconds' => (int)$row['total_sec'],
                ];
                if (!isset($staffMap[$uid])) {
                    $staffMap[$uid] = ['id' => $uid, 'name' => $name];
                }
            }
        }

        $staff = array_values($staffMap);
        usort($staff, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return ['entries' => $entries, 'staff' => $staff];
    }
}

if (!function_exists('task_reports_chart_user_colors')) {
    function task_reports_chart_user_colors(int $count): array
    {
        $base = ['#0088ff', '#6c757d', '#36b9cc', '#42b72a', '#fe6094', '#4db6ad', '#fd7e14', '#20c997', '#a81bcb'];
        $out = [];
        for ($i = 0; $i < max(1, $count); $i++) {
            $out[] = $base[$i % count($base)];
        }
        return $out;
    }
}

if (!function_exists('task_reports_fetch_users')) {
    function task_reports_fetch_users(mysqli $connect, array $filters, string $baseUrl = ''): array
    {
        $parts = task_reports_build_filter_parts($filters);
        $sql = "SELECT t.id, t.status, t.assigned_to, t.due_date
            FROM tasks t {$parts['joins']}
            WHERE {$parts['where']}";

        $res = task_reports_bind_and_execute($connect, $sql, $parts['types'], $parts['params']);
        $userStats = [];

        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $assignees = array_filter(array_map('trim', explode(',', (string)$row['assigned_to'])));
                if (empty($assignees)) {
                    continue;
                }
                $isDone = ($row['status'] === 'done');
                $isPending = in_array($row['status'], ['todo', 'inprogress', 'review'], true);
                $isOverdue = !$isDone
                    && !empty($row['due_date'])
                    && $row['due_date'] !== '0000-00-00'
                    && $row['due_date'] < date('Y-m-d');

                foreach ($assignees as $uid) {
                    $uid = (int)$uid;
                    if ($uid <= 0) {
                        continue;
                    }
                    if (!empty($filters['user_id']) && $uid !== (int)$filters['user_id']) {
                        continue;
                    }
                    if (!isset($userStats[$uid])) {
                        $userStats[$uid] = [
                            'total_tasks' => 0,
                            'completed' => 0,
                            'pending' => 0,
                            'overdue' => 0,
                            'total_seconds' => 0,
                        ];
                    }
                    $userStats[$uid]['total_tasks']++;
                    if ($isDone) {
                        $userStats[$uid]['completed']++;
                    }
                    if ($isPending) {
                        $userStats[$uid]['pending']++;
                    }
                    if ($isOverdue) {
                        $userStats[$uid]['overdue']++;
                    }
                }
            }
        }

        if (function_exists('tasksession_time_tracking_enabled') && tasksession_time_tracking_enabled()) {
            $tp = task_reports_build_time_filter_parts($filters);
            $timeSql = "SELECT e.user_id, SUM(e.duration_seconds) AS total_sec
                FROM task_time_entries e {$tp['joins']}
                WHERE {$tp['where']}
                GROUP BY e.user_id";
            $tRes = task_reports_bind_and_execute($connect, $timeSql, $tp['types'], $tp['params']);
            if ($tRes) {
                while ($tr = $tRes->fetch_assoc()) {
                    $uid = (int)$tr['user_id'];
                    if (!isset($userStats[$uid])) {
                        $userStats[$uid] = [
                            'total_tasks' => 0,
                            'completed' => 0,
                            'pending' => 0,
                            'overdue' => 0,
                            'total_seconds' => 0,
                        ];
                    }
                    $userStats[$uid]['total_seconds'] = (int)$tr['total_sec'];
                }
            }
        }

        if (empty($userStats)) {
            return [];
        }

        $ids = implode(',', array_map('intval', array_keys($userStats)));
        $usersById = [];
        $nameRes = false;
        try {
            $nameRes = $connect->query("SELECT id, firstName FROM users WHERE id IN ($ids)");
        } catch (Throwable $e) {
            error_log('[task_reports] users lookup: ' . $e->getMessage());
        }
        if ($nameRes) {
            while ($nr = $nameRes->fetch_assoc()) {
                $usersById[(int)$nr['id']] = [
                    'firstName' => trim((string)($nr['firstName'] ?? '')),
                ];
            }
        }

        $rows = [];
        foreach ($userStats as $uid => $stat) {
            $total = (int)$stat['total_tasks'];
            $completed = (int)$stat['completed'];
            $sec = (int)$stat['total_seconds'];
            $completion = $total > 0 ? (int)round(($completed / $total) * 100) : 0;
            $avgSec = task_reports_avg_time_per_task_sec($sec, $total);

            $profileBase = rtrim($baseUrl, '/') . '/admin/profile';
            $userInfo = $usersById[$uid] ?? null;
            if ($userInfo === null && class_exists('User')) {
                $userObj = User::findById($uid);
                if ($userObj) {
                    $userInfo = ['firstName' => trim((string)($userObj->firstName ?? ''))];
                }
            }
            $firstName = $userInfo ? $userInfo['firstName'] : '';
            $displayName = $firstName !== '' ? $firstName : ('User #' . $uid);
            $avatarHtml = '';
            if (function_exists('getUserAvatarHtml')) {
                $avatarHtml = getUserAvatarHtml(
                    $uid,
                    $firstName,
                    '',
                    35,
                    35,
                    '',
                    $displayName
                );
            }

            $rows[] = [
                'id' => $uid,
                'name' => $displayName,
                'avatar_html' => $avatarHtml,
                'total_tasks' => $total,
                'completed' => $completed,
                'pending' => (int)$stat['pending'],
                'overdue' => (int)$stat['overdue'],
                'total_time' => task_reports_format_duration_short($sec),
                'avg_time' => task_reports_format_duration_short($avgSec),
                'completion_pct' => $completion,
                'completion' => $completion . '%',
                'view_tasks_url' => $profileBase . '?user_id=' . $uid . '&tab=tasks',
                'view_profile_url' => $profileBase . '?user_id=' . $uid,
            ];
        }

        usort($rows, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $rows;
    }
}

if (!function_exists('task_reports_fetch_bar_comparison')) {
    function task_reports_fetch_bar_comparison(mysqli $connect, array $filters): ?array
    {
        $compareFilters = task_reports_comparison_filters($filters);
        if ($compareFilters === null) {
            return null;
        }

        $prevBar = task_reports_fetch_bar_raw($connect, $compareFilters);

        return [
            'label_key' => task_reports_comparison_label_key((string)($filters['date_preset'] ?? 'this_month')),
            'entries' => $prevBar['entries'] ?? [],
        ];
    }
}

if (!function_exists('task_reports_fetch_payload')) {
    function task_reports_fetch_payload(mysqli $connect, array $filters, string $baseUrl = '', string $othersLabel = 'Others'): array
    {
        $cards = task_reports_fetch_cards($connect, $filters);
        $donut = task_reports_fetch_donut($connect, $filters, $cards);
        $bar = task_reports_fetch_bar_raw($connect, $filters);
        $barComparison = task_reports_fetch_bar_comparison($connect, $filters);
        $users = task_reports_fetch_users($connect, $filters, $baseUrl);

        return [
            'status' => 'ok',
            'filters' => [
                'from_date' => $filters['from_date'],
                'to_date' => $filters['to_date'],
                'date_preset' => $filters['date_preset'] ?? 'this_month',
            ],
            'cards' => $cards,
            'donut' => $donut,
            'bar' => $bar,
            'bar_comparison' => $barComparison,
            'users' => $users,
        ];
    }
}

if (!function_exists('task_reports_render_toolbar_dropdown')) {
    function task_reports_render_toolbar_dropdown(array $args): void
    {
        reports_render_toolbar_dropdown($args);
    }
}
