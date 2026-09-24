<?php
/**
 * Lead CSV/XLSX import — shared by web endpoint and background job worker.
 */
class Comon_IE_LeadImportRunner
{
    public const SYNC_ROW_THRESHOLD = 400;
    public const JOB_CHUNK_ROWS = 200;
    public const MAX_ROWS_GUARD = 100000;

    /**
     * @return array{status:string,error?:string,data?:array}
     */
    public static function processWebRequest(
        mysqli $connect,
        object $session,
        object $settings,
        array $post,
        array $files,
        bool $isAdmin,
        bool $isStaff
    ): array {
        $previewMode = isset($post['preview']) && $post['preview'] === '1';
        $simulate = isset($post['simulate']) && $post['simulate'] === '1';
        $uniqueEmail = isset($post['unique_email']) ? 1 : 0;
        $useCsvStatus = isset($post['use_csv_status']) && $post['use_csv_status'] === '1';
        $useCsvSource = isset($post['use_csv_source']) && $post['use_csv_source'] === '1';

        $fallbackStatusId = isset($post['fallback_status_id']) ? (int)$post['fallback_status_id'] : 0;
        $fallbackSourceId = isset($post['fallback_source_id']) ? (int)$post['fallback_source_id'] : 0;
        $fallbackAssignedTo = isset($post['fallback_assigned_to']) ? (int)$post['fallback_assigned_to'] : 0;
        if ($fallbackAssignedTo <= 0) {
            $fallbackAssignedTo = null;
        } else {
            $fallbackAssignedTo = (string)$fallbackAssignedTo;
        }
        if ($isStaff && $fallbackAssignedTo === null) {
            $fallbackAssignedTo = (string)$session->userId;
        }

        if ($fallbackStatusId <= 0 || $fallbackSourceId <= 0) {
            return ['status' => 'error', 'error' => 'Fallback status/source is required'];
        }

        if (!isset($files['csv_file']) || empty($files['csv_file']['tmp_name'])) {
            return ['status' => 'error', 'error' => 'CSV file is required'];
        }

        $file = $files['csv_file'];
        $workPath = self::prepareWorkFile($file);
        if ($workPath === null) {
            return ['status' => 'error', 'error' => 'Failed to prepare file (invalid XLSX or CSV)'];
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
        if (!$previewMode && !$simulate && $totalDataRows > self::SYNC_ROW_THRESHOLD) {
            $rel = 'storage/import_jobs/' . bin2hex(random_bytes(12)) . '.csv';
            $dest = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!@copy($workPath, $dest)) {
                if ($cleanupWork) {
                    @unlink($workPath);
                }
                return ['status' => 'error', 'error' => 'Could not store import file for background processing'];
            }
            $mapping = $fieldMapping;
            $options = [
                'fallback_status_id' => $fallbackStatusId,
                'fallback_source_id' => $fallbackSourceId,
                'fallback_assigned_to' => $fallbackAssignedTo,
                'unique_email' => $uniqueEmail,
                'use_csv_status' => $useCsvStatus,
                'use_csv_source' => $useCsvSource,
                'created_by' => (int)$session->userId,
                'is_admin_runner' => $isAdmin,
            ];
            $importMode = isset($post['import_mode']) ? (string)$post['import_mode'] : 'create_only';
            $dup = isset($post['duplicate_strategy']) ? (string)$post['duplicate_strategy'] : 'skip';
            $dry = isset($post['dry_run']) && $post['dry_run'] === '1';
            $jobId = Comon_IE_ImportJobService::createJob(
                $connect,
                (int)$session->userId,
                'lead',
                null,
                'csv',
                (string)($file['name'] ?? 'import.csv'),
                $rel,
                $mapping,
                $options,
                $importMode,
                $dup,
                $dry,
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
                    'message' => 'Import queued. It will complete within a few minutes.',
                ],
            ];
        }

        $ctx = self::buildContext(
            $connect,
            $settings,
            (int)$session->userId,
            $fieldMapping,
            $fallbackStatusId,
            $fallbackSourceId,
            $fallbackAssignedTo,
            $uniqueEmail,
            $useCsvStatus,
            $useCsvSource,
            $previewMode,
            $simulate
        );

