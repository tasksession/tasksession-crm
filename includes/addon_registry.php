<?php
/**
 * Paid addon product registry (WooCommerce API Manager product IDs).
 */

function comon_get_addon_definitions()
{
    // Free edition: UI metadata only — no WooCommerce product IDs / license endpoints.
    return [
        'marketing' => [
            'id' => 'marketing',
            'title' => 'Email Marketing',
            'subtitle' => 'Alternative to Mailchimp & Klaviyo',
            'subtitle_lang' => 'Marketing addon subtitle',
            'buy_url' => 'https://www.tasksession.com/pricing/',
            'settings_field' => 'module_marketing',
            'icon' => 'marketing',
        ],
        'woocommerce' => [
            'id' => 'woocommerce',
            'title' => 'WooCommerce',
            'subtitle' => 'Sync orders, customers & products',
            'subtitle_lang' => 'WooCommerce addon subtitle',
            'buy_url' => 'https://www.tasksession.com/pricing/',
            'settings_field' => 'module_ecommerce',
            'icon' => 'woocommerce',
        ],
        'ai' => [
            'id' => 'ai',
            'title' => 'AI Assistant',
            'subtitle' => 'Native AI for your workspace',
            'subtitle_lang' => 'AI addon subtitle',
            'buy_url' => 'https://www.tasksession.com/pricing/',
            'settings_field' => 'module_ai',
            'icon' => 'ai',
        ],
    ];
}

/**
 * Ensure settings.module_* columns for registered addons exist.
 * Safe to call on every bootstrap before Settings::findById (missing columns fatal SELECT).
 *
 * @param mysqli|null $connect
 * @return void
 */
function comon_ensure_addon_module_columns($connect = null)
{
    static $done = false;
    if ($done) {
        return;
    }
    if ($connect === null) {
        global $connect;
    }
    if (!($connect instanceof mysqli)) {
        return;
    }

    foreach (comon_get_addon_definitions() as $def) {
        $field = trim((string) ($def['settings_field'] ?? ''));
        if ($field === '' || !preg_match('/^module_[a-z0-9_]+$/', $field)) {
            continue;
        }
        $esc = $connect->real_escape_string($field);
        $res = @$connect->query("SHOW COLUMNS FROM `settings` LIKE '{$esc}'");
        $exists = $res && $res->num_rows > 0;
        if ($res) {
            $res->free();
        }
        if ($exists) {
            continue;
        }
        @$connect->query(
            "ALTER TABLE `settings` ADD COLUMN `{$field}` TINYINT(1) NOT NULL DEFAULT " . ($field === 'module_ai' ? '0' : '1')
        );
    }

    $done = true;
}

function comon_addon_is_free($addonKey)
{
    $def = comon_get_addon_definition((string) $addonKey);

    return $def && !empty($def['free']);
}

/**
 * @return bool
 */
function comon_addon_free_is_active($addonKey)
{
    $def = comon_get_addon_definition((string) $addonKey);
    if (!$def || empty($def['free'])) {
        return false;
    }
    $field = trim((string) ($def['settings_field'] ?? ''));
    if ($field === '' || !in_array($field, comon_addon_allowed_settings_fields(), true)) {
        return false;
    }

    global $database;
    if (!isset($database) || !$database) {
        return false;
    }

    $result = $database->query('SELECT `' . $field . '` FROM settings WHERE id = 1 LIMIT 1');
    if (!$result || $database->numRows($result) === 0) {
        return false;
    }
    $row = $database->fetchArray($result);

    return !empty($row[$field]);
}

/**
 * Settings columns that addons may toggle (whitelist for direct SQL updates).
 *
 * @return string[]
 */
function comon_addon_allowed_settings_fields()
{
    $fields = [];
    foreach (comon_get_addon_definitions() as $def) {
        $field = trim((string) ($def['settings_field'] ?? ''));
        if ($field !== '') {
            $fields[] = $field;
        }
    }

    return array_values(array_unique($fields));
}

/**
 * Enable/disable a free addon via its settings module flag.
 *
 * @return bool
 */
