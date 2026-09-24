<?php
/**
 * Optional client_companies + client_company_members linking during client user CSV import
 * when rows include Org-prefixed columns (see plan: inline org).
 */
class Comon_IE_ClientCompanyLinkHelper
{
    /**
     * Both Org Company Name and Org Currency non-empty.
     *
     * @param callable(array,string):string $get
     */
    public static function orgColumnsPresent(callable $get, array $row): bool
    {
        $n = trim($get($row, 'Org Company Name'));
        $c = trim($get($row, 'Org Currency'));
        return $n !== '' && $c !== '';
    }

    /**
     * @param callable(array,string):string $get
     * @return array{ok:bool,error?:string,preview_note?:string}
     */
    public static function linkClientFromOrgRow(
        mysqli $connect,
        callable $get,
        array $row,
        int $clientUserId,
        int $actorUserId,
        bool $previewMode,
        bool $dryRun
    ): array {
        if (!self::orgColumnsPresent($get, $row)) {
            return ['ok' => true];
        }
        if (!Comon_IE_CompanyImportRunner::companiesTableExists($connect)) {
            return ['ok' => true];
        }

        $name = mb_substr(trim($get($row, 'Org Company Name')), 0, 191);
        $currency = trim($get($row, 'Org Currency'));
        if ($name === '' || $currency === '') {
            return ['ok' => false, 'error' => 'Incomplete organization columns: set both Org Company Name and Org Currency, or leave both empty'];
        }
        if (!Comon_IE_CompanyImportRunner::isCompanyCurrencyAllowed($connect, $currency)) {
            return ['ok' => false, 'error' => 'Org Currency is invalid or not enabled in system settings'];
        }

        $vat = mb_substr(trim($get($row, 'Org VAT Number')), 0, 64);
        $phone = mb_substr(trim($get($row, 'Org Phone')), 0, 64);
        $email = mb_substr(trim($get($row, 'Org Email')), 0, 191);
        $website = mb_substr(trim($get($row, 'Org Website')), 0, 512);
        $address = mb_substr(trim($get($row, 'Org Address')), 0, 512);
        $city = mb_substr(trim($get($row, 'Org City')), 0, 128);
        $state = mb_substr(trim($get($row, 'Org State')), 0, 128);
        $zip = mb_substr(trim($get($row, 'Org Zip')), 0, 32);
        $country = mb_substr(trim($get($row, 'Org Country')), 0, 128);
        $billAddr = mb_substr(trim($get($row, 'Org Billing address')), 0, 512);
        $billStreet = mb_substr(trim($get($row, 'Org Billing Street')), 0, 512);
        $billCity = mb_substr(trim($get($row, 'Org Billing City')), 0, 128);
        $billState = mb_substr(trim($get($row, 'Org Billing State')), 0, 128);
        $billZip = mb_substr(trim($get($row, 'Org Billing Zip')), 0, 32);
        $billCountry = mb_substr(trim($get($row, 'Org Billing Country')), 0, 128);

        $previewNote = 'Link org "' . $name . '"' . ($vat !== '' ? ' (VAT ' . $vat . ')' : '');

        if ($previewMode || $dryRun) {
            $extra = '';
            if (self::orgCompanyCustomFieldsNonEmpty($connect, $get, $row)) {
                $extra = ' (with organization custom field values)';
            }
            return ['ok' => true, 'preview_note' => $previewNote . $extra];
        }

        if ($clientUserId <= 0) {
            return ['ok' => true];
        }

        $companyId = Comon_IE_CompanyImportRunner::findCompanyIdByNameAndVat($connect, $name, $vat);
        if ($companyId <= 0) {
            $createdBy = $actorUserId > 0 ? $actorUserId : null;
            $insOk = self::insertCompanyRow(
                $connect,
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
                $createdBy
            );
            if ($insOk <= 0) {
                return ['ok' => false, 'error' => 'Could not create client company record'];
            }
            $companyId = $insOk;
        }

        $attach = self::attachMemberIfNeeded($connect, $companyId, $clientUserId);
        if (!$attach['ok']) {
            return ['ok' => false, 'error' => $attach['error'] ?? 'Could not link client to company'];
        }
        self::saveOrgCompanyCustomFieldsFromRow($connect, $get, $row, $companyId);
        return ['ok' => true];
    }

