<?php
/**
 * Global mega search — tab config, role context, SQL runners.
 */
require_once __DIR__ . '/user_search_helper.php';
require_once __DIR__ . '/invoice_search_helper.php';
require_once __DIR__ . '/sidebar_navigation.php';

if (!defined('COMON_MEGA_SEARCH_LIMIT')) {
    define('COMON_MEGA_SEARCH_LIMIT', 12);
}

if (!function_exists('comon_mega_search_role_key')) {
    function comon_mega_search_role_key(int $accountStatus): string
    {
        if ($accountStatus === 1) {
            return 'admin';
        }
        if ($accountStatus === 3) {
            return 'staff';
        }
        return 'client';
    }
}

if (!function_exists('comon_mega_search_context')) {
    /**
     * @return array<string,mixed>
     */
    function comon_mega_search_context($session = null, $settings = null, $user = null): array
    {
        global $connect, $url, $lang;

        if (!$session && isset($GLOBALS['session'])) {
            $session = $GLOBALS['session'];
        }
        $userId = ($session && isset($session->userId)) ? (int) $session->userId : 0;
        $accountStatus = isset($_SESSION['accountStatus']) ? (int) $_SESSION['accountStatus'] : 0;
        $role = comon_mega_search_role_key($accountStatus);

        if (!$settings) {
            $settings = settings::findById(1);
        }
        if (!$user && $userId > 0) {
            $user = User::findById($userId);
        }

        $rolePrefix = $role === 'admin' ? 'admin/' : ($role === 'staff' ? 'staff/' : 'client/');
        $panelBase = rtrim((string) $url, '/') . '/' . $rolePrefix;

        if ($role === 'staff' && !function_exists('has_permission')) {
            require_once __DIR__ . '/permissions.php';
            if (isset($connect)) {
                ensure_user_permissions($connect);
            }
        }

        $tabs = comon_mega_search_tabs_config($settings, $role, $user);

        return [
            'user_id' => $userId,
            'account_status' => $accountStatus,
            'role' => $role,
            'panel_base' => $panelBase,
            'url' => rtrim((string) $url, '/') . '/',
            'settings' => $settings,
            'user' => $user,
            'tabs' => $tabs,
            'lang' => is_array($lang ?? null) ? $lang : [],
            'limit' => COMON_MEGA_SEARCH_LIMIT,
        ];
    }
}

if (!function_exists('comon_mega_search_tab_label')) {
    function comon_mega_search_tab_label(string $key, array $lang, $settings = null): string
    {
        $map = [
            'people' => 'People',
            'companies' => 'Companies',
            'projects' => 'Projects',
            'tasks' => 'Tasks',
            'invoices' => 'Invoices',
            'leads' => 'Leads',
        ];
        if ($settings) {
            if ($key === 'projects' && !empty($settings->label_projects_override)) {
                return (string) $settings->label_projects_override;
            }
            if ($key === 'tasks' && !empty($settings->label_tasks_override)) {
                return (string) $settings->label_tasks_override;
            }
            if ($key === 'invoices' && !empty($settings->label_financials_override)) {
                return (string) $settings->label_financials_override;
            }
            if ($key === 'leads' && !empty($settings->label_leads_override)) {
                return (string) $settings->label_leads_override;
            }
        }
        $langKey = $map[$key] ?? ucfirst($key);
        return isset($lang[$langKey]) ? (string) $lang[$langKey] : $langKey;
    }
}

