<?php
/**
 * Forms Module – Parse incoming request (JSON + form-urlencoded)
 */

class FormsRequestHelper
{
    /**
     * Get merged POST + JSON body (JSON takes precedence for same keys).
     * Reads php://input once; use parsePayloadFromRaw() when you already have the raw string.
     */
    public static function getPayload()
    {
        $raw = file_get_contents('php://input');
        return self::parsePayloadFromRaw($raw !== false ? $raw : '');
    }

    /**
     * Parse raw body (JSON or application/x-www-form-urlencoded) into payload array.
     * Use this when you read php://input once and need to reuse the raw string elsewhere.
     * If the decoded result has a single key that looks like a JSON string, it is unwrapped
     * so the real form data (e.g. your-name, email-599) is used (fixes double-encoded or JSON-as-key payloads).
     */
    public static function parsePayloadFromRaw($raw)
    {
        $json = [];
        if ($raw !== '' && $raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $json = self::unwrapJsonKeyPayload($decoded);
            } elseif (is_string($decoded)) {
                // Body is a JSON-encoded string (e.g. CF7 "get a quote" sends "{\"your-name\":\"...\"}" with Content-Type text/plain)
                $trimmed = trim($decoded);
                if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                    $inner = json_decode($decoded, true);
                    if (is_array($inner)) {
                        $json = $inner;
                    } else {
                        // Lenient fallback: CF7 custom body sometimes has small JSON syntax errors (e.g. missing quote after key). Extract "key": "value" pairs.
                        $json = self::extractJsonLikePairs($decoded);
                    }
                }
            }
            if ($json === [] && $raw !== '') {
                parse_str($raw, $parsed);
                if (is_array($parsed)) {
                    $json = self::unwrapJsonKeyPayload($parsed);
                }
            }
        }
        return array_merge($_POST, $json);
    }

    /**
     * If payload has a single key that is a string looking like JSON (e.g. "{\"your-name\":\"...\"}"),
     * decode it and return that array so mapping sees your-name, email-599, etc.
     */
    private static function unwrapJsonKeyPayload(array $payload)
    {
        if (count($payload) !== 1) {
            return $payload;
        }
        $key = key($payload);
        if (!is_string($key) || $key === '') {
            return $payload;
        }
        $trimmed = trim($key);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            return $payload;
        }
        $inner = json_decode($key, true);
        if (is_array($inner)) {
            return $inner;
        }
        // Inner JSON has syntax errors (e.g. "website-url: " missing quote). Extract "key": "value" pairs so your-name etc. still work.
        $extracted = self::extractJsonLikePairs($key);
        if (!empty($extracted)) {
            return $extracted;
        }
        return $payload;
    }

    /**
     * When strict json_decode fails (e.g. CF7 custom body with small syntax errors), extract "key": "value" pairs.
     * Handles both "key" and \"key\" (escaped inside JSON string) so get a quote payload works.
     */
    private static function extractJsonLikePairs($str)
    {
        $out = [];
        if (!is_string($str) || trim($str) === '') {
            return $out;
        }
        // Normalize: when the key is from decoded JSON, inner quotes are \" so we get literal \"
        $normalized = str_replace('\"', '"', $str);
        // CF7 sometimes sends "key":_"value" (underscore) or "key": "value" – allow \s and _ between : and "
        if (preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"\s*:\s*[\s_]*"((?:[^"\\\\]|\\\\.)*)"/s', $normalized, $m, PREG_SET_ORDER)) {
            foreach ($m as $match) {
                $k = stripcslashes($match[1]);
                $v = stripcslashes($match[2]);
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * Get raw body for logging (truncate if too long).
     */
    public static function getRawPayload($maxLength = 65535)
    {
        $raw = file_get_contents('php://input');
        if ($raw === false) return '';
        if (strlen($raw) > $maxLength) {
            return substr($raw, 0, $maxLength) . '...[truncated]';
        }
        return $raw;
    }

    /**
     * Get auth token from Bearer header or query/body.
     */
    public static function getAuthToken()
    {
        $payload = self::getPayload();
        return self::getAuthTokenFromPayloadAndHeaders($payload);
    }

    /**
     * Get auth token from Bearer header, GET, or pre-parsed payload (use when body already read).
     */
    public static function getAuthTokenFromPayloadAndHeaders(array $payload = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        foreach ($headers ?: [] as $k => $v) {
            if (strtolower($k) === 'authorization' && preg_match('/^\s*Bearer\s+(.+)$/i', trim($v), $m)) {
                return trim($m[1]);
            }
        }
        if (isset($_GET['token'])) return trim((string)$_GET['token']);
        return isset($payload['token']) ? trim((string)$payload['token']) : (isset($payload['auth_token']) ? trim((string)$payload['auth_token']) : null);
    }

    /**
     * Check request method (e.g. POST).
     * Also accepts X-HTTP-Method-Override: POST (for proxies that change method).
     */
    public static function isMethod($method)
    {
        $m = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
        if ($m === strtoupper($method)) {
            return true;
        }
        if (strtoupper($method) !== 'POST') {
            return false;
        }
        if (function_exists('getallheaders')) {
            foreach (getallheaders() ?: [] as $k => $v) {
                if (strtolower($k) === 'x-http-method-override' && strtoupper(trim($v)) === 'POST') {
                    return true;
                }
            }
        }
        return false;
    }
}
