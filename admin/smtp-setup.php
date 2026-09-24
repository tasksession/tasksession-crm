<?php
// AJAX: test email / verify SMTP — before any HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['test_email']) || isset($_POST['verify_smtp']))) {
    if (!defined('CRM_LIGHTWEIGHT_INIT')) {
        define('CRM_LIGHTWEIGHT_INIT', true);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
    require_once __DIR__ . '/../includes/lib-initialize.php';
    require_once __DIR__ . '/../includes/email_helper.php';
    ob_end_clean();

    $smtpAjaxJson = static function (bool $ok, string $message): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(
            ['ok' => $ok, 'message' => $message],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    };

    if (!($session->isLoggedIn()) || (int) ($_SESSION['accountStatus'] ?? 0) !== 1) {
        $smtpAjaxJson(false, 'Unauthorized.');
    }

    $settings = Settings::findById(1);
    $overrides = [
        'use_smtp' => isset($_POST['use_smtp']) ? (int) $_POST['use_smtp'] : (int) ($settings->use_smtp ?? 0),
        'smtp_from_name' => trim((string) ($_POST['smtp_from_name'] ?? '')),
        'smtp_from_email' => trim((string) ($_POST['smtp_from_email'] ?? '')),
        'smtp_username' => trim((string) ($_POST['smtp_username'] ?? '')),
        'smtp_host' => trim((string) ($_POST['smtp_host'] ?? '')),
        'smtp_port' => trim((string) ($_POST['smtp_port'] ?? '')),
        'smtp_password' => trim((string) ($_POST['smtp_password'] ?? '')),
        'smtp_secure' => trim((string) ($_POST['smtp_secure'] ?? '')),
    ];

    $normalizeSmtpError = static function (string $detail): string {
        $detail = trim($detail);
        if ($detail === '') {
            return 'SMTP verification failed. Check your settings.';
        }
        if (stripos($detail, 'authenticate') !== false) {
            return 'SMTP login failed. Check username and password.';
        }
        return $detail;
    };

    if (isset($_POST['verify_smtp'])) {
        if (empty($overrides['use_smtp'])) {
            $smtpAjaxJson(false, 'Select SMTP as the mail service first.');
        }
        if (
            $overrides['smtp_from_name'] === '' ||
            $overrides['smtp_from_email'] === '' ||
            $overrides['smtp_username'] === '' ||
            $overrides['smtp_host'] === '' ||
            $overrides['smtp_port'] === '' ||
            ($overrides['smtp_password'] === '' && empty($settings->smtp_password))
        ) {
            $smtpAjaxJson(false, 'Please fill in all SMTP fields before verifying.');
        }
        try {
            if ($emailHelper->verifySmtpConnection($overrides)) {
                if (!function_exists('setup_guide_mark_visit')) {
                    require_once dirname(__DIR__) . '/includes/setup_guide.php';
                }
                if (function_exists('setup_guide_mark_visit')) {
                    setup_guide_mark_visit('smtp');
                }
                $smtpAjaxJson(true, 'SMTP connected successfully.');
            }
            $smtpAjaxJson(false, $normalizeSmtpError($emailHelper->getLastError()));
        } catch (Exception $e) {
            $smtpAjaxJson(false, $normalizeSmtpError($e->getMessage()));
        }
    }

    $test_email = trim((string) ($_POST['test_email_address'] ?? ''));
    if ($test_email === '' || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $smtpAjaxJson(false, 'Please enter a valid email address.');
    }

    if (!empty($overrides['use_smtp'])) {
        if (
            $overrides['smtp_from_name'] === '' ||
            $overrides['smtp_from_email'] === '' ||
            $overrides['smtp_username'] === '' ||
            $overrides['smtp_host'] === '' ||
            $overrides['smtp_port'] === '' ||
            ($overrides['smtp_password'] === '' && empty($settings->smtp_password))
        ) {
            $smtpAjaxJson(false, 'Please fill in all SMTP fields before testing.');
        }
    }

    try {
        $result = $emailHelper->testEmailConfiguration($test_email, $overrides);
        if ($result) {
            if (!function_exists('setup_guide_mark_visit')) {
                require_once dirname(__DIR__) . '/includes/setup_guide.php';
            }
            if (function_exists('setup_guide_mark_visit')) {
                setup_guide_mark_visit('smtp');
            }
            $smtpAjaxJson(true, 'Test email sent to ' . $test_email . '.');
        }
        $smtpAjaxJson(false, $normalizeSmtpError($emailHelper->getLastError()));
    } catch (Exception $e) {
        $smtpAjaxJson(false, $normalizeSmtpError($e->getMessage()));
    }
}

