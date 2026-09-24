<?php
/**
 * MFA "Trust this device" — separate from Remember Me.
 * Cookie holds only a random token. Server stores SHA-256(hash) + user id + expiry.
 * Never stores TOTP codes or secrets.
 */

if (!function_exists('auth_trusted_device_cookie_name')) {
    function auth_trusted_device_cookie_name()
    {
        return 'ts_trusted_device';
    }
}

if (!function_exists('auth_trusted_device_ttl_days')) {
    function auth_trusted_device_ttl_days($user)
    {
        $status = is_object($user) ? (int) ($user->accountStatus ?? 0) : 0;
        return ($status === 2) ? 30 : 14;
    }
}

if (!function_exists('auth_trusted_device_hash')) {
    function auth_trusted_device_hash($token)
    {
        return hash('sha256', (string) $token);
    }
}

if (!function_exists('auth_trusted_device_ensure_schema')) {
    function auth_trusted_device_ensure_schema($connect = null)
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        if (!($connect instanceof mysqli)) {
            return;
        }
        @$connect->query(
            "CREATE TABLE IF NOT EXISTS `trusted_devices` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT NOT NULL,
                `token_hash` CHAR(64) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_used_at` DATETIME NULL,
                `user_agent` TEXT NULL,
                `ip_address` VARCHAR(45) NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE KEY `uq_trusted_hash` (`token_hash`),
                KEY `idx_trusted_user` (`user_id`),
                KEY `idx_trusted_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $ready = true;
    }
}

if (!function_exists('auth_trusted_device_raw_cookie')) {
    function auth_trusted_device_raw_cookie()
    {
        $name = auth_trusted_device_cookie_name();
        $token = isset($_COOKIE[$name]) ? (string) $_COOKIE[$name] : '';
        $token = preg_replace('/[^a-f0-9]/i', '', $token);
        if (strlen($token) < 32) {
            return '';
        }
        return $token;
    }
}

if (!function_exists('auth_trusted_device_set_cookie')) {
    function auth_trusted_device_set_cookie($token, $expiresTs)
    {
        $name = auth_trusted_device_cookie_name();
        $secure = function_exists('tasksession_request_is_https')
            ? tasksession_request_is_https()
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443));
        $expiresTs = (int) $expiresTs;
        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, (string) $token, array(
                'expires' => $expiresTs,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ));
        } else {
            setcookie($name, (string) $token, $expiresTs, '/; SameSite=Lax', '', $secure, true);
        }
        $_COOKIE[$name] = (string) $token;
    }
}

if (!function_exists('auth_trusted_device_clear_cookie')) {
    function auth_trusted_device_clear_cookie()
    {
        $name = auth_trusted_device_cookie_name();
        $secure = function_exists('tasksession_request_is_https')
            ? tasksession_request_is_https()
            : false;
        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, '', array(
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ));
        } else {
            setcookie($name, '', time() - 3600, '/');
        }
        unset($_COOKIE[$name]);
    }
}

if (!function_exists('auth_trusted_device_issue')) {
    /**
     * Mint a new trusted-device token after successful TOTP. Cookie = raw token only.
     */
    function auth_trusted_device_issue($user, $connect = null)
    {
        if (!is_object($user) || (int) ($user->id ?? 0) <= 0) {
            return false;
        }
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        if (!($connect instanceof mysqli)) {
            return false;
        }
        auth_trusted_device_ensure_schema($connect);
        $userId = (int) $user->id;
        $existing = auth_trusted_device_raw_cookie();
        if ($existing !== '') {
            $oldHash = auth_trusted_device_hash($existing);
            $dis = $connect->prepare('UPDATE trusted_devices SET is_active = 0 WHERE user_id = ? AND token_hash = ? LIMIT 1');
            if ($dis) {
                $dis->bind_param('is', $userId, $oldHash);
                $dis->execute();
                $dis->close();
            }
        }
        $token = bin2hex(random_bytes(32));
        $hash = auth_trusted_device_hash($token);
        $days = auth_trusted_device_ttl_days($user);
        $expiresTs = time() + ($days * 86400);
        $expiresAt = date('Y-m-d H:i:s', $expiresTs);
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512);
        $ip = function_exists('auth_client_ip') ? auth_client_ip() : substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $stmt = $connect->prepare(
            'INSERT INTO trusted_devices (user_id, token_hash, expires_at, last_used_at, user_agent, ip_address, is_active) VALUES (?, ?, ?, NOW(), ?, ?, 1)'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('issss', $userId, $hash, $expiresAt, $ua, $ip);
        $ok = $stmt->execute();
        $stmt->close();
        if (!$ok) {
            return false;
        }
        auth_trusted_device_prune_user($userId, $connect);
        auth_trusted_device_set_cookie($token, $expiresTs);
        return true;
    }
}

