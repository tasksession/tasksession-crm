<?php
/*
================================================================================
     Task Session – Project Management System
     File    : verify-2fa.php
     Purpose : TOTP / recovery-code challenge after password or Google login
================================================================================
*/
ob_start();
require_once __DIR__ . '/includes/lib-initialize.php';
require_once __DIR__ . '/includes/auth_helper.php';

$settings = isset($dash_settings) ? $dash_settings : settings::findById(1);
$title = ($lang['Two-factor authentication'] ?? 'Two-factor authentication') . ' | ' . $syatem_title;
$is_login_page = true;

if ($session->isLoggedIn()) {
    $logged = User::findById((int) $session->userId);
    redirectTo($url . comon_post_login_redirect_path($logged, $settings));
}

if (!function_exists('comon_post_login_redirect_path')) {
    require_once __DIR__ . '/includes/sidebar_navigation.php';
}

if (!auth_mfa_is_pending()) {
    redirectTo($url);
}
if (!empty($_SESSION['mfa_setup_required'])) {
    redirectTo($url . 'setup-2fa.php');
}

$authHelper = new AuthHelper($session);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim((string) ($_POST['code'] ?? ''));
    $trustDevice = !empty($_POST['trust_device']);
    $result = $authHelper->completePendingMfa($code, $trustDevice);
    if (!empty($result['success'])) {
        redirectTo($url . ($result['redirect'] ?? ''));
    }
    $message = (string) ($result['message'] ?? 'Invalid verification code.');
}

$pendingUser = User::findById((int) ($_SESSION['mfa_pending_user_id'] ?? 0));
$codeLabel = $lang['Enter the 6-digit code'] ?? 'Enter the 6-digit code';
$recoveryLabel = $lang['Enter a recovery code'] ?? 'Enter a recovery code';
$useRecovery = $lang['Use a recovery code instead'] ?? 'Use a recovery code instead';
$useAuthenticator = $lang['Use an authenticator code instead'] ?? 'Use an authenticator code instead';

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
				<form class="login-form mfa-verify-form w-100" id="mfaVerifyForm" action="" method="post" autocomplete="off">
					<div class="mfa-heading-icon" aria-hidden="true"><?php echo ts_icon_inline('lock'); ?></div>
					<h3 class="card-title mb-2 font-size-24"><?php echo htmlspecialchars($lang['Authenticator code'] ?? 'Authenticator code'); ?></h3>
					<p class="mfa-verify-lead"><?php echo htmlspecialchars($lang['Enter the 6-digit code from Google Authenticator or Authy, or a recovery code.'] ?? 'Enter the 6-digit code from Google Authenticator or Authy, or use one of your recovery codes.'); ?></p>
					<?php if ($message !== ''): ?>
					<div class="row">
						<div class="col-sm-12">
							<div class="alert alert-danger static-alerts"><span style="display:block;"><?php echo htmlspecialchars($message); ?></span></div>
						</div>
					</div>
					<?php endif; ?>
					<div class="form-group mb-0">
						<div class="input-icon">
							<label class="control-label" id="mfaCodeLabel"><?php echo htmlspecialchars($codeLabel); ?></label>
							<div class="eyes-row mfa-code-row">
								<span class="mfa-code-icon" aria-hidden="true"><?php echo ts_icon_inline('mobile'); ?></span>
								<input class="form-control" type="text" name="code" id="mfaCodeInput" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" maxlength="32" autofocus required />
							</div>
							<?php
							if ($pendingUser && function_exists('auth_trusted_device_card_html')) {
							    echo auth_trusted_device_card_html($pendingUser);
							}
							?>
							<button type="submit" class="btn primary-btn mfa-submit-btn">
								<span><?php echo htmlspecialchars($lang['Verify and continue'] ?? 'Verify and continue'); ?></span>
								<?php echo ts_icon_inline('arrow-right'); ?>
							</button>
							<div class="mfa-or-row" aria-hidden="true">
								<span class="mfa-or-line"></span>
								<span class="mfa-or-text"><?php echo htmlspecialchars($lang['OR'] ?? 'OR'); ?></span>
								<span class="mfa-or-line"></span>
							</div>
							<a href="#" class="mfa-recovery-link" id="mfaRecoveryToggle"><?php echo htmlspecialchars($useRecovery); ?></a>
						</div>
					</div>
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
<script>
(function () {
	var toggle = document.getElementById('mfaRecoveryToggle');
	var input = document.getElementById('mfaCodeInput');
	var label = document.getElementById('mfaCodeLabel');
	var trust = document.getElementById('mfaTrustCard');
	if (!toggle || !input || !label) {
		return;
	}
	var totpLabel = <?php echo json_encode($codeLabel); ?>;
	var recoveryLabel = <?php echo json_encode($recoveryLabel); ?>;
	var toRecovery = <?php echo json_encode($useRecovery); ?>;
	var toTotp = <?php echo json_encode($useAuthenticator); ?>;
	var recovery = false;
	toggle.addEventListener('click', function (e) {
		e.preventDefault();
		recovery = !recovery;
		label.textContent = recovery ? recoveryLabel : totpLabel;
		input.placeholder = recovery ? '' : '000000';
		input.setAttribute('inputmode', recovery ? 'text' : 'numeric');
		input.value = '';
		input.focus();
		toggle.textContent = recovery ? toTotp : toRecovery;
		if (trust) {
			trust.style.display = recovery ? 'none' : '';
			var box = trust.querySelector('input[name="trust_device"]');
			if (box && recovery) {
				box.checked = false;
			}
		}
	});
})();
</script>
<?php include __DIR__ . '/templates/frontend-footer.php'; ?>
