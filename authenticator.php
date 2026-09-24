<?php
/*
 * User Settings — Google Authenticator / TOTP
 */
ob_start();
require_once('includes/lib-initialize.php');

if (!($session->isLoggedIn())) {
    redirectTo($url . 'index.php');
}

$id = $session->userId;
$user = User::findById((int) $id);
$username = $user ? $user->firstName : '';
$email = $user ? $user->email : '';
$account_stat = $user ? $user->status : '';
$settings = settings::findById(1);

$title = ($lang['Authenticator'] ?? 'Authenticator') . ' | ' . $syatem_title;

$userSettingsProfilePath = 'admin/profile?user_id=' . (int) $session->userId;
if (isset($_SESSION['accountStatus'])) {
    if ((int) $_SESSION['accountStatus'] === 2) {
        $userSettingsProfilePath = 'client/edit?editprofile=' . (int) $session->userId;
    } elseif ((int) $_SESSION['accountStatus'] === 3) {
        $userSettingsProfilePath = 'staff/edit?editprofile=' . (int) $session->userId;
    }
}

$enabled = $user && !empty($user->totp_enabled);
$featureOn = function_exists('auth_totp_feature_enabled') && auth_totp_feature_enabled();
if (!$enabled && !$featureOn) {
    redirectTo($url . 'settings');
}
$accountStatus = (int) ($user->accountStatus ?? 0);
$mandatory = function_exists('auth_totp_user_is_required') && auth_totp_user_is_required($user);
$canDisable = $enabled && function_exists('auth_totp_user_can_disable') && auth_totp_user_can_disable($user);
$cryptoReady = function_exists('auth_totp_crypto_ready') && auth_totp_crypto_ready();
$totpFormError = '';
$csrfToken = function_exists('generate_csrf_token') ? generate_csrf_token() : '';

if ($enabled && $user && $_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['revoke_trusted_device_id']) || isset($_POST['revoke_all_trusted_devices']))) {
    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');
    if ($csrfToken !== '' && function_exists('validate_csrf_token') && !validate_csrf_token($postedCsrf)) {
        $totpFormError = $lang['Invalid request. Please refresh and try again.'] ?? 'Invalid request. Please refresh and try again.';
    } elseif (isset($_POST['revoke_all_trusted_devices'])) {
        if (function_exists('auth_trusted_device_revoke_user')) {
            auth_trusted_device_revoke_user((int) $user->id);
        }
        redirectTo(rtrim((string) $url, '/') . '/authenticator?device=revoked-all');
    } else {
        $deviceId = (int) ($_POST['revoke_trusted_device_id'] ?? 0);
        if ($deviceId > 0 && function_exists('auth_trusted_device_revoke_id')) {
            auth_trusted_device_revoke_id((int) $user->id, $deviceId);
        }
        redirectTo(rtrim((string) $url, '/') . '/authenticator?device=revoked');
    }
}

