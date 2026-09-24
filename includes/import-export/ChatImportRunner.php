<?php
/**
 * Chat package import (ZIP + manifest + JSONL) — four backends.
 */
class Comon_IE_ChatImportRunner
{
    public const SYNC_LINE_THRESHOLD = 800;

    public const JOB_PHASES = 5;

    public const GROUP_MAP_FILE = '.chat_import_group_map.json';

    /** @var list<string> */
    private static function attachmentTokensInBody(string $body): array
    {
        $out = [];
        if (preg_match_all('/\[(?:photo|file)Attachment-([^\]]+)\]/', $body, $m)) {
            foreach ($m[1] as $fn) {
                $fn = basename(trim((string)$fn));
                if ($fn !== '') {
                    $out[] = $fn;
                }
            }
        }
        return array_values(array_unique($out));
    }

    public static function sanitizeChatBody(string $message): string
    {
        $message = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/is', '', $message);
        $message = preg_replace('/<(iframe|object|embed|form|input|textarea|select|button|link|meta|style|title|head|body|html|xml|svg|math)[^>]*>/is', '', $message);
        $message = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/is', '', $message);
        $message = preg_replace('/(javascript|vbscript|file|data):\s*[^"\'\s]*/is', '', $message);
        $message = preg_replace('/data:\s*[^;]*;base64,?[a-zA-Z0-9+\/=]*/is', '', $message);
        return preg_replace('/^\s+|\s+$/s', '', $message);
    }

    /**
     * @return int unix timestamp
     */
    public static function parseTime(mixed $t): int
    {
        if (is_int($t) || (is_string($t) && ctype_digit($t))) {
            return (int)$t;
        }
        if (is_string($t) && $t !== '') {
            $u = strtotime($t);
            return $u !== false ? $u : (int)time();
        }
        return (int)time();
    }

    private static function userExists(mysqli $c, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $r = @$c->query('SELECT id FROM users WHERE id = ' . $id . ' LIMIT 1');
        return $r && $r->num_rows > 0;
    }

    private static function projectExists(mysqli $c, int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        $r = @$c->query('SELECT p_id FROM projects WHERE p_id = ' . $pid . ' LIMIT 1');
        return $r && $r->num_rows > 0;
    }

    private static function taskExists(mysqli $c, int $tid): bool
    {
        if ($tid <= 0) {
            return false;
        }
        $r = @$c->query('SELECT id FROM tasks WHERE id = ' . $tid . ' LIMIT 1');
        return $r && $r->num_rows > 0;
    }