ob_start();
include("../includes/lib-initialize.php");

$title = "SMTP Setup | ". $syatem_title;
include("../templates/header.php");

if(!($session->isLoggedIn())){
    redirectTo($url."index");
}
if($_SESSION['accountStatus'] == 2){
    redirectTo($url."client/index");
}
if($_SESSION['accountStatus'] == 3){
    redirectTo($url."staff/index");
}

// load current logged-in user
$id = $session->userId;
$user = User::findById((int)$id);
$username = $user->firstName;
$email = $user->email;
$account_stat = $user->status;

// Always use id=1 for global settings
$settings = Settings::findById(1);
/** @var array{type:string,msg:string}|null Shown via assets/js/toast.js after save */
$toast_flash = null;

// Helper function to encrypt/decrypt SMTP password
function encryptSmtpPassword($password) {
    return !empty($password) ? base64_encode($password) : '';
}

function decryptSmtpPassword($encrypted_password) {
    return !empty($encrypted_password) ? base64_decode($encrypted_password) : '';
}

if (!($session->isLoggedIn()) || $_SESSION['accountStatus'] != 1) {
    redirectTo($url."index");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['test_email']) && !isset($_POST['verify_smtp'])) {
    $use_smtp = isset($_POST['use_smtp']) ? intval($_POST['use_smtp']) : 0;
    $smtp_from_name = trim($_POST['smtp_from_name'] ?? '');
    $smtp_from_email = trim($_POST['smtp_from_email'] ?? '');
    $smtp_username = trim($_POST['smtp_username'] ?? '');
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = trim($_POST['smtp_port'] ?? '');
    $smtp_password = trim($_POST['smtp_password'] ?? '');
    $smtp_secure = trim($_POST['smtp_secure'] ?? '');

    if ($use_smtp) {
        if (
            empty($smtp_from_name) ||
            empty($smtp_from_email) ||
            empty($smtp_username) ||
            empty($smtp_host) ||
            empty($smtp_port) ||
            empty($smtp_password)
        ) {
            $toast_flash = array('type' => 'error', 'msg' => 'Please fill in all SMTP fields.');
        }
    }

    if ($toast_flash === null) {
        // Handle password encryption - only encrypt if password is provided
        $encrypted_password = $settings->smtp_password; // Keep existing if not changed
        if (!empty($smtp_password)) {
            $encrypted_password = encryptSmtpPassword($smtp_password);
        }
        
        $settings->use_smtp = $use_smtp;
        $settings->smtp_from_name = $smtp_from_name;
        $settings->smtp_from_email = $smtp_from_email;
        $settings->smtp_username = $smtp_username;
        $settings->smtp_host = $smtp_host;
        $settings->smtp_port = $smtp_port;
        $settings->smtp_password = $encrypted_password;
        $settings->smtp_secure = $smtp_secure;
        $settings->id = 1; // Always set id to 1 for saving

        if ($settings->save()) {
            $toast_flash = array('type' => 'success', 'msg' => 'SMTP settings saved successfully!');
            // Reload settings from DB to reflect latest values in the form
            $settings = Settings::findById(1);
            // Do not mark setup-guide SMTP done on Save — wait for Verify success or Skip.
        } else {
            $toast_flash = array('type' => 'error', 'msg' => 'Failed to save SMTP settings.');
        }
    }
}

