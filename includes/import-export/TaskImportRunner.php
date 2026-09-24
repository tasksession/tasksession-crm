<?php
/**
 * CSV/XLSX task import (project tasks and internal tasks project_id=0).
 *
 * Mapping targets: Task Title, Description, Project ID, Assigned To, Start Date, Due Date, Status
 * Assigned To: comma-separated user ids and/or group:GROUP_ID.
 */
class Comon_IE_TaskImportRunner
{
    public const SYNC_ROW_THRESHOLD = 250;
    public const JOB_CHUNK_ROWS = 150;

    private const ALLOWED_STATUS = ['todo', 'inprogress', 'review', 'done'];

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
            return ['status' => 'error', 'error' => 'Only administrators can import tasks'];
        }
        $previewMode = isset($post['preview']) && $post['preview'] === '1';
        $simulate = isset($post['simulate']) && $post['simulate'] === '1';
        $dryRun = $simulate || (!empty($post['dry_run']) && $post['dry_run'] === '1');

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
                'created_by' => (int)$session->userId,
            ];
            $jobId = Comon_IE_ImportJobService::createJob(
                $connect,
                (int)$session->userId,
                'task',
                null,
                'csv',
                (string)($file['name'] ?? 'import.csv'),
                $rel,
                $fieldMapping,
                $options,
                'create_only',
                'skip',
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
            $res = self::processOneTaskRow(
                $connect,
                $row,
                $rowNum,
                $get,
                $previewMode,
                $dryRun,
                (int)$session->userId,
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
                'task',
                null,
                (string)($file['name'] ?? 'import.csv'),
                'create_only',
                'skip',
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
     * @return array{action:string,error_line:string}
     */
    private static function processOneTaskRow(
        mysqli $connect,
        array $row,
        int $rowNum,
        callable $get,
        bool $previewMode,
        bool $dryRun,
        int $createdByUserId,
        array &$previewRows,
        int $previewLimit
    ): array {
        $title = mb_substr(trim($get($row, 'Task Title')), 0, 512);
        if ($title === '') {
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Task Title is required'];
        }

        $projectIdRaw = trim($get($row, 'Project ID'));
        $projectId = $projectIdRaw === '' ? 0 : (int)$projectIdRaw;
        if ($projectId < 0) {
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Invalid Project ID'];
        }
        if ($projectId > 0) {
            require_once dirname(__DIR__) . '/projects.php';
            $p = Projects::findByProjectId($projectId);
            if (!$p) {
                return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Project not found for Project ID'];
            }
        }

        $assignRaw = trim($get($row, 'Assigned To'));
        [$assignedCsv, $assignErrs] = Comon_IE_ProjectTaskImportHelpers::expandStaffAssigneeTokens($connect, $assignRaw);
        if ($assignedCsv === '') {
            $msg = 'Row ' . $rowNum . ': Assigned To is required';
            if ($assignErrs !== []) {
                $msg .= ' (' . implode('; ', $assignErrs) . ')';
            }
            return ['action' => 'skip', 'error_line' => $msg];
        }

        $startRaw = trim($get($row, 'Start Date'));
        $dueRaw = trim($get($row, 'Due Date'));
        if ($startRaw === '' || $dueRaw === '') {
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Start Date and Due Date are required'];
        }
        $start = Comon_IE_CsvUtilities::normalizeImportDateToYmd($startRaw);
        $due = Comon_IE_CsvUtilities::normalizeImportDateToYmd($dueRaw);
        if ($start === null || $due === null) {
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Invalid Start Date or Due Date (use Y-m-d, Y/m/d, or e.g. 5/1/2026)'];
        }

        $status = strtolower(trim($get($row, 'Status')));
        if ($status === '') {
            $status = 'todo';
        }
        if (!in_array($status, self::ALLOWED_STATUS, true)) {
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Invalid Status (use todo, inprogress, review, done)'];
        }

        $desc = (string)$get($row, 'Description');
        if (function_exists('sanitize_tinymce_content')) {
            $desc = sanitize_tinymce_content($desc);
        }

        if ($previewMode) {
            if (count($previewRows) < $previewLimit) {
                $previewRows[] = [
                    'row' => $rowNum,
                    'title' => $title,
                    'project_id' => $projectId,
                    'note' => $dryRun ? 'Dry run' : 'Would create',
                ];
            }
            return ['action' => 'insert', 'error_line' => ''];
        }
        if ($dryRun) {
            return ['action' => 'insert', 'error_line' => ''];
        }

        $statusEsc = $connect->real_escape_string($status);
        $posRes = mysqli_query(
            $connect,
            'SELECT COALESCE(MAX(position), -1) + 1 AS pos FROM tasks WHERE project_id = ' . (int)$projectId .
            " AND status = '" . $statusEsc . "'"
        );
        $position = 0;
        if ($posRes && ($pr = mysqli_fetch_assoc($posRes))) {
            $position = (int)($pr['pos'] ?? 0);
        }
        if ($posRes) {
            mysqli_free_result($posRes);
        }

        require_once dirname(__DIR__) . '/task.php';
        $task = new Task();
        $task->title = $title;
        $task->description = $desc;
        $task->project_id = $projectId;
        $task->status = $status;
        $task->assigned_to = $assignedCsv;
        $task->start_date = $start;
        $task->due_date = $due;
        $task->position = $position;
        $task->created_at = date('Y-m-d H:i:s');
        $task->user_id = $createdByUserId;
        $task->creator_id = $createdByUserId;

        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $prevSid = $_SESSION['userId'] ?? null;
        $_SESSION['userId'] = $createdByUserId;

        $ok = $task->save();

        if ($prevSid !== null) {
            $_SESSION['userId'] = $prevSid;
        } else {
            unset($_SESSION['userId']);
        }

        if ($ok !== true) {
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Task save failed'];
        }
        $tid = (int)$task->id;
        if ($tid <= 0) {
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Missing task id after save'];
        }

        self::saveTaskCustomFieldsFromRow($connect, $row, $get, $tid);
        return ['action' => 'insert', 'error_line' => ''];
    }

    private static function saveTaskCustomFieldsFromRow(mysqli $connect, array $row, callable $get, int $taskId): void
    {
        $cfRes = mysqli_query(
            $connect,
            "SELECT id, field_type FROM custom_fields WHERE entity_type = 'task' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC"
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
        save_task_custom_field_values($connect, $taskId, $posted);
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
        $options = json_decode((string)$jobRow['options_json'], true) ?: [];
        $createdBy = (int)($options['created_by'] ?? $jobRow['user_id']);

        $root = dirname(__DIR__, 2);
        $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $jobRow['file_path']);
        if (!is_readable($path)) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'missing file'];
        }
        $mapping = json_decode((string)$jobRow['mapping_json'], true) ?: [];
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
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }
        $prevSid = $_SESSION['userId'] ?? null;
        $_SESSION['userId'] = $createdBy;

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
            $r = self::processOneTaskRow(
                $connect,
                $row,
                $rowNum,
                $get,
                false,
                $dry,
                $createdBy,
                $preview,
                0
            );
            if ($r['action'] === 'skip' && $r['error_line'] !== '') {
                $fail++;
                Comon_IE_ImportJobService::appendError($connect, (int)$jobRow['id'], $rowNum, $row, $r['error_line']);
            } elseif ($r['action'] === 'skip') {
                $skip++;
            } elseif ($r['action'] === 'insert') {
                $success++;
            }
        }
        fclose($fh);

        if ($prevSid !== null) {
            $_SESSION['userId'] = $prevSid;
        } else {
            unset($_SESSION['userId']);
        }

        return ['ok' => true, 'processed' => $processed, 'success' => $success, 'skipped' => $skip, 'failed' => $fail, 'updated' => 0];
    }
}
