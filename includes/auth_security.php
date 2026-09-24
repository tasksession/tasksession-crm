<?php
/**
 * Auth security: TOTP schema, lockout buckets, pending MFA, session epoch, reset throttle.
 */

require_once __DIR__ . '/totp.php';
if (is_file(__DIR__ . '/tasksession-crypto.php')) {
    require_once __DIR__ . '/tasksession-crypto.php';
}
if (is_file(__DIR__ . '/trusted-device.php')) {
    require_once __DIR__ . '/trusted-device.php';
}

if (!defined('AUTH_MFA_PENDING_TTL')) {
    define('AUTH_MFA_PENDING_TTL', 300);
}
if (!defined('AUTH_IDLE_TIMEOUT_SECONDS')) {
    define('AUTH_IDLE_TIMEOUT_SECONDS', 2700);
}

if (!function_exists('auth_session_ensure_writable')) {
    /**
     * Re-open the PHP session when lib-initialize released the file lock for AJAX.
     * Writes to $_SESSION after session_write_close() are discarded.
     */
    function auth_session_ensure_writable()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        if (session_status() === PHP_SESSION_NONE) {
            return @session_start();
        }
        return false;
    }
}

if (!function_exists('auth_security_ensure_schema')) {
    function auth_security_ensure_schema($connect = null)
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
        $ready = true;

        $userCols = array(
            'totp_secret' => 'TEXT NULL',
            'totp_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'totp_confirmed_at' => 'DATETIME NULL',
            'totp_backup_codes' => 'TEXT NULL',
            'totp_last_timestep' => 'BIGINT NULL',
            'session_epoch' => 'INT NOT NULL DEFAULT 1',
        );
        foreach ($userCols as $col => $def) {
            if (function_exists('crm_db_column_exists') && crm_db_column_exists($connect, 'users', $col)) {
                continue;
            }
            $check = @$connect->query("SHOW COLUMNS FROM `users` LIKE '" . $connect->real_escape_string($col) . "'");
            if ($check && $check->num_rows > 0) {
                continue;
            }
            @$connect->query("ALTER TABLE `users` ADD COLUMN `{$col}` {$def}");
        }
        if (function_exists('crm_db_column_exists_reset')) {
            crm_db_column_exists_reset('users');
        }
        if (class_exists('User')) {
            // Force User column map refresh on next attributes() call via new request; findById uses property_exists.
        }

        @$connect->query(
            "CREATE TABLE IF NOT EXISTS `totp_settings` (
                `id` INT UNSIGNED NOT NULL DEFAULT 1,
                `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `require_admin` TINYINT(1) NOT NULL DEFAULT 0,
                `require_staff` TINYINT(1) NOT NULL DEFAULT 0,
                `require_client` TINYINT(1) NOT NULL DEFAULT 0,
                `issuer_label` VARCHAR(255) NOT NULL DEFAULT '',
                `legacy_reset_wiped` TINYINT(1) NOT NULL DEFAULT 0,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $exists = @$connect->query('SELECT id FROM totp_settings WHERE id = 1 LIMIT 1');
        if ($exists && $exists->num_rows === 0) {
            @$connect->query("INSERT INTO totp_settings (id, is_enabled, require_admin, require_staff, require_client, issuer_label, legacy_reset_wiped) VALUES (1, 0, 0, 0, 0, '', 0)");
        }
        $clientCol = @$connect->query("SHOW COLUMNS FROM `totp_settings` LIKE 'require_client'");
        if (!$clientCol || $clientCol->num_rows === 0) {
            @$connect->query("ALTER TABLE `totp_settings` ADD COLUMN `require_client` TINYINT(1) NOT NULL DEFAULT 0 AFTER `require_staff`");
        }

        @$connect->query(
            "CREATE TABLE IF NOT EXISTS `password_reset_requests` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `email` VARCHAR(255) NULL,
                `ip_address` VARCHAR(45) NULL,
                `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_email_time` (`email`, `requested_at`),
                INDEX `idx_ip_time` (`ip_address`, `requested_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        auth_security_wipe_plaintext_reset_tokens($connect);
        if (function_exists('auth_trusted_device_ensure_schema')) {
            auth_trusted_device_ensure_schema($connect);
        }
    }
}

