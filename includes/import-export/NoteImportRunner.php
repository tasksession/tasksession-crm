<?php

class Comon_IE_NoteImportRunner
{
    public const SYNC_ROW_THRESHOLD = 250;
    public const JOB_CHUNK_ROWS = 150;

    /**
     * @return array{status:string,error?:string,data?:array}
     */
    public static function processWebRequest(
        mysqli $connect,
        object $session,
        array $post,
        array $files,
        bool $isAdmin,
        bool $isStaff
    ): array {
        if (!$isAdmin && !$isStaff) {
            return ['status' => 'error', 'error' => 'Not authorized'];
        }

        $notesType = isset($post['notes_type']) ? strtolower(trim((string)$post['notes_type'])) : 'private';
        if (!in_array($notesType, ['private', 'profile', 'project', 'system'], true)) {
            $notesType = 'private';
        }
        $previewMode = isset($post['preview']) && $post['preview'] === '1';
        $simulate = isset($post['simulate']) && $post['simulate'] === '1';
        $dryRun = $simulate || (!empty($post['dry_run']) && $post['dry_run'] === '1');
        $duplicateStrategy = isset($post['duplicate_strategy']) ? (string)$post['duplicate_strategy'] : 'skip';

        if (!isset($files['csv_file']) || empty($files['csv_file']['tmp_name'])) {
            return ['status' => 'error', 'error' => 'CSV file is required'];
        }
        $src = $files['csv_file'];
        $work = self::prepareWorkFile($src);
        if ($work === null) {
            return ['status' => 'error', 'error' => 'Invalid CSV or XLSX file'];
        }
        $cleanup = ($work !== $src['tmp_name']);

        $mapping = [];
        if (isset($post['field_mapping']) && $post['field_mapping'] !== '') {
            $decoded = json_decode((string)$post['field_mapping'], true);
            if (is_array($decoded)) {
                $mapping = $decoded;
            }
        }

        $totalRows = Comon_IE_CsvUtilities::countDataRows($work);
        if ($totalRows <= 0) {
            if ($cleanup) {
                @unlink($work);
            }
            return ['status' => 'error', 'error' => 'No rows in file'];
        }

        if (!$previewMode && !$dryRun && $totalRows > self::SYNC_ROW_THRESHOLD) {
            $jobId = self::createAsyncJob($connect, $session, $src, $work, $mapping, $notesType, $duplicateStrategy, $dryRun);
            if ($cleanup) {
                @unlink($work);
            }
            if ($jobId <= 0) {
                return ['status' => 'error', 'error' => 'Failed to create import job'];
            }
            return [
                'status' => 'ok',
                'data' => [
                    'async' => 1,
                    'job_id' => $jobId,
                    'total_rows' => $totalRows,
                    'message' => 'Import queued.',
                ],
            ];
        }

        $run = self::processRowsFromFile(
            $connect,
            $work,
            $mapping,
            $notesType,
            0,
            PHP_INT_MAX,
            (int)$session->userId,
            $previewMode,
            $dryRun,
            $duplicateStrategy,
            15
        );
        if ($cleanup) {
            @unlink($work);
        }
        if (!$run['ok']) {
            return ['status' => 'error', 'error' => $run['error'] ?? 'Import failed'];
        }

        $data = [
            'inserted' => $run['saved'],
            'would_insert' => $run['would_save'],
            'updated' => 0,
            'skipped' => $run['skipped'],
            'errors' => $run['errors'],
            'simulate' => $dryRun ? 1 : 0,
        ];
        if ($previewMode) {
            $data['preview'] = $run['preview_rows'];
            $data['total_rows'] = $totalRows;
        } elseif (!$dryRun && $totalRows > 0) {
            Comon_IE_ImportJobService::recordCompletedSyncImport(
                $connect,
                (int)$session->userId,
                'notes',
                $notesType,
                (string)($src['name'] ?? 'import.csv'),
                'create_only',
                $duplicateStrategy,
                $totalRows,
                $run['saved'],
                $run['failed'],
                0,
                $run['skipped']
            );
        }
        return ['status' => 'ok', 'data' => $data];
    }

