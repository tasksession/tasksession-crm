<?php
/**
 * System Helper Functions
 * Provides easy-to-use functions for generating correct URLs
 */

require_once __DIR__ . '/file_type_icons.php';

/**
 * Absolute path to a file in uploads/system-uploads.
 */
function crm_system_upload_path($filename) {
    $safe = basename((string) $filename);
    if ($safe === '') {
        return '';
    }
    if (defined('SITE_ROOT')) {
        return SITE_ROOT . DS . 'uploads' . DS . 'system-uploads' . DS . $safe;
    }
    return dirname(__DIR__) . '/uploads/system-uploads/' . $safe;
}

/**
 * Cache-bust token from file mtime — stable until the logo file changes.
 */
function getSystemImageCacheVersion($filename) {
    $path = crm_system_upload_path($filename);
    return ($path !== '' && is_file($path)) ? (int) filemtime($path) : 0;
}

/**
 * Direct /uploads/system-uploads/ URLs are faster but blocked on many shared hosts.
 * Default: secure_image_handler.php (works everywhere). Set CRM_SYSTEM_UPLOADS_DIRECT
 * to true in config.php only when static system-upload URLs return HTTP 200.
 */
function crm_system_uploads_direct_enabled() {
    if (defined('CRM_SYSTEM_UPLOADS_DIRECT')) {
        return (bool) CRM_SYSTEM_UPLOADS_DIRECT;
    }
    static $localhostDirect = null;
    if ($localhostDirect !== null) {
        return $localhostDirect;
    }
    $host = isset($_SERVER['HTTP_HOST']) ? strtolower((string) $_SERVER['HTTP_HOST']) : '';
    $localhostDirect = ($host === 'localhost'
        || $host === '127.0.0.1'
        || strpos($host, 'localhost:') === 0
        || strpos($host, '127.0.0.1:') === 0);
    return $localhostDirect;
}

/**
 * Get the correct URL for system uploads (logos, etc.)
 * Uses secure_image_handler.php by default; optional direct static URL when enabled.
 *
 * @param string $filename The filename in system-uploads folder
 * @return string The complete URL with cache-bust ?v=mtime when the file exists
 */
function getSystemImageUrl($filename) {
    global $url;
    if (empty($filename)) {
        return '';
    }
    $safe = basename((string) $filename);
    $v = getSystemImageCacheVersion($safe);
    if ($v <= 0) {
        return '';
    }
    if (crm_system_uploads_direct_enabled()) {
        return $url . 'uploads/system-uploads/' . rawurlencode($safe) . '?v=' . $v;
    }
    return $url . 'includes/secure_image_handler.php?src='
        . urlencode($url . 'uploads/system-uploads/' . $safe) . '&v=' . $v;
}

/**
 * Cached URL for a static asset under the site root (e.g. assets/images/favicon.png).
 */
function crm_default_asset_image_url($relativePath) {
    global $url;
    $relativePath = ltrim((string) $relativePath, '/');
    if ($relativePath === '') {
        return '';
    }
    $disk = defined('SITE_ROOT')
        ? SITE_ROOT . DS . str_replace('/', DS, $relativePath)
        : dirname(__DIR__) . '/' . $relativePath;
    $v = is_file($disk) ? (int) filemtime($disk) : 1;
    return $url . $relativePath . '?v=' . $v;
}

/**
 * Resolve a system-uploads brand image URL, or empty when the file is missing.
 */
function crm_resolve_system_brand_url($filename) {
    if (empty($filename)) {
        return '';
    }
    return getSystemImageUrl($filename);
}

/**
 * Sidebar shrink icon: favicon → login logo → default asset.
 */
function crm_resolve_sidebar_favicon_url($faviconFilename, $loginLogoFilename = '') {
    $resolved = crm_resolve_system_brand_url($faviconFilename);
    if ($resolved !== '') {
        return $resolved;
    }
    $resolved = crm_resolve_system_brand_url($loginLogoFilename);
    if ($resolved !== '') {
        return $resolved;
    }
    return crm_default_asset_image_url('assets/images/favicon.png');
}

/**
 * Public 192×192 PNG URL for browser push notifications (always static assets path).
 */