if (!function_exists('auth_security_wipe_plaintext_reset_tokens')) {
    function auth_security_wipe_plaintext_reset_tokens($connect)
    {
        if (!($connect instanceof mysqli)) {
            return;
        }
        $flag = @$connect->query('SELECT legacy_reset_wiped FROM totp_settings WHERE id = 1 LIMIT 1');
        $wiped = 0;
        if ($flag && ($row = $flag->fetch_assoc())) {
            $wiped = (int) ($row['legacy_reset_wiped'] ?? 0);
        }
        if ($wiped === 1) {
            return;
        }
        @$connect->query("UPDATE password_reset_tokens SET used = 1 WHERE used = 0 AND (token IS NULL OR token NOT LIKE 'sha256:%')");
        @$connect->query('UPDATE totp_settings SET legacy_reset_wiped = 1 WHERE id = 1');
    }
}

if (!function_exists('auth_totp_crypto_ready')) {
    function auth_totp_crypto_ready()
    {
        if (!function_exists('tasksession_active_secret_key') || !function_exists('tasksession_secret_is_placeholder') || !function_exists('encryptString')) {
            return false;
        }
        $key = (string) tasksession_active_secret_key();
        return $key !== '' && !tasksession_secret_is_placeholder($key);
    }
}

if (!function_exists('auth_totp_encrypt_secret')) {
    function auth_totp_encrypt_secret($plain)
    {
        if (!auth_totp_crypto_ready()) {
            return false;
        }
        $enc = encryptString((string) $plain);
        return ($enc !== false && $enc !== '') ? $enc : false;
    }
}

if (!function_exists('auth_totp_decrypt_secret')) {
    function auth_totp_decrypt_secret($stored)
    {
        $stored = (string) $stored;
        if ($stored === '' || !function_exists('decryptString')) {
            return '';
        }
        $plain = decryptString($stored);
        return is_string($plain) ? $plain : '';
    }
}

if (!function_exists('auth_totp_policy')) {
    /**
     * @return array{is_enabled:int,require_admin:int,require_staff:int,require_client:int,issuer_label:string}
     */
    function auth_totp_policy($connect = null)
    {
        if (isset($GLOBALS['__auth_totp_policy']) && is_array($GLOBALS['__auth_totp_policy'])) {
            return $GLOBALS['__auth_totp_policy'];
        }
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $cached = array(
            'is_enabled' => 0,
            'require_admin' => 0,
            'require_staff' => 0,
            'require_client' => 0,
            'issuer_label' => '',
        );
        if (!($connect instanceof mysqli)) {
            $GLOBALS['__auth_totp_policy'] = $cached;
            return $cached;
        }
        auth_security_ensure_schema($connect);
        $res = @$connect->query('SELECT is_enabled, require_admin, require_staff, require_client, issuer_label FROM totp_settings WHERE id = 1 LIMIT 1');
        if ($res && ($row = $res->fetch_assoc())) {
            $cached['is_enabled'] = (int) ($row['is_enabled'] ?? 0);
            $cached['require_admin'] = (int) ($row['require_admin'] ?? 0);
            $cached['require_staff'] = (int) ($row['require_staff'] ?? 0);
            $cached['require_client'] = (int) ($row['require_client'] ?? 0);
            $cached['issuer_label'] = trim((string) ($row['issuer_label'] ?? ''));
        }
        if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
            $cached['is_enabled'] = 0;
            $cached['require_admin'] = 0;
            $cached['require_staff'] = 0;
            $cached['require_client'] = 0;
        }
        $GLOBALS['__auth_totp_policy'] = $cached;
        return $cached;
    }
}

