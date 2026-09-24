<?php
/**
 * Deferred outbound mail for hot paths (e.g. kanban drag) — processed by cron.php.
 */

if (!function_exists('crm_mail_queue_ensure_table')) {
    function crm_mail_queue_ensure_table($database): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (!$database || !isset($database->connection)) {
            return;
        }
        $sql = "CREATE TABLE IF NOT EXISTS `crm_mail_queue` (
          `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
          `recipient_email` varchar(255) NOT NULL,
          `subject` varchar(500) NOT NULL,
          `body` mediumtext NOT NULL,
          `headers` text DEFAULT NULL,
          `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
          `attempts` tinyint UNSIGNED NOT NULL DEFAULT 0,
          `last_error` varchar(500) DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `sent_at` datetime DEFAULT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_crm_mail_queue_status_created` (`status`,`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        @$database->query($sql);
    }
}

if (!function_exists('crm_task_update_email_on_drag_enabled')) {
    function crm_task_update_email_on_drag_enabled($settings = null): bool
    {
        if ($settings === null) {
            global $dash_settings;
            $settings = isset($dash_settings) && $dash_settings ? $dash_settings : null;
        }
        if (!$settings) {
            return true;
        }
        if (!property_exists($settings, 'task_update_email_on_drag')) {
            return true;
        }
        return (int) $settings->task_update_email_on_drag !== 0;
    }
}

if (!function_exists('crm_mail_queue_enqueue')) {
    function crm_mail_queue_enqueue($database, string $to, string $subject, string $body, string $headers = ''): bool
    {
        crm_mail_queue_ensure_table($database);
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $escTo = $database->escapeValue($to);
        $escSub = $database->escapeValue($subject);
        $escBody = $database->escapeValue($body);
        $escHeaders = $database->escapeValue($headers);
        $database->query(
            "INSERT INTO crm_mail_queue (recipient_email, subject, body, headers, status, attempts)
             VALUES ('{$escTo}', '{$escSub}', '{$escBody}', '{$escHeaders}', 'pending', 0)"
        );
        return true;
    }
}

if (!function_exists('crm_process_mail_queue')) {
    /**
     * @return array{processed:int,sent:int,failed:int}
     */
    function crm_process_mail_queue($database, int $batchLimit = 8): array
    {
        $out = ['processed' => 0, 'sent' => 0, 'failed' => 0];
        crm_mail_queue_ensure_table($database);
        $batchLimit = max(1, min(50, $batchLimit));
        $res = $database->query(
            "SELECT id, recipient_email, subject, body, headers, attempts
             FROM crm_mail_queue
             WHERE status = 'pending' AND attempts < 5
             ORDER BY created_at ASC
             LIMIT " . (int) $batchLimit
        );
        if (!$res || $database->numRows($res) === 0) {
            return $out;
        }
        require_once __DIR__ . '/email_helper.php';
        $mailer = new EmailHelper();
        while ($row = $database->fetchArray($res)) {
            $out['processed']++;
            $id = (int) $row['id'];
            $to = (string) $row['recipient_email'];
            $subject = (string) $row['subject'];
            $body = (string) $row['body'];
            $headers = (string) ($row['headers'] ?? '');
            $attempts = (int) ($row['attempts'] ?? 0) + 1;
            $ok = false;
            try {
                $ok = (bool) $mailer->sendEmail($to, $subject, $body, $headers);
            } catch (Throwable $e) {
                $ok = false;
                $err = $e->getMessage();
            }
            if ($ok) {
                $out['sent']++;
                $database->query(
                    "UPDATE crm_mail_queue SET status = 'sent', attempts = {$attempts}, sent_at = NOW(), last_error = NULL WHERE id = {$id}"
                );
            } else {
                $out['failed']++;
                $errMsg = isset($err) ? $err : 'send failed';
                $escErr = $database->escapeValue(substr($errMsg, 0, 480));
                $status = $attempts >= 5 ? 'failed' : 'pending';
                $database->query(
                    "UPDATE crm_mail_queue SET status = '{$status}', attempts = {$attempts}, last_error = '{$escErr}' WHERE id = {$id}"
                );
            }
        }
        return $out;
    }
}
