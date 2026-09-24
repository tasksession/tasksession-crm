<?php
/**
 * Forms Module – Public API / Webhook entry (no session)
 * POST only. Accept JSON or application/x-www-form-urlencoded.
 * Auth: Bearer token or ?integration=SOURCE_KEY and token in header/body.
 */

ob_start();
$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap_config.php';
if (!isset($connect) || !($connect instanceof mysqli) || (int) $connect->connect_errno !== 0) {
    require_once $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mysqli_connect_safe.php';
    $connect = function_exists('crm_mysqli_open') ? crm_mysqli_open() : mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
}
if (!$connect) {
    header('Content-Type: application/json');
    http_response_code(503);
    echo json_encode(['status' => false, 'message' => 'Service unavailable']);
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$formsRoot = dirname(__DIR__);
$config = is_file($formsRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'defaults.php')
    ? require $formsRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'defaults.php'
    : [];

require_once $formsRoot . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'SecurityHelper.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'ResponseHelper.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'RequestHelper.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'ExistingLeadIntegrationService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'FieldMappingService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'ValidationService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'SubmissionLogService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'DebugLogService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'FormService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'IntegrationService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'LeadCaptureService.php';

$route = 'api/submit';

$integrationSourceKey = isset($_GET['integration']) ? trim((string)$_GET['integration']) : (isset($_POST['integration']) ? trim((string)$_POST['integration']) : null);
if ($integrationSourceKey === '') $integrationSourceKey = null;

$receivedMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
if (!FormsRequestHelper::isMethod('POST')) {
    FormsResponseHelper::jsonError(
        'Method not allowed. This URL accepts only POST. Send your form data via POST (e.g. WordPress webhook plugin, Postman, or curl -X POST). You sent: ' . $receivedMethod,
        ['received_method' => $receivedMethod],
        405
    );
}

if ($integrationSourceKey === null) {
    FormsResponseHelper::jsonError('Missing integration parameter', null, 400);
}

$integrationService = new IntegrationService($connect);
$integration = $integrationService->getBySourceKey($integrationSourceKey);
if (!$integration) {
    FormsResponseHelper::jsonError('Unknown integration', null, 404);
}

$rawPayload = file_get_contents('php://input');
if ($rawPayload === false) $rawPayload = '';
$payload = FormsRequestHelper::parsePayloadFromRaw($rawPayload);
$token = FormsRequestHelper::getAuthTokenFromPayloadAndHeaders($payload);

if (!$integrationService->validateToken($integrationSourceKey, $token)) {
    FormsResponseHelper::jsonError('Invalid or missing authentication', null, 401);
}

$formId = isset($payload['form_id']) ? (int)$payload['form_id'] : null;
if ($formId <= 0) $formId = null;

$context = [
    'integration_id' => (int)$integration['id'],
    'source' => $integrationSourceKey,
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'POST',
    'request_headers' => FormsSecurityHelper::getHeadersForLog(),
    'raw_payload' => strlen($rawPayload) > 65535 ? substr($rawPayload, 0, 65535) . '...[truncated]' : $rawPayload,
    'ip_address' => FormsSecurityHelper::getClientIp(),
    'user_agent' => FormsSecurityHelper::getUserAgent(500),
    'request_id' => null,
];

$leadIntegrationService = new ExistingLeadIntegrationService($connect, $config);
$fieldMappingService = new FieldMappingService($connect, $config);
$submissionLogService = new SubmissionLogService($connect);
$validationService = new ValidationService($config);
$leadCaptureService = new LeadCaptureService($connect, $config, $fieldMappingService, $leadIntegrationService, $submissionLogService, $validationService);

try {
    $result = $leadCaptureService->capture($integrationSourceKey, $formId, $payload, $context);
} catch (Throwable $e) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Internal server error', 'error' => $e->getMessage()]);
    exit;
}

if ($result['success']) {
    FormsResponseHelper::jsonSuccess('Lead created successfully', $result['lead_id']);
}

$reasons = [];
if (stripos($result['message'], 'Validation failed') !== false && !empty($result['errors'])) {
    $reasons[] = 'Field mapping: payload keys must match CRM Field Mappings source field names (e.g. first-name, email).';
    $reasons[] = 'Required mapping: "name" is required; ensure one source field maps to lead column "name".';
    if (isset($result['errors']['name'])) $reasons[] = 'Name is empty or missing in mapped data.';
    if (isset($result['errors']['email'])) $reasons[] = 'Email validation failed or missing.';
}
if (stripos($result['message'], 'Lead creation failed') !== false || stripos($result['message'], 'Failed to create lead') !== false) {
    $reasons[] = 'Database: leads table or lead_statuses/lead_sources may be missing or have wrong structure.';
    $reasons[] = 'Default lead status/source: ensure at least one row in lead_statuses and lead_sources (or one marked default).';
}
if (stripos($result['message'], 'Duplicate') !== false) {
    $reasons[] = 'Duplicate behavior is set to block; a lead with same email/phone already exists.';
}
$errDetail = $result['message'];
if (!empty($result['errors']) && is_array($result['errors'])) {
    $errDetail .= ' | ' . json_encode($result['errors'], JSON_UNESCAPED_UNICODE);
}
FormsResponseHelper::jsonError($result['message'], $result['errors'], 400);
