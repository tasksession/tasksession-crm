<?php
/*
================================================================================
  Company custom field values — save helper (entity_type = company)
================================================================================
 */

if (!function_exists('company_custom_field_values_table_exists')) {
    function company_custom_field_values_table_exists(mysqli $conn): bool
    {
        $r = mysqli_query($conn, "SHOW TABLES LIKE 'company_custom_field_values'");
        return $r && mysqli_num_rows($r) > 0;
    }
}

if (!function_exists('save_company_custom_field_values')) {

    /**
     * Persist posted custom field values for a client company row.
     *
     * @param mysqli $conn
     * @param int $companyId
     * @param array|null $posted Map field_id => raw value
     */
    function save_company_custom_field_values(mysqli $conn, int $companyId, ?array $posted): void
    {
        if ($companyId <= 0 || !company_custom_field_values_table_exists($conn)) {
            return;
        }
        if ($posted === null || !is_array($posted) || $posted === []) {
            return;
        }

        $normPosted = [];
        foreach ($posted as $k => $v) {
            $normPosted[(int) $k] = $v;
        }
        $posted = $normPosted;

        $entityType = 'company';
        $entityTypeEsc = $conn->real_escape_string($entityType);
        $mapRes = mysqli_query(
            $conn,
            "SELECT id, field_type, is_required FROM custom_fields WHERE entity_type = '" . $entityTypeEsc . "' AND COALESCE(is_disabled, 0) = 0"
        );
        $customFieldsMap = [];
        if ($mapRes) {
            while ($row = mysqli_fetch_assoc($mapRes)) {
                $customFieldsMap[(int) $row['id']] = $row;
            }
            mysqli_free_result($mapRes);
        }

        if ($customFieldsMap === []) {
            return;
        }

        foreach ($customFieldsMap as $fid => $def) {
            if (($def['field_type'] ?? '') === 'checkbox' && !array_key_exists((int) $fid, $posted)) {
                $posted[(int) $fid] = '0';
            }
        }

        $upsert = $conn->prepare(
            'INSERT INTO company_custom_field_values (company_id, custom_field_id, field_value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = CURRENT_TIMESTAMP'
        );
        if (!$upsert) {
            return;
        }

        $deleteEmpty = $conn->prepare(
            'DELETE FROM company_custom_field_values WHERE company_id = ? AND custom_field_id = ?'
        );

        foreach ($posted as $fieldIdKey => $fieldValue) {
            $fieldIdInt = (int) $fieldIdKey;
            if ($fieldIdInt <= 0 || !isset($customFieldsMap[$fieldIdInt])) {
                continue;
            }

            $fieldDef = $customFieldsMap[$fieldIdInt];
            $fieldType = (string) ($fieldDef['field_type'] ?? 'text');
            $valueToStore = null;
            $isEmpty = false;

            if ($fieldType === 'multiple_select') {
                if (is_array($fieldValue)) {
                    $filtered = array_values(array_filter(array_map('strval', $fieldValue), static function ($v) {
                        return $v !== '';
                    }));
                    if ($filtered === []) {
                        $isEmpty = true;
                        $valueToStore = null;
                    } else {
                        $valueToStore = json_encode($filtered);
                    }
                } elseif (is_string($fieldValue) && trim($fieldValue) !== '') {
                    $split = array_filter(array_map('trim', preg_split('/[;,]/', $fieldValue)));
                    if ($split === []) {
                        $isEmpty = true;
                        $valueToStore = null;
                    } else {
                        $valueToStore = json_encode(array_values($split));
                    }
                } else {
                    $isEmpty = true;
                    $valueToStore = null;
                }
            } elseif ($fieldType === 'checkbox') {
                $valueToStore = ($fieldValue == '1' || $fieldValue === true || $fieldValue === 'on') ? '1' : '0';
                $isEmpty = false;
            } else {
                $valueToStore = trim((string) $fieldValue);
                if ($valueToStore === '') {
                    $isEmpty = true;
                    $valueToStore = null;
                }
            }

            if ($isEmpty && (int) ($fieldDef['is_required'] ?? 0) !== 1) {
                if ($deleteEmpty) {
                    $deleteEmpty->bind_param('ii', $companyId, $fieldIdInt);
                    $deleteEmpty->execute();
                }
                continue;
            }

            if ($valueToStore === null && (int) ($fieldDef['is_required'] ?? 0) !== 1) {
                if ($deleteEmpty) {
                    $deleteEmpty->bind_param('ii', $companyId, $fieldIdInt);
                    $deleteEmpty->execute();
                }
                continue;
            }

            if ($valueToStore !== null) {
                $upsert->bind_param('iis', $companyId, $fieldIdInt, $valueToStore);
                $upsert->execute();
            }
        }

        $upsert->close();
        if ($deleteEmpty) {
            $deleteEmpty->close();
        }
    }
}

