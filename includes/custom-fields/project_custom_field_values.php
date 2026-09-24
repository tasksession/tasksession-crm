<?php
/*
================================================================================
  Project custom field values — fetch helpers for overview / activity logging
================================================================================
 */

require_once __DIR__ . '/company_custom_field_values.php';

if (!function_exists('project_custom_field_values_table_exists')) {
    function project_custom_field_values_table_exists(mysqli $conn): bool
    {
        $r = mysqli_query($conn, "SHOW TABLES LIKE 'project_custom_field_values'");
        return $r && mysqli_num_rows($r) > 0;
    }
}

if (!function_exists('fetch_project_custom_field_values_map')) {
    /**
     * @return array<int, array{id:int, field_type:string, label:string, field_value:?string}>
     */
    function fetch_project_custom_field_values_map(mysqli $conn, int $projectId): array
    {
        if ($projectId <= 0 || !project_custom_field_values_table_exists($conn)) {
            return [];
        }

        $map = [];
        $stmt = $conn->prepare(
            'SELECT cf.id, cf.field_type, cf.label, cfv.field_value
             FROM custom_fields cf
             INNER JOIN project_custom_field_values cfv
               ON cf.id = cfv.custom_field_id AND cfv.project_id = ?
             WHERE cf.entity_type = ? AND COALESCE(cf.is_disabled, 0) = 0'
        );
        if (!$stmt) {
            return [];
        }
        $entity = 'project';
        $stmt->bind_param('is', $projectId, $entity);
        if (!$stmt->execute()) {
            $stmt->close();
            return [];
        }
        $res = $stmt->get_result();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $fid = (int) ($row['id'] ?? 0);
                if ($fid <= 0) {
                    continue;
                }
                $map[$fid] = [
                    'id' => $fid,
                    'field_type' => (string) ($row['field_type'] ?? 'text'),
                    'label' => trim((string) ($row['label'] ?? '')),
                    'field_value' => $row['field_value'],
                ];
            }
        }
        $stmt->close();

        return $map;
    }
}

if (!function_exists('fetch_project_custom_field_view_rows')) {
    /**
     * @return array<int, array{id:int, label:string, field_type:string, value_text:string, description:string, list_items:?string}>
     */
    function fetch_project_custom_field_view_rows(mysqli $conn, int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $defs = [];
        $dq = mysqli_query(
            $conn,
            "SELECT id, field_type, label, description, list_items FROM custom_fields
             WHERE entity_type = 'project' AND COALESCE(is_disabled, 0) = 0
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
        if (project_custom_field_values_table_exists($conn)) {
            $vq = mysqli_query(
                $conn,
                'SELECT custom_field_id, field_value FROM project_custom_field_values WHERE project_id = ' . (int) $projectId
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
                'id' => $fid,
                'label' => $label,
                'field_type' => $ft,
                'value_text' => $text,
                'description' => trim((string) ($def['description'] ?? '')),
                'list_items' => $def['list_items'] ?? null,
            ];
        }

        return $out;
    }
}

if (!function_exists('project_cf_field_type_icon_svg')) {
    function project_cf_field_type_icon_svg(string $fieldType): string
    {
        $map = [
            'text' => 'document-text',
            'tel' => 'phone',
            'date' => 'calendar',
            'checkbox' => 'check-circle',
            'number' => 'view-grid',
            'link' => 'link',
            'single_select' => 'list',
            'multiple_select' => 'inbox-stack',
        ];
        $name = $map[$fieldType] ?? 'document-text';
        return function_exists('ts_icon') ? ts_icon($name) : '';
    }
}

if (!function_exists('project_cf_field_type_label')) {
    function project_cf_field_type_label(string $fieldType): string
    {
        $map = [
            'text' => 'Text',
            'tel' => 'Phone',
            'date' => 'Date',
            'checkbox' => 'Boolean',
            'number' => 'Number',
            'link' => 'Link',
            'single_select' => 'Dropdown',
            'multiple_select' => 'Multi select',
        ];

        return $map[$fieldType] ?? ucfirst(str_replace('_', ' ', $fieldType));
    }
}