function getPushNotificationPublicIconUrl() {
    global $url;
    $relative = 'assets/images/push-notification-icon.png';
    $disk = defined('SITE_ROOT')
        ? SITE_ROOT . DS . str_replace('/', DS, $relative)
        : dirname(__DIR__) . '/' . $relative;

    if (!function_exists('comon_sync_push_notification_icon')) {
        require_once __DIR__ . '/sync_root_favicon.php';
    }

    if (!is_file($disk) || !is_readable($disk) || comon_push_should_regenerate_assets()) {
        comon_sync_push_notification_icon();
    }

    if (!is_file($disk)) {
        return crm_default_asset_image_url('assets/images/favicon.png');
    }

    $v = (int) filemtime($disk);
    return rtrim((string) $url, '/') . '/' . $relative . '?v=' . $v;
}

/**
 * Public 96×96 monochrome PNG URL for Android Web Push badge (status bar / small icon).
 * Returns null when asset cannot be generated — caller should omit badge (OS/browser fallback).
 *
 * @return string|null
 */
function getPushNotificationBadgeUrl() {
    global $url;
    $relative = 'assets/images/push-notification-badge.png';
    $disk = defined('SITE_ROOT')
        ? SITE_ROOT . DS . str_replace('/', DS, $relative)
        : dirname(__DIR__) . '/' . $relative;

    if (!function_exists('comon_sync_push_notification_badge')) {
        require_once __DIR__ . '/sync_root_favicon.php';
    }

    if (!is_file($disk) || !is_readable($disk) || comon_push_should_regenerate_assets()) {
        comon_sync_push_notification_badge();
    }

    if (!is_file($disk) || !is_readable($disk)) {
        return null;
    }

    $v = (int) filemtime($disk);
    return rtrim((string) $url, '/') . '/' . $relative . '?v=' . $v;
}

/**
 * Get the correct URL for user uploads (requires authentication)
 * This function generates the secure handler URL for user uploads
 * 
 * @param string $filename The filename in user-uploads folder
 * @return string The complete secure URL
 */
function getUserImageUrl($filename) {
    global $url;
    return $url . 'includes/secure_file_handler.php?src=' . urlencode($url . 'uploads/user-uploads/' . $filename);
}

/**
 * Get the correct URL for profile pictures (requires authentication)
 * This function generates the secure handler URL for profile pictures
 * 
 * @param string $filename The filename in profile-pics folder
 * @return string The complete secure URL
 */
function getProfileImageUrl($filename) {
    global $url;
    return $url . 'includes/secure_image_handler.php?src=' . urlencode($url . 'uploads/profile-pics/' . $filename);
}

/**
 * Get thumbnail URL for any image
 * This function generates the thumbnail URL for any image
 * 
 * @param string $filename The filename
 * @param string $folder The folder (user-uploads, system-uploads, profile-pics)
 * @param int $width Thumbnail width (default: 200)
 * @param int $height Thumbnail height (default: 200)
 * @return string The complete thumbnail URL
 */
/**
 * Get file URL for any file type
 * This function generates the secure file URL for any file
 * 
 * @param string $filename The filename
 * @param string $folder The folder (user-uploads, system-uploads, profile-pics)
 * @return string The complete secure file URL
 */
function getFileUrl($filename, $folder = 'user-uploads') {
    global $url;
    return $url . 'includes/secure_file_handler.php?src=' . urlencode($url . 'uploads/' . $folder . '/' . $filename);
}

function getThumbnailUrl($filename, $folder = 'user-uploads', $width = 200, $height = 200) {
    global $url;
    // Use the new secure file handler for thumbnails
    return $url . 'includes/secure_file_handler.php?src=' . urlencode($url . 'uploads/' . $folder . '/' . $filename) . '&thumb=1';
}

/**
 * Thumbnail img URL for files under uploads/ when direct HTTP access is blocked or inconsistent.
 * Serves through secure_file_handler.php using the same session as the CRM (matches project media).
 *
 * @param string $absolute_upload_file_url Full URL whose path includes uploads/...
 */
