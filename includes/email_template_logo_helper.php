<?php
/**
 * Shared {LOGO} shortcode for chat/email templates (CID + TinyMCE preview).
 */

if (!function_exists('task_chat_email_logo_resolved_path')) {

function task_chat_email_logo_resolved_path($settings = null) {
    $root = dirname(__DIR__);
    $allowed = array('png', 'jpg', 'jpeg', 'gif');
    $uploaded = ($settings && !empty($settings->email_template_logo))
        ? basename((string) $settings->email_template_logo)
        : '';
    if ($uploaded !== '' && strpos($uploaded, '..') === false) {
        $ext = strtolower(pathinfo($uploaded, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed, true)) {
            $dir = realpath($root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'system-uploads');
            $candidate = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'system-uploads' . DIRECTORY_SEPARATOR . $uploaded;
            $real = realpath($candidate);
            if ($real && $dir && strpos($real, $dir) === 0 && is_file($real)) {
                return $real;
            }
        }
    }
    $default = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'dark-logo.png';
    $realDefault = realpath($default);
    if ($realDefault && is_file($realDefault)) {
        return $realDefault;
    }
    return null;
}

function task_chat_email_logo_https_url($settings = null) {
    global $url;
    $path = task_chat_email_logo_resolved_path($settings);
    if (!$path) {
        return '';
    }
    $uploaded = ($settings && !empty($settings->email_template_logo))
        ? basename((string) $settings->email_template_logo)
        : '';
    if ($uploaded !== '' && strcasecmp(basename($path), $uploaded) === 0) {
        if (!function_exists('getSystemImageUrl')) {
            $helpers = dirname(__DIR__) . '/includes/system_helpers.php';
            if (is_file($helpers)) {
                require_once $helpers;
            }
        }
        if (function_exists('getSystemImageUrl')) {
            $public = getSystemImageUrl($uploaded);
            if (is_string($public) && preg_match('#^https?://#i', $public)) {
                return $public;
            }
        }
    }
    $root = realpath(dirname(__DIR__));
    if (!$root) {
        return '';
    }
    $rel = str_replace('\\', '/', substr($path, strlen($root)));
    $rel = ltrim($rel, '/');
    $base = rtrim((string) $url, '/');
    if ($base === '' || $rel === '') {
        return '';
    }
    return $base . '/' . $rel;
}

function task_chat_email_logo_html($settings = null, $useCid = true) {
    $path = task_chat_email_logo_resolved_path($settings);
    if (!$path) {
        return '';
    }
    $alt = htmlspecialchars(
        (string) (($settings && !empty($settings->company_name)) ? $settings->company_name : 'Logo'),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
    if ($useCid) {
        $src = 'cid:task-email-logo';
    } else {
        $src = task_chat_email_logo_https_url($settings);
        if ($src === '' || !preg_match('#^https?://#i', $src)) {
            return '';
        }
        $src = htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    return '<img data-email-logo="1" class="ts-email-logo" src="' . $src . '" alt="' . $alt . '"'
        . ' style="display:inline-block; border:0; outline:none; text-decoration:none; max-height:60px; width:auto; height:auto;">';
}

function task_chat_email_logo_inline_part($settings = null) {
    $path = task_chat_email_logo_resolved_path($settings);
    if (!$path) {
        return array();
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $types = array(
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
    );
    if (!isset($types[$ext])) {
        return array();
    }
    return array(
        array(
            'path' => $path,
            'cid' => 'task-email-logo',
            'name' => basename($path),
            'type' => $types[$ext],
        ),
    );
}

function task_chat_email_logo_src_is_shortcode($src, $settings = null) {
    $src = html_entity_decode((string) $src, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $src = urldecode($src);
    $src = trim($src);
    if ($src === '') {
        return false;
    }
    if ($src === '{LOGO}' || stripos($src, '{LOGO}') !== false) {
        return true;
    }
    if (stripos($src, 'cid:task-email-logo') !== false) {
        return true;
    }
    if (preg_match('/email_logo_/i', $src)) {
        return true;
    }
    if (preg_match('#assets/images/dark-logo\.png#i', $src)) {
        return true;
    }
    if ($settings && !empty($settings->email_template_logo)) {
        $fn = basename((string) $settings->email_template_logo);
        if ($fn !== '' && stripos($src, $fn) !== false) {
            return true;
        }
    }
    return false;
}

function task_chat_email_logo_collapse_for_storage($html, $settings = null) {
    $html = (string) $html;
    if ($html === '') {
        return $html;
    }
    $html = preg_replace_callback('/<img\b[^>]*>/i', function ($m) use ($settings) {
        $tag = $m[0];
        $isLogo = (bool) preg_match('/data-email-logo\s*=/i', $tag) || preg_match('/ts-email-logo/i', $tag);
        if (!$isLogo) {
            $src = '';
            if (preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/i', $tag, $sm)) {
                $src = $sm[2];
            } elseif (preg_match('/\bsrc\s*=\s*([^\s>]+)/i', $tag, $sm)) {
                $src = trim($sm[1], '"\'');
            }
            $isLogo = $src !== '' && task_chat_email_logo_src_is_shortcode($src, $settings);
        }
        return $isLogo ? '{LOGO}' : $tag;
    }, $html);
    return str_replace(array('{LOGO_LEFT}', '{LOGO_RIGHT}'), '{LOGO}', $html);
}

function task_chat_email_logo_expand_for_editor($html, $settings = null) {
    $html = task_chat_email_logo_collapse_for_storage((string) $html, $settings);
    $preview = task_chat_email_logo_html($settings, false);
    if ($preview === '') {
        return $html;
    }
    return str_replace('{LOGO}', $preview, $html);
}

}

if (!function_exists('email_template_safe_http_url')) {
function email_template_safe_http_url($url) {
    $url = trim((string) $url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        return '#';
    }
    return $url;
}
}

if (!function_exists('email_template_apply_logo_shortcode')) {
function email_template_apply_logo_shortcode($html, $settings = null) {
    $html = task_chat_email_logo_collapse_for_storage((string) $html, $settings);
    $useCid = $settings && !empty($settings->use_smtp);
    return str_replace('{LOGO}', task_chat_email_logo_html($settings, $useCid), $html);
}
}
