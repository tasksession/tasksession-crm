<?php
/**
 * Persistence for import_jobs / import_job_errors.
 */
class Comon_IE_ImportJobService
{
    public static function storageDir(): string
    {
        $root = dirname(__DIR__, 2);
        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'import_jobs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /** Relative path from project root for DB storage */
    public static function relativePath(int $jobId, string $ext): string
    {
        return 'storage/import_jobs/' . $jobId . '.' . ltrim($ext, '.');
    }

    public static function createJob(
        mysqli $connect,
        int $userId,
        string $moduleName,
        ?string $targetUserType,
        string $sourceType,
        string $originalFilename,
        string $relativeFilePath,
        array $mapping,
        array $options,
        string $importMode,
        string $duplicateStrategy,
        bool $dryRun,
        int $totalRows
    ): int {
        $mappingJson = json_encode($mapping, JSON_UNESCAPED_UNICODE);
        $optionsJson = json_encode($options, JSON_UNESCAPED_UNICODE);
        $dry = $dryRun ? 1 : 0;
        $stmt = $connect->prepare(
            'INSERT INTO import_jobs (user_id, module_name, target_user_type, source_type, original_filename, file_path, mapping_json, options_json, import_mode, duplicate_strategy, dry_run, status, total_rows)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?)'
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param(
            'isssssssssii',
            $userId,
            $moduleName,
            $targetUserType,
            $sourceType,
            $originalFilename,
            $relativeFilePath,
            $mappingJson,
            $optionsJson,
            $importMode,
            $duplicateStrategy,
            $dry,
            $totalRows
        );
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Persist a completed import that ran synchronously (under row threshold), so Import history lists it.
     */
    public static function recordCompletedSyncImport(
        mysqli $connect,
        int $userId,
        string $moduleName,
        ?string $targetUserType,
        string $originalFilename,
        string $importMode,
        string $duplicateStrategy,
        int $totalRows,
        int $successRows,
        int $failedRows,
        int $updatedRows,
        int $skippedRows
    ): void {
        $moduleName = mb_substr(preg_replace('/[^a-z0-9_\-]/i', '', $moduleName), 0, 64) ?: 'unknown';
        $orig = mb_substr($originalFilename, 0, 500);
        $origEsc = $connect->real_escape_string($orig);
        $modeEsc = $connect->real_escape_string(mb_substr($importMode, 0, 32));
        $dupEsc = $connect->real_escape_string(mb_substr($duplicateStrategy, 0, 32));
        $tt = $targetUserType !== null && $targetUserType !== ''
            ? "'" . $connect->real_escape_string(mb_substr($targetUserType, 0, 16)) . "'"
            : 'NULL';
        $uid = (int)$userId;
        $tr = max(0, (int)$totalRows);
        $sr = max(0, (int)$successRows);
        $fr = max(0, (int)$failedRows);
        $ur = max(0, (int)$updatedRows);
        $sk = max(0, (int)$skippedRows);
        $sql = 'INSERT INTO import_jobs (user_id, module_name, target_user_type, source_type, original_filename, file_path, mapping_json, options_json, import_mode, duplicate_strategy, dry_run, status, total_rows, processed_rows, success_rows, failed_rows, updated_rows, skipped_rows, started_at, finished_at)
            VALUES (' . $uid . ', "' . $connect->real_escape_string($moduleName) . '", ' . $tt . ', "csv", "' . $origEsc . '", "", "{}", "{\"sync\":1}", "' . $modeEsc . '", "' . $dupEsc . '", 0, "completed", ' . $tr . ', ' . $tr . ', ' . $sr . ', ' . $fr . ', ' . $ur . ', ' . $sk . ', NOW(), NOW())';
        @$connect->query($sql);
    }

    public static function getById(mysqli $connect, int $jobId): ?array
    {
        $stmt = $connect->prepare('SELECT * FROM import_jobs WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ?: null;
    }

    public static function markProcessing(mysqli $connect, int $jobId): void
    {
        $connect->query(
            'UPDATE import_jobs SET status = "processing", started_at = COALESCE(started_at, NOW()), updated_at = NOW() WHERE id = ' . (int)$jobId
        );
    }

    /**
     * Merge keys into options_json (for chat import cursors, etc.).
     */
    public static function mergeOptionsJson(mysqli $connect, int $jobId, array $patch): void
    {
        $job = self::getById($connect, $jobId);
        if (!$job) {
            return;
        }
        $cur = json_decode((string)($job['options_json'] ?? '{}'), true);
        if (!is_array($cur)) {
            $cur = [];
        }
        $merged = array_merge($cur, $patch);
        $json = $connect->real_escape_string(json_encode($merged, JSON_UNESCAPED_UNICODE));
        $connect->query('UPDATE import_jobs SET options_json = "' . $json . '", updated_at = NOW() WHERE id = ' . (int)$jobId);
    }

    public static function updateProgress(
        mysqli $connect,
        int $jobId,
        int $processedDelta,
        int $successDelta,
        int $failedDelta,
        int $updatedDelta,
        int $skippedDelta
    ): void {
        $jid = (int)$jobId;
        $sql = "UPDATE import_jobs SET
            processed_rows = processed_rows + " . (int)$processedDelta . ",
            success_rows = success_rows + " . (int)$successDelta . ",
            failed_rows = failed_rows + " . (int)$failedDelta . ",
            updated_rows = updated_rows + " . (int)$updatedDelta . ",
            skipped_rows = skipped_rows + " . (int)$skippedDelta . ",
            updated_at = NOW()
            WHERE id = " . $jid;
        $connect->query($sql);
    }

    public static function markCompleted(mysqli $connect, int $jobId, ?string $errorFilePath = null): void
    {
        $jid = (int)$jobId;
        if ($errorFilePath !== null && $errorFilePath !== '') {
            $path = $connect->real_escape_string($errorFilePath);
            $connect->query(
                'UPDATE import_jobs SET status = "completed", finished_at = NOW(), error_file_path = "' . $path . '", updated_at = NOW() WHERE id = ' . $jid
            );
        } else {
            $connect->query(
                'UPDATE import_jobs SET status = "completed", finished_at = NOW(), updated_at = NOW() WHERE id = ' . $jid
            );
        }
    }

    public static function markFailed(mysqli $connect, int $jobId, string $message): void
    {
        $msg = $connect->real_escape_string(mb_substr($message, 0, 2000));
        $connect->query(
            'UPDATE import_jobs SET status = "failed", finished_at = NOW(), last_error = "' . $msg . '", updated_at = NOW() WHERE id = ' . (int)$jobId
        );
    }

    public static function appendError(mysqli $connect, int $jobId, int $rowNum, array $rawRow, string $message): void
    {
        $json = $connect->real_escape_string(json_encode($rawRow, JSON_UNESCAPED_UNICODE));
        $msg = $connect->real_escape_string(mb_substr($message, 0, 1000));
        $connect->query(
            'INSERT INTO import_job_errors (import_job_id, row_number, raw_data_json, error_message) VALUES (' .
            (int)$jobId . ', ' . (int)$rowNum . ', "' . $json . '", "' . $msg . '")'
        );
    }

    /** Claim next pending job (single runner). */
    public static function claimNextPending(mysqli $connect): ?array
    {
        if (!$connect->begin_transaction()) {
            return null;
        }
        try {
            $res = $connect->query('SELECT id FROM import_jobs WHERE status = "pending" ORDER BY id ASC LIMIT 1 FOR UPDATE');
            if (!$res || $res->num_rows === 0) {
                $connect->rollback();
                return null;
            }
            $row = $res->fetch_assoc();
            $id = (int)$row['id'];
            $connect->query(
                'UPDATE import_jobs SET status = "processing", started_at = COALESCE(started_at, NOW()), updated_at = NOW() WHERE id = ' . $id
            );
            $connect->commit();
            return self::getById($connect, $id);
        } catch (Throwable $e) {
            $connect->rollback();
            return null;
        }
    }

    public static function listForUser(mysqli $connect, int $userId, bool $isAdmin, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $uid = (int)$userId;
        if ($isAdmin) {
            $sql = 'SELECT * FROM import_jobs ORDER BY id DESC LIMIT ' . $limit;
            $res = $connect->query($sql);
        } else {
            $stmt = $connect->prepare('SELECT * FROM import_jobs WHERE user_id = ? ORDER BY id DESC LIMIT ' . $limit);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $res = $stmt->get_result();
        }
        $out = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $out[] = $r;
            }
        }
        if (isset($stmt)) {
            $stmt->close();
        }
        return $out;
    }
}