if (!function_exists('validate_company_custom_field_values_required')) {
    /**
     * @return string|null Error message if a required field is missing, else null.
     */
    function validate_company_custom_field_values_required(mysqli $conn, ?array $posted): ?string
    {
        if (!company_custom_field_values_table_exists($conn)) {
            return null;
        }
        $posted = is_array($posted) ? $posted : [];
        $normPosted = [];
        foreach ($posted as $k => $v) {
            $normPosted[(int) $k] = $v;
        }

        $entityType = 'company';
        $entityTypeEsc = $conn->real_escape_string($entityType);
        $reqRes = mysqli_query(
            $conn,
            "SELECT id, field_type, label, is_required FROM custom_fields WHERE entity_type = '" . $entityTypeEsc . "' AND COALESCE(is_disabled, 0) = 0 AND is_required = 1"
        );
        $required = [];
        if ($reqRes) {
            while ($row = mysqli_fetch_assoc($reqRes)) {
                $required[(int) $row['id']] = $row;
            }
            mysqli_free_result($reqRes);
        }

        foreach ($required as $fid => $def) {
            $fieldType = (string) ($def['field_type'] ?? 'text');
            $label = trim((string) ($def['label'] ?? 'Custom field'));
            if ($label === '') {
                $label = 'Custom field';
            }
            $raw = $normPosted[$fid] ?? null;

            if ($fieldType === 'checkbox') {
                $checked = ($raw == '1' || $raw === true || $raw === 'on');
                if (!$checked) {
                    return $label . ' is required.';
                }
                continue;
            }

            if ($fieldType === 'multiple_select') {
                $empty = true;
                if (is_array($raw)) {
                    $filtered = array_filter(array_map('strval', $raw), static function ($v) {
                        return $v !== '';
                    });
                    $empty = $filtered === [];
                } elseif (is_string($raw) && trim($raw) !== '') {
                    $empty = false;
                }
                if ($empty) {
                    return $label . ' is required.';
                }
                continue;
            }

            if ($raw === null || trim((string) $raw) === '') {
                return $label . ' is required.';
            }
        }

        return null;
    }
}

if (!function_exists('fetch_company_custom_field_values_for_hydrate')) {
    /**
     * @return array<int, array{id:int, field_type:string, value:mixed}>
     */
    function fetch_company_custom_field_values_for_hydrate(mysqli $conn, int $companyId): array
    {
        if ($companyId <= 0 || !company_custom_field_values_table_exists($conn)) {
            return [];
        }
        $sql = 'SELECT f.id, f.field_type, v.field_value AS raw_value
            FROM company_custom_field_values v
            INNER JOIN custom_fields f ON f.id = v.custom_field_id AND f.entity_type = \'company\'
            WHERE v.company_id = ' . (int) $companyId;
        $q = mysqli_query($conn, $sql);
        if (!$q) {
            return [];
        }
        $out = [];
        while ($row = mysqli_fetch_assoc($q)) {
            $fid = (int) ($row['id'] ?? 0);
            $ft = (string) ($row['field_type'] ?? 'text');
            $raw = $row['raw_value'] ?? null;
            $value = $raw;
            if ($ft === 'multiple_select' && is_string($raw) && $raw !== '') {
                $dec = json_decode($raw, true);
                $value = is_array($dec) ? $dec : [];
            }
            $out[] = [
                'id' => $fid,
                'field_type' => $ft,
                'value' => $value,
            ];
        }
        mysqli_free_result($q);

        return $out;
    }
}

