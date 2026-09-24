<?php
// Define the core paths
// Define them as absolute paths to make sure that require_once works as expected

// DIRECTORY_SEPARATOR is a PHP pre-defined constant
// (\ for Windows, / for Unix)
defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR);

// Get the absolute path to the root directory
defined('SITE_ROOT') ? null : define('SITE_ROOT', dirname(__DIR__));

// Define the includes directory path
defined('LIB_ROOT') ? null : define('LIB_ROOT', SITE_ROOT . DS . 'includes');

// load config file first (Hostinger-safe wrap; does not overwrite generated config.php)
require_once(LIB_ROOT . DS . 'bootstrap_config.php');

// load basic functions next so that everything after can use them
require_once(LIB_ROOT.DS.'functions.php');
if (is_readable(LIB_ROOT . DS . 'pretty_url.php')) {
    require_once(LIB_ROOT . DS . 'pretty_url.php');
}
require_once(LIB_ROOT.DS.'free_edition.php');
require_once(LIB_ROOT.DS.'user_presence.php');
require_once(LIB_ROOT.DS.'icon.php');
require_once(LIB_ROOT.DS.'project_edit_return.php');

// load core objects
require_once(LIB_ROOT.DS.'session.php');
require_once(LIB_ROOT.DS.'database.php');
require_once(LIB_ROOT.DS.'database-object.php');

// load database-related classes
require_once(LIB_ROOT.DS.'user.php');
require_once(LIB_ROOT.DS.'profilePicture.php');
require_once(LIB_ROOT.DS.'projects.php');
require_once(LIB_ROOT.DS.'settings.php');
require_once(LIB_ROOT.DS.'message.php');
require_once(LIB_ROOT.DS.'milestone.php');

// Free edition: no license gate.
$license_valid = true;

$dash_settings = settings::findById(1);
require_once(LIB_ROOT . DS . 'time_tracking_helper.php');
$img_path = 'uploads/system-uploads/';
$url = $dash_settings->url;
if (function_exists('crm_align_public_url_to_request')) {
    $url = crm_align_public_url_to_request((string) $url);
}
$company_name = $dash_settings->company_name;
$syatem_title = $dash_settings->syatem_title;
$login_page_title = $dash_settings->login_page_title;
$copy_rights = $dash_settings->copy_rights;
$time_zone = $dash_settings->time_zone;
$system_email = $dash_settings->system_email;
$system_language = $dash_settings->system_language;

if(isset($session->userId)){
$id=$session->userId;
$userb = User::findById((int)$session->userId);
$user_lang = $userb->user_language;
if($user_lang !== ""){
require_once(LIB_ROOT.DS.'languages/'.$user_lang.'.php');
global $lang;	
} elseif($system_language != ""){
require_once(LIB_ROOT.DS.'languages/'.$system_language.'.php');
global $lang;
}else{
require_once(LIB_ROOT.DS.'languages/en.php');
global $lang;
}
}else{
if($system_language != ""){
require_once(LIB_ROOT.DS.'languages/'.$system_language.'.php');
global $lang;
}else{
require_once(LIB_ROOT.DS.'languages/en.php');
global $lang;
}
}

if (!isset($lang) || !is_array($lang)) {
    $lang = array();
}

$applyMenuLabelOverride = function ($overrideValue, $baseWords, $keys) use (&$lang) {
    $value = trim((string)$overrideValue);
    if ($value === '') {
        return;
    }
    $escapedBaseWords = array();
    foreach ($baseWords as $baseWord) {
        $word = trim((string)$baseWord);
        if ($word !== '') {
            $escapedBaseWords[] = preg_quote($word, '/');
        }
    }
    if (empty($escapedBaseWords)) {
        return;
    }
    usort($escapedBaseWords, static function ($a, $b) {
        return strlen($b) <=> strlen($a);
    });
    $pattern = '/\b(?:' . implode('|', $escapedBaseWords) . ')\b/i';

    foreach ($keys as $key) {
        $current = isset($lang[$key]) ? (string)$lang[$key] : (string)$key;
        if (trim($current) === $value) {
            $lang[$key] = $value;
            continue;
        }
        $updated = preg_replace($pattern, $value, $current);

        if ($updated === $current || trim($updated) === '') {
            $updated = $value;
        }

        $lang[$key] = $updated;
    }
};

$applyGlobalLangReplacement = function ($overrideValue, $baseWords) use (&$lang) {
    $value = trim((string)$overrideValue);
    if ($value === '') {
        return;
    }
    $escapedBaseWords = array();
    foreach ($baseWords as $baseWord) {
        $word = trim((string)$baseWord);
        if ($word !== '') {
            $escapedBaseWords[] = preg_quote($word, '/');
        }
    }
    if (empty($escapedBaseWords)) {
        return;
    }
    usort($escapedBaseWords, static function ($a, $b) {
        return strlen($b) <=> strlen($a);
    });
    $pattern = '/\b(?:' . implode('|', $escapedBaseWords) . ')\b/i';

    foreach ($lang as $langKey => $langValue) {
        if (!is_string($langValue)) {
            continue;
        }
        if (trim($langValue) === $value) {
            continue;
        }
        $lang[$langKey] = preg_replace($pattern, $value, $langValue);
    }
};