?>
<div class="page-container">
    <div class="container-fluid">
        <div class="row row-eq-height">
            <?php include("../templates/sidebar.php"); ?>
            <div class="page-content" style="padding-bottom:0;">
                <?php include('../templates/top-header.php'); ?>
                <div class="row system-wrap">
                    <?php include("../templates/system-nav.php"); ?>
                    <div class="col-md-9 ss-right">
                        <h2 class="page-title d-flex justify-content-between align-items-center"><?php echo $lang['SMTP Setup']; ?>
                            <a href="smtp-setup" class="primary-btn">
                                <?php echo $lang['Refresh']; ?>
                            </a>
                        </h2>

                        <div class="system-settings-container">
                            <div class="settings-grid">
                                <div class="settings-main">
                                    <!-- Email Configuration -->
                                    <div class="form-group">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4>
                                                    <?php echo $lang['Email Configuration']; ?>
                                                </h4>
                                                <p><?php echo $lang['Configure email delivery settings']; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <form method="post" action="" autocomplete="off" id="smtp-settings-form">
                                                    <div class="form-group">
                                                        <label class="form-label"><?php echo $lang['Mail Service']; ?></label>
                                                        <select name="use_smtp" id="use_smtp" class="form-control">
                                                            <option value="0" <?php if(empty($settings->use_smtp)) echo 'selected'; ?>><?php echo $lang['Default PHP Mail']; ?></option>
                                                            <option value="1" <?php if(!empty($settings->use_smtp)) echo 'selected'; ?>><?php echo $lang['SMTP']; ?></option>
                                                        </select>
                                                        <small class="form-text text-muted"><?php echo $lang['Choose between PHP mail function or SMTP server']; ?></small>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="form-label"><?php echo $lang['From Name']; ?></label>
                                                        <input type="text" name="smtp_from_name" class="form-control" value="<?php echo htmlspecialchars($settings->smtp_from_name ?? ''); ?>" placeholder="<?php echo $lang['Your Company Name']; ?>">
                                                        <small class="form-text text-muted"><?php echo $lang['Name that appears as sender']; ?></small>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label class="form-label"><?php echo $lang['From Email']; ?></label>
                                                        <input type="email" name="smtp_from_email" class="form-control" value="<?php echo htmlspecialchars($settings->smtp_from_email ?? ''); ?>" placeholder="noreply@yourdomain.com">
                                                        <small class="form-text text-muted"><?php echo $lang['Email address that appears as sender']; ?></small>
                                                    </div>
                                                    
                                                    <div class="smtp-only" id="smtp_fields">
                                                        <hr class="my-4">
                                                        <h5 class="mb-3"><?php echo $lang['SMTP Server Settings']; ?></h5>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?php echo $lang['SMTP Username']; ?></label>
                                                            <input type="text" name="smtp_username" class="form-control" value="<?php echo htmlspecialchars($settings->smtp_username ?? ''); ?>" placeholder="<?php echo $lang['Your email or username']; ?>">
                                                            <small class="form-text text-muted"><?php echo $lang['Username for SMTP authentication']; ?></small>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?php echo $lang['SMTP Host']; ?></label>
                                                            <input type="text" name="smtp_host" class="form-control" value="<?php echo htmlspecialchars($settings->smtp_host ?? ''); ?>" placeholder="smtp.gmail.com">
                                                            <small class="form-text text-muted"><?php echo $lang['SMTP server hostname']; ?></small>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?php echo $lang['SMTP Port']; ?></label>
                                                            <input type="number" name="smtp_port" class="form-control" value="<?php echo htmlspecialchars($settings->smtp_port ?? ''); ?>" placeholder="587">
                                                            <small class="form-text text-muted"><?php echo $lang['Port number for SMTP connection']; ?></small>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?php echo $lang['SMTP Password']; ?></label>
                                                            <input type="password" name="smtp_password" class="form-control" value="<?php echo htmlspecialchars(decryptSmtpPassword($settings->smtp_password ?? '')); ?>" placeholder="<?php echo $lang['Your email password or app password']; ?>">
                                                            <small class="form-text text-muted"><?php echo $lang['Password for SMTP authentication']; ?></small>
                                                        </div>
                                                        
                                                        <div class="form-group">
                                                            <label class="form-label"><?php echo $lang['Encryption Type']; ?></label>
                                                            <select name="smtp_secure" class="form-control">
                                                                <option value=""><?php echo $lang['None']; ?></option>
                                                                <option value="ssl" <?php if(($settings->smtp_secure ?? '')=='ssl') echo 'selected'; ?>><?php echo $lang['SSL']; ?></option>
                                                                <option value="tls" <?php if(($settings->smtp_secure ?? '')=='tls') echo 'selected'; ?>><?php echo $lang['TLS']; ?></option>
                                                            </select>
                                                            <small class="form-text text-muted"><?php echo $lang['Encryption method for secure connection']; ?></small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="form-actions mt-4 d-flex flex-wrap col-gap-10">
                                                        <button type="submit" class="btn primary-btn">
                                                            <?php echo $lang['Save Settings']; ?>
                                                        </button>
                                                        <button type="button" class="btn border-btn-a" id="verify-smtp-btn" onclick="verifySmtpConnection(event)">
                                                            <?php echo $lang['Verify SMTP Connection']; ?>
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Test Email Configuration -->
                                    <div class="form-group mt-4">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4>
                                                    <?php echo $lang['Test Email Configuration']; ?>
                                                </h4>
                                                <p><?php echo $lang['Send a test email to verify your settings']; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="form-group">
                                                    <label class="form-label"><?php echo $lang['Test Email Address']; ?></label>
                                                    <div class="input-group">
                                                        <input type="email" id="test_email_input" class="form-control" placeholder="<?php echo $lang['Enter email address to test']; ?>">
                                                        <div class="input-group-append">
                                                            <button type="button" class="btn btn-danger" onclick="sendTestEmail()">
                                                                <?php echo $lang['Send Test Email']; ?>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <small class="form-text text-muted"><?php echo $lang['Enter an email address to test your current configuration']; ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="settings-sidebar">
                                    <!-- Current Status -->
                                    <div class="form-group mb-4">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4>
                                                    <?php echo $lang['Current Status']; ?>
                                                </h4>
                                                <p><?php echo $lang['Email service configuration status']; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="status-indicator">
                                                    <div class="status-badge <?php echo !empty($settings->use_smtp) ? 'pending' : 'valid'; ?>">
                                                        <span class="status-dot"></span>
                                                        <span class="status-text">
                                                            <?php echo !empty($settings->use_smtp) ? $lang['SMTP Enabled'] : $lang['PHP Mail Enabled']; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <?php 
                                                // Check PHPMailer availability
                                                $phpmailer_available = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
                                                if (!$phpmailer_available) {
                                                    $phpmailer_paths = [
                                                        __DIR__ . '/../includes/PHPMailer/PHPMailer.php',
                                                        dirname(__DIR__) . '/includes/PHPMailer/PHPMailer.php',
                                                        dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
                                                        dirname(__DIR__) . '/PHPMailer/PHPMailer.php',
                                                        'includes/PHPMailer/PHPMailer.php',
                                                        '../includes/PHPMailer/PHPMailer.php',
                                                        '../vendor/phpmailer/phpmailer/src/PHPMailer.php',
                                                        '../PHPMailer/PHPMailer.php',
                                                        'PHPMailer/PHPMailer.php'
                                                    ];
                                                    
                                                    foreach ($phpmailer_paths as $path) {
                                                        if (file_exists($path)) {
                                                            $phpmailer_available = true;
                                                            break;
                                                        }
                                                    }
                                                }
                                                ?>
                                                
                                                <?php if (!empty($settings->use_smtp) && !$phpmailer_available): ?>
                                                <div class="alert alert-warning mt-3">
                                                    <strong>PHPMailer Not Found:</strong> SMTP is enabled but PHPMailer library is not installed. 
                                                    The system will fall back to PHP mail() function.
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="settings-info mt-3">
                                                    <div class="info-item">
                                                        <span><?php echo $lang['Configure your email service for reliable delivery']; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Popular SMTP Providers -->
                                    <div class="form-group">
                                        <div class="settings-card">
                                            <div class="card-header">
                                                <h4>
                                                    <?php echo $lang['Popular SMTP Providers']; ?>
                                                </h4>
                                                <p><?php echo $lang['Common SMTP server settings']; ?></p>
                                            </div>
                                            <div class="card-body">
                                                <div class="smtp-providers">
                                                    <div class="provider-item" onclick="fillSmtpSettings('gmail')">
                                                        <div class="provider-name">Gmail</div>
                                                        <div class="provider-details">
                                                            <small>Host: smtp.gmail.com</small><br>
                                                            <small>Port: 587 (TLS)</small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="provider-item" onclick="fillSmtpSettings('outlook')">
                                                        <div class="provider-name">Outlook/Hotmail</div>
                                                        <div class="provider-details">
                                                            <small>Host: smtp-mail.outlook.com</small><br>
                                                            <small>Port: 587 (TLS)</small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="provider-item" onclick="fillSmtpSettings('yahoo')">
                                                        <div class="provider-name">Yahoo</div>
                                                        <div class="provider-details">
                                                            <small>Host: smtp.mail.yahoo.com</small><br>
                                                            <small>Port: 587 (TLS)</small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="provider-item" onclick="fillSmtpSettings('sendgrid')">
                                                        <div class="provider-name">SendGrid</div>
                                                        <div class="provider-details">
                                                            <small>Host: smtp.sendgrid.net</small><br>
                                                            <small>Port: 587 (TLS)</small>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="settings-info mt-3">
                                                    <div class="info-item">
                                                        <span><?php echo $lang['Click to auto-fill settings']; ?></span>
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
        <div class="clearfix"></div>
    </div>
