<?php
/**
 * Forms Module – CRUD for forms and form_fields.
 */

class FormService
{
    private const ALLOWED_FIELD_TYPES = ['text', 'email', 'phone', 'textarea', 'select', 'radio', 'checkbox', 'date', 'hidden'];

    /** @var mysqli */
    private $connect;

    public function __construct($connect)
    {
        $this->connect = $connect;
    }

    public function getAll($activeOnly = false)
    {
        $sql = "SELECT * FROM forms ORDER BY name ASC";
        if ($activeOnly) {
            $sql = "SELECT * FROM forms WHERE status = 'active' ORDER BY name ASC";
        }
        $res = mysqli_query($this->connect, $sql);
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
        $stmt = $this->connect->prepare("SELECT * FROM forms WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }

    public function getBySlug($slug)
    {
        $slug = trim((string)$slug);
        if ($slug === '') return null;
        $stmt = $this->connect->prepare("SELECT * FROM forms WHERE slug = ? LIMIT 1");
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }

    public function getFieldsByFormId($formId)
    {
        $formId = (int)$formId;
        $stmt = $this->connect->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY sort_order ASC, id ASC");
        $stmt->bind_param('i', $formId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    public function getFieldById($fieldId)
    {
        $fieldId = (int)$fieldId;
        if ($fieldId <= 0) return null;
        $stmt = $this->connect->prepare("SELECT * FROM form_fields WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $fieldId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }

    public function createForm(array $data)
    {
        $name = trim((string)($data['name'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));
        if ($name === '' || $slug === '') return null;
        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug));
        $slug = preg_replace('/-+/', '-', $slug);
        $title = trim((string)($data['title'] ?? ''));
        $status = in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : 'active';
        $submitButtonText = trim((string)($data['submit_button_text'] ?? 'Submit'));
        $successMessage = trim((string)($data['success_message'] ?? ''));
        $leadSourceId = isset($data['lead_source_id']) ? (int)$data['lead_source_id'] : null;
        $assignedStatusId = isset($data['assigned_status_id']) ? (int)$data['assigned_status_id'] : null;
        $allowedDomains = isset($data['allowed_domains']) ? trim((string)$data['allowed_domains']) : null;
        $settingsJson = isset($data['settings_json']) ? (is_string($data['settings_json']) ? $data['settings_json'] : json_encode($data['settings_json'])) : null;

        $stmt = $this->connect->prepare("INSERT INTO forms (name, slug, title, status, submit_button_text, success_message, lead_source_id, assigned_status_id, allowed_domains, settings_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return null;
        $stmt->bind_param('ssssssiiss', $name, $slug, $title, $status, $submitButtonText, $successMessage, $leadSourceId, $assignedStatusId, $allowedDomains, $settingsJson);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function updateForm($id, array $data)
    {
        $id = (int)$id;
        if ($id <= 0) return false;
        $existing = $this->getById($id);
        if (!$existing) return false;

        $name = trim((string)($data['name'] ?? $existing['name']));
        $slug = trim((string)($data['slug'] ?? $existing['slug']));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug));
        $slug = preg_replace('/-+/', '-', $slug);
        $title = trim((string)($data['title'] ?? $existing['title']));
        $status = in_array($data['status'] ?? $existing['status'], ['active', 'inactive'], true) ? $data['status'] : 'active';
        $submitButtonText = trim((string)($data['submit_button_text'] ?? $existing['submit_button_text']));
        $successMessage = trim((string)($data['success_message'] ?? $existing['success_message']));
        $leadSourceId = isset($data['lead_source_id']) ? (int)$data['lead_source_id'] : $existing['lead_source_id'];
        $assignedStatusId = isset($data['assigned_status_id']) ? (int)$data['assigned_status_id'] : $existing['assigned_status_id'];
        $allowedDomains = isset($data['allowed_domains']) ? trim((string)$data['allowed_domains']) : $existing['allowed_domains'];
        $settingsJson = isset($data['settings_json']) ? (is_string($data['settings_json']) ? $data['settings_json'] : json_encode($data['settings_json'])) : $existing['settings_json'];

        $stmt = $this->connect->prepare("UPDATE forms SET name=?, slug=?, title=?, status=?, submit_button_text=?, success_message=?, lead_source_id=?, assigned_status_id=?, allowed_domains=?, settings_json=? WHERE id=?");
        if (!$stmt) return false;
        $stmt->bind_param('ssssssiissi', $name, $slug, $title, $status, $submitButtonText, $successMessage, $leadSourceId, $assignedStatusId, $allowedDomains, $settingsJson, $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteForm($id)
    {
        $id = (int)$id;
        if ($id <= 0) return false;
        $stmt = $this->connect->prepare("DELETE FROM forms WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function saveFormField($formId, array $data)
    {
        $formId = (int)$formId;
        $label = trim((string)($data['label'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));
        $type = trim((string)($data['type'] ?? 'text'));
        $type = in_array($type, self::ALLOWED_FIELD_TYPES, true) ? $type : 'text';
        $placeholder = trim((string)($data['placeholder'] ?? ''));
        $isRequired = !empty($data['is_required']) ? 1 : 0;
        $sortOrder = (int)($data['sort_order'] ?? 0);
        $optionsJson = isset($data['options_json']) ? (is_string($data['options_json']) ? $data['options_json'] : json_encode($data['options_json'])) : null;
        $width = in_array($data['width'] ?? 'full', ['full', 'half'], true) ? $data['width'] : 'full';

        $stmt = $this->connect->prepare("INSERT INTO form_fields (form_id, label, name, type, placeholder, is_required, sort_order, options_json, width) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return null;
        $stmt->bind_param('issssiiss', $formId, $label, $name, $type, $placeholder, $isRequired, $sortOrder, $optionsJson, $width);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function updateFormField($fieldId, array $data)
    {
        $fieldId = (int)$fieldId;
        if ($fieldId <= 0) return false;
        $existing = $this->getFieldById($fieldId);
        if (!$existing) return false;

        $label = trim((string)($data['label'] ?? $existing['label']));
        $name = trim((string)($data['name'] ?? $existing['name']));
        $type = trim((string)($data['type'] ?? $existing['type']));
        $type = in_array($type, self::ALLOWED_FIELD_TYPES, true) ? $type : 'text';
        $placeholder = trim((string)($data['placeholder'] ?? $existing['placeholder'] ?? ''));
        $isRequired = !empty($data['is_required']) ? 1 : 0;
        $sortOrder = (int)($data['sort_order'] ?? $existing['sort_order'] ?? 0);
        $optionsJson = isset($data['options_json']) ? (is_string($data['options_json']) ? $data['options_json'] : json_encode($data['options_json'])) : ($existing['options_json'] ?? null);
        $width = in_array($data['width'] ?? ($existing['width'] ?? 'full'), ['full', 'half'], true) ? ($data['width'] ?? $existing['width'] ?? 'full') : 'full';

        $stmt = $this->connect->prepare("UPDATE form_fields SET label=?, name=?, type=?, placeholder=?, is_required=?, sort_order=?, options_json=?, width=? WHERE id=?");
        if (!$stmt) return false;
        $stmt->bind_param('ssssiissi', $label, $name, $type, $placeholder, $isRequired, $sortOrder, $optionsJson, $width, $fieldId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteFormField($id)
    {
        $id = (int)$id;
        if ($id <= 0) return false;
        $stmt = $this->connect->prepare("DELETE FROM form_fields WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
