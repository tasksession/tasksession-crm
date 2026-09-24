<?php
/**
 * CSV/XLSX project import.
 *
 * Column mapping targets (labels / keys):
 *   Project Title, Project Description, Budget, Status, Archive, Trash,
 *   Start Date, End Date, Company ID, Main Client ID, Additional Client IDs, Assigned Staff
 * Rules: If both Company ID and Main Client ID are set, Company ID wins.
 * Assigned Staff: comma-separated user ids and/or group:GROUP_ID (staff_team_group_members).
 */
class Comon_IE_ProjectImportRunner
{
    public const SYNC_ROW_THRESHOLD = 200;
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
        if (!$isAdmin) {
            return ['status' => 'error', 'error' => 'Only administrators can import projects'];
        }
        $previewMode = isset($post['preview']) && $post['preview'] === '1';
        $simulate = isset($post['simulate']) && $post['simulate'] === '1';
        $dryRun = $simulate || (!empty($post['dry_run']) && $post['dry_run'] === '1');
        $duplicateStrategy = isset($post['duplicate_strategy']) ? (string)$post['duplicate_strategy'] : 'skip';

        if (!isset($files['csv_file']) || empty($files['csv_file']['tmp_name'])) {
            return ['status' => 'error', 'error' => 'File is required'];
        }
        $file = $files['csv_file'];
        $workPath = self::prepareWorkFile($file);
        if ($workPath === null) {
            return ['status' => 'error', 'error' => 'Invalid CSV or XLSX file'];
        }
        $cleanupWork = ($workPath !== $file['tmp_name']);

        $fieldMapping = [];
        if (isset($post['field_mapping']) && $post['field_mapping'] !== '') {
            $decoded = json_decode((string)$post['field_mapping'], true);
            if (is_array($decoded)) {
                $fieldMapping = $decoded;
            }
        }