if (!function_exists('comon_mega_search_tabs_config')) {
    /**
     * @return array<string,array{enabled:bool,label:string}>
     */
    function comon_mega_search_tabs_config($settings, string $role, $user = null): array
    {
        $lang = isset($GLOBALS['lang']) && is_array($GLOBALS['lang']) ? $GLOBALS['lang'] : [];
        $defs = [
            'people' => ['menu' => null, 'always' => true],
            'companies' => ['menu' => 'clients', 'always' => false],
            'projects' => ['menu' => 'projects', 'always' => false],
            'tasks' => ['menu' => 'tasks', 'always' => false],
            'invoices' => ['menu' => 'financials', 'always' => false],
            'leads' => ['menu' => 'leads', 'always' => false],
        ];

        $out = [];
        foreach ($defs as $key => $def) {
            $enabled = false;
            if (!empty($def['always'])) {
                $enabled = true;
            } elseif ($def['menu'] !== null && comon_sidebar_menu_item_available($def['menu'], $settings, $role === 'client' ? 'admin' : $role)) {
                $enabled = true;
            }

            if ($enabled && $role === 'staff' && function_exists('has_permission')) {
                switch ($key) {
                    case 'people':
                    case 'companies':
                        $enabled = has_permission('client_view');
                        break;
                    case 'invoices':
                        $enabled = has_permission('milestone_view');
                        break;
                }
            }

            if ($enabled && $role === 'client') {
                if (in_array($key, ['companies', 'leads'], true)) {
                    $enabled = false;
                }
            }

            $out[$key] = [
                'enabled' => $enabled,
                'label' => comon_mega_search_tab_label($key, $lang, $settings),
            ];
        }

        return $out;
    }
}

if (!function_exists('comon_mega_search_placeholder')) {
    function comon_mega_search_placeholder(array $ctx): string
    {
        global $lang;
        return (string) ($lang['Mega search placeholder'] ?? 'Search everything in your workspace...');
    }
}

if (!function_exists('comon_mega_search_like')) {
    function comon_mega_search_like(mysqli $connect, string $q): string
    {
        $safe = mysqli_real_escape_string($connect, trim($q));
        return "'%" . $safe . "%'";
    }
}

if (!function_exists('comon_mega_search_project_match_sql')) {
    function comon_mega_search_project_match_sql(mysqli $connect, string $like, string $projectAlias = 'p'): string
    {
        $p = rtrim($projectAlias, '.') . '.';
        $parts = [
            $p . 'project_title LIKE ' . $like,
        ];
        $parts[] = "EXISTS (
            SELECT 1 FROM users uc
            WHERE (uc.id = {$p}c_id OR uc.id = {$p}main_client_id OR FIND_IN_SET(uc.id, {$p}c_ids))
              AND uc.firstName LIKE {$like}
        )";
        if (comon_db_table_exists($connect, 'project_custom_field_values') && comon_db_table_exists($connect, 'custom_fields')) {
            $parts[] = "EXISTS (
                SELECT 1 FROM project_custom_field_values v
                INNER JOIN custom_fields cf ON cf.id = v.custom_field_id
                WHERE v.project_id = {$p}p_id
                  AND cf.entity_type = 'project'
                  AND COALESCE(cf.is_disabled, 0) = 0
                  AND v.field_value LIKE {$like}
            )";
        }
        return '(' . implode(' OR ', $parts) . ')';
    }
}

if (!function_exists('comon_mega_search_task_match_sql')) {
    function comon_mega_search_task_match_sql(mysqli $connect, string $like, string $taskAlias = 't'): string
    {
        $t = rtrim($taskAlias, '.') . '.';
        $parts = [
            $t . 'title LIKE ' . $like,
            $t . 'description LIKE ' . $like,
            "EXISTS (SELECT 1 FROM projects p2 WHERE p2.p_id = {$t}project_id AND p2.project_title LIKE {$like})",
        ];
        if (comon_db_table_exists($connect, 'task_custom_field_values') && comon_db_table_exists($connect, 'custom_fields')) {
            $parts[] = "EXISTS (
                SELECT 1 FROM task_custom_field_values v
                INNER JOIN custom_fields cf ON cf.id = v.custom_field_id
                WHERE v.task_id = {$t}id
                  AND cf.entity_type = 'task'
                  AND COALESCE(cf.is_disabled, 0) = 0
                  AND v.field_value LIKE {$like}
            )";
        }
        return '(' . implode(' OR ', $parts) . ')';
    }
}