function getSecureUploadThumbnailUrl($absolute_upload_file_url, $w = 550, $h = 250, $crop = 1) {
    global $url;
    $base = rtrim((string) $url, '/');
    $w = max(1, min(2000, (int) $w));
    $h = max(1, min(2000, (int) $h));
    $crop = $crop ? 1 : 0;
    return $base . '/includes/secure_file_handler.php?src=' . urlencode($absolute_upload_file_url)
        . '&thumb=1&w=' . $w . '&h=' . $h . '&crop=' . $crop;
}

/**
 * Generate a complete image tag for system uploads
 * 
 * @param string $filename The filename in system-uploads folder
 * @param string $alt Alt text for the image
 * @param string $class CSS class for the image
 * @param string $style Inline CSS styles
 * @return string Complete HTML img tag
 */
function getSystemImageTag($filename, $alt = '', $class = '', $style = '') {
    $url = getSystemImageUrl($filename);
    $attributes = [];
    
    if (!empty($alt)) $attributes[] = 'alt="' . htmlspecialchars($alt) . '"';
    if (!empty($class)) $attributes[] = 'class="' . htmlspecialchars($class) . '"';
    if (!empty($style)) $attributes[] = 'style="' . htmlspecialchars($style) . '"';
    
    $attr_string = implode(' ', $attributes);
    return '<img src="' . $url . '" ' . $attr_string . ' />';
}

/**
 * Generate a complete image tag for user uploads
 * 
 * @param string $filename The filename in user-uploads folder
 * @param string $alt Alt text for the image
 * @param string $class CSS class for the image
 * @param string $style Inline CSS styles
 * @return string Complete HTML img tag
 */
function getUserImageTag($filename, $alt = '', $class = '', $style = '') {
    $url = getUserImageUrl($filename);
    $attributes = [];
    
    if (!empty($alt)) $attributes[] = 'alt="' . htmlspecialchars($alt) . '"';
    if (!empty($class)) $attributes[] = 'class="' . htmlspecialchars($class) . '"';
    if (!empty($style)) $attributes[] = 'style="' . htmlspecialchars($style) . '"';
    
    $attr_string = implode(' ', $attributes);
    return '<img src="' . $url . '" ' . $attr_string . ' />';
}

/**
 * Generate a lightbox-enabled image link for system uploads
 * 
 * @param string $filename The filename in system-uploads folder
 * @param string $alt Alt text for the image
 * @param string $class CSS class for the link
 * @param string $style Inline CSS styles
 * @param string $lightbox_group Lightbox group name
 * @return string Complete HTML anchor tag with image
 */
function getSystemImageLightbox($filename, $alt = '', $class = 'img-attachment', $style = 'max-width: 200px; max-height: 200px;', $lightbox_group = 'system-images') {
    $full_url = getSystemImageUrl($filename);
    $thumb_url = getThumbnailUrl($filename, 'system-uploads');
    
    return '<a href="' . $full_url . '" data-lightbox="' . $lightbox_group . '" class="' . $class . '">' .
           '<img src="' . $thumb_url . '" alt="' . htmlspecialchars($alt) . '" style="' . $style . '" />' .
           '</a>';
}

/**
 * Generate a lightbox-enabled image link for user uploads
 * 
 * @param string $filename The filename in user-uploads folder
 * @param string $alt Alt text for the image
 * @param string $class CSS class for the link
 * @param string $style Inline CSS styles
 * @param string $lightbox_group Lightbox group name
 * @return string Complete HTML anchor tag with image
 */
function getUserImageLightbox($filename, $alt = '', $class = 'img-attachment', $style = 'max-width: 200px; max-height: 200px;', $lightbox_group = 'user-images') {
    $full_url = getUserImageUrl($filename);
    $thumb_url = getThumbnailUrl($filename, 'user-uploads');
    
    return '<a href="' . $full_url . '" data-lightbox="' . $lightbox_group . '" class="' . $class . '">' .
           '<img src="' . $thumb_url . '" alt="' . htmlspecialchars($alt) . '" style="' . $style . '" />' .
           '</a>';
}

/**
 * Check if a file exists in system uploads
 * 
 * @param string $filename The filename to check
 * @return bool True if file exists, false otherwise
 */
function systemImageExists($filename) {
    $file_path = dirname(__DIR__) . '/uploads/system-uploads/' . $filename;
    return file_exists($file_path);
}