$projectOverride = $dash_settings->label_projects_override ?? '';
$taskOverride = $dash_settings->label_tasks_override ?? '';
$clientOverride = $dash_settings->label_clients_override ?? '';
$financialsOverride = $dash_settings->label_financials_override ?? '';
$chattingOverride = $dash_settings->label_chatting_override ?? '';
$privateNotesOverride = $dash_settings->label_private_notes_override ?? '';
$leadsOverride = $dash_settings->label_leads_override ?? '';
$mediaVaultOverride = $dash_settings->label_media_vault_override ?? '';
$customFieldsOverride = $dash_settings->label_custom_fields_override ?? '';

// Global centralized replacements for all language-driven UI labels.
$applyGlobalLangReplacement($projectOverride, array('Projects', 'Project'));
$applyGlobalLangReplacement($taskOverride, array('Tasks', 'Task'));
$applyGlobalLangReplacement($clientOverride, array('Clients', 'Client'));
$applyGlobalLangReplacement($financialsOverride, array('Financials', 'Invoices', 'Invoice'));
$applyGlobalLangReplacement($chattingOverride, array('Chatting', 'Chats', 'Chat'));
$applyGlobalLangReplacement($privateNotesOverride, array('Documents', 'Private Notes', 'Project Notes', 'Notes'));
$applyGlobalLangReplacement($leadsOverride, array('Leads', 'Lead'));
$applyGlobalLangReplacement($mediaVaultOverride, array('Media Vault'));
$applyGlobalLangReplacement($customFieldsOverride, array('Custom Fields', 'Custom Field'));

// Keep explicit menu keys guaranteed for any edge-case fallback usages.
$applyMenuLabelOverride($projectOverride, array('Projects', 'Project'), array('Projects', 'Project'));
$applyMenuLabelOverride($taskOverride, array('Tasks', 'Task'), array('Tasks', 'Task'));
$applyMenuLabelOverride($clientOverride, array('Clients', 'Client'), array('Clients', 'Client'));
$applyMenuLabelOverride($financialsOverride, array('Financials', 'Invoices', 'Invoice'), array('Financials', 'Invoices', 'Invoice'));
$applyMenuLabelOverride($chattingOverride, array('Chatting', 'Chats', 'Chat'), array('Chatting', 'Chats', 'Chat'));
$applyMenuLabelOverride($privateNotesOverride, array('Documents', 'Private Notes', 'Project Notes', 'Notes'), array('Documents', 'Private Notes', 'Project Notes', 'Notes'));
$applyMenuLabelOverride($leadsOverride, array('Leads', 'Lead'), array('Leads', 'Lead'));
$applyMenuLabelOverride($mediaVaultOverride, array('Media Vault'), array('Media Vault'));
$applyMenuLabelOverride($customFieldsOverride, array('Custom Fields', 'Custom Field'), array('Custom Fields', 'Custom Field'));

if (!function_exists('menu_slugify')) {
    function menu_slugify($text, $fallback = 'item') {
        $text = strtolower(trim((string)$text));
        if ($text === '') {
            return $fallback;
        }
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim((string)$text, '-');
        return $text !== '' ? $text : $fallback;
    }
}

