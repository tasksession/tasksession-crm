<?php
/**
 * Processes one pending import job for a limited time (cron or manual).
 */
class Comon_IE_ImportBatchWorker
{
    public const MAX_SECONDS = 22;

    public static function runOnce(mysqli $connect): array
    {
        $job = Comon_IE_ImportJobService::claimNextPending($connect);
        if (!$job) {
            return ['ran' => false, 'message' => 'no pending jobs'];
        }
        $jobId = (int)$job['id'];
        $module = (string)$job['module_name'];
        $settings = settings::findById(1);
        $deadline = microtime(true) + self::MAX_SECONDS;

        Comon_IE_ImportJobService::markProcessing($connect, $jobId);

        try {
            if ($module === 'lead') {
                if (!$settings || empty($settings->module_lead_board)) {
                    Comon_IE_ImportJobService::markFailed($connect, $jobId, 'Lead module disabled');
                    return ['ran' => true, 'job_id' => $jobId, 'failed' => true];
                }
                self::runLeadJobLoop($connect, $jobId, $settings, $deadline);
            } elseif ($module === 'user') {
                self::runUserJobLoop($connect, $jobId, $deadline);
            } elseif ($module === 'project') {
                self::runProjectJobLoop($connect, $jobId, $deadline);
            } elseif ($module === 'task') {
                self::runTaskJobLoop($connect, $jobId, $deadline);
            } elseif ($module === 'chat') {
                self::runChatJobLoop($connect, $jobId, $deadline);
            } elseif ($module === 'invoice') {
                if (!$settings || empty($settings->module_invoices)) {
                    Comon_IE_ImportJobService::markFailed($connect, $jobId, 'Invoices module disabled');
                    return ['ran' => true, 'job_id' => $jobId, 'failed' => true];
                }
                self::runInvoiceJobLoop($connect, $jobId, $deadline);
            } elseif ($module === 'notes') {
                self::runNotesJobLoop($connect, $jobId, $deadline);
            } elseif ($module === 'company') {
                self::runCompanyJobLoop($connect, $jobId, $deadline);
            } else {
                Comon_IE_ImportJobService::markFailed($connect, $jobId, 'Unknown module');
                return ['ran' => true, 'job_id' => $jobId, 'failed' => true];
            }
        } catch (Throwable $e) {
            Comon_IE_ImportJobService::markFailed($connect, $jobId, $e->getMessage());
            return ['ran' => true, 'job_id' => $jobId, 'failed' => true];
        }

        $fresh = Comon_IE_ImportJobService::getById($connect, $jobId);
        if ($fresh && ($fresh['status'] ?? '') !== 'failed') {
            $processed = (int)$fresh['processed_rows'];
            $total = (int)$fresh['total_rows'];
            if ($total === 0 || ($total > 0 && $processed >= $total)) {
                Comon_IE_ImportJobService::markCompleted($connect, $jobId, $fresh['error_file_path'] ?? null);
            } else {
                $connect->query('UPDATE import_jobs SET status = "pending", updated_at = NOW() WHERE id = ' . (int)$jobId);
            }
        }
        return ['ran' => true, 'job_id' => $jobId];
    }

    private static function runProjectJobLoop(mysqli $connect, int $jobId, float $deadline): void
    {
        while (microtime(true) < $deadline) {
            $job = Comon_IE_ImportJobService::getById($connect, $jobId);
            if (!$job) {
                return;
            }
            $offset = (int)$job['processed_rows'];
            $total = (int)$job['total_rows'];
            if ($total > 0 && $offset >= $total) {
                return;
            }
            $chunk = Comon_IE_ProjectImportRunner::processJobChunk($connect, $job, $deadline);
            if (!$chunk['ok']) {
                Comon_IE_ImportJobService::markFailed($connect, $jobId, $chunk['error'] ?? 'project chunk failed');
                return;
            }
            if ($chunk['processed'] === 0) {
                return;
            }
            Comon_IE_ImportJobService::updateProgress(
                $connect,
                $jobId,
                $chunk['processed'],
                $chunk['success'],
                $chunk['failed'],
                $chunk['updated'],
                $chunk['skipped']
            );
        }
    }

    private static function runTaskJobLoop(mysqli $connect, int $jobId, float $deadline): void
    {
        while (microtime(true) < $deadline) {
            $job = Comon_IE_ImportJobService::getById($connect, $jobId);
            if (!$job) {
                return;
            }
            $offset = (int)$job['processed_rows'];
            $total = (int)$job['total_rows'];
            if ($total > 0 && $offset >= $total) {
                return;
            }
            $chunk = Comon_IE_TaskImportRunner::processJobChunk($connect, $job, $deadline);
            if (!$chunk['ok']) {
                Comon_IE_ImportJobService::markFailed($connect, $jobId, $chunk['error'] ?? 'task chunk failed');
                return;
            }
            if ($chunk['processed'] === 0) {
                return;
            }
            Comon_IE_ImportJobService::updateProgress(
                $connect,
                $jobId,
                $chunk['processed'],
                $chunk['success'],
                $chunk['failed'],
                $chunk['updated'],
                $chunk['skipped']
            );
        }
    }

