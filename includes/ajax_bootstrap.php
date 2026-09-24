<?php
/**
 * Minimal bootstrap for high-frequency AJAX polling (avoids full lib-initialize).
 */
if (!ob_get_level()) {
    ob_start();
}
@ini_set('display_errors', '0');

defined('DS') ? null : define('DS', DIRECTORY_SEPARATOR);
defined('SITE_ROOT') ? null : define('SITE_ROOT', dirname(__DIR__));
defined('LIB_ROOT') ? null : define('LIB_ROOT', SITE_ROOT . DS . 'includes');

require_once LIB_ROOT . DS . 'session_bootstrap.php';
tasksession_session_start();

require_once LIB_ROOT . DS . 'bootstrap_config.php';
require_once LIB_ROOT . DS . 'session.php';

if (!isset($session) || !($session instanceof Session)) {
    $session = new Session();
}

// Hostinger / PHP-FPM: file sessions lock. Release on GET so parallel AJAX do not 500.
if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET'
    && function_exists('session_write_close')
    && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (!isset($connect) || !($connect instanceof mysqli) || (int) $connect->connect_errno !== 0) {
    require_once LIB_ROOT . DS . 'mysqli_connect_safe.php';
    $connect = function_exists('crm_mysqli_open') ? crm_mysqli_open() : @mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME);
    if ($connect instanceof mysqli && (int) $connect->connect_errno === 0) {
        mysqli_set_charset($connect, 'utf8mb4');
    }
}

if (!function_exists('ajax_bootstrap_settings_int')) {
    function ajax_bootstrap_settings_int(string $column, int $default = 0): int
    {
        global $connect;
        static $cache = [];
        if (isset($cache[$column])) {
            return $cache[$column];
        }
        if (empty($connect)) {
            return $default;
        }
        $allowed = ['chat_refresh_interval'];
        if (!in_array($column, $allowed, true)) {
            return $default;
        }
        $sql = "SELECT `{$column}` FROM settings WHERE id = 1 LIMIT 1";
        $res = mysqli_query($connect, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        $value = ($row && isset($row[$column])) ? (int)$row[$column] : $default;
        $cache[$column] = $value;

        return $value;
    }
}
