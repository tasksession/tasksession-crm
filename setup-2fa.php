<?php
/*
================================================================================
     Task Session – Project Management System
     File    : setup-2fa.php
     Purpose : Mandatory TOTP enrollment before a full admin/staff session
================================================================================
*/
ob_start();
require_once __DIR__ . '/includes/lib-initialize.php';
require_once __DIR__ . '/includes/auth_helper.php';

$settings = isset($dash_settings) ? $dash_settings : settings::findById(1);
$title = ($lang['Set up authenticator'] ?? 'Set up authenticator') . ' | ' . $syatem_title;
$is_login_page = true;

if (!function_exists('comon_post_login_redirect_path')) {
    require_once __DIR__ . '/includes/sidebar_navigation.php';
}

if ($session->isLoggedIn()) {
    redirectTo($url . 'authenticator');
}

if (!auth_mfa_is_pending() || empty($_SESSION['mfa_setup_required'])) {
    if (auth_mfa_is_pending()) {
        redirectTo($url . 'verify-2fa.php');
    }
    redirectTo($url);
}

$user = User::findById((int) $_SESSION['mfa_pending_user_id']);
if (!$user) {
    auth_mfa_clear_pending();
    redirectTo($url);
}

$authHelper = new AuthHelper($session);
$message = '';
$recoveryCodes = null;
$showSecret = '';
$qrSvg = '';

if (!auth_totp_crypto_ready()) {
    $message = $lang['Authenticator encryption is not configured.'] ?? 'Authenticator encryption is not configured on this server. Contact an administrator.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_code'])) {
    $pendingSecret = (string) ($_SESSION['totp_setup_secret'] ?? '');
    $check = totp_verify($pendingSecret, (string) ($_POST['confirm_code'] ?? ''), 10);
    if ($pendingSecret === '' || empty($check['ok'])) {
        $message = $lang['Invalid verification code.'] ?? 'Invalid verification code.';
    } else {
        $recoveryCodes = totp_recovery_codes_plain(8);
        if (!auth_store_totp_enabled((int) $user->id, $pendingSecret, $recoveryCodes)) {
            $message = $lang['Could not save authenticator.'] ?? 'Could not save authenticator settings. Please try again.';
            $recoveryCodes = null;
        } else {
            unset($_SESSION['totp_setup_secret']);
            $_SESSION['mfa_setup_required'] = 0;
            $user = User::findById((int) $user->id);
            $remember = !empty($_SESSION['mfa_pending_remember']);
            $provider = (string) ($_SESSION['mfa_pending_provider'] ?? 'local');
            if (!empty($_POST['trust_device']) && function_exists('auth_trusted_device_issue')) {
                auth_trusted_device_issue($user);
            }
            $result = $authHelper->completeFullLogin($user, $remember, $provider);
            if (empty($result['success'])) {
                $message = $result['message'] ?? 'Login failed.';
                $recoveryCodes = null;
            } else {
                $_SESSION['totp_recovery_once'] = $recoveryCodes;
                redirectTo($url . 'authenticator?enrolled=1');
            }
        }
    }
}

if ($message === '' && empty($recoveryCodes) && auth_totp_crypto_ready()) {
    if (empty($_SESSION['totp_setup_secret'])) {
        $_SESSION['totp_setup_secret'] = totp_random_secret();
    }
    $showSecret = (string) $_SESSION['totp_setup_secret'];
    $uri = totp_otpauth_uri($showSecret, (string) $user->email, auth_totp_issuer());
    $qrSvg = totp_qr_svg($uri);
}