        $result = self::runOverCsvPath($connect, $workPath, $ctx);
        if ($cleanupWork) {
            @unlink($workPath);
        }
        if ($result['status'] === 'ok' && !$previewMode && !$simulate && $totalDataRows > 0) {
            $d = $result['data'];
            $ins = (int)($d['inserted'] ?? 0);
            $sk = (int)($d['skipped'] ?? 0);
            $errList = $d['errors'] ?? [];
            $failed = is_array($errList) ? count($errList) : 0;
            Comon_IE_ImportJobService::recordCompletedSyncImport(
                $connect,
                (int)$session->userId,
                'lead',
                null,
                (string)($file['name'] ?? 'import.csv'),
                isset($post['import_mode']) ? (string)$post['import_mode'] : 'create_only',
                isset($post['duplicate_strategy']) ? (string)$post['duplicate_strategy'] : 'skip',
                $totalDataRows,
                $ins,
                $failed,
                0,
                $sk
            );
        }
        return $result;
    }

    /**
     * @param array<string,mixed> $jobRow import_jobs row
     */
    public static function processJobChunk(mysqli $connect, array $jobRow, object $settings, int $offset, int $maxRows, float $deadlineUnix): array
    {
        $root = dirname(__DIR__, 2);
        $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $jobRow['file_path']);
        if (!is_readable($path)) {
            return ['ok' => false, 'error' => 'Import file missing'];
        }
        $mapping = json_decode((string)$jobRow['mapping_json'], true) ?: [];
        $options = json_decode((string)$jobRow['options_json'], true) ?: [];
        $ctx = self::buildContext(
            $connect,
            $settings,
            (int)($options['created_by'] ?? $jobRow['user_id']),
            $mapping,
            (int)($options['fallback_status_id'] ?? 0),
            (int)($options['fallback_source_id'] ?? 0),
            $options['fallback_assigned_to'] ?? null,
            !empty($options['unique_email']) ? 1 : 0,
            !empty($options['use_csv_status']),
            !empty($options['use_csv_source']),
            false,
            !empty($jobRow['dry_run'])
        );
        return self::runChunkFromPath($connect, $path, $ctx, $offset, $maxRows, $deadlineUnix);
    }

    /**
     * @param array<string,mixed> $file $_FILES['csv_file']
     */
    private static function prepareWorkFile(array $file): ?string
    {
        $tmp = $file['tmp_name'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'csv') {
            return $tmp;
        }
        if ($ext === 'xlsx') {
            $csv = Comon_IE_XlsxSimpleReader::writeTempCsv($tmp);
            return $csv;
        }
        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildContext(
        mysqli $connect,
        object $settings,
        int $createdBy,
        array $fieldMapping,
        int $fallbackStatusId,
        int $fallbackSourceId,
        $fallbackAssignedTo,
        int $uniqueEmail,
        bool $useCsvStatus,
        bool $useCsvSource,
        bool $previewMode,
        bool $simulate
    ): array {
        $statusNameToId = [];
        $stmtStatus = $connect->prepare('SELECT id, name FROM lead_statuses');
        if ($stmtStatus) {
            $stmtStatus->execute();
            $res = $stmtStatus->get_result();
            while ($r = $res->fetch_assoc()) {
                $statusNameToId[strtolower(trim($r['name']))] = (int)$r['id'];
            }
            $stmtStatus->close();
        }
        $sourceNameToId = [];
        $stmtSource = $connect->prepare('SELECT id, name FROM lead_sources');
        if ($stmtSource) {
            $stmtSource->execute();
            $res = $stmtSource->get_result();
            while ($r = $res->fetch_assoc()) {
                $sourceNameToId[strtolower(trim($r['name']))] = (int)$r['id'];
            }
            $stmtSource->close();
        }
        return [
            'settings' => $settings,
            'createdBy' => $createdBy,
            'fieldMapping' => $fieldMapping,
            'fallbackStatusId' => $fallbackStatusId,
            'fallbackSourceId' => $fallbackSourceId,
            'fallbackAssignedTo' => $fallbackAssignedTo,
            'uniqueEmail' => $uniqueEmail,
            'useCsvStatus' => $useCsvStatus,
            'useCsvSource' => $useCsvSource,
            'previewMode' => $previewMode,
            'simulate' => $simulate,
            'statusNameToId' => $statusNameToId,
            'sourceNameToId' => $sourceNameToId,
        ];
    }

    /**
     * @param array<string,mixed> $ctx
     * @return array{status:string,error?:string,data?:array}
     */
    private static function runOverCsvPath(mysqli $connect, string $workPath, array $ctx): array
    {
        $fh = fopen($workPath, 'r');
        if (!$fh) {
            return ['status' => 'error', 'error' => 'Failed to open file'];
        }
        [$headers, $headerMap] = Comon_IE_CsvUtilities::readHeadersFromHandle($fh);
        if ($headers === []) {
            fclose($fh);
            return ['status' => 'error', 'error' => 'Invalid CSV header'];
        }
        $inserted = 0;
        $skipped = 0;
        $errors = [];
        $previewRows = [];
        $rowNum = 0;
        $previewLimit = 10;
        $stmtInsert = $connect->prepare('INSERT INTO leads (status_id, source_id, priority, lead_value, currency, assigned_to, name, email, phone, website, company, position, description, country, zip, city, state, address, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmtInsert) {
            fclose($fh);
            return ['status' => 'error', 'error' => 'Database prepare failed'];
        }
        $get = function (array $row, string $key) use ($ctx, $headerMap, $headers) {
            return Comon_IE_CsvUtilities::rowGet($row, $key, $ctx['fieldMapping'], $headerMap, $headers);
        };
        while (($row = fgetcsv($fh)) !== false) {
            if ($rowNum >= self::MAX_ROWS_GUARD) {
                $errors[] = 'Maximum row limit reached. Split the file.';
                break;
            }
            if (!is_array($row) || count($row) === 0) {
                continue;
            }
            $rowNum++;
            $one = self::processOneLeadRow(
                $connect,
                $ctx,
                $row,
                $rowNum,
                $get,
                $stmtInsert,
                $previewRows,
                $previewLimit
            );
            if ($one['skip_empty_name']) {
                $skipped++;
                continue;
            }
            if ($one['skip_dup']) {
                $skipped++;
                continue;
            }
            if ($ctx['previewMode'] || $ctx['simulate']) {
                $inserted++;
                continue;
            }
            if ($one['inserted']) {
                $inserted++;
            } else {
                $skipped++;
                if ($one['error'] !== '') {
                    $errors[] = $one['error'];
                }
            }
        }
        $stmtInsert->close();
        fclose($fh);
        $data = [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
            'simulate' => $ctx['simulate'] ? 1 : 0,
        ];
        if ($ctx['previewMode']) {
            $data['preview'] = $previewRows;
            $data['total_rows'] = $rowNum;
        }
        return ['status' => 'ok', 'data' => $data];
    }

    /**
     * @param array<string,mixed> $ctx
     * @return array{ok:bool,processed:int,success:int,skipped:int,failed:int,updated:int,error?:string}
     */
    private static function runChunkFromPath(
        mysqli $connect,
        string $workPath,
        array $ctx,
        int $skipDataRows,
        int $maxRows,
        float $deadlineUnix
    ): array {
        $fh = fopen($workPath, 'r');
        if (!$fh) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'open fail'];
        }
        [$headers, $headerMap] = Comon_IE_CsvUtilities::readHeadersFromHandle($fh);
        if ($headers === []) {
            fclose($fh);
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'bad header'];
        }
        $skippedLines = 0;
        while ($skippedLines < $skipDataRows && fgetcsv($fh) !== false) {
            $skippedLines++;
        }
        $get = function (array $row, string $key) use ($ctx, $headerMap, $headers) {
            return Comon_IE_CsvUtilities::rowGet($row, $key, $ctx['fieldMapping'], $headerMap, $headers);
        };
        $stmtInsert = $connect->prepare('INSERT INTO leads (status_id, source_id, priority, lead_value, currency, assigned_to, name, email, phone, website, company, position, description, country, zip, city, state, address, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmtInsert) {
            fclose($fh);
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'prepare'];
        }
        $processed = 0;
        $success = 0;
        $skipped = 0;
        $failed = 0;
        $updated = 0;
        $rowNum = $skipDataRows;
        while ($processed < $maxRows && microtime(true) < $deadlineUnix) {
            $row = fgetcsv($fh);
            if ($row === false) {
                break;
            }
            $rowNum++;
            $processed++;
            if (!is_array($row) || count($row) === 0) {
                continue;
            }
            $previewRows = [];
            $one = self::processOneLeadRow(
                $connect,
                $ctx,
                $row,
                $rowNum,
                $get,
                $stmtInsert,
                $previewRows,
                0
            );
            if ($one['skip_empty_name']) {
                $skipped++;
                continue;
            }
            if ($one['skip_dup']) {
                $skipped++;
                continue;
            }
            if ($ctx['simulate']) {
                $success++;
                continue;
            }
            if ($one['inserted']) {
                $success++;
            } else {
                $failed++;
            }
        }
        $stmtInsert->close();
        fclose($fh);
        return ['ok' => true, 'processed' => $processed, 'success' => $success, 'skipped' => $skipped, 'failed' => $failed, 'updated' => $updated];
    }

    /**
     * @param callable(array,string):string $get
     * @param array<int,array<string,mixed>> $previewRows
     * @return array{skip_empty_name:bool,skip_dup:bool,inserted:bool,error:string}
     */
    private static function processOneLeadRow(
        mysqli $connect,
        array $ctx,
        array $row,
        int $rowNum,
        callable $get,
        mysqli_stmt $stmtInsert,
        array &$previewRows,
        int $previewLimit
    ): array {
        /** @var object $settings */
        $settings = $ctx['settings'];
        $name = $get($row, 'Name');
        if ($name === '') {
            return ['skip_empty_name' => true, 'skip_dup' => false, 'inserted' => false, 'error' => ''];
        }
        $name = mb_substr(trim($name), 0, 255);
        $email = $get($row, 'Email');
        if ($email !== '') {
            $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = '';
            }
            $email = mb_substr($email, 0, 255);
        }
        $phone = mb_substr(trim($get($row, 'Phone')), 0, 50);
        $website = $get($row, 'Website');
        if ($website !== '') {
            $website = filter_var(trim($website), FILTER_SANITIZE_URL);
            if (!filter_var($website, FILTER_VALIDATE_URL)) {
                $website = '';
            }
            $website = mb_substr($website, 0, 255);
        }
        $company = mb_substr(trim($get($row, 'Company')), 0, 255);
        $position = mb_substr(trim($get($row, 'Position')), 0, 255);
        $description = mb_substr(trim($get($row, 'Description')), 0, 5000);
        $country = mb_substr(trim($get($row, 'Country')), 0, 100);
        $priority = mb_substr(trim($get($row, 'Priority')), 0, 50);
        $leadValue = $get($row, 'Lead Value');
        if ($leadValue !== '' && !is_numeric($leadValue)) {
            $leadValue = '';
        }
        $currencyCode = mb_substr(strtoupper(trim($get($row, 'Currency'))), 0, 10);
        $zip = mb_substr(trim($get($row, 'Zip')), 0, 20);
        $city = mb_substr(trim($get($row, 'City')), 0, 100);
        $state = mb_substr(trim($get($row, 'State')), 0, 100);
        $address = mb_substr(trim($get($row, 'Address')), 0, 500);
        $notes = mb_substr(trim($get($row, 'Notes')), 0, 5000);
        $statusName = $get($row, 'Status');
        $sourceName = $get($row, 'Source');
        $statusId = (int)$ctx['fallbackStatusId'];
        if ($ctx['useCsvStatus'] && $statusName !== '') {
            $key = strtolower(trim($statusName));
            if (isset($ctx['statusNameToId'][$key])) {
                $statusId = (int)$ctx['statusNameToId'][$key];
            }
        }
        $sourceId = (int)$ctx['fallbackSourceId'];
        if ($ctx['useCsvSource'] && $sourceName !== '') {
            $key = strtolower(trim($sourceName));
            if (isset($ctx['sourceNameToId'][$key])) {
                $sourceId = (int)$ctx['sourceNameToId'][$key];
            }
        }
        $currency = null;
        if ($currencyCode !== '') {
            $currencySymbols = $settings->currency_symbols ?? [];
            if (isset($currencySymbols[$currencyCode])) {
                $currency = $currencySymbols[$currencyCode];
            } else {
                $currency = $currencyCode;
            }
        }
        if ($ctx['previewMode'] && $previewLimit > 0 && count($previewRows) < $previewLimit) {
            $previewRows[] = [
                'row' => $rowNum,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'company' => $company,
                'status' => $statusName ?: 'Fallback',
                'source' => $sourceName ?: 'Fallback',
                'priority' => $priority,
                'currency' => $currencyCode,
                'lead_value' => $leadValue,
            ];
        }
        if ($ctx['uniqueEmail'] && $email !== '') {
            $stmtChk = $connect->prepare('SELECT id FROM leads WHERE email=? LIMIT 1');
            $stmtChk->bind_param('s', $email);
            $stmtChk->execute();
            $resChk = $stmtChk->get_result();
            $exists = $resChk && $resChk->num_rows > 0;
            $stmtChk->close();
            if ($exists) {
                if ($ctx['previewMode'] && count($previewRows) > 0) {
                    $previewRows[count($previewRows) - 1]['error'] = 'Email already exists';
                }
                return ['skip_empty_name' => false, 'skip_dup' => true, 'inserted' => false, 'error' => ''];
            }
        }
        if ($ctx['previewMode'] || $ctx['simulate']) {
            return ['skip_empty_name' => false, 'skip_dup' => false, 'inserted' => true, 'error' => ''];
        }
        $assignedTo = $ctx['fallbackAssignedTo'];
        $nullEmail = ($email === '');
        if ($nullEmail) {
            $email = null;
        }
        $createdBy = (int)$ctx['createdBy'];
        $stmtInsert->bind_param(
            'iisssssssssssssssssi',
            $statusId,
            $sourceId,
            $priority,
            $leadValue,
            $currency,
            $assignedTo,
            $name,
            $email,
            $phone,
            $website,
            $company,
            $position,
            $description,
            $country,
            $zip,
            $city,
            $state,
            $address,
            $notes,
            $createdBy
        );
        $ok = $stmtInsert->execute();
        if ($ok) {
            $newId = $stmtInsert->insert_id;
            self::importLeadCustomFields($connect, $row, $get, (int)$newId);
            return ['skip_empty_name' => false, 'skip_dup' => false, 'inserted' => true, 'error' => ''];
        }
        return ['skip_empty_name' => false, 'skip_dup' => false, 'inserted' => false, 'error' => 'Row ' . $rowNum . ': Failed to insert'];
    }

    private static function importLeadCustomFields(mysqli $connect, array $row, callable $get, int $newId): void
    {
        if ($newId <= 0) {
            return;
        }
        $cfRes = mysqli_query($connect, "SELECT * FROM custom_fields WHERE entity_type = 'lead' ORDER BY sort_order ASC, id ASC");
        if (!$cfRes) {
            return;
        }
        $stmtCustomField = $connect->prepare('INSERT INTO lead_custom_field_values (lead_id, custom_field_id, field_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)');
        if (!$stmtCustomField) {
            return;
        }
        while ($cfRow = mysqli_fetch_assoc($cfRes)) {
            $cfKey = 'CustomField_' . $cfRow['id'];
            $cfValue = $get($row, $cfKey);
            if ($cfValue === '') {
                continue;
            }
            $fieldId = (int)$cfRow['id'];
            $fieldType = $cfRow['field_type'];
            if ($fieldType === 'multiple_select') {
                $values = array_filter(array_map('trim', preg_split('/[;,]/', $cfValue)));
                $valueToStore = json_encode(array_values($values));
            } elseif ($fieldType === 'checkbox') {
                $valueToStore = (strtolower($cfValue) === 'yes' || $cfValue === '1' || strtolower($cfValue) === 'true') ? '1' : '0';
            } else {
                $valueToStore = trim($cfValue);
            }
            if ($valueToStore !== '') {
                $stmtCustomField->bind_param('iis', $newId, $fieldId, $valueToStore);
                $stmtCustomField->execute();
            }
        }
        $stmtCustomField->close();
    }
}