if (!$enabled && $featureOn && $cryptoReady && $user && $_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['totp_confirm']) || isset($_POST['totp_code']))) {
    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');
    if ($csrfToken !== '' && function_exists('validate_csrf_token') && !validate_csrf_token($postedCsrf)) {
        $totpFormError = $lang['Invalid request. Please refresh and try again.'] ?? 'Invalid request. Please refresh and try again.';
    } else {
        $pending = (string) ($_SESSION['totp_setup_secret'] ?? '');
        $code = (string) ($_POST['totp_code'] ?? '');
        $clientEpoch = (int) ($_POST['client_epoch'] ?? 0);
        if ($pending === '') {
            $totpFormError = $lang['Scan the QR code on this page, then enter the 6-digit code.'] ?? 'Scan the QR code on this page, then enter the 6-digit code.';
        } else {
            $check = totp_verify($pending, $code, 10);
            if (empty($check['ok'])) {
                $skew = ($clientEpoch > 1000000000) ? abs(time() - $clientEpoch) : 0;
                if ($skew > 90) {
                    $mins = max(1, (int) round($skew / 60));
                    $totpFormError = 'Invalid verification code. This computer and your phone clocks differ by about ' . $mins . ' minutes. Turn on automatic date and time on both, delete the old authenticator entry, scan this page’s QR again, then enter the code that is showing now.';
                } else {
                    $totpFormError = $lang['Invalid verification code. Delete any old CRM entry in Google Authenticator, scan the QR on this page, then enter the code that is showing right now.'] ?? 'Invalid verification code. Delete any old CRM entry in Google Authenticator, scan the QR on this page, then enter the code that is showing right now.';
                }
            } else {
                $codes = totp_recovery_codes_plain(8);
                if (!auth_store_totp_enabled((int) $user->id, $pending, $codes)) {
                    $totpFormError = $lang['Could not save authenticator settings.'] ?? 'Could not save authenticator settings.';
                } else {
                    unset($_SESSION['totp_setup_secret']);
                    auth_totp_mark_timestep((int) $user->id, (int) $check['slice']);
                    $_SESSION['totp_recovery_once'] = $codes;
                    redirectTo(rtrim((string) $url, '/') . '/authenticator?enrolled=1');
                }
            }
        }
    }
}

$showSecret = '';
$qrSvg = '';
if (!$enabled && $featureOn && $cryptoReady && $user) {
    if (empty($_SESSION['totp_setup_secret'])) {
        $_SESSION['totp_setup_secret'] = totp_random_secret();
    }
    $showSecret = (string) $_SESSION['totp_setup_secret'];
    $uri = totp_otpauth_uri($showSecret, (string) $user->email, auth_totp_issuer());
    $qrSvg = totp_qr_svg($uri);
}
$recoveryOnce = array();
if (!empty($_SESSION['totp_recovery_once']) && is_array($_SESSION['totp_recovery_once'])) {
    $recoveryOnce = $_SESSION['totp_recovery_once'];
    unset($_SESSION['totp_recovery_once']);
}
$justEnrolled = isset($_GET['enrolled']) && $_GET['enrolled'] === '1';
$deviceRevoked = isset($_GET['device']) ? (string) $_GET['device'] : '';

/** @var array{type:string,msg:string}|null assets/js/toast.js reads window.__toastFlash */
$toast_flash = null;
if ($totpFormError !== '') {
    $toast_flash = array('type' => 'error', 'msg' => $totpFormError, 'duration' => 6000);
} elseif ($deviceRevoked === 'revoked-all') {
    $toast_flash = array(
        'type' => 'success',
        'msg' => $lang['All trusted devices were removed.'] ?? 'All trusted devices were removed.',
    );
} elseif ($deviceRevoked === 'revoked') {
    $toast_flash = array(
        'type' => 'success',
        'msg' => $lang['Trusted device removed.'] ?? 'Trusted device removed.',
    );
} elseif ($justEnrolled) {
    $toast_flash = array(
        'type' => 'success',
        'msg' => $recoveryOnce
            ? ($lang['Authenticator is enabled. Save your recovery codes now.'] ?? 'Authenticator is enabled. Save your recovery codes now.')
            : ($lang['Authenticator is enabled on your account.'] ?? 'Authenticator is enabled on your account.'),
        'duration' => 4000,
    );
} elseif (!$enabled && !$cryptoReady) {
    $toast_flash = array(
        'type' => 'error',
        'msg' => $lang['Authenticator encryption is not configured.'] ?? 'Authenticator encryption is not configured on this server. Contact an administrator.',
        'duration' => 6000,
    );
}