    /**
     * @return array{ok:bool,error?:string,data?:array}
     */
    public static function processWebRequest(
        mysqli $connect,
        object $session,
        array $post,
        array $files,
        bool $isAdmin,
        bool $isStaff
    ): array {
        if (!$isAdmin) {
            return ['status' => 'error', 'error' => 'Only administrators can import chat packages'];
        }
        $preview = isset($post['preview']) && $post['preview'] === '1';
        $dry = $preview || (isset($post['simulate']) && $post['simulate'] === '1')
            || (!empty($post['dry_run']) && $post['dry_run'] === '1');
        $copyAttach = !empty($post['copy_attachments']) && $post['copy_attachments'] === '1';

        if (!isset($files['chat_package']) || empty($files['chat_package']['tmp_name'])) {
            return ['status' => 'error', 'error' => 'ZIP package is required (field chat_package)'];
        }
        $f = $files['chat_package'];
        $tmpZip = (string)$f['tmp_name'];
        if (!is_readable($tmpZip) || !class_exists('ZipArchive')) {
            return ['status' => 'error', 'error' => 'Invalid ZIP or ZipArchive missing'];
        }

        $root = dirname(__DIR__, 2);
        $work = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'comon_chat_imp_' . bin2hex(random_bytes(8));
        if (!@mkdir($work, 0755, true)) {
            return ['status' => 'error', 'error' => 'Cannot create temp dir'];
        }
        $ex = Comon_IE_ChatImportPackageReader::extractZipTo($tmpZip, $work);
        if (!$ex['ok']) {
            self::rrmdir($work);
            return ['status' => 'error', 'error' => $ex['error'] ?? 'extract failed'];
        }

        $reader = new Comon_IE_ChatImportPackageReader($work);
        $m = $reader->loadManifest();
        if (!$m['ok']) {
            self::rrmdir($work);
            return ['status' => 'error', 'error' => $m['error'] ?? 'manifest'];
        }

        $counts = $reader->countPreview();
        $totalLines = (int)($counts['one_to_one'] + $counts['discussion'] + $counts['group_msgs'] + $counts['task_msgs'] + $counts['groups']);

        if ($preview) {
            self::rrmdir($work);
            return [
                'status' => 'ok',
                'data' => [
                    'preview' => $counts,
                    'total_rows' => max(1, $totalLines),
                    'manifest' => $reader->getManifest(),
                ],
            ];
        }

        if (!$dry && $totalLines > self::SYNC_LINE_THRESHOLD) {
            $rel = 'storage/import_jobs/' . bin2hex(random_bytes(12)) . '.zip';
            $dest = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!@copy($tmpZip, $dest)) {
                self::rrmdir($work);
                return ['status' => 'error', 'error' => 'Could not store import ZIP'];
            }
            $jobId = Comon_IE_ImportJobService::createJob(
                $connect,
                (int)$session->userId,
                'chat',
                null,
                'zip',
                (string)($f['name'] ?? 'chat-import.zip'),
                $rel,
                [],
                [
                    'chat_import' => 1,
                    'copy_attachments' => $copyAttach ? 1 : 0,
                ],
                'create_only',
                'skip',
                false,
                self::JOB_PHASES
            );
            if ($jobId <= 0) {
                @unlink($dest);
                self::rrmdir($work);
                return ['status' => 'error', 'error' => 'Failed to create import job'];
            }
            $newRel = Comon_IE_ImportJobService::relativePath($jobId, 'zip');
            $newDest = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newRel);
            if (@rename($dest, $newDest)) {
                $connect->query('UPDATE import_jobs SET file_path = "' . $connect->real_escape_string($newRel) . '" WHERE id = ' . (int)$jobId);
            }
            self::rrmdir($work);
            return [
                'status' => 'ok',
                'data' => [
                    'async' => 1,
                    'job_id' => $jobId,
                    'total_rows' => self::JOB_PHASES,
                    'message' => 'Chat import queued (5 phases).',
                ],
            ];
        }

        $stats = self::runImportAllPhases($connect, $work, $dry, $copyAttach, $root);
        self::rrmdir($work);

        if (!$dry && $totalLines > 0) {
            Comon_IE_ImportJobService::recordCompletedSyncImport(
                $connect,
                (int)$session->userId,
                'chat',
                null,
                (string)($f['name'] ?? 'chat-import.zip'),
                'create_only',
                'skip',
                $totalLines,
                $stats['inserted'],
                count($stats['errors']),
                0,
                $stats['skipped']
            );
        }

        return [
            'status' => 'ok',
            'data' => [
                'inserted' => $stats['inserted'],
                'updated' => 0,
                'skipped' => $stats['skipped'],
                'errors' => $stats['errors'],
                'simulate' => $dry ? 1 : 0,
            ],
        ];
    }

    /**
     * @return array{inserted:int,skipped:int,errors:array<int,string>}
     */
    public static function runImportAllPhases(mysqli $connect, string $extractDir, bool $dryRun, bool $copyAttachments, string $projectRoot): array
    {
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $reader = new Comon_IE_ChatImportPackageReader($extractDir);
        $reader->loadManifest();

        $groupMapPath = $extractDir . DIRECTORY_SEPARATOR . self::GROUP_MAP_FILE;
        $groupMap = [];

        $r0 = self::importGroups($connect, $reader, $dryRun, $errors, $inserted, $skipped);
        if ($r0 !== []) {
            file_put_contents($groupMapPath, json_encode($r0, JSON_UNESCAPED_UNICODE));
            $groupMap = $r0;
        } elseif (is_file($groupMapPath)) {
            $groupMap = json_decode((string)file_get_contents($groupMapPath), true) ?: [];
        }

        self::importJsonlMessages(
            $connect,
            $reader->path(Comon_IE_ChatImportManifest::FILE_ONE_TO_ONE),
            '121',
            $dryRun,
            $copyAttachments,
            $projectRoot,
            $reader->attachmentsDir(),
            $errors,
            $inserted,
            $skipped,
            $groupMap
        );
        self::importJsonlMessages(
            $connect,
            $reader->path(Comon_IE_ChatImportManifest::FILE_DISCUSSION),
            'discussion',
            $dryRun,
            $copyAttachments,
            $projectRoot,
            $reader->attachmentsDir(),
            $errors,
            $inserted,
            $skipped,
            $groupMap
        );
        self::importJsonlMessages(
            $connect,
            $reader->path(Comon_IE_ChatImportManifest::FILE_GROUP_MESSAGES),
            'group_msg',
            $dryRun,
            $copyAttachments,
            $projectRoot,
            $reader->attachmentsDir(),
            $errors,
            $inserted,
            $skipped,
            $groupMap
        );
        self::importJsonlMessages(
            $connect,
            $reader->path(Comon_IE_ChatImportManifest::FILE_TASK_MESSAGES),
            'task',
            $dryRun,
            $copyAttachments,
            $projectRoot,
            $reader->attachmentsDir(),
            $errors,
            $inserted,
            $skipped,
            $groupMap
        );

        return ['inserted' => $inserted, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @param array<int,string> $errors
     * @return array<string,int> external_group_id -> new group id
     */
    private static function importGroups(
        mysqli $connect,
        Comon_IE_ChatImportPackageReader $reader,
        bool $dryRun,
        array &$errors,
        int &$inserted,
        int &$skipped
    ): array {
        $path = $reader->path(Comon_IE_ChatImportManifest::FILE_GROUPS);
        if (!is_readable($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        $list = json_decode($raw !== false ? $raw : '[]', true);
        if (!is_array($list)) {
            $errors[] = 'groups.json must be a JSON array';
            return [];
        }
        $map = [];
        foreach ($list as $idx => $g) {
            if (!is_array($g)) {
                continue;
            }
            $ext = trim((string)($g['external_id'] ?? ''));
            if ($ext === '') {
                $errors[] = 'groups[' . $idx . ']: missing external_id';
                continue;
            }
            $name = trim((string)($g['name'] ?? 'Imported group'));
            $createdBy = (int)($g['created_by'] ?? 0);
            if (!self::userExists($connect, $createdBy)) {
                $errors[] = 'groups[' . $ext . ']: invalid created_by';
                $skipped++;
                continue;
            }
            $now = (int)($g['created_at'] ?? time());
            $avatar = isset($g['avatar']) ? trim((string)$g['avatar']) : null;
            if ($avatar === '') {
                $avatar = null;
            }
            if ($dryRun) {
                $map[$ext] = 0;
                $inserted++;
                continue;
            }
            $avBind = $avatar === null || $avatar === '' ? '' : $avatar;
            $st = $connect->prepare('INSERT INTO group_chats (name, avatar, created_by, created_at, updated_at) VALUES (?,?,?,?,?)');
            if (!$st) {
                $errors[] = 'groups: prepare failed';
                return $map;
            }
            $st->bind_param('ssiii', $name, $avBind, $createdBy, $now, $now);
            if (!$st->execute()) {
                $errors[] = 'groups[' . $ext . ']: insert failed — ' . $st->error;
                $st->close();
                $skipped++;
                continue;
            }
            $gid = (int)$st->insert_id;
            $st->close();
            $map[$ext] = $gid;
            $inserted++;

            $members = $g['members'] ?? [];
            if (is_array($members)) {
                foreach ($members as $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    $uid = (int)($m['user_id'] ?? 0);
                    if (!self::userExists($connect, $uid)) {
                        continue;
                    }
                    $role = strtolower(trim((string)($m['role'] ?? 'member')));
                    if ($role !== 'admin') {
                        $role = 'member';
                    }
                    $joined = (int)($m['joined_at'] ?? $now);
                    $st2 = $connect->prepare('INSERT IGNORE INTO group_chat_members (group_id, user_id, role, joined_at, left_at) VALUES (?,?,?,?,NULL)');
                    if ($st2) {
                        $st2->bind_param('iisi', $gid, $uid, $role, $joined);
                        $st2->execute();
                        $st2->close();
                    }
                }
            }
        }
        return $map;
    }

    /**
     * @param array<string,int> $groupExtToId
     * @param array<int,string> $errors
     */
    private static function importJsonlMessages(
        mysqli $connect,
        string $path,
        string $mode,
        bool $dryRun,
        bool $copyAttachments,
        string $projectRoot,
        string $attachmentsDir,
        array &$errors,
        int &$inserted,
        int &$skipped,
        array $groupExtToId
    ): void {
        if (!is_readable($path)) {
            return;
        }
        $rows = [];
        $fh = fopen($path, 'r');
        if (!$fh) {
            return;
        }
        $lineNum = 0;
        while (($line = fgets($fh)) !== false) {
            $lineNum++;
            $t = trim($line);
            if ($t === '' || $t[0] === '#') {
                continue;
            }
            $obj = json_decode($t, true);
            if (!is_array($obj)) {
                $errors[] = basename($path) . ':' . $lineNum . ': invalid JSON';
                continue;
            }
            $rows[] = $obj;
        }
        fclose($fh);
        if ($rows === []) {
            return;
        }
        usort($rows, static function ($a, $b) {
            return self::parseTime($a['time'] ?? 0) <=> self::parseTime($b['time'] ?? 0);
        });

        $extToId = [];
        foreach ($rows as $obj) {
            $ext = trim((string)($obj['external_id'] ?? ''));
            if ($ext === '') {
                $errors[] = basename($path) . ': missing external_id';
                continue;
            }
            $plain = (string)($obj['plaintext_body'] ?? '');
            if ($copyAttachments && !$dryRun && $plain !== '' && is_dir($attachmentsDir)) {
                foreach (self::attachmentTokensInBody($plain) as $bn) {
                    Comon_IE_ChatAttachmentPaths::importCopyBasename($projectRoot, $attachmentsDir, $bn);
                }
            }
            $body = self::sanitizeChatBody($plain);
            $enc = function_exists('encryptString') ? encryptString($body) : $body;
            $uid = (int)($obj['sender_user_id'] ?? 0);
            if (!self::userExists($connect, $uid)) {
                $errors[] = $ext . ': invalid sender_user_id';
                $skipped++;
                continue;
            }
            $tm = self::parseTime($obj['time'] ?? time());
            $edited = !empty($obj['edited']) ? 1 : 0;
            $editTime = isset($obj['edit_time']) && $obj['edit_time'] !== null && $obj['edit_time'] !== ''
                ? (int)$obj['edit_time'] : 0;

            if ($mode === '121') {
                $recv = (int)($obj['receiver_user_id'] ?? 0);
                if (!self::userExists($connect, $recv)) {
                    $errors[] = $ext . ': invalid receiver_user_id';
                    $skipped++;
                    continue;
                }
                if ($dryRun) {
                    $extToId[$ext] = 0;
                    $inserted++;
                    continue;
                }
                $st = $connect->prepare('INSERT INTO messages (message, time, user_id, receiver, storage_a, storage_b, status, Project_id, edited, edit_time, reply_to) VALUES (?,?,?,?,?,?,\'unread\',0,?,?,NULL)');
                if (!$st) {
                    $errors[] = 'messages prepare failed';
                    return;
                }
                $st->bind_param('siiiiiii', $enc, $tm, $uid, $recv, $uid, $recv, $edited, $editTime);
                if (!$st->execute()) {
                    $errors[] = $ext . ': ' . $st->error;
                    $skipped++;
                    $st->close();
                    continue;
                }
                $extToId[$ext] = (int)$st->insert_id;
                $st->close();
                $inserted++;
            } elseif ($mode === 'discussion') {
                $pid = (int)($obj['project_id'] ?? 0);
                if (!self::projectExists($connect, $pid)) {
                    $errors[] = $ext . ': invalid project_id';
                    $skipped++;
                    continue;
                }
                if ($dryRun) {
                    $extToId[$ext] = 0;
                    $inserted++;
                    continue;
                }
                $st = $connect->prepare('INSERT INTO messages (message, time, user_id, receiver, storage_a, storage_b, status, Project_id, edited, edit_time, reply_to) VALUES (?,?,?,0,1,1,\'unread\',?,?,?,NULL)');
                if (!$st) {
                    return;
                }
                $st->bind_param('siiiii', $enc, $tm, $uid, $pid, $edited, $editTime);
                if (!$st->execute()) {
                    $errors[] = $ext . ': ' . $st->error;
                    $skipped++;
                    $st->close();
                    continue;
                }
                $extToId[$ext] = (int)$st->insert_id;
                $st->close();
                $inserted++;
            } elseif ($mode === 'group_msg') {
                $gExt = trim((string)($obj['external_group_id'] ?? ''));
                $gid = $groupExtToId[$gExt] ?? 0;
                if ($gid <= 0) {
                    $errors[] = $ext . ': unknown external_group_id ' . $gExt;
                    $skipped++;
                    continue;
                }
                if ($dryRun) {
                    $extToId[$ext] = 0;
                    $inserted++;
                    continue;
                }
                $st = $connect->prepare('INSERT INTO group_chat_messages (group_id, user_id, message, time, edited, edit_time, reply_to) VALUES (?,?,?,?,?,?,NULL)');
                if (!$st) {
                    return;
                }
                $st->bind_param('iisiii', $gid, $uid, $enc, $tm, $edited, $editTime);
                if (!$st->execute()) {
                    $errors[] = $ext . ': ' . $st->error;
                    $skipped++;
                    $st->close();
                    continue;
                }
                $extToId[$ext] = (int)$st->insert_id;
                $st->close();
                $inserted++;
            } elseif ($mode === 'task') {
                $tid = (int)($obj['task_id'] ?? 0);
                if (!self::taskExists($connect, $tid)) {
                    $errors[] = $ext . ': invalid task_id';
                    $skipped++;
                    continue;
                }
                if ($dryRun) {
                    $extToId[$ext] = 0;
                    $inserted++;
                    continue;
                }
                $st = $connect->prepare('INSERT INTO task_chat (message, time, user_id, task_id, storage_a, storage_b, status, edited, edit_time, reply_to) VALUES (?,?,?,?,1,1,\'unread\',?,?,NULL)');
                if (!$st) {
                    return;
                }
                $st->bind_param('siiiii', $enc, $tm, $uid, $tid, $edited, $editTime);
                if (!$st->execute()) {
                    $errors[] = $ext . ': ' . $st->error;
                    $skipped++;
                    $st->close();
                    continue;
                }
                $extToId[$ext] = (int)$st->insert_id;
                $st->close();
                $inserted++;
            }
        }

        foreach ($rows as $obj) {
            $ext = trim((string)($obj['external_id'] ?? ''));
            $rpl = trim((string)($obj['reply_to_external_id'] ?? ''));
            if ($ext === '' || $rpl === '' || $dryRun) {
                continue;
            }
            $mid = $extToId[$ext] ?? 0;
            $rid = $extToId[$rpl] ?? 0;
            if ($mid <= 0 || $rid <= 0) {
                continue;
            }
            if ($mode === '121' || $mode === 'discussion') {
                $connect->query('UPDATE messages SET reply_to = ' . (int)$rid . ' WHERE id = ' . (int)$mid . ' LIMIT 1');
            } elseif ($mode === 'group_msg') {
                $connect->query('UPDATE group_chat_messages SET reply_to = ' . (int)$rid . ' WHERE id = ' . (int)$mid . ' LIMIT 1');
            } elseif ($mode === 'task') {
                $connect->query('UPDATE task_chat SET reply_to = ' . (int)$rid . ' WHERE id = ' . (int)$mid . ' LIMIT 1');
            }
        }
    }

    /**
     * @return array{ok:bool,processed:int,success:int,skipped:int,failed:int,updated:int,error?:string}
     */
    public static function processJobChunk(mysqli $connect, array $jobRow, float $deadline): array
    {
        $root = dirname(__DIR__, 2);
        $rel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$jobRow['file_path']);
        $zipPath = $root . DIRECTORY_SEPARATOR . $rel;
        if (!is_readable($zipPath)) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'missing zip'];
        }
        $jobId = (int)$jobRow['id'];
        $phase = (int)$jobRow['processed_rows'];
        if ($phase < 0 || $phase >= self::JOB_PHASES) {
            return ['ok' => true, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0];
        }

        $extract = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'import_jobs' . DIRECTORY_SEPARATOR . 'chat_extract_' . $jobId;
        if ($phase === 0 && !is_dir($extract)) {
            if (!@mkdir($extract, 0755, true)) {
                return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'mkdir extract'];
            }
            $ex = Comon_IE_ChatImportPackageReader::extractZipTo($zipPath, $extract);
            if (!$ex['ok']) {
                return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => $ex['error'] ?? 'extract'];
            }
        }
        if (!is_dir($extract)) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'extract dir missing'];
        }

        $opts = json_decode((string)($jobRow['options_json'] ?? '{}'), true);
        if (!is_array($opts)) {
            $opts = [];
        }
        $copyAttach = !empty($opts['copy_attachments']);

        $reader = new Comon_IE_ChatImportPackageReader($extract);
        $m = $reader->loadManifest();
        if (!$m['ok']) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => $m['error'] ?? 'manifest'];
        }

        $errors = [];
        $inserted = 0;
        $skipped = 0;
        $groupMapPath = $extract . DIRECTORY_SEPARATOR . self::GROUP_MAP_FILE;
        $groupMap = [];
        if (is_file($groupMapPath)) {
            $groupMap = json_decode((string)file_get_contents($groupMapPath), true) ?: [];
        }

        if ($phase === 0) {
            $groupMap = self::importGroups($connect, $reader, false, $errors, $inserted, $skipped);
            if ($groupMap !== []) {
                file_put_contents($groupMapPath, json_encode($groupMap, JSON_UNESCAPED_UNICODE));
            }
        } elseif ($phase === 1) {
            self::importJsonlMessages($connect, $reader->path(Comon_IE_ChatImportManifest::FILE_ONE_TO_ONE), '121', false, $copyAttach, $root, $reader->attachmentsDir(), $errors, $inserted, $skipped, $groupMap);
        } elseif ($phase === 2) {
            self::importJsonlMessages($connect, $reader->path(Comon_IE_ChatImportManifest::FILE_DISCUSSION), 'discussion', false, $copyAttach, $root, $reader->attachmentsDir(), $errors, $inserted, $skipped, $groupMap);
        } elseif ($phase === 3) {
            self::importJsonlMessages($connect, $reader->path(Comon_IE_ChatImportManifest::FILE_GROUP_MESSAGES), 'group_msg', false, $copyAttach, $root, $reader->attachmentsDir(), $errors, $inserted, $skipped, $groupMap);
        } elseif ($phase === 4) {
            self::importJsonlMessages($connect, $reader->path(Comon_IE_ChatImportManifest::FILE_TASK_MESSAGES), 'task', false, $copyAttach, $root, $reader->attachmentsDir(), $errors, $inserted, $skipped, $groupMap);
        }

        $fail = count($errors);
        foreach ($errors as $msg) {
            Comon_IE_ImportJobService::appendError($connect, $jobId, $phase, ['phase' => $phase], $msg);
        }

        return [
            'ok' => true,
            'processed' => 1,
            'success' => $inserted,
            'skipped' => $skipped,
            'failed' => $fail,
            'updated' => 0,
        ];
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $p = $file->getRealPath();
            if ($file->isDir()) {
                @rmdir($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }
}