    private static function runLeadJobLoop(mysqli $connect, int $jobId, object $settings, float $deadline): void
    {
        while (microtime(true) < $deadline) {
            $job = Comon_IE_ImportJobService::getById($connect, $jobId);
            if (!$job) {
                return;
            }
            $offset = (int)$job['processed_rows'];
            $total = (int)$job['total_rows'];
            if ($total > 0 && $offset >= $total) {
                return;
            }
            $chunk = Comon_IE_LeadImportRunner::processJobChunk($connect, $job, $settings, $offset, Comon_IE_LeadImportRunner::JOB_CHUNK_ROWS, $deadline);
            if (!$chunk['ok']) {
                Comon_IE_ImportJobService::markFailed($connect, $jobId, $chunk['error'] ?? 'lead chunk failed');
                return;
            }
            if ($chunk['processed'] === 0) {
                return;
            }
            Comon_IE_ImportJobService::updateProgress(
                $connect,
                $jobId,
                $chunk['processed'],
                $chunk['success'],
                $chunk['failed'],
                $chunk['updated'],
                $chunk['skipped']
            );
        }
    }

    private static function runInvoiceJobLoop(mysqli $connect, int $jobId, float $deadline): void
    {
        while (microtime(true) < $deadline) {
            $job = Comon_IE_ImportJobService::getById($connect, $jobId);
            if (!$job) {
                return;
            }
            $offset = (int)$job['processed_rows'];
            $total = (int)$job['total_rows'];
            if ($total > 0 && $offset >= $total) {
                return;
            }
            $chunk = Comon_IE_InvoiceImportRunner::processJobChunk($connect, $job, $deadline);
            if (!$chunk['ok']) {
                Comon_IE_ImportJobService::markFailed($connect, $jobId, $chunk['error'] ?? 'invoice chunk failed');
                return;
            }
            if ($chunk['processed'] === 0) {
                return;
            }
            Comon_IE_ImportJobService::updateProgress(
                $connect,
                $jobId,
                $chunk['processed'],
                $chunk['success'],
                $chunk['failed'],
                $chunk['updated'],
                $chunk['skipped']
            );
        }
    }

    private static function runChatJobLoop(mysqli $connect, int $jobId, float $deadline): void
    {
        while (microtime(true) < $deadline) {
            $job = Comon_IE_ImportJobService::getById($connect, $jobId);
            if (!$job) {
                return;
            }
            $phase = (int)$job['processed_rows'];
            $total = (int)$job['total_rows'];
            if ($total > 0 && $phase >= $total) {
                return;
            }
            $chunk = Comon_IE_ChatImportRunner::processJobChunk($connect, $job, $deadline);
            if (!$chunk['ok']) {
                Comon_IE_ImportJobService::markFailed($connect, $jobId, $chunk['error'] ?? 'chat chunk failed');
                return;
            }
            if ($chunk['processed'] === 0) {
                return;
            }
            Comon_IE_ImportJobService::updateProgress(
                $connect,
                $jobId,
                $chunk['processed'],
                $chunk['success'],
                $chunk['failed'],
                $chunk['updated'],
                $chunk['skipped']
            );
        }
    }

    private static function runCompanyJobLoop(mysqli $connect, int $jobId, float $deadline): void
    {
        while (microtime(true) < $deadline) {
            $job = Comon_IE_ImportJobService::getById($connect, $jobId);
            if (!$job) {
                return;
            }
            $offset = (int)$job['processed_rows'];
            $total = (int)$job['total_rows'];
            if ($total > 0 && $offset >= $total) {
                return;
            }
            $chunk = Comon_IE_CompanyImportRunner::processJobChunk($connect, $job, $deadline);
            if (!$chunk['ok']) {
                Comon_IE_ImportJobService::markFailed($connect, $jobId, $chunk['error'] ?? 'company chunk failed');
                return;
            }
            if ($chunk['processed'] === 0) {
                return;
            }
            Comon_IE_ImportJobService::updateProgress(
                $connect,
                $jobId,
                $chunk['processed'],
                $chunk['success'],
                $chunk['failed'],
                $chunk['updated'],
                $chunk['skipped']
            );
        }
    }

    private static function runNotesJobLoop(mysqli $connect, int $jobId, float $deadline): void
    {
        while (microtime(true) < $deadline) {
            $job = Comon_IE_ImportJobService::getById($connect, $jobId);
            if (!$job) {
                return;
            }
            $offset = (int)$job['processed_rows'];
            $total = (int)$job['total_rows'];
            if ($total > 0 && $offset >= $total) {
                return;
            }
            $chunk = Comon_IE_NoteImportRunner::processJobChunk($connect, $job, $deadline);
            if (!$chunk['ok']) {
                Comon_IE_ImportJobService::markFailed($connect, $jobId, $chunk['error'] ?? 'notes chunk failed');
                return;
            }
            if ($chunk['processed'] === 0) {
                return;
            }
            Comon_IE_ImportJobService::updateProgress(
                $connect,
                $jobId,
                $chunk['processed'],
                $chunk['success'],
                $chunk['failed'],
                $chunk['updated'],
                $chunk['skipped']
            );
        }
    }

    private static function runUserJobLoop(mysqli $connect, int $jobId, float $deadline): void
    {
        while (microtime(true) < $deadline) {
            $job = Comon_IE_ImportJobService::getById($connect, $jobId);
            if (!$job) {
                return;
            }
            $offset = (int)$job['processed_rows'];
            $total = (int)$job['total_rows'];
            if ($total > 0 && $offset >= $total) {
                return;
            }
            $chunk = Comon_IE_UserImportRunner::processJobChunk($connect, $job, $deadline);
            if (!$chunk['ok']) {
                Comon_IE_ImportJobService::markFailed($connect, $jobId, $chunk['error'] ?? 'user chunk failed');
                return;
            }
            if ($chunk['processed'] === 0) {
                return;
            }
            Comon_IE_ImportJobService::updateProgress(
                $connect,
                $jobId,
                $chunk['processed'],
                $chunk['success'],
                $chunk['failed'],
                $chunk['updated'],
                $chunk['skipped']
            );
        }
    }
}