if (!function_exists('comon_mega_search_lead_match_sql')) {
    function comon_mega_search_lead_match_sql(mysqli $connect, string $like, string $leadAlias = 'l'): string
    {
        $l = rtrim($leadAlias, '.') . '.';
        $parts = [
            $l . 'name LIKE ' . $like,
            $l . 'email LIKE ' . $like,
            $l . 'phone LIKE ' . $like,
            $l . 'company LIKE ' . $like,
        ];
        if (comon_db_table_exists($connect, 'leads')) {
            $res = @mysqli_query($connect, 'SHOW COLUMNS FROM leads LIKE \'last_name\'');
            if ($res && mysqli_num_rows($res) > 0) {
                $parts[] = $l . 'last_name LIKE ' . $like;
                $parts[] = "CONCAT({$l}name, ' ', COALESCE({$l}last_name, '')) LIKE {$like}";
            }
        }
        if (comon_db_table_exists($connect, 'lead_custom_field_values') && comon_db_table_exists($connect, 'custom_fields')) {
            $parts[] = "EXISTS (
                SELECT 1 FROM lead_custom_field_values v
                INNER JOIN custom_fields cf ON cf.id = v.custom_field_id
                WHERE v.lead_id = {$l}id
                  AND cf.entity_type = 'lead'
                  AND COALESCE(cf.is_disabled, 0) = 0
                  AND v.field_value LIKE {$like}
            )";
        }
        return '(' . implode(' OR ', $parts) . ')';
    }
}

if (!function_exists('comon_mega_search_people_scope_sql')) {
    function comon_mega_search_people_scope_sql(array $ctx): string
    {
        $role = (string) ($ctx['role'] ?? 'admin');
        $userId = (int) ($ctx['user_id'] ?? 0);

        if ($role === 'admin') {
            return 'u.status = 0 AND u.accountStatus IN (1, 2, 3)';
        }
        if ($role === 'staff') {
            $parts = [];
            if (function_exists('has_permission') && has_permission('client_view')) {
                $parts[] = '(u.accountStatus = 2)';
            }
            if (function_exists('has_permission') && has_permission('staff_view')) {
                $parts[] = '(u.accountStatus = 3)';
            }
            if ($parts === []) {
                return '1=0';
            }
            return 'u.status = 0 AND (' . implode(' OR ', $parts) . ')';
        }
        // Client: staff on shared projects
        if ($userId <= 0) {
            return '1=0';
        }
        return "u.status = 0 AND u.accountStatus = 3 AND EXISTS (
            SELECT 1 FROM projects p
            WHERE (p.c_id = {$userId} OR p.main_client_id = {$userId} OR FIND_IN_SET({$userId}, p.c_ids))
              AND FIND_IN_SET(u.id, p.s_ids)
        )";
    }
}

if (!function_exists('comon_mega_search_project_scope_sql')) {
    function comon_mega_search_project_scope_sql(array $ctx, string $alias = 'p'): string
    {
        $role = (string) ($ctx['role'] ?? 'admin');
        $userId = (int) ($ctx['user_id'] ?? 0);
        $p = rtrim($alias, '.') . '.';

        $base = "{$p}archive = 0 AND {$p}trash != 1";
        if ($role === 'admin') {
            return $base;
        }
        if ($role === 'staff') {
            if (function_exists('has_permission') && has_permission('project_view_all')) {
                return $base;
            }
            return $base . " AND FIND_IN_SET({$userId}, {$p}s_ids)";
        }
        return $base . " AND ({$p}c_id = {$userId} OR {$p}main_client_id = {$userId} OR FIND_IN_SET({$userId}, {$p}c_ids))";
    }
}

if (!function_exists('comon_mega_search_task_scope_sql')) {
    function comon_mega_search_task_scope_sql(array $ctx, string $alias = 't'): string
    {
        $role = (string) ($ctx['role'] ?? 'admin');
        $userId = (int) ($ctx['user_id'] ?? 0);
        $t = rtrim($alias, '.') . '.';

        $active = "({$t}is_archived = 0 OR {$t}is_archived IS NULL)";

        if ($role === 'admin') {
            return $active;
        }
        if ($role === 'staff') {
            return $active . " AND (FIND_IN_SET({$userId}, {$t}assigned_to) OR {$t}user_id = {$userId} OR {$t}creator_id = {$userId})";
        }
        return $active . " AND EXISTS (
            SELECT 1 FROM projects p
            WHERE p.p_id = {$t}project_id
              AND (p.c_id = {$userId} OR p.main_client_id = {$userId} OR FIND_IN_SET({$userId}, p.c_ids))
        )";
    }
}

