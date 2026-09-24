<?php
/**
 * Import users as client (2), staff (3), or admin (1).
 */
class Comon_IE_UserImportRunner
{
    public const SYNC_ROW_THRESHOLD = 300;
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
        $target = isset($post['target_user_type']) ? strtolower(trim((string)$post['target_user_type'])) : '';
        if (!in_array($target, ['client', 'staff', 'admin'], true)) {
            return ['status' => 'error', 'error' => 'Import as (Client / Staff / Admin) is required'];
        }
        if ($target === 'admin' && !$isAdmin) {
            return ['status' => 'error', 'error' => 'Only administrators can import admin users'];
        }
        if ($target === 'staff' && $isStaff) {
            require_once dirname(__DIR__) . '/permissions.php';
            ensure_user_permissions($connect);
            if (!has_permission('staff_create')) {
                return ['status' => 'error', 'error' => 'You do not have permission to import staff'];
            }
        }
        if ($target === 'client' && $isStaff) {
            require_once dirname(__DIR__) . '/permissions.php';
            ensure_user_permissions($connect);
            if (!has_permission('client_create')) {
                return ['status' => 'error', 'error' => 'You do not have permission to import clients'];
            }
        }

        $previewMode = isset($post['preview']) && $post['preview'] === '1';
        $simulate = isset($post['simulate']) && $post['simulate'] === '1';
        $dryRun = $simulate || (!empty($post['dry_run']) && $post['dry_run'] === '1');
        $duplicateStrategy = isset($post['duplicate_strategy']) ? (string)$post['duplicate_strategy'] : 'skip';
        $fallbackRoleId = isset($post['fallback_role_id']) ? (int)$post['fallback_role_id'] : 0;

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

