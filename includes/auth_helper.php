<?php
/**
 * Enhanced Authentication Helper
 * Provides secure authentication methods and session management
 */

require_once __DIR__ . '/remember_me.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/auth_security.php';

class AuthHelper {
    private $rememberMe;
    private $session;
    private $cookieName = 'secure_remember_token';

    /** Max failed logins per email within the lockout window (same rules as login). */
    public static function bruteForceMaxAttempts() {
        return 5;
    }

    /** Lockout window in seconds (failed attempts older than this do not count). */
    public static function bruteForceLockoutSeconds() {
        return 900; // 15 minutes
    }

    /**
     * Emails currently over the failed-attempt threshold within the lockout window.
     * @return array<string,int> email => failure count in window
     */
    public static function getTemporarilyLockedEmailCounts() {
        global $connect;
        if (!$connect) {
            return array();
        }
        $sec = (int) self::bruteForceLockoutSeconds();
        $max = (int) self::bruteForceMaxAttempts();
        $sql = "SELECT email, COUNT(*) AS cnt
                FROM login_attempts
                WHERE success = 0
                AND attempt_time > DATE_SUB(NOW(), INTERVAL " . $sec . " SECOND)
                AND email IS NOT NULL AND TRIM(email) <> ''
                GROUP BY email
                HAVING cnt >= " . $max;
        $res = $connect->query($sql);
        $out = array();
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $out[(string)$row['email']] = (int)$row['cnt'];
            }
        }
        return $out;
    }

    /** Clear failed (unsuccessful) login rows for an email — same as successful-login reset. */
    public static function clearFailedAttemptsForEmail($email) {
        global $connect;
        $email = trim((string)$email);
        if ($email === '' || !$connect) {
            return false;
        }
        $stmt = $connect->prepare('DELETE FROM login_attempts WHERE email = ? AND success = 0');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $email);
        return $stmt->execute();
    }
    
    public function __construct($session) {
        $this->rememberMe = new RememberMe();
        $this->session = $session;
    }
    
    /**
     * Attempt to login a user
     */
    public function attemptLogin($email, $password, $rememberMe = false) {
        $ip = auth_client_ip();
        if (auth_login_email_locked($email) || auth_login_ip_abused($ip)) {
            return [
                'success' => false,
                'message' => auth_generic_lock_message()
            ];
        }
        
        // Authenticate user
        $user = User::authenticate($email, $password);
        
        if ($user) {
            if (function_exists('auth_maybe_rehash_password')) {
                auth_maybe_rehash_password($user, $password);
            }

            if (User::isTrashed($user)) {
                $this->logLoginActivity($user->id, false, $email, 'failed');
                return [
                    'success' => false,
                    'message' => User::trashedLoginMessage(),
                ];
            }
            
            // Check if account is activated
            if ($user->accountStatus == 0) {
                return [
                    'success' => false,
                    'message' => 'Your account has not been activated yet. Please check your email to activate!'
                ];
            }

            $ipValidation = $this->validateLoginIp($user);
            if (!$ipValidation['allowed']) {
                $this->logLoginActivity($user->id, false, $email, 'failed');
                return [
                    'success' => false,
                    'message' => $ipValidation['message']
                ];
            }

            return $this->finishVerifiedLogin($user, $rememberMe, 'local');
        } else {
            $this->incrementFailedAttempts($email);
            $this->logLoginActivity(null, false, $email);
            
            return [
                'success' => false,
                'message' => auth_generic_login_fail_message()
            ];
        }
    }

    /**
     * After password / Google / remember-me credentials are already proven.
     * Never sets a full session until TOTP (if required) succeeds.
     *
     * @return array<string,mixed>
     */
    public function finishVerifiedLogin($user, $rememberMe = false, $provider = 'local')
    {
        $need = auth_totp_user_need($user);
        if ($need === 'verify') {
            if (function_exists('auth_trusted_device_matches') && auth_trusted_device_matches($user)) {
                return $this->completeFullLogin($user, $rememberMe, $provider);
            }
            auth_mfa_start_pending($user, $rememberMe, false, $provider);
            return [
                'success' => false,
                'needs_2fa' => true,
                'redirect' => 'verify-2fa.php',
                'message' => '',
            ];
        }
        if ($need === 'setup') {
            auth_mfa_start_pending($user, $rememberMe, true, $provider);
            return [
                'success' => false,
                'needs_2fa_setup' => true,
                'redirect' => 'setup-2fa.php',
                'message' => '',
            ];
        }
        return $this->completeFullLogin($user, $rememberMe, $provider);
    }

    /**
     * @return array<string,mixed>
     */
    public function completeFullLogin($user, $rememberMe = false, $provider = 'local')
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        auth_mfa_clear_pending();
        $this->resetFailedAttempts($user->email);
        $this->session->login($user);

        $__attPrompt = __DIR__ . '/attendance/session-prompts.php';
        if (is_file($__attPrompt)) {
            require_once $__attPrompt;
            if (function_exists('attendance_set_login_checkin_prompt_after_auth')) {
                attendance_set_login_checkin_prompt_after_auth($user);
            }
        }

        if ($rememberMe) {
            $this->setRememberMeToken($user->id);
        }

        $this->logLoginActivity($user->id, true, $user->email);
        $_SESSION['push_fresh_login'] = true;
        $_SESSION['auth_provider'] = $provider;

        return [
            'success' => true,
            'user' => $user,
            'redirect' => $this->getRedirectUrl($user)
        ];
    }

    /**
     * Complete pending MFA with TOTP or a one-time recovery code.
     *
     * @return array<string,mixed>
     */
    public function completePendingMfa($code, $trustDevice = false)
    {
        if (!auth_mfa_is_pending()) {
            return [
                'success' => false,
                'message' => 'Your verification session expired. Please log in again.',
            ];
        }
        $email = (string) ($_SESSION['mfa_pending_email'] ?? '');
        $ip = auth_client_ip();
        if (auth_login_email_locked($email) || auth_login_ip_abused($ip)) {
            return [
                'success' => false,
                'message' => auth_generic_lock_message(),
            ];
        }

        $userId = (int) $_SESSION['mfa_pending_user_id'];
        $user = User::findById($userId);
        if (!$user) {
            auth_mfa_clear_pending();
            return [
                'success' => false,
                'message' => auth_generic_login_fail_message(),
            ];
        }

        $code = trim((string) $code);
        $ok = false;
        $usedRecovery = false;
        $digits = preg_replace('/\s+/', '', $code);
        if (preg_match('/^\d{6}$/', $digits) && function_exists('auth_totp_verify_and_claim')) {
            $claimed = auth_totp_verify_and_claim($user->id, $digits, 2);
            $ok = !empty($claimed['ok']);
        } elseif (preg_match('/^\d{6}$/', $digits)) {
            $secret = auth_totp_decrypt_secret($user->totp_secret ?? '');
            $check = totp_verify($secret, $digits, 2);
            if (!empty($check['ok'])) {
                $slice = (int) $check['slice'];
                $ok = auth_totp_mark_timestep($user->id, $slice);
            }
        } else {
            $norm = totp_normalize_recovery_code($code);
            if ($norm !== '' && auth_totp_consume_recovery_code($user->id, $norm)) {
                $ok = true;
                $usedRecovery = true;
            }
        }

        if (!$ok) {
            $this->incrementFailedAttempts($email !== '' ? $email : $user->email);
            $this->logLoginActivity($user->id, false, $user->email, 'failed');
            return [
                'success' => false,
                'message' => 'Invalid verification code.',
            ];
        }

        $remember = !empty($_SESSION['mfa_pending_remember']);
        $provider = (string) ($_SESSION['mfa_pending_provider'] ?? 'local');
        if ($usedRecovery) {
            auth_bump_session_epoch($user->id);
            $user = User::findById($user->id) ?: $user;
        } elseif ($trustDevice && function_exists('auth_trusted_device_issue')) {
            auth_trusted_device_issue($user);
        }
        return $this->completeFullLogin($user, $remember, $provider);
    }

    public function validateUserLoginIp($user)
    {
        return $this->validateLoginIp($user);
    }
    
    /**
     * Attempt to login using remember me token
     */
    public function attemptRememberMeLogin() {
        if (!isset($_COOKIE[$this->cookieName])) {
            return false;
        }
        
        $token = $_COOKIE[$this->cookieName];
        $userId = $this->rememberMe->validateToken($token);
        
        if ($userId) {
            $user = User::findById($userId);
            if ($user && $user->accountStatus > 0 && !User::isTrashed($user)) {
                $ipValidation = $this->validateLoginIp($user);
                if (!$ipValidation['allowed']) {
                    $this->rememberMe->deleteToken($token);
                    $this->removeRememberMeCookie();
                    $this->logLoginActivity($user->id, false, $user->email, 'failed');
                    return false;
                }
                $result = $this->finishVerifiedLogin($user, false, 'remember_me');
                if (!empty($result['success'])) {
                    return $user;
                }
                return $result;
            }
        }
        
        // Invalid token, remove the cookie
        $this->removeRememberMeCookie();
        return false;
    }
    
    /**
     * Logout user and clean up
     */
    public function logout() {
        $userId = $this->session->userId ?? null;
        
        // Remove remember me token
        if (isset($_COOKIE[$this->cookieName])) {
            $this->rememberMe->deleteToken($_COOKIE[$this->cookieName]);
            $this->removeRememberMeCookie();
        }
        
        // Log logout activity (include email so admin logs match login rows)
        if ($userId) {
            $logoutEmail = null;
            $logoutUser = User::findById((int)$userId);
            if ($logoutUser && !empty($logoutUser->email)) {
                $logoutEmail = trim((string)$logoutUser->email);
            }
            $this->logLoginActivity($userId, true, $logoutEmail !== '' ? $logoutEmail : null, 'logout');
        }

        if (function_exists('auth_mfa_clear_pending')) {
            auth_mfa_clear_pending();
        }
        
        // Logout from session
        $this->session->logout();
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Set remember me token and cookie
     */
    private function setRememberMeToken($userId) {
        $token = $this->rememberMe->createToken($userId);
        if ($token) {
            $expiry = time() + (30 * 24 * 60 * 60); // 30 days
            $secure = function_exists('tasksession_request_is_https')
                ? tasksession_request_is_https()
                : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443));
            if (PHP_VERSION_ID >= 70300) {
                setcookie($this->cookieName, $token, [
                    'expires' => $expiry,
                    'path' => '/',
                    'domain' => '',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie($this->cookieName, $token, $expiry, '/; SameSite=Lax', '', $secure, true);
            }
        }
    }
    
    /**
     * Remove remember me cookie
     */
    private function removeRememberMeCookie() {
        $secure = function_exists('tasksession_request_is_https')
            ? tasksession_request_is_https()
            : false;
        if (PHP_VERSION_ID >= 70300) {
            setcookie($this->cookieName, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie($this->cookieName, '', time() - 3600, '/');
        }
    }

    /**
     * Login IP allowlist: independent of attendance module. When settings.module_ip_restriction
     * is off, no check. When a user enables login_ip_restriction_enabled, the client IP must match
     * the union of users.allowed_login_ips and settings.global_allowed_ips (global entries are
     * additional allowed addresses, e.g. office gateway).
     */
    private function validateLoginIp($user) {
        if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
            return array('allowed' => true, 'message' => '');
        }
        require_once dirname(__FILE__) . '/settings.php';
        $cfg = settings::findById(1);
        if (!$cfg || empty($cfg->module_ip_restriction)) {
            return array('allowed' => true, 'message' => '');
        }

        $accountStatus = (int)($user->accountStatus ?? 0);
        if (!in_array($accountStatus, array(1, 3), true)) {
            return array('allowed' => true, 'message' => '');
        }

        $restrictionEnabled = (int)($user->login_ip_restriction_enabled ?? 0);
        if ($restrictionEnabled !== 1) {
            return array('allowed' => true, 'message' => '');
        }

        $userIpsRaw = (string)($user->allowed_login_ips ?? '');
        $globalIpsRaw = (string)($cfg->global_allowed_ips ?? '');
        $allowedIps = array_values(array_unique(array_filter(array_map('trim', array_merge(
            explode(',', $userIpsRaw),
            explode(',', $globalIpsRaw)
        )))));
        if (empty($allowedIps)) {
            return array(
                'allowed' => false,
                'message' => 'IP restriction is enabled on this account, but no allowed IP is configured.'
            );
        }

        $exactAllowed = array();
        $cidrRules = array();
        foreach ($allowedIps as $allowedIp) {
            $parsed = $this->parseLoginAllowlistToken($allowedIp);
            if ($parsed === null) {
                continue;
            }
            if ($parsed['type'] === 'exact') {
                $exactAllowed[] = $parsed['value'];
            } else {
                $cidrRules[] = array('subnet' => $parsed['subnet'], 'len' => $parsed['len']);
            }
        }
        $exactAllowed = array_values(array_unique($exactAllowed));

        $currentIps = self::collectLoginIpCheckCandidates();
        $isAllowed = false;
        foreach ($currentIps as $candidateIp) {
            $normalizedCandidate = $this->normalizeIp($candidateIp);
            if ($normalizedCandidate === '') {
                continue;
            }
            if (in_array($normalizedCandidate, $exactAllowed, true)) {
                $isAllowed = true;
                break;
            }
            $candidateBin = @inet_pton($normalizedCandidate);
            if ($candidateBin === false) {
                continue;
            }
            foreach ($cidrRules as $rule) {
                if (self::loginIpMatchesCidr($candidateBin, $rule['subnet'], $rule['len'])) {
                    $isAllowed = true;
                    break 2;
                }
            }
        }

        if (!$isAllowed) {
            return array(
                'allowed' => false,
                'message' => 'Login not allowed from this IP address.'
            );
        }

        return array('allowed' => true, 'message' => '');
    }

    /**
     * Same IP sources as login allowlist (CF / proxy headers + REMOTE_ADDR).
     * @return string[]
     */
    public static function getLoginIpCheckCandidates() {
        return self::collectLoginIpCheckCandidates();
    }

    private static function collectLoginIpCheckCandidates() {
        $candidates = array();

        $serverKeys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        );

        foreach ($serverKeys as $key) {
            if (!isset($_SERVER[$key])) {
                continue;
            }
            $raw = trim((string)$_SERVER[$key]);
            if ($raw === '') {
                continue;
            }

            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = array_map('trim', explode(',', $raw));
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $candidates[] = $part;
                    }
                }
            } else {
                $candidates[] = $raw;
            }
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array{type:'exact',value:string}|array{type:'cidr',subnet:string,len:int}|null
     */
    private function parseLoginAllowlistToken($token) {
        $token = trim((string)$token);
        if ($token === '') {
            return null;
        }
        if (strpos($token, '/') === false) {
            $norm = $this->normalizeIp($token);
            return $norm !== '' ? array('type' => 'exact', 'value' => $norm) : null;
        }
        $parts = explode('/', $token, 2);
        $subnetStr = trim($parts[0]);
        $bitsStr = isset($parts[1]) ? trim($parts[1]) : '';
        if ($subnetStr === '' || $bitsStr === '' || !ctype_digit($bitsStr)) {
            return null;
        }
        $prefixLen = (int)$bitsStr;
        $normSubnet = $this->normalizeIp($subnetStr);
        if ($normSubnet === '') {
            return null;
        }
        $subnetBin = @inet_pton($normSubnet);
        if ($subnetBin === false) {
            return null;
        }
        $maxBits = strlen($subnetBin) * 8;
        if ($prefixLen < 0 || $prefixLen > $maxBits) {
            return null;
        }
        return array('type' => 'cidr', 'subnet' => $subnetBin, 'len' => $prefixLen);
    }

    /**
     * @param string $ipBinary    inet_pton result (4 or 16 bytes)
     * @param string $subnetBinary same length as $ipBinary
     */
    private static function loginIpMatchesCidr($ipBinary, $subnetBinary, $prefixLen) {
        $len = strlen($ipBinary);
        if ($len !== strlen($subnetBinary) || $len === 0) {
            return false;
        }
        $maxBits = $len * 8;
        if ($prefixLen < 0 || $prefixLen > $maxBits) {
            return false;
        }
        $fullBytes = intdiv($prefixLen, 8);
        if ($fullBytes > 0) {
            if (strncmp($ipBinary, $subnetBinary, $fullBytes) !== 0) {
                return false;
            }
        }
        $rem = $prefixLen % 8;
        if ($rem === 0) {
            return true;
        }
        $byteIdx = $fullBytes;
        if ($byteIdx >= $len) {
            return true;
        }
        $mask = (0xFF << (8 - $rem)) & 0xFF;
        return (ord($ipBinary[$byteIdx]) & $mask) === (ord($subnetBinary[$byteIdx]) & $mask);
    }

    private function normalizeIp($ip) {
        $ip = trim((string)$ip);
        if ($ip === '') {
            return '';
        }

        // Handle IPv4-mapped IPv6 (::ffff:1.2.3.4)
        if (stripos($ip, '::ffff:') === 0) {
            $mapped = substr($ip, 7);
            if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $mapped;
            }
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }

        return $ip;
    }
    
    /**
     * Get redirect URL based on account status and module configuration
     */
    private function getRedirectUrl($user) {
        if (!function_exists('comon_post_login_redirect_path')) {
            require_once __DIR__ . '/sidebar_navigation.php';
        }
        $settings = settings::findById(1);
        return comon_post_login_redirect_path($user, $settings);
    }
    
    /**
     * Check if account is locked due to too many failed attempts
     */
    private function isAccountLocked($email) {
        global $connect;
        $this->createLoginAttemptsTable();
        $ip = auth_client_ip();
        return auth_login_email_locked($email, $connect) || auth_login_ip_abused($ip, $connect);
    }
    
    /**
     * Increment failed login attempts
     */
    private function incrementFailedAttempts($email) {
        global $connect;
        
        $this->createLoginAttemptsTable();
        
        $sql = "INSERT INTO login_attempts (email, success, ip_address, user_agent) 
                VALUES (?, FALSE, ?, ?)";
        $stmt = $connect->prepare($sql);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt->bind_param("sss", $email, $ipAddress, $userAgent);
        $stmt->execute();
    }
    
    /**
     * Reset failed attempts on successful login
     */
    private function resetFailedAttempts($email) {
        self::clearFailedAttemptsForEmail($email);
    }
    
    /**
     * Log login activity
     */
    private function logLoginActivity($userId = null, $success = true, $email = null, $type = 'login') {
        global $connect;
        
        $this->createLoginAttemptsTable();
        
        $sql = "INSERT INTO login_attempts (user_id, email, success, type, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $connect->prepare($sql);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt->bind_param("isssss", $userId, $email, $success, $type, $ipAddress, $userAgent);
        $stmt->execute();
    }
    
    /**
     * Create login_attempts table if it doesn't exist
     */
    private function createLoginAttemptsTable() {
        global $connect;
        static $ready = false;
        if ($ready || !$connect) {
            return;
        }
        $exists = @$connect->query("SHOW TABLES LIKE 'login_attempts'");
        if ($exists && $exists->num_rows > 0) {
            $ready = true;
            return;
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            email VARCHAR(255) NULL,
            success BOOLEAN DEFAULT FALSE,
            type ENUM('login', 'logout', 'remember_me', 'failed') DEFAULT 'login',
            ip_address VARCHAR(45),
            user_agent TEXT,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_user_id (user_id),
            INDEX idx_attempt_time (attempt_time),
            INDEX idx_success (success)
        )";
        
        $connect->query($sql);
        $ready = true;
    }
    
    /**
     * Clean up old login attempts (older than 30 days)
     */
    public function cleanupOldLoginAttempts() {
        global $connect;
        
        $sql = "DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        return $connect->query($sql);
    }
    
    /**
     * Get user's active sessions count
     */
    public function getActiveSessionsCount($userId) {
        return $this->rememberMe->getActiveTokenCount($userId);
    }
    
    /**
     * Revoke all user sessions
     */
    public function revokeAllUserSessions($userId) {
        $ok = $this->rememberMe->deleteAllUserTokens($userId);
        if (function_exists('auth_trusted_device_revoke_user')) {
            auth_trusted_device_revoke_user($userId);
        }
        return $ok;
    }
}
?> 