if (!function_exists('comon_mega_search_invoice_scope_sql')) {
    function comon_mega_search_invoice_scope_sql(array $ctx): string
    {
        $role = (string) ($ctx['role'] ?? 'admin');
        $userId = (int) ($ctx['user_id'] ?? 0);

        if ($role === 'admin' || $role === 'staff') {
            return '1=1';
        }
        return "(m.c_id = {$userId} OR p.c_id = {$userId} OR p.main_client_id = {$userId} OR FIND_IN_SET({$userId}, p.c_ids))";
    }
}

if (!function_exists('comon_mega_search_lead_scope_sql')) {
    function comon_mega_search_lead_scope_sql(array $ctx, string $alias = 'l'): string
    {
        $role = (string) ($ctx['role'] ?? 'admin');
        $userId = (int) ($ctx['user_id'] ?? 0);
        $l = rtrim($alias, '.') . '.';

        if ($role === 'admin') {
            return '1=1';
        }
        if ($role === 'staff') {
            return "(FIND_IN_SET({$userId}, {$l}assigned_to) OR {$l}assigned_to = '{$userId}')";
        }
        return '1=0';
    }
}

if (!function_exists('comon_mega_search_fetch_tab')) {
    /**
     * @return array{count:int,rows:array}
     */
    function comon_mega_search_fetch_tab(string $tab, string $q, array $ctx, int $offset = 0): array
    {
        global $connect, $database;

        $tab = (string) $tab;
        $q = trim($q);
        $offset = max(0, $offset);
        if ($q === '' || empty($ctx['tabs'][$tab]['enabled'])) {
            return ['count' => 0, 'rows' => [], 'offset' => $offset];
        }

        $limit = (int) ($ctx['limit'] ?? COMON_MEGA_SEARCH_LIMIT);

        switch ($tab) {
            case 'people':
                $data = comon_mega_search_fetch_people($connect, $q, $ctx, $limit, $offset);
                break;
            case 'companies':
                $data = comon_mega_search_fetch_companies($connect, $q, $ctx, $limit, $offset);
                break;
            case 'projects':
                $data = comon_mega_search_fetch_projects($connect, $database, $q, $ctx, $limit, $offset);
                break;
            case 'tasks':
                $data = comon_mega_search_fetch_tasks($connect, $database, $q, $ctx, $limit, $offset);
                break;
            case 'invoices':
                $data = comon_mega_search_fetch_invoices($connect, $database, $q, $ctx, $limit, $offset);
                break;
            case 'leads':
                $data = comon_mega_search_fetch_leads($connect, $q, $ctx, $limit, $offset);
                break;
            default:
                $data = ['count' => 0, 'rows' => []];
        }

        $data['offset'] = $offset;
        return $data;
    }
}

if (!function_exists('comon_mega_search_fetch_people')) {
    function comon_mega_search_fetch_people(mysqli $connect, string $q, array $ctx, int $limit, int $offset = 0): array
    {
        $scope = comon_mega_search_people_scope_sql($ctx);
        $searchSql = comon_user_list_search_sql($connect, $q, [
            'alias' => 'u',
            'custom_field_entity_types' => ['client', 'staff', 'admin'],
            'include_company_membership' => true,
        ]);
        if ($searchSql === '') {
            return ['count' => 0, 'rows' => []];
        }

        $where = "WHERE {$scope} {$searchSql}";
        $countSql = "SELECT COUNT(*) AS c FROM users u {$where}";
        $countRes = mysqli_query($connect, $countSql);
        $count = 0;
        if ($countRes) {
            $row = mysqli_fetch_assoc($countRes);
            $count = (int) ($row['c'] ?? 0);
        }

        $sql = "SELECT u.* FROM users u {$where} ORDER BY u.firstName ASC LIMIT "
            . (int) $offset . ', ' . (int) $limit;
        $res = mysqli_query($connect, $sql);
        $rows = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $rows[] = $r;
            }
        }
        return ['count' => $count, 'rows' => $rows];
    }
}

