<?php
/**
 * Sidebar navigation helpers: addon modes, login redirect, menu order/hide.
 */

if (!function_exists('comon_sidebar_projects_enabled')) {
    function comon_sidebar_projects_enabled($settings)
    {
        return $settings && !empty($settings->module_projects);
    }
}

if (!function_exists('comon_sidebar_tasks_enabled')) {
    function comon_sidebar_tasks_enabled($settings)
    {
        return $settings && !empty($settings->module_tasks);
    }
}

if (!function_exists('comon_sidebar_projects_tasks_enabled')) {
    /** True when either Projects or Tasks module is enabled. */
    function comon_sidebar_projects_tasks_enabled($settings)
    {
        return comon_sidebar_projects_enabled($settings) || comon_sidebar_tasks_enabled($settings);
    }
}

if (!function_exists('comon_profile_default_tab')) {
    function comon_profile_default_tab($settings)
    {
        return comon_sidebar_projects_enabled($settings) ? 'overview' : (comon_sidebar_tasks_enabled($settings) ? 'tasks' : 'activity');
    }
}

if (!function_exists('comon_profile_normalize_tab')) {
    function comon_profile_normalize_tab($tab, $settings, $invoiceEnabled = true, $mediaEnabled = true)
    {
        $tab = (string) $tab;
        if (!comon_sidebar_projects_enabled($settings) && in_array($tab, ['overview', 'projects'], true)) {
            $tab = comon_sidebar_tasks_enabled($settings) ? 'tasks' : 'activity';
        }
        if (!comon_sidebar_tasks_enabled($settings) && $tab === 'tasks') {
            $tab = comon_sidebar_projects_enabled($settings) ? 'overview' : 'activity';
        }
        if (!$invoiceEnabled && $tab === 'invoice') {
            $tab = comon_profile_default_tab($settings);
        }
        if (!$mediaEnabled && $tab === 'media') {
            $tab = comon_profile_default_tab($settings);
        }
        return $tab;
    }
}

if (!function_exists('comon_sidebar_ecommerce_visible_for_user')) {
    function comon_sidebar_ecommerce_visible_for_user($user = null)
    {
        if (!function_exists('comon_ecommerce_module_enabled')) {
            require_once __DIR__ . '/addon_registry.php';
        }
        if (!comon_ecommerce_module_enabled()) {
            return false;
        }
        if (!function_exists('has_permission') || !has_permission('ecommerce_access')) {
            return false;
        }
        return true;
    }
}

if (!function_exists('comon_sidebar_marketing_visible_for_user')) {
    function comon_sidebar_marketing_visible_for_user($user = null)
    {
        if (!function_exists('comon_marketing_module_enabled')) {
            require_once __DIR__ . '/marketing_module_gate.php';
        }
        if (!comon_marketing_module_enabled()) {
            return false;
        }
        if (!function_exists('has_permission') || !has_permission('marketing_access')) {
            return false;
        }
        return true;
    }
}

if (!function_exists('comon_sidebar_ai_visible_for_user')) {
    function comon_sidebar_ai_visible_for_user($user = null)
    {
        if (!function_exists('comon_ai_module_enabled')) {
            $gate = __DIR__ . '/ai_module_gate.php';
            if (!is_readable($gate)) {
                return false;
            }
            require_once $gate;
        }
        if (!function_exists('comon_ai_module_enabled') || !comon_ai_module_enabled()) {
            return false;
        }
        if (!function_exists('has_permission') || !has_permission('ai_access')) {
            return false;
        }
        return true;
    }
}

if (!function_exists('comon_sidebar_visible_addons')) {
    /**
     * @return string[] Ordered addon keys visible to current user.
     */
    function comon_sidebar_visible_addons($user = null)
    {
        $addons = [];
        if (comon_sidebar_ecommerce_visible_for_user($user)) {
            $addons[] = 'ecommerce';
        }
        if (comon_sidebar_marketing_visible_for_user($user)) {
            $addons[] = 'marketing';
        }
        return $addons;
    }
}