</div>

<script>
// Show/hide SMTP fields based on selection
function toggleSmtpFields() {
    var useSmtp = document.getElementById('use_smtp').value;
    var smtpFields = document.getElementById('smtp_fields');
    
    if (useSmtp == '1') {
        smtpFields.style.display = 'block';
    } else {
        smtpFields.style.display = 'none';
    }
}

// Auto-fill SMTP settings for popular providers
function fillSmtpSettings(provider) {
    var settings = {
        'gmail': {
            host: 'smtp.gmail.com',
            port: '587',
            secure: 'tls'
        },
        'outlook': {
            host: 'smtp-mail.outlook.com',
            port: '587',
            secure: 'tls'
        },
        'yahoo': {
            host: 'smtp.mail.yahoo.com',
            port: '587',
            secure: 'tls'
        },
        'sendgrid': {
            host: 'smtp.sendgrid.net',
            port: '587',
            secure: 'tls'
        }
    };
    
    if (settings[provider]) {
        document.querySelector('select[name="use_smtp"]').value = '1';
        document.querySelector('input[name="smtp_host"]').value = settings[provider].host;
        document.querySelector('input[name="smtp_port"]').value = settings[provider].port;
        document.querySelector('select[name="smtp_secure"]').value = settings[provider].secure;
        toggleSmtpFields();
    }
}

