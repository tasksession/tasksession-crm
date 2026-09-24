<?php
/*
================================================================================
  User profile custom field values — save helper (client / staff entity types)
  Location: includes/custom-fields/user_profile_values.php
================================================================================
 */

if (!function_exists('save_user_profile_custom_field_values')) {

    /**
     * Persist POSTed custom field values for a user. Only fields that exist in
     * custom_fields for the given entity_type are accepted.
     *
     * @param mysqli $connect
     * @param int $userId
     * @param string $entityType 'client', 'staff', or 'admin'
     * @param array|null $posted Map field_id => raw value (string, array for multi-select, etc.)
     * @return void
     */
    function save_user_profile_custom_field_values(mysqli $connect, int $userId, string $entityType, ?array $posted): void
    {
        if ($userId <= 0) {
            return;
        }
        if ($entityType !== 'client' && $entityType !== 'staff' && $entityType !== 'admin') {
            return;
        }
        if ($posted === null || !is_array($posted) || $posted === []) {
            return;
        }

        $normPosted = [];
        foreach ($posted as $k => $v) {
            $normPosted[(int)$k] = $v;
        }
        $posted = $normPosted;

        $stmtMap = $connect->prepare(
            'SELECT id, field_type, is_required FROM custom_fields WHERE entity_type = ? AND COALESCE(is_disabled, 0) = 0'
        );
        if (!$stmtMap) {
            return;
        }
        $stmtMap->bind_param('s', $entityType);
        $stmtMap->execute();
        $res = $stmtMap->get_result();
        $customFieldsMap = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $customFieldsMap[(int)$row['id']] = $row;
            }
        }
        $stmtMap->close();

        if ($customFieldsMap === []) {
            return;
        }

        foreach ($customFieldsMap as $fid => $def) {
            if (($def['field_type'] ?? '') === 'checkbox' && !array_key_exists((int)$fid, $posted)) {
                $posted[(int)$fid] = '0';
            }
        }

        $upsert = $connect->prepare(
            'INSERT INTO user_custom_field_values (user_id, custom_field_id, field_value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = CURRENT_TIMESTAMP'
        );
        if (!$upsert) {
            return;
        }

        $deleteEmpty = $connect->prepare(
            'DELETE FROM user_custom_field_values WHERE user_id = ? AND custom_field_id = ?'
        );

        foreach ($posted as $fieldIdKey => $fieldValue) {
            $fieldIdInt = (int)$fieldIdKey;
            if ($fieldIdInt <= 0 || !isset($customFieldsMap[$fieldIdInt])) {
                continue;
            }

            $fieldDef = $customFieldsMap[$fieldIdInt];
            $fieldType = (string)($fieldDef['field_type'] ?? 'text');
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
                $valueToStore = trim((string)$fieldValue);
                if ($valueToStore === '') {
                    $isEmpty = true;
                    $valueToStore = null;
                }
            }

            if ($isEmpty && (int)($fieldDef['is_required'] ?? 0) !== 1) {
                if ($deleteEmpty) {
                    $deleteEmpty->bind_param('ii', $userId, $fieldIdInt);
                    $deleteEmpty->execute();
                }
                continue;
            }

            if ($valueToStore === null && (int)($fieldDef['is_required'] ?? 0) !== 1) {
                if ($deleteEmpty) {
                    $deleteEmpty->bind_param('ii', $userId, $fieldIdInt);
                    $deleteEmpty->execute();
                }
                continue;
            }

            if ($valueToStore !== null) {
                $upsert->bind_param('iis', $userId, $fieldIdInt, $valueToStore);
                $upsert->execute();
            }
        }

        $upsert->close();
        if ($deleteEmpty) {
            $deleteEmpty->close();
        }
    }
}