if (!function_exists('comon_sidebar_other_crm_modules_enabled')) {
    /**
     * True when any non-addon CRM module toggle is ON (Financials, Leads, etc.).
     */
    function comon_sidebar_other_crm_modules_enabled($settings)
    {
        if (!$settings) {
            return false;
        }
        $moduleFields = [
            'module_invoices',
            'module_lead_board',
            'module_email',
            'module_file_management',
            'module_notes_documents',
            'module_discussions',
            'module_attendance',
            'module_reports',
        ];
        foreach ($moduleFields as $field) {
            if (!empty($settings->$field)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('comon_sidebar_flat_addon_mode')) {
    /**
     * Flat addon links (no parent "Ecommerce" / "Marketing" label) when exactly
     * one addon submenu is actually shown in the sidebar and no other collapsible
     * CRM menus (Clients, Projects, etc.) are visible — including when those
     * items are hidden from Menu settings, not only when their module toggle is off.
     */
    function comon_sidebar_flat_addon_mode($settings, $user = null)
    {
        $role = 'admin';
        if ($user && isset($user->accountStatus) && (int) $user->accountStatus === 3) {
            $role = 'staff';
        }

        $visibleAddons = [];
        foreach (comon_sidebar_visible_addons($user) as $addonKey) {
            if (comon_sidebar_menu_key_accessible($addonKey, $settings, $role, $user)) {
                $visibleAddons[] = $addonKey;
            }
        }
        if (count($visibleAddons) !== 1) {
            return false;
        }

        $otherCollapsible = [
            'clients',
            'staff',
            'projects',
            'tasks',
            'financials',
            'leads',
            'email',
            'attendance',
            'reports',
        ];
        foreach ($otherCollapsible as $key) {
            if (comon_sidebar_menu_key_accessible($key, $settings, $role, $user)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('comon_sidebar_addon_dashboard_path')) {
    function comon_sidebar_addon_dashboard_path($addonKey, $user = null)
    {
        $addonKey = (string) $addonKey;
        if ($addonKey === 'ecommerce') {
            $layoutPath = __DIR__ . '/ecommerce/layout-fragments.php';
            if (!function_exists('tasksession_ecommerce_can')) {
                if (!is_file($layoutPath)) {
                    return '';
                }
                require_once $layoutPath;
            }
            if (!function_exists('tasksession_ecommerce_can')) {
                return '';
            }
            if (tasksession_ecommerce_can('ecommerce_dashboard_view')) {
                return 'ecommerce/dashboard';
            }
            if (tasksession_ecommerce_can('ecommerce_orders_view')) {
                return 'ecommerce/orders';
            }
            if (tasksession_ecommerce_can('ecommerce_products_view')) {
                return 'ecommerce/products';
            }
            return 'ecommerce/dashboard';
        }
        if ($addonKey === 'marketing') {
            if (!is_dir(dirname(__DIR__) . '/marketing')) {
                return '';
            }
            if (function_exists('has_permission') && has_permission('marketing_dashboard_view')) {
                return 'marketing/dashboard';
            }
            if (function_exists('has_permission') && has_permission('marketing_campaigns_view')) {
                return 'marketing/campaigns';
            }
            return 'marketing/dashboard';
        }
        return '';
    }
}

if (!function_exists('comon_login_landing_page_options')) {
    /**
     * Whitelisted post-login pages per role (pretty paths).
     *
     * @param string $role admin|staff
     * @return array<string,string> path => label
     */
    function comon_login_landing_page_options($role = 'admin')
    {
        global $lang;
        $role = ($role === 'staff') ? 'staff' : 'admin';
        if ($role === 'staff') {
            return [
                'staff/dashboard' => $lang['Dashboard'] ?? 'Dashboard',
                'staff/clients' => $lang['Clients'] ?? 'Clients',
                'staff/members' => $lang['Staff'] ?? 'Staff',
                'staff/projects' => $lang['Projects'] ?? 'Projects',
                'staff/all-tasks' => $lang['Tasks'] ?? 'Tasks',
                'staff/invoices' => $lang['Financials'] ?? 'Financials',
                'staff/leads' => $lang['Leads'] ?? 'Leads',
                'staff/media-vault' => $lang['Media Vault'] ?? 'Media Vault',
                'staff/documents' => $lang['Documents'] ?? 'Documents',
                'staff/attendance' => $lang['Attendance'] ?? 'Attendance',
                'ecommerce/dashboard' => ($lang['ecommerce_dashboard'] ?? 'Ecommerce Dashboard'),
                'marketing/dashboard' => ($lang['marketing_dashboard'] ?? 'Marketing Dashboard'),
                'chatting' => $lang['Chatting'] ?? 'Chatting',
                'mail/inbox' => $lang['Inbox'] ?? 'Inbox',
            ];
        }
        return [
            'admin/dashboard' => $lang['Dashboard'] ?? 'Dashboard',
            'admin/clients' => $lang['Clients'] ?? 'Clients',
            'admin/members' => $lang['Admins & Staff'] ?? 'Admins & Staff',
            'admin/projects' => $lang['Projects'] ?? 'Projects',
            'admin/all-tasks' => $lang['Tasks'] ?? 'Tasks',
            'admin/invoices' => $lang['Financials'] ?? 'Financials',
            'admin/leads' => $lang['Leads'] ?? 'Leads',
            'admin/reports' => $lang['Reports'] ?? 'Reports',
            'admin/system-settings' => $lang['System Settings'] ?? 'System Settings',
            'admin/menu-settings' => $lang['Menu settings'] ?? 'Menu settings',
            'admin/media-vault' => $lang['Media Vault'] ?? 'Media Vault',
            'admin/documents' => $lang['Documents'] ?? 'Documents',
            'admin/attendance' => $lang['Attendance'] ?? 'Attendance',
            'ecommerce/dashboard' => ($lang['ecommerce_dashboard'] ?? 'Ecommerce Dashboard'),
            'marketing/dashboard' => ($lang['marketing_dashboard'] ?? 'Marketing Dashboard'),
            'chatting' => $lang['Chatting'] ?? 'Chatting',
            'mail/inbox' => $lang['Inbox'] ?? 'Inbox',
        ];
    }
}

if (!function_exists('tasksession_login_landing_page_options')) {
    function tasksession_login_landing_page_options($role = 'admin')
    {
        return comon_login_landing_page_options($role);
    }
}

if (!function_exists('comon_normalize_login_landing_page')) {
    function comon_normalize_login_landing_page($path, $role = 'admin')
    {
        $path = str_replace('\\', '/', trim((string) $path));
        $path = ltrim($path, '/');
        if ($path === 'admin/menu-label-overrides.php' || $path === 'admin/menu-label-overrides') {
            $path = 'admin/menu-settings';
        }
        if (function_exists('tasksession_normalize_landing_storage_path')) {
            $path = tasksession_normalize_landing_storage_path($path, $role);
        } elseif (preg_match('#^(admin|staff|client)/index\.php$#i', $path, $m)) {
            $path = strtolower($m[1]) . '/dashboard';
        }
        if ($path === '' || strpos($path, '..') !== false) {
            return '';
        }
        // Allow pretty paths or legacy .php that map into options
        if (!preg_match('#^[a-z0-9_\-/]+(?:\.php)?$#i', $path)) {
            return '';
        }
        if (substr($path, -4) === '.php' && function_exists('tasksession_pretty_path')) {
            $path = tasksession_pretty_path($path);
        }
        $options = comon_login_landing_page_options($role);
        return isset($options[$path]) ? $path : '';
    }
}

if (!function_exists('tasksession_normalize_login_landing_page')) {
    function tasksession_normalize_login_landing_page($path, $role = 'admin')
    {
        return comon_normalize_login_landing_page($path, $role);
    }
}

if (!function_exists('comon_get_login_landing_page')) {
    function comon_get_login_landing_page($settings, $role = 'admin')
    {
        $role = ($role === 'staff') ? 'staff' : 'admin';
        $default = $role . '/dashboard';
        if (!$settings) {
            return $default;
        }
        $field = ($role === 'staff') ? 'staff_login_landing_page' : 'admin_login_landing_page';
        $saved = trim((string) ($settings->$field ?? ''));
        if ($saved === '') {
            return $default;
        }
        $normalized = comon_normalize_login_landing_page($saved, $role);
        return $normalized !== '' ? $normalized : $default;
    }
}

if (!function_exists('tasksession_get_login_landing_page')) {
    function tasksession_get_login_landing_page($settings, $role = 'admin')
    {
        return comon_get_login_landing_page($settings, $role);
    }
}

if (!function_exists('comon_sidebar_menu_key_paths')) {
    /**
     * Map sidebar menu keys to relative page paths (pretty).
     *
     * @param string $role admin|staff
     * @return array<string,string>
     */
    function comon_sidebar_menu_key_paths($role = 'admin')
    {
        $role = ($role === 'staff') ? 'staff' : 'admin';
        $paths = [
            'dashboard' => $role . '/dashboard',
            'clients' => $role . '/clients',
            'staff' => $role . '/members',
            'projects' => $role . '/projects',
            'tasks' => $role . '/all-tasks',
            'financials' => $role . '/invoices',
            'leads' => $role . '/leads',
            'ecommerce' => 'ecommerce/dashboard',
            'email' => 'mail/inbox',
            'marketing' => 'marketing/dashboard',
            'chatting' => 'chatting',
            'ai_assistant' => 'ai/assistant',
            'media_vault' => $role . '/media-vault',
            'documents' => $role . '/documents',
            'attendance' => $role . '/attendance',
        ];
        if ($role === 'admin') {
            $paths['reports'] = 'admin/reports';
            $paths['settings'] = 'admin/system-settings';
        }
        return $paths;
    }
}

if (!function_exists('comon_is_dashboard_menu_hidden')) {
    function comon_is_dashboard_menu_hidden($settings, $role = 'admin')
    {
        return comon_sidebar_is_menu_hidden('dashboard', $settings);
    }
}

if (!function_exists('comon_sidebar_menu_key_accessible')) {
    function comon_sidebar_menu_key_accessible($key, $settings, $role = 'admin', $user = null)
    {
        $key = (string) $key;
        if (comon_sidebar_is_menu_hidden($key, $settings)) {
            return false;
        }
        if (!comon_sidebar_menu_item_available($key, $settings, $role)) {
            return false;
        }
        if ($key === 'ecommerce' && !comon_sidebar_ecommerce_visible_for_user($user)) {
            return false;
        }
        if ($key === 'marketing' && !comon_sidebar_marketing_visible_for_user($user)) {
            return false;
        }
        if ($key === 'ai_assistant' && !comon_sidebar_ai_visible_for_user($user)) {
            return false;
        }
        if ($role === 'staff' && function_exists('has_permission')) {
            switch ($key) {
                case 'clients':
                    return has_permission('client_view');
                case 'staff':
                    return has_permission('staff_view');
                case 'financials':
                    return has_permission('milestone_view');
                case 'email':
                    return has_permission('email_inbox_view');
                case 'attendance':
                    if ($user && isset($user->attendance_disabled) && (int) $user->attendance_disabled === 1) {
                        return false;
                    }
                    return has_permission('attendance_view');
            }
        }
        return true;
    }
}

if (!function_exists('comon_get_first_accessible_menu_path')) {
    /**
     * First visible sidebar menu path for role (skips hidden/unavailable items).
     */
    function comon_get_first_accessible_menu_path($settings, $role = 'admin', $user = null)
    {
        $role = ($role === 'staff') ? 'staff' : 'admin';
        $order = comon_sidebar_get_menu_order($settings, $role);
        $keyPaths = comon_sidebar_menu_key_paths($role);
        foreach ($order as $key) {
            if ($key === 'dashboard') {
                continue;
            }
            if (!comon_sidebar_menu_key_accessible($key, $settings, $role, $user)) {
                continue;
            }
            if (isset($keyPaths[$key])) {
                return $keyPaths[$key];
            }
        }
        return ($role === 'staff') ? 'staff/clients' : 'admin/clients';
    }
}

if (!function_exists('comon_resolve_login_landing_page')) {
    /**
     * Effective landing page; never returns dashboard path when dashboard menu is hidden.
     */
    function comon_resolve_login_landing_page($settings, $role = 'admin', $user = null)
    {
        $role = ($role === 'staff') ? 'staff' : 'admin';
        $dashboardPath = $role . '/dashboard';
        $landing = comon_get_login_landing_page($settings, $role);

        if (!comon_is_dashboard_menu_hidden($settings, $role)) {
            return $landing;
        }
        if ($landing === $dashboardPath || $landing === ($role . '/index.php')) {
            return comon_get_first_accessible_menu_path($settings, $role, $user);
        }
        return $landing;
    }
}

if (!function_exists('tasksession_resolve_login_landing_page')) {
    function tasksession_resolve_login_landing_page($settings, $role = 'admin', $user = null)
    {
        return comon_resolve_login_landing_page($settings, $role, $user);
    }
}

if (!function_exists('comon_guard_hidden_dashboard')) {
    /**
     * Redirect away from role dashboard when dashboard menu is hidden.
     */
    function comon_guard_hidden_dashboard($settings, $role = 'admin', $user = null)
    {
        if (!comon_is_dashboard_menu_hidden($settings, $role)) {
            return;
        }
        global $url;
        $target = comon_resolve_login_landing_page($settings, $role, $user);
        if ($target === ($role . '/dashboard') || $target === ($role . '/index.php')) {
            $target = comon_get_first_accessible_menu_path($settings, $role, $user);
        }
        redirectTo($url . $target);
        exit;
    }
}

if (!function_exists('tasksession_guard_hidden_dashboard')) {
    function tasksession_guard_hidden_dashboard($settings, $role = 'admin', $user = null)
    {
        comon_guard_hidden_dashboard($settings, $role, $user);
    }
}

if (!function_exists('comon_post_login_redirect_path')) {
    /**
     * Relative redirect path after login (no base URL).
     *
     * @param object $user User object with accountStatus
     * @param object|false $settings settings row
     * @return string
     */
    function comon_post_login_redirect_path($user, $settings = null)
    {
        if (!$user) {
            return '';
        }
        $status = (int) ($user->accountStatus ?? 0);
        if ($status === 2) {
            return 'client/dashboard';
        }
        if ($settings === null || $settings === false) {
            $settings = settings::findById(1);
        }
        $rolePrefix = ($status === 3) ? 'staff' : 'admin';
        if ($status !== 1 && $status !== 3) {
            return '';
        }
        $mainLanding = comon_resolve_login_landing_page($settings, $rolePrefix, $user);
        if (comon_sidebar_projects_tasks_enabled($settings)) {
            return $mainLanding;
        }
        $addons = comon_sidebar_visible_addons($user);
        if (!empty($addons)) {
            foreach ($addons as $addonKey) {
                $addonPath = comon_sidebar_addon_dashboard_path($addonKey, $user);
                if ($addonPath !== '') {
                    return $addonPath;
                }
            }
        }
        return $mainLanding;
    }
}

if (!function_exists('tasksession_post_login_redirect_path')) {
    function tasksession_post_login_redirect_path($user, $settings = null)
    {
        return comon_post_login_redirect_path($user, $settings);
    }
}

if (!function_exists('comon_sidebar_menu_registry')) {
    /**
     * Default top-level sidebar menu keys per role.
     *
     * @param string $role admin|staff
     * @return array<string,array{label:string,protected:bool}>
     */
    function comon_sidebar_menu_registry($role = 'admin')
    {
        global $lang;
        $role = ($role === 'staff') ? 'staff' : 'admin';
        if ($role === 'staff') {
            return [
                'dashboard' => ['label' => $lang['Dashboard'] ?? 'Dashboard', 'protected' => false],
                'clients' => ['label' => $lang['Clients'] ?? 'Clients', 'protected' => false],
                'staff' => ['label' => $lang['Staff'] ?? 'Staff', 'protected' => false],
                'projects' => ['label' => $lang['Projects'] ?? 'Projects', 'protected' => false],
                'tasks' => ['label' => $lang['Tasks'] ?? 'Tasks', 'protected' => false],
                'financials' => ['label' => $lang['Financials'] ?? 'Financials', 'protected' => false],
                'leads' => ['label' => $lang['Leads'] ?? 'Leads', 'protected' => false],
                'email' => ['label' => $lang['Emails'] ?? 'Emails', 'protected' => false],
                'marketing' => ['label' => $lang['Marketing'] ?? 'Marketing', 'protected' => false],
                'ecommerce' => ['label' => $lang['ecommerce'] ?? 'Ecommerce', 'protected' => false],
                'chatting' => ['label' => $lang['Chatting'] ?? 'Chatting', 'protected' => false],
                'media_vault' => ['label' => $lang['Media Vault'] ?? 'Media Vault', 'protected' => false],
                'documents' => ['label' => $lang['Documents'] ?? 'Documents', 'protected' => false],
                'attendance' => ['label' => $lang['Attendance'] ?? 'Attendance', 'protected' => false],
                'ai_assistant' => ['label' => $lang['AI Workspace'] ?? 'AI Workspace', 'protected' => false],
            ];
        }
        return [
            'dashboard' => ['label' => $lang['Dashboard'] ?? 'Dashboard', 'protected' => false],
            'clients' => ['label' => $lang['Clients'] ?? 'Clients', 'protected' => false],
            'staff' => ['label' => $lang['Admins & Staff'] ?? 'Admins & Staff', 'protected' => false],
            'projects' => ['label' => $lang['Projects'] ?? 'Projects', 'protected' => false],
            'tasks' => ['label' => $lang['Tasks'] ?? 'Tasks', 'protected' => false],
            'financials' => ['label' => $lang['Financials'] ?? 'Financials', 'protected' => false],
            'leads' => ['label' => $lang['Leads'] ?? 'Leads', 'protected' => false],
            'ecommerce' => ['label' => $lang['ecommerce'] ?? 'Ecommerce', 'protected' => false],
            'email' => ['label' => $lang['Emails'] ?? 'Emails', 'protected' => false],
            'marketing' => ['label' => $lang['Marketing'] ?? 'Marketing', 'protected' => false],
            'chatting' => ['label' => $lang['Chatting'] ?? 'Chatting', 'protected' => false],
            'media_vault' => ['label' => $lang['Media Vault'] ?? 'Media Vault', 'protected' => false],
            'documents' => ['label' => $lang['Documents'] ?? 'Documents', 'protected' => false],
            'attendance' => ['label' => $lang['Attendance'] ?? 'Attendance', 'protected' => false],
            'reports' => ['label' => $lang['Reports'] ?? 'Reports', 'protected' => false],
            'ai_assistant' => ['label' => $lang['AI Workspace'] ?? 'AI Workspace', 'protected' => false],
            'settings' => ['label' => $lang['Control panel'] ?? 'Control panel', 'protected' => false],
        ];
    }
}

if (!function_exists('comon_sidebar_default_menu_order')) {
    function comon_sidebar_default_menu_order($role = 'admin')
    {
        return array_keys(comon_sidebar_menu_registry($role));
    }
}

if (!function_exists('comon_sidebar_parse_json_list')) {
    function comon_sidebar_parse_json_list($raw)
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $item) {
            $key = trim((string) $item);
            if ($key !== '') {
                $out[] = $key;
            }
        }
        return $out;
    }
}

if (!function_exists('comon_sidebar_get_menu_order')) {
    function comon_sidebar_get_menu_order($settings, $role = 'admin')
    {
        $defaults = comon_sidebar_default_menu_order($role);
        $saved = comon_sidebar_parse_json_list($settings->sidebar_menu_order ?? '');
        if (empty($saved)) {
            return comon_sidebar_pin_ai_workspace_order($defaults, $role);
        }
        $ordered = [];
        foreach ($saved as $key) {
            if (in_array($key, $defaults, true) && !in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }
        foreach ($defaults as $key) {
            if (!in_array($key, $ordered, true)) {
                $ordered[] = $key;
            }
        }
        // Keep AI Workspace immediately above Control panel (admin) / at end (staff).
        return comon_sidebar_pin_ai_workspace_order($ordered, $role);
    }
}

if (!function_exists('comon_sidebar_pin_ai_workspace_order')) {
    /**
     * @param string[] $ordered
     * @return string[]
     */
    function comon_sidebar_pin_ai_workspace_order(array $ordered, $role = 'admin')
    {
        if (!in_array('ai_assistant', $ordered, true)) {
            return $ordered;
        }
        $ordered = array_values(array_filter($ordered, static function ($k) {
            return $k !== 'ai_assistant';
        }));
        if ($role === 'admin' && in_array('settings', $ordered, true)) {
            $out = [];
            foreach ($ordered as $key) {
                if ($key === 'settings') {
                    $out[] = 'ai_assistant';
                }
                $out[] = $key;
            }
            return $out;
        }
        $ordered[] = 'ai_assistant';
        return $ordered;
    }
}

if (!function_exists('comon_sidebar_get_hidden_keys')) {
    function comon_sidebar_get_hidden_keys($settings)
    {
        return comon_sidebar_parse_json_list($settings->sidebar_menu_hidden ?? '');
    }
}

if (!function_exists('comon_sidebar_hide_only_menu_keys')) {
    /**
     * Sidebar items that can be hidden but never reordered.
     *
     * @return string[]
     */
    function comon_sidebar_hide_only_menu_keys()
    {
        return ['create_new'];
    }
}

if (!function_exists('comon_sidebar_hide_only_menu_registry')) {
    /**
     * @return array<string,array{label:string,note:string}>
     */
    function comon_sidebar_hide_only_menu_registry()
    {
        global $lang;
        return [
            'create_new' => [
                'label' => $lang['Create New'] ?? 'Create New',
                'note' => $lang['Sidebar create new fixed note'] ?? 'Fixed at top of sidebar. Hide only, cannot reorder.',
            ],
        ];
    }
}

if (!function_exists('comon_header_visibility_keys')) {
    /**
     * Top header elements stored in sidebar_menu_hidden (hide-only).
     *
     * @return string[]
     */
    function comon_header_visibility_keys()
    {
        return ['header_search', 'header_language', 'header_date', 'header_ask_ai'];
    }
}

if (!function_exists('comon_header_visibility_registry')) {
    /**
     * @return array<string,array{label:string,note:string}>
     */
    function comon_header_visibility_registry()
    {
        global $lang;
        return [
            'header_search' => [
                'label' => $lang['Header Search'] ?? 'Header Search',
                'note' => $lang['Header search fixed note'] ?? 'Search bar in the top header.',
            ],
            'header_language' => [
                'label' => $lang['Header Language'] ?? 'Header Language',
                'note' => $lang['Header language fixed note'] ?? 'Language selector in the top header.',
            ],
            'header_date' => [
                'label' => $lang['Header Date'] ?? 'Header Date',
                'note' => $lang['Header date fixed note'] ?? 'Current date shown in the top header.',
            ],
            'header_ask_ai' => [
                'label' => $lang['Header Ask AI'] ?? 'Ask AI',
                'note' => $lang['Header ask ai fixed note'] ?? 'Ask AI button in the top header.',
            ],
        ];
    }
}

if (!function_exists('comon_sidebar_is_create_new_hidden')) {
    function comon_sidebar_is_create_new_hidden($settings)
    {
        return comon_sidebar_is_menu_hidden('create_new', $settings);
    }
}

if (!function_exists('comon_header_search_enabled')) {
    function comon_header_search_enabled($settings)
    {
        return !comon_sidebar_is_menu_hidden('header_search', $settings);
    }
}

if (!function_exists('comon_header_language_enabled')) {
    function comon_header_language_enabled($settings)
    {
        return !comon_sidebar_is_menu_hidden('header_language', $settings);
    }
}

if (!function_exists('comon_header_date_enabled')) {
    function comon_header_date_enabled($settings)
    {
        return !comon_sidebar_is_menu_hidden('header_date', $settings);
    }
}

if (!function_exists('comon_header_ask_ai_enabled')) {
    function comon_header_ask_ai_enabled($settings)
    {
        return !comon_sidebar_is_menu_hidden('header_ask_ai', $settings);
    }
}

if (!function_exists('comon_sidebar_is_menu_hidden')) {
    function comon_sidebar_is_menu_hidden($key, $settings)
    {
        $registry = comon_sidebar_menu_registry('admin');
        if (isset($registry[$key]) && !empty($registry[$key]['protected'])) {
            return false;
        }
        $staffRegistry = comon_sidebar_menu_registry('staff');
        if (isset($staffRegistry[$key]) && !empty($staffRegistry[$key]['protected'])) {
            return false;
        }
        return in_array((string) $key, comon_sidebar_get_hidden_keys($settings), true);
    }
}

if (!function_exists('comon_sidebar_apply_menu_layout')) {
    /**
     * Echo menu blocks in configured order, skipping hidden keys.
     *
     * @param array<string,string> $menuBlocks key => HTML
     * @param object|false $settings
     * @param string $role admin|staff
     * @param object|null $user
     */
    function comon_sidebar_apply_menu_layout(array $menuBlocks, $settings, $role = 'admin', $user = null)
    {
        $order = comon_sidebar_get_menu_order($settings, $role);
        foreach ($order as $key) {
            if (!isset($menuBlocks[$key]) || $menuBlocks[$key] === '') {
                continue;
            }
            if (comon_sidebar_is_menu_hidden($key, $settings)) {
                continue;
            }
            echo $menuBlocks[$key];
        }
    }
}

if (!function_exists('comon_sidebar_menu_item_available')) {
    /**
     * Whether a menu key is currently available (module/permission) for reorder UI.
     */
    function comon_sidebar_menu_item_available($key, $settings, $role = 'admin')
    {
        $key = (string) $key;
        switch ($key) {
            case 'dashboard':
                return true;
            case 'projects':
                return comon_sidebar_projects_enabled($settings);
            case 'tasks':
                return comon_sidebar_tasks_enabled($settings);
            case 'financials':
                return $settings && !empty($settings->module_invoices);
            case 'leads':
                return $settings && !empty($settings->module_lead_board);
            case 'email':
                return $settings && !empty($settings->module_email);
            case 'media_vault':
                return $settings && !empty($settings->module_file_management);
            case 'documents':
                return $settings && !empty($settings->module_notes_documents);
            case 'attendance':
                return $settings && !empty($settings->module_attendance);
            case 'reports':
                return $role === 'admin' && $settings && (!isset($settings->module_reports) || !empty($settings->module_reports));
            case 'ecommerce':
                return comon_sidebar_ecommerce_visible_for_user();
            case 'marketing':
                return comon_sidebar_marketing_visible_for_user();
            case 'ai_assistant':
                return comon_sidebar_ai_visible_for_user();
            case 'settings':
                return $role === 'admin';
            default:
                return true;
        }
    }
}

if (!function_exists('comon_sidebar_ensure_menu_config_columns')) {
    function comon_sidebar_ensure_menu_config_columns($connect)
    {
        if (!function_exists('crm_ensure_settings_columns')) {
            require_once __DIR__ . '/system_helpers.php';
        }
        crm_ensure_settings_columns($connect, [
            'sidebar_menu_order' => "ALTER TABLE settings ADD COLUMN sidebar_menu_order TEXT NULL DEFAULT NULL",
            'sidebar_menu_hidden' => "ALTER TABLE settings ADD COLUMN sidebar_menu_hidden TEXT NULL DEFAULT NULL",
            'admin_login_landing_page' => "ALTER TABLE settings ADD COLUMN admin_login_landing_page VARCHAR(255) NULL DEFAULT 'admin/index.php'",
            'staff_login_landing_page' => "ALTER TABLE settings ADD COLUMN staff_login_landing_page VARCHAR(255) NULL DEFAULT 'staff/index.php'",
        ]);
    }
}