// Test email — toast only (no inline alert)
function smtpTestNotify(message, type) {
    var msg = String(message || '').trim();
    if (typeof showToast === 'function') {
        showToast(msg, type === 'success' ? 'success' : 'error');
    }
}

function smtpPostFormJson(extraFields, btn) {
    var form = document.getElementById('smtp-settings-form');
    if (!form) {
        smtpTestNotify('Settings form not found.', 'error');
        return Promise.reject(new Error('no form'));
    }
    var fd = new FormData(form);
    if (extraFields) {
        Object.keys(extraFields).forEach(function(key) {
            fd.append(key, extraFields[key]);
        });
    }
    var originalText = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
    }
    return fetch('smtp-setup', { method: 'POST', body: fd })
        .then(function(response) {
            return response.text().then(function(text) {
                text = (text || '').trim();
                if (text !== '' && text.charAt(0) === '{') {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        return { ok: false, message: 'Invalid server response.' };
                    }
                }
                var ct = response.headers.get('Content-Type') || '';
                if (ct.indexOf('application/json') !== -1 && text !== '') {
                    try {
                        return JSON.parse(text);
                    } catch (e2) {
                        return { ok: false, message: 'Invalid server response.' };
                    }
                }
                return { ok: false, message: 'Unexpected server response.' };
            });
        })
        .finally(function() {
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });
}

