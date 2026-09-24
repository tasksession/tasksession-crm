<?php
/**
 * REST API JSON envelope helpers (Phase 7).
 */

function api_response_headers()
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
    }
}

function api_json_success($data = null, $http = 200, array $meta = [])
{
    api_response_headers();
    http_response_code((int) $http);
    $out = ['success' => true];
    if ($data !== null) {
        $out['data'] = $data;
    }
    if (!empty($meta)) {
        $out['meta'] = $meta;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_json_error($message, $http = 400, $code = null, $extra = null)
{
    api_response_headers();
    http_response_code((int) $http);
    $out = [
        'success' => false,
        'message' => (string) $message,
    ];
    if ($code !== null && $code !== '') {
        $out['error_code'] = (string) $code;
    }
    if ($extra !== null) {
        $out['error'] = $extra;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_request_json_body()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        $cached = [];
        return $cached;
    }
    $json = json_decode($raw, true);
    $cached = is_array($json) ? $json : [];
    return $cached;
}

function api_request_input()
{
    $body = api_request_json_body();
    $get = is_array($_GET ?? null) ? $_GET : [];
    $post = is_array($_POST ?? null) ? $_POST : [];
    return array_merge($get, $post, $body);
}

function api_request_method()
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}
