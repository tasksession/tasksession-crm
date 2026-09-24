<?php
/**
 * REST API auth — Bearer token or CRM session (Phase 7).
 */

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/tokens.php';

function api_auth_bearer_raw()
{
    $header = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $header = (string) $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $header = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) {
                $header = (string) $v;
                break;
            }
        }
    }
    if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * Resolve caller. Sets $_SESSION user context for permission helpers.
 *
 * @return array{auth_type:string,user_id:int,token:?array,scopes:array}
 */
function api_auth_require($connect = null)
{
    if ($connect === null) {
        global $connect;
    }
    global $session;

    $bearer = api_auth_bearer_raw();
    if ($bearer !== '') {
        $token = api_token_find_by_raw($bearer, $connect);
        if (!$token) {
            api_json_error('Invalid or expired API token', 401, 'unauthorized');
        }
        $userId = (int) $token['user_id'];
        if (!api_auth_load_user_context($userId, $connect)) {
            api_json_error('Token user not found or inactive', 401, 'unauthorized');
        }
        api_token_touch((int) $token['id'], $connect);
        $ctx = [
            'auth_type' => 'bearer',
            'user_id' => $userId,
            'token' => $token,
            'scopes' => $token['scopes'] ?? [],
            'rate_limit_per_min' => (int) ($token['rate_limit_per_min'] ?? 60),
        ];
        $GLOBALS['api_auth_context'] = $ctx;
        return $ctx;
    }

    if ($session && $session->isLoggedIn() && !empty($_SESSION['userId'])) {
        ensure_user_permissions($connect);
        $ctx = [
            'auth_type' => 'session',
            'user_id' => (int) $_SESSION['userId'],
            'token' => null,
            'scopes' => api_token_all_scopes(), // session inherits full API capability; CRM perms still apply
            'rate_limit_per_min' => 120,
        ];
        $GLOBALS['api_auth_context'] = $ctx;
        return $ctx;
    }

    api_json_error('Authorization required (Bearer token or logged-in session)', 401, 'unauthorized');
}

function api_auth_load_user_context($userId, $connect)
{
    $userId = (int) $userId;
    if ($userId <= 0 || !$connect instanceof mysqli) {
        return false;
    }
    $res = $connect->query(
        "SELECT id, accountStatus, role_id, firstName, last_name, email
         FROM users WHERE id = {$userId} AND status = 0 LIMIT 1"
    );
    if (!$res || !($row = $res->fetch_assoc())) {
        return false;
    }
    $res->free();

    $_SESSION['userId'] = (int) $row['id'];
    $_SESSION['logged_user_id'] = (int) $row['id'];
    $_SESSION['accountStatus'] = (int) $row['accountStatus'];
    unset($_SESSION['permissions'], $_SESSION['permissions_role_id']);
    ensure_user_permissions($connect);
    return true;
}

function api_auth_context()
{
    return $GLOBALS['api_auth_context'] ?? null;
}
