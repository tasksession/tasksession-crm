<?php
/**
 * Logged-in TOTP enroll / confirm / disable / recovery regenerate.
 */
define('CRM_LIGHTWEIGHT_INIT', true);

if (ob_get_level() === 0) {
    ob_start();
}

require_once dirname(__DIR__) . '/includes/lib-initialize.php';
require_once dirname(__DIR__) . '/includes/auth_helper.php';

if (function_exists('auth_session_ensure_writable')) {
    auth_session_ensure_writable();
} elseif (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

function authenticator_totp_exit(array $payload, $code = 200)
{
    http_response_code((int) $code);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($session) || !$session->isLoggedIn()) {
    authenticator_totp_exit(array('success' => false, 'message' => 'Authentication required'), 401);
}

$user = User::findById((int) $session->userId);
if (!$user) {
    authenticator_totp_exit(array('success' => false, 'message' => 'Authentication required'), 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    authenticator_totp_exit(array('success' => false, 'message' => 'Invalid request'), 405);
}

$csrfPosted = (string) ($_POST['csrf_token'] ?? '');
if ($csrfPosted === '') {
    $csrfPosted = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
}
if (!function_exists('validate_csrf_token') || !validate_csrf_token($csrfPosted)) {
    authenticator_totp_exit(array('success' => false, 'message' => 'Invalid request. Please refresh and try again.'), 403);
}

$action = trim((string) ($_POST['action'] ?? ''));
$canDisable = function_exists('auth_totp_user_can_disable') && auth_totp_user_can_disable($user);

if ($action === 'start') {
    if (!auth_totp_feature_enabled()) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Authenticator is not enabled.'), 403);
    }
    if (!empty($user->totp_enabled)) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Authenticator is already enabled.'));
    }
    if (!auth_totp_crypto_ready()) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Authenticator encryption is not configured.'));
    }
    if (empty($_SESSION['totp_setup_secret'])) {
        $_SESSION['totp_setup_secret'] = totp_random_secret();
    }
    $secret = (string) $_SESSION['totp_setup_secret'];
    $uri = totp_otpauth_uri($secret, (string) $user->email, auth_totp_issuer());
    authenticator_totp_exit(array(
        'success' => true,
        'secret' => $secret,
        'qr_svg' => totp_qr_svg($uri),
    ));
}

if ($action === 'confirm') {
    if (!auth_totp_feature_enabled()) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Authenticator is not enabled.'), 403);
    }
    if (!empty($user->totp_enabled)) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Authenticator is already enabled.'));
    }
    $pending = (string) ($_SESSION['totp_setup_secret'] ?? '');
    if ($pending === '') {
        authenticator_totp_exit(array('success' => false, 'message' => 'Scan the QR code first, then enter the 6-digit code.'));
    }
    $check = totp_verify($pending, (string) ($_POST['code'] ?? ''), 10);
    if (empty($check['ok'])) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Invalid verification code.'));
    }
    $codes = totp_recovery_codes_plain(8);
    if (!auth_store_totp_enabled((int) $user->id, $pending, $codes)) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Could not save authenticator settings.'));
    }
    unset($_SESSION['totp_setup_secret']);
    auth_totp_mark_timestep((int) $user->id, (int) $check['slice']);
    authenticator_totp_exit(array(
        'success' => true,
        'recovery_codes' => $codes,
        'message' => 'Authenticator enabled. Save these recovery codes now — they will not be shown again.',
    ));
}

if ($action === 'regenerate') {
    if (empty($user->totp_enabled)) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Authenticator is not enabled.'));
    }
    $claimed = auth_totp_verify_and_claim((int) $user->id, (string) ($_POST['code'] ?? ''), 10);
    if (empty($claimed['ok'])) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Invalid verification code.'));
    }
    $codes = totp_recovery_codes_plain(8);
    $hashes = array();
    foreach ($codes as $code) {
        $hashes[] = array('h' => totp_hash_recovery_code($code));
    }
    $json = json_encode($hashes);
    global $connect;
    $stmt = $connect->prepare('UPDATE users SET totp_backup_codes = ? WHERE id = ? LIMIT 1');
    if (!$stmt) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Could not save recovery codes.'));
    }
    $uid = (int) $user->id;
    $stmt->bind_param('si', $json, $uid);
    $stmt->execute();
    $stmt->close();
    authenticator_totp_exit(array(
        'success' => true,
        'recovery_codes' => $codes,
        'message' => 'New recovery codes generated. Save them now — they will not be shown again.',
    ));
}

if ($action === 'disable') {
    if (!$canDisable) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Your role cannot disable authenticator.'), 403);
    }
    if (empty($user->totp_enabled)) {
        authenticator_totp_exit(array('success' => true));
    }
    $claimed = auth_totp_verify_and_claim((int) $user->id, (string) ($_POST['code'] ?? ''), 10);
    if (empty($claimed['ok'])) {
        authenticator_totp_exit(array('success' => false, 'message' => 'Invalid verification code.'));
    }
    auth_totp_disable_user((int) $user->id);
    authenticator_totp_exit(array('success' => true, 'message' => 'Authenticator disabled.'));
}

authenticator_totp_exit(array('success' => false, 'message' => 'Invalid action'), 400);