if (!function_exists('auth_totp_save_policy')) {
    function auth_totp_save_policy($isEnabled, $requireAdmin, $requireStaff, $issuerLabel, $connect = null, $requireClient = 0)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        if (!($connect instanceof mysqli)) {
            return false;
        }
        auth_security_ensure_schema($connect);
        $isEnabled = $isEnabled ? 1 : 0;
        $requireAdmin = $requireAdmin ? 1 : 0;
        $requireStaff = $requireStaff ? 1 : 0;
        $requireClient = $requireClient ? 1 : 0;
        $issuerLabel = substr(trim((string) $issuerLabel), 0, 255);
        $stmt = $connect->prepare(
            'INSERT INTO totp_settings (id, is_enabled, require_admin, require_staff, require_client, issuer_label) VALUES (1, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), require_admin = VALUES(require_admin), require_staff = VALUES(require_staff), require_client = VALUES(require_client), issuer_label = VALUES(issuer_label)'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iiiis', $isEnabled, $requireAdmin, $requireStaff, $requireClient, $issuerLabel);
        $ok = $stmt->execute();
        $stmt->close();
        unset($GLOBALS['__auth_totp_policy']);
        return $ok;
    }
}

if (!function_exists('auth_totp_feature_enabled')) {
    function auth_totp_feature_enabled($connect = null)
    {
        $policy = auth_totp_policy($connect);
        return (int) ($policy['is_enabled'] ?? 0) === 1;
    }
}

if (!function_exists('auth_totp_issuer')) {
    function auth_totp_issuer()
    {
        $policy = auth_totp_policy();
        if ($policy['issuer_label'] !== '') {
            return $policy['issuer_label'];
        }
        global $company_name, $syatem_title, $dash_settings;
        if (!empty($company_name) && trim((string) $company_name) !== '') {
            return trim((string) $company_name);
        }
        if (is_object($dash_settings) && !empty($dash_settings->company_name)) {
            return trim((string) $dash_settings->company_name);
        }
        if (!empty($syatem_title)) {
            return trim((string) $syatem_title);
        }
        return 'TaskSession';
    }
}

if (!function_exists('auth_totp_user_need')) {
    /**
     * @return 'none'|'verify'|'setup'
     */
    function auth_totp_user_need($user)
    {
        if (!is_object($user)) {
            return 'none';
        }
        $policy = auth_totp_policy();
        if ((int) $policy['is_enabled'] !== 1) {
            return 'none';
        }
        if (!empty($user->totp_enabled)) {
            return 'verify';
        }
        $status = (int) ($user->accountStatus ?? 0);
        if ($status === 1 && (int) $policy['require_admin'] === 1) {
            return 'setup';
        }
        if ($status === 3 && (int) $policy['require_staff'] === 1) {
            return 'setup';
        }
        if ($status === 2 && (int) ($policy['require_client'] ?? 0) === 1) {
            return 'setup';
        }
        return 'none';
    }
}

if (!function_exists('auth_totp_user_is_required')) {
    function auth_totp_user_is_required($user)
    {
        if (!is_object($user)) {
            return false;
        }
        $policy = auth_totp_policy();
        if ((int) $policy['is_enabled'] !== 1) {
            return false;
        }
        $status = (int) ($user->accountStatus ?? 0);
        if ($status === 1) {
            return (int) $policy['require_admin'] === 1;
        }
        if ($status === 3) {
            return (int) $policy['require_staff'] === 1;
        }
        if ($status === 2) {
            return (int) ($policy['require_client'] ?? 0) === 1;
        }
        return false;
    }
}

if (!function_exists('auth_totp_user_can_disable')) {
    function auth_totp_user_can_disable($user)
    {
        if (!is_object($user) || empty($user->totp_enabled)) {
            return false;
        }
        return !auth_totp_user_is_required($user);
    }
}

if (!function_exists('auth_client_ip')) {
    function auth_client_ip()
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return substr($ip, 0, 45);
    }
}

if (!function_exists('auth_generic_lock_message')) {
    function auth_generic_lock_message()
    {
        global $lang;
        if (isset($lang['Too many attempts. Please try again later.'])) {
            return (string) $lang['Too many attempts. Please try again later.'];
        }
        return 'Too many attempts. Please try again later.';
    }
}

