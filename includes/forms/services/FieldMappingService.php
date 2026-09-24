<?php
/**
 * Forms Module – Map incoming payload to CRM lead columns and custom fields using form_field_mappings.
 */

class FieldMappingService
{
    /** @var mysqli */
    private $connect;
    /** @var array */
    private $config;

    public function __construct($connect, array $config = [])
    {
        $this->connect = $connect;
        $this->config = $config;
    }

    /**
     * Map parsed payload to lead data using integration (and optional form) mappings.
     * @param string $integrationSourceKey
     * @param int|null $formId
     * @param array $payload Incoming key => value (e.g. name, email, phone)
     * @return array Mapped lead data (keys = lead columns + custom_fields) or ['_errors' => [...], '_error_message' => ...]
     */
    public function map($integrationSourceKey, $formId, array $payload)
    {
        $integrationId = $this->getIntegrationIdBySourceKey($integrationSourceKey);
        if ($integrationId <= 0) {
            return ['_errors' => ['integration' => 'Unknown integration'], '_error_message' => 'Unknown integration'];
        }

        $mappings = $this->getMappings($integrationId, $formId);
        $whitelist = $this->config['lead_columns_whitelist'] ?? [];
        $customFieldIds = $this->getLeadCustomFieldIds();
        $result = [];
        $errors = [];

        foreach ($mappings as $m) {
            if (!empty($m['ignore_field'])) continue;
            $sourceVal = isset($payload[$m['source_field']]) ? $payload[$m['source_field']] : $m['default_value'];
            if ($sourceVal === null || $sourceVal === '') {
                $sourceVal = $m['default_value'];
            }
            $sourceVal = is_scalar($sourceVal) ? trim((string)$sourceVal) : (is_array($sourceVal) ? $sourceVal : '');

            if (!empty($m['is_required']) && $sourceVal === '' && $sourceVal !== '0') {
                $errors[$m['source_field']] = 'Required field missing';
                continue;
            }

            $dest = $m['destination_field'];
            $destType = $m['destination_type'] ?? 'lead_column';

            if ($destType === 'custom_field') {
                $cfId = (int)$dest;
                if ($cfId > 0 && isset($customFieldIds[$cfId])) {
                    if (!isset($result['custom_fields'])) $result['custom_fields'] = [];
                    $result['custom_fields'][$cfId] = $sourceVal;
                }
            } else {
                if (in_array($dest, $whitelist, true)) {
                    $result[$dest] = $sourceVal;
                }
            }
        }

        if (!empty($errors)) {
            $result['_errors'] = $errors;
            $result['_error_message'] = implode(' ', $errors);
            return $result;
        }

        return $result;
    }

    private function getIntegrationIdBySourceKey($sourceKey)
    {
        $sourceKey = trim((string)$sourceKey);
        if ($sourceKey === '') return 0;
        $stmt = $this->connect->prepare("SELECT id FROM form_integrations WHERE source_key = ? AND status = 'active' LIMIT 1");
        if (!$stmt) return 0;
        $stmt->bind_param('s', $sourceKey);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row ? (int)$row['id'] : 0;
    }

    private function getMappings($integrationId, $formId)
    {
        $integrationId = (int)$integrationId;
        $formIdInt = $formId ? (int)$formId : null;
        $stmt = $this->connect->prepare("SELECT source_field, destination_field, destination_type, is_required, default_value, ignore_field FROM form_field_mappings WHERE integration_id = ? AND (form_id IS NULL OR form_id = ?) ORDER BY id ASC");
        if (!$stmt) return [];
        $bindFormId = $formIdInt !== null ? $formIdInt : 0;
        $stmt->bind_param('ii', $integrationId, $bindFormId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    private function getLeadCustomFieldIds()
    {
        $res = mysqli_query($this->connect, "SELECT id FROM custom_fields WHERE entity_type = 'lead'");
        $ids = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $ids[(int)$row['id']] = true;
            }
        }
        return $ids;
    }
}
