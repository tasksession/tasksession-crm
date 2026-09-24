<?php
/**
 * Central SVG icon helper.
 * Source: assets/icons/{name}.svg
 * Markup: ts_icon('delete') or <span class="ts-icon ts-icon-delete"></span>
 */

if (!function_exists('ts_icon_dir')) {
    function ts_icon_dir()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'icons';
    }
}

if (!function_exists('ts_icon_sanitize_name')) {
    function ts_icon_sanitize_name($name)
    {
        $name = strtolower(trim((string) $name));
        if ($name === '' || !preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name)) {
            return '';
        }
        return $name;
    }
}

if (!function_exists('ts_icon_exists')) {
    function ts_icon_exists($name)
    {
        $name = ts_icon_sanitize_name($name);
        if ($name === '') {
            return false;
        }
        return is_file(ts_icon_dir() . DIRECTORY_SEPARATOR . $name . '.svg');
    }
}

if (!function_exists('ts_icon')) {
    /**
     * @param string $name  Icon file name without .svg (e.g. delete, dashboard)
     * @param string $class Extra classes (e.g. h-6, dropdown-toggle-icon h-6)
     */
    function ts_icon($name, $class = '')
    {
        $name = ts_icon_sanitize_name($name);
        if ($name === '' || !ts_icon_exists($name)) {
            return '';
        }
        $extra = '';
        $class = trim((string) $class);
        if ($class !== '') {
            $class = preg_replace('/[^a-zA-Z0-9 _-]/', '', $class);
            $class = trim(preg_replace('/\s+/', ' ', (string) $class));
            if ($class !== '') {
                $extra = ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
            }
        }
        return '<span class="ts-icon ts-icon-' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . $extra . '" aria-hidden="true"></span>';
    }
}

if (!function_exists('ts_icon_inline')) {
    /**
     * Inline SVG from assets/icons/{name}.svg (login/public pages that do not load icons.php).
     */
    function ts_icon_inline($name, $class = '')
    {
        $name = ts_icon_sanitize_name($name);
        if ($name === '' || !ts_icon_exists($name)) {
            return '';
        }
        $svg = @file_get_contents(ts_icon_dir() . DIRECTORY_SEPARATOR . $name . '.svg');
        if (!is_string($svg) || $svg === '') {
            return '';
        }
        $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg);
        $svg = str_replace(array('stroke="#000"', 'stroke="#000000"', 'fill="#000"', 'fill="#000000"'), array('stroke="currentColor"', 'stroke="currentColor"', 'fill="currentColor"', 'fill="currentColor"'), $svg);
        $class = trim((string) $class);
        if ($class !== '' && preg_match('/<svg\b/i', $svg)) {
            $safeClass = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
            if (preg_match('/<svg\b[^>]*\bclass="/i', $svg)) {
                $svg = preg_replace('/(<svg\b[^>]*\bclass=")/i', '$1' . $safeClass . ' ', $svg, 1);
            } else {
                $svg = preg_replace('/<svg\b/i', '<svg class="' . $safeClass . '"', $svg, 1);
            }
        }
        return trim($svg);
    }
}

if (!function_exists('ts_icons_cache_version')) {
    function ts_icons_cache_version()
    {
        static $version = null;
        if ($version !== null) {
            return $version;
        }
        $cssDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css';
        $generated = $cssDir . DIRECTORY_SEPARATOR . 'icons.generated.css';
        $cssFile = $cssDir . DIRECTORY_SEPARATOR . 'icons.php';
        $version = 0;
        // Prefer generated file mtime (avoids globbing every SVG for ?v=).
        if (is_file($generated)) {
            $version = (int) @filemtime($generated);
        }
        $cssMtime = is_file($cssFile) ? (int) @filemtime($cssFile) : 0;
        if ($cssMtime > $version) {
            $version = $cssMtime;
        }
        // Fallback when cache not built yet: scan SVG mtimes once.
        if ($version < 1 || !is_file($generated)) {
            $dir = ts_icon_dir();
            if (is_dir($dir)) {
                $files = glob($dir . DIRECTORY_SEPARATOR . '*.svg');
                if (is_array($files)) {
                    foreach ($files as $file) {
                        $mtime = (int) @filemtime($file);
                        if ($mtime > $version) {
                            $version = $mtime;
                        }
                    }
                }
            }
        }
        if ($version < 1) {
            $version = time();
        }
        return $version;
    }
}

if (!function_exists('ts_password_toggle_html')) {
    /**
     * Login-style show/hide password control (inline SVG, no icons.php dependency).
     */
    function ts_password_toggle_html($ariaLabel = 'Show password')
    {
        $label = htmlspecialchars((string) $ariaLabel, ENT_QUOTES, 'UTF-8');
        $open = ts_icon_inline('eye');
        $closed = ts_icon_inline('eye-slash');
        return '<span class="eye" role="button" tabindex="0" aria-label="' . $label . '" style="cursor:pointer;">'
            . '<span class="eye-open" aria-hidden="true" style="display:none;">' . $open . '</span>'
            . '<span class="eye-closed" aria-hidden="true" style="display:inline;">' . $closed . '</span>'
            . '</span>';
    }
}
