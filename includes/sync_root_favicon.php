<?php
/**
 * Browser GET /favicon.ico by default — nginx logs 404 if file missing at site root.
 * Copy current settings favicon (uploads) or default PNG to SITE_ROOT/favicon.ico.
 * Call after logo/favicon settings are saved (logo-settings.php, system-settings.php).
 *
 * @param string|null $favicon_image_filename Value from settings.favicon_image; null = read DB
 * @return bool True if root favicon.ico was written
 */
function comon_sync_root_favicon_file($favicon_image_filename = null)
{
    $root = defined('SITE_ROOT') ? rtrim((string) SITE_ROOT, '/\\') : dirname(__DIR__);
    $dest = $root . DIRECTORY_SEPARATOR . 'favicon.ico';
    $uploadDir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'system-uploads' . DIRECTORY_SEPARATOR;

    if ($favicon_image_filename === null) {
        $favicon_image_filename = '';
        if (class_exists('settings')) {
            $s = settings::findById(1);
            if ($s && !empty($s->favicon_image)) {
                $favicon_image_filename = (string) $s->favicon_image;
            }
        }
    } else {
        $favicon_image_filename = (string) $favicon_image_filename;
    }

    $custom = $uploadDir . $favicon_image_filename;
    if ($favicon_image_filename !== '' && is_file($custom) && is_readable($custom)) {
        return (bool) @copy($custom, $dest);
    }

    $default = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'favicon.png';
    if (is_file($default) && is_readable($default)) {
        return (bool) @copy($default, $dest);
    }

    return false;
}

/**
 * Collect brand image filenames from settings (same priority as sidebar favicon).
 *
 * @param string|null $preferredFilename Explicit favicon filename (e.g. on logo save)
 * @return string[]
 */
function comon_push_collect_brand_filename_candidates($preferredFilename = null)
{
    $ordered = array();
    if ($preferredFilename !== null && trim((string) $preferredFilename) !== '') {
        $ordered[] = trim((string) $preferredFilename);
    }
    if (class_exists('settings')) {
        $s = settings::findById(1);
        if ($s) {
            foreach (array('favicon_image', 'logo', 'login_page_logo', 'mobile_logo') as $field) {
                $val = isset($s->$field) ? trim((string) $s->$field) : '';
                if ($val !== '') {
                    $ordered[] = $val;
                }
            }
        }
    }
    return array_values(array_unique($ordered));
}

/**
 * Resolve brand image path for push asset generation (never use generated push PNG as source).
 *
 * @param string|null $preferredFilename Value from settings.favicon_image; null = read DB
 * @param string|null $root
 * @param string|null $uploadDir
 * @return string
 */