if (!function_exists('comon_mega_search_fetch_companies')) {
    function comon_mega_search_fetch_companies(mysqli $connect, string $q, array $ctx, int $limit, int $offset = 0): array
    {
        if (!comon_db_table_exists($connect, 'client_companies')) {
            return ['count' => 0, 'rows' => []];
        }

        $searchSql = comon_company_list_search_sql($connect, $q, 'c', [
            'include_member_clients' => true,
        ]);
        if ($searchSql === '') {
            return ['count' => 0, 'rows' => []];
        }

        $where = 'WHERE c.deleted_at IS NULL' . $searchSql;
        $countSql = "SELECT COUNT(*) AS c FROM client_companies c {$where}";
        $countRes = mysqli_query($connect, $countSql);
        $count = 0;
        if ($countRes) {
            $row = mysqli_fetch_assoc($countRes);
            $count = (int) ($row['c'] ?? 0);
        }

        $sql = "SELECT c.* FROM client_companies c {$where} ORDER BY c.name ASC LIMIT "
            . (int) $offset . ', ' . (int) $limit;
        $res = mysqli_query($connect, $sql);
        $rows = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $rows[] = $r;
            }
        }
        return ['count' => $count, 'rows' => $rows];
    }
}

if (!function_exists('comon_mega_search_fetch_projects')) {
    function comon_mega_search_fetch_projects(mysqli $connect, $database, string $q, array $ctx, int $limit, int $offset = 0): array
    {
        $like = comon_mega_search_like($connect, $q);
        $scope = comon_mega_search_project_scope_sql($ctx, 'p');
        $match = comon_mega_search_project_match_sql($connect, $like, 'p');
        $where = "WHERE {$scope} AND {$match}";

        $countSql = "SELECT COUNT(DISTINCT p.p_id) AS c FROM projects p {$where}";
        $countRes = $database->query($countSql);
        $countRow = $countRes ? $database->fetchArray($countRes) : ['c' => 0];
        $count = (int) ($countRow['c'] ?? 0);

        $sql = "SELECT DISTINCT p.* FROM projects p {$where} ORDER BY p.p_id DESC LIMIT "
            . (int) $offset . ', ' . (int) $limit;
        $projects = Projects::findBySql($sql);
        $rows = [];
        foreach ($projects as $p) {
            $rows[] = (array) json_decode(json_encode($p), true);
        }
        return ['count' => $count, 'rows' => $rows];
    }
}

if (!function_exists('comon_mega_search_fetch_tasks')) {
    function comon_mega_search_fetch_tasks(mysqli $connect, $database, string $q, array $ctx, int $limit, int $offset = 0): array
    {
        $like = comon_mega_search_like($connect, $q);
        $scope = comon_mega_search_task_scope_sql($ctx, 't');
        $match = comon_mega_search_task_match_sql($connect, $like, 't');
        $where = "WHERE {$scope} AND {$match}";

        $countSql = "SELECT COUNT(*) AS c FROM tasks t {$where}";
        $countRes = $database->query($countSql);
        $countRow = $countRes ? $database->fetchArray($countRes) : ['c' => 0];
        $count = (int) ($countRow['c'] ?? 0);

        require_once __DIR__ . '/all_tasks_table_helper.php';
        $sql = "SELECT " . allTasksTableSelectSql() . "
            FROM tasks t
            " . allTasksTableJoinSql() . "
            {$where}
            ORDER BY t.created_at DESC
            LIMIT " . (int) $offset . ', ' . (int) $limit;
        $res = $database->query($sql);
        $rows = [];
        while ($res && ($row = $database->fetchArray($res))) {
            $rows[] = $row;
        }
        return ['count' => $count, 'rows' => $rows];
    }
}

