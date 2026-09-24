<?php
/**
 * Binary file encryption helpers (AES-256-CBC).
 * Used for voice notes at rest — disk dump is unusable without the active key.
 * Format: ENC1 (4 bytes) + IV (16) + ciphertext (raw).
 * Encrypt uses the active key; decrypt tries active then legacy.
 */
require_once __DIR__ . '/tasksession-crypto.php';

if (!function_exists('encryptFileBytes')) {
    /**
     * @param string $bytes Raw binary content
     * @return string|false Encrypted binary payload
     */
    function encryptFileBytes($bytes) {
        if ($bytes === '' || $bytes === null) {
            return false;
        }
        $key = tasksession_aes_key(tasksession_active_secret_key());
        $iv = openssl_random_pseudo_bytes(16);
        if ($iv === false) {
            return false;
        }
        $cipher = openssl_encrypt($bytes, ENCRYPTION_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            return false;
        }
        return 'ENC1' . $iv . $cipher;
    }
}

if (!function_exists('decryptFileBytes')) {
    /**
     * @param string $payload Encrypted binary from encryptFileBytes
     * @return string|false Raw binary content
     */
    function decryptFileBytes($payload) {
        if (!is_string($payload) || strlen($payload) < 21) {
            return false;
        }
        if (substr($payload, 0, 4) !== 'ENC1') {
            return false;
        }
        $iv = substr($payload, 4, 16);
        $cipher = substr($payload, 20);
        $activeKey = tasksession_aes_key(tasksession_active_secret_key());
        $plain = openssl_decrypt($cipher, ENCRYPTION_METHOD, $activeKey, OPENSSL_RAW_DATA, $iv);
        if ($plain !== false) {
            return $plain;
        }
        $legacySecret = defined('LEGACY_SECRET_KEY') ? LEGACY_SECRET_KEY : (defined('SECRET_KEY') ? SECRET_KEY : 'your_very_secure_key');
        $legacyKey = tasksession_aes_key($legacySecret);
        if ($legacyKey !== $activeKey) {
            $plain = openssl_decrypt($cipher, ENCRYPTION_METHOD, $legacyKey, OPENSSL_RAW_DATA, $iv);
            if ($plain !== false) {
                return $plain;
            }
        }
        return false;
    }
}

if (!function_exists('isEncryptedFilePayload')) {
    function isEncryptedFilePayload($payload) {
        return is_string($payload) && strlen($payload) >= 4 && substr($payload, 0, 4) === 'ENC1';
    }
}
