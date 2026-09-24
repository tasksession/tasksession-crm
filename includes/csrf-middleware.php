<?php
/*
 * Central CSRF guard for mutating requests.
 * Usage: require_once(...'/includes/csrf-middleware.php'); csrf_require_for_request();
 */

if (!function_exists('csrf_request_body_raw')) {
    /**
     * php://input is read-once per request. Cache so CSRF + handlers can both decode JSON.
     */
    function csrf_request_body_raw() {
        static $buf = null;
        if ($buf !== null) {
            return $buf;
        }
        $read = file_get_contents('php://input');
        $buf = ($read === false) ? '' : $read;
        return $buf;
    }
}

if (!function_exists('csrf_collect_request_token')) {
    function csrf_collect_request_token() {
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return (string) $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        if (isset($_POST['csrf_token'])) {
            return (string) $_POST['csrf_token'];
        }

        $raw = csrf_request_body_raw();
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded) && isset($decoded['csrf_token'])) {
                return (string) $decoded['csrf_token'];
            }
        }
        return '';
    }
}

if (!function_exists('csrf_require_for_request')) {
    function csrf_require_for_request($errorMode = 'json') {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $token = csrf_collect_request_token();
        $ok = function_exists('validate_csrf_token') && $token !== '' && validate_csrf_token($token);
        if ($ok) {
            return;
        }

        http_response_code(403);
        if ($errorMode === 'json') {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token', 'error' => 'Invalid CSRF token']);
        } else {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            echo 'Invalid CSRF token';
        }
        exit;
    }
}