/**
 * Check if a file exists in user uploads
 * 
 * @param string $filename The filename to check
 * @return bool True if file exists, false otherwise
 */
function userImageExists($filename) {
    $file_path = dirname(__DIR__) . '/uploads/user-uploads/' . $filename;
    return file_exists($file_path);
}

/**
 * Get a list of all system upload files
 * 
 * @param string $extension File extension filter (optional)
 * @return array Array of filenames
 */
function getSystemUploadFiles($extension = null) {
    $pattern = dirname(__DIR__) . '/uploads/system-uploads/*';
    if ($extension) {
        $pattern .= '.' . $extension;
    }
    
    $files = glob($pattern);
    return array_map('basename', $files);
}

/**
 * Get a list of all user upload files
 * 
 * @param string $extension File extension filter (optional)
 * @return array Array of filenames
 */
function getUserUploadFiles($extension = null) {
    $pattern = dirname(__DIR__) . '/uploads/user-uploads/*';
    if ($extension) {
        $pattern .= '.' . $extension;
    }
    
    $files = glob($pattern);
    return array_map('basename', $files);
}

/**
 * Timezone options for settings dropdowns (cached; avoids repeated date_default_timezone_set).
 *
 * @return array<int, array{zone: string, diff_from_GMT: string}>
 */
function crm_timezone_list(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    $utcNow = new DateTime('now', new DateTimeZone('UTC'));
    foreach (timezone_identifiers_list() as $zone) {
        try {
            $tz = new DateTimeZone($zone);
            $local = clone $utcNow;
            $local->setTimezone($tz);
            $cache[] = [
                'zone' => $zone,
                'diff_from_GMT' => 'UTC/GMT ' . $local->format('P'),
            ];
        } catch (Exception $e) {
            continue;
        }
    }

    return $cache;
}

/**
 * User last name — supports last_name column, legacy lastName, or missing column.
 */
if (!function_exists('crm_user_last_name')) {
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

/**
 * Batch profile picture URLs by user id (one query).
 *
 * @return array<int, string>
 */
function crm_batch_profile_pic_urls(array $userIds, int $width = 40, int $height = 40): array
{
    global $database, $url;
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if (empty($userIds)) {
        return [];
    }
    $idsSql = implode(',', $userIds);
    $map = [];
    $query = $database->query("SELECT fkUserId, filename FROM profile_pics WHERE fkUserId IN ($idsSql)");
    if ($query) {
        while ($row = $database->fetchArray($query)) {
            $uid = (int)($row['fkUserId'] ?? 0);
            $filename = (string)($row['filename'] ?? '');
            if ($uid > 0 && $filename !== '') {
                $map[$uid] = getProfilePicUrl($filename, $width, $height);
            }
        }
    }
    $fallback = $url . 'assets/images/upload-img.jpg';
    foreach ($userIds as $uid) {
        if (!isset($map[$uid])) {
            $map[$uid] = $fallback;
        }
    }
    return $map;
}

/**
 * Request-scoped: does table exist? (one SHOW TABLES LIKE per unique name)
 */
function crm_db_table_exists($connect, string $table): bool
{
    if (!isset($GLOBALS['__crm_db_table_cache']) || !is_array($GLOBALS['__crm_db_table_cache'])) {
        $GLOBALS['__crm_db_table_cache'] = [];
    }
    if (!($connect instanceof mysqli)) {
        return false;
    }
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table === '') {
        return false;
    }
    if (array_key_exists($table, $GLOBALS['__crm_db_table_cache'])) {
        return (bool) $GLOBALS['__crm_db_table_cache'][$table];
    }
    $res = @$connect->query("SHOW TABLES LIKE '" . $connect->real_escape_string($table) . "'");
    $GLOBALS['__crm_db_table_cache'][$table] = ($res && $res->num_rows > 0);
    return (bool) $GLOBALS['__crm_db_table_cache'][$table];
}

/**
 * Request-scoped: does column exist on table?
 * Loads all columns for a table once via SHOW COLUMNS FROM `table`.
 */