    /**
     * @param callable(array,string):string $get
     */
    private static function orgCompanyCustomFieldsNonEmpty(mysqli $connect, callable $get, array $row): bool
    {
        $cfRes = mysqli_query(
            $connect,
            'SELECT id FROM custom_fields WHERE entity_type = \'company\' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC'
        );
        if (!$cfRes) {
            return false;
        }
        while ($cf = mysqli_fetch_assoc($cfRes)) {
            $fid = (int)($cf['id'] ?? 0);
            if ($fid <= 0) {
                continue;
            }
            if (trim($get($row, 'OrgCustomField_' . $fid)) !== '') {
                mysqli_free_result($cfRes);
                return true;
            }
        }
        mysqli_free_result($cfRes);
        return false;
    }

    /**
     * @param callable(array,string):string $get
     */
    private static function saveOrgCompanyCustomFieldsFromRow(mysqli $connect, callable $get, array $row, int $companyId): void
    {
        if ($companyId <= 0) {
            return;
        }
        $tchk = @mysqli_query($connect, "SHOW TABLES LIKE 'company_custom_field_values'");
        if (!$tchk || mysqli_num_rows($tchk) === 0) {
            if ($tchk) {
                mysqli_free_result($tchk);
            }
            return;
        }
        mysqli_free_result($tchk);

        $cfRes = mysqli_query(
            $connect,
            'SELECT id, field_type FROM custom_fields WHERE entity_type = \'company\' AND COALESCE(is_disabled, 0) = 0 ORDER BY sort_order ASC, id ASC'
        );
        if (!$cfRes) {
            return;
        }
        $posted = [];
        while ($cf = mysqli_fetch_assoc($cfRes)) {
            $fid = (int)($cf['id'] ?? 0);
            if ($fid <= 0) {
                continue;
            }
            $val = $get($row, 'OrgCustomField_' . $fid);
            if ($val === '') {
                continue;
            }
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

    private static function insertCompanyRow(
        mysqli $connect,
        string $name,
        string $vat,
        string $phone,
        string $email,
        string $website,
        string $currency,
        string $address,
        string $city,
        string $state,
        string $zip,
        string $country,
        string $billAddr,
        string $billStreet,
        string $billCity,
        string $billState,
        string $billZip,
        string $billCountry,
        ?int $createdBy
    ): int {
        $logoInit = '';
        if ($createdBy !== null && $createdBy > 0) {
            $cb = (int)$createdBy;
            $stmt = mysqli_prepare(
                $connect,
                'INSERT INTO client_companies (name, logo_path, vat_number, phone, email, website, currency,
                address, city, state, zip, country,
                billing_address, billing_street, billing_city, billing_state, billing_zip, billing_country,
                created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            if (!$stmt) {
                return 0;
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
                $cb
            );
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return 0;
            }
            $newId = (int)mysqli_insert_id($connect);
            mysqli_stmt_close($stmt);
            return $newId;
        }
        $stmt = mysqli_prepare(
            $connect,
            'INSERT INTO client_companies (name, logo_path, vat_number, phone, email, website, currency,
            address, city, state, zip, country,
            billing_address, billing_street, billing_city, billing_state, billing_zip, billing_country)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        if (!$stmt) {
            return 0;
        }
        mysqli_stmt_bind_param(
            $stmt,
            'ssssssssssssssssss',
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
            $billCountry
        );
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            return 0;
        }
        $newId = (int)mysqli_insert_id($connect);
        mysqli_stmt_close($stmt);
        return $newId;
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    private static function attachMemberIfNeeded(mysqli $connect, int $companyId, int $clientUserId): array
    {
        if ($companyId <= 0 || $clientUserId <= 0) {
            return ['ok' => true];
        }
        $chk = mysqli_query(
            $connect,
            'SELECT 1 FROM client_company_members WHERE company_id = ' . (int)$companyId . ' AND user_id = ' . (int)$clientUserId . ' LIMIT 1'
        );
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_free_result($chk);
            return ['ok' => true];
        }
        if ($chk) {
            mysqli_free_result($chk);
        }
        $pq = mysqli_query(
            $connect,
            'SELECT COUNT(*) AS c FROM client_company_members WHERE company_id = ' . (int)$companyId . ' AND is_primary = 1'
        );
        $isPrimary = 1;
        if ($pq && ($pr = mysqli_fetch_assoc($pq))) {
            $isPrimary = ((int)($pr['c'] ?? 0) > 0) ? 0 : 1;
        }
        if ($pq) {
            mysqli_free_result($pq);
        }
        $ins = mysqli_prepare($connect, 'INSERT IGNORE INTO client_company_members (company_id, user_id, is_primary) VALUES (?, ?, ?)');
        if (!$ins) {
            return ['ok' => false, 'error' => 'prepare member failed'];
        }
        mysqli_stmt_bind_param($ins, 'iii', $companyId, $clientUserId, $isPrimary);
        $ok = mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => 'insert member failed'];
    }
}
