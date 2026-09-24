<?php
/**
 * Single-CSV invoice import: each data row = one line item. Repeat the same External ID
 * and invoice header columns for every line belonging to the same invoice.
 *
 * Fields (map columns in wizard / match headers):
 *   External ID, Invoice Type, Project ID, Client ID, Title, Issue Date, Due Date, Release Date,
 *   Status, Currency, Sales Tax, Sales Tax Type, Discount, Discount Type, Memo, Footer, Bill From,
 *   Bill From Address, Created By, Description, Item Description, Rate, Quantity, Sort Order
 *
 * Not stored: external_email, notify (UI-only on add-invoice). Phase 1: non-recurring.
 *
 * Optional Client ID on Invoice Type "internal" (custom/external): links milestone.c_id when set
 * (same column as client invoices). First non-empty Client ID among rows sharing one External ID wins.
 */
class Comon_IE_InvoiceImportRunner
{
    public const SYNC_ROW_THRESHOLD = 200;
    public const JOB_CHUNK_ROWS = 150;

    /**
     * @return array{ext_order: list<string>, items_map: array<string, list<array>>, first_row: array<string, list<string>>, first_line: array<string, int>, client_id_hint: array<string, int>, errors: list<string>}|null
     */
    public static function loadSingleFileInvoiceData(string $path, array $fieldMapping): ?array
    {
        $fh = @fopen($path, 'r');
        if (!$fh) {
            return null;
        }
        [$headers, $headerMap] = Comon_IE_CsvUtilities::readHeadersFromHandle($fh);
        if ($headers === []) {
            fclose($fh);
            return null;
        }
        $get = function (array $row, string $key) use ($fieldMapping, $headerMap, $headers) {
            return Comon_IE_CsvUtilities::rowGet($row, $key, $fieldMapping, $headerMap, $headers);
        };
        $extOrder = [];
        $groups = [];
        $lineNo = 1;
        $errors = [];
        while (($row = fgetcsv($fh)) !== false) {
            $lineNo++;
            if (!is_array($row) || self::isEmptyDataRow($row)) {
                continue;
            }
            $ext = trim($get($row, 'External ID'));
            if ($ext === '') {
                $errors[] = 'Line ' . $lineNo . ': External ID is required on each row';
                continue;
            }
            if (!isset($groups[$ext])) {
                $groups[$ext] = [];
                $extOrder[] = $ext;
            }
            $groups[$ext][] = ['row' => $row, 'line' => $lineNo];
        }
        fclose($fh);
        $itemsMap = [];
        $firstRow = [];
        $firstLine = [];
        $clientIdHint = [];
        foreach ($extOrder as $ext) {
            $rows = $groups[$ext];
            $first = $rows[0];
            $firstRow[$ext] = $first['row'];
            $firstLine[$ext] = $first['line'];
            $hint = 0;
            foreach ($rows as $entry) {
                $cidScan = (int)trim($get($entry['row'], 'Client ID'));
                if ($cidScan > 0) {
                    $hint = $cidScan;
                    break;
                }
            }
            $clientIdHint[$ext] = $hint;
            $lines = [];
            foreach ($rows as $entry) {
                $r = $entry['row'];
                $qty = self::floatOr($get($r, 'Quantity'), 0.0);
                if ($qty <= 0) {
                    $qty = 1.0;
                }
                $lines[] = [
                    'description' => trim($get($r, 'Description')),
                    'item_description' => trim($get($r, 'Item Description')),
                    'rate' => self::floatOr($get($r, 'Rate'), 0.0),
                    'quantity' => $qty,
                    'sort_order' => (int)trim($get($r, 'Sort Order')),
                ];
            }
            $itemsMap[$ext] = $lines;
        }
        return [
            'ext_order' => $extOrder,
            'items_map' => $itemsMap,
            'first_row' => $firstRow,
            'first_line' => $firstLine,
            'client_id_hint' => $clientIdHint,
            'errors' => $errors,
        ];
    }

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
            return ['status' => 'error', 'error' => 'Only administrators can import invoices'];
        }
        $settings = settings::findById(1);
        if (!$settings || empty($settings->module_invoices)) {
            return ['status' => 'error', 'error' => 'Invoices module is disabled'];
        }

        $previewMode = isset($post['preview']) && $post['preview'] === '1';
        $simulate = isset($post['simulate']) && $post['simulate'] === '1';
        $dryRun = $simulate || (!empty($post['dry_run']) && $post['dry_run'] === '1');
        $duplicateStrategy = isset($post['duplicate_strategy']) ? (string)$post['duplicate_strategy'] : 'skip';

        if (!isset($files['csv_file']) || empty($files['csv_file']['tmp_name'])) {
            return ['status' => 'error', 'error' => 'CSV file is required'];
        }
        $invoiceFile = $files['csv_file'];
        $workInv = self::prepareWorkFile($invoiceFile);
        if ($workInv === null) {
            return ['status' => 'error', 'error' => 'Invalid CSV or XLSX file'];
        }
        $cleanupInv = ($workInv !== $invoiceFile['tmp_name']);

        $fieldMapping = [];
        if (isset($post['field_mapping']) && $post['field_mapping'] !== '') {
            $decoded = json_decode((string)$post['field_mapping'], true);
            if (is_array($decoded)) {
                $fieldMapping = $decoded;
            }
        }

        $loaded = self::loadSingleFileInvoiceData($workInv, $fieldMapping);
        if ($loaded === null) {
            if ($cleanupInv) {
                @unlink($workInv);
            }
            return ['status' => 'error', 'error' => 'Could not read the CSV (check header row)'];
        }
        $allErrors = $loaded['errors'] ?? [];
        if (($loaded['ext_order'] ?? []) === []) {
            if ($cleanupInv) {
                @unlink($workInv);
            }
            $msg = 'No data rows with External ID';
            if ($allErrors !== []) {
                $msg .= ': ' . implode(' ', $allErrors);
            }
            return ['status' => 'error', 'error' => $msg];
        }
        $invoiceCount = count($loaded['ext_order']);
        if (!$previewMode && !$dryRun && $invoiceCount > self::SYNC_ROW_THRESHOLD) {
            $rel = 'storage/import_jobs/' . bin2hex(random_bytes(12)) . '.csv';
            $dest = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
            if (!@copy($workInv, $dest)) {
                if ($cleanupInv) {
                    @unlink($workInv);
                }
                return ['status' => 'error', 'error' => 'Could not store import file'];
            }
            $options = [
                'duplicate_strategy' => $duplicateStrategy,
                'created_by' => (int)$session->userId,
                'single_file' => 1,
            ];
            $jobId = Comon_IE_ImportJobService::createJob(
                $connect,
                (int)$session->userId,
                'invoice',
                null,
                'csv',
                (string)($invoiceFile['name'] ?? 'import.csv'),
                $rel,
                $fieldMapping,
                $options,
                'create_only',
                $duplicateStrategy,
                $dryRun,
                $invoiceCount
            );
            if ($cleanupInv) {
                @unlink($workInv);
            }
            if ($jobId <= 0) {
                return ['status' => 'error', 'error' => 'Failed to create import job'];
            }
            $serPath = 'storage/import_jobs/' . $jobId . '_invoice_data.ser';
            $serDest = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $serPath);
            $payload = $loaded;
            if (@file_put_contents($serDest, serialize($payload)) === false) {
                Comon_IE_ImportJobService::markFailed($connect, $jobId, 'Could not write import cache');
                return ['status' => 'error', 'error' => 'Could not prepare import data'];
            }
            $jrow = Comon_IE_ImportJobService::getById($connect, $jobId);
            $opt = $jrow ? (json_decode((string)($jrow['options_json'] ?? '{}'), true) ?: []) : [];
            $opt['single_file'] = 1;
            $opt['invoice_data_cache'] = $serPath;
            $optEsc = $connect->real_escape_string(json_encode($opt, JSON_UNESCAPED_UNICODE));
            $connect->query('UPDATE import_jobs SET options_json = "' . $optEsc . '", file_path = "' . $connect->real_escape_string(Comon_IE_ImportJobService::relativePath($jobId, 'csv')) . '" WHERE id = ' . (int)$jobId);
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
                    'total_rows' => $invoiceCount,
                    'message' => 'Import queued.',
                ],
            ];
        }

        $getForRow = self::rowGetterForData($workInv, $fieldMapping);
        if ($getForRow === null) {
            if ($cleanupInv) {
                @unlink($workInv);
            }
            return ['status' => 'error', 'error' => 'Invalid CSV header'];
        }
        $get = $getForRow;
        $itemsMapFull = $loaded['items_map'];
        $clientHints = $loaded['client_id_hint'] ?? [];
        $inserted = 0;
        $wouldInsert = 0;
        $skipped = 0;
        $errors = $allErrors;
        $previewRows = [];
        $rowNum = 0;
        $previewLimit = 15;
        $defaultSettings = settings::findById(1);
        $invoiceIndex = 0;
        foreach ($loaded['ext_order'] as $ext) {
            $invoiceIndex++;
            $row = $loaded['first_row'][$ext] ?? null;
            if (!is_array($row)) {
                continue;
            }
            $rowNum = (int)($loaded['first_line'][$ext] ?? $invoiceIndex);
            $perInvoiceItems = isset($itemsMapFull[$ext]) ? [$ext => $itemsMapFull[$ext]] : [];
            $clientIdHint = (int)($clientHints[$ext] ?? 0);
            $res = self::processOneInvoiceRow(
                $connect,
                $row,
                $rowNum,
                $get,
                $perInvoiceItems,
                (int)$session->userId,
                $defaultSettings,
                $previewMode,
                $dryRun,
                $duplicateStrategy,
                $previewRows,
                $previewLimit,
                $clientIdHint
            );
            if ($res['error_line'] !== '') {
                $errors[] = $res['error_line'];
            }
            if ($res['action'] === 'skip') {
                $skipped++;
            } elseif ($res['action'] === 'saved') {
                $inserted++;
            } elseif ($res['action'] === 'preview_ok' || $res['action'] === 'dry_ok') {
                $wouldInsert++;
            }
        }
        if ($cleanupInv) {
            @unlink($workInv);
        }

        $data = [
            'inserted' => $inserted,
            'would_insert' => $wouldInsert,
            'updated' => 0,
            'skipped' => $skipped,
            'errors' => $errors,
            'simulate' => $dryRun ? 1 : 0,
        ];
        if ($previewMode) {
            $data['preview'] = $previewRows;
            $data['total_rows'] = $invoiceCount;
        } elseif (!$dryRun && $invoiceCount > 0) {
            Comon_IE_ImportJobService::recordCompletedSyncImport(
                $connect,
                (int)$session->userId,
                'invoice',
                null,
                (string)($invoiceFile['name'] ?? 'import.csv'),
                'create_only',
                $duplicateStrategy,
                $invoiceCount,
                $inserted,
                count($errors),
                0,
                $skipped
            );
        }
        return ['status' => 'ok', 'data' => $data];
    }

    /**
     * @return (callable(array,string):string)|null
     */
    private static function rowGetterForData(string $path, array $fieldMapping): ?callable
    {
        $fh = @fopen($path, 'r');
        if (!$fh) {
            return null;
        }
        [$headers, $headerMap] = Comon_IE_CsvUtilities::readHeadersFromHandle($fh);
        fclose($fh);
        if ($headers === []) {
            return null;
        }
        return function (array $row, string $key) use ($fieldMapping, $headerMap, $headers) {
            return Comon_IE_CsvUtilities::rowGet($row, $key, $fieldMapping, $headerMap, $headers);
        };
    }

    /**
     * @param array<int,array<string,mixed>> $previewRows
     * @param int $clientIdHint first non-empty Client ID in this External ID group (any line)
     * @return array{action:string,error_line:string,duplicate?:bool}
     */
    private static function processOneInvoiceRow(
        mysqli $connect,
        array $row,
        int $rowNum,
        callable $get,
        array $itemsMap,
        int $sessionUserId,
        ?object $defaultSettings,
        bool $previewMode,
        bool $dryRun,
        string $duplicateStrategy,
        array &$previewRows,
        int $previewLimit,
        int $clientIdHint = 0
    ): array {
        $extId = trim($get($row, 'External ID'));
        if ($extId === '') {
            $e = 'Invoice row ' . $rowNum . ': External ID is required';
            self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, '', $e);
            return ['action' => 'skip', 'error_line' => $e];
        }

        $typeRaw = trim($get($row, 'Invoice Type'));
        if ($typeRaw === '') {
            $typeRaw = trim($get($row, 'Invoice Typ'));
        }
        $type = self::normalizeInvoiceTypeForImport($typeRaw);
        if ($type === null) {
            $e = 'Invoice row ' . $rowNum . ': Invoice Type is required (use project, client, or internal — same as add-invoice.php: project / client / custom external)';
            self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, $extId, $e);
            return ['action' => 'skip', 'error_line' => $e];
        }

        $pId = null;
        $cId = null;
        if ($type === 'project') {
            $pid = (int)trim($get($row, 'Project ID'));
            if ($pid <= 0) {
                $e = 'Invoice row ' . $rowNum . ': Project ID is required for project invoices';
                self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, $extId, $e);
                return ['action' => 'skip', 'error_line' => $e];
            }
            require_once dirname(__DIR__) . '/projects.php';
            $proj = projects::findByProjectId($pid);
            if (!$proj) {
                $e = 'Invoice row ' . $rowNum . ': Project not found';
                self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, $extId, $e);
                return ['action' => 'skip', 'error_line' => $e];
            }
            $pId = $pid;
            $mainC = (int)($proj->main_client_id ?? 0);
            $pc = (int)($proj->c_id ?? 0);
            if ($mainC > 0) {
                $cId = $mainC;
            } elseif ($pc > 0) {
                $cId = $pc;
            }
        } elseif ($type === 'client') {
            $cid = (int)trim($get($row, 'Client ID'));
            if ($cid <= 0 && $clientIdHint > 0) {
                $cid = $clientIdHint;
            }
            if ($cid <= 0) {
                $e = 'Invoice row ' . $rowNum . ': Client ID is required for client invoices';
                self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, $extId, $e);
                return ['action' => 'skip', 'error_line' => $e];
            }
            if (!Comon_IE_ProjectTaskImportHelpers::isClientUser($connect, $cid)) {
                $e = 'Invoice row ' . $rowNum . ': Client ID must be a client user';
                self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, $extId, $e);
                return ['action' => 'skip', 'error_line' => $e];
            }
            $cId = $cid;
        } elseif ($type === 'internal') {
            $cid = (int)trim($get($row, 'Client ID'));
            if ($cid <= 0 && $clientIdHint > 0) {
                $cid = $clientIdHint;
            }
            if ($cid > 0) {
                if (!Comon_IE_ProjectTaskImportHelpers::isClientUser($connect, $cid)) {
                    $e = 'Invoice row ' . $rowNum . ': Client ID must be a client user';
                    self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, $extId, $e);
                    return ['action' => 'skip', 'error_line' => $e];
                }
                $cId = $cid;
            }
        }

        $title = mb_substr(trim($get($row, 'Title')), 0, 255);
        if ($title === '') {
            $title = 'Invoice';
        }

        $issueRaw = trim($get($row, 'Issue Date'));
        $issue = $issueRaw !== '' ? Comon_IE_CsvUtilities::normalizeImportDateToYmd($issueRaw) : date('Y-m-d');
        if ($issue === null) {
            $e = 'Invoice row ' . $rowNum . ': Invalid Issue Date';
            self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, $extId, $e);
            return ['action' => 'skip', 'error_line' => $e];
        }

        $dueRaw = trim($get($row, 'Due Date'));
        if ($dueRaw === '') {
            $dueRaw = trim($get($row, 'deadline'));
        }
        $deadline = $dueRaw !== '' ? Comon_IE_CsvUtilities::normalizeImportDateToYmd($dueRaw) : null;
        if ($deadline === null) {
            $e = 'Invoice row ' . $rowNum . ': Due Date is required';
            self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, $extId, $e);
            return ['action' => 'skip', 'error_line' => $e];
        }

        $relRaw = trim($get($row, 'Release Date'));
        $release = $relRaw !== '' ? Comon_IE_CsvUtilities::normalizeImportDateToYmd($relRaw) : date('Y-m-d');
        if ($release === null) {
            $release = date('Y-m-d');
        }

        if ($duplicateStrategy === 'skip') {
            $res = $connect->query(
                'SELECT id FROM milestones WHERE title = "' . $connect->real_escape_string($title)
                . '" AND issue_date = "' . $connect->real_escape_string($issue) . '"'
                . ' AND p_id ' . ($pId === null ? 'IS NULL' : '= ' . (int)$pId)
                . ' AND c_id ' . ($cId === null ? 'IS NULL' : '= ' . (int)$cId)
                . ' LIMIT 1'
            );
            if ($res && $res->num_rows > 0) {
                $e = 'Invoice row ' . $rowNum . ': Duplicate (title+issue+scope), skipped';
                self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, $extId, $e, $title);
                $res->free();
                return ['action' => 'skip', 'error_line' => $e, 'duplicate' => true];
            }
            if ($res) {
                $res->free();
            }
        }

        $items = $itemsMap[$extId] ?? [];
        if ($items === []) {
            $e = 'Invoice row ' . $rowNum . ': No line items for External ID "' . $extId . '"';
            self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, $extId, $e, $title);
            return ['action' => 'skip', 'error_line' => $e];
        }

        $validLines = [];
        $sortN = 0;
        foreach ($items as $it) {
            $d = (string)($it['description'] ?? '');
            $r = (float)($it['rate'] ?? 0);
            if ($d === '' || $r <= 0) {
                continue;
            }
            $q = (float)($it['quantity'] ?? 1);
            if ($q <= 0) {
                $q = 1.0;
            }
            $so = (int)($it['sort_order'] ?? 0);
            if ($so === 0) {
                $so = $sortN;
            }
            $validLines[] = [
                'description' => $d,
                'item_description' => (string)($it['item_description'] ?? ''),
                'rate' => $r,
                'quantity' => $q,
                'sort_order' => $so,
            ];
            $sortN++;
        }
        if ($validLines === []) {
            $e = 'Invoice row ' . $rowNum . ': No valid line items (need description and rate > 0)';
            self::pushInvoicePreview($previewMode, $previewRows, $previewLimit, $rowNum, $extId, $e, $title);
            return ['action' => 'skip', 'error_line' => $e];
        }
        $subtotal = 0.0;
        foreach ($validLines as $line) {
            $subtotal += $line['rate'] * $line['quantity'];
        }

        if ($previewMode) {
            $note = $dryRun ? 'Dry run' : 'Would import';
            self::pushInvoicePreview(
                $previewMode,
                $previewRows,
                $previewLimit,
                $rowNum,
                $extId,
                $note . ' — ' . $title . ' (lines: ' . count($validLines) . ')',
                $title
            );
            return ['action' => 'preview_ok', 'error_line' => ''];
        }
        if ($dryRun) {
            return ['action' => 'dry_ok', 'error_line' => ''];
        }

        $status = (int)trim($get($row, 'Status'));
        if ($status < 0 || $status > 3) {
            $status = 0;
        }

        $createdBy = (int)trim($get($row, 'Created By'));
        if ($createdBy <= 0) {
            $createdBy = $sessionUserId;
        }

        $cur = trim($get($row, 'Currency'));
        if ($cur === '' && $defaultSettings && !empty($defaultSettings->system_currency)) {
            $cur = (string)$defaultSettings->system_currency;
        }

        $st = trim($get($row, 'Sales Tax'));
        $defTax = 0.0;
        if ($defaultSettings && isset($defaultSettings->default_sales_tax) && $defaultSettings->default_sales_tax !== null && $defaultSettings->default_sales_tax !== '') {
            $defTax = (float)$defaultSettings->default_sales_tax;
        }
        $stVal = $st !== '' && is_numeric($st) ? (float)$st : $defTax;
        $stType = strtolower(trim($get($row, 'Sales Tax Type')));
        if ($stType !== 'percentage' && $stType !== 'amount' && $stType !== 'fixed') {
            $stType = 'percentage';
        }
        if ($stType === 'fixed') {
            $stType = 'amount';
        }

        $disc = trim($get($row, 'Discount'));
        $discVal = $disc !== '' && is_numeric($disc) ? (float)$disc : 0.0;
        $discType = strtolower(trim($get($row, 'Discount Type')));
        if ($discType !== 'percentage' && $discType !== 'amount' && $discType !== 'fixed') {
            $discType = 'percentage';
        }
        if ($discType === 'fixed') {
            $discType = 'amount';
        }

        require_once dirname(__DIR__) . '/milestone.php';
        require_once dirname(__DIR__) . '/invoice_item.php';

        global $database;
        $dbc = $database->connection;
        if (!mysqli_begin_transaction($dbc)) {
            $e = 'Invoice row ' . $rowNum . ': Could not start transaction';
            return ['action' => 'skip', 'error_line' => $e];
        }

        try {
            $m = new milestone();
            if ($pId !== null) {
                $m->p_id = $pId;
            } else {
                $m->p_id = null;
            }
            if ($cId !== null) {
                $m->c_id = $cId;
            } else {
                $m->c_id = null;
            }
            $m->title = $title;
            $m->budget = $subtotal;
            $m->deadline = $deadline;
            $m->releaseDate = $release;
            $m->status = $status;
            $m->issue_date = $issue;
            $m->currency = $cur;
            $m->created_by = $createdBy;
            $m->bill_from = trim($get($row, 'Bill From'));
            $m->bill_from_address = trim($get($row, 'Bill From Address'));
            $m->memo = (string)$get($row, 'Memo');
            $m->footer = (string)$get($row, 'Footer');
            $m->sales_tax = $stVal;
            $m->sales_tax_type = $stType;
            $m->discount = $discVal;
            $m->discount_type = $discType;
            $m->is_recurring = 0;
            $m->recurring_frequency = null;
            $m->recurring_parent_id = null;
            $m->recurring_next_date = null;
            $m->recurring_stopped = 0;
            $m->recurring_end_date = null;
            $m->recurring_next_renewal_date = null;
            $m->recurring_billing_cycle_days = null;
            $m->recurring_paused = 0;

            $saveM = $m->save();
            if (!is_numeric($saveM) || (int)$saveM <= 0) {
                throw new RuntimeException(is_string($saveM) ? $saveM : 'milestone save failed');
            }
            $mid = (int)$m->id;
            if ($mid <= 0) {
                throw new RuntimeException('missing id');
            }
            $order = 0;
            foreach ($validLines as $line) {
                $inv = new InvoiceItem();
                $inv->milestone_id = $mid;
                $inv->description = $line['description'];
                $inv->item_description = $line['item_description'];
                $inv->rate = $line['rate'];
                $inv->quantity = $line['quantity'];
                $inv->sort_order = $order++;
                $saveI = $inv->save();
                if (!is_numeric($saveI) || (int)$saveI <= 0) {
                    throw new RuntimeException(is_string($saveI) ? $saveI : 'line save failed');
                }
            }
        } catch (Throwable $e) {
            mysqli_rollback($dbc);
            $err = 'Invoice row ' . $rowNum . ': ' . $e->getMessage();
            return ['action' => 'skip', 'error_line' => $err];
        }
        if (!mysqli_commit($dbc)) {
            mysqli_rollback($dbc);
            return ['action' => 'skip', 'error_line' => 'Invoice row ' . $rowNum . ': Commit failed'];
        }

        return ['action' => 'saved', 'error_line' => ''];
    }

    /**
     * Align CSV values with admin/add-invoice.php: project | client | internal.
     * Accepts UI phrases (e.g. "external", "Create custom invoice") and Excel-truncated headers via $get('Invoice Typ').
     */
    private static function normalizeInvoiceTypeForImport(string $raw): ?string
    {
        $t = strtolower(trim($raw));
        $t = preg_replace('/\s+/', ' ', $t);
        if ($t === '') {
            return null;
        }
        if (in_array($t, ['project', 'client', 'internal'], true)) {
            return $t;
        }
        if ($t === 'external' || $t === 'custom' || str_contains($t, 'external') || str_contains($t, 'custom invoice')) {
            return 'internal';
        }
        if (str_contains($t, 'for projects') || str_contains($t, 'projects')) {
            return 'project';
        }
        if (str_contains($t, 'for client') || str_contains($t, 'client')) {
            return 'client';
        }
        return null;
    }

    private static function pushInvoicePreview(
        bool $previewMode,
        array &$previewRows,
        int $previewLimit,
        int $rowNum,
        string $extId,
        string $note,
        string $title = ''
    ): void {
        if (!$previewMode || count($previewRows) >= $previewLimit) {
            return;
        }
        $previewRows[] = [
            'row' => $rowNum,
            'external_id' => $extId,
            'title' => $title,
            'note' => $note,
        ];
    }

    private static function floatOr(string $s, float $default): float
    {
        $s = trim($s);
        if ($s === '' || !is_numeric($s)) {
            return $default;
        }
        return (float)$s;
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
        $cacheRel = (string)($options['invoice_data_cache'] ?? '');
        $cachePath = $cacheRel !== '' ? $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $cacheRel) : '';
        if ($cachePath === '' || !is_readable($cachePath)) {
            $mapping = json_decode((string)($jobRow['mapping_json'] ?? '{}'), true) ?: [];
            $rebuilt = self::loadSingleFileInvoiceData($path, $mapping);
            if ($rebuilt === null) {
                return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'parse file'];
            }
            $jobId = (int)($jobRow['id'] ?? 0);
            if ($jobId > 0) {
                $serDest = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'import_jobs' . DIRECTORY_SEPARATOR . $jobId . '_invoice_data.ser';
                if (@file_put_contents($serDest, serialize($rebuilt)) !== false) {
                    $opt = $options;
                    $opt['invoice_data_cache'] = 'storage/import_jobs/' . $jobId . '_invoice_data.ser';
                    $esc = $connect->real_escape_string(json_encode($opt, JSON_UNESCAPED_UNICODE));
                    $connect->query('UPDATE import_jobs SET options_json = "' . $esc . '" WHERE id = ' . $jobId);
                    $cachePath = $serDest;
                }
            }
        }
        if (!is_readable($cachePath)) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'no cache'];
        }
        $raw = @file_get_contents($cachePath);
        if ($raw === false || $raw === '') {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'cache read'];
        }
        $data = @unserialize($raw);
        if (!is_array($data) || !isset($data['ext_order'])) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'cache bad'];
        }
        $extOrder = $data['ext_order'];
        $get = self::rowGetterForData($path, json_decode((string)($jobRow['mapping_json'] ?? '{}'), true) ?: []);
        if ($get === null) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'headers'];
        }
        $dup = (string)($options['duplicate_strategy'] ?? 'skip');
        $dry = !empty($jobRow['dry_run']);
        $createdBy = (int)($options['created_by'] ?? 0);
        if ($createdBy <= 0) {
            $createdBy = (int)($jobRow['user_id'] ?? 0);
        }
        $defaultSettings = settings::findById(1);
        $itemsMapFull = $data['items_map'] ?? [];
        $firstRow = $data['first_row'] ?? [];
        $firstLine = $data['first_line'] ?? [];
        $clientHints = $data['client_id_hint'] ?? [];

        $offset = (int)($jobRow['processed_rows']);
        $slice = array_slice($extOrder, $offset, self::JOB_CHUNK_ROWS);
        $success = 0;
        $fail = 0;
        $skip = 0;
        $processed = count($slice);
        foreach ($slice as $ext) {
            $row = $firstRow[$ext] ?? null;
            if (!is_array($row)) {
                $fail++;
                continue;
            }
            $rowNum = (int)($firstLine[$ext] ?? 0);
            $perMap = isset($itemsMapFull[$ext]) ? [$ext => $itemsMapFull[$ext]] : [];
            $preview = [];
            $clientIdHint = (int)($clientHints[$ext] ?? 0);
            $r = self::processOneInvoiceRow(
                $connect,
                $row,
                $rowNum,
                $get,
                $perMap,
                $createdBy,
                $defaultSettings,
                false,
                $dry,
                $dup,
                $preview,
                0,
                $clientIdHint
            );
            if ($r['action'] === 'saved') {
                $success++;
            } elseif ($r['action'] === 'skip') {
                if (!empty($r['duplicate'])) {
                    $skip++;
                } else {
                    $fail++;
                }
            } elseif ($r['action'] === 'dry_ok') {
                $success++;
            }
        }
        return [
            'ok' => true,
            'processed' => $processed,
            'success' => $success,
            'skipped' => $skip,
            'failed' => $fail,
            'updated' => 0,
        ];
    }
}
