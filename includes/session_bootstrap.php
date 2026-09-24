<?php
/**
 * Single place for PHP session cookie flags. Call before the first session_start().
 */

if (!function_exists('tasksession_request_is_https')) {
    function tasksession_request_is_https() {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $fwd === 'https';
    }
}

if (!function_exists('tasksession_configure_session_cookies')) {
    /**
     * @param array $options Optional overrides: samesite, domain, path, lifetime, secure, httponly
     */
    function tasksession_configure_session_cookies(array $options = []) {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $secure = array_key_exists('secure', $options)
            ? (bool) $options['secure']
            : tasksession_request_is_https();
        $params = [
            'lifetime' => isset($options['lifetime']) ? (int) $options['lifetime'] : 0,
            'path' => isset($options['path']) ? (string) $options['path'] : '/',
            'domain' => isset($options['domain']) ? (string) $options['domain'] : '',
            'secure' => $secure,
            'httponly' => array_key_exists('httponly', $options) ? (bool) $options['httponly'] : true,
            'samesite' => isset($options['samesite']) ? (string) $options['samesite'] : 'Lax',
        ];

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($params);
            return;
        }

        $path = $params['path'] . '; SameSite=' . $params['samesite'];
        session_set_cookie_params($params['lifetime'], $path, $params['domain'], $params['secure'], $params['httponly']);
    }
}

if (!function_exists('tasksession_session_start')) {
    function tasksession_session_start(array $cookieOptions = []) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        tasksession_configure_session_cookies($cookieOptions);
        if (session_status() === PHP_SESSION_NONE) {
            return session_start();
        }
        return true;
    }
}
