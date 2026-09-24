<?php
/**
 * Dual-key string encryption with authenticated new writes.
 *
 * Legacy rows used hardcoded SECRET_KEY + fixed SECRET_IV.
 * ENC2 used AES-256-CBC with a random IV (no MAC).
 * New writes use ENC3 AES-256-GCM, or ENC3H (CBC + HMAC-SHA256) if GCM is unavailable.
 */

if (!defined('ENCRYPTION_METHOD')) {
    define('ENCRYPTION_METHOD', 'AES-256-CBC');
}
if (!defined('SECRET_KEY')) {
    define('SECRET_KEY', 'your_very_secure_key');
}
if (!defined('SECRET_IV')) {
    define('SECRET_IV', 'your_secure_iv_16');
}
if (!defined('LEGACY_SECRET_KEY')) {
    define('LEGACY_SECRET_KEY', SECRET_KEY);
}
if (!defined('LEGACY_SECRET_IV')) {
    define('LEGACY_SECRET_IV', SECRET_IV);
}

if (!function_exists('tasksession_read_env')) {
    function tasksession_read_env($name) {
        $value = getenv($name);
        if (is_string($value) && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$name]) && is_string($_ENV[$name]) && $_ENV[$name] !== '') {
            return $_ENV[$name];
        }
        if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '') {
            return $_SERVER[$name];
        }
        return '';
    }
}

if (!function_exists('tasksession_local_secret_path')) {
    function tasksession_local_secret_path() {
        return __DIR__ . DIRECTORY_SEPARATOR . 'tasksession-secret.local.php';
    }
}

if (!function_exists('tasksession_secret_is_placeholder')) {
    function tasksession_secret_is_placeholder($secret) {
        $secret = trim((string) $secret);
        if ($secret === '' || strlen($secret) < 24) {
            return true;
        }
        $placeholders = array(
            'replace-with-a-long-random-secret',
            'your_very_secure_key',
            'changeme',
            'change-me',
        );
        return in_array(strtolower($secret), $placeholders, true);
    }
}

if (!function_exists('tasksession_parse_local_secret_file')) {
    function tasksession_parse_local_secret_file($path) {
        if (!is_readable($path)) {
            return '';
        }
        $loaded = include $path;
        if (is_string($loaded) && $loaded !== '') {
            return $loaded;
        }
        if (is_array($loaded) && !empty($loaded['TASKSESSION_SECRET_KEY'])) {
            return (string) $loaded['TASKSESSION_SECRET_KEY'];
        }
        if (defined('TASKSESSION_SECRET_KEY') && (string) TASKSESSION_SECRET_KEY !== '') {
            return (string) TASKSESSION_SECRET_KEY;
        }
        return '';
    }
}

if (!function_exists('tasksession_write_local_secret_file')) {
    function tasksession_write_local_secret_file($path, $secret) {
        $secret = (string) $secret;
        if (tasksession_secret_is_placeholder($secret)) {
            return false;
        }
        $php = "<?php\n"
            . "// Auto-generated on first run. Do not commit, zip, or overwrite.\n"
            . 'return ' . var_export($secret, true) . ";\n";
        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            return false;
        }
        $tmp = $dir . DIRECTORY_SEPARATOR . '.tasksession-secret.' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($tmp, $php, LOCK_EX) === false) {
            return false;
        }
        @chmod($tmp, 0600);

        $existing = tasksession_parse_local_secret_file($path);
        if ($existing !== '' && !tasksession_secret_is_placeholder($existing)) {
            @unlink($tmp);
            return true;
        }

        $renamed = @rename($tmp, $path);
        if (!$renamed) {
            if (is_file($path)) {
                $again = tasksession_parse_local_secret_file($path);
                @unlink($tmp);
                return ($again !== '' && !tasksession_secret_is_placeholder($again));
            }
            if (!@copy($tmp, $path)) {
                @unlink($tmp);
                return false;
            }
            @unlink($tmp);
        }
        @chmod($path, 0600);
        return is_readable($path);
    }
}

