<?php

/**
 * Build ZIP chat packages (template or live DB export) matching ChatImportManifest v1.
 */
class Comon_IE_ChatExportPackager
{
    private static function jsonLine(array $row): string
    {
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $s = json_encode($row, $flags);
        return $s !== false ? $s : '{}';
    }

    /**
     * @param 'template'|'live' $mode
     */
    public static function streamZipDownload(mysqli $connect, string $mode): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!class_exists('ZipArchive')) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'ZipArchive not available';
            return;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'comon_chat_exp_');
        if ($tmp === false) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Cannot create temp file';
            return;
        }
        @unlink($tmp);
        $zipPath = $tmp . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Cannot create ZIP';
            return;
        }

        $manifest = [
            'version' => Comon_IE_ChatImportManifest::VERSION,
            'created_at' => gmdate('c'),
            'generator' => 'Comon_IE_ChatExportPackager',
            'mode' => $mode,
        ];
        $mFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $mFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $zip->addFromString(Comon_IE_ChatImportManifest::FILE_MANIFEST, (string)json_encode($manifest, $mFlags));

        $readme = "Comon chat import package (v1)\n\n"
            . "See includes/import-export/ChatImportManifest.php for JSON schemas.\n"
            . "Optional files: messages_one_to_one.jsonl, messages_discussion.jsonl, groups.json,\n"
            . "group_messages.jsonl, task_messages.jsonl, attachments/\n"
            . "All chat attachment tokens reference uploads/user-uploads/FILENAME.\n";
        $zip->addFromString('README.txt', $readme);

        if ($mode === 'template') {
            $zip->addFromString(Comon_IE_ChatImportManifest::FILE_ONE_TO_ONE, '');
            $zip->addFromString(Comon_IE_ChatImportManifest::FILE_DISCUSSION, '');
            $zip->addFromString(Comon_IE_ChatImportManifest::FILE_GROUPS, "[]\n");
            $zip->addFromString(Comon_IE_ChatImportManifest::FILE_GROUP_MESSAGES, '');
            $zip->addFromString(Comon_IE_ChatImportManifest::FILE_TASK_MESSAGES, '');
        } else {
            self::appendLiveExports($connect, $zip);
        }

        if (!$zip->close()) {
            @unlink($zipPath);
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'ZIP finalize failed';
            return;
        }

        $size = @filesize($zipPath);
        if ($size === false || $size < 30) {
            @unlink($zipPath);
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/plain; charset=utf-8');
            echo 'ZIP file invalid or empty';
            return;
        }

        $fn = $mode === 'template' ? 'chat-import-template.zip' : 'chat-export-live.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $fn) . '"');
        header('Content-Length: ' . (string)$size);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        $fh = @fopen($zipPath, 'rb');
        if ($fh) {
            while (!feof($fh)) {
                echo fread($fh, 8192);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            fclose($fh);
        }
        @unlink($zipPath);
    }

    private static function appendLiveExports(mysqli $connect, ZipArchive $zip): void
    {
        $lim = 300;
        $dec = function_exists('decryptString');

        $lines121 = [];
        $q = @mysqli_query(
            $connect,
            'SELECT id, message, time, user_id, receiver, reply_to FROM messages WHERE Project_id = 0 AND receiver > 0 ORDER BY id DESC LIMIT ' . (int)$lim
        );
        if ($q) {
            while ($r = mysqli_fetch_assoc($q)) {
                $body = (string)($r['message'] ?? '');
                if ($dec) {
                    try {
                        $body = decryptString($body);
                    } catch (Throwable $e) {
                        $body = '[decrypt_error]';
                    }
                }
                $lines121[] = self::jsonLine([
                    'external_id' => 'm' . (int)$r['id'],
                    'sender_user_id' => (int)$r['user_id'],
                    'receiver_user_id' => (int)$r['receiver'],
                    'time' => (int)$r['time'],
                    'plaintext_body' => $body,
                    'reply_to_external_id' => !empty($r['reply_to']) ? 'm' . (int)$r['reply_to'] : null,
                    'edited' => 0,
                    'edit_time' => 0,
                ]);
            }
            mysqli_free_result($q);
        }
        $zip->addFromString(Comon_IE_ChatImportManifest::FILE_ONE_TO_ONE, implode("\n", array_reverse($lines121)) . ($lines121 ? "\n" : ''));

        $linesD = [];
        $q2 = @mysqli_query(
            $connect,
            'SELECT id, message, time, user_id, Project_id, reply_to FROM messages WHERE Project_id > 0 AND receiver = 0 ORDER BY id DESC LIMIT ' . (int)$lim
        );
        if ($q2) {
            while ($r = mysqli_fetch_assoc($q2)) {
                $body = (string)($r['message'] ?? '');
                if ($dec) {
                    try {
                        $body = decryptString($body);
                    } catch (Throwable $e) {
                        $body = '[decrypt_error]';
                    }
                }
                $linesD[] = self::jsonLine([
                    'external_id' => 'd' . (int)$r['id'],
                    'project_id' => (int)$r['Project_id'],
                    'sender_user_id' => (int)$r['user_id'],
                    'time' => (int)$r['time'],
                    'plaintext_body' => $body,
                    'reply_to_external_id' => !empty($r['reply_to']) ? 'd' . (int)$r['reply_to'] : null,
                    'edited' => 0,
                    'edit_time' => 0,
                ]);
            }
            mysqli_free_result($q2);
        }
        $zip->addFromString(Comon_IE_ChatImportManifest::FILE_DISCUSSION, implode("\n", array_reverse($linesD)) . ($linesD ? "\n" : ''));

        $groupsOut = [];
        $gq = @mysqli_query($connect, 'SELECT id, name, avatar, created_by, created_at FROM group_chats ORDER BY id DESC LIMIT 50');
        if ($gq) {
            while ($g = mysqli_fetch_assoc($gq)) {
                $gid = (int)$g['id'];
                $members = [];
                $mq = @mysqli_query($connect, 'SELECT user_id, role, joined_at FROM group_chat_members WHERE group_id = ' . $gid . ' AND left_at IS NULL');
                if ($mq) {
                    while ($m = mysqli_fetch_assoc($mq)) {
                        $members[] = [
                            'user_id' => (int)$m['user_id'],
                            'role' => (string)$m['role'],
                            'joined_at' => (int)$m['joined_at'],
                        ];
                    }
                    mysqli_free_result($mq);
                }
                $groupsOut[] = [
                    'external_id' => 'g' . $gid,
                    'name' => (string)($g['name'] ?? ''),
                    'created_by' => (int)($g['created_by'] ?? 0),
                    'created_at' => (int)($g['created_at'] ?? time()),
                    'avatar' => $g['avatar'] ?? null,
                    'members' => $members,
                ];
            }
            mysqli_free_result($gq);
        }
        $gFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $gFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $zip->addFromString(Comon_IE_ChatImportManifest::FILE_GROUPS, (string)json_encode($groupsOut, $gFlags));

        $linesG = [];
        $gq2 = @mysqli_query($connect, 'SELECT id, group_id, user_id, message, time, reply_to FROM group_chat_messages ORDER BY id DESC LIMIT ' . (int)$lim);
        if ($gq2) {
            while ($r = mysqli_fetch_assoc($gq2)) {
                $body = (string)($r['message'] ?? '');
                if ($dec) {
                    try {
                        $body = decryptString($body);
                    } catch (Throwable $e) {
                        $body = '[decrypt_error]';
                    }
                }
                $linesG[] = self::jsonLine([
                    'external_id' => 'gm' . (int)$r['id'],
                    'external_group_id' => 'g' . (int)$r['group_id'],
                    'sender_user_id' => (int)$r['user_id'],
                    'time' => (int)$r['time'],
                    'plaintext_body' => $body,
                    'reply_to_external_id' => !empty($r['reply_to']) ? 'gm' . (int)$r['reply_to'] : null,
                    'edited' => 0,
                    'edit_time' => 0,
                ]);
            }
            mysqli_free_result($gq2);
        }
        $zip->addFromString(Comon_IE_ChatImportManifest::FILE_GROUP_MESSAGES, implode("\n", array_reverse($linesG)) . ($linesG ? "\n" : ''));

        $linesT = [];
        $tq = @mysqli_query($connect, 'SELECT id, task_id, user_id, message, time, reply_to FROM task_chat ORDER BY id DESC LIMIT ' . (int)$lim);
        if ($tq) {
            while ($r = mysqli_fetch_assoc($tq)) {
                $body = (string)($r['message'] ?? '');
                if ($dec) {
                    try {
                        $body = decryptString($body);
                    } catch (Throwable $e) {
                        $body = '[decrypt_error]';
                    }
                }
                $linesT[] = self::jsonLine([
                    'external_id' => 't' . (int)$r['id'],
                    'task_id' => (int)$r['task_id'],
                    'sender_user_id' => (int)$r['user_id'],
                    'time' => (int)$r['time'],
                    'plaintext_body' => $body,
                    'reply_to_external_id' => !empty($r['reply_to']) ? 't' . (int)$r['reply_to'] : null,
                    'edited' => 0,
                    'edit_time' => 0,
                ]);
            }
            mysqli_free_result($tq);
        }
        $zip->addFromString(Comon_IE_ChatImportManifest::FILE_TASK_MESSAGES, implode("\n", array_reverse($linesT)) . ($linesT ? "\n" : ''));
    }
}