        $accountStatus = $target === 'client' ? 2 : ($target === 'staff' ? 3 : 1);
        $entityType = $target;

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
                'target_user_type' => $target,
                'account_status' => $accountStatus,
                'entity_type' => $entityType,
                'fallback_role_id' => $fallbackRoleId,
                'duplicate_strategy' => $duplicateStrategy,
                'created_by' => (int)$session->userId,
            ];
            $jobId = Comon_IE_ImportJobService::createJob(
                $connect,
                (int)$session->userId,
                'user',
                $target,
                'csv',
                (string)($file['name'] ?? 'import.csv'),
                $rel,
                $fieldMapping,
                $options,
                isset($post['import_mode']) ? (string)$post['import_mode'] : 'create_only',
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

        $get = function (array $row, string $key) use ($fieldMapping, $headerMap, $headers) {
            return Comon_IE_CsvUtilities::rowGet($row, $key, $fieldMapping, $headerMap, $headers);
        };

        while (($row = fgetcsv($fh)) !== false) {
            if (!is_array($row) || count($row) === 0) {
                continue;
            }
            $rowNum++;
            $res = self::processOneUserRow(
                $connect,
                $row,
                $rowNum,
                $get,
                $accountStatus,
                $entityType,
                $fallbackRoleId,
                $duplicateStrategy,
                $previewMode,
                $dryRun,
                $previewRows,
                $previewLimit,
                (int)$session->userId
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
                (int)$session->userId,
                'user',
                $target,
                (string)($file['name'] ?? 'import.csv'),
                isset($post['import_mode']) ? (string)$post['import_mode'] : 'create_only',
                $duplicateStrategy,
                $rowNum,
                $inserted + $updated,
                count($errors),
                $updated,
                $skipped
            );
        }
        return ['status' => 'ok', 'data' => $data];
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
     * Preview row when the main insert/update preview branch is not reached (validation errors, etc.).
     *
     * @param array<int,array<string,mixed>> $previewRows
     */
    private static function pushUserImportPreviewRow(
        bool $previewMode,
        array &$previewRows,
        int $previewLimit,
        int $rowNum,
        string $email,
        string $firstName,
        string $phone,
        string $note
    ): void {
        if (!$previewMode || count($previewRows) >= $previewLimit) {
            return;
        }
        $previewRows[] = [
            'row' => $rowNum,
            'email' => $email,
            'firstName' => $firstName,
            'phone' => $phone,
            'company' => '',
            'note' => $note,
        ];
    }

    /**
     * @param callable(array,string):string $get
     * @param array<int,array<string,mixed>> $previewRows
     * @return array{action:string,error_line:string} action: skip|insert|update|none
     */
    private static function processOneUserRow(
        mysqli $connect,
        array $row,
        int $rowNum,
        callable $get,
        int $accountStatus,
        string $entityType,
        int $fallbackRoleId,
        string $duplicateStrategy,
        bool $previewMode,
        bool $dryRun,
        array &$previewRows,
        int $previewLimit,
        int $actorUserId = 0
    ): array {
        $email = trim($get($row, 'Email'));
        if ($email === '') {
            self::pushUserImportPreviewRow(
                $previewMode,
                $previewRows,
                $previewLimit,
                $rowNum,
                '',
                '',
                '',
                'Skipped: email required (check Email column mapping)'
            );
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Email is required'];
        }
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::pushUserImportPreviewRow(
                $previewMode,
                $previewRows,
                $previewLimit,
                $rowNum,
                $email,
                '',
                '',
                'Skipped: invalid email'
            );
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Invalid email'];
        }
        $firstName = mb_substr(trim($get($row, 'First Name') ?: $get($row, 'Name')), 0, 255);
        if ($firstName === '') {
            self::pushUserImportPreviewRow(
                $previewMode,
                $previewRows,
                $previewLimit,
                $rowNum,
                $email,
                '',
                '',
                'Skipped: first name is required'
            );
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': First name is required'];
        }
        $phone = mb_substr(trim($get($row, 'Phone')), 0, 64);
        $website = mb_substr(trim($get($row, 'Website')), 0, 255);
        $company = mb_substr(trim($get($row, 'Company')), 0, 255);
        $address = mb_substr(trim($get($row, 'Address')), 0, 512);
        $city = mb_substr(trim($get($row, 'City')), 0, 128);
        $state = mb_substr(trim($get($row, 'State')), 0, 128);
        $zip = mb_substr(trim($get($row, 'Zip')), 0, 32);
        $country = mb_substr(trim($get($row, 'Country')), 0, 128);
        $title = mb_substr(trim($get($row, 'Title')), 0, 255);
        $currency = mb_substr(trim($get($row, 'Currency')), 0, 64);
        $assignedTeam = mb_substr(trim($get($row, 'Assigned Team')), 0, 512);
        $rawPassword = trim($get($row, 'Password'));
        $roleIdCsv = trim($get($row, 'Role ID'));
        $roleId = $roleIdCsv !== '' ? (int)$roleIdCsv : $fallbackRoleId;

        if ($entityType === 'staff' && $roleId <= 0) {
            self::pushUserImportPreviewRow(
                $previewMode,
                $previewRows,
                $previewLimit,
                $rowNum,
                $email,
                $firstName,
                '',
                'Skipped: Role ID required for staff'
            );
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Role ID is required for staff (map column or set fallback)'];
        }
        if ($entityType === 'admin') {
            $roleId = null;
        }

        if ($entityType === 'client' && Comon_IE_CompanyImportRunner::companiesTableExists($connect)) {
            $orgName = trim($get($row, 'Org Company Name'));
            $orgCurr = trim($get($row, 'Org Currency'));
            if (($orgName !== '') !== ($orgCurr !== '')) {
                self::pushUserImportPreviewRow(
                    $previewMode,
                    $previewRows,
                    $previewLimit,
                    $rowNum,
                    $email,
                    $firstName,
                    $phone,
                    'Skipped: set both Org Company Name and Org Currency, or leave both empty'
                );
                return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Set both Org Company Name and Org Currency for organization linking, or leave both empty'];
            }
            if ($orgName !== '' && $orgCurr !== '' && !Comon_IE_CompanyImportRunner::isCompanyCurrencyAllowed($connect, $orgCurr)) {
                self::pushUserImportPreviewRow(
                    $previewMode,
                    $previewRows,
                    $previewLimit,
                    $rowNum,
                    $email,
                    $firstName,
                    $phone,
                    'Skipped: Org Currency invalid or not enabled'
                );
                return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Org Currency is invalid or not enabled in system settings'];
            }
        }

        $existing = User::findByEmail($email);
        if ($existing) {
            $exStatus = (int)$existing->accountStatus;
            if ($duplicateStrategy === 'skip' || $duplicateStrategy === 'new_clients_only') {
                if ($previewMode && count($previewRows) < $previewLimit) {
                    $previewRows[] = ['row' => $rowNum, 'email' => $email, 'note' => 'Skipped (duplicate email)'];
                }
                return ['action' => 'skip', 'error_line' => ''];
            }
            if ($duplicateStrategy === 'overwrite' || $duplicateStrategy === 'update_match' || $duplicateStrategy === 'merge') {
                if ($exStatus !== $accountStatus) {
                    self::pushUserImportPreviewRow(
                        $previewMode,
                        $previewRows,
                        $previewLimit,
                        $rowNum,
                        $email,
                        $firstName,
                        $phone,
                        'Skipped: email exists for a different user type'
                    );
                    return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Email exists for a different user type'];
                }
                if ($dryRun || $previewMode) {
                    if ($previewMode && count($previewRows) < $previewLimit) {
                        $note = 'Would update';
                        if ($entityType === 'client' && Comon_IE_CompanyImportRunner::companiesTableExists($connect)) {
                            $lp = Comon_IE_ClientCompanyLinkHelper::linkClientFromOrgRow(
                                $connect,
                                $get,
                                $row,
                                (int)$existing->id,
                                $actorUserId,
                                true,
                                $dryRun
                            );
                            if (!empty($lp['preview_note'])) {
                                $note .= ' (' . $lp['preview_note'] . ')';
                            }
                        }
                        $previewRows[] = ['row' => $rowNum, 'email' => $email, 'note' => $note];
                    }
                    return ['action' => 'update', 'error_line' => ''];
                }
                $ok = self::updateUserRow($connect, (int)$existing->id, $firstName, $title, $phone, $website, $company, $address, $city, $state, $zip, $country, $currency, $assignedTeam, $roleId, $accountStatus);
                if ($ok) {
                    self::saveCustomFieldsFromRow($connect, $row, $get, (int)$existing->id, $entityType);
                    if ($entityType === 'client' && Comon_IE_CompanyImportRunner::companiesTableExists($connect) && Comon_IE_ClientCompanyLinkHelper::orgColumnsPresent($get, $row)) {
                        $lnk = Comon_IE_ClientCompanyLinkHelper::linkClientFromOrgRow(
                            $connect,
                            $get,
                            $row,
                            (int)$existing->id,
                            $actorUserId,
                            false,
                            false
                        );
                        if (!$lnk['ok']) {
                            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': ' . ($lnk['error'] ?? 'Organization link failed')];
                        }
                    }
                }
                return $ok ? ['action' => 'update', 'error_line' => ''] : ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Update failed'];
            }
        }

        if ($previewMode) {
            if (count($previewRows) < $previewLimit) {
                $note = $dryRun ? 'Dry run' : 'Would create';
                if ($entityType === 'client' && Comon_IE_CompanyImportRunner::companiesTableExists($connect)) {
                    $lp = Comon_IE_ClientCompanyLinkHelper::linkClientFromOrgRow($connect, $get, $row, 0, $actorUserId, true, $dryRun);
                    if (!empty($lp['preview_note'])) {
                        $note .= ' (' . $lp['preview_note'] . ')';
                    }
                }
                $previewRows[] = [
                    'row' => $rowNum,
                    'email' => $email,
                    'firstName' => $firstName,
                    'phone' => $phone,
                    'company' => $company,
                    'note' => $note,
                ];
            }
            return ['action' => 'insert', 'error_line' => ''];
        }
        if ($dryRun) {
            return ['action' => 'insert', 'error_line' => ''];
        }

        $hash = password_hash($rawPassword !== '' ? $rawPassword : bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
        $newId = self::insertUserRow(
            $connect,
            $email,
            $hash,
            $accountStatus,
            $firstName,
            $title,
            $address,
            $phone,
            $website,
            $company,
            $city,
            $state,
            $zip,
            $country,
            $currency !== '' ? $currency : '',
            $assignedTeam,
            $roleId,
            $entityType
        );
        if ($newId <= 0) {
            return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': Insert failed'];
        }
        self::saveCustomFieldsFromRow($connect, $row, $get, $newId, $entityType);
        if ($entityType === 'client' && Comon_IE_CompanyImportRunner::companiesTableExists($connect) && Comon_IE_ClientCompanyLinkHelper::orgColumnsPresent($get, $row)) {
            $lnk = Comon_IE_ClientCompanyLinkHelper::linkClientFromOrgRow($connect, $get, $row, $newId, $actorUserId, false, false);
            if (!$lnk['ok']) {
                return ['action' => 'skip', 'error_line' => 'Row ' . $rowNum . ': ' . ($lnk['error'] ?? 'Organization link failed')];
            }
        }
        return ['action' => 'insert', 'error_line' => ''];
    }

    private static function insertUserRow(
        mysqli $connect,
        string $email,
        string $hash,
        int $accountStatus,
        string $firstName,
        string $title,
        string $address,
        string $phone,
        string $website,
        string $company,
        string $city,
        string $state,
        string $zip,
        string $country,
        string $currency,
        string $assignedTeam,
        ?int $roleId,
        string $entityType
    ): int {
        $Projects_ids = '';
        $teams_id = '';
        $fb = '';
        $regDate = date('Y-m-d H:i:s');
        $type_status = '';
        $last_seen = time();
        $session_status = '';
        $status = 0;
        $note = '';
        $user_language = '';

        if ($accountStatus === 2) {
            $id = null;
            $sql = 'INSERT INTO users (
                id, Projects_ids, password, email, accountStatus, firstName, title, address, phone, website, teams_id, fb, regDate, type_status, last_seen, session_status, status, note, city, state, zip, country, user_language, currency, assigned_team
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = $connect->prepare($sql);
            if (!$stmt) {
                return 0;
            }
            $stmt->bind_param(
                'isssissssssssssssssssssss',
                $id,
                $Projects_ids,
                $hash,
                $email,
                $accountStatus,
                $firstName,
                $title,
                $address,
                $phone,
                $website,
                $teams_id,
                $fb,
                $regDate,
                $type_status,
                $last_seen,
                $session_status,
                $status,
                $note,
                $city,
                $state,
                $zip,
                $country,
                $user_language,
                $currency,
                $assignedTeam
            );
        } else {
            $sql = 'INSERT INTO users (Projects_ids, password, email, accountStatus, firstName, title, address, phone, website, teams_id, fb, regDate, type_status, last_seen, session_status, status, note, city, state, zip, country, user_language, role_id, base_salary, attendance_deduction_mode, attendance_deduction_value, login_ip_restriction_enabled, allowed_login_ips, attendance_disabled)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = $connect->prepare($sql);
            if (!$stmt) {
                return 0;
            }
            $base_salary = 0.0;
            $attendance_deduction_mode = null;
            $attendance_deduction_value = 0.0;
            $login_ip_restriction_enabled = 0;
            $allowed_login_ips = '';
            $attendance_disabled = 0;
            $rid = $roleId !== null ? (int)$roleId : null;
            $stmt->bind_param(
                'sssisssssssssisissssssidsdisi',
                $Projects_ids,
                $hash,
                $email,
                $accountStatus,
                $firstName,
                $title,
                $address,
                $phone,
                $website,
                $teams_id,
                $fb,
                $regDate,
                $type_status,
                $last_seen,
                $session_status,
                $status,
                $note,
                $city,
                $state,
                $zip,
                $country,
                $user_language,
                $rid,
                $base_salary,
                $attendance_deduction_mode,
                $attendance_deduction_value,
                $login_ip_restriction_enabled,
                $allowed_login_ips,
                $attendance_disabled
            );
        }
        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }
        $newId = (int)$stmt->insert_id;
        $stmt->close();

        if ($accountStatus === 2 && $company !== '') {
            $cu = $connect->prepare('UPDATE users SET company = ? WHERE id = ?');
            if ($cu) {
                $cu->bind_param('si', $company, $newId);
                $cu->execute();
                $cu->close();
            }
        }

        if ($entityType === 'client') {
            require_once dirname(__DIR__) . '/task_permission.php';
            $tp = new TaskPermission();
            $tp->user_id = $newId;
            $tp->can_create_task = 0;
            $tp->can_delete_task = 0;
            $tp->can_change_status = 0;
            $tp->can_update_task = 0;
            $tp->can_assign_members = 0;
            $tp->can_view_milestones = 0;
            $tp->save();
        }
        return $newId;
    }

    private static function updateUserRow(
        mysqli $connect,
        int $userId,
        string $firstName,
        string $title,
        string $phone,
        string $website,
        string $company,
        string $address,
        string $city,
        string $state,
        string $zip,
        string $country,
        string $currency,
        string $assignedTeam,
        ?int $roleId,
        int $accountStatus
    ): bool {
        if ($accountStatus === 3 && $roleId !== null && $roleId > 0) {
            $stmt = $connect->prepare('UPDATE users SET firstName=?, title=?, phone=?, website=?, company=?, address=?, city=?, state=?, zip=?, country=?, currency=?, assigned_team=?, role_id=? WHERE id=?');
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('ssssssssssssii', $firstName, $title, $phone, $website, $company, $address, $city, $state, $zip, $country, $currency, $assignedTeam, $roleId, $userId);
        } else {
            $stmt = $connect->prepare('UPDATE users SET firstName=?, title=?, phone=?, website=?, company=?, address=?, city=?, state=?, zip=?, country=?, currency=?, assigned_team=? WHERE id=?');
            if (!$stmt) {
                return false;
            }
            $stmt->bind_param('ssssssssssssi', $firstName, $title, $phone, $website, $company, $address, $city, $state, $zip, $country, $currency, $assignedTeam, $userId);
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    private static function saveCustomFieldsFromRow(
        mysqli $connect,
        array $row,
        callable $get,
        int $userId,
        string $entityType
    ): void {
        $cfRes = mysqli_query(
            $connect,
            'SELECT id, field_type FROM custom_fields WHERE entity_type = "' . $connect->real_escape_string($entityType) . '" AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC'
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
            if ($cf['field_type'] === 'multiple_select') {
                $values = array_filter(array_map('trim', preg_split('/[;,]/', $val)));
                $posted[$fid] = array_values($values);
            } else {
                $posted[$fid] = $val;
            }
        }
        if ($posted === []) {
            return;
        }
        require_once dirname(__DIR__) . '/custom-fields/user_profile_values.php';
        save_user_profile_custom_field_values($connect, $userId, $entityType, $posted);
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
        $target = (string)($options['target_user_type'] ?? 'client');
        $accountStatus = (int)($options['account_status'] ?? 2);
        $entityType = (string)($options['entity_type'] ?? $target);
        $fallbackRoleId = (int)($options['fallback_role_id'] ?? 0);
        $dup = (string)($options['duplicate_strategy'] ?? 'skip');
        $dry = !empty($jobRow['dry_run']);
        $offset = (int)$jobRow['processed_rows'];

        $fh = fopen($path, 'r');
        if (!$fh) {
            return ['ok' => false, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'failed' => 0, 'updated' => 0, 'error' => 'open'];
        }
        [$headers, $headerMap] = Comon_IE_CsvUtilities::readHeadersFromHandle($fh);
        $skipped = 0;
        while ($skipped < $offset && fgetcsv($fh) !== false) {
            $skipped++;
        }
        $get = function (array $row, string $key) use ($mapping, $headerMap, $headers) {
            return Comon_IE_CsvUtilities::rowGet($row, $key, $mapping, $headerMap, $headers);
        };
        $processed = 0;
        $success = 0;
        $fail = 0;
        $upd = 0;
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
            $actorId = (int)($options['created_by'] ?? $jobRow['user_id'] ?? 0);
            $r = self::processOneUserRow(
                $connect,
                $row,
                $rowNum,
                $get,
                $accountStatus,
                $entityType,
                $fallbackRoleId,
                $dup,
                false,
                $dry,
                $preview,
                0,
                $actorId
            );
            if ($r['action'] === 'skip' && $r['error_line'] !== '') {
                $fail++;
                Comon_IE_ImportJobService::appendError($connect, (int)$jobRow['id'], $rowNum, $row, $r['error_line']);
            } elseif ($r['action'] === 'skip') {
                $skip++;
            } elseif ($r['action'] === 'insert') {
                $success++;
            } elseif ($r['action'] === 'update') {
                $upd++;
            }
        }
        fclose($fh);
        return ['ok' => true, 'processed' => $processed, 'success' => $success, 'skipped' => $skip, 'failed' => $fail, 'updated' => $upd];
    }
}
