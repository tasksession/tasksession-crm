<?php
/**
 * Forms Module – Public form display and submit (no login).
 * GET: ?slug=xxx or ?id=1 – show form.
 * POST: submit to API (forward to submit.php with auth); show thanks or error.
 */

$projectRoot = dirname(dirname(dirname(__DIR__)));
require_once $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'bootstrap_config.php';
if (empty($connect) || !($connect instanceof mysqli)) {
    http_response_code(503);
    echo 'Service unavailable.';
    exit;
}
mysqli_set_charset($connect, 'utf8mb4');

$formsRoot = dirname(__DIR__);
$config = is_file($formsRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'defaults.php')
    ? require $formsRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'defaults.php'
    : [];

require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'FormService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'IntegrationService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'ExistingLeadIntegrationService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'FieldMappingService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'ValidationService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'SubmissionLogService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'services' . DIRECTORY_SEPARATOR . 'LeadCaptureService.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'SecurityHelper.php';

$formService = new FormService($connect);
$integrationService = new IntegrationService($connect);

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$formIdParam = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$form = null;
if ($slug !== '') {
    $form = $formService->getBySlug($slug);
} elseif ($formIdParam > 0) {
    $form = $formService->getById($formIdParam);
}

if (!$form || $form['status'] !== 'active') {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Form not found</title></head><body><p>Form not found or inactive.</p></body></html>';
    exit;
}

$formId = (int)$form['id'];
$formFields = $formService->getFieldsByFormId($formId);
$settings = [];
if (!empty($form['settings_json'])) {
    $decoded = json_decode($form['settings_json'], true);
    if (is_array($decoded)) {
        $settings = $decoded;
    }
}
$embedIntegrationId = isset($settings['embed_integration_id']) ? (int)$settings['embed_integration_id'] : 0;
$integration = $embedIntegrationId > 0 ? $integrationService->getById($embedIntegrationId) : null;

$formUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['SCRIPT_NAME'] ?? '/form.php');
$formUrl = preg_replace('/\?.*/', '', $formUrl) . '?slug=' . rawurlencode($form['slug']);

// Handle POST (form submitted) – call capture directly so no curl/URL issues
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$integration || $integration['status'] !== 'active' || empty($integration['auth_token'])) {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title></head><body><p>Form is not configured for embed. Set an integration in Form settings → Edit form → Embed integration.</p></body></html>';
        exit;
    }

    $payload = ['form_id' => $formId];
    foreach ($formFields as $f) {
        $name = $f['name'];
        $val = isset($_POST[$name]) ? $_POST[$name] : '';
        if (is_array($val)) {
            $val = implode(', ', $val);
        }
        $payload[$name] = is_string($val) ? trim($val) : $val;
    }

    $sourceKey = $integration['source_key'];
    $rawPayload = json_encode($payload);
    $context = [
        'integration_id' => (int)$integration['id'],
        'source' => $sourceKey,
        'request_method' => 'POST',
        'request_headers' => function_exists('getallheaders') ? json_encode(getallheaders() ?: []) : '',
        'raw_payload' => strlen($rawPayload) > 65535 ? substr($rawPayload, 0, 65535) . '...[truncated]' : $rawPayload,
        'ip_address' => FormsSecurityHelper::getClientIp(),
        'user_agent' => FormsSecurityHelper::getUserAgent(500),
        'request_id' => uniqid('form_', true),
    ];

    $leadIntegrationService = new ExistingLeadIntegrationService($connect, $config);
    $fieldMappingService = new FieldMappingService($connect, $config);
    $submissionLogService = new SubmissionLogService($connect);
    $validationService = new ValidationService($config);
    $leadCaptureService = new LeadCaptureService($connect, $config, $fieldMappingService, $leadIntegrationService, $submissionLogService, $validationService);

    try {
        $result = $leadCaptureService->capture($sourceKey, $formId, $payload, $context);
    } catch (Throwable $e) {
        $result = ['success' => false, 'message' => $e->getMessage(), 'errors' => []];
    }

    if (!empty($result['success'])) {
        header('Location: ' . $formUrl . '&thanks=1');
        exit;
    }

    $errMsg = isset($result['message']) && $result['message'] !== '' ? $result['message'] : 'Sorry, we could not process your submission. Please try again.';
    if (!empty($result['errors']) && is_array($result['errors'])) {
        $errParts = [];
        foreach ($result['errors'] as $k => $v) {
            if ($k !== '_' && is_string($v)) {
                $errParts[] = $v;
            }
        }
        if (!empty($errParts)) {
            $errMsg .= ' ' . implode(' ', $errParts);
        }
    }
    $formError = htmlspecialchars($errMsg, ENT_QUOTES, 'UTF-8');
}