function verifySmtpConnection(ev) {
    var btn = ev && ev.target ? ev.target : document.getElementById('verify-smtp-btn');
    var useSmtp = document.getElementById('use_smtp');
    if (useSmtp && useSmtp.value !== '1') {
        smtpTestNotify('Select SMTP as the mail service first.', 'error');
        return;
    }
    if (btn) {
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> <?php echo addslashes($lang['Verifying SMTP…']); ?>';
    }
    smtpPostFormJson({ verify_smtp: '1' }, btn)
        .then(function(data) {
            var msg = (data && data.message) ? String(data.message) : 'Unknown response.';
            smtpTestNotify(msg, data && data.ok ? 'success' : 'error');
            if (data && data.ok) {
                setTimeout(function () { window.location.reload(); }, 700);
            }
        })
        .catch(function(err) {
            smtpTestNotify(err.message || 'Verification failed.', 'error');
        });
}

function sendTestEmail() {
    var testEmail = document.getElementById('test_email_input').value.trim();
    var btn = event.target;

    if (!testEmail) {
        smtpTestNotify('Please enter an email address to test.', 'error');
        return;
    }

    if (!isValidEmail(testEmail)) {
        smtpTestNotify('Please enter a valid email address.', 'error');
        return;
    }
    
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
    btn.disabled = true;

    smtpPostFormJson({ test_email: '1', test_email_address: testEmail }, btn)
    .then(function(data) {
        var msg = (data && data.message) ? String(data.message) : 'Unknown response.';
        smtpTestNotify(msg, data && data.ok ? 'success' : 'error');
        if (data && data.ok) {
            setTimeout(function () { window.location.reload(); }, 700);
        }
    })
    .catch(function(error) {
        smtpTestNotify('Error testing email: ' + error.message, 'error');
    });
}

// Validate email format
function isValidEmail(email) {
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add event listener for SMTP toggle
    var useSmtpElement = document.getElementById('use_smtp');
    if (useSmtpElement) {
        useSmtpElement.addEventListener('change', toggleSmtpFields);
        toggleSmtpFields();
    }
});

// Also try to initialize immediately in case DOM is already loaded
if (document.readyState !== 'loading') {
    var useSmtpElement = document.getElementById('use_smtp');
    if (useSmtpElement) {
        useSmtpElement.addEventListener('change', toggleSmtpFields);
        toggleSmtpFields();
    }
}


</script>

<style>
.smtp-providers {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.provider-item {
    padding: 12px;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.provider-item:hover {
    background: #e9ecef;
    border-color: #007bff;
    transform: translateY(-1px);
}

.provider-name {
    font-weight: 600;
    color: #333;
    margin-bottom: 4px;
}

.provider-details {
    color: #666;
    font-size: 12px;
}

.form-label {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
}

.form-text {
    font-size: 12px;
    color: #6c757d;
}

.form-actions {
    border-top: 1px solid #e9ecef;
    padding-top: 20px;
}

.smtp-only {
    display: none;
}

.smtp-only.show {
    display: block;
}
</style>
<script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
<script src="<?php echo htmlspecialchars(rtrim((string) $url, '/') . '/assets/js/toast.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
<?php include("../templates/main-footer.php"); ?>