if (!function_exists('auth_trusted_device_prune_user')) {
    function auth_trusted_device_prune_user($userId, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $userId = (int) $userId;
        if ($userId <= 0 || !($connect instanceof mysqli)) {
            return;
        }
        @$connect->query(
            'UPDATE trusted_devices SET is_active = 0
             WHERE user_id = ' . $userId . ' AND is_active = 1 AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM trusted_devices
                    WHERE user_id = ' . $userId . ' AND is_active = 1
                    ORDER BY created_at DESC
                    LIMIT 8
                ) AS keep_rows
             )'
        );
    }
}

if (!function_exists('auth_trusted_device_matches')) {
    function auth_trusted_device_matches($user, $connect = null)
    {
        if (!is_object($user) || (int) ($user->id ?? 0) <= 0) {
            return false;
        }
        $token = auth_trusted_device_raw_cookie();
        if ($token === '') {
            return false;
        }
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        if (!($connect instanceof mysqli)) {
            return false;
        }
        auth_trusted_device_ensure_schema($connect);
        $hash = auth_trusted_device_hash($token);
        $userId = (int) $user->id;
        $stmt = $connect->prepare(
            'SELECT id FROM trusted_devices
             WHERE token_hash = ? AND user_id = ? AND is_active = 1 AND expires_at > NOW()
             LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $hash, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            return false;
        }
        $id = (int) $row['id'];
        $touch = $connect->prepare('UPDATE trusted_devices SET last_used_at = NOW() WHERE id = ? LIMIT 1');
        if ($touch) {
            $touch->bind_param('i', $id);
            $touch->execute();
            $touch->close();
        }
        return true;
    }
}