if (!function_exists('auth_generic_login_fail_message')) {
    function auth_generic_login_fail_message()
    {
        global $lang;
        if (isset($lang['Email/password combination incorrect'])) {
            return (string) $lang['Email/password combination incorrect'];
        }
        return 'Email/password combination incorrect';
    }
}

if (!function_exists('auth_login_cooldown_seconds')) {
    function auth_login_cooldown_seconds($failCount)
    {
        $failCount = (int) $failCount;
        if ($failCount < 5) {
            return 0;
        }
        if ($failCount < 10) {
            return 300;
        }
        if ($failCount < 15) {
            return 900;
        }
        return 3600;
    }
}

if (!function_exists('auth_login_email_locked')) {
    function auth_login_email_locked($email, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $email = trim((string) $email);
        if ($email === '' || !($connect instanceof mysqli)) {
            return false;
        }
        $sql = "SELECT COUNT(*) AS cnt, UNIX_TIMESTAMP(MAX(attempt_time)) AS last_ts
                FROM login_attempts
                WHERE email = ? AND success = 0
                AND attempt_time > DATE_SUB(NOW(), INTERVAL 2 HOUR)";
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $cnt = (int) ($row['cnt'] ?? 0);
        $last = (int) ($row['last_ts'] ?? 0);
        $cool = auth_login_cooldown_seconds($cnt);
        if ($cool <= 0 || $last <= 0) {
            return false;
        }
        return (time() - $last) < $cool;
    }
}

if (!function_exists('auth_login_ip_abused')) {
    function auth_login_ip_abused($ip, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $ip = trim((string) $ip);
        if ($ip === '' || !($connect instanceof mysqli)) {
            return false;
        }
        $sql = "SELECT COUNT(DISTINCT email) AS cnt, UNIX_TIMESTAMP(MAX(attempt_time)) AS last_ts
                FROM login_attempts
                WHERE ip_address = ? AND success = 0
                AND email IS NOT NULL AND TRIM(email) <> ''
                AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $distinct = (int) ($row['cnt'] ?? 0);
        $last = (int) ($row['last_ts'] ?? 0);
        if ($distinct < 12 || $last <= 0) {
            return false;
        }
        return (time() - $last) < 900;
    }
}