function comon_push_resolve_brand_source_path($preferredFilename = null, $root = null, $uploadDir = null)
{
    if ($root === null) {
        $root = defined('SITE_ROOT') ? rtrim((string) SITE_ROOT, '/\\') : dirname(__DIR__);
    }
    if ($uploadDir === null) {
        $uploadDir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'system-uploads' . DIRECTORY_SEPARATOR;
    }

    foreach (comon_push_collect_brand_filename_candidates($preferredFilename) as $filename) {
        $path = $uploadDir . $filename;
        if (is_file($path) && is_readable($path)) {
            return $path;
        }
    }

    $fallbacks = array(
        $root . DIRECTORY_SEPARATOR . 'favicon.ico',
        $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'favicon.png',
    );
    foreach ($fallbacks as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * @deprecated Use comon_push_resolve_brand_source_path()
 */
function comon_push_resolve_favicon_source_path($favicon_image_filename, $root, $uploadDir)
{
    return comon_push_resolve_brand_source_path($favicon_image_filename, $root, $uploadDir);
}

/**
 * Whether push PNG assets should be regenerated from the current brand source.
 */
function comon_push_should_regenerate_assets($preferredFilename = null)
{
    $root = defined('SITE_ROOT') ? rtrim((string) SITE_ROOT, '/\\') : dirname(__DIR__);
    $sourcePath = comon_push_resolve_brand_source_path($preferredFilename, $root, null);
    if ($sourcePath === '') {
        return false;
    }
    $sourceMtime = (int) @filemtime($sourcePath);
    if ($sourceMtime <= 0) {
        return false;
    }
    $iconPath = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'push-notification-icon.png';
    $badgePath = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'push-notification-badge.png';
    $iconMtime = is_file($iconPath) ? (int) @filemtime($iconPath) : 0;
    $badgeMtime = is_file($badgePath) ? (int) @filemtime($badgePath) : 0;
    return $sourceMtime > $iconMtime || $sourceMtime > $badgeMtime;
}

/**
 * Load image resource from path (best-effort).
 *
 * @param string $sourcePath
 * @return resource|false
 */
function comon_push_load_image_resource($sourcePath)
{
    $info = @getimagesize($sourcePath);
    $type = $info ? (int) $info[2] : 0;
    if ($type === IMAGETYPE_PNG) {
        return @imagecreatefrompng($sourcePath);
    }
    if ($type === IMAGETYPE_JPEG) {
        return @imagecreatefromjpeg($sourcePath);
    }
    if ($type === IMAGETYPE_GIF) {
        return @imagecreatefromgif($sourcePath);
    }
    if ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
        return @imagecreatefromwebp($sourcePath);
    }
    return false;
}

/**
 * Generate public 192×192 PNG for Web Push notifications from favicon / logo source.
 *
 * @param string|null $favicon_image_filename Value from settings.favicon_image; null = read DB
 * @return bool True if push-notification-icon.png was written
 */
function comon_sync_push_notification_icon($favicon_image_filename = null)
{
    $root = defined('SITE_ROOT') ? rtrim((string) SITE_ROOT, '/\\') : dirname(__DIR__);
    $dest = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'push-notification-icon.png';
    $uploadDir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'system-uploads' . DIRECTORY_SEPARATOR;

    $sourcePath = comon_push_resolve_brand_source_path($favicon_image_filename, $root, $uploadDir);

    if (!function_exists('imagecreatetruecolor')) {
        return is_file($dest);
    }

    $size = 192;
    $dst = imagecreatetruecolor($size, $size);
    if (!$dst) {
        return false;
    }

    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $transparent);

    if ($sourcePath !== '') {
        $src = comon_push_load_image_resource($sourcePath);
        if ($src) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            if ($sw > 0 && $sh > 0) {
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, $sw, $sh);
            }
            imagedestroy($src);
        }
    }

    $destDir = dirname($dest);
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0755, true);
    }

    $ok = imagepng($dst, $dest);
    imagedestroy($dst);

    if ($ok) {
        comon_sync_push_notification_badge($favicon_image_filename);
    }

    return (bool) $ok;
}

/**
 * Generate monochrome 96×96 PNG for Android Web Push badge (status bar / small icon).
 * White glyph on transparent background — OS may still apply its own styling.
 *
 * @param string|null $favicon_image_filename Value from settings.favicon_image; null = read DB
 * @return bool True if push-notification-badge.png was written
 */
function comon_sync_push_notification_badge($favicon_image_filename = null)
{
    $root = defined('SITE_ROOT') ? rtrim((string) SITE_ROOT, '/\\') : dirname(__DIR__);
    $dest = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'push-notification-badge.png';
    $uploadDir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'system-uploads' . DIRECTORY_SEPARATOR;

    $sourcePath = comon_push_resolve_brand_source_path($favicon_image_filename, $root, $uploadDir);

    if (!function_exists('imagecreatetruecolor')) {
        return is_file($dest);
    }

    $size = 96;
    $work = imagecreatetruecolor($size, $size);
    if (!$work) {
        return false;
    }

    imagealphablending($work, false);
    imagesavealpha($work, true);
    $transparent = imagecolorallocatealpha($work, 0, 0, 0, 127);
    imagefill($work, 0, 0, $transparent);

    if ($sourcePath !== '') {
        $src = comon_push_load_image_resource($sourcePath);
        if ($src) {
            $sw = imagesx($src);
            $sh = imagesy($src);
            if ($sw > 0 && $sh > 0) {
                imagecopyresampled($work, $src, 0, 0, 0, 0, $size, $size, $sw, $sh);
            }
            imagedestroy($src);
        }
    }

    $mono = imagecreatetruecolor($size, $size);
    if (!$mono) {
        imagedestroy($work);
        return false;
    }
    imagealphablending($mono, false);
    imagesavealpha($mono, true);
    imagefill($mono, 0, 0, $transparent);

    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $rgba = imagecolorat($work, $x, $y);
            $a = ($rgba & 0x7F000000) >> 24;
            if ($a >= 120) {
                continue;
            }
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
            $lum = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
            $sourceOpacity = (127 - $a) / 127;
            $strength = (255 - $lum) / 255 * $sourceOpacity;
            if ($strength < 0.08) {
                continue;
            }
            $gdAlpha = 127 - (int) round($strength * 127);
            $white = imagecolorallocatealpha($mono, 255, 255, 255, $gdAlpha);
            imagesetpixel($mono, $x, $y, $white);
        }
    }

    imagedestroy($work);

    $destDir = dirname($dest);
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0755, true);
    }

    $ok = imagepng($mono, $dest);
    imagedestroy($mono);

    return (bool) $ok;
}