        $totalDataRows = Comon_IE_CsvUtilities::countDataRows($workPath);
        if (!$previewMode && !$dryRun && $totalDataRows > self::SYNC_ROW_THRESHOLD) {
            $rel = 'storage/import_jobs/' . bin2hex(random_bytes(12)) . '.csv';
            $dest = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!@copy($workPath, $dest)) {
                if ($cleanupWork) {
                    @unlink($workPath);
                }
                return ['status' => 'error', 'error' => 'Could not store import file'];
            }
            $options = [
                'duplicate_strategy' => $duplicateStrategy,
                'created_by' => (int)$session->userId,
            ];
            $jobId = Comon_IE_ImportJobService::createJob(
                $connect,
                (int)$session->userId,
                'project',
                null,
                'csv',
                (string)($file['name'] ?? 'import.csv'),
                $rel,
                $fieldMapping,
                $options,
                'create_only',
                $duplicateStrategy,
                $dryRun,
                $totalDataRows
            );
            if ($cleanupWork) {
                @unlink($workPath);
            }
            if ($jobId <= 0) {
                return ['status' => 'error', 'error' => 'Failed to create import job'];
            }
            $newRel = Comon_IE_ImportJobService::relativePath($jobId, 'csv');
            $newDest = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $newRel);
            if (@rename($dest, $newDest)) {
                $connect->query('UPDATE import_jobs SET file_path = "' . $connect->real_escape_string($newRel) . '" WHERE id = ' . (int)$jobId);
            }
            return [
                'status' => 'ok',
                'data' => [
                    'async' => 1,
                    'job_id' => $jobId,
                    'total_rows' => $totalDataRows,
                    'message' => 'Import queued.',
                ],
            ];
        }

        $fh = fopen($workPath, 'r');
        if (!$fh) {
            if ($cleanupWork) {
                @unlink($workPath);
            }
            return ['status' => 'error', 'error' => 'Failed to open file'];
        }
        [$headers, $headerMap] = Comon_IE_CsvUtilities::readHeadersFromHandle($fh);
        if ($headers === []) {
            fclose($fh);
            if ($cleanupWork) {
                @unlink($workPath);
            }
            return ['status' => 'error', 'error' => 'Invalid header row'];
        }

        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $previewRows = [];
        $rowNum = 0;
        $previewLimit = 15;

        $get = function (array $row, string $key) use ($fieldMapping, $headerMap, $headers) {
            return Comon_IE_CsvUtilities::rowGet($row, $key, $fieldMapping, $headerMap, $headers);
        };

        while (($row = fgetcsv($fh)) !== false) {
            if (!is_array($row) || count($row) === 0) {
                continue;
            }
            $rowNum++;
            $res = self::processOneProjectRow(
                $connect,
                $row,
                $rowNum,
                $get,
                $previewMode,
                $dryRun,
                $duplicateStrategy,
                $previewRows,
                $previewLimit
            );
            if ($res['error_line'] !== '') {
                $errors[] = $res['error_line'];
            }
            if ($res['action'] === 'skip') {
                $skipped++;
            } elseif ($res['action'] === 'insert') {
                $inserted++;
            }
        }
        fclose($fh);
        if ($cleanupWork) {
            @unlink($workPath);
        }

        $data = [
            'inserted' => $inserted,
            'updated' => 0,
            'skipped' => $skipped,
            'errors' => $errors,
            'simulate' => $dryRun ? 1 : 0,
        ];
        if ($previewMode) {
            $data['preview'] = $previewRows;
            $data['total_rows'] = $rowNum;
        } elseif (!$dryRun && $rowNum > 0) {
            Comon_IE_ImportJobService::recordCompletedSyncImport(
                $connect,
                (int)$session->userId,
                'project',
                null,
                (string)($file['name'] ?? 'import.csv'),
                'create_only',
                $duplicateStrategy,
                $rowNum,
                $inserted,
                count($errors),
                0,
                $skipped
            );
        }
        return ['status' => 'ok', 'data' => $data];
    }

    /**
     * @param array<int,array<string,mixed>> $previewRows
     */
    private static function pushProjectPreviewRow(
        bool $previewMode,
        array &$previewRows,
        int $previewLimit,
        int $rowNum,
        string $projectTitle,
        string $note
    ): void {
        if (!$previewMode || count($previewRows) >= $previewLimit) {
            return;
        }
        $previewRows[] = [
            'row' => $rowNum,
            'project_title' => $projectTitle,
            'note' => $note,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $previewRows
     * @return array{action:string,error_line:string,duplicate_title?:bool}
     */
    private static function processOneProjectRow(
        mysqli $connect,
        array $row,
        int $rowNum,
        callable $get,
        bool $previewMode,
        bool $dryRun,
        string $duplicateStrategy,
        array &$previewRows,
        int $previewLimit
    ): array {
        $title = mb_substr(trim($get($row, 'Project Title')), 0, 512);
        if ($title === '') {
            $err = 'Row ' . $rowNum . ': Project Title is required';
            self::pushProjectPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, '', $err);
            return ['action' => 'skip', 'error_line' => $err];
        }

        if ($duplicateStrategy === 'skip') {
            require_once dirname(__DIR__) . '/projects.php';
            $existing = Projects::findByTitle($title);
            if ($existing) {
                $err = 'Row ' . $rowNum . ': Duplicate project title (skipped because duplicates are set to skip)';
                self::pushProjectPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $title, $err);
                return ['action' => 'skip', 'error_line' => $err, 'duplicate_title' => true];
            }
        }

        $companyId = (int)trim($get($row, 'Company ID'));
        $mainClientId = (int)trim($get($row, 'Main Client ID'));
        $mainUserId = 0;
        $cIdsList = [];

        if ($companyId > 0) {
            $cinfo = Comon_IE_ProjectTaskImportHelpers::companyPrimaryAndMembers($connect, $companyId);
            if ($cinfo === null) {
                $err = 'Row ' . $rowNum . ': Invalid Company ID';
                self::pushProjectPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $title, $err);
                return ['action' => 'skip', 'error_line' => $err];
            }
            $mainUserId = (int)$cinfo['primary'];
            $cIdsList = $cinfo['member_ids'];
        } elseif ($mainClientId > 0) {
            if (!Comon_IE_ProjectTaskImportHelpers::isClientUser($connect, $mainClientId)) {
                $err = 'Row ' . $rowNum . ': Main Client ID must be a client user';
                self::pushProjectPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $title, $err);
                return ['action' => 'skip', 'error_line' => $err];
            }
            $mainUserId = $mainClientId;
            $cIdsList = [$mainUserId];
        } else {
            $err = 'Row ' . $rowNum . ': Company ID or Main Client ID is required';
            self::pushProjectPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $title, $err);
            return ['action' => 'skip', 'error_line' => $err];
        }

        $extraClients = trim($get($row, 'Additional Client IDs'));
        if ($extraClients !== '') {
            foreach (preg_split('/\s*,\s*/', $extraClients) ?: [] as $piece) {
                $piece = (int)trim((string)$piece);
                if ($piece > 0 && Comon_IE_ProjectTaskImportHelpers::isClientUser($connect, $piece)) {
                    $cIdsList[] = $piece;
                }
            }
        }
        if (!in_array($mainUserId, $cIdsList, true)) {
            array_unshift($cIdsList, $mainUserId);
        }
        $cIdsList = array_values(array_unique(array_filter($cIdsList)));
        $cIdsCsv = implode(',', $cIdsList);

        $staffRaw = trim($get($row, 'Assigned Staff'));
        if ($staffRaw === '') {
            $staffRaw = trim($get($row, 's_ids'));
        }
        [$sIds, $staffErrs] = Comon_IE_ProjectTaskImportHelpers::expandStaffAssigneeTokens($connect, $staffRaw);
        if ($sIds === '') {
            $msg = 'Row ' . $rowNum . ': Assigned Staff is required (user ids or group:ID)';
            if ($staffErrs !== []) {
                $msg .= ' (' . implode('; ', $staffErrs) . ')';
            }
            self::pushProjectPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $title, $msg);
            return ['action' => 'skip', 'error_line' => $msg];
        }

        $startRaw = trim($get($row, 'Start Date'));
        $endRaw = trim($get($row, 'End Date'));
        if ($startRaw === '' || $endRaw === '') {
            $err = 'Row ' . $rowNum . ': Start Date and End Date are required';
            self::pushProjectPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $title, $err);
            return ['action' => 'skip', 'error_line' => $err];
        }
        $start = Comon_IE_CsvUtilities::normalizeImportDateToYmd($startRaw);
        $end = Comon_IE_CsvUtilities::normalizeImportDateToYmd($endRaw);
        if ($start === null || $end === null) {
            $err = 'Row ' . $rowNum . ': Invalid Start Date or End Date (use Y-m-d, Y/m/d, or e.g. 5/1/2026)';
            self::pushProjectPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $title, $err);
            return ['action' => 'skip', 'error_line' => $err];
        }

        $budgetRaw = trim($get($row, 'Budget'));
        $budget = 0.0;
        if ($budgetRaw !== '') {
            if (!is_numeric($budgetRaw) || (float)$budgetRaw < 0) {
                $err = 'Row ' . $rowNum . ': Invalid budget';
                self::pushProjectPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $title, $err);
                return ['action' => 'skip', 'error_line' => $err];
            }
            $budget = (float)$budgetRaw;
        }

        $status = (int)trim($get($row, 'Status'));
        if ($status !== 0 && $status !== 1) {
            $status = 0;
        }
        $archive = (int)trim($get($row, 'Archive'));
        if ($archive !== 0 && $archive !== 1) {
            $archive = 0;
        }
        $trash = (int)trim($get($row, 'Trash'));
        if ($trash !== 0 && $trash !== 1) {
            $trash = 0;
        }
        $desc = $get($row, 'Project Description');
        if (!is_string($desc)) {
            $desc = '';
        }
        if (function_exists('sanitize_tinymce_content')) {
            $desc = sanitize_tinymce_content($desc);
        }

        if ($previewMode) {
            self::pushProjectPreviewRow(
                $previewMode,
                $previewRows,
                $previewLimit,
                $rowNum,
                $title,
                $dryRun ? 'Dry run' : 'Would create'
            );
            return ['action' => 'insert', 'error_line' => ''];
        }
        if ($dryRun) {
            return ['action' => 'insert', 'error_line' => ''];
        }

        require_once dirname(__DIR__) . '/projects.php';
        $project = new Projects();
        $project->project_title = $title;
        $project->project_desc = $desc;
        $project->budget = $budget;
        $project->status = $status;
        $project->archive = $archive;
        $project->trash = $trash;
        $project->start_time = $start;
        $project->end_time = $end;
        $project->c_id = (string)$mainUserId;
        $project->main_client_id = $mainUserId;
        $project->c_ids = $cIdsCsv;
        $project->s_ids = $sIds;

        $save = $project->save();
        if (!is_numeric($save) || (int)$save < 1) {
            $err = 'Row ' . $rowNum . ': Save failed';
            self::pushProjectPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $title, $err);
            return ['action' => 'skip', 'error_line' => $err];
        }
        $pid = (int)$project->p_id;
        if ($pid <= 0) {
            $err = 'Row ' . $rowNum . ': Missing project id after save';
            self::pushProjectPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $title, $err);
            return ['action' => 'skip', 'error_line' => $err];
        }

        self::saveProjectCustomFieldsFromRow($connect, $row, $get, $pid);
        return ['action' => 'insert', 'error_line' => ''];
    }

    private static function saveProjectCustomFieldsFromRow(mysqli $connect, array $row, callable $get, int $projectId): void
    {
        $cfRes = mysqli_query(
            $connect,
            "SELECT id, field_type FROM custom_fields WHERE entity_type = 'project' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC"
        );
        if (!$cfRes) {
            return;
        }
        $posted = [];
        while ($cf = mysqli_fetch_assoc($cfRes)) {
            $key = 'CustomField_' . $cf['id'];
            $val = $get($row, $key);
            if ($val === '' || $val === null) {
                continue;
            }
            $fid = (int)$cf['id'];
            if (($cf['field_type'] ?? '') === 'multiple_select') {
                $values = array_filter(array_map('trim', preg_split('/[;,]/', (string)$val)));
                $posted[$fid] = array_values($values);
            } else {
                $posted[$fid] = $val;
            }
        }
        mysqli_free_result($cfRes);
        if ($posted === []) {
            return;
        }
        require_once dirname(__DIR__) . '/custom-fields/project_task_values.php';
        save_project_custom_field_values($connect, $projectId, $posted);
    }

    /**
     * @param array<string,mixed> $file
     */
    private static function prepareWorkFile(array $file): ?string
    {
        $tmp = $file['tmp_name'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return $tmp;
        }
        if ($ext === 'xlsx') {
            return Comon_IE_XlsxSimpleReader::writeTempCsv($tmp);
        }
        return null;
    }

    /**
     * @param array<string,mixed> $jobRow
     * @return array{ok:bool,processed:int,success:int,skipped:int,failed:int,updated:int,error?:string}
     */
    public static function processJobChunk(mysqli $connect, array $jobRow, float $deadlineUnix): array
    {
        $root = dirname(__DIR__, 2);
        $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $jobRow['file_path']);
        if (!is_readable($path)) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'missing file'];
        }
        $mapping = json_decode((string)$jobRow['mapping_json'], true) ?: [];
        $options = json_decode((string)$jobRow['options_json'], true) ?: [];
        $dup = (string)($options['duplicate_strategy'] ?? 'skip');
        $dry = !empty($jobRow['dry_run']);
        $offset = (int)$jobRow['processed_rows'];

        $fh = fopen($path, 'r');
        if (!$fh) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'open'];
        }
        [$headers, $headerMap] = Comon_IE_CsvUtilities::readHeadersFromHandle($fh);
        $skippedLines = 0;
        while ($skippedLines < $offset && fgetcsv($fh) !== false) {
            $skippedLines++;
        }
        $get = function (array $row, string $key) use ($mapping, $headerMap, $headers) {
            return Comon_IE_CsvUtilities::rowGet($row, $key, $mapping, $headerMap, $headers);
        };
        $processed = 0;
        $success = 0;
        $fail = 0;
        $skip = 0;
        $rowNum = $offset;
        $max = self::JOB_CHUNK_ROWS;
        while ($processed < $max && microtime(true) < $deadlineUnix) {
            $row = fgetcsv($fh);
            if ($row === false) {
                break;
            }
            $rowNum++;
            $processed++;
            if (!is_array($row) || count($row) === 0) {
                continue;
            }
            $preview = [];
            $r = self::processOneProjectRow(
                $connect,
                $row,
                $rowNum,
                $get,
                false,
                $dry,
                $dup,
                $preview,
                0
            );
            if ($r['action'] === 'skip' && !empty($r['duplicate_title'])) {
                $skip++;
            } elseif ($r['action'] === 'skip' && $r['error_line'] !== '') {
                $fail++;
                Comon_IE_ImportJobService::appendError($connect, (int)$jobRow['id'], $rowNum, $row, $r['error_line']);
            } elseif ($r['action'] === 'skip') {
                $skip++;
            } elseif ($r['action'] === 'insert') {
                $success++;
            }
        }
        fclose($fh);
        return ['ok' => true, 'processed' => $processed, 'success' => $success, 'skipped' => $skip, 'failed' => $fail, 'updated' => 0];
    }
}