function crm_db_column_exists($connect, string $table, string $column): bool
{
    if (!isset($GLOBALS['__crm_db_col_cache']) || !is_array($GLOBALS['__crm_db_col_cache'])) {
        $GLOBALS['__crm_db_col_cache'] = [];
    }
    if (!($connect instanceof mysqli)) {
        return false;
    }
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') {
        return false;
    }
    if (!isset($GLOBALS['__crm_db_col_cache'][$table])) {
        $GLOBALS['__crm_db_col_cache'][$table] = [];
        $res = @$connect->query('SHOW COLUMNS FROM `' . $table . '`');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $field = (string) ($row['Field'] ?? '');
                if ($field !== '') {
                    $GLOBALS['__crm_db_col_cache'][$table][$field] = true;
                }
            }
        }
    }
    return !empty($GLOBALS['__crm_db_col_cache'][$table][$column]);
}

function crm_db_column_exists_reset(?string $table = null): void
{
    if (!isset($GLOBALS['__crm_db_col_cache']) || !is_array($GLOBALS['__crm_db_col_cache'])) {
        $GLOBALS['__crm_db_col_cache'] = [];
        return;
    }
    if ($table === null) {
        $GLOBALS['__crm_db_col_cache'] = [];
        return;
    }
    unset($GLOBALS['__crm_db_col_cache'][$table]);
}

/**
 * Ensure settings columns exist using a single SHOW COLUMNS (not N× LIKE).
 *
 * @param array<string, string> $columnsMap column => ALTER TABLE SQL
 */
function crm_ensure_settings_columns($connect, array $columnsMap): void
{
    if (!($connect instanceof mysqli) || $columnsMap === []) {
        return;
    }
    $altered = false;
    foreach ($columnsMap as $column => $alterSql) {
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
        if ($column === '' || crm_db_column_exists($connect, 'settings', $column)) {
            continue;
        }
        @$connect->query($alterSql);
        $altered = true;
    }
    if ($altered) {
        crm_db_column_exists_reset('settings');
    }
}

/**
 * Global Control Panel toggle: browser push (login modal + user Settings → Notifications).
 * Default on when the column is missing.
 */
function comon_browser_push_globally_enabled($settings = null): bool
{
    // Free edition: no browser push modal or push client scripts
    if (function_exists('tasksession_is_free_edition') && tasksession_is_free_edition()) {
        return false;
    }
    if ($settings === null || !is_object($settings)) {
        if (isset($GLOBALS['dash_settings']) && is_object($GLOBALS['dash_settings'])) {
            $settings = $GLOBALS['dash_settings'];
        } elseif (class_exists('settings') || class_exists('Settings')) {
            $settings = settings::findById(1);
        }
    }
    if (!$settings || !isset($settings->browser_push_enabled)) {
        return true;
    }
    return (int) $settings->browser_push_enabled === 1;
}

/**
 * If the browser host is the same site as settings.url (www vs non-www, http vs https),
 * rewrite $url to the current request so AJAX/assets stay same-origin with the session cookie.
 */
function crm_align_public_url_to_request(string $settingsUrl): string
{
    $settingsUrl = trim($settingsUrl);
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host);
    if ($host === '' || $settingsUrl === '') {
        return $settingsUrl;
    }
    $parts = parse_url($settingsUrl);
    if (!is_array($parts) || empty($parts['host'])) {
        return $settingsUrl;
    }
    $settingsHost = strtolower((string) $parts['host']);
    $stripWww = static function (string $h): string {
        $h = preg_replace('/:\d+$/', '', $h);
        return (string) preg_replace('/^www\./i', '', $h);
    };
    if ($stripWww($settingsHost) !== $stripWww($host)) {
        return $settingsUrl;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $path = (string) ($parts['path'] ?? '/');
    if ($path === '') {
        $path = '/';
    }
    if (substr($path, -1) !== '/') {
        $path .= '/';
    }
    return ($https ? 'https://' : 'http://') . $host . $path;
}

/**
 * AJAX / polling / theme CSS — skip heavy global init work (license API, offline sweep, etc.).
 */