if (!function_exists('auth_trusted_device_revoke_user')) {
    function auth_trusted_device_revoke_user($userId, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $userId = (int) $userId;
        if ($userId <= 0 || !($connect instanceof mysqli)) {
            return false;
        }
        auth_trusted_device_ensure_schema($connect);
        $stmt = $connect->prepare('UPDATE trusted_devices SET is_active = 0 WHERE user_id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $stmt->close();
        $raw = auth_trusted_device_raw_cookie();
        if ($ok && $raw !== '') {
            $hash = auth_trusted_device_hash($raw);
            $check = $connect->prepare('SELECT id FROM trusted_devices WHERE user_id = ? AND token_hash = ? LIMIT 1');
            if ($check) {
                $check->bind_param('is', $userId, $hash);
                $check->execute();
                $owned = $check->get_result()->fetch_assoc();
                $check->close();
                if ($owned) {
                    auth_trusted_device_clear_cookie();
                }
            }
        }
        return $ok;
    }
}

if (!function_exists('auth_trusted_device_revoke_id')) {
    function auth_trusted_device_revoke_id($userId, $deviceId, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $userId = (int) $userId;
        $deviceId = (int) $deviceId;
        if ($userId <= 0 || $deviceId <= 0 || !($connect instanceof mysqli)) {
            return false;
        }
        auth_trusted_device_ensure_schema($connect);
        $currentHash = '';
        $raw = auth_trusted_device_raw_cookie();
        if ($raw !== '') {
            $currentHash = auth_trusted_device_hash($raw);
        }
        $stmt = $connect->prepare('SELECT token_hash FROM trusted_devices WHERE id = ? AND user_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ii', $deviceId, $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $upd = $connect->prepare('UPDATE trusted_devices SET is_active = 0 WHERE id = ? AND user_id = ? LIMIT 1');
        if (!$upd) {
            return false;
        }
        $upd->bind_param('ii', $deviceId, $userId);
        $ok = $upd->execute();
        $upd->close();
        if ($ok && $row && $currentHash !== '' && hash_equals((string) $row['token_hash'], $currentHash)) {
            auth_trusted_device_clear_cookie();
        }
        return $ok;
    }
}

if (!function_exists('auth_trusted_device_list')) {
    /**
     * @return list<array<string,mixed>>
     */
    function auth_trusted_device_list($userId, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $userId = (int) $userId;
        if ($userId <= 0 || !($connect instanceof mysqli)) {
            return array();
        }
        auth_trusted_device_ensure_schema($connect);
        $stmt = $connect->prepare(
            'SELECT id, created_at, last_used_at, expires_at, user_agent, ip_address, token_hash
             FROM trusted_devices
             WHERE user_id = ? AND is_active = 1 AND expires_at > NOW()
             ORDER BY created_at DESC'
        );
        if (!$stmt) {
            return array();
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = array();
        $currentHash = '';
        $raw = auth_trusted_device_raw_cookie();
        if ($raw !== '') {
            $currentHash = auth_trusted_device_hash($raw);
        }
        while ($row = $res->fetch_assoc()) {
            $out[] = array(
                'id' => (int) $row['id'],
                'created_at' => (string) $row['created_at'],
                'last_used_at' => (string) ($row['last_used_at'] ?? ''),
                'expires_at' => (string) $row['expires_at'],
                'user_agent' => (string) ($row['user_agent'] ?? ''),
                'ip_address' => (string) ($row['ip_address'] ?? ''),
                'is_current' => ($currentHash !== '' && hash_equals((string) $row['token_hash'], $currentHash)),
            );
        }
        $stmt->close();
        return $out;
    }
}

if (!function_exists('auth_trusted_device_label')) {
    function auth_trusted_device_label($userAgent)
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return '';
        }
        $browser = 'Browser';
        if (stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge/') !== false) {
            $browser = 'Edge';
        } elseif (stripos($ua, 'OPR/') !== false || stripos($ua, 'Opera') !== false) {
            $browser = 'Opera';
        } elseif (stripos($ua, 'Chrome/') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($ua, 'Firefox/') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($ua, 'Safari/') !== false) {
            $browser = 'Safari';
        }
        $os = '';
        if (stripos($ua, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($ua, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
            $os = 'iOS';
        } elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) {
            $os = 'macOS';
        } elseif (stripos($ua, 'Linux') !== false) {
            $os = 'Linux';
        }
        return $os !== '' ? ($browser . ' on ' . $os) : $browser;
    }
}

if (!function_exists('auth_trusted_device_public_ip')) {
    function auth_trusted_device_public_ip($ip)
    {
        $ip = strtolower(trim((string) $ip));
        if ($ip === '' || $ip === '::1' || $ip === '0:0:0:0:0:0:0:1' || $ip === '127.0.0.1') {
            return '';
        }
        if (strpos($ip, '127.') === 0 || strpos($ip, '::ffff:127.') === 0) {
            return '';
        }
        return $ip;
    }
}

if (!function_exists('auth_trusted_device_icon')) {
    function auth_trusted_device_icon($userAgent)
    {
        $ua = (string) $userAgent;
        if (preg_match('/Mobile|Android|iPhone|iPad|webOS/i', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }
}

if (!function_exists('auth_trusted_device_days_left')) {
    function auth_trusted_device_days_left($expiresAt)
    {
        $ts = strtotime((string) $expiresAt);
        if ($ts === false) {
            return 0;
        }
        return max(0, (int) ceil(($ts - time()) / 86400));
    }
}

if (!function_exists('auth_trusted_device_checkbox_html')) {
    function auth_trusted_device_checkbox_html($user)
    {
        global $lang;
        $label = $lang['Trust this device'] ?? 'Trust this device';
        return '<label class="checkbox pd-0 d-flex col-gap-5 align-items-center mb-0">'
            . '<input type="checkbox" name="trust_device" value="1" /> '
            . htmlspecialchars($label)
            . '</label>';
    }
}

if (!function_exists('auth_trusted_device_help_html')) {
    function auth_trusted_device_help_html($user)
    {
        global $lang;
        $days = auth_trusted_device_ttl_days($user);
        $template = $lang['Remember this device for %s days.'] ?? ($lang['Skip authenticator on this browser for %s days.'] ?? 'Remember this device for %s days.');
        $help = str_replace('%s', (string) $days, $template);
        return '<small class="mfa-trust-card-help d-block">' . htmlspecialchars($help) . '</small>';
    }
}

if (!function_exists('auth_trusted_device_card_html')) {
    function auth_trusted_device_card_html($user)
    {
        global $lang;
        if (!$user) {
            return '';
        }
        $title = $lang['Trust this device'] ?? 'Trust this device';
        $recommend = $lang['Recommended for your personal device.'] ?? 'Recommended for your personal device.';
        $icon = function_exists('ts_icon_inline') ? ts_icon_inline('desktop') : '';
        return '<label class="mfa-trust-card" id="mfaTrustCard">'
            . '<span class="mfa-trust-card-copy">'
            . '<input type="checkbox" name="trust_device" value="1" />'
            . '<span class="mfa-trust-card-text">'
            . '<span class="mfa-trust-card-title">' . htmlspecialchars($title) . '</span>'
            . auth_trusted_device_help_html($user)
            . '<small class="mfa-trust-card-note d-block">' . htmlspecialchars($recommend) . '</small>'
            . '</span>'
            . '</span>'
            . '<span class="mfa-trust-card-icon" aria-hidden="true">' . $icon . '</span>'
            . '</label>';
    }
}
