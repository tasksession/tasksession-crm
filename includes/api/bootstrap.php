<?php
/**
 * Lightweight bootstrap for /api entry (Phase 7).
 */

if (!defined('CRM_LIGHTWEIGHT_INIT')) {
    define('CRM_LIGHTWEIGHT_INIT', true);
}

require_once dirname(__DIR__, 2) . '/includes/lib-initialize.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';
require_once __DIR__ . '/rate-limit.php';
require_once __DIR__ . '/tokens.php';

api_response_headers();

// CORS for external tools (tighten later if needed)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !headers_sent()) {
    // Same-site apps often omit Origin; allow credentialed CRM host only when Origin matches app URL
    global $url;
    $appHost = parse_url((string) $url, PHP_URL_HOST);
    $originHost = parse_url($origin, PHP_URL_HOST);
    if ($appHost && $originHost && strcasecmp($appHost, $originHost) === 0) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-CSRF-Token');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    }
}

if (api_request_method() === 'OPTIONS') {
    http_response_code(204);
    exit;
}
