<?php
/**
 * Forms Module – Integration with existing CRM lead system
 * Inserts into leads + lead_custom_field_values + taggables. Does NOT create a separate leads table.
 * Reuses same logic as includes/leads/create.php (without session/card rendering).
 */

class ExistingLeadIntegrationService
{
    /** @var mysqli */
    private $connect;
    /** @var array */
    private $config;
    /** @var array */
    private $leadColumnsWhitelist;

    public function __construct($connect, array $config = [])
    {
        $this->connect = $connect;
        $this->config = $config;
        $this->leadColumnsWhitelist = isset($config['lead_columns_whitelist']) ? $config['lead_columns_whitelist'] : [];
    }

    /**
     * Create a lead in the existing CRM tables.
     * @param array $data Keys: lead column names (whitelisted) + 'custom_fields' => [custom_field_id => value] + optional 'tag_ids' => [1,2,3]
     * @return array ['success' => bool, 'lead_id' => int|null, 'error' => string|null]
     */
    public function createLead(array $data)
    {
        $customFieldsData = isset($data['custom_fields']) && is_array($data['custom_fields']) ? $data['custom_fields'] : [];
        $tagIds = isset($data['tag_ids']) && is_array($data['tag_ids']) ? array_filter(array_map('intval', $data['tag_ids'])) : [];
        unset($data['custom_fields'], $data['tag_ids']);

        // Build only whitelisted lead columns
        $allowed = array_flip($this->leadColumnsWhitelist);
        $leadData = [];
        foreach ($data as $k => $v) {
            if (!isset($allowed[$k])) continue;
            $leadData[$k] = $v;
        }

        $name = trim((string)($leadData['name'] ?? ''));
        if ($name === '') {
            return ['success' => false, 'lead_id' => null, 'error' => 'Name is required'];
        }
        $lastName = isset($leadData['last_name']) ? trim((string)$leadData['last_name']) : null;
        if ($lastName === '') $lastName = null;

        $statusId = (int)($leadData['status_id'] ?? 0);
        $sourceId = isset($leadData['source_id']) ? (int)$leadData['source_id'] : 0;
        if ($statusId <= 0) {
            $row = $this->getDefaultStatusId();
            $statusId = (int)($row['id'] ?? 0);
        }
        if ($statusId <= 0) {
            return ['success' => false, 'lead_id' => null, 'error' => 'No lead status exists'];
        }
        if ($sourceId <= 0) {
            $row = $this->getDefaultSourceId();
            $sourceId = (int)($row['id'] ?? 0);
        }
        if ($sourceId <= 0) $sourceId = null;

        $createdBy = (int)($this->config['default_created_by'] ?? 0);
        if ($createdBy < 0) $createdBy = 0;

        $priority = isset($leadData['priority']) ? trim((string)$leadData['priority']) : null;
        if ($priority === '') $priority = null;
        $leadValue = isset($leadData['lead_value']) ? trim((string)$leadData['lead_value']) : null;
        if ($leadValue === '') $leadValue = null;
        $currency = isset($leadData['currency']) ? trim((string)$leadData['currency']) : null;
        if ($currency === '') $currency = null;
        $assignedTo = isset($leadData['assigned_to']) ? trim((string)$leadData['assigned_to']) : null;
        if ($assignedTo !== null) {
            $assignedTo = preg_replace('/\s+/', '', $assignedTo);
            if ($assignedTo === '') $assignedTo = null;
        }
        $email = isset($leadData['email']) ? trim((string)$leadData['email']) : null;
        if ($email === '') $email = null;
        $phone = isset($leadData['phone']) ? trim((string)$leadData['phone']) : null;
        if ($phone === '') $phone = null;
        $website = isset($leadData['website']) ? trim((string)$leadData['website']) : null;
        if ($website === '') $website = null;
        $company = isset($leadData['company']) ? trim((string)$leadData['company']) : null;
        if ($company === '') $company = null;
        $expectedCloseDate = isset($leadData['expected_close_date']) ? trim((string)$leadData['expected_close_date']) : null;
        if ($expectedCloseDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedCloseDate)) $expectedCloseDate = null;
        $position = isset($leadData['position']) ? trim((string)$leadData['position']) : null;
        if ($position === '') $position = null;
        $description = isset($leadData['description']) ? trim((string)$leadData['description']) : null;
        if ($description === '') $description = null;
        $country = isset($leadData['country']) ? trim((string)$leadData['country']) : null;
        if ($country === '') $country = null;
        $zip = isset($leadData['zip']) ? trim((string)$leadData['zip']) : null;
        if ($zip === '') $zip = null;
        $city = isset($leadData['city']) ? trim((string)$leadData['city']) : null;
        if ($city === '') $city = null;
        $state = isset($leadData['state']) ? trim((string)$leadData['state']) : null;
        if ($state === '') $state = null;
        $address = isset($leadData['address']) ? trim((string)$leadData['address']) : null;
        if ($address === '') $address = null;
        $notes = isset($leadData['notes']) ? trim((string)$leadData['notes']) : null;
        if ($notes === '') $notes = null;