include __DIR__ . '/templates/frontend-header.php';
?>
<div class="login-area mfa-login-area">
	<div class="content">
		<div class="row">
			<div class="col-12 col-lg-7 login-col">
				<div class="wrapper-400">
					<div class="logo">
						<a class="signLogo" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">
						<img src="<?php echo htmlspecialchars($login_brand_logo_url ?? crm_default_asset_image_url('assets/images/svg/dark-logo.svg'), ENT_QUOTES, 'UTF-8'); ?>" alt="logo" />
						</a>
					</div>
				</div>
				<div class="wrapper-400 align-center mfa-align">
				<form class="login-form mfa-setup-form w-100" action="" method="post" autocomplete="off">
					<div class="mfa-heading-icon" aria-hidden="true"><?php echo ts_icon_inline('lock'); ?></div>
					<h3 class="card-title mb-2 font-size-24"><?php echo htmlspecialchars($lang['Set up authenticator'] ?? 'Set up authenticator'); ?></h3>
					<p class="mfa-verify-lead"><?php echo htmlspecialchars($lang['Scan the QR code with Google Authenticator or Authy, then enter the 6-digit code shown in the app.'] ?? 'Scan the QR code with Google Authenticator or Authy, then enter the 6-digit code shown in the app.'); ?></p>
					<?php if ($message !== ''): ?>
					<div class="alert alert-danger static-alerts"><span><?php echo htmlspecialchars($message); ?></span></div>
					<?php endif; ?>
					<?php if ($qrSvg !== ''): ?>
					<div class="form-group text-start">
						<?php echo totp_qr_frame($qrSvg); ?>
					</div>
					<div class="form-group">
						<div class="input-icon">
							<label class="control-label"><?php echo htmlspecialchars($lang['Or enter this setup key manually'] ?? 'Or enter this setup key manually'); ?></label>
							<input class="form-control" type="text" readonly value="<?php echo htmlspecialchars(function_exists('totp_secret_display') ? totp_secret_display($showSecret) : $showSecret); ?>" onclick="this.select();" spellcheck="false" aria-label="<?php echo htmlspecialchars($lang['Or enter this setup key manually'] ?? 'Or enter this setup key manually'); ?>" style="letter-spacing: 0.06em; font-weight: 600; text-align: center;">
							<small class="form-text text-muted"><?php echo htmlspecialchars($lang["Can't scan the QR code? Add this setup key manually in your authenticator app."] ?? "Can't scan the QR code? Add this setup key manually in your authenticator app."); ?></small>
						</div>
					</div>
					<div class="form-group mb-0">
						<div class="input-icon">
							<label class="control-label"><?php echo htmlspecialchars($lang['Enter the 6-digit code'] ?? 'Enter the 6-digit code'); ?></label>
							<div class="eyes-row mfa-code-row">
								<span class="mfa-code-icon" aria-hidden="true"><?php echo ts_icon_inline('mobile'); ?></span>
								<input class="form-control" type="text" name="confirm_code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" placeholder="000000" required />
							</div>
							<?php echo function_exists('auth_trusted_device_card_html') ? auth_trusted_device_card_html($user) : ''; ?>
							<button type="submit" class="btn primary-btn mfa-submit-btn">
								<span><?php echo htmlspecialchars($lang['Confirm and continue'] ?? 'Confirm and continue'); ?></span>
								<?php echo ts_icon_inline('arrow-right'); ?>
							</button>
						</div>
					</div>
					<?php endif; ?>
					<div class="mfa-footer">
						<a class="mfa-back-link" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>logout.php">
							<?php echo ts_icon_inline('arrow-left'); ?>
							<span><?php echo htmlspecialchars($lang['Back to Login'] ?? 'Back to login'); ?></span>
						</a>
						<span class="mfa-protected">
							<?php echo ts_icon_inline('shield'); ?>
							<span><?php echo htmlspecialchars($lang['Your account is protected'] ?? 'Your account is protected'); ?></span>
						</span>
					</div>
				</form>
				</div>
			</div>
			<div class="col-12 col-lg-5 login-right">
				<img src="<?php echo htmlspecialchars($login_page_image_url ?? crm_default_asset_image_url('assets/images/login.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="" />
			</div>
		</div>
	</div>
</div>
<?php include __DIR__ . '/templates/frontend-footer.php'; ?>