if (!function_exists('comon_mega_search_fetch_invoices')) {
    function comon_mega_search_fetch_invoices(mysqli $connect, $database, string $q, array $ctx, int $limit, int $offset = 0): array
    {
        $scope = comon_mega_search_invoice_scope_sql($ctx);
        $searchSql = comon_invoice_list_search_sql($connect, $q);
        $where = "WHERE {$scope}" . $searchSql;

        $countSql = "SELECT COUNT(*) AS c FROM milestones m LEFT JOIN projects p ON m.p_id = p.p_id {$where}";
        $countRes = $database->query($countSql);
        $countRow = $countRes ? $database->fetchArray($countRes) : ['c' => 0];
        $count = (int) ($countRow['c'] ?? 0);

        $sql = "SELECT m.*, p.project_title, p.c_id AS project_c_id, m.c_id, m.currency
            FROM milestones m
            LEFT JOIN projects p ON m.p_id = p.p_id
            {$where}
            ORDER BY m.id DESC
            LIMIT " . (int) $offset . ', ' . (int) $limit;
        $res = $database->query($sql);
        $rows = [];
        while ($res && ($row = $database->fetchArray($res))) {
            $rows[] = $row;
        }
        return ['count' => $count, 'rows' => $rows];
    }
}

if (!function_exists('comon_mega_search_fetch_leads')) {
    function comon_mega_search_fetch_leads(mysqli $connect, string $q, array $ctx, int $limit, int $offset = 0): array
    {
        if (!comon_db_table_exists($connect, 'leads')) {
            return ['count' => 0, 'rows' => []];
        }

        $like = comon_mega_search_like($connect, $q);
        $scope = comon_mega_search_lead_scope_sql($ctx, 'l');
        $match = comon_mega_search_lead_match_sql($connect, $like, 'l');
        $where = "WHERE {$scope} AND {$match}";

        $countSql = "SELECT COUNT(*) AS c FROM leads l {$where}";
        $countRes = mysqli_query($connect, $countSql);
        $count = 0;
        if ($countRes) {
            $row = mysqli_fetch_assoc($countRes);
            $count = (int) ($row['c'] ?? 0);
        }

        $sql = "SELECT l.* FROM leads l {$where} ORDER BY l.id DESC LIMIT "
            . (int) $offset . ', ' . (int) $limit;
        $res = mysqli_query($connect, $sql);
        $rows = [];
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $rows[] = $r;
            }
        }
        return ['count' => $count, 'rows' => $rows];
    }
}

if (!function_exists('comon_mega_search_run')) {
    /**
     * @return array<string,array{enabled:bool,count:int,html:string,label:string}>
     */
    function comon_mega_search_run(string $q, string $activeTab, array $ctx): array
    {
        require_once __DIR__ . '/mega_search_render.php';

        $q = trim($q);
        $activeTab = (string) $activeTab;
        if ($activeTab === '' || empty($ctx['tabs'][$activeTab]['enabled'])) {
            foreach ($ctx['tabs'] as $key => $tab) {
                if (!empty($tab['enabled'])) {
                    $activeTab = $key;
                    break;
                }
            }
        }

        $out = [];
        foreach ($ctx['tabs'] as $key => $tab) {
            if (empty($tab['enabled'])) {
                $out[$key] = [
                    'enabled' => false,
                    'label' => (string) $tab['label'],
                    'count' => 0,
                    'html' => '',
                ];
                continue;
            }

            $data = ($q !== '') ? comon_mega_search_fetch_tab($key, $q, $ctx, 0) : ['count' => 0, 'rows' => []];
            $html = ($q !== '') ? mega_search_render_tab($key, $data['rows'], $ctx) : mega_search_render_empty($key, $q, $ctx);
            if ($q !== '' && !empty($data['rows'])) {
                $html .= mega_search_render_load_more_html($key, $q, $ctx, (int) $data['count'], count($data['rows']));
            }

            $out[$key] = [
                'enabled' => true,
                'label' => (string) $tab['label'],
                'count' => (int) $data['count'],
                'html' => $html,
            ];
        }

        return $out;
    }
}