if (!function_exists('get_menu_routes_for_role')) {
    function get_menu_routes_for_role($role) {
        global $dash_settings, $lang;

        $definitions = array(
            'projects' => array('field' => 'label_projects_override', 'label_key' => 'Projects', 'default_slug' => 'projects', 'targets' => array('admin' => 'projects.php', 'staff' => 'projects.php', 'client' => 'projects.php')),
            'tasks' => array('field' => 'label_tasks_override', 'label_key' => 'Tasks', 'default_slug' => 'tasks', 'targets' => array('admin' => 'all-tasks.php', 'staff' => 'all-tasks.php', 'client' => 'kanban.php')),
            'clients' => array('field' => 'label_clients_override', 'label_key' => 'Clients', 'default_slug' => 'clients', 'targets' => array('admin' => 'clients.php', 'staff' => 'clients.php')),
            'financials' => array('field' => 'label_financials_override', 'label_key' => 'Financials', 'default_slug' => 'financials', 'targets' => array('admin' => 'invoices.php', 'staff' => 'invoices.php', 'client' => 'invoices.php')),
            'leads' => array('field' => 'label_leads_override', 'label_key' => 'Leads', 'default_slug' => 'leads', 'targets' => array('admin' => 'leads.php', 'staff' => 'leads.php')),
            'media_vault' => array('field' => 'label_media_vault_override', 'label_key' => 'Media Vault', 'default_slug' => 'media-vault', 'targets' => array('admin' => 'media-vault.php', 'staff' => 'media-vault.php')),
            'private_notes' => array('field' => 'label_private_notes_override', 'label_key' => 'Documents', 'default_slug' => 'documents', 'targets' => array('admin' => 'documents.php', 'staff' => 'documents.php', 'client' => 'documents.php')),
            'custom_fields' => array('field' => 'label_custom_fields_override', 'label_key' => 'Custom Fields', 'default_slug' => 'custom-fields', 'targets' => array('admin' => 'custom-fields.php'))
        );

        $routes = array();
        $usedSlugs = array();
        foreach ($definitions as $module => $def) {
            if (empty($def['targets'][$role])) {
                continue;
            }

            $overrideValue = '';
            if ($dash_settings && isset($def['field']) && isset($dash_settings->{$def['field']})) {
                $overrideValue = trim((string)$dash_settings->{$def['field']});
            }
            $labelValue = $overrideValue !== '' ? $overrideValue : ((isset($lang[$def['label_key']]) && trim((string)$lang[$def['label_key']]) !== '') ? (string)$lang[$def['label_key']] : $def['default_slug']);
            $baseSlug = menu_slugify($labelValue, $def['default_slug']);
            $slug = $baseSlug;
            $suffix = 2;
            while (isset($usedSlugs[$slug])) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }
            $usedSlugs[$slug] = true;

            $routes[$module] = array(
                'slug' => $slug,
                'target' => $def['targets'][$role]
            );
        }

        return $routes;
    }
}

if (!function_exists('menu_module_url')) {
    function menu_module_url($role, $module, $query = array()) {
        global $url;
        $routes = get_menu_routes_for_role($role);
        if (empty($routes[$module]['slug'])) {
            $fallback = function_exists('tasksession_role_dashboard_path')
                ? tasksession_role_dashboard_path($role)
                : ($role . '/index.php');
            return rtrim((string)$url, '/') . '/' . $fallback;
        }
        $slugPath = $role . '/' . $routes[$module]['slug'] . '.php';
        if (function_exists('tasksession_pretty_path')) {
            $slugPath = tasksession_pretty_path($slugPath);
        }
        $path = rtrim((string)$url, '/') . '/' . ltrim($slugPath, '/');
        if (!empty($query) && is_array($query)) {
            $qs = http_build_query($query);
            if ($qs !== '') {
                $path .= '?' . $qs;
            }
        }
        return $path;
    }
}

$favicon_image_check = $dash_settings->favicon_image;
if($favicon_image_check){
	$favicon_image = $favicon_image_check;
}else{
	$favicon_image ='favicon.png';
}
$logo_check = $dash_settings->logo;
if($logo_check){
	$logo = $logo_check;
}else{
	$logo = 'client-side-logo.png';
}
$login_page_logo = $dash_settings->login_page_logo;
$mobile_logo = $dash_settings->mobile_logo;
if (function_exists('crm_resolve_system_brand_url')) {
    $login_page_logo_url = crm_resolve_system_brand_url($login_page_logo);
    $sidebar_favicon_url = crm_resolve_sidebar_favicon_url(
        $favicon_image_check ? $favicon_image : '',
        $login_page_logo
    );
    $mobile_logo_url = crm_resolve_system_brand_url($mobile_logo);
    $login_brand_logo_url = crm_resolve_system_brand_url($logo_check ? $logo : '');
    if ($login_brand_logo_url === '') {
        $login_brand_logo_url = crm_default_asset_image_url('assets/images/svg/dark-logo.svg');
    }
    $login_page_image_setting = trim((string) ($dash_settings->login_page_image ?? ''));
    $login_page_image_url = crm_resolve_system_brand_url($login_page_image_setting);
    if ($login_page_image_url === '') {
        $login_page_image_url = crm_default_asset_image_url('assets/images/login.jpg');
    }
}
$system_currency = $dash_settings->system_currency;
	$sc_arr = explode(",",$system_currency);
	$currency_symbol = $sc_arr[1];
	$currency = $sc_arr[0];
	
		date_default_timezone_set($time_zone);
if (!function_exists('crm_sync_offline_session_status')) {
    require_once(LIB_ROOT . DS . 'system_helpers.php');
}
if (!empty($connect) && $connect instanceof mysqli) {
    crm_sync_mysql_timezone($connect, $time_zone);
    crm_sync_offline_session_status($connect);
}
if (isset($database) && isset($database->connection) && $database->connection instanceof mysqli) {
    crm_sync_mysql_timezone($database->connection, $time_zone);
}
?>