if (!function_exists('auth_forgot_throttled')) {
    function auth_forgot_throttled($email, $ip, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        if (!($connect instanceof mysqli)) {
            return true;
        }
        auth_security_ensure_schema($connect);
        $email = trim((string) $email);
        $ip = trim((string) $ip);
        $emailCount = 0;
        $ipCount = 0;
        if ($email !== '') {
            $stmt = $connect->prepare('SELECT COUNT(*) AS cnt FROM password_reset_requests WHERE email = ? AND requested_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $emailCount = (int) ($row['cnt'] ?? 0);
                $stmt->close();
            }
        }
        if ($ip !== '') {
            $stmt = $connect->prepare('SELECT COUNT(*) AS cnt FROM password_reset_requests WHERE ip_address = ? AND requested_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
            if ($stmt) {
                $stmt->bind_param('s', $ip);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $ipCount = (int) ($row['cnt'] ?? 0);
                $stmt->close();
            }
        }
        return $emailCount >= 5 || $ipCount >= 20;
    }
}

if (!function_exists('auth_forgot_record_request')) {
    function auth_forgot_record_request($email, $ip, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        if (!($connect instanceof mysqli)) {
            return;
        }
        auth_security_ensure_schema($connect);
        $email = trim((string) $email);
        $ip = trim((string) $ip);
        $stmt = $connect->prepare('INSERT INTO password_reset_requests (email, ip_address) VALUES (?, ?)');
        if ($stmt) {
            $stmt->bind_param('ss', $email, $ip);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('auth_forgot_generic_message')) {
    function auth_forgot_generic_message()
    {
        return 'If an account with that email address exists, password reset instructions have been sent.';
    }
}

if (!function_exists('auth_mfa_is_pending')) {
    function auth_mfa_is_pending()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        $uid = (int) ($_SESSION['mfa_pending_user_id'] ?? 0);
        $until = (int) ($_SESSION['mfa_pending_until'] ?? 0);
        if ($uid <= 0) {
            return false;
        }
        if ($until > 0 && time() > $until) {
            auth_mfa_clear_pending();
            return false;
        }
        return true;
    }
}

if (!function_exists('auth_mfa_clear_pending')) {
    function auth_mfa_clear_pending()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        unset(
            $_SESSION['mfa_pending_user_id'],
            $_SESSION['mfa_pending_until'],
            $_SESSION['mfa_pending_remember'],
            $_SESSION['mfa_setup_required'],
            $_SESSION['mfa_pending_email'],
            $_SESSION['mfa_pending_provider'],
            $_SESSION['totp_setup_secret']
        );
    }
}

if (!function_exists('auth_mfa_start_pending')) {
    function auth_mfa_start_pending($user, $rememberMe, $setupRequired, $provider = 'local')
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset(
            $_SESSION['userId'],
            $_SESSION['logged_user_id'],
            $_SESSION['accountStatus'],
            $_SESSION['username'],
            $_SESSION['auth_provider']
        );
        $_SESSION['mfa_pending_user_id'] = (int) $user->id;
        $_SESSION['mfa_pending_until'] = time() + AUTH_MFA_PENDING_TTL;
        $_SESSION['mfa_pending_remember'] = $rememberMe ? 1 : 0;
        $_SESSION['mfa_setup_required'] = $setupRequired ? 1 : 0;
        $_SESSION['mfa_pending_email'] = (string) ($user->email ?? '');
        $_SESSION['mfa_pending_provider'] = (string) $provider;
        if ($setupRequired) {
            unset($_SESSION['totp_setup_secret']);
        }
    }
}

if (!function_exists('auth_mfa_script_name')) {
    function auth_mfa_script_name()
    {
        $script = strtolower(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '')));
        return basename($script);
    }
}

if (!function_exists('auth_mfa_path_allowed')) {
    function auth_mfa_path_allowed()
    {
        $script = strtolower(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '')));
        $base = basename($script);
        $allowedBase = array(
            'setup-2fa.php',
            'verify-2fa.php',
            'logout.php',
        );
        if (in_array($base, $allowedBase, true)) {
            return true;
        }
        if ($base === 'index.php') {
            if (preg_match('#/(admin|staff|client|mail|marketing|ecommerce|ai|ajax|api|system-api)/index\.php$#', $script)) {
                return false;
            }
            return true;
        }
        $uri = strtolower(str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? '')));
        if (preg_match('#/(assets|uploads)/#', $uri)) {
            return true;
        }
        return false;
    }
}

if (!function_exists('auth_mfa_is_json_request')) {
    function auth_mfa_is_json_request()
    {
        $uri = strtolower(str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? '')));
        if (preg_match('#/(ajax|api|real-chat|system-api)/#', $uri)) {
            return true;
        }
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            return true;
        }
        return false;
    }
}

if (!function_exists('auth_mfa_pending_enforce')) {
    function auth_mfa_pending_enforce()
    {
        if (!auth_mfa_is_pending()) {
            return;
        }
        if (auth_mfa_path_allowed()) {
            return;
        }
        global $url;
        $setup = !empty($_SESSION['mfa_setup_required']);
        $target = ($setup ? 'setup-2fa.php' : 'verify-2fa.php');
        $base = isset($url) ? rtrim((string) $url, '/') . '/' : '/';
        if (auth_mfa_is_json_request()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'success' => false,
                'status' => 'error',
                'message' => 'Authentication required',
            ));
            exit;
        }
        header('Location: ' . $base . $target, true, 302);
        exit;
    }
}

