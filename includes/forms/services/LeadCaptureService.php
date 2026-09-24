<?php
/**
 * Forms Module – Orchestrates lead capture: validate, map, duplicate check, insert, log.
 */

class LeadCaptureService
{
    /** @var mysqli */
    private $connect;
    /** @var array */
    private $config;
    /** @var FieldMappingService */
    private $fieldMappingService;
    /** @var ExistingLeadIntegrationService */
    private $leadIntegrationService;
    /** @var SubmissionLogService */
    private $submissionLogService;
    /** @var ValidationService */
    private $validationService;

    public function __construct(
        $connect,
        array $config,
        FieldMappingService $fieldMappingService,
        ExistingLeadIntegrationService $leadIntegrationService,
        SubmissionLogService $submissionLogService,
        ValidationService $validationService
    ) {
        $this->connect = $connect;
        $this->config = $config;
        $this->fieldMappingService = $fieldMappingService;
        $this->leadIntegrationService = $leadIntegrationService;
        $this->submissionLogService = $submissionLogService;
        $this->validationService = $validationService;
    }

    /**
     * Process incoming submission and create lead if valid.
     * @param string $integrationSourceKey
     * @param int|null $formId
     * @param array $parsedPayload Already parsed from request (JSON or POST)
     * @param array $context For logging: route, request_id, ip, user_agent, raw_payload, headers
     * @return array ['success' => bool, 'lead_id' => int|null, 'message' => string, 'errors' => array|null, 'submission_id' => int|null]
     */
    public function capture($integrationSourceKey, $formId, array $parsedPayload, array $context = [])
    {
        $integrationId = (int)($context['integration_id'] ?? 0);
        $mapped = $this->fieldMappingService->map($integrationSourceKey, $formId, $parsedPayload);
        if (isset($mapped['_errors'])) {
            return [
                'success' => false,
                'lead_id' => null,
                'message' => 'Validation failed',
                'errors' => $mapped['_errors'],
                'mapped' => $mapped,
                'submission_id' => $this->logSubmission($integrationId, $formId, $context, $parsedPayload, $mapped, null, 'failed', trim($mapped['_error_message'] ?? '') ?: 'Validation failed (mapping)'),
            ];
        }

        $validation = $this->validationService->validateMappedLead($mapped);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'lead_id' => null,
                'message' => 'Validation failed',
                'errors' => $validation['errors'],
                'mapped' => $mapped,
                'submission_id' => $this->logSubmission($integrationId, $formId, $context, $parsedPayload, $mapped, null, 'failed', trim($validation['errors']['_'] ?? implode(' ', $validation['errors'])) ?: 'Validation failed'),
            ];
        }

        $duplicateBehavior = $this->config['duplicate_behavior'] ?? 'allow';
        if ($duplicateBehavior !== 'allow') {
            $dup = $this->checkDuplicate($mapped, $integrationSourceKey);
            if ($dup['is_duplicate']) {
                $msg = 'Duplicate lead';
                if ($duplicateBehavior === 'block') {
                    return [
                        'success' => false,
                        'lead_id' => $dup['existing_id'],
                        'message' => $msg,
                        'errors' => ['email' => 'A lead with this email already exists'],
                        'mapped' => $mapped,
                        'submission_id' => $this->logSubmission($integrationId, $formId, $context, $parsedPayload, $mapped, $dup['existing_id'], 'failed', $msg),
                    ];
                }
                if ($duplicateBehavior === 'mark') {
                    $mapped['notes'] = (trim($mapped['notes'] ?? '') . "\n[Duplicate of lead #" . $dup['existing_id'] . "]") ?: ('Duplicate of lead #' . $dup['existing_id']);
                }
            }
        }

        $leadData = $this->validationService->buildLeadDataFromMapped($mapped);
        $result = $this->leadIntegrationService->createLead($leadData);
        if (!$result['success']) {
            return [
                'success' => false,
                'lead_id' => null,
                'message' => $result['error'] ?? 'Lead creation failed',
                'errors' => null,
                'mapped' => $mapped,
                'submission_id' => $this->logSubmission($integrationId, $formId, $context, $parsedPayload, $mapped, null, 'failed', trim($result['error'] ?? '') ?: 'Lead creation failed'),
            ];
        }

        try {
            $sourceLabel = $formId ? 'Form' : ('Webhook (' . $integrationSourceKey . ')');
            require_once __DIR__ . '/../helpers/LeadNotificationHelper.php';
            notifyAdminsNewLeadCreated($this->connect, $result['lead_id'], $sourceLabel);
        } catch (\Throwable $e) {
            error_log('Lead notification failed: ' . $e->getMessage());
        }

        $this->logSubmission($integrationId, $formId, $context, $parsedPayload, $mapped, $result['lead_id'], 'success', null);
        return [
            'success' => true,
            'lead_id' => $result['lead_id'],
            'message' => 'Lead created successfully',
            'errors' => null,
            'submission_id' => null,
        ];
    }

    private function checkDuplicate(array $mapped, $sourceKey)
    {
        $allowed = ['email', 'phone'];
        $whitelist = $this->config['lead_columns_whitelist'] ?? [];
        $fields = array_intersect($this->config['duplicate_check_fields'] ?? ['email', 'phone'], $whitelist ?: $allowed);
        if (empty($fields)) $fields = $allowed;
        $conditions = [];
        $types = '';
        $params = [];
        foreach ($fields as $f) {
            if (!in_array($f, $allowed, true)) continue;
            $v = trim((string)($mapped[$f] ?? ''));
            if ($v === '') continue;
            $conditions[] = ($f === 'email' ? 'email' : 'phone') . ' = ?';
            $types .= 's';
            $params[] = $v;
        }
        if (empty($conditions)) return ['is_duplicate' => false, 'existing_id' => null];
        $sql = "SELECT id FROM leads WHERE " . implode(' OR ', $conditions) . " LIMIT 1";
        $stmt = $this->connect->prepare($sql);
        if (!$stmt) return ['is_duplicate' => false, 'existing_id' => null];
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $id = $row ? (int)$row['id'] : null;
        return ['is_duplicate' => $id !== null, 'existing_id' => $id];
    }

    private function logSubmission($integrationId, $formId, array $context, $parsedPayload, $mappedPayload, $leadId, $status, $errorMessage)
    {
        return $this->submissionLogService->log(
            $integrationId,
            $formId,
            $context['source'] ?? null,
            $context['request_method'] ?? null,
            $context['request_headers'] ?? null,
            $context['raw_payload'] ?? null,
            $parsedPayload,
            $mappedPayload,
            $leadId,
            $status,
            $errorMessage,
            $context['ip_address'] ?? null,
            $context['user_agent'] ?? null
        );
    }
}
