<?php

// ===============================================================================
// Redirect pages or URL  
// ===============================================================================
function redirectTo($location = NULL) {
    if ($location != NULL) {
        if (function_exists('tasksession_pretty_redirect_location')) {
            $location = tasksession_pretty_redirect_location($location);
        }
        if (!headers_sent()) {
            header('Location: ' . $location, true, 302);
            exit;
        }
        $safe = json_encode((string) $location, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        echo '<script>location.replace(' . $safe . ');</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars((string) $location, ENT_QUOTES, 'UTF-8') . '"></noscript>';
        exit;
    }
}

// ===============================================================================
// Show any message 
// ===============================================================================
function outputMessage($message = "") {
    if (!empty($message)) { 
        return "<p class=\"message\">{$message}</p>";
    } else {
        return "";
    }
}

// ===============================================================================
// Select absolute path from root directory by putting ../
// ===============================================================================
function FindRoot() {
    $times = substr_count($_SERVER["PHP_SELF"], "/");
    $rootaccess = "";
    $i = 1; // if you're working on local computer set it 2, if its on live server set this value to 1
    while ($i < $times) {
        $rootaccess .= "../";
        $i++;
    }
    return $rootaccess;
}
$root = FindRoot();

// ===============================================================================
// It displays current page URL
// ===============================================================================
function curPageURL() {
    $pageURL = "http";
    // if ($_SERVER["HTTPS"] == true) {$pageURL .= "s";}
    $pageURL .= "://";
    if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
    } else {
        $pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
    }
    return $pageURL;
}

// ===============================================================================
// Autoload Classes using spl_autoload_register()
// Resolve from includes/ using absolute paths (VPS PHP-FPM CWD differs from script dir).
// ===============================================================================
spl_autoload_register(function ($class_name) {
    $lower = strtolower($class_name);
    $candidates = array();

    if (defined('LIB_ROOT')) {
        $candidates[] = LIB_ROOT . DS . $lower . '.php';
        $snake = strtolower((string)preg_replace('/([a-z])([A-Z])/', '$1_$2', $class_name));
        if ($snake !== $lower) {
            $candidates[] = LIB_ROOT . DS . $snake . '.php';
        }
    }

    if (defined('SITE_ROOT')) {
        $candidates[] = SITE_ROOT . DS . $lower . '.php';
        if (defined('LIB_ROOT')) {
            $candidates[] = SITE_ROOT . DS . 'includes' . DS . $lower . '.php';
        }
    }

    $candidates[] = $lower . '.php';

    foreach ($candidates as $path) {
        if ($path !== '' && is_file($path)) {
            require_once $path;
            return;
        }
    }

    // TaskSession WooCommerce classes (vendor/woocommerce/includes/)
    if (strpos($lower, 'tasksession') === 0) {
        $map = array(
            'tasksessionecommercemanager' => 'class-tasksession-ecommerce-manager.php',
            'tasksessionwoapiservice' => 'class-tasksession-woo-api.php',
            'tasksessionwoosettings' => 'class-tasksession-woo-settings.php',
            'tasksessionsenderapiservice' => 'class-tasksession-sender-api.php',
        );
        if (isset($map[$lower])) {
            $file = dirname(__DIR__) . '/vendor/woocommerce/includes/' . $map[$lower];
            if (is_file($file)) {
                require_once $file;
            }
        }
    }
});

// ===============================================================================
// This will return Date and Time i.e. January 10, 2025 at 02:22:12
// ===============================================================================
function datetime_to_text($datetime = "") {
    $unixdatetime = strtotime($datetime);
    return strftime("%B %d, %Y at %I:%M %p", $unixdatetime);
}

// ===============================================================================
// This will return only Date i.e. January 10, 2025 at 02:22:12
// ===============================================================================
function date_to_text($date = "") {
    $unixdatetime = strtotime($date);
    return strftime("%B %d, %Y", $unixdatetime);
}
function day_to_text($day = "") {
    $unixdatetime = strtotime($day);
    return strftime("%d", $unixdatetime);
}
function month_to_text($month = "") {
    $unixdatetime = strtotime($month);
    return strftime("%B", $unixdatetime);
}
function year_to_text($year = "") {
    $unixdatetime = strtotime($year);
    return strftime("%Y", $unixdatetime);
}