if (!function_exists('auth_bump_session_epoch')) {
    function auth_bump_session_epoch($userId, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $userId = (int) $userId;
        if ($userId <= 0 || !($connect instanceof mysqli)) {
            return false;
        }
        auth_security_ensure_schema($connect);
        @$connect->query('UPDATE users SET session_epoch = IFNULL(session_epoch, 1) + 1 WHERE id = ' . $userId . ' LIMIT 1');
        if (session_status() === PHP_SESSION_ACTIVE && (int) ($_SESSION['userId'] ?? 0) === $userId) {
            $res = @$connect->query('SELECT session_epoch FROM users WHERE id = ' . $userId . ' LIMIT 1');
            if ($res && ($row = $res->fetch_assoc())) {
                $_SESSION['session_epoch'] = (int) $row['session_epoch'];
            }
        }
        if (class_exists('User') && method_exists('User', 'revokeRememberMeTokens')) {
            User::revokeRememberMeTokens($userId);
        } elseif (is_file(__DIR__ . '/remember_me.php')) {
            require_once __DIR__ . '/remember_me.php';
            $rm = new RememberMe();
            $rm->deleteAllUserTokens($userId);
        }
        if (function_exists('auth_trusted_device_revoke_user')) {
            auth_trusted_device_revoke_user($userId, $connect);
        }
        return true;
    }
}

if (!function_exists('auth_enforce_session_epoch_on_user')) {
    function auth_enforce_session_epoch_on_user($user)
    {
        if (!is_object($user) || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (empty($_SESSION['userId']) || (int) $_SESSION['userId'] !== (int) ($user->id ?? 0)) {
            return;
        }
        if (!isset($_SESSION['session_epoch']) || !isset($user->session_epoch)) {
            return;
        }
        if ((int) $_SESSION['session_epoch'] === (int) $user->session_epoch) {
            return;
        }
        auth_mfa_clear_pending();
        $_SESSION = array();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
        }
        session_destroy();
        global $url;
        $base = isset($url) ? rtrim((string) $url, '/') . '/' : '/';
        if (auth_mfa_is_json_request()) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('success' => false, 'message' => 'Session expired'));
            exit;
        }
        header('Location: ' . $base, true, 302);
        exit;
    }
}

if (!function_exists('auth_password_algo')) {
    function auth_password_algo()
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return PASSWORD_ARGON2ID;
        }
        return PASSWORD_BCRYPT;
    }
}

