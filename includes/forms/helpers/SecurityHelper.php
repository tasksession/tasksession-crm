<?php
/**
 * Forms Module – Security helpers
 * Sanitization, validation, and safe output.
 */

class FormsSecurityHelper
{
    /**
     * Escape for HTML output (admin pages).
     */
    public static function escape($value)
    {
        if ($value === null || $value === '') return '';
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate that a string is a safe field name (alphanumeric + underscore).
     */
    public static function isValidFieldName($name)
    {
        return is_string($name) && preg_match('/^[a-zA-Z0-9_]+$/', $name) && strlen($name) <= 100;
    }

    /**
     * Check if destination column is in whitelist (lead columns only; custom_field checked separately).
     */
    public static function isAllowedLeadColumn($column, array $whitelist)
    {
        return in_array($column, $whitelist, true);
    }

    /**
     * Sanitize string for DB (length limit; use prepared statements for actual binding).
     */
    public static function sanitizeString($value, $maxLength = 255)
    {
        $s = trim((string)$value);
        if ($maxLength > 0 && strlen($s) > $maxLength) {
            $s = substr($s, 0, $maxLength);
        }
        return $s;
    }

    /**
     * Validate email format (basic).
     */
    public static function isValidEmail($email)
    {
        if ($email === null || $email === '') return true; // optional
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Get client IP (consider X-Forwarded-For but do not trust blindly).
     */
    public static function getClientIp()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        if (!empty($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return null;
    }

    /**
     * Get User-Agent string (truncated for storage).
     */
    public static function getUserAgent($maxLength = 500)
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
        if ($maxLength > 0 && strlen($ua) > $maxLength) {
            $ua = substr($ua, 0, $maxLength);
        }
        return $ua;
    }

    /**
     * Truncate request headers for logging (no full auth tokens).
     */
    public static function getHeadersForLog()
    {
        $out = [];
        $headers = function_exists('getallheaders') ? @getallheaders() : [];
        if (!is_array($headers)) $headers = [];
        foreach ($headers as $k => $v) {
            $k = is_string($k) ? FormsSecurityHelper::sanitizeString($k, 100) : 'header';
            $v = FormsSecurityHelper::sanitizeString((string)$v, 200);
            if (stripos($k, 'auth') !== false || stripos($k, 'token') !== false) {
                $v = '[REDACTED]';
            }
            $out[$k] = $v;
        }
        $json = json_encode($out, JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : '{}';
    }
}
