<?php
/**
 * Project Reports – filter parsing, SQL scope, aggregates, formatting.
 */

require_once __DIR__ . '/reports_common_helper.php';
require_once __DIR__ . '/task_reports_helper.php';

if (!function_exists('project_reports_status_values')) {
    function project_reports_status_values(): array
    {
        return ['0', '1'];
    }
}

if (!function_exists('project_reports_parse_filters')) {
    /**
     * @param array<string,mixed>|null $input
     * @return array<string,mixed>
     */
    function project_reports_parse_filters(?array $input = null): array
    {
        if (!is_array($input)) {
            $input = [];
        }
        $date = reports_parse_date_filters($input);

        $status = isset($input['status']) ? trim((string)$input['status']) : '';
        if ($status !== '' && $status !== 'all' && !in_array($status, project_reports_status_values(), true)) {
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

if (!function_exists('project_reports_project_in_scope_sql')) {
    /**
     * Project in range if schedule overlaps OR has task activity in range.
     * Binds: to, from (overlap), then from, to, from, to (task scope in EXISTS).
     */
    function project_reports_project_in_scope_sql(string $projectAlias = 'p'): string
    {
        $p = $projectAlias;
        $taskScope = task_reports_task_in_scope_sql('t_scope');
        return "(
            ({$p}.start_time <= ? AND {$p}.end_time >= ?)
            OR EXISTS (
                SELECT 1 FROM tasks t_scope
                WHERE t_scope.project_id = {$p}.p_id
                  AND {$taskScope}
            )
        )";
    }
}

if (!function_exists('project_reports_build_filter_parts')) {
    /**
     * @return array{joins:string,where:string,types:string,params:array}
     */
    function project_reports_build_filter_parts(array $filters, string $projectAlias = 'p'): array
    {
        $p = $projectAlias;
        $from = $filters['from_date'];
        $to = $filters['to_date'];

        $where = [
            "{$p}.archive = 0",
            "{$p}.trash != 1",
            project_reports_project_in_scope_sql($p),
        ];
        $types = 'ssssssss';
        $params = [$to, $from, $from, $to, $from, $to, $from, $to];

        if (!empty($filters['user_id'])) {
            $where[] = "FIND_IN_SET(?, {$p}.s_ids) > 0";
            $types .= 'i';
            $params[] = (int)$filters['user_id'];
        }

        if (!empty($filters['project_id'])) {
            $where[] = "{$p}.p_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['project_id'];
        }

        if (!empty($filters['client_id'])) {
            $cid = (int)$filters['client_id'];
            $where[] = "(
                {$p}.c_id = ? OR {$p}.main_client_id = ? OR FIND_IN_SET(?, {$p}.c_ids) > 0
            )";
            $types .= 'iii';
            $params[] = $cid;
            $params[] = $cid;
            $params[] = (string)$cid;
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where[] = "{$p}.status = ?";
            $types .= 's';
            $params[] = $filters['status'];
        }

        return [
            'joins' => '',
            'where' => implode(' AND ', $where),
            'types' => $types,
            'params' => $params,
        ];
    }
}

if (!function_exists('project_reports_build_time_filter_parts')) {
    /**
     * Time entries for tasks on scoped projects.
     * @return array{joins:string,where:string,types:string,params:array}
     */
    function project_reports_build_time_filter_parts(array $filters, string $projectAlias = 'p', string $taskAlias = 't', string $entryAlias = 'e'): array
    {
        $parts = project_reports_build_filter_parts($filters, $projectAlias);
        $parts['joins'] = " INNER JOIN tasks {$taskAlias} ON {$taskAlias}.project_id = {$projectAlias}.p_id AND {$taskAlias}.project_id > 0 "
            . " INNER JOIN task_time_entries {$entryAlias} ON {$entryAlias}.task_id = {$taskAlias}.id ";
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

if (!function_exists('project_reports_fetch_card_metrics')) {
    /**
     * @return array{total_projects:int,completed_projects:int,active_projects:int,overdue_projects:int,total_time_sec:int,avg_time_per_project_sec:int}
     */
    function project_reports_fetch_card_metrics(mysqli $connect, array $filters): array
    {
        $parts = project_reports_build_filter_parts($filters);
        $sql = "SELECT
            COUNT(*) AS total_projects,
            SUM(CASE WHEN p.status = '1' THEN 1 ELSE 0 END) AS completed_projects,
            SUM(CASE WHEN p.status = '0' THEN 1 ELSE 0 END) AS active_projects,
            SUM(CASE WHEN p.status != '1' AND " . reports_sql_valid_date('p.end_time') . "
                AND p.end_time < CURDATE() THEN 1 ELSE 0 END) AS overdue_projects
            FROM projects p
            WHERE {$parts['where']}";

        $res = reports_bind_and_execute($connect, $sql, $parts['types'], $parts['params']);
        $row = [
            'total_projects' => 0,
            'completed_projects' => 0,
            'active_projects' => 0,
            'overdue_projects' => 0,
        ];
        if ($res && ($r = $res->fetch_assoc())) {
            $row = [
                'total_projects' => (int)$r['total_projects'],
                'completed_projects' => (int)$r['completed_projects'],
                'active_projects' => (int)$r['active_projects'],
                'overdue_projects' => (int)$r['overdue_projects'],
            ];
        }

        $totalTimeSec = 0;
        if (function_exists('tasksession_time_tracking_enabled') && tasksession_time_tracking_enabled()) {
            $tp = project_reports_build_time_filter_parts($filters);
            $timeSql = "SELECT COALESCE(SUM(e.duration_seconds), 0) AS total_sec
                FROM projects p {$tp['joins']}
                WHERE {$tp['where']}";
            $tRes = reports_bind_and_execute($connect, $timeSql, $tp['types'], $tp['params']);
            if ($tRes && ($tr = $tRes->fetch_assoc())) {
                $totalTimeSec = (int)$tr['total_sec'];
            }
        }

        $totalProjects = (int)$row['total_projects'];

        return [
            'total_projects' => $totalProjects,
            'completed_projects' => $row['completed_projects'],
            'active_projects' => $row['active_projects'],
            'overdue_projects' => $row['overdue_projects'],
            'total_time_sec' => $totalTimeSec,
            'avg_time_per_project_sec' => reports_avg_time_sec($totalTimeSec, $totalProjects),
        ];
    }
}

if (!function_exists('project_reports_build_card_comparison')) {
    function project_reports_build_card_comparison(array $current, array $previous): array
    {
        return reports_build_card_comparison(
            $current,
            $previous,
            ['total_projects', 'completed_projects', 'active_projects', 'overdue_projects', 'total_time_sec', 'avg_time_per_project_sec'],
            'completed_projects',
            'total_projects'
        );
    }
}

if (!function_exists('project_reports_fetch_cards')) {
    function project_reports_fetch_cards(mysqli $connect, array $filters): array
    {
        $metrics = project_reports_fetch_card_metrics($connect, $filters);

        $comparison = null;
        $compareFilters = reports_comparison_filters($filters);
        if ($compareFilters !== null) {
            $prevMetrics = project_reports_fetch_card_metrics($connect, $compareFilters);
            $comparison = [
                'label_key' => reports_comparison_label_key((string)($filters['date_preset'] ?? 'this_month')),
                'metrics' => project_reports_build_card_comparison($metrics, $prevMetrics),
            ];
        }

        $totalProjects = (int)$metrics['total_projects'];
        $completedProjects = (int)$metrics['completed_projects'];
        $completionRate = $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100, 2) : 0.0;

        return [
            'total_projects' => $totalProjects,
            'completed_projects' => $completedProjects,
            'active_projects' => $metrics['active_projects'],
            'overdue_projects' => $metrics['overdue_projects'],
            'completion_rate' => $completionRate,
            'total_time' => reports_format_duration_short($metrics['total_time_sec']),
            'avg_time_per_project' => reports_format_duration_short($metrics['avg_time_per_project_sec']),
            'comparison' => $comparison,
        ];
    }
}

if (!function_exists('project_reports_fetch_donut')) {
    function project_reports_fetch_donut(mysqli $connect, array $filters, ?array $cards = null): array
    {
        if ($cards === null) {
            $cards = project_reports_fetch_cards($connect, $filters);
        }

        return [
            'active' => (int)$cards['active_projects'],
            'completed' => (int)$cards['completed_projects'],
            'overdue' => (int)$cards['overdue_projects'],
            'total' => (int)$cards['total_projects'],
        ];
    }
}

if (!function_exists('project_reports_fetch_bar_raw')) {
    function project_reports_fetch_bar_raw(mysqli $connect, array $filters): array
    {
        $entries = [];
        $staffMap = [];

        if (!function_exists('tasksession_time_tracking_enabled') || !tasksession_time_tracking_enabled()) {
            return ['entries' => [], 'staff' => []];
        }

        $tp = project_reports_build_time_filter_parts($filters);
        $logDateExpr = task_reports_time_entry_date_sql('e');
        $sql = "SELECT {$logDateExpr} AS log_date,
                e.user_id, u.firstName, SUM(e.duration_seconds) AS total_sec
            FROM projects p {$tp['joins']}
            INNER JOIN users u ON u.id = e.user_id
            WHERE {$tp['where']}
            GROUP BY {$logDateExpr}, e.user_id, u.firstName
            ORDER BY log_date ASC, u.firstName ASC";

        $res = reports_bind_and_execute($connect, $sql, $tp['types'], $tp['params']);
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

if (!function_exists('project_reports_fetch_projects')) {
    function project_reports_fetch_projects(mysqli $connect, array $filters, string $baseUrl = ''): array
    {
        $parts = project_reports_build_filter_parts($filters);
        $sql = "SELECT p.p_id, p.project_title, p.status, p.c_id, p.main_client_id
            FROM projects p
            WHERE {$parts['where']}
            ORDER BY p.project_title ASC";

        $res = reports_bind_and_execute($connect, $sql, $parts['types'], $parts['params']);
        if (!$res) {
            return [];
        }

        $projectRows = [];
        while ($row = $res->fetch_assoc()) {
            $pid = (int)$row['p_id'];
            $projectRows[$pid] = [
                'id' => $pid,
                'title' => (string)$row['project_title'],
                'client_id' => (int)($row['main_client_id'] > 0 ? $row['main_client_id'] : $row['c_id']),
                'total_tasks' => 0,
                'completed' => 0,
                'pending' => 0,
                'overdue' => 0,
                'total_seconds' => 0,
            ];
        }

        if (empty($projectRows)) {
            return [];
        }

        $ids = implode(',', array_map('intval', array_keys($projectRows)));
        $from = $filters['from_date'];
        $to = $filters['to_date'];
        $taskScope = task_reports_task_in_scope_sql('t');
        $taskSql = "SELECT t.project_id, t.status, t.due_date
            FROM tasks t
            WHERE t.project_id IN ($ids)
              AND {$taskScope}";
        $taskRes = reports_bind_and_execute($connect, $taskSql, 'ssssss', [$from, $to, $from, $to, $from, $to]);
        if ($taskRes) {
            while ($tr = $taskRes->fetch_assoc()) {
                $pid = (int)$tr['project_id'];
                if (!isset($projectRows[$pid])) {
                    continue;
                }
                if (!empty($filters['user_id'])) {
                    // Per-project task filter by staff is applied via project s_ids; skip unassigned mismatch at task level
                }
                $projectRows[$pid]['total_tasks']++;
                if ($tr['status'] === 'done') {
                    $projectRows[$pid]['completed']++;
                }
                if (in_array($tr['status'], ['todo', 'inprogress', 'review'], true)) {
                    $projectRows[$pid]['pending']++;
                }
                if ($tr['status'] !== 'done'
                    && !empty($tr['due_date'])
                    && $tr['due_date'] !== '0000-00-00'
                    && $tr['due_date'] < date('Y-m-d')) {
                    $projectRows[$pid]['overdue']++;
                }
            }
        }

        if (function_exists('tasksession_time_tracking_enabled') && tasksession_time_tracking_enabled()) {
            $timeSql = "SELECT t.project_id, SUM(e.duration_seconds) AS total_sec
                FROM tasks t
                INNER JOIN task_time_entries e ON e.task_id = t.id
                WHERE t.project_id IN ($ids)
                  AND " . task_reports_time_entry_date_sql('e') . " BETWEEN ? AND ?
                GROUP BY t.project_id";
            $timeRes = reports_bind_and_execute($connect, $timeSql, 'ss', [$from, $to]);
            if ($timeRes) {
                while ($tr = $timeRes->fetch_assoc()) {
                    $pid = (int)$tr['project_id'];
                    if (isset($projectRows[$pid])) {
                        $projectRows[$pid]['total_seconds'] = (int)$tr['total_sec'];
                    }
                }
            }
        }

        $clientIds = [];
        foreach ($projectRows as $pr) {
            if ($pr['client_id'] > 0) {
                $clientIds[$pr['client_id']] = true;
            }
        }
        $clientsById = [];
        if (!empty($clientIds)) {
            $cidList = implode(',', array_map('intval', array_keys($clientIds)));
            $cRes = $connect->query("SELECT id, firstName FROM users WHERE id IN ($cidList)");
            if ($cRes) {
                while ($cr = $cRes->fetch_assoc()) {
                    $clientsById[(int)$cr['id']] = trim((string)($cr['firstName'] ?? ''));
                }
            }
        }

        $adminBase = rtrim($baseUrl, '/') . '/admin/';
        $rows = [];
        foreach ($projectRows as $pr) {
            $pid = $pr['id'];
            $total = (int)$pr['total_tasks'];
            $completed = (int)$pr['completed'];
            $sec = (int)$pr['total_seconds'];
            $completion = $total > 0 ? (int)round(($completed / $total) * 100) : 0;
            $avgSec = reports_avg_time_sec($sec, $total);

            $clientId = (int)$pr['client_id'];
            $clientName = $clientId > 0 ? ($clientsById[$clientId] ?? '') : '';
            if ($clientName === '' && $clientId > 0 && class_exists('User')) {
                $clientObj = User::findById($clientId);
                if ($clientObj) {
                    $clientName = trim((string)($clientObj->firstName ?? ''));
                }
            }
            if ($clientName === '' && $clientId > 0) {
                $clientName = 'Client #' . $clientId;
            }

            $avatarHtml = '';
            if ($clientId > 0 && function_exists('getUserAvatarHtml')) {
                $avatarHtml = getUserAvatarHtml($clientId, $clientName, '', 35, 35, '', $clientName);
            }

            $rows[] = [
                'id' => $pid,
                'name' => $pr['title'],
                'client_id' => $clientId,
                'client_name' => $clientName,
                'avatar_html' => $avatarHtml,
                'total_tasks' => $total,
                'completed' => $completed,
                'pending' => (int)$pr['pending'],
                'overdue' => (int)$pr['overdue'],
                'total_time' => reports_format_duration_short($sec),
                'avg_time' => reports_format_duration_short($avgSec),
                'completion_pct' => $completion,
                'completion' => $completion . '%',
                'view_project_url' => $adminBase . 'overview.php?projectId=' . $pid,
            ];
        }

        usort($rows, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $rows;
    }
}

if (!function_exists('project_reports_fetch_bar_comparison')) {
    function project_reports_fetch_bar_comparison(mysqli $connect, array $filters): ?array
    {
        $compareFilters = reports_comparison_filters($filters);
        if ($compareFilters === null) {
            return null;
        }

        $prevBar = project_reports_fetch_bar_raw($connect, $compareFilters);

        return [
            'label_key' => reports_comparison_label_key((string)($filters['date_preset'] ?? 'this_month')),
            'entries' => $prevBar['entries'] ?? [],
        ];
    }
}

if (!function_exists('project_reports_fetch_payload')) {
    function project_reports_fetch_payload(mysqli $connect, array $filters, string $baseUrl = '', string $othersLabel = 'Others'): array
    {
        $cards = project_reports_fetch_cards($connect, $filters);
        $donut = project_reports_fetch_donut($connect, $filters, $cards);
        $bar = project_reports_fetch_bar_raw($connect, $filters);
        $barComparison = project_reports_fetch_bar_comparison($connect, $filters);
        $projects = project_reports_fetch_projects($connect, $filters, $baseUrl);

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
            'projects' => $projects,
        ];
    }
}
