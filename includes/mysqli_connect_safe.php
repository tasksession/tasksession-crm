<?php
/**
 * Hostinger / open_basedir safe mysqli open.
 * PHP 8.1+ MYSQLI_REPORT_STRICT throws on unix-socket "localhost" when the
 * socket path is outside open_basedir. Retry 127.0.0.1 (TCP) without throwing.
 */

if (!function_exists('crm_mysqli_is_usable')) {
    function crm_mysqli_is_usable($conn): bool
    {
        return isset($conn) && $conn instanceof mysqli && (int) $conn->connect_errno === 0;
    }
}

if (!function_exists('crm_mysqli_open')) {
    /**
     * @return mysqli|null
     */
    function crm_mysqli_open(?string $host = null, ?string $user = null, ?string $pass = null, ?string $name = null)
    {
        mysqli_report(MYSQLI_REPORT_OFF);

        $host = $host ?? (defined('DB_SERVER') ? (string) DB_SERVER : 'localhost');
        $user = $user ?? (defined('DB_USER') ? (string) DB_USER : '');
        $pass = $pass ?? (defined('DB_PASS') ? (string) DB_PASS : '');
        $name = $name ?? (defined('DB_NAME') ? (string) DB_NAME : '');

        $hosts = [$host];
        $normalized = strtolower(trim($host));
        if ($normalized === 'localhost' || $normalized === 'localhost:3306') {
            $hosts[] = '127.0.0.1';
        }

        $last = null;
        foreach (array_unique($hosts) as $tryHost) {
            try {
                $conn = @new mysqli($tryHost, $user, $pass, $name);
                if (crm_mysqli_is_usable($conn)) {
                    $conn->set_charset('utf8mb4');
                    return $conn;
                }
                $last = $conn;
            } catch (Throwable $e) {
                error_log('crm_mysqli_open: ' . $e->getMessage());
                $last = null;
            }
        }

        return ($last instanceof mysqli) ? $last : null;
    }
}