// ===============================================================================
// Random encrypted activation key for Account activation after account has been created
// ===============================================================================
function actKey($getStr) {
    $actKey = sha1(mt_rand(10000, 99999) . time() . $getStr);
    return $actKey;
}

function randomProId($getStr) {
    $actKey = sha1(mt_rand(100, 999) . $getStr);
    return $actKey;
}

// ===============================================================================
// Format the date by removing zero
// ===============================================================================
function strip_zeros_from_date($marked_string = "") {
    // first remove the marked zeros
    $no_zeros = str_replace("*0", "", $marked_string);
    // then remove any remaining marks
    $cleaned_string = str_replace("*", "", $no_zeros);
    return $cleaned_string;
}

function dobToYears($dob) {
    $currentDate = date("Y-m-d");
    $d1 = new DateTime($dob);
    $d2 = new DateTime($currentDate);
    $diff = $d2->diff($d1);
    return $diff->y . " years old <br />";
}

/* Prevent XSS input - Selective sanitization */
// Only sanitize GET parameters, not POST (to preserve TinyMCE content)
$__filteredGet = filter_input_array(INPUT_GET, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (is_array($__filteredGet)) {
    $_GET = $__filteredGet;
} elseif (!is_array($_GET)) {
    $_GET = array();
}

// For POST data, we'll handle sanitization selectively in specific functions
// This preserves TinyMCE HTML content while still protecting against XSS

/* I prefer not to use $_REQUEST...but for those who do: */
$_REQUEST = (array)$_POST + (array)$_GET + (array)$_REQUEST;

// Enhanced security functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, "UTF-8");
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_integer($value) {
    return filter_var($value, FILTER_VALIDATE_INT) !== false;
}

function generate_csrf_token() {
    if (!empty($_SESSION["csrf_token"])) {
        return $_SESSION["csrf_token"];
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION["csrf_token"] = $token;

    // If the page already released the session lock (session_write_close), a new
    // token written only into the in-memory $_SESSION never reaches the session
    // store — POSTs then fail with 403 Invalid CSRF token (e.g. task_timer.php).
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (@session_start()) {
            $_SESSION["csrf_token"] = $token;
            session_write_close();
        }
    }

    return $token;
}

function validate_csrf_token($token) {
    return isset($_SESSION["csrf_token"]) && hash_equals($_SESSION["csrf_token"], $token);
}

// Safe function for TinyMCE content - allows HTML but removes dangerous scripts
function sanitize_tinymce_content($content) {
    // Remove script tags and event handlers
    $content = preg_replace("/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi", "", $content);
    $content = preg_replace("/on\w+\s*=\s*[\"'][^\"']*[\"']/i", "", $content);
    $content = preg_replace("/javascript:/i", "", $content);
    
    // Allow safe HTML tags
    $allowed_tags = "<p><br><strong><b><em><i><u><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><code><pre><a><img><table><tr><td><th><thead><tbody><tfoot><div><span>";
    
    return strip_tags($content, $allowed_tags);
}

/**
 * Get user avatar data (image or initials)
 * @param int $userId User ID
 * @param string $firstName User's first name
 * @param string $lastName User's last name
 * @param int $width Width for image thumbnail (default: 50)
 * @param int $height Height for image thumbnail (default: 50)
 * @return array Array containing avatar data
 */
function getUserAvatarData($userId, $firstName = '', $lastName = '', $width = 50, $height = 50) {
    global $url, $db1;
    
    try {
        // Try to get profile picture
        $profilePic = '';
        $profilePicSql = "SELECT filename FROM profile_pics WHERE fkUserId = " . (int)$userId . " LIMIT 1";
        $profilePicResult = $db1->query($profilePicSql);
        
        if ($profilePicResult && $db1->num_rows($profilePicResult) > 0) {
            $profilePicRow = $db1->fetch_row($profilePicResult);
            $profilePic = $profilePicRow['filename'];
            
            if (!empty($profilePic)) {
                return [
                    'type' => 'image',
                    'url' => $url . 'includes/thumbnail.php?src=' . urlencode($url . 'uploads/profile-pics/' . $profilePic) . '&w=' . $width . '&h=' . $height,
                    'filename' => $profilePic,
                    'hasImage' => true
                ];
            }
        }
        
        // Generate initials if no profile picture
        $displayName = trim($firstName . ' ' . $lastName);
        $initials = '';
        
        if (!empty($firstName)) {
            $initials = strtoupper(substr($firstName, 0, 1));
            if (!empty($lastName)) {
                $initials .= strtoupper(substr($lastName, 0, 1));
            }
        } else {
            $initials = strtoupper(substr($displayName, 0, 1));
        }
        
        // If still no initials, use 'U' as fallback
        if (empty($initials)) {
            $initials = 'U';
        }
        
        // Get color index (1-8) based on user ID
        $colorIndex = ($userId % 8) + 1;
        
        return [
            'type' => 'initials',
            'initials' => $initials,
            'colorIndex' => $colorIndex,
            'hasImage' => false
        ];
    } catch (Exception $e) {
        // Return fallback data
        return [
            'type' => 'initials',
            'initials' => 'U',
            'colorIndex' => 1,
            'hasImage' => false
        ];
    }
}

/**
 * Generate HTML for user avatar (image or initials)
 * @param int $userId User ID
 * @param string $firstName User's first name
 * @param string $lastName User's last name
 * @param int $width Width for image thumbnail (default: 50)
 * @param int $height Height for image thumbnail (default: 50)
 * @param string $cssClass Additional CSS classes
 * @param string $altText Alt text for image
 * @param string $loading Img loading: lazy|eager|auto (initials ignore this)
 * @return string HTML string for avatar
 */
function getUserAvatarHtml($userId, $firstName = '', $lastName = '', $width = 50, $height = 50, $cssClass = '', $altText = 'User Avatar', $loading = 'lazy') {
    try {
        $avatarData = getUserAvatarData($userId, $firstName, $lastName, $width, $height);
        
        if ($avatarData['type'] === 'image') {
            $class = 'img-fluid rounded-circle ' . $cssClass;
            $loadingAttr = '';
            $loadingNorm = strtolower(trim((string) $loading));
            if ($loadingNorm === 'lazy' || $loadingNorm === 'eager' || $loadingNorm === 'auto') {
                $loadingAttr = ' loading="' . $loadingNorm . '" decoding="async"';
            }
            return '<img src="' . htmlspecialchars($avatarData['url']) . '" width="' . $width . '" height="' . $height . '" class="' . $class . '" alt="' . htmlspecialchars($altText) . '"' . $loadingAttr . '>';
        } else {
            // Determine size class based on dimensions
            $sizeClass = '';
            if ($width <= 30) {
                $sizeClass = 'avatar-initials-small';
            } elseif ($width <= 40) {
                $sizeClass = 'avatar-initials-medium';
            } elseif ($width <= 60) {
                $sizeClass = 'avatar-initials-large';
            } else {
                $sizeClass = 'avatar-initials-xlarge';
            }
            
            $class = 'avatar-initials color-' . $avatarData['colorIndex'] . ' ' . $sizeClass . ' ' . $cssClass;
            return '<div class="' . $class . '">' . htmlspecialchars($avatarData['initials']) . '</div>';
        }
    } catch (Exception $e) {
        // Fallback to simple initials if there's an error
        $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
        if (empty($initials)) $initials = 'U';
        return '<div class="avatar-initials color-1 avatar-initials-medium">' . htmlspecialchars($initials) . '</div>';
    }
}

/**
 * Generate simple avatar with initials
 * @param string $name User name
 * @param int $size Size in pixels
 * @return string HTML for simple avatar
 */
function getSimpleAvatar($name, $size = 50) {
    $initials = strtoupper(substr($name, 0, 1));
    if (empty($initials)) $initials = 'U';
    
    $colorIndex = (crc32($name) % 8) + 1;
    $sizeClass = $size <= 30 ? 'avatar-initials-small' : ($size <= 40 ? 'avatar-initials-medium' : ($size <= 60 ? 'avatar-initials-large' : 'avatar-initials-xlarge'));
    
    return '<div class="avatar-initials color-' . $colorIndex . ' ' . $sizeClass . '" style="width: ' . $size . 'px; height: ' . $size . 'px;">' . htmlspecialchars($initials) . '</div>';
}

if (!function_exists('crm_user_last_name')) {
    /**
     * User last name — supports last_name column, legacy lastName, or missing column.
     */
    function crm_user_last_name($user): string
    {
        if ($user === null || $user === false) {
            return '';
        }
        if (is_array($user)) {
            return trim((string)($user['lastName'] ?? $user['last_name'] ?? ''));
        }
        return trim((string)($user->lastName ?? $user->last_name ?? ''));
    }
}