        $stmt = $this->connect->prepare("INSERT INTO leads (status_id, source_id, priority, lead_value, currency, assigned_to, name, last_name, email, phone, website, company, expected_close_date, position, description, country, zip, city, state, address, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            return ['success' => false, 'lead_id' => null, 'error' => 'Failed to prepare insert statement'];
        }
        $types = 'iisssssssssssssssssssi';
        $stmt->bind_param($types,
            $statusId, $sourceId, $priority, $leadValue, $currency, $assignedTo,
            $name, $lastName, $email, $phone, $website, $company, $expectedCloseDate, $position, $description,
            $country, $zip, $city, $state, $address, $notes, $createdBy
        );
        $ok = $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();
        if (!$ok || $newId <= 0) {
            return ['success' => false, 'lead_id' => null, 'error' => 'Failed to create lead'];
        }

        if (!empty($tagIds)) {
            $tagStmt = $this->connect->prepare("INSERT IGNORE INTO taggables (tag_id, entity_type, entity_id) VALUES (?, 'lead', ?)");
            if ($tagStmt) {
                foreach ($tagIds as $tagId) {
                    if ($tagId > 0) {
                        $tagStmt->bind_param('ii', $tagId, $newId);
                        $tagStmt->execute();
                    }
                }
                $tagStmt->close();
            }
        }

        if (!empty($customFieldsData)) {
            $customFieldsMap = $this->getLeadCustomFieldsMap();
            $cfStmt = $this->connect->prepare("INSERT INTO lead_custom_field_values (lead_id, custom_field_id, field_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)");
            if ($cfStmt) {
                foreach ($customFieldsData as $fieldId => $fieldValue) {
                    $fieldIdInt = (int)$fieldId;
                    if ($fieldIdInt <= 0 || !isset($customFieldsMap[$fieldIdInt])) continue;
                    $fieldDef = $customFieldsMap[$fieldIdInt];
                    $valueToStore = '';
                    if ($fieldDef['field_type'] === 'multiple_select') {
                        $valueToStore = is_array($fieldValue) ? json_encode(array_values($fieldValue)) : json_encode([]);
                    } elseif ($fieldDef['field_type'] === 'checkbox') {
                        $valueToStore = ($fieldValue == '1' || $fieldValue === true || $fieldValue === 'on') ? '1' : '0';
                    } else {
                        $valueToStore = trim((string)$fieldValue);
                        if ($valueToStore === '') $valueToStore = null;
                    }
                    if ($valueToStore !== null) {
                        $cfStmt->bind_param('iis', $newId, $fieldIdInt, $valueToStore);
                        $cfStmt->execute();
                    }
                }
                $cfStmt->close();
            }
        }

        if ($createdBy > 0 && function_exists('recordLeadActivity')) {
            $actorId = $createdBy;
            $title = 'Lead created';
            $msg = 'Lead: ' . $name;
            try {
                if (function_exists('recordLeadActivity')) {
                    recordLeadActivity($newId, $actorId, 'lead_created', $title, $msg, ['name' => $name, 'status_id' => (string)$statusId, 'source_id' => (string)($sourceId ?? '')]);
                }
                if (function_exists('notifyLeadUsers')) {
                    notifyLeadUsers($newId, $actorId, 'lead_created', $title, $msg !== '' ? $msg : 'A lead was created');
                }
            } catch (Exception $e) {
                // ignore
            }
        }

        return ['success' => true, 'lead_id' => (int)$newId, 'error' => null];
    }

    private function getDefaultStatusId()
    {
        $res = mysqli_query($this->connect, "SELECT id FROM lead_statuses WHERE is_default=1 ORDER BY sort_order ASC, id ASC LIMIT 1");
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return $row ?: [];
    }

    private function getDefaultSourceId()
    {
        $res = mysqli_query($this->connect, "SELECT id FROM lead_sources WHERE is_default=1 ORDER BY sort_order ASC, id ASC LIMIT 1");
        $row = $res ? mysqli_fetch_assoc($res) : null;
        return $row ?: [];
    }

    private function getLeadCustomFieldsMap()
    {
        $map = [];
        $res = mysqli_query($this->connect, "SELECT * FROM custom_fields WHERE entity_type = 'lead'");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $map[(int)$row['id']] = $row;
            }
        }
        return $map;
    }
}