function comon_addon_set_module_flag($addonKey, $enabled)
{
    $def = comon_get_addon_definition((string) $addonKey);
    if (!$def) {
        return false;
    }
    $field = trim((string) ($def['settings_field'] ?? ''));
    if ($field === '' || !in_array($field, comon_addon_allowed_settings_fields(), true)) {
        return false;
    }

    global $database;
    if (!isset($database) || !$database) {
        return false;
    }

    global $connect;
    if (function_exists('comon_ensure_addon_module_columns')) {
        comon_ensure_addon_module_columns($connect ?? null);
    }

    $value = $enabled ? 1 : 0;
    $result = $database->query('UPDATE settings SET `' . $field . '` = ' . $value . ' WHERE id = 1 LIMIT 1');
    if (!$result) {
        return false;
    }

    global $dash_settings;
    if (isset($dash_settings) && is_object($dash_settings) && property_exists($dash_settings, $field)) {
        $dash_settings->{$field} = $value;
    }

    return true;
}

/**
 * Whether the Ecommerce module flag is enabled in settings (id=1).
 */
function comon_ecommerce_module_enabled()
{
    if (!is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ecommerce' . DIRECTORY_SEPARATOR . 'dashboard.php')
        && !is_dir(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ecommerce')) {
        return false;
    }
    if (!function_exists('comon_addon_is_enabled')) {
        require_once __DIR__ . '/addon_module_gate.php';
    }

    return comon_addon_is_enabled('woocommerce');
}

/**
 * @return string[]
 */
function comon_ecommerce_permission_keys()
{
    return [
        'ecommerce_access',
        'ecommerce_dashboard_view',
        'ecommerce_orders_view',
        'ecommerce_orders_edit',
        'ecommerce_orders_sync',
        'ecommerce_customers_view',
        'ecommerce_customers_edit',
        'ecommerce_products_view',
        'ecommerce_products_edit',
        'ecommerce_settings_manage',
        'ecommerce_logs_view',
        'ecommerce_queue_manage',
        'ecommerce_field_mapping_manage',
        'ecommerce_custom_fields_manage',
        'ecommerce_webhooks_manage',
        'ecommerce_cron_manage',
        'ecommerce_api_manage',
        'ecommerce_debug_manage',
    ];
}

/**
 * @return string[]
 */
function comon_marketing_permission_keys()
{
    return [
        'marketing_access',
        'marketing_dashboard_view',
        'marketing_campaigns_view',
        'marketing_campaigns_edit',
        'marketing_campaigns_send',
        'marketing_templates_manage',
        'marketing_recipients_manage',
        'marketing_settings_manage',
    ];
}

/**
 * Grant all Marketing permissions to a role (used on addon activation / admin heal).
 *
 * @param int $roleId
 * @param mysqli|null $connect
 * @return bool
 */
function comon_grant_marketing_permissions_to_role($roleId, $connect = null)
{
    $roleId = (int) $roleId;
    if ($roleId <= 0) {
        return false;
    }
    if ($connect === null) {
        global $connect;
    }
    if (!$connect instanceof mysqli) {
        return false;
    }

    $ok = true;
    foreach (comon_marketing_permission_keys() as $permKey) {
        $escKey = $connect->real_escape_string($permKey);
        if (!$connect->query(
            "INSERT INTO role_permissions (role_id, permission_key, value) VALUES ({$roleId}, '{$escKey}', 1)
             ON DUPLICATE KEY UPDATE value = 1"
        )) {
            $ok = false;
        }
    }

    return $ok;
}

/**
 * Grant all Ecommerce permissions to a role (used on addon activation / admin heal).
 *
 * @param int $roleId
 * @param mysqli|null $connect
 * @return bool
 */
function comon_grant_ecommerce_permissions_to_role($roleId, $connect = null)
{
    $roleId = (int) $roleId;
    if ($roleId <= 0) {
        return false;
    }
    if ($connect === null) {
        global $connect;
    }
    if (!$connect instanceof mysqli) {
        return false;
    }

    $ok = true;
    foreach (comon_ecommerce_permission_keys() as $permKey) {
        $escKey = $connect->real_escape_string($permKey);
        if (!$connect->query(
            "INSERT INTO role_permissions (role_id, permission_key, value) VALUES ({$roleId}, '{$escKey}', 1)
             ON DUPLICATE KEY UPDATE value = 1"
        )) {
            $ok = false;
        }
    }

    return $ok;
}

/**
 * @return string[]
 */
function comon_ai_permission_keys()
{
    return [
        'ai_access',
        'ai_settings_manage',
        'ai_workspace_search',
        'ai_speech',
        'ai_voice_chat',
        'ai_voice',
        'ai_upload',
        'ai_generate_images',
        'ai_image_gen',
        'ai_email_tools',
        'ai_email_send',
        'ai_email_auto_send',
        'ai_task_tools',
        'ai_project_tools',
        'ai_client_tools',
        'ai_invoice_tools',
        'ai_lead_tools',
        'ai_lead_send',
        'ai_chat_history',
        'ai_pin_chats',
        'ai_archive_chats',
        'ai_temporary_chat',
        'ai_agents',
        'ai_agents_manage',
        'ai_fields_view',
        'ai_daily_brief',
        'ai_deep_search',
        'ai_external_integration',
        'ai_workspace_memory_view',
        'ai_workspace_memory_create',
        'ai_workspace_memory_edit',
        'ai_workspace_memory_delete',
        'ai_workspace_memory_manage',
    ];
}

function comon_grant_ai_permissions_to_role($roleId, $connect = null)
{
    $roleId = (int) $roleId;
    if ($roleId <= 0) {
        return false;
    }
    if ($connect === null) {
        global $connect;
    }
    if (!$connect instanceof mysqli) {
        return false;
    }
    $ok = true;
    foreach (comon_ai_permission_keys() as $permKey) {
        $escKey = $connect->real_escape_string($permKey);
        if (!$connect->query(
            "INSERT INTO role_permissions (role_id, permission_key, value) VALUES ({$roleId}, '{$escKey}', 1)
             ON DUPLICATE KEY UPDATE value = 1"
        )) {
            $ok = false;
        }
    }
    return $ok;
}

/**
 * Ensure primary admin roles have Ecommerce permissions when the module is active.
 *
 * @param mysqli $connect
 * @param int $roleId
 * @return bool True if permissions were seeded
 */
function comon_maybe_seed_ecommerce_permissions($connect, $roleId)
{
    if (!comon_ecommerce_module_enabled()) {
        return false;
    }
    if (!isset($_SESSION['accountStatus']) || (int) $_SESSION['accountStatus'] !== 1) {
        return false;
    }
    if (!$connect instanceof mysqli) {
        return false;
    }

    $roleId = (int) $roleId;
    $roleIds = array_values(array_unique(array_filter([1, $roleId])));
    $seeded = false;

    foreach ($roleIds as $rid) {
        $esc = $connect->real_escape_string('ecommerce_access');
        $res = $connect->query(
            "SELECT value FROM role_permissions WHERE role_id = {$rid} AND permission_key = '{$esc}' LIMIT 1"
        );
        $hasAccess = $res && ($row = $res->fetch_assoc()) && (int) ($row['value'] ?? 0) === 1;
        if ($res) {
            $res->free();
        }
        if ($hasAccess) {
            continue;
        }
        if (comon_grant_ecommerce_permissions_to_role($rid, $connect)) {
            $seeded = true;
        }
    }

    return $seeded;
}

function comon_addon_is_released($addonKey)
{
    $def = comon_get_addon_definition($addonKey);
    if (!$def) {
        return false;
    }

    return empty($def['coming_soon']);
}

function comon_ecommerce_addon_released()
{
    return comon_addon_is_released('woocommerce');
}

function comon_get_addon_definition($addonKey)
{
    $defs = comon_get_addon_definitions();
    $addonKey = (string) $addonKey;

    return isset($defs[$addonKey]) ? $defs[$addonKey] : null;
}

/**
 * Free edition: paid-addon product IDs are not shipped.
 *
 * @param string $addonKey
 * @return int[]
 */
function comon_get_addon_product_ids($addonKey)
{
    return [];
}
