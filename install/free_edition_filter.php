<?php
/**
 * Free edition installer helpers — omit Pro-only CREATE TABLE statements on fresh install.
 * Never DROP TABLE here (filter only). Core auth tables like login_attempts are kept.
 */

if (!defined('TASKSESSION_FREE_EDITION')) {
    define('TASKSESSION_FREE_EDITION', true);
}

/**
 * Table names exclusive to Pro / unused Free modules.
 *
 * @return string[]
 */
function installer_free_premium_table_names()
{
    return array(
        // Chat / messaging
        'messages',
        'group_chats',
        'group_chat_members',
        'group_chat_messages',
        'group_chat_reads',
        'group_chat_email_notifications',
        'discussion_chat_email_notifications',
        'one_to_one_chat_email_notifications',
        'task_chat_email_notifications',
        'task_chat',
        'task_chat_reads',
        'discussion_reads',
        'chat_email_context',
        'chat_email_user_preference',
        'chat_message_reactions',
        'chat_message_stars',
        'chat_message_pins',
        'chat_push_throttle',
        'user_chat_notification_prefs',
        // Push notifications
        'push_subscriptions',
        'push_notification_payload_cache',
        // Email engine
        'email_accounts',
        'email_messages',
        'email_attachments',
        'email_deleted_messages',
        'email_signatures',
        'email_account_shared_users',
        'email_labels',
        'email_message_labels',
        'email_thread_lead_links',
        'email_tracking_history',
        'email_report_logs',
        'email_report_queue',
        // Invoicing extras / companies (milestones + invoice_items kept; UI Pro-locked)
        'invoice_reminders_sent',
        'client_stripe_customers',
        'client_payment_methods',
        'client_companies',
        'client_company_members',
        'client_company_links',
        'client_company_pins',
        'company_activity_log',
        'company_custom_field_values',
        // Quotations
        'quotations',
        'quotation_items',
        'quotation_status_history',
        // Media vault / Drive
        'media_vault_project_links',
        'media_vault_project_link_users',
        'media_vault_profile_shares',
        'media_vault_link_shares',
        'google_drive_settings',
        'mv_upload_sessions',
        'shared_folders',
        'shared_files',
        // Google Calendar / Login SSO
        'google_calendar_settings',
        'google_calendar_tokens',
        'google_calendar_sync_log',
        'google_login_settings',
        // AI / Zapier / Meet integrations
        'integration_chatgpt',
        'integration_chatgpt_logs',
        'integration_google_meet',
        'integration_zapier',
        'integration_zapier_logs',
        'integration_webhook_deliveries',
        // License
        'wc_am_settings',
        'wc_am_activations',
        'wc_am_cache',
        'wc_am_addon_activations',
        // Marketing
        'marketing_settings',
        'marketing_recipients',
        'marketing_templates',
        'marketing_audiences',
        'marketing_campaigns',
        'marketing_campaign_recipients',
        'marketing_email_logs',
        'marketing_audience_members',
        'marketing_contact_tags',
        'marketing_contact_tag_members',
        'marketing_contact_notes',
        // Leads CRM (Forms Pro-adjacent; not shipped on Free)
        'leads',
        'lead_statuses',
        'lead_sources',
        'lead_activities',
        'lead_activity_meta',
        'lead_comments',
        'lead_comment_mentions',
        'lead_custom_field_values',
        // Attendance
        'attendance_shifts',
        'attendance_shift_assignments',
        'attendance_records',
        'attendance_punches',
        'attendance_leave_types',
        'attendance_leave_balances',
        'attendance_leave_requests',
        'attendance_holidays',
        'attendance_weekly_off_rules',
        'attendance_regularization_requests',
        'attendance_policies',
        'attendance_payroll_exports',
        'attendance_audit_logs',
        'attendance_overtime',
        'attendance_user_day_overrides',
    );
}

/**
 * @param string $table
 * @return bool
 */
function installer_free_is_premium_table($table)
{
    $table = strtolower((string) $table);
    if ($table === '') {
        return false;
    }
    $premium = installer_free_premium_table_names();
    if (in_array($table, $premium, true)) {
        return true;
    }
    // Prefix safety for Pro-only modules
    $prefixes = array(
        'attendance_',
        'media_vault_',
        'marketing_',
        'wc_am_',
        'group_chat',
        'task_chat',
        'ai_',
        'tasksession_ecommerce_',
        'email_',
        'google_calendar_',
        'integration_',
        'lead_',
        'quotation_',
        'push_',
        'chat_',
    );
    foreach ($prefixes as $prefix) {
        if (strpos($table, $prefix) === 0) {
            return true;
        }
    }
    return $table === 'google_drive_settings'
        || $table === 'google_login_settings'
        || $table === 'mv_upload_sessions'
        || $table === 'shared_folders'
        || $table === 'shared_files'
        || $table === 'api_tokens'
        || $table === 'messages'
        || $table === 'leads'
        || $table === 'quotations';
}

