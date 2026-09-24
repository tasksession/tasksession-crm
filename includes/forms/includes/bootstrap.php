<?php
/**
 * Forms Module – Bootstrap
 * Loads CRM core and forms config, helpers, services. Does NOT start session (caller may do that for admin).
 */

$formsRoot = dirname(__DIR__);
$projectRoot = dirname(dirname($formsRoot)); // includes/forms -> includes; for site root use dirname again
if (basename($projectRoot) === 'includes') {
    $projectRoot = dirname($projectRoot);
}

if (!defined('SITE_ROOT')) {
    require_once $projectRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'lib-initialize.php';
}
if (!isset($connect) || !$connect) {
    return;
}

$config = is_file($formsRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'defaults.php')
    ? require $formsRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'defaults.php'
    : [];

require_once $formsRoot . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'LoggerHelper.php';
require_once $formsRoot . DIRECTORY_SEPARATOR . 'helpers' . DIRECTORY_SEPARATOR . 'ActionToggleHelper.php';
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
require_once $formsRoot . DIRECTORY_SEPARATOR . 'controllers' . DIRECTORY_SEPARATOR . 'FormsController.php';

$logFile = isset($config['debug_log_file']) ? $config['debug_log_file'] : ($projectRoot . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'forms_debug.log');
FormsLoggerHelper::init($logFile, $connect);