if (!function_exists('tasksession_ensure_local_secret')) {
    /**
     * Create includes/tasksession-secret.local.php once if missing.
     * Never overwrites a real existing key (updates / concurrent requests).
     */
    function tasksession_ensure_local_secret() {
        $path = tasksession_local_secret_path();
        $existing = tasksession_parse_local_secret_file($path);
        if ($existing !== '' && !tasksession_secret_is_placeholder($existing)) {
            return $existing;
        }

        $lockPath = __DIR__ . DIRECTORY_SEPARATOR . '.tasksession-secret.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock) {
            @flock($lock, LOCK_EX);
        }

        $existing = tasksession_parse_local_secret_file($path);
        if ($existing !== '' && !tasksession_secret_is_placeholder($existing)) {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            return $existing;
        }

        try {
            $secret = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            return '';
        }

        $ok = tasksession_write_local_secret_file($path, $secret);
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        if (!$ok) {
            error_log('tasksession: could not auto-create includes/tasksession-secret.local.php (check includes/ is writable)');
            return '';
        }

        $written = tasksession_parse_local_secret_file($path);
        if ($written !== '' && !tasksession_secret_is_placeholder($written)) {
            return $written;
        }
        return '';
    }
}

if (!function_exists('tasksession_active_secret_key')) {
    function tasksession_active_secret_key() {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $fromEnv = tasksession_read_env('TASKSESSION_SECRET_KEY');
        if ($fromEnv !== '') {
            $cached = $fromEnv;
            return $cached;
        }

        $local = tasksession_ensure_local_secret();
        if ($local !== '') {
            $cached = $local;
            return $cached;
        }

        $cached = defined('SECRET_KEY') ? (string) SECRET_KEY : 'your_very_secure_key';
        return $cached;
    }
}

if (!function_exists('tasksession_aes_key')) {
    function tasksession_aes_key($secret) {
        return hash('sha256', (string) $secret, true);
    }
}

if (!function_exists('tasksession_mac_key')) {
    function tasksession_mac_key($secret) {
        return hash('sha256', (string) $secret . "\0mac", true);
    }
}

if (!function_exists('tasksession_gcm_cipher')) {
    function tasksession_gcm_cipher()
    {
        static $name = null;
        if ($name !== null) {
            return $name;
        }
        $name = '';
        $methods = openssl_get_cipher_methods();
        if (is_array($methods)) {
            foreach ($methods as $method) {
                if (strcasecmp((string) $method, 'aes-256-gcm') === 0) {
                    $name = (string) $method;
                    break;
                }
            }
        }
        return $name;
    }
}

if (!function_exists('tasksession_legacy_key_hex')) {
    function tasksession_legacy_key_hex() {
        return hash('sha256', defined('LEGACY_SECRET_KEY') ? LEGACY_SECRET_KEY : SECRET_KEY);
    }
}

if (!function_exists('tasksession_legacy_iv')) {
    function tasksession_legacy_iv() {
        $ivSrc = defined('LEGACY_SECRET_IV') ? LEGACY_SECRET_IV : SECRET_IV;
        return substr(hash('sha256', $ivSrc), 0, 16);
    }
}

if (!function_exists('encryptString')) {
    /**
     * Authenticated encryption. New writes are ENC3 (AES-256-GCM) or ENC3H (CBC + HMAC)
     * when GCM is unavailable. decryptString still reads ENC3, ENC3H, ENC2, and legacy.
     */
    function encryptString($string) {
        $string = (string) $string;
        $secret = tasksession_active_secret_key();
        $encKey = tasksession_aes_key($secret);
        $gcm = tasksession_gcm_cipher();
        if ($gcm !== '') {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($string, $gcm, $encKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
            if ($cipher !== false && is_string($tag) && strlen($tag) === 16) {
                return 'ENC3:' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($cipher);
            }
        }
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($string, ENCRYPTION_METHOD, $encKey, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return false;
        }
        $mac = hash_hmac('sha256', $iv . $cipher, tasksession_mac_key($secret), true);
        return 'ENC3H:' . base64_encode($iv) . ':' . base64_encode($mac) . ':' . base64_encode($cipher);
    }
}

if (!function_exists('tasksession_decrypt_enc3')) {
    function tasksession_decrypt_enc3($encrypted, $secret)
    {
        $rest = substr((string) $encrypted, 5);
        $parts = explode(':', $rest, 3);
        if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || $parts[2] === '') {
            return false;
        }
        $iv = base64_decode($parts[0], true);
        $tag = base64_decode($parts[1], true);
        $cipher = base64_decode($parts[2], true);
        $gcm = tasksession_gcm_cipher();
        if ($iv === false || $tag === false || $cipher === false || $gcm === '' || strlen($iv) !== 12 || strlen($tag) !== 16) {
            return false;
        }
        $plain = openssl_decrypt($cipher, $gcm, tasksession_aes_key($secret), OPENSSL_RAW_DATA, $iv, $tag);
        return ($plain !== false) ? $plain : false;
    }
}

