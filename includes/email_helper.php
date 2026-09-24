<?php
/**
 * Email Helper Class
 * Centralized email sending system that supports both SMTP and PHP mail()
 */

// Include PHPMailer files
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {
    private $settings;
    private $use_smtp;
    private $smtp_config;
    /** @var string Last send/verify error (for admin test UI) */
    private $lastError = '';

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function __construct($settings = null) {
        // If settings are passed directly, use them
        if ($settings !== null) {
            $this->settings = $settings;
        } else {
            // Try to get settings from global variable
            global $settings;
            if (isset($settings)) {
                $this->settings = $settings;
            } else {
                // Load settings from database if not available
                $this->settings = settings::findById(1);
            }
        }
        
        $this->use_smtp = !empty($this->settings->use_smtp);
        $this->smtp_config = [
            'host' => $this->settings->smtp_host ?? '',
            'port' => $this->settings->smtp_port ?? '',
            'username' => $this->settings->smtp_username ?? '',
            'password' => $this->decryptSmtpPassword($this->settings->smtp_password ?? ''),
            'secure' => $this->settings->smtp_secure ?? '',
            'from_name' => $this->settings->smtp_from_name ?? $this->settings->company_name ?? '',
            'from_email' => $this->settings->smtp_from_email ?? $this->settings->system_email ?? ''
        ];
    }

    /**
     * Decrypt SMTP password
     */
    private function decryptSmtpPassword($encrypted_password) {
        return !empty($encrypted_password) ? base64_decode($encrypted_password) : '';
    }

    /**
     * Apply global template shortcodes ({COMPANY_NAME}, {LOGO}) without callers editing every send site.
     */
    private function applyCommonShortcodes($message, $subject, array $inlineParts) {
        $company = (string) ($this->settings->company_name ?? '');
        $common = array(
            '{COMPANY_NAME}' => $company,
            '{WORKSPACE_NAME}' => $company,
        );
        $message = str_replace(array_keys($common), array_values($common), (string) $message);
        $subject = str_replace(array_keys($common), array_values($common), (string) $subject);

        $needsLogo = (stripos($message, '{LOGO}') !== false)
            || (stripos($message, 'data-email-logo') !== false)
            || (stripos($message, 'ts-email-logo') !== false)
            || (stripos($message, 'cid:task-email-logo') !== false);
        if ($needsLogo) {
            $helper = __DIR__ . '/email_template_logo_helper.php';
            if (is_file($helper)) {
                require_once $helper;
            }
            if (function_exists('email_template_apply_logo_shortcode')) {
                $message = email_template_apply_logo_shortcode($message, $this->settings);
            }
            $hasLogoCid = false;
            foreach ($inlineParts as $part) {
                if (!empty($part['cid']) && (string) $part['cid'] === 'task-email-logo') {
                    $hasLogoCid = true;
                    break;
                }
            }
            if (!$hasLogoCid && $this->use_smtp && stripos($message, 'cid:task-email-logo') !== false
                && function_exists('task_chat_email_logo_inline_part')) {
                $inlineParts = array_merge($inlineParts, task_chat_email_logo_inline_part($this->settings));
            }
        }

        return array($message, $subject, $inlineParts);
    }

    /**
     * Send email using centralized system
     */
    public function sendEmail($to, $subject, $message, $headers = '', $cc = '', $inlineParts = []) {
        $inlineParts = is_array($inlineParts) ? $inlineParts : [];
        list($message, $subject, $inlineParts) = $this->applyCommonShortcodes($message, $subject, $inlineParts);
        if ($this->use_smtp && $this->isSmtpConfigured()) {
            $result = $this->sendViaSmtp($to, $subject, $message, $headers, $cc, $inlineParts);
            if (!$result && empty($inlineParts)) {
                return $this->sendViaPhpMail($to, $subject, $message, $headers, $cc);
            }
            return $result;
        }
        if (!empty($inlineParts) && $this->isSmtpConfigured()) {
            return $this->sendViaSmtp($to, $subject, $message, $headers, $cc, $inlineParts);
        }
        return $this->sendViaPhpMail($to, $subject, $message, $headers, $cc);
    }

    /**
     * Check if SMTP is properly configured
     */
    private function isSmtpConfigured() {
        return !empty($this->smtp_config['host']) && 
               !empty($this->smtp_config['port']) && 
               !empty($this->smtp_config['username']) && 
               !empty($this->smtp_config['password']) &&
               !empty($this->smtp_config['from_email']);
    }

    /**
     * Send email via SMTP using PHPMailer
     */
    private function sendViaSmtp($to, $subject, $message, $headers = '', $cc = '', $inlineParts = []) {
        $this->lastError = '';
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->smtp_config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_config['username'];
            $mail->Password = $this->smtp_config['password'];
            $mail->SMTPSecure = $this->smtp_config['secure'];
            $mail->Port = $this->smtp_config['port'];
            $mail->SMTPDebug = 0;
            $mail->Timeout = 15;
            
            // Recipients
            $mail->setFrom($this->smtp_config['from_email'], $this->smtp_config['from_name']);
            // Support multiple recipients passed as comma/semicolon-separated list
            $toList = preg_split('/[;,]+/', (string)$to);
            $addedAny = false;
            foreach ($toList as $addr) {
                $email = trim($addr);
                if ($email === '') {
                    continue;
                }
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $mail->addAddress($email);
                    $addedAny = true;
                }
            }
            if (!$addedAny) {
                throw new Exception('No valid recipient addresses found for SMTP send');
            }

            $ccList = preg_split('/[;,]+/', (string) $cc);
            foreach ($ccList as $ccAddr) {
                $ccEmail = trim($ccAddr);
                if ($ccEmail !== '' && filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($ccEmail);
                }
            }
            
            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = html_entity_decode(strip_tags((string) $message), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            foreach ((array) $inlineParts as $inlinePart) {
                if (empty($inlinePart['path']) || !is_file($inlinePart['path'])) {
                    continue;
                }
                $cid = trim((string) ($inlinePart['cid'] ?? ''), " \t\n\r\0\x0B<>");
                if ($cid === '') {
                    continue;
                }
                $mail->addEmbeddedImage(
                    (string) $inlinePart['path'],
                    $cid,
                    $inlinePart['name'] ?? basename((string) $inlinePart['path']),
                    'base64',
                    $inlinePart['type'] ?? 'application/octet-stream'
                );
            }
            
            return $mail->send();
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            if (isset($mail) && !empty($mail->ErrorInfo)) {
                $this->lastError = $mail->ErrorInfo;
            }
            // Avoid flooding Hostinger root error_log; enable with EMAIL_SMTP_DEBUG=1.
            if ((defined('EMAIL_SMTP_DEBUG') && EMAIL_SMTP_DEBUG) || getenv('EMAIL_SMTP_DEBUG') === '1') {
                error_log("SMTP Email Error: Failed to send email to {$to}. Error: " . $this->lastError);
            }
            return false;
        }
    }

    /**
     * Send email via PHP mail() function
     */
    private function sendViaPhpMail($to, $subject, $message, $headers = '', $cc = '') {
        // Set default headers if not provided
        if (empty($headers)) {
            $headers = 'MIME-Version: 1.0' . "\r\n";
            $headers .= 'Content-type: text/html; charset=utf-8' . "\r\n";
            $headers .= 'From: ' . $this->smtp_config['from_name'] . ' <' . $this->smtp_config['from_email'] . '>' . "\r\n";
        }
        $ccList = preg_split('/[;,]+/', (string) $cc);
        $ccValid = [];
        foreach ($ccList as $ccAddr) {
            $ccEmail = trim($ccAddr);
            if ($ccEmail !== '' && filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                $ccValid[] = $ccEmail;
            }
        }
        if (!empty($ccValid)) {
            $headers .= 'Cc: ' . implode(', ', $ccValid) . "\r\n";
        }

        $this->lastError = '';
        $sent = @mail($to, $subject, $message, $headers);
        if (!$sent) {
            $err = error_get_last();
            $this->lastError = ($err && isset($err['message']) && stripos($err['message'], 'mail()') !== false)
                ? $err['message']
                : 'PHP mail() failed. Configure SMTP in admin email settings.';
            error_log('PHP mail() failed for ' . $to . ': ' . $this->lastError);
        }

        return $sent;
    }

    /**
     * Send email with template variables replacement
     */
    public function sendTemplateEmail($to, $subject, $template, $variables = [], $cc = '') {
        if (!is_array($variables)) {
            $variables = [];
        }
        $company = (string) ($this->settings->company_name ?? '');
        if (!array_key_exists('{COMPANY_NAME}', $variables)) {
            $variables['{COMPANY_NAME}'] = $company;
        }
        if (!array_key_exists('{WORKSPACE_NAME}', $variables)) {
            $variables['{WORKSPACE_NAME}'] = $company;
        }
        $message = strtr($template, $variables);
        return $this->sendEmail($to, $subject, $message, '', $cc);
    }

    /**
     * Get email configuration status
     */
    public function getEmailStatus() {
        if ($this->use_smtp) {
            if ($this->isSmtpConfigured()) {
                return [
                    'type' => 'smtp',
                    'status' => 'configured',
                    'host' => $this->smtp_config['host'],
                    'port' => $this->smtp_config['port']
                ];
            } else {
                return [
                    'type' => 'smtp',
                    'status' => 'incomplete',
                    'message' => 'SMTP settings incomplete'
                ];
            }
        } else {
            return [
                'type' => 'php_mail',
                'status' => 'configured',
                'message' => 'Using PHP mail() function'
            ];
        }
    }

    /**
     * Apply SMTP values from admin form (test before save).
     */
    private function applyTestSmtpOverrides(array $overrides): void
    {
        if (array_key_exists('use_smtp', $overrides)) {
            $this->use_smtp = !empty($overrides['use_smtp']);
        }
        $fieldMap = [
            'smtp_host' => 'host',
            'smtp_port' => 'port',
            'smtp_username' => 'username',
            'smtp_secure' => 'secure',
            'smtp_from_name' => 'from_name',
            'smtp_from_email' => 'from_email',
        ];
        foreach ($fieldMap as $postKey => $cfgKey) {
            if (array_key_exists($postKey, $overrides)) {
                $this->smtp_config[$cfgKey] = trim((string) $overrides[$postKey]);
            }
        }
        if (array_key_exists('smtp_password', $overrides)) {
            $plain = trim((string) $overrides['smtp_password']);
            if ($plain !== '') {
                $this->smtp_config['password'] = $plain;
            } elseif (!empty($this->settings->smtp_password)) {
                $this->smtp_config['password'] = $this->decryptSmtpPassword($this->settings->smtp_password);
            } else {
                $this->smtp_config['password'] = '';
            }
        }
    }

    /**
     * Send for configuration test — no PHP mail() fallback when SMTP is selected.
     */
    private function sendEmailForConfigurationTest($to, $subject, $message): bool
    {
        $this->lastError = '';
        if ($this->use_smtp) {
            if (!$this->isSmtpConfigured()) {
                $this->lastError = 'SMTP settings are incomplete. Fill host, port, username, password, and from email.';
                return false;
            }
            if (!$this->sendViaSmtp($to, $subject, $message)) {
                if ($this->lastError === '') {
                    $this->lastError = 'SMTP authentication or delivery failed.';
                }
                return false;
            }
            return true;
        }
        if (!$this->sendViaPhpMail($to, $subject, $message)) {
            $this->lastError = 'PHP mail() failed to send the test message.';
            return false;
        }
        return true;
    }

    /**
     * Connect and authenticate to SMTP (no email sent).
     */
    public function verifySmtpConnection(array $overrides = []): bool
    {
        $this->lastError = '';
        if (!empty($overrides)) {
            $this->applyTestSmtpOverrides($overrides);
        }
        if (!$this->use_smtp) {
            $this->lastError = 'Select SMTP as the mail service first.';
            return false;
        }
        if (!$this->isSmtpConfigured()) {
            $this->lastError = 'SMTP settings are incomplete. Fill host, port, username, password, and from email.';
            return false;
        }

        $mail = null;
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->smtp_config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_config['username'];
            $mail->Password = $this->smtp_config['password'];
            $secure = strtolower(trim((string) $this->smtp_config['secure']));
            $mail->SMTPSecure = ($secure === '' || $secure === 'none') ? '' : $secure;
            $mail->Port = (int) $this->smtp_config['port'];
            $mail->SMTPDebug = 0;
            $mail->Timeout = 20;

            if (!$mail->smtpConnect()) {
                $this->lastError = $mail->ErrorInfo ?: 'Could not connect to the SMTP server.';
                return false;
            }
            $mail->smtpClose();
            return true;
        } catch (Exception $e) {
            $this->lastError = ($mail !== null && $mail->ErrorInfo !== '')
                ? $mail->ErrorInfo
                : $e->getMessage();
            if (stripos($this->lastError, 'authenticate') !== false) {
                $this->lastError = 'SMTP login failed. Check username and password.';
            }
            return false;
        }
    }

    /**
     * Test email configuration
     */
    public function testEmailConfiguration($test_email, array $overrides = []) {
        if (!empty($overrides)) {
            $this->applyTestSmtpOverrides($overrides);
        }
        $subject = 'Email Configuration Test';
        $message = '<html><body>';
        $message .= '<h2>Email Configuration Test</h2>';
        $message .= '<p>This is a test email to verify your email configuration is working properly.</p>';
        $message .= '<p><strong>Configuration Details:</strong></p>';
        $message .= '<ul>';
        $message .= '<li>Email Type: ' . ($this->use_smtp ? 'SMTP' : 'PHP Mail') . '</li>';
        if ($this->use_smtp) {
            $message .= '<li>SMTP Host: ' . $this->smtp_config['host'] . '</li>';
            $message .= '<li>SMTP Port: ' . $this->smtp_config['port'] . '</li>';
            $message .= '<li>SMTP Username: ' . $this->smtp_config['username'] . '</li>';
        }
        $message .= '<li>From Email: ' . $this->smtp_config['from_email'] . '</li>';
        $message .= '<li>From Name: ' . $this->smtp_config['from_name'] . '</li>';
        $message .= '</ul>';
        $message .= '<p>If you received this email, your email configuration is working correctly!</p>';
        $message .= '</body></html>';
        
        return $this->sendEmailForConfigurationTest($test_email, $subject, $message);
    }
}

// Global email helper instance
$emailHelper = new EmailHelper();
