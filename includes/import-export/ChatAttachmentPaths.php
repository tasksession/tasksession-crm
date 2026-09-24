<?php
/**
 * Chat attachment storage — audited from real-chat upload handlers.
 *
 * All four chat UIs resolve attachment tokens to files under the same tree:
 *   [photoAttachment-FILENAME] / [fileAttachment-FILENAME]
 *   → project root + uploads/user-uploads/FILENAME
 *
 * References:
 * - One-to-one: {@see real-chat/chatting_upload.php} — dirname(__DIR__) . '/uploads/user-uploads/'
 *   Filename: time() . substr(md5(uniqid()), 0, 8) . '.' . ext
 * - Project discussion: {@see real-chat/discussion_upload.php} — same directory
 *   Filename: time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', original_name)
 * - Task chat: {@see real-chat/task_chat_upload.php} — same directory, same pattern as discussion
 * - Group chat: no dedicated upload script; fetch handlers use uploads/user-uploads/ (see group_chat_fetch.php)
 */
class Comon_IE_ChatAttachmentPaths
{
    /** Relative to project root (same as secure_file_handler ?src=). */
    public const RELATIVE_USER_UPLOADS = 'uploads/user-uploads/';

    public static function absoluteUserUploadsDir(string $projectRoot): string
    {
        return rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, self::RELATIVE_USER_UPLOADS);
    }

    /**
     * Copy a file from the import ZIP attachments/ folder into user-uploads.
     *
     * @return bool true if copied or already exists with same size
     */
    public static function importCopyBasename(string $projectRoot, string $attachmentsDir, string $basename): bool
    {
        $basename = basename($basename);
        if ($basename === '' || strpos($basename, '..') !== false) {
            return false;
        }
        $src = rtrim($attachmentsDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $basename;
        if (!is_file($src)) {
            return false;
        }
        $destDir = self::absoluteUserUploadsDir($projectRoot);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0755, true);
        }
        $dest = $destDir . $basename;
        if (is_file($dest)) {
            return (int)@filesize($src) === (int)@filesize($dest);
        }
        return @copy($src, $dest);
    }
}