if (!function_exists('auth_maybe_rehash_password')) {
    function auth_maybe_rehash_password($user, $plainPassword, $connect = null)
    {
        if (!is_object($user) || empty($user->password) || (string) $plainPassword === '') {
            return;
        }
        $algo = auth_password_algo();
        if ($algo === PASSWORD_BCRYPT) {
            return;
        }
        if (!@password_needs_rehash($user->password, $algo)) {
            return;
        }
        $new = @password_hash((string) $plainPassword, $algo);
        if (!is_string($new) || $new === '') {
            return;
        }
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        if (!($connect instanceof mysqli)) {
            return;
        }
        $stmt = $connect->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1');
        if ($stmt) {
            $id = (int) $user->id;
            $stmt->bind_param('si', $new, $id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('auth_store_totp_enabled')) {
    /**
     * @param string[] $plainCodes
     */
    function auth_store_totp_enabled($userId, $plainSecret, array $plainCodes, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $userId = (int) $userId;
        if ($userId <= 0 || !($connect instanceof mysqli)) {
            return false;
        }
        if (!auth_totp_crypto_ready()) {
            return false;
        }
        $enc = auth_totp_encrypt_secret($plainSecret);
        if ($enc === false) {
            return false;
        }
        $hashes = array();
        foreach ($plainCodes as $code) {
            $hashes[] = array('h' => totp_hash_recovery_code($code));
        }
        $json = json_encode($hashes);
        $now = date('Y-m-d H:i:s');
        $enabled = 1;
        $stmt = $connect->prepare('UPDATE users SET totp_secret = ?, totp_enabled = ?, totp_confirmed_at = ?, totp_backup_codes = ?, totp_last_timestep = NULL WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('sissi', $enc, $enabled, $now, $json, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('auth_totp_consume_recovery_code')) {
    function auth_totp_consume_recovery_code($userId, $code, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $userId = (int) $userId;
        $code = totp_normalize_recovery_code($code);
        if ($userId <= 0 || $code === '' || !($connect instanceof mysqli)) {
            return false;
        }
        $connect->begin_transaction();
        try {
            $stmt = $connect->prepare('SELECT totp_backup_codes FROM users WHERE id = ? LIMIT 1 FOR UPDATE');
            if (!$stmt) {
                $connect->rollback();
                return false;
            }
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            $list = json_decode((string) ($row['totp_backup_codes'] ?? '[]'), true);
            if (!is_array($list) || $list === []) {
                $connect->rollback();
                return false;
            }
            $matched = false;
            $kept = array();
            foreach ($list as $item) {
                $hash = is_array($item) ? (string) ($item['h'] ?? '') : (string) $item;
                if (!$matched && $hash !== '' && password_verify($code, $hash)) {
                    $matched = true;
                    continue;
                }
                $kept[] = is_array($item) ? $item : array('h' => $hash);
            }
            if (!$matched) {
                $connect->rollback();
                return false;
            }
            $json = json_encode(array_values($kept));
            $upd = $connect->prepare('UPDATE users SET totp_backup_codes = ? WHERE id = ? LIMIT 1');
            if (!$upd) {
                $connect->rollback();
                return false;
            }
            $upd->bind_param('si', $json, $userId);
            $upd->execute();
            $upd->close();
            $connect->commit();
            return true;
        } catch (Throwable $e) {
            $connect->rollback();
            return false;
        }
    }
}

if (!function_exists('auth_totp_verify_and_claim')) {
    /**
     * Verify a TOTP code and atomically consume its timestep (replay-safe).
     *
     * @return array{ok:bool,slice:?int}
     */
    function auth_totp_verify_and_claim($userId, $code, $window = 2, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $userId = (int) $userId;
        $code = preg_replace('/\s+/', '', (string) $code);
        $window = max(0, (int) $window);
        if ($userId <= 0 || !preg_match('/^\d{6}$/', $code) || !($connect instanceof mysqli)) {
            return array('ok' => false, 'slice' => null);
        }

        $started = false;
        try {
            $started = $connect->begin_transaction();
        } catch (Throwable $e) {
            $started = false;
        }

        $rollback = function () use ($connect, $started) {
            if ($started) {
                $connect->rollback();
            }
        };

        $sql = $started
            ? 'SELECT totp_secret, totp_last_timestep FROM users WHERE id = ? LIMIT 1 FOR UPDATE'
            : 'SELECT totp_secret, totp_last_timestep FROM users WHERE id = ? LIMIT 1';
        $stmt = $connect->prepare($sql);
        if (!$stmt) {
            $rollback();
            return array('ok' => false, 'slice' => null);
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            $rollback();
            return array('ok' => false, 'slice' => null);
        }

        $secret = auth_totp_decrypt_secret($row['totp_secret'] ?? '');
        $check = totp_verify($secret, $code, $window);
        if (empty($check['ok'])) {
            $rollback();
            return array('ok' => false, 'slice' => null);
        }
        $slice = (int) $check['slice'];
        $last = (int) ($row['totp_last_timestep'] ?? 0);
        if ($last > 0 && $slice <= $last) {
            $rollback();
            return array('ok' => false, 'slice' => null);
        }

        $upd = $connect->prepare(
            'UPDATE users SET totp_last_timestep = ?
             WHERE id = ? AND (totp_last_timestep IS NULL OR totp_last_timestep < ?)
             LIMIT 1'
        );
        if (!$upd) {
            $rollback();
            return array('ok' => false, 'slice' => null);
        }
        $upd->bind_param('iii', $slice, $userId, $slice);
        $upd->execute();
        $claimed = $upd->affected_rows > 0;
        $upd->close();
        if (!$claimed) {
            $rollback();
            return array('ok' => false, 'slice' => null);
        }
        if ($started) {
            $connect->commit();
        }
        return array('ok' => true, 'slice' => $slice);
    }
}

if (!function_exists('auth_totp_mark_timestep')) {
    function auth_totp_mark_timestep($userId, $slice, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $userId = (int) $userId;
        $slice = (int) $slice;
        if ($userId <= 0 || !($connect instanceof mysqli)) {
            return false;
        }
        $stmt = $connect->prepare(
            'UPDATE users SET totp_last_timestep = ?
             WHERE id = ? AND (totp_last_timestep IS NULL OR totp_last_timestep < ?)
             LIMIT 1'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iii', $slice, $userId, $slice);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}

if (!function_exists('auth_totp_enrollment_counts')) {
    /**
     * @return array{enabled:int,admins_missing:int,staff_missing:int,clients_missing:int}
     */
    function auth_totp_enrollment_counts($connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $out = array('enabled' => 0, 'admins_missing' => 0, 'staff_missing' => 0, 'clients_missing' => 0);
        if (!($connect instanceof mysqli)) {
            return $out;
        }
        auth_security_ensure_schema($connect);
        $q = @$connect->query('SELECT COUNT(*) AS c FROM users WHERE totp_enabled = 1');
        if ($q && ($row = $q->fetch_assoc())) {
            $out['enabled'] = (int) $row['c'];
        }
        $q = @$connect->query('SELECT COUNT(*) AS c FROM users WHERE accountStatus = 1 AND IFNULL(totp_enabled,0) = 0 AND IFNULL(status,0) = 0');
        if ($q && ($row = $q->fetch_assoc())) {
            $out['admins_missing'] = (int) $row['c'];
        }
        $q = @$connect->query('SELECT COUNT(*) AS c FROM users WHERE accountStatus = 3 AND IFNULL(totp_enabled,0) = 0 AND IFNULL(status,0) = 0');
        if ($q && ($row = $q->fetch_assoc())) {
            $out['staff_missing'] = (int) $row['c'];
        }
        $q = @$connect->query('SELECT COUNT(*) AS c FROM users WHERE accountStatus = 2 AND IFNULL(totp_enabled,0) = 0 AND IFNULL(status,0) = 0');
        if ($q && ($row = $q->fetch_assoc())) {
            $out['clients_missing'] = (int) $row['c'];
        }
        return $out;
    }
}

if (!function_exists('auth_totp_enrolled_users')) {
    /**
     * @return list<array{id:int,email:string,firstName:string,last_name:string,accountStatus:int}>
     */
    function auth_totp_enrolled_users($connect = null, $limit = 100)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        if (!($connect instanceof mysqli)) {
            return array();
        }
        auth_security_ensure_schema($connect);
        $limit = max(1, min(200, (int) $limit));
        $sql = 'SELECT id, email, firstName, last_name, accountStatus FROM users WHERE IFNULL(totp_enabled, 0) = 1 ORDER BY accountStatus ASC, firstName ASC, id ASC LIMIT ' . $limit;
        $res = @$connect->query($sql);
        $out = array();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[] = array(
                    'id' => (int) $row['id'],
                    'email' => (string) $row['email'],
                    'firstName' => (string) ($row['firstName'] ?? ''),
                    'last_name' => (string) ($row['last_name'] ?? ''),
                    'accountStatus' => (int) ($row['accountStatus'] ?? 0),
                );
            }
        }
        return $out;
    }
}

if (!function_exists('auth_totp_disable_user')) {
    function auth_totp_disable_user($userId, $connect = null)
    {
        if (!($connect instanceof mysqli)) {
            $connect = (isset($GLOBALS['connect']) && $GLOBALS['connect'] instanceof mysqli) ? $GLOBALS['connect'] : null;
        }
        $userId = (int) $userId;
        if ($userId <= 0 || !($connect instanceof mysqli)) {
            return false;
        }
        $stmt = $connect->prepare('UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_confirmed_at = NULL, totp_backup_codes = NULL, totp_last_timestep = NULL WHERE id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $userId);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            auth_bump_session_epoch($userId, $connect);
        }
        return $ok;
    }
}