    /**
     * @param array<string,mixed> $jobRow
     * @return array{ok:bool,processed:int,success:int,skipped:int,failed:int,updated:int,error?:string}
     */
    public static function processJobChunk(mysqli $connect, array $jobRow, float $deadlineUnix): array
    {
        $root = dirname(__DIR__, 2);
        $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $jobRow['file_path'] ?? '');
        if (!is_readable($path)) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'missing file'];
        }
        $options = json_decode((string)($jobRow['options_json'] ?? '{}'), true) ?: [];
        $notesType = (string)($options['notes_type'] ?? 'private');
        if (!in_array($notesType, ['private', 'profile', 'project', 'system'], true)) {
            $notesType = 'private';
        }
        $mapping = json_decode((string)($jobRow['mapping_json'] ?? '{}'), true) ?: [];
        $duplicateStrategy = (string)($jobRow['duplicate_strategy'] ?? 'skip');
        $dry = !empty($jobRow['dry_run']);
        $createdBy = (int)($options['created_by'] ?? 0);
        if ($createdBy <= 0) {
            $createdBy = (int)($jobRow['user_id'] ?? 0);
        }
        $offset = (int)($jobRow['processed_rows'] ?? 0);

        $run = self::processRowsFromFile(
            $connect,
            $path,
            $mapping,
            $notesType,
            $offset,
            self::JOB_CHUNK_ROWS,
            $createdBy,
            false,
            $dry,
            $duplicateStrategy,
            0
        );
        if (!$run['ok']) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => $run['error'] ?? 'chunk failed'];
        }

        return [
            'ok' => true,
            'processed' => $run['processed'],
            'success' => $run['saved'] + $run['would_save'],
            'skipped' => $run['skipped'],
            'failed' => $run['failed'],
            'updated' => 0,
        ];
    }

    /**
     * @param array<string,mixed> $session
     * @param array<string,mixed> $src
     * @param array<string,mixed> $mapping
     */
    private static function createAsyncJob(
        mysqli $connect,
        object $session,
        array $src,
        string $work,
        array $mapping,
        string $notesType,
        string $duplicateStrategy,
        bool $dryRun
    ): int {
        $rel = 'storage/import_jobs/' . bin2hex(random_bytes(12)) . '.csv';
        $dest = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!@copy($work, $dest)) {
            return 0;
        }
        $totalRows = Comon_IE_CsvUtilities::countDataRows($work);
        $options = [
            'notes_type' => $notesType,
            'created_by' => (int)$session->userId,
        ];
        $jobId = Comon_IE_ImportJobService::createJob(
            $connect,
            (int)$session->userId,
            'notes',
            $notesType,
            'csv',
            (string)($src['name'] ?? 'import.csv'),
            $rel,
            $mapping,
            $options,
            'create_only',
            $duplicateStrategy,
            $dryRun,
            $totalRows
        );
        if ($jobId <= 0) {
            @unlink($dest);
            return 0;
        }
        $newRel = Comon_IE_ImportJobService::relativePath($jobId, 'csv');
        $newDest = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newRel);
        if (@rename($dest, $newDest)) {
            $connect->query('UPDATE import_jobs SET file_path = "' . $connect->real_escape_string($newRel) . '" WHERE id = ' . (int)$jobId);
        }
        return $jobId;
    }

    /**
     * @param array<string,mixed> $mapping
     * @return array{ok:bool,processed:int,saved:int,would_save:int,skipped:int,failed:int,errors:list<string>,preview_rows:list<array<string,mixed>>,error?:string}
     */
    private static function processRowsFromFile(
        mysqli $connect,
        string $path,
        array $mapping,
        string $notesType,
        int $offset,
        int $limit,
        int $sessionUserId,
        bool $previewMode,
        bool $dryRun,
        string $duplicateStrategy,
        int $previewLimit
    ): array {
        $fh = @fopen($path, 'r');
        if (!$fh) {
            return ['ok' => false, 'processed' => 0, 'saved' => 0, 'would_save' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [], 'preview_rows' => [], 'error' => 'Could not open file'];
        }
        [$headers, $headerMap] = Comon_IE_CsvUtilities::readHeadersFromHandle($fh);
        if ($headers === []) {
            fclose($fh);
            return ['ok' => false, 'processed' => 0, 'saved' => 0, 'would_save' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => [], 'preview_rows' => [], 'error' => 'Missing header row'];
        }

        $processed = 0;
        $saved = 0;
        $wouldSave = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $previewRows = [];
        $lineNo = 1;
        $skipDataRows = max(0, $offset);
        $seenDataRows = 0;

        while (($row = fgetcsv($fh)) !== false) {
            $lineNo++;
            if (self::isEmptyDataRow($row)) {
                continue;
            }
            if ($seenDataRows < $skipDataRows) {
                $seenDataRows++;
                continue;
            }
            if ($processed >= $limit) {
                break;
            }
            $seenDataRows++;
            $processed++;
            $res = self::processOneRow(
                $connect,
                $row,
                $lineNo,
                $mapping,
                $headerMap,
                $headers,
                $notesType,
                $sessionUserId,
                $previewMode,
                $dryRun,
                $duplicateStrategy,
                $previewRows,
                $previewLimit
            );
            if ($res['error_line'] !== '') {
                $errors[] = $res['error_line'];
            }
            if ($res['action'] === 'saved') {
                $saved++;
            } elseif ($res['action'] === 'preview_ok' || $res['action'] === 'dry_ok') {
                $wouldSave++;
            } elseif ($res['action'] === 'skip') {
                $skipped++;
                if (empty($res['duplicate'])) {
                    $failed++;
                }
            }
        }
        fclose($fh);

        return [
            'ok' => true,
            'processed' => $processed,
            'saved' => $saved,
            'would_save' => $wouldSave,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
            'preview_rows' => $previewRows,
        ];
    }

    /**
     * @param array<int,string> $row
     * @param array<string,mixed> $mapping
     * @param array<string,int> $headerMap
     * @param array<int,string> $headers
     * @param array<int,array<string,mixed>> $previewRows
     * @return array{action:string,error_line:string,duplicate?:bool}
     */
    private static function processOneRow(
        mysqli $connect,
        array $row,
        int $lineNo,
        array $mapping,
        array $headerMap,
        array $headers,
        string $notesType,
        int $sessionUserId,
        bool $previewMode,
        bool $dryRun,
        string $duplicateStrategy,
        array &$previewRows,
        int $previewLimit
    ): array {
        $get = static function (string $key) use ($row, $mapping, $headerMap, $headers): string {
            return Comon_IE_CsvUtilities::rowGet($row, $key, $mapping, $headerMap, $headers);
        };
        $resolvedType = $notesType;
        if ($notesType === 'system') {
            $typeRaw = strtolower(trim((string)$get('Note Type')));
            if ($typeRaw === 'internal') {
                $typeRaw = 'private';
            }
            if (!in_array($typeRaw, ['private', 'profile', 'project'], true)) {
                $e = 'Row ' . $lineNo . ': Note Type must be private, profile, or project';
                self::pushPreview($previewMode, $previewRows, $previewLimit, $notesType, $lineNo, '', '', '', $e);
                return ['action' => 'skip', 'error_line' => $e];
            }
            $resolvedType = $typeRaw;
        }

        $content = trim(sanitize_tinymce_content((string)$get('Content')));
        if ($content === '') {
            $e = 'Row ' . $lineNo . ': Content is required';
            self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, '', '', '', $e);
            return ['action' => 'skip', 'error_line' => $e];
        }
        $color = self::normalizeColor((string)$get('Color'));
        $title = mb_substr(trim((string)$get('Title')), 0, 255);
        $creatorId = (int)trim((string)$get('Creator ID'));
        if ($creatorId <= 0) {
            $creatorId = $sessionUserId;
        }
        $creatorType = self::normalizeCreatorType((string)$get('Creator Type'));
        if ($creatorType === null) {
            $creatorType = self::defaultCreatorType($sessionUserId, $connect, $resolvedType);
        }

        if ($resolvedType === 'private') {
            $userId = (int)trim((string)$get('User ID'));
            if ($userId <= 0) {
                $userId = $sessionUserId;
            }
            if (!self::userExists($connect, $userId)) {
                $e = 'Row ' . $lineNo . ': User ID not found';
                self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$userId, '', $title, $e);
                return ['action' => 'skip', 'error_line' => $e];
            }
            if ($title === '') {
                $title = 'Document';
            }
            if ($duplicateStrategy === 'skip' && self::privateDuplicateExists($connect, $userId, $title)) {
                $e = 'Row ' . $lineNo . ': Duplicate private note title for user, skipped';
                self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$userId, '', $title, $e);
                return ['action' => 'skip', 'error_line' => $e, 'duplicate' => true];
            }
            if ($previewMode) {
                self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$userId, '', $title, 'Would import');
                return ['action' => 'preview_ok', 'error_line' => ''];
            }
            if ($dryRun) {
                return ['action' => 'dry_ok', 'error_line' => ''];
            }
            $enc = encryptString($content);
            $stmt = $connect->prepare('INSERT INTO private_notes (user_id, title, content, color, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
            if (!$stmt) {
                return ['action' => 'skip', 'error_line' => 'Row ' . $lineNo . ': Prepare failed'];
            }
            $stmt->bind_param('isss', $userId, $title, $enc, $color);
            $ok = $stmt->execute();
            $stmt->close();
            if (!$ok) {
                return ['action' => 'skip', 'error_line' => 'Row ' . $lineNo . ': Insert failed'];
            }
            return ['action' => 'saved', 'error_line' => ''];
        }

        if ($resolvedType === 'profile') {
            $userId = (int)trim((string)$get('User ID'));
            if ($userId <= 0) {
                $e = 'Row ' . $lineNo . ': User ID is required for profile docs';
                self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, '', '', '', $e);
                return ['action' => 'skip', 'error_line' => $e];
            }
            if (!self::userExists($connect, $userId)) {
                $e = 'Row ' . $lineNo . ': User ID not found';
                self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$userId, '', '', $e);
                return ['action' => 'skip', 'error_line' => $e];
            }
            if ($creatorType === null || !in_array($creatorType, ['admin', 'staff'], true)) {
                $e = 'Row ' . $lineNo . ': Creator Type must be admin or staff';
                self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$userId, (string)$creatorId, '', $e);
                return ['action' => 'skip', 'error_line' => $e];
            }
            if (!self::creatorMatchesType($connect, $creatorId, $creatorType)) {
                $e = 'Row ' . $lineNo . ': Creator ID does not match Creator Type';
                self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$userId, (string)$creatorId, '', $e);
                return ['action' => 'skip', 'error_line' => $e];
            }
            if ($duplicateStrategy === 'skip' && self::profileDuplicateExists($connect, $userId, $creatorId, $creatorType)) {
                $e = 'Row ' . $lineNo . ': Duplicate profile note scope, skipped';
                self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$userId, (string)$creatorId, '', $e);
                return ['action' => 'skip', 'error_line' => $e, 'duplicate' => true];
            }
            if ($previewMode) {
                self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$userId, $creatorType . ':' . $creatorId, '', 'Would import');
                return ['action' => 'preview_ok', 'error_line' => ''];
            }
            if ($dryRun) {
                return ['action' => 'dry_ok', 'error_line' => ''];
            }
            $enc = encryptString($content);
            $stmt = $connect->prepare('INSERT INTO profile_notes (user_id, creator_id, creator_type, content, color, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
            if (!$stmt) {
                return ['action' => 'skip', 'error_line' => 'Row ' . $lineNo . ': Prepare failed'];
            }
            $stmt->bind_param('iisss', $userId, $creatorId, $creatorType, $enc, $color);
            $ok = $stmt->execute();
            $stmt->close();
            if (!$ok) {
                return ['action' => 'skip', 'error_line' => 'Row ' . $lineNo . ': Insert failed'];
            }
            return ['action' => 'saved', 'error_line' => ''];
        }

        $projectId = (int)trim((string)$get('Project ID'));
        if ($projectId <= 0) {
            $e = 'Row ' . $lineNo . ': Project ID is required for project docs';
            self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, '', '', $title, $e);
            return ['action' => 'skip', 'error_line' => $e];
        }
        if (!self::projectExists($connect, $projectId)) {
            $e = 'Row ' . $lineNo . ': Project ID not found';
            self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$projectId, '', $title, $e);
            return ['action' => 'skip', 'error_line' => $e];
        }
        if ($creatorType === null || !in_array($creatorType, ['admin', 'staff', 'client'], true)) {
            $e = 'Row ' . $lineNo . ': Creator Type must be admin, staff, or client';
            self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$projectId, (string)$creatorId, $title, $e);
            return ['action' => 'skip', 'error_line' => $e];
        }
        if (!self::creatorMatchesType($connect, $creatorId, $creatorType)) {
            $e = 'Row ' . $lineNo . ': Creator ID does not match Creator Type';
            self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$projectId, (string)$creatorId, $title, $e);
            return ['action' => 'skip', 'error_line' => $e];
        }
        if ($duplicateStrategy === 'skip' && self::projectDuplicateExists($connect, $projectId, $creatorId, $creatorType, $title)) {
            $e = 'Row ' . $lineNo . ': Duplicate project note scope/title, skipped';
            self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$projectId, (string)$creatorId, $title, $e);
            return ['action' => 'skip', 'error_line' => $e, 'duplicate' => true];
        }
        if ($previewMode) {
            self::pushPreview($previewMode, $previewRows, $previewLimit, $resolvedType, $lineNo, (string)$projectId, $creatorType . ':' . $creatorId, $title, 'Would import');
            return ['action' => 'preview_ok', 'error_line' => ''];
        }
        if ($dryRun) {
            return ['action' => 'dry_ok', 'error_line' => ''];
        }
        $enc = encryptString($content);
        $stmt = $connect->prepare('INSERT INTO project_tab_notes (project_id, creator_id, creator_type, title, content, color, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
        if (!$stmt) {
            return ['action' => 'skip', 'error_line' => 'Row ' . $lineNo . ': Prepare failed'];
        }
        $stmt->bind_param('iissss', $projectId, $creatorId, $creatorType, $title, $enc, $color);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return ['action' => 'skip', 'error_line' => 'Row ' . $lineNo . ': Insert failed'];
        }
        return ['action' => 'saved', 'error_line' => ''];
    }

    private static function normalizeColor(string $raw): string
    {
        $c = strtolower(trim($raw));
        if (in_array($c, ['blue', 'green', 'yellow', 'default'], true)) {
            return $c === 'default' ? '' : $c;
        }
        return '';
    }

    private static function normalizeCreatorType(string $raw): ?string
    {
        $t = strtolower(trim($raw));
        if (in_array($t, ['admin', 'staff', 'client'], true)) {
            return $t;
        }
        return null;
    }

    private static function defaultCreatorType(int $userId, mysqli $connect, string $notesType): ?string
    {
        $status = self::userAccountStatus($connect, $userId);
        if ($status === 1) {
            return 'admin';
        }
        if ($status === 3) {
            return 'staff';
        }
        if ($status === 2 && $notesType === 'project') {
            return 'client';
        }
        return null;
    }

    private static function userExists(mysqli $connect, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $q = @mysqli_query($connect, 'SELECT id FROM users WHERE id = ' . (int)$userId . ' LIMIT 1');
        if (!$q) {
            return false;
        }
        $ok = mysqli_num_rows($q) > 0;
        mysqli_free_result($q);
        return $ok;
    }

    private static function projectExists(mysqli $connect, int $projectId): bool
    {
        if ($projectId <= 0) {
            return false;
        }
        $q = @mysqli_query($connect, 'SELECT p_id FROM projects WHERE p_id = ' . (int)$projectId . ' LIMIT 1');
        if (!$q) {
            return false;
        }
        $ok = mysqli_num_rows($q) > 0;
        mysqli_free_result($q);
        return $ok;
    }

    private static function userAccountStatus(mysqli $connect, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        $q = @mysqli_query($connect, 'SELECT accountStatus FROM users WHERE id = ' . (int)$userId . ' LIMIT 1');
        if (!$q || mysqli_num_rows($q) === 0) {
            if ($q) {
                mysqli_free_result($q);
            }
            return 0;
        }
        $r = mysqli_fetch_assoc($q);
        mysqli_free_result($q);
        return (int)($r['accountStatus'] ?? 0);
    }

    private static function creatorMatchesType(mysqli $connect, int $creatorId, string $creatorType): bool
    {
        $status = self::userAccountStatus($connect, $creatorId);
        if ($creatorType === 'admin') {
            return $status === 1;
        }
        if ($creatorType === 'staff') {
            return $status === 3;
        }
        if ($creatorType === 'client') {
            return $status === 2;
        }
        return false;
    }

    private static function privateDuplicateExists(mysqli $connect, int $userId, string $title): bool
    {
        $sql = 'SELECT id FROM private_notes WHERE user_id = ' . (int)$userId
            . ' AND title = "' . $connect->real_escape_string($title) . '" LIMIT 1';
        $q = @mysqli_query($connect, $sql);
        if (!$q) {
            return false;
        }
        $dup = mysqli_num_rows($q) > 0;
        mysqli_free_result($q);
        return $dup;
    }

    private static function profileDuplicateExists(mysqli $connect, int $userId, int $creatorId, string $creatorType): bool
    {
        $sql = 'SELECT id FROM profile_notes WHERE user_id = ' . (int)$userId
            . ' AND creator_id = ' . (int)$creatorId
            . ' AND creator_type = "' . $connect->real_escape_string($creatorType) . '" LIMIT 1';
        $q = @mysqli_query($connect, $sql);
        if (!$q) {
            return false;
        }
        $dup = mysqli_num_rows($q) > 0;
        mysqli_free_result($q);
        return $dup;
    }

    private static function projectDuplicateExists(mysqli $connect, int $projectId, int $creatorId, string $creatorType, string $title): bool
    {
        $sql = 'SELECT id FROM project_tab_notes WHERE project_id = ' . (int)$projectId
            . ' AND creator_id = ' . (int)$creatorId
            . ' AND creator_type = "' . $connect->real_escape_string($creatorType) . '"'
            . ' AND title = "' . $connect->real_escape_string($title) . '" LIMIT 1';
        $q = @mysqli_query($connect, $sql);
        if (!$q) {
            return false;
        }
        $dup = mysqli_num_rows($q) > 0;
        mysqli_free_result($q);
        return $dup;
    }

    private static function pushPreview(
        bool $previewMode,
        array &$previewRows,
        int $previewLimit,
        string $notesType,
        int $lineNo,
        string $scopeId,
        string $creator,
        string $title,
        string $note
    ): void {
        if (!$previewMode || count($previewRows) >= $previewLimit) {
            return;
        }
        $row = ['row' => $lineNo, 'scope_id' => $scopeId, 'note' => $note, 'title' => $title, 'creator' => $creator];
        if ($notesType === 'private') {
            unset($row['creator']);
        }
        $previewRows[] = $row;
    }

    private static function isEmptyDataRow(array $row): bool
    {
        foreach ($row as $c) {
            if (trim((string)$c) !== '') {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string,mixed> $file
     */
    private static function prepareWorkFile(array $file): ?string
    {
        $tmp = $file['tmp_name'] ?? '';
        if ($tmp === '' || !is_readable($tmp)) {
            return null;
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return $tmp;
        }
        if ($ext === 'xlsx') {
            return Comon_IE_XlsxSimpleReader::writeTempCsv($tmp);
        }
        return null;
    }
}
