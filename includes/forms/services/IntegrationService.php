<?php
/**
 * Forms Module – CRUD for form_integrations; auth validation.
 */

class IntegrationService
{
    /** @var mysqli */
    private $connect;

    public function __construct($connect)
    {
        $this->connect = $connect;
    }

    public function getAll()
    {
        $res = mysqli_query($this->connect, "SELECT * FROM form_integrations ORDER BY name ASC");
        $rows = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function getById($id)
    {
        $id = (int)$id;
        if ($id <= 0) return null;
        $stmt = $this->connect->prepare("SELECT * FROM form_integrations WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }

    /**
     * Get integration by source_key; return null if inactive or not found.
     */
    public function getBySourceKey($sourceKey, $activeOnly = true)
    {
        $sourceKey = trim((string)$sourceKey);
        if ($sourceKey === '') return null;
        $sql = "SELECT * FROM form_integrations WHERE source_key = ? LIMIT 1";
        if ($activeOnly) {
            $sql = "SELECT * FROM form_integrations WHERE source_key = ? AND status = 'active' LIMIT 1";
        }
        $stmt = $this->connect->prepare($sql);
        $stmt->bind_param('s', $sourceKey);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }

    /**
     * Validate token for integration (Bearer or query/body token must match auth_token).
     */
    public function validateToken($sourceKey, $token)
    {
        $integration = $this->getBySourceKey($sourceKey, true);
        if (!$integration) return false;
        $expected = trim((string)($integration['auth_token'] ?? ''));
        if ($expected === '') return false;
        return $token !== null && trim((string)$token) === $expected;
    }

    public function create(array $data)
    {
        $name = trim((string)($data['name'] ?? ''));
        $sourceKey = trim((string)($data['source_key'] ?? ''));
        $sourceKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sourceKey);
        if ($name === '' || $sourceKey === '') return null;
        $authToken = isset($data['auth_token']) ? trim((string)$data['auth_token']) : null;
        $status = in_array($data['status'] ?? 'active', ['active', 'inactive'], true) ? $data['status'] : 'active';
        $webhookPath = isset($data['webhook_path']) ? trim((string)$data['webhook_path']) : null;
        $allowedMethod = trim((string)($data['allowed_method'] ?? 'POST'));
        $notes = isset($data['notes']) ? trim((string)$data['notes']) : null;

        $stmt = $this->connect->prepare("INSERT INTO form_integrations (name, source_key, auth_token, status, webhook_path, allowed_method, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return null;
        $stmt->bind_param('sssssss', $name, $sourceKey, $authToken, $status, $webhookPath, $allowedMethod, $notes);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function update($id, array $data)
    {
        $id = (int)$id;
        if ($id <= 0) return false;
        $existing = $this->getById($id);
        if (!$existing) return false;

        $name = trim((string)($data['name'] ?? $existing['name']));
        $sourceKey = trim((string)($data['source_key'] ?? $existing['source_key']));
        $sourceKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sourceKey);
        $authToken = (isset($data['auth_token']) && trim((string)$data['auth_token']) !== '') ? trim((string)$data['auth_token']) : $existing['auth_token'];
        $status = in_array($data['status'] ?? $existing['status'], ['active', 'inactive'], true) ? $data['status'] : 'active';
        $webhookPath = isset($data['webhook_path']) ? trim((string)$data['webhook_path']) : $existing['webhook_path'];
        $allowedMethod = trim((string)($data['allowed_method'] ?? $existing['allowed_method']));
        $notes = isset($data['notes']) ? trim((string)$data['notes']) : $existing['notes'];

        $stmt = $this->connect->prepare("UPDATE form_integrations SET name=?, source_key=?, auth_token=?, status=?, webhook_path=?, allowed_method=?, notes=? WHERE id=?");
        if (!$stmt) return false;
        $stmt->bind_param('sssssssi', $name, $sourceKey, $authToken, $status, $webhookPath, $allowedMethod, $notes, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function delete($id)
    {
        $id = (int)$id;
        if ($id <= 0) return false;
        $stmt = $this->connect->prepare("DELETE FROM form_integrations WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    /**
     * Get a single mapping by id.
     */
    public function getMappingById($id)
    {
        $id = (int)$id;
        if ($id <= 0) return null;
        $stmt = $this->connect->prepare("SELECT * FROM form_field_mappings WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }

    /**
     * Get mappings for an integration (and optional form).
     */
    public function getMappings($integrationId, $formId = null)
    {
        $integrationId = (int)$integrationId;
        $stmt = $this->connect->prepare("SELECT * FROM form_field_mappings WHERE integration_id = ? AND (form_id IS NULL OR form_id = ?) ORDER BY id ASC");
        $formId = $formId ? (int)$formId : 0;
        $stmt->bind_param('ii', $integrationId, $formId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    public function saveMapping(array $data)
    {
        $integrationId = (int)($data['integration_id'] ?? 0);
        $formId = isset($data['form_id']) && $data['form_id'] !== '' ? (int)$data['form_id'] : null;
        $sourceField = trim((string)($data['source_field'] ?? ''));
        $destinationField = trim((string)($data['destination_field'] ?? ''));
        $destinationType = in_array($data['destination_type'] ?? 'lead_column', ['lead_column', 'custom_field'], true) ? $data['destination_type'] : 'lead_column';
        $isRequired = !empty($data['is_required']) ? 1 : 0;
        $defaultValue = isset($data['default_value']) ? trim((string)$data['default_value']) : null;
        $ignoreField = !empty($data['ignore_field']) ? 1 : 0;
        $transformRule = isset($data['transform_rule']) ? trim((string)$data['transform_rule']) : null;

        if ($integrationId <= 0 || $sourceField === '') return null;
        $stmt = $this->connect->prepare("INSERT INTO form_field_mappings (integration_id, form_id, source_field, destination_field, destination_type, is_required, default_value, ignore_field, transform_rule) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return null;
        $stmt->bind_param('iisssisss', $integrationId, $formId, $sourceField, $destinationField, $destinationType, $isRequired, $defaultValue, $ignoreField, $transformRule);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    /**
     * Update an existing mapping.
     */
    public function updateMapping($id, array $data)
    {
        $id = (int)$id;
        if ($id <= 0) return false;
        $existing = $this->getMappingById($id);
        if (!$existing) return false;

        $sourceField = trim((string)($data['source_field'] ?? $existing['source_field']));
        $destinationField = trim((string)($data['destination_field'] ?? $existing['destination_field']));
        $destinationType = in_array($data['destination_type'] ?? $existing['destination_type'], ['lead_column', 'custom_field'], true) ? ($data['destination_type'] ?? $existing['destination_type']) : 'lead_column';
        $isRequired = !empty($data['is_required']) ? 1 : 0;
        $defaultValue = isset($data['default_value']) ? trim((string)$data['default_value']) : null;
        $ignoreField = !empty($data['ignore_field']) ? 1 : 0;
        $transformRule = isset($data['transform_rule']) ? trim((string)$data['transform_rule']) : null;

        if ($sourceField === '') return false;
        $stmt = $this->connect->prepare("UPDATE form_field_mappings SET source_field=?, destination_field=?, destination_type=?, is_required=?, default_value=?, ignore_field=?, transform_rule=? WHERE id=?");
        if (!$stmt) return false;
        $stmt->bind_param('sssisssi', $sourceField, $destinationField, $destinationType, $isRequired, $defaultValue, $ignoreField, $transformRule, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteMapping($id)
    {
        $id = (int)$id;
        if ($id <= 0) return false;
        $stmt = $this->connect->prepare("DELETE FROM form_field_mappings WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
