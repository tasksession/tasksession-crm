<?php
/**
 * CSV/XLSX import for client_companies + client_company_members.
 *
 * CRM field keys (mapping targets): Company Name, Currency, VAT Number, Phone, Email, Website,
 * Address, City, State, Zip Code, Country,
 * Billing address, Billing Street, Billing City, Billing State, Billing Zip Code, Billing Country,
 * Client IDs, Primary Client ID, Linked Companies (or legacy Linked Company IDs), CustomField_{id} (company entity custom fields)
 *
 * Linked Companies column accepts numeric client_companies.id, company names, id|name, or name|VAT (semicolon-separated when names contain commas).
 */
class Comon_IE_CompanyImportRunner
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
            return ['status' => 'error', 'error' => 'Only administrators can import companies'];
        }
        if (!self::companiesTableExists($connect)) {
            return ['status' => 'error', 'error' => 'Client companies are not available on this installation'];
        }

        $previewMode = isset($post['preview']) && $post['preview'] === '1';
        $simulate = isset($post['simulate']) && $post['simulate'] === '1';
        $dryRun = $simulate || (!empty($post['dry_run']) && $post['dry_run'] === '1');
        $duplicateStrategy = isset($post['duplicate_strategy']) ? (string)$post['duplicate_strategy'] : 'skip';
        if (!in_array($duplicateStrategy, ['skip', 'create_anyway', 'overwrite'], true)) {
            $duplicateStrategy = 'skip';
        }

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
                'created_by' => (int) ($session->userId ?? 0),
            ];
            $jobId = Comon_IE_ImportJobService::createJob(
                $connect,
                (int) ($session->userId ?? 0),
                'company',
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
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $previewRows = [];
        $rowNum = 0;
        $previewLimit = 15;
        $createdBy = (int) ($session->userId ?? 0);
        $importCompanyMap = [];

        $get = function (array $row, string $key) use ($fieldMapping, $headerMap, $headers) {
            return Comon_IE_CsvUtilities::rowGet($row, $key, $fieldMapping, $headerMap, $headers);
        };

        while (($row = fgetcsv($fh)) !== false) {
            if (!is_array($row) || count($row) === 0) {
                continue;
            }
            $rowNum++;
            $res = self::processOneCompanyRow(
                $connect,
                $row,
                $rowNum,
                $get,
                $previewMode,
                $dryRun,
                $duplicateStrategy,
                $previewRows,
                $previewLimit,
                $createdBy,
                $importCompanyMap
            );
            if ($res['error_line'] !== '') {
                $errors[] = $res['error_line'];
            }
            if ($res['action'] === 'skip') {
                $skipped++;
            } elseif ($res['action'] === 'insert') {
                $inserted++;
            } elseif ($res['action'] === 'update') {
                $updated++;
            }
        }
        fclose($fh);
        if ($cleanupWork) {
            @unlink($workPath);
        }

        $data = [
            'inserted' => $inserted,
            'updated' => $updated,
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
                (int) ($session->userId ?? 0),
                'company',
                null,
                (string)($file['name'] ?? 'import.csv'),
                'create_only',
                $duplicateStrategy,
                $rowNum,
                $inserted,
                count($errors),
                $updated,
                $skipped
            );
        }
        return ['status' => 'ok', 'data' => $data];
    }

    public static function companiesTableExists(mysqli $connect): bool
    {
        $tbl = @mysqli_query($connect, "SHOW TABLES LIKE 'client_companies'");
        if (!$tbl || mysqli_num_rows($tbl) === 0) {
            if ($tbl) {
                mysqli_free_result($tbl);
            }
            return false;
        }
        mysqli_free_result($tbl);
        return true;
    }

    /** Public wrapper for client-import org linking (same rules as company CSV import). */
    public static function isCompanyCurrencyAllowed(mysqli $connect, string $currencyKey): bool
    {
        return self::currencyAllowed($connect, $currencyKey);
    }

    /** @return int Company id or 0 if not found */
    public static function findCompanyIdByNameAndVat(mysqli $connect, string $name, string $vat): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $vat = trim($vat);
        $nameEsc = $connect->real_escape_string(mb_substr($name, 0, 191));
        $vatEsc = $connect->real_escape_string(mb_substr($vat, 0, 64));
        $sql = 'SELECT id FROM client_companies WHERE deleted_at IS NULL AND name = \'' . $nameEsc
            . '\' AND COALESCE(vat_number,\'\') = \'' . $vatEsc . '\' LIMIT 1';
        $q = mysqli_query($connect, $sql);
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            mysqli_free_result($q);
            return (int)($r['id'] ?? 0);
        }
        if ($q) {
            mysqli_free_result($q);
        }
        return 0;
    }

    /**
     * @param array<int,array<string,mixed>> $previewRows
     */
    private static function pushCompanyPreviewRow(
        bool $previewMode,
        array &$previewRows,
        int $previewLimit,
        int $rowNum,
        string $companyName,
        string $note
    ): void {
        if (!$previewMode || count($previewRows) >= $previewLimit) {
            return;
        }
        $previewRows[] = [
            'row' => $rowNum,
            'company_name' => $companyName,
            'note' => $note,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $previewRows
     * @return array{action:string,error_line:string,duplicate_company?:bool}
     */
    private static function processOneCompanyRow(
        mysqli $connect,
        array $row,
        int $rowNum,
        callable $get,
        bool $previewMode,
        bool $dryRun,
        string $duplicateStrategy,
        array &$previewRows,
        int $previewLimit,
        int $createdBy,
        array &$importCompanyMap = []
    ): array {
        $name = mb_substr(trim($get($row, 'Company Name')), 0, 191);
        if ($name === '') {
            $err = 'Row ' . $rowNum . ': Company Name is required';
            self::pushCompanyPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, '', $err);
            return ['action' => 'skip', 'error_line' => $err];
        }

        $currency = trim($get($row, 'Currency'));
        if ($currency === '' || !self::currencyAllowed($connect, $currency)) {
            $err = 'Row ' . $rowNum . ': Currency is required and must be enabled in system settings';
            self::pushCompanyPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $name, $err);
            return ['action' => 'skip', 'error_line' => $err];
        }

        $vat = mb_substr(trim($get($row, 'VAT Number')), 0, 64);
        $dupId = self::findCompanyIdByNameAndVat($connect, $name, $vat);
        if ($dupId > 0 && $duplicateStrategy === 'skip') {
            $err = 'Row ' . $rowNum . ': Duplicate company (same name and VAT; skipped)';
            self::pushCompanyPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $name, $err);
            return ['action' => 'skip', 'error_line' => $err, 'duplicate_company' => true];
        }

        $isOverwriteDup = ($dupId > 0 && $duplicateStrategy === 'overwrite');
        $clientIdsRaw = trim($get($row, 'Client IDs'));
        if ($clientIdsRaw === '') {
            $err = 'Row ' . $rowNum . ': Client IDs is required (comma-separated client user IDs)';
            self::pushCompanyPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $name, $err);
            return ['action' => 'skip', 'error_line' => $err];
        }

        $parsedIds = self::parseClientIdTokens($clientIdsRaw);
        [$validClients, $clientErr] = self::validateClientUserIds($connect, $parsedIds);
        if ($clientErr !== '') {
            $err = 'Row ' . $rowNum . ': ' . $clientErr;
            self::pushCompanyPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $name, $err);
            return ['action' => 'skip', 'error_line' => $err];
        }
        if ($validClients === []) {
            $err = 'Row ' . $rowNum . ': No valid client IDs after validation';
            self::pushCompanyPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $name, $err);
            return ['action' => 'skip', 'error_line' => $err];
        }

        $primaryRaw = (int)trim($get($row, 'Primary Client ID'));
        $primaryId = self::resolvePrimaryClientId($validClients, $primaryRaw);

        $phone = mb_substr(trim($get($row, 'Phone')), 0, 64);
        $email = mb_substr(trim($get($row, 'Email')), 0, 191);
        $website = mb_substr(trim($get($row, 'Website')), 0, 512);
        $address = mb_substr(trim($get($row, 'Address')), 0, 512);
        $city = mb_substr(trim($get($row, 'City')), 0, 128);
        $state = mb_substr(trim($get($row, 'State')), 0, 128);
        $zip = mb_substr(trim($get($row, 'Zip Code')), 0, 32);
        $country = mb_substr(trim($get($row, 'Country')), 0, 128);
        $billAddr = mb_substr(trim($get($row, 'Billing address')), 0, 512);
        $billStreet = mb_substr(trim($get($row, 'Billing Street')), 0, 512);
        $billCity = mb_substr(trim($get($row, 'Billing City')), 0, 128);
        $billState = mb_substr(trim($get($row, 'Billing State')), 0, 128);
        $billZip = mb_substr(trim($get($row, 'Billing Zip Code')), 0, 32);
        $billCountry = mb_substr(trim($get($row, 'Billing Country')), 0, 128);

        if ($previewMode) {
            $note = $dryRun ? 'Dry run' : 'Would create';
            if ($isOverwriteDup) {
                $note = $dryRun ? 'Dry run (update)' : 'Would update existing company';
            } elseif ($dupId > 0 && $duplicateStrategy === 'create_anyway') {
                $note = $dryRun ? 'Dry run' : 'Would create new row (duplicate name+VAT; import anyway)';
            }
            $linkedRaw = self::getLinkedCompaniesRaw($get, $row);
            if ($linkedRaw !== '') {
                $note .= '; linked companies: ' . $linkedRaw;
            }
            self::pushCompanyPreviewRow(
                $previewMode,
                $previewRows,
                $previewLimit,
                $rowNum,
                $name,
                $note
            );
            return ['action' => $isOverwriteDup ? 'update' : 'insert', 'error_line' => ''];
        }
        if ($dryRun) {
            return ['action' => $isOverwriteDup ? 'update' : 'insert', 'error_line' => ''];
        }

        if ($isOverwriteDup) {
            mysqli_begin_transaction($connect);
            try {
                $stmt = mysqli_prepare(
                    $connect,
                    'UPDATE client_companies SET name=?, vat_number=?, phone=?, email=?, website=?, currency=?,
                    address=?, city=?, state=?, zip=?, country=?,
                    billing_address=?, billing_street=?, billing_city=?, billing_state=?, billing_zip=?, billing_country=?
                    WHERE id = ? AND deleted_at IS NULL'
                );
                if (!$stmt) {
                    throw new RuntimeException('prepare update company failed');
                }
                mysqli_stmt_bind_param(
                    $stmt,
                    'ssssssssssssssssi',
                    $name,
                    $vat,
                    $phone,
                    $email,
                    $website,
                    $currency,
                    $address,
                    $city,
                    $state,
                    $zip,
                    $country,
                    $billAddr,
                    $billStreet,
                    $billCity,
                    $billState,
                    $billZip,
                    $billCountry,
                    $dupId
                );
                if (!mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    throw new RuntimeException('update company failed');
                }
                mysqli_stmt_close($stmt);

                $del = mysqli_prepare($connect, 'DELETE FROM client_company_members WHERE company_id = ?');
                if (!$del) {
                    throw new RuntimeException('prepare delete members failed');
                }
                mysqli_stmt_bind_param($del, 'i', $dupId);
                if (!mysqli_stmt_execute($del)) {
                    mysqli_stmt_close($del);
                    throw new RuntimeException('delete members failed');
                }
                mysqli_stmt_close($del);

                $ins = mysqli_prepare($connect, 'INSERT IGNORE INTO client_company_members (company_id, user_id, is_primary) VALUES (?, ?, ?)');
                if (!$ins) {
                    throw new RuntimeException('prepare members failed');
                }
                foreach ($validClients as $memUid) {
                    $isP = ((int)$memUid === $primaryId) ? 1 : 0;
                    mysqli_stmt_bind_param($ins, 'iii', $dupId, $memUid, $isP);
                    mysqli_stmt_execute($ins);
                }
                mysqli_stmt_close($ins);

                mysqli_commit($connect);
            } catch (Throwable $e) {
                mysqli_rollback($connect);
                $err = 'Row ' . $rowNum . ': ' . $e->getMessage();
                self::pushCompanyPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $name, $err);
                return ['action' => 'skip', 'error_line' => $err];
            }
            self::saveCompanyCustomFieldsFromRow($connect, $row, $get, $dupId);
            $linkErr = self::applyLinkedCompaniesFromRow($connect, $row, $get, $dupId, $rowNum, $createdBy, $importCompanyMap);
            if ($linkErr !== '') {
                return ['action' => 'skip', 'error_line' => $linkErr];
            }
            self::registerCompanyInImportMap($importCompanyMap, $name, $vat, $dupId);
            return ['action' => 'update', 'error_line' => ''];
        }

        mysqli_begin_transaction($connect);
        try {
            $logoInit = '';
            $stmt = mysqli_prepare(
                $connect,
                'INSERT INTO client_companies (name, logo_path, vat_number, phone, email, website, currency,
                address, city, state, zip, country,
                billing_address, billing_street, billing_city, billing_state, billing_zip, billing_country,
                created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            if (!$stmt) {
                throw new RuntimeException('prepare failed');
            }
            mysqli_stmt_bind_param(
                $stmt,
                'ssssssssssssssssssi',
                $name,
                $logoInit,
                $vat,
                $phone,
                $email,
                $website,
                $currency,
                $address,
                $city,
                $state,
                $zip,
                $country,
                $billAddr,
                $billStreet,
                $billCity,
                $billState,
                $billZip,
                $billCountry,
                $createdBy
            );
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                throw new RuntimeException('insert company failed');
            }
            $newId = (int)mysqli_insert_id($connect);
            mysqli_stmt_close($stmt);

            $ins = mysqli_prepare($connect, 'INSERT IGNORE INTO client_company_members (company_id, user_id, is_primary) VALUES (?, ?, ?)');
            if (!$ins) {
                throw new RuntimeException('prepare members failed');
            }
            foreach ($validClients as $cid) {
                $isP = ((int)$cid === $primaryId) ? 1 : 0;
                mysqli_stmt_bind_param($ins, 'iii', $newId, $cid, $isP);
                mysqli_stmt_execute($ins);
            }
            mysqli_stmt_close($ins);

            mysqli_commit($connect);
        } catch (Throwable $e) {
            mysqli_rollback($connect);
            $err = 'Row ' . $rowNum . ': ' . $e->getMessage();
            self::pushCompanyPreviewRow($previewMode, $previewRows, $previewLimit, $rowNum, $name, $err);
            return ['action' => 'skip', 'error_line' => $err];
        }

        self::saveCompanyCustomFieldsFromRow($connect, $row, $get, $newId);

        $linkErr = self::applyLinkedCompaniesFromRow($connect, $row, $get, $newId, $rowNum, $createdBy, $importCompanyMap);
        if ($linkErr !== '') {
            return ['action' => 'skip', 'error_line' => $linkErr];
        }
        self::registerCompanyInImportMap($importCompanyMap, $name, $vat, $newId);

        return ['action' => 'insert', 'error_line' => ''];
    }

    /**
     * @return int[]
     */
    private static function parseClientIdTokens(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return [];
        }
        $out = [];
        foreach ($parts as $p) {
            $n = (int)trim((string)$p);
            if ($n > 0) {
                $out[] = $n;
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @param int[] $ids
     * @return array{0:int[],1:string}
     */
    private static function validateClientUserIds(mysqli $connect, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [[], 'Client IDs must contain at least one numeric id'];
        }
        $valid = [];
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            $id = (int)$id;
            $q = mysqli_query(
                $connect,
                'SELECT id FROM users WHERE id = ' . $id . ' AND status = 0 AND accountStatus = 2 LIMIT 1'
            );
            if ($q && mysqli_num_rows($q) > 0) {
                mysqli_free_result($q);
                $valid[] = $id;
            } elseif ($q) {
                mysqli_free_result($q);
            }
        }
        if (count($valid) !== count($ids)) {
            return [[], 'One or more Client IDs are invalid or not active clients (accountStatus=2, status=0)'];
        }
        return [$valid, ''];
    }

    /**
     * @param int[] $validClients
     */
    private static function resolvePrimaryClientId(array $validClients, int $primaryRaw): int
    {
        $n = count($validClients);
        if ($n === 0) {
            return 0;
        }
        if ($n === 1) {
            return (int)$validClients[0];
        }
        if ($primaryRaw > 0 && in_array($primaryRaw, $validClients, true)) {
            return $primaryRaw;
        }
        return (int)$validClients[0];
    }

    private static function currencyAllowed(mysqli $connect, string $currencyKey): bool
    {
        $currencyKey = trim($currencyKey);
        if ($currencyKey === '') {
            return false;
        }
        $settings = settings::findById(1);
        if (!$settings) {
            return false;
        }
        $enabled = $settings->getMultipleCurrencies();
        if (!is_array($enabled) || $enabled === []) {
            $fallback = trim((string)($settings->system_currency ?? ''));
            if ($fallback !== '') {
                $enabled = [$fallback];
            } else {
                return false;
            }
        }
        if (in_array($currencyKey, $enabled, true)) {
            return true;
        }
        // Match by ISO code: sheet may have "USD" or "USD,$" while settings store "USD,$" (same as getMultipleCurrencies pairs).
        $want = strtoupper(explode(',', $currencyKey, 2)[0]);
        if ($want === '') {
            return false;
        }
        foreach ($enabled as $e) {
            if (!is_string($e)) {
                continue;
            }
            $e = trim($e);
            if ($e === '') {
                continue;
            }
            if (strtoupper(explode(',', $e, 2)[0]) === $want) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param callable(array,string):string $get
     */
    private static function saveCompanyCustomFieldsFromRow(mysqli $connect, array $row, callable $get, int $companyId): void
    {
        if ($companyId <= 0) {
            return;
        }
        $cfRes = mysqli_query(
            $connect,
            'SELECT id, field_type FROM custom_fields WHERE entity_type = \'company\' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC'
        );
        if (!$cfRes) {
            return;
        }
        $posted = [];
        while ($cf = mysqli_fetch_assoc($cfRes)) {
            $key = 'CustomField_' . $cf['id'];
            $val = $get($row, $key);
            if ($val === '') {
                continue;
            }
            $fid = (int)$cf['id'];
            if (($cf['field_type'] ?? '') === 'multiple_select') {
                $values = array_filter(array_map('trim', preg_split('/[;,]/', $val)));
                $posted[$fid] = array_values($values);
            } else {
                $posted[$fid] = $val;
            }
        }
        mysqli_free_result($cfRes);
        if ($posted === []) {
            return;
        }
        require_once dirname(__DIR__) . '/custom-fields/company_custom_field_values.php';
        save_company_custom_field_values($connect, $companyId, $posted);
    }

    private static function companyLinksTableExists(mysqli $connect): bool
    {
        $tbl = @mysqli_query($connect, "SHOW TABLES LIKE 'client_company_links'");
        if (!$tbl || mysqli_num_rows($tbl) === 0) {
            if ($tbl) {
                mysqli_free_result($tbl);
            }
            return false;
        }
        mysqli_free_result($tbl);
        return true;
    }

    /**
     * @param int[] $ids
     * @return array{0:int[],1:string}
     */
    private static function validateCompanyIds(mysqli $connect, array $ids, int $excludeCompanyId = 0): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [[], ''];
        }
        $valid = [];
        foreach ($ids as $id) {
            if ($id <= 0 || ($excludeCompanyId > 0 && $id === $excludeCompanyId)) {
                continue;
            }
            $q = mysqli_query(
                $connect,
                'SELECT id FROM client_companies WHERE id = ' . $id . ' AND deleted_at IS NULL LIMIT 1'
            );
            if ($q && mysqli_num_rows($q) > 0) {
                mysqli_free_result($q);
                $valid[] = $id;
            } elseif ($q) {
                mysqli_free_result($q);
            }
        }
        if (count($valid) !== count($ids)) {
            return [[], 'One or more linked company IDs are invalid or deleted'];
        }
        return [$valid, ''];
    }

    /**
     * @param callable(array,string):string $get
     */
    private static function getLinkedCompaniesRaw(callable $get, array $row): string
    {
        $v = trim($get($row, 'Linked Companies'));
        if ($v === '') {
            $v = trim($get($row, 'Linked Company IDs'));
        }
        return $v;
    }

    private static function stripListTokenQuotes(string $token): string
    {
        $token = trim($token);
        if ($token !== '' && $token[0] === '"' && substr($token, -1) === '"') {
            return trim(substr($token, 1, -1));
        }
        if ($token !== '' && $token[0] === "'" && substr($token, -1) === "'") {
            return trim(substr($token, 1, -1));
        }
        return $token;
    }

    /**
     * @return string[]
     */
    private static function parseLinkedCompanyListTokens(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        if (strpos($raw, ';') !== false) {
            $parts = explode(';', $raw);
        } else {
            $parts = [];
            $buf = '';
            $inQuote = false;
            $quoteChar = '';
            $len = strlen($raw);
            for ($i = 0; $i < $len; $i++) {
                $ch = $raw[$i];
                if (!$inQuote && ($ch === '"' || $ch === "'")) {
                    $inQuote = true;
                    $quoteChar = $ch;
                    continue;
                }
                if ($inQuote && $ch === $quoteChar) {
                    $inQuote = false;
                    $quoteChar = '';
                    continue;
                }
                if (!$inQuote && $ch === ',') {
                    $t = trim($buf);
                    if ($t !== '') {
                        $parts[] = $t;
                    }
                    $buf = '';
                    continue;
                }
                $buf .= $ch;
            }
            $t = trim($buf);
            if ($t !== '') {
                $parts[] = $t;
            }
        }
        $out = [];
        foreach ($parts as $p) {
            $t = self::stripListTokenQuotes(trim((string)$p));
            if ($t !== '') {
                $out[] = $t;
            }
        }
        return $out;
    }

    private static function companyImportMapKey(string $name, string $vat = ''): string
    {
        return mb_strtolower(trim($name)) . "\x1e" . mb_strtolower(trim($vat));
    }

    /**
     * @param array<string,int> $importCompanyMap
     */
    private static function registerCompanyInImportMap(array &$importCompanyMap, string $name, string $vat, int $companyId): void
    {
        if ($companyId <= 0 || trim($name) === '') {
            return;
        }
        $importCompanyMap[self::companyImportMapKey($name, $vat)] = $companyId;
        $nameKey = self::companyImportMapKey($name, '');
        if (!isset($importCompanyMap[$nameKey])) {
            $importCompanyMap[$nameKey] = $companyId;
        }
    }

    /** @return int[] */
    private static function findCompanyIdsByNameLoose(mysqli $connect, string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }
        $esc = $connect->real_escape_string($name);
        $q = mysqli_query(
            $connect,
            'SELECT id FROM client_companies WHERE deleted_at IS NULL AND LOWER(name) = LOWER(\'' . $esc . '\') ORDER BY id ASC'
        );
        $ids = [];
        if ($q) {
            while ($r = mysqli_fetch_assoc($q)) {
                $id = (int)($r['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            mysqli_free_result($q);
        }
        return $ids;
    }

    private static function findCompanyIdByNameAndVatLoose(mysqli $connect, string $name, string $vat): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $vat = trim($vat);
        $nameEsc = $connect->real_escape_string($name);
        $vatEsc = $connect->real_escape_string($vat);
        $q = mysqli_query(
            $connect,
            'SELECT id FROM client_companies WHERE deleted_at IS NULL AND LOWER(name) = LOWER(\'' . $nameEsc
            . '\') AND LOWER(COALESCE(vat_number, \'\')) = LOWER(\'' . $vatEsc . '\') LIMIT 1'
        );
        if ($q && ($r = mysqli_fetch_assoc($q))) {
            mysqli_free_result($q);
            return (int)($r['id'] ?? 0);
        }
        if ($q) {
            mysqli_free_result($q);
        }
        return 0;
    }

    /**
     * @param array<string,int> $importCompanyMap
     * @return array{0:int,1:string}
     */
    private static function resolveLinkedCompanyToken(
        mysqli $connect,
        string $token,
        int $excludeCompanyId,
        array $importCompanyMap
    ): array {
        $token = self::stripListTokenQuotes(trim($token));
        if ($token === '') {
            return [0, ''];
        }

        if (preg_match('/^(\d+)\s*[\|:]\s*(.+)$/', $token, $m)) {
            $id = (int)$m[1];
            [$valid, $err] = self::validateCompanyIds($connect, [$id], $excludeCompanyId);
            if ($err !== '') {
                return [0, $err];
            }
            return [(int)$valid[0], ''];
        }

        if (preg_match('/^\d+$/', $token)) {
            $id = (int)$token;
            [$valid, $err] = self::validateCompanyIds($connect, [$id], $excludeCompanyId);
            if ($err !== '') {
                return [0, $err];
            }
            return [(int)$valid[0], ''];
        }

        $name = $token;
        $vat = '';
        if (preg_match('/^(.+?)\s*[\|:]\s*(.+)$/', $token, $m)) {
            $name = trim((string)$m[1]);
            $vat = trim((string)$m[2]);
        }

        if ($name === '') {
            return [0, 'Invalid linked company value'];
        }

        $mapKey = self::companyImportMapKey($name, $vat);
        if (isset($importCompanyMap[$mapKey])) {
            $id = (int)$importCompanyMap[$mapKey];
            if ($id > 0 && $id !== $excludeCompanyId) {
                return [$id, ''];
            }
            if ($id === $excludeCompanyId) {
                return [0, 'Cannot link a company to itself'];
            }
        }
        if ($vat === '') {
            $nameKey = self::companyImportMapKey($name, '');
            if (isset($importCompanyMap[$nameKey])) {
                $id = (int)$importCompanyMap[$nameKey];
                if ($id > 0 && $id !== $excludeCompanyId) {
                    return [$id, ''];
                }
            }
        }

        if ($vat !== '') {
            $id = self::findCompanyIdByNameAndVatLoose($connect, $name, $vat);
            if ($id > 0) {
                if ($id === $excludeCompanyId) {
                    return [0, 'Cannot link a company to itself'];
                }
                return [$id, ''];
            }
            return [0, 'Linked company not found: ' . $name . '|' . $vat];
        }

        $ids = self::findCompanyIdsByNameLoose($connect, $name);
        if (count($ids) === 1) {
            $id = (int)$ids[0];
            if ($id === $excludeCompanyId) {
                return [0, 'Cannot link a company to itself'];
            }
            return [$id, ''];
        }
        if (count($ids) > 1) {
            return [0, 'Ambiguous company name "' . $name . '" (use Name|VAT or numeric ID)'];
        }

        return [0, 'Linked company not found: ' . $name];
    }

    /**
     * @param array<string,int> $importCompanyMap
     * @return array{0:int[],1:string}
     */
    private static function resolveLinkedCompanyReferences(
        mysqli $connect,
        string $raw,
        int $excludeCompanyId,
        array $importCompanyMap
    ): array {
        $tokens = self::parseLinkedCompanyListTokens($raw);
        if ($tokens === []) {
            return [[], ''];
        }
        $ids = [];
        foreach ($tokens as $token) {
            [$id, $err] = self::resolveLinkedCompanyToken($connect, $token, $excludeCompanyId, $importCompanyMap);
            if ($err !== '') {
                return [[], $err];
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return [array_values(array_unique($ids)), ''];
    }

    private static function deleteCompanyLinkPair(mysqli $connect, int $companyId, int $linkedId): void
    {
        if ($companyId <= 0 || $linkedId <= 0 || $companyId === $linkedId) {
            return;
        }
        $del = mysqli_prepare(
            $connect,
            'DELETE FROM client_company_links
             WHERE (company_id = ? AND linked_company_id = ?)
                OR (company_id = ? AND linked_company_id = ?)'
        );
        if (!$del) {
            return;
        }
        mysqli_stmt_bind_param($del, 'iiii', $companyId, $linkedId, $linkedId, $companyId);
        mysqli_stmt_execute($del);
        mysqli_stmt_close($del);
    }

    private static function insertCompanyLinkPair(mysqli $connect, int $companyId, int $linkedId, int $actorUserId): void
    {
        if ($companyId <= 0 || $linkedId <= 0 || $companyId === $linkedId) {
            return;
        }
        $ins = mysqli_prepare($connect, 'INSERT IGNORE INTO client_company_links (company_id, linked_company_id, created_by) VALUES (?, ?, ?)');
        if (!$ins) {
            return;
        }
        mysqli_stmt_bind_param($ins, 'iii', $companyId, $linkedId, $actorUserId);
        mysqli_stmt_execute($ins);
        mysqli_stmt_bind_param($ins, 'iii', $linkedId, $companyId, $actorUserId);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
    }

    /**
     * @param callable(array,string):string $get
     */
    private static function syncCompanyLinks(mysqli $connect, int $companyId, array $linkedIds, int $actorUserId): void
    {
        if ($companyId <= 0 || !self::companyLinksTableExists($connect)) {
            return;
        }
        $linkedIds = array_values(array_unique(array_filter(array_map('intval', $linkedIds), static function ($id) use ($companyId) {
            return $id > 0 && $id !== $companyId;
        })));
        sort($linkedIds);

        require_once dirname(__DIR__) . '/company_members_helper.php';
        $oldIds = company_fetch_linked_company_ids($connect, $companyId);
        sort($oldIds);

        $added = array_values(array_diff($linkedIds, $oldIds));
        $removed = array_values(array_diff($oldIds, $linkedIds));

        foreach ($removed as $lid) {
            self::deleteCompanyLinkPair($connect, $companyId, (int)$lid);
        }
        foreach ($added as $lid) {
            self::insertCompanyLinkPair($connect, $companyId, (int)$lid, $actorUserId);
        }
    }

    /**
     * @param callable(array,string):string $get
     */
    private static function applyLinkedCompaniesFromRow(
        mysqli $connect,
        array $row,
        callable $get,
        int $companyId,
        int $rowNum,
        int $actorUserId,
        array $importCompanyMap = []
    ): string {
        if ($companyId <= 0 || !self::companyLinksTableExists($connect)) {
            return '';
        }
        $linkedRaw = self::getLinkedCompaniesRaw($get, $row);
        if ($linkedRaw === '') {
            return '';
        }
        [$validLinked, $linkErr] = self::resolveLinkedCompanyReferences($connect, $linkedRaw, $companyId, $importCompanyMap);
        if ($linkErr !== '') {
            return 'Row ' . $rowNum . ': ' . $linkErr;
        }
        self::syncCompanyLinks($connect, $companyId, $validLinked, $actorUserId);
        return '';
    }

    /**
     * @param array<string,mixed> $file
     */
    private static function prepareWorkFile(array $file): ?string
    {
        $tmp = $file['tmp_name'];
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
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
        if (!in_array($dup, ['skip', 'create_anyway', 'overwrite'], true)) {
            $dup = 'skip';
        }
        $dry = !empty($jobRow['dry_run']);
        $createdBy = (int)($options['created_by'] ?? $jobRow['user_id'] ?? 0);
        $importCompanyMap = [];
        if (!empty($options['import_company_map']) && is_array($options['import_company_map'])) {
            foreach ($options['import_company_map'] as $k => $v) {
                $importCompanyMap[(string)$k] = (int)$v;
            }
        }
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
        $updated = 0;
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
            $r = self::processOneCompanyRow(
                $connect,
                $row,
                $rowNum,
                $get,
                false,
                $dry,
                $dup,
                $preview,
                0,
                $createdBy,
                $importCompanyMap
            );
            if ($r['action'] === 'skip' && !empty($r['duplicate_company'])) {
                $skip++;
            } elseif ($r['action'] === 'skip' && $r['error_line'] !== '') {
                $fail++;
                Comon_IE_ImportJobService::appendError($connect, (int)$jobRow['id'], $rowNum, $row, $r['error_line']);
            } elseif ($r['action'] === 'skip') {
                $skip++;
            } elseif ($r['action'] === 'insert') {
                $success++;
            } elseif ($r['action'] === 'update') {
                $updated++;
            }
        }
        fclose($fh);
        if ($importCompanyMap !== []) {
            Comon_IE_ImportJobService::mergeOptionsJson($connect, (int)$jobRow['id'], [
                'import_company_map' => $importCompanyMap,
            ]);
        }
        return ['ok' => true, 'processed' => $processed, 'success' => $success, 'skipped' => $skip, 'failed' => $fail, 'updated' => $updated];
    }
}