if (!function_exists('tasksession_decrypt_enc3h')) {
    function tasksession_decrypt_enc3h($encrypted, $secret)
    {
        $rest = substr((string) $encrypted, 6);
        $parts = explode(':', $rest, 3);
        if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || $parts[2] === '') {
            return false;
        }
        $iv = base64_decode($parts[0], true);
        $mac = base64_decode($parts[1], true);
        $cipher = base64_decode($parts[2], true);
        if ($iv === false || $mac === false || $cipher === false || strlen($iv) !== 16 || strlen($mac) !== 32) {
            return false;
        }
        $expect = hash_hmac('sha256', $iv . $cipher, tasksession_mac_key($secret), true);
        if (!hash_equals($expect, $mac)) {
            return false;
        }
        $plain = openssl_decrypt($cipher, ENCRYPTION_METHOD, tasksession_aes_key($secret), OPENSSL_RAW_DATA, $iv);
        return ($plain !== false) ? $plain : false;
    }
}

if (!function_exists('tasksession_decrypt_enc2')) {
    function tasksession_decrypt_enc2($encrypted, $secret)
    {
        $rest = substr((string) $encrypted, 5);
        $parts = explode(':', $rest, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return false;
        }
        $iv = base64_decode($parts[0], true);
        $cipher = base64_decode($parts[1], true);
        if ($iv === false || $cipher === false || strlen($iv) !== 16) {
            return false;
        }
        $plain = openssl_decrypt($cipher, ENCRYPTION_METHOD, tasksession_aes_key($secret), OPENSSL_RAW_DATA, $iv);
        return ($plain !== false) ? $plain : false;
    }
}

if (!function_exists('decryptString')) {
    function decryptString($encrypted) {
        if ($encrypted === null || $encrypted === false) {
            return false;
        }
        $encrypted = (string) $encrypted;
        if ($encrypted === '') {
            return '';
        }

        $activeSecret = tasksession_active_secret_key();
        $legacySecret = defined('LEGACY_SECRET_KEY') ? (string) LEGACY_SECRET_KEY : (defined('SECRET_KEY') ? (string) SECRET_KEY : '');
        $secrets = array($activeSecret);
        if ($legacySecret !== '' && $legacySecret !== $activeSecret) {
            $secrets[] = $legacySecret;
        }

        if (strncmp($encrypted, 'ENC3H:', 6) === 0) {
            foreach ($secrets as $secret) {
                $plain = tasksession_decrypt_enc3h($encrypted, $secret);
                if ($plain !== false) {
                    return $plain;
                }
            }
            return false;
        }

        if (strncmp($encrypted, 'ENC3:', 5) === 0) {
            foreach ($secrets as $secret) {
                $plain = tasksession_decrypt_enc3($encrypted, $secret);
                if ($plain !== false) {
                    return $plain;
                }
            }
            return false;
        }

        if (strncmp($encrypted, 'ENC2:', 5) === 0) {
            foreach ($secrets as $secret) {
                $plain = tasksession_decrypt_enc2($encrypted, $secret);
                if ($plain !== false) {
                    return $plain;
                }
            }
            return false;
        }

        $legacyKey = tasksession_legacy_key_hex();
        $legacyIv = tasksession_legacy_iv();
        return openssl_decrypt(base64_decode($encrypted), ENCRYPTION_METHOD, $legacyKey, 0, $legacyIv);
    }
}

// First page load creates includes/tasksession-secret.local.php (never overwrites a real key).
if (!defined('TASKSESSION_SKIP_SECRET_ENSURE')) {
    tasksession_active_secret_key();
}
