<?php
/**
 * End-to-end cleanup when a task is deleted: chat, chat files, card attachments.
 */

$__chatCleanup = __DIR__ . '/chat_attachment_cleanup.php';
if (is_file($__chatCleanup)) {
    require_once $__chatCleanup;
}


if (!function_exists('task_delete_purge_related_data')) {
    /**
     * Remove task chat (and attachment files), card files, and related rows before task row delete.
     */
    function task_delete_purge_related_data($taskId)
    {
        global $database;

        $taskId = (int) $taskId;
        if ($taskId <= 0 || !isset($database)) {
            return false;
        }

        task_delete_purge_chat($taskId, $database);
        task_delete_purge_card_files($taskId, $database);

        return true;
    }
}

if (!function_exists('task_delete_purge_chat')) {
    function task_delete_purge_chat($taskId, $database)
    {
        $taskId = (int) $taskId;
        $messageIds = array();

        $result = $database->query("SELECT id, message FROM task_chat WHERE task_id = {$taskId}");
        if ($result) {
            while ($row = $database->fetchArray($result)) {
                if (!empty($row['id'])) {
                    $messageIds[] = (int) $row['id'];
                }
                $plain = chat_attachment_decrypt_message($row['message'] ?? '');
                if ($plain !== '') {
                    chat_attachment_unlink_from_plaintext($plain);
                }
            }
        }

        if (!empty($messageIds)) {
            $idList = implode(',', $messageIds);
            $database->querySoft("DELETE FROM chat_message_reactions WHERE chat_type = 'task' AND message_id IN ({$idList})");
            $database->querySoft("DELETE FROM chat_message_stars WHERE chat_type = 'task' AND message_id IN ({$idList})");
            $database->query("DELETE FROM task_chat_reads WHERE message_id IN ({$idList})");
        }

        $database->querySoft("DELETE FROM chat_message_pins WHERE chat_type = 'task' AND context_id = {$taskId}");
        $database->querySoft("DELETE FROM task_chat_email_notifications WHERE task_id = {$taskId}");
        $database->query("DELETE FROM task_chat WHERE task_id = {$taskId}");
    }
}

if (!function_exists('task_delete_purge_card_files')) {
    function task_delete_purge_card_files($taskId, $database)
    {
        $taskId = (int) $taskId;
        $fallbackDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'task-files' . DIRECTORY_SEPARATOR;

        $result = $database->query("SELECT id, filename, file_path FROM task_files WHERE task_id = {$taskId}");
        if (!$result) {
            return;
        }

        while ($row = $database->fetchArray($result)) {
            $paths = array();
            if (!empty($row['file_path'])) {
                $paths[] = (string) $row['file_path'];
            }
            if (!empty($row['filename'])) {
                $paths[] = $fallbackDir . basename((string) $row['filename']);
            }

            foreach (array_unique($paths) as $path) {
                if ($path !== '' && is_file($path)) {
                    @unlink($path);
                }
            }
        }

        $database->query("DELETE FROM task_files WHERE task_id = {$taskId}");
    }
}