function crm_is_lightweight_request(): bool
{
    if (defined('CRM_LIGHTWEIGHT_INIT') && CRM_LIGHTWEIGHT_INIT) {
        return true;
    }
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? ''));
    $requestUri = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
    $markers = [
        '/ajax/',
        '/cron.php',
        '/assets/css/theme-vars.php',
        '/assets/css/theme-fonts.php',
        '/real-chat/',
        '/includes/delete_task.php',
        '/includes/task_details.php',
        '/includes/board_update.php',
        '/includes/task_activity_list.php',
        '/ajax/mega_search.php',
        '/includes/leads/activity_list.php',
        '/vendor/google/gdrive/includes/authenticated_gdrive_proxy.php',
        '/vendor/google/gdrive/includes/download_gdrive_proxy.php',
        '/profile-media-actions.php',
    ];
    foreach ($markers as $marker) {
        if (stripos($script, $marker) !== false || ($requestUri !== '' && stripos($requestUri, $marker) !== false)) {
            return true;
        }
    }

    return false;
}

/**
 * File-session lock: only release on polling/AJAX scripts.
 * Do NOT key off CRM_LIGHTWEIGHT_INIT — the login page sets that flag so
 * license checks are skipped, but the session must stay writable for login().
 */
function crm_should_release_session_lock(): bool
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? $_SERVER['SCRIPT_NAME'] ?? ''));
    $requestUri = str_replace('\\', '/', (string)($_SERVER['REQUEST_URI'] ?? ''));
    if (stripos($script, '/ajax/setup_guide.php') !== false
        || stripos($requestUri, '/ajax/setup_guide.php') !== false
        || stripos($script, '/ajax/authenticator-totp.php') !== false
        || stripos($requestUri, '/ajax/authenticator-totp.php') !== false) {
        return false;
    }
    $markers = [
        '/ajax/',
        '/cron.php',
        '/assets/css/theme-vars.php',
        '/assets/css/theme-fonts.php',
        '/real-chat/',
        '/includes/delete_task.php',
        '/includes/task_details.php',
        '/includes/board_update.php',
        '/includes/task_activity_list.php',
        '/ajax/mega_search.php',
        '/includes/leads/activity_list.php',
        '/vendor/google/gdrive/includes/authenticated_gdrive_proxy.php',
        '/vendor/google/gdrive/includes/download_gdrive_proxy.php',
        '/profile-media-actions.php',
    ];
    foreach ($markers as $marker) {
        if (stripos($script, $marker) !== false || ($requestUri !== '' && stripos($requestUri, $marker) !== false)) {
            return true;
        }
    }

    return false;
}

/**
 * MySQL offset string for CRM timezone (portable — does not require mysql.time_zone tables).
 */
function crm_mysql_offset_for_timezone($timezone): string
{
    $timezone = trim((string) $timezone);
    if ($timezone === '') {
        return '+00:00';
    }
    try {
        return (new DateTime('now', new DateTimeZone($timezone)))->format('P');
    } catch (Exception $e) {
        return '+00:00';
    }
}

/**
 * Align MySQL session time_zone with CRM Settings → Time zone (PHP already uses this via date_default_timezone_set).
 */
function crm_sync_mysql_timezone($connect, $timezone = null): void
{
    if (empty($connect) || !($connect instanceof mysqli)) {
        return;
    }
    if ($timezone === null || $timezone === '') {
        $timezone = date_default_timezone_get();
    }
    $offset = crm_mysql_offset_for_timezone($timezone);
    @mysqli_query($connect, "SET time_zone = '" . mysqli_real_escape_string($connect, $offset) . "'");
}

/**
 * Mark inactive users offline — throttled bulk UPDATE (not per-request full table scan).
 */
function crm_sync_offline_session_status($connect): void
{
    if (empty($connect) || !($connect instanceof mysqli)) {
        return;
    }
    $flag = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crm_offline_sync.flag';
    if (is_file($flag) && (time() - filemtime($flag)) < 300) {
        return;
    }
    $idle = defined('USER_PRESENCE_IDLE_SECONDS') ? (int) USER_PRESENCE_IDLE_SECONDS : 300;
    $cutoff = time() - $idle;
    @mysqli_query($connect, "UPDATE users SET session_status = 'offline' WHERE session_status <> 'offline' AND (last_seen = 0 OR last_seen <= {$cutoff})");
    @touch($flag);
}