/**
 * @param string $sql
 * @return bool true if this SQL should be skipped on Free fresh install
 */
function installer_free_should_skip_sql($sql)
{
    $sql = (string) $sql;
    if ($sql === '') {
        return false;
    }
    if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-zA-Z0-9_]+)`?/i', $sql, $m)) {
        return installer_free_is_premium_table($m[1]);
    }
    if (preg_match('/ALTER\s+TABLE\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $am)) {
        return installer_free_is_premium_table($am[1]);
    }
    // Skip seeds / DML against Pro-only tables (CREATE was already filtered out)
    if (preg_match('/\b(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $dm)) {
        return installer_free_is_premium_table($dm[1]);
    }
    return false;
}

/**
 * Seed/migration labels that must not run on Free (even when SQL targets shared tables).
 *
 * @param string $label
 * @return bool
 */
function installer_free_should_skip_label($label)
{
    $label = strtolower((string) $label);
    if ($label === '') {
        return false;
    }
    $needles = array(
        'seed-marketing',
        'seed-lead',
        'seed-attendance',
        'attendance-',
        'wc_am',
        'gdrive',
        'google-drive',
        'google_drive',
        'google-calendar',
        'google_calendar',
        'google-login',
        'google_login',
        'media-vault',
        'media_vault',
        'ecommerce-',
        'seed-ecommerce',
        'chatgpt',
        'zapier',
        'quotation',
        'push-subscription',
        'push_subscription',
    );
    foreach ($needles as $needle) {
        if (strpos($label, $needle) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Remove FOREIGN KEY constraints that reference Free-omitted tables
 * so dependent CREATE TABLE statements still succeed.
 *
 * @param string $sql
 * @return string
 */
function installer_free_strip_premium_foreign_keys($sql)
{
    $sql = (string) $sql;
    if ($sql === '' || !preg_match('/\bFOREIGN\s+KEY\b/i', $sql)) {
        return $sql;
    }
    // Drop CONSTRAINT name FOREIGN KEY (...) REFERENCES premium_table (...)
    $sql = preg_replace_callback(
        '/,?\s*(?:CONSTRAINT\s+`?[a-zA-Z0-9_]+`?\s+)?FOREIGN\s+KEY\s*\([^)]+\)\s*REFERENCES\s+`?([a-zA-Z0-9_]+)`?\s*\([^)]+\)(?:\s+ON\s+DELETE\s+[A-Z\s]+)?(?:\s+ON\s+UPDATE\s+[A-Z\s]+)?/i',
        static function ($m) {
            $ref = isset($m[1]) ? strtolower($m[1]) : '';
            if ($ref !== '' && function_exists('installer_free_is_premium_table') && installer_free_is_premium_table($ref)) {
                return '';
            }
            return $m[0];
        },
        $sql
    );
    // Clean leftover double commas / trailing commas before closing paren
    $sql = preg_replace('/,\s*,+/', ',', $sql);
    $sql = preg_replace('/,\s*\)/', ')', $sql);
    return $sql;
}

/**
 * @param array $statements
 * @return array
 */
function installer_free_filter_sql_list(array $statements)
{
    $out = array();
    foreach ($statements as $sql) {
        if (installer_free_should_skip_sql($sql)) {
            continue;
        }
        $out[] = $sql;
    }
    return $out;
}

/**
 * Cron job seeds that are Pro-only.
 */
function installer_free_is_premium_cron_path($filePath)
{
    $filePath = strtolower(str_replace('\\', '/', (string) $filePath));
    $banned = array(
        'cron/payment_reminders.php',
        'cron/cron_recurring_invoices.php',
        'cron/attendance_auto_absent.php',
        'cron/task_chat_batch_emails.php',
        'cron/discussion_chat_batch_emails.php',
        'cron/group_chat_batch_emails.php',
        'cron/one_to_one_chat_batch_emails.php',
        'cron/g-calender-cron.php',
        'vendor/google/gdrive/cron_refresh_tokens.php',
        'cron/email_sync.php',
        'cron/email_report.php',
        'cron/email_tracking_update.php',
    );
    foreach ($banned as $b) {
        if (strpos($filePath, $b) !== false) {
            return true;
        }
    }
    return false;
}
