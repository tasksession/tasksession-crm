<?php
/**
 * Single chokepoint around installer-generated includes/config.php.
 * Do not overwrite live config.php — catch Hostinger mysqli throw and reconnect.
 */

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

/**
 * Hostinger/PHP default writes an "error_log" file into the public web root.
 * Keep that off unless CRM_PHP_ERROR_LOG=1 (then logs/php_error.log).
 */
if (!function_exists('crm_configure_php_error_log')) {
    function crm_configure_php_error_log(): void
    {
        $siteRoot = dirname(__DIR__);
        $enabled = (defined('CRM_PHP_ERROR_LOG') && CRM_PHP_ERROR_LOG)
            || getenv('CRM_PHP_ERROR_LOG') === '1';

        @ini_set('display_errors', '0');

        $level = error_reporting();
        if ($level !== 0) {
            error_reporting($level & ~E_DEPRECATED & ~E_STRICT);
        }

        if ($enabled) {
            $logDir = $siteRoot . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            @ini_set('log_errors', '1');
            @ini_set('error_log', $logDir . DIRECTORY_SEPARATOR . 'php_error.log');
            return;
        }

        @ini_set('log_errors', '0');
        // error_log() still writes to error_log path even when log_errors=0 — send nowhere.
        @ini_set('error_log', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'NUL' : '/dev/null');
    }
}

crm_configure_php_error_log();

require_once __DIR__ . '/mysqli_connect_safe.php';

global $connect;

$configFile = __DIR__ . '/config.php';
if (is_file($configFile)) {
    try {
        require_once $configFile;
    } catch (Throwable $e) {
        error_log('bootstrap_config: ' . $e->getMessage());
    }
}

// Re-apply after config.php in case it (or the host) reset error_log / reporting.
crm_configure_php_error_log();

if (!crm_mysqli_is_usable($connect ?? null)) {
    $opened = crm_mysqli_open();
    if ($opened) {
        $connect = $opened;
        $GLOBALS['connect'] = $opened;
    }
}

mysqli_report(MYSQLI_REPORT_OFF);
