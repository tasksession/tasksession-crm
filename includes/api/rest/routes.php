<?php
/**
 * REST route dispatcher (Phase 7).
 */

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/v1/tools.php';
require_once __DIR__ . '/v1/tasks.php';
require_once __DIR__ . '/v1/clients.php';

function api_rest_path_info()
{
    // Prefer PATH_INFO / query rewrite
    if (!empty($_GET['api_path'])) {
        return '/' . ltrim((string) $_GET['api_path'], '/');
    }
    if (!empty($_SERVER['PATH_INFO'])) {
        return (string) $_SERVER['PATH_INFO'];
    }
    $uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $uri = str_replace('\\', '/', (string) $uri);
    if (preg_match('#/api(?:/index\.php)?(/.*)?$#', $uri, $m)) {
        return $m[1] !== '' && $m[1] !== null ? $m[1] : '/';
    }
    return '/';
}

function api_rest_dispatch()
{
    global $connect;

    $method = api_request_method();
    $path = rtrim(api_rest_path_info(), '/') ?: '/';
    $input = api_request_input();

    // Health does not require auth
    if ($path === '/' || $path === '/v1' || $path === '/v1/health') {
        api_json_success([
            'name' => 'Task Session REST API',
            'version' => 'v1',
            'status' => 'ok',
            'auth' => 'Bearer token or CRM session',
        ]);
    }

    $ctx = api_auth_require($connect);
    api_rate_limit_check($ctx);

    // Session cookie mutating calls still need CSRF when not Bearer
    if (($ctx['auth_type'] ?? '') === 'session' && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
        if (!function_exists('validate_csrf_token') || !validate_csrf_token($csrf)) {
            api_json_error('Invalid CSRF token', 403, 'csrf');
        }
    }

    if ($path === '/v1/tools' && $method === 'GET') {
        api_v1_tools_list();
    }
    if ($path === '/v1/tools/invoke' && $method === 'POST') {
        api_v1_tools_invoke($input);
    }
    if ($path === '/v1/tasks' && $method === 'GET') {
        api_v1_tasks_search($input);
    }
    if ($path === '/v1/tasks/overdue' && $method === 'GET') {
        api_v1_tasks_overdue($input);
    }
    if ($path === '/v1/clients' && $method === 'GET') {
        api_v1_clients_search($input);
    }
    if ($path === '/v1/projects' && $method === 'GET') {
        api_v1_projects_search($input);
    }

    api_json_error('Not found: ' . $method . ' ' . $path, 404, 'not_found');
}