include('templates/header.php');
?>
<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include('templates/sidebar.php'); ?>
            <div class="page-content" style="padding-bottom:0;">
                <?php include('templates/top-header.php'); ?>
                <div class="row system-wrap vh-100-1">
                    <?php include('templates/user-settings-nav.php'); ?>
                    <div class="col-md-9 ss-right">
                        <div class="system-settings-container">
                            <div class="settings-header">
                                <h2 class="page-title"><?php echo htmlspecialchars($lang['Authenticator'] ?? 'Authenticator'); ?></h2>
                            </div>
                            <div class="settings-grid">
                                <div class="settings-main">
                                    <div class="form-group">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($lang['Google Authenticator'] ?? 'Google Authenticator'); ?></h4>
                                                <p><?php echo htmlspecialchars($lang['Use Google Authenticator or Authy for a unique code on this account.'] ?? 'Use Google Authenticator or Authy. Each account has its own secret — never share it.'); ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="license-details">
                                                    <div class="detail-row align-items-start">
                                                        <div class="detail-label">
                                                            <div>
                                                                <?php echo htmlspecialchars($lang['Authenticator'] ?? 'Authenticator'); ?>
                                                                <?php if ($enabled && !$canDisable): ?>
                                                                <small class="d-block text-muted mt-2" style="font-weight: 500;"><?php echo htmlspecialchars($lang['Required for your role. Turn off Require for admin or staff in Google Authenticator settings to allow disable.'] ?? 'Required for your role. An administrator can turn off this requirement in Google Authenticator settings.'); ?></small>
                                                                <?php elseif ($enabled && $canDisable): ?>
                                                                <small class="d-block text-muted mt-2" style="font-weight: 500;"><?php echo htmlspecialchars($lang['Enter your current authenticator code, then turn this off to disable.'] ?? 'Enter your current authenticator code on the right, then turn this off to disable.'); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="detail-value">
                                                            <div class="checkbox-wrapper-6">
                                                                <input class="tgl tgl-light" id="totpEnabledToggle" type="checkbox"
                                                                    <?php echo $enabled ? 'checked' : ''; ?>
                                                                    <?php echo ($enabled && !$canDisable) ? 'disabled' : ''; ?>
                                                                    <?php echo !$enabled ? 'disabled' : ''; ?>
                                                                    data-can-disable="<?php echo $canDisable ? '1' : '0'; ?>">
                                                                <label class="tgl-btn" for="totpEnabledToggle"></label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="detail-row">
                                                        <div class="detail-label"><?php echo htmlspecialchars($lang['Status'] ?? 'Status'); ?></div>
                                                        <div class="detail-value">
                                                            <?php if ($enabled): ?>
                                                            <span class="badge completed"><i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars($lang['Enabled'] ?? 'Enabled'); ?></span>
                                                            <?php else: ?>
                                                            <span class="badge red-badge"><i class="fas fa-exclamation-triangle me-1"></i><?php echo htmlspecialchars($lang['Not enabled'] ?? 'Not enabled'); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($mandatory): ?>
                                                    <div class="detail-row">
                                                        <div class="detail-label"><?php echo htmlspecialchars($lang['Requirement'] ?? 'Requirement'); ?></div>
                                                        <div class="detail-value text-muted"><?php echo htmlspecialchars($lang['Required for your role'] ?? 'Required for your role'); ?></div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div id="totpRecoveryMount" class="<?php echo $recoveryOnce ? 'mt-4' : 'd-none'; ?>">
                                                    <?php if ($recoveryOnce): ?>
                                                    <div class="border-top pt-4">
                                                        <label class="form-label"><?php echo htmlspecialchars($lang['Recovery codes'] ?? 'Recovery codes'); ?></label>
                                                        <p class="text-muted mb-3"><?php echo htmlspecialchars($lang['Each code can be used only once. They will not be shown again.'] ?? 'Each code can be used only once. Save them now — they will not be shown again.'); ?></p>
                                                        <div class="row">
                                                            <?php foreach ($recoveryOnce as $rc): ?>
                                                            <div class="col-6 mb-2">
                                                                <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars($rc); ?>" onclick="this.select();">
                                                            </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div id="totpSetupBox" class="<?php echo $enabled ? 'd-none' : 'mt-4'; ?>">
                                                    <form id="totpEnrollForm" method="post" action="">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                        <input type="hidden" name="client_epoch" id="totpClientEpoch" value="">
                                                        <div class="mb-3 setup-steps">
                                                            <li><?php echo htmlspecialchars($lang['Delete any previous CRM entry in your authenticator app.'] ?? 'Delete any previous CRM entry in your authenticator app.'); ?></li>
                                                            <li><?php echo htmlspecialchars($lang['Scan this QR code, or type the key below.'] ?? 'Scan this QR code, or type the key below.'); ?></li>
                                                            <li><?php echo htmlspecialchars($lang['Enter the 6-digit code that is showing now.'] ?? 'Enter the 6-digit code that is showing now.'); ?></li>
                                                        </div>
                                                        <div id="totpQrWrap" class="mb-3 text-center<?php echo $qrSvg === '' ? ' d-none' : ''; ?>"><?php echo function_exists('totp_qr_frame') ? totp_qr_frame($qrSvg) : $qrSvg; ?></div>
                                                        <?php if ($showSecret !== ''): ?>
                                                        <div class="form-group">
                                                            <label class="form-label"><?php echo htmlspecialchars($lang['Or enter this setup key manually'] ?? 'Or enter this setup key manually'); ?></label>
                                                            <input type="text" class="form-control" readonly value="<?php echo htmlspecialchars(totp_secret_display($showSecret)); ?>" onclick="this.select();" spellcheck="false" style="letter-spacing: 0.06em; font-weight: 600; text-align: center;">
                                                            <small class="form-text text-muted"><?php echo htmlspecialchars($lang["Can't scan the QR code? Add this setup key manually in your authenticator app."] ?? "Can't scan the QR code? Add this setup key manually in your authenticator app."); ?></small>
                                                        </div>
                                                        <?php endif; ?>
                                                        <div class="form-group mb-0">
                                                            <label class="form-label" for="totpCode"><?php echo htmlspecialchars($lang['Enter the 6-digit code'] ?? 'Enter the 6-digit code'); ?></label>
                                                            <input type="text" class="form-control" id="totpCode" name="totp_code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" placeholder="000000" <?php echo $enabled ? 'disabled' : ''; ?> required>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php
                                    $trustedDevices = ($enabled && function_exists('auth_trusted_device_list'))
                                        ? auth_trusted_device_list((int) $user->id)
                                        : array();
                                    ?>
                                    <?php if ($enabled): ?>
                                    <div class="form-group">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($lang['Trusted devices'] ?? 'Trusted devices'); ?></h4>
                                                <p><?php echo htmlspecialchars($lang['These devices can skip the authenticator code for a limited time.'] ?? 'These devices can skip the authenticator code for a limited time.'); ?></p>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!$trustedDevices): ?>
                                                <p class="text-muted mb-0"><?php echo htmlspecialchars($lang['No trusted devices.'] ?? 'No trusted devices.'); ?></p>
                                                <?php else: ?>
                                                <form method="post" action="" class="mb-0">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                                                    <div class="license-details form-group mb-0">
                                                        <?php foreach ($trustedDevices as $device): ?>
                                                        <?php
                                                            $ua = trim((string) ($device['user_agent'] ?? ''));
                                                            $label = function_exists('auth_trusted_device_label')
                                                                ? auth_trusted_device_label($ua)
                                                                : $ua;
                                                            if ($label === '') {
                                                                $label = $lang['This browser'] ?? 'This browser';
                                                            }
                                                            $iconName = function_exists('auth_trusted_device_icon')
                                                                ? auth_trusted_device_icon($ua)
                                                                : 'desktop';
                                                            $whenRaw = (string) ($device['last_used_at'] ?: $device['created_at']);
                                                            $whenTs = strtotime($whenRaw);
                                                            $whenLabel = $whenTs ? date('M j, Y', $whenTs) : $whenRaw;
                                                            $expiresTs = strtotime((string) ($device['expires_at'] ?? ''));
                                                            $daysLeft = function_exists('auth_trusted_device_days_left')
                                                                ? auth_trusted_device_days_left($device['expires_at'] ?? '')
                                                                : 0;
                                                            $meta = $whenLabel;
                                                            if ($expiresTs) {
                                                                $until = str_replace('%s', date('M j, Y', $expiresTs), $lang['Trusted until %s'] ?? 'Trusted until %s');
                                                                $daysText = str_replace('%s', (string) $daysLeft, $lang['(%s days left)'] ?? '(%s days left)');
                                                                $meta = $whenLabel . ' - ' . $until . ' ' . $daysText;
                                                            }
                                                        ?>
                                                        <div class="detail-row">
                                                            <div class="detail-label">
                                                                <div class="d-flex col-gap-10" style="min-width:0;width:100%;">
                                                                    <span class="trusted-device-icon" aria-hidden="true"><?php echo function_exists('ts_icon') ? ts_icon($iconName) : ''; ?></span>
                                                                    <div style="min-width:0;">
                                                                        <span class="trusted-device-name" title="<?php echo htmlspecialchars($ua); ?>"><?php echo htmlspecialchars($label); ?></span>
                                                                        <small class="d-block text-muted mt-1" style="font-weight: 500;"><?php echo htmlspecialchars($meta); ?></small>
                                                                        <?php if (!empty($device['is_current'])): ?>
                                                                        <span class="badge completed trusted-device-badge mt-2"><?php echo htmlspecialchars($lang['This browser'] ?? 'This browser'); ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="detail-value text-right">
                                                                <button type="submit" name="revoke_trusted_device_id" value="<?php echo (int) $device['id']; ?>" class="btn outline-btn"><?php echo htmlspecialchars($lang['Revoke'] ?? 'Revoke'); ?></button>
                                                            </div>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <button type="submit" name="revoke_all_trusted_devices" value="1" class="btn outline-btn trusted-devices-revoke-all mt-3"><?php echo htmlspecialchars($lang['Revoke all trusted devices'] ?? 'Revoke all trusted devices'); ?></button>
                                                    <small class="d-block text-muted mt-2" style="font-weight: 500;"><?php echo htmlspecialchars($lang['Remove all trusted devices and require the authenticator code on all devices.'] ?? 'Remove all trusted devices and require the authenticator code on all devices.'); ?></small>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="settings-sidebar">
                                    <div class="form-group mb-4">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4><?php echo htmlspecialchars($lang['Actions'] ?? 'Actions'); ?></h4>
                                                <p><?php echo htmlspecialchars($lang['Manage authenticator for this account'] ?? 'Manage authenticator for this account'); ?></p>
                                            </div>
                                            <div class="card-body">
                                                <?php if (!$enabled): ?>
                                                <button type="submit" class="btn primary-btn w-100 mb-3" name="totp_confirm" value="1" form="totpEnrollForm" id="totpConfirmBtn"><?php echo htmlspecialchars($lang['Confirm and enable'] ?? 'Confirm and enable'); ?></button>
                                                <div class="settings-info">
                                                    <div class="info-item">
                                                        <span><?php echo htmlspecialchars($lang['Scan the QR, then confirm with the 6-digit code.'] ?? 'Scan the QR, then confirm with the 6-digit code.'); ?></span>
                                                    </div>
                                                </div>
                                                <?php else: ?>
                                                <div class="form-group">
                                                    <label class="form-label" for="totpManageCode"><?php echo htmlspecialchars($lang['Current authenticator code'] ?? 'Current authenticator code'); ?></label>
                                                    <input type="text" class="form-control" id="totpManageCode" inputmode="numeric" autocomplete="one-time-code">
                                                </div>
                                                <button type="button" class="btn primary-btn w-100 mb-3" id="totpRegenBtn"><?php echo htmlspecialchars($lang['Generate new recovery codes'] ?? 'Generate new recovery codes'); ?></button>
                                                <div class="settings-info">
                                                    <div class="info-item">
                                                        <span><?php echo htmlspecialchars($canDisable
                                                            ? ($lang['Enter a current authenticator code to generate new recovery codes or disable.'] ?? 'Enter a current authenticator code to generate new recovery codes or to disable.')
                                                            : ($lang['Enter a current authenticator code to generate new recovery codes.'] ?? 'Enter a current authenticator code to generate new recovery codes.')); ?></span>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var epochField = document.getElementById('totpClientEpoch');
    var enrollForm = document.getElementById('totpEnrollForm');
    function stampEpoch() {
        if (epochField) {
            epochField.value = String(Math.floor(Date.now() / 1000));
        }
    }
    stampEpoch();
    if (enrollForm) {
        enrollForm.addEventListener('submit', stampEpoch);
    }
    var scriptName = <?php echo json_encode(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/authenticator.php'))); ?>;
    var basePath = scriptName.replace(/\/[^\/]*$/, '/');
    var endpoint = basePath + 'ajax/authenticator-totp.php';
    function post(action, extra) {
        var body = new FormData();
        body.append('action', action);
        if (window.csrfToken) {
            body.append('csrf_token', window.csrfToken);
        }
        if (extra) {
            Object.keys(extra).forEach(function (k) { body.append(k, extra[k]); });
        }
        var run = window.fetchWithCsrf || fetch;
        return run(endpoint, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); });
    }
    function toastMsg(ok, msg) {
        if (typeof window.showToast === 'function') {
            window.showToast(msg || '', ok ? 'success' : 'error', ok ? 2500 : 5000);
        }
    }
    function renderCodes(codes) {
        var mount = document.getElementById('totpRecoveryMount');
        if (!mount || !codes || !codes.length) return;
        var html = '<div class="border-top pt-4"><label class="form-label">Recovery codes</label><p class="text-muted mb-3">Each code can be used only once. Save them now — they will not be shown again.</p><div class="row">';
        codes.forEach(function (c) {
            html += '<div class="col-6 mb-2"><input type="text" class="form-control" readonly value="' + String(c).replace(/[<>"&]/g, '') + '" onclick="this.select();"></div>';
        });
        html += '</div></div>';
        mount.innerHTML = html;
        mount.classList.remove('d-none');
        toastMsg(true, 'New recovery codes generated. Save them now.');
    }
    var regenBtn = document.getElementById('totpRegenBtn');
    if (regenBtn) {
        regenBtn.addEventListener('click', function () {
            var code = (document.getElementById('totpManageCode') || {}).value || '';
            post('regenerate', { code: code }).then(function (data) {
                if (!data.success) { toastMsg(false, data.message || 'Request failed'); return; }
                renderCodes(data.recovery_codes);
            }).catch(function () { toastMsg(false, 'Request failed'); });
        });
    }
    var disableBtn = document.getElementById('totpDisableBtn');
    function disableAuthenticator() {
        var code = (document.getElementById('totpManageCode') || {}).value || '';
        if (!code) {
            toastMsg(false, 'Enter your current authenticator code first.');
            var input = document.getElementById('totpManageCode');
            if (input) { input.focus(); }
            return Promise.resolve(false);
        }
        return post('disable', { code: code }).then(function (data) {
            if (!data.success) {
                toastMsg(false, data.message || 'Request failed');
                return false;
            }
            toastMsg(true, data.message || 'Authenticator disabled.');
            window.setTimeout(function () { window.location.reload(); }, 600);
            return true;
        }).catch(function () {
            toastMsg(false, 'Request failed');
            return false;
        });
    }
    if (disableBtn) {
        disableBtn.addEventListener('click', function () { disableAuthenticator(); });
    }
    var totpToggle = document.getElementById('totpEnabledToggle');
    if (totpToggle) {
        totpToggle.addEventListener('change', function () {
            if (totpToggle.checked) {
                return;
            }
            if (totpToggle.getAttribute('data-can-disable') !== '1') {
                totpToggle.checked = true;
                toastMsg(false, 'Authenticator is required for your role.');
                return;
            }
            disableAuthenticator().then(function (ok) {
                if (!ok) {
                    totpToggle.checked = true;
                }
            });
        });
    }
})();
</script>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include('templates/main-footer.php');
