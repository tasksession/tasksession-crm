<?php
// INSTALLER: crm/install/index.php

session_start();
if (ob_get_level() === 0) {
    ob_start();
}
@ini_set('memory_limit', '512M');
@set_time_limit(0);

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/free_edition_filter.php';

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Install error</title></head><body>';
    echo '<h2>Installation stopped unexpectedly</h2>';
    echo '<p><strong>' . htmlspecialchars((string)($err['message'] ?? 'Unknown error')) . '</strong></p>';
    echo '<p>File: ' . htmlspecialchars((string)($err['file'] ?? '')) . ' (line ' . (int)($err['line'] ?? 0) . ')</p>';
    echo '<p><a href="?step=2">Back to database setup</a> Â· <a href="diagnose.php">Run diagnostics</a></p>';
    echo '</body></html>';
});

function check_requirements() {
    $requirements = [
        'PHP 8.0+' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'MySQLi PHP Extension' => extension_loaded('mysqli'),
        'PDO PHP Extension' => extension_loaded('pdo'),
        'cURL PHP Extension' => extension_loaded('curl'),
        'OpenSSL PHP Extension' => extension_loaded('openssl'),
        'MBString PHP Extension' => extension_loaded('mbstring'),
        'iconv PHP Extension' => extension_loaded('iconv'),
        'IMAP PHP Extension' => extension_loaded('imap'),
        'GD PHP Extension' => extension_loaded('gd'),
        'Zip PHP Extension' => extension_loaded('zip'),
        'allow_url_fopen' => ini_get('allow_url_fopen'),
    ];
    return $requirements;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

require_once __DIR__ . '/installer_sql_helper.php';

// Helper: run SQL and ignore harmless "already exists" errors
function installer_run_query($connect, $sql, &$errors, &$notes, $label = '')
{
    if (function_exists('installer_free_should_skip_label') && installer_free_should_skip_label((string) $label)) {
        $notes[] = ($label ?: 'SQL') . ' skipped (free edition)';
        return true;
    }
    if (function_exists('installer_free_should_skip_sql') && installer_free_should_skip_sql((string) $sql)) {
        $notes[] = ($label ?: 'SQL') . ' skipped (free edition)';
        return true;
    }
    if (function_exists('installer_free_strip_premium_foreign_keys')) {
        $sql = installer_free_strip_premium_foreign_keys((string) $sql);
    }
    if (installer_sql_is_add_column_if_not_exists((string) $sql)) {
        installer_run_add_columns_compat($connect, (string) $sql, $errors, (string) $label, $notes);
        return true;
    }
    try {
        $res = mysqli_query($connect, $sql);
        if ($res === false) {
            $msg = mysqli_error($connect);
            if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'already exists') !== false) {
                $notes[] = ($label ?: 'SQL') . ' skipped (already exists)';
                return true;
            }
            $errors[] = ($label ?: 'SQL') . ' failed: ' . $msg;
            return false;
        }
        return true;
    } catch (mysqli_sql_exception $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'already exists') !== false) {
            $notes[] = ($label ?: 'SQL') . ' skipped (already exists)';
            return true;
        }
        $errors[] = ($label ?: 'SQL') . ' exception: ' . $msg;
        return false;
    }
}

// Helper: run SQL file using multi_query, stripping comments/delimiters
function installer_run_sql_file($connect, $path, &$errors, &$notes, $label = '')
{
    if (!file_exists($path)) {
        $notes[] = ($label ?: basename($path)) . ' not found, skipping';
        return;
    }
    $sql = file_get_contents($path);
    if (empty($sql)) {
        $notes[] = ($label ?: basename($path)) . ' is empty, skipping';
        return;
    }

    // Strip DELIMITER lines and comments for mysqli compatibility
    $sql = preg_replace('/^\s*DELIMITER\s+.*$/im', '', $sql);
    $sql = preg_replace('/DELIMITER\s+[^\r\n]+/i', '', $sql);
    $sql = preg_replace('/--.*$/m', '', $sql);
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    if (!$connect->multi_query($sql)) {
        $msg = mysqli_error($connect);
        if (stripos($msg, 'Duplicate') !== false || stripos($msg, 'already exists') !== false) {
            $notes[] = ($label ?: basename($path)) . ' executed with existing objects';
            return;
        }
        $errors[] = ($label ?: basename($path)) . ' failed: ' . $msg;
        return;
    }

    // Flush remaining results
    while ($connect->more_results() && $connect->next_result()) {
        if ($result = $connect->store_result()) {
            $result->free();
        }
    }
    $notes[] = ($label ?: basename($path)) . ' executed';
}