if (!function_exists('fetch_company_custom_field_view_rows')) {
    /**
     * Rows for read-only company profile: every active company definition, with formatted value (— if empty).
     *
     * @return array<int, array{label:string, field_type:string, value_text:string}>
     */
    function fetch_company_custom_field_view_rows(mysqli $conn, int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }

        $defs = [];
        $dq = mysqli_query(
            $conn,
            "SELECT id, field_type, label, list_items FROM custom_fields
             WHERE entity_type = 'company' AND COALESCE(is_disabled, 0) = 0
             ORDER BY sort_order ASC, id ASC"
        );
        if ($dq) {
            while ($row = mysqli_fetch_assoc($dq)) {
                $defs[] = $row;
            }
            mysqli_free_result($dq);
        }
        if ($defs === []) {
            return [];
        }

        $byField = [];
        if (company_custom_field_values_table_exists($conn)) {
            $vq = mysqli_query(
                $conn,
                'SELECT custom_field_id, field_value FROM company_custom_field_values WHERE company_id = ' . (int) $companyId
            );
            if ($vq) {
                while ($row = mysqli_fetch_assoc($vq)) {
                    $fid = (int) ($row['custom_field_id'] ?? 0);
                    if ($fid > 0) {
                        $byField[$fid] = $row['field_value'];
                    }
                }
                mysqli_free_result($vq);
            }
        }

        $dash = '—';
        $out = [];
        foreach ($defs as $def) {
            $fid = (int) ($def['id'] ?? 0);
            $ft = (string) ($def['field_type'] ?? 'text');
            $label = trim((string) ($def['label'] ?? ''));
            if ($label === '') {
                $label = 'Field #' . $fid;
            }
            $raw = array_key_exists($fid, $byField) ? $byField[$fid] : null;
            $text = company_cf_format_value_for_view($ft, $raw, $def['list_items'] ?? null);
            if ($text === '' || $text === null) {
                $text = $dash;
            }
            $out[] = [
                'label' => $label,
                'field_type' => $ft,
                'value_text' => $text,
            ];
        }

        return $out;
    }
}

if (!function_exists('company_cf_format_value_for_view')) {
    /**
     * @param mixed $listItemsJson JSON string or null from custom_fields.list_items
     */
    function company_cf_format_value_for_view(string $fieldType, $raw, $listItemsJson): string
    {
        if ($raw === null) {
            return '';
        }
        $s = is_string($raw) ? trim($raw) : $raw;
        if ($s === '' && $s !== '0' && $s !== 0) {
            return '';
        }

        if ($fieldType === 'checkbox') {
            return ($s === '1' || $s === 1 || $s === true) ? 'Yes' : 'No';
        }
        if ($fieldType === 'multiple_select') {
            if (!is_string($raw) || trim($raw) === '') {
                return '';
            }
            $dec = json_decode($raw, true);
            if (!is_array($dec) || $dec === []) {
                return '';
            }
            $parts = array_values(array_filter(array_map('strval', $dec), static function ($v) {
                return $v !== '';
            }));

            return $parts === [] ? '' : implode(', ', $parts);
        }
        if ($fieldType === 'number') {
            return is_numeric($s) ? (string) $s : (string) $raw;
        }
        if ($fieldType === 'date' && is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}/', $raw)) {
            return substr($raw, 0, 10);
        }

        return is_string($raw) ? $raw : (string) $raw;
    }
}