/**
 * Ensure google_drive_settings table exists — once per server (flag file cache).
 */
function crm_ensure_gdrive_settings_table($database): void
{
    if (!isset($database) || !is_object($database)) {
        return;
    }
    $flag = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'crm_gdrive_settings_table.flag';
    if (is_file($flag)) {
        return;
    }
    try {
        $table_check = $database->query("SHOW TABLES LIKE 'google_drive_settings'");
        if (!$table_check || crm_db_result_num_rows($database, $table_check) == 0) {
            $create_gdrive_table = "CREATE TABLE `google_drive_settings` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `client_id` varchar(500) DEFAULT NULL,
                `client_secret` varchar(500) DEFAULT NULL,
                `access_token` text DEFAULT NULL,
                `refresh_token` text DEFAULT NULL,
                `token_expiry` timestamp NULL DEFAULT NULL,
                `is_enabled` tinyint(1) DEFAULT 0,
                `storage_mode` varchar(32) NOT NULL DEFAULT 'google_drive',
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $database->query($create_gdrive_table);
            $database->query("INSERT INTO google_drive_settings (is_enabled) VALUES (0)");
        }
        @touch($flag);
    } catch (Exception $e) {
        error_log("Error ensuring Google Drive settings table: " . $e->getMessage());
    }
    crm_ensure_gdrive_storage_mode_column($database);
}

/**
 * Row count helper — works with MySQLDatabase wrapper or raw mysqli result.
 */
function crm_db_result_num_rows($database, $result): int
{
    if (!$result) {
        return 0;
    }
    if (is_object($database) && method_exists($database, 'numRows')) {
        return (int) $database->numRows($result);
    }
    if ($result instanceof mysqli_result) {
        return (int) $result->num_rows;
    }
    return 0;
}

/**
 * Add storage_mode column to google_drive_settings (local_server vs google_drive).
 */
function crm_ensure_gdrive_storage_mode_column($database): void
{
    if (!isset($database) || !is_object($database)) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $table = $database->query("SHOW TABLES LIKE 'google_drive_settings'");
        if (!$table || crm_db_result_num_rows($database, $table) === 0) {
            return;
        }
        $col = $database->query("SHOW COLUMNS FROM google_drive_settings LIKE 'storage_mode'");
        if (!$col || crm_db_result_num_rows($database, $col) === 0) {
            $database->query(
                "ALTER TABLE google_drive_settings ADD COLUMN storage_mode VARCHAR(32) NOT NULL DEFAULT 'google_drive' AFTER is_enabled"
            );
        }
        $done = true;
    } catch (Exception $e) {
        error_log("Error ensuring google_drive_settings.storage_mode: " . $e->getMessage());
    } catch (Throwable $e) {
        error_log("Error ensuring google_drive_settings.storage_mode: " . $e->getMessage());
    }
}

/**
 * Map ?message= on members/clients list pages to a toast flash payload.
 *
 * @param array<string, string> $lang
 * @param 'staff'|'client' $context
 * @return array{type:string,msg:string}|null
 */
function comon_list_page_toast_from_query(array $lang, string $context = 'staff'): ?array
{
    if (!isset($_GET['message']) || $_GET['message'] === '') {
        return null;
    }
    $status = (string) $_GET['message'];
    $deleted = $lang['User has been deleted sucessfully.'] ?? 'User has been deleted successfully.';
    $err = $lang['Error! Please Try Again later.'] ?? 'Error! Please try again later.';
    if ($context === 'client') {
        $addKeys = [
            'add_success' => $lang['Client has been registered successfully!'] ?? 'Client has been registered successfully!',
        ];
    } else {
        $addKeys = [
            'add_success' => $lang['Staff has been registered successfully!'] ?? 'Staff has been registered successfully!',
            'add_admin_success' => $lang['Admin has been registered successfully!'] ?? 'Admin has been registered successfully!',
        ];
    }
    if (isset($addKeys[$status])) {
        return ['type' => 'success', 'msg' => $addKeys[$status]];
    }
    if ($status === 'success') {
        return ['type' => 'success', 'msg' => $deleted];
    }
    if ($status === 'fail') {
        return ['type' => 'error', 'msg' => $err];
    }
    return null;
}