$thanks = isset($_GET['thanks']) && $_GET['thanks'] === '1';
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($form['title'] ?: $form['name'], ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 620px; margin: 2rem auto; padding: 0 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.25rem; font-weight: 500; }
        .form-group input[type="text"], .form-group input[type="email"], .form-group input[type="tel"], .form-group input[type="date"],
        .form-group textarea, .form-group select { width: 100%; padding: 0.5rem; box-sizing: border-box; }
        .form-group textarea { min-height: 100px; }
        .form-group .radio-option, .form-group .checkbox-option { margin-bottom: 0.5rem; }
        .form-group .radio-option label, .form-group .checkbox-option label { display: inline; font-weight: normal; margin-left: 0.25rem; }
        .required { color: #c00; }
        .error { background: #fee; color: #c00; padding: 0.75rem; margin-bottom: 1rem; border-radius: 4px; }
        .success { background: #efe; color: #060; padding: 0.75rem; margin-bottom: 1rem; border-radius: 4px; }
        button[type="submit"] { padding: 0.5rem 1.5rem; cursor: pointer; }
        .form-fields-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        .form-fields-grid .field-full { grid-column: 1 / -1; }
        .form-fields-grid .field-half { grid-column: span 1; }
        @media (max-width: 480px) { .form-fields-grid .field-half { grid-column: 1 / -1; } }
    </style>
</head>
<body>
<?php if ($thanks): ?>
    <div class="success"><?php echo htmlspecialchars(!empty($form['success_message']) ? $form['success_message'] : 'Thank you. Your submission has been received.', ENT_QUOTES, 'UTF-8'); ?></div>
<?php else: ?>
    <?php if (!empty($formError)): ?>
        <div class="error"><?php echo $formError; ?></div>
    <?php endif; ?>

    <?php if (!$integration || $integration['status'] !== 'active'): ?>
        <p>This form is not configured for embed. Please set an integration in CRM Form settings.</p>
    <?php else: ?>
        <form method="post" action="">
            <input type="hidden" name="form_id" value="<?php echo $formId; ?>">
            <div class="form-fields-grid">
            <?php foreach ($formFields as $f):
                $name = $f['name'];
                $label = $f['label'];
                $type = $f['type'];
                $required = !empty($f['is_required']);
                $placeholder = $f['placeholder'] ?? '';
                $width = (isset($f['width']) && $f['width'] === 'half') ? 'half' : 'full';
                $options = [];
                if (!empty($f['options_json'])) {
                    $opts = json_decode($f['options_json'], true);
                    if (is_array($opts)) {
                        $options = $opts;
                    }
                }
                $fieldClass = $width === 'half' ? 'field-half' : 'field-full';
            ?>
            <div class="form-group <?php echo $fieldClass; ?>">
                <?php if ($type !== 'hidden'): ?>
                <label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_0"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?><?php if ($required): ?> <span class="required">*</span><?php endif; ?></label>
                <?php endif; ?>
                <?php
                if ($type === 'textarea'):
                    ?><textarea id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_0" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>"<?php if ($required): ?> required<?php endif; ?>></textarea><?php
                elseif ($type === 'select'):
                    ?><select id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_0" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"<?php if ($required): ?> required<?php endif; ?>>
                        <option value="">— Select —</option>
                        <?php foreach ($options as $opt): $v = is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt; $l = is_array($opt) ? ($opt['label'] ?? $v) : $opt; ?>
                        <option value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select><?php
                elseif ($type === 'radio'):
                    if (!empty($options)):
                        foreach ($options as $idx => $opt):
                            $v = is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt;
                            $l = is_array($opt) ? ($opt['label'] ?? $v) : $opt;
                            ?><div class="radio-option"><input type="radio" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_<?php echo $idx; ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>"<?php if ($required && $idx === 0): ?> required<?php endif; ?>><label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_<?php echo $idx; ?>"><?php echo htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); ?></label></div><?php
                        endforeach;
                    else:
                        ?><div class="radio-option"><input type="radio" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_0" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="1"><label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_0">Yes</label></div><?php
                    endif;
                elseif ($type === 'checkbox'):
                    if (!empty($options)):
                        foreach ($options as $idx => $opt):
                            $v = is_array($opt) ? ($opt['value'] ?? $opt['label'] ?? '') : $opt;
                            $l = is_array($opt) ? ($opt['label'] ?? $v) : $opt;
                            ?><div class="checkbox-option"><input type="checkbox" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_<?php echo $idx; ?>" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>[]" value="<?php echo htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); ?>"><label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_<?php echo $idx; ?>"><?php echo htmlspecialchars($l, ENT_QUOTES, 'UTF-8'); ?></label></div><?php
                        endforeach;
                    else:
                        ?><div class="checkbox-option"><input type="checkbox" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_0" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="1"><label for="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_0">Yes</label></div><?php
                    endif;
                elseif ($type === 'hidden'):
                    ?><input type="hidden" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>"><?php
                else:
                    $inputType = in_array($type, ['email', 'tel', 'date'], true) ? $type : 'text';
                    ?><input type="<?php echo $inputType; ?>" id="f_<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>_0" name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8'); ?>"<?php if ($required): ?> required<?php endif; ?>><?php
                endif;
                ?>
            </div>
            <?php endforeach; ?>
            <div class="form-group field-full">
                <button type="submit"><?php echo htmlspecialchars($form['submit_button_text'] ?: 'Submit', ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>
</body>
</html>