// Helper: create/alter group chat related structures and email templates
function installer_run_group_chat_migrations($connect, &$errors, &$notes)
{
    $notes[] = 'Free edition: skipped group chat migrations';
    return;
    $inline = [];
    // Core group chat tables
    $inline[] = "CREATE TABLE IF NOT EXISTS `group_chats` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `avatar` VARCHAR(255) DEFAULT NULL,
        `created_by` INT(11) NOT NULL,
        `created_at` INT(11) NOT NULL,
        `updated_at` INT(11) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_created_by` (`created_by`),
        KEY `idx_updated_at` (`updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `group_chat_members` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `group_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `role` ENUM('admin','member') NOT NULL DEFAULT 'member',
        `joined_at` INT(11) NOT NULL,
        `left_at` INT(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_group_user_active` (`group_id`,`user_id`,`left_at`),
        KEY `idx_group_id` (`group_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_active_members` (`group_id`,`left_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `group_chat_messages` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `group_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `message` TEXT NOT NULL,
        `time` INT(11) NOT NULL,
        `edited` TINYINT(1) NOT NULL DEFAULT 0,
        `edit_time` INT(11) DEFAULT NULL,
        `reply_to` INT(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_group_id` (`group_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_time` (`time`),
        KEY `idx_reply_to` (`reply_to`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `group_chat_reads` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `message_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `read_time` INT(11) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_read` (`message_id`,`user_id`),
        KEY `message_id` (`message_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    // Email notifications table for group chat
    $inline[] = "CREATE TABLE IF NOT EXISTS `group_chat_email_notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `message_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `group_id` INT(11) NOT NULL,
        `email_sent_at` INT(11) NOT NULL,
        `batch_id` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_message_user` (`message_id`,`user_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_group_id` (`group_id`),
        KEY `idx_email_sent_at` (`email_sent_at`),
        KEY `idx_batch_id` (`batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    // Settings email templates (ensure base columns exist)
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `group_chat_create_email` TEXT NULL AFTER `message_notification_email`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `group_chat_message_email` TEXT NULL AFTER `group_chat_create_email`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `group_chat_batch_email` TEXT NULL AFTER `group_chat_message_email`";

    // Settings email subject/template columns
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `message_notification_email_subject` VARCHAR(255) NULL DEFAULT 'You have received a new message' AFTER `message_notification_email`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `group_chat_create_email_subject` VARCHAR(255) NULL DEFAULT 'You have been added to group: {GROUP_NAME}' AFTER `group_chat_create_email`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `group_chat_message_email_subject` VARCHAR(255) NULL DEFAULT 'You have {MESSAGE_COUNT} new message(s) in {GROUP_NAME}' AFTER `group_chat_message_email`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `group_chat_batch_email_subject` VARCHAR(255) NULL DEFAULT 'You have {MESSAGE_COUNT} new message(s) in {GROUP_NAME}' AFTER `group_chat_batch_email`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `one_to_one_chat_batch_email` TEXT NULL AFTER `group_chat_batch_email_subject`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `one_to_one_chat_batch_email_subject` VARCHAR(255) NULL DEFAULT 'You have {MESSAGE_COUNT} new message from {SENDER_NAME}' AFTER `one_to_one_chat_batch_email`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_chat_batch_email` TEXT NULL AFTER `one_to_one_chat_batch_email_subject`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_chat_batch_email_subject` VARCHAR(255) NULL DEFAULT 'You have {MESSAGE_COUNT} new message(s) in task: {TASK_NAME}' AFTER `task_chat_batch_email`";

    // Email notifications tables
    $inline[] = "CREATE TABLE IF NOT EXISTS `discussion_chat_email_notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `message_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `project_id` INT(11) NOT NULL,
        `email_sent_at` INT(11) NOT NULL,
        `batch_id` VARCHAR(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_notification` (`message_id`, `user_id`, `project_id`),
        KEY `idx_message_id` (`message_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_project_id` (`project_id`),
        KEY `idx_email_sent_at` (`email_sent_at`),
        KEY `idx_batch_id` (`batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `one_to_one_chat_email_notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `message_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `sender_id` INT(11) NOT NULL,
        `email_sent_at` INT(11) NOT NULL,
        `batch_id` VARCHAR(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_notification` (`message_id`, `user_id`),
        KEY `idx_message_id` (`message_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_sender_id` (`sender_id`),
        KEY `idx_email_sent_at` (`email_sent_at`),
        KEY `idx_batch_id` (`batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `task_chat_email_notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `message_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `task_id` INT(11) NOT NULL,
        `email_sent_at` INT(11) NOT NULL,
        `batch_id` VARCHAR(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_notification` (`message_id`, `user_id`, `task_id`),
        KEY `idx_message_id` (`message_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_task_id` (`task_id`),
        KEY `idx_email_sent_at` (`email_sent_at`),
        KEY `idx_batch_id` (`batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    // Users.assigned_team
    $inline[] = "ALTER TABLE `users` ADD COLUMN `assigned_team` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Comma-separated list of staff/admin user IDs assigned to this client' AFTER `teams_id`";
    $inline[] = "ALTER TABLE `users` ADD INDEX `idx_assigned_team` (`assigned_team`)";

    // Per-user payroll / login IP / attendance disable (matches migrations 3.20 + 3.25)
    $inline[] = "ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `base_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `assigned_team`,
    ADD COLUMN IF NOT EXISTS `attendance_deduction_mode` ENUM('fixed','percentage') DEFAULT NULL AFTER `base_salary`,
    ADD COLUMN IF NOT EXISTS `attendance_deduction_value` DECIMAL(12,2) DEFAULT NULL AFTER `attendance_deduction_mode`,
    ADD COLUMN IF NOT EXISTS `login_ip_restriction_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `attendance_deduction_value`,
    ADD COLUMN IF NOT EXISTS `allowed_login_ips` TEXT DEFAULT NULL AFTER `login_ip_restriction_enabled`,
    ADD COLUMN IF NOT EXISTS `attendance_disabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = attendance hidden/disabled for this user' AFTER `allowed_login_ips`";

    // Add email_muted to group_chat_members
    $inline[] = "ALTER TABLE `group_chat_members` ADD COLUMN `email_muted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Email mute preference' AFTER `left_at`";

    foreach ($inline as $statement) {
        installer_run_query($connect, $statement, $errors, $notes, 'group-chat');
    }

    // Run master SQL files if present
    $baseDir = dirname(__DIR__);
    installer_run_sql_file($connect, $baseDir . '/database_migration_group_chat_master.sql', $errors, $notes, 'group-chat master');
    installer_run_sql_file($connect, $baseDir . '/database_migration_group_chat_batch_emails.sql', $errors, $notes, 'group-chat batch');
}

// Helper: Email module tables and columns (inbox, threads, tracking, reports, etc.)
function installer_run_email_migrations($connect, &$errors, &$notes)
{
    $notes[] = 'Free edition: skipped workspace email migrations';
    return;
    $inline = [];

    // Base email tables
    $inline[] = "CREATE TABLE IF NOT EXISTS `email_accounts` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        provider ENUM('gmail', 'outlook', 'exchange', 'smtp') NOT NULL,
        email_address VARCHAR(255) NOT NULL,
        account_type ENUM('personal', 'team') DEFAULT 'personal',
        sender_name VARCHAR(255) DEFAULT NULL,
        is_default TINYINT(1) DEFAULT 0,
        sync_active TINYINT(1) DEFAULT 1,
        access_token TEXT DEFAULT NULL,
        refresh_token TEXT DEFAULT NULL,
        token_expiry DATETIME DEFAULT NULL,
        imap_host VARCHAR(255) DEFAULT NULL,
        imap_port INT DEFAULT 993,
        imap_username VARCHAR(255) DEFAULT NULL,
        imap_password TEXT DEFAULT NULL,
        smtp_host VARCHAR(255) DEFAULT NULL,
        smtp_port INT DEFAULT 587,
        smtp_username VARCHAR(255) DEFAULT NULL,
        smtp_password TEXT DEFAULT NULL,
        visibility ENUM('shared', 'private') DEFAULT 'private',
        sync_start_date DATETIME DEFAULT NULL,
        sync_all_folders TINYINT(1) DEFAULT 1,
        synced_folders TEXT DEFAULT NULL,
        track_opens TINYINT(1) DEFAULT 1,
        track_clicks TINYINT(1) DEFAULT 1,
        track_alerts TINYINT(1) DEFAULT 1,
        last_sync DATETIME DEFAULT NULL,
        sync_status VARCHAR(50) DEFAULT NULL,
        error_message TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_email (email_address),
        INDEX idx_sync_active (sync_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_messages` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        message_id VARCHAR(255) NOT NULL,
        thread_id VARCHAR(255) DEFAULT NULL,
        folder VARCHAR(100) DEFAULT 'INBOX',
        from_email VARCHAR(255) NOT NULL,
        from_name VARCHAR(255) DEFAULT NULL,
        to_emails TEXT NOT NULL,
        cc_emails TEXT DEFAULT NULL,
        bcc_emails TEXT DEFAULT NULL,
        subject VARCHAR(500) DEFAULT NULL,
        body_html TEXT DEFAULT NULL,
        body_text TEXT DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        is_starred TINYINT(1) DEFAULT 0,
        is_important TINYINT(1) DEFAULT 0,
        is_draft TINYINT(1) DEFAULT 0,
        is_sent TINYINT(1) DEFAULT 0,
        is_archived TINYINT(1) DEFAULT 0,
        opened_count INT DEFAULT 0,
        clicked_count INT DEFAULT 0,
        last_opened DATETIME DEFAULT NULL,
        attachments TEXT DEFAULT NULL,
        labels TEXT DEFAULT NULL,
        received_date DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
        UNIQUE KEY unique_message (account_id, message_id),
        INDEX idx_account_id (account_id),
        INDEX idx_folder (folder),
        INDEX idx_received_date (received_date),
        INDEX idx_is_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_attachments` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NULL,
        filename VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size BIGINT NOT NULL,
        mime_type VARCHAR(100) DEFAULT NULL,
        is_compressed TINYINT(1) DEFAULT 0,
        storage_quota_user_id INT DEFAULT NULL,
        content_id VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_message_id (message_id),
        INDEX idx_storage_quota (storage_quota_user_id),
        INDEX idx_content_id (content_id),
        FOREIGN KEY (message_id) REFERENCES email_messages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_deleted_messages` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        message_id VARCHAR(255) NOT NULL,
        thread_id VARCHAR(255) DEFAULT NULL,
        deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        deleted_by_user_id INT NOT NULL,
        FOREIGN KEY (account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (deleted_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_deleted_message (account_id, message_id),
        INDEX idx_account_id (account_id),
        INDEX idx_thread_id (thread_id),
        INDEX idx_deleted_at (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_signatures` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        signature_name VARCHAR(40) NOT NULL,
        signature_html TEXT NOT NULL,
        is_default TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
        INDEX idx_account_id (account_id),
        INDEX idx_is_default (is_default)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_account_shared_users` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_account_user (account_id, user_id),
        INDEX idx_account_id (account_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_labels` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(20) DEFAULT '#3b82f6',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_label (user_id, name),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_message_labels` (
        message_id INT NOT NULL,
        label_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (message_id, label_id),
        FOREIGN KEY (message_id) REFERENCES email_messages(id) ON DELETE CASCADE,
        FOREIGN KEY (label_id) REFERENCES email_labels(id) ON DELETE CASCADE,
        INDEX idx_label_id (label_id),
        INDEX idx_message_id (message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_thread_lead_links` (
        id INT(11) NOT NULL AUTO_INCREMENT,
        thread_id VARCHAR(255) NOT NULL,
        lead_id INT(11) NOT NULL,
        account_id INT(11) NOT NULL,
        created_by INT(11) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_thread_lead (thread_id, lead_id),
        KEY idx_thread_id (thread_id),
        KEY idx_lead_id (lead_id),
        KEY idx_account_id (account_id),
        KEY idx_created_by (created_by),
        CONSTRAINT fk_email_thread_lead_links_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_email_thread_lead_links_account FOREIGN KEY (account_id) REFERENCES email_accounts (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_email_thread_lead_links_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_tracking_history` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        tracking_token VARCHAR(64) NOT NULL,
        event_type ENUM('open', 'click') DEFAULT 'open',
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES email_messages(id) ON DELETE CASCADE,
        INDEX idx_message_id (message_id),
        INDEX idx_tracking_token (tracking_token),
        INDEX idx_opened_at (opened_at),
        INDEX idx_event_type (event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_report_logs` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        report_frequency VARCHAR(20) NOT NULL,
        report_period_start DATETIME NOT NULL,
        report_period_end DATETIME NOT NULL,
        accounts_included TEXT NULL,
        total_accounts INT DEFAULT 0,
        total_received INT DEFAULT 0,
        total_sent INT DEFAULT 0,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_frequency (user_id, report_frequency),
        INDEX idx_sent_at (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_report_queue` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        account_id INT NOT NULL,
        email_received_at DATETIME NOT NULL,
        queued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        processed TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
        INDEX idx_user_processed (user_id, processed),
        INDEX idx_queued_at (queued_at),
        INDEX idx_account_id (account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    foreach ($inline as $statement) {
        installer_run_query($connect, $statement, $errors, $notes, 'email');
    }

    // ALTER email_messages (visibility, body sizes, body_file_path, tracking, draft dedupe)
    $alters = [
        "ALTER TABLE `email_messages` MODIFY COLUMN `body_html` MEDIUMTEXT DEFAULT NULL, MODIFY COLUMN `body_text` MEDIUMTEXT DEFAULT NULL",
        "ALTER TABLE `email_messages` ADD COLUMN `visibility` ENUM('shared', 'private') DEFAULT NULL AFTER `is_archived`",
        "ALTER TABLE `email_messages` ADD INDEX `idx_visibility` (`visibility`)",
        "ALTER TABLE `email_messages` ADD COLUMN `body_file_path` VARCHAR(500) DEFAULT NULL COMMENT 'Relative path to encrypted email body file' AFTER `body_text`",
        "ALTER TABLE `email_messages` ADD INDEX `idx_body_file_path` (`body_file_path`)",
        "ALTER TABLE `email_messages` ADD COLUMN `tracking_token` VARCHAR(64) NULL AFTER `clicked_count`",
        "ALTER TABLE `email_messages` ADD COLUMN `tracking_enabled` TINYINT(1) DEFAULT 0 AFTER `tracking_token`",
        "ALTER TABLE `email_messages` ADD UNIQUE INDEX `idx_tracking_token` (`tracking_token`)",
        "ALTER TABLE `email_messages` ADD COLUMN `compose_session_id` VARCHAR(64) DEFAULT NULL COMMENT 'Draft dedupe: client session id for new compose; one draft per session' AFTER `updated_at`",
        "ALTER TABLE `email_messages` ADD INDEX `idx_compose_session` (`account_id`, `compose_session_id`)",
        "ALTER TABLE `email_messages` ADD UNIQUE INDEX `unique_account_compose_session` (`account_id`, `compose_session_id`)",
        "ALTER TABLE `email_accounts` ADD COLUMN `imap_secure_type` ENUM('ssl', 'tls', 'starttls', 'none') DEFAULT 'ssl' COMMENT 'IMAP secure connection type' AFTER `imap_port`",
        "ALTER TABLE `email_accounts` ADD COLUMN `smtp_secure_type` ENUM('ssl', 'tls', 'starttls', 'none') DEFAULT 'starttls' COMMENT 'SMTP secure connection type' AFTER `smtp_port`",
        "ALTER TABLE `email_accounts` ADD INDEX `idx_imap_secure_type` (`imap_secure_type`)",
        "ALTER TABLE `email_accounts` ADD INDEX `idx_smtp_secure_type` (`smtp_secure_type`)",
    ];
    foreach ($alters as $sql) {
        installer_run_query($connect, $sql, $errors, $notes, 'email-alter');
    }

    // Settings: email report + OAuth columns (use AFTER column that exists in install settings)
    $settingsAlters = [
        "ALTER TABLE `settings` ADD COLUMN `email_report_enabled` TINYINT(1) DEFAULT 0 COMMENT 'Enable/disable email reports' AFTER `module_discussions`",
        "ALTER TABLE `settings` ADD COLUMN `email_report_template` TEXT NULL COMMENT 'Email template for account summary reports' AFTER `email_report_enabled`",
        "ALTER TABLE `settings` ADD COLUMN `email_report_subject` VARCHAR(255) DEFAULT 'Email Account Summary Report' COMMENT 'Subject line for email reports' AFTER `email_report_template`",
        "ALTER TABLE `settings` ADD COLUMN `email_report_frequency` ENUM('instant', '30mins', '1hour', '6hours', '12hours', '24hours', 'weekly', 'monthly') DEFAULT '24hours' COMMENT 'Frequency for sending email reports' AFTER `email_report_subject`",
        "ALTER TABLE `settings` ADD COLUMN `gmail_client_id` VARCHAR(500) DEFAULT NULL",
        "ALTER TABLE `settings` ADD COLUMN `gmail_client_secret` VARCHAR(500) DEFAULT NULL",
        "ALTER TABLE `settings` ADD COLUMN `outlook_client_id` VARCHAR(500) DEFAULT NULL",
        "ALTER TABLE `settings` ADD COLUMN `outlook_client_secret` VARCHAR(500) DEFAULT NULL",
        "ALTER TABLE `settings` ADD COLUMN `outlook_tenant_id` VARCHAR(100) DEFAULT 'common'",
    ];
    foreach ($settingsAlters as $sql) {
        installer_run_query($connect, $sql, $errors, $notes, 'email-settings');
    }

    // Performance indexes on email_messages
    $indexes = [
        "ALTER TABLE `email_messages` ADD INDEX `idx_account_folder_thread_date` (`account_id`, `folder`, `thread_id`, `received_date`)",
        "ALTER TABLE `email_messages` ADD INDEX `idx_account_folder_read` (`account_id`, `folder`, `is_read`)",
        "ALTER TABLE `email_messages` ADD INDEX `idx_thread_id` (`thread_id`)",
        "ALTER TABLE `email_messages` ADD INDEX `idx_account_sent_draft` (`account_id`, `is_sent`, `is_draft`)",
        "ALTER TABLE `email_messages` ADD INDEX `idx_account_archived` (`account_id`, `is_archived`)",
        "ALTER TABLE `email_messages` ADD INDEX `idx_inbox_query` (`account_id`, `folder`, `is_sent`, `is_archived`, `received_date`)",
    ];
    foreach ($indexes as $sql) {
        installer_run_query($connect, $sql, $errors, $notes, 'email-index');
    }

    // Role permissions fix (dedupe + unique key)
    installer_run_query($connect, "DELETE t1 FROM role_permissions t1 INNER JOIN role_permissions t2 WHERE t1.id > t2.id AND t1.role_id = t2.role_id AND t1.permission_key = t2.permission_key", $errors, $notes, 'role-permissions-dedup');
    installer_run_query($connect, "ALTER TABLE role_permissions ADD UNIQUE KEY unique_role_permission (role_id, permission_key)", $errors, $notes, 'role-permissions-unique');
}

// Helper: Google OAuth tables and user table modifications
function installer_run_google_oauth_migrations($connect, &$errors, &$notes)
{
    if (defined('TASKSESSION_FREE_EDITION') && TASKSESSION_FREE_EDITION) {
        $notes[] = 'Free edition: skipped Google OAuth / login settings migrations';
        return;
    }
    // Create google_login_settings table
    $createTableSql = "CREATE TABLE IF NOT EXISTS `google_login_settings` (
        `id` int unsigned NOT NULL DEFAULT 1,
        `client_id` varchar(255) NOT NULL DEFAULT '',
        `client_secret` varchar(255) NOT NULL DEFAULT '',
        `redirect_uri` varchar(500) NOT NULL DEFAULT '',
        `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
        `allow_registration` tinyint(1) NOT NULL DEFAULT 0,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    installer_run_query($connect, $createTableSql, $errors, $notes, 'google-login-settings');
    
    // Check and add allow_registration column if it doesn't exist
    $checkAllowReg = mysqli_query($connect, "SHOW COLUMNS FROM google_login_settings LIKE 'allow_registration'");
    if (!$checkAllowReg || mysqli_num_rows($checkAllowReg) == 0) {
        $addAllowRegSql = "ALTER TABLE `google_login_settings` ADD COLUMN `allow_registration` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_enabled`";
        installer_run_query($connect, $addAllowRegSql, $errors, $notes, 'google-allow-registration');
    }
    
    // Handle MySQL version differences - check and add columns/indexes manually if needed
    $columns_to_check = [
        'google_sub' => "varchar(255) DEFAULT NULL",
        'auth_provider' => "enum('local','google') NOT NULL DEFAULT 'local'",
        'avatar_url' => "varchar(500) DEFAULT NULL",
        'email_verified' => "tinyint(1) NOT NULL DEFAULT '0'",
        'created_at' => "datetime NOT NULL DEFAULT CURRENT_TIMESTAMP",
        'updated_at' => "datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];
    
    foreach ($columns_to_check as $column => $definition) {
        $checkColumn = mysqli_query($connect, "SHOW COLUMNS FROM users LIKE '$column'");
        if (!$checkColumn || mysqli_num_rows($checkColumn) == 0) {
            $alterSql = "ALTER TABLE `users` ADD COLUMN `$column` $definition";
            installer_run_query($connect, $alterSql, $errors, $notes, 'google-oauth-column-' . $column);
        }
    }
    
    // Check and add unique index on google_sub
    $checkIndex = mysqli_query($connect, "SHOW INDEX FROM users WHERE Key_name = 'uniq_google_sub'");
    if (!$checkIndex || mysqli_num_rows($checkIndex) == 0) {
        $indexSql = "ALTER TABLE `users` ADD UNIQUE KEY `uniq_google_sub` (`google_sub`)";
        installer_run_query($connect, $indexSql, $errors, $notes, 'google-oauth-index');
    }
    
    // Ensure auth_provider enum includes 'google'
    $checkEnum = mysqli_query($connect, "SHOW COLUMNS FROM users WHERE Field = 'auth_provider'");
    if ($checkEnum && mysqli_num_rows($checkEnum) > 0) {
        $row = mysqli_fetch_assoc($checkEnum);
        // Check if enum contains 'google'
        if (strpos($row['Type'], 'google') === false) {
            $modifyEnum = "ALTER TABLE `users` MODIFY `auth_provider` ENUM('local','google') NOT NULL DEFAULT 'local'";
            installer_run_query($connect, $modifyEnum, $errors, $notes, 'google-oauth-enum');
        }
    } else {
        // Column doesn't exist, add it
        $addEnum = "ALTER TABLE `users` ADD COLUMN `auth_provider` ENUM('local','google') NOT NULL DEFAULT 'local'";
        installer_run_query($connect, $addEnum, $errors, $notes, 'google-oauth-enum-add');
    }
}

function installer_run_totp_auth_migrations($connect, &$errors, &$notes)
{
    $columns = array(
        'totp_secret' => 'TEXT NULL',
        'totp_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'totp_confirmed_at' => 'DATETIME NULL',
        'totp_backup_codes' => 'TEXT NULL',
        'totp_last_timestep' => 'BIGINT NULL',
        'session_epoch' => 'INT NOT NULL DEFAULT 1',
    );
    foreach ($columns as $column => $definition) {
        $checkColumn = mysqli_query($connect, "SHOW COLUMNS FROM users LIKE '" . mysqli_real_escape_string($connect, $column) . "'");
        if (!$checkColumn || mysqli_num_rows($checkColumn) == 0) {
            installer_run_query($connect, "ALTER TABLE `users` ADD COLUMN `$column` $definition", $errors, $notes, 'totp-column-' . $column);
        }
    }
    installer_run_query($connect, "CREATE TABLE IF NOT EXISTS `totp_settings` (
        `id` INT UNSIGNED NOT NULL DEFAULT 1,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `require_admin` TINYINT(1) NOT NULL DEFAULT 0,
        `require_staff` TINYINT(1) NOT NULL DEFAULT 0,
        `require_client` TINYINT(1) NOT NULL DEFAULT 0,
        `issuer_label` VARCHAR(255) NOT NULL DEFAULT '',
        `legacy_reset_wiped` TINYINT(1) NOT NULL DEFAULT 0,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $errors, $notes, 'totp-settings');
    installer_run_query($connect, "INSERT IGNORE INTO totp_settings (id, is_enabled, require_admin, require_staff, require_client, issuer_label, legacy_reset_wiped) VALUES (1, 0, 0, 0, 0, '', 0)", $errors, $notes, 'totp-settings-seed');
    $totpClientCol = mysqli_query($connect, "SHOW COLUMNS FROM totp_settings LIKE 'require_client'");
    if (!$totpClientCol || mysqli_num_rows($totpClientCol) == 0) {
        installer_run_query($connect, "ALTER TABLE `totp_settings` ADD COLUMN `require_client` TINYINT(1) NOT NULL DEFAULT 0 AFTER `require_staff`", $errors, $notes, 'totp-require-client');
    }
    installer_run_query($connect, "CREATE TABLE IF NOT EXISTS `password_reset_requests` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NULL,
        `ip_address` VARCHAR(45) NULL,
        `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_email_time` (`email`, `requested_at`),
        INDEX `idx_ip_time` (`ip_address`, `requested_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $errors, $notes, 'password-reset-requests');
    installer_run_query($connect, "UPDATE password_reset_tokens SET used = 1 WHERE used = 0 AND (token IS NULL OR token NOT LIKE 'sha256:%')", $errors, $notes, 'wipe-plaintext-reset-tokens');
    installer_run_query($connect, "UPDATE totp_settings SET legacy_reset_wiped = 1 WHERE id = 1", $errors, $notes, 'legacy-reset-wiped');
    installer_run_query($connect, "CREATE TABLE IF NOT EXISTS `trusted_devices` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `token_hash` CHAR(64) NOT NULL,
        `expires_at` DATETIME NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `last_used_at` DATETIME NULL,
        `user_agent` TEXT NULL,
        `ip_address` VARCHAR(45) NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        UNIQUE KEY `uq_trusted_hash` (`token_hash`),
        KEY `idx_trusted_user` (`user_id`),
        KEY `idx_trusted_expires` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4", $errors, $notes, 'trusted-devices');
}

// Helper: create/alter task reminders related structures
function installer_run_task_reminders_migrations($connect, &$errors, &$notes)
{
    $inline = [];
    
    // Add task reminder settings columns to settings table
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminders_enabled` TINYINT(1) DEFAULT 0 AFTER `task_chat_batch_email_subject`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_1_enabled` TINYINT(1) DEFAULT 0 AFTER `task_reminders_enabled`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_1_days` INT(11) DEFAULT 7 AFTER `task_reminder_1_enabled`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_1_type` ENUM('before', 'after') DEFAULT 'before' AFTER `task_reminder_1_days`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_1_subject` VARCHAR(255) DEFAULT '' AFTER `task_reminder_1_type`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_1_template` TEXT NULL AFTER `task_reminder_1_subject`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_2_enabled` TINYINT(1) DEFAULT 0 AFTER `task_reminder_1_template`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_2_days` INT(11) DEFAULT 3 AFTER `task_reminder_2_enabled`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_2_type` ENUM('before', 'after') DEFAULT 'after' AFTER `task_reminder_2_days`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_2_subject` VARCHAR(255) DEFAULT '' AFTER `task_reminder_2_type`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_2_template` TEXT NULL AFTER `task_reminder_2_subject`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_3_enabled` TINYINT(1) DEFAULT 0 AFTER `task_reminder_2_template`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_3_days` INT(11) DEFAULT 14 AFTER `task_reminder_3_enabled`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_3_type` ENUM('before', 'after') DEFAULT 'after' AFTER `task_reminder_3_days`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_3_subject` VARCHAR(255) DEFAULT '' AFTER `task_reminder_3_type`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_3_template` TEXT NULL AFTER `task_reminder_3_subject`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_send_to_staff` TINYINT(1) DEFAULT 1 AFTER `task_reminder_3_template`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_send_to_client` TINYINT(1) DEFAULT 0 AFTER `task_reminder_send_to_staff`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN `task_reminder_send_to_creator` TINYINT(1) DEFAULT 0 AFTER `task_reminder_send_to_client`";
    
    // Create table to track sent reminders (prevent duplicate sends)
    $inline[] = "CREATE TABLE IF NOT EXISTS `task_reminders_sent` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `task_id` INT(11) NOT NULL,
        `reminder_number` TINYINT(1) NOT NULL,
        `sent_date` DATETIME NOT NULL,
        `sent_to` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `task_id` (`task_id`),
        KEY `reminder_number` (`reminder_number`),
        KEY `sent_date` (`sent_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";
    
    foreach ($inline as $statement) {
        installer_run_query($connect, $statement, $errors, $notes, 'task-reminders');
    }
}

// Helper: cron_jobs table + seed
function installer_setup_cron_jobs($connect, &$errors, &$notes)
{
    $createTableSql = "
    CREATE TABLE IF NOT EXISTS `cron_jobs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `category` VARCHAR(50) NOT NULL,
        `duration` VARCHAR(50) DEFAULT '1 hour',
        `enabled` TINYINT(1) DEFAULT 1,
        `last_run` DATETIME DEFAULT NULL,
        `last_run_status` VARCHAR(20) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_category` (`category`),
        KEY `idx_enabled` (`enabled`),
        KEY `idx_last_run` (`last_run`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";
    installer_run_query($connect, $createTableSql, $errors, $notes, 'cron_jobs');

    $checkSql = "SELECT COUNT(*) as count FROM cron_jobs";
    $result = mysqli_query($connect, $checkSql);
    if ($result && ($row = mysqli_fetch_assoc($result))) {
        if ((int)$row['count'] > 0) {
            $notes[] = "cron_jobs seed skipped (records exist)";
            return;
        }
    }

    $cronJobs = [
        ['name' => 'Google Drive Token Refresh', 'file_path' => 'vendor/google/gdrive/cron_refresh_tokens.php', 'category' => 'Command', 'duration' => '6 hours', 'enabled' => 1],
        ['name' => 'Google Calendar Sync', 'file_path' => 'cron/g-calender-cron.php', 'category' => 'Google Drive', 'duration' => '15 minutes', 'enabled' => 1],
        ['name' => 'Recurring Invoices', 'file_path' => 'cron/cron_recurring_invoices.php', 'category' => 'Invoice', 'duration' => '1 day', 'enabled' => 1],
        ['name' => 'Payment Reminders', 'file_path' => 'cron/payment_reminders.php', 'category' => 'Invoice', 'duration' => '1 day', 'enabled' => 1],
        ['name' => 'Email Sync', 'file_path' => 'cron/email_sync.php', 'category' => 'Email', 'duration' => '5 minutes', 'enabled' => 1],
        ['name' => 'Email Report', 'file_path' => 'cron/email_report.php', 'category' => 'Email', 'duration' => '10 minutes', 'enabled' => 1],
        ['name' => 'Email Tracking Update', 'file_path' => 'cron/email_tracking_update.php', 'category' => 'Email', 'duration' => '10 minutes', 'enabled' => 1],
        ['name' => 'Group Chat Batch Emails', 'file_path' => 'cron/group_chat_batch_emails.php', 'category' => 'Messages', 'duration' => '5 minutes', 'enabled' => 1],
        ['name' => 'Discussion Chat Batch Emails', 'file_path' => 'cron/discussion_chat_batch_emails.php', 'category' => 'Messages', 'duration' => '5 minutes', 'enabled' => 1],
        ['name' => 'One-to-One Chat Batch Emails', 'file_path' => 'cron/one_to_one_chat_batch_emails.php', 'category' => 'Messages', 'duration' => '5 minutes', 'enabled' => 1],
        ['name' => 'Task Chat Batch Emails', 'file_path' => 'cron/task_chat_batch_emails.php', 'category' => 'Messages', 'duration' => '5 minutes', 'enabled' => 1],
        ['name' => 'Task Reminders', 'file_path' => 'cron/task_reminders.php', 'category' => 'Tasks', 'duration' => '1 day', 'enabled' => 1],
        ['name' => 'Recurring Tasks', 'file_path' => 'cron/cron_recurring_tasks.php', 'category' => 'Tasks', 'duration' => '1 day', 'enabled' => 1],
        ['name' => 'Attendance Auto Absent', 'file_path' => 'cron/attendance_auto_absent.php', 'category' => 'Attendance', 'duration' => '15 minutes', 'enabled' => 1],
        ['name' => 'Import Jobs Worker', 'file_path' => 'cron/import_jobs_worker.php', 'category' => 'System', 'duration' => '2 minutes', 'enabled' => 1],
        ['name' => 'Cleanup Inactive Sessions', 'file_path' => 'cleanup_inactive_sessions.php', 'category' => 'Session', 'duration' => '1 hour', 'enabled' => 1],
    ];
    $cronJobs = array_merge($cronJobs, installer_get_ecommerce_cron_jobs());
    if (function_exists('installer_free_is_premium_cron_path')) {
        $cronJobs = array_values(array_filter($cronJobs, static function ($job) {
            return empty($job['file_path']) || !installer_free_is_premium_cron_path($job['file_path']);
        }));
    }

    foreach ($cronJobs as $job) {
        $name = mysqli_real_escape_string($connect, $job['name']);
        $file_path = mysqli_real_escape_string($connect, $job['file_path']);
        $category = mysqli_real_escape_string($connect, $job['category']);
        $duration = mysqli_real_escape_string($connect, $job['duration']);
        $enabled = (int)$job['enabled'];
        $insertSql = "INSERT INTO cron_jobs (name, file_path, category, duration, enabled) VALUES ('{$name}', '{$file_path}', '{$category}', '{$duration}', {$enabled})";
        installer_run_query($connect, $insertSql, $errors, $notes, 'cron_jobs seed');
    }
}

// Attendance module: settings columns, policy/shift alters, leave seeds, cron row (tables are in $tables; user columns in group-chat migration)
function installer_run_attendance_module_post_migrations($connect, &$errors, &$notes)
{
    // Free edition: keep non-attendance settings columns; skip attendance seeds/tables alters.
    $keep = [];
    $keep[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_attendance` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Attendance module' AFTER `module_email`";
    $keep[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_ip_restriction` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable login IP restriction module (independent of attendance)' AFTER `module_attendance`";
    $keep[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_ecommerce` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enable/disable Ecommerce module' AFTER `module_ip_restriction`";
    $keep[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `global_allowed_ips` TEXT NULL DEFAULT NULL COMMENT 'Optional comma-separated IPs merged with per-user allowlist when restriction is on' AFTER `module_ecommerce`";
    $keep[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_time_tracking` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Task Time Tracking' AFTER `global_allowed_ips`";
    $keep[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_reports` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Admin Reports dashboard (admin only)' AFTER `module_time_tracking`";
    foreach ($keep as $sql) {
        installer_run_query($connect, $sql, $errors, $notes, 'free settings columns');
    }
    $notes[] = 'Free edition: skipped attendance-only migrations (leave seeds, attendance alters)';
    return;
    $inline = [];

    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_attendance` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Attendance module' AFTER `module_email`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_ip_restriction` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable login IP restriction module (independent of attendance)' AFTER `module_attendance`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_ecommerce` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enable/disable Ecommerce module' AFTER `module_ip_restriction`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `global_allowed_ips` TEXT NULL DEFAULT NULL COMMENT 'Optional comma-separated IPs merged with per-user allowlist when restriction is on' AFTER `module_ecommerce`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_time_tracking` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Task Time Tracking' AFTER `global_allowed_ips`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `module_reports` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Admin Reports dashboard (admin only)' AFTER `module_time_tracking`";

    $inline[] = "INSERT INTO `attendance_leave_types` (`name`, `code`, `is_paid`, `annual_quota`, `is_active`)
        SELECT 'Casual Leave', 'CL', 1, 12, 1 FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM attendance_leave_types WHERE code = 'CL' LIMIT 1)";
    $inline[] = "INSERT INTO `attendance_leave_types` (`name`, `code`, `is_paid`, `annual_quota`, `is_active`)
        SELECT 'Sick Leave', 'SL', 1, 10, 1 FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM attendance_leave_types WHERE code = 'SL' LIMIT 1)";
    $inline[] = "INSERT INTO `attendance_leave_types` (`name`, `code`, `is_paid`, `annual_quota`, `is_active`)
        SELECT 'Annual Leave', 'AL', 1, 18, 1 FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM attendance_leave_types WHERE code = 'AL' LIMIT 1)";
    $inline[] = "INSERT INTO `attendance_leave_types` (`name`, `code`, `is_paid`, `annual_quota`, `is_active`)
        SELECT 'Unpaid Leave', 'UL', 0, 0, 1 FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM attendance_leave_types WHERE code = 'UL' LIMIT 1)";

    $inline[] = "ALTER TABLE `attendance_policies`
        ADD COLUMN IF NOT EXISTS `salary_deduction_mode_default` ENUM('fixed','percentage') NOT NULL DEFAULT 'fixed' AFTER `overtime_eligible`,
        ADD COLUMN IF NOT EXISTS `salary_deduction_value_default` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `salary_deduction_mode_default`";
    $inline[] = "ALTER TABLE `attendance_policies`
        ADD COLUMN IF NOT EXISTS `closed_weekdays_json` TEXT NULL AFTER `overtime_eligible`";

    $inline[] = "ALTER TABLE `attendance_shifts`
        ADD COLUMN IF NOT EXISTS `allowed_weekdays_json` TEXT NULL AFTER `grace_minutes`";
    $inline[] = "ALTER TABLE `attendance_shifts`
        ADD COLUMN IF NOT EXISTS `use_policy_threshold_override` TINYINT(1) NOT NULL DEFAULT 0 AFTER `allowed_weekdays_json`";
    $inline[] = "ALTER TABLE `attendance_shifts`
        ADD COLUMN IF NOT EXISTS `weekday_time_overrides_json` TEXT NULL AFTER `allowed_weekdays_json`";
    $inline[] = "ALTER TABLE `attendance_records` MODIFY COLUMN `status` ENUM('present','absent','late','half_day','early_exit','on_leave','weekly_off','holiday','work_from_home','on_duty','missed_punch') NOT NULL DEFAULT 'present'";

    $inline[] = "INSERT INTO `cron_jobs` (`name`, `file_path`, `category`, `duration`, `enabled`)
        SELECT 'Attendance Auto Absent', 'cron/attendance_auto_absent.php', 'Attendance', '15 minutes', 1 FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM `cron_jobs` WHERE `file_path` = 'cron/attendance_auto_absent.php' LIMIT 1)";

    foreach ($inline as $statement) {
        installer_run_query($connect, $statement, $errors, $notes, 'attendance-module');
    }
}

// Import/export: idempotent cron row (matches includes/migrations/3.23.sql) for existing DBs
function installer_run_import_export_post_migrations($connect, &$errors, &$notes)
{
    $sql = "INSERT INTO `cron_jobs` (`name`, `file_path`, `category`, `duration`, `enabled`)
        SELECT 'Import Jobs Worker', 'cron/import_jobs_worker.php', 'System', '2 minutes', 1
        FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM `cron_jobs` WHERE `file_path` = 'cron/import_jobs_worker.php' LIMIT 1)";
    installer_run_query($connect, $sql, $errors, $notes, 'import-export-cron');
}

// Forms module: create all forms tables inline (no external SQL files)
function installer_run_forms_module_migrations($connect, &$errors, &$notes)
{
    $inline = [];

    $inline[] = "CREATE TABLE IF NOT EXISTS `forms` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `slug` VARCHAR(100) NOT NULL,
        `title` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `submit_button_text` VARCHAR(100) DEFAULT 'Submit',
        `success_message` TEXT DEFAULT NULL,
        `lead_source_id` INT(11) DEFAULT NULL,
        `assigned_status_id` INT(11) DEFAULT NULL,
        `allowed_domains` TEXT DEFAULT NULL,
        `settings_json` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_forms_slug` (`slug`),
        KEY `idx_forms_status` (`status`),
        KEY `idx_forms_lead_source` (`lead_source_id`),
        KEY `idx_forms_assigned_status` (`assigned_status_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `form_fields` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `form_id` INT(11) NOT NULL,
        `label` VARCHAR(150) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `type` VARCHAR(50) NOT NULL DEFAULT 'text',
        `placeholder` VARCHAR(255) DEFAULT NULL,
        `is_required` TINYINT(1) NOT NULL DEFAULT 0,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `options_json` TEXT DEFAULT NULL,
        `width` VARCHAR(20) NOT NULL DEFAULT 'full',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_form_fields_form_id` (`form_id`),
        KEY `idx_form_fields_sort` (`form_id`, `sort_order`),
        CONSTRAINT `fk_form_fields_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `form_integrations` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `source_key` VARCHAR(100) NOT NULL,
        `auth_token` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `webhook_path` VARCHAR(255) DEFAULT NULL,
        `allowed_method` VARCHAR(20) DEFAULT 'POST',
        `notes` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_form_integrations_source_key` (`source_key`),
        KEY `idx_form_integrations_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `form_field_mappings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `integration_id` INT(11) NOT NULL,
        `form_id` INT(11) DEFAULT NULL,
        `source_field` VARCHAR(100) NOT NULL,
        `destination_field` VARCHAR(100) NOT NULL,
        `destination_type` ENUM('lead_column','custom_field') NOT NULL DEFAULT 'lead_column',
        `is_required` TINYINT(1) NOT NULL DEFAULT 0,
        `default_value` VARCHAR(255) DEFAULT NULL,
        `ignore_field` TINYINT(1) NOT NULL DEFAULT 0,
        `transform_rule` VARCHAR(100) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_form_field_mappings_integration` (`integration_id`),
        KEY `idx_form_field_mappings_form` (`form_id`),
        CONSTRAINT `fk_form_field_mappings_integration` FOREIGN KEY (`integration_id`) REFERENCES `form_integrations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_form_field_mappings_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `form_submissions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `integration_id` INT(11) DEFAULT NULL,
        `form_id` INT(11) DEFAULT NULL,
        `source` VARCHAR(100) DEFAULT NULL,
        `request_method` VARCHAR(20) DEFAULT NULL,
        `request_headers` TEXT DEFAULT NULL,
        `raw_payload` LONGTEXT DEFAULT NULL,
        `parsed_payload` TEXT DEFAULT NULL,
        `mapped_payload` TEXT DEFAULT NULL,
        `lead_id` INT(11) DEFAULT NULL,
        `status` ENUM('success','failed') NOT NULL DEFAULT 'failed',
        `error_message` TEXT DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` VARCHAR(500) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_form_submissions_integration` (`integration_id`),
        KEY `idx_form_submissions_form` (`form_id`),
        KEY `idx_form_submissions_lead` (`lead_id`),
        KEY `idx_form_submissions_status` (`status`),
        KEY `idx_form_submissions_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `forms_debug_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `level` VARCHAR(20) NOT NULL DEFAULT 'info',
        `route` VARCHAR(255) DEFAULT NULL,
        `source` VARCHAR(100) DEFAULT NULL,
        `request_id` VARCHAR(64) DEFAULT NULL,
        `message` VARCHAR(500) NOT NULL,
        `context_json` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_forms_debug_logs_level` (`level`),
        KEY `idx_forms_debug_logs_created` (`created_at`),
        KEY `idx_forms_debug_logs_request_id` (`request_id`),
        KEY `idx_forms_debug_logs_source` (`source`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    foreach ($inline as $statement) {
        installer_run_query($connect, $statement, $errors, $notes, 'forms-tables');
    }

    // 007: Add last_name to leads (if not exists)
    $check = mysqli_query($connect, "SHOW COLUMNS FROM leads LIKE 'last_name'");
    if (!$check || mysqli_num_rows($check) == 0) {
        installer_run_query($connect, "ALTER TABLE `leads` ADD COLUMN `last_name` VARCHAR(150) DEFAULT NULL AFTER `name`", $errors, $notes, 'forms-007-leads-last_name');
    } else {
        $notes[] = 'forms-007-leads-last_name skipped (already exists)';
    }

    // 008: Add width to form_fields (if not exists â€“ for existing DBs that had form_fields before width was added)
    $checkTable = mysqli_query($connect, "SHOW TABLES LIKE 'form_fields'");
    if ($checkTable && mysqli_num_rows($checkTable) > 0) {
        $checkCol = mysqli_query($connect, "SHOW COLUMNS FROM form_fields LIKE 'width'");
        if (!$checkCol || mysqli_num_rows($checkCol) == 0) {
            installer_run_query($connect, "ALTER TABLE `form_fields` ADD COLUMN `width` VARCHAR(20) NOT NULL DEFAULT 'full' AFTER `options_json`", $errors, $notes, 'forms-008-form_fields-width');
        } else {
            $notes[] = 'forms-008-form_fields-width skipped (already exists)';
        }
    }
}

// Future modules: time tracking, quotations, integrations, server stats, chat prefs (schema only)
function installer_run_future_modules_migrations($connect, &$errors, &$notes)
{
    $inline = [];

    $inline[] = "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `last_name` VARCHAR(150) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `firstName`";
    $inline[] = "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `username` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `email`";

    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `payment_reminder_notify_admin_in_app` TINYINT(1) NOT NULL DEFAULT 1 AFTER `payment_reminder_send_to_staff`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_client_one_to_one` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Send chat batch emails to clients (1:1)' AFTER `payment_reminder_notify_admin_in_app`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_client_group` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Send chat batch emails to clients (group)' AFTER `chat_email_client_one_to_one`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_client_task` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Send chat batch emails to clients (task)' AFTER `chat_email_client_group`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_client_discussion` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Send chat batch emails to clients (project discussion)' AFTER `chat_email_client_task`";

    // Version 3.24: chat email policy toggles + per-context / per-user tables (includes/migrations/3.24.sql)
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_notify_admin` TINYINT(1) NOT NULL DEFAULT 1 AFTER `chat_refresh_interval`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_notify_staff` TINYINT(1) NOT NULL DEFAULT 1 AFTER `chat_email_notify_admin`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_notify_client` TINYINT(1) NOT NULL DEFAULT 1 AFTER `chat_email_notify_staff`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `chat_email_notify_mention` TINYINT(1) NOT NULL DEFAULT 1 AFTER `chat_email_notify_client`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `presence_toast_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `chat_email_notify_mention`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `presence_beep_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `presence_toast_enabled`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `presence_staff_toast_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `presence_beep_enabled`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `browser_push_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `presence_staff_toast_enabled`";
    $inline[] = "ALTER TABLE `settings` ADD COLUMN IF NOT EXISTS `setup_guide_pending` TINYINT(1) NOT NULL DEFAULT 0 AFTER `browser_push_enabled`";
    $inline[] = "CREATE TABLE IF NOT EXISTS `setup_guide_prefs` (
        `user_id` INT NOT NULL,
        `dismissed_forever` TINYINT(1) NOT NULL DEFAULT 0,
        `wizard_minimized` TINYINT(1) NOT NULL DEFAULT 0,
        `completed_steps` TEXT NULL,
        `updated_at` DATETIME NULL,
        PRIMARY KEY (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $inline[] = "CREATE TABLE IF NOT EXISTS `chat_email_context` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $inline[] = "CREATE TABLE IF NOT EXISTS `chat_email_user_preference` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` int(10) unsigned NOT NULL,
        `context_type` enum('project','group','task') NOT NULL,
        `context_id` int(10) unsigned NOT NULL,
        `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
        `updated_at` int(10) unsigned NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_ctx` (`user_id`,`context_type`,`context_id`),
        KEY `idx_ctx` (`context_type`,`context_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `task_time_entries` (
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
        CONSTRAINT `fk_tte_task` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_tte_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `task_timer_sessions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `task_time_reports_daily` (
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
        CONSTRAINT `fk_ttrd_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_ttrd_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`p_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_ttrd_task` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `project_activities` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_id` INT(11) NOT NULL,
        `actor_user_id` INT(11) DEFAULT NULL,
        `entity_type` VARCHAR(64) NOT NULL DEFAULT '',
        `entity_id` INT(11) DEFAULT NULL,
        `action` VARCHAR(64) NOT NULL DEFAULT '',
        `metadata` JSON NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_pa_project_created` (`project_id`, `created_at`),
        CONSTRAINT `fk_pa_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`p_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_pa_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `integration_zapier` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `webhook_secret` VARCHAR(255) DEFAULT NULL,
        `api_key_suffix` VARCHAR(32) DEFAULT NULL COMMENT 'Display-only last chars',
        `settings_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `integration_zapier_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `direction` ENUM('inbound','outbound') NOT NULL DEFAULT 'inbound',
        `event_type` VARCHAR(128) NOT NULL DEFAULT '',
        `status_code` SMALLINT DEFAULT NULL,
        `request_snippet` TEXT NULL,
        `response_snippet` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_izl_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `integration_google_meet` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) DEFAULT NULL COMMENT 'NULL = org default',
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `client_credentials_json` LONGTEXT NULL COMMENT 'Encrypt in app layer',
        `refresh_token` TEXT NULL,
        `settings_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_igm_user` (`user_id`),
        CONSTRAINT `fk_igm_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `integration_chatgpt` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `api_key_suffix` VARCHAR(32) DEFAULT NULL,
        `default_model` VARCHAR(64) NOT NULL DEFAULT 'gpt-4o-mini',
        `settings_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `integration_chatgpt_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) DEFAULT NULL,
        `model` VARCHAR(64) DEFAULT NULL,
        `tokens_used` INT UNSIGNED DEFAULT NULL,
        `request_snippet` TEXT NULL,
        `response_snippet` TEXT NULL,
        `status` VARCHAR(32) NOT NULL DEFAULT 'ok',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_icgl_created` (`created_at`),
        KEY `idx_icgl_user` (`user_id`),
        CONSTRAINT `fk_icgl_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `integration_webhook_deliveries` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `integration_key` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'zapier, custom, etc.',
        `event_type` VARCHAR(128) NOT NULL DEFAULT '',
        `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
        `payload_snippet` TEXT NULL,
        `response_snippet` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_iwd_key_created` (`integration_key`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `quotations` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_id` INT(11) DEFAULT NULL,
        `client_user_id` INT(11) DEFAULT NULL,
        `quote_number` VARCHAR(64) NOT NULL DEFAULT '',
        `title` VARCHAR(255) NOT NULL DEFAULT '',
        `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
        `currency` VARCHAR(10) DEFAULT NULL,
        `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `tax_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `valid_until` DATE DEFAULT NULL,
        `notes` TEXT NULL,
        `proposal_body` LONGTEXT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_q_project` (`project_id`),
        KEY `idx_q_client` (`client_user_id`),
        KEY `idx_q_status` (`status`),
        CONSTRAINT `fk_q_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`p_id`) ON DELETE SET NULL,
        CONSTRAINT `fk_q_client` FOREIGN KEY (`client_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_q_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `quotation_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `quotation_id` INT UNSIGNED NOT NULL,
        `line_order` INT UNSIGNED NOT NULL DEFAULT 0,
        `description` TEXT NOT NULL,
        `quantity` DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
        `unit_price` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
        `line_total` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
        PRIMARY KEY (`id`),
        KEY `idx_qi_quote` (`quotation_id`),
        CONSTRAINT `fk_qi_quote` FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `quotation_status_history` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `quotation_id` INT UNSIGNED NOT NULL,
        `from_status` VARCHAR(32) DEFAULT NULL,
        `to_status` VARCHAR(32) NOT NULL,
        `changed_by` INT(11) DEFAULT NULL,
        `note` VARCHAR(500) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_qsh_quote` (`quotation_id`),
        CONSTRAINT `fk_qsh_quote` FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_qsh_user` FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `server_stats_snapshots` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `collected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `cpu_percent` DECIMAL(5,2) DEFAULT NULL,
        `memory_percent` DECIMAL(5,2) DEFAULT NULL,
        `disk_percent` DECIMAL(5,2) DEFAULT NULL,
        `load_avg_1` DECIMAL(8,3) DEFAULT NULL,
        `network_in_mbps` DECIMAL(12,4) DEFAULT NULL,
        `network_out_mbps` DECIMAL(12,4) DEFAULT NULL,
        `extra_json` LONGTEXT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_sss_collected` (`collected_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `user_chat_notification_prefs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `channel` ENUM('one_to_one','group','task','discussion') NOT NULL,
        `email_enabled` TINYINT(1) DEFAULT NULL COMMENT 'NULL = inherit settings',
        `in_app_enabled` TINYINT(1) DEFAULT NULL,
        `push_enabled` TINYINT(1) DEFAULT NULL,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_ucnp_user_channel` (`user_id`, `channel`),
        CONSTRAINT `fk_ucnp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    foreach ($inline as $statement) {
        installer_run_query($connect, $statement, $errors, $notes, 'future-modules');
    }

    $checkIdx = mysqli_query($connect, "SHOW INDEX FROM `users` WHERE Key_name = 'uniq_username'");
    if ($checkIdx && mysqli_num_rows($checkIdx) == 0) {
        installer_run_query($connect, "ALTER TABLE `users` ADD UNIQUE KEY `uniq_username` (`username`)", $errors, $notes, 'future-modules-username-unique');
    } else {
        $notes[] = 'future-modules-username-unique skipped (already exists)';
    }
}

/**
 * Fresh install only: CREATE TABLE + INSERT seeds. No ALTER (upgrades use install/system.php).
 */
function installer_run_fresh_install_create_tables_only($connect, &$errors, &$notes)
{
    $creates = [];

    // Group chat tables (full schema â€” no post-create ALTER)
    $creates[] = "CREATE TABLE IF NOT EXISTS `group_chats` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(255) NOT NULL,
        `avatar` VARCHAR(255) DEFAULT NULL,
        `created_by` INT(11) NOT NULL,
        `created_at` INT(11) NOT NULL,
        `updated_at` INT(11) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_created_by` (`created_by`),
        KEY `idx_updated_at` (`updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `group_chat_members` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `group_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `role` ENUM('admin','member') NOT NULL DEFAULT 'member',
        `joined_at` INT(11) NOT NULL,
        `left_at` INT(11) DEFAULT NULL,
        `email_muted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Email mute preference',
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_group_user_active` (`group_id`,`user_id`,`left_at`),
        KEY `idx_group_id` (`group_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_active_members` (`group_id`,`left_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `group_chat_messages` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `group_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `message` TEXT NOT NULL,
        `time` INT(11) NOT NULL,
        `edited` TINYINT(1) NOT NULL DEFAULT 0,
        `edit_time` INT(11) DEFAULT NULL,
        `reply_to` INT(11) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_group_id` (`group_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_time` (`time`),
        KEY `idx_reply_to` (`reply_to`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `group_chat_reads` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `message_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `read_time` INT(11) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_read` (`message_id`,`user_id`),
        KEY `message_id` (`message_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `group_chat_email_notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `message_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `group_id` INT(11) NOT NULL,
        `email_sent_at` INT(11) NOT NULL,
        `batch_id` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_message_user` (`message_id`,`user_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_group_id` (`group_id`),
        KEY `idx_email_sent_at` (`email_sent_at`),
        KEY `idx_batch_id` (`batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `discussion_chat_email_notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `message_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `project_id` INT(11) NOT NULL,
        `email_sent_at` INT(11) NOT NULL,
        `batch_id` VARCHAR(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_notification` (`message_id`, `user_id`, `project_id`),
        KEY `idx_message_id` (`message_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_project_id` (`project_id`),
        KEY `idx_email_sent_at` (`email_sent_at`),
        KEY `idx_batch_id` (`batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `one_to_one_chat_email_notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `message_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `sender_id` INT(11) NOT NULL,
        `email_sent_at` INT(11) NOT NULL,
        `batch_id` VARCHAR(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_notification` (`message_id`, `user_id`),
        KEY `idx_message_id` (`message_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_sender_id` (`sender_id`),
        KEY `idx_email_sent_at` (`email_sent_at`),
        KEY `idx_batch_id` (`batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `task_chat_email_notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `message_id` INT(11) NOT NULL,
        `user_id` INT(11) NOT NULL,
        `task_id` INT(11) NOT NULL,
        `email_sent_at` INT(11) NOT NULL,
        `batch_id` VARCHAR(100) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unique_notification` (`message_id`, `user_id`, `task_id`),
        KEY `idx_message_id` (`message_id`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_task_id` (`task_id`),
        KEY `idx_email_sent_at` (`email_sent_at`),
        KEY `idx_batch_id` (`batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `chat_message_reactions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `chat_message_stars` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `chat_type` ENUM('one_to_one','group','discussion','task') NOT NULL,
      `message_id` INT(11) NOT NULL,
      `user_id` INT(11) NOT NULL,
      `created_at` INT(11) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_star` (`chat_type`,`message_id`,`user_id`),
      KEY `idx_msg` (`chat_type`,`message_id`),
      KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `chat_message_pins` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `chat_type` ENUM('one_to_one','group','discussion','task') NOT NULL,
      `context_id` INT(11) NOT NULL,
      `message_id` INT(11) NOT NULL,
      `pinned_by` INT(11) NOT NULL,
      `pinned_at` INT(11) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_pin_context` (`chat_type`,`context_id`),
      KEY `idx_message` (`message_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `task_reminders_sent` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `task_id` INT(11) NOT NULL,
        `reminder_number` TINYINT(1) NOT NULL,
        `sent_date` DATETIME NOT NULL,
        `sent_to` VARCHAR(50) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `task_id` (`task_id`),
        KEY `reminder_number` (`reminder_number`),
        KEY `sent_date` (`sent_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `google_login_settings` (
        `id` int unsigned NOT NULL DEFAULT 1,
        `client_id` varchar(255) NOT NULL DEFAULT '',
        `client_secret` varchar(255) NOT NULL DEFAULT '',
        `redirect_uri` varchar(500) NOT NULL DEFAULT '',
        `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
        `allow_registration` tinyint(1) NOT NULL DEFAULT 0,
        `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $creates[] = "CREATE TABLE IF NOT EXISTS `push_subscriptions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `endpoint` text NOT NULL,
        `p256dh` varchar(255) NOT NULL,
        `auth` varchar(255) NOT NULL,
        `browser` varchar(64) DEFAULT NULL,
        `device` varchar(64) DEFAULT NULL,
        `platform` varchar(64) DEFAULT NULL,
        `user_agent` varchar(512) DEFAULT NULL,
        `content_encoding` varchar(32) DEFAULT 'aesgcm',
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `last_used_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `idx_push_subs_active` (`user_id`, `is_active`),
        UNIQUE KEY `uq_push_endpoint` (`endpoint`(255)),
        CONSTRAINT `push_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `chat_push_throttle` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL COMMENT 'recipient',
        `chat_type` enum('one_to_one','group','discussion','task') NOT NULL,
        `context_id` int(11) NOT NULL COMMENT 'peer_id, group_id, project_id, or task_id',
        `last_push_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_chat_push_throttle` (`user_id`, `chat_type`, `context_id`),
        KEY `idx_chat_push_throttle_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    $creates[] = "CREATE TABLE IF NOT EXISTS `push_notification_payload_cache` (
        `user_id` int(11) NOT NULL,
        `tag` varchar(128) NOT NULL,
        `title` varchar(255) NOT NULL,
        `body` text NOT NULL,
        `icon` text NULL,
        `payload_json` text NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`user_id`, `tag`),
        KEY `idx_push_payload_cache_user_time` (`user_id`, `updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci";

    // Delegate email + forms CREATE TABLE blocks (those helpers also run ALTER â€” run CREATE lines only here via dedicated calls)
    installer_run_email_migrations_create_only($connect, $errors, $notes);
    installer_run_forms_module_migrations_create_only($connect, $errors, $notes);

    if (function_exists('installer_free_filter_sql_list')) {
        $creates = installer_free_filter_sql_list($creates);
        $notes[] = 'Free edition: omitted Pro-only CREATE TABLE statements from fresh-install helper';
    }

    foreach ($creates as $statement) {
        installer_run_query($connect, $statement, $errors, $notes, 'fresh-install-create');
    }

    installer_setup_cron_jobs($connect, $errors, $notes);
    installer_run_42_server_monitor_migrations($connect, $errors);
    installer_run_46_performance_create_tables($connect, $errors);
    installer_run_410_migrations($connect, $errors);
    installer_run_totp_auth_migrations($connect, $errors, $notes);

    $seeds = [
        "INSERT INTO `attendance_leave_types` (`name`, `code`, `is_paid`, `annual_quota`, `is_active`)
            SELECT 'Casual Leave', 'CL', 1, 12, 1 FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM attendance_leave_types WHERE code = 'CL' LIMIT 1)",
        "INSERT INTO `attendance_leave_types` (`name`, `code`, `is_paid`, `annual_quota`, `is_active`)
            SELECT 'Sick Leave', 'SL', 1, 10, 1 FROM DUAL
            WHERE NOT EXISTS (SELECT 1 FROM attendance_leave_types WHERE code = 'SL' LIMIT 1)",
    ];
    foreach ($seeds as $sql) {
        installer_run_query($connect, $sql, $errors, $notes, 'fresh-install-seed');
    }
}

function installer_run_email_migrations_create_only($connect, &$errors, &$notes)
{
    $notes[] = 'Free edition: skipped email create-only migrations';
    return;
    $inline = [];
    $inline[] = "CREATE TABLE IF NOT EXISTS `email_accounts` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        provider ENUM('gmail', 'outlook', 'exchange', 'smtp') NOT NULL,
        email_address VARCHAR(255) NOT NULL,
        account_type ENUM('personal', 'team') DEFAULT 'personal',
        sender_name VARCHAR(255) DEFAULT NULL,
        is_default TINYINT(1) DEFAULT 0,
        sync_active TINYINT(1) DEFAULT 1,
        access_token TEXT DEFAULT NULL,
        refresh_token TEXT DEFAULT NULL,
        token_expiry DATETIME DEFAULT NULL,
        imap_host VARCHAR(255) DEFAULT NULL,
        imap_port INT DEFAULT 993,
        imap_secure_type ENUM('ssl', 'tls', 'starttls', 'none') DEFAULT 'ssl',
        imap_username VARCHAR(255) DEFAULT NULL,
        imap_password TEXT DEFAULT NULL,
        smtp_host VARCHAR(255) DEFAULT NULL,
        smtp_port INT DEFAULT 587,
        smtp_secure_type ENUM('ssl', 'tls', 'starttls', 'none') DEFAULT 'starttls',
        smtp_username VARCHAR(255) DEFAULT NULL,
        smtp_password TEXT DEFAULT NULL,
        visibility ENUM('shared', 'private') DEFAULT 'private',
        sync_start_date DATETIME DEFAULT NULL,
        sync_all_folders TINYINT(1) DEFAULT 1,
        synced_folders TEXT DEFAULT NULL,
        track_opens TINYINT(1) DEFAULT 1,
        track_clicks TINYINT(1) DEFAULT 1,
        track_alerts TINYINT(1) DEFAULT 1,
        last_sync DATETIME DEFAULT NULL,
        sync_status VARCHAR(50) DEFAULT NULL,
        error_message TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_email (email_address),
        INDEX idx_sync_active (sync_active),
        INDEX idx_imap_secure_type (imap_secure_type),
        INDEX idx_smtp_secure_type (smtp_secure_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_messages` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        message_id VARCHAR(255) NOT NULL,
        thread_id VARCHAR(255) DEFAULT NULL,
        folder VARCHAR(100) DEFAULT 'INBOX',
        from_email VARCHAR(255) NOT NULL,
        from_name VARCHAR(255) DEFAULT NULL,
        to_emails TEXT NOT NULL,
        cc_emails TEXT DEFAULT NULL,
        bcc_emails TEXT DEFAULT NULL,
        subject VARCHAR(500) DEFAULT NULL,
        body_html MEDIUMTEXT DEFAULT NULL,
        body_text MEDIUMTEXT DEFAULT NULL,
        body_file_path VARCHAR(500) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        is_starred TINYINT(1) DEFAULT 0,
        is_important TINYINT(1) DEFAULT 0,
        is_draft TINYINT(1) DEFAULT 0,
        is_sent TINYINT(1) DEFAULT 0,
        is_archived TINYINT(1) DEFAULT 0,
        visibility ENUM('shared', 'private') DEFAULT NULL,
        opened_count INT DEFAULT 0,
        clicked_count INT DEFAULT 0,
        tracking_token VARCHAR(64) NULL,
        tracking_enabled TINYINT(1) DEFAULT 0,
        last_opened DATETIME DEFAULT NULL,
        attachments TEXT DEFAULT NULL,
        labels TEXT DEFAULT NULL,
        received_date DATETIME NOT NULL,
        compose_session_id VARCHAR(64) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
        UNIQUE KEY unique_message (account_id, message_id),
        UNIQUE KEY idx_tracking_token (tracking_token),
        UNIQUE KEY unique_account_compose_session (account_id, compose_session_id),
        INDEX idx_account_id (account_id),
        INDEX idx_folder (folder),
        INDEX idx_received_date (received_date),
        INDEX idx_is_read (is_read),
        INDEX idx_visibility (visibility),
        INDEX idx_body_file_path (body_file_path),
        INDEX idx_compose_session (account_id, compose_session_id),
        INDEX idx_account_folder_thread_date (account_id, folder, thread_id, received_date),
        INDEX idx_account_folder_read (account_id, folder, is_read),
        INDEX idx_thread_id (thread_id),
        INDEX idx_account_sent_draft (account_id, is_sent, is_draft),
        INDEX idx_account_archived (account_id, is_archived),
        INDEX idx_inbox_query (account_id, folder, is_sent, is_archived, received_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_attachments` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NULL,
        filename VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size BIGINT NOT NULL,
        mime_type VARCHAR(100) DEFAULT NULL,
        is_compressed TINYINT(1) DEFAULT 0,
        storage_quota_user_id INT DEFAULT NULL,
        content_id VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_message_id (message_id),
        INDEX idx_storage_quota (storage_quota_user_id),
        INDEX idx_content_id (content_id),
        FOREIGN KEY (message_id) REFERENCES email_messages(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_deleted_messages` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        message_id VARCHAR(255) NOT NULL,
        thread_id VARCHAR(255) DEFAULT NULL,
        deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        deleted_by_user_id INT NOT NULL,
        FOREIGN KEY (account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (deleted_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_deleted_message (account_id, message_id),
        INDEX idx_account_id (account_id),
        INDEX idx_thread_id (thread_id),
        INDEX idx_deleted_at (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_signatures` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        signature_name VARCHAR(40) NOT NULL,
        signature_html TEXT NOT NULL,
        is_default TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
        INDEX idx_account_id (account_id),
        INDEX idx_is_default (is_default)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_account_shared_users` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        account_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_account_user (account_id, user_id),
        INDEX idx_account_id (account_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_labels` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        color VARCHAR(20) DEFAULT '#3b82f6',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_label (user_id, name),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_message_labels` (
        message_id INT NOT NULL,
        label_id INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (message_id, label_id),
        FOREIGN KEY (message_id) REFERENCES email_messages(id) ON DELETE CASCADE,
        FOREIGN KEY (label_id) REFERENCES email_labels(id) ON DELETE CASCADE,
        INDEX idx_label_id (label_id),
        INDEX idx_message_id (message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_thread_lead_links` (
        id INT(11) NOT NULL AUTO_INCREMENT,
        thread_id VARCHAR(255) NOT NULL,
        lead_id INT(11) NOT NULL,
        account_id INT(11) NOT NULL,
        created_by INT(11) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_thread_lead (thread_id, lead_id),
        KEY idx_thread_id (thread_id),
        KEY idx_lead_id (lead_id),
        KEY idx_account_id (account_id),
        KEY idx_created_by (created_by),
        CONSTRAINT fk_email_thread_lead_links_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_email_thread_lead_links_account FOREIGN KEY (account_id) REFERENCES email_accounts (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_email_thread_lead_links_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_tracking_history` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message_id INT NOT NULL,
        tracking_token VARCHAR(64) NOT NULL,
        event_type ENUM('open', 'click') DEFAULT 'open',
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT DEFAULT NULL,
        opened_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (message_id) REFERENCES email_messages(id) ON DELETE CASCADE,
        INDEX idx_message_id (message_id),
        INDEX idx_tracking_token (tracking_token),
        INDEX idx_opened_at (opened_at),
        INDEX idx_event_type (event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_report_logs` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        report_frequency VARCHAR(20) NOT NULL,
        report_period_start DATETIME NOT NULL,
        report_period_end DATETIME NOT NULL,
        accounts_included TEXT NULL,
        total_accounts INT DEFAULT 0,
        total_received INT DEFAULT 0,
        total_sent INT DEFAULT 0,
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_frequency (user_id, report_frequency),
        INDEX idx_sent_at (sent_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $inline[] = "CREATE TABLE IF NOT EXISTS `email_report_queue` (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        account_id INT NOT NULL,
        email_received_at DATETIME NOT NULL,
        queued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        processed TINYINT(1) DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (account_id) REFERENCES email_accounts(id) ON DELETE CASCADE,
        INDEX idx_user_processed (user_id, processed),
        INDEX idx_queued_at (queued_at),
        INDEX idx_account_id (account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    foreach ($inline as $statement) {
        installer_run_query($connect, $statement, $errors, $notes, 'fresh-install-email');
    }
}

function installer_run_forms_module_migrations_create_only($connect, &$errors, &$notes)
{
    $inline = [];
    $inline[] = "CREATE TABLE IF NOT EXISTS `forms` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `slug` VARCHAR(100) NOT NULL,
        `title` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `submit_button_text` VARCHAR(100) DEFAULT 'Submit',
        `success_message` TEXT DEFAULT NULL,
        `lead_source_id` INT(11) DEFAULT NULL,
        `assigned_status_id` INT(11) DEFAULT NULL,
        `allowed_domains` TEXT DEFAULT NULL,
        `settings_json` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_forms_slug` (`slug`),
        KEY `idx_forms_status` (`status`),
        KEY `idx_forms_lead_source` (`lead_source_id`),
        KEY `idx_forms_assigned_status` (`assigned_status_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `form_fields` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `form_id` INT(11) NOT NULL,
        `label` VARCHAR(150) NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `type` VARCHAR(50) NOT NULL DEFAULT 'text',
        `placeholder` VARCHAR(255) DEFAULT NULL,
        `is_required` TINYINT(1) NOT NULL DEFAULT 0,
        `sort_order` INT(11) NOT NULL DEFAULT 0,
        `options_json` TEXT DEFAULT NULL,
        `width` VARCHAR(20) NOT NULL DEFAULT 'full',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_form_fields_form_id` (`form_id`),
        KEY `idx_form_fields_sort` (`form_id`, `sort_order`),
        CONSTRAINT `fk_form_fields_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `form_integrations` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `source_key` VARCHAR(100) NOT NULL,
        `auth_token` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
        `webhook_path` VARCHAR(255) DEFAULT NULL,
        `allowed_method` VARCHAR(20) DEFAULT 'POST',
        `notes` TEXT DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_form_integrations_source_key` (`source_key`),
        KEY `idx_form_integrations_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `form_field_mappings` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `integration_id` INT(11) NOT NULL,
        `form_id` INT(11) DEFAULT NULL,
        `source_field` VARCHAR(100) NOT NULL,
        `destination_field` VARCHAR(100) NOT NULL,
        `destination_type` ENUM('lead_column','custom_field') NOT NULL DEFAULT 'lead_column',
        `is_required` TINYINT(1) NOT NULL DEFAULT 0,
        `default_value` VARCHAR(255) DEFAULT NULL,
        `ignore_field` TINYINT(1) NOT NULL DEFAULT 0,
        `transform_rule` VARCHAR(100) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_form_field_mappings_integration` (`integration_id`),
        KEY `idx_form_field_mappings_form` (`form_id`),
        CONSTRAINT `fk_form_field_mappings_integration` FOREIGN KEY (`integration_id`) REFERENCES `form_integrations` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT `fk_form_field_mappings_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `form_submissions` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `integration_id` INT(11) DEFAULT NULL,
        `form_id` INT(11) DEFAULT NULL,
        `source` VARCHAR(100) DEFAULT NULL,
        `request_method` VARCHAR(20) DEFAULT NULL,
        `request_headers` TEXT DEFAULT NULL,
        `raw_payload` LONGTEXT DEFAULT NULL,
        `parsed_payload` TEXT DEFAULT NULL,
        `mapped_payload` TEXT DEFAULT NULL,
        `lead_id` INT(11) DEFAULT NULL,
        `status` ENUM('success','failed') NOT NULL DEFAULT 'failed',
        `error_message` TEXT DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `user_agent` VARCHAR(500) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_form_submissions_integration` (`integration_id`),
        KEY `idx_form_submissions_form` (`form_id`),
        KEY `idx_form_submissions_lead` (`lead_id`),
        CONSTRAINT `fk_form_submissions_integration` FOREIGN KEY (`integration_id`) REFERENCES `form_integrations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT `fk_form_submissions_form` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
        CONSTRAINT `fk_form_submissions_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $inline[] = "CREATE TABLE IF NOT EXISTS `forms_debug_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `level` VARCHAR(20) NOT NULL DEFAULT 'info',
        `message` TEXT NOT NULL,
        `context_json` LONGTEXT DEFAULT NULL,
        `request_id` VARCHAR(64) DEFAULT NULL,
        `source` VARCHAR(100) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_forms_debug_logs_level` (`level`),
        KEY `idx_forms_debug_logs_created` (`created_at`),
        KEY `idx_forms_debug_logs_request_id` (`request_id`),
        KEY `idx_forms_debug_logs_source` (`source`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    foreach ($inline as $statement) {
        installer_run_query($connect, $statement, $errors, $notes, 'fresh-install-forms');
    }
}

if ($step === 1) {
    $requirements = check_requirements();
    $all_ok = !in_array(false, $requirements, true);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8"/>
        <title>Task Session Installation - Requirements</title>
        <link rel="icon" type="image/png" href="../assets/images/favicon.png"/>
        <link href="../assets/css/bootstrap.css" rel="stylesheet" type="text/css"/>
        <link href="../assets/css/style.min.css" rel="stylesheet" type="text/css"/>
        <link href="style.css" rel="stylesheet" type="text/css"/>
    </head>
    <body>
	   <div class="install-logo">
     <img src="../assets/images/svg/dark-logo.svg" alt="System Logo"/>
</div>
    <div class="dbinstall center-col">
	
        <h2>System Requirements Check</h2>
        <div class="block">
            <?php foreach ($requirements as $ext => $ok): ?>
                <div class="d-flex justify-content-between wizard-table">
                    <div class="requirement-name"><?= $ext ?></div>
                    <div class="requirement-status">
                        <?php if ($ext === 'PHP 8.0+'): ?>
                            <span class="status-badge <?= $ok ? 'success' : 'error' ?>">
                                v.<?= PHP_VERSION ?>
                            </span>
                        <?php else: ?>
                            <span class="status-badge <?= $ok ? 'success' : 'error' ?>">
                                <?= $ok ? 'Enabled' : 'Missing' ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($all_ok): ?>
            <div class="action-buttons">
                <a href="?step=2" class="btn-success">Go to database setup</a>
            </div>
        <?php else: ?>
            <div class="error-message">
                <p>Please enable all required extensions and settings before proceeding. Ask your hosting provider to enable all necessary settings before installing the system.</p>
            </div>
        <?php endif; ?>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$isInstallPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (isset($_POST['submit']) || !empty($_POST['dbname']));

if ($isInstallPost) {
    // ───────────────────────────────────────────────
    // 1) Read form input and connect to MySQL
    $dbname  = trim($_POST['dbname']);
    $uname   = trim($_POST['uname']);
    $dbhost  = trim($_POST['dbhost']);
    $dbpass  = trim($_POST['dbpass']);

    // Validate inputs
    if (empty($dbname) || empty($uname) || empty($dbhost)) {
        $error_message = "Please fill in all required fields (Host Name, Database User Name, Database Name).";
        goto show_error_form;
    }

    // First, try to connect to MySQL server without database
    // Suppress PHP warnings so failed auth shows only in the UI (not as raw Warning text)
    try {
        mysqli_report(MYSQLI_REPORT_OFF);
        $connect = @mysqli_connect($dbhost, $uname, $dbpass);
        if (!$connect) {
            $error_code = mysqli_connect_errno();
            $error_text = mysqli_connect_error();
            
            switch ($error_code) {
                case 1045:
                    $error_message = "Access denied! Please check your Database User Name and Password.";
                    break;
                case 2002:
                    $error_message = "Cannot connect to MySQL server. Please check your Host Name.";
                    break;
                case 2003:
                    $error_message = "Cannot connect to MySQL server. Please check your Host Name.";
                    break;
                default:
                    $error_message = "Database connection failed: " . $error_text . " (Error Code: " . $error_code . ")";
            }
            goto show_error_form;
        }
    } catch (mysqli_sql_exception $e) {
        $error_msg = $e->getMessage();
        
        // Provide more user-friendly error messages
        if (strpos($error_msg, 'Access denied') !== false) {
            $error_message = "Access denied! Please check your Database User Name and Password. Make sure the user exists and has proper permissions.";
        } elseif (strpos($error_msg, 'Unknown database') !== false) {
            $error_message = "Database '$dbname' does not exist. Please create the database first or check the database name.";
        } elseif (strpos($error_msg, 'Connection refused') !== false) {
            $error_message = "Cannot connect to MySQL server. Please check your Host Name and make sure MySQL is running.";
        } else {
            $error_message = "Database connection failed: " . $error_msg;
        }
        goto show_error_form;
    }

    // Check if database exists
    try {
        $db_exists = mysqli_select_db($connect, $dbname);
        if (!$db_exists) {
            $error_message = "Database '$dbname' does not exist. Please create the database first or check the database name.";
            goto show_error_form;
        }

        // Check if tables already exist
        $result = mysqli_query($connect, "SHOW TABLES LIKE 'users'");
        if (!$result) {
            $error_message = "Error checking database tables: " . mysqli_error($connect);
            goto show_error_form;
        }
        if (mysqli_num_rows($result) > 0) {
            $error_message = "Database '$dbname' already contains tables. This appears to be an existing installation. Please use a fresh database or backup and drop existing tables.";
            goto show_error_form;
        }
    } catch (mysqli_sql_exception $e) {
        $error_message = "Database error: " . $e->getMessage();
        goto show_error_form;
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 2) Write out includes/config.php
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $configPath = __DIR__ . '/../includes/config.php';
    $file = fopen($configPath, 'w+');
    if (!$file) {
        echo "Error: Cannot open config file for writing.<br>";
        exit;
    }
    ftruncate($file, 0);

    // Get the current URL for base_url
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . $host . dirname(dirname($_SERVER['PHP_SELF']));
    if (substr($base_url, -1) !== '/') {
        $base_url .= '/';
    }

    $dbhostLit = var_export($dbhost, true);
    $dbnameLit = var_export($dbname, true);
    $unameLit = var_export($uname, true);
    $dbpassLit = var_export($dbpass, true);
    $baseUrlLit = var_export($base_url, true);

    $content = '<?php 
// Security: Use environment variables for database credentials with fallback to installation values
defined("LIB_ROOT") ? null : define("LIB_ROOT", dirname(__FILE__));
defined("DS") ? null : define("DS", DIRECTORY_SEPARATOR);

defined("DB_SERVER")   ? null : define("DB_SERVER", $_ENV["DB_SERVER"] ?? ' . $dbhostLit . ');
defined("DB_NAME")     ? null : define("DB_NAME", $_ENV["DB_NAME"] ?? ' . $dbnameLit . ');
defined("DB_USER")     ? null : define("DB_USER", $_ENV["DB_USER"] ?? ' . $unameLit . ');
defined("DB_PASS")     ? null : define("DB_PASS", $_ENV["DB_PASS"] ?? ' . $dbpassLit . ');

// Security: Disable error display in production
if (!defined("DEVELOPMENT_MODE") || !DEVELOPMENT_MODE) {
    error_reporting(0);
    ini_set("display_errors", 0);
    ini_set("display_startup_errors", 0);
}

// Security: Set secure headers (skipped during install bootstrap to avoid white screen on setup errors)
if (!defined("TS_INSTALL_BOOTSTRAP")) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

require_once __DIR__ . "/mysqli_connect_safe.php";
$connect = function_exists("crm_mysqli_open") ? crm_mysqli_open() : new mysqli(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
if (!($connect instanceof mysqli) || $connect->connect_error) {
    die("Connection failed: " . (($connect instanceof mysqli) ? $connect->connect_error : "unable to connect"));
}

// Global Vars
$main_url = ' . $baseUrlLit . ';
$base_url = $main_url;
$base_root = dirname(__DIR__);
$perpage = 15;
$contacts_per_page = 100;
';
    fwrite($file, $content);
    fclose($file);

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 2.5) functions.php — preserve packaged file; patch broken autoload only (do not wipe on reinstall)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $functionsPath = __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/installer_sql_helper.php';
    installer_fix_functions_autoload_if_broken($functionsPath);

    if (!installer_should_preserve_functions_php($functionsPath)) {
    $functionsFile = fopen($functionsPath, 'w+');
    if (!$functionsFile) {
        echo "Error: Cannot open functions file for writing.<br>";
        exit;
    }
    ftruncate($functionsFile, 0);

    $functionsContent = '<?php

// ===============================================================================
// Redirect pages or URL  
// ===============================================================================
function redirectTo($location = NULL) {
    if ($location != NULL) {
        // error_log("Redirecting to: $location from " . $_SERVER["REQUEST_URI"]);
        echo "<!-- Redirecting to: $location from " . $_SERVER["REQUEST_URI"] . " -->";
        echo "<script>console.log(\"Redirecting to: $location from " . $_SERVER["REQUEST_URI"] . "\");</script>";
        echo "<script>console.trace();</script>";
        echo "<script>setTimeout(function(){ location.href=\"$location\"; }, 1000);</script>";
        exit;
    }
}

// ===============================================================================
// Show any message 
// ===============================================================================
function outputMessage($message = "") {
    if (!empty($message)) { 
        return "<p class=\"message\">{$message}</p>";
    } else {
        return "";
    }
}

// ===============================================================================
// Select absolute path from root directory by putting ../
// ===============================================================================
function FindRoot() {
    $times = substr_count($_SERVER["PHP_SELF"], "/");
    $rootaccess = "";
    $i = 1; // if you\'re working on local computer set it 2, if its on live server set this value to 1
    while ($i < $times) {
        $rootaccess .= "../";
        $i++;
    }
    return $rootaccess;
}
$root = FindRoot();

// ===============================================================================
// It displays current page URL
// ===============================================================================
function curPageURL() {
    $pageURL = "http";
    // if ($_SERVER["HTTPS"] == true) {$pageURL .= "s";}
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
    } else {
        $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
    }
    return $pageURL;
}

// ===============================================================================
// Autoload Classes using spl_autoload_register()
// Resolve from includes/ using absolute paths (VPS PHP-FPM CWD differs from script dir).
// ===============================================================================
spl_autoload_register(function ($class_name) {
    $lower = strtolower($class_name);
    $candidates = array();

    if (defined(\'LIB_ROOT\')) {
        $candidates[] = LIB_ROOT . DS . $lower . \'.php\';
        $snake = strtolower((string)preg_replace(\'/([a-z])([A-Z])/\', \'$1_$2\', $class_name));
        if ($snake !== $lower) {
            $candidates[] = LIB_ROOT . DS . $snake . \'.php\';
        }
    }

    if (defined(\'SITE_ROOT\')) {
        $candidates[] = SITE_ROOT . DS . $lower . \'.php\';
        if (defined(\'LIB_ROOT\')) {
            $candidates[] = SITE_ROOT . DS . \'includes\' . DS . $lower . \'.php\';
        }
    }

    $candidates[] = $lower . \'.php\';

    foreach ($candidates as $path) {
        if ($path !== \'\' && is_file($path)) {
            require_once $path;
            return;
        }
    }

    if (strpos($lower, \'tasksession\') === 0) {
        $map = array(
            \'tasksessionecommercemanager\' => \'class-tasksession-ecommerce-manager.php\',
            \'tasksessionwoapiservice\' => \'class-tasksession-woo-api.php\',
            \'tasksessionwoosettings\' => \'class-tasksession-woo-settings.php\',
            \'tasksessionsenderapiservice\' => \'class-tasksession-sender-api.php\',
        );
        if (isset($map[$lower])) {
            $file = dirname(__DIR__) . \'/vendor/woocommerce/includes/\' . $map[$lower];
            if (is_file($file)) {
                require_once $file;
            }
        }
    }
});

// ===============================================================================
// This will return Date and Time i.e. January 10, 2025 at 02:22:12
// ===============================================================================
function datetime_to_text($datetime = "") {
    $unixdatetime = strtotime($datetime);
    return strftime("%B %d, %Y at %I:%M %p", $unixdatetime);
}

// ===============================================================================
// This will return only Date i.e. January 10, 2025 at 02:22:12
// ===============================================================================
function date_to_text($date = "") {
    $unixdatetime = strtotime($date);
    return strftime("%B %d, %Y", $unixdatetime);
}
function day_to_text($day = "") {
    $unixdatetime = strtotime($day);
    return strftime("%d", $unixdatetime);
}
function month_to_text($month = "") {
    $unixdatetime = strtotime($month);
    return strftime("%B", $unixdatetime);
}
function year_to_text($year = "") {
    $unixdatetime = strtotime($year);
    return strftime("%Y", $unixdatetime);
}

// ===============================================================================
// Random encrypted activation key for Account activation after account has been created
// ===============================================================================
function actKey($getStr) {
    $actKey = sha1(mt_rand(10000, 99999) . time() . $getStr);
    return $actKey;
}

function randomProId($getStr) {
    $actKey = sha1(mt_rand(100, 999) . $getStr);
    return $actKey;
}

// ===============================================================================
// Format the date by removing zero
// ===============================================================================
function strip_zeros_from_date($marked_string = "") {
    // first remove the marked zeros
    $no_zeros = str_replace("*0", "", $marked_string);
    // then remove any remaining marks
    $cleaned_string = str_replace("*", "", $no_zeros);
    return $cleaned_string;
}

function dobToYears($dob) {
    $currentDate = date("Y-m-d");
    $d1 = new DateTime($dob);
    $d2 = new DateTime($currentDate);
    $diff = $d2->diff($d1);
    return $diff->y . " years old <br />";
}

/* Prevent XSS input - Selective sanitization */
// Only sanitize GET parameters, not POST (to preserve TinyMCE content)
$_GET   = filter_input_array(INPUT_GET, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

// For POST data, we\'ll handle sanitization selectively in specific functions
// This preserves TinyMCE HTML content while still protecting against XSS

/* I prefer not to use $_REQUEST...but for those who do: */
$_REQUEST = (array)$_POST + (array)$_GET + (array)$_REQUEST;

// Enhanced security functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, "UTF-8");
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_integer($value) {
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

function generate_csrf_token() {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function validate_csrf_token($token) {
    return isset($_SESSION["csrf_token"]) && hash_equals($_SESSION["csrf_token"], $token);
}

// Safe function for TinyMCE content - allows HTML but removes dangerous scripts
function sanitize_tinymce_content($content) {
    // Remove script tags and event handlers
    $content = preg_replace("/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi", "", $content);
    $content = preg_replace("/on\w+\s*=\s*[\"\'][^\"\']*[\"\']/i", "", $content);
    $content = preg_replace("/javascript:/i", "", $content);
    
    // Allow safe HTML tags
    $allowed_tags = "<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><code><pre><a><img><table><tr><td><th><thead><tbody><tfoot><div><span>";
    
    return strip_tags($content, $allowed_tags);
}

/**
 * Get user avatar data (image or initials)
 * @param int $userId User ID
 * @param string $firstName User\'s first name
 * @param string $lastName User\'s last name
 * @param int $width Width for image thumbnail (default: 50)
 * @param int $height Height for image thumbnail (default: 50)
 * @return array Array containing avatar data
 */
function getUserAvatarData($userId, $firstName = \'\', $lastName = \'\', $width = 50, $height = 50) {
    global $url, $db1;
    
    try {
        // Try to get profile picture
        $profilePic = \'\';
        $profilePicSql = "SELECT filename FROM profile_pics WHERE fkUserId = " . (int)$userId . " LIMIT 1";
        $profilePicResult = $db1->query($profilePicSql);
        
        if ($profilePicResult && $db1->num_rows($profilePicResult) > 0) {
            $profilePicRow = $db1->fetch_row($profilePicResult);
            $profilePic = $profilePicRow[\'filename\'];
            
            if (!empty($profilePic)) {
                return [
                    \'type\' => \'image\',
                    \'url\' => $url . \'includes/thumbnail.php?src=\' . urlencode($url . \'uploads/profile-pics/\' . $profilePic) . \'&w=\' . $width . \'&h=\' . $height,
                    \'filename\' => $profilePic,
                    \'hasImage\' => true
                ];
            }
        }
        
        // Generate initials if no profile picture
        $displayName = trim($firstName . \' \' . $lastName);
        $initials = \'\';
        
        if (!empty($firstName)) {
            $initials = strtoupper(substr($firstName, 0, 1));
            if (!empty($lastName)) {
                $initials .= strtoupper(substr($lastName, 0, 1));
            }
        } else {
            $initials = strtoupper(substr($displayName, 0, 1));
        }
        
        // If still no initials, use \'U\' as fallback
        if (empty($initials)) {
            $initials = \'U\';
        }
        
        // Get color index (1-8) based on user ID
        $colorIndex = ($userId % 8) + 1;
        
        return [
            \'type\' => \'initials\',
            \'initials\' => $initials,
            \'colorIndex\' => $colorIndex,
            \'hasImage\' => false
        ];
    } catch (Exception $e) {
        // Return fallback data
        return [
            \'type\' => \'initials\',
            \'initials\' => \'U\',
            \'colorIndex\' => 1,
            \'hasImage\' => false
        ];
    }
}

/**
 * Generate HTML for user avatar (image or initials)
 * @param int $userId User ID
 * @param string $firstName User\'s first name
 * @param string $lastName User\'s last name
 * @param int $width Width for image thumbnail (default: 50)
 * @param int $height Height for image thumbnail (default: 50)
 * @param string $cssClass Additional CSS classes
 * @param string $altText Alt text for image
 * @return string HTML string for avatar
 */
function getUserAvatarHtml($userId, $firstName = \'\', $lastName = \'\', $width = 50, $height = 50, $cssClass = \'\', $altText = \'User Avatar\') {
    try {
        $avatarData = getUserAvatarData($userId, $firstName, $lastName, $width, $height);
        
        if ($avatarData[\'type\'] === \'image\') {
            $class = \'img-fluid rounded-circle \' . $cssClass;
            return \'<img src="\' . htmlspecialchars($avatarData[\'url\']) . \'" width="\' . $width . \'" height="\' . $height . \'" class="\' . $class . \'" alt="\' . htmlspecialchars($altText) . \'">\';
        } else {
            // Determine size class based on dimensions
            $sizeClass = \'\';
            if ($width <= 30) {
                $sizeClass = \'avatar-initials-small\';
            } elseif ($width <= 40) {
                $sizeClass = \'avatar-initials-medium\';
            } elseif ($width <= 60) {
                $sizeClass = \'avatar-initials-large\';
            } else {
                $sizeClass = \'avatar-initials-xlarge\';
            }
            
            $class = \'avatar-initials color-\' . $avatarData[\'colorIndex\'] . \' \' . $sizeClass . \' \' . $cssClass;
            return \'<div class="\' . $class . \'">\' . htmlspecialchars($avatarData[\'initials\']) . \'</div>\';
        }
    } catch (Exception $e) {
        // Fallback to simple initials if there\'s an error
        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
        if (empty($initials)) $initials = \'U\';
        return \'<div class="avatar-initials color-1 avatar-initials-medium">\' . htmlspecialchars($initials) . \'</div>\';
    }
}

/**
 * Generate simple avatar with initials
 * @param string $name User name
 * @param int $size Size in pixels
 * @return string HTML for simple avatar
 */
function getSimpleAvatar($name, $size = 50) {
    $initials = strtoupper(substr($name, 0, 1));
    if (empty($initials)) $initials = \'U\';
    
    $colorIndex = (crc32($name) % 8) + 1;
    $sizeClass = $size <= 30 ? \'avatar-initials-small\' : ($size <= 40 ? \'avatar-initials-medium\' : ($size <= 60 ? \'avatar-initials-large\' : \'avatar-initials-xlarge\'));
    
    return \'<div class="avatar-initials color-\' . $colorIndex . \' \' . $sizeClass . \'" style="width: \' . $size . \'px; height: \' . $size . \'px;">\' . htmlspecialchars($initials) . \'</div>\';
}
';
    fwrite($functionsFile, $functionsContent);
    fclose($functionsFile);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 2.6) Create secure .htaccess file
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $htaccessPath = __DIR__ . '/../.htaccess';
    $htaccessFile = fopen($htaccessPath, 'w+');
    if (!$htaccessFile) {
        echo "Error: Cannot open .htaccess file for writing.<br>";
        exit;
    }
    ftruncate($htaccessFile, 0);

    $htaccessContent = '# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

# Force HTTPS (uncomment in production)
# <IfModule mod_rewrite.c>
#     RewriteEngine On
#     RewriteCond %{HTTPS} off
#     RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
# </IfModule>

# Prevent access to sensitive files
<Files "*.env">
    Order allow,deny
    Deny from all
</Files>

<Files "config.php">
    Order allow,deny
    Deny from all
</Files>

<Files "tasksession-secret.local.php">
    Order allow,deny
    Deny from all
</Files>

<Files "database.php">
    Order allow,deny
    Deny from all
</Files>

<Files "database.class.php">
    Order allow,deny
    Deny from all
</Files>

# Prevent directory listing / extensionless MultiViews
Options -Indexes -MultiViews

# Login / app home without index.php in the URL
DirectoryIndex index.php

# Protect against common attacks + pretty URLs
<IfModule mod_rewrite.c>
    RewriteEngine On
    
    # Block access to hidden files
    RewriteCond %{SCRIPT_FILENAME} -d [OR]
    RewriteCond %{SCRIPT_FILENAME} -f
    RewriteRule "(^\.|/\.)" - [F]
    
    # Block access to backup files
    RewriteRule \.(bak|config|sql|fla|psd|ini|log|sh|inc|swp|dist|old|orig|save|sql|sqlite|sqlite3|db)$ - [F]

    # Canonical: root login index.php → folder URL
    RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
    RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\s([^\s?]*?/)index\.php[\s?]
    RewriteRule ^index\.php$ %1 [R=302,L,QSA]

    # Public payment: pay.php/{token} → pay/{token}
    RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
    RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\s([^\s?]*?/)pay\.php/([a-zA-Z0-9_-]+)
    RewriteRule ^pay\.php/(.*)$ %1pay/%2 [R=302,L,QSA]

    # Accept pretty payment URL
    RewriteRule ^pay/([a-zA-Z0-9_-]+)/?$ pay.php?token=$1 [L,QSA]

    # Invoice / document PDF download
    RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
    RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\s([^\s?]*?/)templates/download_pdf\.php[\s?]
    RewriteRule ^templates/download_pdf\.php$ %1templates/download_pdf [R=302,L,QSA]

    RewriteRule ^templates/download_pdf/?$ templates/download_pdf.php [L,QSA]

    # Marketing contact profile pretty URL
    RewriteRule ^marketing/contact-profile$ marketing/contact-profile.php [L,QSA]

    # MFA pages: explicit map
    RewriteRule ^authenticator/?$ authenticator.php [L,QSA]
    RewriteRule ^verify-2fa/?$ verify-2fa.php [L,QSA]
    RewriteRule ^setup-2fa/?$ setup-2fa.php [L,QSA]

    # Root UI pages: GET/HEAD *.php → extensionless (chatting, discussion, etc.)
    RewriteCond %{REQUEST_METHOD} ^(GET|HEAD)$
    RewriteCond %{THE_REQUEST} ^[A-Z]{3,9}\s([^\s?]*?/)(chatting|discussion|settings|search|events|media|activity|forgot-password|reset_password|unauthorized|authenticator|verify-2fa|setup-2fa)\.php[\s?]
    RewriteRule ^(chatting|discussion|settings|search|events|media|activity|forgot-password|reset_password|unauthorized|authenticator|verify-2fa|setup-2fa)\.php$ %1$1 [R=302,L,QSA]

    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME}.php -f
    RewriteRule ^(chatting|discussion|settings|search|events|media|activity|forgot-password|reset_password|unauthorized|authenticator|verify-2fa|setup-2fa)/?$ $1.php [L,QSA]
</IfModule>

# Limit file uploads
<IfModule mod_php.c>
    php_value upload_max_filesize 5M
    php_value post_max_size 8M
    php_value max_execution_time 30
    php_value memory_limit 128M
</IfModule>

# Block bad bots
<IfModule mod_rewrite.c>
    RewriteCond %{REQUEST_URI} !/api(/|$) [NC]
    RewriteCond %{HTTP_USER_AGENT} !WordPress [NC]
    RewriteCond %{HTTP_USER_AGENT} !ComonCRM- [NC]
    RewriteCond %{HTTP_USER_AGENT} ^$ [OR]
    RewriteCond %{HTTP_USER_AGENT} ^(java|curl|wget) [NC,OR]
    RewriteCond %{HTTP_USER_AGENT} ^.*(libwww-perl|curl|wget|python|nikto|wkito|pikto|acunetix).* [NC,OR]
    RewriteCond %{HTTP_USER_AGENT} ^.*(winhttp|HTTrack|clshttp|archiver|loader|email|harvest|extract|grab|miner).* [NC]
    RewriteRule .* - [F,L]
</IfModule>
';
    fwrite($htaccessFile, $htaccessContent);
    fclose($htaccessFile);

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 2.7) Create secure upload directories
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $uploadDirs = [
        __DIR__ . '/../uploads/user-uploads/',
        __DIR__ . '/../uploads/',
        __DIR__ . '/../uploads/cache/'
    ];

    foreach ($uploadDirs as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                echo "Error: Cannot create upload directory: $dir<br>";
                exit;
            }
        }
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 2.8) Create security documentation
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $securityDocPath = __DIR__ . '/../SECURITY_GUIDE.md';
    $securityDocFile = fopen($securityDocPath, 'w+');
    if ($securityDocFile) {
        $securityDocContent = '# SECURITY GUIDE FOR PRODUCTION DEPLOYMENT

## Security Features Implemented During Installation

### 1. Database Security
- Environment variable support for database credentials
- Secure headers implementation
- Error display disabled in production

### 2. Input Validation & Sanitization
- XSS prevention with comprehensive input sanitization
- POST data sanitization enabled
- Enhanced security functions for validation

### 3. Password Reset Security
- Secure token-based password reset system
- 64-character cryptographically secure tokens
- 24-hour token expiration
- Single-use tokens (marked as used after reset)
- No user ID exposure in reset URLs
- Password strength validation (minimum 8 characters)
- Automatic cleanup of expired tokens

### 4. File Upload Security
- File type validation with MIME type checking
- Path traversal protection
- Secure filename generation
- Restricted upload directory permissions (0755)

### 5. Web Server Security
- Security headers configured
- Directory listing disabled
- Sensitive file access blocked
- Bad bot protection

## CRITICAL POST-INSTALLATION STEPS

### 1. Environment Variables Setup
Create a `.env` file in your root directory:
```bash
DB_SERVER=your_db_server
DB_NAME=your_db_name
DB_USER=your_db_user
DB_PASS=your_secure_password
DEVELOPMENT_MODE=false
```

### 2. HTTPS Implementation
- Install SSL certificate
- Uncomment HTTPS redirect in .htaccess
- Update all URLs to use HTTPS

### 3. File Permissions
```bash
# Set proper permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 755 uploads/user-uploads/
chmod 755 uploads/
chmod 755 uploads/cache/
```

### 4. Database Security
- Change default database credentials
- Create dedicated database user with minimal privileges
- Enable database logging
- Set up regular backups

## Security Monitoring

### Regular Tasks:
- Keep PHP and dependencies updated
- Monitor error logs
- Regular security audits
- Backup verification

### Recommended Tools:
- OWASP ZAP for vulnerability scanning
- Burp Suite for penetration testing
- Nikto for web server scanning


---
**Remember**: Security is an ongoing process. Regular monitoring and updates are essential.
';
        fwrite($securityDocFile, $securityDocContent);
        fclose($securityDocFile);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 3) Define all CREATE TABLE and TRIGGER statements
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    $table1 = "\n    CREATE TABLE IF NOT EXISTS messages (\n        id int(11) NOT NULL AUTO_INCREMENT,\n        message text COLLATE utf8_unicode_ci NOT NULL,\n        time int(11) NOT NULL,\n        user_id int(11) NOT NULL,\n        receiver int(11) NOT NULL,\n        storage_a int(11) NOT NULL,\n        storage_b int(11) NOT NULL,\n        Project_id int(255) NOT NULL,\n        status varchar(6) COLLATE utf8_unicode_ci NOT NULL,\n        edited TINYINT(1) NOT NULL DEFAULT 0,\n        edit_time INT DEFAULT NULL,\n        reply_to INT(11) DEFAULT NULL,\n        PRIMARY KEY (id),\n        KEY idx_reply_to (reply_to)\n    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci\n    ";

    $table2 = "
    CREATE TABLE settings (
        id int(10) NOT NULL AUTO_INCREMENT,
        url varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        company_name varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        syatem_title varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        login_page_title varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        copy_rights varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        system_currency varchar(10) COLLATE utf8_unicode_ci NOT NULL,
        multiple_currencies text COLLATE utf8_unicode_ci DEFAULT NULL,
        time_zone varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        favicon_image varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        login_page_logo varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        logo varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        mobile_logo varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        login_page_image varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        invoice_logo varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        email_template_logo varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
        stripe_sk varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
        stripe_pk varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
        paypal_email varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
        checkout_id varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
        checkout_pk varchar(1000) COLLATE utf8_unicode_ci NOT NULL,
        system_email varchar(150) COLLATE utf8_unicode_ci NOT NULL,
        forget_email text COLLATE utf8_unicode_ci NOT NULL,
        create_account_email text COLLATE utf8_unicode_ci NOT NULL,
        project_assign_email text COLLATE utf8_unicode_ci NOT NULL,
        assign_staff_email text COLLATE utf8_unicode_ci NOT NULL,
        project_update_email text COLLATE utf8_unicode_ci NOT NULL,
        task_create_email text COLLATE utf8_unicode_ci NULL,
        task_update_email text COLLATE utf8_unicode_ci NULL,
        task_update_email_on_drag tinyint(1) NOT NULL DEFAULT 1,
        invoice_create_email text COLLATE utf8_unicode_ci NULL,
        invoice_paid_email text COLLATE utf8_unicode_ci NULL,
        invoice_paid_email_admin text COLLATE utf8_unicode_ci NULL,
        message_notification_email text COLLATE utf8_unicode_ci NULL,
        message_notification_email_subject varchar(255) COLLATE utf8_unicode_ci DEFAULT 'You have received a new message',
        group_chat_create_email text COLLATE utf8_unicode_ci NULL,
        group_chat_create_email_subject varchar(255) COLLATE utf8_unicode_ci DEFAULT 'You have been added to group: {GROUP_NAME}',
        group_chat_message_email text COLLATE utf8_unicode_ci NULL,
        group_chat_message_email_subject varchar(255) COLLATE utf8_unicode_ci DEFAULT 'You have {MESSAGE_COUNT} new message(s) in {GROUP_NAME}',
        group_chat_batch_email text COLLATE utf8_unicode_ci NULL,
        group_chat_batch_email_subject varchar(255) COLLATE utf8_unicode_ci DEFAULT 'You have {MESSAGE_COUNT} new message in {GROUP_NAME}',
        discussion_chat_batch_email text COLLATE utf8_unicode_ci NULL,
        discussion_chat_batch_email_subject varchar(255) COLLATE utf8_unicode_ci DEFAULT 'You have {MESSAGE_COUNT} new message',
        one_to_one_chat_batch_email text COLLATE utf8_unicode_ci NULL,
        one_to_one_chat_batch_email_subject varchar(255) COLLATE utf8_unicode_ci DEFAULT 'You have {MESSAGE_COUNT} new message from {SENDER_NAME}',
        task_chat_batch_email text COLLATE utf8_unicode_ci NULL,
        task_chat_batch_email_subject varchar(255) COLLATE utf8_unicode_ci DEFAULT 'You have {MESSAGE_COUNT} new messages in task: {TASK_NAME}',
        task_reminders_enabled TINYINT(1) DEFAULT 0,
        task_reminder_1_enabled TINYINT(1) DEFAULT 0,
        task_reminder_1_days INT(11) DEFAULT 7,
        task_reminder_1_type ENUM('before', 'after') DEFAULT 'before',
        task_reminder_1_subject VARCHAR(255) DEFAULT '',
        task_reminder_1_template TEXT NULL,
        task_reminder_2_enabled TINYINT(1) DEFAULT 0,
        task_reminder_2_days INT(11) DEFAULT 3,
        task_reminder_2_type ENUM('before', 'after') DEFAULT 'after',
        task_reminder_2_subject VARCHAR(255) DEFAULT '',
        task_reminder_2_template TEXT NULL,
        task_reminder_3_enabled TINYINT(1) DEFAULT 0,
        task_reminder_3_days INT(11) DEFAULT 14,
        task_reminder_3_type ENUM('before', 'after') DEFAULT 'after',
        task_reminder_3_subject VARCHAR(255) DEFAULT '',
        task_reminder_3_template TEXT NULL,
        task_reminder_send_to_staff TINYINT(1) DEFAULT 1,
        task_reminder_send_to_client TINYINT(1) DEFAULT 0,
        task_reminder_send_to_creator TINYINT(1) DEFAULT 0,
        system_language varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        chat_refresh_interval int(11) NOT NULL DEFAULT 5,
        chat_email_notify_admin TINYINT(1) NOT NULL DEFAULT 1,
        chat_email_notify_staff TINYINT(1) NOT NULL DEFAULT 1,
        chat_email_notify_client TINYINT(1) NOT NULL DEFAULT 1,
        chat_email_notify_mention TINYINT(1) NOT NULL DEFAULT 1,
        presence_toast_enabled TINYINT(1) NOT NULL DEFAULT 1,
        presence_beep_enabled TINYINT(1) NOT NULL DEFAULT 1,
        presence_staff_toast_enabled TINYINT(1) NOT NULL DEFAULT 1,
        browser_push_enabled TINYINT(1) NOT NULL DEFAULT 1,
        setup_guide_pending TINYINT(1) NOT NULL DEFAULT 0,
        version varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        purchase_code varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        smtp_from_name varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        smtp_from_email varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        smtp_username varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        smtp_host varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        smtp_port varchar(10) COLLATE utf8_unicode_ci DEFAULT NULL,
        smtp_password varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        smtp_secure varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
        use_smtp TINYINT(1) DEFAULT 0,
        default_sales_tax DECIMAL(5,2) DEFAULT NULL,
        company_address TEXT DEFAULT NULL,
        default_invoice_memo TEXT DEFAULT NULL,
        default_invoice_footer TEXT DEFAULT NULL,
        module_lead_board TINYINT(1) NOT NULL DEFAULT 1,
        module_invoices TINYINT(1) NOT NULL DEFAULT 1,
        module_projects TINYINT(1) NOT NULL DEFAULT 1,
        module_tasks TINYINT(1) NOT NULL DEFAULT 1,
        module_file_management TINYINT(1) NOT NULL DEFAULT 1,
        module_notes_documents TINYINT(1) NOT NULL DEFAULT 1,
        module_discussions TINYINT(1) NOT NULL DEFAULT 1,
        email_report_enabled TINYINT(1) DEFAULT 0 COMMENT 'Enable/disable email reports',
        email_report_template TEXT NULL COMMENT 'Email template for account summary reports',
        email_report_subject VARCHAR(255) DEFAULT 'Email Account Summary Report',
        email_report_frequency ENUM('instant', '30mins', '1hour', '6hours', '12hours', '24hours', 'weekly', 'monthly') DEFAULT '24hours',
        gmail_client_id VARCHAR(500) DEFAULT NULL,
        gmail_client_secret VARCHAR(500) DEFAULT NULL,
        outlook_client_id VARCHAR(500) DEFAULT NULL,
        outlook_client_secret VARCHAR(500) DEFAULT NULL,
        outlook_tenant_id VARCHAR(100) DEFAULT 'common',
        module_email TINYINT(1) NOT NULL DEFAULT 1,
        module_marketing TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enable/disable Email Marketing module',
        module_attendance TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Attendance module',
        module_ip_restriction TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable login IP restriction module',
        module_ecommerce TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Enable/disable Ecommerce module',
        global_allowed_ips TEXT NULL DEFAULT NULL COMMENT 'Optional comma-separated IPs for login restriction',
        module_time_tracking TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Task Time Tracking',
        module_reports TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Enable/disable Admin Reports dashboard (admin only)',
        sidebar_menu_order TEXT NULL DEFAULT NULL,
        sidebar_menu_hidden TEXT NULL DEFAULT NULL,
        admin_login_landing_page VARCHAR(255) NULL DEFAULT 'admin/index.php',
        staff_login_landing_page VARCHAR(255) NULL DEFAULT 'staff/index.php',
        label_projects_override VARCHAR(191) DEFAULT NULL,
        label_tasks_override VARCHAR(191) DEFAULT NULL,
        label_clients_override VARCHAR(191) DEFAULT NULL,
        label_financials_override VARCHAR(191) DEFAULT NULL,
        label_chatting_override VARCHAR(191) DEFAULT NULL,
        label_private_notes_override VARCHAR(191) DEFAULT NULL,
        label_leads_override VARCHAR(191) DEFAULT NULL,
        label_media_vault_override VARCHAR(191) DEFAULT NULL,
        label_custom_fields_override VARCHAR(191) DEFAULT NULL,
        label_event_override VARCHAR(191) DEFAULT NULL,
        payment_reminders_enabled TINYINT(1) DEFAULT 0,
        payment_reminder_1_enabled TINYINT(1) DEFAULT 0,
        payment_reminder_1_days INT(11) DEFAULT 7,
        payment_reminder_1_type ENUM('before', 'after') DEFAULT 'before',
        payment_reminder_1_subject VARCHAR(255) DEFAULT '',
        payment_reminder_1_template TEXT NULL,
        payment_reminder_2_enabled TINYINT(1) DEFAULT 0,
        payment_reminder_2_days INT(11) DEFAULT 3,
        payment_reminder_2_type ENUM('before', 'after') DEFAULT 'after',
        payment_reminder_2_subject VARCHAR(255) DEFAULT '',
        payment_reminder_2_template TEXT NULL,
        payment_reminder_3_enabled TINYINT(1) DEFAULT 0,
        payment_reminder_3_days INT(11) DEFAULT 14,
        payment_reminder_3_type ENUM('before', 'after') DEFAULT 'after',
        payment_reminder_3_subject VARCHAR(255) DEFAULT '',
        payment_reminder_3_template TEXT NULL,
        payment_reminder_send_to_client TINYINT(1) DEFAULT 1,
        payment_reminder_send_to_staff TINYINT(1) DEFAULT 0,
        payment_reminder_notify_admin_in_app TINYINT(1) NOT NULL DEFAULT 1,
        chat_email_client_one_to_one TINYINT(1) NOT NULL DEFAULT 1,
        chat_email_client_group TINYINT(1) NOT NULL DEFAULT 1,
        chat_email_client_task TINYINT(1) NOT NULL DEFAULT 1,
        chat_email_client_discussion TINYINT(1) NOT NULL DEFAULT 1,
        vapid_public_key text NULL,
        vapid_private_key text NULL,
        PRIMARY KEY (id)
    ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ";

    $table2b = "
    CREATE TABLE IF NOT EXISTS setup_guide_prefs (
        user_id INT NOT NULL,
        dismissed_forever TINYINT(1) NOT NULL DEFAULT 0,
        wizard_minimized TINYINT(1) NOT NULL DEFAULT 0,
        completed_steps TEXT NULL,
        updated_at DATETIME NULL,
        PRIMARY KEY (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $table3 = "
    CREATE TABLE users (
        id int(9) NOT NULL AUTO_INCREMENT,
        Projects_ids varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        password varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        email varchar(50) NOT NULL,
        username varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
        accountStatus tinyint(1) NOT NULL,
        firstName varchar(50) NOT NULL,
        last_name varchar(150) COLLATE utf8_unicode_ci DEFAULT NULL,
        title varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        company varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        address varchar(100) COLLATE utf8_unicode_ci NOT NULL,
        phone varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        website varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        teams_id varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        assigned_team VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Comma-separated staff/admin user IDs assigned to this client',
        base_salary DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        attendance_deduction_mode ENUM('fixed','percentage') DEFAULT NULL,
        attendance_deduction_value DECIMAL(12,2) DEFAULT NULL,
        login_ip_restriction_enabled TINYINT(1) NOT NULL DEFAULT 0,
        allowed_login_ips TEXT DEFAULT NULL,
        attendance_disabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = attendance hidden/disabled for this user',
        fb varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        regDate datetime NOT NULL,
        type_status varchar(300) COLLATE utf8_unicode_ci NOT NULL,
        last_seen int(255) NOT NULL,
        session_status varchar(7) COLLATE utf8_unicode_ci NOT NULL,
        status int(10) NOT NULL,
        note text COLLATE utf8_unicode_ci NOT NULL,
        city varchar(100) COLLATE utf8_unicode_ci NOT NULL,
        state varchar(100) COLLATE utf8_unicode_ci NOT NULL,
        zip varchar(100) COLLATE utf8_unicode_ci NOT NULL,
        user_language varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        country varchar(100) COLLATE utf8_unicode_ci NOT NULL,
        currency varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
        role_id int DEFAULT NULL,
        google_sub varchar(255) DEFAULT NULL,
        auth_provider enum('local','google') NOT NULL DEFAULT 'local',
        avatar_url varchar(500) DEFAULT NULL,
        push_notifications_enabled tinyint(1) NOT NULL DEFAULT 1,
        push_active_chat_type varchar(20) DEFAULT NULL,
        push_active_chat_id int(11) DEFAULT NULL,
        push_active_chat_at datetime DEFAULT NULL,
        email_verified tinyint(1) NOT NULL DEFAULT '0',
        totp_secret TEXT NULL,
        totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
        totp_confirmed_at DATETIME NULL,
        totp_backup_codes TEXT NULL,
        totp_last_timestep BIGINT NULL,
        session_epoch INT NOT NULL DEFAULT 1,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY email (email),
        UNIQUE KEY uniq_google_sub (google_sub),
        UNIQUE KEY uniq_username (username),
        KEY idx_assigned_team (assigned_team)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ";

    $table4 = "
    CREATE TABLE profile_pics (
        id int(11) NOT NULL AUTO_INCREMENT,
        fkUserId int(11) NOT NULL,
        filename varchar(100) COLLATE utf8_unicode_ci NOT NULL,
        type varchar(10) COLLATE utf8_unicode_ci NOT NULL,
        size varchar(10) COLLATE utf8_unicode_ci NOT NULL,
        createdDate datetime NOT NULL,
        PRIMARY KEY (id),
        KEY fkUserId (fkUserId),
        CONSTRAINT profile_pics_ibfk_1 FOREIGN KEY (fkUserId) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ";

    $table5 = "
    CREATE TABLE projects (
        p_id int(11) NOT NULL AUTO_INCREMENT,
        c_id int(11) NOT NULL,
        c_ids varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        main_client_id int(11) DEFAULT NULL,
        s_ids varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        project_title varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        project_desc TEXT COLLATE utf8_unicode_ci NOT NULL,
        budget varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        status varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        archive int(10) NOT NULL,
        trash int(10) NOT NULL,
        start_time date NOT NULL,
        end_time date NOT NULL,
        PRIMARY KEY (p_id),
        KEY c_id (c_id),
        KEY main_client_id (main_client_id),
        CONSTRAINT fkClientId FOREIGN KEY (c_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
        CONSTRAINT fk_main_client_id FOREIGN KEY (main_client_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ";

    $table_project_notes = "
    CREATE TABLE IF NOT EXISTS project_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        user_id INT NOT NULL,
        note_content TEXT,
        UNIQUE KEY (project_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_project_tab_notes = "
    CREATE TABLE IF NOT EXISTS project_tab_notes (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        project_id INT(11) NOT NULL,
        creator_id INT(11) NOT NULL,
        creator_type ENUM('admin','staff','client') NOT NULL,
        title VARCHAR(255) NULL DEFAULT NULL,
        content TEXT NOT NULL,
        color VARCHAR(32) NULL DEFAULT NULL,
        is_archived TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_project_id (project_id),
        INDEX idx_creator_id (creator_id),
        INDEX idx_creator_type (creator_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_project_tab_note_shares = "
    CREATE TABLE IF NOT EXISTS project_tab_note_shares (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        project_note_id INT NOT NULL,
        project_id INT NOT NULL,
        creator_user_id INT NOT NULL,
        shared_with_user_id INT NOT NULL,
        permission ENUM('view','edit') NOT NULL DEFAULT 'view',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_project_note_share (project_note_id, shared_with_user_id),
        KEY idx_proj_note_shares_project (project_id),
        KEY idx_proj_note_shares_creator (creator_user_id),
        KEY idx_proj_note_shares_target (shared_with_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table6 = "
    CREATE TABLE milestones (
        id int(255) NOT NULL AUTO_INCREMENT,
        p_id int(255) DEFAULT NULL,
        c_id int(255) DEFAULT NULL,
        company_id INT UNSIGNED DEFAULT NULL,
        company_billing_snapshot TEXT DEFAULT NULL,
        title varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        deadline date NOT NULL,
        releaseDate date DEFAULT NULL,
        budget varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        paid_total DECIMAL(10,2) DEFAULT NULL,
        status tinyint(4) NOT NULL,
        bill_from varchar(255) DEFAULT NULL,
        bill_from_address varchar(255) DEFAULT NULL,
        currency varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
        created_by INT NULL,
        sales_tax DECIMAL(5,2) DEFAULT NULL,
        sales_tax_type VARCHAR(10) DEFAULT 'percentage',
        discount DECIMAL(10,2) DEFAULT NULL,
        discount_type VARCHAR(10) DEFAULT 'percentage',
        memo TEXT DEFAULT NULL,
        issue_date DATE DEFAULT NULL,
        is_recurring TINYINT(1) DEFAULT 0,
        recurring_frequency VARCHAR(20) DEFAULT NULL,
        recurring_parent_id INT(11) DEFAULT NULL,
        recurring_next_date DATE DEFAULT NULL,
        recurring_stopped TINYINT(1) DEFAULT 0,
        footer TEXT DEFAULT NULL,
        recurring_end_date DATE DEFAULT NULL,
        recurring_next_renewal_date DATE DEFAULT NULL,
        recurring_billing_cycle_days INT(11) DEFAULT NULL,
        recurring_paused TINYINT(1) DEFAULT 0,
        recurring_auto_charge TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY p_id (p_id),
        KEY idx_milestones_company_id (company_id),
        KEY idx_milestones_created_by (created_by),
        KEY idx_recurring_next_date (recurring_next_date),
        KEY idx_recurring_parent (recurring_parent_id),
        KEY idx_recurring_end_date (recurring_end_date),
        KEY idx_recurring_renewal_date (recurring_next_renewal_date),
        KEY idx_recurring_paused (recurring_paused)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_invoice_items = "
    CREATE TABLE invoice_items (
        id int(11) NOT NULL AUTO_INCREMENT,
        milestone_id int(255) NOT NULL,
        description varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        item_description TEXT NULL,
        rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        sort_order int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY milestone_id (milestone_id),
        CONSTRAINT fk_invoice_items_milestone FOREIGN KEY (milestone_id) REFERENCES milestones (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table7 = "
    CREATE TABLE tasks (
        id int(11) NOT NULL AUTO_INCREMENT,
        project_id int(11) NOT NULL,
        parent_task_id INT(11) DEFAULT NULL COMMENT 'Parent task ID for sub-tasks',
        status varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'todo',
        last_default_status varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
        title varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        description text COLLATE utf8_unicode_ci,
        position int(11) NOT NULL DEFAULT 0,
        created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at datetime NULL,
        estimated_time_seconds INT UNSIGNED NULL DEFAULT NULL COMMENT 'Estimate in seconds',
        assigned_to varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
        start_date date DEFAULT NULL,
        due_date date DEFAULT NULL,
        user_id int(11) DEFAULT NULL COMMENT 'Owner/assignee of the task',
        creator_id int(11) DEFAULT NULL COMMENT 'User who created the task',
        is_archived TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = task hidden from active Kanban/calendar lists',
        recurrence_id int(11) DEFAULT NULL,
        recurrence_parent_task_id int(11) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_project_status (project_id, status),
        KEY idx_tasks_is_archived (is_archived),
        KEY idx_tasks_kanban_board (is_archived, status, position, id),
        KEY idx_creator (creator_id),
        KEY idx_user (user_id),
        KEY idx_parent_task (parent_task_id),
        KEY idx_tasks_recurrence (recurrence_id),
        KEY idx_tasks_recurrence_parent (recurrence_parent_task_id),
        CONSTRAINT fk_task_creator FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE SET NULL,
        CONSTRAINT fk_task_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
        CONSTRAINT fk_task_parent FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ";

    $table_task_recurrences = "
    CREATE TABLE IF NOT EXISTS `task_recurrences` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `root_task_id` INT(11) NOT NULL,
        `mode` VARCHAR(32) NOT NULL DEFAULT 'time_based',
        `interval_unit` VARCHAR(16) NULL DEFAULT NULL,
        `interval_count` INT(11) NOT NULL DEFAULT 1,
        `skip_weekends` TINYINT(1) NOT NULL DEFAULT 0,
        `start_from_date` DATE NULL DEFAULT NULL,
        `due_offset_days` INT(11) NULL DEFAULT NULL,
        `default_status` VARCHAR(50) NOT NULL DEFAULT 'todo',
        `estimated_time_seconds` INT UNSIGNED NULL DEFAULT NULL,
        `after_status` VARCHAR(50) NOT NULL DEFAULT 'todo',
        `ends_at` DATE NULL DEFAULT NULL,
        `next_run_date` DATE NULL DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_tr_root` (`root_task_id`),
        KEY `idx_tr_active_next` (`is_active`, `mode`, `next_run_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $table_task_recurrence_occurrences = "
    CREATE TABLE IF NOT EXISTS `task_recurrence_occurrences` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `recurrence_id` INT(11) NOT NULL,
        `task_id` INT(11) NOT NULL,
        `occurrence_date` DATE NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_tro_recurrence_date` (`recurrence_id`, `occurrence_date`),
        KEY `idx_tro_task` (`task_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $table8 = "
    CREATE TABLE IF NOT EXISTS project_columns (
        id int(11) NOT NULL AUTO_INCREMENT,
        project_id int(11) NOT NULL,
        column_key varchar(50) COLLATE utf8_unicode_ci NOT NULL,
        custom_name varchar(255) COLLATE utf8_unicode_ci NOT NULL,
        user_id int(11) DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY project_column (project_id, column_key, user_id)
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ";

    $table9 = "
    CREATE TABLE task_permissions (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(9) NOT NULL,
        can_create_task tinyint(1) NOT NULL DEFAULT 0,
        can_delete_task tinyint(1) NOT NULL DEFAULT 0,
        can_change_status tinyint(1) NOT NULL DEFAULT 0,
        can_update_task tinyint(1) NOT NULL DEFAULT 0,
        can_assign_members tinyint(1) NOT NULL DEFAULT 0,
        can_view_milestones tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY unique_user (user_id),
        CONSTRAINT fk_perm_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ";

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 3.1) Create theme_settings with updated color defaults
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $table10 = "
    CREATE TABLE theme_settings (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        sidebar_bg_color VARCHAR(7) DEFAULT '#0f1d40',
        sidebar_link_color VARCHAR(7) DEFAULT '#e0e6eb',
        sidebar_active_bg_color VARCHAR(7) DEFAULT '#2d4071',
        sidebar_active_color VARCHAR(7) DEFAULT '#ffffff',
        header_bg_color VARCHAR(7) DEFAULT '#222d32',
        header_link_color VARCHAR(7) DEFAULT '#ffffff',
        main_content_bg_color VARCHAR(7) DEFAULT '#ffffff',
        primary_color VARCHAR(7) DEFAULT '#0094ff',
        secondary_color VARCHAR(7) DEFAULT '#10b981',
        body_bg_color VARCHAR(7) DEFAULT '#f5f8fa',
        body_font_color VARCHAR(7) DEFAULT '#212529',
        title_color VARCHAR(7) DEFAULT '#000000',
        border_color VARCHAR(7) DEFAULT '#e1e1e1',
        card_body_color VARCHAR(7) DEFAULT '#ffffff',
        box_shadow VARCHAR(120) DEFAULT '0px 3px 13px -5px rgb(0 0 0 / 0.1)',
        card_border_radius VARCHAR(20) DEFAULT '12',
        title_font_weight VARCHAR(20) DEFAULT '600',
        primary_header_color VARCHAR(7) DEFAULT '#ffffff',
        secondary_header_color VARCHAR(7) DEFAULT '#ffffff',
        primary_header_font_color VARCHAR(7) DEFAULT '#212529',
        secondary_header_font_color VARCHAR(7) DEFAULT '#212529',
        primary_button_color VARCHAR(7) DEFAULT '#0094ff',
        secondary_button_color VARCHAR(7) DEFAULT '#10b981',
        border_button_color VARCHAR(7) DEFAULT '#dee2e6',
        primary_button_font_color VARCHAR(7) DEFAULT '#ffffff',
        secondary_button_font_color VARCHAR(7) DEFAULT '#212529',
        border_button_font_color VARCHAR(7) DEFAULT '#212529',
        button_border_radius VARCHAR(20) DEFAULT '4',
        chat_card_color VARCHAR(20) DEFAULT '#ffffff',
        chat_card_secondary_color VARCHAR(20) DEFAULT '#e6f0f9',
        chat_title_color VARCHAR(20) DEFAULT '#212529',
        chat_body_color VARCHAR(20) DEFAULT '#f5f8fa',
        chat_buttons_color VARCHAR(20) DEFAULT '#0094ff',
        chat_buttons_text_color VARCHAR(20) DEFAULT '#ffffff',
        chat_time_text_color VARCHAR(20) DEFAULT '#757575',
        chat_font_size VARCHAR(32) DEFAULT '14px',
        chat_font_weight VARCHAR(20) DEFAULT '400',
        ui_font_source VARCHAR(20) NOT NULL DEFAULT 'default' COMMENT 'default|google|websafe|custom',
        ui_google_font_family VARCHAR(120) NOT NULL DEFAULT 'Lato',
        ui_google_font_weights VARCHAR(80) NOT NULL DEFAULT '400;700;900',
        ui_websafe_stack VARCHAR(255) NOT NULL DEFAULT '',
        ui_custom_font_id INT UNSIGNED NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci
    ";

    $table_custom_fonts = "
    CREATE TABLE IF NOT EXISTS `custom_fonts` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `family_css` varchar(120) NOT NULL,
        `font_weight` int NOT NULL DEFAULT 400,
        `is_italic` tinyint(1) NOT NULL DEFAULT 0,
        `file_path` varchar(255) NOT NULL,
        `original_filename` varchar(255) NOT NULL DEFAULT '',
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_family` (`family_css`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 3.2) Create trigger for tasks
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $taskTrigger = "
    CREATE TRIGGER before_task_insert
    BEFORE INSERT ON tasks
    FOR EACH ROW
    BEGIN
        IF NEW.creator_id IS NULL AND NEW.user_id IS NOT NULL THEN
            SET NEW.creator_id = NEW.user_id;
        END IF;
        IF NEW.user_id IS NULL AND NEW.creator_id IS NOT NULL THEN
            SET NEW.user_id = NEW.creator_id;
        END IF;
    END
    ";

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 3.3) Private notes: notes, team shares, media (matches includes/migrations/3.20.sql)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $table11 = "
    CREATE TABLE IF NOT EXISTS `private_notes` (
      `id` INT NOT NULL AUTO_INCREMENT,
      `user_id` INT NOT NULL,
      `title` VARCHAR(255) NOT NULL DEFAULT '',
      `content` TEXT NOT NULL COMMENT 'Encrypted content using AES-256-CBC',
      `color` VARCHAR(20) DEFAULT NULL,
      `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
      `is_trashed` TINYINT(1) NOT NULL DEFAULT 0,
      `public_share_enabled` TINYINT(1) NOT NULL DEFAULT 0,
      `public_share_token` VARCHAR(96) NULL DEFAULT NULL,
      `public_share_permission` ENUM('view','edit') NOT NULL DEFAULT 'view',
      `public_share_updated_at` DATETIME NULL DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_private_notes_user_id` (`user_id`),
      KEY `idx_private_notes_updated_at` (`updated_at`),
      KEY `idx_private_notes_archived` (`is_archived`),
      KEY `idx_private_notes_trashed` (`is_trashed`),
      KEY `idx_private_notes_user_archive` (`user_id`, `is_archived`, `is_trashed`),
      UNIQUE KEY `ux_private_notes_public_share_token` (`public_share_token`),
      KEY `idx_private_notes_public_share_enabled` (`public_share_enabled`),
      CONSTRAINT `fk_private_notes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $table11_private_note_shares = "
    CREATE TABLE IF NOT EXISTS `private_note_shares` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `note_id` INT NOT NULL,
      `owner_user_id` INT NOT NULL,
      `shared_with_user_id` INT NOT NULL,
      `permission` ENUM('view','edit') NOT NULL DEFAULT 'view',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_private_note_share_note_user` (`note_id`, `shared_with_user_id`),
      KEY `idx_private_note_shares_owner` (`owner_user_id`),
      KEY `idx_private_note_shares_target` (`shared_with_user_id`),
      CONSTRAINT `fk_private_note_shares_note` FOREIGN KEY (`note_id`) REFERENCES `private_notes` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_private_note_shares_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_private_note_shares_target` FOREIGN KEY (`shared_with_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $table11_private_note_media = "
    CREATE TABLE IF NOT EXISTS `private_note_media` (
      `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
      `note_id` INT NULL DEFAULT NULL,
      `user_id` INT NOT NULL,
      `file_path` VARCHAR(512) NOT NULL,
      `mime_type` VARCHAR(128) NOT NULL DEFAULT '',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_private_note_media_note_id` (`note_id`),
      KEY `idx_private_note_media_user_id` (`user_id`),
      CONSTRAINT `fk_private_note_media_note` FOREIGN KEY (`note_id`) REFERENCES `private_notes` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_private_note_media_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 3.4) Team groups + client companies (includes/migrations/3.23.sql)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $table_staff_team_groups = "
    CREATE TABLE IF NOT EXISTS `staff_team_groups` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $table_staff_team_group_members = "
    CREATE TABLE IF NOT EXISTS `staff_team_group_members` (
      `group_id` INT UNSIGNED NOT NULL,
      `user_id` INT NOT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`group_id`, `user_id`),
      KEY `idx_staff_team_group_members_user` (`user_id`),
      CONSTRAINT `fk_staff_team_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `staff_team_groups` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_staff_team_group_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $table_client_companies = "
    CREATE TABLE IF NOT EXISTS `client_companies` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $table_client_company_members = "
    CREATE TABLE IF NOT EXISTS `client_company_members` (
      `company_id` INT UNSIGNED NOT NULL,
      `user_id` INT NOT NULL,
      `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`company_id`, `user_id`),
      KEY `idx_client_company_members_user` (`user_id`),
      CONSTRAINT `fk_client_company_members_company` FOREIGN KEY (`company_id`) REFERENCES `client_companies` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_client_company_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $table_client_company_links = "
    CREATE TABLE IF NOT EXISTS `client_company_links` (
      `company_id` INT UNSIGNED NOT NULL,
      `linked_company_id` INT UNSIGNED NOT NULL,
      `created_by` INT DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`company_id`, `linked_company_id`),
      KEY `idx_client_company_links_linked` (`linked_company_id`),
      CONSTRAINT `fk_client_company_links_parent` FOREIGN KEY (`company_id`) REFERENCES `client_companies` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_client_company_links_child` FOREIGN KEY (`linked_company_id`) REFERENCES `client_companies` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_client_company_links_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $table_company_activity_log = "
    CREATE TABLE IF NOT EXISTS `company_activity_log` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $table_client_company_pins = "
    CREATE TABLE IF NOT EXISTS `client_company_pins` (
      `company_id` INT UNSIGNED NOT NULL,
      `pinned_by` INT DEFAULT NULL,
      `pinned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`company_id`),
      KEY `idx_client_company_pins_pinned_at` (`pinned_at`),
      CONSTRAINT `fk_client_company_pins_company` FOREIGN KEY (`company_id`) REFERENCES `client_companies` (`id`) ON DELETE CASCADE,
      CONSTRAINT `fk_client_company_pins_user` FOREIGN KEY (`pinned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 4) Run through each CREATE statement
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // Note: private_notes table is created with encryption support
    // All note content will be automatically encrypted using AES-256-CBC
    // Encryption functions are available in lib-initialize.php
    $table_extra_tasks_columns = "
    CREATE TABLE IF NOT EXISTS extra_tasks_columns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        task_id INT NOT NULL,
        column_key VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_task (user_id, task_id),
        INDEX idx_user_id (user_id),
        INDEX idx_task_id (task_id),
        INDEX idx_column_key (column_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    // Add roles and role_permissions tables
    $table_roles = "
    CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_role_permissions = "\n    CREATE TABLE IF NOT EXISTS role_permissions (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        role_id INT NOT NULL,\n        permission_key VARCHAR(100) NOT NULL,\n        value TINYINT(1) DEFAULT 0,\n        UNIQUE KEY unique_role_permission (role_id, permission_key),\n        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;\n    ";

    // Add notifications table
    $table_notifications = "
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        from_user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        related_id INT DEFAULT NULL,
        related_type VARCHAR(50) DEFAULT NULL,
        related_project_id INT DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_from_user_id (from_user_id),
        INDEX idx_type (type),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at),
        INDEX idx_related_project_id (related_project_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_discussion_reads = "
    CREATE TABLE IF NOT EXISTS discussion_reads (
        message_id INT NOT NULL,
        user_id INT NOT NULL,
        read_time INT NOT NULL,
        PRIMARY KEY (message_id, user_id),
        INDEX idx_user_id (user_id),
        INDEX idx_message_id (message_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    // Add password reset tokens table for secure password reset functionality
    $table_password_reset_tokens = "
    CREATE TABLE IF NOT EXISTS password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_user_id (user_id),
        INDEX idx_expires_at (expires_at),
        INDEX idx_used (used),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    // Add login attempts table for security logging and brute force protection
    $table_login_attempts = "
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        email VARCHAR(255) NULL,
        success BOOLEAN DEFAULT FALSE,
        type ENUM('login', 'logout', 'remember_me', 'failed') DEFAULT 'login',
        ip_address VARCHAR(45),
        user_agent TEXT,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_user_id (user_id),
        INDEX idx_attempt_time (attempt_time),
        INDEX idx_success (success)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    // Free edition: license (wc_am_*) tables are not created.

    // Google Drive Integration Tables (not executed on Free — filtered / removed from create list)
    $table_google_drive_settings = "
    CREATE TABLE IF NOT EXISTS google_drive_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id VARCHAR(500) NOT NULL,
        client_secret VARCHAR(500) NOT NULL,
        access_token TEXT,
        refresh_token TEXT,
        token_expiry TIMESTAMP NULL,
        is_enabled TINYINT(1) DEFAULT 0,
        storage_mode VARCHAR(32) NOT NULL DEFAULT 'google_drive',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_mv_upload_sessions = "
    CREATE TABLE IF NOT EXISTS mv_upload_sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        public_upload_id CHAR(36) NOT NULL,
        user_id INT NOT NULL,
        project_id INT NULL DEFAULT NULL,
        folder_id INT NULL DEFAULT NULL,
        storage_target ENUM('google_drive','local') NOT NULL DEFAULT 'google_drive',
        upload_path ENUM('direct','relay','legacy') NOT NULL DEFAULT 'direct',
        file_name VARCHAR(255) NOT NULL,
        file_size BIGINT UNSIGNED NOT NULL,
        mime_type VARCHAR(127) NOT NULL DEFAULT 'application/octet-stream',
        file_fingerprint VARCHAR(128) NULL DEFAULT NULL,
        relative_path VARCHAR(512) NULL DEFAULT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'created',
        bytes_confirmed BIGINT UNSIGNED NOT NULL DEFAULT 0,
        drive_file_id VARCHAR(255) NULL DEFAULT NULL,
        drive_session_uri_enc TEXT NULL,
        crm_file_id INT NULL DEFAULT NULL,
        retry_count INT UNSIGNED NOT NULL DEFAULT 0,
        last_error VARCHAR(500) NULL DEFAULT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_mv_upload_public_id (public_upload_id),
        KEY idx_mv_upload_user_status (user_id, status),
        KEY idx_mv_upload_status_expires (status, expires_at),
        KEY idx_mv_upload_drive_file (drive_file_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_shared_folders = "
    CREATE TABLE IF NOT EXISTS shared_folders (
        id INT(11) NOT NULL AUTO_INCREMENT,
        folder_id INT(11) NOT NULL,
        shared_by INT(11) NOT NULL,
        shared_with INT(11) NOT NULL,
        permissions VARCHAR(50) NOT NULL,
        shared_at DATETIME NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        INDEX idx_folder_id (folder_id),
        INDEX idx_shared_by (shared_by),
        INDEX idx_shared_with (shared_with)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_shared_files = "
    CREATE TABLE IF NOT EXISTS shared_files (
        id INT(11) NOT NULL AUTO_INCREMENT,
        file_id INT(11) NOT NULL,
        shared_by INT(11) NOT NULL,
        shared_with INT(11) NOT NULL,
        permissions VARCHAR(50) NOT NULL,
        shared_at DATETIME NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY unique_file_share (file_id, shared_with),
        INDEX idx_file_id (file_id),
        INDEX idx_shared_by (shared_by),
        INDEX idx_shared_with (shared_with)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Media Vault extended share tables (project links, profile shares, public links)
    $table_media_vault_project_links = "
    CREATE TABLE IF NOT EXISTS media_vault_project_links (
        id INT(11) NOT NULL AUTO_INCREMENT,
        shared_by INT(11) NOT NULL,
        project_id INT(11) NOT NULL,
        entity_type ENUM('folder','file') NOT NULL,
        entity_id INT(11) NOT NULL,
        permissions VARCHAR(50) NOT NULL DEFAULT 'read',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_mvpl_entity (shared_by, project_id, entity_type, entity_id),
        KEY idx_mvpl_project (project_id),
        KEY idx_mvpl_shared_by (shared_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_media_vault_project_link_users = "
    CREATE TABLE IF NOT EXISTS media_vault_project_link_users (
        id INT(11) NOT NULL AUTO_INCREMENT,
        link_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_link_user (link_id, user_id),
        KEY idx_mvplu_user (user_id),
        KEY idx_mvplu_link (link_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_media_vault_profile_shares = "
    CREATE TABLE IF NOT EXISTS media_vault_profile_shares (
        id INT(11) NOT NULL AUTO_INCREMENT,
        shared_by INT(11) NOT NULL,
        entity_type ENUM('folder','file') NOT NULL,
        entity_id INT(11) NOT NULL,
        shared_with INT(11) NOT NULL,
        permissions VARCHAR(50) NOT NULL DEFAULT 'read',
        shared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_mvps_recipient (entity_type, entity_id, shared_with),
        KEY idx_mvps_shared_by (shared_by),
        KEY idx_mvps_shared_with (shared_with)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_media_vault_link_shares = "
    CREATE TABLE IF NOT EXISTS media_vault_link_shares (
        id INT(11) NOT NULL AUTO_INCREMENT,
        token VARCHAR(64) NOT NULL,
        entity_type ENUM('folder','file') NOT NULL,
        entity_id INT(11) NOT NULL,
        permission ENUM('view_only','view_download') NOT NULL DEFAULT 'view_only',
        shared_by INT(11) NOT NULL,
        expires_at DATETIME DEFAULT NULL,
        revoked_at DATETIME DEFAULT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_token (token),
        KEY idx_mvlk_entity (entity_type, entity_id),
        KEY idx_mvlk_shared_by (shared_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_file_folders = "
    CREATE TABLE IF NOT EXISTS file_folders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        project_id INT NULL,
        parent_folder_id INT NULL DEFAULT NULL,
        created_by INT NOT NULL,
        google_drive_folder_id VARCHAR(255) DEFAULT NULL,
        google_drive_web_view_link VARCHAR(500) DEFAULT NULL,
        is_deleted TINYINT(1) DEFAULT 0,
        deleted_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(p_id) ON DELETE CASCADE,
        FOREIGN KEY (parent_folder_id) REFERENCES file_folders(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_project_id (project_id),
        INDEX idx_parent_folder (parent_folder_id),
        INDEX idx_created_by (created_by),
        INDEX idx_google_drive_folder_id (google_drive_folder_id),
        INDEX idx_is_deleted (is_deleted)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_files = "
    CREATE TABLE IF NOT EXISTS files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        file_size BIGINT NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        folder_id INT NULL,
        project_id INT NULL,
        uploaded_by INT NOT NULL,
        google_drive_file_id VARCHAR(255) NULL,
        google_drive_web_view_link VARCHAR(500) NULL,
        google_drive_download_link VARCHAR(500) NULL,
        local_file_path VARCHAR(500) NULL,
        is_deleted TINYINT(1) DEFAULT 0,
        deleted_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (folder_id) REFERENCES file_folders(id) ON DELETE SET NULL,
        FOREIGN KEY (project_id) REFERENCES projects(p_id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_folder_id (folder_id),
        INDEX idx_project_id (project_id),
        INDEX idx_uploaded_by (uploaded_by),
        INDEX idx_google_drive_file_id (google_drive_file_id),
        INDEX idx_is_deleted (is_deleted)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_file_permissions = "
    CREATE TABLE IF NOT EXISTS file_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT NOT NULL,
        user_id INT NOT NULL,
        permission_type ENUM('view', 'edit', 'delete', 'admin') DEFAULT 'view',
        granted_by INT NOT NULL,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_file_user (file_id, user_id),
        INDEX idx_file_id (file_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_folder_permissions = "
    CREATE TABLE IF NOT EXISTS folder_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        folder_id INT NOT NULL,
        user_id INT NOT NULL,
        permission_type ENUM('view', 'edit', 'delete', 'admin') DEFAULT 'view',
        granted_by INT NOT NULL,
        granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (folder_id) REFERENCES file_folders(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_folder_user (folder_id, user_id),
        INDEX idx_folder_id (folder_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_project_folder_permission_overrides = "
    CREATE TABLE IF NOT EXISTS project_folder_permission_overrides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        folder_id INT NOT NULL,
        user_id INT NOT NULL,
        can_view TINYINT(1) DEFAULT 1,
        can_download TINYINT(1) DEFAULT 1,
        can_upload TINYINT(1) DEFAULT 0,
        can_edit TINYINT(1) DEFAULT 0,
        can_delete TINYINT(1) DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (folder_id) REFERENCES file_folders(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_folder_user_override (folder_id, user_id),
        INDEX idx_folder_id (folder_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_file_activity_log = "
    CREATE TABLE IF NOT EXISTS file_activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_id INT NULL,
        folder_id INT NULL,
        user_id INT NOT NULL,
        action_type ENUM('upload', 'download', 'delete', 'rename', 'move', 'share', 'unshare') NOT NULL,
        action_details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE SET NULL,
        FOREIGN KEY (folder_id) REFERENCES file_folders(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_file_id (file_id),
        INDEX idx_folder_id (folder_id),
        INDEX idx_user_id (user_id),
        INDEX idx_action_type (action_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    // Calendar Integration Tables
    $table_calendar_events = "
    CREATE TABLE IF NOT EXISTS calendar_events (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_calendar_task_schedule = "
    CREATE TABLE IF NOT EXISTS calendar_task_schedule (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_google_calendar_settings = "
    CREATE TABLE IF NOT EXISTS google_calendar_settings (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_google_calendar_tokens = "
    CREATE TABLE IF NOT EXISTS google_calendar_tokens (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_google_calendar_sync_log = "
    CREATE TABLE IF NOT EXISTS google_calendar_sync_log (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    // Attendance module (same schema as includes/migrations/3.19.sql)
    $table_attendance_shifts = "
    CREATE TABLE IF NOT EXISTS `attendance_shifts` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_shift_assignments = "
    CREATE TABLE IF NOT EXISTS `attendance_shift_assignments` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_records = "
    CREATE TABLE IF NOT EXISTS `attendance_records` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_punches = "
    CREATE TABLE IF NOT EXISTS `attendance_punches` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_leave_types = "
    CREATE TABLE IF NOT EXISTS `attendance_leave_types` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `code` VARCHAR(30) DEFAULT NULL,
        `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
        `annual_quota` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_leave_type_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_leave_balances = "
    CREATE TABLE IF NOT EXISTS `attendance_leave_balances` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_leave_requests = "
    CREATE TABLE IF NOT EXISTS `attendance_leave_requests` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_holidays = "
    CREATE TABLE IF NOT EXISTS `attendance_holidays` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_weekly_off_rules = "
    CREATE TABLE IF NOT EXISTS `attendance_weekly_off_rules` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `branch_id` INT DEFAULT NULL,
        `department` VARCHAR(100) DEFAULT NULL,
        `weekday` TINYINT NOT NULL COMMENT '1=Mon ... 7=Sun',
        `week_occurrence` ENUM('every','first','second','third','fourth','last') NOT NULL DEFAULT 'every',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_weekly_off_scope` (`branch_id`, `department`, `weekday`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_regularization_requests = "
    CREATE TABLE IF NOT EXISTS `attendance_regularization_requests` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_policies = "
    CREATE TABLE IF NOT EXISTS `attendance_policies` (
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
        `allowed_weekdays_json` TEXT NULL,
        `use_policy_threshold_override` TINYINT(1) NOT NULL DEFAULT 0,
        `weekday_time_overrides_json` TEXT NULL,
        `updated_by` INT DEFAULT NULL,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_policy_scope` (`company_id`, `branch_id`),
        FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_payroll_exports = "
    CREATE TABLE IF NOT EXISTS `attendance_payroll_exports` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_audit_logs = "
    CREATE TABLE IF NOT EXISTS `attendance_audit_logs` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_overtime = "
    CREATE TABLE IF NOT EXISTS `attendance_overtime` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    $table_attendance_user_day_overrides = "
    CREATE TABLE IF NOT EXISTS `attendance_user_day_overrides` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 3.3) Version 3.3 Updates - Security Logs and Profile Notes
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $table_security_logs = "
    CREATE TABLE IF NOT EXISTS security_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        event VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_event (event),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_profile_notes = "
    CREATE TABLE IF NOT EXISTS profile_notes (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL COMMENT 'The client/user this note is about',
        creator_id INT(11) NOT NULL COMMENT 'Admin or staff who created this note',
        creator_type ENUM('admin', 'staff') NOT NULL COMMENT 'Type of creator',
        content TEXT NOT NULL COMMENT 'Encrypted note content',
        color VARCHAR(32) NULL DEFAULT NULL COMMENT 'default|blue|green|yellow',
        is_archived TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_creator_id (creator_id),
        INDEX idx_creator_type (creator_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_profile_note_shares = "
    CREATE TABLE IF NOT EXISTS profile_note_shares (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        profile_note_id INT NOT NULL,
        creator_user_id INT NOT NULL,
        shared_with_user_id INT NOT NULL,
        permission ENUM('view','edit') NOT NULL DEFAULT 'view',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_profile_note_share (profile_note_id, shared_with_user_id),
        KEY idx_pn_shares_creator (creator_user_id),
        KEY idx_pn_shares_target (shared_with_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Task Chat Tables
    $table_task_chat = "
    CREATE TABLE IF NOT EXISTS task_chat (
        id INT(11) NOT NULL AUTO_INCREMENT,
        message TEXT NOT NULL COMMENT 'Encrypted message content',
        time INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        task_id INT(11) NOT NULL,
        storage_a INT(11) NOT NULL DEFAULT 1,
        storage_b INT(11) NOT NULL DEFAULT 1,
        status ENUM('read','unread') NOT NULL DEFAULT 'unread',
        reply_to INT(11) DEFAULT NULL,
        PRIMARY KEY (id),
        INDEX idx_task_id (task_id),
        INDEX idx_user_id (user_id),
        INDEX idx_time (time),
        INDEX idx_task_reply_to (reply_to)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_task_chat_reads = "
    CREATE TABLE IF NOT EXISTS task_chat_reads (
        id INT(11) NOT NULL AUTO_INCREMENT,
        message_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        read_time INT(11) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY message_user (message_id, user_id),
        INDEX idx_message_id (message_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_chat_message_reactions = "
    CREATE TABLE IF NOT EXISTS `chat_message_reactions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_chat_message_stars = "
    CREATE TABLE IF NOT EXISTS `chat_message_stars` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `chat_type` ENUM('one_to_one','group','discussion','task') NOT NULL,
      `message_id` INT(11) NOT NULL,
      `user_id` INT(11) NOT NULL,
      `created_at` INT(11) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_star` (`chat_type`,`message_id`,`user_id`),
      KEY `idx_msg` (`chat_type`,`message_id`),
      KEY `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_chat_message_pins = "
    CREATE TABLE IF NOT EXISTS `chat_message_pins` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `chat_type` ENUM('one_to_one','group','discussion','task') NOT NULL,
      `context_id` INT(11) NOT NULL,
      `message_id` INT(11) NOT NULL,
      `pinned_by` INT(11) NOT NULL,
      `pinned_at` INT(11) NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uniq_pin_context` (`chat_type`,`context_id`),
      KEY `idx_message` (`message_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_task_files = "
    CREATE TABLE IF NOT EXISTS task_files (
        id INT(11) NOT NULL AUTO_INCREMENT,
        task_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_size INT(11) NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_task_id (task_id),
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_subtasks = "
    CREATE TABLE IF NOT EXISTS subtasks (
        id INT(11) NOT NULL AUTO_INCREMENT,
        parent_task_id INT(11) NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status VARCHAR(20) NOT NULL DEFAULT 'todo',
        assigned_to VARCHAR(255) DEFAULT NULL,
        start_date DATE DEFAULT NULL,
        due_date DATE DEFAULT NULL,
        user_id INT(11) NOT NULL,
        creator_id INT(11) NOT NULL,
        position INT(11) NOT NULL DEFAULT 0,
        completed_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_parent_task (parent_task_id),
        INDEX idx_status (status),
        INDEX idx_assigned_to (assigned_to),
        FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sub-tasks linked to main tasks';
    ";

    // Task sidebar activity log (includes/migrations/3.27.sql)
    $table_task_activities = "
    CREATE TABLE IF NOT EXISTS `task_activities` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `task_id` int(11) NOT NULL,
      `actor_user_id` int(11) DEFAULT NULL,
      `event_type` varchar(50) NOT NULL,
      `event_title` varchar(150) NOT NULL,
      `event_message` text DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_task_activities_task_created` (`task_id`,`created_at`),
      KEY `idx_task_activities_actor` (`actor_user_id`),
      CONSTRAINT `fk_task_activities_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_invoice_reminders_sent = "
    CREATE TABLE IF NOT EXISTS invoice_reminders_sent (
        id INT(11) NOT NULL AUTO_INCREMENT,
        invoice_id INT(11) NOT NULL,
        reminder_number INT(11) NOT NULL,
        sent_date DATETIME NOT NULL,
        sent_to VARCHAR(20) NOT NULL DEFAULT 'client',
        PRIMARY KEY (id),
        KEY invoice_id (invoice_id),
        KEY reminder_number (reminder_number),
        KEY sent_date (sent_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_client_stripe_customers = "
    CREATE TABLE IF NOT EXISTS client_stripe_customers (
        user_id INT(11) NOT NULL,
        stripe_customer_id VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id),
        KEY idx_stripe_customer_id (stripe_customer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_client_payment_methods = "
    CREATE TABLE IF NOT EXISTS client_payment_methods (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        stripe_payment_method_id VARCHAR(255) NOT NULL,
        brand VARCHAR(32) NOT NULL DEFAULT '',
        last4 VARCHAR(4) NOT NULL DEFAULT '',
        exp_month TINYINT(2) UNSIGNED NOT NULL DEFAULT 0,
        exp_year SMALLINT(4) UNSIGNED NOT NULL DEFAULT 0,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_user_pm (user_id, stripe_payment_method_id),
        KEY idx_user_default (user_id, is_default)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Custom Fields Tables
    $table_custom_fields = "
    CREATE TABLE IF NOT EXISTS custom_fields (
      id INT(11) NOT NULL AUTO_INCREMENT,
      entity_type VARCHAR(50) NOT NULL DEFAULT 'lead',
      field_type VARCHAR(50) NOT NULL,
      label VARCHAR(255) NOT NULL,
      description TEXT DEFAULT NULL,
      list_items TEXT DEFAULT NULL,
      is_required TINYINT(1) NOT NULL DEFAULT 0,
      is_disabled TINYINT(1) NOT NULL DEFAULT 0,
      sort_order INT(11) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_custom_fields_entity_type (entity_type),
      KEY idx_custom_fields_sort (entity_type, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_lead_custom_field_values = "
    CREATE TABLE IF NOT EXISTS lead_custom_field_values (
      id INT(11) NOT NULL AUTO_INCREMENT,
      lead_id INT(11) NOT NULL,
      custom_field_id INT(11) NOT NULL,
      field_value TEXT DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_lead_custom_field (lead_id, custom_field_id),
      KEY idx_lead_custom_field_values_lead (lead_id),
      KEY idx_lead_custom_field_values_field (custom_field_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_user_custom_field_values = "
    CREATE TABLE IF NOT EXISTS user_custom_field_values (
      id INT(11) NOT NULL AUTO_INCREMENT,
      user_id INT(11) NOT NULL,
      custom_field_id INT(11) NOT NULL,
      field_value TEXT DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_user_custom_field (user_id, custom_field_id),
      KEY idx_user_custom_field_values_user (user_id),
      KEY idx_user_custom_field_values_field (custom_field_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_project_custom_field_values = "
    CREATE TABLE IF NOT EXISTS project_custom_field_values (
      id INT(11) NOT NULL AUTO_INCREMENT,
      project_id INT(11) NOT NULL,
      custom_field_id INT(11) NOT NULL,
      field_value TEXT DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_project_custom_field (project_id, custom_field_id),
      KEY idx_project_cf_values_project (project_id),
      KEY idx_project_cf_values_field (custom_field_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_task_custom_field_values = "
    CREATE TABLE IF NOT EXISTS task_custom_field_values (
      id INT(11) NOT NULL AUTO_INCREMENT,
      task_id INT(11) NOT NULL,
      custom_field_id INT(11) NOT NULL,
      field_value TEXT DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_task_custom_field (task_id, custom_field_id),
      KEY idx_task_cf_values_task (task_id),
      KEY idx_task_cf_values_field (custom_field_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_company_custom_field_values = "
    CREATE TABLE IF NOT EXISTS company_custom_field_values (
      id INT(11) NOT NULL AUTO_INCREMENT,
      company_id INT UNSIGNED NOT NULL,
      custom_field_id INT(11) NOT NULL,
      field_value TEXT DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uq_company_custom_field (company_id, custom_field_id),
      KEY idx_company_cf_values_company (company_id),
      KEY idx_company_cf_values_field (custom_field_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Tags Tables
    $table_tag_categories = "
    CREATE TABLE IF NOT EXISTS tag_categories (
      id INT(11) NOT NULL AUTO_INCREMENT,
      name VARCHAR(100) NOT NULL,
      sort_order INT(11) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_tag_categories_name (name),
      KEY idx_tag_categories_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_tags = "
    CREATE TABLE IF NOT EXISTS tags (
      id INT(11) NOT NULL AUTO_INCREMENT,
      category_id INT(11) NOT NULL,
      name VARCHAR(100) NOT NULL,
      color_class VARCHAR(50) DEFAULT 'badge tags-bg',
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY uniq_tags_category_name (category_id, name),
      KEY idx_tags_category (category_id),
      CONSTRAINT fk_tags_category FOREIGN KEY (category_id) REFERENCES tag_categories (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_taggables = "
    CREATE TABLE IF NOT EXISTS taggables (
      tag_id INT(11) NOT NULL,
      entity_type VARCHAR(40) NOT NULL,
      entity_id INT(11) NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (tag_id, entity_type, entity_id),
      KEY idx_taggables_entity (entity_type, entity_id),
      CONSTRAINT fk_taggables_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    // Leads Tables
    $table_lead_statuses = "
    CREATE TABLE IF NOT EXISTS lead_statuses (
      id INT(11) NOT NULL AUTO_INCREMENT,
      name VARCHAR(100) NOT NULL,
      color_class VARCHAR(50) DEFAULT 'color-todo-bg',
      sort_order INT(11) NOT NULL DEFAULT 0,
      is_default TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT(11) DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_lead_statuses_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_lead_sources = "
    CREATE TABLE IF NOT EXISTS lead_sources (
      id INT(11) NOT NULL AUTO_INCREMENT,
      name VARCHAR(100) NOT NULL,
      sort_order INT(11) NOT NULL DEFAULT 0,
      is_default TINYINT(1) NOT NULL DEFAULT 0,
      created_by INT(11) DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_lead_sources_sort (sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_leads = "
    CREATE TABLE IF NOT EXISTS leads (
      id INT(11) NOT NULL AUTO_INCREMENT,
      status_id INT(11) DEFAULT NULL,
      board_position INT(11) NOT NULL DEFAULT 0,
      source_id INT(11) DEFAULT NULL,
      priority VARCHAR(50) DEFAULT NULL,
      is_lost TINYINT(1) NOT NULL DEFAULT 0,
      is_junk TINYINT(1) NOT NULL DEFAULT 0,
      is_won TINYINT(1) NOT NULL DEFAULT 0,
      lead_value DECIMAL(10,2) DEFAULT NULL,
      currency VARCHAR(20) DEFAULT NULL,
      assigned_to VARCHAR(255) DEFAULT NULL,
      name VARCHAR(150) NOT NULL,
      last_name VARCHAR(150) DEFAULT NULL,
      email VARCHAR(190) DEFAULT NULL,
      phone VARCHAR(50) DEFAULT NULL,
      website VARCHAR(255) DEFAULT NULL,
      company VARCHAR(150) DEFAULT NULL,
      expected_close_date DATE DEFAULT NULL,
      position VARCHAR(150) DEFAULT NULL,
      description TEXT DEFAULT NULL,
      country VARCHAR(100) DEFAULT NULL,
      zip VARCHAR(30) DEFAULT NULL,
      city VARCHAR(100) DEFAULT NULL,
      state VARCHAR(100) DEFAULT NULL,
      address VARCHAR(255) DEFAULT NULL,
      notes TEXT DEFAULT NULL,
      created_by INT(11) DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      converted_client_id INT(11) DEFAULT NULL,
      PRIMARY KEY (id),
      KEY idx_leads_status (status_id),
      KEY idx_leads_status_board_pos (status_id, board_position),
      KEY idx_leads_source (source_id),
      KEY idx_leads_assigned_to (assigned_to),
      KEY idx_leads_email (email),
      CONSTRAINT fk_leads_status FOREIGN KEY (status_id) REFERENCES lead_statuses (id) ON DELETE SET NULL ON UPDATE CASCADE,
      CONSTRAINT fk_leads_source FOREIGN KEY (source_id) REFERENCES lead_sources (id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_lead_activities = "
    CREATE TABLE IF NOT EXISTS lead_activities (
      id INT(11) NOT NULL AUTO_INCREMENT,
      lead_id INT(11) NOT NULL,
      actor_user_id INT(11) DEFAULT NULL,
      event_type VARCHAR(50) NOT NULL,
      event_title VARCHAR(150) NOT NULL,
      event_message TEXT DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_lead_activities_lead_created (lead_id, created_at),
      KEY idx_lead_activities_actor (actor_user_id),
      CONSTRAINT fk_lead_activities_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_lead_activity_meta = "
    CREATE TABLE IF NOT EXISTS lead_activity_meta (
      id INT(11) NOT NULL AUTO_INCREMENT,
      activity_id INT(11) NOT NULL,
      meta_key VARCHAR(100) NOT NULL,
      meta_value TEXT DEFAULT NULL,
      PRIMARY KEY (id),
      KEY idx_lead_activity_meta_activity (activity_id),
      CONSTRAINT fk_lead_activity_meta_activity FOREIGN KEY (activity_id) REFERENCES lead_activities (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_lead_comments = "
    CREATE TABLE IF NOT EXISTS lead_comments (
      id INT(11) NOT NULL AUTO_INCREMENT,
      lead_id INT(11) NOT NULL,
      user_id INT(11) NOT NULL,
      body TEXT NOT NULL,
      is_edited TINYINT(1) NOT NULL DEFAULT 0,
      edited_at DATETIME DEFAULT NULL,
      deleted_at DATETIME DEFAULT NULL,
      deleted_by INT(11) DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY idx_lead_comments_lead_created (lead_id, created_at),
      KEY idx_lead_comments_user (user_id),
      CONSTRAINT fk_lead_comments_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_lead_comment_mentions = "
    CREATE TABLE IF NOT EXISTS lead_comment_mentions (
      id INT(11) NOT NULL AUTO_INCREMENT,
      comment_id INT(11) NOT NULL,
      mentioned_user_id INT(11) NOT NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uq_lead_comment_mention (comment_id, mentioned_user_id),
      KEY idx_lead_comment_mentions_user (mentioned_user_id),
      CONSTRAINT fk_lead_comment_mentions_comment FOREIGN KEY (comment_id) REFERENCES lead_comments (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    // Import/export job queue (includes/migrations/3.23.sql)
    $table_import_jobs = "
    CREATE TABLE IF NOT EXISTS `import_jobs` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` INT NOT NULL,
      `module_name` VARCHAR(64) NOT NULL DEFAULT '',
      `target_user_type` VARCHAR(16) NULL DEFAULT NULL COMMENT 'client, staff, admin â€” only for user imports',
      `source_type` VARCHAR(16) NOT NULL DEFAULT 'csv',
      `original_filename` VARCHAR(512) NOT NULL DEFAULT '',
      `file_path` VARCHAR(1024) NOT NULL DEFAULT '' COMMENT 'Path under project root (storage)',
      `mapping_json` LONGTEXT NULL,
      `options_json` LONGTEXT NULL COMMENT 'Fallback status/source, duplicate mode, etc.',
      `import_mode` VARCHAR(32) NOT NULL DEFAULT 'create_only' COMMENT 'create_only, update_match, skip_duplicates, mixed',
      `duplicate_strategy` VARCHAR(32) NOT NULL DEFAULT 'skip' COMMENT 'skip, overwrite, merge, create_anyway',
      `dry_run` TINYINT(1) NOT NULL DEFAULT 0,
      `status` VARCHAR(24) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, completed, failed',
      `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
      `processed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
      `success_rows` INT UNSIGNED NOT NULL DEFAULT 0,
      `failed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
      `updated_rows` INT UNSIGNED NOT NULL DEFAULT 0,
      `skipped_rows` INT UNSIGNED NOT NULL DEFAULT 0,
      `error_file_path` VARCHAR(1024) NULL DEFAULT NULL,
      `last_error` TEXT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `started_at` DATETIME NULL DEFAULT NULL,
      `finished_at` DATETIME NULL DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_import_jobs_user_id` (`user_id`),
      KEY `idx_import_jobs_status` (`status`),
      KEY `idx_import_jobs_module` (`module_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_import_job_errors = "
    CREATE TABLE IF NOT EXISTS `import_job_errors` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `import_job_id` BIGINT UNSIGNED NOT NULL,
      `row_number` INT UNSIGNED NOT NULL DEFAULT 0,
      `raw_data_json` LONGTEXT NULL,
      `error_message` VARCHAR(1024) NOT NULL DEFAULT '',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_import_job_errors_job` (`import_job_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Future modules (schema only; same DDL as installer_run_future_modules_migrations)
    $table_task_time_entries = "
    CREATE TABLE IF NOT EXISTS `task_time_entries` (
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
        CONSTRAINT `fk_tte_task` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_tte_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_task_timer_sessions = "
    CREATE TABLE IF NOT EXISTS `task_timer_sessions` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_task_time_reports_daily = "
    CREATE TABLE IF NOT EXISTS `task_time_reports_daily` (
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
        CONSTRAINT `fk_ttrd_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_ttrd_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`p_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_ttrd_task` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_project_activities = "
    CREATE TABLE IF NOT EXISTS `project_activities` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_id` INT(11) NOT NULL,
        `actor_user_id` INT(11) DEFAULT NULL,
        `entity_type` VARCHAR(64) NOT NULL DEFAULT '',
        `entity_id` INT(11) DEFAULT NULL,
        `action` VARCHAR(64) NOT NULL DEFAULT '',
        `metadata` JSON NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_pa_project_created` (`project_id`, `created_at`),
        CONSTRAINT `fk_pa_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`p_id`) ON DELETE CASCADE,
        CONSTRAINT `fk_pa_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_integration_zapier = "
    CREATE TABLE IF NOT EXISTS `integration_zapier` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `webhook_secret` VARCHAR(255) DEFAULT NULL,
        `api_key_suffix` VARCHAR(32) DEFAULT NULL COMMENT 'Display-only last chars',
        `settings_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_integration_zapier_logs = "
    CREATE TABLE IF NOT EXISTS `integration_zapier_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `direction` ENUM('inbound','outbound') NOT NULL DEFAULT 'inbound',
        `event_type` VARCHAR(128) NOT NULL DEFAULT '',
        `status_code` SMALLINT DEFAULT NULL,
        `request_snippet` TEXT NULL,
        `response_snippet` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_izl_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_integration_google_meet = "
    CREATE TABLE IF NOT EXISTS `integration_google_meet` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) DEFAULT NULL COMMENT 'NULL = org default',
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `client_credentials_json` LONGTEXT NULL COMMENT 'Encrypt in app layer',
        `refresh_token` TEXT NULL,
        `settings_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_igm_user` (`user_id`),
        CONSTRAINT `fk_igm_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_integration_chatgpt = "
    CREATE TABLE IF NOT EXISTS `integration_chatgpt` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `api_key_suffix` VARCHAR(32) DEFAULT NULL,
        `default_model` VARCHAR(64) NOT NULL DEFAULT 'gpt-4o-mini',
        `settings_json` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_integration_chatgpt_logs = "
    CREATE TABLE IF NOT EXISTS `integration_chatgpt_logs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) DEFAULT NULL,
        `model` VARCHAR(64) DEFAULT NULL,
        `tokens_used` INT UNSIGNED DEFAULT NULL,
        `request_snippet` TEXT NULL,
        `response_snippet` TEXT NULL,
        `status` VARCHAR(32) NOT NULL DEFAULT 'ok',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_icgl_created` (`created_at`),
        KEY `idx_icgl_user` (`user_id`),
        CONSTRAINT `fk_icgl_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_integration_webhook_deliveries = "
    CREATE TABLE IF NOT EXISTS `integration_webhook_deliveries` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `integration_key` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'zapier, custom, etc.',
        `event_type` VARCHAR(128) NOT NULL DEFAULT '',
        `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
        `payload_snippet` TEXT NULL,
        `response_snippet` TEXT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_iwd_key_created` (`integration_key`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_quotations = "
    CREATE TABLE IF NOT EXISTS `quotations` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `project_id` INT(11) DEFAULT NULL,
        `client_user_id` INT(11) DEFAULT NULL,
        `quote_number` VARCHAR(64) NOT NULL DEFAULT '',
        `title` VARCHAR(255) NOT NULL DEFAULT '',
        `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
        `currency` VARCHAR(10) DEFAULT NULL,
        `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `tax_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        `valid_until` DATE DEFAULT NULL,
        `notes` TEXT NULL,
        `proposal_body` LONGTEXT NULL,
        `created_by` INT(11) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_q_project` (`project_id`),
        KEY `idx_q_client` (`client_user_id`),
        KEY `idx_q_status` (`status`),
        CONSTRAINT `fk_q_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`p_id`) ON DELETE SET NULL,
        CONSTRAINT `fk_q_client` FOREIGN KEY (`client_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
        CONSTRAINT `fk_q_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_quotation_items = "
    CREATE TABLE IF NOT EXISTS `quotation_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `quotation_id` INT UNSIGNED NOT NULL,
        `line_order` INT UNSIGNED NOT NULL DEFAULT 0,
        `description` TEXT NOT NULL,
        `quantity` DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
        `unit_price` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
        `line_total` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
        PRIMARY KEY (`id`),
        KEY `idx_qi_quote` (`quotation_id`),
        CONSTRAINT `fk_qi_quote` FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_quotation_status_history = "
    CREATE TABLE IF NOT EXISTS `quotation_status_history` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `quotation_id` INT UNSIGNED NOT NULL,
        `from_status` VARCHAR(32) DEFAULT NULL,
        `to_status` VARCHAR(32) NOT NULL,
        `changed_by` INT(11) DEFAULT NULL,
        `note` VARCHAR(500) DEFAULT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_qsh_quote` (`quotation_id`),
        CONSTRAINT `fk_qsh_quote` FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_qsh_user` FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_server_stats_snapshots = "
    CREATE TABLE IF NOT EXISTS `server_stats_snapshots` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `collected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `cpu_percent` DECIMAL(5,2) DEFAULT NULL,
        `memory_percent` DECIMAL(5,2) DEFAULT NULL,
        `disk_percent` DECIMAL(5,2) DEFAULT NULL,
        `load_avg_1` DECIMAL(8,3) DEFAULT NULL,
        `network_in_mbps` DECIMAL(12,4) DEFAULT NULL,
        `network_out_mbps` DECIMAL(12,4) DEFAULT NULL,
        `extra_json` LONGTEXT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_sss_collected` (`collected_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_user_chat_notification_prefs = "
    CREATE TABLE IF NOT EXISTS `user_chat_notification_prefs` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL,
        `channel` ENUM('one_to_one','group','task','discussion') NOT NULL,
        `email_enabled` TINYINT(1) DEFAULT NULL COMMENT 'NULL = inherit settings',
        `in_app_enabled` TINYINT(1) DEFAULT NULL,
        `push_enabled` TINYINT(1) DEFAULT NULL,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_ucnp_user_channel` (`user_id`, `channel`),
        CONSTRAINT `fk_ucnp_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_push_subscriptions = "
    CREATE TABLE IF NOT EXISTS `push_subscriptions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `endpoint` text NOT NULL,
        `p256dh` varchar(255) NOT NULL,
        `auth` varchar(255) NOT NULL,
        `browser` varchar(64) DEFAULT NULL,
        `device` varchar(64) DEFAULT NULL,
        `platform` varchar(64) DEFAULT NULL,
        `user_agent` varchar(512) DEFAULT NULL,
        `content_encoding` varchar(32) DEFAULT 'aesgcm',
        `is_active` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `last_used_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        KEY `idx_push_subs_active` (`user_id`, `is_active`),
        UNIQUE KEY `uq_push_endpoint` (`endpoint`(255)),
        CONSTRAINT `push_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_chat_push_throttle = "
    CREATE TABLE IF NOT EXISTS `chat_push_throttle` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL COMMENT 'recipient',
        `chat_type` enum('one_to_one','group','discussion','task') NOT NULL,
        `context_id` int(11) NOT NULL COMMENT 'peer_id, group_id, project_id, or task_id',
        `last_push_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_chat_push_throttle` (`user_id`, `chat_type`, `context_id`),
        KEY `idx_chat_push_throttle_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    $table_push_notification_payload_cache = "
    CREATE TABLE IF NOT EXISTS `push_notification_payload_cache` (
        `user_id` int(11) NOT NULL,
        `tag` varchar(128) NOT NULL,
        `title` varchar(255) NOT NULL,
        `body` text NOT NULL,
        `icon` text NULL,
        `payload_json` text NOT NULL,
        `updated_at` datetime NOT NULL,
        PRIMARY KEY (`user_id`, `tag`),
        KEY `idx_push_payload_cache_user_time` (`user_id`, `updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
    ";

    // Version 3.24 â€” chat email policy + user preference (same DDL as includes/migrations/3.24.sql)
    $table_chat_email_context = "
    CREATE TABLE IF NOT EXISTS `chat_email_context` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_chat_email_user_preference = "
    CREATE TABLE IF NOT EXISTS `chat_email_user_preference` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `user_id` int(10) unsigned NOT NULL,
        `context_type` enum('project','group','task') NOT NULL,
        `context_id` int(10) unsigned NOT NULL,
        `email_enabled` tinyint(1) NOT NULL DEFAULT 1,
        `updated_at` int(10) unsigned NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_user_ctx` (`user_id`,`context_type`,`context_id`),
        KEY `idx_ctx` (`context_type`,`context_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Email Marketing module (includes/migrations/marketing/5.0.sql + 4.7 bounce fields baked in for fresh install)
    $table_marketing_settings = "
    CREATE TABLE IF NOT EXISTS `marketing_settings` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(120) NOT NULL,
        `setting_value` longtext,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_marketing_setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_marketing_recipients = "
    CREATE TABLE IF NOT EXISTS `marketing_recipients` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `first_name` varchar(128) NOT NULL DEFAULT '',
        `last_name` varchar(128) NOT NULL DEFAULT '',
        `email` varchar(255) NOT NULL,
        `company` varchar(255) NOT NULL DEFAULT '',
        `phone` varchar(64) NOT NULL DEFAULT '',
        `address` text,
        `birthday` date DEFAULT NULL,
        `sms_phone` varchar(64) NOT NULL DEFAULT '',
        `user_id` int unsigned DEFAULT NULL,
        `source` enum('manual','import','crm') NOT NULL DEFAULT 'manual',
        `status` enum('subscribed','unsubscribed','bounced') NOT NULL DEFAULT 'subscribed',
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_marketing_recipient_email` (`email`(191)),
        UNIQUE KEY `uk_marketing_recipient_user_id` (`user_id`),
        KEY `idx_marketing_recipient_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_marketing_templates = "
    CREATE TABLE IF NOT EXISTS `marketing_templates` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(191) NOT NULL DEFAULT '',
        `subject` varchar(512) NOT NULL DEFAULT '',
        `body_html` longtext,
        `builder_json` longtext,
        `builder_version` varchar(16) NOT NULL DEFAULT 'comon-1',
        `created_by` int unsigned DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_marketing_template_created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_marketing_audiences = "
    CREATE TABLE IF NOT EXISTS `marketing_audiences` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(191) NOT NULL DEFAULT '',
        `description` varchar(512) NOT NULL DEFAULT '',
        `created_by` int unsigned DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_marketing_audience_name` (`name`),
        KEY `idx_marketing_audience_created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_marketing_campaigns = "
    CREATE TABLE IF NOT EXISTS `marketing_campaigns` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(191) NOT NULL DEFAULT '',
        `subject` varchar(512) NOT NULL DEFAULT '',
        `preview_text` varchar(255) NOT NULL DEFAULT '',
        `body_html` longtext,
        `status` enum('draft','scheduled','queued','sending','completed','failed','paused') NOT NULL DEFAULT 'draft',
        `sender_mode` enum('system','smtp','account') NOT NULL DEFAULT 'smtp',
        `email_account_id` int unsigned DEFAULT NULL,
        `sender_name` varchar(255) NOT NULL DEFAULT '',
        `reply_to` varchar(255) NOT NULL DEFAULT '',
        `recipient_mode` enum('all','selected','audience') NOT NULL DEFAULT 'all',
        `recipient_ids` longtext COMMENT 'JSON array of recipient IDs',
        `audience_ids` longtext COMMENT 'JSON array of audience IDs',
        `template_id` int unsigned DEFAULT NULL,
        `track_opens` tinyint(1) DEFAULT NULL,
        `track_clicks` tinyint(1) DEFAULT NULL,
        `scheduled_at` datetime DEFAULT NULL,
        `total_recipients` int unsigned NOT NULL DEFAULT 0,
        `sent_count` int unsigned NOT NULL DEFAULT 0,
        `failed_count` int unsigned NOT NULL DEFAULT 0,
        `pending_count` int unsigned NOT NULL DEFAULT 0,
        `opened_count` int unsigned NOT NULL DEFAULT 0,
        `clicked_count` int unsigned NOT NULL DEFAULT 0,
        `bounced_count` int unsigned NOT NULL DEFAULT 0,
        `unsubscribe_redirect_url` varchar(512) NOT NULL DEFAULT '',
        `created_by` int unsigned DEFAULT NULL,
        `queued_at` datetime DEFAULT NULL,
        `started_at` datetime DEFAULT NULL,
        `completed_at` datetime DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_marketing_campaign_status` (`status`),
        KEY `idx_marketing_campaign_scheduled_at` (`status`, `scheduled_at`),
        KEY `idx_marketing_campaign_created_by` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Fresh install: bounce delivery status + provider webhook columns (version 4.7) included in CREATE — no ALTER
    $table_marketing_campaign_recipients = "
    CREATE TABLE IF NOT EXISTS `marketing_campaign_recipients` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `campaign_id` int unsigned NOT NULL,
        `recipient_id` int unsigned NOT NULL,
        `status` enum('pending','sent','failed','skipped','bounced') NOT NULL DEFAULT 'pending',
        `error_message` text,
        `tracking_token` varchar(64) DEFAULT NULL,
        `sent_at` datetime DEFAULT NULL,
        `opened_at` datetime DEFAULT NULL,
        `clicked_at` datetime DEFAULT NULL,
        `bounced_at` datetime DEFAULT NULL,
        `bounce_type` varchar(16) DEFAULT NULL,
        `provider` varchar(32) DEFAULT NULL,
        `provider_event_id` varchar(128) DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_marketing_campaign_recipient` (`campaign_id`,`recipient_id`),
        KEY `idx_marketing_cr_status` (`campaign_id`,`status`),
        KEY `idx_marketing_cr_token` (`tracking_token`),
        KEY `idx_marketing_cr_bounced` (`status`, `bounced_at`),
        KEY `idx_marketing_cr_provider_event` (`provider`, `provider_event_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_marketing_email_logs = "
    CREATE TABLE IF NOT EXISTS `marketing_email_logs` (
        `id` bigint unsigned NOT NULL AUTO_INCREMENT,
        `campaign_id` int unsigned NOT NULL,
        `recipient_id` int unsigned DEFAULT NULL,
        `event_type` varchar(32) NOT NULL DEFAULT 'info',
        `message` text,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_marketing_log_campaign` (`campaign_id`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_marketing_audience_members = "
    CREATE TABLE IF NOT EXISTS `marketing_audience_members` (
        `audience_id` int unsigned NOT NULL,
        `recipient_id` int unsigned NOT NULL,
        `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`audience_id`, `recipient_id`),
        KEY `idx_marketing_audience_member_recipient` (`recipient_id`),
        CONSTRAINT `fk_marketing_audience_member_audience` FOREIGN KEY (`audience_id`) REFERENCES `marketing_audiences` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_marketing_audience_member_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `marketing_recipients` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_marketing_contact_tags = "
    CREATE TABLE IF NOT EXISTS `marketing_contact_tags` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `name` varchar(120) NOT NULL DEFAULT '',
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_marketing_contact_tag_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_marketing_contact_tag_members = "
    CREATE TABLE IF NOT EXISTS `marketing_contact_tag_members` (
        `tag_id` int unsigned NOT NULL,
        `recipient_id` int unsigned NOT NULL,
        `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`tag_id`, `recipient_id`),
        KEY `idx_marketing_contact_tag_member_recipient` (`recipient_id`),
        CONSTRAINT `fk_marketing_contact_tag_member_tag` FOREIGN KEY (`tag_id`) REFERENCES `marketing_contact_tags` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_marketing_contact_tag_member_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `marketing_recipients` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    $table_marketing_contact_notes = "
    CREATE TABLE IF NOT EXISTS `marketing_contact_notes` (
        `id` int unsigned NOT NULL AUTO_INCREMENT,
        `recipient_id` int unsigned NOT NULL,
        `content` text NOT NULL,
        `created_by` int unsigned DEFAULT NULL,
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_marketing_contact_note_recipient` (`recipient_id`),
        CONSTRAINT `fk_marketing_contact_note_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `marketing_recipients` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Add created_by column to milestones table
    $alter_milestones_created_by = "
    ALTER TABLE milestones ADD COLUMN created_by INT NULL AFTER currency;
    ";

    // Add index for created_by column
    $index_milestones_created_by = "
    CREATE INDEX idx_milestones_created_by ON milestones (created_by);
    ";

    // Add parent_task_id foreign key constraint to tasks table
    $alter_tasks_parent_key = "
    ALTER TABLE tasks 
    ADD CONSTRAINT fk_task_parent 
    FOREIGN KEY (parent_task_id) 
    REFERENCES tasks(id) 
    ON DELETE CASCADE;
    ";

    // Update existing milestones to set created_by to admin (user_id = 1) where NULL
    $update_milestones_created_by = "
    UPDATE milestones SET created_by = 1 WHERE created_by IS NULL;
    ";

    // Add completed_at column to tasks table
    $alter_tasks_completed_at = "
    ALTER TABLE tasks ADD COLUMN completed_at DATETIME NULL AFTER created_at;
    ";

    $tables = [
        $table1,
        $table2,
        $table2b,
        $table3,
        $table_roles,
        $table_role_permissions,
        $table4,
        $table5,
        $table_project_notes,
        $table_project_tab_notes,
        $table_project_tab_note_shares,
        $table6,
        $table_invoice_items,
        $table7,
        $table_task_recurrences,
        $table_task_recurrence_occurrences,
        $table_task_time_entries,
        $table_task_timer_sessions,
        $table_task_time_reports_daily,
        $table_project_activities,
        $table_integration_zapier,
        $table_integration_zapier_logs,
        $table_integration_google_meet,
        $table_integration_chatgpt,
        $table_integration_chatgpt_logs,
        $table_integration_webhook_deliveries,
        $table_quotations,
        $table_quotation_items,
        $table_quotation_status_history,
        $table_server_stats_snapshots,
        $table_user_chat_notification_prefs,
        $table_push_subscriptions,
        $table_chat_push_throttle,
        $table_push_notification_payload_cache,
        $table_chat_email_context,
        $table_chat_email_user_preference,
        $table8,
        $table9,
        $table10,
        $table_custom_fonts,
        $taskTrigger,
        $table11,
        $table11_private_note_shares,
        $table11_private_note_media,
        $table_staff_team_groups,
        $table_staff_team_group_members,
        $table_client_companies,
        $table_client_company_members,
        $table_client_company_links,
        $table_company_activity_log,
        $table_client_company_pins,
        $table_extra_tasks_columns,
        $table_notifications,
        $table_discussion_reads,
        $table_password_reset_tokens,
        $table_login_attempts,
        $table_file_folders,
        $table_files,
        $table_file_permissions,
        $table_folder_permissions,
        $table_project_folder_permission_overrides,
        $table_file_activity_log,
        // Calendar Integration Tables
        $table_calendar_events,
        $table_calendar_task_schedule,
        $table_google_calendar_settings,
        $table_google_calendar_tokens,
        $table_google_calendar_sync_log,
        // Attendance module (includes/migrations/3.19.sql)
        $table_attendance_shifts,
        $table_attendance_shift_assignments,
        $table_attendance_records,
        $table_attendance_punches,
        $table_attendance_leave_types,
        $table_attendance_leave_balances,
        $table_attendance_leave_requests,
        $table_attendance_holidays,
        $table_attendance_weekly_off_rules,
        $table_attendance_regularization_requests,
        $table_attendance_policies,
        $table_attendance_payroll_exports,
        $table_attendance_audit_logs,
        $table_attendance_overtime,
        $table_attendance_user_day_overrides,
        // Version 3.3 Updates
        $table_security_logs,
        $table_profile_notes,
        $table_profile_note_shares,
        // Task Chat Tables
        $table_task_chat,
        $table_task_chat_reads,
        $table_chat_message_reactions,
        $table_chat_message_stars,
        $table_chat_message_pins,
        $table_task_files,
        // Sub-tasks Table
        $table_subtasks,
        $table_task_activities,
        // Invoice Reminders Table
        $table_invoice_reminders_sent,
        $table_client_stripe_customers,
        $table_client_payment_methods,
        // Custom Fields Tables
        $table_custom_fields,
        $table_lead_custom_field_values,
        $table_user_custom_field_values,
        $table_project_custom_field_values,
        $table_task_custom_field_values,
        $table_company_custom_field_values,
        // Tags Tables
        $table_tag_categories,
        $table_tags,
        $table_taggables,
        // Leads Tables
        $table_lead_statuses,
        $table_lead_sources,
        $table_leads,
        $table_lead_activities,
        $table_lead_activity_meta,
        $table_lead_comments,
        $table_lead_comment_mentions,
        $table_import_jobs,
        $table_import_job_errors
    ];
    $tables = array_merge($tables, installer_get_ecommerce_create_table_queries());
    $tables = array_merge($tables, installer_get_46_performance_create_table_queries());
    $tables = array_merge($tables, installer_get_410_create_table_queries());

    $errors = [];
    $migrationNotes = [];
    if (function_exists('installer_free_filter_sql_list')) {
        $tables = installer_free_filter_sql_list($tables);
        $migrationNotes[] = 'Free edition: omitted Pro-only CREATE TABLE statements';
    }

    foreach ($tables as $sql) {
        try {
            $res = mysqli_query($connect, $sql);
            if (!$res) {
                $errors[] = mysqli_error($connect);
            } else {
                $errors[] = 0;
            }
        } catch (mysqli_sql_exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    // Version 4.10: module_ai column, ALTERs, cron seeds (CREATE already in $tables)
    installer_run_410_migrations($connect, $migrationNotes);
    installer_run_totp_auth_migrations($connect, $errors, $migrationNotes);

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 5) Insert default theme_settings row
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $insertThemeDefaults = "INSERT INTO `theme_settings`
    (
        sidebar_bg_color, sidebar_link_color, sidebar_active_bg_color, sidebar_active_color,
        header_bg_color, header_link_color, main_content_bg_color,
        primary_color, secondary_color, body_bg_color, body_font_color, title_color, border_color, card_body_color,
        box_shadow, card_border_radius, title_font_weight,
        primary_header_color, secondary_header_color, primary_header_font_color, secondary_header_font_color,
        primary_button_color, secondary_button_color, border_button_color,
        primary_button_font_color, secondary_button_font_color, border_button_font_color, button_border_radius,
        chat_card_color, chat_card_secondary_color, chat_title_color, chat_body_color,
        chat_buttons_color, chat_buttons_text_color, chat_time_text_color, chat_font_size, chat_font_weight
    )
    VALUES
    (
        '#0f1d40', '#e0e6eb', '#2d4071', '#ffffff',
        '#222d32', '#ffffff', '#ffffff',
        '#0094ff', '#10b981', '#f5f8fa', '#212529', '#000000', '#e1e1e1', '#ffffff',
        '0px 3px 13px -5px rgb(0 0 0 / 0.1)', '12', '600',
        '#ffffff', '#ffffff', '#212529', '#212529',
        '#0094ff', '#f1f6fa', '#dee2e6',
        '#ffffff', '#212529', '#212529', '4',
        '#ffffff', '#e6f0f9', '#212529', '#f5f8fa',
        '#0094ff', '#ffffff', '#757575', '14px', '400'
    );";
    mysqli_query($connect, $insertThemeDefaults);

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 6) Insert default project_columns
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $defaultColumns = [
        ['project_id' => 0, 'column_key' => 'todo',       'custom_name' => 'To Do'],
        ['project_id' => 0, 'column_key' => 'inprogress', 'custom_name' => 'In Progress'],
        ['project_id' => 0, 'column_key' => 'review',     'custom_name' => 'Review'],
        ['project_id' => 0, 'column_key' => 'done',       'custom_name' => 'Done']
    ];

    foreach ($defaultColumns as $column) {
        $insertColumn = "
            INSERT IGNORE INTO project_columns (project_id, column_key, custom_name)
            VALUES (
                {$column['project_id']},
                '{$column['column_key']}',
                '{$column['custom_name']}'
            );
        ";
        mysqli_query($connect, $insertColumn);
    }

    // Free edition: skip license system + Google Drive seed rows.

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 6.3) Insert sample security log entries for version 3.3
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $insertSecurityLogs = "
        INSERT IGNORE INTO security_logs (user_id, event, details, ip_address, user_agent) 
        VALUES 
        (1, 'system.install', 'System installation completed successfully', '127.0.0.1', 'TaskSession Installer'),
        (1, 'user.create', 'Default admin user created during installation', '127.0.0.1', 'TaskSession Installer'),
        (1, 'database.setup', 'Database tables created and configured', '127.0.0.1', 'TaskSession Installer');
    ";
    mysqli_query($connect, $insertSecurityLogs);

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 6.4) Fresh install supplemental tables (CREATE only â€” upgrades use system.php)
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    installer_run_fresh_install_create_tables_only($connect, $errors, $migrationNotes);

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 6.5) Seed default lead statuses and sources
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $seed_lead_statuses = "
    INSERT INTO lead_statuses (name, color_class, sort_order, is_default)
    SELECT * FROM (
      SELECT 'New' AS name, 'color-todo-bg' AS color_class, 1 AS sort_order, 1 AS is_default
      UNION ALL SELECT 'Contacted', 'color-inprogress-bg', 2, 0
      UNION ALL SELECT 'Qualified', 'color-review-bg', 3, 0
      UNION ALL SELECT 'Won', 'color-done-bg', 4, 0
    ) AS tmp
    WHERE NOT EXISTS (SELECT 1 FROM lead_statuses LIMIT 1);
    ";
    installer_run_query($connect, $seed_lead_statuses, $errors, $migrationNotes, 'seed-lead-statuses');

    $seed_lead_sources = "
    INSERT INTO lead_sources (name, sort_order, is_default)
    SELECT * FROM (
      SELECT 'Website' AS name, 1 AS sort_order, 1 AS is_default
      UNION ALL SELECT 'Referral', 2, 0
      UNION ALL SELECT 'Ads', 3, 0
    ) AS tmp
    WHERE NOT EXISTS (SELECT 1 FROM lead_sources LIMIT 1);
    ";
    installer_run_query($connect, $seed_lead_sources, $errors, $migrationNotes, 'seed-lead-sources');

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 6.6) Seed default tag categories and tags
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $seed_tag_categories = "
    INSERT INTO tag_categories (name, sort_order)
    SELECT * FROM (SELECT 'Priority' AS name, 1 AS sort_order) AS tmp
    WHERE NOT EXISTS (SELECT 1 FROM tag_categories LIMIT 1);
    ";
    installer_run_query($connect, $seed_tag_categories, $errors, $migrationNotes, 'seed-tag-categories');

    $seed_tags = "
    INSERT INTO tags (category_id, name, color_class)
    SELECT c.id, t.name, t.color_class
    FROM tag_categories c
    JOIN (
      SELECT 'High' AS name, 'badge tags-bg' AS color_class
      UNION ALL SELECT 'Low', 'color-todo-bg'
    ) t
    WHERE c.name='Priority'
      AND NOT EXISTS (SELECT 1 FROM tags LIMIT 1);
    ";
    installer_run_query($connect, $seed_tags, $errors, $migrationNotes, 'seed-tags');

    // 6.7) Seed Email Marketing defaults (5.0 + 4.7 webhook_secret for fresh install)
    $seed_marketing_settings = "
    INSERT INTO `marketing_settings` (`setting_key`, `setting_value`) VALUES
        ('default_sender_name', ''),
        ('default_reply_to', ''),
        ('sender_mode', 'smtp'),
        ('email_account_id', '0'),
        ('smtp_host', ''),
        ('smtp_port', '587'),
        ('smtp_username', ''),
        ('smtp_password', ''),
        ('smtp_secure', 'tls'),
        ('smtp_from_email', ''),
        ('smtp_from_name', ''),
        ('batch_size', '25'),
        ('batch_delay_seconds', '1'),
        ('track_opens', '1'),
        ('track_clicks', '0'),
        ('tracking_base_url', ''),
        ('webhook_secret', '')
    ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
    ";
    installer_run_query($connect, $seed_marketing_settings, $errors, $migrationNotes, 'seed-marketing-settings');

    // Default Administrator role — required before role_permissions (FK to roles.id)
    $seed_default_admin_role = "
    INSERT INTO `roles` (`id`, `name`, `description`)
    SELECT 1, 'Administrator', 'Full system access for administrators.'
    FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM roles WHERE id = 1 LIMIT 1);
    ";
    installer_run_query($connect, $seed_default_admin_role, $errors, $migrationNotes, 'seed-default-admin-role');

    $marketingAdminPermissions = [
        'marketing_access',
        'marketing_dashboard_view',
        'marketing_campaigns_view',
        'marketing_campaigns_edit',
        'marketing_campaigns_send',
        'marketing_templates_manage',
        'marketing_recipients_manage',
        'marketing_settings_manage',
    ];
    foreach ($marketingAdminPermissions as $marketingPermissionKey) {
        $seedMarketingPerm = "
        INSERT INTO `role_permissions` (`role_id`, `permission_key`, `value`)
        SELECT r.id, '" . mysqli_real_escape_string($connect, $marketingPermissionKey) . "', 1
        FROM roles r
        WHERE r.id = 1
          AND NOT EXISTS (
            SELECT 1 FROM role_permissions
            WHERE role_id = r.id AND permission_key = '" . mysqli_real_escape_string($connect, $marketingPermissionKey) . "'
            LIMIT 1
        );
        ";
        installer_run_query($connect, $seedMarketingPerm, $errors, $migrationNotes, 'seed-marketing-perm-' . $marketingPermissionKey);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // 7) Check for errors
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    $failedCount = 0;
    foreach ($errors as $e) {
        if ($e !== 0) {
            $failedCount++;
        }
    }

    if ($failedCount === 0) {
        $_SESSION['install_fresh_schema_from_index'] = true;
        $_SESSION['install_system_migrations_done'] = true;
        install_safe_redirect('system.php');
    } else {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8"/>
            <title>Task Session Installation - Error</title>
            <link rel="icon" type="image/png" href="../assets/images/favicon.png"/>
            <link href="../assets/css/bootstrap.css" rel="stylesheet" type="text/css"/>
            <link href="../assets/css/style.min.css" rel="stylesheet" type="text/css"/>
            <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@400;600&display=swap" rel="stylesheet"/>
            <link href="style.css" rel="stylesheet" type="text/css"/>
        </head>
        <body>
            <div class="install-logo">
                <img src="../assets/images/svg/dark-logo.svg" alt="System Logo"/>
            </div>
            <div class="dbinstall center-col">
                <div class="database">
                    <div class="login-col">
                        <h2>Database Setup Error</h2>
                        <div class="alert alert-danger">
                            <strong>Error:</strong> One or more database tables failed to create.
                        </div>
                        <div class="error-details">
                            <h5>Error Details:</h5>
                            <ul>
                                <?php foreach ($errors as $e): ?>
                                    <?php if ($e !== 0): ?>
                                        <li><?php echo htmlspecialchars($e); ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="nav-buttons">
                            <a href="?step=2" class="btn-secondary">â† Back to Database Setup</a>
                        </div>
                    </div>
                    <div class="clearfix"></div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

}

show_error_form:
if (isset($error_message)) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8"/>
        <title>Task Session Installation - Error</title>
        <link rel="icon" type="image/png" href="../assets/images/favicon.png"/>
        <link href="../assets/css/bootstrap.css" rel="stylesheet" type="text/css"/>
        <link href="../assets/css/style.min.css" rel="stylesheet" type="text/css"/>
        <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@400;600&display=swap" rel="stylesheet"/>
        <link href="style.css" rel="stylesheet" type="text/css"/>
    </head>
    <body>
        <div class="install-logo">
            <img src="../assets/images/svg/dark-logo.svg" alt="System Logo"/>
        </div>
        <div class="dbinstall center-col">
            <div class="database">
                <div class="login-col">
                    <h2>Database Connection Error</h2>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <?php if (strpos($error_message, 'Access denied') !== false): ?>
                    <div class="alert alert-warning">
                        <h5>Common Solutions for XAMPP:</h5>
                        <ul>
                            <li>Try username: <strong>root</strong> with no password</li>
                            <li>Make sure MySQL service is running in XAMPP Control Panel</li>
                            <li>Check if the user exists in phpMyAdmin</li>
                            <li>For XAMPP, the default root user usually has no password</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <form method="post" action="" class="install-db-form" onsubmit="return installerLockSubmit(this);">
                        <input type="hidden" name="submit" value="1"/>
                        <input required type="text" placeholder="Host Name" name="dbhost" value="<?php echo htmlspecialchars($_POST['dbhost'] ?? ''); ?>"/>
                        <input required type="text" placeholder="Database User Name" name="uname" value="<?php echo htmlspecialchars($_POST['uname'] ?? ''); ?>"/>
                        <input required type="text" placeholder="Database Name" name="dbname" value="<?php echo htmlspecialchars($_POST['dbname'] ?? ''); ?>"/>
                        <p class="form-field">
                            If you are using any of the localhost environments below (XAMPP, WampServer,
                            EASYPHP, AMPPS), leave the password field empty.
                        </p>
                        <input type="text" placeholder="Database Password" name="dbpass" value="<?php echo htmlspecialchars($_POST['dbpass'] ?? ''); ?>"/>
                        <button type="submit" class="install-submit-btn">
                            <span class="install-submit-spinner" aria-hidden="true"></span>
                            <span class="install-submit-label">Try Again</span>
                        </button>
                    </form>
                    <div class="nav-buttons">
                        <a href="?step=1" class="btn-secondary">Back to Requirements</a>
                    </div>
                </div>
                <div class="clearfix"></div>
            </div>
        </div>
    </body>
    <script>
    function installerLockSubmit(form) {
        var btn = form.querySelector('.install-submit-btn');
        if (!btn || btn.classList.contains('is-loading')) {
            return false;
        }
        btn.classList.add('is-loading');
        btn.setAttribute('aria-busy', 'true');
        var label = btn.querySelector('.install-submit-label');
        if (label) {
            label.textContent = 'Installing...';
        }
        return true;
    }
    </script>
    </html>
    <?php
    exit;
}

// ───────────────────────────────────────────────
// Show the HTML form if not yet submitted
// ───────────────────────────────────────────────
$dbFormHost = htmlspecialchars($_POST['dbhost'] ?? 'localhost', ENT_QUOTES, 'UTF-8');
$dbFormUser = htmlspecialchars($_POST['uname'] ?? '', ENT_QUOTES, 'UTF-8');
$dbFormName = htmlspecialchars($_POST['dbname'] ?? '', ENT_QUOTES, 'UTF-8');
$dbFormPass = htmlspecialchars($_POST['dbpass'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"/>
    <title>Task Session Installation</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon.png"/>
    <link href="../assets/css/bootstrap.css" rel="stylesheet" type="text/css"/>
    <link href="../assets/css/style.min.css" rel="stylesheet" type="text/css"/>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@400;600&display=swap" rel="stylesheet"/>
    <link href="style.css" rel="stylesheet" type="text/css"/>
</head>
<body>
    <div class="install-logo">
        <img src="../assets/images/svg/dark-logo.svg" alt="System Logo"/>
    </div>
    <div class="dbinstall center-col">
        <div class="database">
            <div class="login-col">
                <h2>Database Connection Settings</h2>
                <div class="quick-tips">
                    <h5>Quick Tips:</h5>
                    <ul>
                        <li><strong>Host Name:</strong> Usually "localhost" for local installations</li>
                        <li><strong>Database User:</strong> Your MySQL username (often "root" for local)</li>
                        <li><strong>Database Name:</strong> Create a new database for this installation</li>
                        <li><strong>Password:</strong> Leave empty for XAMPP/WAMP local installations</li>
                    </ul>
                </div>
                <form method="post" action="" class="install-db-form" onsubmit="return installerLockSubmit(this);">
                    <input type="hidden" name="submit" value="1"/>
                    <input required type="text" placeholder="Host Name" name="dbhost" value="<?php echo $dbFormHost; ?>"/>
                    <input required type="text" placeholder="Database User Name" name="uname" value="<?php echo $dbFormUser; ?>"/>
                    <input required type="text" placeholder="Database Name" name="dbname" value="<?php echo $dbFormName; ?>"/>
                    <p class="form-field">
                        If you are using any of the localhost environments below (XAMPP, WampServer,
                        EASYPHP, AMPPS), leave the password field empty.
                    </p>
                    <input type="text" placeholder="Database Password" name="dbpass" value="<?php echo $dbFormPass; ?>"/>
                    <button type="submit" class="install-submit-btn">
                        <span class="install-submit-spinner" aria-hidden="true"></span>
                        <span class="install-submit-label">Next</span>
                    </button>
                </form>
            </div>
            <div class="clearfix"></div>
        </div>
    </div>
</body>
<script>
function installerLockSubmit(form) {
    var btn = form.querySelector('.install-submit-btn');
    if (!btn || btn.classList.contains('is-loading')) {
        return false;
    }
    btn.classList.add('is-loading');
    btn.setAttribute('aria-busy', 'true');
    var label = btn.querySelector('.install-submit-label');
    if (label) {
        label.textContent = 'Installing...';
    }
    return true;
}
</script>
</html>
<?php
?>
