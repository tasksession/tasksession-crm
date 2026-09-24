<?php
session_start();
if (ob_get_level() === 0) {
    ob_start();
}
error_reporting(E_ALL ^ E_NOTICE);
mysqli_report(MYSQLI_REPORT_OFF);
@set_time_limit(300);
@ini_set('memory_limit', '512M');

require_once __DIR__ . '/installer_sql_helper.php';
$migrationWarnings = [];

register_shutdown_function(static function () {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Installation</title></head><body style="font-family:system-ui,sans-serif;padding:2rem;max-width:560px;margin:0 auto;">';
    echo '<h1>Setup step interrupted</h1>';
    echo '<p>The server timed out or hit an error while finishing database setup. Your database may already be ready.</p>';
    echo '<p><a href="system.php">Continue setup</a> &nbsp;|&nbsp; <a href="complete.php">Open completion page</a></p>';
    echo '<pre style="font-size:12px;color:#666;margin-top:1.5rem;">' . htmlspecialchars($err['message'] ?? 'Unknown error', ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '</body></html>';
});

// Check if database is already configured
if (!file_exists('../includes/config.php')) {
    header('Location: index.php');
    exit;
}

// Function to handle redirects
function safe_redirect($url, $message = '') {
    if (!empty($message)) {
        $_SESSION['redirect_message'] = $message;
    }
    if (function_exists('install_safe_redirect')) {
        install_safe_redirect($url);
    }
    header('Location: ' . $url);
    exit;
}

// Function to check if installation is complete
function is_installation_complete() {
    global $connect;
    $check_settings = mysqli_query($connect, "SELECT COUNT(*) as count FROM settings");
    if ($check_settings) {
        $settings_count = mysqli_fetch_assoc($check_settings)['count'];
        return $settings_count > 0;
    }
    return false;
}

$configPath = __DIR__ . '/../includes/config.php';
$configCode = (string) @file_get_contents($configPath);
if (trim($configCode) === '' || !preg_match('/define\s*\(\s*["\']DB_SERVER["\']/i', $configCode)) {
    die('includes/config.php is missing database settings. <a href="index.php">Run the installer again</a>.');
}
if (!defined('TS_INSTALL_BOOTSTRAP')) {
    define('TS_INSTALL_BOOTSTRAP', true);
}
try {
    include $configPath;
} catch (Throwable $e) {
    die('Cannot load includes/config.php: ' . htmlspecialchars($e->getMessage()) . '<br><a href="index.php">Re-run installer</a> (use a DB password without unescaped quotes if you edited config by hand).');
}
date_default_timezone_set('GMT');

// Get current URL
$actual_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$website_url = dirname(dirname($actual_link)).'/';

// Database connection
$connect = mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
if (!$connect) {
    die("Error: Unable to connect to MySQL. <br> Please <a href='index.php'>Click Here</a> to install/Add Database Details!");
}

// Already finished — skip heavy migrations (fixes 500 then refresh on repeat visits).
if (is_installation_complete()) {
    safe_redirect('complete.php', 'Installation already completed');
}

// Run supplemental migrations once per install session (avoids duplicate work on refresh).
// Fresh install: index.php already created full schema — skip this heavy block (prevents timeout/blank page on shared hosting).
if (empty($_SESSION['install_system_migrations_done'])) {
    $skipSupplementalMigrations = !empty($_SESSION['install_fresh_schema_from_index'])
        || installer_fresh_schema_ready_for_admin_setup($connect);
    if ($skipSupplementalMigrations) {
        unset($_SESSION['install_fresh_schema_from_index']);
        $_SESSION['install_system_migrations_done'] = true;
    } else {
$calendarInstallerQueries = [
    "CREATE TABLE IF NOT EXISTS calendar_events (
        id INT(11) NOT NULL AUTO_INCREMENT,
        task_id INT(11) DEFAULT NULL,
        user_id INT(11) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        event_date DATE DEFAULT NULL,
        start_datetime DATETIME DEFAULT NULL,
        end_datetime DATETIME DEFAULT NULL,
        location_label VARCHAR(255) DEFAULT NULL,
        team_label VARCHAR(120) DEFAULT NULL,
        participants_json LONGTEXT DEFAULT NULL,
        source_type ENUM('local','google') NOT NULL DEFAULT 'local',
        google_event_id VARCHAR(191) DEFAULT NULL,
        google_calendar_id VARCHAR(191) DEFAULT NULL,
        google_etag VARCHAR(191) DEFAULT NULL,
        is_waiting_list TINYINT(1) NOT NULL DEFAULT 0,
        waiting_position INT(11) NOT NULL DEFAULT 0,
        sync_state ENUM('pending','synced','error') NOT NULL DEFAULT 'pending',
        last_synced_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_calendar_events_user_date (user_id, event_date),
        KEY idx_calendar_events_waiting (is_waiting_list, waiting_position),
        KEY idx_calendar_events_task (task_id),
        KEY idx_calendar_events_sync (sync_state, last_synced_at),
        UNIQUE KEY uk_calendar_events_google_event (google_event_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
    "CREATE TABLE IF NOT EXISTS calendar_task_schedule (
        id INT(11) NOT NULL AUTO_INCREMENT,
        task_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        schedule_date DATE DEFAULT NULL,
        planned_seconds INT UNSIGNED NULL DEFAULT NULL COMMENT 'Planner-entered seconds for this assignee schedule row',
        is_waiting_list TINYINT(1) NOT NULL DEFAULT 0,
        waiting_position INT(11) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_calendar_task_user (task_id, user_id),
        KEY idx_calendar_task_schedule_date (schedule_date),
        KEY idx_calendar_task_waiting (is_waiting_list, waiting_position)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
    "CREATE TABLE IF NOT EXISTS google_calendar_settings (
        id INT(11) NOT NULL AUTO_INCREMENT,
        client_id VARCHAR(255) DEFAULT NULL,
        client_secret TEXT DEFAULT NULL,
        redirect_uri VARCHAR(255) DEFAULT NULL,
        default_calendar_id VARCHAR(191) DEFAULT NULL,
        two_way_sync TINYINT(1) NOT NULL DEFAULT 1,
        auto_sync_enabled TINYINT(1) NOT NULL DEFAULT 1,
        task_sync_enabled TINYINT(1) NOT NULL DEFAULT 0,
        sync_window_days INT(11) NOT NULL DEFAULT 45,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
    "CREATE TABLE IF NOT EXISTS google_calendar_tokens (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        google_email VARCHAR(191) DEFAULT NULL,
        access_token LONGTEXT DEFAULT NULL,
        refresh_token LONGTEXT DEFAULT NULL,
        token_type VARCHAR(60) DEFAULT NULL,
        expires_at DATETIME DEFAULT NULL,
        scope TEXT DEFAULT NULL,
        calendar_id VARCHAR(191) DEFAULT 'primary',
        sync_token TEXT DEFAULT NULL,
        last_sync_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_google_calendar_tokens_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
    "CREATE TABLE IF NOT EXISTS google_calendar_sync_log (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        direction ENUM('push','pull') NOT NULL,
        record_type ENUM('task','event') NOT NULL DEFAULT 'event',
        local_record_id INT(11) DEFAULT NULL,
        google_event_id VARCHAR(191) DEFAULT NULL,
        status ENUM('success','error') NOT NULL,
        message TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_google_sync_user_date (user_id, created_at),
        KEY idx_google_sync_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
    "ALTER TABLE google_calendar_settings ADD COLUMN IF NOT EXISTS task_sync_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_sync_enabled"
];
install_run_queries($connect, $calendarInstallerQueries, $migrationWarnings);

$themeFontInstallerQueries = [
    "CREATE TABLE IF NOT EXISTS `custom_fonts` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `family_css` varchar(120) NOT NULL,
        `font_weight` int NOT NULL DEFAULT 400,
        `is_italic` tinyint(1) NOT NULL DEFAULT 0,
        `file_path` varchar(255) NOT NULL,
        `original_filename` varchar(255) NOT NULL DEFAULT '',
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_family` (`family_css`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `ui_font_source` varchar(20) NOT NULL DEFAULT 'default' COMMENT 'default|google|websafe|custom' AFTER `button_border_radius`",
    "ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `ui_google_font_family` varchar(120) NOT NULL DEFAULT 'Lato' AFTER `ui_font_source`",
    "ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `ui_google_font_weights` varchar(80) NOT NULL DEFAULT '400;700;900' AFTER `ui_google_font_family`",
    "ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `ui_websafe_stack` varchar(255) NOT NULL DEFAULT '' AFTER `ui_google_font_weights`",
    "ALTER TABLE `theme_settings` ADD COLUMN IF NOT EXISTS `ui_custom_font_id` int unsigned DEFAULT NULL AFTER `ui_websafe_stack`",
];
install_run_queries($connect, $themeFontInstallerQueries, $migrationWarnings);

// Team groups + client companies (includes/migrations/3.23.sql) — same as fresh install in install/index.php
$teamGroupsClientCompaniesInstallerQueries = [
    "CREATE TABLE IF NOT EXISTS `staff_team_groups` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL DEFAULT '',
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_staff_team_groups_name` (`name`(64)),
  KEY `idx_staff_team_groups_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_staff_team_groups_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `staff_team_group_members` (
  `group_id` INT UNSIGNED NOT NULL,
  `user_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_id`, `user_id`),
  KEY `idx_staff_team_group_members_user` (`user_id`),
  CONSTRAINT `fk_staff_team_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `staff_team_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_staff_team_group_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `client_companies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(191) NOT NULL DEFAULT '',
  `logo_path` VARCHAR(512) NULL DEFAULT NULL,
  `vat_number` VARCHAR(64) NOT NULL DEFAULT '',
  `phone` VARCHAR(64) NOT NULL DEFAULT '',
  `email` VARCHAR(191) NOT NULL DEFAULT '',
  `website` VARCHAR(512) NOT NULL DEFAULT '',
  `currency` VARCHAR(64) NOT NULL DEFAULT '',
  `address` VARCHAR(512) NOT NULL DEFAULT '',
  `city` VARCHAR(128) NOT NULL DEFAULT '',
  `state` VARCHAR(128) NOT NULL DEFAULT '',
  `zip` VARCHAR(32) NOT NULL DEFAULT '',
  `country` VARCHAR(128) NOT NULL DEFAULT '',
  `billing_address` VARCHAR(512) NOT NULL DEFAULT '',
  `billing_street` VARCHAR(512) NOT NULL DEFAULT '',
  `billing_city` VARCHAR(128) NOT NULL DEFAULT '',
  `billing_state` VARCHAR(128) NOT NULL DEFAULT '',
  `billing_zip` VARCHAR(32) NOT NULL DEFAULT '',
  `billing_country` VARCHAR(128) NOT NULL DEFAULT '',
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_client_companies_name` (`name`(64)),
  KEY `idx_client_companies_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_client_companies_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `client_company_members` (
  `company_id` INT UNSIGNED NOT NULL,
  `user_id` INT NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`, `user_id`),
  KEY `idx_client_company_members_user` (`user_id`),
  CONSTRAINT `fk_client_company_members_company` FOREIGN KEY (`company_id`) REFERENCES `client_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_client_company_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `client_company_links` (
  `company_id` INT UNSIGNED NOT NULL,
  `linked_company_id` INT UNSIGNED NOT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`, `linked_company_id`),
  KEY `idx_client_company_links_linked` (`linked_company_id`),
  CONSTRAINT `fk_client_company_links_parent` FOREIGN KEY (`company_id`) REFERENCES `client_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_client_company_links_child` FOREIGN KEY (`linked_company_id`) REFERENCES `client_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_client_company_links_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `company_activity_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `actor_user_id` INT DEFAULT NULL,
  `action` VARCHAR(64) NOT NULL DEFAULT '',
  `summary` VARCHAR(512) NOT NULL DEFAULT '',
  `meta_json` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company_activity_company_created` (`company_id`, `created_at`),
  KEY `idx_company_activity_actor` (`actor_user_id`),
  CONSTRAINT `fk_company_activity_company` FOREIGN KEY (`company_id`) REFERENCES `client_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_company_activity_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `client_company_pins` (
  `company_id` INT UNSIGNED NOT NULL,
  `pinned_by` INT DEFAULT NULL,
  `pinned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_id`),
  KEY `idx_client_company_pins_pinned_at` (`pinned_at`),
  CONSTRAINT `fk_client_company_pins_company` FOREIGN KEY (`company_id`) REFERENCES `client_companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_client_company_pins_user` FOREIGN KEY (`pinned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
install_run_queries($connect, $teamGroupsClientCompaniesInstallerQueries, $migrationWarnings);

// Company custom field values (includes/migrations/3.31.sql) — repair / partial installs
$companyCustomFieldValuesInstallerQueries = [
    "CREATE TABLE IF NOT EXISTS `company_custom_field_values` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT UNSIGNED NOT NULL,
  `custom_field_id` INT(11) NOT NULL,
  `field_value` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_custom_field` (`company_id`, `custom_field_id`),
  KEY `idx_company_cf_values_company` (`company_id`),
  KEY `idx_company_cf_values_field` (`custom_field_id`),
  CONSTRAINT `fk_company_cf_values_company` FOREIGN KEY (`company_id`) REFERENCES `client_companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_company_cf_values_field` FOREIGN KEY (`custom_field_id`) REFERENCES `custom_fields` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
install_run_queries($connect, $companyCustomFieldValuesInstallerQueries, $migrationWarnings);

// Chat email policy + user preference (includes/migrations/3.24.sql) — same as fresh install in install/index.php
$chatEmail324InstallerQueries = [
    "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_notify_admin` TINYINT(1) NOT NULL DEFAULT 1 AFTER `chat_refresh_interval`",
    "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_notify_staff` TINYINT(1) NOT NULL DEFAULT 1 AFTER `chat_email_notify_admin`",
    "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_notify_client` TINYINT(1) NOT NULL DEFAULT 1 AFTER `chat_email_notify_staff`",
    "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_notify_mention` TINYINT(1) NOT NULL DEFAULT 1 AFTER `chat_email_notify_client`",
    "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `presence_toast_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `chat_email_notify_mention`",
    "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `presence_beep_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `presence_toast_enabled`",
    "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `presence_staff_toast_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `presence_beep_enabled`",
    "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `browser_push_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `presence_staff_toast_enabled`",
    "CREATE TABLE IF NOT EXISTS `chat_email_context` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `context_type` enum('project','group','task') NOT NULL,
        `context_id` int(10) unsigned NOT NULL,
        `o_admin` tinyint(1) DEFAULT NULL,
        `o_staff` tinyint(1) DEFAULT NULL,
        `o_client` tinyint(1) DEFAULT NULL,
        `o_mention` tinyint(1) DEFAULT NULL,
        `updated_at` int(10) unsigned NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_ctx` (`context_type`,`context_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `chat_email_user_preference` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` int(10) unsigned NOT NULL,
        `context_type` enum('project','group','task') NOT NULL,
        `context_id` int(10) unsigned NOT NULL,
        `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
        `updated_at` int(10) unsigned NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_ctx` (`user_id`,`context_type`,`context_id`),
        KEY `idx_ctx` (`context_type`,`context_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
install_run_queries($connect, $chatEmail324InstallerQueries, $migrationWarnings);

$chatPinStarReactInstallerQueries = [
    "CREATE TABLE IF NOT EXISTS `chat_message_reactions` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `chat_type` ENUM('one_to_one','group','discussion','task') NOT NULL,
      `message_id` INT(11) NOT NULL,
      `user_id` INT(11) NOT NULL,
      `emoji` VARCHAR(16) NOT NULL,
      `created_at` INT(11) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_reaction` (`chat_type`,`message_id`,`user_id`,`emoji`),
      KEY `idx_msg` (`chat_type`,`message_id`),
      KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `chat_message_stars` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `chat_type` ENUM('one_to_one','group','discussion','task') NOT NULL,
      `message_id` INT(11) NOT NULL,
      `user_id` INT(11) NOT NULL,
      `created_at` INT(11) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_star` (`chat_type`,`message_id`,`user_id`),
      KEY `idx_msg` (`chat_type`,`message_id`),
      KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `chat_message_pins` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `chat_type` ENUM('one_to_one','group','discussion','task') NOT NULL,
      `context_id` INT(11) NOT NULL,
      `message_id` INT(11) NOT NULL,
      `pinned_by` INT(11) NOT NULL,
      `pinned_at` INT(11) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_pin_context` (`chat_type`,`context_id`),
      KEY `idx_message` (`message_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
install_run_queries($connect, $chatPinStarReactInstallerQueries, $migrationWarnings);

// Attendance module schema (mirrors includes/migrations/3.19.sql — tables + settings + seeds + policy/shift columns)
$attendanceModuleInstallerQueries = [
    "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_attendance` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Attendance module' AFTER `module_email`",
    "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_ip_restriction` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable login IP restriction module (independent of attendance)' AFTER `module_attendance`",
    "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `global_allowed_ips` TEXT NULL DEFAULT NULL COMMENT 'Optional comma-separated IPs merged with per-user allowlist when restriction is on' AFTER `module_ip_restriction`",
    "CREATE TABLE IF NOT EXISTS `attendance_shifts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT DEFAULT NULL,
        `branch_id` INT DEFAULT NULL,
        `name` VARCHAR(100) NOT NULL,
        `code` VARCHAR(30) DEFAULT NULL,
        `shift_type` ENUM('fixed','rotational','night','half_day','flexible') NOT NULL DEFAULT 'fixed',
        `start_time` TIME DEFAULT NULL,
        `end_time` TIME DEFAULT NULL,
        `full_day_hours` DECIMAL(5,2) NOT NULL DEFAULT 8.00,
        `half_day_hours` DECIMAL(5,2) NOT NULL DEFAULT 4.00,
        `grace_minutes` INT NOT NULL DEFAULT 0,
        `salary_currency` VARCHAR(32) NOT NULL DEFAULT '',
        `allowed_weekdays_json` TEXT NULL,
        `weekday_time_overrides_json` TEXT NULL,
        `use_policy_threshold_override` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT DEFAULT NULL,
        `updated_by` INT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_attendance_shifts_active` (`is_active`),
        INDEX `idx_attendance_shifts_branch` (`branch_id`),
        INDEX `idx_attendance_shifts_type` (`shift_type`),
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_shift_assignments` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `shift_id` INT NOT NULL,
        `effective_from` DATE NOT NULL,
        `effective_to` DATE DEFAULT NULL,
        `assigned_by` INT DEFAULT NULL,
        `reason` VARCHAR(255) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_shift_assign_user` (`user_id`),
        INDEX `idx_shift_assign_dates` (`effective_from`, `effective_to`),
        INDEX `idx_shift_assign_shift` (`shift_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`shift_id`) REFERENCES `attendance_shifts`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_records` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `employee_id` VARCHAR(50) DEFAULT NULL,
        `department` VARCHAR(100) DEFAULT NULL,
        `designation` VARCHAR(100) DEFAULT NULL,
        `reporting_manager_id` INT DEFAULT NULL,
        `branch_id` INT DEFAULT NULL,
        `company_id` INT DEFAULT NULL,
        `shift_id` INT DEFAULT NULL,
        `attendance_date` DATE NOT NULL,
        `first_check_in` DATETIME DEFAULT NULL,
        `last_check_out` DATETIME DEFAULT NULL,
        `work_minutes` INT NOT NULL DEFAULT 0,
        `break_minutes` INT NOT NULL DEFAULT 0,
        `late_minutes` INT NOT NULL DEFAULT 0,
        `early_leave_minutes` INT NOT NULL DEFAULT 0,
        `overtime_minutes` INT NOT NULL DEFAULT 0,
        `status` ENUM('present','absent','late','half_day','early_exit','on_leave','weekly_off','holiday','work_from_home','on_duty','missed_punch') NOT NULL DEFAULT 'present',
        `source` ENUM('web','mobile','admin_manual','system_auto') NOT NULL DEFAULT 'web',
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `checkin_latitude` DECIMAL(10,7) DEFAULT NULL,
        `checkin_longitude` DECIMAL(10,7) DEFAULT NULL,
        `checkout_latitude` DECIMAL(10,7) DEFAULT NULL,
        `checkout_longitude` DECIMAL(10,7) DEFAULT NULL,
        `location_radius_meters` INT DEFAULT NULL,
        `location_note` VARCHAR(255) DEFAULT NULL,
        `policy_snapshot` TEXT DEFAULT NULL,
        `locked` TINYINT(1) NOT NULL DEFAULT 0,
        `approved_by` INT DEFAULT NULL,
        `approved_at` DATETIME DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `updated_by` INT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_attendance_user_date` (`user_id`, `attendance_date`),
        INDEX `idx_attendance_status` (`status`),
        INDEX `idx_attendance_date` (`attendance_date`),
        INDEX `idx_attendance_branch_date` (`branch_id`, `attendance_date`),
        INDEX `idx_attendance_department_date` (`department`, `attendance_date`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`reporting_manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`shift_id`) REFERENCES `attendance_shifts`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_punches` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `attendance_record_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `punch_type` ENUM('in','out','break_start','break_end') NOT NULL DEFAULT 'in',
        `punch_time` DATETIME NOT NULL,
        `source` ENUM('web','mobile','admin_manual') NOT NULL DEFAULT 'web',
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `latitude` DECIMAL(10,7) DEFAULT NULL,
        `longitude` DECIMAL(10,7) DEFAULT NULL,
        `location_text` VARCHAR(255) DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_punch_record` (`attendance_record_id`),
        INDEX `idx_punch_user_time` (`user_id`, `punch_time`),
        INDEX `idx_punch_type` (`punch_type`),
        FOREIGN KEY (`attendance_record_id`) REFERENCES `attendance_records`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_leave_types` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `code` VARCHAR(30) DEFAULT NULL,
        `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
        `annual_quota` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_leave_type_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_leave_balances` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `leave_type_id` INT NOT NULL,
        `year` INT NOT NULL,
        `allocated_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `used_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `carry_forward_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_leave_balance` (`user_id`, `leave_type_id`, `year`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`leave_type_id`) REFERENCES `attendance_leave_types`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_leave_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `leave_type_id` INT NOT NULL,
        `start_date` DATE NOT NULL,
        `end_date` DATE NOT NULL,
        `total_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `reason` TEXT DEFAULT NULL,
        `status` ENUM('pending','manager_approved','hr_approved','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
        `manager_id` INT DEFAULT NULL,
        `manager_note` VARCHAR(255) DEFAULT NULL,
        `manager_action_at` DATETIME DEFAULT NULL,
        `hr_id` INT DEFAULT NULL,
        `hr_note` VARCHAR(255) DEFAULT NULL,
        `hr_action_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_leave_user_status` (`user_id`, `status`),
        INDEX `idx_leave_date_range` (`start_date`, `end_date`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`leave_type_id`) REFERENCES `attendance_leave_types`(`id`) ON DELETE RESTRICT,
        FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`hr_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_holidays` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(150) NOT NULL,
        `holiday_date` DATE NOT NULL,
        `branch_id` INT DEFAULT NULL,
        `department` VARCHAR(100) DEFAULT NULL,
        `is_optional` TINYINT(1) NOT NULL DEFAULT 0,
        `announcement` VARCHAR(255) DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_holiday_scope` (`holiday_date`, `branch_id`, `department`),
        INDEX `idx_holiday_date` (`holiday_date`),
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_weekly_off_rules` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `branch_id` INT DEFAULT NULL,
        `department` VARCHAR(100) DEFAULT NULL,
        `weekday` TINYINT NOT NULL COMMENT '1=Mon ... 7=Sun',
        `week_occurrence` ENUM('every','first','second','third','fourth','last') NOT NULL DEFAULT 'every',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_weekly_off_scope` (`branch_id`, `department`, `weekday`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_regularization_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `attendance_record_id` INT DEFAULT NULL,
        `request_type` ENUM('missed_checkin','missed_checkout','wrong_time','manual_attendance') NOT NULL,
        `requested_check_in` DATETIME DEFAULT NULL,
        `requested_check_out` DATETIME DEFAULT NULL,
        `reason` TEXT DEFAULT NULL,
        `status` ENUM('pending','manager_approved','hr_approved','approved','rejected') NOT NULL DEFAULT 'pending',
        `manager_id` INT DEFAULT NULL,
        `manager_note` VARCHAR(255) DEFAULT NULL,
        `manager_action_at` DATETIME DEFAULT NULL,
        `hr_id` INT DEFAULT NULL,
        `hr_note` VARCHAR(255) DEFAULT NULL,
        `hr_action_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_regularization_user_status` (`user_id`, `status`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`attendance_record_id`) REFERENCES `attendance_records`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`hr_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_policies` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `company_id` INT DEFAULT NULL,
        `branch_id` INT DEFAULT NULL,
        `grace_minutes` INT NOT NULL DEFAULT 0,
        `late_mark_threshold` INT NOT NULL DEFAULT 0,
        `late_to_half_day_count` INT NOT NULL DEFAULT 3,
        `half_day_work_minutes_threshold` INT NOT NULL DEFAULT 240,
        `minimum_work_minutes` INT NOT NULL DEFAULT 480,
        `auto_absent_if_no_punch` TINYINT(1) NOT NULL DEFAULT 0,
        `allow_flexible_timing` TINYINT(1) NOT NULL DEFAULT 0,
        `duplicate_punch_minutes` INT NOT NULL DEFAULT 3,
        `overtime_eligible` TINYINT(1) NOT NULL DEFAULT 1,
        `salary_deduction_mode_default` ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed',
        `salary_deduction_value_default` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `salary_currency` VARCHAR(32) NOT NULL DEFAULT '',
        `closed_weekdays_json` TEXT NULL,
        `updated_by` INT DEFAULT NULL,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_policy_scope` (`company_id`, `branch_id`),
        FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_payroll_exports` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `export_month` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
        `user_id` INT NOT NULL,
        `branch_id` INT DEFAULT NULL,
        `department` VARCHAR(100) DEFAULT NULL,
        `payable_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `absent_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `late_deduction_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `half_day_deduction_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `unpaid_leave_days` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `overtime_hours` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
        `overtime_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `payload_json` LONGTEXT DEFAULT NULL,
        `created_by` INT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_payroll_export_month` (`export_month`),
        INDEX `idx_payroll_export_user` (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_audit_logs` (
        `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
        `entity_type` VARCHAR(80) NOT NULL,
        `entity_id` INT NOT NULL,
        `action` VARCHAR(50) NOT NULL,
        `changed_by` INT DEFAULT NULL,
        `old_values` LONGTEXT DEFAULT NULL,
        `new_values` LONGTEXT DEFAULT NULL,
        `change_note` VARCHAR(255) DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
        INDEX `idx_audit_changed_by` (`changed_by`),
        INDEX `idx_audit_created_at` (`created_at`),
        FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_overtime` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `attendance_record_id` INT DEFAULT NULL,
        `ot_date` DATE NOT NULL,
        `ot_type` ENUM('daily','weekend','holiday') NOT NULL DEFAULT 'daily',
        `hours` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        `approval_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        `approved_by` INT DEFAULT NULL,
        `approved_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_overtime_user_date` (`user_id`, `ot_date`),
        INDEX `idx_overtime_status` (`approval_status`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`attendance_record_id`) REFERENCES `attendance_records`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS `attendance_user_day_overrides` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `weekday` TINYINT NOT NULL COMMENT '1=Mon ... 7=Sun',
        `is_working_day` TINYINT(1) NOT NULL DEFAULT 1,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_by` INT DEFAULT NULL,
        `updated_by` INT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_user_weekday` (`user_id`, `weekday`),
        INDEX `idx_weekday_active` (`weekday`, `is_active`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "INSERT INTO `attendance_leave_types` (`name`, `code`, `is_paid`, `annual_quota`, `is_active`)
        SELECT 'Casual Leave', 'CL', 1, 12, 1 FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM attendance_leave_types WHERE code = 'CL' LIMIT 1)",
    "INSERT INTO `attendance_leave_types` (`name`, `code`, `is_paid`, `annual_quota`, `is_active`)
        SELECT 'Sick Leave', 'SL', 1, 10, 1 FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM attendance_leave_types WHERE code = 'SL' LIMIT 1)",
    "INSERT INTO `attendance_leave_types` (`name`, `code`, `is_paid`, `annual_quota`, `is_active`)
        SELECT 'Annual Leave', 'AL', 1, 18, 1 FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM attendance_leave_types WHERE code = 'AL' LIMIT 1)",
    "INSERT INTO `attendance_leave_types` (`name`, `code`, `is_paid`, `annual_quota`, `is_active`)
        SELECT 'Unpaid Leave', 'UL', 0, 0, 1 FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM attendance_leave_types WHERE code = 'UL' LIMIT 1)",
    "ALTER TABLE `attendance_policies`
        ADD COLUMN IF NOT EXISTS `salary_deduction_mode_default` ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed' AFTER `overtime_eligible`,
        ADD COLUMN IF NOT EXISTS `salary_deduction_value_default` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `salary_deduction_mode_default`,
        ADD COLUMN IF NOT EXISTS `salary_currency` VARCHAR(32) NOT NULL DEFAULT '' AFTER `salary_deduction_value_default`",
    "ALTER TABLE `attendance_policies`
        ADD COLUMN IF NOT EXISTS `closed_weekdays_json` TEXT NULL AFTER `overtime_eligible`",
    "ALTER TABLE `attendance_shifts`
        ADD COLUMN IF NOT EXISTS `allowed_weekdays_json` TEXT NULL AFTER `grace_minutes`",
    "ALTER TABLE `attendance_shifts`
        ADD COLUMN IF NOT EXISTS `salary_currency` VARCHAR(32) NOT NULL DEFAULT '' AFTER `grace_minutes`",
    "ALTER TABLE `attendance_shifts`
        ADD COLUMN IF NOT EXISTS `use_policy_threshold_override` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allowed_weekdays_json`",
    "ALTER TABLE `attendance_shifts`
        ADD COLUMN IF NOT EXISTS `weekday_time_overrides_json` TEXT NULL AFTER `allowed_weekdays_json`",
    "ALTER TABLE `attendance_records` MODIFY COLUMN `status` ENUM('present','absent','late','half_day','early_exit','on_leave','weekly_off','holiday','work_from_home','on_duty','missed_punch') NOT NULL DEFAULT 'present'",
];
install_run_queries($connect, $attendanceModuleInstallerQueries, $migrationWarnings);

$attendanceUserInstallerQueries = [
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `assigned_team` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Comma-separated list of staff/admin user IDs assigned to this client' AFTER `teams_id`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `base_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `assigned_team`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `attendance_deduction_mode` ENUM('fixed','percentage') DEFAULT NULL AFTER `base_salary`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `attendance_deduction_value` DECIMAL(12,2) DEFAULT NULL AFTER `attendance_deduction_mode`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `login_ip_restriction_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `attendance_deduction_value`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `allowed_login_ips` TEXT DEFAULT NULL AFTER `login_ip_restriction_enabled`",
    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `attendance_disabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = attendance hidden/disabled for this user' AFTER `allowed_login_ips`",
];
install_run_queries($connect, $attendanceUserInstallerQueries, $migrationWarnings);

// Version 4.2 — Server Health Monitor (includes/migrations/4.2.sql)
installer_run_42_server_monitor_migrations($connect, $migrationWarnings);

// Version 4.6 — mail queue table (CREATE only; column/indexes baked into fresh install CREATEs)
installer_run_46_performance_create_tables($connect, $migrationWarnings);

// Version 4.7.1 — task recurring tables + columns
installer_run_471_task_recurrence($connect, $migrationWarnings);

// Version 4.10 — AI Assistant tables + api_tokens + ERP separate_so
installer_run_410_migrations($connect, $migrationWarnings);

// Version 4.7 — marketing bounce columns are baked into install/index.php CREATE TABLE definitions
// (not ALTER; existing installs apply includes/migrations/4.7.sql via updater)

$cronJobsTableExists = @mysqli_query($connect, "SHOW TABLES LIKE 'cron_jobs'");
if ($cronJobsTableExists && mysqli_num_rows($cronJobsTableExists) > 0) {
    mysqli_query(
        $connect,
        "INSERT INTO `cron_jobs` (`name`, `file_path`, `category`, `duration`, `enabled`)
         SELECT 'Google Calendar Sync', 'cron/g-calender-cron.php', 'Google Drive', '15 minutes', 1
         FROM DUAL
         WHERE NOT EXISTS (
             SELECT 1
             FROM `cron_jobs`
             WHERE `file_path` = 'cron/g-calender-cron.php'
             LIMIT 1
         )"
    );
    mysqli_query(
        $connect,
        "INSERT INTO `cron_jobs` (`name`, `file_path`, `category`, `duration`, `enabled`)
         SELECT 'Attendance Auto Absent', 'cron/attendance_auto_absent.php', 'Attendance', '15 minutes', 1
         FROM DUAL
         WHERE NOT EXISTS (
             SELECT 1
             FROM `cron_jobs`
             WHERE `file_path` = 'cron/attendance_auto_absent.php'
             LIMIT 1
         )"
    );
}

    $_SESSION['install_system_migrations_done'] = true;
    }
}

// Sanitize function
function sanitize_string($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

if(isset($_POST['admin-form'])){
    $firstName = sanitize_string($_POST['firstName']);
    $rawPassword = sanitize_string($_POST['password']);
    $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);
    $email = sanitize_string($_POST['email']);
    $title = sanitize_string($_POST['title']);
    $accountStatus = (int)$_POST['accountStatus'];
    $regDate = date("Y-m-d H:i:s");
    $last_seen = time();
    $type_status = 'active';
    $session_status = 'online';
    $empty = '';
    
    // Insert admin user with correct number of parameters
    $sql = "INSERT INTO users (
        firstname, password, email, title, Projects_ids, accountStatus, 
        address, phone, website, teams_id, fb, regDate, type_status, 
        last_seen, session_status, status, note, city, state, zip, 
        country, user_language
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'en')";
            
    $stmt = mysqli_prepare($connect, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssssssssssssssssss", 
            $firstName, 
            $hashedPassword, 
            $email, 
            $title, 
            $empty, // Projects_ids
            $accountStatus, 
            $empty, // address
            $empty, // phone
            $empty, // website
            $empty, // teams_id
            $empty, // fb
            $regDate, 
            $type_status, 
            $last_seen, 
            $session_status,
            $empty, // note
            $empty, // city
            $empty, // state
            $empty, // zip
            $empty  // country
        );
        
        if (mysqli_stmt_execute($stmt)) {
            // Default settings
            $company_name = 'Task Session';
            $syatem_title = 'Task Session - Focused workflows, flawless results.';
            $login_page_title = 'Sign in to your account';
            $copy_rights = '&copy; Copyright ' . date('Y') . ' by Task Session.<br/> All Rights Reserved';
            $system_currency = 'USD,$';
            $multiple_currencies = 'USD,$';
            $time_zone = 'UTC';
            $favicon_image = '';
            $login_page_logo = '';
            $logo = '';
            $mobile_logo = '';
            $login_page_image = '';
            $invoice_logo = '';
            $email_template_logo = '';
            $stripe_sk = '';
            $stripe_pk = '';
            $paypal_email = '';
            $checkout_id = '';
            $checkout_pk = '';
            $system_email = $email;
            $forget_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Reset your password
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              We received a request to reset the password for your {COMPANY_NAME} account.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:32px 32px 22px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{RESET_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Reset password</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px 30px 32px; background-color:#fafbfc;">
            <p style="margin:0 0 14px 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              If the button doesn't work, copy this link into your browser:
            </p>
            <p style="margin:0 0 18px 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; word-break:break-all;">
              <a href="{RESET_URL}" style="color:#0094ff; text-decoration:underline;">{RESET_URL}</a>
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              Didn't request this? Ignore this email — your password stays as it is.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $create_account_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Welcome to {COMPANY_NAME}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              Your account is ready. Sign in with the details below.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Email
            </p>
            <p style="margin:0 0 18px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#1a1a1a; word-break:break-all;">
              {USER_LOGIN_EMAIL}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Temporary password
            </p>
            <p style="margin:0 0 18px 0; font-family:'Courier New', Courier, monospace; font-size:16px; line-height:1.4; font-weight:bold; color:#1a1a1a; word-break:break-all;">
              {USER_LOGIN_PASSWORD}
            </p>

            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.55; color:#8b9096;">
              Change this password after your first login.
            </p>

          </td>
        </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Log in</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $project_assign_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              New project created
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              You've been added to a new project on your dashboard.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">
            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Project
            </p>
            <p style="margin:0 0 18px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {PROJECT_NAME}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              Log in to see project details, updates, and progress.
            </p>
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Open project</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $assign_staff_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              New project created
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              You've been added to a new project on your dashboard.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">
            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Project
            </p>
            <p style="margin:0 0 18px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {PROJECT_NAME}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              Log in to see project details, updates, and progress.
            </p>
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Open project</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $project_update_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Project updated
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              There are new changes on one of your projects.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">
            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Project
            </p>
            <p style="margin:0 0 18px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {PROJECT_NAME}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              Log in to review the latest changes, progress, and messages.
            </p>
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">View updates</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $task_create_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              New task created
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              A task has been added to your project.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Task
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {TASK_TITLE}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Description
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.6; color:#2b2f33; word-break:break-word;">
              {TASK_DESCRIPTION}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Due date
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; font-weight:bold; color:#1a1a1a;">
              {DUE_DATE}
            </p>

          </td>
        </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Open task</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $task_update_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Task status updated
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              A task in your project moved to a new status.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              New status
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.4; font-weight:bold; color:#0094ff;">
              {TASK_STATUS}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Task
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {TASK_TITLE}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Description
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.6; color:#2b2f33; word-break:break-word;">
              {TASK_DESCRIPTION}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Due date
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; font-weight:bold; color:#1a1a1a;">
              {DUE_DATE}
            </p>

          </td>
        </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Open task</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $invoice_create_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="padding:28px 32px 4px 32px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              New invoice available
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              A new invoice has been added to your Task Session account.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Invoice
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {INVOICE_TITLE}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Amount
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:24px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              {INVOICE_AMOUNT}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Due date
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.4; font-weight:bold; color:#0094ff;">
              {INVOICE_DUE_DATE}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Status
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#2b2f33;">
              {INVOICE_STATUS}
            </p>

            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              Log in to view, download, or complete the payment.
            </p>

          </td>
        </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">View invoice</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $invoice_paid_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="padding:28px 32px 4px 32px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Payment received
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              Thank you — your account is now up to date.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Invoice
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {INVOICE_TITLE}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Amount paid
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:24px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              {INVOICE_AMOUNT}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Due date
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#2b2f33;">
              {INVOICE_DUE_DATE}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Status
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.4; font-weight:bold; color:#1f8a70;">
              {INVOICE_STATUS}
            </p>

            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              Log in to view or download your invoice.
            </p>

          </td>
        </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">View invoice</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $invoice_paid_email_admin = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="padding:28px 32px 4px 32px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Payment received
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              A payment has been recorded against the invoice below.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Invoice
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {INVOICE_TITLE}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Amount
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:24px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              {INVOICE_AMOUNT}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Client
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#2b2f33; word-break:break-word;">
              {CLIENT_NAME}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Status
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.4; font-weight:bold; color:#1f8a70;">
              {INVOICE_STATUS}
            </p>

            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              Log in to view the full invoice details.
            </p>

          </td>
        </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">View invoice</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $message_notification_email = <<<'EMAIL'
<div style="max-width: 600px; margin: 0 auto; padding: 20px;"><div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;"><h2 style="color: #007bff; margin-top: 0;"><span style="color: rgb(51, 51, 51); font-size: 14px;">Hello {USER_NAME},</span></h2>
            <p>You have received a new message in the project<br>&nbsp;<strong>{PROJECT_NAME}</strong>.</p>
        </div>
        
        <div style="background-color: #ffffff; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
            <h3 style="color: #495057; margin-top: 0;">Message Details</h3>
            <p><strong>From:</strong> {SENDER_NAME}</p>
            <p><strong>Project:</strong> {PROJECT_NAME}</p>
            <p><strong>Time:</strong> {MESSAGE_TIME}</p>
            <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
                <p style="margin: 0;"><strong>Message:</strong></p>
                <p style="margin: 10px 0 0 0;">{MESSAGE_CONTENT}</p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <a href="{DASHBOARD_URL}" style="background-color: #0094ff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">View Message</a>
        </div>
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 14px;">
            <p>This is an automated notification from {SIGNATURE}.</p>
            <p>If you have any questions, please contact your project administrator.</p>
        </div>
    </div>
EMAIL;
            $group_chat_create_email_subject = 'You have been added to group: {GROUP_NAME}';
            $group_chat_create_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hello <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.45; color:#6c757d;">
              <strong style="color:#1a1a1a;">{CREATOR_NAME}</strong> added you to a group
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 8px 32px; background-color:#fafbfc;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tbody><tr>
                <td style="padding:18px 20px; background-color:#ffffff; border:1px solid #e8eaed; border-left:3px solid #0094ff; border-radius:8px;">
                  <p style="margin:0 0 5px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
                    Group
                  </p>
                  <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
                    {GROUP_NAME}
                  </p>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:24px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Open group</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              This is an automated notification from {SIGNATURE}.
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $group_chat_batch_email_subject = 'You have {MESSAGE_COUNT} new message(s) in {GROUP_NAME}.';
            $group_chat_batch_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hello <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.5; color:#6c757d;">
              You have new messages in the group
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:17px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {GROUP_NAME}
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:22px 24px 8px 24px; background-color:#fafbfc;">
            {MESSAGES_LIST}
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:24px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Open group chat</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              This is an automated notification from {SIGNATURE}.
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $group_chat_message_email_subject = 'You have {MESSAGE_COUNT} new message';
            $group_chat_message_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hello <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.5; color:#6c757d;">
              New messages were posted in the project:
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:17px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {PROJECT_NAME}
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:22px 24px 8px 24px; background-color:#fafbfc;">
            {MESSAGES_LIST}
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:24px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Open project chat</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              This is an automated notification from {SIGNATURE}.
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $discussion_chat_batch_email_subject = 'You have {MESSAGE_COUNT} new message';
            $discussion_chat_batch_email = <<<'EMAIL'
<div style="max-width: 600px; margin: 0 auto; padding: 20px;">
    
  <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; color: initial;">
    <h2 style="color: #007bff; margin-top: 0;">
            <span style="color: rgb(51, 51, 51); font-size: 14px;">
                Hello {USER_NAME},
            </span>
        </h2>
        <p style="margin: 0; font-size: 14px;"><strong>You have a new message in the project: {PROJECT_NAME}</strong></p>
  </div>

    
    <div style="background-color: #ffffff; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; color: initial;">
        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
            <p style="margin: 0;"><strong>Messages</strong></p>
            <p style="margin: 10px 0 0 0;">{MESSAGES_LIST}</p>
        </div>
    </div>

    
    <div style="text-align: center; margin-top: 30px;">
        <a href="{DASHBOARD_URL}" style="background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">View Message</a>
    </div>

    
    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; text-align: center; color: #6c757d; font-size: 14px;">
        <p>This is an automated notification from {SIGNATURE}.</p>
        <p>If you have any questions, please contact your project administrator.</p>
    </div>
</div>
EMAIL;
            $task_chat_batch_email_subject = '{MESSAGE_COUNT} new messages were posted in the task: {TASK_NAME}';
            $task_chat_batch_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">

      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        
        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        
        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hello <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.5; color:#6c757d;">
              You have new messages on:
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:17px; line-height:1.35; font-weight:bold; color:#1a1a1a;">
              {TASK_NAME}
            </p>
          </td>
        </tr>

        
        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        
        <tr>
          <td style="padding:22px 24px 8px 24px; background-color:#fafbfc;">
            {MESSAGES_LIST}
          </td>
        </tr>

        
        <tr>
          <td align="center" style="padding:24px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Open conversation</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        
        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              This is an automated notification from {SIGNATURE}.
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>

      </tbody></table>

    </td>
  </tr>
</tbody></table>
EMAIL;
            $one_to_one_chat_batch_email_subject = 'You have {MESSAGE_COUNT} new message(s) from {SENDER_NAME}.';
            $one_to_one_chat_batch_email = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hello <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 3px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.45; color:#6c757d;">
              New message from <strong style="color:#1a1a1a;">{SENDER_NAME}</strong>
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.4; color:#a0a5ab;">
              {MESSAGE_TIME}
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:22px 24px 8px 24px; background-color:#fafbfc;">
            {MESSAGES_LIST}
          </td>
        </tr>

        <tr>
          <td align="center" style="padding:24px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Reply to {SENDER_NAME}</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              This is an automated notification from {SIGNATURE}.
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $payment_reminder_1_subject = 'Payment reminder  {INVOICE_NUMBER}';
            $payment_reminder_1_template = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{CLIENT_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Overdue payment required
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              The invoice below is past its due date.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Invoice
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {INVOICE_NUMBER}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Amount
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:24px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              {TOTAL_AMOUNT}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Due date
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.4; font-weight:bold; color:#c0392b;">
              {DUE_DATE}
            </p>

            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              Please log in to your account to pay the invoice.
            </p>

                      </td>
                    </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Pay invoice</a>
                </td>
                    </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
                      </td>
                    </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $payment_reminder_2_subject = 'Overdue payment reminder  {INVOICE_NUMBER}';
            $payment_reminder_2_template = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{CLIENT_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Invoice past due
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              This invoice is past due and requires attention.
            </p>
          </td>
                    </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
                              </td>
                            </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Invoice
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {INVOICE_NUMBER}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Amount
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:24px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              {TOTAL_AMOUNT}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Overdue by
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.4; font-weight:bold; color:#c0392b;">
              {DAYS_OVERDUE} days
            </p>

            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              Please log in to your account to pay the invoice.
            </p>

                      </td>
                    </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Pay invoice</a>
                      </td>
                    </tr>
            </tbody></table>
              </td>
            </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
      </td>
    </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $payment_reminder_3_subject = 'Overdue payment reminder  {INVOICE_NUMBER}';
            $payment_reminder_3_template = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{CLIENT_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Invoice still outstanding
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              This invoice has not been paid yet.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Invoice
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {INVOICE_NUMBER}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Amount
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:24px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              {TOTAL_AMOUNT}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Overdue by
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.4; font-weight:bold; color:#c0392b;">
              {DAYS_OVERDUE} days
            </p>

            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              Please log in to your account to pay the invoice.
            </p>

                      </td>
                    </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Pay invoice</a>
                </td>
                    </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $task_reminder_1_subject = 'Task due in {DAYS_UNTIL_DUE} days: {TASK_TITLE}';
            $task_reminder_1_template = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Task due in {DAYS_UNTIL_DUE} days
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
                          This is a reminder regarding the following task.
            </p>
                      </td>
                    </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
                    </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Task title
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {TASK_TITLE}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Project
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#2b2f33; word-break:break-word;">
              {PROJECT_NAME}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Status
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#2b2f33;">
              {TASK_STATUS}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Due date
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.4; font-weight:bold; color:#0094ff;">
              {DUE_DATE}
            </p>

          </td>
        </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">View Task</a>
                              </td>
                            </tr>
            </tbody></table>
                      </td>
                    </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
                      </td>
                    </tr>
      </tbody></table>
              </td>
            </tr>
</tbody></table>
EMAIL;
            $task_reminder_2_subject = 'Task due today: {TASK_TITLE}';
            $task_reminder_2_template = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
      </td>
    </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Task due today
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              This is a reminder regarding the following task.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Task title
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {TASK_TITLE}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Project
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#2b2f33; word-break:break-word;">
              {PROJECT_NAME}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Status
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#2b2f33;">
              {TASK_STATUS}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Due date
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.4; font-weight:bold; color:#0094ff;">
              {DUE_DATE}
            </p>

          </td>
        </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">View Task</a>
                </td>
              </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $task_reminder_3_subject = 'Action Required: Overdue Task – {TASK_TITLE}';
            $task_reminder_3_template = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="text-align: left; padding: 28px 32px 4px;">
            {LOGO}
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Action required: overdue task
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              This is a reminder regarding the following task.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Task title
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:18px; line-height:1.35; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {TASK_TITLE}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Project
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#2b2f33; word-break:break-word;">
              {PROJECT_NAME}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Status
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#2b2f33;">
              {TASK_STATUS}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Due date
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.4; font-weight:bold; color:#c0392b;">
              {DUE_DATE}
            </p>

                      </td>
                    </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">View Task</a>
                </td>
                    </tr>
            </tbody></table>
          </td>
        </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
                      </td>
                    </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
            $email_report_template = <<<'EMAIL'
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f8fa; margin:0; font-family:Arial, Helvetica, sans-serif;">
  <tbody><tr>
    <td align="center" style="padding:50px 12px 50px 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e4e6ea;">

        <tbody><tr>
          <td align="center" style="padding:28px 32px 4px 32px;"><br></td>
        </tr>

        <tr>
          <td style="padding:20px 32px 18px 32px;">
            <p style="margin:0 0 10px 0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.4; color:#333333;">
              Hi <strong>{USER_NAME}</strong>,
            </p>
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a;">
              Your email activity report is ready
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.55; color:#6c757d;">
              Report period: {REPORT_PERIOD}
            </p>
          </td>
                    </tr>

        <tr>
          <td style="padding:0 32px;">
            <div style="height:1px; background-color:#eceef1; font-size:0; line-height:1px;">&nbsp;</div>
          </td>
        </tr>

        <tr>
          <td style="padding:26px 32px 0 32px; background-color:#fafbfc;">

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Total accounts
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {TOTAL_ACCOUNTS}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Total emails received
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {TOTAL_EMAILS_RECEIVED}
            </p>

            <p style="margin:0 0 4px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Total emails sent
            </p>
            <p style="margin:0 0 20px 0; font-family:Arial, Helvetica, sans-serif; font-size:22px; line-height:1.3; font-weight:bold; color:#1a1a1a; word-break:break-word;">
              {TOTAL_EMAILS_SENT}
            </p>

                              </td>
                            </tr>

        <tr>
          <td style="padding:8px 32px 0 32px; background-color:#fafbfc;">
            <p style="margin:0 0 12px 0; font-family:Arial, Helvetica, sans-serif; font-size:11px; letter-spacing:0.6px; text-transform:uppercase; color:#8b9096;">
              Account summary
            </p>
            <div style="font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.6; color:#2b2f33; word-break:break-word;">
              {ACCOUNT_REPORTS}
            </div>
                      </td>
                    </tr>

        <tr>
          <td style="padding:20px 32px 0 32px; background-color:#fafbfc;">
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.6; color:#8b9096;">
              Log in to your dashboard to view detailed activity and manage your email accounts.
            </p>
                      </td>
                    </tr>

        <tr>
          <td align="center" style="padding:26px 32px 32px 32px; background-color:#fafbfc;">
            <table role="presentation" width="auto" cellpadding="0" cellspacing="0" border="0" align="center" style="width:auto; border-collapse:collapse;">
              <tbody><tr>
                <td align="center" bgcolor="#0094ff" style="border-radius:6px; mso-padding-alt:0;">
                  <a href="{DASHBOARD_URL}" style="display:inline-block; padding:13px 32px; font-family:Arial, Helvetica, sans-serif; font-size:15px; font-weight:bold; line-height:1.2; color:#ffffff; text-decoration:none; border-radius:6px; white-space:nowrap;">Open Dashboard</a>
              </td>
            </tr>
            </tbody></table>
      </td>
    </tr>

        <tr>
          <td style="padding:20px 32px 26px 32px; border-top:1px solid #eceef1; text-align:center;">
            <p style="margin:0 0 6px 0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Best regards, {SIGNATURE}
            </p>
            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.6; color:#8b9096;">
              Questions? Contact your project administrator.
            </p>
          </td>
        </tr>
      </tbody></table>
    </td>
  </tr>
</tbody></table>
EMAIL;
			$system_language = 'en';
            $version = '4.10';
            $purchase_code = '';
            $url = $website_url;
            // Ensure menu label override columns exist on fresh installs.
            $adminSetupSql = [
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `label_projects_override` VARCHAR(191) NULL DEFAULT NULL",
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `label_tasks_override` VARCHAR(191) NULL DEFAULT NULL",
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `label_clients_override` VARCHAR(191) NULL DEFAULT NULL",
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `label_financials_override` VARCHAR(191) NULL DEFAULT NULL",
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `label_chatting_override` VARCHAR(191) NULL DEFAULT NULL",
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `label_private_notes_override` VARCHAR(191) NULL DEFAULT NULL",
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `label_leads_override` VARCHAR(191) NULL DEFAULT NULL",
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `label_media_vault_override` VARCHAR(191) NULL DEFAULT NULL",
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `label_custom_fields_override` VARCHAR(191) NULL DEFAULT NULL",
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `label_event_override` VARCHAR(191) NULL DEFAULT NULL",
            "ALTER TABLE `email_attachments` ADD COLUMN IF NOT EXISTS `content_id` VARCHAR(255) DEFAULT NULL AFTER `storage_quota_user_id`",
            "ALTER TABLE `email_attachments` ADD INDEX IF NOT EXISTS `idx_content_id` (`content_id`)",
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_time_tracking` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Task Time Tracking' AFTER `global_allowed_ips`",
            "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_reports` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Admin Reports dashboard (admin only)' AFTER `module_time_tracking`",
            "ALTER TABLE `tasks` ADD COLUMN IF NOT EXISTS `estimated_time_seconds` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Task estimate in seconds' AFTER `completed_at`",
            "CREATE TABLE IF NOT EXISTS `task_time_entries` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `task_id` INT(11) NOT NULL,
                `project_id` INT(11) DEFAULT NULL COMMENT 'NULL for internal tasks',
                `user_id` INT(11) NOT NULL COMMENT 'User who logged time',
                `started_at` DATETIME NOT NULL,
                `ended_at` DATETIME DEFAULT NULL,
                `duration_seconds` INT UNSIGNED DEFAULT NULL COMMENT 'Set when timer stopped',
                `billable` TINYINT(1) NOT NULL DEFAULT 1,
                `notes` TEXT NULL,
                `source` VARCHAR(32) NOT NULL DEFAULT 'manual' COMMENT 'manual,timer,import',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_tte_task` (`task_id`),
                KEY `idx_tte_project_user` (`project_id`, `user_id`),
                KEY `idx_tte_started` (`started_at`),
                CONSTRAINT `fk_tte_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_tte_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `task_timer_sessions` (
                `user_id` INT(11) NOT NULL,
                `task_id` INT(11) NOT NULL,
                `project_id` INT(11) DEFAULT NULL COMMENT 'NULL for internal tasks',
                `status` ENUM('running','paused') NOT NULL DEFAULT 'running',
                `segment_started_at` DATETIME DEFAULT NULL,
                `accumulated_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`user_id`, `task_id`),
                KEY `idx_tts_task` (`task_id`),
                CONSTRAINT `fk_tts_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_tts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `task_time_reports_daily` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `report_date` DATE NOT NULL,
                `user_id` INT(11) NOT NULL,
                `project_id` INT(11) NOT NULL,
                `task_id` INT(11) DEFAULT NULL,
                `total_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
                `billable_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_ttrd_day_user_proj_task` (`report_date`, `user_id`, `project_id`, `task_id`),
                KEY `idx_ttrd_user_date` (`user_id`, `report_date`),
                CONSTRAINT `fk_ttrd_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_ttrd_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`p_id`) ON DELETE CASCADE,
                CONSTRAINT `fk_ttrd_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
            ];
            install_run_queries($connect, $adminSetupSql, $migrationWarnings);
            if (function_exists('installer_run_471_task_recurrence')) {
                installer_run_471_task_recurrence($connect, $migrationWarnings);
            }
            if (function_exists('installer_run_410_migrations')) {
                installer_run_410_migrations($connect, $migrationWarnings);
            }
            $sqlsetting = "INSERT INTO settings (url, company_name, syatem_title, login_page_title, copy_rights, system_currency, time_zone, favicon_image, login_page_logo, logo, mobile_logo, login_page_image, invoice_logo, stripe_sk, stripe_pk, paypal_email, checkout_id, checkout_pk, system_email, forget_email, create_account_email, project_assign_email, assign_staff_email, project_update_email, task_create_email, task_update_email, invoice_create_email, invoice_paid_email, invoice_paid_email_admin, message_notification_email, group_chat_create_email, group_chat_create_email_subject, group_chat_message_email, group_chat_message_email_subject, group_chat_batch_email, group_chat_batch_email_subject, discussion_chat_batch_email, discussion_chat_batch_email_subject, one_to_one_chat_batch_email, one_to_one_chat_batch_email_subject, task_chat_batch_email, task_chat_batch_email_subject, payment_reminders_enabled, payment_reminder_1_enabled, payment_reminder_1_days, payment_reminder_1_type, payment_reminder_1_subject, payment_reminder_1_template, payment_reminder_2_enabled, payment_reminder_2_days, payment_reminder_2_type, payment_reminder_2_subject, payment_reminder_2_template, payment_reminder_3_enabled, payment_reminder_3_days, payment_reminder_3_type, payment_reminder_3_subject, payment_reminder_3_template, payment_reminder_send_to_client, payment_reminder_send_to_staff, task_reminders_enabled, task_reminder_1_enabled, task_reminder_1_days, task_reminder_1_type, task_reminder_1_subject, task_reminder_1_template, task_reminder_2_enabled, task_reminder_2_days, task_reminder_2_type, task_reminder_2_subject, task_reminder_2_template, task_reminder_3_enabled, task_reminder_3_days, task_reminder_3_type, task_reminder_3_subject, task_reminder_3_template, task_reminder_send_to_staff, task_reminder_send_to_client, task_reminder_send_to_creator, system_language, version, purchase_code, default_sales_tax, company_address, module_lead_board, module_invoices, module_projects, module_tasks, module_file_management, module_notes_documents, module_discussions, module_email, email_report_template, email_report_subject) VALUES ('" . mysqli_real_escape_string($connect, $url) . "', '" . mysqli_real_escape_string($connect, $company_name) . "', '" . mysqli_real_escape_string($connect, $syatem_title) . "', '" . mysqli_real_escape_string($connect, $login_page_title) . "', '" . mysqli_real_escape_string($connect, $copy_rights) . "', '" . mysqli_real_escape_string($connect, $system_currency) . "', '" . mysqli_real_escape_string($connect, $time_zone) . "', '" . mysqli_real_escape_string($connect, $favicon_image) . "', '" . mysqli_real_escape_string($connect, $login_page_logo) . "', '" . mysqli_real_escape_string($connect, $logo) . "', '" . mysqli_real_escape_string($connect, $mobile_logo) . "', '" . mysqli_real_escape_string($connect, $login_page_image) . "', '" . mysqli_real_escape_string($connect, $invoice_logo) . "', '" . mysqli_real_escape_string($connect, $stripe_sk) . "', '" . mysqli_real_escape_string($connect, $stripe_pk) . "', '" . mysqli_real_escape_string($connect, $paypal_email) . "', '" . mysqli_real_escape_string($connect, $checkout_id) . "', '" . mysqli_real_escape_string($connect, $checkout_pk) . "', '" . mysqli_real_escape_string($connect, $system_email) . "', '" . mysqli_real_escape_string($connect, $forget_email) . "', '" . mysqli_real_escape_string($connect, $create_account_email) . "', '" . mysqli_real_escape_string($connect, $project_assign_email) . "', '" . mysqli_real_escape_string($connect, $assign_staff_email) . "', '" . mysqli_real_escape_string($connect, $project_update_email) . "', '" . mysqli_real_escape_string($connect, $task_create_email) . "', '" . mysqli_real_escape_string($connect, $task_update_email) . "', '" . mysqli_real_escape_string($connect, $invoice_create_email) . "', '" . mysqli_real_escape_string($connect, $invoice_paid_email) . "', '" . mysqli_real_escape_string($connect, $invoice_paid_email_admin) . "', '" . mysqli_real_escape_string($connect, $message_notification_email) . "', '" . mysqli_real_escape_string($connect, $group_chat_create_email) . "', '" . mysqli_real_escape_string($connect, $group_chat_create_email_subject) . "', '" . mysqli_real_escape_string($connect, $group_chat_message_email) . "', '" . mysqli_real_escape_string($connect, $group_chat_message_email_subject) . "', '" . mysqli_real_escape_string($connect, $group_chat_batch_email) . "', '" . mysqli_real_escape_string($connect, $group_chat_batch_email_subject) . "', '" . mysqli_real_escape_string($connect, $discussion_chat_batch_email) . "', '" . mysqli_real_escape_string($connect, $discussion_chat_batch_email_subject) . "', '" . mysqli_real_escape_string($connect, $one_to_one_chat_batch_email) . "', '" . mysqli_real_escape_string($connect, $one_to_one_chat_batch_email_subject) . "', '" . mysqli_real_escape_string($connect, $task_chat_batch_email) . "', '" . mysqli_real_escape_string($connect, $task_chat_batch_email_subject) . "', 0, 0, 7, 'before', '" . mysqli_real_escape_string($connect, $payment_reminder_1_subject) . "', '" . mysqli_real_escape_string($connect, $payment_reminder_1_template) . "', 0, 3, 'after', '" . mysqli_real_escape_string($connect, $payment_reminder_2_subject) . "', '" . mysqli_real_escape_string($connect, $payment_reminder_2_template) . "', 0, 14, 'after', '" . mysqli_real_escape_string($connect, $payment_reminder_3_subject) . "', '" . mysqli_real_escape_string($connect, $payment_reminder_3_template) . "', 1, 0, 0, 0, 3, 'before', '" . mysqli_real_escape_string($connect, $task_reminder_1_subject) . "', '" . mysqli_real_escape_string($connect, $task_reminder_1_template) . "', 0, 0, 'before', '" . mysqli_real_escape_string($connect, $task_reminder_2_subject) . "', '" . mysqli_real_escape_string($connect, $task_reminder_2_template) . "', 0, 2, 'after', '" . mysqli_real_escape_string($connect, $task_reminder_3_subject) . "', '" . mysqli_real_escape_string($connect, $task_reminder_3_template) . "', 1, 0, 0, '" . mysqli_real_escape_string($connect, $system_language) . "', '" . mysqli_real_escape_string($connect, $version) . "', '" . mysqli_real_escape_string($connect, $purchase_code) . "', NULL, NULL, 1, 1, 1, 1, 1, 1, 1, 1, '" . mysqli_real_escape_string($connect, $email_report_template) . "', 'Email Account Summary Report')";
            if ($connect->query($sqlsetting) === TRUE) {
                $insertedSettingsId = mysqli_insert_id($connect);
                if ($insertedSettingsId > 0) {
                    mysqli_query(
                        $connect,
                        "UPDATE settings SET multiple_currencies = '" . mysqli_real_escape_string($connect, $multiple_currencies) . "' WHERE id = " . (int)$insertedSettingsId
                    );
                }
                install_baseline_schema_migrations($connect, dirname(__DIR__), $version);
                // Fresh install only: enable setup guide for Super Admin (user id 1)
                @mysqli_query($connect, "UPDATE settings SET setup_guide_pending = 1 WHERE id = 1");
                // Free edition: all modules ON by default
                @mysqli_query($connect, "UPDATE settings SET
                    module_lead_board = 1,
                    module_invoices = 1,
                    module_projects = 1,
                    module_tasks = 1,
                    module_email = 1,
                    module_file_management = 1,
                    module_notes_documents = 1,
                    module_discussions = 1,
                    module_attendance = 1,
                    module_ip_restriction = 1,
                    module_ecommerce = 0,
                    module_time_tracking = 1,
                    module_reports = 1,
                    module_marketing = 0
                    WHERE id = 1");
                $_SESSION['install_complete'] = true;
                $_SESSION['admin_email'] = $email;
                safe_redirect('complete.php', 'Installation completed successfully');
            } else {
                $error = "Error inserting settings: " . mysqli_error($connect);
            }
        } else {
            $error = "Error creating admin account: " . mysqli_error($connect);
        }
    } else {
        $error = "Error preparing user statement: " . mysqli_error($connect);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <title>Admin Setup - Task Session</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png"/>
    <link href="../assets/css/bootstrap.css" rel="stylesheet" type="text/css"/>
    <link href="../assets/css/style.min.css" rel="stylesheet" type="text/css"/>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@400;600&display=swap" rel="stylesheet"/>
    <link href="style.css" rel="stylesheet" type="text/css"/>
</head>
<body class="install-complete-page">
    <div class="install-logo">
        <img src="../assets/images/svg/dark-logo.svg" alt="Task Session"/>
    </div>
    <div class="dbinstall center-col install-complete-wrap">
        <div class="database">
            <div class="login-cols install-admin-setup">
                <div class="install-complete-header">
                    <div class="install-complete-header__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="40" height="40">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                        </svg>
                    </div>
                    <h1>Admin Account Setup</h1>
                    <p class="install-complete-header__lead">Final step — create your administrator account for this installation.</p>
                </div>

                <div class="install-complete-card install-complete-card--info">
                    <div class="install-complete-card__title">
                        Create your admin account
                    </div>
                    <p>This will be your main administrator account with full system access. Use these credentials to log in and manage Task Session.</p>
                </div>

                <?php if (!empty($migrationWarnings)): ?>
                    <div class="install-complete-card install-complete-card--warning">
                        <div class="install-complete-card__title">
                            <span class="install-complete-card__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                                </svg>
                            </span>
                            Schema notes
                        </div>
                        <p>Some optional upgrade SQL statements were skipped (common on Cloudways). You can continue admin setup.</p>
                        <details class="install-admin-details">
                            <summary>Show details</summary>
                            <pre><?php echo htmlspecialchars(implode("\n", array_slice($migrationWarnings, 0, 15))); ?></pre>
                        </details>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="install-complete-card install-complete-card--error">
                        <div class="install-complete-card__title">
                            <span class="install-complete-card__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                                </svg>
                            </span>
                            Error
                        </div>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="install-admin-form">
                    <div class="install-admin-field">
                        <label for="install-firstName">Full name</label>
                        <input required type="text" id="install-firstName" name="firstName" placeholder="Full Name" value="<?php echo isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : ''; ?>"/>
                        <small class="install-admin-hint">Enter your full name as it will appear in the system</small>
                    </div>

                    <div class="install-admin-field">
                        <label for="install-email">Admin email</label>
                        <input required type="email" id="install-email" name="email" placeholder="Admin Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"/>
                        <small class="install-admin-hint">This will be your login email address</small>
                    </div>

                    <div class="install-admin-field">
                        <label for="install-password">Password</label>
                        <input required type="password" id="install-password" name="password" placeholder="Password"/>
                        <small class="install-admin-hint">Choose a strong password (minimum 8 characters)</small>
                    </div>

                    <div class="install-admin-field">
                        <label for="install-title">Designation / role</label>
                        <input required type="text" id="install-title" name="title" placeholder="Designation/Role" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"/>
                        <small class="install-admin-hint">e.g. Administrator, Manager, CEO</small>
                    </div>

                    <input type="hidden" name="accountStatus" value="1"/>

                    <button type="submit" name="admin-form" class="primary-btn install-complete-btn">
                        Create admin account
                    </button>
                </form>

                <div class="install-complete-card install-complete-card--steps">
                    <div class="install-complete-card__title">
                        <span class="install-complete-card__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm0 5.25h.007v.008H3.75v-.008Zm0 5.25h.007v.008H3.75v-.008Z"/>
                            </svg>
                        </span>
                        Important notes
                    </div>
                    <ul>
                        <li>Keep your login credentials secure</li>
                        <li>You can add more users after installation</li>
                        <li>This account will have full system permissions</li>
                        <li>You will be redirected to the dashboard after setup</li>
                    </ul>
                </div>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>
</body>
</html>
