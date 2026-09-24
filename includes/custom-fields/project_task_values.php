<?php
/*
================================================================================
  Project / task custom field values — save helpers
================================================================================
 */

if (!function_exists('comon_save_custom_field_values_generic')) {

    /**
     * @param mysqli $connect
     * @param string $valuesTable 'project_custom_field_values' | 'task_custom_field_values'
     * @param string $parentColumn 'project_id' | 'task_id'
     * @param int $parentId
     * @param string $entityType 'project' | 'task' (must match custom_fields.entity_type)
     * @param array|null $posted custom_fields[field_id] from POST
     */
    function comon_save_custom_field_values_generic(
        mysqli $connect,
        string $valuesTable,
        string $parentColumn,
        int $parentId,
        string $entityType,
        ?array $posted
    ): void {
        if ($parentId <= 0) {
            return;
        }
        if ($posted === null || !is_array($posted) || $posted === []) {
            return;
        }

        $allowedTables = ['project_custom_field_values', 'task_custom_field_values'];
        $allowedCols = ['project_id', 'task_id'];
        if (!in_array($valuesTable, $allowedTables, true) || !in_array($parentColumn, $allowedCols, true)) {
            return;
        }

        $normPosted = [];
        foreach ($posted as $k => $v) {
            $normPosted[(int) $k] = $v;
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
                $customFieldsMap[(int) $row['id']] = $row;
            }
        }
        $stmtMap->close();

        if ($customFieldsMap === []) {
            return;
        }

        foreach ($customFieldsMap as $fid => $def) {
            if (($def['field_type'] ?? '') === 'checkbox' && !array_key_exists((int) $fid, $posted)) {
                $posted[(int) $fid] = '0';
            }
        }

        $sqlUpsert = 'INSERT INTO `' . $valuesTable . '` (`' . $parentColumn . '`, `custom_field_id`, `field_value`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE field_value = VALUES(field_value), updated_at = CURRENT_TIMESTAMP';
        $upsert = $connect->prepare($sqlUpsert);
        if (!$upsert) {
            return;
        }

        $sqlDel = 'DELETE FROM `' . $valuesTable . '` WHERE `' . $parentColumn . '` = ? AND custom_field_id = ?';
        $deleteEmpty = $connect->prepare($sqlDel);

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
                    $deleteEmpty->bind_param('ii', $parentId, $fieldIdInt);
                    $deleteEmpty->execute();
                }
                continue;
            }

            if ($valueToStore === null && (int) ($fieldDef['is_required'] ?? 0) !== 1) {
                if ($deleteEmpty) {
                    $deleteEmpty->bind_param('ii', $parentId, $fieldIdInt);
                    $deleteEmpty->execute();
                }
                continue;
            }

            if ($valueToStore !== null) {
                $upsert->bind_param('iis', $parentId, $fieldIdInt, $valueToStore);
                $upsert->execute();
            }
        }

        $upsert->close();
        if ($deleteEmpty) {
            $deleteEmpty->close();
        }
    }
}

if (!function_exists('save_project_custom_field_values')) {
    function save_project_custom_field_values(mysqli $connect, int $projectId, ?array $posted): void
    {
        comon_save_custom_field_values_generic(
            $connect,
            'project_custom_field_values',
            'project_id',
            $projectId,
            'project',
            $posted
        );
    }
}

if (!function_exists('save_task_custom_field_values')) {
    function save_task_custom_field_values(mysqli $connect, int $taskId, ?array $posted): void
    {
        comon_save_custom_field_values_generic(
            $connect,
            'task_custom_field_values',
            'task_id',
            $taskId,
            'task',
            $posted
        );
    }
}
